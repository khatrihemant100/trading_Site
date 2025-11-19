<?php
require_once __DIR__.'/auth.php';

$message = '';
$message_type = '';

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_course') {
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $content = trim($_POST['content']);
                $price = floatval($_POST['price']);
                $is_free = isset($_POST['is_free']) ? 1 : 0;
                $duration_weeks = !empty($_POST['duration_weeks']) ? intval($_POST['duration_weeks']) : null;
                $level = $_POST['level'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("INSERT INTO courses (title, description, content, price, is_free, duration_weeks, level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $content, $price, $is_free, $duration_weeks, $level, $status]);
                $message = "Course added successfully!";
                $message_type = 'success';
            } elseif ($_POST['action'] === 'update_course') {
                $course_id = intval($_POST['course_id']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $content = trim($_POST['content']);
                $price = floatval($_POST['price']);
                $is_free = isset($_POST['is_free']) ? 1 : 0;
                $duration_weeks = !empty($_POST['duration_weeks']) ? intval($_POST['duration_weeks']) : null;
                $level = $_POST['level'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, content = ?, price = ?, is_free = ?, duration_weeks = ?, level = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $description, $content, $price, $is_free, $duration_weeks, $level, $status, $course_id]);
                $message = "Course updated successfully!";
                $message_type = 'success';
            } elseif ($_POST['action'] === 'delete_course') {
                $course_id = intval($_POST['course_id']);
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$course_id]);
                $message = "Course deleted successfully!";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get courses
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "title LIKE ?";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$courses_stmt = $pdo->prepare("SELECT * FROM courses $where_clause ORDER BY created_at DESC");
$courses_stmt->execute($params);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course for editing
$edit_course = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_course = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__.'/styles.php'; ?>
</head>
<body>
    <?php include __DIR__.'/sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-book me-2"></i>Course Management</h1>
                <p class="text-muted mb-0">Manage all courses</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal">
                    <i class="fas fa-plus me-2"></i>Add Course
                </button>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="search" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                    <a href="courses.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Courses Table -->
        <div class="card">
            <div class="card-header">
                <h5>All Courses (<?php echo count($courses); ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-dark">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Price</th>
                            <th>Level</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($courses)): ?>
                            <tr><td colspan="8" class="text-center text-muted">No courses found</td></tr>
                        <?php else: ?>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo $course['id']; ?></td>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td>
                                        <?php if ($course['is_free']): ?>
                                            <span class="badge bg-success">Free</span>
                                        <?php else: ?>
                                            रु <?php echo number_format($course['price'], 2); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($course['level']); ?></span></td>
                                    <td><?php echo $course['duration_weeks'] ? $course['duration_weeks'] . ' weeks' : 'N/A'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $course['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($course['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                    <td>
                                        <a href="?edit=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                            <input type="hidden" name="action" value="delete_course">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Course Modal -->
    <div class="modal fade" id="courseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $edit_course ? 'Edit' : 'Add'; ?> Course</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $edit_course ? 'update_course' : 'add_course'; ?>">
                        <?php if ($edit_course): ?>
                            <input type="hidden" name="course_id" value="<?php echo $edit_course['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($edit_course['title'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($edit_course['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea class="form-control" name="content" rows="10"><?php echo htmlspecialchars($edit_course['content'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Price</label>
                                <input type="number" class="form-control" name="price" step="0.01" value="<?php echo $edit_course['price'] ?? 0; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Level</label>
                                <select class="form-select" name="level" required>
                                    <option value="basic" <?php echo ($edit_course['level'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                    <option value="intermediate" <?php echo ($edit_course['level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="advanced" <?php echo ($edit_course['level'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Duration (weeks)</label>
                                <input type="number" class="form-control" name="duration_weeks" value="<?php echo $edit_course['duration_weeks'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_free" id="is_free" <?php echo ($edit_course['is_free'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_free">Free Course</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo ($edit_course['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($edit_course['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_course ? 'Update' : 'Add'; ?> Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($edit_course): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('courseModal'));
            modal.show();
        });
    </script>
    <?php endif; ?>
</body>
</html>

