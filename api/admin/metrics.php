<?php
require_once __DIR__ . '/../../config.php';
session_write_close(); // Lepas session lock agar request paralel cepat

$date    = trim($_GET['date'] ?? '');
$refresh = isset($_GET['refresh']) && ($_GET['refresh'] == '1' || $_GET['refresh'] === 'true');

// Backend Caching (TTL: 60 detik atau refresh on-demand)
$cacheDir = __DIR__ . '/../../uploads/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}
$cacheKey = md5('metrics_' . $date);
$cacheFile = $cacheDir . '/metrics_' . $cacheKey . '.json';

if (!$refresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 60)) {
    header('Content-Type: application/json; charset=utf-8');
    echo file_get_contents($cacheFile);
    exit;
}

try {
    // 0. Ambil rentang tanggal kedatangan paket yang BELUM di-unboxing
    $sqlUnboxRange = "
        SELECT 
            MIN(DATE(COALESCE(rp.scanned_at, er.created_at))) AS min_unboxed_date,
            MAX(DATE(COALESCE(rp.scanned_at, er.created_at))) AS max_unboxed_date,
            COUNT(DISTINCT rp.package_barcode) AS count_unboxed
        FROM reception_packages rp
        JOIN expedition_receptions er ON er.id = rp.reception_id
        LEFT JOIN return_sessions rs ON rs.invoice_number = rp.package_barcode
        WHERE rs.id IS NULL
    ";
    $stmtUnboxRange = $pdo->query($sqlUnboxRange);
    $unboxRange = $stmtUnboxRange->fetch(PDO::FETCH_ASSOC);
    $todayDate      = date('Y-m-d');
    $minUnboxedDate = !empty($unboxRange['min_unboxed_date']) ? $unboxRange['min_unboxed_date'] : $todayDate;
    $maxUnboxedDate = !empty($unboxRange['max_unboxed_date']) ? $unboxRange['max_unboxed_date'] : $todayDate;

    // ── Tentukan Rentang Tanggal Filter ───────────────────────────────────────
    $isRange      = false;
    $startDate    = '';
    $endDate      = '';
    $isAutoFilled = false;

    if (!empty($date)) {
        if (strpos($date, ' to ') !== false) {
            $parts = explode(' to ', $date);
            $startDate = trim($parts[0]);
            $endDate   = trim($parts[1] ?? $parts[0]);
            $isRange   = ($startDate !== $endDate);
        } else {
            $startDate = $date;
            $endDate   = $date;
        }
    } else {
        // Otomatis terisi rentang dari tanggal kedatangan paket belum di-unboxing tertua sampai hari ini
        $startDate    = $minUnboxedDate;
        $endDate      = $todayDate;
        $isRange      = ($startDate !== $endDate);
        $isAutoFilled = true;
    }

    $where = "WHERE s.created_at >= ? AND s.created_at <= ?";
    $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

    $whereRec = "WHERE r.created_at >= ? AND r.created_at <= ?";
    $paramsRec = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

    // ── 0. Receiving Inbound Summary (Surat Jalan & Paket Diterima) ───────────
    $sqlRecKpi = "
        SELECT
            COUNT(DISTINCT r.id) AS total_receptions,
            COALESCE(SUM(r.total_packages), 0) AS total_received_packages
        FROM expedition_receptions r
        {$whereRec}
    ";
    $stmtRecKpi = $pdo->prepare($sqlRecKpi);
    $stmtRecKpi->execute($paramsRec);
    $recKpi = $stmtRecKpi->fetch(PDO::FETCH_ASSOC);

    // ── 1. KPI Unboxing Summary (Paket Selesai, Paket Reject, Qty Pcs) ────────
    // Menggunakan kolom agregat resmi return_sessions yang selalu sinkron dan konsisten
    $sqlKpi = "
        SELECT
            COUNT(DISTINCT s.id)                                                              AS total_invoices,
            COUNT(DISTINCT CASE WHEN s.total_damaged > 0 THEN s.id END)                       AS total_damaged_packages,
            COUNT(DISTINCT CASE WHEN s.total_damaged = 0 AND s.total_good > 0 THEN s.id END) AS total_good_packages,
            COALESCE(SUM(s.total_items), 0)                                                   AS total_items,
            COALESCE(SUM(s.total_good), 0)                                                    AS total_good,
            COALESCE(SUM(s.total_damaged), 0)                                                 AS total_damaged
        FROM return_sessions s
        {$where}
    ";
    $stmtKpi = $pdo->prepare($sqlKpi);
    $stmtKpi->execute($params);
    $metrics = $stmtKpi->fetch(PDO::FETCH_ASSOC);

    // ── 2. Sisa Paket yang Belum di Unboxing ──────────────────────────────────
    // a. Sisa paket dari serah terima yang diterima pada rentang tanggal filter
    $sqlPeriodMissing = "
        SELECT 
            COUNT(DISTINCT rp.package_barcode) AS period_received,
            COUNT(DISTINCT CASE WHEN rs.id IS NOT NULL THEN rp.package_barcode END) AS period_unboxed,
            COUNT(DISTINCT CASE WHEN rs.id IS NULL THEN rp.package_barcode END) AS period_remaining
        FROM reception_packages rp
        JOIN expedition_receptions er ON er.id = rp.reception_id
        LEFT JOIN return_sessions rs ON rs.invoice_number = rp.package_barcode
        WHERE er.created_at >= ? AND er.created_at <= ?
    ";
    $stmtPerMis = $pdo->prepare($sqlPeriodMissing);
    $stmtPerMis->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $perMisRow = $stmtPerMis->fetch(PDO::FETCH_ASSOC);

    // b. Total antrean paket gudang saat ini (all-time reception yang belum pernah di-unboxing)
    $sqlTotalBacklog = "
        SELECT COUNT(DISTINCT rp.package_barcode)
        FROM reception_packages rp
        LEFT JOIN return_sessions rs ON rs.invoice_number = rp.package_barcode
        WHERE rs.id IS NULL
    ";
    $totalBacklog = 0;
    try {
        $totalBacklog = (int)$pdo->query($sqlTotalBacklog)->fetchColumn();
    } catch (Exception $eMis) {}

    $totalReceivedPackages = (int)($recKpi['total_received_packages'] ?? 0);
    $totalReceptions       = (int)($recKpi['total_receptions'] ?? 0);

    $periodReceivedPkgs  = (int)($perMisRow['period_received'] ?? 0);
    $periodUnboxedPkgs   = (int)($perMisRow['period_unboxed'] ?? 0);
    $periodRemainingPkgs = (int)($perMisRow['period_remaining'] ?? 0);

    // Sesuai kebutuhan operasional: data card yang ditampilkan adalah yang difilter,
    // KECUALI Paket yang Belum di-Unboxing yang selalu menampilkan total antrean real-time gudang (backlog)
    $displayMissingPackages = $totalBacklog;

    // Total Paket Selesai di-Unboxing pada rentang tanggal/filter aktif
    $allUnboxedSessions  = (int)($metrics['total_invoices'] ?? 0);
    $syncUnboxedPackages = $allUnboxedSessions;
    $directUnboxedCount  = 0;

    // ── 3. Total Paket per Ekspedisi (Kombinasi Receiving & Unboxing) ───────
    $sqlExpRec = "
        SELECT 
            COALESCE(NULLIF(r.expedition, ''), 'Lainnya') AS expedition,
            COALESCE(SUM(r.total_packages), 0) AS rec_packages,
            COUNT(r.id) AS rec_sessions
        FROM expedition_receptions r
        {$whereRec}
        GROUP BY expedition
    ";
    $stmtExpRec = $pdo->prepare($sqlExpRec);
    $stmtExpRec->execute($paramsRec);
    $expRecRows = $stmtExpRec->fetchAll(PDO::FETCH_ASSOC);

    $sqlExpUnbox = "
        SELECT
            COALESCE(NULLIF(s.expedition, ''), 'Tidak Ada') AS expedition,
            COUNT(DISTINCT s.id)                             AS unbox_packages,
            COUNT(DISTINCT CASE WHEN s.total_damaged > 0 THEN s.id END) AS unbox_damaged_pkgs,
            COUNT(DISTINCT CASE WHEN s.total_damaged = 0 AND s.total_good > 0 THEN s.id END) AS unbox_good_pkgs,
            COALESCE(SUM(s.total_items), 0)                  AS total_qty,
            COALESCE(SUM(s.total_good), 0)                   AS good_qty,
            COALESCE(SUM(s.total_damaged), 0)                AS damaged_qty
        FROM return_sessions s
        {$where}
        GROUP BY expedition
    ";
    $stmtExpUnbox = $pdo->prepare($sqlExpUnbox);
    $stmtExpUnbox->execute($params);
    $expUnboxRows = $stmtExpUnbox->fetchAll(PDO::FETCH_ASSOC);

    $expMap = [];
    foreach ($expRecRows as $er) {
        $name = trim($er['expedition']);
        if (!isset($expMap[$name])) {
            $expMap[$name] = [
                'expedition'         => $name,
                'receiving_packages' => 0,
                'receiving_sessions' => 0,
                'unboxing_packages'  => 0,
                'unbox_good_pkgs'    => 0,
                'unbox_damaged_pkgs' => 0,
                'total_qty'          => 0,
                'good_qty'           => 0,
                'damaged_qty'        => 0,
                'total_packages'     => 0,
                'total_sessions'     => 0
            ];
        }
        $expMap[$name]['receiving_packages'] += (int)$er['rec_packages'];
        $expMap[$name]['receiving_sessions'] += (int)$er['rec_sessions'];
        $expMap[$name]['total_packages']     += (int)$er['rec_packages'];
    }
    foreach ($expUnboxRows as $eu) {
        $name = trim($eu['expedition']);
        if (!isset($expMap[$name])) {
            $expMap[$name] = [
                'expedition'         => $name,
                'receiving_packages' => 0,
                'receiving_sessions' => 0,
                'unboxing_packages'  => 0,
                'unbox_good_pkgs'    => 0,
                'unbox_damaged_pkgs' => 0,
                'total_qty'          => 0,
                'good_qty'           => 0,
                'damaged_qty'        => 0,
                'total_packages'     => 0,
                'total_sessions'     => 0
            ];
        }
        $expMap[$name]['unboxing_packages']  += (int)$eu['unbox_packages'];
        $expMap[$name]['total_sessions']     += (int)$eu['unbox_packages'];
        $expMap[$name]['unbox_good_pkgs']    += (int)$eu['unbox_good_pkgs'];
        $expMap[$name]['unbox_damaged_pkgs'] += (int)$eu['unbox_damaged_pkgs'];
        $expMap[$name]['total_qty']          += (int)$eu['total_qty'];
        $expMap[$name]['good_qty']           += (int)$eu['good_qty'];
        $expMap[$name]['damaged_qty']        += (int)$eu['damaged_qty'];
        if ($expMap[$name]['receiving_packages'] === 0) {
            $expMap[$name]['total_packages'] += (int)$eu['unbox_packages'];
        }
    }

    $byExpeditionCombined = array_values($expMap);
    usort($byExpeditionCombined, function($a, $b) {
        return ($b['receiving_packages'] + $b['unboxing_packages']) <=> ($a['receiving_packages'] + $a['unboxing_packages']);
    });

    // ── 4. Total Kondisi per Ekspedisi ──────────────────────────────────────
    $sqlCond = "
        SELECT
            COALESCE(NULLIF(s.expedition, ''), 'Tidak Ada')    AS expedition,
            UPPER(COALESCE(NULLIF(i.type, ''), i.condition, 'GOOD')) AS condition_code,
            COALESCE(SUM(i.qty), 0)                             AS total_qty,
            COUNT(i.id)                                         AS total_rows
        FROM return_sessions s
        LEFT JOIN return_items i ON i.session_id = s.id
        {$where}
        GROUP BY expedition, condition_code
        ORDER BY expedition ASC, total_qty DESC
    ";
    $stmtCond = $pdo->prepare($sqlCond);
    $stmtCond->execute($params);
    $condRows = $stmtCond->fetchAll(PDO::FETCH_ASSOC);

    // Regroup kondisi per ekspedisi
    $byCondition = [];
    foreach ($condRows as $row) {
        $exp  = $row['expedition'];
        $code = $row['condition_code'];
        if (!isset($byCondition[$exp])) {
            $byCondition[$exp] = [];
        }
        $byCondition[$exp][] = [
            'code'       => $code,
            'total_qty'  => (int)$row['total_qty'],
            'total_rows' => (int)$row['total_rows'],
        ];
    }

    // ── 5. Produktivitas PIC Inbound (Receiving vs Unboxing) ─────────────────
    $sqlPicRec = "
        SELECT
            COALESCE(NULLIF(r.operator_name, ''), 'Gudang 01') AS pic_name,
            COUNT(r.id) AS rec_sessions,
            COALESCE(SUM(r.total_packages), 0) AS rec_packages
        FROM expedition_receptions r
        {$whereRec}
        GROUP BY pic_name
    ";
    $stmtPicRec = $pdo->prepare($sqlPicRec);
    $stmtPicRec->execute($paramsRec);
    $picRecRows = $stmtPicRec->fetchAll(PDO::FETCH_ASSOC);

    $sqlPicUnbox = "
        SELECT
            COALESCE(NULLIF(s.operator_name, ''), 'Gudang 01') AS pic_name,
            COUNT(DISTINCT s.id) AS unbox_packages,
            COALESCE(SUM(s.total_items), 0) AS unbox_items
        FROM return_sessions s
        {$where}
        GROUP BY pic_name
    ";
    $stmtPicUnbox = $pdo->prepare($sqlPicUnbox);
    $stmtPicUnbox->execute($params);
    $picUnboxRows = $stmtPicUnbox->fetchAll(PDO::FETCH_ASSOC);

    $picMap = [];
    foreach ($picRecRows as $pr) {
        $name = trim($pr['pic_name']);
        if (!isset($picMap[$name])) {
            $picMap[$name] = [
                'pic_name'           => $name,
                'receiving_sessions' => 0,
                'receiving_packages' => 0,
                'unboxing_packages'  => 0,
                'unboxing_items'     => 0,
                'total_processed'    => 0
            ];
        }
        $picMap[$name]['receiving_sessions'] += (int)$pr['rec_sessions'];
        $picMap[$name]['receiving_packages'] += (int)$pr['rec_packages'];
        $picMap[$name]['total_processed']    += (int)$pr['rec_packages'];
    }

    foreach ($picUnboxRows as $pu) {
        $name = trim($pu['pic_name']);
        if (!isset($picMap[$name])) {
            $picMap[$name] = [
                'pic_name'           => $name,
                'receiving_sessions' => 0,
                'receiving_packages' => 0,
                'unboxing_packages'  => 0,
                'unboxing_items'     => 0,
                'total_processed'    => 0
            ];
        }
        $picMap[$name]['unboxing_packages'] += (int)$pu['unbox_packages'];
        $picMap[$name]['unboxing_items']    += (int)$pu['unbox_items'];
        $picMap[$name]['total_processed']   += (int)$pu['unbox_packages'];
    }

    $picStats = array_values($picMap);
    usort($picStats, function($a, $b) {
        return $b['total_processed'] <=> $a['total_processed'];
    });

    // ── 6. Trend & Data Per Hari Sesuai Filter ────────────────────────────────
    // Rentang trend: jika range dipilih gunakan range tsb; jika 1 tanggal, gunakan 7 hari terakhir berakhir pada tanggal tsb
    $trendStart = $isRange ? $startDate : date('Y-m-d', strtotime('-6 days', strtotime($endDate)));
    $trendEnd   = $endDate;

    $trendParamsRec = [$trendStart . ' 00:00:00', $trendEnd . ' 23:59:59'];
    $trendParamsUnb = [$trendStart . ' 00:00:00', $trendEnd . ' 23:59:59'];

    // a. Trend Penerimaan Paket (Line Chart)
    $sqlTrendRec = "
        SELECT 
            DATE(er.created_at) as tgl, 
            COUNT(DISTINCT er.id) as total_receptions,
            COALESCE(SUM(er.total_packages), 0) as total_packages
        FROM expedition_receptions er
        WHERE er.created_at >= ? AND er.created_at <= ?
        GROUP BY DATE(er.created_at)
        ORDER BY tgl ASC
    ";
    $stmtTrendRec = $pdo->prepare($sqlTrendRec);
    $stmtTrendRec->execute($trendParamsRec);
    $trendRec = $stmtTrendRec->fetchAll(PDO::FETCH_ASSOC);

    // b. Trend Unboxing Paket & Pcs per Hari (Bar Chart)
    $sqlTrendUnbox = "
        SELECT 
            DATE(s.created_at) as tgl, 
            COUNT(DISTINCT s.id) as total_packages,
            COUNT(DISTINCT CASE WHEN s.total_damaged > 0 THEN s.id END) as reject_packages,
            COUNT(DISTINCT CASE WHEN s.total_damaged = 0 AND s.total_good > 0 THEN s.id END) as good_packages,
            COALESCE(SUM(s.total_items), 0) as total_qty,
            COALESCE(SUM(s.total_good), 0) as good_qty,
            COALESCE(SUM(s.total_damaged), 0) as damaged_qty
        FROM return_sessions s
        WHERE s.created_at >= ? AND s.created_at <= ?
        GROUP BY DATE(s.created_at)
        ORDER BY tgl ASC
    ";
    $stmtTrendUnb = $pdo->prepare($sqlTrendUnbox);
    $stmtTrendUnb->execute($trendParamsUnb);
    $trendUnbox = $stmtTrendUnb->fetchAll(PDO::FETCH_ASSOC);

    // c. Sisa paket belum unboxing per hari
    $sqlTrendRem = "
        SELECT 
            DATE(er.created_at) as tgl,
            COUNT(DISTINCT rp.package_barcode) as total_received,
            COUNT(DISTINCT CASE WHEN rs.id IS NOT NULL THEN rp.package_barcode END) as unboxed_count,
            COUNT(DISTINCT CASE WHEN rs.id IS NULL THEN rp.package_barcode END) as remaining_unboxed
        FROM reception_packages rp
        JOIN expedition_receptions er ON er.id = rp.reception_id
        LEFT JOIN return_sessions rs ON rs.invoice_number = rp.package_barcode
        WHERE er.created_at >= ? AND er.created_at <= ?
        GROUP BY DATE(er.created_at)
    ";
    $stmtTrendRem = $pdo->prepare($sqlTrendRem);
    $stmtTrendRem->execute($trendParamsRec);
    $trendRemRows = $stmtTrendRem->fetchAll(PDO::FETCH_ASSOC);

    $remMap = [];
    foreach ($trendRemRows as $rm) {
        $remMap[$rm['tgl']] = $rm;
    }
    $recMap = [];
    foreach ($trendRec as $tr) {
        $recMap[$tr['tgl']] = $tr;
    }
    $unbMap = [];
    foreach ($trendUnbox as $tu) {
        $unbMap[$tu['tgl']] = $tu;
    }

    // Rekapitulasi Data Harian (Daily Breakdown)
    $allDates = array_unique(array_merge(array_keys($recMap), array_keys($unbMap)));
    sort($allDates);

    $dailyBreakdown = [];
    foreach ($allDates as $d) {
        $r   = $recMap[$d] ?? ['total_packages' => 0, 'total_receptions' => 0];
        $u   = $unbMap[$d] ?? ['total_packages' => 0, 'good_packages' => 0, 'reject_packages' => 0, 'total_qty' => 0, 'good_qty' => 0, 'damaged_qty' => 0];
        $rem = $remMap[$d] ?? ['remaining_unboxed' => 0, 'unboxed_count' => 0];

        $dailyBreakdown[] = [
            'date'              => $d,
            'received_packages' => (int)$r['total_packages'],
            'total_receptions'  => (int)$r['total_receptions'],
            'remaining_unboxed' => (int)$rem['remaining_unboxed'],
            'unboxed_packages'  => (int)$u['total_packages'],
            'good_packages'     => (int)$u['good_packages'],
            'reject_packages'   => (int)$u['reject_packages'],
            'total_pcs'         => (int)$u['total_qty'],
            'good_pcs'          => (int)$u['good_qty'],
            'damaged_pcs'       => (int)$u['damaged_qty']
        ];
    }

    $response = [
        // Paket Diterima (Receiving)
        'total_received_packages'  => $totalReceivedPackages,
        'total_receptions'         => $totalReceptions,

        // Sisa Paket Belum Unboxing
        'total_missing_packages'   => $displayMissingPackages,
        'period_remaining_packages'=> $periodRemainingPkgs,
        'period_received_packages' => $periodReceivedPkgs,
        'period_unboxed_packages'  => $periodUnboxedPkgs,
        'total_backlog_packages'   => $totalBacklog,

        // Paket Selesai Di-unboxing
        'total_invoices'           => $allUnboxedSessions,
        'total_unboxed_receiving'  => $allUnboxedSessions,
        'total_all_unboxed'        => $allUnboxedSessions,
        'direct_unboxed_count'     => 0,
        'total_damaged_packages'   => (int)($metrics['total_damaged_packages'] ?? 0),
        'total_good_packages'      => (int)($metrics['total_good_packages'] ?? 0),

        // Total Unit Fisik (Pcs Qty Produk)
        'total_items'              => (int)($metrics['total_items'] ?? 0),
        'total_good'               => (int)($metrics['total_good'] ?? 0),
        'total_damaged'            => (int)($metrics['total_damaged'] ?? 0),

        // Breakdown Tambahan
        'by_expedition'            => $byExpeditionCombined,
        'by_condition'             => $byCondition,
        'pic_stats'                => $picStats,

        // Data Trend & Rekap Harian
        'trend_receiving'          => $trendRec,
        'trend_unboxing'           => $trendUnbox,
        'daily_breakdown'          => $dailyBreakdown,
        'filter_info'              => [
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'date_str'       => ($startDate !== $endDate) ? "{$startDate} to {$endDate}" : $startDate,
            'is_range'       => $isRange,
            'is_auto_filled' => $isAutoFilled
        ],
        'unboxed_range'            => [
            'start_date' => $minUnboxedDate,
            'end_date'   => $todayDate,
            'date_str'   => ($minUnboxedDate !== $todayDate) ? "{$minUnboxedDate} to {$todayDate}" : $todayDate,
            'count'      => (int)($unboxRange['count_unboxed'] ?? 0)
        ],
        'cached_at'                => date('Y-m-d H:i:s')
    ];

    // Simpan ke cache backend
    @file_put_contents($cacheFile, json_encode($response, JSON_UNESCAPED_UNICODE));

    jsonResponse($response);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
