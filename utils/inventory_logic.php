<?php
// utils/inventory_logic.php

// 1. Function to Get Inventory Items
function getInventory($conn, $search = '') {
    $items = [];
    try {
        if (!empty($search)) {
            $stmt = $conn->prepare("SELECT * FROM inventory WHERE (part_name LIKE ? OR category LIKE ? OR id = ?) AND is_deleted = 0 ORDER BY id ASC");
            $searchTerm = "%$search%";
            $stmt->bind_param("sss", $searchTerm, $searchTerm, $search);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT * FROM inventory WHERE is_deleted = 0 ORDER BY id ASC");
        }
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        return $items;
    } catch (Exception $e) { return []; }
}

// 2. Function to Get Logs (Fixes "Not Working" issue)
function getRecentLogs($conn, $limit = 50) {
    $logs = [];
    // We use LEFT JOIN so logs appear even if the user was deleted
    $sql = "SELECT a.action, a.timestamp, u.username 
            FROM audit_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            ORDER BY a.timestamp DESC LIMIT ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // If username is null (user deleted), show 'Unknown'
        if (empty($row['username'])) $row['username'] = 'System/Unknown';
        $logs[] = $row;
    }
    return $logs;
}

// 3. Function to Manage Items (Add/Update/Delete)
function manageInventory($conn, $action, $data, $userId) {
    // Validation
    if ($action === 'add' || $action === 'update') {
        if ($data['quantity'] < 0) throw new Exception("Stock cannot be negative.");
        if (empty($data['part_name'])) throw new Exception("Part Name is required.");
    }

    try {
        $conn->begin_transaction();
        $logDetails = "";

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO inventory (part_name, category, supplier, stock_level) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $data['part_name'], $data['category'], $data['supplier'], $data['quantity']);
            $stmt->execute();
            $newId = $conn->insert_id;
            $logDetails = "Added Item #$newId: '{$data['part_name']}'";

        } elseif ($action === 'update') {
            $stmt = $conn->prepare("UPDATE inventory SET stock_level = ?, part_name = ?, category = ?, supplier = ? WHERE id = ?");
            $stmt->bind_param("isssi", $data['quantity'], $data['part_name'], $data['category'], $data['supplier'], $data['id']);
            $stmt->execute();
            $logDetails = "Updated Item #{$data['id']}: Stock set to {$data['quantity']}";

        } elseif ($action === 'delete') {
            // Soft Delete logic
            $stmtGet = $conn->prepare("SELECT part_name FROM inventory WHERE id = ?");
            $stmtGet->bind_param("i", $data['id']);
            $stmtGet->execute();
            $res = $stmtGet->get_result();
            $part = $res->fetch_assoc();
            
            $stmt = $conn->prepare("UPDATE inventory SET is_deleted = 1 WHERE id = ?");
            $stmt->bind_param("i", $data['id']);
            $stmt->execute();
            
            $partName = $part ? $part['part_name'] : 'Unknown';
            $logDetails = "Deleted Item #{$data['id']} ($partName)";
        }

        // --- CRITICAL: SAVE THE LOG ---
        if (!empty($logDetails)) {
            $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
            $logStmt->bind_param("is", $userId, $logDetails);
            $logStmt->execute();
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception($e->getMessage());
    }
}
?>