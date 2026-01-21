<?php
// pages/view_audit_logs.php
session_start();
require_once '../config/config.php';
require_once '../utils/inventory_logic.php';

// Security Check
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    header("Location: ../index.php");
    exit();
}

// Fetch Logs
$logs = getRecentLogs($conn, 100); // Get last 100 actions

$pageTitle = 'Audit Trail';
require_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../css/style.css">

<style>
    .log-container { max-width: 1000px; margin: 30px auto; padding: 20px; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .back-btn { text-decoration: none; color: #555; font-weight: bold; display: inline-block; margin-bottom: 20px; }
    .back-btn:hover { color: #000; }
    
    .log-table { width: 100%; border-collapse: collapse; }
    .log-table th { background: #333; color: white; padding: 12px; text-align: left; }
    .log-table td { padding: 12px; border-bottom: 1px solid #eee; }
    .log-table tr:hover { background: #f9f9f9; }
</style>

<div class="log-container">
    <a href="manage_inventory.php" class="back-btn">&larr; Back to Inventory</a>
    
    <h2 style="margin-top: 0;">🕵️ System Audit Trail</h2>
    <p>Tracking the last 100 actions performed by users.</p>

    <table class="log-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Action Details</th>
                <th>Time (Date)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($logs) > 0): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="font-weight: bold; color: #007bff;">
                            <?php echo htmlspecialchars($log['username']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($log['action']); ?>
                        </td>
                        <td style="color: #666; font-size: 0.9em;">
                            <?php echo $log['timestamp']; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3" style="text-align:center;">No logs found yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>