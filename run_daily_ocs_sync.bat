@echo off
REM ==============================================================================
REM   IEG INBOUND RETURN - AUTO SYNC ORDERS OCS HARIAN (H-2)
REM   Dijalankan otomatis oleh Windows Task Scheduler setiap hari jam 01:00 WIB.
REM   Script menyinkronkan seluruh order OCS untuk H-2 (00:00:00 s/d 23:59:59 WIB).
REM ==============================================================================

SETLOCAL EnableDelayedExpansion

cd /d "%~dp0"

SET TARGET_DATE=h-2
IF NOT "%~1"=="" SET TARGET_DATE=%~1

REM 1. Deteksi PHP Binary (Laragon, XAMPP, atau System PATH)
SET PHP_BIN=
FOR /D %%D IN ("C:\laragon\bin\php\*") DO (
    IF EXIST "%%D\php.exe" SET PHP_BIN="%%D\php.exe"
)
IF "!PHP_BIN!"=="" (
    IF EXIST "C:\xampp\php\php.exe" SET PHP_BIN="C:\xampp\php\php.exe"
)
IF "!PHP_BIN!"=="" (
    SET PHP_BIN=php
)

echo [ %date% %time% ] Memulai sinkronisasi orders OCS target %TARGET_DATE%...

!PHP_BIN! "api/sync_ocs_orders.php" --date=%TARGET_DATE%

echo.
echo [ %date% %time% ] Sinkronisasi OCS selesai. Log tersimpan di uploads/logs/ocs_sync.log
ENDLOCAL
