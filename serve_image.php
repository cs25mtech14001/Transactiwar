<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$upload_path = UPLOAD_DIR . 'profile_images/';

// ── GET & SANITIZE ───────────────────────────────────────────
$filename = $_GET['file'] ?? '';
$filename = str_replace("\0", '', $filename); // null byte
$filename = basename($filename);              // path traversal → strips ../../

// ── STRICT WHITELIST ─────────────────────────────────────────
// Only filenames WE generated: 32 hex chars + extension
if (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp)$/', $filename)) {
    http_response_code(400);
    exit('Invalid request.');
}

$full_path = $upload_path . $filename;
if (!file_exists($full_path) || !is_file($full_path)) {
    http_response_code(404);
    exit('Not found.');
}

// ── RE-VERIFY MIME ───────────────────────────────────────────
$finfo     = new finfo(FILEINFO_MIME_TYPE);
$real_mime = $finfo->file($full_path);
$allowed   = ['image/jpeg','image/png','image/gif','image/webp'];
if (!in_array($real_mime, $allowed, true)) {
    http_response_code(403);
    exit('Forbidden.');
}

// ── SECURITY HEADERS ────────────────────────────────────────
header("Content-Type: $real_mime");
header("Content-Length: " . filesize($full_path));
header("X-Content-Type-Options: nosniff");   // no MIME sniffing
header("Cache-Control: private, no-store");  // no caching of private images
header("Content-Disposition: inline; filename=\"$filename\"");

readfile($full_path);
exit();
?>