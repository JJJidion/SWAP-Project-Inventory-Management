<?php
/**
 * Admin Dashboard Page
 *
 * Page allowing admin to perform administrative tasks.
 */

session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}
$pageTitle = 'Admin Dashboard';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container mt-5"> <h1>Admin Dashboard</h1>
        <h3>Welcome, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</h3>
        <br>
        
        <button type="button" class="btn btn-primary" onclick="window.location.href='account_management.php';">
            Account Management
        </button>

        <button type="button" class="btn btn-success ms-2" onclick="window.location.href='manage_inventory.php';">
            📦 Manage Inventory
        </button>

    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>