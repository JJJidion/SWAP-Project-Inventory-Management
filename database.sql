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
-- 2. Table: login_attempts
-- --------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Table: users
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
-- 4. Table: audit_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50),
    role VARCHAR(20),
    action VARCHAR(50),
    entity VARCHAR(50),
    entity_id INT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO audit_logs (user_id, username, role, action, entity, description)
VALUES (
1,
'Admin',
'Admin',
'SYSTEM_INIT',
'database',
'Database reset: Loaded Manufacturing Inventory'
);

-- --------------------------------------------------------
-- 5. Table: parts_log (Ashton Neo) //Moved here to ensure the audit tables exist before inventory
-- --------------------------------------------------------
DROP TABLE IF EXISTS `parts_log`;
CREATE TABLE `parts_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255), 
    `part_used` VARCHAR(255), 
    `amount_used` INT,
    `date_used` DATE, 
    `project_id` VARCHAR(255), -- Added this for Students to show that usage of parts was for project
    `comments` VARCHAR(255), 
    `claim_submission_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Table: report_audit_log (Ashton Neo)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `report_audit_log`;
CREATE TABLE `report_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `SHA256_ID` VARCHAR(255),
    `report_type` VARCHAR(50),
    `generated_by` VARCHAR(255),
    `claim_submission_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Table: inventory
-- --------------------------------------------------------
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `part_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) DEFAULT 'General',
    `supplier` VARCHAR(100) DEFAULT 'Unknown',
    `stock_level` INT NOT NULL DEFAULT 0,
    `cost_per_part` FLOAT(6,2),
    `status` ENUM('active', 'obsolete') DEFAULT 'active',
    `is_deleted` TINYINT(1) DEFAULT 0, 
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1. Clear previous data
TRUNCATE TABLE `inventory`;

-- 2. Insert Advanced Manufacturing Items
INSERT INTO `inventory` (`part_name`, `category`, `supplier`, `stock_level`, `cost_per_part`) VALUES
('Aluminum 6061 Rod (20mm)', 'Raw Materials', 'Gidcoa', 120, 12.50),
('Carbide End Mill (1/4 inch)', 'Tooling', 'Sandvik Coromant', 4, 377.89),   
('PLA Filament 1.75mm (Black)', 'Consumables', 'Moonlight Polymers', 45, 23.30),
('NEMA 17 Stepper Motor', 'Components', 'Columbina Industries', 30, 14.00),
('Industrial Coolant (5 Gallon)', 'Consumables', 'Blaser Swisslube', 2, 476.97), 
('Stainless Steel Sheet (3mm)', 'Raw Materials', 'ThyssenKrupp', 0, 13.88),    
('Digital Caliper (150mm)', 'Tooling', 'Mitutoyo', 15, 37.90),
('Robotic Arm Servo (MG996R)', 'Components', 'Lye Pro', 8, 6944.88),            
('Laser Cutter Focus Lens', 'Tooling', 'II-VI Infrared', 10, 2450.00),
('Ball Bearing (608ZZ)', 'Components', 'LZF Bearings', 200, 1.89);

COMMIT;

-- 1. Fix Inventory Table (Add Price)
ALTER TABLE `inventory` 
ADD COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `stock_level`;

-- 2. Fix Audit Logs Table (Add Timestamp)
-- We alter the table to ensure the column exists
ALTER TABLE `audit_logs` 
ADD COLUMN `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP;

-- 3. (Optional) Insert a test log to make sure it works now
INSERT INTO `audit_logs` (`user_id`, `action`, `timestamp`) VALUES 
(1, 'System Repair: Database columns fixed', NOW());

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- Revised parts_log data, Do note that test cases dates only range from 26/07/31 to 31/07/31

-- Clear existing test data
TRUNCATE TABLE `parts_log`;

-- Insert sample parts usage data (dates in July-August 2025 for testing)
INSERT INTO `parts_log` (`username`, `part_used`, `amount_used`, `date_used`, `project_id`, `comments`) VALUES

-- Project A5: Device Manufacturing
('billyKwang', 'Aluminum 6061 Rod (20mm)', 5, '2025-07-26', 'A5', 'Project A5: Device Manufacturing - Frame Construction'),
('billyKwang', 'NEMA 17 Stepper Motor', 2, '2025-07-26', 'A5', 'Project A5: Device Manufacturing - Motor Installation'),
('billyKwang', 'Ball Bearing (608ZZ)', 8, '2025-07-27', 'A5', 'Project A5: Device Manufacturing - Assembly'),
('billyKwang', 'Digital Caliper (150mm)', 1, '2025-07-29', 'A5', 'Project A5: Quality Measurement Tool'),

-- AMC Maintenance (MAINT)
('Alice_123', 'Industrial Coolant (5 Gallon)', 1, '2025-07-27', 'MAINT', 'AMC Maintenance - CNC Machine Coolant Refill'),
('Bob_456', 'Carbide End Mill (1/4 inch)', 2, '2025-07-28', 'MAINT', 'AMC Maintenance - Worn Tool Replacement'),
('Alice_123', 'Laser Cutter Focus Lens', 1, '2025-07-30', 'MAINT', 'AMC Maintenance - Laser Recalibration');

-- Create a specific user for the AI Search
-- Added DROP to prevent errors if you run this script twice
DROP USER IF EXISTS 'ai_search_bot'@'localhost';
CREATE USER 'ai_search_bot'@'localhost' IDENTIFIED BY 'StrongPassword123!';

-- Grant ONLY SELECT permissions
GRANT SELECT ON inv_management_db.* TO 'ai_search_bot'@'localhost'; -- Previously Commented out for DB Creation to work

FLUSH PRIVILEGES; -- Previously Commented out for DB Creation to work