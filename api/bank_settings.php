<?php
/**
 * api/bank_settings.php
 * API Pengaturan Rekening Bank untuk Invoice Penagihan Klaim.
 * Dikhususkan untuk diakses dan dikelola oleh role: Management dan Accounting (serta Superadmin).
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$currentUser = getSessionUser();
if (!$currentUser) {
    jsonResponse(['error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

// Hak akses: Hanya Management, Accounting, dan Superadmin
$allowedRoles = ['superadmin', 'management', 'accounting'];
if (!in_array($currentUser['role'] ?? '', $allowedRoles, true)) {
    jsonResponse([
        'error' => 'Akses ditolak: Menu Pengaturan Bank hanya dapat diakses oleh Management dan Accounting.'
    ], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. GET BANK SETTINGS
if ($method === 'GET') {
    try {
        $bankSettings = [
            'bank_name'           => 'BCA (Bank Central Asia)',
            'bank_account_number' => '873-098-1234',
            'bank_account_holder' => 'PT. INOVASI EKA GEMILANG',
            'bank_payment_notes'  => '*Mohon sertakan nomor invoice pada berita transfer saat pembayaran.'
        ];

        $stmtBank = $pdo->query("SELECT key_name, key_value FROM system_settings WHERE key_name IN ('bank_name', 'bank_account_number', 'bank_account_holder', 'bank_payment_notes')");
        if ($stmtBank) {
            while ($bRow = $stmtBank->fetch()) {
                if ($bRow['key_value'] !== null && $bRow['key_value'] !== '') {
                    $bankSettings[$bRow['key_name']] = $bRow['key_value'];
                }
            }
        }

        jsonResponse([
            'success' => true,
            'data'    => $bankSettings
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal memuat pengaturan bank: ' . $e->getMessage()], 500);
    }
}

// 2. SAVE BANK SETTINGS
if ($method === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $bankName   = trim($input['bank_name'] ?? '');
        $accNum     = trim($input['bank_account_number'] ?? '');
        $accHolder  = trim($input['bank_account_holder'] ?? '');
        $notes      = trim($input['bank_payment_notes'] ?? '');

        if (empty($bankName) || empty($accNum) || empty($accHolder)) {
            jsonResponse(['error' => 'Nama Bank, No. Rekening, dan Atas Nama Rekening wajib diisi!'], 400);
        }

        $stmtUpsert = $pdo->prepare("
            INSERT INTO system_settings (key_name, key_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)
        ");

        $stmtUpsert->execute(['bank_name', $bankName]);
        $stmtUpsert->execute(['bank_account_number', $accNum]);
        $stmtUpsert->execute(['bank_account_holder', $accHolder]);
        $stmtUpsert->execute(['bank_payment_notes', $notes]);

        jsonResponse([
            'success' => true,
            'message' => 'Pengaturan Rekening Bank berhasil disimpan!',
            'data'    => [
                'bank_name'           => $bankName,
                'bank_account_number' => $accNum,
                'bank_account_holder' => $accHolder,
                'bank_payment_notes'  => $notes
            ]
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menyimpan pengaturan bank: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Metode HTTP tidak didukung'], 405);
