<?php
// utils/inventory_logic.php

/**
 * Fetch inventory items, optionally filtering by a search term.
 * Returns an array of items.
 */
function getInventory($pdo, $search = '') {
    try {
        if (!empty($search)) {
            // Secure search by name or exact ID
            $stmt = $pdo->prepare("SELECT * FROM inventory WHERE part_name LIKE :search OR id = :id ORDER BY id ASC");
            $stmt->execute([':search' => "%$search%", ':id' => $search]);
        } else {
            $stmt = $pdo->query("SELECT * FROM inventory ORDER BY id ASC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Return empty array on error to prevent page crash
        return [];
    }
}

/**
 * Handles Add, Update, and Delete operations with Audit Logging.
 * This is the function you target with PHPUnit.
 */
function manageInventory($pdo, $action, $data, $userId) {
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
        $pdo->beginTransaction();
        $logDetails = "";

        // 2. Execution Logic
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO inventory (part_name, stock_level) VALUES (:name, :qty)");
            $stmt->execute([':name' => $data['part_name'], ':qty' => $data['quantity']]);
            $newId = $pdo->lastInsertId();
            $logDetails = "Added Part ID $newId: '{$data['part_name']}' with Stock {$data['quantity']}";

        } elseif ($action === 'update') {
            $stmt = $pdo->prepare("UPDATE inventory SET stock_level = :qty, part_name = :name WHERE id = :id");
            $stmt->execute([':qty' => $data['quantity'], ':name' => $data['part_name'], ':id' => $data['id']]);
            $logDetails = "Updated Part ID {$data['id']}: Stock set to {$data['quantity']}";

        } elseif ($action === 'delete') {
            // Get name before deleting for the log
            $stmtGet = $pdo->prepare("SELECT part_name FROM inventory WHERE id = ?");
            $stmtGet->execute([$data['id']]);
            $part = $stmtGet->fetch();
            $partName = $part ? $part['part_name'] : 'Unknown';

            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = :id");
            $stmt->execute([':id' => $data['id']]);
            $logDetails = "Deleted Part ID {$data['id']} ($partName)";
        }

        // 3. Traceability Logic (Audit Log)
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (:uid, :action, NOW())");
        $logStmt->execute([':uid' => $userId, ':action' => $logDetails]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new Exception($e->getMessage());
    }
}
?>