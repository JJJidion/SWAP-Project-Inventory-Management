<?php
/**
 * Profile Page
 *
 * Page allows users to view and update their profile information.
 */

session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit;
}
$pageTitle = 'Profile Page';

$username = $_SESSION["username"];
$sql = "SELECT id, first_name, last_name, username, email, role, phone_number FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
if ($stmt->execute()) {
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $id = $row["id"];
        $firstName = $row["first_name"];
        $lastName = $row["last_name"];
        $username = $row["username"];
        $email = $row["email"];
        $role = $row["role"];
        $phoneNumber = $row["phone_number"];
    } else {
        echo "No book found with that ID.";
    }
} else {
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