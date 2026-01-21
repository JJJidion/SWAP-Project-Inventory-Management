<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// SECURITY CHECK: Ensure the user is actually logged in AND is an User
// If they are not logged in OR they are not an User, kick them out.
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "User") {
    header("Location: login.php");
    exit;
}



$pageTitle = 'User Dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory AI Search</title>
    <style>
        /* Modern Clean Look */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; padding-top: 50px; }
        .container { width: 100%; max-width: 800px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        
        h2 { text-align: center; color: #333; margin-bottom: 20px; }

        /* Role Switcher */
        .role-selector { background: #e9ecef; padding: 10px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; }

        /* Chat Area */
        .chat-box { height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 8px; background: #fafafa; }
        .message { padding: 10px 15px; margin-bottom: 10px; border-radius: 15px; max-width: 80%; line-height: 1.5; }
        .user { background: #007bff; color: white; align-self: flex-end; margin-left: auto; border-bottom-right-radius: 2px; }
        .bot { background: #e4e6eb; color: black; align-self: flex-start; margin-right: auto; border-bottom-left-radius: 2px; }

        /* Input Area */
        .input-group { display: flex; gap: 10px; }
        input[type="text"] { flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 20px; outline: none; }
        button { padding: 10px 25px; background: #28a745; color: white; border: none; border-radius: 20px; cursor: pointer; font-weight: bold; }
        button:hover { background: #218838; }

        /* Data Table Styling */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9em; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .data-table th { background-color: #007bff; color: white; }
        .badge-active { background: #d4edda; color: #155724; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
        .badge-obsolete { background: #f8d7da; color: #721c24; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
    </style>
</head>
<body>

<div class="container">
    <h2>🤖 AI Inventory Search</h2>

    <div class="role-selector">
        <span><strong>Current Role:</strong></span>
        <select id="userRole">
            <option value="guest">Guest (Restricted View)</option>
            <option value="admin">Admin (Full Access)</option>
        </select>
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
        const role = document.getElementById('userRole').value;
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
            // 3. Call Python API (Ensure port 5000 is running!)
            const response = await fetch('http://127.0.0.1:5000/ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: text, role: role })
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
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;
                
                data.answer.forEach(item => {
                    // Badge Logic
                    let statusClass = item.status === 'active' ? 'badge-active' : 'badge-obsolete';
                    
                    tableHtml += `
                        <tr>
                            <td>${item.part_name}</td>
                            <td>${item.category}</td>
                            <td>${item.stock_level}</td>
                            <td><span class="${statusClass}">${item.status}</span></td>
                        </tr>`;
                });
                
                tableHtml += `</tbody></table>`;
                
                // Append SQL for debugging (Optional)
                tableHtml += `<div style="font-size:0.8em; color:#888; margin-top:5px;">🔍 Executed SQL: <i>${data.sql_used}</i></div>`;
                
                loader.innerHTML = tableHtml;
            } else {
                loader.innerHTML = "No matching records found.";
            }

        } catch (err) {
            document.getElementById(loadingId).innerHTML = "❌ Connection Error. Is 'app.py' running?";
        }
        
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function handleEnter(e) {
        if (e.key === 'Enter') sendMessage();
    }
</script>

</body>
</html>