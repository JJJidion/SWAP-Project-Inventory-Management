<?php
// audit/audit_logger.php

require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

/**
 * Convert system roles into user-friendly display roles
 * (Used ONLY for audit logs)
 */
function getDisplayRole(string $role): string
{
    return match ($role) {
        'Admin' => 'Inventory Manager',
        'User'  => 'Employee',
        default => $role
    };
}

/**
 * Central Audit Logger
 */
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
    // Resolve IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    if ($ip_address === '::1') {
        $ip_address = '127.0.0.1';
    }

    // Resolve User Agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    // ✅ Convert role for DISPLAY PURPOSES ONLY
    $displayRole = getDisplayRole($role);

    // Prepare statement
    $stmt = $conn->prepare("
        INSERT INTO audit_logs
        (user_id, username, role, action, entity, entity_id, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Bind parameters
    $stmt->bind_param(
        "issssisss",
        $user_id,
        $username,
        $displayRole,
        $action,
        $entity,
        $entity_id,
        $description,
        $ip_address,
        $user_agent
    );

    // Execute & close
    $stmt->execute();
    $stmt->close();
}