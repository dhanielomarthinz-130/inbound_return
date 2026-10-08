<?php
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle DELETE: Hapus Transaksi Sesi Unboxing
if ($method === 'DELETE' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete')) {
    $sessionUser = getSessionUser();
    if (!in_array($sessionUser['role'] ?? '', ['admin', 'superadmin'])) {
        jsonResponse(['error' => 'Akses ditolak. Hanya Admin yang dapat menghapus data transaksi unboxing.'], 403);
    }

    $id = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
    if ($id <= 0) {
        $raw = file_get_contents('php://input');
        $parsed = json_decode($raw, true);
        $id = intval($parsed['id'] ?? 0);
    }

    if ($id <= 0) {
        jsonResponse(['error' => 'ID Sesi Unboxing tidak valid'], 400);
    }

    try {
        // Ambil data sesi untuk hapus file video & foto
        $stmtS = $pdo->prepare("SELECT video_path, package_photo, product_photo, photos FROM return_sessions WHERE id = ?");
        $stmtS->execute([$id]);
        $s = $stmtS->fetch(PDO::FETCH_ASSOC);

        if ($s) {
            $filesToDelete = [];
            if (!empty($s['video_path'])) $filesToDelete[] = $s['video_path'];
            if (!empty($s['package_photo'])) $filesToDelete[] = $s['package_photo'];
            if (!empty($s['product_photo'])) $filesToDelete[] = $s['product_photo'];
            if (!empty($s['photos'])) {
                $dec = json_decode($s['photos'], true);
                if (is_array($dec)) {
                    foreach ($dec as $dp) {
                        $pPath = is_array($dp) ? ($dp['path'] ?? '') : $dp;
                        if (!empty($pPath)) $filesToDelete[] = $pPath;
                    }
                }
            }

            // Foto item
            $stmtItemPhotos = $pdo->prepare("SELECT photo_path FROM return_items WHERE session_id = ?");
            $stmtItemPhotos->execute([$id]);
            $itemPhotos = $stmtItemPhotos->fetchAll(PDO::FETCH_COLUMN);
            foreach ($itemPhotos as $ip) {
                if (!empty($ip)) $filesToDelete[] = $ip;
            }

            // Hapus fisik file
            $uploadsDir = realpath(__DIR__ . '/../uploads');
            foreach (array_unique($filesToDelete) as $relPath) {
                $absPath = realpath(__DIR__ . '/../' . ltrim($relPath, '/'));
                if ($absPath && file_exists($absPath) && is_file($absPath)) {
                    if ($uploadsDir && strpos($absPath, $uploadsDir) === 0) {
                        @unlink($absPath);
                    }
                }
            }

            // Hapus items & sessions
            $pdo->prepare("DELETE FROM return_items WHERE session_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM return_sessions WHERE id = ?")->execute([$id]);

            // Bersihkan cache dashboard
            $cacheDir = __DIR__ . '/../uploads/cache/';
            if (is_dir($cacheDir)) {
                @array_map('unlink', glob($cacheDir . '*.json'));
            }

            jsonResponse(['success' => true, 'message' => 'Data transaksi unboxing berhasil dihapus']);
        } else {
            jsonResponse(['error' => 'Data transaksi tidak ditemukan'], 404);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menghapus: ' . $e->getMessage()], 500);
    }
}

// Handle GET: Ambil Data Sesi & Item untuk Modal Edit
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_edit') {
    $sessionUser = getSessionUser();
    if (!in_array($sessionUser['role'] ?? '', ['admin', 'superadmin', 'operator'])) {
        jsonResponse(['error' => 'Akses ditolak.'], 403);
    }

    $sessionId = intval($_GET['id'] ?? 0);
    $itemId    = intval($_GET['item_id'] ?? 0);

    if ($sessionId <= 0 && $itemId > 0) {
        $stmtFindSess = $pdo->prepare("SELECT session_id FROM return_items WHERE id = ?");
        $stmtFindSess->execute([$itemId]);
        $sessionId = intval($stmtFindSess->fetchColumn());
    }

    if ($sessionId <= 0) {
        jsonResponse(['error' => 'ID Transaksi Unboxing tidak valid'], 400);
    }

    try {
        $stmtSess = $pdo->prepare("SELECT id, invoice_number, expedition, operator_name, customer_name, notes, total_items, total_good, total_damaged, created_at FROM return_sessions WHERE id = ?");
        $stmtSess->execute([$sessionId]);
        $session = $stmtSess->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            jsonResponse(['error' => 'Data sesi unboxing tidak ditemukan'], 404);
        }

        $stmtItems = $pdo->prepare("
            SELECT id, session_id, barcode, wrong_barcode, wrong_product_name, product_name, sku, seller_sku, sap_code, batch_no, exp_date, type, qty, `condition`, damage_reason, photo_path 
            FROM return_items 
            WHERE session_id = ? 
            ORDER BY id ASC
        ");
        $stmtItems->execute([$sessionId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'success' => true,
            'session' => $session,
            'items'   => $items,
            'focus_item_id' => $itemId
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal mengambil data edit: ' . $e->getMessage()], 500);
    }
}

// Handle POST: Update Data Sesi & Item Unboxing
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update') {
    $sessionUser = getSessionUser();
    if (!in_array($sessionUser['role'] ?? '', ['admin', 'superadmin'])) {
        jsonResponse(['error' => 'Akses ditolak. Hanya Admin yang dapat mengedit data transaksi unboxing.'], 403);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $sessionId = intval($payload['session_id'] ?? $payload['id'] ?? 0);
    if ($sessionId <= 0) {
        jsonResponse(['error' => 'ID Sesi Unboxing tidak valid'], 400);
    }

    $invoiceNumber = trim($payload['invoice_number'] ?? '');
    $expedition    = trim($payload['expedition'] ?? '');
    $operatorName  = trim($payload['operator_name'] ?? '');
    $notes         = trim($payload['notes'] ?? '');
    $items         = $payload['items'] ?? [];

    if (empty($invoiceNumber)) {
        jsonResponse(['error' => 'Nomor Invoice tidak boleh kosong'], 400);
    }

    try {
        $pdo->beginTransaction();

        // 1. Update return_sessions
        $stmtUpdSess = $pdo->prepare("
            UPDATE return_sessions 
            SET invoice_number = ?, expedition = ?, operator_name = ?, notes = ? 
            WHERE id = ?
        ");
        $stmtUpdSess->execute([
            $invoiceNumber,
            $expedition ?: 'Lainnya',
            $operatorName ?: 'Gudang 01',
            $notes,
            $sessionId
        ]);

        // 2. Update masing-masing return_items jika ada
        if (!empty($items) && is_array($items)) {
            $stmtUpdItem = $pdo->prepare("
                UPDATE return_items 
                SET product_name = ?, 
                    sku = ?, 
                    seller_sku = ?, 
                    barcode = ?, 
                    type = ?, 
                    `condition` = ?, 
                    damage_reason = ?, 
                    qty = ?, 
                    batch_no = ?, 
                    exp_date = ?
                WHERE id = ? AND session_id = ?
            ");

            foreach ($items as $it) {
                $itemId = intval($it['id'] ?? 0);
                if ($itemId <= 0) continue;

                $pName    = trim($it['product_name'] ?? '');
                $sku      = trim($it['sku'] ?? '');
                $sellerSku= trim($it['seller_sku'] ?? $sku);
                $barcode  = trim($it['barcode'] ?? '');
                $type     = strtoupper(trim($it['type'] ?? 'GOOD'));
                $cond     = ($type === 'GOOD' || $type === 'BAGUS' || $type === 'LAYAK') ? 'GOOD' : 'RUSAK';
                $reason   = ($cond === 'RUSAK') ? trim($it['damage_reason'] ?? $type) : '';
                $qty      = max(1, intval($it['qty'] ?? 1));
                $batchNo  = trim($it['batch_no'] ?? '-');
                $expDate  = trim($it['exp_date'] ?? '-');

                $stmtUpdItem->execute([
                    $pName,
                    $sku,
                    $sellerSku,
                    $barcode,
                    $type,
                    $cond,
                    $reason,
                    $qty,
                    $batchNo,
                    $expDate,
                    $itemId,
                    $sessionId
                ]);
            }
        }

        // 3. Rekalkulasi total unit, total good, total damaged
        $pdo->prepare("
            UPDATE return_sessions s
            SET s.total_items = (SELECT COALESCE(SUM(qty), 0) FROM return_items WHERE session_id = s.id),
                s.total_good  = (SELECT COALESCE(SUM(CASE WHEN UPPER(COALESCE(type, `condition`)) IN ('GOOD', 'BAGUS', 'LAYAK') THEN qty ELSE 0 END), 0) FROM return_items WHERE session_id = s.id),
                s.total_damaged = (SELECT COALESCE(SUM(CASE WHEN UPPER(COALESCE(type, `condition`)) NOT IN ('GOOD', 'BAGUS', 'LAYAK') THEN qty ELSE 0 END), 0) FROM return_items WHERE session_id = s.id)
            WHERE s.id = ?
        ")->execute([$sessionId]);

        $pdo->commit();

        // 4. Bersihkan cache dashboard
        $cacheDir = __DIR__ . '/../uploads/cache/';
        if (is_dir($cacheDir)) {
            @array_map('unlink', glob($cacheDir . '*.json'));
        }

        jsonResponse([
            'success' => true,
            'message' => "Data transaksi unboxing [{$invoiceNumber}] berhasil diperbarui!",
            'session_id' => $sessionId
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['error' => 'Gagal memperbarui data transaksi: ' . $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metode tidak diizinkan'], 405);
}

// Simpan sesi unboxing WAJIB login (JSON 401/403 untuk request /api/)
requireLogin(); // wajib login (role apa pun yang punya akses scanner)

// 1. Terima payload (bisa via Multipart FormData atau Raw JSON)
$body = [];
if (!empty($_POST['data'])) {
    $body = json_decode($_POST['data'], true);
} else {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $rawInput = preg_replace('/^[\xEF\xBB\xBF]+/', '', trim($rawInput));
        $body = json_decode($rawInput, true);
    }
}

if (!$body || !is_array($body)) {
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $maxStr = ini_get('post_max_size') ?: '30M';
    $unit = strtolower(substr($maxStr, -1));
    $val = (int)$maxStr;
    $maxBytes = $unit === 'g' ? $val * 1073741824 : ($unit === 'm' ? $val * 1048576 : ($unit === 'k' ? $val * 1024 : $val));

    if ($len > 0 && $maxBytes > 0 && $len > $maxBytes) {
        jsonResponse(['error' => "Ukuran file video & data melebihi batas upload server ($maxStr). Silakan rekam lebih singkat atau kurangi durasi."], 413);
    }
    jsonResponse(['error' => 'Format input data transaksi unboxing tidak valid atau kosong'], 400);
}

$sessionUser   = getSessionUser();
$invoiceNumber = trim($body['invoice_number'] ?? '');
$operatorName  = trim($body['operator_name'] ?? ($sessionUser['name'] ?? 'Gudang 01'));
if (empty($operatorName)) $operatorName = $sessionUser['name'] ?? 'Gudang 01';
$customerName  = trim($body['customer_name'] ?? 'Pelanggan Return');
$expedition    = trim($body['expedition'] ?? '');
if (empty($expedition)) $expedition = 'Lainnya';
$notes         = trim($body['notes'] ?? '');
$items         = $body['items'] ?? [];

// ---------------------------------------------------------------------------
// VALIDASI DULU sebelum menulis file apa pun ke disk (cegah file yatim/orphan)
// ---------------------------------------------------------------------------
if (empty($invoiceNumber) || empty($items) || !is_array($items)) {
    jsonResponse(['error' => 'Nomor Invoice dan minimal 1 produk wajib diisi'], 400);
}

// Kode kondisi yang dianggap BAIK (konsisten dengan operator.js & action=update)
$goodConditionCodes = ['GOOD', 'BAGUS', 'LAYAK'];

// Anti Double-Submit: Cek apakah invoice_number yang sama persis baru saja di-submit dalam 10 detik terakhir
// (dicek sebelum menyimpan foto/video agar submit ganda tidak meninggalkan file yatim)
try {
    $chkRecent = $pdo->prepare("
        SELECT id, invoice_number, created_at 
        FROM return_sessions 
        WHERE invoice_number = ? AND created_at >= (NOW() - INTERVAL 10 SECOND) 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $chkRecent->execute([$invoiceNumber]);
    $recentSession = $chkRecent->fetch(PDO::FETCH_ASSOC);
    if ($recentSession) {
        jsonResponse([
            'success'        => true,
            'message'        => 'Data transaksi telah berhasil dicatat sebelumnya.',
            'session_id'     => $recentSession['id'],
            'invoice_number' => $recentSession['invoice_number'],
            'already_exists' => true
        ]);
    }
} catch (Exception $eDup) {}

// Video unboxing WAJIB ada: dukung $_FILES['video'] dan $_POST['video_base64']
// (sangat penting untuk hosting seperti InfinityFree di mana upload_tmp_dir tidak tersedia / Error 6)
$rawVideoBase64 = $_POST['video_base64'] ?? ($body['video_base64'] ?? '');
$clientVideoExt = strtolower(trim($_POST['video_ext'] ?? ($body['video_ext'] ?? '')));

$hasFilesVideo = isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK && ((int)($_FILES['video']['size'] ?? 0) >= 100);
$hasBase64Video = !empty($rawVideoBase64) && strlen($rawVideoBase64) >= 100;

if (!$hasFilesVideo && !$hasBase64Video) {
    if (isset($_FILES['video']) && $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $vErr = (int)$_FILES['video']['error'];
        $vErrMap = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran video melebihi batas upload_max_filesize server (' . ini_get('upload_max_filesize') . '). Silakan rekam lebih singkat',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran video melebihi batas maksimum form upload',
            UPLOAD_ERR_PARTIAL    => 'Upload video terputus di tengah jalan (partial). Silakan kirim ulang',
            UPLOAD_ERR_NO_FILE    => 'File video tidak ikut terkirim',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara (tmp) PHP di server tidak tersedia',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file video ke disk',
            UPLOAD_ERR_EXTENSION  => 'Upload video dihentikan oleh ekstensi PHP',
        ];
        error_log("Video upload failed with PHP error code: " . $vErr);
        jsonResponse([
            'error' => ($vErrMap[$vErr] ?? ('Gagal mengunggah file rekaman video unboxing (Kode Error PHP: ' . $vErr . ')')) . '. Video unboxing WAJIB ada.',
            'video_status' => 'upload_err_' . $vErr
        ], in_array($vErr, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400);
    }
    jsonResponse([
        'error' => 'Rekaman video unboxing WAJIB ada dan valid! Pastikan webcam/kamera menyala dan merekam proses unboxing sebelum menyelesaikan sesi.',
        'video_status' => 'no_video'
    ], 400);
}

// Daftar file yang ditulis request ini -> dihapus lagi bila penyimpanan gagal
$writtenFiles = [];
function cleanupWrittenFiles() {
    foreach (($GLOBALS['writtenFiles'] ?? []) as $relPath) {
        $abs = __DIR__ . '/../' . ltrim($relPath, '/');
        if (is_file($abs)) @unlink($abs);
    }
    $GLOBALS['writtenFiles'] = [];
}

// Helper simpan Base64 Image
function saveBase64Image($base64Data, $dir, $prefix) {
    if (empty($base64Data) || !is_string($base64Data)) return '';
    $ext = 'jpg';
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        $data = substr($base64Data, strpos($base64Data, ',') + 1);
        $type = strtolower($type[1]);
        if ($type === 'jpeg') $type = 'jpg';
        // Hanya izinkan ekstensi gambar aman (cegah upload .php dsb.)
        if (!in_array($type, ['jpg', 'png', 'webp'], true)) return '';
        $ext = $type;
    } else {
        $data = $base64Data;
    }
    $decoded = base64_decode($data);
    if (!$decoded) return '';

    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $fileName = $prefix . '_' . time() . '_' . substr(md5(uniqid(rand(), true)), 0, 6) . '.' . $ext;
    $filePath = rtrim($dir, '/') . '/' . $fileName;
    if (file_put_contents($filePath, $decoded)) {
        $GLOBALS['writtenFiles'][] = 'uploads/photos/' . $fileName;
        return 'uploads/photos/' . $fileName;
    }
    return '';
}

$cleanInv = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invoiceNumber);

// 1. Simpan semua foto unboxing (bisa multiple photos) dengan deduplikasi
$photosArr = [];
$packagePhoto = '';
$productPhoto = '';
$damagedPhoto = '';
$savedBase64Map = []; // hash => saved relative path
$photoPathByClientIdx = []; // index array photos dari client => path tersimpan (untuk photo_ref item)

if (!empty($body['photos']) && is_array($body['photos'])) {
    foreach ($body['photos'] as $idx => $itemP) {
        $pStr = is_array($itemP) ? ($itemP['data'] ?? $itemP['path'] ?? '') : $itemP;
        $pType = is_array($itemP) ? ($itemP['type'] ?? 'product') : 'product';
        $pTitle = is_array($itemP) ? ($itemP['title'] ?? '') : '';
        
        $savedPath = '';
        if (strpos($pStr, 'data:image') === 0) {
            $pHash = md5($pStr);
            if (isset($savedBase64Map[$pHash])) {
                $savedPath = $savedBase64Map[$pHash];
            } else {
                $prefix = ($pType === 'package' ? 'pkg_' : ($pType === 'damaged' ? 'dmg_' : 'prod_')) . $cleanInv . "_{$idx}";
                $savedPath = saveBase64Image($pStr, __DIR__ . '/../uploads/photos', $prefix);
                if (!empty($savedPath)) {
                    $savedBase64Map[$pHash] = $savedPath;
                }
            }
        } elseif (!empty($pStr) && is_string($pStr)) {
            $savedPath = $pStr;
        }

        if (!empty($savedPath)) {
            $photoPathByClientIdx[$idx] = $savedPath;
            $isDmg = ($pType === 'damaged' || stripos($pTitle, 'rusak') !== false);
            $photosArr[] = [
                'type' => $pType,
                'path' => $savedPath,
                'title' => $pTitle,
                'badge' => $isDmg ? 'Barang Rusak' : ($pType === 'package' ? 'Paket Retur' : 'Produk Retur')
            ];
            if ($pType === 'package' && empty($packagePhoto)) {
                $packagePhoto = $savedPath;
            } elseif (($pType === 'product' || $pType === 'damaged') && empty($productPhoto)) {
                $productPhoto = $savedPath;
            }
            if ($isDmg && empty($damagedPhoto)) {
                $damagedPhoto = $savedPath;
            }
        }
    }
}

// Fallback jika dikirim via package_photo & product_photo tunggal (legacy / direct)
if (empty($packagePhoto) && !empty($body['package_photo'])) {
    $rawPkg = trim($body['package_photo']);
    if (strpos($rawPkg, 'data:image') === 0) {
        $packagePhoto = saveBase64Image($rawPkg, __DIR__ . '/../uploads/photos', 'pkg_' . $cleanInv);
    } else {
        $packagePhoto = $rawPkg;
    }
    if (!empty($packagePhoto)) {
        $found = false;
        foreach ($photosArr as $p) { if ($p['path'] === $packagePhoto) { $found = true; break; } }
        if (!$found) $photosArr[] = ['type' => 'package', 'path' => $packagePhoto];
    }
}

if (empty($productPhoto) && !empty($body['product_photo'])) {
    $rawProd = trim($body['product_photo']);
    if (strpos($rawProd, 'data:image') === 0) {
        $productPhoto = saveBase64Image($rawProd, __DIR__ . '/../uploads/photos', 'prod_' . $cleanInv);
    } else {
        $productPhoto = $rawProd;
    }
    if (!empty($productPhoto)) {
        $found = false;
        foreach ($photosArr as $p) { if ($p['path'] === $productPhoto) { $found = true; break; } }
        if (!$found) $photosArr[] = ['type' => 'product', 'path' => $productPhoto];
    }
}

$photosJson = count($photosArr) > 0 ? json_encode($photosArr, JSON_UNESCAPED_SLASHES) : null;

// 2. Simpan file video (status upload sudah divalidasi di atas)
$videoPath = null;
$videoStatus = 'no_video';
$uploadDir = __DIR__ . '/../uploads/videos/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// Tentukan ekstensi yang aman
$ext = 'webm';
if (!empty($clientVideoExt) && in_array($clientVideoExt, ['webm', 'mp4'], true)) {
    $ext = $clientVideoExt;
} elseif (isset($_FILES['video']['name'])) {
    $fileExt = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
    if (in_array($fileExt, ['webm', 'mp4'], true)) {
        $ext = $fileExt;
    }
}

$fileName = 'video_' . $cleanInv . '_' . time() . '_' . substr(md5(uniqid((string)rand(), true)), 0, 6) . '.' . $ext;
$targetFile = $uploadDir . $fileName;

// Prioritas 1: Simpan dari $_FILES['video'] jika upload valid
if ($hasFilesVideo) {
    if (move_uploaded_file($_FILES['video']['tmp_name'], $targetFile)) {
        $videoPath = 'uploads/videos/' . $fileName;
        $videoStatus = 'uploaded';
        $writtenFiles[] = $videoPath;
    } else {
        error_log("move_uploaded_file failed for " . $targetFile);
    }
}

// Prioritas 2 / Fallback: Simpan dari Base64 jika belum tersimpan (kebal error upload_tmp_dir)
if (empty($videoPath) && $hasBase64Video) {
    $cleanB64 = $rawVideoBase64;
    if (preg_match('/^data:video\/(\w+);base64,/', $cleanB64, $mType)) {
        $cleanB64 = substr($cleanB64, strpos($cleanB64, ',') + 1);
        $matchedExt = strtolower($mType[1]);
        if (in_array($matchedExt, ['webm', 'mp4'], true)) {
            $ext = $matchedExt;
            $fileName = 'video_' . $cleanInv . '_' . time() . '_' . substr(md5(uniqid((string)rand(), true)), 0, 6) . '.' . $ext;
            $targetFile = $uploadDir . $fileName;
        }
    }
    $decodedVideo = base64_decode($cleanB64);
    if ($decodedVideo && strlen($decodedVideo) >= 100) {
        if (file_put_contents($targetFile, $decodedVideo)) {
            $videoPath = 'uploads/videos/' . $fileName;
            $videoStatus = 'uploaded_base64';
            $writtenFiles[] = $videoPath;
        } else {
            error_log("file_put_contents failed for video to " . $targetFile);
        }
    }
}

// VALIDASI WAJIB: Sesi Inbound Unboxing TIDAK BOLEH disimpan tanpa file video unboxing
if (empty($videoPath) || !file_exists(__DIR__ . '/../' . $videoPath) || filesize(__DIR__ . '/../' . $videoPath) < 100) {
    cleanupWrittenFiles();
    jsonResponse([
        'error' => 'Rekaman video unboxing WAJIB ada dan valid! Pastikan webcam/kamera menyala dan merekam proses unboxing sebelum menyelesaikan sesi.',
        'video_status' => $videoStatus
    ], 400);
}

$totalGood = 0;
$totalDamaged = 0;
$totalItems = 0;

foreach ($items as $item) {
    $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
    if ($qty < 1) $qty = 1;
    $totalItems += $qty;

    // Aturan sama persis dengan kolom `condition` yang disimpan per item di bawah
    $typeT = strtoupper(trim($item['type'] ?? $item['condition'] ?? 'GOOD'));
    if (in_array($typeT, $goodConditionCodes, true)) {
        $totalGood += $qty;
    } else {
        $totalDamaged += $qty;
    }
}

// Schema flags (kolom sudah dipastikan terstruktur di database)
$hasWrongBarcodeCol = true;
$hasWrongProductCol = true;
$hasPhotoPathCol    = true;
$hasDamageReasonCol = true;

try {
    $pdo->beginTransaction();

    $stmtSession = $pdo->prepare("
        INSERT INTO return_sessions (invoice_number, customer_name, expedition, operator_name, total_items, total_good, total_damaged, notes, video_path, package_photo, product_photo, photos)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtSession->execute([
        $invoiceNumber,
        $customerName,
        $expedition,
        $operatorName,
        $totalItems,
        $totalGood,
        $totalDamaged,
        $notes,
        $videoPath,
        $packagePhoto ?: null,
        $productPhoto ?: null,
        $photosJson
    ]);

    $sessionId = $pdo->lastInsertId();

    if ($hasWrongBarcodeCol && $hasWrongProductCol) {
        $stmtItem = $pdo->prepare("
            INSERT INTO return_items (session_id, barcode, wrong_barcode, wrong_product_name, product_name, sku, seller_sku, sap_code, batch_no, exp_date, type, qty, `condition`, damage_reason, photo_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    } else {
        $stmtItem = $pdo->prepare("
            INSERT INTO return_items (session_id, barcode, product_name, sku, seller_sku, sap_code, batch_no, exp_date, type, qty, `condition`, damage_reason, photo_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    }

    foreach ($items as $item) {
        $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
        if ($qty < 1) $qty = 1;
        $type = strtoupper(trim($item['type'] ?? $item['condition'] ?? 'GOOD'));
        $cond = in_array($type, $goodConditionCodes, true) ? 'GOOD' : 'RUSAK';
        $reason = ($cond === 'RUSAK') ? ($item['damage_reason'] ?? $type) : '';

        $wrongBarcode = trim($item['wrong_barcode'] ?? '');
        $wrongProductName = trim($item['wrong_product_name'] ?? '');

        if (!empty($wrongBarcode) || !empty($wrongProductName)) {
            $hasRealWrongBcode = (!empty($wrongBarcode) && $wrongBarcode !== '-');
            $wrongDesc = !empty($wrongProductName) ? $wrongProductName : ($hasRealWrongBcode ? $wrongBarcode : 'Barang Salah Kirim');
            $bPart = $hasRealWrongBcode ? " (Barcode: {$wrongBarcode})" : "";
            $wrongInfo = "[SALAH KIRIM] Fisik: {$wrongDesc}{$bPart}";
            if (empty($reason)) {
                $reason = $wrongInfo;
            } elseif (strpos($reason, '[SALAH KIRIM]') === false) {
                $reason = $wrongInfo . ' | ' . $reason;
            }
        }

        $expDate = trim($item['exp_date'] ?? '');
        if (!empty($expDate) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $expDate, $m)) {
            $expDate = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        $itemPhoto = trim($item['photo_path'] ?? $item['photo'] ?? '');
        // Prioritaskan photo_ref dari array photos yang sudah disimpan (Deduplikasi instan tanpa overhead)
        if (isset($item['photo_ref']) && is_numeric($item['photo_ref'])) {
            $pRefIdx = (int)$item['photo_ref'];
            // photo_ref = index di array photos milik client (bukan index $photosArr yang bisa bergeser)
            if (isset($photoPathByClientIdx[$pRefIdx])) {
                $itemPhoto = $photoPathByClientIdx[$pRefIdx];
            }
        }

        if (!empty($itemPhoto) && strpos($itemPhoto, 'data:image') === 0) {
            $iHash = md5($itemPhoto);
            if (isset($savedBase64Map[$iHash])) {
                // Re-use file yang sudah disimpan di photosArr agar TIDAK TERDUPLIKASI
                $itemPhoto = $savedBase64Map[$iHash];
            } else {
                $bCodeClean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $item['barcode'] ?? 'item');
                $itemPhoto = saveBase64Image($itemPhoto, __DIR__ . '/../uploads/photos', 'item_' . $cleanInv . '_' . $bCodeClean);
                if (!empty($itemPhoto)) {
                    $savedBase64Map[$iHash] = $itemPhoto;
                }
            }
        }
        if (empty($itemPhoto) && $cond === 'RUSAK' && !empty($damagedPhoto)) {
            $itemPhoto = $damagedPhoto;
        }

        if ($hasWrongBarcodeCol && $hasWrongProductCol) {
            $stmtItem->execute([
                $sessionId,
                $item['barcode'] ?? '',
                $wrongBarcode ?: null,
                $wrongProductName ?: null,
                $item['product_name'] ?? '',
                $item['sku'] ?? '',
                $item['seller_sku'] ?? $item['sku'] ?? '',
                $item['sap_code'] ?? '',
                $item['batch_no'] ?? '',
                $expDate,
                $type,
                $qty,
                $cond,
                $reason,
                $itemPhoto ?: null
            ]);
        } else {
            $stmtItem->execute([
                $sessionId,
                $item['barcode'] ?? '',
                $item['product_name'] ?? '',
                $item['sku'] ?? '',
                $item['seller_sku'] ?? $item['sku'] ?? '',
                $item['sap_code'] ?? '',
                $item['batch_no'] ?? '',
                $expDate,
                $type,
                $qty,
                $cond,
                $reason,
                $itemPhoto ?: null
            ]);
        }
    }

    $pdo->commit();

    // Hapus cache dashboard agar dashboard menampilkan data terbaru di request berikutnya
    $cacheDir = __DIR__ . '/../uploads/cache/';
    if (is_dir($cacheDir)) {
        $cFiles = glob($cacheDir . 'metrics_*.json');
        if ($cFiles) {
            foreach ($cFiles as $cf) {
                @unlink($cf);
            }
        }
    }

    jsonResponse([
        'success' => true,
        'session_id' => (int)$sessionId,
        'video_saved' => !empty($videoPath),
        'video_path' => $videoPath,
        'video_status' => $videoStatus,
        'message' => 'Inbound Return berhasil direkam!'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Hapus foto/video yang sudah tertulis agar tidak menjadi file yatim
    cleanupWrittenFiles();
    jsonResponse(['error' => 'Gagal menyimpan return: ' . $e->getMessage()], 500);
}
