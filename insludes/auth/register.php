<?php
require_once __DIR__.'/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $password]);
        
        header("Location: /login.php?success=1");
        exit();
    } catch (PDOException $e) {
        die("रजिस्ट्रेशन असफल: " . $e->getMessage());
    }
}
?>