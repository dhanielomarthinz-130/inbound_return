@echo off
chcp 65001 >nul
title Installer & Launcher Server Inbound Return IEG
color 0B

echo ====================================================================
echo    PENGINSTAL & PELUNCUR SISTEM INBOUND RETURN IEG (SERVER LOKAL)
echo ====================================================================
echo.

:: Periksa apakah file install-server.ps1 ada di folder yang sama
if exist "%~dp0install-server.ps1" (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-server.ps1"
) else (
    echo Mengunduh installer terbaru dari GitHub...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "iwr -useb https://raw.githubusercontent.com/dhanielomarthinz-130/inbound_return/main/install-server.ps1 | iex"
)

pause
