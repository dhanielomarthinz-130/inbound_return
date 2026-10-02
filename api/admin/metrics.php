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

    if (!empty($date)) {
        if (strpos($date, ' to ') !== false) {
            $parts = explode(' to ', $date);
            $startDate = trim($parts[0]);
            $endDate   = trim($parts[1] ?? $parts[0]);
            $where .= " AND s.created_at >= ? AND s.created_at <= ?";
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
        } else {
            $where .= " AND s.created_at >= ? AND s.created_at <= ?";
            $params[] = $date . ' 00:00:00';
            $params[] = $date . ' 23:59:59';
        }
    } else {
        $where .= " AND s.created_at >= CURDATE() AND s.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    }

    // ── 1. KPI Summary ──────────────────────────────────────────────────────
    // Gunakan JOIN ke return_items agar total qty selalu akurat (bukan kolom denormalisasi)
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

    // ── 2. Total Qty per Ekspedisi ───────────────────────────────────────────
    $sqlExp = "
        SELECT
            COALESCE(NULLIF(s.expedition, ''), 'Tidak Ada') AS expedition,
            COUNT(DISTINCT s.id)                             AS total_sessions,
            COALESCE(SUM(i.qty), 0)                          AS total_qty
        FROM return_sessions s
        LEFT JOIN return_items i ON i.session_id = s.id
        {$where}
        GROUP BY expedition
        ORDER BY total_qty DESC
    ";
    $stmtExp = $pdo->prepare($sqlExp);
    $stmtExp->execute($params);
    $byExpedition = $stmtExp->fetchAll();

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

    // ── 4. Trend 7 hari terakhir (Gunakan Index created_at) ────────────────
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
        'total_invoices'  => (int)($metrics['total_invoices'] ?? 0),
        'total_items'     => (int)($metrics['total_items'] ?? 0),
        'total_good'      => (int)($metrics['total_good'] ?? 0),
        'total_damaged'   => (int)($metrics['total_damaged'] ?? 0),
        'by_expedition'   => $byExpedition,
        'by_condition'    => $byCondition,
        'trend_7days'     => $trend,
        'cached_at'       => date('Y-m-d H:i:s')
    ];

    // Simpan ke cache backend
    @file_put_contents($cacheFile, json_encode($response, JSON_UNESCAPED_UNICODE));

    jsonResponse($response);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
