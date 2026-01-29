<?php
require_once __DIR__ . '/../config/config.php';

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

$stmt = $conn->prepare("
    SELECT id, created_at, username, role, action, entity, description, ip_address
    FROM audit_logs
    WHERE entity = 'auth'
      AND id > ?
    ORDER BY id ASC
");
$stmt->bind_param("i", $lastId);
$stmt->execute();

$result = $stmt->get_result();
$logs = [];

while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

echo json_encode($logs);