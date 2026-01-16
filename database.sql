-- 1. Setup Database
CREATE DATABASE IF NOT EXISTS `inv_management_db`;
USE `inv_management_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 2. Table: login_attempts (Teammate's Work - DO NOT TOUCH)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Table: users (Teammate's Work - DO NOT TOUCH STRUCTURE)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','User','Inventory Manager') NOT NULL DEFAULT 'User',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone_number` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dump data for users
-- ADDED: A user with 'Inventory Manager' role so you can test!
INSERT INTO `users` (`id`, `email`, `username`, `password_hash`, `role`, `first_name`, `last_name`, `phone_number`, `created_at`, `updated_at`) VALUES
(1, 'admin@gmail.com', 'Admin', '$2a$10$Di7knnd.pHrgu75uJ863D.vZJERMQ0Ic71c9E/CyU1XUn3ZC.ampC', 'Admin', 'John', 'Doe', '67676767', '2026-01-08 14:25:00', '2026-01-10 12:22:34'),
(2, 'user@gmail.com', 'User', '$2y$10$FUAlQQdWuKOgh0QQKpBoL.0QqOTdT3i6Yxhez4Y2/tcxzPG/5iRCu', 'User', 'Jimmy', 'Lee', '12345678', '2026-01-11 08:31:30', '2026-01-12 14:31:55'),
(3, 'manager@gmail.com', 'Manager', '$2y$10$FUAlQQdWuKOgh0QQKpBoL.0QqOTdT3i6Yxhez4Y2/tcxzPG/5iRCu', 'Inventory Manager', 'Test', 'Manager', '99999999', '2026-01-14 09:00:00', '2026-01-14 09:00:00');

-- --------------------------------------------------------
-- 4. Table: inventory (Your Work - You can modify this freely)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `part_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) DEFAULT 'General',
    `supplier` VARCHAR(100) DEFAULT 'Unknown',
    `stock_level` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'obsolete') DEFAULT 'active',
    `is_deleted` TINYINT(1) DEFAULT 0, 
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Table: audit_logs (Shared Work - Coordinate changes)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `action` VARCHAR(255),
    `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;