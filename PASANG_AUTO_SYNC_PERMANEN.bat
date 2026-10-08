@echo off
title PASANG AUTO-SYNC PERMANEN PC SERVER KANTOR
color 0a

echo ===================================================================
echo   IEG WAREHOUSE - PASANG AUTO-SYNC BACKGROUND PERMANEN
echo   Menjadikan sync berjalan otomatis di background PC Server
echo   TANPA HARUS MEMBUKA BROWSER ATAU URL LOCALHOST!
echo ===================================================================
echo.
echo Pastikan Anda menjalankan script ini di PC SERVER KANTOR.
echo.
pause

:: 1. Daftarkan PC ini sebagai PC Server Resmi
echo.
echo [1/4] Mengaktifkan hak akses Server di sistem...
echo ^<?php define('SYNC_ENABLED', true); > "%~dp0sync_config.local.php"
echo SERVER > "%~dp0.is_pc_server"
echo [OK] PC ini telah resmi terdaftar sebagai PC Server Utama!

:: 2. Tambahkan ke Folder Windows Startup (Otomatis nyala tiap PC dinyalakan)
echo.
echo [2/4] Mendaftarkan ke Windows Startup...
powershell -NoProfile -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut([System.IO.Path]::Combine($env:APPDATA, 'Microsoft\Windows\Start Menu\Programs\Startup\IEG_AutoSync_Infinity.lnk')); $s.TargetPath = 'wscript.exe'; $s.Arguments = '\"%~dp0run_sync_silent.vbs\"'; $s.WorkingDirectory = '%~dp0'; $s.WindowStyle = 7; $s.Save()"
echo [OK] Shortcut Startup berhasil dibuat!

:: 3. Daftarkan ke Windows Task Scheduler (Tugas Terjadwal Windows)
echo.
echo [3/4] Mendaftarkan ke Windows Task Scheduler...
schtasks /create /tn "IEG_AutoSync_Infinity" /tr "wscript.exe \"%~dp0run_sync_silent.vbs\"" /sc onlogon /f >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Windows Scheduled Task berhasil dipasang!
) else (
    echo [INFO] Scheduled Task memerlukan hak Admin, namun Startup shortcut sudah aktif bekerja normal.
)

:: 4. Hentikan worker lama jika ada dan langsung jalankan di background sekarang
echo.
echo [4/4] Memulai sinkronisasi di background sekarang juga...
powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*sync_worker.php*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }" >nul 2>&1
wscript.exe "%~dp0run_sync_silent.vbs"

echo.
echo ===================================================================
echo   SELAMAT! AUTO-SYNC BACKGROUND BERHASIL DIPASANG!
echo ===================================================================
echo.
echo  Mulai sekarang:
echo  1. Data dari InfinityFree otomatis ditarik tiap 30 detik ke MySQL Server.
echo  2. Anda TIDAK PERLU lagi membuka browser atau url localhost!
echo  3. Saat PC Server dinyalakan, sync langsung aktif di background.
echo.
echo  Untuk cek status log kapan saja, buka file:
echo  - CEK_STATUS_SYNC_BACKGROUND.bat
echo.
pause
