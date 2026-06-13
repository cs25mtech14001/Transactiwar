<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025

// Session Security 
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 1); // Set to 1 in production with HTTPS

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Database Credentials ───────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST'));         // Docker service name
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_CHARSET', getenv('DB_CHARSET'));

// App Settings
if (!defined('BASE_URL')) {
    $is_https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    $scheme = $is_https ? 'https' : 'http';

    define('BASE_URL', $scheme . '://' . $_SERVER['HTTP_HOST']);
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('LOG_DIR',    __DIR__ . '/../logs/');
define('INITIAL_BALANCE', 100.00);
