<?php
// menu.php - Launcher Menu Portal Operator (Gaya iOS / Android App Hub)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inbound Hub • Operator Portal</title>
    
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
                        'ios': '24px',
                        'squircle': '28px'
                    },
                    boxShadow: {
                        'ios-card': '0 20px 40px -15px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.1)',
                        'ios-glow-emerald': '0 15px 35px -5px rgba(16, 185, 129, 0.45)',
                        'ios-glow-indigo': '0 15px 35px -5px rgba(99, 102, 241, 0.45)',
                        'dock': '0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.15)'
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
        /* Smooth backdrop filters & iOS spring animations */
        .glass-panel {
            background: rgba(30, 41, 59, 0.65);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .glass-dock {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.16);
        }
        .squircle-app {
            border-radius: 26px;
            position: relative;
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .squircle-app:hover {
            transform: translateY(-8px) scale(1.02);
        }
        .squircle-app:active {
            transform: scale(0.97);
        }
        .squircle-app::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 48%;
            background: linear-gradient(180deg, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0) 100%);
            border-radius: 26px 26px 0 0;
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
<body class="h-full bg-slate-950 text-slate-100 flex flex-col justify-between selection:bg-indigo-500 selection:text-white antialiased overflow-x-hidden relative">

    <!-- AMBIENT BACKGROUND GLOW (iOS / Material You Wallpaper) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-600/25 rounded-full blur-3xl mesh-glow-1"></div>
        <div class="absolute top-1/3 -right-32 w-[30rem] h-[30rem] bg-indigo-600/25 rounded-full blur-3xl mesh-glow-2"></div>
        <div class="absolute -bottom-32 left-1/4 w-[28rem] h-[28rem] bg-teal-500/20 rounded-full blur-3xl mesh-glow-1"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900/60 via-slate-950 to-black"></div>
    </div>

    <!-- WRAPPER UTAMA -->
    <div class="relative z-10 w-full max-w-5xl mx-auto px-4 sm:px-6 md:px-8 py-3 sm:py-5 flex-1 flex flex-col justify-between">

        <!-- ============================================================== -->
        <!-- 1. STATUS BAR GAYA iOS / ANDROID (TOP BAR)                     -->
        <!-- ============================================================== -->
        <header class="flex items-center justify-between py-2 px-1 text-slate-300 text-xs select-none">
            <!-- Sisi Kiri: Jam Live Digital & Tanggal -->
            <div class="flex items-center gap-2 font-mono">
                <span id="liveClock" class="font-black text-sm text-white tracking-wider">00:00</span>
                <span class="text-slate-500">|</span>
                <span id="liveDate" class="text-[11px] text-slate-400 font-sans hidden sm:inline">Rabu, 30 Sep</span>
            </div>

            <!-- Bagian Tengah: Dynamic Island / Badge Stasiun Inbound -->
            <div class="glass-panel px-3.5 py-1.5 rounded-full flex items-center gap-2 shadow-inner border border-white/10">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-[11px] font-bold text-slate-200 tracking-wide">INBOUND HUB • PT IEG</span>
            </div>

            <!-- Sisi Kanan: Indikator Sinyal, Wifi & Baterai -->
            <div class="flex items-center gap-2.5 text-xs text-slate-300">
                <span class="text-[10px] font-bold text-emerald-400 hidden md:inline">SYSTEM ONLINE</span>
                <i class="fa-solid fa-signal text-[11px]" title="Sinyal Gudang Kuat"></i>
                <i class="fa-solid fa-wifi text-[11px]" title="Wi-Fi Station Aktif"></i>
                <div class="flex items-center gap-1 text-emerald-400">
                    <span class="text-[10px] font-mono font-bold">100%</span>
                    <i class="fa-solid fa-battery-full text-xs"></i>
                </div>
            </div>
        </header>


        <!-- ============================================================== -->
        <!-- 2. GREETING CARD & USER PROFILE WIDGET                         -->
        <!-- ============================================================== -->
        <div class="mt-4 mb-4">
            <div class="glass-panel rounded-ios p-5 sm:p-6 shadow-ios-card relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                
                <div class="flex items-center gap-4">
                    <!-- Avatar Lingkaran Inisial -->
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-emerald-500 via-teal-500 to-indigo-600 p-0.5 shadow-lg shadow-indigo-500/30 shrink-0">
                        <div class="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center font-black text-xl sm:text-2xl text-white tracking-wider">
                            <?= htmlspecialchars($initials ?: 'OP') ?>
                        </div>
                    </div>

                    <!-- Salam & Identitas -->
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                <i class="fa-solid fa-circle-check text-[9px] mr-1"></i> Shift Aktif
                            </span>
                            <span class="text-xs text-slate-400 font-mono"><?= strtoupper($user['role']) ?></span>
                        </div>
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-white mt-1 leading-tight tracking-tight">
                            Halo, <?= htmlspecialchars($user['name']) ?> 👋
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                            Silakan pilih modul kerja di bawah ini untuk memulai proses inbound.
                        </p>
                    </div>
                </div>

                <!-- Mini Stats Pill (iOS Widget Style) -->
                <div class="grid grid-cols-2 sm:grid-cols-2 gap-2.5 shrink-0">
                    <div class="bg-slate-900/80 border border-slate-700/60 rounded-2xl p-3 text-center min-w-[105px]">
                        <span class="text-[10px] text-slate-400 block font-semibold uppercase tracking-wider">Paket Masuk</span>
                        <span class="text-xl sm:text-2xl font-black text-emerald-400 font-mono"><?= number_format($totalReceivedToday) ?></span>
                        <span class="text-[9px] text-slate-500 block">Hari ini</span>
                    </div>
                    <div class="bg-slate-900/80 border border-slate-700/60 rounded-2xl p-3 text-center min-w-[105px]">
                        <span class="text-[10px] text-slate-400 block font-semibold uppercase tracking-wider">Unboxed</span>
                        <span class="text-xl sm:text-2xl font-black text-indigo-400 font-mono"><?= number_format($totalUnboxedToday) ?></span>
                        <span class="text-[9px] text-slate-500 block">Sesi Return</span>
                    </div>
                </div>

            </div>
        </div>


        <!-- ============================================================== -->
        <!-- 3. MAIN MENU LAUNCHER: 2 APLIKASI UTAMA (ANDROID / iOS STYLE)  -->
        <!-- ============================================================== -->
        <div class="my-auto py-2">
            <div class="text-center mb-5 sm:mb-7">
                <span class="text-[11px] font-bold uppercase tracking-widest text-slate-400 bg-white/5 border border-white/10 px-3 py-1 rounded-full">
                    <i class="fa-solid fa-shapes text-indigo-400 mr-1.5"></i> PILIH MODUL OPERASIONAL
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-7 max-w-4xl mx-auto">

                <!-- ------------------------------------------------------ -->
                <!-- MENU 1: INBOUND RECEIVING (PENERIMAAN EKSPEDISI)       -->
                <!-- ------------------------------------------------------ -->
                <a href="reception" class="group squircle-app bg-gradient-to-br from-slate-900/90 via-slate-800/80 to-slate-900/90 border border-emerald-500/30 p-6 sm:p-8 flex flex-col justify-between shadow-ios-card hover:shadow-ios-glow-emerald cursor-pointer transition-all duration-300">
                    
                    <div class="flex items-start justify-between gap-4 mb-6">
                        <!-- Icon App Squircle iOS -->
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-squircle bg-gradient-to-tr from-emerald-600 via-teal-500 to-emerald-400 p-0.5 shadow-xl shadow-emerald-500/30 group-hover:scale-105 transition-transform duration-300 shrink-0 flex items-center justify-center relative overflow-hidden">
                            <div class="w-full h-full bg-gradient-to-b from-emerald-500 to-teal-700 rounded-[26px] flex items-center justify-center">
                                <i class="fa-solid fa-truck-ramp-box text-3xl sm:text-4xl text-white drop-shadow-md"></i>
                            </div>
                            <!-- Kilauan Glossy -->
                            <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/25 to-white/40 pointer-events-none"></div>
                        </div>

                        <!-- Badge Status & Shortcut Info -->
                        <div class="flex flex-col items-end gap-1.5">
                            <span class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/40">
                                MODUL 01
                            </span>
                            <span class="text-[10px] font-mono text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded-lg border border-emerald-500/20">
                                📸 Tuts TAB = Foto
                            </span>
                        </div>
                    </div>

                    <!-- Keterangan Menu -->
                    <div class="space-y-2 mb-6">
                        <div class="flex items-center gap-2">
                            <h2 class="text-2xl sm:text-3xl font-black text-white group-hover:text-emerald-300 transition-colors">
                                Inbound Receiving
                            </h2>
                            <i class="fa-solid fa-arrow-right text-emerald-400 text-sm group-hover:translate-x-1 transition-transform"></i>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-300 font-medium">
                            Penerimaan & serah terima tumpukan paket dari kurir ekspedisi.
                        </p>

                        <!-- Poin Fitur Cepat -->
                        <div class="grid grid-cols-2 gap-2 pt-3 text-[11px] text-slate-400">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-emerald-400 text-xs"></i>
                                <span>Scan Barcode Cepat</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-camera text-emerald-400 text-xs"></i>
                                <span>Foto Bukti Fisik (Tab)</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-stamp text-emerald-400 text-xs"></i>
                                <span>Watermark Otomatis</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-print text-emerald-400 text-xs"></i>
                                <span>Cetak Tanda Terima</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Action Utama -->
                    <div class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white rounded-2xl font-black text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/30">
                        <span>Buka Inbound Receiving</span>
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </div>

                </a>


                <!-- ------------------------------------------------------ -->
                <!-- MENU 2: INBOUND UNBOXING (SCAN & BUKA RETUR)           -->
                <!-- ------------------------------------------------------ -->
                <a href="scanner" class="group squircle-app bg-gradient-to-br from-slate-900/90 via-slate-800/80 to-slate-900/90 border border-indigo-500/30 p-6 sm:p-8 flex flex-col justify-between shadow-ios-card hover:shadow-ios-glow-indigo cursor-pointer transition-all duration-300">
                    
                    <div class="flex items-start justify-between gap-4 mb-6">
                        <!-- Icon App Squircle iOS -->
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-squircle bg-gradient-to-tr from-indigo-600 via-blue-500 to-indigo-400 p-0.5 shadow-xl shadow-indigo-500/30 group-hover:scale-105 transition-transform duration-300 shrink-0 flex items-center justify-center relative overflow-hidden">
                            <div class="w-full h-full bg-gradient-to-b from-indigo-600 to-blue-800 rounded-[26px] flex items-center justify-center">
                                <i class="fa-solid fa-box-open text-3xl sm:text-4xl text-white drop-shadow-md"></i>
                            </div>
                            <!-- Kilauan Glossy -->
                            <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/25 to-white/40 pointer-events-none"></div>
                        </div>

                        <!-- Badge Status & Shortcut Info -->
                        <div class="flex flex-col items-end gap-1.5">
                            <span class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-400/40">
                                MODUL 02
                            </span>
                            <span class="text-[10px] font-mono text-indigo-400 bg-indigo-950/80 px-2 py-0.5 rounded-lg border border-indigo-500/20">
                                📷 Tuts F2 & F4 = Foto
                            </span>
                        </div>
                    </div>

                    <!-- Keterangan Menu -->
                    <div class="space-y-2 mb-6">
                        <div class="flex items-center gap-2">
                            <h2 class="text-2xl sm:text-3xl font-black text-white group-hover:text-indigo-300 transition-colors">
                                Inbound Unboxing
                            </h2>
                            <i class="fa-solid fa-arrow-right text-indigo-400 text-sm group-hover:translate-x-1 transition-transform"></i>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-300 font-medium">
                            Validasi invoice SAP, unboxing fisik paket, dan verifikasi produk.
                        </p>

                        <!-- Poin Fitur Cepat -->
                        <div class="grid grid-cols-2 gap-2 pt-3 text-[11px] text-slate-400">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-video text-indigo-400 text-xs"></i>
                                <span>Rekam Video Unboxing</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-camera text-indigo-400 text-xs"></i>
                                <span>Foto Paket & Produk</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-barcode text-indigo-400 text-xs"></i>
                                <span>Scan Barcode Gun</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-clipboard-check text-indigo-400 text-xs"></i>
                                <span>Kondisi & Disposisi</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Action Utama -->
                    <div class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white rounded-2xl font-black text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/30">
                        <span>Buka Inbound Unboxing</span>
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </div>

                </a>

            </div>
        </div>


        <!-- ============================================================== -->
        <!-- 4. BOTTOM FLOATING DOCK (GAYA iOS / ANDROID NAVIGATION BAR)   -->
        <!-- ============================================================== -->
        <footer class="mt-6 mb-2 flex justify-center">
            <nav class="glass-dock rounded-full px-4 sm:px-6 py-2.5 sm:py-3 shadow-dock flex items-center gap-2 sm:gap-4 border border-white/15">
                
                <!-- Tab Menu Aktif -->
                <a href="menu" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/15 text-white text-xs font-bold transition shadow-sm">
                    <i class="fa-solid fa-table-cells-large text-sm text-indigo-400"></i>
                    <span class="hidden sm:inline">Menu Utama</span>
                </a>

                <div class="h-5 w-px bg-white/15 mx-1"></div>

                <!-- Shortcut Cepat Receiving -->
                <a href="reception" title="Buka Inbound Receiving" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-slate-300 hover:text-white hover:bg-white/10 text-xs font-semibold transition">
                    <i class="fa-solid fa-truck-ramp-box text-emerald-400"></i>
                    <span class="hidden md:inline">Receiving</span>
                </a>

                <!-- Shortcut Cepat Unboxing -->
                <a href="scanner" title="Buka Inbound Unboxing" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-slate-300 hover:text-white hover:bg-white/10 text-xs font-semibold transition">
                    <i class="fa-solid fa-box-open text-indigo-400"></i>
                    <span class="hidden md:inline">Unboxing</span>
                </a>

                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <div class="h-5 w-px bg-white/15 mx-1"></div>
                <!-- Admin Panel Switcher -->
                <a href="admin" title="Kembali ke Panel Admin" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-amber-300 hover:text-amber-200 hover:bg-amber-500/10 text-xs font-semibold transition">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span class="hidden sm:inline">Admin Panel</span>
                </a>
                <?php endif; ?>

                <div class="h-5 w-px bg-white/15 mx-1"></div>

                <!-- Tombol Logout Station -->
                <a href="logout" onclick="return confirm('Keluar dari sesi Operator ini?')" title="Logout / Keluar" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 text-xs font-semibold transition">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span class="hidden sm:inline">Keluar</span>
                </a>

            </nav>
        </footer>

    </div>

    <!-- SCRIPT JAM REAL-TIME DIGITAL -->
    <script>
        function updateLiveClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const clockEl = document.getElementById('liveClock');
            if (clockEl) {
                clockEl.innerText = `${hours}:${minutes}`;
            }

            const dateEl = document.getElementById('liveDate');
            if (dateEl) {
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                const dayName = days[now.getDay()];
                const dateNum = now.getDate();
                const monthName = months[now.getMonth()];
                dateEl.innerText = `${dayName}, ${dateNum} ${monthName}`;
            }
        }

        setInterval(updateLiveClock, 1000);
        updateLiveClock();
    </script>
</body>
</html>
