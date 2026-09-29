<?php
require_once __DIR__ . '/../../config.php';
session_write_close();

$search     = trim($_GET['search'] ?? '');
$date       = trim($_GET['date'] ?? '');
$expedition = trim($_GET['expedition'] ?? '');
$condition  = trim($_GET['condition'] ?? '');
$operator   = trim($_GET['operator'] ?? '');

try {
    $sql = "
        SELECT 
            i.id AS item_id,
            i.session_id,
            s.id AS id,
            s.invoice_number,
            s.expedition,
            s.operator_name,
            s.customer_name,
            s.notes,
            s.video_path,
            s.created_at,
            COALESCE(NULLIF(i.seller_sku, ''), NULLIF(i.sku, ''), NULLIF(p.seller_sku, ''), NULLIF(p.sku, ''), i.barcode) AS seller_sku,
            COALESCE(NULLIF(i.product_name, ''), p.name, 'Produk Inbound') AS product_name,
            i.barcode,
            i.batch_no,
            i.exp_date,
            i.qty,
            COALESCE(i.type, i.condition, 'GOOD') AS raw_type,
            CASE 
                WHEN UPPER(COALESCE(i.type, i.condition, 'GOOD')) = 'GOOD' THEN 'GOOD'
                ELSE 'RUSAK'
            END AS condition_type,
            i.damage_reason
        FROM return_items i
        JOIN return_sessions s ON i.session_id = s.id
        LEFT JOIN master_products p ON i.barcode = p.barcode
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (
            s.invoice_number LIKE ? 
            OR s.operator_name LIKE ? 
            OR s.expedition LIKE ? 
            OR i.seller_sku LIKE ? 
            OR i.sku LIKE ? 
            OR i.product_name LIKE ? 
            OR i.barcode LIKE ? 
            OR i.batch_no LIKE ? 
            OR p.seller_sku LIKE ? 
            OR p.name LIKE ?
        )";
        $wildcard = "%{$search}%";
        for ($k = 0; $k < 10; $k++) {
            $params[] = $wildcard;
        }
    }

    if (!empty($date)) {
        if (strpos($date, ' to ') !== false) {
            $parts = explode(' to ', $date);
            $startDate = trim($parts[0]);
            $endDate = trim($parts[1] ?? $parts[0]);
            $sql .= " AND s.created_at >= ? AND s.created_at <= ?";
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
        } else {
            $sql .= " AND s.created_at >= ? AND s.created_at <= ?";
            $params[] = $date . ' 00:00:00';
            $params[] = $date . ' 23:59:59';
        }
    }

    if (!empty($expedition)) {
        $sql .= " AND s.expedition = ?";
        $params[] = $expedition;
    }

    if (!empty($condition)) {
        if (strtoupper($condition) === 'GOOD') {
            $sql .= " AND UPPER(COALESCE(i.type, i.condition, 'GOOD')) = 'GOOD'";
        } else {
            $sql .= " AND UPPER(COALESCE(i.type, i.condition, 'GOOD')) != 'GOOD'";
        }
    }

    if (!empty($operator)) {
        $sql .= " AND s.operator_name = ?";
        $params[] = $operator;
    }

    $sql .= " ORDER BY i.id DESC LIMIT 300";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        if (!empty($r['exp_date']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($r['exp_date']), $m)) {
            $r['exp_date'] = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
    }
    unset($r);

    jsonResponse($rows);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
