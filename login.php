<?php
require_once __DIR__ . '/config.php';

$error = '';
$currentUser = getSessionUser();

// Jika sudah login, langsung redirect sesuai role
if ($currentUser) {
    if ($currentUser['role'] === 'operator') {
        header('Location: index.php');
    } else {
        header('Location: admin.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
                if ($user['status'] !== 'ACTIVE') {
                    $error = 'Akun Anda sedang dinonaktifkan. Hubungi Superadmin.';
                } else {
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'name' => $user['name'],
                        'role' => $user['role']
                    ];

                    if ($user['role'] === 'operator') {
                        header('Location: index.php');
                    } else {
                        header('Location: admin.php');
                    }
                    exit;
                }
            } else {
                $error = 'Username atau password salah!';
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
    <title>Masuk ke Akun - Inbound Return Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= file_exists(__DIR__ . '/assets/css/custom.css') ? filemtime(__DIR__ . '/assets/css/custom.css') : time() ?>">
</head>
<body class="bg-gradient-to-br from-slate-100 via-indigo-50/40 to-slate-200 min-h-screen flex items-center justify-center p-4 font-sans text-slate-800">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-200/80 p-8 space-y-6">
        
        <!-- Header & Logo -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-600 text-white text-2xl shadow-md shadow-indigo-600/30 mb-2">
                <i class="fa-solid fa-boxes-packing"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-900 tracking-tight">Masuk ke Akun Anda</h2>
            <p class="text-xs text-slate-400 font-medium">Sistem Inbound Return Warehouse Station</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-500 text-base"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form method="POST" action="login.php" class="space-y-4">
            
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1.5">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="username" id="inputUsername" required placeholder="Contoh: superadmin / admin / operator"
                        class="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password" id="inputPassword" required placeholder="Masukkan password Anda"
                        class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-2xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 text-sm">
                        <i class="fa-solid fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-2xl text-xs transition duration-200 flex items-center justify-center gap-2 shadow-md shadow-indigo-600/30">
                <span>Masuk Sekarang</span>
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>
        </form>

        <!-- Quick Demo Login Helper -->
        <div class="pt-4 border-t border-slate-100 text-center space-y-2">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Pilih Akun Demo (1-Klik):</span>
            <div class="flex flex-wrap items-center justify-center gap-1.5">
                <button type="button" onclick="fillLogin('superadmin', 'admin')" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[10px] font-bold px-2.5 py-1.5 rounded-xl transition">
                    <i class="fa-solid fa-shield-halved"></i> Superadmin
                </button>
                <button type="button" onclick="fillLogin('admin', 'admin')" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-[10px] font-bold px-2.5 py-1.5 rounded-xl transition">
                    <i class="fa-solid fa-user-tie"></i> Admin
                </button>
                <button type="button" onclick="fillLogin('operator', 'operator')" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-[10px] font-bold px-2.5 py-1.5 rounded-xl transition">
                    <i class="fa-solid fa-barcode"></i> Operator
                </button>
            </div>
        </div>

        <div class="text-center">
            <a href="index.php" class="text-[11px] text-slate-400 hover:text-indigo-600 font-semibold transition">
                &larr; Kembali ke Inbound Station
            </a>
        </div>

    </div>

    <script>
    function togglePasswordVisibility() {
        const inp = document.getElementById('inputPassword');
        const icon = document.getElementById('eyeIcon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    }

    function fillLogin(u, p) {
        document.getElementById('inputUsername').value = u;
        document.getElementById('inputPassword').value = p;
        document.getElementById('inputUsername').focus();
    }
    </script>
</body>
</html>
