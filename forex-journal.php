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
} catch (PDOException $e) {
    die("डाटाबेस त्रुटि: " . $e->getMessage());
}

// Forex Trading Journal entry save गर्ने
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_forex') {
    try {
        // Forex Trading Journal Entry
        $trade_date = $_POST['trade_date'];
        $symbol = trim($_POST['symbol']);
        $trade_type = $_POST['trade_type'];
        $entry_price = floatval($_POST['entry_price']);
        $exit_price = !empty($_POST['exit_price']) ? floatval($_POST['exit_price']) : null;
        $net_pnl = !empty($_POST['net_pnl']) ? floatval($_POST['net_pnl']) : null;
        
        // Collect all forex journal data
        $forex_data = [
            'account' => trim($_POST['account'] ?? ''),
            'session' => trim($_POST['session'] ?? ''),
            'timeframe' => trim($_POST['timeframe'] ?? ''),
            'strategy_model' => trim($_POST['strategy_model'] ?? ''),
            'confluences' => trim($_POST['confluences'] ?? ''),
            'entry_signal' => trim($_POST['entry_signal'] ?? ''),
            'sl_pips' => !empty($_POST['sl_pips']) ? floatval($_POST['sl_pips']) : null,
            'tp_pips' => !empty($_POST['tp_pips']) ? floatval($_POST['tp_pips']) : null,
            'position_size' => !empty($_POST['position_size']) ? floatval($_POST['position_size']) : null,
            'risk_percent' => !empty($_POST['risk_percent']) ? floatval($_POST['risk_percent']) : null,
            'trade_status' => trim($_POST['trade_status'] ?? ''),
            'psychology' => trim($_POST['psychology'] ?? ''),
            'mistakes' => trim($_POST['mistakes'] ?? ''),
            'review' => trim($_POST['review'] ?? '')
        ];
        
        // Store forex data as JSON in notes field
        $notes = json_encode($forex_data, JSON_PRETTY_PRINT);
        
        // Calculate quantity (position size) for compatibility
        $quantity = $forex_data['position_size'] ?? 0.01;
        
        // Use net_pnl if provided, otherwise calculate
        $profit_loss = $net_pnl;
        if ($profit_loss === null && $exit_price !== null && $exit_price > 0) {
            if ($trade_type === 'Buy') {
                $profit_loss = ($exit_price - $entry_price) * $quantity * 100000; // For forex lots
            } else {
                $profit_loss = ($entry_price - $exit_price) * $quantity * 100000;
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO trading_journal 
            (user_id, symbol, trade_type, quantity, entry_price, exit_price, trade_date, profit_loss, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $symbol, $trade_type, $quantity, $entry_price, $exit_price, $trade_date, $profit_loss, $notes]);
        $message = "Forex Journal Entry सफलतापूर्वक सेभ भयो!";
        $message_type = 'success';
        header("Location: dashboard.php?success=journal_saved");
        exit();
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forex Trading Journal - NpLTrader</title>
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
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
        }
        
        .page-header {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: var(--text-primary);
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        .page-header p {
            color: var(--text-secondary);
            margin: 10px 0 0 0;
        }
        
        .journal-form-card {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
        }
        
        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-control,
        .form-select {
            background-color: var(--dark-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus,
        .form-select:focus {
            background-color: var(--dark-bg);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--dark-hover);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background-color: var(--border-color);
            border-color: var(--border-color);
        }
        
        .text-danger {
            color: #ef4444 !important;
        }
        
        .text-muted {
            color: var(--text-secondary) !important;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid #ef4444;
        }
        
        .back-link {
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            color: var(--primary-dark);
        }
        
        /* Navbar Styles */
        .top-navbar {
            background-color: var(--dark-card) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding: 1rem 0;
            margin-bottom: 20px;
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">DASHBOARD</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['username'])): 
                        // Profile image fetch गर्ने
                        require_once __DIR__.'/config/database.php';
                        $profile_stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                        $profile_stmt->execute([$_SESSION['user_id']]);
                        $profile_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);
                        $profile_image = $profile_data['profile_image'] ?? null;
                    ?>
                        <div class="dropdown me-3">
                            <button class="btn btn-link text-decoration-none dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" style="color: var(--primary) !important; padding: 0;">
                                <?php if (!empty($profile_image) && file_exists($profile_image)): ?>
                                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; border: 2px solid var(--primary);">
                                <?php else: ?>
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 8px; font-weight: bold;">
                                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-th-large me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="user/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2">Sign In</a>
                        <a href="register.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header">
            <h1><i class="fas fa-book me-2" style="color: var(--primary);"></i>Forex Trading Journal Entry</h1>
            <p>Document your trading journey with detailed analysis and insights</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="journal-form-card">
            <form method="POST" id="forexJournalForm">
                <input type="hidden" name="action" value="add_forex">
                
                <div class="row g-3">
                    <!-- Date -->
                    <div class="col-md-6">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="trade_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <!-- Account -->
                    <div class="col-md-6">
                        <label class="form-label">Account</label>
                        <input type="text" class="form-control" name="account" 
                               placeholder="e.g., Demo Account, Live Account">
                    </div>
                    
                    <!-- Symbol -->
                    <div class="col-md-6">
                        <label class="form-label">Symbol <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="symbol" 
                               placeholder="e.g., EURUSD, GBPUSD, XAUUSD" required>
                    </div>
                    
                    <!-- Session -->
                    <div class="col-md-6">
                        <label class="form-label">Session</label>
                        <select class="form-select" name="session">
                            <option value="">Select Session</option>
                            <option value="Asian">Asian</option>
                            <option value="London">London</option>
                            <option value="New York">New York</option>
                            <option value="Overlap">Overlap</option>
                        </select>
                    </div>
                    
                    <!-- Timeframe -->
                    <div class="col-md-6">
                        <label class="form-label">Timeframe</label>
                        <select class="form-select" name="timeframe">
                            <option value="">Select Timeframe</option>
                            <option value="M1">M1</option>
                            <option value="M5">M5</option>
                            <option value="M15">M15</option>
                            <option value="M30">M30</option>
                            <option value="H1">H1</option>
                            <option value="H4">H4</option>
                            <option value="D1">D1</option>
                            <option value="W1">W1</option>
                        </select>
                    </div>
                    
                    <!-- Type of Trade -->
                    <div class="col-md-6">
                        <label class="form-label">Type of Trade <span class="text-danger">*</span></label>
                        <select class="form-select" name="trade_type" required>
                            <option value="">Select Type</option>
                            <option value="Buy">Buy</option>
                            <option value="Sell">Sell</option>
                        </select>
                    </div>
                    
                    <!-- Strategy Model -->
                    <div class="col-12">
                        <label class="form-label">Strategy Model</label>
                        <input type="text" class="form-control" name="strategy_model" 
                               placeholder="e.g., Price Action, Support/Resistance, Trend Following">
                    </div>
                    
                    <!-- Confluences (3-5) -->
                    <div class="col-12">
                        <label class="form-label">Confluences (3-5)</label>
                        <textarea class="form-control" name="confluences" rows="3" 
                                  placeholder="List 3-5 confluences that supported your trade entry..."></textarea>
                        <small class="text-muted">Example: Support level, RSI oversold, Bullish candlestick pattern, Volume spike, Trend line bounce</small>
                    </div>
                    
                    <!-- Entry Signal -->
                    <div class="col-12">
                        <label class="form-label">Entry Signal</label>
                        <textarea class="form-control" name="entry_signal" rows="2" 
                                  placeholder="Describe the specific signal that triggered your entry..."></textarea>
                    </div>
                    
                    <!-- Entry Price -->
                    <div class="col-md-4">
                        <label class="form-label">Entry Price <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="entry_price" 
                               step="0.00001" min="0" required
                               placeholder="0.00000">
                    </div>
                    
                    <!-- SL (pips) / TP (pips) -->
                    <div class="col-md-4">
                        <label class="form-label">SL (pips)</label>
                        <input type="number" class="form-control" name="sl_pips" 
                               step="0.1" min="0"
                               placeholder="e.g., 20">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">TP (pips)</label>
                        <input type="number" class="form-control" name="tp_pips" 
                               step="0.1" min="0"
                               placeholder="e.g., 40">
                    </div>
                    
                    <!-- Position Size / Risk % -->
                    <div class="col-md-6">
                        <label class="form-label">Position Size (Lots)</label>
                        <input type="number" class="form-control" name="position_size" 
                               step="0.01" min="0"
                               placeholder="e.g., 0.1">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Risk %</label>
                        <input type="number" class="form-control" name="risk_percent" 
                               step="0.1" min="0" max="100"
                               placeholder="e.g., 1.0">
                    </div>
                    
                    <!-- Exit Price -->
                    <div class="col-md-6">
                        <label class="form-label">Exit Price</label>
                        <input type="number" class="form-control" name="exit_price" 
                               step="0.00001" min="0"
                               placeholder="0.00000">
                    </div>
                    
                    <!-- Net PnL -->
                    <div class="col-md-6">
                        <label class="form-label">Net PnL ($)</label>
                        <input type="number" class="form-control" name="net_pnl" 
                               step="0.01"
                               placeholder="0.00">
                    </div>
                    
                    <!-- Trade Status -->
                    <div class="col-md-6">
                        <label class="form-label">Trade Status</label>
                        <select class="form-select" name="trade_status">
                            <option value="">Select Status</option>
                            <option value="Win">Win</option>
                            <option value="Loss">Loss</option>
                            <option value="Breakeven">Breakeven</option>
                        </select>
                    </div>
                    
                    <!-- Psychology -->
                    <div class="col-12">
                        <label class="form-label">Psychology</label>
                        <textarea class="form-control" name="psychology" rows="3" 
                                  placeholder="How were you feeling? Were you confident, anxious, greedy, fearful?"></textarea>
                    </div>
                    
                    <!-- Mistakes (If any) -->
                    <div class="col-12">
                        <label class="form-label">Mistakes (If any)</label>
                        <textarea class="form-control" name="mistakes" rows="2" 
                                  placeholder="What mistakes did you make? What could you have done better?"></textarea>
                    </div>
                    
                    <!-- Review (What I learned) -->
                    <div class="col-12">
                        <label class="form-label">Review (What I learned) <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="review" rows="3" 
                                  placeholder="Key takeaways and lessons learned from this trade..." required></textarea>
                    </div>
                </div>
                
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Journal Entry
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

