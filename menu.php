<?php
// menu.php - Launcher Portal Operator Bergaya Aplikasi Mobile Android (Material You Light Theme & Google Symbols)
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
    <title>Inbound Hub • IEG</title>
    <?php
    $appBaseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $appBaseHref = ($appBaseDir === '' || $appBaseDir === '/') ? '/' : ($appBaseDir . '/');
    ?>
    <base href="<?= htmlspecialchars($appBaseHref) ?>">
    <script>window.APP_BASE_URL = <?= json_encode($appBaseHref) ?>;</script>
    
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
                        'squircle': '22px'
                    },
                    boxShadow: {
                        'material-card': '0 4px 20px -2px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04)',
                        'glow-emerald': '0 12px 28px -6px rgba(16, 185, 129, 0.35)',
                        'glow-indigo': '0 12px 28px -6px rgba(99, 102, 241, 0.35)'
                    }
                }
            }
        };
    </script>

    <!-- Google Material Symbols (Rounded) -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <!-- Google Fonts (Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
            line-height: 1;
        }
        
        /* Android Ripple Effect & Touch Response */
        .android-ripple {
            position: relative;
            overflow: hidden;
            transition: transform 0.18s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.18s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.18s;
            -webkit-tap-highlight-color: transparent;
        }
        .android-ripple:active {
            transform: scale(0.96);
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
            height: 44%;
            background: linear-gradient(180deg, rgba(255,255,255,0.32) 0%, rgba(255,255,255,0) 100%);
            border-radius: inherit;
            pointer-events: none;
        }

        /* Ambient animated mesh light */
        @keyframes floatMesh {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(20px, -15px) scale(1.05); }
        }
        .mesh-glow-1 {
            animation: floatMesh 14s ease-in-out infinite alternate;
        }
        .mesh-glow-2 {
            animation: floatMesh 18s ease-in-out infinite alternate-reverse;
        }
        
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="min-h-screen bg-[#f0f4f9] text-slate-800 flex items-center justify-center selection:bg-indigo-600 selection:text-white antialiased overflow-x-hidden p-0 sm:py-6 sm:px-4">

    <!-- AMBIENT BACKGROUND GLOW (LIGHT THEME) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-300/25 rounded-full blur-3xl mesh-glow-1"></div>
        <div class="absolute top-1/3 -right-32 w-[32rem] h-[32rem] bg-indigo-300/25 rounded-full blur-3xl mesh-glow-2"></div>
        <div class="absolute -bottom-32 left-1/4 w-[30rem] h-[30rem] bg-teal-200/25 rounded-full blur-3xl mesh-glow-1"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-[#f0f4f9]/80 via-[#f8fafc] to-[#eef2f6]"></div>
    </div>

    <!-- ============================================================== -->
    <!-- ANDROID MOBILE APP CONTAINER FRAME (LIGHT THEME MATERIAL YOU) -->
    <!-- ============================================================== -->
    <div class="relative z-10 w-full max-w-[460px] bg-white sm:rounded-[36px] border-0 sm:border sm:border-slate-200/80 shadow-xl shadow-slate-300/40 overflow-hidden flex flex-col justify-between min-h-screen sm:min-h-0 sm:my-auto">

        <!-- 1. TOP APP BAR (MATERIAL 3 LIGHT) -->
        <header class="pt-4 sm:pt-5 pb-3 px-5 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-20">
            <div class="flex items-center gap-3">
                <!-- Avatar Profil Operator -->
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-500 via-teal-500 to-indigo-600 p-0.5 shadow-md shadow-emerald-500/20 shrink-0">
                    <div class="w-full h-full bg-white rounded-[14px] flex items-center justify-center font-black text-sm text-indigo-700">
                        <?= htmlspecialchars($initials ?: 'OP') ?>
                    </div>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h2 class="text-sm font-extrabold text-slate-900 leading-tight"><?= htmlspecialchars($user['name']) ?></h2>
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-mono uppercase tracking-wider mt-0.5 font-medium">
                        <?= htmlspecialchars($user['role']) ?> &bull; IEG
                    </p>
                </div>
            </div>

            <!-- Action Buttons Kanan (Google Material Symbols) -->
            <div class="flex items-center gap-2">
                <?php if (in_array($user['role'], ['admin', 'superadmin'])): ?>
                <a href="admin" title="Panel Admin" class="w-9 h-9 rounded-2xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white border border-indigo-200/70 flex items-center justify-center transition shadow-2xs">
                    <span class="material-symbols-rounded text-lg">admin_panel_settings</span>
                </a>
                <?php endif; ?>

                <a href="logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari sesi ini?')" title="Logout" class="w-9 h-9 rounded-2xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white border border-rose-200/70 flex items-center justify-center transition shadow-2xs">
                    <span class="material-symbols-rounded text-lg">logout</span>
                </a>
            </div>
        </header>

        <!-- 2. SCROLLABLE APP BODY -->
        <main class="flex-1 px-5 py-4 space-y-4 overflow-y-auto no-scrollbar">

            <!-- WIDGET RINGKASAN HARI INI (MATERIAL 3 LIGHT CARD) -->
            <div class="bg-gradient-to-br from-slate-50 via-white to-slate-50 border border-slate-200/90 rounded-2xl p-4 shadow-sm">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                        <span class="material-symbols-rounded text-base text-indigo-600">monitoring</span>
                        <span>Aktivitas Hari Ini</span>
                    </span>
                    <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full font-bold">
                        <?= date('d M Y') ?>
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-white rounded-xl p-2.5 border border-slate-200/70 shadow-2xs">
                        <span class="text-lg font-black text-emerald-600 font-mono block"><?= number_format($totalReceivedToday) ?></span>
                        <span class="text-[10px] text-slate-500 font-medium">Paket Masuk</span>
                    </div>
                    <div class="bg-white rounded-xl p-2.5 border border-slate-200/70 shadow-2xs">
                        <span class="text-lg font-black text-indigo-600 font-mono block"><?= number_format($totalUnboxedToday) ?></span>
                        <span class="text-[10px] text-slate-500 font-medium">Unboxed</span>
                    </div>
                    <div class="bg-white rounded-xl p-2.5 border border-slate-200/70 shadow-2xs">
                        <span class="text-lg font-black text-amber-600 font-mono block"><?= number_format($totalItemsToday) ?></span>
                        <span class="text-[10px] text-slate-500 font-medium">Total Item</span>
                    </div>
                </div>
            </div>

            <!-- SECTION TITLE: MENU OPERASIONAL -->
            <div class="pt-1">
                <div class="flex items-center justify-between mb-3 px-0.5">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <span class="material-symbols-rounded text-base text-indigo-600">category</span>
                        <span>Menu Inbound</span>
                    </h3>
                    <span class="text-[10px] text-slate-400 font-medium">Pilih stasiun kerja</span>
                </div>

                <!-- 2 APLIKASI UTAMA (GRID 2 KOLOM GAYA ANDROID APP LAUNCHER) -->
                <div class="grid grid-cols-2 gap-3.5">

                    <!-- APP 01: INBOUND RECEIVING (TANPA MODUL 01, GOOGLE SYMBOLS) -->
                    <a href="reception" class="android-ripple bg-white border-2 border-emerald-500/25 hover:border-emerald-500 rounded-2xl p-4 sm:p-5 flex flex-col justify-between items-center text-center shadow-sm hover:shadow-glow-emerald group">
                        
                        <!-- Icon App Squircle Hijau -->
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 p-0.5 shadow-md shadow-emerald-500/30 group-hover:scale-105 transition-transform mb-3 app-icon-gloss">
                            <div class="w-full h-full bg-gradient-to-b from-emerald-500 to-teal-600 rounded-[14px] flex items-center justify-center text-white">
                                <span class="material-symbols-rounded text-3xl">local_shipping</span>
                            </div>
                        </div>

                        <!-- Info Teks -->
                        <div class="w-full">
                            <h4 class="font-black text-slate-900 text-sm leading-snug group-hover:text-emerald-700 transition-colors">
                                Inbound Receiving
                            </h4>
                        </div>

                        <!-- Shortcut Badge -->
                        <div class="mt-4 w-full pt-2.5 border-t border-slate-100 flex items-center justify-center gap-1.5 text-[11px] font-mono text-emerald-700 font-bold bg-emerald-50/60 rounded-xl py-1">
                            <span class="material-symbols-rounded text-xs text-emerald-600">keyboard</span>
                            <span>Tuts [TAB]</span>
                        </div>
                    </a>

                    <!-- APP 02: INBOUND UNBOXING (TANPA MODUL 02, GOOGLE SYMBOLS) -->
                    <a href="scanner" class="android-ripple bg-white border-2 border-indigo-500/25 hover:border-indigo-500 rounded-2xl p-4 sm:p-5 flex flex-col justify-between items-center text-center shadow-sm hover:shadow-glow-indigo group">
                        
                        <!-- Icon App Squircle Biru/Indigo -->
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-500 to-blue-400 p-0.5 shadow-md shadow-indigo-500/30 group-hover:scale-105 transition-transform mb-3 app-icon-gloss">
                            <div class="w-full h-full bg-gradient-to-b from-indigo-500 to-blue-600 rounded-[14px] flex items-center justify-center text-white">
                                <span class="material-symbols-rounded text-3xl">inventory_2</span>
                            </div>
                        </div>

                        <!-- Info Teks -->
                        <div class="w-full">
                            <h4 class="font-black text-slate-900 text-sm leading-snug group-hover:text-indigo-700 transition-colors">
                                Inbound Unboxing
                            </h4>
                        </div>

                        <!-- Shortcut Badge -->
                        <div class="mt-4 w-full pt-2.5 border-t border-slate-100 flex items-center justify-center gap-1.5 text-[11px] font-mono text-indigo-700 font-bold bg-indigo-50/60 rounded-xl py-1">
                            <span class="material-symbols-rounded text-xs text-indigo-600">keyboard</span>
                            <span>Tuts [F2] & [F4]</span>
                        </div>
                    </a>

                </div>
            </div>

        </main>

        <!-- FOOTER GESTURE BAR (MINIMALIS KHAS ANDROID LIGHT) -->
        <footer class="pb-5 pt-2 text-center bg-white border-t border-slate-50">
            <div class="w-28 h-1 bg-slate-300 rounded-full mx-auto"></div>
        </footer>

    </div>

</body>
</html>
