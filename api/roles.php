<?php
/**
 * api/roles.php
 * Endpoint CRUD Manajemen Role Pengguna.
 * Mendukung Tambah (Add), Edit, dan Hapus (Delete) Role dengan validasi keamanan.
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$currentUser = getSessionUser();
if (!$currentUser) {
    jsonResponse(['error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

// Hanya Admin dan Superadmin yang dapat mengelola Role
if (!in_array($currentUser['role'] ?? '', ['superadmin', 'admin'], true)) {
    jsonResponse(['error' => 'Akses ditolak: Hanya Admin/Superadmin yang berhak mengelola Role.'], 403);
}

ensureClaimStatusColumn($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. GET ALL ROLES
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT r.id, r.role_key, r.role_name, r.description, r.permissions, r.is_system, r.created_at, r.updated_at,
                   COUNT(u.id) as user_count
            FROM roles r
            LEFT JOIN users u ON u.role = r.role_key
            GROUP BY r.id, r.role_key, r.role_name, r.description, r.permissions, r.is_system, r.created_at, r.updated_at
            ORDER BY r.is_system DESC, r.id ASC
        ");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'success' => true,
            'roles'   => $roles
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal memuat daftar role: ' . $e->getMessage()], 500);
    }
}

// 2. ADD NEW ROLE
if ($method === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $roleKey     = strtolower(trim($input['role_key'] ?? ''));
        $roleName    = trim($input['role_name'] ?? '');
        $description = trim($input['description'] ?? '');
        $permissions = isset($input['permissions']) ? (is_array($input['permissions']) ? json_encode($input['permissions']) : trim($input['permissions'])) : null;

        // Validasi Role Key: hanya huruf kecil, angka, dan underscore
        $roleKey = preg_replace('/[^a-z0-9_]/', '_', $roleKey);
        $roleKey = trim($roleKey, '_');

        if (empty($roleKey) || strlen($roleKey) < 2) {
            jsonResponse(['error' => 'Kode Role (Key) wajib diisi minimal 2 karakter alfanumerik (contoh: accounting, finance, supervisor)!'], 400);
        }

        if (empty($roleName)) {
            jsonResponse(['error' => 'Nama Role wajib diisi!'], 400);
        }

        // Cek duplikasi
        $stmtCheck = $pdo->prepare("SELECT id FROM roles WHERE role_key = ?");
        $stmtCheck->execute([$roleKey]);
        if ($stmtCheck->fetch()) {
            jsonResponse(['error' => "Kode Role '{$roleKey}' sudah ada! Gunakan kode role yang berbeda."], 409);
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO roles (role_key, role_name, description, permissions, is_system) 
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmtInsert->execute([$roleKey, $roleName, $description, $permissions]);

        jsonResponse([
            'success' => true,
            'message' => "Role baru '{$roleName}' ({$roleKey}) berhasil ditambahkan!",
            'role_id' => (int)$pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menambahkan role: ' . $e->getMessage()], 500);
    }
}

// 3. EDIT ROLE
if ($method === 'PUT') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $id          = (int)($input['id'] ?? 0);
        $roleName    = trim($input['role_name'] ?? '');
        $description = trim($input['description'] ?? '');
        $permissions = isset($input['permissions']) ? (is_array($input['permissions']) ? json_encode($input['permissions']) : trim($input['permissions'])) : null;

        if ($id <= 0) {
            jsonResponse(['error' => 'ID Role tidak valid!'], 400);
        }

        if (empty($roleName)) {
            jsonResponse(['error' => 'Nama Role wajib diisi!'], 400);
        }

        $stmtRole = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmtRole->execute([$id]);
        $existing = $stmtRole->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            jsonResponse(['error' => 'Role tidak ditemukan!'], 404);
        }

        // Update role
        $stmtUpdate = $pdo->prepare("
            UPDATE roles 
            SET role_name = ?, description = ?, permissions = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$roleName, $description, $permissions, $id]);

        jsonResponse([
            'success' => true,
            'message' => "Role '{$roleName}' berhasil diperbarui!"
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal memperbarui role: ' . $e->getMessage()], 500);
    }
}

// 4. DELETE ROLE
if ($method === 'DELETE') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_REQUEST;

        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['error' => 'ID Role tidak valid!'], 400);
        }

        $stmtRole = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmtRole->execute([$id]);
        $existing = $stmtRole->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            jsonResponse(['error' => 'Role tidak ditemukan!'], 404);
        }

        // Proteksi 1: Role sistem bawaan tidak boleh dihapus
        if (!empty($existing['is_system'])) {
            jsonResponse(['error' => "Role sistem bawaan '{$existing['role_name']}' tidak boleh dihapus."], 403);
        }

        // Proteksi 2: Cek apakah masih ada user yang menggunakan role ini
        $stmtUserCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmtUserCheck->execute([$existing['role_key']]);
        $userCount = (int)$stmtUserCheck->fetchColumn();

        if ($userCount > 0) {
            jsonResponse([
                'error' => "Role '{$existing['role_name']}' tidak dapat dihapus karena masih digunakan oleh {$userCount} akun pengguna. Silakan ubah role pengguna tersebut terlebih dahulu."
            ], 400);
        }

        $stmtDelete = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmtDelete->execute([$id]);

        jsonResponse([
            'success' => true,
            'message' => "Role '{$existing['role_name']}' ({$existing['role_key']}) berhasil dihapus!"
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal menghapus role: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Metode HTTP tidak didukung'], 405);
