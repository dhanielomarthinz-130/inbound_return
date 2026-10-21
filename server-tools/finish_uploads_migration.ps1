# server-tools/finish_uploads_migration.ps1
# Script untuk finalisasi migrasi uploads ke Drive D:
$sourceDir = "c:\Users\USER\Documents\GitHub\inbound_return\uploads"
$tempOldDir = "c:\Users\USER\Documents\GitHub\inbound_return\uploads_old_c"
$destDir   = "D:\inbound_return_uploads"

Write-Host "Memeriksa folder tujuan di D:..." -ForegroundColor Cyan
if (-not (Test-Path $destDir)) {
    Write-Host "[ERROR] Folder $destDir tidak ditemukan!" -ForegroundColor Red
    exit 1
}

$destCount = (Get-ChildItem $destDir -Recurse -File | Measure-Object).Count
Write-Host "[OK] File terverifikasi di $destDir : $destCount file" -ForegroundColor Green

Write-Host "Menghentikan httpd sebentar untuk swap junction..." -ForegroundColor Cyan
Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

if (Test-Path $sourceDir) {
    Write-Host "Mengganti nama uploads lama di C: -> uploads_old_c..." -ForegroundColor Yellow
    Rename-Item -Path $sourceDir -NewName "uploads_old_c" -Force
}

Write-Host "Membuat Directory Junction (mklink /J): $sourceDir -> $destDir..." -ForegroundColor Cyan
cmd /c "mklink /J `"$sourceDir`" `"$destDir`""

Write-Host "Menyalakan kembali Apache httpd..." -ForegroundColor Cyan
Start-Process -FilePath 'C:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\bin\httpd.exe' -ArgumentList '-d C:/laragon/bin/apache/httpd-2.4.66-260223-Win64-VS18' -WindowStyle Hidden
Start-Sleep -Seconds 2

# Verifikasi akses junction
if (Test-Path "$sourceDir\photos") {
    Write-Host "[SUKSES] Junction uploads -> D:\inbound_return_uploads AKTIF dan terbaca!" -ForegroundColor Green
    
    # Hapus folder backup di C: untuk membebaskan 83 GB
    if (Test-Path $tempOldDir) {
        Write-Host "Membersihkan folder lama di C: untuk membebaskan ruang disk C:..." -ForegroundColor Yellow
        Remove-Item -Path $tempOldDir -Recurse -Force -ErrorAction SilentlyContinue
        Write-Host "[SUKSES] Folder lama di C: telah dihapus!" -ForegroundColor Green
    }
} else {
    Write-Host "[PERINGATAN] Gagal membaca junction. Memulihkan folder..." -ForegroundColor Red
    Remove-Item -Path $sourceDir -Force -ErrorAction SilentlyContinue
    Rename-Item -Path $tempOldDir -NewName "uploads" -Force
}

$cFree = [math]::Round(((Get-PSDrive C).Free / 1GB), 2)
$dFree = [math]::Round(((Get-PSDrive D).Free / 1GB), 2)
Write-Host "Kapasitas Disk Saat Ini:" -ForegroundColor White
Write-Host "  Disk C: Free $cFree GB" -ForegroundColor Green
Write-Host "  Disk D: Free $dFree GB" -ForegroundColor Green
