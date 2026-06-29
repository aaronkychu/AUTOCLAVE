DROP DATABASE IF EXISTS autoclave;

CREATE DATABASE autoclave
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
  
USE autoclave;

DROP TABLE IF EXISTS `autoclaves`;
CREATE TABLE IF NOT EXISTS `autoclaves` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `autoclaves` (`id`, `name`, `location`, `created_at`) VALUES
(1, 'W&H LISA', 'Sterilization Room', '2022-01-01 00:00:00');

DROP TABLE IF EXISTS `autoclave_cycles`;
CREATE TABLE IF NOT EXISTS `autoclave_cycles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `autoclave_id` int NOT NULL,
  `start_time` datetime NOT NULL,
  `started_by` int NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `ended_by` int DEFAULT NULL,
  `load_status` enum('Running','Completed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicator_result` enum('Pass','Fail') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_datetime` datetime DEFAULT NULL,
  `result_by` int DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `autoclave_id` (`autoclave_id`),
  KEY `started_by` (`started_by`),
  KEY `ended_by` (`ended_by`),
  KEY `result_by` (`result_by`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `biological_tests`;
CREATE TABLE IF NOT EXISTS `biological_tests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `autoclave_id` int NOT NULL,
  `test_datetime` datetime NOT NULL,
  `result` enum('Pass','Fail') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `autoclave_id` (`autoclave_id`),
  KEY `performed_by` (`performed_by`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
