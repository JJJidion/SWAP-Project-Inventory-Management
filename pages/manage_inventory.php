<?php
// pages/manage_inventory.php
session_start();
require_once '../config/config.php';
require_once '../utils/inventory_logic.php';

// --- SECURITY CHECKS ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    require_once '../includes/header.php';
    echo "<div class='container'><h3>⛔ Access Denied.</h3></div>";
    require_once '../includes/footer.php';
    exit();
}

// --- LOGIC: Handle Form Actions ---
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'];
        $data = [
            'id' => $_POST['part_id'] ?? null,
            'part_name' => trim($_POST['part_name'] ?? ''),
            'category' => $_POST['category'] ?? 'General',
            'supplier' => trim($_POST['supplier'] ?? ''),
            'quantity' => $_POST['quantity'] ?? 0
        ];
        
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        manageInventory($conn, $action, $data, $userId);
        $message = "Action successful!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// --- FETCH DATA ---
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);

// --- STATS CALCULATION ---
// Count items directly from the list so it matches the table
$totalItems = count($inventoryItems);
$lowStockCount = 0;
foreach ($inventoryItems as $item) {
    if ($item['stock_level'] < 10) {
        $lowStockCount++;
    }
}

$pageTitle = 'Inventory Manager';
require_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../css/style.css">

<style>
    .btn-green {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        text-decoration: none;
        font-size: 14px;
    }
    .btn-green:hover {
        background-color: #218838;
    }
</style>

<div class="container">
    <h1>Inventory Management</h1>
    
    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <div style="flex: 1; padding: 15px; background: #f4f4f4; border: 1px solid #ddd; border-radius: 5px; border-left: 5px solid #007bff;">
            <h3>Total Items</h3>
            <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo $totalItems; ?></p>
        </div>
        <div style="flex: 1; padding: 15px; background: #f4f4f4; border: 1px solid #ddd; border-radius: 5px; border-left: 5px solid #dc3545;">
            <h3>Low Stock Alerts</h3>
            <p style="font-size: 24px; font-weight: bold; margin: 0; color: #dc3545;"><?php echo $lowStockCount; ?></p>
        </div>
        <div style="flex: 1; padding: 15px; background: #f4f4f4; border: 1px solid #ddd; border-radius: 5px; border-left: 5px solid #6c757d;">
            <h3>Audit Logs</h3>
            <p><a href="view_audit_logs.php" style="text-decoration: none; color: #333; font-weight: bold;">View Full History &rarr;</a></p>
        </div>
    </div>

    <?php if ($message): ?>
        <p style="padding: 10px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <hr>
    <h3>Add New Item</h3>
    <form method="POST" action="" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px; border-radius: 5px;">
        <input type="hidden" name="action" value="add">
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <div style="flex: 2;">
                <label>Part Name:</label><br>
                <input type="text" name="part_name" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="flex: 1;">
                <label>Category:</label><br>
                <select name="category" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option>General</option>
                    <option>Electronics</option>
                    <option>Hardware</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label>Supplier:</label><br>
                <input type="text" name="supplier" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="flex: 1;">
                <label>Stock:</label><br>
                <input type="number" name="quantity" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn-green">Add Item</button>
            </div>
        </div>
    </form>

    <h3>Current Stock</h3>
    <form method="GET" style="margin-bottom: 10px; display: flex; gap: 5px;">
        <input type="text" name="search" placeholder="Search part name..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        <button type="submit" class="btn-green">Search</button>
        <a href="manage_inventory.php?export=true" class="btn-green" style="margin-left: 10px;">Export CSV</a>
    </form>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <tr style="background: #333; color: #333;">
            <th>No.</th>
            <th>Category</th>
            <th>Part Name</th>
            <th>Supplier</th>
            <th>Stock</th>
            <th>Actions</th>
        </tr>
        <?php if (count($inventoryItems) > 0): ?>
            <?php 
                // Initialize Counter
                $rowNumber = 1; 
            ?>
            <?php foreach ($inventoryItems as $item): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td><?php echo $rowNumber++; ?></td>
                    
                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                    <td><b><?php echo htmlspecialchars($item['part_name']); ?></b></td>
                    <td><?php echo htmlspecialchars($item['supplier']); ?></td>
                    <td>
                        <?php echo $item['stock_level']; ?>
                        <?php if($item['stock_level'] < 10) echo " <span style='color:red; font-weight:bold;'>(Low)</span>"; ?>
                    </td>
                    <td>
                        <button onclick="alert('To edit, please delete and re-add.');" style="cursor: pointer; padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px;">Edit</button>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="part_id" value="<?php echo $item['id']; ?>">
                            <button style="cursor: pointer; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align: center;">No items found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>