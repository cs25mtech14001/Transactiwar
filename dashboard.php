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

require_login();

$user = get_logged_in_user();
log_activity('dashboard.php', $user['username']);

function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$image_src   = $user['profile_image']
    ? 'serve_image.php?file=' . urlencode($user['profile_image'])
    : 'assets/default_avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - TransactiWar</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body { background: #0f172a; color: #e2e8f0; }
    .card { background: #1e293b; border: 1px solid #334155; }
    h2, h5, small { color: #ffffff; }
    .balance-badge { font-size: 2rem; color: #4ade80; font-weight: 700; }
    .btn-primary { background: #6366f1; border-color: #6366f1; }
    .btn-primary:hover { background: #4f46e5; }
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
  </style>
</head>
<body class="p-4">
  <div class="container">
    <?php render_flash(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold">💸 TransactiWar</h2>
      <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>

    <div class="card p-4 mb-3">
      <h5 class="text-secondary">Welcome back,</h5>
      <div class="avatar-wrap">
          <img src="<?= esc($image_src) ?>"
                alt="Avatar"
                id="avatar-preview">
      </div>
      <h2 class="fw-bold"><?= htmlspecialchars($user['username']) ?></h2>
      <p class="text-secondary mb-1">📧 <?= htmlspecialchars($user['email']) ?></p>
      <div class="balance-badge mt-2">₹<?= number_format($user['balance'], 2) ?></div>
      <small>Current Balance</small>
    </div>

    <div class="row g-3">
      <div class="col-md-3">
        <a href="transfer.php" class="btn btn-primary w-100 py-3">💸 Transfer Money</a>
      </div>
      <div class="col-md-3">
        <a href="history.php" class="btn btn-outline-light w-100 py-3">📋 Transaction History</a>
      </div>
      <div class="col-md-3">
        <a href="profile.php" class="btn btn-outline-light w-100 py-3">👤 My Profile</a>
      </div>
    </div>
  </div>
</body>
</html>