<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

// Uses your team's existing session guard
require_login();

$pdo  = get_db();
$user = get_logged_in_user();
log_activity('profile.php', $user['username']);

// ── SECURITY HEADERS ─────────────────────────────────────────
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header_remove("X-Powered-By");

// ── CSRF TOKEN (uses your team's existing helper) ─────────────
$csrf_token = generate_csrf_token();

// ── FETCH FULL PROFILE FROM DB ───────────────────────────────
// get_logged_in_user() may not return bio/profile_image,
// so we do a fresh fetch for the full row
$stmt = $pdo->prepare(
    "SELECT username, email, full_name, bio, profile_image, created_at
     FROM users WHERE id = :id LIMIT 1"
);
$stmt->execute([':id' => (int) $_SESSION['user_id']]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    // Shouldn't happen — guard anyway
    session_destroy();
    redirect(BASE_URL . '/public/login.php');
}

// ── STATUS MESSAGES from handler redirects ───────────────────
// Whitelist — never echo raw GET values
$status_map = [
    'profile_updated' => ['type' => 'success', 'msg' => 'Profile updated successfully.'],
    'image_uploaded'  => ['type' => 'success', 'msg' => 'Profile photo updated successfully.'],
];
$error_map = [
    'invalid_input'  => ['type' => 'error', 'msg' => 'Some fields had invalid input. Please try again.'],
    'email_taken'    => ['type' => 'error', 'msg' => 'That email address is already in use.'],
    'password_weak'  => ['type' => 'error', 'msg' => 'Password must be 8+ chars with uppercase, number and special character.'],
    'upload_failed'  => ['type' => 'error', 'msg' => 'Image upload failed. Please try again.'],
    'invalid_file'   => ['type' => 'error', 'msg' => 'Invalid file. Only JPG, PNG, GIF, WEBP under 2MB allowed.'],
    'file_too_large' => ['type' => 'error', 'msg' => 'Image must be under 2MB.'],
    'csrf_error'     => ['type' => 'error', 'msg' => 'Session expired. Please try again.'],
];

$alert = null;
$s = $_GET['status'] ?? '';
$e = $_GET['error']  ?? '';
if (isset($status_map[$s])) $alert = $status_map[$s];
if (isset($error_map[$e]))  $alert = $error_map[$e];

// ── HELPERS ───────────────────────────────────────────────────
function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$image_src   = $profile['profile_image']
    ? 'serve_image.php?file=' . urlencode($profile['profile_image'])
    : 'assets/default_avatar.png';
$member_since = date('F Y', strtotime($profile['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | TransactiWar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:      #0f1722;
            --surface: #151e2d;
            --border:  #1e2d42;
            --accent:  #00e5ff;
            --accent2: #ff4b6e;
            --text:    #e2e8f0;
            --muted:   #64748b;
            --success: #00c896;
            --error:   #ff4b6e;
            --mono:    'Space Mono', monospace;
            --sans:    'Syne', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
            min-height: 100vh;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.9rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar .logo {
            font-family: var(--mono);
            color: var(--accent);
            font-size: 0.95rem;
            letter-spacing: 0.1em;
            text-decoration: none;
        }
        .topbar nav a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.82rem;
            margin-left: 1.4rem;
            transition: color 0.2s;
            font-family: var(--mono);
        }
        .topbar nav a:hover  { color: var(--text); }
        .topbar nav a.active { color: var(--accent); }

        /* ── LAYOUT ── */
        .page { max-width: 960px; margin: 0 auto; padding: 2.5rem 1.2rem; }

        .page-title {
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.3rem;
        }
        .page-title span { color: var(--accent); }
        .page-sub {
            font-family: var(--mono);
            font-size: 0.78rem;
            color: var(--muted);
            margin-bottom: 2rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        /* ── CARD ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
        }
        .card-label {
            font-family: var(--mono);
            font-size: 0.65rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--muted);
            padding-bottom: 0.75rem;
            margin-bottom: 1.2rem;
            border-bottom: 1px solid var(--border);
        }

        /* ── AVATAR CARD ── */
        .avatar-card { text-align: center; }
        .avatar-wrap {
            position: relative;
            width: 110px; height: 110px;
            margin: 0 auto 0.9rem;
            cursor: pointer;
        }
        .avatar-wrap img {
            width: 110px; height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
        }
        .avatar-overlay {
            position: absolute; inset: 0;
            border-radius: 50%;
            background: rgba(0,0,0,0.6);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s;
            font-family: var(--mono); font-size: 0.65rem;
            color: var(--accent); letter-spacing: 0.1em;
        }
        .avatar-wrap:hover .avatar-overlay { opacity: 1; }

        .uname {
            font-family: var(--mono);
            color: var(--accent);
            font-size: 0.88rem;
            margin-bottom: 0.2rem;
        }
        .since {
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--muted);
        }

        /* Image upload reveal */
        #img-form { display: none; margin-top: 1rem; }
        #img-form.show { display: block; }
        #profile_image { display: none; }
        .pick-btn {
            display: block; width: 100%;
            padding: 0.45rem 0;
            background: transparent;
            border: 1px dashed var(--border);
            border-radius: 6px;
            color: var(--muted);
            font-family: var(--mono);
            font-size: 0.68rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .pick-btn:hover { border-color: var(--accent); color: var(--accent); }
        #file-name {
            font-family: var(--mono); font-size: 0.62rem;
            color: var(--muted); text-align: center;
            margin-top: 0.35rem; word-break: break-all;
        }
        .upload-submit {
            width: 100%; margin-top: 0.5rem;
            padding: 0.45rem;
            background: var(--accent); color: #000;
            border: none; border-radius: 6px;
            font-family: var(--mono); font-size: 0.68rem;
            font-weight: 700; cursor: pointer; letter-spacing: 0.05em;
            transition: opacity 0.2s;
        }
        .upload-submit:hover { opacity: 0.85; }

        /* ── FORM FIELDS ── */
        .f-group { margin-bottom: 1.1rem; }
        .f-label {
            display: block;
            font-family: var(--mono);
            font-size: 0.65rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.35rem;
        }
        .f-input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.6rem 0.8rem;
            color: var(--text);
            font-family: var(--sans);
            font-size: 0.88rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .f-input:focus  { border-color: var(--accent); }
        .f-input[readonly] { color: var(--muted); cursor: not-allowed; }
        textarea.f-input { resize: vertical; min-height: 90px; }
        .f-hint {
            font-family: var(--mono); font-size: 0.62rem;
            color: var(--muted); margin-top: 0.25rem;
        }

        fieldset.f-set {
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.1rem;
        }
        fieldset.f-set legend {
            font-family: var(--mono); font-size: 0.65rem;
            letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--muted); padding: 0 0.5rem;
        }

        /* ── STRENGTH METER ── */
        .str-bar { height: 3px; background: var(--border); border-radius: 2px; margin-top: 0.35rem; }
        .str-fill { height: 100%; width: 0; border-radius: 2px; transition: width 0.3s, background 0.3s; }
        .str-text { font-family: var(--mono); font-size: 0.62rem; color: var(--muted); margin-top: 0.2rem; }

        /* ── CHAR COUNT ── */
        .char-ct {
            font-family: var(--mono); font-size: 0.62rem;
            color: var(--muted); text-align: right; margin-top: 0.2rem;
        }
        .char-ct.warn { color: var(--accent2); }

        /* ── SAVE BUTTON ── */
        .btn-save {
            padding: 0.65rem 1.4rem;
            background: var(--accent); color: #000;
            border: none; border-radius: 6px;
            font-family: var(--mono); font-size: 0.78rem;
            font-weight: 700; cursor: pointer;
            letter-spacing: 0.05em; transition: opacity 0.2s;
        }
        .btn-save:hover { opacity: 0.85; }

        /* ── ALERT ── */
        .tw-alert {
            padding: 0.7rem 1rem;
            border-radius: 6px;
            font-family: var(--mono);
            font-size: 0.76rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid;
        }
        .tw-alert.success { background: rgba(0,200,150,0.08); border-color: var(--success); color: var(--success); }
        .tw-alert.error   { background: rgba(255,75,110,0.08); border-color: var(--error);   color: var(--error);   }

        @media (max-width: 680px) { .profile-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <a href="dashboard.php" class="logo">⬡ TRANSACTIWAR</a>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="transfer.php">Transfer</a>
        <a href="history.php">History</a>
        <a href="profile.php" class="active">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="page">

    <!-- HEADING -->
    <div class="page-title">My <span>Profile</span></div>
    <div class="page-sub">// manage account details and security settings</div>

    <!-- ALERT (from redirect) -->
    <?php if ($alert): ?>
        <div class="tw-alert <?= $alert['type'] === 'success' ? 'success' : 'error' ?>">
            <?= esc($alert['msg']) ?>
        </div>
    <?php endif; ?>

    <!-- FLASH (from your existing flash system) -->
    <?php render_flash(); ?>

    <div class="profile-grid">

        <!-- LEFT: AVATAR + IMAGE UPLOAD -->
        <div>
            <div class="card avatar-card">
                <div class="card-label">Identity</div>

                <div class="avatar-wrap" id="avatar-trigger" title="Click to change photo">
                    <img src="<?= esc($image_src) ?>"
                         alt="Avatar"
                         id="avatar-preview">
                    <div class="avatar-overlay">CHANGE</div>
                </div>

                <div class="uname">@<?= esc($profile['username']) ?></div>
                <div class="since">Member since <?= esc($member_since) ?></div>

                <!-- Image upload (hidden until avatar clicked) -->
                <form id="img-form"
                      method="POST"
                      action="handlers/upload_profile_image.php"
                      enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token"
                           value="<?= esc($csrf_token) ?>">
                    <input type="file" id="profile_image" name="profile_image"
                           accept="image/jpeg,image/png,image/gif,image/webp">

                    <label class="pick-btn" for="profile_image">▲ Choose Image</label>
                    <div id="file-name">No file selected</div>
                    <button type="submit" class="upload-submit">Upload Photo</button>
                </form>
            </div>
        </div>

        <!-- RIGHT: EDIT FORM -->
        <div>
            <div class="card">
                <div class="card-label">Edit Details</div>

                <form method="POST" action="handlers/update_profile.php" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= esc($csrf_token) ?>">

                    <!-- Username: read-only, NOT in POST -->
                    <div class="f-group">
                        <label class="f-label" for="username">Username</label>
                        <input class="f-input" type="text" id="username"
                               value="<?= esc($profile['username']) ?>"
                               readonly tabindex="-1">
                        <div class="f-hint">// cannot be changed</div>
                    </div>

                    <!-- Email -->
                    <div class="f-group">
                        <label class="f-label" for="email">Email Address</label>
                        <input class="f-input" type="email" id="email" name="email"
                               maxlength="255" required
                               value="<?= esc($profile['email']) ?>">
                    </div>

                    <!-- Full Name -->
                    <div class="f-group">
                        <label class="f-label" for="full_name">Full Name</label>
                        <input class="f-input" type="text" id="full_name" name="full_name"
                               maxlength="100"
                               value="<?= esc($profile['full_name'] ?? '') ?>">
                    </div>

                    <!-- Bio + char counter -->
                    <div class="f-group">
                        <label class="f-label" for="bio">Biography</label>
                        <textarea class="f-input" id="bio" name="bio"
                                  maxlength="5000"><?= esc($profile['bio'] ?? '') ?></textarea>
                        <div class="char-ct" id="bio-ct">
                            <?= strlen($profile['bio'] ?? '') ?> / 5000
                        </div>
                    </div>

                    <!-- Password change -->
                    <fieldset class="f-set">
                        <legend>Change Password</legend>
                        <div class="f-group">
                            <label class="f-label" for="new_password">New Password</label>
                            <input class="f-input" type="password" id="new_password"
                                   name="new_password" maxlength="128"
                                   autocomplete="new-password"
                                   placeholder="Leave blank to keep current">
                            <div class="str-bar"><div class="str-fill" id="str-fill"></div></div>
                            <div class="str-text" id="str-text"></div>
                        </div>
                        <div class="f-group" style="margin-bottom:0">
                            <label class="f-label" for="confirm_password">Confirm Password</label>
                            <input class="f-input" type="password" id="confirm_password"
                                   name="confirm_password" maxlength="128"
                                   autocomplete="new-password">
                            <div class="f-hint" id="pw-match"></div>
                        </div>
                    </fieldset>

                    <button type="submit" class="btn-save">Save Changes</button>
                </form>
            </div>
        </div>

    </div><!-- /profile-grid -->
</div><!-- /page -->

<script>
// Avatar click → show upload form
document.getElementById('avatar-trigger').addEventListener('click', () => {
    document.getElementById('img-form').classList.toggle('show');
});

// File picker: UX validation + live preview
document.getElementById('profile_image').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!allowed.includes(file.type)) {
        document.getElementById('file-name').textContent = '✗ Invalid type';
        this.value = ''; return;
    }
    if (file.size > 2 * 1024 * 1024) {
        document.getElementById('file-name').textContent = '✗ Too large (max 2MB)';
        this.value = ''; return;
    }
    document.getElementById('file-name').textContent = file.name;
    const reader = new FileReader();
    reader.onload = e => document.getElementById('avatar-preview').src = e.target.result;
    reader.readAsDataURL(file);
});

// Bio char counter
const bio = document.getElementById('bio');
const bioct = document.getElementById('bio-ct');
bio.addEventListener('input', () => {
    const l = bio.value.length;
    bioct.textContent = `${l} / 5000`;
    bioct.classList.toggle('warn', l > 4500);
});

// Password strength meter
const pwIn   = document.getElementById('new_password');
const cfIn   = document.getElementById('confirm_password');
const fill   = document.getElementById('str-fill');
const strtxt = document.getElementById('str-text');
const match  = document.getElementById('pw-match');

pwIn.addEventListener('input', () => {
    const pw = pwIn.value;
    if (!pw) { fill.style.width='0'; strtxt.textContent=''; return; }
    let s = 0;
    if (pw.length >= 8)    s++;
    if (/[A-Z]/.test(pw))  s++;
    if (/[0-9]/.test(pw))  s++;
    if (/[\W_]/.test(pw))  s++;
    if (pw.length >= 16)   s++;
    const lvl = [
        {w:'15%', c:'#ff4b6e', t:'Too weak'},
        {w:'35%', c:'#ff8c42', t:'Weak'},
        {w:'60%', c:'#ffd166', t:'Fair'},
        {w:'80%', c:'#06d6a0', t:'Strong'},
        {w:'100%',c:'#00e5ff', t:'Very strong'},
    ][Math.min(s-1,4)] || {w:'15%',c:'#ff4b6e',t:'Too weak'};
    fill.style.width = lvl.w; fill.style.background = lvl.c;
    strtxt.textContent = lvl.t; strtxt.style.color = lvl.c;
    checkMatch();
});
cfIn.addEventListener('input', checkMatch);
function checkMatch() {
    if (!cfIn.value) { match.textContent=''; return; }
    if (pwIn.value === cfIn.value) {
        match.textContent = '✓ Passwords match'; match.style.color = '#00c896';
    } else {
        match.textContent = '✗ Passwords do not match'; match.style.color = '#ff4b6e';
    }
}
</script>

</body>
</html>