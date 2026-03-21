<?php
session_start();

// User login check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/dashboard_mode.php';

$is_demo = is_demo_mode();
$mode_name = get_mode_name();

$error = '';
$success = '';
$mt5_error = '';
$mt5_success = '';

// MT5 backend API base URL (FastAPI service)
// यो URL लाई आफ्नो Python MT5 backend जहाँ चलिराखेको छ त्यहाँ अनुसार change गर्नुस्।
$MT5_API_BASE = 'http://127.0.0.1:8000/api/v1';

// Simple helper to call MT5 backend for connect + sync
function mt5_connect_account(
    string $apiBase,
    string $accountNumber,
    string $brokerServer,
    string $investorPassword,
    int $linkedAccountId,
    int $userId,
    ?string $jwtToken = null,
    string $syncMethod = 'cloud',
    string $cloudApiKey = '',
    string $cloudApiUrl = ''
): array
{
    $url = rtrim($apiBase, '/') . '/accounts/connect';

    $payload = [
        'account_number'    => $accountNumber,
        'broker_server'     => $brokerServer,
        'investor_password' => $investorPassword,
        'account_id'        => $linkedAccountId,
        'user_id'           => $userId,
        'sync_method'       => $syncMethod,
    ];
    
    if ($syncMethod === 'cloud') {
        if ($cloudApiKey) {
            $payload['cloud_api_key'] = $cloudApiKey;
        }
        if ($cloudApiUrl) {
            $payload['cloud_api_url'] = $cloudApiUrl;
        }
    }

    $headers = ['Content-Type: application/json'];
    if ($jwtToken) {
        $headers[] = 'Authorization: Bearer ' . $jwtToken;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($responseBody === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'message' => 'MT5 API error: ' . $error];
    }

    curl_close($ch);

    $data = json_decode($responseBody, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }

    $message = $data['detail'] ?? ('MT5 API HTTP ' . $httpCode);
    return ['success' => false, 'message' => $message];
}

// Handle new manual trading account submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_account'])) {
    $account_name   = trim($_POST['account_name'] ?? '');
    $account_type   = $_POST['account_type'] ?? '';
    $broker_name    = trim($_POST['broker_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $initial_balance = $_POST['initial_balance'] !== '' ? (float)$_POST['initial_balance'] : 0;
    $currency       = trim($_POST['currency'] ?? 'USD');
    $leverage       = trim($_POST['leverage'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');

    if ($account_name === '' || $account_type === '') {
        $error = "Account name र account type अनिवार्य छन्।";
    } else {
        try {
            // Check if is_demo column exists
            $columns_check = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'is_demo'")->fetch();
            if ($columns_check) {
                $stmt = $pdo->prepare("
                    INSERT INTO trading_accounts 
                        (user_id, is_demo, account_name, account_type, broker_name, account_number, initial_balance, current_balance, currency, leverage, notes) 
                    VALUES 
                        (:user_id, :is_demo, :account_name, :account_type, :broker_name, :account_number, :initial_balance, :current_balance, :currency, :leverage, :notes)
                ");
                $stmt->execute([
                    ':user_id'         => $_SESSION['user_id'],
                    ':is_demo'         => $is_demo ? 1 : 0,
                    ':account_name'    => $account_name,
                    ':account_type'    => $account_type,
                    ':broker_name'     => $broker_name,
                    ':account_number'  => $account_number,
                    ':initial_balance' => $initial_balance,
                    ':current_balance' => $initial_balance,
                    ':currency'        => $currency,
                    ':leverage'        => $leverage,
                    ':notes'           => $notes,
                ]);
            } else {
                // Fallback if column doesn't exist yet
                $stmt = $pdo->prepare("
                    INSERT INTO trading_accounts 
                        (user_id, account_name, account_type, broker_name, account_number, initial_balance, current_balance, currency, leverage, notes) 
                    VALUES 
                        (:user_id, :account_name, :account_type, :broker_name, :account_number, :initial_balance, :current_balance, :currency, :leverage, :notes)
                ");
                $stmt->execute([
                    ':user_id'         => $_SESSION['user_id'],
                    ':account_name'    => $account_name,
                    ':account_type'    => $account_type,
                    ':broker_name'     => $broker_name,
                    ':account_number'  => $account_number,
                    ':initial_balance' => $initial_balance,
                    ':current_balance' => $initial_balance,
                    ':currency'        => $currency,
                    ':leverage'        => $leverage,
                    ':notes'           => $notes,
                ]);
            }
            $success = ucfirst($mode_name) . " trading account सफलतापूर्वक थपियो!";
        } catch (PDOException $e) {
            $error = "डाटाबेस त्रुटि: " . $e->getMessage();
        }
    }
}

// Handle MT5 connect submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mt5_connect'])) {
    $mt5_account_number = trim($_POST['mt5_account_number'] ?? '');
    $mt5_broker_server  = trim($_POST['mt5_broker_server'] ?? '');
    $mt5_password       = trim($_POST['mt5_investor_password'] ?? '');
<<<<<<< HEAD
    $mt5_linked_account_id = isset($_POST['mt5_linked_account_id']) ? (int)$_POST['mt5_linked_account_id'] : 0;
    $mt5_sync_method    = trim($_POST['mt5_sync_method'] ?? 'cloud');
    $mt5_cloud_api_key  = trim($_POST['mt5_cloud_api_key'] ?? '');
    $mt5_cloud_api_url  = trim($_POST['mt5_cloud_api_url'] ?? '');

    if ($mt5_account_number === '' || $mt5_broker_server === '' || $mt5_password === '') {
        $mt5_error = "MT5 Account ID, Broker server र Investor password सबै अनिवार्य छन्।";
    } elseif ($mt5_linked_account_id <= 0) {
        $mt5_error = "पहिले माथिको सूचीबाट कुन Trading Account सँग MT5 history link गर्ने हो, त्यो छान्नुहोस्।";
    } else {
        $jwtToken = null;

        $result = mt5_connect_account(
            $MT5_API_BASE,
            $mt5_account_number,
            $mt5_broker_server,
            $mt5_password,
            $mt5_linked_account_id,
            (int)$_SESSION['user_id'],
            $jwtToken,
            $mt5_sync_method,
            $mt5_cloud_api_key,
            $mt5_cloud_api_url
        );
        if ($result['success']) {
            $mt5_success = "MT5 account सफलतापूर्वक connect भयो (" . ($mt5_sync_method === 'cloud' ? 'Cloud Sync' : 'Local Sync') . ")। Backend बाट trade history sync हुन्छ।";
        } else {
            $mt5_error = "MT5 connect असफल: " . $result['message'];
=======
    $mt5_linked_account_id = 0;

    if ($mt5_account_number === '' || $mt5_broker_server === '' || $mt5_password === '') {
        $mt5_error = "MT5 Account ID, Broker server र Investor password सबै अनिवार्य छन्।";
    } else {
        try {
            // Auto-link by MT5 login/server; if not found, create a dedicated trading account.
            $columns_check = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'is_demo'")->fetch();
            if ($columns_check) {
                $find_account_stmt = $pdo->prepare("
                    SELECT id
                    FROM trading_accounts
                    WHERE user_id = ?
                      AND is_demo = ?
                      AND account_number = ?
                      AND broker_name = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $find_account_stmt->execute([
                    $_SESSION['user_id'],
                    $is_demo ? 1 : 0,
                    $mt5_account_number,
                    $mt5_broker_server,
                ]);
            } else {
                $find_account_stmt = $pdo->prepare("
                    SELECT id
                    FROM trading_accounts
                    WHERE user_id = ?
                      AND account_number = ?
                      AND broker_name = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $find_account_stmt->execute([
                    $_SESSION['user_id'],
                    $mt5_account_number,
                    $mt5_broker_server,
                ]);
            }

            $existing_account = $find_account_stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_account) {
                $mt5_linked_account_id = (int)$existing_account['id'];
            } else {
                if ($columns_check) {
                    $create_account_stmt = $pdo->prepare("
                        INSERT INTO trading_accounts
                            (user_id, is_demo, account_name, account_type, broker_name, account_number, initial_balance, current_balance, currency, leverage, notes)
                        VALUES
                            (:user_id, :is_demo, :account_name, :account_type, :broker_name, :account_number, :initial_balance, :current_balance, :currency, :leverage, :notes)
                    ");
                    $create_account_stmt->execute([
                        ':user_id'         => $_SESSION['user_id'],
                        ':is_demo'         => $is_demo ? 1 : 0,
                        ':account_name'    => 'MT5 ' . $mt5_account_number,
                        ':account_type'    => 'forex',
                        ':broker_name'     => $mt5_broker_server,
                        ':account_number'  => $mt5_account_number,
                        ':initial_balance' => 0,
                        ':current_balance' => 0,
                        ':currency'        => 'USD',
                        ':leverage'        => '',
                        ':notes'           => 'Auto-created while connecting MT5 account.',
                    ]);
                } else {
                    $create_account_stmt = $pdo->prepare("
                        INSERT INTO trading_accounts
                            (user_id, account_name, account_type, broker_name, account_number, initial_balance, current_balance, currency, leverage, notes)
                        VALUES
                            (:user_id, :account_name, :account_type, :broker_name, :account_number, :initial_balance, :current_balance, :currency, :leverage, :notes)
                    ");
                    $create_account_stmt->execute([
                        ':user_id'         => $_SESSION['user_id'],
                        ':account_name'    => 'MT5 ' . $mt5_account_number,
                        ':account_type'    => 'forex',
                        ':broker_name'     => $mt5_broker_server,
                        ':account_number'  => $mt5_account_number,
                        ':initial_balance' => 0,
                        ':current_balance' => 0,
                        ':currency'        => 'USD',
                        ':leverage'        => '',
                        ':notes'           => 'Auto-created while connecting MT5 account.',
                    ]);
                }
                $mt5_linked_account_id = (int)$pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            $mt5_error = "MT5 link account create/find असफल: " . $e->getMessage();
        }

        if (!$mt5_error) {
            $jwtToken = null;
            $result = mt5_connect_account(
                $MT5_API_BASE,
                $mt5_account_number,
                $mt5_broker_server,
                $mt5_password,
                $mt5_linked_account_id,
                (int)$_SESSION['user_id'],
                $jwtToken
            );
            if ($result['success']) {
                $mt5_success = "MT5 account सफलतापूर्वक connect भयो। Backend बाट trade history sync हुन्छ।";
            } else {
                $mt5_error = "MT5 connect असफल: " . $result['message'];
            }
>>>>>>> d01e1cd (update)
        }
    }
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: ../logout.php");
        exit();
    }

    // Fetch user's trading accounts (filtered by Real/Demo mode)
    $columns_check = $pdo->query("SHOW COLUMNS FROM trading_accounts LIKE 'is_demo'")->fetch();
    if ($columns_check) {
        $accounts_stmt = $pdo->prepare("
            SELECT * 
            FROM trading_accounts 
            WHERE user_id = ? AND is_demo = ?
            ORDER BY created_at DESC
        ");
        $accounts_stmt->execute([$_SESSION['user_id'], $is_demo]);
    } else {
        // Fallback if column doesn't exist
        $accounts_stmt = $pdo->prepare("
            SELECT * 
            FROM trading_accounts 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $accounts_stmt->execute([$_SESSION['user_id']]);
    }
    $accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch connected MT5 accounts (like TradeFXBook)
    $mt5_accounts_stmt = $pdo->prepare("
        SELECT m.*, t.account_name, t.account_type
        FROM mt5_accounts m
        INNER JOIN trading_accounts t ON m.account_id = t.id
        WHERE m.user_id = ? AND m.is_active = 1
        ORDER BY m.last_sync_at DESC, m.created_at DESC
    ");
    $mt5_accounts_stmt->execute([$_SESSION['user_id']]);
    $mt5_connected = $mt5_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("डाटाबेस त्रुटि: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Trading Accounts - NpLTrader</title>
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
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

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
        }

        .top-navbar .nav-link.active {
            background-color: var(--primary) !important;
            color: #ffffff !important;
            border-radius: 6px;
        }

        .page-header {
            margin-top: 2rem;
            margin-bottom: 1.5rem;
        }

        .card-dark {
            background-color: var(--dark-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .badge-status-active {
            background-color: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
        }

        .badge-status-inactive {
            background-color: rgba(148, 163, 184, 0.15);
            color: #e5e7eb;
        }

        .badge-status-closed {
            background-color: rgba(239, 68, 68, 0.15);
            color: #fecaca;
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
    </style>
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

    <div class="container">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h2 class="mb-0">My Trading Accounts</h2>
                    <?php echo get_mode_badge(); ?>
                </div>
                <p class="text-muted mb-0" style="color: var(--text-secondary) !important;">
                    यहाँबाट तपाईँले आफ्ना सबै <?php echo strtolower($mode_name); ?> trading accounts व्यवस्थापन गर्न सक्नुहुन्छ।
                </p>
                <div class="mt-2">
                    <small class="text-muted" style="color: var(--text-secondary) !important;">
                        <a href="dashboard.php?mode=<?php echo $is_demo ? 'real' : 'demo'; ?>" class="text-primary text-decoration-none">
                            <i class="fas fa-exchange-alt me-1"></i>Switch to <?php echo $is_demo ? 'Real' : 'Demo'; ?> Dashboard
                        </a>
                    </small>
                </div>
            </div>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                <i class="fas fa-plus me-2"></i>Add Trading Account
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-dark mb-4">
            <div class="card-body">
                <?php if (empty($accounts)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-wallet fa-3x mb-3 text-secondary"></i>
                        <h5 class="mb-2">अहिले सम्म कुनै trading account जोडिएको छैन।</h5>
                        <p class="text-muted mb-3" style="color: var(--text-secondary) !important;">
                            सुरु गर्नको लागि "Add Trading Account" बटन थिच्नुहोस्।
                        </p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                            <i class="fas fa-plus me-2"></i>Add Trading Account
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Account Name</th>
                                    <th>Type</th>
                                    <th>Broker</th>
                                    <th>Account #</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $acc): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($acc['account_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary text-uppercase">
                                                <?php echo htmlspecialchars($acc['account_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($acc['broker_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($acc['account_number'] ?? '-'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($acc['currency'] ?? 'USD'); ?>
                                            <?php echo number_format((float)$acc['current_balance'], 2); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $acc['status'] ?? 'active';
                                            $badgeClass = 'badge-status-active';
                                            if ($status === 'inactive') {
                                                $badgeClass = 'badge-status-inactive';
                                            } elseif ($status === 'closed') {
                                                $badgeClass = 'badge-status-closed';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> text-uppercase">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($acc['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Account Modal -->
    <div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content card-dark">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAccountModalLabel">
                        <i class="fas fa-wallet me-2 text-primary"></i>नयाँ Trading Account थप्नुहोस्
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Account Name *</label>
                                <input type="text" name="account_name" class="form-control" required placeholder="जस्तै: FTMO Challenge, Personal Forex Account">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Type *</label>
                                <select name="account_type" class="form-select" required>
                                    <option value="">छान्नुहोस्...</option>
                                    <option value="forex">Forex</option>
                                    <option value="propfirm">Prop Firm</option>
                                    <option value="nepse">NEPSE</option>
                                    <option value="crypto">Crypto</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Broker / Platform</label>
                                <input type="text" name="broker_name" class="form-control" placeholder="जस्तै: IC Markets, FTMO, Binomo">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="account_number" class="form-control" placeholder="यदि लागू हुन्छ भने मात्र">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Initial Balance</label>
                                <input type="number" step="0.01" name="initial_balance" class="form-control" placeholder="जस्तै: 1000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Currency</label>
                                <input type="text" name="currency" class="form-control" value="USD">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Leverage</label>
                                <input type="text" name="leverage" class="form-control" placeholder="जस्तै: 1:100">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" rows="3" class="form-control" placeholder="यस खातासम्बन्धी महत्वपूर्ण जानकारी (rules, goals, etc.)"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <input type="hidden" name="save_account" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MT5 Connect Section -->
    <div class="container mb-5">
        <div class="card card-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-1">
                            <i class="fas fa-plug text-primary me-2"></i>MT5 Auto Sync (Investor Password)
                        </h4>
                        <p class="mb-0" style="color: var(--text-secondary) !important;">
                            यहाँबाट तपाईँले आफ्नो MetaTrader 5 account लाई investor (read‑only) password प्रयोग गरेर connect गर्न सक्नुहुन्छ।
<<<<<<< HEAD
                            <strong>Cloud Sync:</strong> MT5 terminal install नगरीकनै server बाट सीधै sync गर्न सकिन्छ (MT Connect API वा broker REST API)।
                            <strong>Local Sync:</strong> तपाईंको laptop मा MT5 terminal चाहिन्छ।
=======
                            Login, Server र Investor Password मात्र हालेर connect गर्न मिल्छ।
>>>>>>> d01e1cd (update)
                        </p>
                    </div>
                </div>

                <?php if ($mt5_error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mt5_error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($mt5_success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mt5_success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="post" class="row g-3">
<<<<<<< HEAD
                    <div class="col-12">
                        <label class="form-label">Sync Method *</label>
                        <select name="mt5_sync_method" id="mt5_sync_method" class="form-select" required onchange="toggleCloudFields()">
                            <option value="cloud">Cloud Sync (No MT5 Terminal Required) - Recommended</option>
                            <option value="local">Local Sync (Requires MT5 Terminal on Server)</option>
                        </select>
                        <small class="text-muted" style="color: var(--text-secondary) !important;">
                            Cloud Sync: MT Connect API वा broker REST API use गर्छ, terminal install नगर्नुपर्छ।
                        </small>
                    </div>
                    
=======
>>>>>>> d01e1cd (update)
                    <div class="col-md-4">
                        <label class="form-label">MT5 Account ID *</label>
                        <input type="text" name="mt5_account_number" class="form-control" required placeholder="जस्तै: 12345678">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Broker Server *</label>
                        <input
                            type="text"
                            name="mt5_broker_server"
                            class="form-control"
                            required
                            placeholder="MT5 मा देखिने server नाम जस्तै: Exness-MT5Real, ICMarketsSC-Real"
                        >
                        <small class="text-muted" style="color: var(--text-secondary) !important;">
                            * MetaTrader 5 को login window मा देखिने ठ्याक्कै server name यहाँ copy–paste गर्नुहोस्।
                        </small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Investor Password (Read‑Only) *</label>
                        <input type="password" name="mt5_investor_password" class="form-control" required>
                        <small class="text-muted" style="color: var(--text-secondary) !important;">
                            * यो password backend मा मात्र encrypt भएर रहन्छ, वेबसाइटले कहिल्यै plain text मा देखाउँदैन।
                        </small>
                    </div>
<<<<<<< HEAD
                    
                    <!-- Cloud API fields (shown only when cloud sync selected) -->
                    <div class="col-md-6" id="cloud_api_key_field" style="display: none;">
                        <label class="form-label">MT Connect API Key (Optional)</label>
                        <input type="text" name="mt5_cloud_api_key" class="form-control" placeholder="Get from https://mtconnectapi.com">
                        <small class="text-muted" style="color: var(--text-secondary) !important;">
                            यदि MT Connect API use गर्न चाहनुहुन्छ भने API key राख्नुहोस्। अन्यथा broker को REST API use हुन्छ।
                        </small>
                    </div>
                    <div class="col-md-6" id="cloud_api_url_field" style="display: none;">
                        <label class="form-label">Custom Broker API URL (Optional)</label>
                        <input type="text" name="mt5_cloud_api_url" class="form-control" placeholder="https://your-broker-api.com/v1">
                        <small class="text-muted" style="color: var(--text-secondary) !important;">
                            यदि तपाईंको broker को custom REST API छ भने यहाँ URL राख्नुहोस्।
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Link to Trading Account *</label>
                        <?php if (!empty($accounts)): ?>
                            <select name="mt5_linked_account_id" class="form-select" required>
                                <option value="">छान्नुहोस्...</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?php echo (int)$acc['id']; ?>">
                                        <?php echo htmlspecialchars($acc['account_name'] . ' (' . $acc['account_type'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" style="color: var(--text-secondary) !important;">
                                * यस MT5 account को history कुन Trading Account मा link गर्ने हो, त्यो छान्नुहोस्।
                            </small>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                पहिले माथि "Add Trading Account" बाट कम्तिमा एउटा account create गर्नुहोस्, त्यसपछि यहाँ link गर्न सक्नुहुन्छ।
                            </div>
                        <?php endif; ?>
                    </div>
=======
>>>>>>> d01e1cd (update)
                    <div class="col-12 mt-2">
                        <input type="hidden" name="mt5_connect" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-link me-1"></i>Connect MT5 Account
                        </button>
                        <small class="ms-2 text-muted" style="color: var(--text-secondary) !important;">
                            Connect भएको account को trade history Python MT5 backend बाट fetch हुन्छ।
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Connected MT5 Accounts (TradeFXBook style) -->
    <?php if (!empty($mt5_connected)): ?>
    <div class="container mb-5">
        <div class="card card-dark">
            <div class="card-body">
                <h4 class="mb-3">
                    <i class="fas fa-link text-primary me-2"></i>Connected MT5 Accounts
                </h4>
                <p class="text-muted mb-3">Auto-sync runs every 5 minutes. Click "Sync Now" to manually trigger sync.</p>

                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Trading Account</th>
                                <th>MT5 Account</th>
                                <th>Broker Server</th>
                                <th>Last Sync</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mt5_connected as $mt5): ?>
                                <tr id="mt5-row-<?php echo (int)$mt5['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($mt5['account_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($mt5['account_type']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($mt5['mt5_account_number']); ?></td>
                                    <td><small><?php echo htmlspecialchars($mt5['mt5_broker_server']); ?></small></td>
                                    <td>
                                        <?php if ($mt5['last_sync_at']): ?>
                                            <small><?php echo date('Y-m-d H:i', strtotime($mt5['last_sync_at'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($mt5['sync_error']): ?>
                                            <span class="badge bg-danger" title="<?php echo htmlspecialchars($mt5['sync_error']); ?>">Error</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button 
<<<<<<< HEAD
                                            class="btn btn-sm btn-primary sync-btn" 
                                            data-mt5-id="<?php echo (int)$mt5['id']; ?>"
                                            onclick="syncMT5Account(<?php echo (int)$mt5['id']; ?>)"
=======
                                            type="button"
                                            class="btn btn-sm btn-primary sync-btn" 
                                            data-mt5-id="<?php echo (int)$mt5['id']; ?>"
                                            onclick="syncMT5Account(event, <?php echo (int)$mt5['id']; ?>)"
>>>>>>> d01e1cd (update)
                                        >
                                            <i class="fas fa-sync-alt"></i> Sync Now
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
<<<<<<< HEAD
        function toggleCloudFields() {
            const method = document.getElementById('mt5_sync_method').value;
            const apiKeyField = document.getElementById('cloud_api_key_field');
            const apiUrlField = document.getElementById('cloud_api_url_field');
            
            if (method === 'cloud') {
                apiKeyField.style.display = 'block';
                apiUrlField.style.display = 'block';
            } else {
                apiKeyField.style.display = 'none';
                apiUrlField.style.display = 'none';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleCloudFields();
        });
        
        function syncMT5Account(mt5Id) {
=======
        function syncMT5Account(event, mt5Id) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
>>>>>>> d01e1cd (update)
            const btn = document.querySelector(`button[data-mt5-id="${mt5Id}"]`);
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

            fetch('<?php echo $MT5_API_BASE; ?>/accounts/' + mt5Id + '/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    alert('Sync successful! ' + (data.inserted_trades || 0) + ' new trades synced.');
                    location.reload(); // Refresh to show updated last_sync_at
                } else {
                    alert('Sync failed: ' + (data.detail || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            })
            .catch(error => {
                alert('Sync error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        }
    </script>
</body>
</html>

