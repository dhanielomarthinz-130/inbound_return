<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metode tidak diizinkan'], 405);
}

// 1. Terima payload (bisa via Multipart FormData atau Raw JSON)
$body = [];
if (!empty($_POST['data'])) {
    $body = json_decode($_POST['data'], true);
} else {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $body = json_decode($rawInput, true);
    }
}

if (!$body || !is_array($body)) {
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $maxSize = ini_get('post_max_size');
        jsonResponse(['error' => "Ukuran file video melebihi batas upload server ($maxSize). Silakan rekam lebih singkat atau kurangi durasi."], 413);
    }
    jsonResponse(['error' => 'Format input data tidak valid'], 400);
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
// Helper simpan Base64 Image
function saveBase64Image($base64Data, $dir, $prefix) {
    if (empty($base64Data) || !is_string($base64Data)) return '';
    $ext = 'jpg';
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        $data = substr($base64Data, strpos($base64Data, ',') + 1);
        $type = strtolower($type[1]);
        if ($type === 'jpeg') $type = 'jpg';
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
        return 'uploads/photos/' . $fileName;
    }
    return '';
}

$cleanInv = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invoiceNumber);

// 1. Simpan semua foto unboxing (bisa multiple photos)
$photosArr = [];
$packagePhoto = '';
$productPhoto = '';
$damagedPhoto = '';

if (!empty($body['photos']) && is_array($body['photos'])) {
    foreach ($body['photos'] as $idx => $itemP) {
        $pStr = is_array($itemP) ? ($itemP['data'] ?? $itemP['path'] ?? '') : $itemP;
        $pType = is_array($itemP) ? ($itemP['type'] ?? 'product') : 'product';
        $pTitle = is_array($itemP) ? ($itemP['title'] ?? '') : '';
        
        $savedPath = '';
        if (strpos($pStr, 'data:image') === 0) {
            $prefix = ($pType === 'package' ? 'pkg_' : ($pType === 'damaged' ? 'dmg_' : 'prod_')) . $cleanInv . "_{$idx}";
            $savedPath = saveBase64Image($pStr, __DIR__ . '/../uploads/photos', $prefix);
        } elseif (!empty($pStr) && is_string($pStr)) {
            $savedPath = $pStr;
        }

        if (!empty($savedPath)) {
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

// 2. Cek apakah ada file video yang di-upload via $_FILES
$videoStatus = 'no_video';
if (isset($_FILES['video'])) {
    if ($_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/videos/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);
        if (empty($ext)) $ext = 'webm';
        $fileName = 'video_' . $cleanInv . '_' . time() . '.' . $ext;
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['video']['tmp_name'], $targetFile)) {
            $videoPath = 'uploads/videos/' . $fileName;
            $videoStatus = 'uploaded';
        } else {
            $videoStatus = 'move_error';
            error_log("Failed to move uploaded video file to " . $targetFile);
        }
    } else {
        $videoStatus = 'upload_err_' . $_FILES['video']['error'];
        error_log("Video upload failed with PHP error code: " . $_FILES['video']['error']);
    }
}

if (empty($invoiceNumber) || empty($items) || !is_array($items)) {
    jsonResponse(['error' => 'Nomor Invoice dan minimal 1 produk wajib diisi'], 400);
}

$totalGood = 0;
$totalDamaged = 0;
$totalItems = 0;

foreach ($items as $item) {
    $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
    if ($qty < 1) $qty = 1;
    $totalItems += $qty;

    $condition = strtoupper(trim($item['condition'] ?? 'GOOD'));
    if ($condition === 'GOOD') {
        $totalGood += $qty;
    } else {
        $totalDamaged += $qty;
    }
}

// Anti Double-Submit: Cek apakah invoice_number yang sama persis baru saja di-submit dalam 10 detik terakhir
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

    $stmtItem = $pdo->prepare("
        INSERT INTO return_items (session_id, barcode, product_name, sku, seller_sku, sap_code, batch_no, exp_date, type, qty, `condition`, damage_reason, photo_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
        if ($qty < 1) $qty = 1;
        $type = strtoupper(trim($item['type'] ?? $item['condition'] ?? 'GOOD'));
        $cond = ($type === 'GOOD') ? 'GOOD' : 'RUSAK';
        $reason = ($cond === 'RUSAK') ? ($item['damage_reason'] ?? $type) : '';

        $expDate = trim($item['exp_date'] ?? '');
        if (!empty($expDate) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $expDate, $m)) {
            $expDate = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        $itemPhoto = trim($item['photo_path'] ?? $item['photo'] ?? '');
        if (!empty($itemPhoto) && strpos($itemPhoto, 'data:image') === 0) {
            $bCodeClean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $item['barcode'] ?? 'item');
            $itemPhoto = saveBase64Image($itemPhoto, __DIR__ . '/../uploads/photos', 'item_' . $cleanInv . '_' . $bCodeClean);
        }
        if (empty($itemPhoto) && $cond === 'RUSAK' && !empty($damagedPhoto)) {
            $itemPhoto = $damagedPhoto;
        }

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
    jsonResponse(['error' => 'Gagal menyimpan return: ' . $e->getMessage()], 500);
}
