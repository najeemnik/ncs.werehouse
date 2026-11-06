<?php
/** Settings come from the environment or a private file outside the web root. */
define('NSC_INTERNAL_SETTINGS',true);require_once __DIR__.'/private-settings.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kabul');
define('DB_HOST', nscSetting('DB_HOST','localhost'));
define('DB_PORT', nscSetting('DB_PORT','3306'));
define('DB_NAME', nscSetting('DB_NAME',''));
define('DB_USER', nscSetting('DB_USER',''));
define('DB_PASS', nscSetting('DB_PASS',''));
define('DB_CHARSET', 'utf8mb4');
define('APP_ENV', nscSetting('APP_ENV','production'));
define('APP_ORIGIN', rtrim(nscSetting('APP_ORIGIN',''), '/'));
define('UPLOAD_DIR', nscSetting('UPLOAD_DIR',dirname(__DIR__, 2) . '/nsc-private-uploads/'));
define('MAX_FILE_SIZE_BYTES', 10 * 1024 * 1024);
define('ALLOWED_FILE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'webp']);

function sendSecurityHeaders(bool $multipart = false): void {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    if (APP_ENV === 'production') {
        if (!APP_ORIGIN || !str_starts_with(APP_ORIGIN, 'https://')) {
            http_response_code(503); echo '{"success":false,"error":"Secure host configuration is required."}'; exit;
        }
        $secure = in_array(strtolower((string)($_SERVER['HTTPS'] ?? '')), ['on','1'], true);
        $proxies = array_filter(array_map('trim', explode(',', getenv('TRUSTED_PROXY_IPS') ?: '')));
        if (!$secure && in_array($_SERVER['REMOTE_ADDR'] ?? '', $proxies, true)) $secure = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        if (!$secure) {http_response_code(426);echo '{"success":false,"error":"HTTPS is required."}';exit;}
        header('Strict-Transport-Security: max-age=31536000');
    }
    foreach($_GET as $value)if(!is_string($value)){http_response_code(400);echo '{"success":false,"error":"Query parameters must be strings."}';exit;}
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== APP_ORIGIN) {
        http_response_code(403); echo '{"success":false,"error":"Origin is not allowed."}'; exit;
    }
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        http_response_code(403); echo '{"success":false,"error":"Cross-site requests are not allowed."}'; exit;
    }
    // No cross-origin credential access. The frontend and PHP API use the same origin.
    if ($method === 'OPTIONS') { http_response_code(204); exit; }
    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
        http_response_code(405); header('Allow: GET, POST, PUT, DELETE'); exit;
    }
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
            http_response_code(403); echo '{"success":false,"error":"A same-origin application request is required."}'; exit;
        }
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', $multipart ? 'multipart/form-data' : 'application/json') !== 0) {
            http_response_code(415); echo '{"success":false,"error":"JSON content is required."}'; exit;
        }
    }
}
