<?php
require_once __DIR__ . '/../../config.php';

$sessionId = $_GET['session_id'] ?? $_GET['id'] ?? '';

// Support PATH_INFO
if (empty($sessionId) && !empty($_SERVER['PATH_INFO'])) {
    $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    $sessionId = $parts[0] ?? '';
}

if (empty($sessionId)) {
    jsonResponse(['error' => 'ID Sesi wajib diisi'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM return_items WHERE session_id = ? ORDER BY id ASC");
    $stmt->execute([(int)$sessionId]);
    $items = $stmt->fetchAll();

    jsonResponse($items);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
