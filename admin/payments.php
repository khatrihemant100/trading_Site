<?php
require_once __DIR__.'/auth.php';

$message = '';
$message_type = '';

// Handle payment status update and course purchase approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_status') {
            $payment_id = intval($_POST['payment_id']);
            $new_status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $payment_id]);
            $message = "Payment status updated successfully!";
            $message_type = 'success';
        } elseif ($_POST['action'] === 'approve_purchase') {
            $purchase_id = intval($_POST['purchase_id']);
            $admin_notes = $_POST['admin_notes'] ?? '';
            
            $pdo->beginTransaction();
            
            // Update course purchase status
            $purchase_stmt = $pdo->prepare("
                UPDATE course_purchases 
                SET status = 'approved', 
                    approved_by = ?, 
                    approved_at = NOW(),
                    admin_notes = ?
                WHERE id = ?
            ");
            $purchase_stmt->execute([$_SESSION['user_id'], $admin_notes, $purchase_id]);
            
            // Get purchase details
            $purchase_info = $pdo->prepare("SELECT payment_id, course_id, user_id FROM course_purchases WHERE id = ?");
            $purchase_info->execute([$purchase_id]);
            $purchase = $purchase_info->fetch(PDO::FETCH_ASSOC);
            
            if ($purchase) {
                // Update payment status
                $payment_stmt = $pdo->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE id = ?");
                $payment_stmt->execute([$purchase['payment_id']]);
                
                // Create enrollment if not exists
                $enrollment_check = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
                $enrollment_check->execute([$purchase['user_id'], $purchase['course_id']]);
                if (!$enrollment_check->fetch()) {
                    $enrollment_stmt = $pdo->prepare("
                        INSERT INTO enrollments (user_id, course_id, payment_id, status) 
                        VALUES (?, ?, ?, 'active')
                    ");
                    $enrollment_stmt->execute([$purchase['user_id'], $purchase['course_id'], $purchase['payment_id']]);
                }
            }
            
            $pdo->commit();
            $message = "Course purchase approved successfully! User has been enrolled in the course.";
            $message_type = 'success';
        } elseif ($_POST['action'] === 'reject_purchase') {
            $purchase_id = intval($_POST['purchase_id']);
            $admin_notes = $_POST['admin_notes'] ?? '';
            
            $purchase_stmt = $pdo->prepare("
                UPDATE course_purchases 
                SET status = 'rejected', 
                    admin_notes = ?
                WHERE id = ?
            ");
            $purchase_stmt->execute([$admin_notes, $purchase_id]);
            
            $message = "Course purchase rejected.";
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get payments with course purchases
$payments_stmt = $pdo->prepare("
    SELECT p.*, u.username, u.email, c.title as course_title,
           cp.id as purchase_id, cp.status as purchase_status, cp.payment_proof, cp.admin_notes as purchase_notes
    FROM payments p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN courses c ON p.course_id = c.id 
    LEFT JOIN course_purchases cp ON p.id = cp.payment_id
    $where_clause
    ORDER BY p.created_at DESC
");
$payments_stmt->execute($params);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course purchases for separate view
$purchases_stmt = $pdo->prepare("
    SELECT cp.*, u.username, u.email, c.title as course_title, c.price as course_price
    FROM course_purchases cp
    LEFT JOIN users u ON cp.user_id = u.id
    LEFT JOIN courses c ON cp.course_id = c.id
    WHERE cp.status = 'pending'
    ORDER BY cp.created_at DESC
");
$purchases_stmt->execute();
$pending_purchases = $purchases_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'")->fetchColumn() ?? 0;
$pending_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$completed_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__.'/styles.php'; ?>
</head>
<body>
    <?php include __DIR__.'/sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-credit-card me-2"></i>Payment Management</h1>
                <p class="text-muted mb-0">Manage all payments</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value">रु <?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($completed_payments); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($pending_payments); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="search" placeholder="Search by user, email, or transaction ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                    <a href="payments.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Pending Course Purchases (Payment Proofs) -->
        <?php if (!empty($pending_purchases)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Pending Course Purchases (<?php echo count($pending_purchases); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($pending_purchases as $purchase): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card border-warning">
                            <div class="card-header bg-dark">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($purchase['course_title']); ?></strong><br>
                                        <small class="text-muted">User: <?php echo htmlspecialchars($purchase['username']); ?></small>
                                    </div>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                </div>
                            </div>
                            <div class="card-body bg-dark">
                                <p class="mb-2"><strong>Amount:</strong> रु <?php echo number_format($purchase['amount'], 2); ?></p>
                                <p class="mb-2"><strong>Payment Method:</strong> 
                                    <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $purchase['payment_method'])); ?></span>
                                </p>
                                <p class="mb-2"><strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($purchase['created_at'])); ?></p>
                                
                                <?php if ($purchase['payment_proof']): ?>
                                <div class="mb-3">
                                    <strong>Payment Proof:</strong><br>
                                    <img src="../<?php echo htmlspecialchars($purchase['payment_proof']); ?>" 
                                         alt="Payment Proof" 
                                         class="img-thumbnail mt-2" 
                                         style="max-width: 100%; cursor: pointer;"
                                         onclick="window.open('../<?php echo htmlspecialchars($purchase['payment_proof']); ?>', '_blank')">
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="action" value="approve_purchase">
                                    <input type="hidden" name="purchase_id" value="<?php echo $purchase['id']; ?>">
                                    <div class="mb-2">
                                        <label class="form-label">Admin Notes (Optional):</label>
                                        <textarea name="admin_notes" class="form-control" rows="2" placeholder="Add notes about this approval..."><?php echo htmlspecialchars($purchase['purchase_notes'] ?? ''); ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check me-2"></i>Approve & Enroll
                                    </button>
                                </form>
                                
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="reject_purchase">
                                    <input type="hidden" name="purchase_id" value="<?php echo $purchase['id']; ?>">
                                    <div class="mb-2">
                                        <label class="form-label">Rejection Reason:</label>
                                        <textarea name="admin_notes" class="form-control" rows="2" placeholder="Reason for rejection..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject this purchase?')">
                                        <i class="fas fa-times me-2"></i>Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payments Table -->
        <div class="card">
            <div class="card-header">
                <h5>All Payments (<?php echo count($payments); ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-dark">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Course</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Transaction ID</th>
                            <th>Payment Proof</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="10" class="text-center text-muted">No payments found</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['username'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['course_title'] ?? 'N/A'); ?></td>
                                    <td>रु <?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($payment['payment_method']); ?></span></td>
                                    <td><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($payment['payment_proof'])): ?>
                                            <button type="button" class="btn btn-sm btn-info" onclick="viewProof('<?php echo htmlspecialchars($payment['payment_proof']); ?>')">
                                                <i class="fas fa-image me-1"></i>View Proof
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $payment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="completed" <?php echo $payment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="failed" <?php echo $payment['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                <option value="cancelled" <?php echo $payment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td><?php echo $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $payment['status'] === 'completed' ? 'success' : 
                                                ($payment['status'] === 'pending' ? 'warning' : 
                                                ($payment['status'] === 'failed' ? 'danger' : 'secondary')); 
                                        ?>">
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
    </main>
    
    <!-- Payment Proof Modal -->
    <div class="modal fade" id="proofModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Proof</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="proofImage" src="" alt="Payment Proof" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewProof(imagePath) {
            document.getElementById('proofImage').src = '../' + imagePath;
            const modal = new bootstrap.Modal(document.getElementById('proofModal'));
            modal.show();
        }
    </script>
</body>
</html>

