@echo off
title IEG Return - Sinkronisasi Manual On-Demand (InfinityFree -> Server)
color 0a

echo ===================================================================
echo   IEG WAREHOUSE - TARIK DATA CLOUD MANUAL (SEKALI JALAN)
echo   Menyinkronkan data & foto transaksi dari InfinityFree ke Database
echo ===================================================================
echo.
echo Menghubungi Cloud InfinityFree...
echo.

"C:\xampp\php\php.exe" sync_worker.php

echo.
echo ===================================================================
echo   Proses sinkronisasi selesai!
echo ===================================================================
echo.
pause
