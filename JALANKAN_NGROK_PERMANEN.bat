@echo off
title SISTEM INBOUND RETURN - NGROK STATIC DOMAIN PERMANEN
color 0B

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0server-tools\run_ngrok.ps1"

pause
