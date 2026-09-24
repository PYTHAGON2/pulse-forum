<?php
/**
 * Authentication API Endpoint
 * Handles Login, Registration, Logout, Profile updates
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDBConnection();
$jsonDb = $db ? null : getJSONDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token for state-changing requests
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        jsonResponse(['error' => 'Invalid CSRF token. Please refresh the page.'], 403);
    }
}

switch ($action) {
    case 'register':
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            jsonResponse(['error' => 'Username must be 3-30 characters long and contain only letters, numbers, and underscores.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Please enter a valid email address.'], 400);
        }

        if (strlen($password) < 6) {
            jsonResponse(['error' => 'Password must be at least 6 characters long.'], 400);
        }

        if ($db) {
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                jsonResponse(['error' => 'Username or Email is already registered.'], 409);
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash]);
            $userId = $db->lastInsertId();
        } else {
            if ($jsonDb->findUserByUsernameOrEmail($username) || $jsonDb->findUserByUsernameOrEmail($email)) {
                jsonResponse(['error' => 'Username or Email is already registered.'], 409);
            }
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $user = $jsonDb->insertUser($username, $email, $passwordHash);
            $userId = $user['id'];
        }

        // Log user in
        $_SESSION['user_id'] = $userId;

        jsonResponse([
            'success' => true,
            'message' => 'Registration successful! Welcome to Pulse Community.',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'avatar' => getAvatarUrl(null, $username)
            ]
        ]);
        break;

    case 'login':
        $loginInput = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($loginInput) || empty($password)) {
            jsonResponse(['error' => 'Please provide both username/email and password.'], 400);
        }

        if ($db) {
            $stmt = $db->prepare("SELECT id, username, password_hash, avatar FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch();
        } else {
            $user = $jsonDb->findUserByUsernameOrEmail($loginInput);
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['error' => 'Invalid username/email or password.'], 401);
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];

        jsonResponse([
            'success' => true,
            'message' => 'Welcome back, ' . htmlspecialchars($user['username']) . '!',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'avatar' => getAvatarUrl($user['avatar'], $user['username'])
            ]
        ]);
        break;


    case 'logout':
        unset($_SESSION['user_id']);
        session_destroy();
        jsonResponse(['success' => true, 'message' => 'Logged out successfully.']);
        break;

    case 'me':
        $user = getCurrentUser();
        if (!$user) {
            jsonResponse(['logged_in' => false, 'csrf_token' => getCSRFToken()]);
        }
        $user['avatar_url'] = getAvatarUrl($user['avatar'], $user['username']);
        unset($user['password_hash']);
        jsonResponse(['logged_in' => true, 'user' => $user, 'csrf_token' => getCSRFToken()]);
        break;


    default:
        jsonResponse(['error' => 'Invalid action.'], 400);
}
