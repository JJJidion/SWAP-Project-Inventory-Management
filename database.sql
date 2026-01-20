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

-- --------------------------------------------------------
-- 6. Table: parts_log (Ashton Neo)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `parts_log`;
CREATE TABLE `parts_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255), -- User Input
    `part_used` VARCHAR(255), -- FuzzySearch for this? Dropdown Selector as well since there are a lot of parts
    `amount_used` INT,
    `date_used` DATE, -- User Input
    `comments` VARCHAR(255), -- Reason for use of parts
    `claim_submission_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- 1. Optional: Clear existing items to start fresh
TRUNCATE TABLE `inventory`;

-- 2. Insert 10 Realistic Items
-- We include varied stock levels to test your Red/Yellow/Green badges.

INSERT INTO `inventory` (`part_name`, `category`, `supplier`, `stock_level`) VALUES
('Wireless Mouse M350', 'Electronics', 'Logitech', 45),
('HDMI Cable (2m)', 'Hardware', 'Ugreen', 150),
('Mechanical Keyboard', 'Electronics', 'Keychron', 8),   -- LOW STOCK (<10)
('Samsung 24" Monitor', 'Electronics', 'Samsung', 12),
('USB-C Hub', 'Hardware', 'Anker', 0),                  -- OUT OF STOCK (0)
('Thermal Paste 4g', 'Hardware', 'Arctic', 55),
('Office Chair (Mesh)', 'General', 'Ikea', 3),          -- LOW STOCK (<10)
('Desk Lamp', 'General', 'Xiaomi', 20),
('GTX 1660 Super', 'Electronics', 'NVIDIA', 1),         -- LOW STOCK (<10)
('AA Batteries (Pack)', 'General', 'Duracell', 200);

-- 3. Add a sample Audit Log entry so the log viewer isn't empty
INSERT INTO `audit_logs` (`user_id`, `action`, `timestamp`) VALUES 
(1, 'Generated sample database data', NOW());

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Insert 5 Realistic Logs for Parts Log (Simulate Previous Usage) [Do Insert Parts when that section has been edited appropriately]
INSERT INTO `parts_log` (`username`, `part_used`, `amount_used`, `date_used`, `comments`) VALUES
('User', 'Insert Part Here', '2', '2025-12-07', 'Project A5: Device Manufacturing'),
('Admin', 'Insert Part Here', '7', '2025-12-15', 'AMC Maintenance'),
('Admin', 'Insert Part Here', '1', '2025-12-21', 'Robot Repairs'),
('User', 'Insert Part Here', '12', '2026-01-06', 'Project A5: Device Manufacturing'),
('User', 'Insert Part Here', '5', '2025-01-11', 'Project B2: Automated Assembly'); -- Edit to ensure date cannot be in the future?