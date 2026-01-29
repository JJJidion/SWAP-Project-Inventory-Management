<?php
/**
 * Parts Usage Log (Admin View)
 * Display-only page showing all parts usage claims submitted by users
 * Only accessible by Admin users
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

$pageTitle = 'Parts Usage Log';

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
// PAGINATION SETTINGS (For Display Settings)
// ============================================================================

$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// ============================================================================
// FETCH FILTER OPTIONS
// ============================================================================

$users = $pdo->query("SELECT DISTINCT username FROM parts_log ORDER BY username")->fetchAll();
$projects = $pdo->query("SELECT DISTINCT project_id FROM parts_log WHERE project_id IS NOT NULL AND project_id != '' ORDER BY project_id")->fetchAll();
$parts = $pdo->query("SELECT DISTINCT part_used FROM parts_log ORDER BY part_used")->fetchAll();

// ============================================================================
// BUILD QUERY WITH FILTERS
// ============================================================================

$whereConditions = [];
$params = [];

// Apply filters if set
if (!empty($_GET['filter_user'])) {
    $whereConditions[] = "pl.username = :username";
    $params[':username'] = $_GET['filter_user'];
}

if (!empty($_GET['filter_project'])) {
    $whereConditions[] = "pl.project_id = :project_id";
    $params[':project_id'] = $_GET['filter_project'];
}

if (!empty($_GET['filter_part'])) {
    $whereConditions[] = "pl.part_used = :part_used";
    $params[':part_used'] = $_GET['filter_part'];
}

if (!empty($_GET['filter_date_from'])) {
    $whereConditions[] = "pl.date_used >= :date_from";
    $params[':date_from'] = $_GET['filter_date_from'];
}

if (!empty($_GET['filter_date_to'])) {
    $whereConditions[] = "pl.date_used <= :date_to";
    $params[':date_to'] = $_GET['filter_date_to'];
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// ============================================================================
// COUNT TOTAL RECORDS
// ============================================================================

$countSql = "SELECT COUNT(*) as total FROM parts_log pl $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// ============================================================================
// FETCH RECORDS
// ============================================================================

$sql = "SELECT 
            pl.id,
            pl.username,
            pl.part_used,
            pl.amount_used,
            pl.date_used,
            pl.project_id,
            pl.comments,
            pl.claim_submission_time,
            COALESCE(i.cost_per_part, 0) as cost_per_part,
            (pl.amount_used * COALESCE(i.cost_per_part, 0)) as total_cost
        FROM parts_log pl
        LEFT JOIN inventory i ON pl.part_used = i.part_name
        $whereClause
        ORDER BY pl.claim_submission_time DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// Bind all filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll();

// ============================================================================
// CALCULATE SUMMARY STATISTICS
// ============================================================================

$summarySql = "SELECT 
                COUNT(*) as total_claims,
                SUM(pl.amount_used) as total_parts_used,
                SUM(pl.amount_used * COALESCE(i.cost_per_part, 0)) as total_value
               FROM parts_log pl
               LEFT JOIN inventory i ON pl.part_used = i.part_name
               $whereClause";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <title><?php echo $pageTitle; ?></title>
    <style>
    /* Styles are here to prevent conflict with other pages */
        .log-container {
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
        
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        /* Summary Stats */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        
        /* Filter Form */
        .filter-form {
            display: grid;
            grid-template-columns: repeat(5, 1fr) auto;
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
            padding: 12px 10px;
            text-align: left;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        tr:hover {
            background: #f0f0f0;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .comment-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .comment-cell:hover {
            white-space: normal;
            overflow: visible;
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
        
        .info-text {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        @media (max-width: 1200px) {
            .filter-form {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="log-container">
        <div class="page-header">
            <h1>📋 Parts Usage Log</h1>
        </div>
        
        <!-- Summary Statistics -->
        <div class="summary-stats">
            <div class="stat-box">
                <div class="stat-value"><?php echo number_format($summary['total_claims'] ?? 0); ?></div>
                <div class="stat-label">Total Claims</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo number_format($summary['total_parts_used'] ?? 0); ?></div>
                <div class="stat-label">Total Parts Used</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">$<?php echo number_format($summary['total_value'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Value</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card">
            <h2>🔍 Filter Records</h2>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="filter_user">User</label>
                    <select name="filter_user" id="filter_user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['username']); ?>"
                                <?php echo (isset($_GET['filter_user']) && $_GET['filter_user'] === $user['username']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_project">Project</label>
                    <select name="filter_project" id="filter_project">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo htmlspecialchars($project['project_id']); ?>"
                                <?php echo (isset($_GET['filter_project']) && $_GET['filter_project'] === $project['project_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['project_id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_part">Part</label>
                    <select name="filter_part" id="filter_part">
                        <option value="">All Parts</option>
                        <?php foreach ($parts as $part): ?>
                            <option value="<?php echo htmlspecialchars($part['part_used']); ?>"
                                <?php echo (isset($_GET['filter_part']) && $_GET['filter_part'] === $part['part_used']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($part['part_used']); ?>
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
                    <a href="parts_submission.php" class="btn-clear" title="Submit a Parts Use Claim">Claim</a>
                </div>
            </form>
        </div>
        
        <!-- Records Table -->
        <div class="card">
            <h2>📝 Usage Records</h2>
            <p class="info-text">
                Showing <?php echo count($records); ?> of <?php echo number_format($totalRecords); ?> records 
                (Page <?php echo $currentPage; ?> of <?php echo max(1, $totalPages); ?>)
            </p>
            
            <?php if (empty($records)): ?>
                <div class="no-data">
                    <p>No records found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Part Used</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Cost</th>
                                <th class="text-right">Total Cost</th>
                                <th>Date Used</th>
                                <th>Project</th>
                                <th>Comments</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo $record['id']; ?></td>
                                    <td><?php echo htmlspecialchars($record['username']); ?></td>
                                    <td><?php echo htmlspecialchars($record['part_used']); ?></td>
                                    <td class="text-center"><?php echo $record['amount_used']; ?></td>
                                    <td class="text-right">$<?php echo number_format($record['cost_per_part'], 2); ?></td>
                                    <td class="text-right">$<?php echo number_format($record['total_cost'], 2); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($record['date_used'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['project_id'] ?? '-'); ?></td>
                                    <td class="comment-cell" title="<?php echo htmlspecialchars($record['comments'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($record['comments'] ?? '-'); ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($record['claim_submission_time'])); ?></td>
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
    </div>
    
    <?php require_once '../includes/footer.php'; ?>
</body>
</html>