<?php
// /reseller/login.php
// Set secure cookie parameters BEFORE session_start
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $cookieParams['domain'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ResellerLogger.php';

// Redirect if already logged in
if (isset($_SESSION['reseller_logged_in']) && $_SESSION['reseller_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    }
    else {
        // --- BRUTE FORCE PROTECTION: 5 failed attempts per 15 minutes per IP ---
        $failed_count = ResellerLogger::countLoginAttempts($pdo, $client_ip, '-15 minutes');
        if ($failed_count >= 5) {
            $error = "Too many login attempts. Please wait 15 minutes before trying again.";
            ResellerLogger::log($pdo, 'login_blocked', "Brute force block | IP: $client_ip | Email: $email", [
                'ip' => $client_ip, 'email' => $email
            ]);
        }
        else {
            // Find reseller by email (include license_key for session enforcement)
            $stmt = $pdo->prepare("SELECT id, email, password_hash, license_key, status, is_verified FROM resellers WHERE email = ?");
            $stmt->execute([$email]);
            $reseller = $stmt->fetch();

            if ($reseller && password_verify($password, $reseller['password_hash'])) {
                if ($reseller['status'] !== 'active') {
                    $error = "This reseller account is suspended. Please contact support.";
                    ResellerLogger::log($pdo, 'login_suspended', "Suspended account login attempt | IP: $client_ip | Email: $email", [
                        'ip' => $client_ip, 'email' => $email, 'reseller_id' => $reseller['id']
                    ]);
                }
                else {
                    // Login valid
                    session_regenerate_id(true);
                    $_SESSION['reseller_logged_in'] = true;
                    $_SESSION['reseller_id'] = $reseller['id'];
                    $_SESSION['reseller_email'] = $reseller['email'];
                    $_SESSION['reseller_key'] = $reseller['license_key'];
                    $_SESSION['reseller_is_verified'] = (int)$reseller['is_verified'];
                    $_SESSION['LAST_ACTIVITY'] = time();

                    ResellerLogger::log($pdo, 'login_success', "Reseller login | IP: $client_ip | Email: $email | Key: " . ($reseller['license_key'] ?? 'none'), [
                        'ip' => $client_ip, 'email' => $email, 'reseller_id' => $reseller['id']
                    ]);

                    header('Location: dashboard.php');
                    exit;
                }
            }
            else {
                $error = "Invalid credentials.";
                ResellerLogger::log($pdo, 'login_failed', "Invalid credentials | IP: $client_ip | Email: $email", [
                    'ip' => $client_ip, 'email' => $email
                ]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Portal - BioScript</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ent-primary: #10b981;
            /* Emerald green theme for reseller vs blue for root admin */
            --ent-bg: #020617;
            --ent-surface: #0f172a;
            --ent-border: #1e293b;
        }

        body {
            background-color: var(--ent-bg);
            background-image:
                radial-gradient(circle at 15% 50%, rgba(16, 185, 129, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(16, 185, 129, 0.05), transparent 25%);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .ent-glass {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .ent-input {
            background: rgba(2, 6, 23, 0.6);
            border: 1px solid var(--ent-border);
            color: #f8fafc;
            transition: all 0.2s;
        }

        .ent-input:focus {
            border-color: var(--ent-primary);
            box-shadow: 0 0 0 1px var(--ent-primary), inset 0 2px 4px rgba(0, 0, 0, 0.5);
            outline: none;
        }

        .ent-btn {
            background: linear-gradient(to bottom right, var(--ent-primary), #059669);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 0.75rem;
            font-weight: 900;
        }

        .ent-btn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }

        .ent-btn:active {
            transform: translateY(0);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Grid Pattern overlay -->
    <div class="absolute inset-0 z-0 pointer-events-none"
        style="background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 3rem 3rem; background-position: center center;">
    </div>

    <div class="ent-glass rounded-2xl w-full max-w-sm p-8 relative z-10 overflow-hidden group">
        <!-- Accent Line -->
        <div
            class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-emerald-500 to-transparent opacity-50">
        </div>

        <div class="text-center mb-8">
            <div
                class="w-16 h-16 mx-auto bg-slate-900 border border-slate-700/50 rounded-2xl flex items-center justify-center mb-6 shadow-xl relative group-hover:border-emerald-500/30 transition-colors duration-500">
                <i class="fas fa-briefcase text-2xl text-emerald-500"></i>
                <div
                    class="absolute inset-0 bg-emerald-500/20 blur-xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                </div>
            </div>
            <h1 class="text-xl font-bold text-white tracking-tight mb-1">Reseller Portal</h1>
            <p class="text-[10px] font-black uppercase text-slate-500 tracking-widest">BioScript Agency Access</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-lg mb-6 flex items-start space-x-3">
            <i class="fas fa-exclamation-triangle mt-0.5"></i>
            <span class="text-sm font-medium">
                <?php echo htmlspecialchars($error); ?>
            </span>
        </div>
        <?php
endif; ?>

        <?php if (isset($_GET['msg'])): ?>
        <div
            class="bg-blue-500/10 border border-blue-500/20 text-blue-400 p-4 rounded-lg mb-6 flex items-start space-x-3">
            <i class="fas fa-info-circle mt-0.5"></i>
            <span class="text-sm font-medium">
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </span>
        </div>
        <?php
endif; ?>

        <form method="POST" class="space-y-5 relative">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Agency
                    Email</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input type="email" name="email" placeholder="partner@agency.com" required
                        class="ent-input w-full rounded-xl py-3.5 pl-11 pr-4 text-sm tracking-wide">
                </div>
            </div>
            <div>
                <label
                    class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Passphrase</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input type="password" name="password" placeholder="••••••••" required
                        class="ent-input w-full rounded-xl py-3.5 pl-11 pr-4 text-sm tracking-widest font-mono">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" name="login"
                    class="ent-btn w-full py-4 rounded-xl flex justify-center items-center space-x-2">
                    <span>Access Portal</span>
                    <i class="fas fa-arrow-right opacity-70"></i>
                </button>
            </div>
        </form>
    </div>
</body>

</html>