<?php
/**
 * BIOSCRIPT SECURE DOWNLOAD ENDPOINT
 * 
 * Flow:
 *  1. Validate license key from GET param.
 *  2. If valid, generate a 10-min download token and redirect to ?token=XXX.
 *  3. If token present and valid, stream the ZIP file securely.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';

define('DOWNLOAD_ZIP_PATH', __DIR__ . '/../../storage/bioscript-creator-edition.zip');

// ── Helper ─────────────────────────────────────────────────────────────────
function download_error(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo '<html><body style="font-family:sans-serif;background:#0f172a;color:#fff;padding:60px;text-align:center;">
    <h2 style="color:#ef4444;">⛔ Download Failed</h2>
    <p style="color:#94a3b8;">' . htmlspecialchars($msg) . '</p>
    </body></html>';
    exit;
}

$license_param = trim($_GET['license'] ?? '');
$token_param = trim($_GET['token'] ?? '');

// ── Step 1: Token present → validate and stream file ──────────────────────
if (!empty($token_param)) {

    $stmt = $pdo->prepare("
        SELECT dt.*, l.status AS license_status 
        FROM download_tokens dt
        JOIN licenses l ON l.license_key = dt.license_key
        WHERE dt.token = ?
          AND dt.used  = 0
          AND dt.expires_at > CURRENT_TIMESTAMP
        LIMIT 1
    ");
    $stmt->execute([$token_param]);
    $token_row = $stmt->fetch();

    if (!$token_row) {
        download_error('This download link has expired or has already been used. Please request a new download link from your license email.', 403);
    }

    $license_status = $token_row['license_status'];
    if ($license_status === 'revoked') {
        download_error('This license has been revoked. Please contact support.', 403);
    }

    // Mark token as used
    $pdo->prepare("UPDATE download_tokens SET used = 1 WHERE token = ?")
        ->execute([$token_param]);

    // Log the download event
    try {
        $pdo->prepare("INSERT INTO system_events (event_type, details) VALUES ('download_requested', ?)")
            ->execute(["License: " . $token_row['license_key'] . " | Token: $token_param"]);
    }
    catch (Exception $e) {
    }

    // Serve the ZIP securely
    if (!file_exists(DOWNLOAD_ZIP_PATH)) {
        download_error('The download file is temporarily unavailable. Please contact support@bioscript.link.', 503);
    }

    $filename = 'BioScript-Creator-Edition.zip';
    $filesize = filesize(DOWNLOAD_ZIP_PATH);

    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $filesize);
    header('Pragma: public');
    header('Cache-Control: must-revalidate');
    header('Expires: 0');
    ob_end_clean();
    readfile(DOWNLOAD_ZIP_PATH);
    exit;
}

// ── Step 2: License present → validate and issue token ────────────────────
if (!empty($license_param)) {

    // Validate format: BIO-XXXX-XXXX-XXXX
    if (!preg_match('/^BIO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license_param)) {
        download_error('Invalid license key format.');
    }

    $stmt = $pdo->prepare("SELECT id, status FROM licenses WHERE license_key = ? LIMIT 1");
    $stmt->execute([$license_param]);
    $lic = $stmt->fetch();

    if (!$lic) {
        download_error('License key not found. Please check your license email and try again.');
    }

    if ($lic['status'] === 'revoked') {
        download_error('This license has been revoked and is no longer eligible for downloads.');
    }

    // Generate a single-use 10-minute token
    $token = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    // Clean up any expired old tokens for this license so the DB stays lean
    $pdo->prepare("DELETE FROM download_tokens WHERE license_key = ? AND expires_at < CURRENT_TIMESTAMP")
        ->execute([$license_param]);

    $pdo->prepare("INSERT INTO download_tokens (license_key, token, expires_at) VALUES (?, ?, ?)")
        ->execute([$license_param, $token, $expires]);

    // Redirect to token-authenticated URL
    $redirect = '?token=' . urlencode($token);
    header('Location: ' . $redirect);
    exit;
}

// ── Step 3: No params → show the download entry form ──────────────────────
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BioScript — Secure Download</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-8">
    <div class="max-w-md w-full">
        <div class="text-center mb-10">
            <div
                class="inline-flex items-center justify-center w-16 h-16 bg-emerald-500/10 rounded-2xl border border-emerald-500/30 mb-4">
                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Download BioScript</h1>
            <p class="text-slate-400 text-sm">Enter your license key to access the secure download.</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8">
            <form method="GET">
                <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">License Key</label>
                <input type="text" name="license" required placeholder="BIO-XXXX-XXXX-XXXX"
                    class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-4 py-3 font-mono text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 mb-6">
                <button type="submit"
                    class="w-full bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white font-bold py-3 px-6 rounded-xl transition-all">
                    Verify &amp; Download
                </button>
            </form>
            <p class="text-xs text-slate-500 text-center mt-4">Your license key was included in your purchase
                confirmation email.</p>
        </div>
    </div>
</body>

</html>