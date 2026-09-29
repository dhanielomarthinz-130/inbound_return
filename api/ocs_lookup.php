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
        $stmtDamaged = $pdo->query("
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
        ");
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
                'GMV'                    => (float)($cached['gmv'] ?? 0),
                'NMV'                    => (float)($cached['nmv'] ?? 0),
                'CreatedAt'              => $cached['order_created_at'],
                'HasPackingVideo'        => (bool)$cached['has_packing_video'],
                'PackingVideoUrl'        => $cached['packing_video_url'],
                '_source'                => 'local_cache'
            ];
        }
    }

    // 2. Jika belum ada di cache atau di-refresh, query langsung ke OCS IEG System
    if (!$orderData) {
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
            CURLOPT_TIMEOUT        => 15
        ]);
        $loginRes = curl_exec($chLogin);
        $loginHttp = curl_getinfo($chLogin, CURLINFO_HTTP_CODE);
        curl_close($chLogin);

        if ($loginHttp !== 200 || !$loginRes) {
            throw new Exception("Gagal login ke OCS IEG System (HTTP {$loginHttp}).");
        }

        $loginJson = json_decode($loginRes, true);
        $token = $loginJson['Token'] ?? null;
        if (!$token) {
            throw new Exception("Token otentikasi OCS tidak valid.");
        }

        // 1. Coba cari di DTO_Orders berdasarkan Id (Sangat cepat karena Primary Key terindeks ~0.3s)
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
            CURLOPT_TIMEOUT        => 5
        ]);
        $ordRes = curl_exec($chOrd);
        curl_close($chOrd);
        $ordJson = json_decode($ordRes, true);

        // 2. Jika tidak ditemukan berdasarkan Id, cari berdasarkan TrackingNumber
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
                CURLOPT_TIMEOUT        => 6
            ]);
            $ordTrackRes = curl_exec($chOrdTrack);
            curl_close($chOrdTrack);
            $ordJson = json_decode($ordTrackRes, true);
        }

        if (!empty($ordJson['value'][0])) {
            $raw = $ordJson['value'][0];
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
                'CreatedAt'          => $raw['CreatedAt'] ?? '',
                '_source'            => 'ocs_api'
            ];
        } else {
            // Jika tidak ada di DTO_Orders, coba cari di DTO_ReturnOrder
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
                CURLOPT_TIMEOUT        => 20
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
                    'CreatedAt'          => $rawRet['CreatedAt'] ?? '',
                    'ReturnReason'       => $rawRet['ReturnReason'] ?? '',
                    'ReturnReasonText'   => $rawRet['ReturnReasonText'] ?? '',
                    '_source'            => 'ocs_return_order'
                ];
            }
        }

        // Jika data order ditemukan di OCS, cek ketersediaan video packing
        if ($orderData) {
            $orderId = $orderData['Id'];
            $hasVideo = false;
            $videoStreamUrl = null;

            if ($orderId) {
                $chVidTest = curl_init("{$ocsBaseUrl}/Streaming/PackingTest?orderId=" . urlencode($orderId));
                curl_setopt_array($chVidTest, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPGET        => true,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: Bearer {$token}",
                        "Accept: application/json"
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT        => 10
                ]);
                $testRes = curl_exec($chVidTest);
                $testCode = curl_getinfo($chVidTest, CURLINFO_HTTP_CODE);
                curl_close($chVidTest);

                if ($testCode === 200) {
                    $hasVideo = true;
                    $videoStreamUrl = "{$ocsBaseUrl}/Streaming/Packing?orderId=" . urlencode($orderId);
                }
            }

            $orderData['HasPackingVideo'] = $hasVideo;
            $orderData['PackingVideoUrl'] = $videoStreamUrl;

            // Ambil Nilai / Harga Paket (GMV & NMV) dari DTO_OrderGmv
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
                    CURLOPT_TIMEOUT        => 6
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

            $orderData['PackagePrice'] = $pkgPrice;
            $orderData['PackagePriceFormatted'] = 'Rp ' . number_format($pkgPrice, 0, ',', '.');
            $orderData['GMV'] = $gmv;
            $orderData['NMV'] = $nmv;

            // Simpan atau perbarui cache di tabel ocs_orders
            $stmtUpsert = $pdo->prepare("
                INSERT INTO ocs_orders (
                    order_id, tracking_number, platform_id, commerce_platform, 
                    shop_name, shipping_provider, status_code, product_name, 
                    total_qty, package_price, gmv, nmv, has_packing_video, packing_video_url, order_created_at, raw_payload
                ) VALUES (
                    :order_id, :tracking_number, :platform_id, :commerce_platform, 
                    :shop_name, :shipping_provider, :status_code, :product_name, 
                    :total_qty, :package_price, :gmv, :nmv, :has_packing_video, :packing_video_url, :order_created_at, :raw_payload
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
                    has_packing_video = VALUES(has_packing_video),
                    packing_video_url = VALUES(packing_video_url),
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
                ':has_packing_video' => $orderData['HasPackingVideo'] ? 1 : 0,
                ':packing_video_url' => $orderData['PackingVideoUrl'],
                ':order_created_at'  => $orderData['CreatedAt'],
                ':raw_payload'       => json_encode($orderData)
            ]);
        }
    }

    // 3. CROSS-REFERENCE DATA LOKAL RECEIVING INBOUND (Tanda Terima Ekspedisi)
    $receptionData = null;
    $searchResi = $orderData['TrackingNumber'] ?? $query;
    $searchOrder = $orderData['Id'] ?? $query;

    $stmtRec = $pdo->prepare("
        SELECT er.*, rp.package_barcode, rp.scanned_at AS package_scanned_at
        FROM reception_packages rp
        JOIN expedition_receptions er ON er.id = rp.reception_id
        WHERE rp.package_barcode = :q1 OR rp.package_barcode = :q2 OR rp.package_barcode = :q3
        ORDER BY rp.id DESC
        LIMIT 1
    ");
    $stmtRec->execute([
        ':q1' => $query,
        ':q2' => $searchResi,
        ':q3' => $searchOrder
    ]);
    $receptionRow = $stmtRec->fetch(PDO::FETCH_ASSOC);
    if ($receptionRow) {
        $receptionData = [
            'id'             => $receptionRow['id'],
            'receipt_number' => $receptionRow['receipt_number'],
            'expedition'     => $receptionRow['expedition'],
            'courier_name'   => $receptionRow['courier_name'],
            'operator_name'  => $receptionRow['operator_name'],
            'package_barcode'=> $receptionRow['package_barcode'],
            'scanned_at'     => $receptionRow['package_scanned_at'] ?: $receptionRow['created_at'],
            'created_at'     => $receptionRow['created_at']
        ];
    }

    // 4. CROSS-REFERENCE DATA LOKAL INBOUND UNBOXING (Rekaman Video Unboxing & Detail Barang)
    $unboxingData = null;
    $stmtUnbox = $pdo->prepare("
        SELECT rs.* 
        FROM return_sessions rs
        WHERE rs.invoice_number = :q1 OR rs.invoice_number = :q2 OR rs.invoice_number = :q3
        ORDER BY rs.id DESC
        LIMIT 1
    ");
    $stmtUnbox->execute([
        ':q1' => $query,
        ':q2' => $searchResi,
        ':q3' => $searchOrder
    ]);
    $unboxRow = $stmtUnbox->fetch(PDO::FETCH_ASSOC);

    if ($unboxRow) {
        // Ambil list item unboxing
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
            'created_at'     => $unboxRow['created_at'],
            'items'          => $items
        ];
    }

    // Proxy URL untuk video packing OCS agar bisa diputar di browser tanpa kendala Bearer token
    $packingProxyUrl = null;
    if (!empty($orderData['Id'])) {
        $packingProxyUrl = "api/ocs_video_stream.php?orderId=" . urlencode($orderData['Id']);
    }

    // 5. EVALUASI KELAYAKAN KLAIM: HANYA PAKET DENGAN TYPE ATAU KONDISI BUKAN GOOD / BAGUS
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
        if ($damagedCount > 0) $isClaimable = true;

        if ($isClaimable) {
            $cnt = $damagedCount > 0 ? $damagedCount : count($damageDetails);
            $claimEligibilityReason = "Kondisi unboxing tercatat RUSAK / CACAT ({$cnt} item). Memenuhi syarat untuk diajukan klaim / banding.";
        } else {
            $claimEligibilityReason = "Kondisi unboxing tercatat BAGUS (GOOD). Paket retur normal, BUKAN paket klaim kerusakan.";
        }
    } else {
        $claimEligibilityReason = "Paket belum di-unboxing di stasiun gudang.";
    }

    // 6. EVALUASI KESIAPAN BERKAS KLAIM (Claim Dossier Readiness)
    $readiness = [
        'has_order'       => !empty($orderData),
        'has_tracking'    => !empty($orderData['TrackingNumber']),
        'has_reception'   => !empty($receptionData),
        'has_unboxing'    => !empty($unboxingData),
        'has_unbox_video' => !empty($unboxingData['video_path']) && file_exists(__DIR__ . '/../' . ltrim($unboxingData['video_path'], '/')),
        'has_pack_video'  => !empty($orderData['HasPackingVideo']),
        'is_damaged'      => $isClaimable,
        'score_percent'   => 0
    ];

    $score = 0;
    if ($readiness['has_order']) $score += 25;
    if ($readiness['has_reception']) $score += 25;
    if ($readiness['has_unboxing']) $score += 25;
    if ($readiness['has_pack_video'] || $readiness['has_unbox_video']) $score += 25;
    $readiness['score_percent'] = $score;

    echo json_encode([
        'success'                  => true,
        'query'                    => $query,
        'is_claimable'             => $isClaimable,
        'claim_eligibility_reason' => $claimEligibilityReason,
        'damaged_count'            => $damagedCount,
        'damage_details'           => $damageDetails,
        'order'                    => $orderData,
        'packing_video'            => [
            'has_video'            => !empty($orderData['HasPackingVideo']),
            'stream_url'           => $orderData['PackingVideoUrl'] ?? null,
            'proxy_url'            => $packingProxyUrl
        ],
        'reception'                => $receptionData,
        'unboxing'                 => $unboxingData,
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
