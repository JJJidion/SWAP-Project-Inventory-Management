<?php
// utils/inventory_logic.php

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

function getRecentLogs($conn, $limit = 50) {
    $logs = [];
    $sql = "SELECT a.action, a.timestamp, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.timestamp DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (empty($row['username'])) $row['username'] = 'System/Unknown';
        $logs[] = $row;
    }
    return $logs;
}

function manageInventory($conn, $action, $data, $userId) {
    // Validation
    if ($action === 'add' || $action === 'update') {
        if ($data['quantity'] < 0) throw new Exception("Stock cannot be negative.");
        if ($data['price'] < 0) throw new Exception("Price cannot be negative."); // New Check
        if (empty($data['part_name'])) throw new Exception("Part Name is required.");
    }

    try {
        $conn->begin_transaction();
        $logDetails = "";

        if ($action === 'add') {
            // UPDATED: Added price column
            $stmt = $conn->prepare("INSERT INTO inventory (part_name, category, supplier, stock_level, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssid", $data['part_name'], $data['category'], $data['supplier'], $data['quantity'], $data['price']);
            $stmt->execute();
            $newId = $conn->insert_id;
            $logDetails = "Added Item #$newId: '{$data['part_name']}' (Value: \${$data['price']})";

        } elseif ($action === 'update') {
            // UPDATED: Added price column
            $stmt = $conn->prepare("UPDATE inventory SET stock_level = ?, part_name = ?, category = ?, supplier = ?, price = ? WHERE id = ?");
            $stmt->bind_param("isssdi", $data['quantity'], $data['part_name'], $data['category'], $data['supplier'], $data['price'], $data['id']);
            $stmt->execute();
            $logDetails = "Updated Item #{$data['id']}: Stock {$data['quantity']}, Value \${$data['price']}";

        } elseif ($action === 'delete') {
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