<?php
// config.php - Konfigurasi Database MySQL & Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$http_host = $_SERVER['HTTP_HOST'] ?? '';
$is_remote = (
    strpos($http_host, 'great-site.net') !== false ||
    strpos($http_host, 'infinityfree') !== false ||
    strpos($http_host, 'rf.gd') !== false ||
    strpos($http_host, 'page.gd') !== false ||
    strpos($http_host, '42web.io') !== false ||
    strpos($http_host, 'infy.uk') !== false ||
    getenv('APP_ENV') === 'production'
);

if ($is_remote) {
    // Production InfinityFree (returninboundieg.great-site.net)
    $db_host = 'sql202.infinityfree.com';
    $db_user = 'if0_38464190';
    $db_pass = 'Dhaniel0';
    $db_name = 'if0_38464190_inboundreturnIEG';
} else {
    // Localhost (Laragon / XAMPP)
    $db_host = '127.0.0.1';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'inbound_return';
}

try {
    // 1. Koneksi langsung ke database
    try {
        $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $connErr) {
        if (!$is_remote && ($connErr->getCode() == 1049 || strpos($connErr->getMessage(), 'Unknown database') !== false)) {
            // Database belum ada di localhost, buatkan otomatis
            $pdoServer = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } else {
            throw $connErr;
        }
    }

    // Set Timezone WIB (+07:00)
    date_default_timezone_set('Asia/Jakarta');
    $pdo->exec("SET time_zone = '+07:00'");

    // 2. Fungsi Skema & Migrasi (Hanya berjalan sekali saat pertama install atau saat api/migrate.php dipanggil)
    function ensureDatabaseSchema($pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `master_products` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `barcode` VARCHAR(100) NOT NULL UNIQUE,
                `sku` VARCHAR(100) NOT NULL,
                `seller_sku` VARCHAR(150) NULL,
                `sap_code` VARCHAR(100) NULL,
                `name` VARCHAR(255) NOT NULL,
                `category` VARCHAR(100) DEFAULT 'Umum',
                `unit` VARCHAR(50) DEFAULT 'Pcs',
                `shop` VARCHAR(100) NULL,
                `bin_code` VARCHAR(100) NULL,
                `barcode_bpom` VARCHAR(150) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_prod_sku (`sku`),
                INDEX idx_prod_seller_sku (`seller_sku`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `master_expeditions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `prefix_pattern` VARCHAR(255) NULL DEFAULT '',
                `status` VARCHAR(20) DEFAULT 'ACTIVE',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `return_sessions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `invoice_number` VARCHAR(100) NOT NULL,
                `customer_name` VARCHAR(255) DEFAULT 'Pelanggan Umum',
                `expedition` VARCHAR(100) NULL,
                `operator_name` VARCHAR(100) DEFAULT 'Gudang 01',
                `status` VARCHAR(50) DEFAULT 'COMPLETED',
                `total_items` INT DEFAULT 0,
                `total_good` INT DEFAULT 0,
                `total_damaged` INT DEFAULT 0,
                `notes` TEXT NULL,
                `video_path` VARCHAR(255) NULL,
                `package_photo` VARCHAR(255) NULL,
                `product_photo` VARCHAR(255) NULL,
                `photos` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_invoice (`invoice_number`),
                INDEX idx_created (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `return_items` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT NOT NULL,
                `barcode` VARCHAR(100) NOT NULL,
                `product_name` VARCHAR(255) NOT NULL,
                `sku` VARCHAR(100) NULL,
                `seller_sku` VARCHAR(150) NULL,
                `sap_code` VARCHAR(100) NULL,
                `batch_no` VARCHAR(100) NULL,
                `exp_date` VARCHAR(50) NULL,
                `type` VARCHAR(50) DEFAULT 'GOOD',
                `qty` INT NOT NULL DEFAULT 1,
                `condition` VARCHAR(20) NOT NULL DEFAULT 'GOOD',
                `damage_reason` VARCHAR(255) NULL,
                `photo_path` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session (`session_id`),
                INDEX idx_item_barcode (`barcode`),
                INDEX idx_item_sku (`sku`),
                INDEX idx_item_seller_sku (`seller_sku`),
                INDEX idx_item_created (`created_at`),
                CONSTRAINT fk_session_items FOREIGN KEY (`session_id`) REFERENCES `return_sessions`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `role` ENUM('superadmin', 'admin', 'operator') NOT NULL DEFAULT 'operator',
                `pin` VARCHAR(20) NULL DEFAULT '123456',
                `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `system_settings` (
                `key_name` VARCHAR(100) PRIMARY KEY,
                `key_value` TEXT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `master_conditions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `description` VARCHAR(255) NULL,
                `color` VARCHAR(30) DEFAULT 'slate',
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `expedition_receptions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `receipt_number` VARCHAR(100) NOT NULL UNIQUE,
                `expedition` VARCHAR(100) NOT NULL,
                `courier_name` VARCHAR(150) NULL,
                `sack_number` VARCHAR(100) NULL,
                `vehicle_no` VARCHAR(50) NULL,
                `operator_name` VARCHAR(100) NOT NULL,
                `total_packages` INT DEFAULT 0,
                `notes` TEXT NULL,
                `photo_path` VARCHAR(255) NULL,
                `package_photos` TEXT NULL,
                `status` VARCHAR(50) DEFAULT 'RECEIVED',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_receipt_number (`receipt_number`),
                INDEX idx_reception_expedition (`expedition`),
                INDEX idx_reception_sack (`sack_number`),
                INDEX idx_reception_created (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `reception_packages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `reception_id` INT NOT NULL,
                `package_barcode` VARCHAR(100) NOT NULL,
                `sack_number` VARCHAR(100) NULL,
                `photo_path` VARCHAR(255) NULL,
                `scanned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reception_id (`reception_id`),
                INDEX idx_package_barcode (`package_barcode`),
                INDEX idx_pkg_sack (`sack_number`),
                CONSTRAINT fk_reception_packages FOREIGN KEY (`reception_id`) REFERENCES `expedition_receptions`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `ocs_orders` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` VARCHAR(100) NOT NULL UNIQUE,
                `tracking_number` VARCHAR(100) NULL,
                `platform_id` INT NULL,
                `commerce_platform` VARCHAR(50) NULL,
                `shop_name` VARCHAR(100) NULL,
                `shipping_provider` VARCHAR(150) NULL,
                `status_code` INT NULL,
                `status_name` VARCHAR(100) NULL,
                `product_name` TEXT NULL,
                `seller_sku` VARCHAR(150) NULL,
                `total_qty` INT DEFAULT 1,
                `package_price` DECIMAL(15,2) DEFAULT 0.00,
                `original_price` DECIMAL(15,2) DEFAULT 0.00,
                `seller_discount` DECIMAL(15,2) DEFAULT 0.00,
                `platform_discount` DECIMAL(15,2) DEFAULT 0.00,
                `shipping_fee` DECIMAL(15,2) DEFAULT 0.00,
                `service_fee` DECIMAL(15,2) DEFAULT 0.00,
                `subtotal` DECIMAL(15,2) DEFAULT 0.00,
                `total_amount` DECIMAL(15,2) DEFAULT 0.00,
                `gmv` DECIMAL(15,2) DEFAULT 0.00,
                `nmv` DECIMAL(15,2) DEFAULT 0.00,
                `customer_name` VARCHAR(150) NULL,
                `customer_phone` VARCHAR(100) NULL,
                `customer_address` TEXT NULL,
                `order_items_json` LONGTEXT NULL,
                `has_packing_video` TINYINT(1) DEFAULT 0,
                `packing_video_url` VARCHAR(255) NULL,
                `order_created_at` VARCHAR(50) NULL,
                `raw_payload` LONGTEXT NULL,
                `is_synced_to_local` TINYINT(1) DEFAULT 0,
                `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ocs_order_id (`order_id`),
                INDEX idx_ocs_tracking (`tracking_number`),
                INDEX idx_ocs_platform (`commerce_platform`),
                INDEX idx_ocs_shop (`shop_name`),
                INDEX idx_ocs_created (`order_created_at`),
                INDEX idx_ocs_sync_local (`is_synced_to_local`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Auto-patch kolom jika sebelumnya belum ada
        try {
            $colsOcs = $pdo->query("SHOW COLUMNS FROM ocs_orders")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('status_name', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN status_name VARCHAR(100) NULL AFTER status_code");
            if (!in_array('seller_sku', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN seller_sku VARCHAR(150) NULL AFTER product_name");
            if (!in_array('package_price', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN package_price DECIMAL(15,2) DEFAULT 0.00 AFTER total_qty");
            if (!in_array('original_price', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN original_price DECIMAL(15,2) DEFAULT 0.00 AFTER package_price");
            if (!in_array('seller_discount', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN seller_discount DECIMAL(15,2) DEFAULT 0.00 AFTER original_price");
            if (!in_array('platform_discount', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN platform_discount DECIMAL(15,2) DEFAULT 0.00 AFTER seller_discount");
            if (!in_array('shipping_fee', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN shipping_fee DECIMAL(15,2) DEFAULT 0.00 AFTER platform_discount");
            if (!in_array('service_fee', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN service_fee DECIMAL(15,2) DEFAULT 0.00 AFTER shipping_fee");
            if (!in_array('subtotal', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN subtotal DECIMAL(15,2) DEFAULT 0.00 AFTER service_fee");
            if (!in_array('total_amount', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN total_amount DECIMAL(15,2) DEFAULT 0.00 AFTER subtotal");
            if (!in_array('gmv', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN gmv DECIMAL(15,2) DEFAULT 0.00 AFTER total_amount");
            if (!in_array('nmv', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN nmv DECIMAL(15,2) DEFAULT 0.00 AFTER gmv");
            if (!in_array('customer_name', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN customer_name VARCHAR(150) NULL AFTER nmv");
            if (!in_array('customer_phone', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN customer_phone VARCHAR(100) NULL AFTER customer_name");
            if (!in_array('customer_address', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN customer_address TEXT NULL AFTER customer_phone");
            if (!in_array('order_items_json', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN order_items_json LONGTEXT NULL AFTER customer_address");
            if (!in_array('is_synced_to_local', $colsOcs)) $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN is_synced_to_local TINYINT(1) DEFAULT 0 AFTER raw_payload");

            $colsRecPkgs = $pdo->query("SHOW COLUMNS FROM reception_packages")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('photo_path', $colsRecPkgs)) $pdo->exec("ALTER TABLE reception_packages ADD COLUMN photo_path VARCHAR(255) NULL AFTER package_barcode");

            $cols = $pdo->query("SHOW COLUMNS FROM return_items")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('batch_no', $cols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN batch_no VARCHAR(100) NULL AFTER sku");
            if (!in_array('exp_date', $cols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN exp_date VARCHAR(50) NULL AFTER batch_no");
            if (!in_array('type', $cols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN type VARCHAR(50) NULL DEFAULT 'GOOD' AFTER exp_date");
            if (!in_array('seller_sku', $cols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN seller_sku VARCHAR(150) NULL AFTER sku");
            if (!in_array('sap_code', $cols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN sap_code VARCHAR(100) NULL AFTER seller_sku");
            if (!in_array('condition', $cols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN `condition` VARCHAR(20) NOT NULL DEFAULT 'GOOD' AFTER qty");
            if (!in_array('damage_reason', $cols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN damage_reason VARCHAR(255) NULL AFTER `condition`");
            if (!in_array('photo_path', $cols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN photo_path VARCHAR(255) NULL AFTER damage_reason");

            $colsSessions = $pdo->query("SHOW COLUMNS FROM return_sessions")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('expedition', $colsSessions)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN expedition VARCHAR(100) NULL AFTER customer_name");
            if (!in_array('video_path', $colsSessions)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN video_path VARCHAR(255) NULL AFTER notes");
            if (!in_array('package_photo', $colsSessions)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN package_photo VARCHAR(255) NULL AFTER video_path");
            if (!in_array('product_photo', $colsSessions)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN product_photo VARCHAR(255) NULL AFTER package_photo");
            if (!in_array('photos', $colsSessions)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN photos TEXT NULL AFTER product_photo");

            $colsRecep = $pdo->query("SHOW COLUMNS FROM expedition_receptions")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('courier_name', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN courier_name VARCHAR(150) NULL AFTER expedition");
            if (!in_array('sack_number', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN sack_number VARCHAR(100) NULL AFTER courier_name");
            if (!in_array('courier_photo', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN courier_photo VARCHAR(255) NULL AFTER courier_name");
            if (!in_array('vehicle_no', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN vehicle_no VARCHAR(50) NULL AFTER courier_photo");
            if (!in_array('operator_name', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN operator_name VARCHAR(100) DEFAULT 'Operator' AFTER vehicle_no");
            if (!in_array('total_packages', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN total_packages INT DEFAULT 0 AFTER operator_name");
            if (!in_array('notes', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN notes TEXT NULL AFTER total_packages");
            if (!in_array('photo_path', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN photo_path VARCHAR(255) NULL AFTER notes");
            if (!in_array('package_photos', $colsRecep)) $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN package_photos TEXT NULL AFTER photo_path");
            $colsRecepPkg = $pdo->query("SHOW COLUMNS FROM reception_packages")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('sack_number', $colsRecepPkg)) $pdo->exec("ALTER TABLE reception_packages ADD COLUMN sack_number VARCHAR(100) NULL AFTER package_barcode");

            // Auto-backfill data penerimaan lama yang belum ada nomor karung
            try {
                $pdo->exec("UPDATE expedition_receptions SET sack_number = 'Karung 1' WHERE sack_number IS NULL OR TRIM(sack_number) = '' OR sack_number = '-'");
                $pdo->exec("UPDATE reception_packages SET sack_number = 'Karung 1' WHERE sack_number IS NULL OR TRIM(sack_number) = '' OR sack_number = '-'");
            } catch (Exception $eBackfill) {}

            $colsProd = $pdo->query("SHOW COLUMNS FROM master_products")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('seller_sku', $colsProd)) $pdo->exec("ALTER TABLE master_products ADD COLUMN seller_sku VARCHAR(150) NULL AFTER sku");
            if (!in_array('sap_code', $colsProd)) $pdo->exec("ALTER TABLE master_products ADD COLUMN sap_code VARCHAR(100) NULL AFTER seller_sku");
            if (!in_array('shop', $colsProd)) $pdo->exec("ALTER TABLE master_products ADD COLUMN shop VARCHAR(100) NULL AFTER sap_code");
            if (!in_array('bin_code', $colsProd)) $pdo->exec("ALTER TABLE master_products ADD COLUMN bin_code VARCHAR(100) NULL AFTER shop");
            if (!in_array('barcode_bpom', $colsProd)) $pdo->exec("ALTER TABLE master_products ADD COLUMN barcode_bpom VARCHAR(150) NULL AFTER bin_code");

            $colsExp = $pdo->query("SHOW COLUMNS FROM master_expeditions")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('prefix_pattern', $colsExp)) $pdo->exec("ALTER TABLE master_expeditions ADD COLUMN prefix_pattern VARCHAR(255) NULL DEFAULT '' AFTER name");

            $colsUsers = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('pin', $colsUsers)) $pdo->exec("ALTER TABLE users ADD COLUMN pin VARCHAR(20) NULL DEFAULT '123456' AFTER role");
        } catch (Exception $e) {}

        // Seed Ekspedisi
        try {
            $checkExp = $pdo->query("SELECT COUNT(*) AS total FROM master_expeditions");
            if ($checkExp->fetch()['total'] == 0) {
                $seedExp = $pdo->prepare("INSERT INTO master_expeditions (code, name, prefix_pattern, status) VALUES (?, ?, ?, ?)");
                $dummyExp = [
                    ['SHOPEE',  'Shopee Xpress (SPX)', 'SPX,SPXID,ID', 'ACTIVE'],
                    ['GTL',      'GoTo Logistics (GTL)', 'GTL,TKP,GOTO', 'ACTIVE'],
                    ['JNT',      'J&T Express',          'JP,JX,JS,JT', 'ACTIVE'],
                    ['SICEPAT',  'SiCepat Ekspres',      '00,SC,SICEPAT', 'ACTIVE'],
                    ['JNE',      'JNE Express',          'JNE,TJNE,01', 'ACTIVE'],
                    ['ANTERAJA', 'AnterAja',             '100,10,AP', 'ACTIVE'],
                    ['GOSEND',   'GoSend / Grab',        'GK,GRAB,GO', 'ACTIVE'],
                    ['TIKI',     'TIKI',                 'TIKI,12', 'ACTIVE'],
                    ['POS',      'Pos Indonesia',        'POS,P', 'ACTIVE']
                ];
                foreach ($dummyExp as $exp) {
                    $seedExp->execute($exp);
                }
            }
        } catch (Exception $e) {}

        // Seed Kondisi
        try {
            $chkCond = $pdo->query("SELECT COUNT(*) AS total FROM master_conditions");
            if ($chkCond->fetch()['total'] == 0) {
                $seedCond = $pdo->prepare("INSERT INTO master_conditions (code, name, description, color, sort_order) VALUES (?, ?, ?, ?, ?)");
                $defaultConds = [
                    ['GOOD',    'Baik / Good',          'Produk dalam kondisi baik, tidak ada kerusakan',       'emerald', 1],
                    ['DAMAGED', 'Rusak / Damaged',       'Produk mengalami kerusakan fisik',                     'red',     2],
                    ['MISSING', 'Kurang / Missing',      'Produk kurang dari jumlah yang tercantum di invoice',  'amber',   3],
                    ['EXPIRED', 'Kadaluarsa / Expired',  'Produk sudah melewati tanggal kadaluarsa',             'orange',  4],
                    ['WRONG',   'Salah Kirim / Wrong',   'Produk tidak sesuai dengan yang dipesan',             'purple',  5],
                ];
                foreach ($defaultConds as $cond) {
                    $seedCond->execute($cond);
                }
            }
        } catch (Exception $e) {}

        // Seed / Update Pengguna Resmi (Hanya jika belum ada atau password kosong)
        try {
            $requiredUsers = [
                ['username' => 'Daniel',     'password' => 'Dh@niel0',   'name' => 'Daniel',     'role' => 'superadmin', 'pin' => '123456'],
                ['username' => 'Admin',      'password' => 'Password01', 'name' => 'Admin',      'role' => 'admin',      'pin' => '123456'],
                ['username' => 'Operator 1', 'password' => 'Password01', 'name' => 'Operator 1', 'role' => 'operator',   'pin' => '123456'],
                ['username' => 'Operator 2', 'password' => 'Password01', 'name' => 'Operator 2', 'role' => 'operator',   'pin' => '123456']
            ];

            foreach ($requiredUsers as $reqUser) {
                $chkU = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
                $chkU->execute([$reqUser['username']]);
                $existing = $chkU->fetch();

                if (!$existing) {
                    $hashedPass = password_hash($reqUser['password'], PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (username, password, name, role, pin, status) VALUES (?, ?, ?, ?, ?, 'ACTIVE')")
                        ->execute([$reqUser['username'], $hashedPass, $reqUser['name'], $reqUser['role'], $reqUser['pin']]);
                }
            }

            $pdo->exec("DELETE FROM users WHERE username IN ('superadmin', 'operator')");

            $chkMaint = $pdo->prepare("SELECT key_value FROM system_settings WHERE key_name = 'maintenance_mode'");
            $chkMaint->execute();
            if (!$chkMaint->fetch()) {
                $pdo->prepare("INSERT INTO system_settings (key_name, key_value) VALUES ('maintenance_mode', '0')")->execute();
            }
        } catch (Exception $e) {}

        // Buat file penanda bahwa skema sudah siap
        $lockFile = __DIR__ . '/uploads/.db_ready';
        @file_put_contents($lockFile, date('Y-m-d H:i:s'));
    }

    // Jalankan migrasi cerdas (Self-Healing Schema Check)
    $needsMigration = isset($_GET['run_migration']);
    if (!$needsMigration) {
        try {
            $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $requiredTables = ['return_sessions', 'return_items', 'expedition_receptions', 'reception_packages', 'ocs_orders', 'users', 'master_expeditions'];
            foreach ($requiredTables as $rt) {
                if (!in_array($rt, $existingTables)) {
                    $needsMigration = true;
                    break;
                }
            }

            // Verifikasi kolom receiving & kurir
            if (!$needsMigration && in_array('expedition_receptions', $existingTables)) {
                $recCols = $pdo->query("SHOW COLUMNS FROM expedition_receptions")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('courier_photo', $recCols) || !in_array('photo_path', $recCols) || !in_array('package_photos', $recCols) || !in_array('sack_number', $recCols)) {
                    $needsMigration = true;
                }
            }

            // Verifikasi kolom foto per paket & karung
            if (!$needsMigration && in_array('reception_packages', $existingTables)) {
                $recPkgCols = $pdo->query("SHOW COLUMNS FROM reception_packages")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('photo_path', $recPkgCols) || !in_array('sack_number', $recPkgCols)) {
                    $needsMigration = true;
                }
            }

            // Verifikasi kolom ocs_orders sync
            if (!$needsMigration && in_array('ocs_orders', $existingTables)) {
                $ocsCols = $pdo->query("SHOW COLUMNS FROM ocs_orders")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('is_synced_to_local', $ocsCols) || !in_array('status_name', $ocsCols) || !in_array('total_amount', $ocsCols)) {
                    $needsMigration = true;
                }
            }

            // Verifikasi kolom foto unboxing
            if (!$needsMigration && in_array('return_sessions', $existingTables)) {
                $sessCols = $pdo->query("SHOW COLUMNS FROM return_sessions")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('package_photo', $sessCols) || !in_array('photos', $sessCols)) {
                    $needsMigration = true;
                }
            }
        } catch (Exception $e) {
            $needsMigration = true;
        }
    }

    if ($needsMigration) {
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
        ensureDatabaseSchema($pdo);
    }

} catch (PDOException $e) {
    if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) !== 'config.php') {
        header('Content-Type: application/json; charset=utf-8', true, 500);
        echo json_encode([
            'error' => 'Gagal koneksi ke database Laragon MySQL: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Helper output json
function jsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Auth & Session Helpers
function getSessionUser() {
    return $_SESSION['user'] ?? null;
}

function requireLogin($allowedRoles = []) {
    $user = getSessionUser();
    if (!$user) {
        $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isApi) {
            jsonResponse(['error' => 'Sesi berakhir atau belum login. Silakan login kembali.'], 401);
        } else {
            header('Location: login');
            exit;
        }
    }

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles)) {
        $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);
        if ($isApi) {
            jsonResponse(['error' => 'Akses ditolak: role Anda (' . $user['role'] . ') tidak memiliki izin.'], 403);
        } else {
            $redirect = ($user['role'] === 'operator') ? 'menu' : 'admin';
            echo "<script>alert('Akses Ditolak: Halaman ini hanya untuk role " . implode('/', $allowedRoles) . "'); window.location.href = '{$redirect}';</script>";
            exit;
        }
    }
    return $user;
}

function checkMaintenanceMode($pdo, $user = null) {
    // Superadmin selalu bisa bypass maintenance mode
    if ($user && $user['role'] === 'superadmin') {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT key_value FROM system_settings WHERE key_name = 'maintenance_mode'");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && $row['key_value'] == '1') {
            include __DIR__ . '/maintenance.php';
            exit;
        }
    } catch (Exception $e) {}
    return false;
}
