<?php
// pages/user_inventory.php
session_start();
require_once '../config/config.php';
require_once '../utils/inventory_logic.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

// --- SECURITY HEADERS ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// --- SECURITY: ACCESS CONTROL ---
// Allow User, Admin, and Manager to VIEW this page
$allowedRoles = ['User', 'Admin', 'Inventory Manager'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
    require_once '../includes/header.php';
    echo "<div class='container'><h3>⛔ Access Denied.</h3></div>";
    require_once '../includes/footer.php';
    exit();
}

// --- FETCH DATA ---
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);

// --- DASHBOARD STATS ---
$totalItems = count($inventoryItems);
$lowStockCount = 0;
foreach ($inventoryItems as $item) {
    if ($item['stock_level'] < 10) $lowStockCount++;
}

$pageTitle = 'Inventory List';
require_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../css/style.css">

<div class="container">
    <h1>📦 Inventory List (Read Only)</h1>
    <p>Current stock levels. Contact a Manager to make changes.</p>

    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <div style="padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; flex: 1;">
            <strong>Total Items:</strong> <?php echo $totalItems; ?>
        </div>
        <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 5px; flex: 1; color: #856404;">
            <strong>Low Stock Alerts:</strong> <?php echo $lowStockCount; ?>
        </div>
    </div>

    <form method="GET" style="margin-bottom: 15px; display:flex; gap:5px;">
        <input type="text" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="padding: 6px; border: 1px solid #ccc; width: 300px;">
        <button type="submit" class="btn" style="background-color: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">Search</button>
    </form>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background: #e9ecef; color: black; font-weight: bold;">
            <th style="padding: 10px; border: 1px solid #dee2e6;">No.</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Category</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Part Name</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Supplier</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Value ($)</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Stock</th>
        </tr>
        <?php if (count($inventoryItems) > 0): ?>
            <?php $rowNum = 1; ?>
            <?php foreach ($inventoryItems as $item): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td><?php echo $rowNum++; ?></td>
                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                    <td><b><?php echo htmlspecialchars($item['part_name']); ?></b></td>
                    <td><?php echo htmlspecialchars($item['supplier']); ?></td>
                    <td>$<?php echo isset($item['price']) ? number_format($item['price'], 2) : '0.00'; ?></td>
                    <td>
                        <?php echo $item['stock_level']; ?>
                        <?php if($item['stock_level'] < 10) echo " <span style='color:red; font-weight:bold;'>(Low)</span>"; ?>
                    </td>
                    </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No items found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>