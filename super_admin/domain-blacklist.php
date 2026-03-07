<?php
require_once __DIR__ . '/auth.php';

// Helper to normalize domain for blacklisting
function normalize_domain_for_blacklist($domain)
{
    if (empty($domain))
        return '';
    $domain = strtolower(trim($domain));
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    $domain = preg_replace('/^www\./', '', $domain);
    $domain = explode('/', $domain)[0];
    return $domain;
}

// Handle Add/Remove Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    $action = $_POST['action'];

    if ($action === 'add') {
        $raw_domain = $_POST['domain'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $domain = normalize_domain_for_blacklist($raw_domain);

        if (!empty($domain)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO domain_blacklist (domain, reason) VALUES (?, ?)");
                $stmt->execute([$domain, $reason]);
                $success = "Domain {$domain} has been added to the blacklist.";
            }
            catch (PDOException $e) {
                // Ignore unique constraint violations (already blacklisted)
                if ($e->getCode() === '23000') {
                    $error = "Domain {$domain} is already blacklisted.";
                }
                else {
                    $error = "Database Error: " . $e->getMessage();
                }
            }
        }
        else {
            $error = "Please provide a valid domain name.";
        }
    }
    elseif ($action === 'remove') {
        $id = (int)($_POST['blacklist_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM domain_blacklist WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Domain removed from blacklist.";
        }
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Count total
$countQuery = "SELECT COUNT(*) FROM domain_blacklist";
$total_blacklisted = (int)$pdo->query($countQuery)->fetchColumn();
$total_pages = max(1, ceil($total_blacklisted / $limit));

// Fetch Blacklist
$stmt = $pdo->prepare("SELECT * FROM domain_blacklist ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$blacklisted_domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Blacklist - Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: { 850: '#1e293b', 950: '#0f172a' },
                        primary: { 500: '#e11d48', 600: '#be123c' }
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
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-rose-500 transition-all group">
                <i class="fas fa-users-cog w-5 text-center group-hover:text-rose-400 transition-colors"></i>
                <span
                    class="font-semibold tracking-wide text-sm group-hover:text-rose-100 transition-colors">Resellers</span>
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
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-rose-400 border-l-2 border-rose-500 transition-all group">
                <i class="fas fa-ban w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">Domain Blacklist</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-8 lg:p-12 relative">
        <header class="mb-10 relative z-10 flex flex-col justify-between items-start gap-6">
            <div>
                <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Domain Blacklist</h2>
                <p class="text-slate-400">Proactively block known fraudulent domains from activating any license.</p>
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

        <?php if (isset($error)): ?>
        <div
            class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-8 flex items-center relative z-10">
            <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
            <span>
                <?php echo htmlspecialchars($error); ?>
            </span>
        </div>
        <?php
endif; ?>

        <!-- Add New Blacklist Record -->
        <form method="POST" class="ent-glass p-8 rounded-2xl mb-12 relative z-10">
            <h3 class="text-lg font-bold text-white mb-6 flex items-center">
                <i class="fas fa-shield-virus text-rose-500 mr-3"></i> Add Domain to Blacklist
            </h3>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-1">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Domain
                        Name</label>
                    <input type="text" name="domain" required placeholder="example.com"
                        class="w-full bg-slate-950/50 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:border-rose-500 focus:outline-none transition-all font-mono">
                    <p class="text-[10px] text-slate-500 mt-2">HTTP(S) and WWW will be stripped automatically.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Internal Reason
                        (Optional)</label>
                    <div class="flex">
                        <input type="text" name="reason" placeholder="e.g. Known chargeback fraud ring"
                            class="flex-1 bg-slate-950/50 border border-slate-700 border-r-0 rounded-l-xl px-4 py-3 text-sm text-white focus:border-rose-500 focus:outline-none transition-all">
                        <button type="submit"
                            class="bg-rose-600 hover:bg-rose-500 text-white font-bold px-6 py-3 rounded-r-xl transition-colors border border-rose-600 hover:border-rose-500 text-sm">
                            <i class="fas fa-plus mr-2"></i> Blacklist
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="ent-glass rounded-2xl shadow-xl overflow-hidden relative z-10">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-400">
                    <thead
                        class="bg-slate-900/80 text-slate-300 uppercase text-[10px] font-black tracking-widest border-b border-slate-700/50">
                        <tr>
                            <th class="px-6 py-5">Blocked Domain</th>
                            <th class="px-6 py-5">Reason</th>
                            <th class="px-6 py-5">Date Blacklisted</th>
                            <th class="px-6 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach ($blacklisted_domains as $b): ?>
                        <tr class="hover:bg-slate-800/40 transition-colors group">
                            <td class="px-6 py-4">
                                <span class="font-mono text-rose-400 tracking-tight"><i
                                        class="fas fa-ban opacity-50 mr-2 text-[10px]"></i>
                                    <?php echo htmlspecialchars($b['domain']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-300">
                                <?php echo !empty($b['reason']) ? htmlspecialchars($b['reason']) : '<span class="text-slate-600 italic text-xs">No reason provided</span>'; ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?php echo date('M d, Y', strtotime($b['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" class="inline"
                                    onsubmit="return confirm('Remove this domain from the blacklist? It will be able to activate licenses again.');">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="blacklist_id" value="<?php echo $b['id']; ?>">
                                    <button type="submit"
                                        class="p-2 text-slate-500 hover:text-emerald-500 transition-colors"
                                        title="Remove block">
                                        <i class="fas fa-unlock"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php
endforeach; ?>

                        <?php if (empty($blacklisted_domains)): ?>
                        <tr>
                            <td colspan="4"
                                class="text-center py-12 text-slate-500 text-sm tracking-widest uppercase font-bold"><i
                                    class="fas fa-shield-alt text-2xl mb-3 block opacity-20"></i> Blacklist is empty
                            </td>
                        </tr>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-slate-900/50 border-t border-slate-700/50 flex justify-between items-center">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Page
                    <?php echo $page; ?> of
                    <?php echo $total_pages; ?>
                </span>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>"
                        class="px-3 py-1 bg-slate-800 text-white rounded hover:bg-slate-700 text-xs font-bold transition-colors">Prev</a>
                    <?php
    endif; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>"
                        class="px-3 py-1 bg-slate-800 text-white rounded hover:bg-slate-700 text-xs font-bold transition-colors">Next</a>
                    <?php
    endif; ?>
                </div>
            </div>
            <?php
endif; ?>
        </div>
    </main>
</body>

</html>