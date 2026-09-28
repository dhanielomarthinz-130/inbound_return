<?php
require_once __DIR__ . '/config.php';
$currentUser = getSessionUser();
checkMaintenanceMode($pdo, $currentUser);
$user = requireLogin(['admin', 'superadmin']);
$isSuperAdmin = ($user['role'] === 'superadmin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Inbound Return</title>
    <!-- Favicon Huruf D Warna Hijau -->
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Flatpickr Date Picker (Premium Airbnb theme) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= file_exists(__DIR__ . '/assets/css/custom.css') ? filemtime(__DIR__ . '/assets/css/custom.css') : time() ?>">
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 flex overflow-x-hidden">

    <!-- Mobile Backdrop -->
    <div id="sidebarBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-40 lg:hidden hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-slate-900 text-slate-200 flex flex-col shrink-0 fixed inset-y-0 left-0 z-50 lg:static transition-transform duration-300 transform -translate-x-full lg:translate-x-0 shadow-2xl lg:shadow-none border-r border-slate-800">
        
        <!-- Sidebar Brand -->
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white p-1.5 flex items-center justify-center shadow-md shrink-0">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
                </div>
                <div>
                    <h1 class="font-bold text-white text-base leading-tight tracking-tight">Return Inbound</h1>
                </div>
            </div>
            <button id="btnCloseSidebar" class="lg:hidden text-slate-400 hover:text-white p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Sidebar Navigation -->
        <div class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
            <div class="px-3 pb-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Menu Navigasi</div>

            <button onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition text-white bg-indigo-600 shadow-sm shadow-indigo-600/30">
                <i class="fa-solid fa-gauge-high w-5 text-center text-indigo-200"></i>
                <span>Dashboard Overview</span>
            </button>

            <button onclick="switchTab('transactions')" id="nav-transactions" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-box-open w-5 text-center"></i>
                <span>Inbound Unboxing</span>
            </button>

            <button onclick="switchTab('products')" id="nav-products" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-tags w-5 text-center"></i>
                <span>Master Produk</span>
            </button>

            <button onclick="switchTab('expeditions')" id="nav-expeditions" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-truck-fast w-5 text-center"></i>
                <span>Master Ekspedisi</span>
            </button>

            <button onclick="switchTab('conditions')" id="nav-conditions" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-tags w-5 text-center"></i>
                <span>Master Kondisi</span>
            </button>

            <button onclick="switchTab('users')" id="nav-users" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-users-gear w-5 text-center"></i>
                <span>Kelola Pengguna</span>
            </button>

            <?php if ($isSuperAdmin): ?>
            <button onclick="switchTab('maintenance')" id="nav-maintenance" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-amber-400 hover:text-amber-300 hover:bg-amber-950/40 border border-amber-500/20">
                <i class="fa-solid fa-screwdriver-wrench w-5 text-center text-amber-400"></i>
                <span>Pemeliharaan</span>
            </button>
            <?php endif; ?>

            <div class="pt-4 px-3 pb-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Akses Langsung</div>

            <a href="scanner" class="w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-emerald-400 hover:text-emerald-300 hover:bg-emerald-950/40 border border-emerald-500/20 transition">
                <i class="fa-solid fa-barcode w-5 text-center"></i>
                <span>Buka Scanner Operator</span>
            </a>
        </div>

        <!-- Sidebar Footer: User Info & Logout Button -->
        <div class="p-3.5 border-t border-slate-800 bg-slate-950/80 flex items-center justify-between">
            <div class="flex items-center space-x-2.5 overflow-hidden">
                <div class="w-8 h-8 rounded-xl bg-indigo-600/30 text-indigo-400 border border-indigo-500/30 flex items-center justify-center shrink-0">
                    <i class="fa-solid <?= $isSuperAdmin ? 'fa-shield-halved text-amber-400' : 'fa-user-tie text-indigo-400' ?> text-xs"></i>
                </div>
                <div class="truncate">
                    <div class="text-xs font-bold text-slate-200 truncate"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="text-[10px] <?= $isSuperAdmin ? 'text-amber-400' : 'text-indigo-400' ?> font-mono uppercase font-semibold"><?= $user['role'] ?></div>
                </div>
            </div>
            <a href="logout" onclick="return confirm('Apakah Anda yakin ingin logout?')" title="Logout / Keluar" class="text-rose-400 hover:text-white hover:bg-rose-600/30 p-2 rounded-xl transition">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">

        <!-- Top Header Bar -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-20 px-4 md:px-8 py-3.5 flex items-center justify-between shadow-xs">
            <div class="flex items-center space-x-3">
                <button id="btnOpenSidebar" class="lg:hidden text-slate-600 hover:text-slate-900 p-1.5 rounded-lg border border-slate-200">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="font-bold text-lg md:text-xl text-slate-800" id="currentViewTitle">Dashboard Monitoring Retur</h2>
                </div>
            </div>
        </header>

        <!-- Body Container (Jarak ke topbar konsisten dan rapi di semua halaman) -->
        <main class="w-full px-4 md:px-8 py-6 flex-1">

            <!-- TAB 1: DASHBOARD OVERVIEW -->
            <div id="tab-dashboard" class="tab-content space-y-5">

                <!-- Filter Bar -->
                <div class="bg-white p-4 rounded-2xl shadow-xs border border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-600/10 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-chart-line text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-800">Dashboard Monitoring Retur</h3>
                            <span id="dashboardDateBadge" class="bg-indigo-50 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-indigo-200">Hari Ini</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto flex-wrap">
                        <div class="relative flex items-center">
                            <span class="absolute left-3 text-indigo-600 pointer-events-none text-xs z-10">
                                <i class="fa-regular fa-calendar-days"></i>
                            </span>
                            <input type="text" id="dashboardFilterDate" placeholder="Pilih Tanggal / Rentang..." readonly
                                class="bg-slate-50 hover:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs transition w-full sm:w-64 cursor-pointer">
                            <button type="button" id="btnClearDashboardDate" onclick="clearDashboardDateFilter()" title="Hapus filter tanggal" class="absolute right-2.5 text-slate-400 hover:text-rose-500 transition text-xs hidden z-10">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </button>
                        </div>
                        <button onclick="applyDashboardDateFilter()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-indigo-600/30 shrink-0">
                            <i class="fa-solid fa-filter text-[11px]"></i> Terapkan
                        </button>
                        <button onclick="exportDashboardExcel()" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/20 shrink-0">
                            <i class="fa-solid fa-file-excel text-xs"></i> Export Excel
                        </button>
                    </div>
                </div>

                <!-- KPI Cards Row -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Total Invoice -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 relative overflow-hidden group hover:shadow-md transition">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-indigo-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Invoice</span>
                                <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <i class="fa-solid fa-file-invoice text-xs"></i>
                                </span>
                            </div>
                            <div class="text-3xl font-black text-slate-800" id="kpiTotalInvoice">—</div>
                            <div class="text-[10px] text-slate-400 mt-1">Sesi retur tercatat</div>
                        </div>
                    </div>
                    <!-- Total Unit -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 relative overflow-hidden group hover:shadow-md transition">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-blue-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Qty</span>
                                <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                    <i class="fa-solid fa-boxes-stacked text-xs"></i>
                                </span>
                            </div>
                            <div class="text-3xl font-black text-slate-800" id="kpiTotalItems">—</div>
                            <div class="text-[10px] text-slate-400 mt-1">Total unit fisik masuk</div>
                        </div>
                    </div>
                    <!-- Kondisi Baik -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-emerald-100 relative overflow-hidden group hover:shadow-md transition">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-emerald-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Kondisi Baik</span>
                                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                    <i class="fa-solid fa-circle-check text-xs"></i>
                                </span>
                            </div>
                            <div class="text-3xl font-black text-emerald-600" id="kpiTotalGood">—</div>
                            <div class="text-[10px] text-emerald-500 mt-1">Layak restock / jual</div>
                        </div>
                    </div>
                    <!-- Kondisi Rusak -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-rose-100 relative overflow-hidden group hover:shadow-md transition">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-rose-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-rose-600 uppercase tracking-wider">Kondisi Rusak</span>
                                <span class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                </span>
                            </div>
                            <div class="text-3xl font-black text-rose-600" id="kpiTotalDamaged">—</div>
                            <div class="text-[10px] text-rose-500 mt-1">Cacat / retur vendor</div>
                        </div>
                    </div>
                </div>

                <!-- Middle Row: Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
                    <!-- Donut Rasio -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 lg:col-span-1 flex flex-col">
                        <h4 class="font-bold text-sm text-slate-700 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-chart-pie text-indigo-500 text-xs"></i> Rasio Kondisi
                        </h4>
                        <div class="flex-1 relative min-h-[180px]">
                            <canvas id="ratioChart"></canvas>
                        </div>
                    </div>
                    <!-- Trend 7 hari -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 lg:col-span-4 flex flex-col">
                        <h4 class="font-bold text-sm text-slate-700 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-chart-area text-indigo-500 text-xs"></i> Trend Volume Retur 7 Hari Terakhir
                        </h4>
                        <div class="flex-1 relative min-h-[170px]">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Expedition Tables -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <!-- Total Qty per Ekspedisi -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                    <i class="fa-solid fa-truck-fast text-xs"></i>
                                </span>
                                <h4 class="font-bold text-sm text-slate-800">Total Qty per Ekspedisi</h4>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px]">
                                    <tr>
                                        <th class="p-3">#</th>
                                        <th class="p-3">Ekspedisi</th>
                                        <th class="p-3 text-center">Total Sesi</th>
                                        <th class="p-3 text-right">Total Qty</th>
                                        <th class="p-3">Proporsi</th>
                                    </tr>
                                </thead>
                                <tbody id="dashExpeditionTableBody" class="divide-y divide-slate-100">
                                    <tr><td colspan="5" class="p-6 text-center text-slate-400">Memuat data...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Kondisi per Ekspedisi -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                                <i class="fa-solid fa-tags text-xs"></i>
                            </span>
                            <h4 class="font-bold text-sm text-slate-800">Kondisi per Ekspedisi</h4>
                        </div>
                        <div id="dashConditionContainer" class="divide-y divide-slate-100 max-h-[340px] overflow-y-auto">
                            <div class="p-6 text-center text-slate-400 text-xs">Memuat data...</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions Preview -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <h3 class="font-bold text-sm text-slate-800">Transaksi Terbaru</h3>
                        <button onclick="switchTab('transactions')" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold border border-indigo-200 hover:border-indigo-400 px-3 py-2 rounded-xl transition">
                            Lihat Semua &rarr;
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[11px]">
                                <tr>
                                    <th class="p-3 whitespace-nowrap">Tanggal & Waktu</th>
                                    <th class="p-3 whitespace-nowrap">Invoice</th>
                                    <th class="p-3 whitespace-nowrap">Ekspedisi</th>
                                    <th class="p-3 whitespace-nowrap">Operator</th>
                                    <th class="p-3 whitespace-nowrap">Seller SKU</th>
                                    <th class="p-3 min-w-[200px] max-w-[340px]">Nama Produk</th>
                                    <th class="p-3 whitespace-nowrap">Batch</th>
                                    <th class="p-3 whitespace-nowrap">Exp Date</th>
                                    <th class="p-3 text-center whitespace-nowrap">Qty</th>
                                    <th class="p-3 text-center whitespace-nowrap">Type (Kondisi)</th>
                                    <th class="p-3 text-center whitespace-nowrap">Video</th>
                                    <th class="p-3 text-center whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="previewTransactionsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="12" class="text-center py-6 text-slate-400">Memuat data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: INBOUND UNBOXING & RIWAYAT TRANSAKSI LENGKAP -->
            <div id="tab-transactions" class="tab-content hidden space-y-6">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col lg:flex-row justify-between items-center gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-base text-slate-800">Inbound Unboxing</h3>
                                <span class="bg-indigo-50 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-indigo-200">Video &amp; Audit Trail</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                            <!-- Premium Datepicker (Flatpickr) -->
                            <div class="relative flex items-center">
                                <span class="absolute left-3 text-indigo-600 pointer-events-none text-xs z-10">
                                    <i class="fa-regular fa-calendar-days"></i>
                                </span>
                                <input type="text" id="filterDate" placeholder="Pilih Tanggal / Rentang..." readonly
                                    class="bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs transition w-44 sm:w-56 cursor-pointer">
                                <button type="button" id="btnClearDate" onclick="clearDateFilter()" title="Hapus filter tanggal" class="absolute right-2.5 text-slate-400 hover:text-rose-500 transition text-xs hidden z-10">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                            </div>

                            <!-- Filter Ekspedisi -->
                            <div class="relative">
                                <select id="filterExpedition" onchange="loadTransactions()" class="bg-slate-50 hover:bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="">Semua Ekspedisi</option>
                                </select>
                            </div>

                            <!-- Filter Type (Kondisi) -->
                            <div class="relative">
                                <select id="filterCondition" onchange="loadTransactions()" class="bg-slate-50 hover:bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="">Semua Kondisi</option>
                                    <option value="GOOD">GOOD (Layak)</option>
                                    <option value="RUSAK">RUSAK</option>
                                </select>
                            </div>

                            <!-- Filter Operator -->
                            <div class="relative">
                                <select id="filterOperator" onchange="loadTransactions()" class="bg-slate-50 hover:bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="">Semua Operator</option>
                                </select>
                            </div>

                            <!-- Search Input -->
                            <div class="relative flex items-center">
                                <span class="absolute left-3 text-slate-400 pointer-events-none text-xs">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <input type="text" id="filterSearch" placeholder="Cari invoice/sku..." 
                                    class="border border-slate-300 rounded-xl pl-8 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs w-36 sm:w-44">
                            </div>

                            <button id="btnApplyFilter" onclick="loadTransactions()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-indigo-600/30">
                                <i class="fa-solid fa-filter text-[11px]"></i> Filter
                            </button>
                            <button onclick="exportInboundUnboxingExcel()" title="Download Seluruh Riwayat Inbound Unboxing ke Excel" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/20">
                                <i class="fa-solid fa-file-excel text-[11px]"></i> Download Excel
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[11px]">
                                <tr>
                                    <th class="p-3 whitespace-nowrap">Tanggal & Waktu</th>
                                    <th class="p-3 whitespace-nowrap">Invoice</th>
                                    <th class="p-3 whitespace-nowrap">Ekspedisi</th>
                                    <th class="p-3 whitespace-nowrap">Operator</th>
                                    <th class="p-3 whitespace-nowrap">Seller SKU</th>
                                    <th class="p-3 min-w-[200px] max-w-[340px]">Nama Produk</th>
                                    <th class="p-3 whitespace-nowrap">Batch</th>
                                    <th class="p-3 whitespace-nowrap">Exp Date</th>
                                    <th class="p-3 text-center whitespace-nowrap">Qty</th>
                                    <th class="p-3 text-center whitespace-nowrap">Type (Kondisi)</th>
                                    <th class="p-3 text-center whitespace-nowrap">Video Unboxing</th>
                                    <th class="p-3 text-center whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="12" class="text-center py-8 text-slate-400">Memuat data transaksi unboxing...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: MASTER PRODUK LENGKAP -->
            <div id="tab-products" class="tab-content hidden space-y-6">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Master Data Produk & Barcode</h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Dropdown Filter Toko / Shop -->
                            <div class="relative">
                                <select id="filterProductShop" onchange="filterProductTable()" class="bg-slate-50 hover:bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="">Semua Toko / Shop</option>
                                </select>
                            </div>
                            <!-- Search Input Keyword -->
                            <div class="relative">
                                <input type="text" id="filterProductSearch" onkeyup="filterProductTable()" placeholder="Cari nama/barcode/sku/sap..." class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 w-44 sm:w-60">
                            </div>
                            <button id="btnSyncOcs" onclick="syncProductsFromOCS()" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-2xs">
                                <i class="fa-solid fa-arrows-rotate" id="syncOcsIcon"></i> Tarik Data dari OCS IEG
                            </button>
                            <button onclick="exportProductsExcel()" title="Download Seluruh Master Produk ke Excel" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/20">
                                <i class="fa-solid fa-file-excel text-xs"></i> Download Excel
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[11px]">
                                <tr>
                                    <th class="p-3 w-12 text-center whitespace-nowrap">#</th>
                                    <th class="p-3 whitespace-nowrap">Shop / Toko</th>
                                    <th class="p-3 whitespace-nowrap">Barcode / Seller SKU / SAP Code</th>
                                    <th class="p-3" style="min-width:160px;max-width:260px;width:260px">Nama Produk</th>
                                    <th class="p-3 whitespace-nowrap">Barcode BPOM</th>
                                </tr>
                            </thead>
                            <tbody id="fullProductsTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="5" class="text-center py-12 text-slate-400">
                                        <div class="flex flex-col items-center justify-center space-y-3">
                                            <div class="traffic-loader">
                                                <div class="traffic-ball traffic-ball-red"></div>
                                                <div class="traffic-ball traffic-ball-yellow"></div>
                                                <div class="traffic-ball traffic-ball-green"></div>
                                            </div>
                                            <span class="text-xs font-semibold text-slate-500">Memuat daftar master produk...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: MASTER EKSPEDISI / KURIR -->
            <div id="tab-expeditions" class="tab-content hidden space-y-6">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Master Data Ekspedisi & Kurir</h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                            <input type="text" id="filterExpeditionSearch" oninput="filterExpeditionTable()" placeholder="Cari nama / kode ekspedisi..."
                                class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full sm:w-56">
                            <button onclick="exportExpeditionsExcel()" title="Download Seluruh Data Ekspedisi ke Excel" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shrink-0 shadow-sm shadow-emerald-600/20">
                                <i class="fa-solid fa-file-excel text-xs"></i> Download Excel
                            </button>
                            <button onclick="openAddExpeditionModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shrink-0 shadow-sm">
                                <i class="fa-solid fa-plus-circle"></i> Tambah Ekspedisi
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold">
                                <tr>
                                    <th class="p-3 w-14">#</th>
                                    <th class="p-3">Kode Ekspedisi</th>
                                    <th class="p-3">Nama Ekspedisi</th>
                                    <th class="p-3">Prefix Resi (Auto-Detect)</th>
                                    <th class="p-3 text-center">Status</th>
                                    <th class="p-3 text-center w-32">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="fullExpeditionsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="6" class="text-center py-8 text-slate-400">Memuat data ekspedisi...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: MASTER KONDISI / TYPE -->
            <div id="tab-conditions" class="tab-content hidden space-y-6">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Master Data Type / Kondisi Produk</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Kelola daftar kondisi yang tersedia saat scanning retur</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                            <input type="text" id="filterConditionSearch" oninput="filterConditionTable()" placeholder="Cari kode / nama kondisi..."
                                class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full sm:w-56">
                            <button onclick="openAddConditionModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shrink-0 shadow-sm">
                                <i class="fa-solid fa-plus-circle"></i> Tambah Kondisi
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[11px]">
                                <tr>
                                    <th class="p-3 w-12 text-center">#</th>
                                    <th class="p-3">Kode</th>
                                    <th class="p-3">Nama Kondisi</th>
                                    <th class="p-3">Deskripsi</th>
                                    <th class="p-3 text-center">Warna Badge</th>
                                    <th class="p-3 text-center w-32">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="fullConditionsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="6" class="text-center py-8 text-slate-400">Memuat data kondisi...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 6: KELOLA PENGGUNA (USERS) -->
            <div id="tab-users" class="tab-content hidden space-y-6">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Manajemen Pengguna Sistem</h3>
                        </div>
                        <div class="flex items-center space-x-2 w-full sm:w-auto">
                            <input type="text" id="filterUserSearch" oninput="filterUserTable()" placeholder="Cari nama / username..."
                                class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full sm:w-64">
                            <button onclick="openAddUserModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shrink-0 shadow-sm">
                                <i class="fa-solid fa-user-plus"></i> Tambah Pengguna
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold">
                                <tr>
                                    <th class="p-3 w-14">#</th>
                                    <th class="p-3">Username</th>
                                    <th class="p-3">Nama Lengkap</th>
                                    <th class="p-3 text-center">Role / Wewenang</th>
                                    <th class="p-3 text-center">PIN</th>
                                    <th class="p-3 text-center">Status</th>
                                    <th class="p-3">Tanggal Dibuat</th>
                                    <th class="p-3 text-center w-32">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="fullUsersTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="7" class="text-center py-12 text-slate-400">
                                        <div class="flex flex-col items-center justify-center space-y-3">
                                            <div class="traffic-loader">
                                                <div class="traffic-ball traffic-ball-red"></div>
                                                <div class="traffic-ball traffic-ball-yellow"></div>
                                                <div class="traffic-ball traffic-ball-green"></div>
                                            </div>
                                            <span class="text-xs font-semibold text-slate-500">Memuat data pengguna...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($isSuperAdmin): ?>
            <!-- TAB 6: PEMELIHARAAN SISTEM (MAINTENANCE - SUPERADMIN ONLY) -->
            <div id="tab-maintenance" class="tab-content hidden space-y-6">
                <!-- Header Card -->
                <div class="bg-gradient-to-r from-amber-600 to-amber-700 rounded-2xl p-6 text-white shadow-lg space-y-2">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <span class="bg-white/20 text-white text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">Superadmin Exclusive</span>
                            <h3 class="text-xl font-black tracking-tight mt-1 flex items-center gap-2">
                                <i class="fa-solid fa-screwdriver-wrench"></i> Panel Pemeliharaan Sistem
                            </h3>
                        </div>
                        <div>
                            <span id="maintStatusBadge" class="bg-white text-slate-800 text-xs font-bold px-3.5 py-1.5 rounded-xl shadow-sm flex items-center gap-2">
                                <i class="fa-solid fa-circle-check text-emerald-500" id="maintStatusIcon"></i> <span id="maintStatusText">Memeriksa Status...</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Sisi Kiri (7 Kolom): Maintenance Controls -->
                    <div class="lg:col-span-7 space-y-4">
                        <!-- Switch Maintenance Mode -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-4">
                            <div>
                                <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                    <i class="fa-solid fa-power-off text-amber-500"></i> Mode Pemeliharaan (Maintenance Mode)
                                </h4>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Ketika aktif, operator dan admin biasa tidak dapat mengakses sistem dan diarahkan ke layar maintenance. Superadmin tetap memiliki akses penuh.
                                </p>
                            </div>

                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-lg shrink-0">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                    <div>
                                        <span class="text-xs font-bold text-slate-800 block" id="maintModeTitle">Mode Normal</span>
                                        <span class="text-[11px] text-slate-500" id="maintModeDesc">Sistem dapat diakses secara normal oleh semua user.</span>
                                    </div>
                                </div>
                                <button type="button" id="btnToggleMaint" onclick="toggleMaintenanceMode()" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition shadow-sm shrink-0">
                                    Aktifkan Maintenance
                                </button>
                            </div>
                        </div>

                        <!-- Database Maintenance & Optimization -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-4">
                            <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-database text-indigo-500"></i> Optimasi & Defragmentasi Database
                            </h4>
                            <p class="text-xs text-slate-500">
                                Menjalankan perintah SQL <code>OPTIMIZE TABLE</code> pada semua tabel untuk mempercepat waktu query dan merapikan indeks data.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="optimizeDatabaseTables()" id="btnOptimizeDb" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition flex items-center gap-1.5 shadow-sm">
                                    <i class="fa-solid fa-broom"></i> Optimasi Semua Tabel
                                </button>
                                <button type="button" onclick="cleanTestTransactions()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs px-3.5 py-2.5 rounded-xl transition flex items-center gap-1.5 border border-slate-300">
                                    <i class="fa-solid fa-trash-can"></i> Bersihkan Transaksi Demo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan (5 Kolom): Ringkasan Skema & Server Diagnostics -->
                    <div class="lg:col-span-5 space-y-4">
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-4">
                            <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-server text-emerald-500"></i> Diagnostik Server & Database
                            </h4>
                            <div class="divide-y divide-slate-100 text-xs" id="maintServerStats">
                                <div class="py-2 flex justify-between">
                                    <span class="text-slate-500">Versi PHP</span>
                                    <span class="font-mono font-bold text-slate-800" id="diagPhpVersion"><?= PHP_VERSION ?></span>
                                </div>
                                <div class="py-2 flex justify-between">
                                    <span class="text-slate-500">Host Database</span>
                                    <span class="font-mono font-bold text-slate-800" id="diagDbHost"><?= $db_host ?></span>
                                </div>
                                <div class="py-2 flex justify-between">
                                    <span class="text-slate-500">Nama Database</span>
                                    <span class="font-mono font-bold text-indigo-700" id="diagDbName"><?= $db_name ?></span>
                                </div>
                                <div class="py-2 flex justify-between">
                                    <span class="text-slate-500">Environment</span>
                                    <span class="font-bold text-emerald-600"><?= !empty($is_remote) ? 'InfinityFree Production' : 'Local Server (Active)' ?></span>
                                </div>
                                <div class="py-2 flex justify-between">
                                    <span class="text-slate-500">Zona Waktu</span>
                                    <span class="font-mono text-slate-700">Asia/Jakarta (WIB)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Ringkasan Baris Tabel -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-3">
                            <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-table-list text-purple-500"></i> Jumlah Baris Data (Row Counts)
                            </h4>
                            <div class="grid grid-cols-2 gap-2 text-center" id="maintTableCounts">
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                    <span class="text-[10px] text-slate-500 font-bold block uppercase">Master Produk</span>
                                    <span class="text-base font-black text-slate-800" id="countMasterProducts">-</span>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                    <span class="text-[10px] text-slate-500 font-bold block uppercase">Sesi Transaksi</span>
                                    <span class="text-base font-black text-slate-800" id="countReturnSessions">-</span>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                    <span class="text-[10px] text-slate-500 font-bold block uppercase">Detail Item</span>
                                    <span class="text-base font-black text-slate-800" id="countReturnItems">-</span>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                    <span class="text-[10px] text-slate-500 font-bold block uppercase">Total Users</span>
                                    <span class="text-base font-black text-slate-800" id="countUsers">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODAL TAMBAH PRODUK BARU -->
    <div id="addProductModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-tag"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Tambah Produk Baru</h3>
                    </div>
                </div>
                <button onclick="closeAddProductModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="formAddProduct" onsubmit="submitNewProduct(event)" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nomor Barcode *</label>
                    <input type="text" id="newBarcode" required placeholder="Contoh: 8991006"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nama Produk *</label>
                    <input type="text" id="newName" required placeholder="Contoh: Mouse Wireless Silent Click"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">SKU</label>
                        <input type="text" id="newSku" placeholder="Otomatis jika kosong"
                            class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Satuan</label>
                        <select id="newUnit" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="Pcs">Pcs</option>
                            <option value="Unit">Unit</option>
                            <option value="Box">Box</option>
                            <option value="Set">Set</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Kategori</label>
                    <input type="text" id="newCategory" placeholder="Contoh: Aksesoris Komputer"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" onclick="closeAddProductModal()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" id="btnSaveProduct" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-save"></i> Simpan ke MySQL
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TAMBAH / EDIT EKSPEDISI -->
    <div id="expeditionModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800" id="expeditionModalTitle">Tambah Ekspedisi Baru</h3>
                    </div>
                </div>
                <button onclick="closeExpeditionModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="formExpedition" onsubmit="submitExpedition(event)" class="space-y-3">
                <input type="hidden" id="expeditionId" value="">

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Kode Ekspedisi *</label>
                    <input type="text" id="expeditionCode" required placeholder="Contoh: JNE, JNT, SICEPAT"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono uppercase focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nama Ekspedisi / Kurir *</label>
                    <input type="text" id="expeditionName" required placeholder="Contoh: J&T Express"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Prefix Awalan Resi (Auto-Detect)</label>
                    <input type="text" id="expeditionPrefix" placeholder="Contoh: SPX,SPXID,ID (pisahkan dengan koma)"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono uppercase focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <span class="text-[10px] text-slate-400 block mt-0.5">Sistem akan otomatis memilih ekspedisi ini saat barcode berawalan prefix ini di-scan.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Status Operasional</label>
                    <select id="expeditionStatus" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="ACTIVE">Aktif (Tampil di Scanner)</option>
                        <option value="INACTIVE">Nonaktif</option>
                    </select>
                </div>

                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" onclick="closeExpeditionModal()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" id="btnSaveExpedition" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-save"></i> Simpan Ekspedisi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TAMBAH / EDIT PENGGUNA (USER) -->
    <div id="userModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800" id="userModalTitle">Tambah Pengguna Baru</h3>
                    </div>
                </div>
                <button onclick="closeUserModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="formUser" onsubmit="submitUser(event)" class="space-y-3">
                <input type="hidden" id="userId" value="">

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Username *</label>
                    <input type="text" id="userUsername" required placeholder="Contoh: operator1"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nama Lengkap *</label>
                    <input type="text" id="userName" required placeholder="Contoh: Ahmad Operator"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1" id="userPasswordLabel">Password *</label>
                        <input type="password" id="userPassword" placeholder="Masukkan password"
                            class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span class="text-[10px] text-slate-400 block mt-0.5" id="userPasswordHelp">Untuk login Admin.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">PIN Operator (6 Digit)</label>
                        <input type="text" id="userPin" maxlength="10" placeholder="Contoh: 123456"
                            class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono font-bold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span class="text-[10px] text-slate-400 block mt-0.5">Untuk login Tab Operator.</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Role / Hak Akses *</label>
                        <select id="userRole" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="operator">Operator Inbound</option>
                            <option value="admin">Admin Gudang</option>
                            <?php if ($isSuperAdmin): ?>
                            <option value="superadmin">Superadmin</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Status</label>
                        <select id="userStatus" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="ACTIVE">Aktif</option>
                            <option value="INACTIVE">Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" onclick="closeUserModal()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" id="btnSaveUser" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-save"></i> Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- MODAL DETAIL TRANSAKSI & PEMUTAR VIDEO INBOUND -->
    <div id="transactionDetailModal" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center hidden p-3 md:p-6 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center border-b border-slate-800">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white text-base shadow-sm">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-base tracking-tight" id="modalDetailInvoice">INV-XXXXXX</h3>
                            <span id="modalDetailExpedition" class="bg-indigo-500/20 text-indigo-300 text-[10px] font-semibold px-2 py-0.5 rounded border border-indigo-500/30">Kurir</span>
                        </div>
                        <p class="text-[11px] text-slate-400" id="modalDetailMeta">Operator &bull; Waktu Transaksi</p>
                    </div>
                </div>
                <button onclick="closeDetailModal()" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <!-- Modal Body (Grid: Video di Kiri/Atas, Info & Tabel Produk di Kanan/Bawah) -->
            <div class="p-5 overflow-y-auto flex-1 space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                    
                    <!-- SISI KIRI (6 Kolom): PEMUTAR VIDEO REKAMAN UNBOXING -->
                    <div class="lg:col-span-6 space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-700 font-bold mb-1">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-video text-rose-500"></i> Video Rekaman Inbound:
                            </span>
                            <span id="modalVideoStatusBadge" class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                Rekaman Tersedia
                            </span>
                        </div>

                        <!-- Video Container -->
                        <div class="relative bg-slate-950 rounded-2xl overflow-hidden shadow-inner aspect-video flex items-center justify-center border border-slate-800">
                            <video id="modalVideoPlayer" controls playsinline class="w-full h-full object-contain"></video>
                            
                            <!-- Placeholder Bila Tidak Ada Video -->
                            <div id="modalNoVideo" class="absolute inset-0 bg-slate-900 flex flex-col items-center justify-center text-slate-400 p-4 text-center hidden">
                                <i class="fa-solid fa-video-slash text-4xl text-slate-600 mb-2"></i>
                                <span class="text-xs font-bold text-slate-300">Tidak Ada Video Rekaman</span>
                                <span class="text-[10px] text-slate-500 mt-1 max-w-xs">
                                    Kamera tidak aktif atau izin kamera belum diberikan saat sesi transaksi ini diselesaikan.
                                </span>
                            </div>

                            <!-- Watermark Overlay (Invoice, Ekspedisi, Tanggal & Jam) -->
                            <div id="modalVideoWatermark" class="absolute bottom-2 left-2 right-2 bg-slate-950/85 backdrop-blur-xs text-white text-[11px] px-3 py-1.5 rounded-xl flex items-center justify-between border border-white/10 pointer-events-none transition shadow-lg">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-indigo-300 font-mono" id="watermarkInvoice">INV: -</span>
                                    <span class="text-slate-400">&bull;</span>
                                    <span class="text-sky-300 font-semibold" id="watermarkExpedition">KURIR: -</span>
                                </div>
                                <div class="font-mono text-slate-200 text-[10px] flex items-center gap-1.5" id="watermarkTime">
                                    <i class="fa-regular fa-clock text-indigo-400"></i> -
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between items-center text-[11px] text-slate-500 pt-1">
                            <span id="modalVideoFilename" class="font-mono truncate max-w-[220px] text-[10px] text-slate-400">-</span>
                            <a id="modalDownloadVideoBtn" href="#" download class="text-indigo-600 hover:text-indigo-700 font-semibold flex items-center gap-1">
                                <i class="fa-solid fa-download"></i> Unduh Video
                            </a>
                        </div>
                    </div>

                    <!-- SISI KANAN (6 Kolom): STATISTIK KONDISI PRODUK -->
                    <div class="lg:col-span-6 space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-center">
                                <span class="text-[10px] text-slate-500 block uppercase font-bold">Total Unit</span>
                                <span class="text-lg font-black text-slate-800" id="modalTotalUnit">0</span>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-200 p-2.5 rounded-xl text-center">
                                <span class="text-[10px] text-emerald-600 block uppercase font-bold">Layak (Good)</span>
                                <span class="text-lg font-black text-emerald-600" id="modalTotalGood">0</span>
                            </div>
                            <div class="bg-rose-50 border border-rose-200 p-2.5 rounded-xl text-center">
                                <span class="text-[10px] text-rose-600 block uppercase font-bold">Rusak / Exp</span>
                                <span class="text-lg font-black text-rose-600" id="modalTotalDamaged">0</span>
                            </div>
                        </div>

                        <!-- Catatan Transaksi -->
                        <div class="bg-slate-50 border border-slate-200 p-3 rounded-xl text-xs space-y-1">
                            <span class="text-[10px] font-bold text-slate-500 uppercase">Catatan Operator:</span>
                            <p class="text-slate-700 italic text-[11px]" id="modalNotes">-</p>
                        </div>
                    </div>
                </div>

                <!-- TABEL DETAIL PRODUK YANG DI-RETURN -->
                <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
                    <div class="p-3 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700 flex items-center justify-between">
                        <span>Daftar Produk Dalam Invoice (<span id="modalItemCount">0</span> Item)</span>
                        <span class="text-[10px] text-slate-400 font-normal">Audit Trail Kondisi & Serial Barcode</span>
                    </div>
                    <div class="overflow-x-auto max-h-56">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100/70 text-slate-600 uppercase text-[10px] sticky top-0">
                                <tr>
                                    <th class="p-2.5">Barcode</th>
                                    <th class="p-2.5">Nama Produk</th>
                                    <th class="p-2.5">Batch / Exp</th>
                                    <th class="p-2.5 text-center">Qty</th>
                                    <th class="p-2.5 text-center">Kondisi / Tipe</th>
                                </tr>
                            </thead>
                            <tbody id="modalItemsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="5" class="text-center py-4 text-slate-400">Memuat rincian produk...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-3.5 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeDetailModal()" class="bg-slate-800 hover:bg-slate-700 text-white px-5 py-2 rounded-xl text-xs font-semibold transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: TAMBAH / EDIT KONDISI -->
    <div id="modalCondition" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 animate-in fade-in zoom-in duration-200">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600/10 flex items-center justify-center">
                        <i class="fa-solid fa-tag text-indigo-600 text-sm"></i>
                    </div>
                    <h3 id="conditionModalTitle" class="font-bold text-slate-800 text-sm">Tambah Kondisi Baru</h3>
                </div>
                <button onclick="closeConditionModal()" class="text-slate-400 hover:text-slate-600 transition p-1 rounded-lg hover:bg-slate-100">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <input type="hidden" id="conditionId" value="">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kode <span class="text-red-500">*</span></label>
                        <input type="text" id="conditionCode" placeholder="cth: GOOD, DAMAGED" maxlength="50"
                            class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono uppercase">
                        <p class="text-[10px] text-slate-400 mt-1">Huruf kapital, tanpa spasi</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Warna Badge <span class="text-red-500">*</span></label>
                        <select id="conditionColor" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="emerald">🟢 Hijau (Emerald)</option>
                            <option value="red">🔴 Merah (Red)</option>
                            <option value="amber">🟡 Kuning (Amber)</option>
                            <option value="orange">🟠 Oranye (Orange)</option>
                            <option value="purple">🟣 Ungu (Purple)</option>
                            <option value="blue">🔵 Biru (Blue)</option>
                            <option value="slate">⚫ Abu-abu (Slate)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kondisi <span class="text-red-500">*</span></label>
                    <input type="text" id="conditionName" placeholder="cth: Baik / Good"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi</label>
                    <textarea id="conditionDesc" rows="2" placeholder="Penjelasan singkat tentang kondisi ini..."
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Urutan Tampil</label>
                    <input type="number" id="conditionSort" value="0" min="0" max="999"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 w-28">
                    <p class="text-[10px] text-slate-400 mt-1">Angka kecil ditampilkan lebih dahulu</p>
                </div>
            </div>
            <div class="p-4 border-t border-slate-100 flex justify-end gap-2">
                <button onclick="closeConditionModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition">
                    Batal
                </button>
                <button onclick="saveCondition()" id="btnSaveCondition" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-5 py-2 rounded-xl font-bold transition flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Kondisi
                </button>
            </div>
        </div>
    </div>

    <!-- GLOBAL LOADING OVERLAY (BOLA-BOLA MERAH, KUNING, HIJAU) -->
    <div id="globalLoadingOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4 transition-all">
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-sm w-full shadow-2xl flex flex-col items-center text-center space-y-4 border border-slate-100 animate-in fade-in zoom-in duration-200">
            <div class="traffic-loader traffic-loader-lg py-2">
                <div class="traffic-ball traffic-ball-red"></div>
                <div class="traffic-ball traffic-ball-yellow"></div>
                <div class="traffic-ball traffic-ball-green"></div>
            </div>
            <div class="space-y-1">
                <h4 class="font-bold text-base text-slate-800" id="globalLoadingTitle">Memuat Data...</h4>
                <p class="text-xs text-slate-500 leading-relaxed" id="globalLoadingDesc">Mohon tunggu sebentar, sistem sedang memproses data.</p>
            </div>
        </div>
    </div>

    <!-- SheetJS (Official Native XLSX Generator - 100% Bebas Corrupt) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/id.js"></script>
    <script src="assets/js/admin.js?v=<?= file_exists(__DIR__ . '/assets/js/admin.js') ? filemtime(__DIR__ . '/assets/css/custom.css') : time() ?>"></script>
</body>
</html>
