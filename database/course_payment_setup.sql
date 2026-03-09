-- Course Payment System Setup
-- This file creates tables for course purchases with payment proof upload

-- Update payments table to support bank transfer and crypto
ALTER TABLE `payments` 
MODIFY COLUMN `payment_method` ENUM('khalti','esewa','bank_transfer','crypto') DEFAULT 'bank_transfer';

-- Add payment proof fields to payments table
ALTER TABLE `payments` 
ADD COLUMN IF NOT EXISTS `payment_proof` VARCHAR(500) DEFAULT NULL AFTER `transaction_id`,
ADD COLUMN IF NOT EXISTS `payment_details` TEXT DEFAULT NULL AFTER `payment_proof`,
ADD COLUMN IF NOT EXISTS `admin_notes` TEXT DEFAULT NULL AFTER `status`;

-- Create course_purchases table for tracking purchases
CREATE TABLE IF NOT EXISTS `course_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `payment_method` ENUM('bank_transfer','crypto') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_proof` VARCHAR(500) DEFAULT NULL,
  `bank_details` TEXT DEFAULT NULL COMMENT 'Bank account details or crypto wallet address used',
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` TEXT DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who approved',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `course_id` (`course_id`),
  KEY `payment_id` (`payment_id`),
  KEY `status` (`status`),
  CONSTRAINT `course_purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_purchases_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_purchases_ibfk_3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_purchases_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_settings table for storing bank/crypto details
CREATE TABLE IF NOT EXISTS `payment_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_type` ENUM('bank_transfer','crypto') NOT NULL,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_type_key` (`payment_type`,`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default payment settings (you can update these later)
INSERT INTO `payment_settings` (`payment_type`, `setting_key`, `setting_value`) VALUES
('bank_transfer', 'bank_name', 'Nepal Investment Bank'),
('bank_transfer', 'account_name', 'NPLTrader'),
('bank_transfer', 'account_number', '1234567890123'),
('bank_transfer', 'account_type', 'Current Account'),
('bank_transfer', 'qr_code', 'uploads/payment/bank_qr.png'),
('crypto', 'crypto_type', 'USDT'),
('crypto', 'network', 'TRC20'),
('crypto', 'wallet_address', 'TXYZ1234567890ABCDEF'),
('crypto', 'qr_code', 'uploads/payment/crypto_qr.png');
