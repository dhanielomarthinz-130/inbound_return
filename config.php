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
    if (!$is_remote) {
        // Hanya di localhost coba buat database jika belum ada
        $pdoServer = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    // Koneksi ke database
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Set Timezone WIB (+07:00)
    date_default_timezone_set('Asia/Jakarta');
    $pdo->exec("SET time_zone = '+07:00'");

    // 3. Auto Migration: Buat tabel jika belum ada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `master_products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `barcode` VARCHAR(100) NOT NULL UNIQUE,
            `sku` VARCHAR(100) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `category` VARCHAR(100) DEFAULT 'Umum',
            `unit` VARCHAR(50) DEFAULT 'Pcs',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `master_expeditions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) NOT NULL UNIQUE,
            `name` VARCHAR(100) NOT NULL,
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
            `batch_no` VARCHAR(100) NULL,
            `exp_date` VARCHAR(50) NULL,
            `type` VARCHAR(50) DEFAULT 'GOOD',
            `qty` INT NOT NULL DEFAULT 1,
            `condition` VARCHAR(20) NOT NULL DEFAULT 'GOOD',
            `damage_reason` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (`session_id`),
            CONSTRAINT fk_session_items FOREIGN KEY (`session_id`) REFERENCES `return_sessions`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `role` ENUM('superadmin', 'admin', 'operator') NOT NULL DEFAULT 'operator',
            `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `system_settings` (
            `key_name` VARCHAR(100) PRIMARY KEY,
            `key_value` TEXT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Auto-patch kolom jika tabel sudah ada sebelumnya
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM return_items")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('batch_no', $cols)) {
            $pdo->exec("ALTER TABLE return_items ADD COLUMN batch_no VARCHAR(100) NULL AFTER sku");
        }
        if (!in_array('exp_date', $cols)) {
            $pdo->exec("ALTER TABLE return_items ADD COLUMN exp_date VARCHAR(50) NULL AFTER batch_no");
        }
        if (!in_array('type', $cols)) {
            $pdo->exec("ALTER TABLE return_items ADD COLUMN type VARCHAR(50) NULL DEFAULT 'GOOD' AFTER exp_date");
        }

        $colsSessions = $pdo->query("SHOW COLUMNS FROM return_sessions")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('expedition', $colsSessions)) {
            $pdo->exec("ALTER TABLE return_sessions ADD COLUMN expedition VARCHAR(100) NULL AFTER customer_name");
        }
        if (!in_array('video_path', $colsSessions)) {
            $pdo->exec("ALTER TABLE return_sessions ADD COLUMN video_path VARCHAR(255) NULL AFTER notes");
        }

        $colsProd = $pdo->query("SHOW COLUMNS FROM master_products")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('seller_sku', $colsProd)) {
            $pdo->exec("ALTER TABLE master_products ADD COLUMN seller_sku VARCHAR(150) NULL AFTER sku");
        }
        if (!in_array('sap_code', $colsProd)) {
            $pdo->exec("ALTER TABLE master_products ADD COLUMN sap_code VARCHAR(100) NULL AFTER seller_sku");
        }
        if (!in_array('shop', $colsProd)) {
            $pdo->exec("ALTER TABLE master_products ADD COLUMN shop VARCHAR(100) NULL AFTER sap_code");
        }
        if (!in_array('bin_code', $colsProd)) {
            $pdo->exec("ALTER TABLE master_products ADD COLUMN bin_code VARCHAR(100) NULL AFTER shop");
        }
        if (!in_array('barcode_bpom', $colsProd)) {
            $pdo->exec("ALTER TABLE master_products ADD COLUMN barcode_bpom VARCHAR(150) NULL AFTER bin_code");
        }

        if (!in_array('seller_sku', $cols)) {
            $pdo->exec("ALTER TABLE return_items ADD COLUMN seller_sku VARCHAR(150) NULL AFTER sku");
        }
        if (!in_array('sap_code', $cols)) {
            $pdo->exec("ALTER TABLE return_items ADD COLUMN sap_code VARCHAR(100) NULL AFTER seller_sku");
        }

        $colsExp = $pdo->query("SHOW COLUMNS FROM master_expeditions")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('prefix_pattern', $colsExp)) {
            $pdo->exec("ALTER TABLE master_expeditions ADD COLUMN prefix_pattern VARCHAR(255) NULL DEFAULT '' AFTER name");
        }
    } catch (Exception $e) {}

    // Auto Seed & Update Master Ekspedisi dengan Prefix Deteksi
    try {
        $checkExp = $pdo->query("SELECT COUNT(*) AS total FROM master_expeditions");
        if ($checkExp->fetch()['total'] == 0) {
            $seedExp = $pdo->prepare("INSERT INTO master_expeditions (code, name, prefix_pattern, status) VALUES (?, ?, ?, ?)");
            $dummyExp = [
                ['SHOPEE', 'Shopee Xpress (SPX)', 'SPX,SPXID,ID', 'ACTIVE'],
                ['GTL', 'GoTo Logistics (GTL)', 'GTL,TKP,GOTO', 'ACTIVE'],
                ['JNT', 'J&T Express', 'JP,JX,JS,JT', 'ACTIVE'],
                ['SICEPAT', 'SiCepat Ekspres', '00,SC,SICEPAT', 'ACTIVE'],
                ['JNE', 'JNE Express', 'JNE,TJNE,01', 'ACTIVE'],
                ['ANTERAJA', 'AnterAja', '100,10,AP', 'ACTIVE'],
                ['GOSEND', 'GoSend / Grab', 'GK,GRAB,GO', 'ACTIVE'],
                ['TIKI', 'TIKI', 'TIKI,12', 'ACTIVE'],
                ['POS', 'Pos Indonesia', 'POS,P', 'ACTIVE']
            ];
            foreach ($dummyExp as $exp) {
                $seedExp->execute($exp);
            }
        } else {
            // Update prefix pattern untuk ekspedisi yang sudah ada
            $defaultPrefixes = [
                'SHOPEE'  => 'SPX,SPXID,ID',
                'GTL'      => 'GTL,TKP,GOTO',
                'JNT'      => 'JP,JX,JS,JT',
                'SICEPAT'  => '00,SC,SICEPAT',
                'JNE'      => 'JNE,TJNE,01',
                'ANTERAJA' => '100,10,AP',
                'GOSEND'   => 'GK,GRAB,GO',
                'TIKI'     => 'TIKI,12',
                'POS'      => 'POS,P'
            ];
            foreach ($defaultPrefixes as $code => $prefixes) {
                $pdo->prepare("UPDATE master_expeditions SET prefix_pattern = ? WHERE code = ? AND (prefix_pattern IS NULL OR prefix_pattern = '')")->execute([$prefixes, $code]);
            }
            // Pastikan GTL ada di database
            $chkGtl = $pdo->prepare("SELECT id FROM master_expeditions WHERE code = 'GTL'");
            $chkGtl->execute();
            if (!$chkGtl->fetch()) {
                $pdo->prepare("INSERT INTO master_expeditions (code, name, prefix_pattern, status) VALUES ('GTL', 'GoTo Logistics (GTL)', 'GTL,TKP,GOTO', 'ACTIVE')")->execute();
            }
        }
        // Bersihkan data dummy contoh awal jika ada
        $pdo->exec("DELETE FROM master_products WHERE barcode LIKE '899100%' AND (seller_sku IS NULL OR seller_sku = '')");

        // Seed Default Users jika tabel users masih kosong
        $chkUserCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($chkUserCount == 0) {
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, name, role, status) VALUES (?, ?, ?, ?, 'ACTIVE')");
            $defaultUsers = [
                ['superadmin', password_hash('admin', PASSWORD_DEFAULT), 'Super Administrator', 'superadmin'],
                ['admin', password_hash('admin', PASSWORD_DEFAULT), 'Admin Gudang', 'admin'],
                ['operator', password_hash('operator', PASSWORD_DEFAULT), 'Operator Inbound', 'operator']
            ];
            foreach ($defaultUsers as $u) {
                $stmtUser->execute($u);
            }
        }

        // Setting maintenance_mode default 0 (OFF)
        $chkMaint = $pdo->prepare("SELECT key_value FROM system_settings WHERE key_name = 'maintenance_mode'");
        $chkMaint->execute();
        if (!$chkMaint->fetch()) {
            $pdo->prepare("INSERT INTO system_settings (key_name, key_value) VALUES ('maintenance_mode', '0')")->execute();
        }

    } catch (Exception $e) {}

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
            header('Location: login.php');
            exit;
        }
    }

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles)) {
        $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);
        if ($isApi) {
            jsonResponse(['error' => 'Akses ditolak: role Anda (' . $user['role'] . ') tidak memiliki izin.'], 403);
        } else {
            $redirect = ($user['role'] === 'operator') ? 'index.php' : 'admin.php';
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
