<?php
require_once __DIR__.'/config/database.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// टोकन वैधता जाँच
try {
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $request = $stmt->fetch();
    
    if (!$request) {
        $error = "अमान्य वा समय सकिएको लिंक";
    }
} catch (PDOException $e) {
    $error = "डाटाबेस त्रुटि: " . $e->getMessage();
}

// पासवर्ड अपडेट गर्ने
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request) {
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $pdo->beginTransaction();
        
        // पासवर्ड अपडेट
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$new_password, $request['email']]);
        
        // टोकन मेटाउने
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        
        $pdo->commit();
        $success = "पासवर्ड सफलतापूर्वक परिवर्तन भयो! <a href='login.php'>लगइन गर्नुहोस्</a>";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "त्रुटि: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>नयाँ पासवर्ड सेट गर्नुहोस्</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card mx-auto" style="max-width: 500px;">
            <div class="card-header bg-primary text-white">
                <h4>नयाँ पासवर्ड सेट गर्नुहोस्</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="password" class="form-label">नयाँ पासवर्ड</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">पासवर्ड पुष्टि गर्नुहोस्</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">पासवर्ड परिवर्तन गर्नुहोस्</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>