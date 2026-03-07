<?php
declare(strict_types=1);

// /api/verify.php
// Prevent HTML Error Output (Strict API Mode)
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

function send_json_error(string $msg, string $status = 'error', string $reason = ''): void
{
    $response = [
        'status' => 'success', // HTTP 200 wrapper
        'payload' => json_encode([
            'status' => $status,
            'reason' => $reason,
            'message' => $msg,
            'timestamp' => time()
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'signature' => null
    ];
    if (defined('SHARED_SECRET')) {
        $response['signature'] = hash_hmac('sha256', $response['payload'], SHARED_SECRET);
    }
    echo json_encode($response);
    exit;
}

function log_activation_event($pdo, string $license_key, string $domain, string $ip, string $event): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO license_logs (license_key, domain, ip_address, event) VALUES (?, ?, ?, ?)");
        $stmt->execute([$license_key, $domain, $ip, $event]);
    }
    catch (Exception $e) {
    // Silently fail logging rather than breaking API
    }
}

function log_security_event($pdo, string $ip, string $event, string $details): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO security_logs (ip_address, event, details) VALUES (?, ?, ?)");
        $stmt->execute([$ip, $event, $details]);
    }
    catch (Exception $e) {
        @file_put_contents(__DIR__ . '/../logs/api_error.log', "[" . date('Y-m-d H:i:s') . "] Security Log Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

try {
    require_once __DIR__ . '/../includes/db.php';
    if (!defined('SHARED_SECRET'))
        define('SHARED_SECRET', 'dev_secret_key_change_in_production');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_error('Method Not Allowed', 'error', 'method_not_allowed');
    }

    $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // --- RATE LIMITING (10 per minute per IP) ---
    $pdo->prepare("DELETE FROM api_rate_limits WHERE attempt_time < datetime('now', '-1 minute')")->execute();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE ip = ?");
    $stmt->execute([$client_ip]);
    if ($stmt->fetchColumn() >= 10) {
        send_json_error('Rate limit exceeded. Try again later.', 'error', 'rate_limit_exceeded');
    }
    $pdo->prepare("INSERT INTO api_rate_limits (ip) VALUES (?)")->execute([$client_ip]);

    // --- 5-FAIL FRAUD DETECTION ---
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE ip_address = ? AND event = 'failed_activation' AND created_at >= datetime('now', '-2 minutes')");
    $stmt->execute([$client_ip]);
    if ($stmt->fetchColumn() >= 5) {
        send_json_error('Too many invalid attempts. Your IP has been temporarily blocked.', 'error', 'rate_limit_exceeded');
    }

    // --- MUTUAL AUTH VALIDATION ---
    $raw_data = $_POST['data'] ?? '';
    $received_signature = $_POST['signature'] ?? '';

    if (empty($raw_data) || empty($received_signature)) {
        send_json_error('Missing Security Payload', 'error', 'missing_payload');
    }

    $expected_request_signature = hash_hmac('sha256', $raw_data, SHARED_SECRET);
    if (!hash_equals($expected_request_signature, $received_signature)) {
        send_json_error('Request Authentication Failed', 'error', 'auth_failed');
    }

    $request = json_decode($raw_data, true);
    if (!$request) {
        send_json_error('Malformed Request Payload', 'error', 'malformed_payload');
    }

    $license_key = trim($request['license_key'] ?? '');
    $raw_domain = $request['host_domain'] ?? $request['domain'] ?? ''; // Support both for compatibility
    $fingerprint = $request['fingerprint'] ?? '';
    $timestamp = $request['timestamp'] ?? 0;
    $email = trim(strtolower($request['email'] ?? ''));

    if (abs(time() - $timestamp) > 300) {
        send_json_error('Request Expired (Time Drift)', 'error', 'expired_request');
    }

    // --- FORMAT VERIFICATION ---
    if (!preg_match('/^BIO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license_key)) {
        log_activation_event($pdo, $license_key, $raw_domain, $client_ip, 'invalid_format');
        log_security_event($pdo, $client_ip, 'failed_activation', "Invalid format: $license_key");
        send_json_error('Invalid License Key Format', 'invalid', 'invalid_license');
    }

    // --- DOMAIN NORMALIZATION & BLACKLIST CHECK ---
    $domain = strtolower(trim($raw_domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('/^www\./', '', $domain);
    $domain = preg_replace('/:\d+$/', '', $domain);
    $domain = rtrim($domain, '/');
    if (empty($domain))
        $domain = 'unknown_origin';

    // Blacklist enforcement
    $stmt = $pdo->prepare("SELECT id FROM domain_blacklist WHERE domain = ? LIMIT 1");
    $stmt->execute([$domain]);
    if ($stmt->fetch()) {
        log_security_event($pdo, $client_ip, 'failed_activation', "Blacklisted domain attempt: $domain");
        send_json_error('This domain has been banned from the network.', 'invalid', 'domain_blacklisted');
    }

    // --- LOOKUP LICENSE ---
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = :key LIMIT 1");
    $stmt->execute([':key' => $license_key]);
    $license = $stmt->fetch();

    if (!$license) {
        log_activation_event($pdo, $license_key, $domain, $client_ip, 'activation_failed_not_found');
        log_security_event($pdo, $client_ip, 'failed_activation', "Key not found: $license_key");
        send_json_error('Invalid License Key', 'invalid', 'invalid_license');
    }

    // --- STATUS CHECKS ---
    if (in_array($license['status'], ['banned', 'revoked'])) {
        log_activation_event($pdo, $license_key, $domain, $client_ip, 'activation_failed_revoked');
        send_json_error('License ' . ucfirst($license['status']), 'invalid', 'revoked_license');
    }

    if ($license['status'] === 'expired') {
        log_activation_event($pdo, $license_key, $domain, $client_ip, 'activation_failed_expired');
        send_json_error('License Expired', 'invalid', 'expired_license');
    }

    // --- EMAIL MATCH CHECK ---
    $stored_email = trim(strtolower($license['client_email']));
    if (!empty($email) && !empty($stored_email) && $email !== $stored_email) {
        log_activation_event($pdo, $license_key, $domain, $client_ip, 'email_mismatch');
        log_security_event($pdo, $client_ip, 'failed_activation', "Email mismatch. Provided: $email, Expected: $stored_email");
        send_json_error('Email address does not match this license', 'invalid', 'email_mismatch');
    }

    // --- RESELLER PENDING FLOW (EMAIL VERIFICATION) ---
    if ($license['type'] === 'reseller_generated' && $license['status'] === 'pending_activation') {

        $stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE license_key = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$license_key]);
        $existing_verification = $stmt->fetch();

        $token_valid = ($existing_verification && $existing_verification['verified'] == 0 && strtotime($existing_verification['expires_at']) > time());

        if (!$token_valid) {
            // Generate a new token
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', time() + 1800); // +30 mins

        $stmt = $pdo->prepare("INSERT INTO email_verifications (license_key, verification_token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$license_key, $token, $expires]);

            log_activation_event($pdo, $license_key, $domain, $client_ip, 'verification_required');
    send_json_error('Please check your email to verify this license.', 'invalid', 'verification_required');
    // In a real scenario, this is where we would trigger the SMTP mail() to $stored_email with verify-email.php?token=$token
        }
        else {
            log_activation_event($pdo, $license_key, $domain, $client_ip, 'verification_still_pending');
    send_json_error('A verification email was already sent. Please check your inbox.', 'invalid', 'verification_required');
        }
    }

    // --- DOMAIN LOCKING & ACTIVATION (Status Active) ---
    $registered_domains = json_decode($license['registered_domains'] ?? '[]', true);
    if (!is_array($registered_domains))
        $registered_domains = [];

    $payload = [];

    // FINGERPRINT CHECK (Anti-Clone)
    if (!empty($license['installation_fingerprint']) && $license['installation_fingerprint'] !== $fingerprint) {
        log_activation_event($pdo, $license_key, $domain, $client_ip, 'fingerprint_mismatch');
        $payload = [
            'status' => 'invalid',
            'reason' => 'fingerprint_mismatch',
            'message' => 'This license is active on another hardware instance.',
            'timestamp' => time()
        ];
    }
    else {
        if (empty($registered_domains)) {
            // FIRST USE: Lock to domain and fingerprint
            $registered_domains[] = $domain;
            $pdo->prepare("UPDATE licenses SET registered_domains = ?, installation_fingerprint = ?, status = 'active', activated_at = CURRENT_TIMESTAMP, last_verified_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([json_encode($registered_domains), $fingerprint, $license['id']]);

            log_activation_event($pdo, $license_key, $domain, $client_ip, 'activation_success');

            // --- PHASE 4: POST-ACTIVATION TRACKING ---
            // Log system event
            try {
                $pdo->prepare("INSERT INTO system_events (event_type, details) VALUES ('activation_success', ?)")
                    ->execute(["License: $license_key | Domain: $domain"]);
            }
            catch (Exception $se) {
            }

            // If reseller-issued license, bump the reseller's total_active counter
            if (!empty($license['reseller_id'])) {
                try {
                    $pdo->prepare("UPDATE resellers SET total_active = total_active + 1 WHERE id = ?")
                        ->execute([$license['reseller_id']]);
                }
                catch (Exception $ce) {
                }
            }

            // Send "Installation Is Ready" email (silently, non-blocking)
            try {
                require_once __DIR__ . '/../includes/EmailService.php';
                $stored_email_for_mail = $license['client_email'] ?? '';
                if (!empty($stored_email_for_mail)) {
                    EmailService::sendCustomerOnboarding($pdo, $stored_email_for_mail, $license_key, $domain);
                }
            }
            catch (Exception $me) {
                @file_put_contents(__DIR__ . '/../logs/email_error.log', "[" . date('Y-m-d H:i:s') . "] Onboarding email failed for $license_key: " . $me->getMessage() . "\n", FILE_APPEND);
            }

            $payload = [
                'status' => 'active',
                'domain' => $domain,
                'license' => $license_key,
                'message' => 'Activated and Locked',
                'verified' => (bool)$license['is_verified'],
                'client_email' => $license['client_email'],
                'timestamp' => time()
            ];
        }
        elseif (in_array($domain, $registered_domains)) {
            // ALREADY REGISTERED ON THIS DOMAIN
            $pdo->prepare("UPDATE licenses SET last_verified_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$license['id']]);
            log_activation_event($pdo, $license_key, $domain, $client_ip, 'verification_success');
            $payload = [
                'status' => 'active',
                'domain' => $domain,
                'license' => $license_key,
                'message' => 'License Valid',
                'verified' => (bool)$license['is_verified'],
                'client_email' => $license['client_email'],
                'timestamp' => time()
            ];
        }
        else {
            // MULTI-DOMAIN TIER CHECK
            if (count($registered_domains) < ($license['max_domains'] ?? 1)) {
                $registered_domains[] = $domain;
                $pdo->prepare("UPDATE licenses SET registered_domains = ?, last_verified_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([json_encode($registered_domains), $license['id']]);

                log_activation_event($pdo, $license_key, $domain, $client_ip, 'domain_added');
                $payload = [
                    'status' => 'active',
                    'domain' => $domain,
                    'license' => $license_key,
                    'message' => 'Domain Added',
                    'verified' => (bool)$license['is_verified'],
                    'client_email' => $license['client_email'],
                    'timestamp' => time()
                ];
            }
            else {
                log_activation_event($pdo, $license_key, $domain, $client_ip, 'domain_mismatch');
                $payload = ['status' => 'invalid', 'reason' => 'domain_mismatch', 'message' => 'License locked to: ' . implode(',', $registered_domains), 'timestamp' => time()];
            }
        }
    }

    $payload_json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $payload_json, SHARED_SECRET);

 
    echo json_encode(['status' => 'success', 'payload' => $payload_json, 'signature' => $signature]);
    exit;

}
catch (Throwable $e) {
    if (!headers_sent())
        header('Content-Type: application/json');
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir))
        @mkdir($log_dir, 0755, true);
    $err_log = "[" . date('Y-m-d H:i:s') . "] API Exception: " . $e->getMessage() . "\n";
    @file_put_contents($log_dir . '/api_error.log', $err_log, FILE_APPEND);

    // We hardcode a generic response that the client parser won't crash on
    echo json_encode([
        'status' => 'success',
        'payload' => json_encode(['status' => 'error', 'message' => 'Internal Server Error']),
        'signature' => null
    ]);
    exit;
}
