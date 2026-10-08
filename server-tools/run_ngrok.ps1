# server-tools/run_ngrok.ps1
# Script launcher untuk Ngrok Static Domain (1 Link Permanen Gratis Tanpa Berubah-ubah)

$projectDir = Split-Path -Parent $PSScriptRoot
$ngrokExe = Join-Path $projectDir "ngrok.exe"
$domainConfigFile = Join-Path $projectDir "ngrok_domain.txt"

# 1. Pastikan folder alias inbound_return ada di XAMPP / Laragon
$laragonWww = "C:\laragon\www\inbound_return"
$xamppHtdocs = "C:\xampp\htdocs\inbound_return"
if (Test-Path "C:\laragon\www" -and -not (Test-Path $laragonWww)) {
    cmd /c "mklink /j $laragonWww `"$projectDir`"" | Out-Null
}
if (Test-Path "C:\xampp\htdocs" -and -not (Test-Path $xamppHtdocs)) {
    cmd /c "mklink /j $xamppHtdocs `"$projectDir`"" | Out-Null
}

if (-not (Test-Path $ngrokExe)) {
    if (Test-Path "C:\laragon\bin\ngrok\ngrok.exe") {
        $ngrokExe = "C:\laragon\bin\ngrok\ngrok.exe"
    } else {
        Write-Host "[INFO] Sedang mendownload ngrok.exe portable..." -ForegroundColor Cyan
    $zipPath = Join-Path $projectDir "ngrok.zip"
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri "https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip" -OutFile $zipPath
        Expand-Archive -Path $zipPath -DestinationPath $projectDir -Force
        Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
    } catch {
        Write-Host "[ERROR] Gagal download ngrok.exe. Silakan cek koneksi internet." -ForegroundColor Red
        Read-Host "Tekan Enter untuk keluar..."
        exit 1
    }
}

# 3. Cek Authtoken & Static Domain
$currentDomain = ""
if (Test-Path $domainConfigFile) {
    $currentDomain = (Get-Content $domainConfigFile -Raw).Trim()
}

# Cek apakah authtoken ngrok sudah ada
$hasToken = $false
try {
    $chk = & $ngrokExe config check 2>&1
    if ($LASTEXITCODE -eq 0) {
        $hasToken = $true
    }
} catch {
    $hasToken = $false
}

if (-not $hasToken -or [string]::IsNullOrWhiteSpace($currentDomain)) {
    Clear-Host
    Write-Host ""
    Write-Host "================================================================================" -ForegroundColor Cyan
    Write-Host "            SETUP PERTAMA KALI: NGROK STATIC DOMAIN (1 LINK TETAP)             " -ForegroundColor Yellow
    Write-Host "================================================================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host " Setup ini hanya dilakukan 1x saja! Selanjutnya sistem akan otomatis berjalan. " -ForegroundColor Green
    Write-Host ""
    Write-Host " CARA MENDAPATKAN TOKEN & DOMAIN GRATIS DARI NGROK:" -ForegroundColor White
    Write-Host " 1. Buka https://dashboard.ngrok.com/ lalu login / daftar gratis." -ForegroundColor Gray
    Write-Host " 2. Ambil Token di menu: https://dashboard.ngrok.com/get-started/your-authtoken" -ForegroundColor Gray
    Write-Host " 3. Ambil Domain Tetap di menu: https://dashboard.ngrok.com/cloud-edge/domains" -ForegroundColor Gray
    Write-Host "    (Klik tombol 'Claim your free static domain', contoh: climb-fast-deer.ngrok-free.app)" -ForegroundColor Gray
    Write-Host ""
    Write-Host "--------------------------------------------------------------------------------" -ForegroundColor Cyan

    if (-not $hasToken) {
        Write-Host "Masukkan NGROK AUTHTOKEN Anda:" -ForegroundColor Yellow
        $tokenInput = Read-Host "Authtoken"
        $tokenInput = $tokenInput.Trim()

        if (-not [string]::IsNullOrWhiteSpace($tokenInput)) {
            & $ngrokExe config add-authtoken $tokenInput
            Write-Host "[OK] Authtoken berhasil disimpan!" -ForegroundColor Green
        } else {
            Write-Host "[ERROR] Authtoken tidak boleh kosong." -ForegroundColor Red
            Read-Host "Tekan Enter untuk keluar..."
            exit 1
        }
    }

    Write-Host ""
    Write-Host "Masukkan NGROK STATIC DOMAIN Anda (Contoh: climb-fast-deer.ngrok-free.app):" -ForegroundColor Yellow
    $domainInput = Read-Host "Static Domain"
    $domainInput = $domainInput.Trim()
    # Bersihkan jika user mem-paste dengan https:// atau trailing slash
    $domainInput = $domainInput -replace "^https?://", "" -replace "/.*$", ""

    if (-not [string]::IsNullOrWhiteSpace($domainInput)) {
        Set-Content -Path $domainConfigFile -Value $domainInput -Encoding UTF8
        $currentDomain = $domainInput
        Write-Host "[OK] Static Domain berhasil disimpan: $currentDomain" -ForegroundColor Green
        Start-Sleep -Seconds 2
    } else {
        Write-Host "[ERROR] Domain tidak boleh kosong." -ForegroundColor Red
        Read-Host "Tekan Enter untuk keluar..."
        exit 1
    }
}

# Bersihkan format domain
$currentDomain = $currentDomain -replace "^https?://", "" -replace "/.*$", ""
$fullLoginUrl = "https://$currentDomain/inbound_return/login"

# Simpan info link ke Desktop
try {
    $desktopPath = [Environment]::GetFolderPath("Desktop")
    $desktopFile = Join-Path $desktopPath "LINK_PERMANEN_NGROK.txt"
    $fileContent = @"
================================================================================
          LINK RESMI & TETAP SISTEM INBOUND RETURN (NGROK STATIC DOMAIN)
================================================================================

Link Login Utama (Buka di browser Laptop / HP kantor / rumah):
$fullLoginUrl

Link Alternatif:
https://$currentDomain/retrun.inboud/login

--------------------------------------------------------------------------------
PETUNJUK PENGGUNAAN:
1. Link di atas PERMANEN (TIDAK AKAN BERUBAH-UBAH).
2. Kirim link di atas ke WhatsApp Anda atau simpan di Bookmark browser.
3. Di laptop lain / HP, cukup buka link di atas (TIDAK PERLU INSTALL APA-APA).
4. Pastikan jendela hitam 'JALANKAN_NGROK_PERMANEN' di PC Server tetap terbuka.
================================================================================
"@
    Set-Content -Path $desktopFile -Value $fileContent -Encoding UTF8
} catch {}

# Copy link login ke clipboard
try {
    Set-Clipboard -Value $fullLoginUrl
} catch {}

Clear-Host
Write-Host ""
Write-Host "================================================================================" -ForegroundColor Green
Write-Host "    SUCCESS! NGROK TUNNEL STATIC BERJALAN DENGAN LINK RESMI PERMANEN           " -ForegroundColor Green
Write-Host "================================================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  >> LINK BERIKUT SUDAH OTOMATIS DI-COPY KE CLIPBOARD! <<" -ForegroundColor Magenta
Write-Host "  >> FILE LINK TERSIMPAN DI DESKTOP: LINK_PERMANEN_NGROK.txt <<" -ForegroundColor Gray
Write-Host ""
Write-Host "  Salin & Buka link ini di Laptop / HP (Tidak akan berubah lagi):" -ForegroundColor White
Write-Host ""
Write-Host "  👉 $fullLoginUrl" -ForegroundColor Yellow -BackgroundColor DarkBlue
Write-Host ""
Write-Host "--------------------------------------------------------------------------------" -ForegroundColor Cyan
Write-Host "  TIPS:" -ForegroundColor White
Write-Host "  - Kirim link di atas ke WhatsApp Anda / Bookmark di Chrome." -ForegroundColor Gray
Write-Host "  - Laptop lain CUKUP BUKA LINK TERSEBUT di browser (bebas tanpa login/install ngrok)." -ForegroundColor Gray
Write-Host "  - Jendela ini JANGAN DITUTUP selama jam kerja agar server tetap online." -ForegroundColor DarkYellow
Write-Host "  - Tekan Ctrl+C jika ingin mematikan koneksi." -ForegroundColor DarkGray
Write-Host "--------------------------------------------------------------------------------" -ForegroundColor Cyan
Write-Host ""
Write-Host "Memulai Ngrok Tunnel Traffic Monitor..." -ForegroundColor Cyan
Write-Host ""

# Jalankan ngrok tunnel
& $ngrokExe http 80 --url "https://$currentDomain"
