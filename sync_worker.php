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
 * Helper Request ke InfinityFree dengan Auto-Bypass Security Cookie __test
 */
function callInfinityFreeApi($url, $postPayload = null) {
    static $solvedCookie = null;
    $cookieFile = __DIR__ . '/uploads/logs/infinity_cookie.txt';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    if ($solvedCookie) {
        curl_setopt($ch, CURLOPT_COOKIE, "__test=$solvedCookie");
    }

    if ($postPayload !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postPayload) ? json_encode($postPayload) : $postPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    // Deteksi jika server InfinityFree menyodorkan proteksi AES slowAES.decrypt
    if (strpos($response, 'slowAES.decrypt') !== false || strpos($response, 'toNumbers') !== false) {
        if (preg_match('/a=toNumbers\("([a-f0-9]+)"\),b=toNumbers\("([a-f0-9]+)"\),c=toNumbers\("([a-f0-9]+)"\)/i', $response, $m)) {
            $key = hex2bin($m[1]);
            $iv  = hex2bin($m[2]);
            $ct  = hex2bin($m[3]);
            $decrypted = openssl_decrypt($ct, 'aes-128-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
            $solvedCookie = bin2hex($decrypted);

            // Simpan cookie ke jar
            $domain = parse_url($url, PHP_URL_HOST);
            @file_put_contents($cookieFile, "$domain\tTRUE\t/\tFALSE\t2147483647\t__test\t$solvedCookie\n");

            // Ulangi request langsung dengan cookie yang telah dipecahkan
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 40);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_COOKIE, "__test=$solvedCookie");
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

            if ($postPayload !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postPayload) ? json_encode($postPayload) : $postPayload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
        }
    }

    return [
        'code' => $httpCode,
        'body' => $response,
        'error' => $curlErr
    ];
}

/**
 * Helper Download File Media (Foto & Video) dari Cloud ke Localhost
 */
function downloadCloudFile($cloudUrl, $relPath) {
    if (empty($relPath) || empty($cloudUrl)) return false;
    $targetPath = __DIR__ . '/' . ltrim($relPath, '/');
    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    if (file_exists($targetPath) && filesize($targetPath) > 500) {
        return true;
    }

    $sourceUrl = rtrim($cloudUrl, '/') . '/' . ltrim($relPath, '/');
    $res = callInfinityFreeApi($sourceUrl);

    if ($res['code'] === 200 && !empty($res['body']) && strpos($res['body'], 'slowAES.decrypt') === false) {
        return file_put_contents($targetPath, $res['body']) !== false;
    }
    return false;
}

function downloadCloudPhoto($cloudUrl, $relPath) {
    return downloadCloudFile($cloudUrl, $relPath);
}

/**
 * Eksekutor 1 Putaran Sinkronisasi
 */
function executeSyncRound($pdo) {
    $cloudUrl = defined('CLOUD_BASE_URL') ? rtrim(CLOUD_BASE_URL, '/') : 'http://localhost/retrun.inboud';
    $secretKey = defined('SYNC_SECRET_KEY') ? SYNC_SECRET_KEY : 'IEG_RETURN_SYNC_TOKEN_2026_X99A';

    // 1. Panggil API Sync Export dari Cloud
    $exportUrl = $cloudUrl . '/api/sync_export.php?key=' . urlencode($secretKey);
    $apiRes = callInfinityFreeApi($exportUrl);

    if ($apiRes['code'] !== 200 || empty($apiRes['body'])) {
        $err = "Koneksi ke Cloud gagal [HTTP {$apiRes['code']}]. " . ($apiRes['error'] ?: substr($apiRes['body'], 0, 150));
        return ['success' => false, 'error' => $err];
    }

    $data = json_decode($apiRes['body'], true);
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

        // Download semua file foto & video unboxing ke disk PC Localhost
        foreach ($photoFiles as $pf) {
            if (downloadCloudFile($cloudUrl, $pf)) {
                $downloadedPhotosCount++;
            }
        }
        if (!empty($sess['video_path'])) {
            downloadCloudFile($cloudUrl, $sess['video_path']);
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
                    session_id, barcode, wrong_barcode, wrong_product_name, product_name, sku, seller_sku, sap_code,
                    batch_no, exp_date, type, qty, `condition`, damage_reason, photo_path, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $it) {
                $stmtInsItem->execute([
                    $newSessionId,
                    $it['barcode'] ?? '',
                    $it['wrong_barcode'] ?? null,
                    $it['wrong_product_name'] ?? null,
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
    if (!empty($receptions)) {
        try {
            $colsRec = $pdo->query("SHOW COLUMNS FROM expedition_receptions")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('sack_number', $colsRec)) {
                try { $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN sack_number VARCHAR(100) NULL AFTER courier_name"); } catch (Exception $e) {}
            }
            $colsPkg = $pdo->query("SHOW COLUMNS FROM reception_packages")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('sack_number', $colsPkg)) {
                try { $pdo->exec("ALTER TABLE reception_packages ADD COLUMN sack_number VARCHAR(100) NULL AFTER package_barcode"); } catch (Exception $e) {}
            }
        } catch (Exception $e) {}
    }

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
                    receipt_number, expedition, courier_name, sack_number, vehicle_no, operator_name,
                    total_packages, notes, photo_path, package_photos, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    expedition = VALUES(expedition),
                    sack_number = VALUES(sack_number),
                    total_packages = VALUES(total_packages)
            ");
            $stmtInsRec->execute([
                $rec['receipt_number'],
                $rec['expedition'] ?? '',
                $rec['courier_name'] ?? null,
                $rec['sack_number'] ?? null,
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
                    INSERT INTO reception_packages (reception_id, package_barcode, sack_number, photo_path, scanned_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($packages as $pkg) {
                    $stmtInsPkg->execute([
                        $newRecId,
                        $pkg['package_barcode'] ?? '',
                        $pkg['sack_number'] ?? ($rec['sack_number'] ?? null),
                        $pkg['photo_path'] ?? null,
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

    // 4. Proses OCS Orders Lengkap untuk Klaim
    $orders = $data['data']['orders'] ?? [];
    $syncedOrderIds = [];

    if (!empty($orders)) {
        $hasIsSyncedCol = false;
        try {
            $colsOcs = $pdo->query("SHOW COLUMNS FROM ocs_orders")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('is_synced_to_local', $colsOcs)) {
                try { $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN is_synced_to_local TINYINT(1) DEFAULT 0 AFTER raw_payload"); $colsOcs[] = 'is_synced_to_local'; } catch (Exception $eCol) {}
            }
            $hasIsSyncedCol = in_array('is_synced_to_local', $colsOcs);
        } catch (Exception $eCols) {}

        $colSyncPart = $hasIsSyncedCol ? ", is_synced_to_local" : "";
        $valSyncPart = $hasIsSyncedCol ? ", 1" : "";
        $updSyncPart = $hasIsSyncedCol ? "is_synced_to_local = 1," : "";

        $stmtUpsertOrd = $pdo->prepare("
            INSERT INTO ocs_orders (
                order_id, tracking_number, platform_id, commerce_platform, 
                shop_name, shipping_provider, status_code, status_name, product_name, seller_sku,
                total_qty, package_price, original_price, seller_discount, platform_discount,
                shipping_fee, service_fee, subtotal, total_amount, gmv, nmv,
                customer_name, customer_phone, customer_address, order_items_json,
                has_packing_video, packing_video_url, order_created_at, raw_payload{$colSyncPart}
            ) VALUES (
                :order_id, :tracking_number, :platform_id, :commerce_platform, 
                :shop_name, :shipping_provider, :status_code, :status_name, :product_name, :seller_sku,
                :total_qty, :package_price, :original_price, :seller_discount, :platform_discount,
                :shipping_fee, :service_fee, :subtotal, :total_amount, :gmv, :nmv,
                :customer_name, :customer_phone, :customer_address, :order_items_json,
                :has_packing_video, :packing_video_url, :order_created_at, :raw_payload{$valSyncPart}
            )
            ON DUPLICATE KEY UPDATE 
                tracking_number   = VALUES(tracking_number),
                platform_id       = VALUES(platform_id),
                commerce_platform = VALUES(commerce_platform),
                shop_name         = VALUES(shop_name),
                shipping_provider = VALUES(shipping_provider),
                status_code       = VALUES(status_code),
                status_name       = VALUES(status_name),
                product_name      = VALUES(product_name),
                seller_sku        = VALUES(seller_sku),
                total_qty         = VALUES(total_qty),
                package_price     = VALUES(package_price),
                original_price    = VALUES(original_price),
                seller_discount   = VALUES(seller_discount),
                platform_discount = VALUES(platform_discount),
                shipping_fee      = VALUES(shipping_fee),
                service_fee       = VALUES(service_fee),
                subtotal          = VALUES(subtotal),
                total_amount      = VALUES(total_amount),
                gmv               = VALUES(gmv),
                nmv               = VALUES(nmv),
                customer_name     = VALUES(customer_name),
                customer_phone    = VALUES(customer_phone),
                customer_address  = VALUES(customer_address),
                order_items_json  = VALUES(order_items_json),
                order_created_at  = VALUES(order_created_at),
                raw_payload       = VALUES(raw_payload),
                is_synced_to_local = 1
        ");

        foreach ($orders as $ord) {
            if (empty($ord['order_id'])) continue;
            try {
                $stmtUpsertOrd->execute([
                    ':order_id'          => $ord['order_id'],
                    ':tracking_number'   => $ord['tracking_number'] ?? null,
                    ':platform_id'       => $ord['platform_id'] ?? null,
                    ':commerce_platform' => $ord['commerce_platform'] ?? null,
                    ':shop_name'         => $ord['shop_name'] ?? null,
                    ':shipping_provider' => $ord['shipping_provider'] ?? null,
                    ':status_code'       => $ord['status_code'] ?? null,
                    ':status_name'       => $ord['status_name'] ?? null,
                    ':product_name'      => $ord['product_name'] ?? null,
                    ':seller_sku'        => $ord['seller_sku'] ?? null,
                    ':total_qty'         => (int)($ord['total_qty'] ?? 1),
                    ':package_price'     => (float)($ord['package_price'] ?? 0),
                    ':original_price'    => (float)($ord['original_price'] ?? 0),
                    ':seller_discount'   => (float)($ord['seller_discount'] ?? 0),
                    ':platform_discount' => (float)($ord['platform_discount'] ?? 0),
                    ':shipping_fee'      => (float)($ord['shipping_fee'] ?? 0),
                    ':service_fee'       => (float)($ord['service_fee'] ?? 0),
                    ':subtotal'          => (float)($ord['subtotal'] ?? 0),
                    ':total_amount'      => (float)($ord['total_amount'] ?? 0),
                    ':gmv'               => (float)($ord['gmv'] ?? 0),
                    ':nmv'               => (float)($ord['nmv'] ?? 0),
                    ':customer_name'     => $ord['customer_name'] ?? null,
                    ':customer_phone'    => $ord['customer_phone'] ?? null,
                    ':customer_address'  => $ord['customer_address'] ?? null,
                    ':order_items_json'  => $ord['order_items_json'] ?? null,
                    ':has_packing_video' => (int)($ord['has_packing_video'] ?? 0),
                    ':packing_video_url' => $ord['packing_video_url'] ?? null,
                    ':order_created_at'  => $ord['order_created_at'] ?? null,
                    ':raw_payload'       => $ord['raw_payload'] ?? null
                ]);
                $syncedOrderIds[] = (int)$ord['id'];
            } catch (Exception $eOrd) {
                writeSyncLog("Error insert OCS Order [{$ord['order_id']}]: " . $eOrd->getMessage());
            }
        }
    }

    // 5. Sinkronisasi Master Data dari InfinityFree ke Localhost
    // (Users, Master Conditions, Master Expeditions, System Settings)
    $syncedUsersCount = 0;
    $syncedConditionsCount = 0;
    $syncedExpeditionsCount = 0;

    $users = $data['data']['users'] ?? [];
    if (!empty($users)) {
        $stmtUpsertUser = $pdo->prepare("
            INSERT INTO users (username, password, name, role, pin, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                password   = VALUES(password),
                name       = VALUES(name),
                role       = VALUES(role),
                pin        = VALUES(pin),
                status     = VALUES(status)
        ");
        foreach ($users as $u) {
            if (empty($u['username'])) continue;
            try {
                $stmtUpsertUser->execute([
                    $u['username'],
                    $u['password'] ?? '',
                    $u['name'] ?? $u['username'],
                    $u['role'] ?? 'operator',
                    $u['pin'] ?? '123456',
                    $u['status'] ?? 'ACTIVE',
                    $u['created_at'] ?? date('Y-m-d H:i:s')
                ]);
                $syncedUsersCount++;
            } catch (Exception $eUser) {
                writeSyncLog("Error sync user [{$u['username']}]: " . $eUser->getMessage());
            }
        }
    }

    $conditions = $data['data']['master_conditions'] ?? [];
    if (!empty($conditions)) {
        $stmtUpsertCond = $pdo->prepare("
            INSERT INTO master_conditions (code, name, description, color, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                name        = VALUES(name),
                description = VALUES(description),
                color       = VALUES(color),
                sort_order  = VALUES(sort_order)
        ");
        foreach ($conditions as $c) {
            if (empty($c['code'])) continue;
            try {
                $stmtUpsertCond->execute([
                    $c['code'],
                    $c['name'] ?? $c['code'],
                    $c['description'] ?? null,
                    $c['color'] ?? 'slate',
                    (int)($c['sort_order'] ?? 0),
                    $c['created_at'] ?? date('Y-m-d H:i:s')
                ]);
                $syncedConditionsCount++;
            } catch (Exception $eCond) {
                writeSyncLog("Error sync master_condition [{$c['code']}]: " . $eCond->getMessage());
            }
        }
    }

    $expeditions = $data['data']['master_expeditions'] ?? [];
    if (!empty($expeditions)) {
        $stmtUpsertExp = $pdo->prepare("
            INSERT INTO master_expeditions (code, name, prefix_pattern, status, created_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                name           = VALUES(name),
                prefix_pattern = VALUES(prefix_pattern),
                status         = VALUES(status)
        ");
        foreach ($expeditions as $e) {
            if (empty($e['code'])) continue;
            try {
                $stmtUpsertExp->execute([
                    $e['code'],
                    $e['name'] ?? $e['code'],
                    $e['prefix_pattern'] ?? '',
                    $e['status'] ?? 'ACTIVE',
                    $e['created_at'] ?? date('Y-m-d H:i:s')
                ]);
                $syncedExpeditionsCount++;
            } catch (Exception $eExp) {
                writeSyncLog("Error sync master_expedition [{$e['code']}]: " . $eExp->getMessage());
            }
        }
    }

    $settings = $data['data']['system_settings'] ?? [];
    if (!empty($settings)) {
        $stmtUpsertSet = $pdo->prepare("
            INSERT INTO system_settings (key_name, key_value, updated_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                key_value  = VALUES(key_value),
                updated_at = VALUES(updated_at)
        ");
        foreach ($settings as $s) {
            if (empty($s['key_name'])) continue;
            try {
                $stmtUpsertSet->execute([
                    $s['key_name'],
                    $s['key_value'] ?? null,
                    $s['updated_at'] ?? date('Y-m-d H:i:s')
                ]);
            } catch (Exception $eSet) {}
        }
    }

    if ($syncedUsersCount > 0 || $syncedConditionsCount > 0 || $syncedExpeditionsCount > 0) {
        writeSyncLog("Sync Master Data Sukses: {$syncedUsersCount} users, {$syncedConditionsCount} kondisi retur, {$syncedExpeditionsCount} ekspedisi.");
    }

    // 6. Kirim Konfirmasi Cleanup / Sync Status ke InfinityFree jika ada transaksi yang berhasil disimpan
    $cleanupResult = null;
    if (!empty($syncedReturnIds) || !empty($syncedReceptionIds) || !empty($syncedOrderIds)) {
        $cleanupUrl = $cloudUrl . '/api/sync_cleanup.php?key=' . urlencode($secretKey);
        $payload = json_encode([
            'synced_return_session_ids' => $syncedReturnIds,
            'synced_reception_ids'      => $syncedReceptionIds,
            'synced_ocs_order_ids'      => $syncedOrderIds
        ]);

        $cleanResp = callInfinityFreeApi($cleanupUrl, $payload);
        $cleanupResult = json_decode($cleanResp['body'] ?? '', true);
        writeSyncLog("Sync Berhasil: " . count($syncedReturnIds) . " return unboxing, " . count($syncedReceptionIds) . " receiving, " . count($syncedOrderIds) . " OCS orders, $downloadedPhotosCount foto terunduh.");
    }

    return [
        'success'            => true,
        'synced_returns'     => count($syncedReturnIds),
        'synced_receptions'  => count($syncedReceptionIds),
        'synced_orders'      => count($syncedOrderIds),
        'synced_users'       => $syncedUsersCount,
        'synced_conditions'  => $syncedConditionsCount,
        'synced_expeditions' => $syncedExpeditionsCount,
        'downloaded_photos'  => $downloadedPhotosCount,
        'cloud_cleaned'      => !empty($cleanupResult['success']),
        'timestamp'          => date('Y-m-d H:i:s')
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
