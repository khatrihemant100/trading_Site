<?php
require_once __DIR__.'/config/database.php';
session_start();

// फर्म सब्मिट भएमा
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // सेसनमा युजर डाटा सेभ गर्ने
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['profile_image'] = $user['profile_image'] ?? null;
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "गलत इमेल वा पासवर्ड!";
        }
    } catch (PDOException $e) {
        $error = "डाटाबेस त्रुटि: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>लगइन गर्नुहोस्</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-hover: #334155;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-color: #334155;
        }
        
        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-color: var(--dark-card) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary) !important;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: var(--text-secondary) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 6px;
        }
        
        .nav-link:hover {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
        
        .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
        
        .navbar-toggler {
            border-color: var(--border-color);
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28148, 163, 184, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        .login-container {
            max-width: 500px;
            margin: 3rem auto;
            padding: 2rem;
            background: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .login-title {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .form-label {
            color: var(--text-secondary);
        }
        
        .form-control {
            background-color: var(--dark-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            background-color: var(--dark-bg);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 0.5rem;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .alert {
            background-color: var(--dark-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .alert-success {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .alert-danger {
            border-color: #ef4444;
            color: #ef4444;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-secondary);
        }
        
        .footer-links a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .footer-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i>NpLTrader
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">HOME</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="blog.php">BLOG</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="course/course.php">COURSE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">ABOUT US</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">CONTACT</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">DASHBOARD</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['username'])): ?>
                        <span class="text-primary me-3" style="color: var(--primary) !important;">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                        <a href="user/profile.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-user-circle me-1"></i>Profile
                        </a>
                        <a href="logout.php" class="btn btn-primary">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-outline-primary me-2">रजिस्टर गर्नुहोस्</a>
                        <a href="login.php" class="btn btn-primary">लगइन गर्नुहोस्</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="container">
        <div class="login-container">
            <h2 class="login-title">लगइन गर्नुहोस्</h2>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">तपाईं सफलतापूर्वक रजिस्टर भएको छ! लगइन गर्नुहोस्</div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">इमेल ठेगाना</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">पासवर्ड</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">लगइन गर्नुहोस्</button>
            </form>
            
            <div class="footer-links">
                <p>खाता छैन? <a href="register.php">रजिस्टर गर्नुहोस्</a> | <a href="forgot-password.php">पासवर्ड बिर्सनुभयो?</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>