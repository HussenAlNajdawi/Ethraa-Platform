-- ========================================================
-- Platform: Ethraa - Service Exchange Platform
-- Database: ethraa_db
-- Exported for GitHub Production Setup
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for table `admin_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE `admin_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `admins`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT 'المدير العام',
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_version` int(11) DEFAULT 1,
  `failed_attempts` int(11) DEFAULT 0,
  `lockout_time` datetime DEFAULT NULL,
  `role` varchar(30) DEFAULT 'sub_admin',
  `permissions` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `appeals`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `appeals`;
CREATE TABLE `appeals` (
  `appeal_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`appeal_id`),
  KEY `user_id` (`user_id`),
  KEY `notification_id` (`notification_id`),
  CONSTRAINT `appeals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `appeals_ibfk_2` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`notification_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `availability_subscriptions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `availability_subscriptions`;
CREATE TABLE `availability_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requester_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subscription` (`requester_id`,`provider_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `loginlogs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `loginlogs`;
CREATE TABLE `loginlogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `messages`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `request_id` (`request_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(50) DEFAULT 'info',
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `password_resets`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `points_transactions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `points_transactions`;
CREATE TABLE `points_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL,
  `type` enum('earn','spend','refund') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `points_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `points_transactions_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `reports`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','resolved') DEFAULT 'pending',
  PRIMARY KEY (`report_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `requests`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `requests`;
CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `requester_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `details` text NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `points_cost` int(11) DEFAULT 0,
  `requester_confirmed_at` timestamp NULL DEFAULT NULL,
  `provider_confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `hidden_for_requester` tinyint(4) DEFAULT 0,
  `hidden_for_provider` tinyint(4) DEFAULT 0,
  `provider_confirmed` tinyint(1) DEFAULT 0,
  `provider_typing_at` datetime DEFAULT NULL,
  `requester_confirmed` tinyint(1) DEFAULT 0,
  `requester_typing_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `requester_id` (`requester_id`),
  KEY `provider_id` (`provider_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `requests_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `reviews`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `unique_request_review` (`request_id`),
  KEY `provider_id` (`provider_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `services`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'sub',
  `parent_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping default data for table `settings`
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('contact_email', 'athara@gmail.com') ON DUPLICATE KEY UPDATE `setting_value`='athara@gmail.com';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('contact_phone', '(962) 777 999 00') ON DUPLICATE KEY UPDATE `setting_value`='(962) 777 999 00';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('daily_limit', '3') ON DUPLICATE KEY UPDATE `setting_value`='3';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('facebook_link', 'https://www.linkedin.com/in/hussen-al-najdawi-2bb957260') ON DUPLICATE KEY UPDATE `setting_value`='https://www.linkedin.com/in/hussen-al-najdawi-2bb957260';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('from_email', 'noreply@ethraa.com') ON DUPLICATE KEY UPDATE `setting_value`='noreply@ethraa.com';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('instagram_link', 'https://www.linkedin.com/in/hussen-al-najdawi-2bb957260') ON DUPLICATE KEY UPDATE `setting_value`='https://www.linkedin.com/in/hussen-al-najdawi-2bb957260';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('linkedin_link', 'https://www.linkedin.com/in/hussen-al-najdawi-2bb957260') ON DUPLICATE KEY UPDATE `setting_value`='https://www.linkedin.com/in/hussen-al-najdawi-2bb957260';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('maintenance_mode', '0') ON DUPLICATE KEY UPDATE `setting_value`='0';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('smtp_host', 'smtp.gmail.com') ON DUPLICATE KEY UPDATE `setting_value`='smtp.gmail.com';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('smtp_pass', '') ON DUPLICATE KEY UPDATE `setting_value`='';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('smtp_port', '465') ON DUPLICATE KEY UPDATE `setting_value`='465';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('smtp_secure', 'ssl') ON DUPLICATE KEY UPDATE `setting_value`='ssl';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('smtp_user', 'your_email@gmail.com') ON DUPLICATE KEY UPDATE `setting_value`='your_email@gmail.com';
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('twitter_link', 'https://www.linkedin.com/in/hussen-al-najdawi-2bb957260') ON DUPLICATE KEY UPDATE `setting_value`='https://www.linkedin.com/in/hussen-al-najdawi-2bb957260';

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `hide_phone` tinyint(1) DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `governorate` varchar(50) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `violations_points` int(11) DEFAULT 0,
  `muted_until` datetime DEFAULT NULL,
  `banned_until` datetime DEFAULT NULL,
  `free_time_start` time DEFAULT NULL,
  `free_time_end` time DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `failed_attempts` int(11) DEFAULT 0,
  `lockout_time` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `daily_limit` int(11) NOT NULL DEFAULT 3,
  `availability_type` enum('specific','always','unavailable') NOT NULL DEFAULT 'specific',
  `verification_token` varchar(255) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `pending_email` varchar(255) DEFAULT NULL,
  `email_token` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `session_version` int(11) DEFAULT 1,
  `referrer_id` int(11) DEFAULT NULL,
  `referral_rewarded` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1002 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
