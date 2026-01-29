<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../audit/audit_logger.php';
// 1. Security Check: This exit is FINE because we want to stop here if they aren't logged in.
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit;
}
//AUDIT LOG — MUST HAPPEN BEFORE session_destroy
audit_log(
$conn,
$_SESSION['user_id'] ?? 0,
$_SESSION['username'] ?? 'UNKNOWN',
$_SESSION['role'] ?? 'UNKNOWN',
'LOGOUT',
'auth',
null,
'User logged out'
);
// 2. Clear All Cookies (Server-Side) & Build JS String
// We need to rebuild the $deleteCookie string so the JS below doesn't crash
$deleteCookie = ""; 
foreach ($_COOKIE as $key => $value) {
    setcookie($key, "", time() - 1 * 60 * 60, "/");
    // Re-added this line so the JS below has something to echo
    $deleteCookie .= "$key=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; ";
}

// 3. Clear Session Variables
session_unset();

// 4. Destroy the Session
session_destroy();

// REMOVED: header("Location: /login.php");
// REMOVED: exit;
// We deleted these so the script continues to the HTML below
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout</title>
</head>
<body>
    <script>
    // 1. Clear client-side cookies
    document.cookie = "<?php echo $deleteCookie; ?>";

    // 2. Trigger the alert
    alert("✅ You have been logged out successfully!");

    // 3. Redirect using JavaScript (Use this INSTEAD of the PHP header)
    window.location.href = "login.php";
    </script>
</body>
</html>