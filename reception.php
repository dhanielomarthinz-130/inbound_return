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
                <!-- Tombol Kembali ke Menu Utama Operator (Hub) -->
                <a href="menu" title="Kembali ke Menu Utama Portal" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 px-2.5 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-shapes text-indigo-400"></i>
                    <span class="hidden sm:inline">Menu Utama</span>
                </a>

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
    <main class="w-full max-w-5xl mx-auto px-3 md:px-6 py-4 pb-12 space-y-4 flex-1 no-print">

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

            <!-- CARD 1: INFORMASI EKSPEDISI & PENGATURAN -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-3.5 sm:p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-truck-ramp-box"></i>
                        </div>
                        <div>
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Ekspedisi Pengantar</h2>
                            <p class="text-[10px] text-slate-400">Pilih ekspedisi dan isi identitas driver</p>
                        </div>
                    </div>
                    <button onclick="resetReceptionForm()" type="button" class="text-[11px] text-amber-600 hover:text-amber-700 font-semibold flex items-center gap-1 transition">
                        <i class="fa-solid fa-rotate"></i> <span class="hidden sm:inline">Reset Form</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5 sm:gap-3">
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
                                <option value="">-- Pilih Ekspedisi --</option>
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
                            <span>ID Penerimaan</span>
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

                <!-- Auto-Foto Setting Toggle Banner -->
                <div class="flex items-center justify-between bg-emerald-50/60 border border-emerald-200/80 rounded-xl px-3 py-2">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-emerald-950">Auto-Jepret Foto Paket saat Scan</span>
                            <p class="text-[10px] text-emerald-700 leading-tight">Saat barcode discan, kamera langsung memotret paket & masuk ke Draft</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-2">
                        <input type="checkbox" id="toggleAutoPhoto" checked class="sr-only peer" onchange="onAutoPhotoToggle(this)">
                        <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>
            </div>

            <!-- CARD 2: SCAN BARCODE & KAMERA VIEWPORT (MOBILE RESPONSIVE UNIFIED) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-3.5 sm:p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <div>
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Scan & Kamera Paket</h2>
                            <p class="text-[10px] text-slate-400">Scan resi dan otomatis abadikan foto fisik paket</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-mono font-bold bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded-lg hidden sm:inline-flex items-center gap-1">
                            <i class="fa-regular fa-keyboard"></i> TAB = Foto
                        </span>
                        <div class="bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-xl flex items-center gap-1.5">
                            <span class="text-[10px] font-bold text-amber-800 uppercase">Draft:</span>
                            <span id="draftCountBadgeTop" class="bg-amber-500 text-white font-black text-xs px-2 py-0.5 rounded-lg leading-none">0</span>
                        </div>
                    </div>
                </div>

                <!-- Live Camera Viewport (Mobile Friendly Aspect Ratio) -->
                <div class="relative bg-slate-950 rounded-2xl overflow-hidden border border-slate-800 shadow-inner">
                    <div class="aspect-video w-full max-h-[300px] flex items-center justify-center relative">
                        <video id="receptionLiveVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
                        <!-- Visual Flash Shutter -->
                        <div id="receptionFlashOverlay" class="absolute inset-0 bg-white pointer-events-none opacity-0 transition-opacity duration-150 z-20"></div>

                        <!-- Placeholder / Tombol Start Kamera jika mati -->
                        <div id="receptionCameraPlaceholder" class="absolute inset-0 bg-slate-900/95 flex flex-col items-center justify-center text-white space-y-2 p-3 text-center z-10">
                            <i class="fa-solid fa-camera text-3xl text-emerald-400 mb-1"></i>
                            <span class="text-xs font-bold text-slate-200">Kamera Live Paket Siap Digunakan</span>
                            <p class="text-[10px] text-slate-400 max-w-xs">Nyalakan kamera live untuk memotret otomatis setiap paket saat barcode di-scan</p>
                            <button onclick="startReceptionCamera()" type="button" class="mt-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2 rounded-xl transition shadow-md flex items-center gap-1.5">
                                <i class="fa-solid fa-power-off"></i> Nyalakan Kamera
                            </button>
                        </div>

                        <!-- Top Floating Overlay Controls on Camera -->
                        <div class="absolute top-2 left-2 right-2 z-10 flex items-center justify-between pointer-events-none">
                            <span id="receptionCamBadge" class="hidden pointer-events-auto bg-black/60 backdrop-blur-xs text-white text-[10px] px-2 py-0.5 rounded-md font-mono flex items-center gap-1.5 border border-white/10">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span> KAMERA READY
                            </span>

                            <div class="flex items-center gap-1.5 pointer-events-auto">
                                <button id="btnSwitchRecCam" onclick="switchReceptionCamera()" type="button" title="Ganti Kamera Depan/Belakang" class="bg-black/50 hover:bg-black/80 text-white px-2 py-1 rounded-lg text-xs transition border border-white/10 hidden flex items-center gap-1">
                                    <i class="fa-solid fa-arrows-rotate text-[11px]"></i>
                                    <span class="text-[10px]">Putar</span>
                                </button>
                                <button onclick="toggleReceptionCameraPower()" type="button" title="Matikan/Nyalakan Kamera" class="bg-black/50 hover:bg-black/80 text-white px-2 py-1 rounded-lg text-[10px] transition border border-white/10">
                                    <i class="fa-solid fa-video text-[10px]"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Bottom Floating Watermark Simulator Indicator -->
                        <div class="absolute bottom-2 left-2 right-2 pointer-events-none z-10 flex items-center justify-between text-[10px] text-white/80 bg-black/50 backdrop-blur-xs px-2.5 py-1 rounded-lg border border-white/10">
                            <span class="font-mono text-emerald-300 font-bold truncate max-w-[200px]" id="camOverlayExp">IEG INBOUND</span>
                            <span class="font-mono text-slate-300" id="camOverlayTime">Auto Watermark ON</span>
                        </div>
                    </div>
                </div>

                <!-- Input Scanner Box & Mobile Actions -->
                <div class="space-y-2">
                    <div class="flex flex-col sm:flex-row gap-2">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                <i class="fa-solid fa-barcode"></i>
                            </span>
                            <input type="text" id="inputPackageBarcode" placeholder="Scan barcode resi di sini..." autocomplete="off" class="w-full pl-10 pr-3 py-3 bg-slate-50 border-2 border-slate-300 rounded-2xl text-sm font-mono font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-inner">
                        </div>
                        
                        <!-- Tombol Tambah Manual & Jepret -->
                        <div class="flex items-center gap-1.5">
                            <button onclick="submitPackageBarcode()" type="button" class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white px-4 py-3 rounded-2xl font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 shrink-0">
                                <i class="fa-solid fa-plus text-sm"></i>
                                <span>Tambah Draft</span>
                            </button>

                            <!-- Tombol Jepret Manual / Tuts TAB -->
                            <button id="btnCaptureReceptionPhoto" onclick="captureReceptionPhoto()" type="button" title="Jepret foto manual (TAB)" class="bg-slate-800 hover:bg-slate-900 active:scale-95 text-white px-3.5 py-3 rounded-2xl font-bold text-xs transition flex items-center justify-center gap-1 shrink-0 shadow-sm">
                                <i class="fa-solid fa-camera"></i>
                                <span class="hidden md:inline">Jepret</span>
                            </button>

                            <!-- Tombol Buka Kamera Scanner Barcode HP -->
                            <button id="btnToggleCamera" onclick="toggleCameraScanner()" type="button" title="Scan Barcode via Kamera HP" class="bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white px-3.5 py-3 rounded-2xl font-bold text-xs transition flex items-center justify-center gap-1 shrink-0 shadow-sm shadow-indigo-600/20">
                                <i class="fa-solid fa-qrcode"></i>
                                <span class="hidden sm:inline">Barcode HP</span>
                            </button>

                            <!-- Tombol Jepret Kamera HP Asli (File Input Capture) -->
                            <button onclick="triggerMobileCameraInput()" type="button" title="Ambil Foto langsung dari Kamera HP" class="bg-teal-600 hover:bg-teal-700 active:scale-95 text-white px-3.5 py-3 rounded-2xl font-bold text-xs transition flex items-center justify-center gap-1 shrink-0 shadow-sm shadow-teal-600/20">
                                <i class="fa-solid fa-camera-retro"></i>
                                <span class="hidden sm:inline">Foto HP</span>
                            </button>
                        </div>
                    </div>

                    <!-- Hidden Native Mobile Camera Input -->
                    <input type="file" id="mobileCameraInput" accept="image/*" capture="environment" class="hidden" onchange="handleMobileCameraFile(this)">

                    <!-- Viewport Kamera Barcode HP (HTML5-QRCode) -->
                    <div id="cameraScannerContainer" class="hidden bg-slate-900 rounded-2xl p-3 border border-slate-800 relative transition-all">
                        <div class="flex justify-between items-center text-white mb-2 px-1">
                            <div class="flex items-center gap-2 text-xs font-bold">
                                <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                                <span>Arahkan Kamera ke Barcode Resi</span>
                            </div>
                            <button onclick="toggleCameraScanner()" type="button" class="text-slate-400 hover:text-white text-xs bg-slate-800 px-2 py-1 rounded-lg">
                                <i class="fa-solid fa-xmark"></i> Tutup
                            </button>
                        </div>
                        <div id="reader" class="w-full overflow-hidden rounded-xl bg-black min-h-[200px]"></div>
                        <p class="text-[10px] text-center text-slate-400 mt-2">
                            Mode continuous scan: Barcode terdeteksi langsung otomatis difoto dan masuk ke Draft.
                        </p>
                    </div>

                    <!-- Alert / Status Notifikasi -->
                    <div id="scanStatusMsg" class="hidden text-xs font-semibold px-3 py-2 rounded-xl flex items-center justify-between transition">
                        <span id="scanStatusText"></span>
                        <button onclick="dismissStatusMsg()" class="text-slate-400 hover:text-slate-600 text-xs ml-2"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>
            </div>

            <!-- CARD 3: DAFTAR PAKET DRAFT (SIAP DI-SUBMIT KE SISTEM) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-3.5 sm:p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Daftar Paket Draft</h2>
                                <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full border border-amber-300/80">Belum Disimpan</span>
                            </div>
                            <p class="text-[10px] text-slate-400">Total <span id="draftCountLabel" class="font-bold text-slate-700">0</span> paket siap di-submit ke sistem</p>
                        </div>
                    </div>

                    <button onclick="clearAllDrafts()" type="button" class="text-[11px] text-rose-500 hover:text-rose-700 font-semibold transition flex items-center gap-1">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                        <span>Hapus Semua Draft</span>
                    </button>
                </div>

                <!-- Empty State Draft -->
                <div id="draftListEmpty" class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center text-slate-400">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2 text-xl">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-600">Belum Ada Paket di Draft</p>
                    <p class="text-[11px] text-slate-400 mt-0.5 max-w-sm mx-auto">
                        Tembakkan barcode scanner atau ketik nomor resi di atas. Paket beserta fotonya akan otomatis terkumpul di sini sebagai draft.
                    </p>
                </div>

                <!-- Non-Empty Draft Container (Mobile Friendly Cards) -->
                <div id="draftPackagesContainer" class="hidden space-y-2 max-h-[460px] overflow-y-auto pr-1">
                    <!-- Dynamic Draft Item Cards -->
                </div>
            </div>

            <!-- CARD 4: SUBMIT PENERIMAAN DESKTOP & TABLET -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-black text-lg shrink-0 border border-emerald-500/20">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <div class="leading-tight">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-800">Submit Penerimaan Ekspedisi</span>
                                <span id="submitExpeditionBadge" class="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-200">Semua Ekspedisi</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-0.5">Menyimpan seluruh paket draft dan foto bukti sekaligus ke database sistem</p>
                        </div>
                    </div>
                    
                    <button id="btnSubmitReception" onclick="submitCompleteReception()" type="button" class="w-full sm:w-auto px-6 py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-[0.99] text-white rounded-2xl font-black text-sm transition shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2.5">
                        <i class="fa-solid fa-check-double text-base"></i>
                        <span>Simpan & Selesaikan Penerimaan (<span id="btnSubmitCount">0</span> Paket Draft)</span>
                    </button>
                </div>
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

    <!-- MOBILE FLOATING STICKY BOTTOM BAR (TAMPILAN KHUSUS HP) -->
    <div id="mobileStickyBar" class="fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-md px-3.5 py-2.5 border-t border-slate-800 flex justify-between items-center md:hidden shadow-2xl no-print">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-black text-xs shrink-0 border border-amber-500/30">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div class="leading-tight">
                <div class="flex items-center gap-1.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Draft:</span>
                    <span id="mobileDraftCount" class="font-mono font-black text-amber-400 text-sm">0</span>
                    <span class="text-[10px] text-slate-400">Paket</span>
                </div>
                <span id="mobileExpBadge" class="text-[10px] text-slate-300 font-semibold block truncate max-w-[130px]">Pilih Ekspedisi</span>
            </div>
        </div>

        <button onclick="submitCompleteReception()" type="button" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-black text-xs px-4 py-2.5 rounded-xl shadow-lg shadow-emerald-900/40 flex items-center gap-2 transition">
            <i class="fa-solid fa-paper-plane text-xs"></i>
            <span>SUBMIT (<span id="mobileBtnCount">0</span>)</span>
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
                    <h2 class="font-black text-base tracking-tight">IEG</h2>
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

                <!-- Foto Bukti Paket di Slip Bukti -->
                <div id="slipPhotoSection" class="hidden border-t border-dashed border-slate-200 pt-3">
                    <h4 class="font-bold text-slate-700 mb-1.5 text-[11px] uppercase flex items-center justify-between">
                        <span>Foto Bukti Paket (<span id="slipPhotoCount">0</span>)</span>
                        <span class="text-[9px] text-slate-400 font-normal no-print">Klik foto untuk perbesar</span>
                    </h4>
                    <div id="slipPhotoContainer" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        <!-- Foto thumbnails -->
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

        // State Draft Penerimaan & Foto
        let draftPackages = []; // Array of { id, barcode, photo, time, timestamp }
        let isAutoPhotoEnabled = true;
        let targetRetakeDraftId = null;
        let html5QrCode = null;
        let isCameraActive = false;

        // State Kamera Receiving Live
        let receptionMediaStream = null;
        let availableVideoDevices = [];
        let currentVideoDeviceIndex = 0;

        // Inisialisasi saat halaman selesai dimuat
        document.addEventListener('DOMContentLoaded', () => {
            generateReceiptId();
            loadHistoryData();
            initReceptionLiveCamera();

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

            // Keyboard Shortcut TAB: Ambil / Simpan Foto Paket
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    captureReceptionPhoto();
                }
            });

            // Update overlay waktu kamera berkala
            setInterval(updateCameraOverlayTime, 1000);
        });

        // Toggle Switch Auto Photo
        function onAutoPhotoToggle(el) {
            isAutoPhotoEnabled = el ? el.checked : true;
            if (isAutoPhotoEnabled) {
                showStatusMsg('⚡ <b>Auto-Foto Aktif:</b> Setiap scan barcode akan langsung difoto dari kamera dan disimpan ke Draft.', 'success');
            } else {
                showStatusMsg('ℹ️ <b>Auto-Foto Dimatikan:</b> Paket akan masuk ke Draft tanpa foto otomatis. Foto dapat diambil manual.', 'info');
            }
        }

        function updateCameraOverlayTime() {
            const timeEl = document.getElementById('camOverlayTime');
            if (timeEl) {
                const now = new Date();
                timeEl.innerText = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
            }
        }

        // ==============================================================
        // KAMERA RECEIVING & WATERMARK ENGINE
        // ==============================================================

        // 1. Inisialisasi Kamera Live Receiving
        async function initReceptionLiveCamera() {
            const videoEl = document.getElementById('receptionLiveVideo');
            const placeholder = document.getElementById('receptionCameraPlaceholder');
            const switchBtn = document.getElementById('btnSwitchRecCam');
            const camBadge = document.getElementById('receptionCamBadge');
            if (!videoEl) return;

            try {
                // Deteksi daftar kamera
                if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    availableVideoDevices = devices.filter(d => d.kind === 'videoinput');
                    if (availableVideoDevices.length > 1 && switchBtn) {
                        switchBtn.classList.remove('hidden');
                    }
                }

                // Pengaturan stream (utamakan kamera belakang jika HP)
                const constraints = {
                    video: {
                        facingMode: { ideal: "environment" },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };

                receptionMediaStream = await navigator.mediaDevices.getUserMedia(constraints);
                videoEl.srcObject = receptionMediaStream;
                videoEl.onloadedmetadata = () => {
                    videoEl.play();
                    if (placeholder) placeholder.classList.add('hidden');
                    if (camBadge) camBadge.classList.remove('hidden');
                };
            } catch (err) {
                console.warn('Tidak dapat memulai kamera live receiving otomatis:', err);
                if (placeholder) {
                    placeholder.classList.remove('hidden');
                }
                if (camBadge) camBadge.classList.add('hidden');
            }
        }

        // 2. Nyalakan Kamera Manual jika sempat mati
        async function startReceptionCamera() {
            await initReceptionLiveCamera();
        }

        // 3. Matikan / Toggle Power Kamera
        function toggleReceptionCameraPower() {
            const videoEl = document.getElementById('receptionLiveVideo');
            const placeholder = document.getElementById('receptionCameraPlaceholder');
            const camBadge = document.getElementById('receptionCamBadge');

            if (receptionMediaStream) {
                receptionMediaStream.getTracks().forEach(track => track.stop());
                receptionMediaStream = null;
                if (videoEl) videoEl.srcObject = null;
                if (placeholder) placeholder.classList.remove('hidden');
                if (camBadge) camBadge.classList.add('hidden');
                showStatusMsg('Kamera live dinonaktifkan.', 'info');
            } else {
                initReceptionLiveCamera();
                showStatusMsg('Menyalakan kamera live...', 'info');
            }
        }

        // 4. Ganti Kamera (Depan / Belakang jika ada >1 kamera)
        async function switchReceptionCamera() {
            if (availableVideoDevices.length <= 1) return;
            currentVideoDeviceIndex = (currentVideoDeviceIndex + 1) % availableVideoDevices.length;
            const targetDevice = availableVideoDevices[currentVideoDeviceIndex];

            if (receptionMediaStream) {
                receptionMediaStream.getTracks().forEach(track => track.stop());
            }

            const videoEl = document.getElementById('receptionLiveVideo');
            try {
                receptionMediaStream = await navigator.mediaDevices.getUserMedia({
                    video: { deviceId: { exact: targetDevice.deviceId } },
                    audio: false
                });
                videoEl.srcObject = receptionMediaStream;
                videoEl.play();
            } catch (e) {
                console.error('Gagal beralih kamera:', e);
            }
        }

        // 5. Efek Flash Kamera Putih
        function triggerReceptionFlash() {
            const flash = document.getElementById('cameraFlashOverlay');
            const localFlash = document.getElementById('receptionFlashOverlay');
            [flash, localFlash].forEach(f => {
                if (f) {
                    f.classList.remove('opacity-0');
                    f.classList.add('opacity-80');
                    setTimeout(() => {
                        f.classList.remove('opacity-80');
                        f.classList.add('opacity-0');
                    }, 130);
                }
            });
        }

        // 6. Generate Foto dengan Watermark Receiving & Barcode Resi
        function generateReceptionWatermarkPhoto(sourceEl, packageBarcode = null) {
            const canvas = document.createElement('canvas');
            let w, h;
            if (sourceEl instanceof HTMLVideoElement) {
                w = sourceEl.videoWidth || 1280;
                h = sourceEl.videoHeight || 720;
            } else if (sourceEl instanceof HTMLImageElement) {
                w = sourceEl.naturalWidth || sourceEl.width || 1280;
                h = sourceEl.naturalHeight || sourceEl.height || 720;
            } else {
                w = 1280;
                h = 720;
            }

            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');

            // Render gambar
            ctx.drawImage(sourceEl, 0, 0, w, h);

            // Buat banner watermark bawah (Semi-transparan profesional)
            const barHeight = Math.max(88, Math.round(h * 0.16));
            const grad = ctx.createLinearGradient(0, h - barHeight, 0, h);
            grad.addColorStop(0, 'rgba(15, 23, 42, 0.92)');
            grad.addColorStop(1, 'rgba(2, 6, 23, 0.98)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, h - barHeight, w, barHeight);

            // Garis pembatas atas aksen hijau Emerald
            ctx.fillStyle = '#10b981';
            ctx.fillRect(0, h - barHeight, w, Math.max(3, Math.round(h * 0.006)));

            // Waktu & Tanggal
            const now = new Date();
            const dateStr = now.toLocaleDateString('id-ID', { year: 'numeric', month: '2-digit', day: '2-digit' });
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';

            const receiptNo = document.getElementById('inputReceiptNo')?.value.trim() || 'RCV-PENDING';
            const selectExp = document.getElementById('selectExpedition');
            const expedition = selectExp?.value.trim() || 'UMUM';
            const courier = document.getElementById('inputCourierName')?.value.trim() || '-';
            const bCode = packageBarcode || (document.getElementById('inputPackageBarcode')?.value.trim()) || '-';

            // Ukuran font proporsional
            const titleSize = Math.max(14, Math.round(w * 0.018));
            const bodySize  = Math.max(12, Math.round(w * 0.014));
            const subSize   = Math.max(10, Math.round(w * 0.011));

            ctx.textBaseline = 'top';

            // SISI KIRI: Branding & Data Resi Paket
            ctx.textAlign = 'left';
            ctx.fillStyle = '#10b981';
            ctx.font = `900 ${titleSize}px monospace, sans-serif`;
            ctx.fillText('IEG • INBOUND RECEIVING', 16, h - barHeight + 10);

            ctx.fillStyle = '#ffffff';
            ctx.font = `bold ${bodySize}px monospace, sans-serif`;
            ctx.fillText(`RESI: ${bCode}  |  NO. TERIMA: ${receiptNo}`, 16, h - barHeight + 10 + titleSize + 5);

            ctx.fillStyle = '#94a3b8';
            ctx.font = `normal ${subSize}px monospace, sans-serif`;
            ctx.fillText(`EKSPEDISI: ${expedition}  |  KURIR: ${courier}  |  OPERATOR: ${CURRENT_OPERATOR_NAME}`, 16, h - barHeight + 10 + titleSize + bodySize + 8);

            // SISI KANAN: Waktu & Status Fisik
            ctx.textAlign = 'right';
            ctx.fillStyle = '#38bdf8';
            ctx.font = `bold ${bodySize}px monospace, sans-serif`;
            ctx.fillText(`${dateStr} ${timeStr}`, w - 16, h - barHeight + 10);

            ctx.fillStyle = '#fbbf24';
            ctx.font = `bold ${subSize}px monospace, sans-serif`;
            ctx.fillText('📸 BUKTI SERAH TERIMA FISIK PAKET', w - 16, h - barHeight + 12 + bodySize);

            ctx.textAlign = 'left';
            return canvas.toDataURL('image/jpeg', 0.88);
        }

        // ==============================================================
        // LOGIKA SCAN PAKET, FOTO INSTAN & DRAFT MANAGEMENT
        // ==============================================================

        // Generate Receipt ID dari server
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

        // Event perubahan Ekspedisi
        function onExpeditionChanged() {
            const sel = document.getElementById('selectExpedition');
            const expName = sel ? sel.value : '';
            setText('mobileExpBadge', expName || 'Pilih Ekspedisi');
            setText('submitExpeditionBadge', expName || 'Semua Ekspedisi');
            const camExp = document.getElementById('camOverlayExp');
            if (camExp) camExp.innerText = expName ? `EKSPEDISI: ${expName}` : 'IEG INBOUND';
            document.getElementById('inputPackageBarcode')?.focus();
        }

        // Tambah Barcode Paket ke Draft
        function submitPackageBarcode() {
            const input = document.getElementById('inputPackageBarcode');
            const barcode = (input ? input.value : '').trim();
            if (!barcode) return;

            processPackageBarcode(barcode);
            if (input) {
                input.value = '';
                input.focus();
            }
        }

        // Inti Pemrosesan Scan Paket + Foto Instan ke DRAFT
        function processPackageBarcode(barcode) {
            const cleanBarcode = barcode.trim();
            if (!cleanBarcode) return;

            // Auto-detect ekspedisi jika belum dipilih
            const selectExp = document.getElementById('selectExpedition');
            if (selectExp && !selectExp.value) {
                detectExpeditionFromBarcode(cleanBarcode);
            }

            // Cek duplikasi di sesi draft saat ini
            const isDuplicate = draftPackages.some(item => item.barcode.toUpperCase() === cleanBarcode.toUpperCase());
            if (isDuplicate) {
                playBeep('warning');
                vibrateMobile([100, 50, 100]);
                showStatusMsg(`⚠️ Resi <b>${escapeHtml(cleanBarcode)}</b> sudah pernah di-scan dalam draft sesi ini!`, 'warning');
                return;
            }

            let photoDataUrl = null;
            const videoEl = document.getElementById('receptionLiveVideo');

            // Auto-Foto Seketika jika mode Auto-Foto ON dan video aktif
            if (isAutoPhotoEnabled && videoEl && videoEl.videoWidth > 0) {
                try {
                    photoDataUrl = generateReceptionWatermarkPhoto(videoEl, cleanBarcode);
                    playShutterSound();
                    triggerReceptionFlash();
                } catch (e) {
                    console.warn('Gagal auto-jepret:', e);
                }
            }

            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';

            const newDraftItem = {
                id: 'draft_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
                barcode: cleanBarcode,
                photo: photoDataUrl,
                time: timeStr,
                timestamp: Date.now()
            };

            draftPackages.unshift(newDraftItem);
            playBeep('success');
            vibrateMobile(60);

            if (photoDataUrl) {
                showStatusMsg(`📸 Paket <b>${escapeHtml(cleanBarcode)}</b> berhasil difoto & masuk ke <b>DRAFT</b>!`, 'success');
            } else {
                showStatusMsg(`📦 Paket <b>${escapeHtml(cleanBarcode)}</b> masuk ke <b>DRAFT</b>. (Klik [Foto HP] atau TAB untuk ambil foto)`, 'info');
            }

            renderDraftList();
        }

        // Deteksi otomatis ekspedisi dari barcode resi jika operator belum pilih
        function detectExpeditionFromBarcode(code) {
            const upper = code.toUpperCase();
            const selectExp = document.getElementById('selectExpedition');
            if (!selectExp) return;
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

        // Trigger Input Kamera HP Asli (File Input Capture)
        function triggerMobileCameraInput(draftId = null) {
            targetRetakeDraftId = draftId;
            const fileInput = document.getElementById('mobileCameraInput');
            if (fileInput) {
                fileInput.value = '';
                fileInput.click();
            }
        }

        // Handle Hasil Jepretan Kamera HP Asli
        function handleMobileCameraFile(inputEl) {
            if (!inputEl.files || !inputEl.files[0]) return;
            const file = inputEl.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    let targetBarcode = '';
                    if (targetRetakeDraftId) {
                        const item = draftPackages.find(p => p.id === targetRetakeDraftId);
                        if (item) targetBarcode = item.barcode;
                    } else {
                        const inputPkg = document.getElementById('inputPackageBarcode');
                        targetBarcode = (inputPkg && inputPkg.value.trim()) || (draftPackages.length > 0 ? draftPackages[0].barcode : 'PAKET');
                    }

                    const watermarked = generateReceptionWatermarkPhoto(img, targetBarcode);
                    playShutterSound();
                    triggerReceptionFlash();

                    if (targetRetakeDraftId) {
                        // Update paket spesifik
                        const item = draftPackages.find(p => p.id === targetRetakeDraftId);
                        if (item) {
                            item.photo = watermarked;
                            showStatusMsg(`📸 Foto untuk paket <b>${escapeHtml(item.barcode)}</b> berhasil diperbarui!`, 'success');
                        }
                        targetRetakeDraftId = null;
                    } else {
                        // Cek apakah ada barcode yang sedang diketik
                        const inputPkg = document.getElementById('inputPackageBarcode');
                        const typedBarcode = (inputPkg ? inputPkg.value : '').trim();

                        if (typedBarcode) {
                            const isDup = draftPackages.some(item => item.barcode.toUpperCase() === typedBarcode.toUpperCase());
                            if (!isDup) {
                                const now = new Date();
                                const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
                                draftPackages.unshift({
                                    id: 'draft_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
                                    barcode: typedBarcode,
                                    photo: watermarked,
                                    time: timeStr,
                                    timestamp: Date.now()
                                });
                                inputPkg.value = '';
                                showStatusMsg(`📸 Paket <b>${escapeHtml(typedBarcode)}</b> difoto & masuk ke <b>DRAFT</b>!`, 'success');
                            }
                        } else if (draftPackages.length > 0) {
                            // Update foto paket teratas
                            draftPackages[0].photo = watermarked;
                            showStatusMsg(`📸 Foto untuk paket <b>${escapeHtml(draftPackages[0].barcode)}</b> berhasil disimpan!`, 'success');
                        } else {
                            showStatusMsg('📸 Foto tersimpan. Silakan masukkan nomor resi barcode paket.', 'info');
                        }
                    }

                    renderDraftList();
                };
                img.src = e.target.result;
            };

            reader.readAsDataURL(file);
        }

        // Jepret Manual via Tombol atau Tuts TAB
        function captureReceptionPhoto() {
            const videoEl = document.getElementById('receptionLiveVideo');
            if (!videoEl || !videoEl.videoWidth) {
                initReceptionLiveCamera();
                showStatusMsg('Kamera sedang dipersiapkan, silakan klik kembali setelah menyala...', 'warning');
                return;
            }

            const inputPkg = document.getElementById('inputPackageBarcode');
            const typedBarcode = (inputPkg ? inputPkg.value : '').trim();

            if (typedBarcode) {
                // Jika sedang ada nomor barcode diketik di input, langsung eksekusi!
                processPackageBarcode(typedBarcode);
                if (inputPkg) inputPkg.value = '';
                return;
            }

            // Jika ada draft yang belum memiliki foto, isi foto untuk item tersebut
            const pendingItem = draftPackages.find(p => !p.photo);
            if (pendingItem) {
                const photo = generateReceptionWatermarkPhoto(videoEl, pendingItem.barcode);
                pendingItem.photo = photo;
                playShutterSound();
                triggerReceptionFlash();
                vibrateMobile(100);
                showStatusMsg(`📸 Foto bukti paket <b>${escapeHtml(pendingItem.barcode)}</b> berhasil disimpan!`, 'success');
                renderDraftList();
                return;
            }

            // Jika semua sudah berfoto, perbarui foto paket teratas
            if (draftPackages.length > 0) {
                const photo = generateReceptionWatermarkPhoto(videoEl, draftPackages[0].barcode);
                draftPackages[0].photo = photo;
                playShutterSound();
                triggerReceptionFlash();
                vibrateMobile(100);
                showStatusMsg(`📸 Foto untuk paket <b>${escapeHtml(draftPackages[0].barcode)}</b> diperbarui!`, 'success');
                renderDraftList();
            } else {
                playShutterSound();
                triggerReceptionFlash();
                showStatusMsg('💡 Masukkan atau scan barcode paket terlebih dahulu agar foto terhubung dengan resi.', 'warning');
                inputPkg?.focus();
            }
        }

        // Render Card Draft Paket (Mobile Responsive & Clean)
        function renderDraftList() {
            const count = draftPackages.length;
            setText('draftCountBadgeTop', count);
            setText('draftCountLabel', count);
            setText('btnSubmitCount', count);
            setText('mobileDraftCount', count);
            setText('mobileBtnCount', count);

            const emptyBox = document.getElementById('draftListEmpty');
            const container = document.getElementById('draftPackagesContainer');

            if (count === 0) {
                if (emptyBox) emptyBox.classList.remove('hidden');
                if (container) {
                    container.classList.add('hidden');
                    container.innerHTML = '';
                }
                return;
            }

            if (emptyBox) emptyBox.classList.add('hidden');
            if (container) {
                container.classList.remove('hidden');
                let html = '';
                draftPackages.forEach((pkg, idx) => {
                    const seq = count - idx;
                    const hasPhoto = !!pkg.photo;

                    const thumbHtml = hasPhoto
                        ? `<div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-xl overflow-hidden bg-black shrink-0 border border-slate-200 cursor-pointer shadow-xs group" onclick="previewDraftPhoto('${pkg.id}')" title="Klik untuk lihat foto">
                               <img src="${pkg.photo}" alt="Foto ${escapeHtml(pkg.barcode)}" class="w-full h-full object-cover group-hover:scale-105 transition">
                               <span class="absolute bottom-0.5 right-0.5 bg-black/70 text-[9px] text-white font-mono px-1 rounded font-bold">#${seq}</span>
                           </div>`
                        : `<div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl bg-amber-50 border border-amber-200 flex flex-col items-center justify-center text-amber-600 shrink-0 cursor-pointer hover:bg-amber-100 transition" onclick="retakeDraftPhoto('${pkg.id}')" title="Klik untuk ambil foto">
                               <i class="fa-solid fa-camera text-base mb-0.5"></i>
                               <span class="text-[8px] font-bold">Ambil Foto</span>
                           </div>`;

                    const badgePhoto = hasPhoto
                        ? `<span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-md border border-emerald-200"><i class="fa-solid fa-check text-[9px]"></i> Foto OK</span>`
                        : `<span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 text-[10px] font-bold px-2 py-0.5 rounded-md border border-amber-200 animate-pulse"><i class="fa-solid fa-triangle-exclamation text-[9px]"></i> Belum Foto</span>`;

                    html += `
                        <div class="flex items-center justify-between p-2.5 sm:p-3 bg-slate-50 hover:bg-slate-100/90 rounded-2xl border border-slate-200/90 transition shadow-xs gap-2.5 sm:gap-3">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                ${thumbHtml}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono font-black text-slate-900 text-xs sm:text-sm tracking-tight break-all">${escapeHtml(pkg.barcode)}</span>
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
                                <button onclick="retakeDraftPhoto('${pkg.id}')" type="button" title="Ganti / Jepret Ulang Foto" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 flex items-center justify-center text-xs transition">
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

        // Retake Foto untuk Item Draft Tertentu
        function retakeDraftPhoto(draftId) {
            const item = draftPackages.find(p => p.id === draftId);
            if (!item) return;

            const videoEl = document.getElementById('receptionLiveVideo');
            if (videoEl && videoEl.videoWidth > 0) {
                const photo = generateReceptionWatermarkPhoto(videoEl, item.barcode);
                item.photo = photo;
                playShutterSound();
                triggerReceptionFlash();
                vibrateMobile(100);
                showStatusMsg(`📸 Foto untuk paket <b>${escapeHtml(item.barcode)}</b> berhasil diperbarui!`, 'success');
                renderDraftList();
            } else {
                triggerMobileCameraInput(draftId);
            }
        }

        // Hapus 1 Paket dari Draft
        function removeDraftPackage(draftId) {
            const idx = draftPackages.findIndex(p => p.id === draftId);
            if (idx !== -1) {
                const removed = draftPackages.splice(idx, 1);
                showStatusMsg(`Resi ${escapeHtml(removed[0].barcode)} dihapus dari Draft.`, 'info');
                renderDraftList();
            }
        }

        // Hapus Semua Draft
        function clearAllDrafts() {
            if (draftPackages.length === 0) return;
            if (confirm(`Hapus semua ${draftPackages.length} paket dari Draft sesi ini?`)) {
                draftPackages = [];
                renderDraftList();
                showStatusMsg('Semua paket dalam draft telah dibersihkan.', 'info');
                document.getElementById('inputPackageBarcode')?.focus();
            }
        }

        // Preview Foto Draft
        function previewDraftPhoto(draftId) {
            const item = draftPackages.find(p => p.id === draftId);
            if (!item || !item.photo) return;
            previewImageDirect(item.photo);
        }

        // Kamera Scanner Mobile (HTML5-QRCode)
        function toggleCameraScanner() {
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
                    if (decodedText) {
                        processPackageBarcode(decodedText);
                    }
                },
                (errorMessage) => {}
            ).then(() => {
                isCameraActive = true;
                const btn = document.getElementById('btnToggleCamera');
                if (btn) {
                    btn.innerHTML = '<i class="fa-solid fa-camera-rotate"></i> <span class="hidden sm:inline">Matikan</span>';
                    btn.classList.replace('bg-indigo-600', 'bg-rose-600');
                }
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
                    const btn = document.getElementById('btnToggleCamera');
                    if (btn) {
                        btn.innerHTML = '<i class="fa-solid fa-qrcode"></i> <span class="hidden sm:inline">Barcode HP</span>';
                        btn.classList.replace('bg-rose-600', 'bg-indigo-600');
                    }
                }).catch(() => {});
            } else {
                document.getElementById('cameraScannerContainer').classList.add('hidden');
            }
        }

        // Notifikasi Status
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
            el.classList.remove('hidden');
        }

        function dismissStatusMsg() {
            document.getElementById('scanStatusMsg')?.classList.add('hidden');
        }

        // ==============================================================
        // SUBMIT BATCH PENERIMAAN DRAFT KE SISTEM
        // ==============================================================

        async function submitCompleteReception() {
            const expSelect = document.getElementById('selectExpedition');
            const expedition = expSelect ? expSelect.value.trim() : '';
            if (!expedition) {
                alert('Silakan pilih Ekspedisi Pengantar terlebih dahulu!');
                expSelect?.focus();
                return;
            }

            if (draftPackages.length === 0) {
                alert('Minimal 1 barcode/resi paket harus di-scan ke Draft sebelum submit!');
                document.getElementById('inputPackageBarcode')?.focus();
                return;
            }

            const receiptNo = document.getElementById('inputReceiptNo').value.trim();
            const courierName = document.getElementById('inputCourierName').value.trim();
            const withoutPhotoCount = draftPackages.filter(p => !p.photo).length;

            let confirmMsg = `Simpan serah terima ${draftPackages.length} paket Draft untuk Ekspedisi ${expedition} ke sistem?`;
            if (withoutPhotoCount > 0) {
                confirmMsg = `Perhatian: Ada ${withoutPhotoCount} paket yang belum memiliki foto bukti.\n\nTetap simpan ${draftPackages.length} paket ini ke sistem?`;
            }

            if (!confirm(confirmMsg)) return;

            const btn = document.getElementById('btnSubmitReception');
            const origText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan Seluruh Paket & Foto ke Sistem...';
            }

            try {
                const payload = {
                    receipt_number: receiptNo,
                    expedition: expedition,
                    courier_name: courierName,
                    packages: draftPackages.map(p => ({
                        barcode: p.barcode,
                        photo: p.photo
                    })),
                    photos: draftPackages.filter(p => p.photo).map(p => p.photo)
                };

                const res = await fetch('api/reception.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data && data.success) {
                    showStatusMsg(`✅ Sukses! Penerimaan <b>${data.total_packages} paket</b> (${escapeHtml(data.expedition)}) berhasil disimpan ke sistem! [${escapeHtml(data.receipt_number)}]`, 'success');

                    // Tampilkan Slip Bukti Serah Terima langsung
                    showReceiptModal({
                        receipt_number: data.receipt_number,
                        expedition: data.expedition,
                        courier_name: courierName,
                        total_packages: data.total_packages,
                        created_at: new Date().toLocaleString('id-ID'),
                        packages: draftPackages.map(p => ({ package_barcode: p.barcode, photo_path: p.photo })),
                        photos: data.photos || draftPackages.filter(p => p.photo).map(p => p.photo)
                    });

                    // Bersihkan draft & generate ID baru
                    draftPackages = [];
                    renderDraftList();
                    generateReceiptId();
                    loadHistoryData();
                } else {
                    alert('Gagal menyimpan: ' + (data.error || 'Terjadi kesalahan sistem'));
                }
            } catch (err) {
                console.error('Error submitCompleteReception:', err);
                alert('Terjadi kesalahan saat memproses data: ' + err.message);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origText;
                }
            }
        }

        // Tampilkan Modal Tanda Terima (Slip Cetak)
        function showReceiptModal(data) {
            setText('slipReceiptNo', data.receipt_number || '-');
            setText('slipExpedition', data.expedition || '-');
            setText('slipDateTime', data.created_at || '-');
            setText('slipCourier', data.courier_name || '-');
            setText('slipTotalPackages', data.total_packages || 0);

            const listEl = document.getElementById('slipPackageList');
            if (listEl) {
                let listHtml = '';
                (data.packages || []).forEach((item, i) => {
                    const bCode = (typeof item === 'object') ? (item.package_barcode || item.barcode || '') : item;
                    const pPath = (typeof item === 'object') ? (item.photo_path || item.photo || null) : null;
                    const photoThumb = pPath ? `<img src="${escapeHtml(pPath)}" class="w-7 h-7 rounded-md object-cover border border-slate-200 cursor-pointer" onclick="previewImageDirect('${escapeHtml(pPath)}')">` : '';

                    listHtml += `
                        <div class="flex items-center justify-between border-b border-slate-100 py-1 px-1">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-md bg-slate-200 text-slate-700 text-[10px] font-bold flex items-center justify-center">${i + 1}</span>
                                <span class="font-mono font-bold text-slate-800 text-xs">${escapeHtml(bCode)}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                ${photoThumb}
                                <span class="text-[9px] text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded">TERIMA</span>
                            </div>
                        </div>
                    `;
                });
                listEl.innerHTML = listHtml;
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
                if (photoSection) photoSection.classList.remove('hidden');
                setText('slipPhotoCount', photosToDisplay.length);
                let photoHtml = '';
                photosToDisplay.forEach((pUrl, idx) => {
                    photoHtml += `
                        <div class="rounded-xl overflow-hidden border border-slate-200 aspect-video bg-black cursor-pointer shadow-xs" onclick="previewImageDirect('${escapeHtml(pUrl)}')">
                            <img src="${escapeHtml(pUrl)}" alt="Foto Paket ${idx + 1}" class="w-full h-full object-cover hover:scale-105 transition">
                        </div>
                    `;
                });
                if (photoContainer) photoContainer.innerHTML = photoHtml;
            } else {
                if (photoSection) photoSection.classList.add('hidden');
                if (photoContainer) photoContainer.innerHTML = '';
            }

            const receiptModalEl = document.getElementById('receiptModal');
            if (receiptModalEl) receiptModalEl.classList.remove('hidden');
        }

        function previewImageDirect(url) {
            const modal = document.getElementById('receptionPhotoModal');
            const img = document.getElementById('receptionPhotoModalImg');
            const title = document.getElementById('receptionPhotoModalTitle');
            const dlBtn = document.getElementById('btnDownloadReceptionPhoto');

            if (img) img.src = url;
            setText(title, 'Bukti Foto Serah Terima Paket');
            if (dlBtn) {
                dlBtn.href = url;
                dlBtn.download = `rcv_foto_paket_${Date.now()}.jpg`;
            }

            if (modal) modal.classList.remove('hidden');
        }

        function closeReceiptModal() {
            const el = document.getElementById('receiptModal');
            if (el) el.classList.add('hidden');
        }

        function startNewReceptionAfterSave() {
            closeReceiptModal();
            resetReceptionForm();
        }

        function resetReceptionForm() {
            draftPackages = [];
            const courierInput = document.getElementById('inputCourierName');
            const pkgInput = document.getElementById('inputPackageBarcode');
            if (courierInput) courierInput.value = '';
            if (pkgInput) pkgInput.value = '';
            renderDraftList();
            generateReceiptId();
            dismissStatusMsg();
            pkgInput?.focus();
        }

        // Tab Switcher
        function switchTab(tab) {
            const btnScan = document.getElementById('tabBtnScan');
            const btnHist = document.getElementById('tabBtnHistory');
            const viewScan = document.getElementById('viewScan');
            const viewHist = document.getElementById('viewHistory');

            if (tab === 'scan') {
                if (btnScan) btnScan.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-emerald-600 text-white shadow-sm';
                if (btnHist) btnHist.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
                if (viewScan) viewScan.classList.remove('hidden');
                if (viewHist) viewHist.classList.add('hidden');
                document.getElementById('inputPackageBarcode')?.focus();
            } else {
                if (btnHist) btnHist.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-emerald-600 text-white shadow-sm';
                if (btnScan) btnScan.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
                if (viewScan) viewScan.classList.add('hidden');
                if (viewHist) viewHist.classList.remove('hidden');
                loadHistoryData();
            }
        }

        // Muat Riwayat Penerimaan Hari Ini
        async function loadHistoryData() {
            const dateInput = document.getElementById('historyDateFilter');
            const selectedDate = (dateInput && dateInput.value) ? dateInput.value : new Date().toISOString().slice(0, 10);
            if (dateInput && !dateInput.value) dateInput.value = selectedDate;

            try {
                const res = await fetch(`api/reception.php?action=list&date=${selectedDate}`);
                const data = await res.json();
                const tbody = document.getElementById('historyTableBody');
                const badge = document.getElementById('historyCountBadge');

                if (data && data.success && Array.isArray(data.data)) {
                    setText(badge, data.data.length);
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-slate-400">Belum ada penerimaan ekspedisi pada tanggal ini.</td></tr>';
                        return;
                    }

                    let rows = '';
                    data.data.forEach(item => {
                        const timeOnly = (item.created_at || '').split(' ')[1] || item.created_at;
                        
                        // Cek apakah ada foto
                        let photoBadge = '';
                        let photoCount = 0;
                        if (item.package_photos) {
                            try {
                                const parsed = JSON.parse(item.package_photos);
                                if (Array.isArray(parsed)) photoCount = parsed.length;
                            } catch (e) {}
                        } else if (item.photo_path) {
                            photoCount = 1;
                        }

                        if (photoCount > 0) {
                            photoBadge = `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-[10px] font-bold border border-emerald-200/80 ml-1.5"><i class="fa-solid fa-camera"></i> ${photoCount}</span>`;
                        }

                        rows += `
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-2.5 px-3 font-mono font-bold text-slate-800">
                                    ${escapeHtml(item.receipt_number)}
                                    ${photoBadge}
                                </td>
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

        // Lihat Detail Penerimaan Riwayat
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
                        created_at: data.reception.created_at,
                        photos: data.reception.package_photos || (data.reception.photo_path ? [data.reception.photo_path] : [])
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
