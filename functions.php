<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/logger.php';

// Input Sanitization
function sanitize_input(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validation
function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_username(string $username): bool {
    // Only letters, numbers, underscores. 3–30 chars.
    return (bool) preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username);
}

function validate_password(string $password): bool {
    // Min 8 chars, at least one letter and one number
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

// CSRF Protection
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    if (isset($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['csrf_token']); // ✅ Keep this
        return true;
    }
    return false;
}

//Redirect Helper 
function redirect(string $url): void {
    header("Location: $url");
    exit();
}

// Flash Messages
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Render Flash HTML
function render_flash(): void {
    $flash = get_flash();
    if ($flash) {
        $type = $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type']);
        $msg  = htmlspecialchars($flash['message']);
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$msg}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

function search_user($username): ?array {
    // -------- INPUT VALIDATION --------
    if (!is_string($username)) {
        return null; // reject arrays / invalid types
    }

    $username = trim($username);

    if ($username === '') {
        return null; // empty input not allowed
    }

    // Optional: restrict allowed characters (recommended)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return null;
    }

    // -------- DATABASE QUERY --------
    $pdo = get_db();

    $stmt = $pdo->prepare(
        "SELECT username FROM users WHERE username LIKE ? LIMIT 10"
    );

    $stmt->execute([$username . "%"]);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -------- LOGGING (safe check) --------
    if (isset($_SESSION['username']) && is_string($_SESSION['username'])) {
        log_activity('transfer.php', $_SESSION['username']);
    }

    // -------- RETURN --------
    return !empty($result) ? $result : null;
}

function transaction(string $sender, string $receiver, int $amount, string $comment): void {
    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        if ($amount <= 0) {
            throw new Exception("Invalid amount");
        }

        if ($sender === $receiver) {
            throw new Exception("Cannot send money to yourself");
        }

        $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE username=? FOR UPDATE");
        $stmt->execute([$sender]);
        $sender_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sender_data) {
            throw new Exception("Sender does not exist");
        }

        $sender_id      = $sender_data["id"];
        $sender_balance = $sender_data["balance"];

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username=? FOR UPDATE");
        $stmt->execute([$receiver]);
        $receiver_id = $stmt->fetchColumn();

        if (!$receiver_id) {
            throw new Exception("Receiver doesnt exist");
        }

        if ($sender_balance < $amount) {
            throw new Exception("Insufficient balance");
        }

        // ✅ FIX 2: Idempotency check — block duplicate transactions within 5 seconds
        // Prevents replay attacks where attacker resends the same POST request
        $dup = $pdo->prepare("
            SELECT COUNT(*) FROM transactions
            WHERE sender_id = ?
            AND receiver_id = ?
            AND amount = ?
            AND created_at >= NOW() - INTERVAL 5 SECOND
        ");
        $dup->execute([$sender_id, $receiver_id, $amount]);
        if ($dup->fetchColumn() > 0) {
            throw new Exception("Duplicate transaction detected. Please wait a moment before trying again.");
        }

        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE username=?");
        $stmt->execute([$amount, $sender]);
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE username=?");
        $stmt->execute([$amount, $receiver]);

        $stmt = $pdo->prepare("INSERT INTO transactions(sender_id, receiver_id, amount, comment) VALUES(?,?,?,?)");
        $stmt->execute([$sender_id, $receiver_id, $amount, $comment]);

        $pdo->commit();

        $_SESSION["flash"] = [
            "type"    => "success",
            "message" => "Transaction successful"
        ];

        header("Location: dashboard.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION["flash"] = [
            "type"    => "error",
            "message" => $e->getMessage()
        ];

        header("Location: transfer.php");
        exit;
    }
}
