<?php
/**
 * api/admin/aging_return.php
 * Endpoint API untuk Menu Baru: Aging Return
 * Menampilkan Aging Paket dari Receiving sampai Unboxing (berapa hari).
 * Jika belum di-unboxing, menampilkan status BELUM_UNBOXING dan aging dihitung dari receiving sampai hari ini (Today).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config.php';

$user = getSessionUser();
if (!$user) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

$rawRole = strtolower(trim(str_replace([' ', '_', '-'], '', $user['role'] ?? '')));
$allowedRoles = ['superadmin', 'admin', 'management', 'operatorreporting', 'operator_reporting', 'customerservice', 'customer_service', 'accounting'];
if (!in_array($rawRole, $allowedRoles, true)) {
    jsonResponse(['success' => false, 'error' => 'Akses ditolak.'], 403);
}

try {
    $search     = trim($_GET['search'] ?? '');
    $status     = strtoupper(trim($_GET['status'] ?? 'ALL'));
    $expedition = trim($_GET['expedition'] ?? '');
    $agingFlt   = strtoupper(trim($_GET['aging_filter'] ?? 'ALL'));
    $dateFilter = trim($_GET['date'] ?? '');
    $sort       = strtolower(trim($_GET['sort'] ?? 'fifo'));
    $limit      = min(500, max(20, (int)($_GET['limit'] ?? 200)));

    // CTE / Subquery data paket terpadu (Receiving -> Unboxing)
    // 1. Data paket yang tercatat di serah terima receiving (reception_packages)
    // 2. Ditambah data unboxing yang langsung masuk tanpa scan receiving
    $baseSql = "
        SELECT 
            rp.package_barcode AS barcode,
            COALESCE(NULLIF(rs.expedition, ''), NULLIF(er.expedition, ''), 'Lainnya') AS expedition,
            er.receipt_number,
            er.courier_name,
            er.courier_photo,
            er.operator_name AS receiving_operator,
            rp.scanned_at AS receiving_at,
            rp.sack_number AS receiving_sack,
            COALESCE(NULLIF(rp.photo_path, ''), er.photo_path) AS receiving_photo,
            rs.id AS session_id,
            rs.invoice_number,
            rs.operator_name AS unboxing_operator,
            rs.created_at AS unboxing_at,
            rs.status AS unboxing_status,
            COALESCE(rs.total_items, 0) AS total_items,
            COALESCE(rs.total_damaged, 0) AS total_damaged,
            rs.video_path AS unboxing_video,
            rs.package_photo AS unboxing_package_photo,
            rs.product_photo AS unboxing_product_photo,
            rs.photos AS unboxing_photos,
            rs.notes AS unboxing_notes,
            CASE 
                WHEN rs.id IS NOT NULL THEN 'SUDAH_UNBOXING'
                ELSE 'BELUM_UNBOXING'
            END AS status_paket,
            CASE 
                WHEN rs.id IS NOT NULL AND rp.scanned_at IS NOT NULL 
                    THEN GREATEST(0, DATEDIFF(rs.created_at, rp.scanned_at))
                WHEN rs.id IS NULL AND rp.scanned_at IS NOT NULL 
                    THEN GREATEST(0, DATEDIFF(NOW(), rp.scanned_at))
                ELSE 0
            END AS aging_days
        FROM (
            SELECT package_barcode, MIN(scanned_at) as scanned_at, MIN(reception_id) as reception_id, MIN(sack_number) as sack_number, MIN(photo_path) as photo_path
            FROM reception_packages
            GROUP BY package_barcode
        ) rp
        JOIN expedition_receptions er ON er.id = rp.reception_id
        LEFT JOIN (
            SELECT rs1.* 
            FROM return_sessions rs1
            INNER JOIN (
                SELECT invoice_number, MAX(id) as max_id 
                FROM return_sessions 
                GROUP BY invoice_number
            ) rs2 ON rs1.id = rs2.max_id
        ) rs ON rs.invoice_number = rp.package_barcode

        UNION ALL

        SELECT 
            rs.invoice_number AS barcode,
            COALESCE(NULLIF(rs.expedition, ''), 'Lainnya') AS expedition,
            NULL AS receipt_number,
            NULL AS courier_name,
            NULL AS courier_photo,
            NULL AS receiving_operator,
            NULL AS receiving_at,
            NULL AS receiving_sack,
            NULL AS receiving_photo,
            rs.id AS session_id,
            rs.invoice_number,
            rs.operator_name AS unboxing_operator,
            rs.created_at AS unboxing_at,
            rs.status AS unboxing_status,
            COALESCE(rs.total_items, 0) AS total_items,
            COALESCE(rs.total_damaged, 0) AS total_damaged,
            rs.video_path AS unboxing_video,
            rs.package_photo AS unboxing_package_photo,
            rs.product_photo AS unboxing_product_photo,
            rs.photos AS unboxing_photos,
            rs.notes AS unboxing_notes,
            'SUDAH_UNBOXING' AS status_paket,
            0 AS aging_days
        FROM return_sessions rs
        LEFT JOIN reception_packages rp ON rp.package_barcode = rs.invoice_number
        WHERE rp.id IS NULL
    ";

    // Bangun filter WHERE
    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        // Cek apakah kata kunci cocok dengan nomor order di ocs_orders
        $matchingResis = [];
        try {
            $stmtSrchOcs = $pdo->prepare("
                SELECT tracking_number, order_id 
                FROM ocs_orders 
                WHERE order_id LIKE :s OR tracking_number LIKE :s 
                LIMIT 50
            ");
            $stmtSrchOcs->execute([':s' => '%' . $search . '%']);
            while ($rOcs = $stmtSrchOcs->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($rOcs['tracking_number'])) $matchingResis[] = $rOcs['tracking_number'];
                if (!empty($rOcs['order_id'])) $matchingResis[] = $rOcs['order_id'];
            }
        } catch (Exception $e) {}

        if (!empty($matchingResis)) {
            $matchingResis = array_unique($matchingResis);
            $inPlaceholders = [];
            foreach ($matchingResis as $idx => $mResi) {
                $pName = ":mResi{$idx}";
                $inPlaceholders[] = $pName;
                $params[$pName] = $mResi;
            }
            $whereConditions[] = "(
                barcode LIKE :search1 
                OR invoice_number LIKE :search2 
                OR receipt_number LIKE :search3 
                OR courier_name LIKE :search4 
                OR receiving_operator LIKE :search5 
                OR unboxing_operator LIKE :search6 
                OR expedition LIKE :search7
                OR barcode IN (" . implode(',', $inPlaceholders) . ")
                OR invoice_number IN (" . implode(',', $inPlaceholders) . ")
            )";
        } else {
            $whereConditions[] = "(
                barcode LIKE :search1 
                OR invoice_number LIKE :search2 
                OR receipt_number LIKE :search3 
                OR courier_name LIKE :search4 
                OR receiving_operator LIKE :search5 
                OR unboxing_operator LIKE :search6 
                OR expedition LIKE :search7
            )";
        }
        $sTerm = '%' . $search . '%';
        for ($i = 1; $i <= 7; $i++) {
            $params[":search{$i}"] = $sTerm;
        }
    }

    if ($status === 'BELUM_UNBOXING') {
        $whereConditions[] = "status_paket = 'BELUM_UNBOXING'";
    } elseif ($status === 'SUDAH_UNBOXING') {
        $whereConditions[] = "status_paket = 'SUDAH_UNBOXING'";
    }

    if (!empty($expedition)) {
        $whereConditions[] = "expedition = :expedition";
        $params[':expedition'] = $expedition;
    }

    if ($agingFlt === 'LE14') {
        $whereConditions[] = "aging_days <= 14";
    } elseif ($agingFlt === 'GT14') {
        $whereConditions[] = "aging_days > 14";
    }

    if (!empty($dateFilter)) {
        if (strpos($dateFilter, ' to ') !== false) {
            $parts = explode(' to ', $dateFilter);
            $startDate = trim($parts[0]) . ' 00:00:00';
            $endDate = trim($parts[1]) . ' 23:59:59';
            $whereConditions[] = "COALESCE(receiving_at, unboxing_at) BETWEEN :startDate AND :endDate";
            $params[':startDate'] = $startDate;
            $params[':endDate'] = $endDate;
        } else {
            $whereConditions[] = "DATE(COALESCE(receiving_at, unboxing_at)) = :singleDate";
            $params[':singleDate'] = $dateFilter;
        }
    }

    $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // Sort order
    $orderBy = " ORDER BY COALESCE(receiving_at, unboxing_at) ASC, barcode ASC "; // Default FIFO
    if ($sort === 'lifo') {
        $orderBy = " ORDER BY COALESCE(receiving_at, unboxing_at) DESC, barcode DESC ";
    } elseif ($sort === 'aging_desc') {
        $orderBy = " ORDER BY aging_days DESC, COALESCE(receiving_at, unboxing_at) ASC ";
    } elseif ($sort === 'aging_asc') {
        $orderBy = " ORDER BY aging_days ASC, COALESCE(receiving_at, unboxing_at) ASC ";
    }

    // 1. Ambil summary metrik dari hasil filter
    $summarySql = "
        SELECT 
            COUNT(*) AS total_packages,
            SUM(CASE WHEN status_paket = 'BELUM_UNBOXING' THEN 1 ELSE 0 END) AS total_belum_unboxing,
            SUM(CASE WHEN status_paket = 'SUDAH_UNBOXING' THEN 1 ELSE 0 END) AS total_sudah_unboxing,
            SUM(CASE WHEN aging_days > 14 THEN 1 ELSE 0 END) AS total_critical_aging,
            SUM(CASE WHEN aging_days <= 14 THEN 1 ELSE 0 END) AS total_safe_aging,
            COALESCE(AVG(aging_days), 0) AS avg_aging_days,
            COALESCE(MAX(aging_days), 0) AS max_aging_days
        FROM ({$baseSql}) AS t {$whereClause}
    ";
    $stmtSummary = $pdo->prepare($summarySql);
    $stmtSummary->execute($params);
    $summary = $stmtSummary->fetch(PDO::FETCH_ASSOC);

    // 2. Ambil list ekspedisi unik untuk filter dropdown
    $stmtExp = $pdo->query("
        SELECT DISTINCT COALESCE(NULLIF(expedition,''), 'Lainnya') as exp_name 
        FROM expedition_receptions 
        WHERE expedition IS NOT NULL AND expedition != ''
        ORDER BY exp_name ASC
    ");
    $availableExpeditions = $stmtExp->fetchAll(PDO::FETCH_COLUMN);

    // 3. Ambil data list paket
    $dataSql = "
        SELECT * FROM ({$baseSql}) AS t 
        {$whereClause} 
        {$orderBy} 
        LIMIT {$limit}
    ";
    $stmtData = $pdo->prepare($dataSql);
    $stmtData->execute($params);
    $packages = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // 4. Batch lookup ocs_orders untuk Order ID & Data Penjualan (Sangat Cepat & Efisien)
    $barcodes = [];
    foreach ($packages as $p) {
        if (!empty($p['barcode'])) $barcodes[$p['barcode']] = true;
        if (!empty($p['invoice_number'])) $barcodes[$p['invoice_number']] = true;
    }
    $barcodeList = array_keys($barcodes);
    $ocsMap = [];
    if (!empty($barcodeList)) {
        try {
            $placeholders = implode(',', array_fill(0, count($barcodeList), '?'));
            $stmtOcs = $pdo->prepare("
                SELECT order_id, tracking_number, commerce_platform, shop_name, customer_name, total_amount, package_price
                FROM ocs_orders
                WHERE tracking_number IN ($placeholders) OR order_id IN ($placeholders)
            ");
            $stmtOcs->execute(array_merge($barcodeList, $barcodeList));
            while ($row = $stmtOcs->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['tracking_number'])) $ocsMap[$row['tracking_number']] = $row;
                if (!empty($row['order_id'])) $ocsMap[$row['order_id']] = $row;
            }
        } catch (Exception $e) {}
    }

    // Format fields & Media Foto Kurir / Foto Paket
    foreach ($packages as &$pkg) {
        $pkg['aging_days'] = (int)($pkg['aging_days'] ?? 0);
        $pkg['is_critical_aging'] = ($pkg['aging_days'] > 14);
        $pkg['total_items'] = (int)($pkg['total_items'] ?? 0);
        $pkg['total_damaged'] = (int)($pkg['total_damaged'] ?? 0);

        // Pasangkan data Order dari OCS
        $matchedOcs = $ocsMap[$pkg['barcode']] ?? ($ocsMap[$pkg['invoice_number']] ?? null);
        if ($matchedOcs) {
            $pkg['order_id'] = $matchedOcs['order_id'] ?? null;
            $pkg['commerce_platform'] = $matchedOcs['commerce_platform'] ?? null;
            $pkg['shop_name'] = $matchedOcs['shop_name'] ?? null;
            $pkg['customer_name'] = $matchedOcs['customer_name'] ?? null;
            $pkg['package_price'] = (float)($matchedOcs['package_price'] ?? ($matchedOcs['total_amount'] ?? 0));
        } else {
            $pkg['order_id'] = null;
            $pkg['commerce_platform'] = null;
            $pkg['shop_name'] = null;
            $pkg['customer_name'] = null;
            $pkg['package_price'] = 0;
        }

        // Kumpulkan Daftar Foto Paket
        $pkgPhotos = [];
        if (!empty($pkg['receiving_photo'])) {
            $pkgPhotos[] = [
                'type'   => 'receiving',
                'title'  => 'Foto Paket Saat Receiving',
                'url'    => $pkg['receiving_photo'],
                'tag'    => 'Receiving Inbound'
            ];
        }
        if (!empty($pkg['unboxing_package_photo'])) {
            $pkgPhotos[] = [
                'type'   => 'unboxing_pkg',
                'title'  => 'Foto Paket Sebelum Unboxing',
                'url'    => $pkg['unboxing_package_photo'],
                'tag'    => 'Meja Unboxing'
            ];
        }
        if (!empty($pkg['unboxing_product_photo'])) {
            $pkgPhotos[] = [
                'type'   => 'product',
                'title'  => 'Foto Produk / Kerusakan',
                'url'    => $pkg['unboxing_product_photo'],
                'tag'    => 'Fisik Produk'
            ];
        }
        if (!empty($pkg['unboxing_photos'])) {
            $dec = json_decode($pkg['unboxing_photos'], true);
            if (is_array($dec)) {
                foreach ($dec as $pItem) {
                    if (is_array($pItem) && !empty($pItem['path'])) {
                        $already = false;
                        foreach ($pkgPhotos as $ex) {
                            if ($ex['url'] === $pItem['path']) { $already = true; break; }
                        }
                        if (!$already) {
                            $pkgPhotos[] = [
                                'type'  => $pItem['type'] ?? 'unboxing',
                                'title' => $pItem['title'] ?? 'Foto Unboxing Tambahan',
                                'url'   => $pItem['path'],
                                'tag'   => 'Unboxing'
                            ];
                        }
                    }
                }
            }
        }
        $pkg['package_photos'] = $pkgPhotos;
        $pkg['has_courier_photo'] = !empty($pkg['courier_photo']);
        $pkg['has_package_photo'] = !empty($pkgPhotos);
        $pkg['has_video'] = !empty($pkg['unboxing_video']);
        $pkg['has_photo'] = $pkg['has_package_photo'] || $pkg['has_courier_photo'];
    }
    unset($pkg);

    jsonResponse([
        'success'              => true,
        'summary'              => [
            'total_packages'       => (int)($summary['total_packages'] ?? 0),
            'total_belum_unboxing' => (int)($summary['total_belum_unboxing'] ?? 0),
            'total_sudah_unboxing' => (int)($summary['total_sudah_unboxing'] ?? 0),
            'total_critical_aging' => (int)($summary['total_critical_aging'] ?? 0),
            'total_safe_aging'     => (int)($summary['total_safe_aging'] ?? 0),
            'avg_aging_days'       => round((float)($summary['avg_aging_days'] ?? 0), 1),
            'max_aging_days'       => (int)($summary['max_aging_days'] ?? 0),
        ],
        'expeditions'          => $availableExpeditions,
        'packages'             => $packages,
        'count'                => count($packages),
        'limit'                => $limit
    ]);

} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error'   => 'Gagal memuat data Aging Return: ' . $e->getMessage()
    ], 500);
}
