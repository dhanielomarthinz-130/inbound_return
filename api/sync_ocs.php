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

    // 2. AMBIL DATA SKU RACK DARI OCS (Barcode, Rak/Bin, SAP, Shop)
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

    // 2b. AMBIL DATA STOCKS VIEW V2 (Sku Name Lengkap berdasarkan Sku / Seller SKU)
    // Sumber: https://ocs.iegsystem.id/stocks/view-v2 -> /odata/DTO_WmsItemStockLite
    $stockNameMap = [];
    try {
        $chStock = curl_init("{$ocsBaseUrl}/odata/DTO_WmsItemStockLite");
        curl_setopt_array($chStock, [
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
        $stockResponse = curl_exec($chStock);
        $stockHttpCode = curl_getinfo($chStock, CURLINFO_HTTP_CODE);
        curl_close($chStock);

        if ($stockHttpCode === 200 && $stockResponse) {
            $stockJson = json_decode($stockResponse, true);
            $stockItems = $stockJson['value'] ?? [];
            if (is_array($stockItems)) {
                foreach ($stockItems as $stk) {
                    $sSku = strtoupper(trim($stk['Sku'] ?? ''));
                    $sName = trim($stk['Name'] ?? '');
                    if (!empty($sSku) && !empty($sName)) {
                        $stockNameMap[$sSku] = $sName;
                    }
                }
            }
        }
    } catch (Exception $eStock) {
        // Fallback jika stocks view lambat/gagal, tetap lanjutkan dengan SKU Rack
        error_log("Peringatan: Gagal memuat DTO_WmsItemStockLite: " . $eStock->getMessage());
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
    $namesMatched = 0;

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

        // Tentukan Nama Produk:
        // Prioritas UTAMA: Ambil Sku Name dari https://ocs.iegsystem.id/stocks/view-v2 berdasarkan Seller SKU!
        $sellerSkuUpper = strtoupper($sellerSku);
        $sapCodeUpper   = strtoupper($sapCode);
        $productName    = '';

        if (!empty($sellerSkuUpper) && isset($stockNameMap[$sellerSkuUpper])) {
            $productName = $stockNameMap[$sellerSkuUpper];
            $namesMatched++;
        } elseif (!empty($sapCodeUpper) && isset($stockNameMap[$sapCodeUpper])) {
            $productName = $stockNameMap[$sapCodeUpper];
            $namesMatched++;
        } else {
            // Fallback jika tidak terdaftar di Stocks View
            $productName = $sellerSku;
            if (!empty($shop) && $shop !== 'IEG' && stripos($productName, $shop) === false) {
                $productName = "[{$shop}] " . $sellerSku;
            }
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
        'success'        => true,
        'total_synced'   => $synced,
        'names_matched'  => $namesMatched,
        'skipped'        => $skipped,
        'total_source'   => count($skuList),
        'message'        => "Berhasil menyinkronkan {$synced} produk dari OCS IEG System ({$namesMatched} nama produk diambil dari Stocks View V2)!"
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
