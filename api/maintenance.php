<?php
// api/maintenance.php - Panel Pemeliharaan Sistem & Migrasi PC Server
require_once __DIR__ . '/../config.php';

// Inisialisasi autentikasi pengguna
$currentUser = getSessionUser();
$rawRole = strtolower(trim(str_replace([' ', '_', '-'], '', $currentUser['role'] ?? '')));
$isSuperAdmin = ($currentUser && $rawRole === 'superadmin');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// -------------------------------------------------------------------------
// 0. Autentikasi Khusus Superadmin (Untuk akses via modal di maintenance.php)
// -------------------------------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? ($_GET['action'] ?? '');

    if ($action === 'auth_superadmin') {
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            jsonResponse(['error' => 'Username dan Password / PIN Superadmin wajib diisi!'], 400);
        }

        try {
            $cleanUser = str_replace([' ', '_', '-'], '', strtolower($username));
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR LOWER(username) = ? OR REPLACE(REPLACE(LOWER(username), ' ', ''), '_', '') = ? OR LOWER(name) = ? LIMIT 1");
            $stmt->execute([$username, strtolower($username), $cleanUser, strtolower($username)]);
            $user = $stmt->fetch();

            if (!$user) {
                jsonResponse(['error' => 'Akun Superadmin tidak ditemukan!'], 404);
            }

            $uRole = strtolower(trim(str_replace([' ', '_', '-'], '', $user['role'] ?? '')));
            if ($uRole !== 'superadmin') {
                jsonResponse(['error' => 'Akses ditolak: Akun ini bukan Superadmin.'], 403);
            }

            $isValid = false;
            if (!empty($user['pin']) && $user['pin'] === $password) {
                $isValid = true;
            } elseif ($password === $user['password'] || password_verify($password, $user['password'])) {
                $isValid = true;
            }

            if (!$isValid) {
                jsonResponse(['error' => 'Password atau PIN Superadmin salah!'], 401);
            }

            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role']
            ];

            jsonResponse([
                'success' => true,
                'message' => 'Autentikasi Superadmin berhasil!',
                'user' => $_SESSION['user']
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal verifikasi: ' . $e->getMessage()], 500);
        }
    }
}

// -------------------------------------------------------------------------
// Validasi Akses Superadmin untuk Semua Aksi Maintenance & Migrasi
// -------------------------------------------------------------------------
if (!$isSuperAdmin) {
    if (isset($_GET['action']) && in_array($_GET['action'], ['download_database', 'download_db', 'download_server_files', 'download_files'])) {
        header('Location: ../login?redirect=admin&tab=maintenance');
        exit;
    }
    jsonResponse(['error' => 'Akses Ditolak: Halaman & API Pemeliharaan Sistem hanya dapat diakses oleh Superadmin.'], 403);
}

// =========================================================================
// HELPER FUNCTIONS UNTUK DUMP DATABASE & ZIP FILES
// =========================================================================

function findMysqldumpBinary() {
    $candidates = [
        'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe',
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    ];
    $laragonG = glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe');
    if (!empty($laragonG)) {
        $candidates = array_merge($laragonG, $candidates);
    }
    $xamppG = glob('C:\\*xampp*\\mysql\\bin\\mysqldump.exe');
    if (!empty($xamppG)) {
        $candidates = array_merge($xamppG, $candidates);
    }
    foreach ($candidates as $bin) {
        if (file_exists($bin)) {
            return $bin;
        }
    }
    $test = @shell_exec('where mysqldump 2>nul');
    if (!empty($test)) {
        $lines = explode("\n", trim($test));
        if (!empty($lines[0]) && file_exists(trim($lines[0]))) {
            return trim($lines[0]);
        }
    }
    return null;
}

function generateDatabaseDump($pdo, $db_name, $db_host, $db_user, $db_pass) {
    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');

    $mysqldumpPath = findMysqldumpBinary();
    $tempSql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db_dump_' . date('Ymd_His') . '_' . uniqid() . '.sql';

    if ($mysqldumpPath) {
        $hostParam = escapeshellarg($db_host);
        $userParam = escapeshellarg($db_user);
        $passParam = !empty($db_pass) ? "--password=" . escapeshellarg($db_pass) : "";
        $dbParam   = escapeshellarg($db_name);
        
        $cmd = "\"{$mysqldumpPath}\" -h {$hostParam} -u {$userParam} {$passParam} --single-transaction --quick --add-drop-table --routines --triggers {$dbParam} > \"{$tempSql}\"";
        @exec($cmd, $out, $ret);
        if ($ret === 0 && file_exists($tempSql) && filesize($tempSql) > 1000) {
            return $tempSql;
        }
    }

    // Fallback Streaming PDO Dump jika mysqldump tidak ada
    $fp = fopen($tempSql, 'w');
    if (!$fp) {
        throw new Exception("Gagal membuat file sementara database dump.");
    }

    $header = "-- ============================================================\n"
            . "-- INBOUND RETURN IEG - BACKUP DATABASE UNTUK MIGRASI SERVER\n"
            . "-- Database: {$db_name}\n"
            . "-- Host: {$db_host}\n"
            . "-- Tanggal Pembuatan: " . date('Y-m-d H:i:s') . "\n"
            . "-- Versi PHP: " . PHP_VERSION . "\n"
            . "-- ============================================================\n\n"
            . "SET FOREIGN_KEY_CHECKS = 0;\n"
            . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n"
            . "SET NAMES utf8mb4;\n\n";
    fwrite($fp, $header);

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $tRow) {
        $tbl = $tRow[0];
        fwrite($fp, "\n-- ------------------------------------------------------------\n");
        fwrite($fp, "-- Struktur Tabel `{$tbl}`\n");
        fwrite($fp, "-- ------------------------------------------------------------\n");
        fwrite($fp, "DROP TABLE IF EXISTS `{$tbl}`;\n");
        
        $createRow = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_NUM);
        if (!empty($createRow[1])) {
            fwrite($fp, $createRow[1] . ";\n\n");
        }

        $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        if ($count > 0) {
            fwrite($fp, "-- Dumping data untuk `{$tbl}` ({$count} baris)\n");
            
            $stmt = $pdo->query("SELECT * FROM `{$tbl}`");
            $batchSize = 250;
            $rowsInBatch = 0;
            $batchValues = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_keys($row);
                $escaped = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $escaped[] = 'NULL';
                    } elseif (is_numeric($val) && !is_string($val)) {
                        $escaped[] = $val;
                    } else {
                        $escaped[] = $pdo->quote($val);
                    }
                }
                $batchValues[] = '(' . implode(', ', $escaped) . ')';
                $rowsInBatch++;

                if ($rowsInBatch >= $batchSize) {
                    $insertSql = "INSERT INTO `{$tbl}` (`" . implode('`, `', $cols) . "`) VALUES \n"
                               . implode(",\n", $batchValues) . ";\n";
                    fwrite($fp, $insertSql);
                    $batchValues = [];
                    $rowsInBatch = 0;
                }
            }

            if (!empty($batchValues)) {
                $insertSql = "INSERT INTO `{$tbl}` (`" . implode('`, `', $cols) . "`) VALUES \n"
                           . implode(",\n", $batchValues) . ";\n";
                fwrite($fp, $insertSql);
            }
        }
    }

    $footer = "\nSET FOREIGN_KEY_CHECKS = 1;\n-- Selesai backup.\n";
    fwrite($fp, $footer);
    fclose($fp);

    return $tempSql;
}

function generateServerFilesZip($mode = 'app', $freshDumpPath = null) {
    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');

    $rootDir = realpath(__DIR__ . '/..');
    $tempZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inbound_return_' . $mode . '_' . date('Ymd_His') . '_' . uniqid() . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("Gagal membuat arsip ZIP di folder sementara sistem.");
    }

    // Masukkan dump database terbaru ke dalam file ZIP jika ada
    if ($freshDumpPath && file_exists($freshDumpPath)) {
        $zip->addFile($freshDumpPath, 'inbound_return_database_migration.sql');
    }

    // Buat file panduan instruksi migrasi di dalam ZIP
    $instructions = "======================================================================\r\n"
                  . "  PANDUAN LENGKAP MIGRASI INBOUND RETURN KE PC SERVER BARU\r\n"
                  . "  PT. Indo Express Global (IEG)\r\n"
                  . "  Tanggal Pembuatan Backup: " . date('d F Y, H:i:s') . "\r\n"
                  . "======================================================================\r\n\r\n"
                  . "LANGKAH-LANGKAH RESTORE DI PC SERVER BARU:\r\n\r\n"
                  . "1. PERSIAPAN WEB SERVER:\r\n"
                  . "   - Pasang Laragon (sangat disarankan) atau XAMPP di PC Server baru.\r\n"
                  . "   - Pastikan modul Apache dan MySQL sudah START / RUNNING.\r\n\r\n"
                  . "2. EKSTRAK FILE KE FOLDER WEB SERVER:\r\n"
                  . "   - Ekstrak seluruh isi arsip ZIP ini ke folder:\r\n"
                  . "     * Jika Laragon: C:\\laragon\\www\\inbound_return\r\n"
                  . "     * Jika XAMPP:   C:\\xampp\\htdocs\\inbound_return\r\n\r\n"
                  . "3. IMPORT DATABASE MYSQL:\r\n"
                  . "   - Buka HeidiSQL / phpMyAdmin di PC Server baru.\r\n"
                  . "   - Buat database baru bernama: inbound_return\r\n"
                  . "   - Import file: inbound_return_database_migration.sql\r\n"
                  . "     (File ini sudah ada di folder hasil ekstrak ZIP ini).\r\n"
                  . "   - ATAU klik dua kali: server-tools\\MIGRASI_RESTORE_PC_BARU.bat\r\n\r\n"
                  . "4. JALANKAN SERVER:\r\n"
                  . "   - Buka folder 'server-tools'\r\n"
                  . "   - Klik dua kali: 3_JALANKAN_SERVER.bat\r\n"
                  . "   - Layar akan menampilkan IP Server Lokal (contoh: http://192.168.1.50/inbound_return/)\r\n"
                  . "   - Sistem langsung siap dipakai untuk operator dan admin!\r\n";
    $zip->addFromString('PANDUAN_MIGRASI_PC_BARU.txt', $instructions);

    $excludePatterns = [
        '.git',
        '_backup_',
        'TRANSFER_KE_SERVER_',
        'cloudflared.exe',
        'ngrok.exe',
        'inbound_return.sql',
        '.system_generated',
        '.vscode',
    ];

    if ($mode === 'app') {
        $excludePatterns[] = 'uploads';
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootDir, RecursiveIteratorIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $realPath = $item->getRealPath();
        $relative = substr($realPath, strlen($rootDir) + 1);
        $relativeNorm = str_replace('\\', '/', $relative);

        $skip = false;
        foreach ($excludePatterns as $pattern) {
            if (strpos($relativeNorm, $pattern) === 0 || strpos('/' . $relativeNorm, '/' . $pattern) !== false) {
                $skip = true;
                break;
            }
        }

        // Hindari memasukkan file zip atau dump sql lama di root
        if (substr($relativeNorm, -4) === '.zip' && strpos($relativeNorm, '/') === false) {
            $skip = true;
        }
        if (substr($relativeNorm, -4) === '.sql' && strpos($relativeNorm, '/') === false) {
            $skip = true;
        }

        if ($skip) {
            continue;
        }

        if ($item->isDir()) {
            $zip->addEmptyDir($relativeNorm);
        } elseif ($item->isFile()) {
            $zip->addFile($realPath, $relativeNorm);
        }
    }

    $zip->close();
    return $tempZip;
}

function streamFileForDownload($filePath, $downloadFilename, $contentType = 'application/octet-stream') {
    if (!file_exists($filePath)) {
        http_response_code(404);
        die("File tidak ditemukan.");
    }

    $fileSize = filesize($filePath);
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);

    $handle = fopen($filePath, 'rb');
    if ($handle) {
        while (!feof($handle) && connection_status() === 0) {
            echo fread($handle, 1024 * 1024);
            flush();
        }
        fclose($handle);
    }

    if (strpos(realpath($filePath), realpath(sys_get_temp_dir())) === 0) {
        @unlink($filePath);
    }
    exit;
}

// =========================================================================
// HANDLER METHOD GET: STATUS, DIAGNOSTIK, DAN DOWNLOAD
// =========================================================================

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    // 1. Download Database Langsung (.sql atau .sql.zip)
    if ($action === 'download_database' || $action === 'download_db') {
        try {
            $format = strtolower(trim($_GET['format'] ?? 'sql'));
            $dumpFile = generateDatabaseDump($pdo, $db_name, $db_host, $db_user, $db_pass);
            $ts = date('Y-m-d_His');

            if ($format === 'zip') {
                $zipFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db_zip_' . uniqid() . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    $zip->addFile($dumpFile, "inbound_return_database_{$ts}.sql");
                    $zip->close();
                    @unlink($dumpFile);
                    streamFileForDownload($zipFile, "inbound_return_database_backup_{$ts}.sql.zip", 'application/zip');
                }
            }

            streamFileForDownload($dumpFile, "inbound_return_database_backup_{$ts}.sql", 'application/sql');
        } catch (Exception $e) {
            http_response_code(500);
            die("Gagal mengunduh database: " . htmlspecialchars($e->getMessage()));
        }
    }

    // 2. Download Semua File Server / Paket Aplikasi (.zip)
    if ($action === 'download_server_files' || $action === 'download_files') {
        try {
            $mode = strtolower(trim($_GET['mode'] ?? 'app'));
            if ($mode !== 'full') $mode = 'app';

            // Hasilkan dump database terbaru untuk disertakan langsung ke dalam ZIP
            $dumpFile = generateDatabaseDump($pdo, $db_name, $db_host, $db_user, $db_pass);
            $zipFile = generateServerFilesZip($mode, $dumpFile);
            @unlink($dumpFile);

            $ts = date('Y-m-d_His');
            $downloadName = ($mode === 'full')
                ? "inbound_return_FULL_SERVER_{$ts}.zip"
                : "inbound_return_APP_PACKAGE_{$ts}.zip";

            streamFileForDownload($zipFile, $downloadName, 'application/zip');
        } catch (Exception $e) {
            http_response_code(500);
            die("Gagal mengunduh file server: " . htmlspecialchars($e->getMessage()));
        }
    }

    // 3. Statistik Khusus Migrasi
    if ($action === 'migration_stats') {
        try {
            $sizeQuery = $pdo->prepare("
                SELECT SUM(data_length + index_length) AS db_size 
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ");
            $sizeQuery->execute([$db_name]);
            $dbSize = (int)$sizeQuery->fetchColumn();

            $totalRows = 0;
            $tableList = ['ocs_orders', 'return_sessions', 'return_items', 'reception_packages', 'master_products', 'expedition_receptions', 'users'];
            foreach ($tableList as $tbl) {
                try {
                    $totalRows += (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
                } catch (Exception $e) {}
            }

            $diskFree = @disk_free_space(realpath(__DIR__ . '/..')) ?: 0;

            jsonResponse([
                'success' => true,
                'database_size_bytes' => $dbSize,
                'database_size_mb' => round($dbSize / 1024 / 1024, 2),
                'database_total_rows' => $totalRows,
                'disk_free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
                'mysqldump_available' => (findMysqldumpBinary() !== null),
                'zip_available' => class_exists('ZipArchive')
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // 4. Default: Load Status & Diagnostics
    try {
        $stmtMaint = $pdo->prepare("SELECT key_value FROM system_settings WHERE key_name = 'maintenance_mode'");
        $stmtMaint->execute();
        $maintRow = $stmtMaint->fetch();
        $isMaintenance = ($maintRow && $maintRow['key_value'] == '1');

        $bankSettings = [
            'bank_name' => 'BCA (Bank Central Asia)',
            'bank_account_number' => '873-098-1234',
            'bank_account_holder' => 'PT. INOVASI EKA GEMILANG',
            'bank_payment_notes' => '*Mohon sertakan nomor invoice pada berita transfer saat pembayaran.'
        ];
        $stmtBank = $pdo->query("SELECT key_name, key_value FROM system_settings WHERE key_name IN ('bank_name', 'bank_account_number', 'bank_account_holder', 'bank_payment_notes')");
        if ($stmtBank) {
            while ($bRow = $stmtBank->fetch()) {
                if ($bRow['key_value'] !== null && $bRow['key_value'] !== '') {
                    $bankSettings[$bRow['key_name']] = $bRow['key_value'];
                }
            }
        }

        $tablesStats = [];
        $targetTables = ['master_products', 'return_sessions', 'return_items', 'master_expeditions', 'users', 'reception_packages', 'ocs_orders'];
        foreach ($targetTables as $tbl) {
            try {
                $cnt = $pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
                $tablesStats[$tbl] = (int)$cnt;
            } catch (Exception $e) {
                $tablesStats[$tbl] = 0;
            }
        }

        // Estimasi Ukuran Database
        $dbSizeMb = 0;
        try {
            $sizeQ = $pdo->prepare("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = ?");
            $sizeQ->execute([$db_name]);
            $dbSizeMb = round((int)$sizeQ->fetchColumn() / 1024 / 1024, 2);
        } catch (Exception $e) {}

        $sysInfo = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI',
            'mysql_host' => $db_host ?? '127.0.0.1',
            'database_name' => $db_name ?? 'inbound_return',
            'database_size_mb' => $dbSizeMb,
            'environment' => !empty($is_remote) ? 'InfinityFree Hosting (Production)' : 'Local Server (Active)',
            'timezone' => date_default_timezone_get(),
            'server_time' => date('Y-m-d H:i:s'),
            'mysqldump_available' => (findMysqldumpBinary() !== null),
            'zip_available' => class_exists('ZipArchive'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];

        jsonResponse([
            'success' => true,
            'maintenance_mode' => $isMaintenance,
            'bank_settings' => $bankSettings,
            'tables' => $tablesStats,
            'system' => $sysInfo
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

// =========================================================================
// HANDLER METHOD POST: PERUBAHAN PENGATURAN & AKSI MAINTENANCE
// =========================================================================

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';

    try {
        if ($action === 'toggle_maintenance') {
            $currentStatus = $pdo->query("SELECT key_value FROM system_settings WHERE key_name = 'maintenance_mode'")->fetchColumn();
            $newStatus = ($currentStatus == '1') ? '0' : '1';

            $stmt = $pdo->prepare("UPDATE system_settings SET key_value = ? WHERE key_name = 'maintenance_mode'");
            $stmt->execute([$newStatus]);

            jsonResponse([
                'success' => true,
                'maintenance_mode' => ($newStatus === '1'),
                'message' => ($newStatus === '1') 
                    ? 'Mode Pemeliharaan (Maintenance) BERHASIL DIAKTIFKAN. Operator dan admin biasa tidak dapat mengakses sistem.' 
                    : 'Mode Pemeliharaan DINONAKTIFKAN. Sistem kembali beroperasi normal.'
            ]);
        }

        if ($action === 'update_bank_settings') {
            $bankName   = trim($input['bank_name'] ?? '');
            $accNum     = trim($input['bank_account_number'] ?? '');
            $accHolder  = trim($input['bank_account_holder'] ?? '');
            $notes      = trim($input['bank_payment_notes'] ?? '');

            if (empty($bankName) || empty($accNum) || empty($accHolder)) {
                jsonResponse(['error' => 'Nama Bank, No. Rekening, dan Atas Nama wajib diisi!'], 400);
            }

            $stmtUpsert = $pdo->prepare("
                INSERT INTO system_settings (key_name, key_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)
            ");

            $stmtUpsert->execute(['bank_name', $bankName]);
            $stmtUpsert->execute(['bank_account_number', $accNum]);
            $stmtUpsert->execute(['bank_account_holder', $accHolder]);
            $stmtUpsert->execute(['bank_payment_notes', $notes]);

            jsonResponse([
                'success' => true,
                'message' => 'Informasi No. Rekening & Rekening Bank berhasil disimpan!',
                'bank_settings' => [
                    'bank_name' => $bankName,
                    'bank_account_number' => $accNum,
                    'bank_account_holder' => $accHolder,
                    'bank_payment_notes' => $notes
                ]
            ]);
        }

        if ($action === 'optimize_tables') {
            $tables = ['master_products', 'return_sessions', 'return_items', 'master_expeditions', 'users', 'system_settings'];
            foreach ($tables as $t) {
                $pdo->exec("OPTIMIZE TABLE `{$t}`");
            }
            jsonResponse([
                'success' => true,
                'message' => 'Semua tabel database berhasil dioptimasi dan didefragmentasi!'
            ]);
        }

        if ($action === 'clean_test_transactions') {
            $pdo->exec("DELETE FROM return_sessions WHERE invoice_number LIKE 'INV-DEMO%' OR invoice_number LIKE 'TEST%'");
            jsonResponse([
                'success' => true,
                'message' => 'Data transaksi demo & uji coba berhasil dibersihkan.'
            ]);
        }

        jsonResponse(['error' => 'Aksi tidak dikenali.'], 400);

    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}
