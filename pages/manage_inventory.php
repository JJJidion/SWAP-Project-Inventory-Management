<?php
// pages/manage_inventory.php
session_start();
require_once '../config/config.php';
require_once '../utils/inventory_logic.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

// --- SECURITY HEADERS ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// --- ROLE CHECK ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'User') {
        header("Location: user_inventory.php");
        exit();
    }
    header("Location: ../index.php");
    exit();
}

// --- CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    try {
        $action = $_POST['action'];
        $data = [
            'id' => $_POST['part_id'] ?? null,
            'part_name' => $_POST['part_name'] ?? '',
            'category' => $_POST['category'] ?? 'General',
            'supplier' => $_POST['supplier'] ?? '',
            'quantity' => $_POST['quantity'] ?? 0,
            'price' => $_POST['price'] ?? 0.00
        ];
        
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $userRole = $_SESSION['role'];
        
        manageInventory($conn, $action, $data, $userId, $userRole);
        $message = "Action successful!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Fetch Data
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);
$totalItems = count($inventoryItems);
$lowStockCount = 0;
foreach ($inventoryItems as $item) { if ($item['stock_level'] < 10) $lowStockCount++; }

$pageTitle = 'Inventory Manager';
require_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../css/style.css">

<style>
    .btn-green { background-color: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
    .btn-green:hover { background-color: #218838; }
    .btn-blue { background-color: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; }
    .btn-red { background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; }
    .btn-warning { background-color: #ffc107; color: black; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-secondary { background-color: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-left: 5px; }
    .edit-mode { border: 2px solid #ffc107 !important; background-color: #fffbf0 !important; }
</style>

<div class="container">
    <h1>🏭 Advanced Manufacturing Centre</h1>
    
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
    <h3 id="formTitle">Add New Item</h3>
    <form id="inventoryForm" method="POST" action="" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px; border-radius: 5px;">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="action" value="add" id="formAction">
        <input type="hidden" name="part_id" id="inputID">
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <div style="flex: 2;">
                <label>Part Name:</label><br>
                <input type="text" name="part_name" id="inputName" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="flex: 1;">
                <label>Category:</label><br>
                <select name="category" id="inputCategory" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option>Raw Materials</option>
                    <option>Tooling</option>
                    <option>Components</option>
                    <option>Consumables</option>
                    <option>General</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label>Supplier:</label><br>
                <input type="text" name="supplier" id="inputSupplier" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="flex: 1;">
                <label>Value ($):</label><br>
                <input type="number" step="0.01" min="0" max="10000" name="price" id="inputPrice" placeholder="0.00" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="flex: 1;">
                <label>Stock:</label><br>
                <input type="number" min="0" max="10000" name="quantity" id="inputQty" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" id="submitBtn" class="btn-green">Add Item</button>
                <button type="button" id="cancelBtn" class="btn-secondary" onclick="resetForm()" style="display: none;">Cancel</button>
            </div>
        </div>
    </form>

    <h3>Current Stock</h3>
    <form method="GET" style="margin-bottom: 10px; display: flex; gap: 5px;">
        <input type="text" name="search" placeholder="Search part name..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        <button type="submit" class="btn-green">Search</button>
        <a href="export_inventory.php" class="btn-green" style="margin-left: 10px;">Export CSV</a>
    </form>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <tr style="background: #e9ecef; color: black; font-weight: bold;">
            <th style="padding: 10px; border: 1px solid #dee2e6;">No.</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Category</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Part Name</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Supplier</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Value ($)</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Stock</th>
            <th style="padding: 10px; border: 1px solid #dee2e6;">Actions</th>
        </tr>
        <?php if (count($inventoryItems) > 0): ?>
            <?php $rowNumber = 1; ?>
            <?php foreach ($inventoryItems as $item): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td><?php echo $rowNumber++; ?></td>
                    <td><?php echo $item['category']; ?></td>
                    <td><b><?php echo $item['part_name']; ?></b></td>
                    <td><?php echo $item['supplier']; ?></td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td>
                        <?php echo $item['stock_level']; ?>
                        <?php if($item['stock_level'] < 10) echo " <span style='color:red; font-weight:bold;'>(Low)</span>"; ?>
                    </td>
                    <td>
                        <button class="btn-blue" onclick="editItem(
                            '<?php echo $item['id']; ?>', 
                            '<?php echo addslashes($item['part_name']); ?>', 
                            '<?php echo $item['stock_level']; ?>',
                            '<?php echo addslashes($item['category']); ?>',
                            '<?php echo addslashes($item['supplier']); ?>',
                            '<?php echo $item['price']; ?>'
                        )">Edit</button>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="part_id" value="<?php echo $item['id']; ?>">
                            <button class="btn-red">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" style="text-align: center;">No items found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<script>
function editItem(id, name, qty, cat, supp, price) {
    document.getElementById('inventoryForm').scrollIntoView({ behavior: 'smooth' });
    document.getElementById('formAction').value = 'update';
    document.getElementById('inputID').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputQty').value = qty;
    document.getElementById('inputCategory').value = cat;
    document.getElementById('inputSupplier').value = supp;
    document.getElementById('inputPrice').value = price;
    
    document.getElementById('formTitle').innerText = '✏️ Edit Item';
    document.getElementById('submitBtn').innerText = 'Update Item';
    document.getElementById('submitBtn').className = 'btn-warning';
    document.getElementById('cancelBtn').style.display = 'inline-block';
    
    document.getElementById('inventoryForm').classList.add('edit-mode');
}

function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('inputID').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputQty').value = '';
    document.getElementById('inputSupplier').value = '';
    document.getElementById('inputPrice').value = '';
    
    document.getElementById('formTitle').innerText = 'Add New Item';
    document.getElementById('submitBtn').innerText = 'Add Item';
    document.getElementById('submitBtn').className = 'btn-green';
    document.getElementById('cancelBtn').style.display = 'none';
    
    document.getElementById('inventoryForm').classList.remove('edit-mode');
}
</script>

<?php require_once '../includes/footer.php'; ?>