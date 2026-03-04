<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Ensure data directory exists
if (!is_dir(dirname(DB_PATH))) {
    mkdir(dirname(DB_PATH), 0755, true);
}

try {
    // Connect to SQLite Database
    $db_file = __DIR__ . '/../authority.db';
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- TABLE 1: LICENSES ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS licenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        license_key TEXT UNIQUE NOT NULL,
        client_email TEXT,
        tier TEXT DEFAULT 'Standard',
        max_domains INTEGER DEFAULT 1,
        registered_domains TEXT DEFAULT '[]', -- JSON Array of domains
        status TEXT DEFAULT 'active', -- 'active', 'banned', 'expired'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- TABLE 2: PLATFORM SETTINGS ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_user TEXT NOT NULL,
        admin_pass TEXT NOT NULL,
        ls_api_key TEXT,
        ls_webhook_secret TEXT
    )");

    // --- TABLE 3: SYSTEM SETTINGS (Advanced Control Center) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ls_api_key TEXT,
        ls_webhook_secret TEXT,
        ls_store_id TEXT,
        admin_username TEXT DEFAULT 'admin',
        admin_password TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- DEFAULT SEED FOR PLATFORM SETTINGS (Legacy) ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM platform_settings");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO platform_settings (id, admin_user, admin_pass) VALUES (1, :user, :pass)");
        $stmt->execute([
            ':user' => 'admin',
            ':pass' => password_hash('supersecure', PASSWORD_DEFAULT) // Secure Hashed Password
        ]);
    }

    // --- DEFAULT SEED FOR SYSTEM SETTINGS (New) ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO system_settings (id, admin_username, admin_password) VALUES (1, :user, :pass)");
        $stmt->execute([
            ':user' => 'admin',
            ':pass' => password_hash('supersecure', PASSWORD_DEFAULT) // Secure Hashed Password
        ]);
    }

}
catch (PDOException $e) {
    // Log error securely
    error_log("Database Connection Error: " . $e->getMessage());
    die("System Error: Database unavailable.");
}