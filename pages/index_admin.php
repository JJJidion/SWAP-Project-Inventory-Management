<?php
/**
 * Admin Dashboard Page
 *
 * Page allowing admin to perform administrative tasks.
 */

require_once __DIR__ . '/../config/config.php';

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
    <div class="container">
        <h1>Admin Dashboard</h1>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>