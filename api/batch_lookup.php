<?php
require_once __DIR__ . '/../config.php';
session_write_close();

$barcode = trim($_GET['barcode'] ?? '');
$batch   = trim($_GET['batch'] ?? '');

if (empty($barcode) || empty($batch)) {
    jsonResponse(['found' => false]);
}

try {
    // 1. Coba cari dengan kombinasi barcode/SKU + nomor batch
    $stmt = $pdo->prepare("
        SELECT exp_date 
        FROM return_items 
        WHERE (barcode = ? OR sku = ? OR seller_sku = ?) 
          AND LOWER(TRIM(batch_no)) = LOWER(?) 
          AND exp_date IS NOT NULL 
          AND exp_date != '' 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$barcode, $barcode, $barcode, $batch]);
    $row = $stmt->fetch();

    // 2. Jika belum ditemukan, cari berdasarkan nomor batch saja (riwayat produk serupa)
    if (!$row) {
        $stmt2 = $pdo->prepare("
            SELECT exp_date 
            FROM return_items 
            WHERE LOWER(TRIM(batch_no)) = LOWER(?) 
              AND exp_date IS NOT NULL 
              AND exp_date != '' 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt2->execute([$batch]);
        $row = $stmt2->fetch();
    }

    if ($row && !empty($row['exp_date'])) {
        $exp = trim($row['exp_date']);
        // Format ke YYYY-MM-DD agar pas dengan input HTML5 date
        if (preg_match('#^(\d{2})[-/](\d{2})[-/](\d{4})$#', $exp, $m)) {
            $exp = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        jsonResponse([
            'found' => true,
            'exp_date' => $exp,
            'source' => 'history'
        ]);
    } else {
        jsonResponse(['found' => false]);
    }
} catch (Exception $e) {
    jsonResponse(['found' => false, 'error' => $e->getMessage()]);
}
