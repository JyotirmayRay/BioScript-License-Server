<?php
require_once __DIR__ . '/auth.php';

$success = null;
$error = null;

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    $action = $_POST['action'];

    if ($action === 'suspend' || $action === 'activate' || $action === 'restore' || $action === 'rotate_key') {
        $id = (int)($_POST['reseller_id'] ?? 0);
        if ($id > 0) {
            if ($action === 'rotate_key') {
                // Generate new key and reset verification
                $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $segments = [];
                for ($i = 0; $i < 3; $i++) {
                    $s = '';
                    for ($j = 0; $j < 4; $j++)
                        $s .= $chars[random_int(0, strlen($chars) - 1)];
                    $segments[] = $s;
                }
                $new_key = 'RES-' . implode('-', $segments);
                $pdo->prepare("UPDATE resellers SET is_verified = 0, license_key = ? WHERE id = ?")->execute([$new_key, $id]);
                $success = "Reseller key rotated and account reset to unverified. New Key: $new_key";
            }
            else {
                $new_status = ($action === 'suspend') ? 'suspended' : 'active';
                $pdo->prepare("UPDATE resellers SET status = ? WHERE id = ?")->execute([$new_status, $id]);
                $success = "Reseller status updated successfully.";
            }
        }
    }
    elseif ($action === 'create') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        }
        elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        }
        else {
            // Check for existing active or suspended
            $stmt = $pdo->prepare("SELECT id, status FROM resellers WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            if ($existing) {
                if ($existing['status'] === 'deleted') {
                    // Smart Creation: Re-activate soft-deleted account
                    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $segments = [];
                    for ($i = 0; $i < 3; $i++) {
                        $s = '';
                        for ($j = 0; $j < 4; $j++)
                            $s .= $chars[random_int(0, strlen($chars) - 1)];
                        $segments[] = $s;
                    }
                    $reseller_key = 'RES-' . implode('-', $segments);
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $pdo->prepare("UPDATE resellers SET status = 'active', password_hash = ?, license_key = ?, is_verified = 0 WHERE id = ?")
                        ->execute([$hash, $reseller_key, $existing['id']]);
                    $success = "Existing account for $email was restored and updated with a new key: $reseller_key";
                }
                else {
                    $error = "A reseller with this email already exists.";
                }
            }
            else {
                // Generate RES-XXXX-XXXX-XXXX key
                $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $segments = [];
                for ($i = 0; $i < 3; $i++) {
                    $s = '';
                    for ($j = 0; $j < 4; $j++)
                        $s .= $chars[random_int(0, strlen($chars) - 1)];
                    $segments[] = $s;
                }
                $reseller_key = 'RES-' . implode('-', $segments);
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $pdo->prepare("INSERT INTO resellers (email, password_hash, license_key, status) VALUES (?, ?, ?, 'active')")
                    ->execute([$email, $hash, $reseller_key]);
                $success = "Reseller created: $email | Key: $reseller_key";
            }
        }
    }
    elseif ($action === 'edit') {
        $id = (int)($_POST['reseller_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');

        if ($id > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo->prepare("UPDATE resellers SET email = ? WHERE id = ?")->execute([$email, $id]);
            if (!empty($new_password) && strlen($new_password) >= 8) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE resellers SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
            }
            $success = "Reseller updated successfully.";
        }
        else {
            $error = "Invalid reseller ID or email.";
        }
    }
    elseif ($action === 'delete') {
        $id = (int)($_POST['reseller_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE resellers SET status = 'deleted' WHERE id = ?")->execute([$id]);
            $success = "Reseller deleted (soft). Login blocked.";
        }
    }
    elseif ($action === 'hard_delete') {
        $id = (int)($_POST['reseller_id'] ?? 0);
        if ($id > 0) {
            // Safety: Only hard delete if already soft-deleted
            $stmt = $pdo->prepare("SELECT status FROM resellers WHERE id = ?");
            $stmt->execute([$id]);
            $r = $stmt->fetch();
            if ($r && $r['status'] === 'deleted') {
                $pdo->prepare("DELETE FROM resellers WHERE id = ?")->execute([$id]);
                $success = "Reseller permanently removed from database.";
            }
            else {
                $error = "Hard delete only allowed for archived/soft-deleted accounts.";
            }
        }
    }
}

// Fetch Resellers with aggregate metrics including deleted_by_reseller count
$query = "
    SELECT r.*,
           (SELECT COUNT(*) FROM licenses WHERE reseller_id = r.id) as total_licenses_generated,
           (SELECT COUNT(*) FROM licenses WHERE reseller_id = r.id AND status = 'active') as active_licenses,
           (SELECT COUNT(*) FROM licenses WHERE reseller_id = r.id AND status = 'deleted_by_reseller') as deleted_by_reseller_count
    FROM resellers r
    WHERE r.status != 'deleted'
    ORDER BY r.created_at DESC
";
$stmt = $pdo->query($query);
$resellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Deleted Resellers for Archival list
$archived_query = "
    SELECT r.*,
           (SELECT COUNT(*) FROM licenses WHERE reseller_id = r.id) as total_licenses_generated
    FROM resellers r
    WHERE r.status = 'deleted'
    ORDER BY r.created_at DESC
";
$archived_resellers = $pdo->query($archived_query)->fetchAll(PDO::FETCH_ASSOC);


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
            <button onclick="document.getElementById('createModal').classList.remove('hidden')"
                class="bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white font-bold py-3 px-6 rounded-xl transition-all flex items-center space-x-2 text-xs uppercase tracking-widest border border-rose-500/30">
                <i class="fas fa-plus"></i>
                <span>Create Reseller</span>
            </button>
        </header>

        <?php if ($success): ?>
        <div
            class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 flex items-center relative z-10">
            <i class="fas fa-check-circle mr-3 text-lg"></i>
            <span>
                <?php echo htmlspecialchars($success); ?>
            </span>
        </div>
        <?php
endif; ?>
        <?php if ($error): ?>
        <div
            class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 flex items-center relative z-10">
            <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
            <span>
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
                                <?php if (!$r['is_verified']): ?>
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border bg-amber-500/10 text-amber-400 border-amber-500/20 ml-1"
                                    title="Account not yet paired with Reseller Key">Unpaired</span>
                                <?php
        endif; ?>
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

                                    <button
                                        onclick="openEditModal(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['email'], ENT_QUOTES); ?>')"
                                        class="p-2 text-slate-400 hover:text-amber-400 transition-colors"
                                        title="Edit Reseller">
                                        <i class="fas fa-edit"></i>
                                    </button>

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

                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Force re-verification? This will reset their pairing and generate a NEW Reseller Key.');">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="reseller_id" value="<?php echo $r['id']; ?>">
                                        <input type="hidden" name="action" value="rotate_key">
                                        <button type="submit"
                                            class="p-2 text-slate-400 hover:text-rose-400 transition-colors"
                                            title="Force Re-verification (Rotate Key)">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>

                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Delete this reseller? Their login will be blocked.');">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="reseller_id" value="<?php echo $r['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit"
                                            class="p-2 text-slate-400 hover:text-red-500 transition-colors"
                                            title="Delete Reseller">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
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

        <?php if (!empty($archived_resellers)): ?>
        <!-- Archived Table -->
        <div class="mt-16 mb-6">
            <h3 class="text-xl font-bold text-slate-500 mb-2 border-b border-slate-800 pb-2 flex items-center">
                <i class="fas fa-archive mr-2 opacity-50"></i> Archived Resellers
            </h3>
            <p class="text-xs text-slate-600 mb-6 uppercase tracking-widest font-black">Accounts marked as deleted.
                Restore them to allow login.</p>
        </div>

        <div
            class="ent-glass rounded-2xl shadow-xl overflow-hidden relative z-10 opacity-70 hover:opacity-100 transition-opacity">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-500">
                    <thead
                        class="bg-slate-900/40 text-slate-500 uppercase text-[10px] font-bold tracking-widest border-b border-slate-800/50">
                        <tr>
                            <th class="px-6 py-4">Reseller Email</th>
                            <th class="px-6 py-4">Generated</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/30">
                        <?php foreach ($archived_resellers as $ar): ?>
                        <tr class="hover:bg-slate-800/20 transition-colors">
                            <td class="px-6 py-4 font-medium">
                                <?php echo htmlspecialchars($ar['email']); ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs">
                                <?php echo number_format($ar['total_licenses_generated']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[9px] uppercase font-black border border-slate-700 text-slate-500">Deleted</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" class="inline"
                                    onsubmit="return confirm('Restore this reseller account?');">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="reseller_id" value="<?php echo $ar['id']; ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <button type="submit"
                                        class="bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-500 border border-emerald-500/20 rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-widest transition-all">
                                        <i class="fas fa-undo mr-1"></i> Restore
                                    </button>
                                </form>

                                <form method="POST" class="inline"
                                    onsubmit="return confirm('PERMANENTLY DELETE THIS RESELLER?\n\nThis action is IRREVERSIBLE. All data for this account will be purged.');">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="reseller_id" value="<?php echo $ar['id']; ?>">
                                    <input type="hidden" name="action" value="hard_delete">
                                    <button type="submit"
                                        class="bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/20 rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-widest transition-all ml-1">
                                        <i class="fas fa-fire-alt mr-1"></i> PURGE
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php
    endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
endif; ?>
    </main>

    <!-- Create Reseller Modal -->
    <div id="createModal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl p-8 w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-white mb-6"><i class="fas fa-user-plus text-rose-500 mr-2"></i>Create
                Reseller</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email</label>
                    <input type="email" name="email" required
                        class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-4 py-3 focus:border-rose-500 focus:outline-none text-sm">
                </div>
                <div class="mb-6">
                    <label
                        class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Password</label>
                    <input type="password" name="password" required minlength="8"
                        class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-4 py-3 focus:border-rose-500 focus:outline-none text-sm"
                        placeholder="Min 8 characters">
                </div>
                <div class="flex space-x-3">
                    <button type="submit"
                        class="flex-1 bg-gradient-to-r from-rose-600 to-rose-700 text-white font-bold py-3 rounded-lg text-sm uppercase tracking-widest">Create</button>
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                        class="flex-1 bg-slate-800 text-slate-400 font-bold py-3 rounded-lg text-sm uppercase tracking-widest border border-slate-700 hover:text-white">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Reseller Modal -->
    <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl p-8 w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-white mb-6"><i class="fas fa-edit text-amber-500 mr-2"></i>Edit Reseller
            </h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="reseller_id" id="editId">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email</label>
                    <input type="email" name="email" id="editEmail" required
                        class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-4 py-3 focus:border-amber-500 focus:outline-none text-sm">
                </div>
                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">New Password
                        <span class="text-slate-600">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" minlength="8"
                        class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-4 py-3 focus:border-amber-500 focus:outline-none text-sm">
                </div>
                <div class="flex space-x-3">
                    <button type="submit"
                        class="flex-1 bg-gradient-to-r from-amber-600 to-amber-700 text-white font-bold py-3 rounded-lg text-sm uppercase tracking-widest">Update</button>
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="flex-1 bg-slate-800 text-slate-400 font-bold py-3 rounded-lg text-sm uppercase tracking-widest border border-slate-700 hover:text-white">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, email) {
            document.getElementById('editId').value = id;
            document.getElementById('editEmail').value = email;
            document.getElementById('editModal').classLiemoden');
        }
    </script>
</body>

</html>