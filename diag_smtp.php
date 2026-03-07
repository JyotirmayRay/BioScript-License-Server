<?php
/**
 * SMTP Diagnostic Tool v2 — Tests BOTH plain and verification-style emails.
 * URL: https://license.bioscript.link/diag_smtp.php
 * DELETE THIS FILE AFTER DEBUGGING.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/EmailService.php';

header('Content-Type: text/plain; charset=utf-8');

$test_email = $_GET['to'] ?? 'jyotirmay244@gmail.com';
echo "=== BioScript SMTP Diagnostic v2 ===\n";
echo "Target: $test_email\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Check SMTP settings
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
$settings = $stmt->fetch();

echo "Host: " . ($settings['smtp_host'] ?: '(empty)') . "\n";
echo "Port: " . $settings['smtp_port'] . "\n";
echo "User: " . ($settings['smtp_user'] ?: '(empty)') . "\n";
echo "From: " . $settings['smtp_from_email'] . "\n\n";

// ---- TEST 1: Plain test email (this already works) ----
echo "--- TEST 1: Plain Email ---\n";
try {
    $result = EmailService::sendTestEmail($pdo, $test_email);
    echo "Result: " . ($result ? "SENT OK" : "FAILED") . "\n\n";
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ---- TEST 2: Verification-style email (with URL link) ----
echo "--- TEST 2: Verification-Style Email (with link) ---\n";
try {
    $fake_url = 'https://license.bioscript.link/verify-email.php?token=TEST_DIAGNOSTIC_TOKEN_123';
    $result = EmailService::sendVerification($pdo, $test_email, $fake_url);
    echo "Result: " . ($result ? "SENT OK" : "FAILED") . "\n\n";
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ---- TEST 3: Manual minimal email with link (bypass EmailService) ----
echo "--- TEST 3: Raw PHPMailer with link ---\n";
try {
    $mail = EmailService::createMailer($pdo, $test_email);
    $mail->SMTPDebug = 2;
    $smtp_log = '';
    $mail->Debugoutput = function ($str, $level) use (&$smtp_log) {
        $smtp_log .= $str;
    };
    $mail->CharSet = 'UTF-8';
    $mail->addReplyTo($settings['smtp_from_email'], $settings['smtp_from_name']);
    $mail->Subject = 'BioScript Test - Link Delivery Check';
    $mail->Body = '<html><body><p>Hello, click this link: <a href="https://license.bioscript.link/verify-email.php?token=abc123">Verify</a></p></body></html>';
    $mail->AltBody = "Hello, verify here: https://license.bioscript.link/verify-email.php?token=abc123";

    $result = $mail->send();
    echo "Result: " . ($result ? "SENT OK" : "FAILED") . "\n";
    echo "ErrorInfo: " . $mail->ErrorInfo . "\n\n";

    echo "--- SMTP Log ---\n";
    echo $smtp_log . "\n";
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ---- Check if smtp_debug.log exists ----
echo "\n--- Log File Check ---\n";
$log_path = __DIR__ . '/logs/smtp_debug.log';
if (file_exists($log_path)) {
    echo "smtp_debug.log exists (" . filesize($log_path) . " bytes)\n";
    echo "Last 500 chars:\n";
    $content = file_get_contents($log_path);
    echo substr($content, -500) . "\n";
}
else {
    echo "smtp_debug.log does NOT exist yet.\n";
}

echo "\n=== Done. Check inbox AND spam for 3 emails. ===\n";