<?php
/**
 * Profile Page
 *
 * This page displays the currently logged-in user's account details.
 * It retrieves data securely from the database using the session username.
 */

session_start();

// 1. Configuration & Imports
require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

// 2. Authentication Check
// Verify the user is logged in. If not, redirect to login page.
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Profile Page';
$username = $_SESSION["username"];

// 3. Data Retrieval
// We use a Prepared Statement here to prevent SQL Injection.
$sql = "SELECT id, first_name, last_name, username, email, role, phone_number FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);

if ($stmt->execute()) {
    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Assign variables for display in HTML
        $id = $row["id"];
        $firstName = $row["first_name"];
        $lastName = $row["last_name"];
        $username = $row["username"];
        $email = $row["email"];
        $role = $row["role"];
        $phoneNumber = $row["phone_number"];
    } else {
        // Fallback if user is not found in DB
        echo "No book found with that ID.";
    }
} else {
    // Database execution error
    echo "Error searching: " . $conn->error;
}

$stmt->close();
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
        <h1>Profile</h1>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone Number</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($firstName . " " . $lastName); ?></td>
                    <td><?php echo htmlspecialchars($username); ?></td>
                    <td><?php echo htmlspecialchars($role); ?></td>
                    <td><?php echo htmlspecialchars($email); ?></td>
                    <td><?php echo htmlspecialchars($phoneNumber); ?></td>
                </tr>
            </tbody>
            
        </table>

        <button type="button" class="btn btn-primary" onclick="window.location.href='update_profile.php?id=<?php echo $id; ?>'">Update Profile</button>
    </div>
</body>
</html>