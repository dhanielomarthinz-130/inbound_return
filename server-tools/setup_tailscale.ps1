# server-tools/setup_tailscale.ps1
# Jalankan di PC SERVER. Memasang Tailscale + membuat link HTTPS permanen untuk Sistem Inbound Return.
$ErrorActionPreference = "SilentlyContinue"

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Start-Process powershell -Verb RunAs -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    exit
}

function Get-TsExe {
    foreach ($p in @("C:\Program Files\Tailscale\tailscale.exe", "C:\Program Files (x86)\Tailscale\tailscale.exe")) {
        if (Test-Path $p) { return $p }
    }
    $cmd = Get-Command tailscale -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return $null
}

Write-Host ""
Write-Host "=== SETUP TAILSCALE - LINK PERMANEN INBOUND RETURN ===" -ForegroundColor Cyan
Write-Host ""

# 1. Install Tailscale jika belum ada
$ts = Get-TsExe
if (-not $ts) {
    Write-Host "[1/4] Menginstall Tailscale..." -ForegroundColor Yellow
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if ($winget) {
        winget install --id Tailscale.Tailscale -e --silent --accept-package-agreements --accept-source-agreements | Out-Null
    }
    $ts = Get-TsExe
    if (-not $ts) {
        $setup = Join-Path $env:TEMP "tailscale-setup.exe"
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri "https://pkgs.tailscale.com/stable/tailscale-setup-latest.exe" -OutFile $setup -UseBasicParsing
        Start-Process $setup -ArgumentList "/quiet" -Wait
        Start-Sleep -Seconds 5
        $ts = Get-TsExe
    }
    if (-not $ts) {
        Write-Host "[GAGAL] Tailscale tidak berhasil diinstall. Install manual dari https://tailscale.com/download/windows lalu jalankan script ini lagi." -ForegroundColor Red
        Read-Host "Tekan Enter untuk keluar"; exit 1
    }
}
Write-Host "[1/4] Tailscale terpasang: $ts" -ForegroundColor Green

# 2. Login (akan membuka browser jika belum login)
Write-Host "[2/4] Menghubungkan ke akun Tailscale (login di browser jika diminta)..." -ForegroundColor Yellow
& $ts up --unattended | Out-Host
$status = & $ts status --json | Out-String | ConvertFrom-Json
if (-not $status -or $status.BackendState -ne 'Running') {
    Write-Host "[GAGAL] Belum login. Selesaikan login di browser, lalu jalankan script ini lagi." -ForegroundColor Red
    Read-Host "Tekan Enter untuk keluar"; exit 1
}
Write-Host "[2/4] Terhubung sebagai: $($status.Self.HostName)" -ForegroundColor Green

# 3. Buka firewall port 80 (untuk akses via IP Tailscale)
$ruleName = "Inbound Return - Apache HTTP 80"
if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Domain,Private,Public | Out-Null
}
Write-Host "[3/4] Firewall port 80 siap" -ForegroundColor Green

# 4. Aktifkan HTTPS permanen (wajib agar kamera scanner bisa dipakai di browser)
Write-Host "[4/4] Mengaktifkan link HTTPS permanen (tailscale serve)..." -ForegroundColor Yellow
$serveOut = & $ts serve --bg http://127.0.0.1:80 2>&1 | Out-String
Write-Host $serveOut -ForegroundColor DarkGray

$dns = ($status.Self.DNSName).TrimEnd('.')
$ip4 = (& $ts ip -4 | Select-Object -First 1)

$httpsOk = $serveOut -notmatch 'enable|not enabled|disabled|error'
Write-Host ""
Write-Host "================================================================================" -ForegroundColor Green
Write-Host "  LINK PERMANEN (tidak akan berubah):" -ForegroundColor Green
Write-Host ""
if ($httpsOk -and $dns) {
    Write-Host "  https://$dns/inbound_return/login" -ForegroundColor Yellow -BackgroundColor DarkBlue
    Write-Host "  https://$dns/inbound_return/scanner" -ForegroundColor Yellow -BackgroundColor DarkBlue
} else {
    Write-Host "  [!] HTTPS belum aktif. Buka https://login.tailscale.com/admin/dns lalu:" -ForegroundColor Red
    Write-Host "      1) Aktifkan 'MagicDNS'   2) Klik 'Enable HTTPS'" -ForegroundColor Red
    Write-Host "      Setelah itu jalankan script ini sekali lagi." -ForegroundColor Red
}
Write-Host ""
Write-Host "  Cadangan (tanpa kamera, http biasa): http://$ip4/inbound_return/login" -ForegroundColor Gray
Write-Host "================================================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Di laptop / HP lain: install aplikasi Tailscale, login dengan AKUN YANG SAMA, lalu buka link di atas." -ForegroundColor White
Write-Host "Tailscale berjalan otomatis sebagai service - tidak perlu jendela yang dibiarkan terbuka." -ForegroundColor White

if ($httpsOk -and $dns) {
    $desktopFile = Join-Path ([Environment]::GetFolderPath("Desktop")) "LINK_PERMANEN_TAILSCALE.txt"
    @"
LINK PERMANEN SISTEM INBOUND RETURN (via Tailscale)

Login   : https://$dns/inbound_return/login
Scanner : https://$dns/inbound_return/scanner

Syarat di laptop/HP: install Tailscale (https://tailscale.com/download) dan login dengan akun yang sama.
"@ | Set-Content -Path $desktopFile -Encoding UTF8
    try { Set-Clipboard -Value "https://$dns/inbound_return/login" } catch {}
    Write-Host "Link juga disimpan di Desktop: LINK_PERMANEN_TAILSCALE.txt (dan sudah di-copy)" -ForegroundColor Gray
}
Write-Host ""
Read-Host "Tekan Enter untuk keluar"
