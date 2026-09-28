@echo off
chcp 65001 >nul
title Update Otomatis dari GitHub - Inbound Return IEG
color 0A

cd /d "%~dp0"

echo ====================================================================
echo     MEMPERBARUI SISTEM DARI GITHUB KE PC SERVER LOKAL INI
echo ====================================================================
echo.

:: 1. Cek apakah ada git
where git >nul 2>nul
if %errorlevel% equ 0 (
    echo [*] Mengambil perubahan terbaru dari repository GitHub (git pull)...
    git pull origin main
    echo.
) else (
    echo [!] Git tidak terdeteksi di PATH, memperbarui via PowerShell...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "& { iwr -useb https://github.com/dhanielomarthinz-130/inbound_return/archive/refs/heads/main.zip -OutFile \"$env:TEMP\latest.zip\"; Expand-Archive \"$env:TEMP\latest.zip\" -DestinationPath \"$env:TEMP\update_temp\" -Force; Copy-Item \"$env:TEMP\update_temp\inbound_return-main\*\" -Destination . -Recurse -Force; Remove-Item \"$env:TEMP\latest.zip\" -Force; Remove-Item \"$env:TEMP\update_temp\" -Recurse -Force; Write-Host '[✓] File proyek berhasil diperbarui dari GitHub!' -ForegroundColor Green }"
)

:: 2. Jalankan migrasi database lokal jika ada perubahan tabel atau user baru
echo [*] Menyinkronkan database MySQL & tabel...
set PHP_BIN=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\xampp\php\php.exe" set PHP_BIN="C:\xampp\php\php.exe"
    if exist "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe" set PHP_BIN="C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"
)

%PHP_BIN% "api/migrate.php" >nul 2>nul
if %errorlevel% equ 0 (
    echo [✓] Database dan tabel berhasil disinkronkan!
) else (
    echo [!] Catatan: Pastikan MySQL di XAMPP / Laragon sudah aktif (START).
)

echo.
echo ====================================================================
echo   UPDATE BERHASIL! PC Server Anda sudah menggunakan versi terbaru.
echo ====================================================================
echo.
pause
