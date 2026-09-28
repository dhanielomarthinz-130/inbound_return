<?php
require_once __DIR__ . '/../../config.php';

$date = trim($_GET['date'] ?? '');

try {
    $params = [];
    $paramsExp = [];
    $paramsCond = [];
    $where = "WHERE 1=1";

    if (!empty($date)) {
        if (strpos($date, ' to ') !== false) {
            $parts = explode(' to ', $date);
            $startDate = trim($parts[0]);
            $endDate   = trim($parts[1] ?? $parts[0]);
            $where .= " AND DATE(s.created_at) >= ? AND DATE(s.created_at) <= ?";
            $params[] = $startDate; $params[] = $endDate;
        } else {
            $where .= " AND DATE(s.created_at) = ?";
            $params[] = $date;
        }
    } else {
        $where .= " AND DATE(s.created_at) = CURDATE()";
    }

    // ── 1. KPI Summary ──────────────────────────────────────────────────────
    $kpiWhere = str_replace("s.created_at", "created_at", $where);
    $kpiParams = $params;
    $sqlKpi = "
        SELECT
            COUNT(id)                       AS total_invoices,
            COALESCE(SUM(total_items), 0)   AS total_items,
            COALESCE(SUM(total_good), 0)    AS total_good,
            COALESCE(SUM(total_damaged), 0) AS total_damaged
        FROM return_sessions
        {$kpiWhere}
    ";
    $stmtKpi = $pdo->prepare($sqlKpi);
    $stmtKpi->execute($kpiParams);
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

    // ── 4. Trend 7 hari terakhir ────────────────────────────────────────────
    $sqlTrend = "
        SELECT
            DATE(s.created_at) AS tgl,
            COALESCE(SUM(i.qty), 0) AS total_qty
        FROM return_sessions s
        LEFT JOIN return_items i ON i.session_id = s.id
        WHERE DATE(s.created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY tgl
        ORDER BY tgl ASC
    ";
    $stmtTrend = $pdo->query($sqlTrend);
    $trend = $stmtTrend->fetchAll();

    jsonResponse([
        'total_invoices'  => (int)($metrics['total_invoices'] ?? 0),
        'total_items'     => (int)($metrics['total_items'] ?? 0),
        'total_good'      => (int)($metrics['total_good'] ?? 0),
        'total_damaged'   => (int)($metrics['total_damaged'] ?? 0),
        'by_expedition'   => $byExpedition,
        'by_condition'    => $byCondition,
        'trend_7days'     => $trend,
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
