<?php
session_start();
require_once __DIR__.'/config/database.php';

// User data fetch (if logged in)
$user = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Continue without user data
    }
}

// Create contacts/feedback table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('contact', 'feedback') DEFAULT 'contact',
        status ENUM('pending', 'read', 'replied') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
} catch (PDOException $e) {
    // Table might already exist
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    $type = $_POST['type'] ?? 'contact';
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message_text)) {
        $message = 'Please fill in all fields.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        try {
            $user_id = $user ? $user['id'] : null;
            
            $stmt = $pdo->prepare("INSERT INTO contacts (user_id, name, email, subject, message, type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $email, $subject, $message_text, $type]);
            
            $message = 'Thank you! Your message has been sent successfully. We will contact you soon.';
            $message_type = 'success';
            
            // Clear form data
            $name = $email = $subject = $message_text = '';
        } catch (PDOException $e) {
            $message = 'Error: Failed to send message. Please try again.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact & Feedback - Trading Site</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-hover: #334155;
            --border-color: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        .navbar {
            background-color: var(--dark-card) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding: 0.5rem 0;
            min-height: 60px;
        }
        
        .navbar .container {
            display: flex;
            align-items: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        
        .navbar-brand {
            color: var(--primary) !important;
            font-size: 1.4rem;
            margin-right: 2rem;
            margin-left: -0.5rem;
            padding: 0.5rem 0;
            font-weight: 700;
        }
        
        .navbar-collapse {
            flex-grow: 1;
            display: flex !important;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .navbar-nav.mx-auto {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .navbar-nav .nav-item {
            margin: 0 0.4rem;
        }
        
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem !important;
            font-size: 0.95rem;
            font-weight: 500;
            white-space: nowrap;
            color: var(--text-secondary) !important;
            transition: all 0.3s;
            border-radius: 6px;
        }
        
        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
        
        .navbar .d-flex.align-items-center {
            margin-left: auto;
            margin-right: -0.5rem;
            padding-left: 1rem;
        }
        
        .navbar-toggler {
            border-color: var(--border-color);
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28148, 163, 184, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        @media (max-width: 991px) {
            .navbar .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .navbar-brand {
                margin-right: 1rem;
                margin-left: 0;
            }
            
            .navbar-nav.mx-auto {
                position: static;
                transform: none;
                margin: 0.75rem 0 !important;
                width: 100%;
            }
            
            .navbar-nav .nav-item {
                margin: 0.2rem 0;
            }
            
            .navbar-collapse {
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .navbar .d-flex.align-items-center {
                margin-left: 0;
                margin-right: 0;
                padding-left: 0;
                width: 100%;
                justify-content: flex-end;
                margin-top: 0.75rem;
            }
        }
        
        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }
        
        .contact-card {
            background: linear-gradient(135deg, var(--dark-card) 0%, var(--dark-hover) 100%);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .contact-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
        }
        
        .form-label {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            background: var(--dark-hover);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 12px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--dark-hover);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, #059669 100%);
            border: none;
            color: white;
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        
        .alert {
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            border: none;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }
        
        .contact-info {
            background: linear-gradient(135deg, var(--dark-card) 0%, var(--dark-hover) 100%);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, #059669 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .info-content h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .info-content p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 30px;
        }
        
        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 24px;
            background: transparent;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
            border-bottom-color: var(--primary);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        @media (max-width: 768px) {
            .contact-container {
                padding: 20px 15px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .contact-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-chart-line"></i> Trading Site
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">HOME</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="course/course.php">COURSE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="blogs/post1.php">BLOG</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php">CONTACT</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard/dashboard.php" class="btn btn-primary me-2">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-outline-primary">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary me-2">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="register.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="contact-container">
        <div class="page-header">
            <h1><i class="fas fa-envelope"></i> Contact & Feedback</h1>
            <p>Get in touch with us or share your feedback</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="contact-card">
                    <ul class="nav nav-tabs" id="contactTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                <i class="fas fa-phone"></i> Contact
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="feedback-tab" data-bs-toggle="tab" data-bs-target="#feedback" type="button" role="tab">
                                <i class="fas fa-comment-dots"></i> Feedback
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="contactTabContent">
                        <!-- Contact Form -->
                        <div class="tab-pane fade show active" id="contact" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="type" value="contact">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($name ?? ($user ? $user['username'] : '')); ?>" 
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($email ?? ($user ? $user['email'] : '')); ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" 
                                           value="<?php echo htmlspecialchars($subject ?? ''); ?>" 
                                           placeholder="e.g., Account related question" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" id="message" name="message" rows="6" 
                                              placeholder="Write your message here..." required><?php echo htmlspecialchars($message_text ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-paper-plane"></i> Send Message
                                </button>
                            </form>
                        </div>
                        
                        <!-- Feedback Form -->
                        <div class="tab-pane fade" id="feedback" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="type" value="feedback">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="feedback_name" class="form-label">Name *</label>
                                        <input type="text" class="form-control" id="feedback_name" name="name" 
                                               value="<?php echo htmlspecialchars($name ?? ($user ? $user['username'] : '')); ?>" 
                                               required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="feedback_email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="feedback_email" name="email" 
                                               value="<?php echo htmlspecialchars($email ?? ($user ? $user['email'] : '')); ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="feedback_subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control" id="feedback_subject" name="subject" 
                                           value="<?php echo htmlspecialchars($subject ?? ''); ?>" 
                                           placeholder="e.g., Website improvement suggestion" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="feedback_message" class="form-label">Feedback *</label>
                                    <textarea class="form-control" id="feedback_message" name="message" rows="6" 
                                              placeholder="Share your feedback, suggestions, or opinions with us..." required><?php echo htmlspecialchars($message_text ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-comment"></i> Send Feedback
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="contact-info">
                    <h4 class="mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle"></i> Contact Information
                    </h4>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h5>Email</h5>
                            <p>info@tradingsite.com</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="info-content">
                            <h5>Phone</h5>
                            <p>+977-1-XXXXXXX</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h5>Address</h5>
                            <p>Kathmandu, Nepal</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <h5>Business Hours</h5>
                            <p>Mon - Fri: 9:00 AM - 6:00 PM</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide success message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.transition = 'opacity 0.5s';
                    successAlert.style.opacity = '0';
                    setTimeout(() => successAlert.remove(), 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
