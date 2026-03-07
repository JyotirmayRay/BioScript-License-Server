<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

// --- AUTHENTICATION & CSRF SETUP ---

// Check Login
if (!isset($_SESSION['ls_admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF Token Validation Failed');
    }
}

// --- ACTIONS ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    if (isset($_POST['action']) && isset($_POST['order_id'])) {
        $order_id = $_POST['order_id'];
        $action = $_POST['action'];
        
        // Fetch order and raw_payload
        $stmt = $pdo->prepare("SELECT o.*, l.raw_payload FROM orders o LEFT JOIN order_logs l ON o.woo_order_id = l.woo_order_id WHERE o.woo_order_id = ? ORDER BY l.id DESC LIMIT 1");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            if ($action === 'revoke') {
                $license = $order['license_key'];
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', updated_at = CURRENT_TIMESTAMP WHERE woo_order_id = ?");
                $stmt->execute([$order_id]);
                if ($license) {
                    $stmt = $pdo->prepare("UPDATE licenses SET status = 'revoked' WHERE license_key = ?");
                    $stmt->execute([$license]);
                }
                $pdo->commit();
                $success = "Order $order_id refunded and license revoked.";
            }
            
            if ($action === 'resend') {
                $email = $order['customer_email'];
                $license = $order['license_key'];
                
                if ($license) {
                    try {
                        require_once __DIR__ . '/includes/EmailService.php';
                        EmailService::sendLicense($pdo, $order_id, $license, $email);
                        $success = "License resent to $email successfully.";
                        @file_put_contents(__DIR__ . '/../logs/email_success.log', "[" . date('Y-m-d H:i:s') . "] Resend: Email sent for order $order_id to $email\n", FILE_APPEND);
                    } catch (Exception $e) {
                        $error = "Resend failed: " . $e->getMessage();
                        @file_put_contents(__DIR__ . '/../logs/email_error.log', "[" . date('Y-m-d H:i:s') . "] Resend Order: $order_id | Error: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                } else {
                    $error = "Could not resend. No license has been generated for this order yet.";
                }
            }

            if ($action === 'retry') {
                // Manually trigger processing logic (simplified replicate of woo-webhook.php)
                if ($order['raw_payload']) {
                    $data = json_decode($order['raw_payload'], true);
                    $line_items = $data['line_items'] ?? [];
                    $valid_product = false;
                    foreach ($line_items as $item) {
                        $p_stmt = $pdo->prepare("SELECT 1 FROM products_registry WHERE woo_product_id = ? AND active = 1");
                        $p_stmt->execute([$item['product_id']]);
                        if ($p_stmt->fetch()) { $valid_product = true; break; }
                    }

                    if ($valid_product) {
                        // Re-run the completion logic
                        $email = $order['customer_email'];
                        // (Replicate generateLicense here for portability as per instructions)
                        $new_license = 'BIO-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4)) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
                        
                        try {
                            $pdo->beginTransaction();
                            // Check if license already exists
                            if (empty($order['license_key'])) {
                                $stmt = $pdo->prepare("INSERT INTO licenses (license_key, client_email, status, created_at) VALUES (?, ?, 'active', CURRENT_TIMESTAMP)");
                                $stmt->execute([$new_license, $email]);
                                
                                $stmt = $pdo->prepare("UPDATE orders SET license_key = ?, status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE woo_order_id = ?");
                                $stmt->execute([$new_license, $order_id]);
                                
                                // Processed check
                                $stmt = $pdo->prepare("INSERT OR IGNORE INTO processed_orders (woo_order_id) VALUES (?)");
                                $stmt->execute([$order_id]);
                                
                                $pdo->commit();
                                $success = "Order $order_id reprocessed successfully. License generated.";
                            } else {
                                $pdo->rollBack();
                                $error = "Order already has a license.";
                            }
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $error = "Critical error during retry: " . $e->getMessage();
                        }
                    } else {
                        $error = "Order does not contain a registered BioScript product.";
                    }
                } else {
                    $error = "No raw payload found for this order to retry.";
                }
            }
            }
    }

    // PHASE 3 — VERIFY PROCESSING HANDLER
    if (isset($_POST['manual_sync'])) {
        $order_id = trim($_POST['order_id'] ?? '');
        
        // PHASE 6 — LOGGING
        file_put_contents(__DIR__ . '/../logs/manual_sync.log', "[" . date('Y-m-d H:i:s') . "] Sync Attempt: $order_id\n", FILE_APPEND);

        if (!empty($order_id) && is_numeric($order_id)) {
            $data = fetchWooOrder($order_id);
            if ($data) {
                // PHASE 5 — PROCESS LOGIC
                // We reuse the sync logic here
                $process_result = processManualOrder($data);
                if ($process_result['success']) {
                    $success = "Manual Sync Successful: " . $process_result['message'];
                } else {
                    $error = "Manual Sync Failed: " . $process_result['message'];
                }
            } else {
                $error = "Failed to fetch order #$order_id from WooCommerce API. Check logs/sync_error.log";
            }
        } else {
            $error = "Invalid Order ID provided.";
        }
    }
}

// PHASE 4 — VERIFY REST CALL
function fetchWooOrder($order_id) {
    global $pdo;

    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch();

    if (!$settings || empty($settings['woo_store_url']) || empty($settings['woo_consumer_key']) || empty($settings['woo_consumer_secret'])) {
        file_put_contents(__DIR__ . '/../logs/sync_error.log', "[" . date('Y-m-d H:i:s') . "] Configuration Issue: Missing API credentials.\n", FILE_APPEND);
        return false;
    }

    $url = rtrim($settings['woo_store_url'], '/') . "/wp-json/wc/v3/orders/" . $order_id;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $settings['woo_consumer_key'] . ":" . $settings['woo_consumer_secret']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        file_put_contents(__DIR__ . '/../logs/sync_error.log', "[" . date('Y-m-d H:i:s') . "] CURL Error: " . curl_error($ch) . "\n", FILE_APPEND);
        curl_close($ch);
        return false;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        file_put_contents(__DIR__ . '/../logs/sync_error.log', "[" . date('Y-m-d H:i:s') . "] API Error (HTTP $http_code): " . $response . "\n", FILE_APPEND);
        return false;
    }

    $data = json_decode($response, true);

    if (!isset($data['id'])) {
        file_put_contents(__DIR__ . '/../logs/sync_error.log', "[" . date('Y-m-d H:i:s') . "] Invalid JSON Response: " . $response . "\n", FILE_APPEND);
        return false;
    }

    return $data;
}

/**
 * Reusable logic for processing a WooCommerce order payload
 */
function processManualOrder($data) {
    global $pdo;
    
    $order_id = (string)$data['id'];
    $status = (string)$data['status'];
    $email = $data['billing']['email'] ?? '';
    $total = $data['total'] ?? '0.00';
    $currency = $data['currency'] ?? 'USD';
    $line_items = $data['line_items'] ?? [];
    $raw_payload = json_encode($data);

    try {
        // 1. Log payload
        $stmt = $pdo->prepare("INSERT INTO order_logs (woo_order_id, status, raw_payload) VALUES (?, ?, ?)");
        $stmt->execute([$order_id, $status, $raw_payload]);

        // 2. Validate product
        $valid_product = false;
        foreach ($line_items as $item) {
            $p_stmt = $pdo->prepare("SELECT 1 FROM products_registry WHERE woo_product_id = ? AND active = 1");
            $p_stmt->execute([(string)$item['product_id']]);
            if ($p_stmt->fetch()) { $valid_product = true; break; }
        }

        if (!$valid_product) {
            return ['success' => false, 'message' => "Order #$order_id does not contain an authorized BioScript product."];
        }

        // 3. Insert/Update orders table
        $stmt = $pdo->prepare("INSERT INTO orders (woo_order_id, customer_email, amount, currency, status, updated_at) 
                               VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                               ON CONFLICT(woo_order_id) DO UPDATE SET 
                               status = excluded.status, updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$order_id, $email, $total, $currency, $status]);

        // 4. Trigger Check
        $sys_stmt = $pdo->query("SELECT value FROM system_settings WHERE key = 'trigger_status'");
        $trigger_status = $sys_stmt->fetchColumn() ?: 'completed';

        if ($status === $trigger_status) {
            // Check if already processed
            $check = $pdo->prepare("SELECT 1 FROM processed_orders WHERE woo_order_id = ?");
            $check->execute([$order_id]);

            if (!$check->fetch()) {
                // Generate license
                $license = 'BIO-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4)) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
                
                $pdo->beginTransaction();
                try {
                    // Insert license
                    $stmt = $pdo->prepare("INSERT INTO licenses (license_key, client_email, status, created_at) VALUES (?, ?, 'active', CURRENT_TIMESTAMP)");
                    $stmt->execute([$license, $email]);

                    // Update order
                    $stmt = $pdo->prepare("UPDATE orders SET license_key = ? WHERE woo_order_id = ?");
                    $stmt->execute([$license, $order_id]);

                    // Mark processed
                    $stmt = $pdo->prepare("INSERT INTO processed_orders (woo_order_id) VALUES (?)");
                    $stmt->execute([$order_id]);

                    $pdo->commit();
                    return ['success' => true, 'message' => "Order #$order_id processed. License generated: $license"];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => "Error during transaction: " . $e->getMessage()];
                }
            } else {
                return ['success' => true, 'message' => "Order #$order_id already fully processed."];
            }
        }

        return ['success' => true, 'message' => "Order #$order_id synced with status: $status"];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Database error: " . $e->getMessage()];
    }
}

// --- PAGINATION & FILTERING ---

$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$where = "1=1";
$params = [];
$valid_filters = ['completed', 'pending', 'failed', 'refunded'];
if (in_array(strtolower($filter), $valid_filters)) {
    $where .= " AND status = :status";
    $params[':status'] = strtolower($filter);
}

// Count total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $where");
$stmt->execute($params);
$total_orders = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total_orders / $limit));

// Fetch rows with Pagination and join logs for payload matching
$stmt = $pdo->prepare("SELECT o.*, 
    (SELECT raw_payload FROM order_logs WHERE woo_order_id = o.woo_order_id ORDER BY id DESC LIMIT 1) as raw_payload 
    FROM orders o WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WooCommerce Orders - License Authority</title>
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

        /* Nav Pills for Filters */
        .ent-pill {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .ent-pill-active {
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border-color: rgba(59, 130, 246, 0.3);
        }

        .ent-pill-inactive {
            color: #94a3b8;
            border-color: #334155;
        }

        .ent-pill-inactive:hover {
            background: #1e293b;
            color: #f8fafc;
        }
    </style>
</head>

<body class="ui-enterprise bg-slate-950 text-slate-100 h-screen flex overflow-hidden font-sans">

    <!-- Sidebar (Existing Layout) -->
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
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-blue-400 border-l-2 border-blue-500 transition-all group">
                <i class="fas fa-shopping-cart w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">WooCommerce Orders</span>
            </a>

            <a href="settings.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-sliders-h w-5 text-center group-hover:text-slate-300 transition-colors"></i>
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

        <!-- Header -->
        <header class="flex justify-between items-end mb-10 relative z-10">
            <div>
                <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Order Logs</h2>
                <p class="text-slate-400">Track and manage inbound WooCommerce webhook executions.</p>
            </div>
            <div class="flex space-x-2">
                <a href="orders.php?filter=all"
                    class="ent-pill <?php echo $filter === 'all' ? 'ent-pill-active' : 'ent-pill-inactive'; ?>">All</a>
                <a href="orders.php?filter=completed"
                    class="ent-pill <?php echo $filter === 'completed' ? 'ent-pill-active' : 'ent-pill-inactive'; ?>">Completed</a>
                <a href="orders.php?filter=pending"
                    class="ent-pill <?php echo $filter === 'pending' ? 'ent-pill-active' : 'ent-pill-inactive'; ?>">Pending</a>
                <a href="orders.php?filter=failed"
                    class="ent-pill <?php echo $filter === 'failed' ? 'ent-pill-active' : 'ent-pill-inactive'; ?>">Failed</a>
                <a href="orders.php?filter=refunded"
                    class="ent-pill <?php echo $filter === 'refunded' ? 'ent-pill-active' : 'ent-pill-inactive'; ?>">Refunded</a>
            </div>
        </header>

        <!-- Manual Order Fetch Section -->
        <div
            class="ent-glass rounded-2xl p-6 mb-8 relative z-10 border border-emerald-500/20 shadow-lg shadow-emerald-500/5">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div class="flex items-center space-x-4">
                    <div
                        class="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 text-emerald-500">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-bold">Manual Woo Order Sync</h3>
                        <p class="text-xs text-slate-500">Fetch and process orders directly from WooCommerce API</p>
                    </div>
                </div>
                <form method="POST" class="flex flex-1 md:flex-none md:w-96">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <!-- PHASE 2 — VERIFY SYNC FORM -->
                    <input type="hidden" name="manual_sync" value="1">
                    <div class="flex w-full">
                        <input type="text" name="order_id" placeholder="Enter Woo Order ID (e.g. 199)"
                            class="flex-1 bg-slate-950/50 border border-slate-700/50 border-r-0 rounded-l-xl px-4 py-3 text-sm text-white focus:border-emerald-500 focus:outline-none transition-all">
                        <button type="submit"
                            class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-6 rounded-r-xl transition-all text-xs uppercase tracking-widest">
                            Fetch & Process
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($success)): ?>
        <div
            class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-8 flex items-center relative z-10 animate-fade-in-down">
            <i class="fas fa-check-circle mr-3 text-lg"></i>
            <span>
                <?php echo htmlspecialchars($success); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div
            class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-8 flex items-center relative z-10 animate-fade-in-down">
            <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
            <span>
                <?php echo htmlspecialchars($error); ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Orders Table -->
        <div class="ent-glass rounded-2xl shadow-xl overflow-hidden relative z-10">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-400">
                    <thead
                        class="bg-slate-900/80 text-slate-300 uppercase text-xs font-bold tracking-wider border-b border-slate-700/50">
                        <tr>
                            <th class="px-6 py-5">Order ID</th>
                            <th class="px-6 py-5">Email</th>
                            <th class="px-6 py-5">Amount</th>
                            <th class="px-6 py-5">Status</th>
                            <th class="px-6 py-5">License</th>
                            <th class="px-6 py-5">Date</th>
                            <th class="px-6 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach ($orders as $order): 
                            
                            $status_class = match(strtolower($order['status'])) {
                                'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                'pending' => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
                                'failed' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                'refunded' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                default => 'bg-slate-800 text-slate-300 border-slate-700'
                            };

                            $date = date('M d, Y H:i', strtotime($order['created_at']));
                        ?>
                        <tr class="hover:bg-slate-800/40 transition-colors group">
                            <td class="px-6 py-4 font-mono text-white text-xs">
                                #
                                <?php echo htmlspecialchars($order['woo_order_id']); ?>
                            </td>
                            <td class="px-6 py-4 text-white font-medium">
                                <?php echo htmlspecialchars($order['customer_email']); ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-slate-300">
                                <?php echo htmlspecialchars($order['amount'] . ' ' . $order['currency']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded text-[10px] uppercase font-bold border <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($order['license_key']): ?>
                                <div class="font-mono text-xs text-blue-400">
                                    <?php echo htmlspecialchars($order['license_key']); ?>
                                </div>
                                <?php else: ?>
                                <span
                                    class="text-slate-600 text-[10px] uppercase tracking-widest font-bold">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?php echo $date; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($order['status'] === 'completed'): ?>
                                <div class="flex items-center justify-end space-x-2">

                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="resend">
                                        <input type="hidden" name="order_id"
                                            value="<?php echo htmlspecialchars($order['woo_order_id']); ?>">
                                        <button type="submit"
                                            class="bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wider transition-colors"
                                            title="Resend Email">
                                            Resend
                                        </button>
                                    </form>

                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to refund this order and permanently revoke the generated license?');">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="revoke">
                                        <input type="hidden" name="order_id"
                                            value="<?php echo htmlspecialchars($order['woo_order_id']); ?>">
                                        <button type="submit"
                                            class="bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wider transition-colors"
                                            title="Revoke License">
                                            Revoke
                                        </button>
                                    </form>

                                </div>
                                <?php elseif ($order['status'] === 'refunded'): ?>
                                <span
                                    class="inline-flex items-center text-[10px] font-bold uppercase tracking-widest text-purple-500">
                                    <i class="fas fa-ban mr-1"></i> Revoked
                                </span>
                                <?php else: ?>
                                <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count() === 0): ?>
                <div class='p-16 text-center text-slate-500'>
                    <i class='fas fa-receipt text-3xl mb-4 opacity-30'></i>
                    <p class='text-xs font-bold uppercase tracking-widest'>No orders found matching criteria</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <div class='px-6 py-4 bg-slate-900/50 border-t border-slate-700/50 flex justify-between items-center'>
                <span class='text-xs font-bold text-slate-500 uppercase tracking-widest'>Page
                    <?php echo $page; ?> of
                    <?php echo $total_pages; ?>
                </span>
                <div class='flex space-x-2'>
                    <?php if ($page > 1): ?>
                    <a href='?filter=<?php echo urlencode($filter); ?>&page=<?php echo $page - 1; ?>'
                        class='px-3 py-1 bg-slate-800 text-white rounded hover:bg-slate-700 text-xs font-bold transition-colors'>Prev</a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href='?filter=<?php echo urlencode($filter); ?>&page=<?php echo $page + 1; ?>'
                        class='px-3 py-1 bg-slate-800 text-white rounded hover:bg-slate-700 text-xs font-bold transition-colors'>Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</body>

</html>