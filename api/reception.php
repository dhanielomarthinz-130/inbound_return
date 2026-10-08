<?php
require_once __DIR__ . '/../config.php';
$user = requireLogin(['operator', 'admin', 'superadmin', 'management', 'accounting']);
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// Nomor tanda terima berikutnya (RCV-YYYYMMDD-XXXX) berdasarkan urutan TERBESAR hari ini
// (bukan baris terakhir), dipakai oleh generate_id, auto-generate, dan saat nomor bentrok.
// $after: nomor yang barusan bentrok (opsional) -> hasil dijamin lebih besar dari nomor tsb,
// sehingga retry di dalam transaksi (snapshot REPEATABLE READ) tetap maju.
if (!function_exists('nextReceiptNumber')) {
    function nextReceiptNumber(PDO $pdo, string $after = ''): string {
        $prefix = 'RCV-' . date('Ymd') . '-';
        $st = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)), 0)
            FROM expedition_receptions
            WHERE receipt_number LIKE ?
        ");
        $st->execute([$prefix . '%']);
        $seq = (int)$st->fetchColumn() + 1;
        if ($after !== '' && strpos($after, $prefix) === 0) {
            $afterSeq = (int)substr($after, strlen($prefix));
            if ($seq <= $afterSeq) {
                $seq = $afterSeq + 1;
            }
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}

// ==========================================
// 1. GET: GENERATE ID, LIST, ATAU DETAIL
// ==========================================
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    // A. Generate Receipt Number unik (RCV-YYYYMMDD-XXXX)
    if ($action === 'generate_id') {
        $newId = nextReceiptNumber($pdo);
        jsonResponse([
            'success' => true,
            'receipt_number' => $newId
        ]);
    }

    // A2. Cek apakah Resi / Barcode sudah pernah diterima di database sebelumnya (Anti Double Input)
    if ($action === 'check_barcode') {
        $barcode = trim($_GET['barcode'] ?? '');
        if ($barcode === '') {
            jsonResponse(['exists' => false]);
        }
        $stmt = $pdo->prepare("
            SELECT rp.id, rp.package_barcode, rp.sack_number, rp.scanned_at, er.receipt_number, er.expedition, er.courier_name, er.created_at
            FROM reception_packages rp
            JOIN expedition_receptions er ON rp.reception_id = er.id
            WHERE rp.package_barcode = ?
            ORDER BY rp.id DESC
            LIMIT 1
        ");
        $stmt->execute([$barcode]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            $scanTime = $found['scanned_at'] ?: $found['created_at'];
            jsonResponse([
                'exists' => true,
                'message' => "Resi {$barcode} sudah pernah diterima pada No. Terima {$found['receipt_number']} ({$found['expedition']}) oleh {$found['courier_name']} [Waktu: {$scanTime}]",
                'data' => $found
            ]);
        } else {
            jsonResponse(['exists' => false]);
        }
    }

    // B. Detail Penerimaan beserta daftar resi/paketnya (Dukung ID atau No. Tanda Terima)
    if ($action === 'detail') {
        $id = intval($_GET['id'] ?? 0);
        $receiptNumber = trim($_GET['receipt_number'] ?? $_GET['receipt_no'] ?? '');

        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM expedition_receptions WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
        } elseif (!empty($receiptNumber)) {
            $stmt = $pdo->prepare("SELECT * FROM expedition_receptions WHERE receipt_number = ? LIMIT 1");
            $stmt->execute([$receiptNumber]);
        } else {
            jsonResponse(['error' => 'ID atau No. Tanda Terima tidak valid'], 400);
        }
        $reception = $stmt->fetch();

        if (!$reception) {
            jsonResponse(['error' => 'Data penerimaan tidak ditemukan'], 404);
        }
        $id = intval($reception['id']);

        $stmtPkg = $pdo->prepare("
            SELECT id, package_barcode, sack_number, photo_path, scanned_at 
            FROM reception_packages 
            WHERE reception_id = ? 
            ORDER BY id ASC
        ");
        $stmtPkg->execute([$id]);
        $packages = $stmtPkg->fetchAll();
        $actualTotal = count($packages);
        if ($actualTotal > 0 && intval($reception['total_packages'] ?? 0) !== $actualTotal) {
            $reception['total_packages'] = $actualTotal;
            try {
                $pdo->prepare("UPDATE expedition_receptions SET total_packages = ? WHERE id = ?")->execute([$actualTotal, $id]);
            } catch (Exception $eSyncCount) {}
        }

        // Normalisasi dan perbaiki package_photos jika ada duplikasi foto
        $pkgPhotoList = array_values(array_filter(array_column($packages, 'photo_path')));
        if (!empty($reception['package_photos'])) {
            $decPhotos = is_array($reception['package_photos']) ? $reception['package_photos'] : json_decode($reception['package_photos'], true);
            if (is_array($decPhotos)) {
                // Jika jumlah foto di package_photos lebih banyak dari jumlah foto paket, bersihkan duplikat rcv_
                if (count($pkgPhotoList) > 0 && count($decPhotos) > count($pkgPhotoList)) {
                    $cleanDecPhotos = array_values(array_filter($decPhotos, function($p) {
                        return strpos($p, 'rcv_') === false;
                    }));
                    if (count($cleanDecPhotos) >= count($pkgPhotoList)) {
                        $reception['package_photos'] = json_encode($cleanDecPhotos);
                        try {
                            $pdo->prepare("UPDATE expedition_receptions SET package_photos = ? WHERE id = ?")->execute([$reception['package_photos'], $id]);
                        } catch (Exception $eFixDup) {}
                    }
                }
            }
        }

        jsonResponse([
            'success' => true,
            'reception' => $reception,
            'packages' => $packages
        ]);
    }

    // C. List Riwayat Penerimaan (Mendukung view=packages per-paket dan view=sessions)
    $view       = trim($_GET['view'] ?? '');
    $startDate  = trim($_GET['start_date'] ?? '');
    $endDate    = trim($_GET['end_date'] ?? '');
    $date       = trim($_GET['date'] ?? '');
    $expedition = trim($_GET['expedition'] ?? '');
    $search     = trim($_GET['search'] ?? '');

    // 1. Tampilan PER-PAKET (Individu Barcode / Resi)
    if ($view === 'packages') {
        $where = [];
        $params = [];

        if (!empty($startDate) && !empty($endDate)) {
            $where[] = "((p.scanned_at >= ? AND p.scanned_at <= ?) OR (p.scanned_at IS NULL AND r.created_at >= ? AND r.created_at <= ?))";
            $sTs = $startDate . ' 00:00:00';
            $eTs = $endDate . ' 23:59:59';
            $params[] = $sTs;
            $params[] = $eTs;
            $params[] = $sTs;
            $params[] = $eTs;
        } elseif (!empty($date)) {
            $where[] = "((p.scanned_at >= ? AND p.scanned_at <= ?) OR (p.scanned_at IS NULL AND r.created_at >= ? AND r.created_at <= ?))";
            $sTs = $date . ' 00:00:00';
            $eTs = $date . ' 23:59:59';
            $params[] = $sTs;
            $params[] = $eTs;
            $params[] = $sTs;
            $params[] = $eTs;
        }

        if (!empty($expedition)) {
            $where[] = "r.expedition = ?";
            $params[] = $expedition;
        }

        if (!empty($search)) {
            $where[] = "(p.package_barcode LIKE ? OR r.receipt_number LIKE ? OR r.courier_name LIKE ? OR r.operator_name LIKE ? OR r.expedition LIKE ? OR r.sack_number LIKE ? OR p.sack_number LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereSql = count($where) > 0 ? implode(' AND ', $where) : '1=1';

        try {
            $stmtPkg = $pdo->prepare("
                SELECT 
                    p.id AS package_id,
                    p.reception_id,
                    p.package_barcode,
                    COALESCE(NULLIF(TRIM(p.sack_number), ''), NULLIF(TRIM(r.sack_number), ''), 'Karung 1') AS sack_number,
                    p.photo_path AS package_photo,
                    COALESCE(p.scanned_at, r.created_at) AS scanned_at,
                    r.receipt_number,
                    r.expedition,
                    r.courier_name,
                    r.courier_photo,
                    r.vehicle_no,
                    r.operator_name,
                    r.total_packages,
                    r.created_at AS reception_created_at
                FROM reception_packages p
                JOIN expedition_receptions r ON p.reception_id = r.id
                WHERE {$whereSql}
                ORDER BY p.id DESC
            ");
            $stmtPkg->execute($params);
            $packageRows = $stmtPkg->fetchAll();
        } catch (PDOException $e) {
            // Auto-repair skema jika belum ada kolom
            if (function_exists('ensureDatabaseSchema')) {
                try { ensureDatabaseSchema($pdo); } catch (Exception $ign) {}
            }
            try {
                $stmtPkg = $pdo->prepare("
                    SELECT 
                        p.id AS package_id,
                        p.reception_id,
                        p.package_barcode,
                        p.photo_path AS package_photo,
                        COALESCE(p.scanned_at, r.created_at) AS scanned_at,
                        r.receipt_number,
                        r.expedition,
                        r.courier_name,
                        r.courier_photo,
                        r.vehicle_no,
                        r.operator_name,
                        r.total_packages,
                        r.created_at AS reception_created_at
                    FROM reception_packages p
                    JOIN expedition_receptions r ON p.reception_id = r.id
                    WHERE {$whereSql}
                    ORDER BY p.id DESC
                ");
                $stmtPkg->execute($params);
                $packageRows = $stmtPkg->fetchAll();
            } catch (Exception $e2) {
                $packageRows = [];
            }
        }

        $totalPhotos = 0;
        $uniqueExpeditions = [];
        foreach ($packageRows as $pr) {
            if (!empty($pr['package_photo'])) $totalPhotos++;
            if (!empty($pr['expedition']) && !in_array($pr['expedition'], $uniqueExpeditions)) {
                $uniqueExpeditions[] = $pr['expedition'];
            }
        }

        jsonResponse([
            'success' => true,
            'view' => 'packages',
            'date' => $date ?: "$startDate s/d $endDate",
            'total' => count($packageRows),
            'total_packages' => count($packageRows),
            'total_photos' => $totalPhotos,
            'total_expeditions' => count($uniqueExpeditions),
            'data' => $packageRows
        ]);
    }

    // 2. Tampilan DEFAULT (Sesi Header Penerimaan untuk Dashboard Admin)
    $where = [];
    $params = [];

    if (!empty($startDate) && !empty($endDate)) {
        $where[] = "r.created_at >= ? AND r.created_at <= ?";
        $params[] = $startDate . ' 00:00:00';
        $params[] = $endDate . ' 23:59:59';
    } elseif (!empty($date)) {
        $where[] = "r.created_at >= ? AND r.created_at <= ?";
        $params[] = $date . ' 00:00:00';
        $params[] = $date . ' 23:59:59';
    }

    if (!empty($expedition)) {
        $where[] = "r.expedition = ?";
        $params[] = $expedition;
    }

    if (!empty($search)) {
        $where[] = "(r.receipt_number LIKE ? OR r.courier_name LIKE ? OR r.operator_name LIKE ? OR r.expedition LIKE ? OR r.sack_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSql = count($where) > 0 ? implode(' AND ', $where) : '1=1';

    $rows = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                r.id, 
                r.receipt_number, 
                r.expedition, 
                r.courier_name, 
                COALESCE(NULLIF(TRIM(r.sack_number), ''), 'Karung 1') AS sack_number, 
                r.courier_photo, 
                r.vehicle_no, 
                r.operator_name, 
                r.total_packages, 
                r.notes, 
                r.photo_path, 
                r.package_photos, 
                r.status, 
                r.created_at
            FROM expedition_receptions r
            WHERE {$whereSql}
            ORDER BY r.id DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Jika tabel atau kolom baru belum ada di hosting, jalankan auto-repair
        if (function_exists('ensureDatabaseSchema')) {
            try {
                ensureDatabaseSchema($pdo);
            } catch (Exception $ign) {}
        }

        try {
            // Coba lagi dengan kolom foto
            $stmt = $pdo->prepare("
                SELECT 
                    r.id, 
                    r.receipt_number, 
                    r.expedition, 
                    r.courier_name, 
                    COALESCE(NULLIF(TRIM(r.sack_number), ''), 'Karung 1') AS sack_number, 
                    r.courier_photo, 
                    r.vehicle_no, 
                    r.operator_name, 
                    r.total_packages, 
                    r.notes, 
                    r.photo_path, 
                    r.package_photos, 
                    r.status, 
                    r.created_at
                FROM expedition_receptions r
                WHERE {$whereSql}
                ORDER BY r.id DESC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e2) {
            try {
                // Fallback jika hosting belum mengizinkan kolom photo_path / package_photos
                $stmtFallback = $pdo->prepare("
                    SELECT id, receipt_number, expedition, courier_name, NULL as sack_number, NULL as courier_photo, vehicle_no, operator_name, total_packages, notes, NULL as photo_path, NULL as package_photos, status, created_at
                    FROM expedition_receptions
                    WHERE {$whereSql}
                    ORDER BY id DESC
                ");
                $stmtFallback->execute($params);
                $rows = $stmtFallback->fetchAll();
            } catch (PDOException $e3) {
                $rows = [];
            }
        }
    }

    jsonResponse([
        'success' => true,
        'date' => $date ?: "$startDate s/d $endDate",
        'total' => count($rows),
        'data' => $rows
    ]);
}

// ==========================================
// 2. DELETE: HAPUS PENERIMAAN (KHUSUS ADMIN)
// ==========================================
if ($method === 'DELETE' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete')) {
    if (!in_array($user['role'], ['admin', 'superadmin'])) {
        jsonResponse(['error' => 'Akses ditolak. Hanya Admin yang dapat menghapus data serah terima.'], 403);
    }

    $id = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
    if ($id <= 0) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
    }

    if ($id <= 0) {
        jsonResponse(['error' => 'ID Penerimaan tidak valid'], 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM expedition_receptions WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Data penerimaan berhasil dihapus']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menghapus: ' . $e->getMessage()], 500);
    }
}

// ==========================================
// 1b. POST ?action=update_expedition : EDIT EKSPEDISI & DATA PENERIMAAN
// ==========================================
if ($method === 'POST' && (($_GET['action'] ?? '') === 'update_expedition' || ($_POST['action'] ?? '') === 'update_expedition')) {
    $rawUserRole = strtolower(trim(str_replace([' ', '_', '-'], '', $user['role'] ?? '')));
    if ($rawUserRole !== 'superadmin') {
        jsonResponse(['error' => 'Akses ditolak. Fitur edit ekspedisi hanya diizinkan untuk Super Admin.'], 403);
    }

    $input = [];
    if (!empty($_POST['expedition']) || !empty($_POST['id'])) {
        $input = $_POST;
    } else {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $input = json_decode(preg_replace('/^[\xEF\xBB\xBF]+/', '', trim($raw)), true) ?: [];
        }
    }

    $id = intval($_GET['id'] ?? ($input['id'] ?? 0));
    $newExpedition = trim($input['expedition'] ?? '');
    $courierName = isset($input['courier_name']) ? trim($input['courier_name']) : null;
    $sackNumber = isset($input['sack_number']) ? trim($input['sack_number']) : null;
    $vehicleNo = isset($input['vehicle_no']) ? trim($input['vehicle_no']) : null;
    $notes = isset($input['notes']) ? trim($input['notes']) : null;

    if ($id <= 0) {
        jsonResponse(['error' => 'ID Penerimaan tidak valid.'], 400);
    }
    if (empty($newExpedition)) {
        jsonResponse(['error' => 'Nama ekspedisi tidak boleh kosong.'], 400);
    }

    try {
        $stmtChk = $pdo->prepare("SELECT id, receipt_number, expedition FROM expedition_receptions WHERE id = ?");
        $stmtChk->execute([$id]);
        $curr = $stmtChk->fetch(PDO::FETCH_ASSOC);
        if (!$curr) {
            jsonResponse(['error' => 'Data penerimaan tidak ditemukan.'], 404);
        }

        $fields = ["expedition = ?"];
        $params = [$newExpedition];

        if ($courierName !== null) {
            $fields[] = "courier_name = ?";
            $params[] = $courierName;
        }
        if ($sackNumber !== null) {
            $fields[] = "sack_number = ?";
            $params[] = $sackNumber;
        }
        if ($vehicleNo !== null) {
            $fields[] = "vehicle_no = ?";
            $params[] = $vehicleNo;
        }
        if ($notes !== null) {
            $fields[] = "notes = ?";
            $params[] = $notes;
        }

        $params[] = $id;
        $sql = "UPDATE expedition_receptions SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmtUpd = $pdo->prepare($sql);
        $stmtUpd->execute($params);

        jsonResponse([
            'success' => true,
            'message' => "Ekspedisi tanda terima {$curr['receipt_number']} berhasil diperbarui menjadi '{$newExpedition}'.",
            'id' => $id,
            'old_expedition' => $curr['expedition'],
            'new_expedition' => $newExpedition
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal mengubah ekspedisi: ' . $e->getMessage()], 500);
    }
}

// ==========================================
// 2a. POST ?action=upload_photo : UNGGAH 1 FOTO (Dipakai sebelum submit final)
//     Foto diunggah satu per satu agar request submit final kecil dan tidak
//     pernah melebihi batas post_max_size server.
// ==========================================
if ($method === 'POST' && ($_GET['action'] ?? '') === 'upload_photo') {
    $up = [];
    if (!empty($_POST['photo'])) {
        $up = $_POST;
    } elseif (!empty($_POST['data'])) {
        $up = json_decode($_POST['data'], true) ?: [];
    } else {
        $rawUp = file_get_contents('php://input');
        if ($rawUp) {
            $rawUp = preg_replace('/^[\xEF\xBB\xBF]+/', '', trim($rawUp));
            $up = json_decode($rawUp, true) ?: [];
        }
    }
    if (!is_array($up)) $up = [];

    $photoData = $up['photo'] ?? ($_POST['photo'] ?? '');
    if (!is_string($photoData) || !preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/i', $photoData, $mType)) {
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $maxStr = ini_get('post_max_size') ?: '30M';
        jsonResponse([
            'error' => ($len > 30 * 1048576)
                ? 'Foto gagal diterima server (ukuran ' . round($len / 1048576, 2) . ' MB melebihi batas ' . $maxStr . ').'
                : 'Data foto tidak valid atau tidak terbaca oleh server.'
        ], 400);
    }
    $decoded = base64_decode(substr($photoData, strpos($photoData, ',') + 1), true);
    if ($decoded === false || strlen($decoded) < 10) {
        jsonResponse(['error' => 'Data foto rusak / tidak dapat dibaca.'], 400);
    }
    $ext = strtolower($mType[1]) === 'jpeg' ? 'jpg' : strtolower($mType[1]);
    $prefix = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)($up['prefix'] ?? ($_POST['prefix'] ?? 'pkg')));
    $prefix = substr($prefix ?: 'pkg', 0, 120);
    $fname = $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;

    // Kandidat folder penyimpanan dengan auto-repair izin folder
    $possibleDirs = [
        ['dir' => __DIR__ . '/../uploads/reception', 'rel' => 'uploads/reception/'],
        ['dir' => __DIR__ . '/../uploads/photos',    'rel' => 'uploads/photos/'],
        ['dir' => __DIR__ . '/../uploads/cache',     'rel' => 'uploads/cache/'],
        ['dir' => __DIR__ . '/../uploads',           'rel' => 'uploads/']
    ];

    $savedPath = null;
    foreach ($possibleDirs as $cand) {
        $targetDir = $cand['dir'];
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }
        @chmod($targetDir, 0777);
        $fullPath = $targetDir . '/' . $fname;
        if (@file_put_contents($fullPath, $decoded) !== false) {
            @chmod($fullPath, 0666);
            $savedPath = $cand['rel'] . $fname;
            break;
        }
    }

    if ($savedPath) {
        jsonResponse(['success' => true, 'path' => $savedPath]);
    }

    // Jika seluruh folder gagal ditulis (izin hosting sangat ketat), jangan gagalkan proses serah terima!
    jsonResponse(['success' => true, 'path' => null, 'warning' => 'Izin folder dibatasi oleh hosting, data tetap aman']);
}

// ==========================================
// 2. POST: SIMPAN PENERIMAAN PAKET MULTIPLE
// ==========================================
if ($method === 'POST') {
    $input = [];
    if (!empty($_POST['data'])) {
        $input = json_decode($_POST['data'], true);
    } elseif (!empty($_POST['payload'])) {
        $input = json_decode($_POST['payload'], true);
    } elseif (!empty($_POST['packages']) || !empty($_POST['expedition'])) {
        $input = $_POST;
    } else {
        $rawInput = file_get_contents('php://input');
        if ($rawInput) {
            $rawInput = preg_replace('/^[\xEF\xBB\xBF]+/', '', trim($rawInput));
            $input = json_decode($rawInput, true);
        }
    }

    // Validasi payload
    if (!is_array($input) || empty($input)) {
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $maxStr = ini_get('post_max_size') ?: '30M';
        $unit = strtolower(substr($maxStr, -1));
        $val = (int)$maxStr;
        $maxBytes = $unit === 'g' ? $val * 1073741824 : ($unit === 'm' ? $val * 1048576 : ($unit === 'k' ? $val * 1024 : $val));

        if ($len > 0 && $maxBytes > 0 && $len > $maxBytes) {
            jsonResponse([
                'error' => 'Data penerimaan melebihi batas upload server (' . round($len / 1048576, 2) . ' MB, batas server ' . $maxStr . '). Coba bagi paket ke beberapa karung.'
            ], 413);
        }
        jsonResponse(['error' => 'Data penerimaan kosong atau tidak terbaca oleh server. Silakan coba simpan kembali.'], 400);
    }

    $expedition   = trim($input['expedition'] ?? '');
    $courierName  = trim($input['courier_name'] ?? '');
    $sackNumber   = trim($input['sack_number'] ?? '');
    $vehicleNo    = trim($input['vehicle_no'] ?? '');
    $notes        = trim($input['notes'] ?? '');
    $receiptNo    = trim($input['receipt_number'] ?? '');
    $packages     = $input['packages'] ?? [];
    $isChunk      = !empty($input['is_chunk']);
    $chunkIndex   = (int)($input['chunk_index'] ?? 0);
    $totalExpected = (int)($input['total_packages'] ?? count((array)$packages));
    // ID header dari batch sebelumnya (opsional) agar batch lanjutan / retry menempel ke header yang benar
    $continueReceptionId = (int)($input['reception_id'] ?? 0);

    // Potong teks agar tidak melebihi panjang kolom VARCHAR (strict mode -> error 1406 "Data too long")
    $cutLen = function($str, $max) {
        $str = (string)$str;
        return function_exists('mb_substr') ? mb_substr($str, 0, $max, 'UTF-8') : substr($str, 0, $max);
    };
    $strLen = function($str) {
        return function_exists('mb_strlen') ? mb_strlen((string)$str, 'UTF-8') : strlen((string)$str);
    };
    $courierName = $cutLen($courierName, 150);
    $sackNumber  = $cutLen($sackNumber, 100);
    $vehicleNo   = $cutLen($vehicleNo, 50);
    $receiptNo   = $cutLen($receiptNo, 100);

    if (empty($expedition)) {
        jsonResponse(['error' => 'Ekspedisi pengantar tidak terbaca oleh server. Pilih ulang ekspedisi di Langkah 1 lalu simpan kembali.'], 400);
    }

    if (!is_array($packages) || count($packages) === 0) {
        jsonResponse(['error' => 'Minimal 1 barcode/resi paket harus di-scan sebelum submit!'], 400);
    }

    // Bersihkan dan proses paket (bisa string biasa atau object {barcode, photo, sack_number})
    $cleanPackages = [];
    foreach ($packages as $pkg) {
        if (is_array($pkg)) {
            $b = trim((string)($pkg['barcode'] ?? ''));
            $p = $pkg['photo'] ?? null;
            $s = $cutLen(trim((string)($pkg['sack_number'] ?? '')), 100) ?: $sackNumber;
            if ($b !== '') {
                $cleanPackages[] = [
                    'barcode'     => $b,
                    'photo'       => $p,
                    'sack_number' => $s ?: null
                ];
            }
        } else {
            $val = trim((string)$pkg);
            if ($val !== '') {
                $cleanPackages[] = [
                    'barcode'     => $val,
                    'photo'       => null,
                    'sack_number' => $sackNumber ?: null
                ];
            }
        }
    }

    // Deduplikasi resi di dalam batch agar tidak ada barcode yang ter-input ganda dalam 1 penerimaan
    $uniquePackages = [];
    $seenCodes = [];
    foreach ($cleanPackages as $cp) {
        $key = strtoupper(trim($cp['barcode']));
        if (!isset($seenCodes[$key])) {
            $seenCodes[$key] = true;
            $uniquePackages[] = $cp;
        }
    }
    $cleanPackages = $uniquePackages;

    // Barcode lebih dari 100 karakter tidak muat di kolom package_barcode (biasanya QR yang ter-scan, bukan resi)
    foreach ($cleanPackages as $cp) {
        if ($strLen($cp['barcode']) > 100) {
            jsonResponse([
                'error' => 'Barcode terlalu panjang (' . $strLen($cp['barcode']) . ' karakter): "' . $cutLen($cp['barcode'], 40) . '…". Kemungkinan QR yang ter-scan, bukan resi. Hapus paket tersebut dari draft lalu simpan kembali.'
            ], 400);
        }
    }

    // Rangkum seluruh karung yang ada dalam penerimaan ini (bisa multiple karung per 1 ID)
    $distinctSacks = [];
    foreach ($cleanPackages as $cp) {
        $s = trim((string)($cp['sack_number'] ?? ''));
        if ($s !== '' && !in_array($s, $distinctSacks)) {
            $distinctSacks[] = $s;
        }
    }
    $headerSackSummary = $cutLen(!empty($distinctSacks) ? implode(', ', $distinctSacks) : ($sackNumber ?: 'Karung 1'), 100);

    if (count($cleanPackages) === 0) {
        jsonResponse(['error' => 'Daftar barcode paket tidak boleh kosong!'], 400);
    }

    // Batch lanjutan / retry: client mengirim reception_id dari respons batch sebelumnya.
    // Validasi bahwa header tersebut ada dan nomor tanda terimanya cocok.
    // Batch lanjutan / retry: client mengirim reception_id dari respons batch sebelumnya.
    // Validasi bahwa header tersebut ada dan sinkronkan nomor tanda terimanya.
    $continueHeader = null;
    if ($continueReceptionId > 0) {
        $stCont = $pdo->prepare("SELECT id, receipt_number FROM expedition_receptions WHERE id = ? LIMIT 1");
        $stCont->execute([$continueReceptionId]);
        $continueHeader = $stCont->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($continueHeader) {
            // Gunakan nomor tanda terima resmi dari header yang sudah ada
            $receiptNo = (string)$continueHeader['receipt_number'];
        } else {
            // Header tidak ditemukan di database (mis. draft lama atau sudah dibersihkan) -> buat header baru otomatis
            $continueHeader = null;
            $continueReceptionId = 0;
        }
    }

    // Jika nomor tanda terima belum diisi, generate otomatis
    if (empty($receiptNo)) {
        $receiptNo = nextReceiptNumber($pdo);
    }

    $cleanRcpt = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $receiptNo);
    $recUploadDir = __DIR__ . '/../uploads/reception';
    if (!is_dir($recUploadDir)) {
        @mkdir($recUploadDir, 0777, true);
    }

    // Helper simpan base64 image dengan multi-folder fallback
    $saveImgHelper = function($pData, $prefix) {
        if (is_string($pData) && strpos($pData, 'data:image') === 0) {
            $ext = 'jpg';
            if (preg_match('/^data:image\/(\w+);base64,/', $pData, $typeMatch)) {
                $ext = strtolower($typeMatch[1]) === 'jpeg' ? 'jpg' : strtolower($typeMatch[1]);
                $raw = substr($pData, strpos($pData, ',') + 1);
            } else {
                $raw = $pData;
            }
            $decoded = base64_decode($raw);
            if ($decoded) {
                $pName = $prefix . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
                $possibleDirs = [
                    ['dir' => __DIR__ . '/../uploads/reception', 'rel' => 'uploads/reception/'],
                    ['dir' => __DIR__ . '/../uploads/photos',    'rel' => 'uploads/photos/'],
                    ['dir' => __DIR__ . '/../uploads/cache',     'rel' => 'uploads/cache/'],
                    ['dir' => __DIR__ . '/../uploads',           'rel' => 'uploads/']
                ];
                foreach ($possibleDirs as $cand) {
                    $targetDir = $cand['dir'];
                    if (!is_dir($targetDir)) @mkdir($targetDir, 0777, true);
                    @chmod($targetDir, 0777);
                    $fullPath = $targetDir . '/' . $pName;
                    if (@file_put_contents($fullPath, $decoded) !== false) {
                        @chmod($fullPath, 0666);
                        return $cand['rel'] . $pName;
                    }
                }
            }
        } elseif (is_string($pData) && strpos($pData, 'uploads/') === 0 && strpos($pData, '..') === false) {
            // Foto sudah diunggah sebelumnya via action=upload_photo
            return $pData;
        }
        return null;
    };

    $photoPaths = [];

    // 1. Simpan foto per-paket jika ada
    foreach ($cleanPackages as &$cp) {
        $savedPath = null;
        if (!empty($cp['photo'])) {
            $cleanB = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $cp['barcode']);
            $savedPath = $saveImgHelper($cp['photo'], 'pkg_' . $cleanRcpt . '_' . $cleanB);
            if ($savedPath) {
                $photoPaths[] = $savedPath;
            }
        }
        $cp['saved_photo'] = $savedPath;
    }
    unset($cp);

    // 2. Simpan Foto Tambahan Umum (jika dikirim via photos / package_photos yang BUKAN foto paket)
    $photosInput = $input['photos'] ?? $input['package_photos'] ?? [];
    if (is_string($photosInput) && !empty($photosInput)) {
        $photosInput = [$photosInput];
    }
    if (!empty($input['photo_path']) && is_string($input['photo_path']) && !in_array($input['photo_path'], $photosInput)) {
        $photosInput[] = $input['photo_path'];
    }

    // Ambil hash dari foto-foto per-paket yang sudah diproses di atas
    $existingRawPkgHashes = [];
    foreach ($cleanPackages as $cp) {
        if (!empty($cp['photo']) && is_string($cp['photo'])) {
            $existingRawPkgHashes[md5(trim($cp['photo']))] = true;
        }
    }

    foreach ($photosInput as $idx => $pData) {
        if (!is_string($pData) || empty($pData)) continue;
        // Jangan simpan ulang jika foto ini identik dengan foto salah satu paket
        if (isset($existingRawPkgHashes[md5(trim($pData))])) {
            continue;
        }
        $saved = $saveImgHelper($pData, 'rcv_' . $cleanRcpt . "_{$idx}");
        if ($saved && !in_array($saved, $photoPaths)) {
            $photoPaths[] = $saved;
        }
    }

    // 2b. Simpan Foto Kurir jika ada
    $courierPhotoData = $input['courier_photo'] ?? null;
    $savedCourierPhoto = null;
    if (!empty($courierPhotoData)) {
        $savedCourierPhoto = $saveImgHelper($courierPhotoData, 'courier_' . $cleanRcpt);
    }

    $mainPhotoPath = count($photoPaths) > 0 ? $photoPaths[0] : null;
    $allPhotosJson = count($photoPaths) > 0 ? json_encode($photoPaths, JSON_UNESCAPED_SLASHES) : null;

    // Pastikan schema tabel dan kolom tersedia SEBELUM memulai transaksi (DDL inside transaction causes implicit commit in MySQL)
    $recCols = [];
    try {
        $recCols = $pdo->query("SHOW COLUMNS FROM expedition_receptions")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('courier_name', $recCols)) {
            try { $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN courier_name VARCHAR(150) NULL AFTER expedition"); $recCols[] = 'courier_name'; } catch (Exception $e) {}
        }
        if (!in_array('sack_number', $recCols)) {
            try { $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN sack_number VARCHAR(100) NULL AFTER courier_name"); $recCols[] = 'sack_number'; } catch (Exception $e) {}
        }
        if (!in_array('courier_photo', $recCols)) {
            try { $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN courier_photo VARCHAR(255) NULL AFTER courier_name"); $recCols[] = 'courier_photo'; } catch (Exception $e) {}
        }
        if (!in_array('vehicle_no', $recCols)) {
            try { $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN vehicle_no VARCHAR(50) NULL AFTER courier_photo"); $recCols[] = 'vehicle_no'; } catch (Exception $e) {}
        }
        if (!in_array('notes', $recCols)) {
            try { $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN notes TEXT NULL AFTER total_packages"); $recCols[] = 'notes'; } catch (Exception $e) {}
        }
        if (!in_array('photo_path', $recCols)) {
            try { $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN photo_path VARCHAR(255) NULL AFTER notes"); $recCols[] = 'photo_path'; } catch (Exception $e) {}
        }
        if (!in_array('package_photos', $recCols)) {
            try { $pdo->exec("ALTER TABLE expedition_receptions ADD COLUMN package_photos TEXT NULL AFTER photo_path"); $recCols[] = 'package_photos'; } catch (Exception $e) {}
        }
    } catch (Exception $eCols) {}

    $pkgCols = [];
    try {
        $pkgCols = $pdo->query("SHOW COLUMNS FROM reception_packages")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('photo_path', $pkgCols)) {
            try { $pdo->exec("ALTER TABLE reception_packages ADD COLUMN photo_path VARCHAR(255) NULL AFTER package_barcode"); $pkgCols[] = 'photo_path'; } catch (Exception $e) {}
        }
        if (!in_array('sack_number', $pkgCols)) {
            try { $pdo->exec("ALTER TABLE reception_packages ADD COLUMN sack_number VARCHAR(100) NULL AFTER package_barcode"); $pkgCols[] = 'sack_number'; } catch (Exception $e) {}
        }
    } catch (Exception $eCols2) {}

    // Anti Double-Submission & Idempotensi Penerimaan (submit normal atau chunk #0 tanpa reception_id).
    // Dianggap "sudah tersimpan" HANYA jika nomor tanda terima sama DAN daftar barcode identik.
    // Nomor sama dengan isi berbeda (2 operator dapat nomor yang sama) -> nomor baru, bukan dibuang.
    if (!$continueHeader && (!$isChunk || $chunkIndex === 0)) {
        try {
            $chkRcpt = $pdo->prepare("SELECT id, receipt_number, expedition, total_packages, created_at FROM expedition_receptions WHERE receipt_number = ? LIMIT 1");
            $chkRcpt->execute([$receiptNo]);
            $existingRcpt = $chkRcpt->fetch(PDO::FETCH_ASSOC);
            if ($existingRcpt) {
                $sameBatch = false;
                $stB = $pdo->prepare("SELECT package_barcode FROM reception_packages WHERE reception_id = ?");
                $stB->execute([$existingRcpt['id']]);
                $oldCodes = array_values(array_unique(array_map(function($c) { return strtoupper(trim((string)$c)); }, $stB->fetchAll(PDO::FETCH_COLUMN))));
                $newCodes = array_values(array_unique(array_map(function($c) { return strtoupper(trim((string)$c['barcode'])); }, $cleanPackages)));
                sort($oldCodes);
                sort($newCodes);
                if (!$isChunk) {
                    $sameBatch = (count($oldCodes) > 0 && $oldCodes === $newCodes);
                } elseif (count($oldCodes) > 0
                    && strcasecmp((string)$existingRcpt['expedition'], $expedition) === 0
                    && count(array_diff($newCodes, $oldCodes)) === 0) {
                    // Retry chunk #0 yang sebenarnya sudah tersimpan (respons hilang karena sinyal PDT):
                    // lanjutkan ke header yang sama, jangan buat penerimaan baru yang setengah isi.
                    $continueHeader = ['id' => (int)$existingRcpt['id'], 'receipt_number' => $existingRcpt['receipt_number']];
                }
                if ($continueHeader) {
                    // lanjut ke header yang sudah ada (barcode yang sudah tersimpan akan dilewati)
                } elseif ($sameBatch) {
                    jsonResponse([
                        'success'        => true,
                        'message'        => 'Penerimaan ini sudah tersimpan di database.',
                        'id'             => $existingRcpt['id'],
                        'reception_id'   => $existingRcpt['id'],
                        'receipt_number' => $existingRcpt['receipt_number'],
                        'expedition'     => $existingRcpt['expedition'],
                        'total_packages' => (int)$existingRcpt['total_packages'],
                        'already_exists' => true
                    ]);
                } else {
                    // Generate receipt number baru otomatis agar tidak bentrok dengan data lama (juga untuk chunk #0)
                    $receiptNo = nextReceiptNumber($pdo, $receiptNo);
                }
            }
        } catch (Exception $eRcpt) {}
    }

    $hasCourierNameCol   = in_array('courier_name', $recCols);
    $hasSackNumberCol    = in_array('sack_number', $recCols);
    $hasCourierPhotoCol  = in_array('courier_photo', $recCols);
    $hasVehicleNoCol     = in_array('vehicle_no', $recCols);
    $hasNotesCol         = in_array('notes', $recCols);
    $hasPhotoPathCol     = in_array('photo_path', $recCols);
    $hasPackagePhotosCol = in_array('package_photos', $recCols);
    $hasItemPhotoCol     = in_array('photo_path', $pkgCols);
    $hasItemSackCol      = in_array('sack_number', $pkgCols);

    try {
        $pdo->beginTransaction();

        $operatorName = $user['name'] ?? $user['username'] ?? 'Operator';
        $totalCount   = count($cleanPackages);
        $receptionId  = null;
        $headerPreExisted = false;

        if ($continueHeader) {
            // Batch lanjutan / retry dengan reception_id yang sudah divalidasi
            $receptionId = (int)$continueHeader['id'];
        } elseif ($isChunk && $chunkIndex > 0) {
            // Kompatibilitas client lama: chunk > 0 tanpa reception_id -> cari header berdasarkan nomor tanda terima
            $stmtFindRec = $pdo->prepare("SELECT id FROM expedition_receptions WHERE receipt_number = ? LIMIT 1");
            $stmtFindRec->execute([$receiptNo]);
            $receptionId = $stmtFindRec->fetchColumn();
        }
        if ($receptionId) {
            $headerPreExisted = true;
        }

        // Jika belum ada header (submit normal atau chunk #0), buatkan header baru.
        // Nomor yang sudah dipakai header lain TIDAK lagi ditumpangi (lihat cek anti-bentrok di atas);
        // jika bentrok terjadi bersamaan (error 1062), nomor baru dibuat lalu INSERT diulang.
        if (!$receptionId) {
            $initTotal = $isChunk ? $totalExpected : $totalCount;
            $fields = ['receipt_number', 'expedition', 'operator_name', 'total_packages', 'status', 'created_at'];
            $placeholders = ['?', '?', '?', '?', "'RECEIVED'", 'NOW()'];
            $values = [$receiptNo, $expedition, $operatorName, $initTotal];

            if ($hasCourierNameCol) {
                $fields[] = 'courier_name';
                $placeholders[] = '?';
                $values[] = $courierName ?: null;
            }
            if ($hasSackNumberCol) {
                $fields[] = 'sack_number';
                $placeholders[] = '?';
                $values[] = $headerSackSummary ?: ($sackNumber ?: null);
            }
            if ($hasCourierPhotoCol) {
                $fields[] = 'courier_photo';
                $placeholders[] = '?';
                $values[] = $savedCourierPhoto ?: null;
            }
            if ($hasVehicleNoCol) {
                $fields[] = 'vehicle_no';
                $placeholders[] = '?';
                $values[] = $vehicleNo ?: null;
            }
            if ($hasNotesCol) {
                $fields[] = 'notes';
                $placeholders[] = '?';
                $values[] = $notes ?: null;
            }
            if ($hasPhotoPathCol) {
                $fields[] = 'photo_path';
                $placeholders[] = '?';
                $values[] = $mainPhotoPath;
            }
            if ($hasPackagePhotosCol) {
                $fields[] = 'package_photos';
                $placeholders[] = '?';
                $values[] = $allPhotosJson;
            }

            $sqlHead = "INSERT INTO expedition_receptions (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmtHead = $pdo->prepare($sqlHead);
            for ($tryHead = 0; ; $tryHead++) {
                try {
                    $values[0] = $receiptNo;
                    $stmtHead->execute($values);
                    $receptionId = $pdo->lastInsertId();
                    break;
                } catch (PDOException $eIns) {
                    $isDupKey = ((int)($eIns->errorInfo[1] ?? 0) === 1062);
                    if (!$isDupKey || $tryHead >= 4) {
                        throw $eIns;
                    }
                    // Nomor tanda terima direbut operator lain di saat bersamaan -> ambil nomor berikutnya
                    $receiptNo = nextReceiptNumber($pdo, $receiptNo);
                }
            }
        } else {
            // Perbarui informasi header jika perlu
            if (!empty($savedCourierPhoto) && $hasCourierPhotoCol) {
                $pdo->prepare("UPDATE expedition_receptions SET courier_photo = COALESCE(courier_photo, ?) WHERE id = ?")->execute([$savedCourierPhoto, $receptionId]);
            }
        }

        // Barcode yang sudah tersimpan di header ini (retry batch) dilewati agar tidak tercatat ganda
        $alreadyStored = [];
        if ($headerPreExisted) {
            $stAlready = $pdo->prepare("SELECT package_barcode FROM reception_packages WHERE reception_id = ?");
            $stAlready->execute([$receptionId]);
            foreach ($stAlready->fetchAll(PDO::FETCH_COLUMN) as $ac) {
                $alreadyStored[strtoupper(trim((string)$ac))] = true;
            }
        }
        $skippedExisting = 0;

        // 2. Simpan Detail Paket ke reception_packages
        if ($hasItemPhotoCol && $hasItemSackCol) {
            $stmtItem = $pdo->prepare("
                INSERT INTO reception_packages (reception_id, package_barcode, sack_number, photo_path, scanned_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
        } elseif ($hasItemPhotoCol) {
            $stmtItem = $pdo->prepare("
                INSERT INTO reception_packages (reception_id, package_barcode, photo_path, scanned_at)
                VALUES (?, ?, ?, NOW())
            ");
        } elseif ($hasItemSackCol) {
            $stmtItem = $pdo->prepare("
                INSERT INTO reception_packages (reception_id, package_barcode, sack_number, scanned_at)
                VALUES (?, ?, ?, NOW())
            ");
        } else {
            $stmtItem = $pdo->prepare("
                INSERT INTO reception_packages (reception_id, package_barcode, scanned_at)
                VALUES (?, ?, NOW())
            ");
        }

        foreach ($cleanPackages as $pkg) {
            if (isset($alreadyStored[strtoupper(trim($pkg['barcode']))])) {
                $skippedExisting++;
                continue;
            }
            if ($hasItemPhotoCol && $hasItemSackCol) {
                $stmtItem->execute([$receptionId, $pkg['barcode'], $pkg['sack_number'], $pkg['saved_photo']]);
            } elseif ($hasItemPhotoCol) {
                $stmtItem->execute([$receptionId, $pkg['barcode'], $pkg['saved_photo']]);
            } elseif ($hasItemSackCol) {
                $stmtItem->execute([$receptionId, $pkg['barcode'], $pkg['sack_number']]);
            } else {
                $stmtItem->execute([$receptionId, $pkg['barcode']]);
            }
        }

        // Update total aktual paket yang sudah tersimpan
        $cntActual = $pdo->prepare("SELECT COUNT(*) FROM reception_packages WHERE reception_id = ?");
        $cntActual->execute([$receptionId]);
        $actualSavedCount = (int)$cntActual->fetchColumn();
        $pdo->prepare("UPDATE expedition_receptions SET total_packages = ? WHERE id = ?")->execute([$actualSavedCount, $receptionId]);

        // Header dari batch sebelumnya: perbarui ringkasan karung dari SELURUH paket yang tersimpan
        if ($headerPreExisted && $hasSackNumberCol && $hasItemSackCol) {
            $stSacks = $pdo->prepare("SELECT sack_number FROM reception_packages WHERE reception_id = ? ORDER BY id ASC");
            $stSacks->execute([$receptionId]);
            $allSacks = [];
            foreach ($stSacks->fetchAll(PDO::FETCH_COLUMN) as $sn) {
                $sn = trim((string)$sn);
                if ($sn !== '' && !in_array($sn, $allSacks)) {
                    $allSacks[] = $sn;
                }
            }
            if (!empty($allSacks)) {
                $pdo->prepare("UPDATE expedition_receptions SET sack_number = ? WHERE id = ?")->execute([$cutLen(implode(', ', $allSacks), 100), $receptionId]);
            }
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        jsonResponse([
            'success'        => true,
            'is_chunk'       => $isChunk,
            'chunk_index'    => $chunkIndex,
            'message'        => $isChunk 
                ? "Batch paket berhasil disimpan ({$actualSavedCount} / {$totalExpected} paket)." 
                : "Penerimaan {$totalCount} paket ekspedisi {$expedition} berhasil disimpan!",
            'reception_id'   => (int)$receptionId,
            'receipt_number' => $receiptNo,
            'skipped_existing' => $skippedExisting,
            'expedition'     => $expedition,
            'total_packages' => $actualSavedCount,
            'operator_name'  => $operatorName,
            'courier_photo'  => $savedCourierPhoto,
            'photo_path'     => $mainPhotoPath,
            'photos'         => $photoPaths,
            'created_at'     => date('Y-m-d H:i:s')
        ]);

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse([
            'error' => 'Gagal menyimpan penerimaan: ' . $e->getMessage()
        ], 500);
    }
}
