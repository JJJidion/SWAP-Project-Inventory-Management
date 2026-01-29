<?php
// utils/inventory_logic.php

require_once __DIR__ . '/../audit/audit_logger.php';

/**
 * Fetch inventory items (with optional search)
 */
function getInventory($conn, $search = '') {
    $items = [];
    try {
        if (!empty($search)) {
            $stmt = $conn->prepare(
                "SELECT * FROM inventory
                 WHERE (part_name LIKE ? OR category LIKE ? OR id = ?)
                   AND is_deleted = 0
                 ORDER BY id ASC"
            );
            $searchTerm = "%$search%";
            $stmt->bind_param("sss", $searchTerm, $searchTerm, $search);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query(
                "SELECT * FROM inventory
                 WHERE is_deleted = 0
                 ORDER BY id ASC"
            );
        }

        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        return $items;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Fetch recent audit logs (used for dashboard widgets if needed)
 */
function getRecentLogs($conn, $limit = 50) {
    $logs = [];
    $sql = "
        SELECT a.action, a.created_at, u.username
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (empty($row['username'])) {
            $row['username'] = 'System/Unknown';
        }
        $logs[] = $row;
    }
    return $logs;
}

/**
 * Central inventory management logic
 * Handles add / update / delete with audit logging
 */
function manageInventory($conn, $action, $data, $userId) {

    // --- Validation ---
    if ($action === 'add' || $action === 'update') {
        if ($data['quantity'] < 0) {
            throw new Exception("Stock cannot be negative.");
        }
        if ($data['cost_per_part'] < 0) {
            throw new Exception("Price cannot be negative.");
        }
        if (empty($data['part_name'])) {
            throw new Exception("Part Name is required.");
        }
    }

    try {
        $conn->begin_transaction();

        // --- ADD ITEM ---
        if ($action === 'add') {

            $stmt = $conn->prepare(
                "INSERT INTO inventory
                 (part_name, category, supplier, stock_level, cost_per_part)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "sssid",
                $data['part_name'],
                $data['category'],
                $data['supplier'],
                $data['quantity'],
                $data['cost_per_part']
            );
            $stmt->execute();
            $newId = $conn->insert_id;

            audit_log(
                $conn,
                $userId,
                $_SESSION['username'],
                $_SESSION['role'],
                'STOCK_CREATE',
                'inventory',
                $newId,
                "Created item '{$data['part_name']}' (Qty: {$data['quantity']}, Value: \${$data['cost_per_part']})"
            );

        // --- UPDATE ITEM ---
        } elseif ($action === 'update') {

            // Fetch old stock level for audit clarity
            $oldStmt = $conn->prepare(
                "SELECT stock_level FROM inventory WHERE id = ?"
            );
            $oldStmt->bind_param("i", $data['id']);
            $oldStmt->execute();
            $oldRow = $oldStmt->get_result()->fetch_assoc();
            $oldQty = $oldRow['stock_level'] ?? 'Unknown';
            $oldStmt->close();

            $stmt = $conn->prepare(
                "UPDATE inventory
                 SET stock_level = ?, part_name = ?, category = ?, supplier = ?, cost_per_part = ?
                 WHERE id = ?"
            );
            $stmt->bind_param(
                "isssdi",
                $data['quantity'],
                $data['part_name'],
                $data['category'],
                $data['supplier'],
                $data['cost_per_part'],
                $data['id']
            );
            $stmt->execute();

            audit_log(
                $conn,
                $userId,
                $_SESSION['username'],
                $_SESSION['role'],
                'STOCK_UPDATE',
                'inventory',
                $data['id'],
                "Updated '{$data['part_name']}' stock from {$oldQty} to {$data['quantity']}"
            );

        // --- DELETE ITEM (SOFT DELETE) ---
        } elseif ($action === 'delete') {

            $stmtGet = $conn->prepare(
                "SELECT part_name FROM inventory WHERE id = ?"
            );
            $stmtGet->bind_param("i", $data['id']);
            $stmtGet->execute();
            $part = $stmtGet->get_result()->fetch_assoc();
            $partName = $part ? $part['part_name'] : 'Unknown';
            $stmtGet->close();

            $stmt = $conn->prepare(
                "UPDATE inventory SET is_deleted = 1 WHERE id = ?"
            );
            $stmt->bind_param("i", $data['id']);
            $stmt->execute();

            audit_log(
                $conn,
                $userId,
                $_SESSION['username'],
                $_SESSION['role'],
                'STOCK_DELETE',
                'inventory',
                $data['id'],
                "Deleted item '{$partName}'"
            );
        }

        $conn->commit();
        return true;

    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception($e->getMessage());
    }
}