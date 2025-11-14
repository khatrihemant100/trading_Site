<?php
session_start();
require_once __DIR__.'/config/database.php';

// फर्म सब्मिट भएमा
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // वैधता जाँच
    if ($password !== $confirm_password) {
        $error = "पासवर्ड मेल खाँदैन!";
    } else {
        try {
            // युजर अस्तित्व जाँच
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "यो इमेल पहिले नै प्रयोग भइसकेको छ";
            } else {
                // नयाँ युजर थप्ने
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // Check if role column exists, if not, don't include it
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->execute([$username, $email, $hashed_password]);
                
                header("Location: login.php?success=1");
                exit();
            }
        } catch (PDOException $e) {
            $error = "डाटाबेस त्रुटि: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>खाता खोल्नुहोस्</title>
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
        
        .card { 
            max-width: 500px; 
            margin: 50px auto; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background-color: var(--dark-card);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            border-bottom: none;
        }
        
        .card-body {
            background-color: var(--dark-card);
            color: var(--text-primary);
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
        
        .alert-danger {
            border-color: #ef4444;
            color: #ef4444;
        }
        
        .text-center a {
            color: var(--primary);
        }
        
        .text-center a:hover {
            color: var(--primary-dark);
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
                        <a href="login.php" class="btn btn-outline-primary me-2">Sign In</a>
                        <a href="register.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header text-center">
                <h2 class="mb-0"><i class="fas fa-user-plus me-2"></i>खाता खोल्नुहोस्</h2>
            </div>
            <div class="card-body p-4">
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">प्रयोगकर्ता नाम</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">इमेल ठेगाना</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">पासवर्ड</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="6">
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">पासवर्ड पुष्टि गर्नुहोस्</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">रजिस्टर गर्नुहोस्</button>
            </form>
            
            <div class="mt-3 text-center">
                <p>पहिले नै खाता छ? <a href="login.php">लगइन गर्नुहोस्</a></p>
            </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>