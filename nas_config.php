<?php
/**
 * Konfigurasi Synology NAS - Video Packing Outbound
 * Digunakan untuk mengambil video rekaman packing pesanan di folder PACKER (https://192.168.30.5:5001/)
 */
defined('NAS_HOST') or define('NAS_HOST', '192.168.30.5');
defined('NAS_PORT') or define('NAS_PORT', 5001);
defined('NAS_PROTOCOL') or define('NAS_PROTOCOL', 'https');
defined('NAS_FOLDER') or define('NAS_FOLDER', '/PACKER');

defined('NAS_USER') or define('NAS_USER', 'admin.cs');
defined('NAS_PASS') or define('NAS_PASS', 'I3g@1234');
defined('NAS_SMB_PATH') or define('NAS_SMB_PATH', '\\\\192.168.30.5\\PACKER');
