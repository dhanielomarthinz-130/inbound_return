@echo off
chcp 65001 >nul
title Pendaftaran Auto Sync OCS ke Windows Task Scheduler
color 0A

:: Masuk ke folder root proyek
cd /d "%~dp0"

echo ====================================================================
echo     PENDAFTARAN AUTO SYNC OCS HARIAN KE WINDOWS TASK SCHEDULER
echo ====================================================================
echo   Direktori Proyek: %CD%
echo   Jadwal Otomatis : Setiap hari pukul 01:00 WIB (Tarik Data H-2)
echo.

set TASK_NAME=IEG_Inbound_Sync_OCS_Orders_H2
set BAT_PATH=%CD%\run_daily_ocs_sync.bat

if not exist "%BAT_PATH%" (
    color 0C
    echo [ERROR] File run_daily_ocs_sync.bat tidak ditemukan di %BAT_PATH%!
    pause
    exit /b 1
)

echo Mendaftarkan Task '%TASK_NAME%' ke Windows...
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
    "$action = New-ScheduledTaskAction -Execute '%BAT_PATH%' -WorkingDirectory '%CD%';" ^
    "$trigger = New-ScheduledTaskTrigger -Daily -At 01:00;" ^
    "$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable;" ^
    "Register-ScheduledTask -TaskName '%TASK_NAME%' -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null;"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ====================================================================
    echo   [BERHASIL] Task '%TASK_NAME%' AKTIF di Windows Task Scheduler!
    echo ====================================================================
    echo   - Waktu Jalan : Pukul 01:00 WIB setiap malam
    echo   - Target Data : OCS H-2 (00:00:00 s/d 23:59:59 WIB)
    echo   - Ketahanan   : StartWhenAvailable aktif (jika PC mati jam 01:00,
    echo                   sinkronisasi otomatis jalan saat PC menyala pagi).
    echo ====================================================================
) else (
    color 0C
    echo [GAGAL] Gagal mendaftarkan task. Coba jalankan file ini dengan 'Run as administrator'.
)

echo.
echo Ingin membuka aplikasi Task Scheduler Windows untuk melihatnya? (Y/N)
set /p OPEN_GUI="Pilihan Anda (Y/N, default Y): "
if /i "%OPEN_GUI%"=="" set OPEN_GUI=Y
if /i "%OPEN_GUI%"=="Y" (
    start taskschd.msc
)

echo.
echo Tekan sembarang tombol untuk menutup jendela ini...
pause >nul
