<?php
declare(strict_types=1);

// Prevent direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Load dependencies
require_once __DIR__ . '/../includes/db.php';

try {
    // 1. Fetch System Settings (SMTP & Webhook Secret)
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings || empty($settings['lemon_secret'])) {
        error_log("Webhook Error: Missing Lemon Squeezy Secret in Settings.");
        http_response_code(500);
        exit('Server Configuration Error');
    }

    $lemon_secret = $settings['lemon_secret'];

    // 2. Read Raw Payload & Signature
    $payload = file_get_contents('php://input');
    $signature_header = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

    // 3. Cryptographic Verification
    $hash = hash_hmac('sha256', $payload, $lemon_secret);

    if (!hash_equals($hash, $signature_header)) {
        http_response_code(401);
        exit('Unauthorized: Invalid Signature');
    }

    // 4. Process Payload
    $data = json_decode($payload, true);

    // Check if event is 'order_created' or similar
    $event_name = $data['meta']['event_name'] ?? '';

    if ($event_name !== 'order_created') {
        // Just return 200 for other events to acknowledge receipt
        http_response_code(200);
        exit('Event Ignored');
    }

    // Extract Buyer Email
    $client_email = $data['data']['attributes']['user_email'] ?? '';

    if (empty($client_email)) {
        error_log("Webhook Error: No email found in payload.");
        http_response_code(400);
        exit('Invalid Payload');
    }

    // 5. Generate License Key
    $new_key = 'BIO-' . strtoupper(bin2hex(random_bytes(8)));

    // 6. Insert into Database
    // Note: 'licenses' table structure from includes/db.php:
    // license_key, client_email, tier, max_domains, registered_domains, status
    $stmt = $pdo->prepare("INSERT INTO licenses (license_key, client_email, status, created_at) VALUES (:key, :email, 'active', CURRENT_TIMESTAMP)");
    $stmt->execute([
        ':key' => $new_key,
        ':email' => $client_email
    ]);

    // 7. Send Email via Email Service
    try {
        require_once __DIR__ . '/../includes/EmailService.php';
        EmailService::sendLicense($pdo, 'Lemon-' . ($data['data']['id'] ?? 'Unknown'), $new_key, $client_email);

        @file_put_contents(__DIR__ . '/../logs/email_success.log', "[" . date('Y-m-d H:i:s') . "] Lemon Webhook: Email sent for license $new_key to $client_email\n", FILE_APPEND);
    }
    catch (Exception $e) {
        @file_put_contents(__DIR__ . '/../logs/email_error.log', "[" . date('Y-m-d H:i:s') . "] Lemon Webhook Error: " . $e->getMessage() . "\n", FILE_APPEND);
        error_log("Lemon Webhook Mail Error: " . $e->getMessage());
    }

    http_response_code(200);
    echo "License Generated & Email Sent";

}
catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error');
}
catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error');
}