@echo off
chcp 65001 >nul
title 2. Update Otomatis dari GitHub - Inbound Return IEG
color 0A

REM Masuk ke folder root proyek (satu tingkat di atas server-tools)
cd /d "%~dp0.."

echo ====================================================================
echo     [2/3] MEMPERBARUI SISTEM DARI GITHUB KE PC SERVER LOKAL
echo ====================================================================
echo   Direktori Proyek: %CD%
echo.

REM 1. Cek Git dan tarik perubahan terbaru
where git >nul 2>nul
if %errorlevel% neq 0 goto :download_zip

echo [*] Menjalankan git pull origin main...
git pull origin main
goto :after_git

:download_zip
echo [!] Git tidak terdeteksi di PATH, mengunduh update via PowerShell...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$zip = Join-Path $env:TEMP 'latest.zip'; $dest = Join-Path $env:TEMP 'update_temp'; iwr -useb 'https://github.com/dhanielomarthinz-130/inbound_return/archive/refs/heads/main.zip' -OutFile $zip; Expand-Archive $zip -DestinationPath $dest -Force; Copy-Item (Join-Path $dest 'inbound_return-main\*') -Destination $pwd -Recurse -Force; Remove-Item $zip -Force; Remove-Item $dest -Recurse -Force; Write-Host '[OK] File proyek berhasil diperbarui dari GitHub!' -ForegroundColor Green"

:after_git
echo.
echo [*] Menyinkronkan database MySQL, tabel, dan user...
set PHP_BIN=php
if exist "C:\xampp\php\php.exe" set PHP_BIN=C:\xampp\php\php.exe
if exist "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe" set PHP_BIN=C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe

"%PHP_BIN%" "api\migrate.php" >nul 2>nul
if %errorlevel% equ 0 (
    echo [OK] Database dan tabel berhasil disinkronkan!
) else (
    echo [!] Catatan: Pastikan MySQL di XAMPP atau Laragon sudah di-START.
)

echo.
echo ====================================================================
echo   UPDATE SELESAI! PC Server Anda sudah menggunakan versi terbaru.
echo ====================================================================
echo.
pause
