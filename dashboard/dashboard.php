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
            <p class="dashboard-subtitle">Track your performance and manage your trading journey.</p>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4 stats-grid">
            <div class="col-md-3 col-sm-6">
                <div class="summary-card <?php echo ($stats['total_trades'] ?? 0) > 0 ? 'highlight' : ''; ?>">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Total Trades</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                    <div class="summary-card-value"><?php echo $stats['total_trades'] ?? 0; ?></div>
                    <div class="summary-card-change">
                        <i class="fas fa-info-circle"></i>
                        <span>All time trades</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Win Rate</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                    <div class="summary-card-value" style="color: <?php echo $win_rate >= 50 ? 'var(--primary)' : '#ef4444'; ?>">
                        <?php echo number_format($win_rate, 1); ?>%
                    </div>
                    <div class="summary-card-change">
                        <i class="fas fa-<?php echo $win_rate >= 50 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                        <span><?php echo $stats['winning_trades'] ?? 0; ?> wins / <?php echo $stats['losing_trades'] ?? 0; ?> losses</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="summary-card highlight">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Total P/L</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="summary-card-value" style="color: <?php echo ($stats['total_profit_loss'] ?? 0) >= 0 ? 'var(--primary)' : '#ef4444'; ?>">
                        रु <?php echo number_format($stats['total_profit_loss'] ?? 0, 2); ?>
                    </div>
                    <?php if (($stats['total_profit_loss'] ?? 0) > 0): ?>
                    <div class="summary-card-change">
                        <i class="fas fa-arrow-up"></i>
                        <span>Profitable</span>
                    </div>
                    <?php elseif (($stats['total_profit_loss'] ?? 0) < 0): ?>
                    <div class="summary-card-change" style="color: #ef4444;">
                        <i class="fas fa-arrow-down"></i>
                        <span>In loss</span>
                    </div>
                    <?php endif; ?>
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
                    <div class="summary-card-change">
                        <i class="fas fa-clock"></i>
                        <span>Active trades</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Trader Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Best Trade</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="summary-card-value" style="color: var(--primary);">
                        रु <?php echo number_format($stats['best_trade'] ?? 0, 2); ?>
                    </div>
                    <div class="summary-card-change">
                        <i class="fas fa-thumbs-up"></i>
                        <span>Highest profit</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Worst Trade</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="summary-card-value" style="color: #ef4444;">
                        रु <?php echo number_format($stats['worst_trade'] ?? 0, 2); ?>
                    </div>
                    <div class="summary-card-change" style="color: #ef4444;">
                        <i class="fas fa-thumbs-down"></i>
                        <span>Biggest loss</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <div class="summary-card-title">Avg P/L</div>
                        <div class="summary-card-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                    <div class="summary-card-value" style="color: <?php echo ($stats['avg_profit_loss'] ?? 0) >= 0 ? 'var(--primary)' : '#ef4444'; ?>">
                        रु <?php echo number_format($stats['avg_profit_loss'] ?? 0, 2); ?>
                    </div>
                    <div class="summary-card-change">
                        <i class="fas fa-chart-line"></i>
                        <span>Per trade average</span>
                    </div>
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
                    <div class="summary-card-change">
                        <i class="fas fa-list"></i>
                        <span>Recent entries</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Trader Features Section -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <h3 class="content-card-title mb-4">
                    <i class="fas fa-rocket me-2"></i>Trader Essentials
                </h3>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="journal.php" class="trader-feature-card">
                    <div class="trader-feature-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="trader-feature-title">Trading Journal</div>
                    <div class="trader-feature-desc">
                        Track every trade, analyze patterns, and improve your strategy with detailed journal entries.
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="Community.php" class="trader-feature-card">
                    <div class="trader-feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="trader-feature-title">Community</div>
                    <div class="trader-feature-desc">
                        Connect with fellow traders, share insights, learn strategies, and grow together in our active community.
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="portfolio.php" class="trader-feature-card">
                    <div class="trader-feature-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="trader-feature-title">Portfolio</div>
                    <div class="trader-feature-desc">
                        Monitor your portfolio performance, view detailed analytics, and track your trading progress.
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="Mindset.php" class="trader-feature-card">
                    <div class="trader-feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div class="trader-feature-title">Mindset & Psychology</div>
                    <div class="trader-feature-desc">
                        Master your trading psychology, control emotions, and develop the right mindset for consistent success.
                    </div>
                </a>
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
                    
                    <a href="journal.php" class="quick-action-btn">
                        <div class="quick-action-btn-title">
                            <i class="fas fa-plus-circle me-2"></i>Add Journal Entry
                        </div>
                        <div class="quick-action-btn-desc">Record your trading thoughts and analysis.</div>
                    </a>
                    
                    <a href="journal.php" class="quick-action-btn">
                        <div class="quick-action-btn-title">
                            <i class="fas fa-chart-line me-2"></i>Log New Trade
                        </div>
                        <div class="quick-action-btn-desc">Add a new trade to your portfolio.</div>
                    </a>
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
            document.querySelectorAll('.summary-card, .content-card, .trader-feature-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                observer.observe(card);
            });
        });
        
    </script>
</body>
</html>

