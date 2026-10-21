@echo off
chcp 65001 >nul
title RESTORE & MIGRASI KE PC SERVER BARU - INBOUND RETURN IEG
color 0A

:: Masuk ke folder root proyek
cd /d "%~dp0.."
if not exist "config.php" cd /d "%~dp0"

echo ====================================================================
echo   IEG WAREHOUSE - OTOMASI MIGRASI & RESTORE DATABASE PC SERVER BARU
echo ====================================================================
echo.
echo Direktori Saat Ini : %CD%
echo.
echo Script ini akan secara otomatis:
echo   1. Mendeteksi MySQL di PC Server baru (Laragon / XAMPP).
echo   2. Menyiapkan database 'inbound_return'.
echo   3. Mengimpor file backup SQL hasil migrasi.
echo   4. Mengaktifkan konfigurasi sebagai PC Server Kantor utama.
echo.
echo Pastikan Laragon / XAMPP (Apache ^& MySQL) SUDAH DINYALAKAN (Running)!
echo.
pause

echo.
echo [1/4] Mendeteksi MySQL & PHP...

set MYSQL_BIN=
if exist "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" set "MYSQL_BIN=C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"
if not defined MYSQL_BIN (
    for /d %%D in (C:\laragon\bin\mysql\*) do (
        if exist "%%D\bin\mysql.exe" set "MYSQL_BIN=%%D\bin\mysql.exe"
    )
)
if not defined MYSQL_BIN if exist "C:\xampp\mysql\bin\mysql.exe" set "MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe"
if not defined MYSQL_BIN (
    where mysql.exe >nul 2>&1
    if not errorlevel 1 set "MYSQL_BIN=mysql.exe"
)

if not defined MYSQL_BIN (
    echo [!] PERINGATAN: Program mysql.exe tidak ditemukan di C:\laragon atau C:\xampp.
    echo     Silakan buka HeidiSQL / phpMyAdmin dan import manual file .sql ke database 'inbound_return'.
    goto LANJUT_CONFIG
)

echo [OK] MySQL terdeteksi: "%MYSQL_BIN%"

echo.
echo [2/4] Mencari file database SQL migrasi...
set SQL_FILE=
if exist "inbound_return_database_migration.sql" set "SQL_FILE=inbound_return_database_migration.sql"
if not defined SQL_FILE (
    for %%F in (inbound_return_database_backup_*.sql) do (
        set "SQL_FILE=%%F"
    )
)
if not defined SQL_FILE (
    for %%F in (inbound_return_backup_*.sql) do (
        set "SQL_FILE=%%F"
    )
)
if not defined SQL_FILE if exist "database.sql" set "SQL_FILE=database.sql"

if not defined SQL_FILE (
    echo [!] Tidak ditemukan file .sql migrasi di folder ini.
    echo     Pastikan file database hasil download ditaruh di folder proyek ini.
    goto LANJUT_CONFIG
)

echo [OK] Ditemukan file database: %SQL_FILE%

echo.
echo [3/4] Mengimpor database ke MySQL (mohon tunggu beberapa detik)...
"%MYSQL_BIN%" -u root -e "CREATE DATABASE IF NOT EXISTS inbound_return CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
"%MYSQL_BIN%" -u root inbound_return < "%SQL_FILE%"
if errorlevel 1 (
    echo [!] Terjadi kendala saat import database. Jika MySQL menggunakan password, silakan import melalui HeidiSQL.
) else (
    echo [OK] Seluruh data database berhasil diimpor ke database 'inbound_return'!
)

:LANJUT_CONFIG
echo.
echo [4/4] Mengonfigurasi PC ini sebagai Server Utama...
echo define('SYNC_ENABLED', true); > sync_config.local.php
echo SERVER > .is_pc_server
echo [OK] Konfigurasi server lokal aktif.

echo.
echo ====================================================================
echo   MIGRASI KE PC SERVER BARU BERHASIL!
echo ====================================================================
echo.
echo Apakah Anda ingin langsung menyalakan server sekarang?
echo Tekan 'Y' untuk Jalankan Server, atau sembarang tombol untuk selesai.
set /p PILIH=Pilihan Anda (Y/N): 
if /i "%PILIH%"=="Y" (
    if exist "server-tools\3_JALANKAN_SERVER.bat" (
        call "server-tools\3_JALANKAN_SERVER.bat"
    ) else if exist "3_JALANKAN_SERVER.bat" (
        call "3_JALANKAN_SERVER.bat"
    )
)

echo.
echo Selesai.
pause
