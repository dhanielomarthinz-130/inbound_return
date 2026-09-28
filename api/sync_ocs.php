<?php
// api/sync_ocs.php - Sinkronisasi Data Master Produk dari OCS IEG System (https://ocs.iegsystem.id/)
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Meningkatkan timeout untuk sinkronisasi 700+ item
set_time_limit(180);

$ocsBaseUrl = 'https://ocs.iegsystem.id';
$ocsUser    = 'ADMIN';
$ocsPass    = 'ADMIN';
$ocsCompany = 'EJI_WMS';

try {
    // 1. LOGIN KE OCS IEG SYSTEM
    $loginPayload = json_encode([
        'username'  => $ocsUser,
        'password'  => $ocsPass,
        'companydb' => $ocsCompany
    ]);

    $ch = curl_init("{$ocsBaseUrl}/Auth/Login");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $loginPayload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30
    ]);

    $loginResponse = curl_exec($ch);
    $loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError     = curl_error($ch);
    curl_close($ch);

    if ($loginHttpCode !== 200 || !$loginResponse) {
        throw new Exception("Gagal login ke OCS IEG System (HTTP {$loginHttpCode}): " . ($curlError ?: $loginResponse));
    }

    $loginData = json_decode($loginResponse, true);
    $token = $loginData['Token'] ?? null;

    if (!$token) {
        throw new Exception("Token otentikasi tidak ditemukan dalam respons login OCS.");
    }

    // 2. AMBIL DATA SKU RACK DARI OCS
    $ch2 = curl_init("{$ocsBaseUrl}/MasterData/GetSkuRack");
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 60
    ]);

    $skuResponse = curl_exec($ch2);
    $skuHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $skuError    = curl_error($ch2);
    curl_close($ch2);

    if ($skuHttpCode !== 200 || !$skuResponse) {
        throw new Exception("Gagal mengambil data SKU Rack dari OCS (HTTP {$skuHttpCode}): " . ($skuError ?: $skuResponse));
    }

    $skuList = json_decode($skuResponse, true);
    if (!is_array($skuList)) {
        throw new Exception("Data SKU yang diterima bukan array yang valid.");
    }

    // 3. SIMPAN KE DATABASE MYSQL (master_products)
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO master_products 
            (barcode, sku, name, category, unit, seller_sku, sap_code, shop, bin_code, barcode_bpom)
        VALUES 
            (?, ?, ?, ?, 'Pcs', ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            sku = VALUES(sku),
            name = VALUES(name),
            category = VALUES(category),
            seller_sku = VALUES(seller_sku),
            sap_code = VALUES(sap_code),
            shop = VALUES(shop),
            bin_code = VALUES(bin_code),
            barcode_bpom = VALUES(barcode_bpom)
    ");

    $synced = 0;
    $skipped = 0;

    foreach ($skuList as $item) {
        $sellerSku   = trim($item['SellerSku'] ?? '');
        $sapCode     = trim($item['SapCode'] ?? '');
        $barcode     = trim($item['Barcode'] ?? '');
        $barcodeBpom = trim($item['BarcodeBpom'] ?? '');
        $shop        = trim($item['ShopCode'] ?? 'IEG');
        $binCode     = trim($item['BinCode'] ?? '');

        // Tentukan barcode utama untuk lookup:
        // Prioritas: Barcode asli -> SAP Code -> Seller SKU
        $primaryBarcode = !empty($barcode) ? $barcode : (!empty($sapCode) && $sapCode !== '0' ? $sapCode : $sellerSku);

        if (empty($primaryBarcode)) {
            $skipped++;
            continue;
        }

        // Nama produk: format dari Seller SKU & Shop
        $productName = $sellerSku;
        if (!empty($shop) && $shop !== 'IEG' && stripos($productName, $shop) === false) {
            $productName = "[{$shop}] " . $sellerSku;
        }

        $stmt->execute([
            $primaryBarcode,
            $sellerSku ?: $primaryBarcode,
            $productName,
            $shop ?: 'Umum',
            $sellerSku,
            $sapCode,
            $shop,
            $binCode,
            $barcodeBpom
        ]);

        $synced++;
    }

    $pdo->commit();

    jsonResponse([
        'success'      => true,
        'total_synced' => $synced,
        'skipped'      => $skipped,
        'total_source' => count($skuList),
        'message'      => "Berhasil menyinkronkan {$synced} produk dari OCS IEG System!"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse([
        'success' => false,
        'error'   => $e->getMessage()
    ], 500);
}
