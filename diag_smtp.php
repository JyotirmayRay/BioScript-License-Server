<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/EmailService.php';

header('Content-Type: text/plain');

$test_email = 'jyotirmay244@gmail.com';
echo "Starting SMTP Diagnostic for: $test_email\n";

try {
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch();

    if (!$settings) {
        die("ERROR: No settings found in DB.\n");
    }

    echo "SMTP Host: " . ($settings['smtp_host'] ?: 'EMPTY') . "\n";
    echo "SMTP User: " . ($settings['smtp_user'] ?: 'EMPTY') . "\n";
    echo "SMTP Port: " . $settings['smtp_port'] . "\n";
    echo "From Email: " . $settings['smtp_from_email'] . "\n";

    if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
        die("ERROR: SMTP settings are incomplete.\n");
    }

    echo "Attempting to send test email...\n";
    $result = EmailService::sendTestEmail($pdo, $test_email);

    if ($result) {
        echo "SUCCESS: Test email sent successfully!\n";
    }
    else {
        echo "FAILED: EmailService returned false.\n";
    }
}
catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}