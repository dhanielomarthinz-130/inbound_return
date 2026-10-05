# server-tools/setup_lan_access.ps1
# Jalankan di PC SERVER (sebagai Administrator) agar sistem bisa dibuka dari laptop/HP lain di jaringan kantor.
$ErrorActionPreference = "SilentlyContinue"

# Minta hak Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Start-Process powershell -Verb RunAs -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    exit
}

Write-Host ""
Write-Host "=== SETUP AKSES LAN - SISTEM INBOUND RETURN ===" -ForegroundColor Cyan
Write-Host ""

# 1. Ubah profil jaringan dari Public ke Private (Public memblokir akses dari device lain)
Get-NetConnectionProfile | Where-Object { $_.NetworkCategory -eq 'Public' } | ForEach-Object {
    Set-NetConnectionProfile -InterfaceIndex $_.InterfaceIndex -NetworkCategory Private
    Write-Host "[OK] Profil jaringan '$($_.Name)' diubah: Public -> Private" -ForegroundColor Green
}

# 2. Buka port 80 (HTTP Apache/XAMPP) di Windows Firewall
$ruleName = "Inbound Return - Apache HTTP 80"
if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Domain,Private,Public | Out-Null
    Write-Host "[OK] Firewall: port 80 dibuka" -ForegroundColor Green
} else {
    Write-Host "[OK] Firewall: port 80 sudah terbuka" -ForegroundColor Green
}

# Izinkan juga httpd.exe XAMPP / Laragon jika ada
foreach ($exe in @("C:\xampp\apache\bin\httpd.exe")) {
    if (Test-Path $exe) {
        $n = "Inbound Return - httpd ($exe)"
        if (-not (Get-NetFirewallRule -DisplayName $n -ErrorAction SilentlyContinue)) {
            New-NetFirewallRule -DisplayName $n -Direction Inbound -Program $exe -Action Allow -Profile Domain,Private,Public | Out-Null
        }
    }
}
Get-ChildItem "C:\laragon\bin\apache" -Recurse -Filter httpd.exe -ErrorAction SilentlyContinue | ForEach-Object {
    $n = "Inbound Return - httpd ($($_.FullName))"
    if (-not (Get-NetFirewallRule -DisplayName $n -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $n -Direction Inbound -Program $_.FullName -Action Allow -Profile Domain,Private,Public | Out-Null
    }
}

# 3. Tampilkan link berbasis IP (lebih andal daripada nama komputer)
$ips = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {
    $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254*' -and $_.InterfaceAlias -notmatch 'vEthernet|VirtualBox|VMware|Loopback'
}

Write-Host ""
Write-Host "Buka salah satu link ini dari laptop / HP lain (harus di jaringan WiFi/LAN kantor yang sama):" -ForegroundColor White
foreach ($ip in $ips) {
    Write-Host ("   http://{0}/inbound_return/scanner   ({1})" -f $ip.IPAddress, $ip.InterfaceAlias) -ForegroundColor Yellow
}
Write-Host ""
Write-Host "CATATAN:" -ForegroundColor White
Write-Host " - Agar IP tidak berubah-ubah, minta IT set 'DHCP Reservation' / IP statis untuk PC server ini." -ForegroundColor Gray
Write-Host " - Jika masih gagal: pastikan laptop TIDAK memakai WiFi Guest (Guest biasanya memblokir antar device)." -ForegroundColor Gray
Write-Host ""
Read-Host "Tekan Enter untuk keluar"
