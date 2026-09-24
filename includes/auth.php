<?php
/**
 * Authentication and Session Helper
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';


// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Generate CSRF Token if not exists
 */
function getCSRFToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken(?string $token): bool {
    if (!$token || empty($_SESSION[CSRF_TOKEN_KEY])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_KEY], $token);
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get logged in user details
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    static $currentUser = null;
    if ($currentUser !== null) {
        return $currentUser;
    }

    $db = getDBConnection();
    if ($db) {
        $stmt = $db->prepare("SELECT id, username, email, avatar, bio, reputation, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch() ?: null;
    } else {
        $jsonDb = getJSONDB();
        $currentUser = $jsonDb->findUserById($_SESSION['user_id']);
    }
    
    if (!$currentUser) {
        // Invalid session
        unset($_SESSION['user_id']);
    }

    return $currentUser;
}


/**
 * Require login for protected actions
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required.']);
            exit;
        } else {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
}
