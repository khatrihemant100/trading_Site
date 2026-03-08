<style>
/* Mode Switcher in Sidebar */
.sidebar-mode-switcher {
    margin-top: 12px;
    margin-bottom: 12px;
    padding: 12px;
    background: var(--dark-card);
    border: 2px solid var(--border-color);
    border-radius: 12px;
}

.sidebar-mode-switcher.demo-mode {
    border-color: #fbbf24;
    background: rgba(251, 191, 36, 0.05);
}

.sidebar-mode-switcher.real-mode {
    border-color: var(--primary);
    background: rgba(16, 185, 129, 0.05);
}

.mode-switcher-buttons {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
}

.mode-switch-btn {
    flex: 1;
    padding: 10px 12px;
    border: 2px solid;
    border-radius: 8px;
    background: transparent;
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
}

.mode-switch-btn.real-btn {
    border-color: var(--primary);
    color: var(--primary);
}

.mode-switch-btn.real-btn:hover,
.mode-switch-btn.real-btn.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.mode-switch-btn.demo-btn {
    border-color: #fbbf24;
    color: #fbbf24;
}

.mode-switch-btn.demo-btn:hover,
.mode-switch-btn.demo-btn.active {
    background: #fbbf24;
    color: #1e293b;
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
}

.mode-status-indicator {
    text-align: center;
    padding: 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
}

.mode-status-indicator.real {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
    border: 1px solid var(--primary);
}

.mode-status-indicator.demo {
    background: rgba(251, 191, 36, 0.15);
    color: #fde047;
    border: 1px solid #fbbf24;
}

/* Dashboard Link - Color based on mode */
.nav-link.dashboard-link.real-mode {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
}

.nav-link.dashboard-link.demo-mode {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%) !important;
}
</style>

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
    
    <?php
    // Include dashboard mode if not already included
    if (!function_exists('is_demo_mode')) {
        require_once __DIR__.'/dashboard_mode.php';
    }
    $is_demo = is_demo_mode();
    ?>
    
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link dashboard-link <?php echo $is_demo ? 'demo-mode' : 'real-mode'; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <div class="sidebar-mode-switcher <?php echo $is_demo ? 'demo-mode' : 'real-mode'; ?>">
                <div class="mode-switcher-buttons">
                    <a href="dashboard.php?mode=real" class="mode-switch-btn real-btn <?php echo !$is_demo ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Real</span>
                    </a>
                    <a href="dashboard.php?mode=demo" class="mode-switch-btn demo-btn <?php echo $is_demo ? 'active' : ''; ?>">
                        <i class="fas fa-flask"></i>
                        <span>Demo</span>
                    </a>
                </div>
                <div class="mode-status-indicator <?php echo $is_demo ? 'demo' : 'real'; ?>">
                    <i class="fas fa-<?php echo $is_demo ? 'flask' : 'check-circle'; ?> me-1"></i>
                    <?php echo $is_demo ? 'Demo Mode Active' : 'Real Mode Active'; ?>
                </div>
            </div>
        </li>
        <li class="nav-item">
            <a href="journal.php" class="nav-link">
                <i class="fas fa-book"></i>
                <span>Journal</span>
            </a>
        </li>
        <li class="nav-item">
            <?php 
            $current_page = basename($_SERVER['PHP_SELF']);
            $is_portfolio = ($current_page === 'portfolio.php');
            ?>
            <a href="portfolio.php" class="nav-link <?php echo $is_portfolio ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i>
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

