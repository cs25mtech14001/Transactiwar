<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/logger.php';

# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025

// Register User
function register_user(string $username, string $email, string $password): array {
    // Validate
    if (!validate_username($username)) {
        return ['success' => false, 'message' => 'Username must be 3-30 chars (letters, numbers, underscores only).'];
    }
    if (!validate_email($email)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }
    if (!validate_password($password)) {
        return ['success' => false, 'message' => 'Password must be 8+ chars with at least one letter and one number.'];
    }

    $pdo = get_db();

    // Check uniqueness
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already taken.'];
    }

    // Hash password (bcrypt)
    $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Insert user with Rs. 100 initial balance
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, password, balance) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$username, $email, $hashed, INITIAL_BALANCE]);

    log_activity('register.php', $username);
    return ['success' => true, 'message' => 'Registration successful! You have been credited Rs. 100.'];
}

// Login User
function login_user(string $username, string $password): array {
    $pdo = get_db();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        // Same error for both cases — prevents username enumeration
        log_activity('login.php', $username . '[FAILED]');
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    // Session Fixation Prevention 
    session_regenerate_id(true);

    // Store minimal info in session
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['logged_in']     = true;
    $_SESSION['login_time']    = time();
    $_SESSION['ip']            = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent']    = $_SERVER['HTTP_USER_AGENT'];

    log_activity('login.php', $user['username']);
    return ['success' => true, 'message' => 'Login successful!'];
}

// Logout
function logout_user(): void {
    $username = $_SESSION['username'] ?? 'guest';
    log_activity('logout.php', $username);

    // Destroy session completely
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

//Access Control
function require_login(): void {
    if (empty($_SESSION['logged_in'])) {
        redirect(BASE_URL . '/public/login.php');
    }

    // Session Hijacking Protection: IP + UA binding
    if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR'] ||
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        logout_user();
        redirect(BASE_URL . '/public/login.php');
    }

    // Session Timeout: 30 minutes of inactivity
    if (time() - $_SESSION['login_time'] > 1800) {
        logout_user();
        set_flash('error', 'Session expired. Please log in again.');
        redirect(BASE_URL . '/public/login.php');
    }

    // Refresh activity time
    $_SESSION['login_time'] = time();
}

// Get Current User from DB
function get_logged_in_user(): ?array  {
    if (empty($_SESSION['user_id'])) return null;
    $pdo  = get_db();
    $stmt = $pdo->prepare("SELECT id, username, email, balance, profile_image, bio FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}