-- ============================================
-- Trading Journal Enhancement
-- ============================================
-- यो SQL file लाई phpMyAdmin मा import गर्नुहोस्
-- ============================================

USE `trading_db`;

-- ============================================
-- 1. ENHANCE TRADING_JOURNAL TABLE
-- ============================================
-- Add new columns to trading_journal table
-- Note: MySQL doesn't support IF NOT EXISTS with ADD COLUMN, so we check first

SET @dbname = DATABASE();
SET @tablename = 'trading_journal';

-- Add lot column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'lot') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `lot` decimal(10,2) DEFAULT NULL AFTER `quantity`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add stop_loss column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'stop_loss') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `stop_loss` decimal(10,2) DEFAULT NULL AFTER `entry_price`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add take_profit column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'take_profit') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `take_profit` decimal(10,2) DEFAULT NULL AFTER `stop_loss`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add entry_time column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'entry_time') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `entry_time` time DEFAULT NULL AFTER `entry_price`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add exit_time column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'exit_time') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `exit_time` time DEFAULT NULL AFTER `exit_price`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add risk_percent column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'risk_percent') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `risk_percent` decimal(5,2) DEFAULT NULL COMMENT ''Risk percentage'' AFTER `take_profit`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add r_multiple column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'r_multiple') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `r_multiple` decimal(10,2) DEFAULT NULL COMMENT ''R multiple (risk/reward ratio)'' AFTER `risk_percent`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add strategy column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'strategy') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `strategy` varchar(255) DEFAULT NULL AFTER `r_multiple`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add setup_type column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'setup_type') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `setup_type` varchar(255) DEFAULT NULL AFTER `strategy`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add emotion_before column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'emotion_before') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `emotion_before` varchar(50) DEFAULT NULL COMMENT ''Emotion before trade'' AFTER `setup_type`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add emotion_during column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'emotion_during') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `emotion_during` varchar(50) DEFAULT NULL COMMENT ''Emotion during trade'' AFTER `emotion_before`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add emotion_after column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'emotion_after') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `emotion_after` varchar(50) DEFAULT NULL COMMENT ''Emotion after trade'' AFTER `emotion_during`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add mistake_tags column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'mistake_tags') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `mistake_tags` text DEFAULT NULL COMMENT ''JSON array of mistake tags'' AFTER `emotion_after`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add screenshot_path column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'screenshot_path') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `screenshot_path` varchar(500) DEFAULT NULL AFTER `notes`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add session_type column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'session_type') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `session_type` varchar(50) DEFAULT NULL COMMENT ''Trading session: Asian, London, New York, etc.'' AFTER `trade_date`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add trade_status column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'trade_status') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `trade_status` enum(''open'',''closed'',''breakeven'') DEFAULT ''closed'' AFTER `trade_date`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add indexes for better performance (check if they exist first)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_symbol') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX `idx_symbol` (`symbol`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_trade_date') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX `idx_trade_date` (`trade_date`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_profit_loss') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX `idx_profit_loss` (`profit_loss`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_session_type') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX `idx_session_type` (`session_type`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_trade_status') > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX `idx_trade_status` (`trade_status`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- 2. PSYCHOLOGY_LOG TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `psychology_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `log_date` date NOT NULL,
  `emotion_type` varchar(50) DEFAULT NULL COMMENT 'fear, greed, confidence, anxiety, etc.',
  `intensity` tinyint(1) DEFAULT 5 COMMENT '1-10 scale',
  `trigger` text DEFAULT NULL COMMENT 'What triggered this emotion',
  `notes` text DEFAULT NULL,
  `tags` text DEFAULT NULL COMMENT 'JSON array of tags',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `account_id` (`account_id`),
  KEY `log_date` (`log_date`),
  CONSTRAINT `psychology_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psychology_log_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `trading_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. MISTAKE_LOG TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `mistake_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `trade_id` int(11) DEFAULT NULL COMMENT 'Link to trading_journal if mistake is trade-specific',
  `mistake_date` date NOT NULL,
  `mistake_type` varchar(100) NOT NULL COMMENT 'overtrading, revenge trading, FOMO, etc.',
  `description` text NOT NULL,
  `impact` enum('low','medium','high','critical') DEFAULT 'medium',
  `tags` text DEFAULT NULL COMMENT 'JSON array of tags',
  `lesson_learned` text DEFAULT NULL,
  `action_plan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `account_id` (`account_id`),
  KEY `trade_id` (`trade_id`),
  KEY `mistake_date` (`mistake_date`),
  KEY `mistake_type` (`mistake_type`),
  CONSTRAINT `mistake_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mistake_log_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `trading_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mistake_log_ibfk_3` FOREIGN KEY (`trade_id`) REFERENCES `trading_journal` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. TRADING_REVIEWS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `trading_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `review_type` enum('weekly','monthly') NOT NULL,
  `review_period_start` date NOT NULL,
  `review_period_end` date NOT NULL,
  `total_trades` int(11) DEFAULT 0,
  `winning_trades` int(11) DEFAULT 0,
  `losing_trades` int(11) DEFAULT 0,
  `win_rate` decimal(5,2) DEFAULT 0.00,
  `total_profit_loss` decimal(15,2) DEFAULT 0.00,
  `best_trade` decimal(15,2) DEFAULT NULL,
  `worst_trade` decimal(15,2) DEFAULT NULL,
  `avg_win` decimal(15,2) DEFAULT NULL,
  `avg_loss` decimal(15,2) DEFAULT NULL,
  `what_went_well` text DEFAULT NULL,
  `what_went_wrong` text DEFAULT NULL,
  `lessons_learned` text DEFAULT NULL,
  `goals_for_next_period` text DEFAULT NULL,
  `action_items` text DEFAULT NULL COMMENT 'JSON array of action items',
  `self_rating` tinyint(1) DEFAULT NULL COMMENT '1-10 scale',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `account_id` (`account_id`),
  KEY `review_type` (`review_type`),
  KEY `review_period_start` (`review_period_start`),
  CONSTRAINT `trading_reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trading_reviews_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `trading_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- END OF TRADING JOURNAL ENHANCEMENT
-- ============================================

