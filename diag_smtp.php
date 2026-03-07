<?php
/**
 * SMTP Diagnostic Tool — Run this in your browser on the LIVE server.
 * URL: https://license.bioscript.link/diag_smtp.php
 *
 * This sends a plain test email and logs the full SMTP conversation.
 * Check the output for errors or rejections.
 *
 * DELETE THIS FILE AFTER DEBUGGING.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/EmailService.php';

header('Content-Type: text/plain; charset=utf-8');

$test_email = $_GET['to'] ?? 'jyotirmay244@gmail.com';
echo "=== BioScript SMTP Diagnostic ===\n";
echo "Target: $test_email\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check SMTP settings
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
$settings = $stmt->fetch();

if (!$settings) {
    die("FATAL: No settings row found in database.\n");
}

echo "--- SMTP Configuration ---\n";
echo "Host:      " . ($settings['smtp_host'] ?: '(empty)') . "\n";
echo "Port:      " . $settings['smtp_port'] . "\n";
echo "User:      " . ($settings['smtp_user'] ?: '(empty)') . "\n";
echo "Pass:      " . (empty($settings['smtp_pass']) ? '(empty)' : str_repeat('*', strlen($settings['smtp_pass']))) . "\n";
echo "From:      " . $settings['smtp_from_email'] . "\n";
echo "From Name: " . $settings['smtp_from_name'] . "\n\n";

if (empty($settings['smtp_host']) || empty($settings['smtp_user']) || empty($settings['smtp_pass'])) {
    die("FATAL: SMTP credentials are incomplete. Configure them in Super Admin settings.\n");
}

// 2. Send test via EmailService with full SMTP debug
echo "--- Sending Test Email (with SMTP Debug) ---\n\n";

try {
    $mail = EmailService::createMailer($pdo, $test_email);

    // Enable SMTP debug output directly to screen
    $mail->SMTPDebug = 3; // Maximum verbosity
    $mail->Debugoutput = function ($str, $level) {
        echo "[SMTP L$level] $str";
    };

    $mail->CharSet = 'UTF-8';
    $mail->addReplyTo($settings['smtp_from_email'], $settings['smtp_from_name']);
    $mail->Subject = 'BioScript SMTP Diagnostic Test';
    $mail->Body = '<html><body style="font-family:Arial,sans-serif;color:#333;padding:20px;"><p>This is a diagnostic test email from BioScript License Server.</p><p>If you received this, SMTP is working correctly.</p></body></html>';
    $mail->AltBody = 'This is a diagnostic test email from BioScript License Server. If you received this, SMTP is working correctly.';

    $result = $mail->send();
    echo "\n\n--- Result ---\n";
    echo $result ? "SUCCESS: PHPMailer reports email was sent.\n" : "FAILED: PHPMailer reports send failure.\n";
    echo "ErrorInfo: " . $mail->ErrorInfo . "\n";

}
catch (Exception $e) {
    echo "\n\nEXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n--- Done ---\n";
echo "Check your inbox AND spam folder for: $test_email\n";
echo "If the email appears in SMTP log above as 'queued' but never arrives, the issue is DNS (SPF/DKIM/DMARC).\n";