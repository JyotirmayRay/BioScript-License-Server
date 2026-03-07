<?php
// /reseller/activate.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ResellerLogger.php';

// Redirect if already verified
if (isset($_SESSION['reseller_is_verified']) && $_SESSION['reseller_is_verified'] === 1) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate'])) {
    $reseller_key = trim($_POST['reseller_key'] ?? '');

    if (empty($reseller_key)) {
        $error = "Please enter your Reseller Key.";
    }
    else {
        // Verify key against database
        $stmt = $pdo->prepare("SELECT id, license_key FROM resellers WHERE id = ?");
        $stmt->execute([$_SESSION['reseller_id']]);
        $reseller = $stmt->fetch();

        if ($reseller && $reseller['license_key'] === $reseller_key) {
            // Success: Mark as verified
            $pdo->prepare("UPDATE resellers SET is_verified = 1 WHERE id = ?")->execute([$reseller['id']]);

            // Update Session
            $_SESSION['reseller_is_verified'] = 1;

            ResellerLogger::log($pdo, 'account_paired', "Reseller account paired with key | ID: " . $reseller['id'], [
                'reseller_id' => $reseller['id'],
                'key' => $reseller_key
            ]);

            $success = "Account successfully paired! You can now generate licenses.";
            header("Refresh: 2; url=dashboard.php");
        }
        else {
            $error = "Invalid Reseller Key. Please check the welcome email you received.";
            ResellerLogger::log($pdo, 'activation_failed', "Invalid key pairing attempt | ID: " . $_SESSION['reseller_id'], [
                'reseller_id' => $_SESSION['reseller_id'],
                'attempted_key' => $reseller_key
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Account - Reseller Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ent-primary: #10b981;
            --ent-bg: #020617;
            --ent-surface: #0f172a;
            --ent-border: #1e293b;
        }

        body {
            background-color: var(--ent-bg);
            background-image:
                radial-gradient(circle at 15% 50%, rgba(16, 185, 129, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(16, 185, 129, 0.05), transparent 25%);
            font-family: ui-sans-serif, system-ui, sans-serif;
        }

        .ent-glass {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .ent-input {
            background: rgba(2, 6, 23, 0.6);
            border: 1px solid var(--ent-border);
            color: #f8fafc;
        }

        .ent-input:focus {
            border-color: var(--ent-primary);
            box-shadow: 0 0 0 1px var(--ent-primary);
            outline: none;
        }

        .ent-btn {
            background: linear-gradient(to bottom right, var(--ent-primary), #059669);
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 0.75rem;
            font-weight: 900;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="ent-glass rounded-2xl w-full max-w-md p-8 relative z-10">
        <div class="text-center mb-8">
            <div
                class="w-16 h-16 mx-auto bg-slate-900 border border-slate-700/50 rounded-2xl flex items-center justify-center mb-6 shadow-xl">
                <i class="fas fa-shield-halved text-2xl text-emerald-500"></i>
            </div>
            <h1 class="text-xl font-bold text-white tracking-tight mb-2">Account Activation</h1>
            <p class="text-xs text-slate-400">Please enter your unique Reseller Key to pair your account. You only need
                to do this once.</p>
        </div>

        <?php if ($success): ?>
        <div
            class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-lg mb-6 flex items-start space-x-3">
            <i class="fas fa-check-circle mt-0.5"></i>
            <span class="text-sm font-medium">
                <?php echo $success; ?>
            </span>
        </div>
        <?php
endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-lg mb-6 flex items-start space-x-3">
            <i class="fas fa-exclamation-triangle mt-0.5"></i>
            <span class="text-sm font-medium">
                <?php echo $error; ?>
            </span>
        </div>
        <?php
endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Reseller
                    Key</label>
                <div class="relative">
                    <i class="fas fa-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input type="text" name="reseller_key" placeholder="RES-XXXX-XXXX-XXXX" required
                        class="ent-input w-full rounded-xl py-3.5 pl-11 pr-4 text-sm font-mono tracking-widest">
                </div>
                <p class="mt-3 text-[10px] text-slate-500 leading-relaxed italic">
                    <i class="fas fa-info-circle mr-1"></i> This key was sent to your registered email address when your
                    agency account was created.
                </p>
            </div>

            <button type="submit" name="activate"
                class="ent-btn w-full py-4 rounded-xl flex justify-center items-center space-x-2">
                <span>Pair Account</span>
                <i class="fas fa-link opacity-70"></i>
            </button>
        </form>
    </div>
</body>

</html>