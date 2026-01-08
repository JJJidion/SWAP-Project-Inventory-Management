<?php
/**
 * Application Configuration
 *
 * Defines database connection settings.
 */

// Project Configuration
$host = $_SERVER['HTTP_HOST'];
$base = '/sample_project';
define('BASE_URL', 'http://' . $host . $base);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'products_db');

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8');
?>
