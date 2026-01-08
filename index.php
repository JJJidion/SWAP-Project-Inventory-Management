<?php
/**
 * Homepage - Product List Display
 *
 * Displays all products from the database in a responsive grid layout.
 * Handles product deletion with confirmation and displays success/error messages.
 */

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Home - Products List';

/**
 * Handle product deletion
 * Processes delete requests from URL parameter and removes product from database
 */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        $success = 'Product deleted successfully!';
    } else {
        $error = 'Error deleting product.';
    }
    $stmt->close();
}

// Fetch all products ordered by newest first
$sql = 'SELECT * FROM products ORDER BY created_at DESC';
$result = $conn->query($sql);
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
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="container">
        <div class="card">
            <h2>Products List</h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($result->num_rows > 0): ?>
                <div class="products-grid">
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="product-card">
                            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                            <p class="price">$<?php echo number_format($row['price'], 2); ?></p>
                            <p class="description"><?php echo htmlspecialchars($row['description']); ?></p>

                            <div>
                                <a href="<?php echo BASE_URL; ?>/pages/edit_product.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">Edit</a>
                                <a href="<?php echo BASE_URL; ?>/index.php?delete=<?php echo $row['id']; ?>"
                                   class="btn btn-danger"
                                   onclick="return confirmDelete('<?php echo htmlspecialchars($row['name']); ?>')">
                                    Delete
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No products found.<br><a href="<?php echo BASE_URL; ?>/pages/add_product.php">Add your first product</a></p>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?php echo BASE_URL; ?>/javascripts/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
