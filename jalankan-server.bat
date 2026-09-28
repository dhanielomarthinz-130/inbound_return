@echo off
chcp 65001 >nul
title Inbound Return System - Server Launcher
color 0B

echo ====================================================================
echo        SISTEM INBOUND RETURN SCANNER - SERVER LOCAL
echo ====================================================================
echo.

:: 1. Deteksi executable PHP dari Laragon, XAMPP, atau System PATH
set PHP_BIN=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe" (
        set PHP_BIN="C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"
    ) else (
        for /d %%i in (C:\laragon\bin\php\php-8*) do (
            if exist "%%i\php.exe" set PHP_BIN="%%i\php.exe"
        )
    )
    if not defined PHP_BIN (
        if exist "C:\xampp\php\php.exe" set PHP_BIN="C:\xampp\php\php.exe"
    )
)

echo [*] Memeriksa koneksi database MySQL & auto-migration...
%PHP_BIN% -f "%~dp0config.php" >nul 2>nul
if %errorlevel% equ 0 (
    echo [✓] Database 'inbound_return' dan tabel berhasil diverifikasi!
) else (
    echo [!] CATATAN: Pastikan Apache & MySQL di Laragon sudah di-klik START ALL!
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

echo ====================================================================
echo                     PANDUAN ALAMAT AKSES URL
echo ====================================================================
echo.
echo  [A] Akses di PC Server ini:
echo      • Scanner Operator : http://localhost/retrun.inboud/
echo      • Admin Portal     : http://localhost/retrun.inboud/admin.php
echo.
echo  [B] Akses dari PC / HP / Scanner Gudang Lain (Satu Wi-Fi / LAN):
echo      • Scanner Operator : http://%LOCAL_IP%/retrun.inboud/
echo      • Admin Portal     : http://%LOCAL_IP%/retrun.inboud/admin.php
echo.
echo  [C] Akun Pengguna Resmi:
echo      • Superadmin : Daniel      ^| Password : Dh@niel0
echo      • Admin      : Admin       ^| Password : Password01
echo      • Operator 1 : PIN 123456  ^| Pass: Password01
echo      • Operator 2 : PIN 123456  ^| Pass: Password01
echo.
echo ====================================================================
echo.
echo Tekan tombol apa saja untuk membuka sistem di browser...
pause >nul

start http://localhost/retrun.inboud/
