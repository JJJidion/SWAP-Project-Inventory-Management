<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Inventory Manager'])) {
    die('Access denied');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Live Audit Logs</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f7fa;
}
h2 {
    text-align: center;
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
}
th {
    background: #222;
    color: white;
}
tr.login { background: #e8f5e9; }
tr.delete { background: #fdecea; }
tr.update { background: #fff8e1; }
</style>
</head>

<body>

<h2>🔴 Live Audit Log Monitor</h2>

<table>
<thead>
<tr>
    <th>Time</th>
    <th>User</th>
    <th>Role</th>
    <th>Action</th>
    <th>Entity</th>
    <th>Description</th>
    <th>IP</th>
</tr>
</thead>
<tbody id="auditBody"></tbody>
</table>

<script>
let lastId = 0;

function loadLogs() {
    fetch(`fetch_logs.php?last_id=${lastId}`)
        .then(res => res.json())
        .then(logs => {
            logs.forEach(log => {
                lastId = log.id;

                let rowClass = '';
                if (log.action.includes('LOGIN')) rowClass = 'login';
                if (log.action.includes('DELETE')) rowClass = 'delete';
                if (log.action.includes('UPDATE')) rowClass = 'update';

                const row = `
                    <tr class="${rowClass}">
                        <td>${log.created_at}</td>
                        <td>${log.username}</td>
                        <td>${log.role}</td>
                        <td>${log.action}</td>
                        <td>${log.entity}</td>
                        <td>${log.description}</td>
                        <td>${log.ip_address}</td>
                    </tr>
                `;

                document
                    .getElementById('auditBody')
                    .insertAdjacentHTML('afterbegin', row);
            });
        })
        .catch(() => {});
}

setInterval(loadLogs, 3000); // every 3 seconds
</script>

</body>
</html>