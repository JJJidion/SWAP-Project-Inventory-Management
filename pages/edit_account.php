<?php
/**
 * Edit Account Page
 *
 * Page allowing inventory managers to edit user accounts.
 */
session_start();
require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Edit Account';

// Security Check
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: No user ID specified.");
}

$userId = $_GET['id'];
$error = '';
$message = '';

// HANDLE FORM SUBMISSION (UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST['username'];
    $email = $_POST['email'];
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $role = $_POST['role'];
    $phoneNumber = $_POST['phone_number'];
    $isValid = true;

    // Validate Basic Fields
    $requiredFields = ['first_name', 'last_name', 'username', 'email', 'role', 'phone_number'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $error = "Error: Basic profile fields cannot be empty";
            $isValid = false;
            break;
        }
    }

    // Handle Password Logic (Only if user typed something)
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $updatePassword = false;

    if (!empty($password)) {
        $updatePassword = true;

        if ($password !== $confirmPassword) {
            $error = "Error: Passwords do not match";
            $isValid = false;
        } elseif (strlen($password) < 10) {
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

    if ($isValid) {
        // Validate Email Format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Error: Invalid email format.";
            $isValid = false;
        }

        // Validate Role (Security against Inspect Element hacks)
        $allowedRoles = ['Admin', 'User'];
        if (!in_array($role, $allowedRoles)) {
            $error = "Error: Invalid role selected.";
            $isValid = false;
        }

        // Validate Phone (Exactly 8 Digits)
        if (!preg_match("/^[0-9]{8}$/", $phoneNumber)) {
            $error = "Error: Phone number must be exactly 8 digits.";
            $isValid = false;
        }
    }

    // Execute Update
    if ($isValid) {

        if ($updatePassword) {
            // Update WITH password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $sql = "UPDATE users SET username = ?, email = ?, password_hash = ?, first_name = ?, last_name = ?, role = ?, phone_number = ?, updated_at = NOW() WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssssi", $username, $email, $passwordHash, $firstName, $lastName, $role, $phoneNumber, $userId);
            }
        } else {
            // Update WITHOUT password
            $sql = "UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role = ?, phone_number = ?, updated_at = NOW() WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssssssi", $username, $email, $firstName, $lastName, $role, $phoneNumber, $userId);
            }
        }

        if (isset($stmt) && $stmt->execute()) {
            echo "<script>alert('Account updated successfully!'); window.location.href='account_management.php';</script>";
            exit;
        } else {
            $error = "Error updating account: " . $conn->error;
        }
        if (isset($stmt)) $stmt->close();
    }
}

// FETCH EXISTING DATA
$sql = "SELECT id, username, email, first_name, last_name, role, phone_number FROM users WHERE id = ?";
$userData = null;

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $userData = $result->fetch_assoc();
        } else {
            die("Error: User not found.");
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validator/13.11.0/validator.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <main>
            <div style="margin-top: 2rem; margin-bottom: 1rem;">
                <h1 style="text-align: center; color: #333;">Edit Account</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <form action="edit_account.php?id=<?php echo $userId; ?>" method="POST" id="editAccountForm">
                    
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                    </div>

                    <div style="margin: 2rem 0; padding: 1rem; background-color: #f9f9f9; border-left: 4px solid #4CAF50;">
                        <h3 style="margin-bottom: 0.5rem; color: #333;">Change Password</h3>
                        <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                            Leave these fields blank if you want to keep the current password.
                        </p>

                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password" placeholder="Enter new password">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; background-color: white;">
                            <option value="Admin" <?php echo ($userData['role'] == 'Admin') ? 'selected' : ''; ?>>Inventory Manager</option>
                            <option value="User" <?php echo ($userData['role'] == 'User') ? 'selected' : ''; ?>>Warehouse Staff</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" value="<?php echo htmlspecialchars($userData['phone_number']); ?>">
                    </div>

                    <div style="margin-top: 2rem; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Update Account</button>
                        <a href="account_management.php" class="btn btn-danger">Cancel</a>
                    </div>
                    
                </form>
            </div>
        </main>
    </div>


    <script>
        document.getElementById('editAccountForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Only validate if user typed a password
            if (password.length > 0) {
                const rules = { minLength: 10, minLowercase: 1, minUppercase: 1, minNumbers: 1, minSymbols: 1 };

                if (!validator.isStrongPassword(password, rules)) {
                    e.preventDefault();
                    alert('New password is too weak!\nIt must be at least 10 characters long and contain:\n- Uppercase letter\n- Lowercase letter\n- Number\n- Special character');
                    return;
                }

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return;
                }
            }
        });
    </script>
</body>
</html>