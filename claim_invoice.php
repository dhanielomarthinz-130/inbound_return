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

// Terbilang dalam Bahasa Indonesia
function terbilangRupiah($angka) {
    $angka = (float)abs($angka);
    $bilangan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    $temp = '';
    if ($angka < 12) {
        $temp = ' ' . $bilangan[(int)$angka];
    } else if ($angka < 20) {
        $temp = terbilangRupiah($angka - 10) . ' Belas';
    } else if ($angka < 100) {
        $temp = terbilangRupiah($angka / 10) . ' Puluh' . terbilangRupiah($angka % 10);
    } else if ($angka < 200) {
        $temp = ' Seratus' . terbilangRupiah($angka - 100);
    } else if ($angka < 1000) {
        $temp = terbilangRupiah($angka / 100) . ' Ratus' . terbilangRupiah($angka % 100);
    } else if ($angka < 2000) {
        $temp = ' Seribu' . terbilangRupiah($angka - 1000);
    } else if ($angka < 1000000) {
        $temp = terbilangRupiah($angka / 1000) . ' Ribu' . terbilangRupiah($angka % 1000);
    } else if ($angka < 1000000000) {
        $temp = terbilangRupiah($angka / 1000000) . ' Juta' . terbilangRupiah($angka % 1000000);
    } else if ($angka < 1000000000000) {
        $temp = terbilangRupiah($angka / 1000000000) . ' Miliar' . terbilangRupiah(fmod($angka, 1000000000));
    } else if ($angka < 1000000000000000) {
        $temp = terbilangRupiah($angka / 1000000000000) . ' Triliun' . terbilangRupiah(fmod($angka, 1000000000000));
    }
    return trim($temp);
}

$itemsData = [];
$expeditionNames = [];
$grandTotal = 0;
$totalDamagedQty = 0;

if (!empty($invoiceList)) {
    // Siapkan placeholder untuk query
    $inClause = implode(',', array_fill(0, count($invoiceList), '?'));
    
    try {
        // Query data sesi unboxing & item rusak
        $sql = "
            SELECT rs.id AS session_id, rs.invoice_number, rs.expedition, rs.operator_name, rs.created_at,
                   ri.id AS item_id, ri.barcode, ri.product_name, ri.sku, ri.seller_sku, ri.type, ri.qty,
                   ri.condition, ri.damage_reason, ri.wrong_barcode, ri.wrong_product_name
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
                margin: 10mm 12mm; 
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
            .invoice-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
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

            <!-- LEMBAR INVOICE RESMI (TAMPIL SEBAGAI DOKUMEN CETAK STANDAR) -->
            <div id="invoiceSheet" class="invoice-container">
                
                <!-- KOP INVOICE: PERUSAHAAN & IDENTITAS SURAT TAGIHAN -->
                <div class="flex flex-col sm:flex-row items-start justify-between gap-6 pb-5 border-b-2 border-slate-800">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded bg-emerald-700 text-white font-black flex items-center justify-center text-xs">IEG</span>
                            <h1 class="font-black text-lg sm:text-xl text-slate-900 tracking-tight">PT. INOVASI EKA GEMILANG</h1>
                        </div>
                        <p class="text-xs font-semibold text-slate-600">Reverse Logistics, Return Inbound & Claims Settlement</p>
                        <p class="text-[11px] text-slate-500">Pergudangan Retur IEG • Email: dispute-claims@ieg.co.id</p>
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
                            <tr>
                                <td class="text-slate-500 pr-3 py-0.5 font-medium">Jatuh Tempo:</td>
                                <td class="font-bold text-slate-800 py-0.5">14 Hari Kalender</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- INFO DITUJUKAN KEPADA (BILL TO) & DESKRIPSI -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4 border-b border-slate-200 text-xs">
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Tagihan Ditujukan Kepada:</span>
                        <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($expeditionDisplay) ?></div>
                        <div class="text-slate-600 text-[11px]">Bagian Klaim, Asuransi & Rekonsiliasi Ekspedisi</div>
                        <p class="text-slate-500 text-[11px] mt-1 leading-relaxed">
                            Perihal: Pengajuan ganti rugi paket retur pembeli yang terkonfirmasi rusak / cacat fisik saat unboxing di gudang.
                        </p>
                    </div>
                    <div class="sm:text-right flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Diterbitkan Oleh:</span>
                            <div class="font-bold text-slate-900"><?= htmlspecialchars($currentUser['username'] ?? 'Staff Administrasi Klaim IEG') ?></div>
                            <div class="text-slate-500 text-[11px]">Divisi Inbound & Dispute Handling</div>
                        </div>
                        <div class="mt-2 text-[11px] text-slate-500">
                            Status Tagihan: <span class="font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">TERBUKA / DIAJUKAN</span>
                        </div>
                    </div>
                </div>

                <!-- TABEL DATA TAGIHAN STANDAR (CLASSIC BUSINESS DATA TABLE) -->
                <div class="overflow-x-auto">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th class="text-center w-8">#</th>
                                <th class="w-36">No. Resi (AWB)</th>
                                <th class="w-24">Ekspedisi</th>
                                <th class="w-32">SKU Produk</th>
                                <th>Nama Produk Retur</th>
                                <th class="text-center w-16">Qty</th>
                                <th class="w-36">Kondisi / Kerusakan</th>
                                <th class="text-right w-28">Harga Paket</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itemsData as $row): ?>
                                <tr>
                                    <td class="text-center font-bold text-slate-400"><?= $row['no'] ?></td>
                                    <td>
                                        <div class="font-mono font-bold text-slate-900 text-[11px] tracking-tight"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                        <?php if ($row['invoice_number'] !== $row['tracking_number']): ?>
                                            <div class="text-[10px] text-slate-400 font-mono">Ref: <?= htmlspecialchars($row['invoice_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-semibold text-slate-800 text-[11px]"><?= htmlspecialchars($row['expedition']) ?></td>
                                    <td class="font-mono font-bold text-slate-900 text-[11px]"><?= htmlspecialchars($row['sku']) ?></td>
                                    <td>
                                        <div class="font-semibold text-slate-900 leading-snug"><?= htmlspecialchars($row['products']) ?></div>
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
                            <tr class="bg-slate-100 font-bold">
                                <td colspan="5" class="text-right uppercase text-slate-700 pr-3">
                                    Total (<?= count($itemsData) ?> Paket):
                                </td>
                                <td class="text-center font-mono text-slate-900 font-black text-xs">
                                    <?= $totalDamagedQty ?> pcs
                                </td>
                                <td class="text-right uppercase text-slate-700 pr-3">
                                    Grand Total:
                                </td>
                                <td class="text-right font-mono font-black text-slate-900 whitespace-nowrap text-sm">
                                    Rp <?= number_format($grandTotal, 0, ',', '.') ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- TERBILANG -->
                <div class="p-3 bg-slate-50 border border-slate-300 text-xs rounded-none mb-4">
                    <span class="font-bold text-slate-600 uppercase text-[10px] block mb-0.5">Terbilang:</span>
                    <span class="font-bold text-slate-900 italic text-xs">
                        "<?= htmlspecialchars($terbilangText) ?>"
                    </span>
                </div>

                <!-- INSTRUKSI PEMBAYARAN & CATATAN -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 py-3 border-t border-slate-200 text-[11px] leading-relaxed">
                    <div>
                        <b class="text-slate-800 block mb-1">Informasi Pembayaran (Transfer Bank):</b>
                        <p class="text-slate-600">Bank: <b>BCA (Bank Central Asia)</b></p>
                        <p class="text-slate-600">No. Rekening: <b class="font-mono text-slate-900">873-098-1234</b></p>
                        <p class="text-slate-600">Atas Nama: <b>PT. INOVASI EKA GEMILANG</b></p>
                        <p class="text-slate-400 text-[10px] mt-1">*Mohon sertakan nomor invoice pada berita transfer saat pembayaran.</p>
                    </div>
                    <div>
                        <b class="text-slate-800 block mb-1">Catatan & Ketentuan:</b>
                        <p class="text-slate-600">1. Seluruh barang rusak di atas telah melalui pemeriksaan dan rekaman fisik unboxing di gudang.</p>
                        <p class="text-slate-600">2. Dokumen ini merupakan surat tagihan sah penggantian klaim barang rusak.</p>
                        <p class="text-slate-600">3. Mohon pihak ekspedisi melakukan konfirmasi penyelesaian maksimal 14 hari kerja.</p>
                    </div>
                </div>

                <!-- LEMBAR TANDA TANGAN (3 KOLOM RESMI) -->
                <div class="grid grid-cols-3 gap-4 pt-8 text-center text-xs print-break-inside-avoid">
                    <div class="space-y-14">
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase font-bold">Dibuat Oleh,</span>
                            <span class="font-bold text-slate-700 text-[11px]">Staff Klaim & Inbound IEG</span>
                        </div>
                        <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 mx-3">
                            ( <?= htmlspecialchars($currentUser['username'] ?? 'Admin Gudang') ?> )
                        </div>
                    </div>

                    <div class="space-y-14">
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase font-bold">Mengetahui / Verifikasi,</span>
                            <span class="font-bold text-slate-700 text-[11px]">Supervisor / Ka. Gudang</span>
                        </div>
                        <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 mx-3">
                            ( ..................................... )
                        </div>
                    </div>

                    <div class="space-y-14">
                        <div>
                            <span class="text-slate-500 block text-[10px] uppercase font-bold">Diterima & Disetujui,</span>
                            <span class="font-bold text-slate-700 text-[11px]">PIC / Perwakilan Ekspedisi</span>
                        </div>
                        <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 mx-3">
                            ( ..................................... )
                        </div>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>

    <!-- SCRIPT COPY FORMAT WA -->
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
    </script>
</body>
</html>
