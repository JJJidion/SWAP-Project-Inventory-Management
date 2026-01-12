-- ========================================
-- DATABASE SETUP FILE
-- ========================================
-- Run this in phpMyAdmin: http://localhost/phpmyadmin
-- Click "SQL" tab, paste this code, click "Go"

-- ========================================
-- CREATE DATABASE
-- ========================================

CREATE DATABASE IF NOT EXISTS inv_management_db;

USE inv_management_db;

-- ========================================
-- CREATE PRODUCTS TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL, -- function auto creates salt
    role ENUM('Admin', 'User') DEFAULT 'User' NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(10) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- INSERT SAMPLE DATA
-- ========================================

INSERT INTO users (email, username, password_hash, role, first_name, last_name, phone_number) VALUES
    ('admin@gmail.com', 'Admin', '$2a$10$Di7knnd.pHrgu75uJ863D.vZJERMQ0Ic71c9E/CyU1XUn3ZC.ampC', 'Admin', 'John', 'Doe', '12345678');
    ('user@gmail.com', 'User', '$2a$10$SbtdcL2vbNDWunMCAAEZiuKiOP60yppkOU9hGJyY9Qbj8FuDNOPUS', 'User', 'Jimmy', 'Lee', '87654321',);
-- ========================================
-- VERIFICATION (Optional)
-- ========================================
-- SELECT * FROM products;
-- DESCRIBE products;

-- Setup complete! Your database is ready to use.

