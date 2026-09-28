@echo off
chcp 65001 >nul
title 1. Instalasi Server Baru Inbound Return IEG
color 0B

:: Masuk ke folder root proyek (satu tingkat di atas server-tools)
cd /d "%~dp0.."

echo ====================================================================
echo     [1/3] INSTALASI SERVER LOKAL INBOUND RETURN IEG
echo ====================================================================
echo   Direktori Proyek: %CD%
echo.

if exist "server-tools\install-server.ps1" (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "server-tools\install-server.ps1"
) else if exist "install-server.ps1" (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "install-server.ps1"
) else (
    echo Mengunduh installer langsung dari GitHub...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "iwr -useb https://raw.githubusercontent.com/dhanielomarthinz-130/inbound_return/main/install-server.ps1 | iex"
)

echo.
echo ====================================================================
echo   Instalasi selesai. Tekan sembarang tombol untuk keluar...
echo ====================================================================
pause >nul
