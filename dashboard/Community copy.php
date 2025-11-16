<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__.'/../config/database.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$trading_strategies = [
    [
        'name' => 'Breakout Trading',
        'description' => 'Trade breakouts from key support/resistance levels',
        'success_rate' => 65,
        'risk_level' => 'Medium',
        'timeframe' => 'Intraday/Swing'
    ],
    [
        'name' => 'Moving Average Crossover',
        'description' => 'Use MA crossovers to identify trend changes',
        'success_rate' => 60,
        'risk_level' => 'Low',
        'timeframe' => 'Swing/Position'
    ],
    [
        'name' => 'RSI Divergence',
        'description' => 'Spot hidden divergences for reversal trades',
        'success_rate' => 70,
        'risk_level' => 'Medium',
        'timeframe' => 'Intraday'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Strategies - NpLTrader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
        }
        
        body {
            background-color: var(--dark-bg);
            color: white;
        }
        
        .strategy-card {
            background: var(--dark-card);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #334155;
            transition: all 0.3s ease;
        }
        
        .strategy-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .success-rate {
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="mb-4">
                <h1 class="h2">Trading Strategies</h1>
                <p class="text-muted">Learn and implement proven trading strategies</p>
            </div>

            <!-- Strategy Cards -->
            <div class="row">
                <?php foreach ($trading_strategies as $strategy): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="strategy-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="mb-0"><?php echo $strategy['name']; ?></h5>
                                <span class="badge bg-<?php 
                                    echo $strategy['risk_level'] === 'Low' ? 'success' : 
                                         ($strategy['risk_level'] === 'Medium' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo $strategy['risk_level']; ?> Risk
                                </span>
                            </div>
                            
                            <p class="text-muted mb-3"><?php echo $strategy['description']; ?></p>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="success-rate text-primary">
                                        <?php echo $strategy['success_rate']; ?>%
                                    </div>
                                    <small class="text-muted">Success Rate</small>
                                </div>
                                <div class="col-6">
                                    <div class="success-rate text-info">
                                        <?php echo $strategy['timeframe']; ?>
                                    </div>
                                    <small class="text-muted">Timeframe</small>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-book me-2"></i>Learn Strategy
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Strategy Implementation -->
            <div class="strategy-card mt-4">
                <h5 class="mb-3">Strategy Performance Tracking</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Strategy</th>
                                <th>Trades</th>
                                <th>Win Rate</th>
                                <th>Avg P/L</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Breakout Trading</td>
                                <td>15</td>
                                <td>67%</td>
                                <td class="text-success">+रु 1,250</td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">Analyze</button>
                                </td>
                            </tr>
                            <tr>
                                <td>MA Crossover</td>
                                <td>8</td>
                                <td>50%</td>
                                <td class="text-danger">-रु 450</td>
                                <td><span class="badge bg-warning">Testing</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">Analyze</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>