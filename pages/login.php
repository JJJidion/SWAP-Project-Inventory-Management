<?php
/**
 * Login Page
 *
 * Allows users to login.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . "/../utils/utility.php";
require_once __DIR__ . '/../audit/audit_logger.php';

$formSubmitted = false;
$loginSuccess = false;
$errorMessage = "";
$userData = null;
$pageTitle = 'Login';

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME_MINUTES', 1);

// --- RATE LIMIT CHECK START ---
// --- RATE LIMIT CHECK START ---
$ip_address = $_SERVER['REMOTE_ADDR'];
$lockout_minutes = LOCKOUT_TIME_MINUTES; // Assign constant to variable for binding

// Count failed attempts using ONLY MySQL's clock
// We check if attempt_time > (NOW - X minutes)
$stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)");

// Bind the parameters: "s" for string (IP), "i" for integer (minutes)
$stmt->bind_param("si", $ip_address, $lockout_minutes);

$stmt->execute();
$result = $stmt->get_result();
$countRow = $result->fetch_array();
$failed_attempts = $countRow[0];
$stmt->close();

if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {

    // Try to resolve role from username (if exists)
    $attemptedUser = $_POST['username'] ?? 'UNKNOWN';
    $attemptedRole = 'UNKNOWN';
    $attemptedUserId = 0;

    if ($attemptedUser !== 'UNKNOWN') {
        $roleStmt = $conn->prepare(
            "SELECT id, role FROM users WHERE username = ? LIMIT 1"
        );
        $roleStmt->bind_param("s", $attemptedUser);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();

        if ($row = $roleResult->fetch_assoc()) {
            $attemptedUserId = (int)$row['id'];
            $attemptedRole = $row['role'];
        }

        $roleStmt->close();
    }

    audit_log(
        $conn,
        $attemptedUserId,
        $attemptedUser,
        $attemptedRole,
        'ACCOUNT_LOCKED',
        'auth',
        null,
        'Login blocked due to too many failed attempts'
    );

    $errorMessage = "Too many failed attempts. Please try again in " . LOCKOUT_TIME_MINUTES . " minutes.";
    $formSubmitted = true;
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
                
audit_log(
$conn,
$userData['id'],
$userData['username'],
$userData['role'],
'LOGIN_SUCCESS',
'auth',
null,
'User logged in successfully'
);

                } else {
                    // --- FAILURE: LOG ATTEMPT ---
                    $errorMessage = "Invalid username or password.";
audit_log(
$conn,
0,
$_POST['username'],
'UNKNOWN',
'LOGIN_FAILED',
'auth',
null,
'Invalid password'
);
                    $logStmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
                    $logStmt->bind_param("s", $ip_address);
                    $logStmt->execute();
                    $logStmt->close();
                }

            } else {
                // --- FAILURE (User not found): LOG ATTEMPT ---
                // We log this too, to prevent username enumeration/brute force
                $errorMessage = "Invalid username or password.";
audit_log(
    $conn,
    0,
    $_POST['username'],
    'UNKNOWN',
    'LOGIN_FAILED',
    'auth',
    null,
    'Username not found'
);
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