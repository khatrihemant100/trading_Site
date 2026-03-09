-- ============================================
-- Real/Demo Dashboard System Setup
-- ============================================
-- यो SQL file लाई phpMyAdmin मा import गर्नुहोस्
-- Real र Demo accounts/journals लाई separate गर्ने लागि
-- ============================================

USE `trading_db`;

-- ============================================
-- 1. ADD is_demo COLUMN TO trading_accounts
-- ============================================
SET @dbname = DATABASE();
SET @tablename = 'trading_accounts';
SET @columnname = 'is_demo';

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' tinyint(1) NOT NULL DEFAULT 0 COMMENT ''0=Real, 1=Demo'' AFTER user_id')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for faster filtering
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'idx_is_demo')
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX idx_is_demo (is_demo)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- 2. ADD is_demo COLUMN TO trading_journal
-- ============================================
SET @tablename = 'trading_journal';
SET @columnname = 'is_demo';

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' tinyint(1) NOT NULL DEFAULT 0 COMMENT ''0=Real, 1=Demo'' AFTER user_id')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for faster filtering
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'idx_is_demo')
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX idx_is_demo (is_demo)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- 3. UPDATE EXISTING DATA (Optional - set all existing as Real)
-- ============================================
-- Uncomment these if you want to mark existing data as Real (0)
-- UPDATE trading_accounts SET is_demo = 0 WHERE is_demo IS NULL;
-- UPDATE trading_journal SET is_demo = 0 WHERE is_demo IS NULL;

-- ============================================
-- END OF REAL/DEMO DASHBOARD SETUP
-- ============================================
