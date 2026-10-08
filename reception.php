<?php
require_once __DIR__ . '/config.php';
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$currentUser = getSessionUser();
checkMaintenanceMode($pdo, $currentUser);
$user = requireLogin(['operator', 'admin', 'superadmin', 'management', 'accounting']);


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
    <?php
    $appBaseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $appBaseHref = ($appBaseDir === '' || $appBaseDir === '/') ? '/' : ($appBaseDir . '/');
    ?>
    <base href="<?= htmlspecialchars($appBaseHref) ?>">
    <script>window.APP_BASE_URL = <?= json_encode($appBaseHref) ?>;</script>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            body > *:not(#receiptModal) {
                display: none !important;
            }
            #receiptModal {
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
            #receiptModal > div {
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
            #receiptModal .no-print,
            #receiptModal > div > div:first-child {
                display: none !important;
            }
            #printReceiptArea {
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
            #printReceiptArea img {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #printReceiptArea .sj-header {
                border-bottom: 2px solid #0f172a !important;
                padding-bottom: 6px !important;
                margin-bottom: 8px !important;
            }
            #printReceiptArea .sj-info-table {
                width: 100% !important;
                border-collapse: collapse !important;
                border: 1px solid #cbd5e1 !important;
                margin-bottom: 6px !important;
                font-size: 8pt !important;
            }
            #printReceiptArea .sj-info-table td {
                padding: 3px 6px !important;
                border: 1px solid #cbd5e1 !important;
            }
            #printReceiptArea #slipPackageList {
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
            #printReceiptArea #slipPackageList > div {
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
            #printReceiptArea #slipPackageList span,
            #printReceiptArea #slipPackageList div {
                text-overflow: clip !important;
                white-space: normal !important;
                word-break: break-all !important;
                overflow: visible !important;
            }
            #printReceiptArea #slipSackBreakdownSection {
                background-color: #fffbeb !important;
                border: 1px solid #fde68a !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                padding: 4px 8px !important;
                margin-bottom: 6px !important;
            }
            #printReceiptArea #slipSackBreakdownList {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 4px !important;
            }
            #printReceiptArea #slipSackBreakdownList > div {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                border: 1px solid #fcd34d !important;
                background-color: #ffffff !important;
                padding: 2px 5px !important;
                font-size: 7.5pt !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #printReceiptArea .total-banner {
                background: #059669 !important;
                color: #ffffff !important;
                padding: 5px 10px !important;
                border-radius: 6px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                margin-bottom: 6px !important;
            }
            #printReceiptArea .signatures-box {
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
<body class="bg-slate-100 min-h-screen text-slate-800 antialiased flex flex-col justify-between selection:bg-emerald-500 selection:text-white pb-24 md:pb-6">

    <!-- TOP NAVBAR (RESPONSIVE & MOBILE FIRST) -->
    <header class="bg-slate-900 border-b border-slate-800 text-white shadow-lg sticky top-0 z-30 w-full no-print">
        <div class="w-full max-w-5xl mx-auto px-3 sm:px-4 py-2 flex justify-between items-center gap-2">
            
            <!-- Brand / Logo & Title -->
            <div class="flex items-center space-x-2 shrink-0">
                <div class="w-8 h-8 rounded-xl bg-white p-1 flex items-center justify-center shadow shrink-0">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h1 class="font-black text-white text-xs sm:text-sm tracking-tight leading-none">Inbound Receiving</h1>
                        <span class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-[9px] font-bold px-1.5 py-0.2 rounded leading-tight">Mobile</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium hidden sm:block">Serah Terima & Scan Barcode Paket Inbound</p>
                </div>
            </div>
            
            <!-- Navigation Switcher & User Actions -->
            <div class="flex items-center space-x-1.5 sm:space-x-2">
                <!-- Tombol Menu Utama Hub -->
                <a href="menu" title="Menu Utama Portal" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 px-2.5 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-xs">
                    <i class="fa-solid fa-shapes text-indigo-400 text-xs"></i>
                    <span class="hidden md:inline">Menu</span>
                </a>

                <!-- Nav Unboxing Station -->
                <a href="scanner" title="Unboxing Station" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 px-2.5 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-xs">
                    <i class="fa-solid fa-box-open text-indigo-400 text-xs"></i>
                    <span class="hidden md:inline">Unboxing</span>
                </a>

                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <a href="admin" title="Panel Admin" class="hidden sm:flex bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-2.5 py-1.5 rounded-xl font-semibold transition items-center gap-1 shadow-xs">
                    <i class="fa-solid fa-chart-pie text-xs"></i>
                    <span class="hidden md:inline">Admin</span>
                </a>
                <?php endif; ?>

                <!-- Operator Badge Ringkas -->
                <div class="flex items-center gap-1.5 bg-slate-800/90 border border-slate-700 px-2 py-1.5 rounded-xl text-xs" title="Operator: <?= htmlspecialchars($user['name']) ?>">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span class="font-bold text-slate-200 text-[11px] truncate max-w-[85px] sm:max-w-[120px]"><?= htmlspecialchars($user['name']) ?></span>
                </div>

                <!-- Tombol Logout -->
                <a href="logout" onclick="return confirm('Keluar dari sesi ini?')" title="Logout" class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white flex items-center justify-center transition shadow-xs shrink-0">
                    <i class="fa-solid fa-arrow-right-from-bracket text-[11px]"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- WORKSPACE UTAMA -->
    <main class="w-full max-w-5xl mx-auto px-2.5 sm:px-4 py-3 space-y-3 flex-1 no-print">

        <!-- TAB MENU: 1. SCAN PENERIMAAN | 2. RIWAYAT HARI INI -->
        <div class="flex items-center justify-between bg-white p-1 rounded-2xl border border-slate-200 shadow-xs">
            <button id="tabBtnScan" onclick="switchTab('scan')" class="flex-1 py-1.5 sm:py-2 px-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-emerald-600 text-white shadow-xs">
                <i class="fa-solid fa-barcode text-xs"></i>
                <span>Scan Penerimaan</span>
            </button>
            <button id="tabBtnHistory" onclick="switchTab('history')" class="flex-1 py-1.5 sm:py-2 px-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900">
                <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                <span>Riwayat (<span id="historyTabCountBadge">0</span>)</span>
            </button>
        </div>

        <!-- ============================================================== -->
        <!-- VIEW 1: SCAN PENERIMAAN BARU (PROGRESSIVE STEP-BY-STEP HANDHELD)-->
        <!-- ============================================================== -->
        <div id="viewScan" class="space-y-3">

            <!-- STEP 1: PILIH EKSPEDISI -->
            <div id="cardStep1" class="bg-white rounded-2xl shadow-xs border border-slate-200/90 p-3 sm:p-3.5 space-y-2.5 transition-all">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-600 text-white font-black text-xs flex items-center justify-center shrink-0">1</span>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pilih Ekspedisi</h2>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span id="badgeReceiptDisplay" class="bg-slate-100 text-slate-600 font-mono text-[10px] font-bold px-2 py-0.5 rounded-md border border-slate-200">
                            Memuat ID...
                        </span>
                        <button onclick="confirmResetReceptionForm()" type="button" class="text-[11px] text-amber-600 hover:text-amber-700 font-semibold flex items-center gap-1 ml-1" title="Reset Ulang Form">
                            <i class="fa-solid fa-rotate text-xs"></i> <span class="hidden sm:inline">Reset</span>
                        </button>
                    </div>
                </div>

                <div class="relative" id="step1SelectWrapper">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                        <i class="fa-solid fa-truck-fast"></i>
                    </span>
                    <select id="selectExpedition" onchange="onExpeditionSelected()" class="w-full pl-9 pr-8 py-2.5 bg-slate-50 border-2 border-slate-300 rounded-xl text-xs sm:text-sm font-bold text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition appearance-none">
                        <option value="">-- Sentuh & Pilih Ekspedisi Pengantar --</option>
                        <?php foreach ($expeditions as $exp): ?>
                            <option value="<?= htmlspecialchars($exp['name']) ?>" data-prefix="<?= htmlspecialchars($exp['prefix_pattern'] ?? '') ?>">
                                <?= htmlspecialchars($exp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs">
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </span>
                </div>

                <div id="step1Summary" class="hidden flex items-center justify-between bg-emerald-50 border border-emerald-200 px-3 py-2 rounded-xl text-xs">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                        <div>
                            <span class="text-[9px] uppercase font-bold text-emerald-700 block">Ekspedisi Terpilih</span>
                            <span id="txtSelectedExpedition" class="font-black text-emerald-950 text-xs sm:text-sm">-</span>
                        </div>
                    </div>
                    <button type="button" onclick="changeExpedition()" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 bg-white border border-emerald-300 px-2.5 py-1 rounded-lg shadow-2xs">
                        Ubah
                    </button>
                </div>
            </div>

            <!-- STEP 2: NAMA DRIVER & NOMOR KARUNG (TERBUKA SETELAH STEP 1) -->
            <div id="cardStep2" class="hidden bg-white rounded-2xl shadow-xs border border-slate-200/90 p-3 sm:p-3.5 space-y-2.5 transition-all">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-600 text-white font-black text-xs flex items-center justify-center shrink-0">2</span>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Driver / Kurir & Nomor Karung</h2>
                    </div>
                    <span class="text-[10px] text-slate-400 font-medium">Input kurir & nomor karung</span>
                </div>

                <div id="step2InputWrapper" class="space-y-2.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- Input Nama Kurir -->
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Nama Driver / Kurir</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-id-card"></i>
                                </span>
                                <input type="text" id="inputCourierName" placeholder="Ketik nama kurir pengantar..." 
                                    class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border-2 border-slate-300 rounded-xl text-xs sm:text-sm font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                            </div>
                        </div>

                        <!-- Input Nomor Karung -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[10px] font-bold uppercase text-slate-500">Nomor / Label Karung</label>
                                <span class="text-[9px] text-amber-700 font-bold bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200">Karung Paket</span>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-box-archive"></i>
                                </span>
                                <input type="text" id="inputSackNumber" placeholder="Contoh: Karung 1, KR-01..." value="Karung 1" oninput="saveDraftToStorage()"
                                    class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border-2 border-slate-300 rounded-xl text-xs sm:text-sm font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500 transition font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- Quick Chips Pilihan Karung Cepat -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-[10px] text-slate-400 font-medium mr-1">Pilih Cepat:</span>
                        <button type="button" onclick="selectQuickSack('Karung 1')" class="quick-sack-chip px-2.5 py-1 bg-amber-500 text-white border border-amber-600 rounded-lg text-[11px] font-bold transition shadow-2xs">Karung 1</button>
                        <button type="button" onclick="selectQuickSack('Karung 2')" class="quick-sack-chip px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold transition shadow-2xs">Karung 2</button>
                        <button type="button" onclick="selectQuickSack('Karung 3')" class="quick-sack-chip px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold transition shadow-2xs">Karung 3</button>
                        <button type="button" onclick="selectQuickSack('Karung 4')" class="quick-sack-chip px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold transition shadow-2xs">Karung 4</button>
                        <button type="button" onclick="selectQuickSack('Karung 5')" class="quick-sack-chip px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold transition shadow-2xs">Karung 5</button>
                    </div>

                    <p class="text-[10px] text-amber-800 bg-amber-50/80 border border-amber-200/60 rounded-xl px-2.5 py-1.5 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info text-amber-600 shrink-0"></i>
                        <span><b>Bisa multi-karung:</b> 1 kurir bisa bawa banyak karung dalam 1 ID serah terima. Anda bisa langsung tambah & pindah karung di Langkah 4 saat scan.</span>
                    </p>

                    <div class="pt-1">
                        <button type="button" id="btnConfirmCourier" onclick="confirmCourierName()" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white py-2.5 rounded-xl font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-sm shadow-indigo-600/20">
                            <i class="fa-solid fa-camera text-sm"></i>
                            <span>Konfirmasi & Ambil Foto Kurir</span>
                        </button>
                    </div>
                </div>

                <div id="step2Summary" class="hidden flex items-center justify-between bg-indigo-50 border border-indigo-200 px-3 py-2 rounded-xl text-xs">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-user-check text-indigo-600 text-sm"></i>
                            <div>
                                <span class="text-[9px] uppercase font-bold text-indigo-700 block">Nama Kurir</span>
                                <span id="txtConfirmedCourier" class="font-black text-indigo-950 text-xs sm:text-sm">-</span>
                            </div>
                        </div>
                        <div class="border-l border-indigo-200 pl-3">
                            <span class="text-[9px] uppercase font-bold text-amber-700 block">Nomor Karung</span>
                            <span id="txtConfirmedSack" class="font-black text-amber-950 text-xs sm:text-sm font-mono">-</span>
                        </div>
                    </div>
                    <button type="button" onclick="editCourierName()" class="text-xs font-bold text-indigo-700 hover:text-indigo-900 bg-white border border-indigo-300 px-2.5 py-1 rounded-lg shadow-2xs">
                        Ubah
                    </button>
                </div>
            </div>

            <!-- STEP 3: FOTO KURIR TERVERIFIKASI (TERBUKA SETELAH FOTO KURIR DIAMBIL) -->
            <div id="cardStep3" class="hidden bg-white rounded-2xl shadow-xs border border-slate-200/90 p-3 sm:p-3.5 space-y-2.5 transition-all">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-600 text-white font-black text-xs flex items-center justify-center shrink-0">3</span>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Foto Kurir Serah Terima</h2>
                    </div>
                    <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1">
                        <i class="fa-solid fa-check"></i> Terverifikasi
                    </span>
                </div>

                <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 p-2.5 rounded-xl">
                    <div class="relative w-16 h-16 rounded-xl overflow-hidden bg-black shrink-0 border border-slate-300 shadow-xs cursor-pointer group" onclick="previewCourierPhoto()" title="Klik untuk perbesar">
                        <img id="imgCourierPhotoPreview" src="" alt="Foto Kurir" class="w-full h-full object-cover group-hover:scale-105 transition">
                        <div class="absolute inset-0 bg-black/20 group-hover:bg-transparent transition"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span id="txtCourierPhotoName" class="font-black text-slate-800 text-xs sm:text-sm truncate block">-</span>
                            <span class="text-[9px] bg-indigo-100 text-indigo-800 font-bold px-1.5 py-0.2 rounded">Kurir</span>
                        </div>
                        <span class="text-[10px] text-slate-400 block mt-0.5" id="txtCourierPhotoTime">-</span>
                        <button type="button" onclick="retakeCourierPhoto()" class="mt-1 text-[11px] font-bold text-amber-600 hover:text-amber-800 flex items-center gap-1">
                            <i class="fa-solid fa-camera-rotate"></i> Foto Ulang Kurir
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 4: SCAN RESI & WAJIB FOTO PAKET (MODE HANDHELD SCANNER) -->
            <div id="cardStep4" class="hidden bg-white rounded-2xl shadow-xs border border-slate-200/90 p-3 sm:p-3.5 space-y-2.5 transition-all">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-600 text-white font-black text-xs flex items-center justify-center shrink-0">4</span>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Scan Resi & Wajib Foto Paket</h2>
                    </div>
                    <span class="bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1">
                        <i class="fa-solid fa-hand-holding-hand"></i> Handheld Scanner
                    </span>
                </div>

                <!-- Active Sack Indicator & Quick Switcher (Bisa Banyak Karung dalam 1 ID) -->
                <div class="bg-amber-50/90 border border-amber-200/90 p-2.5 rounded-xl space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-2xs">
                                <i class="fa-solid fa-box-archive text-xs"></i>
                            </span>
                            <div>
                                <span class="text-[9px] uppercase font-bold text-amber-800 block">Karung Aktif (Target Scan Saat Ini)</span>
                                <span id="displayActiveSack" class="font-black text-amber-950 text-xs sm:text-sm font-mono">Karung 1</span>
                            </div>
                        </div>
                        <button type="button" onclick="promptCustomSack()" class="text-xs font-bold text-amber-900 hover:text-amber-950 bg-white hover:bg-amber-100 border border-amber-300 px-2.5 py-1 rounded-lg shadow-2xs flex items-center gap-1.5 transition ml-auto" title="Buat nomor atau label karung baru">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>+ Karung Lain</span>
                        </button>
                    </div>

                    <!-- Tombol Cepat Pindah Karung (Karung 1, Karung 2, dst) -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 pt-0.5" id="step4SackChipsList">
                        <!-- Di-render dinamis oleh JavaScript -->
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex gap-1.5 items-stretch">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-sm">
                                <i class="fa-solid fa-barcode"></i>
                            </span>
                            <input type="text" id="inputPackageBarcode" placeholder="Arahkan laser scanner & tembak resi..." autocomplete="off" 
                                class="w-full pl-9 pr-3 py-2.5 sm:py-3 bg-emerald-50/50 border-2 border-emerald-500 rounded-xl text-xs sm:text-base font-mono font-black text-slate-900 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/20 transition shadow-inner">
                        </div>
                        <button onclick="submitPackageBarcode()" type="button" class="bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl font-bold text-xs transition flex items-center gap-1.5 shadow-md shadow-emerald-600/20 shrink-0">
                            <i class="fa-solid fa-camera"></i>
                            <span class="hidden sm:inline">Foto Paket</span>
                            <span class="sm:hidden">Foto</span>
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-500 italic">
                        ⚡ Setiap scan resi akan otomatis membuka kamera. <b>Semua paket wajib difoto</b> sebelum masuk ke sistem.
                    </p>
                </div>

                <!-- Notifikasi Status Bar -->
                <div id="scanStatusMsg" class="hidden text-xs font-semibold px-3 py-2 rounded-xl flex items-center justify-between transition">
                    <span id="scanStatusText"></span>
                    <button onclick="dismissStatusMsg()" class="text-slate-400 hover:text-slate-600 text-xs ml-2"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <!-- STEP 5: DAFTAR PAKET DRAFT & SUBMIT PENERIMAAN -->
            <div id="cardStep5" class="hidden bg-white rounded-2xl shadow-xs border border-slate-200/90 p-3 sm:p-3.5 space-y-2.5 transition-all">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-slate-800 text-white font-black text-xs flex items-center justify-center shrink-0">5</span>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Daftar Paket Terfoto (<span id="draftCountBadgeTop">0</span>)</h2>
                    </div>
                    <button onclick="clearAllDrafts()" type="button" class="text-[11px] text-rose-500 hover:text-rose-700 font-semibold transition flex items-center gap-1">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                        <span>Hapus Semua</span>
                    </button>
                </div>

                <!-- Ringkasan Distribusi Karung dalam 1 ID Penerimaan -->
                <div id="draftSacksSummaryBar" class="hidden flex items-center justify-between bg-amber-50/60 border border-amber-200/70 px-3 py-2 rounded-xl text-xs flex-wrap gap-2">
                    <div class="flex items-center gap-1.5 text-amber-900 font-bold text-[11px]">
                        <i class="fa-solid fa-layer-group text-amber-600"></i>
                        <span>Rincian Karung ID Ini:</span>
                    </div>
                    <div id="draftSacksSummaryChips" class="flex items-center gap-1.5 flex-wrap"></div>
                </div>

                <!-- Empty State Draft -->
                <div id="draftListEmpty" class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center text-slate-400">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-1.5 text-base">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-600">Belum Ada Paket di Draft</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Scan resi pada langkah 4 di atas untuk memotret dan mengumpulkan paket.</p>
                </div>

                <!-- List Paket Draft Cards -->
                <div id="draftPackagesContainer" class="hidden space-y-2 max-h-[380px] overflow-y-auto pr-0.5">
                    <!-- Dynamic Draft Cards -->
                </div>

                <!-- Submit Button Desktop / Tablet -->
                <div class="pt-2">
                    <button id="btnSubmitReception" onclick="submitCompleteReception()" type="button" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-[0.99] text-white rounded-xl font-black text-xs sm:text-sm transition shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check-double text-base"></i>
                        <span>SIMPAN & SELESAIKAN PENERIMAAN (<span id="btnSubmitCount">0</span> PAKET)</span>
                    </button>
                </div>
            </div>

            <!-- Hidden Input ID Penerimaan -->
            <input type="hidden" id="inputReceiptNo" value="">
        </div>

        <!-- ============================================================== -->
        <!-- VIEW 2: RIWAYAT PENERIMAAN PER PAKET                           -->
        <!-- ============================================================== -->
        <div id="viewHistory" class="hidden space-y-3">
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200/90 p-3 sm:p-4 space-y-3">
                
                <!-- Header Toolbar & Metrics -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xs sm:text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-emerald-600 text-lg">inventory_2</span>
                                <span>Riwayat Paket Diterima (Per Resi)</span>
                            </h2>
                            <span id="historyCountBadge" class="bg-emerald-100 text-emerald-800 font-mono font-bold text-xs px-2.5 py-0.5 rounded-full">0 Paket</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">Daftar paket yang telah di-scan dan difoto secara terperinci per nomor resi</p>
                    </div>

                    <!-- Counter Badges -->
                    <div class="flex items-center gap-2">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="text-[11px] text-slate-500 font-medium">Foto Tersimpan:</span>
                            <span id="historyPhotoCountBadge" class="font-mono font-bold text-xs text-slate-800">0</span>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Bar -->
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
                    <!-- Search Input -->
                    <div class="sm:col-span-5 relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="historySearchInput" oninput="debounceHistorySearch()" placeholder="Cari barcode resi / kurir / ref..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-800 focus:bg-white focus:border-emerald-500 transition outline-none">
                        <button type="button" id="historyClearSearch" onclick="clearHistorySearch()" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>

                    <!-- Ekspedisi Filter -->
                    <div class="sm:col-span-3">
                        <select id="historyExpeditionFilter" onchange="loadHistoryData()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-700 outline-none">
                            <option value="">Semua Ekspedisi</option>
                        </select>
                    </div>

                    <!-- Date Filter -->
                    <div class="sm:col-span-3">
                        <input type="date" id="historyDateFilter" onchange="loadHistoryData()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-slate-700 outline-none">
                    </div>

                    <!-- Refresh Button -->
                    <div class="sm:col-span-1 flex justify-end">
                        <button onclick="loadHistoryData()" title="Muat Ulang" class="w-full sm:w-auto h-9 px-3 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                </div>

                <!-- Table Riwayat Per Paket -->
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 font-extrabold uppercase text-[10px] tracking-wider">
                                <th class="py-2.5 px-3 text-center w-12">#</th>
                                <th class="py-2.5 px-3 text-center w-20">Foto Paket</th>
                                <th class="py-2.5 px-3">No. Resi / Barcode</th>
                                <th class="py-2.5 px-3">Ekspedisi</th>
                                <th class="py-2.5 px-3">Driver / Kurir</th>
                                <th class="py-2.5 px-3">Karung</th>
                                <th class="py-2.5 px-3">Ref Serah Terima</th>
                                <th class="py-2.5 px-3">Waktu Scan</th>
                                <th class="py-2.5 px-3">Operator</th>
                                <th class="py-2.5 px-3 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody" class="divide-y divide-slate-100 bg-white">
                            <tr>
                                <td colspan="10" class="text-center py-10 text-slate-400">
                                    <i class="fa-solid fa-spinner fa-spin text-emerald-500 mr-2 text-base"></i> Memuat data paket...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </main>

    <!-- MOBILE FLOATING STICKY BOTTOM BAR (TUNGGAL & APP-LIKE KHUSUS HP) -->
    <div id="mobileStickyBar" class="fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-md px-3.5 py-2.5 border-t border-slate-800 flex justify-between items-center md:hidden shadow-2xl no-print">
        <div class="flex items-center gap-2 min-w-0">
            <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-black text-xs shrink-0 border border-amber-500/30">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div class="leading-tight min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Draft:</span>
                    <span id="mobileDraftCount" class="font-mono font-black text-amber-400 text-sm">0</span>
                    <span class="text-[10px] text-slate-400">Paket</span>
                </div>
                <span id="mobileExpBadge" class="text-[10px] text-slate-300 font-semibold block truncate max-w-[120px]">Pilih Ekspedisi</span>
            </div>
        </div>

        <button onclick="submitCompleteReception()" type="button" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-black text-xs px-4 py-2.5 rounded-xl shadow-lg shadow-emerald-900/40 flex items-center gap-2 transition shrink-0">
            <i class="fa-solid fa-paper-plane text-xs"></i>
            <span>SIMPAN (<span id="mobileBtnCount">0</span>)</span>
        </button>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL KAMERA TERPADU (FOTO KURIR & WAJIB FOTO PAKET RESI)      -->
    <!-- ============================================================== -->
    <div id="modalCameraCapture" class="hidden fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex flex-col justify-between items-center p-2.5 sm:p-4 no-print select-none">
        
        <!-- Header Info Modal Kamera -->
        <div class="w-full max-w-md bg-slate-900/90 text-white rounded-2xl px-3.5 py-2.5 flex items-center justify-between border border-white/10 shadow-lg">
            <div class="flex items-center gap-2 min-w-0">
                <span id="camModalBadgeIcon" class="w-7 h-7 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs shrink-0 border border-emerald-500/30">
                    <i class="fa-solid fa-camera"></i>
                </span>
                <div class="min-w-0">
                    <h3 id="camModalTitle" class="text-xs sm:text-sm font-black tracking-wide text-white truncate">Ambil Foto</h3>
                    <p id="camModalSubtitle" class="text-[10px] text-slate-300 font-mono truncate">Arahkan kamera ke objek...</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" id="btnModalSwitchCam" onclick="switchModalCamera()" class="hidden w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 text-white flex items-center justify-center text-xs transition" title="Ganti Kamera">
                    <i class="fa-solid fa-camera-rotate"></i>
                </button>
                <button type="button" onclick="closeModalCamera()" class="w-8 h-8 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-400 flex items-center justify-center text-xs transition active:scale-95" title="Batal / Tutup">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Viewport Kamera / Preview Box -->
        <div class="relative w-full max-w-md flex-1 my-2 flex items-center justify-center overflow-hidden rounded-2xl bg-black border border-slate-800 shadow-2xl">
            <!-- Video Live Stream -->
            <video id="modalCameraVideo" playsinline autoplay muted class="w-full h-full object-cover"></video>

            <!-- Image Preview (Saat selesai dijepret) -->
            <img id="modalCameraPreviewImg" src="" alt="Preview Foto" class="hidden w-full h-full object-contain bg-black">

            <!-- Target Reticle Box Frame (Khusus saat live) -->
            <div id="modalCameraReticle" class="pointer-events-none absolute inset-4 sm:inset-8 border-2 border-dashed border-emerald-400/70 rounded-2xl flex flex-col justify-between p-3">
                <div class="flex justify-between items-start">
                    <span class="bg-black/60 backdrop-blur-xs text-[10px] font-mono text-emerald-400 font-bold px-2 py-0.5 rounded border border-emerald-500/30" id="camModalTargetTag">IEG CAMERA</span>
                    <span class="bg-black/60 backdrop-blur-xs text-[10px] font-mono text-amber-400 font-bold px-2 py-0.5 rounded border border-amber-500/30" id="camModalLiveClock">00:00:00</span>
                </div>
                <div class="text-center">
                    <span id="camModalGuideText" class="bg-black/60 backdrop-blur-xs text-[11px] font-bold text-white px-3 py-1 rounded-full border border-white/20">
                        Posisikan objek di dalam kotak
                    </span>
                </div>
            </div>

            <!-- Loading Spinner saat kamera loading -->
            <div id="modalCameraLoading" class="absolute inset-0 bg-slate-950 flex flex-col items-center justify-center gap-2 text-white">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-emerald-400"></i>
                <span class="text-xs font-semibold text-slate-300">Menghubungkan kamera...</span>
            </div>
        </div>

        <!-- Controls Footer (Jepret / Konfirmasi) -->
        <div class="w-full max-w-md bg-slate-900/90 rounded-2xl p-3 border border-white/10 shadow-lg">
            
            <!-- Controls State 1: LIVE SHOOTING -->
            <div id="modalControlsLive" class="flex items-center justify-between gap-3">
                <button type="button" onclick="triggerModalNativeCamera()" class="flex-1 py-2.5 px-3 bg-white/10 hover:bg-white/20 active:scale-95 text-slate-200 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition">
                    <i class="fa-solid fa-paperclip"></i>
                    <span>Kamera HP</span>
                </button>

                <!-- Tombol Shutter Utama Besar -->
                <button type="button" id="btnModalShutter" onclick="snapModalPhoto()" class="w-16 h-16 rounded-full bg-white border-4 border-emerald-500 hover:border-emerald-400 active:scale-90 flex items-center justify-center shadow-xl shadow-emerald-500/30 transition shrink-0 group">
                    <span class="w-11 h-11 rounded-full bg-emerald-500 group-hover:bg-emerald-600 transition flex items-center justify-center text-white text-lg">
                        <i class="fa-solid fa-camera"></i>
                    </span>
                </button>

                <button type="button" onclick="closeModalCamera()" class="flex-1 py-2.5 px-3 bg-white/10 hover:bg-rose-500/20 active:scale-95 text-slate-300 hover:text-rose-400 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition">
                    <i class="fa-solid fa-ban"></i>
                    <span>Batal</span>
                </button>
            </div>

            <!-- Controls State 2: PREVIEW & CONFIRMATION -->
            <div id="modalControlsPreview" class="hidden flex items-center justify-between gap-2.5">
                <button type="button" onclick="retakeModalPhoto()" class="flex-1 py-3 px-3 bg-slate-800 hover:bg-slate-700 active:scale-95 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition border border-white/10">
                    <i class="fa-solid fa-camera-rotate text-amber-400"></i>
                    <span>Foto Ulang</span>
                </button>

                <button type="button" id="btnAcceptModalPhoto" onclick="acceptModalPhoto()" class="flex-1 py-3 px-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white rounded-xl font-black text-xs flex items-center justify-center gap-2 transition shadow-lg shadow-emerald-600/30">
                    <i class="fa-solid fa-check text-sm"></i>
                    <span id="txtBtnAcceptPhoto">Gunakan Foto Ini</span>
                </button>
            </div>
        </div>

        <!-- Hidden Native File Input Fallback -->
        <input type="file" id="modalNativeFileInput" accept="image/*" capture="environment" class="hidden" onchange="handleModalNativeFile(this)">
    </div>

    <!-- MODAL DETAIL / BUKTI TANDA TERIMA CETAK -->
    <div id="receiptModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
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

            <!-- Content Area (Printable: FORMAT SURAT JALAN INBOUND RETUR RESMI) -->
            <div id="printReceiptArea" class="p-6 overflow-y-auto space-y-3.5 flex-1 text-xs">
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
                        <div class="font-mono font-black text-xs sm:text-sm text-emerald-700" id="slipReceiptNo">RCV-20260929-0001</div>
                        <div class="text-[9px] text-slate-500 font-medium">Status: <span class="text-emerald-700 font-bold">VERIFIKASI SAH</span></div>
                    </div>
                </div>

                <!-- Info Grid: Tabel Detail Serah Terima Formal -->
                <div class="border border-slate-300 rounded-xl overflow-hidden shadow-2xs">
                    <table class="sj-info-table w-full text-xs">
                        <tr class="border-b border-slate-200">
                            <td class="w-1/4 bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Jasa Ekspedisi</td>
                            <td class="w-1/4 py-2 px-3 font-bold text-slate-900 text-xs" id="slipExpedition">-</td>
                            <td class="w-1/4 bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Waktu Serah Terima</td>
                            <td class="w-1/4 py-2 px-3 font-mono font-semibold text-slate-800" id="slipDateTime">-</td>
                        </tr>
                        <tr class="border-b border-slate-200">
                            <td class="bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Driver / Kurir Pengantar</td>
                            <td class="py-2 px-3 font-semibold text-slate-800" id="slipCourier">-</td>
                            <td class="bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Petugas Penerima (Gudang)</td>
                            <td class="py-2 px-3 font-bold text-slate-900" id="slipOperator">-</td>
                        </tr>
                        <tr>
                            <td class="bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Nomor Karung Terdaftar</td>
                            <td class="py-2 px-3 font-mono font-bold text-amber-800" id="slipSackNumber">-</td>
                            <td class="bg-slate-50 py-2 px-3 text-[10px] text-slate-500 font-bold uppercase">Total Karung / Bag</td>
                            <td class="py-2 px-3 font-mono font-black text-amber-900" id="slipTotalSacksCount">0 Karung</td>
                        </tr>
                    </table>
                </div>

                <!-- Foto Kurir di Slip Bukti (Jika Tersedia) -->
                <div id="slipCourierPhotoSection" class="hidden border border-slate-200 p-2 rounded-xl bg-slate-50">
                    <div class="flex items-center gap-3">
                        <img id="slipCourierPhotoImg" src="" alt="Foto Kurir" class="w-12 h-12 rounded-lg object-cover border border-slate-300 cursor-pointer shadow-xs" onclick="previewImageDirect(this.src)">
                        <div>
                            <span class="text-[9px] uppercase font-bold text-slate-400 block">Foto Kurir Ekspedisi Terverifikasi:</span>
                            <span id="slipCourierPhotoName" class="font-bold text-slate-800 text-xs">-</span>
                        </div>
                    </div>
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
                        <span id="slipTotalPackages" class="font-black text-2xl font-mono leading-none">0</span>
                        <span class="text-xs font-bold text-emerald-100 ml-1">Paket</span>
                    </div>
                </div>

                <!-- Rekap Total Paket Per Karung (Tampil di Layar & Cetak Fisik) -->
                <div id="slipSackBreakdownSection" class="border border-amber-200 bg-amber-50/70 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-bold text-amber-950 text-[11px] uppercase flex items-center gap-1.5">
                            <i class="fa-solid fa-boxes-stacked text-amber-600"></i>
                            <span>Rekapitulasi Paket Per Karung / Bag:</span>
                        </h4>
                    </div>
                    <div id="slipSackBreakdownList" class="grid grid-cols-2 sm:grid-cols-4 gap-2">
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
                    <div id="slipPackageList" class="bg-slate-50 rounded-xl p-2.5 max-h-56 overflow-y-auto grid grid-cols-2 gap-1.5 font-mono text-[11px] border border-slate-200">
                        <!-- List Resi -->
                    </div>
                </div>

                <!-- Foto Bukti Paket di Slip Bukti -->
                <div id="slipPhotoSection" class="hidden border-t border-dashed border-slate-200 pt-3">
                    <h4 class="font-bold text-slate-700 mb-1.5 text-[11px] uppercase flex items-center justify-between">
                        <span>Foto Dokumentasi Fisik Paket (<span id="slipPhotoCount">0</span>)</span>
                        <span class="text-[9px] text-slate-400 font-normal no-print">Klik foto untuk perbesar</span>
                    </h4>
                    <div id="slipPhotoContainer" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        <!-- Foto thumbnails -->
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
                            <p id="slipSignOperator" class="text-[10px] font-bold text-slate-800 border-t border-slate-400 mx-2 pt-1"><?= htmlspecialchars($user['name'] ?? 'Petugas Inbound') ?></p>
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
                <button onclick="window.print()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Cetak Bukti
                </button>
                <button onclick="startNewReceptionAfterSave()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-plus"></i> Scan Penerimaan Baru
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL LIGHTBOX PREVIEW FOTO RECEIVING -->
    <div id="receptionPhotoModal" class="hidden fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
        <div class="relative max-w-4xl w-full bg-slate-900 rounded-3xl overflow-hidden shadow-2xl border border-white/10 flex flex-col max-h-[90vh]">
            <div class="p-3.5 bg-slate-950/80 border-b border-white/10 flex items-center justify-between text-white shrink-0">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-camera text-emerald-400"></i>
                    <h3 id="receptionPhotoModalTitle" class="text-xs font-bold font-mono tracking-wide">Foto Bukti Paket Receiving</h3>
                </div>
                <button onclick="closeReceptionPhotoModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-4 flex-1 flex items-center justify-center bg-black/95 overflow-hidden">
                <img id="receptionPhotoModalImg" src="" alt="Preview Bukti Paket" class="max-h-[70vh] w-auto max-w-full object-contain rounded-xl shadow-2xl border border-white/10">
            </div>
            <div class="p-3 bg-slate-950/80 border-t border-white/10 flex justify-between items-center text-xs shrink-0">
                <span class="text-slate-400 text-[11px]">Watermark otomatis tercantum pada foto fisik.</span>
                <div class="flex gap-2">
                    <a id="btnDownloadReceptionPhoto" href="#" download="foto_paket_receiving.jpg" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition">
                        <i class="fa-solid fa-download"></i> Unduh
                    </a>
                    <button onclick="closeReceptionPhotoModal()" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-xl font-bold text-xs transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FLASH OVERLAY EFEK JEPRET KAMERA -->
    <div id="cameraFlashOverlay" class="fixed inset-0 bg-white pointer-events-none opacity-0 z-[100] transition-opacity duration-150"></div>

    <!-- AUDIO BEEP & SHUTTER SYNTHESIZER -->
    <script>
        // Helper Safe Dom Text Setter
        function setText(idOrEl, text) {
            const el = (typeof idOrEl === 'string') ? document.getElementById(idOrEl) : idOrEl;
            if (el) {
                el.innerText = (text !== null && text !== undefined) ? text : '';
            }
        }

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
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(320, audioCtx.currentTime);
                    gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.25);
                }
            } catch (e) {}
        }

        // Suara Shutter Jepret Kamera Mekanis
        function playShutterSound() {
            try {
                if (audioCtx.state === 'suspended') audioCtx.resume();
                
                const bufferSize = audioCtx.sampleRate * 0.08;
                const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < bufferSize; i++) {
                    data[i] = (Math.random() * 2 - 1) * Math.exp(-i / (bufferSize * 0.2));
                }

                const noise = audioCtx.createBufferSource();
                noise.buffer = buffer;

                const filter = audioCtx.createBiquadFilter();
                filter.type = 'bandpass';
                filter.frequency.value = 1400;

                const gain = audioCtx.createGain();
                gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);

                noise.connect(filter);
                filter.connect(gain);
                gain.connect(audioCtx.destination);

                noise.start();
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
        const CURRENT_OPERATOR_NAME = <?= json_encode($user['name'] ?? 'Operator') ?>;

        // State Penerimaan & Progressive Steps
        let currentReceiptId = '';
        let currentReceptionDbId = null; // ID header di DB setelah batch pertama tersimpan (untuk batch lanjutan / retry)
        let currentExpedition = '';
        let currentCourierName = '';
        let currentCourierPhoto = null; // DataURL Foto Kurir
        let currentSackNumber = 'Karung 1'; // Nomor Karung / Bag Aktif
        let currentScanningBarcode = ''; // Barcode yang sedang difoto
        let draftPackages = []; // Array of { id, barcode, photo, sack_number, time, timestamp }
        let targetRetakeDraftId = null;

        // State Modal Kamera Terpadu
        let cameraModalMode = 'courier'; // 'courier' | 'package'
        let cameraMediaStream = null;
        let availableVideoDevices = [];
        let currentVideoDeviceIndex = 0;
        let pendingCapturedPhoto = null;
        let cameraClockInterval = null;

        // ==============================================================
        // DRAFT PERSISTENCE ENGINE (INDEXEDDB + LOCALSTORAGE)
        // Mencegah data draft hilang jika halaman tidak sengaja di-refresh
        // ==============================================================
        const DB_NAME = 'IEG_InboundReceivingDB';
        const DB_VERSION = 1;
        const STORE_NAME = 'reception_draft_store';

        function openDraftDB() {
            return new Promise((resolve) => {
                if (!window.indexedDB) {
                    resolve(null);
                    return;
                }
                try {
                    const req = indexedDB.open(DB_NAME, DB_VERSION);
                    req.onupgradeneeded = (e) => {
                        const db = e.target.result;
                        if (!db.objectStoreNames.contains(STORE_NAME)) {
                            db.createObjectStore(STORE_NAME, { keyPath: 'key' });
                        }
                    };
                    req.onsuccess = () => resolve(req.result);
                    req.onerror = (e) => {
                        console.warn('IndexedDB open error:', e);
                        resolve(null);
                    };
                } catch (err) {
                    console.warn('IndexedDB exception:', err);
                    resolve(null);
                }
            });
        }

        async function saveDraftToStorage() {
            // Validasi: jika belum ada ekspedisi atau kurir atau paket, jangan simpan sampah kosong
            if (!currentExpedition && !currentCourierName && draftPackages.length === 0) {
                return;
            }

            const draftPayload = {
                key: 'active_session_draft',
                receiptId: currentReceiptId,
                receptionDbId: currentReceptionDbId,
                expedition: currentExpedition,
                courierName: currentCourierName,
                courierPhoto: currentCourierPhoto,
                sackNumber: currentSackNumber || 'Karung 1',
                draftPackages: draftPackages,
                savedAt: Date.now()
            };

            // 1. Simpan ke IndexedDB (Kapasitas besar, sanggup simpan puluhan foto Base64)
            try {
                const db = await openDraftDB();
                if (db) {
                    const tx = db.transaction(STORE_NAME, 'readwrite');
                    tx.objectStore(STORE_NAME).put(draftPayload);
                }
            } catch (e) {
                console.warn('Gagal menyimpan draft ke IndexedDB:', e);
            }

            // 2. Simpan metadata ke localStorage sebagai indikator cepat
            try {
                localStorage.setItem('ieg_inbound_active_draft_meta', JSON.stringify({
                    hasDraft: true,
                    receiptId: currentReceiptId,
                    expedition: currentExpedition,
                    courierName: currentCourierName,
                    sackNumber: currentSackNumber || 'Karung 1',
                    totalPackages: draftPackages.length,
                    savedAt: Date.now()
                }));
            } catch (e) {}
        }

        async function loadDraftFromStorage() {
            try {
                const db = await openDraftDB();
                if (db) {
                    return new Promise((resolve) => {
                        const tx = db.transaction(STORE_NAME, 'readonly');
                        const store = tx.objectStore(STORE_NAME);
                        const req = store.get('active_session_draft');
                        req.onsuccess = () => resolve(req.result || null);
                        req.onerror = () => resolve(null);
                    });
                }
            } catch (e) {
                console.warn('Gagal membaca draft dari IndexedDB:', e);
            }
            return null;
        }

        async function clearDraftFromStorage() {
            try {
                const db = await openDraftDB();
                if (db) {
                    const tx = db.transaction(STORE_NAME, 'readwrite');
                    tx.objectStore(STORE_NAME).delete('active_session_draft');
                }
                localStorage.removeItem('ieg_inbound_active_draft_meta');
            } catch (e) {
                console.warn('Gagal membersihkan draft storage:', e);
            }
        }

        // Otomatis pulihkan data draft jika halaman sempat di-refresh
        async function restoreDraftIfAvailable() {
            try {
                const draft = await loadDraftFromStorage();
                if (!draft || (!draft.expedition && !draft.courierName && (!draft.draftPackages || draft.draftPackages.length === 0))) {
                    // Tidak ada draft aktif, generate ID baru
                    await generateReceiptId();
                    return;
                }

                // Pulihkan data state
                currentReceiptId = draft.receiptId || '';
                currentReceptionDbId = draft.receptionDbId || null;
                currentExpedition = draft.expedition || '';
                currentCourierName = draft.courierName || '';
                currentCourierPhoto = draft.courierPhoto || null;
                currentSackNumber = draft.sackNumber || 'Karung 1';
                draftPackages = Array.isArray(draft.draftPackages) ? draft.draftPackages : [];

                if (!currentReceiptId) {
                    await generateReceiptId();
                } else {
                    const inputNo = document.getElementById('inputReceiptNo');
                    if (inputNo) inputNo.value = currentReceiptId;
                    setText('badgeReceiptDisplay', '#' + currentReceiptId);
                }

                // Pulihkan STEP 1 (Ekspedisi)
                if (currentExpedition) {
                    const selectExp = document.getElementById('selectExpedition');
                    if (selectExp) selectExp.value = currentExpedition;
                    setText('txtSelectedExpedition', currentExpedition);
                    setText('mobileExpBadge', currentExpedition);
                    hideElement('step1SelectWrapper');
                    showElement('step1Summary');
                    showElement('cardStep2');
                }

                // Pulihkan STEP 2 (Kurir & Karung)
                const inputCourier = document.getElementById('inputCourierName');
                if (inputCourier && currentCourierName) inputCourier.value = currentCourierName;
                
                const inputSack = document.getElementById('inputSackNumber');
                if (inputSack && currentSackNumber) inputSack.value = currentSackNumber;
                
                updateActiveSackDisplay(currentSackNumber);

                if (currentCourierName) {
                    setText('txtConfirmedCourier', currentCourierName);
                    setText('txtConfirmedSack', currentSackNumber);
                    hideElement('step2InputWrapper');
                    showElement('step2Summary');
                }

                // Pulihkan STEP 3 (Foto Kurir)
                if (currentCourierPhoto) {
                    const imgPreview = document.getElementById('imgCourierPhotoPreview');
                    if (imgPreview) imgPreview.src = currentCourierPhoto;
                    setText('txtCourierPhotoName', currentCourierName);
                    setText('txtCourierPhotoTime', 'Draft Tersimpan');
                    showElement('cardStep3');
                    showElement('cardStep4');
                    showElement('cardStep5');
                } else if (draftPackages.length > 0) {
                    // Draft berisi paket tapi foto kurir hilang: tetap tampilkan daftar paket (simpan akan meminta foto kurir)
                    showElement('cardStep4');
                    showElement('cardStep5');
                }

                // Pulihkan STEP 5 (Daftar Paket Draft)
                renderDraftList();

                // Notifikasi pemulihan berhasil
                const restoredCount = draftPackages.length;
                showStatusMsg(`💾 <b>Draft Berhasil Dipulihkan!</b> Sesi penerimaan ${escapeHtml(currentExpedition || 'Inbound')} (${restoredCount} paket, ${escapeHtml(currentSackNumber)}) tetap aman tersimpan setelah refresh.`, 'info');
                playBeep('success');

                // Fokuskan ke input scan resi jika langkah 4 sudah terbuka
                if (currentCourierPhoto) {
                    setTimeout(() => {
                        const inputPkg = document.getElementById('inputPackageBarcode');
                        inputPkg?.focus();
                    }, 300);
                }
            } catch (err) {
                console.error('Error saat restore draft:', err);
                await generateReceiptId();
            }
        }

        // ==============================================================
        // SACK / KARUNG MANAGEMENT (DUKUNG MULTI-KARUNG DALAM 1 ID)
        // ==============================================================
        function selectQuickSack(sackVal) {
            switchActiveSack(sackVal);

            // Highlight chip terpilih di Langkah 2
            document.querySelectorAll('.quick-sack-chip').forEach(btn => {
                if (btn.innerText.trim() === sackVal) {
                    btn.className = 'quick-sack-chip px-2.5 py-1 bg-amber-500 text-white border border-amber-600 rounded-lg text-[11px] font-bold transition shadow-2xs';
                } else {
                    btn.className = 'quick-sack-chip px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold transition shadow-2xs';
                }
            });
        }

        function switchActiveSack(sackVal) {
            const cleanVal = (sackVal || '').trim() || 'Karung 1';
            currentSackNumber = cleanVal;
            const inputSack = document.getElementById('inputSackNumber');
            if (inputSack) inputSack.value = cleanVal;

            updateActiveSackDisplay(cleanVal);
            saveDraftToStorage();
            renderStep4SackChips();

            // Refocus ke input resi agar bisa langsung tembak laser
            const inputPkg = document.getElementById('inputPackageBarcode');
            if (inputPkg && !inputPkg.disabled) {
                setTimeout(() => inputPkg.focus(), 80);
            }
        }

        function updateActiveSackDisplay(sackVal) {
            const val = sackVal || currentSackNumber || 'Karung 1';
            setText('displayActiveSack', val);
            setText('txtConfirmedSack', val);
        }

        function renderStep4SackChips(sackCounts = null) {
            const container = document.getElementById('step4SackChipsList');
            if (!container) return;

            if (!sackCounts) {
                sackCounts = {};
                draftPackages.forEach(p => {
                    const s = p.sack_number || currentSackNumber || 'Karung 1';
                    sackCounts[s] = (sackCounts[s] || 0) + 1;
                });
            }

            // Gabungkan karung default, karung yang ada di draft, dan karung aktif
            const sacksSet = new Set(['Karung 1', 'Karung 2', 'Karung 3', 'Karung 4', 'Karung 5']);
            Object.keys(sackCounts).forEach(s => { if (s) sacksSet.add(s); });
            if (currentSackNumber) sacksSet.add(currentSackNumber);

            // Urutkan secara natural
            const sortedSacks = Array.from(sacksSet).sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));

            let chipsHtml = '';
            sortedSacks.forEach(sackName => {
                const isActive = (sackName.toLowerCase() === (currentSackNumber || 'Karung 1').toLowerCase());
                const count = sackCounts[sackName] || 0;
                const countBadge = count > 0 
                    ? `<span class="ml-1 text-[9px] px-1.5 py-0.2 rounded-full ${isActive ? 'bg-amber-700 text-white' : 'bg-amber-100 text-amber-800'} font-black font-mono">${count}</span>` 
                    : '';

                if (isActive) {
                    chipsHtml += `
                        <button type="button" onclick="switchActiveSack(${jsArg(sackName)})"
                            class="px-3 py-1.5 bg-amber-500 text-white border-2 border-amber-600 rounded-xl text-xs font-black shrink-0 transition shadow-sm flex items-center gap-1 active:scale-95 cursor-pointer"
                            title="Sedang aktif: Paket selanjutnya masuk ke ${escapeHtml(sackName)}">
                            <i class="fa-solid fa-check text-[10px]"></i>
                            <span>${escapeHtml(sackName)}</span>
                            ${countBadge}
                        </button>
                    `;
                } else {
                    chipsHtml += `
                        <button type="button" onclick="switchActiveSack(${jsArg(sackName)})"
                            class="px-3 py-1.5 bg-white hover:bg-amber-50/80 text-slate-700 hover:text-amber-900 border border-slate-200 hover:border-amber-300 rounded-xl text-xs font-bold shrink-0 transition shadow-2xs flex items-center gap-1 active:scale-95 cursor-pointer"
                            title="Klik untuk pindah scan ke ${escapeHtml(sackName)}">
                            <i class="fa-solid fa-box-archive text-[10px] text-slate-400"></i>
                            <span>${escapeHtml(sackName)}</span>
                            ${countBadge}
                        </button>
                    `;
                }
            });

            container.innerHTML = chipsHtml;
        }

        function promptCustomSack() {
            const currentVal = currentSackNumber || 'Karung 1';
            const newVal = prompt('Ketik Nama / Nomor Karung Tambahan:\n(Contoh: Karung 6, Karung 7, Karung Jumbo, KR-08)', '');
            if (newVal !== null && newVal.trim() !== '') {
                const cleanVal = newVal.trim();
                switchActiveSack(cleanVal);
                showStatusMsg(`📦 Karung aktif berpindah ke <b>${escapeHtml(cleanVal)}</b>. Resi yang di-scan selanjutnya akan dicatat di ${escapeHtml(cleanVal)}.`, 'info');
                playBeep('success');
            }
        }

        function promptChangeSack() {
            promptCustomSack();
        }

        // Inisialisasi saat DOM siap
        document.addEventListener('DOMContentLoaded', async () => {
            loadHistoryData();
            await restoreDraftIfAvailable();

            // Setup input nama kurir (Tekan Enter langsung konfirmasi & buka foto)
            const inputCourier = document.getElementById('inputCourierName');
            if (inputCourier) {
                inputCourier.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        confirmCourierName();
                    }
                });
            }

            // Setup input nomor karung (Tekan Enter langsung konfirmasi)
            const inputSack = document.getElementById('inputSackNumber');
            if (inputSack) {
                inputSack.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        confirmCourierName();
                    }
                });
                inputSack.addEventListener('input', (e) => {
                    currentSackNumber = e.target.value.trim() || 'Karung 1';
                    updateActiveSackDisplay(currentSackNumber);
                    saveDraftToStorage();
                });
            }

            // Setup input barcode paket Handheld (Tekan Enter otomatis dari laser scanner -> buka kamera foto paket)
            const inputPkg = document.getElementById('inputPackageBarcode');
            if (inputPkg) {
                inputPkg.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitPackageBarcode();
                    }
                });
            }

            // Keyboard shortcut pada modal kamera (Spasi = jepret jika live, atau konfirmasi jika preview)
            window.addEventListener('keydown', (e) => {
                const modal = document.getElementById('modalCameraCapture');
                if (modal && !modal.classList.contains('hidden')) {
                    if (e.key === ' ' || e.key === 'Enter') {
                        e.preventDefault();
                        if (pendingCapturedPhoto) {
                            acceptModalPhoto();
                        } else {
                            snapModalPhoto();
                        }
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        closeModalCamera();
                    }
                }
            });
        });

        // ==============================================================
        // PROGRESSIVE STEP-BY-STEP WORKFLOW
        // ==============================================================

        // STEP 1: Pilih Ekspedisi
        function onExpeditionSelected() {
            const selectExp = document.getElementById('selectExpedition');
            const expName = selectExp ? selectExp.value.trim() : '';

            if (!expName) {
                // Sembunyikan langkah-langkah berikutnya
                hideElement('step1Summary');
                showElement('step1SelectWrapper');
                hideElement('cardStep2');
                hideElement('cardStep3');
                hideElement('cardStep4');
                hideElement('cardStep5');
                currentExpedition = '';
                saveDraftToStorage();
                return;
            }

            currentExpedition = expName;
            setText('txtSelectedExpedition', expName);
            setText('mobileExpBadge', expName);

            // Hide select dropdown, show summary ringkas
            hideElement('step1SelectWrapper');
            showElement('step1Summary');

            // Buka STEP 2: Input Nama Kurir & Karung
            showElement('cardStep2');
            showElement('step2InputWrapper');
            hideElement('step2Summary');

            const inputCourier = document.getElementById('inputCourierName');
            if (inputCourier) {
                inputCourier.value = '';
                setTimeout(() => inputCourier.focus(), 100);
            }

            saveDraftToStorage();
        }

        function changeExpedition() {
            showElement('step1SelectWrapper');
            hideElement('step1Summary');
            document.getElementById('selectExpedition')?.focus();
        }

        // STEP 2: Konfirmasi Nama Kurir & Nomor Karung -> Buka Kamera Foto Kurir
        function confirmCourierName() {
            const input = document.getElementById('inputCourierName');
            const name = (input ? input.value : '').trim();

            const inputSack = document.getElementById('inputSackNumber');
            const sack = (inputSack ? inputSack.value : '').trim() || 'Karung 1';

            if (!name) {
                alert('Silakan ketik nama driver / kurir pengantar terlebih dahulu!');
                input?.focus();
                return;
            }

            currentCourierName = name;
            currentSackNumber = sack;
            setText('txtConfirmedCourier', name);
            setText('txtConfirmedSack', sack);
            updateActiveSackDisplay(sack);

            // Sembunyikan form input kurir, tampilkan summary ringkas
            hideElement('step2InputWrapper');
            showElement('step2Summary');

            saveDraftToStorage();

            // Buka kamera untuk Ambil Foto Kurir
            openCameraModal('courier');
        }

        function editCourierName() {
            showElement('step2InputWrapper');
            hideElement('step2Summary');
            document.getElementById('inputCourierName')?.focus();
        }

        // STEP 3: Preview & Jepret Ulang Foto Kurir
        function previewCourierPhoto() {
            if (currentCourierPhoto) {
                previewImageDirect(currentCourierPhoto);
            }
        }

        function retakeCourierPhoto() {
            openCameraModal('courier');
        }

        // STEP 4: Scan Resi Barcode Handheld -> Otomatis Buka Kamera Foto Paket (Anti Double Input)
        let isCheckingBarcode = false;
        async function submitPackageBarcode() {
            if (isCheckingBarcode) return;
            if (isSubmittingReception) {
                showStatusMsg('⏳ Penerimaan sedang disimpan. Tunggu sampai selesai sebelum scan resi berikutnya.', 'warning');
                return;
            }

            const input = document.getElementById('inputPackageBarcode');
            const rawCode = (input ? input.value : '').trim();

            if (!rawCode) {
                input?.focus();
                return;
            }

            const cleanCode = rawCode.trim();

            // Barcode > 100 karakter tidak muat di database (biasanya QR yang ter-scan, bukan resi)
            if (cleanCode.length > 100) {
                playBeep('warning');
                vibrateMobile([120, 60, 120]);
                showStatusMsg(`⚠️ Barcode terlalu panjang (${cleanCode.length} karakter) — kemungkinan QR yang ter-scan, bukan resi. Scan ulang barcode resi.`, 'warning');
                if (input) input.value = '';
                input?.focus();
                return;
            }

            // 1. Cek duplikasi di sesi draft saat ini (Local Session Check)
            const isDuplicateLocal = draftPackages.some(item => item.barcode.toUpperCase() === cleanCode.toUpperCase());
            if (isDuplicateLocal) {
                playBeep('warning');
                vibrateMobile([120, 60, 120]);
                showStatusMsg(`⚠️ Resi <b>${escapeHtml(cleanCode)}</b> sudah pernah di-scan dalam sesi draft ini!`, 'warning');
                if (input) input.value = '';
                input?.focus();
                return;
            }

            // 2. Cek ke database apakah resi ini pernah diterima sebelumnya (Database Duplicate Check)
            isCheckingBarcode = true;
            try {
                const checkData = await fetchJson(`api/reception.php?action=check_barcode&barcode=${encodeURIComponent(cleanCode)}`);
                if (checkData && checkData.exists) {
                    playBeep('warning');
                    vibrateMobile([150, 80, 150]);
                    const proceed = confirm(`⚠️ PERINGATAN RESI PERNAH DIINPUT:\n\n${checkData.message}\n\nApakah Anda yakin ingin tetap memproses & memotret ulang resi ini?`);
                    if (!proceed) {
                        showStatusMsg(`⛔ Scan resi <b>${escapeHtml(cleanCode)}</b> dibatalkan karena sudah pernah diterima.`, 'warning');
                        if (input) input.value = '';
                        input?.focus();
                        isCheckingBarcode = false;
                        return;
                    }
                }
            } catch (errCheck) {
                console.warn('Gagal cek duplikasi resi ke server:', errCheck);
                playBeep('warning');
                showStatusMsg(`⚠️ Cek duplikat resi ke server gagal: ${escapeHtml(errCheck.message || String(errCheck))}. Resi tetap diproses — pastikan resi belum pernah diterima.`, 'warning');
            } finally {
                isCheckingBarcode = false;
            }

            currentScanningBarcode = cleanCode;
            if (input) input.value = '';

            // Otomatis BUKA KAMERA untuk Memotret Fisik Paket (Semua paket wajib difoto!)
            openCameraModal('package', cleanCode);
        }

        // ==============================================================
        // MODAL KAMERA TERPADU ENGINE (FOTO KURIR & PAKET)
        // ==============================================================

        async function openCameraModal(mode, barcode = null, retakeDraftId = null) {
            if (isSubmittingReception) return;
            cameraModalMode = mode;
            pendingCapturedPhoto = null;
            // Foto ulang paket draft: ID paket dikirim eksplisit agar foto MENGGANTI paket tsb (bukan menambah paket baru)
            targetRetakeDraftId = (mode === 'package') ? (retakeDraftId || null) : null;

            // Reset UI state modal
            showElement('modalControlsLive');
            hideElement('modalControlsPreview');
            hideElement('modalCameraPreviewImg');
            showElement('modalCameraVideo');
            showElement('modalCameraReticle');
            showElement('modalCameraCapture');

            const titleEl = document.getElementById('camModalTitle');
            const subtitleEl = document.getElementById('camModalSubtitle');
            const targetTagEl = document.getElementById('camModalTargetTag');
            const guideTextEl = document.getElementById('camModalGuideText');
            const badgeIconEl = document.getElementById('camModalBadgeIcon');

            if (mode === 'courier') {
                setText(titleEl, 'Foto Driver / Kurir Pengantar');
                setText(subtitleEl, `Kurir: ${currentCourierName} • Ekspedisi: ${currentExpedition}`);
                setText(targetTagEl, 'FOTO KURIR');
                setText(guideTextEl, 'Arahkan kamera ke wajah kurir pengantar');
                if (badgeIconEl) badgeIconEl.innerHTML = '<i class="fa-solid fa-user-check"></i>';
            } else {
                const bCode = barcode || currentScanningBarcode || 'PAKET';
                setText(titleEl, 'Wajib Foto Fisik Paket');
                setText(subtitleEl, `No. Resi: ${bCode}`);
                setText(targetTagEl, 'BUKTI PAKET');
                setText(guideTextEl, 'Arahkan kamera ke label resi & fisik paket');
                if (badgeIconEl) badgeIconEl.innerHTML = '<i class="fa-solid fa-box-archive"></i>';
            }

            startCameraClock();
            await startModalCameraStream();
        }

        let cameraStreamToken = 0; // naik setiap stream dimulai/dihentikan -> stream yang telat datang setelah modal ditutup langsung dimatikan

        function isCameraModalOpen() {
            const modal = document.getElementById('modalCameraCapture');
            return !!(modal && !modal.classList.contains('hidden'));
        }

        async function startModalCameraStream() {
            const videoEl = document.getElementById('modalCameraVideo');
            const loadingEl = document.getElementById('modalCameraLoading');
            const switchBtn = document.getElementById('btnModalSwitchCam');
            if (loadingEl) {
                // Kembalikan tampilan "Menghubungkan kamera..." jika sebelumnya menampilkan pesan error
                if (loadingEl.dataset.origHtml === undefined) loadingEl.dataset.origHtml = loadingEl.innerHTML;
                else loadingEl.innerHTML = loadingEl.dataset.origHtml;
            }
            showElement(loadingEl);
            const myToken = ++cameraStreamToken;

            try {
                if (cameraMediaStream) {
                    cameraMediaStream.getTracks().forEach(t => t.stop());
                    cameraMediaStream = null;
                }
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Browser tidak mengizinkan akses kamera langsung (halaman harus dibuka via HTTPS / localhost).');
                }

                // Deteksi ketersediaan kamera
                if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    availableVideoDevices = devices.filter(d => d.kind === 'videoinput');
                    if (availableVideoDevices.length > 1 && switchBtn) {
                        showElement(switchBtn);
                    } else if (switchBtn) {
                        hideElement(switchBtn);
                    }
                }

                const constraints = {
                    video: {
                        facingMode: { ideal: "environment" },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };

                const newStream = await navigator.mediaDevices.getUserMedia(constraints);
                // Modal sudah ditutup / stream lain sudah dimulai selama menunggu izin kamera -> matikan stream ini
                if (myToken !== cameraStreamToken || !isCameraModalOpen()) {
                    newStream.getTracks().forEach(t => t.stop());
                    return;
                }
                cameraMediaStream = newStream;
                if (videoEl) {
                    videoEl.srcObject = cameraMediaStream;
                    videoEl.onloadedmetadata = () => {
                        const playPromise = videoEl.play();
                        if (playPromise && playPromise.catch) playPromise.catch(() => {});
                        hideElement(loadingEl);
                    };
                }
            } catch (err) {
                console.warn('Gagal mengakses kamera langsung:', err);
                if (myToken !== cameraStreamToken) return;
                // Tampilkan pesan error DI DALAM modal (status bar Langkah 4 tertutup modal)
                if (loadingEl) {
                    loadingEl.innerHTML = `
                        <i class="fa-solid fa-video-slash text-2xl text-rose-400"></i>
                        <span class="text-xs font-bold text-rose-300 text-center px-6">Kamera langsung tidak dapat dibuka</span>
                        <span class="text-[11px] text-slate-400 text-center px-6">${escapeHtml(err && err.message ? err.message : String(err))}</span>
                        <span class="text-[11px] text-slate-300 text-center px-6">Gunakan tombol <b>"Kamera HP"</b> di bawah untuk memotret.</span>
                    `;
                    showElement(loadingEl);
                }
                showStatusMsg('⚠️ Tidak dapat membuka stream kamera langsung. Anda dapat menggunakan tombol "Kamera HP".', 'warning');
            }
        }

        function stopModalCameraStream() {
            cameraStreamToken++;
            if (cameraMediaStream) {
                cameraMediaStream.getTracks().forEach(t => t.stop());
                cameraMediaStream = null;
            }
            const videoEl = document.getElementById('modalCameraVideo');
            if (videoEl) videoEl.srcObject = null;
            stopCameraClock();
        }

        function closeModalCamera() {
            stopModalCameraStream();
            hideElement('modalCameraCapture');

            if (cameraModalMode === 'package') {
                const pkgInput = document.getElementById('inputPackageBarcode');
                pkgInput?.focus();
            } else if (cameraModalMode === 'courier' && !currentCourierPhoto) {
                // Jika belum foto kurir, kembali fokus ke tombol foto kurir
                document.getElementById('btnConfirmCourier')?.focus();
            }
        }

        async function switchModalCamera() {
            if (availableVideoDevices.length <= 1) return;
            currentVideoDeviceIndex = (currentVideoDeviceIndex + 1) % availableVideoDevices.length;
            const targetDevice = availableVideoDevices[currentVideoDeviceIndex];

            if (cameraMediaStream) {
                cameraMediaStream.getTracks().forEach(t => t.stop());
                cameraMediaStream = null;
            }

            const videoEl = document.getElementById('modalCameraVideo');
            const myToken = ++cameraStreamToken;
            try {
                const newStream = await navigator.mediaDevices.getUserMedia({
                    video: { deviceId: { exact: targetDevice.deviceId } },
                    audio: false
                });
                if (myToken !== cameraStreamToken || !isCameraModalOpen()) {
                    newStream.getTracks().forEach(t => t.stop());
                    return;
                }
                cameraMediaStream = newStream;
                if (videoEl) {
                    videoEl.srcObject = cameraMediaStream;
                    videoEl.play();
                }
            } catch (e) {
                console.error('Gagal ganti kamera:', e);
            }
        }

        function startCameraClock() {
            stopCameraClock();
            const updateClock = () => {
                const clockEl = document.getElementById('camModalLiveClock');
                if (clockEl) {
                    const now = new Date();
                    clockEl.innerText = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
                }
            };
            updateClock();
            cameraClockInterval = setInterval(updateClock, 1000);
        }

        function stopCameraClock() {
            if (cameraClockInterval) {
                clearInterval(cameraClockInterval);
                cameraClockInterval = null;
            }
        }

        // Jepret Foto dari Video Stream
        function snapModalPhoto() {
            const videoEl = document.getElementById('modalCameraVideo');
            if (!videoEl || !videoEl.videoWidth) {
                alert('Kamera belum siap atau tidak aktif. Silakan gunakan tombol "Kamera HP" di bawah.');
                return;
            }

            playShutterSound();
            triggerFlashEffect();
            vibrateMobile(70);

            const bCode = (cameraModalMode === 'package') ? currentScanningBarcode : null;
            pendingCapturedPhoto = generateWatermarkedPhoto(videoEl, cameraModalMode, bCode);

            // Tampilkan Preview Foto
            const previewImg = document.getElementById('modalCameraPreviewImg');
            if (previewImg) {
                previewImg.src = pendingCapturedPhoto;
                showElement(previewImg);
            }
            hideElement(videoEl);
            hideElement('modalCameraReticle');

            // Beralih ke tombol konfirmasi
            hideElement('modalControlsLive');
            showElement('modalControlsPreview');
        }

        // Ambil Ulang Foto
        function retakeModalPhoto() {
            pendingCapturedPhoto = null;
            hideElement('modalCameraPreviewImg');
            showElement('modalCameraVideo');
            showElement('modalCameraReticle');
            if (!cameraMediaStream) showElement('modalCameraLoading'); // tampilkan lagi status kamera (memuat / error)
            showElement('modalControlsLive');
            hideElement('modalControlsPreview');
        }

        // Konfirmasi & Gunakan Foto
        function acceptModalPhoto() {
            if (!pendingCapturedPhoto) return;

            if (cameraModalMode === 'courier') {
                // Simpan Foto Kurir
                currentCourierPhoto = pendingCapturedPhoto;
                pendingCapturedPhoto = null;
                saveDraftToStorage(); // Foto kurir baru / foto ulang langsung masuk draft (aman jika halaman ter-refresh)
                stopModalCameraStream();
                hideElement('modalCameraCapture');

                // Update Tampilan STEP 3: Foto Kurir Terverifikasi
                const imgPreview = document.getElementById('imgCourierPhotoPreview');
                if (imgPreview) imgPreview.src = currentCourierPhoto;
                setText('txtCourierPhotoName', currentCourierName);
                setText('txtCourierPhotoTime', new Date().toLocaleTimeString('id-ID') + ' WIB');
                showElement('cardStep3');

                // Buka STEP 4 & STEP 5
                showElement('cardStep4');
                showElement('cardStep5');

                playBeep('success');
                vibrateMobile(80);
                showStatusMsg(`✅ <b>Foto Kurir ${escapeHtml(currentCourierName)} Terverifikasi!</b> Silakan mulai scan resi paket.`, 'success');

                // Otomatis fokus ke input resi siap tembak laser handheld!
                const inputPkg = document.getElementById('inputPackageBarcode');
                if (inputPkg) {
                    inputPkg.value = '';
                    setTimeout(() => inputPkg.focus(), 150);
                }

            } else if (cameraModalMode === 'package') {
                // Simpan Foto Paket
                // Foto ulang: paket target dari tombol "Foto Ulang", atau paket dengan barcode yang sama di draft (anti dobel)
                const retakeItem = targetRetakeDraftId
                    ? draftPackages.find(p => p.id === targetRetakeDraftId)
                    : draftPackages.find(p => String(p.barcode).toUpperCase() === String(currentScanningBarcode).toUpperCase());
                if (retakeItem) {
                    // Update paket tertentu
                    retakeItem.photo = pendingCapturedPhoto;
                    showStatusMsg(`📸 Foto untuk resi <b>${escapeHtml(retakeItem.barcode)}</b> berhasil diperbarui!`, 'success');
                    targetRetakeDraftId = null;
                } else {
                    targetRetakeDraftId = null;
                    // Tambah paket baru ke Draft
                    const now = new Date();
                    const newDraftItem = {
                        id: 'draft_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
                        barcode: currentScanningBarcode,
                        sack_number: currentSackNumber || 'Karung 1',
                        photo: pendingCapturedPhoto,
                        time: now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB',
                        timestamp: Date.now()
                    };

                    draftPackages.unshift(newDraftItem);
                    showStatusMsg(`📦 Resi <b>${escapeHtml(currentScanningBarcode)}</b> [${escapeHtml(currentSackNumber)}] berhasil difoto & masuk ke <b>DRAFT</b>!`, 'success');
                }
                pendingCapturedPhoto = null;

                saveDraftToStorage();

                stopModalCameraStream();
                hideElement('modalCameraCapture');
                playBeep('success');
                vibrateMobile(60);

                renderDraftList();

                // Fokus kembali ke input barcode paket
                const inputPkg = document.getElementById('inputPackageBarcode');
                if (inputPkg) {
                    inputPkg.value = '';
                    setTimeout(() => inputPkg.focus(), 100);
                }
            }
        }

        // Fallback Native File Capture
        function triggerModalNativeCamera() {
            const fileInput = document.getElementById('modalNativeFileInput');
            if (fileInput) {
                fileInput.value = '';
                fileInput.click();
            }
        }

        function handleModalNativeFile(inputEl) {
            if (!inputEl.files || !inputEl.files[0]) return;
            const file = inputEl.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const bCode = (cameraModalMode === 'package') ? currentScanningBarcode : null;
                    pendingCapturedPhoto = generateWatermarkedPhoto(img, cameraModalMode, bCode);

                    playShutterSound();
                    triggerFlashEffect();

                    const previewImg = document.getElementById('modalCameraPreviewImg');
                    if (previewImg) {
                        previewImg.src = pendingCapturedPhoto;
                        showElement(previewImg);
                    }
                    hideElement('modalCameraVideo');
                    hideElement('modalCameraReticle');
                    hideElement('modalCameraLoading'); // overlay loading/error tidak boleh menutupi preview
                    hideElement('modalControlsLive');
                    showElement('modalControlsPreview');
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        // ==============================================================
        // GENERATE WATERMARK CANVAS BERSTANDAR TINGGI
        // ==============================================================

        function generateWatermarkedPhoto(sourceEl, mode, barcode = null) {
            const canvas = document.createElement('canvas');
            let origW, origH;

            if (sourceEl instanceof HTMLVideoElement) {
                origW = sourceEl.videoWidth || 1280;
                origH = sourceEl.videoHeight || 720;
            } else if (sourceEl instanceof HTMLImageElement) {
                origW = sourceEl.naturalWidth || sourceEl.width || 1280;
                origH = sourceEl.naturalHeight || sourceEl.height || 720;
            } else {
                origW = 1280;
                origH = 720;
            }

            // Skala proporsional agar memori browser & payload transmisi ringan (Maksimal 800x600, hemat kuota & anti-error 413)
            const MAX_WIDTH = 800;
            const MAX_HEIGHT = 600;
            let w = origW;
            let h = origH;
            if (w > MAX_WIDTH || h > MAX_HEIGHT) {
                const ratio = Math.min(MAX_WIDTH / w, MAX_HEIGHT / h);
                w = Math.round(w * ratio);
                h = Math.round(h * ratio);
            }

            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'medium';

            // Render gambar dasar
            ctx.drawImage(sourceEl, 0, 0, w, h);

            // Banner Watermark Bawah (Gradient Slate Gelap Profesional)
            const barHeight = Math.max(80, Math.round(h * 0.17));
            const grad = ctx.createLinearGradient(0, h - barHeight, 0, h);
            grad.addColorStop(0, 'rgba(15, 23, 42, 0.92)');
            grad.addColorStop(1, 'rgba(2, 6, 23, 0.98)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, h - barHeight, w, barHeight);

            // Garis Aksen Atas Watermark
            ctx.fillStyle = (mode === 'courier') ? '#6366f1' : '#10b981';
            ctx.fillRect(0, h - barHeight, w, Math.max(3, Math.round(h * 0.006)));

            // Tanggal & Waktu
            const now = new Date();
            const dateStr = now.toLocaleDateString('id-ID', { year: 'numeric', month: '2-digit', day: '2-digit' });
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';

            const receiptNo = currentReceiptId || 'RCV-PENDING';
            const exp = currentExpedition || 'UMUM';
            const courier = currentCourierName || '-';
            const sack = currentSackNumber || 'Karung 1';

            // Font sizing
            const titleSize = Math.max(13, Math.round(w * 0.020));
            const bodySize  = Math.max(11, Math.round(w * 0.015));
            const subSize   = Math.max(9, Math.round(w * 0.012));

            ctx.textBaseline = 'top';

            if (mode === 'courier') {
                // SISI KIRI: Branding & Kurir
                ctx.textAlign = 'left';
                ctx.fillStyle = '#818cf8';
                ctx.font = `900 ${titleSize}px monospace, sans-serif`;
                ctx.fillText('IEG • FOTO BUKTI KURIR PENGANTAR', 14, h - barHeight + 8);

                ctx.fillStyle = '#ffffff';
                ctx.font = `bold ${bodySize}px monospace, sans-serif`;
                ctx.fillText(`NAMA KURIR: ${courier}  |  EKSPEDISI: ${exp}`, 14, h - barHeight + 8 + titleSize + 4);

                ctx.fillStyle = '#94a3b8';
                ctx.font = `normal ${subSize}px monospace, sans-serif`;
                ctx.fillText(`NO. TERIMA: ${receiptNo}  |  OPERATOR: ${CURRENT_OPERATOR_NAME}`, 14, h - barHeight + 8 + titleSize + bodySize + 6);

                // SISI KANAN: Waktu & Badge
                ctx.textAlign = 'right';
                ctx.fillStyle = '#38bdf8';
                ctx.font = `bold ${bodySize}px monospace, sans-serif`;
                ctx.fillText(`${dateStr} ${timeStr}`, w - 14, h - barHeight + 8);

                ctx.fillStyle = '#fbbf24';
                ctx.font = `bold ${subSize}px monospace, sans-serif`;
                ctx.fillText('👤 BUKTI FISIK SERAH TERIMA DRIVER', w - 14, h - barHeight + 10 + bodySize);
            } else {
                // SISI KIRI: Branding & Paket Resi
                ctx.textAlign = 'left';
                ctx.fillStyle = '#10b981';
                ctx.font = `900 ${titleSize}px monospace, sans-serif`;
                ctx.fillText('IEG • INBOUND RECEIVING', 14, h - barHeight + 8);

                ctx.fillStyle = '#ffffff';
                ctx.font = `bold ${bodySize}px monospace, sans-serif`;
                ctx.fillText(`RESI: ${barcode || '-'}  |  KARUNG: ${sack}`, 14, h - barHeight + 8 + titleSize + 4);

                ctx.fillStyle = '#94a3b8';
                ctx.font = `normal ${subSize}px monospace, sans-serif`;
                ctx.fillText(`EKSPEDISI: ${exp}  |  KURIR: ${courier}  |  OPERATOR: ${CURRENT_OPERATOR_NAME}`, 14, h - barHeight + 8 + titleSize + bodySize + 6);

                // SISI KANAN: Waktu & Status
                ctx.textAlign = 'right';
                ctx.fillStyle = '#38bdf8';
                ctx.font = `bold ${bodySize}px monospace, sans-serif`;
                ctx.fillText(`${dateStr} ${timeStr}`, w - 14, h - barHeight + 8);

                ctx.fillStyle = '#fbbf24';
                ctx.font = `bold ${subSize}px monospace, sans-serif`;
                ctx.fillText('📸 BUKTI SERAH TERIMA FISIK PAKET', w - 14, h - barHeight + 10 + bodySize);
            }

            ctx.textAlign = 'left';
            return canvas.toDataURL('image/jpeg', 0.58);
        }

        // Kompres DataURL ke ukuran sangat ringan (maks 800x600, kualitas 0.55 = ~20-25 KB per foto)
        function compressDataUrl(dataUrl, maxW = 800, maxH = 600, quality = 0.55) {
            return new Promise((resolve) => {
                if (!dataUrl || typeof dataUrl !== 'string') { resolve(dataUrl); return; }
                if (!dataUrl.startsWith('data:image')) { resolve(dataUrl); return; }
                if (dataUrl.length < 55000) { resolve(dataUrl); return; }
                const img = new Image();
                img.onload = () => {
                    let w = img.naturalWidth || 800, h = img.naturalHeight || 600;
                    const ratio = Math.min(1, maxW / w, maxH / h);
                    w = Math.max(320, Math.round(w * ratio));
                    h = Math.max(240, Math.round(h * ratio));
                    const c = document.createElement('canvas');
                    c.width = w; c.height = h;
                    const cx = c.getContext('2d');
                    cx.imageSmoothingQuality = 'medium';
                    cx.drawImage(img, 0, 0, w, h);
                    resolve(c.toDataURL('image/jpeg', quality));
                };
                img.onerror = () => resolve(dataUrl);
                img.src = dataUrl;
            });
        }

        // Unggah 1 foto ke server, kembalikan path file (uploads/...).
        async function uploadReceptionPhoto(dataUrl, prefix) {
            if (!dataUrl || typeof dataUrl !== 'string') return null;
            if (!dataUrl.startsWith('data:image')) return dataUrl; // Sudah berupa path server

            let compact = await compressDataUrl(dataUrl, 800, 600, 0.55);
            let lastErr = null;
            for (let attempt = 1; attempt <= 3; attempt++) {
                try {
                    if (attempt > 1) {
                        compact = await compressDataUrl(compact, 640, 480, 0.45);
                    }
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 12000);
                    const uploadEndpoint = (window.APP_BASE_URL || '').replace(/\/+$/, '') + '/api/reception.php?action=upload_photo';
                    const fd = new FormData();
                    fd.append('photo', compact);
                    fd.append('prefix', prefix);
                    const res = await fetch(uploadEndpoint, {
                        method: 'POST',
                        body: fd,
                        signal: controller.signal
                    });
                    clearTimeout(timeoutId);
                    const txt = await res.text();
                    let d = null;
                    try { d = JSON.parse(txt); } catch (e) { d = null; }
                    if (res.status === 401) {
                        throw new Error('Sesi login telah habis. Buka tab baru untuk login kembali.');
                    }
                    if (d && d.success && d.path) {
                        return d.path;
                    }
                    if (d && d.error) {
                        lastErr = new Error(d.error);
                    }
                } catch (e) {
                    lastErr = e;
                    if (e.message && e.message.includes('Sesi login')) {
                        throw e;
                    }
                }
            }
            console.warn('Upload foto server dilewati, foto dikirim langsung bersama data penerimaan:', lastErr);
            // JANGAN pernah mengembalikan null (foto paket/kurir akan hilang diam-diam).
            // Kirim versi kecil sebagai base64 -> server tetap menyimpannya saat submit data penerimaan.
            const thumb = await compressDataUrl(compact, 480, 360, 0.40);
            return thumb || compact || dataUrl;
        }

        // ==============================================================
        // DRAFT MANAGEMENT & LIST RENDERER
        // ==============================================================

        function renderDraftList() {
            const count = draftPackages.length;
            setText('draftCountBadgeTop', count);
            setText('btnSubmitCount', count);
            setText('mobileDraftCount', count);
            setText('mobileBtnCount', count);

            const emptyBox = document.getElementById('draftListEmpty');
            const container = document.getElementById('draftPackagesContainer');

            // Hitung distribusi paket per karung dalam 1 ID serah terima
            const sackCounts = {};
            draftPackages.forEach(p => {
                const s = p.sack_number || currentSackNumber || 'Karung 1';
                sackCounts[s] = (sackCounts[s] || 0) + 1;
            });

            // Update bar ringkasan karung di Step 5
            const summaryBar = document.getElementById('draftSacksSummaryBar');
            const summaryChips = document.getElementById('draftSacksSummaryChips');
            if (summaryBar && summaryChips) {
                const sackEntries = Object.entries(sackCounts);
                if (sackEntries.length > 0) {
                    showElement(summaryBar);
                    summaryChips.innerHTML = sackEntries.map(([sName, sQty]) => `
                        <button type="button" onclick="switchActiveSack(${jsArg(sName)})" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white hover:bg-amber-100 border border-amber-300 text-amber-950 font-bold text-[11px] shadow-2xs font-mono transition cursor-pointer"
                            title="Klik untuk jadikan karung target scan">
                            <i class="fa-solid fa-box-archive text-amber-600 text-[10px]"></i>
                            <span>${escapeHtml(sName)}</span>
                            <span class="bg-amber-100 text-amber-800 text-[10px] px-1.5 py-0.2 rounded-full font-black">${sQty} paket</span>
                        </button>
                    `).join('');
                } else {
                    hideElement(summaryBar);
                    summaryChips.innerHTML = '';
                }
            }

            // Update bar quick switch karung di Langkah 4
            renderStep4SackChips(sackCounts);

            if (count === 0) {
                showElement(emptyBox);
                if (container) {
                    hideElement(container);
                    container.innerHTML = '';
                }
                return;
            }

            hideElement(emptyBox);
            if (container) {
                showElement(container);
                let html = '';
                draftPackages.forEach((pkg, idx) => {
                    const seq = count - idx;
                    const hasPhoto = !!pkg.photo;
                    const pkgSack = pkg.sack_number || currentSackNumber || 'Karung 1';

                    const thumbHtml = hasPhoto
                        ? `<div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-xl overflow-hidden bg-black shrink-0 border border-slate-200 cursor-pointer shadow-xs group" onclick="previewDraftPhoto('${pkg.id}')" title="Klik untuk lihat foto">
                               <img src="${pkg.photo}" alt="Foto ${escapeHtml(pkg.barcode)}" class="w-full h-full object-cover group-hover:scale-105 transition">
                               <span class="absolute bottom-0.5 right-0.5 bg-black/70 text-[9px] text-white font-mono px-1 rounded font-bold">#${seq}</span>
                           </div>`
                        : `<div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl bg-amber-50 border border-amber-200 flex flex-col items-center justify-center text-amber-600 shrink-0 cursor-pointer hover:bg-amber-100 transition" onclick="retakeDraftPhoto('${pkg.id}')" title="Klik untuk ambil foto">
                               <i class="fa-solid fa-camera text-base mb-0.5"></i>
                               <span class="text-[8px] font-bold">Wajib Foto</span>
                           </div>`;

                    const badgePhoto = hasPhoto
                        ? `<span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-md border border-emerald-200"><i class="fa-solid fa-check text-[9px]"></i> Foto OK</span>`
                        : `<span class="inline-flex items-center gap-1 bg-rose-50 text-rose-700 text-[10px] font-bold px-2 py-0.5 rounded-md border border-rose-200 animate-pulse"><i class="fa-solid fa-triangle-exclamation text-[9px]"></i> Belum Difoto</span>`;

                    const badgeSack = `<span class="inline-flex items-center gap-1 bg-amber-50 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-md border border-amber-200 font-mono"><i class="fa-solid fa-box-archive text-[9px]"></i> ${escapeHtml(pkgSack)}</span>`;

                    html += `
                        <div class="flex items-center justify-between p-2.5 sm:p-3 bg-slate-50 hover:bg-slate-100/90 rounded-2xl border border-slate-200/90 transition shadow-xs gap-2.5 sm:gap-3">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                ${thumbHtml}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono font-black text-slate-900 text-xs sm:text-sm tracking-tight break-all">${escapeHtml(pkg.barcode)}</span>
                                        ${badgeSack}
                                        ${badgePhoto}
                                    </div>
                                    <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-1">
                                        <span class="font-bold text-slate-500">Draft #${seq}</span>
                                        <span>•</span>
                                        <span>${pkg.time}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol Aksi Draft -->
                            <div class="flex items-center gap-1 shrink-0">
                                <button onclick="retakeDraftPhoto('${pkg.id}')" type="button" title="Foto Ulang Paket" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 flex items-center justify-center text-xs transition">
                                    <i class="fa-solid fa-camera-rotate"></i>
                                </button>
                                <button onclick="removeDraftPackage('${pkg.id}')" type="button" title="Hapus dari Draft" class="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center text-xs transition">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }
        }

        function retakeDraftPhoto(draftId) {
            if (isSubmittingReception) return;
            const item = draftPackages.find(p => p.id === draftId);
            if (!item) return;
            currentScanningBarcode = item.barcode;
            openCameraModal('package', item.barcode, draftId);
        }

        function removeDraftPackage(draftId) {
            if (isSubmittingReception) return;
            const idx = draftPackages.findIndex(p => p.id === draftId);
            if (idx !== -1) {
                const removed = draftPackages.splice(idx, 1);
                showStatusMsg(`Resi ${escapeHtml(removed[0].barcode)} dihapus dari draft.`, 'info');
                saveDraftToStorage();
                renderDraftList();
            }
        }

        function clearAllDrafts() {
            if (isSubmittingReception) return;
            if (draftPackages.length === 0) return;
            if (confirm(`Hapus semua ${draftPackages.length} paket dari draft sesi ini?`)) {
                draftPackages = [];
                saveDraftToStorage();
                renderDraftList();
                showStatusMsg('Semua paket dalam draft telah dibersihkan.', 'info');
                document.getElementById('inputPackageBarcode')?.focus();
            }
        }

        function previewDraftPhoto(draftId) {
            const item = draftPackages.find(p => p.id === draftId);
            if (!item || !item.photo) return;
            previewImageDirect(item.photo);
        }

        // ==============================================================
        // SUBMIT BATCH PENERIMAAN DRAFT KE DATABASE (ANTI DOUBLE INPUT)
        // ==============================================================
        let isSubmittingReception = false;

        async function submitCompleteReception() {
            if (isSubmittingReception) {
                console.warn('Pengiriman data penerimaan sedang berlangsung...');
                return;
            }

            if (!currentExpedition) {
                alert('Silakan pilih Ekspedisi terlebih dahulu!');
                document.getElementById('selectExpedition')?.focus();
                return;
            }

            if (!currentCourierName) {
                alert('Silakan masukkan nama driver / kurir pengantar terlebih dahulu!');
                document.getElementById('inputCourierName')?.focus();
                return;
            }

            if (!currentCourierPhoto) {
                alert('Foto kurir wajib diambil sebelum menyelesaikan penerimaan!');
                openCameraModal('courier');
                return;
            }

            if (draftPackages.length === 0) {
                alert('Minimal 1 resi paket harus di-scan dan difoto sebelum menyelesaikan penerimaan!');
                document.getElementById('inputPackageBarcode')?.focus();
                return;
            }

            // Validasi: SEMUA PAKET WAJIB DIFOTO
            const unphotographed = draftPackages.filter(p => !p.photo);
            if (unphotographed.length > 0) {
                alert(`Perhatian: Ada ${unphotographed.length} paket yang belum memiliki foto!\nSemua paket wajib difoto sebelum disimpan ke sistem.`);
                retakeDraftPhoto(unphotographed[0].id);
                return;
            }

            if (!confirm(`Selesaikan & simpan penerimaan ${draftPackages.length} paket untuk Ekspedisi ${currentExpedition} (${currentSackNumber})?`)) {
                return;
            }

            // Lock agar tidak bisa dipencet 2x (Anti Double Submit)
            isSubmittingReception = true;
            // Reset ID header penerimaan agar submit baru tidak menempel ke header basi
            currentReceptionDbId = null;

            // Snapshot paket yang dikirim: perubahan draft selama proses simpan tidak boleh hilang / terkirim ganda
            const submitPackages = draftPackages.slice();
            const submittedIds = new Set(submitPackages.map(p => p.id));

            // Kunci input scan resi selama proses simpan berlangsung
            const pkgInputLock = document.getElementById('inputPackageBarcode');
            if (pkgInputLock) pkgInputLock.disabled = true;

            const btnDesktop = document.getElementById('btnSubmitReception');
            const origDesktopHtml = btnDesktop ? btnDesktop.innerHTML : '';
            if (btnDesktop) {
                btnDesktop.disabled = true;
                btnDesktop.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan Paket & Foto ke Server...';
                btnDesktop.classList.add('opacity-70', 'pointer-events-none');
            }

            const btnMobile = document.querySelector('#mobileStickyBar button');
            const origMobileHtml = btnMobile ? btnMobile.innerHTML : '';
            if (btnMobile) {
                btnMobile.disabled = true;
                btnMobile.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
                btnMobile.classList.add('opacity-70', 'pointer-events-none');
            }

            // Kirim 1 request simpan & parse respons (aman dari respons HTML / error PHP)
            const postReception = async (payload, batchLabel) => {
                const apiEndpoint = (window.APP_BASE_URL || '').replace(/\/+$/, '') + '/api/reception.php';
                const fd = new FormData();
                fd.append('data', JSON.stringify(payload));
                const res = await fetch(apiEndpoint, {
                    method: 'POST',
                    body: fd
                });
                const responseText = await res.text();
                let result = null;
                try {
                    result = JSON.parse(responseText);
                } catch (eParse) {
                    if (res.status === 413) {
                        throw new Error('Ukuran foto melebihi batas upload server (Error 413). Coba bagi paket ke beberapa karung.');
                    } else if (res.status === 401) {
                        throw new Error('Sesi login telah habis. Buka tab baru untuk login kembali.');
                    }
                    throw new Error(`Server error${batchLabel} (${res.status}): ` + responseText.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 150));
                }
                if (res.status === 409 && currentReceptionDbId) {
                    // Header batch sebelumnya tidak ditemukan lagi (mis. dihapus admin) -> mulai header baru pada percobaan berikutnya
                    currentReceptionDbId = null;
                    await saveDraftToStorage();
                }
                if (!result || !result.success) {
                    throw new Error((result && result.error) || `Gagal menyimpan data penerimaan${batchLabel}.`);
                }
                // Simpan ID header & nomor final dari server agar batch berikutnya / retry menempel ke header yang sama
                if (result.reception_id) {
                    currentReceptionDbId = result.reception_id;
                    if (result.receipt_number) {
                        currentReceiptId = result.receipt_number;
                        const inputNo = document.getElementById('inputReceiptNo');
                        if (inputNo) inputNo.value = currentReceiptId;
                        setText('badgeReceiptDisplay', '#' + currentReceiptId);
                    }
                    await saveDraftToStorage();
                }
                return result;
            };

            try {
                const setProgress = (txt) => {
                    if (btnDesktop) btnDesktop.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${txt}`;
                    if (btnMobile) btnMobile.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${txt}`;
                };
                const safeRcpt = String(currentReceiptId || 'RCV').replace(/[^a-zA-Z0-9_\-]/g, '_');

                // 1. Unggah foto kurir jika masih berupa dataUrl
                if (currentCourierPhoto && currentCourierPhoto.startsWith('data:image')) {
                    setProgress('Mengunggah foto kurir...');
                    currentCourierPhoto = (await uploadReceptionPhoto(currentCourierPhoto, `courier_${safeRcpt}`)) || currentCourierPhoto;
                    await saveDraftToStorage();
                }

                // 2. Unggah foto paket dengan concurrent worker pool (3 koneksi simultan)
                const itemsToUpload = submitPackages.filter(p => p.photo && p.photo.startsWith('data:image'));
                const totalUploadCount = itemsToUpload.length;
                let uploadedCount = 0;

                if (totalUploadCount > 0) {
                    const CONCURRENCY = 3;
                    const queue = [...itemsToUpload];
                    const workers = Array(Math.min(CONCURRENCY, queue.length)).fill(0).map(async () => {
                        while (queue.length > 0) {
                            const p = queue.shift();
                            if (!p) break;
                            const safeB = String(p.barcode).replace(/[^a-zA-Z0-9_\-]/g, '_');
                            p.photo = (await uploadReceptionPhoto(p.photo, `pkg_${safeRcpt}_${safeB}`)) || p.photo;
                            uploadedCount++;
                            setProgress(`Mengunggah foto (${uploadedCount}/${totalUploadCount})...`);
                        }
                    });
                    await Promise.all(workers);
                    await saveDraftToStorage();
                }

                // 3. Simpan data penerimaan ke database (menggunakan chunking jika paket > 25)
                const CHUNK_SIZE = 25;
                const totalPkgCount = submitPackages.length;
                let lastResponseData = null;

                if (totalPkgCount > CHUNK_SIZE) {
                    for (let i = 0; i < totalPkgCount; i += CHUNK_SIZE) {
                        const slice = submitPackages.slice(i, i + CHUNK_SIZE);
                        const chunkIdx = Math.floor(i / CHUNK_SIZE);
                        const endNum = Math.min(i + CHUNK_SIZE, totalPkgCount);
                        setProgress(`Menyimpan paket ${i + 1}-${endNum} dari ${totalPkgCount}...`);

                        const chunkPayload = {
                            receipt_number: currentReceiptId,
                            reception_id: currentReceptionDbId || undefined,
                            expedition: currentExpedition,
                            courier_name: currentCourierName,
                            courier_photo: currentCourierPhoto,
                            sack_number: currentSackNumber || 'Karung 1',
                            is_chunk: true,
                            chunk_index: chunkIdx,
                            total_packages: totalPkgCount,
                            packages: slice.map(p => ({
                                barcode: p.barcode,
                                photo: p.photo,
                                sack_number: p.sack_number || currentSackNumber || 'Karung 1'
                            }))
                        };

                        lastResponseData = await postReception(chunkPayload, ` saat batch #${chunkIdx + 1}`);
                    }
                } else {
                    // Single request jika <= 25 paket
                    setProgress('Menyimpan data penerimaan...');
                    const payload = {
                        receipt_number: currentReceiptId,
                        reception_id: currentReceptionDbId || undefined,
                        expedition: currentExpedition,
                        courier_name: currentCourierName,
                        courier_photo: currentCourierPhoto,
                        sack_number: currentSackNumber || 'Karung 1',
                        packages: submitPackages.map(p => ({
                            barcode: p.barcode,
                            photo: p.photo,
                            sack_number: p.sack_number || currentSackNumber || 'Karung 1'
                        }))
                    };

                    lastResponseData = await postReception(payload, '');
                }

                if (lastResponseData && lastResponseData.success) {
                    showStatusMsg(`✅ <b>Sukses Tersimpan!</b> Penerimaan <b>${lastResponseData.total_packages || totalPkgCount} paket</b> (${escapeHtml(lastResponseData.expedition || currentExpedition)}) berhasil disimpan ke sistem! [Ref: ${escapeHtml(lastResponseData.receipt_number || currentReceiptId)}]`, 'success');
                    playBeep('success');

                    // Paket yang tidak ikut terkirim (seharusnya tidak ada karena input dikunci) tetap dipertahankan di draft
                    const remainingPackages = draftPackages.filter(p => !submittedIds.has(p.id));
                    if (remainingPackages.length === 0) {
                        // Bersihkan draft tersimpan karena penerimaan sudah sukses masuk database
                        await clearDraftFromStorage();

                        // Reset form untuk penerimaan baru tanpa memunculkan modal bukti di layar
                        resetReceptionForm();
                    } else {
                        draftPackages = remainingPackages;
                        currentReceptionDbId = null;
                        await generateReceiptId();
                        await saveDraftToStorage();
                        renderDraftList();
                        showStatusMsg(`✅ Penerimaan tersimpan. ⚠️ ${remainingPackages.length} paket yang di-scan saat proses simpan tetap di draft sebagai penerimaan baru — klik Simpan lagi.`, 'warning');
                    }
                    loadHistoryData();
                } else {
                    alert('Gagal menyimpan: ' + (lastResponseData?.error || 'Terjadi kesalahan sistem saat menyimpan data penerimaan.'));
                }
            } catch (err) {
                console.error('Error submitCompleteReception:', err);
                alert('Terjadi kesalahan saat memproses data:\n\n' + (err && err.message ? err.message : String(err)));
            } finally {
                isSubmittingReception = false;
                if (pkgInputLock) pkgInputLock.disabled = false;
                if (btnDesktop) {
                    btnDesktop.disabled = false;
                    btnDesktop.innerHTML = origDesktopHtml;
                    btnDesktop.classList.remove('opacity-70', 'pointer-events-none');
                }
                if (btnMobile) {
                    btnMobile.disabled = false;
                    btnMobile.innerHTML = origMobileHtml;
                    btnMobile.classList.remove('opacity-70', 'pointer-events-none');
                }
                // innerHTML tombol baru dikembalikan -> perbarui ulang angka jumlah paket (#btnSubmitCount / #mobileBtnCount)
                renderDraftList();
            }
        }

        // Tampilkan Modal Slip Tanda Terima (Cetak)
        function showReceiptModal(data) {
            setText('slipReceiptNo', data.receipt_number || '-');
            setText('slipExpedition', data.expedition || '-');
            setText('slipDateTime', data.created_at || '-');
            setText('slipCourier', data.courier_name || '-');
            setText('slipOperator', data.operator_name || CURRENT_OPERATOR_NAME || '-');
            setText('slipSackNumber', data.sack_number || '-');
            const actualTotal = (data.packages && data.packages.length > 0) ? data.packages.length : (data.total_packages || 0);
            setText('slipTotalPackages', actualTotal);

            // Rekap Total Paket Per Karung
            const sackSummary = {};
            const pkgList = data.packages || [];
            if (pkgList.length > 0) {
                pkgList.forEach(p => {
                    const s = (typeof p === 'object' ? (p.sack_number || data.sack_number || 'Karung 1') : (data.sack_number || 'Karung 1')).trim() || 'Karung 1';
                    sackSummary[s] = (sackSummary[s] || 0) + 1;
                });
            } else if (data.sack_number) {
                sackSummary[data.sack_number] = actualTotal;
            } else {
                sackSummary['Karung 1'] = actualTotal;
            }

            const sackBreakdownList = document.getElementById('slipSackBreakdownList');
            const sackKeys = Object.keys(sackSummary);
            setText('slipTotalSacksCount', `${sackKeys.length} Karung`);

            if (sackBreakdownList) {
                let sHtml = '';
                sackKeys.forEach(sName => {
                    const count = sackSummary[sName];
                    sHtml += `
                        <div class="bg-white border border-amber-200/90 rounded-lg p-2 flex items-center justify-between shadow-2xs">
                            <span class="font-mono font-bold text-amber-950 text-xs truncate mr-1.5">${escapeHtml(sName)}</span>
                            <span class="bg-amber-100 text-amber-900 font-black text-xs px-2 py-0.5 rounded-md shrink-0">${count} <span class="text-[10px] font-medium font-sans">paket</span></span>
                        </div>
                    `;
                });
                sackBreakdownList.innerHTML = sHtml || '<div class="text-amber-800 text-xs py-1 col-span-2">Tidak ada data karung</div>';
            }

            // Foto Kurir di Slip
            const courierSec = document.getElementById('slipCourierPhotoSection');
            const courierImg = document.getElementById('slipCourierPhotoImg');
            const courierName = document.getElementById('slipCourierPhotoName');

            if (data.courier_photo) {
                showElement(courierSec);
                if (courierImg) courierImg.src = data.courier_photo;
                setText(courierName, (data.courier_name || '-') + ' (' + (data.expedition || '-') + ')');
            } else {
                hideElement(courierSec);
            }

            // List Barcode Resi & Foto Paket
            const listEl = document.getElementById('slipPackageList');
            if (listEl) {
                let listHtml = '';
                (data.packages || []).forEach((item, i) => {
                    const bCode = (typeof item === 'object') ? (item.package_barcode || item.barcode || '') : item;
                    const pPath = (typeof item === 'object') ? (item.photo_path || item.photo || null) : null;
                    const sNum  = (typeof item === 'object') ? (item.sack_number || '') : '';
                    const scanTimeStr = (typeof item === 'object' && item.scanned_at) ? (item.scanned_at.includes(' ') ? item.scanned_at.split(' ')[1] : item.scanned_at) : '';
                    const sackTag = sNum ? `<span class="text-[9px] bg-amber-50 text-amber-800 font-bold px-1.5 py-0.5 rounded border border-amber-200 font-mono">${escapeHtml(sNum)}</span>` : '';
                    const timeTag = scanTimeStr ? `<span class="text-[9px] text-slate-400 font-mono font-medium">${escapeHtml(scanTimeStr)}</span>` : '';

                    const photoThumb = pPath ? `
                        <div class="relative group cursor-pointer shrink-0" onclick="previewImageDirect(${jsArg(pPath)})">
                            <img src="${escapeHtml(pPath)}" class="w-11 h-11 rounded-lg object-cover border border-slate-300 shadow-2xs group-hover:scale-105 group-hover:border-emerald-500 transition" alt="Foto Paket">
                            <span class="absolute inset-0 bg-black/35 opacity-0 group-hover:opacity-100 flex items-center justify-center rounded-lg transition text-white text-[10px]">
                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                            </span>
                        </div>
                    ` : `
                        <div class="w-11 h-11 rounded-lg bg-slate-100 border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 shrink-0" title="Belum ada foto fisik">
                            <i class="fa-solid fa-camera text-[10px]"></i>
                            <span class="text-[8px] font-sans">No Foto</span>
                        </div>
                    `;

                    listHtml += `
                        <div class="flex items-center justify-between gap-2 bg-white rounded-xl border border-slate-200/90 p-2 text-xs hover:border-emerald-300 transition shadow-2xs">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <span class="w-5 h-5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold flex items-center justify-center shrink-0 border border-slate-200">${i + 1}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono font-bold text-slate-900 text-xs tracking-tight break-all select-all whitespace-normal leading-tight">${escapeHtml(bCode)}</span>
                                        ${sackTag}
                                        ${timeTag}
                                    </div>
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <span class="text-[9px] text-emerald-700 font-bold bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-300 inline-block">TERIMA OK</span>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 flex items-center gap-1">
                                ${photoThumb}
                            </div>
                        </div>
                    `;
                });
                listEl.innerHTML = listHtml || '<div class="text-slate-400 text-center py-2 col-span-2">Tidak ada rincian resi.</div>';
            }

            // Render Foto-foto Bukti Paket di Slip
            const photoSection = document.getElementById('slipPhotoSection');
            const photoContainer = document.getElementById('slipPhotoContainer');

            let photosToDisplay = [];
            if (Array.isArray(data.photos)) {
                photosToDisplay = data.photos;
            } else if (typeof data.photos === 'string' && data.photos.startsWith('[')) {
                try { photosToDisplay = JSON.parse(data.photos); } catch (e) {}
            } else if (data.photo_path) {
                photosToDisplay = [data.photo_path];
            }

            if (photosToDisplay && photosToDisplay.length > 0) {
                showElement(photoSection);
                setText('slipPhotoCount', photosToDisplay.length);
                let photoHtml = '';
                photosToDisplay.forEach((pUrl, idx) => {
                    photoHtml += `
                        <div class="relative group rounded-xl overflow-hidden border border-slate-200 aspect-video bg-black cursor-pointer shadow-xs hover:border-emerald-500 transition" onclick="previewImageDirect(${jsArg(pUrl)})">
                            <img src="${escapeHtml(pUrl)}" alt="Foto Paket ${idx + 1}" class="w-full h-full object-cover group-hover:scale-105 transition">
                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition text-white text-xs font-semibold gap-1">
                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                                <span>Perbesar</span>
                            </div>
                        </div>
                    `;
                });
                if (photoContainer) photoContainer.innerHTML = photoHtml;
            } else {
                hideElement(photoSection);
                if (photoContainer) photoContainer.innerHTML = '';
            }

            showElement('receiptModal');
        }

        function closeReceiptModal() {
            hideElement('receiptModal');
        }

        function startNewReceptionAfterSave() {
            closeReceiptModal();
            resetReceptionForm();
        }

        // Tombol "Reset" di Langkah 1: minta konfirmasi agar draft tidak terhapus karena salah sentuh
        function confirmResetReceptionForm() {
            if (isSubmittingReception) return;
            if (draftPackages.length > 0 && !confirm(`Hapus draft ${draftPackages.length} paket beserta fotonya & mulai penerimaan baru?`)) {
                return;
            }
            resetReceptionForm();
        }

        function resetReceptionForm() {
            draftPackages = [];
            currentReceptionDbId = null;
            currentExpedition = '';
            currentCourierName = '';
            currentCourierPhoto = null;
            currentSackNumber = 'Karung 1';
            currentScanningBarcode = '';
            targetRetakeDraftId = null;

            clearDraftFromStorage();

            // Reset Input DOM
            const selectExp = document.getElementById('selectExpedition');
            if (selectExp) selectExp.value = '';
            const courierInput = document.getElementById('inputCourierName');
            if (courierInput) courierInput.value = '';
            const sackInput = document.getElementById('inputSackNumber');
            if (sackInput) sackInput.value = 'Karung 1';
            selectQuickSack('Karung 1');
            const pkgInput = document.getElementById('inputPackageBarcode');
            if (pkgInput) pkgInput.value = '';

            // Reset Card Steps visibility (kembali ke Card Step 1)
            showElement('step1SelectWrapper');
            hideElement('step1Summary');
            hideElement('cardStep2');
            hideElement('cardStep3');
            hideElement('cardStep4');
            hideElement('cardStep5');

            renderDraftList();
            generateReceiptId();
            dismissStatusMsg();
            selectExp?.focus();
        }

        // ==============================================================
        // UTILITIES & RIWAYAT PENERIMAAN
        // ==============================================================

        async function generateReceiptId() {
            let rId = '';
            try {
                const data = await fetchJson('api/reception.php?action=generate_id');
                if (data && data.success && data.receipt_number) {
                    rId = data.receipt_number;
                }
            } catch (e) {
                console.warn('Gagal mengambil No. Tanda Terima dari server, memakai nomor sementara:', e);
            }
            if (!rId) {
                // Nomor sementara (tanggal LOKAL WIB, bukan UTC); server akan memberi nomor baru jika bentrok
                const dStr = localYmd().replace(/-/g, '');
                rId = `RCV-${dStr}-${Math.floor(1000 + Math.random() * 9000)}`;
            }
            currentReceiptId = rId;
            const inputNo = document.getElementById('inputReceiptNo');
            if (inputNo) inputNo.value = rId;
            setText('badgeReceiptDisplay', '#' + rId);
        }

        function previewImageDirect(url) {
            const modal = document.getElementById('receptionPhotoModal');
            const img = document.getElementById('receptionPhotoModalImg');
            const title = document.getElementById('receptionPhotoModalTitle');
            const dlBtn = document.getElementById('btnDownloadReceptionPhoto');

            if (img) img.src = url;
            setText(title, 'Bukti Foto Serah Terima');
            if (dlBtn) {
                dlBtn.href = url;
                dlBtn.download = `rcv_foto_${Date.now()}.jpg`;
            }

            showElement(modal);
        }

        function closeReceptionPhotoModal() {
            hideElement('receptionPhotoModal');
        }

        function triggerFlashEffect() {
            const flash = document.getElementById('cameraFlashOverlay');
            if (flash) {
                flash.classList.remove('opacity-0');
                flash.classList.add('opacity-80');
                setTimeout(() => {
                    flash.classList.remove('opacity-80');
                    flash.classList.add('opacity-0');
                }, 130);
            }
        }

        function showStatusMsg(msg, type = 'info') {
            const el = document.getElementById('scanStatusMsg');
            const text = document.getElementById('scanStatusText');
            if (!el || !text) return;

            el.className = 'text-xs font-semibold px-3 py-2 rounded-xl flex items-center justify-between transition ';
            if (type === 'success') {
                el.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
            } else if (type === 'warning') {
                el.classList.add('bg-amber-50', 'text-amber-800', 'border', 'border-amber-200');
            } else {
                el.classList.add('bg-slate-100', 'text-slate-700', 'border', 'border-slate-200');
            }
            text.innerHTML = msg;
            showElement(el);
        }

        function dismissStatusMsg() {
            hideElement('scanStatusMsg');
        }

        function switchTab(tab) {
            const btnScan = document.getElementById('tabBtnScan');
            const btnHist = document.getElementById('tabBtnHistory');
            const viewScan = document.getElementById('viewScan');
            const viewHist = document.getElementById('viewHistory');

            if (tab === 'scan') {
                if (btnScan) btnScan.className = 'flex-1 py-1.5 sm:py-2 px-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-emerald-600 text-white shadow-xs';
                if (btnHist) btnHist.className = 'flex-1 py-1.5 sm:py-2 px-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900';
                showElement(viewScan);
                hideElement(viewHist);
            } else {
                if (btnHist) btnHist.className = 'flex-1 py-1.5 sm:py-2 px-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-emerald-600 text-white shadow-xs';
                if (btnScan) btnScan.className = 'flex-1 py-1.5 sm:py-2 px-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900';
                hideElement(viewScan);
                showElement(viewHist);
                loadHistoryData();
            }
        }

        let historySearchTimeout = null;
        function debounceHistorySearch() {
            clearTimeout(historySearchTimeout);
            const val = document.getElementById('historySearchInput')?.value.trim() || '';
            const clearBtn = document.getElementById('historyClearSearch');
            if (clearBtn) {
                if (val) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');
            }
            historySearchTimeout = setTimeout(() => {
                loadHistoryData();
            }, 300);
        }

        function clearHistorySearch() {
            const input = document.getElementById('historySearchInput');
            if (input) input.value = '';
            const clearBtn = document.getElementById('historyClearSearch');
            if (clearBtn) clearBtn.classList.add('hidden');
            loadHistoryData();
        }

        async function loadHistoryData() {
            const dateInput = document.getElementById('historyDateFilter');
            const expSelect = document.getElementById('historyExpeditionFilter');
            const searchInput = document.getElementById('historySearchInput');

            const selectedDate = (dateInput && dateInput.value) ? dateInput.value : localYmd(); // tanggal LOKAL (WIB), bukan UTC
            if (dateInput && !dateInput.value) dateInput.value = selectedDate;

            const selectedExp = (expSelect && expSelect.value) ? expSelect.value : '';
            const searchQuery = (searchInput && searchInput.value) ? searchInput.value.trim() : '';

            const tbody = document.getElementById('historyTableBody');
            const badge = document.getElementById('historyCountBadge');
            const photoBadge = document.getElementById('historyPhotoCountBadge');

            try {
                let url = `api/reception.php?action=list&view=packages&date=${encodeURIComponent(selectedDate)}`;
                if (selectedExp) url += `&expedition=${encodeURIComponent(selectedExp)}`;
                if (searchQuery) url += `&search=${encodeURIComponent(searchQuery)}`;

                const data = await fetchJson(url);

                if (data && data.success && Array.isArray(data.data)) {
                    const packages = data.data;
                    const totalPkgs = data.total_packages || packages.length;
                    const totalPhotos = data.total_photos || 0;

                    setText(badge, `${totalPkgs} Paket`);
                    setText('historyTabCountBadge', totalPkgs);
                    setText(photoBadge, totalPhotos);

                    // Populate dropdown ekspedisi jika masih hanya 1 opsi
                    if (expSelect && expSelect.options.length <= 1) {
                        const uniqueExps = [...new Set(packages.map(p => p.expedition).filter(Boolean))];
                        uniqueExps.forEach(exp => {
                            const opt = document.createElement('option');
                            opt.value = exp;
                            opt.textContent = exp;
                            expSelect.appendChild(opt);
                        });
                    }

                    if (packages.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="10" class="text-center py-12 text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 text-xl">
                                            <i class="fa-solid fa-box-open"></i>
                                        </div>
                                        <span class="font-bold text-slate-600 text-xs">Belum ada paket yang tercatat</span>
                                        <span class="text-[11px] text-slate-400">Silakan scan paket pada tab "Scan Paket" atau ubah filter tanggal.</span>
                                    </div>
                                </td>
                            </tr>
                        `;
                        return;
                    }

                    let rows = '';
                    packages.forEach((item, idx) => {
                        const scanTime = item.scanned_at || item.reception_created_at || '-';
                        const timeOnly = scanTime.includes(' ') ? scanTime.split(' ')[1] : scanTime;

                        // Foto Paket Thumbnail
                        let photoHtml = '';
                        if (item.package_photo) {
                            photoHtml = `
                                <div class="relative group w-10 h-10 mx-auto cursor-pointer" onclick="previewImageDirect(${jsArg(item.package_photo)})">
                                    <img src="${escapeHtml(item.package_photo)}" alt="Foto Paket" class="w-10 h-10 rounded-lg object-cover border border-slate-200 shadow-2xs group-hover:scale-105 transition">
                                    <span class="absolute inset-0 bg-black/40 rounded-lg opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-[10px] transition">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                                </div>
                            `;
                        } else {
                            photoHtml = `
                                <div class="w-10 h-10 rounded-lg bg-slate-100 border border-dashed border-slate-300 flex items-center justify-center text-slate-400 mx-auto" title="Tidak ada foto">
                                    <i class="fa-solid fa-image-slash text-xs"></i>
                                </div>
                            `;
                        }

                        // Courier & Avatar
                        let courierHtml = '';
                        if (item.courier_photo) {
                            courierHtml = `
                                <div class="flex items-center gap-2">
                                    <img src="${escapeHtml(item.courier_photo)}" onclick="previewImageDirect(${jsArg(item.courier_photo)})" class="w-7 h-7 rounded-full object-cover border border-indigo-200 cursor-pointer shadow-2xs shrink-0 hover:scale-110 transition" title="Klik foto kurir">
                                    <span class="text-slate-800 font-semibold truncate max-w-[120px]">${escapeHtml(item.courier_name || '-')}</span>
                                </div>
                            `;
                        } else {
                            courierHtml = `<span class="text-slate-600 font-medium">${escapeHtml(item.courier_name || '-')}</span>`;
                        }

                        // Action Buttons
                        let actionBtns = `
                            <div class="flex items-center justify-center gap-1.5">
                                ${item.package_photo ? `
                                <button onclick="previewImageDirect(${jsArg(item.package_photo)})" class="w-7 h-7 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 flex items-center justify-center transition shadow-2xs" title="Lihat Foto Paket">
                                    <i class="fa-solid fa-camera text-xs"></i>
                                </button>` : ''}
                                <button onclick="viewReceptionDetail(${item.reception_id})" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 font-bold text-[11px] flex items-center gap-1 transition shadow-2xs" title="Buka Slip Serah Terima">
                                    <i class="fa-solid fa-file-lines text-xs"></i>
                                    <span>Slip</span>
                                </button>
                            </div>
                        `;

                        rows += `
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-2.5 px-3 text-center font-mono text-[11px] text-slate-400 font-bold">${idx + 1}</td>
                                <td class="py-2.5 px-3 text-center">${photoHtml}</td>
                                <td class="py-2.5 px-3">
                                    <div class="flex items-center gap-1.5 font-mono">
                                        <span class="font-black text-slate-900 text-xs tracking-wider">${escapeHtml(item.package_barcode)}</span>
                                        <button onclick="copyTextSafe(${jsArg(item.package_barcode)})" class="text-slate-400 hover:text-slate-600 p-0.5" title="Salin Barcode">
                                            <i class="fa-regular fa-copy text-[11px]"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 font-bold border border-emerald-200/80 text-[11px]">
                                        ${escapeHtml(item.expedition)}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">${courierHtml}</td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-800 font-bold border border-amber-200/80 text-[11px] font-mono">
                                        ${escapeHtml(item.sack_number || '-')}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-[11px]">
                                    <button type="button" onclick="viewReceptionDetail(${item.reception_id || item.id})" class="inline-flex items-center gap-1.5 font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg border border-emerald-200/80 transition text-[11px] group cursor-pointer shadow-2xs" title="Lihat detail paket history receiving & foto">
                                        <i class="fa-solid fa-receipt text-emerald-600 group-hover:scale-110 transition"></i>
                                        <span class="underline decoration-emerald-300 underline-offset-2">${escapeHtml(item.receipt_number)}</span>
                                    </button>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600">
                                    <span class="font-bold">${timeOnly}</span>
                                    <span class="text-[10px] text-slate-400 block">${scanTime.includes(' ') ? scanTime.split(' ')[0] : ''}</span>
                                </td>
                                <td class="py-2.5 px-3 text-slate-600 font-medium">${escapeHtml(item.operator_name || '-')}</td>
                                <td class="py-2.5 px-3 text-center">${actionBtns}</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = rows;
                }
            } catch (e) {
                console.error('Gagal memuat riwayat paket:', e);
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-rose-500 font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i>Gagal memuat riwayat: ${escapeHtml(e.message)}</td></tr>`;
            }
        }

        async function viewReceptionDetail(id) {
            try {
                const data = await fetchJson(`api/reception.php?action=detail&id=${encodeURIComponent(id)}`);
                if (data && data.success && data.reception) {
                    const packagePhotos = (data.packages || []).map(p => p.photo_path).filter(Boolean);
                    if (data.reception.photo_path && !packagePhotos.includes(data.reception.photo_path)) {
                        packagePhotos.unshift(data.reception.photo_path);
                    }
                    // package_photos dari DB berupa string JSON -> parse dulu
                    let recPkgPhotos = data.reception.package_photos;
                    if (typeof recPkgPhotos === 'string' && recPkgPhotos.trim().startsWith('[')) {
                        try { recPkgPhotos = JSON.parse(recPkgPhotos); } catch (e) { recPkgPhotos = null; }
                    }
                    if (Array.isArray(recPkgPhotos)) {
                        recPkgPhotos.forEach(ph => {
                            if (ph && !packagePhotos.includes(ph)) packagePhotos.push(ph);
                        });
                    }

                    showReceiptModal({
                        receipt_number: data.reception.receipt_number,
                        expedition: data.reception.expedition,
                        courier_name: data.reception.courier_name,
                        courier_photo: data.reception.courier_photo,
                        sack_number: data.reception.sack_number,
                        vehicle_no: data.reception.vehicle_no,
                        total_packages: data.reception.total_packages,
                        operator_name: data.reception.operator_name,
                        packages: (data.packages || []).map(p => ({
                            package_barcode: p.package_barcode,
                            photo_path: p.photo_path,
                            sack_number: p.sack_number,
                            scanned_at: p.scanned_at
                        })),
                        created_at: data.reception.created_at,
                        photos: packagePhotos
                    });
                } else {
                    alert('Data penerimaan tidak ditemukan.');
                }
            } catch (e) {
                alert('Gagal mengambil detail penerimaan: ' + e.message);
            }
        }

        // Helper DOM Visibility
        function showElement(elOrId) {
            const el = (typeof elOrId === 'string') ? document.getElementById(elOrId) : elOrOrNull(elOrId);
            if (el) el.classList.remove('hidden');
        }

        function hideElement(elOrId) {
            const el = (typeof elOrId === 'string') ? document.getElementById(elOrId) : elOrOrNull(elOrId);
            if (el) el.classList.add('hidden');
        }

        function elOrOrNull(el) {
            return (el instanceof HTMLElement) ? el : null;
        }

        function escapeHtml(text) {
            if (!text && text !== 0) return '';
            return String(text).replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
            });
        }

        // Argumen string aman untuk handler inline onclick="fn(...)": JSON.stringify lalu escape HTML.
        // (escapeHtml saja tidak cukup: &#039; di-decode browser kembali menjadi ' sebelum JS dijalankan)
        function jsArg(value) {
            return escapeHtml(JSON.stringify(value === null || value === undefined ? '' : String(value)));
        }

        // fetch + parse JSON yang aman: respons HTML/warning PHP atau HTTP error menjadi pesan yang jelas
        async function fetchJson(url, options) {
            const res = await fetch(url, options);
            const txt = await res.text();
            let data;
            try {
                data = JSON.parse(txt);
            } catch (e) {
                if (res.status === 401) throw new Error('Sesi login telah habis. Buka tab baru untuk login kembali.');
                throw new Error(`Server (${res.status}) tidak mengirim data JSON: ` + txt.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 150));
            }
            if (!res.ok && !(data && data.success)) {
                throw new Error((data && data.error) || `HTTP ${res.status}`);
            }
            return data;
        }

        // Tanggal LOKAL perangkat (WIB) format YYYY-MM-DD (toISOString() memakai UTC -> mundur 1 hari pukul 00:00-06:59)
        function localYmd(d = new Date()) {
            const z = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
        }

        // Salin teks ke clipboard (navigator.clipboard tidak tersedia di http non-localhost)
        function copyTextSafe(text) {
            const done = () => playBeep('success');
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(() => {});
                    return;
                }
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                done();
            } catch (e) {}
        }

        // Mencegah browser meng-autofill kredensial (admin.cs) ke input pencarian
        function clearReceptionSearchAutofill() {
            const el = document.getElementById('historySearchInput');
            if (el) {
                const v = (el.value || '').trim().toLowerCase();
                if (v === 'admin.cs' || v.includes('admin.cs')) {
                    el.value = '';
                    if (typeof debounceHistorySearch === 'function') debounceHistorySearch();
                }
            }
        }
        document.addEventListener('DOMContentLoaded', () => {
            clearReceptionSearchAutofill();
            setTimeout(clearReceptionSearchAutofill, 200);
            setTimeout(clearReceptionSearchAutofill, 800);
        });
        document.addEventListener('focusin', (e) => {
            if (e.target && e.target.id === 'historySearchInput') {
                const v = (e.target.value || '').trim().toLowerCase();
                if (v === 'admin.cs' || v.includes('admin.cs')) {
                    e.target.value = '';
                }
            }
        });
    </script>
</body>
</html>
