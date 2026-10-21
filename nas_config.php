<?php
/**
 * Konfigurasi Synology NAS - Video Packing Outbound
 * Digunakan untuk mengambil video rekaman packing pesanan di folder PACKER (https://192.168.30.5:5001/)
 */

// Muat konfigurasi kredensial lokal jika ada (di-ignore oleh git)
if (file_exists(__DIR__ . '/nas_config.local.php')) {
    require_once __DIR__ . '/nas_config.local.php';
}

defined('NAS_HOST')     or define('NAS_HOST', getenv('NAS_HOST') ?: '192.168.30.5');
defined('NAS_PORT')     or define('NAS_PORT', getenv('NAS_PORT') ?: 5001);
defined('NAS_PROTOCOL') or define('NAS_PROTOCOL', getenv('NAS_PROTOCOL') ?: 'https');
defined('NAS_FOLDER')   or define('NAS_FOLDER', getenv('NAS_FOLDER') ?: '/PACKER');

defined('NAS_USER')     or define('NAS_USER', getenv('NAS_USER') ?: 'admin.cs');
defined('NAS_PASS')     or define('NAS_PASS', getenv('NAS_PASS') ?: '');
defined('NAS_SMB_PATH') or define('NAS_SMB_PATH', getenv('NAS_SMB_PATH') ?: '\\\\192.168.30.5\\PACKER');
