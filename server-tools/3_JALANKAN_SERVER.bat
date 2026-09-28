@echo off
chcp 65001 >nul
title 3. Peluncur Server Inbound Return IEG
color 0B

:: Masuk ke folder root proyek (satu tingkat di atas server-tools)
cd /d "%~dp0.."

echo ====================================================================
echo     [3/3] PELUNCUR SERVER LOCALHOST - INBOUND RETURN IEG
echo ====================================================================
echo.

:: 1. Deteksi executable PHP dari XAMPP, Laragon, atau PATH
set PHP_BIN=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_BIN="C:\xampp\php\php.exe"
    ) else if exist "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe" (
        set PHP_BIN="C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"
    ) else (
        for /d %%i in (C:\laragon\bin\php\php-8*) do (
            if exist "%%i\php.exe" set PHP_BIN="%%i\php.exe"
        )
    )
)

echo [*] Memeriksa database MySQL dan user resmi...
%PHP_BIN% "api/migrate.php" >nul 2>nul
if %errorlevel% equ 0 (
    echo [OK] Database dan seluruh tabel siap digunakan!
) else (
    echo [!] PERINGATAN: Pastikan MySQL di XAMPP atau Laragon sudah di-START!
)
echo.

:: 2. Ambil IP Address PC Server di Jaringan Lokal (WiFi/LAN)
set LOCAL_IP=127.0.0.1
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address" /c:"Alamat IPv4"') do (
    set LOCAL_IP=%%a
    goto :ip_found
)
:ip_found
set LOCAL_IP=%LOCAL_IP: =%

:: Ambil nama folder proyek
for %%I in ("%CD%") do set FOLDER_NAME=%%~nxI

echo ====================================================================
echo                     PANDUAN ALAMAT AKSES URL
echo ====================================================================
echo.
echo  [A] Akses di PC Server ini:
echo      - Scanner Operator : http://localhost/%FOLDER_NAME%/
echo      - Admin Portal     : http://localhost/%FOLDER_NAME%/admin.php
echo.
echo  [B] Akses dari PC / HP / Barcode Scanner Lain (Satu Wi-Fi / LAN):
echo      - Scanner Operator : http://%LOCAL_IP%/%FOLDER_NAME%/
echo      - Admin Portal     : http://%LOCAL_IP%/%FOLDER_NAME%/admin.php
echo.
echo  [C] Akun Pengguna Resmi:
echo      - Superadmin : Daniel      ^| Password : Dh@niel0
echo      - Admin      : Admin       ^| Password : Password01
echo      - Operator 1 : PIN 123456  ^| Pass: Password01
echo      - Operator 2 : PIN 123456  ^| Pass: Password01
echo.
echo ====================================================================
echo.
echo Tekan tombol apa saja untuk membuka sistem di browser...
pause >nul

start http://localhost/%FOLDER_NAME%/
