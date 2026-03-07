<?php
// test_activations.php
require_once __DIR__ . '/includes/db.php';
if (!defined('SHARED_SECRET'))
    define('SHARED_SECRET', 'dev_secret_key_change_in_production');

echo "--- BioScript API Activation Tester ---\n\n";

function send_api_request($payload)
{
    $json_payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $json_payload, SHARED_SECRET);

    $ch = curl_init('http://127.0.0.1:9091/api/verify.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'data' => $json_payload,
        'signature' => $signature
    ]));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    usleep(250000); // 250ms delay for SQLite WAL propagation

    return ['code' => $http_code, 'body' => $response];
}

function expect_reason($test_name, $response, $expected_reason)
{
    echo str_pad("TEST: $test_name", 40) . " ";
    $data = json_decode($response['body'], true);

    if (!$data || !isset($data['payload'])) {
        echo "[FAIL] Internal Error or Malformed JSON\n";
        echo "Response was: " . $response['body'] . "\n";
        return;
    }

    $payload = json_decode($data['payload'], true);
    $reason = $payload['reason'] ?? $payload['status'] ?? 'unknown';

    if ($reason === $expected_reason) {
        echo "[PASS] Got expected: $expected_reason\n";
    }
    else {
        echo "[FAIL] Expected $expected_reason, got $reason\n";
        print_r($payload);
    }
}

// 1. Setup Test Data
$pdo->exec("DELETE FROM licenses WHERE client_email LIKE 'test_%@example.com'");
$pdo->exec("DELETE FROM email_verifications");

$std_key = 'BIO-1111-2222-3333';
$revoked_key = 'BIO-DEAD-BEEF-0000';
$reseller_key = 'BIO-RESE-LLER-9999';

$pdo->prepare("INSERT INTO licenses (license_key, client_email, type, status, registered_domains) VALUES (?, ?, 'standard', 'active', '[]')")->execute([$std_key, 'test_std@example.com']);
$pdo->prepare("INSERT INTO licenses (license_key, client_email, type, status, registered_domains) VALUES (?, ?, 'standard', 'revoked', '[]')")->execute([$revoked_key, 'test_rev@example.com']);
$pdo->prepare("INSERT INTO licenses (license_key, client_email, type, status, reseller_id) VALUES (?, ?, 'reseller_generated', 'pending_activation', 1)")->execute([$reseller_key, 'test_res@example.com']);

// Keep $pdo open for later tests

// --- RUN TESTS ---

// Test 1: Invalid Format
$res = send_api_request(['license_key' => 'INVALID-KEY', 'email' => 'test@example.com', 'host_domain' => 'site.com', 'fingerprint' => 'fp', 'timestamp' => time()]);
expect_reason('Invalid Format', $res, 'invalid_license');

// Test 2: Revoked
$res = send_api_request(['license_key' => $revoked_key, 'email' => 'test_rev@example.com', 'host_domain' => 'site.com', 'fingerprint' => 'fp', 'timestamp' => time()]);
expect_reason('Revoked License', $res, 'revoked_license');

// Test 3: Email Mismatch
$res = send_api_request(['license_key' => $std_key, 'email' => 'wrong@email.com', 'host_domain' => 'site.com', 'fingerprint' => 'fp', 'timestamp' => time()]);
expect_reason('Email Mismatch', $res, 'email_mismatch');

// Test 4: Std Valid First Activation (Locks Domain)
$res = send_api_request(['license_key' => $std_key, 'email' => 'test_std@example.com', 'host_domain' => 'https://www.mysite.com/path', 'fingerprint' => 'fp1', 'timestamp' => time()]);
expect_reason('Std Activation (Locking)', $res, 'active'); // Assuming status='active' is what we check here

// Test 5: Std Domain Mismatch (Trying second domain on single license)
$res = send_api_request(['license_key' => $std_key, 'email' => 'test_std@example.com', 'host_domain' => 'othersite.com', 'fingerprint' => 'fp1', 'timestamp' => time()]);
expect_reason('Domain Mismatch', $res, 'domain_mismatch');

// Test 6: Reseller Verification Required
$res = send_api_request(['license_key' => $reseller_key, 'email' => 'test_res@example.com', 'host_domain' => 'site.com', 'fingerprint' => 'fp', 'timestamp' => time()]);
expect_reason('Verification Required', $res, 'verification_required');

// $pdo is already open and valid here
$stmt = $pdo->prepare("SELECT verification_token FROM email_verifications WHERE license_key = ?");
$stmt->execute([$reseller_key]);
$token = $stmt->fetchColumn();

if ($token) {
    echo "Simulating email click for token: $token\n";
    $_GET['token'] = $token;
    ob_start();
    require __DIR__ . '/verify-email.php';
    ob_end_clean();

    // Test 7: Reseller Activation Success (After verify)
    $res = send_api_request(['license_key' => $reseller_key, 'email' => 'test_res@example.com', 'host_domain' => 'site.com', 'fingerprint' => 'fp2', 'timestamp' => time()]);
    expect_reason('Reseller Activation After Verif', $res, 'active');
}
else {
    echo "[FAIL] Verification token was never generated in DB.\n";
}

// Check Logs
echo "\n--- License Logs Check ---\n";
$logs = $pdo->query("SELECT license_key, event FROM license_logs ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($logs as $l) {
    echo "{$l['license_key']} -> {$l['event']}\n";
}

// Test 8: Domain Blacklist
echo "\n--- Phase 3 Security Rules ---\n";
$pdo->exec("INSERT OR IGNORE INTO domain_blacklist (domain, reason) VALUES ('banned-site.com', 'Test')");
$res = send_api_request(['license_key' => $std_key, 'email' => 'test_std@example.com', 'host_domain' => 'banned-site.com', 'fingerprint' => 'fpX', 'timestamp' => time()]);
expect_reason('Domain Blacklist Rule', $res, 'domain_blacklisted');

// Test 9: 5-Fail IP Fraud Block
echo "Triggering 5 rapid failures...\n";
$pdo->exec("DELETE FROM security_logs"); // Reset for clean test
for ($i = 0; $i < 5; $i++) {
    send_api_request(['license_key' => 'FAKE-1234-5678-9012', 'email' => 'x@y.com', 'host_domain' => 'x.com', 'fingerprint' => 'f', 'timestamp' => time()]);
}

$res = send_api_request(['license_key' => 'FAKE-0000-0000-0000', 'email' => 'x@y.com', 'host_domain' => 'y.com', 'fingerprint' => 'f', 'timestamp' => time()]);
expect_reason('5-Fail IP Fraud Block', $res, 'rate_limit_exceeded');

// Check Security Logs
echo "\n--- Security Logs Check ---\n";
$slogs = $pdo->query("SELECT event, details FROM security_logs ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($slogs as $l) {
    echo "{$l['event']} -> {$l['details']}\n";
}