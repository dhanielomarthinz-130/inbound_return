# server-tools/run_tunnel.ps1
$ErrorActionPreference = "SilentlyContinue"

# 1. Pastikan junction inbound_return ada di Laragon dan XAMPP
$projectDir = Split-Path -Parent $PSScriptRoot
$laragonWww = "C:\laragon\www\inbound_return"
$xamppHtdocs = "C:\xampp\htdocs\inbound_return"

if (Test-Path "C:\laragon\www" -and -not (Test-Path $laragonWww)) {
    cmd /c "mklink /j $laragonWww `"$projectDir`"" | Out-Null
}
if (Test-Path "C:\xampp\htdocs" -and -not (Test-Path $xamppHtdocs)) {
    cmd /c "mklink /j $xamppHtdocs `"$projectDir`"" | Out-Null
}

# 2. Cari executable cloudflared
$cfExe = Join-Path $projectDir "cloudflared.exe"
if (-not (Test-Path $cfExe)) {
    $possiblePaths = @(
        "C:\laragon\cloudflared.exe",
        "C:\xampp\cloudflared.exe",
        "C:\cloudflared.exe"
    )
    foreach ($p in $possiblePaths) {
        if (Test-Path $p) {
            $cfExe = $p
            break
        }
    }
}

if (-not (Test-Path $cfExe)) {
    Write-Host "[INFO] Sedang mengunduh cloudflared.exe otomatis..." -ForegroundColor Cyan
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe" -OutFile $cfExe -UseBasicParsing
    } catch {
        Write-Host "[ERROR] Gagal mengunduh cloudflared. Pastikan server terhubung ke internet." -ForegroundColor Red
        Read-Host "Tekan Enter untuk keluar..."
        exit 1
    }
}

# 3. Log file untuk menangkap URL
$tempDir = [System.IO.Path]::GetTempPath()
$logFile = Join-Path $tempDir "cf_tunnel_inbound.log"
if (Test-Path $logFile) { Remove-Item $logFile -Force -ErrorAction SilentlyContinue }

# 4. Jalankan cloudflared di background process
$pinfo = New-Object System.Diagnostics.ProcessStartInfo
$pinfo.FileName = $cfExe
$pinfo.Arguments = "tunnel --url http://127.0.0.1:80 --http-host-header localhost --logfile `"$logFile`""
$pinfo.UseShellExecute = $false
$pinfo.CreateNoWindow = $true

$proc = [System.Diagnostics.Process]::Start($pinfo)

if (-not $proc) {
    Write-Host "[ERROR] Gagal memulai proses cloudflared." -ForegroundColor Red
    Read-Host "Tekan Enter untuk keluar..."
    exit 1
}

# 5. Loop tunggu URL keluar dari log
Write-Host ""
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host "   SEDANG MENGHUBUNGKAN SISTEM INBOUND RETURN KE JALUR AMAN INTERNET...        " -ForegroundColor Yellow
Write-Host "   Mohon tunggu 5 - 10 detik...                                                " -ForegroundColor Gray
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host ""

$foundUrl = $null
$timeout = 30
$elapsed = 0

while ($elapsed -lt $timeout -and -not $foundUrl) {
    Start-Sleep -Seconds 1
    $elapsed++
    if (Test-Path $logFile) {
        $lines = Get-Content $logFile -ErrorAction SilentlyContinue
        foreach ($line in $lines) {
            if ($line -match 'https://[a-zA-Z0-9-]+\.trycloudflare\.com') {
                $foundUrl = $matches[0]
                break
            }
        }
    }
    Write-Host -NoNewline "." -ForegroundColor Green
}
Write-Host ""

if ($foundUrl) {
    $fullUrl = "$foundUrl/inbound_return/login"
    
    # Simpan ke clipboard jika ada antarmuka GUI
    try {
        Set-Clipboard -Value $fullUrl
    } catch {}

    # Buat file teks di Desktop Server
    $desktopPath = [Environment]::GetFolderPath("Desktop")
    $desktopFile = Join-Path $desktopPath "LINK_AKSES_DI_RUMAH.txt"
    $fileContent = @"
================================================================================
           LINK RESMI AKSES SISTEM INBOUND RETURN DARI LUAR KANTOR / RUMAH
================================================================================

Buka link berikut di Google Chrome / Browser di Laptop atau HP Anda:

$fullUrl

--------------------------------------------------------------------------------
PETUNJUK:
1. Kirim link di atas ke WhatsApp Anda sendiri, lalu buka di laptop rumah.
2. Jendela hitam server ("start_online_tunnel") TIDAK BOLEH DITUTUP agar koneksi tetap hidup.
3. Di laptop rumah, Anda TIDAK PERLU menyalakan script atau bat apapun. Cukup buka link di atas.
================================================================================
"@
    try {
        Set-Content -Path $desktopFile -Value $fileContent -Encoding UTF8
    } catch {}

    Clear-Host
    Write-Host ""
    Write-Host "================================================================================" -ForegroundColor Green
    Write-Host "      SUCCESS! SISTEM INBOUND RETURN SIAP DIAKSES DARI RUMAH / LUAR KANTOR      " -ForegroundColor Green
    Write-Host "================================================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "  >> LINK BERIKUT SUDAH OTOMATIS DI-COPY KE CLIPBOARD! <<" -ForegroundColor Magenta
    Write-Host "  >> FILE LINK JUGA SUDAH DISIMPAN DI DESKTOP: LINK_AKSES_DI_RUMAH.txt <<" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  Salin dan buka link ini di Google Chrome Laptop / HP di Rumah:" -ForegroundColor White
    Write-Host ""
    Write-Host "  👉 $fullUrl" -ForegroundColor Yellow -BackgroundColor DarkBlue
    Write-Host ""
    Write-Host "--------------------------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host "  PETUNJUK PENTING:" -ForegroundColor White
    Write-Host "  1. Kirim link di atas ke WhatsApp Anda sendiri (Chat WA ke nomor sendiri / Saved Messages)." -ForegroundColor Gray
    Write-Host "  2. Buka WhatsApp Web di Laptop Rumah, lalu klik link tersebut." -ForegroundColor Gray
    Write-Host "  3. JANGAN TUTUP jendela ini di Server selama Anda ingin mengakses dari rumah!" -ForegroundColor Red
    Write-Host "  4. Di laptop rumah, Anda CUKUP BUKA LINK TERSEBUT di browser (tidak perlu install apa-apa)." -ForegroundColor Gray
    Write-Host "--------------------------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host "  Tekan Ctrl+C atau tutup jendela ini jika ingin menghentikan akses luar kantor." -ForegroundColor DarkYellow
    Write-Host ""

    # Tunggu sampai proses dihentikan
    while (-not $proc.HasExited) {
        Start-Sleep -Seconds 2
    }
} else {
    Write-Host ""
    Write-Host "[GAGAL] Tidak dapat menemukan URL Cloudflare dalam $timeout detik." -ForegroundColor Red
    Write-Host "Silakan periksa koneksi internet server atau coba jalankan kembali." -ForegroundColor Yellow
    if ($proc -and -not $proc.HasExited) {
        $proc.Kill()
    }
    Read-Host "Tekan Enter untuk keluar..."
}
