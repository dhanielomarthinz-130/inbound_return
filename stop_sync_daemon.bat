@echo off
title Hentikan IEG Auto-Sync Daemon
color 0c
echo Menghentikan proses IEG Auto-Sync Daemon...
powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*sync_worker.php*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force; Write-Host ('Menghentikan PID: ' + $_.ProcessId) }"
echo.
echo IEG Auto-Sync Daemon telah dihentikan.
timeout /t 3
