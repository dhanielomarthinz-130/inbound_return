<?php
/**
 * api/nas_video.php
 * Endpoint Integrasi Video Packing Synology NAS (https://192.168.30.5:5001/ / SMB \\192.168.30.5\PACKER)
 * Mengambil & streaming rekaman video packing dari Stasiun Packing Outbound.
 */
require_once __DIR__ . '/../config.php';
if (file_exists(__DIR__ . '/../nas_config.php')) {
    require_once __DIR__ . '/../nas_config.php';
}

header('Access-Control-Allow-Origin: *');

$action = trim($_GET['action'] ?? $_POST['action'] ?? 'search');
$query  = trim($_GET['q'] ?? $_GET['query'] ?? $_POST['query'] ?? '');
$path   = trim($_GET['path'] ?? $_GET['file'] ?? $_POST['path'] ?? '');

$nasHost     = defined('NAS_HOST') ? NAS_HOST : '192.168.30.5';
$nasPort     = defined('NAS_PORT') ? NAS_PORT : 5001;
$nasProtocol = defined('NAS_PROTOCOL') ? NAS_PROTOCOL : 'https';
$nasFolder   = defined('NAS_FOLDER') ? NAS_FOLDER : '/PACKER';
$nasUser     = defined('NAS_USER') ? NAS_USER : 'admin.cs';
$nasPass     = defined('NAS_PASS') ? NAS_PASS : 'I3g@1234';
$nasSmbPath  = defined('NAS_SMB_PATH') ? NAS_SMB_PATH : "\\\\{$nasHost}\\PACKER";

// -------------------------------------------------------------
// Helper: Memastikan koneksi SMB aktif ke NAS
// -------------------------------------------------------------
function ensureSmbConnected($smbPath, $user, $pass) {
    if (@is_dir($smbPath)) {
        return true;
    }
    if (PHP_OS_FAMILY === 'Windows' && !empty($user) && !empty($pass)) {
        $cmd = 'net use ' . escapeshellarg($smbPath) . ' ' . escapeshellarg($pass) . ' /USER:' . escapeshellarg($user) . ' /persistent:yes 2>&1';
        @exec($cmd);
        return @is_dir($smbPath);
    }
    return false;
}

// -------------------------------------------------------------
// Helper: Stream Video dengan Support HTTP 206 Partial Content (Range)
// -------------------------------------------------------------
function streamVideoWithRange($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo "File video packing tidak ditemukan atau tidak dapat dibaca.";
        exit;
    }

    $size = filesize($filePath);
    $length = $size;
    $start = 0;
    $end = $size - 1;

    header("Content-Type: video/mp4");
    header("Accept-Ranges: bytes");

    if (isset($_SERVER['HTTP_RANGE'])) {
        $c_start = $start;
        $c_end = $end;

        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        if ($range === '-') {
            $c_start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $c_start = $range[0];
            $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size - 1;
        }

        $c_end = ($c_end > $end) ? $end : $c_end;
        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;

        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$size");
    }

    header("Content-Length: " . $length);

    $fp = @fopen($filePath, 'rb');
    if ($fp) {
        fseek($fp, $start);
        $buffer = 1024 * 64;
        while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
            if ($pos + $buffer > $end) {
                $buffer = $end - $pos + 1;
            }
            echo fread($fp, $buffer);
            flush();
        }
        fclose($fp);
    }
    exit;
}

// -------------------------------------------------------------
// Helper: Login ke Synology DSM API (Web API)
// -------------------------------------------------------------
function getSynoSid($protocol, $host, $port, $user, $pass) {
    if (empty($user) || empty($pass)) return null;

    $url = "{$protocol}://{$host}:{$port}/webapi/entry.cgi?api=SYNO.API.Auth&version=3&method=login&account=" . urlencode($user) . "&passwd=" . urlencode($pass) . "&session=FileStation&format=sid";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 6
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    if ($json && !empty($json['success']) && !empty($json['data']['sid'])) {
        return $json['data']['sid'];
    }
    return null;
}

// -------------------------------------------------------------
// 1. ACTION: STREAM VIDEO
// -------------------------------------------------------------
if ($action === 'stream') {
    $targetFile = basename($path);
    if (empty($targetFile)) {
        http_response_code(400);
        die("Nama file video tidak disertakan.");
    }

    // Cek di SMB lokal jika file ada
    $localSmbFile = rtrim($nasSmbPath, "\\/") . DIRECTORY_SEPARATOR . $targetFile;
    if (file_exists($localSmbFile)) {
        streamVideoWithRange($localSmbFile);
    }

    // Stream via Synology DSM Web API
    $sid = getSynoSid($nasProtocol, $nasHost, $nasPort, $nasUser, $nasPass);
    if (!$sid) {
        http_response_code(404);
        die("Autentikasi Synology Web API gagal.");
    }

    $folder = trim($_GET['folder'] ?? $nasFolder);
    $fullNasPath = rtrim($folder, '/') . '/' . $targetFile;
    $downloadUrl = "{$nasProtocol}://{$nasHost}:{$nasPort}/webapi/entry.cgi?api=SYNO.FileStation.Download&version=2&method=download&path=" . urlencode($fullNasPath) . "&mode=open&_sid=" . urlencode($sid);

    $ch = curl_init($downloadUrl);
    $curlHeaders = [];
    if (isset($_SERVER['HTTP_RANGE'])) {
        $curlHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_HEADERFUNCTION => function($curl, $header) {
            $len = strlen($header);
            $h = trim($header);
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                http_response_code((int)$m[1]);
            } elseif (stripos($h, 'Content-Type:') === 0 || 
                      stripos($h, 'Content-Length:') === 0 || 
                      stripos($h, 'Content-Range:') === 0 || 
                      stripos($h, 'Accept-Ranges:') === 0 ||
                      stripos($h, 'ETag:') === 0 ||
                      stripos($h, 'Last-Modified:') === 0) {
                header($h);
            }
            return $len;
        }
    ]);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// -------------------------------------------------------------
// 2. ACTION: SAVE CONFIG
// -------------------------------------------------------------
if ($action === 'save_config') {
    header('Content-Type: application/json; charset=utf-8');
    $newUser   = trim($_POST['nas_user'] ?? '');
    $newPass   = trim($_POST['nas_pass'] ?? '');
    $newFolder = trim($_POST['nas_folder'] ?? '/PACKER');

    $content = "<?php\n"
        . "defined('NAS_HOST') or define('NAS_HOST', '{$nasHost}');\n"
        . "defined('NAS_PORT') or define('NAS_PORT', {$nasPort});\n"
        . "defined('NAS_PROTOCOL') or define('NAS_PROTOCOL', '{$nasProtocol}');\n"
        . "defined('NAS_FOLDER') or define('NAS_FOLDER', " . var_export($newFolder, true) . ");\n"
        . "defined('NAS_USER') or define('NAS_USER', " . var_export($newUser, true) . ");\n"
        . "defined('NAS_PASS') or define('NAS_PASS', " . var_export($newPass, true) . ");\n"
        . "defined('NAS_SMB_PATH') or define('NAS_SMB_PATH', '\\\\\\\\' . NAS_HOST . '\\\\PACKER');\n";

    file_put_contents(__DIR__ . '/../nas_config.php', $content);

    // Test koneksi Web API dan SMB
    $testSid = getSynoSid($nasProtocol, $nasHost, $nasPort, $newUser, $newPass);
    $smbOk = ensureSmbConnected($nasSmbPath, $newUser, $newPass);

    echo json_encode([
        'success'       => true,
        'authenticated' => !empty($testSid) || $smbOk,
        'message'       => (!empty($testSid) || $smbOk) ? 'Konfigurasi Synology NAS berhasil & terhubung!' : 'Konfigurasi tersimpan, namun autentikasi username/password ke NAS ditolak.'
    ]);
    exit;
}

// -------------------------------------------------------------
// 3. ACTION: SEARCH VIDEO PACKING BY RESI / INVOICE
// -------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

$rawCandidates = [
    $query,
    trim($_GET['order_id'] ?? $_POST['order_id'] ?? ''),
    trim($_GET['tracking_number'] ?? $_POST['tracking_number'] ?? ''),
    trim($_GET['package_barcode'] ?? $_POST['package_barcode'] ?? ''),
    trim($_GET['alt_query'] ?? $_POST['alt_query'] ?? '')
];

$rawCandidates = array_values(array_unique(array_filter($rawCandidates)));

if (empty($rawCandidates)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter nomor resi atau invoice (q) tidak boleh kosong.'
    ]);
    exit;
}

$directFileStationUrl = "{$nasProtocol}://{$nasHost}:{$nasPort}/#/signin";

// Bangun varian pencarian dari semua kandidat
$searchVariants = [];
foreach ($rawCandidates as $cand) {
    $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', $cand);
    $alpha = preg_replace('/[^a-zA-Z0-9]/', '', $cand);
    if (strlen($clean) >= 4) $searchVariants[] = $clean;
    if (strlen($alpha) >= 4 && $alpha !== $clean) $searchVariants[] = $alpha;
    // Prefix 12 digit jika panjang
    if (strlen($clean) > 12) $searchVariants[] = substr($clean, 0, 12);
    if (strlen($alpha) > 12 && $alpha !== $clean) $searchVariants[] = substr($alpha, 0, 12);
}
$searchVariants = array_values(array_unique($searchVariants));

if (empty($searchVariants)) {
    echo json_encode([
        'success'   => false,
        'has_video' => false,
        'message'   => 'Kata kunci pencarian terlalu pendek (minimal 4 karakter).'
    ]);
    exit;
}

$foundVideos = [];

// 1. CARI CEPAT MELALUI SYNOLOGY DSM WEB API (FileStation List dengan Pattern)
// Ini berjalan sangat cepat (<1 detik) di NAS tanpa overhead scan 2.2 juta file SMB
$sid = getSynoSid($nasProtocol, $nasHost, $nasPort, $nasUser, $nasPass);
if ($sid) {
    $foldersToCheck = array_values(array_unique([$nasFolder, '/RETURN CAM', '/PACKER-BAK']));
    foreach ($foldersToCheck as $fld) {
        foreach ($searchVariants as $sv) {
            $listUrl = "{$nasProtocol}://{$nasHost}:{$nasPort}/webapi/entry.cgi?" . http_build_query([
                'api'         => 'SYNO.FileStation.List',
                'version'     => 2,
                'method'      => 'list',
                'folder_path' => $fld,
                'pattern'     => "*{$sv}*",
                'additional'  => 'size,time',
                'offset'      => 0,
                'limit'       => 10,
                '_sid'        => $sid
            ]);
            $ch = curl_init($listUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 5
            ]);
            $listRes = curl_exec($ch);
            curl_close($ch);

            $listJson = json_decode($listRes, true);
            $files = $listJson['data']['files'] ?? [];
            foreach ($files as $f) {
                if (!empty($f['isdir'])) continue;
                $fName = $f['name'] ?? '';
                $fExt = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                if (!in_array($fExt, ['mp4', 'webm', 'avi', 'mkv'])) continue;

                // Cek duplikasi
                foreach ($foundVideos as $fv) {
                    if ($fv['name'] === $fName) continue 2;
                }

                $sizeBytes = $f['additional']['size'] ?? 0;
                $mtime = $f['additional']['time'] ?? null;
                $fPath = $f['path'] ?? ($fld . '/' . $fName);

                // Ekstrak info stasiun dari nama file jika ada
                // Contoh: SPXID06493096577A_25_261001FV3UD257_20261002102603.mp4 -> Stasiun 25
                $station = 'Stasiun Packing';
                $parts = explode('_', pathinfo($fName, PATHINFO_FILENAME));
                if (count($parts) >= 3 && is_numeric($parts[1])) {
                    $station = 'Stasiun Packing #' . $parts[1];
                } elseif (count($parts) >= 3) {
                    $station = 'Stasiun ' . $parts[1];
                }

                $foundVideos[] = [
                    'name'           => $fName,
                    'path'           => $fPath,
                    'size'           => $sizeBytes,
                    'size_formatted' => $sizeBytes > 0 ? round($sizeBytes / (1024 * 1024), 2) . ' MB' : '-',
                    'mtime'          => $mtime ? (is_numeric($mtime) ? date('Y-m-d H:i:s', $mtime) : $mtime) : null,
                    'station'        => $station,
                    'folder'         => $fld,
                    'stream_url'     => "api/nas_video.php?action=stream&folder=" . urlencode($fld) . "&file=" . urlencode($fName),
                    'direct_nas_url' => "{$nasProtocol}://{$nasHost}:{$nasPort}/webapi/entry.cgi?api=SYNO.FileStation.Download&version=2&method=download&path=" . urlencode($fPath) . "&mode=open&_sid=" . urlencode($sid)
                ];
            }

            if (!empty($foundVideos)) break 2;
        }
    }
}

// 2. FALLBACK KE SMB JIKA WEB API TIDAK TERSEDIA
if (empty($foundVideos)) {
    $smbConnected = ensureSmbConnected($nasSmbPath, $nasUser, $nasPass);
    if ($smbConnected && @is_dir($nasSmbPath)) {
        foreach ($searchVariants as $sv) {
            // Pencarian langsung di root folder SMB tanpa /s recursive agar tidak hang
            $smbSearchCmd = 'cmd.exe /c "dir /b ' . escapeshellarg($nasSmbPath . '\*' . $sv . '*.mp4') . ' 2>nul"';
            $outputLines = [];
            @exec($smbSearchCmd, $outputLines);

            foreach ($outputLines as $line) {
                $fName = trim(basename($line));
                if (empty($fName)) continue;
                $ext = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                if ($ext !== 'mp4' && $ext !== 'webm') continue;

                foreach ($foundVideos as $fv) {
                    if ($fv['name'] === $fName) continue 2;
                }

                $fullPath = rtrim($nasSmbPath, "\\/") . DIRECTORY_SEPARATOR . $fName;
                $sizeBytes = @filesize($fullPath) ?: 0;
                $mtime = @filemtime($fullPath) ?: null;

                $foundVideos[] = [
                    'name'           => $fName,
                    'path'           => $fName,
                    'size'           => $sizeBytes,
                    'size_formatted' => $sizeBytes > 0 ? round($sizeBytes / (1024 * 1024), 2) . ' MB' : '-',
                    'mtime'          => $mtime ? date('Y-m-d H:i:s', $mtime) : null,
                    'station'        => 'Stasiun Packing',
                    'folder'         => $nasFolder,
                    'stream_url'     => "api/nas_video.php?action=stream&file=" . urlencode($fName),
                    'direct_nas_url' => "{$nasProtocol}://{$nasHost}:{$nasPort}/#/signin"
                ];
            }
            if (!empty($foundVideos)) break;
        }
    }
}

$hasVideo = count($foundVideos) > 0;

echo json_encode([
    'success'         => true,
    'has_video'       => $hasVideo,
    'total'           => count($foundVideos),
    'query'           => $query,
    'folder'          => $nasFolder,
    'videos'          => $foundVideos,
    'primary_video'   => $hasVideo ? $foundVideos[0] : null,
    'quick_open_url'  => $directFileStationUrl,
    'message'         => $hasVideo ? "Video packing ditemukan di NAS-IEG ({$foundVideos[0]['name']})." : "Video packing tidak ditemukan di folder {$nasFolder} untuk: {$query}"
]);

