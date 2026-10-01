@echo off
REM ==============================================================================
REM   IEG INBOUND RETURN - AUTO SYNC ORDERS OCS HARIAN (HARI SEBELUMNYA)
REM   Jadwalkan script ini di Windows Task Scheduler untuk berjalan setiap hari
REM   (misalnya setiap jam 00:05 WIB).
REM   Script akan menyinkronkan seluruh order dari jam 00:00:00 s/d 23:59:59 WIB kemarin.
REM ==============================================================================

echo [ %date% %time% ] Memulai sinkronisasi orders OCS hari kemarin...

cd /d "%~dp0"

IF EXIST "c:\xampp\php\php.exe" (
    SET PHP_BIN="c:\xampp\php\php.exe"
) ELSE (
    SET PHP_BIN=php
)

%PHP_BIN% "api/sync_ocs_orders.php" --date=yesterday

echo.
echo [ %date% %time% ] Sinkronisasi OCS selesai. Log tersimpan di uploads/logs/ocs_sync.log
REM timeout /t 10
