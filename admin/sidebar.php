<!-- Admin Sidebar -->
<aside class="admin-sidebar">
    <div class="admin-sidebar-header">
        <i class="fas fa-shield-alt" style="color: var(--primary); font-size: 1.5rem;"></i>
        <h4>Admin Panel</h4>
    </div>
    
    <ul class="admin-nav">
        <li class="admin-nav-item">
            <a href="dashboard.php" class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="admin-nav-item">
            <a href="users.php" class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        <li class="admin-nav-item">
            <a href="courses.php" class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'courses.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
        </li>
        <li class="admin-nav-item">
            <a href="blogs.php" class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'blogs.php' ? 'active' : ''; ?>">
                <i class="fas fa-blog"></i>
                <span>Blogs</span>
            </a>
        </li>
        <li class="admin-nav-item">
            <a href="payments.php" class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
        </li>
        <li class="admin-nav-item">
            <a href="contacts.php" class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'contacts.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>Contacts</span>
                <?php
                try {
                    $pending_count = $pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'pending'")->fetchColumn();
                    if ($pending_count > 0):
                ?>
                    <span class="badge bg-danger ms-auto"><?php echo $pending_count; ?></span>
                <?php endif; } catch (Exception $e) {} ?>
            </a>
        </li>
        <li class="admin-nav-item">
            <a href="trades.php" class="admin-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'trades.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Trading Journal</span>
            </a>
        </li>
        <li class="admin-nav-item">
            <a href="../dashboard/dashboard.php" class="admin-nav-link">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Site</span>
            </a>
        </li>
        <li class="admin-nav-item">
            <a href="../logout.php" class="admin-nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

