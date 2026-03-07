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
    $pdo->exec('PRAGMA journal_mode = wal;');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- TABLE: LICENSES ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS licenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        license_key TEXT UNIQUE NOT NULL,
        client_email TEXT,
        tier TEXT DEFAULT 'Standard',
        max_domains INTEGER DEFAULT 1,
        registered_domains TEXT DEFAULT '[]',
        installation_fingerprint TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_verified_at DATETIME
    )"); } catch (PDOException $e) {}

    // --- TABLE: PLATFORM SETTINGS (Admin Auth) ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_user TEXT NOT NULL,
        admin_pass TEXT NOT NULL,
        ls_api_key TEXT,
        ls_webhook_secret TEXT
    )"); } catch (PDOException $e) {}

    // --- TABLE: SYSTEM SETTINGS (KV store for engine config) ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key TEXT,
        value TEXT,
        admin_username TEXT DEFAULT 'admin',
        admin_password TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"); } catch (PDOException $e) {}

    // --- TABLE: SETTINGS (SMTP + WooCommerce API config) ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        smtp_host TEXT DEFAULT '',
        smtp_port INTEGER DEFAULT 587,
        smtp_user TEXT DEFAULT '',
        smtp_pass TEXT DEFAULT '',
        smtp_from_email TEXT DEFAULT '',
        smtp_from_name TEXT DEFAULT 'BioScript Security',
        lemon_secret TEXT DEFAULT '',
        woo_store_url TEXT DEFAULT '',
        woo_consumer_key TEXT DEFAULT '',
        woo_consumer_secret TEXT DEFAULT '',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"); } catch (PDOException $e) {}

    // --- TABLE: ACTIVATIONS ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS activations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        license_id INTEGER NOT NULL,
        domain TEXT NOT NULL,
        fingerprint TEXT,
        activated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_ping DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'active'
    )"); } catch (PDOException $e) {}

    // --- TABLE: ORDERS (WooCommerce orders) ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        woo_order_id TEXT UNIQUE,
        order_number TEXT,
        customer_email TEXT,
        client_email TEXT,
        license_key TEXT,
        product_name TEXT,
        amount TEXT DEFAULT '0.00',
        currency TEXT DEFAULT 'USD',
        status TEXT DEFAULT 'pending',
        order_status TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"); } catch (PDOException $e) {}

    // --- TABLE: ORDER LOGS (Raw webhook payloads) ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS order_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        woo_order_id TEXT,
        status TEXT,
        raw_payload TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"); } catch (PDOException $e) {}

    // --- TABLE: PROCESSED ORDERS (Deduplication) ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS processed_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        woo_order_id TEXT UNIQUE,
        processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"); } catch (PDOException $e) {}

    // --- TABLE: PRODUCTS REGISTRY (WooCommerce product whitelist) ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS products_registry (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        woo_product_id TEXT UNIQUE,
        sku TEXT DEFAULT '',
        license_type TEXT DEFAULT 'standard',
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"); } catch (PDOException $e) {}

    // --- TABLE: WEBHOOK HEALTH ---
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS webhook_health (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        last_received_at DATETIME,
        last_event_type TEXT,
        total_received INTEGER DEFAULT 0
    )"); } catch (PDOException $e) {}

    // --- TABLE: RESELLERS ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS resellers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        license_key TEXT,
        total_generated INTEGER DEFAULT 0,
        total_active INTEGER DEFAULT 0,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    }
    catch (PDOException $e) {
    }

    // --- TABLE: EMAIL_VERIFICATIONS ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        license_key TEXT NOT NULL,
        verification_token TEXT UNIQUE NOT NULL,
        expires_at DATETIME NOT NULL,
        verified INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    }
    catch (PDOException $e) {
    }

    // --- TABLE: LICENSE_LOGS ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS license_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        license_key TEXT NOT NULL,
        domain TEXT,
        ip_address TEXT,
        event TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    }
    catch (PDOException $e) {
    }

    // --- TABLE: API_RATE_LIMITS ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_rate_limits (
        ip TEXT NOT NULL,
        attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    }
    catch (PDOException $e) {
    }

    // --- TABLE: DOMAIN_BLACKLIST ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS domain_blacklist (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT UNIQUE NOT NULL,
        reason TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    }
    catch (PDOException $e) {
    }

    // --- TABLE: SECURITY_LOGS ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS security_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT,
        event TEXT,
        details TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    }
    catch (PDOException $e) {
    }

    // --- TABLE: SYSTEM_EVENTS (Phase 4) ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_type TEXT NOT NULL,
        details TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    }
    catch (PDOException $e) {
    }

    // --- TABLE: DOWNLOAD_TOKENS (Phase 4) ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS download_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        license_key TEXT NOT NULL,
        token TEXT UNIQUE NOT NULL,
        expires_at DATETIME NOT NULL,
        used INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    }
    catch (PDOException $e) {
    }

    // Performance Indexes (wrapped in try/catch — reseller_id column may not exist yet on first run)
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_licenses_key ON licenses(license_key);");
    }
    catch (PDOException $e) {
    }
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_licenses_reseller ON licenses(reseller_id);");
    }
    catch (PDOException $e) {
    }

    // --- MIGRATIONS: Add missing columns to existing tables (safe - catches error if column exists) ---
    $migrations = [
        // licenses table (Original)
        "ALTER TABLE licenses ADD COLUMN installation_fingerprint TEXT",
        "ALTER TABLE licenses ADD COLUMN last_verified_at DATETIME",
        "ALTER TABLE licenses ADD COLUMN registered_domains TEXT DEFAULT '[]'",
        "ALTER TABLE licenses ADD COLUMN max_domains INTEGER DEFAULT 1",
        "ALTER TABLE licenses ADD COLUMN tier TEXT DEFAULT 'Standard'",
        // licenses table (Reseller Phase 1)
        "ALTER TABLE licenses ADD COLUMN type TEXT DEFAULT 'standard'",
        "ALTER TABLE licenses ADD COLUMN reseller_id INTEGER NULL",
        "ALTER TABLE licenses ADD COLUMN activated_at DATETIME NULL",
        // orders table
        "ALTER TABLE orders ADD COLUMN woo_order_id TEXT",
        "ALTER TABLE orders ADD COLUMN customer_email TEXT",
        "ALTER TABLE orders ADD COLUMN amount TEXT DEFAULT '0.00'",
        "ALTER TABLE orders ADD COLUMN currency TEXT DEFAULT 'USD'",
        "ALTER TABLE orders ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE orders ADD COLUMN status TEXT DEFAULT 'pending'",
        // settings table
        "ALTER TABLE settings ADD COLUMN woo_store_url TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN woo_consumer_key TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN woo_consumer_secret TEXT DEFAULT ''",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_woo_order_id ON orders(woo_order_id)",
        // resellers Phase 4
        "ALTER TABLE resellers ADD COLUMN license_key TEXT",
        "ALTER TABLE resellers ADD COLUMN total_generated INTEGER DEFAULT 0",
        "ALTER TABLE resellers ADD COLUMN total_active INTEGER DEFAULT 0",
    ];
    foreach ($migrations as $sql) {
        try {
            $pdo->exec($sql);
        }
        catch (Exception $e) { /* Column already exists - safe to ignore */
        }
    }

    // Ensure unique index on system_settings.key
    try {
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_settings_key ON system_settings(key)");
    }
    catch (Exception $e) {
    }

    // Phase 4: Unique index on resellers.license_key (ALTER TABLE doesn't support UNIQUE in SQLite)
    try {
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_resellers_license_key ON resellers(license_key)");
    }
    catch (Exception $e) {
    }
    // Phase 4: Performance indexes for download_tokens
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_download_tokens_token ON download_tokens(token)");
    }
    catch (Exception $e) {
    }
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_download_tokens_license ON download_tokens(license_key)");
    }
    catch (Exception $e) {
    }

    // --- SEED: SETTINGS (SMTP row, id=1) ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from_email, smtp_from_name) VALUES (1, '', 587, '', '', '', 'BioScript Security')");
    }

    // --- SEED: PLATFORM SETTINGS (Admin Auth) ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM platform_settings");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO platform_settings (id, admin_user, admin_pass) VALUES (1, :user, :pass)");
        $stmt->execute([
            ':user' => 'admin',
            ':pass' => password_hash('b1oScR1pT_F0rtress_2026_#2bf57f09', PASSWORD_DEFAULT)
        ]);
    }
    else {
        // MIGRATION: Hash legacy plaintext passwords
        $stmt = $pdo->query("SELECT admin_pass FROM platform_settings WHERE id = 1");
        $admin = $stmt->fetch();
        if ($admin && (password_get_info($admin['admin_pass'])['algo'] === 0 || $admin['admin_pass'] === 'supersecure')) {
            $new_hash = password_hash('b1oScR1pT_F0rtress_2026_#2bf57f09', PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE platform_settings SET admin_pass = ? WHERE id = 1")->execute([$new_hash]);
        }
    }

    // --- SEED: SYSTEM SETTINGS KV defaults ---
    $kv_defaults = [
        'whitelist_domains' => json_encode(['bioscript.link']),
        'webhook_secret' => '',
        'trigger_status' => 'completed',
        'license_prefix' => 'BIO',
        'auto_license' => '1',
        'woo_api_url' => '',
        'woo_consumer_key' => '',
        'woo_consumer_secret' => '',
    ];
    foreach ($kv_defaults as $k => $v) {
        try {
            $pdo->prepare("INSERT OR IGNORE INTO system_settings (key, value) VALUES (?, ?)")->execute([$k, $v]);
        }
        catch (Exception $e) {
        }
    }

    // Seed admin row in system_settings if missing
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE admin_username = 'admin'");
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO system_settings (admin_username, admin_password) VALUES (:user, :pass)")->execute([
                ':user' => 'admin',
                ':pass' => password_hash('b1oScR1pT_F0rtress_2026_#2bf57f09', PASSWORD_DEFAULT)
            ]);
        }
    }
    catch (Exception $e) {
    }

}
catch (PDOException $e) {
    // Log error safely, never expose to public
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $error_msg = "[" . date('Y-m-d H:i:s') . "] DB Failure: " . $e->getMessage() . " | Path: " . (defined('DB_PATH') ? DB_PATH : 'UNDEFINED') . "\n";
    @file_put_contents($log_dir . '/db_error.log', $error_msg, FILE_APPEND);
    error_log($error_msg);

    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']);
        exit;
    }
    die("System Error: Database unavailable.");
}