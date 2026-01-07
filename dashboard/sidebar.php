<!-- Sidebar Toggle Button (shown when sidebar is closed) -->
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Open Sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <span>NpLTrader</span>
        </div>
        <button class="sidebar-close" onclick="toggleSidebar()" title="Close Sidebar" aria-label="Close Sidebar">
            <i class="fas fa-angle-left"></i>
            <span class="close-arrow">←</span>
        </button>
    </div>
    
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link dashboard-link">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="journal.php" class="nav-link">
                <i class="fas fa-book"></i>
                <span>Journal</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="portfolio.php" class="nav-link">
                <i class="fas fa-chart-line"></i>
                <span>Portfolio</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="Community.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Community</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="Mindset.php" class="nav-link">
                <i class="fas fa-heart"></i>
                <span>Mindset</span>
            </a>
        </li>
        <li class="nav-item calculator-dropdown">
            <?php 
            $current_page = basename($_SERVER['PHP_SELF']);
            $is_calculator = ($current_page === 'calculator.php');
            $calc_type = isset($_GET['type']) ? $_GET['type'] : 'position';
            ?>
            <button class="nav-link calculator-dropdown-btn" onclick="toggleCalculatorDropdown(event)" style="width: 100%; text-align: left; background: none; border: none; <?php echo $is_calculator ? 'color: #ffffff !important; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;' : ''; ?>">
                <i class="fas fa-calculator"></i>
                <span>Calculators</span>
                <i class="fas fa-chevron-down ms-auto" style="margin-left: auto;"></i>
            </button>
            <div class="calculator-dropdown-menu" id="calculatorDropdown" style="<?php echo $is_calculator ? 'display: block;' : 'display: none;'; ?>">
                <a href="calculator.php?type=position" class="calculator-dropdown-item <?php echo ($is_calculator && $calc_type === 'position') ? 'active' : ''; ?>">
                    Position Sizing Calculator
                </a>
                <a href="calculator.php?type=compound" class="calculator-dropdown-item <?php echo ($is_calculator && $calc_type === 'compound') ? 'active' : ''; ?>">
                    Compound Interest Calculator
                </a>
                <a href="calculator.php?type=emi" class="calculator-dropdown-item <?php echo ($is_calculator && $calc_type === 'emi') ? 'active' : ''; ?>">
                    EMI calculator
                </a>
                <a href="calculator.php?type=sip" class="calculator-dropdown-item <?php echo ($is_calculator && $calc_type === 'sip') ? 'active' : ''; ?>">
                    SIP Calculator
                </a>
            </div>
        </li>
    </ul>
    
    <div style="position: absolute; bottom: 20px; left: 20px; right: 20px;">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                $profile_image = $user['profile_image'] ?? null;
                if (!empty($profile_image) && file_exists($profile_image)): 
                ?>
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="user-id">ID: <?php echo htmlspecialchars($user['id']); ?></div>
            </div>
        </div>
        <a href="../logout.php" class="nav-link mt-3" style="justify-content: center; color: var(--text-primary);">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

