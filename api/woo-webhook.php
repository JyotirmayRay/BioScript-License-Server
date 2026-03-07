<?php
declare(strict_types=1);

// Forensic Hits Log (Temporary Phase 3 removed)

/**
 * PRODUCTION-GRADE WOOCOMMERCE WEBHOOK ENGINE
 *
 * FIXED: Webhook signature validation hardening with robust header extraction.
 */

// 1. Initial configuration & Security
require_once __DIR__ . '/../includes/db.php';

// Load webhook secret from database (system_settings key-value table)
$stmt = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'webhook_secret' LIMIT 1");
$stmt->execute();
$db_webhook_secret = $stmt->fetchColumn();
if (empty($db_webhook_secret)) {
    file_put_contents(SIGNATURE_ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] Webhook secret not configured in system_settings\n", FILE_APPEND);
    http_response_code(500);
    exit;
}
define('WOO_WEBHOOK_SECRET', $db_webhook_secret);
define('WEBHOOK_LOG_FILE', __DIR__ . '/webhook_debug.log');
define('SIGNATURE_ERROR_LOG', __DIR__ . '/signature_errors.log');

// Directory for logs
if (!is_dir(dirname(WEBHOOK_LOG_FILE))) {
    @mkdir(dirname(WEBHOOK_LOG_FILE), 0755, true);
}

function log_error(string $msg)
{
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents(WEBHOOK_LOG_FILE, "[$timestamp] ERROR: $msg\n", FILE_APPEND);
}

// Global entry log to verify script is reached
$timestamp = date('Y-m-d H:i:s');
$headers_json = json_encode(function_exists('getallheaders') ? getallheaders() : $_SERVER);
@file_put_contents(SIGNATURE_ERROR_LOG, "[$timestamp] PING RECEIVED - Request Method: " . $_SERVER['REQUEST_METHOD'] . " - Headers: $headers_json\n", FILE_APPEND);

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ROBUST HEADER EXTRACTION & VALIDATION
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

// 1. Read RAW payload immediately
$payload = file_get_contents('php://input');


// 2. Robust header extraction
$signature = '';

if (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strtolower((string)$key) === 'x-wc-webhook-signature') {
            $signature = trim((string)$value);
            break;
        }
    }
}

// Fallback to server variables
if (empty($signature)) {
    $signature = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? '';
    $signature = trim((string)$signature);
}

// If still missing, log and exit
if (empty($signature)) {
    file_put_contents(
        SIGNATURE_ERROR_LOG,
        "[" . date('Y-m-d H:i:s') . "] Signature Header Missing\n",
        FILE_APPEND
    );
    http_response_code(401);
    exit;
}

// 4. Verify secret constant exists:
if (!defined('WOO_WEBHOOK_SECRET')) {
    file_put_contents(
        SIGNATURE_ERROR_LOG,
        "[" . date('Y-m-d H:i:s') . "] Secret Not Defined\n",
        FILE_APPEND
    );
    http_response_code(500);
    exit;
}

// 5. Compute expected signature using RAW payload:
$expected = base64_encode(
    hash_hmac('sha256', (string)$payload, WOO_WEBHOOK_SECRET, true)
);

// 6. Compare safely using timing-safe check:
if (!hash_equals($expected, $signature)) {
    file_put_contents(
        SIGNATURE_ERROR_LOG,
        "[" . date('Y-m-d H:i:s') . "] Signature Mismatch\n" .
        "Expected: $expected\n" .
        "Received: $signature\n\n",
        FILE_APPEND
    );

    http_response_code(401);
    exit;
}

// 8. Database Operations (db.php already loaded above)

// --- WEBHOOK MONITORING: Record Receipt ---
try {
    $stmt = $pdo->prepare("UPDATE webhook_health 
                           SET last_received_at = CURRENT_TIMESTAMP, 
                               total_received = total_received + 1 
                           WHERE id = 1");
    $stmt->execute();
}
catch (PDOException $healthError) {
    // Fail silently for monitoring updates to ensure core logic continues
    log_error("Health monitoring receipt update failed: " . $healthError->getMessage());
}

// Decode JSON safely
$data = json_decode((string)$payload, true);
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    exit;
}

// Extract required fields
$order_id = (string)$data['id'];
$status = (string)$data['status'];
$email = $data['billing']['email'] ?? null;
$total = $data['total'] ?? null;
$currency = $data['currency'] ?? null;
$line_items = $data['line_items'] ?? [];

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PRODUCT REGISTRY VALIDATION
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$valid_product = false;

foreach ($line_items as $item) {
    if (!isset($item['product_id']))
        continue;

    $stmt = $pdo->prepare("SELECT 1 FROM products_registry WHERE woo_product_id = ? AND active = 1");
    $stmt->execute([(string)$item['product_id']]);
    if ($stmt->fetch()) {
        $valid_product = true;
        break;
    }
}

if (!$valid_product) {
    // Acknowledge receipt but do not process
    http_response_code(200);
    echo "OK - Ignoring non-BioScript order";
    exit;
}

// License Generation Logic — MUST produce BIO-XXXX-XXXX-XXXX (3 segments)
// to match the activation API regex: /^BIO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/
function generateLicense()
{
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $segments = [];
    for ($i = 0; $i < 3; $i++) {
        $seg = '';
        for ($j = 0; $j < 4; $j++) {
            $seg .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $segments[] = $seg;
    }
    return 'BIO-' . implode('-', $segments);
}

// 8. Database Operations (db.php already loaded above)

try {
    // A. Insert raw payload into order_logs table
    $stmt = $pdo->prepare("INSERT INTO order_logs (woo_order_id, status, raw_payload) VALUES (?, ?, ?)");
    $stmt->execute([$order_id, $status, $payload]);

    // B. Insert or update orders table
    $stmt = $pdo->prepare("INSERT INTO orders (woo_order_id, customer_email, amount, currency, status, updated_at) 
                           VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                           ON CONFLICT(woo_order_id) DO UPDATE SET 
                           status = excluded.status, updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$order_id, $email, $total, $currency, $status]);

    // C. Logic for 'completed' orders
    $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE key = 'trigger_status'");
    $stmt->execute();
    $trigger_status = $stmt->fetchColumn();

    if ($status === $trigger_status) {

        $pdo->beginTransaction();

        // Check for duplicate in processed_orders
        $check = $pdo->prepare("SELECT 1 FROM processed_orders WHERE woo_order_id = ?");
        $check->execute([$order_id]);

        if (!$check->fetch()) {

            // Determine product type from registry
            $license_type = 'standard';
            foreach ($line_items as $item) {
                if (!isset($item['product_id']))
                    continue;
                $stmt = $pdo->prepare("SELECT license_type FROM products_registry WHERE woo_product_id = ? AND active = 1");
                $stmt->execute([(string)$item['product_id']]);
                $row = $stmt->fetch();
                if ($row) {
                    $license_type = $row['license_type'] ?? 'standard';
                    break;
                }
            }

            // ── RESELLER PURCHASE ──────────────────────────────────────────────
            if ($license_type === 'reseller') {

                // Generate RES-XXXX-XXXX-XXXX key
                $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $generateSegment = function () use ($chars) {
                    $s = '';
                    for ($i = 0; $i < 4; $i++)
                        $s .= $chars[random_int(0, strlen($chars) - 1)];
                    return $s;
                };
                $reseller_license = 'RES-' . $generateSegment() . '-' . $generateSegment() . '-' . $generateSegment();

                // Secure random password: 14 chars, mixed case+digits
                $pwd_chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $plain_password = '';
                for ($i = 0; $i < 14; $i++) {
                    $plain_password .= $pwd_chars[random_int(0, strlen($pwd_chars) - 1)];
                }
                $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

                // Insert reseller (ignore if email exists – idempotent)
                $stmt = $pdo->prepare("INSERT OR IGNORE INTO resellers (email, password_hash, license_key, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$email, $password_hash, $reseller_license]);

                // Log processed order
                $stmt = $pdo->prepare("INSERT INTO processed_orders (woo_order_id) VALUES (?)");
                $stmt->execute([$order_id]);

                $pdo->commit();

                // Log system event
                try {
                    $pdo->prepare("INSERT INTO system_events (event_type, details) VALUES ('reseller_created', ?)")
                        ->execute(["Email: $email | License: $reseller_license"]);
                }
                catch (Exception $e) {
                }

                // Send onboarding email
                try {
                    require_once __DIR__ . '/../includes/EmailService.php';
                    EmailService::sendResellerOnboarding($pdo, $email, $plain_password, $reseller_license);
                    @file_put_contents(__DIR__ . '/../logs/email_success.log', "[" . date('Y-m-d H:i:s') . "] Reseller onboarding email sent to $email\n", FILE_APPEND);
                }
                catch (Exception $e) {
                    @file_put_contents(__DIR__ . '/../logs/email_error.log', "[" . date('Y-m-d H:i:s') . "] Reseller email failed for $email: " . $e->getMessage() . "\n", FILE_APPEND);
                }

            }
            // ── STANDARD PURCHASE ──────────────────────────────────────────────
            else {
                $license = generateLicense();

                // Insert license
                $stmt = $pdo->prepare("INSERT INTO licenses (license_key, client_email, status, created_at) VALUES (?, ?, 'active', CURRENT_TIMESTAMP)");
                $stmt->execute([$license, $email]);

                // Update order
                $stmt = $pdo->prepare("UPDATE orders SET license_key = ? WHERE woo_order_id = ?");
                $stmt->execute([$license, $order_id]);

                // Track processed order
                $stmt = $pdo->prepare("INSERT INTO processed_orders (woo_order_id) VALUES (?)");
                $stmt->execute([$order_id]);

                $pdo->commit();

                // Log system event
                try {
                    $pdo->prepare("INSERT INTO system_events (event_type, details) VALUES ('license_generated', ?)")
                        ->execute(["License: $license | Email: $email | Order: $order_id"]);
                }
                catch (Exception $e) {
                }

                // Email Delivery (Post-Commit)
                try {
                    require_once __DIR__ . '/../includes/EmailService.php';
                    EmailService::sendLicense($pdo, $order_id, $license, $email);
                    @file_put_contents(__DIR__ . '/../logs/email_success.log', "[" . date('Y-m-d H:i:s') . "] Webhook: Email sent for order $order_id to $email\n", FILE_APPEND);
                }
                catch (Exception $e) {
                    @file_put_contents(__DIR__ . '/../logs/email_error.log', "[" . date('Y-m-d H:i:s') . "] Webhook Order: $order_id | Error: " . $e->getMessage() . "\n", FILE_APPEND);
                    log_error("Email delivery failed for order $order_id: " . $e->getMessage());
                }
            }
        }
        else {
            $pdo->rollBack();
        }
    }

    // D. Logic for 'refunded' orders
    if ($status === 'refunded') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT license_key FROM orders WHERE woo_order_id = ?");
        $stmt->execute([$order_id]);
        $linked_license = $stmt->fetchColumn();

        if ($linked_license) {
            $stmt = $pdo->prepare("UPDATE licenses SET status = 'revoked' WHERE license_key = ?");
            $stmt->execute([$linked_license]);

            $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE woo_order_id = ?");
            $stmt->execute([$order_id]);
        }
        $pdo->commit();
    }

}
catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @file_put_contents(__DIR__ . '/../logs/db_error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    log_error("DB Error on order " . ($order_id ?? 'unknown') . ": " . $e->getMessage());

    // --- WEBHOOK MONITORING: Record Failure ---
    try {
        $stmt = $pdo->prepare("UPDATE webhook_health 
                               SET total_failed = total_failed + 1, 
                                   last_error = ? 
                               WHERE id = 1");
        $stmt->execute([$e->getMessage()]);
    }
    catch (PDOException $healthError) {
        log_error("Health monitoring failure update failed: " . $healthError->getMessage());
    }

    http_response_code(500);
    exit;
}

// 9. Always return HTTP 200
http_response_code(200);
echo "OK";
exit;