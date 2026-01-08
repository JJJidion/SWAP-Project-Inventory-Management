-- ========================================
-- DATABASE SETUP FILE
-- ========================================
-- Run this in phpMyAdmin: http://localhost/phpmyadmin
-- Click "SQL" tab, paste this code, click "Go"

-- ========================================
-- CREATE DATABASE
-- ========================================

CREATE DATABASE IF NOT EXISTS products_db;

USE products_db;

-- ========================================
-- CREATE PRODUCTS TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- INSERT SAMPLE DATA
-- ========================================

INSERT INTO products (name, price, description) VALUES
    ('Laptop', 999.99, 'High-performance laptop with 16GB RAM and 512GB SSD'),
    ('Wireless Mouse', 29.99, 'Ergonomic wireless mouse with USB receiver'),
    ('Mechanical Keyboard', 79.99, 'RGB mechanical keyboard with Cherry MX switches'),
    ('Monitor', 299.99, '27-inch 4K UHD monitor with HDR support'),
    ('USB-C Hub', 49.99, '7-in-1 USB-C hub with multiple ports');

-- ========================================
-- VERIFICATION (Optional)
-- ========================================
-- SELECT * FROM products;
-- DESCRIBE products;

-- Setup complete! Your database is ready to use.
