@echo off
title SETUP TAILSCALE FUNNEL - LINK PUBLIK PERMANEN INBOUND RETURN
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0server-tools\setup_tailscale.ps1" -Public
