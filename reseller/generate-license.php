<?php
// /reseller/generate-license.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ResellerLogger.php';

// Force Account Pairing (One-time security check)
if (!isset($_SESSION['reseller_is_verified']) || $_SESSION['reseller_is_verified'] !== 1) {
    header('Location: activate.php');
    exit;
}

$reseller_id = $_SESSION['reseller_id'];
$reseller_email = $_SESSION['reseller_email'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$success = null;
$error = null;

// CSRF wrapper
if (empty($_SESSION['reseller_csrf'])) {
    $_SESSION['reseller_csrf'] = bin2hex(random_bytes(32));
}

// --- RE-VERIFY RESELLER STATUS + KEY (anti-suspension bypass + anti-hijack) ---
$stmt = $pdo->prepare("SELECT status, license_key FROM resellers WHERE id = ? LIMIT 1");
$stmt->execute([$reseller_id]);
$current_reseller = $stmt->fetch();

if (!$current_reseller || $current_reseller['status'] !== 'active') {
    session_unset();
    session_destroy();
    header('Location: login.php?msg=' . urlencode('Your account has been suspended.'));
    exit;
}

// Verify reseller_key matches session (anti-session-hijack)
$session_key = $_SESSION['reseller_key'] ?? '';
if (!empty($current_reseller['license_key']) && $session_key !== $current_reseller['license_key']) {
    ResellerLogger::log($pdo, 'key_mismatch', "Session key mismatch for Reseller: $reseller_id | Session: $session_key | DB: " . $current_reseller['license_key'], [
        'ip' => $client_ip, 'reseller_id' => $reseller_id
    ]);
    session_unset();
    session_destroy();
    header('Location: login.php?msg=' . urlencode('Session verification failed. Please log in again.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || trim($_POST['csrf_token']) !== $_SESSION['reseller_csrf']) {
        $error = "Security token mismatch. Please try again.";
    }
    else {
        // --- DB-BASED RATE LIMITING ---
        $hourly_count = ResellerLogger::countEvents($pdo, 'license_generated', $reseller_id, '-1 hour');
        $daily_count = ResellerLogger::countEvents($pdo, 'license_generated', $reseller_id, '-1 day');

        if ($hourly_count >= 50) {
            $error = "Hourly limit reached (50 per hour). Please wait before generating more licenses.";
            ResellerLogger::log($pdo, 'rate_limit_hit', "Hourly limit | Reseller: $reseller_id | Count: $hourly_count", [
                'ip' => $client_ip, 'reseller_id' => $reseller_id, 'email' => $reseller_email
            ]);
        }
        elseif ($daily_count >= 200) {
            // AUTO-FLAG: Suspend reseller for abuse
            try {
                $pdo->prepare("UPDATE resellers SET status = 'suspended' WHERE id = ?")->execute([$reseller_id]);
            }
            catch (\Exception $e) {
            }

            ResellerLogger::log($pdo, 'account_flagged', "Auto-suspended for abuse | Reseller: $reseller_id | Daily count: $daily_count", [
                'ip' => $client_ip, 'reseller_id' => $reseller_id, 'email' => $reseller_email
            ]);

            session_unset();
            session_destroy();
            header('Location: login.php?msg=' . urlencode('Account suspended due to unusual activity. Contact support.'));
            exit;
        }
        else {
            $customer_email = trim($_POST['customer_email'] ?? '');

            if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please provide a valid customer email address.";
            }
            else {
                // Generate BIO-XXXX-XXXX-XXXX
                function generateResellerKey()
                {
                    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $segments = [];
                    for ($i = 0; $i < 3; $i++) {
                        $segment = '';
                        for ($j = 0; $j < 4; $j++) {
                            $segment .= $chars[random_int(0, strlen($chars) - 1)];
                        }
                        $segments[] = $segment;
                    }
                    return 'BIO-' . implode('-', $segments);
                }

                $key_generated = false;
                $max_attempts = 10;
                $attempts = 0;
                $final_key = '';

                while (!$key_generated && $attempts < $max_attempts) {
                    $candidate_key = generateResellerKey();
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE license_key = ?");
                    $stmt->execute([$candidate_key]);
                    if ($stmt->fetchColumn() == 0) {
                        $final_key = $candidate_key;
                        $key_generated = true;
                    }
                    $attempts++;
                }

                if (!$key_generated) {
                    $error = "Failed to generate a unique key. Please try again.";
                }
                else {
                    try {
                        $pdo->beginTransaction();

                        // Insert license as pending_activation
                        $stmt = $pdo->prepare("INSERT INTO licenses (license_key, client_email, type, reseller_id, status, max_domains) VALUES (:key, :email, 'reseller_generated', :reseller_id, 'pending_activation', 1)");
                        $stmt->execute([
                            ':key' => $final_key,
                            ':email' => $customer_email,
                            ':reseller_id' => $reseller_id
                        ]);

                        // Increment reseller's total_generated counter
                        $pdo->prepare("UPDATE resellers SET total_generated = total_generated + 1 WHERE id = ?")
                            ->execute([$reseller_id]);

                        // Generate email verification token for buyer
                        $verification_token = bin2hex(random_bytes(16));
                        $verification_expires = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

                        $stmt = $pdo->prepare("INSERT INTO email_verifications (license_key, verification_token, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$final_key, $verification_token, $verification_expires]);

                        $pdo->commit();

                        // Log the generation event
                        ResellerLogger::log($pdo, 'license_generated', "Reseller: $reseller_id | License: $final_key | Customer: $customer_email", [
                            'ip' => $client_ip, 'reseller_id' => $reseller_id, 'email' => $reseller_email
                        ]);

                        // Send verification email to buyer (non-blocking — don't fail on SMTP errors)
                        try {
                            require_once __DIR__ . '/../includes/EmailService.php';
                            $verify_url = 'https://license.bioscript.link/verify-email.php?token=' . urlencode($verification_token);

                            $mail = EmailService::createMailer($pdo, $customer_email);
                            $mail->Subject = 'Verify Your BioScript License';
                            $mail->Body = '<html><body style="font-family:Arial,sans-serif;background:#0f172a;color:#fff;padding:40px;margin:0;">'
                                . '<div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;overflow:hidden;border:1px solid #334155;">'
                                . '<div style="padding:30px;border-bottom:1px solid #334155;background:linear-gradient(135deg,#0f172a,#1e293b);">'
                                . '<h2 style="margin:0;color:#38bdf8;font-size:22px;text-transform:uppercase;letter-spacing:2px;">Verify Your Email</h2>'
                                . '</div><div style="padding:40px;">'
                                . '<p style="margin-top:0;color:#94a3b8;">A BioScript license has been issued to this email address. Please verify your email to activate it.</p>'
                                . '<div style="margin:24px 0;padding:20px;background:#0f172a;border-radius:8px;border:1px dashed #334155;text-align:center;">'
                                . '<p style="margin:0 0 8px 0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#64748b;">Your License Key</p>'
                                . '<div style="font-family:monospace;font-size:20px;font-weight:bold;color:#10b981;letter-spacing:2px;">' . htmlspecialchars($final_key) . '</div>'
                                . '</div>'
                                . '<div style="text-align:center;margin-top:24px;">'
                                . '<a href="' . htmlspecialchars($verify_url) . '" style="display:inline-block;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:bold;font-size:14px;">Verify Email &amp; Activate License</a>'
                                . '</div>'
                                . '<p style="font-size:12px;color:#64748b;margin-top:20px;text-align:center;">This verification link expires in 30 minutes.</p>'
                                . '</div><div style="padding:16px;text-align:center;background:#0f172a;border-top:1px solid #334155;">'
                                . '<p style="margin:0;font-size:11px;color:#475569;">&copy; ' . date('Y') . ' BioScript</p>'
                                . '</div></div></body></html>';
                            $mail->send();

                            ResellerLogger::log($pdo, 'verification_email_sent', "Token sent to $customer_email for license $final_key", [
                                'ip' => $client_ip, 'reseller_id' => $reseller_id, 'email' => $customer_email
                            ]);
                        }
                        catch (\Throwable $mailErr) {
                            // Log but don't fail — license was still created
                            ResellerLogger::log($pdo, 'verification_email_failed', "SMTP failed for $customer_email: " . $mailErr->getMessage(), [
                                'ip' => $client_ip, 'reseller_id' => $reseller_id, 'email' => $customer_email
                            ]);
                        }

                        $success = [
                            'key' => $final_key,
                            'email' => htmlspecialchars($customer_email)
                        ];
                    }
                    catch (PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = "Database error generating license. Please try again.";
                        ResellerLogger::log($pdo, 'license_generation_error', "DB error for Reseller $reseller_id: " . $e->getMessage(), [
                            'ip' => $client_ip, 'reseller_id' => $reseller_id
                        ]);
                    }
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
    <title>Generate License - BioScript Reseller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: { 850: '#1e293b', 950: '#0f172a' },
                        emerald: { 400: '#34d399', 500: '#10b981', 600: '#059669' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .ui-reseller {
            font-family: 'Inter', sans-serif;
            --ent-primary: #10b981;
            --ent-surface: #0f172a;
            --ent-card: #1e293b;
            --ent-border: #334155;
            --ent-text: #f8fafc;
        }

        .ent-card {
            background: var(--ent-card);
            border: 1px solid var(--ent-border);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .ent-input {
            background: rgba(2, 6, 23, 0.6);
            border: 1px solid var(--ent-border);
            color: var(--ent-text);
            transition: all 0.2s;
        }

        .ent-input:focus {
            border-color: var(--ent-primary);
            box-shadow: 0 0 0 1px var(--ent-primary), inset 0 2px 4px rgba(0, 0, 0, 0.5);
            outline: none;
        }

        .ent-btn-primary {
            background: linear-gradient(to bottom right, var(--ent-primary), #059669);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 0.75rem;
            font-weight: 900;
        }

        .ent-btn-primary:active {
            transform: translateY(1px);
        }
    </style>
</head>

<body class="ui-reseller bg-slate-950 text-slate-100 h-screen flex overflow-hidden font-sans">

    <aside class="w-64 bg-slate-950 border-r border-slate-800 flex flex-col hidden md:flex shrink-0 z-20">
        <div class="p-6 border-b border-slate-800 flex items-center space-x-3">
            <div
                class="w-8 h-8 bg-emerald-600 rounded flex items-center justify-center border border-emerald-500 shadow-sm">
                <i class="fas fa-briefcase text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-base font-black text-white tracking-widest uppercase leading-tight">Bio<span
                        class="text-emerald-500">Script</span></h1>
                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Reseller Portal</p>
            </div>
        </div>

        <nav class="flex-1 py-6 space-y-1">
            <a href="dashboard.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-chart-pie w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">Dashboard</span>
            </a>
            <a href="generate-license.php"
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-emerald-400 border-l-2 border-emerald-500 transition-all group">
                <i class="fas fa-key w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">Generate License</span>
            </a>
            <a href="customers.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-users w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">Customers</span>
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <div class="px-4 py-2 mb-2 text-xs text-slate-400 truncate w-full"
                title="<?php echo htmlspecialchars($reseller_email); ?>">
                <i class="fas fa-user-circle mr-2"></i>
                <?php echo htmlspecialchars(strlen($reseller_email) > 20 ? substr($reseller_email, 0, 17) . '...' : $reseller_email); ?>
            </div>
            <a href="logout.php"
                class="flex items-center justify-center space-x-2 w-full px-4 py-2 hover:bg-slate-900 text-slate-500 hover:text-red-400 border border-transparent hover:border-slate-800 rounded transition-all text-xs font-bold uppercase tracking-wider">
                <i class="fas fa-power-off"></i>
                <span>Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto p-8 lg:p-12 relative">
        <div
            class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-emerald-900/10 to-transparent pointer-events-none">
        </div>

        <header class="flex justify-between items-end mb-10 relative z-10">
            <div>
                <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Generate License</h2>
                <p class="text-slate-400">Issue a new standard tier license to a customer.</p>
            </div>
        </header>

        <div class="max-w-2xl relative z-10">
            <?php if ($error): ?>
            <div
                class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 flex items-start space-x-3">
                <i class="fas fa-exclamation-triangle mt-0.5"></i>
                <span>
                    <?php echo htmlspecialchars($error); ?>
                </span>
            </div>
            <?php
endif; ?>

            <?php if ($success): ?>
            <div class="ent-card p-8 mb-8 border-emerald-500/50 relative overflow-hidden">
                <div
                    class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-emerald-500 to-transparent">
                </div>
                <div class="flex items-center space-x-4 mb-6">
                    <div
                        class="w-12 h-12 bg-emerald-500/10 rounded-full flex items-center justify-center border border-emerald-500/30">
                        <i class="fas fa-check text-2xl text-emerald-500"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">License Generated</h3>
                        <p class="text-sm text-slate-400">Successfully allocated for <span class="text-white">
                                <?php echo $success['email']; ?>
                            </span></p>
                    </div>
                </div>

                <div class="bg-slate-900 border border-slate-700 rounded-xl p-6 relative group">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">License Key</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-2xl font-mono text-emerald-400 tracking-wider" id="generatedKey">
                            <?php echo $success['key']; ?>
                        </span>
                        <button
                            onclick="navigator.clipboard.writeText('<?php echo $success['key']; ?>'); this.innerHTML = '<i class=\'fas fa-check\'></i> Copied';"
                            class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded transition-colors text-sm font-bold flex items-center space-x-2">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php
endif; ?>

            <div class="ent-card p-8 bg-slate-900/80">
                <form method="POST">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['reseller_csrf']); ?>">

                    <div class="mb-6">
                        <label
                            class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Customer
                            Email</label>
                        <div class="relative">
                            <i
                                class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                            <input type="email" name="customer_email" required
                                class="ent-input w-full rounded-xl py-3.5 pl-11 pr-4 text-sm tracking-wide"
                                placeholder="client@domain.com">
                        </div>
                        <p class="text-xs text-slate-500 mt-2 ml-1">The license key will be associated with this address
                            but you must deliver the key manually.</p>
                    </div>

                    <div class="bg-amber-500/10 border border-amber-500/20 rounded-lg p-4 mb-6">
                        <p class="text-xs text-amber-400 flex items-start">
                            <i class="fas fa-info-circle mt-0.5 mr-2"></i>
                            This will instantly generate a unique active license key. You are allotted 50 generations
                            per minute to prevent system abuse.
                        </p>
                    </div>

                    <button type="submit" name="generate"
                        class="ent-btn-primary w-full py-4 rounded-xl flex justify-center items-center space-x-2">
                        <i class="fas fa-magic"></i>
                        <span>Generate Standard License</span>
                    </button>
                </form>
            </div>
        </div>

    </main>
</body>

</html>