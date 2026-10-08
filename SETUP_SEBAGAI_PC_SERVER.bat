@echo off
title AKTIFKAN MODE PC SERVER KANTOR
color 0a

echo ===================================================================
echo   IEG WAREHOUSE - AKTIFKAN HAK SYNC SEBAGAI PC SERVER KANTOR
echo ===================================================================
echo.
echo Script ini HANYA boleh dijalankan di PC Server Kantor utama.
echo Setelah diaktifkan, PC ini diizinkan menarik data otomatis dari InfinityFree.
echo.
pause

echo.
echo Menyetel konfigurasi Server...
echo define('SYNC_ENABLED', true); > sync_config.local.php
echo SERVER > .is_pc_server

echo.
echo ===================================================================
echo   SUKSES! Perangkat ini sekarang resmi terdaftar sebagai PC SERVER.
echo   Auto-Sync Cloud (InfinityFree -> Server) diaktifkan.
echo ===================================================================
echo.
pause
