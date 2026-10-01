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
            echo json_encode(['success' => false, 'error' => 'Data order tidak ditemukan di database']);
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
    // ACTION: LIST (Data table dengan pagination, search & filter)
    // -------------------------------------------------------------
    if ($action === 'list') {
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $limit      = min(10000, max(1, (int)($_GET['limit'] ?? 25)));
        $offset     = ($page - 1) * $limit;
        $search     = trim($_GET['search'] ?? '');
        $platform   = trim($_GET['platform'] ?? '');
        $dateFilter = trim($_GET['date'] ?? '');
        $startDate  = trim($_GET['start_date'] ?? '');
        $endDate    = trim($_GET['end_date'] ?? '');

        $where = [];
        $params = [];

        // Filter Pencarian kata kunci
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

        // Filter Platform Marketplace
        if ($platform !== '' && $platform !== 'ALL') {
            $where[] = "commerce_platform = :plat";
            $params[':plat'] = $platform;
        }

        // Filter Tanggal
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

        // Hitung total baris
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ocs_orders {$whereSql}");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

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
                ORDER BY order_created_at DESC, id DESC 
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auto-enrich on-demand jika ada order di page ini yang belum memiliki harga & detail SKU
        $missingOrderIds = [];
        foreach ($orders as $oRow) {
            $calc = computeOrderPrice($oRow);
            if ($calc <= 0 && empty($oRow['order_items_json'])) {
                $missingOrderIds[] = $oRow['order_id'];
            }
        }
        if (!empty($missingOrderIds)) {
            $enrichedData = enrichOrdersFromOcs($pdo, $missingOrderIds);
            if (!empty($enrichedData)) {
                foreach ($orders as &$oRef) {
                    $oid = $oRef['order_id'];
                    if (isset($enrichedData[$oid])) {
                        $enr = $enrichedData[$oid];
                        $oRef['tracking_number']  = !empty($enr['tracking_number']) ? $enr['tracking_number'] : $oRef['tracking_number'];
                        $oRef['seller_sku']       = !empty($enr['seller_sku']) ? $enr['seller_sku'] : $oRef['seller_sku'];
                        $oRef['total_amount']     = $enr['total_amount'];
                        $oRef['package_price']    = $enr['package_price'];
                        $oRef['customer_name']    = !empty($enr['customer_name']) ? $enr['customer_name'] : $oRef['customer_name'];
                        $oRef['order_items_json'] = $enr['order_items_json'];
                        $oRef['raw_payload']      = $enr['raw_payload'];
                    }
                }
                unset($oRef);
            }
        }

        // Format angka dan tanggal untuk respons ringkas & kompatibel
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

        // Daftar platform untuk filter dropdown
        $platformsStmt = $pdo->query("SELECT DISTINCT commerce_platform FROM ocs_orders WHERE commerce_platform IS NOT NULL AND commerce_platform != '' ORDER BY commerce_platform ASC");
        $platforms = $platformsStmt->fetchAll(PDO::FETCH_COLUMN);

        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

        echo json_encode([
            'success'       => true,
            'orders'        => $orders,
            'data'          => $orders,
            'pagination'    => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $totalRecords,
                'total_pages' => $totalPages
            ],
            'page'          => $page,
            'limit'         => $limit,
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages,
            'platforms'     => $platforms
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenali']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
