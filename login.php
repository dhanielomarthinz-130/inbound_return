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
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
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
    <title>Masuk Sistem - Inbound Return IEG</title>
    <base href="<?= htmlspecialchars(getAppBaseUrl()) ?>">
    <!-- Favicon Huruf D Warna Hijau -->
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/image/favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/image/favicon.png?v=2">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico?v=2">
    <link rel="apple-touch-icon" href="assets/image/favicon.png?v=2">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= file_exists(__DIR__ . '/assets/css/custom.css') ? filemtime(__DIR__ . '/assets/css/custom.css') : time() ?>">
    <style>
        .numpad-btn {
            transition: all 0.15s ease;
            user-select: none;
            -webkit-user-select: none;
        }
        .numpad-btn:active {
            transform: scale(0.92);
            background-color: #cbd5e1;
        }
        /* Responsif Height & Multi-Screen Aware untuk Layar Laptop / Kiosk Touchscreen */
        @media (max-height: 760px) {
            body { padding: 0.5rem !important; }
            .login-card { padding: 1.25rem 1.5rem !important; space-y: 0.75rem !important; }
            .login-header-logo { width: 3rem !important; height: 3rem !important; margin-bottom: 0 !important; }
            .login-header-title { font-size: 1.25rem !important; }
            .login-header-desc { display: none !important; }
            .numpad-btn { padding-top: 0.4rem !important; padding-bottom: 0.4rem !important; font-size: 0.875rem !important; }
        }
        @media (max-height: 640px) {
            .login-header-logo { display: none !important; }
            .login-card { padding: 0.75rem 1rem !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-100 via-indigo-50/50 to-slate-200 min-h-screen flex items-center justify-center p-3 sm:p-4 md:p-6 font-sans text-slate-800">

    <div id="loginCard" class="login-card w-full <?= $activeTab === 'operator' ? 'max-w-2xl' : 'max-w-md' ?> bg-white rounded-3xl shadow-2xl shadow-indigo-900/10 border border-slate-200/90 p-5 sm:p-6 md:p-8 space-y-4 md:space-y-5 transition-all duration-300 my-auto">
        
        <!-- Header & Logo -->
        <div class="text-center space-y-1.5">
            <div class="login-header-logo inline-flex items-center justify-center w-14 h-14 sm:w-16 sm:h-16 p-2 rounded-2xl bg-white border border-slate-200 shadow-md mb-0.5">
                <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
            </div>
            <h2 class="login-header-title text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Inbound Return IEG</h2>
            <p class="login-header-desc text-xs text-slate-400 font-medium">Sistem Verifikasi & Unboxing Pengembalian Barang</p>
        </div>

        <!-- 2 TAB SWITCHER: ADMIN (PASSWORD) & OPERATOR (PIN) -->
        <div class="p-1.5 bg-slate-100 rounded-2xl flex items-center gap-1.5 border border-slate-200">
            <button type="button" id="tabBtnAdmin" onclick="switchLoginTab('admin')"
                class="flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 <?= $activeTab === 'admin' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-user-shield text-sm"></i>
                <span>Admin</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-md font-mono <?= $activeTab === 'admin' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-200 text-slate-500' ?>">Password</span>
            </button>
            <button type="button" id="tabBtnOperator" onclick="switchLoginTab('operator')"
                class="flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 <?= $activeTab === 'operator' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                <i class="fa-solid fa-id-badge text-sm"></i>
                <span>Operator</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-md font-mono <?= $activeTab === 'operator' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-200 text-slate-500' ?>">PIN</span>
            </button>
        </div>

        <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold flex items-center gap-2.5 animate-shake">
            <i class="fa-solid fa-circle-exclamation text-rose-500 text-base shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- ======================================================== -->
        <!-- FORM 1: LOGIN ADMIN (USERNAME & PASSWORD) -->
        <!-- ======================================================== -->
        <div id="sectionAdmin" class="<?= $activeTab === 'admin' ? '' : 'hidden' ?>">
            <form method="POST" action="login" class="space-y-4">
                <input type="hidden" name="tab" value="admin">
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5">Username Admin</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" name="username" id="adminUsername" required placeholder="Contoh: superadmin / admin"
                            value="<?= $activeTab === 'admin' ? htmlspecialchars($_POST['username'] ?? '') : '' ?>"
                            class="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="adminPassword" required placeholder="Masukkan password Anda"
                            class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                        <button type="button" onclick="toggleAdminPassword()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 text-sm">
                            <i class="fa-solid fa-eye" id="adminEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-2xl text-xs transition duration-200 flex items-center justify-center gap-2 shadow-md shadow-indigo-600/30">
                    <span>Masuk sebagai Admin</span>
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </button>
            </form>
        </div>

        <!-- ======================================================== -->
        <!-- FORM 2: LOGIN OPERATOR (OPERATOR & PIN) -->
        <!-- ======================================================== -->
        <div id="sectionOperator" class="<?= $activeTab === 'operator' ? '' : 'hidden' ?>">
            <form method="POST" action="login" id="formOperator">
                <input type="hidden" name="tab" value="operator">

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 md:gap-6 items-stretch">
                    <!-- KOLOM KIRI: INFO, PILIH AKUN, INPUT PIN, & SUBMIT DESKTOP -->
                    <div class="md:col-span-6 flex flex-col justify-between space-y-3.5">
                        <div class="space-y-3.5">
                            <!-- INFO LOGIN OPERATOR -->
                            <div class="bg-emerald-50 border border-emerald-200/80 rounded-2xl p-3 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-sm shadow-emerald-600/30">
                                    <i class="fa-solid fa-shapes"></i>
                                </div>
                                <div class="leading-tight">
                                    <span class="text-xs font-bold text-emerald-900 block">Stasiun Operator Inbound</span>
                                    <span class="text-[10px] text-emerald-700">Pilih akun & masukkan PIN untuk akses sistem</span>
                                </div>
                            </div>
                            
                            <!-- PILIH AKUN OPERATOR -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-slate-600">Pilih Akun Operator</label>
                                    <button type="button" onclick="toggleManualOperatorInput()" id="btnToggleManualOp" class="text-[11px] text-emerald-600 hover:text-emerald-700 font-bold transition">
                                        <i class="fa-solid fa-keyboard"></i> Ketik Manual
                                    </button>
                                </div>

                                <!-- Dropdown Pilihan Operator Aktif -->
                                <div id="containerOpSelect" class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                        <i class="fa-solid fa-user-check"></i>
                                    </span>
                                    <select name="operator_username" id="operatorSelect" onchange="onOperatorSelected()"
                                        class="w-full pl-10 pr-8 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition appearance-none">
                                        <option value="">-- Pilih Operator Inbound --</option>
                                        <?php foreach ($operators as $op): ?>
                                            <option value="<?= htmlspecialchars($op['username']) ?>" <?= (isset($_POST['operator_username']) && $_POST['operator_username'] === $op['username']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(!empty($op['name']) ? $op['name'] : $op['username']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </span>
                                </div>

                                <!-- Input Manual Alternatif -->
                                <div id="containerOpInput" class="relative hidden">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                        <i class="fa-solid fa-user-pen"></i>
                                    </span>
                                    <input type="text" id="operatorManualInput" placeholder="Masukkan username operator..."
                                        class="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                                </div>
                            </div>

                            <!-- Input PIN Operator -->
                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="block text-xs font-bold text-slate-600">PIN Keamanan (6 Digit)</label>
                                    <span class="text-[10px] text-slate-400 font-medium">Numpad / Keyboard</span>
                                </div>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                        <i class="fa-solid fa-key"></i>
                                    </span>
                                    <input type="password" name="pin" id="operatorPin" required maxlength="10" inputmode="numeric" pattern="[0-9]*" placeholder="••••••"
                                        class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-sm font-mono tracking-widest text-center font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                                    <button type="button" onclick="toggleOperatorPin()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 text-sm">
                                        <i class="fa-solid fa-eye" id="operatorEyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- SUBMIT BUTTON DESKTOP (tampil di bawah form kiri di layar laptop/desktop) -->
                        <div class="hidden md:block pt-1">
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-2xl text-xs transition duration-200 flex items-center justify-center gap-2 shadow-md shadow-emerald-600/30">
                                <span>Masuk sebagai Operator</span>
                                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            </button>
                        </div>
                    </div>

                    <!-- KOLOM KANAN: INTERACTIVE TOUCH NUMPAD & SUBMIT MOBILE -->
                    <div class="md:col-span-6 flex flex-col justify-between">
                        <div class="bg-slate-50/90 border border-slate-200/90 rounded-2xl p-2.5 sm:p-3 flex-1 flex flex-col justify-center">
                            <div class="text-[11px] font-bold text-slate-500 text-center mb-2 flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-calculator text-emerald-600"></i> Numpad Sentuh Cepat
                            </div>
                            <div class="grid grid-cols-3 gap-2 flex-1">
                                <button type="button" onclick="appendPin('1')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">1</button>
                                <button type="button" onclick="appendPin('2')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">2</button>
                                <button type="button" onclick="appendPin('3')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">3</button>
                                
                                <button type="button" onclick="appendPin('4')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">4</button>
                                <button type="button" onclick="appendPin('5')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">5</button>
                                <button type="button" onclick="appendPin('6')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">6</button>
                                
                                <button type="button" onclick="appendPin('7')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">7</button>
                                <button type="button" onclick="appendPin('8')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">8</button>
                                <button type="button" onclick="appendPin('9')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">9</button>
                                
                                <button type="button" onclick="clearPin()" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 text-xs font-bold shadow-sm hover:bg-rose-100 flex items-center justify-center">
                                    <span>HAPUS</span>
                                </button>
                                <button type="button" onclick="appendPin('0')" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-bold shadow-sm hover:bg-slate-100">0</button>
                                <button type="button" onclick="backspacePin()" class="numpad-btn py-2 sm:py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-sm font-bold shadow-sm hover:bg-amber-100 flex items-center justify-center">
                                    <i class="fa-solid fa-delete-left"></i>
                                </button>
                            </div>
                        </div>

                        <!-- SUBMIT BUTTON MOBILE (hanya tampil di layar ponsel/vertikal) -->
                        <div class="block md:hidden mt-3">
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-2xl text-xs transition duration-200 flex items-center justify-center gap-2 shadow-md shadow-emerald-600/30">
                                <span>Masuk sebagai Operator</span>
                                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>



    </div>

    <script src="assets/js/toast.js?v=<?= file_exists(__DIR__ . '/assets/js/toast.js') ? filemtime(__DIR__ . '/assets/js/toast.js') : time() ?>"></script>
    <script>
    // Tab switching logic with automatic card resizing
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

            btnAdmin.className = 'flex-1 py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition duration-200 bg-white text-indigo-700 shadow-sm';
            btnAdmin.querySelector('span:last-child').className = 'text-[10px] px-1.5 py-0.5 rounded-md font-mono bg-indigo-50 text-indigo-600';

            document.getElementById('adminUsername').focus();
        }
    }

    // Toggle Password visibility for Admin
    function toggleAdminPassword() {
        const inp = document.getElementById('adminPassword');
        const icon = document.getElementById('adminEyeIcon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    }

    // Toggle PIN visibility for Operator
    function toggleOperatorPin() {
        const inp = document.getElementById('operatorPin');
        const icon = document.getElementById('operatorEyeIcon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    }

    // Numpad button handlers for Operator PIN
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

    </script>
</body>
</html>
