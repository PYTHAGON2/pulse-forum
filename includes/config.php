<?php
/**
 * Active Configuration file
 */
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

require_once __DIR__ . '/config.example.php';


