-- ============================================
-- NpLTrader - Complete Database Setup
-- ============================================
-- यो SQL file लाई phpMyAdmin मा import गर्नुहोस् वा SQL tab मा run गर्नुहोस्
-- Database: trading_db
-- ============================================

-- Database बनाउने (यदि पहिले नै बनेको छैन भने)
CREATE DATABASE IF NOT EXISTS `trading_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `trading_db`;

-- ============================================
-- 1. USERS TABLE (प्रयोगकर्ता तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `balance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. TRADING_JOURNAL TABLE (ट्रेडिङ जर्नल तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `trading_journal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `symbol` varchar(20) NOT NULL,
  `trade_type` enum('buy','sell') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `entry_price` decimal(10,2) NOT NULL,
  `exit_price` decimal(10,2) DEFAULT NULL,
  `trade_date` date NOT NULL,
  `profit_loss` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `trade_date` (`trade_date`),
  KEY `symbol` (`symbol`),
  CONSTRAINT `trading_journal_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. COURSES TABLE (कोर्स तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_free` tinyint(1) DEFAULT 0,
  `duration_weeks` int(11) DEFAULT NULL,
  `level` enum('basic','intermediate','advanced') DEFAULT 'basic',
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_free` (`is_free`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. PAYMENTS TABLE (भुक्तानी तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('khalti','esewa','bank') DEFAULT 'khalti',
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `course_id` (`course_id`),
  KEY `status` (`status`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. PASSWORD_RESETS TABLE (पासवर्ड रिसेट तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. BLOGS TABLE (ब्लग तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `blogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `excerpt` text DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `author_id` (`author_id`),
  KEY `status` (`status`),
  CONSTRAINT `blogs_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. ENROLLMENTS TABLE (कोर्स एन्रोलमेन्ट तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `progress` int(11) DEFAULT 0,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_course` (`user_id`,`course_id`),
  KEY `course_id` (`course_id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. MEMBERSHIP_PLANS TABLE (सदस्यता योजना तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `membership_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `features` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. SUBSCRIPTIONS TABLE (सदस्यता तालिका)
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  KEY `status` (`status`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_ibfk_3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE DATA INSERT (वैकल्पिक - टेस्टिङको लागि)
-- ============================================

-- Admin user थप्ने (पासवर्ड: admin123)
-- नोट: Production मा यो user लाई मेटाउनुहोस् वा पासवर्ड बदल्नुहोस्
INSERT INTO `users` (`username`, `email`, `password`, `role`, `balance`) VALUES
('admin', 'admin@npltrader.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0.00)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Sample Courses थप्ने
INSERT INTO `courses` (`title`, `description`, `price`, `is_free`, `duration_weeks`, `level`, `status`) VALUES
('शेयर बजारको बेसिक ज्ञान', 'शुरुआतीहरूका लागि शेयर बजारको मूलभूत ज्ञान, कसरी सुरु गर्ने, र बजार विश्लेषणको आधारभूत तरिकाहरू।', 2500.00, 0, 4, 'basic', 'active'),
('टेक्निकल विश्लेषण', 'चार्ट, प्याटर्न, र टेक्निकल इन्डिकेटरहरूको प्रयोग गरेर बजारको प्रवृत्ति विश्लेषण गर्ने तरिका सिक्नुहोस्।', 4500.00, 0, 6, 'intermediate', 'active'),
('फन्डामेन्टल विश्लेषण', 'कम्पनीहरूको वित्तीय विवरण, उद्योग विश्लेषण, र मूल्यांकन तरिकाहरूबारे गहन अध्ययन।', 6500.00, 0, 8, 'advanced', 'active'),
('निःशुल्क परिचयात्मक कोर्स', 'शेयर बजारको परिचय र मूलभूत अवधारणाहरू', 0.00, 1, 1, 'basic', 'active')
ON DUPLICATE KEY UPDATE `title`=`title`;

-- Sample Membership Plans थप्ने
INSERT INTO `membership_plans` (`name`, `description`, `price`, `duration_days`, `features`, `status`) VALUES
('Basic Plan', 'मूलभूत सुविधाहरू', 500.00, 30, 'Basic course access, Community support', 'active'),
('Premium Plan', 'प्रिमियम सुविधाहरू', 1500.00, 90, 'All courses, Priority support, Trading signals', 'active'),
('Pro Plan', 'व्यावसायिक सुविधाहरू', 3000.00, 180, 'All features, 1-on-1 mentorship, Advanced tools', 'active')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- ============================================
-- END OF DATABASE SETUP
-- ============================================
-- सबै tables सफलतापूर्वक बनेको छ!
-- अब तपाईं आफ्नो website प्रयोग गर्न सक्नुहुन्छ।
-- ============================================

