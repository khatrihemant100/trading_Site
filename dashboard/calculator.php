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
} catch (PDOException $e) {
    die("डाटाबेस त्रुटि: " . $e->getMessage());
}

// Get selected calculator type
$calc_type = isset($_GET['type']) ? $_GET['type'] : 'lot-size';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculators - NpLTrader</title>
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
        
        /* Sidebar Styles - Same as dashboard */
        .sidebar {
            position: fixed !important;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background-color: var(--dark-card);
            border-right: 1px solid var(--border-color);
            padding: 20px;
            z-index: 1050 !important;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            overflow-x: hidden;
            transform: translateX(0);
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
            background: #10b981 !important;
            border: 2px solid #10b981 !important;
            border-radius: 8px;
            color: white !important;
            font-size: 1.4rem;
            cursor: pointer !important;
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
            z-index: 1052 !important;
            position: relative;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.5);
        }
        
        .sidebar-close:hover {
            background: #059669 !important;
            border-color: #059669 !important;
            transform: translateX(-3px);
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
        
        .sidebar-toggle-btn {
            position: fixed !important;
            left: 20px !important;
            top: 20px !important;
            z-index: 1051 !important;
            background: #10b981 !important;
            border: none !important;
            border-radius: 8px;
            color: white !important;
            font-size: 1.2rem;
            cursor: pointer !important;
            padding: 10px 12px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
            display: none !important;
        }
        
        .sidebar-toggle-btn:hover {
            background: #059669 !important;
            transform: scale(1.1);
        }
        
        .sidebar-toggle-btn.show {
            display: block !important;
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
        
        .nav-link:not(.dashboard-link) {
            padding: 10px 14px;
            font-size: 0.9rem;
            font-weight: 500;
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
        }
        
        .nav-link.active::before {
            transform: scaleY(1);
            background: rgba(255, 255, 255, 0.3);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }
        
        /* Top Navbar */
        .top-navbar {
            background-color: var(--dark-card) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding: 1rem 0;
            z-index: 999 !important;
            position: relative;
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
        
        /* Calculator Container */
        .calculator-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .calculator-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .calculator-main-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .calculator-description {
            color: var(--text-secondary);
            font-size: 1rem;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Calculator Tabs */
        .calculator-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
            flex-wrap: wrap;
        }
        
        .calculator-tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        
        .calculator-tab:hover {
            color: var(--primary);
            background-color: rgba(16, 185, 129, 0.1);
        }
        
        .calculator-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 600;
        }
        
        .calculator-tab i {
            font-size: 1.1rem;
        }
        
        /* Calculator Card */
        .calculator-card {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
        }
        
        .calculator-content {
            display: none;
        }
        
        .calculator-content.active {
            display: block;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .calculator-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        
        .calculator-subtitle {
            color: var(--text-secondary);
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        
        /* Form Styles */
        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .form-label .info-icon {
            color: var(--primary);
            cursor: help;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            background-color: var(--dark-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--dark-bg);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
            outline: none;
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }
        
        .btn-calculate {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            color: white;
            padding: 14px 40px;
            font-weight: 600;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-calculate:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        
        /* Result Box */
        .result-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        .result-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%);
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .result-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .result-value {
            color: var(--primary);
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        /* Disclaimer */
        .disclaimer-box {
            background-color: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 12px;
            padding: 20px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-top: 30px;
        }
        
        .disclaimer-box strong {
            color: var(--text-primary);
        }
        
        /* Responsive */
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
                padding: 20px;
            }
            
            .sidebar-toggle-btn {
                display: block !important;
            }
            
            .calculator-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            
            .calculator-tab {
                white-space: nowrap;
                font-size: 0.85rem;
                padding: 10px 16px;
            }
            
            .calculator-card {
                padding: 25px;
            }
            
            .result-container {
                grid-template-columns: 1fr;
            }
            
            .calculator-main-title {
                font-size: 2rem;
            }
        }
        
        .sidebar.closed ~ .main-content {
            margin-left: 0;
        }
        
        .sidebar.show ~ .main-content {
            margin-left: 280px;
        }
        
        @media (max-width: 768px) {
            .sidebar.show ~ .main-content {
                margin-left: 0;
            }
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

    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Open Sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span>NpLTrader</span>
            </div>
            <button class="sidebar-close" onclick="toggleSidebar()" title="Close Sidebar">
                <i class="fas fa-angle-left"></i>
                <span class="close-arrow">←</span>
            </button>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link dashboard-link">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="journal.php" class="nav-link">
                    <i class="fas fa-book"></i>
                    <span>Journal</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="portfolio.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Portfolio</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="Community.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Community</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="Mindset.php" class="nav-link">
                    <i class="fas fa-heart"></i>
                    <span>Mindset</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="calculator.php" class="nav-link active">
                    <i class="fas fa-calculator"></i>
                    <span>Calculators</span>
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
            <a href="../logout.php" class="nav-link mt-3" style="justify-content: center; color: var(--text-primary);">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="calculator-container">
            <!-- Header -->
            <div class="calculator-header">
                <h1 class="calculator-main-title">Lot Size Calculator</h1>
                <p class="calculator-description">
                    Our FundedNext Lot Size Calculator allows you to calculate the optimal lot size for your trades. 
                    This tool helps you manage risk effectively by determining position size that aligns with your trading plan for your trades.
                </p>
            </div>

            <!-- Calculator Tabs -->
            <div class="calculator-tabs">
                <button class="calculator-tab <?php echo $calc_type === 'margin' ? 'active' : ''; ?>" onclick="switchCalculator('margin')">
                    <i class="fas fa-chart-line"></i>
                    <span>Margin Calculator</span>
                </button>
                <button class="calculator-tab <?php echo $calc_type === 'profit-loss' ? 'active' : ''; ?>" onclick="switchCalculator('profit-loss')">
                    <i class="fas fa-clock"></i>
                    <span>Profit/Loss Calculator</span>
                </button>
                <button class="calculator-tab <?php echo $calc_type === 'lot-size' ? 'active' : ''; ?>" onclick="switchCalculator('lot-size')">
                    <i class="fas fa-chart-bar"></i>
                    <span>Lot Size Calculator</span>
                </button>
                <button class="calculator-tab <?php echo $calc_type === 'swap' ? 'active' : ''; ?>" onclick="switchCalculator('swap')">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Swap Calculator</span>
                </button>
            </div>

            <!-- Calculator Card -->
            <div class="calculator-card">
                <!-- Margin Calculator -->
                <div class="calculator-content <?php echo $calc_type === 'margin' ? 'active' : ''; ?>" id="margin-calc">
                    <h2 class="calculator-title">Margin Calculator</h2>
                    <p class="calculator-subtitle">Calculate the required margin for your trading positions</p>
                    
                    <form id="marginCalculator" onsubmit="calculateMargin(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Instrument
                                    <i class="fas fa-info-circle info-icon" title="Select the trading instrument"></i>
                                </label>
                                <select class="form-select" id="marginInstrument" required>
                                    <option value="">Select Instrument</option>
                                    <option value="EURUSD">EUR/USD</option>
                                    <option value="GBPUSD">GBP/USD</option>
                                    <option value="USDJPY">USD/JPY</option>
                                    <option value="AUDUSD">AUD/USD</option>
                                    <option value="USDCHF">USD/CHF</option>
                                    <option value="NZDUSD">NZD/USD</option>
                                    <option value="EURJPY">EUR/JPY</option>
                                    <option value="GBPJPY">GBP/JPY</option>
                                    <option value="XAUUSD">XAU/USD (Gold)</option>
                                    <option value="BTCUSD">BTC/USD</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Account Balance
                                    <i class="fas fa-info-circle info-icon" title="Your account balance"></i>
                                </label>
                                <input type="number" class="form-control" id="marginBalance" step="0.01" min="0" placeholder="Account Balance" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Lot Size
                                    <i class="fas fa-info-circle info-icon" title="Position size in lots"></i>
                                </label>
                                <input type="number" class="form-control" id="marginLotSize" step="0.01" min="0" placeholder="Lot Size" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Leverage
                                    <i class="fas fa-info-circle info-icon" title="Trading leverage (e.g., 100, 200, 500)"></i>
                                </label>
                                <input type="number" class="form-control" id="marginLeverage" step="1" min="1" placeholder="Leverage (e.g., 100)" value="100" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-calculate">
                            <i class="fas fa-calculator me-2"></i>Calculate
                        </button>
                    </form>
                    
                    <div class="result-container" id="marginResult" style="display: none;">
                        <div class="result-box">
                            <div class="result-label">Required Margin</div>
                            <div class="result-value" id="marginValue">$0.00</div>
                        </div>
                        <div class="result-box">
                            <div class="result-label">Free Margin</div>
                            <div class="result-value" id="freeMargin">$0.00</div>
                        </div>
                    </div>
                </div>

                <!-- Profit/Loss Calculator -->
                <div class="calculator-content <?php echo $calc_type === 'profit-loss' ? 'active' : ''; ?>" id="profit-loss-calc">
                    <h2 class="calculator-title">Profit/Loss Calculator</h2>
                    <p class="calculator-subtitle">Calculate potential profit or loss for your trades</p>
                    
                    <form id="profitLossCalculator" onsubmit="calculateProfitLoss(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Instrument
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <select class="form-select" id="plInstrument" required>
                                    <option value="">Select Instrument</option>
                                    <option value="EURUSD">EUR/USD</option>
                                    <option value="GBPUSD">GBP/USD</option>
                                    <option value="USDJPY">USD/JPY</option>
                                    <option value="AUDUSD">AUD/USD</option>
                                    <option value="USDCHF">USD/CHF</option>
                                    <option value="NZDUSD">NZD/USD</option>
                                    <option value="EURJPY">EUR/JPY</option>
                                    <option value="GBPJPY">GBP/JPY</option>
                                    <option value="XAUUSD">XAU/USD (Gold)</option>
                                    <option value="BTCUSD">BTC/USD</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Trade Type
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <select class="form-select" id="plTradeType" required>
                                    <option value="buy">Buy</option>
                                    <option value="sell">Sell</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Entry Price
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="plEntryPrice" step="0.00001" min="0" placeholder="Entry Price" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Exit Price
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="plExitPrice" step="0.00001" min="0" placeholder="Exit Price" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Lot Size
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="plLotSize" step="0.01" min="0" placeholder="Lot Size" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Contract Size
                                    <i class="fas fa-info-circle info-icon" title="Standard is 100,000 for forex"></i>
                                </label>
                                <input type="number" class="form-control" id="plContractSize" step="1" min="0" placeholder="Contract Size" value="100000" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-calculate">
                            <i class="fas fa-calculator me-2"></i>Calculate
                        </button>
                    </form>
                    
                    <div class="result-container" id="plResult" style="display: none;">
                        <div class="result-box">
                            <div class="result-label">Profit/Loss</div>
                            <div class="result-value" id="plValue">$0.00</div>
                        </div>
                        <div class="result-box">
                            <div class="result-label">Pips</div>
                            <div class="result-value" id="plPips">0</div>
                        </div>
                    </div>
                </div>

                <!-- Lot Size Calculator -->
                <div class="calculator-content <?php echo $calc_type === 'lot-size' ? 'active' : ''; ?>" id="lot-size-calc">
                    <h2 class="calculator-title">Lot Size Calculator</h2>
                    <p class="calculator-subtitle">Calculate the optimal lot size based on your risk management parameters</p>
                    
                    <form id="lotSizeCalculator" onsubmit="calculateLotSize(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Instrument
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <select class="form-select" id="lotInstrument" required>
                                    <option value="">Select Instrument</option>
                                    <option value="EURUSD">EUR/USD</option>
                                    <option value="GBPUSD">GBP/USD</option>
                                    <option value="USDJPY">USD/JPY</option>
                                    <option value="AUDUSD">AUD/USD</option>
                                    <option value="USDCHF">USD/CHF</option>
                                    <option value="NZDUSD">NZD/USD</option>
                                    <option value="EURJPY">EUR/JPY</option>
                                    <option value="GBPJPY">GBP/JPY</option>
                                    <option value="XAUUSD">XAU/USD (Gold)</option>
                                    <option value="BTCUSD">BTC/USD</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Account Balance
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="lotBalance" step="0.01" min="0" placeholder="Account Balance" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Risk Percentage
                                    <i class="fas fa-info-circle info-icon" title="Percentage (1 for 1%)"></i>
                                </label>
                                <input type="number" class="form-control" id="lotRiskPercent" step="0.1" min="0" max="100" placeholder="Percentage (1 for 1%)" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Entry Price
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="lotEntryPrice" step="0.00001" min="0" placeholder="Entry Price" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Exit Price (Stop Loss)
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="lotExitPrice" step="0.00001" min="0" placeholder="Exit Price" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Leverage
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="lotLeverage" step="1" min="1" placeholder="Leverage" value="100" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-calculate">
                            <i class="fas fa-calculator me-2"></i>Calculate
                        </button>
                    </form>
                    
                    <div class="result-container" id="lotResult" style="display: none;">
                        <div class="result-box">
                            <div class="result-label">Lot Size</div>
                            <div class="result-value" id="lotSizeValue">0.00</div>
                        </div>
                        <div class="result-box">
                            <div class="result-label">Risk Amount</div>
                            <div class="result-value" id="lotRiskAmount">$0.00</div>
                        </div>
                    </div>
                </div>

                <!-- Swap Calculator -->
                <div class="calculator-content <?php echo $calc_type === 'swap' ? 'active' : ''; ?>" id="swap-calc">
                    <h2 class="calculator-title">Swap Calculator</h2>
                    <p class="calculator-subtitle">Calculate swap fees for holding positions overnight</p>
                    
                    <form id="swapCalculator" onsubmit="calculateSwap(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Instrument
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <select class="form-select" id="swapInstrument" required>
                                    <option value="">Select Instrument</option>
                                    <option value="EURUSD">EUR/USD</option>
                                    <option value="GBPUSD">GBP/USD</option>
                                    <option value="USDJPY">USD/JPY</option>
                                    <option value="AUDUSD">AUD/USD</option>
                                    <option value="USDCHF">USD/CHF</option>
                                    <option value="NZDUSD">NZD/USD</option>
                                    <option value="EURJPY">EUR/JPY</option>
                                    <option value="GBPJPY">GBP/JPY</option>
                                    <option value="XAUUSD">XAU/USD (Gold)</option>
                                    <option value="BTCUSD">BTC/USD</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Trade Type
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <select class="form-select" id="swapTradeType" required>
                                    <option value="buy">Buy</option>
                                    <option value="sell">Sell</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Lot Size
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="swapLotSize" step="0.01" min="0" placeholder="Lot Size" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Number of Nights
                                    <i class="fas fa-info-circle info-icon"></i>
                                </label>
                                <input type="number" class="form-control" id="swapNights" step="1" min="1" placeholder="Number of Nights" value="1" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Swap Long (per lot per night)
                                    <i class="fas fa-info-circle info-icon" title="Swap rate for long positions"></i>
                                </label>
                                <input type="number" class="form-control" id="swapLong" step="0.01" placeholder="Swap Long" value="0.5">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Swap Short (per lot per night)
                                    <i class="fas fa-info-circle info-icon" title="Swap rate for short positions"></i>
                                </label>
                                <input type="number" class="form-control" id="swapShort" step="0.01" placeholder="Swap Short" value="-0.5">
                            </div>
                        </div>
                        <button type="submit" class="btn-calculate">
                            <i class="fas fa-calculator me-2"></i>Calculate
                        </button>
                    </form>
                    
                    <div class="result-container" id="swapResult" style="display: none;">
                        <div class="result-box">
                            <div class="result-label">Total Swap</div>
                            <div class="result-value" id="swapValue">$0.00</div>
                        </div>
                        <div class="result-box">
                            <div class="result-label">Per Night</div>
                            <div class="result-value" id="swapPerNight">$0.00</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Disclaimer -->
            <div class="disclaimer-box">
                <strong>Disclaimer:</strong> The results from this calculator are for informational purposes only and may differ from actual outcomes due to market conditions. Please use caution and consider professional advice before making trading decisions.
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Switch calculator tabs
        function switchCalculator(type) {
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('type', type);
            window.history.pushState({}, '', url);
            
            // Hide all calculator contents
            document.querySelectorAll('.calculator-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.calculator-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected calculator
            document.getElementById(type + '-calc').classList.add('active');
            
            // Add active class to clicked tab
            event.target.closest('.calculator-tab').classList.add('active');
        }
        
        // Sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const mainContent = document.querySelector('.main-content');
            
            if (!sidebar) return;
            
            if (sidebar.classList.contains('closed')) {
                sidebar.classList.remove('closed');
                sidebar.classList.add('show');
                sidebar.style.setProperty('transform', 'translateX(0)', 'important');
                if (mainContent && window.innerWidth > 768) {
                    mainContent.style.marginLeft = '280px';
                }
                if (toggleBtn) {
                    toggleBtn.classList.remove('show');
                    toggleBtn.style.setProperty('display', 'none', 'important');
                }
            } else {
                sidebar.classList.add('closed');
                sidebar.classList.remove('show');
                sidebar.style.setProperty('transform', 'translateX(-100%)', 'important');
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                }
                if (toggleBtn) {
                    toggleBtn.classList.add('show');
                    toggleBtn.style.setProperty('display', 'block', 'important');
                }
            }
        }
        
        // Initialize sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const mainContent = document.querySelector('.main-content');
            
            if (!sidebar) return;
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('closed');
                sidebar.classList.add('show');
                sidebar.style.setProperty('transform', 'translateX(0)', 'important');
                if (mainContent) {
                    mainContent.style.marginLeft = '280px';
                }
                if (toggleBtn) {
                    toggleBtn.classList.remove('show');
                    toggleBtn.style.setProperty('display', 'none', 'important');
                }
            } else {
                sidebar.classList.add('closed');
                sidebar.classList.remove('show');
                sidebar.style.setProperty('transform', 'translateX(-100%)', 'important');
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                }
                if (toggleBtn) {
                    toggleBtn.classList.add('show');
                    toggleBtn.style.setProperty('display', 'block', 'important');
                }
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    if (!sidebar.classList.contains('closed')) {
                        sidebar.classList.add('show');
                        sidebar.classList.remove('closed');
                        sidebar.style.setProperty('transform', 'translateX(0)', 'important');
                        if (mainContent) {
                            mainContent.style.marginLeft = '280px';
                        }
                    }
                    if (toggleBtn) {
                        toggleBtn.classList.remove('show');
                        toggleBtn.style.setProperty('display', 'none', 'important');
                    }
                } else {
                    sidebar.classList.add('closed');
                    sidebar.classList.remove('show');
                    sidebar.style.setProperty('transform', 'translateX(-100%)', 'important');
                    if (mainContent) {
                        mainContent.style.marginLeft = '0';
                    }
                    if (toggleBtn) {
                        toggleBtn.classList.add('show');
                        toggleBtn.style.setProperty('display', 'block', 'important');
                    }
                }
            });
        });
        
        // Margin Calculator
        function calculateMargin(event) {
            event.preventDefault();
            
            const balance = parseFloat(document.getElementById('marginBalance').value);
            const lotSize = parseFloat(document.getElementById('marginLotSize').value);
            const leverage = parseFloat(document.getElementById('marginLeverage').value);
            
            // Standard lot size is 100,000 units
            const contractSize = 100000;
            const marginRequired = (lotSize * contractSize) / leverage;
            const freeMargin = balance - marginRequired;
            
            document.getElementById('marginValue').textContent = '$' + marginRequired.toFixed(2);
            document.getElementById('freeMargin').textContent = '$' + Math.max(0, freeMargin).toFixed(2);
            document.getElementById('marginResult').style.display = 'grid';
        }
        
        // Profit/Loss Calculator
        function calculateProfitLoss(event) {
            event.preventDefault();
            
            const tradeType = document.getElementById('plTradeType').value;
            const entryPrice = parseFloat(document.getElementById('plEntryPrice').value);
            const exitPrice = parseFloat(document.getElementById('plExitPrice').value);
            const lotSize = parseFloat(document.getElementById('plLotSize').value);
            const contractSize = parseFloat(document.getElementById('plContractSize').value);
            
            let profitLoss;
            if (tradeType === 'buy') {
                profitLoss = (exitPrice - entryPrice) * lotSize * contractSize;
            } else {
                profitLoss = (entryPrice - exitPrice) * lotSize * contractSize;
            }
            
            // Calculate pips (for 4 decimal places, 1 pip = 0.0001)
            const pipValue = 0.0001;
            const pips = Math.abs((exitPrice - entryPrice) / pipValue);
            
            const plValueElement = document.getElementById('plValue');
            plValueElement.textContent = '$' + profitLoss.toFixed(2);
            plValueElement.style.color = profitLoss >= 0 ? 'var(--primary)' : '#ef4444';
            
            document.getElementById('plPips').textContent = pips.toFixed(1);
            document.getElementById('plResult').style.display = 'grid';
        }
        
        // Lot Size Calculator
        function calculateLotSize(event) {
            event.preventDefault();
            
            const balance = parseFloat(document.getElementById('lotBalance').value);
            const riskPercent = parseFloat(document.getElementById('lotRiskPercent').value);
            const entryPrice = parseFloat(document.getElementById('lotEntryPrice').value);
            const exitPrice = parseFloat(document.getElementById('lotExitPrice').value);
            const leverage = parseFloat(document.getElementById('lotLeverage').value);
            
            // Calculate risk amount
            const riskAmount = balance * (riskPercent / 100);
            
            // Calculate price difference
            const priceDifference = Math.abs(entryPrice - exitPrice);
            
            // Standard contract size
            const contractSize = 100000;
            
            // Calculate lot size
            let lotSize = 0;
            if (priceDifference > 0) {
                lotSize = riskAmount / (priceDifference * contractSize);
            }
            
            document.getElementById('lotSizeValue').textContent = lotSize.toFixed(2);
            document.getElementById('lotRiskAmount').textContent = '$' + riskAmount.toFixed(2);
            document.getElementById('lotResult').style.display = 'grid';
        }
        
        // Swap Calculator
        function calculateSwap(event) {
            event.preventDefault();
            
            const tradeType = document.getElementById('swapTradeType').value;
            const lotSize = parseFloat(document.getElementById('swapLotSize').value);
            const nights = parseFloat(document.getElementById('swapNights').value);
            const swapLong = parseFloat(document.getElementById('swapLong').value) || 0;
            const swapShort = parseFloat(document.getElementById('swapShort').value) || 0;
            
            let swapPerNight;
            if (tradeType === 'buy') {
                swapPerNight = swapLong * lotSize;
            } else {
                swapPerNight = Math.abs(swapShort) * lotSize;
            }
            
            const totalSwap = swapPerNight * nights;
            
            document.getElementById('swapPerNight').textContent = '$' + swapPerNight.toFixed(2);
            document.getElementById('swapValue').textContent = '$' + totalSwap.toFixed(2);
            document.getElementById('swapResult').style.display = 'grid';
        }
    </script>
</body>
</html>
