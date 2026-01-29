<?php
// audit/audit_logger.php

require_once __DIR__ . '/../config/config.php';

function audit_log(
    mysqli $conn,
    int $user_id,
    string $username,
    string $role,
    string $action,
    string $entity,
    ?int $entity_id = null,
    string $description = ''
) {
    // ✅ Assign to variables (REQUIRED for bind_param)
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

if ($ip_address === '::1') {
    $ip_address = '127.0.0.1';
}
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $stmt = $conn->prepare("
        INSERT INTO audit_logs
        (user_id, username, role, action, entity, entity_id, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issssisss",
        $user_id,
        $username,
        $role,
        $action,
        $entity,
        $entity_id,
        $description,
        $ip_address,
        $user_agent
    );

    $stmt->execute();
    $stmt->close();
}