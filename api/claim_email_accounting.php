<?php
/**
 * api/claim_email_accounting.php
 * Endpoint Assign & Kirim Email ke Accounting untuk Paket Rusak (J&T & JNE).
 * 
 * Alur:
 * 1. Admin/Superadmin memilih beberapa paket (Multiple Select).
 * 2. Mengirim email rekap ke Accounting (HTML email + mailto fallback).
 * 3. Status paket otomatis berubah menjadi 'DONE_EMAIL' (Done Email).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

ensureClaimStatusColumn($pdo);

$currentUser = getSessionUser();
if (!$currentUser) {
    jsonResponse(['error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

$rawRole = strtolower(trim(str_replace([' ', '_', '-'], '', $currentUser['role'] ?? '')));
if (!in_array($rawRole, ['superadmin', 'admin', 'management'], true)) {
    jsonResponse(['error' => 'Akses ditolak: Hanya Admin/Superadmin yang berhak meng-assign paket ke Accounting.'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error' => 'Metode HTTP tidak diizinkan. Gunakan POST.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$invoices = $input['invoices'] ?? [];
if (is_string($invoices)) {
    $invoices = preg_split('/[\r\n,]+/', $invoices);
}
$invoices = array_values(array_unique(array_filter(array_map('trim', (array)$invoices), 'strlen')));

if (empty($invoices)) {
    jsonResponse(['error' => 'Pilih minimal 1 paket / resi untuk di-assign ke Accounting.'], 400);
}

// Ambil email tujuan accounting dari system_settings atau input
$accountingEmail = trim($input['accounting_email'] ?? '');
if (empty($accountingEmail)) {
    try {
        $stmtEmail = $pdo->prepare("SELECT key_value FROM system_settings WHERE key_name = 'accounting_email'");
        $stmtEmail->execute();
        $rowE = $stmtEmail->fetch();
        if ($rowE && !empty($rowE['key_value'])) {
            $accountingEmail = trim($rowE['key_value']);
        }
    } catch (Exception $eE) {}
}
if (empty($accountingEmail)) {
    $accountingEmail = 'accounting@ieg.co.id';
}

$senderName = $currentUser['name'] ?? 'Admin Inbound Return';

try {
    // 1. Ambil detail lengkap paket yang dipilih
    $placeholders = implode(',', array_fill(0, count($invoices), '?'));
    $sql = "
        SELECT rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.created_at, rs.notes,
               rs.claim_status, rs.accounting_status,
               COALESCE(SUM(ri.qty), rs.total_items) as total_items,
               COALESCE(SUM(CASE WHEN (ri.condition != 'GOOD' AND ri.condition != 'BAGUS') OR (ri.type != 'GOOD' AND ri.type != 'BAGUS') THEN ri.qty ELSE 0 END), rs.total_damaged) as damaged_qty,
               GROUP_CONCAT(DISTINCT ri.product_name SEPARATOR ', ') as product_names,
               GROUP_CONCAT(DISTINCT COALESCE(NULLIF(ri.seller_sku,''), ri.sku) SEPARATOR ', ') as skus,
               GROUP_CONCAT(DISTINCT ri.damage_reason SEPARATOR '; ') as damage_reasons
        FROM return_sessions rs
        LEFT JOIN return_items ri ON ri.session_id = rs.id
        WHERE rs.invoice_number IN ($placeholders)
        GROUP BY rs.id, rs.invoice_number, rs.expedition, rs.operator_name, rs.created_at, rs.notes, rs.claim_status, rs.accounting_status
        ORDER BY rs.created_at ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($invoices);
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($packages)) {
        jsonResponse(['error' => 'Data paket terpilih tidak ditemukan di database.'], 404);
    }

    // Ambil harga dari ocs_orders jika tersedia
    $totalClaimNominal = 0;
    try {
        $stmtOcs = $pdo->prepare("SELECT order_id, tracking_number, total_amount, package_price FROM ocs_orders WHERE tracking_number IN ($placeholders) OR order_id IN ($placeholders)");
        $stmtOcs->execute(array_merge($invoices, $invoices));
        $ocsPrices = [];
        while ($oRow = $stmtOcs->fetch(PDO::FETCH_ASSOC)) {
            $p = (float)($oRow['total_amount'] ?: $oRow['package_price'] ?: 0);
            if (!empty($oRow['tracking_number'])) $ocsPrices[trim($oRow['tracking_number'])] = $p;
            if (!empty($oRow['order_id'])) $ocsPrices[trim($oRow['order_id'])] = $p;
        }

        foreach ($packages as &$pkg) {
            $inv = trim($pkg['invoice_number']);
            $pr = $ocsPrices[$inv] ?? 0;
            $pkg['package_price'] = $pr;
            $totalClaimNominal += $pr;
        }
        unset($pkg);
    } catch (Exception $eOcs) {}

    // 2. Update status ke DONE_EMAIL di return_sessions
    $updateStmt = $pdo->prepare("
        UPDATE return_sessions 
        SET claim_status = 'DONE_EMAIL',
            accounting_status = 'DONE_EMAIL',
            email_accounting_at = NOW(),
            email_accounting_by = ?,
            claim_updated_at = NOW()
        WHERE invoice_number IN ($placeholders)
    ");
    $updateStmt->execute(array_merge([$senderName], $invoices));
    $updatedCount = $updateStmt->rowCount();

    // 3. Susun isi Email HTML & Plaintext
    $tanggalKirim = date('d-m-Y H:i:s');
    $subject = "Pengajuan Klaim Ekspedisi J&T / JNE (" . count($packages) . " Paket) - " . date('d/m/Y');

    // Buat tabel baris HTML
    $rowsHtml = '';
    $rowsPlain = '';
    $no = 1;
    foreach ($packages as $pkg) {
        $inv = htmlspecialchars($pkg['invoice_number']);
        $exp = htmlspecialchars($pkg['expedition'] ?: 'J&T / JNE');
        $prod = htmlspecialchars($pkg['product_names'] ?: 'Produk Retur');
        $sku = htmlspecialchars($pkg['skus'] ?: '-');
        $qty = (int)($pkg['damaged_qty'] ?: 1);
        $reason = htmlspecialchars($pkg['damage_reasons'] ?: ($pkg['notes'] ?: 'Rusak'));
        $price = $pkg['package_price'] > 0 ? 'Rp ' . number_format($pkg['package_price'], 0, ',', '.') : '-';
        $tglUnbox = !empty($pkg['created_at']) ? date('d-m-Y', strtotime($pkg['created_at'])) : '-';

        $rowsHtml .= "
            <tr style='border-bottom: 1px solid #e2e8f0;'>
                <td style='padding: 8px 10px; text-align: center; font-size: 11px;'>{$no}</td>
                <td style='padding: 8px 10px; font-family: monospace; font-weight: bold; color: #1e293b; font-size: 12px;'>{$inv}</td>
                <td style='padding: 8px 10px; font-size: 11px; font-weight: bold;'>{$exp}</td>
                <td style='padding: 8px 10px; font-size: 11px;'>{$prod}</td>
                <td style='padding: 8px 10px; font-family: monospace; font-size: 10px; color: #475569;'>{$sku}</td>
                <td style='padding: 8px 10px; text-align: center; font-size: 11px; font-weight: bold; color: #b91c1c;'>{$qty} pcs</td>
                <td style='padding: 8px 10px; font-size: 11px; color: #334155;'>{$reason}</td>
                <td style='padding: 8px 10px; text-align: right; font-weight: bold; color: #047857; font-size: 11px;'>{$price}</td>
                <td style='padding: 8px 10px; text-align: center; font-size: 10px; color: #64748b;'>{$tglUnbox}</td>
            </tr>
        ";

        $rowsPlain .= "{$no}. Resi: {$pkg['invoice_number']} | Ekspedisi: {$exp} | Qty Rusak: {$qty} | Alasan: {$reason} | Harga: {$price}\n";
        $no++;
    }

    $totalNominalFmt = $totalClaimNominal > 0 ? 'Rp ' . number_format($totalClaimNominal, 0, ',', '.') : 'Sesuai data OCS';

    $emailHtml = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #334155;'>
        <div style='max-width: 900px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
            <div style='background: #0f172a; padding: 20px; color: #ffffff;'>
                <h2 style='margin: 0; font-size: 18px; font-weight: bold; letter-spacing: -0.5px;'>PT. INOVASI EKA GEMILANG</h2>
                <p style='margin: 4px 0 0; font-size: 12px; color: #94a3b8;'>Pengajuan Berkas Klaim Retur Ekspedisi ke Divisi Accounting</p>
            </div>
            <div style='padding: 20px;'>
                <p style='font-size: 13px; line-height: 1.5;'>
                    Halo Rekan <b>Accounting IEG</b>,<br><br>
                    Berikut kami lampirkan daftar paket retur rusak ekspedisi <b>J&amp;T Express &amp; JNE Express</b> yang telah diverifikasi di Stasiun Unboxing Inbound Return dan siap untuk diproses klaim ke pihak ekspedisi.
                </p>

                <div style='background: #f1f5f9; padding: 12px 16px; border-radius: 8px; margin: 16px 0; font-size: 12px; display: flex; justify-content: space-between;'>
                    <div><b>Pengirim:</b> {$senderName} (Tim Inbound Return)</div>
                    <div><b>Waktu Pengiriman:</b> {$tanggalKirim}</div>
                    <div><b>Total Paket:</b> <b>" . count($packages) . " Paket</b></div>
                    <div><b>Total Nilai Klaim:</b> <b style='color: #047857;'>{$totalNominalFmt}</b></div>
                </div>

                <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                    <thead>
                        <tr style='background: #e2e8f0; color: #334155; text-align: left; font-size: 11px; text-transform: uppercase;'>
                            <th style='padding: 8px 10px; text-align: center; width: 30px;'>#</th>
                            <th style='padding: 8px 10px;'>No. Resi / Invoice</th>
                            <th style='padding: 8px 10px;'>Ekspedisi</th>
                            <th style='padding: 8px 10px;'>Nama Produk</th>
                            <th style='padding: 8px 10px;'>SKU</th>
                            <th style='padding: 8px 10px; text-align: center;'>Qty Rusak</th>
                            <th style='padding: 8px 10px;'>Alasan Cacat</th>
                            <th style='padding: 8px 10px; text-align: right;'>Total Nilai</th>
                            <th style='padding: 8px 10px; text-align: center;'>Tgl Unbox</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rowsHtml}
                    </tbody>
                </table>

                <div style='margin-top: 25px; padding: 16px; background: #e0e7ff; border-radius: 8px; font-size: 12px; color: #3730a3;'>
                    <b>Catatan untuk Accounting:</b><br>
                    1. Paket di atas kini berstatus <b>DONE EMAIL</b> di sistem Pusat Klaim Inbound Return.<br>
                    2. Rekan Accounting dapat membuka sistem untuk melakukan verifikasi <b>Receive</b>, melakukan <b>Edit Data</b> nominal/catatan bila diperlukan, dan menuntaskan berkas menjadi <b>Done Klaim</b>.<br>
                    3. Berkas digital, invoice, dan video/foto unboxing lengkap dapat dilihat langsung melalui portal Inbound Return.
                </div>
            </div>
            <div style='background: #f8fafc; padding: 12px 20px; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; text-align: center;'>
                Sistem Otomasi Inbound Return IEG &bull; Generated Automatically
            </div>
        </div>
    </body>
    </html>
    ";

    // Kirim via mail() PHP bila server mendukung
    $mailSent = false;
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Inbound Return IEG <noreply@ieg.co.id>\r\n";
    $headers .= "Reply-To: {$accountingEmail}\r\n";

    try {
        if (function_exists('mail')) {
            $mailSent = @mail($accountingEmail, $subject, $emailHtml, $headers);
        }
    } catch (Exception $eMail) {
        $mailSent = false;
    }

    // Bangun URL Mailto sebagai opsi buka aplikasi email lokal (Outlook / Gmail)
    $mailtoBody = "Halo Tim Accounting IEG,\n\nBerikut daftar pengajuan klaim paket retur rusak ekspedisi J&T / JNE (" . count($packages) . " paket):\n\n"
        . $rowsPlain
        . "\nTotal Nilai Klaim: {$totalNominalFmt}\n"
        . "Pengirim: {$senderName}\nTanggal: {$tanggalKirim}\n\nSilakan verifikasi dan terima di sistem Inbound Return.";
    
    $mailtoUrl = "mailto:{$accountingEmail}?subject=" . rawurlencode($subject) . "&body=" . rawurlencode($mailtoBody);

    jsonResponse([
        'success'           => true,
        'message'           => count($packages) . " paket (J&T / JNE) berhasil di-assign ke Accounting. Status kini berubah menjadi [Done Email].",
        'status'            => 'DONE_EMAIL',
        'updated_count'     => $updatedCount,
        'packages_count'    => count($packages),
        'total_nominal'     => $totalClaimNominal,
        'total_nominal_fmt' => $totalNominalFmt,
        'accounting_email'  => $accountingEmail,
        'mail_sent'         => $mailSent,
        'mailto_url'        => $mailtoUrl
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => 'Gagal memproses assign ke Accounting: ' . $e->getMessage()], 500);
}
