<?php
declare(strict_types=1);

// Prevent HTML Error Output (Strict API Mode)
ini_set('display_errors', '0');
error_reporting(0);

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

    // Get Mutual Auth Data
    $raw_data = $_POST['data'] ?? '';
    $received_signature = $_POST['signature'] ?? '';

    if (empty($raw_data) || empty($received_signature)) {
        send_json_error('Missing Security Payload');
    }

    // 1. Verify Request Signature (Mutual Auth)
    $expected_request_signature = hash_hmac('sha256', $raw_data, SHARED_SECRET);
    if (!hash_equals($expected_request_signature, $received_signature)) {
        send_json_error('Request Authentication Failed');
    }

    $request = json_decode($raw_data, true);
    if (!$request) {
        send_json_error('Malformed Request Payload');
    }

    $license_key = $request['license_key'] ?? '';
    $raw_domain = $request['host_domain'] ?? '';
    $fingerprint = $request['fingerprint'] ?? '';
    $timestamp = $request['timestamp'] ?? 0;

    // Check Replay Attack
    if (abs(time() - $timestamp) > 300) {
        send_json_error('Request Expired (Time Drift)');
    }

    // --- DOMAIN NORMALIZATION ---
    $domain = strtolower(trim($raw_domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('/^www\./', '', $domain);
    $domain = preg_replace('/:\d+$/', '', $domain);
    $domain = rtrim($domain, '/');

    if (empty($domain)) {
        $domain = 'unknown_origin';
    }

    // Default Response Payload (Invalid)
    $payload = [
        'status' => 'invalid',
        'message' => 'Invalid License Key',
        'timestamp' => time()
    ];

    if (!empty($license_key)) {
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = :key LIMIT 1");
        $stmt->execute([':key' => $license_key]);
        $license = $stmt->fetch();

        if ($license && $license['status'] === 'active') {
            $registered_domains = json_decode($license['registered_domains'] ?? '[]', true);
            if (!is_array($registered_domains)) {
                $registered_domains = [];
            }

            // --- FINGERPRINT CHECK (Anti-Clone) ---
            if (!empty($license['installation_fingerprint']) && $license['installation_fingerprint'] !== $fingerprint) {
                $payload = [
                    'status' => 'invalid',
                    'reason' => 'Fingerprint Mismatch',
                    'message' => 'This license is already active on another server hardware.',
                    'timestamp' => time()
                ];
            }
            else {
                if (empty($registered_domains)) {
                    // FIRST USE: Lock to this domain AND fingerprint
                    $registered_domains[] = $domain;
                    $update = $pdo->prepare("UPDATE licenses SET registered_domains = :domains, installation_fingerprint = :fp, last_verified_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $update->execute([':domains' => json_encode($registered_domains), ':fp' => $fingerprint, ':id' => $license['id']]);
                    $payload = ['status' => 'active', 'domain' => $domain, 'message' => 'License Activated & Locked to ' . $domain, 'timestamp' => time()];
                }
                elseif (in_array($domain, $registered_domains)) {
                    // Already registered
                    $pdo->prepare("UPDATE licenses SET last_verified_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $license['id']]);
                    $payload = ['status' => 'active', 'domain' => $domain, 'message' => 'License Valid', 'timestamp' => time()];
                }
                else {
                    // New domain - check tier slots
                    if (count($registered_domains) < ($license['max_domains'] ?? 1)) {
                        $registered_domains[] = $domain;
                        $update = $pdo->prepare("UPDATE licenses SET registered_domains = :domains, last_verified_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $update->execute([':domains' => json_encode($registered_domains), ':id' => $license['id']]);
                        $payload = ['status' => 'active', 'domain' => $domain, 'message' => 'Additional Domain Registered', 'timestamp' => time()];
                    }
                    else {
                        $payload = ['status' => 'invalid', 'reason' => 'Domain Mismatch', 'message' => 'License locked to: ' . implode(', ', $registered_domains), 'timestamp' => time()];
                    }
                }
            }
        }
        elseif ($license && $license['status'] === 'banned') {
            $payload = ['status' => 'invalid', 'reason' => 'Banned', 'message' => 'License suspended by authority.', 'timestamp' => time()];
        }
        elseif ($license && $license['status'] === 'expired') {
            $payload = ['status' => 'invalid', 'reason' => 'Expired', 'message' => 'License has expired.', 'timestamp' => time()];
        }
    }

    // --- SIGNED RESPONSE ---
    $payload_json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $payload_json, SHARED_SECRET);

    echo json_encode(['status' => 'success', 'payload' => $payload_json, 'signature' => $signature]);
    exit;

}
catch (Throwable $e) {
    // Safe Error Logging - never expose details publicly
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $err_log = "[" . date('Y-m-d H:i:s') . "] API Exception [" . get_class($e) . "]: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    @file_put_contents($log_dir . '/api_error.log', $err_log, FILE_APPEND);
    error_log($err_log);

    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error', 'debug_ref' => time()]);
    exit;
}