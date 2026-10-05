@echo off
title SETUP TAILSCALE - LINK PERMANEN INBOUND RETURN
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0server-tools\setup_tailscale.ps1"
