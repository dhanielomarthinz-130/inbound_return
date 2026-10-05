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

            // Hitung total qty rusak
            $damagedQty = 0;
            $prodNames = [];
            $reasons = [];

            if ($sess && !empty($sess['items'])) {
                foreach ($sess['items'] as $it) {
                    $itCond = strtoupper(trim($it['type'] ?? $it['condition'] ?? ''));
                    $isDmg = ($itCond !== 'GOOD' && $itCond !== 'BAGUS');
                    $q = (int)($it['qty'] ?? 1);
                    if ($isDmg || count($sess['items']) === 1) {
                        $damagedQty += $q;
                        $pTitle = $it['product_name'] ?: ($it['sku'] ?: $it['barcode']);
                        if (!empty($it['wrong_barcode'])) {
                            $pTitle .= " [Salah Kirim Fisik: " . ($it['wrong_product_name'] ?: $it['wrong_barcode']) . "]";
                        }
                        $prodNames[] = $pTitle;
                        if (!empty($it['damage_reason'])) {
                            $reasons[] = $it['damage_reason'];
                        } elseif (!empty($itCond) && $itCond !== 'GOOD') {
                            $reasons[] = $itCond;
                        }
                    }
                }
            }

            if ($damagedQty === 0) $damagedQty = 1;
            if (empty($prodNames) && $ocs && !empty($ocs['product_name'])) {
                $prodNames[] = $ocs['product_name'];
            }
            if (empty($reasons)) {
                $reasons[] = 'Kondisi Rusak Saat Unboxing';
            }

            $itemsData[] = [
                'no' => $idx++,
                'invoice_number' => $invKey,
                'tracking_number' => $ocs['tracking_number'] ?? $invKey,
                'expedition' => $exp,
                'shop_name' => $ocs['shop_name'] ?? '-',
                'platform' => $ocs['commerce_platform'] ?? '-',
                'products' => implode(' + ', array_unique($prodNames)) ?: 'Produk Retur',
                'damaged_qty' => $damagedQty,
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
    <title>Invoice Tagihan Klaim - <?= htmlspecialchars($docNumber) ?> - PT. IEG</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        @media print {
            @page { size: A4 portrait; margin: 8mm 10mm; }
            body { background: white !important; color: #0f172a !important; padding: 0 !important; font-size: 11px !important; }
            .no-print { display: none !important; }
            .print-shadow-none { box-shadow: none !important; }
            .print-border { border: 1px solid #cbd5e1 !important; }
            .print-break-inside-avoid { break-inside: avoid !important; page-break-inside: avoid !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 antialiased p-3 sm:p-6 lg:p-8">

    <div class="max-w-5xl mx-auto space-y-4">
        
        <!-- TOOLBAR ATAS (NO PRINT) -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-3 sm:p-4 flex flex-wrap items-center justify-between gap-3 no-print">
            <div class="flex items-center gap-2.5">
                <a href="admin.php#claims" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Kembali ke Pusat Klaim</span>
                </a>
                <span class="text-xs font-bold text-slate-300">|</span>
                <span class="text-xs text-slate-500 font-medium">Tagihan Kolektif: <b class="text-slate-800"><?= count($itemsData) ?> Paket</b></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="copyClaimInvoiceText()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                    <i class="fa-regular fa-copy"></i>
                    <span>Salin Format Teks</span>
                </button>
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/20 cursor-pointer">
                    <i class="fa-solid fa-print"></i>
                    <span>Cetak PDF (A4)</span>
                </button>
            </div>
        </div>

        <?php if (empty($itemsData)): ?>
            <!-- JIKA TIDAK ADA DATA TERPILIH -->
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-sm space-y-4">
                <div class="w-16 h-16 rounded-3xl bg-amber-50 text-amber-600 flex items-center justify-center text-3xl mx-auto shadow-inner">
                    <i class="fa-solid fa-clipboard-question"></i>
                </div>
                <div>
                    <h3 class="font-black text-lg text-slate-800">Belum Ada Paket yang Dipilih</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                        Silakan kembali ke <b>Pusat Klaim & Banding</b> di halaman Admin, lalu centang satu atau beberapa paket rusak untuk membuat tagihan klaim resmi.
                    </p>
                </div>
                <div class="pt-2">
                    <a href="admin.php#claims" class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl shadow-md transition">
                        <i class="fa-solid fa-shield-halved"></i> Buka Pusat Klaim
                    </a>
                </div>
            </div>
        <?php else: ?>

            <!-- DOKUMEN RESMI INVOICE TAGIHAN KLAIM (A4 READY) -->
            <div id="invoiceDocument" class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6 print:rounded-none print:border-none print:p-0 print:shadow-none">
                
                <!-- HEADER PERUSAHAAN & JUDUL DOKUMEN -->
                <div class="flex flex-col sm:flex-row items-start justify-between gap-4 border-b-2 border-slate-800 pb-5">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-base font-black shadow-xs">
                                IEG
                            </span>
                            <h1 class="font-black text-lg sm:text-xl text-slate-900 tracking-tight">PT. INOVASI EKA GEMILANG</h1>
                        </div>
                        <p class="text-xs text-slate-500 font-medium">Warehouse Reverse Logistics, Inbound Return & Claims Department</p>
                        <p class="text-[11px] text-slate-400">Portal Pengawasan Retur & Pengajuan Klaim Resmi Barang Rusak / Cacat</p>
                    </div>

                    <div class="text-left sm:text-right shrink-0">
                        <div class="inline-block px-3 py-1 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs font-black uppercase tracking-wider mb-1">
                            SURAT TAGIHAN KLAIM
                        </div>
                        <div class="text-xs font-mono font-bold text-slate-800">No: <span class="text-rose-600"><?= htmlspecialchars($docNumber) ?></span></div>
                        <div class="text-[11px] text-slate-500">Tanggal: <b class="text-slate-700"><?= date('d F Y') ?></b></div>
                    </div>
                </div>

                <!-- INFO TUJUAN EKSPEDISI & PENGESAHAN -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/80 text-xs">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Ditujukan Kepada:</span>
                        <div class="font-black text-sm text-slate-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-truck-fast text-amber-500"></i>
                            <span>Tim Klaim / Finance <?= htmlspecialchars($expeditionDisplay) ?></span>
                        </div>
                        <p class="text-slate-500 mt-1 leading-relaxed">
                            Perihal: Pengajuan penggantian kerugian paket retur pembeli yang terkonfirmasi rusak / cacat fisik saat unboxing di gudang.
                        </p>
                    </div>
                    <div class="sm:text-right flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Dibuat Oleh:</span>
                            <div class="font-bold text-slate-800"><?= htmlspecialchars($currentUser['username'] ?? 'Admin Gudang IEG') ?></div>
                            <div class="text-slate-500 text-[11px]">Staff Administrasi Retur & Klaim</div>
                        </div>
                        <div class="mt-2 text-[11px] text-slate-500">
                            Waktu Cetak: <b class="font-mono text-slate-700"><?= date('d/m/Y H:i:s') ?> WIB</b>
                        </div>
                    </div>
                </div>

                <!-- TABEL RINCIAN RESI & BARANG YANG DITAGIHKAN -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-800 text-white font-bold text-[10px] uppercase">
                                <th class="p-2.5 text-center w-8">#</th>
                                <th class="p-2.5">No. Resi (AWB)</th>
                                <th class="p-2.5">No. Order / Invoice</th>
                                <th class="p-2.5">Toko / Produk Rusak</th>
                                <th class="p-2.5 text-center w-16">Qty</th>
                                <th class="p-2.5">Alasan Kerusakan Fisik</th>
                                <th class="p-2.5 text-right w-28">Nilai Tagihan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php foreach ($itemsData as $row): ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-2.5 text-center font-bold text-slate-400"><?= $row['no'] ?></td>
                                    <td class="p-2.5 font-mono font-bold text-slate-800">
                                        <?= htmlspecialchars($row['tracking_number']) ?>
                                        <div class="text-[10px] text-amber-600 font-normal"><?= htmlspecialchars($row['expedition']) ?></div>
                                    </td>
                                    <td class="p-2.5 font-mono text-slate-600"><?= htmlspecialchars($row['invoice_number']) ?></td>
                                    <td class="p-2.5">
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($row['products']) ?></div>
                                        <div class="text-[10px] text-slate-400 font-medium">Toko: <?= htmlspecialchars($row['shop_name']) ?></div>
                                    </td>
                                    <td class="p-2.5 text-center font-bold text-rose-600 font-mono"><?= $row['damaged_qty'] ?></td>
                                    <td class="p-2.5 text-slate-700 font-medium"><?= htmlspecialchars($row['reason']) ?></td>
                                    <td class="p-2.5 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                        <?= ($row['price'] > 0) ? 'Rp ' . number_format($row['price'], 0, ',', '.') : '<span class="text-slate-400 italic">Rp 0</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-100 font-bold border-t-2 border-slate-800 text-xs">
                                <td colspan="4" class="p-2.5 text-right text-slate-700 uppercase">
                                    Total (<?= count($itemsData) ?> Paket):
                                </td>
                                <td class="p-2.5 text-center font-mono text-rose-700 text-sm">
                                    <?= $totalDamagedQty ?>
                                </td>
                                <td class="p-2.5 text-right text-slate-700 uppercase">
                                    Grand Total Tagihan:
                                </td>
                                <td class="p-2.5 text-right font-mono text-base font-black text-emerald-700 whitespace-nowrap">
                                    Rp <?= number_format($grandTotal, 0, ',', '.') ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- TERBILANG & CATATAN KLAIM -->
                <div class="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-2xl text-xs space-y-1">
                    <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Terbilang:</span>
                    <div class="font-bold text-emerald-950 italic text-sm">
                        "<?= htmlspecialchars($terbilangText) ?>"
                    </div>
                </div>

                <!-- CATATAN & KETENTUAN KLAIM -->
                <div class="text-[11px] text-slate-500 space-y-1 leading-relaxed border-l-2 border-amber-500 pl-3">
                    <b class="text-slate-700">Catatan & Pernyataan Resmi:</b>
                    <p>1. Seluruh barang di atas telah melalui proses verifikasi dan perekaman unboxing oleh tim gudang retur PT. Inovasi Eka Gemilang.</p>
                    <p>2. Bukti rekaman video pembukaan paket serta foto kerusakan fisik tersimpan aman dan dapat ditinjau melalui tautan portal klaim resmi.</p>
                    <p>3. Mohon pihak ekspedisi terkait dapat segera memproses penggantian klaim sesuai nominal di atas ke rekening resmi perusahaan.</p>
                </div>

                <!-- LEMBAR TANDA TANGAN 3 PIHAK (UNTUK CETAK FORMAL) -->
                <div class="grid grid-cols-3 gap-4 pt-6 text-center text-xs print-break-inside-avoid">
                    <div class="space-y-16">
                        <div>
                            <span class="text-slate-500 block">Dibuat Oleh,</span>
                            <span class="font-bold text-slate-700 text-[11px]">Staff Klaim & Retur IEG</span>
                        </div>
                        <div class="border-t border-slate-300 pt-1 font-bold text-slate-800">
                            ( <?= htmlspecialchars($currentUser['username'] ?? 'Admin Gudang') ?> )
                        </div>
                    </div>

                    <div class="space-y-16">
                        <div>
                            <span class="text-slate-500 block">Mengetahui / Verifikasi,</span>
                            <span class="font-bold text-slate-700 text-[11px]">Supervisor / Ka. Gudang</span>
                        </div>
                        <div class="border-t border-slate-300 pt-1 font-bold text-slate-800">
                            ( ..................................... )
                        </div>
                    </div>

                    <div class="space-y-16">
                        <div>
                            <span class="text-slate-500 block">Diterima & Disetujui,</span>
                            <span class="font-bold text-slate-700 text-[11px]">PIC / Perwakilan Ekspedisi</span>
                        </div>
                        <div class="border-t border-slate-300 pt-1 font-bold text-slate-800">
                            ( ..................................... )
                        </div>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>

    <!-- SCRIPT COPY TEKS FORMAT PESAN -->
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

            let text = `*SURAT TAGIHAN KLAIM BARANG RUSAK EKSPEDISI*\n`;
            text += `No. Tagihan: ${invoiceDocNumber}\n`;
            text += `Tanggal: <?= date('d/m/Y') ?>\n`;
            text += `Ekspedisi: ${expeditionName}\n`;
            text += `Jumlah Paket: ${invoiceDataItems.length} Paket\n`;
            text += `Total Tagihan: *${grandTotalFormatted}*\n\n`;
            text += `*Rincian Paket:*\n`;

            invoiceDataItems.forEach((it, idx) => {
                const pr = it.price > 0 ? ('Rp ' + Number(it.price).toLocaleString('id-ID')) : '-';
                text += `${idx + 1}. Resi: ${it.tracking_number} (${it.invoice_number})\n`;
                text += `   Barang: ${it.products}\n`;
                text += `   Qty: ${it.damaged_qty} pcs | Alasan: ${it.reason}\n`;
                text += `   Nominal: ${pr}\n\n`;
            });

            text += `Mohon segera diverifikasi dan diproses penggantian klaimnya. Terima kasih.\n`;
            text += `_PT. Inovasi Eka Gemilang - Reverse Logistics_`;

            navigator.clipboard.writeText(text).then(() => {
                alert('Format rekap tagihan klaim berhasil disalin ke clipboard! Silakan paste ke chat WhatsApp tim ekspedisi.');
            }).catch(e => {
                prompt('Salin teks tagihan berikut:', text);
            });
        }

        // Otomatis cetak jika ada parameter autoprint=1
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get('autoprint') === '1' || params.get('print') === '1') {
                setTimeout(() => {
                    window.print();
                }, 600);
            }
        });
    </script>
</body>
</html>
