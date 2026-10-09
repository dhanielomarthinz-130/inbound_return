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

    <div class="max-w-lg w-full bg-slate-800/90 rounded-3xl p-8 md:p-10 border border-slate-700/80 shadow-2xl text-center space-y-6">
        
        <!-- Logo IEG & Animated Maintenance Icon -->
        <div class="flex items-center justify-center space-x-3 mb-2">
            <div class="w-12 h-12 rounded-2xl bg-white p-1.5 flex items-center justify-center shadow-md">
                <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
            </div>
            <div class="text-left">
                <h2 class="font-bold text-white text-base leading-tight">Inbound Return IEG</h2>
                <p class="text-[10px] text-slate-400">IEG</p>
            </div>
        </div>

        <div class="relative w-20 h-20 mx-auto flex items-center justify-center">
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
            <p class="text-xs text-slate-400 leading-relaxed max-w-sm mx-auto">
                Layanan sistem Inbound Return saat ini sedang dalam proses pemeliharaan berkala atau pembaruan database oleh Super Administrator.
            </p>
        </div>

        <!-- Spinner Bola-Bola Merah Kuning Hijau -->
        <div class="py-2">
            <div class="traffic-loader">
                <div class="traffic-ball traffic-ball-red"></div>
                <div class="traffic-ball traffic-ball-yellow"></div>
                <div class="traffic-ball traffic-ball-green"></div>
            </div>
            <span class="text-[11px] text-slate-500 block mt-2 font-mono">Sedang mengoptimasi database & sistem...</span>
        </div>

        <div class="pt-4 border-t border-slate-700 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="login" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-sm">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Masuk sebagai Superadmin</span>
            </a>
            <button onclick="window.location.reload()" class="w-full sm:w-auto bg-slate-700 hover:bg-slate-600 text-slate-200 font-semibold px-4 py-2.5 rounded-xl text-xs transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span>Muat Ulang Halaman</span>
            </button>
        </div>

    </div>

</body>
</html>
