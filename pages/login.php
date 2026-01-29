<?php
/**
 * Login Page
 *
 * Handles user authentication, rate limiting, and session management.
 */

// 1. Initialization & Configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . "/../utils/utility.php";

// Start the session immediately. Best practice to have this near the top.
session_start();

// Constants for security configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME_MINUTES', 15);

// Initialize variables to avoid "Undefined variable" warnings
$error = '';
$success = false;
$isLockedOut = false;
$userData = null;

// 2. Auth Check: Redirect if already logged in
if (isset($_SESSION["username"]) && isset($_SESSION["role"])) {
    $redirectPage = ($_SESSION["role"] === "Admin") ? "index_admin.php" : "index_user.php";
    header("Location: " . $redirectPage);
    exit;
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ip_address = $_SERVER['REMOTE_ADDR'];

    // --- SECURITY: Rate Limiting (Brute Force Protection) ---
    // Check if this IP has failed too many times recently
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
    $lockout_time = LOCKOUT_TIME_MINUTES; 
    $checkStmt->bind_param("si", $ip_address, $lockout_time);
    $checkStmt->execute();
    $checkStmt->bind_result($failed_attempts);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
        $error = "Too many failed attempts. Please try again in " . LOCKOUT_TIME_MINUTES . " minutes.";
        $isLockedOut = true;
    } else {
        // --- SECURITY: Input Validation ---
        // Using your utility function to check if fields exist and are not empty
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            // --- SECURITY: Authentication ---
            // Fetch the user. We explicitly select only needed fields.
            $authStmt = $conn->prepare("SELECT id, username, password_hash, role, first_name FROM users WHERE username = ?");
            
            if ($authStmt) {
                $authStmt->bind_param("s", $username);
                $authStmt->execute();
                $result = $authStmt->get_result();

                if ($result->num_rows === 1) {
                    $userData = $result->fetch_assoc();

                    // Verify Password
                    if (password_verify($password, $userData['password_hash'])) {
                        
                        // --- SECURITY: Session Fixation Protection ---
                        // Critical: Regenerate ID to prevent session hijacking attacks
                        session_regenerate_id(true);

                        // Set Session Data
                        $_SESSION["user_id"]    = $userData["id"];
                        $_SESSION["username"]   = $userData["username"];
                        $_SESSION["role"]       = $userData["role"];
                        $_SESSION["first_name"] = $userData["first_name"];
                        
                        // Clear failed attempts on successful login
                        $clearStmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                        $clearStmt->bind_param("s", $ip_address);
                        $clearStmt->execute();
                        $clearStmt->close();

                        $success = true;

                    } else {
                        // Password Incorrect
                        $error = "Invalid username or password."; // Generic error message
                        logFailedAttempt($conn, $ip_address);
                    }
                } else {
                    // User Not Found
                    $error = "Invalid username or password."; // Generic error message
                    logFailedAttempt($conn, $ip_address);
                }
                $authStmt->close();
            } else {
                // Database Error (Do not show specific SQL errors to user)
                error_log("Database Prepare Error: " . $conn->error); // Log it instead
                $error = "An internal error occurred. Please try again later.";
            }
        }
    }
}

/**
 * Helper function to log failed login attempts.
 * Keeps the main logic clean.
 */
function logFailedAttempt($conn, $ip) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo defined('BASE_URL') ? BASE_URL : '..'; ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container login-wrapper">
        <div class="card login-card">
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h2>Login Successful!</h2>
                    <p><strong>Welcome, <?php echo htmlspecialchars($userData['first_name']); ?>!</strong></p>
                </div>
                
                <div class="links">
                    <?php 
                        $dashboardLink = ($_SESSION["role"] === "Admin") ? "index_admin.php" : "index_user.php";
                    ?>
                    <a href="<?php echo $dashboardLink; ?>" class="btn btn-primary btn-block">Continue to Dashboard</a>
                </div>

            <?php else: ?>
                <h2>Sign In</h2>
                
                <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 'true'): ?>
                    <div class="alert alert-warning" style="background-color: #fff3cd; color: #856404; border-color: #ffeeba;">
                        Your session has expired due to inactivity. Please log in again.
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php" <?php echo $isLockedOut ? 'style="opacity:0.5; pointer-events:none;"' : ''; ?>>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" 
                               placeholder="Enter your username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               required <?php echo $isLockedOut ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" 
                               required <?php echo $isLockedOut ? 'disabled' : ''; ?>>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" <?php echo $isLockedOut ? 'disabled' : ''; ?>>
                        Sign In
                    </button>
                    
                </form>
            <?php endif; ?>
            
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>