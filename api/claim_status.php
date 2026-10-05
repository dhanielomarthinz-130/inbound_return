<?php
/**
 * api/claim_status.php
 * Update status klaim paket unboxing rusak (Pusat Klaim).
 * Alur status: PENDING (Belum Klaim) -> PROCESS (Proses Klaim) -> DONE (Done Claim)
 */
require_once __DIR__ . '/../config.php';

$sessionUser = getSessionUser();
if (!$sessionUser) {
    jsonResponse(['error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}
if (!in_array($sessionUser['role'] ?? '', ['admin', 'superadmin'])) {
    jsonResponse(['error' => 'Akses ditolak. Hanya Admin yang dapat mengubah status klaim.'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error' => 'Metode tidak diizinkan'], 405);
}

ensureClaimStatusColumn($pdo);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$status   = strtoupper(trim($input['status'] ?? ''));
$invoices = $input['invoices'] ?? [];

$allowed = ['PENDING', 'PROCESS', 'DONE'];
if (!in_array($status, $allowed, true)) {
    jsonResponse(['error' => 'Status klaim tidak valid. Gunakan: PENDING, PROCESS, atau DONE.'], 400);
}

if (is_string($invoices)) {
    $invoices = preg_split('/[\r\n,]+/', $invoices);
}
$invoices = array_values(array_unique(array_filter(array_map('trim', (array)$invoices), 'strlen')));

if (empty($invoices)) {
    jsonResponse(['error' => 'Pilih minimal 1 paket / resi.'], 400);
}

try {
    $updated = 0;
    foreach (array_chunk($invoices, 200) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("UPDATE return_sessions SET claim_status = ?, claim_updated_at = NOW() WHERE invoice_number IN ($ph)");
        $stmt->execute(array_merge([$status], $chunk));
        $updated += $stmt->rowCount();
    }

    $labels = ['PENDING' => 'Belum Klaim', 'PROCESS' => 'Proses Klaim', 'DONE' => 'Done Claim'];
    jsonResponse([
        'success' => true,
        'status'  => $status,
        'updated' => $updated,
        'message' => count($invoices) . " paket berhasil diubah ke status [{$labels[$status]}]."
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => 'Gagal memperbarui status klaim: ' . $e->getMessage()], 500);
}
