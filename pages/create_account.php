<?php
/**
 * Account Creation Page
 *
 * Page allowing inventory managers to create accounts.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Create Account';

// Check if user is logged in and has permission
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isValid = true;

    if (!empty($_POST['first_name']) &&
        !empty($_POST['last_name']) &&
        !empty($_POST['username']) &&
        !empty($_POST['email']) &&
        !empty($_POST['password']) &&
        !empty($_POST['confirm_password']) &&
        !empty($_POST['role']) &&
        !empty($_POST['phone_number'])) {
    }
    else {
        $error =  "Error: No fields should be empty";
        $isValid = false;
    }

    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error =  "Error: Passwords do not match";
        $isValid = false;
    }

    if ($isValid) {
        $firstName=$_POST['first_name'];
        $lastName=$_POST['last_name'];
        $username=$_POST['username'];
        $email=$_POST['email'];
        $password=$_POST['password'];
        $role=$_POST['role'];
        $phoneNumber=$_POST['phone_number'];

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $query= $conn->prepare("INSERT INTO `users` (`email`, `username`, `password_hash`, `role`, `first_name`, `last_name`, `phone_number`) VALUES
        (?,?,?,?,?,?,?)");
        $query->bind_param('sssssss', $email, $username, $passwordHash, $role, $firstName, $lastName, $phoneNumber); //bind the parameters

        if ($query->execute()){  //execute query
            echo "<script>alert('Account successfully created!'); window.location.href='account_management.php';</script>";
            exit; // Stop further execution
        } else {
            echo "Error executing query.";
        }
    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="form">

            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>

            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="text" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number:</label>
                <input type="text" id="phone_number" name="phone_number" required>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="Admin">Inventory Manager</option>
                    <option value="User">Warehouse Staff</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
            <a href="<?php echo BASE_URL; ?>/pages/account_management.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>