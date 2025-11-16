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
$calc_type = isset($_GET['type']) ? $_GET['type'] : 'position';
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
        
        .sidebar-close i.fa-angle-left {
            display: none !important;
        }
        
        .sidebar-close.show-icon i.fa-angle-left {
            display: inline-block !important;
        }
        
        .sidebar-close.show-icon .close-arrow {
            display: none !important;
        }
        
        .sidebar.closed .sidebar-close i {
            transform: rotate(180deg);
        }
        
        /* Sidebar Toggle Button */
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
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.6);
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
            transform: translateX(0);
        }
        
        .nav-link.active::before {
            transform: scaleY(1);
            background: rgba(255, 255, 255, 0.3);
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
        
        .nav-link.active i {
            color: #ffffff !important;
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 16px;
            border-radius: 6px;
            position: relative;
        }
        
        .top-navbar .nav-link:hover {
            background-color: var(--primary) !important;
            color: #ffffff !important;
            transform: translateY(-2px);
        }
        
        .top-navbar .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
        
        /* Calculator Card */
        .calculator-card {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
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
        }
        
        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            background-color: var(--dark-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 15px;
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--dark-bg);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .result-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }
        
        .result-box.show {
            display: block;
        }
        
        .result-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .result-value {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 700;
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
            <button class="sidebar-close" onclick="toggleSidebar()" title="Close Sidebar" aria-label="Close Sidebar">
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
            <li class="nav-item calculator-dropdown">
                <button class="nav-link calculator-dropdown-btn" onclick="toggleCalculatorDropdown(event)" style="width: 100%; text-align: left; background: none; border: none;">
                    <i class="fas fa-calculator"></i>
                    <span>Calculators</span>
                    <i class="fas fa-chevron-down ms-auto" style="margin-left: auto;"></i>
                </button>
                <div class="calculator-dropdown-menu" id="calculatorDropdown">
                    <a href="calculator.php?type=position" class="calculator-dropdown-item <?php echo $calc_type === 'position' ? 'active' : ''; ?>">
                        Position Sizing Calculator
                    </a>
                    <a href="calculator.php?type=compound" class="calculator-dropdown-item <?php echo $calc_type === 'compound' ? 'active' : ''; ?>">
                        Compound Interest Calculator
                    </a>
                    <a href="calculator.php?type=emi" class="calculator-dropdown-item <?php echo $calc_type === 'emi' ? 'active' : ''; ?>">
                        EMI calculator
                    </a>
                    <a href="calculator.php?type=sip" class="calculator-dropdown-item <?php echo $calc_type === 'sip' ? 'active' : ''; ?>">
                        SIP Calculator
                    </a>
                </div>
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
        <div class="calculator-card">
            <?php if ($calc_type === 'position'): ?>
                <h2 class="calculator-title">Position Sizing Calculator</h2>
                <p class="calculator-subtitle">Calculate the optimal position size based on your risk tolerance</p>
                
                <form id="positionCalculator">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Balance (रु)</label>
                            <input type="number" class="form-control" id="accountBalance" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Risk Per Trade (%)</label>
                            <input type="number" class="form-control" id="riskPercent" step="0.1" min="0" max="100" value="2" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Entry Price (रु)</label>
                            <input type="number" class="form-control" id="entryPrice" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stop Loss Price (रु)</label>
                            <input type="number" class="form-control" id="stopLoss" step="0.01" min="0" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator me-2"></i>Calculate Position Size
                    </button>
                </form>
                
                <div class="result-box" id="positionResult">
                    <div class="result-label">Position Size</div>
                    <div class="result-value" id="positionSize">0</div>
                    <div class="result-label mt-3">Risk Amount</div>
                    <div class="result-value" style="font-size: 1.5rem;" id="riskAmount">रु 0</div>
                </div>
                
            <?php elseif ($calc_type === 'compound'): ?>
                <h2 class="calculator-title">Compound Interest Calculator</h2>
                <p class="calculator-subtitle">Calculate compound interest on your investment</p>
                
                <form id="compoundCalculator">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Principal Amount (रु)</label>
                            <input type="number" class="form-control" id="principal" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Annual Interest Rate (%)</label>
                            <input type="number" class="form-control" id="interestRate" step="0.1" min="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time Period (Years)</label>
                            <input type="number" class="form-control" id="timePeriod" step="0.1" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Compounding Frequency</label>
                            <select class="form-select" id="compoundingFreq" required>
                                <option value="1">Annually</option>
                                <option value="2">Semi-Annually</option>
                                <option value="4">Quarterly</option>
                                <option value="12" selected>Monthly</option>
                                <option value="365">Daily</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator me-2"></i>Calculate Compound Interest
                    </button>
                </form>
                
                <div class="result-box" id="compoundResult">
                    <div class="result-label">Final Amount</div>
                    <div class="result-value" id="finalAmount">रु 0</div>
                    <div class="result-label mt-3">Interest Earned</div>
                    <div class="result-value" style="font-size: 1.5rem;" id="interestEarned">रु 0</div>
                </div>
                
            <?php elseif ($calc_type === 'emi'): ?>
                <h2 class="calculator-title">EMI Calculator</h2>
                <p class="calculator-subtitle">Calculate your Equated Monthly Installment</p>
                
                <form id="emiCalculator">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Loan Amount (रु)</label>
                            <input type="number" class="form-control" id="loanAmount" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Interest Rate (% per annum)</label>
                            <input type="number" class="form-control" id="emiInterestRate" step="0.1" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Loan Tenure (Months)</label>
                            <input type="number" class="form-control" id="loanTenure" step="1" min="1" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator me-2"></i>Calculate EMI
                    </button>
                </form>
                
                <div class="result-box" id="emiResult">
                    <div class="result-label">Monthly EMI</div>
                    <div class="result-value" id="emiAmount">रु 0</div>
                    <div class="result-label mt-3">Total Amount Payable</div>
                    <div class="result-value" style="font-size: 1.5rem;" id="totalPayable">रु 0</div>
                    <div class="result-label mt-3">Total Interest</div>
                    <div class="result-value" style="font-size: 1.5rem;" id="totalInterest">रु 0</div>
                </div>
                
            <?php elseif ($calc_type === 'sip'): ?>
                <h2 class="calculator-title">SIP Calculator</h2>
                <p class="calculator-subtitle">Calculate returns on your Systematic Investment Plan</p>
                
                <form id="sipCalculator">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Monthly Investment (रु)</label>
                            <input type="number" class="form-control" id="monthlyInvestment" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expected Annual Return (%)</label>
                            <input type="number" class="form-control" id="sipReturnRate" step="0.1" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Investment Period (Years)</label>
                            <input type="number" class="form-control" id="sipPeriod" step="0.1" min="0" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator me-2"></i>Calculate SIP Returns
                    </button>
                </form>
                
                <div class="result-box" id="sipResult">
                    <div class="result-label">Total Investment</div>
                    <div class="result-value" id="totalInvestment">रु 0</div>
                    <div class="result-label mt-3">Estimated Returns</div>
                    <div class="result-value" style="font-size: 1.5rem;" id="estimatedReturns">रु 0</div>
                    <div class="result-label mt-3">Maturity Value</div>
                    <div class="result-value" style="font-size: 1.5rem;" id="maturityValue">रु 0</div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle calculator dropdown
        function toggleCalculatorDropdown(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const dropdown = document.getElementById('calculatorDropdown');
            const dropdownParent = dropdown.closest('.calculator-dropdown');
            dropdown.classList.toggle('show');
            dropdownParent.classList.toggle('active');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('calculatorDropdown');
            const dropdownParent = dropdown.closest('.calculator-dropdown');
            if (!event.target.closest('.calculator-dropdown')) {
                dropdown.classList.remove('show');
                dropdownParent.classList.remove('active');
            }
        });
        
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
            
            // Show calculator dropdown if a calculator type is selected
            const calcType = '<?php echo $calc_type; ?>';
            if (calcType) {
                const dropdown = document.getElementById('calculatorDropdown');
                const dropdownParent = dropdown.closest('.calculator-dropdown');
                dropdown.classList.add('show');
                dropdownParent.classList.add('active');
            }
            
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
        });
        
        // Position Sizing Calculator
        <?php if ($calc_type === 'position'): ?>
        document.getElementById('positionCalculator').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const accountBalance = parseFloat(document.getElementById('accountBalance').value);
            const riskPercent = parseFloat(document.getElementById('riskPercent').value);
            const entryPrice = parseFloat(document.getElementById('entryPrice').value);
            const stopLoss = parseFloat(document.getElementById('stopLoss').value);
            
            const riskAmount = accountBalance * (riskPercent / 100);
            const priceDifference = Math.abs(entryPrice - stopLoss);
            const positionSize = priceDifference > 0 ? riskAmount / priceDifference : 0;
            
            document.getElementById('positionSize').textContent = positionSize.toFixed(2) + ' units';
            document.getElementById('riskAmount').textContent = 'रु ' + riskAmount.toFixed(2);
            document.getElementById('positionResult').classList.add('show');
        });
        <?php endif; ?>
        
        // Compound Interest Calculator
        <?php if ($calc_type === 'compound'): ?>
        document.getElementById('compoundCalculator').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const principal = parseFloat(document.getElementById('principal').value);
            const rate = parseFloat(document.getElementById('interestRate').value) / 100;
            const time = parseFloat(document.getElementById('timePeriod').value);
            const n = parseFloat(document.getElementById('compoundingFreq').value);
            
            const amount = principal * Math.pow(1 + (rate / n), n * time);
            const interest = amount - principal;
            
            document.getElementById('finalAmount').textContent = 'रु ' + amount.toFixed(2);
            document.getElementById('interestEarned').textContent = 'रु ' + interest.toFixed(2);
            document.getElementById('compoundResult').classList.add('show');
        });
        <?php endif; ?>
        
        // EMI Calculator
        <?php if ($calc_type === 'emi'): ?>
        document.getElementById('emiCalculator').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const principal = parseFloat(document.getElementById('loanAmount').value);
            const rate = parseFloat(document.getElementById('emiInterestRate').value) / 100 / 12; // Monthly rate
            const tenure = parseFloat(document.getElementById('loanTenure').value);
            
            const emi = principal * rate * Math.pow(1 + rate, tenure) / (Math.pow(1 + rate, tenure) - 1);
            const totalPayable = emi * tenure;
            const totalInterest = totalPayable - principal;
            
            document.getElementById('emiAmount').textContent = 'रु ' + emi.toFixed(2);
            document.getElementById('totalPayable').textContent = 'रु ' + totalPayable.toFixed(2);
            document.getElementById('totalInterest').textContent = 'रु ' + totalInterest.toFixed(2);
            document.getElementById('emiResult').classList.add('show');
        });
        <?php endif; ?>
        
        // SIP Calculator
        <?php if ($calc_type === 'sip'): ?>
        document.getElementById('sipCalculator').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const monthlyInvestment = parseFloat(document.getElementById('monthlyInvestment').value);
            const rate = parseFloat(document.getElementById('sipReturnRate').value) / 100 / 12; // Monthly rate
            const period = parseFloat(document.getElementById('sipPeriod').value) * 12; // In months
            
            const totalInvestment = monthlyInvestment * period;
            const maturityValue = monthlyInvestment * ((Math.pow(1 + rate, period) - 1) / rate) * (1 + rate);
            const estimatedReturns = maturityValue - totalInvestment;
            
            document.getElementById('totalInvestment').textContent = 'रु ' + totalInvestment.toFixed(2);
            document.getElementById('estimatedReturns').textContent = 'रु ' + estimatedReturns.toFixed(2);
            document.getElementById('maturityValue').textContent = 'रु ' + maturityValue.toFixed(2);
            document.getElementById('sipResult').classList.add('show');
        });
        <?php endif; ?>
    </script>
</body>
</html>

