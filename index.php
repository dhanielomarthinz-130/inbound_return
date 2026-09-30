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
<body class="bg-slate-100/90 min-h-screen text-slate-800 antialiased flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <!-- TOP NAVBAR STATION -->
    <header class="bg-slate-900 border-b border-slate-800 text-white shadow-lg sticky top-0 z-30 w-full">
        <div class="w-full px-4 md:px-8 py-3 flex justify-between items-center gap-4">
            
            <!-- Brand / Logo -->
            <div class="flex items-center space-x-3 shrink-0">
                <div class="w-10 h-10 rounded-2xl bg-white p-1.5 flex items-center justify-center shadow-md shadow-black/20 shrink-0">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
                </div>
                <div>
                    <h1 class="font-black text-white text-base md:text-lg leading-tight tracking-tight">Inbound Return Station</h1>
                    <p class="text-[10px] text-slate-400 font-medium hidden sm:block">IEG &bull; Warehouse Station</p>
                </div>
            </div>
            
            <!-- Station Status & User Controls -->
            <div class="flex items-center space-x-2 md:space-x-3">
                <!-- Status Kamera -->
                <span id="cameraStatusBadge" class="hidden sm:inline-flex bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-xs px-3 py-1.5 rounded-xl font-semibold items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Kamera Siap</span>
                </span>

                <!-- Tombol Reset Sesi di Topbar -->
                <button onclick="resetInvoiceSession()" title="Reset Sesi Scan Saat Ini" class="bg-slate-800 hover:bg-slate-700 text-amber-300 hover:text-amber-200 border border-slate-700/80 px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span class="hidden md:inline">Reset Sesi</span>
                </button>

                <!-- Tombol Kembali ke Menu Utama Operator (Hub) -->
                <a href="menu" title="Kembali ke Menu Utama Portal" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-shapes text-indigo-400"></i>
                    <span class="hidden sm:inline">Menu Utama</span>
                </a>

                <!-- Menu Penerimaan Ekspedisi (Mobile & Dok) -->
                <a href="reception" title="Penerimaan Returan dari Ekspedisi (Mobile)" class="bg-emerald-600 hover:bg-emerald-700 text-white border border-emerald-500/50 px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/30">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    <span class="hidden sm:inline">Penerimaan Ekspedisi</span>
                </a>

                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <a href="admin" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-xl font-semibold transition flex items-center gap-1.5 shadow-sm shadow-indigo-600/30">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span class="hidden md:inline">Admin Panel</span>
                </a>
                <?php endif; ?>

                <!-- Profil Operator -->
                <div class="flex items-center gap-2.5 bg-slate-800/90 border border-slate-700/80 px-3 py-1.5 rounded-xl text-xs">
                    <div class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-xs shrink-0">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="flex flex-col text-left leading-none">
                        <span class="font-bold text-slate-100 text-xs" id="displayOperator"><?= htmlspecialchars($user['name']) ?></span>
                        <span class="text-[9px] text-indigo-300 font-mono uppercase mt-0.5 tracking-wider"><?= $user['role'] ?></span>
                    </div>
                </div>

                <!-- Tombol Logout -->
                <a href="logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari sesi ini?')" title="Logout / Keluar" class="w-9 h-9 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white flex items-center justify-center transition shadow-sm">
                    <i class="fa-solid fa-arrow-right-from-bracket text-sm"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN 2-COLUMN WORKSPACE -->
    <main class="w-full max-w-[1700px] mx-auto px-4 md:px-8 py-6 grid grid-cols-1 lg:grid-cols-12 gap-6 flex-1 items-start">

        <!-- ============================================================== -->
        <!-- KOLOM KIRI (4-5 KOLOM): VIDEO KAMERA DOKUMENTASI UNBOXING      -->
        <!-- ============================================================== -->
        <div class="lg:col-span-5 xl:col-span-4 space-y-4">

            <!-- Card Live Video Dokumentasi / Record -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200/90 overflow-hidden">
                <!-- Header Live Video Card -->
                <div class="p-3.5 bg-slate-900 text-white flex justify-between items-center border-b border-slate-800">
                    <div class="flex items-center space-x-2 text-xs font-bold">
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                        <i class="fa-solid fa-video text-rose-400"></i>
                        <span>Live Video Record</span>
                        <span id="cameraRecBadge" class="hidden bg-rose-600 text-white font-mono text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span> REC <span id="cameraRecTime">00:00</span>
                        </span>
                    </div>
                    <button id="btnSwitchCamera" onclick="switchCamera()" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-2.5 py-1 rounded-xl border border-slate-700 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span>Putar Kamera</span>
                    </button>
                </div>

                <!-- Video Viewport Bersih & Responsif (Panjang ke Bawah) -->
                <div class="relative bg-slate-950 flex justify-center items-center w-full h-[520px] sm:h-[600px] lg:h-[680px] overflow-hidden">
                    <video id="liveVideoFeed" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    <!-- Visual Camera Flash Effect -->
                    <div id="cameraFlashOverlay" class="absolute inset-0 bg-white pointer-events-none opacity-0 transition-opacity duration-150 z-20"></div>
                    <div id="cameraLoading" class="absolute inset-0 bg-slate-900/95 flex flex-col items-center justify-center text-white space-y-2.5">
                        <div class="traffic-loader py-2">
                            <div class="traffic-ball traffic-ball-red"></div>
                            <div class="traffic-ball traffic-ball-yellow"></div>
                            <div class="traffic-ball traffic-ball-green"></div>
                        </div>
                        <span class="text-xs font-semibold text-slate-300">Menghubungkan ke kamera video...</span>
                    </div>
                </div>

                <!-- Status Mode Scan & Gun Barcode -->
                <div class="p-3.5 bg-slate-50 border-t border-slate-200 text-xs flex justify-between items-center text-slate-600">
                    <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-700">
                        <i class="fa-solid fa-barcode text-indigo-600 text-sm"></i> Barcode Gun Aktif
                    </span>
                    <span id="scanModeIndicator" class="font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-xl text-[10px] tracking-wide uppercase">
                        LANGKAH 1: SCAN INVOICE
                    </span>
                </div>
            </div>

            <!-- Card Ambil Foto Dokumentasi (Paket & Produk dengan Watermark) -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200/90 p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-xs tracking-tight">Foto Unboxing (Watermark)</h3>
                            <p class="text-[10px] text-slate-400">Jepret foto otomatis dengan watermark</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-lg flex items-center gap-1">
                        <i class="fa-regular fa-keyboard"></i> F2 & F4
                    </span>
                </div>

                <!-- Input File Fallback Tersembunyi (jika kamera offline atau ingin upload file langsung) -->
                <input type="file" id="filePackagePhoto" accept="image/*" class="hidden" onchange="handlePhotoUpload('package', this)">
                <input type="file" id="fileProductPhoto" accept="image/*" class="hidden" onchange="handlePhotoUpload('product', this)">

                <!-- Tombol Shortcut Tuts Keyboard -->
                <div class="grid grid-cols-2 gap-2">
                    <button id="btnCapturePackagePhoto" onclick="capturePackagePhoto()" type="button" 
                        class="flex items-center justify-center gap-2 py-2.5 px-3 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white rounded-2xl font-bold text-xs transition shadow-sm shadow-indigo-600/25">
                        <i class="fa-solid fa-box text-sm"></i>
                        <div class="text-left leading-tight">
                            <span class="block text-[10px] opacity-80 font-mono font-black">TUTS [F2]</span>
                            <span>Foto Paket</span>
                        </div>
                    </button>

                    <button id="btnCaptureProductPhoto" onclick="captureProductPhoto()" type="button" 
                        class="flex items-center justify-center gap-2 py-2.5 px-3 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-2xl font-bold text-xs transition shadow-sm shadow-emerald-600/25">
                        <i class="fa-solid fa-tag text-sm"></i>
                        <div class="text-left leading-tight">
                            <span class="block text-[10px] opacity-80 font-mono font-black">TUTS [F4]</span>
                            <span>Foto Produk</span>
                        </div>
                    </button>
                </div>

                <!-- Preview Foto yang Diambil -->
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <!-- Box Foto Paket -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-2.5 relative overflow-hidden flex flex-col justify-between min-h-[115px]">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[10px] font-bold text-slate-700 flex items-center gap-1">
                                <i class="fa-solid fa-box text-indigo-500"></i> Paket
                            </span>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="document.getElementById('filePackagePhoto').click()" title="Pilih foto paket dari file / galeri" class="text-[10px] text-slate-400 hover:text-indigo-600 p-0.5 rounded transition">
                                    <i class="fa-solid fa-upload"></i>
                                </button>
                                <span id="badgePackagePhoto" class="hidden text-[9px] font-bold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded">SIAP</span>
                            </div>
                        </div>
                        <div id="previewPackagePhotoEmpty" class="flex-1 flex flex-col items-center justify-center text-center py-2 text-slate-300">
                            <i class="fa-regular fa-image text-2xl mb-1 text-slate-300"></i>
                            <span class="text-[10px] text-slate-400 font-medium">Tekan F2</span>
                        </div>
                        <div id="previewPackagePhotoFilled" class="hidden relative group rounded-xl overflow-hidden aspect-video bg-black flex items-center justify-center">
                            <img id="imgPackagePhoto" src="" alt="Foto Paket" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-1.5">
                                <button type="button" onclick="previewImageModal('imgPackagePhoto', 'Foto Paket Unboxing')" title="Lihat Foto Full" class="p-1.5 bg-white/90 text-slate-800 rounded-lg text-xs hover:bg-white"><i class="fa-solid fa-expand"></i></button>
                                <button type="button" onclick="clearPackagePhoto()" title="Hapus / Foto Ulang" class="p-1.5 bg-rose-600 text-white rounded-lg text-xs hover:bg-rose-700"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Box Foto Produk -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-2.5 relative overflow-hidden flex flex-col justify-between min-h-[115px]">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[10px] font-bold text-slate-700 flex items-center gap-1">
                                <i class="fa-solid fa-tag text-emerald-500"></i> Produk
                            </span>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="document.getElementById('fileProductPhoto').click()" title="Pilih foto produk dari file / galeri" class="text-[10px] text-slate-400 hover:text-emerald-600 p-0.5 rounded transition">
                                    <i class="fa-solid fa-upload"></i>
                                </button>
                                <span id="badgeProductPhoto" class="hidden text-[9px] font-bold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded">SIAP</span>
                            </div>
                        </div>
                        <div id="previewProductPhotoEmpty" class="flex-1 flex flex-col items-center justify-center text-center py-2 text-slate-300">
                            <i class="fa-regular fa-image text-2xl mb-1 text-slate-300"></i>
                            <span class="text-[10px] text-slate-400 font-medium">Tekan F4</span>
                        </div>
                        <div id="previewProductPhotoFilled" class="hidden relative group rounded-xl overflow-hidden aspect-video bg-black flex items-center justify-center">
                            <img id="imgProductPhoto" src="" alt="Foto Produk" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-1.5">
                                <button type="button" onclick="previewImageModal('imgProductPhoto', 'Foto Produk Unboxing')" title="Lihat Foto Full" class="p-1.5 bg-white/90 text-slate-800 rounded-lg text-xs hover:bg-white"><i class="fa-solid fa-expand"></i></button>
                                <button type="button" onclick="clearProductPhoto()" title="Hapus / Foto Ulang" class="p-1.5 bg-rose-600 text-white rounded-lg text-xs hover:bg-rose-700"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- KOLOM KANAN (7-8 KOLOM): FORM INPUT & TABEL ITEM              -->
        <!-- ============================================================== -->
        <div class="lg:col-span-7 xl:col-span-8 space-y-5">

            <!-- 1. INPUT NOMOR INVOICE & EKSPEDISI -->
            <div id="sectionInvoice" class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-200/90 transition-all">
                
                <!-- Status Belum Terkunci -->
                <div id="invoiceInputWrapper" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <span class="w-6 h-6 rounded-xl bg-indigo-600 text-white text-xs flex items-center justify-center font-bold shadow-sm shadow-indigo-600/30">1</span>
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pilih Ekspedisi & Scan Nomor Invoice</h2>
                        </div>
                        <span class="text-[11px] text-indigo-600 font-semibold bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-xl flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt"></i> Auto Record saat scan
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <!-- Select Ekspedisi (4 Kolom) -->
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-slate-700 flex items-center gap-1">
                                    <i class="fa-solid fa-truck-fast text-indigo-600"></i>
                                    <span>Ekspedisi *</span>
                                </label>
                                <span id="autoDetectBadge" class="hidden text-[10px] text-emerald-700 bg-emerald-100 border border-emerald-300 px-2 py-0.5 rounded-full font-bold">
                                    <i class="fa-solid fa-wand-magic-sparkles text-emerald-600"></i> <span id="autoDetectLabel">Auto</span>
                                </span>
                            </div>
                            <div class="relative">
                                <select id="selectExpedition" class="w-full pl-3 pr-8 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition appearance-none">
                                    <option value="">-- Pilih Ekspedisi --</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Input Nomor Invoice (8 Kolom) -->
                        <div class="md:col-span-8">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                                <i class="fa-solid fa-barcode text-indigo-600"></i>
                                <span>Nomor Invoice / Resi *</span>
                            </label>
                            <div class="flex space-x-2">
                                <div class="relative flex-1">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 text-sm">
                                        <i class="fa-solid fa-receipt"></i>
                                    </span>
                                    <input type="text" id="inputInvoice" placeholder="Scan barcode invoice atau ketik lalu tekan Enter..."
                                        autocomplete="off"
                                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-sm font-bold font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                                </div>
                                <button id="btnLockInvoice" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-2xl font-bold text-xs transition shadow-md shadow-indigo-600/30 flex items-center gap-1.5 shrink-0">
                                    <span>Lanjut</span>
                                    <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Sudah Terkunci (Banner Hijau Premium) -->
                <div id="invoiceLockedBanner" class="hidden flex items-center justify-between bg-gradient-to-r from-emerald-50 via-teal-50/40 to-emerald-50 border border-emerald-200 p-4 rounded-2xl shadow-2xs">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-lg shadow-md shadow-emerald-600/30 shrink-0">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <div class="text-[10px] text-emerald-700 font-bold uppercase tracking-wider flex items-center gap-1">
                                <i class="fa-solid fa-lock text-[9px]"></i> Sesi Invoice Terkunci & Aktif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                <span class="text-lg font-black text-slate-900 font-mono leading-tight tracking-tight" id="displayActiveInvoice">-</span>
                                <span id="displayActiveExpedition" class="bg-indigo-100 text-indigo-800 text-xs font-bold px-2.5 py-0.5 rounded-lg flex items-center gap-1 border border-indigo-200">
                                    <i class="fa-solid fa-truck-fast text-indigo-600"></i>
                                    <span id="displayExpeditionText">-</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button onclick="resetInvoiceSession()" class="text-xs text-rose-600 hover:text-rose-800 bg-white hover:bg-rose-50 px-3.5 py-2 rounded-xl border border-rose-200 font-bold transition flex items-center gap-1.5 shadow-2xs">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>Ganti Invoice</span>
                    </button>
                </div>

            </div>

            <!-- 2. FORM INPUT PRODUK: BARCODE, BATCH, EXP DATE, QTY, TYPE -->
            <div id="sectionProductInput" class="hidden bg-white rounded-3xl p-5 md:p-6 shadow-md border-2 border-indigo-500/20 ring-4 ring-indigo-500/5 space-y-4 transition-all">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center space-x-2.5">
                        <span class="w-6 h-6 rounded-xl bg-indigo-600 text-white text-xs flex items-center justify-center font-bold shadow-sm shadow-indigo-600/30">2</span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800">Scan Barcode & Detail Produk</h2>
                    </div>
                    <div class="text-[11px] text-slate-400 font-medium">
                        Tekan <kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[10px] font-bold text-slate-600">Enter</kbd> untuk pindah kolom
                    </div>
                </div>

                <form id="formProductEntry" onsubmit="handleAddItem(event)" class="space-y-3.5">
                    
                    <!-- Row 1: Barcode Produk & No. Batch -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                        <!-- Barcode Produk (7 Kolom) -->
                        <div class="sm:col-span-7">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                                <i class="fa-solid fa-barcode text-indigo-600"></i>
                                <span>Scan Barcode Produk *</span>
                            </label>
                            <div class="relative">
                                <input type="text" id="inputBarcode" placeholder="Scan barcode produk..."
                                    autocomplete="off" required
                                    class="w-full pl-3.5 pr-8 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-sm font-mono font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                                <span id="barcodeLoadingIcon" class="hidden absolute right-3 top-3 text-indigo-500">
                                    <i class="fa-solid fa-circle-notch fa-spin text-sm"></i>
                                </span>
                            </div>
                        </div>

                        <!-- No. Batch (5 Kolom) -->
                        <div class="sm:col-span-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                                <i class="fa-solid fa-tag text-indigo-600"></i>
                                <span>No. Batch</span>
                            </label>
                            <input type="text" id="inputBatch" placeholder="Contoh: B260901"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-sm font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- Row 2: Exp Date, Qty, Type -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <!-- Exp Date (4 Kolom) -->
                        <div class="sm:col-span-4">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-slate-700 flex items-center gap-1">
                                    <i class="fa-regular fa-calendar-days text-indigo-600"></i>
                                    <span>Exp Date (dd-mm-yyyy)</span>
                                </label>
                                <span id="autoExpBadge" class="hidden text-[10px] text-emerald-700 bg-emerald-100 border border-emerald-300 px-2 py-0.5 rounded-full font-bold flex items-center gap-1">
                                    <i class="fa-solid fa-wand-magic-sparkles text-emerald-600"></i> <span id="autoExpLabel">Auto</span>
                                </span>
                            </div>
                            <input type="date" id="inputExpDate"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                        </div>

                        <!-- Qty (3 Kolom) -->
                        <div class="sm:col-span-3">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                                <i class="fa-solid fa-box text-indigo-600"></i>
                                <span>Qty Unit *</span>
                            </label>
                            <input type="number" id="inputQty" value="1" min="1" required
                                class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-sm font-bold text-center text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition">
                        </div>

                        <!-- Type (5 Kolom) -->
                        <div class="sm:col-span-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                                <i class="fa-solid fa-clipboard-check text-indigo-600"></i>
                                <span>Type / Kondisi</span>
                            </label>
                            <div class="relative">
                                <select id="inputType" class="w-full pl-3 pr-8 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition appearance-none">
                                    <option value="GOOD">GOOD (Layak Jual)</option>
                                    <option value="RUSAK">RUSAK (Defect)</option>
                                    <option value="EXPIRED">EXPIRED (Kadaluarsa)</option>
                                    <option value="SALAH_KIRIM">SALAH KIRIM</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Status Preview Produk Terdeteksi & Tombol Tambahkan Item -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pt-2 bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                        <div class="text-xs min-w-0 flex-1">
                            <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Produk Terdeteksi:</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span id="detectedProductName" class="font-bold text-indigo-700 text-xs">Silakan scan / ketik barcode...</span>
                            </div>
                            <div id="detectedProductSku" class="mt-1"></div>
                        </div>

                        <button type="submit" id="btnSubmitItem" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-2xl text-xs transition flex items-center justify-center gap-2 shadow-md shadow-indigo-600/30 shrink-0">
                            <i class="fa-solid fa-plus-circle"></i>
                            <span>Tambahkan Item</span>
                        </button>
                    </div>

                </form>

            </div>

            <!-- 3. TABEL DAFTAR BARANG YANG SUDAH TER-INPUT -->
            <div id="sectionItemsList" class="bg-white rounded-3xl shadow-sm border border-slate-200/90 overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-list-check"></i>
                        </div>
                        <h3 class="font-bold text-xs uppercase tracking-wider text-slate-800">
                            Daftar Produk Masuk (<span id="totalItemsBadge" class="text-indigo-600">0</span>)
                        </h3>
                    </div>

                    <div class="flex items-center space-x-2 text-xs">
                        <span class="bg-white border border-slate-200 text-slate-800 px-3 py-1 rounded-xl font-bold shadow-2xs">
                            Total: <span id="summaryTotalUnits" class="text-indigo-600 font-mono font-black">0</span> Pcs
                        </span>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto max-h-[300px]">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-slate-100 text-slate-600 uppercase font-semibold text-[11px] sticky top-0">
                            <tr>
                                <th class="p-3 text-center w-10">#</th>
                                <th class="p-3 whitespace-nowrap">Barcode</th>
                                <th class="p-3">Nama Produk & SKU</th>
                                <th class="p-3 whitespace-nowrap">Batch</th>
                                <th class="p-3 whitespace-nowrap">Exp Date</th>
                                <th class="p-3 text-center whitespace-nowrap">Qty</th>
                                <th class="p-3 text-center whitespace-nowrap">Kondisi</th>
                                <th class="p-3 text-center w-14">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody" class="divide-y divide-slate-100">
                            <tr id="emptyTablePlaceholder">
                                <td colspan="8" class="text-center py-10 text-slate-400 italic">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <i class="fa-solid fa-box-open text-2xl text-slate-300"></i>
                                        <span>Belum ada produk yang dimasukkan untuk invoice ini. Silakan scan barcode di atas.</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Action Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                    <div class="relative w-full sm:w-auto flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs">
                            <i class="fa-regular fa-note-sticky"></i>
                        </span>
                        <input type="text" id="sessionNotesInput" placeholder="Catatan invoice (opsional)..."
                            class="w-full pl-8 pr-3 py-2 border border-slate-300 rounded-2xl text-xs bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                    
                    <button id="btnFinalizeSession" disabled onclick="submitFinalSession()"
                        class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold px-6 py-2.5 rounded-2xl text-xs transition flex items-center justify-center gap-2 shadow-md shadow-emerald-600/25 shrink-0">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Selesaikan Inbound Invoice</span>
                    </button>
                </div>
            </div>

        </div>

    </main>

    <!-- FOOTER STATUS -->
    <footer class="bg-white border-t border-slate-200 py-3 px-4 md:px-8 text-center text-xs text-slate-400 w-full mt-auto">
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
