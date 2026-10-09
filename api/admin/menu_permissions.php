<?php
/**
 * api/admin/menu_permissions.php
 * Endpoint API Control Panel Hak Akses Menu & Role
 * Hanya Super Admin yang berhak mengubah / menyimpan konfigurasi izin menu.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config.php';

$user = getSessionUser();
if (!$user) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
}

$rawRole = strtolower(trim(str_replace([' ', '_', '-'], '', $user['role'] ?? '')));
$isSuperAdmin = ($rawRole === 'superadmin');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. GET: Ambil Definisi Menu, Role, dan Pemetaan Izin Aktif
if ($method === 'GET') {
    try {
        $allMenus = getSystemMenus();
        $defaults = getDefaultMenuPermissions();
        $activePerms = getRoleMenuPermissions($pdo);

        // Ambil daftar role dari tabel roles
        $stmtRoles = $pdo->query("
            SELECT id, role_key, role_name, description, permissions, is_system, created_at, updated_at 
            FROM roles 
            ORDER BY is_system DESC, id ASC
        ");
        $rolesList = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

        // Format permissions untuk masing-masing role
        $formattedRoles = [];
        foreach ($rolesList as $r) {
            $rKey = strtolower(trim(str_replace([' ', '-'], '_', $r['role_key'])));
            $perms = !empty($r['permissions']) ? json_decode($r['permissions'], true) : null;
            if (!is_array($perms)) {
                $perms = $defaults[$rKey] ?? ($defaults[$r['role_key']] ?? []);
            }
            if ($rKey === 'superadmin') {
                $perms = array_keys($allMenus);
            }

            $r['effective_permissions'] = $perms;
            $formattedRoles[] = $r;
        }

        jsonResponse([
            'success'       => true,
            'is_superadmin' => $isSuperAdmin,
            'menus'         => array_values($allMenus),
            'roles'         => $formattedRoles,
            'permissions'   => $activePerms,
            'defaults'      => $defaults
        ]);

    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'error'   => 'Gagal memuat konfigurasi hak akses: ' . $e->getMessage()
        ], 500);
    }
}

// 2. POST / PUT: Simpan Perubahan Hak Akses Menu (HANYA SUPER ADMIN!)
if ($method === 'POST' || $method === 'PUT') {
    if (!$isSuperAdmin) {
        jsonResponse([
            'success' => false,
            'error'   => 'Akses Ditolak: Hanya Super Admin yang berhak mengatur dan menyimpan hak akses menu.'
        ], 403);
    }

    try {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $action = trim($input['action'] ?? $_GET['action'] ?? 'save');
        $allMenuKeys = array_keys(getSystemMenus());

        // A. Reset ke rekomendasi default sistem
        if ($action === 'reset_defaults') {
            $defaults = getDefaultMenuPermissions();
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("UPDATE roles SET permissions = :perms, updated_at = NOW() WHERE role_key = :role_key");
            foreach ($defaults as $rKey => $menuList) {
                if ($rKey === 'superadmin') continue;
                $stmtUpdate->execute([
                    ':perms'    => json_encode(array_values($menuList)),
                    ':role_key' => $rKey
                ]);
            }

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => 'Seluruh hak akses menu berhasil dikembalikan ke rekomendasi awal sistem!'
            ]);
        }

        // B. Simpan konfigurasi baru yang ditentukan oleh Super Admin
        $permissions = $input['permissions'] ?? [];
        if (!is_array($permissions)) {
            jsonResponse(['success' => false, 'error' => 'Format data hak akses tidak valid.'], 400);
        }

        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("
            UPDATE roles 
            SET permissions = :permissions, updated_at = NOW() 
            WHERE role_key = :role_key
        ");

        $updatedCount = 0;
        foreach ($permissions as $roleKey => $menuList) {
            $cleanRoleKey = strtolower(trim($roleKey));
            
            // Superadmin selalu memiliki full access, tidak perlu diubah
            if ($cleanRoleKey === 'superadmin') {
                continue;
            }

            // Validasi & filter hanya menu keys yang valid di sistem
            $validMenuList = [];
            if (is_array($menuList)) {
                foreach ($menuList as $mKey) {
                    $mKeyClean = trim($mKey);
                    // Control panel akses menu tidak dapat diberikan ke selain superadmin
                    if ($mKeyClean === 'menu-permissions') continue;

                    if (in_array($mKeyClean, $allMenuKeys, true)) {
                        $validMenuList[] = $mKeyClean;
                    }
                }
            }
            $validMenuList = array_values(array_unique($validMenuList));

            $jsonPerms = !empty($validMenuList) ? json_encode($validMenuList, JSON_UNESCAPED_UNICODE) : json_encode([]);

            $stmtUpdate->execute([
                ':permissions' => $jsonPerms,
                ':role_key'    => $cleanRoleKey
            ]);
            $updatedCount++;
        }

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "Hak akses menu untuk {$updatedCount} role berhasil diperbarui oleh Super Admin!",
            'updated' => $updatedCount
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse([
            'success' => false,
            'error'   => 'Gagal menyimpan hak akses menu: ' . $e->getMessage()
        ], 500);
    }
}

jsonResponse(['success' => false, 'error' => 'Metode HTTP tidak didukung'], 405);
