<?php
/**
 * Konfigurasi Sinkronisasi Cloud (InfinityFree) <-> Localhost (PC Server)
 * 
 * Token ini harus bernilai SAMA PERSIS antara yang diupload ke InfinityFree
 * dan yang ada di PC Localhost untuk keamanan transmisi data.
 */

// Kunci Keamanan Token Rahasia (Pastikan sama di online dan local)
defined('SYNC_SECRET_KEY') or define('SYNC_SECRET_KEY', 'IEG_RETURN_SYNC_TOKEN_2026_X99A');

// URL Web InfinityFree Anda (Contoh: https://namaweb.epizy.com atau http://domainanda.com)
// Di PC Localhost, ganti nilai ini dengan URL domain InfinityFree Anda yang sebenarnya.
defined('CLOUD_BASE_URL') or define('CLOUD_BASE_URL', 'http://localhost/retrun.inboud');

// Batas jumlah transaksi yang ditarik per 1 kali putaran sync
defined('SYNC_BATCH_LIMIT') or define('SYNC_BATCH_LIMIT', 30);
