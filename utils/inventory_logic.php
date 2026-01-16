<?php
// utils/inventory_logic.php

/**
 * Fetch inventory items, optionally filtering by a search term.
 * (MySQLi Version)
 */
function getInventory($conn, $search = '') {
    $items = [];
    try {
        if (!empty($search)) {
            // Prepare statement for search
            $stmt = $conn->prepare("SELECT * FROM inventory WHERE part_name LIKE ? OR id = ? ORDER BY id ASC");
            $searchTerm = "%$search%";
            // Bind parameters: "ss" means string, string
            $stmt->bind_param("ss", $searchTerm, $search);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            // Simple query
            $result = $conn->query("SELECT * FROM inventory ORDER BY id ASC");
        }

        // Fetch all rows
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        return $items;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Handles Add, Update, and Delete operations with Audit Logging.
 * (MySQLi Version)
 */
function manageInventory($conn, $action, $data, $userId) {
    // 1. Validation Logic
    if ($action === 'add' || $action === 'update') {
        if ($data['quantity'] < 0) {
            throw new Exception("Stock level cannot be negative.");
        }
        if (empty($data['part_name']) && $action === 'add') {
            throw new Exception("Part Name is required.");
        }
    }

    try {
        // Start Transaction
        $conn->begin_transaction();

        $logDetails = "";

        // 2. Execution Logic
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO inventory (part_name, stock_level) VALUES (?, ?)");
            // "si" means String, Integer
            $stmt->bind_param("si", $data['part_name'], $data['quantity']);
            $stmt->execute();
            $newId = $conn->insert_id;
            $logDetails = "Added Part ID $newId: '{$data['part_name']}' with Stock {$data['quantity']}";

        } elseif ($action === 'update') {
            $stmt = $conn->prepare("UPDATE inventory SET stock_level = ?, part_name = ? WHERE id = ?");
            // "isi" means Integer, String, Integer
            $stmt->bind_param("isi", $data['quantity'], $data['part_name'], $data['id']);
            $stmt->execute();
            $logDetails = "Updated Part ID {$data['id']}: Stock set to {$data['quantity']}";

        } elseif ($action === 'delete') {
            // Get name first for logging
            $stmtGet = $conn->prepare("SELECT part_name FROM inventory WHERE id = ?");
            $stmtGet->bind_param("i", $data['id']);
            $stmtGet->execute();
            $res = $stmtGet->get_result();
            $part = $res->fetch_assoc();
            $partName = $part ? $part['part_name'] : 'Unknown';

            // Delete
            $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->bind_param("i", $data['id']);
            $stmt->execute();
            $logDetails = "Deleted Part ID {$data['id']} ($partName)";
        }

        // 3. Audit Log
        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $logStmt->bind_param("is", $userId, $logDetails);
        $logStmt->execute();

        // Commit changes
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception($e->getMessage());
    }
}
?>