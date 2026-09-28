<?php
require_once __DIR__ . '/../config.php';

$invoiceNumber = $_GET['invoice_number'] ?? $_GET['invoice'] ?? '';

// Support jika dipanggil via PATH_INFO /api/invoice.php/INV-001
if (empty($invoiceNumber) && !empty($_SERVER['PATH_INFO'])) {
    $invoiceNumber = trim($_SERVER['PATH_INFO'], '/');
}

if (empty($invoiceNumber)) {
    jsonResponse(['error' => 'Nomor invoice wajib diisi'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM return_sessions WHERE invoice_number = ? ORDER BY id DESC");
    $stmt->execute([$invoiceNumber]);
    $previousReturns = $stmt->fetchAll();

    jsonResponse([
        'invoice_number' => $invoiceNumber,
        'previous_returns' => $previousReturns,
        'valid' => true
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
