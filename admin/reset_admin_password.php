<?php
/**
 * Admin Password Reset Script
 * Run this once to reset admin password
 * DELETE THIS FILE after use for security!
 */

require_once __DIR__.'/../config/database.php';

// Set new password here
$new_password = 'admin123';
$admin_email = 'admin@npltrader.com'; // or use username

// Hash the password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    // Update admin password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? OR username = 'admin'");
    $stmt->execute([$hashed_password, $admin_email]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Admin password updated successfully!<br>";
        echo "New password: <strong>$new_password</strong><br>";
        echo "Password hash: $hashed_password<br><br>";
        echo "⚠️ <strong>IMPORTANT: Delete this file (reset_admin_password.php) for security!</strong>";
    } else {
        echo "❌ Admin user not found. Make sure admin user exists in database.";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

