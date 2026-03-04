<?php
// license_server/sync_db.php
declare(strict_types=1);

// Define path to database (relative to this script in license_server root)
$dbPath = __DIR__ . '/data/authority.db';

// Ensure directory exists
if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0755, true);
}

try {
    // Connect to SQLite Database
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create 'settings' table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        smtp_host TEXT,
        smtp_port INTEGER,
        smtp_user TEXT,
        smtp_pass TEXT,
        smtp_from_email TEXT,
        smtp_from_name TEXT,
        lemon_secret TEXT
    )";
    $pdo->exec($sql);

    // Check if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        // Insert default blank row
        $stmt = $pdo->prepare("INSERT INTO settings (id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from_email, smtp_from_name, lemon_secret) VALUES (1, '', 587, '', '', '', 'BioScript License Authority', '')");
        $stmt->execute();
        echo "Settings table created and seeded successfully.\n";
    } else {
        echo "Settings table already exists.\n";
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>