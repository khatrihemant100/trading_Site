-- ============================================
-- Fix Admin Password SQL
-- ============================================
-- Run this SQL to set admin password to: admin123
-- ============================================

USE `trading_db`;

-- Method 1: Update existing admin password
-- Generate hash using PHP: password_hash('admin123', PASSWORD_DEFAULT)
-- Or use the PHP script: admin/fix_password.php

-- Method 2: Direct SQL Update (if you have the correct hash)
-- Replace the hash below with a fresh one generated from PHP
UPDATE `users` 
SET `password` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE `username` = 'admin' OR `email` = 'admin@npltrader.com';

-- Verify the update
SELECT id, username, email, role FROM users WHERE username = 'admin' OR email = 'admin@npltrader.com';

-- ============================================
-- RECOMMENDED: Use PHP Script Instead
-- ============================================
-- Go to: http://localhost/Trading_Site/admin/fix_password.php
-- This will generate a fresh password hash and update it automatically

