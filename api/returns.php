<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metode tidak diizinkan'], 405);
}

// 1. Terima payload (bisa via Multipart FormData atau Raw JSON)
$body = [];
if (!empty($_POST['data'])) {
    $body = json_decode($_POST['data'], true);
} else {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $body = json_decode($rawInput, true);
    }
}

if (!$body || !is_array($body)) {
    jsonResponse(['error' => 'Format input data tidak valid'], 400);
}

$invoiceNumber = trim($body['invoice_number'] ?? '');
$operatorName  = trim($body['operator_name'] ?? 'Gudang 01');
$customerName  = trim($body['customer_name'] ?? 'Pelanggan Return');
$expedition    = trim($body['expedition'] ?? '');
$notes         = trim($body['notes'] ?? '');
$items         = $body['items'] ?? [];
$videoPath     = trim($body['video_path'] ?? '');

// 2. Cek apakah ada file video yang di-upload via $_FILES
if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/videos/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    
    $cleanInv = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invoiceNumber);
    $ext = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);
    if (empty($ext)) $ext = 'webm';
    $fileName = 'video_' . $cleanInv . '_' . time() . '.' . $ext;
    $targetFile = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['video']['tmp_name'], $targetFile)) {
        $videoPath = 'uploads/videos/' . $fileName;
    }
}

if (empty($invoiceNumber) || empty($items) || !is_array($items)) {
    jsonResponse(['error' => 'Nomor Invoice dan minimal 1 produk wajib diisi'], 400);
}

$totalGood = 0;
$totalDamaged = 0;
$totalItems = 0;

foreach ($items as $item) {
    $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
    if ($qty < 1) $qty = 1;
    $totalItems += $qty;

    $condition = strtoupper(trim($item['condition'] ?? 'GOOD'));
    if ($condition === 'GOOD') {
        $totalGood += $qty;
    } else {
        $totalDamaged += $qty;
    }
}

try {
    $pdo->beginTransaction();

    $stmtSession = $pdo->prepare("
        INSERT INTO return_sessions (invoice_number, customer_name, expedition, operator_name, total_items, total_good, total_damaged, notes, video_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtSession->execute([
        $invoiceNumber,
        $customerName,
        $expedition,
        $operatorName,
        $totalItems,
        $totalGood,
        $totalDamaged,
        $notes,
        $videoPath
    ]);

    $sessionId = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO return_items (session_id, barcode, product_name, sku, seller_sku, sap_code, batch_no, exp_date, type, qty, `condition`, damage_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
        if ($qty < 1) $qty = 1;
        $type = strtoupper(trim($item['type'] ?? $item['condition'] ?? 'GOOD'));
        $cond = ($type === 'GOOD') ? 'GOOD' : 'RUSAK';
        $reason = ($cond === 'RUSAK') ? ($item['damage_reason'] ?? $type) : '';

        $stmtItem->execute([
            $sessionId,
            $item['barcode'] ?? '',
            $item['product_name'] ?? '',
            $item['sku'] ?? '',
            $item['seller_sku'] ?? $item['sku'] ?? '',
            $item['sap_code'] ?? '',
            $item['batch_no'] ?? '',
            $item['exp_date'] ?? '',
            $type,
            $qty,
            $cond,
            $reason
        ]);
    }

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'session_id' => (int)$sessionId,
        'message' => 'Inbound Return berhasil direkam!'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['error' => 'Gagal menyimpan return: ' . $e->getMessage()], 500);
}
