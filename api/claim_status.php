<?php
/**
 * api/claim_status.php
 * Update status klaim paket unboxing rusak (Pusat Klaim & Accounting).
 * Mendukung:
 * - PENDING (Belum Klaim)
 * - PROCESS (Proses Klaim)
 * - DONE_EMAIL (Done Email ke Accounting)
 * - RECEIVED (Diterima oleh Accounting)
 * - DONE (Done Klaim / Selesai Klaim)
 * Serta edit catatan klaim oleh Accounting / Admin.
 */
require_once __DIR__ . '/../config.php';

$sessionUser = getSessionUser();
if (!$sessionUser) {
    jsonResponse(['error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

$rawRole = strtolower(trim(str_replace([' ', '_', '-'], '', $sessionUser['role'] ?? '')));
$allowedRoles = ['admin', 'superadmin', 'management', 'accounting'];
if (!in_array($rawRole, $allowedRoles, true)) {
    jsonResponse(['error' => 'Akses ditolak. Anda tidak memiliki wewenang untuk mengubah status klaim.'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error' => 'Metode tidak diizinkan'], 405);
}

ensureClaimStatusColumn($pdo);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$status   = strtoupper(trim($input['status'] ?? ''));
$invoices = $input['invoices'] ?? [];
$notes    = trim($input['notes'] ?? $input['accounting_notes'] ?? '');

// Normalisasi alias status
if ($status === 'DONE_KLAIM') $status = 'DONE';
if ($status === 'RECEIVE')    $status = 'RECEIVED';

$allowed = ['PENDING', 'PROCESS', 'DONE', 'DONE_EMAIL', 'RECEIVED'];
if (!in_array($status, $allowed, true)) {
    jsonResponse(['error' => 'Status klaim tidak valid. Gunakan: PENDING, PROCESS, DONE_EMAIL, RECEIVED, atau DONE.'], 400);
}

// Accounting permissions check: Accounting hanya bisa Receive atau Edit Data / Done Klaim
if ($rawRole === 'accounting' && !in_array($status, ['RECEIVED', 'DONE'], true)) {
    jsonResponse(['error' => 'Role Accounting hanya memiliki akses untuk Receive atau menyelesaikan (Done Klaim).'], 403);
}

if (is_string($invoices)) {
    $invoices = preg_split('/[\r\n,]+/', $invoices);
}
$invoices = array_values(array_unique(array_filter(array_map('trim', (array)$invoices), 'strlen')));

if (empty($invoices)) {
    jsonResponse(['error' => 'Pilih minimal 1 paket / resi.'], 400);
}

$userName = $sessionUser['name'] ?? $sessionUser['username'] ?? 'Petugas';

try {
    $updated = 0;
    foreach (array_chunk($invoices, 200) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        
        if ($status === 'RECEIVED') {
            // Accounting Menerima Paket
            $sql = "UPDATE return_sessions 
                    SET accounting_status = 'RECEIVED',
                        accounting_received_at = NOW(),
                        accounting_received_by = ?,
                        claim_updated_at = NOW()" . 
                    ($notes !== '' ? ", accounting_notes = ?" : "") . 
                    " WHERE invoice_number IN ($ph)";
            $params = ($notes !== '') 
                ? array_merge([$userName, $notes], $chunk) 
                : array_merge([$userName], $chunk);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $updated += $stmt->rowCount();
        } elseif ($status === 'DONE_EMAIL') {
            // Paket di-email ke Accounting
            $sql = "UPDATE return_sessions 
                    SET claim_status = 'DONE_EMAIL',
                        accounting_status = 'DONE_EMAIL',
                        email_accounting_at = NOW(),
                        email_accounting_by = ?,
                        claim_updated_at = NOW()
                    WHERE invoice_number IN ($ph)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$userName], $chunk));
            $updated += $stmt->rowCount();
        } elseif ($status === 'DONE') {
            // Done Klaim (Bisa oleh Admin ekspedisi lain, atau oleh Accounting untuk JNT/JNE)
            $sql = "UPDATE return_sessions 
                    SET claim_status = 'DONE',
                        accounting_status = CASE WHEN ? = 'accounting' THEN 'DONE' ELSE COALESCE(accounting_status, 'DONE') END,
                        claim_updated_at = NOW()" . 
                    ($notes !== '' ? ", accounting_notes = ?" : "") . 
                    " WHERE invoice_number IN ($ph)";
            $params = ($notes !== '') 
                ? array_merge([$rawRole, $notes], $chunk) 
                : array_merge([$rawRole], $chunk);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $updated += $stmt->rowCount();
        } else {
            // PENDING atau PROCESS
            $sql = "UPDATE return_sessions 
                    SET claim_status = ?,
                        claim_updated_at = NOW() 
                    WHERE invoice_number IN ($ph)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$status], $chunk));
            $updated += $stmt->rowCount();
        }
    }

    $labels = [
        'PENDING'    => 'Belum Klaim',
        'PROCESS'    => 'Proses Klaim',
        'DONE_EMAIL' => 'Done Email',
        'RECEIVED'   => 'Diterima Accounting',
        'DONE'       => 'Done Klaim'
    ];
    jsonResponse([
        'success' => true,
        'status'  => $status,
        'updated' => $updated,
        'message' => count($invoices) . " paket berhasil diubah ke status [{$labels[$status]}]."
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => 'Gagal memperbarui status klaim: ' . $e->getMessage()], 500);
}

