<?php
require_once __DIR__ . '/auth.php';

// Handle Actions (Suspend/Activate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    $id = (int)($_POST['reseller_id'] ?? 0);
    $action = $_POST['action'];

    if ($id > 0) {
        if ($action === 'suspend') {
            $stmt = $pdo->prepare("UPDATE resellers SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Reseller suspended successfully.";
        }
        elseif ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE resellers SET status = 'active' WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Reseller activated successfully.";
        }
    }
}

// Fetch Resellers and their aggregate metrics
// SQLite doesn't have a direct count join as efficiently as MySQL, so we use subqueries for safety
$query = "
    SELECT r.*,
           (SELECT COUNT(*) FROM licenses WHERE reseller_id = r.id) as total_licenses_generated,
           (SELECT COUNT(*) FROM licenses WHERE reseller_id = r.id AND status = 'active') as active_licenses
    FROM resellers r
    ORDER BY r.created_at DESC
";
$stmt = $pdo->query($query);
$resellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resellers Management - Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: { 850: '#1e293b', 950: '#0f172a' },
                        primary: { 500: '#e11d48', 600: '#be123c' } /* Rose theme for Super Admin */
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
        .ui-enterprise {
            font-family: 'Inter', sans-serif;
            --ent-primary: #e11d48;
            --ent-primary-dark: #be123c;
            --ent-surface: #0f172a;
            --ent-card: #1e293b;
            --ent-border: #334155;
            --ent-text: #f8fafc;
        }

        .ent-glass {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--ent-border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>

<body class="ui-enterprise bg-slate-950 text-slate-100 h-screen flex overflow-hidden font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-950 border-r border-slate-800 flex flex-col hidden md:flex shrink-0 z-20">
        <div class="p-6 border-b border-slate-800 flex items-center space-x-3">
            <div class="w-8 h-8 bg-rose-600 rounded flex items-center justify-center border border-rose-500 shadow-sm">
                <i class="fas fa-crown text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-base font-black text-rose-500 tracking-widest uppercase leading-tight">Super<span
                        class="text-white">Admin</span></h1>
                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Network Authority</p>
            </div>
        </div>

        <nav class="flex-1 py-6 space-y-1 overflow-y-auto">
            <a href="../dashboard.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-500 transition-all group">
                <i class="fas fa-arrow-left w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">Back to CRM</span>
            </a>

            <div class="mt-4 mb-4 border-t border-slate-800/50"></div>

            <p class="px-6 text-[10px] font-black text-rose-500 uppercase tracking-widest mb-3">Super Admin</p>

            <a href="resellers.php"
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-rose-400 border-l-2 border-rose-500 transition-all group">
                <i class="fas fa-users-cog w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">Resellers</span>
            </a>

            <a href="reseller-customers.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-rose-500 transition-all group">
                <i class="fas fa-user-friends w-5 text-center group-hover:text-rose-400 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm group-hover:text-rose-100 transition-colors">Reseller
                    Customers</span>
            </a>

            <a href="license-monitor.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-rose-500 transition-all group">
                <i class="fas fa-shield-alt w-5 text-center group-hover:text-rose-400 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm group-hover:text-rose-100 transition-colors">License
                    Monitor</span>
            </a>

            <a href="domain-blacklist.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-rose-500 transition-all group">
                <i class="fas fa-ban w-5 text-center group-hover:text-rose-400 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm group-hover:text-rose-100 transition-colors">Domain
                    Blacklist</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-8 lg:p-12 relative">
        <header class="mb-10 relative z-10 flex justify-between items-end">
            <div>
                <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Resellers Management</h2>
                <p class="text-slate-400">View and manage authorized BioScript resellers.</p>
            </div>
        </header>

        <?php if (isset($success)): ?>
        <div
            class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-8 flex items-center relative z-10">
            <i class="fas fa-check-circle mr-3 text-lg"></i>
            <span>
                <?php echo htmlspecialchars($success); ?>
            </span>
        </div>
        <?php
endif; ?>

        <!-- Table -->
        <div class="ent-glass rounded-2xl shadow-xl overflow-hidden relative z-10">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-400">
                    <thead
                        class="bg-slate-900/80 text-slate-300 uppercase text-xs font-bold tracking-wider border-b border-slate-700/50">
                        <tr>
                            <th class="px-6 py-5">Reseller Email</th>
                            <th class="px-6 py-5">Generated Licenses</th>
                            <th class="px-6 py-5">Active Domains</th>
                            <th class="px-6 py-5">Status</th>
                            <th class="px-6 py-5">Date Enrolled</th>
                            <th class="px-6 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach ($resellers as $r): ?>
                        <tr class="hover:bg-slate-800/40 transition-colors group">
                            <td class="px-6 py-4 text-white font-medium">
                                <i class="fas fa-user-tie text-rose-500 mr-2 opacity-50"></i>
                                <?php echo htmlspecialchars($r['email']); ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-slate-300">
                                <?php echo number_format($r['total_licenses_generated']); ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-emerald-400">
                                <?php echo number_format($r['active_licenses']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($r['status'] === 'active'): ?>
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-emerald-500/10 text-emerald-400 border-emerald-500/20">Active</span>
                                <?php
    else: ?>
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-red-500/10 text-red-400 border-red-500/20">Suspended</span>
                                <?php
    endif; ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2 border-opacity-0">
                                    <a href="reseller-customers.php?reseller_id=<?php echo $r['id']; ?>"
                                        class="p-2 text-slate-400 hover:text-blue-400 transition-colors"
                                        title="View Customers">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Change reseller access status?');">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="reseller_id" value="<?php echo $r['id']; ?>">

                                        <?php if ($r['status'] === 'active'): ?>
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit"
                                            class="p-2 text-slate-400 hover:text-red-400 transition-colors"
                                            title="Suspend Reseller">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <?php
    else: ?>
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit"
                                            class="p-2 text-slate-400 hover:text-emerald-400 transition-colors"
                                            title="Activate Reseller">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                        <?php
    endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php
endforeach; ?>

                        <?php if (empty($resellers)): ?>
                        <tr>
                            <td colspan="6"
                                class="text-center py-12 text-slate-500 text-sm tracking-widest uppercase font-bold"><i
                                    class="fas fa-user-slash text-2xl mb-3 block opacity-20"></i> No Resellers Found
                            </td>
                        </tr>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>

</html>