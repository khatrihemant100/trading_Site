<?php
/**
 * Admin Authentication Check
 * Include this file at the top of every admin page
 */
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=admin");
    exit();
}

require_once __DIR__.'/../config/database.php';

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin_user) {
        session_destroy();
        header("Location: ../login.php?redirect=admin");
        exit();
    }
    
    // Check if user is admin
    if ($admin_user['role'] !== 'admin') {
        header("Location: ../dashboard/dashboard.php?error=access_denied");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

