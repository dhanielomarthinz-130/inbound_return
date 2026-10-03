<?php
require_once __DIR__ . '/../../config.php';
session_write_close(); // Lepas session lock agar request paralel cepat

$date    = trim($_GET['date'] ?? '');
$refresh = isset($_GET['refresh']) && ($_GET['refresh'] == '1' || $_GET['refresh'] === 'true');

// Backend Caching (TTL: 60 detik atau refresh on-demand)
$cacheDir = __DIR__ . '/../../uploads/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}
$cacheKey = md5('metrics_' . $date);
$cacheFile = $cacheDir . '/metrics_' . $cacheKey . '.json';

if (!$refresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 60)) {
    header('Content-Type: application/json; charset=utf-8');
    echo file_get_contents($cacheFile);
    exit;
}

try {
    $params = [];
    $where = "WHERE 1=1";

    $paramsRec = [];
    $whereRec = "WHERE 1=1";

    if (!empty($date)) {
        if (strpos($date, ' to ') !== false) {
            $parts = explode(' to ', $date);
            $startDate = trim($parts[0]);
            $endDate   = trim($parts[1] ?? $parts[0]);
            $where .= " AND s.created_at >= ? AND s.created_at <= ?";
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';

            $whereRec .= " AND r.created_at >= ? AND r.created_at <= ?";
            $paramsRec[] = $startDate . ' 00:00:00';
            $paramsRec[] = $endDate . ' 23:59:59';
        } else {
            $where .= " AND s.created_at >= ? AND s.created_at <= ?";
            $params[] = $date . ' 00:00:00';
            $params[] = $date . ' 23:59:59';

            $whereRec .= " AND r.created_at >= ? AND r.created_at <= ?";
            $paramsRec[] = $date . ' 00:00:00';
            $paramsRec[] = $date . ' 23:59:59';
        }
    } else {
        $where .= " AND s.created_at >= CURDATE() AND s.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        $whereRec .= " AND r.created_at >= CURDATE() AND r.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    }

    // ── 0. Receiving Inbound Summary ──────────────────────────────────────────
    $sqlRecKpi = "
        SELECT
            COUNT(DISTINCT r.id) AS total_receptions,
            COALESCE(SUM(r.total_packages), 0) AS total_received_packages
        FROM expedition_receptions r
        {$whereRec}
    ";
    $stmtRecKpi = $pdo->prepare($sqlRecKpi);
    $stmtRecKpi->execute($paramsRec);
    $recKpi = $stmtRecKpi->fetch();

    // ── 1. KPI Unboxing Summary ──────────────────────────────────────────────
    $sqlKpi = "
        SELECT
            COUNT(DISTINCT s.id)            AS total_invoices,
            COALESCE(SUM(i.qty), 0)         AS total_items,
            COALESCE(SUM(CASE WHEN UPPER(COALESCE(NULLIF(i.type,''), i.condition, 'GOOD')) = 'GOOD' THEN i.qty ELSE 0 END), 0) AS total_good,
            COALESCE(SUM(CASE WHEN UPPER(COALESCE(NULLIF(i.type,''), i.condition, 'GOOD')) != 'GOOD' THEN i.qty ELSE 0 END), 0) AS total_damaged
        FROM return_sessions s
        LEFT JOIN return_items i ON i.session_id = s.id
        {$where}
    ";
    $stmtKpi = $pdo->prepare($sqlKpi);
    $stmtKpi->execute($params);
    $metrics = $stmtKpi->fetch();

    // ── 2. Total Paket per Ekspedisi (Kombinasi Receiving & Unboxing) ───────
    $sqlExpRec = "
        SELECT 
            COALESCE(NULLIF(r.expedition, ''), 'Lainnya') AS expedition,
            COALESCE(SUM(r.total_packages), 0) AS rec_packages,
            COUNT(r.id) AS rec_sessions
        FROM expedition_receptions r
        {$whereRec}
        GROUP BY expedition
    ";
    $stmtExpRec = $pdo->prepare($sqlExpRec);
    $stmtExpRec->execute($paramsRec);
    $expRecRows = $stmtExpRec->fetchAll();

    $sqlExpUnbox = "
        SELECT
            COALESCE(NULLIF(s.expedition, ''), 'Tidak Ada') AS expedition,
            COUNT(DISTINCT s.id)                             AS unbox_packages,
            COALESCE(SUM(i.qty), 0)                          AS total_qty
        FROM return_sessions s
        LEFT JOIN return_items i ON i.session_id = s.id
        {$where}
        GROUP BY expedition
    ";
    $stmtExpUnbox = $pdo->prepare($sqlExpUnbox);
    $stmtExpUnbox->execute($params);
    $expUnboxRows = $stmtExpUnbox->fetchAll();

    $expMap = [];
    foreach ($expRecRows as $er) {
        $name = trim($er['expedition']);
        if (!isset($expMap[$name])) {
            $expMap[$name] = [
                'expedition'         => $name,
                'receiving_packages' => 0,
                'unboxing_packages'  => 0,
                'total_qty'          => 0,
                'total_packages'     => 0
            ];
        }
        $expMap[$name]['receiving_packages'] += (int)$er['rec_packages'];
        $expMap[$name]['total_packages'] += (int)$er['rec_packages'];
    }
    foreach ($expUnboxRows as $eu) {
        $name = trim($eu['expedition']);
        if (!isset($expMap[$name])) {
            $expMap[$name] = [
                'expedition'         => $name,
                'receiving_packages' => 0,
                'unboxing_packages'  => 0,
                'total_qty'          => 0,
                'total_packages'     => 0
            ];
        }
        $expMap[$name]['unboxing_packages'] += (int)$eu['unbox_packages'];
        $expMap[$name]['total_qty'] += (int)$eu['total_qty'];
        if ($expMap[$name]['receiving_packages'] === 0) {
            $expMap[$name]['total_packages'] += (int)$eu['unbox_packages'];
        }
    }

    $byExpeditionCombined = array_values($expMap);
    usort($byExpeditionCombined, function($a, $b) {
        return ($b['receiving_packages'] + $b['unboxing_packages']) <=> ($a['receiving_packages'] + $a['unboxing_packages']);
    });

    // ── 3. Total Kondisi per Ekspedisi ──────────────────────────────────────
    $sqlCond = "
        SELECT
            COALESCE(NULLIF(s.expedition, ''), 'Tidak Ada')    AS expedition,
            UPPER(COALESCE(NULLIF(i.type, ''), i.condition, 'GOOD')) AS condition_code,
            COALESCE(SUM(i.qty), 0)                             AS total_qty,
            COUNT(i.id)                                         AS total_rows
        FROM return_sessions s
        LEFT JOIN return_items i ON i.session_id = s.id
        {$where}
        GROUP BY expedition, condition_code
        ORDER BY expedition ASC, total_qty DESC
    ";
    $stmtCond = $pdo->prepare($sqlCond);
    $stmtCond->execute($params);
    $condRows = $stmtCond->fetchAll();

    // Regroup kondisi per ekspedisi
    $byCondition = [];
    foreach ($condRows as $row) {
        $exp  = $row['expedition'];
        $code = $row['condition_code'];
        if (!isset($byCondition[$exp])) {
            $byCondition[$exp] = [];
        }
        $byCondition[$exp][] = [
            'code'      => $code,
            'total_qty' => (int)$row['total_qty'],
            'total_rows'=> (int)$row['total_rows'],
        ];
    }

    // ── 4. Produktivitas PIC Inbound (Receiving vs Unboxing) ─────────────────
    $sqlPicRec = "
        SELECT
            COALESCE(NULLIF(r.operator_name, ''), 'Gudang 01') AS pic_name,
            COUNT(r.id) AS rec_sessions,
            COALESCE(SUM(r.total_packages), 0) AS rec_packages
        FROM expedition_receptions r
        {$whereRec}
        GROUP BY pic_name
    ";
    $stmtPicRec = $pdo->prepare($sqlPicRec);
    $stmtPicRec->execute($paramsRec);
    $picRecRows = $stmtPicRec->fetchAll();

    $sqlPicUnbox = "
        SELECT
            COALESCE(NULLIF(s.operator_name, ''), 'Gudang 01') AS pic_name,
            COUNT(DISTINCT s.id) AS unbox_packages,
            COALESCE(SUM(i.qty), 0) AS unbox_items
        FROM return_sessions s
        LEFT JOIN return_items i ON i.session_id = s.id
        {$where}
        GROUP BY pic_name
    ";
    $stmtPicUnbox = $pdo->prepare($sqlPicUnbox);
    $stmtPicUnbox->execute($params);
    $picUnboxRows = $stmtPicUnbox->fetchAll();

    $picMap = [];
    foreach ($picRecRows as $pr) {
        $name = trim($pr['pic_name']);
        if (!isset($picMap[$name])) {
            $picMap[$name] = [
                'pic_name'           => $name,
                'receiving_sessions' => 0,
                'receiving_packages' => 0,
                'unboxing_packages'  => 0,
                'unboxing_items'     => 0,
                'total_processed'    => 0
            ];
        }
        $picMap[$name]['receiving_sessions'] += (int)$pr['rec_sessions'];
        $picMap[$name]['receiving_packages'] += (int)$pr['rec_packages'];
        $picMap[$name]['total_processed'] += (int)$pr['rec_packages'];
    }

    foreach ($picUnboxRows as $pu) {
        $name = trim($pu['pic_name']);
        if (!isset($picMap[$name])) {
            $picMap[$name] = [
                'pic_name'           => $name,
                'receiving_sessions' => 0,
                'receiving_packages' => 0,
                'unboxing_packages'  => 0,
                'unboxing_items'     => 0,
                'total_processed'    => 0
            ];
        }
        $picMap[$name]['unboxing_packages'] += (int)$pu['unbox_packages'];
        $picMap[$name]['unboxing_items'] += (int)$pu['unbox_items'];
        $picMap[$name]['total_processed'] += (int)$pu['unbox_packages'];
    }

    $picStats = array_values($picMap);
    usort($picStats, function($a, $b) {
        return $b['total_processed'] <=> $a['total_processed'];
    });

    // ── 5. Trend 7 hari terakhir (Gunakan Index created_at) ────────────────
    $sqlTrend = "
        SELECT
            DATE(s.created_at) AS tgl,
            COALESCE(SUM(i.qty), 0) AS total_qty
        FROM return_sessions s
        LEFT JOIN return_items i ON i.session_id = s.id
        WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(s.created_at)
        ORDER BY tgl ASC
    ";
    $stmtTrend = $pdo->query($sqlTrend);
    $trend = $stmtTrend->fetchAll();

    $response = [
        'total_received_packages' => (int)($recKpi['total_received_packages'] ?? 0),
        'total_receptions'        => (int)($recKpi['total_receptions'] ?? 0),
        'total_invoices'          => (int)($metrics['total_invoices'] ?? 0),
        'total_items'             => (int)($metrics['total_items'] ?? 0),
        'total_good'              => (int)($metrics['total_good'] ?? 0),
        'total_damaged'           => (int)($metrics['total_damaged'] ?? 0),
        'by_expedition'           => $byExpeditionCombined,
        'by_condition'            => $byCondition,
        'pic_stats'               => $picStats,
        'trend_7days'             => $trend,
        'cached_at'               => date('Y-m-d H:i:s')
    ];

    // Simpan ke cache backend
    @file_put_contents($cacheFile, json_encode($response, JSON_UNESCAPED_UNICODE));

    jsonResponse($response);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
