@echo off
chcp 65001 >nul
title Pemasang Service Apache (Anti-Putus & Auto-Restart)
color 0B

:: Cek Administrator privilege
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Meminta hak akses Administrator...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

cd /d "%~dp0.."

echo ====================================================================
echo    PEMASANG SERVICE APACHE WINDOWS (ANTI-PUTUS & AUTO-RESTART)
echo ====================================================================
echo.

set APACHE_EXE=
if exist "C:\laragon\bin\apache" (
    for /f "delims=" %%i in ('dir /b /s "C:\laragon\bin\apache\httpd.exe" 2^>nul') do (
        set "APACHE_EXE=%%i"
        goto :found_apache
    )
)
if exist "C:\xampp\apache\bin\httpd.exe" (
    set "APACHE_EXE=C:\xampp\apache\bin\httpd.exe"
    goto :found_apache
)

:found_apache
if "%APACHE_EXE%"=="" (
    color 0C
    echo [ERROR] httpd.exe tidak ditemukan di C:\laragon\bin\apache atau C:\xampp\apache!
    pause
    exit /b 1
)

echo [*] Ditemukan Apache: %APACHE_EXE%
echo [*] Memeriksa status service Apache24...

sc query Apache24 >nul 2>&1
if %errorlevel% neq 0 (
    echo [*] Memasang service 'Apache24'...
    "%APACHE_EXE%" -k install -n "Apache24"
)

echo [*] Mengatur service agar Otomatis menyala saat Windows booting...
sc.exe config Apache24 start= auto >nul 2>&1

echo [*] Mengatur pemulihan otomatis jika Apache pernah crash (Auto-Restart 3 detik)...
sc.exe failure Apache24 reset= 0 actions= restart/3000/restart/3000/restart/3000 >nul 2>&1

echo [*] Memulai service Apache24...
net start Apache24 >nul 2>&1

echo.
sc query Apache24 | findstr /i "STATE"
echo.
echo ====================================================================
echo   [BERHASIL] Service Apache24 sudah aktif dan terlindungi!
echo   - Web server akan otomatis menyala setiap kali komputer hidup.
echo   - Jika ada error/crash, Windows akan merestartnya dalam 3 detik.
echo   - Tidak akan putus lagi walau jendela CMD/Laragon ditutup.
echo ====================================================================
echo.
pause
