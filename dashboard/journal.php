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
        
        // Handle Prop Firm specific fields
        $prop_firm_name = trim($_POST['prop_firm_name'] ?? '');
        $prop_account_number = trim($_POST['prop_account_number'] ?? '');
        $challenge_fee = !empty($_POST['challenge_fee']) ? floatval($_POST['challenge_fee']) : 0;
        $challenge_type = trim($_POST['challenge_type'] ?? '');
        
        // If prop firm, use prop firm name as broker name and prop account number
        if ($account_type === 'propfirm') {
            $broker_name = !empty($prop_firm_name) ? $prop_firm_name : trim($_POST['broker_name'] ?? '');
            $account_number = !empty($prop_account_number) ? $prop_account_number : trim($_POST['account_number'] ?? '');
        } else {
            $broker_name = trim($_POST['broker_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
        }
        
        $initial_balance = floatval($_POST['initial_balance']); // Account value (challenge account balance)
        // For prop firm: challenge_fee is separate from initial_balance
        // initial_balance = account value (e.g., $10,000 challenge account)
        // challenge_fee = fee paid to purchase the challenge (e.g., $99)
        
        $target_amount = !empty($_POST['target_amount']) ? floatval($_POST['target_amount']) : null;
        $currency = $_POST['currency'] ?? 'USD';
        $leverage = trim($_POST['leverage'] ?? '');
        $status = $_POST['status'] ?? ($account_type === 'propfirm' ? 'ongoing' : 'active');
        
        // Update status based on account type if not explicitly set
        if ($account_type === 'propfirm' && empty($_POST['status'])) {
            $status = 'ongoing';
        }
        
        // Build notes with prop firm details
        $notes = trim($_POST['notes'] ?? '');
        if ($account_type === 'propfirm') {
            $prop_details = [];
            if (!empty($prop_firm_name)) $prop_details[] = "Prop Firm: " . $prop_firm_name;
            if (!empty($challenge_type)) $prop_details[] = "Challenge Type: " . $challenge_type;
            
            if (!empty($prop_details)) {
                $prop_info = "=== PROP FIRM CHALLENGE DETAILS ===\n" . implode("\n", $prop_details);
                $notes = !empty($notes) ? $prop_info . "\n\n" . $notes : $prop_info;
            }
        }
        
        // Check if challenge_fee column exists, if not use notes
        $columns_check = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'challenge_fee'")->fetch();
        if ($columns_check) {
            $stmt = $pdo->prepare("
                INSERT INTO trading_accounts 
                (user_id, account_name, account_type, broker_name, account_number, initial_balance, current_balance, challenge_fee, target_amount, currency, leverage, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $account_name, 
                $account_type, 
                $broker_name, 
                $account_number, 
                $initial_balance, 
                $initial_balance, 
                $challenge_fee,
                $target_amount, 
                $currency, 
                $leverage,
                $status,
                $notes
            ]);
        } else {
            // Fallback if column doesn't exist yet
            $stmt = $pdo->prepare("
                INSERT INTO trading_accounts 
                (user_id, account_name, account_type, broker_name, account_number, initial_balance, current_balance, target_amount, currency, leverage, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $status,
                $notes
            ]);
        }
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
        
        // Handle challenge_fee for prop firms
        $challenge_fee = 0;
        if ($account_type === 'propfirm' && isset($_POST['challenge_fee'])) {
            $challenge_fee = floatval($_POST['challenge_fee']);
        }
        
        // Recalculate current balance
        $balance_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(profit_loss), 0) as total_pl 
            FROM trading_journal 
            WHERE account_id = ? AND profit_loss IS NOT NULL
        ");
        $balance_stmt->execute([$account_id]);
        $balance_data = $balance_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Subtract withdrawals from balance
        try {
            $withdrawals_stmt = $pdo->prepare("
                SELECT COALESCE(SUM(withdrawal_amount), 0) as total_withdrawals 
                FROM account_withdrawals 
                WHERE account_id = ?
            ");
            $withdrawals_stmt->execute([$account_id]);
            $withdrawals_data = $withdrawals_stmt->fetch(PDO::FETCH_ASSOC);
            $total_withdrawals = floatval($withdrawals_data['total_withdrawals'] ?? 0);
        } catch (PDOException $e) {
            $total_withdrawals = 0;
        }
        
        $current_balance = $initial_balance + floatval($balance_data['total_pl']) - $total_withdrawals;
        
        // Check if challenge_fee column exists
        $columns_check = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'challenge_fee'")->fetch();
        if ($columns_check) {
            $stmt = $pdo->prepare("
                UPDATE trading_accounts 
                SET account_name = ?, account_type = ?, broker_name = ?, account_number = ?, 
                    initial_balance = ?, current_balance = ?, challenge_fee = ?, target_amount = ?, currency = ?, 
                    leverage = ?, status = ?, notes = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $account_name, $account_type, $broker_name, $account_number,
                $initial_balance, $current_balance, $challenge_fee, $target_amount, $currency,
                $leverage, $status, $notes, $account_id, $_SESSION['user_id']
            ]);
        } else {
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
        }
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

// Handle Withdrawal Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_withdrawal') {
    try {
        // Create withdrawals table if it doesn't exist
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
        
        $account_id = !empty($_POST['account_id']) ? intval($_POST['account_id']) : null;
        $withdrawal_amount = floatval($_POST['withdrawal_amount']);
        $currency = $_POST['currency'] ?? 'USD';
        $platform = $_POST['platform'];
        $platform_details = trim($_POST['platform_details'] ?? '');
        $withdrawal_date = $_POST['withdrawal_date'];
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO account_withdrawals 
            (user_id, account_id, withdrawal_amount, currency, platform, platform_details, withdrawal_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], 
            $account_id, 
            $withdrawal_amount, 
            $currency, 
            $platform, 
            $platform_details, 
            $withdrawal_date, 
            $notes
        ]);
        
        // Update account current balance (recalculate with all trades and withdrawals)
        if ($account_id) {
            // Get account initial balance
            $account_stmt = $pdo->prepare("SELECT initial_balance FROM trading_accounts WHERE id = ? AND user_id = ?");
            $account_stmt->execute([$account_id, $_SESSION['user_id']]);
            $account_data = $account_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account_data) {
                $initial_balance = floatval($account_data['initial_balance']);
                
                // Get total profit/loss from trades
                $pl_stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(profit_loss), 0) as total_pl 
                    FROM trading_journal 
                    WHERE account_id = ? AND profit_loss IS NOT NULL
                ");
                $pl_stmt->execute([$account_id]);
                $pl_data = $pl_stmt->fetch(PDO::FETCH_ASSOC);
                $total_pl = floatval($pl_data['total_pl'] ?? 0);
                
                // Get total withdrawals
                $withdrawals_stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(withdrawal_amount), 0) as total_withdrawals 
                    FROM account_withdrawals 
                    WHERE account_id = ?
                ");
                $withdrawals_stmt->execute([$account_id]);
                $withdrawals_data = $withdrawals_stmt->fetch(PDO::FETCH_ASSOC);
                $total_withdrawals = floatval($withdrawals_data['total_withdrawals'] ?? 0);
                
                // Calculate new balance
                $new_balance = $initial_balance + $total_pl - $total_withdrawals;
                
                $update_stmt = $pdo->prepare("
                    UPDATE trading_accounts 
                    SET current_balance = ?
                    WHERE id = ? AND user_id = ?
                ");
                $update_stmt->execute([$new_balance, $account_id, $_SESSION['user_id']]);
            }
        }
        
        // Redirect to keep account selected
        if ($account_id) {
            header("Location: journal.php?account_id=" . $account_id . "&tab=trades");
            exit();
        }
        
        $message = "Withdrawal सफलतापूर्वक सेभ भयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle Trade Entry (Enhanced)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_trade') {
    try {
        // Create upload directory for trade screenshots
        $upload_dir = __DIR__.'/../uploads/trade_screenshots/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $account_id = !empty($_POST['account_id']) ? intval($_POST['account_id']) : null;
        $symbol = trim($_POST['symbol']);
        $trade_type = $_POST['trade_type'];
        $quantity = floatval($_POST['quantity']);
        $lot = !empty($_POST['lot']) ? floatval($_POST['lot']) : null;
        $entry_price = floatval($_POST['entry_price']);
        $exit_price = !empty($_POST['exit_price']) ? floatval($_POST['exit_price']) : null;
        $stop_loss = !empty($_POST['stop_loss']) ? floatval($_POST['stop_loss']) : null;
        $take_profit = !empty($_POST['take_profit']) ? floatval($_POST['take_profit']) : null;
        $entry_time = !empty($_POST['entry_time']) ? $_POST['entry_time'] : null;
        $exit_time = !empty($_POST['exit_time']) ? $_POST['exit_time'] : null;
        $trade_date = $_POST['trade_date'];
        $session_type = !empty($_POST['session_type']) ? $_POST['session_type'] : null;
        $risk_percent = !empty($_POST['risk_percent']) ? floatval($_POST['risk_percent']) : null;
        $r_multiple = !empty($_POST['r_multiple']) ? floatval($_POST['r_multiple']) : null;
        $strategy = !empty($_POST['strategy']) ? trim($_POST['strategy']) : null;
        $setup_type = !empty($_POST['setup_type']) ? trim($_POST['setup_type']) : null;
        $emotion_before = !empty($_POST['emotion_before']) ? $_POST['emotion_before'] : null;
        $emotion_during = !empty($_POST['emotion_during']) ? $_POST['emotion_during'] : null;
        $emotion_after = !empty($_POST['emotion_after']) ? $_POST['emotion_after'] : null;
        $mistake_tags = !empty($_POST['mistake_tags']) ? json_encode(explode(',', $_POST['mistake_tags'])) : null;
        $notes = trim($_POST['notes'] ?? '');
        
        // Handle screenshot upload
        $screenshot_path = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['screenshot'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 10 * 1024 * 1024; // 10MB
            
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'trade_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $screenshot_path = 'uploads/trade_screenshots/' . $new_filename;
                }
            }
        }
        
        // Calculate profit/loss
        $profit_loss = null;
        $trade_status = 'open';
        if ($exit_price !== null && $exit_price > 0) {
            if ($trade_type === 'buy') {
                $profit_loss = ($exit_price - $entry_price) * ($lot ? $lot * 100000 : $quantity);
            } else {
                $profit_loss = ($entry_price - $exit_price) * ($lot ? $lot * 100000 : $quantity);
            }
            $trade_status = 'closed';
            if (abs($profit_loss) < 0.01) {
                $trade_status = 'breakeven';
            }
        }
        
        // Calculate R multiple if not provided
        if ($r_multiple === null && $stop_loss !== null && $entry_price > 0 && $profit_loss !== null) {
            $risk_amount = abs($entry_price - $stop_loss) * ($lot ? $lot * 100000 : $quantity);
            if ($risk_amount > 0) {
                $r_multiple = $profit_loss / $risk_amount;
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO trading_journal 
            (user_id, account_id, symbol, trade_type, quantity, lot, entry_price, exit_price, stop_loss, take_profit,
             entry_time, exit_time, trade_date, session_type, risk_percent, r_multiple, strategy, setup_type,
             emotion_before, emotion_during, emotion_after, mistake_tags, notes, screenshot_path, profit_loss, trade_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $account_id, $symbol, $trade_type, 
            $quantity, $lot, $entry_price, $exit_price, $stop_loss, $take_profit,
            $entry_time, $exit_time, $trade_date, $session_type, $risk_percent, $r_multiple,
            $strategy, $setup_type, $emotion_before, $emotion_during, $emotion_after,
            $mistake_tags, $notes, $screenshot_path, $profit_loss, $trade_status
        ]);
        
        // Update account current balance (including withdrawals)
        if ($account_id && $profit_loss !== null) {
            // Get total withdrawals
            try {
                $withdrawals_stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(withdrawal_amount), 0) as total_withdrawals 
                    FROM account_withdrawals 
                    WHERE account_id = ?
                ");
                $withdrawals_stmt->execute([$account_id]);
                $withdrawals_data = $withdrawals_stmt->fetch(PDO::FETCH_ASSOC);
                $total_withdrawals = floatval($withdrawals_data['total_withdrawals'] ?? 0);
            } catch (PDOException $e) {
                $total_withdrawals = 0;
            }
            
            $update_stmt = $pdo->prepare("
                UPDATE trading_accounts 
                SET current_balance = initial_balance + (
                    SELECT COALESCE(SUM(profit_loss), 0) 
                    FROM trading_journal 
                    WHERE account_id = ? AND profit_loss IS NOT NULL
                ) - ?
                WHERE id = ?
            ");
            $update_stmt->execute([$account_id, $total_withdrawals, $account_id]);
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
        // Get account_id and screenshot before deleting
        $get_stmt = $pdo->prepare("SELECT account_id, screenshot_path FROM trading_journal WHERE id = ? AND user_id = ?");
        $get_stmt->execute([$trade_id, $_SESSION['user_id']]);
        $trade_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("DELETE FROM trading_journal WHERE id = ? AND user_id = ?");
        $stmt->execute([$trade_id, $_SESSION['user_id']]);
        
        // Delete screenshot if exists
        if ($trade_data && $trade_data['screenshot_path'] && file_exists(__DIR__.'/../' . $trade_data['screenshot_path'])) {
            unlink(__DIR__.'/../' . $trade_data['screenshot_path']);
        }
        
        // Update account balance (including withdrawals)
        if ($trade_data && $trade_data['account_id']) {
            // Get total withdrawals
            try {
                $withdrawals_stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(withdrawal_amount), 0) as total_withdrawals 
                    FROM account_withdrawals 
                    WHERE account_id = ?
                ");
                $withdrawals_stmt->execute([$trade_data['account_id']]);
                $withdrawals_data = $withdrawals_stmt->fetch(PDO::FETCH_ASSOC);
                $total_withdrawals = floatval($withdrawals_data['total_withdrawals'] ?? 0);
            } catch (PDOException $e) {
                $total_withdrawals = 0;
            }
            
            $update_stmt = $pdo->prepare("
                UPDATE trading_accounts 
                SET current_balance = initial_balance + (
                    SELECT COALESCE(SUM(profit_loss), 0) 
                    FROM trading_journal 
                    WHERE account_id = ? AND profit_loss IS NOT NULL
                ) - ?
                WHERE id = ?
            ");
            $update_stmt->execute([$trade_data['account_id'], $total_withdrawals, $trade_data['account_id']]);
        }
        
        $message = "Trade सफलतापूर्वक मेटाइयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle Psychology Log Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_psychology_log') {
    try {
        $account_id = !empty($_POST['account_id']) ? intval($_POST['account_id']) : null;
        $log_date = $_POST['log_date'];
        $emotion_type = !empty($_POST['emotion_type']) ? $_POST['emotion_type'] : null;
        $intensity = !empty($_POST['intensity']) ? intval($_POST['intensity']) : 5;
        $trigger = trim($_POST['trigger'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $tags = !empty($_POST['tags']) ? json_encode(explode(',', $_POST['tags'])) : null;
        
        $stmt = $pdo->prepare("
            INSERT INTO psychology_log 
            (user_id, account_id, log_date, emotion_type, intensity, trigger, notes, tags) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $account_id, $log_date, $emotion_type, 
            $intensity, $trigger, $notes, $tags
        ]);
        
        $message = "Psychology log सफलतापूर्वक सेभ भयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle Mistake Log Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_mistake_log') {
    try {
        $account_id = !empty($_POST['account_id']) ? intval($_POST['account_id']) : null;
        $trade_id = !empty($_POST['trade_id']) ? intval($_POST['trade_id']) : null;
        $mistake_date = $_POST['mistake_date'];
        $mistake_type = trim($_POST['mistake_type']);
        $description = trim($_POST['description']);
        $impact = $_POST['impact'] ?? 'medium';
        $tags = !empty($_POST['tags']) ? json_encode(explode(',', $_POST['tags'])) : null;
        $lesson_learned = trim($_POST['lesson_learned'] ?? '');
        $action_plan = trim($_POST['action_plan'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO mistake_log 
            (user_id, account_id, trade_id, mistake_date, mistake_type, description, impact, tags, lesson_learned, action_plan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $account_id, $trade_id, $mistake_date, $mistake_type,
            $description, $impact, $tags, $lesson_learned, $action_plan
        ]);
        
        $message = "Mistake log सफलतापूर्वक सेभ भयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle Review Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    try {
        $account_id = !empty($_POST['account_id']) ? intval($_POST['account_id']) : null;
        $review_type = $_POST['review_type'];
        $review_period_start = $_POST['review_period_start'];
        $review_period_end = $_POST['review_period_end'];
        $what_went_well = trim($_POST['what_went_well'] ?? '');
        $what_went_wrong = trim($_POST['what_went_wrong'] ?? '');
        $lessons_learned = trim($_POST['lessons_learned'] ?? '');
        $goals_for_next_period = trim($_POST['goals_for_next_period'] ?? '');
        $action_items = !empty($_POST['action_items']) ? json_encode(explode("\n", $_POST['action_items'])) : null;
        $self_rating = !empty($_POST['self_rating']) ? intval($_POST['self_rating']) : null;
        
        // Calculate stats for the period
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
                SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades,
                COALESCE(SUM(profit_loss), 0) as total_pl,
                COALESCE(MAX(profit_loss), 0) as best_trade,
                COALESCE(MIN(profit_loss), 0) as worst_trade,
                COALESCE(AVG(CASE WHEN profit_loss > 0 THEN profit_loss END), 0) as avg_win,
                COALESCE(AVG(CASE WHEN profit_loss < 0 THEN profit_loss END), 0) as avg_loss
            FROM trading_journal
            WHERE user_id = ? AND account_id = ? AND trade_date BETWEEN ? AND ?
        ");
        $stats_stmt->execute([$_SESSION['user_id'], $account_id, $review_period_start, $review_period_end]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        $win_rate = $stats['total_trades'] > 0 ? ($stats['winning_trades'] / $stats['total_trades']) * 100 : 0;
        
        $stmt = $pdo->prepare("
            INSERT INTO trading_reviews 
            (user_id, account_id, review_type, review_period_start, review_period_end,
             total_trades, winning_trades, losing_trades, win_rate, total_profit_loss,
             best_trade, worst_trade, avg_win, avg_loss, what_went_well, what_went_wrong,
             lessons_learned, goals_for_next_period, action_items, self_rating) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $account_id, $review_type, $review_period_start, $review_period_end,
            $stats['total_trades'], $stats['winning_trades'], $stats['losing_trades'], $win_rate, $stats['total_pl'],
            $stats['best_trade'], $stats['worst_trade'], $stats['avg_win'], $stats['avg_loss'],
            $what_went_well, $what_went_wrong, $lessons_learned, $goals_for_next_period, $action_items, $self_rating
        ]);
        
        $message = "Review सफलतापूर्वक सेभ भयो!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "त्रुटि: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get selected account and active tab
$selected_account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'trades';

// Fetch all accounts (include challenge_fee if column exists)
$columns_check_accounts = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'challenge_fee'")->fetch();
if ($columns_check_accounts) {
    $accounts_stmt = $pdo->prepare("SELECT *, COALESCE(challenge_fee, 0) as challenge_fee FROM trading_accounts WHERE user_id = ? ORDER BY created_at DESC");
} else {
    $accounts_stmt = $pdo->prepare("SELECT *, 0 as challenge_fee FROM trading_accounts WHERE user_id = ? ORDER BY created_at DESC");
}
$accounts_stmt->execute([$_SESSION['user_id']]);
$accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build filter conditions for trades
$filter_conditions = ["j.user_id = ?"];
$filter_params = [$_SESSION['user_id']];

if ($selected_account_id) {
    $filter_conditions[] = "j.account_id = ?";
    $filter_params[] = $selected_account_id;
}

if (isset($_GET['filter_symbol']) && !empty($_GET['filter_symbol'])) {
    $filter_conditions[] = "j.symbol LIKE ?";
    $filter_params[] = '%' . $_GET['filter_symbol'] . '%';
}

if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
    $filter_conditions[] = "j.trade_date >= ?";
    $filter_params[] = $_GET['filter_date_from'];
}

if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
    $filter_conditions[] = "j.trade_date <= ?";
    $filter_params[] = $_GET['filter_date_to'];
}

if (isset($_GET['filter_result']) && $_GET['filter_result'] !== '') {
    $filter_result = $_GET['filter_result'];
    // Only allow specific values for security
    if (in_array($filter_result, ['win', 'loss', 'breakeven'])) {
        if ($filter_result === 'win') {
            $filter_conditions[] = "j.profit_loss > 0";
        } elseif ($filter_result === 'loss') {
            $filter_conditions[] = "j.profit_loss < 0";
        } elseif ($filter_result === 'breakeven') {
            $filter_conditions[] = "(j.profit_loss = 0 OR j.profit_loss IS NULL)";
        }
    }
}

$where_clause = implode(' AND ', $filter_conditions);

// Fetch journal entries with filters
$journal_stmt = $pdo->prepare("
    SELECT j.*, a.account_name, a.account_type 
    FROM trading_journal j 
    LEFT JOIN trading_accounts a ON j.account_id = a.id 
    WHERE $where_clause
    ORDER BY j.trade_date DESC, j.id DESC
");
$journal_stmt->execute($filter_params);
$journal_entries = $journal_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get comprehensive statistics
$account_stats = null;
$all_stats = null;

if ($selected_account_id) {
    $stats_stmt = $pdo->prepare("
        SELECT 
            a.*,
            COUNT(j.id) as total_trades,
            SUM(CASE WHEN j.profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(CASE WHEN j.profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades,
            COALESCE(SUM(j.profit_loss), 0) as total_pl,
            COALESCE(AVG(CASE WHEN j.profit_loss > 0 THEN j.profit_loss END), 0) as avg_win,
            COALESCE(AVG(CASE WHEN j.profit_loss < 0 THEN j.profit_loss END), 0) as avg_loss,
            COALESCE(MAX(j.profit_loss), 0) as best_trade,
            COALESCE(MIN(j.profit_loss), 0) as worst_trade
        FROM trading_accounts a
        LEFT JOIN trading_journal j ON a.id = j.account_id
        WHERE a.id = ? AND a.user_id = ?
        GROUP BY a.id
    ");
    $stats_stmt->execute([$selected_account_id, $_SESSION['user_id']]);
    $account_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account_stats && $account_stats['total_trades'] > 0) {
        $account_stats['win_rate'] = ($account_stats['winning_trades'] / $account_stats['total_trades']) * 100;
    }
}

// Fetch symbol performance stats
$symbol_stats_stmt = $pdo->prepare("
    SELECT 
        symbol,
        COUNT(*) as total_trades,
        SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as wins,
        SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losses,
        COALESCE(SUM(profit_loss), 0) as total_pl,
        COALESCE(AVG(profit_loss), 0) as avg_pl
    FROM trading_journal
    WHERE user_id = ? " . ($selected_account_id ? "AND account_id = ?" : "") . "
    GROUP BY symbol
    ORDER BY total_pl DESC
");
if ($selected_account_id) {
    $symbol_stats_stmt->execute([$_SESSION['user_id'], $selected_account_id]);
} else {
    $symbol_stats_stmt->execute([$_SESSION['user_id']]);
}
$symbol_stats = $symbol_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch session performance stats
$session_stats_stmt = $pdo->prepare("
    SELECT 
        COALESCE(session_type, 'Unknown') as session_type,
        COUNT(*) as total_trades,
        SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as wins,
        SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losses,
        COALESCE(SUM(profit_loss), 0) as total_pl
    FROM trading_journal
    WHERE user_id = ? " . ($selected_account_id ? "AND account_id = ?" : "") . "
    GROUP BY session_type
    ORDER BY total_pl DESC
");
if ($selected_account_id) {
    $session_stats_stmt->execute([$_SESSION['user_id'], $selected_account_id]);
} else {
    $session_stats_stmt->execute([$_SESSION['user_id']]);
}
$session_stats = $session_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch psychology logs
$psychology_logs_stmt = $pdo->prepare("
    SELECT p.*, a.account_name 
    FROM psychology_log p
    LEFT JOIN trading_accounts a ON p.account_id = a.id
    WHERE p.user_id = ? " . ($selected_account_id ? "AND p.account_id = ?" : "") . "
    ORDER BY p.log_date DESC, p.id DESC
");
if ($selected_account_id) {
    $psychology_logs_stmt->execute([$_SESSION['user_id'], $selected_account_id]);
} else {
    $psychology_logs_stmt->execute([$_SESSION['user_id']]);
}
$psychology_logs = $psychology_logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch mistake logs
$mistake_logs_stmt = $pdo->prepare("
    SELECT m.*, a.account_name, j.symbol as trade_symbol
    FROM mistake_log m
    LEFT JOIN trading_accounts a ON m.account_id = a.id
    LEFT JOIN trading_journal j ON m.trade_id = j.id
    WHERE m.user_id = ? " . ($selected_account_id ? "AND m.account_id = ?" : "") . "
    ORDER BY m.mistake_date DESC, m.id DESC
");
if ($selected_account_id) {
    $mistake_logs_stmt->execute([$_SESSION['user_id'], $selected_account_id]);
} else {
    $mistake_logs_stmt->execute([$_SESSION['user_id']]);
}
$mistake_logs = $mistake_logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviews
$reviews_stmt = $pdo->prepare("
    SELECT r.*, a.account_name 
    FROM trading_reviews r
    LEFT JOIN trading_accounts a ON r.account_id = a.id
    WHERE r.user_id = ? " . ($selected_account_id ? "AND r.account_id = ?" : "") . "
    ORDER BY r.review_period_start DESC, r.id DESC
");
if ($selected_account_id) {
    $reviews_stmt->execute([$_SESSION['user_id'], $selected_account_id]);
} else {
    $reviews_stmt->execute([$_SESSION['user_id']]);
}
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
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
            color: var(--text-primary) !important;
        }
        
        .journal-card * {
            color: inherit;
        }
        
        .journal-card h3, .journal-card h4, .journal-card h5, .journal-card h6 {
            color: var(--text-primary) !important;
        }
        
        .journal-card p {
            color: var(--text-primary) !important;
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
            background-color: var(--dark-hover);
            border: 1px solid var(--border-color);
            color: var(--text-primary) !important;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--dark-hover);
            border-color: var(--primary);
            color: var(--text-primary) !important;
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary) !important;
            opacity: 0.7;
        }
        
        .form-label {
            color: var(--text-primary) !important;
            font-weight: 600;
        }
        
        .modal-content {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary) !important;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary) !important;
        }
        
        .modal-header .modal-title {
            color: var(--text-primary) !important;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
        }
        
        .modal-body {
            color: var(--text-primary) !important;
        }
        
        .modal-body * {
            color: inherit;
        }
        
        /* Tab Styles */
        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
        }
        
        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background-color: rgba(16, 185, 129, 0.1);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            background-color: transparent;
            border-bottom-color: var(--primary);
            font-weight: 600;
        }
        
        .tab-content {
            padding-top: 20px;
            color: var(--text-primary) !important;
        }
        
        /* Table Styles - Ensure text is visible */
        .table-dark {
            background-color: var(--dark-card) !important;
            color: var(--text-primary) !important;
        }
        
        .table-dark th {
            background-color: var(--dark-hover) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        .table-dark td {
            background-color: var(--dark-card) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        .table-dark tbody tr:hover {
            background-color: var(--dark-hover) !important;
        }
        
        .table-dark tbody tr:hover td {
            color: var(--text-primary) !important;
        }
        
        /* Text muted - make it more visible */
        .text-muted {
            color: #cbd5e1 !important;
        }
        
        /* Badge colors */
        .badge {
            color: white !important;
        }
        
        .badge.bg-secondary {
            background-color: #64748b !important;
            color: white !important;
        }
        
        /* List group items */
        .list-group-item {
            background-color: var(--dark-card) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }
        
        .list-group-item h6 {
            color: var(--text-primary) !important;
        }
        
        .list-group-item small {
            color: var(--text-secondary) !important;
        }
        
        .list-group-item p {
            color: var(--text-primary) !important;
        }
        
        /* Dropdown menu */
        .dropdown-menu {
            background-color: var(--dark-card) !important;
            border-color: var(--border-color) !important;
        }
        
        .dropdown-item {
            color: var(--text-primary) !important;
        }
        
        .dropdown-item:hover {
            background-color: var(--dark-hover) !important;
            color: var(--text-primary) !important;
        }
        
        /* Account card text */
        .account-card {
            color: var(--text-primary) !important;
        }
        
        .account-card h5 {
            color: var(--text-primary) !important;
        }
        
        .account-card p {
            color: var(--text-primary) !important;
        }
        
        .account-card .text-muted {
            color: var(--text-secondary) !important;
        }
        
        /* Stat card text */
        .stat-card {
            color: var(--text-primary) !important;
        }
        
        .stat-value {
            color: var(--text-primary) !important;
        }
        
        .stat-label {
            color: var(--text-secondary) !important;
        }
        
        /* Chart container */
        .chart-container {
            color: var(--text-primary) !important;
        }
        
        .chart-container h5 {
            color: var(--text-primary) !important;
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
                    <?php if ($active_tab === 'trades'): ?>
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addTradeModal">
                            <i class="fas fa-plus me-2"></i>Add Trade
                        </button>
                        <?php if ($selected_account_id): ?>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addWithdrawalModal">
                                <i class="fas fa-money-bill-wave me-2"></i>Add Withdrawal
                            </button>
                        <?php endif; ?>
                    <?php elseif ($active_tab === 'psychology'): ?>
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addPsychologyModal">
                            <i class="fas fa-brain me-2"></i>Add Psychology Log
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMistakeModal">
                            <i class="fas fa-exclamation-triangle me-2"></i>Add Mistake Log
                        </button>
                    <?php elseif ($active_tab === 'reviews'): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                            <i class="fas fa-clipboard-check me-2"></i>Add Review
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs mb-4" id="journalTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'trades' ? 'active' : ''; ?>" id="trades-tab" data-bs-toggle="tab" data-bs-target="#trades" type="button" role="tab" onclick="window.location.href='?tab=trades<?php echo $selected_account_id ? '&account_id='.$selected_account_id : ''; ?>'">
                        <i class="fas fa-chart-line me-2"></i>Trades
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'stats' ? 'active' : ''; ?>" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab" onclick="window.location.href='?tab=stats<?php echo $selected_account_id ? '&account_id='.$selected_account_id : ''; ?>'">
                        <i class="fas fa-chart-bar me-2"></i>Stats
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'psychology' ? 'active' : ''; ?>" id="psychology-tab" data-bs-toggle="tab" data-bs-target="#psychology" type="button" role="tab" onclick="window.location.href='?tab=psychology<?php echo $selected_account_id ? '&account_id='.$selected_account_id : ''; ?>'">
                        <i class="fas fa-brain me-2"></i>Psychology & Mistakes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'reviews' ? 'active' : ''; ?>" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" onclick="window.location.href='?tab=reviews<?php echo $selected_account_id ? '&account_id='.$selected_account_id : ''; ?>'">
                        <i class="fas fa-clipboard-check me-2"></i>Reviews
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="journalTabContent">
            
            <!-- Trades Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'trades' ? 'show active' : ''; ?>" id="trades" role="tabpanel">
            
            <!-- Accounts Section -->
            <?php if (!empty($accounts)): ?>
                <div class="mb-4">
                    <h3 class="h5 mb-3">Your Trading Accounts</h3>
                    <div class="row">
                        <?php foreach ($accounts as $account): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="account-card <?php echo ($selected_account_id == $account['id']) ? 'active' : ''; ?>" 
                                     onclick="window.location.href='?account_id=<?php echo $account['id']; ?>&tab=trades'">
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
                                                <li><a class="dropdown-item" href="?account_id=<?php echo $account['id']; ?>&tab=trades">View</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); event.preventDefault(); editAccount(<?php echo htmlspecialchars(json_encode($account), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>); return false;">Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?delete_account=<?php echo $account['id']; ?>" onclick="event.stopPropagation(); return confirm('Are you sure you want to delete this account?')">Delete</a></li>
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

            <!-- Filters -->
            <div class="journal-card mb-4">
                <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filters</h5>
                <form method="GET" action="journal.php" class="row g-3">
                    <?php if ($selected_account_id): ?>
                        <input type="hidden" name="account_id" value="<?php echo $selected_account_id; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="tab" value="trades">
                    <div class="col-md-3">
                        <label class="form-label">Symbol</label>
                        <input type="text" class="form-control" name="filter_symbol" value="<?php echo htmlspecialchars($_GET['filter_symbol'] ?? ''); ?>" placeholder="EURUSD, NTC...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="filter_date_from" value="<?php echo htmlspecialchars($_GET['filter_date_from'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="filter_date_to" value="<?php echo htmlspecialchars($_GET['filter_date_to'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Result</label>
                        <select class="form-select" name="filter_result">
                            <option value="">All</option>
                            <option value="win" <?php echo (isset($_GET['filter_result']) && $_GET['filter_result'] === 'win') ? 'selected' : ''; ?>>Win</option>
                            <option value="loss" <?php echo (isset($_GET['filter_result']) && $_GET['filter_result'] === 'loss') ? 'selected' : ''; ?>>Loss</option>
                            <option value="breakeven" <?php echo (isset($_GET['filter_result']) && $_GET['filter_result'] === 'breakeven') ? 'selected' : ''; ?>>Breakeven</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-2"></i>Filter</button>
                        <a href="?tab=trades<?php echo $selected_account_id ? '&account_id='.$selected_account_id : ''; ?>" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Clear</a>
                    </div>
                </form>
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
                                <th>Lot</th>
                                <th>Entry</th>
                                <th>Exit</th>
                                <th>SL</th>
                                <th>TP</th>
                                <th>R Multiple</th>
                                <th>P/L</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($journal_entries)): ?>
                                <tr>
                                    <td colspan="<?php echo $selected_account_id ? '11' : '12'; ?>" class="text-center text-muted py-4">
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
                                        <td><?php echo $entry['lot'] ? number_format($entry['lot'], 2) : number_format($entry['quantity'], 2); ?></td>
                                        <td><?php echo number_format($entry['entry_price'], 2); ?></td>
                                        <td>
                                            <?php echo $entry['exit_price'] ? number_format($entry['exit_price'], 2) : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php echo $entry['stop_loss'] ? number_format($entry['stop_loss'], 2) : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php echo $entry['take_profit'] ? number_format($entry['take_profit'], 2) : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if ($entry['r_multiple'] !== null): ?>
                                                <span class="<?php echo $entry['r_multiple'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo number_format($entry['r_multiple'], 2); ?>R
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($entry['profit_loss'] !== null): ?>
                                                <span class="<?php echo $entry['profit_loss'] >= 0 ? 'trade-profit' : 'trade-loss'; ?>">
                                                    <?php echo $entry['profit_loss'] >= 0 ? '+' : ''; ?><?php echo number_format($entry['profit_loss'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewTrade(<?php echo $entry['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteTrade(<?php echo $entry['id']; ?>)" title="Delete">
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
            <!-- End Trades Tab -->
            
            <!-- Stats Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'stats' ? 'show active' : ''; ?>" id="stats" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h3 class="h5 mb-3">Overall Statistics</h3>
                        <?php if ($account_stats): ?>
                            <div class="account-stats-grid mb-4">
                                <div class="stat-card">
                                    <div class="stat-value text-primary"><?php echo $account_stats['total_trades']; ?></div>
                                    <div class="stat-label">Total Trades</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value text-success"><?php echo number_format($account_stats['win_rate'] ?? 0, 1); ?>%</div>
                                    <div class="stat-label">Win Rate</div>
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
                                <div class="stat-card">
                                    <div class="stat-value text-success"><?php echo $account_stats['currency']; ?> <?php echo number_format($account_stats['avg_win'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Avg Win</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value text-danger"><?php echo $account_stats['currency']; ?> <?php echo number_format($account_stats['avg_loss'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Avg Loss</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value text-success"><?php echo $account_stats['currency']; ?> <?php echo number_format($account_stats['best_trade'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Best Trade</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value text-danger"><?php echo $account_stats['currency']; ?> <?php echo number_format($account_stats['worst_trade'] ?? 0, 2); ?></div>
                                    <div class="stat-label">Worst Trade</div>
                                </div>
                            </div>
                            
                            <!-- Equity Curve Chart -->
                            <div class="journal-card mb-4">
                                <h5 class="mb-3">Equity Curve</h5>
                                <canvas id="equityChart" height="80"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="journal-card text-center py-5">
                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                <h4>No Statistics Available</h4>
                                <p class="text-muted">Select an account to view statistics</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Symbol Performance -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="journal-card">
                            <h5 class="mb-3">Symbol Performance</h5>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm">
                                    <thead>
                                        <tr>
                                            <th>Symbol</th>
                                            <th>Trades</th>
                                            <th>Wins</th>
                                            <th>Losses</th>
                                            <th>P/L</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($symbol_stats)): ?>
                                            <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($symbol_stats as $stat): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($stat['symbol']); ?></strong></td>
                                                    <td><?php echo $stat['total_trades']; ?></td>
                                                    <td class="text-success"><?php echo $stat['wins']; ?></td>
                                                    <td class="text-danger"><?php echo $stat['losses']; ?></td>
                                                    <td class="<?php echo $stat['total_pl'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo number_format($stat['total_pl'], 2); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Session Performance -->
                    <div class="col-md-6">
                        <div class="journal-card">
                            <h5 class="mb-3">Session Performance</h5>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm">
                                    <thead>
                                        <tr>
                                            <th>Session</th>
                                            <th>Trades</th>
                                            <th>Wins</th>
                                            <th>Losses</th>
                                            <th>P/L</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($session_stats)): ?>
                                            <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($session_stats as $stat): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($stat['session_type']); ?></strong></td>
                                                    <td><?php echo $stat['total_trades']; ?></td>
                                                    <td class="text-success"><?php echo $stat['wins']; ?></td>
                                                    <td class="text-danger"><?php echo $stat['losses']; ?></td>
                                                    <td class="<?php echo $stat['total_pl'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo number_format($stat['total_pl'], 2); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Stats Tab -->
            
            <!-- Psychology Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'psychology' ? 'show active' : ''; ?>" id="psychology" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="journal-card">
                            <h5 class="mb-3"><i class="fas fa-brain me-2"></i>Psychology Logs</h5>
                            <?php if (empty($psychology_logs)): ?>
                                <p class="text-muted text-center py-4">No psychology logs yet</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($psychology_logs as $log): ?>
                                        <div class="list-group-item bg-dark border-secondary mb-2">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($log['emotion_type'] ?? 'Unknown'); ?></h6>
                                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></small>
                                                    <?php if ($log['intensity']): ?>
                                                        <div class="mt-2">
                                                            <small>Intensity: </small>
                                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                                <i class="fas fa-star <?php echo $i <= $log['intensity'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($log['notes']): ?>
                                                        <p class="mb-0 mt-2"><?php echo htmlspecialchars($log['notes']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="journal-card">
                            <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Mistake Logs</h5>
                            <?php if (empty($mistake_logs)): ?>
                                <p class="text-muted text-center py-4">No mistake logs yet</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($mistake_logs as $mistake): ?>
                                        <div class="list-group-item bg-dark border-secondary mb-2">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($mistake['mistake_type']); ?>
                                                        <span class="badge bg-<?php echo $mistake['impact'] === 'critical' ? 'danger' : ($mistake['impact'] === 'high' ? 'warning' : 'secondary'); ?> ms-2">
                                                            <?php echo ucfirst($mistake['impact']); ?>
                                                        </span>
                                                    </h6>
                                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($mistake['mistake_date'])); ?></small>
                                                    <p class="mb-1 mt-2"><?php echo htmlspecialchars($mistake['description']); ?></p>
                                                    <?php if ($mistake['lesson_learned']): ?>
                                                        <p class="mb-0 text-success"><small><strong>Lesson:</strong> <?php echo htmlspecialchars($mistake['lesson_learned']); ?></small></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Psychology Tab -->
            
            <!-- Reviews Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'reviews' ? 'show active' : ''; ?>" id="reviews" role="tabpanel">
                <div class="journal-card">
                    <h5 class="mb-3"><i class="fas fa-clipboard-check me-2"></i>Weekly & Monthly Reviews</h5>
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted text-center py-4">No reviews yet. Create your first review to track your progress!</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($reviews as $review): ?>
                                <div class="list-group-item bg-dark border-secondary mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo ucfirst($review['review_type']); ?> Review
                                                <span class="badge bg-primary ms-2">
                                                    <?php echo date('M d', strtotime($review['review_period_start'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($review['review_period_end'])); ?>
                                                </span>
                                            </h6>
                                            <small class="text-muted">
                                                Win Rate: <?php echo number_format($review['win_rate'], 1); ?>% | 
                                                Total P/L: <span class="<?php echo $review['total_profit_loss'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo number_format($review['total_profit_loss'], 2); ?>
                                                </span>
                                            </small>
                                        </div>
                                        <?php if ($review['self_rating']): ?>
                                            <div class="text-end">
                                                <div class="h4 mb-0"><?php echo $review['self_rating']; ?>/10</div>
                                                <small class="text-muted">Self Rating</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($review['what_went_well']): ?>
                                        <div class="mt-2">
                                            <strong class="text-success">What Went Well:</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['what_went_well'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($review['what_went_wrong']): ?>
                                        <div class="mt-2">
                                            <strong class="text-danger">What Went Wrong:</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['what_went_wrong'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($review['lessons_learned']): ?>
                                        <div class="mt-2">
                                            <strong class="text-primary">Lessons Learned:</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['lessons_learned'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- End Reviews Tab -->
            
            </div>
            <!-- End Tab Content -->
        </div>
    </main>

    <!-- Add Trade Modal -->
    <div class="modal fade" id="addTradeModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Trade</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="tradeForm" action="journal.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_trade">
                        <?php if (!empty($accounts)): ?>
                            <div class="mb-3">
                                <label class="form-label">Trading Account</label>
                                <select class="form-select" name="account_id">
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
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" name="trade_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Symbol *</label>
                                <input type="text" class="form-control" name="symbol" placeholder="EURUSD, NTC, NBL" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Buy/Sell *</label>
                                <select class="form-select" name="trade_type" required>
                                    <option value="buy">Buy</option>
                                    <option value="sell">Sell</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Session Type</label>
                                <select class="form-select" name="session_type">
                                    <option value="">Select Session</option>
                                    <option value="Asian">Asian</option>
                                    <option value="London">London</option>
                                    <option value="New York">New York</option>
                                    <option value="Overlap">Overlap</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Lot Size</label>
                                <input type="number" class="form-control" name="lot" step="0.01" placeholder="0.01">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" step="0.01" placeholder="Alternative to lot">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Entry Price *</label>
                                <input type="number" class="form-control" name="entry_price" step="0.00001" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Entry Time</label>
                                <input type="time" class="form-control" name="entry_time">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Exit Price</label>
                                <input type="number" class="form-control" name="exit_price" step="0.00001">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Exit Time</label>
                                <input type="time" class="form-control" name="exit_time">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Stop Loss (SL)</label>
                                <input type="number" class="form-control" name="stop_loss" step="0.00001">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Take Profit (TP)</label>
                                <input type="number" class="form-control" name="take_profit" step="0.00001">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Risk %</label>
                                <input type="number" class="form-control" name="risk_percent" step="0.1" placeholder="1.0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">R Multiple</label>
                                <input type="number" class="form-control" name="r_multiple" step="0.01" placeholder="Auto-calculated">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Strategy</label>
                                <input type="text" class="form-control" name="strategy" placeholder="e.g., Breakout, Reversal">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Setup Type</label>
                                <input type="text" class="form-control" name="setup_type" placeholder="e.g., Pin Bar, Engulfing">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emotion Before Trade</label>
                                <select class="form-select" name="emotion_before">
                                    <option value="">Select</option>
                                    <option value="confident">Confident</option>
                                    <option value="anxious">Anxious</option>
                                    <option value="fearful">Fearful</option>
                                    <option value="greedy">Greedy</option>
                                    <option value="calm">Calm</option>
                                    <option value="excited">Excited</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emotion During Trade</label>
                                <select class="form-select" name="emotion_during">
                                    <option value="">Select</option>
                                    <option value="confident">Confident</option>
                                    <option value="anxious">Anxious</option>
                                    <option value="fearful">Fearful</option>
                                    <option value="greedy">Greedy</option>
                                    <option value="calm">Calm</option>
                                    <option value="excited">Excited</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emotion After Trade</label>
                                <select class="form-select" name="emotion_after">
                                    <option value="">Select</option>
                                    <option value="confident">Confident</option>
                                    <option value="anxious">Anxious</option>
                                    <option value="fearful">Fearful</option>
                                    <option value="greedy">Greedy</option>
                                    <option value="calm">Calm</option>
                                    <option value="excited">Excited</option>
                                    <option value="regretful">Regretful</option>
                                    <option value="satisfied">Satisfied</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mistake Tags (comma-separated)</label>
                            <input type="text" class="form-control" name="mistake_tags" placeholder="overtrading, revenge trading, FOMO">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Trade analysis, observations..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Screenshot</label>
                            <input type="file" class="form-control" name="screenshot" accept="image/*">
                            <small class="text-muted">Upload trade screenshot (max 10MB)</small>
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
                    <h5 class="modal-title"><i class="fas fa-wallet me-2"></i>Create New Trading Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-4" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Important:</strong> This account information will be used in your Portfolio to track investments, losses by broker, and account performance.
                    </div>
                    
                    <form id="accountForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="create_account">
                        
                        <!-- Basic Information -->
                        <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name * <small class="text-muted">(e.g., "My FTMO Challenge", "IC Markets Live")</small></label>
                                <input type="text" class="form-control" name="account_name" id="account_name" placeholder="Enter a unique account name" required>
                                <small class="text-muted">This name will appear in your portfolio and journal</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Type * <small class="text-muted">(Used for portfolio breakdown)</small></label>
                                <select class="form-select" name="account_type" id="account_type" required onchange="togglePropFirmFields()">
                                    <option value="">-- Select Account Type --</option>
                                    <option value="forex">Forex</option>
                                    <option value="propfirm">Prop Firm Challenge</option>
                                    <option value="nepse">NEPSE (Nepal Stock Exchange)</option>
                                    <option value="crypto">Crypto</option>
                                    <option value="other">Other</option>
                                </select>
                                <small class="text-muted">This helps categorize your accounts in portfolio statistics</small>
                            </div>
                        </div>
                        
                        <!-- Prop Firm Specific Fields (Hidden by default) -->
                        <div id="propFirmFields" style="display: none;">
                            <h6 class="text-warning mb-3 mt-4"><i class="fas fa-trophy me-2"></i>Prop Firm Challenge Details</h6>
                            <div class="alert alert-warning mb-3" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Prop Firm Challenge:</strong> Fill in the challenge details below.
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Prop Firm Name * <small class="text-muted">(Which prop firm?)</small></label>
                                    <select class="form-select" name="prop_firm_name" id="prop_firm_name">
                                        <option value="">-- Select Prop Firm --</option>
                                        <option value="FTMO">FTMO</option>
                                        <option value="MyForexFunds">MyForexFunds (MFF)</option>
                                        <option value="The5ers">The5ers</option>
                                        <option value="TopStep">TopStep</option>
                                        <option value="FundedNext">FundedNext</option>
                                        <option value="E8 Markets">E8 Markets</option>
                                        <option value="Blue Guardian">Blue Guardian</option>
                                        <option value="True Forex Funds">True Forex Funds</option>
                                        <option value="SurgeTrader">SurgeTrader</option>
                                        <option value="Apex Trader Funding">Apex Trader Funding</option>
                                        <option value="Other">Other (Specify in notes)</option>
                                    </select>
                                    <small class="text-muted">Select the prop firm you're doing the challenge with</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Challenge Account Number/ID *</label>
                                    <input type="text" class="form-control" name="prop_account_number" id="prop_account_number" placeholder="e.g., FTMO-12345, MFF-67890">
                                    <small class="text-muted">Your challenge account number from the prop firm</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Challenge Fee Paid * <small class="text-muted">(Account purchase fee)</small></label>
                                    <input type="number" class="form-control" name="challenge_fee" id="challenge_fee" step="0.01" min="0" placeholder="0.00" required>
                                    <small class="text-muted">The fee you paid to purchase this challenge (e.g., $49, $99, $299). This is separate from account value.</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Challenge Type</label>
                                    <select class="form-select" name="challenge_type" id="challenge_type">
                                        <option value="">-- Select Type --</option>
                                        <option value="1-Step Challenge">1-Step Challenge</option>
                                        <option value="2-Step Challenge">2-Step Challenge</option>
                                        <option value="Evaluation">Evaluation</option>
                                        <option value="Express">Express</option>
                                    </select>
                                    <small class="text-muted">Type of challenge you're doing</small>
                                </div>
                            </div>
                            <div class="alert alert-info mb-3" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> For prop firm challenges, you have two amounts:
                                <ul class="mb-0 mt-2">
                                    <li><strong>Account Value:</strong> The challenge account balance (e.g., $10,000, $25,000)</li>
                                    <li><strong>Challenge Fee:</strong> The fee you paid to purchase the challenge (e.g., $99, $299)</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Broker Information -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-building me-2"></i>Broker Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Broker Name <small class="text-muted">(Important for loss tracking)</small></label>
                                <input type="text" class="form-control" name="broker_name" id="broker_name" placeholder="e.g., IC Markets, Exness, FTMO, MyForexFunds">
                                <small class="text-muted">Portfolio will show loss breakdown by broker</small>
                            </div>
                            <div class="col-md-6 mb-3" id="regularAccountNumber">
                                <label class="form-label">Account Number/ID</label>
                                <input type="text" class="form-control" name="account_number" id="account_number" placeholder="Your broker account number or ID">
                                <small class="text-muted">Optional: For your reference</small>
                            </div>
                        </div>
                        
                        <!-- Financial Information -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-dollar-sign me-2"></i>Financial Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3" id="initialBalanceField">
                                <label class="form-label">Account Value * <small class="text-muted" id="initialBalanceLabel">(Account balance/initial balance)</small></label>
                                <input type="number" class="form-control" name="initial_balance" id="initial_balance" step="0.01" min="0" placeholder="0.00" required>
                                <small class="text-muted" id="initialBalanceHelp">For prop firm: Challenge account value (e.g., $10,000). For others: Initial balance</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Target Amount <small class="text-muted">(Optional goal)</small></label>
                                <input type="number" class="form-control" name="target_amount" step="0.01" min="0" placeholder="0.00">
                                <small class="text-muted">Your target balance for this account</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Currency *</label>
                                <select class="form-select" name="currency" required>
                                    <option value="USD">USD ($)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="NPR">NPR (रु)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="JPY">JPY (¥)</option>
                                    <option value="AUD">AUD (A$)</option>
                                    <option value="CAD">CAD (C$)</option>
                                </select>
                                <small class="text-muted">Currency for this account</small>
                            </div>
                        </div>
                        
                        <!-- Trading Settings -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-cog me-2"></i>Trading Settings</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Leverage</label>
                                <input type="text" class="form-control" name="leverage" placeholder="e.g., 1:100, 1:500, 1:1000">
                                <small class="text-muted">Optional: Account leverage if applicable</small>
                            </div>
                        </div>
                        
                        <!-- Additional Notes -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-sticky-note me-2"></i>Additional Information</h6>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Any additional information about this account (challenge rules, special conditions, etc.)"></textarea>
                            <small class="text-muted">Optional: Add any relevant notes about this account</small>
                        </div>
                        
                        <div class="alert alert-warning mt-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> After creating the account, you can add trades to it. The current balance will be automatically calculated based on your trades.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" form="accountForm" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>Create Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Account Modal -->
    <div class="modal fade" id="editAccountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Trading Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-4" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Changing the initial balance will recalculate the current balance based on your trades.
                    </div>
                    
                    <form id="editAccountForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="update_account">
                        <input type="hidden" name="account_id" id="edit_account_id">
                        
                        <!-- Basic Information -->
                        <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name *</label>
                                <input type="text" class="form-control" name="account_name" id="edit_account_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Type * <small class="text-muted">(Used for portfolio breakdown)</small></label>
                                <select class="form-select" name="account_type" id="edit_account_type" required>
                                    <option value="forex">Forex</option>
                                    <option value="propfirm">Prop Firm</option>
                                    <option value="nepse">NEPSE</option>
                                    <option value="crypto">Crypto</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Broker Information -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-building me-2"></i>Broker Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Broker Name <small class="text-muted">(Important for loss tracking)</small></label>
                                <input type="text" class="form-control" name="broker_name" id="edit_broker_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number/ID</label>
                                <input type="text" class="form-control" name="account_number" id="edit_account_number">
                            </div>
                        </div>
                        
                        <!-- Financial Information -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-dollar-sign me-2"></i>Financial Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Account Value * <small class="text-muted">(Initial balance/account value)</small></label>
                                <input type="number" class="form-control" name="initial_balance" id="edit_initial_balance" step="0.01" min="0" required>
                                <small class="text-muted">For prop firm: Challenge account value. For others: Initial balance</small>
                            </div>
                            <div class="col-md-4 mb-3" id="editPropFirmFeeRow" style="display: none;">
                                <label class="form-label">Challenge Fee <small class="text-muted">(Account purchase fee)</small></label>
                                <input type="number" class="form-control" name="challenge_fee" id="edit_challenge_fee" step="0.01" min="0" placeholder="0.00">
                                <small class="text-muted">Fee paid to purchase the challenge</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Target Amount <small class="text-muted">(Optional goal)</small></label>
                                <input type="number" class="form-control" name="target_amount" id="edit_target_amount" step="0.01" min="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Currency *</label>
                                <select class="form-select" name="currency" id="edit_currency" required>
                                    <option value="USD">USD ($)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="NPR">NPR (रु)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="JPY">JPY (¥)</option>
                                    <option value="AUD">AUD (A$)</option>
                                    <option value="CAD">CAD (C$)</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Trading Settings & Status -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-cog me-2"></i>Trading Settings & Status</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Leverage</label>
                                <input type="text" class="form-control" name="leverage" id="edit_leverage" placeholder="e.g., 1:100, 1:500">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Status * <small class="text-muted">(Affects portfolio statistics)</small></label>
                                <select class="form-select" name="status" id="edit_status" required>
                                    <option value="active">Active</option>
                                    <option value="ongoing">Ongoing (Prop Firm Challenge)</option>
                                    <option value="breach">Breach (Prop Firm Failed)</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="closed">Closed</option>
                                </select>
                                <small class="text-muted">Status: Active (regular), Ongoing (prop challenge in progress), Breach (prop challenge failed), Closed (completed/withdrawn)</small>
                            </div>
                        </div>
                        
                        <!-- Additional Notes -->
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-sticky-note me-2"></i>Additional Information</h6>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" form="editAccountForm" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Psychology Log Modal -->
    <div class="modal fade" id="addPsychologyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Add Psychology Log</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="psychologyForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="add_psychology_log">
                        <?php if (!empty($accounts)): ?>
                            <div class="mb-3">
                                <label class="form-label">Account</label>
                                <select class="form-select" name="account_id">
                                    <option value="">All Accounts</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>" <?php echo ($selected_account_id == $acc['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($acc['account_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Emotion Type</label>
                            <select class="form-select" name="emotion_type">
                                <option value="">Select</option>
                                <option value="fear">Fear</option>
                                <option value="greed">Greed</option>
                                <option value="confidence">Confidence</option>
                                <option value="anxiety">Anxiety</option>
                                <option value="excitement">Excitement</option>
                                <option value="calm">Calm</option>
                                <option value="frustration">Frustration</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Intensity (1-10)</label>
                            <input type="range" class="form-range" name="intensity" min="1" max="10" value="5" oninput="document.getElementById('intensityValue').textContent = this.value">
                            <div class="text-center"><span id="intensityValue">5</span>/10</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trigger</label>
                            <textarea class="form-control" name="trigger" rows="2" placeholder="What triggered this emotion?"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tags (comma-separated)</label>
                            <input type="text" class="form-control" name="tags" placeholder="stress, pressure, news">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="psychologyForm" class="btn btn-primary">Save Log</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Mistake Log Modal -->
    <div class="modal fade" id="addMistakeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Add Mistake Log</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="mistakeForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="add_mistake_log">
                        <?php if (!empty($accounts)): ?>
                            <div class="mb-3">
                                <label class="form-label">Account</label>
                                <select class="form-select" name="account_id">
                                    <option value="">All Accounts</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>" <?php echo ($selected_account_id == $acc['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($acc['account_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" name="mistake_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mistake Type *</label>
                                <input type="text" class="form-control" name="mistake_type" placeholder="e.g., Overtrading, Revenge Trading" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" required placeholder="Describe what happened..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Impact</label>
                                <select class="form-select" name="impact">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tags (comma-separated)</label>
                                <input type="text" class="form-control" name="tags" placeholder="overtrading, FOMO, revenge">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lesson Learned</label>
                            <textarea class="form-control" name="lesson_learned" rows="2" placeholder="What did you learn from this mistake?"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Action Plan</label>
                            <textarea class="form-control" name="action_plan" rows="2" placeholder="How will you avoid this mistake in the future?"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="mistakeForm" class="btn btn-primary">Save Mistake Log</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Withdrawal Modal -->
    <div class="modal fade" id="addWithdrawalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Add Withdrawal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="withdrawalForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="add_withdrawal">
                        <?php if ($selected_account_id): ?>
                            <input type="hidden" name="account_id" value="<?php echo $selected_account_id; ?>">
                            <?php 
                            $selected_account = null;
                            foreach ($accounts as $acc) {
                                if ($acc['id'] == $selected_account_id) {
                                    $selected_account = $acc;
                                    break;
                                }
                            }
                            ?>
                            <div class="alert alert-info mb-3">
                                <strong>Account:</strong> <?php echo htmlspecialchars($selected_account['account_name'] ?? 'Selected Account'); ?>
                                <br>
                                <strong>Current Balance:</strong> <?php echo $selected_account['currency'] ?? 'USD'; ?> <?php echo number_format($selected_account['current_balance'] ?? 0, 2); ?>
                            </div>
                        <?php elseif (!empty($accounts)): ?>
                            <div class="mb-3">
                                <label class="form-label">Account *</label>
                                <select class="form-select" name="account_id" required>
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>">
                                            <?php echo htmlspecialchars($acc['account_name']); ?> (<?php echo $acc['currency']; ?> <?php echo number_format($acc['current_balance'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Withdrawal Amount *</label>
                                <input type="number" class="form-control" name="withdrawal_amount" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Currency *</label>
                                <select class="form-select" name="currency" required>
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
                                <label class="form-label">Platform *</label>
                                <select class="form-select" name="platform" required>
                                    <option value="">-- Select Platform --</option>
                                    <option value="rise">Rise</option>
                                    <option value="bank">Bank</option>
                                    <option value="crypto">Crypto</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Withdrawal Date *</label>
                                <input type="date" class="form-control" name="withdrawal_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Platform Details</label>
                            <input type="text" class="form-control" name="platform_details" placeholder="e.g., Bank name, Crypto wallet address, etc.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Additional notes about this withdrawal..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="withdrawalForm" class="btn btn-success">Save Withdrawal</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Review Modal -->
    <div class="modal fade" id="addReviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Add Weekly/Monthly Review</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="reviewForm" action="journal.php" method="POST">
                        <input type="hidden" name="action" value="add_review">
                        <?php if (!empty($accounts)): ?>
                            <div class="mb-3">
                                <label class="form-label">Account *</label>
                                <select class="form-select" name="account_id" required>
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>" <?php echo ($selected_account_id == $acc['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($acc['account_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Review Type *</label>
                                <select class="form-select" name="review_type" required>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Period Start *</label>
                                <input type="date" class="form-control" name="review_period_start" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Period End *</label>
                                <input type="date" class="form-control" name="review_period_end" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">What Went Well</label>
                            <textarea class="form-control" name="what_went_well" rows="3" placeholder="Things that went well this period..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">What Went Wrong</label>
                            <textarea class="form-control" name="what_went_wrong" rows="3" placeholder="Things that didn't go well..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lessons Learned</label>
                            <textarea class="form-control" name="lessons_learned" rows="3" placeholder="Key lessons from this period..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Goals for Next Period</label>
                            <textarea class="form-control" name="goals_for_next_period" rows="2" placeholder="What are your goals for the next period?"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Action Items (one per line)</label>
                            <textarea class="form-control" name="action_items" rows="3" placeholder="Action item 1&#10;Action item 2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Self Rating (1-10)</label>
                            <input type="range" class="form-range" name="self_rating" min="1" max="10" value="5" oninput="document.getElementById('ratingValue').textContent = this.value">
                            <div class="text-center"><span id="ratingValue">5</span>/10</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="reviewForm" class="btn btn-primary">Save Review</button>
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
            try {
                console.log('Editing account:', account);
                
                document.getElementById('edit_account_id').value = account.id || '';
                document.getElementById('edit_account_name').value = account.account_name || '';
                document.getElementById('edit_account_type').value = account.account_type || 'forex';
                document.getElementById('edit_broker_name').value = account.broker_name || '';
                document.getElementById('edit_account_number').value = account.account_number || '';
                document.getElementById('edit_initial_balance').value = account.initial_balance || '0';
                document.getElementById('edit_target_amount').value = account.target_amount || '';
                document.getElementById('edit_currency').value = account.currency || 'USD';
                document.getElementById('edit_leverage').value = account.leverage || '';
                document.getElementById('edit_status').value = account.status || 'active';
                document.getElementById('edit_notes').value = account.notes || '';
                
                // Show/hide challenge fee field based on account type
                const challengeFeeField = document.getElementById('editPropFirmFeeRow');
                if (account.account_type === 'propfirm') {
                    if (challengeFeeField) challengeFeeField.style.display = 'block';
                    const challengeFeeInput = document.getElementById('edit_challenge_fee');
                    if (challengeFeeInput) {
                        challengeFeeInput.value = account.challenge_fee || '0';
                    }
                } else {
                    if (challengeFeeField) challengeFeeField.style.display = 'none';
                }
                
                // Update account type change handler
                const editAccountType = document.getElementById('edit_account_type');
                if (editAccountType) {
                    // Remove all existing listeners by cloning
                    const newEditAccountType = editAccountType.cloneNode(true);
                    editAccountType.parentNode.replaceChild(newEditAccountType, editAccountType);
                    
                    // Re-attach change handler
                    const newElement = document.getElementById('edit_account_type');
                    if (newElement) {
                        newElement.addEventListener('change', function() {
                            const feeField = document.getElementById('editPropFirmFeeRow');
                            if (this.value === 'propfirm') {
                                if (feeField) feeField.style.display = 'block';
                            } else {
                                if (feeField) feeField.style.display = 'none';
                            }
                        });
                    }
                }
                
                const editModalElement = document.getElementById('editAccountModal');
                if (editModalElement) {
                    const editModal = new bootstrap.Modal(editModalElement);
                    editModal.show();
                } else {
                    console.error('Edit modal element not found');
                    alert('Error: Edit modal not found. Please refresh the page.');
                }
            } catch (error) {
                console.error('Error in editAccount:', error);
                alert('Error opening edit modal: ' + error.message);
            }
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
        // Get balance history (compatible with MySQL 5.7+)
        $history_stmt = $pdo->prepare("
            SELECT 
                DATE(trade_date) as date,
                profit_loss
            FROM trading_journal
            WHERE account_id = ? AND profit_loss IS NOT NULL
            ORDER BY trade_date ASC
        ");
        $history_stmt->execute([$selected_account_id]);
        $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $dates = [];
        $balances = [];
        $running_balance = floatval($account_stats['initial_balance']);
        
        // Add initial balance point
        $dates[] = date('M d', strtotime($account_stats['created_at']));
        $balances[] = $running_balance;
        
        if (!empty($history)) {
            foreach ($history as $h) {
                $running_balance += floatval($h['profit_loss']);
                $dates[] = date('M d', strtotime($h['date']));
                $balances[] = $running_balance;
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
        
        // Equity Curve Chart
        <?php if ($account_stats && $selected_account_id && $active_tab === 'stats'): ?>
        const equityCtx = document.getElementById('equityChart');
        if (equityCtx) {
            <?php
            // Get equity curve data (compatible with MySQL 5.7+)
            $equity_stmt = $pdo->prepare("
                SELECT 
                    DATE(trade_date) as date,
                    profit_loss
                FROM trading_journal
                WHERE account_id = ? AND profit_loss IS NOT NULL
                ORDER BY trade_date ASC
            ");
            $equity_stmt->execute([$selected_account_id]);
            $equity_data = $equity_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $equity_dates = [];
            $equity_balances = [];
            $running_balance = floatval($account_stats['initial_balance']);
            
            // Add initial balance
            $equity_dates[] = date('M d', strtotime($account_stats['created_at']));
            $equity_balances[] = $running_balance;
            
            foreach ($equity_data as $e) {
                $running_balance += floatval($e['profit_loss']);
                $equity_dates[] = date('M d', strtotime($e['date']));
                $equity_balances[] = $running_balance;
            }
            ?>
            
            new Chart(equityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($equity_dates); ?>,
                    datasets: [{
                        label: 'Equity Curve',
                        data: <?php echo json_encode($equity_balances); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 5
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
        
        // Toggle Prop Firm Fields
        function togglePropFirmFields() {
            const accountType = document.getElementById('account_type');
            const propFirmFields = document.getElementById('propFirmFields');
            const regularAccountNumber = document.getElementById('regularAccountNumber');
            const brokerName = document.getElementById('broker_name');
            const accountNumber = document.getElementById('account_number');
            const propFirmName = document.getElementById('prop_firm_name');
            const propAccountNumber = document.getElementById('prop_account_number');
            const challengeFee = document.getElementById('challenge_fee');
            const initialBalance = document.querySelector('input[name="initial_balance"]');
            
            if (accountType && accountType.value === 'propfirm') {
                // Show prop firm fields
                if (propFirmFields) propFirmFields.style.display = 'block';
                if (regularAccountNumber) regularAccountNumber.style.display = 'none';
                
                // Make prop firm fields required
                if (propFirmName) propFirmName.setAttribute('required', 'required');
                if (propAccountNumber) propAccountNumber.setAttribute('required', 'required');
                if (challengeFee) challengeFee.setAttribute('required', 'required');
                
                // Set default status to ongoing for prop firm
                const accountStatus = document.getElementById('account_status');
                if (accountStatus) {
                    accountStatus.value = 'ongoing';
                }
                
                // Update label for prop firm
                const initialBalanceLabel = document.getElementById('initialBalanceLabel');
                const initialBalanceHelp = document.getElementById('initialBalanceHelp');
                if (initialBalanceLabel) {
                    initialBalanceLabel.textContent = '(Challenge account value)';
                }
                if (initialBalanceHelp) {
                    initialBalanceHelp.textContent = 'Challenge account value (e.g., $10,000, $25,000). Challenge fee is separate.';
                }
            } else {
                // Set default status to active for non-prop firm
                const accountStatus = document.getElementById('account_status');
                if (accountStatus) {
                    accountStatus.value = 'active';
                }
                
                // Reset labels
                const initialBalanceLabel = document.getElementById('initialBalanceLabel');
                const initialBalanceHelp = document.getElementById('initialBalanceHelp');
                if (initialBalanceLabel) {
                    initialBalanceLabel.textContent = '(Account balance/initial balance)';
                }
                if (initialBalanceHelp) {
                    initialBalanceHelp.textContent = 'For prop firm: Challenge account value (e.g., $10,000). For others: Initial balance';
                }
                // Hide prop firm fields
                if (propFirmFields) propFirmFields.style.display = 'none';
                if (regularAccountNumber) regularAccountNumber.style.display = 'block';
                
                // Remove required from prop firm fields
                if (propFirmName) propFirmName.removeAttribute('required');
                if (propAccountNumber) propAccountNumber.removeAttribute('required');
                if (challengeFee) challengeFee.removeAttribute('required');
                
                // Clear prop firm fields
                if (propFirmName) propFirmName.value = '';
                if (propAccountNumber) propAccountNumber.value = '';
                if (challengeFee) challengeFee.value = '';
            }
        }
        
        // Initialize on page load and set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            togglePropFirmFields();
            
            // Auto-fill broker name when prop firm is selected
            const propFirmName = document.getElementById('prop_firm_name');
            const brokerName = document.getElementById('broker_name');
            if (propFirmName && brokerName) {
                propFirmName.addEventListener('change', function() {
                    if (this.value && this.value !== 'Other') {
                        brokerName.value = this.value;
                    }
                });
            }
            
            // Auto-fill account number
            const propAccountNumber = document.getElementById('prop_account_number');
            const accountNumber = document.getElementById('account_number');
            if (propAccountNumber && accountNumber) {
                propAccountNumber.addEventListener('input', function() {
                    accountNumber.value = this.value;
                });
            }
            
            // Note: Challenge fee and initial balance are separate for prop firms
            // Challenge fee = money paid to purchase challenge
            // Initial balance = challenge account value (e.g., $10,000)
        });
        
        // View Trade Function
        function viewTrade(tradeId) {
            // You can implement a modal to show full trade details
            alert('View trade details for ID: ' + tradeId);
            // TODO: Implement detailed trade view modal
        }
        
        function deleteTrade(tradeId) {
            if (confirm('Are you sure you want to delete this trade?')) {
                const accountId = <?php echo $selected_account_id ? $selected_account_id : 'null'; ?>;
                const tab = '<?php echo $active_tab; ?>';
                const url = 'journal.php?delete_trade=' + tradeId + (accountId ? '&account_id=' + accountId : '') + '&tab=' + tab;
                window.location.href = url;
            }
        }
    </script>
    
    <!-- AI Chat Widget -->
    <?php include '../includes/ai-chat-widget.php'; ?>
</body>
</html>