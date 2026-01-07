<?php
require_once __DIR__.'/auth.php';

// Get filters
$user_filter = $_GET['user_id'] ?? '';
$symbol_filter = $_GET['symbol'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = ["1=1"];
$params = [];

if (!empty($user_filter)) {
    $where_conditions[] = "j.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($symbol_filter)) {
    $where_conditions[] = "j.symbol LIKE ?";
    $params[] = "%$symbol_filter%";
}

if (!empty($date_from)) {
    $where_conditions[] = "j.trade_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "j.trade_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// Get trades
$trades_stmt = $pdo->prepare("
    SELECT j.*, u.username, a.account_name 
    FROM trading_journal j 
    LEFT JOIN users u ON j.user_id = u.id 
    LEFT JOIN trading_accounts a ON j.account_id = a.id 
    WHERE $where_clause
    ORDER BY j.trade_date DESC, j.id DESC
    LIMIT 500
");
$trades_stmt->execute($params);
$trades = $trades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for filter
$users_list = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_trades = $pdo->query("SELECT COUNT(*) FROM trading_journal")->fetchColumn();
$total_profit = $pdo->query("SELECT SUM(profit_loss) FROM trading_journal WHERE profit_loss IS NOT NULL")->fetchColumn() ?? 0;
$winning_trades = $pdo->query("SELECT COUNT(*) FROM trading_journal WHERE profit_loss > 0")->fetchColumn();
$losing_trades = $pdo->query("SELECT COUNT(*) FROM trading_journal WHERE profit_loss < 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Journal Overview - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__.'/styles.php'; ?>
</head>
<body>
    <?php include __DIR__.'/sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-chart-line me-2"></i>Trading Journal Overview</h1>
                <p class="text-muted mb-0">View all trades from all users</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_trades); ?></div>
                    <div class="stat-label">Total Trades</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon <?php echo $total_profit >= 0 ? 'primary' : 'danger'; ?>">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">रु <?php echo number_format($total_profit, 2); ?></div>
                    <div class="stat-label">Total P/L</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($winning_trades); ?></div>
                    <div class="stat-label">Winning Trades</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($losing_trades); ?></div>
                    <div class="stat-label">Losing Trades</div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control" name="symbol" placeholder="Symbol..." value="<?php echo htmlspecialchars($symbol_filter); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                    <a href="trades.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Trades Table -->
        <div class="card">
            <div class="card-header">
                <h5>All Trades (<?php echo count($trades); ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Account</th>
                            <th>Date</th>
                            <th>Symbol</th>
                            <th>Type</th>
                            <th>Entry</th>
                            <th>Exit</th>
                            <th>P/L</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trades)): ?>
                            <tr><td colspan="9" class="text-center text-muted">No trades found</td></tr>
                        <?php else: ?>
                            <?php foreach ($trades as $trade): ?>
                                <tr>
                                    <td><?php echo $trade['id']; ?></td>
                                    <td><?php echo htmlspecialchars($trade['username'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($trade['account_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($trade['trade_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($trade['symbol']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $trade['trade_type'] === 'buy' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($trade['trade_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($trade['entry_price'], 2); ?></td>
                                    <td><?php echo $trade['exit_price'] ? number_format($trade['exit_price'], 2) : '-'; ?></td>
                                    <td>
                                        <?php if ($trade['profit_loss'] !== null): ?>
                                            <span class="text-<?php echo $trade['profit_loss'] >= 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $trade['profit_loss'] >= 0 ? '+' : ''; ?><?php echo number_format($trade['profit_loss'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

