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
            er.operator_name AS receiving_operator,
            rp.scanned_at AS receiving_at,
            rp.sack_number AS receiving_sack,
            rp.photo_path AS receiving_photo,
            rs.id AS session_id,
            rs.invoice_number,
            rs.operator_name AS unboxing_operator,
            rs.created_at AS unboxing_at,
            rs.status AS unboxing_status,
            COALESCE(rs.total_items, 0) AS total_items,
            COALESCE(rs.total_damaged, 0) AS total_damaged,
            rs.video_path AS unboxing_video,
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
        $whereConditions[] = "(
            barcode LIKE :search1 
            OR invoice_number LIKE :search2 
            OR receipt_number LIKE :search3 
            OR courier_name LIKE :search4 
            OR receiving_operator LIKE :search5 
            OR unboxing_operator LIKE :search6 
            OR expedition LIKE :search7
        )";
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

    // Format fields
    foreach ($packages as &$pkg) {
        $pkg['aging_days'] = (int)($pkg['aging_days'] ?? 0);
        $pkg['is_critical_aging'] = ($pkg['aging_days'] > 14);
        $pkg['total_items'] = (int)($pkg['total_items'] ?? 0);
        $pkg['total_damaged'] = (int)($pkg['total_damaged'] ?? 0);
        $pkg['has_video'] = !empty($pkg['unboxing_video']);
        $pkg['has_photo'] = !empty($pkg['receiving_photo']) || !empty($pkg['unboxing_photo']);
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
