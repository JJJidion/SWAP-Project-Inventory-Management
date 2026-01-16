<?php
// pages/manage_inventory.php
session_start();
require_once '../config/config.php';
require_once '../utils/inventory_logic.php';

// --- SECURITY HEADERS ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// --- ACCESS CONTROL ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    require_once '../includes/header.php';
    echo "<div class='container' style='margin-top:20px; color:red;'><h3>⛔ Access Denied.</h3></div>";
    require_once '../includes/footer.php';
    exit();
}

// --- EXPORT LOGIC ---
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Part Name', 'Category', 'Supplier', 'Stock', 'Status', 'Last Updated'));
    $items = getInventory($conn, '');
    foreach ($items as $item) {
        $status = ($item['stock_level'] < 10) ? (($item['stock_level'] == 0) ? 'Out of Stock' : 'Low Stock') : 'In Stock';
        fputcsv($output, array($item['id'], $item['part_name'], $item['category'], $item['supplier'], $item['stock_level'], $status, $item['updated_at']));
    }
    fclose($output);
    exit();
}

// --- FORM HANDLING ---
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$message = ""; $messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("CSRF Fail");
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
        $message = "Action successful!"; $messageType = "green";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage(); $messageType = "red";
    }
}

// --- FETCH DATA ---
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);
$stats = getDashboardStats($conn);
$logs = getRecentLogs($conn);

// --- INCLUDE YOUR COMMON HEADER ---
// This brings in your style.css automatically
require_once '../includes/header.php'; 
?>

<style>
    /* Mimic the layout using basic CSS grid/flex */
    .dashboard-grid { display: flex; gap: 20px; margin-bottom: 20px; }
    .stat-card { flex: 1; padding: 15px; border: 1px solid #ccc; border-radius: 5px; background: #f9f9f9; }
    .stat-card h2 { margin: 5px 0 0 0; font-size: 24px; }
    
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
    .search-box { display: flex; gap: 5px; }
    
    .inventory-form { background: #f4f4f4; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ddd; }
    .form-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
    .form-group { flex: 1; min-width: 150px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 0.9em; }
    .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    
    /* Standard Table Styling */
    .inv-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .inv-table th { background: #333; color: white; padding: 10px; text-align: left; }
    .inv-table td { padding: 10px; border-bottom: 1px solid #eee; }
    .inv-table tr:hover { background-color: #f9f9f9; }
    
    /* Utility Classes */
    .badge { padding: 3px 8px; border-radius: 10px; font-size: 0.8em; color: white; }
    .bg-green { background-color: #28a745; }
    .bg-orange { background-color: #ffc107; color: black; }
    .bg-red { background-color: #dc3545; }
    
    .audit-log-container { display: none; margin-bottom: 20px; padding: 15px; border: 1px solid #333; background: #fff; }
</style>

<div class="container">
    <h2 style="margin-top: 20px;">Inventory Dashboard</h2>

    <div class="dashboard-grid">
        <div class="stat-card" style="border-left: 5px solid #007bff;">
            <span>📦 Total Items</span>
            <h2><?php echo $stats['total_items']; ?></h2>
        </div>
        <div class="stat-card" style="border-left: 5px solid #ffc107;">
            <span>⚠️ Low Stock</span>
            <h2><?php echo $stats['low_stock']; ?></h2>
        </div>
        <div class="stat-card" style="border-left: 5px solid #6c757d; cursor: pointer;" onclick="toggleAuditLogs()">
            <span>🕵️ Audit Logs (Click to View)</span>
            <h2 style="font-size: 16px; margin-top: 10px;">View Recent Activity &darr;</h2>
        </div>
    </div>

    <div id="auditLogs" class="audit-log-container">
        <h4>Recent System Activity</h4>
        <table class="inv-table">
            <tr><th>User</th><th>Action</th><th>Time</th></tr>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><b><?php echo htmlspecialchars($log['username']); ?></b></td>
                <td><?php echo htmlspecialchars($log['action']); ?></td>
                <td><?php echo $log['timestamp']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php if ($message): ?>
        <div style="padding: 10px; margin-bottom: 20px; background: <?php echo ($messageType=='green')?'#d4edda':'#f8d7da'; ?>; color: <?php echo ($messageType=='green')?'#155724':'#721c24'; ?>; border: 1px solid transparent; border-radius: 4px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <h3>Manage Items</h3>
        <div class="search-box">
            <form method="GET" style="display:flex; gap:5px;">
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="padding: 5px;">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
            <a href="manage_inventory.php?export=true" class="btn btn-success" style="text-decoration:none; padding: 6px 12px; background: green; color: white; border-radius: 4px;">Export CSV</a>
        </div>
    </div>

    <div class="inventory-form">
        <h4 id="formTitle" style="margin-top:0;">➕ Add New Item</h4>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add" id="formAction">
            <input type="hidden" name="part_id" id="inputID">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Part Name</label>
                    <input type="text" name="part_name" id="inputName" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="inputCategory">
                        <option value="General">General</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Hardware">Hardware</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" name="supplier" id="inputSupplier">
                </div>
                <div class="form-group" style="max-width: 100px;">
                    <label>Stock</label>
                    <input type="number" name="quantity" id="inputQty" required>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" id="submitBtn" class="btn btn-primary" style="width:100%;">Add Item</button>
                </div>
                <div class="form-group" id="cancelGroup" style="display:none; max-width: 100px;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger" onclick="resetForm()" style="width:100%; background: #666;">Cancel</button>
                </div>
            </div>
        </form>
    </div>

    <table class="inv-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Part Name</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($inventoryItems) > 0): ?>
                <?php foreach ($inventoryItems as $item): ?>
                    <?php 
                        $badgeClass = ($item['stock_level'] < 10) ? (($item['stock_level'] == 0) ? 'bg-red' : 'bg-orange') : 'bg-green';
                        $statusText = ($item['stock_level'] < 10) ? (($item['stock_level'] == 0) ? 'Out of Stock' : 'Low Stock') : 'OK';
                    ?>
                    <tr>
                        <td>#<?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                        <td><b><?php echo htmlspecialchars($item['part_name']); ?></b></td>
                        <td><?php echo $item['stock_level']; ?></td>
                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span></td>
                        <td>
                            <button class="btn btn-primary" style="padding: 2px 8px; font-size: 0.8em;" onclick="editItem('<?php echo $item['id']; ?>', '<?php echo addslashes($item['part_name']); ?>', '<?php echo $item['stock_level']; ?>', '<?php echo addslashes($item['category']); ?>', '<?php echo addslashes($item['supplier']); ?>')">Edit</button>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="part_id" value="<?php echo $item['id']; ?>">
                                <button class="btn btn-danger" style="padding: 2px 8px; font-size: 0.8em; background: #dc3545; color: white; border: none; cursor: pointer;">Del</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding: 20px;">No items found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleAuditLogs() {
    var x = document.getElementById("auditLogs");
    if (x.style.display === "block") { x.style.display = "none"; } 
    else { x.style.display = "block"; }
}

function editItem(id, name, qty, cat, supp) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('inputID').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputQty').value = qty;
    document.getElementById('inputCategory').value = cat;
    document.getElementById('inputSupplier').value = supp;

    document.getElementById('formTitle').innerText = '✏️ Edit Item #' + id;
    document.getElementById('submitBtn').innerText = 'Save Changes';
    document.getElementById('cancelGroup').style.display = 'block';
    
    // Smooth scroll to form
    document.querySelector('.inventory-form').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('inputID').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputQty').value = '';
    document.getElementById('inputSupplier').value = '';
    
    document.getElementById('formTitle').innerText = '➕ Add New Item';
    document.getElementById('submitBtn').innerText = 'Add Item';
    document.getElementById('cancelGroup').style.display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>