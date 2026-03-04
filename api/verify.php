<?php
declare(strict_types=1);

// Prevent HTML Error Output (Strict API Mode)
ini_set('display_errors', '0');
error_reporting(0); // Suppress warnings too

// SHARED SECRET is loaded from config.php (via db.php include chain)
// Defined in: super_admin/config.php as define('SHARED_SECRET', '...')

// Set JSON Header Immediately
header('Content-Type: application/json');

// Helper to return JSON and exit
function send_json_error(string $msg, string $status = 'error'): void
{
    echo json_encode([
        'status' => $status,
        'message' => $msg,
        'payload' => null,
        'signature' => null
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../includes/db.php';

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        send_json_error('Method Not Allowed');
    }

    // Get Input
    $license_key = $_POST['license_key'] ?? '';
    $raw_domain = $_POST['host_domain'] ?? '';

    // --- DOMAIN NORMALIZATION ---
    // 1. Remove protocol (http://, https://)
    $domain = preg_replace('#^https?://#', '', $raw_domain);
    // 2. Remove 'www.'
    $domain = preg_replace('/^www\./', '', $domain);
    // 3. Remove port number (e.g., :8080)
    $domain = preg_replace('/:\d+$/', '', $domain);
    // 4. Remove trailing slashes
    $domain = rtrim($domain, '/');
    // 5. Lowercase
    $domain = strtolower($domain);

    // Default Response Payload (Invalid)
    $payload = ['status' => 'invalid', 'message' => 'Invalid License Key'];

    if (!empty($license_key)) {
        // Query DB for the key
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = :key LIMIT 1");
        $stmt->execute([':key' => $license_key]);
        $license = $stmt->fetch();

        if ($license && $license['status'] === 'active') {

            // Decode registered domains
            $registered_domains = json_decode($license['registered_domains'], true);
            if (!is_array($registered_domains)) {
                $registered_domains = [];
            }

            // Check Domain Logic
            if (empty($registered_domains)) {
                // FIRST USE: Lock to this domain
                $registered_domains[] = $domain;

                $update = $pdo->prepare("UPDATE licenses SET registered_domains = :domains WHERE id = :id");
                $update->execute([
                    ':domains' => json_encode($registered_domains),
                    ':id' => $license['id']
                ]);

                $payload = [
                    'status' => 'active',
                    'domain' => $domain,
                    'message' => 'License Activated & Locked to ' . $domain,
                    'timestamp' => time()
                ];

            }
            elseif (in_array($domain, $registered_domains)) {
                // Domain already registered -> Active
                $payload = [
                    'status' => 'active',
                    'domain' => $domain,
                    'message' => 'License Valid',
                    'timestamp' => time()
                ];
            }
            else {
                // Domain Mismatch
                // Check if they have slots left (Enterprise features)
                if (count($registered_domains) < $license['max_domains']) {
                    // Add new domain
                    $registered_domains[] = $domain;
                    $update = $pdo->prepare("UPDATE licenses SET registered_domains = :domains WHERE id = :id");
                    $update->execute([
                        ':domains' => json_encode($registered_domains),
                        ':id' => $license['id']
                    ]);

                    $payload = [
                        'status' => 'active',
                        'domain' => $domain,
                        'message' => 'Additional Domain Registered',
                        'timestamp' => time()
                    ];
                }
                else {
                    $payload = [
                        'status' => 'invalid',
                        'reason' => 'Domain Mismatch',
                        'message' => 'This license is locked to another domain: ' . implode(', ', $registered_domains),
                        'timestamp' => time()
                    ];
                }
            }
        }
        elseif ($license && $license['status'] === 'banned') {
            $payload = ['status' => 'invalid', 'reason' => 'Banned', 'message' => 'This license has been banned by the authority.', 'timestamp' => time()];
        }
        elseif ($license && $license['status'] === 'expired') {
            $payload = ['status' => 'invalid', 'reason' => 'Expired', 'message' => 'This license has expired.', 'timestamp' => time()];
        }
    }

    // --- THE CRYPTOGRAPHY ---

    // Encode Payload to JSON string
    $payload_json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Generate Signature
    $signature = hash_hmac('sha256', $payload_json, SHARED_SECRET);

    // Return JSON Response
    echo json_encode([
        'status' => 'success',
        'payload' => $payload_json,
        'signature' => $signature
    ]);
    exit;

}
catch (Exception $e) {
    // Catch ANY server-side error (PDO, Logic, etc) and return valid JSON
    http_response_code(500);
    send_json_error('Server Exception: ' . $e->getMessage());
}