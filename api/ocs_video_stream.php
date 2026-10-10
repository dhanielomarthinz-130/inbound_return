<?php
/**
 * api/ocs_video_stream.php
 * Proxy video streaming dari OCS IEG System dengan otentikasi Bearer Token otomatis
 * Mendukung HTTP Range Requests untuk scrubbing / seeking video di browser
 */
set_time_limit(0);

$orderId = trim($_GET['orderId'] ?? $_GET['order_id'] ?? '');
if ($orderId === '') {
    http_response_code(400);
    die("Parameter orderId wajib diisi.");
}

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

$ocsBaseUrl = defined('OCS_BASE_URL') ? OCS_BASE_URL : 'https://ocs.iegsystem.id';
$ocsUser    = defined('OCS_USERNAME') ? OCS_USERNAME : 'ADMIN';
$ocsPass    = defined('OCS_PASSWORD') ? OCS_PASSWORD : 'luwakwhitecoffee';
$ocsCompany = defined('OCS_COMPANYDB') ? OCS_COMPANYDB : 'EJI_WMS';

// Token caching sederhana (50 menit)
$tokenCacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocs_token_cache.json';
$token = null;

if (file_exists($tokenCacheFile)) {
    $cached = json_decode(file_get_contents($tokenCacheFile), true);
    if (!empty($cached['token']) && !empty($cached['expire']) && $cached['expire'] > time()) {
        $token = $cached['token'];
    }
}

if (!$token) {
    $ch = curl_init("{$ocsBaseUrl}/Auth/Login");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'username'  => $ocsUser,
            'password'  => $ocsPass,
            'companydb' => $ocsCompany
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 15
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $res) {
        $data = json_decode($res, true);
        $token = $data['Token'] ?? null;
        if ($token) {
            file_put_contents($tokenCacheFile, json_encode([
                'token'  => $token,
                'expire' => time() + 3000
            ]));
        }
    }
}

if (!$token) {
    http_response_code(502);
    die("Gagal mengotentikasi ke server video OCS.");
}

// Teruskan request ke Streaming/Packing dengan Range header (jika ada)
$streamUrl = "{$ocsBaseUrl}/Streaming/Packing?orderId=" . urlencode($orderId);
$headersToSend = [
    "Authorization: Bearer {$token}"
];

if (isset($_SERVER['HTTP_RANGE'])) {
    $headersToSend[] = "Range: " . $_SERVER['HTTP_RANGE'];
}

$chStream = curl_init($streamUrl);
curl_setopt_array($chStream, [
    CURLOPT_HTTPHEADER     => $headersToSend,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HEADER         => false,
    CURLOPT_TIMEOUT        => 300,
    CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) {
        $lower = strtolower($headerLine);
        // Forward relevant headers untuk streaming video
        if (strpos($lower, 'content-type:') === 0 ||
            strpos($lower, 'content-length:') === 0 ||
            strpos($lower, 'content-range:') === 0 ||
            strpos($lower, 'accept-ranges:') === 0) {
            header(trim($headerLine));
        }
        return strlen($headerLine);
    },
    CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) {
        echo $chunk;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        return strlen($chunk);
    }
]);

// Set default header video
header("Accept-Ranges: bytes");
header("Content-Type: video/mp4");

curl_exec($chStream);
$httpCode = curl_getinfo($chStream, CURLINFO_HTTP_CODE);
curl_close($chStream);

if ($httpCode >= 400 && $httpCode !== 416) {
    http_response_code($httpCode);
}
