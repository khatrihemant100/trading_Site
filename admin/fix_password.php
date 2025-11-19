<?php
/**
 * Quick Password Fix Script
 * Access via: http://localhost/Trading_Site/admin/fix_password.php
 * DELETE THIS FILE after use!
 */

require_once __DIR__.'/../config/database.php';

// Generate correct password hash for 'admin123'
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Admin Password Reset</h2>";
echo "<p>Generating password hash for: <strong>$password</strong></p>";
echo "<p>Hash: <code>$hash</code></p>";

try {
    // Update admin password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin' OR email = 'admin@npltrader.com'");
    $stmt->execute([$hash]);
    
    if ($stmt->rowCount() > 0) {
        echo "<div style='background: #10b981; color: white; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
        echo "✅ <strong>SUCCESS!</strong> Admin password has been reset.<br>";
        echo "Username: <strong>admin</strong><br>";
        echo "Password: <strong>admin123</strong><br>";
        echo "</div>";
        
        // Verify
        $verify = $pdo->prepare("SELECT username, email, role FROM users WHERE username = 'admin'");
        $verify->execute();
        $user = $verify->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<p>✅ Admin user verified:</p>";
            echo "<ul>";
            echo "<li>Username: " . htmlspecialchars($user['username']) . "</li>";
            echo "<li>Email: " . htmlspecialchars($user['email']) . "</li>";
            echo "<li>Role: " . htmlspecialchars($user['role']) . "</li>";
            echo "</ul>";
        }
        
        echo "<div style='background: #f59e0b; color: white; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
        echo "⚠️ <strong>IMPORTANT:</strong> Delete this file (fix_password.php) for security!";
        echo "</div>";
        
        echo "<p><a href='../login.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Go to Login</a></p>";
    } else {
        echo "<div style='background: #ef4444; color: white; padding: 15px; border-radius: 8px;'>";
        echo "❌ Admin user not found. Please create admin user first.";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div style='background: #ef4444; color: white; padding: 15px; border-radius: 8px;'>";
    echo "❌ Error: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

