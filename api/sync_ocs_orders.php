<?php
/**
 * api/sync_ocs_orders.php
 * Sinkronisasi Semua Data Orders dari OCS IEG System (https://ocs.iegsystem.id/)
 * Lengkap dengan: No Invoice, No Resi, SKU & Items, Biaya-biaya, Total Klaim, dan Customer.
 * 
 * Fitur:
 * - Stream-upsert per batch 100 order langsung ke MySQL (hemat memori & aman dari timeout)
 * - Ekstraksi paralel detail SKU & biaya klaim via curl_multi
 * - Auto-fallback on-demand enrichment saat di-lookup di scanner/klaim
 * - Kompatibel dengan CLI dan Web API
 * 
 * Penggunaan:
 * 1. CLI Hari Kemarin:  php api/sync_ocs_orders.php --date=yesterday
 * 2. CLI Tanggal Spesifik: php api/sync_ocs_orders.php --date=2026-09-30
 * 3. CLI Dengan Limit:    php api/sync_ocs_orders.php --date=yesterday --limit=500
 * 4. Web Browser / AJAX:  GET /api/sync_ocs_orders.php?date=yesterday
 */

set_time_limit(1800); // 30 menit
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

function writeOcsSyncLog($msg) {
    $logDir = __DIR__ . '/../uploads/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logDir . '/ocs_sync.log', $line, FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}

// -------------------------------------------------------------
// 1. PARSING PARAMETER
// -------------------------------------------------------------
$params = [];
if ($isCli) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--') === 0) {
            $parts = explode('=', substr($arg, 2), 2);
            $params[$parts[0]] = $parts[1] ?? true;
        }
    }
} else {
    $params = array_merge($_GET, $_POST);
}

$dateParam      = trim($params['date'] ?? 'yesterday');
$startParam     = trim($params['start'] ?? $params['start_date'] ?? '');
$endParam       = trim($params['end'] ?? $params['end_date'] ?? '');
$limitParam     = isset($params['limit']) ? (int)$params['limit'] : 0; // 0 = semua
$dryRun         = !empty($params['dry_run']);
$fetchDetails   = !isset($params['details']) || $params['details'] === '1' || $params['details'] === 'true' || $params['details'] === 'auto';
$detailsLimit   = isset($params['details_limit']) ? (int)$params['details_limit'] : 300; // default perkaya 300 detail per run (atau 0 untuk semua)

date_default_timezone_set('Asia/Jakarta');

if (!empty($startParam) && !empty($endParam)) {
    $startWib = date('Y-m-d H:i:s', strtotime($startParam));
    $endWib   = date('Y-m-d H:i:s', strtotime($endParam));
    $targetDateLabel = date('Y-m-d', strtotime($startWib)) . ' s/d ' . date('Y-m-d', strtotime($endWib));
} elseif ($dateParam === 'today') {
    $todayStr = date('Y-m-d');
    $startWib = "{$todayStr} 00:00:00";
    $endWib   = "{$todayStr} 23:59:59";
    $targetDateLabel = $todayStr;
} else {
    if ($dateParam === 'yesterday') {
        $targetDayStr = date('Y-m-d', strtotime('-1 day'));
    } else {
        $targetDayStr = date('Y-m-d', strtotime($dateParam));
    }
    // Dari jam 00:00:00 sampai 23:59:59 WIB
    $startWib = "{$targetDayStr} 00:00:00";
    $endWib   = "{$targetDayStr} 23:59:59";
    $targetDateLabel = $targetDayStr;
}

// Konversi WIB ke UTC untuk query OData CreatedAt OCS
$startUtc = gmdate('Y-m-d\TH:i:s\Z', strtotime($startWib . ' +07:00'));
$endUtc   = gmdate('Y-m-d\TH:i:s\Z', strtotime($endWib . ' +07:00'));

writeOcsSyncLog("=== MEMULAI SYNC ORDERS DARI OCS ===");
writeOcsSyncLog("Rentang WIB: {$startWib} s/d {$endWib} [Target: {$targetDateLabel}]");
writeOcsSyncLog("Rentang UTC: {$startUtc} s/d {$endUtc}");

$ocsBaseUrl = 'https://ocs.iegsystem.id';
$ocsUser    = 'ADMIN';
$ocsPass    = 'ADMIN';
$ocsCompany = 'EJI_WMS';

try {
    // -------------------------------------------------------------
    // 2. LOGIN KE OCS IEG SYSTEM
    // -------------------------------------------------------------
    $chLogin = curl_init("{$ocsBaseUrl}/Auth/Login");
    curl_setopt_array($chLogin, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'username'  => $ocsUser,
            'password'  => $ocsPass,
            'companydb' => $ocsCompany
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 20
    ]);

    $loginRes = curl_exec($chLogin);
    $loginCode = curl_getinfo($chLogin, CURLINFO_HTTP_CODE);
    curl_close($chLogin);

    if ($loginCode !== 200 || !$loginRes) {
        throw new Exception("Gagal login ke OCS (HTTP {$loginCode}).");
    }

    $loginData = json_decode($loginRes, true);
    $token = $loginData['Token'] ?? null;
    if (!$token) {
        throw new Exception("Token otentikasi tidak ditemukan dalam respon login OCS.");
    }

    writeOcsSyncLog("Login OCS berhasil. Token diperoleh.");

    // -------------------------------------------------------------
    // 3. QUERY ODATA DTO_Orders & STREAM UPSERT KE DATABASE
    // -------------------------------------------------------------
    $filterOdata = "CreatedAt ge {$startUtc} and CreatedAt le {$endUtc}";
    $pageSize = 100;
    $skip = 0;
    $totalFetched = 0;
    $totalUpserted = 0;
    $totalWithResi = 0;
    $ordersForDetailEnrichment = [];

    // Statement stream-upsert header order
    $stmtStreamHeader = $pdo->prepare("
        INSERT INTO ocs_orders (
            order_id, tracking_number, platform_id, commerce_platform, 
            shop_name, shipping_provider, status_code, product_name,
            total_qty, order_created_at, raw_payload, is_synced_to_local
        ) VALUES (
            :order_id, :tracking_number, :platform_id, :commerce_platform, 
            :shop_name, :shipping_provider, :status_code, :product_name,
            :total_qty, :order_created_at, :raw_payload, 0
        )
        ON DUPLICATE KEY UPDATE 
            tracking_number   = COALESCE(VALUES(tracking_number), tracking_number),
            platform_id       = COALESCE(VALUES(platform_id), platform_id),
            commerce_platform = COALESCE(VALUES(commerce_platform), commerce_platform),
            shop_name         = COALESCE(VALUES(shop_name), shop_name),
            shipping_provider = COALESCE(VALUES(shipping_provider), shipping_provider),
            status_code       = COALESCE(VALUES(status_code), status_code),
            product_name      = COALESCE(VALUES(product_name), product_name),
            total_qty         = COALESCE(VALUES(total_qty), total_qty),
            order_created_at  = COALESCE(VALUES(order_created_at), order_created_at)
    ");

    while (true) {
        $urlOdata = "{$ocsBaseUrl}/odata/DTO_Orders?\$filter=" . urlencode($filterOdata) . "&\$orderby=CreatedAt%20asc&\$top={$pageSize}&\$skip={$skip}";
        
        $chOrd = curl_init($urlOdata);
        curl_setopt_array($chOrd, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                "Accept: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 35
        ]);

        $resOrd = curl_exec($chOrd);
        $codeOrd = curl_getinfo($chOrd, CURLINFO_HTTP_CODE);
        curl_close($chOrd);

        if ($codeOrd !== 200 || !$resOrd) {
            writeOcsSyncLog("Peringatan: Gagal menarik batch OData (HTTP {$codeOrd}) pada skip={$skip}.");
            break;
        }

        $jsonOrd = json_decode($resOrd, true);
        $items = $jsonOrd['value'] ?? [];
        if (empty($items)) {
            break;
        }

        if (!$dryRun) {
            $pdo->beginTransaction();
            foreach ($items as $it) {
                $oid = trim($it['Id'] ?? '');
                if (!$oid) continue;

                $tn = trim($it['TrackingNumber'] ?? '');
                $pId = (int)($it['PlatformId'] ?? 0);
                $cp = trim($it['CommercePlatform'] ?? '');
                $shop = trim($it['ShopName'] ?? '');
                $sp = trim($it['ShippingProvider'] ?? '');
                $sc = (int)($it['StatusCode'] ?? 0);
                $pName = trim($it['ProductName'] ?? '');
                $qty = (int)($it['TotalQtyOrder'] ?? 1);
                $cAt = trim($it['CreatedAt'] ?? '');

                $stmtStreamHeader->execute([
                    ':order_id'          => $oid,
                    ':tracking_number'   => $tn ?: null,
                    ':platform_id'       => $pId ?: null,
                    ':commerce_platform' => $cp ?: null,
                    ':shop_name'         => $shop ?: null,
                    ':shipping_provider' => $sp ?: null,
                    ':status_code'       => $sc ?: null,
                    ':product_name'      => $pName ?: null,
                    ':total_qty'         => $qty > 0 ? $qty : 1,
                    ':order_created_at'  => $cAt ?: null,
                    ':raw_payload'       => json_encode($it, JSON_UNESCAPED_UNICODE)
                ]);

                $totalUpserted++;
                if (!empty($tn)) {
                    $totalWithResi++;
                }

                // Kumpulkan pesanan yang memiliki resi untuk diperkaya detail finansial & klaim
                if ($fetchDetails && !empty($tn) && ($detailsLimit === 0 || count($ordersForDetailEnrichment) < $detailsLimit)) {
                    $ordersForDetailEnrichment[] = $it;
                }
            }
            $pdo->commit();
        }

        $totalFetched += count($items);
        $skip += $pageSize;

        if ($totalFetched % 500 === 0 || count($items) < $pageSize) {
            writeOcsSyncLog("Progress DTO_Orders: {$totalFetched} orders tersinkron (Dengan resi: {$totalWithResi})...");
        }

        if ($limitParam > 0 && $totalFetched >= $limitParam) {
            break;
        }

        if (count($items) < $pageSize) {
            break; // Data habis
        }
    }

    writeOcsSyncLog("Selesai stream-sync headers: Total {$totalFetched} orders tersimpan ke MySQL.");

    // -------------------------------------------------------------
    // 4. PERKAYA DETAIL SKUS, FINANSIAL & KLAIM (CURL_MULTI PARALEL)
    // -------------------------------------------------------------
    $totalDetailsEnriched = 0;
    $totalClaimAmountSum = 0.00;

    if ($fetchDetails && !empty($ordersForDetailEnrichment) && !$dryRun) {
        $enrichBatchSize = 25;
        $enrichChunks = array_chunk($ordersForDetailEnrichment, $enrichBatchSize);
        writeOcsSyncLog("Memulai pengayaan detail klaim untuk " . count($ordersForDetailEnrichment) . " orders...");

        $stmtEnrichOrder = $pdo->prepare("
            UPDATE ocs_orders SET 
                tracking_number   = COALESCE(:tracking_number, tracking_number),
                seller_sku        = :seller_sku,
                package_price     = :package_price,
                original_price    = :original_price,
                seller_discount   = :seller_discount,
                platform_discount = :platform_discount,
                shipping_fee      = :shipping_fee,
                service_fee       = :service_fee,
                subtotal          = :subtotal,
                total_amount      = :total_amount,
                gmv               = :gmv,
                nmv               = :nmv,
                customer_name     = :customer_name,
                customer_phone    = :customer_phone,
                customer_address  = :customer_address,
                order_items_json  = :order_items_json,
                raw_payload       = :raw_payload
            WHERE order_id = :order_id
        ");

        foreach ($enrichChunks as $chunk) {
            $mh = curl_multi_init();
            $handles = [];

            foreach ($chunk as $ordHeader) {
                $oid = trim($ordHeader['Id'] ?? '');
                if (!$oid) continue;

                $ch = curl_init("{$ocsBaseUrl}/Orders/GetOrderDetail?orderId=" . urlencode($oid));
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: Bearer {$token}",
                        "Accept: application/json"
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT        => 25
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$oid] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.2);
            } while ($running > 0);

            $pdo->beginTransaction();

            foreach ($chunk as $ordHeader) {
                $oid = trim($ordHeader['Id'] ?? '');
                if (!$oid || !isset($handles[$oid])) continue;

                $ch = $handles[$oid];
                $resContent = curl_multi_getcontent($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                $detailJson = json_decode($resContent, true);
                $detailData = $detailJson['Data'] ?? [];
                if (empty($detailData)) continue;

                $payment = $detailData['Payment'] ?? [];
                $origTotalProduct = (float)($payment['OriginalTotalProductPrice'] ?? 0);
                $sellerDiscount   = (float)($payment['SellerDiscount'] ?? 0);
                $platformDiscount = (float)($payment['PlatformDiscount'] ?? 0);
                $origShippingFee  = (float)($payment['OriginalShippingFee'] ?? 0);
                $shippingFee      = (float)($payment['ShippingFee'] ?? $origShippingFee);
                $serviceFee       = (float)($payment['ServiceFee'] ?? 0);
                $subtotal         = (float)($payment['SubTotal'] ?? 0);
                $totalAmount      = (float)($payment['TotalAmount'] ?? 0);

                if ($totalAmount <= 0) {
                    $totalAmount = $subtotal > 0 ? ($subtotal + $shippingFee + $serviceFee) : $origTotalProduct;
                }

                $customer = $detailData['Customer'] ?? [];
                $custName    = trim($customer['Name'] ?? '');
                $custPhone   = trim($customer['PhoneNumber'] ?? '');
                $custAddress = trim($customer['FullAddress'] ?? '');

                $rawItems = $detailData['Details'] ?? [];
                $parsedItems = [];
                $primarySellerSku = null;

                foreach ($rawItems as $it) {
                    $sSku = trim($it['SellerSku'] ?? '');
                    if (!$primarySellerSku && !empty($sSku)) $primarySellerSku = $sSku;

                    $parsedItems[] = [
                        'sku_id'            => trim($it['SkuId'] ?? ''),
                        'seller_sku'        => $sSku,
                        'product_name'      => trim($it['ProductName'] ?? ''),
                        'sku_name'          => trim($it['SkuName'] ?? ''),
                        'qty'               => (int)($it['Qty'] ?? 1),
                        'original_price'    => (float)($it['OriginalPrice'] ?? 0),
                        'sale_price'        => (float)($it['SalePrice'] ?? 0),
                        'seller_discount'   => (float)($it['SellerDiscount'] ?? 0),
                        'platform_discount' => (float)($it['PlatformDiscount'] ?? 0),
                        'subtotal'          => (float)($it['SubTotal'] ?? 0)
                    ];
                }

                $orderItemsJson = !empty($parsedItems) ? json_encode($parsedItems, JSON_UNESCAPED_UNICODE) : null;
                $trackingNumber = trim($detailData['TrackingNumber'] ?? $ordHeader['TrackingNumber'] ?? '');

                $stmtEnrichOrder->execute([
                    ':order_id'          => $oid,
                    ':tracking_number'   => $trackingNumber ?: null,
                    ':seller_sku'        => $primarySellerSku ?: null,
                    ':package_price'     => $totalAmount,
                    ':original_price'    => $origTotalProduct,
                    ':seller_discount'   => $sellerDiscount,
                    ':platform_discount' => $platformDiscount,
                    ':shipping_fee'      => $shippingFee,
                    ':service_fee'       => $serviceFee,
                    ':subtotal'          => $subtotal,
                    ':total_amount'      => $totalAmount,
                    ':gmv'               => $origTotalProduct > 0 ? $origTotalProduct : $totalAmount,
                    ':nmv'               => $totalAmount,
                    ':customer_name'     => $custName ?: null,
                    ':customer_phone'    => $custPhone ?: null,
                    ':customer_address'  => $custAddress ?: null,
                    ':order_items_json'  => $orderItemsJson,
                    ':raw_payload'       => json_encode($detailData, JSON_UNESCAPED_UNICODE)
                ]);

                $totalDetailsEnriched++;
                $totalClaimAmountSum += $totalAmount;
            }

            $pdo->commit();
            curl_multi_close($mh);
        }

        writeOcsSyncLog("Selesai pengayaan detail: {$totalDetailsEnriched} orders lengkap dengan SKU, Biaya & Total Klaim.");
    }

    // Ambil total nilai klaim dari database untuk tanggal tersebut jika ada
    $stmtSum = $pdo->prepare("SELECT SUM(total_amount) FROM ocs_orders WHERE order_created_at >= ? AND order_created_at <= ?");
    $stmtSum->execute([$startUtc, $endUtc]);
    $dbSumClaim = (float)$stmtSum->fetchColumn();
    if ($dbSumClaim > 0) {
        $totalClaimAmountSum = $dbSumClaim;
    }

    writeOcsSyncLog("=== SYNC ORDERS SELESAI ===");
    writeOcsSyncLog("Total Orders: {$totalFetched} | Dengan Resi: {$totalWithResi} | Detail Lengkap: {$totalDetailsEnriched} | Total Nilai Klaim: Rp " . number_format($totalClaimAmountSum, 0, ',', '.'));

    $resp = [
        'success'                => true,
        'message'                => "Sinkronisasi {$totalFetched} orders dari OCS berhasil disimpan.",
        'target_date'            => $targetDateLabel,
        'start_wib'              => $startWib,
        'end_wib'                => $endWib,
        'total_orders_found'     => $totalFetched,
        'total_synced'           => $totalUpserted ?: $totalFetched,
        'total_with_resi'        => $totalWithResi,
        'total_details_enriched' => $totalDetailsEnriched,
        'total_claim_amount'     => $totalClaimAmountSum,
        'total_claim_amount_fmt' => 'Rp ' . number_format($totalClaimAmountSum, 0, ',', '.'),
        'timestamp'              => date('Y-m-d H:i:s')
    ];

    if ($isCli) {
        echo json_encode($resp, JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo json_encode($resp);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errMsg = "Error sinkronisasi OCS Orders: " . $e->getMessage();
    writeOcsSyncLog($errMsg);

    if ($isCli) {
        echo json_encode(['success' => false, 'error' => $errMsg], JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $errMsg]);
    }
}
