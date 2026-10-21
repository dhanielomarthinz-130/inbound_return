<?php
// maintenance.php - Halaman Pemeliharaan Sistem & Portal Migrasi PC Server
if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
}
$currentUser = getSessionUser();
$rawRole = strtolower(trim(str_replace([' ', '_', '-'], '', $currentUser['role'] ?? '')));
$isSuperAdmin = ($currentUser && $rawRole === 'superadmin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Sedang Dalam Pemeliharaan - Return Inbound</title>
    <!-- Favicon Huruf D Warna Hijau -->
    <link rel="icon" type="image/svg+xml" sizes="any" href="assets/image/favicon.svg?v=2">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/image/favicon.png?v=2">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico?v=2">
    <link rel="apple-touch-icon" href="assets/image/favicon.png?v=2">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-xl w-full bg-slate-800/90 rounded-3xl p-6 md:p-8 border border-slate-700/80 shadow-2xl text-center space-y-6">
        
        <!-- Logo IEG & Animated Maintenance Icon -->
        <div class="flex items-center justify-center space-x-3 mb-1">
            <div class="w-12 h-12 rounded-2xl bg-white p-1.5 flex items-center justify-center shadow-md">
                <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
            </div>
            <div class="text-left">
                <h2 class="font-bold text-white text-base leading-tight">Inbound Return IEG</h2>
                <p class="text-[10px] text-slate-400">Warehouse System</p>
            </div>
        </div>

        <div class="relative w-16 h-16 mx-auto flex items-center justify-center">
            <div class="absolute inset-0 rounded-3xl bg-amber-500/10 animate-ping"></div>
            <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-white text-2xl shadow-xl shadow-amber-500/30">
                <i class="fa-solid fa-screwdriver-wrench animate-bounce"></i>
            </div>
        </div>

        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 bg-amber-500/20 text-amber-300 text-xs font-bold px-3 py-1 rounded-full border border-amber-500/30">
                <i class="fa-solid fa-circle-exclamation text-amber-400"></i> Mode Pemeliharaan Aktif
            </span>
            <h1 class="text-2xl font-black text-white tracking-tight">Sistem Sedang Dalam Maintenance</h1>
            <p class="text-xs text-slate-400 leading-relaxed max-w-md mx-auto">
                Layanan sistem Inbound Return saat ini sedang dalam pemeliharaan berkala atau persiapan migrasi database oleh Super Administrator.
            </p>
        </div>

        <!-- Spinner Bola-Bola Merah Kuning Hijau -->
        <div class="py-1">
            <div class="traffic-loader">
                <div class="traffic-ball traffic-ball-red"></div>
                <div class="traffic-ball traffic-ball-yellow"></div>
                <div class="traffic-ball traffic-ball-green"></div>
            </div>
            <span class="text-[11px] text-slate-500 block mt-2 font-mono">Sedang mengoptimasi database & sistem...</span>
        </div>

        <!-- ============================================================= -->
        <!-- SECTION KHUSUS MIGRASI PC SERVER BARU (SUPERADMIN)           -->
        <!-- ============================================================= -->
        <?php if ($isSuperAdmin): ?>
        <div class="bg-gradient-to-br from-indigo-950/80 to-slate-900/90 border border-indigo-500/40 rounded-2xl p-5 text-left space-y-4 shadow-inner">
            <div class="flex items-center justify-between border-b border-indigo-500/30 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600/30 text-indigo-400 flex items-center justify-center text-sm border border-indigo-500/40">
                        <i class="fa-solid fa-server"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-xs text-white">Panel Migrasi ke PC Server Baru</h3>
                        <p class="text-[10px] text-emerald-400">Login sebagai: <b><?= htmlspecialchars($currentUser['name'] ?? $currentUser['username']) ?></b> (Superadmin)</p>
                    </div>
                </div>
                <span class="text-[9px] bg-emerald-500/20 text-emerald-300 font-bold px-2 py-0.5 rounded-full border border-emerald-500/30 uppercase">Terverifikasi</span>
            </div>

            <p class="text-[11px] text-slate-300 leading-relaxed">
                Gunakan tombol unduh di bawah ini untuk mengambil database lengkap dan source code server untuk dipindahkan ke PC Server baru:
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <!-- Download Database .sql -->
                <a href="api/maintenance.php?action=download_database&format=sql" download class="bg-amber-600 hover:bg-amber-500 text-white font-bold p-3 rounded-xl text-xs transition flex items-center gap-2.5 shadow-sm">
                    <i class="fa-solid fa-database text-amber-200 text-base"></i>
                    <div class="text-left">
                        <span class="block font-bold">1. Unduh Database (.sql)</span>
                        <span class="block text-[10px] text-amber-100 font-normal">Seluruh tabel &amp; riwayat data</span>
                    </div>
                </a>

                <!-- Download Paket Ringan .zip -->
                <a href="api/maintenance.php?action=download_server_files&mode=app" download class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold p-3 rounded-xl text-xs transition flex items-center gap-2.5 shadow-sm">
                    <i class="fa-solid fa-file-zipper text-indigo-200 text-base"></i>
                    <div class="text-left">
                        <span class="block font-bold">2. Paket Aplikasi (.zip)</span>
                        <span class="block text-[10px] text-indigo-100 font-normal">±15 MB (Program + DB terbaru)</span>
                    </div>
                </a>

                <!-- Download Full Server .zip -->
                <a href="api/maintenance.php?action=download_server_files&mode=full" download class="sm:col-span-2 bg-emerald-700 hover:bg-emerald-600 text-white font-bold p-3 rounded-xl text-xs transition flex items-center gap-2.5 shadow-sm">
                    <i class="fa-solid fa-boxes-packing text-emerald-200 text-base"></i>
                    <div class="text-left">
                        <span class="block font-bold">3. Unduh Semua File Server + Bukti Foto (.zip)</span>
                        <span class="block text-[10px] text-emerald-100 font-normal">Seluruh source code, server-tools, dan folder uploads/</span>
                    </div>
                </a>
            </div>

            <div class="pt-2 border-t border-indigo-500/30 flex items-center justify-between text-[11px]">
                <a href="admin" class="text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-gauge"></i> Masuk ke Dashboard Admin &rarr;
                </a>
                <span class="text-slate-400 text-[10px]">Auto-Restore: <code>MIGRASI_RESTORE_PC_BARU.bat</code></span>
            </div>
        </div>
        <?php else: ?>
        <!-- Pilihan Jika Belum Terdeteksi Superadmin -->
        <div class="p-4 bg-slate-800/60 rounded-2xl border border-slate-700/80 text-left space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                    <i class="fa-solid fa-server text-indigo-400"></i> Migrasi ke PC Server Baru?
                </span>
                <span class="text-[10px] bg-slate-700 text-slate-300 px-2 py-0.5 rounded-full font-bold">Khusus Superadmin</span>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed">
                Untuk mengunduh database dan semua file folder server dalam format .sql dan .zip, silakan autentikasi sebagai Superadmin.
            </p>
            <button type="button" onclick="openSuperadminModal()" class="w-full bg-indigo-600/80 hover:bg-indigo-600 text-white font-bold text-xs py-2 px-3 rounded-xl transition flex items-center justify-center gap-2 border border-indigo-500/40 cursor-pointer">
                <i class="fa-solid fa-key text-indigo-200"></i>
                <span>Buka Unduhan Migrasi Server (Login Superadmin)</span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Tombol Standar Navigasi -->
        <div class="pt-3 border-t border-slate-700 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="login" class="w-full sm:w-auto bg-slate-700 hover:bg-slate-600 text-slate-200 font-bold px-5 py-2.5 rounded-xl text-xs transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Halaman Login Utama</span>
            </a>
            <button onclick="window.location.reload()" class="w-full sm:w-auto bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold px-4 py-2.5 rounded-xl text-xs transition flex items-center justify-center gap-2 border border-slate-600">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span>Muat Ulang Halaman</span>
            </button>
        </div>

    </div>

    <!-- ============================================================= -->
    <!-- MODAL AUTENTIKASI SUPERADMIN UNTUK MIGRASI                    -->
    <!-- ============================================================= -->
    <div id="modalSuperadminAuth" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-slate-800 border border-slate-700 rounded-3xl max-w-sm w-full p-6 text-left shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-700 pb-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center border border-amber-500/30">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm text-white">Verifikasi Superadmin</h4>
                        <p class="text-[10px] text-slate-400">Akses Pengunduhan Data Server</p>
                    </div>
                </div>
                <button type="button" onclick="closeSuperadminModal()" class="text-slate-400 hover:text-white transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="formSuperadminAuth" onsubmit="submitSuperadminAuth(event)" class="space-y-3">
                <div id="authAlert" class="hidden text-xs p-2.5 rounded-xl border"></div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Username Superadmin</label>
                    <input type="text" id="authUsername" placeholder="Contoh: Daniel atau Admin" required
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-medium focus:border-indigo-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Password atau PIN</label>
                    <input type="password" id="authPassword" placeholder="Masukkan password atau PIN" required
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-medium focus:border-indigo-500 focus:outline-none transition">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeSuperadminModal()" class="px-3 py-2 rounded-xl text-xs text-slate-300 hover:bg-slate-700 transition">
                        Batal
                    </button>
                    <button type="submit" id="btnSubmitAuth" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-unlock"></i>
                        <span>Buka Akses Download</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openSuperadminModal() {
        document.getElementById('modalSuperadminAuth').classList.remove('hidden');
        document.getElementById('authUsername').focus();
    }
    function closeSuperadminModal() {
        document.getElementById('modalSuperadminAuth').classList.add('hidden');
    }

    async function submitSuperadminAuth(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitAuth');
        const alertEl = document.getElementById('authAlert');
        const username = document.getElementById('authUsername').value.trim();
        const password = document.getElementById('authPassword').value.trim();

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifikasi...';
        alertEl.classList.add('hidden');

        try {
            const res = await fetch('api/maintenance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'auth_superadmin',
                    username: username,
                    password: password
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                alertEl.className = "text-xs p-2.5 rounded-xl border bg-emerald-500/20 text-emerald-300 border-emerald-500/40 block";
                alertEl.innerHTML = '<i class="fa-solid fa-circle-check mr-1"></i> Berhasil! Memuat panel migrasi...';
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            } else {
                alertEl.className = "text-xs p-2.5 rounded-xl border bg-rose-500/20 text-rose-300 border-rose-500/40 block";
                alertEl.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> ' + (data.error || 'Autentikasi gagal.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-unlock"></i> <span>Buka Akses Download</span>';
            }
        } catch (err) {
            alertEl.className = "text-xs p-2.5 rounded-xl border bg-rose-500/20 text-rose-300 border-rose-500/40 block";
            alertEl.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> Terjadi kesalahan koneksi server.';
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-unlock"></i> <span>Buka Akses Download</span>';
        }
    }
    </script>

</body>
</html>
