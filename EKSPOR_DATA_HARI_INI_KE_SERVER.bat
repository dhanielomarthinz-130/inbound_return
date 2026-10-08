@echo off
title EKSPOR DATA HARI INI KE PC SERVER KANTOR
color 0a

echo ===================================================================
echo   IEG WAREHOUSE - EKSPOR DATA LAPTOP UNTUK PC SERVER KANTOR
echo   Mengekspor data transaksi dan foto hari ini ke folder transfer
echo ===================================================================
cd /d "%~dp0"
set PHP_BIN=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_BIN="C:\xampp\php\php.exe"
    ) else (
        for /d %%i in (C:\laragon\bin\php\php*) do (
            if exist "%%i\php.exe" set PHP_BIN="%%i\php.exe"
        )
    )
)

%PHP_BIN% scratch/export_today_to_server.php
echo.
pause
