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

if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}
$pageTitle = 'Inventory Manager Dashboard';

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
    
    <div class="container mt-5"> <h1>Inventory Manager Dashboard</h1>
        <h3>Welcome, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</h3>
        <br>
        
        <button type="button" class="btn btn-primary" onclick="window.location.href='account_management.php';">
            Account Management
        </button>

        <button type="button" class="btn btn-success ms-2" onclick="window.location.href='manage_inventory.php';">
            📦 Manage Inventory
        </button>

        <button type="button" class="btn btn-success ms-2" onclick="window.location.href='admin_search.php';">
        🔎 Search Inventory
        </button>

        <button type="button" class="btn btn-success ms-2" onclick="window.location.href='report_management.php';">
        Report Management
        </button>
        <hr class="my-4">

        <h4>🔐 Security & Monitoring</h4>

        <?php if ($_SESSION["role"] === "Admin"): ?>
            <button type="button"
                    class="btn btn-dark mt-2"
                    onclick="window.location.href='<?php echo BASE_URL; ?>/audit/audit_dashboard.php';">
                🔐 Authentication Logs
            </button>
        <?php endif; ?>
    </div>

</body>
</html>