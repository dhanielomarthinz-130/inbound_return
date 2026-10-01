<?php
require_once __DIR__ . '/config.php';
$currentUser = getSessionUser();
checkMaintenanceMode($pdo, $currentUser);
$user = requireLogin(['operator', 'admin', 'superadmin']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbound Return Station</title>
    <!-- Favicon Huruf D Warna Hijau -->
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- HTML5-QRCode Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= file_exists(__DIR__ . '/assets/css/custom.css') ? filemtime(__DIR__ . '/assets/css/custom.css') : time() ?>">
</head>
<body class="bg-slate-100/90 h-screen max-h-screen text-slate-800 antialiased flex flex-col overflow-hidden selection:bg-indigo-500 selection:text-white">

    <!-- TOP NAVBAR STATION (COMPACT & SLEEK) -->
    <header class="bg-slate-900 border-b border-slate-800 text-white shadow-md sticky top-0 z-30 w-full shrink-0">
        <div class="w-full px-3 md:px-6 py-2 flex justify-between items-center gap-3">
            
            <!-- Brand / Logo -->
            <div class="flex items-center space-x-2.5 shrink-0">
                <div class="w-8 h-8 rounded-xl bg-white p-1 flex items-center justify-center shadow-md shadow-black/20 shrink-0">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
                </div>
                <div>
                    <h1 class="font-black text-white text-sm md:text-base leading-tight tracking-tight">Inbound Return Station</h1>
                    <p class="text-[9px] text-slate-400 font-medium hidden sm:block">IEG &bull; Warehouse Station</p>
                </div>
            </div>
            
            <!-- Station Status & User Controls -->
            <div class="flex items-center space-x-1.5 md:space-x-2.5">
                <!-- Status Kamera -->
                <span id="cameraStatusBadge" class="hidden sm:inline-flex bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-[11px] px-2.5 py-1 rounded-lg font-semibold items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Kamera Siap</span>
                </span>

                <!-- Tombol Reset Sesi di Topbar -->
                <button onclick="resetInvoiceSession()" title="Reset Sesi Scan Saat Ini" class="bg-slate-800 hover:bg-slate-700 text-amber-300 hover:text-amber-200 border border-slate-700/80 px-2.5 py-1 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span class="hidden md:inline">Reset Sesi</span>
                </button>

                <!-- Tombol Kembali ke Menu Utama Operator (Hub) -->
                <a href="menu" title="Kembali ke Menu Utama Portal" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 px-2.5 py-1 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-shapes text-indigo-400"></i>
                    <span class="hidden sm:inline">Menu Utama</span>
                </a>

                <!-- Menu Penerimaan Ekspedisi (Mobile & Dok) -->
                <a href="reception" title="Penerimaan Returan dari Ekspedisi (Mobile)" class="bg-emerald-600 hover:bg-emerald-700 text-white border border-emerald-500/50 px-2.5 py-1 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/30">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    <span class="hidden sm:inline">Penerimaan Ekspedisi</span>
                </a>

                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <a href="admin" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-2.5 py-1 rounded-lg font-semibold transition flex items-center gap-1.5 shadow-sm shadow-indigo-600/30">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span class="hidden md:inline">Admin Panel</span>
                </a>
                <?php endif; ?>

                <!-- Profil Operator -->
                <div class="flex items-center gap-2 bg-slate-800/90 border border-slate-700/80 px-2.5 py-1 rounded-lg text-xs">
                    <div class="w-5 h-5 rounded-md bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-[10px] shrink-0">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="flex flex-col text-left leading-none">
                        <span class="font-bold text-slate-100 text-xs" id="displayOperator"><?= htmlspecialchars($user['name']) ?></span>
                        <span class="text-[8px] text-indigo-300 font-mono uppercase mt-0.5 tracking-wider"><?= $user['role'] ?></span>
                    </div>
                </div>

                <!-- Tombol Logout -->
                <a href="logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari sesi ini?')" title="Logout / Keluar" class="w-8 h-8 rounded-lg bg-rose-600/80 hover:bg-rose-600 text-white flex items-center justify-center transition shadow-sm text-xs">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN 2-COLUMN WORKSPACE (FITS TO SCREEN, ZERO OVERFLOW) -->
    <main class="flex-1 w-full max-w-[1750px] mx-auto p-2.5 sm:p-3.5 grid grid-cols-1 lg:grid-cols-12 gap-3 min-h-0 overflow-hidden items-stretch">

        <!-- ============================================================== -->
        <!-- KOLOM KIRI (4-5 KOLOM): VIDEO KAMERA DOKUMENTASI UNBOXING      -->
        <!-- ============================================================== -->
        <div class="lg:col-span-5 xl:col-span-4 flex flex-col gap-2.5 min-h-0 h-full overflow-hidden">

            <!-- Card Live Video Dokumentasi / Record -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 overflow-hidden shrink-0">
                <!-- Header Live Video Card -->
                <div class="p-2.5 bg-slate-900 text-white flex justify-between items-center border-b border-slate-800">
                    <div class="flex items-center space-x-2 text-xs font-bold">
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                        <i class="fa-solid fa-video text-rose-400"></i>
                        <span>Live Video Record</span>
                        <span id="cameraRecBadge" class="hidden bg-rose-600 text-white font-mono text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span> REC <span id="cameraRecTime">00:00</span>
                        </span>
                    </div>
                    <button id="btnSwitchCamera" onclick="switchCamera()" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-2 py-1 rounded-lg border border-slate-700 transition flex items-center gap-1">
                        <i class="fa-solid fa-arrows-rotate text-[11px]"></i>
                        <span>Putar</span>
                    </button>
                </div>

                <!-- Video Viewport Bersih & Responsif (Tinggi Pas & Ergonomis di Desktop) -->
                <div class="relative bg-slate-950 flex justify-center items-center w-full h-[155px] sm:h-[185px] xl:h-[210px] 2xl:h-[240px] ws-video-box overflow-hidden">
                    <video id="liveVideoFeed" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    <!-- Visual Camera Flash Effect -->
                    <div id="cameraFlashOverlay" class="absolute inset-0 bg-white pointer-events-none opacity-0 transition-opacity duration-150 z-20"></div>
                    <div id="cameraLoading" class="absolute inset-0 bg-slate-900/95 flex flex-col items-center justify-center text-white space-y-2">
                        <div class="traffic-loader py-1.5">
                            <div class="traffic-ball traffic-ball-red"></div>
                            <div class="traffic-ball traffic-ball-yellow"></div>
                            <div class="traffic-ball traffic-ball-green"></div>
                        </div>
                        <span class="text-xs font-semibold text-slate-300">Menghubungkan ke kamera video...</span>
                    </div>
                </div>

                <!-- Status Mode Scan & Gun Barcode -->
                <div class="p-2 bg-slate-50 border-t border-slate-200 text-xs flex justify-between items-center text-slate-600">
                    <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-700">
                        <i class="fa-solid fa-barcode text-indigo-600 text-sm"></i> Barcode Gun Aktif
                    </span>
                    <span id="scanModeIndicator" class="font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-md text-[10px] tracking-wide uppercase">
                        LANGKAH 1: SCAN INVOICE
                    </span>
                </div>
            </div>

            <!-- Card Ambil Foto Dokumentasi (Multiple Foto Paket & Produk dengan Watermark) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/90 p-3 flex flex-col flex-1 min-h-0 overflow-hidden gap-2">
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5 shrink-0">
                    <div class="flex items-center gap-1.5">
                        <div class="w-6 h-6 rounded-lg bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-xs tracking-tight">Foto Bukti Unboxing</h3>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span id="badgeTotalPhotos" class="text-[10px] font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-md">
                            0 Foto
                        </span>
                        <button type="button" onclick="clearAllPhotos()" id="btnClearAllPhotos" title="Hapus semua foto" class="hidden text-[10px] text-rose-600 hover:text-rose-700 font-bold bg-rose-50 hover:bg-rose-100 border border-rose-200 px-2 py-0.5 rounded-md transition flex items-center gap-1">
                            <i class="fa-solid fa-trash-can"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- Input File Multiple Fallback Tersembunyi -->
                <input type="file" id="filePhotosUpload" accept="image/*" multiple class="hidden" onchange="handlePhotosMultipleUpload(this)">

                <!-- Tombol Shortcut Tuts Keyboard -->
                <div class="grid grid-cols-2 gap-2 shrink-0">
                    <button id="btnCapturePackagePhoto" onclick="capturePackagePhoto()" type="button" 
                        class="flex items-center justify-center gap-2 py-1.5 px-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white rounded-xl font-bold text-xs transition shadow-sm shadow-indigo-600/25">
                        <i class="fa-solid fa-box text-xs"></i>
                        <div class="text-left leading-tight">
                            <span class="block text-[9px] opacity-80 font-mono font-black">TUTS [F2]</span>
                            <span>+ Foto Paket</span>
                        </div>
                    </button>

                    <button id="btnCaptureProductPhoto" onclick="captureProductPhoto()" type="button" 
                        class="flex items-center justify-center gap-2 py-1.5 px-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl font-bold text-xs transition shadow-sm shadow-emerald-600/25">
                        <i class="fa-solid fa-tag text-xs"></i>
                        <div class="text-left leading-tight">
                            <span class="block text-[9px] opacity-80 font-mono font-black">TUTS [F4]</span>
                            <span>+ Foto Produk</span>
                        </div>
                    </button>
                </div>

                <!-- Opsi Tambah dari File & Info -->
                <div class="flex items-center justify-between text-[10px] text-slate-500 shrink-0">
                    <span class="text-slate-400">Tekan F2/F4 untuk ambil foto</span>
                    <button type="button" onclick="document.getElementById('filePhotosUpload').click()" title="Unggah foto dari galeri / komputer" class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1 hover:underline">
                        <i class="fa-solid fa-upload"></i> Upload File
                    </button>
                </div>

                <!-- Galeri Daftar Foto yang Sudah Diambil -->
                <div id="photosGalleryContainer" class="flex-1 min-h-0 overflow-y-auto custom-scrollbar">
                    <div id="photosEmptyState" class="bg-slate-50 border border-slate-200/90 rounded-xl p-2.5 text-center text-slate-400 flex flex-col items-center justify-center h-full min-h-[60px]">
                        <i class="fa-regular fa-images text-base text-slate-300 mb-0.5"></i>
                        <span class="text-[10px] font-medium text-slate-500">Belum ada foto</span>
                    </div>
                    <div id="photosGridList" class="hidden grid grid-cols-2 sm:grid-cols-3 gap-1.5 p-0.5"></div>
                </div>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- KOLOM KANAN (7-8 KOLOM): FORM INPUT & TABEL ITEM              -->
        <!-- ============================================================== -->
        <div class="lg:col-span-7 xl:col-span-8 flex flex-col gap-2.5 sm:gap-3 min-h-0 h-full overflow-hidden">

            <!-- 1. INPUT NOMOR INVOICE & EKSPEDISI -->
            <div id="sectionInvoice" class="bg-white rounded-2xl p-2.5 sm:p-3 shadow-sm border border-slate-200/90 shrink-0 transition-all">
                
                <!-- Status Belum Terkunci -->
                <div id="invoiceInputWrapper" class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span class="w-5 h-5 rounded-lg bg-indigo-600 text-white text-[11px] flex items-center justify-center font-bold shadow-sm shadow-indigo-600/30">1</span>
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pilih Ekspedisi & Scan Nomor Invoice</h2>
                        </div>
                        <span class="text-[10px] text-indigo-600 font-semibold bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-lg flex items-center gap-1">
                            <i class="fa-solid fa-bolt text-[10px]"></i> Auto Record
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2.5 items-end">
                        <!-- Select Ekspedisi (4 Kolom) -->
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[11px] font-bold text-slate-700 flex items-center gap-1">
                                    <i class="fa-solid fa-truck-fast text-indigo-600"></i>
                                    <span>Ekspedisi *</span>
                                </label>
                                <span id="autoDetectBadge" class="hidden text-[9px] text-emerald-700 bg-emerald-100 border border-emerald-300 px-1.5 py-0.5 rounded-full font-bold">
                                    <i class="fa-solid fa-wand-magic-sparkles text-emerald-600"></i> <span id="autoDetectLabel">Auto</span>
                                </span>
                            </div>
                            <div class="relative">
                                <select id="selectExpedition" class="w-full pl-2.5 pr-7 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition appearance-none">
                                    <option value="">-- Pilih Ekspedisi --</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Input Nomor Invoice (8 Kolom) -->
                        <div class="md:col-span-8">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 flex items-center gap-1">
                                <i class="fa-solid fa-barcode text-indigo-600"></i>
                                <span>Nomor Invoice / Resi *</span>
                            </label>
                            <div class="flex space-x-2">
                                <div class="relative flex-1">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs">
                                        <i class="fa-solid fa-receipt"></i>
                                    </span>
                                    <input type="text" id="inputInvoice" placeholder="Scan barcode invoice atau ketik lalu tekan Enter..."
                                        autocomplete="off"
                                        class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm font-bold font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                                </div>
                                <button id="btnLockInvoice" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-xl font-bold text-xs transition shadow-sm shadow-indigo-600/30 flex items-center gap-1 shrink-0">
                                    <span>Lanjut</span>
                                    <i class="fa-solid fa-arrow-right text-[11px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Sudah Terkunci (Banner Hijau Premium Compact) -->
                <div id="invoiceLockedBanner" class="hidden flex items-center justify-between bg-gradient-to-r from-emerald-50 via-teal-50/40 to-emerald-50 border border-emerald-200 p-2 sm:p-2.5 rounded-xl shadow-2xs">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shadow-md shadow-emerald-600/30 shrink-0">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <div class="text-[9px] text-emerald-700 font-bold uppercase tracking-wider flex items-center gap-1">
                                <i class="fa-solid fa-lock text-[8px]"></i> Sesi Invoice Terkunci
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                <span class="text-sm sm:text-base font-black text-slate-900 font-mono leading-tight tracking-tight" id="displayActiveInvoice">-</span>
                                <span id="displayActiveExpedition" class="bg-indigo-100 text-indigo-800 text-[11px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1 border border-indigo-200">
                                    <i class="fa-solid fa-truck-fast text-indigo-600 text-[10px]"></i>
                                    <span id="displayExpeditionText">-</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button onclick="resetInvoiceSession()" class="text-xs text-rose-600 hover:text-rose-800 bg-white hover:bg-rose-50 px-2.5 py-1.5 rounded-lg border border-rose-200 font-bold transition flex items-center gap-1 shadow-2xs">
                        <i class="fa-solid fa-rotate-left text-[11px]"></i>
                        <span>Ganti Invoice</span>
                    </button>
                </div>

            </div>

            <!-- 2. FORM INPUT PRODUK: BARCODE, BATCH, EXP DATE, QTY, TYPE -->
            <div id="sectionProductInput" class="hidden bg-white rounded-2xl p-2.5 sm:p-3 shadow-sm border border-slate-200/90 space-y-2 shrink-0 transition-all">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                    <div class="flex items-center space-x-2">
                        <span class="w-5 h-5 rounded-lg bg-indigo-600 text-white text-[11px] flex items-center justify-center font-bold shadow-sm shadow-indigo-600/30">2</span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800">Scan Barcode & Detail Produk</h2>
                    </div>
                    <div class="text-[10px] text-slate-400 font-medium">
                        Tekan <kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[9px] font-bold text-slate-600">Enter</kbd> untuk pindah kolom
                    </div>
                </div>

                <form id="formProductEntry" onsubmit="handleAddItem(event)" class="space-y-2">
                    
                    <!-- Row 1: Barcode Produk & No. Batch -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5">
                        <!-- Barcode Produk (7 Kolom) -->
                        <div class="sm:col-span-7">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 flex items-center gap-1">
                                <i class="fa-solid fa-barcode text-indigo-600"></i>
                                <span>Scan Barcode Produk *</span>
                            </label>
                            <div class="relative">
                                <input type="text" id="inputBarcode" placeholder="Scan barcode produk..."
                                    autocomplete="off" required
                                    class="w-full pl-3 pr-8 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm font-mono font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                                <span id="barcodeLoadingIcon" class="hidden absolute right-2.5 top-2 text-indigo-500">
                                    <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                </span>
                            </div>
                        </div>

                        <!-- No. Batch (5 Kolom) -->
                        <div class="sm:col-span-5">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[11px] font-bold text-slate-700 flex items-center gap-1">
                                    <i class="fa-solid fa-tag text-indigo-600"></i>
                                    <span>No. Batch</span>
                                </label>
                                <button type="button" onclick="toggleVirtualKeyboard()" id="btnToggleVK"
                                    title="Tampilkan / Sembunyikan Keyboard Touchscreen"
                                    class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded-lg border border-indigo-200 transition flex items-center gap-1 shadow-2xs cursor-pointer">
                                    <i class="fa-solid fa-keyboard"></i>
                                    <span id="btnToggleVKLabel">Tutup Keyboard</span>
                                </button>
                            </div>
                            <input type="text" id="inputBatch" placeholder="Contoh: B260901"
                                autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- VIRTUAL KEYBOARD TOUCHSCREEN KHUSUS NO. BATCH & TOMBOL ENTER TERPISAH -->
                    <div id="virtualKeyboardContainer" class="bg-slate-900 text-white rounded-2xl p-2.5 sm:p-3 shadow-lg border border-slate-800 space-y-2 transition-all">
                        <div class="flex items-center justify-between border-b border-slate-800/80 pb-1.5">
                            <div class="flex items-center gap-1.5">
                                <span class="w-4 h-4 rounded-md bg-indigo-600 text-white flex items-center justify-center text-[10px]">
                                    <i class="fa-solid fa-keyboard"></i>
                                </span>
                                <span class="text-[11px] font-bold text-slate-200 tracking-wide">Keyboard Touchscreen &bull; Batch</span>
                            </div>
                            
                            <!-- Presets Cepat Format Batch -->
                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="text-[9px] text-slate-400 font-semibold mr-0.5 hidden sm:inline">Preset:</span>
                                <button type="button" onclick="vkPressChar('B')" class="px-1.5 py-0.5 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-slate-200 hover:text-white rounded text-[10px] font-mono font-bold transition">B</button>
                                <button type="button" onclick="vkPressChar('LOT')" class="px-1.5 py-0.5 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-slate-200 hover:text-white rounded text-[10px] font-mono font-bold transition">LOT</button>
                                <button type="button" onclick="vkPressChar('EXP')" class="px-1.5 py-0.5 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-slate-200 hover:text-white rounded text-[10px] font-mono font-bold transition">EXP</button>
                                <button type="button" onclick="vkPressChar('2025')" class="px-1.5 py-0.5 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-slate-200 hover:text-white rounded text-[10px] font-mono font-bold transition">2025</button>
                                <button type="button" onclick="vkPressChar('2026')" class="px-1.5 py-0.5 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-slate-200 hover:text-white rounded text-[10px] font-mono font-bold transition">2026</button>
                                <button type="button" onclick="vkPressChar('2027')" class="px-1.5 py-0.5 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-slate-200 hover:text-white rounded text-[10px] font-mono font-bold transition">2027</button>
                                <button type="button" onclick="toggleVirtualKeyboard(false)" class="text-slate-400 hover:text-rose-400 text-[10px] px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 transition ml-1 font-medium">✕ Tutup</button>
                            </div>
                        </div>

                        <!-- Row 1: Angka 1-0 dan tanda hubung -->
                        <div class="grid grid-cols-12 gap-1">
                            <button type="button" onclick="vkPressChar('1')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">1</button>
                            <button type="button" onclick="vkPressChar('2')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">2</button>
                            <button type="button" onclick="vkPressChar('3')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">3</button>
                            <button type="button" onclick="vkPressChar('4')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">4</button>
                            <button type="button" onclick="vkPressChar('5')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">5</button>
                            <button type="button" onclick="vkPressChar('6')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">6</button>
                            <button type="button" onclick="vkPressChar('7')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">7</button>
                            <button type="button" onclick="vkPressChar('8')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">8</button>
                            <button type="button" onclick="vkPressChar('9')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">9</button>
                            <button type="button" onclick="vkPressChar('0')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">0</button>
                            <button type="button" onclick="vkPressChar('-')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-slate-300 font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">-</button>
                            <button type="button" onclick="vkPressChar('/')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-slate-300 font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">/</button>
                        </div>

                        <!-- Row 2: Huruf Q - P -->
                        <div class="grid grid-cols-10 gap-1">
                            <button type="button" onclick="vkPressChar('Q')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">Q</button>
                            <button type="button" onclick="vkPressChar('W')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">W</button>
                            <button type="button" onclick="vkPressChar('E')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">E</button>
                            <button type="button" onclick="vkPressChar('R')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">R</button>
                            <button type="button" onclick="vkPressChar('T')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">T</button>
                            <button type="button" onclick="vkPressChar('Y')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">Y</button>
                            <button type="button" onclick="vkPressChar('U')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">U</button>
                            <button type="button" onclick="vkPressChar('I')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">I</button>
                            <button type="button" onclick="vkPressChar('O')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">O</button>
                            <button type="button" onclick="vkPressChar('P')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">P</button>
                        </div>

                        <!-- Row 3: Huruf A - L -->
                        <div class="grid grid-cols-9 gap-1 px-2 sm:px-4">
                            <button type="button" onclick="vkPressChar('A')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">A</button>
                            <button type="button" onclick="vkPressChar('S')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">S</button>
                            <button type="button" onclick="vkPressChar('D')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">D</button>
                            <button type="button" onclick="vkPressChar('F')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">F</button>
                            <button type="button" onclick="vkPressChar('G')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">G</button>
                            <button type="button" onclick="vkPressChar('H')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">H</button>
                            <button type="button" onclick="vkPressChar('J')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">J</button>
                            <button type="button" onclick="vkPressChar('K')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">K</button>
                            <button type="button" onclick="vkPressChar('L')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">L</button>
                        </div>

                        <!-- Row 4: Huruf Z - M -->
                        <div class="grid grid-cols-7 gap-1 px-4 sm:px-8">
                            <button type="button" onclick="vkPressChar('Z')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">Z</button>
                            <button type="button" onclick="vkPressChar('X')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">X</button>
                            <button type="button" onclick="vkPressChar('C')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">C</button>
                            <button type="button" onclick="vkPressChar('V')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">V</button>
                            <button type="button" onclick="vkPressChar('B')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">B</button>
                            <button type="button" onclick="vkPressChar('N')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">N</button>
                            <button type="button" onclick="vkPressChar('M')" class="h-8 bg-slate-800 hover:bg-indigo-600 active:scale-95 text-white font-mono font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">M</button>
                        </div>

                        <!-- Row 5: Action Buttons (Backspace, Clear, Spasi, and PROMINENT SEPARATED ENTER BUTTON) -->
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-1.5 pt-0.5">
                            <button type="button" onclick="vkBackspace()" title="Hapus karakter terakhir"
                                class="px-2.5 sm:px-3 h-8.5 bg-rose-950/70 hover:bg-rose-700 active:scale-95 text-rose-200 hover:text-white font-bold text-[11px] rounded-xl flex items-center gap-1 transition select-none cursor-pointer border border-rose-800/40">
                                <i class="fa-solid fa-delete-left text-xs"></i>
                                <span>Hapus</span>
                            </button>
                            <button type="button" onclick="vkClear()" title="Kosongkan seluruh kolom batch"
                                class="px-2.5 sm:px-3 h-8.5 bg-slate-800 hover:bg-slate-700 active:scale-95 text-slate-300 hover:text-white font-bold text-[11px] rounded-xl flex items-center gap-1 transition select-none cursor-pointer">
                                <i class="fa-regular fa-trash-can"></i>
                                <span>Clear</span>
                            </button>
                            <button type="button" onclick="vkPressChar(' ')" title="Tambah spasi"
                                class="px-2.5 sm:px-3 h-8.5 bg-slate-800 hover:bg-slate-700 active:scale-95 text-slate-300 hover:text-white font-bold text-[11px] rounded-xl flex items-center gap-1 transition select-none cursor-pointer">
                                <span>Spasi</span>
                            </button>
                            
                            <!-- TOMBOL ENTER TERPISAH (BESAR & SANGAT JELAS) -->
                            <button type="button" onclick="vkEnter()" id="btnVkEnter"
                                class="flex-1 min-w-[170px] h-8.5 bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 transition shadow-md shadow-emerald-950/40 select-none cursor-pointer border border-emerald-300/40">
                                <kbd class="px-1.5 py-0.5 bg-emerald-800/90 border border-emerald-400/40 rounded font-mono font-black text-[10px] text-white shadow-xs">↵ ENTER</kbd>
                                <span>PINDAH KE EXP DATE ➔</span>
                            </button>
                        </div>
                    </div>

                    <!-- Row 2: Exp Date, Qty, Type -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                        <!-- Exp Date (4 Kolom) -->
                        <div class="sm:col-span-4">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[11px] font-bold text-slate-700 flex items-center gap-1">
                                    <i class="fa-regular fa-calendar-days text-indigo-600"></i>
                                    <span>Exp Date (dd-mm-yyyy)</span>
                                </label>
                                <div class="flex items-center gap-1">
                                    <span id="autoExpBadge" class="hidden text-[9px] text-emerald-700 bg-emerald-100 border border-emerald-300 px-1.5 py-0.5 rounded-full font-bold flex items-center gap-1">
                                        <i class="fa-solid fa-wand-magic-sparkles text-emerald-600"></i> <span id="autoExpLabel">Auto</span>
                                    </span>
                                    <button type="button" onclick="toggleNumpadExpDate()" id="btnToggleNP"
                                        title="Buka / Tutup Numpad Tanggal Touchscreen"
                                        class="text-[10px] font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-1.5 py-0.5 rounded-lg border border-emerald-200 transition flex items-center gap-1 shadow-2xs cursor-pointer">
                                        <i class="fa-solid fa-calculator"></i>
                                        <span id="btnToggleNPLabel">Numpad</span>
                                    </button>
                                </div>
                            </div>
                            <input type="date" id="inputExpDate"
                                class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                        </div>

                        <!-- Qty (3 Kolom) -->
                        <div class="sm:col-span-3">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 flex items-center gap-1">
                                <i class="fa-solid fa-box text-indigo-600"></i>
                                <span>Qty Unit *</span>
                            </label>
                            <input type="number" id="inputQty" value="1" min="1" required
                                class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm font-bold text-center text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                        </div>

                        <!-- Type (5 Kolom) -->
                        <div class="sm:col-span-5">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 flex items-center gap-1">
                                <i class="fa-solid fa-clipboard-check text-indigo-600"></i>
                                <span>Type / Kondisi</span>
                            </label>
                            <div class="relative">
                                <select id="inputType" class="w-full pl-2.5 pr-7 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition appearance-none">
                                    <option value="GOOD">GOOD (Layak Jual)</option>
                                    <option value="RUSAK">RUSAK (Defect)</option>
                                    <option value="EXPIRED">EXPIRED (Kadaluarsa)</option>
                                    <option value="SALAH_KIRIM">SALAH KIRIM</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- NUMPAD TOUCHSCREEN KHUSUS EXP DATE -->
                    <div id="numpadExpDateContainer" class="hidden bg-slate-900 text-white rounded-2xl p-2.5 sm:p-3 shadow-lg border border-slate-800 space-y-2 transition-all">
                        <div class="flex items-center justify-between border-b border-slate-800/80 pb-1.5">
                            <div class="flex items-center gap-1.5">
                                <span class="w-4 h-4 rounded-md bg-emerald-600 text-white flex items-center justify-center text-[10px]">
                                    <i class="fa-solid fa-calculator"></i>
                                </span>
                                <span class="text-[11px] font-bold text-slate-200 tracking-wide">Numpad Touchscreen &bull; Exp Date</span>
                                <span id="numpadDateDisplay" class="ml-1.5 px-2 py-0.5 bg-slate-800 border border-slate-700 rounded-lg text-emerald-400 font-mono text-[11px] font-bold tracking-wider">dd - mm - yyyy</span>
                            </div>
                            
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="toggleNumpadExpDate(false)" class="text-slate-400 hover:text-rose-400 text-[10px] px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 transition font-medium">✕ Tutup</button>
                            </div>
                        </div>

                        <!-- Quick Year Shortcuts -->
                        <div class="flex items-center gap-1 flex-wrap">
                            <span class="text-[9px] text-slate-400 font-semibold mr-0.5">Tahun:</span>
                            <button type="button" onclick="npSetYear('2025')" class="px-2 py-0.5 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-slate-200 hover:text-white rounded-lg text-[11px] font-mono font-bold transition">2025</button>
                            <button type="button" onclick="npSetYear('2026')" class="px-2 py-0.5 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-slate-200 hover:text-white rounded-lg text-[11px] font-mono font-bold transition">2026</button>
                            <button type="button" onclick="npSetYear('2027')" class="px-2 py-0.5 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-slate-200 hover:text-white rounded-lg text-[11px] font-mono font-bold transition">2027</button>
                            <button type="button" onclick="npSetYear('2028')" class="px-2 py-0.5 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-slate-200 hover:text-white rounded-lg text-[11px] font-mono font-bold transition">2028</button>
                            <button type="button" onclick="npSetYear('2029')" class="px-2 py-0.5 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-slate-200 hover:text-white rounded-lg text-[11px] font-mono font-bold transition">2029</button>
                            <button type="button" onclick="npSetPreset('1Y')" class="px-1.5 py-0.5 bg-indigo-950/70 hover:bg-indigo-600 active:scale-95 text-indigo-300 hover:text-white rounded-lg text-[10px] font-bold transition border border-indigo-800/40">+1 Thn</button>
                            <button type="button" onclick="npSetPreset('2Y')" class="px-1.5 py-0.5 bg-indigo-950/70 hover:bg-indigo-600 active:scale-95 text-indigo-300 hover:text-white rounded-lg text-[10px] font-bold transition border border-indigo-800/40">+2 Thn</button>
                            <button type="button" onclick="npSetPreset('3Y')" class="px-1.5 py-0.5 bg-indigo-950/70 hover:bg-indigo-600 active:scale-95 text-indigo-300 hover:text-white rounded-lg text-[10px] font-bold transition border border-indigo-800/40">+3 Thn</button>
                        </div>

                        <!-- Numpad Grid & Controls -->
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                            <!-- Digit Buttons (1-9, Clear, 0, Backspace) - 7 Kolom -->
                            <div class="sm:col-span-7 grid grid-cols-3 gap-1">
                                <button type="button" onclick="npDigit('1')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">1</button>
                                <button type="button" onclick="npDigit('2')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">2</button>
                                <button type="button" onclick="npDigit('3')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">3</button>
                                <button type="button" onclick="npDigit('4')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">4</button>
                                <button type="button" onclick="npDigit('5')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">5</button>
                                <button type="button" onclick="npDigit('6')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">6</button>
                                <button type="button" onclick="npDigit('7')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">7</button>
                                <button type="button" onclick="npDigit('8')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">8</button>
                                <button type="button" onclick="npDigit('9')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">9</button>
                                <button type="button" onclick="npClear()" class="h-8 bg-slate-800 hover:bg-slate-700 active:scale-95 text-slate-300 font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none">Clear</button>
                                <button type="button" onclick="npDigit('0')" class="h-8 bg-slate-800 hover:bg-emerald-600 active:scale-95 text-white font-mono font-bold text-sm rounded-lg flex items-center justify-center transition shadow-2xs select-none">0</button>
                                <button type="button" onclick="npBackspace()" class="h-8 bg-rose-950/70 hover:bg-rose-700 active:scale-95 text-rose-200 font-bold text-xs rounded-lg flex items-center justify-center transition shadow-2xs select-none border border-rose-800/40"><i class="fa-solid fa-delete-left"></i></button>
                            </div>

                            <!-- Tombol Lanjut / Enter Terpisah (5 Kolom) -->
                            <div class="sm:col-span-5 flex flex-col gap-1.5">
                                <div class="text-[10px] text-slate-400 bg-slate-800/60 p-2 rounded-lg border border-slate-800">
                                    <div class="font-bold text-slate-300 mb-0.5">Petunjuk Numpad:</div>
                                    Ketik tanggal (cth: <b class="text-emerald-400 font-mono">200926</b>) atau pilih tahun cepat di atas.
                                </div>

                                <button type="button" onclick="npEnter()" id="btnNpEnter"
                                    class="w-full h-9 bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 transition shadow-md shadow-emerald-950/40 select-none cursor-pointer border border-emerald-300/40">
                                    <kbd class="px-1.5 py-0.5 bg-emerald-800/90 border border-emerald-400/40 rounded font-mono font-black text-[10px] text-white shadow-xs">↵ ENTER</kbd>
                                    <span>PINDAH KE QTY ➔</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar: Tombol ENTER Terpisah & Tombol Tambah Item -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 pt-0.5">
                        <!-- Tombol ENTER Terpisah (Besar & Responsif untuk Touchscreen / Tanpa Keyboard Fisik) -->
                        <button type="button" onclick="triggerVirtualEnter()" id="btnVirtualEnter"
                            class="sm:col-span-7 bg-slate-800 hover:bg-slate-900 active:scale-98 text-white font-bold py-2 px-3 rounded-xl text-xs transition shadow-sm flex items-center justify-between group border border-slate-700 cursor-pointer">
                            <span class="flex items-center gap-1.5">
                                <kbd class="px-1.5 py-0.5 bg-slate-700 group-hover:bg-indigo-600 rounded-md font-mono text-[10px] font-black tracking-wider text-white shadow-xs transition">↵ ENTER</kbd>
                                <span class="font-bold text-xs">Pindah Kolom</span>
                            </span>
                            <span id="virtualEnterTargetLabel" class="text-[10px] font-mono text-slate-300 group-hover:text-indigo-200 transition">
                                Lanjut ke No. Batch ➔
                            </span>
                        </button>

                        <!-- Tombol Tambahkan Item -->
                        <button type="submit" id="btnSubmitItem"
                            class="sm:col-span-5 bg-indigo-600 hover:bg-indigo-700 active:scale-98 text-white font-bold py-2 px-3 rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-sm shadow-indigo-600/30 cursor-pointer">
                            <i class="fa-solid fa-plus-circle text-xs"></i>
                            <span>Tambahkan Item</span>
                        </button>
                    </div>

                    <!-- Status Preview Produk Terdeteksi -->
                    <div class="text-[11px] bg-slate-50 p-2 rounded-xl border border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-1.5">
                        <div class="min-w-0 flex-1">
                            <span class="text-slate-400 font-bold uppercase text-[9px] tracking-wider block">Produk Terdeteksi:</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span id="detectedProductName" class="font-bold text-indigo-700 text-xs">Silakan scan / ketik barcode...</span>
                            </div>
                            <div id="detectedProductSku" class="mt-0.5"></div>
                        </div>
                    </div>

                </form>

            </div>

            <!-- 3. TABEL DAFTAR BARANG YANG SUDAH TER-INPUT (FLEX-1 INTERNAL SCROLL) -->
            <div id="sectionItemsList" class="bg-white rounded-2xl shadow-sm border border-slate-200/90 flex flex-col flex-1 min-h-0 overflow-hidden">
                <div class="py-2 px-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center shrink-0">
                    <div class="flex items-center space-x-1.5">
                        <div class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-list-check"></i>
                        </div>
                        <h3 class="font-bold text-xs uppercase tracking-wider text-slate-800">
                            Daftar Produk Masuk (<span id="totalItemsBadge" class="text-indigo-600">0</span>)
                        </h3>
                    </div>

                    <div class="flex items-center space-x-1.5 text-xs">
                        <span class="bg-white border border-slate-200 text-slate-800 px-2.5 py-0.5 rounded-lg font-bold shadow-2xs text-xs">
                            Total: <span id="summaryTotalUnits" class="text-indigo-600 font-mono font-black">0</span> Pcs
                        </span>
                    </div>
                </div>

                <!-- Table Content (Internal Scroll Tanpa Menggeser Halaman) -->
                <div class="overflow-x-auto overflow-y-auto flex-1 min-h-[70px] custom-scrollbar">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[10px] sticky top-0 z-10 shadow-2xs">
                            <tr>
                                <th class="p-2 text-center w-8">#</th>
                                <th class="p-2 whitespace-nowrap">Barcode</th>
                                <th class="p-2">Nama Produk & SKU</th>
                                <th class="p-2 whitespace-nowrap">Batch</th>
                                <th class="p-2 whitespace-nowrap">Exp Date</th>
                                <th class="p-2 text-center whitespace-nowrap">Qty</th>
                                <th class="p-2 text-center whitespace-nowrap">Kondisi</th>
                                <th class="p-2 text-center w-12">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody" class="divide-y divide-slate-100">
                            <tr id="emptyTablePlaceholder">
                                <td colspan="8" class="text-center py-6 text-slate-400 italic">
                                    <div class="flex flex-col items-center justify-center space-y-1">
                                        <i class="fa-solid fa-box-open text-xl text-slate-300"></i>
                                        <span class="text-xs">Belum ada produk yang dimasukkan untuk invoice ini. Silakan scan barcode di atas.</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Action Footer (Locked at bottom of card) -->
                <div class="p-2 sm:p-2.5 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-2 shrink-0">
                    <div class="relative w-full sm:w-auto flex-1">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 text-xs">
                            <i class="fa-regular fa-note-sticky"></i>
                        </span>
                        <input type="text" id="sessionNotesInput" placeholder="Catatan invoice (opsional)..."
                            class="w-full pl-7 pr-2.5 py-1.5 border border-slate-300 rounded-xl text-xs bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                    
                    <button id="btnFinalizeSession" disabled onclick="submitFinalSession()"
                        class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold px-4 py-1.5 rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-sm shadow-emerald-600/25 shrink-0 cursor-pointer">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Selesaikan Inbound Invoice</span>
                    </button>
                </div>
            </div>

        </div>

    </main>

    <!-- FOOTER STATUS (Hidden on desktop to preserve single-screen fit) -->
    <footer class="hidden">
        <span>&copy; <?= date('Y') ?> Inbound Return Station &bull; IEG</span>
    </footer>

    <!-- MODAL PREVIEW FOTO WATERMARK -->
    <div id="photoPreviewModal" class="fixed inset-0 bg-black/85 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-4 space-y-3 shadow-2xl animate-in fade-in zoom-in duration-150">
            <div class="flex justify-between items-center text-white px-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    <h4 class="text-xs font-bold tracking-tight" id="photoPreviewModalTitle">Preview Foto Watermark</h4>
                </div>
                <button onclick="closePhotoPreviewModal()" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center text-sm transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="rounded-2xl overflow-hidden bg-black flex items-center justify-center max-h-[75vh] border border-slate-800">
                <img id="photoPreviewModalImg" src="" class="max-w-full max-h-[75vh] object-contain">
            </div>
        </div>
    </div>

    <!-- MODAL KONFIRMASI / SUKSES -->
    <div id="successModal" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 text-center shadow-2xl space-y-4 animate-in fade-in zoom-in duration-200 border border-slate-100">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl mx-auto shadow-sm">
                <i class="fa-solid fa-check"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 tracking-tight">Inbound Return Berhasil!</h3>
            <p class="text-xs text-slate-500" id="modalSuccessDesc">Data return invoice dan rekaman video telah tersimpan ke sistem.</p>
            <div class="pt-2">
                <button onclick="resetInvoiceSession()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-2xl text-xs transition shadow-md shadow-indigo-600/30 flex items-center justify-center gap-2">
                    <span>Scan Invoice Selanjutnya</span>
                    <i class="fa-solid fa-arrow-right"></i>
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
                <h4 class="font-bold text-base text-slate-800" id="globalLoadingTitle">Menyimpan Transaksi...</h4>
                <p class="text-xs text-slate-500 leading-relaxed" id="globalLoadingDesc">Mohon tunggu, sedang memproses data retur ke server.</p>
            </div>
        </div>
    </div>

    <script src="assets/js/toast.js?v=<?= file_exists(__DIR__ . '/assets/js/toast.js') ? filemtime(__DIR__ . '/assets/js/toast.js') : time() ?>"></script>
    <script src="assets/js/operator.js?v=<?= file_exists(__DIR__ . '/assets/js/operator.js') ? filemtime(__DIR__ . '/assets/js/operator.js') : time() ?>"></script>
</body>
</html>
