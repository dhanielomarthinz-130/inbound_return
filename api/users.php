<?php
require_once __DIR__ . '/../config.php';

$currentUser = getSessionUser();
if (!$currentUser) {
    jsonResponse(['error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    session_write_close();
}

// 1. GET USERS LIST
if ($method === 'GET') {
    try {
        if ($currentUser['role'] === 'superadmin') {
            // Superadmin dapat melihat SEMUA user
            $stmt = $pdo->query("SELECT id, username, name, role, pin, status, created_at FROM users ORDER BY id ASC");
        } else {
            // ADMIN dan OPERATOR TIDAK BISA MELIHAT user ber-role superadmin!
            $stmt = $pdo->prepare("SELECT id, username, name, role, pin, status, created_at FROM users WHERE role != 'superadmin' ORDER BY id ASC");
            $stmt->execute();
        }
        $users = $stmt->fetchAll();
        jsonResponse($users);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

// 2. CREATE NEW USER
if ($method === 'POST') {
    if (!in_array($currentUser['role'], ['superadmin', 'admin'])) {
        jsonResponse(['error' => 'Akses ditolak: Hanya Admin/Superadmin yang dapat menambah user.'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $name     = trim($input['name'] ?? '');
    $role     = trim($input['role'] ?? 'operator');
    $status   = trim($input['status'] ?? 'ACTIVE');
    $pin      = trim($input['pin'] ?? '123456');
    if (empty($pin)) $pin = '123456';

    if (empty($username) || empty($password) || empty($name)) {
        jsonResponse(['error' => 'Username, Password, dan Nama Lengkap wajib diisi!'], 400);
    }

    // Validasi Role: Admin dilarang membuat akun superadmin
    if ($role === 'superadmin' && $currentUser['role'] !== 'superadmin') {
        jsonResponse(['error' => 'Akses ditolak: Hanya Superadmin yang berhak mendaftarkan user dengan role Superadmin!'], 403);
    }

    if (!in_array($role, ['superadmin', 'admin', 'operator'])) {
        $role = 'operator';
    }

    try {
        // Cek duplikasi username
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            jsonResponse(['error' => "Username '{$username}' sudah digunakan!"], 409);
        }

        $hashedPass = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, status, pin) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashedPass, $name, $role, $status, $pin]);

        jsonResponse([
            'success' => true,
            'id' => (int)$pdo->lastInsertId(),
            'message' => "User '{$name}' ({$role}) berhasil didaftarkan!"
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

// 3. UPDATE USER
if ($method === 'PUT') {
    if (!in_array($currentUser['role'], ['superadmin', 'admin'])) {
        jsonResponse(['error' => 'Akses ditolak: Hanya Admin/Superadmin yang dapat mengubah user.'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id       = (int)($input['id'] ?? 0);
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $name     = trim($input['name'] ?? '');
    $role     = trim($input['role'] ?? 'operator');
    $status   = trim($input['status'] ?? 'ACTIVE');
    $pin      = isset($input['pin']) ? trim($input['pin']) : null;

    if ($id <= 0 || empty($username) || empty($name)) {
        jsonResponse(['error' => 'Data tidak lengkap untuk update user.'], 400);
    }

    try {
        // Ambil data user target saat ini
        $stmtTarget = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmtTarget->execute([$id]);
        $targetUser = $stmtTarget->fetch();
        if (!$targetUser) {
            jsonResponse(['error' => 'User tidak ditemukan.'], 404);
        }

        // Jika target adalah superadmin dan pengubah bukan superadmin, TOLAK
        if ($targetUser['role'] === 'superadmin' && $currentUser['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Akses ditolak: Anda tidak memiliki izin untuk mengedit data Superadmin!'], 403);
        }

        // Jika admin biasa mencoba mengubah role user menjadi superadmin, TOLAK
        if ($role === 'superadmin' && $currentUser['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Akses ditolak: Hanya Superadmin yang berhak menetapkan role Superadmin!'], 403);
        }

        // Cek duplikasi username untuk user lain
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $chk->execute([$username, $id]);
        if ($chk->fetch()) {
            jsonResponse(['error' => "Username '{$username}' sudah digunakan oleh akun lain!"], 409);
        }

        $pinValue = ($pin !== null && $pin !== '') ? $pin : ($targetUser['pin'] ?: '123456');

        if (!empty($password)) {
            $hashedPass = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, name = ?, role = ?, status = ?, pin = ? WHERE id = ?");
            $stmt->execute([$username, $hashedPass, $name, $role, $status, $pinValue, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, role = ?, status = ?, pin = ? WHERE id = ?");
            $stmt->execute([$username, $name, $role, $status, $pinValue, $id]);
        }

        jsonResponse([
            'success' => true,
            'message' => "Data user '{$name}' berhasil diperbarui!"
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

// 4. DELETE USER
if ($method === 'DELETE' || (isset($_GET['action']) && $_GET['action'] === 'delete')) {
    if (!in_array($currentUser['role'], ['superadmin', 'admin'])) {
        jsonResponse(['error' => 'Akses ditolak: Hanya Admin/Superadmin yang dapat menghapus user.'], 403);
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'ID user tidak valid.'], 400);
    }

    if ($id === (int)$currentUser['id']) {
        jsonResponse(['error' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!'], 400);
    }

    try {
        $stmtTarget = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmtTarget->execute([$id]);
        $targetUser = $stmtTarget->fetch();
        if (!$targetUser) {
            jsonResponse(['error' => 'User tidak ditemukan.'], 404);
        }

        // Larang admin menghapus superadmin
        if ($targetUser['role'] === 'superadmin' && $currentUser['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus akun Superadmin!'], 403);
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse([
            'success' => true,
            'message' => "User '{$targetUser['name']}' berhasil dihapus dari sistem."
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}
