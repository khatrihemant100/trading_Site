<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__.'/../config/database.php';

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: ../logout.php");
    exit();
}

// Get all accounts statistics
try {
    // Total accounts created
    $total_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ?");
    $total_accounts_stmt->execute([$_SESSION['user_id']]);
    $total_accounts = $total_accounts_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total money invested (ONLY challenge_fee for prop firms, initial_balance for others)
    // Check if challenge_fee column exists
    $columns_check = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'challenge_fee'")->fetch();
    if ($columns_check) {
        // For prop firms: only challenge_fee is investment
        // For other accounts: initial_balance is investment
        $total_invested_stmt = $pdo->prepare("
            SELECT SUM(
                CASE 
                    WHEN account_type = 'propfirm' THEN COALESCE(challenge_fee, 0)
                    ELSE initial_balance
                END
            ) as total 
            FROM trading_accounts 
            WHERE user_id = ?
        ");
    } else {
        $total_invested_stmt = $pdo->prepare("SELECT SUM(initial_balance) as total FROM trading_accounts WHERE user_id = ?");
    }
    $total_invested_stmt->execute([$_SESSION['user_id']]);
    $total_invested = $total_invested_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total challenge fees paid (for prop firms only)
    if ($columns_check) {
        $challenge_fees_stmt = $pdo->prepare("SELECT SUM(COALESCE(challenge_fee, 0)) as total FROM trading_accounts WHERE user_id = ? AND account_type = 'propfirm'");
        $challenge_fees_stmt->execute([$_SESSION['user_id']]);
        $total_challenge_fees = $challenge_fees_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } else {
        $total_challenge_fees = 0;
    }
    
    // Active accounts (active + ongoing)
    $active_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ? AND status IN ('active', 'ongoing')");
    $active_accounts_stmt->execute([$_SESSION['user_id']]);
    $active_accounts = $active_accounts_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Failed/Closed accounts (breach, closed, inactive)
    $failed_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ? AND status IN ('closed', 'inactive', 'breach')");
    $failed_accounts_stmt->execute([$_SESSION['user_id']]);
    $failed_accounts = $failed_accounts_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total withdrawals from account_withdrawals table
    // Create table if it doesn't exist
    try {
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
    
    $withdrawals_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(withdrawal_amount), 0) as total 
        FROM account_withdrawals 
        WHERE user_id = ?
    ");
    $withdrawals_stmt->execute([$_SESSION['user_id']]);
    $total_withdrawals = $withdrawals_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Withdrawals breakdown by platform
    $withdrawals_by_platform_stmt = $pdo->prepare("
        SELECT 
            platform,
            COUNT(*) as count,
            COALESCE(SUM(withdrawal_amount), 0) as total_amount
        FROM account_withdrawals
        WHERE user_id = ?
        GROUP BY platform
        ORDER BY total_amount DESC
    ");
    $withdrawals_by_platform_stmt->execute([$_SESSION['user_id']]);
    $withdrawals_by_platform = $withdrawals_by_platform_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lifetime profit/loss (from all trades)
    $lifetime_pl_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(profit_loss), 0) as total 
        FROM trading_journal 
        WHERE user_id = ? AND profit_loss IS NOT NULL
    ");
    $lifetime_pl_stmt->execute([$_SESSION['user_id']]);
    $lifetime_pl = $lifetime_pl_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total loss (only negative values)
    $total_loss_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(profit_loss), 0) as total 
        FROM trading_journal 
        WHERE user_id = ? AND profit_loss < 0
    ");
    $total_loss_stmt->execute([$_SESSION['user_id']]);
    $total_loss = abs($total_loss_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // Loss breakdown by broker
    $broker_loss_stmt = $pdo->prepare("
        SELECT 
            COALESCE(a.broker_name, 'Unknown') as broker_name,
            COALESCE(SUM(j.profit_loss), 0) as total_loss
        FROM trading_journal j
        LEFT JOIN trading_accounts a ON j.account_id = a.id
        WHERE j.user_id = ? AND j.profit_loss < 0
        GROUP BY a.broker_name
        ORDER BY total_loss ASC
    ");
    $broker_loss_stmt->execute([$_SESSION['user_id']]);
    $broker_losses = $broker_loss_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Loss breakdown by account type
    $type_loss_stmt = $pdo->prepare("
        SELECT 
            COALESCE(a.account_type, 'Unknown') as account_type,
            COALESCE(SUM(j.profit_loss), 0) as total_loss,
            COUNT(DISTINCT a.id) as account_count
        FROM trading_journal j
        LEFT JOIN trading_accounts a ON j.account_id = a.id
        WHERE j.user_id = ? AND j.profit_loss < 0
        GROUP BY a.account_type
        ORDER BY total_loss ASC
    ");
    $type_loss_stmt->execute([$_SESSION['user_id']]);
    $type_losses = $type_loss_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Account type breakdown
    $account_type_stmt = $pdo->prepare("
        SELECT 
            account_type,
            COUNT(*) as count,
            SUM(initial_balance) as total_invested,
            SUM(current_balance) as total_current
        FROM trading_accounts
        WHERE user_id = ?
        GROUP BY account_type
    ");
    $account_type_stmt->execute([$_SESSION['user_id']]);
    $account_types = $account_type_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Current total balance (sum of all current balances)
    $current_balance_stmt = $pdo->prepare("SELECT SUM(current_balance) as total FROM trading_accounts WHERE user_id = ?");
    $current_balance_stmt->execute([$_SESSION['user_id']]);
    $current_total_balance = $current_balance_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Net profit/loss (current balance - initial investment)
    $net_pl = $current_total_balance - $total_invested;
    
    // Account status breakdown
    $status_breakdown_stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(initial_balance) as total_invested
        FROM trading_accounts
        WHERE user_id = ?
        GROUP BY status
    ");
    $status_breakdown_stmt->execute([$_SESSION['user_id']]);
    $status_breakdown = $status_breakdown_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent withdrawals
    $recent_withdrawals_stmt = $pdo->prepare("
        SELECT w.*, a.account_name 
        FROM account_withdrawals w
        LEFT JOIN trading_accounts a ON w.account_id = a.id
        WHERE w.user_id = ?
        ORDER BY w.withdrawal_date DESC, w.created_at DESC
        LIMIT 10
    ");
    $recent_withdrawals_stmt->execute([$_SESSION['user_id']]);
    $recent_withdrawals = $recent_withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent accounts (include challenge_fee if column exists)
    $columns_check_accounts = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'challenge_fee'")->fetch();
    if ($columns_check_accounts) {
        $recent_accounts_stmt = $pdo->prepare("
            SELECT *, COALESCE(challenge_fee, 0) as challenge_fee FROM trading_accounts 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
    } else {
        $recent_accounts_stmt = $pdo->prepare("
            SELECT *, 0 as challenge_fee FROM trading_accounts 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
    }
    $recent_accounts_stmt->execute([$_SESSION['user_id']]);
    $recent_accounts = $recent_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Motivational quotes
$quotes = [
    "The goal of a successful trader is to make the best trades. Money is secondary.",
    "Risk comes from not knowing what you're doing.",
    "The stock market is filled with individuals who know the price of everything, but the value of nothing.",
    "In trading, you have to be defensive and aggressive at the same time. If you are not aggressive, you are not going to make money, and if you are not defensive, you are not going to keep money.",
    "The most important quality for an investor is temperament, not intellect.",
    "Time in the market beats timing the market.",
    "The best investment you can make is in yourself.",
    "Don't look for the needle in the haystack. Just buy the haystack.",
    "Rule No. 1: Never lose money. Rule No. 2: Never forget rule No. 1.",
    "The stock market is a voting machine in the short run, but a weighing machine in the long run.",
    "It's not how much money you make, but how much money you keep, how hard it works for you, and how many generations you keep it for.",
    "The biggest risk is not taking any risk. In a world that's changing really quickly, the only strategy that is guaranteed to fail is not taking risks.",
    "Price is what you pay. Value is what you get.",
    "The market can stay irrational longer than you can stay solvent.",
    "Be fearful when others are greedy and greedy when others are fearful."
];
$random_quote = $quotes[array_rand($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio - NpLTrader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
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
        
        /* Sidebar styles */
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
        
        .sidebar-toggle-btn.show {
            display: block !important;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .portfolio-header {
            margin-bottom: 30px;
        }
        
        .portfolio-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .portfolio-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--dark-card) 0%, var(--dark-hover) 100%);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            border-color: var(--primary);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .stat-icon.primary { background: rgba(16, 185, 129, 0.2); color: var(--primary); }
        .stat-icon.success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .stat-icon.danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        .stat-icon.info { background: rgba(59, 130, 246, 0.2); color: var(--info); }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .stat-change {
            font-size: 0.85rem;
            margin-top: 8px;
        }
        
        .stat-change.positive {
            color: var(--success);
        }
        
        .stat-change.negative {
            color: var(--danger);
        }
        
        .card {
            background: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .card-header h5 {
            color: var(--text-primary);
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .table-dark {
            background-color: transparent;
            color: var(--text-primary);
        }
        
        .table-dark th {
            background-color: var(--dark-hover);
            border-color: var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .table-dark td {
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .table-dark tbody tr:hover {
            background-color: var(--dark-hover);
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .motivation-card {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
            border: 2px solid var(--primary);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .motivation-card::before {
            content: '"';
            position: absolute;
            top: -20px;
            left: 20px;
            font-size: 120px;
            color: var(--primary);
            opacity: 0.2;
            font-family: Georgia, serif;
        }
        
        .motivation-quote {
            font-size: 1.3rem;
            font-style: italic;
            color: var(--text-primary);
            line-height: 1.8;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .motivation-author {
            color: var(--primary);
            font-weight: 600;
            font-size: 1rem;
        }
        
        .btn-refresh-quote {
            background: var(--primary);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
        }
        
        .btn-refresh-quote:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: var(--dark-hover);
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .account-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .account-type-forex { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .account-type-propfirm { background: rgba(168, 85, 247, 0.2); color: #a78bfa; }
        .account-type-nepse { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .account-type-crypto { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .account-type-other { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__.'/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="portfolio-header">
            <h1><i class="fas fa-chart-pie me-2"></i>Portfolio Overview</h1>
            <p>Complete analysis of your trading accounts and performance</p>
        </div>
        
        <!-- Main Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_accounts); ?></div>
                    <div class="stat-label">Total Accounts Created</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">रु <?php echo number_format($total_invested, 2); ?></div>
                    <div class="stat-label">Total Money Invested</div>
                    <?php if ($total_challenge_fees > 0): ?>
                        <div class="stat-change text-muted" style="font-size: 0.75rem; margin-top: 5px;">
                            (रु <?php echo number_format($total_challenge_fees, 2); ?> from prop firm challenge fees)
                        </div>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                        Note: For prop firms, only challenge fees are counted as investment. Account value is separate.
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($active_accounts); ?></div>
                    <div class="stat-label">Active Accounts</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($failed_accounts); ?></div>
                    <div class="stat-label">Failed/Closed Accounts</div>
                </div>
            </div>
        </div>
        
        <!-- Financial Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon <?php echo $lifetime_pl >= 0 ? 'success' : 'danger'; ?>">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value <?php echo $lifetime_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                        रु <?php echo number_format($lifetime_pl, 2); ?>
                    </div>
                    <div class="stat-label">Lifetime Profit/Loss</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stat-value text-danger">रु <?php echo number_format($total_loss, 2); ?></div>
                    <div class="stat-label">Total Loss</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value">रु <?php echo number_format($total_withdrawals, 2); ?></div>
                    <div class="stat-label">Total Withdrawals</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon <?php echo $net_pl >= 0 ? 'success' : 'danger'; ?>">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-value <?php echo $net_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                        रु <?php echo number_format($net_pl, 2); ?>
                    </div>
                    <div class="stat-label">Net P/L (Current - Invested)</div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie me-2"></i>Account Type Distribution</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="accountTypeChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Account Status Breakdown</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Loss Breakdown -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Loss by Broker</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Broker</th>
                                    <th>Loss Amount</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($broker_losses)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No loss data available</td></tr>
                                <?php else: 
                                    $total_broker_loss = array_sum(array_column($broker_losses, 'total_loss'));
                                    foreach ($broker_losses as $broker):
                                        $percentage = $total_broker_loss != 0 ? (abs($broker['total_loss']) / abs($total_broker_loss)) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($broker['broker_name']); ?></strong></td>
                                        <td class="text-danger">रु <?php echo number_format(abs($broker['total_loss']), 2); ?></td>
                                        <td>
                                            <div class="progress-bar-custom">
                                                <div class="progress-fill bg-danger" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2 text-danger"></i>Loss by Account Type</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Account Type</th>
                                    <th>Accounts</th>
                                    <th>Loss Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($type_losses)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No loss data available</td></tr>
                                <?php else: ?>
                                    <?php foreach ($type_losses as $type): ?>
                                        <tr>
                                            <td>
                                                <span class="account-type-badge account-type-<?php echo $type['account_type']; ?>">
                                                    <?php echo ucfirst($type['account_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $type['account_count']; ?></td>
                                            <td class="text-danger">रु <?php echo number_format(abs($type['total_loss']), 2); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Account Type Details -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Account Type Breakdown</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark">
                            <thead>
                                <tr>
                                    <th>Account Type</th>
                                    <th>Count</th>
                                    <th>Total Invested</th>
                                    <th>Current Balance</th>
                                    <th>P/L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($account_types)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No accounts yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($account_types as $type): 
                                        $type_pl = ($type['total_current'] ?? 0) - ($type['total_invested'] ?? 0);
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="account-type-badge account-type-<?php echo $type['account_type']; ?>">
                                                    <?php echo ucfirst($type['account_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $type['count']; ?></td>
                                            <td>रु <?php echo number_format($type['total_invested'] ?? 0, 2); ?></td>
                                            <td>रु <?php echo number_format($type['total_current'] ?? 0, 2); ?></td>
                                            <td class="<?php echo $type_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $type_pl >= 0 ? '+' : ''; ?>रु <?php echo number_format($type_pl, 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Withdrawals Breakdown -->
        <?php if (!empty($withdrawals_by_platform)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-money-bill-wave me-2 text-success"></i>Withdrawals by Platform</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Platform</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_withdrawal_amount = array_sum(array_column($withdrawals_by_platform, 'total_amount'));
                                foreach ($withdrawals_by_platform as $w): 
                                    $percentage = $total_withdrawal_amount > 0 ? ($w['total_amount'] / $total_withdrawal_amount) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><strong class="text-capitalize"><?php echo htmlspecialchars($w['platform']); ?></strong></td>
                                        <td><?php echo $w['count']; ?></td>
                                        <td class="text-success">रु <?php echo number_format($w['total_amount'], 2); ?></td>
                                        <td>
                                            <div class="progress-bar-custom">
                                                <div class="progress-fill bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Withdrawals -->
        <?php if (!empty($recent_withdrawals)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>Recent Withdrawals</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th>Amount</th>
                                    <th>Platform</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_withdrawals as $w): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($w['withdrawal_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($w['account_name'] ?? 'N/A'); ?></td>
                                        <td class="text-success"><strong><?php echo $w['currency']; ?> <?php echo number_format($w['withdrawal_amount'], 2); ?></strong></td>
                                        <td><span class="badge bg-success text-capitalize"><?php echo htmlspecialchars($w['platform']); ?></span></td>
                                        <td><?php echo htmlspecialchars($w['platform_details'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Accounts -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i>Recent Accounts</h5>
                        <a href="journal.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark">
                            <thead>
                                <tr>
                                    <th>Account Name</th>
                                    <th>Type</th>
                                    <th>Broker</th>
                                    <th>Account Value</th>
                                    <th>Challenge Fee</th>
                                    <th>Current Balance</th>
                                    <th>P/L</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_accounts)): ?>
                                    <tr><td colspan="9" class="text-center text-muted">No accounts yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_accounts as $account): 
                                        $account_pl = $account['current_balance'] - $account['initial_balance'];
                                        $challenge_fee = isset($account['challenge_fee']) ? floatval($account['challenge_fee']) : 0;
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($account['account_name']); ?></strong></td>
                                            <td>
                                                <span class="account-type-badge account-type-<?php echo $account['account_type']; ?>">
                                                    <?php echo ucfirst($account['account_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($account['broker_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $account['currency']; ?> <?php echo number_format($account['initial_balance'], 2); ?></td>
                                            <td>
                                                <?php if ($account['account_type'] === 'propfirm' && $challenge_fee > 0): ?>
                                                    <span class="text-warning"><?php echo $account['currency']; ?> <?php echo number_format($challenge_fee, 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $account['currency']; ?> <?php echo number_format($account['current_balance'], 2); ?></td>
                                            <td class="<?php echo $account_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $account_pl >= 0 ? '+' : ''; ?><?php echo $account['currency']; ?> <?php echo number_format($account_pl, 2); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($account['status'] === 'active') $status_class = 'success';
                                                elseif ($account['status'] === 'ongoing') $status_class = 'info';
                                                elseif ($account['status'] === 'breach') $status_class = 'danger';
                                                elseif ($account['status'] === 'closed') $status_class = 'warning';
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst($account['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($account['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Motivational Quote -->
        <div class="motivation-card">
            <div class="motivation-quote" id="motivationQuote">
                <?php echo htmlspecialchars($random_quote); ?>
            </div>
            <div class="motivation-author">— Trading Wisdom</div>
            <button class="btn-refresh-quote" onclick="changeQuote()">
                <i class="fas fa-sync-alt me-2"></i>New Quote
            </button>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Motivational Quotes
        const quotes = <?php echo json_encode($quotes); ?>;
        
        function changeQuote() {
            const randomQuote = quotes[Math.floor(Math.random() * quotes.length)];
            const quoteElement = document.getElementById('motivationQuote');
            quoteElement.style.opacity = '0';
            setTimeout(() => {
                quoteElement.textContent = randomQuote;
                quoteElement.style.opacity = '1';
            }, 300);
        }
        
        // Account Type Chart
        <?php if (!empty($account_types)): ?>
        const accountTypeCtx = document.getElementById('accountTypeChart');
        if (accountTypeCtx) {
            new Chart(accountTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_map(function($t) { return ucfirst($t['account_type']); }, $account_types)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($account_types, 'count')); ?>,
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(168, 85, 247, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(148, 163, 184, 0.8)'
                        ],
                        borderColor: [
                            '#3b82f6',
                            '#a855f7',
                            '#10b981',
                            '#f59e0b',
                            '#94a3b8'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#94a3b8',
                                padding: 15
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
        
        // Status Chart
        <?php if (!empty($status_breakdown)): ?>
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($s) { return ucfirst($s['status']); }, $status_breakdown)); ?>,
                    datasets: [{
                        label: 'Account Count',
                        data: <?php echo json_encode(array_column($status_breakdown, 'count')); ?>,
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',   // active
                            'rgba(59, 130, 246, 0.8)',   // ongoing
                            'rgba(239, 68, 68, 0.8)',    // breach
                            'rgba(148, 163, 184, 0.8)',  // inactive
                            'rgba(245, 158, 11, 0.8)'    // closed
                        ],
                        borderColor: [
                            '#10b981',
                            '#3b82f6',
                            '#ef4444',
                            '#94a3b8',
                            '#f59e0b'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#94a3b8',
                                stepSize: 1
                            },
                            grid: {
                                color: '#334155'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#94a3b8'
                            },
                            grid: {
                                color: '#334155'
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
        
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar) {
                sidebar.classList.toggle('closed');
                if (window.innerWidth > 768) {
                    if (sidebar.classList.contains('closed')) {
                        mainContent.style.marginLeft = '0';
                    } else {
                        mainContent.style.marginLeft = '280px';
                    }
                }
            }
        }
    </script>
</body>
</html>

