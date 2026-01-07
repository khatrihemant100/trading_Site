<?php
require_once __DIR__.'/auth.php';

$message = '';
$message_type = '';

// Handle contact actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'update_status') {
                $contact_id = intval($_POST['contact_id']);
                $new_status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE contacts SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $contact_id]);
                $message = "Contact status updated successfully!";
                $message_type = 'success';
            } elseif ($_POST['action'] === 'delete_contact') {
                $contact_id = intval($_POST['contact_id']);
                $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
                $stmt->execute([$contact_id]);
                $message = "Contact deleted successfully!";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get contacts
$contacts_stmt = $pdo->prepare("SELECT * FROM contacts $where_clause ORDER BY created_at DESC");
$contacts_stmt->execute($params);
$contacts = $contacts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$pending_contacts = $pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'pending'")->fetchColumn();
$total_contacts = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__.'/styles.php'; ?>
</head>
<body>
    <?php include __DIR__.'/sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-envelope me-2"></i>Contact & Feedback Management</h1>
                <p class="text-muted mb-0">Manage all contacts and feedback</p>
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
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($pending_contacts); ?></div>
                    <div class="stat-label">Pending Contacts</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_contacts); ?></div>
                    <div class="stat-label">Total Contacts</div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search by name, email, or subject..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="type">
                        <option value="">All Types</option>
                        <option value="contact" <?php echo $type_filter === 'contact' ? 'selected' : ''; ?>>Contact</option>
                        <option value="feedback" <?php echo $type_filter === 'feedback' ? 'selected' : ''; ?>>Feedback</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Contacts Table -->
        <div class="card">
            <div class="card-header">
                <h5>All Contacts (<?php echo count($contacts); ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-dark">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr><td colspan="8" class="text-center text-muted">No contacts found</td></tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?php echo $contact['id']; ?></td>
                                    <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['subject'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $contact['type'] === 'contact' ? 'info' : 'primary'; ?>">
                                            <?php echo ucfirst($contact['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $contact['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="read" <?php echo $contact['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                                <option value="replied" <?php echo $contact['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($contact['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $contact['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                            <input type="hidden" name="action" value="delete_contact">
                                            <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                
                                <!-- View Modal -->
                                <div class="modal fade" id="viewModal<?php echo $contact['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Contact Details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($contact['name']); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($contact['email']); ?></p>
                                                <p><strong>Subject:</strong> <?php echo htmlspecialchars($contact['subject'] ?? 'N/A'); ?></p>
                                                <p><strong>Type:</strong> <?php echo ucfirst($contact['type']); ?></p>
                                                <p><strong>Status:</strong> <?php echo ucfirst($contact['status']); ?></p>
                                                <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($contact['created_at'])); ?></p>
                                                <hr>
                                                <p><strong>Message:</strong></p>
                                                <p><?php echo nl2br(htmlspecialchars($contact['message'])); ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

