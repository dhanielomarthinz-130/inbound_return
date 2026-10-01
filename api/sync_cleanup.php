<?php
/**
 * API SYNC CLEANUP (Berjalan di Server InfinityFree / Cloud)
 * Menghapus data dan file foto di InfinityFree setelah Localhost berhasil menyimpannya.
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

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);

if (empty($body) || (!isset($body['synced_return_session_ids']) && !isset($body['synced_reception_ids']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload konfirmasi sync tidak valid.']);
    exit;
}

$returnIds = array_filter(array_map('intval', (array)($body['synced_return_session_ids'] ?? [])));
$receptionIds = array_filter(array_map('intval', (array)($body['synced_reception_ids'] ?? [])));

$deletedReturnsCount = 0;
$deletedReceptionsCount = 0;
$deletedFilesCount = 0;

try {
    $pdo->beginTransaction();

    // 2. Bersihkan Data & Foto Inbound Unboxing
    if (!empty($returnIds)) {
        $inClause = implode(',', array_fill(0, count($returnIds), '?'));

        // Kumpulkan file foto & video yang akan dihapus dari disk server online
        $stmtPhotos = $pdo->prepare("SELECT package_photo, product_photo, photos, video_path FROM return_sessions WHERE id IN ($inClause)");
        $stmtPhotos->execute($returnIds);
        $sessions = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

        $stmtItemPhotos = $pdo->prepare("SELECT photo_path FROM return_items WHERE session_id IN ($inClause)");
        $stmtItemPhotos->execute($returnIds);
        $itemPhotos = $stmtItemPhotos->fetchAll(PDO::FETCH_COLUMN);

        $filesToDelete = [];
        foreach ($sessions as $s) {
            if (!empty($s['video_path'])) $filesToDelete[] = $s['video_path'];
            if (!empty($s['package_photo'])) $filesToDelete[] = $s['package_photo'];
            if (!empty($s['product_photo'])) $filesToDelete[] = $s['product_photo'];
            if (!empty($s['photos'])) {
                $dec = json_decode($s['photos'], true);
                if (is_array($dec)) {
                    foreach ($dec as $dp) {
                        $pPath = is_array($dp) ? ($dp['path'] ?? '') : $dp;
                        if (!empty($pPath)) $filesToDelete[] = $pPath;
                    }
                }
            }
        }
        foreach ($itemPhotos as $ip) {
            if (!empty($ip)) $filesToDelete[] = $ip;
        }

        // Hapus fisik file foto dari disk InfinityFree
        foreach (array_unique($filesToDelete) as $relPath) {
            $absPath = realpath(__DIR__ . '/../' . ltrim($relPath, '/'));
            if ($absPath && file_exists($absPath) && is_file($absPath)) {
                // Pastikan berada di dalam folder uploads demi keamanan
                if (strpos($absPath, realpath(__DIR__ . '/../uploads')) === 0) {
                    if (@unlink($absPath)) {
                        $deletedFilesCount++;
                    }
                }
            }
        }

        // Hapus record dari database online (cascade delete otomatis menghapus items)
        $stmtDel = $pdo->prepare("DELETE FROM return_sessions WHERE id IN ($inClause)");
        $stmtDel->execute($returnIds);
        $deletedReturnsCount = $stmtDel->rowCount();
    }

    // 3. Bersihkan Data & Foto Receiving Inbound
    if (!empty($receptionIds)) {
        $inRecClause = implode(',', array_fill(0, count($receptionIds), '?'));

        $stmtRecPhotos = $pdo->prepare("SELECT photo_path, package_photos FROM expedition_receptions WHERE id IN ($inRecClause)");
        $stmtRecPhotos->execute($receptionIds);
        $recRows = $stmtRecPhotos->fetchAll(PDO::FETCH_ASSOC);

        $recFilesToDelete = [];
        foreach ($recRows as $rr) {
            if (!empty($rr['photo_path'])) $recFilesToDelete[] = $rr['photo_path'];
            if (!empty($rr['package_photos'])) {
                $decRec = json_decode($rr['package_photos'], true);
                if (is_array($decRec)) {
                    foreach ($decRec as $drp) {
                        $pPath = is_array($drp) ? ($drp['path'] ?? '') : $drp;
                        if (!empty($pPath)) $recFilesToDelete[] = $pPath;
                    }
                }
            }
        }

        // Hapus file foto receiving
        foreach (array_unique($recFilesToDelete) as $relPath) {
            $absPath = realpath(__DIR__ . '/../' . ltrim($relPath, '/'));
            if ($absPath && file_exists($absPath) && is_file($absPath)) {
                if (strpos($absPath, realpath(__DIR__ . '/../uploads')) === 0) {
                    if (@unlink($absPath)) {
                        $deletedFilesCount++;
                    }
                }
            }
        }

        // Hapus record dari database online (cascade delete otomatis menghapus packages)
        $stmtDelRec = $pdo->prepare("DELETE FROM expedition_receptions WHERE id IN ($inRecClause)");
        $stmtDelRec->execute($receptionIds);
        $deletedReceptionsCount = $stmtDelRec->rowCount();
    }

    $pdo->commit();

    // Hapus cache metrik dashboard
    $cacheDir = __DIR__ . '/../uploads/cache/';
    if (is_dir($cacheDir)) {
        $cFiles = glob($cacheDir . 'metrics_*.json');
        if ($cFiles) {
            foreach ($cFiles as $cf) { @unlink($cf); }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Data dan file foto online berhasil dibersihkan dari server InfinityFree.',
        'deleted_returns' => $deletedReturnsCount,
        'deleted_receptions' => $deletedReceptionsCount,
        'deleted_files' => $deletedFilesCount,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Gagal membersihkan data online: ' . $e->getMessage()
    ]);
}
