<?php
// /reseller/customers.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$reseller_id = $_SESSION['reseller_id'];
$reseller_email = $_SESSION['reseller_email'];

$success = null;
$error = null;

// --- CRUD ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['reseller_csrf'] ?? '')) {
        $error = "Security token mismatch.";
    }
    else {
        $license_id = (int)($_POST['license_id'] ?? 0);
        $action = $_POST['action'];

        if ($license_id > 0) {
            // Verify this license belongs to this reseller
            $stmt = $pdo->prepare("SELECT id, status FROM licenses WHERE id = ? AND reseller_id = ?");
            $stmt->execute([$license_id, $reseller_id]);
            $license = $stmt->fetch();

            if ($license) {
                if ($action === 'revoke' && $license['status'] === 'active') {
                    $pdo->prepare("UPDATE licenses SET status = 'revoked' WHERE id = ? AND reseller_id = ?")
                        ->execute([$license_id, $reseller_id]);
                    $success = "License revoked successfully.";
                }
                elseif ($action === 'delete') {
                    $pdo->prepare("UPDATE licenses SET status = 'deleted_by_reseller' WHERE id = ? AND reseller_id = ?")
                        ->execute([$license_id, $reseller_id]);
                    $success = "Customer removed from your panel.";
                }
            }
            else {
                $error = "License not found or access denied.";
            }
        }
    }
}

// Ensure CSRF token exists
if (empty($_SESSION['reseller_csrf'])) {
    $_SESSION['reseller_csrf'] = bin2hex(random_bytes(32));
}

// Fetch customers — hide soft-deleted by reseller
$stmt = $pdo->prepare("SELECT id, client_email, license_key, registered_domains, status, created_at FROM licenses WHERE reseller_id = ? AND status != 'deleted_by_reseller' ORDER BY created_at DESC");
$stmt->execute([$reseller_id]);
$customers = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - BioScript Reseller</title>
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

        /* Custom Scrollbar for the table */
        .ui-reseller ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .ui-reseller ::-webkit-scrollbar-track {
            background: transparent;
        }

        .ui-reseller ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 3px;
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
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-key w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">Generate License</span>
            </a>
            <a href="customers.php"
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-emerald-400 border-l-2 border-emerald-500 transition-all group">
                <i class="fas fa-users w-5 text-center"></i>
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

    <main class="flex-1 overflow-y-auto p-8 lg:p-12 relative flex flex-col">
        <div
            class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-emerald-900/10 to-transparent pointer-events-none">
        </div>

        <header class="flex justify-between items-end mb-10 relative z-10 shrink-0">
            <div>
                <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Customer Register</h2>
                <p class="text-slate-400">View and manage the licenses you have issued.</p>
            </div>
            <a href="generate-license.php"
                class="ent-btn-primary font-bold py-3 px-6 rounded-xl transition-all flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Issue License</span>
            </a>
        </header>

        <?php if ($success): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 flex items-center relative z-10">
            <i class="fas fa-check-circle mr-3"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 flex items-center relative z-10">
            <i class="fas fa-exclamation-circle mr-3"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <div class="ent-card rounded-2xl shadow-xl overflow-hidden relative z-10 flex-1 flex flex-col bg-slate-900/50">
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left text-sm text-slate-400">
                    <thead
                        class="bg-slate-900 text-slate-300 uppercase text-xs font-bold tracking-wider border-b border-slate-800 sticky top-0 z-20">
                        <tr>
                            <th class="px-6 py-5">Customer Email</th>
                            <th class="px-6 py-5">License Key</th>
                            <th class="px-6 py-5">Active Domain</th>
                            <th class="px-6 py-5">Status</th>
                            <th class="px-6 py-5">Issue Date</th>
                            <th class="px-6 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <div
                                        class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mb-4">
                                        <i class="fas fa-inbox text-2xl text-slate-600"></i>
                                    </div>
                                    <p class="text-lg font-medium text-slate-300 mb-1">No customers yet</p>
                                    <p class="text-sm">Generate your first license to see it here.</p>
                                </div>
                            </td>
                        </tr>
                        <?php
else: ?>
                        <?php foreach ($customers as $customer):
        // Parse domains from JSON
        $domains = json_decode($customer['registered_domains'] ?: '[]', true);
        $display_domain = is_array($domains) && count($domains) > 0 ? $domains[0] : 'None';
?>
                        <tr class="hover:bg-slate-800/30 transition-colors group">
                            <td class="px-6 py-4">
                                <span class="font-medium text-white">
                                    <?php echo htmlspecialchars($customer['client_email']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <span
                                        class="text-xs font-mono text-emerald-400 bg-emerald-500/10 px-2 py-1 rounded border border-emerald-500/20 truncate max-w-[180px]">
                                        <?php echo htmlspecialchars($customer['license_key']); ?>
                                    </span>
                                    <button
                                        onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($customer['license_key']); ?>');"
                                        class="text-slate-500 hover:text-emerald-400 transition-colors"
                                        title="Copy Key">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($display_domain !== 'None'): ?>
                                <span class="text-blue-400 font-mono text-xs"><i
                                        class="fas fa-globe mr-1 text-slate-500"></i>
                                    <?php echo htmlspecialchars($display_domain); ?>
                                </span>
                                <?php
        else: ?>
                                <span class="text-slate-600 text-xs italic">Awaiting Activation</span>
                                <?php
        endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($customer['status'] === 'active'): ?>
                                <span class="text-emerald-400 text-xs font-bold uppercase flex items-center">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></span> Active
                                </span>
                                <?php
        elseif ($customer['status'] === 'pending_activation'): ?>
                                <span class="text-amber-400 text-xs font-bold uppercase flex items-center">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-2"></span> Pending
                                </span>
                                <?php
        else: ?>
                                <span class="text-red-400 text-xs font-bold uppercase flex items-center">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-2"></span>
                                    <?php echo htmlspecialchars($customer['status']); ?>
                                </span>
                                <?php
        endif; ?>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs">
                                <?php echo date('M d, Y - H:i', strtotime($customer['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-1">
                                    <?php if ($customer['status'] === 'active'): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Revoke this license?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['reseller_csrf']; ?>">
                                        <input type="hidden" name="license_id" value="<?php echo $customer['id']; ?>">
                                        <input type="hidden" name="action" value="revoke">
                                        <button type="submit" class="p-2 text-slate-500 hover:text-amber-400 transition-colors" title="Revoke License">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Remove this customer from your panel? The license record will be preserved for admin review.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['reseller_csrf']; ?>">
                                        <input type="hidden" name="license_id" value="<?php echo $customer['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="p-2 text-slate-500 hover:text-red-400 transition-colors" title="Remove Customer">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php
    endforeach; ?>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>

            <div
                class="p-4 border-t border-slate-800 bg-slate-900/80 flex justify-between items-center text-xs text-slate-500">
                <span>Showing
                    <?php echo count($customers); ?> registered customers
                </span>
                <span class="font-mono bg-slate-800 px-2 py-1 rounded border border-slate-700 text-slate-400">Data
                    matches live CRM</span>
            </div>
        </div>

    </main>
</body>

</html>