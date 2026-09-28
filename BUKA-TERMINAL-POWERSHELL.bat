@echo off
chcp 65001 >nul
title Terminal PowerShell - Inbound Return IEG
color 0A

cd /d "%~dp0"
echo ====================================================================
echo      TERMINAL POWERSHELL SIAP PAKAI (Inbound Return IEG)
echo ====================================================================
echo   Lokasi: %CD%
echo.
echo   Perintah Cepat:
echo   - Jalankan Installer Server : .\install-server.ps1
echo   - Cek Git Status            : git status
echo   - Tarik Update Terbaru      : git pull origin main
echo ====================================================================
echo.

powershell.exe -NoExit -ExecutionPolicy Bypass
