<?php
// /verify-email.php
require_once __DIR__ . '/includes/db.php';

$token = $_GET['token'] ?? '';
$status = 'invalid'; // 'success', 'invalid', 'expired', 'already_verified'
$message = 'Invalid or missing verification token.';
$license_key = '';

if (!empty($token)) {
    // Lookup token
    $stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE verification_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $verification = $stmt->fetch();

    if ($verification) {
        $license_key = $verification['license_key'];

        if ($verification['verified'] == 1) {
            $status = 'already_verified';
            $message = 'This email has already been verified. Your license is active.';
        }
        else {
            $expires_at = strtotime($verification['expires_at']);
            $now = time();

            if ($now > $expires_at) {
                $status = 'expired';
                $message = 'This verification link has expired. Please request a new activation attempt from the software.';
            }
            else {
                // Determine if we need to update license status to active
                // If the license is pending_activation, activate it.
                $stmt = $pdo->prepare("SELECT status FROM licenses WHERE license_key = ? LIMIT 1");
                $stmt->execute([$license_key]);
                $lic = $stmt->fetch();

                if ($lic && $lic['status'] === 'pending_activation') {
                    $pdo->beginTransaction();
                    try {
                        // Mark as verified
                        $stmt = $pdo->prepare("UPDATE email_verifications SET verified = 1 WHERE id = ?");
                        $stmt->execute([$verification['id']]);

                        // Set license to active and record activation time, and MARK AS VERIFIED
                        $stmt = $pdo->prepare("UPDATE licenses SET status = 'active', activated_at = CURRENT_TIMESTAMP, is_verified = 1 WHERE license_key = ?");
                        $stmt->execute([$license_key]);

                        // Log the event
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        $stmt = $pdo->prepare("INSERT INTO license_logs (license_key, ip_address, event) VALUES (?, ?, ?)");
                        $stmt->execute([$license_key, $ip, 'email_verified']);

                        $pdo->commit();
                        $status = 'success';
                        $message = 'Email successfully verified! Your license is now active. You may return to your website and click verify again.';
                    }
                    catch (Exception $e) {
                        $pdo->rollBack();
                        $status = 'error';
                        $message = 'A database error occurred during verification.';
                    }
                }
                elseif ($lic && $lic['status'] === 'active') {
                    // Safety catch if license got activated somehow but token wasn't updated
                    $pdo->prepare("UPDATE email_verifications SET verified = 1 WHERE id = ?")->execute([$verification['id']]);
                    // CRUCIAL MISSING STEP: Update the actual license record to show it is verified!
                    $pdo->prepare("UPDATE licenses SET is_verified = 1 WHERE license_key = ?")->execute([$license_key]);

                    $status = 'already_verified';
                    $message = 'Your license is already active. You may proceed.';
                }
                else {
                    $status = 'error';
                    $message = 'License could not be verified. It may be revoked or invalid.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - BioScript</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: { 850: '#1e293b', 950: '#0f172a' },
                        primary: { 500: '#6366f1', 600: '#4f46e5' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div
        class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-primary-900/20 via-slate-950 to-slate-950 pointer-events-none">
    </div>

    <div class="max-w-md w-full relative z-10">
        <div class="text-center mb-8">
            <div
                class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl mb-6">
                <i class="fas fa-shield-alt text-2xl text-blue-500"></i>
            </div>
            <h1 class="text-3xl font-bold tracking-tight mb-2">License Verification</h1>
            <p class="text-slate-400">BioScript Creator Edition</p>
        </div>

        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-8 shadow-2xl">
            <?php if ($status === 'success'): ?>
            <div class="text-center">
                <div
                    class="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-emerald-500/20">
                    <i class="fas fa-check text-2xl text-emerald-500"></i>
                </div>
                <h2 class="text-xl font-bold text-white mb-3">Verification Complete</h2>
                <p class="text-slate-400 text-sm leading-relaxed mb-6">
                    <?php echo htmlspecialchars($message); ?>
                </p>
                <div
                    class="p-4 bg-slate-950 rounded-lg border border-slate-800 font-mono text-sm text-slate-300 break-all mb-6">
                    <?php echo htmlspecialchars($license_key); ?>
                </div>
            </div>
            <?php
elseif ($status === 'already_verified'): ?>
            <div class="text-center">
                <div
                    class="w-16 h-16 bg-blue-500/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-blue-500/20">
                    <i class="fas fa-info text-2xl text-blue-500"></i>
                </div>
                <h2 class="text-xl font-bold text-white mb-3">Already Verified</h2>
                <p class="text-slate-400 text-sm leading-relaxed">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
            <?php
else: ?>
            <div class="text-center">
                <div
                    class="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-red-500/20">
                    <i class="fas fa-times text-2xl text-red-500"></i>
                </div>
                <h2 class="text-xl font-bold text-white mb-3">Verification Failed</h2>
                <p class="text-slate-400 text-sm leading-relaxed">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
            <?php
endif; ?>
        </div>

        <div class="text-center mt-8 text-xs text-slate-600 font-medium tracking-wide uppercase">
            Secured by BioScript Network
        </div>
    </div>
</body>

</html>