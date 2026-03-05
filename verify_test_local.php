<?php
// Local Test for verify.php
define('SHARED_SECRET', 'b1oScR1pT_S3cr3t_2026_xYz987!');

function simulate_verify($license_key, $domain = 'localhost')
{
    $payload = json_encode([
        'license_key' => $license_key,
        'host_domain' => $domain,
        'fingerprint' => 'test-fp-' . $domain,
        'timestamp' => time()
    ]);

    $signature = hash_hmac('sha256', $payload, SHARED_SECRET);

    $_POST['data'] = $payload;
    $_POST['signature'] = $signature;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    ob_start();
    include 'api/verify.php';
    return ob_get_clean();
}

echo "--- TEST 1: WHitelisted domain (Bypass) ---\n";
echo simulate_verify('ANY-KEY-HERE', 'localhost');
echo "\n\n";

echo "--- TEST 2: Invalid Key ---\n";
echo simulate_verify('INVALID-999', 'mysite.com');
echo "\n\n";