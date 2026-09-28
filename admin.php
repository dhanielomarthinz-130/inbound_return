<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Inbound Return (Laragon)</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 flex overflow-x-hidden">

    <!-- Mobile Backdrop -->
    <div id="sidebarBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-40 lg:hidden hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-slate-900 text-slate-200 flex flex-col shrink-0 fixed inset-y-0 left-0 z-50 lg:static transition-transform duration-300 transform -translate-x-full lg:translate-x-0 shadow-2xl lg:shadow-none border-r border-slate-800">
        
        <!-- Sidebar Brand -->
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white shadow-md shadow-indigo-500/30">
                    <i class="fa-solid fa-boxes-packing text-lg"></i>
                </div>
                <div>
                    <h1 class="font-bold text-white text-base leading-tight tracking-tight">Return Inbound</h1>
                    <p class="text-[11px] text-indigo-400 font-medium">Warehouse Admin Hub</p>
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
                <i class="fa-solid fa-receipt w-5 text-center"></i>
                <span>Riwayat Inbound</span>
            </button>

            <button onclick="switchTab('products')" id="nav-products" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-tags w-5 text-center"></i>
                <span>Master Produk</span>
            </button>

            <button onclick="switchTab('expeditions')" id="nav-expeditions" class="nav-item w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i class="fa-solid fa-truck-fast w-5 text-center"></i>
                <span>Master Ekspedisi</span>
            </button>

            <div class="pt-4 px-3 pb-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Akses Langsung</div>

            <a href="index.php" class="w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-emerald-400 hover:text-emerald-300 hover:bg-emerald-950/40 border border-emerald-500/20 transition">
                <i class="fa-solid fa-barcode w-5 text-center"></i>
                <span>Buka Scanner Operator</span>
            </a>
        </div>

        <!-- Sidebar Footer: Laragon MySQL Status Info -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/50">
            <div class="flex items-center justify-between text-xs mb-1.5">
                <span class="text-slate-400 font-medium">Status Database</span>
                <span class="inline-flex items-center gap-1.5 text-emerald-400 font-semibold text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> MySQL Aktif
                </span>
            </div>
            <div class="text-[11px] text-slate-500 font-mono flex items-center gap-1">
                <i class="fa-solid fa-database text-[10px] text-indigo-400"></i>
                <span>DB: <b>inbound_return</b></span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1">Laragon Port 3306 &bull; Apache 80</div>
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
                    <p class="text-xs text-slate-400 hidden sm:block">Pemantauan data penerimaan barang retur secara realtime</p>
                </div>
            </div>

            <div class="flex items-center space-x-2.5">
                <!-- Tombol Tambah Produk Cepat -->
                <button onclick="openAddProductModal()" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs px-3 py-2 rounded-xl font-bold transition flex items-center gap-1.5 border border-indigo-200">
                    <i class="fa-solid fa-plus-circle"></i> <span class="hidden sm:inline">Tambah Produk</span>
                </button>

                <!-- Tombol Refresh Data -->
                <button onclick="refreshAllData()" title="Segarkan Data" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs px-3 py-2 rounded-xl font-semibold transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrows-rotate" id="refreshIcon"></i> <span class="hidden sm:inline">Refresh</span>
                </button>

                <!-- Link Cepat ke Scanner -->
                <a href="index.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-camera"></i> <span class="hidden md:inline">Scanner</span>
                </a>
            </div>
        </header>

        <!-- Body Container -->
        <main class="p-4 md:p-8 space-y-6 flex-1">

            <!-- TAB 1: DASHBOARD OVERVIEW -->
            <div id="tab-dashboard" class="tab-content space-y-6">
                <!-- KPI Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200">
                        <div class="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                            <span>Total Invoice Hari Ini</span>
                            <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <i class="fa-solid fa-file-invoice text-sm"></i>
                            </span>
                        </div>
                        <div class="text-3xl font-black text-slate-800 mt-2" id="kpiTotalInvoice">0</div>
                        <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
                            <span class="text-indigo-600 font-bold">&bull;</span> Sesi transaksi retur
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200">
                        <div class="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                            <span>Total Unit Fisik</span>
                            <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i class="fa-solid fa-box-open text-sm"></i>
                            </span>
                        </div>
                        <div class="text-3xl font-black text-slate-800 mt-2" id="kpiTotalItems">0</div>
                        <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
                            <span class="text-blue-600 font-bold">&bull;</span> Total seluruh pcs masuk
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200">
                        <div class="flex items-center justify-between text-emerald-600 text-xs font-semibold uppercase">
                            <span>Kondisi Good</span>
                            <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i class="fa-solid fa-circle-check text-sm"></i>
                            </span>
                        </div>
                        <div class="text-3xl font-black text-emerald-600 mt-2" id="kpiTotalGood">0</div>
                        <div class="text-[11px] text-emerald-500 mt-1 flex items-center gap-1">
                            <span class="text-emerald-600 font-bold">&bull;</span> Layak restock / jual
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200">
                        <div class="flex items-center justify-between text-rose-600 text-xs font-semibold uppercase">
                            <span>Kondisi Rusak</span>
                            <span class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                                <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                            </span>
                        </div>
                        <div class="text-3xl font-black text-rose-600 mt-2" id="kpiTotalDamaged">0</div>
                        <div class="text-[11px] text-rose-500 mt-1 flex items-center gap-1">
                            <span class="text-rose-600 font-bold">&bull;</span> Cacat / retur vendor
                        </div>
                    </div>
                </div>

                <!-- Middle Section: Chart Rasio & Master Produk Preview -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Chart Rasio Good vs Rusak -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 lg:col-span-1 flex flex-col items-center">
                        <h3 class="font-bold text-sm text-slate-700 w-full mb-3 flex items-center justify-between">
                            <span>Rasio Kualitas Hari Ini</span>
                            <i class="fa-solid fa-chart-pie text-indigo-500"></i>
                        </h3>
                        <div class="relative w-full h-56 flex justify-center items-center">
                            <canvas id="ratioChart"></canvas>
                        </div>
                    </div>

                    <!-- Ringkasan Master Produk Cepat -->
                    <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200 lg:col-span-2 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h3 class="font-bold text-sm text-slate-800">Master Data Produk Terdaftar</h3>
                                    <p class="text-xs text-slate-400">Barcode yang siap dipindai oleh stasiun operator</p>
                                </div>
                                <button onclick="switchTab('products')" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold">
                                    Lihat Semua &rarr;
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-slate-50 text-slate-600 uppercase font-semibold">
                                        <tr>
                                            <th class="p-2.5">Barcode</th>
                                            <th class="p-2.5">SKU</th>
                                            <th class="p-2.5">Nama Produk</th>
                                            <th class="p-2.5">Kategori</th>
                                        </tr>
                                    </thead>
                                    <tbody id="quickProductsTableBody" class="divide-y divide-slate-100">
                                        <tr><td colspan="4" class="p-4 text-center text-slate-400">Memuat data produk...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5 Transaksi Terakhir Preview -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-sm text-slate-800">5 Transaksi Inbound Terbaru</h3>
                            <p class="text-xs text-slate-400">Ringkasan aktivitas retur invoice terkini</p>
                        </div>
                        <button onclick="switchTab('transactions')" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold">
                            Buka Semua Riwayat &rarr;
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">Waktu</th>
                                    <th class="p-3">Invoice</th>
                                    <th class="p-3">Ekspedisi</th>
                                    <th class="p-3">Operator</th>
                                    <th class="p-3 text-center">Total Unit</th>
                                    <th class="p-3 text-center">Good</th>
                                    <th class="p-3 text-center">Rusak</th>
                                    <th class="p-3">Detail Item</th>
                                    <th class="p-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="previewTransactionsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="9" class="text-center py-6 text-slate-400">Memuat data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RIWAYAT TRANSAKSI LENGKAP -->
            <div id="tab-transactions" class="tab-content hidden space-y-4">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-center gap-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Seluruh Riwayat Inbound Return</h3>
                            <p class="text-xs text-slate-400">Audit trail dan detail seluruh sesi transaksi retur</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                            <input type="date" id="filterDate" class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <input type="text" id="filterSearch" placeholder="Cari invoice/operator..." class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <button id="btnApplyFilter" class="bg-slate-800 hover:bg-slate-700 text-white text-xs px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-1">
                                <i class="fa-solid fa-filter"></i> Filter
                            </button>
                            <button id="btnExportCsv" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-1 shadow-xs">
                                <i class="fa-solid fa-file-excel"></i> Export CSV
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">Waktu</th>
                                    <th class="p-3">Invoice</th>
                                    <th class="p-3">Ekspedisi</th>
                                    <th class="p-3">Operator</th>
                                    <th class="p-3 text-center">Total Unit</th>
                                    <th class="p-3 text-center">Good</th>
                                    <th class="p-3 text-center">Rusak</th>
                                    <th class="p-3">Ringkasan Produk</th>
                                    <th class="p-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="9" class="text-center py-8 text-slate-400">Memuat data transaksi...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: MASTER PRODUK LENGKAP -->
            <div id="tab-products" class="tab-content hidden space-y-4">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Master Data Produk & Barcode</h3>
                            <p class="text-xs text-slate-400">Daftar produk yang tersimpan di database MySQL Laragon</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" id="filterProductSearch" onkeyup="filterProductTable()" placeholder="Cari nama/barcode/sku..." class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <button onclick="openAddProductModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-plus-circle"></i> Tambah Produk Baru
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">ID</th>
                                    <th class="p-3">Barcode</th>
                                    <th class="p-3">SKU</th>
                                    <th class="p-3">Nama Produk</th>
                                    <th class="p-3">Kategori</th>
                                    <th class="p-3 text-center">Satuan</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="fullProductsTableBody" class="divide-y divide-slate-100">
                                <tr><td colspan="7" class="text-center py-8 text-slate-400">Memuat daftar master produk...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: MASTER EKSPEDISI / KURIR -->
            <div id="tab-expeditions" class="tab-content hidden space-y-4">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Master Data Ekspedisi & Kurir</h3>
                            <p class="text-xs text-slate-400">Kelola daftar armada ekspedisi penerima barang retur (Tambah, Edit, Hapus)</p>
                        </div>
                        <div class="flex items-center space-x-2 w-full sm:w-auto">
                            <input type="text" id="filterExpeditionSearch" oninput="filterExpeditionTable()" placeholder="Cari nama / kode ekspedisi..."
                                class="border border-slate-300 rounded-xl px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full sm:w-64">
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

        </main>
    </div>

    <!-- MODAL DETAIL ITEM PER SESI -->
    <div id="detailModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-800" id="modalInvoiceTitle">Detail Invoice</h3>
                    <p class="text-xs text-slate-400" id="modalInvoiceSubtitle">Rincian item barang yang diretur</p>
                </div>
                <button id="btnCloseModal" class="text-slate-400 hover:text-slate-600 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="overflow-x-auto max-h-[350px]">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="bg-slate-50 text-slate-600 uppercase font-semibold sticky top-0">
                        <tr>
                            <th class="p-2.5">Barcode</th>
                            <th class="p-2.5">Nama Produk</th>
                            <th class="p-2.5">Batch</th>
                            <th class="p-2.5">Exp Date</th>
                            <th class="p-2.5 text-center">Qty</th>
                            <th class="p-2.5 text-center">Type</th>
                        </tr>
                    </thead>
                    <tbody id="modalItemsBody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
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
                        <p class="text-xs text-slate-400">Daftarkan barcode ke database MySQL</p>
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
                        <p class="text-xs text-slate-400" id="expeditionModalSubtitle">Simpan data armada/kurir ke database MySQL</p>
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

    <script src="assets/js/admin.js"></script>
</body>
</html>
