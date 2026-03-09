-- ============================================
-- Add 'loss' status to trading_accounts ENUM
-- ============================================
-- Run this SQL to add 'loss' as a valid status option
-- ============================================

USE `trading_db`;

-- Update account status enum to include 'loss'
ALTER TABLE `trading_accounts` 
MODIFY COLUMN `status` ENUM('active', 'inactive', 'closed', 'ongoing', 'breach', 'loss') DEFAULT 'active';

-- Verify the change
SELECT COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'trading_accounts' 
  AND COLUMN_NAME = 'status';
