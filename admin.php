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
    <style>
        @media print {
            body * { visibility: hidden !important; }
            #printableReceivingReceiptArea, #printableReceivingReceiptArea * { visibility: visible !important; }
            #printableReceivingReceiptArea {
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                background: white !important;
                color: black !important;
                padding: 24px !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            .no-print { display: none !important; }
        }
    </style>
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

            <!-- MENU BARU: RECEIVING INBOUND (PENERIMAAN EKSPEDISI) -->
            <button onclick="switchTab('receiving')" id="nav-receiving" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-truck-ramp-box w-5 text-center text-emerald-400"></i>
                <span>Receiving Inbound</span>
            </button>

            <button onclick="switchTab('transactions')" id="nav-transactions" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-box-open w-5 text-center"></i>
                <span>Inbound Unboxing</span>
            </button>

            <!-- MENU BARU: PUSAT KLAIM & BANDING (CLAIM DOSSIER) -->
            <button onclick="switchTab('claims')" id="nav-claims" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-shield-halved w-5 text-center text-amber-400"></i>
                <span>Pusat Klaim & Banding</span>
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
                        <button onclick="refreshDashboardMetrics()" title="Refresh data metrik dashboard dari backend" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 border border-slate-300 shadow-2xs shrink-0">
                            <i class="fa-solid fa-rotate text-indigo-600"></i> Refresh
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
            </div>

            <!-- TAB: RECEIVING INBOUND (PENERIMAAN PAKET EKSPEDISI) -->
            <div id="tab-receiving" class="tab-content hidden space-y-6">
                <!-- Filter & Header Bar -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col lg:flex-row justify-between items-center gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-base text-slate-800">Receiving Inbound Ekspedisi</h3>
                                <span class="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-200">Serah Terima Paket</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">Daftar tanda terima dan serah terima paket dari kurir ekspedisi sebelum unboxing</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                            <!-- Filter Tanggal (Flatpickr) -->
                            <div class="relative flex items-center">
                                <span class="absolute left-3 text-emerald-600 pointer-events-none text-xs z-10">
                                    <i class="fa-regular fa-calendar-days"></i>
                                </span>
                                <input type="text" id="filterReceivingDate" placeholder="Pilih Tanggal / Rentang..." readonly
                                    class="bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs transition w-44 sm:w-56 cursor-pointer">
                                <button type="button" id="btnClearReceivingDate" onclick="clearReceivingDateFilter()" title="Hapus filter tanggal" class="absolute right-2.5 text-slate-400 hover:text-rose-500 transition text-xs hidden z-10">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                            </div>

                            <!-- Filter Ekspedisi -->
                            <select id="filterReceivingExpedition" onchange="loadReceivingData()"
                                class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs transition">
                                <option value="">Semua Ekspedisi</option>
                            </select>

                            <!-- Search Input -->
                            <div class="relative flex-1 sm:w-52">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-search"></i>
                                </span>
                                <input type="text" id="searchReceivingInput" placeholder="Cari No. RCV / Kurir..."
                                    class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs transition">
                            </div>

                            <!-- Refresh Button -->
                            <button onclick="loadReceivingData(true)" title="Refresh data receiving"
                                class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold border border-slate-300 transition flex items-center gap-1.5 shadow-2xs">
                                <i class="fa-solid fa-arrows-rotate text-emerald-600"></i> Refresh
                            </button>

                            <!-- Export Excel -->
                            <button onclick="exportReceivingExcel()" title="Export data ke Excel"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/20">
                                <i class="fa-solid fa-file-excel"></i> Export Excel
                            </button>
                        </div>
                    </div>

                    <!-- Summary Mini Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 p-4 bg-slate-50/60 border-b border-slate-200">
                        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm shrink-0">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase block">Total Penerimaan</span>
                                <span id="summaryReceivingTotalBatches" class="font-black text-base text-slate-800">0</span>
                                <span class="text-[10px] text-slate-500"> Sesi</span>
                            </div>
                        </div>

                        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm shrink-0">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase block">Total Paket Diterima</span>
                                <span id="summaryReceivingTotalPackages" class="font-black text-base text-indigo-600">0</span>
                                <span class="text-[10px] text-slate-500"> Paket/Resi</span>
                            </div>
                        </div>

                        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs flex items-center gap-3 col-span-2 md:col-span-1">
                            <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-sm shrink-0">
                                <i class="fa-solid fa-truck-fast"></i>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase block">Ekspedisi Aktif</span>
                                <span id="summaryReceivingTotalExpeditions" class="font-black text-base text-slate-800">0</span>
                                <span class="text-[10px] text-slate-500"> Ekspedisi</span>
                            </div>
                        </div>
                    </div>

                    <!-- Table Data -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="py-3 px-4">#</th>
                                    <th class="py-3 px-4">No. Tanda Terima</th>
                                    <th class="py-3 px-4">Ekspedisi</th>
                                    <th class="py-3 px-4">Driver / Kurir</th>
                                    <th class="py-3 px-4 text-center">Total Paket</th>
                                    <th class="py-3 px-4">Operator Penerima</th>
                                    <th class="py-3 px-4">Waktu Penerimaan</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="receivingTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="8" class="text-center py-12 text-slate-400">
                                        <i class="fa-solid fa-truck-ramp-box text-3xl mb-2 text-slate-300 block"></i>
                                        Memuat data receiving inbound...
                                    </td>
                                </tr>
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

            <!-- TAB BARU: PUSAT KLAIM & BANDING (CLAIM DOSSIER) -->
            <div id="tab-claims" class="tab-content hidden space-y-6">
                <!-- Search & Quick Actions -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-5">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <span class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-base shrink-0">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </span>
                                <div>
                                    <h3 class="font-bold text-base text-slate-800">Pusat Klaim & Banding Ekspedisi</h3>
                                </div>
                            </div>
                        </div>

                        <!-- Form Pencarian Resi / Order -->
                        <form id="formClaimLookup" onsubmit="executeClaimLookup(event)" class="w-full lg:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <div class="relative flex-1 sm:w-80">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <input type="text" id="claimSearchInput" placeholder="Masukkan No. Resi atau Order ID / Invoice..." required
                                    class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500 transition shadow-2xs">
                            </div>
                            <button type="submit" id="btnClaimSearch" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm shadow-amber-500/20 shrink-0">
                                <i class="fa-solid fa-search"></i>
                                <span>Cari Bukti</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Hasil Dossier Klaim (Muncul setelah pencarian) -->
                <div id="claimResultContainer" class="hidden space-y-6">
                    <!-- Skor Kelengkapan Bukti -->
                    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 rounded-2xl p-5 text-white shadow-md">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <span class="px-2 py-0.5 rounded-md bg-amber-400/20 text-amber-300 font-bold text-[10px] uppercase tracking-wider">Berkas Klaim Resmi</span>
                                    <span id="claimMarketplaceBadge" class="px-2 py-0.5 rounded-md bg-white/10 text-white font-bold text-[10px] uppercase">Marketplace</span>
                                    <span id="claimPriceBadge" class="px-2.5 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300 font-bold text-xs border border-emerald-400/30 flex items-center gap-1.5 shadow-xs">
                                        <i class="fa-solid fa-money-bill-wave text-emerald-400 text-xs"></i> Nilai Paket: <span id="claimPriceText" class="font-black text-white">Rp -</span>
                                    </span>
                                    <span id="claimTotalClaimBadge" class="px-2.5 py-0.5 rounded-md bg-amber-500/20 text-amber-300 font-bold text-xs border border-amber-400/30 flex items-center gap-1.5 shadow-xs">
                                        <i class="fa-solid fa-shield-halved text-amber-400 text-xs"></i> Tuntutan Klaim: <span id="claimTotalClaimText" class="font-black text-white">Rp -</span>
                                    </span>
                                </div>
                                <h4 id="claimOrderTitle" class="text-lg font-black tracking-tight">Order # - Resi #</h4>
                                <p id="claimShopSubtitle" class="text-xs text-slate-300">Toko: - | Ekspedisi: -</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <button onclick="printClaimDossier()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                                    <i class="fa-solid fa-print"></i> Cetak Berkas Klaim (PDF)
                                </button>
                                <button onclick="copyClaimPacketSummary()" class="bg-white/10 hover:bg-white/20 text-white px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2">
                                    <i class="fa-solid fa-copy"></i> Salin Ringkasan
                                </button>
                            </div>
                        </div>

                        <!-- 4 Indikator Kelengkapan Checklist -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 pt-4 border-t border-white/10 text-xs">
                            <div id="checkOrder" class="flex items-center gap-2 p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <i class="fa-solid fa-circle-check text-emerald-400 text-base" id="iconCheckOrder"></i>
                                <div>
                                    <span class="font-bold block text-white text-[11px]">Invoice & Resi</span>
                                    <span id="labelCheckOrder" class="text-[10px] text-slate-300">Terverifikasi</span>
                                </div>
                            </div>
                            <div id="checkReception" class="flex items-center gap-2 p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <i class="fa-solid fa-circle-check text-emerald-400 text-base" id="iconCheckRec"></i>
                                <div>
                                    <span class="font-bold block text-white text-[11px]">Tanda Terima Kurir</span>
                                    <span id="labelCheckRec" class="text-[10px] text-slate-300">Diterima Fisik</span>
                                </div>
                            </div>
                            <div id="checkUnboxVideo" class="flex items-center gap-2 p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <i class="fa-solid fa-circle-check text-emerald-400 text-base" id="iconCheckUnbox"></i>
                                <div>
                                    <span class="font-bold block text-white text-[11px]">Video Unboxing Retur</span>
                                    <span id="labelCheckUnbox" class="text-[10px] text-slate-300">Terekam Lengkap</span>
                                </div>
                            </div>
                            <div id="checkPhotos" class="flex items-center gap-2 p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <i class="fa-solid fa-circle-check text-emerald-400 text-base" id="iconCheckPhotos"></i>
                                <div>
                                    <span class="font-bold block text-white text-[11px]">Foto Bukti Barang</span>
                                    <span id="labelCheckPhotos" class="text-[10px] text-slate-300">Tersedia</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Banner Status Kelayakan Klaim (Hanya Paket Rusak / Bukan Good) -->
                    <div id="claimEligibilityBanner" class="p-4 rounded-2xl border text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition">
                        <div class="flex items-center gap-3">
                            <span id="claimEligibilityIcon" class="w-9 h-9 rounded-xl flex items-center justify-center text-base shrink-0">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </span>
                            <div>
                                <b id="claimEligibilityTitle" class="text-xs font-black block">Status Kelayakan Klaim</b>
                                <span id="claimEligibilitySubtitle" class="text-[11px]">-</span>
                            </div>
                        </div>
                        <span id="claimEligibilityTag" class="px-3 py-1 rounded-lg text-white font-black text-[10px] uppercase tracking-wider shrink-0 self-start sm:self-center">Status</span>
                    </div>

                    <!-- Media Bukti: Video Unboxing & Galeri Foto Bukti (Tanpa Video Packing) -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- Video Unboxing Retur (Saat Diterima Kembali) -->
                        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden flex flex-col">
                            <div class="p-3 bg-slate-900 text-white flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span class="font-bold">1. Rekaman Video Unboxing (Retur di Gudang)</span>
                                </div>
                                <span class="text-[10px] text-slate-400">Stasiun Inbound Unboxing</span>
                            </div>
                            <div class="relative bg-black aspect-video flex items-center justify-center">
                                <video id="playerUnboxingVideo" controls class="w-full h-full object-contain hidden"></video>
                                <div id="noUnboxingVideoPlaceholder" class="text-center p-6 text-slate-400">
                                    <i class="fa-solid fa-video-slash text-3xl mb-2 text-slate-600 block"></i>
                                    <span class="text-xs">Video unboxing belum tersedia atau belum direkam di stasiun retur.</span>
                                </div>
                            </div>
                            <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-[11px] text-slate-600">
                                <span>Operator: <b id="unboxingOperatorText" class="text-slate-800">-</b></span>
                                <span id="unboxingTimestampText" class="text-slate-500">-</span>
                            </div>
                        </div>

                        <!-- Galeri Foto Bukti Retur & Serah Terima -->
                        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden flex flex-col">
                            <div class="p-3 bg-slate-900 text-white flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                    <span class="font-bold">2. Galeri Foto Bukti (Paket, Produk & Serah Terima)</span>
                                </div>
                                <span id="claimPhotoCountBadge" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-bold">0 Foto</span>
                            </div>
                            <div class="flex-1 p-3 bg-slate-950/5 flex flex-col justify-center min-h-[220px]">
                                <div id="claimPhotoGallery" class="hidden grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-[280px] overflow-y-auto pr-1">
                                    <!-- Thumbnail foto bukti diinject via JS -->
                                </div>
                                <div id="noPhotosPlaceholder" class="text-center p-6 text-slate-400">
                                    <i class="fa-solid fa-images text-3xl mb-2 text-slate-300 block"></i>
                                    <span class="text-xs">Belum ada foto bukti tersimpan untuk paket / resi ini.</span>
                                </div>
                            </div>
                            <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-[11px] text-slate-600">
                                <span><i class="fa-solid fa-circle-info text-indigo-500 mr-1"></i> Klik foto untuk memperbesar & unduh</span>
                                <span id="claimPhotoTotalText" class="text-slate-500 font-semibold">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Rincian Data Komparasi Klaim -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- 1. BIAYA & FINANSIAL KLAIM -->
                        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-4 space-y-3 flex flex-col justify-between">
                            <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-800">
                                <i class="fa-solid fa-money-bill-transfer text-emerald-600"></i>
                                <span>Rincian Biaya & Tuntutan Klaim</span>
                            </div>
                            <div class="space-y-2.5 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Nilai / Harga Barang (NMV OCS)</span>
                                    <span id="detailPackagePrice" class="font-black text-emerald-700 text-base">Rp -</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Biaya Kirim / Ongkos Ekspedisi</span>
                                    <span id="detailShippingFee" class="font-bold text-slate-800">Rp -</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200">
                                    <span class="text-[10px] text-emerald-800 uppercase font-black block">Total Estimasi Tuntutan Ganti Rugi</span>
                                    <span id="detailTotalClaim" class="font-black text-emerald-700 text-base">Rp -</span>
                                    <span class="text-[10px] text-emerald-600 block mt-0.5">Nilai barang yang diajukan banding ke ekspedisi</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Nilai Gross GMV</span>
                                    <span id="detailGmvPrice" class="text-slate-600 font-medium">Rp -</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. DETAIL EKSPEDISI -->
                        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-4 space-y-3 flex flex-col justify-between">
                            <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-800">
                                <i class="fa-solid fa-truck-fast text-indigo-600"></i>
                                <span>Detail Ekspedisi & Serah Terima</span>
                            </div>
                            <div class="grid grid-cols-1 gap-2.5 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Jasa Ekspedisi Pengiriman</span>
                                    <span id="detailShippingProvider" class="font-bold text-indigo-700 text-sm">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Nomor Resi Paket (AWB)</span>
                                    <span id="detailTrackingNumber" class="font-mono font-bold text-slate-800 text-xs bg-slate-100 px-2 py-1 rounded inline-block">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Kurir / Driver Pengantar (Receiving)</span>
                                    <span id="detailCourier" class="font-semibold text-slate-700">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">No. Tanda Terima Ekspedisi</span>
                                    <span id="detailReceiptNo" class="font-mono font-bold text-emerald-700">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Waktu Fisik Diterima di Gudang</span>
                                    <span id="detailReceivedAt" class="text-slate-600">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. DETAIL PAKET & UNBOXING -->
                        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-4 space-y-3 flex flex-col justify-between">
                            <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-800">
                                <i class="fa-solid fa-boxes-packing text-amber-600"></i>
                                <span>Detail Paket & Hasil Unboxing</span>
                            </div>
                            <div class="space-y-2.5 text-xs">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <span class="text-[10px] text-slate-400 uppercase font-bold block">No. Order / Invoice</span>
                                        <span id="detailOrderId" class="font-bold text-slate-800 text-xs">-</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-slate-400 uppercase font-bold block">Marketplace / Toko</span>
                                        <span id="detailShopName" class="font-semibold text-slate-700 text-xs truncate block">-</span>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Status Unboxing Retur</span>
                                    <span id="detailUnboxStatus" class="font-bold text-slate-800">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Rincian Produk di Paket</span>
                                    <span id="detailOrderProductName" class="text-slate-700 font-medium block bg-slate-50 p-2 rounded-lg border border-slate-200 text-xs max-h-16 overflow-y-auto">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Kondisi Barang & Alasan Retur</span>
                                    <div id="detailConditionNotes" class="font-semibold text-slate-800 bg-amber-50 p-2 rounded-lg border border-amber-200 text-xs max-h-20 overflow-y-auto">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Placeholder Belum Ada Pencarian -->
                <div id="claimEmptyPlaceholder" class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-500 mx-auto flex items-center justify-center text-2xl mb-3 shadow-inner">
                        <i class="fa-solid fa-magnifying-glass-location"></i>
                    </div>
                    <h4 class="font-bold text-base text-slate-800">Siap Mencari Bukti Klaim & Banding</h4>
                </div>

                <!-- Tabel Kandidat Paket Layak Klaim (Kondisi BUKAN GOOD) -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-sm">
                                <i class="fa-solid fa-box-tissue"></i>
                            </span>
                            <div>
                                <h4 class="font-bold text-sm text-slate-800">Daftar Paket Rusak / Layak Klaim</h4>
                            </div>
                        </div>
                        <button onclick="loadClaimCandidates(true)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-slate-300 shadow-2xs">
                            <i class="fa-solid fa-arrows-rotate text-amber-600" id="iconRefreshCandidates"></i> Refresh Daftar
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="py-3 px-4">#</th>
                                    <th class="py-3 px-4">No. Resi / Invoice</th>
                                    <th class="py-3 px-4">Ekspedisi</th>
                                    <th class="py-3 px-4">Waktu Unboxing</th>
                                    <th class="py-3 px-4 text-center">Qty Rusak</th>
                                    <th class="py-3 px-4">Kondisi / Alasan Rusak</th>
                                    <th class="py-3 px-4 text-center">Video Unbox</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="claimCandidatesTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-slate-400">
                                        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat daftar paket rusak...
                                    </td>
                                </tr>
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

    <!-- ============================================================== -->
    <!-- MODAL BUKTI SERAH TERIMA PAKET (RECEIVING INBOUND)            -->
    <!-- ============================================================== -->
    <div id="modalReceivingReceipt" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <!-- Header Modal -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-file-circle-check text-emerald-400 text-base"></i>
                    <h3 class="font-bold text-sm">Bukti Serah Terima Paket</h3>
                </div>
                <button onclick="closeReceivingReceiptModal()" class="text-slate-400 hover:text-white text-base">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Content Area (Printable) -->
            <div id="printableReceivingReceiptArea" class="p-5 overflow-y-auto space-y-4 flex-1 text-xs">
                <!-- Header Slip -->
                <div class="text-center border-b border-dashed border-slate-300 pb-3">
                    <h2 class="font-black text-base tracking-tight text-slate-900">PT. INDO EXPRESS GLOBAL</h2>
                    <p class="text-[10px] text-slate-500 font-medium">INBOUND WAREHOUSE RETURN RECEPTION</p>
                    <p id="adminSlipReceiptNo" class="font-mono font-bold text-xs text-emerald-600 mt-1">-</p>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-2 gap-2 text-[11px] bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Ekspedisi</span>
                        <span id="adminSlipExpedition" class="font-bold text-slate-800 text-xs">-</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Waktu Penerimaan</span>
                        <span id="adminSlipDateTime" class="font-semibold text-slate-700">-</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Driver / Kurir</span>
                        <span id="adminSlipCourier" class="font-semibold text-slate-700">-</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Operator Penerima</span>
                        <span id="adminSlipOperator" class="font-bold text-slate-800">-</span>
                    </div>
                </div>

                <!-- Total Count Banner -->
                <div class="bg-emerald-500 text-white rounded-xl p-3 text-center">
                    <span class="text-[10px] uppercase font-bold opacity-80 block">Jumlah Paket Diterima</span>
                    <span id="adminSlipTotalPackages" class="font-black text-2xl">0</span>
                    <span class="text-xs font-semibold"> Paket</span>
                </div>

                <!-- Daftar Resi Paket -->
                <div>
                    <h4 class="font-bold text-slate-700 mb-1.5 text-[11px] uppercase">Rincian Nomor Resi / Barcode:</h4>
                    <div id="adminSlipPackageList" class="bg-slate-50 rounded-xl p-3 max-h-48 overflow-y-auto space-y-1 font-mono text-[11px] border border-slate-200">
                        <!-- List Resi -->
                    </div>
                </div>

                <!-- Tanda Tangan Serah Terima (Untuk Cetak Fisik) -->
                <div class="grid grid-cols-2 gap-4 text-center pt-4 border-t border-dashed border-slate-300">
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold mb-10">Yang Menyerahkan (Kurir)</p>
                        <p class="text-[10px] font-bold text-slate-700 border-t border-slate-300 mx-4 pt-1">( ........................... )</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold mb-10">Yang Menerima (Gudang)</p>
                        <p id="adminSlipSignOperator" class="text-[10px] font-bold text-slate-700 border-t border-slate-300 mx-4 pt-1">-</p>
                    </div>
                </div>
            </div>

            <!-- Footer Modal Actions -->
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2 shrink-0 no-print">
                <button onclick="window.print()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-print"></i> Cetak Bukti
                </button>
                <button onclick="closeReceivingReceiptModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DAFTAR RESI PAKET LENGKAP -->
    <div id="modalReceivingPackages" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-barcode text-indigo-400 text-base"></i>
                    <h3 class="font-bold text-sm" id="pkgModalTitle">Daftar Resi Paket</h3>
                </div>
                <button onclick="closeReceivingPackagesModal()" class="text-slate-400 hover:text-white text-base">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-4 overflow-y-auto space-y-2 flex-1 text-xs">
                <div class="flex justify-between items-center text-slate-500 text-[11px] mb-1">
                    <span>Total: <b id="pkgModalTotal" class="text-slate-800">0</b> Paket</span>
                    <button onclick="copyAllReceivingBarcodes()" class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1">
                        <i class="fa-regular fa-copy"></i> Salin Semua
                    </button>
                </div>
                <div id="pkgModalList" class="divide-y divide-slate-100 font-mono text-xs max-h-80 overflow-y-auto border border-slate-200 rounded-xl bg-slate-50 p-2"></div>
            </div>
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button onclick="closeReceivingPackagesModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL PREVIEW FOTO BUKTI KLAIM (LIGHTBOX) -->
    <div id="claimPhotoModal" class="hidden fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
        <div class="relative bg-slate-900 border border-white/10 rounded-3xl max-w-4xl w-full p-4 flex flex-col max-h-[92vh] shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-white/10 text-white">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <h3 id="claimPhotoModalTitle" class="text-xs font-bold font-mono tracking-wide">Foto Bukti Retur</h3>
                </div>
                <button onclick="closeClaimPhotoModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
            <div class="flex-1 flex items-center justify-center p-2 min-h-0 overflow-hidden my-2">
                <img id="claimPhotoModalImg" src="" alt="Preview Bukti Paket" class="max-h-[70vh] w-auto max-w-full object-contain rounded-xl shadow-2xl border border-white/10">
            </div>
            <div class="flex items-center justify-between pt-3 border-t border-white/10">
                <a id="btnDownloadClaimPhoto" href="#" download="foto_bukti_klaim.jpg" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition shadow-sm">
                    <i class="fa-solid fa-download"></i> Unduh Foto Bukti
                </a>
                <button onclick="closeClaimPhotoModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-bold text-xs transition">
                    Tutup
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
    <!-- Toast Premium Notification -->
    <script src="assets/js/toast.js?v=<?= file_exists(__DIR__ . '/assets/js/toast.js') ? filemtime(__DIR__ . '/assets/js/toast.js') : time() ?>"></script>
    <script src="assets/js/admin.js?v=<?= file_exists(__DIR__ . '/assets/js/admin.js') ? filemtime(__DIR__ . '/assets/js/admin.js') : time() ?>"></script>
</body>
</html>
