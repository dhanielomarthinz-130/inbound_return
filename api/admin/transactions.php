<?php
require_once __DIR__ . '/../../config.php';

$search = trim($_GET['search'] ?? '');
$date   = trim($_GET['date'] ?? '');

try {
    $sql = "
        SELECT 
            s.*,
            GROUP_CONCAT(CONCAT(i.product_name, ' (', COALESCE(i.type, i.condition), ' x', i.qty, ')') SEPARATOR ', ') AS items_summary
        FROM return_sessions s
        LEFT JOIN return_items i ON s.id = i.session_id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (s.invoice_number LIKE ? OR s.operator_name LIKE ? OR s.customer_name LIKE ? OR s.expedition LIKE ?)";
        $wildcard = "%{$search}%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    if (!empty($date)) {
        $sql .= " AND DATE(s.created_at) = ?";
        $params[] = $date;
    }

    $sql .= " GROUP BY s.id ORDER BY s.id DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    jsonResponse($rows);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
