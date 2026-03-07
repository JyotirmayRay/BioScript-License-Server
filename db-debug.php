<?php
/**
 * TEMPORARY DATABASE DIAGNOSTIC SCRIPT
 * ⚠️  DELETE THIS FILE AFTER DEBUGGING — it exposes server paths.
 */

// Only allow access from specific IPs for safety
// Uncomment and fill in your IP to restrict access:
// $allowed = ['YOUR.IP.HERE'];
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowed)) { http_response_code(403); exit('Forbidden'); }

header('Content-Type: text/plain');

echo "=== BioScript DB Diagnostic ===\n\n";

// 1. Config file
$config_path = __DIR__ . '/config.php';
echo "1. config.php exists: " . (file_exists($config_path) ? "YES" : "NO — CRITICAL PROBLEM") . "\n";

if (file_exists($config_path)) {
    require_once $config_path;
    echo "   DB_PATH defined: " . (defined('DB_PATH') ? "YES → " . DB_PATH : "NO — CRITICAL PROBLEM") . "\n";
}

if (!defined('DB_PATH')) {
    echo "\n❌ Cannot continue — DB_PATH not defined.\n";
    exit;
}

// 2. Data directory
$db_dir = dirname(DB_PATH);
echo "\n2. DB directory: $db_dir\n";
echo "   Directory exists: " . (is_dir($db_dir) ? "YES" : "NO") . "\n";
echo "   Directory writable: " . (is_writable($db_dir) ? "YES" : "NO — likely the problem") . "\n";

// 3. DB file itself
echo "\n3. DB file: " . DB_PATH . "\n";
echo "   File exists: " . (file_exists(DB_PATH) ? "YES" : "NO — will be created on first connect") . "\n";
if (file_exists(DB_PATH)) {
    echo "   File readable: " . (is_readable(DB_PATH) ? "YES" : "NO — permission problem") . "\n";
    echo "   File writable: " . (is_writable(DB_PATH) ? "YES" : "NO — permission problem") . "\n";
    echo "   File size: " . filesize(DB_PATH) . " bytes\n";
}

// 4. Try connecting
echo "\n4. PDO SQLite connection test:\n";
try {
    $pdo_test = new PDO('sqlite:' . DB_PATH);
    $pdo_test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_test->exec('PRAGMA journal_mode = wal;');
    echo "   ✅ SUCCESS — connection opened fine.\n";

    // Check tables
    $tables = $pdo_test->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "   Tables found: " . (count($tables) ? implode(', ', $tables) : "NONE (fresh DB)") . "\n";
}
catch (Exception $e) {
    echo "   ❌ FAILED: " . $e->getMessage() . "\n";
}

// 5. PHP/server environment
echo "\n5. Server environment:\n";
echo "   PHP version: " . PHP_VERSION . "\n";
echo "   SQLite3 extension: " . (extension_loaded('pdo_sqlite') ? "LOADED ✅" : "MISSING ❌") . "\n";
echo "   Web server user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : shell_exec('whoami')) . "\n";
echo "   __DIR__: " . __DIR__ . "\n";

echo "\n=== End Diagnostic ===\n";
echo "\n⚠️  DELETE this file from server after reading results!\n";