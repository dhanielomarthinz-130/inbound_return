@echo off
title EKSPOR DATA HARI INI KE PC SERVER KANTOR
color 0a

echo ===================================================================
echo   IEG WAREHOUSE - EKSPOR DATA LAPTOP UNTUK PC SERVER KANTOR
echo   Mengekspor data transaksi dan foto hari ini ke folder transfer
echo ===================================================================
echo.
"C:\xampp\php\php.exe" scratch/export_today_to_server.php
echo.
pause
