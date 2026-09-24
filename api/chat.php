<?php
/**
 * Real-time Chat API Endpoint using lightweight polling
 * GET /api/chat.php?since=123 (fetches messages with ID > since)
 * POST /api/chat.php (posts new message)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sinceId = max(0, (int)($_GET['since'] ?? 0));
    $limit = CHAT_MAX_LIMIT;

    if ($db) {
        if ($sinceId > 0) {
            $query = "SELECT c.id, c.user_id, c.message, c.attachment, c.created_at, u.username, u.avatar
                      FROM chat_messages c JOIN users u ON c.user_id = u.id
                      WHERE c.id > ? ORDER BY c.id ASC LIMIT {$limit}";
            $stmt = $db->prepare($query);
            $stmt->execute([$sinceId]);
            $messages = $stmt->fetchAll();
        } else {
            $query = "SELECT c.id, c.user_id, c.message, c.attachment, c.created_at, u.username, u.avatar
                      FROM chat_messages c JOIN users u ON c.user_id = u.id
                      ORDER BY c.id DESC LIMIT {$limit}";
            $stmt = $db->query($query);
            $messages = array_reverse($stmt->fetchAll());
        }
    } else {
        $jsonDb = getJSONDB();
        $messages = $jsonDb->getChatMessages($sinceId);
    }

    $currentUser = getCurrentUser();

    foreach ($messages as &$msg) {
        $msg['avatar_url'] = getAvatarUrl($msg['avatar'], $msg['username']);
        $msg['formatted_message'] = formatMessage($msg['message']) . renderAttachmentHtml($msg['attachment'] ?? null);
        $msg['time'] = date('H:i', strtotime($msg['created_at']));
        $msg['is_self'] = ($currentUser && $currentUser['id'] == $msg['user_id']);
    }


    // Get active online users (posted or active recently)
    $onlineUsersStmt = $db->query("SELECT id, username, avatar FROM users ORDER BY created_at DESC LIMIT 8");
    $onlineUsers = $onlineUsersStmt->fetchAll();
    foreach ($onlineUsers as &$u) {
        $u['avatar_url'] = getAvatarUrl($u['avatar'], $u['username']);
    }

    jsonResponse([
        'messages' => $messages,
        'online_users' => $onlineUsers,
        'server_time' => time()
    ]);
} elseif ($method === 'POST') {
    requireLogin();

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        jsonResponse(['error' => 'Invalid CSRF token.'], 403);
    }

    $message = trim($_POST['message'] ?? '');
    $currentUser = getCurrentUser();

    if (empty($message)) {
        jsonResponse(['error' => 'Message cannot be empty.'], 400);
    }

    if (strlen($message) > 1000) {
        jsonResponse(['error' => 'Message exceeds maximum length of 1000 characters.'], 400);
    }

    $attachmentPath = null;
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $attachmentPath = handleUpload($_FILES['attachment']);
    }

    if ($db) {
        $stmt = $db->prepare("INSERT INTO chat_messages (user_id, message, attachment) VALUES (?, ?, ?)");
        $stmt->execute([$currentUser['id'], $message, $attachmentPath]);
        $msgId = $db->lastInsertId();
    } else {
        $jsonDb = getJSONDB();
        $msgId = $jsonDb->addChatMessage($currentUser['id'], $message);
    }


    jsonResponse([
        'success' => true,
        'message_id' => $msgId
    ]);
}
