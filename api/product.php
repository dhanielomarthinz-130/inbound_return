<?php
require_once __DIR__ . '/../config.php';

$barcode = $_GET['barcode'] ?? '';

// Support jika dipanggil via PATH_INFO /api/product.php/8991001
if (empty($barcode) && !empty($_SERVER['PATH_INFO'])) {
    $barcode = trim($_SERVER['PATH_INFO'], '/');
}

$barcode = trim($barcode);

if ($barcode === '') {
    jsonResponse(['error' => 'Barcode wajib diisi'], 400);
}

// Support tanda '-' atau placeholder non-barcode untuk produk tidak dikenal (salah return / fisik tanpa barcode)
if ($barcode === '-' || strtoupper($barcode) === 'NON_BARCODE' || strtoupper($barcode) === 'TANPA_BARCODE' || strtoupper($barcode) === 'TIDAK_ADA') {
    jsonResponse([
        'id' => 0,
        'barcode' => '-',
        'name' => 'Produk Tidak Dikenal (Salah Return / Tanpa Barcode)',
        'sku' => '-',
        'seller_sku' => '-',
        'sap_code' => '-',
        'shop' => '-',
        'bin_code' => '-',
        'category' => 'Salah Return',
        'is_unknown' => true
    ]);
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
