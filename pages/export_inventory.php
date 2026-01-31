<?php
// pages/export_inventory.php
session_start();
require_once '../config/config.php';
require_once '../utils/inventory_logic.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

// --- SECURITY CHECK ---
// Ensure only Admins/Managers can access this file directly
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {
    header("Location: ../index.php");
    exit();
}

// --- CSV EXPORT LOGIC ---
// 1. Fetch current inventory
$exportItems = getInventory($conn, ''); 

// 2. Set Headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=AMC_Inventory_Export_' . date('Y-m-d') . '.csv');

// 3. Open output stream
$output = fopen('php://output', 'w');

// 4. Add Column Headers
fputcsv($output, ['ID', 'Category', 'Part Name', 'Supplier', 'Value ($)', 'Stock Level']);

// 5. Add Data Rows
foreach ($exportItems as $item) {
    fputcsv($output, [
        $item['id'],
        $item['category'],
        $item['part_name'],
        $item['supplier'],
        number_format($item['price'], 2),
        $item['stock_level']
    ]);
}

// 6. Close and Exit
fclose($output);
exit();
?>