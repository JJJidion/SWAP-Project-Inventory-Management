<?php
    /**
     * Account Deletion Page
     *
     * Page allowing inventory managers to delete user accounts.
     */
    session_start();
    require_once __DIR__ . '/../config/config.php';

    $pageTitle = 'Delete Account';

    if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
        header("Location: login.php");
        exit;
        }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        die("Error: No user ID specified.");
        }

    $userId = $_GET['id'];
    $query= $conn->prepare("DELETE FROM users WHERE id=?");
    $query->bind_param('i', $userId); //bind the parameters
    if ($query->execute()){
        echo "<script>alert('Account successfully deleted!'); window.location.href='account_management.php';</script>";
        exit; // Stop further execution
    } else {
        echo "Error executing query.";
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