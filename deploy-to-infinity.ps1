# Script Deploy Otomatis ke Hosting InfinityFree via FTP
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   MENGUNGGAH APLIKASI RETURN INBOUND KE INFINITYFREE      " -ForegroundColor Yellow
Write-Host "   Target: returninboundieg.great-site.net               " -ForegroundColor Yellow
Write-Host "==========================================================" -ForegroundColor Cyan

$ftpUser = "if0_38464190"
$ftpPass = "Dhaniel0"
$ftpHost = "ftpupload.net"
$remoteBase = "returninboundieg.great-site.net/htdocs"

# 1. Pastikan folder-folder remote dibuat terlebih dahulu
$remoteDirs = @(
    "api",
    "api/admin",
    "assets",
    "assets/css",
    "assets/js",
    "public",
    "public/css",
    "public/js",
    "uploads",
    "uploads/photos",
    "uploads/videos",
    "uploads/reception",
    "uploads/cache"
)

foreach ($dir in $remoteDirs) {
    Write-Host "Memeriksa folder: $dir ..." -ForegroundColor DarkGray
    curl.exe -s -u "${ftpUser}:${ftpPass}" --ftp-create-dirs "ftp://${ftpHost}/${remoteBase}/${dir}/" | Out-Null
}

# 2. Daftar file yang akan diupload (hanya kode aplikasi, kecualikan folder uploads/scratch/tools)
$files = Get-ChildItem -Recurse -File | Where-Object { 
    $_.FullName -notmatch '\\\.git' -and 
    $_.FullName -notmatch '\\\.github' -and
    $_.FullName -notmatch '\\uploads\\' -and
    $_.FullName -notmatch '\\scratch\\' -and
    $_.FullName -notmatch '\\server-tools\\' -and
    $_.Name -ne 'deploy-to-infinity.ps1' -and
    $_.Name -ne 'jalankan-server.bat' -and
    $_.Extension -ne '.log'
}

$total = $files.Count
$current = 0

foreach ($file in $files) {
    $current++
    $relPath = $file.FullName.Substring((Get-Location).Path.Length + 1).Replace('\', '/')
    $remoteUrl = "ftp://${ftpHost}/${remoteBase}/${relPath}"
    
    Write-Host "[$current/$total] Mengunggah $relPath ..." -ForegroundColor Green
    curl.exe -s -T $file.FullName -u "${ftpUser}:${ftpPass}" --ftp-create-dirs "$remoteUrl"
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   DEPLOY BERHASIL SELESAI 100%!                          " -ForegroundColor Green
Write-Host "   Buka: http://returninboundieg.great-site.net           " -ForegroundColor Yellow
Write-Host "==========================================================" -ForegroundColor Cyan
