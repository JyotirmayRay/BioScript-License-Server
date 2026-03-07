<?php
declare(strict_types=1);

// /api/resend-verification.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/EmailService.php';
    if (!defined('SHARED_SECRET'))
        define('SHARED_SECRET', 'dev_secret_key_change_in_production');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        exit;
    }

    $raw_data = $_POST['data'] ?? '';
    $received_signature = $_POST['signature'] ?? '';

    if (!hash_equals(hash_hmac('sha256', $raw_data, SHARED_SECRET), $received_signature)) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication Failed']);
        exit;
    }

    $request = json_decode($raw_data, true);
    $license_key = trim($request['license_key'] ?? '');
    $fingerprint = $request['fingerprint'] ?? '';

    // Lookup license
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? LIMIT 1");
    $stmt->execute([$license_key]);
    $license = $stmt->fetch();

    if (!$license || empty($license['client_email'])) {
        echo json_encode(['status' => 'error', 'message' => 'License or Email Not Found']);
        exit;
    }

    if ($license['is_verified'] == 1) {
        echo json_encode(['status' => 'error', 'message' => 'Already Verified']);
        exit;
    }

    // Fingerprint check
    if (!empty($license['installation_fingerprint']) && $license['installation_fingerprint'] !== $fingerprint) {
        echo json_encode(['status' => 'error', 'message' => 'Security Error']);
        exit;
    }

    // Generate new token
    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', time() + 1800);

    $stmt = $pdo->prepare("INSERT INTO email_verifications (license_key, verification_token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$license_key, $token, $expires]);

    // Send Mail via Super Admin SMTP
    $customer_email = $license['client_email'];
    $verify_url = (defined('LICENSE_SERVER_URL') ? LICENSE_SERVER_URL : 'https://license.bioscript.link') . '/verify-email.php?token=' . urlencode($token);

    if (EmailService::sendVerification($pdo, $customer_email, $verify_url)) {
        echo json_encode(['status' => 'success', 'message' => 'Verification email resent.']);
    }
    else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send email. Check SMTP settings.']);
    }

}
catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}