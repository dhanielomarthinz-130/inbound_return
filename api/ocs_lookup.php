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
                       GROUP_CONCAT(DISTINCT CASE WHEN ri.damage_reason IS NOT NULL AND ri.damage_reason != '' THEN ri.damage_reason ELSE NULL END SEPARATOR '; ') as damage_reasons,
                       MAX(o.package_price) as package_price, 
                       MAX(o.commerce_platform) as commerce_platform, 
                       MAX(o.shop_name) as shop_name, 
                       MAX(o.has_packing_video) as has_packing_video
                FROM return_sessions rs
                LEFT JOIN return_items ri ON ri.session_id = rs.id
                LEFT JOIN ocs_orders o ON (o.order_id = rs.invoice_number OR o.tracking_number = rs.invoice_number)
                GROUP BY rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.status, 
                         rs.total_items, rs.total_good, rs.total_damaged, rs.notes, rs.video_path, rs.created_at
                HAVING rs.total_damaged > 0 OR damaged_items_count > 0
                ORDER BY rs.id DESC
                LIMIT 50
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
                       GROUP_CONCAT(DISTINCT CASE WHEN ri.damage_reason IS NOT NULL AND ri.damage_reason != '' THEN ri.damage_reason ELSE NULL END SEPARATOR '; ') as damage_reasons,
                       0 as package_price, 
                       NULL as commerce_platform, 
                       NULL as shop_name, 
                       0 as has_packing_video
                FROM return_sessions rs
                LEFT JOIN return_items ri ON ri.session_id = rs.id
                GROUP BY rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.status, 
                         rs.total_items, rs.total_good, rs.total_damaged, rs.notes, rs.video_path, rs.created_at
                HAVING rs.total_damaged > 0 OR damaged_items_count > 0
                ORDER BY rs.id DESC
                LIMIT 50
            ";
        }

        $stmtDamaged = $pdo->query($sqlDamaged);
        $candidates = $stmtDamaged->fetchAll(PDO::FETCH_ASSOC);

        // Format harga paket
        foreach ($candidates as &$c) {
            $price = (float)($c['package_price'] ?? 0);
            $c['package_price_formatted'] = $price > 0 ? 'Rp ' . number_format($price, 0, ',', '.') : '-';
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

$ocsBaseUrl = 'https://ocs.iegsystem.id';
$ocsUser    = 'ADMIN';
$ocsPass    = 'ADMIN';
$ocsCompany = 'EJI_WMS';

try {
    $orderData = null;

    // 1. Cek cache lokal di tabel ocs_orders terlebih dahulu (jika bukan mode force refresh)
    if (!$refresh) {
        $stmtCache = $pdo->prepare("
            SELECT * FROM ocs_orders 
            WHERE order_id = :q1 OR tracking_number = :q2 
            LIMIT 1
        ");
        $stmtCache->execute([':q1' => $query, ':q2' => $query]);
        $cached = $stmtCache->fetch(PDO::FETCH_ASSOC);

        if ($cached) {
            $pkgPrice = (float)($cached['package_price'] > 0 ? $cached['package_price'] : ($cached['nmv'] > 0 ? $cached['nmv'] : $cached['gmv']));
            $shippingFee = (float)($cached['shipping_fee'] ?? 0);
            $totalClaim = $pkgPrice + $shippingFee;
            $orderData = [
                'Id'                     => $cached['order_id'],
                'TrackingNumber'         => $cached['tracking_number'],
                'PlatformId'             => $cached['platform_id'],
                'CommercePlatform'       => $cached['commerce_platform'],
                'ShopName'               => $cached['shop_name'],
                'ShippingProvider'       => $cached['shipping_provider'],
                'StatusCode'             => $cached['status_code'],
                'ProductName'            => $cached['product_name'],
                'TotalQtyOrder'          => $cached['total_qty'],
                'PackagePrice'           => $pkgPrice,
                'PackagePriceFormatted'  => 'Rp ' . number_format($pkgPrice, 0, ',', '.'),
                'ShippingFee'            => $shippingFee,
                'ShippingFeeFormatted'   => $shippingFee > 0 ? 'Rp ' . number_format($shippingFee, 0, ',', '.') : 'Rp 0',
                'TotalClaimAmount'       => $totalClaim,
                'TotalClaimAmountFormatted' => $totalClaim > 0 ? 'Rp ' . number_format($totalClaim, 0, ',', '.') : 'Rp ' . number_format($pkgPrice, 0, ',', '.'),
                'GMV'                    => (float)($cached['gmv'] ?? 0),
                'NMV'                    => (float)($cached['nmv'] ?? 0),
                'CreatedAt'              => $cached['order_created_at'],
                '_source'                => 'local_cache'
            ];
        }
    }

    // 2. Jika belum ada di cache atau di-refresh, query langsung ke OCS IEG System
    if (!$orderData) {
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
                    // 1. Coba panggil langsung endpoint resmi detail order OCS: /Orders/GetOrderDetail
                    $chDetail = curl_init("{$ocsBaseUrl}/Orders/GetOrderDetail?orderId=" . urlencode($query));
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
                        if ($od) {
                            $payment = $od['Payment'] ?? [];
                            $origProdPrice = (float)($payment['OriginalTotalProductPrice'] ?? 0);
                            $sellerDisc = (float)($payment['SellerDiscount'] ?? 0);
                            $platformDisc = (float)($payment['PlatformDiscount'] ?? 0);
                            $netProdPrice = (float)($payment['TotalProductPrice'] ?? ($origProdPrice - $sellerDisc));
                            if ($netProdPrice <= 0 && $origProdPrice > 0) $netProdPrice = $origProdPrice;

                            $origShipFee = (float)($payment['OriginalShippingFee'] ?? 0);
                            $shipDisc = (float)(($payment['ShippingFeePlatformDiscount'] ?? 0) + ($payment['ShippingFeeSellerDiscount'] ?? 0));
                            $netShipFee = max(0, $origShipFee - $shipDisc);
                            $serviceFee = (float)($payment['ServiceFee'] ?? 0);
                            $totalAmount = (float)($payment['TotalAmount'] ?? ($netProdPrice + $netShipFee + $serviceFee));

                            // Nama & SKU Produk
                            $itemNames = [];
                            if (!empty($od['Items']) && is_array($od['Items'])) {
                                foreach ($od['Items'] as $it) {
                                    $pTitle = $it['ProductName'] ?? $it['ItemName'] ?? '';
                                    $pSku = $it['Sku'] ?? $it['SellerSku'] ?? '';
                                    $pQty = (int)($it['Qty'] ?? 1);
                                    $itemNames[] = ($pSku ? "[{$pSku}] " : "") . $pTitle . " (x{$pQty})";
                                }
                            }
                            $prodText = !empty($itemNames) ? implode(', ', $itemNames) : ($od['ProductName'] ?? '');

                            $orderData = [
                                'Id'                     => $od['Id'] ?? $query,
                                'TrackingNumber'         => $od['TrackingNumber'] ?? '',
                                'PlatformId'             => $od['PlatformId'] ?? null,
                                'CommercePlatform'       => $od['CommercePlatform'] ?? '',
                                'ShopName'               => $od['ShopName'] ?? '',
                                'ShippingProvider'       => trim(($od['ShippingProvider'] ?? '') . ' ' . ($od['DeliveryOptionName'] ?? '')),
                                'StatusCode'             => $od['StatusCode'] ?? null,
                                'ProductName'            => $prodText,
                                'TotalQtyOrder'          => $od['TotalQtyOrder'] ?? count($od['Items'] ?? [1]),
                                'PackagePrice'           => $netProdPrice > 0 ? $netProdPrice : $origProdPrice,
                                'PackagePriceFormatted'  => 'Rp ' . number_format($netProdPrice > 0 ? $netProdPrice : $origProdPrice, 0, ',', '.'),
                                'ShippingFee'            => $origShipFee,
                                'ShippingFeeFormatted'   => $origShipFee > 0 ? 'Rp ' . number_format($origShipFee, 0, ',', '.') : 'Rp 0',
                                'TotalClaimAmount'       => $totalAmount,
                                'TotalClaimAmountFormatted' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
                                'GMV'                    => $origProdPrice,
                                'NMV'                    => $netProdPrice,
                                'Payment'                => $payment,
                                'CreatedAt'              => $od['CreatedAt'] ?? '',
                                '_source'                => 'ocs_order_detail'
                            ];
                        }
                    }

                    // 2. Jika belum ditemukan, coba cari di DTO_Orders berdasarkan Id
                    if (!$orderData) {
                        $chOrd = curl_init("{$ocsBaseUrl}/odata/DTO_Orders?\$filter=" . urlencode("Id eq '{$query}'") . "&\$top=1");
                        curl_setopt_array($chOrd, [
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
                        $ordRes = curl_exec($chOrd);
                        curl_close($chOrd);
                        $ordJson = json_decode($ordRes, true);

                        // 3. Jika tidak ditemukan berdasarkan Id, cari berdasarkan TrackingNumber
                        if (empty($ordJson['value'][0])) {
                            $chOrdTrack = curl_init("{$ocsBaseUrl}/odata/DTO_Orders?\$filter=" . urlencode("TrackingNumber eq '{$query}'") . "&\$top=1");
                            curl_setopt_array($chOrdTrack, [
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
                            $ordTrackRes = curl_exec($chOrdTrack);
                            curl_close($chOrdTrack);
                            $ordJson = json_decode($ordTrackRes, true);
                        }

                        if (!empty($ordJson['value'][0])) {
                            $raw = $ordJson['value'][0];
                            $shipFee = (float)($raw['ShippingFee'] ?? $raw['ShippingCost'] ?? 0);
                            $orderData = [
                                'Id'                 => $raw['Id'] ?? '',
                                'TrackingNumber'     => $raw['TrackingNumber'] ?? '',
                                'PlatformId'         => $raw['PlatformId'] ?? null,
                                'CommercePlatform'   => $raw['CommercePlatform'] ?? '',
                                'ShopName'           => $raw['ShopName'] ?? '',
                                'ShippingProvider'   => $raw['ShippingProvider'] ?? '',
                                'StatusCode'         => $raw['StatusCode'] ?? null,
                                'ProductName'        => $raw['ProductName'] ?? '',
                                'TotalQtyOrder'      => $raw['TotalQtyOrder'] ?? 1,
                                'ShippingFee'        => $shipFee,
                                'CreatedAt'          => $raw['CreatedAt'] ?? '',
                                '_source'            => 'ocs_api'
                            ];
                        } else {
                            // Coba cari di DTO_ReturnOrder
                            $filterRetUrl = "{$ocsBaseUrl}/odata/DTO_ReturnOrder?\$filter=" . urlencode("TrackingNumber eq '{$query}' or ReturnId eq '{$query}' or SalesOrderId eq '{$query}'") . "&\$top=1";
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
                                CURLOPT_TIMEOUT        => 8
                            ]);
                            $retRes = curl_exec($chRet);
                            curl_close($chRet);
                            $retJson = json_decode($retRes, true);

                            if (!empty($retJson['value'][0])) {
                                $rawRet = $retJson['value'][0];
                                $orderData = [
                                    'Id'                 => $rawRet['SalesOrderId'] ?? $rawRet['ReturnId'],
                                    'TrackingNumber'     => $rawRet['TrackingNumber'] ?? '',
                                    'PlatformId'         => $rawRet['PlatformId'] ?? null,
                                    'CommercePlatform'   => $rawRet['CommercePlatform'] ?? '',
                                    'ShopName'           => $rawRet['ShopName'] ?? '',
                                    'ShippingProvider'   => 'Ekspedisi Marketplace',
                                    'StatusCode'         => 0,
                                    'ProductName'        => $rawRet['ReturnReasonText'] ?? 'Retur: ' . ($rawRet['ReturnReason'] ?? ''),
                                    'TotalQtyOrder'      => 1,
                                    'ShippingFee'        => 0,
                                    'CreatedAt'          => $rawRet['CreatedAt'] ?? '',
                                    'ReturnReason'       => $rawRet['ReturnReason'] ?? '',
                                    'ReturnReasonText'   => $rawRet['ReturnReasonText'] ?? '',
                                    '_source'            => 'ocs_return_order'
                                ];
                            }
                        }
                    }

                    // Ambil GMV & NMV Nilai Paket dari DTO_OrderGmv jika belum ada
                    if ($orderData && empty($orderData['PackagePrice'])) {
                        $orderId = $orderData['Id'];
                        $pkgPrice = 0.0;
                        $gmv = 0.0;
                        $nmv = 0.0;
                        if ($orderId) {
                            $chGmv = curl_init("{$ocsBaseUrl}/odata/DTO_OrderGmv?\$filter=" . urlencode("Id eq '{$orderId}'") . "&\$top=1");
                            curl_setopt_array($chGmv, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPGET        => true,
                                CURLOPT_HTTPHEADER     => [
                                    "Authorization: Bearer {$token}",
                                    "Accept: application/json"
                                ],
                                CURLOPT_SSL_VERIFYPEER => false,
                                CURLOPT_SSL_VERIFYHOST => false,
                                CURLOPT_TIMEOUT        => 4
                            ]);
                            $gmvRes = curl_exec($chGmv);
                            curl_close($chGmv);
                            $gmvJson = json_decode($gmvRes, true);
                            if (!empty($gmvJson['value'][0])) {
                                $gmv = (float)($gmvJson['value'][0]['GMV'] ?? 0);
                                $nmv = (float)($gmvJson['value'][0]['NMV'] ?? 0);
                                $pkgPrice = $nmv > 0 ? $nmv : $gmv;
                            }
                        }

                        $shipFee = (float)($orderData['ShippingFee'] ?? 0);
                        $totalClaim = $pkgPrice + $shipFee;

                        $orderData['PackagePrice'] = $pkgPrice;
                        $orderData['PackagePriceFormatted'] = 'Rp ' . number_format($pkgPrice, 0, ',', '.');
                        $orderData['ShippingFeeFormatted'] = $shipFee > 0 ? 'Rp ' . number_format($shipFee, 0, ',', '.') : 'Rp 0';
                        $orderData['TotalClaimAmount'] = $totalClaim;
                        $orderData['TotalClaimAmountFormatted'] = $totalClaim > 0 ? 'Rp ' . number_format($totalClaim, 0, ',', '.') : 'Rp ' . number_format($pkgPrice, 0, ',', '.');
                        $orderData['GMV'] = $gmv;
                        $orderData['NMV'] = $nmv;
                    }

                    if ($orderData) {
                        // Simpan atau perbarui cache di tabel ocs_orders
                        try {
                            $stmtUpsert = $pdo->prepare("
                                INSERT INTO ocs_orders (
                                    order_id, tracking_number, platform_id, commerce_platform, 
                                    shop_name, shipping_provider, status_code, product_name, 
                                    total_qty, package_price, gmv, nmv, has_packing_video, packing_video_url, order_created_at, raw_payload
                                ) VALUES (
                                    :order_id, :tracking_number, :platform_id, :commerce_platform, 
                                    :shop_name, :shipping_provider, :status_code, :product_name, 
                                    :total_qty, :package_price, :gmv, :nmv, 0, NULL, :order_created_at, :raw_payload
                                )
                                ON DUPLICATE KEY UPDATE 
                                    tracking_number = VALUES(tracking_number),
                                    platform_id = VALUES(platform_id),
                                    commerce_platform = VALUES(commerce_platform),
                                    shop_name = VALUES(shop_name),
                                    shipping_provider = VALUES(shipping_provider),
                                    status_code = VALUES(status_code),
                                    product_name = VALUES(product_name),
                                    total_qty = VALUES(total_qty),
                                    package_price = VALUES(package_price),
                                    gmv = VALUES(gmv),
                                    nmv = VALUES(nmv),
                                    order_created_at = VALUES(order_created_at),
                                    raw_payload = VALUES(raw_payload)
                            ");
                            $stmtUpsert->execute([
                                ':order_id'          => $orderData['Id'],
                                ':tracking_number'   => $orderData['TrackingNumber'],
                                ':platform_id'       => $orderData['PlatformId'],
                                ':commerce_platform' => $orderData['CommercePlatform'],
                                ':shop_name'         => $orderData['ShopName'],
                                ':shipping_provider' => $orderData['ShippingProvider'],
                                ':status_code'       => $orderData['StatusCode'],
                                ':product_name'      => $orderData['ProductName'],
                                ':total_qty'         => $orderData['TotalQtyOrder'],
                                ':package_price'     => $orderData['PackagePrice'],
                                ':gmv'               => $orderData['GMV'],
                                ':nmv'               => $orderData['NMV'],
                                ':order_created_at'  => $orderData['CreatedAt'],
                                ':raw_payload'       => json_encode($orderData)
                            ]);
                        } catch (Exception $eUpsert) {}
                    }
                }
            }
        } catch (Exception $ocsErr) {
            // Abaikan kegagalan koneksi OCS, lanjutkan lookup lokal
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

        // Jika belum ketemu dan ada receiving barcode, coba cari berdasarkan partial / like
        if (!$unboxRow) {
            foreach ($candidateIds as $cid) {
                if (strlen($cid) >= 6) {
                    $stmtUnboxLike = $pdo->prepare("
                        SELECT rs.* 
                        FROM return_sessions rs
                        WHERE rs.invoice_number LIKE ?
                        ORDER BY rs.id DESC
                        LIMIT 1
                    ");
                    $stmtUnboxLike->execute(["%{$cid}%"]);
                    $unboxRow = $stmtUnboxLike->fetch(PDO::FETCH_ASSOC);
                    if ($unboxRow) break;
                }
            }
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

    // 5. GATHER SEMUA FOTO BUKTI (FOTO PAKET, PRODUK, ITEM RUSAK, SERAH TERIMA KURIR)
    $photosList = [];

    // Foto Paket Unboxing
    if (!empty($unboxRow['package_photo'])) {
        $photosList[] = [
            'type'  => 'package',
            'badge' => 'Paket Retur',
            'title' => 'Foto Fisik Paket Saat Unboxing',
            'url'   => $unboxRow['package_photo']
        ];
    }
    // Foto Produk Unboxing
    if (!empty($unboxRow['product_photo'])) {
        $photosList[] = [
            'type'  => 'product',
            'badge' => 'Produk Retur',
            'title' => 'Foto Produk Saat Unboxing',
            'url'   => $unboxRow['product_photo']
        ];
    }
    // Foto Tambahan Unboxing
    if (!empty($unboxRow['photos'])) {
        $extraPhotos = is_array($unboxRow['photos']) ? $unboxRow['photos'] : json_decode($unboxRow['photos'], true);
        if (is_array($extraPhotos)) {
            foreach ($extraPhotos as $idx => $ep) {
                $pPath = is_array($ep) ? ($ep['path'] ?? '') : $ep;
                $pType = is_array($ep) ? ($ep['type'] ?? 'extra') : 'extra';
                if ($pPath && $pPath !== ($unboxRow['package_photo'] ?? '') && $pPath !== ($unboxRow['product_photo'] ?? '')) {
                    $photosList[] = [
                        'type'  => $pType,
                        'badge' => 'Bukti Retur',
                        'title' => 'Foto Tambahan Unboxing #' . ($idx + 1),
                        'url'   => $pPath
                    ];
                }
            }
        }
    }
    // Foto Tiap Item Unboxing
    if (!empty($items)) {
        foreach ($items as $it) {
            if (!empty($it['photo_path'])) {
                $isDmg = ($it['condition'] === 'DAMAGED' || !empty($it['damage_reason']));
                $photosList[] = [
                    'type'  => 'item',
                    'badge' => $isDmg ? 'Barang Rusak' : 'Foto Item',
                    'title' => 'Foto Item: ' . ($it['product_name'] ?: $it['barcode']) . ($it['damage_reason'] ? ' (' . $it['damage_reason'] . ')' : ''),
                    'url'   => $it['photo_path']
                ];
            }
        }
    }
    // Foto Serah Terima Kurir Receiving
    if (!empty($receptionRow['photo_path'])) {
        $photosList[] = [
            'type'  => 'reception',
            'badge' => 'Kurir Receiving',
            'title' => 'Foto Serah Terima Kurir: ' . ($receptionRow['courier_name'] ?: $receptionRow['expedition']),
            'url'   => $receptionRow['photo_path']
        ];
    }
    if (!empty($receptionRow['package_photos'])) {
        $recExtra = is_array($receptionRow['package_photos']) ? $receptionRow['package_photos'] : json_decode($receptionRow['package_photos'], true);
        if (is_array($recExtra)) {
            foreach ($recExtra as $idx => $rp) {
                if ($rp && $rp !== ($receptionRow['photo_path'] ?? '')) {
                    $photosList[] = [
                        'type'  => 'reception',
                        'badge' => 'Serah Terima',
                        'title' => 'Foto Paket Serah Terima Ekspedisi #' . ($idx + 1),
                        'url'   => $rp
                    ];
                }
            }
        }
    }

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
        echo json_encode([
            'success' => false,
            'message' => 'Data nomor resi atau order tidak ditemukan di sistem OCS maupun gudang lokal.',
            'query'   => $query
        ]);
        exit;
    }

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
