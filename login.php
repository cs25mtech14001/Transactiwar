<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['logged_in'])) {
    redirect(BASE_URL . '/public/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = login_user($username, $password);
        if ($result['success']) {
            redirect(BASE_URL . '/public/dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login – TransactiWar</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body { background: #0f172a; color: #e2e8f0; }
    .card { background: #1e293b; border: 1px solid #334155; }
    .form-control { background: #0f172a; color: #e2e8f0; border-color: #334155; }
    .form-control:focus { background: #0f172a; color: #fff; border-color: #6366f1; box-shadow: 0 0 0 .2rem rgba(99,102,241,.3); }
    .btn-primary { background: #6366f1; border-color: #6366f1; }
    .btn-primary:hover { background: #4f46e5; }
    a { color: #818cf8; }
    h3 { color: #ffffff; }
    .form-label { color: #ffffff; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
  <div class="card p-4 shadow" style="width:100%;max-width:420px;">
    <h3 class="text-center mb-1 fw-bold">💸 TransactiWar</h3>
    <p class="text-center text-secondary mb-4">Sign in to your account</p>

    <?php render_flash(); ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-semibold">Login</button>
    </form>

    <p class="text-center mt-3 mb-0 text-secondary">
      No account? <a href="register.php">Register here</a>
    </p>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>