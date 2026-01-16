<?php
// pages/manage_inventory.php
session_start();
require_once '../config/config.php';   // Connects to DB (MySQLi $conn)
require_once '../utils/inventory_logic.php'; // Includes the logic functions

// --- SECURITY: Access Control ---
// IMPORTANT: Allow 'Admin' role so you can access it from your dashboard
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    // Load header just to show the error nicely
    require_once '../includes/header.php'; 
    echo "<div class='container mt-5'><div class='alert alert-danger'>⛔ Access Denied: You do not have permission.</div></div>";
    require_once '../includes/footer.php';
    exit();
}

// --- SECURITY: CSRF Token Generation ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- CONTROLLER: Handle Form Actions ---
$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF Validation Failed");
    }

    try {
        $action = $_POST['action'];
        // Collect Data
        $data = [
            'id' => $_POST['part_id'] ?? null,
            'part_name' => trim($_POST['part_name'] ?? ''),
            'quantity' => $_POST['quantity'] ?? 0
        ];

        // Call the logic function
        manageInventory($conn, $action, $data, $_SESSION['id']);
        
        $message = ucfirst($action) . " action completed successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// --- VIEW: Fetch Data ---
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);

// --- PAGE TITLE ---
$pageTitle = 'Manage Inventory';
require_once '../includes/header.php'; 
?>

<div class="container mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Inventory Management</h1>
        <form class="d-flex" method="GET" action="">
            <input class="form-control me-2" type="search" name="search" placeholder="Search part..." value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if($searchTerm): ?>
                <a href="manage_inventory.php" class="btn btn-secondary ms-2">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header text-white bg-primary">
            <h5 class="mb-0" id="cardTitle">➕ Add New Item</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add" id="formAction">
                
                <div class="col-md-2">
                    <label class="form-label">Part ID</label>
                    <input type="text" name="part_id" id="inputID" class="form-control bg-light" placeholder="Auto" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Part Name</label>
                    <input type="text" name="part_name" id="inputName" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stock Level</label>
                    <input type="number" name="quantity" id="inputQty" class="form-control" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="w-100">
                        <button type="submit" id="submitBtn" class="btn btn-success w-100">Add Part</button>
                        <button type="button" id="cancelBtn" class="btn btn-secondary w-100 mt-2 d-none" onclick="resetForm()">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Part Name</th>
                            <th>Stock Level</th>
                            <th>Status</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($inventoryItems) > 0): ?>
                            <?php foreach ($inventoryItems as $item): ?>
                                <?php 
                                    // Status Badges
                                    $status = "<span class='badge bg-success'>OK</span>";
                                    if ($item['stock_level'] == 0) {
                                        $status = "<span class='badge bg-danger'>Out of Stock</span>";
                                    } elseif ($item['stock_level'] < 10) {
                                        $status = "<span class='badge bg-warning text-dark'>Low Stock</span>";
                                    }
                                ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($item['id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($item['part_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['stock_level']); ?></td>
                                    <td><?php echo $status; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary me-1" 
                                            onclick="editItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['part_name']); ?>', <?php echo $item['stock_level']; ?>)">
                                            Edit
                                        </button>

                                        <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete Part ID <?php echo $item['id']; ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="part_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No parts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editItem(id, name, qty) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('inputID').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputQty').value = qty;
    
    document.getElementById('cardTitle').innerText = '✏️ Edit Item #' + id;
    const btn = document.getElementById('submitBtn');
    btn.className = 'btn btn-warning w-100';
    btn.innerText = 'Update Part';
    document.getElementById('cancelBtn').classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('inputID').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputQty').value = '';
    
    document.getElementById('cardTitle').innerText = '➕ Add New Item';
    const btn = document.getElementById('submitBtn');
    btn.className = 'btn btn-success w-100';
    btn.innerText = 'Add Part';
    document.getElementById('cancelBtn').classList.add('d-none');
}
</script>

<?php require_once '../includes/footer.php'; ?>