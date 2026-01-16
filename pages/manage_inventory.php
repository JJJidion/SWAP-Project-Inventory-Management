<?php
// pages/manage_inventory.php
session_start();
require_once '../config/config.php';   // Connects to DB (Assumes $conn or $pdo)
require_once '../utils/inventory_logic.php'; // Includes the logic above
require_once '../includes/header.php'; // Your site header

// --- SECURITY: Access Control ---
// Change 'Inventory Manager' to match your DB role exactly
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>⛔ Access Denied.</div></div>";
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
    // CSRF Check
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

        // Call the Logic Function (from utils/inventory_logic.php)
        // NOTE: Ensure your config file variable is named $conn. If it is $pdo, change it here.
        manageInventory($conn, $action, $data, $_SESSION['user_id']);
        
        $message = "Action '$action' completed successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// --- VIEW: Fetch Data (With Search Support) ---
$searchTerm = $_GET['search'] ?? '';
$inventoryItems = getInventory($conn, $searchTerm);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📦 Inventory Management</h2>
        <form class="d-flex" method="GET" action="">
            <input class="form-control me-2" type="search" name="search" placeholder="Search part..." value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button class="btn btn-outline-primary" type="submit">Search</button>
            <?php if($searchTerm): ?>
                <a href="manage_inventory.php" class="btn btn-outline-secondary ms-2">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm border-primary">
        <div class="card-header bg-primary text-white">
            <strong id="cardTitle">➕ Add New Item</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add" id="formAction">
                
                <div class="col-md-2">
                    <label class="form-label">Part ID</label>
                    <input type="text" name="part_id" id="inputID" class="form-control" placeholder="Auto" readonly>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Part Name</label>
                    <input type="text" name="part_name" id="inputName" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stock Level</label>
                    <input type="number" name="quantity" id="inputQty" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" id="submitBtn" class="btn btn-success w-100">Add Part</button>
                    <button type="button" id="cancelBtn" class="btn btn-secondary w-100 d-none" onclick="resetForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover table-bordered mb-0">
                <thead class="table-dark">
                    <tr>
                        <th width="10%">ID</th>
                        <th width="40%">Part Name</th>
                        <th width="20%">Stock Level</th>
                        <th width="15%">Status</th>
                        <th width="15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($inventoryItems) > 0): ?>
                        <?php foreach ($inventoryItems as $item): ?>
                            <?php 
                                // Visual Alerts (Low Stock Logic)
                                $rowClass = "";
                                $status = "<span class='badge bg-success'>OK</span>";
                                
                                if ($item['stock_level'] == 0) {
                                    $rowClass = "table-danger"; // Red
                                    $status = "<span class='badge bg-danger'>Out of Stock</span>";
                                } elseif ($item['stock_level'] < 10) {
                                    $rowClass = "table-warning"; // Yellow
                                    $status = "<span class='badge bg-warning text-dark'>Low Stock</span>";
                                }
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo htmlspecialchars($item['id']); ?></td>
                                <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($item['stock_level']); ?></strong></td>
                                <td><?php echo $status; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" 
                                        onclick="editItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['part_name']); ?>', <?php echo $item['stock_level']; ?>)">
                                        ✏️ Edit
                                    </button>

                                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Delete Part ID <?php echo $item['id']; ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="part_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4">No parts found matching your search.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editItem(id, name, qty) {
    // 1. Populate the form
    document.getElementById('formAction').value = 'update';
    document.getElementById('inputID').value = id;
    document.getElementById('inputName').value = name;
    document.getElementById('inputQty').value = qty;
    
    // 2. Change UI to "Edit Mode"
    document.getElementById('cardTitle').innerText = '✏️ Edit Item (ID: ' + id + ')';
    
    const btn = document.getElementById('submitBtn');
    btn.className = 'btn btn-warning w-100';
    btn.innerText = 'Save Changes';
    
    // 3. Show Cancel button
    document.getElementById('cancelBtn').classList.remove('d-none');
    document.getElementById('submitBtn').classList.add('mb-2'); // Add spacing
    
    // 4. Scroll to form
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    // 1. Clear Form
    document.getElementById('formAction').value = 'add';
    document.getElementById('inputID').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputQty').value = '';
    
    // 2. Reset UI to "Add Mode"
    document.getElementById('cardTitle').innerText = '➕ Add New Item';
    
    const btn = document.getElementById('submitBtn');
    btn.className = 'btn btn-success w-100';
    btn.innerText = 'Add Part';
    
    // 3. Hide Cancel button
    document.getElementById('cancelBtn').classList.add('d-none');
    document.getElementById('submitBtn').classList.remove('mb-2');
}
</script>

<?php require_once '../includes/footer.php'; ?>