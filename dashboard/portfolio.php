<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/dashboard_mode.php';

$is_demo = is_demo_mode();
$mode_name = get_mode_name();

// Currency conversion rates (to USD) - Update these rates as needed
// These are approximate rates - you can fetch live rates from an API if needed
$currency_rates = [
    'USD' => 1.0,
    'EUR' => 1.08,  // 1 EUR = 1.08 USD (approximate)
    'NPR' => 0.0075, // 1 NPR = 0.0075 USD (approximate, 1 USD ≁E133 NPR)
    'GBP' => 1.27,
    'JPY' => 0.0067,
    'AUD' => 0.66,
    'CAD' => 0.73,
    'CHF' => 1.11,
    'CNY' => 0.14,
    'INR' => 0.012,
];

/**
 * Convert amount to USD
 */
function convertToUSD($amount, $currency, $rates) {
    $currency = strtoupper($currency ?? 'USD');
    $rate = $rates[$currency] ?? 1.0;
    return floatval($amount) * $rate;
}

/**
 * Format USD amount
 */
function formatUSD($amount) {
    return '$' . number_format($amount, 2);
}

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
    // Total accounts created (filtered by Real/Demo mode)
    $columns_check_demo = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'is_demo'")->fetch();
    if ($columns_check_demo) {
        $total_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ? AND is_demo = ?");
        $total_accounts_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
    $total_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ?");
    $total_accounts_stmt->execute([$_SESSION['user_id']]);
    }
    $total_accounts = $total_accounts_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total money invested (ONLY challenge_fee for prop firms, initial_balance for others) - filtered by mode
    // Check if challenge_fee column exists
    $columns_check = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'challenge_fee'")->fetch();
    
    // Get all accounts with currency for proper conversion
    if ($columns_check && $columns_check_demo) {
        $accounts_stmt = $pdo->prepare("
            SELECT 
                account_type,
                CASE 
                    WHEN account_type = 'propfirm' THEN COALESCE(challenge_fee, 0)
                    ELSE initial_balance
                END as investment,
                COALESCE(challenge_fee, 0) as challenge_fee,
                initial_balance,
                current_balance,
                currency
            FROM trading_accounts 
            WHERE user_id = ? AND is_demo = ?
        ");
        $accounts_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } elseif ($columns_check_demo) {
        $accounts_stmt = $pdo->prepare("
            SELECT 
                account_type,
                initial_balance as investment,
                0 as challenge_fee,
                initial_balance,
                current_balance,
                currency
            FROM trading_accounts 
            WHERE user_id = ? AND is_demo = ?
        ");
        $accounts_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } elseif ($columns_check) {
        $accounts_stmt = $pdo->prepare("
            SELECT 
                account_type,
                CASE 
                    WHEN account_type = 'propfirm' THEN COALESCE(challenge_fee, 0)
                    ELSE initial_balance
                END as investment,
                COALESCE(challenge_fee, 0) as challenge_fee,
                initial_balance,
                current_balance,
                currency
            FROM trading_accounts 
            WHERE user_id = ?
        ");
        $accounts_stmt->execute([$_SESSION['user_id']]);
    } else {
        $accounts_stmt = $pdo->prepare("
            SELECT 
                account_type,
                initial_balance as investment,
                0 as challenge_fee,
                initial_balance,
                current_balance,
                currency
            FROM trading_accounts 
            WHERE user_id = ?
        ");
        $accounts_stmt->execute([$_SESSION['user_id']]);
    }
    $all_accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals in USD
    $total_invested_usd = 0;
    $total_challenge_fees_usd = 0;
    $total_deposits_usd = 0;
    $current_total_balance_usd = 0;
    $regular_balance_usd = 0; // Only regular account balances
    $prop_firm_withdrawals_usd = 0; // Only prop firm withdrawals (actual profit)
    
    // Get all account IDs
    $all_account_ids = array_column($all_accounts, 'id');
    
    // Get withdrawals for prop firm accounts only
    if (!empty($all_account_ids)) {
        $placeholders = implode(',', array_fill(0, count($all_account_ids), '?'));
        
        // Get prop firm account IDs
        $prop_account_ids = [];
        foreach ($all_accounts as $acc) {
            if ($acc['account_type'] === 'propfirm') {
                $prop_account_ids[] = $acc['id'];
            }
        }
        
        // Get withdrawals from prop firm accounts
        if (!empty($prop_account_ids)) {
            $prop_placeholders = implode(',', array_fill(0, count($prop_account_ids), '?'));
            $prop_withdrawals_stmt = $pdo->prepare("
                SELECT withdrawal_amount, currency
                FROM account_withdrawals
                WHERE account_id IN ($prop_placeholders)
            ");
            $prop_withdrawals_stmt->execute($prop_account_ids);
            $prop_withdrawals_data = $prop_withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($prop_withdrawals_data as $w) {
                $currency = $w['currency'] ?? 'USD';
                $prop_firm_withdrawals_usd += convertToUSD($w['withdrawal_amount'], $currency, $currency_rates);
            }
        }
    }
    
    foreach ($all_accounts as $acc) {
        $currency = $acc['currency'] ?? 'USD';
        $investment = floatval($acc['investment']);
        $balance = floatval($acc['current_balance']);
        $challenge_fee = floatval($acc['challenge_fee'] ?? 0);
        $deposit = floatval($acc['initial_balance']);
        
        $total_invested_usd += convertToUSD($investment, $currency, $currency_rates);
        
        // For prop firms: Don't count balance as profit (it's still locked)
        // Only count withdrawals as profit
        // For regular accounts: Count balance as current value
        if ($acc['account_type'] === 'propfirm') {
            // Prop firm: Challenge fee is investment (cost)
            if ($challenge_fee > 0) {
                $total_challenge_fees_usd += convertToUSD($challenge_fee, $currency, $currency_rates);
            }
            // Don't add prop firm balance to current_total_balance
    } else {
            // Regular account: Initial balance is investment, current balance is value
            $total_deposits_usd += convertToUSD($deposit, $currency, $currency_rates);
            $regular_balance_usd += convertToUSD($balance, $currency, $currency_rates);
            $current_total_balance_usd += convertToUSD($balance, $currency, $currency_rates);
        }
    }
    
    // Total investment = Challenge fees (cost) + Regular deposits
    $total_invested = $total_invested_usd;
    $total_challenge_fees = $total_challenge_fees_usd;
    
    // Total current value = Regular account balances + Prop firm withdrawals (actual profit)
    // Prop firm balances are NOT counted (they're still locked in account)
    $current_total_balance = $regular_balance_usd + $prop_firm_withdrawals_usd;
    
    // Active accounts (active + ongoing) - filtered by mode
    if ($columns_check_demo) {
        $active_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ? AND is_demo = ? AND status IN ('active', 'ongoing')");
        $active_accounts_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
    $active_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ? AND status IN ('active', 'ongoing')");
    $active_accounts_stmt->execute([$_SESSION['user_id']]);
    }
    $active_accounts = $active_accounts_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Failed/Closed accounts (breach, closed, inactive, loss) - filtered by mode
    if ($columns_check_demo) {
        $failed_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ? AND is_demo = ? AND status IN ('closed', 'inactive', 'breach', 'loss')");
        $failed_accounts_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
    $failed_accounts_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trading_accounts WHERE user_id = ? AND status IN ('closed', 'inactive', 'breach', 'loss')");
    $failed_accounts_stmt->execute([$_SESSION['user_id']]);
    }
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
    
    // Total withdrawals with currency conversion
    $withdrawals_stmt = $pdo->prepare("
        SELECT withdrawal_amount, currency
        FROM account_withdrawals 
        WHERE user_id = ?
    ");
    $withdrawals_stmt->execute([$_SESSION['user_id']]);
    $all_withdrawals = $withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_withdrawals_usd = 0;
    foreach ($all_withdrawals as $w) {
        $amount = floatval($w['withdrawal_amount']);
        $currency = $w['currency'] ?? 'USD';
        $total_withdrawals_usd += convertToUSD($amount, $currency, $currency_rates);
    }
    $total_withdrawals = $total_withdrawals_usd;
    
    // Withdrawals breakdown by platform (with currency conversion)
    $withdrawals_by_platform_stmt = $pdo->prepare("
        SELECT 
            platform,
            withdrawal_amount,
            currency
        FROM account_withdrawals
        WHERE user_id = ?
    ");
    $withdrawals_by_platform_stmt->execute([$_SESSION['user_id']]);
    $all_withdrawals_platform = $withdrawals_by_platform_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by platform and convert to USD
    $withdrawals_by_platform = [];
    foreach ($all_withdrawals_platform as $w) {
        $platform = $w['platform'];
        $amount = floatval($w['withdrawal_amount']);
        $currency = $w['currency'] ?? 'USD';
        $amount_usd = convertToUSD($amount, $currency, $currency_rates);
        
        if (!isset($withdrawals_by_platform[$platform])) {
            $withdrawals_by_platform[$platform] = ['count' => 0, 'total_amount' => 0];
        }
        $withdrawals_by_platform[$platform]['count']++;
        $withdrawals_by_platform[$platform]['total_amount'] += $amount_usd;
    }
    
    // Convert to array format
    $withdrawals_by_platform = array_map(function($platform, $data) {
        return [
            'platform' => $platform,
            'count' => $data['count'],
            'total_amount' => $data['total_amount']
        ];
    }, array_keys($withdrawals_by_platform), $withdrawals_by_platform);
    
    // Sort by total amount
    usort($withdrawals_by_platform, function($a, $b) {
        return $b['total_amount'] <=> $a['total_amount'];
    });
    
    // Lifetime profit/loss (from all trades) - filtered by mode with currency conversion
    $columns_check_journal_demo = $pdo->query("SHOW COLUMNS FROM trading_journal LIKE 'is_demo'")->fetch();
    
    // Get all trades with account currency for proper conversion
    // Handle both cases: with account_id and without account_id
    if ($columns_check_journal_demo) {
        $trades_stmt = $pdo->prepare("
            SELECT 
                j.profit_loss, 
                COALESCE(a.currency, 'USD') as currency
            FROM trading_journal j
            LEFT JOIN trading_accounts a ON j.account_id = a.id AND a.user_id = j.user_id
            WHERE j.user_id = ? AND j.is_demo = ? AND j.profit_loss IS NOT NULL
        ");
        $trades_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
        $trades_stmt = $pdo->prepare("
            SELECT 
                j.profit_loss, 
                COALESCE(a.currency, 'USD') as currency
            FROM trading_journal j
            LEFT JOIN trading_accounts a ON j.account_id = a.id AND a.user_id = j.user_id
            WHERE j.user_id = ? AND j.profit_loss IS NOT NULL
        ");
        $trades_stmt->execute([$_SESSION['user_id']]);
    }
    $all_trades = $trades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate lifetime profit and loss separately in USD
    $lifetime_profit_usd = 0;
    $lifetime_loss_usd = 0;
    $lifetime_pl_usd = 0;
    
    // Debug: Check if we have trades
    // Uncomment below line to debug:
    // error_log("Portfolio Debug: Found " . count($all_trades) . " trades for user " . $_SESSION['user_id'] . " in " . ($is_demo ? "demo" : "real") . " mode");
    
    foreach ($all_trades as $trade) {
        $profit_loss = floatval($trade['profit_loss'] ?? 0);
        if ($profit_loss == 0) continue; // Skip zero P/L trades
        
        $currency = $trade['currency'] ?? 'USD';
        $profit_loss_usd = convertToUSD($profit_loss, $currency, $currency_rates);
        
        if ($profit_loss_usd > 0) {
            $lifetime_profit_usd += $profit_loss_usd;
        } else if ($profit_loss_usd < 0) {
            $lifetime_loss_usd += abs($profit_loss_usd);
        }
        $lifetime_pl_usd += $profit_loss_usd;
    }
    
    // Add loss from accounts with status='loss' to lifetime loss
    // Get accounts with status='loss' and calculate their loss
    if ($columns_check_demo) {
        $loss_accounts_for_total_stmt = $pdo->prepare("
            SELECT 
                account_type,
                initial_balance,
                current_balance,
                COALESCE(challenge_fee, 0) as challenge_fee,
                currency
            FROM trading_accounts
            WHERE user_id = ? AND is_demo = ? AND status = 'loss'
        ");
        $loss_accounts_for_total_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
        $loss_accounts_for_total_stmt = $pdo->prepare("
            SELECT 
                account_type,
                initial_balance,
                current_balance,
                COALESCE(challenge_fee, 0) as challenge_fee,
                currency
            FROM trading_accounts
            WHERE user_id = ? AND status = 'loss'
        ");
        $loss_accounts_for_total_stmt->execute([$_SESSION['user_id']]);
    }
    $loss_accounts_for_total = $loss_accounts_for_total_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add loss from accounts with status='loss' to lifetime loss
    foreach ($loss_accounts_for_total as $loss_acc) {
        $currency = $loss_acc['currency'] ?? 'USD';
        
        // Calculate loss amount
        if ($loss_acc['account_type'] === 'propfirm') {
            // For prop firms: challenge_fee is the loss (investment lost)
            $account_loss = floatval($loss_acc['challenge_fee'] ?? 0);
        } else {
            // For regular accounts (forex, etc.) with status='loss': entire initial_balance is loss
            $account_loss = floatval($loss_acc['initial_balance'] ?? 0);
        }
        
        if ($account_loss > 0) {
            $loss_usd = convertToUSD($account_loss, $currency, $currency_rates);
            $lifetime_loss_usd += $loss_usd;
            $lifetime_pl_usd -= $loss_usd; // Subtract from net P/L
        }
    }
    
    $lifetime_pl = $lifetime_pl_usd;
    $total_profit = $lifetime_profit_usd;
    $total_loss = $lifetime_loss_usd;
    
    // Loss breakdown by broker (with currency conversion)
    if ($columns_check_journal_demo) {
    $broker_loss_stmt = $pdo->prepare("
        SELECT 
            COALESCE(a.broker_name, 'Unknown') as broker_name,
                j.profit_loss,
                a.currency
            FROM trading_journal j
            LEFT JOIN trading_accounts a ON j.account_id = a.id
            WHERE j.user_id = ? AND j.is_demo = ? AND j.profit_loss < 0
        ");
        $broker_loss_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
        $broker_loss_stmt = $pdo->prepare("
            SELECT 
                COALESCE(a.broker_name, 'Unknown') as broker_name,
                j.profit_loss,
                a.currency
        FROM trading_journal j
        LEFT JOIN trading_accounts a ON j.account_id = a.id
        WHERE j.user_id = ? AND j.profit_loss < 0
    ");
    $broker_loss_stmt->execute([$_SESSION['user_id']]);
    }
    $broker_loss_data = $broker_loss_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by broker and convert to USD (keep as numeric totals for now)
    $broker_losses = [];
    foreach ($broker_loss_data as $row) {
        $broker = $row['broker_name'] ?? 'Unknown';
        $loss = floatval($row['profit_loss']);
        $currency = $row['currency'] ?? 'USD';
        $loss_usd = convertToUSD(abs($loss), $currency, $currency_rates);
        
        if (!isset($broker_losses[$broker])) {
            $broker_losses[$broker] = 0;
        }
        $broker_losses[$broker] += $loss_usd;
    }
    
    // Add loss from accounts with status='loss' to broker losses
    // Get accounts with status='loss' and calculate their loss
    if ($columns_check_demo) {
        $loss_accounts_stmt = $pdo->prepare("
            SELECT 
                id,
                broker_name,
                account_type,
                initial_balance,
                current_balance,
                COALESCE(challenge_fee, 0) as challenge_fee,
                currency
            FROM trading_accounts
            WHERE user_id = ? AND is_demo = ? AND status = 'loss'
        ");
        $loss_accounts_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
        $loss_accounts_stmt = $pdo->prepare("
            SELECT 
                id,
                broker_name,
                account_type,
                initial_balance,
                current_balance,
                COALESCE(challenge_fee, 0) as challenge_fee,
                currency
            FROM trading_accounts
            WHERE user_id = ? AND status = 'loss'
        ");
        $loss_accounts_stmt->execute([$_SESSION['user_id']]);
    }
    $loss_accounts = $loss_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate loss for each account with status='loss'
    foreach ($loss_accounts as $loss_acc) {
        $currency = $loss_acc['currency'] ?? 'USD';
        $broker = $loss_acc['broker_name'] ?? 'Unknown';
        
        // Calculate loss amount
        if ($loss_acc['account_type'] === 'propfirm') {
            // For prop firms: challenge_fee is the loss (investment lost)
            $account_loss = floatval($loss_acc['challenge_fee'] ?? 0);
        } else {
            // For regular accounts (forex, etc.) with status='loss': entire initial_balance is loss
            $account_loss = floatval($loss_acc['initial_balance'] ?? 0);
        }
        
        if ($account_loss > 0) {
            $loss_usd = convertToUSD($account_loss, $currency, $currency_rates);
            
            // Add to broker losses
            if (!isset($broker_losses[$broker])) {
                $broker_losses[$broker] = 0;
            }
            $broker_losses[$broker] += $loss_usd;
        }
    }
    
    // Re-sort broker losses after adding account losses
    $broker_losses = array_map(function($broker, $loss) {
        return ['broker_name' => $broker, 'total_loss' => -$loss];
    }, array_keys($broker_losses), $broker_losses);
    usort($broker_losses, function($a, $b) {
        return $a['total_loss'] <=> $b['total_loss'];
    });
    
    // Loss breakdown by account type (with currency conversion)
    if ($columns_check_journal_demo) {
    $type_loss_stmt = $pdo->prepare("
        SELECT 
            COALESCE(a.account_type, 'Unknown') as account_type,
                j.profit_loss,
                a.currency,
                a.id as account_id
            FROM trading_journal j
            LEFT JOIN trading_accounts a ON j.account_id = a.id
            WHERE j.user_id = ? AND j.is_demo = ? AND j.profit_loss < 0
        ");
        $type_loss_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
        $type_loss_stmt = $pdo->prepare("
            SELECT 
                COALESCE(a.account_type, 'Unknown') as account_type,
                j.profit_loss,
                a.currency,
                a.id as account_id
        FROM trading_journal j
        LEFT JOIN trading_accounts a ON j.account_id = a.id
        WHERE j.user_id = ? AND j.profit_loss < 0
    ");
    $type_loss_stmt->execute([$_SESSION['user_id']]);
    }
    $type_loss_data = $type_loss_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by account type and convert to USD
    $type_losses = [];
    $account_ids_by_type = [];
    foreach ($type_loss_data as $row) {
        $type = $row['account_type'] ?? 'Unknown';
        $loss = floatval($row['profit_loss']);
        $currency = $row['currency'] ?? 'USD';
        $account_id = $row['account_id'];
        $loss_usd = convertToUSD(abs($loss), $currency, $currency_rates);
        
        if (!isset($type_losses[$type])) {
            $type_losses[$type] = ['total_loss' => 0, 'account_ids' => []];
        }
        $type_losses[$type]['total_loss'] += $loss_usd;
        if ($account_id && !in_array($account_id, $type_losses[$type]['account_ids'])) {
            $type_losses[$type]['account_ids'][] = $account_id;
        }
    }
    
    // Add loss from accounts with status='loss' to account type losses
    foreach ($loss_accounts as $loss_acc) {
        $currency = $loss_acc['currency'] ?? 'USD';
        $type = $loss_acc['account_type'] ?? 'Unknown';
        $account_id = $loss_acc['id'];
        
        // Calculate loss amount
        if ($loss_acc['account_type'] === 'propfirm') {
            // For prop firms: challenge_fee is the loss (investment lost)
            $account_loss = floatval($loss_acc['challenge_fee'] ?? 0);
        } else {
            // For regular accounts (forex, etc.) with status='loss': entire initial_balance is loss
            $account_loss = floatval($loss_acc['initial_balance'] ?? 0);
        }
        
        if ($account_loss > 0) {
            $loss_usd = convertToUSD($account_loss, $currency, $currency_rates);
            
            if (!isset($type_losses[$type])) {
                $type_losses[$type] = ['total_loss' => 0, 'account_ids' => []];
            }
            $type_losses[$type]['total_loss'] += $loss_usd;
            if ($account_id && !in_array($account_id, $type_losses[$type]['account_ids'])) {
                $type_losses[$type]['account_ids'][] = $account_id;
            }
        }
    }
    
    // Convert to array format
    $type_losses = array_map(function($type, $data) {
        return [
            'account_type' => $type,
            'total_loss' => -$data['total_loss'],
            'account_count' => count($data['account_ids'])
        ];
    }, array_keys($type_losses), $type_losses);
    usort($type_losses, function($a, $b) {
        return $a['total_loss'] <=> $b['total_loss'];
    });
    
    // Account type breakdown (with proper investment calculation and USD conversion)
    // Already have $all_accounts from above, group by account_type
    $account_types = [];
    foreach ($all_accounts as $acc) {
        $type = $acc['account_type'];
        $currency = $acc['currency'] ?? 'USD';
        
        if (!isset($account_types[$type])) {
            $account_types[$type] = [
                'account_type' => $type,
                'count' => 0,
                'total_invested' => 0,
                'total_current' => 0,
                'total_challenge_fees' => 0,
                'total_deposits' => 0
            ];
        }
        
        $account_types[$type]['count']++;
        $investment = floatval($acc['investment']);
        $balance = floatval($acc['current_balance']);
        $challenge_fee = floatval($acc['challenge_fee'] ?? 0);
        $deposit = floatval($acc['initial_balance']);
        
        $account_types[$type]['total_invested'] += convertToUSD($investment, $currency, $currency_rates);
        $account_types[$type]['total_current'] += convertToUSD($balance, $currency, $currency_rates);
        
        if ($type === 'propfirm' && $challenge_fee > 0) {
            $account_types[$type]['total_challenge_fees'] += convertToUSD($challenge_fee, $currency, $currency_rates);
        } else {
            $account_types[$type]['total_deposits'] += convertToUSD($deposit, $currency, $currency_rates);
        }
    }
    $account_types = array_values($account_types);
    
    // Net profit/loss calculation:
    // For Prop Firms: Withdrawals (profit) - Challenge Fees (cost)
    // For Regular: Current Balance - Initial Deposit
    // Total: (Regular Balance + Prop Withdrawals) - (Challenge Fees + Regular Deposits)
    $net_pl = $current_total_balance - $total_invested;
    
    // Calculate win rate and other statistics
    $columns_check_journal_demo = $pdo->query("SHOW COLUMNS FROM trading_journal LIKE 'is_demo'")->fetch();
    if ($columns_check_journal_demo) {
        $trade_stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
                SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades,
                SUM(CASE WHEN profit_loss = 0 OR profit_loss IS NULL THEN 1 ELSE 0 END) as breakeven_trades
            FROM trading_journal
            WHERE user_id = ? AND is_demo = ? AND profit_loss IS NOT NULL
        ");
        $trade_stats_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
        $trade_stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
                SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades,
                SUM(CASE WHEN profit_loss = 0 OR profit_loss IS NULL THEN 1 ELSE 0 END) as breakeven_trades
            FROM trading_journal
            WHERE user_id = ? AND profit_loss IS NOT NULL
        ");
        $trade_stats_stmt->execute([$_SESSION['user_id']]);
    }
    $trade_stats = $trade_stats_stmt->fetch(PDO::FETCH_ASSOC);
    $total_trades = $trade_stats['total_trades'] ?? 0;
    $winning_trades = $trade_stats['winning_trades'] ?? 0;
    $losing_trades = $trade_stats['losing_trades'] ?? 0;
    $win_rate = $total_trades > 0 ? ($winning_trades / $total_trades) * 100 : 0;
    
    // Average win and loss
    $avg_win = $winning_trades > 0 ? $total_profit / $winning_trades : 0;
    $avg_loss = $losing_trades > 0 ? $total_loss / $losing_trades : 0;
    $profit_factor = $total_loss > 0 ? $total_profit / $total_loss : ($total_profit > 0 ? 999 : 0);
    
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
    
    // Recent accounts (include challenge_fee if column exists, filtered by mode)
    $columns_check_accounts = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'challenge_fee'")->fetch();
    $columns_check_demo = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'is_demo'")->fetch();
    
    if ($columns_check_accounts && $columns_check_demo) {
        $recent_accounts_stmt = $pdo->prepare("
            SELECT *, COALESCE(challenge_fee, 0) as challenge_fee FROM trading_accounts 
            WHERE user_id = ? AND is_demo = ?
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $recent_accounts_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } elseif ($columns_check_accounts) {
        $recent_accounts_stmt = $pdo->prepare("
            SELECT *, COALESCE(challenge_fee, 0) as challenge_fee FROM trading_accounts 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $recent_accounts_stmt->execute([$_SESSION['user_id']]);
    } else {
        $recent_accounts_stmt = $pdo->prepare("
            SELECT *, 0 as challenge_fee FROM trading_accounts 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
    $recent_accounts_stmt->execute([$_SESSION['user_id']]);
    }
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
            background: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
            transform: translateX(-3px);
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
        
        /* Sidebar Navigation Styles */
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
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
        
        /* Dashboard Link - Larger Size */
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
        
        /* Other Nav Links - Smaller Size (Journal, Portfolio, etc.) */
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
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
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
        
        /* User Info in Sidebar */
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
        
        /* Top Navbar Styles */
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
        
        /* Analytics Styles */
        .analytics-item {
            padding: 15px;
            background: rgba(30, 41, 59, 0.5);
            border-radius: 8px;
            border-left: 3px solid var(--primary);
            margin-bottom: 15px;
        }
        
        .analytics-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .analytics-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .investment-detail-card {
            transition: all 0.3s;
        }
        
        .investment-detail-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    <?php
    if (!function_exists('is_demo_mode')) {
        require_once __DIR__.'/dashboard_mode.php';
    }
    $is_demo = is_demo_mode();
    ?>
    
        /* Share Modal Styles */
        .share-card-preview {
            box-shadow: 0 20px 60px rgba(251, 191, 36, 0.4), 0 0 0 1px rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .format-btn {
            flex: 1;
            padding: 15px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
            text-align: center;
            border: 2px solid var(--border-color);
            background: var(--dark-card);
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .format-btn:hover {
            border-color: #fbbf24;
            background: rgba(251, 191, 36, 0.1);
            transform: translateY(-2px);
        }
        
        .format-btn.active {
            font-weight: 700;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-color: #fbbf24;
            color: white;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
        }
        
        .share-withdrawal-btn {
            transition: all 0.3s ease;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer !important;
            pointer-events: auto !important;
            position: relative;
            z-index: 10;
        }
        
        .share-withdrawal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
        }
        
        .share-withdrawal-btn:active {
            transform: translateY(0);
        }
        
        #shareCardPreview {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            animation: cardGlow 4s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(-50%) rotate(45deg) translateY(0); }
            50% { transform: translateY(-50%) rotate(45deg) translateY(-10px); }
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        @keyframes cardGlow {
            0%, 100% { box-shadow: 0 25px 80px rgba(251, 191, 36, 0.5), inset 0 0 100px rgba(251, 191, 36, 0.15), 0 0 60px rgba(251, 191, 36, 0.3); }
            50% { box-shadow: 0 25px 100px rgba(251, 191, 36, 0.7), inset 0 0 120px rgba(251, 191, 36, 0.2), 0 0 80px rgba(251, 191, 36, 0.4); }
        }
        
        .modal-content {
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .modal-header {
            background: var(--dark-card);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 30px;
        }
        
        .modal-title {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .modal-body {
            background: var(--dark-bg);
            padding: 30px;
        }
        
        .btn-close-white {
            filter: invert(1);
        }
    </style>
    <script>
        // Share Withdrawal Functionality - Define early so it's available when buttons are rendered
        let currentWithdrawalData = {};
        let currentFormat = 'post';
        
        // Function to open share modal - globally accessible
        function openShareModal(btn, event) {
            console.log('=== openShareModal CALLED ===', btn);
            
            // Prevent any default behavior
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            if (!btn) {
                console.error('Button element not provided');
                alert('Button not found');
                return false;
            }
            
            try {
                // Get all data attributes
                const withdrawalId = btn.getAttribute('data-withdrawal-id');
                const amount = btn.getAttribute('data-amount');
                const currency = btn.getAttribute('data-currency');
                const amountOriginal = btn.getAttribute('data-amount-original');
                const account = btn.getAttribute('data-account');
                const platform = btn.getAttribute('data-platform');
                const date = btn.getAttribute('data-date');
                
                console.log('Data attributes:', {
                    withdrawalId, amount, currency, amountOriginal, account, platform, date
                });
                
                currentWithdrawalData = {
                    id: withdrawalId,
                    amount: parseFloat(amount) || 0,
                    currency: currency || 'USD',
                    amountOriginal: parseFloat(amountOriginal) || 0,
                    account: account || 'N/A',
                    platform: platform || 'N/A',
                    date: date || ''
                };
                
                console.log('Share button clicked - Data:', currentWithdrawalData);
                
                // Get modal element first
                const modalElement = document.getElementById('shareWithdrawalModal');
                console.log('Modal element:', modalElement);
                
                if (!modalElement) {
                    console.error('Modal element not found');
                    alert('Share modal not found. Please refresh the page.');
                    return false;
                }
                
                // Check if Bootstrap is loaded
                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap is not loaded');
                    alert('Bootstrap library not loaded. Please refresh the page.');
                    return false;
                }
                
                console.log('Bootstrap found, creating modal...');
                
                // Create and show modal first
                const modal = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true,
                    focus: true
                });
                
                console.log('Modal instance created, showing...');
                modal.show();
                
                // Update share card content after modal is shown (wait for modal to be visible)
                setTimeout(function() {
                    if (typeof updateShareCard === 'function') {
                        console.log('Calling updateShareCard with data:', currentWithdrawalData);
                        updateShareCard();
                    } else {
                        console.warn('updateShareCard function not found');
                        // Manual update
                        const amountEl = document.getElementById('shareCardAmount');
                        const accountEl = document.getElementById('shareCardAccount');
                        const platformEl = document.getElementById('shareCardPlatform');
                        const dateEl = document.getElementById('shareCardDate');
                        const currencyEl = document.getElementById('shareCardCurrency');
                        
                        if (amountEl) {
                            const formattedAmount = (currentWithdrawalData.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            amountEl.textContent = `+$${formattedAmount}`;
                            amountEl.style.color = '#10b981';
                        }
                        if (accountEl) accountEl.textContent = currentWithdrawalData.account || 'N/A';
                        if (platformEl) platformEl.textContent = currentWithdrawalData.platform || 'N/A';
                        if (dateEl) dateEl.textContent = currentWithdrawalData.date || '-';
                        if (currencyEl) currencyEl.textContent = currentWithdrawalData.currency || 'USD';
                    }
                }, 300);
                
                console.log('Modal opened successfully');
                return false; // Prevent default form submission
            } catch (error) {
                console.error('Error opening share modal:', error);
                console.error('Error stack:', error.stack);
                alert('Error opening share modal: ' + error.message);
                return false;
            }
        }
        
        // Update share card content - Define early so it's available
        function updateShareCard() {
            console.log('=== updateShareCard CALLED ===');
            console.log('Current withdrawal data:', currentWithdrawalData);
            
            const amount = currentWithdrawalData.amount || 0;
            const currency = currentWithdrawalData.currency || 'USD';
            const account = currentWithdrawalData.account || 'N/A';
            const platform = currentWithdrawalData.platform || 'N/A';
            const date = currentWithdrawalData.date || '-';
            
            // Format amount with proper spacing
            const formattedAmount = amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Update amount with green color
            const amountEl = document.getElementById('shareCardAmount');
            if (amountEl) {
                amountEl.textContent = `+$${formattedAmount}`;
                amountEl.style.color = '#10b981';
                console.log('✓ Amount updated:', `+$${formattedAmount}`);
            } else {
                console.error('✗ shareCardAmount element not found');
            }
            
            // Update currency
            const currencyEl = document.getElementById('shareCardCurrency');
            if (currencyEl) {
                currencyEl.textContent = currency;
                console.log('✓ Currency updated:', currency);
            } else {
                console.error('✗ shareCardCurrency element not found');
            }
            
            // Update account
            const accountEl = document.getElementById('shareCardAccount');
            if (accountEl) {
                accountEl.textContent = account;
                console.log('✓ Account updated:', account);
            } else {
                console.error('✗ shareCardAccount element not found');
            }
            
            // Update platform
            const platformEl = document.getElementById('shareCardPlatform');
            if (platformEl) {
                platformEl.textContent = platform;
                console.log('✓ Platform updated:', platform);
            } else {
                console.error('✗ shareCardPlatform element not found');
            }
            
            // Update date
            const dateEl = document.getElementById('shareCardDate');
            if (dateEl) {
                dateEl.textContent = date;
                console.log('✓ Date updated:', date);
            } else {
                console.error('✗ shareCardDate element not found');
            }
            
            console.log('=== Share card update complete ===');
            
            // Username is already set in PHP, but ensure it's visible
            const usernameEl = document.getElementById('shareCardUsername');
            if (usernameEl) {
                usernameEl.style.display = 'block';
                usernameEl.style.visibility = 'visible';
                usernameEl.style.opacity = '1';
            }
            
            const now = new Date();
            const timestamp = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' + 
                            now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            const timestampEl = document.getElementById('shareCardTimestamp');
            if (timestampEl) {
                timestampEl.textContent = timestamp;
            }
            
            // Update verification link with withdrawal ID
            const verificationLink = document.getElementById('verificationLink');
            if (verificationLink && currentWithdrawalData.id) {
                verificationLink.href = `https://www.npltrader.com/verify?id=${currentWithdrawalData.id}`;
            }
            
            // Update card dimensions based on format
            if (typeof updateCardDimensions === 'function') {
                updateCardDimensions();
            }
        }
        
        // Make functions globally accessible
        window.openShareModal = openShareModal;
        window.updateShareCard = updateShareCard;
    </script>
</head>
<body class="<?php echo $is_demo ? 'demo-mode-active' : ''; ?>">
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

    <?php include __DIR__.'/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="portfolio-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
            <h1><i class="fas fa-chart-pie me-2"></i>Portfolio Overview</h1>
            <p>Complete analysis of your trading accounts and performance</p>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block">
                        <i class="fas fa-info-circle me-1"></i>All amounts converted to USD
                    </small>
                    <small class="text-muted" style="font-size: 0.7rem;">
                        Exchange rates: NPR ≁E0.0075, EUR ≁E1.08
                    </small>
                </div>
            </div>
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
                    <div class="stat-value"><?php echo formatUSD($total_invested); ?></div>
                    <div class="stat-label">Total Money Invested</div>
                    <?php 
                    // Calculate breakdown
                    $total_deposits = $total_invested - $total_challenge_fees;
                    ?>
                    <?php if ($total_challenge_fees > 0 && $total_deposits > 0): ?>
                        <div class="stat-breakdown mt-2" style="font-size: 0.75rem;">
                            <div class="text-warning">
                                <i class="fas fa-flask me-1"></i>Prop Firm Fees: <?php echo formatUSD($total_challenge_fees); ?>
                            </div>
                            <div class="text-info mt-1">
                                <i class="fas fa-wallet me-1"></i>Regular Deposits: <?php echo formatUSD($total_deposits); ?>
                            </div>
                        </div>
                    <?php elseif ($total_challenge_fees > 0): ?>
                        <div class="stat-change text-warning mt-2" style="font-size: 0.75rem;">
                            <i class="fas fa-flask me-1"></i>All from prop firm challenge fees
                        </div>
                    <?php endif; ?>
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
                    <div class="stat-icon success">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-value text-success">
                        <?php echo formatUSD($total_profit); ?>
                    </div>
                    <div class="stat-label">Lifetime Profit</div>
                    <?php if ($winning_trades > 0): ?>
                        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                            <?php echo $winning_trades; ?> winning trades
                        </small>
                    <?php elseif ($total_trades == 0): ?>
                        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                            <i class="fas fa-info-circle me-1"></i>No trades recorded yet
                        </small>
                    <?php else: ?>
                        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                            No winning trades
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stat-value text-danger">
                        <?php echo formatUSD($total_loss); ?>
                    </div>
                    <div class="stat-label">Lifetime Loss</div>
                    <?php if ($losing_trades > 0): ?>
                        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                            <?php echo $losing_trades; ?> losing trades
                        </small>
                    <?php elseif ($total_trades == 0): ?>
                        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                            <i class="fas fa-info-circle me-1"></i>No trades recorded yet
                        </small>
                    <?php else: ?>
                        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                            No losing trades
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon <?php echo $lifetime_pl >= 0 ? 'success' : 'danger'; ?>">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value <?php echo $lifetime_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatUSD($lifetime_pl); ?>
                    </div>
                    <div class="stat-label">Net P/L (Profit - Loss)</div>
                    <?php if ($total_trades > 0): ?>
                        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                            Win Rate: <?php echo number_format($win_rate, 1); ?>%
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon <?php echo $net_pl >= 0 ? 'success' : 'danger'; ?>">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-value <?php echo $net_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatUSD($net_pl); ?>
                    </div>
                    <div class="stat-label">Net P/L</div>
                    <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">
                        Regular: Balance - Deposit<br>
                        Prop: Withdrawals - Fee
                    </small>
                    <?php 
                    $net_roi = $total_invested > 0 ? ($net_pl / $total_invested) * 100 : 0;
                    ?>
                    <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                        ROI: <?php echo $net_roi >= 0 ? '+' : ''; ?><?php echo number_format($net_roi, 2); ?>%
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Advanced Analytics -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Trading Performance Analytics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="analytics-item">
                                    <div class="analytics-label">Total Trades</div>
                                    <div class="analytics-value"><?php echo number_format($total_trades); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-item">
                                    <div class="analytics-label">Win Rate</div>
                                    <div class="analytics-value text-success"><?php echo number_format($win_rate, 2); ?>%</div>
                                    <small class="text-muted">
                                        <?php echo $winning_trades; ?> wins / <?php echo $losing_trades; ?> losses
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-item">
                                    <div class="analytics-label">Average Win</div>
                                    <div class="analytics-value text-success"><?php echo formatUSD($avg_win); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-item">
                                    <div class="analytics-label">Average Loss</div>
                                    <div class="analytics-value text-danger"><?php echo formatUSD($avg_loss); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <div class="analytics-item">
                                    <div class="analytics-label">Profit Factor</div>
                                    <div class="analytics-value <?php echo $profit_factor >= 1 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo number_format($profit_factor, 2); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $profit_factor >= 1 ? 'Profitable' : 'Needs Improvement'; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-item">
                                    <div class="analytics-label">Risk/Reward Ratio</div>
                                    <div class="analytics-value">
                                        <?php echo $avg_loss > 0 ? number_format($avg_win / $avg_loss, 2) : 'N/A'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-item">
                                    <div class="analytics-label">Total Withdrawals</div>
                                    <div class="analytics-value text-info"><?php echo formatUSD($total_withdrawals); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analytics-item">
                                    <div class="analytics-label">Current Value</div>
                                    <div class="analytics-value"><?php echo formatUSD($current_total_balance); ?></div>
                                    <small class="text-muted">
                                        Regular Balances + Prop Withdrawals
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Investment Breakdown Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie me-2"></i>Investment Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3"><i class="fas fa-flask me-2"></i>Prop Firm Accounts</h6>
                                <?php
                                // Get prop firm accounts summary with currency conversion
                                $columns_check_accounts = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'challenge_fee'")->fetch();
                                $columns_check_demo = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'is_demo'")->fetch();
                                
                                if ($columns_check_accounts && $columns_check_demo) {
                                    $prop_firm_stmt = $pdo->prepare("
                                        SELECT 
                                            id,
                                            COALESCE(challenge_fee, 0) as challenge_fee,
                                            current_balance,
                                            currency
                                        FROM trading_accounts
                                        WHERE user_id = ? AND account_type = 'propfirm' AND is_demo = ?
                                    ");
                                    $prop_firm_stmt->execute([$_SESSION['user_id'], $is_demo]);
                                } elseif ($columns_check_accounts) {
                                    $prop_firm_stmt = $pdo->prepare("
                                        SELECT 
                                            id,
                                            COALESCE(challenge_fee, 0) as challenge_fee,
                                            current_balance,
                                            currency
                                        FROM trading_accounts
                                        WHERE user_id = ? AND account_type = 'propfirm'
                                    ");
                                    $prop_firm_stmt->execute([$_SESSION['user_id']]);
                                } else {
                                    $prop_firm_stmt = $pdo->prepare("
                                        SELECT 
                                            id,
                                            0 as challenge_fee,
                                            current_balance,
                                            currency
                                        FROM trading_accounts
                                        WHERE user_id = ? AND account_type = 'propfirm'
                                    ");
                                    $prop_firm_stmt->execute([$_SESSION['user_id']]);
                                }
                                $prop_firm_accounts = $prop_firm_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                $prop_fees = 0;
                                $prop_balance = 0;
                                $prop_withdrawals = 0;
                                $prop_count = count($prop_firm_accounts);
                                
                                // Get account IDs for prop firm accounts
                                $prop_account_ids = array_column($prop_firm_accounts, 'id');
                                
                                // Get withdrawals for prop firm accounts
                                if (!empty($prop_account_ids)) {
                                    $placeholders = implode(',', array_fill(0, count($prop_account_ids), '?'));
                                    $prop_withdrawals_stmt = $pdo->prepare("
                                        SELECT withdrawal_amount, currency
                                        FROM account_withdrawals
                                        WHERE account_id IN ($placeholders)
                                    ");
                                    $prop_withdrawals_stmt->execute($prop_account_ids);
                                    $prop_withdrawals_data = $prop_withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($prop_withdrawals_data as $w) {
                                        $currency = $w['currency'] ?? 'USD';
                                        $prop_withdrawals += convertToUSD($w['withdrawal_amount'], $currency, $currency_rates);
                                    }
                                }
                                
                                foreach ($prop_firm_accounts as $acc) {
                                    $currency = $acc['currency'] ?? 'USD';
                                    $prop_fees += convertToUSD($acc['challenge_fee'] ?? 0, $currency, $currency_rates);
                                    $prop_balance += convertToUSD($acc['current_balance'] ?? 0, $currency, $currency_rates);
                                }
                                
                                // Balance after withdrawals
                                $prop_balance_after_withdrawals = $prop_balance - $prop_withdrawals;
                                
                                // P/L calculation: (Current Balance - Withdrawals) vs Challenge Fee
                                // This shows actual profit/loss considering withdrawals
                                $prop_pl = $prop_balance_after_withdrawals - $prop_fees;
                                
                                // Total profit including withdrawals
                                $prop_total_profit = ($prop_balance + $prop_withdrawals) - $prop_fees;
                                
                                $prop_roi = $prop_fees > 0 ? ($prop_pl / $prop_fees) * 100 : 0;
                                $prop_total_roi = $prop_fees > 0 ? (($prop_balance + $prop_withdrawals - $prop_fees) / $prop_fees) * 100 : 0;
                                ?>
                                <div class="investment-detail-card mb-3 p-3" style="background: rgba(251, 191, 36, 0.1); border-left: 4px solid #fbbf24; border-radius: 8px;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-flask me-1"></i>Total Challenge Fees (Investment):</span>
                                        <strong class="text-warning"><?php echo formatUSD($prop_fees); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-wallet me-1"></i>Current Account Balance:</span>
                                        <strong><?php echo formatUSD($prop_balance); ?></strong>
                                    </div>
                                    <?php if ($prop_withdrawals > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-money-bill-wave me-1"></i>Total Withdrawals:</span>
                                        <strong class="text-success"><?php echo formatUSD($prop_withdrawals); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-calculator me-1"></i>Balance After Withdrawals:</span>
                                        <strong><?php echo formatUSD($prop_balance_after_withdrawals); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-chart-line me-1"></i>P/L (Balance After Withdrawals vs Challenge Fee):</span>
                                        <strong class="<?php echo $prop_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $prop_pl >= 0 ? '+' : ''; ?><?php echo formatUSD($prop_pl); ?>
                                        </strong>
                                    </div>
                                    <?php if ($prop_withdrawals > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-trophy me-1"></i>Total Profit (Including Withdrawals):</span>
                                        <strong class="<?php echo $prop_total_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $prop_total_profit >= 0 ? '+' : ''; ?><?php echo formatUSD($prop_total_profit); ?>
                                        </strong>
                                    </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted"><i class="fas fa-percentage me-1"></i>ROI:</span>
                                        <strong class="<?php echo $prop_roi >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $prop_roi >= 0 ? '+' : ''; ?><?php echo number_format($prop_roi, 2); ?>%
                                        </strong>
                                    </div>
                                    <?php if ($prop_withdrawals > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="text-muted"><i class="fas fa-chart-bar me-1"></i>Total ROI (Including Withdrawals):</span>
                                        <strong class="<?php echo $prop_total_roi >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $prop_total_roi >= 0 ? '+' : ''; ?><?php echo number_format($prop_total_roi, 2); ?>%
                                        </strong>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mt-2 pt-2 border-top">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?php echo $prop_count; ?> prop firm account(s)
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3"><i class="fas fa-wallet me-2"></i>Regular Accounts</h6>
                                <?php
                                // Get regular accounts summary with currency conversion
                                if ($columns_check_demo) {
                                    $regular_stmt = $pdo->prepare("
                                        SELECT 
                                            id,
                                            initial_balance,
                                            current_balance,
                                            currency
                                        FROM trading_accounts
                                        WHERE user_id = ? AND account_type != 'propfirm' AND is_demo = ?
                                    ");
                                    $regular_stmt->execute([$_SESSION['user_id'], $is_demo]);
                                } else {
                                    $regular_stmt = $pdo->prepare("
                                        SELECT 
                                            id,
                                            initial_balance,
                                            current_balance,
                                            currency
                                        FROM trading_accounts
                                        WHERE user_id = ? AND account_type != 'propfirm'
                                    ");
                                    $regular_stmt->execute([$_SESSION['user_id']]);
                                }
                                $regular_accounts = $regular_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                $regular_deposits = 0;
                                $regular_balance = 0;
                                $regular_withdrawals = 0;
                                $regular_count = count($regular_accounts);
                                
                                // Get account IDs for regular accounts
                                $regular_account_ids = array_column($regular_accounts, 'id');
                                
                                // Get withdrawals for regular accounts
                                if (!empty($regular_account_ids)) {
                                    $placeholders = implode(',', array_fill(0, count($regular_account_ids), '?'));
                                    $regular_withdrawals_stmt = $pdo->prepare("
                                        SELECT withdrawal_amount, currency
                                        FROM account_withdrawals
                                        WHERE account_id IN ($placeholders)
                                    ");
                                    $regular_withdrawals_stmt->execute($regular_account_ids);
                                    $regular_withdrawals_data = $regular_withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($regular_withdrawals_data as $w) {
                                        $currency = $w['currency'] ?? 'USD';
                                        $regular_withdrawals += convertToUSD($w['withdrawal_amount'], $currency, $currency_rates);
                                    }
                                }
                                
                                foreach ($regular_accounts as $acc) {
                                    $currency = $acc['currency'] ?? 'USD';
                                    $regular_deposits += convertToUSD($acc['initial_balance'] ?? 0, $currency, $currency_rates);
                                    $regular_balance += convertToUSD($acc['current_balance'] ?? 0, $currency, $currency_rates);
                                }
                                
                                // Balance after withdrawals
                                $regular_balance_after_withdrawals = $regular_balance - $regular_withdrawals;
                                
                                // P/L calculation: (Current Balance - Withdrawals) vs Initial Deposit
                                $regular_pl = $regular_balance_after_withdrawals - $regular_deposits;
                                
                                // Total profit including withdrawals
                                $regular_total_profit = ($regular_balance + $regular_withdrawals) - $regular_deposits;
                                
                                $regular_roi = $regular_deposits > 0 ? ($regular_pl / $regular_deposits) * 100 : 0;
                                $regular_total_roi = $regular_deposits > 0 ? (($regular_balance + $regular_withdrawals - $regular_deposits) / $regular_deposits) * 100 : 0;
                                ?>
                                <div class="investment-detail-card mb-3 p-3" style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; border-radius: 8px;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-wallet me-1"></i>Total Deposits (Investment):</span>
                                        <strong class="text-info"><?php echo formatUSD($regular_deposits); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-balance-scale me-1"></i>Current Account Balance:</span>
                                        <strong><?php echo formatUSD($regular_balance); ?></strong>
                                    </div>
                                    <?php if ($regular_withdrawals > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-money-bill-wave me-1"></i>Total Withdrawals:</span>
                                        <strong class="text-success"><?php echo formatUSD($regular_withdrawals); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-calculator me-1"></i>Balance After Withdrawals:</span>
                                        <strong><?php echo formatUSD($regular_balance_after_withdrawals); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-chart-line me-1"></i>P/L (Balance After Withdrawals vs Deposit):</span>
                                        <strong class="<?php echo $regular_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $regular_pl >= 0 ? '+' : ''; ?><?php echo formatUSD($regular_pl); ?>
                                        </strong>
                                    </div>
                                    <?php if ($regular_withdrawals > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted"><i class="fas fa-trophy me-1"></i>Total Profit (Including Withdrawals):</span>
                                        <strong class="<?php echo $regular_total_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $regular_total_profit >= 0 ? '+' : ''; ?><?php echo formatUSD($regular_total_profit); ?>
                                        </strong>
                                    </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted"><i class="fas fa-percentage me-1"></i>ROI:</span>
                                        <strong class="<?php echo $regular_roi >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $regular_roi >= 0 ? '+' : ''; ?><?php echo number_format($regular_roi, 2); ?>%
                                        </strong>
                                    </div>
                                    <?php if ($regular_withdrawals > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="text-muted"><i class="fas fa-chart-bar me-1"></i>Total ROI (Including Withdrawals):</span>
                                        <strong class="<?php echo $regular_total_roi >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $regular_total_roi >= 0 ? '+' : ''; ?><?php echo number_format($regular_total_roi, 2); ?>%
                                        </strong>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mt-2 pt-2 border-top">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?php echo $regular_count; ?> regular account(s)
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                        <td class="text-danger"><?php echo formatUSD(abs($broker['total_loss'])); ?></td>
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
                                            <td class="text-danger"><?php echo formatUSD(abs($type['total_loss'])); ?></td>
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
                                    <tr><td colspan="6" class="text-center text-muted">No accounts yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($account_types as $type): 
                                        $type_pl = ($type['total_current'] ?? 0) - ($type['total_invested'] ?? 0);
                                        $type_roi = $type['total_invested'] > 0 ? ($type_pl / $type['total_invested']) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="account-type-badge account-type-<?php echo $type['account_type']; ?>">
                                                    <?php echo ucfirst($type['account_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $type['count']; ?></td>
                                            <td>
                                                <div><?php echo formatUSD($type['total_invested'] ?? 0); ?></div>
                                                <?php if ($type['account_type'] === 'propfirm' && ($type['total_challenge_fees'] ?? 0) > 0): ?>
                                                    <small class="text-warning">
                                                        <i class="fas fa-flask me-1"></i>Challenge Fees: <?php echo formatUSD($type['total_challenge_fees']); ?>
                                                    </small>
                                                <?php elseif ($type['account_type'] !== 'propfirm' && ($type['total_deposits'] ?? 0) > 0): ?>
                                                    <small class="text-info">
                                                        <i class="fas fa-wallet me-1"></i>Deposits: <?php echo formatUSD($type['total_deposits']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatUSD($type['total_current'] ?? 0); ?></td>
                                            <td class="<?php echo $type_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <div>
                                                    <?php echo $type_pl >= 0 ? '+' : ''; ?><?php echo formatUSD($type_pl); ?>
                                                </div>
                                                <small class="text-muted">
                                                    (<?php echo $type_roi >= 0 ? '+' : ''; ?><?php echo number_format($type_roi, 2); ?>%)
                                                </small>
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
                                        <td class="text-success"><?php echo formatUSD($w['total_amount']); ?></td>
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_withdrawals as $w): 
                                    $withdrawal_usd = convertToUSD($w['withdrawal_amount'], $w['currency'], $currency_rates);
                                ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($w['withdrawal_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($w['account_name'] ?? 'N/A'); ?></td>
                                        <td class="text-success">
                                            <strong><?php echo formatUSD($withdrawal_usd); ?></strong>
                                            <small class="text-muted">(<?php echo $w['currency']; ?> <?php echo number_format($w['withdrawal_amount'], 2); ?>)</small>
                                        </td>
                                        <td><span class="badge bg-success text-capitalize"><?php echo htmlspecialchars($w['platform']); ?></span></td>
                                        <td><?php echo htmlspecialchars($w['platform_details'] ?? '-'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-warning share-withdrawal-btn" 
                                                    onclick="event.preventDefault(); event.stopPropagation(); openShareModal(this, event); return false;"
                                                    data-withdrawal-id="<?php echo $w['id']; ?>"
                                                    data-amount="<?php echo $withdrawal_usd; ?>"
                                                    data-currency="<?php echo htmlspecialchars($w['currency']); ?>"
                                                    data-amount-original="<?php echo $w['withdrawal_amount']; ?>"
                                                    data-account="<?php echo htmlspecialchars($w['account_name'] ?? 'N/A'); ?>"
                                                    data-platform="<?php echo htmlspecialchars($w['platform']); ?>"
                                                    data-date="<?php echo date('M d, Y', strtotime($w['withdrawal_date'])); ?>"
                                                    title="Share Withdrawal">
                                                <i class="fas fa-share-alt"></i> Share
                                            </button>
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
                                    <th>Actual Investment</th>
                                    <th>Current Balance</th>
                                    <th>P/L (vs Investment)</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_accounts)): ?>
                                    <tr><td colspan="9" class="text-center text-muted">No accounts yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_accounts as $account): 
                                        $challenge_fee = isset($account['challenge_fee']) ? floatval($account['challenge_fee']) : 0;
                                        $account_status = strtolower(trim($account['status'] ?? ''));
                                        
                                        // Calculate actual investment and P/L
                                        if ($account['account_type'] === 'propfirm') {
                                            // For prop firms: challenge_fee is the actual investment
                                            $actual_investment = $challenge_fee > 0 ? $challenge_fee : 0;
                                            if ($account_status === 'loss') {
                                                // For prop firms with status='loss': entire challenge_fee is loss
                                                $account_pl = -$actual_investment;
                                            } else {
                                                $account_pl = $account['current_balance'] - $actual_investment;
                                            }
                                        } else {
                                            // For regular accounts: initial_balance is the investment
                                            $actual_investment = $account['initial_balance'];
                                            if ($account_status === 'loss') {
                                                // For regular accounts with status='loss': entire initial_balance is loss
                                                $account_pl = -$actual_investment;
                                            } else {
                                                $account_pl = $account['current_balance'] - $actual_investment;
                                            }
                                        }
                                        
                                        $account_roi = $actual_investment > 0 ? ($account_pl / $actual_investment) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($account['account_name']); ?></strong></td>
                                            <td>
                                                <span class="account-type-badge account-type-<?php echo $account['account_type']; ?>">
                                                    <?php echo ucfirst($account['account_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($account['broker_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <div><?php echo $account['currency']; ?> <?php echo number_format($account['initial_balance'], 2); ?></div>
                                                <small class="text-muted">Account Value</small>
                                            </td>
                                            <td>
                                                <?php if ($account['account_type'] === 'propfirm' && $challenge_fee > 0): ?>
                                                    <div class="text-warning">
                                                        <i class="fas fa-flask me-1"></i><?php echo $account['currency']; ?> <?php echo number_format($challenge_fee, 2); ?>
                                                    </div>
                                                    <small class="text-muted">Challenge Fee</small>
                                                <?php else: ?>
                                                    <div class="text-info">
                                                        <i class="fas fa-wallet me-1"></i><?php echo $account['currency']; ?> <?php echo number_format($account['initial_balance'], 2); ?>
                                                    </div>
                                                    <small class="text-muted">Deposit</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $account['currency']; ?> <?php echo number_format($account['current_balance'], 2); ?></td>
                                            <td class="<?php echo $account_pl >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <div class="fw-bold">
                                                <?php echo $account_pl >= 0 ? '+' : ''; ?><?php echo $account['currency']; ?> <?php echo number_format($account_pl, 2); ?>
                                                </div>
                                                <small class="text-muted">
                                                    (<?php echo $account_roi >= 0 ? '+' : ''; ?><?php echo number_format($account_roi, 2); ?>% ROI)
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $account_status = strtolower(trim($account['status'] ?? ''));
                                                $status_class = 'secondary';
                                                $status_text = 'N/A';
                                                
                                                if ($account_status === 'active') {
                                                    $status_class = 'success';
                                                    $status_text = 'Active';
                                                } elseif ($account_status === 'ongoing') {
                                                    $status_class = 'info';
                                                    $status_text = 'Ongoing';
                                                } elseif ($account_status === 'breach') {
                                                    $status_class = 'danger';
                                                    $status_text = 'Breach';
                                                } elseif ($account_status === 'loss') {
                                                    $status_class = 'danger';
                                                    $status_text = 'Loss';
                                                } elseif ($account_status === 'closed') {
                                                    $status_class = 'warning';
                                                    $status_text = 'Closed';
                                                } elseif ($account_status === 'inactive') {
                                                    $status_class = 'secondary';
                                                    $status_text = 'Inactive';
                                                } elseif (!empty($account_status)) {
                                                    $status_class = 'secondary';
                                                    $status_text = ucfirst($account_status);
                                                }
                                                
                                                // If status is empty but account shows as loss in P/L, show Loss
                                                if (empty($account_status) && $account_pl < 0 && $account_pl <= -($actual_investment * 0.9)) {
                                                    $status_class = 'danger';
                                                    $status_text = 'Loss';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
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
            <div class="motivation-author"> ETrading Wisdom</div>
            <button class="btn-refresh-quote" onclick="changeQuote()">
                <i class="fas fa-sync-alt me-2"></i>New Quote
            </button>
        </div>
    </main>
    
    <!-- Share Withdrawal Modal -->
    <div class="modal fade" id="shareWithdrawalModal" tabindex="-1" aria-labelledby="shareWithdrawalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background: var(--dark-bg); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="shareWithdrawalModalLabel">
                        <i class="fas fa-share-alt me-2"></i>Share Trade
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mx-auto">
                            <!-- Share Card Preview -->
                            <div id="shareCardPreview" class="share-card-preview" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 20%, #fcd34d 40%, #fbbf24 60%, #f59e0b 80%, #d97706 100%); border-radius: 24px; padding: 60px; position: relative; overflow: hidden; min-height: 500px; box-shadow: 0 25px 80px rgba(251, 191, 36, 0.6), inset 0 0 100px rgba(255, 255, 255, 0.2); border: 3px solid rgba(251, 191, 36, 0.5);">
                                <!-- Animated Background Pattern -->
                                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.1; background-image: radial-gradient(circle at 3px 3px, rgba(251, 191, 36, 0.6) 1.5px, transparent 0); background-size: 50px 50px;"></div>
                                
                                <!-- Decorative Glow Elements (Yellow/Gold) -->
                                <div style="position: absolute; top: -120px; right: -120px; width: 500px; height: 500px; background: radial-gradient(circle, rgba(251, 191, 36, 0.3) 0%, transparent 70%); border-radius: 50%; filter: blur(40px);"></div>
                                <div style="position: absolute; bottom: -180px; left: -180px; width: 600px; height: 600px; background: radial-gradient(circle, rgba(245, 158, 11, 0.25) 0%, transparent 70%); border-radius: 50%; filter: blur(50px);"></div>
                                <div style="position: absolute; top: 20%; left: -50px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(251, 191, 36, 0.2) 0%, transparent 70%); border-radius: 50%; filter: blur(30px);"></div>
                                <div style="position: absolute; bottom: 10%; right: -80px; width: 400px; height: 400px; background: radial-gradient(circle, rgba(245, 158, 11, 0.2) 0%, transparent 70%); border-radius: 50%; filter: blur(35px);"></div>
                                
                                <!-- Animated Upward Trend Arrow (Gold) -->
                                <div style="position: absolute; top: 45%; right: 20px; transform: translateY(-50%) rotate(45deg); width: 250px; height: 250px; opacity: 0.15; animation: float 6s ease-in-out infinite;">
                                    <svg viewBox="0 0 100 100" style="width: 100%; height: 100%;">
                                        <defs>
                                            <linearGradient id="arrowGradientGold" x1="0%" y1="0%" x2="100%" y2="100%">
                                                <stop offset="0%" style="stop-color:#fbbf24;stop-opacity:1" />
                                                <stop offset="50%" style="stop-color:#f59e0b;stop-opacity:1" />
                                                <stop offset="100%" style="stop-color:#d97706;stop-opacity:1" />
                                            </linearGradient>
                                        </defs>
                                        <path d="M 50 5 L 95 50 L 75 50 L 75 95 L 25 95 L 25 50 L 5 50 Z" fill="url(#arrowGradientGold)" stroke="#fbbf24" stroke-width="2"/>
                                    </svg>
                                </div>
                                
                                <!-- Shimmer Effect (Gold) -->
                                <div style="position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(251, 191, 36, 0.2), transparent); animation: shimmer 3s infinite;"></div>
                                
                                <!-- Logo and Branding -->
                                <div class="d-flex align-items-center mb-4" style="position: relative; z-index: 2;">
                                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%); padding: 14px 18px; border-radius: 14px; margin-right: 15px; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4), inset 0 1px 5px rgba(255,255,255,0.2), 0 0 25px rgba(16, 185, 129, 0.3); position: relative; overflow: hidden; border: 2px solid rgba(255,255,255,0.3);">
                                        <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent); animation: shine 3s infinite;"></div>
                                        <i class="fas fa-chart-line" style="font-size: 26px; color: white; position: relative; z-index: 1; text-shadow: 0 2px 6px rgba(0,0,0,0.2);"></i>
                                    </div>
                                    <div>
                                        <div style="color: #78350f; font-size: 24px; font-weight: 900; letter-spacing: 1px; margin-bottom: 4px; text-shadow: 0 1px 5px rgba(0,0,0,0.1);">NpLTrader</div>
                                        <div style="color: #92400e; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">Professional Trading Journal & Analytics Platform</div>
                                    </div>
                                </div>
                                
                                <!-- Withdrawal Details -->
                                <div style="position: relative; z-index: 2;">
                                    <div style="background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 16px; padding: 28px; margin-bottom: 25px; border: 2px solid rgba(251, 191, 36, 0.5); box-shadow: 0 10px 35px rgba(0,0,0,0.15), inset 0 1px 5px rgba(255,255,255,0.5), 0 0 35px rgba(251, 191, 36, 0.25); position: relative; overflow: hidden;">
                                        <!-- Inner glow (White/Yellow) -->
                                        <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);"></div>
                                        
                                        <div style="position: relative; z-index: 1;">
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 18px;">
                                                <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 8px 14px; border-radius: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3), 0 0 20px rgba(16, 185, 129, 0.15);">
                                                    <i class="fas fa-check-circle" style="color: white; font-size: 14px; margin-right: 6px; text-shadow: 0 1px 3px rgba(0,0,0,0.2);"></i>
                                                    <span style="color: white; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 1px 3px rgba(0,0,0,0.2);">Withdrawal Success</span>
                                                </div>
                                            </div>
                                            
                                            <div style="font-size: 56px; font-weight: 900; color: #10b981; margin-bottom: 12px; line-height: 1; letter-spacing: -1px; text-shadow: 0 3px 15px rgba(16, 185, 129, 0.4), 0 0 25px rgba(16, 185, 129, 0.3);" id="shareCardAmount">
                                                +$0.00
                                            </div>
                                            
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 25px; padding: 10px 16px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border: 2px solid rgba(16, 185, 129, 0.2);">
                                                <div style="color: #047857; font-size: 15px; font-weight: 700; text-shadow: 0 1px 3px rgba(16, 185, 129, 0.2);" id="shareCardCurrency">USD</div>
                                                <div style="width: 4px; height: 4px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);"></div>
                                                <div style="color: #065f46; font-size: 12px; font-weight: 600; text-shadow: 0 1px 2px rgba(16, 185, 129, 0.2);">Withdrawal</div>
                                            </div>
                                            
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; padding-top: 20px; border-top: 2px solid rgba(251, 191, 36, 0.3);">
                                                <div style="padding: 10px; background: rgba(251, 191, 36, 0.1); border-radius: 8px; border: 2px solid rgba(251, 191, 36, 0.2);">
                                                    <div style="color: #92400e; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; font-weight: 600;">Account Name</div>
                                                    <div style="color: #78350f; font-size: 14px; font-weight: 700; text-shadow: 0 1px 3px rgba(0,0,0,0.1); line-height: 1.2; word-break: break-word;" id="shareCardAccount">-</div>
                                                </div>
                                                <div style="padding: 10px; background: rgba(251, 191, 36, 0.1); border-radius: 8px; border: 2px solid rgba(251, 191, 36, 0.2);">
                                                    <div style="color: #92400e; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; font-weight: 600;">Platform</div>
                                                    <div style="color: #78350f; font-size: 14px; font-weight: 700; text-shadow: 0 1px 3px rgba(0,0,0,0.1); line-height: 1.2; word-break: break-word;" id="shareCardPlatform">-</div>
                                                </div>
                                            </div>
                                            
                                            <div style="margin-top: 15px; padding: 12px; background: rgba(251, 191, 36, 0.1); border-radius: 8px; border: 2px solid rgba(251, 191, 36, 0.2); border-top: 2px solid rgba(251, 191, 36, 0.3);">
                                                <div style="color: #92400e; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; font-weight: 600;">Withdrawal Date</div>
                                                <div style="color: #78350f; font-size: 14px; font-weight: 700; text-shadow: 0 1px 3px rgba(0,0,0,0.1);" id="shareCardDate">-</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- User Info -->
                                    <div style="display: flex; align-items: center; background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 14px; padding: 14px; border: 2px solid rgba(251, 191, 36, 0.5); box-shadow: 0 10px 30px rgba(0,0,0,0.15), inset 0 1px 5px rgba(255,255,255,0.5), 0 0 30px rgba(251, 191, 36, 0.25);">
                                        <?php 
                                        $profile_image = $user['profile_image'] ?? null;
                                        $profile_image_path = $profile_image ? __DIR__.'/../' . $profile_image : null;
                                        ?>
                                        <div class="user-avatar me-2" style="width: 45px; height: 45px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #d97706 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; box-shadow: 0 4px 15px rgba(251, 191, 36, 0.5), 0 0 20px rgba(251, 191, 36, 0.3), inset 0 1px 3px rgba(255,255,255,0.3); flex-shrink: 0; border: 2px solid rgba(255,255,255,0.2);">
                                            <?php if (!empty($profile_image) && $profile_image_path && file_exists($profile_image_path)): ?>
                                                <img src="../<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="flex: 1; min-width: 0; padding-right: 10px; overflow: hidden;">
                                            <div style="color: #78350f; font-weight: 700; font-size: 16px; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-shadow: 0 1px 3px rgba(0,0,0,0.1); line-height: 1.2; display: block; width: 100%;" id="shareCardUsername"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <div style="color: #92400e; font-size: 11px; font-weight: 500; text-shadow: 0 1px 2px rgba(0,0,0,0.1);" id="shareCardTimestamp">-</div>
                                        </div>
                                        <div style="text-align: right; flex-shrink: 0; margin-left: 10px;">
                                            <div style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #d97706 100%); padding: 8px 12px; border-radius: 8px; display: inline-block; box-shadow: 0 4px 12px rgba(251, 191, 36, 0.4), 0 0 15px rgba(251, 191, 36, 0.2), inset 0 1px 3px rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.2);">
                                                <span style="color: white; font-size: 14px; font-weight: 800; letter-spacing: 1.5px; text-shadow: 0 1px 5px rgba(0,0,0,0.2);">NpL</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Website URL and Verification Badge -->
                                <div style="position: absolute; bottom: 25px; left: 40px; right: 40px; display: flex; justify-content: space-between; align-items: center; z-index: 2;">
                                    <a href="https://www.npltrader.com" target="_blank" style="color: #78350f; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; text-shadow: 0 1px 2px rgba(0,0,0,0.1); text-decoration: none; transition: all 0.3s; padding: 6px 12px; background: rgba(255,255,255,0.85); border-radius: 15px; border: 2px solid rgba(251, 191, 36, 0.3);">
                                        <i class="fas fa-globe" style="font-size: 12px; color: #10b981; text-shadow: 0 0 6px rgba(16, 185, 129, 0.3);"></i>
                                        <span>www.npltrader.com</span>
                                    </a>
                                    <a href="https://www.npltrader.com/verify" target="_blank" id="verificationLink" style="display: flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 6px 12px; border-radius: 18px; border: 2px solid rgba(255,255,255,0.25); box-shadow: 0 3px 10px rgba(16, 185, 129, 0.4), inset 0 1px 0 rgba(255,255,255,0.15); text-decoration: none; transition: all 0.3s; cursor: pointer;">
                                        <i class="fas fa-check-circle" style="color: white; font-size: 12px; text-shadow: 0 0 8px rgba(255,255,255,0.4);"></i>
                                        <span style="color: white; font-size: 10px; font-weight: 700; letter-spacing: 0.8px; text-shadow: 0 1px 3px rgba(0,0,0,0.2);">VERIFIED</span>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Format Selection -->
                            <div class="mt-4">
                                <label class="text-muted mb-2 d-block">Select Format:</label>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-warning format-btn" data-format="story" data-width="1080" data-height="1920">
                                        Story<br><small>1080x1920</small>
                                    </button>
                                    <button class="btn btn-warning format-btn active" data-format="post" data-width="1080" data-height="1080">
                                        Post<br><small>1080x1080</small>
                                    </button>
                                    <button class="btn btn-outline-warning format-btn" data-format="landscape" data-width="1920" data-height="1080">
                                        Landscape<br><small>1920x1080</small>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-flex gap-2 mt-4">
                                <button class="btn btn-light flex-fill" id="downloadShareCardBtn">
                                    <i class="fas fa-download me-2"></i>Download
                                </button>
                                <button class="btn btn-warning flex-fill" id="shareShareCardBtn">
                                    <i class="fas fa-share-alt me-2"></i>Share
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Share Withdrawal Functionality - Function already defined in head section
        // Variables are already defined globally in head section
        
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
        
        <?php endif; ?>
        
        // Status Chart
        <?php if (!empty($status_breakdown)): ?>
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            // Status color mapping
            const statusColors = {
                'active': { bg: 'rgba(16, 185, 129, 0.8)', border: '#10b981' },
                'ongoing': { bg: 'rgba(59, 130, 246, 0.8)', border: '#3b82f6' },
                'breach': { bg: 'rgba(239, 68, 68, 0.8)', border: '#ef4444' },
                'loss': { bg: 'rgba(239, 68, 68, 0.8)', border: '#ef4444' },
                'inactive': { bg: 'rgba(148, 163, 184, 0.8)', border: '#94a3b8' },
                'closed': { bg: 'rgba(245, 158, 11, 0.8)', border: '#f59e0b' }
            };
            
            const statusLabels = <?php echo json_encode(array_map(function($s) { return ucfirst($s['status']); }, $status_breakdown)); ?>;
            const statusData = <?php echo json_encode(array_column($status_breakdown, 'count')); ?>;
            const statusValues = <?php echo json_encode(array_map(function($s) { return strtolower($s['status']); }, $status_breakdown)); ?>;
            
            const bgColors = statusValues.map(s => statusColors[s]?.bg || 'rgba(148, 163, 184, 0.8)');
            const borderColors = statusValues.map(s => statusColors[s]?.border || '#94a3b8');
            
            new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'Account Count',
                        data: statusData,
                        backgroundColor: bgColors,
                        borderColor: borderColors,
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
        
        // Share Withdrawal Functionality - Duplicate removed, using the one defined earlier
        
        // Update share card content - Duplicate removed, using the one defined in head section
        
        // Format selection - improved handler
        document.addEventListener('click', function(e) {
            const formatBtn = e.target.closest('.format-btn');
            if (formatBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Format button clicked:', formatBtn.getAttribute('data-format'));
                
                // Remove active class from all buttons
                document.querySelectorAll('.format-btn').forEach(b => {
                    b.classList.remove('active', 'btn-warning');
                    b.classList.add('btn-outline-warning');
                });
                
                // Add active class to clicked button
                formatBtn.classList.add('active', 'btn-warning');
                formatBtn.classList.remove('btn-outline-warning');
                
                // Update current format
                currentFormat = formatBtn.getAttribute('data-format');
                console.log('Current format set to:', currentFormat);
                
                // Update card dimensions
                updateCardDimensions();
            }
        });
        
        // Update card dimensions
        function updateCardDimensions() {
            console.log('updateCardDimensions called, format:', currentFormat);
            const card = document.getElementById('shareCardPreview');
            if (!card) {
                console.error('shareCardPreview element not found');
                return;
            }
            
            const formatBtn = document.querySelector(`.format-btn[data-format="${currentFormat}"]`);
            if (!formatBtn) {
                console.error('Format button not found for:', currentFormat);
                return;
            }
            
            const width = parseInt(formatBtn.getAttribute('data-width')) || 1080;
            const height = parseInt(formatBtn.getAttribute('data-height')) || 1080;
            
            console.log('Updating card dimensions:', width, 'x', height);
            
            // Calculate aspect ratio
            const aspectRatio = width / height;
            const maxWidth = 600; // Max width for preview
            const calculatedHeight = maxWidth / aspectRatio;
            
            card.style.width = maxWidth + 'px';
            card.style.height = calculatedHeight + 'px';
            card.style.minHeight = calculatedHeight + 'px';
            
            console.log('Card dimensions updated to:', maxWidth, 'x', calculatedHeight);
        }
        
        // Download share card - improved handler with direct button click
        function setupDownloadButton() {
            const downloadBtn = document.getElementById('downloadShareCardBtn');
            if (!downloadBtn) {
                console.log('Download button not found, will retry...');
                return;
            }
            
            // Remove any existing listeners by cloning
            const newBtn = downloadBtn.cloneNode(true);
            downloadBtn.parentNode.replaceChild(newBtn, downloadBtn);
            
            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Download button clicked directly');
                
                const card = document.getElementById('shareCardPreview');
                if (!card) {
                    console.error('shareCardPreview not found');
                    alert('Card preview not found. Please open the share modal first.');
                    return false;
                }
                
                // Get current format or default to 'post'
                let formatBtn = document.querySelector('.format-btn.active');
                if (!formatBtn) {
                    formatBtn = document.querySelector('.format-btn[data-format="post"]');
                }
                if (!formatBtn) {
                    formatBtn = document.querySelector('.format-btn');
                }
                
                // Get the actual visible dimensions of the card
                const cardRect = card.getBoundingClientRect();
                const actualWidth = cardRect.width;
                const actualHeight = cardRect.height;
                
                console.log('Card visible dimensions:', actualWidth, 'x', actualHeight);
                
                // Get format dimensions for final output
                const formatWidth = formatBtn ? parseInt(formatBtn.getAttribute('data-width')) || 1080 : 1080;
                const formatHeight = formatBtn ? parseInt(formatBtn.getAttribute('data-height')) || 1080 : 1080;
                
                console.log('Format dimensions:', formatWidth, 'x', formatHeight);
                
                // Show loading
                const btn = this;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Downloading...';
                btn.disabled = true;
                
                // Check if html2canvas is loaded
                if (typeof html2canvas === 'undefined') {
                    alert('Image generation library not loaded. Please refresh the page.');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    return false;
                }
                
                // Store original card styles
                const originalWidth = card.style.width;
                const originalHeight = card.style.height;
                const originalMaxWidth = card.style.maxWidth;
                const originalTransform = card.style.transform;
                
                // Temporarily resize card to format dimensions for capture
                card.style.width = formatWidth + 'px';
                card.style.height = formatHeight + 'px';
                card.style.maxWidth = 'none';
                card.style.transform = 'none';
                
                // Wait a bit for resize to take effect
                setTimeout(() => {
                    html2canvas(card, {
                        width: formatWidth,
                        height: formatHeight,
                        scale: 2,
                        backgroundColor: '#fef3c7',
                        useCORS: true,
                        logging: false,
                        allowTaint: false,
                        windowWidth: formatWidth,
                        windowHeight: formatHeight,
                        onclone: function(clonedDoc) {
                            // Ensure all styles are preserved in clone
                            const clonedCard = clonedDoc.getElementById('shareCardPreview');
                            if (clonedCard) {
                                clonedCard.style.width = formatWidth + 'px';
                                clonedCard.style.height = formatHeight + 'px';
                                clonedCard.style.maxWidth = 'none';
                                clonedCard.style.display = 'block';
                                clonedCard.style.visibility = 'visible';
                                clonedCard.style.transform = 'none';
                            }
                        }
                    }).then(canvas => {
                        // Restore original card styles
                        card.style.width = originalWidth;
                        card.style.height = originalHeight;
                        card.style.maxWidth = originalMaxWidth;
                        card.style.transform = originalTransform;
                        
                        // Update card dimensions back to preview size
                        if (typeof updateCardDimensions === 'function') {
                            updateCardDimensions();
                        }
                        
                        try {
                            console.log('Canvas created, size:', canvas.width, 'x', canvas.height);
                            
                            // Convert canvas to blob
                            canvas.toBlob(function(blob) {
                                if (!blob) {
                                    throw new Error('Failed to create blob from canvas');
                                }
                                
                                console.log('Blob created, size:', blob.size, 'bytes');
                                
                                const url = URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                const withdrawalId = currentWithdrawalData && currentWithdrawalData.id ? currentWithdrawalData.id : 'share';
                                const format = formatBtn ? formatBtn.getAttribute('data-format') || 'post' : 'post';
                                const filename = `withdrawal-${withdrawalId}-${format}-${Date.now()}.png`;
                                
                                link.download = filename;
                                link.href = url;
                                link.style.display = 'none';
                                
                                document.body.appendChild(link);
                                
                                // Trigger download
                                setTimeout(() => {
                                    link.click();
                                    console.log('Download triggered for:', filename);
                                    
                                    // Clean up after a delay
                                    setTimeout(() => {
                                        if (document.body.contains(link)) {
                                            document.body.removeChild(link);
                                        }
                                        URL.revokeObjectURL(url);
                                        console.log('Cleanup completed');
                                    }, 200);
                                }, 100);
                                
                                btn.innerHTML = originalText;
                                btn.disabled = false;
                            }, 'image/png', 1.0);
                        } catch (error) {
                            // Restore original card styles even on error
                            card.style.width = originalWidth;
                            card.style.height = originalHeight;
                            card.style.maxWidth = originalMaxWidth;
                            card.style.transform = originalTransform;
                            
                            if (typeof updateCardDimensions === 'function') {
                                updateCardDimensions();
                            }
                            
                            console.error('Download error:', error);
                            alert('Error downloading image: ' + error.message);
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    }).catch(error => {
                        // Restore original card styles even on error
                        card.style.width = originalWidth;
                        card.style.height = originalHeight;
                        card.style.maxWidth = originalMaxWidth;
                        card.style.transform = originalTransform;
                        
                        if (typeof updateCardDimensions === 'function') {
                            updateCardDimensions();
                        }
                        
                        console.error('html2canvas error:', error);
                        alert('Error generating image: ' + error.message + '\n\nPlease try again or refresh the page.');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
                }, 100);
                
                return false;
            });
        }
        
        // Initialize download button when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupDownloadButton);
        } else {
            setupDownloadButton();
        }
        
        // Also setup when modal is shown (in case button is added dynamically)
        const shareModal = document.getElementById('shareWithdrawalModal');
        if (shareModal) {
            shareModal.addEventListener('shown.bs.modal', function() {
                setTimeout(setupDownloadButton, 100);
            });
        }
        
        // Share share card - improved handler (event delegation)
        document.addEventListener('click', function(e) {
            if (e.target.closest('#shareShareCardBtn')) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Share button clicked');
                
                const card = document.getElementById('shareCardPreview');
                if (!card) {
                    console.error('shareCardPreview not found');
                    alert('Card preview not found');
                    return;
                }
                
                const formatBtn = document.querySelector(`.format-btn[data-format="${currentFormat}"]`);
                if (!formatBtn) {
                    console.error('Format button not found');
                    alert('Please select a format first');
                    return;
                }
                
                const width = parseInt(formatBtn.getAttribute('data-width')) || 1080;
                const height = parseInt(formatBtn.getAttribute('data-height')) || 1080;
                
                console.log('Sharing with dimensions:', width, 'x', height);
                
                // Show loading
                const btn = e.target.closest('#shareShareCardBtn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sharing...';
                btn.disabled = true;
                
                html2canvas(card, {
                    width: width,
                    height: height,
                    scale: 2,
                    backgroundColor: '#fef3c7',
                    useCORS: true,
                    logging: false,
                    allowTaint: false
                }).then(canvas => {
                    canvas.toBlob(function(blob) {
                        if (!blob) {
                            console.error('Failed to create blob');
                            alert('Error creating image. Please try again.');
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            return;
                        }
                        
                        // Try native share API first
                        if (navigator.share) {
                            const file = new File([blob], `withdrawal-${currentFormat}.png`, { type: 'image/png' });
                            const shareData = {
                                title: 'My Withdrawal - NpLTrader',
                                text: `Withdrew $${(currentWithdrawalData.amount || 0).toFixed(2)} from ${currentWithdrawalData.account || 'account'}`,
                                files: [file]
                            };
                            
                            // Check if can share files
                            if (navigator.canShare && navigator.canShare(shareData)) {
                                navigator.share(shareData).then(() => {
                                    console.log('Shared successfully');
                                    btn.innerHTML = originalText;
                                    btn.disabled = false;
                                }).catch(err => {
                                    console.log('Share cancelled or error:', err);
                                    if (err.name !== 'AbortError') {
                                        // Only show fallback if it's not user cancellation
                                        showShareOptions(canvas, blob);
                                    } else {
                                        btn.innerHTML = originalText;
                                        btn.disabled = false;
                                    }
                                });
                            } else {
                                // Try without files (text only)
                                navigator.share({
                                    title: 'My Withdrawal - NpLTrader',
                                    text: `Withdrew $${(currentWithdrawalData.amount || 0).toFixed(2)} from ${currentWithdrawalData.account || 'account'}`,
                                    url: window.location.href
                                }).then(() => {
                                    console.log('Shared successfully (text only)');
                                    // Also download the image
                                    downloadImage(blob);
                                    btn.innerHTML = originalText;
                                    btn.disabled = false;
                                }).catch(() => {
                                    showShareOptions(canvas, blob);
                                });
                            }
                        } else {
                            // No native share API, show custom share options
                            showShareOptions(canvas, blob);
                        }
                    }, 'image/png', 1.0);
                }).catch(error => {
                    console.error('html2canvas error:', error);
                    alert('Error generating image: ' + error.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        });
        
        function downloadImage(blob) {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = `withdrawal-${currentWithdrawalData.id || 'share'}-${currentFormat}-${Date.now()}.png`;
            link.href = url;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            setTimeout(() => {
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }, 100);
        }
        
        function showShareOptions(canvas, blob) {
            // Create share options modal
            const shareModal = document.createElement('div');
            shareModal.id = 'shareOptionsModal';
            shareModal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;';
            
            shareModal.innerHTML = `
                <div style="background: var(--dark-card); border-radius: 16px; padding: 30px; max-width: 400px; width: 90%; border: 2px solid var(--border-color); box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h5 style="color: var(--text-primary); margin: 0;">Share Image</h5>
                        <button type="button" class="btn-close btn-close-white" onclick="this.closest('#shareOptionsModal').remove()"></button>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button class="btn btn-primary share-option-btn" data-action="copy" style="width: 100%; padding: 12px; text-align: left;">
                            <i class="fas fa-copy me-2"></i>Copy to Clipboard
                        </button>
                        <button class="btn btn-success share-option-btn" data-action="download" style="width: 100%; padding: 12px; text-align: left;">
                            <i class="fas fa-download me-2"></i>Download Image
                        </button>
                        <button class="btn btn-info share-option-btn" data-action="whatsapp" style="width: 100%; padding: 12px; text-align: left;">
                            <i class="fab fa-whatsapp me-2"></i>Share on WhatsApp
                        </button>
                        <button class="btn btn-primary share-option-btn" data-action="facebook" style="width: 100%; padding: 12px; text-align: left;">
                            <i class="fab fa-facebook me-2"></i>Share on Facebook
                        </button>
                        <button class="btn btn-info share-option-btn" data-action="twitter" style="width: 100%; padding: 12px; text-align: left;">
                            <i class="fab fa-twitter me-2"></i>Share on Twitter
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(shareModal);
            
            // Handle share option clicks
            shareModal.querySelectorAll('.share-option-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.getAttribute('data-action');
                    handleShareAction(action, canvas, blob);
                    shareModal.remove();
                });
            });
            
            // Close on background click
            shareModal.addEventListener('click', function(e) {
                if (e.target === shareModal) {
                    shareModal.remove();
                }
            });
            
            // Reset share button
            const shareBtn = document.getElementById('shareShareCardBtn');
            if (shareBtn) {
                shareBtn.innerHTML = '<i class="fas fa-share-alt me-2"></i>Share';
                shareBtn.disabled = false;
            }
        }
        
        function handleShareAction(action, canvas, blob) {
            switch(action) {
                case 'copy':
                    if (navigator.clipboard && navigator.clipboard.write) {
                        navigator.clipboard.write([
                            new ClipboardItem({ 'image/png': blob })
                        ]).then(() => {
                            alert('Image copied to clipboard! You can paste it anywhere.');
                        }).catch(() => {
                            downloadImage(blob);
                            alert('Clipboard not available. Image downloaded instead.');
                        });
                    } else {
                        downloadImage(blob);
                        alert('Clipboard not available. Image downloaded instead.');
                    }
                    break;
                    
                case 'download':
                    downloadImage(blob);
                    break;
                    
                case 'whatsapp':
                    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent('Check out my withdrawal from NpLTrader!')}`;
                    window.open(whatsappUrl, '_blank');
                    setTimeout(() => downloadImage(blob), 500);
                    break;
                    
                case 'facebook':
                    const fbUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}`;
                    window.open(fbUrl, '_blank');
                    setTimeout(() => downloadImage(blob), 500);
                    break;
                    
                case 'twitter':
                    const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent('Check out my withdrawal from NpLTrader!')}&url=${encodeURIComponent(window.location.href)}`;
                    window.open(twitterUrl, '_blank');
                    setTimeout(() => downloadImage(blob), 500);
                    break;
            }
        }
        
        // Sidebar toggle functionality
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
        
        // Initialize sidebar on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const mainContent = document.querySelector('.main-content');
            
            if (!sidebar) return;
            
            // On desktop (width > 768px), sidebar is OPEN by default
            if (window.innerWidth > 768) {
                sidebar.classList.remove('closed');
                sidebar.classList.add('show');
                if (mainContent) {
                        mainContent.style.marginLeft = '280px';
                    }
                if (toggleBtn) {
                    toggleBtn.classList.remove('show');
                    toggleBtn.style.display = 'none';
                }
            } else {
                // On mobile, sidebar is CLOSED by default
                sidebar.classList.add('closed');
                sidebar.classList.remove('show');
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                }
                if (toggleBtn) {
                    toggleBtn.classList.add('show');
                    toggleBtn.style.display = 'block';
                }
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    if (!sidebar.classList.contains('closed')) {
                        if (mainContent) {
                            mainContent.style.marginLeft = '280px';
                        }
                        if (toggleBtn) {
                            toggleBtn.classList.remove('show');
                            toggleBtn.style.display = 'none';
                        }
                    }
                } else {
                    if (mainContent) {
                        mainContent.style.marginLeft = '0';
                    }
                    if (toggleBtn && sidebar.classList.contains('closed')) {
                        toggleBtn.classList.add('show');
                        toggleBtn.style.display = 'block';
                    }
                }
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggleBtn = toggleBtn && toggleBtn.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggleBtn && !sidebar.classList.contains('closed')) {
                        sidebar.classList.add('closed');
                        if (toggleBtn) {
                            toggleBtn.classList.add('show');
                            toggleBtn.style.display = 'block';
                        }
                    }
                }
            });
        });
        
        // Share Withdrawal Functionality - Duplicate removed, using the one above
            const amount = currentWithdrawalData.amount || 0;
            const currency = currentWithdrawalData.currency || 'USD';
            
            // Format amount with proper spacing
            const formattedAmount = amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('shareCardAmount').textContent = `+$${formattedAmount}`;
            document.getElementById('shareCardCurrency').textContent = currency;
            document.getElementById('shareCardAccount').textContent = currentWithdrawalData.account || 'N/A';
            document.getElementById('shareCardPlatform').textContent = currentWithdrawalData.platform || 'N/A';
            document.getElementById('shareCardDate').textContent = currentWithdrawalData.date || '';
            
            // Username is already set in PHP, but ensure it's visible
            const usernameEl = document.getElementById('shareCardUsername');
            if (usernameEl) {
                usernameEl.style.display = 'block';
                usernameEl.style.visibility = 'visible';
                usernameEl.style.opacity = '1';
            }
            
            const now = new Date();
            const timestamp = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' + 
                            now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            document.getElementById('shareCardTimestamp').textContent = timestamp;
            
            // Update verification link with withdrawal ID
            const verificationLink = document.getElementById('verificationLink');
            if (verificationLink && currentWithdrawalData.id) {
                verificationLink.href = `https://www.npltrader.com/verify?id=${currentWithdrawalData.id}`;
            }
            
            // Update card dimensions based on format
            updateCardDimensions();
        }
        
        // Format selection
        document.addEventListener('click', function(e) {
            if (e.target.closest('.format-btn')) {
                const btn = e.target.closest('.format-btn');
                document.querySelectorAll('.format-btn').forEach(b => {
                    b.classList.remove('active', 'btn-warning');
                    b.classList.add('btn-outline-warning');
                });
                btn.classList.add('active', 'btn-warning');
                btn.classList.remove('btn-outline-warning');
                
                currentFormat = btn.getAttribute('data-format');
                updateCardDimensions();
            }
        });
        
        // Update card dimensions
        function updateCardDimensions() {
            const card = document.getElementById('shareCardPreview');
            const formatBtn = document.querySelector(`.format-btn[data-format="${currentFormat}"]`);
            
            if (formatBtn) {
                const width = formatBtn.getAttribute('data-width');
                const height = formatBtn.getAttribute('data-height');
                
                // Calculate aspect ratio
                const aspectRatio = parseInt(width) / parseInt(height);
                const maxWidth = 600; // Max width for preview
                const calculatedHeight = maxWidth / aspectRatio;
                
                card.style.width = maxWidth + 'px';
                card.style.height = calculatedHeight + 'px';
                card.style.minHeight = calculatedHeight + 'px';
            }
        }
        
        // Download share card
        // Old duplicate handlers removed - using event delegation handlers defined above
    </script>
</body>
</html>
