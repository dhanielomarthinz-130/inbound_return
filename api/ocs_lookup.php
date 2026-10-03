<?php
/**
 * api/ocs_lookup.php
 * Lookup data order, no resi, dan video packing dari OCS IEG System (https://ocs.iegsystem.id/)
 * Serta melakukan cross-reference dengan data Receiving Inbound dan Inbound Unboxing lokal.
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$query   = trim($_GET['q'] ?? $_GET['query'] ?? $_POST['query'] ?? '');
$action  = trim($_GET['action'] ?? $_POST['action'] ?? '');
$refresh = isset($_GET['refresh']) && $_GET['refresh'] == '1';

// JIKA TANPA QUERY ATAU MEMINTA LIST KANDIDAT KLAIM: Tampilkan semua paket unboxing yang kondisinya BUKAN GOOD
if ($query === '' || $action === 'list_claimable') {
    try {
        // Pastikan tabel ocs_orders ada
        $hasOcsTable = false;
        try {
            $chkOcs = $pdo->query("SHOW TABLES LIKE 'ocs_orders'");
            if ($chkOcs && $chkOcs->rowCount() > 0) {
                $hasOcsTable = true;
            } else {
                if (function_exists('ensureDatabaseSchema')) {
                    ensureDatabaseSchema($pdo);
                    $hasOcsTable = true;
                }
            }
        } catch (Exception $e) {}

        if ($hasOcsTable) {
            $sqlDamaged = "
                SELECT rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.status, 
                       rs.total_items, rs.total_good, rs.total_damaged, rs.notes, rs.video_path, rs.created_at,
                       COUNT(ri.id) as item_count,
                       SUM(CASE WHEN (ri.condition != 'GOOD' AND ri.condition != 'BAGUS' AND ri.condition IS NOT NULL AND ri.condition != '') 
                                  OR (ri.type != 'GOOD' AND ri.type != 'BAGUS' AND ri.type IS NOT NULL AND ri.type != '') 
                                  OR (ri.damage_reason IS NOT NULL AND ri.damage_reason != '') THEN 1 ELSE 0 END) as damaged_items_count,
                       SUM(CASE WHEN (ri.condition != 'GOOD' AND ri.condition != 'BAGUS' AND ri.condition IS NOT NULL AND ri.condition != '') 
                                  OR (ri.type != 'GOOD' AND ri.type != 'BAGUS' AND ri.type IS NOT NULL AND ri.type != '') 
                                  OR (ri.damage_reason IS NOT NULL AND ri.damage_reason != '') THEN ri.qty ELSE 0 END) as damaged_qty_sum,
                       GROUP_CONCAT(DISTINCT CASE 
                           WHEN (ri.condition != 'GOOD' AND ri.condition != 'BAGUS' AND ri.condition IS NOT NULL AND ri.condition != '') 
                             OR (ri.type != 'GOOD' AND ri.type != 'BAGUS' AND ri.type IS NOT NULL AND ri.type != '') 
                             OR (ri.damage_reason IS NOT NULL AND ri.damage_reason != '') 
                           THEN CONCAT(ri.product_name, ' (x', ri.qty, ')') 
                           ELSE NULL 
                       END SEPARATOR ', ') as damaged_product_names,
                       GROUP_CONCAT(DISTINCT CONCAT(ri.product_name, ' (x', ri.qty, ')') SEPARATOR ', ') as all_product_names,
                       GROUP_CONCAT(DISTINCT CASE WHEN ri.damage_reason IS NOT NULL AND ri.damage_reason != '' THEN ri.damage_reason ELSE NULL END SEPARATOR '; ') as damage_reasons,
                       MAX(o.package_price) as package_price, 
                       MAX(o.commerce_platform) as commerce_platform, 
                       MAX(o.shop_name) as shop_name, 
                       MAX(o.product_name) as ocs_product_name,
                       MAX(o.has_packing_video) as has_packing_video
                FROM return_sessions rs
                LEFT JOIN return_items ri ON ri.session_id = rs.id
                LEFT JOIN ocs_orders o ON (o.order_id = rs.invoice_number OR o.tracking_number = rs.invoice_number)
                GROUP BY rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.status, 
                         rs.total_items, rs.total_good, rs.total_damaged, rs.notes, rs.video_path, rs.created_at
                HAVING rs.total_damaged > 0 OR damaged_items_count > 0
                ORDER BY rs.id DESC
            ";
        } else {
            // Fallback query jika ocs_orders belum siap
            $sqlDamaged = "
                SELECT rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.status, 
                       rs.total_items, rs.total_good, rs.total_damaged, rs.notes, rs.video_path, rs.created_at,
                       COUNT(ri.id) as item_count,
                       SUM(CASE WHEN (ri.condition != 'GOOD' AND ri.condition != 'BAGUS' AND ri.condition IS NOT NULL AND ri.condition != '') 
                                  OR (ri.type != 'GOOD' AND ri.type != 'BAGUS' AND ri.type IS NOT NULL AND ri.type != '') 
                                  OR (ri.damage_reason IS NOT NULL AND ri.damage_reason != '') THEN 1 ELSE 0 END) as damaged_items_count,
                       SUM(CASE WHEN (ri.condition != 'GOOD' AND ri.condition != 'BAGUS' AND ri.condition IS NOT NULL AND ri.condition != '') 
                                  OR (ri.type != 'GOOD' AND ri.type != 'BAGUS' AND ri.type IS NOT NULL AND ri.type != '') 
                                  OR (ri.damage_reason IS NOT NULL AND ri.damage_reason != '') THEN ri.qty ELSE 0 END) as damaged_qty_sum,
                       GROUP_CONCAT(DISTINCT CASE 
                           WHEN (ri.condition != 'GOOD' AND ri.condition != 'BAGUS' AND ri.condition IS NOT NULL AND ri.condition != '') 
                             OR (ri.type != 'GOOD' AND ri.type != 'BAGUS' AND ri.type IS NOT NULL AND ri.type != '') 
                             OR (ri.damage_reason IS NOT NULL AND ri.damage_reason != '') 
                           THEN CONCAT(ri.product_name, ' (x', ri.qty, ')') 
                           ELSE NULL 
                       END SEPARATOR ', ') as damaged_product_names,
                       GROUP_CONCAT(DISTINCT CONCAT(ri.product_name, ' (x', ri.qty, ')') SEPARATOR ', ') as all_product_names,
                       GROUP_CONCAT(DISTINCT CASE WHEN ri.damage_reason IS NOT NULL AND ri.damage_reason != '' THEN ri.damage_reason ELSE NULL END SEPARATOR '; ') as damage_reasons,
                       0 as package_price, 
                       NULL as commerce_platform, 
                       NULL as shop_name, 
                       NULL as ocs_product_name,
                       0 as has_packing_video
                FROM return_sessions rs
                LEFT JOIN return_items ri ON ri.session_id = rs.id
                GROUP BY rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.status, 
                         rs.total_items, rs.total_good, rs.total_damaged, rs.notes, rs.video_path, rs.created_at
                HAVING rs.total_damaged > 0 OR damaged_items_count > 0
                ORDER BY rs.id DESC
            ";
        }

        $stmtDamaged = $pdo->query($sqlDamaged);
        $candidates = $stmtDamaged->fetchAll(PDO::FETCH_ASSOC);

        // Format data kandidat
        foreach ($candidates as &$c) {
            $price = (float)($c['package_price'] ?? 0);
            $c['package_price_formatted'] = $price > 0 ? 'Rp ' . number_format($price, 0, ',', '.') : '-';
            
            // Nama produk prioritaskan barang yang rusak
            $pName = !empty($c['damaged_product_names']) ? $c['damaged_product_names'] : (!empty($c['all_product_names']) ? $c['all_product_names'] : ($c['ocs_product_name'] ?? '-'));
            $c['product_names'] = $pName;

            // Qty rusak akurat
            $dmgQty = (int)($c['damaged_qty_sum'] ?? 0);
            if ($dmgQty <= 0) {
                $dmgQty = (int)($c['total_damaged'] ?? 0);
            }
            if ($dmgQty <= 0) {
                $dmgQty = (int)($c['damaged_items_count'] ?? 1);
            }
            $c['damaged_qty'] = $dmgQty;
        }

        echo json_encode([
            'success'    => true,
            'action'     => 'list_claimable',
            'total'      => count($candidates),
            'candidates' => $candidates
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 1. Sanitasi query input — pertahankan tanda hubung (-) & slash karena ada di format resi ekspedisi
$rawQuery = trim($_GET['q'] ?? $_GET['query'] ?? $_POST['query'] ?? '');
$query    = trim(preg_replace('/[\r\n\t]+/', '', $rawQuery));
// cleanQuery: hanya hapus karakter benar-benar tidak valid (spasi, dll), tapi pertahankan - dan /
$cleanQuery = trim(preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $query));
// alphaNum: versi tanpa separator (untuk fallback matching)
$alphaNumQuery = preg_replace('/[^a-zA-Z0-9]/', '', $query);

$ocsBaseUrl = 'https://ocs.iegsystem.id';
$ocsUser    = 'ADMIN';
$ocsPass    = 'ADMIN';
$ocsCompany = 'EJI_WMS';

try {
    $orderData = null;

    // 1. Cek cache lokal di tabel ocs_orders terlebih dahulu (jika bukan mode force refresh)
    if (!$refresh && ($query !== '' || $cleanQuery !== '')) {
        $stmtCache = $pdo->prepare("
            SELECT * FROM ocs_orders 
            WHERE order_id = :q1 
               OR tracking_number = :q2 
               OR order_id = :q3 
               OR tracking_number = :q4
               OR REPLACE(REPLACE(tracking_number, ' ', ''), '-', '') = :q5
               OR REPLACE(REPLACE(order_id, ' ', ''), '-', '') = :q6
               OR tracking_number LIKE :q7
               OR order_id LIKE :q8
            LIMIT 1
        ");
        $stmtCache->execute([
            ':q1' => $query,
            ':q2' => $query,
            ':q3' => $cleanQuery,
            ':q4' => $cleanQuery,
            ':q5' => $alphaNumQuery,
            ':q6' => $alphaNumQuery,
            ':q7' => '%' . $cleanQuery . '%',
            ':q8' => '%' . $cleanQuery . '%',
        ]);
        $cached = $stmtCache->fetch(PDO::FETCH_ASSOC);

        // Jika belum langsung ditemukan di ocs_orders, coba cari padanan resi/invoice dari data receiving / unboxing lokal
        if (!$cached) {
            try {
                $stmtLocalLink = $pdo->prepare("
                    SELECT p.package_barcode, rs.invoice_number 
                    FROM packages p 
                    LEFT JOIN return_sessions rs ON (rs.invoice_number = p.package_barcode OR rs.invoice_number LIKE CONCAT('%', p.package_barcode, '%'))
                    WHERE p.package_barcode = :q1 OR rs.invoice_number = :q2 
                       OR p.package_barcode LIKE :q3 OR rs.invoice_number LIKE :q4
                    LIMIT 1
                ");
                $stmtLocalLink->execute([
                    ':q1' => $query,
                    ':q2' => $query,
                    ':q3' => '%' . $cleanQuery . '%',
                    ':q4' => '%' . $cleanQuery . '%'
                ]);
                $link = $stmtLocalLink->fetch(PDO::FETCH_ASSOC);
                if ($link) {
                    $altQuery = !empty($link['package_barcode']) ? $link['package_barcode'] : $link['invoice_number'];
                    if ($altQuery && $altQuery !== $query) {
                        $stmtCache2 = $pdo->prepare("
                            SELECT * FROM ocs_orders 
                            WHERE order_id = :q1 OR tracking_number = :q2 
                               OR tracking_number LIKE :q3 OR order_id LIKE :q4 
                            LIMIT 1
                        ");
                        $stmtCache2->execute([
                            ':q1' => $altQuery,
                            ':q2' => $altQuery,
                            ':q3' => '%' . $altQuery . '%',
                            ':q4' => '%' . $altQuery . '%'
                        ]);
                        $cached = $stmtCache2->fetch(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Exception $eLink) {}
        }

        if ($cached) {
            $itemsDecoded = !empty($cached['order_items_json']) ? json_decode($cached['order_items_json'], true) : [];
            $pkgPrice = (float)($cached['total_amount'] > 0 ? $cached['total_amount'] : ($cached['package_price'] > 0 ? $cached['package_price'] : ($cached['nmv'] > 0 ? $cached['nmv'] : $cached['gmv'])));
            $shippingFee = (float)($cached['shipping_fee'] ?? 0);
            $totalClaim = $pkgPrice > 0 ? $pkgPrice : ((float)($cached['original_price'] ?? 0) + $shippingFee);

            $orderData = [
                'Id'                     => $cached['order_id'],
                'TrackingNumber'         => $cached['tracking_number'],
                'PlatformId'             => $cached['platform_id'],
                'CommercePlatform'       => $cached['commerce_platform'],
                'ShopName'               => $cached['shop_name'],
                'ShippingProvider'       => $cached['shipping_provider'],
                'StatusCode'             => $cached['status_code'],
                'StatusName'             => $cached['status_name'] ?? '',
                'ProductName'            => $cached['product_name'],
                'SellerSku'              => $cached['seller_sku'],
                'TotalQtyOrder'          => $cached['total_qty'],
                'PackagePrice'           => $pkgPrice,
                'PackagePriceFormatted'  => 'Rp ' . number_format($pkgPrice, 0, ',', '.'),
                'ShippingFee'            => $shippingFee,
                'ShippingFeeFormatted'   => $shippingFee > 0 ? 'Rp ' . number_format($shippingFee, 0, ',', '.') : 'Rp 0',
                'TotalClaimAmount'       => $totalClaim,
                'TotalClaimAmountFormatted' => $totalClaim > 0 ? 'Rp ' . number_format($totalClaim, 0, ',', '.') : 'Rp ' . number_format($pkgPrice, 0, ',', '.'),
                'GMV'                    => (float)($cached['gmv'] > 0 ? $cached['gmv'] : $cached['original_price']),
                'NMV'                    => (float)($cached['nmv'] > 0 ? $cached['nmv'] : $pkgPrice),
                'Details'                => $itemsDecoded,
                'Items'                  => $itemsDecoded,
                'Customer'               => [
                    'Name'        => $cached['customer_name'] ?? '',
                    'PhoneNumber' => $cached['customer_phone'] ?? '',
                    'FullAddress' => $cached['customer_address'] ?? ''
                ],
                'Payment'                => [
                    'OriginalTotalProductPrice' => (float)$cached['original_price'],
                    'SellerDiscount'            => (float)$cached['seller_discount'],
                    'PlatformDiscount'          => (float)$cached['platform_discount'],
                    'ShippingFee'               => $shippingFee,
                    'ServiceFee'                => (float)$cached['service_fee'],
                    'SubTotal'                  => (float)$cached['subtotal'],
                    'TotalAmount'               => $totalClaim
                ],
                'CreatedAt'              => $cached['order_created_at'],
                '_source'                => 'local_server_orders'
            ];
        }
    }

    // 2. Jika belum ada di cache atau di-refresh, query langsung ke OCS IEG System
    if (!$orderData && ($query !== '' || $cleanQuery !== '')) {
        try {
            // Login ke OCS untuk mendapatkan Bearer Token
            $chLogin = curl_init("{$ocsBaseUrl}/Auth/Login");
            curl_setopt_array($chLogin, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode([
                    'username'  => $ocsUser,
                    'password'  => $ocsPass,
                    'companydb' => $ocsCompany
                ]),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 8
            ]);
            $loginRes = curl_exec($chLogin);
            $loginHttp = curl_getinfo($chLogin, CURLINFO_HTTP_CODE);
            curl_close($chLogin);

            if ($loginHttp === 200 && $loginRes) {
                $loginJson = json_decode($loginRes, true);
                $token = $loginJson['Token'] ?? null;
                if ($token) {
                    $queryCandidates = array_values(array_unique(array_filter([$cleanQuery, $query])));

                    // =========================================================================
                    // LANGKAH 1 (PRIORITAS UTAMA): FIND ORDER OCS (FITUR RESMI PICKLIST OCS)
                    // Menggunakan https://ocs.iegsystem.id/Orders/FindOrder?keyword=...
                    // Sesuai modul Find Order di https://ocs.iegsystem.id/picklist
                    // Mendukung pencarian instan: No. Resi (TrackingNumber), No. Order (Id), PackageId!
                    // =========================================================================
                    $findOrderMatched = null;
                    foreach ($queryCandidates as $qc) {
                        $chFind = curl_init("{$ocsBaseUrl}/Orders/FindOrder?keyword=" . urlencode($qc));
                        curl_setopt_array($chFind, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPGET        => true,
                            CURLOPT_HTTPHEADER     => [
                                "Authorization: Bearer {$token}",
                                "Accept: application/json"
                            ],
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                            CURLOPT_TIMEOUT        => 8
                        ]);
                        $findRes = curl_exec($chFind);
                        $findHttp = curl_getinfo($chFind, CURLINFO_HTTP_CODE);
                        curl_close($chFind);

                        if ($findHttp === 200 && $findRes) {
                            $findJson = json_decode($findRes, true);
                            if (!empty($findJson['Order']['Id'])) {
                                $findOrderMatched = $findJson;
                                break;
                            }
                        }
                    }

                    if ($findOrderMatched) {
                        $fo = $findOrderMatched['Order'];
                        $fp = $findOrderMatched['Payment'] ?? [];
                        $fa = $findOrderMatched['Address'] ?? [];
                        $fSkus = $findOrderMatched['Skus'] ?? [];
                        $fPick = $findOrderMatched['Picklists'] ?? [];

                        $origProdPrice = (float)($fp['OriginalTotalProductPrice'] ?? 0);
                        $sellerDisc = (float)($fp['SellerDiscount'] ?? 0);
                        $platformDisc = (float)($fp['PlatformDiscount'] ?? 0);
                        $shipFee = (float)($fp['ShippingFee'] ?? 0);
                        $serviceFee = (float)($fp['ServiceFee'] ?? 0);
                        $subtotal = (float)($fp['SubTotal'] ?? ($origProdPrice - $sellerDisc));
                        $totalAmount = (float)($fp['TotalAmount'] ?? 0);
                        if ($totalAmount <= 0) {
                            $totalAmount = $subtotal > 0 ? ($subtotal + $shipFee + $serviceFee) : $origProdPrice;
                        }

                        $parsedItems = [];
                        $itemNames = [];
                        $primarySellerSku = null;

                        foreach ($fSkus as $det) {
                            $pSku = trim($det['SellerSku'] ?? $det['BundleSku'] ?? '');
                            $pName = trim($det['ProductName'] ?? '');
                            $sName = trim($det['SkuName'] ?? '');
                            $pQty = (int)($det['Qty'] ?? 1);

                            if (!$primarySellerSku && !empty($pSku)) $primarySellerSku = $pSku;
                            $itemNames[] = ($pSku ? "[{$pSku}] " : "") . $pName . ($sName ? " ({$sName})" : "") . " (x{$pQty})";

                            $parsedItems[] = [
                                'sku_id'            => $pSku,
                                'seller_sku'        => $pSku,
                                'product_name'      => $pName,
                                'sku_name'          => $sName,
                                'qty'               => $pQty,
                                'original_price'    => (float)($det['OriginalPrice'] ?? 0),
                                'sale_price'        => (float)($det['SalePrice'] ?? 0),
                                'seller_discount'   => 0,
                                'platform_discount' => 0,
                                'subtotal'          => (float)($det['SalePrice'] ?? 0) * $pQty
                            ];
                        }

                        if (empty($parsedItems) && !empty($fPick)) {
                            foreach ($fPick as $pIt) {
                                $pSku = trim($pIt['ItemCode'] ?? $pIt['BundleCode'] ?? '');
                                $pName = trim($pIt['ItemName'] ?? '');
                                $pQty = (int)($pIt['QtyOrdered'] ?? 1);
                                if (!$primarySellerSku && !empty($pSku)) $primarySellerSku = $pSku;
                                $itemNames[] = ($pSku ? "[{$pSku}] " : "") . $pName . " (x{$pQty})";
                                $parsedItems[] = [
                                    'sku_id'            => $pSku,
                                    'seller_sku'        => $pSku,
                                    'product_name'      => $pName,
                                    'sku_name'          => '',
                                    'qty'               => $pQty,
                                    'original_price'    => 0,
                                    'sale_price'        => 0,
                                    'seller_discount'   => 0,
                                    'platform_discount' => 0,
                                    'subtotal'          => 0
                                ];
                            }
                        }

                        $prodText = !empty($itemNames) ? implode(', ', $itemNames) : ($fo['ProductName'] ?? '');

                        $orderData = [
                            'Id'                     => $fo['Id'],
                            'TrackingNumber'         => $fo['TrackingNumber'] ?? '',
                            'PlatformId'             => $fo['PlatformId'] ?? null,
                            'CommercePlatform'       => $fo['CommercePlatform'] ?? '',
                            'ShopName'               => $fo['ShopName'] ?? '',
                            'ShippingProvider'       => trim(($fo['ShippingProvider'] ?? '') . ' ' . ($fo['DeliveryOptionName'] ?? '')),
                            'StatusCode'             => $fo['StatusCode'] ?? null,
                            'StatusName'             => $fo['Status'] ?? $fo['StatusName'] ?? '',
                            'ProductName'            => $prodText,
                            'SellerSku'              => $primarySellerSku,
                            'TotalQtyOrder'          => (int)($fo['TotalQtyOrder'] ?? count($parsedItems)),
                            'PackagePrice'           => $totalAmount,
                            'PackagePriceFormatted'  => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
                            'OriginalPrice'          => $origProdPrice,
                            'SellerDiscount'         => $sellerDisc,
                            'PlatformDiscount'       => $platformDisc,
                            'ShippingFee'            => $shipFee,
                            'ShippingFeeFormatted'   => $shipFee > 0 ? 'Rp ' . number_format($shipFee, 0, ',', '.') : 'Rp 0',
                            'ServiceFee'             => $serviceFee,
                            'SubTotal'               => $subtotal,
                            'TotalClaimAmount'       => $totalAmount,
                            'TotalClaimAmountFormatted' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
                            'GMV'                    => $origProdPrice > 0 ? $origProdPrice : $totalAmount,
                            'NMV'                    => $totalAmount,
                            'Details'                => $parsedItems,
                            'Items'                  => $parsedItems,
                            'Picklists'              => $fPick,
                            'Customer'               => [
                                'Name'        => $fa['Name'] ?? '',
                                'PhoneNumber' => $fa['PhoneNumber'] ?? '',
                                'FullAddress' => trim(($fa['Province'] ?? '') . ' ' . ($fa['Regency'] ?? '') . ' ' . ($fa['Country'] ?? ''))
                            ],
                            'Payment'                => $fp,
                            'CreatedAt'              => $fo['CreatedAt'] ?? '',
                            '_source'                => 'ocs_picklist_find_order'
                        ];

                        // Simpan ke database ocs_orders lokal
                        try {
                            $hasIsSyncedCol = false;
                            try {
                                $colsOcs = $pdo->query("SHOW COLUMNS FROM ocs_orders")->fetchAll(PDO::FETCH_COLUMN);
                                if (!in_array('is_synced_to_local', $colsOcs)) {
                                    try { $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN is_synced_to_local TINYINT(1) DEFAULT 0 AFTER raw_payload"); $colsOcs[] = 'is_synced_to_local'; } catch (Exception $eCol) {}
                                }
                                $hasIsSyncedCol = in_array('is_synced_to_local', $colsOcs);
                            } catch (Exception $eCols) {}

                            $colSyncPart = $hasIsSyncedCol ? ", is_synced_to_local" : "";
                            $valSyncPart = $hasIsSyncedCol ? ", 1" : "";

                            $stmtUpsert = $pdo->prepare("
                                INSERT INTO ocs_orders (
                                    order_id, tracking_number, platform_id, commerce_platform, 
                                    shop_name, shipping_provider, status_code, status_name, product_name, seller_sku,
                                    total_qty, package_price, original_price, seller_discount, platform_discount,
                                    shipping_fee, service_fee, subtotal, total_amount, gmv, nmv,
                                    customer_name, customer_phone, customer_address, order_items_json,
                                    has_packing_video, packing_video_url, order_created_at, raw_payload{$colSyncPart}
                                ) VALUES (
                                    :order_id, :tracking_number, :platform_id, :commerce_platform, 
                                    :shop_name, :shipping_provider, :status_code, :status_name, :product_name, :seller_sku,
                                    :total_qty, :package_price, :original_price, :seller_discount, :platform_discount,
                                    :shipping_fee, :service_fee, :subtotal, :total_amount, :gmv, :nmv,
                                    :customer_name, :customer_phone, :customer_address, :order_items_json,
                                    0, NULL, :order_created_at, :raw_payload{$valSyncPart}
                                )
                                ON DUPLICATE KEY UPDATE 
                                    tracking_number   = COALESCE(VALUES(tracking_number), tracking_number),
                                    platform_id       = VALUES(platform_id),
                                    commerce_platform = VALUES(commerce_platform),
                                    shop_name         = VALUES(shop_name),
                                    shipping_provider = VALUES(shipping_provider),
                                    status_code       = VALUES(status_code),
                                    status_name       = VALUES(status_name),
                                    product_name      = VALUES(product_name),
                                    seller_sku        = VALUES(seller_sku),
                                    total_qty         = VALUES(total_qty),
                                    package_price     = VALUES(package_price),
                                    original_price    = VALUES(original_price),
                                    seller_discount   = VALUES(seller_discount),
                                    platform_discount = VALUES(platform_discount),
                                    shipping_fee      = VALUES(shipping_fee),
                                    service_fee       = VALUES(service_fee),
                                    subtotal          = VALUES(subtotal),
                                    total_amount      = VALUES(total_amount),
                                    gmv               = VALUES(gmv),
                                    nmv               = VALUES(nmv),
                                    customer_name     = VALUES(customer_name),
                                    customer_phone    = VALUES(customer_phone),
                                    customer_address  = VALUES(customer_address),
                                    order_items_json  = VALUES(order_items_json),
                                    order_created_at  = VALUES(order_created_at),
                                    raw_payload       = VALUES(raw_payload)
                            ");
                            $stmtUpsert->execute([
                                ':order_id'          => $orderData['Id'],
                                ':tracking_number'   => $orderData['TrackingNumber'] ?: null,
                                ':platform_id'       => $orderData['PlatformId'] ?: null,
                                ':commerce_platform' => $orderData['CommercePlatform'] ?: null,
                                ':shop_name'         => $orderData['ShopName'] ?: null,
                                ':shipping_provider' => $orderData['ShippingProvider'] ?: null,
                                ':status_code'       => $orderData['StatusCode'] ?: null,
                                ':status_name'       => $orderData['StatusName'] ?: null,
                                ':product_name'      => $orderData['ProductName'] ?: null,
                                ':seller_sku'        => $orderData['SellerSku'] ?: null,
                                ':total_qty'         => (int)$orderData['TotalQtyOrder'],
                                ':package_price'     => $orderData['PackagePrice'],
                                ':original_price'    => $orderData['OriginalPrice'],
                                ':seller_discount'   => $orderData['SellerDiscount'],
                                ':platform_discount' => $orderData['PlatformDiscount'],
                                ':shipping_fee'      => $orderData['ShippingFee'],
                                ':service_fee'       => $orderData['ServiceFee'],
                                ':subtotal'          => $orderData['SubTotal'],
                                ':total_amount'      => $orderData['TotalClaimAmount'],
                                ':gmv'               => $orderData['GMV'],
                                ':nmv'               => $orderData['NMV'],
                                ':customer_name'     => $orderData['Customer']['Name'] ?: null,
                                ':customer_phone'    => $orderData['Customer']['PhoneNumber'] ?: null,
                                ':customer_address'  => $orderData['Customer']['FullAddress'] ?: null,
                                ':order_items_json'  => json_encode($parsedItems, JSON_UNESCAPED_UNICODE),
                                ':order_created_at'  => !empty($orderData['CreatedAt']) ? gmdate('Y-m-d H:i:s', strtotime($orderData['CreatedAt'])) : date('Y-m-d H:i:s'),
                                ':raw_payload'       => json_encode($findOrderMatched, JSON_UNESCAPED_UNICODE)
                            ]);
                        } catch (Exception $eUpsert) {
                            error_log("Gagal upsert ocs_orders dari FindOrder: " . $eUpsert->getMessage());
                        }
                    }

                    // FALLBACK JIKA FIND ORDER TIDAK MENEMUKAN DATA:
                    if (!$orderData) {
                        $foundOrderId = null;

                        // Fallback A: Coba panggil GetOrderDetail dengan $cleanQuery atau $query
                        foreach ($queryCandidates as $qc) {
                            $chDetail = curl_init("{$ocsBaseUrl}/Orders/GetOrderDetail?orderId=" . urlencode($qc));
                            curl_setopt_array($chDetail, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPGET        => true,
                                CURLOPT_HTTPHEADER     => [
                                    "Authorization: Bearer {$token}",
                                    "Accept: application/json"
                                ],
                                CURLOPT_SSL_VERIFYPEER => false,
                                CURLOPT_SSL_VERIFYHOST => false,
                                CURLOPT_TIMEOUT        => 6
                            ]);
                            $detailRes = curl_exec($chDetail);
                            $detailHttp = curl_getinfo($chDetail, CURLINFO_HTTP_CODE);
                            curl_close($chDetail);

                            if ($detailHttp === 200 && $detailRes) {
                                $detailJson = json_decode($detailRes, true);
                                $od = $detailJson['data']['Data'] ?? $detailJson['Data'] ?? null;
                                if (!empty($od['Id'])) {
                                    $foundOrderId = $od['Id'];
                                    break;
                                }
                            }
                        }

                        // Fallback B: Jika belum ketemu, cari di DTO_Orders berdasarkan Id ATAU TrackingNumber
                        if (!$foundOrderId) {
                            foreach ($queryCandidates as $qc) {
                                $filterById = urlencode("Id eq '{$qc}'");
                                $filterByTrack = urlencode("TrackingNumber eq '{$qc}'");

                                foreach ([$filterById, $filterByTrack] as $filterStr) {
                                    $chOrd = curl_init("{$ocsBaseUrl}/odata/DTO_Orders?\$filter={$filterStr}&\$top=1&\$select=Id,TrackingNumber");
                                    curl_setopt_array($chOrd, [
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_HTTPGET        => true,
                                        CURLOPT_HTTPHEADER     => [
                                            "Authorization: Bearer {$token}",
                                            "Accept: application/json"
                                        ],
                                        CURLOPT_SSL_VERIFYPEER => false,
                                        CURLOPT_SSL_VERIFYHOST => false,
                                        CURLOPT_TIMEOUT        => 5
                                    ]);
                                    $ordRes = curl_exec($chOrd);
                                    curl_close($chOrd);
                                    $ordJson = json_decode($ordRes, true);
                                    if (!empty($ordJson['value'][0]['Id'])) {
                                        $foundOrderId = $ordJson['value'][0]['Id'];
                                        break 2;
                                    }
                                }
                        }
                    }


                    // Langkah C: Cari di DTO_ReturnOrder (Tabel Retur OCS, cepat 0.5s)
                    if (!$foundOrderId) {
                        foreach ($queryCandidates as $qc) {
                            $filterRetUrl = "{$ocsBaseUrl}/odata/DTO_ReturnOrder?\$filter=" . urlencode("TrackingNumber eq '{$qc}' or ReturnId eq '{$qc}' or SalesOrderId eq '{$qc}'") . "&\$top=1";
                            $chRet = curl_init($filterRetUrl);
                            curl_setopt_array($chRet, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPGET        => true,
                                CURLOPT_HTTPHEADER     => [
                                    "Authorization: Bearer {$token}",
                                    "Accept: application/json"
                                ],
                                CURLOPT_SSL_VERIFYPEER => false,
                                CURLOPT_SSL_VERIFYHOST => false,
                                CURLOPT_TIMEOUT        => 6
                            ]);
                            $retRes = curl_exec($chRet);
                            curl_close($chRet);
                            $retJson = json_decode($retRes, true);
                            if (!empty($retJson['value'][0])) {
                                $foundOrderId = $retJson['value'][0]['SalesOrderId'] ?? $retJson['value'][0]['ReturnId'] ?? null;
                                if ($foundOrderId) break;
                            }
                        }
                    }

                    // Langkah D: Ambil GetOrderDetail lengkap jika orderId ditemukan
                    if ($foundOrderId) {
                        $chFinalDetail = curl_init("{$ocsBaseUrl}/Orders/GetOrderDetail?orderId=" . urlencode($foundOrderId));
                        curl_setopt_array($chFinalDetail, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPGET        => true,
                            CURLOPT_HTTPHEADER     => [
                                "Authorization: Bearer {$token}",
                                "Accept: application/json"
                            ],
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                            CURLOPT_TIMEOUT        => 8
                        ]);
                        $finalRes = curl_exec($chFinalDetail);
                        curl_close($chFinalDetail);
                        $finalJson = json_decode($finalRes, true);
                        $od = $finalJson['data']['Data'] ?? $finalJson['Data'] ?? [];

                        if (!empty($od)) {
                            $payment = $od['Payment'] ?? [];
                            $origProdPrice = (float)($payment['OriginalTotalProductPrice'] ?? 0);
                            $sellerDisc = (float)($payment['SellerDiscount'] ?? 0);
                            $platformDisc = (float)($payment['PlatformDiscount'] ?? 0);
                            $netProdPrice = (float)($payment['TotalProductPrice'] ?? ($origProdPrice - $sellerDisc));
                            if ($netProdPrice <= 0 && $origProdPrice > 0) $netProdPrice = $origProdPrice;

                            $origShipFee = (float)($payment['OriginalShippingFee'] ?? 0);
                            $shipFee = (float)($payment['ShippingFee'] ?? $origShipFee);
                            $serviceFee = (float)($payment['ServiceFee'] ?? 0);
                            $subtotal = (float)($payment['SubTotal'] ?? $netProdPrice);
                            $totalAmount = (float)($payment['TotalAmount'] ?? 0);
                            if ($totalAmount <= 0) {
                                $totalAmount = $subtotal > 0 ? ($subtotal + $shippingFee + $serviceFee) : $origProdPrice;
                            }

                            // Ekstraksi Rincian SKU Produk dari Details (Format Resmi OCS)
                            $rawDetails = $od['Details'] ?? $od['Items'] ?? [];
                            $parsedItems = [];
                            $itemNames = [];
                            $primarySellerSku = null;

                            foreach ($rawDetails as $det) {
                                $pSku = trim($det['SellerSku'] ?? $det['SkuId'] ?? '');
                                $pName = trim($det['ProductName'] ?? '');
                                $sName = trim($det['SkuName'] ?? '');
                                $pQty = (int)($det['Qty'] ?? 1);

                                if (!$primarySellerSku && !empty($pSku)) $primarySellerSku = $pSku;
                                $itemNames[] = ($pSku ? "[{$pSku}] " : "") . $pName . ($sName ? " ({$sName})" : "") . " (x{$pQty})";

                                $parsedItems[] = [
                                    'sku_id'            => trim($det['SkuId'] ?? ''),
                                    'seller_sku'        => $pSku,
                                    'product_name'      => $pName,
                                    'sku_name'          => $sName,
                                    'qty'               => $pQty,
                                    'original_price'    => (float)($det['OriginalPrice'] ?? 0),
                                    'sale_price'        => (float)($det['SalePrice'] ?? 0),
                                    'seller_discount'   => (float)($det['SellerDiscount'] ?? 0),
                                    'platform_discount' => (float)($det['PlatformDiscount'] ?? 0),
                                    'subtotal'          => (float)($det['SubTotal'] ?? 0)
                                ];
                            }
                            $prodText = !empty($itemNames) ? implode(', ', $itemNames) : ($od['ProductName'] ?? '');

                            $customer = $od['Customer'] ?? [];

                            $orderData = [
                                'Id'                     => $od['Id'] ?? $foundOrderId,
                                'TrackingNumber'         => $od['TrackingNumber'] ?? '',
                                'PlatformId'             => $od['PlatformId'] ?? null,
                                'CommercePlatform'       => $od['CommercePlatform'] ?? '',
                                'ShopName'               => $od['ShopName'] ?? '',
                                'ShippingProvider'       => trim(($od['ShippingProvider'] ?? '') . ' ' . ($od['DeliveryOptionName'] ?? '')),
                                'StatusCode'             => $od['StatusCode'] ?? null,
                                'StatusName'             => $od['StatusName'] ?? '',
                                'ProductName'            => $prodText,
                                'SellerSku'              => $primarySellerSku,
                                'TotalQtyOrder'          => $od['TotalQtyOrder'] ?? count($parsedItems),
                                'PackagePrice'           => $totalAmount,
                                'PackagePriceFormatted'  => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
                                'OriginalPrice'          => $origProdPrice,
                                'SellerDiscount'         => $sellerDisc,
                                'PlatformDiscount'       => $platformDisc,
                                'ShippingFee'            => $shippingFee,
                                'ShippingFeeFormatted'   => $shippingFee > 0 ? 'Rp ' . number_format($shippingFee, 0, ',', '.') : 'Rp 0',
                                'ServiceFee'             => $serviceFee,
                                'SubTotal'               => $subtotal,
                                'TotalClaimAmount'       => $totalAmount,
                                'TotalClaimAmountFormatted' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
                                'GMV'                    => $origProdPrice > 0 ? $origProdPrice : $totalAmount,
                                'NMV'                    => $totalAmount,
                                'Details'                => $parsedItems,
                                'Items'                  => $parsedItems,
                                'Customer'               => [
                                    'Name'        => $customer['Name'] ?? '',
                                    'PhoneNumber' => $customer['PhoneNumber'] ?? '',
                                    'FullAddress' => $customer['FullAddress'] ?? ''
                                ],
                                'Payment'                => $payment,
                                'CreatedAt'              => $od['CreatedAt'] ?? '',
                                '_source'                => 'ocs_order_detail'
                            ];

                            // Simpan langsung ke database ocs_orders lokal
                            try {
                                $hasIsSyncedCol = false;
                                try {
                                    $colsOcs = $pdo->query("SHOW COLUMNS FROM ocs_orders")->fetchAll(PDO::FETCH_COLUMN);
                                    if (!in_array('is_synced_to_local', $colsOcs)) {
                                        try { $pdo->exec("ALTER TABLE ocs_orders ADD COLUMN is_synced_to_local TINYINT(1) DEFAULT 0 AFTER raw_payload"); $colsOcs[] = 'is_synced_to_local'; } catch (Exception $eCol) {}
                                    }
                                    $hasIsSyncedCol = in_array('is_synced_to_local', $colsOcs);
                                } catch (Exception $eCols) {}

                                $colSyncPart = $hasIsSyncedCol ? ", is_synced_to_local" : "";
                                $valSyncPart = $hasIsSyncedCol ? ", 1" : "";

                                $stmtUpsert = $pdo->prepare("
                                    INSERT INTO ocs_orders (
                                        order_id, tracking_number, platform_id, commerce_platform, 
                                        shop_name, shipping_provider, status_code, status_name, product_name, seller_sku,
                                        total_qty, package_price, original_price, seller_discount, platform_discount,
                                        shipping_fee, service_fee, subtotal, total_amount, gmv, nmv,
                                        customer_name, customer_phone, customer_address, order_items_json,
                                        has_packing_video, packing_video_url, order_created_at, raw_payload{$colSyncPart}
                                    ) VALUES (
                                        :order_id, :tracking_number, :platform_id, :commerce_platform, 
                                        :shop_name, :shipping_provider, :status_code, :status_name, :product_name, :seller_sku,
                                        :total_qty, :package_price, :original_price, :seller_discount, :platform_discount,
                                        :shipping_fee, :service_fee, :subtotal, :total_amount, :gmv, :nmv,
                                        :customer_name, :customer_phone, :customer_address, :order_items_json,
                                        0, NULL, :order_created_at, :raw_payload{$valSyncPart}
                                    )
                                    ON DUPLICATE KEY UPDATE 
                                        tracking_number   = COALESCE(VALUES(tracking_number), tracking_number),
                                        platform_id       = VALUES(platform_id),
                                        commerce_platform = VALUES(commerce_platform),
                                        shop_name         = VALUES(shop_name),
                                        shipping_provider = VALUES(shipping_provider),
                                        status_code       = VALUES(status_code),
                                        status_name       = VALUES(status_name),
                                        product_name      = VALUES(product_name),
                                        seller_sku        = VALUES(seller_sku),
                                        total_qty         = VALUES(total_qty),
                                        package_price     = VALUES(package_price),
                                        original_price    = VALUES(original_price),
                                        seller_discount   = VALUES(seller_discount),
                                        platform_discount = VALUES(platform_discount),
                                        shipping_fee      = VALUES(shipping_fee),
                                        service_fee       = VALUES(service_fee),
                                        subtotal          = VALUES(subtotal),
                                        total_amount      = VALUES(total_amount),
                                        gmv               = VALUES(gmv),
                                        nmv               = VALUES(nmv),
                                        customer_name     = VALUES(customer_name),
                                        customer_phone    = VALUES(customer_phone),
                                        customer_address  = VALUES(customer_address),
                                        order_items_json  = VALUES(order_items_json),
                                        order_created_at  = VALUES(order_created_at),
                                        raw_payload       = VALUES(raw_payload)
                                ");
                                $stmtUpsert->execute([
                                    ':order_id'          => $orderData['Id'],
                                    ':tracking_number'   => $orderData['TrackingNumber'] ?: null,
                                    ':platform_id'       => $orderData['PlatformId'] ?: null,
                                    ':commerce_platform' => $orderData['CommercePlatform'] ?: null,
                                    ':shop_name'         => $orderData['ShopName'] ?: null,
                                    ':shipping_provider' => $orderData['ShippingProvider'] ?: null,
                                    ':status_code'       => $orderData['StatusCode'] ?: null,
                                    ':status_name'       => $orderData['StatusName'] ?: null,
                                    ':product_name'      => $orderData['ProductName'] ?: null,
                                    ':seller_sku'        => $orderData['SellerSku'] ?: null,
                                    ':total_qty'         => (int)($orderData['TotalQtyOrder'] ?: 1),
                                    ':package_price'     => (float)($orderData['PackagePrice'] ?: 0),
                                    ':original_price'    => (float)($orderData['OriginalPrice'] ?: 0),
                                    ':seller_discount'   => (float)($orderData['SellerDiscount'] ?: 0),
                                    ':platform_discount' => (float)($orderData['PlatformDiscount'] ?: 0),
                                    ':shipping_fee'      => (float)($orderData['ShippingFee'] ?: 0),
                                    ':service_fee'       => (float)($orderData['ServiceFee'] ?: 0),
                                    ':subtotal'          => (float)($orderData['SubTotal'] ?: 0),
                                    ':total_amount'      => (float)($orderData['TotalAmount'] ?: 0),
                                    ':gmv'               => (float)($orderData['GMV'] ?: 0),
                                    ':nmv'               => (float)($orderData['NMV'] ?: 0),
                                    ':customer_name'     => $orderData['Customer']['Name'] ?: null,
                                    ':customer_phone'    => $orderData['Customer']['PhoneNumber'] ?: null,
                                    ':customer_address'  => $orderData['Customer']['FullAddress'] ?: null,
                                    ':order_items_json'  => !empty($parsedItems) ? json_encode($parsedItems, JSON_UNESCAPED_UNICODE) : null,
                                    ':order_created_at'  => $orderData['CreatedAt'] ?: null,
                                    ':raw_payload'       => json_encode($od, JSON_UNESCAPED_UNICODE)
                                ]);
                            } catch (Exception $eUpsert) {}
                        }
                    }
                }
            }
        }
    } catch (Exception $ocsErr) {
            // Lanjutkan jika OCS tidak merespon
        }
    }


    // 3. KUMPULKAN SEMUA KANDIDAT IDENTIFIER (Resi, Order ID, Barcode Paket, dsb)
    $candidateIds = array_values(array_unique(array_filter([
        trim($query),
        !empty($orderData['TrackingNumber']) ? trim($orderData['TrackingNumber']) : null,
        !empty($orderData['Id']) ? trim($orderData['Id']) : null,
    ])));

    // Cek juga dari tabel ocs_orders lokal apakah ada mapping order_id <-> tracking_number
    if (!empty($candidateIds)) {
        try {
            $inPlaceholders = implode(',', array_fill(0, count($candidateIds), '?'));
            $stmtMap = $pdo->prepare("SELECT order_id, tracking_number FROM ocs_orders WHERE order_id IN ($inPlaceholders) OR tracking_number IN ($inPlaceholders)");
            $stmtMap->execute(array_merge($candidateIds, $candidateIds));
            $mappedOrders = $stmtMap->fetchAll(PDO::FETCH_ASSOC);
            foreach ($mappedOrders as $m) {
                if (!empty($m['order_id'])) $candidateIds[] = trim($m['order_id']);
                if (!empty($m['tracking_number'])) $candidateIds[] = trim($m['tracking_number']);
            }
            $candidateIds = array_values(array_unique(array_filter($candidateIds)));
        } catch (Exception $eMap) {}
    }

    // CROSS-REFERENCE DATA LOKAL RECEIVING INBOUND (Tanda Terima Ekspedisi & Kurir)
    $receptionData = null;
    if (!empty($candidateIds)) {
        $recPlaceholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $stmtRec = $pdo->prepare("
            SELECT er.*, rp.package_barcode, rp.scanned_at AS package_scanned_at
            FROM reception_packages rp
            JOIN expedition_receptions er ON er.id = rp.reception_id
            WHERE rp.package_barcode IN ($recPlaceholders)
               OR er.receipt_number IN ($recPlaceholders)
            ORDER BY rp.id DESC
            LIMIT 1
        ");
        $stmtRec->execute(array_merge($candidateIds, $candidateIds));
        $receptionRow = $stmtRec->fetch(PDO::FETCH_ASSOC);
        if ($receptionRow) {
            $receptionData = [
                'id'             => $receptionRow['id'],
                'receipt_number' => $receptionRow['receipt_number'],
                'expedition'     => $receptionRow['expedition'],
                'courier_name'   => $receptionRow['courier_name'],
                'vehicle_no'     => $receptionRow['vehicle_no'] ?? null,
                'operator_name'  => $receptionRow['operator_name'],
                'package_barcode'=> $receptionRow['package_barcode'],
                'photo_path'     => $receptionRow['photo_path'] ?? null,
                'package_photos' => $receptionRow['package_photos'] ? json_decode($receptionRow['package_photos'], true) : [],
                'scanned_at'     => $receptionRow['package_scanned_at'] ?: $receptionRow['created_at'],
                'created_at'     => $receptionRow['created_at']
            ];
            if (!empty($receptionRow['package_barcode'])) $candidateIds[] = trim($receptionRow['package_barcode']);
            if (!empty($receptionRow['receipt_number'])) $candidateIds[] = trim($receptionRow['receipt_number']);
            $candidateIds = array_values(array_unique(array_filter($candidateIds)));
        }
    }

    // 4. CROSS-REFERENCE DATA LOKAL INBOUND UNBOXING (Rekaman Video Unboxing & Detail Barang)
    $unboxingData = null;
    $unboxRow = null;
    if (!empty($candidateIds)) {
        $unboxPlaceholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $stmtUnbox = $pdo->prepare("
            SELECT rs.* 
            FROM return_sessions rs
            WHERE rs.invoice_number IN ($unboxPlaceholders)
            ORDER BY rs.id DESC
            LIMIT 1
        ");
        $stmtUnbox->execute($candidateIds);
        $unboxRow = $stmtUnbox->fetch(PDO::FETCH_ASSOC);

        // Jika belum ketemu, coba normalisasi: strip non-alphanumeric lalu bandingkan
        if (!$unboxRow) {
            foreach ($candidateIds as $cid) {
                if (strlen($cid) >= 6) {
                    // Coba exact LIKE partial match
                    $stmtUnboxLike = $pdo->prepare("
                        SELECT rs.* 
                        FROM return_sessions rs
                        WHERE rs.invoice_number LIKE ?
                           OR REPLACE(REPLACE(rs.invoice_number, '-', ''), ' ', '') = ?
                           OR REPLACE(REPLACE(?, '-', ''), ' ', '') = REPLACE(REPLACE(rs.invoice_number, '-', ''), ' ', '')
                        ORDER BY rs.id DESC
                        LIMIT 1
                    ");
                    $cidAlnum = preg_replace('/[^a-zA-Z0-9]/', '', $cid);
                    $stmtUnboxLike->execute(["%{$cid}%", $cidAlnum, $cid]);
                    $unboxRow = $stmtUnboxLike->fetch(PDO::FETCH_ASSOC);
                    if ($unboxRow) break;
                }
            }
        }

        // Jika masih belum ketemu, coba cari dari alphaNumQuery (versi tanpa separator)
        if (!$unboxRow && !empty($alphaNumQuery) && strlen($alphaNumQuery) >= 6) {
            $stmtAlnum = $pdo->prepare("
                SELECT rs.* 
                FROM return_sessions rs
                WHERE REPLACE(REPLACE(REPLACE(rs.invoice_number, '-', ''), ' ', ''), '/', '') = ?
                ORDER BY rs.id DESC
                LIMIT 1
            ");
            $stmtAlnum->execute([$alphaNumQuery]);
            $unboxRow = $stmtAlnum->fetch(PDO::FETCH_ASSOC);
        }
    }

    $items = [];
    if ($unboxRow) {
        $stmtItems = $pdo->prepare("SELECT * FROM return_items WHERE session_id = :sid ORDER BY id ASC");
        $stmtItems->execute([':sid' => $unboxRow['id']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $unboxingData = [
            'session_id'     => $unboxRow['id'],
            'invoice_number' => $unboxRow['invoice_number'],
            'customer_name'  => $unboxRow['customer_name'],
            'expedition'     => $unboxRow['expedition'],
            'operator_name'  => $unboxRow['operator_name'],
            'status'         => $unboxRow['status'],
            'total_items'    => $unboxRow['total_items'],
            'total_good'     => $unboxRow['total_good'],
            'total_damaged'  => $unboxRow['total_damaged'],
            'notes'          => $unboxRow['notes'],
            'video_path'     => $unboxRow['video_path'],
            'package_photo'  => $unboxRow['package_photo'] ?? null,
            'product_photo'  => $unboxRow['product_photo'] ?? null,
            'photos'         => $unboxRow['photos'] ? json_decode($unboxRow['photos'], true) : [],
            'created_at'     => $unboxRow['created_at'],
            'items'          => $items
        ];
    }

    // 5. GATHER SEMUA FOTO BUKTI (FOTO BARANG RUSAK WAJIB PERTAMA / UTAMA, PRODUK, PAKET, SERAH TERIMA KURIR)
    $photosList = [];

    // Foto Tiap Item Unboxing (Terutama Barang Rusak!)
    if (!empty($items)) {
        foreach ($items as $it) {
            if (!empty($it['photo_path'])) {
                $condUpper = strtoupper(trim($it['condition'] ?? ''));
                $typeUpper = strtoupper(trim($it['type'] ?? ''));
                $isDmg = ($condUpper !== 'GOOD' && $condUpper !== 'BAGUS' && $condUpper !== '') || 
                          ($typeUpper !== 'GOOD' && $typeUpper !== 'BAGUS' && $typeUpper !== '') || 
                          !empty($it['damage_reason']);
                $pQty = (int)($it['qty'] ?? 1);
                $photosList[] = [
                    'type'       => $isDmg ? 'damaged' : 'item',
                    'is_damaged' => $isDmg,
                    'badge'      => $isDmg ? 'Barang Rusak' : 'Foto Item',
                    'title'      => ($isDmg ? '⚠️ Foto Barang Rusak: ' : 'Foto Item: ') . ($it['product_name'] ?: $it['barcode']) . " (x{$pQty})" . ($it['damage_reason'] ? ' - ' . $it['damage_reason'] : ''),
                    'url'        => $it['photo_path']
                ];
            }
        }
    }

    // Foto Tambahan Unboxing dari return_sessions.photos
    if (!empty($unboxRow['photos'])) {
        $extraPhotos = is_array($unboxRow['photos']) ? $unboxRow['photos'] : json_decode($unboxRow['photos'], true);
        if (is_array($extraPhotos)) {
            foreach ($extraPhotos as $idx => $ep) {
                $pPath = is_array($ep) ? ($ep['path'] ?? '') : $ep;
                $pType = is_array($ep) ? ($ep['type'] ?? 'extra') : 'extra';
                $pTitle = is_array($ep) ? ($ep['title'] ?? '') : '';
                $isDmg = ($pType === 'damaged' || stripos($pTitle, 'rusak') !== false || stripos($pType, 'rusak') !== false);
                
                if ($pPath && $pPath !== ($unboxRow['package_photo'] ?? '') && $pPath !== ($unboxRow['product_photo'] ?? '')) {
                    $photosList[] = [
                        'type'       => $pType,
                        'is_damaged' => $isDmg,
                        'badge'      => $isDmg ? 'Barang Rusak' : ($pType === 'package' ? 'Paket Retur' : 'Produk Retur'),
                        'title'      => !empty($pTitle) ? $pTitle : ($isDmg ? 'Foto Barang Rusak #' . ($idx + 1) : 'Foto Dokumentasi #' . ($idx + 1)),
                        'url'        => $pPath
                    ];
                }
            }
        }
    }

    // Foto Produk Unboxing
    if (!empty($unboxRow['product_photo'])) {
        $already = false;
        foreach ($photosList as $pl) { if ($pl['url'] === $unboxRow['product_photo']) { $already = true; break; } }
        if (!$already) {
            $isUnboxDamaged = ((int)($unboxRow['total_damaged'] ?? 0) > 0);
            $photosList[] = [
                'type'       => $isUnboxDamaged ? 'damaged' : 'product',
                'is_damaged' => $isUnboxDamaged,
                'badge'      => $isUnboxDamaged ? 'Barang Rusak' : 'Produk Retur',
                'title'      => $isUnboxDamaged ? 'Foto Bukti Produk Rusak Saat Unboxing' : 'Foto Produk Saat Unboxing',
                'url'        => $unboxRow['product_photo']
            ];
        }
    }

    // Foto Paket Unboxing
    if (!empty($unboxRow['package_photo'])) {
        $already = false;
        foreach ($photosList as $pl) { if ($pl['url'] === $unboxRow['package_photo']) { $already = true; break; } }
        if (!$already) {
            $photosList[] = [
                'type'       => 'package',
                'is_damaged' => false,
                'badge'      => 'Paket Retur',
                'title'      => 'Foto Fisik Paket Saat Unboxing',
                'url'        => $unboxRow['package_photo']
            ];
        }
    }

    // Foto Serah Terima Kurir Receiving
    if (!empty($receptionRow['photo_path'])) {
        $photosList[] = [
            'type'       => 'reception',
            'is_damaged' => false,
            'badge'      => 'Kurir Receiving',
            'title'      => 'Foto Serah Terima Kurir: ' . ($receptionRow['courier_name'] ?: $receptionRow['expedition']),
            'url'        => $receptionRow['photo_path']
        ];
    }
    if (!empty($receptionRow['package_photos'])) {
        $recExtra = is_array($receptionRow['package_photos']) ? $receptionRow['package_photos'] : json_decode($receptionRow['package_photos'], true);
        if (is_array($recExtra)) {
            foreach ($recExtra as $idx => $rp) {
                if ($rp && $rp !== ($receptionRow['photo_path'] ?? '')) {
                    $photosList[] = [
                        'type'       => 'reception',
                        'is_damaged' => false,
                        'badge'      => 'Serah Terima',
                        'title'      => 'Foto Paket Serah Terima Ekspedisi #' . ($idx + 1),
                        'url'        => $rp
                    ];
                }
            }
        }
    }

    // URUTKAN FOTO: FOTO BARANG RUSAK WAJIB BERADA DI URUTAN PERTAMA UNTUK DOKUMEN KLAIM!
    usort($photosList, function($a, $b) {
        $aScore = (!empty($a['is_damaged']) || $a['badge'] === 'Barang Rusak' || stripos($a['title'], 'rusak') !== false) ? 0 : 1;
        $bScore = (!empty($b['is_damaged']) || $b['badge'] === 'Barang Rusak' || stripos($b['title'], 'rusak') !== false) ? 0 : 1;
        if ($aScore !== $bScore) {
            return $aScore <=> $bScore;
        }
        return 0;
    });

    // 6. JIKA ORDER DATA BELUM ADA DARI OCS, SINTESIS DATA DARI HASIL SCAN LOKAL (UNBOXING & RECEIVING)
    if (!$orderData && ($unboxingData || $receptionData)) {
        $prodSummary = 'Barang Retur';
        if (!empty($items)) {
            $names = array_filter(array_map(function($i) { return $i['product_name'] ?: $i['barcode']; }, $items));
            if (!empty($names)) $prodSummary = implode(', ', array_slice($names, 0, 3));
        }

        $orderData = [
            'Id'                     => $unboxingData['invoice_number'] ?? $query,
            'TrackingNumber'         => $receptionData['package_barcode'] ?? $unboxingData['invoice_number'] ?? $query,
            'PlatformId'             => null,
            'CommercePlatform'       => 'Marketplace',
            'ShopName'               => '-',
            'ShippingProvider'       => $unboxingData['expedition'] ?? $receptionData['expedition'] ?? '-',
            'StatusCode'             => null,
            'ProductName'            => $prodSummary,
            'TotalQtyOrder'          => $unboxingData['total_items'] ?? 1,
            'PackagePrice'           => 0,
            'PackagePriceFormatted'  => 'Rp -',
            'ShippingFee'            => 0,
            'ShippingFeeFormatted'   => 'Rp -',
            'TotalClaimAmount'       => 0,
            'TotalClaimAmountFormatted' => 'Rp -',
            'GMV'                    => 0,
            'NMV'                    => 0,
            'CreatedAt'              => $unboxingData['created_at'] ?? $receptionData['created_at'] ?? date('Y-m-d H:i:s'),
            '_source'                => 'local_database'
        ];
    }

    if (!$orderData && !$receptionData && !$unboxingData) {
        // Bantu user mengerti kenapa tidak ketemu
        echo json_encode([
            'success'        => false,
            'ocs_not_found'  => true,
            'message'        => 'Data tidak ditemukan. Pastikan nomor resi/order sesuai dengan yang tercatat di Inbound Unboxing atau OCS. Coba juga cari tanpa tanda hubung (-) atau dengan format lain.',
            'query'          => $query,
            'clean_query'    => $cleanQuery,
            'alpha_query'    => $alphaNumQuery,
            'hint'           => 'Jika baru saja melakukan unboxing, pastikan session sudah tersimpan. Cek juga apakah nomor resi di unboxing menggunakan format yang sama.'
        ]);
        exit;
    }
    
    // Tandai apakah data OCS berhasil ditemukan (untuk info user di halaman klaim)
    $ocsFound = !empty($orderData) && ($orderData['_source'] ?? '') !== 'local_database';

    // 7. EVALUASI KELAYAKAN KLAIM: HANYA PAKET DENGAN TYPE ATAU KONDISI BUKAN GOOD / BAGUS
    $isClaimable = false;
    $claimEligibilityReason = '';
    $damagedCount = 0;
    $damageDetails = [];

    if ($unboxingData) {
        $damagedCount = (int)($unboxingData['total_damaged'] ?? 0);
        foreach ($unboxingData['items'] as $it) {
            $c = strtoupper(trim($it['condition'] ?? ''));
            $t = strtoupper(trim($it['type'] ?? ''));
            $r = trim($it['damage_reason'] ?? '');
            if (($c !== '' && $c !== 'GOOD' && $c !== 'BAGUS') || 
                ($t !== '' && $t !== 'GOOD' && $t !== 'BAGUS') || 
                $r !== '') {
                $isClaimable = true;
                $damageDetails[] = $it['product_name'] . ($r ? " ({$r})" : " ({$c})");
            }
        }
        $sessNotes = strtolower($unboxingData['notes'] ?? '');
        if ($sessNotes && (strpos($sessNotes, 'rusak') !== false || strpos($sessNotes, 'pecah') !== false || strpos($sessNotes, 'bocor') !== false || strpos($sessNotes, 'cacat') !== false || strpos($sessNotes, 'hilang') !== false)) {
            $isClaimable = true;
            if (empty($damageDetails)) $damageDetails[] = $unboxingData['notes'];
        }
        if ($damagedCount > 0) $isClaimable = true;

        if ($isClaimable) {
            $cnt = $damagedCount > 0 ? $damagedCount : (count($damageDetails) > 0 ? count($damageDetails) : 1);
            $claimEligibilityReason = "Kondisi unboxing tercatat RUSAK / CACAT ({$cnt} item). Memenuhi syarat untuk diajukan klaim / banding.";
        } else {
            $claimEligibilityReason = "Kondisi unboxing tercatat BAGUS (GOOD). Paket retur normal, BUKAN paket klaim kerusakan.";
        }
    } else {
        $claimEligibilityReason = "Paket belum di-unboxing di stasiun gudang.";
    }

    // 8. EVALUASI KESIAPAN BERKAS KLAIM (Claim Dossier Readiness)
    $readiness = [
        'has_order'       => !empty($orderData),
        'has_tracking'    => !empty($orderData['TrackingNumber']) || !empty($receptionData['package_barcode']),
        'has_reception'   => !empty($receptionData),
        'has_unboxing'    => !empty($unboxingData),
        'has_unbox_video' => !empty($unboxingData['video_path']),
        'has_photos'      => count($photosList) > 0,
        'is_damaged'      => $isClaimable,
        'score_percent'   => 0
    ];

    $score = 0;
    if ($readiness['has_order']) $score += 25;
    if ($readiness['has_reception']) $score += 25;
    if ($readiness['has_unbox_video']) $score += 25;
    if ($readiness['has_photos']) $score += 25;
    $readiness['score_percent'] = $score;

    echo json_encode([
        'success'                  => true,
        'query'                    => $query,
        'ocs_found'                => $ocsFound,
        'ocs_source'               => $orderData['_source'] ?? 'unknown',
        'is_claimable'             => $isClaimable,
        'claim_eligibility_reason' => $claimEligibilityReason,
        'damaged_count'            => $damagedCount,
        'damage_details'           => $damageDetails,
        'order'                    => $orderData,
        'reception'                => $receptionData,
        'unboxing'                 => $unboxingData,
        'photos'                   => $photosList,
        'claim_readiness'          => $readiness,
        'timestamp'                => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'query'   => $query
    ]);
}
