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

// 1. Simpan semua foto unboxing (bisa multiple photos) dengan deduplikasi
$photosArr = [];
$packagePhoto = '';
$productPhoto = '';
$damagedPhoto = '';
$savedBase64Map = []; // hash => saved relative path

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
$videoPath = null;
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

// Self-healing: Pastikan kolom return_sessions & return_items lengkap sebelum transaksi
try {
    $rSessCols = $pdo->query("SHOW COLUMNS FROM return_sessions")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('expedition', $rSessCols)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN expedition VARCHAR(100) NULL AFTER customer_name");
    if (!in_array('video_path', $rSessCols)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN video_path VARCHAR(255) NULL AFTER notes");
    if (!in_array('package_photo', $rSessCols)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN package_photo VARCHAR(255) NULL AFTER video_path");
    if (!in_array('product_photo', $rSessCols)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN product_photo VARCHAR(255) NULL AFTER package_photo");
    if (!in_array('photos', $rSessCols)) $pdo->exec("ALTER TABLE return_sessions ADD COLUMN photos TEXT NULL AFTER product_photo");

    $rItemCols = $pdo->query("SHOW COLUMNS FROM return_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('wrong_barcode', $rItemCols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN wrong_barcode VARCHAR(100) NULL AFTER barcode");
    if (!in_array('wrong_product_name', $rItemCols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN wrong_product_name VARCHAR(255) NULL AFTER wrong_barcode");
    if (!in_array('damage_reason', $rItemCols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN damage_reason VARCHAR(255) NULL AFTER `condition`");
    if (!in_array('photo_path', $rItemCols)) $pdo->exec("ALTER TABLE return_items ADD COLUMN photo_path VARCHAR(255) NULL AFTER damage_reason");
} catch (Exception $eSchemaFix) {
    // Non-blocking schema auto-fix
}

// Cek ulang kolom yang aktif di return_items agar query INSERT tidak pernah crash
$activeItemCols = [];
try {
    $activeItemCols = $pdo->query("SHOW COLUMNS FROM return_items")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $eColChk) {}

$hasWrongBarcodeCol = in_array('wrong_barcode', $activeItemCols);
$hasWrongProductCol = in_array('wrong_product_name', $activeItemCols);
$hasPhotoPathCol    = in_array('photo_path', $activeItemCols);
$hasDamageReasonCol = in_array('damage_reason', $activeItemCols);

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
        $cond = ($type === 'GOOD') ? 'GOOD' : 'RUSAK';
        $reason = ($cond === 'RUSAK') ? ($item['damage_reason'] ?? $type) : '';

        $wrongBarcode = trim($item['wrong_barcode'] ?? '');
        $wrongProductName = trim($item['wrong_product_name'] ?? '');

        if (!empty($wrongBarcode)) {
            $wrongInfo = "[SALAH KIRIM] Fisik: " . ($wrongProductName ?: $wrongBarcode) . " (Barcode: {$wrongBarcode})";
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
    jsonResponse(['error' => 'Gagal menyimpan return: ' . $e->getMessage()], 500);
}
