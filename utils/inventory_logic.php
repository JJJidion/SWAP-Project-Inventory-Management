<?php
// utils/inventory_logic.php

function getInventory($conn, $search = '') {
    $items = [];
    try {
        // IMPROVEMENT: Added "AND is_deleted = 0" to hide deleted items
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
    } catch (Exception $e) {
        return [];
    }
}

function manageInventory($conn, $action, $data, $userId) {
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
            $logDetails = "Added Part ID $newId: '{$data['part_name']}'";

        } elseif ($action === 'update') {
            $stmt = $conn->prepare("UPDATE inventory SET stock_level = ?, part_name = ?, category = ?, supplier = ? WHERE id = ?");
            $stmt->bind_param("isssi", $data['quantity'], $data['part_name'], $data['category'], $data['supplier'], $data['id']);
            $stmt->execute();
            $logDetails = "Updated Part ID {$data['id']}: Stock {$data['quantity']}";

        } elseif ($action === 'delete') {
            // IMPROVEMENT: Soft Delete (Update Flag instead of DELETE)
            // This preserves the data for forensics/audit purposes.
            $stmtGet = $conn->prepare("SELECT part_name FROM inventory WHERE id = ?");
            $stmtGet->bind_param("i", $data['id']);
            $stmtGet->execute();
            $res = $stmtGet->get_result();
            $part = $res->fetch_assoc();
            
            // The Logic Change:
            $stmt = $conn->prepare("UPDATE inventory SET is_deleted = 1 WHERE id = ?");
            $stmt->bind_param("i", $data['id']);
            $stmt->execute();
            $logDetails = "Soft Deleted Part ID {$data['id']} ({$part['part_name']})";
        }

        // Traceability
        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $logStmt->bind_param("is", $userId, $logDetails);
        $logStmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception($e->getMessage());
    }
}
?>
function manageInventory($conn, $action, $data, $userId) {
    // 1. Validation
    if ($action === 'add' || $action === 'update') {
        if ($data['quantity'] < 0) throw new Exception("Stock cannot be negative.");
        if (empty($data['part_name'])) throw new Exception("Part Name is required.");
    }

    try {
        $conn->begin_transaction();
        $logDetails = "";

        if ($action === 'add') {
            // Updated SQL to include Category and Supplier
            $stmt = $conn->prepare("INSERT INTO inventory (part_name, category, supplier, stock_level) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $data['part_name'], $data['category'], $data['supplier'], $data['quantity']);
            $stmt->execute();
            $newId = $conn->insert_id;
            $logDetails = "Added Part ID $newId: '{$data['part_name']}' ({$data['category']})";

        } elseif ($action === 'update') {
            // Updated SQL
            $stmt = $conn->prepare("UPDATE inventory SET stock_level = ?, part_name = ?, category = ?, supplier = ? WHERE id = ?");
            $stmt->bind_param("isssi", $data['quantity'], $data['part_name'], $data['category'], $data['supplier'], $data['id']);
            $stmt->execute();
            $logDetails = "Updated Part ID {$data['id']}: Stock {$data['quantity']}, Cat: {$data['category']}";

        } elseif ($action === 'delete') {
            // (Keep existing delete logic)
            $stmtGet = $conn->prepare("SELECT part_name FROM inventory WHERE id = ?");
            $stmtGet->bind_param("i", $data['id']);
            $stmtGet->execute();
            $res = $stmtGet->get_result();
            $part = $res->fetch_assoc();
            
            $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->bind_param("i", $data['id']);
            $stmt->execute();
            $logDetails = "Deleted Part ID {$data['id']} ({$part['part_name']})";
        }

        // Audit Log
        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $logStmt->bind_param("is", $userId, $logDetails);
        $logStmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception($e->getMessage());
    }
}
?>