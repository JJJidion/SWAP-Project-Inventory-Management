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
    echo "<div class='container' style='color:red; margin-top:20px;'><h3>⛔ Access Denied.</h3></div>";
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

// --- INCLUDE COMMON HEADER ---
$pageTitle = 'Inventory Manager';
require_once '../includes/header.php'; 
?>

<style>
    /* Mimic Admin Dashboard Layout */
    .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    
    /* Stats Row */
    .stats-row { display: flex; gap: 20px; margin-bottom: 30px; }
    .stat-box { flex: 1; background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .stat-box h3 { margin: 0 0 10px 0; font-size: 16px; color: #555; }
    .stat-box .number { font-size: 32px; font-weight: bold; margin: 0; color: #333; }
    
    /* Action Bar */
    .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 5px; }
    .search-form { display: flex; gap: 10px; }
    .search-form input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    
    /* Buttons (Mimic your btn-primary) */
    .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; color: white; font-size: 14px; }
    .btn-blue { background-color: #007bff; }
    .btn-green { background-color: #28a745; }
    .btn-red { background-color: #dc3545; }
    .btn-grey { background-color: #6c757d; }

    /* Form Section */
    .add-form { background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 30px; }
    .form-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 15px; align-items: end; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9em; }
    .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }

    /* Table */
    .data-table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #ddd; }
    .data-table th { background: #333; color: white; padding: 12px; text-align: left; }
    .data-table td { padding: 12px; border-bottom: 1px solid #eee; }
    .data-table tr:hover { background-color: #f5f5f5; }

    /* Status Badges */
    .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; color: white; }
    .status-ok { background: #28a745; }
    .status-low { background: #ffc107; color: black; }
    .status-out { background: #dc3545; }

    /* Audit Log Popup */
    #auditLogSection { display: none; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
</style>

<div class="dashboard-container">
    <h2>Inventory Management</h2>
    <p>Manage your stock levels, suppliers, and view audit trails.</p>

    <div class="stats-row">
        <div class="stat-box" style="border-left: 5px solid #007bff;">
            <h3>📦 Total Items</h3>
            <p class="number"><?php echo $stats['total_items']; ?></p>
        </div>
        <div class="stat-box" style="border-left: 5px solid #ffc107;">
            <h3>⚠️ Low Stock Alerts</h3>
            <p class="number"><?php echo $stats['low_stock']; ?></p>
        </div>
        <div class="stat-box" style="border-left: 5px solid #6c757d;">
            <h3>🕵️ Audit Logs</h3>
            <button onclick="toggleLogs()" class="btn btn-grey" style="width:100%; margin-top:5px;">View Recent Activity</button>
            <div id="auditLogSection">
                <ul style="list-style:none; padding:0; font-size:0.9em;">
                    <?php foreach ($logs as $log): ?>
                        <li style="margin-bottom:5px; border-bottom:1px solid #eee; padding-bottom:2px;">
                            <b><?php echo htmlspecialchars($log['username']); ?></b>: <?php echo htmlspecialchars($log['action']); ?>
                            <br><small style="color:#888;"><?php echo $log['timestamp']; ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div style="padding:15px; margin-bottom:20px; background:<?php echo ($messageType=='green')?'#d4edda':'#f8d7da'; ?>; color:<?php echo ($messageType=='green')?'#155724':'#721c24'; ?>; border-radius:4px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="action-bar">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search part name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button type="submit" class="btn btn-blue">Search</button>
        </form>
        <a href="manage_inventory.php?export=true" class="btn btn-green">📥 Export CSV Report</a>
    </div>

    <div class="add-form">
        <h4 id="formTitle" style="margin-top:0;">➕ Add New Item</h4>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add" id="formAction">
            <input type="hidden" name="part_id" id="inputID">

            <div class="form-grid">
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
                <div class="form-group">
                    <label>Stock</label>
                    <input type="number" name="quantity" id="inputQty" required>
                </div>
                <div class="form-group">
                    <button type="submit" id="submitBtn" class="btn btn-blue" style="width:100%;">Add Item</button>
                    <button type="button" id="cancelBtn" class="btn btn-grey" onclick="resetForm()" style="width:100%; display:none; margin-top:5px;">Cancel</button>
                </div>
            </div>
        </form>
    </div>

    <table class="data-table">
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
                        $badgeClass = 'status-ok';
                        $statusText = 'OK';
                        if ($item['stock_level'] == 0) { $badgeClass = 'status-out'; $statusText = 'Out of Stock'; }
                        elseif ($item['stock_level'] < 10) { $badgeClass = 'status-low'; $statusText = 'Low Stock'; }
                    ?>
                    <tr>
                        <td>#<?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                        <td><strong><?php echo htmlspecialchars($item['part_name']); ?></strong></td>
                        <td><?php echo $item['stock_level']; ?></td>
                        <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span></td>
                        <td>
                            <button class="btn btn-blue" style="padding:4px 8px; font-size:12px;" 
                                onclick="editItem(
                                    '<?php echo $item['id']; ?>', 
                                    '<?php echo addslashes($item['part_name']); ?>', 
                                    '<?php echo $item['stock_level']; ?>', 
                                    '<?php echo addslashes($item['category']); ?>', 
                                    '<?php echo addslashes($item['supplier']); ?>'
                                )">Edit</button>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="part_id" value="<?php echo $item['id']; ?>">
                                <button class="btn btn-red" style="padding:4px 8px; font-size:12px;">Del</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding:20px; color:#777;">No items found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleLogs() {
    var x = document.getElementById("auditLogSection");
    if (x.style.display === "block") { x.style.display = "none"; } else { x.style.display = "block"; }
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
    document.getElementById('cancelBtn').style.display = 'block';
    
    document.querySelector('.add-form').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('inputID').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputQty').value = '';
    document.getElementById('inputSupplier').value = '';
    
    document.getElementById('formTitle').innerText = '➕ Add New Item';
    document.getElementById('submitBtn').innerText = 'Add Item';
    document.getElementById('cancelBtn').style.display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>