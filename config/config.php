<?php
/**
 * Application Configuration
 *
 * Defines database connection settings.
 */

// Project Configuration
$host = $_SERVER['HTTP_HOST'];
$base = '/SWAP-Project-Inventory-Management';
define('BASE_URL', 'http://' . $host . $base);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inv_management_db');

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3306);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8');
?>
