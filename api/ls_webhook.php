<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

// Ensure this script only processes POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// 1. Get Raw Payload & Signature Header
$raw_payload = file_get_contents('php://input');
$signature_header = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// 2. Verify Signature
// Calculate expected signature using the raw payload and secret
$expected_signature = hash_hmac('sha256', $raw_payload, LS_WEBHOOK_SECRET);

if (!hash_equals($expected_signature, $signature_header)) {
    // Signature mismatch -> unauthorized request
    http_response_code(401);
    exit('Invalid Signature');
}

// 3. Process Webhook Event
$data = json_decode($raw_payload, true);
$event_name = $data['meta']['event_name'] ?? '';

if ($event_name === 'order_created') {
    
    // Extract Order Details
    $attributes = $data['data']['attributes'];
    $client_email = $attributes['user_email'] ?? '';
    // $product_id = $attributes['product_id'] ?? 0;
    // $variant_id = $attributes['variant_id'] ?? 0;

    // Determine Max Domains based on Product logic (Placeholder)
    $max_domains = 1; 
    // Example: if ($variant_id == 12345) $max_domains = 10;

    // Generate License Key
    // Format: BIO-XXXXXXXXXXXXXXXX (16 random hex chars)
    $license_key = 'BIO-' . strtoupper(bin2hex(random_bytes(8)));

    try {
        // Insert into Database
        $stmt = $pdo->prepare("INSERT INTO licenses (license_key, client_email, max_domains, registered_domains, status) VALUES (:key, :email, :max, :domains, 'active')");
        
        $stmt->execute([
            ':key' => $license_key,
            ':email' => $client_email,
            ':max' => $max_domains,
            ':domains' => '[]' // Initialize empty JSON array
        ]);

        // Success
        http_response_code(200);
        echo "License Generated: " . $license_key;

        // TODO: Send email to customer with the key (or let Lemon Squeezy handle it via custom fields)

    } catch (PDOException $e) {
        // Log DB error but don't expose it
        error_log("Webhook DB Error: " . $e->getMessage());
        http_response_code(500);
        exit('Database Error');
    }

} else {
    // Other events (subscription_updated, etc.) can be handled here
    http_response_code(200);
    echo "Event Ignored";
}
