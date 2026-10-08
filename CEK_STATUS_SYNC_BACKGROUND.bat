@echo off
title CEK STATUS AUTO-SYNC BACKGROUND
color 0b

echo ===================================================================
echo   IEG WAREHOUSE - STATUS AUTO-SYNC BACKGROUND (INFINITY -> SERVER)
echo ===================================================================
echo.

powershell -NoProfile -Command "$p = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*sync_worker.php*' }; if ($p) { Write-Host '[STATUS] Auto-Sync Daemon AKTIF BERJALAN di Background (PID: ' + ($p.ProcessId -join ', ') + ')' -ForegroundColor Green } else { Write-Host '[STATUS] Auto-Sync TIDAK AKTIF di background!' -ForegroundColor Red }"
echo.
echo -------------------------------------------------------------------
echo   CATATAN AKTIVITAS SINKRONISASI TERAKHIR (sync.log):
echo -------------------------------------------------------------------
powershell -NoProfile -Command "if (Test-Path 'uploads\logs\sync.log') { Get-Content 'uploads\logs\sync.log' -Tail 15 } else { Write-Host 'Belum ada log sinkronisasi.' }"
echo -------------------------------------------------------------------
echo.
pause
