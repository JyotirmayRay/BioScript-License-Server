<?php
declare(strict_types=1);

// /api/update-email.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../includes/db.php';
    if (!defined('SHARED_SECRET'))
        define('SHARED_SECRET', 'dev_secret_key_change_in_production');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        exit;
    }

    $raw_data = $_POST['data'] ?? '';
    $received_signature = $_POST['signature'] ?? '';

    if (empty($raw_data) || empty($received_signature)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing Security Payload']);
        exit;
    }

    if (!hash_equals(hash_hmac('sha256', $raw_data, SHARED_SECRET), $received_signature)) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication Failed']);
        exit;
    }

    $request = json_decode($raw_data, true);
    $license_key = trim($request['license_key'] ?? '');
    $new_email = trim(strtolower($request['new_email'] ?? ''));
    $fingerprint = $request['fingerprint'] ?? '';

    if (empty($license_key) || !filter_var($new_email, FILTER_VALIDATE_EMAIL) || empty($fingerprint)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Data Provided']);
        exit;
    }

    // Lookup license
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? LIMIT 1");
    $stmt->execute([$license_key]);
    $license = $stmt->fetch();

    if (!$license) {
        echo json_encode(['status' => 'error', 'message' => 'License Not Found']);
        exit;
    }

    // Protection: Only allow update if NOT YET VERIFIED
    if ($license['is_verified'] == 1) {
        echo json_encode(['status' => 'error', 'message' => 'Email is already verified and locked.']);
        exit;
    }

    // Protection: Must match fingerprint if one is set
    if (!empty($license['installation_fingerprint']) && $license['installation_fingerprint'] !== $fingerprint) {
        echo json_encode(['status' => 'error', 'message' => 'Hardware fingerprint mismatch.']);
        exit;
    }

    // Update Email
    $stmt = $pdo->prepare("UPDATE licenses SET client_email = ? WHERE id = ?");
    $stmt->execute([$new_email, $license['id']]);

    // Log the change
    $stmt = $pdo->prepare("INSERT INTO license_logs (license_key, event) VALUES (?, ?)");
    $stmt->execute([$license_key, "email_corrected_to: $new_email"]);

    echo json_encode(['status' => 'success', 'message' => 'Email updated successfully.']);

}
catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
}