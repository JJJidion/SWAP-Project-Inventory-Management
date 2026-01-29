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
  `role` enum('Admin', 'User') NOT NULL,
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
INSERT INTO `users` (`id`, `email`, `username`, `password_hash`, `role`, `first_name`, `last_name`, `phone_number`, `created_at`, `updated_at`) VALUES
(1, 'alice@gmail.com', 'Alice_123', '$2y$10$1Lep6tvYv3KX6rfrMYCf/OVkHpAAuBKTQXoBCIRj0s/FbkghQ2htW', 'Admin', 'Alice', 'Tan', '67676767', '2026-01-08 14:25:00', '2026-01-10 12:22:34'),
(2, 'bob@gmail.com', 'Bob_456', '$2y$10$t220d8rfWa7RSgU1I2UcNOGCZ..eMrYdG0pBsfCvUs/S38w3s5vOO', 'Admin', 'Bob', 'Buns', '77676767', '2026-01-08 14:25:00', '2026-01-10 12:22:34'),
(3, 'billykwang@gmail.com', 'billyKwang', '$2y$10$pFGUognB8Hn.BymhXw/BrOo5XhKdlXcz2ANK4S6vdP/4RzdLVH3Se', 'User', 'Billy', 'Kwang', '66667777', '2026-01-21 23:05:36', '2026-01-21 23:05:36');

-- --------------------------------------------------------
-- 4. Table: audit_logs (MOVED UP so it exists before we insert!)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `action` VARCHAR(255),
    `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Table: inventory (Your Work)
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

-- 1. Clear previous data
TRUNCATE TABLE `inventory`;

-- 2. Insert Advanced Manufacturing Items
INSERT INTO `inventory` (`part_name`, `category`, `supplier`, `stock_level`) VALUES
('Aluminum 6061 Rod (20mm)', 'Raw Materials', 'Alcoa', 120),
('Carbide End Mill (1/4 inch)', 'Tooling', 'Sandvik Coromant', 4),   
('PLA Filament 1.75mm (Black)', 'Consumables', 'Prusa Polymers', 45),
('NEMA 17 Stepper Motor', 'Components', 'Moons Industries', 30),
('Industrial Coolant (5 Gallon)', 'Consumables', 'Blaser Swisslube', 2), 
('Stainless Steel Sheet (3mm)', 'Raw Materials', 'ThyssenKrupp', 0),    
('Digital Caliper (150mm)', 'Tooling', 'Mitutoyo', 15),
('Robotic Arm Servo (MG996R)', 'Components', 'Tower Pro', 8),            
('Laser Cutter Focus Lens', 'Tooling', 'II-VI Infrared', 10),
('Ball Bearing (608ZZ)', 'Components', 'SKF Bearings', 200);

-- 3. Log this update (NOW THIS WORKS)
INSERT INTO `audit_logs` (`user_id`, `action`, `timestamp`) VALUES 
(1, 'Database reset: Loaded Manufacturing Inventory', NOW());

-- --------------------------------------------------------
-- 6. Table: parts_log (Ashton Neo) -- Deprecated
-- --------------------------------------------------------
DROP TABLE IF EXISTS `parts_log`;
CREATE TABLE `parts_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255), 
    `part_used` VARCHAR(255), 
    `amount_used` INT,
    `date_used` DATE, 
    `comments` VARCHAR(255), 
    `claim_submission_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

INSERT INTO `parts_log` (`username`, `part_used`, `amount_used`, `date_used`, `comments`) VALUES
('User', 'Insert Part Here', '2', '2025-12-07', 'Project A5: Device Manufacturing'),
('Admin', 'Insert Part Here', '7', '2025-12-15', 'AMC Maintenance'),
('Admin', 'Insert Part Here', '1', '2025-12-21', 'Robot Repairs'),
('User', 'Insert Part Here', '12', '2026-01-06', 'Project A5: Device Manufacturing'),
('User', 'Insert Part Here', '5', '2025-01-11', 'Project B2: Automated Assembly'); 

-- Create a specific user for the AI Search
-- Added DROP to prevent errors if you run this script twice
DROP USER IF EXISTS 'ai_search_bot'@'localhost';
CREATE USER 'ai_search_bot'@'localhost' IDENTIFIED BY 'StrongPassword123!';

-- Grant ONLY SELECT permissions
GRANT SELECT ON inv_management_db.* TO 'ai_search_bot'@'localhost';

FLUSH PRIVILEGES;