-- ============================================
-- Add Profile Image Column to Users Table
-- ============================================
-- Run this SQL in phpMyAdmin to add profile_image column

USE `trading_db`;

-- Add profile_image column if it doesn't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `profile_image` VARCHAR(255) DEFAULT NULL AFTER `balance`;

