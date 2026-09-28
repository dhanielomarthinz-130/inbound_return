<?php
require_once __DIR__ . '/../../config.php';

try {
    $sql = "
        SELECT 
            COUNT(id) AS total_invoices,
            COALESCE(SUM(total_items), 0) AS total_items,
            COALESCE(SUM(total_good), 0) AS total_good,
            COALESCE(SUM(total_damaged), 0) AS total_damaged
        FROM return_sessions
        WHERE DATE(created_at) = CURDATE()
    ";
    $stmt = $pdo->query($sql);
    $metrics = $stmt->fetch();

    jsonResponse([
        'total_invoices' => (int)($metrics['total_invoices'] ?? 0),
        'total_items'    => (int)($metrics['total_items'] ?? 0),
        'total_good'     => (int)($metrics['total_good'] ?? 0),
        'total_damaged'  => (int)($metrics['total_damaged'] ?? 0)
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
