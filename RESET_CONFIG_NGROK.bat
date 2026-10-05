@echo off
title RESET CONFIG NGROK DOMAIN
color 0C

if exist "%~dp0ngrok_domain.txt" (
    del /f /q "%~dp0ngrok_domain.txt"
    echo [SUKSES] Konfigurasi domain lama telah dihapus.
) else (
    echo Konfigurasi domain belum ada.
)

echo.
echo Silakan jalankan file "JALANKAN_NGROK_PERMANEN.bat" untuk memasukkan domain baru.
echo.
pause
