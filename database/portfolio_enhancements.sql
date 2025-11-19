-- ============================================
-- Portfolio Enhancements - Account Status & Withdrawals
-- ============================================
-- Run this SQL to add new columns and tables for enhanced portfolio tracking
-- ============================================

USE `trading_db`;

-- ============================================
-- 1. Add challenge_fee column to trading_accounts
-- ============================================
SET @dbname = DATABASE();
SET @tablename = 'trading_accounts';
SET @columnname = 'challenge_fee';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' decimal(15,2) DEFAULT 0.00 AFTER initial_balance')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- 2. Update account status enum to include ongoing and breach
-- ============================================
-- Note: MySQL doesn't support ALTER ENUM easily, so we'll use MODIFY
ALTER TABLE `trading_accounts` 
MODIFY COLUMN `status` ENUM('active', 'inactive', 'closed', 'ongoing', 'breach') DEFAULT 'active';

-- ============================================
-- 3. Create withdrawals table
-- ============================================
CREATE TABLE IF NOT EXISTS `account_withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `withdrawal_amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `platform` enum('rise','bank','crypto','other') NOT NULL,
  `platform_details` varchar(255) DEFAULT NULL,
  `withdrawal_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `account_id` (`account_id`),
  KEY `withdrawal_date` (`withdrawal_date`),
  CONSTRAINT `account_withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_withdrawals_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `trading_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- END OF PORTFOLIO ENHANCEMENTS
-- ============================================

