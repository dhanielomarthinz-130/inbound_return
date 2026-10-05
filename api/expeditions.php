<?php
require_once __DIR__ . '/../config.php';
session_write_close();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input) {
    $input = $_POST;
}

$action = $_GET['action'] ?? $input['action'] ?? '';

// 1. DELETE EXPEDITION
if ($method === 'DELETE' || $action === 'delete') {
    $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'ID ekspedisi tidak valid'], 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM master_expeditions WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse([
            'success' => true,
            'message' => 'Ekspedisi berhasil dihapus'
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menghapus ekspedisi: ' . $e->getMessage()], 500);
    }
}

// 2. UPDATE / EDIT EXPEDITION
if (($method === 'PUT' || $action === 'update' || (!empty($input['id']) && (int)$input['id'] > 0 && $action !== 'create')) && $method !== 'DELETE') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    $prefixPattern = strtoupper(trim($input['prefix_pattern'] ?? ''));
    $status = strtoupper(trim($input['status'] ?? 'ACTIVE'));

    if ($id <= 0 || empty($code) || empty($name)) {
        jsonResponse(['error' => 'ID, Kode, dan Nama Ekspedisi wajib diisi'], 400);
    }

    try {
        // Cek duplikasi kode selain ID ini
        $chk = $pdo->prepare("SELECT id FROM master_expeditions WHERE code = ? AND id != ?");
        $chk->execute([$code, $id]);
        if ($chk->fetch()) {
            jsonResponse(['error' => "Kode ekspedisi '{$code}' sudah digunakan oleh data lain!"], 409);
        }

        $stmt = $pdo->prepare("UPDATE master_expeditions SET code = ?, name = ?, prefix_pattern = ?, status = ? WHERE id = ?");
        $stmt->execute([$code, $name, $prefixPattern, $status, $id]);

        jsonResponse([
            'success' => true,
            'message' => 'Data ekspedisi berhasil diperbarui'
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal memperbarui ekspedisi: ' . $e->getMessage()], 500);
    }
}

// 3. CREATE / ADD NEW EXPEDITION
if ($method === 'POST') {
    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    $prefixPattern = strtoupper(trim($input['prefix_pattern'] ?? ''));
    $status = strtoupper(trim($input['status'] ?? 'ACTIVE'));

    if (empty($code) || empty($name)) {
        jsonResponse(['error' => 'Kode dan Nama Ekspedisi wajib diisi!'], 400);
    }

    try {
        $chk = $pdo->prepare("SELECT id FROM master_expeditions WHERE code = ?");
        $chk->execute([$code]);
        if ($chk->fetch()) {
            jsonResponse(['error' => "Kode ekspedisi '{$code}' sudah terdaftar!"], 409);
        }

        $stmt = $pdo->prepare("INSERT INTO master_expeditions (code, name, prefix_pattern, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $name, $prefixPattern, $status]);
        $newId = $pdo->lastInsertId();

        jsonResponse([
            'success' => true,
            'id' => (int)$newId,
            'message' => 'Ekspedisi baru berhasil ditambahkan'
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menambahkan ekspedisi: ' . $e->getMessage()], 500);
    }
}

// 4. GET ALL EXPEDITIONS
try {
    $stmt = $pdo->query("SELECT * FROM master_expeditions ORDER BY name ASC");
    $list = $stmt->fetchAll();
    jsonResponse($list);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
