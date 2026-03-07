<?php
require_once __DIR__ . '/auth.php';

// Handle Revocation Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke') {
    verify_csrf();
    $id = (int)($_POST['license_id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE licenses SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$id]);
        $success = "License access has been permanently revoked.";
    }
}

// Pagination and Filters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(client_email LIKE :search OR license_key LIKE :search OR registered_domains LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($filter_type)) {
    $whereConditions[] = "type = :type";
    $params[':type'] = $filter_type;
}

if (!empty($filter_status)) {
    $whereConditions[] = "status = :status";
    $params[':status'] = $filter_status;
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Count total
$countQuery = "SELECT COUNT(*) FROM licenses $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$total_licenses = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total_licenses / $limit));

// Fetch Licenses
$query = "
    SELECT *
    FROM licenses
    $whereClause
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>License Monitor - Super Admin</title>
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
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-rose-400 border-l-2 border-rose-500 transition-all group">
                <i class="fas fa-shield-alt w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">License Monitor</span>
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
        <header class="mb-10 relative z-10">
            <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
                <div>
                    <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Global License Monitor</h2>
                    <p class="text-slate-400">View and protect the entire activation network.</p>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET"
                class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 bg-slate-900/50 border border-slate-800 rounded-xl">
                <div class="relative col-span-1 md:col-span-2">
                    <i class="fas fa-search absolute left-4 top-3 text-slate-500"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search keyword..."
                        class="w-full bg-slate-950/50 border border-slate-700/50 rounded-lg pl-12 pr-4 py-2.5 text-sm text-white focus:border-rose-500 focus:outline-none transition-all">
                </div>

                <select name="type"
                    class="bg-slate-950/50 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-300 focus:border-rose-500 focus:outline-none transition-all appearance-none cursor-pointer">
                    <option value="">All Types</option>
                    <option value="standard" <?php if ($filter_type==='standard' )
    echo 'selected' ; ?>>Standard (Direct)
                    </option>
                    <option value="reseller_generated" <?php if ($filter_type==='reseller_generated' )
    echo 'selected' ;
                        ?>>Reseller Generated</option>
                </select>

                <div class="flex space-x-2">
                    <select name="status"
                        class="flex-1 bg-slate-950/50 border border-slate-700/50 rounded-lg px-4 py-2.5 text-sm text-slate-300 focus:border-rose-500 focus:outline-none transition-all appearance-none cursor-pointer">
                        <option value="">All Statuses</option>
                        <option value="active" <?php if ($filter_status==='active' )
    echo 'selected' ; ?>>Active</option>
                        <option value="pending_activation" <?php if ($filter_status==='pending_activation' )

                               echo 'selected' ; ?>>Pending</option>
                        <option value="revoked" <?php if ($filter_status==='revoked' )
    echo 'selected' ; ?>>Revoked
                        </option>
                    </select>
                    <button type="submit"
                        class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2.5 rounded-lg transition-colors border border-slate-700 text-sm font-bold shadow-sm">Filter</button>
                    <?php if (!empty($search) || !empty($filter_type) || !empty($filter_status)): ?>
                    <a href="license-monitor.php"
                        class="bg-slate-900 hover:bg-rose-900/30 text-rose-500 px-4 py-2.5 rounded-lg transition-colors border border-rose-500/20 text-sm font-bold flex items-center"
                        title="Clear Filters"><i class="fas fa-times"></i></a>
                    <?php
endif; ?>
                </div>
            </form>
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
                        class="bg-slate-900/80 text-slate-300 uppercase text-[10px] font-black tracking-widest border-b border-slate-700/50">
                        <tr>
                            <th class="px-6 py-5">Key / Email</th>
                            <th class="px-6 py-5">Asset Type</th>
                            <th class="px-6 py-5">Originating Domain</th>
                            <th class="px-6 py-5 text-center">Status</th>
                            <th class="px-6 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach ($licenses as $l):
    $domain = parse_active_domain($l['registered_domains']);
?>
                        <tr class="hover:bg-slate-800/40 transition-colors group">
                            <td class="px-6 py-4">
                                <span class="block font-mono text-xs text-blue-400 mb-1">
                                    <?php echo htmlspecialchars($l['license_key']); ?>
                                </span>
                                <span class="text-[11px] text-slate-500 flex items-center"><i
                                        class="fas fa-envelope mr-2 opacity-50 text-[10px]"></i>
                                    <?php echo htmlspecialchars($l['client_email']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($l['type'] === 'reseller_generated'): ?>
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-sm text-[9px] uppercase font-black border bg-indigo-500/10 text-indigo-400 border-indigo-500/20"><i
                                        class="fas fa-handshake mr-1.5 opacity-50"></i> Reseller</span>
                                <?php
    else: ?>
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-sm text-[9px] uppercase font-black border bg-slate-800 text-slate-400 border-slate-700"><i
                                        class="fas fa-store mr-1.5 opacity-50"></i> Direct Sale</span>
                                <?php
    endif; ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-rose-400 tracking-tight">
                                <?php echo $domain ? $domain : '<span class="text-slate-600 uppercase text-[10px] tracking-widest font-black">---</span>'; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php
    $s = strtolower($l['status']);
    if ($s === 'active') {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-emerald-500/10 text-emerald-400 border-emerald-500/20 shadow-[0_0_10px_rgba(16,185,129,0.1)]">Active</span>';
    }
    elseif ($s === 'pending_activation') {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-amber-500/10 text-amber-500 border-amber-500/20">Pending</span>';
    }
    elseif ($s === 'revoked' || $s === 'banned') {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-red-500/10 text-red-500 border-red-500/20">Revoked</span>';
    }
    else {
        echo '<span class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-slate-800 text-slate-400 border-slate-700">' . $s . '</span>';
    }
?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($s !== 'revoked' && $s !== 'banned'): ?>
                                <form method="POST" class="inline"
                                    onsubmit="return confirm('CRITICAL: Revoking this license will immediately shut down the associated BioScript installation. This action is permanent. Proceed?');">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="license_id" value="<?php echo $l['id']; ?>">
                                    <button type="submit"
                                        class="p-2 text-slate-500 hover:text-red-500 transition-colors"
                                        title="Revoke Access">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </form>
                                <?php
    else: ?>
                                <span class="text-slate-600 text-xs italic">Terminated</span>
                                <?php
    endif; ?>
                            </td>
                        </tr>
                        <?php
endforeach; ?>

                        <?php if (empty($licenses)): ?>
                        <tr>
                            <td colspan="5"
                                class="text-center py-12 text-slate-500 text-sm tracking-widest uppercase font-bold"><i
                                    class="fas fa-ghost text-2xl mb-3 block opacity-20"></i> No Licenses Found</td>
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
    if (!empty($filter_type))
        $qs .= '&type=' . urlencode($filter_type);
    if (!empty($filter_status))
        $qs .= '&status=' . urlencode($filter_status);
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