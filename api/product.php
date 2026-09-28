<?php
require_once __DIR__ . '/../config.php';

$barcode = $_GET['barcode'] ?? '';

// Support jika dipanggil via PATH_INFO /api/product.php/8991001
if (empty($barcode) && !empty($_SERVER['PATH_INFO'])) {
    $barcode = trim($_SERVER['PATH_INFO'], '/');
}

if (empty($barcode)) {
    jsonResponse(['error' => 'Barcode wajib diisi'], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM master_products 
        WHERE barcode = ? 
           OR barcode_bpom = ? 
           OR sap_code = ? 
           OR seller_sku = ? 
           OR sku = ?
        LIMIT 1
    ");
    $stmt->execute([$barcode, $barcode, $barcode, $barcode, $barcode]);
    $product = $stmt->fetch();

    if (!$product) {
        jsonResponse([
            'error' => 'Produk tidak ditemukan di master data',
            'barcode' => $barcode
        ], 404);
    }

    jsonResponse($product);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
