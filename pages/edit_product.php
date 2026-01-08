<?php
/**
 * Edit Product Page
 *
 * Allows editing of existing products.
 * Fetches product by ID from URL, displays pre-filled form, and updates database on submission.
 */

require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Edit Product';

// Redirect if no ID provided in URL
if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = intval($_GET['id']);

/**
 * Handle form submission
 * Updates existing product in database
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);

    if (!empty($name) && $price > 0 && !empty($description)) {
        $stmt = $conn->prepare('UPDATE products SET name = ?, price = ?, description = ? WHERE id = ?');
        $stmt->bind_param('sdsi', $name, $price, $description, $id);

        if ($stmt->execute()) {
            $success = 'Product updated successfully!';
        } else {
            $error = 'Error updating product: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Please fill all fields correctly.';
    }
}

// Fetch product details for form
$stmt = $conn->prepare('SELECT * FROM products WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();
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
            <h2>Edit Product</h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="" onsubmit="return validateProductForm()">
                <div class="form-group">
                    <label for="name">Product Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="price">Price ($):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $product['price']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Update Product</button>
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn">Cancel</a>
            </form>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="<?php echo BASE_URL; ?>/javascripts/script.js"></script>
</body>
</html>
