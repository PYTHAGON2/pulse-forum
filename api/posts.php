<?php
/**
 * Posts API Endpoint
 * Handles fetching posts for a thread, creating replies, editing, deleting, and upvoting
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$currentUser = getCurrentUser();

if ($method === 'GET') {
    $threadId = (int)($_GET['thread_id'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = POSTS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    if (!$threadId) {
        jsonResponse(['error' => 'Thread ID is required.'], 400);
    }

    // Fetch posts with author profile and vote status for current logged-in user
    $currentUserId = $currentUser ? $currentUser['id'] : 0;
    
    if ($db) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE thread_id = ?");
        $stmt->execute([$threadId]);
        $totalPosts = (int)$stmt->fetch()['total'];

        $query = "SELECT p.id, p.thread_id, p.user_id, p.content, p.attachment, p.upvotes, p.is_original_post, p.created_at, p.updated_at,
                         u.username, u.avatar, u.reputation,
                         (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as user_post_count,
                         (SELECT COUNT(*) FROM votes WHERE post_id = p.id AND user_id = ?) as user_has_voted
                  FROM posts p
                  JOIN users u ON p.user_id = u.id
                  WHERE p.thread_id = ?
                  ORDER BY p.is_original_post DESC, p.created_at ASC
                  LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->prepare($query);
        $stmt->execute([$currentUserId, $threadId]);
        $posts = $stmt->fetchAll();
    } else {
        $jsonDb = getJSONDB();
        $posts = $jsonDb->getPosts($threadId, $currentUserId);
        $totalPosts = count($posts);
        $posts = array_slice($posts, $offset, $limit);
    }


    foreach ($posts as &$post) {
        $post['avatar_url'] = getAvatarUrl($post['avatar'], $post['username']);
        $post['formatted_content'] = formatMessage($post['content']) . renderAttachmentHtml($post['attachment'] ?? null);
        $post['time_ago'] = timeAgo($post['created_at']);
        $post['is_owner'] = ($currentUser && $currentUser['id'] == $post['user_id']);
        $post['user_has_voted'] = (bool)$post['user_has_voted'];
    }


    jsonResponse([
        'posts' => $posts,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalPosts / $limit),
            'total_items' => $totalPosts
        ]
    ]);
} elseif ($method === 'POST') {
    requireLogin();

    $action = $_POST['action'] ?? 'reply';
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        jsonResponse(['error' => 'Invalid CSRF token.'], 403);
    }

    if ($action === 'reply') {
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if (!$threadId || strlen($content) < 3) {
            jsonResponse(['error' => 'Reply content must be at least 3 characters.'], 400);
        }

        $attachmentPath = null;
        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $attachmentPath = handleUpload($_FILES['attachment']);
        }

        if ($db) {
            // Check if thread is locked
            $stmt = $db->prepare("SELECT is_locked FROM threads WHERE id = ?");
            $stmt->execute([$threadId]);
            $thread = $stmt->fetch();

            if (!$thread) {
                jsonResponse(['error' => 'Thread not found.'], 404);
            }

            if ($thread['is_locked']) {
                jsonResponse(['error' => 'This thread is locked for new replies.'], 403);
            }

            $stmt = $db->prepare("INSERT INTO posts (thread_id, user_id, content, attachment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$threadId, $currentUser['id'], $content, $attachmentPath]);
            $postId = $db->lastInsertId();

            // Increment user reputation (+2 for reply)
            $db->prepare("UPDATE users SET reputation = reputation + 2 WHERE id = ?")->execute([$currentUser['id']]);

            // Touch thread updated_at
            $db->prepare("UPDATE threads SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$threadId]);
        } else {
            $jsonDb = getJSONDB();
            $postId = $jsonDb->createReply($threadId, $currentUser['id'], $content);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Reply posted!',
            'post_id' => $postId
        ]);
    } elseif ($action === 'vote') {
        $postId = (int)($_POST['post_id'] ?? 0);

        if (!$postId) {
            jsonResponse(['error' => 'Post ID is required.'], 400);
        }

        // Get post details
        $stmt = $db->prepare("SELECT user_id, upvotes FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post) {
            jsonResponse(['error' => 'Post not found.'], 404);
        }

        // Check existing vote
        $stmt = $db->prepare("SELECT id FROM votes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$currentUser['id'], $postId]);
        $existingVote = $stmt->fetch();

        $db->beginTransaction();
        try {
            if ($existingVote) {
                // Remove vote (toggle off)
                $db->prepare("DELETE FROM votes WHERE id = ?")->execute([$existingVote['id']]);
                $db->prepare("UPDATE posts SET upvotes = GREATEST(0, upvotes - 1) WHERE id = ?")->execute([$postId]);
                $db->prepare("UPDATE users SET reputation = GREATEST(0, reputation - 1) WHERE id = ?")->execute([$post['user_id']]);
                $voted = false;
            } else {
                // Add upvote
                $db->prepare("INSERT INTO votes (user_id, post_id, vote_type) VALUES (?, ?, 1)")->execute([$currentUser['id'], $postId]);
                $db->prepare("UPDATE posts SET upvotes = upvotes + 1 WHERE id = ?")->execute([$postId]);
                $db->prepare("UPDATE users SET reputation = reputation + 1 WHERE id = ?")->execute([$post['user_id']]);
                $voted = true;
            }

            $db->commit();

            // Fetch new total upvotes
            $stmt = $db->prepare("SELECT upvotes FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
            $newUpvotes = (int)$stmt->fetch()['upvotes'];

            jsonResponse([
                'success' => true,
                'voted' => $voted,
                'upvotes' => $newUpvotes
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Failed to process vote.'], 500);
        }
    } elseif ($action === 'edit') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if (!$postId || strlen($content) < 3) {
            jsonResponse(['error' => 'Content must be at least 3 characters.'], 400);
        }

        // Check ownership
        $stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post || $post['user_id'] != $currentUser['id']) {
            jsonResponse(['error' => 'You are not authorized to edit this post.'], 403);
        }

        $stmt = $db->prepare("UPDATE posts SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$content, $postId]);

        jsonResponse([
            'success' => true,
            'message' => 'Post updated successfully!',
            'formatted_content' => formatMessage($content)
        ]);
    } elseif ($action === 'delete') {
        $postId = (int)($_POST['post_id'] ?? 0);

        if (!$postId) {
            jsonResponse(['error' => 'Post ID required.'], 400);
        }

        // Check ownership & if it's original post
        $stmt = $db->prepare("SELECT user_id, is_original_post, thread_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post || $post['user_id'] != $currentUser['id']) {
            jsonResponse(['error' => 'You are not authorized to delete this post.'], 403);
        }

        if ($post['is_original_post']) {
            // If original post is deleted, delete entire thread
            $db->prepare("DELETE FROM threads WHERE id = ?")->execute([$post['thread_id']]);
            jsonResponse([
                'success' => true,
                'message' => 'Thread and all posts deleted.',
                'thread_deleted' => true
            ]);
        } else {
            $db->prepare("DELETE FROM posts WHERE id = ?")->execute([$postId]);
            jsonResponse([
                'success' => true,
                'message' => 'Post deleted successfully.',
                'thread_deleted' => false
            ]);
        }
    }
}
