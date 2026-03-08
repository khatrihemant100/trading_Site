-- ============================================
-- MT5 Accounts Connection Setup
-- ============================================
-- यो SQL file लाई phpMyAdmin मा import गर्नुहोस्
-- ============================================

USE `trading_db`;

-- ============================================
-- 1. MT5_ACCOUNTS TABLE (MT5 connection info store गर्ने)
-- ============================================
CREATE TABLE IF NOT EXISTS `mt5_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL COMMENT 'Links to trading_accounts.id',
  `mt5_account_number` varchar(64) NOT NULL,
  `mt5_broker_server` varchar(255) NOT NULL,
  `mt5_password_encrypted` text NOT NULL COMMENT 'Fernet encrypted investor password',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sync_at` datetime DEFAULT NULL,
  `last_sync_ticket` bigint(20) DEFAULT NULL COMMENT 'Last synced ticket number',
  `sync_error` text DEFAULT NULL COMMENT 'Last sync error if any',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_account_mt5` (`user_id`, `account_id`, `mt5_account_number`, `mt5_broker_server`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `mt5_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mt5_accounts_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `trading_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- END OF MT5 ACCOUNTS SETUP
-- ============================================
