<?php
require_once __DIR__ . '/includes/db.php';

$email = 'test@agency.com';
$password = 'test1234';
$pass_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if test reseller exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resellers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        // Delete it to start fresh
        $pdo->prepare("DELETE FROM resellers WHERE email = ?")->execute([$email]);
    }

    // Insert test reseller
    $stmt = $pdo->prepare("INSERT INTO resellers (email, password_hash, status) VALUES (?, ?, 'active')");
    $stmt->execute([$email, $pass_hash]);

    echo "\n[SUCCESS] Test reseller created!\n";
    echo "Login: {$email}\n";
    echo "Password: {$password}\n\n";
    exit(0);

}
catch (Exception $e) {
    echo "\n[ERROR] Failed to create test reseller: " . $e->getMessage() . "\n\n";
    exit(1);
}