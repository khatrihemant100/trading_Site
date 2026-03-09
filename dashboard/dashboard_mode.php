<?php
/**
 * Dashboard Mode Utility
 * Manages Real/Demo dashboard mode switching via session
 */

/**
 * Get current dashboard mode (real or demo)
 * @return int 0 for Real, 1 for Demo
 */
function get_dashboard_mode(): int {
    if (!isset($_SESSION['dashboard_mode'])) {
        // Default to Real (0)
        $_SESSION['dashboard_mode'] = 0;
    }
    return (int)$_SESSION['dashboard_mode'];
}

/**
 * Set dashboard mode
 * @param int $mode 0 for Real, 1 for Demo
 */
function set_dashboard_mode(int $mode): void {
    $_SESSION['dashboard_mode'] = $mode === 1 ? 1 : 0;
}

/**
 * Check if current mode is Demo
 * @return bool
 */
function is_demo_mode(): bool {
    return get_dashboard_mode() === 1;
}

/**
 * Check if current mode is Real
 * @return bool
 */
function is_real_mode(): bool {
    return get_dashboard_mode() === 0;
}

/**
 * Get mode name for display
 * @return string "Real" or "Demo"
 */
function get_mode_name(): string {
    return is_demo_mode() ? "Demo" : "Real";
}

/**
 * Get mode badge HTML
 * @return string HTML badge
 */
function get_mode_badge(): string {
    if (is_demo_mode()) {
        return '<span class="badge bg-warning text-dark"><i class="fas fa-flask me-1"></i>Demo</span>';
    }
    return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Real</span>';
}
