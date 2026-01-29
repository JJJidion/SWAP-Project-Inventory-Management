<?php
/**
 * Account Management Page
 *
 * Displays a dashboard of all user accounts.
 * Allows Administrators to View, Edit, and Delete users.
 * Includes logic to highlight the current admin and prevent self-deletion.
 */

session_start();

// 1. Configuration & Imports
require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

$pageTitle = 'Account Management';
$errorMessage = '';
$acc_info = [];

// 2. Security Check (Role-Based Access Control)
// Verify the user is logged in and has 'Admin' privileges.
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    // Optional: Log unauthorized access attempts here
    header("Location: login.php");
    exit;
}

// 3. Identify Current User
// We need the current user's ID to visually highlight them and hide their delete button.
$currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// 4. Data Retrieval
// Fetch user data. We select specific columns rather than `*` for better performance and security.
$sql = "SELECT id, email, username, role, first_name, last_name, phone_number, created_at, updated_at FROM users";

if ($stmt = $conn->prepare($sql)) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        // Fetch all rows into an array to be iterated over in the HTML view
        while ($row = $result->fetch_assoc()) {
            $acc_info[] = $row;
        }
    } else {
        // Log the actual error for the developer, show a generic one to the user
        error_log("Database Execute Error: " . $stmt->error);
        $errorMessage = "Error fetching account data.";
    }
    $stmt->close();
} else {
    error_log("Database Prepare Error: " . $conn->error);
    $errorMessage = "Internal system error.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <style>
        /* Highlight the logged-in user's row for better UX */
        .current-user-row {
            background-color: #e3f2fd; /* Light Blue */
            font-weight: 500;
            border-left: 4px solid #2196F3;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>Account Management</h1>
            <button type="button" class="btn btn-primary" onclick="window.location.href='create_account.php';">
                + Create New Account
            </button>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
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
                        <th>Actions</th> 
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($acc_info)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No accounts found.</td>
                        </tr>
                    <?php else: ?>
                        
                        <?php foreach ($acc_info as $acc): ?>
                            <?php 
                                // Logic: Check if this row belongs to the logged-in Admin
                                $isCurrentUser = ($acc['id'] == $currentUserId) || ($acc['username'] === $_SESSION['username']);
                            ?>

                            <tr class="<?php echo $isCurrentUser ? 'current-user-row' : ''; ?>">
                                <td>
                                    <?php echo htmlspecialchars($acc["first_name"] . " " . $acc["last_name"]); ?>
                                    
                                    <?php if ($isCurrentUser): ?>
                                        <span class="badge badge-info" style="margin-left:5px; font-size:0.8em; color: #0056b3;">(You)</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td><?php echo htmlspecialchars($acc["username"]); ?></td>
                                
                                <td>
                                    <?php 
                                        // Display friendly role names
                                        if ($acc["role"] === 'User') {
                                            echo 'Warehouse Staff';
                                        } elseif ($acc["role"] === 'Admin') {
                                            echo 'Inventory Manager';
                                        } else {
                                            echo htmlspecialchars($acc["role"]);
                                        }
                                    ?>
                                </td>
                                
                                <td><?php echo htmlspecialchars($acc["email"]); ?></td>
                                <td><?php echo htmlspecialchars($acc["phone_number"]); ?></td>
                                <td><?php echo htmlspecialchars($acc["created_at"]); ?></td>
                                <td><?php echo htmlspecialchars($acc["updated_at"]); ?></td>
                                
                                <td>
                                    <a href="edit_account.php?id=<?php echo $acc["id"]; ?>" class="btn-link">Edit</a>
                                    
                                    <?php if (!$isCurrentUser): ?>
                                    <!--check if not current user before deleting-->
                                        | <a href="delete_account.php?id=<?php echo $acc["id"]; ?>" 
                                             class="btn-link text-danger" 
                                             onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">Delete</a>
                                    <?php else: ?>
                                        <span style="color:#ccc; cursor:not-allowed;" title="You cannot delete yourself">| Delete</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>