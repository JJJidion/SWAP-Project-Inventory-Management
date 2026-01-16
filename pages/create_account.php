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

    // 1. Basic Empty Check
    if (empty($_POST['first_name']) ||
        empty($_POST['last_name']) ||
        empty($_POST['username']) ||
        empty($_POST['email']) ||
        empty($_POST['password']) ||
        empty($_POST['confirm_password']) ||
        empty($_POST['role']) ||
        empty($_POST['phone_number'])) {
        
        $error = "Error: No fields should be empty";
        $isValid = false;
    }

    // 2. Password Match Check
    if ($isValid && $_POST['password'] !== $_POST['confirm_password']) {
        $error = "Error: Passwords do not match";
        $isValid = false;
    }

    // 3. Password Complexity Check (Server-Side Security)
    // Rules: Min 10 chars, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Char
    if ($isValid) {
        $pwd = $_POST['password'];
        if (strlen($pwd) < 10) {
            $error = "Error: Password must be at least 10 characters long.";
            $isValid = false;
        } elseif (!preg_match("/[A-Z]/", $pwd) || 
                  !preg_match("/[a-z]/", $pwd) || 
                  !preg_match("/[0-9]/", $pwd) || 
                  !preg_match("/[\W_]/", $pwd)) { // \W matches non-word chars (symbols), _ matches underscore
            
            $error = "Error: Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
            $isValid = false;
        }
    }

    if ($isValid) {
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        $phoneNumber = $_POST['phone_number'];

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Ideally, check if username/email already exists before inserting to prevent SQL errors
        $query = $conn->prepare("INSERT INTO `users` (`email`, `username`, `password_hash`, `role`, `first_name`, `last_name`, `phone_number`) VALUES (?,?,?,?,?,?,?)");
        $query->bind_param('sssssss', $email, $username, $passwordHash, $role, $firstName, $lastName, $phoneNumber);

        if ($query->execute()){
            echo "<script>alert('Account successfully created!'); window.location.href='account_management.php';</script>";
            exit;
        } else {
            $error = "Error creating account: " . $conn->error;
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
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validator/13.11.0/validator.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="form" id="createAccountForm">

            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <small style="color: gray; display: block; margin-top: 5px;">
                    Must be 10+ chars, include Uppercase, Lowercase, Number, and Symbol.
                </small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number:</label>
                <input type="text" id="phone_number" name="phone_number" required value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="Admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Admin') ? 'selected' : ''; ?>>Inventory Manager</option>
                    <option value="User" <?php echo (isset($_POST['role']) && $_POST['role'] === 'User') ? 'selected' : ''; ?>>Warehouse Staff</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
            <a href="<?php echo BASE_URL; ?>/pages/account_management.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        document.getElementById('createAccountForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Configuration for validator.js
            const rules = {
                minLength: 10,
                minLowercase: 1,
                minUppercase: 1,
                minNumbers: 1,
                minSymbols: 1
            };

            // 1. Check Complexity
            if (!validator.isStrongPassword(password, rules)) {
                e.preventDefault(); // Stop form submission
                alert('Password is too weak!\nIt must be at least 10 characters long and contain:\n- Uppercase letter\n- Lowercase letter\n- Number\n- Special character');
                return;
            }

            // 2. Check Matching
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
        });
    </script>
</body>
</html>