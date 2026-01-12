<?php
    /**
     * Edit Account Page
     *
     * Page allowing inventory managers to edit user accounts.
     */
    session_start();
    require_once __DIR__ . '/../config/config.php';
    
    $pageTitle = 'Edit Account';

    if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        die("Error: No user ID specified.");
    }

    $userId = $_GET['id'];

    // 2. HANDLE FORM SUBMISSION (UPDATE)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        $isValid = true;

        $requiredFields = ['first_name', 'last_name', 'username', 'email', 'password', 'confirm_password', 'role', 'phone_number'];

        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $error = "Error: No fields should be empty";
                $isValid = false;
                break;
            }
        }

        if ($_POST['password'] !== $_POST['confirm_password']) {
            $error =  "Error: Passwords do not match";
            $isValid = false;
        }
            if ($isValid) {
            // Collect data from the form
            $username = $_POST['username'];
            $email = $_POST['email'];
            $firstName = $_POST['first_name'];
            $lastName = $_POST['last_name'];
            $role = $_POST['role'];
            $phoneNumber = $_POST['phone_number'];
            $password = $_POST['password'];

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Update Query
            $sql = "UPDATE users SET username = ?, email = ?, password_hash = ?, first_name = ?, last_name = ?, role = ?, phone_number = ?, updated_at = NOW() WHERE id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                // "ssssssi" means: String, String, String, String, String, String, Integer (id)
                $stmt->bind_param("sssssssi", $username, $email, $passwordHash, $firstName, $lastName, $role, $phoneNumber, $userId);
                
                if ($stmt->execute()) {
                    echo "<script>alert('Account updated successfully!'); window.location.href='account_management.php';</script>";
                    exit; // Stop further execution
                } else {
                    $error = "Error updating account: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
    // 3. FETCH EXISTING DATA (TO FILL FORM)
    // We fetch the data *after* the update logic so the form shows the new values immediately
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
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        
        <h1 class="center-text">Edit Account</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <form action="edit_account.php?id=<?php echo $userId; ?>" method="POST">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
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
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="Admin" <?php echo ($userData['role'] == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="User" <?php echo ($userData['role'] == 'User') ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" value="<?php echo htmlspecialchars($userData['phone_number']); ?>">
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Account</button>
                    <a href="account_management.php" class="btn btn-danger">Cancel</a>
                </div>
                
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>