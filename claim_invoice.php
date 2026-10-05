<?php
/**
 * claim_invoice.php
 * Halaman Surat Tagihan Invoice Klaim Kolektif (Multiple Resi / Barang Rusak)
 * PT. IEG Inovasi Eka Gemilang
 */
require_once __DIR__ . '/config.php';

// Pastikan user login
$currentUser = requireLogin();

// Ambil daftar invoice dari parameter GET atau POST (invoices atau resis)
$rawInvoices = $_REQUEST['invoices'] ?? $_REQUEST['resis'] ?? '';
$invoiceList = [];

if (is_array($rawInvoices)) {
    $invoiceList = array_filter(array_map('trim', $rawInvoices));
} elseif (is_string($rawInvoices) && trim($rawInvoices) !== '') {
    // Bisa format koma atau baris baru
    $parts = preg_split('/[\r\n,]+/', $rawInvoices);
    $invoiceList = array_filter(array_map('trim', $parts));
}

// Ambil info rekening bank dari database system_settings
$bankName    = 'BCA (Bank Central Asia)';
$bankAccount = '873-098-1234';
$bankHolder  = 'PT. INOVASI EKA GEMILANG';
$bankNotes   = '*Mohon sertakan nomor invoice pada berita transfer saat pembayaran.';

try {
    $stmtBank = $pdo->query("SELECT key_name, key_value FROM system_settings WHERE key_name IN ('bank_name', 'bank_account_number', 'bank_account_holder', 'bank_payment_notes')");
    if ($stmtBank) {
        while ($bRow = $stmtBank->fetch()) {
            if ($bRow['key_name'] === 'bank_name' && !empty($bRow['key_value'])) $bankName = $bRow['key_value'];
            if ($bRow['key_name'] === 'bank_account_number' && !empty($bRow['key_value'])) $bankAccount = $bRow['key_value'];
            if ($bRow['key_name'] === 'bank_account_holder' && !empty($bRow['key_value'])) $bankHolder = $bRow['key_value'];
            if ($bRow['key_name'] === 'bank_payment_notes' && !empty($bRow['key_value'])) $bankNotes = $bRow['key_value'];
        }
    }
} catch (Exception $eB) {}

// Terbilang dalam Bahasa Indonesia
function terbilangRupiah($angka) {
    $angka = (float)floor(abs((float)$angka));
    $bilangan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    $temp = '';
    if ($angka < 12) {
        $temp = $bilangan[(int)$angka];
    } else if ($angka < 20) {
        $temp = terbilangRupiah($angka - 10) . ' Belas';
    } else if ($angka < 100) {
        $temp = terbilangRupiah(floor($angka / 10)) . ' Puluh ' . terbilangRupiah(fmod($angka, 10));
    } else if ($angka < 200) {
        $temp = 'Seratus ' . terbilangRupiah($angka - 100);
    } else if ($angka < 1000) {
        $temp = terbilangRupiah(floor($angka / 100)) . ' Ratus ' . terbilangRupiah(fmod($angka, 100));
    } else if ($angka < 2000) {
        $temp = 'Seribu ' . terbilangRupiah($angka - 1000);
    } else if ($angka < 1000000) {
        $temp = terbilangRupiah(floor($angka / 1000)) . ' Ribu ' . terbilangRupiah(fmod($angka, 1000));
    } else if ($angka < 1000000000) {
        $temp = terbilangRupiah(floor($angka / 1000000)) . ' Juta ' . terbilangRupiah(fmod($angka, 1000000));
    } else if ($angka < 1000000000000) {
        $temp = terbilangRupiah(floor($angka / 1000000000)) . ' Miliar ' . terbilangRupiah(fmod($angka, 1000000000));
    } else if ($angka < 1000000000000000) {
        $temp = terbilangRupiah(floor($angka / 1000000000000)) . ' Triliun ' . terbilangRupiah(fmod($angka, 1000000000000));
    }
    return preg_replace('/\s+/', ' ', trim($temp));
}

// Helper pembagian item ke beberapa lembar (halaman cetak) agar pas di A4 dan memiliki subtotal per lembar
function paginateInvoiceItems($items, $page1Limit = 8, $middleLimit = 11, $lastLimit = 7) {
    $total = count($items);
    if ($total <= $page1Limit) {
        return !empty($items) ? [$items] : [];
    }
    
    $pages = [];
    $pages[] = array_slice($items, 0, $page1Limit);
    $offset = $page1Limit;
    
    while ($offset < $total) {
        $remaining = $total - $offset;
        if ($remaining <= $lastLimit) {
            $pages[] = array_slice($items, $offset);
            break;
        }
        if ($remaining <= $middleLimit + 2) {
            $take = (int)ceil($remaining / 2);
            $pages[] = array_slice($items, $offset, $take);
            $offset += $take;
            $pages[] = array_slice($items, $offset);
            break;
        }
        $pages[] = array_slice($items, $offset, $middleLimit);
        $offset += $middleLimit;
    }
    
    return $pages;
}

$itemsData = [];
$expeditionNames = [];
$grandTotal = 0;
$totalDamagedQty = 0;

if (!empty($invoiceList)) {
    // Siapkan placeholder untuk query
    $inClause = implode(',', array_fill(0, count($invoiceList), '?'));
    
    try {
        // Query data sesi unboxing, item rusak, dan foto bukti
        $sql = "
            SELECT rs.id AS session_id, rs.invoice_number, rs.expedition, rs.operator_name, rs.created_at,
                   rs.package_photo, rs.product_photo, rs.photos,
                   ri.id AS item_id, ri.barcode, ri.product_name, ri.sku, ri.seller_sku, ri.type, ri.qty,
                   ri.condition, ri.damage_reason, ri.wrong_barcode, ri.wrong_product_name, ri.photo_path
            FROM return_sessions rs
            LEFT JOIN return_items ri ON ri.session_id = rs.id
            WHERE rs.invoice_number IN ($inClause)
            ORDER BY rs.id DESC, ri.id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($invoiceList);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map per invoice
        $sessionMap = [];
        foreach ($rawRows as $r) {
            $inv = $r['invoice_number'];
            if (!isset($sessionMap[$inv])) {
                $sessionMap[$inv] = [
                    'session_id' => $r['session_id'],
                    'invoice_number' => $inv,
                    'expedition' => $r['expedition'] ?: 'Lainnya',
                    'operator_name' => $r['operator_name'] ?: 'Gudang',
                    'created_at' => $r['created_at'],
                    'package_photo' => $r['package_photo'] ?? null,
                    'product_photo' => $r['product_photo'] ?? null,
                    'photos' => $r['photos'] ?? null,
                    'items' => []
                ];
            }
            if ($r['item_id']) {
                $sessionMap[$inv]['items'][] = $r;
            }
        }

        // Ambil data harga dari ocs_orders jika tersedia
        $ocsMap = [];
        try {
            $stmtOcs = $pdo->prepare("
                SELECT order_id, tracking_number, package_price, original_price, subtotal, total_amount, nmv, gmv,
                       shop_name, commerce_platform, product_name, seller_sku
                FROM ocs_orders
                WHERE order_id IN ($inClause) OR tracking_number IN ($inClause)
            ");
            $stmtOcs->execute(array_merge($invoiceList, $invoiceList));
            while ($oRow = $stmtOcs->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($oRow['order_id'])) $ocsMap[trim($oRow['order_id'])] = $oRow;
                if (!empty($oRow['tracking_number'])) $ocsMap[trim($oRow['tracking_number'])] = $oRow;
            }
        } catch (Exception $eOcs) {}

        // Susun data baris invoice tagihan
        $idx = 1;
        foreach ($invoiceList as $invKey) {
            $sess = $sessionMap[$invKey] ?? null;
            $ocs = $ocsMap[$invKey] ?? null;

            $exp = $sess['expedition'] ?? ($ocs['ShippingProvider'] ?? 'Lainnya');
            if ($exp && !in_array($exp, $expeditionNames)) {
                $expeditionNames[] = $exp;
            }

            // Tentukan harga paket
            $price = 0;
            if ($ocs) {
                $candidates = [
                    $ocs['package_price'] ?? 0,
                    $ocs['total_amount'] ?? 0,
                    $ocs['subtotal'] ?? 0,
                    $ocs['original_price'] ?? 0,
                    $ocs['nmv'] ?? 0,
                    $ocs['gmv'] ?? 0
                ];
                foreach ($candidates as $cand) {
                    $cNum = (float)$cand;
                    if ($cNum > 0) {
                        $price = $cNum;
                        break;
                    }
                }
            }

            // Hitung total qty rusak, kumpulkan SKU, dan detail kondisi fisik
            $damagedQty = 0;
            $prodNames = [];
            $reasons = [];
            $skus = [];
            $conditions = [];

            if ($sess && !empty($sess['items'])) {
                foreach ($sess['items'] as $it) {
                    $itCond = strtoupper(trim($it['type'] ?? $it['condition'] ?? ''));
                    $isDmg = ($itCond !== 'GOOD' && $itCond !== 'BAGUS' && $itCond !== 'LAYAK');
                    $q = (int)($it['qty'] ?? 1);
                    if ($isDmg || count($sess['items']) === 1) {
                        $damagedQty += $q;
                        
                        $skuVal = trim($it['seller_sku'] ?: ($it['sku'] ?: ''));
                        if (!empty($skuVal)) {
                            $skus[] = $skuVal;
                        }

                        $pTitle = $it['product_name'] ?: ($it['sku'] ?: $it['barcode']);
                        if (!empty($it['wrong_barcode'])) {
                            $pTitle .= " [Salah Kirim: " . ($it['wrong_product_name'] ?: $it['wrong_barcode']) . "]";
                        }
                        $prodNames[] = $pTitle;

                        $condName = ($itCond && $itCond !== 'GOOD' && $itCond !== 'BAGUS') ? $itCond : 'RUSAK';
                        if (!empty($it['damage_reason'])) {
                            $reasons[] = $it['damage_reason'];
                            $conditions[] = $condName . ' (' . $it['damage_reason'] . ')';
                        } else {
                            $reasons[] = $condName;
                            $conditions[] = $condName;
                        }
                    }
                }
            }

            if ($damagedQty === 0) $damagedQty = 1;
            if (empty($skus) && $ocs && !empty($ocs['seller_sku'])) {
                $skus[] = $ocs['seller_sku'];
            }
            if (empty($prodNames) && $ocs && !empty($ocs['product_name'])) {
                $prodNames[] = $ocs['product_name'];
            }
            if (empty($reasons)) {
                $reasons[] = 'Kondisi Rusak Saat Unboxing';
            }
            if (empty($conditions)) {
                $conditions[] = 'RUSAK';
            }

            // Estimasi harga dari SKU sejenis jika harga paket masih 0
            if ($price == 0 && !empty($skus)) {
                try {
                    $stmtEst = $pdo->prepare("SELECT package_price FROM ocs_orders WHERE seller_sku = ? AND package_price > 0 LIMIT 1");
                    $stmtEst->execute([$skus[0]]);
                    $estRow = $stmtEst->fetch(PDO::FETCH_ASSOC);
                    if ($estRow && (float)$estRow['package_price'] > 0) {
                        $price = (float)$estRow['package_price'];
                    }
                } catch (Exception $eEst) {}
            }

            // Kumpulkan foto-foto bukti unboxing & kerusakan paket
            $itemPhotos = [];
            $seenPhotoUrls = [];
            $addPhotoItem = function($url, $label = '') use (&$itemPhotos, &$seenPhotoUrls) {
                if (!$url) return;
                $url = trim((string)$url);
                $clean = ltrim($url, './');
                if (!$clean || isset($seenPhotoUrls[$clean])) return;
                $seenPhotoUrls[$clean] = true;
                $itemPhotos[] = [
                    'url' => $url,
                    'label' => $label
                ];
            };

            // 1. Dari rs.photos (JSON array unboxing)
            if ($sess && !empty($sess['photos'])) {
                $pJson = $sess['photos'];
                if (is_string($pJson)) {
                    $decoded = json_decode($pJson, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $pIdx => $pVal) {
                            $pUrl = is_array($pVal) ? ($pVal['path'] ?? $pVal['url'] ?? '') : $pVal;
                            $pTitle = is_array($pVal) ? ($pVal['title'] ?? '') : ('Foto #' . ($pIdx + 1));
                            if ($pUrl) $addPhotoItem($pUrl, $pTitle);
                        }
                    }
                }
            }

            // 2. Dari rs.product_photo
            if ($sess && !empty($sess['product_photo'])) {
                $addPhotoItem($sess['product_photo'], 'Foto Bukti Kerusakan');
            }

            // 3. Dari rs.package_photo
            if ($sess && !empty($sess['package_photo'])) {
                $addPhotoItem($sess['package_photo'], 'Foto Paket / Resi');
            }

            // 4. Dari ri.photo_path di setiap item rusak
            if ($sess && !empty($sess['items'])) {
                foreach ($sess['items'] as $it) {
                    if (!empty($it['photo_path'])) {
                        $addPhotoItem($it['photo_path'], 'Foto Item: ' . ($it['sku'] ?: $it['barcode']));
                    }
                }
            }

            $itemsData[] = [
                'no' => $idx++,
                'invoice_number' => $invKey,
                'tracking_number' => $ocs['tracking_number'] ?? $invKey,
                'expedition' => $exp,
                'shop_name' => $ocs['shop_name'] ?? '-',
                'platform' => $ocs['commerce_platform'] ?? '-',
                'sku' => !empty($skus) ? implode(', ', array_unique($skus)) : '-',
                'products' => !empty($prodNames) ? implode(' + ', array_unique($prodNames)) : 'Produk Retur',
                'damaged_qty' => $damagedQty,
                'conditions' => implode(', ', array_unique($conditions)),
                'reason' => implode(' | ', array_unique($reasons)),
                'price' => $price,
                'photos' => $itemPhotos,
                'created_at' => $sess['created_at'] ?? date('Y-m-d H:i:s')
            ];

            $grandTotal += $price;
            $totalDamagedQty += $damagedQty;
        }

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$docNumber = 'INV-CLM-' . date('Ymd') . '-' . str_pad(count($itemsData), 3, '0', STR_PAD_LEFT);
$expeditionDisplay = !empty($expeditionNames) ? implode(', ', $expeditionNames) : 'Ekspedisi Terkait';
$terbilangText = ($grandTotal > 0) ? terbilangRupiah($grandTotal) . ' Rupiah' : 'Nol Rupiah';

// Lakukan paginasi dokumen (pecah tabel per lembar A4 dengan subtotal masing-masing)
$pages = paginateInvoiceItems($itemsData);
$totalPages = count($pages);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Penagihan Klaim - <?= htmlspecialchars($docNumber) ?> - PT. IEG</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* Format Standar Invoice Tagihan Bisnis */
        .invoice-container {
            background-color: #ffffff;
            max-width: 960px;
            margin: 0 auto;
            padding: 36px 40px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            color: #0f172a;
            font-size: 12px;
            line-height: 1.5;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            margin-top: 14px;
            margin-bottom: 14px;
        }

        .invoice-table th, .invoice-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            vertical-align: top;
        }

        .invoice-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .invoice-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        @media print {
            @page { 
                size: A4 portrait; 
                margin: 8mm 10mm; 
            }
            body { 
                background: #ffffff !important; 
                padding: 0 !important; 
                margin: 0 !important; 
                color: #000000 !important; 
                font-size: 11px !important; 
            }
            .no-print { 
                display: none !important; 
            }
            .invoice-page {
                page-break-after: always !important;
                break-after: page !important;
            }
            .photo-appendix-page {
                page-break-before: always !important;
                break-before: page !important;
            }
            .photo-card {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .print-break-inside-avoid {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .invoice-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 0 10mm 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .invoice-table th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .invoice-table tbody tr:nth-child(even) {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            * { 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
            }
        }
    </style>
</head>
<body class="bg-slate-200 min-h-screen text-slate-800 antialiased p-3 sm:p-6 lg:p-8">

    <!-- SPINNER OVERLAY: MENAMPILKAN INDIKATOR RENDERING FOTO SEBELUM MEMBUKA CETAK -->
    <div id="printLoadingOverlay" class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-sm flex flex-col items-center justify-center p-6 text-white no-print">
        <div class="relative flex items-center justify-center mb-4">
            <div class="w-16 h-16 border-4 border-emerald-500/20 border-t-emerald-500 rounded-full animate-spin"></div>
            <div class="absolute text-emerald-400 text-lg">
                <i class="fa-solid fa-camera"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-lg font-black text-white tracking-tight text-center">
            Menyiapkan Dokumen &amp; Merender Lampiran Foto...
        </h3>
        <p class="text-xs text-slate-300 mt-1.5 text-center font-medium max-w-sm">
            Harap tunggu sebentar, sistem sedang memuat seluruh aset dan foto bukti unboxing agar hasil cetak jernih...
        </p>
        <div id="spinnerCounter" class="mt-3 text-[11px] font-mono text-emerald-300 font-bold bg-emerald-950/70 border border-emerald-600/50 px-4 py-1 rounded-full shadow-inner">
            Memeriksa file foto...
        </div>
    </div>

    <div class="max-w-4xl mx-auto space-y-4">
        
        <!-- TOOLBAR AKSI ATAS (HANYA MUNCUL DI LAYAR WEB, TIDAK TERCETAK) -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-300 p-3 sm:p-4 flex flex-wrap items-center justify-between gap-3 no-print">
            <div class="flex items-center gap-2.5">
                <a href="admin.php#claims" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Kembali ke Pusat Klaim</span>
                </a>
                <span class="text-slate-300 font-bold">|</span>
                <span class="text-xs text-slate-600 font-medium">
                    Total: <b class="text-slate-900"><?= count($itemsData) ?> Paket</b> (<b class="text-emerald-700">Rp <?= number_format($grandTotal, 0, ',', '.') ?></b>)
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="copyClaimInvoiceText()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 active:scale-95 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs cursor-pointer" title="Salin Rincian ke Format WhatsApp">
                    <i class="fa-brands fa-whatsapp text-emerald-400"></i>
                    <span>Salin Format WA</span>
                </button>
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-sm shadow-emerald-600/30 cursor-pointer" title="Cetak atau Simpan sebagai PDF">
                    <i class="fa-solid fa-print"></i>
                    <span>Cetak PDF (A4)</span>
                </button>
            </div>
        </div>

        <?php if (empty($itemsData)): ?>
            <!-- STATE JIKA BELUM ADA DATA -->
            <div class="bg-white rounded-2xl p-12 text-center border border-slate-300 shadow-sm space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-3xl mx-auto shadow-inner">
                    <i class="fa-solid fa-clipboard-question"></i>
                </div>
                <div>
                    <h3 class="font-black text-lg text-slate-800">Tidak Ada Paket Klaim yang Dipilih</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                        Silakan kembali ke halaman <b>Pusat Klaim</b> dan centang satu atau beberapa paket rusak untuk melihat dan mencetak invoice penagihan resmi.
                    </p>
                </div>
                <div class="pt-2">
                    <a href="admin.php#claims" class="inline-flex items-center gap-2 px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition">
                        <i class="fa-solid fa-arrow-left"></i> Buka Pusat Klaim
                    </a>
                </div>
            </div>
        <?php else: ?>

            <!-- LOOP PER LEMBAR HALAMAN CETAK INVOICE -->
            <?php foreach ($pages as $pageIndex => $pageItems): ?>
                <?php 
                    $isFirstPage = ($pageIndex === 0);
                    $isLastPage = ($pageIndex === count($pages) - 1);
                    $pageNum = $pageIndex + 1;
                    
                    // Hitung subtotal khusus untuk lembar ini
                    $pageSubtotal = 0;
                    $pageQty = 0;
                    foreach ($pageItems as $pi) {
                        $pageSubtotal += (float)$pi['price'];
                        $pageQty += (int)$pi['damaged_qty'];
                    }
                ?>
                <div class="invoice-container invoice-page mb-8 print:mb-0">
                    
                    <?php if ($isFirstPage): ?>
                        <!-- KOP INVOICE UTAMA (HANYA DI LEMBAR PERTAMA) -->
                        <div class="flex flex-col sm:flex-row items-start justify-between gap-6 pb-5 border-b-2 border-slate-800">
                            <div class="flex items-center gap-3.5">
                                <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="h-14 sm:h-16 w-auto object-contain shrink-0">
                                <div class="space-y-0.5">
                                    <h1 class="font-black text-lg sm:text-xl text-slate-900 tracking-tight leading-tight">PT. INOVASI EKA GEMILANG</h1>
                                    <p class="text-xs font-semibold text-slate-600">Reverse Logistics, Return Inbound &amp; Claims Settlement</p>
                                </div>
                            </div>

                            <div class="text-left sm:text-right shrink-0">
                                <h2 class="text-base sm:text-lg font-black text-slate-900 uppercase tracking-wide">FAKTUR PENAGIHAN KLAIM</h2>
                                <table class="text-xs mt-1.5 inline-table text-left">
                                    <tr>
                                        <td class="text-slate-500 pr-3 py-0.5 font-medium">No. Invoice:</td>
                                        <td class="font-mono font-bold text-slate-900 py-0.5"><?= htmlspecialchars($docNumber) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-slate-500 pr-3 py-0.5 font-medium">Tanggal:</td>
                                        <td class="font-bold text-slate-800 py-0.5"><?= date('d F Y') ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- INFO DITUJUKAN KEPADA (BILL TO) -->
                        <div class="py-3 border-b border-slate-200 text-xs">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-0.5">Tagihan Ditujukan Kepada:</span>
                            <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($expeditionDisplay) ?></div>
                            <div class="text-slate-600 text-[11px]">Bagian Klaim, Asuransi &amp; Rekonsiliasi Ekspedisi</div>
                            <p class="text-slate-500 text-[11px] mt-1 leading-relaxed">
                                Perihal: Pengajuan ganti rugi paket retur pembeli yang terkonfirmasi rusak / cacat fisik saat unboxing di gudang.
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- KOP LEMBAR LANJUTAN -->
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800 text-xs">
                            <div class="flex items-center gap-2.5">
                                <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="h-8 w-auto object-contain">
                                <div>
                                    <span class="font-black text-slate-900 text-xs">PT. INOVASI EKA GEMILANG</span>
                                    <span class="text-slate-400 mx-1.5">|</span>
                                    <span class="text-slate-600">Lanjutan Faktur: <b class="font-mono text-slate-900"><?= htmlspecialchars($docNumber) ?></b></span>
                                </div>
                            </div>
                            <div class="text-right text-[11px] font-bold text-slate-500">
                                Lembar <?= $pageNum ?> dari <?= $totalPages ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- TABEL DATA TAGIHAN STANDAR -->
                    <div class="overflow-x-auto">
                        <table class="invoice-table">
                            <thead>
                                <tr>
                                    <th class="text-center w-8">#</th>
                                    <th class="w-44">No. Resi &amp; Ekspedisi</th>
                                    <th>SKU &amp; Nama Produk</th>
                                    <th class="text-center w-16">Qty</th>
                                    <th class="w-40">Kondisi / Kerusakan</th>
                                    <th class="text-right w-28">Harga Paket</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pageItems as $row): ?>
                                    <tr>
                                        <td class="text-center font-bold text-slate-400"><?= $row['no'] ?></td>
                                        <td>
                                            <div class="font-mono font-bold text-slate-900 text-[11px] tracking-tight"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                            <div class="text-[10px] text-amber-800 font-bold bg-amber-50 border border-amber-200/80 px-1.5 py-0.5 rounded inline-block mt-0.5">
                                                <i class="fa-solid fa-truck-fast text-[9px] text-amber-500"></i> <?= htmlspecialchars($row['expedition']) ?>
                                            </div>
                                            <?php if ($row['invoice_number'] !== $row['tracking_number']): ?>
                                                <div class="text-[9px] text-slate-400 font-mono mt-0.5">Ref: <?= htmlspecialchars($row['invoice_number']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['sku']) && $row['sku'] !== '-'): ?>
                                                <div class="mb-1">
                                                    <span class="inline-flex items-center gap-1 font-mono font-bold text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 border border-indigo-200 text-indigo-900">
                                                        <i class="fa-solid fa-tag text-[8px] text-indigo-400"></i> SKU: <?= htmlspecialchars($row['sku']) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="font-semibold text-slate-900 leading-snug text-xs"><?= htmlspecialchars($row['products']) ?></div>
                                            <?php if (!empty($row['shop_name']) && $row['shop_name'] !== '-'): ?>
                                                <div class="text-[10px] text-slate-400 mt-0.5">Toko: <?= htmlspecialchars($row['shop_name']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-mono font-bold text-slate-900 text-xs">
                                            <?= $row['damaged_qty'] ?> pcs
                                        </td>
                                        <td>
                                            <div class="font-bold text-rose-700 text-[11px]"><?= htmlspecialchars($row['conditions']) ?></div>
                                            <div class="text-[10px] text-slate-500 leading-tight mt-0.5"><?= htmlspecialchars($row['reason']) ?></div>
                                        </td>
                                        <td class="text-right font-mono font-bold text-slate-900 whitespace-nowrap text-xs">
                                            <?= ($row['price'] > 0) ? 'Rp ' . number_format($row['price'], 0, ',', '.') : '<span class="text-slate-400 italic font-normal text-[11px]">Rp 0</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <!-- SUB-TOTAL LEMBAR INI -->
                                <tr class="bg-amber-50/70 font-bold border-t border-slate-300">
                                    <td colspan="3" class="text-right uppercase text-slate-700 pr-3 text-[11px]">
                                        Subtotal Lembar <?= $pageNum ?> (<?= count($pageItems) ?> Paket):
                                    </td>
                                    <td class="text-center font-mono text-slate-900 font-bold text-xs">
                                        <?= $pageQty ?> pcs
                                    </td>
                                    <td class="text-slate-500 text-[10px] italic">
                                        Subtotal halaman <?= $pageNum ?>
                                    </td>
                                    <td class="text-right font-mono font-bold text-slate-900 text-xs whitespace-nowrap">
                                        Rp <?= number_format($pageSubtotal, 0, ',', '.') ?>
                                    </td>
                                </tr>

                                <?php if ($isLastPage): ?>
                                    <!-- TOTAL KESELURUHAN (HANYA DITAMPILKAN DI LEMBAR TERAKHIR) -->
                                    <tr class="bg-emerald-100 font-black border-t-2 border-slate-800 text-slate-900">
                                        <td colspan="3" class="text-right uppercase tracking-wider pr-3 text-xs">
                                            TOTAL KESELURUHAN (<?= count($itemsData) ?> PAKET):
                                        </td>
                                        <td class="text-center font-mono font-black text-sm text-emerald-950">
                                            <?= $totalDamagedQty ?> pcs
                                        </td>
                                        <td class="text-emerald-900 text-[10px] font-bold uppercase tracking-tight">
                                            Grand Total Final
                                        </td>
                                        <td class="text-right font-mono font-black text-emerald-950 whitespace-nowrap text-sm">
                                            Rp <?= number_format($grandTotal, 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>

                    <?php if ($isLastPage): ?>
                        <!-- TERBILANG -->
                        <div class="p-2.5 bg-slate-50 border border-slate-300 text-xs rounded-none mb-3">
                            <span class="font-bold text-slate-600 uppercase text-[10px] block mb-0.5">Terbilang:</span>
                            <span class="font-bold text-slate-900 italic text-xs">
                                "<?= htmlspecialchars($terbilangText) ?>"
                            </span>
                        </div>

                        <!-- INSTRUKSI PEMBAYARAN & CATATAN -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 py-2.5 border-t border-slate-200 text-[11px] leading-relaxed">
                            <div>
                                <b class="text-slate-800 block mb-0.5">Informasi Pembayaran (Transfer Bank):</b>
                                <p class="text-slate-600">Bank: <b><?= htmlspecialchars($bankName) ?></b></p>
                                <p class="text-slate-600">No. Rekening: <b class="font-mono text-slate-900"><?= htmlspecialchars($bankAccount) ?></b></p>
                                <p class="text-slate-600">Atas Nama: <b><?= htmlspecialchars($bankHolder) ?></b></p>
                                <?php if (!empty($bankNotes)): ?>
                                <p class="text-slate-400 text-[10px] mt-1"><?= htmlspecialchars($bankNotes) ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <b class="text-slate-800 block mb-0.5">Catatan &amp; Ketentuan:</b>
                                <p class="text-slate-600">1. Seluruh barang rusak di atas telah melalui pemeriksaan dan rekaman fisik unboxing di gudang.</p>
                                <p class="text-slate-600">2. Dokumen ini merupakan surat tagihan sah penggantian klaim barang rusak.</p>
                                <p class="text-slate-600">3. Mohon pihak ekspedisi melakukan konfirmasi penyelesaian klaim.</p>
                            </div>
                        </div>

                        <!-- LEMBAR TANDA TANGAN (5 POSISI: Admin Retrun, SPV, Manager, CEO, Accounting) -->
                        <div class="pt-5 border-t border-slate-300 mt-3 print-break-inside-avoid">
                            <div class="text-[10px] uppercase font-bold text-slate-400 mb-2 tracking-wider text-center">
                                LEMBAR PENGESAHAN &amp; VERIFIKASI KLAIM
                            </div>
                            <div class="grid grid-cols-5 gap-2 text-center text-xs">
                                <!-- 1. ADMIN RETRUN -->
                                <div class="flex flex-col justify-between h-24 border border-slate-300 rounded p-1.5 bg-slate-50/50">
                                    <div>
                                        <span class="text-slate-500 block text-[9px] uppercase font-bold">Dibuat Oleh</span>
                                        <span class="font-black text-slate-800 text-[10px] sm:text-[11px]">Admin Retrun</span>
                                    </div>
                                    <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 text-[10px] truncate">
                                        ( <?= htmlspecialchars($currentUser['username'] ?? 'Admin') ?> )
                                    </div>
                                </div>

                                <!-- 2. SPV -->
                                <div class="flex flex-col justify-between h-24 border border-slate-300 rounded p-1.5 bg-slate-50/50">
                                    <div>
                                        <span class="text-slate-500 block text-[9px] uppercase font-bold">Diperiksa Oleh</span>
                                        <span class="font-black text-slate-800 text-[10px] sm:text-[11px]">SPV / Supervisor</span>
                                    </div>
                                    <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 text-[10px]">
                                        ( ..................... )
                                    </div>
                                </div>

                                <!-- 3. MANAGER -->
                                <div class="flex flex-col justify-between h-24 border border-slate-300 rounded p-1.5 bg-slate-50/50">
                                    <div>
                                        <span class="text-slate-500 block text-[9px] uppercase font-bold">Disetujui Oleh</span>
                                        <span class="font-black text-slate-800 text-[10px] sm:text-[11px]">Manager</span>
                                    </div>
                                    <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 text-[10px]">
                                        ( ..................... )
                                    </div>
                                </div>

                                <!-- 4. CEO -->
                                <div class="flex flex-col justify-between h-24 border border-slate-300 rounded p-1.5 bg-slate-50/50">
                                    <div>
                                        <span class="text-slate-500 block text-[9px] uppercase font-bold">Mengetahui</span>
                                        <span class="font-black text-slate-800 text-[10px] sm:text-[11px]">CEO</span>
                                    </div>
                                    <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 text-[10px]">
                                        ( ..................... )
                                    </div>
                                </div>

                                <!-- 5. ACCOUNTING -->
                                <div class="flex flex-col justify-between h-24 border border-slate-300 rounded p-1.5 bg-slate-50/50">
                                    <div>
                                        <span class="text-slate-500 block text-[9px] uppercase font-bold">Diverifikasi</span>
                                        <span class="font-black text-slate-800 text-[10px] sm:text-[11px]">Accounting</span>
                                    </div>
                                    <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 text-[10px]">
                                        ( ..................... )
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between text-[10px] text-slate-400 mt-3 pt-2 border-t border-slate-200">
                        <span class="font-mono text-slate-400"><?= htmlspecialchars($docNumber) ?></span>
                        <span class="font-semibold text-slate-500">Lembar <?= $pageNum ?> dari <?= $totalPages ?></span>
                    </div>

                </div>
            <?php endforeach; ?>

            <!-- LAMPIRAN DOKUMENTASI FOTO KERUSAKAN & BUKTI FISIK PAKET (HALAMAN CETAK BERIKUTNYA) -->
            <div class="invoice-container photo-appendix-page mb-8 print:mb-0">
                <!-- KOP LAMPIRAN -->
                <div class="flex items-center justify-between pb-3 mb-4 border-b-2 border-slate-800">
                    <div class="flex items-center gap-3">
                        <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="h-10 w-auto object-contain">
                        <div>
                            <h2 class="font-black text-base sm:text-lg text-slate-900 tracking-tight">LAMPIRAN DOKUMENTASI FOTO KERUSAKAN PAKET</h2>
                            <p class="text-xs font-semibold text-slate-600">Bukti Fisik &amp; Kerusakan Sesi Unboxing Inbound Retur</p>
                        </div>
                    </div>
                    <div class="text-right text-xs">
                        <div class="font-mono font-bold text-slate-800">No. Inv: <?= htmlspecialchars($docNumber) ?></div>
                        <div class="text-slate-500 text-[11px]"><?= date('d F Y') ?></div>
                        <div class="text-[10px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 inline-block mt-0.5">
                            Lampiran <?= count($itemsData) ?> Resi
                        </div>
                    </div>
                </div>

                <!-- DAFTAR RESI & FOTO-FOTO BUKTI -->
                <div class="space-y-4">
                    <?php foreach ($itemsData as $it): ?>
                        <div class="photo-card border border-slate-300 rounded-lg p-3 bg-white shadow-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-2 border-b border-slate-200 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-slate-800 text-white font-black flex items-center justify-center text-[10px]">
                                        <?= $it['no'] ?>
                                    </span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase">No. Resi (AWB):</span>
                                        <span class="font-mono font-black text-slate-900 text-xs sm:text-sm ml-1">
                                            <?= htmlspecialchars($it['tracking_number']) ?>
                                        </span>
                                        <?php if ($it['invoice_number'] !== $it['tracking_number']): ?>
                                            <span class="text-[10px] text-slate-400 font-mono ml-1">(Ref: <?= htmlspecialchars($it['invoice_number']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded">
                                        <i class="fa-solid fa-truck-fast text-amber-500"></i> <?= htmlspecialchars($it['expedition']) ?>
                                    </span>
                                    <span class="text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded">
                                        <?= htmlspecialchars($it['conditions']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="text-xs mb-2.5 bg-slate-50 px-2.5 py-1.5 rounded border border-slate-200 flex flex-wrap items-center justify-between gap-2">
                                <div class="truncate max-w-xl">
                                    <?php if (!empty($it['sku']) && $it['sku'] !== '-'): ?>
                                        <b class="font-mono text-indigo-900 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded text-[10px] mr-1">
                                            SKU: <?= htmlspecialchars($it['sku']) ?>
                                        </b>
                                    <?php endif; ?>
                                    <span class="font-medium text-slate-800"><?= htmlspecialchars($it['products']) ?></span>
                                </div>
                                <div class="text-[11px] font-bold text-slate-700 shrink-0">
                                    Qty Rusak: <span class="font-mono text-rose-600"><?= $it['damaged_qty'] ?> pcs</span> | 
                                    Harga: <span class="font-mono text-slate-900"><?= $it['price'] > 0 ? ('Rp ' . number_format($it['price'], 0, ',', '.')) : '-' ?></span>
                                </div>
                            </div>

                            <?php if (!empty($it['photos'])): ?>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                    <?php foreach ($it['photos'] as $p): ?>
                                        <?php 
                                            $pUrl = $p['url'];
                                            if (!preg_match('/^(http|https|data):/i', $pUrl)) {
                                                $pUrl = ltrim($pUrl, './');
                                            }
                                        ?>
                                        <div class="border border-slate-200 rounded overflow-hidden bg-slate-50 text-center">
                                            <div class="h-36 sm:h-40 w-full overflow-hidden flex items-center justify-center bg-black/5">
                                                <img src="<?= htmlspecialchars($pUrl) ?>" 
                                                     alt="Foto Resi <?= htmlspecialchars($it['tracking_number']) ?>" 
                                                     class="w-full h-full object-contain hover:scale-105 transition"
                                                     loading="lazy"
                                                     onerror="this.parentElement.innerHTML='<span class=\'text-[10px] text-slate-400 p-2 block\'>Foto tidak dapat dimuat</span>'">
                                            </div>
                                            <?php if (!empty($p['label'])): ?>
                                                <div class="text-[9px] font-bold text-slate-600 p-1 bg-white border-t border-slate-200 truncate">
                                                    <?= htmlspecialchars($p['label']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center bg-slate-50 border border-dashed border-slate-300 rounded text-slate-400 text-xs italic">
                                    <i class="fa-solid fa-image text-slate-300 mr-1"></i> Tidak ada file foto terlampir untuk resi ini
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <!-- SCRIPT COPY FORMAT WA & OTOMATIS BUKA PRINT DIALOG BROWSER -->
    <script>
        const invoiceDataItems = <?= json_encode($itemsData) ?>;
        const invoiceDocNumber = <?= json_encode($docNumber) ?>;
        const grandTotalFormatted = <?= json_encode('Rp ' . number_format($grandTotal, 0, ',', '.')) ?>;
        const expeditionName = <?= json_encode($expeditionDisplay) ?>;

        function copyClaimInvoiceText() {
            if (!invoiceDataItems || invoiceDataItems.length === 0) {
                alert('Tidak ada data klaim untuk disalin.');
                return;
            }

            let text = `*FAKTUR TAGIHAN KLAIM BARANG RUSAK EKSPEDISI*\n`;
            text += `No. Tagihan: ${invoiceDocNumber}\n`;
            text += `Tanggal: <?= date('d/m/Y') ?>\n`;
            text += `Ekspedisi: ${expeditionName}\n`;
            text += `Jumlah Paket: ${invoiceDataItems.length} Paket\n`;
            text += `Total Tagihan: *${grandTotalFormatted}*\n\n`;
            text += `*Rincian Tagihan:*\n`;

            invoiceDataItems.forEach((it, idx) => {
                const pr = it.price > 0 ? ('Rp ' + Number(it.price).toLocaleString('id-ID')) : '-';
                text += `${idx + 1}. Resi: ${it.tracking_number} (${it.invoice_number})\n`;
                text += `   Ekspedisi: ${it.expedition || '-'}\n`;
                if (it.sku && it.sku !== '-') text += `   SKU: ${it.sku}\n`;
                text += `   Produk: ${it.products}\n`;
                text += `   Qty: ${it.damaged_qty} pcs\n`;
                text += `   Kondisi: ${it.conditions} - ${it.reason}\n`;
                text += `   Nominal: ${pr}\n\n`;
            });

            text += `Mohon segera diverifikasi dan diproses penggantian klaimnya. Terima kasih.\n`;
            text += `_PT. Inovasi Eka Gemilang - Reverse Logistics_`;

            navigator.clipboard.writeText(text).then(() => {
                alert('Format rekap tagihan klaim berhasil disalin ke clipboard! Silakan kirimkan ke chat WhatsApp tim ekspedisi.');
            }).catch(e => {
                prompt('Salin teks tagihan berikut:', text);
            });
        }

        // Otomatis menunggu seluruh foto ter-render sebelum membuka print dialog browser
        function waitForAllImages() {
            return new Promise((resolve) => {
                const images = Array.from(document.images);
                if (images.length === 0) return resolve();

                let loadedCount = 0;
                const totalCount = images.length;
                const counterEl = document.getElementById('spinnerCounter');

                function updateProgress() {
                    loadedCount++;
                    if (counterEl) {
                        counterEl.textContent = `Foto siap: ${loadedCount} / ${totalCount}`;
                    }
                    if (loadedCount >= totalCount) {
                        resolve();
                    }
                }

                images.forEach((img) => {
                    if (img.complete && img.naturalHeight !== 0) {
                        updateProgress();
                    } else {
                        img.addEventListener('load', updateProgress, { once: true });
                        img.addEventListener('error', updateProgress, { once: true });
                    }
                });

                // Fallback timeout maksimal 6 detik jika ada aset lambat
                setTimeout(resolve, 6000);
            });
        }

        window.addEventListener('load', function() {
            waitForAllImages().then(() => {
                const overlay = document.getElementById('printLoadingOverlay');
                if (overlay) {
                    overlay.classList.add('transition-opacity', 'duration-300', 'opacity-0');
                    setTimeout(() => {
                        overlay.remove();
                    }, 350);
                }

                setTimeout(function() {
                    window.print();
                }, 300);
            });
        });
    </script>
</body>
</html>
