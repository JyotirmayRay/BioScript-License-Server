<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

// --- AUTHENTICATION & ROUTING ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['ls_admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Fetch Admin Credentials from DB
    $stmt = $pdo->query("SELECT admin_user, admin_pass FROM platform_settings WHERE id = 1");
    $admin = $stmt->fetch();

    if ($admin && $user === $admin['admin_user'] && password_verify($pass, $admin['admin_pass'])) {
        $_SESSION['ls_admin_logged_in'] = true;
        $_SESSION['ls_admin_user'] = $user;
        header('Location: dashboard.php');
        exit;
    }
    else {
        $error = "Invalid credentials.";
    }
}

// --- DASHBOARD ACTIONS ---
// Legacy action handling removed - now fully self-contained in dashboard.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Authority - BioScript</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ent-primary: #2563eb;
            --ent-bg: #020617;
            --ent-surface: #0f172a;
            --ent-border: #1e293b;
        }

        body {
            background-color: var(--ent-bg);
            background-image:
                radial-gradient(circle at 15% 50%, rgba(37, 99, 235, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(37, 99, 235, 0.05), transparent 25%);
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
            background: linear-gradient(to bottom right, var(--ent-primary), #1d4ed8);
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
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
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
            class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-50">
        </div>

        <div class="text-center mb-8">
            <div
                class="w-16 h-16 mx-auto bg-slate-900 border border-slate-700/50 rounded-2xl flex items-center justify-center mb-6 shadow-xl relative group-hover:border-blue-500/30 transition-colors duration-500">
                <i class="fas fa-fingerprint text-2xl text-blue-500"></i>
                <div
                    class="absolute inset-0 bg-blue-500/20 blur-xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                </div>
            </div>
            <h1 class="text-xl font-bold text-white tracking-tight mb-1">Architecture Control</h1>
            <p class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Restricted Personnel Only</p>
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

        <form method="POST" class="space-y-5 relative">
            <div>
                <label
                    class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Identity</label>
                <div class="relative">
                    <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input type="text" name="username" placeholder="Admin Node" required
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
                    <span>Authenticate</span>
                    <i class="fas fa-arrow-right opacity-70"></i>
                </button>
            </div>
        </form>
    </div>
</body>

</html>