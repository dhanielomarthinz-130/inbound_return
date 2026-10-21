<?php
/**
 * Template Konfigurasi Lokal (Contoh)
 * 
 * Salin file ini menjadi 'config.local.php' dan sesuaikan kredensialnya.
 * File 'config.local.php' otomatis diabaikan oleh git (.gitignore).
 */

// Kredensial Database Production (InfinityFree)
defined('DB_PROD_HOST') or define('DB_PROD_HOST', 'sql202.infinityfree.com');
defined('DB_PROD_USER') or define('DB_PROD_USER', 'if0_xxxxxxx');
defined('DB_PROD_PASS') or define('DB_PROD_PASS', 'YOUR_DATABASE_PASSWORD_HERE');
defined('DB_PROD_NAME') or define('DB_PROD_NAME', 'if0_xxxxxxx_inboundreturnIEG');

// Kredensial Database Localhost
defined('DB_LOCAL_HOST') or define('DB_LOCAL_HOST', '127.0.0.1');
defined('DB_LOCAL_USER') or define('DB_LOCAL_USER', 'root');
defined('DB_LOCAL_PASS') or define('DB_LOCAL_PASS', '');
defined('DB_LOCAL_NAME') or define('DB_LOCAL_NAME', 'inbound_return');

// Kredensial OCS IEG System
defined('OCS_BASE_URL')  or define('OCS_BASE_URL', 'https://ocs.iegsystem.id');
defined('OCS_USERNAME')  or define('OCS_USERNAME', 'ADMIN');
defined('OCS_PASSWORD')  or define('OCS_PASSWORD', 'YOUR_OCS_PASSWORD_HERE');
defined('OCS_COMPANYDB') or define('OCS_COMPANYDB', 'EJI_WMS');
