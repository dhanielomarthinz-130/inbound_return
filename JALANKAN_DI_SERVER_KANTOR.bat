@echo off
title SISTEM INBOUND RETURN - AKSES DARI RUMAH
color 0A
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0server-tools\run_tunnel.ps1"
pause
