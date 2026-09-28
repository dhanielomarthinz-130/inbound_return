# ==============================================================================
# SCRIPT INSTALASI OTOMATIS INBOUND RETURN IEG SERVER LOCALHOST
# Bisa dijalankan langsung di PowerShell atau di-download dari GitHub
# ==============================================================================
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
Clear-Host

Write-Host "====================================================================" -ForegroundColor Cyan
Write-Host "   PENGINSTAL OTOMATIS SISTEM INBOUND RETURN IEG - SERVER LOKAL    " -ForegroundColor Yellow
Write-Host "   PT. Indo Express Global (IEG)                                    " -ForegroundColor White
Write-Host "====================================================================" -ForegroundColor Cyan
Write-Host ""

$repoUrl = "https://github.com/dhanielomarthinz-130/inbound_return.git"
$zipUrl  = "https://github.com/dhanielomarthinz-130/inbound_return/archive/refs/heads/main.zip"

# 1. Tentukan Direktori Instalasi
$defaultDir = "C:\xampp\htdocs\inbound_return"
if (Test-Path "C:\laragon\www") {
    $defaultDir = "C:\laragon\www\inbound_return"
}

# Jika script dijalankan di dalam folder repo yang sudah ada, gunakan folder saat ini
if (Test-Path "$PSScriptRoot\config.php") {
    $installDir = $PSScriptRoot
    Write-Host "[✓] Dijalankan di dalam folder aplikasi: $installDir" -ForegroundColor Green
} else {
    $installDir = $defaultDir
    Write-Host "[*] Target instalasi server: $installDir" -ForegroundColor Yellow
    if (!(Test-Path $installDir)) {
        New-Item -ItemType Directory -Path $installDir -Force | Out-Null
    }
}

# 2. Ambil Source Code dari GitHub
Write-Host ""
Write-Host "[1/5] Mengunduh / Memperbarui source code dari GitHub..." -ForegroundColor Cyan

$gitInstalled = $null
try { $gitInstalled = (Get-Command git -ErrorAction SilentlyContinue) } catch {}

if ($gitInstalled) {
    if (Test-Path "$installDir\.git") {
        Write-Host "      Melakukan 'git pull' untuk mengambil update terbaru..." -ForegroundColor DarkGray
        git -C $installDir pull origin main
    } else {
        Write-Host "      Melakukan 'git clone' dari repository GitHub..." -ForegroundColor DarkGray
        git clone $repoUrl $installDir
    }
} else {
    Write-Host "      Git tidak terdeteksi, mengunduh file ZIP dari GitHub..." -ForegroundColor DarkGray
    $tempZip = "$env:TEMP\inbound_return_latest.zip"
    Invoke-WebRequest -Uri $zipUrl -OutFile $tempZip -UseBasicParsing
    
    $tempExtract = "$env:TEMP\inbound_extract"
    if (Test-Path $tempExtract) { Remove-Item -Recurse -Force $tempExtract }
    Expand-Archive -Path $tempZip -DestinationPath $tempExtract -Force
    
    Copy-Item -Path "$tempExtract\inbound_return-main\*" -Destination $installDir -Recurse -Force
    Remove-Item -Force $tempZip -ErrorAction SilentlyContinue
    Remove-Item -Recurse -Force $tempExtract -ErrorAction SilentlyContinue
    Write-Host "      [✓] Ekstraksi source code berhasil." -ForegroundColor Green
}

# 3. Deteksi PHP Executable
Write-Host ""
Write-Host "[2/5] Mendeteksi PHP di server lokal..." -ForegroundColor Cyan
$phpBin = "php"
$foundPhp = $false

$phpCandidates = @(
    "php",
    "C:\xampp\php\php.exe",
    "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe",
    "C:\laragon\bin\php\php-8.2*\php.exe",
    "C:\laragon\bin\php\php-8.1*\php.exe"
)

foreach ($c in $phpCandidates) {
    if ($c -eq "php") {
        try {
            $ver = & php -v 2>$null
            if ($ver) { $phpBin = "php"; $foundPhp = $true; break }
        } catch {}
    } else {
        $resolved = Resolve-Path $c -ErrorAction SilentlyContinue
        if ($resolved) {
            $phpBin = $resolved[0].Path
            $foundPhp = $true
            break
        }
    }
}

if ($foundPhp) {
    Write-Host "      [✓] PHP ditemukan: $phpBin" -ForegroundColor Green
} else {
    Write-Host "      [!] PERINGATAN: PHP tidak ditemukan di PATH, XAMPP, ataupun Laragon." -ForegroundColor Red
    Write-Host "          Silakan install XAMPP atau Laragon terlebih dahulu." -ForegroundColor Yellow
}

# 4. Inisialisasi Database MySQL & Migrasi Tabel
Write-Host ""
Write-Host "[3/5] Memeriksa & Menyiapkan Database MySQL..." -ForegroundColor Cyan

if ($foundPhp) {
    Set-Location $installDir
    $migrateResult = & $phpBin "api/migrate.php" 2>&1
    
    # Cek apakah database berhasil
    if ($migrateResult -match '"success":\s*true') {
        Write-Host "      [✓] Database 'inbound_return' siap!" -ForegroundColor Green
        Write-Host "      [✓] Semua tabel & auto-patch kolom berhasil dieksekusi." -ForegroundColor Green
        Write-Host "      [✓] Akun pengguna resmi berhasil disinkronkan." -ForegroundColor Green
    } else {
        Write-Host "      [!] Respon migrasi:" -ForegroundColor Yellow
        Write-Host "          $migrateResult" -ForegroundColor DarkGray
        Write-Host "          CATATAN: Pastikan service MySQL di XAMPP / Laragon sudah dinyalakan (START)!" -ForegroundColor Yellow
    }
}

# 5. Cari IP Address Lokal Server (LAN / WiFi)
Write-Host ""
Write-Host "[4/5] Mendeteksi IP Address Jaringan Lokal (LAN/Wi-Fi)..." -ForegroundColor Cyan
$serverIp = "127.0.0.1"
try {
    $ips = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | 
           Where-Object { $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*" }
    if ($ips) {
        $serverIp = $ips[0].IPAddress
    }
} catch {
    # Fallback ipconfig
    $ipMatches = (ipconfig) -match "(IPv4 Address|Alamat IPv4)[^:]*:\s*([0-9\.]+)"
    if ($matches -and $matches[2]) {
        $serverIp = $matches[2].Trim()
    }
}

Write-Host "      [✓] IP Server Lokal: $serverIp" -ForegroundColor Green

# 6. Rangkuman Akses & Pilihan Server
Write-Host ""
Write-Host "====================================================================" -ForegroundColor Cyan
Write-Host "                     INSTALASI SERVER SELESAI                       " -ForegroundColor Green
Write-Host "====================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Daftar Akun Pengguna Default:" -ForegroundColor White
Write-Host "  ----------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "  1. Superadmin : Username: " -NoNewline; Write-Host "Daniel" -ForegroundColor Yellow -NoNewline; Write-Host "    | Password: " -NoNewline; Write-Host "Dh@niel0" -ForegroundColor Yellow
Write-Host "  2. Admin      : Username: " -NoNewline; Write-Host "Admin" -ForegroundColor Yellow -NoNewline; Write-Host "     | Password: " -NoNewline; Write-Host "Password01" -ForegroundColor Yellow
Write-Host "  3. Operator 1 : Pilih dari Dropdown (PIN: " -NoNewline; Write-Host "123456" -ForegroundColor Yellow -NoNewline; Write-Host " | Pass: Password01)"
Write-Host "  4. Operator 2 : Pilih dari Dropdown (PIN: " -NoNewline; Write-Host "123456" -ForegroundColor Yellow -NoNewline; Write-Host " | Pass: Password01)"
Write-Host "  ----------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host ""

$folderName = Split-Path $installDir -Leaf
$xamppUrl = "http://localhost/$folderName/"
$lanUrl   = "http://${serverIp}/$folderName/"

Write-Host "  [A] Jika menggunakan Apache (XAMPP / Laragon sudah START):" -ForegroundColor Cyan
Write-Host "      • Akses di PC Server ini : $xamppUrl" -ForegroundColor White
Write-Host "      • Akses dari HP/PC lain  : $lanUrl" -ForegroundColor White
Write-Host ""

# Pilihan: Jalankan PHP Built-in Server otomatis pada Port 8080
$runPhpServer = Read-Host "Apakah Anda ingin langsung menyalakan server lokal mandiri (Port 8080)? [Y/N, default Y]"
if ([string]::IsNullOrWhiteSpace($runPhpServer) -or $runPhpServer -match '^[Yy]') {
    $port = 8080
    $localServerUrl = "http://localhost:${port}/"
    $lanServerUrl   = "http://${serverIp}:${port}/"
    
    Write-Host ""
    Write-Host "====================================================================" -ForegroundColor Cyan
    Write-Host "   SERVER LOCAL SEDANG BERJALAN DI PORT $port                       " -ForegroundColor Green
    Write-Host "====================================================================" -ForegroundColor Cyan
    Write-Host "   • PC Server ini        : $localServerUrl" -ForegroundColor Yellow
    Write-Host "   • Barcode Scanner / HP : $lanServerUrl" -ForegroundColor Yellow
    Write-Host "====================================================================" -ForegroundColor Cyan
    Write-Host "   Tekan Ctrl + C untuk menghentikan server." -ForegroundColor DarkGray
    Write-Host ""
    
    Start-Process $localServerUrl
    & $phpBin -S "0.0.0.0:${port}" -t $installDir
} else {
    Start-Process $xamppUrl
}
