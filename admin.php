<?php
require_once __DIR__ . '/config.php';
$currentUser = getSessionUser();
$user = requireLogin(['admin', 'superadmin', 'management', 'accounting', 'operator_reporting', 'customer_service']);

// Normalisasi role pengguna agar toleran variasi string ('super admin', 'superadmin') dan superadmin mendapat FULL ACCESS
$rawRole = strtolower(trim(str_replace([' ', '_', '-'], '', $user['role'] ?? '')));
$isSuperAdmin = ($rawRole === 'superadmin');
$isAdmin = ($rawRole === 'admin' || $isSuperAdmin);
$isManagement = ($rawRole === 'management' || $isSuperAdmin);
$isAccounting = ($rawRole === 'accounting' || $isSuperAdmin);
$isReporting = ($rawRole === 'operatorreporting' || $rawRole === 'operator_reporting' || $isSuperAdmin || $isAdmin);
$isCS = ($rawRole === 'customerservice' || $rawRole === 'customer_service' || $isSuperAdmin || $isAdmin);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Inbound Return</title>
    <?php
    $appBaseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $appBaseHref = ($appBaseDir === '' || $appBaseDir === '/') ? '/' : ($appBaseDir . '/');
    ?>
    <base href="<?= htmlspecialchars($appBaseHref) ?>">
    <script>
        window.APP_BASE_URL = <?= json_encode($appBaseHref) ?>;
        window.CURRENT_USER_ROLE = <?= json_encode($rawRole) ?>;
        window.IS_SUPER_ADMIN = <?= $isSuperAdmin ? 'true' : 'false' ?>;
        window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        window.IS_MANAGEMENT = <?= $isManagement ? 'true' : 'false' ?>;
        window.IS_ACCOUNTING = <?= $isAccounting ? 'true' : 'false' ?>;
        window.IS_REPORTING = <?= $isReporting ? 'true' : 'false' ?>;
        window.IS_CS = <?= $isCS ? 'true' : 'false' ?>;
    </script>
    <!-- Favicon Huruf D Warna Hijau -->
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/image/favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/image/favicon.png?v=2">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico?v=2">
    <link rel="apple-touch-icon" href="assets/image/favicon.png?v=2">
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
            @page {
                size: A4 portrait;
                margin: 8mm 10mm 10mm 10mm;
            }
            html, body {
                height: auto !important;
                min-height: auto !important;
                overflow: visible !important;
                background: #ffffff !important;
                color: #0f172a !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block !important;
            }
            /* Hilangkan seluruh elemen halaman di luar modal cetak agar tidak memakan ruang / lembar kosong */
            body > *:not(#modalReceivingReceipt):not(#modalClaimDetail):not(#modalCollectiveClaimInvoice) {
                display: none !important;
            }
            #modalReceivingReceipt {
                display: block !important;
                position: static !important;
                inset: auto !important;
                width: 100% !important;
                height: auto !important;
                min-height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                padding: 0 !important;
                margin: 0 !important;
                background: transparent !important;
                backdrop-filter: none !important;
                border: none !important;
                box-shadow: none !important;
                z-index: auto !important;
            }
            #modalReceivingReceipt > div {
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                display: block !important;
                background: transparent !important;
            }
            #modalReceivingReceipt .no-print,
            #modalReceivingReceipt > div > div:first-child {
                display: none !important;
            }
            #printableReceivingReceiptArea {
                position: static !important;
                left: auto !important;
                top: auto !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                max-height: none !important;
                background: white !important;
                color: #0f172a !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
                overflow: visible !important;
                display: block !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #printableReceivingReceiptArea img {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #printableReceivingReceiptArea .sj-header {
                border-bottom: 2px solid #0f172a !important;
                padding-bottom: 6px !important;
                margin-bottom: 8px !important;
            }
            #printableReceivingReceiptArea .sj-info-table {
                width: 100% !important;
                border-collapse: collapse !important;
                border: 1px solid #cbd5e1 !important;
                margin-bottom: 6px !important;
                font-size: 8pt !important;
            }
            #printableReceivingReceiptArea .sj-info-table td {
                padding: 3px 6px !important;
                border: 1px solid #cbd5e1 !important;
            }
            #printableReceivingReceiptArea #adminSlipPackageList {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 3px 6px !important;
                width: 100% !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                background-color: transparent !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                box-sizing: border-box !important;
            }
            #printableReceivingReceiptArea #adminSlipPackageList > div {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                border: 1px solid #cbd5e1 !important;
                background-color: #f8fafc !important;
                padding: 2.5px 6px !important;
                font-size: 7.5pt !important;
                border-radius: 6px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #printableReceivingReceiptArea #adminSlipPackageList span,
            #printableReceivingReceiptArea #adminSlipPackageList div {
                text-overflow: clip !important;
                white-space: normal !important;
                word-break: break-all !important;
                overflow: visible !important;
            }
            #printableReceivingReceiptArea #adminSlipSackBreakdownSection {
                background-color: #fffbeb !important;
                border: 1px solid #fde68a !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                padding: 4px 8px !important;
                margin-bottom: 6px !important;
            }
            #printableReceivingReceiptArea #adminSlipSackBreakdownList {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 4px !important;
            }
            #printableReceivingReceiptArea #adminSlipSackBreakdownList > div {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                border: 1px solid #fcd34d !important;
                background-color: #ffffff !important;
                padding: 2px 5px !important;
                font-size: 7.5pt !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #printableReceivingReceiptArea .total-banner {
                background: #059669 !important;
                color: #ffffff !important;
                padding: 5px 10px !important;
                border-radius: 6px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                margin-bottom: 6px !important;
            }
            #printableReceivingReceiptArea .signatures-box {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                margin-top: 10px !important;
                padding-top: 6px !important;
                border-top: 1px solid #94a3b8 !important;
            }
            ::-webkit-scrollbar {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
            }
            * {
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 flex overflow-x-hidden">

    <!-- Mobile Backdrop -->
    <div id="sidebarBackdrop" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-40 lg:hidden hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-white text-slate-700 flex flex-col shrink-0 fixed inset-y-0 left-0 z-50 lg:static transition-transform duration-300 transform -translate-x-full lg:translate-x-0 shadow-xl lg:shadow-none border-r border-slate-200">
        
        <!-- Sidebar Brand -->
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-200/80 p-1.5 flex items-center justify-center shadow-xs shrink-0">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
                </div>
                <div>
                    <h1 class="font-bold text-slate-900 text-base leading-tight tracking-tight">Return Inbound</h1>
                    <p class="text-[10px] text-slate-400 font-medium">IEG Warehouse System</p>
                </div>
            </div>
            <button id="btnCloseSidebar" class="lg:hidden text-slate-400 hover:text-slate-700 p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Sidebar Navigation -->
        <div class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
            <div class="px-3 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Menu Navigasi</div>

            <?php if ($isAdmin || $isManagement || $isReporting): ?>
            <button onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition text-white bg-indigo-600 shadow-sm shadow-indigo-600/30">
                <i class="fa-solid fa-gauge-high w-5 text-center text-indigo-100"></i>
                <span>Dashboard Overview</span>
            </button>
            <?php endif; ?>

            <!-- MENU: RECEIVING INBOUND (PENERIMAAN EKSPEDISI) -->
            <?php if ($isAdmin): ?>
            <button onclick="switchTab('receiving')" id="nav-receiving" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-truck-ramp-box w-5 text-center text-emerald-600"></i>
                <span>Receiving Inbound</span>
            </button>
            <?php endif; ?>

            <?php if ($isAdmin || $isCS): ?>
            <button onclick="switchTab('transactions')" id="nav-transactions" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-box-open w-5 text-center text-indigo-500"></i>
                <span>Inbound Unboxing</span>
            </button>
            <?php endif; ?>

            <!-- MENU BARU: AGING RETURN (MONITORING DURASI RECEIVING KE UNBOXING) -->
            <?php if ($isAdmin || $isManagement || $isReporting || $isAccounting || $isCS): ?>
            <button onclick="switchTab('aging-return')" id="nav-aging-return" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-clock-rotate-left w-5 text-center text-teal-600"></i>
                <div class="flex items-center justify-between flex-1">
                    <span>Aging Return</span>
                    <span id="navAgingCountBadge" class="hidden px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-teal-100 text-teal-700">0</span>
                </div>
            </button>
            <?php endif; ?>

            <!-- MENU: PUSAT KLAIM & BANDING (CLAIM DOSSIER) -->
            <?php if ($isAdmin || $isAccounting || $isCS): ?>
            <button onclick="switchTab('claims')" id="nav-claims" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-shield-halved w-5 text-center text-amber-500"></i>
                <span>Pusat Klaim &amp; Banding</span>
            </button>
            <?php endif; ?>

            <!-- MENU BARU: DATA ORDERS OCS (SINKRONISASI PESANAN) -->
            <?php if ($isAdmin): ?>
            <button onclick="switchTab('orders')" id="nav-orders" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-cart-flatbed w-5 text-center text-blue-600"></i>
                <span>Data Orders OCS</span>
            </button>

            <button onclick="switchTab('products')" id="nav-products" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-tags w-5 text-center text-blue-500"></i>
                <span>Master Produk</span>
            </button>

            <button onclick="switchTab('expeditions')" id="nav-expeditions" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-truck-fast w-5 text-center text-teal-500"></i>
                <span>Master Ekspedisi</span>
            </button>

            <button onclick="switchTab('conditions')" id="nav-conditions" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-clipboard-check w-5 text-center text-purple-500"></i>
                <span>Master Kondisi</span>
            </button>

            <button onclick="switchTab('users')" id="nav-users" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-users-gear w-5 text-center text-slate-500"></i>
                <span>Kelola Pengguna</span>
            </button>
            <?php endif; ?>

            <?php if ($isSuperAdmin || $isAdmin): ?>
            <button onclick="switchTab('roles')" id="nav-roles" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-user-shield w-5 text-center text-purple-600"></i>
                <span>Kelola Role</span>
            </button>
            <?php endif; ?>

            <?php if ($isSuperAdmin || $isAdmin || $isAccounting): ?>
            <button onclick="switchTab('bank-settings')" id="nav-bank-settings" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-building-columns w-5 text-center text-emerald-600"></i>
                <span>Pengaturan Bank</span>
            </button>
            <button onclick="switchTab('approval-jnt')" id="nav-approval-jnt" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                <i class="fa-solid fa-stamp w-5 text-center text-rose-600"></i>
                <span>Approval Klaim J&amp;T</span>
            </button>
            <?php endif; ?>

            <?php if ($isSuperAdmin): ?>
            <button onclick="switchTab('maintenance')" id="nav-maintenance" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-amber-700 hover:text-amber-800 hover:bg-amber-100/70 border border-amber-300/80 bg-amber-50/60">
                <i class="fa-solid fa-screwdriver-wrench w-5 text-center text-amber-600"></i>
                <span>Pemeliharaan</span>
            </button>
            <?php endif; ?>

            <div class="pt-4 px-3 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Akses Langsung</div>

            <a href="scanner" class="w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-emerald-700 hover:text-emerald-800 hover:bg-emerald-100/70 border border-emerald-300/80 bg-emerald-50/60 transition">
                <i class="fa-solid fa-barcode w-5 text-center text-emerald-600"></i>
                <span>Buka Scanner Operator</span>
            </a>
        </div>

        <!-- Sidebar Footer: User Info & Logout Button -->
        <div class="p-3.5 border-t border-slate-100 bg-slate-50/90 flex items-center justify-between">
            <div class="flex items-center space-x-2.5 overflow-hidden">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200/80 flex items-center justify-center shrink-0">
                    <i class="fa-solid <?= $isSuperAdmin ? 'fa-shield-halved text-amber-500' : 'fa-user-tie text-indigo-600' ?> text-xs"></i>
                </div>
                <div class="truncate">
                    <div class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="text-[10px] <?= $isSuperAdmin ? 'text-amber-600' : 'text-indigo-600' ?> font-mono uppercase font-semibold"><?= $user['role'] ?></div>
                </div>
            </div>
            <a href="logout" onclick="return confirm('Apakah Anda yakin ingin logout?')" title="Logout / Keluar" class="text-rose-500 hover:text-rose-700 hover:bg-rose-100/60 p-2 rounded-xl transition">
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
                        <button onclick="triggerCloudSyncNow(true)" title="Tarik data & foto transaksi terbaru dari InfinityFree secara manual (On-Demand)" class="bg-sky-600 hover:bg-sky-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-sky-600/30 shrink-0 cursor-pointer">
                            <i class="fa-solid fa-cloud-arrow-down text-xs"></i> Tarik Data Cloud
                        </button>
                        <button onclick="shareDashboardReport()" title="Bagikan ringkasan laporan produktivitas" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-indigo-600/20 shrink-0 cursor-pointer">
                            <i class="fa-solid fa-share-nodes text-xs"></i> Share Report
                        </button>
                        <button onclick="exportDashboardExcel()" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/20 shrink-0 cursor-pointer">
                            <i class="fa-solid fa-file-excel text-xs"></i> Export Excel
                        </button>
                    </div>
                </div>

                <!-- KPI Cards Row (5 Cards Sesuai Request) -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5">
                    <!-- 1. Total Paket yang Diterima -->
                    <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-xs border border-emerald-200/80 relative overflow-hidden group hover:shadow-md transition">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-emerald-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider">Total Paket Diterima</span>
                                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                                    <i class="fa-solid fa-truck-ramp-box text-xs"></i>
                                </span>
                            </div>
                            <div class="text-2xl sm:text-3xl font-black text-emerald-700 font-mono" id="kpiTotalReceivedPackages">—</div>
                            <div class="text-[10px] text-emerald-600 mt-1 flex items-center gap-1">
                                <b id="kpiTotalReceptions">0</b> Surat Jalan Fisik
                            </div>
                        </div>
                    </div>

                    <!-- 2. Total Paket yang Missing (Belum di Unboxing) -->
                    <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-xs border border-amber-200/90 relative overflow-hidden group hover:shadow-md transition">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-amber-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider">Paket Missing</span>
                                <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                </span>
                            </div>
                            <div class="text-2xl sm:text-3xl font-black text-amber-600 font-mono" id="kpiTotalMissingPackages">—</div>
                            <div class="text-[10px] text-amber-600 mt-1">Belum unboxing di station</div>
                        </div>
                    </div>

                    <!-- 3. Total Paket yang Received (Sudah di Unboxing) -->
                    <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-xs border border-indigo-200/80 relative overflow-hidden group hover:shadow-md transition">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-indigo-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-indigo-800 uppercase tracking-wider">Paket Received</span>
                                <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <i class="fa-solid fa-box-open text-xs"></i>
                                </span>
                            </div>
                            <div class="text-2xl sm:text-3xl font-black text-indigo-700 font-mono" id="kpiTotalInvoice">—</div>
                            <div class="text-[10px] text-slate-400 mt-1">Sudah selesai unboxing</div>
                        </div>
                    </div>

                    <!-- 4. Total Paket yang Reject (Kondisi Rusak / Defect) -->
                    <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-xs border border-rose-200/80 relative overflow-hidden group hover:shadow-md transition">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-rose-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-rose-600 uppercase tracking-wider">Paket Reject</span>
                                <span class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                </span>
                            </div>
                            <div class="text-2xl sm:text-3xl font-black text-rose-600 font-mono" id="kpiTotalDamaged">—</div>
                            <div class="text-[10px] text-rose-500 mt-1">Cacat / layak klaim</div>
                        </div>
                    </div>

                    <!-- 5. Total Unit Fisik (Qty Barang Masuk) -->
                    <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-xs border border-blue-200/80 relative overflow-hidden group hover:shadow-md transition col-span-2 md:col-span-1">
                        <div class="absolute -right-3 -top-3 w-16 h-16 rounded-full bg-blue-50 opacity-60 group-hover:opacity-100 transition"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold text-blue-800 uppercase tracking-wider">Total Unit Fisik</span>
                                <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                    <i class="fa-solid fa-boxes-stacked text-xs"></i>
                                </span>
                            </div>
                            <div class="text-2xl sm:text-3xl font-black text-blue-700 font-mono" id="kpiTotalItems">—</div>
                            <div class="text-[10px] text-slate-400 mt-1">Total produk diperiksa</div>
                        </div>
                    </div>
                </div>

                <!-- KPI Productivity Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- KPI Productivity 1: Trend Penerimaan Paket (Line Chart) -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 flex flex-col">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                    <i class="fa-solid fa-chart-line text-emerald-600 text-xs"></i> Trend Penerimaan Paket (Receiving)
                                </h4>
                                <p class="text-[11px] text-slate-400">KPI Productivity Serah Terima Paket Ekspedisi Fisik</p>
                            </div>
                            <span class="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full font-bold">Line Chart</span>
                        </div>
                        <div class="flex-1 relative min-h-[230px]">
                            <canvas id="trendReceivingChart"></canvas>
                        </div>
                    </div>

                    <!-- KPI Productivity 2: Trend Unboxing per Hari (Bar Chart) -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 flex flex-col">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                    <i class="fa-solid fa-chart-simple text-indigo-600 text-xs"></i> Trend Unboxing per Hari
                                </h4>
                                <p class="text-[11px] text-slate-400">KPI Productivity Paket Selesai Di-unboxing per Hari</p>
                            </div>
                            <span class="text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full font-bold">Bar Chart</span>
                        </div>
                        <div class="flex-1 relative min-h-[230px]">
                            <canvas id="trendUnboxingChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Middle Row: Breakdown Ekspedisi & Donut Rasio Kondisi -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    <!-- Chart 1: Total Paket per Ekspedisi -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 lg:col-span-7 flex flex-col">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-truck-fast text-blue-600 text-xs"></i> Total Paket per Ekspedisi
                            </h4>
                            <span class="text-[10px] text-slate-400 font-mono">Receiving &amp; Unboxing</span>
                        </div>
                        <div class="flex-1 relative min-h-[220px]">
                            <canvas id="expeditionChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 2: Donut Rasio Kondisi -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 lg:col-span-5 flex flex-col">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-chart-pie text-emerald-500 text-xs"></i> Rasio Kondisi
                            </h4>
                            <span class="text-[10px] text-slate-400 font-mono">Restock vs Rusak</span>
                        </div>
                        <div class="flex-1 relative min-h-[220px]">
                            <canvas id="ratioChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Produktivitas Petugas Inbound (PIC Receiving & Unboxing) -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-slate-50/70">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-sm">
                                <i class="fa-solid fa-users-gear"></i>
                            </span>
                            <div>
                                <h4 class="font-bold text-sm text-slate-800">Produktivitas Petugas Inbound (PIC Receiving & Unboxing)</h4>
                                <p class="text-[11px] text-slate-500">Jumlah paket yang diproses di serah terima (Receiving) dan stasiun unboxing per PIC</p>
                            </div>
                        </div>
                        <span class="text-[11px] bg-white border border-slate-200 text-slate-600 px-2.5 py-1 rounded-lg font-semibold shadow-2xs">
                            <i class="fa-regular fa-clock text-amber-500 mr-1"></i> Data Realtime Periode Aktif
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-4 w-12 text-center">#</th>
                                    <th class="py-3 px-4">Nama Petugas / PIC</th>
                                    <th class="py-3 px-4 text-center">Receiving (Serah Terima)</th>
                                    <th class="py-3 px-4 text-center">Inbound Unboxing</th>
                                    <th class="py-3 px-4 text-right">Total Paket Diproses</th>
                                    <th class="py-3 px-4 text-center">Status / Kontribusi</th>
                                </tr>
                            </thead>
                            <tbody id="dashPicTableBody" class="divide-y divide-slate-100 font-medium">
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400">
                                        <i class="fa-solid fa-spinner fa-spin mr-2 text-indigo-600"></i> Memuat data produktivitas PIC...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Row 4: Expedition Tables -->
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
                                    <tr><td colspan="5" class="p-6 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2 text-indigo-600"></i>Memuat data...</td></tr>
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
                            <div class="p-6 text-center text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-2 text-indigo-600"></i>Memuat data...</div>
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
                                <input type="text" id="searchReceivingInput" placeholder="Cari No. RCV / Kurir..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
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
                                    <th class="py-3 px-4">Nomor Karung</th>
                                    <th class="py-3 px-4 text-center">Total Paket</th>
                                    <th class="py-3 px-4">Operator Penerima</th>
                                    <th class="py-3 px-4">Waktu Penerimaan</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="receivingTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="9" class="text-center py-12 text-slate-400">
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
                    <!-- Header Baris 1: Judul & Download Excel -->
                    <div class="p-4 sm:p-5 border-b border-slate-200 bg-white flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg shadow-sm border border-indigo-100 shrink-0">
                                <i class="fa-solid fa-box-open"></i>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-base text-slate-800">Inbound Unboxing</h3>
                                    <span class="bg-indigo-50 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-indigo-200">Video &amp; Audit Trail</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">Riwayat hasil scan unboxing fisik paket, kondisi produk, dan rekaman audit trail</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <button onclick="exportInboundUnboxingExcel()" title="Download Seluruh Riwayat Inbound Unboxing ke Excel" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2.5 rounded-xl font-bold transition flex items-center justify-center gap-1.5 shadow-sm shadow-emerald-600/20 w-full sm:w-auto cursor-pointer">
                                <i class="fa-solid fa-file-excel text-xs"></i>
                                <span>Download Excel</span>
                            </button>
                        </div>
                    </div>

                    <!-- Header Baris 2: Dedicated Responsive Filter Bar (Rapi & Tahan Zoom) -->
                    <div class="p-3.5 sm:p-4 bg-slate-50/70 border-b border-slate-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2.5">
                            <!-- 1. Premium Datepicker (Flatpickr) -->
                            <div class="relative flex items-center w-full">
                                <span class="absolute left-3 text-indigo-600 pointer-events-none text-xs z-10">
                                    <i class="fa-regular fa-calendar-days"></i>
                                </span>
                                <input type="text" id="filterDate" placeholder="Pilih Tanggal / Rentang..." readonly
                                    class="w-full bg-white hover:border-slate-400 focus:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs transition cursor-pointer">
                                <button type="button" id="btnClearDate" onclick="clearDateFilter()" title="Hapus filter tanggal" class="absolute right-2.5 text-slate-400 hover:text-rose-500 transition text-xs hidden z-10">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                            </div>

                            <!-- 2. Filter Ekspedisi -->
                            <div class="relative w-full">
                                <select id="filterExpedition" onchange="loadTransactions()" class="w-full bg-white hover:border-slate-400 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-2xs cursor-pointer">
                                    <option value="">Semua Ekspedisi</option>
                                </select>
                            </div>

                            <!-- 3. Filter Type (Kondisi) -->
                            <div class="relative w-full">
                                <select id="filterCondition" onchange="loadTransactions()" class="w-full bg-white hover:border-slate-400 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-2xs cursor-pointer">
                                    <option value="">Semua Kondisi</option>
                                    <?php
                                    if (isset($pdo)) {
                                        try {
                                            $stmtC = $pdo->query("SELECT code, name FROM master_conditions ORDER BY sort_order ASC, name ASC");
                                            while ($rC = $stmtC->fetch()) {
                                                echo '<option value="' . htmlspecialchars($rC['code']) . '">' . htmlspecialchars($rC['name']) . ' (' . htmlspecialchars($rC['code']) . ')</option>';
                                            }
                                        } catch (Exception $eC) {}
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- 4. Filter Operator -->
                            <div class="relative w-full">
                                <select id="filterOperator" onchange="loadTransactions()" class="w-full bg-white hover:border-slate-400 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-2xs cursor-pointer">
                                    <option value="">Semua Operator</option>
                                </select>
                            </div>

                            <!-- 5. Search Input -->
                            <div class="relative flex items-center w-full">
                                <span class="absolute left-3 text-slate-400 pointer-events-none text-xs">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <input type="text" id="filterSearch" placeholder="Cari invoice/sku..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                    class="w-full bg-white hover:border-slate-400 border border-slate-300 rounded-xl pl-8 pr-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                            </div>

                            <!-- 6. Action Button Group (Filter & Reset) -->
                            <div class="flex items-center gap-2 w-full">
                                <button id="btnApplyFilter" onclick="loadTransactions()" class="flex-1 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs py-2 px-3 rounded-xl font-bold transition flex items-center justify-center gap-1.5 shadow-sm shadow-indigo-600/30 cursor-pointer">
                                    <i class="fa-solid fa-filter text-[11px]"></i>
                                    <span>Filter</span>
                                </button>
                                <button type="button" onclick="resetTransactionFilters()" title="Reset semua filter ke default" class="bg-slate-200 hover:bg-slate-300 active:scale-95 text-slate-700 text-xs py-2 px-3 rounded-xl font-bold transition flex items-center justify-center cursor-pointer">
                                    <i class="fa-solid fa-rotate-left text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[11px]">
                                <tr>
                                    <th class="p-3 whitespace-nowrap">Tanggal & Waktu</th>
                                    <th class="p-3 whitespace-nowrap">Invoice & Ekspedisi</th>
                                    <th class="p-3 whitespace-nowrap">Operator</th>
                                    <th class="p-3 min-w-[220px] max-w-[360px]">Nama Produk & SKU</th>
                                    <th class="p-3 whitespace-nowrap">Batch</th>
                                    <th class="p-3 whitespace-nowrap">Exp Date</th>
                                    <th class="p-3 text-center whitespace-nowrap">Qty</th>
                                    <th class="p-3 text-center whitespace-nowrap">Type (Kondisi)</th>
                                    <th class="p-3 text-center whitespace-nowrap">Video Unboxing</th>
                                    <th class="p-3 text-center whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="10" class="text-center py-8 text-slate-400">Memuat data transaksi unboxing...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB BARU: AGING RETURN (MONITORING DURASI RECEIVING KE UNBOXING) -->
            <div id="tab-aging-return" class="tab-content hidden space-y-6">
                <!-- Header Card & Actions -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 sm:p-5 border-b border-slate-200 bg-white flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold text-lg shadow-sm border border-teal-100 shrink-0">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-base text-slate-800">Aging Return - Lead Time Inbound</h3>
                                    <span class="bg-teal-50 text-teal-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-teal-200">Receiving ➔ Unboxing</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">Pantau durasi hari paket dari serah terima ekspedisi hingga unboxing & deteksi paket yang belum di-unboxing di gudang</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <button onclick="exportAgingReturnExcel()" title="Download Seluruh Data Aging Return ke Excel" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center justify-center gap-1.5 shadow-sm shadow-emerald-600/20 cursor-pointer">
                                <i class="fa-solid fa-file-excel text-xs"></i>
                                <span>Export Excel</span>
                            </button>
                            <button onclick="loadAgingReturnData(true)" title="Muat ulang data terbaru" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs px-3 py-2 rounded-xl font-bold transition flex items-center justify-center gap-1.5 border border-slate-200 cursor-pointer">
                                <i class="fa-solid fa-rotate text-xs" id="agingRefreshIcon"></i>
                                <span>Refresh</span>
                            </button>
                        </div>
                    </div>

                    <!-- 5 KPI Summary Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 p-4 sm:p-5 bg-slate-50/70 border-b border-slate-200">
                        <!-- 1. Total Terpantau -->
                        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Paket</span>
                                <span class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                </span>
                            </div>
                            <div class="text-xl sm:text-2xl font-black text-slate-800 mt-1" id="agingKpiTotal">0</div>
                            <span class="text-[10px] text-slate-500 font-medium">Paket receiving inbound</span>
                        </div>

                        <!-- 2. Belum di Unboxing -->
                        <div class="bg-white p-3.5 rounded-2xl border border-amber-200 shadow-2xs bg-gradient-to-br from-white to-amber-50/30">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Belum di Unboxing</span>
                                <span class="w-6 h-6 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                </span>
                            </div>
                            <div class="text-xl sm:text-2xl font-black text-amber-600 mt-1" id="agingKpiBelum">0</div>
                            <span class="text-[10px] text-amber-600 font-medium">Menunggu di gudang</span>
                        </div>

                        <!-- 3. Sudah di Unboxing -->
                        <div class="bg-white p-3.5 rounded-2xl border border-emerald-200 shadow-2xs bg-gradient-to-br from-white to-emerald-50/30">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Sudah Unboxing</span>
                                <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-circle-check"></i>
                                </span>
                            </div>
                            <div class="text-xl sm:text-2xl font-black text-emerald-600 mt-1" id="agingKpiSudah">0</div>
                            <span class="text-[10px] text-emerald-600 font-medium">Selesai diperiksa</span>
                        </div>

                        <!-- 4. Rata-rata Aging -->
                        <div class="bg-white p-3.5 rounded-2xl border border-teal-200 shadow-2xs bg-gradient-to-br from-white to-teal-50/30">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-teal-700">Rata-rata Aging</span>
                                <span class="w-6 h-6 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-stopwatch"></i>
                                </span>
                            </div>
                            <div class="text-xl sm:text-2xl font-black text-teal-600 mt-1"><span id="agingKpiAvg">0</span> <span class="text-xs font-normal text-slate-500">Hari</span></div>
                            <span class="text-[10px] text-teal-600 font-medium">Lead time rata-rata</span>
                        </div>

                        <!-- 5. Kritis > 14 Hari -->
                        <div class="col-span-2 sm:col-span-1 bg-white p-3.5 rounded-2xl border border-rose-200 shadow-2xs bg-gradient-to-br from-white to-rose-50/30">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Aging Kritis (&gt;14 Hari)</span>
                                <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </span>
                            </div>
                            <div class="text-xl sm:text-2xl font-black text-rose-600 mt-1" id="agingKpiCritical">0</div>
                            <span class="text-[10px] text-rose-600 font-medium">Perlu tindakan segera</span>
                        </div>
                    </div>

                    <!-- Toolbar Filter & Search -->
                    <div class="p-3.5 sm:p-4 bg-slate-50 border-b border-slate-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2.5">
                            <!-- 1. Search Box -->
                            <div class="relative w-full">
                                <span class="absolute left-3 top-2.5 text-slate-400 pointer-events-none text-xs">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <input type="text" id="agingSearchInput" placeholder="Cari Resi / Surat Jalan / Kurir..."
                                    class="w-full bg-white hover:border-slate-400 focus:bg-white border border-slate-300 rounded-xl pl-8 pr-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 transition shadow-2xs">
                            </div>

                            <!-- 2. Status Unboxing Filter -->
                            <div class="relative w-full">
                                <select id="agingStatusFilter" onchange="loadAgingReturnData()" class="w-full bg-white hover:border-slate-400 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 transition shadow-2xs cursor-pointer">
                                    <option value="ALL">Semua Status Paket</option>
                                    <option value="BELUM_UNBOXING">⚠️ Belum di Unboxing</option>
                                    <option value="SUDAH_UNBOXING">✓ Sudah di Unboxing</option>
                                </select>
                            </div>

                            <!-- 3. Ekspedisi Filter -->
                            <div class="relative w-full">
                                <select id="agingExpeditionFilter" onchange="loadAgingReturnData()" class="w-full bg-white hover:border-slate-400 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 transition shadow-2xs cursor-pointer">
                                    <option value="">Semua Ekspedisi</option>
                                </select>
                            </div>

                            <!-- 4. Aging Category Filter (<=14 vs >14) -->
                            <div class="relative w-full">
                                <select id="agingCategoryFilter" onchange="loadAgingReturnData()" class="w-full bg-white hover:border-slate-400 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 transition shadow-2xs cursor-pointer">
                                    <option value="ALL">Semua Rentang Aging</option>
                                    <option value="LE14">Aman (≤ 14 Hari)</option>
                                    <option value="GT14">⚠️ Kritis (&gt; 14 Hari)</option>
                                </select>
                            </div>

                            <!-- 5. Datepicker Flatpickr -->
                            <div class="relative flex items-center w-full">
                                <span class="absolute left-3 text-teal-600 pointer-events-none text-xs z-10">
                                    <i class="fa-regular fa-calendar-days"></i>
                                </span>
                                <input type="text" id="agingDateFilter" placeholder="Rentang Tanggal..." readonly
                                    class="w-full bg-white hover:border-slate-400 focus:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 shadow-2xs transition cursor-pointer">
                                <button type="button" id="btnClearAgingDate" onclick="clearAgingDateFilter()" title="Hapus filter tanggal" class="absolute right-2.5 text-slate-400 hover:text-rose-500 transition text-xs hidden z-10">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                            </div>

                            <!-- 6. Sort Order & Reset -->
                            <div class="flex items-center gap-1.5 w-full">
                                <select id="agingSortOrder" onchange="loadAgingReturnData()" class="w-full bg-white hover:border-slate-400 border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 transition shadow-2xs cursor-pointer">
                                    <option value="fifo">FIFO (Masuk Dulu)</option>
                                    <option value="lifo">LIFO (Baru Dulu)</option>
                                    <option value="aging_desc">Aging Terlama</option>
                                    <option value="aging_asc">Aging Tercepat</option>
                                </select>
                                <button type="button" onclick="resetAgingFilters()" title="Reset semua filter" class="p-2 rounded-xl bg-white hover:bg-slate-100 border border-slate-300 text-slate-500 hover:text-slate-800 transition shrink-0 cursor-pointer">
                                    <i class="fa-solid fa-arrow-rotate-left text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100/80 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                    <th class="py-3 px-3.5 text-center w-12">No</th>
                                    <th class="py-3 px-4">No. Resi / AWB</th>
                                    <th class="py-3 px-4">Ekspedisi & Kurir</th>
                                    <th class="py-3 px-4">Tgl Receiving</th>
                                    <th class="py-3 px-4">Tgl Unboxing</th>
                                    <th class="py-3 px-4 text-center">Status Paket</th>
                                    <th class="py-3 px-4 text-center">Aging (Lead Time)</th>
                                    <th class="py-3 px-4">Petugas</th>
                                    <th class="py-3 px-4 text-center">Bukti / Media</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="agingReturnTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="10" class="text-center py-12 text-slate-400">
                                        <i class="fa-solid fa-spinner fa-spin text-2xl text-teal-600 block mb-2"></i>
                                        <span>Memuat data Aging Return...</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Table / Info -->
                    <div class="p-3.5 sm:p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-2 text-xs text-slate-500">
                        <div id="agingTableCountInfo">Menampilkan 0 data paket</div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 text-[11px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aman (≤ 14 Hari)
                            </span>
                            <span class="inline-flex items-center gap-1 text-[11px] text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Kritis (&gt; 14 Hari)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB BARU: PUSAT KLAIM & BANDING (CLAIM DOSSIER) -->
            <div id="tab-claims" class="tab-content hidden space-y-6">
                <!-- Data Table Card: Pusat Klaim & Banding (Daftar Paket Rusak / Layak Klaim) -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 bg-white">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-lg shadow-sm shrink-0 border border-amber-500/20">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            <div>
                                <h3 class="font-black text-slate-800 text-base">Pusat Klaim & Banding Ekspedisi</h3>
                                <p class="text-xs text-slate-500">Daftar paket unboxing yang terindikasi rusak atau cacat fisik untuk pengajuan klaim ekspedisi & marketplace</p>
                            </div>
                        </div>

                        <!-- Quick Scan / Resi Lookup & Refresh Button -->
                        <div class="flex items-center gap-2 w-full lg:w-auto">
                            <form id="formClaimLookup" onsubmit="executeClaimLookup(event)" autocomplete="off" class="flex items-center gap-1.5 flex-1 lg:flex-none">
                                <div class="relative flex-1 sm:w-72">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                        <i class="fa-solid fa-barcode text-amber-500"></i>
                                    </span>
                                    <input type="text" id="claimSearchInput" placeholder="Scan Resi / Order ID..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                        class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500 transition shadow-2xs">
                                </div>
                                <button type="submit" id="btnClaimSearch" class="bg-amber-500 hover:bg-amber-600 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm shadow-amber-500/20 shrink-0" title="Cari berkas klaim">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <span class="hidden sm:inline">Cari</span>
                                </button>
                            </form>
                            <button onclick="loadClaimCandidates(true)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-slate-300 shadow-2xs shrink-0" title="Refresh Daftar Paket Rusak">
                                <i class="fa-solid fa-arrows-rotate text-amber-600" id="iconRefreshCandidates"></i>
                                <span class="hidden sm:inline">Refresh</span>
                            </button>
                        </div>
                    </div>

                    <!-- TAB PEMISAH: KLAIM J&T & JNE (ASSIGN KE ACCOUNTING) VS EKSPEDISI LAIN (KLAIM MANUAL) -->
                    <div class="px-4 pt-2.5 bg-slate-100 border-b border-slate-200 flex items-center gap-2 overflow-x-auto">
                        <button type="button" onclick="setClaimExpeditionMode('jnt_jne')" id="btnClaimModeJnt" class="px-4 py-2 rounded-t-xl font-bold text-xs transition border-t-2 border-rose-500 bg-white text-rose-700 shadow-2xs flex items-center gap-1.5 cursor-pointer">
                            <i class="fa-solid fa-envelope text-rose-600"></i>
                            <span>Klaim Ekspedisi J&amp;T &amp; JNE (Assign ke Accounting)</span>
                            <span id="badgeJntPendingApproval" class="ml-1 px-1.5 py-0.2 rounded-full text-[9px] bg-rose-100 text-rose-800 font-extrabold hidden">0</span>
                        </button>
                        <button type="button" onclick="setClaimExpeditionMode('other')" id="btnClaimModeOther" class="px-4 py-2 rounded-t-xl font-bold text-xs transition text-slate-500 hover:text-slate-800 hover:bg-slate-200/60 flex items-center gap-1.5 cursor-pointer">
                            <i class="fa-solid fa-truck-fast text-slate-500"></i>
                            <span>Klaim Ekspedisi Lain (Klaim Manual Ekspedisi)</span>
                        </button>
                    </div>

                    <!-- Filter Toolbar 1 Baris Rapi & Rekap Total Realtime Mengikuti Filter -->
                    <div class="p-3.5 bg-slate-50/90 border-b border-slate-200 flex flex-col xl:flex-row items-start xl:items-center justify-between gap-3 text-xs">
                        <div class="flex flex-wrap items-center gap-2 flex-1 w-full xl:w-auto">
                            <!-- Input Search -->
                            <div class="relative flex-1 min-w-[180px] max-w-xs">
                                <input type="text" id="filterClaimSearch" placeholder="Cari Resi / Invoice / Produk..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                    oninput="applyClaimCandidatesFilter()"
                                    class="w-full pl-8 pr-3 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none transition shadow-2xs font-semibold">
                                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            </div>

                            <!-- Dropdown Ekspedisi -->
                            <select id="filterClaimExpedition" onchange="applyClaimCandidatesFilter()" 
                                class="bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-amber-500 focus:outline-none transition shadow-2xs cursor-pointer">
                                <option value="">Semua Ekspedisi</option>
                            </select>

                            <!-- Dropdown Status Klaim -->
                            <select id="filterClaimStatus" onchange="applyClaimCandidatesFilter()" 
                                class="bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-amber-500 focus:outline-none transition shadow-2xs cursor-pointer">
                                <option value="">Semua Status</option>
                                <option value="PENDING">Belum Klaim</option>
                                <option value="DONE_EMAIL">Done Email (Accounting)</option>
                                <option value="RECEIVED">Received (Accounting)</option>
                                <option value="PROCESS">Proses Klaim</option>
                                <option value="DONE">Done Klaim</option>
                            </select>

                            <!-- Filter Tanggal Unboxing (Flatpickr Rentang) -->
                            <div class="relative flex items-center">
                                <span class="absolute left-3 text-amber-500 pointer-events-none text-xs z-10">
                                    <i class="fa-regular fa-calendar-days"></i>
                                </span>
                                <input type="text" id="filterClaimDate" placeholder="Pilih Rentang Tanggal..." readonly
                                    class="bg-white border border-slate-300 rounded-xl pl-8 pr-7 py-2 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-amber-500 focus:outline-none transition shadow-2xs w-48 sm:w-56 cursor-pointer"
                                    title="Filter Rentang Tanggal Unboxing">
                                <button type="button" id="btnClearClaimDate" onclick="clearClaimDateFilter()" title="Hapus filter tanggal" class="absolute right-2 text-slate-400 hover:text-rose-500 transition text-xs hidden z-10">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                            </div>

                            <!-- Reset Filter -->
                            <button type="button" onclick="resetClaimCandidatesFilter()" 
                                class="px-3 py-2 bg-slate-200 hover:bg-slate-300 active:scale-95 text-slate-700 rounded-xl font-bold text-xs transition cursor-pointer" title="Reset Filter ke Default">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                        </div>

                        <!-- Rekap Total Finansial Realtime Mengikuti Filter Termasuk Average Aging -->
                        <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto justify-between xl:justify-end text-xs">
                            <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-xl shadow-2xs">
                                <span class="text-[11px] text-amber-700 font-medium"><i class="fa-solid fa-clock-rotate-left text-amber-600 mr-1"></i>Avg Aging:</span>
                                <b id="avgClaimAging" class="text-amber-800 font-mono font-black">0 Hari</b>
                            </div>
                            <div class="flex items-center gap-1.5 bg-white border border-slate-200 px-3 py-1.5 rounded-xl shadow-2xs">
                                <span class="text-[11px] text-slate-500">Terfilter:</span>
                                <b id="countClaimFiltered" class="text-indigo-600 font-bold">0</b>
                                <span class="text-slate-400">/ <span id="countClaimTotal">0</span> paket</span>
                            </div>
                            <div class="flex items-center gap-1.5 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl shadow-2xs">
                                <span class="text-[11px] text-rose-600 font-medium">Qty Rusak:</span>
                                <b id="sumClaimFilteredDamaged" class="text-rose-700 font-mono font-black">0 pcs</b>
                            </div>
                            <div class="flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-xl shadow-2xs">
                                <span class="text-[11px] text-emerald-700 font-medium">Total Nilai Tagihan:</span>
                                <b id="sumClaimFilteredNominal" class="text-emerald-800 font-mono font-black text-xs sm:text-sm">Rp 0</b>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="py-3 px-3 text-center w-14">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <input type="checkbox" id="checkAllClaimCandidates" onchange="toggleSelectAllClaims(this)" 
                                                class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500 border-slate-300 cursor-pointer" title="Pilih Semua Paket">
                                            <span class="text-[9px] text-slate-400 font-mono">ALL</span>
                                        </div>
                                    </th>
                                    <th class="py-3 px-3">No. Resi / Invoice &amp; Ekspedisi</th>
                                    <th class="py-3 px-3 text-center">Age Paket (FIFO)</th>
                                    <th class="py-3 px-3">Nama Produk &amp; SKU</th>
                                    <th class="py-3 px-3 text-center">Qty Rusak</th>
                                    <th class="py-3 px-3">Kondisi / Alasan Rusak</th>
                                    <th class="py-3 px-3 text-right">Biaya Paket</th>
                                    <th class="py-3 px-3">Waktu Unboxing</th>
                                    <th class="py-3 px-3 text-center">Status / Accounting</th>
                                    <th class="py-3 px-3 text-center">Video Unbox</th>
                                    <th class="py-3 px-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="claimCandidatesTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="11" class="text-center py-10 text-slate-400">
                                        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat daftar paket rusak...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- FLOATING STICKY ACTION BAR MULTIPLE SELECT KLAIM -->
                    <div id="selectedClaimsActionCard" class="hidden p-3.5 sm:p-4 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white border-t border-slate-700/80 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-lg transition-all">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-2xl bg-amber-500 text-slate-950 font-black flex items-center justify-center text-base shadow-sm shrink-0">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-300">Paket Klaim Terpilih:</span>
                                    <span id="selectedClaimsCount" class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 font-mono font-black text-xs border border-amber-500/30">0 Paket</span>
                                    <span id="selectedClaimsDamagedQty" class="text-[11px] text-rose-400 font-bold">(0 pcs rusak)</span>
                                </div>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    Total Estimasi Tagihan: <b id="selectedClaimsTotalPrice" class="text-emerald-400 font-mono text-sm sm:text-base font-black">Rp 0</b>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end flex-wrap">
                            <button type="button" onclick="clearSelectedClaims()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 active:scale-95 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition border border-slate-700 cursor-pointer">
                                Batal
                            </button>

                            <!-- KHUSUS MODE JNT & JNE: EMAIL & ASSIGN KE ACCOUNTING -->
                            <button type="button" id="btnSendToAccounting" onclick="sendSelectedToAccounting()" class="hidden px-4 py-2 bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-700 hover:to-pink-700 active:scale-95 text-white font-bold rounded-xl text-xs sm:text-sm transition flex items-center gap-2 shadow-lg shadow-rose-600/30 cursor-pointer" title="Assign dan kirim email rekap klaim terpilih langsung ke Accounting">
                                <i class="fa-solid fa-envelope-circle-check"></i>
                                <span>Kirim Email ke Accounting (<span id="btnSendAccountingCount">0</span>)</span>
                            </button>

                            <!-- KHUSUS MODE EKSPEDISI LAIN: KLAIM MANUAL -->
                            <button type="button" id="btnBulkClaimProcess" onclick="processSelectedClaims()" class="hidden px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 active:scale-95 text-slate-950 font-black rounded-xl text-xs sm:text-sm transition flex items-center gap-2 shadow-lg shadow-amber-500/30 cursor-pointer" title="Ubah status paket terpilih menjadi Proses Klaim">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Proses Klaim (<span id="btnBulkClaimCount">0</span>)</span>
                            </button>
                            <!-- Tandai Done Klaim secara manual per ekspedisi -->
                            <button type="button" id="btnBulkClaimDone" onclick="markSelectedClaimsDone()" class="hidden px-3.5 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-95 text-white rounded-xl text-xs sm:text-sm font-bold transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/30 cursor-pointer" title="Tandai paket terpilih sebagai Done Klaim sesuai ekspedisi">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Done Klaim Ekspedisi</span>
                            </button>
                            <!-- Step 2: Print Invoice (muncul setelah di-approve atau diklaim) -->
                            <button type="button" id="btnPrintClaimInvoice" onclick="printCollectiveClaimInvoice()" class="hidden px-4 py-2 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 hover:from-emerald-700 hover:to-teal-700 active:scale-95 text-white font-black rounded-xl text-xs sm:text-sm transition flex items-center gap-2 shadow-lg shadow-emerald-600/30 cursor-pointer">
                                <i class="fa-solid fa-print"></i>
                                <span>Print Tagihan (<span id="btnPrintTotalCost">Rp 0</span>)</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB BARU: DATA ORDERS OCS (SINKRONISASI PESANAN) -->
            <div id="tab-orders" class="tab-content hidden space-y-5">
                <!-- Header Card & Quick Action -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg shadow-sm shadow-blue-600/10 shrink-0">
                                <i class="fa-solid fa-cart-flatbed"></i>
                            </span>
                            <div>
                                <h3 class="font-black text-slate-800 text-base">Data Orders OCS (Sinkronisasi Pesanan)</h3>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto flex-wrap">
                            <button onclick="exportOrdersExcel()" type="button" class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2.5 rounded-xl font-bold transition flex items-center justify-center gap-1.5 shadow-sm shadow-emerald-600/20">
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Export Excel</span>
                            </button>
                            <button onclick="openOcsSyncModal('month')" type="button" class="flex-1 sm:flex-none bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2.5 rounded-xl font-bold transition flex items-center justify-center gap-2 shadow-sm shadow-indigo-600/20" title="Sync Semua Orders / Picklist 1 Bulan Terakhir (30 Hari)">
                                <i class="fa-solid fa-calendar-days text-sm"></i>
                                <span>Sync 1 Bulan</span>
                            </button>
                            <button onclick="openOcsSyncModal('picklist')" type="button" class="flex-1 sm:flex-none bg-blue-600 hover:bg-blue-700 text-white text-xs px-3.5 py-2.5 rounded-xl font-bold transition flex items-center justify-center gap-2 shadow-sm shadow-blue-600/20" title="Sync via No. Resi atau Order ID menggunakan Picklist OCS">
                                <i class="fa-solid fa-barcode"></i>
                                <span>Sync Resi/Order (Picklist)</span>
                            </button>
                            <button onclick="openOcsSyncModal()" type="button" class="flex-1 sm:flex-none bg-slate-800 hover:bg-slate-900 text-white text-xs px-4 py-2.5 rounded-xl font-bold transition flex items-center justify-center gap-2 shadow-sm shadow-slate-800/25">
                                <i class="fa-solid fa-cloud-arrow-down text-sm"></i>
                                <span>Pilihan Sync OCS</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Data Orders Container -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <!-- Filter Toolbar 1 Baris Tanpa Icon -->
                    <div class="p-3 border-b border-slate-200 bg-slate-50/70 overflow-x-auto">
                        <div class="flex items-center gap-2 min-w-max">
                            <!-- Search -->
                            <input type="text" id="orderSearchInput" onkeyup="debounceOrderSearch()" placeholder="Cari Resi, Order ID, SKU, Toko..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                class="w-48 sm:w-56 px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs shrink-0">

                            <!-- Filter Platform -->
                            <select id="orderPlatformFilter" onchange="loadOrdersTable(1)" class="bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs shrink-0">
                                <option value="ALL">Semua Platform</option>
                                <option value="SHOPEE">Shopee</option>
                                <option value="TIKTOK_SHOP">TikTok Shop</option>
                                <option value="TOKOPEDIA">Tokopedia</option>
                                <option value="LAZADA">Lazada</option>
                            </select>

                            <!-- Filter Toko -->
                            <select id="orderShopFilter" onchange="loadOrdersTable(1)" class="bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs max-w-[150px] truncate shrink-0">
                                <option value="ALL">Semua Toko</option>
                            </select>

                            <!-- Filter Ekspedisi -->
                            <select id="orderShippingFilter" onchange="loadOrdersTable(1)" class="bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs max-w-[150px] truncate shrink-0">
                                <option value="ALL">Semua Ekspedisi</option>
                            </select>

                            <!-- Filter Status Order -->
                            <select id="orderStatusFilter" onchange="loadOrdersTable(1)" class="bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs max-w-[140px] truncate shrink-0">
                                <option value="ALL">Semua Status</option>
                            </select>

                            <!-- Filter Status Resi -->
                            <select id="orderResiFilter" onchange="loadOrdersTable(1)" class="bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs shrink-0">
                                <option value="ALL">Semua Resi</option>
                                <option value="with_resi">Ada Resi</option>
                                <option value="no_resi">Tanpa Resi</option>
                            </select>

                            <!-- Filter Periode Tanggal -->
                            <select id="orderDateFilter" onchange="onOrderDateFilterChanged()" class="bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs shrink-0">
                                <option value="">Semua Periode</option>
                                <option value="today" selected>Hari Ini</option>
                                <option value="yesterday">Hari Kemarin</option>
                                <option value="last7">7 Hari Terakhir</option>
                                <option value="last30">30 Hari Terakhir</option>
                                <option value="custom">Pilih Tanggal...</option>
                            </select>

                            <div id="orderCustomDateBox" class="hidden flex items-center gap-1 shrink-0">
                                <input type="date" id="orderStartDate" onchange="loadOrdersTable(1)" class="bg-white border border-slate-300 rounded-xl px-2 py-1 text-xs text-slate-700">
                                <span class="text-xs text-slate-400">s/d</span>
                                <input type="date" id="orderEndDate" onchange="loadOrdersTable(1)" class="bg-white border border-slate-300 rounded-xl px-2 py-1 text-xs text-slate-700">
                            </div>

                            <!-- Limit Baris -->
                            <select id="orderLimitSelect" onchange="loadOrdersTable(1)" class="bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-slate-700 shrink-0">
                                <option value="25">25 Baris</option>
                                <option value="50">50 Baris</option>
                                <option value="100">100 Baris</option>
                                <option value="250">250 Baris</option>
                                <option value="500">500 Baris</option>
                                <option value="1000">1.000 Baris</option>
                            </select>

                            <!-- Tombol Reset -->
                            <button type="button" onclick="resetOrderFilters()" class="px-3 py-1.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold transition shrink-0" title="Reset Semua Filter">
                                Reset
                            </button>

                            <!-- Tombol Refresh / Terapkan -->
                            <button type="button" onclick="loadOrdersTable(1)" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition shrink-0" title="Refresh / Cari Data">
                                Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Table Data -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[10px] tracking-wider">
                                <tr>
                                    <th class="p-3 whitespace-nowrap">No. Pesanan (Order ID)</th>
                                    <th class="p-3 whitespace-nowrap">No. Resi (Tracking)</th>
                                    <th class="p-3 whitespace-nowrap">Toko & Ekspedisi</th>
                                    <th class="p-3 min-w-[200px] max-w-[320px]">Produk & SKU</th>
                                    <th class="p-3 whitespace-nowrap text-right">Total Harga</th>
                                    <th class="p-3 whitespace-nowrap text-right">Total Klaim</th>
                                    <th class="p-3 whitespace-nowrap">Tanggal Order</th>
                                    <th class="p-3 whitespace-nowrap text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="8" class="text-center py-12 text-slate-400">
                                        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat data orders...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="p-3.5 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50 text-xs">
                        <span id="orderPaginationInfo" class="text-slate-500 font-medium">Menampilkan 0 dari 0 data</span>
                        <div id="orderPaginationControls" class="flex items-center gap-1">
                            <!-- Tombol Pagination -->
                        </div>
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
                                <input type="text" id="filterProductSearch" onkeyup="filterProductTable()" placeholder="Cari nama/barcode/sku/sap..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 w-44 sm:w-60">
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
                            <input type="text" id="filterExpeditionSearch" oninput="filterExpeditionTable()" placeholder="Cari nama / kode ekspedisi..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
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
                            <input type="text" id="filterConditionSearch" oninput="filterConditionTable()" placeholder="Cari kode / nama kondisi..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
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
                            <input type="text" id="filterUserSearch" oninput="filterUserTable()" placeholder="Cari nama / username..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
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

                        <!-- Pengaturan Rekening Bank (Telah Dipisah) -->
                        <div class="bg-emerald-50/50 rounded-2xl border border-emerald-200 p-5 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg shrink-0">
                                    <i class="fa-solid fa-building-columns"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm text-slate-800">Pengaturan Rekening Bank (Invoice Klaim)</h4>
                                    <p class="text-xs text-slate-500">
                                        Pengaturan bank telah dipisahkan ke menu tersendiri agar dapat diakses khusus oleh Management &amp; Accounting.
                                    </p>
                                </div>
                            </div>
                            <button type="button" onclick="switchTab('bank-settings')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                                <span>Buka Pengaturan Bank</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
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
            <?php endif; ?>

            <!-- TAB: PENGATURAN BANK (KHUSUS MANAGEMENT & ACCOUNTING & SUPERADMIN) -->
            <?php if ($isSuperAdmin || $isAdmin || $isManagement || $isAccounting): ?>
            <div id="tab-bank-settings" class="tab-content hidden space-y-5">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0 border border-emerald-200">
                                <i class="fa-solid fa-building-columns"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-slate-800">Pengaturan Rekening Bank (Invoice Klaim)</h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Informasi rekening resmi yang akan tercetak otomatis pada lembar penagihan / invoice klaim ekspedisi.
                                </p>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shrink-0 self-start sm:self-auto">
                            <i class="fa-solid fa-shield-halved mr-1"></i> Khusus Management &amp; Accounting
                        </span>
                    </div>

                    <form id="formBankSettingsStandalone" onsubmit="saveBankSettingsStandalone(event)" class="max-w-3xl space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Nama Bank <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" id="standaloneBankName" name="bank_name" placeholder="Contoh: BCA (Bank Central Asia)" required
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Nomor Rekening <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" id="standaloneBankAccountNumber" name="bank_account_number" placeholder="Contoh: 873-098-1234" required
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Atas Nama (Pemilik Rekening) <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" id="standaloneBankAccountHolder" name="bank_account_holder" placeholder="Contoh: PT. INOVASI EKA GEMILANG" required
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Catatan Pembayaran / Transfer (Opsional)
                                </label>
                                <input type="text" id="standaloneBankPaymentNotes" name="bank_payment_notes" placeholder="Contoh: *Mohon sertakan no invoice saat transfer"
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-800 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition">
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <span class="text-xs text-slate-400">
                                <i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> Data langsung tersimpan ke database &amp; tampil di cetak invoice klaim.
                            </span>
                            <button type="submit" id="btnSaveStandaloneBank" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold text-xs rounded-xl transition shadow-md shadow-emerald-600/30 flex items-center gap-2 cursor-pointer">
                                <i class="fa-solid fa-floppy-disk"></i>
                                <span>Simpan Pengaturan Bank</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB: KELOLA ROLE (ADD, EDIT, DELETE ROLE) -->
            <?php if ($isSuperAdmin || $isAdmin): ?>
            <div id="tab-roles" class="tab-content hidden space-y-5">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl shrink-0 border border-purple-200">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-slate-800">Kelola Role &amp; Hak Akses</h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Tambah role baru, ubah nama, deskripsi, dan hapus role yang tidak lagi digunakan.
                                </p>
                            </div>
                        </div>
                        <button type="button" onclick="openAddRoleModal()" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 active:scale-95 text-white font-bold text-xs rounded-xl transition shadow-md shadow-purple-600/30 flex items-center gap-2 shrink-0 cursor-pointer">
                            <i class="fa-solid fa-plus"></i>
                            <span>Tambah Role Baru</span>
                        </button>
                    </div>

                    <!-- Roles Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="py-3 px-4">Kode Role (Key)</th>
                                    <th class="py-3 px-4">Nama Role</th>
                                    <th class="py-3 px-4">Deskripsi</th>
                                    <th class="py-3 px-4 text-center">Jumlah Pengguna</th>
                                    <th class="py-3 px-4 text-center">Tipe Role</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="rolesTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="6" class="text-center py-10 text-slate-400">
                                        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat daftar role...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB: APPROVAL KLAIM J&T (KHUSUS MANAGEMENT & ACCOUNTING & SUPERADMIN) -->
            <?php if ($isSuperAdmin || $isAdmin || $isManagement || $isAccounting): ?>
            <div id="tab-approval-jnt" class="tab-content hidden space-y-6">
                <!-- Header Banner -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shrink-0 border border-rose-200">
                            <i class="fa-solid fa-stamp"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="font-bold text-base text-slate-800">Approval Klaim Ekspedisi J&amp;T</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-700 border border-rose-200 uppercase tracking-wider">Khusus J&amp;T</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Verifikasi berkas tagihan invoice klaim retur rusak J&amp;T resmi oleh Accounting &amp; Management dengan e-Sign digital.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="loadJntClaimsData()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition border border-slate-200 flex items-center gap-1.5 cursor-pointer" title="Muat Ulang Data">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>Segarkan</span>
                        </button>
                    </div>
                </div>

                <!-- Top KPI Metrics -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-file-invoice"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Klaim J&amp;T</div>
                            <div class="text-xl sm:text-2xl font-black text-slate-900" id="jntStatTotalAll">0</div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-amber-200 bg-amber-50/20 shadow-xs flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Menunggu Approval</div>
                            <div class="text-xl sm:text-2xl font-black text-amber-700" id="jntStatPending">0</div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-emerald-200 bg-emerald-50/20 shadow-xs flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Disetujui (Approved)</div>
                            <div class="text-xl sm:text-2xl font-black text-emerald-700" id="jntStatApproved">0</div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-blue-200 bg-blue-50/20 shadow-xs flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <div class="truncate">
                            <div class="text-[11px] font-bold text-blue-700 uppercase tracking-wider">Total Nilai Disetujui</div>
                            <div class="text-lg sm:text-xl font-black text-blue-800 truncate" id="jntStatApprovedAmount">Rp 0</div>
                        </div>
                    </div>
                </div>

                <!-- Filter & Action Toolbar -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-center space-x-1.5 overflow-x-auto pb-1 md:pb-0">
                        <button onclick="setFilterJntStatus('ALL')" id="jntTabFilterALL" class="jnt-status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition bg-slate-900 text-white cursor-pointer">
                            Semua
                        </button>
                        <button onclick="setFilterJntStatus('PENDING')" id="jntTabFilterPENDING" class="jnt-status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition text-slate-600 hover:bg-slate-100 cursor-pointer">
                            Menunggu Approval (<span id="jntTabCountPending">0</span>)
                        </button>
                        <button onclick="setFilterJntStatus('APPROVED')" id="jntTabFilterAPPROVED" class="jnt-status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition text-slate-600 hover:bg-slate-100 cursor-pointer">
                            Disetujui (<span id="jntTabCountApproved">0</span>)
                        </button>
                        <button onclick="setFilterJntStatus('REJECTED')" id="jntTabFilterREJECTED" class="jnt-status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition text-slate-600 hover:bg-slate-100 cursor-pointer">
                            Ditolak
                        </button>
                    </div>

                    <div class="flex items-center space-x-2">
                        <div class="relative flex-1 md:w-64">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" id="jntSearchInput" placeholder="Cari Resi, Order, Produk..." oninput="handleJntSearch(this.value)" class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-rose-500">
                        </div>

                        <button onclick="openBulkApproveJntModal()" id="btnJntBulkApprove" class="inline-flex items-center space-x-1.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition disabled:opacity-50 disabled:pointer-events-none cursor-pointer" disabled>
                            <i class="fa-solid fa-stamp"></i>
                            <span>Setujui Terpilih</span>
                        </button>
                    </div>
                </div>

                <!-- Claims Table Card -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="py-3 px-3.5 text-center w-10">
                                        <input type="checkbox" id="jntCheckAll" onchange="toggleSelectAllJnt(this)" class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 cursor-pointer">
                                    </th>
                                    <th class="py-3 px-3.5">No. Resi J&amp;T / Order ID</th>
                                    <th class="py-3 px-3.5">Tgl Unboxing / Petugas</th>
                                    <th class="py-3 px-3.5">Rincian Barang Rusak &amp; Alasan</th>
                                    <th class="py-3 px-3.5 text-right">Nilai Tagihan (OCS)</th>
                                    <th class="py-3 px-3.5 text-center">Status Approval</th>
                                    <th class="py-3 px-3.5 text-center w-28">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="jntClaimsTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-rose-500"></i>
                                        <div>Memuat data klaim J&amp;T...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3.5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <div id="jntSelectedCountText">0 baris dipilih</div>
                        <div id="jntTableTotalText">Total: 0 klaim</div>
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
                            <option value="admin">Admin Retrun</option>
                            <option value="management">Management</option>
                            <option value="accounting">Accounting</option>
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

    <!-- MODAL TAMBAH / EDIT ROLE -->
    <div id="modalRole" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-base">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800" id="modalRoleTitle">Tambah Role Baru</h3>
                    </div>
                </div>
                <button onclick="closeRoleModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="formRole" onsubmit="saveRole(event)" class="space-y-3.5 text-xs">
                <input type="hidden" id="roleEditId" value="">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kode Role (Key) <span class="text-rose-500">*</span></label>
                    <input type="text" id="roleInputKey" required placeholder="Contoh: accounting, finance, supervisor"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono font-bold focus:ring-2 focus:ring-purple-500 focus:outline-none">
                    <p class="text-[10px] text-slate-400 mt-0.5">Hanya huruf kecil, angka, dan garis bawah (_).</p>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Tampilan Role <span class="text-rose-500">*</span></label>
                    <input type="text" id="roleInputName" required placeholder="Contoh: Accounting, Management"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-purple-500 focus:outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Deskripsi Hak Akses</label>
                    <textarea id="roleInputDesc" rows="2" placeholder="Jelaskan wewenang dan hak akses role ini..."
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button type="button" onclick="closeRoleModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold transition">
                        Batal
                    </button>
                    <button type="submit" id="btnSubmitRole" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold shadow-md shadow-purple-600/30 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Simpan Role</span>
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
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-base tracking-tight" id="modalDetailInvoice">INV-XXXXXX</h3>
                            <span id="modalDetailExpedition" class="bg-indigo-500/20 text-indigo-300 text-[10px] font-semibold px-2 py-0.5 rounded border border-indigo-500/30">Kurir</span>
                            <span id="modalDetailConditionBadge" class="hidden text-[10px] font-bold px-2.5 py-0.5 rounded-lg border font-mono shadow-2xs"></span>
                        </div>
                        <p class="text-[11px] text-slate-400" id="modalDetailMeta">Operator &bull; Waktu Transaksi</p>
                        <div id="modalDetailDamageAlert" class="hidden mt-1 px-2.5 py-1 bg-rose-500/20 border border-rose-500/40 rounded-lg text-rose-200 text-[11px] font-medium flex items-center gap-1.5">
                            <i class="fa-solid fa-triangle-exclamation text-rose-400 text-xs"></i>
                            <span>Catatan Kerusakan: <b id="modalDetailDamageText" class="text-white">-</b></span>
                        </div>
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

                <!-- GALERI FOTO DOKUMENTASI UNBOXING -->
                <div id="modalPhotosSection" class="hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-images text-indigo-500"></i>
                            Foto Dokumentasi Unboxing
                            <span id="modalPhotoCount" class="text-[10px] bg-indigo-50 text-indigo-600 border border-indigo-200 px-1.5 py-0.5 rounded font-bold">0 Foto</span>
                        </span>
                        <span class="text-[10px] text-slate-400">Klik foto untuk perbesar</span>
                    </div>
                    <div id="modalPhotosGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                        <!-- Foto di-render oleh JS -->
                    </div>
                </div>

                <!-- Lightbox Foto Full dengan Header Status Kondisi -->
                <div id="modalPhotoLightbox" class="fixed inset-0 bg-black/95 z-[60] hidden flex-col items-center justify-center p-3 sm:p-5" onclick="if(event.target === this || event.target.id === 'modalPhotoLightboxBackdrop') { this.classList.add('hidden'); this.classList.remove('flex'); }">
                    <div id="modalPhotoLightboxBackdrop" class="absolute inset-0"></div>
                    <div class="relative z-10 max-w-5xl w-full max-h-[95vh] flex flex-col items-center bg-slate-900 rounded-2xl overflow-hidden shadow-2xl border border-slate-700/80">
                        <!-- Header Lightbox: Status Kondisi & Actions -->
                        <div class="w-full bg-slate-900/95 text-white px-4 py-2.5 flex items-center justify-between gap-3 border-b border-slate-800 text-xs shrink-0">
                            <div class="flex items-center gap-2 truncate">
                                <span id="modalPhotoLightboxTag" class="px-2.5 py-0.5 rounded-lg font-mono font-bold text-[10px] bg-rose-600 text-white shadow-2xs">KONDISI: RUSAK</span>
                                <span id="modalPhotoLightboxTitle" class="font-bold truncate text-slate-200">Foto Bukti Unboxing</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a id="modalPhotoLightboxDownload" href="#" download="foto_unboxing.jpg" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition shadow-2xs">
                                    <i class="fa-solid fa-download text-[11px]"></i> Unduh Foto
                                </a>
                                <button type="button" class="text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 text-sm w-7 h-7 rounded-lg flex items-center justify-center transition" onclick="document.getElementById('modalPhotoLightbox').classList.add('hidden'); document.getElementById('modalPhotoLightbox').classList.remove('flex');">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                        <!-- Frame Gambar Utama -->
                        <div class="w-full flex-1 min-h-0 bg-black flex items-center justify-center p-1 sm:p-2 overflow-hidden">
                            <img id="modalPhotoLightboxImg" src="" alt="Foto Unboxing" class="max-w-full max-h-[82vh] object-contain rounded-lg">
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
            <div class="p-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="editCurrentModalSession()" class="text-amber-800 hover:text-white hover:bg-amber-600 bg-amber-50 border border-amber-300 px-3.5 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 shadow-2xs cursor-pointer">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Transaksi
                    </button>
                    <button type="button" onclick="deleteCurrentModalSession()" class="text-rose-600 hover:text-white hover:bg-rose-600 border border-rose-300 px-3.5 py-2 rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5 shadow-2xs cursor-pointer">
                        <i class="fa-solid fa-trash-can"></i> Hapus Sesi
                    </button>
                </div>
                <button type="button" onclick="closeDetailModal()" class="bg-slate-800 hover:bg-slate-700 text-white px-5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT DATA TRANSAKSI UNBOXING & PRODUK -->
    <div id="modalEditUnboxing" class="fixed inset-0 bg-black/80 backdrop-blur-xs z-50 flex items-center justify-center hidden p-3 md:p-6 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-3xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <!-- Modal Header -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center border-b border-slate-800 shrink-0">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500 text-slate-950 flex items-center justify-center font-bold text-base shadow-sm">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-base tracking-tight">Edit Transaksi Unboxing</h3>
                            <span id="badgeEditUnboxInvoice" class="bg-amber-500/20 text-amber-300 text-[10px] font-mono font-bold px-2 py-0.5 rounded border border-amber-500/40">INV-XXX</span>
                        </div>
                        <p class="text-[11px] text-slate-400">Perbarui nomor resi, ekspedisi, operator, kondisi fisik atau rincian item unboxing</p>
                    </div>
                </div>
                <button type="button" onclick="closeEditUnboxingModal()" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition cursor-pointer">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <!-- Modal Form Body -->
            <form id="formEditUnboxing" onsubmit="saveEditUnboxingTransaction(event)" class="flex flex-col flex-1 min-h-0">
                <input type="hidden" id="editUnboxSessionId" value="">

                <div class="p-5 overflow-y-auto flex-1 space-y-5">
                    <!-- Seksi 1: Data Sesi Header -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-file-invoice text-indigo-600"></i>
                            <span>Informasi Sesi Transaksi</span>
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Nomor Resi / Invoice <span class="text-rose-500">*</span></label>
                                <input type="text" id="editUnboxInvoice" required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Jasa Ekspedisi</label>
                                <select id="editUnboxExpedition" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="SPX">Shopee Xpress (SPX)</option>
                                    <option value="J&T">J&T Express</option>
                                    <option value="SiCepat">SiCepat Ekspres</option>
                                    <option value="JNE">JNE Express</option>
                                    <option value="GoSend">GoSend / Grab</option>
                                    <option value="AnterAja">AnterAja</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Petugas (Operator)</label>
                                <input type="text" id="editUnboxOperator" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Keseluruhan Sesi Unboxing</label>
                            <textarea id="editUnboxNotes" rows="2" placeholder="Catatan opsional..." class="w-full bg-white border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Seksi 2: Rincian Produk Unboxing -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-boxes-stacked text-indigo-600"></i>
                                <span>Rincian Item Produk Unboxing</span>
                            </h4>
                            <span class="text-[11px] text-slate-500">Ubah kondisi, qty, atau alasan kerusakan barang</span>
                        </div>
                        
                        <div id="editUnboxItemsList" class="space-y-3">
                            <!-- Diisi secara dinamis oleh JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
                    <button type="button" onclick="closeEditUnboxingModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 active:scale-95 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" id="btnSaveEditUnboxing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs font-black rounded-xl transition flex items-center gap-2 shadow-sm shadow-indigo-600/30 cursor-pointer">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
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
        <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
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

            <!-- Content Area (Printable: FORMAT SURAT JALAN INBOUND RETUR RESMI) -->
            <div id="printableReceivingReceiptArea" class="p-6 overflow-y-auto space-y-3.5 flex-1 text-xs">
                <!-- Header Surat Jalan dengan Logo IEG Resmi -->
                <div class="sj-header border-b-2 border-slate-900 pb-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="h-14 w-auto object-contain shrink-0">
                        <div>
                            <h2 class="font-black text-sm sm:text-base tracking-tight text-slate-900 leading-tight">IEG Inovasi Eka Gemilang</h2>
                            <p class="text-[10px] text-slate-600 font-bold uppercase tracking-wider">Warehouse Return &amp; Dispute</p>
                            <p class="text-[9px] text-slate-400">Inbound Reception Department • Tanda Terima Fisik Barang Retur</p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="inline-block bg-slate-900 text-white font-black text-[10px] sm:text-[11px] px-2.5 py-0.5 rounded tracking-wider uppercase mb-1 shadow-2xs">
                            SURAT JALAN INBOUND RETUR
                        </div>
                        <div class="font-mono font-black text-xs sm:text-sm text-emerald-700" id="adminSlipReceiptNo">RCV-20260929-0001</div>
                        <div class="text-[9px] text-slate-500 font-medium">Status: <span class="text-emerald-700 font-bold">VERIFIKASI SAH</span></div>
                    </div>
                </div>

                <!-- Info Grid: Tabel Detail Serah Terima Formal -->
                <div class="border border-slate-300 rounded-xl overflow-hidden shadow-2xs">
                    <table class="sj-info-table w-full text-xs">
                        <tr class="border-b border-slate-200">
                            <td class="w-1/4 bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Jasa Ekspedisi</td>
                            <td class="w-1/4 py-2 px-3 font-bold text-slate-900 text-xs" id="adminSlipExpedition">-</td>
                            <td class="w-1/4 bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Waktu Serah Terima</td>
                            <td class="w-1/4 py-2 px-3 font-mono font-semibold text-slate-800" id="adminSlipDateTime">-</td>
                        </tr>
                        <tr class="border-b border-slate-200">
                            <td class="bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Driver / Kurir Pengantar</td>
                            <td class="py-2 px-3 font-semibold text-slate-800" id="adminSlipCourier">-</td>
                            <td class="bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Petugas Penerima (Gudang)</td>
                            <td class="py-2 px-3 font-bold text-slate-900" id="adminSlipOperator">-</td>
                        </tr>
                        <tr>
                            <td class="bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Nomor Karung Terdaftar</td>
                            <td class="py-2 px-3 font-mono font-bold text-amber-800" id="adminSlipSackNumber">-</td>
                            <td class="bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Total Karung / Bag</td>
                            <td class="py-2 px-3 font-mono font-black text-amber-900" id="adminSlipTotalSacksCount">0 Karung</td>
                        </tr>
                    </table>
                </div>

                <!-- Total Count Banner -->
                <div class="total-banner bg-emerald-600 text-white rounded-xl p-3 flex items-center justify-between shadow-2xs">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-white text-sm font-bold shrink-0">
                            <i class="fa-solid fa-boxes-packing"></i>
                        </span>
                        <div>
                            <span class="text-[10px] uppercase font-bold tracking-wider text-emerald-100 block">TOTAL KUANTITAS PAKET DITERIMA FISIK:</span>
                            <span class="text-[11px] text-emerald-50">Seluruh paket fisik telah dihitung &amp; diverifikasi via scan barcode di stasiun receiving inbound.</span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span id="adminSlipTotalPackages" class="font-black text-2xl font-mono leading-none">0</span>
                        <span class="text-xs font-bold text-emerald-100 ml-1">Paket</span>
                    </div>
                </div>

                <!-- Rekap Total Paket Per Karung (Tampil di Layar & Cetak Fisik) -->
                <div id="adminSlipSackBreakdownSection" class="border border-amber-200 bg-amber-50/70 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-bold text-amber-950 text-[11px] uppercase flex items-center gap-1.5">
                            <i class="fa-solid fa-boxes-stacked text-amber-600"></i>
                            <span>Rekapitulasi Paket Per Karung / Bag:</span>
                        </h4>
                    </div>
                    <div id="adminSlipSackBreakdownList" class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <!-- Dynamic items -->
                    </div>
                </div>

                <!-- Daftar Resi Paket -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-slate-800 text-[11px] uppercase flex items-center gap-1.5">
                            <i class="fa-solid fa-barcode text-indigo-600"></i>
                            <span>Lampiran Rincian Nomor Resi / Barcode Paket:</span>
                        </h4>
                        <span class="text-[10px] text-slate-500 font-medium">Status Seluruh Resi: <b class="text-emerald-700">DITERIMA LENGKAP</b></span>
                    </div>
                    <div id="adminSlipPackageList" class="bg-slate-50 rounded-xl p-2.5 max-h-56 overflow-y-auto grid grid-cols-2 gap-1.5 font-mono text-[11px] border border-slate-200">
                        <!-- List Resi -->
                    </div>
                </div>

                <!-- Tanda Tangan Serah Terima 3 Pihak (Untuk Cetak Fisik Formal) -->
                <div class="signatures-box pt-3 space-y-2.5">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="border border-slate-200 rounded-xl p-2 bg-slate-50/60">
                            <p class="text-[9px] text-slate-500 font-bold uppercase mb-12">Yang Menyerahkan (Kurir/Driver)</p>
                            <p class="text-[10px] font-bold text-slate-800 border-t border-slate-400 mx-2 pt-1">( .................................... )</p>
                            <p class="text-[8px] text-slate-400 mt-0.5">Tanda Tangan &amp; Nama Terang</p>
                        </div>
                        <div class="border border-slate-200 rounded-xl p-2 bg-slate-50/60">
                            <p class="text-[9px] text-slate-500 font-bold uppercase mb-12">Yang Memeriksa &amp; Menerima</p>
                            <p id="adminSlipSignOperator" class="text-[10px] font-bold text-slate-800 border-t border-slate-400 mx-2 pt-1">Gudang</p>
                            <p class="text-[8px] text-slate-400 mt-0.5">Petugas Inbound Warehouse</p>
                        </div>
                        <div class="border border-slate-200 rounded-xl p-2 bg-slate-50/60">
                            <p class="text-[9px] text-slate-500 font-bold uppercase mb-12">Mengetahui / Disetujui</p>
                            <p class="text-[10px] font-bold text-slate-800 border-t border-slate-400 mx-2 pt-1">( .................................... )</p>
                            <p class="text-[8px] text-slate-400 mt-0.5">Supervisor Inbound / WH Lead</p>
                        </div>
                    </div>
                    <div class="text-[8.5px] text-slate-400 text-center italic leading-tight pt-1">
                        * Dokumen Surat Jalan ini sah dan mengikat sebagai bukti serah terima fisik paket retur antara pihak Ekspedisi dan Gudang Inbound IEG Inovasi Eka Gemilang.
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

    <!-- MODAL DETAIL PAKET & FOTO HISTORY RECEIVING -->
    <div id="modalReceivingPackages" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <!-- Header Modal -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-sm border border-indigo-500/30">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </span>
                    <div>
                        <h3 class="font-bold text-sm leading-tight" id="pkgModalTitle">Detail Paket History Receiving</h3>
                        <p class="text-[10px] text-slate-400 font-mono" id="pkgModalSubtitle">Rincian tanda terima fisik &amp; dokumentasi foto paket</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printCurrentReceivingFromModal()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold flex items-center gap-1.5 transition border border-slate-700 shadow-2xs" title="Lihat Bukti Tanda Terima & Cetak">
                        <i class="fa-solid fa-print text-indigo-400"></i>
                        <span class="hidden sm:inline">Bukti Serah Terima</span>
                    </button>
                    <button onclick="closeReceivingPackagesModal()" class="text-slate-400 hover:text-white text-base p-1.5 rounded-lg hover:bg-slate-800 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Reception Summary Card -->
            <div class="p-4 bg-slate-50 border-b border-slate-200/80 shrink-0 space-y-3">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/90 shadow-2xs">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block mb-0.5">No. Tanda Terima</span>
                        <div class="font-mono font-black text-emerald-700 text-xs truncate" id="pkgModalReceiptNo">-</div>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/90 shadow-2xs">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block mb-0.5">Ekspedisi</span>
                        <div class="font-bold text-slate-800 text-xs truncate" id="pkgModalExpeditionCourier">-</div>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/90 shadow-2xs">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block mb-0.5">Waktu Serah Terima</span>
                        <div class="font-mono text-slate-700 text-xs truncate" id="pkgModalTime">-</div>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/90 shadow-2xs">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block mb-0.5">Total &amp; Foto</span>
                        <div class="font-bold text-indigo-700 text-xs flex items-center justify-between">
                            <span id="pkgModalTotal">0 Paket</span>
                            <span class="text-[10px] bg-indigo-50 text-indigo-600 px-1.5 py-0.2 rounded font-semibold border border-indigo-200/60" id="pkgModalPhotoCount">0 Foto</span>
                        </div>
                    </div>
                </div>

                <!-- Identitas Kurir & PIC Petugas Penerima Gudang -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <!-- Kurir Ekspedisi -->
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/90 flex items-center gap-3 shadow-2xs">
                        <div class="shrink-0 relative">
                            <img id="pkgModalCourierImg" src="" alt="Foto Kurir" class="w-12 h-12 rounded-xl object-cover border border-slate-200 cursor-pointer hover:scale-105 transition hidden shadow-2xs" onclick="openClaimPhotoModal(this.src, 'Foto Kurir Serah Terima')" title="Klik untuk memperbesar foto kurir">
                            <div id="pkgModalCourierAvatarPlaceholder" class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400">
                                <i class="fa-solid fa-truck-fast text-lg text-slate-400"></i>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1 text-xs">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block">Identitas Driver / Kurir:</span>
                            <span id="pkgModalCourierName" class="font-bold text-slate-800 text-xs truncate block">-</span>
                            <span id="pkgModalCourierMeta" class="text-[10px] text-slate-500 font-mono block truncate">-</span>
                        </div>
                    </div>

                    <!-- PIC Petugas Penerima Gudang -->
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/90 flex items-center gap-3 shadow-2xs">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shrink-0">
                            <i class="fa-solid fa-user-check text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1 text-xs">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block">Petugas Penerima (PIC Gudang):</span>
                            <span id="pkgModalOperatorName" class="font-bold text-emerald-800 text-xs truncate block">-</span>
                            <span class="text-[10px] text-slate-500 block truncate">Inbound Warehouse Staff</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Bar & Controls -->
            <div class="px-4 pt-3 pb-2 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs shrink-0">
                <div class="relative w-full sm:w-72">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="pkgModalSearchInput" oninput="filterReceivingPackagesModal(this.value)" placeholder="Cari resi paket / karung..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button" onclick="copyAllReceivingBarcodes()" class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold border border-indigo-200 text-xs flex items-center gap-1.5 transition shadow-2xs">
                        <i class="fa-regular fa-copy"></i> Salin Semua Resi
                    </button>
                </div>
            </div>

            <!-- Package Cards with Photos -->
            <div class="p-4 overflow-y-auto space-y-2 flex-1 text-xs">
                <div id="pkgModalList" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <!-- Dynamic Package Cards -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
                <span class="text-[11px] text-slate-500 font-medium hidden sm:inline"><i class="fa-regular fa-circle-question mr-1 text-slate-400"></i>Klik foto paket untuk memperbesar resolusi tinggi</span>
                <div class="flex items-center gap-2 ml-auto">
                    <button onclick="closeReceivingPackagesModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL KANDIDAT KLAIM & DOSSIER BUKTI LENGKAP -->
    <div id="modalClaimDetail" class="hidden fixed inset-0 z-50 bg-slate-900/75 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
        <div class="bg-white rounded-3xl max-w-5xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200">
            <!-- Header Modal -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-base border border-amber-500/30">
                        <i class="fa-solid fa-shield-halved"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-black text-sm sm:text-base leading-tight" id="mClaimInvoiceTitle">Berkas Detail Klaim</h3>
                            <span id="mClaimStatusBadge" class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-rose-600 text-white">RUSAK / LAYAK KLAIM</span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-mono" id="mClaimSubtitle">Dokumen bukti kerusakan paket & komparasi data OCS</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printClaimFromModal()" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1.5 transition shadow-2xs" title="Cetak Berkas Klaim Resmi (PDF)">
                        <i class="fa-solid fa-print"></i>
                        <span class="hidden sm:inline">Cetak PDF</span>
                    </button>
                    <button type="button" onclick="openClaimDossierFullTab()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-1.5 transition border border-slate-700" title="Buka Halaman Penuh di Tab Baru">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </button>
                    <button onclick="closeClaimDetailModal()" class="text-slate-400 hover:text-white text-base p-1.5 rounded-lg hover:bg-slate-800 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Scrollable Modal Content Body -->
            <div class="p-4 sm:p-5 overflow-y-auto space-y-4 flex-1 text-xs">
                
                <!-- Loading State inside Modal -->
                <div id="mClaimLoading" class="py-12 text-center text-slate-500 space-y-3">
                    <i class="fa-solid fa-spinner fa-spin text-3xl text-amber-500"></i>
                    <p class="font-bold">Memuat berkas dossier klaim...</p>
                </div>

                <!-- Main Content (hidden while loading) -->
                <div id="mClaimBody" class="hidden space-y-4">
                    
                    <!-- 1. Banner Kelayakan & Status Klaim -->
                    <div id="mClaimBanner" class="p-3.5 rounded-2xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-rose-50 border-rose-200 text-rose-950">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-sm font-bold shrink-0">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </span>
                            <div>
                                <b class="font-black text-xs block text-rose-900" id="mClaimBannerTitle">Status: Paket Rusak / Layak Klaim</b>
                                <span class="text-[11px] text-rose-800" id="mClaimBannerDesc">Kerusakan barang terverifikasi dari stasiun unboxing retur gudang.</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="px-2.5 py-1 rounded-lg bg-rose-600 text-white font-black text-[10px] uppercase tracking-wider" id="mClaimBadgeTag">LAYAK KLAIM</span>
                        </div>
                    </div>

                    <!-- 2. Ringkasan Finansial & Data Ekspedisi (4-Card Grid) -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
                        <!-- Biaya Paket -->
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/90 shadow-2xs">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block mb-0.5">Biaya / Harga Barang (NMV)</span>
                            <div class="font-black text-emerald-700 text-base font-mono truncate" id="mClaimPrice">Rp -</div>
                            <span class="text-[9px] text-slate-500 block truncate">Nilai bersih barang OCS</span>
                        </div>

                        <!-- Estimasi Tuntutan Klaim -->
                        <div class="bg-amber-50/70 p-3 rounded-2xl border border-amber-200 shadow-2xs">
                            <span class="text-[9px] uppercase font-bold text-amber-800 block mb-0.5">Total Tuntutan Klaim</span>
                            <div class="font-black text-amber-700 text-base font-mono truncate" id="mClaimTotalClaim">Rp -</div>
                            <span class="text-[9px] text-amber-700 block truncate">Estimasi diajukan ke ekspedisi</span>
                        </div>

                        <!-- Ekspedisi & Resi -->
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/90 shadow-2xs">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block mb-0.5">Ekspedisi & Resi</span>
                            <div class="font-bold text-slate-800 text-xs truncate" id="mClaimExpedition">-</div>
                            <span class="font-mono text-[10px] text-indigo-600 font-bold block truncate" id="mClaimTrackingNo">-</span>
                        </div>

                        <!-- Toko / Marketplace -->
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/90 shadow-2xs">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block mb-0.5">Toko & Platform</span>
                            <div class="font-bold text-slate-800 text-xs truncate" id="mClaimShopName">-</div>
                            <span class="text-[10px] text-slate-500 font-medium block truncate" id="mClaimPlatform">Marketplace</span>
                        </div>
                    </div>

                    <!-- 3. Rincian Serah Terima Kurir & Unboxing Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/90 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Serah Terima Ekspedisi</span>
                                <div class="font-semibold text-slate-800" id="mClaimCourierInfo">-</div>
                                <span class="text-[10px] text-slate-500 font-mono" id="mClaimReceiptNo">Tanda Terima: -</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200" id="mClaimRecTag">Diterima</span>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/90 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Stasiun Unboxing Retur</span>
                                <div class="font-semibold text-slate-800" id="mClaimOperatorInfo">Operator: -</div>
                                <span class="text-[10px] text-slate-500" id="mClaimUnboxTime">-</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200" id="mClaimUnboxTag">Terekam</span>
                        </div>
                    </div>

                    <!-- 4. Bukti Media: Player Video Unboxing + Galeri Foto Bukti -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <!-- Video Player Unboxing Retur -->
                        <div class="bg-slate-900 rounded-2xl overflow-hidden flex flex-col border border-slate-800">
                            <div class="p-2.5 bg-slate-950 text-white flex items-center justify-between text-xs">
                                <span class="font-bold flex items-center gap-2">
                                    <i class="fa-solid fa-video text-rose-500"></i> Video Rekaman Unboxing Retur
                                </span>
                                <span class="text-[10px] text-slate-400" id="mClaimVideoOperator">Stasiun Unboxing</span>
                            </div>
                            <div class="relative bg-black aspect-video flex items-center justify-center">
                                <video id="mClaimVideoPlayer" controls class="w-full h-full object-contain hidden"></video>
                                <div id="mClaimNoVideoPlaceholder" class="text-center p-4 text-slate-400 space-y-1">
                                    <i class="fa-solid fa-video-slash text-2xl text-slate-600 block"></i>
                                    <span class="text-xs">Tidak ada video unboxing tersimpan untuk invoice ini.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Galeri Foto Bukti Kerusakan & Paket -->
                        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col">
                            <div class="p-2.5 bg-slate-100 border-b border-slate-200 flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-camera text-indigo-600"></i> Dokumentasi Foto Bukti (<span id="mClaimPhotoCount">0</span>)
                                </span>
                                <span class="text-[10px] text-slate-500">Klik foto untuk perbesar</span>
                            </div>
                            <div class="p-3 flex-1 min-h-[180px] max-h-[220px] overflow-y-auto">
                                <div id="mClaimPhotosGrid" class="grid grid-cols-3 gap-2">
                                    <!-- Dynamic Photos -->
                                </div>
                                <div id="mClaimNoPhotosPlaceholder" class="hidden text-center py-8 text-slate-400">
                                    <i class="fa-solid fa-images text-2xl mb-1 block"></i>
                                    <span>Belum ada foto bukti.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Tabel Rincian Barang di Paket & Kondisi Fisik -->
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-2xs">
                        <div class="p-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <h4 class="font-bold text-xs text-slate-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-boxes-stacked text-amber-600"></i> Rincian Produk & Kerusakan di Paket
                            </h4>
                            <span class="text-[10px] text-slate-500" id="mClaimItemsSummary">0 Produk Terdaftar</span>
                        </div>
                        <div class="overflow-x-auto max-h-56 overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-100 text-slate-600 text-[10px] font-bold uppercase sticky top-0">
                                    <tr>
                                        <th class="py-2 px-3">Barcode / SKU</th>
                                        <th class="py-2 px-3">Nama Produk</th>
                                        <th class="py-2 px-3 text-center">Qty</th>
                                        <th class="py-2 px-3 text-center">Kondisi</th>
                                        <th class="py-2 px-3">Detail Alasan / Kerusakan</th>
                                    </tr>
                                </thead>
                                <tbody id="mClaimItemsTableBody" class="divide-y divide-slate-100 font-medium">
                                    <!-- Dynamic items -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer Modal Actions -->
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 shrink-0">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="copyClaimPacketSummary()" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                        <i class="fa-regular fa-copy"></i> Salin Ringkasan
                    </button>
                    <button type="button" onclick="openClaimDossierFullTab()" class="px-3 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 border border-indigo-200">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Halaman Lengkap
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="closeClaimDetailModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PREVIEW INVOICE TAGIHAN KLAIM KOLEKTIF -->
    <div id="modalCollectiveClaimInvoice" class="hidden fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
        <div class="relative bg-white rounded-3xl border border-slate-200 max-w-4xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between bg-slate-900 text-white shrink-0">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-amber-500 text-slate-950 font-black flex items-center justify-center text-lg shadow-sm">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-black text-sm sm:text-base leading-tight">Surat Tagihan Klaim Kolektif</h3>
                            <span id="mColClaimBadgeCount" class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-amber-500 text-slate-950">0 Paket</span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-mono">Invoice kompilasi penggantian paket rusak untuk ekspedisi</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printCollectiveClaimInvoice()" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1.5 transition shadow-2xs cursor-pointer" title="Cetak Berkas Tagihan PDF">
                        <i class="fa-solid fa-print"></i>
                        <span class="hidden sm:inline">Cetak PDF</span>
                    </button>
                    <button type="button" onclick="openCollectiveClaimFullTab()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-1.5 transition border border-slate-700 cursor-pointer" title="Buka Halaman Penuh di Tab Baru">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </button>
                    <button type="button" onclick="closeCollectiveClaimModal()" class="text-slate-400 hover:text-white text-base p-1.5 rounded-lg hover:bg-slate-800 transition cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Scrollable Invoice Preview Body -->
            <div class="p-4 sm:p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                <div id="mColClaimPreviewContainer" class="space-y-4">
                    <!-- Dynamic Invoice Content Rendered via JS -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 shrink-0">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="copyCollectiveClaimText()" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-regular fa-copy"></i> Salin Format WA
                    </button>
                    <button type="button" onclick="openCollectiveClaimFullTab()" class="px-3 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 border border-indigo-200 cursor-pointer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Halaman Penuh
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeCollectiveClaimModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition cursor-pointer">
                        Tutup
                    </button>
                </div>
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

    <!-- MODAL KONFIGURASI SYNOLOGY NAS-IEG (192.168.30.5:5001) -->
    <div id="modalNasConfig" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-server"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-black text-slate-800">Koneksi Synology NAS-IEG</h3>
                        <p class="text-[11px] text-slate-400">File Station: Folder /PACKER</p>
                    </div>
                </div>
                <button onclick="closeNasConfigModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="space-y-3 text-xs">
                <div class="p-3 bg-amber-50/70 border border-amber-200/80 rounded-2xl text-[11px] text-amber-900 leading-relaxed">
                    <i class="fa-solid fa-circle-info text-amber-600 mr-1"></i>
                    Video packing diambil langsung dari Synology NAS di <b>https://192.168.30.5:5001/</b> pada folder <b>/PACKER</b>.
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Alamat IP / Port NAS</label>
                    <input type="text" id="nasConfigHost" value="https://192.168.30.5:5001" disabled class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2 text-slate-500 font-mono text-xs cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Folder Video Packing</label>
                    <input type="text" id="nasConfigFolder" value="/PACKER" placeholder="/PACKER" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-slate-700 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Username DSM</label>
                        <input type="text" id="nasConfigUser" placeholder="admin" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-slate-700 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Password DSM</label>
                        <input type="password" id="nasConfigPass" placeholder="••••••••" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-slate-700 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div id="nasTestResultBox" class="hidden p-2.5 rounded-xl text-[11px]"></div>
            </div>

            <div class="pt-2 flex items-center justify-between gap-2">
                <a href="https://192.168.30.5:5001/#/signin" target="_blank" class="px-3 py-2 text-[11px] text-amber-700 hover:text-amber-800 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka NAS
                </a>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeNasConfigModal()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                        Batal
                    </button>
                    <button type="button" id="btnSaveNasConfig" onclick="saveNasConfig()" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black rounded-xl text-xs shadow-sm transition flex items-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan & Hubungkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL SINKRONISASI ORDERS OCS (LENGKAP RESI, INVOICE, SKU, BIAYA & TOTAL KLAIM) -->
    <div id="modalOcsSync" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-5 animate-in fade-in zoom-in duration-200">
            <!-- Header Modal -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold shadow-sm shadow-indigo-600/10">
                        <i class="fa-solid fa-cloud-arrow-down"></i>
                    </span>
                    <div>
                        <h3 class="font-black text-slate-800 text-base">Sinkronisasi Orders OCS</h3>
                        <p class="text-[11px] text-slate-500">Sync No. Resi, Invoice, SKU & Biaya Klaim dari OCS IEG System</p>
                    </div>
                </div>
                <button onclick="closeOcsSyncModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Form Pilihan Rentang Waktu -->
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Pilih Metode & Periode Sinkronisasi:</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                        <label class="cursor-pointer border-2 border-indigo-600 bg-indigo-50/50 rounded-2xl p-2.5 flex flex-col justify-between transition hover:border-indigo-600" id="labelSyncYesterday">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-black text-indigo-900">Kemarin</span>
                                <input type="radio" name="syncPeriodType" value="yesterday" checked onchange="toggleSyncDateInput()" class="text-indigo-600 focus:ring-indigo-500">
                            </div>
                            <span class="text-[10px] text-indigo-700 font-semibold leading-tight">24 Jam Penuh</span>
                        </label>

                        <label class="cursor-pointer border-2 border-slate-200 bg-white rounded-2xl p-2.5 flex flex-col justify-between transition hover:border-slate-300" id="labelSyncToday">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-800">Hari Ini</span>
                                <input type="radio" name="syncPeriodType" value="today" onchange="toggleSyncDateInput()" class="text-indigo-600 focus:ring-indigo-500">
                            </div>
                            <span class="text-[10px] text-slate-500 leading-tight">Hingga Sekarang</span>
                        </label>

                        <label class="cursor-pointer border-2 border-slate-200 bg-white rounded-2xl p-2.5 flex flex-col justify-between transition hover:border-slate-300" id="labelSyncMonth">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-800 flex items-center gap-1">
                                    <i class="fa-solid fa-calendar-days text-indigo-600"></i> 1 Bulan
                                </span>
                                <input type="radio" name="syncPeriodType" value="month" onchange="toggleSyncDateInput()" class="text-indigo-600 focus:ring-indigo-500">
                            </div>
                            <span class="text-[10px] text-slate-500 leading-tight">30 Hari Terakhir</span>
                        </label>

                        <label class="cursor-pointer border-2 border-slate-200 bg-white rounded-2xl p-2.5 flex flex-col justify-between transition hover:border-slate-300" id="labelSyncCustom">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-800">Rentang</span>
                                <input type="radio" name="syncPeriodType" value="custom" onchange="toggleSyncDateInput()" class="text-indigo-600 focus:ring-indigo-500">
                            </div>
                            <span class="text-[10px] text-slate-500 leading-tight">Pilih Tanggal</span>
                        </label>

                        <label class="cursor-pointer border-2 border-slate-200 bg-white rounded-2xl p-2.5 flex flex-col justify-between transition hover:border-slate-300" id="labelSyncPicklist">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-800 flex items-center gap-1">
                                    <i class="fa-solid fa-barcode text-blue-500"></i> Resi / ID
                                </span>
                                <input type="radio" name="syncPeriodType" value="picklist" onchange="toggleSyncDateInput()" class="text-indigo-600 focus:ring-indigo-500">
                            </div>
                            <span class="text-[10px] text-slate-500 leading-tight">FindOrder OCS</span>
                        </label>

                        <label class="cursor-pointer border-2 border-slate-200 bg-white rounded-2xl p-2.5 flex flex-col justify-between transition hover:border-slate-300" id="labelSyncResyncTotals">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-800 flex items-center gap-1">
                                    <i class="fa-solid fa-arrows-rotate text-emerald-600"></i> Sync Total
                                </span>
                                <input type="radio" name="syncPeriodType" value="resync_totals" onchange="toggleSyncDateInput()" class="text-indigo-600 focus:ring-indigo-500">
                            </div>
                            <span class="text-[10px] text-emerald-600 font-bold leading-tight">Baris Total OCS</span>
                        </label>
                    </div>
                </div>

                <!-- Input Rentang Tanggal Tertentu (Hidden by default) -->
                <div id="syncCustomDateContainer" class="hidden space-y-2 bg-slate-50 border border-slate-200 rounded-2xl p-3.5">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-700">Tentukan Rentang Tanggal (WIB):</label>
                        <span class="text-[10px] text-indigo-600 font-semibold bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-200">Rentang Bebas (Maks. 31 Hari)</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tanggal Mulai:</label>
                            <input type="date" id="syncStartDateInput" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tanggal Selesai:</label>
                            <input type="date" id="syncEndDateInput" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- Input Resi / Order ID Khusus Picklist OCS (Hidden by default) -->
                <div id="syncPicklistContainer" class="hidden space-y-1.5 bg-blue-50/60 border border-blue-200/80 rounded-2xl p-3.5">
                    <label class="block text-xs font-bold text-blue-900 flex items-center gap-1.5">
                        <i class="fa-solid fa-barcode text-blue-600"></i> Masukkan No. Resi atau Order ID:
                    </label>
                    <div class="relative">
                        <input type="text" id="syncPicklistKeywordInput" placeholder="Contoh: JY1555283461 atau 585719466774005654" 
                            class="w-full px-3.5 py-2.5 bg-white border border-blue-300 rounded-xl text-xs font-mono font-bold text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-2xs">
                    </div>
                    <p class="text-[10px] text-blue-700 leading-tight">
                        Mengambil data langsung dari endpoint resmi <a href="https://ocs.iegsystem.id/picklist" target="_blank" class="underline font-bold">ocs.iegsystem.id/picklist</a> (Fitur Find Order). Mendukung pencarian instan: No. Resi, Order ID, atau Package ID.
                    </p>
                </div>

                <!-- Info Sync Ulang Biaya Paket (Hidden by default) -->
                <div id="syncResyncTotalsContainer" class="hidden space-y-2 bg-emerald-50/70 border border-emerald-200/80 rounded-2xl p-3.5">
                    <div class="flex items-center gap-2 text-emerald-900 font-bold text-xs">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        <span>Sync Ulang Biaya Paket & Nilai Klaim (Baris Total OCS)</span>
                    </div>
                    <p class="text-[11px] text-emerald-800 leading-relaxed">
                        Sistem akan memindai seluruh data order di database lokal dan mengambil <strong>persis nilai dari baris &quot;Total&quot; pada Tab Pembayaran OCS</strong> (BUKAN Total Harga Produk). Ini memastikan seluruh nilai klaim dan biaya paket 100% akurat.
                    </p>
                </div>

                <!-- Info Box Kebutuhan Klaim -->
                <div class="bg-amber-50/80 border border-amber-200/80 rounded-2xl p-3 text-[11px] text-amber-800 flex items-start gap-2.5">
                    <i class="fa-solid fa-circle-info text-amber-600 text-sm mt-0.5 shrink-0"></i>
                    <div>
                        <span class="font-bold">Informasi Klaim yang Disinkron:</span>
                        <ul class="list-disc list-inside mt-0.5 text-[10px] text-amber-700 space-y-0.5">
                            <li>Nomor Resi & Nomor Invoice lengkap dengan platform (Shopee, TikTok, Lazada, dll)</li>
                            <li>Rincian SKU item, kuantiti, harga satuan, dan diskon per produk</li>
                            <li>Biaya ongkir, biaya layanan (service fee), dan total tuntutan klaim</li>
                            <li>Proses otomatis dan cepat: Header di-stream dalam detik, rincian biaya langsung tersinkron</li>
                        </ul>
                    </div>
                </div>

                <!-- Status & Hasil Sinkronisasi -->
                <div id="syncProgressContainer" class="hidden bg-slate-900 text-white rounded-2xl p-4 space-y-3">
                    <!-- Traffic Loader Spinner Warna (Merah, Kuning, Hijau Berputar) -->
                    <div class="traffic-loader py-2 flex items-center justify-center gap-3" id="syncTrafficLoader">
                        <div class="traffic-ball traffic-ball-red"></div>
                        <div class="traffic-ball traffic-ball-yellow"></div>
                        <div class="traffic-ball traffic-ball-green"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span id="syncProgressTitle" class="text-xs font-bold text-indigo-300 flex items-center gap-2">
                            Sedang Menghubungkan ke OCS IEG...
                        </span>
                        <span id="syncProgressBadge" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/30 text-indigo-300 font-mono">PROSES</span>
                    </div>
                    <div id="syncProgressDetail" class="text-[11px] text-slate-300 font-mono">
                        Menyiapkan kueri data order...
                    </div>
                    <!-- Statistik ringkas -->
                    <div id="syncStatsBox" class="hidden grid grid-cols-3 gap-2 pt-2 border-t border-white/10 text-center">
                        <div class="bg-white/5 rounded-xl p-2">
                            <span class="block text-[10px] text-slate-400">Total Orders</span>
                            <span id="statTotalOrders" class="text-sm font-black text-white">0</span>
                        </div>
                        <div class="bg-white/5 rounded-xl p-2">
                            <span class="block text-[10px] text-slate-400">Dengan Resi</span>
                            <span id="statWithResi" class="text-sm font-black text-emerald-400">0</span>
                        </div>
                        <div class="bg-white/5 rounded-xl p-2">
                            <span class="block text-[10px] text-slate-400">Total Klaim</span>
                            <span id="statTotalClaim" class="text-xs font-black text-amber-400 truncate">Rp 0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeOcsSyncModal()" id="btnCancelOcsSync" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-bold text-xs transition">
                    Tutup
                </button>
                <button type="button" onclick="executeOcsOrderSync()" id="btnStartOcsSync" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs flex items-center gap-2 transition shadow-sm shadow-indigo-600/20">
                    <i class="fa-solid fa-play"></i>
                    <span>Mulai Sinkronisasi Sekarang</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL ITEM ORDER OCS & BREAKDOWN BIAYA KLAIM -->
    <div id="modalOrderDetail" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-200">
            <!-- Header Modal -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-bold">
                        <i class="fa-solid fa-receipt"></i>
                    </span>
                    <div>
                        <h3 class="font-black text-slate-800 text-base" id="detailOrderModalTitle">Detail Pesanan OCS</h3>
                        <p class="text-[11px] text-slate-500" id="detailOrderModalSubtitle">-</p>
                    </div>
                </div>
                <button onclick="closeOrderDetailModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto space-y-4 pr-1 text-xs">
                <!-- Info Ringkas Order -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 bg-slate-50 p-3 rounded-2xl border border-slate-200">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase block">Platform</span>
                        <span id="dtlPlatform" class="font-bold text-slate-800">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase block">Toko / Shop</span>
                        <span id="dtlShop" class="font-bold text-slate-800">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase block">Ekspedisi</span>
                        <span id="dtlShipping" class="font-bold text-slate-800">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase block">Customer</span>
                        <span id="dtlCustomer" class="font-bold text-slate-800">-</span>
                    </div>
                </div>

                <!-- Rincian Item SKU Table -->
                <div>
                    <h4 class="font-bold text-slate-800 mb-2 uppercase text-[11px] flex items-center gap-1.5">
                        <i class="fa-solid fa-list-check text-blue-600"></i>
                        <span>Daftar Produk & Varian SKU</span>
                    </h4>
                    <div class="overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-slate-600 font-semibold text-[10px] uppercase">
                                <tr>
                                    <th class="p-2.5">Produk / SKU</th>
                                    <th class="p-2.5 text-center">Qty</th>
                                    <th class="p-2.5 text-right">Harga Asli</th>
                                    <th class="p-2.5 text-right">Diskon</th>
                                    <th class="p-2.5 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="dtlSkuTableBody" class="divide-y divide-slate-100">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Breakdown Finansial & Klaim -->
                <div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white p-4 rounded-2xl space-y-2">
                    <span class="text-[10px] text-amber-300 font-bold uppercase tracking-wider block">Ringkasan Biaya & Nilai Klaim</span>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs pt-1 border-t border-white/10">
                        <div>
                            <span class="text-[10px] text-slate-400 block">Total Harga Produk:</span>
                            <span id="dtlOrigPrice" class="font-bold text-white">Rp 0</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block">Ongkir (Shipping Fee):</span>
                            <span id="dtlShipFee" class="font-bold text-white">Rp 0</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block">Diskon Penjual & Platform:</span>
                            <span id="dtlDiscount" class="font-bold text-rose-300">- Rp 0</span>
                        </div>
                    </div>
                    <div class="pt-2 border-t border-white/10 flex items-center justify-between">
                        <span class="font-bold text-xs text-amber-300">TOTAL TUNTUTAN KLAIM RESMI:</span>
                        <span id="dtlTotalClaim" class="font-black text-lg text-emerald-400 font-mono">Rp 0</span>
                    </div>
                </div>
            </div>

            <!-- Footer Action -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between shrink-0">
                <button type="button" id="btnDtlCheckClaimDossier" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition shadow-sm">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Cek Bukti di Pusat Klaim</span>
                </button>
                <button onclick="closeOrderDetailModal()" type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition">
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

    <!-- MODAL APPROVAL / DIGITAL E-SIGN KLAIM J&T -->
    <div id="modalApprovalJnt" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 animate-scaleIn">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center space-x-2.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-stamp"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base" id="modalJntApprovalTitle">Persetujuan Klaim (e-Sign)</h3>
                        <p class="text-xs text-slate-400">Verifikasi Resmi Divisi Accounting</p>
                    </div>
                </div>
                <button onclick="closeJntApprovalModal()" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Info Summary -->
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1">
                    <div class="flex justify-between text-slate-600">
                        <span>Jumlah Paket yang Dipilih:</span>
                        <b class="text-slate-900 font-mono" id="modalJntClaimCount">0 Paket</b>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Total Nilai Tagihan:</span>
                        <b class="text-emerald-700 font-bold" id="modalJntClaimAmount">Rp 0</b>
                    </div>
                </div>

                <!-- Input Nama Petugas Approver -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Petugas Accounting (Approver) <span class="text-rose-500">*</span></label>
                    <input type="text" id="inputJntApproverName" value="<?= htmlspecialchars($user['name'] ?: $user['username']) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500">
                </div>

                <!-- Digital e-Signature Stamp Badge Preview -->
                <div class="border border-dashed border-emerald-300 bg-emerald-50/40 p-3.5 rounded-2xl text-center space-y-1.5">
                    <div class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full bg-emerald-600 text-white font-black text-[10px] tracking-wider uppercase">
                        <i class="fa-solid fa-certificate"></i>
                        <span>Digital Verified e-Sign</span>
                    </div>
                    <div class="text-[11px] font-bold text-slate-800">
                        Dokumen Tagihan Invoice akan otomatis dibubuhi Cap &amp; Tanda Tangan Digital Resmi
                    </div>
                    <div class="text-[10px] text-slate-500 font-mono" id="previewJntEsignCode">
                        Token: ESIGN-JNT-<?= date('Ymd') ?>-AUTO
                    </div>
                </div>

                <!-- Catatan Verifikasi -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Catatan Verifikasi Accounting (Opsional)</label>
                    <textarea id="inputJntApprovalNotes" rows="2" placeholder="Contoh: Telah diverifikasi fisik barang rusak dan sesuai dengan data OCS..." class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" onclick="closeJntApprovalModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                    Batal
                </button>
                <button type="button" onclick="submitJntApproval('APPROVE')" id="btnSubmitJntApproval" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/30 transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-check-double"></i>
                    <span>Setujui &amp; Terbitkan e-Sign</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT EKSPEDISI PENERIMAAN INBOUND -->
    <div id="modalEditReceivingExpedition" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 animate-scaleIn">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center space-x-2.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Edit Ekspedisi Penerimaan</h3>
                        <p class="text-xs text-slate-400">Perbarui data ekspedisi &amp; informasi serah terima</p>
                    </div>
                </div>
                <button type="button" onclick="closeEditReceivingModal()" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="formEditReceivingExpedition" onsubmit="submitEditReceivingExpedition(event)" class="space-y-4 text-xs">
                <input type="hidden" id="editReceivingId" value="">

                <!-- Summary Info No Terima -->
                <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">No. Tanda Terima</span>
                        <b class="text-sm font-mono text-emerald-700" id="editReceivingReceiptNo">-</b>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Total Paket</span>
                        <b class="text-xs font-mono text-indigo-700" id="editReceivingTotalPkgs">-</b>
                    </div>
                </div>

                <!-- Pilihan Ekspedisi -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1">
                        Jasa Ekspedisi <span class="text-rose-500">*</span>
                    </label>
                    <select id="editReceivingExpeditionSelect" onchange="toggleCustomExpeditionInput()" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-800 text-xs focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500">
                        <!-- Populated dynamically -->
                    </select>
                </div>

                <!-- Input Ekspedisi Kustom jika dipilih lainnya -->
                <div id="customExpeditionContainer" class="hidden">
                    <label class="block font-bold text-slate-700 mb-1">Ketik Nama Ekspedisi Kustom</label>
                    <input type="text" id="editReceivingExpeditionCustom" placeholder="Contoh: J&amp;T Cargo, Paxel, dll." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl font-medium text-xs focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Driver / Nama Kurir</label>
                        <input type="text" id="editReceivingCourierName" placeholder="Nama Kurir" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nomor Karung / Sack</label>
                        <input type="text" id="editReceivingSackNumber" placeholder="Contoh: KR-01" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">No. Polisi Kendaraan</label>
                        <input type="text" id="editReceivingVehicleNo" placeholder="Contoh: B 1234 XYZ" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Catatan Tambahan</label>
                        <input type="text" id="editReceivingNotes" placeholder="Catatan jika ada" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeEditReceivingModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" id="btnSaveEditReceiving" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-600/30 transition flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT DATA & STATUS KLAIM ACCOUNTING -->
    <div id="modalAccountingEdit" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold shadow-2xs">
                        <i class="fa-solid fa-file-signature"></i>
                    </span>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Proses &amp; Edit Data Klaim</h3>
                        <p class="text-[10px] text-slate-400">Verifikasi status &amp; catatan divisi Accounting</p>
                    </div>
                </div>
                <button type="button" onclick="closeAccountingEditModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
            <form id="formAccountingEdit" onsubmit="submitAccountingEdit(event)" class="p-5 space-y-3.5 text-xs">
                <input type="hidden" id="accEditInvoice">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">No. Resi / Invoice</label>
                    <input type="text" id="accEditInvoiceDisplay" readonly class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl font-mono font-bold text-slate-700 text-xs">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Ekspedisi</label>
                        <input type="text" id="accEditExpedition" readonly class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl font-bold text-slate-700 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nilai Tagihan</label>
                        <input type="text" id="accEditPrice" readonly class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl font-mono font-bold text-emerald-700 text-xs">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Status Accounting <span class="text-rose-500">*</span></label>
                    <select id="accEditStatus" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-bold text-slate-800 text-xs focus:ring-2 focus:ring-indigo-500">
                        <option value="RECEIVED">RECEIVED (Paket Diterima Accounting)</option>
                        <option value="DONE">DONE (Done Klaim / Selesai Klaim)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Catatan / No. Bukti Pengajuan Ekspedisi</label>
                    <textarea id="accEditNotes" rows="3" placeholder="Masukkan nomor referensi klaim, tiket ekspedisi, atau catatan approval..." class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-slate-800 text-xs focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" onclick="closeAccountingEditModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold transition">
                        Batal
                    </button>
                    <button type="submit" id="btnSubmitAccEdit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-md shadow-indigo-600/30 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Simpan Status</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DETAIL KHUSUS AGING RETURN (BISA LIHAT DETAIL SAAT KLIK RESI / INVOICE) -->
    <div id="modalAgingDetail" class="hidden fixed inset-0 z-50 bg-slate-900/75 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
        <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200">
            <!-- Header Modal -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-teal-500/20 text-teal-400 flex items-center justify-center font-bold text-base border border-teal-500/30">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-black text-sm sm:text-base leading-tight" id="mAgingBarcodeTitle">Detail Paket Inbound</h3>
                            <span id="mAgingStatusBadge" class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-amber-500 text-slate-950">BELUM DI UNBOXING</span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-mono" id="mAgingSubtitle">Pelacakan riwayat serah terima kurir dan status unboxing</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeAgingDetailModal()" class="text-slate-400 hover:text-white text-base p-1.5 rounded-lg hover:bg-slate-800 transition cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Scrollable Modal Content Body -->
            <div class="p-4 sm:p-6 overflow-y-auto space-y-4 flex-1 text-xs" id="mAgingModalBody">
                <!-- Highlight Lead Time / Aging Journey Banner -->
                <div id="mAgingJourneyBanner" class="p-4 rounded-2xl border transition">
                    <!-- Dynamic Journey content injected via JS -->
                </div>

                <!-- 2 Kolom Komparasi: Receiving Gudang vs Unboxing Stasiun -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Kolom 1: Receiving Inbound (Penerimaan Fisik) -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <span class="font-bold text-slate-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-truck-ramp-box text-emerald-600"></i> Serah Terima Receiving
                            </span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Fisik Diterima
                            </span>
                        </div>

                        <div class="space-y-2">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">No. Resi / Paket</span>
                                <div class="font-mono font-bold text-slate-800 text-sm flex items-center gap-2">
                                    <span id="mAgingRecBarcode">-</span>
                                    <button type="button" onclick="copyAgingBarcode()" class="text-slate-400 hover:text-slate-600 transition" title="Copy Resi">
                                        <i class="fa-regular fa-copy text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Ekspedisi</span>
                                    <div class="font-semibold text-slate-800" id="mAgingRecExpedition">-</div>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Nama Kurir</span>
                                    <div class="font-semibold text-slate-800" id="mAgingRecCourier">-</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">No Surat Jalan / Karung</span>
                                    <div class="font-mono text-slate-700" id="mAgingRecReceipt">-</div>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Petugas Receiving</span>
                                    <div class="font-semibold text-slate-800" id="mAgingRecOperator">-</div>
                                </div>
                            </div>

                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Waktu Scan Masuk Gudang</span>
                                <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-regular fa-calendar-check text-emerald-600"></i>
                                    <span id="mAgingRecTime">-</span>
                                </div>
                            </div>

                            <!-- Foto Serah Terima Receiving -->
                            <div id="mAgingRecPhotoContainer" class="pt-2 border-t border-slate-200">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Foto Bukti Receiving</span>
                                <div id="mAgingRecPhotoView" class="rounded-xl overflow-hidden border border-slate-200 min-h-[90px] max-h-48 bg-slate-900 flex items-center justify-center">
                                    <span class="text-slate-400 text-xs">Tidak ada foto receiving</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom 2: Status & Hasil Unboxing -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <span class="font-bold text-slate-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-box-open text-indigo-600"></i> Stasiun Unboxing
                            </span>
                            <span id="mAgingUnboxStatusBadge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">
                                Status
                            </span>
                        </div>

                        <!-- Konten Dinamis: Jika Belum vs Jika Sudah Unboxing -->
                        <div id="mAgingUnboxContent" class="space-y-3">
                            <!-- Diisi via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Section Tambahan: Data Order OCS & Finansial (Jika ada) -->
                <div id="mAgingOcsSection" class="p-4 bg-slate-50/60 rounded-2xl border border-slate-200 hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2 mb-3">
                        <span class="font-bold text-slate-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-cart-flatbed text-blue-600"></i> Data Order Marketplace (OCS System)
                        </span>
                        <span class="text-[10px] text-slate-500 font-mono" id="mAgingOcsOrderId">-</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Toko</span>
                            <div class="font-semibold text-slate-800" id="mAgingOcsShop">-</div>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Platform</span>
                            <div class="font-semibold text-slate-800" id="mAgingOcsPlatform">-</div>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Pembayaran</span>
                            <div class="font-bold text-emerald-600 font-mono text-sm" id="mAgingOcsPrice">Rp -</div>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Pelanggan</span>
                            <div class="font-semibold text-slate-800 truncate" id="mAgingOcsCustomer">-</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Modal -->
            <div class="p-3.5 sm:p-4 bg-slate-100 border-t border-slate-200 flex justify-between items-center shrink-0">
                <div class="text-[11px] text-slate-500">
                    Sistem Inbound Return IEG &bull; Terhubung Otomatis dengan Receiving & Unboxing
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="btnAgingOpenClaimDossier" onclick="openClaimDossierFromAging()" class="hidden px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold text-xs shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Buka Berkas Klaim</span>
                    </button>
                    <button type="button" onclick="closeAgingDetailModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-bold text-xs transition cursor-pointer">
                        Tutup
                    </button>
                </div>
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
    <script>
        window.SERVER_SYNC_ENABLED = <?= (defined('SYNC_ENABLED') && SYNC_ENABLED) ? 'true' : 'false' ?>;
    </script>
    <script src="assets/js/admin.js?v=<?= file_exists(__DIR__ . '/assets/js/admin.js') ? filemtime(__DIR__ . '/assets/js/admin.js') : time() ?>"></script>
</body>
</html>
