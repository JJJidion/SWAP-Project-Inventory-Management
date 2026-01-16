-- 1. Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `inv_management_db`;

-- 2. Select the database to use
USE `inv_management_db`;

-- 3. Set up configurations
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`; -- Safety measure to prevent errors if table exists
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','User') NOT NULL DEFAULT 'User',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone_number` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `password_hash`, `role`, `first_name`, `last_name`, `phone_number`, `created_at`, `updated_at`) VALUES
(1, 'admin@gmail.com', 'Admin', '$2a$10$Di7knnd.pHrgu75uJ863D.vZJERMQ0Ic71c9E/CyU1XUn3ZC.ampC', 'Admin', 'John', 'Doe', '67676767', '2026-01-08 14:25:00', '2026-01-10 12:22:34'),
(2, 'user@gmail.com', 'User', '$2y$10$FUAlQQdWuKOgh0QQKpBoL.0QqOTdT3i6Yxhez4Y2/tcxzPG/5iRCu', 'User', 'Jimmy', 'Lee', '12345678', '2026-01-11 08:31:30', '2026-01-12 14:31:55');

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `phone_number` (`phone_number`);

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;