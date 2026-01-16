<?php
// pages/manage_inventory.php
session_start();
require_once '../config/config.php';   
require_once '../utils/inventory_logic.php'; 

// --- SECURITY CHECK ---
// Allow Admin OR Inventory Manager
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    require_once '../includes/header.php';
    echo "<div class='container mt-5'><div class='alert alert-danger'>⛔ Access Denied.</div></div>";
    require_once '../includes/footer.php';
    exit();
}

// --- CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- HANDLE FORM ACTIONS ---
$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF Validation Failed");
    }
    try {
        $action = $_POST['action'];
        $data = [
            'id' => $_POST['part_id'] ?? null,
            'part_name' => trim($_POST['part_name'] ?? ''),
            'quantity' => $_POST['quantity'] ?? 0
        ];
        // Pass session ID correctly
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0; 
        manageInventory($conn, $action, $data, $userId);
        
        $message = ucfirst($action) . " action completed successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// --- FETCH DATA ---
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);

$pageTitle = 'Manage Inventory';
require_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container mt-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <h2>📦 Inventory Management</h2>
        
        <form class="d-flex" method="GET" action="">
            <input class="form-control me-2" type="search" name="search" placeholder="Search part name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if($searchTerm): ?>
                <a href="manage_inventory.php" class="btn btn-outline-secondary ms-2">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <strong><?php echo ($messageType == 'success') ? 'Success!' : 'Error!'; ?></strong> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0" id="cardTitle">➕ Add New Item</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add" id="formAction">
                
                <div class="col-md-2">
                    <label class="form-label fw-bold">Part ID</label>
                    <input type="text" name="part_id" id="inputID" class="form-control bg-light" placeholder="Auto" readonly>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Part Name</label>
                    <input type="text" name="part_name" id="inputName" class="form-control" placeholder="e.g. Brake Pad" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Stock Level</label>
                    <input type="number" name="quantity" id="inputQty" class="form-control" placeholder="0" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" id="submitBtn" class="btn btn-success w-100 fw-bold">Add Item</button>
                    <button type="button" id="cancelBtn" class="btn btn-secondary w-100 mt-2 d-none" onclick="resetForm()">Cancel Edit</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th style="width: 10%;">ID</th>
                        <th style="width: 40%;">Part Name</th>
                        <th style="width: 20%;">Stock Level</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($inventoryItems) > 0): ?>
                        <?php foreach ($inventoryItems as $item): ?>
                            <?php 
                                // Status Logic
                                $statusBadge = "<span class='badge bg-success'>In Stock</span>";
                                if ($item['stock_level'] == 0) {
                                    $statusBadge = "<span class='badge bg-danger'>Out of Stock</span>";
                                } elseif ($item['stock_level'] < 10) {
                                    $statusBadge = "<span class='badge bg-warning text-dark'>Low Stock</span>";
                                }
                            ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($item['id']); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($item['part_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['stock_level']); ?></td>
                                <td><?php echo $statusBadge; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary me-1" 
                                        onclick="editItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['part_name']); ?>', <?php echo $item['stock_level']; ?>)">
                                        ✏️
                                    </button>

                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="part_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No items found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editItem(id, name, qty) {
    // Fill Form
    document.getElementById('formAction').value = 'update';
    document.getElementById('inputID').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputQty').value = qty;
    
    // Change UI to Edit Mode
    document.getElementById('cardTitle').innerText = '✏️ Edit Item #' + id;
    const btn = document.getElementById('submitBtn');
    btn.className = 'btn btn-warning w-100 fw-bold';
    btn.innerText = 'Save Changes';
    
    // Show Cancel Button
    document.getElementById('cancelBtn').classList.remove('d-none');
    
    // Scroll to Top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    // Clear Form
    document.getElementById('formAction').value = 'add';
    document.getElementById('inputID').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputQty').value = '';
    
    // Reset UI to Add Mode
    document.getElementById('cardTitle').innerText = '➕ Add New Item';
    const btn = document.getElementById('submitBtn');
    btn.className = 'btn btn-success w-100 fw-bold';
    btn.innerText = 'Add Item';
    
    // Hide Cancel Button
    document.getElementById('cancelBtn').classList.add('d-none');
}
</script>

<?php require_once '../includes/footer.php'; ?>