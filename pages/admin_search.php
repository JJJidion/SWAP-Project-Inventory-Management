<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// ---------------------------------------------------------
// 1. SECURITY: Page Access Control
// ---------------------------------------------------------
// If they are not logged in OR they are not an Admin/Manager, kick them out.
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Inventory Manager' && $_SESSION['role'] !== 'Admin')) {

    echo "<div class='container'><h3>⛔ Access Denied.</h3></div>";
    exit();
}

// ---------------------------------------------------------
// 2. SECURE PROXY (PHP talks to Python, not JS)
// ---------------------------------------------------------
// This block runs ONLY when JavaScript sends a search request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    
    // Get the User's Query from JS
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    $userQuery = $input['query'] ?? '';

    // 🛡️ RATE LIMITING: Allow only 1 search per second
    $limit_time = 8.0; // Seconds
    if (isset($_SESSION['last_search_time'])) {
    $time_since_last = microtime(true) - $_SESSION['last_search_time'];
    if ($time_since_last < $limit_time) {
        // Return error immediately without calling Python
        echo json_encode(["error" => "⏳ Too fast! Please wait a moment."]);
        exit();
    }
    }
    // Update the last search timestamp
    $_SESSION['last_search_time'] = microtime(true);

    // SECURELY Determine Role from Session (User cannot touch this)
    // We ignore whatever 'role' JS might have tried to send.
    $realRole = 'employee'; 
    if ($_SESSION['role'] === 'Inventory Manager' || $_SESSION['role'] === 'Admin') {
        $realRole = 'admin';
    }

    // Prepare data for Python
    $payload = json_encode([
        "query" => $userQuery,
        "role" => $realRole, // <--- We send the Trusted Role here
        "username" => $_SESSION['username']
    ]);

    // Send to Python Backend (cURL)
    $ch = curl_init('http://127.0.0.1:5000/ask');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo json_encode(["error" => "AI Server Error: " . curl_error($ch)]);
    } else {
        echo $response; // Send Python's answer back to JS
    }
    curl_close($ch);
    exit(); // 🛑 CRITICAL: Stop here so we don't send the HTML page again
}

$pageTitle = 'AI Search';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory AI Search</title>
    <link rel="stylesheet" href="../css/search.css">
</head>
<body>
    <nav class="navbar">
    <div class="container">
        <h1>Inventory Manager</h1>
        <ul>
            <?php if (isset($_SESSION["username"])): ?>
                <?php if ($_SESSION["role"] === "User"): ?>
                    <li><a href="<?php echo BASE_URL; ?>/pages/index_user.php">Home</a></li>
                <?php elseif ($_SESSION["role"] === "Admin"): ?>
                    <li><a href="<?php echo BASE_URL; ?>/pages/index_admin.php">Home</a></li>
                <?php endif; ?>
                <li><a href="<?php echo BASE_URL; ?>/pages/profile.php">Profile</a></li>
                <li><a href="<?php echo BASE_URL; ?>/pages/logout.php">Log Out</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<div class="searchcontain">
    <h2>🤖 AI Inventory Search</h2>

    <div class="role">
        <strong>Current Role: <?php echo htmlspecialchars($_SESSION['role']); ?></strong>
    </div>

    <div class="chat-box" id="chatBox">
        <div class="message bot">Hello! I can search the <b>inventory</b> table. Try asking: "Show me all active cables."</div>
    </div>

    <div class="input-group">
        <input type="text" id="userInput" placeholder="Ask about parts, stock, or status..." onkeypress="handleEnter(event)">
        <button onclick="sendMessage()">Search</button>
    </div>
</div>

<script>
    async function sendMessage() {
        const input = document.getElementById('userInput');
        const chatBox = document.getElementById('chatBox');
        const text = input.value.trim();

        if (!text) return;

        // 1. Add User Message
        chatBox.innerHTML += `<div class="message user">${text}</div>`;
        input.value = '';
        chatBox.scrollTop = chatBox.scrollHeight;

        // 2. Add Loading Message
        const loadingId = "load-" + Date.now();
        chatBox.innerHTML += `<div class="message bot" id="${loadingId}">Searching database...</div>`;

        try {
            // 3. SECURE REQUEST
            // We fetch the CURRENT PHP PAGE (window.location.href)
            // We do NOT send the role. PHP handles it.
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: text }) 
            });

            const data = await response.json();
            const loader = document.getElementById(loadingId);

            // 4. Handle Error
            if (data.error) {
                loader.innerHTML = "⚠️ Error: " + data.error;
                return;
            }

            // 5. Build HTML Table for Results
            if (Array.isArray(data.answer) && data.answer.length > 0) {
                let tableHtml = `<table class="data-table">
                    <thead>
                        <tr>
                            <th>Part Name</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;
                
                data.answer.forEach(item => {
                    let statusClass = item.status === 'active' ? 'badge-active' : 'badge-obsolete';
                    tableHtml += `
                        <tr>
                            <td>${item.part_name}</td>
                            <td>${item.category}</td>
                            <td>${item.supplier}</td>
                            <td>${item.stock_level}</td>
                            <td><span class="${statusClass}">${item.status}</span></td>
                        </tr>`;
                });
                
                tableHtml += `</tbody></table>`;
                // Optional: Show Debug SQL
                tableHtml += `<div style="font-size:0.8em; color:#888; margin-top:5px;">🔍 Executed SQL: <i>${data.sql_used}</i></div>`;
                loader.innerHTML = tableHtml;
            } else {
                loader.innerHTML = typeof data.answer === 'string' ? data.answer : "No matching records found.";
            }

        } catch (err) {
            document.getElementById(loadingId).innerHTML = "❌ Connection Error.";
        }
        
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function handleEnter(e) {
        if (e.key === 'Enter') sendMessage();
    }
</script>

</body>
</html>