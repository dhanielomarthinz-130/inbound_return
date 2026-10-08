<?php
/**
 * API SYNC RESTORE (Berjalan di Server InfinityFree / Cloud)
 * Mengembalikan / memasukkan kembali data transaksi Inbound Unboxing & Receiving
 * yang sebelumnya terambil ke laptop agar dapat disinkronkan ke PC Server Kantor.
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
    echo json_encode(['success' => false, 'error' => 'Akses ditolak! Token autentikasi tidak valid.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);

if (empty($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload kosong atau JSON tidak valid.']);
    exit;
}

$returns    = $body['returns'] ?? [];
$receptions = $body['receptions'] ?? [];

$restoredReturnsCount = 0;
$restoredReceptionsCount = 0;

try {
    $pdo->beginTransaction();

    // 2. Restore Return Sessions & Items
    if (!empty($returns)) {
        $stmtChkSess = $pdo->prepare("SELECT id FROM return_sessions WHERE invoice_number = ? LIMIT 1");
        $stmtInsSess = $pdo->prepare("
            INSERT INTO return_sessions (
                invoice_number, customer_name, expedition, operator_name, status,
                total_items, total_good, total_damaged, notes, video_path,
                package_photo, product_photo, photos, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtUpdSess = $pdo->prepare("
            UPDATE return_sessions SET
                customer_name = ?, expedition = ?, operator_name = ?, status = ?,
                total_items = ?, total_good = ?, total_damaged = ?, notes = ?,
                video_path = COALESCE(?, video_path),
                package_photo = COALESCE(?, package_photo),
                product_photo = COALESCE(?, product_photo),
                photos = COALESCE(?, photos),
                created_at = ?
            WHERE id = ?
        ");
        $stmtDelItems = $pdo->prepare("DELETE FROM return_items WHERE session_id = ?");
        $stmtInsItem = $pdo->prepare("
            INSERT INTO return_items (
                session_id, barcode, wrong_barcode, wrong_product_name, product_name, sku, seller_sku, sap_code,
                batch_no, exp_date, type, qty, `condition`, damage_reason, photo_path, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($returns as $itemGroup) {
            $sess = $itemGroup['session'] ?? [];
            $items = $itemGroup['items'] ?? [];
            if (empty($sess['invoice_number'])) continue;

            $stmtChkSess->execute([$sess['invoice_number']]);
            $existingId = $stmtChkSess->fetchColumn();

            if ($existingId) {
                $sessId = (int)$existingId;
                $stmtUpdSess->execute([
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
                    $sess['created_at'] ?? date('Y-m-d H:i:s'),
                    $sessId
                ]);
                $stmtDelItems->execute([$sessId]);
            } else {
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
                $sessId = (int)$pdo->lastInsertId();
            }

            foreach ($items as $it) {
                $stmtInsItem->execute([
                    $sessId,
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
            $restoredReturnsCount++;
        }
    }

    // 3. Restore Expedition Receptions & Packages
    if (!empty($receptions)) {
        $stmtChkRec = $pdo->prepare("SELECT id FROM expedition_receptions WHERE receipt_number = ? LIMIT 1");
        $stmtInsRec = $pdo->prepare("
            INSERT INTO expedition_receptions (
                receipt_number, expedition, courier_name, sack_number, vehicle_no, operator_name,
                total_packages, notes, photo_path, package_photos, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtUpdRec = $pdo->prepare("
            UPDATE expedition_receptions SET
                expedition = ?, courier_name = ?, sack_number = ?, vehicle_no = ?, operator_name = ?,
                total_packages = ?, notes = ?, photo_path = ?, package_photos = ?, status = ?, created_at = ?
            WHERE id = ?
        ");
        $stmtDelPkgs = $pdo->prepare("DELETE FROM reception_packages WHERE reception_id = ?");
        $stmtInsPkg = $pdo->prepare("
            INSERT INTO reception_packages (reception_id, package_barcode, sack_number, photo_path, scanned_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($receptions as $recGroup) {
            $rec = $recGroup['reception'] ?? [];
            $packages = $recGroup['packages'] ?? [];
            if (empty($rec['receipt_number'])) continue;

            $stmtChkRec->execute([$rec['receipt_number']]);
            $existingRecId = $stmtChkRec->fetchColumn();

            if ($existingRecId) {
                $recId = (int)$existingRecId;
                $stmtUpdRec->execute([
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
                    $rec['created_at'] ?? date('Y-m-d H:i:s'),
                    $recId
                ]);
                $stmtDelPkgs->execute([$recId]);
            } else {
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
                $recId = (int)$pdo->lastInsertId();
            }

            foreach ($packages as $pkg) {
                $stmtInsPkg->execute([
                    $recId,
                    $pkg['package_barcode'] ?? '',
                    $pkg['sack_number'] ?? ($rec['sack_number'] ?? null),
                    $pkg['photo_path'] ?? null,
                    $pkg['scanned_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
            $restoredReceptionsCount++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'             => true,
        'restored_returns'    => $restoredReturnsCount,
        'restored_receptions' => $restoredReceptionsCount,
        'timestamp'           => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
