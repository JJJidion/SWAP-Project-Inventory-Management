<?php
/**
 * Account Creation Page
 *
 * Allows Administrators to create new user accounts.
 * Includes validation for password complexity, role security, and duplicate detection.
 */

session_start();

// 1. Configuration & Imports
require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

$pageTitle = 'Create Account';
$error = '';
$success = false;

// 2. Security Check (Role-Based Access Control)
// Ensure only Admins can access this page.
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Sanitize Input
    // Remove whitespace from beginning/end of strings
    $firstName   = trim($_POST['first_name'] ?? '');
    $lastName    = trim($_POST['last_name'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $role        = $_POST['role'] ?? '';
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    
    $isValid = true;

    // B. Validation: Empty Fields
    if (empty($firstName) || empty($lastName) || empty($username) || 
        empty($email) || empty($password) || empty($role) || empty($phoneNumber)) {
        
        $error = "Error: All fields are required.";
        $isValid = false;
    }

    if ($isValid) {
        // Validation: Email Format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Error: Invalid email format.";
            $isValid = false;
        }

        // Validation: Role (Security Allowlist)
        // Prevents users from injecting invalid roles via "Inspect Element"
        $allowedRoles = ['Admin', 'User'];
        if (!in_array($role, $allowedRoles)) {
            $error = "Error: Invalid role selected.";
            $isValid = false;
        }

        // Validation: Phone Number (Strict Regex)
        if (!preg_match("/^[0-9]{8}$/", $phoneNumber)) {
            $error = "Error: Phone number must be exactly 8 digits.";
            $isValid = false;
        }

        // Validation: Password Match
        if ($password !== $confirmPass) {
            $error = "Error: Passwords do not match.";
            $isValid = false;
        }
    }

    // C. Validation: Password Complexity (Server-Side)
    // We enforce this on the server in case JS is disabled or bypassed.
    if ($isValid) {
        if (strlen($password) < 10) {
            $error = "Error: Password must be at least 10 characters long.";
            $isValid = false;
        } elseif (!preg_match("/[A-Z]/", $password) || 
                  !preg_match("/[a-z]/", $password) || 
                  !preg_match("/[0-9]/", $password) || 
                  !preg_match("/[\W_]/", $password)) {
            
            $error = "Error: Password must contain uppercase, lowercase, number, and special char.";
            $isValid = false;
        }
    }

    // D. Database Operations
    if ($isValid) {
        
        // Step 1: Check for Duplicates
        // It is best practice to check if the user exists before trying to insert.
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkStmt->bind_param("ss", $username, $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "Error: Username or Email already exists.";
            $isValid = false;
        }
        $checkStmt->close();

        // Step 2: Insert New User
        if ($isValid) {
            // Hash the password using BCRYPT
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $insertStmt = $conn->prepare("INSERT INTO users (email, username, password_hash, role, first_name, last_name, phone_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if ($insertStmt) {
                $insertStmt->bind_param('sssssss', $email, $username, $passwordHash, $role, $firstName, $lastName, $phoneNumber);

                if ($insertStmt->execute()) {
                    // Success: Redirect
                    echo "<script>alert('Account successfully created!'); window.location.href='account_management.php';</script>";
                    exit;
                } else {
                    // Log error internally
                    error_log("Database Insert Error: " . $insertStmt->error);
                    $error = "Error creating account. Please try again.";
                }
                $insertStmt->close();
            } else {
                error_log("Database Prepare Error: " . $conn->error);
                $error = "Internal system error.";
            }
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

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="form" id="createAccountForm">

            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required 
                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required 
                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
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
                <input type="text" id="phone_number" name="phone_number" required 
                       value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="Admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Admin') ? 'selected' : ''; ?>>Inventory Manager</option>
                    <option value="User" <?php echo (isset($_POST['role']) && $_POST['role'] === 'User') ? 'selected' : ''; ?>>Warehouse Staff</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Account</button>
                <a href="<?php echo BASE_URL; ?>/pages/account_management.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

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