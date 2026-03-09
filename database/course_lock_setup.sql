-- Course Lock/Unlock System Setup
-- This allows admin to enable/disable course page

-- Create settings table if not exists (or add to existing settings)
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default course lock setting (0 = locked/disabled, 1 = unlocked/enabled)
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('courses_enabled', '0', 'Enable/Disable course page (0 = disabled/coming soon, 1 = enabled)')
ON DUPLICATE KEY UPDATE `setting_value` = '0';
