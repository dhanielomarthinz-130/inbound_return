@echo off
title IEG Return - Auto Sync Worker Daemon (InfinityFree -> Localhost)
color 0a

echo ===================================================================
echo   IEG WAREHOUSE - AUTO SYNC WORKER DAEMON
echo   Menyinkronkan data & foto dari InfinityFree ke Database Localhost
echo ===================================================================
echo.
echo Daemon aktif... Mengecek data baru tiap 30 detik.
echo Jangan tutup jendela ini agar sinkronisasi terus berjalan otomatis.
echo.

php sync_worker.php --daemon

pause
