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
        
        /* Show text arrow by default - it's more reliable */
        .sidebar-close i.fa-angle-left {
            display: none !important;
        }
        
        /* Only show icon if explicitly enabled via JS */
        .sidebar-close.show-icon i.fa-angle-left {
            display: inline-block !important;
        }
        
        .sidebar-close.show-icon .close-arrow {
            display: none !important;
        }
        
        .sidebar.closed .sidebar-close i {
            transform: rotate(180deg);
        }
        
        /* Sidebar Toggle Button (when sidebar is closed) */
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
        
        /* Other Nav Links - Smaller Size */
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
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .summary-card:hover::before {
            left: 100%;
        }
        
        .summary-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--primary);
            box-shadow: 0 12px 32px rgba(16, 185, 129, 0.3);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            transition: all 0.3s;
        }
        
        .summary-card:hover .summary-card-icon {
            background-color: var(--primary);
            color: white;
            transform: rotate(360deg) scale(1.1);
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.6s ease-out;
        }
        
        .content-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
            transform: translateY(-4px);
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .quick-action-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 20px;
            transform: translateY(-50%) translateX(10px);
            width: 0;
            height: 0;
            border-left: 8px solid white;
            border-top: 6px solid transparent;
            border-bottom: 6px solid transparent;
            opacity: 0;
            transition: all 0.3s;
        }
        
        .quick-action-btn:hover {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-color: var(--primary);
            color: white;
            transform: translateX(10px) scale(1.02);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        
        .quick-action-btn:hover::after {
            opacity: 1;
            transform: translateY(-50%) translateX(0);
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background-color: var(--dark-hover);
            border-left-color: var(--primary);
            transform: translateX(5px);
            box-shadow: -4px 0 12px rgba(16, 185, 129, 0.2);
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
        
        /* Section Blocks */
        .dashboard-section {
            display: none;
            margin-top: 2rem;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }
        
        .dashboard-section.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        .section-header {
            margin-bottom: 1.5rem;
        }
        
        .section-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .metric-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.2);
            color: var(--text-primary);
            font-size: 0.8rem;
            margin-right: 8px;
        }
        
        .portfolio-holding {
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .portfolio-holding:last-child {
            border-bottom: none;
        }
        
        .portfolio-holding strong {
            color: var(--text-primary);
        }
        
        .progress {
            height: 8px;
            background-color: var(--dark-hover);
            border-radius: 20px;
            overflow: hidden;
            margin-top: 6px;
        }
        
        .progress-bar-custom {
            height: 100%;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }
        
        .community-item, .mindset-item {
            padding: 18px;
            border-radius: 12px;
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            margin-bottom: 14px;
            transition: all 0.3s ease;
        }
        
        .community-item:hover, .mindset-item:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.15);
        }
        
        .community-item h6, .mindset-item h6 {
            margin-bottom: 6px;
            color: var(--text-primary);
        }
        
        .community-item p, .mindset-item p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
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
        
        /* When sidebar is closed, adjust main content */
        .sidebar.closed ~ .main-content {
            margin-left: 0;
        }
        
        /* When sidebar is open, adjust main content */
        .sidebar.show ~ .main-content {
            margin-left: 280px;
        }
        
        @media (max-width: 768px) {
            .sidebar.show ~ .main-content {
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
        
        /* Trader Feature Cards */
        .trader-feature-card {
            background: linear-gradient(135deg, var(--dark-card) 0%, var(--dark-hover) 100%);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            height: 100%;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        
        .trader-feature-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s;
        }
        
        .trader-feature-card:hover::before {
            opacity: 1;
        }
        
        .trader-feature-card:hover {
            transform: translateY(-8px) scale(1.03);
            border-color: var(--primary);
            box-shadow: 0 16px 40px rgba(16, 185, 129, 0.3);
        }
        
        .trader-feature-card:hover .trader-feature-title {
            color: var(--primary);
        }
        
        .trader-feature-card:hover .trader-feature-desc {
            color: var(--text-primary);
        }
        
        .trader-feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 16px;
            transition: all 0.4s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .trader-feature-card:hover .trader-feature-icon {
            transform: rotate(10deg) scale(1.1);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.5);
        }
        
        .trader-feature-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .trader-feature-desc {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        /* Win Rate Badge */
        .win-rate-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--primary);
            border-radius: 20px;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        /* Stats Grid Animation */
        .stats-grid {
            animation: fadeInUp 0.8s ease-out;
        }
        
        .stats-grid .col-md-3:nth-child(1) { animation-delay: 0.1s; }
        .stats-grid .col-md-3:nth-child(2) { animation-delay: 0.2s; }
        .stats-grid .col-md-3:nth-child(3) { animation-delay: 0.3s; }
        .stats-grid .col-md-3:nth-child(4) { animation-delay: 0.4s; }
        
        /* Trader Feature Cards Animation */
        .trader-feature-card:nth-child(1) { animation-delay: 0.1s; }
        .trader-feature-card:nth-child(2) { animation-delay: 0.2s; }
        .trader-feature-card:nth-child(3) { animation-delay: 0.3s; }
        .trader-feature-card:nth-child(4) { animation-delay: 0.4s; }
        
        /* Pulse Animation for Important Stats */
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
            }
        }
        
        .summary-card.highlight {
            animation: pulse-glow 2s infinite;
        }
        
        /* Loading Animation */
        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }
        
        .shimmer {
            background: linear-gradient(90deg, var(--dark-card) 0%, var(--dark-hover) 50%, var(--dark-card) 100%);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite;
        }
        
        /* Live Price Cards */
        .price-card {
            background: linear-gradient(135deg, var(--dark-card) 0%, var(--dark-hover) 100%);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .price-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            transition: left 0.6s;
        }
        
        .price-card:hover::before {
            left: 100%;
        }
        
        .price-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
        }
        
        .gold-card {
            border-color: #fbbf24;
        }
        
        .gold-card:hover {
            border-color: #f59e0b;
            box-shadow: 0 16px 40px rgba(251, 191, 36, 0.3);
        }
        
        .nepse-card {
            border-color: #3b82f6;
        }
        
        .nepse-card:hover {
            border-color: #2563eb;
            box-shadow: 0 16px 40px rgba(59, 130, 246, 0.3);
        }
        
        .btc-card {
            border-color: #f59e0b;
        }
        
        .btc-card:hover {
            border-color: #d97706;
            box-shadow: 0 16px 40px rgba(245, 158, 11, 0.3);
        }
        
        .price-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .price-card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .gold-icon {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        }
        
        .nepse-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        
        .btc-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .price-card-title h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .price-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .price-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            min-height: 50px;
            display: flex;
            align-items: center;
        }
        
        .price-change {
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .price-change.positive {
            color: var(--primary);
        }
        
        .price-change.negative {
            color: #ef4444;
        }
        
        .price-update-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 8px;
            font-style: italic;
        }
        
        .loading-spinner {
            color: var(--text-secondary);
        }
        
        /* TradingView Widget Wrapper - Clips bottom copyright area */
        .tradingview-widget-wrapper {
            position: relative;
            height: 180px;
            overflow: hidden;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        /* TradingView Widget Styles */
        .tradingview-widget-container {
            border-radius: 8px;
            background: transparent;
            position: relative;
            height: 100%;
            overflow: hidden;
            /* Clip bottom 30px where copyright appears */
            clip-path: inset(0 0 30px 0);
            -webkit-clip-path: inset(0 0 30px 0);
        }
        
        .tradingview-widget-container__widget {
            width: 100%;
            height: 100%;
        }
        
        /* Hide TradingView logo and copyright using wrapper overflow */
        .tradingview-widget-wrapper iframe {
            position: relative;
            /* Move up to hide bottom copyright */
            margin-bottom: -30px;
        }
        
        /* Additional hiding for any visible elements */
        .tradingview-widget-wrapper .tradingview-widget-copyright,
        .tradingview-widget-wrapper a[href*="tradingview.com"],
        .tradingview-widget-wrapper div[class*="copyright"],
        .tradingview-widget-wrapper span[class*="trademark"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            position: absolute !important;
            bottom: -100px !important;
        }
        
        /* Motivation Card */
        .motivation-card {
            background: linear-gradient(135deg, var(--dark-card) 0%, rgba(16, 185, 129, 0.1) 100%);
            border: 2px solid var(--primary);
            border-radius: 20px;
            padding: 32px;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }
        
        .motivation-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
            animation: pulse-glow 4s infinite;
        }
        
        .motivation-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }
        
        .motivation-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .motivation-title {
            flex: 1;
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .btn-refresh-motivation {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-refresh-motivation:hover {
            transform: rotate(180deg) scale(1.1);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
        }
        
        .motivation-content {
            position: relative;
            z-index: 1;
        }
        
        .motivation-text {
            font-size: 1.2rem;
            line-height: 1.8;
            color: var(--text-primary);
            margin: 0;
            font-style: italic;
            text-align: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            transition: all 0.4s;
        }
        
        .motivation-text.fade-out {
            opacity: 0;
            transform: translateY(-10px);
        }
        
        .motivation-text.fade-in {
            opacity: 1;
            transform: translateY(0);
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
                    $profile_image_path = $profile_image ? __DIR__.'/../' . $profile_image : null;
                    ?>
                    <div class="dropdown me-3">
                        <button class="btn btn-link text-decoration-none dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" style="color: var(--primary) !important; padding: 0;">
                            <?php if (!empty($profile_image) && $profile_image_path && file_exists($profile_image_path)): ?>
                                <img src="../<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; border: 2px solid var(--primary);">
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

    <!-- Sidebar Toggle Button (shown when sidebar is closed) -->
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
                <a href="dashboard.php" class="nav-link active dashboard-link">
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
                <a href="calculator.php" class="nav-link">
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
                    $profile_image_path = $profile_image ? __DIR__.'/../' . $profile_image : null;
                    if (!empty($profile_image) && $profile_image_path && file_exists($profile_image_path)): 
                    ?>
                        <img src="../<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
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
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="btn-icon d-md-none" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="ms-auto top-actions">
                <a href="../user/profile.php" class="btn-icon" title="Profile">
                    <i class="fas fa-user"></i>
                </a>
                <a href="../course/course.php" class="btn-icon" title="Courses">
                    <i class="fas fa-book"></i>
                </a>
                <?php if ($user['role'] === 'admin'): ?>
                <a href="../admin/dashboard.php" class="btn-icon" title="Admin Panel">
                    <i class="fas fa-shield-alt"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>


        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">Welcome back, <?php echo htmlspecialchars($user['username']); ?>! 👋</h1>
            <p class="dashboard-subtitle">Track live market prices and stay motivated on your trading journey.</p>
        </div>

        <!-- Live Market Prices -->
        <div class="row g-4 mb-4">
            <div class="col-md-4 col-sm-6">
                <div class="price-card gold-card">
                    <div class="price-card-header">
                        <div class="price-card-icon gold-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="price-card-title">
                            <h5>Gold (XAU/USD)</h5>
                            <span class="price-label">Live Spot Price</span>
                        </div>
                    </div>
                    <div class="tradingview-widget-wrapper">
                        <div class="tradingview-widget-container" style="height: 180px;">
                            <div class="tradingview-widget-container__widget"></div>
                            <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-mini-symbol-overview.js" async>
                            {
                            "symbol": "OANDA:XAUUSD",
                            "chartOnly": false,
                            "dateRange": "1D",
                            "trendLineColor": "rgba(255, 152, 0, 1)",
                            "noTimeScale": false,
                            "colorTheme": "dark",
                            "isTransparent": true,
                            "locale": "en",
                            "width": "100%",
                            "autosize": true,
                            "height": "100%"
                            }
                            </script>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 col-sm-6">
                <div class="price-card nepse-card">
                    <div class="price-card-header">
                        <div class="price-card-icon nepse-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="price-card-title">
                            <h5>NEPSE Index</h5>
                            <span class="price-label">Nepal Stock Exchange</span>
                        </div>
                    </div>
                    <div class="price-value" id="nepsePrice">
                        <span class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</span>
                    </div>
                    <div class="price-change" id="nepseChange">
                        <span class="loading-spinner"><i class="fas fa-spinner fa-spin"></i></span>
                    </div>
                    <div class="price-update-time" id="nepseUpdateTime"></div>
                    <div style="margin-top: 8px;">
                        <a href="https://nepsealpha.com/live-market" target="_blank" style="color: var(--text-secondary); font-size: 0.75rem; text-decoration: none;">
                            <i class="fas fa-external-link-alt"></i> View Live Market
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 col-sm-6">
                <div class="price-card btc-card">
                    <div class="price-card-header">
                        <div class="price-card-icon btc-icon">
                            <i class="fab fa-bitcoin"></i>
                        </div>
                        <div class="price-card-title">
                            <h5>Bitcoin (BTC)</h5>
                            <span class="price-label">Cryptocurrency</span>
                        </div>
                    </div>
                    <div class="tradingview-widget-wrapper">
                        <div class="tradingview-widget-container" style="height: 180px;">
                            <div class="tradingview-widget-container__widget"></div>
                            <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-mini-symbol-overview.js" async>
                            {
                            "symbol": "BINANCE:BTCUSDT",
                            "chartOnly": false,
                            "dateRange": "1D",
                            "trendLineColor": "rgba(255, 152, 0, 1)",
                            "noTimeScale": false,
                            "colorTheme": "dark",
                            "isTransparent": true,
                            "locale": "en",
                            "width": "100%",
                            "autosize": true,
                            "height": "100%"
                            }
                            </script>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Motivation Section -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="motivation-card">
                    <div class="motivation-header">
                        <div class="motivation-icon">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <h4 class="motivation-title">Daily Trading Motivation</h4>
                        <button class="btn-refresh-motivation" onclick="changeMotivation()" title="Get New Motivation">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="motivation-content">
                        <p class="motivation-text" id="motivationText">
                            <?php
                            $motivations = [
                                "The stock market is filled with individuals who know the price of everything, but the value of nothing. - Philip Fisher",
                                "The goal of a successful trader is to make the best trades. Money is secondary. - Alexander Elder",
                                "In trading, just like in life, you have to work with probabilities, not certainties. - Unknown",
                                "The most important quality for an investor is temperament, not intellect. - Warren Buffett",
                                "Risk comes from not knowing what you're doing. - Warren Buffett",
                                "The market can remain irrational longer than you can remain solvent. - John Maynard Keynes",
                                "Trading is 80% psychology and 20% method. Master your mind, master the markets.",
                                "The best time to plant a tree was 20 years ago. The second best time is now. Start your trading journey today.",
                                "Success in trading is not about being right all the time, it's about managing risk and staying disciplined.",
                                "The difference between successful traders and others is not the absence of fear, but the mastery of it.",
                                "Every expert was once a beginner. Every pro was once an amateur. Keep learning, keep growing.",
                                "The market is a voting machine in the short run, but a weighing machine in the long run. - Benjamin Graham",
                                "Patience is not the ability to wait, but the ability to keep a good attitude while waiting. - Unknown",
                                "The biggest risk is not taking any risk. In a world that's changing quickly, the only strategy that is guaranteed to fail is not taking risks. - Mark Zuckerberg",
                                "Your trading plan is your roadmap to success. Follow it religiously, adjust it wisely.",
                                "Losses are tuition fees in the school of trading. Learn from them, don't fear them.",
                                "The best traders are not the ones who make the most money, but the ones who lose the least when they're wrong.",
                                "Consistency beats intensity. Small, consistent gains compound into significant wealth over time.",
                                "Emotion is the enemy of the trader. Discipline is your best friend.",
                                "The market doesn't care about your feelings. Trade the charts, not your emotions.",
                                "Price is what you pay. Value is what you get. - Warren Buffett",
                                "The four most dangerous words in investing are: 'This time it's different.' - Sir John Templeton",
                                "In the short run, the market is a voting machine, but in the long run, it is a weighing machine. - Benjamin Graham",
                                "Rule No. 1: Never lose money. Rule No. 2: Never forget rule No. 1. - Warren Buffett",
                                "The stock market is a device for transferring money from the impatient to the patient. - Warren Buffett"
                            ];
                            echo $motivations[array_rand($motivations)];
                            ?>
                        </p>
                    </div>
                </div>
            </div>
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
        
        // Simple and reliable toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const mainContent = document.querySelector('.main-content');
            
            if (!sidebar) {
                console.error('Sidebar not found!');
                return;
            }
            
            // Simple check: if sidebar has 'closed' class, open it. Otherwise, close it.
            if (sidebar.classList.contains('closed')) {
                // OPEN SIDEBAR
                sidebar.classList.remove('closed');
                sidebar.classList.add('show');
                sidebar.style.setProperty('transform', 'translateX(0)', 'important');
                sidebar.style.setProperty('display', 'block', 'important');
                
                // Adjust main content
                if (mainContent && window.innerWidth > 768) {
                    mainContent.style.marginLeft = '280px';
                }
                
                // Hide toggle button
                if (toggleBtn) {
                    toggleBtn.classList.remove('show');
                    toggleBtn.style.setProperty('display', 'none', 'important');
                }
            } else {
                // CLOSE SIDEBAR
                sidebar.classList.add('closed');
                sidebar.classList.remove('show');
                sidebar.style.setProperty('transform', 'translateX(-100%)', 'important');
                
                // Adjust main content
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                }
                
                // Show toggle button
                if (toggleBtn) {
                    toggleBtn.classList.add('show');
                    toggleBtn.style.setProperty('display', 'block', 'important');
                }
            }
        }
        
        // Initialize sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const mainContent = document.querySelector('.main-content');
            
            if (!sidebar) return;
            
            // On desktop (width > 768px), sidebar is OPEN by default
            if (window.innerWidth > 768) {
                sidebar.classList.remove('closed');
                sidebar.classList.add('show');
                sidebar.style.setProperty('transform', 'translateX(0)', 'important');
                sidebar.style.setProperty('display', 'block', 'important');
                
                if (mainContent) {
                    mainContent.style.marginLeft = '280px';
                }
                
                if (toggleBtn) {
                    toggleBtn.classList.remove('show');
                    toggleBtn.style.setProperty('display', 'none', 'important');
                }
            } else {
                // On mobile, sidebar is CLOSED by default
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
                    // Desktop: show sidebar if not manually closed
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
                    // Mobile: always hide sidebar
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
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggleBtn = toggleBtn && toggleBtn.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggleBtn && !sidebar.classList.contains('closed')) {
                        toggleSidebar(); // Use the toggle function
                    }
                }
            });
        });
        
        // Highlight active sidebar link based on current page
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
        
        
        // Add smooth scroll animations on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Observe all cards
            document.querySelectorAll('.price-card, .motivation-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                observer.observe(card);
            });
            
            // Load NEPSE price (Gold and Bitcoin use TradingView widgets)
            loadNEPSEPrice();
            // Update NEPSE price every 30 seconds
            setInterval(loadNEPSEPrice, 30000);
            
            // Hide TradingView copyright/logo after widgets load
            setTimeout(() => {
                const widgets = document.querySelectorAll('.tradingview-widget-container');
                widgets.forEach(widget => {
                    // Hide copyright links
                    const links = widget.querySelectorAll('a[href*="tradingview.com"]');
                    links.forEach(link => {
                        link.style.display = 'none';
                        link.parentElement && (link.parentElement.style.display = 'none');
                    });
                    
                    // Hide copyright text
                    const copyright = widget.querySelector('.tradingview-widget-copyright');
                    if (copyright) {
                        copyright.style.display = 'none';
                    }
                    
                    // Hide any text containing "TradingView"
                    const allElements = widget.querySelectorAll('*');
                    allElements.forEach(el => {
                        if (el.textContent && el.textContent.includes('TradingView') && el.tagName !== 'SCRIPT') {
                            el.style.display = 'none';
                        }
                    });
                });
            }, 2000);
            
            // Also hide on iframe load using MutationObserver
            const tvObserver = new MutationObserver(() => {
                document.querySelectorAll('.tradingview-widget-container a[href*="tradingview.com"]').forEach(el => {
                    el.style.display = 'none';
                    el.parentElement && (el.parentElement.style.display = 'none');
                });
                document.querySelectorAll('.tradingview-widget-copyright').forEach(el => {
                    el.style.display = 'none';
                });
            });
            
            document.querySelectorAll('.tradingview-widget-container').forEach(container => {
                tvObserver.observe(container, { childList: true, subtree: true });
            });
        });
        
        // Load NEPSE Index Price
        function loadNEPSEPrice() {
            // NEPSE Index - Fetching from our backend API
            fetch('../api/nepse.php')
                .then(response => response.json())
                .then(data => {
                    if (data && data.success) {
                        const value = parseFloat(data.index).toFixed(2);
                        const change = parseFloat(data.change || 0).toFixed(2);
                        const changePercent = parseFloat(data.changePercent || 0).toFixed(2);
                        const isPositive = change >= 0;
                        
                        document.getElementById('nepsePrice').innerHTML = value;
                        document.getElementById('nepseChange').innerHTML = `
                            <i class="fas fa-arrow-${isPositive ? 'up' : 'down'}"></i>
                            <span class="${isPositive ? 'positive' : 'negative'}">${isPositive ? '+' : ''}${change} (${isPositive ? '+' : ''}${changePercent}%)</span>
                        `;
                        document.getElementById('nepseUpdateTime').textContent = 'Updated: ' + new Date().toLocaleTimeString();
                    } else {
                        throw new Error('NEPSE data not available');
                    }
                })
                .catch(() => {
                    // Fallback: Show link to nepsealpha.com
                    document.getElementById('nepsePrice').innerHTML = '<a href="https://nepsealpha.com/live-market" target="_blank" style="color: var(--primary); text-decoration: none;">View Live Market →</a>';
                    document.getElementById('nepseChange').innerHTML = '<span style="color: var(--text-secondary);">Click to view</span>';
                    document.getElementById('nepseUpdateTime').textContent = 'Visit nepsealpha.com for live data';
                });
        }
        
        // Change Motivation Function
        function changeMotivation() {
            const motivations = [
                "The stock market is filled with individuals who know the price of everything, but the value of nothing. - Philip Fisher",
                "The goal of a successful trader is to make the best trades. Money is secondary. - Alexander Elder",
                "In trading, just like in life, you have to work with probabilities, not certainties. - Unknown",
                "The most important quality for an investor is temperament, not intellect. - Warren Buffett",
                "Risk comes from not knowing what you're doing. - Warren Buffett",
                "The market can remain irrational longer than you can remain solvent. - John Maynard Keynes",
                "Trading is 80% psychology and 20% method. Master your mind, master the markets.",
                "The best time to plant a tree was 20 years ago. The second best time is now. Start your trading journey today.",
                "Success in trading is not about being right all the time, it's about managing risk and staying disciplined.",
                "The difference between successful traders and others is not the absence of fear, but the mastery of it.",
                "Every expert was once a beginner. Every pro was once an amateur. Keep learning, keep growing.",
                "The market is a voting machine in the short run, but a weighing machine in the long run. - Benjamin Graham",
                "Patience is not the ability to wait, but the ability to keep a good attitude while waiting. - Unknown",
                "The biggest risk is not taking any risk. In a world that's changing quickly, the only strategy that is guaranteed to fail is not taking risks. - Mark Zuckerberg",
                "Your trading plan is your roadmap to success. Follow it religiously, adjust it wisely.",
                "Losses are tuition fees in the school of trading. Learn from them, don't fear them.",
                "The best traders are not the ones who make the most money, but the ones who lose the least when they're wrong.",
                "Consistency beats intensity. Small, consistent gains compound into significant wealth over time.",
                "Emotion is the enemy of the trader. Discipline is your best friend.",
                "The market doesn't care about your feelings. Trade the charts, not your emotions.",
                "Price is what you pay. Value is what you get. - Warren Buffett",
                "The four most dangerous words in investing are: 'This time it's different.' - Sir John Templeton",
                "In the short run, the market is a voting machine, but in the long run, it is a weighing machine. - Benjamin Graham",
                "Rule No. 1: Never lose money. Rule No. 2: Never forget rule No. 1. - Warren Buffett",
                "The stock market is a device for transferring money from the impatient to the patient. - Warren Buffett"
            ];
            
            const motivationText = document.getElementById('motivationText');
            motivationText.classList.add('fade-out');
            
            setTimeout(() => {
                const randomMotivation = motivations[Math.floor(Math.random() * motivations.length)];
                motivationText.textContent = randomMotivation;
                motivationText.classList.remove('fade-out');
                motivationText.classList.add('fade-in');
                
                setTimeout(() => {
                    motivationText.classList.remove('fade-in');
                }, 400);
            }, 300);
        }
        
    </script>
    
    <!-- AI Chat Widget -->
    <?php include '../includes/ai-chat-widget.php'; ?>
</body>
</html>

