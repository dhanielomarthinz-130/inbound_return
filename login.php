<?php
require_once __DIR__ . '/config.php';

$error = '';
$currentUser = getSessionUser();

// Jika sudah login, langsung redirect sesuai role
if ($currentUser) {
    if (in_array($currentUser['role'] ?? '', ['operator', 'operator_mobile'])) {
        header('Location: ' . getAppBaseUrl() . 'menu');
    } else {
        header('Location: ' . getAppBaseUrl() . 'admin');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan Password wajib diisi!';
    } else {
        try {
            $cleanUser = str_replace([' ', '_', '-'], '', strtolower($username));
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR LOWER(username) = ? OR REPLACE(REPLACE(LOWER(username), ' ', ''), '_', '') = ? OR LOWER(name) = ? LIMIT 1");
            $stmt->execute([$username, strtolower($username), $cleanUser, strtolower($username)]);
            $user = $stmt->fetch();

            $isAuthValid = false;
            if ($user) {
                if (password_verify($password, $user['password']) || $password === $user['password']) {
                    $isAuthValid = true;
                } elseif (!empty($user['pin']) && $password === $user['pin']) {
                    $isAuthValid = true;
                }
            }

            if ($user && $isAuthValid) {
                if ($user['status'] !== 'ACTIVE') {
                    $error = 'Akun Anda sedang dinonaktifkan. Hubungi Administrator.';
                } else {
                    $_SESSION['user'] = [
                        'id'       => $user['id'],
                        'username' => $user['username'],
                        'name'     => $user['name'],
                        'role'     => $user['role']
                    ];

                    if (in_array($user['role'], ['operator', 'operator_mobile'])) {
                        header('Location: ' . getAppBaseUrl() . 'menu');
                    } else {
                        header('Location: ' . getAppBaseUrl() . 'admin');
                    }
                    exit;
                }
            } else {
                $error = 'Username atau Password salah! Periksa kembali data Anda.';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - IEG OCS (Omni Channel System)</title>
    <base href="<?= htmlspecialchars(getAppBaseUrl()) ?>">
    <!-- Favicon Huruf D Warna Hijau -->
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/image/favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/image/favicon.png?v=2">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico?v=2">
    <link rel="apple-touch-icon" href="assets/image/favicon.png?v=2">
    
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background-color: #1547dd;
            background-image: 
                radial-gradient(circle at 88% 50%, rgba(139, 92, 246, 0.55) 0%, transparent 55%),
                radial-gradient(circle at 18% 30%, rgba(29, 78, 216, 0.65) 0%, transparent 60%),
                radial-gradient(circle at 45% 85%, rgba(37, 99, 235, 0.45) 0%, transparent 65%),
                linear-gradient(135deg, #0b3bb8 0%, #1754ee 32%, #2563eb 55%, #6366f1 78%, #7c3aed 100%);
            background-attachment: fixed;
        }

        .numpad-btn {
            transition: all 0.15s ease;
            user-select: none;
            -webkit-user-select: none;
        }
        .numpad-btn:active {
            transform: scale(0.94);
            background-color: #e2e8f0;
        }

        .floating-input-group:focus-within {
            border-color: #6348eb;
            box-shadow: 0 0 0 3px rgba(99, 72, 235, 0.15);
        }
        .floating-input-group:focus-within label {
            color: #6348eb;
        }
    </style>
</head>
<body class="min-h-screen text-slate-800 flex flex-col justify-between selection:bg-purple-500 selection:text-white">

    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 py-6 sm:py-8 lg:py-12 min-h-screen flex flex-col justify-between">
        
        <!-- Header Branding: Top Left -->
        <header class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-white/20 border border-white/30 backdrop-blur-md flex items-center justify-center text-white shadow-md shadow-blue-950/20">
                    <i class="fa-solid fa-cart-shopping text-lg"></i>
                </div>
                <div>
                    <div class="text-base sm:text-lg font-black text-white tracking-tight leading-none drop-shadow-sm">IEG OCS</div>
                    <div class="text-[11px] text-blue-100 font-medium tracking-wide mt-0.5 opacity-90">Omni Channel System</div>
                </div>
            </div>
            <div class="hidden sm:flex items-center gap-2 text-xs font-semibold text-white/80 bg-white/10 px-3.5 py-1.5 rounded-full border border-white/15 backdrop-blur-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Sistem Inbound Return Aktif</span>
            </div>
        </header>

        <!-- Main Content Area: Left Hero + Right Card -->
        <main class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center py-6 sm:py-10 my-auto">
            
            <!-- KOLOM KIRI: TEKS SELAMAT DATANG & 4 FITUR UTAMA -->
            <section class="lg:col-span-7 text-white space-y-6 sm:space-y-8 pr-0 lg:pr-6">
                <div class="space-y-4">
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.12] drop-shadow-md">
                        Selamat Datang<br>di IEG OCS
                    </h1>
                    <p class="text-sm sm:text-base lg:text-lg text-blue-100/90 font-normal leading-relaxed max-w-xl">
                        Kelola pergudangan, pesanan, dan integrasi multi-platform dengan cepat dan akurat.
                    </p>
                </div>

                <!-- 4 FEATURE PILLS (Order Fulfillment, Stock Monitoring, Multi Shipping, Marketplace Sync) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-3.5 max-w-lg pt-1">
                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs sm:text-[13px] font-semibold shadow-xs transition duration-200">
                        <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-globe text-xs text-blue-200"></i>
                        </div>
                        <span class="truncate">Order Fulfillment</span>
                    </div>

                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs sm:text-[13px] font-semibold shadow-xs transition duration-200">
                        <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-boxes-stacked text-xs text-blue-200"></i>
                        </div>
                        <span class="truncate">Stock Monitoring</span>
                    </div>

                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs sm:text-[13px] font-semibold shadow-xs transition duration-200">
                        <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-truck-fast text-xs text-blue-200"></i>
                        </div>
                        <span class="truncate">Multi Shipping</span>
                    </div>

                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs sm:text-[13px] font-semibold shadow-xs transition duration-200">
                        <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-store text-xs text-blue-200"></i>
                        </div>
                        <span class="truncate">Marketplace Sync</span>
                    </div>
                </div>
            </section>

            <!-- KOLOM KANAN: KARTU LOGIN PUTIH (TANPA PILIH DATABASE) -->
            <section class="lg:col-span-5 flex justify-center lg:justify-end">
                <div class="w-full max-w-[420px] bg-white rounded-3xl sm:rounded-[2rem] shadow-2xl shadow-indigo-950/30 p-6 sm:p-8 md:p-9 border border-white/40">
                    
                    <!-- Logo Perusahaan: logo_text-BademdvM.jpg -->
                    <div class="flex justify-center mb-5">
                        <img src="assets/image/logo_text-BademdvM.jpg" alt="Inovasi Eka Gemilang" class="h-14 sm:h-16 w-auto max-w-[260px] object-contain">
                    </div>

                    <!-- Judul & Subjudul -->
                    <div class="text-center mb-6">
                        <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Masuk ke Akun Anda</h2>
                        <p class="text-xs text-slate-400 mt-1 font-medium">Silakan isi data di bawah untuk melanjutkan</p>
                    </div>

                    <!-- Notifikasi Error Jika Ada -->
                    <?php if (!empty($error)): ?>
                    <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold flex items-center gap-2.5 animate-shake">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 text-base shrink-0"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Form Login Langsung (Tanpa Pilih Database) -->
                    <form method="POST" action="login" class="space-y-4">
                        
                        <!-- Input Username -->
                        <div>
                            <div class="floating-input-group relative border border-slate-300 rounded-xl bg-slate-50/60 focus-within:bg-white transition duration-200">
                                <label for="loginUsername" class="absolute -top-2.5 left-3 px-1.5 bg-white text-[10px] font-bold text-slate-500 rounded transition-colors">
                                    Username
                                </label>
                                <div class="flex items-center px-3.5 py-2.5">
                                    <span class="text-slate-400 text-sm mr-2.5 shrink-0">
                                        <i class="fa-regular fa-user"></i>
                                    </span>
                                    <input type="text" name="username" id="loginUsername" required autocomplete="username"
                                        placeholder="ADMIN"
                                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                        class="w-full bg-transparent text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Input Password / PIN -->
                        <div>
                            <div class="floating-input-group relative border border-slate-300 rounded-xl bg-slate-50/60 focus-within:bg-white transition duration-200">
                                <label for="loginPassword" class="absolute -top-2.5 left-3 px-1.5 bg-white text-[10px] font-bold text-slate-500 rounded transition-colors">
                                    Password
                                </label>
                                <div class="flex items-center px-3.5 py-2.5">
                                    <span class="text-slate-400 text-sm mr-2.5 shrink-0">
                                        <i class="fa-solid fa-lock"></i>
                                    </span>
                                    <input type="password" name="password" id="loginPassword" required autocomplete="current-password"
                                        placeholder="•••••"
                                        class="w-full bg-transparent text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none">
                                    <button type="button" onclick="togglePasswordVisibility()" class="text-slate-400 hover:text-slate-600 ml-2 focus:outline-none cursor-pointer">
                                        <i class="fa-regular fa-eye-slash text-sm" id="pwdEyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Masuk Warna Ungu / Violet Sesuai Gambar -->
                        <div class="pt-2">
                            <button type="submit" class="w-full py-3.5 px-4 bg-[#6348eb] hover:bg-[#5235e2] active:scale-[0.99] text-white font-bold rounded-xl text-sm transition duration-200 shadow-md shadow-indigo-600/30 flex items-center justify-center gap-2 cursor-pointer">
                                <span>Masuk</span>
                                <i class="fa-solid fa-arrow-right-to-bracket text-sm"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Toggle Numpad Touch Screen Opsional (Untuk Operator Gudang Kiosk / Layar Sentuh) -->
                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col items-center">
                        <button type="button" onclick="toggleTouchNumpad()" id="btnToggleNumpad" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-bold inline-flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-calculator"></i>
                            <span>Mode Numpad Sentuh (Kiosk)</span>
                        </button>

                        <div id="touchNumpadContainer" class="hidden w-full mt-3 p-2 bg-slate-50 rounded-2xl border border-slate-200">
                            <div class="grid grid-cols-3 gap-1.5">
                                <button type="button" onclick="appendPinDigit('1')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">1</button>
                                <button type="button" onclick="appendPinDigit('2')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">2</button>
                                <button type="button" onclick="appendPinDigit('3')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">3</button>
                                <button type="button" onclick="appendPinDigit('4')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">4</button>
                                <button type="button" onclick="appendPinDigit('5')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">5</button>
                                <button type="button" onclick="appendPinDigit('6')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">6</button>
                                <button type="button" onclick="appendPinDigit('7')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">7</button>
                                <button type="button" onclick="appendPinDigit('8')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">8</button>
                                <button type="button" onclick="appendPinDigit('9')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">9</button>
                                <button type="button" onclick="clearPinInput()" class="numpad-btn py-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-[11px] font-bold">HAPUS</button>
                                <button type="button" onclick="appendPinDigit('0')" class="numpad-btn py-2 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold shadow-xs">0</button>
                                <button type="button" onclick="backspacePinInput()" class="numpad-btn py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold">
                                    <i class="fa-solid fa-delete-left"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Link Sesuai Gambar -->
                    <div class="text-center mt-5">
                        <span class="text-xs text-slate-400 font-medium">
                            Butuh bantuan? Hubungi tim IT
                        </span>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer Bawah Hak Cipta -->
        <footer class="pt-4 text-center sm:text-left text-xs text-white/60 flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-white/10">
            <div>
                &copy; <?= date('Y') ?> PT Inovasi Eka Gemilang &bull; Sistem Inbound Return &amp; OCS IEG
            </div>
            <div class="flex items-center gap-4 text-white/70">
                <span>Versi 2.4</span>
                <span>&bull;</span>
                <span>All Rights Reserved</span>
            </div>
        </footer>

    </div>

    <!-- Script Kontrol Form & Tampilan -->
    <script>
        function togglePasswordVisibility() {
            const input = document.getElementById('loginPassword');
            const icon = document.getElementById('pwdEyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-regular fa-eye text-sm';
            } else {
                input.type = 'password';
                icon.className = 'fa-regular fa-eye-slash text-sm';
            }
        }

        // Toggle Numpad Touchscreen
        function toggleTouchNumpad() {
            const container = document.getElementById('touchNumpadContainer');
            const btn = document.getElementById('btnToggleNumpad');
            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                btn.innerHTML = '<i class="fa-solid fa-xmark"></i> <span>Tutup Numpad</span>';
                document.getElementById('loginPassword').focus();
            } else {
                container.classList.add('hidden');
                btn.innerHTML = '<i class="fa-solid fa-calculator"></i> <span>Mode Numpad Sentuh (Kiosk)</span>';
            }
        }

        function appendPinDigit(d) {
            const pwd = document.getElementById('loginPassword');
            pwd.value += d;
            pwd.focus();
        }

        function clearPinInput() {
            const pwd = document.getElementById('loginPassword');
            pwd.value = '';
            pwd.focus();
        }

        function backspacePinInput() {
            const pwd = document.getElementById('loginPassword');
            pwd.value = pwd.value.slice(0, -1);
            pwd.focus();
        }

        // Auto-focus username on page load
        window.addEventListener('DOMContentLoaded', () => {
            const u = document.getElementById('loginUsername');
            if (u && !u.value) {
                u.focus();
            }
        });
    </script>
</body>
</html>
