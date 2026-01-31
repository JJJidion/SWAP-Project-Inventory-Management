<?php
/**
 * Update Profile Page
 *
 * This script handles the modification of user account details.
 * It includes robust Role-Based Access Control (RBAC) to ensure:
 * 1. Administrators can edit any account.
 * 2. Standard Users can ONLY edit their own account.
 * 3. Role elevation is restricted to Administrators only.
 */

session_start();

// 1. Configuration & Imports
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Update Profile';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

// 2. Authentication Check
// Verify the user is logged in before allowing access.
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit;
}

// 3. Input Validation
// Ensure a Target User ID is present in the URL.
if (!isset($_GET['id'])) {
    die("Error: User ID not specified.");
}

$requestedUserId = $_GET['id'];
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'];
$error = '';

// 4. Authorization & Access Control
// Critical Security Check:
// - Allow access if the user is an Admin.
// - Allow access if the user is editing their OWN profile ($requestedUserId == $currentUserId).
// - Deny access for everything else.
if ($currentUserRole !== 'Admin' && $requestedUserId != $currentUserId) {
    error_log("Security Alert: User $currentUserId tried to access profile $requestedUserId");
    die("Error: You are not authorized to edit this profile.");
}

// --- 5. HANDLE FORM SUBMISSION (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName       = trim($_POST['first_name']);
    $lastName        = trim($_POST['last_name']);
    $username        = trim($_POST['username']);
    $email           = trim($_POST['email']);
    $phoneNumber     = trim($_POST['phone_number']);
    
    // SECURITY: Role Tampering Prevention
    // Logic: Only Admins can submit a 'role' change via POST.
    // If a non-admin tries to change their role, we ignore the POST data
    // and fetch their existing role from the database instead.
    $roleToSave = '';
    
    if ($currentUserRole === 'Admin') {
        $roleToSave = $_POST['role'];
    } else {
        // Fetch the existing immutable role from the database
        $roleQuery = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $roleQuery->bind_param("i", $requestedUserId);
        $roleQuery->execute();
        $roleResult = $roleQuery->get_result();
        $roleRow = $roleResult->fetch_assoc();
        $roleToSave = $roleRow['role'];
        $roleQuery->close();
    }

    $isValid = true;

    // A. Required Fields Validation
    if (empty($_POST['first_name']) || empty($_POST['last_name']) || 
        empty($_POST['username']) || empty($_POST['email']) || 
        empty($_POST['phone_number'])) {
        
        $error = "Error: Basic profile fields cannot be empty";
        $isValid = false;
    }

    // B. Password Logic (Optional Update)
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $updatePassword = false;

    // Only validate password rules if the user actually typed something
    if (!empty($password)) {
        $updatePassword = true;

        if ($password !== $confirmPassword) {
            $error = "Error: Passwords do not match";
            $isValid = false;
        } 
        // Server-Side Complexity Enforcement (Length check)
        elseif (strlen($password) < 10) {
            $error = "Error: Password must be at least 10 characters long.";
            $isValid = false;
        } 
        // Server-Side Complexity Enforcement (Regex check)
        elseif (!preg_match("/[A-Z]/", $password) || 
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
        // Only necessary if the current user is an Admin
        if ($currentUserRole === 'Admin') {
            $allowedRoles = ['Admin', 'User'];
            if (!in_array($roleToSave, $allowedRoles)) {
                $error = "Error: Invalid role selected.";
                $isValid = false;
            }
        }

        // Validate Phone (Strict 8-digit requirement)
        if (!preg_match("/^[0-9]{8}$/", $phoneNumber)) {
            $error = "Error: Phone number must be exactly 8 digits.";
            $isValid = false;
        }
    }

    // C. Database Update Execution
    if ($isValid) {

        // Logic Split: Determine if we are updating the password or not.
        if ($updatePassword) {
            // Path 1: Update EVERYTHING including Password (Hashing required)
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $query = $conn->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, first_name = ?, last_name = ?, role = ?, phone_number = ?, updated_at = NOW() WHERE id = ?");
            $query->bind_param('sssssssi', $username, $email, $passwordHash, $firstName, $lastName, $roleToSave, $phoneNumber, $requestedUserId);
        } else {
            // Path 2: Update ONLY profile info (Keep old password intact)
            $query = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role = ?, phone_number = ?, updated_at = NOW() WHERE id = ?");
            $query->bind_param('ssssssi', $username, $email, $firstName, $lastName, $roleToSave, $phoneNumber, $requestedUserId);
        }

        if ($query->execute()) {
            // Success: Redirect based on role
            // Admins go back to management list; Users go back to their profile view.
            $redirect = ($currentUserRole === 'Admin') ? 'account_management.php' : 'profile.php';
            echo "<script>alert('Profile successfully updated!'); window.location.href='$redirect';</script>";
            exit; 
        } else {
            $error = "Error executing query: " . $conn->error;
        }
    }

}

// --- 6. FETCH EXISTING DATA (GET Request) ---
// Populates the form fields with the current data from the database.
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validator/13.11.0/validator.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container">
        
        <div style="margin-top: 2rem; margin-bottom: 1rem;">
            <h1 style="text-align: center; color: #333;"><?php echo htmlspecialchars($pageTitle); ?></h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($userData): ?>
        
        <div class="card">
            <form method="POST" class="form" id="updateProfileForm">

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
                        Leave these fields blank if you want to keep your current password.
                    </p>

                    <div class="form-group">
                        <label for="password">New Password:</label>
                        <input type="password" id="password" name="password" placeholder="Enter new password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                    </div>
                </div>

                <?php if ($currentUserRole === 'Admin'): ?>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; background-color: white;">
                        <option value="Admin" <?php echo ($userData['role'] == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="User" <?php echo ($userData['role'] == 'User') ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" value="<?php echo htmlspecialchars($userData['phone_number']); ?>">
                </div>

                <div style="margin-top: 2rem; display:flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                    <?php 
                        // Determine where to send the user if they click Cancel
                        $cancelLink = ($currentUserRole === 'Admin') ? 'account_management.php' : 'profile.php';
                    ?>
                    <a href="<?php echo $cancelLink; ?>" class="btn btn-danger">Cancel</a>
                </div>
                
            </form>
        </div>

        <?php else: ?>
            <p>User data could not be loaded.</p>
        <?php endif; ?>
    </div>

    <script>
        // Client-Side Validation
        document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Logic: Only validate IF the user has typed a password
            if (password.length > 0) {
                
                const rules = {
                    minLength: 10,
                    minLowercase: 1,
                    minUppercase: 1,
                    minNumbers: 1,
                    minSymbols: 1
                };

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