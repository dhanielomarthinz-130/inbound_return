<?php
/**
 * Auto Database Migration & Sync Endpoint
 * Dipanggil otomatis oleh CI/CD GitHub Actions atau browser saat deploy
 */
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config.php';

    // 1. Skema tabel dan kolom baru sudah dieksekusi secara otomatis oleh config.php
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    // 2. Cek jumlah data master produk
    $prodCount = $pdo->query("SELECT COUNT(*) FROM master_products")->fetchColumn();

    // 3. Jika master produk masih kosong (misal database baru di hosting), jalankan auto sync dari OCS WMS
    $syncedFromOcs = 0;
    if ($prodCount == 0) {
        // Coba jalankan sinkronisasi OCS
        $syncFile = __DIR__ . '/sync_ocs.php';
        if (file_exists($syncFile)) {
            // Include sync logic
            // sync_ocs output handled safely
            ob_start();
            include $syncFile;
            $rawSync = ob_get_clean();
            $syncJson = json_decode($rawSync, true);
            $syncedFromOcs = $syncJson['total_synced'] ?? 0;
            $prodCount = $pdo->query("SELECT COUNT(*) FROM master_products")->fetchColumn();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database skema dan tabel berhasil dimigrasi & diperbarui!',
        'database' => $db_name ?? 'unknown',
        'environment' => !empty($is_remote) ? 'InfinityFree Production' : 'Localhost Laragon',
        'tables_found' => $tables,
        'total_products' => (int)$prodCount,
        'ocs_synced_now' => (int)$syncedFromOcs,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
