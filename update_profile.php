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

// ── METHOD GUARD ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/public/profile.php');
}

// ── CONTENT-TYPE GUARD ───────────────────────────────────────
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/x-www-form-urlencoded') === false &&
    stripos($ct, 'multipart/form-data') === false) {
    redirect(BASE_URL . '/public/profile.php?error=invalid_input');
}

// ── RATE LIMITING ────────────────────────────────────────────
if (!isset($_SESSION['profile_update_attempts'])) {
    $_SESSION['profile_update_attempts'] = 0;
    $_SESSION['profile_update_window']   = time();
}
if (time() - $_SESSION['profile_update_window'] > 300) {
    $_SESSION['profile_update_attempts'] = 0;
    $_SESSION['profile_update_window']   = time();
}
$_SESSION['profile_update_attempts']++;
if ($_SESSION['profile_update_attempts'] > 10) {
    http_response_code(429);
    redirect(BASE_URL . '/public/profile.php?error=rate_limited');
}

// ── CSRF GUARD ───────────────────────────────────────────────
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . '/public/profile.php?error=csrf_error');
}

$user_id = (int) $_SESSION['user_id'];
log_activity('handlers/update_profile.php', $user['username']);

// ── HTTP PARAMETER POLLUTION DEFENSE ─────────────────────────
$raw_body  = file_get_contents('php://input');
$seen_keys = [];
foreach (explode('&', $raw_body) as $pair) {
    $key = urldecode(explode('=', $pair)[0]);
    if (in_array($key, ['email','full_name','bio','new_password','confirm_password'])) {
        if (isset($seen_keys[$key])) {
            redirect(BASE_URL . '/public/profile.php?error=invalid_input');
        }
        $seen_keys[$key] = true;
    }
}

// ── COLLECT INPUTS ───────────────────────────────────────────
$email     = sanitize_input($_POST['email']            ?? '');
$full_name = sanitize_input($_POST['full_name']        ?? '');
$bio_raw   = $_POST['bio']              ?? '';
$new_pw    = $_POST['new_password']     ?? '';
$conf_pw   = $_POST['confirm_password'] ?? '';

// ── VALIDATE EMAIL ───────────────────────────────────────────
if (empty($email) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 255) {
    redirect(BASE_URL . '/public/profile.php?error=invalid_input');
}
$chk = $pdo->prepare(
    "SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1"
);
$chk->execute([':email' => $email, ':id' => $user_id]);
if ($chk->fetch()) {
    redirect(BASE_URL . '/public/profile.php?error=email_taken');
}

// ── VALIDATE FULL NAME ───────────────────────────────────────
if (strlen($full_name) > 100 ||
    (!empty($full_name) && !preg_match("/^[\p{L}\s'\-\.]{1,100}$/u", $full_name))) {
    redirect(BASE_URL . '/public/profile.php?error=invalid_input');
}

// ── VALIDATE & SANITIZE BIO ──────────────────────────────────
// FIX: Use strip_tags(trim()) NOT strip_tags(sanitize_input())
// sanitize_input() runs htmlspecialchars() which would cause
// double encoding when esc() runs again on output.
// strip_tags() strips HTML, trim() cleans whitespace — correct approach.
$bio = strip_tags(trim($bio_raw));
if (strlen($bio) > 5000) {
    redirect(BASE_URL . '/public/profile.php?error=invalid_input');
}

// ── VALIDATE PASSWORD ────────────────────────────────────────
$hashed = null;
if (!empty($new_pw)) {
    $pw_ok = strlen($new_pw) >= 8
          && preg_match('/[A-Z]/', $new_pw)
          && preg_match('/[0-9]/', $new_pw)
          && preg_match('/[\W_]/', $new_pw)
          && $new_pw === $conf_pw;

    if (!$pw_ok) {
        $new_pw = $conf_pw = '';
        redirect(BASE_URL . '/public/profile.php?error=password_weak');
    }

    // FIX: Hash FIRST, wipe AFTER
    $hashed = password_hash($new_pw, PASSWORD_BCRYPT, ['cost' => 12]);
    $new_pw = $conf_pw = '';
}

// ── WRITE TO DATABASE ────────────────────────────────────────
// FIX 1: Column is `password` not `password_hash`
// FIX 2: No `updated_at` column in schema — removed from both queries
try {
    if ($hashed !== null) {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET email = :email, full_name = :fn, bio = :bio,
                 password = :pw
             WHERE id = :id"
        );
        $stmt->execute([
            ':email' => $email,
            ':fn'    => $full_name,
            ':bio'   => $bio,
            ':pw'    => $hashed,
            ':id'    => $user_id,
        ]);
        // Regenerate session ID after password change
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    } else {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET email = :email, full_name = :fn, bio = :bio
             WHERE id = :id"
        );
        $stmt->execute([
            ':email' => $email,
            ':fn'    => $full_name,
            ':bio'   => $bio,
            ':id'    => $user_id,
        ]);
    }
} catch (PDOException $e) {
    error_log("Profile update error [uid=$user_id]: " . $e->getMessage());
    redirect(BASE_URL . '/public/profile.php?error=invalid_input');
}

$_SESSION['profile_update_attempts'] = 0;
redirect(BASE_URL . '/public/profile.php?status=profile_updated');
?>