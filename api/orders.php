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

        $price = (float)($order['total_amount'] > 0 ? $order['total_amount'] : ($order['package_price'] > 0 ? $order['package_price'] : $order['original_price']));

        $orderObj = array_merge($order, [
            'platform'               => $order['commerce_platform'] ?? '-',
            'sku'                    => $order['seller_sku'] ?? '-',
            'total_items'            => (int)($order['total_qty'] ?? 1),
            'order_date'             => !empty($order['order_created_at']) ? date('d M Y H:i', strtotime($order['order_created_at'])) : '-',
            'items_detail'           => $items ?: [],
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
                       total_qty, package_price, original_price, shipping_fee,
                       (COALESCE(seller_discount, 0) + COALESCE(platform_discount, 0)) AS total_discount,
                       seller_discount, platform_discount, total_amount, 
                       customer_name, order_created_at, synced_at, order_items_json,
                       (CASE WHEN order_items_json IS NOT NULL AND order_items_json != '' THEN 1 ELSE 0 END) AS has_sku_details
                FROM ocs_orders 
                {$whereSql} 
                ORDER BY order_created_at DESC, id DESC 
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format angka dan tanggal untuk respons ringkas & kompatibel
        foreach ($orders as &$ord) {
            $price = (float)($ord['total_amount'] > 0 ? $ord['total_amount'] : ($ord['package_price'] > 0 ? $ord['package_price'] : $ord['original_price']));
            $ord['platform']               = $ord['commerce_platform'] ?? '-';
            $ord['sku']                    = $ord['seller_sku'] ?? '-';
            $ord['total_items']            = (int)($ord['total_qty'] ?? 1);
            $ord['total_amount_fmt']       = 'Rp ' . number_format($price, 0, ',', '.');
            $ord['total_claim_amount']     = $price;
            $ord['total_claim_amount_fmt'] = 'Rp ' . number_format($price, 0, ',', '.');
            $ord['shipping_fee_fmt']       = 'Rp ' . number_format((float)($ord['shipping_fee'] ?? 0), 0, ',', '.');
            $ord['order_date_fmt']         = !empty($ord['order_created_at']) ? date('d M Y H:i', strtotime($ord['order_created_at'])) : '-';
            $ord['order_date']             = $ord['order_date_fmt'];

            if (!empty($ord['order_items_json'])) {
                $ord['items_detail'] = json_decode($ord['order_items_json'], true) ?: [];
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
