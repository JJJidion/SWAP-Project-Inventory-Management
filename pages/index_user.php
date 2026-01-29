<?php
/**
 * Admin Dashboard Page
 *
 * Page allowing admin to perform administrative tasks.
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

// SECURITY CHECK: Ensure the user is actually logged in AND is an User
// If they are not logged in OR they are not an User, kick them out.
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "User") {
    header("Location: login.php");
    exit;
}



$pageTitle = 'Warehouse Staff Dashboard';
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
    <div class="container mt-5"> <h1>Warehouse Staff Dashboard</h1>
        <h3>Welcome, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</h3>
        <br>
    <button type="button" class="btn btn-success ms-2" onclick="window.location.href='user_inventory.php';">
    📦 View Inventory
    </button>
    <button type="button" class="btn btn-success ms-2" onclick="window.location.href='user_search.php';">
    🔎 Search Inventory
    </button>
    </div>
</body>
</html>