<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

// Auth Check
if (!isset($_SESSION['ls_admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Handle SMTP & Lemon Squeezy (Existing)
    if (isset($_POST['save_settings']) || isset($_POST['test_smtp'])) {
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = trim($_POST['smtp_pass'] ?? '');
        $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
        $smtp_from_name = trim($_POST['smtp_from_name'] ?? '');
        $lemon_secret = trim($_POST['lemon_secret'] ?? '');

        try {
            $fields = [
                'smtp_host' => $smtp_host, 'smtp_port' => $smtp_port,
                'smtp_user' => $smtp_user, 'smtp_from_email' => $smtp_from_email,
                'smtp_from_name' => $smtp_from_name, 'lemon_secret' => $lemon_secret
            ];
            $setClause = [];
            $params = [];
            foreach ($fields as $key => $value) {
                $setClause[] = "$key = :$key";
                $params[":$key"] = $value;
            }
            if (!empty($smtp_pass)) {
                $setClause[] = "smtp_pass = :smtp_pass";
                $params[':smtp_pass'] = $smtp_pass;
            }
            $stmt = $pdo->prepare("UPDATE settings SET " . implode(', ', $setClause) . " WHERE id = 1");
            $stmt->execute($params);

            if (isset($_POST['test_smtp'])) {
                $test_email = trim($_POST['test_email'] ?? '');
                if (empty($test_email)) {
                    $message = "Please enter an email address to send the test email to.";
                    $messageType = 'error';
                }
                else {
                    try {
                        require_once __DIR__ . '/includes/EmailService.php';
                        EmailService::sendTestEmail($pdo, $test_email);
                        $message = "Settings saved & Test Email successfully sent!";
                        $messageType = 'success';
                    }
                    catch (Exception $e) {
                        $message = "Mail failed: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            }
            else {
                header("Location: settings.php?success=1");
                exit;
            }
        }
        catch (PDOException $e) {
            $message = "Database Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 2. Handle WooCommerce License Engine Settings
    if (isset($_POST['save_engine_settings'])) {
        try {
            $updates = [
                'webhook_secret' => trim($_POST['webhook_secret'] ?? ''),
                'trigger_status' => trim($_POST['trigger_status'] ?? 'completed'),
                'license_prefix' => trim($_POST['license_prefix'] ?? 'BIO'),
                'auto_license' => isset($_POST['auto_license']) ? "1" : "0",
                'woo_api_url' => rtrim(trim($_POST['woo_api_url'] ?? ''), '/'),
                'woo_consumer_key' => trim($_POST['woo_consumer_key'] ?? ''),
                'woo_consumer_secret' => trim($_POST['woo_consumer_secret'] ?? '')
            ];
            $stmt = $pdo->prepare("UPDATE system_settings SET value = ? WHERE key = ?");
            foreach ($updates as $key => $val) {
                $stmt->execute([$val, $key]);
            }
            header("Location: settings.php?success=1");
            exit;
        }
        catch (PDOException $e) {
            $message = "Error saving engine settings: " . $e->getMessage();
            $messageType = 'error';
        }
    }

    // PHASE 3 — VERIFY SAVE HANDLER (NEW)
    if (isset($_POST['save_api'])) {
        // PHASE 5 — ADD TEMP DEBUG
        file_put_contents(__DIR__ . '/logs/api_debug.log', "POST data received for save_api: " . print_r($_POST, true), FILE_APPEND);

        try {
            $stmt = $pdo->prepare("
                UPDATE settings
                SET woo_store_url = ?,
                    woo_consumer_key = ?,
                    woo_consumer_secret = ?
                WHERE id = 1
            ");

            $stmt->execute([
                trim($_POST['woo_store_url'] ?? ''),
                trim($_POST['woo_consumer_key'] ?? ''),
                trim($_POST['woo_consumer_secret'] ?? '')
            ]);

            header("Location: settings.php?success=1&saved=1");
            exit;
        }
        catch (PDOException $e) {
            $message = "Audit Save Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 3. Handle Product Registry Actions
    $action = $_POST['action'] ?? '';
    if ($action === 'add_product') {
        $woo_id = trim($_POST['woo_product_id'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $license_type = trim($_POST['license_type'] ?? 'standard');

        if (!is_numeric($woo_id)) {
            header("Location: settings.php?error=invalid_id");
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO products_registry (woo_product_id, sku, license_type, active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$woo_id, $sku, $license_type]);
            header("Location: settings.php?success=product_added");
            exit;
        }
        catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM products_registry WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: settings.php?success=deleted");
            exit;
        }
        catch (PDOException $e) {
            $message = "Delete Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Success Notification Handling
if (isset($_GET['success'])) {
    $message = "Settings updated successfully.";
    $messageType = 'success';
}

// Fetch SMTP & Core Settings
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['smtp_host' => '', 'smtp_port' => 587, 'smtp_user' => '', 'smtp_pass' => '', 'smtp_from_email' => '', 'smtp_from_name' => '', 'lemon_secret' => ''];

// Fetch System Settings (K-V)
$stmt = $pdo->query("SELECT key, value FROM system_settings");
$sys_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch Products Registry
$stmt = $pdo->query("SELECT * FROM products_registry ORDER BY id DESC");
$product_registry = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - License Authority</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: { 850: '#1e293b', 950: '#0f172a' },
                        primary: { 500: '#6366f1', 600: '#4f46e5' }
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .ui-enterprise {
            font-family: 'Inter', sans-serif;
            --ent-primary: #2563eb;
            --ent-primary-dark: #1d4ed8;
            --ent-surface: #0f172a;
            --ent-card: #1e293b;
            --ent-border: #334155;
            --ent-border-strong: #475569;
            --ent-text: #f8fafc;
            --ent-muted: #94a3b8;
            --ent-accent: #3b82f6;
            --ent-success: #10b981;
            --ent-warning: #f59e0b;
            --ent-danger: #ef4444;
        }

        .ui-enterprise .ent-glass {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--ent-border-strong);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .ui-enterprise .ent-btn-primary {
            background: var(--ent-primary);
            color: white;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
        }

        .ui-enterprise .ent-btn-primary:hover {
            background: var(--ent-primary-dark);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }

        .ui-enterprise .ent-btn-primary:active {
            transform: scale(0.98);
        }

        /* Custom Scrollbar */
        .ui-enterprise ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .ui-enterprise ::-webkit-scrollbar-track {
            background: var(--ent-surface);
        }

        .ui-enterprise ::-webkit-scrollbar-thumb {
            background: var(--ent-border-strong);
            border-radius: 3px;
        }

        .ui-enterprise ::-webkit-scrollbar-thumb:hover {
            background: var(--ent-muted);
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>

<body class="ui-enterprise bg-slate-950 text-slate-100 h-screen flex overflow-hidden font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-950 border-r border-slate-800 flex flex-col hidden md:flex shrink-0 z-20">
        <div class="p-6 border-b border-slate-800 flex items-center space-x-3">
            <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center border border-blue-500 shadow-sm">
                <i class="fas fa-layer-group text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-base font-black text-white tracking-widest uppercase leading-tight">Guard<span
                        class="text-blue-500">Node</span></h1>
                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Digital Infrastructure</p>
            </div>
        </div>

        <nav class="flex-1 py-6 space-y-1">
            <p class="px-6 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Control Plane</p>

            <a href="dashboard.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-satellite-dish w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">License Overview</span>
            </a>

            <a href="orders.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-shopping-cart w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">WooCommerce Orders</span>
            </a>

            <a href="settings.php"
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-blue-400 border-l-2 border-blue-500 transition-all group">
                <i class="fas fa-sliders-h w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">Global Settings</span>
            </a>

            <div class="mt-8">
                <p class="px-6 text-[10px] font-black text-rose-500 uppercase tracking-widest mb-3">Super Admin</p>

                <a href="super_admin/resellers.php"
                    class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-rose-500 transition-all group">
                    <i class="fas fa-users-cog w-5 text-center group-hover:text-rose-400 transition-colors"></i>
                    <span
                        class="font-semibold tracking-wide text-sm group-hover:text-rose-100 transition-colors">Resellers</span>
                </a>

                <a href="super_admin/reseller-customers.php"
                    class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-rose-500 transition-all group">
                    <i class="fas fa-user-friends w-5 text-center group-hover:text-rose-400 transition-colors"></i>
                    <span
                        class="font-semibold tracking-wide text-sm group-hover:text-rose-100 transition-colors">Reseller
                        Customers</span>
                </a>

                <a href="super_admin/license-monitor.php"
                    class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-rose-500 transition-all group">
                    <i class="fas fa-shield-alt w-5 text-center group-hover:text-rose-400 transition-colors"></i>
                    <span
                        class="font-semibold tracking-wide text-sm group-hover:text-rose-100 transition-colors">License
                        Monitor</span>
                </a>

                <a href="super_admin/domain-blacklist.php"
                    class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-rose-500 transition-all group">
                    <i class="fas fa-ban w-5 text-center group-hover:text-rose-400 transition-colors"></i>
                    <span class="font-semibold tracking-wide text-sm group-hover:text-rose-100 transition-colors">Domain
                        Blacklist</span>
                </a>
            </div>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <a href="index.php?logout=true"
                class="flex items-center justify-center space-x-2 w-full px-4 py-2 hover:bg-slate-900 text-slate-500 hover:text-red-400 border border-transparent hover:border-slate-800 rounded transition-all text-xs font-bold uppercase tracking-wider">
                <i class="fas fa-power-off"></i>
                <span>Terminate Session</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-8 lg:p-12 relative">
        <!-- Background Glow -->
        <div
            class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-primary-900/20 to-transparent pointer-events-none">
        </div>

        <header class="mb-10 relative z-10">
            <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">System Configuration</h2>
            <p class="text-slate-400">Manage SMTP servers and Webhook integrations.</p>
        </header>

        <?php if ($message): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: '<?php echo $messageType; ?>',
                    title: '<?php echo $messageType === 'success' ? 'Success' : 'Error'; ?>',
                    text: '<?php echo addslashes($message); ?>',
                    background: '#1e293b',
                    color: '#fff',
                    confirmButtonColor: '#3b82f6'
                })
            });
        </script>
        <?php
endif; ?>

        <div class="max-w-4xl relative z-10 space-y-8 pb-12">

            <!-- SMTP Settings Section -->
            <form method="POST" class="glass-panel p-8 rounded-2xl border border-slate-800/50">
                <h3 class="text-xl font-bold text-white mb-6 flex items-center justify-between">
                    <span class="flex items-center"><i class="fas fa-envelope text-primary-500 mr-3"></i> SMTP
                        Configuration</span>
                    <span
                        class="text-[10px] bg-primary-500/10 text-primary-400 px-2 py-1 rounded border border-primary-500/20 uppercase tracking-widest">Global
                        Status: Active</span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">SMTP
                            Host</label>
                        <input type="text" name="smtp_host"
                            value="<?php echo htmlspecialchars($settings['smtp_host']); ?>"
                            placeholder="smtp.example.com"
                            class="w-full bg-slate-950/50 border border-slate-700/50 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500/50 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">SMTP
                            Port</label>
                        <input type="number" name="smtp_port"
                            value="<?php echo htmlspecialchars((string)$settings['smtp_port']); ?>" placeholder="587"
                            class="w-full bg-slate-950/50 border border-slate-700/50 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">SMTP
                            Username</label>
                        <input type="text" name="smtp_user"
                            value="<?php echo htmlspecialchars($settings['smtp_user']); ?>"
                            placeholder="user@example.com"
                            class="w-full bg-slate-950/50 border border-slate-700/50 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:outline-none transition-all">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">SMTP
                            Password</label>
                        <input type="password" name="smtp_pass" placeholder="••••••••"
                            class="w-full bg-slate-950/50 border border-slate-700/50 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:outline-none transition-all">
                        <p class="text-[10px] text-slate-500 mt-2 italic px-1">Leave blank to keep encrypted password
                            safely stored in DB.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">From Email
                            Address</label>
                        <input type="email" name="smtp_from_email"
                            value="<?php echo htmlspecialchars($settings['smtp_from_email']); ?>"
                            placeholder="noreply@domain.com"
                            class="w-full bg-slate-950/50 border border-slate-700/50 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Display
                            Name</label>
                        <input type="text" name="smtp_from_name"
                            value="<?php echo htmlspecialchars($settings['smtp_from_name']); ?>"
                            placeholder="BioScript Security"
                            class="w-full bg-slate-950/50 border border-slate-700/50 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:outline-none transition-all">
                    </div>
                </div>

                <div
                    class="mt-8 pt-8 border-t border-slate-800/50 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="w-full max-sm:max-w-none max-w-sm">
                        <label
                            class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Test
                            Connection</label>
                        <div class="flex">
                            <input type="email" name="test_email" placeholder="test@email.com"
                                class="flex-1 bg-slate-950/50 border border-slate-700/50 border-r-0 rounded-l-xl px-4 py-2 text-sm text-white focus:border-blue-500 focus:outline-none transition-all">
                            <button type="submit" name="test_smtp"
                                class="bg-slate-800/80 hover:bg-slate-700 text-blue-400 font-bold px-4 border border-slate-700/50 border-l-0 rounded-r-xl transition-all text-xs">
                                <i class="fas fa-paper-plane mr-2"></i> Send Test
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="save_settings"
                        class="bg-primary-600 hover:bg-primary-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-primary-600/20 transition-all flex items-center space-x-2">
                        <i class="fas fa-save shadow-inner"></i> <span>Save SMTP Settings</span>
                    </button>
                </div>
            </form>

            <!-- WooCommerce License Engine Settings -->
            <form method="POST" class="glass-panel p-8 rounded-2xl space-y-6">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-engine-warning text-blue-500 mr-3"></i> WooCommerce License Engine
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Webhook
                            Secret</label>
                        <input type="text" name="webhook_secret"
                            value="<?php echo htmlspecialchars($sys_settings['webhook_secret'] ?? ''); ?>"
                            class="w-full bg-slate-950/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:outline-none transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Trigger
                            Status</label>
                        <select name="trigger_status"
                            class="w-full bg-slate-950/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:outline-none transition-all appearance-none cursor-pointer">
                            <option value="completed" <?php echo ($sys_settings['trigger_status'] ?? '' )==='completed'
                                ? 'selected' : '' ; ?>>completed</option>
                            <option value="processing" <?php echo ($sys_settings['trigger_status'] ?? ''
                                )==='processing' ? 'selected' : '' ; ?>>processing</option>
                            <option value="refunded" <?php echo ($sys_settings['trigger_status'] ?? '' )==='refunded'
                                ? 'selected' : '' ; ?>>refunded</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">License
                            Prefix</label>
                        <input type="text" name="license_prefix"
                            value="<?php echo htmlspecialchars($sys_settings['license_prefix'] ?? 'BIO'); ?>"
                            class="w-full bg-slate-950/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-primary-500 focus:outline-none transition-all">
                    </div>

                    <div class="flex items-end pb-3">
                        <label class="flex items-center space-x-3 cursor-pointer group">
                            <div class="relative">
                                <input type="checkbox" name="auto_license" value="1" <?php echo
                                    ($sys_settings['auto_license'] ?? '0' )=='1' ? 'checked' : '' ; ?> class="sr-only
                                peer">
                                <div
                                    class="w-10 h-6 bg-slate-800 rounded-full peer peer-checked:bg-primary-600 transition-all">
                                </div>
                                <div
                                    class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-4">
                                </div>
                            </div>
                            <span
                                class="text-sm font-semibold text-slate-300 group-hover:text-white transition-colors">Auto-License
                                Enabled</span>
                        </label>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" name="save_engine_settings"
                        class="ent-btn-primary px-6 py-2 rounded-lg font-bold text-sm">
                        <i class="fas fa-save mr-2"></i> Save Engine Settings
                    </button>
                </div>
            </form>

            <!-- WooCommerce REST API Interface -->
            <form method="POST" class="glass-panel p-8 rounded-2xl space-y-6">
                <!-- PHASE 2 — VERIFY FORM STRUCTURE -->
                <input type="hidden" name="save_api" value="1">

                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-network-wired text-emerald-500 mr-3"></i> WooCommerce REST API Interface
                </h3>

                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Store API
                            URL</label>
                        <!-- PHASE 4 — VERIFY DATA LOAD -->
                        <input type="url" name="woo_store_url"
                            value="<?php echo htmlspecialchars($settings['woo_store_url'] ?? ''); ?>"
                            placeholder="https://yourstore.com"
                            class="w-full bg-slate-950/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        <p class="text-[10px] text-slate-500 mt-2 italic px-1">Base URL of your WooCommerce store (e.g.,
                            https://example.com)</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Consumer
                                Key</label>
                            <input type="text" name="woo_consumer_key"
                                value="<?php echo htmlspecialchars($settings['woo_consumer_key'] ?? ''); ?>"
                                placeholder="ck_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                                class="w-full bg-slate-950/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Consumer
                                Secret</label>
                            <input type="password" name="woo_consumer_secret"
                                value="<?php echo htmlspecialchars($settings['woo_consumer_secret'] ?? ''); ?>"
                                placeholder="cs_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                                class="w-full bg-slate-950/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-emerald-500 focus:outline-none transition-all font-mono">
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit"
                        class="bg-slate-800 hover:bg-slate-700 text-emerald-500 font-bold px-6 py-2 rounded-lg text-sm border border-slate-700">
                        <i class="fas fa-save mr-2"></i> Save API Credentials
                    </button>
                </div>
            </form>

            <!-- Lemon Squeezy Integration Card (Existing) -->
            <form method="POST" class="glass-panel p-8 rounded-2xl space-y-6">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-lemon text-yellow-400 mr-3"></i> Lemon Squeezy Integration
                </h3>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Webhook
                        Secret</label>
                    <div class="relative">
                        <input type="password" name="lemon_secret"
                            value="<?php echo htmlspecialchars($settings['lemon_secret']); ?>"
                            class="w-full bg-slate-950/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-yellow-400 focus:outline-none transition-all font-mono">
                        <i class="fas fa-lock absolute right-4 top-4 text-slate-500"></i>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" name="save_settings"
                        class="bg-slate-800 hover:bg-slate-700 text-yellow-500 font-bold px-6 py-2 rounded-lg text-sm border border-slate-700">
                        <i class="fas fa-save mr-2"></i> Save Lemon Secret
                    </button>
                </div>
            </form>

            <!-- Product Registry Management -->
            <div class="glass-panel p-8 rounded-2xl border border-slate-800/50 overflow-hidden relative">
                <div class="absolute top-0 right-0 w-32 h-32 bg-green-500/5 rounded-full -mr-16 -mt-16 blur-2xl"></div>

                <h3 class="text-xl font-bold text-white mb-6 flex items-center justify-between">
                    <span class="flex items-center"><i class="fas fa-list-check text-green-500 mr-3"></i> Authorized
                        Registry</span>
                    <span class="text-[10px] text-slate-500 italic">Total whitelisted products:
                        <?php echo count($product_registry); ?>
                    </span>
                </h3>

                <div class="overflow-x-auto mb-8 border border-slate-800/50 rounded-xl bg-slate-950/20">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-900/60 transition-colors">
                                <th
                                    class="p-4 text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-800/50">
                                    Woo ID</th>
                                <th
                                    class="p-4 text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-800/50">
                                    SKU Identifier</th>
                                <th
                                    class="p-4 text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-800/50">
                                    License Level</th>
                                <th
                                    class="p-4 text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-800/50 text-center">
                                    Status</th>
                                <th
                                    class="p-4 text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-800/50 text-right">
                                    Protection</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/30">
                            <?php foreach ($product_registry as $prod): ?>
                            <tr class="hover:bg-slate-900/40 transition-all group">
                                <td class="p-4 text-sm font-mono text-white tracking-tighter">#
                                    <?php echo htmlspecialchars($prod['woo_product_id']); ?>
                                </td>
                                <td class="p-4 text-sm text-slate-300">
                                    <?php echo htmlspecialchars($prod['sku']); ?>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="bg-slate-800/50 text-slate-400 text-[9px] font-black uppercase px-2 py-1 rounded-md border border-slate-700/50 tracking-tighter">
                                        <?php echo htmlspecialchars($prod['license_type']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span
                                        class="px-2 py-0.5 text-[9px] font-black uppercase rounded <?php echo $prod['active'] ? 'bg-green-500/10 text-green-500 border border-green-500/20' : 'bg-red-500/10 text-red-500 border border-red-500/20'; ?>">
                                        <?php echo $prod['active'] ? 'Active' : 'Halted'; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <form method="POST"
                                        onsubmit="return confirm('Secure Warning: Removing this product will halt all license delivery for this ID. Proceed?');"
                                        class="inline">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                        <button type="submit"
                                            class="text-slate-600 hover:text-red-500 transition-colors p-2 opacity-0 group-hover:opacity-100 transform translate-x-2 group-hover:translate-x-0 transition-all duration-300">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php
endforeach;
if (empty($product_registry)): ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-slate-600 italic text-sm tracking-wide">
                                    <i class="fas fa-shield-blank block text-2xl mb-3 opacity-20"></i>
                                    System Registry is Empty
                                </td>
                            </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>

                <h4 class="text-sm font-black text-slate-500 uppercase tracking-widest mb-4">Add New Product</h4>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="action" value="add_product">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Woo
                            Product ID</label>
                        <input type="text" name="woo_product_id" required placeholder="e.g. 199"
                            class="w-full bg-slate-950/50 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-green-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label
                            class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">SKU</label>
                        <input type="text" name="sku" placeholder="bioscript-standard"
                            class="w-full bg-slate-950/50 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-green-500 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">License
                            Type</label>
                        <input type="text" name="license_type" placeholder="standard"
                            class="w-full bg-slate-950/50 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-green-500 focus:outline-none transition-all">
                    </div>
                    <div class="flex justify-end py-1">
                        <button type="submit"
                            class="bg-slate-800 hover:bg-slate-700 text-green-500 font-bold px-6 py-2 rounded-lg text-sm border border-slate-700 w-full transition-all">
                            <i class="fas fa-plus mr-2"></i> Add Product
                        </button>
                    </div>