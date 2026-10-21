@echo off
chcp 65001 >nul
title 4. Pendaftaran Auto Sync OCS H-2 ke Windows Task Scheduler
color 0A

:: Masuk ke folder root proyek (satu tingkat di atas server-tools)
cd /d "%~dp0.."

if exist "DAFTARKAN_TASK_SCHEDULER_OCS.bat" (
    call "DAFTARKAN_TASK_SCHEDULER_OCS.bat"
) else (
    echo File DAFTARKAN_TASK_SCHEDULER_OCS.bat tidak ditemukan.
    pause
)
