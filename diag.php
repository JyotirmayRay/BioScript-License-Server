<?php
// TEMPORARY DIAGNOSTIC - DELETE AFTER USE
// Access: https://license.bioscript.link/diag.php

header('Content-Type: text/plain');

echo "=== SERVER DIAGNOSTIC ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . PHP_SAPI . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n\n";

// Check config
echo "--- Config ---\n";
$config_path = __DIR__ . '/config.php';
if (file_exists($config_path)) {
    @require_once $config_path;
    echo "config.php: FOUND\n";
    echo "DB_PATH: " . (defined('DB_PATH') ? DB_PATH : 'NOT DEFINED') . "\n";
    echo "SHARED_SECRET: " . (defined('SHARED_SECRET') ? 'DEFINED' : 'NOT DEFINED') . "\n";
}
else {
    echo "config.php: NOT FOUND at $config_path\n";
}

// Check DB create capability
echo "\n--- Database ---\n";
$db_dir = defined('DB_PATH') ? dirname(DB_PATH) : __DIR__;
echo "DB Dir: $db_dir\n";
echo "DB Dir Writable: " . (is_writable($db_dir) ? 'YES' : 'NO') . "\n";
$db_file = defined('DB_PATH') ? DB_PATH : (__DIR__ . '/authority.db');
echo "DB File Exists: " . (file_exists($db_file) ? 'YES' : 'NO') . "\n";

// Try connecting
try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SQLite Connection: OK\n";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
}
catch (Exception $e) {
    echo "SQLite Error: " . $e->getMessage() . "\n";
}

// PHP function availability
echo "\n--- PHP Compatibility ---\n";
echo "str_ends_with: " . (function_exists('str_ends_with') ? 'OK (PHP 8.0+)' : 'MISSING - PHP < 8.0!') . "\n";
echo "password_hash: " . (function_exists('password_hash') ? 'OK' : 'MISSING') . "\n";

// Logs
echo "\n--- Log Files ---\n";
$logs_dir = __DIR__ . '/logs';
if (is_dir($logs_dir)) {
    foreach (glob($logs_dir . '/*.log') as $log) {
        echo basename($log) . ":\n";
        echo @file_get_contents($log) ?: "(empty)\n";
        echo "\n";
    }
}
else {
    echo "logs/ directory: NOT FOUND\n";
}

echo "\n=== END DIAGNOSTIC ===\n";