<?php
// menu.php - Launcher Menu Portal Operator (Desktop & Workstation Optimized)
require_once __DIR__ . '/config.php';

// Pastikan hanya operator, admin, atau superadmin yang sudah login dapat mengakses
$user = requireLogin(['operator', 'admin', 'superadmin']);

// Ambil Statistik Hari Ini untuk Widget
$todayDate = date('Y-m-d');
$totalReceivedToday = 0;
$totalUnboxedToday = 0;
$totalItemsToday = 0;

try {
    $stmtRec = $pdo->prepare("SELECT COALESCE(SUM(total_packages), 0) FROM expedition_receptions WHERE DATE(created_at) = ?");
    $stmtRec->execute([$todayDate]);
    $totalReceivedToday = (int)$stmtRec->fetchColumn();

    $stmtUnbox = $pdo->prepare("SELECT COUNT(*) FROM return_sessions WHERE DATE(created_at) = ?");
    $stmtUnbox->execute([$todayDate]);
    $totalUnboxedToday = (int)$stmtUnbox->fetchColumn();

    $stmtItems = $pdo->prepare("SELECT COUNT(*) FROM return_items WHERE DATE(created_at) = ?");
    $stmtItems->execute([$todayDate]);
    $totalItemsToday = (int)$stmtItems->fetchColumn();
} catch (Exception $e) {
    // Fail silently jika ada error query
}

// Inisial Nama Operator
$nameParts = explode(' ', trim($user['name']));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbound Hub • Operator Portal</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Inter', 'SF Pro Display', '-apple-system', 'sans-serif'],
                        mono: ['JetBrains Mono', 'Menlo', 'monospace']
                    },
                    borderRadius: {
                        'ios': '20px',
                        'squircle': '24px'
                    },
                    boxShadow: {
                        'ios-card': '0 20px 40px -15px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.08)',
                        'ios-glow-emerald': '0 15px 35px -5px rgba(16, 185, 129, 0.40)',
                        'ios-glow-indigo': '0 15px 35px -5px rgba(99, 102, 241, 0.40)',
                        'dock': '0 20px 40px -10px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.12)'
                    }
                }
            }
        };
    </script>

    <!-- Font Awesome 6 Free -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        .glass-panel {
            background: rgba(15, 23, 42, 0.70);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }
        .glass-dock {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }
        .squircle-app {
            border-radius: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .squircle-app:hover {
            transform: translateY(-6px);
        }
        .squircle-app:active {
            transform: scale(0.98);
        }
        .squircle-app::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 42%;
            background: linear-gradient(180deg, rgba(255,255,255,0.14) 0%, rgba(255,255,255,0) 100%);
            border-radius: 24px 24px 0 0;
            pointer-events: none;
        }

        /* Pulse glow background */
        @keyframes floatMesh {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(30px, -20px) scale(1.08); }
        }
        .mesh-glow-1 {
            animation: floatMesh 14s ease-in-out infinite alternate;
        }
        .mesh-glow-2 {
            animation: floatMesh 18s ease-in-out infinite alternate-reverse;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between selection:bg-indigo-500 selection:text-white antialiased overflow-x-hidden relative">

    <!-- AMBIENT BACKGROUND GLOW -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-600/20 rounded-full blur-3xl mesh-glow-1"></div>
        <div class="absolute top-1/3 -right-32 w-[32rem] h-[32rem] bg-indigo-600/20 rounded-full blur-3xl mesh-glow-2"></div>
        <div class="absolute -bottom-32 left-1/4 w-[30rem] h-[30rem] bg-teal-500/15 rounded-full blur-3xl mesh-glow-1"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900/70 via-slate-950 to-black"></div>
    </div>

    <!-- ============================================================== -->
    <!-- DESKTOP TOP NAVBAR (BERSIH, ELEGAN, ENTERPRISE)                -->
    <!-- ============================================================== -->
    <header class="relative z-20 bg-slate-900/80 backdrop-blur-md border-b border-slate-800/90 text-white shadow-md w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center gap-4">
            
            <!-- Brand / Logo -->
            <div class="flex items-center space-x-3.5">
                <div class="w-10 h-10 rounded-2xl bg-white p-1.5 flex items-center justify-center shadow-md shadow-black/20 shrink-0">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-black text-white text-base md:text-lg leading-tight tracking-tight">Inbound Hub</h1>
                        <span class="bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>Online</span>
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-medium">PT. Indo Express Global &bull; Warehouse Operation</p>
                </div>
            </div>

            <!-- Sisi Kanan: Jam Live, Profile & Navigasi -->
            <div class="flex items-center space-x-3 md:space-x-4">
                
                <!-- Live Clock & Date di Desktop -->
                <div class="hidden sm:flex items-center gap-2.5 bg-slate-800/80 border border-slate-700/80 px-3.5 py-1.5 rounded-xl text-xs font-mono text-slate-300">
                    <i class="fa-regular fa-clock text-indigo-400 text-xs"></i>
                    <span id="liveClock" class="font-bold text-white text-xs">--:--:--</span>
                    <span class="text-slate-600">|</span>
                    <span id="liveDate" class="text-[11px] text-slate-300 font-sans">--</span>
                </div>

                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <a href="admin" title="Buka Panel Admin" class="bg-indigo-600/90 hover:bg-indigo-600 text-white text-xs px-3 py-1.5 rounded-xl font-bold transition flex items-center gap-1.5 shadow-sm shadow-indigo-600/30">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span class="hidden md:inline">Admin Panel</span>
                </a>
                <?php endif; ?>

                <!-- User Profile Badge -->
                <div class="flex items-center gap-2.5 bg-slate-800/90 border border-slate-700/80 px-3 py-1.5 rounded-xl text-xs">
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-emerald-500 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                        <?= htmlspecialchars($initials ?: 'OP') ?>
                    </div>
                    <div class="flex flex-col text-left leading-none">
                        <span class="font-bold text-slate-100 text-xs"><?= htmlspecialchars($user['name']) ?></span>
                        <span class="text-[9px] text-indigo-300 font-mono uppercase mt-0.5 tracking-wider"><?= $user['role'] ?></span>
                    </div>
                </div>

                <!-- Tombol Logout -->
                <a href="logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari sesi ini?')" title="Logout / Keluar" class="w-9 h-9 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white flex items-center justify-center transition shadow-sm">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                </a>
            </div>

        </div>
    </header>


    <!-- ============================================================== -->
    <!-- MAIN WORKSPACE CONTENT                                         -->
    <!-- ============================================================== -->
    <main class="relative z-10 w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8 flex-1 flex flex-col justify-center">

        <!-- 1. GREETING CARD & KPI SUMMARY -->
        <div class="mb-6 md:mb-8">
            <div class="glass-panel rounded-ios p-5 sm:p-7 shadow-ios-card relative overflow-hidden flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                
                <div class="flex items-center gap-4 sm:gap-5">
                    <!-- Avatar Lingkaran Inisial -->
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-emerald-500 via-teal-500 to-indigo-600 p-0.5 shadow-lg shadow-indigo-500/25 shrink-0">
                        <div class="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center font-black text-xl sm:text-2xl text-white tracking-wider">
                            <?= htmlspecialchars($initials ?: 'OP') ?>
                        </div>
                    </div>

                    <!-- Salam & Identitas -->
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Shift Operasional Aktif
                            </span>
                            <span class="text-xs text-slate-400 font-mono"><?= strtoupper($user['role']) ?></span>
                        </div>
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-white mt-1 leading-tight tracking-tight">
                            Halo, <?= htmlspecialchars($user['name']) ?> 👋
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-xl">
                            Pilih modul kerja di bawah ini untuk memulai pencatatan penerimaan paket atau proses unboxing return.
                        </p>
                    </div>
                </div>

                <!-- Live KPI Performance Hari Ini (3 Box Desktop) -->
                <div class="grid grid-cols-3 gap-3 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-800">
                    <div class="bg-slate-900/80 border border-slate-700/60 rounded-2xl p-3 sm:p-3.5 text-center min-w-[95px] sm:min-w-[110px]">
                        <span class="text-[10px] text-slate-400 block font-bold uppercase tracking-wider">Paket Masuk</span>
                        <span class="text-xl sm:text-2xl font-black text-emerald-400 font-mono mt-0.5 block"><?= number_format($totalReceivedToday) ?></span>
                        <span class="text-[9px] text-slate-500 block">Penerimaan</span>
                    </div>
                    <div class="bg-slate-900/80 border border-slate-700/60 rounded-2xl p-3 sm:p-3.5 text-center min-w-[95px] sm:min-w-[110px]">
                        <span class="text-[10px] text-slate-400 block font-bold uppercase tracking-wider">Unboxed</span>
                        <span class="text-xl sm:text-2xl font-black text-indigo-400 font-mono mt-0.5 block"><?= number_format($totalUnboxedToday) ?></span>
                        <span class="text-[9px] text-slate-500 block">Sesi Selesai</span>
                    </div>
                    <div class="bg-slate-900/80 border border-slate-700/60 rounded-2xl p-3 sm:p-3.5 text-center min-w-[95px] sm:min-w-[110px]">
                        <span class="text-[10px] text-slate-400 block font-bold uppercase tracking-wider">Total Item</span>
                        <span class="text-xl sm:text-2xl font-black text-amber-400 font-mono mt-0.5 block"><?= number_format($totalItemsToday) ?></span>
                        <span class="text-[9px] text-slate-500 block">Produk Fisik</span>
                    </div>
                </div>

            </div>
        </div>


        <!-- 2. MAIN WORKSTATION LAUNCHER: 2 MODUL UTAMA -->
        <div>
            <div class="flex items-center justify-between mb-4 px-1">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-indigo-500"></div>
                    <span class="text-xs font-black uppercase tracking-wider text-slate-300">PILIH MODUL OPERASIONAL</span>
                </div>
                <span class="text-[11px] text-slate-500 font-medium">Klik salah satu modul untuk mulai bekerja</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">

                <!-- ------------------------------------------------------ -->
                <!-- MODUL 01: INBOUND RECEIVING (PENERIMAAN EKSPEDISI)     -->
                <!-- ------------------------------------------------------ -->
                <a href="reception" class="group squircle-app bg-gradient-to-br from-slate-900/90 via-slate-800/80 to-slate-900/90 border border-emerald-500/30 p-6 sm:p-8 flex flex-col justify-between shadow-ios-card hover:shadow-ios-glow-emerald cursor-pointer transition-all duration-300">
                    
                    <div>
                        <div class="flex items-start justify-between gap-4 mb-5">
                            <!-- Icon Squircle -->
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-squircle bg-gradient-to-tr from-emerald-600 via-teal-500 to-emerald-400 p-0.5 shadow-lg shadow-emerald-500/25 group-hover:scale-105 transition-transform duration-300 shrink-0 flex items-center justify-center relative overflow-hidden">
                                <div class="w-full h-full bg-gradient-to-b from-emerald-500 to-teal-700 rounded-[22px] flex items-center justify-center">
                                    <i class="fa-solid fa-truck-ramp-box text-2xl sm:text-3xl text-white drop-shadow-md"></i>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/20 to-white/35 pointer-events-none"></div>
                            </div>

                            <!-- Badge Modul & Shortcut -->
                            <div class="flex flex-col items-end gap-1.5">
                                <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                                    MODUL 01
                                </span>
                                <span class="text-[10px] font-mono text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded-lg border border-emerald-500/20">
                                    📸 Tuts [TAB] = Foto
                                </span>
                            </div>
                        </div>

                        <!-- Keterangan Modul -->
                        <div class="space-y-1.5 mb-5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-xl sm:text-2xl font-black text-white group-hover:text-emerald-300 transition-colors">
                                    Inbound Receiving
                                </h3>
                                <i class="fa-solid fa-arrow-right text-emerald-400 text-xs sm:text-sm group-hover:translate-x-1 transition-transform"></i>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-300 font-medium leading-relaxed">
                                Penerimaan paket dari kurir ekspedisi, scan resi massal, dan foto bukti tumpukan serah terima.
                            </p>
                        </div>

                        <!-- Fitur Highlight -->
                        <div class="grid grid-cols-2 gap-2 text-xs text-slate-300 mb-6 bg-slate-950/40 p-3 rounded-2xl border border-white/5">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-emerald-400 text-[11px]"></i>
                                <span>Scan Barcode Resi</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-camera text-emerald-400 text-[11px]"></i>
                                <span>Foto Bukti Fisik [TAB]</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-stamp text-emerald-400 text-[11px]"></i>
                                <span>Watermark Ekspedisi</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-print text-emerald-400 text-[11px]"></i>
                                <span>Cetak Tanda Terima</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Action -->
                    <div class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white rounded-xl font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-md shadow-emerald-600/30">
                        <span>Buka Inbound Receiving</span>
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </div>

                </a>


                <!-- ------------------------------------------------------ -->
                <!-- MODUL 02: INBOUND UNBOXING (SCAN & BUKA RETUR)         -->
                <!-- ------------------------------------------------------ -->
                <a href="scanner" class="group squircle-app bg-gradient-to-br from-slate-900/90 via-slate-800/80 to-slate-900/90 border border-indigo-500/30 p-6 sm:p-8 flex flex-col justify-between shadow-ios-card hover:shadow-ios-glow-indigo cursor-pointer transition-all duration-300">
                    
                    <div>
                        <div class="flex items-start justify-between gap-4 mb-5">
                            <!-- Icon Squircle -->
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-squircle bg-gradient-to-tr from-indigo-600 via-blue-500 to-indigo-400 p-0.5 shadow-lg shadow-indigo-500/25 group-hover:scale-105 transition-transform duration-300 shrink-0 flex items-center justify-center relative overflow-hidden">
                                <div class="w-full h-full bg-gradient-to-b from-indigo-600 to-blue-800 rounded-[22px] flex items-center justify-center">
                                    <i class="fa-solid fa-box-open text-2xl sm:text-3xl text-white drop-shadow-md"></i>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/20 to-white/35 pointer-events-none"></div>
                            </div>

                            <!-- Badge Modul & Shortcut -->
                            <div class="flex flex-col items-end gap-1.5">
                                <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                                    MODUL 02
                                </span>
                                <span class="text-[10px] font-mono text-indigo-400 bg-indigo-950/80 px-2 py-0.5 rounded-lg border border-indigo-500/20">
                                    📷 Tuts [F2] & [F4]
                                </span>
                            </div>
                        </div>

                        <!-- Keterangan Modul -->
                        <div class="space-y-1.5 mb-5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-xl sm:text-2xl font-black text-white group-hover:text-indigo-300 transition-colors">
                                    Inbound Unboxing
                                </h3>
                                <i class="fa-solid fa-arrow-right text-indigo-400 text-xs sm:text-sm group-hover:translate-x-1 transition-transform"></i>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-300 font-medium leading-relaxed">
                                Validasi resi/invoice, rekam video dokumentasi unboxing, foto produk, dan cek kondisi barang.
                            </p>
                        </div>

                        <!-- Fitur Highlight -->
                        <div class="grid grid-cols-2 gap-2 text-xs text-slate-300 mb-6 bg-slate-950/40 p-3 rounded-2xl border border-white/5">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-video text-indigo-400 text-[11px]"></i>
                                <span>Rekam Video Unboxing</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-camera text-indigo-400 text-[11px]"></i>
                                <span>Foto Paket [F2] & Produk [F4]</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-barcode text-indigo-400 text-[11px]"></i>
                                <span>Barcode Gun Scanning</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-clipboard-check text-indigo-400 text-[11px]"></i>
                                <span>Cek Tipe & Disposisi</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Action -->
                    <div class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white rounded-xl font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-md shadow-indigo-600/30">
                        <span>Buka Inbound Unboxing</span>
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </div>

                </a>

            </div>
        </div>

    </main>


    <!-- ============================================================== -->
    <!-- DESKTOP FOOTER / NAVIGATION BAR                                -->
    <!-- ============================================================== -->
    <footer class="relative z-20 py-4 px-4 sm:px-6 lg:px-8 border-t border-slate-800/80 bg-slate-950/80">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                <span>&copy; <?= date('Y') ?> Inbound Return System &bull; PT. Indo Express Global</span>
            </div>

            <!-- Quick Navigation Pills -->
            <div class="flex items-center gap-2">
                <a href="reception" class="hover:text-emerald-400 transition px-2.5 py-1 rounded-lg hover:bg-white/5 flex items-center gap-1.5">
                    <i class="fa-solid fa-truck-ramp-box text-emerald-400"></i>
                    <span>Receiving</span>
                </a>
                <span class="text-slate-700">&bull;</span>
                <a href="scanner" class="hover:text-indigo-400 transition px-2.5 py-1 rounded-lg hover:bg-white/5 flex items-center gap-1.5">
                    <i class="fa-solid fa-box-open text-indigo-400"></i>
                    <span>Unboxing</span>
                </a>
                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <span class="text-slate-700">&bull;</span>
                <a href="admin" class="hover:text-indigo-400 transition px-2.5 py-1 rounded-lg hover:bg-white/5 flex items-center gap-1.5">
                    <i class="fa-solid fa-chart-pie text-indigo-400"></i>
                    <span>Admin Panel</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <!-- SCRIPT JAM REAL-TIME DIGITAL -->
    <script>
        function updateLiveClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const clockEl = document.getElementById('liveClock');
            if (clockEl) {
                clockEl.innerText = `${hours}:${minutes}:${seconds} WIB`;
            }

            const dateEl = document.getElementById('liveDate');
            if (dateEl) {
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                const dayName = days[now.getDay()];
                const dateNum = now.getDate();
                const monthName = months[now.getMonth()];
                const year = now.getFullYear();
                dateEl.innerText = `${dayName}, ${dateNum} ${monthName} ${year}`;
            }
        }

        setInterval(updateLiveClock, 1000);
        updateLiveClock();
    </script>
</body>
</html>
