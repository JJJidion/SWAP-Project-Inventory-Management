<?php
/**
 * Account Management Page
 *
 * Page allowing inventory managers to view user accounts, with buttons redirecting to creating, updating and deleting accounts.
 */

session_start();
require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Account Management';

if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}
?>

<?php
    // Define an array to hold the books
    $acc_info = [];

    $sql = "SELECT id, email, username, role, first_name, last_name, phone_number, created_at, updated_at FROM users";
        
        // No bind_param needed because we are selecting everything
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            // Loop through all results and add to array
            while ($row = $result->fetch_assoc()) {
                $acc_info[] = $row;
            }
        } else {
            $message = "Error fetching accounts: " . $conn->error;
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
        <h1>Account Management</h1>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone Number</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                    <th>Actions</th> </tr>
            </thead>

            <tbody>
                <?php foreach ($acc_info as $acc): ?>
                <tr>
                    <td><?php echo htmlspecialchars($acc["first_name"] . " " . $acc["last_name"]); ?></td>
                    <td><?php echo htmlspecialchars($acc["username"]); ?></td>
                    
                    <td>
                        <?php 
                        // check the role and change display text
                        if ($acc["role"] === 'User') {
                            echo 'Warehouse Staff';
                        } elseif ($acc["role"] === 'Admin') {
                            echo 'Inventory Manager';
                        } else {
                            // If it's something else, just show it as is
                            echo htmlspecialchars($acc["role"]);
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($acc["email"]); ?></td>
                    <td><?php echo htmlspecialchars($acc["phone_number"]); ?></td>
                    <td><?php echo htmlspecialchars($acc["created_at"]); ?></td>
                    <td><?php echo htmlspecialchars($acc["updated_at"]); ?></td>
                    <td>
                        <a href="edit_account.php?id=<?php echo $acc["id"]; ?>">Edit</a>
                        <a href="delete_account.php?id=<?php echo $acc["id"]; ?>">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            
        </table>
        <br>
        <button type="button" class="btn btn-primary" onclick="window.location.href='create_account.php';">Create Account</button>
    </div>
</body>
</html>