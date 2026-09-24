<?php
/**
 * Users / Profiles API Endpoint
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$username = trim($_GET['username'] ?? '');

if (empty($username)) {
    jsonResponse(['error' => 'Username parameter is required.'], 400);
}

// Fetch user profile
$stmt = $db->prepare("SELECT id, username, avatar, bio, reputation, created_at FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(['error' => 'User not found.'], 404);
}

$user['avatar_url'] = getAvatarUrl($user['avatar'], $user['username']);
$user['joined_date'] = date('M Y', strtotime($user['created_at']));

// Fetch post count and thread count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE user_id = ?");
$stmt->execute([$user['id']]);
$user['post_count'] = (int)$stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM threads WHERE user_id = ?");
$stmt->execute([$user['id']]);
$user['thread_count'] = (int)$stmt->fetch()['total'];

// Fetch recent activity / threads created by user
$stmt = $db->prepare("SELECT t.id, t.title, t.created_at, c.name as category_name, c.slug as category_slug, c.color as category_color 
                       FROM threads t 
                       JOIN categories c ON t.category_id = c.id 
                       WHERE t.user_id = ? 
                       ORDER BY t.created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recentThreads = $stmt->fetchAll();

foreach ($recentThreads as &$rt) {
    $rt['time_ago'] = timeAgo($rt['created_at']);
}

jsonResponse([
    'user' => $user,
    'recent_threads' => $recentThreads
]);
