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

// Output buffering: cegah warning/notice/BOM dari config merusak JSON response
ob_start();

set_time_limit(1800); // 30 menit
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config.php';

if (function_exists('ensureDatabaseSchema')) {
    try {
        ensureDatabaseSchema($pdo);
    } catch (Exception $eSchema) {}
}

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    // Buang semua output yang sudah terlanjur dicetak (warning, BOM, whitespace dari config)
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    // Perpanjang batas waktu Apache/nginx gateway
    header('X-Accel-Buffering: no');
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

$keywordParam   = trim($params['keyword'] ?? $params['resi'] ?? $params['order_id'] ?? $params['q'] ?? '');
$dateParam      = trim($params['date'] ?? 'yesterday');
$startParam     = trim($params['start'] ?? $params['start_date'] ?? '');
$endParam       = trim($params['end'] ?? $params['end_date'] ?? '');
$limitParam     = isset($params['limit']) ? (int)$params['limit'] : 0; // 0 = tanpa batas (ambil semua orders dari rentang tanggal)
$dryRun         = !empty($params['dry_run']);
$fetchDetailsParam = $params['details'] ?? null;
if ($fetchDetailsParam === '0' || $fetchDetailsParam === 'false' || !empty($params['fast'])) {
    $fetchDetails = false;
} else {
    $fetchDetails = true;
}
$detailsLimit = isset($params['details_limit']) ? (int)$params['details_limit'] : 250;

date_default_timezone_set('Asia/Jakarta');

if (!empty($startParam) && !empty($endParam)) {
    $startWib = date('Y-m-d 00:00:00', strtotime($startParam));
    $endWib   = date('Y-m-d 23:59:59', strtotime($endParam));
    $targetDateLabel = date('Y-m-d', strtotime($startWib)) . ' s/d ' . date('Y-m-d', strtotime($endWib));
    $diffDays = (strtotime($endWib) - strtotime($startWib)) / 86400;
    if ($diffDays > 1 && !isset($params['details'])) {
        $fetchDetails = false; // Fast stream headers mode untuk rentang multi-hari (anti gateway timeout)
    }
} elseif ($dateParam === 'month' || $dateParam === 'last_30_days' || $dateParam === '1_month' || $dateParam === '30_days') {
    $todayStr = date('Y-m-d');
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $startWib = "{$thirtyDaysAgo} 00:00:00";
    $endWib   = "{$todayStr} 23:59:59";
    $targetDateLabel = "{$thirtyDaysAgo} s/d {$todayStr} (1 Bulan Terakhir)";
    if (!isset($params['details'])) {
        $fetchDetails = false; // Fast mode untuk 1 bulan (bebas 504 gateway timeout)
    }
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

    // =============================================================
    // SYNC TUNGGAL BERDASARKAN NO. RESI / ORDER ID (TARIKAN PICKLIST OCS)
    // Menggunakan endpoint resmi Picklist OCS: https://ocs.iegsystem.id/Orders/FindOrder?keyword=...
    // =============================================================
    if (!empty($keywordParam)) {
        writeOcsSyncLog("Melakukan sync tunggal via Picklist OCS (FindOrder): '{$keywordParam}'");
        $chFind = curl_init("{$ocsBaseUrl}/Orders/FindOrder?keyword=" . urlencode($keywordParam));
        curl_setopt_array($chFind, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                "Accept: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 12
        ]);
        $findRes = curl_exec($chFind);
        $findHttp = curl_getinfo($chFind, CURLINFO_HTTP_CODE);
        curl_close($chFind);

        if ($findHttp !== 200 || !$findRes) {
            throw new Exception("Gagal menghubungi endpoint FindOrder OCS (HTTP {$findHttp})");
        }

        $findJson = json_decode($findRes, true);
        if (empty($findJson['Order']['Id'])) {
            throw new Exception("Order atau No. Resi '{$keywordParam}' tidak ditemukan di Picklist OCS");
        }

        $fo = $findJson['Order'];
        $fp = $findJson['Payment'] ?? [];
        $fa = $findJson['Address'] ?? [];
        $fSkus = $findJson['Skus'] ?? [];
        $fPick = $findJson['Picklists'] ?? [];

        $origProdPrice = (float)($fp['OriginalTotalProductPrice'] ?? 0);
        $sellerDisc = (float)($fp['SellerDiscount'] ?? 0);
        $platformDisc = (float)($fp['PlatformDiscount'] ?? 0);
        $shipFee = (float)($fp['ShippingFee'] ?? 0);
        $serviceFee = (float)($fp['ServiceFee'] ?? 0);
        $subtotal = (float)($fp['SubTotal'] ?? ($origProdPrice - $sellerDisc));
        // Sesuai permintaan: gunakan persis field Total dari tab Pembayaran OCS (tidak dioverride dengan Total Harga Produk)
        $totalAmount = (float)($fp['TotalAmount'] ?? 0);

        $parsedItems = [];
        $itemNames = [];
        $skuList = [];

        foreach ($fSkus as $det) {
            $pSku = trim($det['SellerSku'] ?? $det['BundleSku'] ?? '');
            $pName = trim($det['ProductName'] ?? '');
            $sName = trim($det['SkuName'] ?? '');
            $pQty = (int)($det['Qty'] ?? 1);
            $sPrice = (float)($det['SalePrice'] ?? 0);
            $oPrice = (float)($det['OriginalPrice'] ?? 0);

            if ($pSku) $skuList[] = $pSku;
            $itemNames[] = ($pSku ? "[{$pSku}] " : "") . $pName . ($sName ? " ({$sName})" : "") . " (x{$pQty})";

            $parsedItems[] = [
                'sku'               => $pSku,
                'seller_sku'        => $pSku,
                'product_name'      => $pName,
                'sku_name'          => $sName,
                'qty'               => $pQty,
                'quantity'          => $pQty,
                'original_price'    => $oPrice,
                'sale_price'        => $sPrice > 0 ? $sPrice : $oPrice,
                'price'             => $sPrice > 0 ? $sPrice : $oPrice,
                'subtotal'          => ($sPrice > 0 ? $sPrice : $oPrice) * $pQty
            ];
        }

        if (empty($parsedItems) && !empty($fPick)) {
            foreach ($fPick as $pIt) {
                $pSku = trim($pIt['ItemCode'] ?? $pIt['BundleCode'] ?? '');
                $pName = trim($pIt['ItemName'] ?? '');
                $pQty = (int)($pIt['QtyOrdered'] ?? 1);
                if ($pSku) $skuList[] = $pSku;
                $itemNames[] = ($pSku ? "[{$pSku}] " : "") . $pName . " (x{$pQty})";
                $parsedItems[] = [
                    'sku'               => $pSku,
                    'seller_sku'        => $pSku,
                    'product_name'      => $pName,
                    'sku_name'          => '',
                    'qty'               => $pQty,
                    'quantity'          => $pQty,
                    'original_price'    => 0,
                    'sale_price'        => 0,
                    'price'             => 0,
                    'subtotal'          => 0
                ];
            }
        }

        $prodText = !empty($itemNames) ? implode(', ', $itemNames) : ($fo['ProductName'] ?? '');
        $skuJoined = !empty($skuList) ? implode(', ', array_unique($skuList)) : null;
        $shippingProvider = trim(($fo['ShippingProvider'] ?? '') . ' ' . ($fo['DeliveryOptionName'] ?? ''));

        // Skema dan Simpan ke database ocs_orders jika database aktif
        if (!empty($pdo)) {
            $hasIsSyncedCol = false;
            try {
                $colsOcs = $pdo->query("SHOW COLUMNS FROM ocs_orders")->fetchAll(PDO::FETCH_COLUMN);
                $hasIsSyncedCol = in_array('is_synced_to_local', $colsOcs);
            } catch (Exception $eCols) {}

            $colSyncPart = $hasIsSyncedCol ? ", is_synced_to_local" : "";
            $valSyncPart = $hasIsSyncedCol ? ", 0" : "";

            $stmtUpsert = $pdo->prepare("
                INSERT INTO ocs_orders (
                    order_id, tracking_number, platform_id, commerce_platform, 
                    shop_name, shipping_provider, status_code, status_name, product_name, seller_sku,
                    total_qty, package_price, original_price, seller_discount, platform_discount,
                    shipping_fee, service_fee, subtotal, total_amount,
                    customer_name, customer_phone, customer_address, order_items_json,
                    order_created_at, raw_payload{$colSyncPart}
                ) VALUES (
                    :order_id, :tracking_number, :platform_id, :commerce_platform, 
                    :shop_name, :shipping_provider, :status_code, :status_name, :product_name, :seller_sku,
                    :total_qty, :package_price, :original_price, :seller_discount, :platform_discount,
                    :shipping_fee, :service_fee, :subtotal, :total_amount,
                    :customer_name, :customer_phone, :customer_address, :order_items_json,
                    :order_created_at, :raw_payload{$valSyncPart}
                )
                ON DUPLICATE KEY UPDATE 
                    tracking_number   = COALESCE(VALUES(tracking_number), tracking_number),
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
                    customer_name     = VALUES(customer_name),
                    customer_phone    = VALUES(customer_phone),
                    customer_address  = VALUES(customer_address),
                    order_items_json  = VALUES(order_items_json),
                    order_created_at  = VALUES(order_created_at),
                    raw_payload       = VALUES(raw_payload)
            ");

            $stmtUpsert->execute([
                ':order_id'          => $fo['Id'],
                ':tracking_number'   => $fo['TrackingNumber'] ?: null,
                ':platform_id'       => $fo['PlatformId'] ?: null,
                ':commerce_platform' => $fo['CommercePlatform'] ?: null,
                ':shop_name'         => $fo['ShopName'] ?: null,
                ':shipping_provider' => $shippingProvider ?: null,
                ':status_code'       => $fo['StatusCode'] ?: null,
                ':status_name'       => $fo['StatusName'] ?: null,
                ':product_name'      => $prodText ?: null,
                ':seller_sku'        => $skuJoined ?: null,
                ':total_qty'         => (int)($fo['TotalQtyOrder'] ?? 1),
                ':package_price'     => $totalAmount,
                ':original_price'    => $origProdPrice,
                ':seller_discount'   => $sellerDisc,
                ':platform_discount' => $platformDisc,
                ':shipping_fee'      => $shipFee,
                ':service_fee'       => $serviceFee,
                ':subtotal'          => $subtotal,
                ':total_amount'      => $totalAmount,
                ':customer_name'     => $fa['Name'] ?? null,
                ':customer_phone'    => $fa['PhoneNumber'] ?? null,
                ':customer_address'  => $fa['FullAddress'] ?? null,
                ':order_items_json'  => json_encode($parsedItems, JSON_UNESCAPED_UNICODE),
                ':order_created_at'  => !empty($fo['CreatedAt']) ? gmdate('Y-m-d H:i:s', strtotime($fo['CreatedAt'])) : date('Y-m-d H:i:s'),
                ':raw_payload'       => json_encode($findJson, JSON_UNESCAPED_UNICODE)
            ]);
            writeOcsSyncLog("Sukses simpan order {$fo['Id']} (Resi: " . ($fo['TrackingNumber'] ?: '-') . ") ke database lokal.");
        } else {
            writeOcsSyncLog("Order {$fo['Id']} (Resi: " . ($fo['TrackingNumber'] ?: '-') . ") berhasil ditarik dari Picklist OCS.");
        }

        writeOcsSyncLog("Sukses sync order {$fo['Id']} (Resi: " . ($fo['TrackingNumber'] ?: '-') . "). Nilai klaim: Rp " . number_format($totalAmount, 0, ',', '.'));

        $resPayload = [
            'success'                => true,
            'source'                 => 'picklist_find_order',
            'matched_by'             => $findJson['MatchedBy'] ?? 'Keyword',
            'order_id'               => $fo['Id'],
            'tracking_number'        => $fo['TrackingNumber'] ?? '',
            'platform'               => $fo['CommercePlatform'] ?? '',
            'shop_name'              => $fo['ShopName'] ?? '',
            'status'                 => $fo['StatusName'] ?? '',
            'total_items'            => (int)($fo['TotalQtyOrder'] ?? 1),
            'skus_count'             => count($parsedItems),
            'total_claim_amount'     => $totalAmount,
            'total_claim_amount_fmt' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
            'total_synced'           => 1,
            'total_with_resi'        => !empty($fo['TrackingNumber']) ? 1 : 0,
            'order'                  => [
                'order_id'           => $fo['Id'],
                'tracking_number'    => $fo['TrackingNumber'] ?? '',
                'commerce_platform'  => $fo['CommercePlatform'] ?? '',
                'shop_name'          => $fo['ShopName'] ?? '',
                'shipping_provider'  => $shippingProvider,
                'status_name'        => $fo['StatusName'] ?? '',
                'product_name'       => $prodText,
                'seller_sku'         => $skuJoined,
                'total_amount'       => $totalAmount,
                'items_detail'       => $parsedItems
            ]
        ];

        echo json_encode($resPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // -------------------------------------------------------------
    // 3. QUERY ODATA DTO_Orders & STREAM UPSERT KE DATABASE
    // -------------------------------------------------------------
    $filterOdata = "CreatedAt ge {$startUtc} and CreatedAt le {$endUtc}";
    $pageSize = 150;
    $skip = 0;
    $totalFetched = 0;
    $totalUpserted = 0;
    $totalWithResi = 0;
    $ordersForDetailEnrichment = [];
    $syncStartTime = microtime(true);
    $maxExecSeconds = 22; // Batas aman ketat agar gateway/proxy Cloudflare/Apache tidak pernah 504 Timeout

    // Pastikan skema ocs_orders up to date
    $hasIsSyncedCol = false;
    try {
        $colsOcs = $pdo->query("SHOW COLUMNS FROM ocs_orders")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_synced_to_local', $colsOcs)) {
            try {
                $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN is_synced_to_local TINYINT(1) DEFAULT 0 AFTER raw_payload");
                $colsOcs[] = 'is_synced_to_local';
            } catch (Exception $eCol) {}
        }
        $hasIsSyncedCol = in_array('is_synced_to_local', $colsOcs);
    } catch (Exception $eOcsCols) {}

    $colSql = "order_id, tracking_number, platform_id, commerce_platform, shop_name, shipping_provider, status_code, product_name, total_qty, order_created_at, raw_payload" . ($hasIsSyncedCol ? ", is_synced_to_local" : "");
    $valSql = ":order_id, :tracking_number, :platform_id, :commerce_platform, :shop_name, :shipping_provider, :status_code, :product_name, :total_qty, :order_created_at, :raw_payload" . ($hasIsSyncedCol ? ", 0" : "");

    // Statement stream-upsert header order
    $stmtStreamHeader = $pdo->prepare("
        INSERT INTO ocs_orders (
            {$colSql}
        ) VALUES (
            {$valSql}
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
        // Safety guard batas waktu gateway proxy
        if ((microtime(true) - $syncStartTime) > $maxExecSeconds) {
            writeOcsSyncLog("Waktu eksekusi mendekati batas aman gateway ({$maxExecSeconds}s). Selesai sementara pada {$totalFetched} orders agar bebas 504.");
            break;
        }

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
            CURLOPT_TIMEOUT        => 15
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

                // Kumpulkan pesanan untuk diperkaya detail finansial & klaim (termasuk yang belum terbit resi)
                if ($fetchDetails && ($detailsLimit === 0 || count($ordersForDetailEnrichment) < $detailsLimit)) {
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
                    CURLOPT_TIMEOUT        => 12
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

                $customer = $detailData['Customer'] ?? [];
                $custName    = trim($customer['Name'] ?? '');
                $custPhone   = trim($customer['PhoneNumber'] ?? '');
                $custAddress = trim($customer['FullAddress'] ?? '');

                $rawItems = $detailData['Details'] ?? [];
                $parsedItems = [];
                $primarySellerSku = null;
                $itemSaleSum = 0;
                $itemOrigSum = 0;

                foreach ($rawItems as $it) {
                    $sSku = trim($it['SellerSku'] ?? '');
                    if (!$primarySellerSku && !empty($sSku)) $primarySellerSku = $sSku;
                    $qty = (int)($it['Qty'] ?? 1);
                    $sPrice = (float)($it['SalePrice'] ?? 0);
                    $oPrice = (float)($it['OriginalPrice'] ?? 0);
                    $itemSaleSum += ($sPrice * $qty);
                    $itemOrigSum += ($oPrice * $qty);

                    $parsedItems[] = [
                        'sku_id'            => trim($it['SkuId'] ?? ''),
                        'seller_sku'        => $sSku,
                        'product_name'      => trim($it['ProductName'] ?? ''),
                        'sku_name'          => trim($it['SkuName'] ?? ''),
                        'qty'               => $qty,
                        'original_price'    => $oPrice,
                        'sale_price'        => $sPrice,
                        'seller_discount'   => (float)($it['SellerDiscount'] ?? 0),
                        'platform_discount' => (float)($it['PlatformDiscount'] ?? 0),
                        'subtotal'          => (float)($it['SubTotal'] ?? 0)
                    ];
                }

                // Sesuai permintaan: gunakan persis field Total dari tab Pembayaran OCS

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
        ob_clean(); // Bersihkan sekali lagi sebelum output JSON final
        echo json_encode($resp);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Exception $eRb) {}
    }
    $errMsg = "Error sinkronisasi OCS Orders: " . $e->getMessage();
    writeOcsSyncLog($errMsg);

    if ($isCli) {
        echo json_encode(['success' => false, 'error' => $errMsg], JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        ob_clean(); // Bersihkan output sebelum kirim error JSON
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $errMsg]);
    }
}
