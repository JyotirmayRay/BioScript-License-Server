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
        installation_fingerprint TEXT,        -- Unique hardware/env hash
        status TEXT DEFAULT 'active',          -- 'active', 'banned', 'expired'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_verified_at DATETIME             -- Track periodic pings
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
    // Modified to be a flexible KV store while keeping core admin fields
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key TEXT UNIQUE,
        value TEXT,
        admin_username TEXT DEFAULT 'admin',
        admin_password TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure columns exist (for migration)
    try {
        $pdo->exec("ALTER TABLE system_settings ADD COLUMN key TEXT");
        $pdo->exec("ALTER TABLE system_settings ADD COLUMN value TEXT");
    } catch (Exception $e) { /* ignore if already exists */ }

    // --- DEFAULT SEED FOR PLATFORM SETTINGS (Legacy) ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM platform_settings");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO platform_settings (id, admin_user, admin_pass) VALUES (1, :user, :pass)");
        $stmt->execute([
            ':user' => 'admin',
            ':pass' => password_hash('supersecure', PASSWORD_DEFAULT)
        ]);
    }

    // --- DEFAULT SEED FOR SYSTEM SETTINGS (New) ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE id = 1");
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO system_settings (id, admin_username, admin_password) VALUES (1, :user, :pass)");
        $stmt->execute([
            ':user' => 'admin',
            ':pass' => password_hash('supersecure', PASSWORD_DEFAULT)
        ]);
        
        // Initial Whitelist
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO system_settings (key, value) VALUES ('whitelist_domains', :val)");
        $stmt->execute([':val' => json_encode(['bioscript.link'])]);
    }

}
catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("System Error: Database unavailable.");
}