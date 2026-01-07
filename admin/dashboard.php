<?php
require_once __DIR__.'/auth.php';

// Get statistics
try {
    // Total users
    $users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $total_users = $users_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total admins
    $admins_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
    $total_admins = $admins_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total courses
    $courses_stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
    $total_courses = $courses_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total blogs
    $blogs_stmt = $pdo->query("SELECT COUNT(*) as total FROM blogs");
    $total_blogs = $blogs_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total payments
    $payments_stmt = $pdo->query("SELECT COUNT(*) as total, SUM(amount) as total_amount FROM payments WHERE status = 'completed'");
    $payments_data = $payments_stmt->fetch(PDO::FETCH_ASSOC);
    $total_payments = $payments_data['total'] ?? 0;
    $total_revenue = $payments_data['total_amount'] ?? 0;
    
    // Total trades
    $trades_stmt = $pdo->query("SELECT COUNT(*) as total FROM trading_journal");
    $total_trades = $trades_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending contacts/feedback
    $contacts_stmt = $pdo->query("SELECT COUNT(*) as total FROM contacts WHERE status = 'pending'");
    $pending_contacts = $contacts_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent users (last 7 days)
    $recent_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recent_users = $recent_users_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent payments (last 30 days)
    $recent_payments_stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $recent_revenue = $recent_payments_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get recent activities
    $recent_users_list = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recent_payments_list = $pdo->query("SELECT p.*, u.username, c.title as course_title FROM payments p LEFT JOIN users u ON p.user_id = u.id LEFT JOIN courses c ON p.course_id = c.id ORDER BY p.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recent_contacts = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NpLTrader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__.'/styles.php'; ?>
</head>
<body>
    <?php include __DIR__.'/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1>Admin Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($admin_user['username']); ?>!</p>
            </div>
            <div>
                <a href="../dashboard/dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-home me-2"></i>View Site
                </a>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Total Users</div>
                    <small class="text-muted">+<?php echo $recent_users; ?> this week</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_courses); ?></div>
                    <div class="stat-label">Total Courses</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-value">रु <?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <small class="text-muted">रु <?php echo number_format($recent_revenue, 2); ?> this month</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_trades); ?></div>
                    <div class="stat-label">Total Trades</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-blog"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_blogs); ?></div>
                    <div class="stat-label">Total Blogs</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_payments); ?></div>
                    <div class="stat-label">Completed Payments</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($pending_contacts); ?></div>
                    <div class="stat-label">Pending Contacts</div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users me-2"></i>Recent Users</h5>
                        <a href="users.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_users_list)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No users yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_users_list as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-credit-card me-2"></i>Recent Payments</h5>
                        <a href="payments.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Course</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_payments_list)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No payments yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_payments_list as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['username'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($payment['course_title'] ?? 'N/A'); ?></td>
                                            <td>रु <?php echo number_format($payment['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
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
        
        <!-- Recent Contacts -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-envelope me-2"></i>Recent Contacts & Feedback</h5>
                        <a href="contacts.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_contacts)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">No contacts yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_contacts as $contact): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['subject'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst($contact['type']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $contact['status'] === 'pending' ? 'warning' : ($contact['status'] === 'replied' ? 'success' : 'secondary'); ?>">
                                                    <?php echo ucfirst($contact['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($contact['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

