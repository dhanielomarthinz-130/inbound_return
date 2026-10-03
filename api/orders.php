<?php
/**
 * api/orders.php
 * Backend API untuk Manajemen Data Orders OCS yang tersinkronisasi di MySQL (ocs_orders)
 * Mendukung: List data table, Filter, Search, Pagination, Detail Order, dan Trigger Sync Aman
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$currentUser = getSessionUser();
checkMaintenanceMode($pdo, $currentUser);
$user = requireLogin(['operator', 'admin', 'superadmin']);

$action = trim($_GET['action'] ?? $_POST['action'] ?? 'list');

/**
 * Helper untuk menghitung total harga order dengan fallback bertingkat
 */
function computeOrderPrice($ord) {
    $price = (float)($ord['total_amount'] ?? 0);
    if ($price <= 0) $price = (float)($ord['package_price'] ?? 0);
    if ($price <= 0) $price = (float)($ord['subtotal'] ?? 0);
    if ($price <= 0) $price = (float)($ord['original_price'] ?? 0);

    // Fallback dari items detail
    if ($price <= 0 && !empty($ord['order_items_json'])) {
        $items = is_array($ord['order_items_json']) ? $ord['order_items_json'] : (json_decode($ord['order_items_json'], true) ?: []);
        $sum = 0;
        foreach ($items as $it) {
            $qty = (int)($it['quantity'] ?? $it['Qty'] ?? 1);
            $p = (float)($it['sale_price'] ?? $it['SalePrice'] ?? $it['price'] ?? $it['original_price'] ?? $it['OriginalPrice'] ?? 0);
            $sum += ($p * $qty);
        }
        if ($sum > 0) $price = $sum;
    }

    // Fallback dari raw_payload
    if ($price <= 0 && !empty($ord['raw_payload'])) {
        $raw = is_array($ord['raw_payload']) ? $ord['raw_payload'] : (json_decode($ord['raw_payload'], true) ?: []);
        if (!empty($raw['Payment'])) {
            $p = $raw['Payment'];
            $price = (float)($p['TotalAmount'] ?? 0);
            if ($price <= 0) $price = (float)($p['SubTotal'] ?? 0);
            if ($price <= 0) $price = (float)($p['OriginalTotalProductPrice'] ?? 0);
        }
        if ($price <= 0 && !empty($raw['Details'])) {
            $sum = 0;
            foreach ($raw['Details'] as $it) {
                $qty = (int)($it['Qty'] ?? $it['quantity'] ?? 1);
                $p = (float)($it['SalePrice'] ?? $it['OriginalPrice'] ?? 0);
                $sum += ($p * $qty);
            }
            if ($sum > 0) $price = $sum;
        }
    }

    return $price;
}

/**
 * Tarik order tunggal secara live menggunakan API Picklist OCS
 * Endpoint resmi: https://ocs.iegsystem.id/Orders/FindOrder?keyword=...
 * Mendukung pencarian instan berdasarkan No. Resi (TrackingNumber), Order ID, maupun Barcode Paket.
 */
function syncSingleOrderFromPicklistFindOrder($pdo, $keyword) {
    $keyword = trim($keyword);
    if (!$keyword) return null;

    $chLogin = curl_init("https://ocs.iegsystem.id/Auth/Login");
    curl_setopt_array($chLogin, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['username' => 'ADMIN', 'password' => 'ADMIN', 'companydb' => 'EJI_WMS']),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 8
    ]);
    $loginRes = curl_exec($chLogin);
    $token = json_decode($loginRes, true)['Token'] ?? null;
    curl_close($chLogin);

    if (!$token) return null;

    $chFind = curl_init("https://ocs.iegsystem.id/Orders/FindOrder?keyword=" . urlencode($keyword));
    curl_setopt_array($chFind, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 10
    ]);
    $findRes = curl_exec($chFind);
    $findHttp = curl_getinfo($chFind, CURLINFO_HTTP_CODE);
    curl_close($chFind);

    if ($findHttp !== 200 || !$findRes) return null;

    $findJson = json_decode($findRes, true);
    if (empty($findJson['Order']['Id'])) return null;

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
    $totalAmount = (float)($fp['TotalAmount'] ?? 0);
    if ($totalAmount <= 0) {
        $totalAmount = $subtotal > 0 ? ($subtotal + $shipFee + $serviceFee) : $origProdPrice;
    }

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

    // Upsert ke tabel ocs_orders
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

    $getStmt = $pdo->prepare("SELECT * FROM ocs_orders WHERE order_id = :oid LIMIT 1");
    $getStmt->execute([':oid' => $fo['Id']]);
    return $getStmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Auto-enrich detail finansial & SKU pesanan secara on-demand via OCS
 */
function enrichOrdersFromOcs($pdo, array $orderIds) {
    if (empty($orderIds)) return [];
    
    // Login OCS
    $chLogin = curl_init("https://ocs.iegsystem.id/Auth/Login");
    curl_setopt_array($chLogin, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['username' => 'ADMIN', 'password' => 'ADMIN', 'companydb' => 'EJI_WMS']),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 8
    ]);
    $loginRes = curl_exec($chLogin);
    $token = json_decode($loginRes, true)['Token'] ?? null;
    curl_close($chLogin);

    if (!$token) return [];

    $mh = curl_multi_init();
    $handles = [];
    foreach ($orderIds as $oid) {
        $ch = curl_init("https://ocs.iegsystem.id/Orders/GetOrderDetail?orderId=" . urlencode($oid));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", "Accept: application/json"],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 8
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$oid] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.1);
    } while ($running > 0);

    $enrichedMap = [];
    $updateStmt = $pdo->prepare("
        UPDATE ocs_orders SET 
            tracking_number   = COALESCE(NULLIF(:tracking_number, ''), tracking_number),
            seller_sku        = :seller_sku,
            package_price     = :package_price,
            original_price    = :original_price,
            seller_discount   = :seller_discount,
            platform_discount = :platform_discount,
            shipping_fee      = :shipping_fee,
            service_fee       = :service_fee,
            subtotal          = :subtotal,
            total_amount      = :total_amount,
            customer_name     = COALESCE(NULLIF(:customer_name, ''), customer_name),
            customer_phone    = :customer_phone,
            customer_address  = :customer_address,
            order_items_json  = :order_items_json,
            raw_payload       = :raw_payload
        WHERE order_id = :order_id
    ");

    $pdo->beginTransaction();
    foreach ($orderIds as $oid) {
        if (!isset($handles[$oid])) continue;
        $ch = $handles[$oid];
        $content = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $json = json_decode($content, true);
        $d = $json['Data'] ?? null;
        if (!$d) continue;

        $payment = $d['Payment'] ?? [];
        $origTotalProduct = (float)($payment['OriginalTotalProductPrice'] ?? 0);
        $sellerDiscount   = (float)($payment['SellerDiscount'] ?? 0);
        $platformDiscount = (float)($payment['PlatformDiscount'] ?? 0);
        $shippingFee      = (float)($payment['ShippingFee'] ?? 0);
        $serviceFee       = (float)($payment['ServiceFee'] ?? 0);
        $subtotal         = (float)($payment['SubTotal'] ?? 0);
        $totalAmount      = (float)($payment['TotalAmount'] ?? 0);

        $customer = $d['Customer'] ?? [];
        $custName = trim($customer['Name'] ?? '');
        $custPhone = trim($customer['PhoneNumber'] ?? '');
        $custAddr = trim($customer['Address'] ?? '');

        $details = $d['Details'] ?? [];
        $itemsFormatted = [];
        $skuList = [];
        $itemSaleSum = 0;
        $itemOrigSum = 0;

        foreach ($details as $dt) {
            $sku = trim($dt['SellerSku'] ?? $dt['SkuId'] ?? '');
            if ($sku) $skuList[] = $sku;
            $qty = (int)($dt['Qty'] ?? 1);
            $sPrice = (float)($dt['SalePrice'] ?? 0);
            $oPrice = (float)($dt['OriginalPrice'] ?? 0);
            $itemSaleSum += ($sPrice * $qty);
            $itemOrigSum += ($oPrice * $qty);

            $itemsFormatted[] = [
                'sku'            => $sku,
                'product_name'   => $dt['ProductName'] ?? '',
                'quantity'       => $qty,
                'price'          => $sPrice > 0 ? $sPrice : $oPrice,
                'original_price' => $oPrice,
                'sale_price'     => $sPrice,
                'image_url'      => $dt['SkuImage'] ?? ''
            ];
        }

        $calcPrice = $totalAmount;
        if ($calcPrice <= 0) $calcPrice = $subtotal;
        if ($calcPrice <= 0 && $itemSaleSum > 0) $calcPrice = $itemSaleSum;
        if ($calcPrice <= 0 && $origTotalProduct > 0) $calcPrice = $origTotalProduct;
        if ($calcPrice <= 0 && $itemOrigSum > 0) $calcPrice = $itemOrigSum;

        $trackingNum = trim($d['TrackingNumber'] ?? '');
        $skuJoined = !empty($skuList) ? implode(', ', array_unique($skuList)) : null;
        $orderItemsJsonStr = !empty($itemsFormatted) ? json_encode($itemsFormatted, JSON_UNESCAPED_UNICODE) : null;
        $rawPayloadStr = json_encode($d, JSON_UNESCAPED_UNICODE);

        try {
            $updateStmt->execute([
                ':order_id'          => $oid,
                ':tracking_number'   => $trackingNum ?: null,
                ':seller_sku'        => $skuJoined,
                ':package_price'     => $calcPrice,
                ':original_price'    => $origTotalProduct > 0 ? $origTotalProduct : $itemOrigSum,
                ':seller_discount'   => $sellerDiscount,
                ':platform_discount' => $platformDiscount,
                ':shipping_fee'      => $shippingFee,
                ':service_fee'       => $serviceFee,
                ':subtotal'          => $subtotal > 0 ? $subtotal : $calcPrice,
                ':total_amount'      => $calcPrice,
                ':customer_name'     => $custName ?: null,
                ':customer_phone'    => $custPhone ?: null,
                ':customer_address'  => $custAddr ?: null,
                ':order_items_json'  => $orderItemsJsonStr,
                ':raw_payload'       => $rawPayloadStr
            ]);
            $enrichedMap[$oid] = [
                'tracking_number'  => $trackingNum,
                'seller_sku'       => $skuJoined,
                'total_amount'     => $calcPrice,
                'package_price'    => $calcPrice,
                'shipping_fee'     => $shippingFee,
                'customer_name'    => $custName,
                'items_detail'     => $itemsFormatted,
                'order_items_json' => $orderItemsJsonStr,
                'raw_payload'      => $rawPayloadStr
            ];
        } catch (Exception $e) {
            // Abaikan error per row
        }
    }
    $pdo->commit();
    return $enrichedMap;
}

try {
    // -------------------------------------------------------------
    // ACTION: STATS (Statistik ringkas di atas data table)
    // -------------------------------------------------------------
    if ($action === 'stats') {
        $stmtTotal = $pdo->query("SELECT COUNT(*) FROM ocs_orders");
        $totalOrders = (int)$stmtTotal->fetchColumn();

        $stmtWithResi = $pdo->query("SELECT COUNT(*) FROM ocs_orders WHERE tracking_number IS NOT NULL AND tracking_number != ''");
        $totalWithResi = (int)$stmtWithResi->fetchColumn();

        $stmtClaim = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM ocs_orders");
        $totalClaim = (float)$stmtClaim->fetchColumn();

        $stmtLastSync = $pdo->query("SELECT MAX(synced_at) FROM ocs_orders");
        $lastSync = $stmtLastSync->fetchColumn();

        $stmtToday = $pdo->query("SELECT COUNT(*) FROM ocs_orders WHERE DATE(synced_at) = CURDATE()");
        $syncedToday = (int)$stmtToday->fetchColumn();

        $statsPayload = [
            'total_orders'     => $totalOrders,
            'total_with_resi'  => $totalWithResi,
            'total_claim'      => $totalClaim,
            'total_claim_fmt'  => 'Rp ' . number_format($totalClaim, 0, ',', '.'),
            'last_sync'        => $lastSync ?: '-',
            'synced_today'     => $syncedToday
        ];

        echo json_encode([
            'success'          => true,
            'stats'            => $statsPayload,
            'total_orders'     => $totalOrders,
            'total_with_resi'  => $totalWithResi,
            'total_claim'      => $totalClaim,
            'total_claim_fmt'  => 'Rp ' . number_format($totalClaim, 0, ',', '.'),
            'last_sync'        => $lastSync ?: '-',
            'synced_today'     => $syncedToday
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: DETAIL (Rincian lengkap 1 order)
    // -------------------------------------------------------------
    if ($action === 'detail') {
        $id = trim($_GET['id'] ?? $_GET['order_id'] ?? $_POST['id'] ?? $_POST['order_id'] ?? '');
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Parameter ID order wajib diisi']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT * FROM ocs_orders 
            WHERE id = :id OR order_id = :oid OR tracking_number = :resi 
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':oid' => $id, ':resi' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            // Coba live sync otomatis dari OCS Picklist FindOrder!
            $order = syncSingleOrderFromPicklistFindOrder($pdo, $id);
        }

        if (!$order) {
            echo json_encode(['success' => false, 'error' => 'Data order tidak ditemukan di database maupun di OCS Picklist']);
            exit;
        }

        $items = !empty($order['order_items_json']) ? json_decode($order['order_items_json'], true) : [];
        $raw = !empty($order['raw_payload']) ? json_decode($order['raw_payload'], true) : [];

        $price = computeOrderPrice($order);

        $orderObj = array_merge($order, [
            'platform'               => $order['commerce_platform'] ?? '-',
            'sku'                    => $order['seller_sku'] ?? '-',
            'total_items'            => (int)($order['total_qty'] ?? 1),
            'order_date'             => !empty($order['order_created_at']) ? date('d M Y H:i', strtotime($order['order_created_at'])) : '-',
            'items_detail'           => $items ?: [],
            'total_price'            => $price,
            'total_price_fmt'        => 'Rp ' . number_format($price, 0, ',', '.'),
            'total_amount'           => $price,
            'total_amount_fmt'       => 'Rp ' . number_format($price, 0, ',', '.'),
            'original_price_fmt'     => 'Rp ' . number_format((float)($order['original_price'] ?? 0), 0, ',', '.'),
            'shipping_fee_fmt'       => 'Rp ' . number_format((float)($order['shipping_fee'] ?? 0), 0, ',', '.'),
            'total_discount_fmt'     => 'Rp ' . number_format((float)($order['total_discount'] ?? 0), 0, ',', '.'),
            'total_claim_amount'     => $price,
            'total_claim_amount_fmt' => 'Rp ' . number_format($price, 0, ',', '.')
        ]);

        echo json_encode([
            'success' => true,
            'order'   => $orderObj,
            'data'    => $orderObj,
            'items'   => $items ?: [],
            'raw'     => $raw
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: SYNC_ORDER (Sinkronisasi On-Demand 1 Order / No. Resi via Picklist OCS)
    // -------------------------------------------------------------
    if ($action === 'sync_order' || $action === 'sync_single') {
        $keyword = trim($_GET['keyword'] ?? $_GET['resi'] ?? $_GET['order_id'] ?? $_POST['keyword'] ?? $_POST['resi'] ?? $_POST['order_id'] ?? '');
        if (!$keyword) {
            echo json_encode(['success' => false, 'error' => 'Nomor resi atau Order ID wajib diisi']);
            exit;
        }

        $order = syncSingleOrderFromPicklistFindOrder($pdo, $keyword);
        if (!$order) {
            echo json_encode(['success' => false, 'error' => "Order dengan kata kunci '{$keyword}' tidak ditemukan di Picklist OCS"]);
            exit;
        }

        $items = !empty($order['order_items_json']) ? json_decode($order['order_items_json'], true) : [];
        $price = computeOrderPrice($order);

        $orderObj = array_merge($order, [
            'platform'               => $order['commerce_platform'] ?? '-',
            'sku'                    => $order['seller_sku'] ?? '-',
            'total_items'            => (int)($order['total_qty'] ?? 1),
            'order_date'             => !empty($order['order_created_at']) ? date('d M Y H:i', strtotime($order['order_created_at'])) : '-',
            'items_detail'           => $items ?: [],
            'total_price'            => $price,
            'total_price_fmt'        => 'Rp ' . number_format($price, 0, ',', '.'),
            'total_amount'           => $price,
            'total_amount_fmt'       => 'Rp ' . number_format($price, 0, ',', '.'),
            'original_price_fmt'     => 'Rp ' . number_format((float)($order['original_price'] ?? 0), 0, ',', '.'),
            'shipping_fee_fmt'       => 'Rp ' . number_format((float)($order['shipping_fee'] ?? 0), 0, ',', '.'),
            'total_discount_fmt'     => 'Rp ' . number_format((float)($order['total_discount'] ?? 0), 0, ',', '.'),
            'total_claim_amount'     => $price,
            'total_claim_amount_fmt' => 'Rp ' . number_format($price, 0, ',', '.')
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Order {$order['order_id']} (Resi: " . ($order['tracking_number'] ?: '-') . ") berhasil disinkronisasi dari Picklist OCS!",
            'order'   => $orderObj,
            'data'    => $orderObj,
            'items'   => $items ?: []
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: FILTER_OPTIONS (Pilihan filter unik untuk dropdown UI)
    // -------------------------------------------------------------
    if ($action === 'filter_options') {
        $platforms = $pdo->query("SELECT DISTINCT commerce_platform FROM ocs_orders WHERE commerce_platform IS NOT NULL AND commerce_platform != '' ORDER BY commerce_platform ASC")->fetchAll(PDO::FETCH_COLUMN);
        $shops     = $pdo->query("SELECT DISTINCT shop_name FROM ocs_orders WHERE shop_name IS NOT NULL AND shop_name != '' ORDER BY shop_name ASC")->fetchAll(PDO::FETCH_COLUMN);
        $shipping  = $pdo->query("SELECT DISTINCT shipping_provider FROM ocs_orders WHERE shipping_provider IS NOT NULL AND shipping_provider != '' ORDER BY shipping_provider ASC")->fetchAll(PDO::FETCH_COLUMN);
        $statuses  = $pdo->query("SELECT DISTINCT status_name FROM ocs_orders WHERE status_name IS NOT NULL AND status_name != '' ORDER BY status_name ASC")->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success'            => true,
            'platforms'          => $platforms,
            'shops'              => $shops,
            'shipping_providers' => $shipping,
            'statuses'           => $statuses
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: LIST (Data table dengan pagination, search & multi-filter)
    // -------------------------------------------------------------
    if ($action === 'list') {
        $page        = max(1, (int)($_GET['page'] ?? 1));
        $limitRaw    = $_GET['limit'] ?? 25;
        if (strtolower((string)$limitRaw) === 'all' || (int)$limitRaw >= 50000) {
            $limit   = 50000;
        } else {
            $limit   = min(50000, max(1, (int)$limitRaw));
        }
        $offset      = ($page - 1) * $limit;
        $search      = trim($_GET['search'] ?? '');
        $platform    = trim($_GET['platform'] ?? '');
        $shop        = trim($_GET['shop'] ?? '');
        $shipping    = trim($_GET['shipping'] ?? '');
        $status      = trim($_GET['status'] ?? '');
        $resiStatus  = trim($_GET['resi_status'] ?? '');
        $claimStatus = trim($_GET['claim_status'] ?? '');
        $dateFilter  = trim($_GET['date'] ?? '');
        $startDate   = trim($_GET['start_date'] ?? '');
        $endDate     = trim($_GET['end_date'] ?? '');
        $sort        = trim($_GET['sort'] ?? 'date_desc');

        $where = [];
        $params = [];

        // 1. Filter Pencarian kata kunci
        if ($search !== '') {
            $where[] = "(
                order_id LIKE :s1 
                OR tracking_number LIKE :s2 
                OR shop_name LIKE :s3 
                OR product_name LIKE :s4 
                OR seller_sku LIKE :s5 
                OR customer_name LIKE :s6
                OR shipping_provider LIKE :s7
            )";
            $sWild = "%{$search}%";
            $params[':s1'] = $sWild;
            $params[':s2'] = $sWild;
            $params[':s3'] = $sWild;
            $params[':s4'] = $sWild;
            $params[':s5'] = $sWild;
            $params[':s6'] = $sWild;
            $params[':s7'] = $sWild;
        }

        // 2. Filter Platform Marketplace
        if ($platform !== '' && $platform !== 'ALL') {
            $where[] = "commerce_platform = :plat";
            $params[':plat'] = $platform;
        }

        // 3. Filter Toko (Shop Name)
        if ($shop !== '' && $shop !== 'ALL') {
            $where[] = "shop_name = :shop";
            $params[':shop'] = $shop;
        }

        // 4. Filter Ekspedisi (Shipping Provider)
        if ($shipping !== '' && $shipping !== 'ALL') {
            $where[] = "shipping_provider LIKE :shipping";
            $params[':shipping'] = "%{$shipping}%";
        }

        // 5. Filter Status Order
        if ($status !== '' && $status !== 'ALL') {
            $where[] = "(status_name = :status OR status_code = :status)";
            $params[':status'] = $status;
        }

        // 6. Filter Kelengkapan No. Resi
        if ($resiStatus === 'with_resi') {
            $where[] = "(tracking_number IS NOT NULL AND tracking_number != '')";
        } elseif ($resiStatus === 'no_resi') {
            $where[] = "(tracking_number IS NULL OR tracking_number = '')";
        }

        // 7. Filter Nilai Klaim
        if ($claimStatus === 'has_claim') {
            $where[] = "total_amount > 0";
        } elseif ($claimStatus === 'zero_claim') {
            $where[] = "(total_amount <= 0 OR total_amount IS NULL)";
        }

        // 8. Filter Tanggal Order
        if ($dateFilter === 'today') {
            $where[] = "DATE(order_created_at) = CURDATE()";
        } elseif ($dateFilter === 'yesterday') {
            $where[] = "DATE(order_created_at) = SUBDATE(CURDATE(), 1)";
        } elseif ($dateFilter === 'last7') {
            $where[] = "order_created_at >= SUBDATE(CURDATE(), 7)";
        } elseif ($dateFilter === 'last30') {
            $where[] = "order_created_at >= SUBDATE(CURDATE(), 30)";
        } elseif (!empty($startDate) && !empty($endDate)) {
            $where[] = "DATE(order_created_at) >= :sd AND DATE(order_created_at) <= :ed";
            $params[':sd'] = $startDate;
            $params[':ed'] = $endDate;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Hitung total baris sesuai filter murni dari database server lokal ocs_orders
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ocs_orders {$whereSql}");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        // Tentukan Urutan (Sort)
        switch ($sort) {
            case 'date_asc':
                $orderBySql = "order_created_at ASC, id ASC";
                break;
            case 'price_desc':
                $orderBySql = "total_amount DESC, id DESC";
                break;
            case 'price_asc':
                $orderBySql = "total_amount ASC, id ASC";
                break;
            default:
                $orderBySql = "order_created_at DESC, id DESC";
                break;
        }

        // Ambil data halaman aktif
        $sql = "SELECT id, order_id, tracking_number, commerce_platform, shop_name, 
                       shipping_provider, status_code, status_name, product_name, seller_sku, 
                       total_qty, package_price, original_price, shipping_fee, subtotal,
                       (COALESCE(seller_discount, 0) + COALESCE(platform_discount, 0)) AS total_discount,
                       seller_discount, platform_discount, total_amount, 
                       customer_name, order_created_at, synced_at, order_items_json, raw_payload,
                       (CASE WHEN order_items_json IS NOT NULL AND order_items_json != '' THEN 1 ELSE 0 END) AS has_sku_details
                FROM ocs_orders 
                {$whereSql} 
                ORDER BY {$orderBySql} 
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format angka dan tanggal untuk respons ringkas & kompatibel langsung dari database lokal ocs_orders
        foreach ($orders as &$ord) {
            $price = computeOrderPrice($ord);
            $ord['platform']               = $ord['commerce_platform'] ?? '-';
            $ord['sku']                    = $ord['seller_sku'] ?? '-';
            $ord['total_items']            = (int)($ord['total_qty'] ?? 1);
            $ord['total_price']            = $price;
            $ord['total_price_fmt']        = 'Rp ' . number_format($price, 0, ',', '.');
            $ord['total_amount']           = $price;
            $ord['total_amount_fmt']       = 'Rp ' . number_format($price, 0, ',', '.');
            $ord['total_claim_amount']     = $price;
            $ord['total_claim_amount_fmt'] = 'Rp ' . number_format($price, 0, ',', '.');
            $ord['shipping_fee_fmt']       = 'Rp ' . number_format((float)($ord['shipping_fee'] ?? 0), 0, ',', '.');
            $ord['order_date_fmt']         = !empty($ord['order_created_at']) ? date('d M Y H:i', strtotime($ord['order_created_at'])) : '-';
            $ord['order_date']             = $ord['order_date_fmt'];

            if (!empty($ord['order_items_json'])) {
                $ord['items_detail'] = is_array($ord['order_items_json']) ? $ord['order_items_json'] : (json_decode($ord['order_items_json'], true) ?: []);
            } else {
                $ord['items_detail'] = [];
            }
        }
        unset($ord);

        // Ambil daftar filter dinamis dari database untuk melengkapi dropdown
        $platforms = $pdo->query("SELECT DISTINCT commerce_platform FROM ocs_orders WHERE commerce_platform IS NOT NULL AND commerce_platform != '' ORDER BY commerce_platform ASC")->fetchAll(PDO::FETCH_COLUMN);
        $shops     = $pdo->query("SELECT DISTINCT shop_name FROM ocs_orders WHERE shop_name IS NOT NULL AND shop_name != '' ORDER BY shop_name ASC")->fetchAll(PDO::FETCH_COLUMN);
        $shipping  = $pdo->query("SELECT DISTINCT shipping_provider FROM ocs_orders WHERE shipping_provider IS NOT NULL AND shipping_provider != '' ORDER BY shipping_provider ASC")->fetchAll(PDO::FETCH_COLUMN);
        $statuses  = $pdo->query("SELECT DISTINCT status_name FROM ocs_orders WHERE status_name IS NOT NULL AND status_name != '' ORDER BY status_name ASC")->fetchAll(PDO::FETCH_COLUMN);

        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

        echo json_encode([
            'success'            => true,
            'orders'             => $orders,
            'data'               => $orders,
            'pagination'         => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $totalRecords,
                'total_pages' => $totalPages
            ],
            'page'               => $page,
            'limit'              => $limit,
            'total_records'      => $totalRecords,
            'total_pages'        => $totalPages,
            'platforms'          => $platforms,
            'shops'              => $shops,
            'shipping_providers' => $shipping,
            'statuses'           => $statuses
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenali']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
