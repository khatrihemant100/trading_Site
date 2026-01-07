<?php
require_once __DIR__.'/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['email']); // Can be username or email
    $password = $_POST['password'];

    // Check both username and email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$login_input, $login_input]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['profile_image'] = $user['profile_image'] ?? null;
        
        header("Location: ../../dashboard/dashboard.php");
        exit();
    } else {
        header("Location: ../../login.php?error=1");
        exit();
    }
}
?>