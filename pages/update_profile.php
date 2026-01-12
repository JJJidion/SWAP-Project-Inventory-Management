<?php
/**
 * Update Profile Page
 */
session_start();
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Update Profile';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit;
}

// Ensure ID is provided in URL
if (!isset($_GET['id'])) {
    die("Error: User ID not specified.");
}

$requestedUserId = $_GET['id'];
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'];
$error = '';

if ($currentUserRole !== 'Admin' && $requestedUserId != $currentUserId) {
    // Log the attempt (optional)
    error_log("Security Alert: User $currentUserId tried to access profile $requestedUserId");
    
    // Stop execution and show error
    die("Error: You are not authorized to edit this profile.");
    
    // Alternatively, redirect them to their own profile:
    // header("Location: update_profile.php?id=" . $currentUserId);
    // exit;
}

// --- 1. HANDLE FORM SUBMISSION (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isValid = true;

    // Check for empty fields
    if (empty($_POST['first_name']) || empty($_POST['last_name']) || 
        empty($_POST['username']) || empty($_POST['email']) || 
        empty($_POST['password']) || empty($_POST['confirm_password']) || 
        empty($_POST['role']) || empty($_POST['phone_number'])) {
        
        $error = "Error: No fields should be empty";
        $isValid = false;
    }

    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Error: Passwords do not match";
        $isValid = false;
    }

    if ($isValid) {
        $firstName   = $_POST['first_name'];
        $lastName    = $_POST['last_name'];
        $username    = $_POST['username'];
        $email       = $_POST['email'];
        $password    = $_POST['password'];
        $role        = $_POST['role'];
        $phoneNumber = $_POST['phone_number'];

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Prepare Update
        $query = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role = ?, phone_number = ?, updated_at = NOW() WHERE id = ?");
        $query->bind_param('sssssss', $username, $email, $firstName, $lastName, $role, $phoneNumber, $requestedUserId);

        if ($query->execute()) {
            echo "<script>alert('Profile successfully updated!'); window.location.href='profile.php';</script>";
            exit; 
        } else {
            $error = "Error executing query: " . $conn->error;
        }
    }
}

// --- 2. FETCH USER DATA (GET) ---
// This now runs unconditionally (unless script exited above), 
// ensuring $userData exists when the form loads.

$sql = "SELECT id, username, email, first_name, last_name, role, phone_number FROM users WHERE id = ?";
$userData = null;

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $requestedUserId);
    
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
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($userData): ?>
        <form method="POST" class="form">

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

            <div class="form-group">
                <label for="password">Password (Enter new to change):</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
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
                <button type="submit" class="btn btn-primary">Update Profile</button>
                <a href="account_management.php" class="btn btn-danger">Cancel</a>
            </div>
            
        </form>
        <?php else: ?>
            <p>User data could not be loaded.</p>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>