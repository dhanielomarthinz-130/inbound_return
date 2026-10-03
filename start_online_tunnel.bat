@echo off
title SISTEM INBOUND RETURN - AKSES DARI RUMAH
color 0A
echo ================================================================
echo    SISTEM INBOUND RETURN - AKTIFKAN AKSES DARI LUAR KANTOR / RUMAH
echo ================================================================
echo.

set "CF_EXE=%~dp0cloudflared.exe"
if not exist "%CF_EXE%" (
    if exist "C:\xampp\htdocs\retrun.inboud\cloudflared.exe" (
        set "CF_EXE=C:\xampp\htdocs\retrun.inboud\cloudflared.exe"
    ) else if exist "C:\laragon\cloudflared.exe" (
        set "CF_EXE=C:\laragon\cloudflared.exe"
    ) else if exist "C:\laragon\www\retrun.inboud\cloudflared.exe" (
        set "CF_EXE=C:\laragon\www\retrun.inboud\cloudflared.exe"
    ) else (
        echo [INFO] Mengunduh Cloudflare Tunnel otomatis...
        powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%~dp0cloudflared.exe'"
        set "CF_EXE=%~dp0cloudflared.exe"
    )
)

echo Membuka tunnel dengan: "%CF_EXE%"
echo.
echo ================================================================
echo  MEMBUKA JALUR INTERNET AMAN KE SERVER LARAGON...
echo  Silakan tunggu 5-10 detik sampai muncul link HTTPS:
echo  Contoh: https://xxxx.trycloudflare.com
echo.
echo  Link tersebut BISA LANGSUNG DIBUKA DARI LAPTOP/HP DI RUMAH!
echo  (JANGAN TUTUP JENDELA INI SELAMA ANDA INGIN MENGAKSES DARI RUMAH)
echo ================================================================
echo.

"%CF_EXE%" tunnel --url http://127.0.0.1:80
echo.
echo Tunnel telah dihentikan.
pause
