-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.32-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.15.0.7171
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para habbo_clubcortefina
DROP DATABASE IF EXISTS `habbo_clubcortefina`;
CREATE DATABASE IF NOT EXISTS `habbo_clubcortefina` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `habbo_clubcortefina`;

-- Volcando estructura para tabla habbo_clubcortefina.active_time_sessions
DROP TABLE IF EXISTS `active_time_sessions`;
CREATE TABLE IF NOT EXISTS `active_time_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active_time_sessions_user_active` (`user_id`,`ended_at`),
  KEY `idx_active_time_sessions_admin_active` (`admin_id`,`ended_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.active_time_sessions: ~0 rows (aproximadamente)
DELETE FROM `active_time_sessions`;

-- Volcando estructura para tabla habbo_clubcortefina.encargado_requests
DROP TABLE IF EXISTS `encargado_requests`;
CREATE TABLE IF NOT EXISTS `encargado_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `encargado_admin_id` int(11) NOT NULL,
  `requested_by_id` int(11) NOT NULL,
  `status` enum('pendiente','aceptada','denegada','cancelada') NOT NULL DEFAULT 'pendiente',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_encargado_requests_user_status` (`user_id`,`status`),
  KEY `idx_encargado_requests_admin_status` (`encargado_admin_id`,`status`),
  KEY `idx_encargado_requests_requested_by` (`requested_by_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.encargado_requests: ~0 rows (aproximadamente)
DELETE FROM `encargado_requests`;

-- Volcando estructura para tabla habbo_clubcortefina.events
DROP TABLE IF EXISTS `events`;
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.events: ~0 rows (aproximadamente)
DELETE FROM `events`;

-- Volcando estructura para tabla habbo_clubcortefina.games
DROP TABLE IF EXISTS `games`;
CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `room_link` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.games: ~0 rows (aproximadamente)
DELETE FROM `games`;

-- Volcando estructura para tabla habbo_clubcortefina.photos
DROP TABLE IF EXISTS `photos`;
CREATE TABLE IF NOT EXISTS `photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` varchar(50) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.photos: ~0 rows (aproximadamente)
DELETE FROM `photos`;

-- Volcando estructura para tabla habbo_clubcortefina.time_activation_requests
DROP TABLE IF EXISTS `time_activation_requests`;
CREATE TABLE IF NOT EXISTS `time_activation_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `status` enum('pendiente','aceptada','denegada','cancelada') NOT NULL DEFAULT 'pendiente',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_time_activation_requests_user_status` (`user_id`,`status`),
  KEY `idx_time_activation_requests_admin_status` (`admin_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.time_activation_requests: ~0 rows (aproximadamente)
DELETE FROM `time_activation_requests`;

-- Volcando estructura para tabla habbo_clubcortefina.time_logs
DROP TABLE IF EXISTS `time_logs`;
CREATE TABLE IF NOT EXISTS `time_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `total_minutos` int(11) NOT NULL DEFAULT 0,
  `creditos_otorgados` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_time_logs_user` (`user_id`),
  KEY `idx_time_logs_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.time_logs: ~0 rows (aproximadamente)
DELETE FROM `time_logs`;

-- Volcando estructura para tabla habbo_clubcortefina.time_requests
DROP TABLE IF EXISTS `time_requests`;
CREATE TABLE IF NOT EXISTS `time_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `horas` int(11) NOT NULL DEFAULT 0,
  `minutos` int(11) NOT NULL DEFAULT 0,
  `total_minutos` int(11) NOT NULL DEFAULT 0,
  `status` enum('pendiente','completada','cancelada') NOT NULL DEFAULT 'pendiente',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_time_requests_user_status` (`user_id`,`status`),
  KEY `idx_time_requests_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.time_requests: ~0 rows (aproximadamente)
DELETE FROM `time_requests`;

-- Volcando estructura para tabla habbo_clubcortefina.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `habbo_username` varchar(255) NOT NULL,
  `ccf_code` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('user','admin') DEFAULT 'user',
  `avatar` varchar(255) DEFAULT '',
  `rank` int(11) DEFAULT 1,
  `verified` tinyint(1) DEFAULT 0,
  `total_time` int(11) DEFAULT 0,
  `experiencia` int(11) DEFAULT 0,
  `creditos` int(11) DEFAULT 0,
  `horas_acumuladas` int(11) DEFAULT 0,
  `horas_actuales` int(11) DEFAULT 0,
  `encargado_admin_id` int(11) DEFAULT NULL,
  `mission_verified` tinyint(1) DEFAULT 0,
  `mission_verified_at` datetime DEFAULT NULL,
  `is_suspended` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_encargado_admin_id` (`encargado_admin_id`),
  CONSTRAINT `fk_users_encargado_admin` FOREIGN KEY (`encargado_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla habbo_clubcortefina.users: ~0 rows (aproximadamente)
DELETE FROM `users`;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
