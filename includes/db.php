<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Ensure data directory exists
if (!is_dir(dirname(DB_PATH))) {
    mkdir(dirname(DB_PATH), 0755, true);
}

try {
    // Connect to SQLite Database
    $pdo = new PDO('sqlite:' . DB_PATH);
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

    // --- TABLE 3: ACTIVATIONS (Track individual activations) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS activations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        license_id INTEGER NOT NULL,
        domain TEXT NOT NULL,
        fingerprint TEXT,
        activated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_ping DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'active'
    )");

    // --- TABLE 4: ORDERS (Log purchase events) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_number TEXT UNIQUE,
        client_email TEXT,
        license_key TEXT,
        product_name TEXT,
        order_status TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure UNIQUE constraint on 'key' column if possible (SQLite specific migration)
    try {
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_settings_key ON system_settings(key)");
    }
    catch (Exception $e) {
    }

    // Ensure columns exist (for migration)
    try {
        $pdo->exec("ALTER TABLE system_settings ADD COLUMN id INTEGER PRIMARY KEY AUTOINCREMENT");
    }
    catch (Exception $e) {
    }
    try {
        $pdo->exec("ALTER TABLE system_settings ADD COLUMN key TEXT");
    }
    catch (Exception $e) {
    }
    try {
        $pdo->exec("ALTER TABLE system_settings ADD COLUMN value TEXT");
    }
    catch (Exception $e) {
    }

    // --- DEFAULT SEED FOR PLATFORM SETTINGS (Legacy) ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM platform_settings");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO platform_settings (id, admin_user, admin_pass) VALUES (1, :user, :pass)");
        $stmt->execute([
            ':user' => 'admin',
            ':pass' => password_hash('b1oScR1pT_F0rtress_2026_#2bf57f09', PASSWORD_DEFAULT)
        ]);
    }
    else {
        // MIGRATION: Secure legacy plaintext passwords if they exist
        $stmt = $pdo->query("SELECT admin_pass FROM platform_settings WHERE id = 1");
        $admin = $stmt->fetch();
        if ($admin && (password_get_info($admin['admin_pass'])['algo'] === 0 || $admin['admin_pass'] === 'supersecure')) {
            $new_hash = password_hash('b1oScR1pT_F0rtress_2026_#2bf57f09', PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE platform_settings SET admin_pass = ? WHERE id = 1")->execute([$new_hash]);
        }
    }

    // --- DEFAULT SEED FOR SYSTEM SETTINGS (New) ---
    try {
        // Query a generic field to check existence
        $stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
        $all = $stmt->fetchAll();
        $has_row1 = false;
        if ($all) {
            foreach ($all as $row) {
                // Check if it's the admin row without assuming 'id' exists
                if (($row['admin_username'] ?? '') === 'admin') {
                    $has_row1 = true;
                    break;
                }
            }
        }
    }
    catch (Exception $e) {
        $has_row1 = false;
    }

    if (!$has_row1) {
        try {
            // Seed without explicit ID
            $stmt = $pdo->prepare("INSERT INTO system_settings (admin_username, admin_password) VALUES (:user, :pass)");
            $stmt->execute([
                ':user' => 'admin',
                ':pass' => password_hash('b1oScR1pT_F0rtress_2026_#2bf57f09', PASSWORD_DEFAULT)
            ]);

            // Initial Whitelist
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO system_settings (key, value) VALUES ('whitelist_domains', :val)");
            $stmt->execute([':val' => json_encode(['bioscript.link'])]);
        }
        catch (Exception $e) {
        }
    }

}
catch (PDOException $e) {
    // Log detailed error for diagnostic, but show generic message to user
    $error_msg = "[" . date('Y-m-d H:i:s') . "] DB Failure: " . $e->getMessage() . " | Path: " . (defined('DB_PATH') ? DB_PATH : 'UNDEFINED') . "\n";
    @file_put_contents(__DIR__ . '/../logs/db_error.log', $error_msg, FILE_APPEND);
    error_log($error_msg); // Also send to PHP system log
    die("System Error: Database unavailable.");
}       }
    }

}
catch (PDOException $e) {
    // Log detailed error for diagnostic, but show generic message to user
    $error_msg = "[" . date('Y-m-d H:i:s') . "] DB Failure: " . $e->getMessage() . " | Path: " . (defined('DB_PATH') ? DB_PATH : 'UNDEFINED') . "\n";
    @file_put_contents(__DIR__ . '/../logs/db_error.log', $error_msg, FILE_APPEND);
    error_log($error_msg); // Also send to PHP system log
    die("System Error: Database unavailable.");
}