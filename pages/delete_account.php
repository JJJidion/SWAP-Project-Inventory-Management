<?php
/**
 * Account Deletion Script
 *
 * This script handles the deletion of user accounts from the database.
 * It performs security checks to ensure only Administrators can access it
 * and prevents an Administrator from deleting their own account while logged in.
 */

session_start();

// 1. Configuration & Imports
require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

$pageTitle = 'Delete Account';

// 2. Security Check: Admin Authorization
// Ensure the user is logged in and holds the 'Admin' role.
// If not, redirect them to the login page immediately.
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}

// 3. Input Validation
// Verify that an ID has been passed via the URL (GET request).
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: No user ID specified.");
}

$userIdToDelete = $_GET['id'];

// 4. Security Check: Prevent Self-Deletion
// Administrators should not be able to delete the account they are currently using.
// We compare the requested ID against the session ID.
if ($userIdToDelete == $_SESSION['user_id']) {
    // If a match is found, alert the user via JavaScript and redirect.
    echo "<script>
        alert('Error: You cannot delete your own account while logged in!'); 
        window.location.href='account_management.php';
    </script>";
    
    // Stop script execution to ensure the DELETE query below never runs.
    exit; 
}

// 5. Database Deletion
// Prepare the SQL statement to prevent SQL Injection.
$query = $conn->prepare("DELETE FROM users WHERE id=?");
$query->bind_param('i', $userIdToDelete);

if ($query->execute()) {
    // Success: Notify the user and redirect back to the management dashboard.
    echo "<script>alert('Account successfully deleted!'); window.location.href='account_management.php';</script>";
    exit; 
} else {
    // Failure: Display the database error message for debugging.
    echo "Error executing query: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account</title>
</head>
<body>
</body>
</html>