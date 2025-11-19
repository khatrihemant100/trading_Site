<?php
session_start();

// युजर लगइन जाँच
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__.'/../config/database.php';

// युजर डाटा फेच गर्ने
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: ../logout.php");
        exit();
    }
    
    // Fetch user's withdrawal history
    try {
        // Create withdrawals table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS account_withdrawals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                account_id INT NULL,
                withdrawal_amount DECIMAL(15,2) NOT NULL,
                currency VARCHAR(10) DEFAULT 'USD',
                platform ENUM('rise','bank','crypto','other') NOT NULL,
                platform_details VARCHAR(255) DEFAULT NULL,
                withdrawal_date DATE NOT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (account_id) REFERENCES trading_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (PDOException $e) {
        // Table might already exist, continue
    }
    
    // Fetch all withdrawals with account names
    $withdrawals_stmt = $pdo->prepare("
        SELECT w.*, a.account_name, a.account_type 
        FROM account_withdrawals w
        LEFT JOIN trading_accounts a ON w.account_id = a.id
        WHERE w.user_id = ?
        ORDER BY w.withdrawal_date DESC, w.created_at DESC
    ");
    $withdrawals_stmt->execute([$_SESSION['user_id']]);
    $withdrawals = $withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total withdrawals
    $total_withdrawals = array_sum(array_column($withdrawals, 'withdrawal_amount'));
    
    // Withdrawals by platform
    $withdrawals_by_platform = [];
    foreach ($withdrawals as $w) {
        $platform = $w['platform'];
        if (!isset($withdrawals_by_platform[$platform])) {
            $withdrawals_by_platform[$platform] = [
                'count' => 0,
                'total' => 0,
                'platform' => $platform
            ];
        }
        $withdrawals_by_platform[$platform]['count']++;
        $withdrawals_by_platform[$platform]['total'] += floatval($w['withdrawal_amount']);
    }
    
} catch (PDOException $e) {
    die("डाटाबेस त्रुटि: " . $e->getMessage());
}

$message = '';
$message_type = '';
$active_tab = $_GET['tab'] ?? 'profile';

// Upload directory बनाउने
$upload_dir = __DIR__.'/../uploads/profile_images/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Profile update गर्ने
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $username = trim($_POST['username']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        try {
            // Email uniqueness check (except current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                $message = "यो इमेल पहिले नै प्रयोग भइसकेको छ";
                $message_type = 'danger';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $stmt->execute([$username, $email, $_SESSION['user_id']]);
                
                $_SESSION['username'] = $username;
                $user['username'] = $username;
                $user['email'] = $email;
                
                $message = "प्रोफाइल सफलतापूर्वक अपडेट भयो!";
                $message_type = 'success';
                $active_tab = 'profile';
            }
        } catch (PDOException $e) {
            $message = "त्रुटि: " . $e->getMessage();
            $message_type = 'danger';
        }
    } elseif ($_POST['action'] === 'upload_image') {
        // Profile image upload गर्ने
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $message = "कृपया मात्र JPG, PNG, वा GIF फाइल अपलोड गर्नुहोस्!";
                $message_type = 'danger';
            } elseif ($file['size'] > $max_size) {
                $message = "फाइल साइज 5MB भन्दा कम हुनुपर्छ!";
                $message_type = 'danger';
            } else {
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // पुरानो image मेटाउने
                    if (!empty($user['profile_image']) && file_exists(__DIR__.'/../' . $user['profile_image'])) {
                        unlink(__DIR__.'/../' . $user['profile_image']);
                    }
                    
                    $image_path = 'uploads/profile_images/' . $new_filename;
                    $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$image_path, $_SESSION['user_id']]);
                    
                    // User data refresh गर्ने
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $_SESSION['profile_image'] = $image_path;
                    
                    $message = "प्रोफाइल फोटो सफलतापूर्वक अपलोड भयो!";
                    $message_type = 'success';
                    $active_tab = 'profile';
                } else {
                    $message = "फाइल अपलोड गर्दा त्रुटि भयो!";
                    $message_type = 'danger';
                }
            }
        } else {
            $message = "कृपया फाइल छान्नुहोस्!";
            $message_type = 'danger';
        }
    } elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password'])) {
            $message = "हालको पासवर्ड गलत छ!";
            $message_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $message = "नयाँ पासवर्ड मेल खाँदैन!";
            $message_type = 'danger';
        } elseif (strlen($new_password) < 6) {
            $message = "पासवर्ड कम्तिमा ६ अक्षरको हुनुपर्छ!";
            $message_type = 'danger';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                $message = "पासवर्ड सफलतापूर्वक परिवर्तन भयो!";
                $message_type = 'success';
                $active_tab = 'settings';
            } catch (PDOException $e) {
                $message = "त्रुटि: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>प्रोफाइल - NpLTrader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --dark: #1e293b;
        }
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: var(--dark) !important;
        }
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 20px;
        }
        .profile-header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 20px;
            position: relative;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        
        .profile-avatar-upload input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }
        
        .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px;
            text-align: center;
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 5;
            pointer-events: none;
        }
        
        .profile-avatar-upload:hover .upload-overlay {
            opacity: 1;
        }
        
        .profile-avatar-upload:hover .profile-avatar {
            transform: scale(1.05);
            transition: transform 0.3s;
        }
        .nav-pills .nav-link {
            color: #6b7280;
            border-radius: 8px;
            margin-right: 10px;
        }
        .nav-pills .nav-link.active {
            background-color: var(--primary);
            color: white;
        }
        .info-item {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .info-value {
            color: #1e293b;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-chart-line me-2"></i>NpLTrader
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="fas fa-home me-1"></i>ड्यासबोर्ड
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../course/course.php">
                            <i class="fas fa-book me-1"></i>कोर्सहरू
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user me-1"></i>प्रोफाइल
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($user['username']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../dashboard.php">ड्यासबोर्ड</a></li>
                            <li><a class="dropdown-item" href="profile.php">प्रोफाइल</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php">लगआउट</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Sidebar -->
            <div class="col-md-4 mb-4">
                <div class="profile-card">
                    <div class="profile-header">
                        <form method="POST" enctype="multipart/form-data" class="profile-avatar-upload" id="profileImageForm">
                            <input type="hidden" name="action" value="upload_image">
                            <div class="profile-avatar">
                                <?php if (!empty($user['profile_image']) && file_exists(__DIR__.'/../' . $user['profile_image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                                <div class="upload-overlay">
                                    <i class="fas fa-camera me-1"></i>फोटो बदल्नुहोस्
                                </div>
                            </div>
                            <input type="file" name="profile_image" id="profileImageInput" accept="image/jpeg,image/jpg,image/png,image/gif" onchange="document.getElementById('profileImageForm').submit();">
                        </form>
                        <h4 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h4>
                        <p class="text-muted mb-0">User ID: <?php echo htmlspecialchars($user['id']); ?></p>
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?> mt-2">
                            <?php echo $user['role'] === 'admin' ? 'Admin' : 'User'; ?>
                        </span>
                    </div>
                    
                    <ul class="nav nav-pills flex-column" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" 
                               data-bs-toggle="pill" href="#profile-tab">
                                <i class="fas fa-user me-2"></i>Profile Information
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" 
                               data-bs-toggle="pill" href="#settings-tab">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="col-md-8">
                <div class="tab-content">
                    <!-- Profile Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'profile' ? 'show active' : ''; ?>" id="profile-tab">
                        <div class="profile-card">
                            <h5 class="fw-bold mb-4">
                                <i class="fas fa-user-edit me-2"></i>प्रोफाइल जानकारी
                            </h5>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label class="form-label info-label">प्रयोगकर्ता नाम</label>
                                    <input type="text" class="form-control" name="username" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label info-label">इमेल ठेगाना</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label info-label">User ID</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['id']); ?>" disabled>
                                    <small class="text-muted">User ID परिवर्तन गर्न सकिँदैन</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label info-label">भूमिका (Role)</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo $user['role'] === 'admin' ? 'Admin' : 'User'; ?>" disabled>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label info-label">खाता खोलेको मिति</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?>" disabled>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>अपडेट गर्नुहोस्
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'settings' ? 'show active' : ''; ?>" id="settings-tab">
                        <div class="profile-card">
                            <h5 class="fw-bold mb-4">
                                <i class="fas fa-cog me-2"></i>Settings
                            </h5>
                            
                            <h6 class="fw-bold mb-3">Change Password</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label info-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label info-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" 
                                           minlength="6" required>
                                    <small class="text-muted">Minimum 6 characters required</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label info-label">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile image click handler
        document.addEventListener('DOMContentLoaded', function() {
            const profileAvatar = document.querySelector('.profile-avatar');
            const fileInput = document.getElementById('profileImageInput');
            
            if (profileAvatar && fileInput) {
                // Avatar मा click गर्दा file input trigger गर्ने
                profileAvatar.addEventListener('click', function(e) {
                    e.preventDefault();
                    fileInput.click();
                });
                
                // File input change भएमा form submit गर्ने
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        document.getElementById('profileImageForm').submit();
                    }
                });
            }
        });
    </script>
</body>
</html>

