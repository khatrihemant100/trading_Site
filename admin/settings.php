<?php
require_once __DIR__.'/auth.php';

$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    try {
        $courses_enabled = isset($_POST['courses_enabled']) ? '1' : '0';
        
        // Update or insert setting
        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value, description) 
            VALUES ('courses_enabled', ?, 'Enable/Disable course page (0 = disabled/coming soon, 1 = enabled)')
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$courses_enabled, $courses_enabled]);
        
        $message = "Settings updated successfully!";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get current settings
try {
    // Check if table exists first
    $table_check = $pdo->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check->rowCount() == 0) {
        // Table doesn't exist, create it
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `site_settings` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `setting_key` VARCHAR(100) NOT NULL,
              `setting_value` TEXT DEFAULT NULL,
              `description` TEXT DEFAULT NULL,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert default setting
        $pdo->exec("
            INSERT INTO `site_settings` (`setting_key`, `setting_value`, `description`) 
            VALUES ('courses_enabled', '0', 'Enable/Disable course page (0 = disabled/coming soon, 1 = enabled)')
        ");
        $courses_enabled = '0';
    } else {
        // Table exists, get setting
        $courses_stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'courses_enabled'");
        $courses_stmt->execute();
        $courses_setting = $courses_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$courses_setting) {
            // Setting doesn't exist, insert it
            $pdo->exec("
                INSERT INTO `site_settings` (`setting_key`, `setting_value`, `description`) 
                VALUES ('courses_enabled', '0', 'Enable/Disable course page (0 = disabled/coming soon, 1 = enabled)')
            ");
            $courses_enabled = '0';
        } else {
            $courses_enabled = isset($courses_setting['setting_value']) ? $courses_setting['setting_value'] : '0';
        }
    }
} catch (PDOException $e) {
    $courses_enabled = '0';
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__.'/styles.php'; ?>
</head>
<body>
    <?php include __DIR__.'/sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-cog me-2"></i>Settings</h1>
                <p class="text-muted mb-0">Manage site settings</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-book me-2"></i>Course Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="courses_enabled" id="courses_enabled" 
                                   value="1" <?php echo $courses_enabled == '1' ? 'checked' : ''; ?> 
                                   style="width: 60px; height: 30px; cursor: pointer;">
                            <label class="form-check-label" for="courses_enabled" style="font-size: 1.1rem; font-weight: 600; margin-left: 15px; cursor: pointer;">
                                Enable Courses Page
                            </label>
                        </div>
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Current Status:</strong> 
                                <span id="statusText">
                                    <?php if ($courses_enabled == '1'): ?>
                                        <span class="text-success"><i class="fas fa-check-circle me-1"></i>Courses are <strong>ENABLED</strong> - Users can access and purchase courses</span>
                                    <?php else: ?>
                                        <span class="text-warning"><i class="fas fa-clock me-1"></i>Courses are <strong>DISABLED</strong> - Users will see "Coming Soon" message</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="text-muted">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>How it works:</strong><br>
                                • <strong>Enabled:</strong> Course page is fully accessible, users can view and purchase courses<br>
                                • <strong>Disabled:</strong> Course page shows "Coming Soon" message, navbar link shows notification
                            </p>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update status text when checkbox changes
        document.getElementById('courses_enabled').addEventListener('change', function() {
            const statusText = document.getElementById('statusText');
            if (this.checked) {
                statusText.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Courses will be <strong>ENABLED</strong> - Users can access and purchase courses</span>';
            } else {
                statusText.innerHTML = '<span class="text-warning"><i class="fas fa-clock me-1"></i>Courses will be <strong>DISABLED</strong> - Users will see "Coming Soon" message</span>';
            }
        });
    </script>
</body>
</html>
