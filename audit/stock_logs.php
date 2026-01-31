<?php
// audit/stock_logs.php

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/audit_logger.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Access denied');
}
//LOG STOCK LOG DASHBOARD ACCESS
audit_log(
    $conn,
    $_SESSION['user_id'],
    $_SESSION['username'],
    $_SESSION['role'],
    'VIEW_STOCK_LOGS',
    'audit',
    null,
    'Admin accessed stock audit logs dashboard'
);


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Stock Audit Logs</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f7fa;
}

h2 {
    text-align: center;
    margin-bottom: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

th, td {
    padding: 8px;
    border: 1px solid #ddd;
    font-size: 14px;
    text-align: left;
}

th {
    background: #222;
    color: white;
}

/* Row highlights */
tr.create { background: #e8f5e9; }
tr.update { background: #fff8e1; }
tr.delete { background: #fdecea; }

/* Role column */
td:nth-child(3) {
    font-weight: 600;
}
</style>
</head>

<body>

<h2>📦 Inventory Stock Audit Logs</h2>

<table>
<thead>
<tr>
    <th>Time</th>
    <th>User</th>
    <th>Role</th>
    <th>Action</th>
    <th>Description</th>
    <th>IP</th>
</tr>
</thead>
<tbody id="logBody"></tbody>
</table>

<script>
let lastId = 0;

function loadLogs() {
    fetch(`fetch_stock_logs.php?last_id=${lastId}`)
        .then(res => res.json())
        .then(logs => {
            logs.forEach(log => {
                lastId = log.id;

                let rowClass = '';
                if (log.action.includes('CREATE')) rowClass = 'create';
                if (log.action.includes('UPDATE')) rowClass = 'update';
                if (log.action.includes('DELETE')) rowClass = 'delete';

                const row = `
                    <tr class="${rowClass}">
                        <td>${log.created_at}</td>
                        <td>${log.username}</td>
                        <td>${log.role}</td>
                        <td>${log.action}</td>
                        <td>${log.description}</td>
                        <td>${log.ip_address}</td>
                    </tr>
                `;

                document
                    .getElementById('logBody')
                    .insertAdjacentHTML('afterbegin', row);
            });
        })
        .catch(() => {});
}

// Auto-refresh every 3 seconds
setInterval(loadLogs, 3000);
</script>

</body>
</html>