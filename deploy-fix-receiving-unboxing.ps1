# Upload HANYA file perbaikan Receiving & Unboxing (8 Okt 2026) ke InfinityFree.
# Kredensial FTP dibaca dari deploy-to-infinity.ps1 (tidak ditulis ulang di sini).
# Cara pakai: klik kanan file ini > "Run with PowerShell" (dari folder retrun.inboud).
Set-Location -Path $PSScriptRoot
$src = Get-Content -Raw -Path ".\deploy-to-infinity.ps1"
function Get-Val($name) { if ($src -match "\`$$name\s*=\s*""([^""]+)""") { return $Matches[1] } else { throw "Nilai `$$name tidak ditemukan di deploy-to-infinity.ps1" } }
$ftpUser = Get-Val "ftpUser"; $ftpPass = Get-Val "ftpPass"; $ftpHost = Get-Val "ftpHost"; $remoteBase = Get-Val "remoteBase"

$files = @(
    "config.php",
    "reception.php",
    "sync_worker.php",
    "api/reception.php",
    "api/returns.php",
    "api/batch_lookup.php",
    "api/conditions.php",
    "api/expeditions.php",
    "assets/js/operator.js",
    "public/js/operator.js",
    "index.php",
    "uploads/.htaccess"
)

Write-Host "Mengunggah $($files.Count) file perbaikan ke $remoteBase ..." -ForegroundColor Cyan
$fail = 0
foreach ($rel in $files) {
    $local = Join-Path $PSScriptRoot ($rel -replace '/', '\')
    if (-not (Test-Path $local)) { Write-Host "  [LEWATI] $rel tidak ada" -ForegroundColor Yellow; continue }
    curl.exe -sS -T "$local" -u "${ftpUser}:${ftpPass}" --ftp-create-dirs "ftp://${ftpHost}/${remoteBase}/${rel}"
    if ($LASTEXITCODE -eq 0) { Write-Host "  [OK] $rel" -ForegroundColor Green } else { Write-Host "  [GAGAL] $rel (kode $LASTEXITCODE)" -ForegroundColor Red; $fail++ }
}
if ($fail -eq 0) { Write-Host "SELESAI: semua file terunggah. Buka https://returninboundieg.great-site.net lalu tekan Ctrl+F5." -ForegroundColor Green }
else { Write-Host "Ada $fail file gagal. Jalankan ulang script ini." -ForegroundColor Red }
if ([Environment]::UserInteractive -and -not [Console]::IsInputRedirected) {
    Read-Host "Tekan Enter untuk menutup"
}
