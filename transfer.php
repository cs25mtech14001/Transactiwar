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
log_activity('transfer.php', $user['username']);

$csrf = generate_csrf_token();
$search_result = null;

if(isset($_GET["username"]) && $_GET["username"]!==''){
    $search_result = search_user($_GET["username"]);
}

function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$image_src   = $user['profile_image']
    ? 'serve_image.php?file=' . urlencode($user['profile_image'])
    : 'assets/default_avatar.png';

$profile_user = null;
$image_src = null;

$profile = $_GET["profile"] ?? null;

// -------- TYPE VALIDATION --------
if (!is_string($profile)) {
    $profile = null;
}

// -------- VALIDATE FORMAT --------
if ($profile !== null && preg_match('/^[a-zA-Z0-9_]{3,30}$/', $profile)) {

    $pdo = get_db();

    $stmt = $pdo->prepare(
        "SELECT username, email, bio, profile_image FROM users WHERE username=?"
    );
    $stmt->execute([$profile]);

    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

    // -------- HANDLE NOT FOUND --------
    if ($profile_user && is_array($profile_user)) {
        $image_src = $profile_user['profile_image']
            ? 'serve_image.php?file=' . urlencode($profile_user['profile_image'])
            : 'assets/default_avatar.png';
    } else {
        $profile_user = null;
        $image_src = null;
    }
}

if(isset($_POST["send_money"])){
    // ✅ FIX: Verify CSRF token before processing any transaction
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION["flash"] = [
            "type"    => "error",
            "message" => "Invalid or expired request. Please try again."
        ];
        header("Location: transfer.php");
        exit;
    }

    $receiver = $_POST["receiver"];
    $amount = (int)$_POST["amount"];
    $comment = $_POST["comment"];

    if($receiver === $_SESSION["username"]) {
        die("Cannot send money to yourself");
    }else{
        transaction($user["username"], $receiver, $amount, $comment);
    }
}


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
    p {color: #ffffff;}
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
    .dropdown-menu {
    background-color: #1f2937;
    }

    .dropdown-item {
        color: white;
    }

    .dropdown-item:hover {
        background-color: #374151;
    }

    .search-dropdown {
        max-height: 250px;      /* limit height */
        overflow-y: auto;       /* enable scrolling */
        overflow-x: hidden;
        background-color: #1f2937;
        border: 1px solid #334155;
    }

    /* optional: nice scrollbar */
    .search-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .search-dropdown::-webkit-scrollbar-thumb {
        background: #4f46e5;
        border-radius: 3px;
    }

    .search-dropdown::-webkit-scrollbar-track {
        background: #1e293b;
    }

    .position-relative .dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 1000;
    }
  </style>
</head>
<body class="p-4">
  <div class="container">
    <?php render_flash(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold">💸 TransactiWar</h2>
      <div class="d-flex justify-conten-between align-items-center mb-4">
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
        <a href="history.php" class="btn btn-outline-light btn-sm">Transaction History</a>
        <a href="profile.php" class="btn btn-outline-light btn-sm">Profile</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
      </div>
    </div>

    <form action="transfer.php" method="get" class="row g-3">
        <div class="col-md-3 position-relative">

            <input type="search"
                name="username"
                id="user-search"
                placeholder="Enter username"
                class="form-control py-3"
                autocomplete="off">

            <?php if(isset($_GET["username"])): ?>
            <div class="dropdown-menu show w-100 search-dropdown">

                <?php if(!empty($search_result)): ?>

                    <?php foreach($search_result as $search_user): ?>
                        <?php if($user["username"] === $search_user["username"]) continue; ?>

                        <a class="dropdown-item"
                        href="transfer.php?profile=<?= urlencode($search_user["username"]) ?>">

                        <?= htmlspecialchars($search_user["username"]) ?>

                        </a>

                    <?php endforeach; ?>

                <?php else: ?>

                    <span class="dropdown-item text-muted">
                        No users found
                    </span>

                <?php endif; ?>

            </div>
            <?php endif; ?>

        </div>

        <div class="col-md-3">
            <button class="btn btn-outline-light py-3">Search</button>
        </div>
    </form>

    <?php if($profile_user): ?>
        <div class="card p-4 mt-4">
            <div class="avatar-wrap">
            <img src="<?= esc($image_src) ?>"
                  alt="Avatar"
                  id="avatar-preview">
            </div>
            <h2 class="fw-bold"><?= htmlspecialchars($profile_user['username']) ?></h2>
            <p class="text-secondary mb-1">📧 <?= htmlspecialchars($profile_user['email']) ?></p>
            <?php if($profile_user["bio"]!==null): ?>
                <p class="mb-0">
                    <?= htmlspecialchars($profile_user["bio"]) ?>
                </p>
            <?php endif; ?>
        </div>
        <hr>
        <button class="btn btn-success"
            onclick="document.getElementById('send-money-form').style.display='block'">
            Send Money
        </button>
    <?php endif; ?>

    <form method="POST" id="send-money-form" class="card p-4 mt-3" style="display:none">
        <!-- ✅ FIX: CSRF token — each form load gets a unique single-use token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden"
            name="receiver"
            value="<?= htmlspecialchars($profile_user["username"] ?? "") ?>">

        <div class="mb-3">
            <label style="color:white">Amount</label>
            <input type="number" name="amount" class="form-control" required>
        </div>

        <div class="mb-3">
            <label style="color:white">Comment</label>
            <input type="text" name="comment" class="form-control">
        </div>

        <button class="btn btn-primary" name="send_money">Send</button>
    </form>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
