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

# 2. Buka port 80 dan 443 (HTTP & HTTPS Apache) di Windows Firewall
$ruleName80 = "Inbound Return - Apache HTTP 80"
if (-not (Get-NetFirewallRule -DisplayName $ruleName80 -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $ruleName80 -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Domain,Private,Public | Out-Null
}
$ruleName443 = "Inbound Return - Apache HTTPS 443"
if (-not (Get-NetFirewallRule -DisplayName $ruleName443 -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $ruleName443 -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow -Profile Domain,Private,Public | Out-Null
    Write-Host "[OK] Firewall: port 80 & 443 (HTTPS) dibuka" -ForegroundColor Green
} else {
    Write-Host "[OK] Firewall: port 80 & 443 (HTTPS) sudah terbuka" -ForegroundColor Green
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

# 3. Pasang & Jalankan Apache sebagai Windows Service (Anti-Putus, Auto-Restart jika crash)
$apacheExes = Get-ChildItem "C:\laragon\bin\apache" -Recurse -Filter httpd.exe -ErrorAction SilentlyContinue
if ($apacheExes) {
    $httpdPath = $apacheExes[0].FullName
    $srv = Get-Service -Name "Apache24" -ErrorAction SilentlyContinue
    if (-not $srv) {
        Write-Host "[*] Memasang Apache sebagai Windows Service (Apache24)..." -ForegroundColor Yellow
        & $httpdPath -k install -n "Apache24"
        sc.exe config Apache24 start= auto | Out-Null
        sc.exe failure Apache24 reset= 0 actions= restart/3000/restart/3000/restart/3000 | Out-Null
        Write-Host "[OK] Service Apache24 berhasil dipasang & auto-restart diaktifkan!" -ForegroundColor Green
    } else {
        sc.exe config Apache24 start= auto | Out-Null
        sc.exe failure Apache24 reset= 0 actions= restart/3000/restart/3000/restart/3000 | Out-Null
        Write-Host "[OK] Service Apache24 terdaftar (Startup: Otomatis, Auto-Restart: Aktif)" -ForegroundColor Green
    }
    
    # Jalankan service Apache24 jika belum aktif
    $srv = Get-Service -Name "Apache24" -ErrorAction SilentlyContinue
    if ($srv -and $srv.Status -ne 'Running') {
        Write-Host "[*] Menjalankan Service Apache24..." -ForegroundColor Yellow
        Start-Service -Name "Apache24" -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }
}

# 4. Tampilkan link berbasis IP (HTTPS wajib untuk kamera barcode HP)
$ips = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {
    $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254*' -and $_.InterfaceAlias -notmatch 'vEthernet|VirtualBox|VMware|Loopback'
}

Write-Host ""
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host " LINK AKSES HTTPS RESMI (Bisa dibuka dari HP / Laptop di Wi-Fi kantor yang sama):" -ForegroundColor Green
Write-Host "================================================================================" -ForegroundColor Cyan
foreach ($ip in $ips) {
    Write-Host ("  [+] Login Utama  : https://{0}/inbound_return/login   ({1})" -f $ip.IPAddress, $ip.InterfaceAlias) -ForegroundColor Yellow
    Write-Host ("      Scanner      : https://{0}/inbound_return/scanner" -f $ip.IPAddress) -ForegroundColor Gray
    Write-Host ("      Receiving    : https://{0}/inbound_return/reception" -f $ip.IPAddress) -ForegroundColor Gray
    Write-Host ("      Admin Portal : https://{0}/inbound_return/admin" -f $ip.IPAddress) -ForegroundColor Gray
    Write-Host ""
}
Write-Host "CATATAN:" -ForegroundColor White
Write-Host " - Kamera barcode scanner HP membutuhkan HTTPS. Jika muncul peringatan SSL di HP, klik 'Lanjutkan (Advanced -> Proceed)'." -ForegroundColor Gray
Write-Host " - Agar IP tidak berubah-ubah, minta tim IT mengunci IP PC ini (DHCP Reservation / Static IP)." -ForegroundColor Gray
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host ""
Read-Host "Tekan Enter untuk keluar"
