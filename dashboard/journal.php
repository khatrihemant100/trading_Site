<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__.'/../config/database.php';

$message = '';
$message_type = '';

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Account Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_account') {
    try {
        $account_name = trim($_POST['account_name']);
        $account_type = $_POST['account_type'];
        $broker_name = trim($_POST['broker_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $initial_balance = floatval($_POST['initial_balance']);
        $target_amount = !empty($_POST['target_amount']) ? floatval($_POST['target_amount']) : null;
        $currency = $_POST['currency'] ?? 'USD';
        $leverage = trim($_POST['leverage'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO trading_accounts 
            (user_id, account_name, account_type, broker_name, account_number, initial_balance, current_balance, target_amount, currency, leverage, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], 
            $account_name, 
            $account_type, 
            $broker_name, 
            $account_number, 
            $initial_balance, 
            $initial_balance, 
            $target_amount, 
            $currency, 
            $leverage, 
            $notes
        ]);
        $message = "Account सफलतापूर्वक बनाइयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle Account Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_account') {
    try {
        $account_id = intval($_POST['account_id']);
        $account_name = trim($_POST['account_name']);
        $account_type = $_POST['account_type'];
        $broker_name = trim($_POST['broker_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $initial_balance = floatval($_POST['initial_balance']);
        $target_amount = !empty($_POST['target_amount']) ? floatval($_POST['target_amount']) : null;
        $currency = $_POST['currency'] ?? 'USD';
        $leverage = trim($_POST['leverage'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $notes = trim($_POST['notes'] ?? '');
        
        // Recalculate current balance
        $balance_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(profit_loss), 0) as total_pl 
            FROM trading_journal 
            WHERE account_id = ? AND profit_loss IS NOT NULL
        ");
        $balance_stmt->execute([$account_id]);
        $balance_data = $balance_stmt->fetch(PDO::FETCH_ASSOC);
        $current_balance = $initial_balance + floatval($balance_data['total_pl']);
        
        $stmt = $pdo->prepare("
            UPDATE trading_accounts 
            SET account_name = ?, account_type = ?, broker_name = ?, account_number = ?, 
                initial_balance = ?, current_balance = ?, target_amount = ?, currency = ?, 
                leverage = ?, status = ?, notes = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $account_name, $account_type, $broker_name, $account_number,
            $initial_balance, $current_balance, $target_amount, $currency,
            $leverage, $status, $notes, $account_id, $_SESSION['user_id']
        ]);
        $message = "Account सफलतापूर्वक अपडेट भयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle Account Deletion
if (isset($_GET['delete_account'])) {
    try {
        $account_id = intval($_GET['delete_account']);
        $stmt = $pdo->prepare("DELETE FROM trading_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $_SESSION['user_id']]);
        $message = "Account सफलतापूर्वक मेटाइयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle Trade Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_trade') {
    try {
        $account_id = !empty($_POST['account_id']) ? intval($_POST['account_id']) : null;
        $symbol = trim($_POST['symbol']);
        $trade_type = $_POST['trade_type'];
        $quantity = floatval($_POST['quantity']);
        $entry_price = floatval($_POST['entry_price']);
        $exit_price = !empty($_POST['exit_price']) ? floatval($_POST['exit_price']) : null;
        $trade_date = $_POST['trade_date'];
        $notes = trim($_POST['notes'] ?? '');
        
        // Calculate profit/loss
        $profit_loss = null;
        if ($exit_price !== null && $exit_price > 0) {
            if ($trade_type === 'buy') {
                $profit_loss = ($exit_price - $entry_price) * $quantity;
            } else {
                $profit_loss = ($entry_price - $exit_price) * $quantity;
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO trading_journal 
            (user_id, account_id, symbol, trade_type, quantity, entry_price, exit_price, trade_date, profit_loss, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $account_id, $symbol, $trade_type, 
            $quantity, $entry_price, $exit_price, $trade_date, $profit_loss, $notes
        ]);
        
        // Update account current balance
        if ($account_id && $profit_loss !== null) {
            $update_stmt = $pdo->prepare("
                UPDATE trading_accounts 
                SET current_balance = initial_balance + (
                    SELECT COALESCE(SUM(profit_loss), 0) 
                    FROM trading_journal 
                    WHERE account_id = ? AND profit_loss IS NOT NULL
                )
                WHERE id = ?
            ");
            $update_stmt->execute([$account_id, $account_id]);
        }
        
        $message = "Trade सफलतापूर्वक सेभ भयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle Trade Deletion
if (isset($_GET['delete_trade'])) {
    try {
        $trade_id = intval($_GET['delete_trade']);
        // Get account_id before deleting
        $get_stmt = $pdo->prepare("SELECT account_id FROM trading_journal WHERE id = ? AND user_id = ?");
        $get_stmt->execute([$trade_id, $_SESSION['user_id']]);
        $trade_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("DELETE FROM trading_journal WHERE id = ? AND user_id = ?");
        $stmt->execute([$trade_id, $_SESSION['user_id']]);
        
        // Update account balance
        if ($trade_data && $trade_data['account_id']) {
            $update_stmt = $pdo->prepare("
                UPDATE trading_accounts 
                SET current_balance = initial_balance + (
                    SELECT COALESCE(SUM(profit_loss), 0) 
                    FROM trading_journal 
                    WHERE account_id = ? AND profit_loss IS NOT NULL
                )
                WHERE id = ?
            ");
            $update_stmt->execute([$trade_data['account_id'], $trade_data['account_id']]);
        }
        
        $message = "Trade सफलतापूर्वक मेटाइयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get selected account
$selected_account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;

// Fetch all accounts
$accounts_stmt = $pdo->prepare("SELECT * FROM trading_accounts WHERE user_id = ? ORDER BY created_at DESC");
$accounts_stmt->execute([$_SESSION['user_id']]);
$accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch journal entries (filtered by account if selected)
if ($selected_account_id) {
    $journal_stmt = $pdo->prepare("
        SELECT j.*, a.account_name, a.account_type 
        FROM trading_journal j 
        LEFT JOIN trading_accounts a ON j.account_id = a.id 
        WHERE j.user_id = ? AND j.account_id = ? 
        ORDER BY j.trade_date DESC
    ");
    $journal_stmt->execute([$_SESSION['user_id'], $selected_account_id]);
} else {
    $journal_stmt = $pdo->prepare("
        SELECT j.*, a.account_name, a.account_type 
        FROM trading_journal j 
        LEFT JOIN trading_accounts a ON j.account_id = a.id 
        WHERE j.user_id = ? 
        ORDER BY j.trade_date DESC
    ");
    $journal_stmt->execute([$_SESSION['user_id']]);
}
$journal_entries = $journal_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get account statistics for selected account
$account_stats = null;
if ($selected_account_id) {
    $stats_stmt = $pdo->prepare("
        SELECT 
            a.*,
            COUNT(j.id) as total_trades,
            SUM(CASE WHEN j.profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(CASE WHEN j.profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades,
            COALESCE(SUM(j.profit_loss), 0) as total_pl,
            COALESCE(AVG(CASE WHEN j.profit_loss > 0 THEN j.profit_loss END), 0) as avg_win,
            COALESCE(AVG(CASE WHEN j.profit_loss < 0 THEN j.profit_loss END), 0) as avg_loss
        FROM trading_accounts a
        LEFT JOIN trading_journal j ON a.id = j.account_id
        WHERE a.id = ? AND a.user_id = ?
        GROUP BY a.id
    ");
    $stats_stmt->execute([$selected_account_id, $_SESSION['user_id']]);
    $account_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Journal - NpLTrader</title>
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
        
        .journal-card {
            background: var(--dark-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #334155;
        }
        
        .account-card {
            background: var(--dark-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .account-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        
        .account-card.active {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
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
        
        .chart-container {
            background: var(--dark-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .trade-profit { color: #10b981; font-weight: bold; }
        .trade-loss { color: #ef4444; font-weight: bold; }
        
        .account-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--dark-card);
            border-radius: 8px;
            padding: 15px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
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
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2">Trading Journal</h1>
                    <p class="text-muted">Manage accounts and track all your trades</p>
                </div>
                <div>
                    <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#createAccountModal">
                        <i class="fas fa-plus-circle me-2"></i>Create Account
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTradeModal">
                        <i class="fas fa-plus me-2"></i>Add Trade
                    </button>
                </div>
            </div>
            
            <!-- Accounts Section -->
            <?php if (!empty($accounts)): ?>
                <div class="mb-4">
                    <h3 class="h5 mb-3">Your Trading Accounts</h3>
                    <div class="row">
                        <?php foreach ($accounts as $account): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="account-card <?php echo ($selected_account_id == $account['id']) ? 'active' : ''; ?>" 
                                     onclick="window.location.href='?account_id=<?php echo $account['id']; ?>'">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($account['account_name']); ?></h5>
                                            <span class="account-type-badge account-type-<?php echo $account['account_type']; ?>">
                                                <?php echo ucfirst($account['account_type']); ?>
                                            </span>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-white" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation();">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="?account_id=<?php echo $account['id']; ?>">View</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="editAccount(<?php echo htmlspecialchars(json_encode($account)); ?>); return false;">Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?delete_account=<?php echo $account['id']; ?>" onclick="return confirm('Are you sure?')">Delete</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php if ($account['broker_name']): ?>
                                        <p class="text-muted small mb-2"><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($account['broker_name']); ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small">Current Balance</div>
                                            <div class="h5 mb-0"><?php echo $account['currency']; ?> <?php echo number_format($account['current_balance'], 2); ?></div>
                                        </div>
                                        <?php if ($account['target_amount']): ?>
                                            <div class="text-end">
                                                <div class="text-muted small">Target</div>
                                                <div class="h6 mb-0"><?php echo $account['currency']; ?> <?php echo number_format($account['target_amount'], 2); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                    $progress = $account['target_amount'] ? ($account['current_balance'] / $account['target_amount']) * 100 : 0;
                                    if ($account['target_amount'] && $progress > 0):
                                    ?>
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min($progress, 100); ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="journal-card text-center py-5">
                    <i class="fas fa-wallet fa-3x text-muted mb-3"></i>
                    <h4>No Trading Accounts Yet</h4>
                    <p class="text-muted">Create your first trading account to start tracking your trades</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAccountModal">
                        <i class="fas fa-plus-circle me-2"></i>Create Your First Account
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Account Statistics -->
            <?php if ($account_stats): ?>
                <div class="mb-4">
                    <h3 class="h5 mb-3">Account Statistics</h3>
                    <div class="account-stats-grid">
                        <div class="stat-card">
                            <div class="stat-value text-primary"><?php echo $account_stats['total_trades']; ?></div>
                            <div class="stat-label">Total Trades</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value text-success"><?php echo $account_stats['winning_trades']; ?></div>
                            <div class="stat-label">Winning Trades</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value text-danger"><?php echo $account_stats['losing_trades']; ?></div>
                            <div class="stat-label">Losing Trades</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value <?php echo $account_stats['total_pl'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $account_stats['currency']; ?> <?php echo number_format($account_stats['total_pl'], 2); ?>
                            </div>
                            <div class="stat-label">Total P/L</div>
                        </div>
                        <?php if ($account_stats['winning_trades'] > 0): ?>
                            <div class="stat-card">
                                <div class="stat-value text-success"><?php echo $account_stats['currency']; ?> <?php echo number_format($account_stats['avg_win'], 2); ?></div>
                                <div class="stat-label">Avg Win</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($account_stats['losing_trades'] > 0): ?>
                            <div class="stat-card">
                                <div class="stat-value text-danger"><?php echo $account_stats['currency']; ?> <?php echo number_format($account_stats['avg_loss'], 2); ?></div>
                                <div class="stat-label">Avg Loss</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="chart-container">
                            <h5 class="mb-3">Balance Over Time</h5>
                            <canvas id="balanceChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="journal-card text-center">
                        <h3 class="text-primary"><?php echo count($journal_entries); ?></h3>
                        <p class="text-muted mb-0">Total Trades</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="journal-card text-center">
                        <h3 class="text-success">
                            <?php
                            $winning_trades = array_filter($journal_entries, function($trade) {
                                return $trade['profit_loss'] > 0;
                            });
                            echo count($winning_trades);
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Winning Trades</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="journal-card text-center">
                        <h3 class="text-danger">
                            <?php
                            $losing_trades = array_filter($journal_entries, function($trade) {
                                return $trade['profit_loss'] < 0;
                            });
                            echo count($losing_trades);
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Losing Trades</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="journal-card text-center">
                        <h3 class="text-warning">
                            रु <?php
                            $total_pl = array_sum(array_column($journal_entries, 'profit_loss'));
                            echo number_format($total_pl, 2);
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Total P/L</p>
                    </div>
                </div>
            </div>

            <!-- Trades Table -->
            <div class="journal-card">
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <?php if (!$selected_account_id): ?><th>Account</th><?php endif; ?>
                                <th>Symbol</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Entry Price</th>
                                <th>Exit Price</th>
                                <th>P/L</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($journal_entries)): ?>
                                <tr>
                                    <td colspan="<?php echo $selected_account_id ? '8' : '9'; ?>" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                        No trades recorded yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($journal_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($entry['trade_date'])); ?></td>
                                        <?php if (!$selected_account_id): ?>
                                            <td>
                                                <?php if ($entry['account_name']): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($entry['account_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">No Account</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
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
                                                <span class="<?php echo $entry['profit_loss'] >= 0 ? 'trade-profit' : 'trade-loss'; ?>">
                                                    <?php echo $entry['profit_loss'] >= 0 ? '+' : ''; ?>रु <?php echo number_format($entry['profit_loss'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editTrade(<?php echo $entry['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteTrade(<?php echo $entry['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Trade Modal -->
    <div class="modal fade" id="addTradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Trade</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="tradeForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="add_trade">
                        <?php if (!empty($accounts)): ?>
                            <div class="mb-3">
                                <label class="form-label">Trading Account</label>
                                <select class="form-select" name="account_id" required>
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>" <?php echo ($selected_account_id == $acc['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($acc['account_name']); ?> (<?php echo ucfirst($acc['account_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Symbol</label>
                                <input type="text" class="form-control" name="symbol" placeholder="NTC, NBL, EURUSD" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trade Type</label>
                                <select class="form-select" name="trade_type" required>
                                    <option value="buy">Buy</option>
                                    <option value="sell">Sell</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trade Date</label>
                                <input type="date" class="form-control" name="trade_date" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Entry Price (रु)</label>
                                <input type="number" class="form-control" name="entry_price" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Exit Price (रु)</label>
                                <input type="number" class="form-control" name="exit_price" step="0.01">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Trade analysis..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="tradeForm" class="btn btn-primary">Save Trade</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Account Modal -->
    <div class="modal fade" id="createAccountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Create Trading Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="accountForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="create_account">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name *</label>
                                <input type="text" class="form-control" name="account_name" placeholder="My Forex Account" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Type *</label>
                                <select class="form-select" name="account_type" required>
                                    <option value="forex">Forex</option>
                                    <option value="propfirm">Prop Firm</option>
                                    <option value="nepse">NEPSE</option>
                                    <option value="crypto">Crypto</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Broker Name</label>
                                <input type="text" class="form-control" name="broker_name" placeholder="e.g., IC Markets, FTMO">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number" placeholder="Account ID/Number">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Initial Balance *</label>
                                <input type="number" class="form-control" name="initial_balance" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Target Amount</label>
                                <input type="number" class="form-control" name="target_amount" step="0.01" min="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Currency</label>
                                <select class="form-select" name="currency">
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="NPR">NPR</option>
                                    <option value="GBP">GBP</option>
                                    <option value="JPY">JPY</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Leverage</label>
                                <input type="text" class="form-control" name="leverage" placeholder="e.g., 1:100, 1:500">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about this account..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="accountForm" class="btn btn-primary">Create Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Account Modal -->
    <div class="modal fade" id="editAccountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Trading Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editAccountForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="update_account">
                        <input type="hidden" name="account_id" id="edit_account_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name *</label>
                                <input type="text" class="form-control" name="account_name" id="edit_account_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Type *</label>
                                <select class="form-select" name="account_type" id="edit_account_type" required>
                                    <option value="forex">Forex</option>
                                    <option value="propfirm">Prop Firm</option>
                                    <option value="nepse">NEPSE</option>
                                    <option value="crypto">Crypto</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Broker Name</label>
                                <input type="text" class="form-control" name="broker_name" id="edit_broker_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number" id="edit_account_number">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Initial Balance *</label>
                                <input type="number" class="form-control" name="initial_balance" id="edit_initial_balance" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Target Amount</label>
                                <input type="number" class="form-control" name="target_amount" id="edit_target_amount" step="0.01" min="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Currency</label>
                                <select class="form-select" name="currency" id="edit_currency">
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="NPR">NPR</option>
                                    <option value="GBP">GBP</option>
                                    <option value="JPY">JPY</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Leverage</label>
                                <input type="text" class="form-control" name="leverage" id="edit_leverage">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editAccountForm" class="btn btn-primary">Update Account</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
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
            
            // Highlight active sidebar link
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
        
        // Edit Account Function
        function editAccount(account) {
            document.getElementById('edit_account_id').value = account.id;
            document.getElementById('edit_account_name').value = account.account_name;
            document.getElementById('edit_account_type').value = account.account_type;
            document.getElementById('edit_broker_name').value = account.broker_name || '';
            document.getElementById('edit_account_number').value = account.account_number || '';
            document.getElementById('edit_initial_balance').value = account.initial_balance;
            document.getElementById('edit_target_amount').value = account.target_amount || '';
            document.getElementById('edit_currency').value = account.currency || 'USD';
            document.getElementById('edit_leverage').value = account.leverage || '';
            document.getElementById('edit_status').value = account.status || 'active';
            document.getElementById('edit_notes').value = account.notes || '';
            
            const editModal = new bootstrap.Modal(document.getElementById('editAccountModal'));
            editModal.show();
        }
        
        function editTrade(tradeId) {
            alert('Edit trade: ' + tradeId);
            // Implement edit functionality
        }
        
        function deleteTrade(tradeId) {
            if (confirm('Are you sure you want to delete this trade?')) {
                const accountId = <?php echo $selected_account_id ? $selected_account_id : 'null'; ?>;
                const url = accountId ? 'journal.php?delete_trade=' + tradeId + '&account_id=' + accountId : 'journal.php?delete_trade=' + tradeId;
                window.location.href = url;
            }
        }
        
        // Chart rendering
        <?php if ($account_stats && $selected_account_id): ?>
        const accountId = <?php echo $selected_account_id; ?>;
        const chartData = {
            labels: [],
            balances: []
        };
        
        <?php
        // Get balance history
        $history_stmt = $pdo->prepare("
            SELECT 
                DATE(trade_date) as date,
                ? + COALESCE(SUM(profit_loss) OVER (ORDER BY trade_date), 0) as balance
            FROM trading_journal
            WHERE account_id = ? AND profit_loss IS NOT NULL
            ORDER BY trade_date
        ");
        $history_stmt->execute([$account_stats['initial_balance'], $selected_account_id]);
        $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $dates = [];
        $balances = [];
        
        // Add initial balance point
        $dates[] = date('M d', strtotime($account_stats['created_at']));
        $balances[] = floatval($account_stats['initial_balance']);
        
        if (!empty($history)) {
            foreach ($history as $h) {
                $dates[] = date('M d', strtotime($h['date']));
                $balances[] = floatval($h['balance']);
            }
        }
        ?>
        
        chartData.labels = <?php echo json_encode($dates); ?>;
        chartData.balances = <?php echo json_encode($balances); ?>;
        
        const ctx = document.getElementById('balanceChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Account Balance',
                        data: chartData.balances,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                color: '#94a3b8'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                color: '#94a3b8',
                                callback: function(value) {
                                    return '<?php echo $account_stats['currency']; ?> ' + value.toLocaleString();
                                }
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
        
        // Set today's date as default
        const tradeDateInput = document.querySelector('input[name="trade_date"]');
        if (tradeDateInput) {
            tradeDateInput.valueAsDate = new Date();
        }
    </script>
</body>
</html>