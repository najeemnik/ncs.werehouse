<?php
require_once __DIR__ . '/../includes/compat.php';

/**
 * Nik Smart Count - Mobile Shop Management System
 * General System Configuration
 */

// Buffer all outputs to prevent "headers already sent" errors on free hosting and XAMPP
ini_set('zend.exception_ignore_args','1');
if (ob_get_level() === 0) {
    @ob_start();
}

// Enforce UTF-8 internal encoding if mbstring is loaded
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Default Timezone (Asia/Kabul for Afghanistan)
date_default_timezone_set('Asia/Kabul');

// Application Constants
defined('APP_NAME') or define('APP_NAME', 'Nik Smart Count');
defined('APP_BRAND_NAME') or define('APP_BRAND_NAME', 'Nik Smart Count - Warehousing');
defined('APP_BRAND_NAME_FA') or define('APP_BRAND_NAME_FA', 'حسابداری هوشمند نیک - گدام داری');
defined('APP_BRAND_LOGO') or define('APP_BRAND_LOGO', 'branding/nik-smart-count-logo.png');
defined('APP_BRAND_MARK') or define('APP_BRAND_MARK', 'branding/nik-smart-count-mark.png');
defined('APP_BRAND_SIGNATURE') or define('APP_BRAND_SIGNATURE', 'branding/nik-signature-ahmad-najeem-nik.png');
defined('APP_VERSION') or define('APP_VERSION', '2.3.8');
// Legacy sentinel only: a saved shop name equal to this value is treated as "not customised".
// Existing businesses keep their saved name; the public/default brand is APP_BRAND_NAME.
defined('DEFAULT_SHOP_NAME') or define('DEFAULT_SHOP_NAME', 'فروشگاه ضیا');
defined('CURRENCY_NAME') or define('CURRENCY_NAME', 'افغانی');

// Root Paths
defined('BASE_DIR') or define('BASE_DIR', realpath(__DIR__ . '/..'));
defined('INCLUDES_DIR') or define('INCLUDES_DIR', BASE_DIR . '/includes');
defined('MODULES_DIR') or define('MODULES_DIR', BASE_DIR . '/modules');
defined('UPLOADS_DIR') or define('UPLOADS_DIR', BASE_DIR . '/uploads');

// Calculate dynamic BASE_URL so it works seamlessly under any folder name (e.g. /nikxor/, /myshop/, /) on XAMPP and web hosts
if (!defined('BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (empty($scriptName)) {
        define('BASE_URL', '/');
    } else {
        // Detect project subfolder relative to standard folders
        $subDirs = ['/modules/', '/admin/', '/includes/', '/config/', '/uploads/', '/assets/', '/print/', '/reports/', '/api/', '/ajax/'];
        $base = '';
        foreach ($subDirs as $sd) {
            $pos = strpos($scriptName, $sd);
            if ($pos !== false) {
                $base = substr($scriptName, 0, $pos + 1);
                break;
            }
        }
        if (empty($base)) {
            $dir = dirname($scriptName);
            $base = ($dir === '/' || $dir === '\\') ? '/' : rtrim($dir, '/\\') . '/';
        }
        define('BASE_URL', $base ?: '/');
    }
}

// No shared, hard-coded application secret.
require_once __DIR__ . '/../includes/http.php';
header_register_callback('app_prepare_cookie_headers');

// Secure and Universal Session Initialization (Compatible with Desktop, Mobile, XAMPP, and Web Hosting)
if (session_status() === PHP_SESSION_NONE) {
    // 1. Ensure dedicated writable session directory to avoid XAMPP tmp permissions/missing path issues
    $localSessionDir = getenv('NIKXOR_SESSION_DIR') ?: (hosting_profile()?'':dirname(BASE_DIR).'/var/sessions');
    if(hosting_profile()&&!getenv('NIKXOR_SESSION_DIR'))hosting_validate_native_session();
    if ($localSessionDir!==''&&!is_dir($localSessionDir)) {
        @mkdir($localSessionDir, 0700, true);
        @file_put_contents($localSessionDir . '/.htaccess', "Deny from all\n");
    }
    if ($localSessionDir!==''&&is_dir($localSessionDir) && is_writable($localSessionDir)) {
        @chmod($localSessionDir,0700);
        @session_save_path($localSessionDir);
    }

    // Session identifiers are accepted only through secure cookies, never request parameters.
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    ini_set('session.use_trans_sid','0');
    ini_set('session.gc_maxlifetime','43200');

    session_name('NIKXOR_SESSION');

    // 3. Detect HTTPS strictly (Standard SSL, Cloudflare, Reverse Proxy, Port 443)
    $isHttps = app_request_is_https();

    // 4. Set cross-origin and mobile/XAMPP friendly cookie parameters
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0, // Session cookie; authenticated sessions also have server-side expiry.
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps, // True ONLY on HTTPS, False on local HTTP
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    } else {
        session_set_cookie_params(86400 * 30, '/', '', $isHttps, true);
    }

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_path', '/');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
    } else {
        ini_set('session.cookie_secure', '0');
        ini_set('session.cookie_samesite', 'Lax');
    }

    @session_start();
    if(!headers_sent()){header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');if($isHttps)header('Strict-Transport-Security: max-age=31536000');}
}

// Load Multi-Language Localization System (English as Default, Dari, Pashto)
require_once INCLUDES_DIR . '/i18n.php';
init_app_language();

// Multi-shop context (Phase 4 / Stage B). Defines shop_id(), shop_sql(), shop_settings().
require_once INCLUDES_DIR . '/shop.php';

