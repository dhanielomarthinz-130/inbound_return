@echo off
title AKSI CEPAT: AKTIFKAN AKSES DARI RUMAH (CLOUDFLARE TUNNEL)
color 0A
echo ================================================================
echo    SISTEM INBOUND RETURN - AKTIFKAN AKSES DARI LUAR KANTOR / RUMAH
echo ================================================================
echo.
echo Sedang memeriksa cloudflared...

if not exist "%~dp0cloudflared.exe" (
    echo Mengunduh Cloudflare Tunnel otomatis (hanya 1 kali)...
    powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%~dp0cloudflared.exe'"
    if not exist "%~dp0cloudflared.exe" (
        echo [ERROR] Gagal mengunduh cloudflared.exe. Pastikan ada koneksi internet.
        pause
        exit /b
    )
    echo Unduhan berhasil!
)

echo.
echo ================================================================
echo  MEMBUKA JALUR INTERNET AMAN KE SERVER LOKAL...
echo  Silakan tunggu sampai muncul link: https://xxxx.trycloudflare.com
echo.
echo  Link tersebut BISA LANGSUNG DIBUKA DARI LAPTOP/HP DI RUMAH!
echo  (JANGAN TUTUP JENDELA INI SELAMA ANDA INGIN MENGAKSES DARI RUMAH)
echo ================================================================
echo.

"%~dp0cloudflared.exe" tunnel --url http://127.0.0.1:80
pause
