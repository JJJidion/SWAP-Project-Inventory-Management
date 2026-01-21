<?php
/**
 * Parts Usage Page: For Users submit parts use claims [Can only see claims they have sent] (Will be done by ZF)
 */

session_start();
require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Parts Log';

if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <title>Parts Log</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>