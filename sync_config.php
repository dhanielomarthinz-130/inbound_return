<?php
/**
 * Konfigurasi Sinkronisasi Cloud (InfinityFree) <-> Localhost (PC Server)
 * 
 * Token ini harus bernilai SAMA PERSIS antara yang diupload ke InfinityFree
 * dan yang ada di PC Localhost untuk keamanan transmisi data.
 */

// Muat konfigurasi khusus mesin ini jika ada (misal di PC Server Kantor)
if (file_exists(__DIR__ . '/sync_config.local.php')) {
    require_once __DIR__ . '/sync_config.local.php';
}

// Kunci Keamanan Token Rahasia (Pastikan sama di online dan local, override di sync_config.local.php)
defined('SYNC_SECRET_KEY') or define('SYNC_SECRET_KEY', getenv('SYNC_SECRET_KEY') ?: 'IEG_RETURN_DEFAULT_SYNC_KEY');

// URL Web InfinityFree Anda
defined('CLOUD_BASE_URL') or define('CLOUD_BASE_URL', getenv('CLOUD_BASE_URL') ?: 'https://returninboundieg.great-site.net');

/**
 * PENGATURAN SYNC MULTI-PERANGKAT:
 * Setel true HANYA di PC Server Kantor.
 * Di laptop / PC dev / perangkat lain, setel false agar tidak menarik data dari Cloud (tidak salah jalur).
 */
$isLaptopDevice = (strpos(strtoupper(gethostname()), 'LAPTOP') !== false);
$hasServerMarker = file_exists(__DIR__ . '/.is_pc_server');
defined('SYNC_ENABLED') or define('SYNC_ENABLED', ($hasServerMarker && !$isLaptopDevice));

// Batas jumlah transaksi yang ditarik per 1 kali putaran sync
defined('SYNC_BATCH_LIMIT') or define('SYNC_BATCH_LIMIT', 30);

// Konfigurasi Direct FTP (Bypass Anti-Bot / Anti-Scraping InfinityFree untuk Foto & Video)
defined('FTP_SYNC_HOST') or define('FTP_SYNC_HOST', getenv('FTP_SYNC_HOST') ?: 'ftpupload.net');
defined('FTP_SYNC_USER') or define('FTP_SYNC_USER', getenv('FTP_SYNC_USER') ?: 'if0_38464190');
defined('FTP_SYNC_PASS') or define('FTP_SYNC_PASS', getenv('FTP_SYNC_PASS') ?: '');
defined('FTP_SYNC_BASE') or define('FTP_SYNC_BASE', getenv('FTP_SYNC_BASE') ?: 'returninboundieg.great-site.net/htdocs');
