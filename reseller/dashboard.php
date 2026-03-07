<?php
// /reseller/dashboard.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$reseller_id = $_SESSION['reseller_id'];
$reseller_email = $_SESSION['reseller_email'];

// --- FETCH METRICS ---
// Total Licenses Generated
$stmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE reseller_id = ?");
$stmt->execute([$reseller_id]);
$total_generated = $stmt->fetchColumn();

// Active Domains (count of licenses with status='active' and non-empty registered_domains)
// In SQLite, JSON might just be a string '[]'. We count active licenses that have been activated.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE reseller_id = ? AND status = 'active'");
$stmt->execute([$reseller_id]);
$active_domains = $stmt->fetchColumn();

// Pending Activations
$stmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE reseller_id = ? AND status = 'pending_activation'");
$stmt->execute([$reseller_id]);
$pending_activations = $stmt->fetchColumn();

// Fetch Recent Customers
$stmt = $pdo->prepare("SELECT client_email, license_key, status, created_at FROM licenses WHERE reseller_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$reseller_id]);
$recent_customers = $stmt->fetchAll();

// Fetch Monthly Generation Stats for Chart
$current_year = date('Y');
$stmt = $pdo->prepare("
    SELECT strftime('%m', created_at) as month, COUNT(*) as count 
    FROM licenses 
    WHERE reseller_id = ? AND strftime('%Y', created_at) = ? 
    GROUP BY strftime('%m', created_at)
");
$stmt->execute([$reseller_id, $current_year]);
$monthly_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthly_data = array_fill(1, 12, 0);
foreach ($monthly_raw as $row) {
    if (!empty($row['month'])) {
        $monthly_data[(int)$row['month']] = (int)$row['count'];
    }
}
$chart_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$chart_values = array_values($monthly_data);

?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Dashboard - BioScript</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .ui-reseller {
            font-family: 'Inter', sans-serif;
            --ent-primary: #10b981;
            --ent-primary-dark: #059669;
            --ent-surface: #0f172a;
            --ent-card: #1e293b;
            --ent-border: #334155;
            --ent-border-strong: #475569;
            --ent-text: #f8fafc;
        }

        .ui-reseller .ent-card {
            background: var(--ent-card);
            border: 1px solid var(--ent-border);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }

        .ui-reseller .ent-btn-primary {
            background: var(--ent-primary);
            color: white;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        .ui-reseller .ent-btn-primary:hover {
            background: var(--ent-primary-dark);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
    </style>
</head>

<body class="ui-reseller bg-slate-950 text-slate-100 h-screen flex overflow-hidden font-sans">

    <!-- Sidebar -->
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
                class="flex items-center space-x-3 px-6 py-3 bg-slate-900 text-emerald-400 border-l-2 border-emerald-500 transition-all group">
                <i class="fas fa-chart-pie w-5 text-center"></i>
                <span class="font-semibold tracking-wide text-sm">Dashboard</span>
            </a>
            <a href="generate-license.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-key w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">Generate License</span>
            </a>
            <a href="customers.php"
                class="flex items-center space-x-3 px-6 py-3 text-slate-400 hover:bg-slate-900 border-l-2 border-transparent hover:border-slate-700 transition-all group">
                <i class="fas fa-users w-5 text-center group-hover:text-slate-300 transition-colors"></i>
                <span class="font-semibold tracking-wide text-sm">Customers</span>
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <div class="px-4 py-2 mb-2 text-xs text-slate-400 truncate whitespace-nowrap overflow-hidden text-ellipsis w-full"
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

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-8 lg:p-12 relative">
        <div
            class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-emerald-900/10 to-transparent pointer-events-none">
        </div>

        <header class="flex justify-between items-end mb-10 relative z-10">
            <div>
                <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Overview</h2>
                <p class="text-slate-400">Welcome back. View your license issuance metrics here.</p>
            </div>
            <a href="generate-license.php"
                class="ent-btn-primary font-bold py-3 px-6 rounded-xl transition-all flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>New License</span>
            </a>
        </header>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10 relative z-10">
            <!-- Metric 1 -->
            <div class="ent-card p-6 relative overflow-hidden group hover:border-emerald-500/50">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Generated
                        </h3>
                        <p class="text-3xl font-black text-white tracking-tight">
                            <?php echo number_format($total_generated); ?>
                        </p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 text-emerald-500">
                        <i class="fas fa-key text-lg"></i>
                    </div>
                </div>
            </div>

            <!-- Metric 2 -->
            <div class="ent-card p-6 relative overflow-hidden group hover:border-blue-500/50">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Active Domains
                        </h3>
                        <p class="text-3xl font-black text-blue-400 tracking-tight">
                            <?php echo number_format($active_domains); ?>
                        </p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center border border-blue-500/20 text-blue-500">
                        <i class="fas fa-globe text-lg"></i>
                    </div>
                </div>
            </div>

            <!-- Metric 3 -->
            <div class="ent-card p-6 relative overflow-hidden group hover:border-amber-500/50">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Pending
                            Activations</h3>
                        <p class="text-3xl font-black text-amber-400 tracking-tight">
                            <?php echo number_format($pending_activations); ?>
                        </p>
                    </div>
                    <div
                        class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center border border-amber-500/20 text-amber-500">
                        <i class="fas fa-clock text-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10 relative z-10">

            <!-- Monthly Chart -->
            <div class="ent-card p-6 lg:col-span-2 relative">
                <h3 class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-4">License Generation Overview
                    (
                    <?php echo $current_year; ?>)
                </h3>
                <div class="h-64 w-full">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Recent Activity Mini Table (Moved to Sidebar structure in grid) -->
            <div class="ent-card rounded-2xl shadow-xl overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-800">
                    <h3 class="text-lg font-bold text-white">Recent License Issuance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-400">
                        <thead
                            class="bg-slate-900/50 text-slate-300 uppercase text-xs font-bold tracking-wider border-b border-slate-800">
                            <tr>
                                <th class="px-6 py-4">Customer Email</th>
                                <th class="px-6 py-4">License Key</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Generated Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php if (empty($recent_customers)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500">No licenses generated yet.
                                </td>
                            </tr>
                            <?php
else: ?>
                            <?php foreach ($recent_customers as $customer): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="px-6 py-4 text-white font-medium">
                                    <?php echo htmlspecialchars($customer['client_email']); ?>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs">
                                    <?php echo htmlspecialchars($customer['license_key']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($customer['status'] === 'active'): ?>
                                    <span class="text-emerald-400 text-xs font-bold uppercase"><i
                                            class="fas fa-check-circle mr-1"></i> Active</span>
                                    <?php
        elseif ($customer['status'] === 'pending_activation'): ?>
                                    <span class="text-amber-400 text-xs font-bold uppercase"><i
                                            class="fas fa-clock mr-1"></i> Pending</span>
                                    <?php
        else: ?>
                                    <span class="text-red-400 text-xs font-bold uppercase"><i
                                            class="fas fa-ban mr-1"></i>
                                        <?php echo htmlspecialchars($customer['status']); ?>
                                    </span>
                                    <?php
        endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500 text-xs">
                                    <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                                </td>
                            </tr>
                            <?php
    endforeach; ?>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-slate-800 bg-slate-900/50 text-center">
                    <a href="customers.php"
                        class="text-emerald-400 hover:text-emerald-300 text-xs font-bold uppercase tracking-wider transition-colors">View
                        All Customers <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>

        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('monthlyChart').getContext('2d');

            Chart.defaults.color = '#64748b';
            Chart.defaults.font.family = 'Inter';

            // Create gradient
            let gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.5)'); // Emerald 500 at 50%
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <? php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Licenses Generated',
                    data: <? php echo json_encode($chart_values); ?>,
                    backgroundColor: gradient,
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6
                    }]
                },
            options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f8fafc',
                    bodyColor: '#cbd5e1',
                    borderColor: '#334155',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function (context) {
                            return context.parsed.y + ' licenses';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(51, 65, 85, 0.5)', drawBorder: false },
                    ticks: { precision: 0 }
                },
                x: {
                    grid: { display: false, drawBorder: false }
                }
            }
        }
            });
        });
    </script>
</body>

</html>