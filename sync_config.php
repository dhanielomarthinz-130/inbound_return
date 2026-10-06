<?php
/**
 * Konfigurasi Sinkronisasi Cloud (InfinityFree) <-> Localhost (PC Server)
 * 
 * Token ini harus bernilai SAMA PERSIS antara yang diupload ke InfinityFree
 * dan yang ada di PC Localhost untuk keamanan transmisi data.
 */

// Kunci Keamanan Token Rahasia (Pastikan sama di online dan local)
defined('SYNC_SECRET_KEY') or define('SYNC_SECRET_KEY', 'IEG_RETURN_SYNC_TOKEN_2026_X99A');

// URL Web InfinityFree Anda
defined('CLOUD_BASE_URL') or define('CLOUD_BASE_URL', 'https://returninboundieg.great-site.net');

// Batas jumlah transaksi yang ditarik per 1 kali putaran sync
defined('SYNC_BATCH_LIMIT') or define('SYNC_BATCH_LIMIT', 30);

// Konfigurasi Direct FTP (Bypass Anti-Bot / Anti-Scraping InfinityFree untuk Foto & Video)
defined('FTP_SYNC_HOST') or define('FTP_SYNC_HOST', 'ftpupload.net');
defined('FTP_SYNC_USER') or define('FTP_SYNC_USER', 'if0_38464190');
defined('FTP_SYNC_PASS') or define('FTP_SYNC_PASS', 'Dhaniel0');
defined('FTP_SYNC_BASE') or define('FTP_SYNC_BASE', 'returninboundieg.great-site.net/htdocs');
