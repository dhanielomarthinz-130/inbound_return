<?php
/**
 * api/sync_claim_approval.php
 * Endpoint Sinkronisasi & API Approval Klaim JNT Accounting (InfinityFree & Localhost).
 * 
 * Actions:
 * 1. receive_from_local (POST)      : Menerima data tabel klaim JNT dari Localhost (tanpa foto)
 * 2. list_for_accounting (GET)      : Menampilkan daftar klaim JNT untuk portal Accounting
 * 3. approve_reject (POST)          : Memberi approval / reject dengan e-sign digital
 * 4. get_approved_for_local (GET)   : Mengembalikan data approval untuk ditarik balik ke Localhost
 * 5. mark_synced_to_local (POST)    : Menandai status sudah ditarik balik ke Localhost
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
if (file_exists(__DIR__ . '/../sync_config.php')) {
    require_once __DIR__ . '/../sync_config.php';
}

ensureClaimStatusColumn($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$secretKey = defined('SYNC_SECRET_KEY') ? SYNC_SECRET_KEY : 'IEG_RETURN_SYNC_TOKEN_2026_X99A';

// Helper validasi token rahasia antar-server
function verifySyncSecretKey($secretKey) {
    $token = $_GET['key'] ?? $_SERVER['HTTP_X_SYNC_TOKEN'] ?? $_POST['sync_key'] ?? '';
    if (empty($token) || !hash_equals($secretKey, $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Akses ditolak: Token sinkronisasi tidak valid.']);
        exit;
    }
}

// Auto-migration kolom video_path pada jnt_claim_approvals
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM jnt_claim_approvals LIKE 'video_path'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE jnt_claim_approvals ADD COLUMN video_path VARCHAR(255) NULL AFTER items_summary");
    }
} catch (Exception $eCol) {}

// -------------------------------------------------------------
// 1. RECEIVE_FROM_LOCAL: Terima data tabel klaim JNT dari Localhost
// -------------------------------------------------------------
if ($action === 'receive_from_local') {
    verifySyncSecretKey($secretKey);

    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    if (!is_array($inputData)) $inputData = $_POST;

    $items = $inputData['items'] ?? [];
    if (empty($items)) {
        jsonResponse(['success' => false, 'error' => 'Tidak ada data klaim yang dikirim.'], 400);
    }

    try {
        $saved = 0;
        $stmtUpsert = $pdo->prepare("
            INSERT INTO jnt_claim_approvals (
                invoice_number, order_id, expedition, unboxing_date, operator_name,
                customer_name, damaged_reason, items_summary, video_path, total_claim_amount,
                total_claim_amount_fmt, status
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, 'PENDING'
            ) ON DUPLICATE KEY UPDATE
                order_id = VALUES(order_id),
                expedition = VALUES(expedition),
                unboxing_date = VALUES(unboxing_date),
                operator_name = VALUES(operator_name),
                customer_name = VALUES(customer_name),
                damaged_reason = VALUES(damaged_reason),
                items_summary = VALUES(items_summary),
                video_path = COALESCE(VALUES(video_path), video_path),
                total_claim_amount = VALUES(total_claim_amount),
                total_claim_amount_fmt = VALUES(total_claim_amount_fmt),
                updated_at = NOW()
        ");

        foreach ($items as $it) {
            $inv = trim($it['invoice_number'] ?? '');
            if (empty($inv)) continue;

            $totalAmt = (float)($it['total_claim_amount'] ?? 0);
            $totalFmt = trim($it['total_claim_amount_fmt'] ?? ('Rp ' . number_format($totalAmt, 0, ',', '.')));

            $stmtUpsert->execute([
                $inv,
                $it['order_id'] ?? null,
                $it['expedition'] ?? 'J&T',
                !empty($it['unboxing_date']) ? $it['unboxing_date'] : date('Y-m-d H:i:s'),
                $it['operator_name'] ?? 'Admin',
                $it['customer_name'] ?? 'Pelanggan Umum',
                $it['damaged_reason'] ?? 'Rusak Unboxing',
                $it['items_summary'] ?? '-',
                !empty($it['video_path']) ? $it['video_path'] : null,
                $totalAmt,
                $totalFmt
            ]);
            $saved++;
        }

        jsonResponse([
            'success' => true,
            'saved'   => $saved,
            'message' => "{$saved} data klaim JNT berhasil diterima di Cloud untuk diajukan ke Accounting!"
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Gagal menyimpan data klaim di cloud: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// 2. LIST_FOR_ACCOUNTING: Ambil data klaim JNT untuk portal Accounting
// -------------------------------------------------------------
if ($action === 'list_for_accounting') {
    // Bisa diakses oleh session Accounting / Management atau sync key
    $user = getSessionUser();
    $token = $_GET['key'] ?? '';
    if (!$user && (empty($token) || !hash_equals($secretKey, $token))) {
        jsonResponse(['error' => 'Unauthorized. Silakan login sebagai Accounting atau sertakan sync key.'], 401);
    }

    $filterStatus = strtoupper(trim($_GET['status'] ?? 'ALL'));
    $search = trim($_GET['search'] ?? '');

    try {
        $where = [];
        $params = [];

        if ($filterStatus !== 'ALL' && in_array($filterStatus, ['PENDING', 'APPROVED', 'REJECTED'])) {
            $where[] = "j.status = ?";
            $params[] = $filterStatus;
        }

        if (!empty($search)) {
            $where[] = "(j.invoice_number LIKE ? OR j.order_id LIKE ? OR j.items_summary LIKE ? OR j.operator_name LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Hitung total dan statistik status
        $statStmt = $pdo->query("
            SELECT 
                COUNT(*) as total_all,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as total_pending,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as total_approved,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as total_rejected,
                SUM(CASE WHEN status = 'APPROVED' THEN total_claim_amount ELSE 0 END) as sum_approved_amount,
                SUM(total_claim_amount) as sum_total_amount
            FROM jnt_claim_approvals
        ");
        $stats = $statStmt->fetch(PDO::FETCH_ASSOC);

        $listStmt = $pdo->prepare("
            SELECT j.*, COALESCE(j.video_path, rs.video_path) AS video_path
            FROM jnt_claim_approvals j
            LEFT JOIN return_sessions rs ON rs.invoice_number = j.invoice_number
            {$whereSql}
            ORDER BY j.id DESC
            LIMIT 500
        ");
        $listStmt->execute($params);
        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'success' => true,
            'stats'   => $stats,
            'items'   => $rows
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Gagal memuat data approval: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// 3. APPROVE_REJECT: Aksi Persetujuan / Penolakan oleh Accounting
// -------------------------------------------------------------
if ($action === 'approve_reject') {
    $user = getSessionUser();
    // Memeriksa izin: Accounting, Management, Admin, atau Superadmin (dengan toleransi spasi)
    $roleClean = strtolower(trim(str_replace([' ', '_', '-'], '', $user['role'] ?? '')));
    if (!$user || !in_array($roleClean, ['accounting', 'management', 'superadmin', 'admin'], true)) {
        jsonResponse(['error' => 'Akses ditolak: Hanya Accounting, Management, atau Superadmin yang berhak memberikan approval.'], 403);
    }

    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    if (!is_array($inputData)) $inputData = $_POST;

    $invoices   = (array)($inputData['invoices'] ?? []);
    $decision   = strtoupper(trim($inputData['decision'] ?? 'APPROVE')); // APPROVE atau REJECT
    $notes      = trim($inputData['notes'] ?? '');
    $approver   = trim($inputData['approved_by'] ?? ($user['name'] ?: $user['username']));
    $esignData  = trim($inputData['esign_data'] ?? ''); // signature canvas base64 atau auto hash

    if (empty($invoices)) {
        jsonResponse(['error' => 'Pilih minimal 1 nomor resi / klaim!'], 400);
    }

    $newStatus = ($decision === 'APPROVE') ? 'APPROVED' : 'REJECTED';
    $now = date('Y-m-d H:i:s');

    try {
        $updated = 0;
        $ph = implode(',', array_fill(0, count($invoices), '?'));

        // Generate token digital e-sign unik
        $esignToken = 'ESIGN-JNT-' . date('Ymd-His') . '-' . strtoupper(substr(md5($approver . time() . implode(',', $invoices)), 0, 8));

        $stmt = $pdo->prepare("
            UPDATE jnt_claim_approvals 
            SET status = ?, 
                approved_by = ?, 
                approved_at = ?, 
                esign_token = ?, 
                notes = ?, 
                synced_back_to_local = 0,
                updated_at = NOW()
            WHERE invoice_number IN ($ph)
        ");

        $execParams = array_merge([$newStatus, $approver, $now, $esignToken, $notes], $invoices);
        $stmt->execute($execParams);
        $updated = $stmt->rowCount();

        jsonResponse([
            'success'     => true,
            'status'      => $newStatus,
            'updated'     => $updated,
            'esign_token' => $esignToken,
            'approved_by' => $approver,
            'approved_at' => $now,
            'message'     => count($invoices) . " klaim JNT berhasil di-{$newStatus} oleh Accounting!"
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal memproses approval: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// 4. GET_APPROVED_FOR_LOCAL: Ditarik oleh Localhost saat Sync
// -------------------------------------------------------------
if ($action === 'get_approved_for_local') {
    verifySyncSecretKey($secretKey);

    try {
        // Ambil data yang sudah berstatus APPROVED atau REJECTED
        $stmt = $pdo->query("
            SELECT invoice_number, order_id, status, approved_by, approved_at, esign_token, notes
            FROM jnt_claim_approvals
            WHERE status IN ('APPROVED', 'REJECTED')
            ORDER BY updated_at ASC
            LIMIT 500
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'success' => true,
            'total'   => count($results),
            'items'   => $results
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Gagal mengambil status approval cloud: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// 5. MARK_SYNCED_TO_LOCAL: Tandai bahwa Localhost sudah sukses sync
// -------------------------------------------------------------
if ($action === 'mark_synced_to_local') {
    verifySyncSecretKey($secretKey);

    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    if (!is_array($inputData)) $inputData = $_POST;

    $invoices = (array)($inputData['invoices'] ?? []);
    if (!empty($invoices)) {
        try {
            $ph = implode(',', array_fill(0, count($invoices), '?'));
            $stmt = $pdo->prepare("UPDATE jnt_claim_approvals SET synced_back_to_local = 1 WHERE invoice_number IN ($ph)");
            $stmt->execute($invoices);
        } catch (Exception $e) {}
    }

    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Action tidak valid'], 400);
