<?php
// pages/user_inventory.php
session_start();
require_once '../config/config.php';
require_once '../utils/inventory_logic.php';

// --- SECURITY: ACCESS CONTROL ---
$allowedRoles = ['User', 'Admin', 'Inventory Manager'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
    require_once '../includes/header.php';
    echo "<div class='container'><h3>⛔ Access Denied.</h3></div>";
    require_once '../includes/footer.php';
    exit();
}

// --- HANDLE UPDATES ONLY ---
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'update') {
            $data = [
                'id' => $_POST['part_id'],
                'part_name' => trim($_POST['part_name']),
                'category' => $_POST['category'],
                'supplier' => trim($_POST['supplier']),
                'quantity' => $_POST['quantity']
            ];
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
            manageInventory($conn, 'update', $data, $userId);
            $message = "Item updated successfully!";
        } else {
            $message = "Error: Unauthorized action.";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// --- FETCH DATA ---
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);

// Stats
$totalItems = count($inventoryItems);
$lowStockCount = 0;
foreach ($inventoryItems as $item) {
    if ($item['stock_level'] < 10) $lowStockCount++;
}

$pageTitle = 'Inventory List';
require_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../css/style.css">

<style>
    .btn-blue { background-color: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; text-decoration: none; }
    .btn-green { background-color: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
    .btn-grey { background-color: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
    
    #editFormContainer {
        display: none; 
        background: #eef5fa; 
        border: 1px solid #cce5ff; 
        padding: 20px; 
        margin-bottom: 20px; 
        border-radius: 5px;
    }
</style>

<div class="container">
    <h1>📦 Inventory List</h1>
    <p>View stock levels and update item details.</p>

    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <div style="padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; flex: 1;">
            <strong>Total Items:</strong> <?php echo $totalItems; ?>
        </div>
        <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 5px; flex: 1; color: #856404;">
            <strong>Low Stock Alerts:</strong> <?php echo $lowStockCount; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <p style="padding: 10px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <div id="editFormContainer">
        <h3 style="margin-top:0;">✏️ Update Item Details</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="part_id" id="inputID">
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 2;">
                    <label>Part Name:</label><br>
                    <input type="text" name="part_name" id="inputName" required style="width:100%; padding:5px;">
                </div>
                <div style="flex: 1;">
                    <label>Category:</label><br>
                    <select name="category" id="inputCategory" style="width:100%; padding:5px;">
                        <option value="General">General</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Hardware">Hardware</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Supplier:</label><br>
                    <input type="text" name="supplier" id="inputSupplier" style="width:100%; padding:5px;">
                </div>
                <div style="flex: 1;">
                    <label>Stock:</label><br>
                    <input type="number" name="quantity" id="inputQty" required style="width:100%; padding:5px;">
                </div>
                <div>
                    <button type="submit" class="btn-green">Save Updates</button>
                    <button type="button" class="btn-grey" onclick="closeEditForm()">Cancel</button>
                </div>
            </div>
        </form>
    </div>

    <form method="GET" style="margin-bottom: 15px; display:flex; gap:5px;">
        <input type="text" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="padding: 6px; border: 1px solid #ccc;">
        <button type="submit" class="btn-blue">Search</button>
    </form>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background: #e9ecef; color: black; font-weight: bold;">
            <th style="padding: 10px; border: 1px solid #dee2e6;">No.</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Category</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Part Name</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Supplier</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Stock</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Actions</th>
        </tr>
        <?php if (count($inventoryItems) > 0): ?>
            <?php $rowNum = 1; ?>
            <?php foreach ($inventoryItems as $item): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td><?php echo $rowNum++; ?></td>
                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                    <td><b><?php echo htmlspecialchars($item['part_name']); ?></b></td>
                    <td><?php echo htmlspecialchars($item['supplier']); ?></td>
                    <td>
                        <?php echo $item['stock_level']; ?>
                        <?php if($item['stock_level'] < 10) echo " <span style='color:red; font-weight:bold;'>(Low)</span>"; ?>
                    </td>
                    <td>
                        <button class="btn-blue" onclick="openEditForm(
                            '<?php echo $item['id']; ?>',
                            '<?php echo addslashes($item['part_name']); ?>',
                            '<?php echo $item['stock_level']; ?>',
                            '<?php echo addslashes($item['category']); ?>',
                            '<?php echo addslashes($item['supplier']); ?>'
                        )">Update</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No items found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<script>
function openEditForm(id, name, qty, cat, supp) {
    document.getElementById('editFormContainer').style.display = 'block';
    document.getElementById('inputID').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputQty').value = qty;
    document.getElementById('inputCategory').value = cat;
    document.getElementById('inputSupplier').value = supp;
    document.getElementById('editFormContainer').scrollIntoView({ behavior: 'smooth' });
}

function closeEditForm() {
    document.getElementById('editFormContainer').style.display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>