<?php
/**
 * API SYNC EXPORT (Berjalan di Server InfinityFree / Cloud)
 * Menyiapkan batch data Inbound Unboxing & Receiving untuk ditarik ke PC Server Localhost.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
if (file_exists(__DIR__ . '/../sync_config.php')) {
    require_once __DIR__ . '/../sync_config.php';
}

// 1. Validasi Token Keamanan
$authKey = $_GET['key'] ?? $_SERVER['HTTP_X_SYNC_TOKEN'] ?? '';
$secretKey = defined('SYNC_SECRET_KEY') ? SYNC_SECRET_KEY : 'IEG_RETURN_SYNC_TOKEN_2026_X99A';

if (empty($authKey) || !hash_equals($secretKey, $authKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Akses ditolak! Token autentikasi sinkronisasi tidak valid.']);
    exit;
}

try {
    $limit = defined('SYNC_BATCH_LIMIT') ? (int)SYNC_BATCH_LIMIT : 30;
    if ($limit < 5) $limit = 5;
    if ($limit > 100) $limit = 100;

    // 2. Ambil Data Inbound Unboxing (Return Sessions & Items)
    $stmtReturns = $pdo->prepare("SELECT * FROM return_sessions ORDER BY id ASC LIMIT ?");
    $stmtReturns->bindValue(1, $limit, PDO::PARAM_INT);
    $stmtReturns->execute();
    $rawSessions = $stmtReturns->fetchAll(PDO::FETCH_ASSOC);

    $returnsData = [];
    if (!empty($rawSessions)) {
        $sessionIds = array_column($rawSessions, 'id');
        $inClause = implode(',', array_fill(0, count($sessionIds), '?'));

        $stmtItems = $pdo->prepare("SELECT * FROM return_items WHERE session_id IN ($inClause) ORDER BY id ASC");
        $stmtItems->execute($sessionIds);
        $allItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Kelompokkan items berdasarkan session_id
        $itemsBySession = [];
        foreach ($allItems as $it) {
            $itemsBySession[$it['session_id']][] = $it;
        }

        foreach ($rawSessions as $sess) {
            $photosList = [];
            if (!empty($sess['package_photo'])) $photosList[] = $sess['package_photo'];
            if (!empty($sess['product_photo'])) $photosList[] = $sess['product_photo'];
            if (!empty($sess['photos'])) {
                $decodedPhotos = json_decode($sess['photos'], true);
                if (is_array($decodedPhotos)) {
                    foreach ($decodedPhotos as $dp) {
                        $pPath = is_array($dp) ? ($dp['path'] ?? '') : $dp;
                        if (!empty($pPath) && !in_array($pPath, $photosList)) {
                            $photosList[] = $pPath;
                        }
                    }
                }
            }

            // Kumpulkan foto dari items jika ada
            $sessItems = $itemsBySession[$sess['id']] ?? [];
            foreach ($sessItems as $sItem) {
                if (!empty($sItem['photo_path']) && !in_array($sItem['photo_path'], $photosList)) {
                    $photosList[] = $sItem['photo_path'];
                }
            }

            // Kumpulkan video unboxing jika ada
            $videoFile = !empty($sess['video_path']) ? $sess['video_path'] : null;
            if ($videoFile && !in_array($videoFile, $photosList)) {
                $photosList[] = $videoFile;
            }

            $returnsData[] = [
                'session' => $sess,
                'items' => $sessItems,
                'photo_files' => $photosList,
                'video_file' => $videoFile
            ];
        }
    }

    // 3. Ambil Data Receiving Inbound (Expedition Receptions & Packages)
    $stmtReceptions = $pdo->prepare("SELECT * FROM expedition_receptions ORDER BY id ASC LIMIT ?");
    $stmtReceptions->bindValue(1, $limit, PDO::PARAM_INT);
    $stmtReceptions->execute();
    $rawReceptions = $stmtReceptions->fetchAll(PDO::FETCH_ASSOC);

    $receptionsData = [];
    if (!empty($rawReceptions)) {
        $receptionIds = array_column($rawReceptions, 'id');
        $inRecClause = implode(',', array_fill(0, count($receptionIds), '?'));

        $stmtPackages = $pdo->prepare("SELECT * FROM reception_packages WHERE reception_id IN ($inRecClause) ORDER BY id ASC");
        $stmtPackages->execute($receptionIds);
        $allPackages = $stmtPackages->fetchAll(PDO::FETCH_ASSOC);

        $packagesByRec = [];
        foreach ($allPackages as $pkg) {
            $packagesByRec[$pkg['reception_id']][] = $pkg;
        }

        foreach ($rawReceptions as $rec) {
            $recPhotos = [];
            if (!empty($rec['photo_path'])) $recPhotos[] = $rec['photo_path'];
            if (!empty($rec['package_photos'])) {
                $decodedRecPhotos = json_decode($rec['package_photos'], true);
                if (is_array($decodedRecPhotos)) {
                    foreach ($decodedRecPhotos as $drp) {
                        $rpPath = is_array($drp) ? ($drp['path'] ?? '') : $drp;
                        if (!empty($rpPath) && !in_array($rpPath, $recPhotos)) {
                            $recPhotos[] = $rpPath;
                        }
                    }
                }
            }

            $receptionsData[] = [
                'reception' => $rec,
                'packages' => $packagesByRec[$rec['id']] ?? [],
                'photo_files' => $recPhotos
            ];
        }
    }

    // 4. Ambil Data OCS Orders Lengkap untuk Klaim & Cross-Reference Localhost
    $ordersLimit = 100;
    $stmtOrders = $pdo->prepare("SELECT * FROM ocs_orders WHERE is_synced_to_local = 0 ORDER BY id ASC LIMIT ?");
    $stmtOrders->bindValue(1, $ordersLimit, PDO::PARAM_INT);
    $stmtOrders->execute();
    $ordersData = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    // 5. Ambil Master Data: Users, Master Conditions, Master Expeditions, System Settings
    // Menjamin update pengguna atau master di InfinityFree otomatis tersinkron ke PC Server Localhost
    $usersData = [];
    $conditionsData = [];
    $expeditionsData = [];
    $settingsData = [];

    try {
        $stmtUsers = $pdo->query("SELECT id, username, password, name, role, pin, status, created_at FROM users ORDER BY id ASC");
        if ($stmtUsers) $usersData = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eU) {}

    try {
        $stmtCond = $pdo->query("SELECT id, code, name, description, color, sort_order, created_at FROM master_conditions ORDER BY sort_order ASC, id ASC");
        if ($stmtCond) $conditionsData = $stmtCond->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eC) {}

    try {
        $stmtExp = $pdo->query("SELECT id, code, name, prefix_pattern, status, created_at FROM master_expeditions ORDER BY id ASC");
        if ($stmtExp) $expeditionsData = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eE) {}

    try {
        $stmtSettings = $pdo->query("SELECT key_name, key_value, updated_at FROM system_settings");
        if ($stmtSettings) $settingsData = $stmtSettings->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eS) {}

    echo json_encode([
        'success' => true,
        'server_time' => date('Y-m-d H:i:s'),
        'counts' => [
            'returns'            => count($returnsData),
            'receptions'         => count($receptionsData),
            'orders'             => count($ordersData),
            'users'              => count($usersData),
            'master_conditions'  => count($conditionsData),
            'master_expeditions' => count($expeditionsData),
            'system_settings'    => count($settingsData)
        ],
        'data' => [
            'returns'            => $returnsData,
            'receptions'         => $receptionsData,
            'orders'             => $ordersData,
            'users'              => $usersData,
            'master_conditions'  => $conditionsData,
            'master_expeditions' => $expeditionsData,
            'system_settings'    => $settingsData
        ]
    ], JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Gagal export data sync: ' . $e->getMessage()
    ]);
}
