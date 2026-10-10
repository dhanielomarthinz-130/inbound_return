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

// Ambil daftar operator aktif untuk memudahkan operator memilih akun di station
try {
    $stmtOps = $pdo->query("SELECT id, username, name FROM users WHERE role IN ('operator', 'operator_mobile') AND status = 'ACTIVE' ORDER BY name ASC");
    $operators = $stmtOps->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $operators = [];
}

// Menentukan tab aktif default (admin atau operator)
$activeTab = $_POST['tab'] ?? ($_GET['tab'] ?? 'admin');
if (!in_array($activeTab, ['admin', 'operator'])) {
    $activeTab = 'admin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedTab = trim($_POST['tab'] ?? 'admin');
    $activeTab = $submittedTab;

    if ($submittedTab === 'operator') {
        // ==========================================
        // PROSES LOGIN OPERATOR (MENGGUNAKAN PIN)
        // ==========================================
        $username = trim($_POST['operator_username'] ?? '');
        $pin      = trim($_POST['pin'] ?? '');

        if (empty($username)) {
            $error = 'Silakan pilih atau masukkan Username Operator!';
        } elseif (empty($pin)) {
            $error = 'Silakan masukkan PIN Operator (6 Digit)!';
        } else {
            try {
                $cleanUser = str_replace([' ', '_', '-'], '', strtolower($username));
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR REPLACE(REPLACE(LOWER(username), ' ', ''), '_', '') = ? OR LOWER(name) = ? LIMIT 1");
                $stmt->execute([$username, $cleanUser, strtolower($username)]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'Akun Operator tidak ditemukan!';
                } elseif (!in_array($user['role'], ['operator', 'operator_mobile'])) {
                    $error = 'Akun ini bukan role Operator. Silakan login melalui Tab Admin.';
                } elseif ($user['status'] !== 'ACTIVE') {
                    $error = 'Akun Anda sedang dinonaktifkan. Hubungi Admin Gudang.';
                } else {
                    // Verifikasi PIN
                    $isPinValid = false;
                    if (!empty($user['pin']) && $user['pin'] === $pin) {
                        $isPinValid = true;
                    } elseif ($pin === $user['password'] || password_verify($pin, $user['password'])) {
                        $isPinValid = true;
                    }

                    if ($isPinValid) {
                        $_SESSION['user'] = [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'name' => $user['name'],
                            'role' => $user['role']
                        ];
                        
                        header('Location: ' . getAppBaseUrl() . 'menu');
                        exit;
                    } else {
                        $error = 'PIN yang Anda masukkan salah!';
                    }
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } else {
        // ==========================================
        // PROSES LOGIN ADMIN (MENGGUNAKAN PASSWORD)
        // ==========================================
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Username dan Password Admin wajib diisi!';
        } else {
            try {
                $cleanUser = str_replace([' ', '_', '-'], '', strtolower($username));
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR LOWER(username) = ? OR REPLACE(REPLACE(LOWER(username), ' ', ''), '_', '') = ? OR LOWER(name) = ? LIMIT 1");
                $stmt->execute([$username, strtolower($username), $cleanUser, strtolower($username)]);
                $user = $stmt->fetch();

                if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
                    if ($user['status'] !== 'ACTIVE') {
                        $error = 'Akun Anda sedang dinonaktifkan. Hubungi Superadmin.';
                    } elseif (in_array($user['role'], ['operator', 'operator_mobile'])) {
                        // Jika operator mencoba login di tab admin dengan password
                        $error = 'Akun ini adalah Operator Inbound. Silakan gunakan Tab Operator untuk masuk dengan PIN.';
                    } else {
                        $_SESSION['user'] = [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'name' => $user['name'],
                            'role' => $user['role']
                        ];
                        header('Location: ' . getAppBaseUrl() . 'admin');
                        exit;
                    }
                } else {
                    $error = 'Username atau Password Admin salah!';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - IEG Return</title>
    <base href="<?= htmlspecialchars(getAppBaseUrl()) ?>">
    <!-- Favicon Huruf D Warna Hijau -->
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/image/favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/image/favicon.png?v=2">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico?v=2">
    <link rel="apple-touch-icon" href="assets/image/favicon.png?v=2">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN & Font Awesome -->
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
            transition: all 0.12s ease;
            user-select: none;
            -webkit-user-select: none;
        }
        .numpad-btn:active {
            transform: scale(0.93);
            background-color: #e2e8f0;
        }

        .floating-input-group:focus-within {
            border-color: #6348eb;
            box-shadow: 0 0 0 3px rgba(99, 72, 235, 0.15);
        }
        .floating-input-group:focus-within label {
            color: #6348eb;
        }

        .floating-input-group-op:focus-within {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
        }
        .floating-input-group-op:focus-within label {
            color: #059669;
        }
    </style>
</head>
<body class="min-h-screen text-slate-800 flex flex-col justify-between selection:bg-purple-500 selection:text-white">

    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-7 lg:py-10 min-h-screen flex flex-col justify-between">
        
        <!-- Main Content Area: Left Hero + Right Form Card -->
        <main class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center py-6 sm:py-10 my-auto">
            
            <!-- KOLOM KIRI: TEKS SELAMAT DATANG & 4 FITUR UTAMA -->
            <section class="lg:col-span-5 text-white space-y-6 sm:space-y-8 pr-0 lg:pr-4">
                <div class="space-y-4">
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.12] drop-shadow-md">
                        Selamat Datang<br>di IEG Return
                    </h1>
                    <p class="text-sm sm:text-base lg:text-lg text-blue-100/90 font-normal leading-relaxed max-w-xl">
                        Kelola pergudangan, pesanan, dan integrasi multi-platform dengan cepat dan akurat.
                    </p>
                </div>

                <!-- 4 FEATURE PILLS (Reciving Expedisi, Unboxing Paket, Klaim Expedisi, Video & Foto) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-3.5 max-w-lg pt-1">
                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs sm:text-[13px] font-semibold shadow-xs transition duration-200">
                        <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-truck-ramp-box text-xs text-blue-200"></i>
                        </div>
                        <span class="truncate">Reciving Expedisi</span>
                    </div>

                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs sm:text-[13px] font-semibold shadow-xs transition duration-200">
                        <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-box-open text-xs text-blue-200"></i>
                        </div>
                        <span class="truncate">Unboxing Paket</span>
                    </div>

                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs sm:text-[13px] font-semibold shadow-xs transition duration-200">
                        <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-file-shield text-xs text-blue-200"></i>
                        </div>
                        <span class="truncate">Klaim Expedisi</span>
                    </div>

                    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs sm:text-[13px] font-semibold shadow-xs transition duration-200">
                        <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-video text-xs text-blue-200"></i>
                        </div>
                        <span class="truncate">Video & Foto</span>
                    </div>
                </div>
            </section>

            <!-- KOLOM KANAN: KARTU LOGIN PUTIH (PIN & PASSWORD, TANPA PILIH DATABASE) -->
            <section class="lg:col-span-7 flex justify-center lg:justify-end">
                <div id="loginCard" class="w-full transition-all duration-300 <?= $activeTab === 'operator' ? 'max-w-2xl' : 'max-w-md' ?> bg-white rounded-3xl sm:rounded-[2rem] shadow-2xl shadow-indigo-950/30 p-5 sm:p-7 md:p-8 border border-white/40">
                    
                    <!-- Logo Perusahaan: logo_text-BademdvM.jpg (Diperbesar & Tajam) -->
                    <div class="flex justify-center mb-5">
                        <img src="assets/image/logo_text-BademdvM.jpg?v=<?= file_exists(__DIR__ . '/assets/image/logo_text-BademdvM.jpg') ? filemtime(__DIR__ . '/assets/image/logo_text-BademdvM.jpg') : time() ?>" alt="Inovasi Eka Gemilang" class="h-16 sm:h-20 w-auto max-w-[280px] sm:max-w-[330px] object-contain">
                    </div>

                    <!-- Judul & Subjudul -->
                    <div class="text-center mb-4">
                        <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Masuk ke Akun Anda</h2>
                        <p class="text-xs text-slate-400 mt-1 font-medium">Pilih metode masuk Admin (Password) atau Operator (PIN)</p>
                    </div>

                    <!-- 2 TAB SWITCHER: ADMIN (PASSWORD) & OPERATOR (PIN) -->
                    <div class="p-1.5 bg-slate-100 rounded-2xl flex items-center gap-1.5 border border-slate-200 mb-5">
                        <button type="button" id="tabBtnAdmin" onclick="switchLoginTab('admin')"
                            class="flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 <?= $activeTab === 'admin' ? 'bg-white text-[#6348eb] shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                            <i class="fa-solid fa-user-shield text-sm"></i>
                            <span>Admin</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-md font-mono <?= $activeTab === 'admin' ? 'bg-indigo-50 text-[#6348eb]' : 'bg-slate-200 text-slate-500' ?>">Password</span>
                        </button>
                        <button type="button" id="tabBtnOperator" onclick="switchLoginTab('operator')"
                            class="flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 <?= $activeTab === 'operator' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                            <i class="fa-solid fa-id-badge text-sm"></i>
                            <span>Operator</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-md font-mono <?= $activeTab === 'operator' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-200 text-slate-500' ?>">PIN</span>
                        </button>
                    </div>

                    <!-- Notifikasi Error Jika Ada -->
                    <?php if (!empty($error)): ?>
                    <div class="mb-4 p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 text-base shrink-0"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- ======================================================== -->
                    <!-- TAB 1: FORM ADMIN (USERNAME & PASSWORD) -->
                    <!-- ======================================================== -->
                    <div id="sectionAdmin" class="<?= $activeTab === 'admin' ? '' : 'hidden' ?>">
                        <form method="POST" action="login" class="space-y-4">
                            <input type="hidden" name="tab" value="admin">
                            
                            <!-- Input Username Admin -->
                            <div>
                                <div class="floating-input-group relative border border-slate-300 rounded-xl bg-slate-50/60 focus-within:bg-white transition duration-200">
                                    <label for="adminUsername" class="absolute -top-2.5 left-3 px-1.5 bg-white text-[10px] font-bold text-slate-500 rounded transition-colors">
                                        Username
                                    </label>
                                    <div class="flex items-center px-3.5 py-2.5">
                                        <span class="text-slate-400 text-sm mr-2.5 shrink-0">
                                            <i class="fa-regular fa-user"></i>
                                        </span>
                                        <input type="text" name="username" id="adminUsername" required autocomplete="username"
                                            placeholder="ADMIN"
                                            value="<?= $activeTab === 'admin' ? htmlspecialchars($_POST['username'] ?? '') : '' ?>"
                                            class="w-full bg-transparent text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none">
                                    </div>
                                </div>
                            </div>

                            <!-- Input Password Admin -->
                            <div>
                                <div class="floating-input-group relative border border-slate-300 rounded-xl bg-slate-50/60 focus-within:bg-white transition duration-200">
                                    <label for="adminPassword" class="absolute -top-2.5 left-3 px-1.5 bg-white text-[10px] font-bold text-slate-500 rounded transition-colors">
                                        Password
                                    </label>
                                    <div class="flex items-center px-3.5 py-2.5">
                                        <span class="text-slate-400 text-sm mr-2.5 shrink-0">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                        <input type="password" name="password" id="adminPassword" required autocomplete="current-password"
                                            placeholder="••••••••"
                                            class="w-full bg-transparent text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none">
                                        <button type="button" onclick="toggleAdminPassword()" class="text-slate-400 hover:text-slate-600 ml-2 focus:outline-none cursor-pointer">
                                            <i class="fa-regular fa-eye-slash text-sm" id="adminEyeIcon"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol Masuk Admin -->
                            <div class="pt-2">
                                <button type="submit" class="w-full py-3.5 px-4 bg-[#6348eb] hover:bg-[#5235e2] active:scale-[0.99] text-white font-bold rounded-xl text-xs sm:text-sm transition duration-200 shadow-md shadow-indigo-600/30 flex items-center justify-center gap-2 cursor-pointer">
                                    <span>Masuk sebagai Admin</span>
                                    <i class="fa-solid fa-arrow-right-to-bracket text-sm"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ======================================================== -->
                    <!-- TAB 2: FORM OPERATOR (OPERATOR & PIN + TOUCH NUMPAD) -->
                    <!-- ======================================================== -->
                    <div id="sectionOperator" class="<?= $activeTab === 'operator' ? '' : 'hidden' ?>">
                        <form method="POST" action="login" id="formOperator">
                            <input type="hidden" name="tab" value="operator">

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 md:gap-6 items-stretch">
                                
                                <!-- SISI KIRI OPERATOR: DROPDOWN AKUN & PIN -->
                                <div class="md:col-span-6 flex flex-col justify-between space-y-4">
                                    <div class="space-y-4">
                                        <!-- Pilih Akun Operator -->
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-[10px] font-bold text-slate-500">Pilih Operator</span>
                                                <button type="button" onclick="toggleManualOperatorInput()" id="btnToggleManualOp" class="text-[10px] text-emerald-600 hover:text-emerald-800 font-bold">
                                                    <i class="fa-solid fa-keyboard"></i> Ketik Manual
                                                </button>
                                            </div>

                                            <div id="containerOpSelect" class="relative">
                                                <div class="floating-input-group-op relative border border-slate-300 rounded-xl bg-slate-50/60 focus-within:bg-white transition duration-200">
                                                    <div class="flex items-center px-3.5 py-2.5">
                                                        <span class="text-slate-400 text-sm mr-2.5 shrink-0">
                                                            <i class="fa-solid fa-users"></i>
                                                        </span>
                                                        <select name="operator_username" id="operatorSelect" onchange="onOperatorSelected()"
                                                            class="w-full bg-transparent text-xs font-semibold text-slate-800 focus:outline-none appearance-none cursor-pointer">
                                                            <option value="">-- Pilih Operator --</option>
                                                            <?php foreach ($operators as $op): ?>
                                                                <option value="<?= htmlspecialchars($op['username']) ?>" <?= (isset($_POST['operator_username']) && $_POST['operator_username'] === $op['username']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars(!empty($op['name']) ? $op['name'] : $op['username']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <span class="text-slate-400 text-xs ml-2 pointer-events-none">
                                                            <i class="fa-solid fa-chevron-down"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Input Manual Alternatif -->
                                            <div id="containerOpInput" class="relative hidden">
                                                <div class="floating-input-group-op relative border border-slate-300 rounded-xl bg-slate-50/60 focus-within:bg-white transition duration-200">
                                                    <div class="flex items-center px-3.5 py-2.5">
                                                        <span class="text-slate-400 text-sm mr-2.5 shrink-0">
                                                            <i class="fa-solid fa-user-pen"></i>
                                                        </span>
                                                        <input type="text" id="operatorManualInput" placeholder="Ketik username operator..."
                                                            class="w-full bg-transparent text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Input PIN Operator -->
                                        <div>
                                            <div class="floating-input-group-op relative border border-slate-300 rounded-xl bg-slate-50/60 focus-within:bg-white transition duration-200">
                                                <label for="operatorPin" class="absolute -top-2.5 left-3 px-1.5 bg-white text-[10px] font-bold text-slate-500 rounded transition-colors">
                                                    PIN Keamanan (6 Digit)
                                                </label>
                                                <div class="flex items-center px-3.5 py-2.5">
                                                    <span class="text-slate-400 text-sm mr-2.5 shrink-0">
                                                        <i class="fa-solid fa-key"></i>
                                                    </span>
                                                    <input type="password" name="pin" id="operatorPin" required maxlength="10" inputmode="numeric" pattern="[0-9]*"
                                                        placeholder="••••••"
                                                        class="w-full bg-transparent text-sm font-mono tracking-widest text-center font-bold text-slate-800 placeholder-slate-400 focus:outline-none">
                                                    <button type="button" onclick="toggleOperatorPin()" class="text-slate-400 hover:text-slate-600 ml-2 focus:outline-none cursor-pointer">
                                                        <i class="fa-regular fa-eye-slash text-sm" id="operatorEyeIcon"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tombol Masuk Operator (Desktop) -->
                                    <div class="hidden md:block pt-2">
                                        <button type="submit" class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-bold rounded-xl text-xs sm:text-sm transition duration-200 shadow-md shadow-emerald-600/30 flex items-center justify-center gap-2 cursor-pointer">
                                            <span>Masuk sebagai Operator</span>
                                            <i class="fa-solid fa-arrow-right-to-bracket text-sm"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- SISI KANAN OPERATOR: INTERACTIVE TOUCH NUMPAD -->
                                <div class="md:col-span-6 flex flex-col justify-between">
                                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-2.5 sm:p-3 flex-1 flex flex-col justify-center">
                                        <div class="text-[11px] font-bold text-slate-500 text-center mb-2 flex items-center justify-center gap-1.5">
                                            <i class="fa-solid fa-calculator text-emerald-600"></i> Numpad Sentuh Layar
                                        </div>
                                        <div class="grid grid-cols-3 gap-1.5 flex-1">
                                            <button type="button" onclick="appendPin('1')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">1</button>
                                            <button type="button" onclick="appendPin('2')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">2</button>
                                            <button type="button" onclick="appendPin('3')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">3</button>
                                            
                                            <button type="button" onclick="appendPin('4')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">4</button>
                                            <button type="button" onclick="appendPin('5')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">5</button>
                                            <button type="button" onclick="appendPin('6')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">6</button>
                                            
                                            <button type="button" onclick="appendPin('7')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">7</button>
                                            <button type="button" onclick="appendPin('8')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">8</button>
                                            <button type="button" onclick="appendPin('9')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">9</button>
                                            
                                            <button type="button" onclick="clearPin()" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-xs font-bold shadow-xs hover:bg-rose-100 flex items-center justify-center">
                                                <span>HAPUS</span>
                                            </button>
                                            <button type="button" onclick="appendPin('0')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-xs hover:bg-slate-100">0</button>
                                            <button type="button" onclick="backspacePin()" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-sm font-bold shadow-xs hover:bg-amber-100 flex items-center justify-center">
                                                <i class="fa-solid fa-delete-left"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Tombol Masuk Operator (Mobile) -->
                                    <div class="block md:hidden mt-3">
                                        <button type="submit" class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-bold rounded-xl text-xs sm:text-sm transition duration-200 shadow-md shadow-emerald-600/30 flex items-center justify-center gap-2 cursor-pointer">
                                            <span>Masuk sebagai Operator</span>
                                            <i class="fa-solid fa-arrow-right-to-bracket text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </section>
        </main>

        <!-- Footer Bawah -->
        <footer class="pt-4 text-center sm:text-left text-xs text-white/70 flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-white/10 font-medium">
            <div>
                Powered By Dhanielo-Marthinz
            </div>
            <div class="flex items-center gap-4 text-white/60">
                <span>Versi 2.4</span>
                <span>&bull;</span>
                <span>All Rights Reserved</span>
            </div>
        </footer>

    </div>

    <!-- Scripts -->
    <script src="assets/js/toast.js?v=<?= file_exists(__DIR__ . '/assets/js/toast.js') ? filemtime(__DIR__ . '/assets/js/toast.js') : time() ?>"></script>
    <script>
        // Tab switching logic
        function switchLoginTab(tab) {
            const secAdmin = document.getElementById('sectionAdmin');
            const secOperator = document.getElementById('sectionOperator');
            const btnAdmin = document.getElementById('tabBtnAdmin');
            const btnOperator = document.getElementById('tabBtnOperator');
            const loginCard = document.getElementById('loginCard');

            if (tab === 'operator') {
                secAdmin.classList.add('hidden');
                secOperator.classList.remove('hidden');

                if (loginCard) {
                    loginCard.classList.remove('max-w-md');
                    loginCard.classList.add('max-w-2xl');
                }

                btnAdmin.className = 'flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 text-slate-500 hover:text-slate-800';
                btnAdmin.querySelector('span:last-child').className = 'text-[10px] px-1.5 py-0.5 rounded-md font-mono bg-slate-200 text-slate-500';

                btnOperator.className = 'flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 bg-white text-emerald-700 shadow-sm';
                btnOperator.querySelector('span:last-child').className = 'text-[10px] px-1.5 py-0.5 rounded-md font-mono bg-emerald-50 text-emerald-600';

                const opSelect = document.getElementById('operatorSelect');
                if (opSelect && opSelect.value) {
                    document.getElementById('operatorPin').focus();
                } else if (opSelect) {
                    opSelect.focus();
                }
            } else {
                secOperator.classList.add('hidden');
                secAdmin.classList.remove('hidden');

                if (loginCard) {
                    loginCard.classList.remove('max-w-2xl');
                    loginCard.classList.add('max-w-md');
                }

                btnOperator.className = 'flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 text-slate-500 hover:text-slate-800';
                btnOperator.querySelector('span:last-child').className = 'text-[10px] px-1.5 py-0.5 rounded-md font-mono bg-slate-200 text-slate-500';

                btnAdmin.className = 'flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 bg-white text-[#6348eb] shadow-sm';
                btnAdmin.querySelector('span:last-child').className = 'text-[10px] px-1.5 py-0.5 rounded-md font-mono bg-indigo-50 text-[#6348eb]';

                document.getElementById('adminUsername').focus();
            }
        }

        // Toggle Admin Password
        function toggleAdminPassword() {
            const inp = document.getElementById('adminPassword');
            const icon = document.getElementById('adminEyeIcon');
            if (inp.type === 'password') {
                inp.type = 'text';
                icon.className = 'fa-regular fa-eye text-sm';
            } else {
                inp.type = 'password';
                icon.className = 'fa-regular fa-eye-slash text-sm';
            }
        }

        // Toggle Operator PIN
        function toggleOperatorPin() {
            const inp = document.getElementById('operatorPin');
            const icon = document.getElementById('operatorEyeIcon');
            if (inp.type === 'password') {
                inp.type = 'text';
                icon.className = 'fa-regular fa-eye text-sm';
            } else {
                inp.type = 'password';
                icon.className = 'fa-regular fa-eye-slash text-sm';
            }
        }

        // Numpad button handlers
        function appendPin(num) {
            const pinInp = document.getElementById('operatorPin');
            if (pinInp.value.length < 10) {
                pinInp.value += num;
            }
        }

        function clearPin() {
            document.getElementById('operatorPin').value = '';
        }

        function backspacePin() {
            const pinInp = document.getElementById('operatorPin');
            pinInp.value = pinInp.value.slice(0, -1);
        }

        function onOperatorSelected() {
            const sel = document.getElementById('operatorSelect');
            if (sel.value) {
                document.getElementById('operatorPin').focus();
            }
        }

        // Toggle manual operator typing vs select
        let isManualOp = false;
        function toggleManualOperatorInput() {
            isManualOp = !isManualOp;
            const contSelect = document.getElementById('containerOpSelect');
            const contInput = document.getElementById('containerOpInput');
            const btnToggle = document.getElementById('btnToggleManualOp');
            const sel = document.getElementById('operatorSelect');
            const inp = document.getElementById('operatorManualInput');

            if (isManualOp) {
                contSelect.classList.add('hidden');
                contInput.classList.remove('hidden');
                sel.removeAttribute('name');
                inp.setAttribute('name', 'operator_username');
                btnToggle.innerHTML = '<i class="fa-solid fa-list"></i> Pilih dari Daftar';
                inp.focus();
            } else {
                contInput.classList.add('hidden');
                contSelect.classList.remove('hidden');
                inp.removeAttribute('name');
                sel.setAttribute('name', 'operator_username');
                btnToggle.innerHTML = '<i class="fa-solid fa-keyboard"></i> Ketik Manual';
                sel.focus();
            }
        }

        // Auto-focus saat load
        window.addEventListener('DOMContentLoaded', () => {
            const u = document.getElementById('adminUsername');
            if (u && !u.value) {
                u.focus();
            }
        });
    </script>
</body>
</html>
