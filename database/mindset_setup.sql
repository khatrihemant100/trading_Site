-- Mindset/Psychology System Setup
-- This creates tables for psychology logs, daily routines, and progress tracking

-- Psychology logs table
CREATE TABLE IF NOT EXISTS `psychology_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `emotion_before` VARCHAR(100) DEFAULT NULL,
  `emotion_during` VARCHAR(100) DEFAULT NULL,
  `emotion_after` VARCHAR(100) DEFAULT NULL,
  `confidence_level` INT DEFAULT 0 COMMENT '1-10 scale',
  `stress_level` INT DEFAULT 0 COMMENT '1-10 scale',
  `notes` TEXT DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `log_date` (`log_date`),
  CONSTRAINT `psychology_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily routine tracker
CREATE TABLE IF NOT EXISTS `daily_routines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `routine_date` date NOT NULL,
  `pre_market` TINYINT(1) DEFAULT 0,
  `trading_session` TINYINT(1) DEFAULT 0,
  `post_market` TINYINT(1) DEFAULT 0,
  `evening` TINYINT(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_date` (`user_id`, `routine_date`),
  KEY `routine_date` (`routine_date`),
  CONSTRAINT `daily_routines_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Psychology progress tracking
CREATE TABLE IF NOT EXISTS `psychology_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `metric_type` ENUM('emotional_control', 'discipline', 'risk_management') NOT NULL,
  `score` INT DEFAULT 0 COMMENT '0-100',
  `assessment_date` date NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `metric_type` (`metric_type`),
  KEY `assessment_date` (`assessment_date`),
  CONSTRAINT `psychology_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exercise completion tracking
CREATE TABLE IF NOT EXISTS `exercise_completions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exercise_name` VARCHAR(255) NOT NULL,
  `module_name` VARCHAR(255) NOT NULL,
  `completion_date` date NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `completion_date` (`completion_date`),
  CONSTRAINT `exercise_completions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
