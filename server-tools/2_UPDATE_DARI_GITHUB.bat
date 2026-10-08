@echo off
chcp 65001 >nul
title 2. Update Otomatis dari GitHub - Inbound Return IEG
color 0A

REM Masuk ke folder root proyek (satu tingkat di atas server-tools)
cd /d "%~dp0.."

echo ====================================================================
echo     [2/3] MEMPERBARUI SISTEM DARI GITHUB KE PC SERVER LOKAL
echo ====================================================================
echo   Direktori Proyek: %CD%
echo.

REM 1. Deteksi executable Git
set "GIT_BIN=git"
where git >nul 2>nul
if %errorlevel% equ 0 goto :git_ready

if exist "C:\Users\IEG\AppData\Local\Programs\Git\cmd\git.exe" (
    set "GIT_BIN=C:\Users\IEG\AppData\Local\Programs\Git\cmd\git.exe"
    goto :git_ready
)
if exist "%LOCALAPPDATA%\Programs\Git\cmd\git.exe" (
    set "GIT_BIN=%LOCALAPPDATA%\Programs\Git\cmd\git.exe"
    goto :git_ready
)
if exist "C:\Program Files\Git\cmd\git.exe" (
    set "GIT_BIN=C:\Program Files\Git\cmd\git.exe"
    goto :git_ready
)
if exist "C:\Program Files (x86)\Git\cmd\git.exe" (
    set "GIT_BIN=C:\Program Files (x86)\Git\cmd\git.exe"
    goto :git_ready
)

goto :download_zip

:git_ready
echo [*] Git terdeteksi: "%GIT_BIN%"
echo [*] Memeriksa koneksi ke repositori GitHub...
"%GIT_BIN%" remote -v >nul 2>nul
if %errorlevel% neq 0 (
    echo [!] Folder ini bukan repository git aktif. Mengunduh via zip...
    goto :download_zip
)

echo [*] Menghubungi GitHub...
"%GIT_BIN%" fetch origin main
if %errorlevel% neq 0 (
    echo [!] Gagal menghubungi GitHub. Periksa koneksi internet Anda.
    goto :after_git
)

REM Amankan perubahan lokal sementara sebelum pull jika ada
"%GIT_BIN%" status --porcelain > "%TEMP%\git_local_changes.txt" 2>nul
set HAD_STASH=0
for %%R in ("%TEMP%\git_local_changes.txt") do (
    if %%~zR gtr 0 (
        echo [*] Mendeteksi file yang dimodifikasi secara lokal di PC ini.
        echo [*] Mengamankan file lokal sementara sebelum menarik update...
        "%GIT_BIN%" stash save "Auto-stash update" >nul 2>nul
        set HAD_STASH=1
    )
)

echo [*] Menarik pembaruan kode terbaru dari origin/main...
"%GIT_BIN%" pull origin main
if %errorlevel% equ 0 (
    echo [OK] Kode sistem berhasil diperbarui dari GitHub!
) else (
    echo [!] Terjadi kendala saat git pull.
)

if "%HAD_STASH%"=="1" (
    echo [*] Mengembalikan file lokal yang sebelumnya diamankan...
    "%GIT_BIN%" stash pop >nul 2>nul
)

goto :after_git

:download_zip
echo [!] Git CLI tidak ditemukan, mengunduh update kode via PowerShell...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$zip = Join-Path $env:TEMP 'latest.zip'; $dest = Join-Path $env:TEMP 'update_temp'; try { iwr -useb 'https://github.com/dhanielomarthinz-130/inbound_return/archive/refs/heads/main.zip' -OutFile $zip; Expand-Archive $zip -DestinationPath $dest -Force; Copy-Item (Join-Path $dest 'inbound_return-main\*') -Destination $pwd -Recurse -Force; Remove-Item $zip -Force; Remove-Item $dest -Recurse -Force; Write-Host '[OK] File proyek berhasil diunduh dan diperbarui dari GitHub!' -ForegroundColor Green } catch { Write-Host ('[!] Gagal mengunduh: ' + $_.Exception.Message) -ForegroundColor Red }"

:after_git
echo.
echo [*] Menyinkronkan database MySQL, tabel, dan user...
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

%PHP_BIN% "api\migrate.php" >nul 2>nul
if %errorlevel% equ 0 (
    echo [OK] Database dan struktur tabel berhasil disinkronkan!
) else (
    echo [!] Catatan: Pastikan MySQL di XAMPP atau Laragon sudah di-START.
)

echo.
echo ====================================================================
echo   UPDATE KODE SELESAI!
echo.
echo   * PENJELASAN PENTING:
echo     1. Script ini memperbarui KODE & FITUR APLIKASI dari GitHub.
echo     2. Data TRANSAKSI SCAN & FOTO/VIDEO berasal dari InfinityFree
echo        dan disinkronkan via [run_sync_daemon.bat] (bukan dari GitHub).
echo ====================================================================
echo.
pause
