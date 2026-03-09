<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__.'/../config/database.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'save_routine') {
                $routine_date = $_POST['routine_date'] ?? date('Y-m-d');
                $pre_market = isset($_POST['pre_market']) ? 1 : 0;
                $trading_session = isset($_POST['trading_session']) ? 1 : 0;
                $post_market = isset($_POST['post_market']) ? 1 : 0;
                $evening = isset($_POST['evening']) ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    INSERT INTO daily_routines (user_id, routine_date, pre_market, trading_session, post_market, evening)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        pre_market = VALUES(pre_market),
                        trading_session = VALUES(trading_session),
                        post_market = VALUES(post_market),
                        evening = VALUES(evening),
                        updated_at = NOW()
                ");
                $stmt->execute([$user_id, $routine_date, $pre_market, $trading_session, $post_market, $evening]);
                $message = "Daily routine saved successfully!";
                $message_type = 'success';
            } elseif ($_POST['action'] === 'add_psychology_log') {
                $log_date = $_POST['log_date'] ?? date('Y-m-d');
                $emotion_before = $_POST['emotion_before'] ?? null;
                $emotion_during = $_POST['emotion_during'] ?? null;
                $emotion_after = $_POST['emotion_after'] ?? null;
                $confidence_level = intval($_POST['confidence_level'] ?? 5);
                $stress_level = intval($_POST['stress_level'] ?? 5);
                $notes = $_POST['notes'] ?? null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO psychology_logs (user_id, log_date, emotion_before, emotion_during, emotion_after, confidence_level, stress_level, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $log_date, $emotion_before, $emotion_during, $emotion_after, $confidence_level, $stress_level, $notes]);
                $message = "Psychology log added successfully!";
                $message_type = 'success';
            } elseif ($_POST['action'] === 'complete_exercise') {
                $exercise_name = $_POST['exercise_name'] ?? '';
                $module_name = $_POST['module_name'] ?? '';
                $completion_date = $_POST['completion_date'] ?? date('Y-m-d');
                $notes = $_POST['exercise_notes'] ?? null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO exercise_completions (user_id, exercise_name, module_name, completion_date, notes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $exercise_name, $module_name, $completion_date, $notes]);
                $message = "Exercise marked as complete!";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get today's routine
$today = date('Y-m-d');
$routine_stmt = $pdo->prepare("SELECT * FROM daily_routines WHERE user_id = ? AND routine_date = ?");
$routine_stmt->execute([$user_id, $today]);
$today_routine = $routine_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate progress scores
// Emotional Control: Based on confidence/stress levels and emotion logs
$emotion_stmt = $pdo->prepare("
    SELECT AVG(confidence_level) as avg_confidence, AVG(stress_level) as avg_stress, COUNT(*) as log_count
    FROM psychology_logs 
    WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$emotion_stmt->execute([$user_id]);
$emotion_data = $emotion_stmt->fetch(PDO::FETCH_ASSOC);
$emotional_control_score = 0;
if ($emotion_data && $emotion_data['log_count'] > 0) {
    $avg_conf = $emotion_data['avg_confidence'] ?? 5;
    $avg_stress = $emotion_data['avg_stress'] ?? 5;
    // Higher confidence and lower stress = better emotional control
    $confidence_score = ($avg_conf / 10) * 60;
    $stress_score = ((10 - $avg_stress) / 10) * 40;
    $emotional_control_score = min(100, max(0, $confidence_score + $stress_score));
}

// Discipline: Based on routine completion
$routine_stmt = $pdo->prepare("
    SELECT 
        SUM(pre_market + trading_session + post_market + evening) as total_completed,
        COUNT(*) * 4 as total_possible
    FROM daily_routines 
    WHERE user_id = ? AND routine_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$routine_stmt->execute([$user_id]);
$routine_data = $routine_stmt->fetch(PDO::FETCH_ASSOC);
$discipline_score = 0;
if ($routine_data && $routine_data['total_possible'] > 0) {
    $discipline_score = min(100, ($routine_data['total_completed'] / $routine_data['total_possible']) * 100);
}

// Risk Management: Based on exercise completions and journal entries
$risk_stmt = $pdo->prepare("
    SELECT COUNT(*) as exercise_count
    FROM exercise_completions 
    WHERE user_id = ? AND module_name = 'Risk Management Mindset' AND completion_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$risk_stmt->execute([$user_id]);
$risk_data = $risk_stmt->fetch(PDO::FETCH_ASSOC);
$risk_management_score = min(100, ($risk_data['exercise_count'] ?? 0) * 20);

// Get recent psychology logs
$recent_logs_stmt = $pdo->prepare("
    SELECT * FROM psychology_logs 
    WHERE user_id = ? 
    ORDER BY log_date DESC, created_at DESC 
    LIMIT 10
");
$recent_logs_stmt->execute([$user_id]);
$recent_logs = $recent_logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get exercise completions
$exercise_stmt = $pdo->prepare("
    SELECT exercise_name, module_name, completion_date 
    FROM exercise_completions 
    WHERE user_id = ? 
    ORDER BY completion_date DESC
");
$exercise_stmt->execute([$user_id]);
$completed_exercises = $exercise_stmt->fetchAll(PDO::FETCH_ASSOC);
$completed_exercise_map = [];
foreach ($completed_exercises as $ex) {
    $key = $ex['module_name'] . '|' . $ex['exercise_name'];
    $completed_exercise_map[$key] = true;
}

// Psychology exercises and content
$psychology_modules = [
    [
        'title' => 'Emotional Control',
        'icon' => 'fas fa-brain',
        'exercises' => [
            'Breathing Techniques - 5-5-5 method',
            'Trading Meditation - 10 min daily',
            'Emotion Journaling'
        ],
        'description' => 'Learn to control fear and greed in trading'
    ],
    [
        'title' => 'Risk Management Mindset',
        'icon' => 'fas fa-shield-alt',
        'exercises' => [
            '1% Rule Practice',
            'Risk-Reward Visualization',
            'Position Sizing Drills'
        ],
        'description' => 'Develop disciplined risk management habits'
    ],
    [
        'title' => 'Patience & Discipline',
        'icon' => 'fas fa-hourglass-half',
        'exercises' => [
            'Waiting Exercise - No trade days',
            'Set-up Recognition Training',
            'Impulse Control Practice'
        ],
        'description' => 'Build patience for high-probability setups'
    ]
];

$daily_routines = [
    'Pre-Market: 10-min meditation + plan review',
    'Trading Session: Follow trading plan strictly',
    'Post-Market: Journaling + performance review',
    'Evening: Learning + next day preparation'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trader Psychology - NpLTrader</title>
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed !important;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background-color: var(--dark-card);
            border-right: 1px solid var(--border-color);
            padding: 20px;
            z-index: 1000;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .sidebar.closed {
            transform: translateX(-100%) !important;
        }
        
        .sidebar.show {
            transform: translateX(0) !important;
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .sidebar-close {
            background: var(--primary) !important;
            border: 2px solid var(--primary) !important;
            border-radius: 8px;
            color: white !important;
            font-size: 1.4rem;
            cursor: pointer;
            padding: 0;
            transition: all 0.3s;
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            min-width: 45px;
            min-height: 45px;
            opacity: 1 !important;
            visibility: visible !important;
            z-index: 1000;
            position: relative;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .sidebar-close i {
            display: inline-block !important;
            font-size: 1.5rem !important;
            line-height: 1 !important;
            width: auto !important;
            height: auto !important;
            color: white !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .sidebar-close .close-arrow {
            display: inline-block !important;
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
            color: white;
            margin: 0;
            padding: 0;
        }
        
        .sidebar-close i.fa-angle-left {
            display: none !important;
        }
        
        .sidebar-close.show-icon i.fa-angle-left {
            display: inline-block !important;
        }
        
        .sidebar-close.show-icon .close-arrow {
            display: none !important;
        }
        
        .sidebar-close:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateX(-3px);
        }
        
        .sidebar.closed .sidebar-close i {
            transform: rotate(180deg);
        }
        
        .sidebar-toggle-btn {
            position: fixed;
            left: 20px;
            top: 20px;
            z-index: 1001;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 10px 12px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            display: none;
        }
        
        .sidebar-toggle-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        
        .sidebar-toggle-btn.show {
            display: block;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 8px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-primary) !important;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .nav-link.dashboard-link {
            padding: 16px 20px;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .nav-link.dashboard-link i {
            font-size: 1.3rem;
            width: 24px;
        }
        
        .nav-link:not(.dashboard-link) {
            padding: 10px 14px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .nav-link:not(.dashboard-link) i {
            font-size: 1rem;
            width: 18px;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
            transform: scaleY(0);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-link:hover {
            background-color: var(--dark-hover);
            color: var(--text-primary) !important;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        
        .nav-link:hover::before {
            transform: scaleY(1);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
            color: #ffffff !important;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
            transform: translateX(0);
        }
        
        .nav-link.active::before {
            transform: scaleY(1);
            background: rgba(255, 255, 255, 0.3);
        }
        
        .nav-link.active i {
            color: #ffffff !important;
            animation: pulse 2s infinite;
        }
        
        /* Calculator Dropdown in Sidebar */
        .calculator-dropdown {
            position: relative;
        }
        
        .calculator-dropdown-btn {
            background: none !important;
            border: none !important;
            width: 100%;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: var(--text-primary) !important;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .calculator-dropdown-btn i.fa-chevron-down {
            margin-left: auto;
            transition: transform 0.3s;
        }
        
        .calculator-dropdown-btn:hover {
            background-color: var(--dark-hover) !important;
            color: var(--text-primary) !important;
        }
        
        .calculator-dropdown.active .calculator-dropdown-btn i.fa-chevron-down {
            transform: rotate(180deg);
        }
        
        .calculator-dropdown-menu {
            display: none;
            background-color: var(--dark-bg);
            border-left: 3px solid var(--primary);
            margin-left: 20px;
            margin-top: 5px;
            margin-bottom: 8px;
            border-radius: 0 8px 8px 0;
            overflow: hidden;
        }
        
        .calculator-dropdown-menu.show {
            display: block;
        }
        
        .calculator-dropdown-item {
            display: block;
            padding: 10px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .calculator-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .calculator-dropdown-item:hover {
            background-color: var(--dark-hover);
            color: var(--primary);
            padding-left: 25px;
        }
        
        .calculator-dropdown-item.active {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--primary);
            font-weight: 600;
            border-left: 3px solid var(--primary);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
            color: var(--text-primary);
            transition: all 0.3s;
        }
        
        .nav-link:hover i {
            color: var(--primary);
            transform: scale(1.2);
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            overflow: hidden;
            position: relative;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .user-id {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .top-navbar {
            background-color: var(--dark-card) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding: 1rem 0;
        }
        
        .top-navbar .navbar-brand {
            color: var(--primary) !important;
            font-weight: 700;
        }
        
        .top-navbar .nav-link {
            color: var(--text-secondary) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 16px;
            border-radius: 6px;
            position: relative;
        }
        
        .top-navbar .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary);
            transform: translateX(-50%);
            transition: width 0.3s;
        }
        
        .top-navbar .nav-link:hover {
            background-color: var(--primary) !important;
            color: #ffffff !important;
            transform: translateY(-2px);
        }
        
        .top-navbar .nav-link:hover::after {
            width: 80%;
        }
        
        .top-navbar .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .top-navbar .nav-link.active::after {
            width: 80%;
        }
        
        .top-navbar .navbar-toggler {
            border-color: var(--border-color);
        }
        
        .top-navbar .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28148, 163, 184, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .sidebar.closed {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle-btn {
                display: block !important;
            }
        }
        
        .sidebar.closed ~ .main-content {
            margin-left: 0 !important;
        }
        
        .psychology-card {
            background: var(--dark-card);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #334155;
            transition: all 0.3s ease;
        }
        
        .psychology-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .exercise-item {
            background: rgba(255,255,255,0.05);
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .progress-ring {
            width: 80px;
            height: 80px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top top-navbar">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-chart-line text-primary me-2"></i>NpLTrader
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">HOME</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../blog.php">BLOG</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../course/course.php">COURSE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../about.php">ABOUT US</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../contact.php">CONTACT</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">DASHBOARD</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php 
                    $profile_image = $user['profile_image'] ?? null;
                    ?>
                    <div class="dropdown me-3">
                        <button class="btn btn-link text-decoration-none dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" style="color: var(--primary) !important; padding: 0;">
                            <?php if (!empty($profile_image) && file_exists($profile_image)): ?>
                                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; border: 2px solid var(--primary);">
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 8px; font-weight: bold;">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-th-large me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="../user/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2">Trader Psychology Development</h1>
                    <p class="text-muted">Build the mindset of successful traders</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPsychologyModal">
                    <i class="fas fa-plus me-2"></i>Add Psychology Log
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Psychology Assessment -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="psychology-card text-center">
                        <div class="progress-ring mb-3 mx-auto position-relative" style="width: 80px; height: 80px;">
                            <svg width="80" height="80">
                                <circle cx="40" cy="40" r="35" stroke="#334155" stroke-width="8" fill="none"/>
                                <?php 
                                $emotion_percent = round($emotional_control_score);
                                $circumference = 2 * M_PI * 35;
                                $offset = $circumference - ($emotion_percent / 100) * $circumference;
                                ?>
                                <circle cx="40" cy="40" r="35" stroke="#10b981" stroke-width="8" fill="none" 
                                        stroke-dasharray="<?php echo $circumference; ?>" 
                                        stroke-dashoffset="<?php echo $offset; ?>" 
                                        stroke-linecap="round" transform="rotate(-90 40 40)"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle" style="transform: translate(-50%, -50%);">
                                <h4 class="mb-0"><?php echo $emotion_percent; ?>%</h4>
                            </div>
                        </div>
                        <h6>Emotional Control</h6>
                        <small class="text-muted">
                            <?php 
                            if ($emotion_percent >= 80) echo "Excellent";
                            elseif ($emotion_percent >= 60) echo "Good Progress";
                            elseif ($emotion_percent >= 40) echo "Needs Improvement";
                            else echo "Start Tracking";
                            ?>
                        </small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="psychology-card text-center">
                        <div class="progress-ring mb-3 mx-auto position-relative" style="width: 80px; height: 80px;">
                            <svg width="80" height="80">
                                <circle cx="40" cy="40" r="35" stroke="#334155" stroke-width="8" fill="none"/>
                                <?php 
                                $discipline_percent = round($discipline_score);
                                $circumference = 2 * M_PI * 35;
                                $offset = $circumference - ($discipline_percent / 100) * $circumference;
                                ?>
                                <circle cx="40" cy="40" r="35" stroke="#10b981" stroke-width="8" fill="none" 
                                        stroke-dasharray="<?php echo $circumference; ?>" 
                                        stroke-dashoffset="<?php echo $offset; ?>" 
                                        stroke-linecap="round" transform="rotate(-90 40 40)"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle" style="transform: translate(-50%, -50%);">
                                <h4 class="mb-0"><?php echo $discipline_percent; ?>%</h4>
                            </div>
                        </div>
                        <h6>Discipline Score</h6>
                        <small class="text-muted">
                            <?php 
                            if ($discipline_percent >= 80) echo "Excellent";
                            elseif ($discipline_percent >= 60) echo "Good Progress";
                            elseif ($discipline_percent >= 40) echo "Needs Improvement";
                            else echo "Start Tracking";
                            ?>
                        </small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="psychology-card text-center">
                        <div class="progress-ring mb-3 mx-auto position-relative" style="width: 80px; height: 80px;">
                            <svg width="80" height="80">
                                <circle cx="40" cy="40" r="35" stroke="#334155" stroke-width="8" fill="none"/>
                                <?php 
                                $risk_percent = round($risk_management_score);
                                $circumference = 2 * M_PI * 35;
                                $offset = $circumference - ($risk_percent / 100) * $circumference;
                                ?>
                                <circle cx="40" cy="40" r="35" stroke="#10b981" stroke-width="8" fill="none" 
                                        stroke-dasharray="<?php echo $circumference; ?>" 
                                        stroke-dashoffset="<?php echo $offset; ?>" 
                                        stroke-linecap="round" transform="rotate(-90 40 40)"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle" style="transform: translate(-50%, -50%);">
                                <h4 class="mb-0"><?php echo $risk_percent; ?>%</h4>
                            </div>
                        </div>
                        <h6>Risk Management</h6>
                        <small class="text-muted">
                            <?php 
                            if ($risk_percent >= 80) echo "Excellent";
                            elseif ($risk_percent >= 60) echo "Good Progress";
                            elseif ($risk_percent >= 40) echo "Needs Improvement";
                            else echo "Start Tracking";
                            ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Psychology Training Modules -->
            <div class="row">
                <?php foreach ($psychology_modules as $module): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="psychology-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary rounded-circle p-3 me-3">
                                    <i class="<?php echo $module['icon']; ?> fa-lg"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?php echo $module['title']; ?></h5>
                                    <small class="text-muted"><?php echo $module['description']; ?></small>
                                </div>
                            </div>
                            
                            <h6 class="mb-2">Daily Exercises:</h6>
                            <?php foreach ($module['exercises'] as $exercise): ?>
                                <?php 
                                $exercise_key = $module['title'] . '|' . $exercise;
                                $is_completed = isset($completed_exercise_map[$exercise_key]);
                                ?>
                                <div class="exercise-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas <?php echo $is_completed ? 'fa-check-circle text-success' : 'fa-circle'; ?> me-2"></i>
                                        <?php echo $exercise; ?>
                                    </div>
                                    <?php if (!$is_completed): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="completeExercise('<?php echo htmlspecialchars($module['title']); ?>', '<?php echo htmlspecialchars($exercise); ?>')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <button class="btn btn-primary btn-sm w-100 mt-3" onclick="viewModuleDetails('<?php echo htmlspecialchars($module['title']); ?>')">
                                <i class="fas fa-play me-2"></i>View Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Daily Routine Tracker -->
            <div class="psychology-card mt-4">
                <h5 class="mb-3">Daily Trader Routine - <?php echo date('F d, Y'); ?></h5>
                <form method="POST" id="routineForm">
                    <input type="hidden" name="action" value="save_routine">
                    <input type="hidden" name="routine_date" value="<?php echo $today; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pre_market" id="pre_market" 
                                       <?php echo ($today_routine && $today_routine['pre_market']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="pre_market">
                                    Pre-Market: 10-min meditation + plan review
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="trading_session" id="trading_session"
                                       <?php echo ($today_routine && $today_routine['trading_session']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="trading_session">
                                    Trading Session: Follow trading plan strictly
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="post_market" id="post_market"
                                       <?php echo ($today_routine && $today_routine['post_market']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="post_market">
                                    Post-Market: Journaling + performance review
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="evening" id="evening"
                                       <?php echo ($today_routine && $today_routine['evening']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="evening">
                                    Evening: Learning + next day preparation
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Save Progress
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Recent Psychology Logs -->
            <?php if (!empty($recent_logs)): ?>
            <div class="psychology-card mt-4">
                <h5 class="mb-3">Recent Psychology Logs</h5>
                <div class="table-responsive">
                    <table class="table table-dark">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Emotion Before</th>
                                <th>Emotion During</th>
                                <th>Emotion After</th>
                                <th>Confidence</th>
                                <th>Stress</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                                <td><?php echo htmlspecialchars($log['emotion_before'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['emotion_during'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['emotion_after'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $log['confidence_level'] >= 7 ? 'success' : ($log['confidence_level'] >= 4 ? 'warning' : 'danger'); ?>">
                                        <?php echo $log['confidence_level']; ?>/10
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $log['stress_level'] <= 3 ? 'success' : ($log['stress_level'] <= 6 ? 'warning' : 'danger'); ?>">
                                        <?php echo $log['stress_level']; ?>/10
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($log['notes'] ?? '', 0, 50)); ?><?php echo strlen($log['notes'] ?? '') > 50 ? '...' : ''; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Psychology Tips -->
            <div class="psychology-card mt-4">
                <h5 class="mb-3">Psychology Tips for New Traders</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Start Small</h6>
                            <small>Trade with small position sizes until you're consistently profitable</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Embrace Losses</h6>
                            <small>Losses are tuition fees in trading school - learn from them</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-success">
                            <h6><i class="fas fa-chart-line me-2"></i>Process Over Profit</h6>
                            <small>Focus on executing your plan correctly, not on making money</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-primary">
                            <h6><i class="fas fa-book me-2"></i>Keep Learning</h6>
                            <small>Markets change constantly - continuous learning is essential</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Psychology Logs -->
            <?php if (!empty($recent_logs)): ?>
            <div class="psychology-card mt-4">
                <h5 class="mb-3">Recent Psychology Logs</h5>
                <div class="table-responsive">
                    <table class="table table-dark">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Emotion Before</th>
                                <th>Emotion During</th>
                                <th>Emotion After</th>
                                <th>Confidence</th>
                                <th>Stress</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                                <td><?php echo htmlspecialchars($log['emotion_before'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['emotion_during'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['emotion_after'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $log['confidence_level'] >= 7 ? 'success' : ($log['confidence_level'] >= 4 ? 'warning' : 'danger'); ?>">
                                        <?php echo $log['confidence_level']; ?>/10
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $log['stress_level'] <= 3 ? 'success' : ($log['stress_level'] <= 6 ? 'warning' : 'danger'); ?>">
                                        <?php echo $log['stress_level']; ?>/10
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($log['notes'] ?? '', 0, 50)); ?><?php echo strlen($log['notes'] ?? '') > 50 ? '...' : ''; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Psychology Log Modal -->
    <div class="modal fade" id="addPsychologyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background-color: var(--dark-card); border: 1px solid var(--border-color);">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-bottom: 2px solid var(--primary-dark);">
                    <h5 class="modal-title" style="color: white; font-weight: 700;">
                        <i class="fas fa-brain me-2"></i>Add Psychology Log
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="background-color: var(--dark-card); color: var(--text-primary);">
                    <form method="POST" id="psychologyForm">
                        <input type="hidden" name="action" value="add_psychology_log">
                        <div class="mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Date</label>
                            <input type="date" class="form-control" name="log_date" value="<?php echo date('Y-m-d'); ?>" required style="background-color: var(--dark-hover); border: 2px solid var(--border-color); color: var(--text-primary);">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" style="color: var(--text-primary);">Emotion Before Trading</label>
                                <select class="form-select" name="emotion_before" style="background-color: var(--dark-hover); border: 2px solid var(--border-color); color: var(--text-primary);">
                                    <option value="">Select...</option>
                                    <option value="Confident">Confident</option>
                                    <option value="Anxious">Anxious</option>
                                    <option value="Calm">Calm</option>
                                    <option value="Excited">Excited</option>
                                    <option value="Fearful">Fearful</option>
                                    <option value="Greedy">Greedy</option>
                                    <option value="Neutral">Neutral</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" style="color: var(--text-primary);">Emotion During Trading</label>
                                <select class="form-select" name="emotion_during" style="background-color: var(--dark-hover); border: 2px solid var(--border-color); color: var(--text-primary);">
                                    <option value="">Select...</option>
                                    <option value="Confident">Confident</option>
                                    <option value="Anxious">Anxious</option>
                                    <option value="Calm">Calm</option>
                                    <option value="Excited">Excited</option>
                                    <option value="Fearful">Fearful</option>
                                    <option value="Greedy">Greedy</option>
                                    <option value="Neutral">Neutral</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" style="color: var(--text-primary);">Emotion After Trading</label>
                                <select class="form-select" name="emotion_after" style="background-color: var(--dark-hover); border: 2px solid var(--border-color); color: var(--text-primary);">
                                    <option value="">Select...</option>
                                    <option value="Satisfied">Satisfied</option>
                                    <option value="Frustrated">Frustrated</option>
                                    <option value="Calm">Calm</option>
                                    <option value="Regretful">Regretful</option>
                                    <option value="Proud">Proud</option>
                                    <option value="Neutral">Neutral</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="color: var(--text-primary);">Confidence Level (1-10)</label>
                                <input type="range" class="form-range" name="confidence_level" min="1" max="10" value="5" oninput="document.getElementById('confValue').textContent = this.value">
                                <div class="text-center">
                                    <span class="badge bg-primary" id="confValue">5</span>/10
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="color: var(--text-primary);">Stress Level (1-10)</label>
                                <input type="range" class="form-range" name="stress_level" min="1" max="10" value="5" oninput="document.getElementById('stressValue').textContent = this.value">
                                <div class="text-center">
                                    <span class="badge bg-danger" id="stressValue">5</span>/10
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about your psychology state..." style="background-color: var(--dark-hover); border: 2px solid var(--border-color); color: var(--text-primary);"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="background-color: var(--dark-card); border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="psychologyForm" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Log
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Complete Exercise Modal -->
    <div class="modal fade" id="completeExerciseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background-color: var(--dark-card); border: 1px solid var(--border-color);">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                    <h5 class="modal-title" style="color: white;">Complete Exercise</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="background-color: var(--dark-card); color: var(--text-primary);">
                    <form method="POST" id="exerciseForm">
                        <input type="hidden" name="action" value="complete_exercise">
                        <input type="hidden" name="module_name" id="exercise_module">
                        <input type="hidden" name="exercise_name" id="exercise_name">
                        <input type="hidden" name="completion_date" value="<?php echo date('Y-m-d'); ?>">
                        <p>Mark <strong id="exercise_display"></strong> as completed?</p>
                        <div class="mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Notes (Optional)</label>
                            <textarea class="form-control" name="exercise_notes" rows="2" style="background-color: var(--dark-hover); border: 2px solid var(--border-color); color: var(--text-primary);"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="background-color: var(--dark-card);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="exerciseForm" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Mark Complete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function completeExercise(moduleName, exerciseName) {
            document.getElementById('exercise_module').value = moduleName;
            document.getElementById('exercise_name').value = exerciseName;
            document.getElementById('exercise_display').textContent = exerciseName;
            const modal = new bootstrap.Modal(document.getElementById('completeExerciseModal'));
            modal.show();
        }
        
        function viewModuleDetails(moduleName) {
            alert('Module details for ' + moduleName + ' coming soon!');
        }
        
        // Toggle calculator dropdown
        function toggleCalculatorDropdown(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const dropdown = document.getElementById('calculatorDropdown');
            const dropdownParent = dropdown ? dropdown.closest('.calculator-dropdown') : null;
            if (dropdown && dropdownParent) {
                dropdown.classList.toggle('show');
                dropdownParent.classList.toggle('active');
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('calculatorDropdown');
            const dropdownParent = dropdown ? dropdown.closest('.calculator-dropdown') : null;
            if (dropdown && dropdownParent && !event.target.closest('.calculator-dropdown')) {
                dropdown.classList.remove('show');
                dropdownParent.classList.remove('active');
            }
        });
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const mainContent = document.querySelector('.main-content');
            
            if (!sidebar) return;
            
            const isClosed = sidebar.classList.contains('closed');
            
            if (isClosed) {
                sidebar.classList.remove('closed');
                sidebar.classList.add('show');
                sidebar.style.transform = 'translateX(0)';
                
                if (mainContent) {
                    if (window.innerWidth > 768) {
                        mainContent.style.marginLeft = '280px';
                        mainContent.style.transition = 'margin-left 0.3s ease';
                    } else {
                        mainContent.style.marginLeft = '0';
                    }
                }
                
                if (toggleBtn) {
                    toggleBtn.classList.remove('show');
                    toggleBtn.style.display = 'none';
                }
            } else {
                sidebar.classList.add('closed');
                sidebar.classList.remove('show');
                sidebar.style.transform = 'translateX(-100%)';
                
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.transition = 'margin-left 0.3s ease';
                }
                
                if (toggleBtn) {
                    toggleBtn.classList.add('show');
                    toggleBtn.style.display = 'block';
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const mainContent = document.querySelector('.main-content');
            const closeBtn = document.querySelector('.sidebar-close');
            
            if (closeBtn) {
                closeBtn.style.display = 'flex';
                closeBtn.style.visibility = 'visible';
                closeBtn.style.opacity = '1';
                
                const icon = closeBtn.querySelector('i.fa-angle-left');
                if (icon) {
                    setTimeout(function() {
                        const testEl = document.createElement('i');
                        testEl.className = 'fas fa-check';
                        document.body.appendChild(testEl);
                        const fontFamily = window.getComputedStyle(testEl, ':before').getPropertyValue('font-family');
                        document.body.removeChild(testEl);
                        
                        if (fontFamily && fontFamily.includes('Font Awesome')) {
                            closeBtn.classList.add('show-icon');
                        }
                    }, 100);
                }
            }
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('closed');
                sidebar.classList.add('show');
                sidebar.style.transform = 'translateX(0)';
                if (mainContent) {
                    mainContent.style.marginLeft = '280px';
                    mainContent.style.transition = 'margin-left 0.3s ease';
                }
                if (toggleBtn) {
                    toggleBtn.classList.remove('show');
                    toggleBtn.style.display = 'none';
                }
            } else {
                sidebar.classList.add('closed');
                sidebar.classList.remove('show');
                sidebar.style.transform = 'translateX(-100%)';
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.transition = 'margin-left 0.3s ease';
                }
                if (toggleBtn) {
                    toggleBtn.classList.add('show');
                    toggleBtn.style.display = 'block';
                }
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    if (!sidebar.classList.contains('closed')) {
                        sidebar.classList.add('show');
                        sidebar.style.transform = 'translateX(0)';
                        if (mainContent) {
                            mainContent.style.marginLeft = '280px';
                            mainContent.style.transition = 'margin-left 0.3s ease';
                        }
                    }
                    if (toggleBtn) {
                        toggleBtn.style.display = 'none';
                    }
                } else {
                    sidebar.classList.add('closed');
                    sidebar.classList.remove('show');
                    sidebar.style.transform = 'translateX(-100%)';
                    if (mainContent) {
                        mainContent.style.marginLeft = '0';
                        mainContent.style.transition = 'margin-left 0.3s ease';
                    }
                    if (toggleBtn) {
                        toggleBtn.classList.add('show');
                        toggleBtn.style.display = 'block';
                    }
                }
            });
        });
        
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnToggleBtn = toggleBtn && toggleBtn.contains(event.target);
            
            if (window.innerWidth <= 768 && !isClickInsideSidebar && !isClickOnToggleBtn && !sidebar.classList.contains('closed')) {
                sidebar.classList.add('closed');
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                    toggleBtn.title = 'Open Sidebar';
                }
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
            
            sidebarLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && (href === currentPage || href.includes(currentPage))) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>