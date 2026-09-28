@echo off
chcp 65001 >nul
title Menjalankan PowerShell Inbound Return IEG
color 0B

cd /d "%~dp0"

echo ====================================================================
echo     MEMBUKA POWERSHELL SERVER INBOUND RETURN IEG (1-KLIK)
echo ====================================================================
echo.

if exist "%~dp0install-server.ps1" (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-server.ps1"
) else (
    echo [!] File install-server.ps1 tidak ditemukan di folder lokal.
    echo     Mengunduh dan menjalankan installer langsung dari GitHub...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "iwr -useb https://raw.githubusercontent.com/dhanielomarthinz-130/inbound_return/main/install-server.ps1 | iex"
)

echo.
echo ====================================================================
echo   Proses telah selesai. Tekan tombol apa saja untuk keluar...
echo ====================================================================
pause >nul
