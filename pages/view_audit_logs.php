<?php
// pages/view_audit_logs.php
session_start();
require_once '../config/config.php';
require_once '../utils/inventory_logic.php';

// --- ACCESS CONTROL ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    require_once '../includes/header.php';
    echo "<div class='container'><h3>⛔ Access Denied.</h3></div>";
    require_once '../includes/footer.php';
    exit();
}

// Fetch the last 50 logs (instead of just 10)
$logs = getRecentLogs($conn, 50);

$pageTitle = 'Audit Logs';
require_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../css/style.css">

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>🕵️ System Audit Logs</h1>
        <a href="manage_inventory.php" class="btn" style="background-color: #6c757d; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px;">&larr; Back to Inventory</a>
    </div>

    <p>Viewing the last <strong>50</strong> system actions.</p>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <tr style="background: #333; color: white;">
            <th>User</th>
            <th>Action</th>
            <th>Time</th>
        </tr>
        <?php if (count($logs) > 0): ?>
            <?php foreach ($logs as $log): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="font-weight: bold;"><?php echo htmlspecialchars($log['username']); ?></td>
                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                    <td><?php echo $log['timestamp']; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="3" style="text-align: center;">No logs found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>