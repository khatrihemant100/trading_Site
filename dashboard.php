<?php
session_start();

// युजर लगइन जाँच
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__.'/config/database.php';

// युजर डाटा फेच गर्ने
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: logout.php");
        exit();
    }
    
    // Trading Journal entries फेच गर्ने
    $journal_stmt = $pdo->prepare("SELECT * FROM trading_journal WHERE user_id = ? ORDER BY trade_date DESC, created_at DESC LIMIT 10");
    $journal_stmt->execute([$_SESSION['user_id']]);
    $journal_entries = $journal_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Open positions (trades without exit price)
    $open_positions_stmt = $pdo->prepare("SELECT COUNT(*) FROM trading_journal WHERE user_id = ? AND exit_price IS NULL");
    $open_positions_stmt->execute([$_SESSION['user_id']]);
    $open_positions = $open_positions_stmt->fetchColumn();
    
    // Statistics फेच गर्ने
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_trades,
            SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades,
            SUM(profit_loss) as total_profit_loss,
            AVG(profit_loss) as avg_profit_loss,
            MAX(profit_loss) as best_trade,
            MIN(profit_loss) as worst_trade
        FROM trading_journal 
        WHERE user_id = ?
    ");
    $stats_stmt->execute([$_SESSION['user_id']]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Win rate calculate गर्ने
    $win_rate = $stats['total_trades'] > 0 ? ($stats['winning_trades'] / $stats['total_trades']) * 100 : 0;
    
} catch (PDOException $e) {
    die("डाटाबेस त्रुटि: " . $e->getMessage());
}

// Trading Journal entry add/edit/delete गर्ने
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $symbol = trim($_POST['symbol']);
                $trade_type = $_POST['trade_type'];
                $quantity = floatval($_POST['quantity']);
                $entry_price = floatval($_POST['entry_price']);
                $exit_price = isset($_POST['exit_price']) ? floatval($_POST['exit_price']) : null;
                $trade_date = $_POST['trade_date'];
                $notes = trim($_POST['notes'] ?? '');
                
                // Profit/Loss calculate गर्ने
                $profit_loss = null;
                if ($exit_price !== null && $exit_price > 0) {
                    if ($trade_type === 'buy') {
                        $profit_loss = ($exit_price - $entry_price) * $quantity;
                    } else {
                        $profit_loss = ($entry_price - $exit_price) * $quantity;
                    }
                }
                
                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO trading_journal 
                        (user_id, symbol, trade_type, quantity, entry_price, exit_price, trade_date, profit_loss, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $symbol, $trade_type, $quantity, $entry_price, $exit_price, $trade_date, $profit_loss, $notes]);
                    $message = "ट्रेड सफलतापूर्वक थपियो!";
                    $message_type = 'success';
                } else {
                    $trade_id = intval($_POST['trade_id']);
                    $stmt = $pdo->prepare("
                        UPDATE trading_journal 
                        SET symbol = ?, trade_type = ?, quantity = ?, entry_price = ?, exit_price = ?, 
                            trade_date = ?, profit_loss = ?, notes = ?
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$symbol, $trade_type, $quantity, $entry_price, $exit_price, $trade_date, $profit_loss, $notes, $trade_id, $_SESSION['user_id']]);
                    $message = "ट्रेड सफलतापूर्वक अपडेट भयो!";
                    $message_type = 'success';
                }
                
                // Refresh data
                header("Location: dashboard.php");
                exit();
            } elseif ($_POST['action'] === 'delete') {
                $trade_id = intval($_POST['trade_id']);
                $stmt = $pdo->prepare("DELETE FROM trading_journal WHERE id = ? AND user_id = ?");
                $stmt->execute([$trade_id, $_SESSION['user_id']]);
                $message = "ट्रेड सफलतापूर्वक मेटाइयो!";
                $message_type = 'success';
                header("Location: dashboard.php");
                exit();
            }
        } catch (PDOException $e) {
            $message = "त्रुटि: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Edit के लागि trade data फेच गर्ने
$edit_trade = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $pdo->prepare("SELECT * FROM trading_journal WHERE id = ? AND user_id = ?");
    $edit_stmt->execute([$edit_id, $_SESSION['user_id']]);
    $edit_trade = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Dashboard - NpLTrader</title>
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
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background-color: var(--dark-card);
            border-right: 1px solid var(--border-color);
            padding: 20px;
            z-index: 1000;
            transition: transform 0.3s;
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
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
        }
        
        .sidebar-close:hover {
            color: var(--text-primary);
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
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background-color: var(--dark-hover);
            color: var(--text-primary) !important;
        }
        
        .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(255, 255, 255, 0.3);
        }
        
        .nav-link.active i {
            color: #ffffff !important;
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
            color: var(--text-primary);
        }
        
        .nav-link:hover i {
            color: var(--text-primary);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }
        
        /* Header */
        .dashboard-header {
            margin-bottom: 30px;
        }
        
        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .dashboard-subtitle {
            font-size: 1rem;
            color: var(--text-secondary);
        }
        
        /* Summary Cards */
        .summary-card {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            height: 100%;
            transition: transform 0.3s;
        }
        
        .summary-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }
        
        .summary-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .summary-card-title {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-card-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(16, 185, 129, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .summary-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .summary-card-change {
            font-size: 0.875rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Content Cards */
        .content-card {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            height: 100%;
        }
        
        .content-card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        /* Quick Action Buttons */
        .quick-action-btn {
            background-color: var(--dark-hover);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: var(--text-primary);
            display: block;
            transition: all 0.3s;
            margin-bottom: 12px;
        }
        
        .quick-action-btn:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateX(5px);
        }
        
        .quick-action-btn-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .quick-action-btn-desc {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .quick-action-btn:hover .quick-action-btn-desc {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Activity List */
        .activity-item {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background-color: var(--dark-hover);
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-symbol {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .activity-details {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .activity-pnl {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .activity-pnl.positive {
            color: var(--primary);
        }
        
        .activity-pnl.negative {
            color: #ef4444;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state-text {
            font-size: 1rem;
        }
        
        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
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
        
        .top-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: var(--dark-card);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-icon:hover {
            background-color: var(--dark-hover);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Modal Styles */
        .modal-content {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
        }
        
        .form-control, .form-select {
            background-color: var(--dark-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--dark-bg);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
        }
        
        .form-label {
            color: var(--text-secondary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
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
        /* Navbar Styles */
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
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 6px;
        }
        
        .top-navbar .nav-link:hover {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
        
        .top-navbar .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
        
        .top-navbar .navbar-toggler {
            border-color: var(--border-color);
        }
        
        .top-navbar .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28148, 163, 184, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top top-navbar">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-chart-line text-primary me-2"></i>NpLTrader
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
                            <li><a class="dropdown-item" href="user/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span>NpLTrader</span>
            </div>
            <button class="sidebar-close" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#journal" class="nav-link" onclick="showJournal(); return false;">
                    <i class="fas fa-book"></i>
                    <span>Journal</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="user/courses/enrolled.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Portfolio</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="course/course.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Community</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="user/profile.php" class="nav-link">
                    <i class="fas fa-heart"></i>
                    <span>Mindset</span>
                </a>
            </li>
        </ul>
        
        <div style="position: absolute; bottom: 20px; left: 20px; right: 20px;">
            <div class="user-info">
                <div class="user-avatar">
                    <?php 
                    $profile_image = $user['profile_image'] ?? null;
                    if (!empty($profile_image) && file_exists($profile_image)): 
                    ?>
                        <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="user-id">ID: <?php echo htmlspecialchars($user['id']); ?></div>
                </div>
            </div>
            <a href="logout.php" class="nav-link mt-3" style="justify-content: center; color: var(--text-primary);">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="btn-icon d-md-none" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="ms-auto top-actions">
                <a href="user/profile.php" class="btn-icon" title="Profile">
                    <i class="fas fa-user"></i>
                </a>
                <a href="course/course.php" class="btn-icon" title="Courses">
                    <i class="fas fa-book"></i>
                </a>
                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin/dashboard.php" class="btn-icon" title="Admin Panel">
                    <i class="fas fa-shield-alt"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">Trading Dashboard</h1>
            <p class="dashboard-subtitle">Track your performance and manage your trading journey.</p>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Total Trades</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <div class="summary-card-value"><?php echo $stats['total_trades'] ?? 0; ?></div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Open Positions</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                    </div>
                    <div class="summary-card-value"><?php echo $open_positions; ?></div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Total P/L</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="summary-card-value" style="color: <?php echo ($stats['total_profit_loss'] ?? 0) >= 0 ? 'var(--primary)' : '#ef4444'; ?>">
                        रु <?php echo number_format($stats['total_profit_loss'] ?? 0, 2); ?>
                    </div>
                    <?php if ($stats['total_profit_loss'] > 0): ?>
                    <div class="summary-card-change">
                        <i class="fas fa-arrow-up"></i>
                        <span>from last period</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Journal Entries</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="summary-card-value"><?php echo count($journal_entries); ?></div>
                </div>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="row g-4">
            <!-- Recent Activity -->
            <div class="col-lg-8">
                <div class="content-card">
                    <h3 class="content-card-title">Recent Activity</h3>
                    
                    <?php if (empty($journal_entries)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <p class="empty-state-text">Start adding trades to see your activity here.</p>
                        </div>
                    <?php else: ?>
                        <div>
                            <?php foreach (array_slice($journal_entries, 0, 5) as $entry): ?>
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <div class="activity-symbol">
                                            <?php echo htmlspecialchars($entry['symbol']); ?> - 
                                            <?php echo $entry['trade_type'] === 'buy' ? 'Buy' : 'Sell'; ?>
                                        </div>
                                        <div class="activity-details">
                                            <?php echo date('M d, Y', strtotime($entry['trade_date'])); ?> • 
                                            Qty: <?php echo number_format($entry['quantity'], 2); ?> • 
                                            Entry: रु <?php echo number_format($entry['entry_price'], 2); ?>
                                            <?php if ($entry['exit_price']): ?>
                                                • Exit: रु <?php echo number_format($entry['exit_price'], 2); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($entry['profit_loss'] !== null): ?>
                                        <div class="activity-pnl <?php echo $entry['profit_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $entry['profit_loss'] >= 0 ? '+' : ''; ?>रु <?php echo number_format($entry['profit_loss'], 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="activity-pnl" style="color: var(--text-secondary);">
                                            Open
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="content-card">
                    <h3 class="content-card-title">Quick Actions</h3>
                    
                    <a href="#journal" class="quick-action-btn" onclick="showJournal(); return false;">
                        <div class="quick-action-btn-title">
                            <i class="fas fa-plus-circle me-2"></i>Add Journal Entry
                        </div>
                        <div class="quick-action-btn-desc">Record your trading thoughts and analysis.</div>
                    </a>
                    
                    <a href="#journal" class="quick-action-btn" onclick="showJournal(); return false;">
                        <div class="quick-action-btn-title">
                            <i class="fas fa-chart-line me-2"></i>Log New Trade
                        </div>
                        <div class="quick-action-btn-desc">Add a new trade to your portfolio.</div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Journal Section (Hidden by default, shown when clicking Quick Actions) -->
        <div id="journalSection" style="display: none;" class="mt-4">
            <div class="row g-4">
                <!-- Trading Journal Form -->
                <div class="col-lg-4">
                    <div class="content-card">
                        <h5 class="content-card-title">
                            <?php echo $edit_trade ? 'Edit Trade' : 'Add New Trade'; ?>
                        </h5>
                        
                        <form method="POST" id="tradeForm">
                            <input type="hidden" name="action" value="<?php echo $edit_trade ? 'edit' : 'add'; ?>">
                            <?php if ($edit_trade): ?>
                                <input type="hidden" name="trade_id" value="<?php echo $edit_trade['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Stock Symbol</label>
                                <input type="text" class="form-control" name="symbol" 
                                       value="<?php echo htmlspecialchars($edit_trade['symbol'] ?? ''); ?>" 
                                       placeholder="e.g., NTC, NBL" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Trade Type</label>
                                <select class="form-select" name="trade_type" required>
                                    <option value="buy" <?php echo (isset($edit_trade['trade_type']) && $edit_trade['trade_type'] === 'buy') ? 'selected' : ''; ?>>Buy</option>
                                    <option value="sell" <?php echo (isset($edit_trade['trade_type']) && $edit_trade['trade_type'] === 'sell') ? 'selected' : ''; ?>>Sell</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control" name="quantity" 
                                           value="<?php echo $edit_trade['quantity'] ?? ''; ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Trade Date</label>
                                    <input type="date" class="form-control" name="trade_date" 
                                           value="<?php echo $edit_trade['trade_date'] ?? date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Entry Price (रु)</label>
                                    <input type="number" class="form-control" name="entry_price" 
                                           value="<?php echo $edit_trade['entry_price'] ?? ''; ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Exit Price (रु) <small class="text-muted">(Optional)</small></label>
                                    <input type="number" class="form-control" name="exit_price" 
                                           value="<?php echo $edit_trade['exit_price'] ?? ''; ?>" 
                                           step="0.01" min="0">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Trading notes and analysis..."><?php echo htmlspecialchars($edit_trade['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $edit_trade ? 'Update Trade' : 'Add Trade'; ?>
                                </button>
                                <?php if ($edit_trade): ?>
                                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Trading Journal List -->
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="content-card-title mb-0">Trading Journal</h5>
                        </div>
                        
                        <?php if (empty($journal_entries)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <p class="empty-state-text">No trades recorded yet. Add your first trade above.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Symbol</th>
                                            <th>Type</th>
                                            <th>Quantity</th>
                                            <th>Entry</th>
                                            <th>Exit</th>
                                            <th>P/L</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($journal_entries as $entry): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d', strtotime($entry['trade_date'])); ?></td>
                                                <td><strong><?php echo htmlspecialchars($entry['symbol']); ?></strong></td>
                                                <td>
                                                    <span class="badge <?php echo $entry['trade_type'] === 'buy' ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $entry['trade_type'] === 'buy' ? 'Buy' : 'Sell'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($entry['quantity'], 2); ?></td>
                                                <td>रु <?php echo number_format($entry['entry_price'], 2); ?></td>
                                                <td>
                                                    <?php echo $entry['exit_price'] ? 'रु ' . number_format($entry['exit_price'], 2) : '<span class="text-muted">-</span>'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($entry['profit_loss'] !== null): ?>
                                                        <span style="color: <?php echo $entry['profit_loss'] >= 0 ? 'var(--primary)' : '#ef4444'; ?>; font-weight: 600;">
                                                            <?php echo $entry['profit_loss'] >= 0 ? '+' : ''; ?>रु <?php echo number_format($entry['profit_loss'], 2); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="dashboard.php?edit=<?php echo $entry['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to delete this trade?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="trade_id" value="<?php echo $entry['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php if (!empty($entry['notes'])): ?>
                                                <tr>
                                                    <td colspan="8" class="text-muted small" style="padding-left: 40px;">
                                                        <i class="fas fa-sticky-note me-1"></i>
                                                        <?php echo htmlspecialchars($entry['notes']); ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }
        
        function showJournal() {
            window.location.href = 'forex-journal.php';
        }
        
        // Auto-calculate profit/loss when exit price is entered
        document.querySelector('input[name="exit_price"]')?.addEventListener('input', function() {
            const entryPrice = parseFloat(document.querySelector('input[name="entry_price"]').value) || 0;
            const exitPrice = parseFloat(this.value) || 0;
            const quantity = parseFloat(document.querySelector('input[name="quantity"]').value) || 0;
            const tradeType = document.querySelector('select[name="trade_type"]').value;
            
            if (entryPrice > 0 && exitPrice > 0 && quantity > 0) {
                let profitLoss;
                if (tradeType === 'buy') {
                    profitLoss = (exitPrice - entryPrice) * quantity;
                } else {
                    profitLoss = (entryPrice - exitPrice) * quantity;
                }
                
                console.log('Estimated P/L: रु ' + profitLoss.toFixed(2));
            }
        });
        
        // Show journal section if editing
        <?php if ($edit_trade): ?>
        showJournal();
        <?php endif; ?>
    </script>
</body>
</html>
