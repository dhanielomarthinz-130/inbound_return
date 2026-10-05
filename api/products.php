<?php
require_once __DIR__ . '/../config.php';
session_write_close();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $barcode  = trim($input['barcode'] ?? '');
    $sku      = trim($input['sku'] ?? '');
    $name     = trim($input['name'] ?? '');
    $category = trim($input['category'] ?? 'Umum');
    $unit     = trim($input['unit'] ?? 'Pcs');

    if (empty($barcode) || empty($name)) {
        jsonResponse(['error' => 'Barcode dan Nama Produk wajib diisi!'], 400);
    }

    if (empty($sku)) {
        $sku = 'SKU-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4)) . '-' . rand(10, 99);
    }

    try {
        // Cek duplikasi barcode
        $check = $pdo->prepare("SELECT id FROM master_products WHERE barcode = ?");
        $check->execute([$barcode]);
        if ($check->fetch()) {
            jsonResponse(['error' => 'Barcode ' . $barcode . ' sudah terdaftar!'], 409);
        }

        $stmt = $pdo->prepare("INSERT INTO master_products (barcode, sku, name, category, unit) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$barcode, $sku, $name, $category, $unit]);
        $newId = $pdo->lastInsertId();

        jsonResponse([
            'success' => true,
            'id' => (int)$newId,
            'message' => 'Produk baru berhasil ditambahkan ke database!'
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
} else {
    // GET
    try {
        $stmt = $pdo->query("SELECT * FROM master_products ORDER BY id ASC");
        $products = $stmt->fetchAll();
        jsonResponse($products);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

