<?php
/**
 * About Page
 *
 * Static information page describing the application features and technologies.
 */

require_once __DIR__ . '/../config/config.php';

$pageTitle = 'About';
$conn->close();
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

    <main class="container">
        <div class="card">
            <h2>About Products Manager</h2>
            <p>This is a simple product management system built with PHP and MySQL.</p>

            <h3>Features:</h3>
            <ul>
                <li>Add new products with name, price, and description</li>
                <li>View all products in a responsive grid layout</li>
                <li>Edit existing products</li>
                <li>Delete products with confirmation</li>
                <li>Clean separation of HTML, CSS, and JavaScript</li>
            </ul>

            <h3>Technologies Used:</h3>
            <ul>
                <li>PHP 7.4+</li>
                <li>MySQL Database</li>
                <li>HTML5</li>
                <li>CSS3</li>
                <li>JavaScript (ES6)</li>
            </ul>

            <p><a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">Back to Products</a></p>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
