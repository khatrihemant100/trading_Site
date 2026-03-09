<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__.'/dashboard_mode.php';

$is_demo = is_demo_mode();
$mode_name = get_mode_name();
$user_id = $_SESSION['user_id'];
$selected_account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;

// सबै trading_accounts load (filtered by Real/Demo mode)
$columns_check_demo = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'is_demo'")->fetch();
if ($columns_check_demo) {
    $accounts_stmt = $pdo->prepare("SELECT id, account_name, account_type FROM trading_accounts WHERE user_id = ? AND is_demo = ? ORDER BY created_at DESC");
    $accounts_stmt->execute([$user_id, $is_demo]);
} else {
    $accounts_stmt = $pdo->prepare("SELECT id, account_name, account_type FROM trading_accounts WHERE user_id = ? ORDER BY created_at DESC");
    $accounts_stmt->execute([$user_id]);
}
$accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);

// trade list + stats
$trades = [];
$stats = [
    'total_trades'   => 0,
    'winning_trades' => 0,
    'losing_trades'  => 0,
    'total_profit'   => 0.0,
    'best_trade'     => 0.0,
    'worst_trade'    => 0.0,
    'win_rate'       => 0.0,
    'avg_profit'     => 0.0,
];

if ($selected_account_id > 0) {
    $trades_stmt = $pdo->prepare("
        SELECT * FROM mt5_trades
        WHERE user_id = ? AND account_id = ?
        ORDER BY close_time ASC
    ");
    $trades_stmt->execute([$user_id, $selected_account_id]);
    $trades = $trades_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($trades) {
        $profits = array_column($trades, 'profit');
        $stats['total_trades']   = count($trades);
        $stats['total_profit']   = array_sum($profits);
        $stats['best_trade']     = max($profits);
        $stats['worst_trade']    = min($profits);
        $stats['winning_trades'] = count(array_filter($profits, fn($p) => $p > 0));
        $stats['losing_trades']  = count(array_filter($profits, fn($p) => $p < 0));
        $stats['win_rate']       = $stats['total_trades'] > 0
            ? ($stats['winning_trades'] / $stats['total_trades']) * 100
            : 0.0;
        $stats['avg_profit']     = $stats['total_trades'] > 0
            ? $stats['total_profit'] / $stats['total_trades']
            : 0.0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MT5 History - NpLTrader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container py-4">
    <h2 class="mb-3">MT5 Trade History & PnL</h2>

    <!-- Account chooser -->
    <form method="get" class="mb-3">
        <label class="form-label">Select Trading Account</label>
        <select name="account_id" class="form-select" onchange="this.form.submit()">
            <option value="0">-- छान्नुहोस् --</option>
            <?php foreach ($accounts as $acc): ?>
                <option value="<?php echo (int)$acc['id']; ?>"
                    <?php echo $selected_account_id == $acc['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($acc['account_name'] . ' (' . $acc['account_type'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selected_account_id > 0): ?>
        <!-- Summary stats -->
        <div class="row mb-3">
            <div class="col-md-3"><div class="card bg-secondary"><div class="card-body">
                <small>Total Trades</small><h4><?php echo $stats['total_trades']; ?></h4>
            </div></div></div>
            <div class="col-md-3"><div class="card bg-secondary"><div class="card-body">
                <small>Total PnL</small><h4><?php echo number_format($stats['total_profit'], 2); ?></h4>
            </div></div></div>
            <div class="col-md-3"><div class="card bg-secondary"><div class="card-body">
                <small>Win Rate</small><h4><?php echo number_format($stats['win_rate'], 1); ?>%</h4>
            </div></div></div>
            <div class="col-md-3"><div class="card bg-secondary"><div class="card-body">
                <small>Avg PnL / Trade</small><h4><?php echo number_format($stats['avg_profit'], 2); ?></h4>
            </div></div></div>
        </div>

        <!-- Trade table -->
        <div class="table-responsive">
            <table class="table table-dark table-striped table-sm">
                <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Symbol</th>
                    <th>Type</th>
                    <th>Volume</th>
                    <th>Open Time</th>
                    <th>Close Time</th>
                    <th>Open</th>
                    <th>Close</th>
                    <th>Profit</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($trades as $t): ?>
                    <tr>
                        <td><?php echo (int)$t['ticket']; ?></td>
                        <td><?php echo htmlspecialchars($t['symbol']); ?></td>
                        <td><?php echo htmlspecialchars($t['order_type']); ?></td>
                        <td><?php echo number_format($t['volume'], 2); ?></td>
                        <td><?php echo htmlspecialchars($t['open_time']); ?></td>
                        <td><?php echo htmlspecialchars($t['close_time']); ?></td>
                        <td><?php echo number_format($t['open_price'], 5); ?></td>
                        <td><?php echo number_format($t['close_price'], 5); ?></td>
                        <td class="<?php echo $t['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($t['profit'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>