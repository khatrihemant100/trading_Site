-- ============================================
-- Admin Panel Setup SQL
-- ============================================
-- यो SQL file लाई phpMyAdmin मा import गर्नुहोस्
-- Database: trading_db
-- ============================================

USE `trading_db`;

-- ============================================
-- 1. CONTACTS TABLE (if not exists)
-- ============================================
CREATE TABLE IF NOT EXISTS `contacts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('contact', 'feedback') DEFAULT 'contact',
    `status` ENUM('pending', 'read', 'replied') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. TRADING_ACCOUNTS TABLE (if not exists)
-- ============================================
CREATE TABLE IF NOT EXISTS `trading_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `account_name` VARCHAR(255) NOT NULL,
    `account_type` ENUM('forex', 'propfirm', 'nepse', 'crypto', 'other') DEFAULT 'forex',
    `broker_name` VARCHAR(255) DEFAULT NULL,
    `account_number` VARCHAR(100) DEFAULT NULL,
    `initial_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `current_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `target_amount` DECIMAL(10,2) DEFAULT NULL,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `leverage` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'closed') DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. Ensure users table has role column
-- ============================================
-- Check if role column exists, if not add it
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'role';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' ENUM(\'user\',\'admin\') DEFAULT \'user\' AFTER password')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- 4. Ensure users table has profile_image column
-- ============================================
SET @columnname = 'profile_image';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(255) DEFAULT NULL AFTER balance')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- 5. Create Admin User (if not exists)
-- ============================================
-- Default password: admin123
-- Password hash for 'admin123'
INSERT INTO `users` (`username`, `email`, `password`, `role`, `balance`) 
VALUES ('admin', 'admin@npltrader.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0.00)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- ============================================
-- 6. Update existing user to admin (Optional)
-- ============================================
-- यदि तपाईंको existing user लाई admin बनाउन चाहनुहुन्छ भने:
-- UPDATE `users` SET `role` = 'admin' WHERE `email` = 'your-email@example.com';

-- ============================================
-- 7. Verify Admin User
-- ============================================
-- Check if admin user exists:
-- SELECT id, username, email, role FROM users WHERE role = 'admin';

