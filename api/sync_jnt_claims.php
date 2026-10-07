<?php
/**
 * api/sync_jnt_claims.php
 * Script Sinkronisasi Klaim JNT (Berjalan di Localhost).
 * 
 * Alur:
 * 1. send_to_cloud : Kirim data tabel klaim JNT (tanpa foto) ke InfinityFree untuk di-approval Accounting.
 * 2. pull_from_cloud : Tarik hasil approval Accounting dari InfinityFree kembali ke Localhost.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
if (file_exists(__DIR__ . '/../sync_config.php')) {
    require_once __DIR__ . '/../sync_config.php';
}

ensureClaimStatusColumn($pdo);

$currentUser = getSessionUser();
if (!$currentUser) {
    jsonResponse(['error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

// Helper Request ke InfinityFree dengan Auto-Bypass Security Cookie __test
function requestInfinityFreeApi($url, $postPayload = null) {
    static $solvedCookie = null;
    $logDir = __DIR__ . '/../uploads/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    $cookieFile = $logDir . '/infinity_cookie.txt';

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

    if (strpos($response, 'slowAES.decrypt') !== false || strpos($response, 'toNumbers') !== false) {
        if (preg_match('/a=toNumbers\("([a-f0-9]+)"\),b=toNumbers\("([a-f0-9]+)"\),c=toNumbers\("([a-f0-9]+)"\)/i', $response, $m)) {
            $key = hex2bin($m[1]);
            $iv  = hex2bin($m[2]);
            $ct  = hex2bin($m[3]);
            $decrypted = openssl_decrypt($ct, 'aes-128-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
            $solvedCookie = bin2hex($decrypted);

            $domain = parse_url($url, PHP_URL_HOST);
            @file_put_contents($cookieFile, "$domain\tTRUE\t/\tFALSE\t2147483647\t__test\t$solvedCookie\n");

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
            curl_close($ch);
        }
    }

    return $response;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$cloudBaseUrl = defined('CLOUD_BASE_URL') ? rtrim(CLOUD_BASE_URL, '/') : 'https://returninboundieg.great-site.net';
$secretKey = defined('SYNC_SECRET_KEY') ? SYNC_SECRET_KEY : 'IEG_RETURN_SYNC_TOKEN_2026_X99A';

// =========================================================================
// ACTION 1: SEND_TO_CLOUD (Kirim klaim JNT ke Cloud Accounting, Tanpa Foto)
// =========================================================================
if ($action === 'send_to_cloud') {
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    if (!is_array($inputData)) $inputData = $_POST;

    $invoices = (array)($inputData['invoices'] ?? []);
    if (is_string($invoices)) {
        $invoices = preg_split('/[\r\n,]+/', $invoices);
    }
    $invoices = array_values(array_unique(array_filter(array_map('trim', $invoices))));

    if (empty($invoices)) {
        jsonResponse(['error' => 'Pilih minimal 1 paket J&T untuk diajukan ke Accounting!'], 400);
    }

    try {
        $ph = implode(',', array_fill(0, count($invoices), '?'));

        // Ambil data sessions JNT
        $stmtSessions = $pdo->prepare("
            SELECT rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.customer_name,
                   rs.status, rs.claim_status, rs.accounting_status, rs.total_items, rs.total_damaged,
                   rs.video_path, rs.notes, rs.created_at
            FROM return_sessions rs
            WHERE rs.invoice_number IN ($ph)
              AND (rs.expedition LIKE '%JNT%' OR rs.expedition LIKE '%J&T%')
        ");
        $stmtSessions->execute($invoices);
        $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

        if (empty($sessions)) {
            jsonResponse(['error' => 'Tidak ditemukan paket ekspedisi J&T dari nomor resi yang dipilih.'], 400);
        }

        $sessionIds = array_column($sessions, 'id');
        $phIds = implode(',', array_fill(0, count($sessionIds), '?'));

        // Ambil data items & alasan kerusakan
        $stmtItems = $pdo->prepare("
            SELECT ri.session_id, ri.product_name, ri.sku, ri.seller_sku, ri.qty, ri.condition, ri.type, ri.damage_reason
            FROM return_items ri
            WHERE ri.session_id IN ($phIds)
        ");
        $stmtItems->execute($sessionIds);
        $allItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $itemsGrouped = [];
        foreach ($allItems as $it) {
            $itemsGrouped[$it['session_id']][] = $it;
        }

        // Ambil data total claim amount dari OCS Orders (Find Orders / Picklist)
        $invList = array_column($sessions, 'invoice_number');
        $phInv = implode(',', array_fill(0, count($invList), '?'));
        $stmtOcs = $pdo->prepare("
            SELECT order_id, tracking_number, total_amount, package_price, original_price
            FROM ocs_orders
            WHERE order_id IN ($phInv) OR tracking_number IN ($phInv)
        ");
        $stmtOcs->execute(array_merge($invList, $invList));
        $ocsRows = $stmtOcs->fetchAll(PDO::FETCH_ASSOC);

        $ocsMap = [];
        foreach ($ocsRows as $o) {
            if (!empty($o['order_id'])) $ocsMap[trim($o['order_id'])] = $o;
            if (!empty($o['tracking_number'])) $ocsMap[trim($o['tracking_number'])] = $o;
        }

        // Rakit payload tabel murni (DATA TABLE ONLY, TANPA FOTO)
        $payloadItems = [];
        $validInvoices = [];

        foreach ($sessions as $s) {
            $inv = trim($s['invoice_number']);
            $sItems = $itemsGrouped[$s['id']] ?? [];

            // Rincian produk rusak
            $damagedProductNames = [];
            $damageReasons = [];
            foreach ($sItems as $si) {
                $isDamaged = (!in_array(strtoupper(trim($si['condition'] ?? '')), ['GOOD', 'BAGUS']) && !empty($si['condition']))
                          || (!in_array(strtoupper(trim($si['type'] ?? '')), ['GOOD', 'BAGUS']) && !empty($si['type']))
                          || !empty($si['damage_reason']);
                if ($isDamaged) {
                    $damagedProductNames[] = $si['product_name'] . ' (x' . ($si['qty'] ?: 1) . ')';
                    if (!empty($si['damage_reason'])) {
                        $damageReasons[] = $si['damage_reason'];
                    }
                }
            }

            if (empty($damagedProductNames)) {
                foreach ($sItems as $si) {
                    $damagedProductNames[] = $si['product_name'] . ' (x' . ($si['qty'] ?: 1) . ')';
                }
            }

            $itemsSummary = implode(', ', array_unique($damagedProductNames));
            $dmgReasonText = !empty($damageReasons) ? implode('; ', array_unique($damageReasons)) : ($s['notes'] ?: 'Paket rusak unboxing');

            // Nilai klaim dari OCS Orders (Find Orders / Picklist)
            $ocsData = $ocsMap[$inv] ?? null;
            $totalClaimAmount = 0.00;
            $orderId = $inv;

            if ($ocsData) {
                $orderId = !empty($ocsData['order_id']) ? $ocsData['order_id'] : $inv;
                $tot = isset($ocsData['total_amount']) ? (float)$ocsData['total_amount'] : (float)($ocsData['package_price'] ?? 0);
                $totalClaimAmount = $tot;
            }

            $payloadItems[] = [
                'invoice_number'         => $inv,
                'order_id'               => $orderId,
                'expedition'             => $s['expedition'] ?: 'J&T',
                'unboxing_date'          => $s['created_at'],
                'operator_name'          => $s['operator_name'] ?: 'Admin',
                'customer_name'          => $s['customer_name'] ?: 'Pelanggan Umum',
                'damaged_reason'         => $dmgReasonText,
                'items_summary'          => $itemsSummary,
                'total_claim_amount'     => $totalClaimAmount,
                'total_claim_amount_fmt' => 'Rp ' . number_format($totalClaimAmount, 0, ',', '.'),
                'video_path'             => $s['video_path'] ?? null
            ];

            $validInvoices[] = $inv;
        }

        // Kirim ke InfinityFree API
        $targetUrl = $cloudBaseUrl . '/api/sync_claim_approval.php?action=receive_from_local&key=' . urlencode($secretKey);
        $cloudResponse = requestInfinityFreeApi($targetUrl, ['items' => $payloadItems]);
        $respJson = json_decode($cloudResponse, true);

        if (!$respJson || empty($respJson['success'])) {
            $err = $respJson['error'] ?? ('Gagal respon dari server online InfinityFree: ' . substr(strip_tags($cloudResponse), 0, 150));
            jsonResponse(['error' => $err], 500);
        }

        // Tandai status di Localhost menjadi PENDING_APPROVAL
        if (!empty($validInvoices)) {
            $phVal = implode(',', array_fill(0, count($validInvoices), '?'));
            $stmtUpd = $pdo->prepare("
                UPDATE return_sessions 
                SET accounting_status = 'PENDING_APPROVAL', 
                    accounting_synced_at = NOW() 
                WHERE invoice_number IN ($phVal)
            ");
            $stmtUpd->execute($validInvoices);
        }

        jsonResponse([
            'success' => true,
            'count'   => count($validInvoices),
            'message' => count($validInvoices) . " paket klaim JNT berhasil dikirim ke portal Accounting di web InfinityFree!"
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal mengirim data klaim ke accounting: ' . $e->getMessage()], 500);
    }
}

// =========================================================================
// ACTION 2: PULL_FROM_CLOUD (Tarik status approval Accounting ke Localhost)
// =========================================================================
if ($action === 'pull_from_cloud') {
    try {
        $targetUrl = $cloudBaseUrl . '/api/sync_claim_approval.php?action=get_approved_for_local&key=' . urlencode($secretKey);
        $cloudResponse = requestInfinityFreeApi($targetUrl);
        $respJson = json_decode($cloudResponse, true);

        if (!$respJson || !isset($respJson['success']) || !$respJson['success']) {
            $err = $respJson['error'] ?? ('Gagal menghubungi server InfinityFree: ' . substr(strip_tags($cloudResponse), 0, 150));
            jsonResponse(['error' => $err], 500);
        }

        $items = $respJson['items'] ?? [];
        $approvedCount = 0;
        $rejectedCount = 0;
        $syncedInvoices = [];

        $stmtUpdateSession = $pdo->prepare("
            UPDATE return_sessions 
            SET accounting_status = ?,
                accounting_approved_by = ?,
                accounting_approved_at = ?,
                accounting_esign = ?,
                accounting_notes = ?,
                accounting_synced_at = NOW(),
                claim_status = CASE WHEN ? = 'APPROVED' THEN 'PROCESS' ELSE claim_status END,
                claim_updated_at = CASE WHEN ? = 'APPROVED' THEN NOW() ELSE claim_updated_at END
            WHERE invoice_number = ?
        ");

        foreach ($items as $it) {
            $inv    = trim($it['invoice_number'] ?? '');
            $status = strtoupper(trim($it['status'] ?? ''));
            if (empty($inv) || !in_array($status, ['APPROVED', 'REJECTED'])) continue;

            $stmtUpdateSession->execute([
                $status,
                $it['approved_by'] ?? 'Accounting',
                $it['approved_at'] ?? date('Y-m-d H:i:s'),
                $it['esign_token'] ?? null,
                $it['notes'] ?? null,
                $status,
                $status,
                $inv
            ]);

            if ($status === 'APPROVED') $approvedCount++;
            if ($status === 'REJECTED') $rejectedCount++;
            $syncedInvoices[] = $inv;
        }

        // Tandai kembali ke cloud bahwa Localhost sudah selesai menarik status
        if (!empty($syncedInvoices)) {
            $ackUrl = $cloudBaseUrl . '/api/sync_claim_approval.php?action=mark_synced_to_local&key=' . urlencode($secretKey);
            requestInfinityFreeApi($ackUrl, ['invoices' => $syncedInvoices]);
        }

        jsonResponse([
            'success'        => true,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'total_synced'   => count($syncedInvoices),
            'message'        => "Sinkronisasi berhasil! {$approvedCount} klaim JNT disetujui (Approved) dan {$rejectedCount} ditolak oleh Accounting."
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menarik status approval dari cloud: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Action tidak didukung'], 400);
