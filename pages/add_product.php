<?php
/**
 * Add Product Page
 *
 * Displays a form to create new products and handles form submission.
 * Validates input data and inserts new products into the database.
 */

require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Add Product';

/**
 * Handle form submission
 * Validates and inserts new product into database
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);

    if (!empty($name) && $price > 0 && !empty($description)) {
        $stmt = $conn->prepare('INSERT INTO products (name, price, description) VALUES (?, ?, ?)');
        $stmt->bind_param('sds', $name, $price, $description);

        if ($stmt->execute()) {
            $success = 'Product added successfully!';
            $name = $price = $description = '';
        } else {
            $error = 'Error adding product: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Please fill all fields correctly.';
    }
}

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
            <h2>Add New Product</h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="" onsubmit="return validateProductForm()">
                <div class="form-group">
                    <label for="name">Product Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="price">Price ($):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo isset($price) ? $price : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Add Product</button>
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn">Cancel</a>
            </form>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?php echo BASE_URL; ?>/javascripts/script.js"></script>
</body>
</html>
