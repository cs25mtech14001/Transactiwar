<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Already logged in → redirect
if (!empty($_SESSION['logged_in'])) {
    redirect(BASE_URL . '/public/dashboard.php');
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $email    = sanitize_input($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';         // Don't sanitize password before hashing
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $result = register_user($username, $email, $password);
            if ($result['success']) {
                set_flash('success', $result['message']);
                redirect(BASE_URL . '/public/login.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register – TransactiWar</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body { background: #0f172a; color: #e2e8f0; }
    .card { background: #1e293b; border: 1px solid #334155; }
    .form-control { background: #0f172a; color: #e2e8f0; border-color: #334155; }
    .form-control:focus { background: #0f172a; color: #fff; border-color: #6366f1; box-shadow: 0 0 0 .2rem rgba(99,102,241,.3); }
    .btn-primary { background: #6366f1; border-color: #6366f1; }
    .btn-primary:hover { background: #4f46e5; }
    a { color: #818cf8; }
    .form-label { color: #ffffff; }
    h3 { color: #ffffff; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
  <div class="card p-4 shadow" style="width:100%;max-width:440px;">
    <h3 class="text-center mb-1 fw-bold">💸 TransactiWar</h3>
    <p class="text-center text-secondary mb-4">Create your account — get Rs. 100 free!</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               placeholder="3-30 chars, letters/numbers/_" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="Min 8 chars, letters + numbers" required>
      </div>
      <div class="mb-4">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-semibold">Register</button>
    </form>

    <p class="text-center mt-3 mb-0 text-secondary">
      Already have an account? <a href="login.php">Login</a>
    </p>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>