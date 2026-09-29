<?php
require_once __DIR__ . '/config.php';
$currentUser = getSessionUser();
checkMaintenanceMode($pdo, $currentUser);
$user = requireLogin(['operator', 'admin', 'superadmin']);

// Ambil daftar master ekspedisi aktif
try {
    $stmtExp = $pdo->query("SELECT * FROM master_expeditions WHERE status = 'ACTIVE' ORDER BY name ASC");
    $expeditions = $stmtExp->fetchAll();
} catch (Exception $e) {
    $expeditions = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Penerimaan Returan Ekspedisi - Inbound Station</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- HTML5-QRCode Scanner Library untuk Kamera HP -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= time() ?>">
    <style>
        /* Optimasi Tampilan Mobile */
        html, body {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        #reader video {
            object-fit: cover !important;
            border-radius: 1rem;
        }
        @media print {
            body * { visibility: hidden; }
            #printReceiptArea, #printReceiptArea * { visibility: visible; }
            #printReceiptArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white !important;
                color: black !important;
                padding: 16px;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 antialiased flex flex-col justify-between selection:bg-emerald-500 selection:text-white pb-24 md:pb-6">

    <!-- TOP NAVBAR (RESPONSIVE & MOBILE FRIENDLY) -->
    <header class="bg-slate-900 border-b border-slate-800 text-white shadow-lg sticky top-0 z-30 w-full no-print">
        <div class="w-full px-3 md:px-6 py-2.5 flex justify-between items-center gap-2">
            
            <!-- Brand / Logo & Title -->
            <div class="flex items-center space-x-2.5 shrink-0">
                <div class="w-9 h-9 rounded-2xl bg-white p-1 flex items-center justify-center shadow-md shrink-0">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h1 class="font-black text-white text-sm md:text-base leading-tight">Penerimaan Ekspedisi</h1>
                        <span class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-[9px] font-bold px-1.5 py-0.5 rounded-md">Mobile</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium hidden sm:block">Serah Terima & Scan Barcode Paket Inbound</p>
                </div>
            </div>
            
            <!-- Navigation Switcher & User Profile -->
            <div class="flex items-center space-x-1.5 md:space-x-3">
                <!-- Nav Switcher ke Unboxing Station -->
                <a href="scanner" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 px-2.5 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-box-open text-indigo-400"></i>
                    <span class="hidden sm:inline">Unboxing Station</span>
                </a>

                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <a href="admin" class="hidden md:flex bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-2.5 py-1.5 rounded-xl font-semibold transition items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Admin</span>
                </a>
                <?php endif; ?>

                <!-- Operator Badge -->
                <div class="flex items-center gap-1.5 bg-slate-800/90 border border-slate-700/80 px-2.5 py-1.5 rounded-xl text-xs">
                    <div class="w-5 h-5 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">
                        <i class="fa-solid fa-user-check text-[10px]"></i>
                    </div>
                    <span class="font-bold text-slate-100 text-xs truncate max-w-[100px]"><?= htmlspecialchars($user['name']) ?></span>
                </div>

                <!-- Tombol Logout -->
                <a href="logout" onclick="return confirm('Keluar dari sesi ini?')" title="Logout" class="w-8 h-8 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white flex items-center justify-center transition shadow-sm shrink-0">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- WORKSPACE UTAMA -->
    <main class="w-full max-w-5xl mx-auto px-3 md:px-6 py-4 space-y-4 flex-1 no-print">

        <!-- TAB MENU: 1. INPUT SCAN PENERIMAAN | 2. RIWAYAT HARI INI -->
        <div class="flex items-center justify-between bg-white p-1 rounded-2xl border border-slate-200 shadow-sm">
            <button id="tabBtnScan" onclick="switchTab('scan')" class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-emerald-600 text-white shadow-sm">
                <i class="fa-solid fa-barcode"></i>
                <span>Scan Penerimaan</span>
            </button>
            <button id="tabBtnHistory" onclick="switchTab('history')" class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Riwayat Hari Ini (<span id="historyCountBadge">0</span>)</span>
            </button>
        </div>

        <!-- ============================================================== -->
        <!-- VIEW 1: SCAN PENERIMAAN BARU                                   -->
        <!-- ============================================================== -->
        <div id="viewScan" class="space-y-4">

            <!-- CARD 1: INFORMASI EKSPEDISI & TANDA TERIMA -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-truck-ramp-box"></i>
                        </div>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Data Ekspedisi Pengantar</h2>
                    </div>
                    <button onclick="resetReceptionForm()" type="button" class="text-[11px] text-amber-600 hover:text-amber-700 font-semibold flex items-center gap-1 transition">
                        <i class="fa-solid fa-rotate"></i> Reset Form
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <!-- Dropdown Ekspedisi -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                            Pilih Ekspedisi <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-truck-fast"></i>
                            </span>
                            <select id="selectExpedition" onchange="onExpeditionChanged()" class="w-full pl-9 pr-8 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition appearance-none">
                                <option value="">-- Pilih Ekspedisi Pengantar --</option>
                                <?php foreach ($expeditions as $exp): ?>
                                    <option value="<?= htmlspecialchars($exp['name']) ?>" data-prefix="<?= htmlspecialchars($exp['prefix_pattern'] ?? '') ?>">
                                        <?= htmlspecialchars($exp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-chevron-down"></i>
                            </span>
                        </div>
                    </div>

                    <!-- No. Tanda Terima / ID Penerimaan (Kunci & Readonly) -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1 flex justify-between">
                            <span>ID Penerimaan / Tanda Terima</span>
                            <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1"><i class="fa-solid fa-lock text-[9px]"></i> Otomatis</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-receipt"></i>
                            </span>
                            <input type="text" id="inputReceiptNo" readonly placeholder="Membuat ID penerimaan..." class="w-full pl-9 pr-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-mono font-bold text-slate-700 cursor-not-allowed select-none focus:outline-none transition">
                        </div>
                    </div>

                    <!-- Nama Kurir / Driver (Opsional) -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                            Nama Driver / Kurir <span class="text-slate-400 font-normal">(Opsional)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-id-card"></i>
                            </span>
                            <input type="text" id="inputCourierName" placeholder="Contoh: Budi (Kurir SPX)" class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD 2: AREA SCAN BARCODE PAKET (MOBILE KAMERA & HARDWARE GUN) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Scan Barcode / Resi Paket</h2>
                    </div>

                    <!-- Total Paket Counter Badge -->
                    <div class="bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-xl flex items-center gap-2">
                        <span class="text-[11px] font-bold text-emerald-700">Total Paket:</span>
                        <span id="packageCountBadge" class="bg-emerald-600 text-white font-black text-sm px-2 py-0.5 rounded-lg leading-none">0</span>
                    </div>
                </div>

                <!-- Input Scanner Box -->
                <div class="space-y-2">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                <i class="fa-solid fa-barcode"></i>
                            </span>
                            <input type="text" id="inputPackageBarcode" placeholder="Scan barcode resi di sini..." autocomplete="off" class="w-full pl-10 pr-3 py-3 bg-slate-50 border-2 border-slate-300 rounded-2xl text-sm font-mono font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-inner">
                        </div>
                        
                        <!-- Tombol Tambah Manual -->
                        <button onclick="submitPackageBarcode()" type="button" class="bg-slate-800 hover:bg-slate-900 text-white px-4 rounded-2xl font-bold text-xs transition flex items-center justify-center shrink-0 shadow-sm">
                            <i class="fa-solid fa-plus text-sm"></i>
                        </button>

                        <!-- Tombol Buka Kamera Scanner HP -->
                        <button id="btnToggleCamera" onclick="toggleCameraScanner()" type="button" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 rounded-2xl font-bold text-xs transition flex items-center gap-1.5 shrink-0 shadow-sm shadow-emerald-600/30">
                            <i class="fa-solid fa-camera"></i>
                            <span class="hidden sm:inline">Kamera</span>
                        </button>
                    </div>

                    <!-- Viewport Kamera Scanner HP (HTML5-QRCode) -->
                    <div id="cameraScannerContainer" class="hidden bg-slate-900 rounded-2xl p-3 border border-slate-800 relative transition-all">
                        <div class="flex justify-between items-center text-white mb-2 px-1">
                            <div class="flex items-center gap-2 text-xs font-bold">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>Arahkan Kamera ke Barcode Resi</span>
                            </div>
                            <button onclick="toggleCameraScanner()" type="button" class="text-slate-400 hover:text-white text-xs bg-slate-800 px-2 py-1 rounded-lg">
                                <i class="fa-solid fa-xmark"></i> Tutup Kamera
                            </button>
                        </div>
                        <div id="reader" class="w-full overflow-hidden rounded-xl bg-black min-h-[220px]"></div>
                        <p class="text-[10px] text-center text-slate-400 mt-2">
                            Mode continuous scan aktif: Paket langsung otomatis tersimpan ke daftar tanpa menutup kamera.
                        </p>
                    </div>

                    <!-- Alert / Status Notifikasi Suara & Teks -->
                    <div id="scanStatusMsg" class="hidden text-xs font-semibold px-3 py-2 rounded-xl flex items-center justify-between transition">
                        <span id="scanStatusText"></span>
                        <button onclick="dismissStatusMsg()" class="text-slate-400 hover:text-slate-600 text-xs ml-2"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>

                <!-- DAFTAR PAKET YANG SUDAH DI-SCAN -->
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-2 px-1">
                        <span class="text-xs font-bold text-slate-700">Daftar Paket Di-scan (<span id="listCountLabel">0</span>)</span>
                        <button onclick="clearAllPackages()" type="button" class="text-[11px] text-rose-500 hover:text-rose-700 font-semibold transition">
                            Hapus Semua
                        </button>
                    </div>

                    <div id="packageListEmpty" class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center text-slate-400">
                        <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-semibold text-slate-500">Belum ada paket yang di-scan</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Tembakkan barcode gun atau gunakan kamera HP untuk memulai</p>
                    </div>

                    <!-- List Container (Scrollable) -->
                    <div id="packageListContainer" class="hidden max-h-[360px] overflow-y-auto space-y-1.5 pr-1 divide-y divide-slate-100">
                        <!-- Dynamic Item Rows -->
                    </div>
                </div>
            </div>

            <!-- TOMBOL SUBMIT PENERIMAAN (DESKTOP / INLINE) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-4">
                <button id="btnSubmitReception" onclick="submitCompleteReception()" type="button" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white rounded-2xl font-black text-sm transition shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-double text-base"></i>
                    <span>Simpan & Selesaikan Penerimaan (<span id="btnSubmitCount">0</span> Paket)</span>
                </button>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- VIEW 2: RIWAYAT PENERIMAAN HARI INI                            -->
        <!-- ============================================================== -->
        <div id="viewHistory" class="hidden space-y-3">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-4 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Riwayat Penerimaan Ekspedisi</h2>
                        <p class="text-[10px] text-slate-400">Daftar tanda terima paket yang telah diserahterimakan</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="date" id="historyDateFilter" onchange="loadHistoryData()" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-slate-700">
                        <button onclick="loadHistoryData()" class="bg-slate-800 text-white px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-slate-700 transition">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                </div>

                <!-- Table Riwayat -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold uppercase text-[10px]">
                                <th class="py-2.5 px-3">No. Tanda Terima</th>
                                <th class="py-2.5 px-3">Ekspedisi</th>
                                <th class="py-2.5 px-3">Driver / Kurir</th>
                                <th class="py-2.5 px-3 text-center">Total Paket</th>
                                <th class="py-2.5 px-3">Operator</th>
                                <th class="py-2.5 px-3">Waktu</th>
                                <th class="py-2.5 px-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody" class="divide-y divide-slate-100">
                            <tr>
                                <td colspan="7" class="text-center py-8 text-slate-400">Memuat riwayat...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- FLOATING BOTTOM BAR UNTUK MOBILE (STICKY ACTION) -->
    <div class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-200 p-3 flex md:hidden items-center justify-between gap-2 z-20 shadow-lg no-print">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-black text-base shrink-0">
                <span id="mobileCountBadge">0</span>
            </div>
            <div class="leading-tight">
                <p class="text-[10px] text-slate-400 font-semibold">Total Paket</p>
                <p id="mobileExpBadge" class="text-xs font-bold text-slate-800 truncate max-w-[120px]">Pilih Ekspedisi</p>
            </div>
        </div>

        <button onclick="submitCompleteReception()" type="button" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold text-xs rounded-xl transition shadow-md shadow-emerald-600/30 flex items-center justify-center gap-2">
            <i class="fa-solid fa-check"></i>
            <span>Simpan (<span id="mobileBtnCount">0</span>)</span>
        </button>
    </div>

    <!-- MODAL DETAIL / BUKTI TANDA TERIMA CETAK -->
    <div id="receiptModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <!-- Header Modal -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-file-circle-check text-emerald-400 text-base"></i>
                    <h3 class="font-bold text-sm">Bukti Serah Terima Paket</h3>
                </div>
                <button onclick="closeReceiptModal()" class="text-slate-400 hover:text-white text-base">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Content Area (Printable) -->
            <div id="printReceiptArea" class="p-5 overflow-y-auto space-y-4 flex-1 text-xs">
                <!-- Header Slip -->
                <div class="text-center border-b border-dashed border-slate-300 pb-3">
                    <h2 class="font-black text-base tracking-tight">PT. INDO EXPRESS GLOBAL</h2>
                    <p class="text-[10px] text-slate-500 font-medium">INBOUND WAREHOUSE RETURN RECEPTION</p>
                    <p id="slipReceiptNo" class="font-mono font-bold text-xs text-emerald-600 mt-1">RCV-20260929-0001</p>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-2 gap-2 text-[11px] bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Ekspedisi</span>
                        <span id="slipExpedition" class="font-bold text-slate-800 text-xs">-</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Waktu Penerimaan</span>
                        <span id="slipDateTime" class="font-semibold text-slate-700">-</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Driver / Kurir</span>
                        <span id="slipCourier" class="font-semibold text-slate-700">-</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Operator Penerima</span>
                        <span id="slipOperator" class="font-bold text-slate-800">-</span>
                    </div>
                </div>

                <!-- Total Count Banner -->
                <div class="bg-emerald-500 text-white rounded-xl p-3 text-center">
                    <span class="text-[10px] uppercase font-bold opacity-80 block">Jumlah Paket Diterima</span>
                    <span id="slipTotalPackages" class="font-black text-2xl">0</span>
                    <span class="text-xs font-semibold"> Paket</span>
                </div>

                <!-- Daftar Resi Paket -->
                <div>
                    <h4 class="font-bold text-slate-700 mb-1.5 text-[11px] uppercase">Rincian Nomor Resi / Barcode:</h4>
                    <div id="slipPackageList" class="bg-slate-50 rounded-xl p-3 max-h-48 overflow-y-auto space-y-1 font-mono text-[11px] border border-slate-200">
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
                        <p id="slipSignOperator" class="text-[10px] font-bold text-slate-700 border-t border-slate-300 mx-4 pt-1"><?= htmlspecialchars($user['name']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Footer Modal Actions -->
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2 shrink-0 no-print">
                <button onclick="window.print()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Cetak Bukti
                </button>
                <button onclick="startNewReceptionAfterSave()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-plus"></i> Scan Penerimaan Baru
                </button>
            </div>
        </div>
    </div>

    <!-- AUDIO BEEP SYNTHESIZER (Tanpa file eksternal, jalan offline/mobile 100%) -->
    <script>
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        function playBeep(type = 'success') {
            try {
                if (audioCtx.state === 'suspended') audioCtx.resume();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);

                if (type === 'success') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, audioCtx.currentTime); // Nada A5
                    gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.12);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.12);
                } else if (type === 'warning') {
                    // Nada ganda peringatan duplikat
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(320, audioCtx.currentTime);
                    gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.25);
                }
            } catch (e) {}
        }

        // Getar HP haptic
        function vibrateMobile(ms = 80) {
            if (navigator.vibrate) {
                try { navigator.vibrate(ms); } catch (e) {}
            }
        }
    </script>

    <!-- APPLICATION LOGIC SCRIPT -->
    <script>
        // State Penerimaan
        let scannedPackages = [];
        let html5QrCode = null;
        let isCameraActive = false;

        // Inisialisasi saat halaman selesai dimuat
        document.addEventListener('DOMContentLoaded', () => {
            generateReceiptId();
            loadHistoryData();

            const inputPkg = document.getElementById('inputPackageBarcode');
            if (inputPkg) {
                inputPkg.focus();
                inputPkg.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitPackageBarcode();
                    }
                });
            }
        });

        // 1. Generate Receipt ID dari server
        async function generateReceiptId() {
            try {
                const res = await fetch('api/reception.php?action=generate_id');
                const data = await res.json();
                if (data && data.success && data.receipt_number) {
                    document.getElementById('inputReceiptNo').value = data.receipt_number;
                }
            } catch (e) {
                const now = new Date();
                const dStr = now.toISOString().slice(0, 10).replace(/-/g, '');
                document.getElementById('inputReceiptNo').value = `RCV-${dStr}-${Math.floor(1000 + Math.random() * 9000)}`;
            }
        }

        // 2. Event perubahan Ekspedisi
        function onExpeditionChanged() {
            const sel = document.getElementById('selectExpedition');
            const expName = sel.value;
            const mobileExp = document.getElementById('mobileExpBadge');
            if (mobileExp) mobileExp.innerText = expName || 'Pilih Ekspedisi';
            document.getElementById('inputPackageBarcode').focus();
        }

        // 3. Tambah Barcode Paket ke List
        function submitPackageBarcode() {
            const input = document.getElementById('inputPackageBarcode');
            const barcode = (input.value || '').trim();
            if (!barcode) return;

            addPackageToList(barcode);
            input.value = '';
            input.focus();
        }

        function addPackageToList(barcode) {
            const cleanBarcode = barcode.trim();
            if (!cleanBarcode) return;

            // Auto-detect ekspedisi jika belum dipilih
            const selectExp = document.getElementById('selectExpedition');
            if (!selectExp.value) {
                detectExpeditionFromBarcode(cleanBarcode);
            }

            // Cek duplikasi di sesi saat ini
            const isDuplicate = scannedPackages.some(item => item.barcode.toUpperCase() === cleanBarcode.toUpperCase());
            if (isDuplicate) {
                playBeep('warning');
                vibrateMobile([100, 50, 100]);
                showStatusMsg(`Resi <b>${escapeHtml(cleanBarcode)}</b> sudah pernah di-scan pada sesi ini!`, 'warning');
                return;
            }

            // Tambahkan paket baru
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            scannedPackages.unshift({
                barcode: cleanBarcode,
                time: timeStr,
                timestamp: Date.now()
            });

            playBeep('success');
            vibrateMobile(60);
            showStatusMsg(`Paket <b>${escapeHtml(cleanBarcode)}</b> berhasil ditambahkan.`, 'success');
            renderPackageList();
        }

        // 4. Deteksi otomatis ekspedisi dari barcode resi jika operator lupa pilih
        function detectExpeditionFromBarcode(code) {
            const upper = code.toUpperCase();
            const selectExp = document.getElementById('selectExpedition');
            for (let i = 0; i < selectExp.options.length; i++) {
                const opt = selectExp.options[i];
                const prefixStr = opt.getAttribute('data-prefix') || '';
                const prefixes = prefixStr.split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
                
                for (const p of prefixes) {
                    if (upper.startsWith(p)) {
                        selectExp.selectedIndex = i;
                        onExpeditionChanged();
                        return;
                    }
                }
            }
        }

        // 5. Render Daftar Paket di UI
        function renderPackageList() {
            const count = scannedPackages.length;
            document.getElementById('packageCountBadge').innerText = count;
            document.getElementById('btnSubmitCount').innerText = count;
            document.getElementById('listCountLabel').innerText = count;
            document.getElementById('mobileCountBadge').innerText = count;
            document.getElementById('mobileBtnCount').innerText = count;

            const emptyBox = document.getElementById('packageListEmpty');
            const container = document.getElementById('packageListContainer');

            if (count === 0) {
                emptyBox.classList.remove('hidden');
                container.classList.add('hidden');
                container.innerHTML = '';
                return;
            }

            emptyBox.classList.add('hidden');
            container.classList.remove('hidden');

            let html = '';
            scannedPackages.forEach((pkg, idx) => {
                const realSeq = count - idx;
                html += `
                    <div class="flex items-center justify-between py-2 px-3 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200/80 transition text-xs">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-lg bg-emerald-500/10 text-emerald-700 font-bold text-[11px] flex items-center justify-center shrink-0">
                                ${realSeq}
                            </span>
                            <div>
                                <span class="font-mono font-bold text-slate-800 text-xs tracking-tight">${escapeHtml(pkg.barcode)}</span>
                                <span class="text-[10px] text-slate-400 block">${pkg.time}</span>
                            </div>
                        </div>
                        <button onclick="removePackage(${idx})" type="button" title="Hapus resi ini" class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function removePackage(index) {
            if (index >= 0 && index < scannedPackages.length) {
                const removed = scannedPackages.splice(index, 1);
                showStatusMsg(`Resi ${escapeHtml(removed[0].barcode)} dihapus dari daftar.`, 'info');
                renderPackageList();
            }
        }

        function clearAllPackages() {
            if (scannedPackages.length === 0) return;
            if (confirm(`Hapus semua ${scannedPackages.length} paket dari daftar sementara ini?`)) {
                scannedPackages = [];
                renderPackageList();
                showStatusMsg('Semua paket dalam daftar telah dibersihkan.', 'info');
            }
        }

        // 6. Kamera Scanner Mobile (HTML5-QRCode)
        function toggleCameraScanner() {
            const container = document.getElementById('cameraScannerContainer');
            if (isCameraActive) {
                stopCameraScanner();
            } else {
                startCameraScanner();
            }
        }

        function startCameraScanner() {
            const container = document.getElementById('cameraScannerContainer');
            container.classList.remove('hidden');

            html5QrCode = new Html5Qrcode("reader");
            const config = {
                fps: 12,
                qrbox: { width: 280, height: 180 },
                aspectRatio: 1.333
            };

            html5QrCode.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    // Berhasil scan barcode dari kamera
                    if (decodedText) {
                        addPackageToList(decodedText);
                    }
                },
                (errorMessage) => {
                    // scan parse error, continue
                }
            ).then(() => {
                isCameraActive = true;
                document.getElementById('btnToggleCamera').innerHTML = '<i class="fa-solid fa-camera-rotate"></i> Matikan Kamera';
                document.getElementById('btnToggleCamera').classList.replace('bg-emerald-600', 'bg-rose-600');
            }).catch((err) => {
                alert("Tidak dapat mengakses kamera: " + err);
                container.classList.add('hidden');
            });
        }

        function stopCameraScanner() {
            if (html5QrCode && isCameraActive) {
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    isCameraActive = false;
                    document.getElementById('cameraScannerContainer').classList.add('hidden');
                    document.getElementById('btnToggleCamera').innerHTML = '<i class="fa-solid fa-camera"></i> <span class="hidden sm:inline">Kamera</span>';
                    document.getElementById('btnToggleCamera').classList.replace('bg-rose-600', 'bg-emerald-600');
                }).catch(() => {});
            } else {
                document.getElementById('cameraScannerContainer').classList.add('hidden');
            }
        }

        // 7. Notifikasi Pesan
        function showStatusMsg(msg, type = 'info') {
            const el = document.getElementById('scanStatusMsg');
            const text = document.getElementById('scanStatusText');
            el.className = 'text-xs font-semibold px-3 py-2 rounded-xl flex items-center justify-between transition ';
            if (type === 'success') {
                el.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
            } else if (type === 'warning') {
                el.classList.add('bg-amber-50', 'text-amber-800', 'border', 'border-amber-200');
            } else {
                el.classList.add('bg-slate-100', 'text-slate-700', 'border', 'border-slate-200');
            }
            text.innerHTML = msg;
            el.classList.remove('hidden');
        }

        function dismissStatusMsg() {
            document.getElementById('scanStatusMsg').classList.add('hidden');
        }

        // 8. Submit Selesai Penerimaan (Multiple Packages -> 1 ID)
        async function submitCompleteReception() {
            const expSelect = document.getElementById('selectExpedition');
            const expedition = expSelect.value.trim();
            if (!expedition) {
                alert('Silakan pilih Ekspedisi Pengantar terlebih dahulu!');
                expSelect.focus();
                return;
            }

            if (scannedPackages.length === 0) {
                alert('Minimal 1 barcode/resi paket harus di-scan sebelum submit!');
                document.getElementById('inputPackageBarcode').focus();
                return;
            }

            const receiptNo = document.getElementById('inputReceiptNo').value.trim();
            const courierName = document.getElementById('inputCourierName').value.trim();

            const confirmMsg = `Simpan serah terima ${scannedPackages.length} paket untuk Ekspedisi ${expedition}?`;
            if (!confirm(confirmMsg)) return;

            const btn = document.getElementById('btnSubmitReception');
            const origText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan Penerimaan...';

            try {
                const payload = {
                    receipt_number: receiptNo,
                    expedition: expedition,
                    courier_name: courierName,
                    packages: scannedPackages.map(p => p.barcode)
                };

                const res = await fetch('api/reception.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data && data.success) {
                    showStatusMsg(`✅ Sukses! Penerimaan <b>${data.total_packages} paket</b> (${escapeHtml(data.expedition)}) berhasil disimpan! [${escapeHtml(data.receipt_number)}]`, 'success');
                    resetReceptionForm();
                    // Muat ulang data riwayat
                    loadHistoryData();
                } else {
                    alert('Gagal menyimpan: ' + (data.error || 'Terjadi kesalahan sistem'));
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        }

        // 9. Tampilkan Modal Tanda Terima
        function showReceiptModal(data) {
            document.getElementById('slipReceiptNo').innerText = data.receipt_number;
            document.getElementById('slipExpedition').innerText = data.expedition;
            document.getElementById('slipDateTime').innerText = data.created_at;
            document.getElementById('slipCourier').innerText = data.courier_name || '-';
            document.getElementById('slipTotalPackages').innerText = data.total_packages;

            const listEl = document.getElementById('slipPackageList');
            let listHtml = '';
            (data.packages || []).forEach((bar, i) => {
                listHtml += `<div class="flex justify-between border-b border-slate-100 py-0.5"><span>${i + 1}. ${escapeHtml(bar)}</span><span class="text-[9px] text-slate-400">OK</span></div>`;
            });
            listEl.innerHTML = listHtml;

            document.getElementById('receiptModal').classList.remove('hidden');
        }

        function closeReceiptModal() {
            document.getElementById('receiptModal').classList.add('hidden');
        }

        function startNewReceptionAfterSave() {
            closeReceiptModal();
            resetReceptionForm();
        }

        function resetReceptionForm() {
            scannedPackages = [];
            document.getElementById('inputCourierName').value = '';
            document.getElementById('inputPackageBarcode').value = '';
            renderPackageList();
            generateReceiptId();
            dismissStatusMsg();
            document.getElementById('inputPackageBarcode').focus();
        }

        // 10. Tab Switcher
        function switchTab(tab) {
            const btnScan = document.getElementById('tabBtnScan');
            const btnHist = document.getElementById('tabBtnHistory');
            const viewScan = document.getElementById('viewScan');
            const viewHist = document.getElementById('viewHistory');

            if (tab === 'scan') {
                btnScan.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-emerald-600 text-white shadow-sm';
                btnHist.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
                viewScan.classList.remove('hidden');
                viewHist.classList.add('hidden');
                document.getElementById('inputPackageBarcode').focus();
            } else {
                btnHist.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-emerald-600 text-white shadow-sm';
                btnScan.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
                viewScan.classList.add('hidden');
                viewHist.classList.remove('hidden');
                loadHistoryData();
            }
        }

        // 11. Muat Riwayat Penerimaan Hari Ini
        async function loadHistoryData() {
            const dateInput = document.getElementById('historyDateFilter');
            const selectedDate = dateInput.value || new Date().toISOString().slice(0, 10);
            if (!dateInput.value) dateInput.value = selectedDate;

            try {
                const res = await fetch(`api/reception.php?action=list&date=${selectedDate}`);
                const data = await res.json();
                const tbody = document.getElementById('historyTableBody');
                const badge = document.getElementById('historyCountBadge');

                if (data && data.success && Array.isArray(data.data)) {
                    badge.innerText = data.data.length;
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-slate-400">Belum ada penerimaan ekspedisi pada tanggal ini.</td></tr>';
                        return;
                    }

                    let rows = '';
                    data.data.forEach(item => {
                        const timeOnly = (item.created_at || '').split(' ')[1] || item.created_at;
                        rows += `
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-2.5 px-3 font-mono font-bold text-slate-800">${escapeHtml(item.receipt_number)}</td>
                                <td class="py-2.5 px-3 font-semibold text-emerald-700">${escapeHtml(item.expedition)}</td>
                                <td class="py-2.5 px-3 text-slate-600">${escapeHtml(item.courier_name || '-')}</td>
                                <td class="py-2.5 px-3 text-center font-bold text-slate-900">
                                    <span class="bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-lg text-xs">${item.total_packages}</span>
                                </td>
                                <td class="py-2.5 px-3 text-slate-600">${escapeHtml(item.operator_name)}</td>
                                <td class="py-2.5 px-3 text-slate-500 font-mono text-[11px]">${timeOnly}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <button onclick="viewReceptionDetail(${item.id})" class="text-indigo-600 hover:text-indigo-800 font-bold text-xs bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg transition">
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = rows;
                }
            } catch (e) {
                console.error('Gagal memuat riwayat:', e);
            }
        }

        // 12. Lihat Detail Penerimaan Riwayat
        async function viewReceptionDetail(id) {
            try {
                const res = await fetch(`api/reception.php?action=detail&id=${id}`);
                const data = await res.json();
                if (data && data.success && data.reception) {
                    showReceiptModal({
                        receipt_number: data.reception.receipt_number,
                        expedition: data.reception.expedition,
                        courier_name: data.reception.courier_name,
                        vehicle_no: data.reception.vehicle_no,
                        total_packages: data.reception.total_packages,
                        packages: (data.packages || []).map(p => p.package_barcode),
                        created_at: data.reception.created_at
                    });
                }
            } catch (e) {
                alert('Gagal mengambil detail penerimaan: ' + e.message);
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
            });
        }
    </script>
</body>
</html>
