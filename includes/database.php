<?php
/**
 * Database Connection Wrapper (PDO / JSON Fallback)
 * Supports MySQL / MariaDB (InfinityFree), SQLite, and JSON Fallback
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SimpleJSONDB.php';

function getJSONDB(): SimpleJSONDB {
    static $jsonDb = null;
    if ($jsonDb === null) {
        $jsonDb = new SimpleJSONDB();
    }
    return $jsonDb;
}

function getDBConnection(): ?PDO {
    static $pdo = null;

    if ($pdo === null) {
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
        if ($driver === 'json_fallback') {
            return null;
        }

        try {
            if ($driver === 'sqlite') {
                $dbPath = DB_SQLITE_PATH;
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                $dsn = "sqlite:" . $dbPath;
                $pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES " . DB_CHARSET;
                }
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            }
        } catch (PDOException $e) {
            // Auto fallback to JSON driver locally if PDO driver is missing in current PHP CLI build
            return null;
        }
    }

    return $pdo;
}

