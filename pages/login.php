<?php
/**
 * Login Page
 *
 * Allows users to login.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . "/../utils/utility.php";

$formSubmitted = false;
$loginSuccess = false;
$errorMessage = "";
$userData = null;
$pageTitle = 'Login';

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME_MINUTES', 1);

// --- RATE LIMIT CHECK START ---
$ip_address = $_SERVER['REMOTE_ADDR'];
$lockout_threshold = date('Y-m-d H:i:s', time() - (60 * LOCKOUT_TIME_MINUTES));

// Count failed attempts from this IP in the last X minutes
$stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > ?");
$stmt->bind_param("ss", $ip_address, $lockout_threshold);
$stmt->execute();
$result = $stmt->get_result();
$countRow = $result->fetch_array();
$failed_attempts = $countRow[0];
$stmt->close();

if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
    $errorMessage = "Too many failed attempts. Please try again in " . LOCKOUT_TIME_MINUTES . " minutes.";
    
    // ADD THIS LINE:
    $formSubmitted = true; // Force the HTML to display the error alert
    
    // Skip the rest of the login logic and go straight to HTML
    goto render;
}
// --- RATE LIMIT CHECK END ---

// Start session to check if user is already logged in
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION["username"])):
    if ($_SESSION["role"] === "User"):
        header("Location: index_user.php");
        exit;
    elseif ($_SESSION["role"] === "Admin"):
        header("Location: index_admin.php");
        exit;
    endif;
endif;


// If form not submitted, skip to HTML (show form)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    goto render;
}

// Form was submitted
$formSubmitted = true;

// Validate all form inputs
$checkAll = true;
$checkAll = $checkAll && checkPost("username", true, "");
$checkAll = $checkAll && checkPost("password", true, "");

// If validation fails, set error and skip to HTML
// If validation fails, set error and skip

if (!$checkAll) {
    $errorMessage = "Please enter both username and password.";
} else {
    // 1. Prepare the query
    $query = "SELECT id, email, username, password_hash, role, first_name, last_name, phone_number, created_at, updated_at FROM users WHERE username = ?";
    
    if ($stmt = $conn->prepare($query)) {
        
        // 2. BIND PARAMETER
        // "s" means string
        $stmt->bind_param("s", $_POST['username']); 

        // 3. Execute
        if ($stmt->execute()) {
            $result = $stmt->get_result();

            // 4. Check if we found exactly one user
            if ($result->num_rows === 1) {
                
                // 5. Fetch that single user data directly
                $userData = $result->fetch_assoc();

                // 6. Verify password
                if (password_verify($_POST['password'], $userData['password_hash'])) {
                    // --- SUCCESS: RESET ATTEMPTS ---
                    // If login is successful, delete failed attempts for this IP so they don't get locked out later
                    $resetStmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                    $resetStmt->bind_param("s", $ip_address);
                    $resetStmt->execute();
                    $resetStmt->close();
                    
                    // Set Session Variables
                    $_SESSION["username"] = $userData["username"];
                    $_SESSION["role"] = $userData["role"];
                    $_SESSION["user_id"] = $userData["id"];
                    $_SESSION["first_name"] = $userData["first_name"];
                    $loginSuccess = true;

                } else {
                    // --- FAILURE: LOG ATTEMPT ---
                    $errorMessage = "Invalid username or password.";
                    
                    $logStmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
                    $logStmt->bind_param("s", $ip_address);
                    $logStmt->execute();
                    $logStmt->close();
                }

            } else {
                // --- FAILURE (User not found): LOG ATTEMPT ---
                // We log this too, to prevent username enumeration/brute force
                $errorMessage = "Invalid username or password.";
                
                $logStmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
                $logStmt->bind_param("s", $ip_address);
                $logStmt->execute();
                $logStmt->close();
            }

        } else {
            $errorMessage = "Execution failed: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $errorMessage = "Database prepare error: " . $conn->error;
    }
}

// Render HTML
// Render HTML
render:
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container login-wrapper">

        <div class="card login-card">
            
            <?php if ($formSubmitted && $loginSuccess): ?>
                
                <div class="alert alert-success">
                    <h2 style="margin-bottom: 10px; color: inherit;">Login Successful!</h2>
                    <p><strong>Welcome:</strong> <?php echo htmlspecialchars($userData['first_name']); ?></p>
                </div>
                
                <div class="links">
                    <?php if ($_SESSION["role"] === "User"): ?>
                        <a href="index_user.php" class="btn btn-primary btn-block">Continue to Dashboard</a>
                    <?php elseif ($_SESSION["role"] === "Admin"): ?>
                        <a href="index_admin.php" class="btn btn-primary btn-block">Continue to Dashboard</a>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <h2>Sign In</h2>

                <?php if ($formSubmitted && !$loginSuccess): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                    
                </form>

            <?php endif; ?>
            
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>