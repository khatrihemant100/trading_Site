<?php
require_once __DIR__.'/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    try {
        // युजर अस्तित्व जाँच
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // टोकन जनरेट गर्ने
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));
            
            // डाटाबेसमा सेभ गर्ने
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires]);
            
            // रिसेट लिंक पठाउने
            $reset_link = "http://localhost/trading_site/reset-password.php?token=$token";
            require_once __DIR__.'/includes/mailer.php';
            send_password_reset_email($email, $reset_link);
            
            $success = "रिसेट लिंक तपाईंको इमेलमा पठाइएको छ!";
        } else {
            $error = "यो इमेलसँग कुनै खाता छैन";
        }
    } catch (PDOException $e) {
        $error = "डाटाबेस त्रुटि: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>पासवर्ड बिर्सनुभयो</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card mx-auto" style="max-width: 500px;">
            <div class="card-header bg-primary text-white">
                <h4>पासवर्ड रिसेट गर्नुहोस्</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">इमेल ठेगाना</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">रिसेट लिंक पठाउनुहोस्</button>
                </form>
                
                <div class="mt-3 text-center">
                    <a href="login.php">लगइन पेजमा फर्कनुहोस्</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>