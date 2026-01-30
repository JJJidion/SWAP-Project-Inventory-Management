<?php
/**
 * ===================================================================================================
 * AMC REPORT AUDIT LOG PAGE -- For Logging all Report Creation attempts (Only Role: Admin can access)
 * ===================================================================================================
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

$pageTitle = 'Report Audit Log';

// Only Admin can access this page
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============================================================================
// PAGINATION SETTINGS
// ============================================================================

$recordsPerPage = 15;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// ============================================================================
// FETCH FILTER OPTIONS
// ============================================================================

$users = $pdo->query("SELECT DISTINCT generated_by FROM report_audit_log WHERE generated_by IS NOT NULL ORDER BY generated_by")->fetchAll();
$reportTypes = $pdo->query("SELECT DISTINCT report_type FROM report_audit_log WHERE report_type IS NOT NULL ORDER BY report_type")->fetchAll();

// ============================================================================
// BUILD QUERY WITH FILTERS
// ============================================================================

$whereConditions = [];
$params = [];

if (!empty($_GET['filter_user'])) {
    $whereConditions[] = "generated_by = :generated_by";
    $params[':generated_by'] = $_GET['filter_user'];
}

if (!empty($_GET['filter_type'])) {
    $whereConditions[] = "report_type = :report_type";
    $params[':report_type'] = $_GET['filter_type'];
}

if (!empty($_GET['filter_date_from'])) {
    $whereConditions[] = "DATE(claim_submission_time) >= :date_from";
    $params[':date_from'] = $_GET['filter_date_from'];
}

if (!empty($_GET['filter_date_to'])) {
    $whereConditions[] = "DATE(claim_submission_time) <= :date_to";
    $params[':date_to'] = $_GET['filter_date_to'];
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// ============================================================================
// COUNT TOTAL RECORDS
// ============================================================================

$countSql = "SELECT COUNT(*) as total FROM report_audit_log $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// ============================================================================
// FETCH RECORDS
// ============================================================================

$sql = "SELECT id, SHA256_ID, report_type, generated_by, claim_submission_time 
        FROM report_audit_log 
        $whereClause
        ORDER BY claim_submission_time DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll();

// ============================================================================
// REPORT TYPE DISPLAY NAMES
// ============================================================================

$reportTypeNames = [
    'parts_usage' => 'Parts Usage Report',
    'finance' => 'Finance Report',
    'inventory' => 'Inventory Report'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <title><?php echo $pageTitle; ?></title>
    <style>
        .audit-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h1 {
            color: #333;
            font-size: 1.5rem;
        }
        
        .btn-back {
            background: #333;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-back:hover {
            background: #555;
        }
        
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .info-box strong {
            color: #1565c0;
        }
        
        .summary-box {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            background: #f8f9fa;
            padding: 15px 25px;
            border-radius: 8px;
            text-align: center;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .summary-label {
            font-size: 0.85rem;
            color: #666;
        }
        
        /* Filter Form */
        .filter-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr) auto;
            gap: 15px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 5px;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-filter {
            background: #333;
            color: #fff;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .btn-filter:hover {
            background: #555;
        }
        
        .btn-clear {
            background: #6c757d;
            color: #fff;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-clear:hover {
            background: #545b62;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #333;
            color: #fff;
            padding: 12px 15px;
            text-align: left;
            font-size: 0.9rem;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        tr:hover {
            background: #f0f0f0;
        }
        
        .hash-cell {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.75rem;
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 4px;
            word-break: break-all;
            max-width: 400px;
        }
        
        .copy-btn {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.75rem;
            margin-left: 10px;
        }
        
        .copy-btn:hover {
            background: #545b62;
        }
        
        .copy-btn.copied {
            background: #28a745;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Report Type Badge */
        .report-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-parts_usage {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .badge-finance {
            background: #fff3e0;
            color: #e65100;
        }
        
        .badge-inventory {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background: #f0f0f0;
        }
        
        .pagination .active {
            background: #333;
            color: #fff;
            border-color: #333;
        }
        
        .pagination .disabled {
            color: #ccc;
            pointer-events: none;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .record-count {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        @media (max-width: 1200px) {
            .filter-form {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .summary-box {
                flex-direction: column;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .hash-cell {
                font-size: 0.65rem;
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="audit-container">
        <div class="page-header">
            <h1>🔐 Report Audit Log</h1>
            <a href="report_management.php" class="btn-back">← Back to Report Management</a>
        </div>
        
        <!-- Information Box -->
        <div class="info-box">
            <strong>About Report Integrity Verification</strong><br>
            Each generated report has a unique SHA-256 hash. This hash acts as a digital fingerprint - 
            if anyone modifies the PDF file, the hash will no longer match, indicating tampering.
            To verify a report's integrity, generate a SHA-256 hash of the PDF file and compare it 
            with the hash stored here.
        </div>
        
        <!-- Summary -->
        <div class="summary-box">
            <div class="summary-item">
                <div class="summary-value"><?php echo number_format($totalRecords); ?></div>
                <div class="summary-label">Total Reports Generated</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card">
            <h2>🔍 Filter Records</h2>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="filter_user">Generated By</label>
                    <select name="filter_user" id="filter_user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['generated_by']); ?>"
                                <?php echo (isset($_GET['filter_user']) && $_GET['filter_user'] === $user['generated_by']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['generated_by']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_type">Report Type</label>
                    <select name="filter_type" id="filter_type">
                        <option value="">All Types</option>
                        <?php foreach ($reportTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['report_type']); ?>"
                                <?php echo (isset($_GET['filter_type']) && $_GET['filter_type'] === $type['report_type']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($reportTypeNames[$type['report_type']] ?? $type['report_type']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_date_from">Date From</label>
                    <input type="date" name="filter_date_from" id="filter_date_from" 
                           value="<?php echo htmlspecialchars($_GET['filter_date_from'] ?? ''); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="filter_date_to">Date To</label>
                    <input type="date" name="filter_date_to" id="filter_date_to" 
                           value="<?php echo htmlspecialchars($_GET['filter_date_to'] ?? ''); ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter">Apply</button>
                    <a href="report_log.php" class="btn-clear">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Records Table -->
        <div class="card">
            <h2>📋 Generated Reports</h2>
            <p class="record-count">
                Showing <?php echo count($records); ?> of <?php echo number_format($totalRecords); ?> records
                (Page <?php echo $currentPage; ?> of <?php echo max(1, $totalPages); ?>)
            </p>
            
            <?php if (empty($records)): ?>
                <div class="no-data">
                    <p>No reports have been generated yet.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 150px;">Report Type</th>
                                <th style="width: 120px;">Generated By</th>
                                <th>SHA-256 Hash</th>
                                <th style="width: 170px;">Generated At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td class="text-center"><?php echo $record['id']; ?></td>
                                    <td>
                                        <span class="report-badge badge-<?php echo htmlspecialchars($record['report_type'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($reportTypeNames[$record['report_type']] ?? $record['report_type'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['generated_by'] ?? '-'); ?></td>
                                    <td>
                                        <span class="hash-cell" id="hash-<?php echo $record['id']; ?>">
                                            <?php echo htmlspecialchars($record['SHA256_ID']); ?>
                                        </span>
                                        <button class="copy-btn" onclick="copyHash(<?php echo $record['id']; ?>)">
                                            📋 Copy
                                        </button>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($record['claim_submission_time'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                        // Build query string for pagination links
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = http_build_query($queryParams);
                        $queryString = $queryString ? "&$queryString" : "";
                        ?>
                        
                        <!-- Previous -->
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?><?php echo $queryString; ?>">« Prev</a>
                        <?php else: ?>
                            <span class="disabled">« Prev</span>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        if ($startPage > 1): ?>
                            <a href="?page=1<?php echo $queryString; ?>">1</a>
                            <?php if ($startPage > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif;
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $queryString; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor;
                        
                        if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $totalPages; ?><?php echo $queryString; ?>"><?php echo $totalPages; ?></a>
                        <?php endif; ?>
                        
                        <!-- Next -->
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?><?php echo $queryString; ?>">Next »</a>
                        <?php else: ?>
                            <span class="disabled">Next »</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Verification Instructions -->
        <div class="card">
            <h2>🔍 How to Verify a Report</h2>
            <ol style="margin-left: 20px; line-height: 1.8;">
                <li>Download the PDF report you want to verify</li>
                <li>Generate a SHA-256 hash of the file:
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        <li><strong>Windows (PowerShell):</strong> <code>Get-FileHash -Algorithm SHA256 report.pdf</code></li>
                        <li><strong>macOS/Linux:</strong> <code>shasum -a 256 report.pdf</code></li>
                        <li><strong>Online:</strong> <a href="https://emn178.github.io/online-tools/sha256_checksum.html">Tap here for an online SHA-256 hash generator</a></li>
                    </ul>
                </li>
                <li>Compare the generated hash with the hash stored in this audit log</li>
                <li>If the hashes match, the report has not been modified</li>
            </ol>
        </div>
    </div>
    
    <?php require_once '../includes/footer.php'; ?>
    
    <script>
        function copyHash(id) {
            const hashElement = document.getElementById('hash-' + id);
            const hashText = hashElement.textContent.trim();
            
            navigator.clipboard.writeText(hashText).then(() => {
                // Change button text temporarily
                const btn = event.target;
                btn.textContent = '✓ Copied!';
                btn.classList.add('copied');
                
                setTimeout(() => {
                    btn.textContent = '📋 Copy';
                    btn.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                alert('Failed to copy hash. Please select and copy manually.');
            });
        }
    </script>
</body>
</html>