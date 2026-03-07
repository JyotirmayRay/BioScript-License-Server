<?php
require_once __DIR__ . '/auth.php';

$success = null;
$error = null;

// --- CRUD ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    $license_id = (int)($_POST['license_id'] ?? 0);
    $action = $_POST['action'];

    if ($license_id > 0) {
        if ($action === 'revoke') {
            $pdo->prepare("UPDATE licenses SET status = 'revoked' WHERE id = ?")->execute([$license_id]);
            $success = "License revoked.";
        }
        elseif ($action === 'reactivate') {
            $pdo->prepare("UPDATE licenses SET status = 'active' WHERE id = ?")->execute([$license_id]);
            $success = "License re-activated.";
        }
        elseif ($action === 'delete') {
            $pdo->prepare("UPDATE licenses SET status = 'deleted_by_admin' WHERE id = ?")->execute([$license_id]);
            $success = "License deleted (soft).";
        }
        elseif ($action === 'restore') {
            $pdo->prepare("UPDATE licenses SET status = 'active' WHERE id = ?")->execute([$license_id]);
            $success = "License restored to active.";
        }
    }
}

// Pagination and Search
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$reseller_id = (int)($_GET['reseller_id'] ?? 0);

$whereClause = "WHERE type = 'reseller_generated'";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (l.client_email LIKE :search OR l.license_key LIKE :search OR l.registered_domains LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($reseller_id > 0) {
    $whereClause .= " AND l.reseller_id = :reseller_id";
    $params[':reseller_id'] = $reseller_id;
}

// Count total
$countQuery = "SELECT COUNT(*) FROM licenses l $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$total_customers = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total_customers / $limit));

// Fetch Customers + JOIN Reseller Email
$query = "
    SELECT l.*, r.email as reseller_email
    FROM licenses l
    LEFT JOIN resellers r ON l.reseller_id = r.id
    $whereClause
    ORDER BY l.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function parse_active_domain($json)
{
    $arr = json_decode($json, true);
    if (is_array($arr) && !empty($arr)) {
        return htmlspecialchars($arr[0]);
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Customers - Super Admin</title>
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
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-rose-400 border-l-2 border-rose-500 transition-all group">
                <i class="fas fa-user-friends w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">Reseller Customers</span>
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
        <header class="mb-10 relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
            <div>
                <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Reseller Customers</h2>
                <p class="text-slate-400">Search and audit licenses generated by external resellers.</p>
            </div>

            <form method="GET" class="w-full md:w-96 flex">
                <?php if ($reseller_id > 0): ?>
                <input type="hidden" name="reseller_id" value="<?php echo $reseller_id; ?>">
                <?php
endif; ?>
                <div class="relative w-full">
                    <i class="fas fa-search absolute left-4 top-3.5 text-slate-500"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search email, domain, or key..."
                        class="w-full bg-slate-950/50 border border-slate-700/50 rounded-xl pl-12 pr-4 py-3 text-sm text-white focus:border-rose-500 focus:outline-none transition-all">
                </div>
                <button type="submit"
                    class="ml-2 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-xl transition-colors border border-slate-700 text-sm font-bold">Search</button>
            </form>
        </header>

        <?php if ($success): ?>
        <div
            class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 flex items-center relative z-10">
            <i class="fas fa-check-circle mr-3"></i><span>
                <?php echo htmlspecialchars($success); ?>
            </span>
        </div>
        <?php
endif; ?>
        <?php if ($error): ?>
        <div
            class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 flex items-center relative z-10">
            <i class="fas fa-exclamation-circle mr-3"></i><span>
                <?php echo htmlspecialchars($error); ?>
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
                            <th class="px-6 py-5">Customer Email</th>
                            <th class="px-6 py-5">License Key</th>
                            <th class="px-6 py-5">Reseller Origin</th>
                            <th class="px-6 py-5">Activated Domain</th>
                            <th class="px-6 py-5">Status</th>
                            <th class="px-6 py-5">Creation Date</th>
                            <th class="px-6 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach ($customers as $c):
    $domain = parse_active_domain($c['registered_domains']);
?>
                        <tr class="hover:bg-slate-800/40 transition-colors group">
                            <td class="px-6 py-4 text-white font-medium">
                                <i class="fas fa-user-circle text-slate-500 mr-2"></i>
                                <?php echo htmlspecialchars($c['client_email']); ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-blue-400">
                                <?php echo htmlspecialchars($c['license_key']); ?>
                            </td>
                            <td class="px-6 py-4 text-slate-300 font-medium">
                                <?php echo htmlspecialchars($c['reseller_email'] ?? 'Unknown'); ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-rose-400">
                                <?php echo $domain ? $domain : '<span class="text-slate-600 uppercase text-[10px] tracking-widest font-black">Unbound</span>'; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
    $s = strtolower($c['status']);
    if ($s === 'active') {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-emerald-500/10 text-emerald-400 border-emerald-500/20">Active</span>';
    }
    elseif ($s === 'pending_activation') {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-amber-500/10 text-amber-500 border-amber-500/20">Pending Email</span>';
    }
    elseif ($s === 'revoked' || $s === 'banned') {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-red-500/10 text-red-500 border-red-500/20">Revoked</span>';
    }
    elseif ($s === 'deleted_by_reseller') {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-slate-800 text-rose-300 border-rose-500/30"><i class="fas fa-user-slash mr-1"></i> Reseller Deleted</span>';
    }
    else {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-slate-800 text-slate-400 border-slate-700">' . $s . '</span>';
    }
?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?php echo date('M d, Y', strtotime($c['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-1">
                                    <?php if ($s === 'active'): ?>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Revoke this license?');">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="license_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="action" value="revoke">
                                        <button type="submit"
                                            class="p-2 text-slate-400 hover:text-amber-400 transition-colors"
                                            title="Revoke"><i class="fas fa-ban"></i></button>
                                    </form>
                                    <?php
    elseif ($s === 'revoked' || $s === 'deleted_by_reseller'): ?>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Restore this license to active?');">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="license_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="action" value="reactivate">
                                        <button type="submit"
                                            class="p-2 text-slate-400 hover:text-emerald-400 transition-colors"
                                            title="Re-activate"><i class="fas fa-check-circle"></i></button>
                                    </form>
                                    <?php
    endif; ?>
                                    <?php if ($s !== 'deleted_by_admin'): ?>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Delete this license?');">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="license_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit"
                                            class="p-2 text-slate-400 hover:text-red-500 transition-colors"
                                            title="Delete"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                    <?php
    endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
endforeach; ?>

                        <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="7"
                                class="text-center py-12 text-slate-500 text-sm tracking-widest uppercase font-bold"><i
                                    class="fas fa-search text-2xl mb-3 block opacity-20"></i> No Customers Found</td>
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
                    <?php
    $qs = '';
    if (!empty($search))
        $qs .= '&search=' . urlencode($search);
    if ($reseller_id > 0)
        $qs .= '&reseller_id=' . $reseller_id;
?>
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $qs; ?>"
                        class="px-3 py-1 bg-slate-800 text-white rounded hover:bg-slate-700 text-xs font-bold transition-colors">Prev</a>
                    <?php
    endif; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $qs; ?>"
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