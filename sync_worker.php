<?php
/**
 * AUTO SYNC WORKER (Berjalan di PC Server Localhost)
 * Menarik data transaksi & receiving serta foto-foto dari InfinityFree secara otomatis,
 * menyimpannya ke database MySQL Localhost, lalu memerintahkan InfinityFree untuk menghapusnya.
 * 
 * Penggunaan:
 * 1. CLI Sekali jalan: php sync_worker.php
 * 2. CLI Daemon (Otomatis Loop): php sync_worker.php --daemon
 * 3. Browser / Background AJAX: http://localhost/retrun.inboud/sync_worker.php
 */

// Cegah timeout untuk proses download batch
set_time_limit(300);

require_once __DIR__ . '/config.php';
if (file_exists(__DIR__ . '/sync_config.php')) {
    require_once __DIR__ . '/sync_config.php';
}

$isCli = (php_sapi_name() === 'cli');
$isDaemon = $isCli && (isset($argv[1]) && $argv[1] === '--daemon');

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

function writeSyncLog($msg) {
    $logDir = __DIR__ . '/uploads/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logDir . '/sync.log', $line, FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}

/**
 * Helper Download File Gambar dari Cloud ke Localhost
 */
function downloadCloudPhoto($cloudUrl, $relPath) {
    if (empty($relPath) || empty($cloudUrl)) return false;
    $targetPath = __DIR__ . '/' . ltrim($relPath, '/');
    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    // Jika file sudah ada di lokal dengan ukuran valid, lewati download
    if (file_exists($targetPath) && filesize($targetPath) > 500) {
        return true;
    }

    $sourceUrl = rtrim($cloudUrl, '/') . '/' . ltrim($relPath, '/');
    
    // Download via cURL
    $ch = curl_init($sourceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'IEG-SyncWorker/1.0');
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($data)) {
        return file_put_contents($targetPath, $data) !== false;
    }
    return false;
}

/**
 * Eksekutor 1 Putaran Sinkronisasi
 */
function executeSyncRound($pdo) {
    $cloudUrl = defined('CLOUD_BASE_URL') ? rtrim(CLOUD_BASE_URL, '/') : 'http://localhost/retrun.inboud';
    $secretKey = defined('SYNC_SECRET_KEY') ? SYNC_SECRET_KEY : 'IEG_RETURN_SYNC_TOKEN_2026_X99A';

    // 1. Panggil API Sync Export dari Cloud
    $exportUrl = $cloudUrl . '/api/sync_export.php?key=' . urlencode($secretKey);

    $ch = curl_init($exportUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'IEG-SyncWorker/1.0');
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) {
        $err = "Koneksi ke Cloud gagal [HTTP $httpCode]. " . ($curlError ?: substr($response, 0, 150));
        return ['success' => false, 'error' => $err];
    }

    $data = json_decode($response, true);
    if (!$data || empty($data['success'])) {
        $err = $data['error'] ?? 'Respon JSON dari Cloud tidak valid';
        return ['success' => false, 'error' => $err];
    }

    $returns = $data['data']['returns'] ?? [];
    $receptions = $data['data']['receptions'] ?? [];

    $syncedReturnIds = [];
    $syncedReceptionIds = [];
    $downloadedPhotosCount = 0;

    // 2. Proses Inbound Unboxing
    foreach ($returns as $itemGroup) {
        $sess = $itemGroup['session'] ?? [];
        $items = $itemGroup['items'] ?? [];
        $photoFiles = $itemGroup['photo_files'] ?? [];

        if (empty($sess) || empty($sess['invoice_number'])) continue;

        // Download semua file foto unboxing ke disk PC Localhost
        foreach ($photoFiles as $pf) {
            if (downloadCloudPhoto($cloudUrl, $pf)) {
                $downloadedPhotosCount++;
            }
        }

        try {
            $pdo->beginTransaction();

            $stmtInsSess = $pdo->prepare("
                INSERT INTO return_sessions (
                    invoice_number, customer_name, expedition, operator_name, status,
                    total_items, total_good, total_damaged, notes, video_path,
                    package_photo, product_photo, photos, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsSess->execute([
                $sess['invoice_number'],
                $sess['customer_name'] ?? 'Pelanggan Umum',
                $sess['expedition'] ?? null,
                $sess['operator_name'] ?? 'Gudang 01',
                $sess['status'] ?? 'COMPLETED',
                (int)($sess['total_items'] ?? count($items)),
                (int)($sess['total_good'] ?? 0),
                (int)($sess['total_damaged'] ?? 0),
                $sess['notes'] ?? null,
                $sess['video_path'] ?? null,
                $sess['package_photo'] ?? null,
                $sess['product_photo'] ?? null,
                $sess['photos'] ?? null,
                $sess['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $newSessionId = $pdo->lastInsertId();

            // Insert Items
            $stmtInsItem = $pdo->prepare("
                INSERT INTO return_items (
                    session_id, barcode, product_name, sku, seller_sku, sap_code,
                    batch_no, exp_date, type, qty, `condition`, damage_reason, photo_path, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $it) {
                $stmtInsItem->execute([
                    $newSessionId,
                    $it['barcode'] ?? '',
                    $it['product_name'] ?? '',
                    $it['sku'] ?? null,
                    $it['seller_sku'] ?? null,
                    $it['sap_code'] ?? null,
                    $it['batch_no'] ?? null,
                    $it['exp_date'] ?? null,
                    $it['type'] ?? 'GOOD',
                    (int)($it['qty'] ?? 1),
                    $it['condition'] ?? 'GOOD',
                    $it['damage_reason'] ?? null,
                    $it['photo_path'] ?? null,
                    $it['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }

            $pdo->commit();
            $syncedReturnIds[] = (int)$sess['id'];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            writeSyncLog("Error insert return invoice [{$sess['invoice_number']}]: " . $e->getMessage());
        }
    }

    // 3. Proses Receiving Inbound
    foreach ($receptions as $recGroup) {
        $rec = $recGroup['reception'] ?? [];
        $packages = $recGroup['packages'] ?? [];
        $photoFiles = $recGroup['photo_files'] ?? [];

        if (empty($rec) || empty($rec['receipt_number'])) continue;

        // Download foto receiving
        foreach ($photoFiles as $rpf) {
            if (downloadCloudPhoto($cloudUrl, $rpf)) {
                $downloadedPhotosCount++;
            }
        }

        try {
            $pdo->beginTransaction();

            $stmtInsRec = $pdo->prepare("
                INSERT INTO expedition_receptions (
                    receipt_number, expedition, courier_name, vehicle_no, operator_name,
                    total_packages, notes, photo_path, package_photos, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    expedition = VALUES(expedition),
                    total_packages = VALUES(total_packages)
            ");
            $stmtInsRec->execute([
                $rec['receipt_number'],
                $rec['expedition'] ?? '',
                $rec['courier_name'] ?? null,
                $rec['vehicle_no'] ?? null,
                $rec['operator_name'] ?? '',
                (int)($rec['total_packages'] ?? count($packages)),
                $rec['notes'] ?? null,
                $rec['photo_path'] ?? null,
                $rec['package_photos'] ?? null,
                $rec['status'] ?? 'RECEIVED',
                $rec['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $newRecId = $pdo->lastInsertId();

            if (!empty($packages)) {
                $stmtInsPkg = $pdo->prepare("
                    INSERT INTO reception_packages (reception_id, package_barcode, scanned_at)
                    VALUES (?, ?, ?)
                ");
                foreach ($packages as $pkg) {
                    $stmtInsPkg->execute([
                        $newRecId,
                        $pkg['package_barcode'] ?? '',
                        $pkg['scanned_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }

            $pdo->commit();
            $syncedReceptionIds[] = (int)$rec['id'];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            writeSyncLog("Error insert reception [{$rec['receipt_number']}]: " . $e->getMessage());
        }
    }

    // 4. Kirim Konfirmasi Cleanup ke InfinityFree jika ada yang berhasil disimpan
    $cleanupResult = null;
    if (!empty($syncedReturnIds) || !empty($syncedReceptionIds)) {
        $cleanupUrl = $cloudUrl . '/api/sync_cleanup.php?key=' . urlencode($secretKey);
        $payload = json_encode([
            'synced_return_session_ids' => $syncedReturnIds,
            'synced_reception_ids' => $syncedReceptionIds
        ]);

        $chClean = curl_init($cleanupUrl);
        curl_setopt($chClean, CURLOPT_POST, true);
        curl_setopt($chClean, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($chClean, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($chClean, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chClean, CURLOPT_TIMEOUT, 30);
        curl_setopt($chClean, CURLOPT_SSL_VERIFYPEER, false);
        $cleanResp = curl_exec($chClean);
        curl_close($chClean);

        $cleanupResult = json_decode($cleanResp, true);
        writeSyncLog("Sync Berhasil: " . count($syncedReturnIds) . " return unboxing, " . count($syncedReceptionIds) . " receiving, $downloadedPhotosCount foto terunduh. InfinityFree dibersihkan.");
    }

    return [
        'success' => true,
        'synced_returns' => count($syncedReturnIds),
        'synced_receptions' => count($syncedReceptionIds),
        'downloaded_photos' => $downloadedPhotosCount,
        'cloud_cleaned' => !empty($cleanupResult['success']),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// -------------------------------------------------------------
// EKSEKUSI
// -------------------------------------------------------------
if ($isDaemon) {
    writeSyncLog("Memulai IEG Auto-Sync Daemon di PC Localhost (Interval: 30 detik)... Tekan Ctrl+C untuk berhenti.");
    while (true) {
        try {
            $result = executeSyncRound($pdo);
            if (!empty($result['synced_returns']) || !empty($result['synced_receptions'])) {
                writeSyncLog("Siklus selesai: {$result['synced_returns']} retur & {$result['synced_receptions']} receiving ditarik.");
            }
        } catch (Exception $e) {
            writeSyncLog("Exception pada siklus sync: " . $e->getMessage());
        }
        sleep(30); // Jeda 30 detik tiap putaran
    }
} else {
    $result = executeSyncRound($pdo);
    if ($isCli) {
        echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo json_encode($result);
    }
}
