<?php
/**
 * Pulse Community Forum & Chat
 * Configuration file
 */

// Environment settings
define('APP_NAME', 'Pulse Forum & Chat');
define('APP_URL', 'http://localhost:8000');
define('APP_ENV', 'development'); // 'development' or 'production'

// Database Credentials (Customize for InfinityFree or Local MySQL)
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'mysql'); // 'mysql' for InfinityFree / MySQL / MariaDB, 'sqlite' for local fallback without mysql server
if (!defined('DB_HOST')) define('DB_HOST', 'sql311.infinityfree.com');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'if0_43000894_pulse');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// SQLite Fallback Path (Used only if DB_DRIVER === 'sqlite')
if (!defined('DB_SQLITE_PATH')) define('DB_SQLITE_PATH', __DIR__ . '/../database/pulse_local.sqlite');


// Security & Sessions
define('SESSION_LIFETIME', 86400 * 7); // 7 days
define('CSRF_TOKEN_KEY', 'pulse_csrf_token');

// Pagination & Limits
define('THREADS_PER_PAGE', 15);
define('POSTS_PER_PAGE', 10);
define('CHAT_MAX_LIMIT', 50);

// Error handling settings
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
