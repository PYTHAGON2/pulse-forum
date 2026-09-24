<?php
/**
 * Threads API Endpoint
 * Handles fetching threads, searching, filtering by category/sort, and creating threads
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$jsonDb = $db ? null : getJSONDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $categorySlug = $_GET['category'] ?? '';
        $sort = $_GET['sort'] ?? 'latest'; // latest, popular, unanswered
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = THREADS_PER_PAGE;
        $offset = ($page - 1) * $limit;

        if ($db) {
            $whereClause = ["1=1"];
            $params = [];

            if (!empty($categorySlug)) {
                $whereClause[] = "c.slug = ?";
                $params[] = $categorySlug;
            }

            if (!empty($search)) {
                $whereClause[] = "(t.title LIKE ? OR p.content LIKE ?)";
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }

            $whereSql = implode(" AND ", $whereClause);

            $orderSql = "t.is_pinned DESC, t.created_at DESC";
            if ($sort === 'popular') {
                $orderSql = "t.is_pinned DESC, views DESC, post_count DESC";
            } elseif ($sort === 'unanswered') {
                $whereClause[] = "post_count <= 1";
                $whereSql = implode(" AND ", $whereClause);
            }

            $countQuery = "SELECT COUNT(DISTINCT t.id) as total 
                           FROM threads t 
                           JOIN categories c ON t.category_id = c.id 
                           LEFT JOIN posts p ON t.id = p.thread_id 
                           WHERE {$whereSql}";
            $stmt = $db->prepare($countQuery);
            $stmt->execute($params);
            $totalThreads = (int)$stmt->fetch()['total'];

            $query = "SELECT t.id, t.title, t.views, t.is_pinned, t.is_locked, t.created_at, t.updated_at,
                             c.name as category_name, c.slug as category_slug, c.color as category_color, c.icon as category_icon,
                             u.username, u.avatar,
                             (SELECT COUNT(*) FROM posts WHERE thread_id = t.id) as post_count,
                             (SELECT p2.created_at FROM posts p2 WHERE p2.thread_id = t.id ORDER BY p2.created_at DESC LIMIT 1) as last_activity
                      FROM threads t
                      JOIN categories c ON t.category_id = c.id
                      JOIN users u ON t.user_id = u.id
                      LEFT JOIN posts p ON t.id = p.thread_id
                      WHERE {$whereSql}
                      GROUP BY t.id
                      ORDER BY {$orderSql}
                      LIMIT {$limit} OFFSET {$offset}";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $threads = $stmt->fetchAll();

            foreach ($threads as &$thread) {
                $thread['avatar_url'] = getAvatarUrl($thread['avatar'], $thread['username']);
                $thread['time_ago'] = timeAgo($thread['created_at']);
                $thread['last_activity_ago'] = timeAgo($thread['last_activity'] ?? $thread['created_at']);
            }

            $categoriesStmt = $db->query("SELECT id, name, slug, description, icon, color, (SELECT COUNT(*) FROM threads WHERE category_id = categories.id) as thread_count FROM categories ORDER BY display_order ASC");
            $categories = $categoriesStmt->fetchAll();
        } else {
            $data = $jsonDb->getThreads($categorySlug, $sort, $search, $page, $limit);
            $threads = $data['threads'];
            $totalThreads = $data['total'];
            $categories = $data['categories'];

            foreach ($threads as &$thread) {
                $thread['avatar_url'] = getAvatarUrl($thread['avatar'], $thread['username']);
                $thread['time_ago'] = timeAgo($thread['created_at']);
                $thread['last_activity_ago'] = timeAgo($thread['last_activity'] ?? $thread['created_at']);
            }
        }

        jsonResponse([
            'threads' => $threads,
            'categories' => $categories,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => max(1, ceil($totalThreads / $limit)),
                'total_items' => $totalThreads
            ]
        ]);
    } elseif ($action === 'detail') {
        $threadId = (int)($_GET['id'] ?? 0);
        if (!$threadId) {
            jsonResponse(['error' => 'Invalid thread ID.'], 400);
        }

        if ($db) {
            $db->prepare("UPDATE threads SET views = views + 1 WHERE id = ?")->execute([$threadId]);
            $stmt = $db->prepare("SELECT t.id, t.title, t.views, t.is_pinned, t.is_locked, t.created_at, t.user_id,
                                         c.id as category_id, c.name as category_name, c.slug as category_slug, c.color as category_color,
                                         u.username, u.avatar, u.reputation
                                  FROM threads t
                                  JOIN categories c ON t.category_id = c.id
                                  JOIN users u ON t.user_id = u.id
                                  WHERE t.id = ?");
            $stmt->execute([$threadId]);
            $thread = $stmt->fetch();
        } else {
            $thread = $jsonDb->getThreadDetail($threadId);
        }

        if (!$thread) {
            jsonResponse(['error' => 'Thread not found.'], 404);
        }

        $thread['avatar_url'] = getAvatarUrl($thread['avatar'], $thread['username']);
        $thread['time_ago'] = timeAgo($thread['created_at']);

        jsonResponse(['thread' => $thread]);
    }
} elseif ($method === 'POST') {
    requireLogin();

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        jsonResponse(['error' => 'Invalid CSRF token.'], 403);
    }

    $title = trim($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $currentUser = getCurrentUser();

    if (strlen($title) < 5 || strlen($title) > 150) {
        jsonResponse(['error' => 'Title must be between 5 and 150 characters.'], 400);
    }

    if (strlen($content) < 10) {
        jsonResponse(['error' => 'Content must be at least 10 characters.'], 400);
    }

    $attachmentPath = null;
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $attachmentPath = handleUpload($_FILES['attachment']);
    }

    if ($db) {
        $stmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Invalid category selected.'], 400);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO threads (category_id, user_id, title) VALUES (?, ?, ?)");
            $stmt->execute([$categoryId, $currentUser['id'], $title]);
            $threadId = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO posts (thread_id, user_id, content, attachment, is_original_post) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$threadId, $currentUser['id'], $content, $attachmentPath]);

            $db->prepare("UPDATE users SET reputation = reputation + 5 WHERE id = ?")->execute([$currentUser['id']]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage() ?: 'Failed to create thread. Please try again.'], 500);
        }
    } else {
        $threadId = $jsonDb->createThread($currentUser['id'], $categoryId, $title, $content);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Thread created successfully!',
        'thread_id' => $threadId
    ]);
}

