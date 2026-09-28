<?php
require_once __DIR__ . '/../config.php';

$currentUser = getSessionUser();
if (!$currentUser) {
    jsonResponse(['error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

// Hanya SUPERADMIN yang berhak mengakses dan mengelola maintenance!
if ($currentUser['role'] !== 'superadmin') {
    jsonResponse(['error' => 'Akses Ditolak: Halaman & API Pemeliharaan Sistem hanya dapat diakses oleh Superadmin.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        // 1. Ambil status maintenance_mode
        $stmtMaint = $pdo->prepare("SELECT key_value FROM system_settings WHERE key_name = 'maintenance_mode'");
        $stmtMaint->execute();
        $maintRow = $stmtMaint->fetch();
        $isMaintenance = ($maintRow && $maintRow['key_value'] == '1');

        // 2. Statistik Tabel
        $tablesStats = [];
        $targetTables = ['master_products', 'return_sessions', 'return_items', 'master_expeditions', 'users'];
        foreach ($targetTables as $tbl) {
            try {
                $cnt = $pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
                $tablesStats[$tbl] = (int)$cnt;
            } catch (Exception $e) {
                $tablesStats[$tbl] = 0;
            }
        }

        // 3. Info Server & Environment
        $sysInfo = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI',
            'mysql_host' => $db_host ?? '127.0.0.1',
            'database_name' => $db_name ?? 'inbound_return',
            'environment' => !empty($is_remote) ? 'InfinityFree Hosting (Production)' : 'Localhost Laragon',
            'timezone' => date_default_timezone_get(),
            'server_time' => date('Y-m-d H:i:s'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];

        jsonResponse([
            'success' => true,
            'maintenance_mode' => $isMaintenance,
            'tables' => $tablesStats,
            'system' => $sysInfo
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

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
            // Hapus data retur uji coba (hanya return_sessions dan return_items)
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
