<?php
// menu.php - Launcher Portal Operator Bergaya Aplikasi Mobile Android (Material You & App Hub)
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
    <title>Inbound Hub • PT IEG</title>
    
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
                        sans: ['Plus Jakarta Sans', 'Roboto', 'Inter', '-apple-system', 'sans-serif'],
                        mono: ['JetBrains Mono', 'Menlo', 'monospace']
                    },
                    borderRadius: {
                        'android': '28px',
                        'squircle': '24px'
                    },
                    boxShadow: {
                        'android-card': '0 12px 30px -10px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.08)',
                        'android-glow-emerald': '0 14px 28px -6px rgba(16, 185, 129, 0.45)',
                        'android-glow-indigo': '0 14px 28px -6px rgba(99, 102, 241, 0.45)',
                        'bottom-nav': '0 -8px 25px -5px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        };
    </script>

    <!-- Font Awesome 6 Free -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts (Material / Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        /* Android Material 3 Touch Feedback & Styling */
        .android-ripple {
            position: relative;
            overflow: hidden;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            -webkit-tap-highlight-color: transparent;
        }
        .android-ripple:active {
            transform: scale(0.96);
        }
        .android-glass {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }
        .app-icon-gloss {
            position: relative;
            overflow: hidden;
        }
        .app-icon-gloss::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 45%;
            background: linear-gradient(180deg, rgba(255,255,255,0.28) 0%, rgba(255,255,255,0) 100%);
            border-radius: inherit;
            pointer-events: none;
        }

        /* Ambient animated mesh */
        @keyframes floatMesh {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(25px, -20px) scale(1.08); }
        }
        .mesh-glow-1 {
            animation: floatMesh 14s ease-in-out infinite alternate;
        }
        .mesh-glow-2 {
            animation: floatMesh 18s ease-in-out infinite alternate-reverse;
        }
        
        /* Custom scrollbar disembunyikan untuk tampilan native app */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center selection:bg-indigo-500 selection:text-white antialiased overflow-x-hidden p-0 sm:py-6 sm:px-4">

    <!-- AMBIENT BACKGROUND GLOW (Wallpaper Modern Android) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-600/20 rounded-full blur-3xl mesh-glow-1"></div>
        <div class="absolute top-1/3 -right-32 w-[32rem] h-[32rem] bg-indigo-600/20 rounded-full blur-3xl mesh-glow-2"></div>
        <div class="absolute -bottom-32 left-1/4 w-[30rem] h-[30rem] bg-teal-500/15 rounded-full blur-3xl mesh-glow-1"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900/80 via-slate-950 to-black"></div>
    </div>

    <!-- ============================================================== -->
    <!-- ANDROID MOBILE APP CONTAINER FRAME (PAS & RAPI DI HP & DESKTOP)-->
    <!-- ============================================================== -->
    <div class="relative z-10 w-full max-w-[480px] bg-slate-900/90 sm:rounded-[36px] border-0 sm:border sm:border-slate-800/90 shadow-2xl overflow-hidden flex flex-col justify-between min-h-screen sm:min-h-[780px] sm:max-h-[920px] backdrop-blur-xl">

        <!-- 1. TOP APP BAR (MATERIAL 3) -->
        <header class="pt-4 sm:pt-5 pb-3 px-5 border-b border-white/5 flex items-center justify-between bg-slate-900/80 sticky top-0 z-20">
            <div class="flex items-center gap-3">
                <!-- Avatar Profil Operator -->
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-500 to-indigo-600 p-0.5 shadow-md shadow-emerald-500/20 shrink-0">
                    <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center font-black text-sm text-white">
                        <?= htmlspecialchars($initials ?: 'OP') ?>
                    </div>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h2 class="text-sm font-bold text-white leading-tight"><?= htmlspecialchars($user['name']) ?></h2>
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-mono uppercase tracking-wider mt-0.5">
                        <?= htmlspecialchars($user['role']) ?> &bull; PT IEG
                    </p>
                </div>
            </div>

            <!-- Action Buttons Kanan -->
            <div class="flex items-center gap-2">
                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <a href="admin" title="Panel Admin" class="w-9 h-9 rounded-2xl bg-indigo-600/20 text-indigo-400 hover:bg-indigo-600 hover:text-white border border-indigo-500/30 flex items-center justify-center transition text-xs">
                    <i class="fa-solid fa-chart-pie"></i>
                </a>
                <?php endif; ?>

                <a href="logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari sesi ini?')" title="Logout" class="w-9 h-9 rounded-2xl bg-rose-600/15 text-rose-400 hover:bg-rose-600 hover:text-white border border-rose-500/25 flex items-center justify-center transition text-xs">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </header>

        <!-- 2. SCROLLABLE APP BODY -->
        <main class="flex-1 px-5 py-4 space-y-4 overflow-y-auto no-scrollbar">

            <!-- WIDGET RINGKASAN HARI INI (MATERIAL 3 SURFACE CARD) -->
            <div class="bg-gradient-to-br from-slate-800/80 via-slate-800/50 to-slate-900/90 border border-white/10 rounded-2xl p-4 shadow-android-card">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-chart-simple text-indigo-400"></i> Aktivitas Hari Ini
                    </span>
                    <span class="text-[10px] font-mono text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full font-bold">
                        <?= date('d M Y') ?>
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-slate-900/80 rounded-xl p-2.5 border border-white/5">
                        <span class="text-lg font-black text-emerald-400 font-mono block"><?= number_format($totalReceivedToday) ?></span>
                        <span class="text-[10px] text-slate-400 font-medium">Paket Masuk</span>
                    </div>
                    <div class="bg-slate-900/80 rounded-xl p-2.5 border border-white/5">
                        <span class="text-lg font-black text-indigo-400 font-mono block"><?= number_format($totalUnboxedToday) ?></span>
                        <span class="text-[10px] text-slate-400 font-medium">Unboxed</span>
                    </div>
                    <div class="bg-slate-900/80 rounded-xl p-2.5 border border-white/5">
                        <span class="text-lg font-black text-amber-400 font-mono block"><?= number_format($totalItemsToday) ?></span>
                        <span class="text-[10px] text-slate-400 font-medium">Total Item</span>
                    </div>
                </div>
            </div>

            <!-- SECTION TITLE: MODUL UTAMA -->
            <div class="pt-1">
                <div class="flex items-center justify-between mb-3 px-0.5">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-shapes text-indigo-400"></i> Modul Inbound
                    </h3>
                    <span class="text-[10px] text-slate-500">Pilih aplikasi kerja</span>
                </div>

                <!-- 2 APLIKASI UTAMA (GRID 2 KOLOM GAYA ANDROID APP LAUNCHER) -->
                <div class="grid grid-cols-2 gap-3.5">

                    <!-- APP 01: INBOUND RECEIVING -->
                    <a href="reception" class="android-ripple bg-gradient-to-b from-slate-800/90 to-slate-900/95 border border-emerald-500/35 rounded-2xl p-4 flex flex-col justify-between items-center text-center shadow-android-card hover:border-emerald-400 hover:shadow-android-glow-emerald group">
                        
                        <!-- Icon App Squircle Hijau -->
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-400 p-0.5 shadow-lg shadow-emerald-500/30 group-hover:scale-105 transition-transform mb-3 app-icon-gloss">
                            <div class="w-full h-full bg-gradient-to-b from-emerald-500 to-teal-700 rounded-[14px] flex items-center justify-center text-white text-2xl">
                                <i class="fa-solid fa-truck-ramp-box drop-shadow"></i>
                            </div>
                        </div>

                        <!-- Info Teks -->
                        <div class="w-full">
                            <span class="text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 px-2 py-0.5 rounded-full inline-block mb-1">
                                MODUL 01
                            </span>
                            <h4 class="font-extrabold text-white text-sm leading-snug group-hover:text-emerald-300 transition-colors">
                                Inbound Receiving
                            </h4>
                            <p class="text-[10px] text-slate-400 mt-1 leading-tight line-clamp-2">
                                Terima tumpukan paket kurir & foto fisik.
                            </p>
                        </div>

                        <!-- Shortcut Badge -->
                        <div class="mt-3 w-full pt-2 border-t border-white/5 flex items-center justify-center gap-1 text-[10px] font-mono text-emerald-300">
                            <i class="fa-solid fa-keyboard text-[9px]"></i>
                            <span>Tuts [TAB]</span>
                        </div>
                    </a>

                    <!-- APP 02: INBOUND UNBOXING -->
                    <a href="scanner" class="android-ripple bg-gradient-to-b from-slate-800/90 to-slate-900/95 border border-indigo-500/35 rounded-2xl p-4 flex flex-col justify-between items-center text-center shadow-android-card hover:border-indigo-400 hover:shadow-android-glow-indigo group">
                        
                        <!-- Icon App Squircle Biru/Indigo -->
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-blue-400 p-0.5 shadow-lg shadow-indigo-500/30 group-hover:scale-105 transition-transform mb-3 app-icon-gloss">
                            <div class="w-full h-full bg-gradient-to-b from-indigo-600 to-blue-800 rounded-[14px] flex items-center justify-center text-white text-2xl">
                                <i class="fa-solid fa-box-open drop-shadow"></i>
                            </div>
                        </div>

                        <!-- Info Teks -->
                        <div class="w-full">
                            <span class="text-[9px] font-black uppercase tracking-wider text-indigo-400 bg-indigo-500/15 border border-indigo-500/30 px-2 py-0.5 rounded-full inline-block mb-1">
                                MODUL 02
                            </span>
                            <h4 class="font-extrabold text-white text-sm leading-snug group-hover:text-indigo-300 transition-colors">
                                Inbound Unboxing
                            </h4>
                            <p class="text-[10px] text-slate-400 mt-1 leading-tight line-clamp-2">
                                Rekam video unboxing, foto paket & cek SKU.
                            </p>
                        </div>

                        <!-- Shortcut Badge -->
                        <div class="mt-3 w-full pt-2 border-t border-white/5 flex items-center justify-center gap-1 text-[10px] font-mono text-indigo-300">
                            <i class="fa-solid fa-keyboard text-[9px]"></i>
                            <span>Tuts [F2] & [F4]</span>
                        </div>
                    </a>

                </div>
            </div>

            <!-- QUICK SHORTCUT TILES (FITUR PENDUKUNG) -->
            <div class="pt-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-2 px-0.5">Akses Cepat</span>
                <div class="grid grid-cols-3 gap-2">
                    
                    <a href="reception" class="android-ripple bg-slate-900/70 border border-white/5 hover:border-emerald-500/30 rounded-xl p-2.5 flex flex-col items-center justify-center text-center">
                        <i class="fa-solid fa-barcode text-emerald-400 text-sm mb-1"></i>
                        <span class="text-[10px] font-bold text-slate-300">Scan Resi</span>
                    </a>

                    <a href="scanner" class="android-ripple bg-slate-900/70 border border-white/5 hover:border-indigo-500/30 rounded-xl p-2.5 flex flex-col items-center justify-center text-center">
                        <i class="fa-solid fa-video text-indigo-400 text-sm mb-1"></i>
                        <span class="text-[10px] font-bold text-slate-300">Unbox Video</span>
                    </a>

                    <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                    <a href="admin" class="android-ripple bg-slate-900/70 border border-white/5 hover:border-amber-500/30 rounded-xl p-2.5 flex flex-col items-center justify-center text-center">
                        <i class="fa-solid fa-file-invoice text-amber-400 text-sm mb-1"></i>
                        <span class="text-[10px] font-bold text-slate-300">Klaim & OCS</span>
                    </a>
                    <?php else: ?>
                    <div class="bg-slate-900/40 border border-white/5 rounded-xl p-2.5 flex flex-col items-center justify-center text-center opacity-60">
                        <i class="fa-solid fa-shield-halved text-slate-400 text-sm mb-1"></i>
                        <span class="text-[10px] font-bold text-slate-400">Station 01</span>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </main>

        <!-- 3. BOTTOM NAVIGATION BAR (KHAS ANDROID MATERIAL 3 DENGAN PILL AKTIF) -->
        <nav class="bg-slate-950/95 border-t border-white/10 px-4 pt-2.5 pb-3 sticky bottom-0 z-20">
            <div class="flex items-center justify-around">
                
                <!-- Nav Item: Hub / Home (Aktif dengan Pill) -->
                <a href="menu" class="flex flex-col items-center group">
                    <div class="w-14 h-7 rounded-full bg-indigo-500/20 text-indigo-400 flex items-center justify-center transition group-hover:bg-indigo-500 group-hover:text-white">
                        <i class="fa-solid fa-shapes text-sm"></i>
                    </div>
                    <span class="text-[10px] font-bold text-indigo-300 mt-0.5">Hub</span>
                </a>

                <!-- Nav Item: Receiving -->
                <a href="reception" class="flex flex-col items-center group text-slate-400 hover:text-emerald-400 transition">
                    <div class="w-14 h-7 rounded-full flex items-center justify-center group-hover:bg-emerald-500/15">
                        <i class="fa-solid fa-truck-ramp-box text-sm"></i>
                    </div>
                    <span class="text-[10px] font-medium mt-0.5">Receiving</span>
                </a>

                <!-- Nav Item: Unboxing -->
                <a href="scanner" class="flex flex-col items-center group text-slate-400 hover:text-indigo-400 transition">
                    <div class="w-14 h-7 rounded-full flex items-center justify-center group-hover:bg-indigo-500/15">
                        <i class="fa-solid fa-box-open text-sm"></i>
                    </div>
                    <span class="text-[10px] font-medium mt-0.5">Unboxing</span>
                </a>

                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <!-- Nav Item: Admin -->
                <a href="admin" class="flex flex-col items-center group text-slate-400 hover:text-amber-400 transition">
                    <div class="w-14 h-7 rounded-full flex items-center justify-center group-hover:bg-amber-500/15">
                        <i class="fa-solid fa-chart-pie text-sm"></i>
                    </div>
                    <span class="text-[10px] font-medium mt-0.5">Admin</span>
                </a>
                <?php endif; ?>

            </div>

            <!-- Gesture Navigation Bar Android (Material Bar Pill) -->
            <div class="w-28 h-1 bg-white/20 rounded-full mx-auto mt-2.5"></div>
        </nav>

    </div>

</body>
</html>
