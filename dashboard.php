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

// CSRF Verification Function
function verify_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF Token Validation Failed');
    }
}

// --- ACTIONS ---

// Generate Key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])) {
    verify_csrf();
    $email = trim($_POST['email']);
    $tier = $_POST['tier']; // Standard, Agency, Enterprise
    
    // Tier Logic
    $max_domains = 1;
    if ($tier === 'Agency') $max_domains = 10;
    if ($tier === 'Enterprise') $max_domains = 999;
    
    // Format: BIO-XXXX-XXXX-XXXX (16 chars random hex)
    $key = 'BIO-' . strtoupper(bin2hex(random_bytes(8)));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO licenses (license_key, client_email, tier, max_domains, registered_domains, status) VALUES (:key, :email, :tier, :max, '[]', 'active')");
        $stmt->execute([
            ':key' => $key,
            ':email' => $email,
            ':tier' => $tier,
            ':max' => $max_domains
        ]);
        $success = "Generated Key: $key";
    } catch (PDOException $e) {
        $error = "Error generating key: " . $e->getMessage();
    }
}

// Upgrade Tier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_tier'])) {
    verify_csrf();
    $id = $_POST['id'];
    $tier = $_POST['tier'];
    
    $max_domains = 1;
    if ($tier === 'Agency') $max_domains = 10;
    if ($tier === 'Enterprise') $max_domains = 999;
    
    $stmt = $pdo->prepare("UPDATE licenses SET tier = :tier, max_domains = :max WHERE id = :id");
    $stmt->execute([':tier' => $tier, ':max' => $max_domains, ':id' => $id]);
    header('Location: dashboard.php?msg=upgraded');
    exit;
}

// Toggle Status (Ban/Unban)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    // Note: GET actions are harder to CSRF protect without a token in URL. 
    // For strict security, these should be POSTs. 
    // However, for this dashboard, we'll verify a token passed in GET or just proceed if we assume Admin-only access is safe enough for now.
    // Let's add a token check to GET for safety.
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
        die('Invalid Security Token');
    }

    $id = $_GET['id'];
    $current = $_GET['current'];
    $new_status = ($current === 'active') ? 'banned' : 'active';
    
    $stmt = $pdo->prepare("UPDATE licenses SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $new_status, ':id' => $id]);
    header('Location: dashboard.php');
    exit;
}

// Reset Domains
if (isset($_GET['action']) && $_GET['action'] === 'reset_domains' && isset($_GET['id'])) {
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
        die('Invalid Security Token');
    }
    $stmt = $pdo->prepare("UPDATE licenses SET registered_domains = '[]' WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    header('Location: dashboard.php?msg=domains_reset');
    exit;
}

// Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
        die('Invalid Security Token');
    }
    $stmt = $pdo->prepare("DELETE FROM licenses WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    header('Location: dashboard.php?msg=deleted');
    exit;
}

// Fetch All Licenses
$stmt = $pdo->query("SELECT * FROM licenses ORDER BY created_at DESC");
$licenses = $stmt->fetchAll();

$total_licenses = count($licenses);
$active_licenses = count(array_filter($licenses, fn($l) => $l['status'] === 'active'));
$banned_licenses = count(array_filter($licenses, fn($l) => $l['status'] === 'banned'));
$agency_licenses = count(array_filter($licenses, fn($l) => $l['tier'] === 'Agency'));
$enterprise_licenses = count(array_filter($licenses, fn($l) => $l['tier'] === 'Enterprise'));

// Webhook Health Data
$stmt = $pdo->query("SELECT * FROM webhook_health WHERE id = 1");
$health = $stmt->fetch();

$webhook_status = 'inactive';
if ($health && !empty($health['last_received_at'])) {
    $last_received = strtotime($health['last_received_at'] . ' UTC');
    $now = time();
    if (($now - $last_received) <= 600) { // 10 minutes
        $webhook_status = 'active';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Dashboard - License Authority</title>
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

        .ui-enterprise .ent-card {
            background: var(--ent-card);
            border: 1px solid var(--ent-border);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.2s;
        }

        .ui-enterprise .ent-card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            border-color: var(--ent-border-strong);
            z-index: 10;
        }

        .ui-enterprise .ent-input {
            background: var(--ent-surface);
            border: 1px solid var(--ent-border);
            color: var(--ent-text);
            transition: all 0.2s;
        }

        .ui-enterprise .ent-input:focus {
            border-color: var(--ent-accent);
            box-shadow: 0 0 0 1px var(--ent-accent), inset 0 1px 2px rgba(0, 0, 0, 0.5);
            outline: none;
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

        /* Tier Selection Cards */
        .ui-enterprise .ent-tier-card {
            border: 1px solid var(--ent-border);
            background: var(--ent-surface);
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            border-radius: 0.5rem;
        }

        .ui-enterprise .ent-tier-card:hover {
            border-color: var(--ent-border-strong);
        }

        .ui-enterprise .ent-tier-input:checked+.ent-tier-card {
            border-color: var(--ent-accent);
            background: rgba(59, 130, 246, 0.08);
            box-shadow: 0 0 0 1px var(--ent-accent);
        }

        .ui-enterprise .ent-tier-input:checked+.ent-tier-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            border-width: 0 24px 24px 0;
            border-style: solid;
            border-color: transparent var(--ent-accent) transparent transparent;
        }

        .ui-enterprise .ent-tier-input:checked+.ent-tier-card::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 2px;
            right: 3px;
            color: white;
            font-size: 8px;
        }

        /* Status Badges */
        .ui-enterprise .badge-active {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .ui-enterprise .badge-suspended {
            background: rgba(245, 158, 11, 0.1);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .ui-enterprise .badge-refunded {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .ui-enterprise .badge-pending {
            background: rgba(148, 163, 184, 0.1);
            color: #cbd5e1;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
    </style>
</head>

<body class="ui-enterprise bg-slate-950 text-slate-100 h-screen flex overflow-hidden font-sans" x-data="{ 
    generateModalOpen: false, 
    upgradeModalOpen: false, 
    domainsModalOpen: false,
    selectedLicense: null,
    domainList: [],
    tierSelection: 'Standard'
}">

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
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-blue-400 border-l-2 border-blue-500 transition-all group">
                <i class="fas fa-satellite-dish w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">License Overview</span>
            </a>

            <a href="orders.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-shopping-cart w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">WooCommerce Orders</span>
            </a>

            <a href="settings.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-sliders-h w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">Global Settings</span>
            </a>
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
                <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">License CRM</h2>
                <p class="text-slate-400">Manage client licenses, tiers, and domain allocations.</p>
            </div>
            <button @click="generateModalOpen = true"
                class="bg-primary-600 hover:bg-primary-500 text-white font-bold py-3 px-6 rounded-xl shadow-lg shadow-primary-600/20 transition-all flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Generate New License</span>
            </button>
        </header>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10 relative z-10">
            <!-- Metric 1 -->
            <div class="ent-card p-6 relative overflow-hidden group">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Deployed
                        </h3>
                        <p class="text-3xl font-black text-white tracking-tight">
                            <?php echo number_format($total_licenses); ?>
                        </p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center border border-blue-500/20 text-blue-500">
                        <i class="fas fa-cubes text-lg"></i>
                    </div>
                </div>
                <!-- Mini SVG Chart -->
                <svg class="w-full h-10 stroke-blue-500/30 group-hover:stroke-blue-500 transition-colors duration-500"
                    viewBox="0 0 100 20" preserveAspectRatio="none">
                    <path d="M0,20 Q10,5 20,15 T40,10 T60,18 T80,5 T100,10" fill="none" stroke-width="2"
                        stroke-linecap="round"></path>
                    <path d="M0,20 Q10,5 20,15 T40,10 T60,18 T80,5 T100,10 L100,20 L0,20 Z" fill="url(#blue-grad)"
                        stroke="none"></path>
                    <defs>
                        <linearGradient id="blue-grad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="rgba(59,130,246,0.15)"></stop>
                            <stop offset="100%" stop-color="transparent"></stop>
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <!-- Metric 2 -->
            <div class="ent-card p-6 relative overflow-hidden group">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Active Nodes
                        </h3>
                        <p class="text-3xl font-black text-emerald-400 tracking-tight">
                            <?php echo number_format($active_licenses); ?>
                        </p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 text-emerald-500">
                        <i class="fas fa-check-circle text-lg"></i>
                    </div>
                </div>
                <!-- Mini SVG Chart -->
                <svg class="w-full h-10 stroke-emerald-500/30 group-hover:stroke-emerald-500 transition-colors duration-500"
                    viewBox="0 0 100 20" preserveAspectRatio="none">
                    <path d="M0,15 L20,15 L30,5 L40,15 L80,15 L90,8 L100,15" fill="none" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M0,15 L20,15 L30,5 L40,15 L80,15 L90,8 L100,15 L100,20 L0,20 Z" fill="url(#emerald-grad)"
                        stroke="none"></path>
                    <defs>
                        <linearGradient id="emerald-grad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="rgba(16,185,129,0.15)"></stop>
                            <stop offset="100%" stop-color="transparent"></stop>
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <!-- Metric 3 -->
            <div class="ent-card p-6 relative overflow-hidden group">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Premium Tiers
                        </h3>
                        <p class="text-3xl font-black text-purple-400 tracking-tight">
                            <?php echo number_format($agency_licenses + $enterprise_licenses); ?>
                        </p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center border border-purple-500/20 text-purple-500">
                        <i class="fas fa-crown text-lg"></i>
                    </div>
                </div>
                <div class="w-full h-10 flex items-end">
                    <div class="w-full bg-slate-800/50 rounded-full h-2 overflow-hidden">
                        <?php 
                        $prem_pct = $total_licenses > 0 ? (($agency_licenses + $enterprise_licenses) / $total_licenses) * 100 : 0; 
                        ?>
                        <div class="bg-purple-500 h-full rounded-full transition-all duration-1000"
                            style="width: <?php echo $prem_pct; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Metric 4 -->
            <div class="ent-card p-6 relative overflow-hidden group">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Revoked</h3>
                        <p class="text-3xl font-black text-red-400 tracking-tight">
                            <?php echo number_format((float)$banned_licenses); ?>
                        </p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg bg-red-500/10 flex items-center justify-center border border-red-500/20 text-red-500">
                        <i class="fas fa-ban text-lg"></i>
                    </div>
                </div>
                <!-- Mini SVG Chart -->
                <svg class="w-full h-10 stroke-red-500/30 group-hover:stroke-red-500 transition-colors duration-500"
                    viewBox="0 0 100 20" preserveAspectRatio="none">
                    <path d="M0,5 L20,8 L40,15 L60,12 L80,18 L100,10" fill="none" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M0,5 L20,8 L40,15 L60,12 L80,18 L100,10 L100,20 L0,20 Z" fill="url(#red-grad)"
                        stroke="none"></path>
                    <defs>
                        <linearGradient id="red-grad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="rgba(239,68,68,0.15)"></stop>
                            <stop offset="100%" stop-color="transparent"></stop>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>

        <!-- Webhook Health Monitor -->
        <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mb-10 relative z-10">
            <div
                class="ent-card p-6 border-l-4 <?php echo $webhook_status === 'active' ? 'border-l-emerald-500' : 'border-l-red-500'; ?> bg-slate-900/40">
                <div
                    class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                    <div class="flex items-center space-x-4">
                        <div class="flex h-3 w-3 relative">
                            <?php if ($webhook_status === 'active'): ?>
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            <?php else: ?>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="text-white font-bold flex items-center">
                                Webhook Resilience Engine
                                <span
                                    class="ml-3 px-2 py-0.5 rounded text-[9px] font-black uppercase <?php echo $webhook_status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'; ?>">
                                    <?php echo $webhook_status === 'active' ? 'Signal Active' : 'No Recent Activity'; ?>
                                </span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Last received: <span class="text-slate-300 font-mono">
                                    <?php echo $health['last_received_at'] ? date('Y-m-d H:i:s', strtotime($health['last_received_at'] . ' UTC')) : 'Never'; ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-8">
                        <div class="text-center">
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Total
                                Signals</p>
                            <p class="text-xl font-bold text-white">
                                <?php echo number_format((float)($health['total_received'] ?? 0)); ?>
                            </p>
                        </div>
                        <div class="text-center">
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Total Drops
                            </p>
                            <p
                                class="text-xl font-bold <?php echo ($health['total_failed'] ?? 0) > 0 ? 'text-red-400' : 'text-slate-400'; ?>">
                                <?php echo number_format((float)($health['total_failed'] ?? 0)); ?>
                            </p>
                        </div>
                        <?php if (!empty($health['last_error'])): ?>
                        <div class="max-w-xs overflow-hidden">
                            <p class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-1">Last Error</p>
                            <p class="text-[10px] text-red-400 truncate"
                                title="<?php echo htmlspecialchars($health['last_error']); ?>">
                                <?php echo htmlspecialchars($health['last_error']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
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

        <!-- CRM Table -->
        <div class="glass-panel rounded-2xl shadow-xl overflow-hidden relative z-10">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-400">
                    <thead
                        class="bg-slate-900/50 text-slate-300 uppercase text-xs font-bold tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-5">Client Info</th>
                            <th class="px-6 py-5">License Key</th>
                            <th class="px-6 py-5">Domain Usage</th>
                            <th class="px-6 py-5">Status</th>
                            <th class="px-6 py-5">Fingerprint / Last Ping</th>
                            <th class="px-6 py-5">Created</th>
                            <th class="px-6 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php foreach ($licenses as $lic): 
                            $domains = json_decode($lic['registered_domains'] ?: '[]', true);
                            $domain_count = is_array($domains) ? count($domains) : 0;
                            $percentage = ($domain_count / max(1, $lic['max_domains'])) * 100;
                            
                            $tierClass = match($lic['tier']) {
                                'Agency' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                'Enterprise' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                default => 'bg-blue-500/10 text-blue-400 border-blue-500/20'
                            };
                        ?>
                        <tr class="hover:bg-slate-800/30 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="font-medium text-white mb-1">
                                        <?php echo htmlspecialchars($lic['client_email'] ?: 'Unknown Client'); ?>
                                    </span>
                                    <span
                                        class="inline-flex w-fit items-center px-2 py-0.5 rounded text-[10px] font-bold border <?php echo $tierClass; ?>">
                                        <?php echo htmlspecialchars($lic['tier']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <button
                                    onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($lic['license_key']); ?>');"
                                    class="bg-slate-950 hover:bg-slate-900 border border-slate-800 hover:border-primary-500/50 px-3 py-1.5 rounded-lg text-xs font-mono text-slate-300 transition-all flex items-center group/key relative">
                                    <span class="truncate max-w-[140px]">
                                        <?php echo htmlspecialchars($lic['license_key']); ?>
                                    </span>
                                    <i class="fas fa-copy ml-2 text-slate-500 group-hover/key:text-primary-400"></i>
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-1 min-w-[120px]">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-white font-mono">
                                                <?php echo $domain_count; ?> /
                                                <?php echo $lic['max_domains']; ?>
                                            </span>
                                            <?php if ($domain_count > 0): ?>
                                            <button
                                                @click="domainList = <?php echo htmlspecialchars(json_encode($domains)); ?>; domainsModalOpen = true"
                                                class="text-blue-400 hover:text-blue-300 text-[10px] uppercase font-bold tracking-wider">View
                                                Domains</button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                            <div class="h-full <?php echo $percentage >= 100 ? 'bg-red-500' : 'bg-primary-500'; ?> rounded-full transition-all duration-500"
                                                style="width: <?php echo min(100, $percentage); ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($lic['status'] === 'active'): ?>
                                <div class="flex items-center">
                                    <div class="relative flex h-2 w-2 mr-2">
                                        <span
                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                    </div>
                                    <span class="text-emerald-400 text-xs font-bold uppercase">Active</span>
                                </div>
                                <?php else: ?>
                                <div class="flex items-center">
                                    <span class="h-2 w-2 rounded-full bg-red-500 mr-2"></span>
                                    <span class="text-red-400 text-xs font-bold uppercase">
                                        <?php echo ucfirst($lic['status']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col text-[10px] font-mono">
                                    <span class="text-slate-300 truncate max-w-[120px]"
                                        title="<?php echo htmlspecialchars($lic['installation_fingerprint'] ?: 'N/A'); ?>">
                                        ID:
                                        <?php echo $lic['installation_fingerprint'] ? substr($lic['installation_fingerprint'], 0, 12) . '...' : 'N/A'; ?>
                                    </span>
                                    <span class="text-slate-500 mt-1">
                                        Ping:
                                        <?php echo $lic['last_verified_at'] ? date('M d, H:i', strtotime($lic['last_verified_at'] . ' UTC')) : 'Never'; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs">
                                <?php echo date('M d, Y', strtotime($lic['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" @click.away="open = false"
                                        class="p-2 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white transition-colors">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div x-show="open" style="display: none;"
                                        class="absolute right-0 mt-2 w-48 bg-slate-900 border border-slate-700 rounded-xl shadow-xl z-50 overflow-hidden"
                                        x-transition.origin.top.right>
                                        <a href="?action=toggle_status&id=<?php echo $lic['id']; ?>&current=<?php echo $lic['status']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>"
                                            class="block px-4 py-3 text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition-colors border-b border-slate-800">
                                            <?php echo $lic['status'] === 'active' ? '<i class="fas fa-ban w-5 text-red-400"></i> Ban License' : '<i class="fas fa-check w-5 text-emerald-400"></i> Activate'; ?>
                                        </a>
                                        <button
                                            @click="selectedLicense = <?php echo htmlspecialchars(json_encode($lic)); ?>; upgradeModalOpen = true; open = false"
                                            class="w-full text-left px-4 py-3 text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition-colors border-b border-slate-800">
                                            <i class="fas fa-arrow-circle-up w-5 text-purple-400"></i> Upgrade Tier
                                        </button>
                                        <a href="?action=reset_domains&id=<?php echo $lic['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>"
                                            @click.prevent="Swal.fire({
                                               title: 'Reset Domains?',
                                               text: 'Client can then activate on new sites.',
                                               icon: 'warning',
                                               showCancelButton: true,
                                               confirmButtonColor: '#3b82f6',
                                               cancelButtonColor: '#334155',
                                               confirmButtonText: 'Yes, reset them',
                                               background: '#1e293b',
                                               color: '#fff'
                                           }).then((result) => {
                                               if (result.isConfirmed) {
                                                   window.location.href = $el.href;
                                               }
                                           })"
                                            class="block px-4 py-3 text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition-colors border-b border-slate-800">
                                            <i class="fas fa-sync w-5 text-blue-400"></i> Reset Domains
                                        </a>
                                        <a href="?action=delete&id=<?php echo $lic['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>"
                                            @click.prevent="Swal.fire({
                                               title: 'Permanently Delete?',
                                               text: 'This action cannot be undone.',
                                               icon: 'warning',
                                               showCancelButton: true,
                                               confirmButtonColor: '#ef4444',
                                               cancelButtonColor: '#334155',
                                               confirmButtonText: 'Yes, delete it',
                                               background: '#1e293b',
                                               color: '#fff'
                                           }).then((result) => {
                                               if (result.isConfirmed) {
                                                   window.location.href = $el.href;
                                               }
                                           })"
                                            class="block px-4 py-3 text-sm text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors">
                                            <i class="fas fa-trash w-5"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($licenses) === 0): ?>
                <div class="p-12 text-center text-slate-500">
                    <i class="fas fa-database text-3xl mb-3 opacity-30"></i>
                    <p class="text-xs font-bold uppercase tracking-widest">No architectures deployed</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- GENERATE LICENSE MODAL -->
    <div x-show="generateModalOpen" style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 p-4"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="ent-glass w-full max-w-lg rounded-lg overflow-hidden" @click.away="generateModalOpen = false">
            <div class="p-5 border-b border-slate-700/50 flex justify-between items-center bg-slate-900/50">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-key text-blue-500"></i>
                    <h3 class="text-sm font-black text-white uppercase tracking-widest">Deploy New Key</h3>
                </div>
                <button @click="generateModalOpen = false" class="text-slate-500 hover:text-white transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-8">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-6">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Target
                            Client Origin</label>
                        <input type="email" name="email" placeholder="identity@domain.com" required
                            class="ent-input w-full rounded px-4 py-3 text-sm font-medium">
                    </div>
                    <div class="mb-8">
                        <label
                            class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Provisioning
                            Tier</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <label class="relative block">
                                <input type="radio" name="tier" value="Standard" x-model="tierSelection"
                                    class="peer sr-only ent-tier-input">
                                <div class="ent-tier-card p-3 h-full">
                                    <div class="text-[10px] font-black uppercase text-blue-400 mb-1">Creator</div>
                                    <div class="text-xs text-white font-bold">1 Node</div>
                                </div>
                            </label>
                            <label class="relative block">
                                <input type="radio" name="tier" value="Agency" x-model="tierSelection"
                                    class="peer sr-only ent-tier-input">
                                <div class="ent-tier-card p-3 h-full">
                                    <div class="text-[10px] font-black uppercase text-purple-400 mb-1">White-Label</div>
                                    <div class="text-xs text-white font-bold">10 Nodes</div>
                                </div>
                            </label>
                            <label class="relative block">
                                <input type="radio" name="tier" value="Enterprise" x-model="tierSelection"
                                    class="peer sr-only ent-tier-input">
                                <div class="ent-tier-card p-3 h-full">
                                    <div class="text-[10px] font-black uppercase text-amber-500 mb-1">Enterprise</div>
                                    <div class="text-xs text-white font-bold">Unlimited</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="generate_key"
                        class="ent-btn-primary w-full text-xs font-black uppercase tracking-widest py-3.5 rounded flex items-center justify-center space-x-2">
                        <span>Execute Deployment</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- UPGRADE TIER MODAL -->
    <div x-show="upgradeModalOpen" style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 p-4"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="ent-glass w-full max-w-lg rounded-lg overflow-hidden" @click.away="upgradeModalOpen = false">
            <div class="p-5 border-b border-slate-700/50 flex justify-between items-center bg-slate-900/50">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-arrow-up text-purple-500"></i>
                    <h3 class="text-sm font-black text-white uppercase tracking-widest">Elevate Tier</h3>
                </div>
                <button @click="upgradeModalOpen = false" class="text-slate-500 hover:text-white transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-8">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" :value="selectedLicense ? selectedLicense.id : ''">
                    <input type="hidden" name="upgrade_tier" value="1">

                    <div class="mb-5 text-center bg-slate-900/50 border border-slate-800 rounded p-4">
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-1"
                            x-text="selectedLicense ? selectedLicense.client_email : ''"></p>
                        <p class="text-sm text-blue-400 font-mono tracking-wide"
                            x-text="selectedLicense ? selectedLicense.license_key : ''"></p>
                    </div>

                    <div class="mb-8">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <label class="relative block">
                                <input type="radio" name="tier" value="Standard" x-model="tierSelection"
                                    class="peer sr-only ent-tier-input">
                                <div class="ent-tier-card p-3 h-full">
                                    <div class="text-[10px] font-black uppercase text-blue-400 mb-1">Creator</div>
                                    <div class="text-xs text-white font-bold">1 Node</div>
                                </div>
                            </label>
                            <label class="relative block">
                                <input type="radio" name="tier" value="Agency" x-model="tierSelection"
                                    class="peer sr-only ent-tier-input">
                                <div class="ent-tier-card p-3 h-full">
                                    <div class="text-[10px] font-black uppercase text-purple-400 mb-1">White-Label</div>
                                    <div class="text-xs text-white font-bold">10 Nodes</div>
                                </div>
                            </label>
                            <label class="relative block">
                                <input type="radio" name="tier" value="Enterprise" x-model="tierSelection"
                                    class="peer sr-only ent-tier-input">
                                <div class="ent-tier-card p-3 h-full">
                                    <div class="text-[10px] font-black uppercase text-amber-500 mb-1">Enterprise</div>
                                    <div class="text-xs text-white font-bold">Unlimited</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <button type="submit"
                        class="w-full bg-purple-600 hover:bg-purple-500 text-white font-black text-xs uppercase tracking-widest py-3.5 rounded transition-all active:scale-98 shadow-[0_2px_4px_rgba(147,51,234,0.3)]">
                        Confirm Elevation
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- VIEW DOMAINS MODAL (License Detail Panel) -->
    <div x-show="domainsModalOpen" style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-end bg-slate-950/80 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-10"
        x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-10">
        <div class="ent-glass h-full w-full max-w-md shadow-2xl relative overflow-y-auto border-y-0 border-r-0 rounded-none flex flex-col"
            @click.away="domainsModalOpen = false">
            <div class="p-6 border-b border-slate-700/50 flex justify-between items-center bg-slate-900/50 shrink-0">
                <div class="flex items-center space-x-3 text-emerald-500">
                    <i class="fas fa-layer-group"></i>
                    <h3 class="text-sm font-black text-white uppercase tracking-widest">License Details</h3>
                </div>
                <button @click="domainsModalOpen = false" class="text-slate-500 hover:text-white transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 flex-1 space-y-6">
                <!-- Details Group -->
                <div class="bg-slate-950 rounded border border-slate-800/80 p-5">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Client
                        Identity</label>
                    <div class="text-sm font-bold text-white mb-4"
                        x-text="selectedLicense ? selectedLicense.client_email : ''"></div>

                    <label
                        class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Architecture
                        Key</label>
                    <div
                        class="bg-slate-900 border border-slate-800 rounded px-3 py-2 text-xs font-mono text-blue-400 mb-4 tracking-wider flex justify-between items-center">
                        <span x-text="selectedLicense ? selectedLicense.license_key : ''"></span>
                        <button onclick="navigator.clipboard.writeText(selectedLicense.license_key)"
                            class="text-slate-500 hover:text-white"><i class="fas fa-copy"></i></button>
                    </div>

                    <div class="flex justify-between items-center">
                        <div>
                            <label
                                class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Status</label>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                                :class="selectedLicense && selectedLicense.status === 'active' ? 'badge-active' : 'badge-suspended'">
                                <span
                                    x-text="selectedLicense && selectedLicense.status === 'active' ? 'Active' : 'Suspended'"></span>
                            </span>
                        </div>
                        <div class="text-right">
                            <label
                                class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Provisioned</label>
                            <span class="text-xs text-slate-300 font-mono"
                                x-text="selectedLicense ? new Date(selectedLicense.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : ''"></span>
                        </div>
                    </div>
                </div>

                <!-- Domain Bindings -->
                <div>
                    <h4
                        class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 flex items-center justify-between">
                        <span>Domain Bindings</span>
                        <span class="bg-slate-800 text-slate-300 px-2 py-0.5 rounded"
                            x-text="domainList.length + ' / ' + (selectedLicense ? selectedLicense.max_domains : 0)"></span>
                    </h4>
                    <ul class="space-y-2">
                        <template x-for="domain in domainList">
                            <li class="flex items-center space-x-3 p-3 bg-slate-950 rounded border border-slate-800">
                                <i class="fas fa-link text-slate-600 text-[10px]"></i>
                                <span class="text-xs font-mono text-slate-300 tracking-wide" x-text="domain"></span>
                            </li>
                        </template>
                        <template x-if="domainList.length === 0">
                            <li class="p-5 text-center bg-slate-950 rounded border border-dashed border-slate-800">
                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">No Domains
                                    Registered</p>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="p-6 border-t border-slate-700/50 bg-slate-900/30">
                <a :href="'?action=reset_domains&id=' + (selectedLicense ? selectedLicense.id : '') + '&token=<?php echo $_SESSION['csrf_token']; ?>'"
                    class="w-full bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold uppercase tracking-widest py-3 rounded flex items-center justify-center space-x-2 transition-colors">
                    <i class="fas fa-sync-alt"></i>
                    <span>Reset Bindings</span>
                </a>
            </div>
        </div>
    </div>

</body>

</html>