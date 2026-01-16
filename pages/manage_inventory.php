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
    echo "<div class='container mt-5'><div class='alert alert-danger'>⛔ Access Denied.</div></div>";
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
        $message = "Success!"; $messageType = "success";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage(); $messageType = "danger";
    }
}

// --- FETCH DATA FOR VIEW ---
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);
$stats = getDashboardStats($conn); // Get Stats
$logs = getRecentLogs($conn);      // Get Logs

require_once '../includes/header.php'; 
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<div class="container mt-5">
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">📦 Total Items</h5>
                    <h2 class="fw-bold"><?php echo $stats['total_items']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">⚠️ Low Stock Alerts</h5>
                    <h2 class="fw-bold"><?php echo $stats['low_stock']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-white shadow-sm" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#auditModal">
                <div class="card-body">
                    <h5 class="card-title">🕵️ Audit Logs</h5>
                    <p class="mb-0 mt-2">Click to view recent activity</p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <h2>Inventory Management</h2>
        <div class="d-flex gap-2">
            <form class="d-flex" method="GET" action="">
                <input class="form-control me-2" type="search" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button class="btn btn-primary" type="submit">Search</button>
            </form>
            <a href="manage_inventory.php?export=true" class="btn btn-success">📥 Export CSV</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm bg-light">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3" id="cardTitle">➕ Add New Item</h5>
            <form method="POST" action="" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="part_id" id="inputID">
                
                <div class="col-md-3">
                    <label class="fw-bold small">Part Name</label>
                    <input type="text" name="part_name" id="inputName" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold small">Category</label>
                    <select name="category" id="inputCategory" class="form-select">
                        <option value="General">General</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Hardware">Hardware</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold small">Supplier</label>
                    <input type="text" name="supplier" id="inputSupplier" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="fw-bold small">Stock</label>
                    <input type="number" name="quantity" id="inputQty" class="form-control" required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" id="submitBtn" class="btn btn-primary w-100">Add</button>
                    <button type="button" id="cancelBtn" class="btn btn-secondary w-100 d-none" onclick="resetForm()">X</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-dark">
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
                                $badge = ($item['stock_level'] < 10) ? (($item['stock_level'] == 0) ? 'danger' : 'warning text-dark') : 'success';
                                $text = ($item['stock_level'] < 10) ? (($item['stock_level'] == 0) ? 'Out of Stock' : 'Low Stock') : 'OK';
                            ?>
                            <tr>
                                <td>#<?php echo $item['id']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($item['part_name']); ?></td>
                                <td><span class="fs-5"><?php echo $item['stock_level']; ?></span></td>
                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $text; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editItem('<?php echo $item['id']; ?>', '<?php echo addslashes($item['part_name']); ?>', '<?php echo $item['stock_level']; ?>', '<?php echo addslashes($item['category']); ?>', '<?php echo addslashes($item['supplier']); ?>')">✏️</button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Soft Delete this item?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="part_id" value="<?php echo $item['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="auditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">🕵️ Recent Audit Logs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-striped">
                    <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo $log['timestamp']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editItem(id, name, qty, cat, supp) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('inputID').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputQty').value = qty;
    document.getElementById('inputCategory').value = cat;
    document.getElementById('inputSupplier').value = supp;
    document.getElementById('cardTitle').innerText = '✏️ Edit Item #' + id;
    document.getElementById('submitBtn').innerText = 'Save';
    document.getElementById('submitBtn').classList.replace('btn-primary', 'btn-warning');
    document.getElementById('cancelBtn').classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('inputID').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputQty').value = '';
    document.getElementById('cardTitle').innerText = '➕ Add New Item';
    document.getElementById('submitBtn').innerText = 'Add';
    document.getElementById('submitBtn').classList.replace('btn-warning', 'btn-primary');
    document.getElementById('cancelBtn').classList.add('d-none');
}
</script>

<?php require_once '../includes/footer.php'; ?>