<?php
// /reseller/generate-license.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$reseller_id = $_SESSION['reseller_id'];
$reseller_email = $_SESSION['reseller_email'];

$success = null;
$error = null;

// CSRF wrapper
if (empty($_SESSION['reseller_csrf'])) {
    $_SESSION['reseller_csrf'] = bin2hex(random_bytes(32));
}

// RATE LIMITING: Math max 50 per minute per session
$rate_limit_window = 60; // seconds
$rate_limit_max = 50;

if (!isset($_SESSION['reseller_rate_limit'])) {
    $_SESSION['reseller_rate_limit'] = [];
}

// Clean old requests from session
$now = time();
$_SESSION['reseller_rate_limit'] = array_filter($_SESSION['reseller_rate_limit'], function ($timestamp) use ($now, $rate_limit_window) {
    return ($now - $timestamp) < $rate_limit_window;
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || trim($_POST['csrf_token']) !== $_SESSION['reseller_csrf']) {
        $error = "Security token mismatch. Please try again.";
    }
    elseif (count($_SESSION['reseller_rate_limit']) >= $rate_limit_max) {
        $error = "Rate limit exceeded. You can only generate 50 licenses per minute to prevent abuse.";
    }
    else {
        $customer_email = trim($_POST['customer_email'] ?? '');

        if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please provide a valid customer email address.";
        }
        else {
            // Log attempt
            $_SESSION['reseller_rate_limit'][] = $now;

            // Generate BIO-XXXX-XXXX-XXXX
            // We use uppercase alphanumeric for format parity with main engine
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

                // Check if key exists
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
                // Insert into licenses. Using client_email specifically to map to main engine requirements
                try {
                    $stmt = $pdo->prepare("INSERT INTO licenses (license_key, client_email, type, reseller_id, status, max_domains) VALUES (:key, :email, 'reseller_generated', :reseller_id, 'pending_activation', 1)");
                    $stmt->execute([
                        ':key' => $final_key,
                        ':email' => $customer_email,
                        ':reseller_id' => $reseller_id
                    ]);

                    // Phase 4: Increment reseller's total_generated counter
                    $pdo->prepare("UPDATE resellers SET total_generated = total_generated + 1 WHERE id = ?")
                        ->execute([$reseller_id]);

                    // Phase 4: Log system event
                    try {
                        $pdo->prepare("INSERT INTO system_events (event_type, details) VALUES ('license_generated', ?)")
                            ->execute(["Reseller: $reseller_id | License: $final_key | Customer: $customer_email"]);
                    }
                    catch (Exception $e) {
                    }

                    $success = [
                        'key' => $final_key,
                        'email' => htmlspecialchars($customer_email)
                    ];
                }
                catch (PDOException $e) {
                    $error = "Database error generating license: " . $e->getMessage();
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