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

// 1. DELETE CONDITION
if ($method === 'DELETE' || $action === 'delete') {
    $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'ID kondisi tidak valid'], 400);
    }

    try {
        $chk = $pdo->prepare("SELECT COUNT(*) AS total FROM return_items WHERE type = (SELECT code FROM master_conditions WHERE id = ?)");
        $chk->execute([$id]);
        $used = (int)$chk->fetch()['total'];
        if ($used > 0) {
            jsonResponse(['error' => "Kondisi ini masih digunakan oleh {$used} data transaksi dan tidak bisa dihapus."], 409);
        }

        $stmt = $pdo->prepare("DELETE FROM master_conditions WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['success' => true, 'message' => 'Kondisi berhasil dihapus']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menghapus kondisi: ' . $e->getMessage()], 500);
    }
}

// 2. UPDATE / EDIT CONDITION
if (($method === 'PUT' || ($action === 'update' && !empty($input['id']))) && $method !== 'DELETE') {
    $id   = (int)($input['id'] ?? $_GET['id'] ?? 0);
    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $color= trim($input['color'] ?? 'slate');
    $sort = (int)($input['sort_order'] ?? 0);

    if ($id <= 0 || empty($code) || empty($name)) {
        jsonResponse(['error' => 'ID, Kode, dan Nama Kondisi wajib diisi'], 400);
    }

    try {
        $chk = $pdo->prepare("SELECT id FROM master_conditions WHERE code = ? AND id != ?");
        $chk->execute([$code, $id]);
        if ($chk->fetch()) {
            jsonResponse(['error' => "Kode kondisi '{$code}' sudah digunakan oleh data lain!"], 409);
        }

        $stmt = $pdo->prepare("UPDATE master_conditions SET code = ?, name = ?, description = ?, color = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$code, $name, $desc, $color, $sort, $id]);

        jsonResponse(['success' => true, 'message' => 'Data kondisi berhasil diperbarui']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal memperbarui kondisi: ' . $e->getMessage()], 500);
    }
}

// 3. CREATE / ADD NEW CONDITION
if ($method === 'POST') {
    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $color= trim($input['color'] ?? 'slate');
    $sort = (int)($input['sort_order'] ?? 0);

    if (empty($code) || empty($name)) {
        jsonResponse(['error' => 'Kode dan Nama Kondisi wajib diisi!'], 400);
    }

    try {
        $chk = $pdo->prepare("SELECT id FROM master_conditions WHERE code = ?");
        $chk->execute([$code]);
        if ($chk->fetch()) {
            jsonResponse(['error' => "Kode kondisi '{$code}' sudah terdaftar!"], 409);
        }

        $stmt = $pdo->prepare("INSERT INTO master_conditions (code, name, description, color, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $desc, $color, $sort]);
        $newId = $pdo->lastInsertId();

        jsonResponse(['success' => true, 'id' => (int)$newId, 'message' => 'Kondisi baru berhasil ditambahkan']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menambahkan kondisi: ' . $e->getMessage()], 500);
    }
}

// 4. GET ALL CONDITIONS
try {
    $stmt = $pdo->query("SELECT * FROM master_conditions ORDER BY sort_order ASC, name ASC");
    $list = $stmt->fetchAll();
    jsonResponse($list);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
