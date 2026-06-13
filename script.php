<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = get_db();

$file = fopen(__DIR__ . '/Phase2.csv', 'r');

// skip header
fgetcsv($file);

while (($row = fgetcsv($file)) !== false) {
    [$username, $email,$Name, $password] = $row;

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, email, password,full_name)
             VALUES (?,?, ?, ?)"
        );
        $stmt->execute([$username, $email, $hashed, $Name]);

        echo "Inserted: $username\n";

    } catch (Exception $e) {
        echo "Skipped: $username\n";
    }
}

fclose($file);
