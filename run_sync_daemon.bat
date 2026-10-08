@echo off
title IEG Return - Sinkronisasi Manual On-Demand (InfinityFree -> Server)
color 0a

cd /d "%~dp0"

echo ===================================================================
echo   IEG WAREHOUSE - TARIK DATA CLOUD (INFINITYFREE)
echo   Menyinkronkan data dan foto transaksi dari InfinityFree ke Database
echo ===================================================================
echo.

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

echo [*] Menghubungi Cloud InfinityFree...
echo.

%PHP_BIN% sync_worker.php

echo.
echo ===================================================================
echo   Proses sinkronisasi selesai!
echo ===================================================================
echo.
pause
