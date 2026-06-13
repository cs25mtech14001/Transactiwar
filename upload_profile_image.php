<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/logger.php';

require_login();

$pdo  = get_db();
$user = get_logged_in_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/public/profile.php');
}

// ── CONTENT-TYPE GUARD ───────────────────────────────────────
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'multipart/form-data') === false) {
    redirect(BASE_URL . '/public/profile.php?error=upload_failed');
}

// ── RATE LIMITING ────────────────────────────────────────────
if (!isset($_SESSION['img_upload_attempts'])) {
    $_SESSION['img_upload_attempts'] = 0;
    $_SESSION['img_upload_window']   = time();
}
if (time() - $_SESSION['img_upload_window'] > 120) {
    $_SESSION['img_upload_attempts'] = 0;
    $_SESSION['img_upload_window']   = time();
}
$_SESSION['img_upload_attempts']++;
if ($_SESSION['img_upload_attempts'] > 5) {
    http_response_code(429);
    redirect(BASE_URL . '/public/profile.php?error=rate_limited');
}

// ── CSRF GUARD ───────────────────────────────────────────────
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . '/public/profile.php?error=csrf_error');
}

$user_id = (int) $_SESSION['user_id'];
log_activity('handlers/upload_profile_image.php', $user['username']);

// ── CONFIG ───────────────────────────────────────────────────
// UPLOAD_DIR comes from config.php — no redefinition needed
$upload_path = UPLOAD_DIR . 'profile_images/';
define('MAX_SIZE', 2 * 1024 * 1024);
define('OK_MIME',  ['image/jpeg','image/png','image/gif','image/webp']);
define('OK_EXT',   ['jpg','jpeg','png','gif','webp']);

function bail(string $err): void {
    redirect(BASE_URL . '/public/profile.php?error=' . $err);
}

// ── FILE PRESENCE CHECK ──────────────────────────────────────
if (!isset($_FILES['profile_image']) ||
    $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    bail('upload_failed');
}
$f = $_FILES['profile_image'];

// ── is_uploaded_file() CHECK ─────────────────────────────────
// Burp can craft a multipart request where tmp_name points to
// a server file like /etc/passwd. This check blocks that.
if (!is_uploaded_file($f['tmp_name'])) {
    error_log("is_uploaded_file() failed for uid=$user_id");
    bail('upload_failed');
}

// ── SIZE CHECK ───────────────────────────────────────────────
if ($f['size'] > MAX_SIZE || $f['size'] === 0) {
    bail('file_too_large');
}

// ── NULL BYTE STRIP ──────────────────────────────────────────
$orig = str_replace("\0", '', $f['name']);

// ── EXTENSION CHECK ──────────────────────────────────────────
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
if (!in_array($ext, OK_EXT, true)) { bail('invalid_file'); }

// ── REAL MIME CHECK via magic bytes ──────────────────────────
$finfo     = new finfo(FILEINFO_MIME_TYPE);
$real_mime = $finfo->file($f['tmp_name']);
if (!in_array($real_mime, OK_MIME, true)) { bail('invalid_file'); }

// ── EXTENSION ↔ MIME CONSISTENCY ─────────────────────────────
$map = [
    'image/jpeg' => ['jpg','jpeg'],
    'image/png'  => ['png'],
    'image/gif'  => ['gif'],
    'image/webp' => ['webp'],
];
if (!in_array($ext, $map[$real_mime] ?? [], true)) { bail('invalid_file'); }

// ── POLYGLOT FILE SCAN ───────────────────────────────────────
$raw = file_get_contents($f['tmp_name']);
if ($raw === false) { bail('upload_failed'); }
foreach (['<?php','<?=','<%','<script','#!/'] as $pattern) {
    if (stripos($raw, $pattern) !== false) {
        error_log("Polyglot attempt blocked for uid=$user_id");
        bail('invalid_file');
    }
}
unset($raw);

// ── GD REPROCESSING ──────────────────────────────────────────
$img = null;
switch ($real_mime) {
    case 'image/jpeg': $img = @imagecreatefromjpeg($f['tmp_name']); break;
    case 'image/png':  $img = @imagecreatefrompng($f['tmp_name']);  break;
    case 'image/gif':  $img = @imagecreatefromgif($f['tmp_name']);  break;
    case 'image/webp': $img = @imagecreatefromwebp($f['tmp_name']); break;
}
if (!$img) {
    error_log("GD failed for uid=$user_id");
    bail('upload_failed');
}

// Resize to 800x800 max — strips all metadata as side effect
$ow = imagesx($img); $oh = imagesy($img); $max = 800;
if ($ow > $max || $oh > $max) {
    $r  = min($max/$ow, $max/$oh);
    $nw = (int)($ow*$r); $nh = (int)($oh*$r);
    $rs = imagecreatetruecolor($nw, $nh);
    if ($real_mime === 'image/png') {
        imagealphablending($rs, false);
        imagesavealpha($rs, true);
    }
    imagecopyresampled($rs, $img, 0,0,0,0, $nw,$nh,$ow,$oh);
    imagedestroy($img);
    $img = $rs;
}

// ── RANDOM FILENAME ──────────────────────────────────────────
$newname = bin2hex(random_bytes(16)) . '.' . $ext;

// FIX: was is_dir() with no argument — syntax error
// Correct: pass $upload_path as the argument
if (!is_dir($upload_path)) {
    mkdir($upload_path, 0750, true);
}

// FIX: removed stray $upload_path line that was a syntax error
$dest = $upload_path . $newname;

// ── SAVE REPROCESSED IMAGE ───────────────────────────────────
$saved = false;
switch ($real_mime) {
    case 'image/jpeg': $saved = imagejpeg($img, $dest, 85); break;
    case 'image/png':
        imagesavealpha($img, true);
        $saved = imagepng($img, $dest, 6);
        break;
    case 'image/gif':  $saved = imagegif($img,  $dest);     break;
    case 'image/webp': $saved = imagewebp($img, $dest, 85); break;
}
imagedestroy($img);

if (!$saved) {
    error_log("Image save failed for uid=$user_id");
    bail('upload_failed');
}

chmod($dest, 0640);

// ── DELETE OLD IMAGE ─────────────────────────────────────────
$old_stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = :id");
$old_stmt->execute([':id' => $user_id]);
$old = $old_stmt->fetchColumn();
if ($old && $old !== 'default.png' && file_exists($upload_path . basename($old))) {
    unlink($upload_path . basename($old));
}

// ── SAVE TO DB ───────────────────────────────────────────────
// FIX: removed updated_at = NOW() — column does not exist in schema
try {
    $upd = $pdo->prepare(
        "UPDATE users SET profile_image = :img WHERE id = :id"
    );
    $upd->execute([':img' => $newname, ':id' => $user_id]);
} catch (PDOException $e) {
    error_log("Image DB error [uid=$user_id]: " . $e->getMessage());
    unlink($dest);
    bail('upload_failed');
}

$_SESSION['img_upload_attempts'] = 0;
redirect(BASE_URL . '/public/profile.php?status=image_uploaded');
?>