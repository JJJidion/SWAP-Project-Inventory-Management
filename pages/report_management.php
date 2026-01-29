<?php
/**
 * ============================================================================
 * AMC REPORT MANAGEMENT PAGE
 * ============================================================================
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/dompdf/autoload.inc.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

// ============================================================================
// CONFIGURATION CONSTANTS
// ============================================================================

define('COOLDOWN_SECONDS', 30);           // Cooldown between report generations
define('LOW_STOCK_THRESHOLD', 10);        // Stock level considered "low"
define('RESTOCK_QUANTITY', 10);           // Recommended restock amount (Keeping it as such for simplicity)
define('REPORTS_QUEUE_DIR', __DIR__ . '/../reports/queue/');  // Queue directory
define('REPORTS_OUTPUT_DIR', __DIR__ . '/../reports/generated/'); // Output directory

// ============================================================================
// COOLDOWN TIMER FUNCTIONS (Misuse Case 5.2 Mitigation)
// ============================================================================

/**
 * Check if user is currently in cooldown period
 * 
 * @return int - Seconds remaining in cooldown (0 if ready)
 */
function checkCooldown(): int {
    if (!isset($_SESSION['last_report_time'])) {
        return 0;
    }
    
    $elapsed = time() - $_SESSION['last_report_time'];
    $remaining = COOLDOWN_SECONDS - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Set cooldown timestamp after successful report generation
 */
function setCooldown(): void {
    $_SESSION['last_report_time'] = time();
}

/**
 * Get cooldown status message for display
 * 
 * @return array - ['on_cooldown' => bool, 'seconds' => int, 'message' => string]
 */
function getCooldownStatus(): array {
    $remaining = checkCooldown();
    
    if ($remaining > 0) {
        return [
            'on_cooldown' => true,
            'seconds' => $remaining,
            'message' => "Please wait {$remaining} seconds before generating another report."
        ];
    }
    
    return [
        'on_cooldown' => false,
        'seconds' => 0,
        'message' => ''
    ];
}

// ============================================================================
// BACKGROUND PROCESSING QUEUE
// ============================================================================

/**
 * ReportQueue class handles queuing report generation requests
 * This prevents the server from being overwhelmed by multiple simultaneous requests 
 */
class ReportQueue {
    private $queueDir;
    private $outputDir;
    
    public function __construct() {
        $this->queueDir = REPORTS_QUEUE_DIR;
        $this->outputDir = REPORTS_OUTPUT_DIR;
        
        // Create directories if they don't exist
        if (!is_dir($this->queueDir)) {
            mkdir($this->queueDir, 0755, true);
        }
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }
    
    /**
     * Add a report request to the queue
     */
    public function addToQueue(array $params, string $username): string {
        $queueId = uniqid('rpt_', true);
        
        $queueItem = [
            'id' => $queueId,
            'params' => $params,
            'username' => $username,
            'status' => 'pending',
            'created_at' => time(),
            'completed_at' => null,
            'output_file' => null,
            'error' => null
        ];
        
        $queueFile = $this->queueDir . $queueId . '.json';
        file_put_contents($queueFile, json_encode($queueItem, JSON_PRETTY_PRINT));
        
        return $queueId;
    }
    
    /**
     * Get queue item status
     */
    public function getStatus(string $queueId): ?array {
        $queueFile = $this->queueDir . $queueId . '.json';
        
        if (!file_exists($queueFile)) {
            return null;
        }
        
        return json_decode(file_get_contents($queueFile), true);
    }
    
    /**
     * Update queue item status
     */
    public function updateStatus(string $queueId, array $updates): void {
        $queueFile = $this->queueDir . $queueId . '.json';
        
        if (file_exists($queueFile)) {
            $item = json_decode(file_get_contents($queueFile), true);
            $item = array_merge($item, $updates);
            file_put_contents($queueFile, json_encode($item, JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Process the next pending item in queue (ONE at a time)
     * Also logs the report to report_audit_log table
     */
    public function processNext(PDO $pdo): ?array {
        $files = glob($this->queueDir . '*.json');
        
        // Sort by creation time (oldest first)
        usort($files, function($a, $b) {
            $aData = json_decode(file_get_contents($a), true);
            $bData = json_decode(file_get_contents($b), true);
            return $aData['created_at'] - $bData['created_at'];
        });
        
        // Find first pending item
        foreach ($files as $file) {
            $item = json_decode(file_get_contents($file), true);
            
            if ($item['status'] === 'pending') {
                $this->updateStatus($item['id'], ['status' => 'processing']);
                
                try {
                    $generator = new AMCReportGenerator($pdo);
                    $generator->setReportType($item['params']['report_type'])
                              ->setDateRange($item['params']['start_date'], $item['params']['end_date'])
                              ->setFilters($item['params']['filters'] ?? [])
                              ->setGeneratedBy($item['username']);
                    
                    $result = $generator->generate();
                    
                    $outputFile = $this->outputDir . $result['filename'];
                    file_put_contents($outputFile, $result['pdf']);
                    
                    // Log to report_audit_log table for integrity verification
                    $this->logReportToDatabase($pdo, $result['hash'], $item['params']['report_type'], $item['username']);
                    
                    $this->updateStatus($item['id'], [
                        'status' => 'completed',
                        'completed_at' => time(),
                        'output_file' => $result['filename'],
                        'hash' => $result['hash']
                    ]);
                    
                    $item['status'] = 'completed';
                    $item['output_file'] = $result['filename'];
                    $item['hash'] = $result['hash'];
                    
                } catch (Exception $e) {
                    $this->updateStatus($item['id'], [
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ]);
                    
                    $item['status'] = 'failed';
                    $item['error'] = $e->getMessage();
                }
                
                return $item;
            }
        }
        
        return null;
    }
    
    /**
     * Log report hash to database for audit/integrity verification
     * 
     * @param PDO $pdo - Database connection
     * @param string $hash - SHA256 hash of the generated PDF
     * @param string $reportType - Type of report (parts_usage, finance, inventory)
     * @param string $generatedBy - Username of user who generated the report
     */
    private function logReportToDatabase(PDO $pdo, string $hash, string $reportType, string $generatedBy): void {
        $sql = "INSERT INTO report_audit_log (SHA256_ID, report_type, generated_by, claim_submission_time) 
                VALUES (:hash, :report_type, :generated_by, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':hash' => $hash,
            ':report_type' => $reportType,
            ':generated_by' => $generatedBy
        ]);
    }
    
    /**
     * Get count of pending items in queue
     */
    public function getPendingCount(): int {
        $count = 0;
        $files = glob($this->queueDir . '*.json');
        
        foreach ($files as $file) {
            $item = json_decode(file_get_contents($file), true);
            if ($item['status'] === 'pending') {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Clean up old completed/failed queue items (older than 1 hour)
     */
    public function cleanup(): void {
        $files = glob($this->queueDir . '*.json');
        $oneHourAgo = time() - 3600;
        
        foreach ($files as $file) {
            $item = json_decode(file_get_contents($file), true);
            
            if (in_array($item['status'], ['completed', 'failed']) && $item['created_at'] < $oneHourAgo) {
                unlink($file);
            }
        }
    }
}

use Dompdf\Dompdf;
use Dompdf\Options;

// ============================================================================
// AUTHENTICATION CHECK
// ============================================================================
// Only Admin users can access the report management page

$pageTitle = 'Report Management';

if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}

// ============================================================================
// AMC REPORT GENERATOR CLASS
// ============================================================================
// This class handles all PDF report generation logic

class AMCReportGenerator {
    
    // -------------------------------------------------------------------------
    // CLASS PROPERTIES
    // -------------------------------------------------------------------------
    
    private $pdo;           // Database connection
    private $dompdf;        // Dompdf instance for PDF generation
    private $reportType;    // Type of report: 'parts_usage', 'finance', or 'inventory'
    private $startDate;     // Report start date (YYYY-MM-DD format)
    private $endDate;       // Report end date (YYYY-MM-DD format)
    private $filters = [];  // Optional filters (category, supplier, project_id)
    private $generatedBy;   // Username of person generating the report
    
    // -------------------------------------------------------------------------
    // CONSTRUCTOR
    // -------------------------------------------------------------------------
    /**
     * Initialize the report generator with database connection and Dompdf settings
     * 
     * @param PDO $pdo - Active database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        
        // Configure Dompdf options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);  // Enable HTML5 parsing
        $options->set('isPhpEnabled', false);          // Disable PHP execution in templates (security)
        $options->set('defaultFont', 'Helvetica');     // Set default font
        
        $this->dompdf = new Dompdf($options);
        $this->dompdf->setPaper('A4', 'portrait');     // A4 paper, portrait orientation
    }
    
    // -------------------------------------------------------------------------
    // SETTER METHODS (Fluent Interface - allows method chaining)
    // -------------------------------------------------------------------------
    
    /**
     * Set the type of report to generate
     * 
     * @param string $type - 'parts_usage', 'finance', or 'inventory'
     * @return self - Returns $this for method chaining
     * @throws Exception - If invalid report type provided
     */
    public function setReportType(string $type): self {
        $validTypes = ['parts_usage', 'finance', 'inventory'];
        if (!in_array($type, $validTypes)) {
            throw new Exception("Invalid report type: $type");
        }
        $this->reportType = $type;
        return $this;
    }
    
    /**
     * Set the date range for the report
     * 
     * @param string $startDate - Start date in YYYY-MM-DD format
     * @param string $endDate - End date in YYYY-MM-DD format
     * @return self - Returns $this for method chaining
     * @throws Exception - If start date is after end date
     */
    public function setDateRange(string $startDate, string $endDate): self {
        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception("Start date must be before end date");
        }
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        return $this;
    }
    
    /**
     * Set optional filters for the report
     * 
     * @param array $filters - Associative array with keys: 'category', 'supplier', 'project_id'
     * @return self - Returns $this for method chaining
     */
    public function setFilters(array $filters): self {
        $this->filters = $filters;
        return $this;
    }
    
    /**
     * Set the username of person generating the report
     * 
     * @param string $username - Username to display on report
     * @return self - Returns $this for method chaining
     */
    public function setGeneratedBy(string $username): self {
        $this->generatedBy = $username;
        return $this;
    }
    
    // -------------------------------------------------------------------------
    // CSS STYLES FOR PDF
    // -------------------------------------------------------------------------
    /**
     * Generate the CSS styles for the PDF document itself
     * Theme: Grey (#333) and White
     * 
     * @return string - HTML <style> block with all CSS
     */
    private function getStyles(): string {
        return '
        <style>
            /* Reset and base styles */
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: Helvetica, Arial, sans-serif;
                font-size: 10pt;
                color: #333333;
                background: #ffffff;
                padding: 20px;
            }
            
            /* Report header - dark grey banner at top */
            .header {
                background: #333333;
                color: #ffffff;
                padding: 20px;
                margin: -20px -20px 20px -20px;
            }
            
            .header h1 {
                font-size: 18pt;
                margin-bottom: 5px;
            }
            
            .header .subtitle {
                font-size: 10pt;
                opacity: 0.9;
            }
            
            /* Meta information box (Report ID, Generated By, Date) */
            .meta-info {
                background: #f5f5f5;
                padding: 10px 15px;
                margin-bottom: 15px;
                border-left: 4px solid #333333;
            }
            
            .meta-info p {
                margin: 3px 0;
                font-size: 9pt;
            }
            
            /* Filter transparency box - yellow/warning style */
            /* This ensures all applied filters are clearly visible (Misuse Case 5.1 mitigation) */
            .filters-box {
                background: #fff3cd;
                border: 2px solid #ffc107;
                padding: 12px 15px;
                margin-bottom: 20px;
            }
            
            .filters-box strong {
                color: #856404;
                font-size: 11pt;
            }
            
            /* Section titles - dark grey bars */
            .section-title {
                background: #333333;
                color: #ffffff;
                padding: 8px 12px;
                font-size: 11pt;
                margin: 20px 0 10px 0;
            }
            
            /* Table styles */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }
            
            th {
                background: #666666;
                color: #ffffff;
                padding: 8px 6px;
                text-align: left;
                font-size: 9pt;
                font-weight: bold;
            }
            
            td {
                padding: 6px;
                border-bottom: 1px solid #ddd;
                font-size: 9pt;
            }
            
            /* Zebra striping for table rows */
            tr:nth-child(even) {
                background: #f5f5f5;
            }
            
            /* Low stock warning highlight - red background */
            .highlight-warning {
                background: #ffebee !important;
                color: #ff6b6b;
                font-weight: bold;
            }
            
            /* Summary statistics box */
            .summary-box {
                background: #f5f5f5;
                border: 1px solid #ddd;
                padding: 15px;
                margin: 15px 0;
            }
            
            .summary-box h3 {
                color: #333333;
                margin-bottom: 10px;
                font-size: 11pt;
            }
            
            .summary-value {
                font-size: 16pt;
                font-weight: bold;
                color: #333333;
            }
            
            .summary-label {
                font-size: 8pt;
                color: #666666;
            }
            
            /* Procurement recommendation box - blue style */
            .procurement-box {
                background: #e3f2fd;
                border: 2px solid #1976d2;
                padding: 15px;
                margin-top: 20px;
            }
            
            .procurement-box h3 {
                color: #1565c0;
                margin-bottom: 10px;
            }
            
            /* No procurement needed - green success style */
            .no-procurement {
                background: #e8f5e9;
                border: 2px solid #4caf50;
                padding: 15px;
                text-align: center;
                color: #2e7d32;
                font-weight: bold;
            }
            
            /* Grand total row - dark with white text */
            .total-row {
                background: #333333 !important;
                color: #ffffff !important;
                font-weight: bold;
            }
            
            .total-row td {
                border: none;
                padding: 10px 6px;
                color: #ffffff;
            }
            
            /* Footer - fixed at bottom of page */
            .footer {
                position: fixed;
                bottom: 20px;
                left: 20px;
                right: 20px;
                text-align: center;
                font-size: 8pt;
                color: #666666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            
            /* Utility classes */
            .text-right { text-align: right; }
            .text-center { text-align: center; }
        </style>';
    }
    
    // -------------------------------------------------------------------------
    // FILTER HEADER GENERATION
    // -------------------------------------------------------------------------
    /**
     * Generate the filter transparency header
     * This explicitly states ALL filters applied to the report
     * Required for audit purposes (Misuse Case 5.1 mitigation)
     * 
     * @return string - HTML for the filter header box
     */
    private function generateFilterHeader(): string {
        $filterItems = [];
        
        // Always show date range
        $filterItems[] = "Date Range: " . date('d/m/Y', strtotime($this->startDate)) . 
                        " to " . date('d/m/Y', strtotime($this->endDate));
        
        // Show category filter (or "All" if not filtered)
        if (!empty($this->filters['category'])) {
            $filterItems[] = "Category: " . htmlspecialchars($this->filters['category']);
        } else {
            $filterItems[] = "Category: All";
        }
        
        // Show supplier filter (or "All" if not filtered)
        if (!empty($this->filters['supplier'])) {
            $filterItems[] = "Supplier: " . htmlspecialchars($this->filters['supplier']);
        } else {
            $filterItems[] = "Supplier: All";
        }
        
        // Show project filter if applied
        if (!empty($this->filters['project_id'])) {
            $filterItems[] = "Project: " . htmlspecialchars($this->filters['project_id']);
        }
        
        // Build the HTML
        $html = '<div class="filters-box">';
        $html .= '<strong>FILTERS APPLIED:</strong><br>';
        $html .= implode(' | ', $filterItems);
        $html .= '</div>';
        
        return $html;
    }
    
    // -------------------------------------------------------------------------
    // REPORT HEADER GENERATION
    // -------------------------------------------------------------------------
    /**
     * Generate the report header with title and metadata
     * 
     * @return string - HTML for the report header section
     */
    private function generateHeader(): string {
        // Map report types to display titles
        $titles = [
            'parts_usage' => 'Parts Usage Report',
            'finance' => 'Finance Report',
            'inventory' => 'Inventory Report'
        ];
        
        $title = $titles[$this->reportType] ?? 'Report';
        
        // Generate unique report ID: RPT-[TYPE]-[DATE]-[TIME]
        $reportId = 'RPT-' . strtoupper(substr($this->reportType, 0, 3)) . '-' . date('Ymd-His');
        
        return '
        <div class="header">
            <h1>' . $title . '</h1>
            <div class="subtitle">Advanced Manufacturing Centre (AMC) - Temasek Polytechnic</div>
        </div>
        
        <div class="meta-info">
            <p><strong>Report ID:</strong> ' . $reportId . '</p>
            <p><strong>Generated By:</strong> ' . htmlspecialchars($this->generatedBy ?? 'System') . '</p>
            <p><strong>Generated At:</strong> ' . date('d/m/Y H:i:s') . '</p>
        </div>';
    }
    
    // =========================================================================
    // PARTS USAGE REPORT
    // =========================================================================
    /**
     * Generate Parts Usage Report
     * 
     * Shows parts used grouped by Project ID with:
     * - Total quantity used per part
     * - Cost calculations (unit cost × quantity)
     * - Budget totals per project
     * - Grand total across all projects
     * 
     * Tables used: parts_log, inventory
     * Join: parts_log.part_used = inventory.part_name
     * 
     * @return string - Complete HTML content for the report
     */
    private function generatePartsUsageReport(): string {
        $html = $this->generateHeader();
        $html .= $this->generateFilterHeader();
        
        // Build SQL query with optional filters
        // Uses LEFT JOIN to include parts even if not in inventory table
        $sql = "SELECT 
                    pl.project_id,
                    pl.part_used,
                    i.category,
                    i.supplier,
                    SUM(pl.amount_used) as total_quantity,
                    i.cost_per_part,
                    SUM(pl.amount_used * COALESCE(i.cost_per_part, 0)) as total_cost,
                    GROUP_CONCAT(DISTINCT pl.username) as users
                FROM parts_log pl
                LEFT JOIN inventory i ON pl.part_used = i.part_name
                WHERE pl.date_used BETWEEN :start_date AND :end_date";
        
        $params = [':start_date' => $this->startDate, ':end_date' => $this->endDate];
        
        // Add optional category filter
        if (!empty($this->filters['category'])) {
            $sql .= " AND i.category = :category";
            $params[':category'] = $this->filters['category'];
        }
        
        // Add optional supplier filter
        if (!empty($this->filters['supplier'])) {
            $sql .= " AND i.supplier = :supplier";
            $params[':supplier'] = $this->filters['supplier'];
        }
        
        // Add optional project filter
        if (!empty($this->filters['project_id'])) {
            $sql .= " AND pl.project_id = :project_id";
            $params[':project_id'] = $this->filters['project_id'];
        }
        
        // Group by project and part, order by project then cost (descending)
        $sql .= " GROUP BY pl.project_id, pl.part_used ORDER BY pl.project_id, total_cost DESC";
        
        // Execute query
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize data by project
        $projectData = [];
        $grandTotal = 0;
        
        foreach ($data as $row) {
            $projectId = $row['project_id'] ?? 'Unassigned';
            if (!isset($projectData[$projectId])) {
                $projectData[$projectId] = ['items' => [], 'total' => 0];
            }
            $projectData[$projectId]['items'][] = $row;
            $projectData[$projectId]['total'] += $row['total_cost'];
            $grandTotal += $row['total_cost'];
        }
        
        // Generate Summary Section
        $html .= '<div class="summary-box">';
        $html .= '<h3>Summary</h3>';
        $html .= '<table><tr>';
        $html .= '<td class="text-center" style="width:33%"><div class="summary-value">' . count($projectData) . '</div><div class="summary-label">Projects</div></td>';
        $html .= '<td class="text-center" style="width:33%"><div class="summary-value">' . count($data) . '</div><div class="summary-label">Part Types Used</div></td>';
        $html .= '<td class="text-center" style="width:33%"><div class="summary-value">$' . number_format($grandTotal, 2) . '</div><div class="summary-label">Total Budget Used</div></td>';
        $html .= '</tr></table>';
        $html .= '</div>';
        
        // Check if no data found
        if (empty($projectData)) {
            $html .= '<div class="no-procurement">No parts usage data found for the selected date range and filters.</div>';
            return $html;
        }
        
        // Generate detailed breakdown by project
        foreach ($projectData as $projectId => $project) {
            $html .= '<div class="section-title">Project: ' . htmlspecialchars($projectId) . ' (Subtotal: $' . number_format($project['total'], 2) . ')</div>';
            
            $html .= '<table>';
            $html .= '<tr><th>Part Name</th><th>Category</th><th>Qty Used</th><th>Unit Cost</th><th>Total Cost</th><th>Used By</th></tr>';
            
            foreach ($project['items'] as $item) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($item['part_used']) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['category'] ?? '-') . '</td>';
                $html .= '<td class="text-center">' . $item['total_quantity'] . '</td>';
                $html .= '<td class="text-right">$' . number_format($item['cost_per_part'] ?? 0, 2) . '</td>';
                $html .= '<td class="text-right">$' . number_format($item['total_cost'], 2) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['users']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }
        
        // Grand Total row
        $html .= '<table><tr class="total-row"><td colspan="4" class="text-right">GRAND TOTAL:</td><td class="text-right">$' . number_format($grandTotal, 2) . '</td><td></td></tr></table>';
        
        return $html;
    }
    
    // =========================================================================
    // FINANCE REPORT
    // =========================================================================
    /**
     * Generate Finance Report
     * 
     * Shows all transactions within date range with:
     * - Expenditure breakdown by category (with percentages)
     * - Detailed transaction log (date, part, qty, cost, user, project)
     * - Grand total value
     * 
     * Tables used: parts_log, inventory
     * Join: parts_log.part_used = inventory.part_name
     * 
     * @return string - Complete HTML content for the report
     */
    private function generateFinanceReport(): string {
        $html = $this->generateHeader();
        $html .= $this->generateFilterHeader();
        
        // Build SQL query
        $sql = "SELECT 
                    pl.date_used,
                    pl.part_used,
                    i.category,
                    i.supplier,
                    pl.amount_used,
                    i.cost_per_part,
                    (pl.amount_used * COALESCE(i.cost_per_part, 0)) as line_total,
                    pl.username,
                    pl.project_id,
                    pl.comments
                FROM parts_log pl
                LEFT JOIN inventory i ON pl.part_used = i.part_name
                WHERE pl.date_used BETWEEN :start_date AND :end_date";
        
        $params = [':start_date' => $this->startDate, ':end_date' => $this->endDate];
        
        // Add optional filters
        if (!empty($this->filters['category'])) {
            $sql .= " AND i.category = :category";
            $params[':category'] = $this->filters['category'];
        }
        
        if (!empty($this->filters['supplier'])) {
            $sql .= " AND i.supplier = :supplier";
            $params[':supplier'] = $this->filters['supplier'];
        }
        
        // Order by date ascending
        $sql .= " ORDER BY pl.date_used ASC, pl.id ASC";
        
        // Execute query
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals and category breakdown
        $totalValue = 0;
        $totalParts = 0;
        $categoryTotals = [];
        
        foreach ($data as $row) {
            $totalValue += $row['line_total'];
            $totalParts += $row['amount_used'];
            $cat = $row['category'] ?? 'Uncategorized';
            if (!isset($categoryTotals[$cat])) {
                $categoryTotals[$cat] = 0;
            }
            $categoryTotals[$cat] += $row['line_total'];
        }
        
        // Generate Summary Section
        $html .= '<div class="summary-box">';
        $html .= '<h3>Financial Summary</h3>';
        $html .= '<table><tr>';
        $html .= '<td class="text-center" style="width:33%"><div class="summary-value">' . count($data) . '</div><div class="summary-label">Transactions</div></td>';
        $html .= '<td class="text-center" style="width:33%"><div class="summary-value">' . $totalParts . '</div><div class="summary-label">Total Parts Used</div></td>';
        $html .= '<td class="text-center" style="width:33%"><div class="summary-value">$' . number_format($totalValue, 2) . '</div><div class="summary-label">Total Value</div></td>';
        $html .= '</tr></table>';
        $html .= '</div>';
        
        // Check if no data found
        if (empty($data)) {
            $html .= '<div class="no-procurement">No transaction data found for the selected date range and filters.</div>';
            return $html;
        }
        
        // Generate Category Breakdown table
        $html .= '<div class="section-title">Expenditure by Category</div>';
        $html .= '<table>';
        $html .= '<tr><th>Category</th><th>Total Spent</th><th>% of Total</th></tr>';
        
        // Sort categories by total (descending)
        arsort($categoryTotals);
        foreach ($categoryTotals as $category => $amount) {
            $percentage = ($totalValue > 0) ? ($amount / $totalValue * 100) : 0;
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($category) . '</td>';
            $html .= '<td class="text-right">$' . number_format($amount, 2) . '</td>';
            $html .= '<td class="text-right">' . number_format($percentage, 1) . '%</td>';
            $html .= '</tr>';
        }
        $html .= '<tr class="total-row"><td>TOTAL</td><td class="text-right">$' . number_format($totalValue, 2) . '</td><td class="text-right">100%</td></tr>';
        $html .= '</table>';
        
        // Generate Detailed Transaction Log
        $html .= '<div class="section-title">Detailed Transaction Log</div>';
        $html .= '<table>';
        $html .= '<tr><th>Date</th><th>Part</th><th>Qty</th><th>Unit Cost</th><th>Total</th><th>User</th><th>Project</th></tr>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . date('d/m/Y', strtotime($row['date_used'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['part_used']) . '</td>';
            $html .= '<td class="text-center">' . $row['amount_used'] . '</td>';
            $html .= '<td class="text-right">$' . number_format($row['cost_per_part'] ?? 0, 2) . '</td>';
            $html .= '<td class="text-right">$' . number_format($row['line_total'], 2) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['username']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['project_id'] ?? '-') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '<tr class="total-row"><td colspan="4" class="text-right">GRAND TOTAL:</td><td class="text-right">$' . number_format($totalValue, 2) . '</td><td colspan="2"></td></tr>';
        $html .= '</table>';
        
        return $html;
    }
    
    // =========================================================================
    // INVENTORY REPORT
    // =========================================================================
    /**
     * Generate Inventory Report
     * 
     * Shows current inventory status with:
     * - Summary statistics (part types, total units, inventory value, low stock count)
     * - Full inventory listing with low stock highlighting (stock < 10)
     * - Procurement recommendations for low stock items
     * - Total procurement cost calculation
     * 
     * Tables used: inventory only
     * 
     * @return string - Complete HTML content for the report
     */
    private function generateInventoryReport(): string {
        $html = $this->generateHeader();
        $html .= $this->generateFilterHeader();
        
        // Build SQL query for current inventory
        $sql = "SELECT 
                    id,
                    part_name,
                    category,
                    supplier,
                    stock_level,
                    cost_per_part,
                    status
                FROM inventory
                WHERE is_deleted = 0";
        
        $params = [];
        
        // Add optional filters
        if (!empty($this->filters['category'])) {
            $sql .= " AND category = :category";
            $params[':category'] = $this->filters['category'];
        }
        
        if (!empty($this->filters['supplier'])) {
            $sql .= " AND supplier = :supplier";
            $params[':supplier'] = $this->filters['supplier'];
        }
        
        $sql .= " ORDER BY category, part_name";
        
        // Execute query
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Configuration for low stock detection
        $lowStockThreshold = LOW_STOCK_THRESHOLD;  // Items with stock < 10 are considered low
        $restockQty = RESTOCK_QUANTITY;          // Recommended restock quantity
        
        // Calculate statistics
        $lowStockItems = [];
        $totalParts = 0;
        $totalValue = 0;
        $procurementCost = 0;
        
        foreach ($data as $row) {
            $totalParts += $row['stock_level'];
            $totalValue += ($row['stock_level'] * ($row['cost_per_part'] ?? 0));
            
            // Check if low stock
            if ($row['stock_level'] < $lowStockThreshold) {
                $lowStockItems[] = $row;
                $procurementCost += ($restockQty * ($row['cost_per_part'] ?? 0));
            }
        }
        
        // Generate Summary Section
        $html .= '<div class="summary-box">';
        $html .= '<h3>Inventory Summary</h3>';
        $html .= '<table><tr>';
        $html .= '<td class="text-center" style="width:25%"><div class="summary-value">' . count($data) . '</div><div class="summary-label">Part Types</div></td>';
        $html .= '<td class="text-center" style="width:25%"><div class="summary-value">' . $totalParts . '</div><div class="summary-label">Total Units</div></td>';
        $html .= '<td class="text-center" style="width:25%"><div class="summary-value">$' . number_format($totalValue, 2) . '</div><div class="summary-label">Inventory Value</div></td>';
        // Low stock count - red if > 0, green if 0
        $html .= '<td class="text-center" style="width:25%"><div class="summary-value" style="color:' . (count($lowStockItems) > 0 ? '#ff6b6b' : '#4caf50') . '">' . count($lowStockItems) . '</div><div class="summary-label">Low Stock Items</div></td>';
        $html .= '</tr></table>';
        $html .= '</div>';
        
        // Generate Current Inventory Table
        $html .= '<div class="section-title">Current Inventory</div>';
        $html .= '<table>';
        $html .= '<tr><th>Part Name</th><th>Category</th><th>Supplier</th><th>Stock</th><th>Unit Cost</th><th>Total Value</th><th>Status</th></tr>';
        
        foreach ($data as $row) {
            $isLowStock = $row['stock_level'] < $lowStockThreshold;
            $rowClass = $isLowStock ? 'highlight-warning' : '';
            
            $html .= '<tr class="' . $rowClass . '">';
            $html .= '<td>' . htmlspecialchars($row['part_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['category']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['supplier']) . '</td>';
            $html .= '<td class="text-center">' . $row['stock_level'] . ($isLowStock ? ' (LOW)' : '') . '</td>';
            $html .= '<td class="text-right">$' . number_format($row['cost_per_part'] ?? 0, 2) . '</td>';
            $html .= '<td class="text-right">$' . number_format($row['stock_level'] * ($row['cost_per_part'] ?? 0), 2) . '</td>';
            $html .= '<td>' . ucfirst($row['status']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '<tr class="total-row"><td colspan="5" class="text-right">TOTAL INVENTORY VALUE:</td><td class="text-right">$' . number_format($totalValue, 2) . '</td><td></td></tr>';
        $html .= '</table>';
        
        // Generate Procurement Recommendations Section
        $html .= '<div class="section-title">Procurement Recommendations</div>';
        
        if (count($lowStockItems) > 0) {
            // There are items that need restocking
            $html .= '<div class="procurement-box">';
            $html .= '<h3>Restock Required</h3>';
            $html .= '<p>The following items have stock levels below ' . $lowStockThreshold . ' units:</p>';
            $html .= '<table style="margin-top:10px">';
            $html .= '<tr><th>Part Name</th><th>Current Stock</th><th>Restock Qty</th><th>Unit Cost</th><th>Procurement Cost</th></tr>';
            
            foreach ($lowStockItems as $item) {
                $itemCost = $restockQty * ($item['cost_per_part'] ?? 0);
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($item['part_name']) . '</td>';
                $html .= '<td class="text-center">' . $item['stock_level'] . '</td>';
                $html .= '<td class="text-center">' . $restockQty . '</td>';
                $html .= '<td class="text-right">$' . number_format($item['cost_per_part'] ?? 0, 2) . '</td>';
                $html .= '<td class="text-right">$' . number_format($itemCost, 2) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '<tr class="total-row"><td colspan="4" class="text-right">TOTAL PROCUREMENT COST:</td><td class="text-right">$' . number_format($procurementCost, 2) . '</td></tr>';
            $html .= '</table>';
            $html .= '</div>';
        } else {
            // All items are adequately stocked
            $html .= '<div class="no-procurement">';
            $html .= 'No Procurement Required<br>';
            $html .= '<span style="font-weight:normal;font-size:9pt">All items are above the minimum threshold of ' . $lowStockThreshold . ' units.</span>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    // =========================================================================
    // MAIN GENERATION METHOD
    // =========================================================================
    /**
     * Generate the complete PDF and return result data
     * 
     * @return array - Contains: success (bool), pdf (binary), hash (string), filename (string)
     * @throws Exception - If report type not set
     */
    public function generate(): array {
        if (!$this->reportType) {
            throw new Exception("Report type not set");
        }
        
        // Generate HTML content based on report type
        switch ($this->reportType) {
            case 'parts_usage':
                $content = $this->generatePartsUsageReport();
                break;
            case 'finance':
                $content = $this->generateFinanceReport();
                break;
            case 'inventory':
                $content = $this->generateInventoryReport();
                break;
            default:
                throw new Exception("Unknown report type");
        }
        
        // Build complete HTML document
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $this->getStyles() . '</head><body>';
        $html .= $content;
        $html .= '<div class="footer">Generated by AMC Inventory Management System | Temasek Polytechnic</div>';
        $html .= '</body></html>';
        
        // Generate PDF using Dompdf
        $this->dompdf->loadHtml($html);
        $this->dompdf->render();
        
        // Get PDF content and generate hash for integrity verification
        $pdfContent = $this->dompdf->output();
        $hash = hash('sha256', $pdfContent);
        
        return [
            'success' => true,
            'pdf' => $pdfContent,
            'hash' => $hash,
            'filename' => $this->reportType . '_report_' . date('Ymd_His') . '.pdf'
        ];
    }
    
    /**
     * Stream PDF directly to browser
     * 
     * @param bool $download - true to force download, false to display in browser
     */
    public function stream(bool $download = false): void {
        $result = $this->generate();
        $this->dompdf->stream($result['filename'], ['Attachment' => $download ? 1 : 0]);
    }
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================
// Using config.php constants for database credentials

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
// INITIALIZE QUEUE AND CHECK COOLDOWN
// ============================================================================

$queue = new ReportQueue();
$queue->cleanup();  // Clean up old queue items

$cooldownStatus = getCooldownStatus();
$pendingCount = $queue->getPendingCount();

// ============================================================================
// FETCH DATA FOR FILTER DROPDOWNS
// ============================================================================
// Get unique categories, suppliers, and project IDs for the filter options

$categories = $pdo->query("SELECT DISTINCT category FROM inventory WHERE is_deleted = 0 ORDER BY category")->fetchAll();
$suppliers = $pdo->query("SELECT DISTINCT supplier FROM inventory WHERE is_deleted = 0 ORDER BY supplier")->fetchAll();
$projects = $pdo->query("SELECT DISTINCT project_id FROM parts_log WHERE project_id IS NOT NULL AND project_id != '' ORDER BY project_id")->fetchAll();

// ============================================================================
// HANDLE FILE DOWNLOAD
// ============================================================================

if (isset($_GET['download']) && isset($_GET['file'])) {
    $filename = basename($_GET['file']);  // Security: prevent directory traversal
    $filepath = REPORTS_OUTPUT_DIR . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        die("File not found");
    }
}

// ============================================================================
// FORM SUBMISSION HANDLER
// ============================================================================

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    
    // Check cooldown first (Misuse Case 5.2)
    if ($cooldownStatus['on_cooldown']) {
        $message = $cooldownStatus['message'];
        $messageType = 'warning';
    } else {
        try {
            $reportType = $_POST['report_type'] ?? '';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            
            // Validate inputs
            if (empty($reportType)) {
                throw new Exception("Please select a report type");
            }
            
            if (empty($startDate) || empty($endDate)) {
                throw new Exception("Please select both start and end dates");
            }
            
            // Server-side date validation
            if (strtotime($startDate) > strtotime($endDate)) {
                throw new Exception("Start Date must be earlier than End Date");
            }
            
            // Build filters array
            $filters = [];
            if (!empty($_POST['category'])) {
                $filters['category'] = $_POST['category'];
            }
            if (!empty($_POST['supplier'])) {
                $filters['supplier'] = $_POST['supplier'];
            }
            if (!empty($_POST['project_id']) && $reportType === 'parts_usage') {
                $filters['project_id'] = $_POST['project_id'];
            }
            
            // Add to queue
            $params = [
                'report_type' => $reportType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters
            ];
            
            $queueId = $queue->addToQueue($params, $_SESSION['username']);
            
            // Set cooldown timer
            setCooldown();
            
            // Process the queue immediately
            $result = $queue->processNext($pdo);
            
            if ($result && $result['status'] === 'completed') {
                // Redirect to view the PDF
                header('Location: ?download=1&file=' . urlencode($result['output_file']));
                exit;
            } elseif ($result && $result['status'] === 'failed') {
                throw new Exception($result['error']);
            }
            
            // Update cooldown status for display
            $cooldownStatus = getCooldownStatus();
            
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <title><?php echo $pageTitle; ?></title>
    <style>
        /* ===================================================================================
           REPORT MANAGEMENT PAGE STYLES -- This is here since DomPDF cannot read External CSS
           =================================================================================== */
        
        /* Main container */
        .report-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Card component for each section */
        .report-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .report-card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        /* Form elements */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group select,
        .form-group input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input[type="date"]:focus {
            outline: none;
            border-color: #333;
        }
        
        /* Date range layout - two columns */
        .date-range {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* Filter row layout - three columns */
        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        /* Report type selection cards */
        .report-type-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .report-type-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .report-type-card:hover {
            border-color: #333;
            background-color: #f9f9f9;
        }
        
        /* Selected state for report type card */
        .report-type-card.selected {
            border-color: #333;
            background-color: #333;
            color: #fff;
        }
        
        .report-type-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .report-type-card h3 {
            font-size: 1rem;
            margin-bottom: 5px;
        }
        
        .report-type-card p {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        /* Buttons */
        .btn-generate {
            background-color: #333;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-generate:hover {
            background-color: #555;
        }
        
        .btn-generate:disabled {
            background-color: #999;
            cursor: not-allowed;
        }
        
        .btn-reset {
            background-color: #6c757d;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .btn-reset:hover {
            background-color: #545b62;
        }
        
        .btn-secondary {
            background-color: #17a2b8;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background-color: #138496;
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #ffebee;
            border: 1px solid #ef9a9a;
            color: #c62828;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            border: 1px solid #a5d6a7;
            color: #2e7d32;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-info {
            background-color: #e3f2fd;
            border: 1px solid #90caf9;
            color: #1565c0;
        }
        
        /* Info box */
        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #1976d2;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        /* Project filter - hidden by default, shown for parts_usage report */
        .project-filter {
            display: none;
        }
        
        .project-filter.show {
            display: block;
        }
        
        /* Button group */
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        /* Summary display box */
        .summary-display {
            margin-bottom: 15px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
            display: none;
        }
        
        small {
            color: #666;
            font-size: 0.85rem;
        }
        
        /* Responsive design for mobile */
        @media (max-width: 768px) {
            .report-type-cards,
            .filter-row,
            .date-range {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn-reset {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="report-container">
        <h1 style="margin-bottom: 20px;">Report Management</h1>
        
        <!-- Display error/success messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Cooldown Warning Banner -->
        <?php if ($cooldownStatus['on_cooldown']): ?>
            <div class="alert alert-warning" id="cooldownAlert">
                <strong>⏱️ Cooldown Active:</strong> 
                Please wait <span id="cooldownTimer"><?php echo $cooldownStatus['seconds']; ?></span> seconds before generating another report.
            </div>
        <?php endif; ?>
        
        <!-- Queue Status -->
        <?php if ($pendingCount > 0): ?>
            <div class="alert alert-info">
                <strong>📋 Queue Status:</strong> 
                <?php echo $pendingCount; ?> report(s) pending in queue.
            </div>
        <?php endif; ?>
        
        <!-- Information box explaining the feature -->
        <div class="info-box">
            <strong>Report Generation</strong><br>
            Select a report type, date range, and optional filters. All applied filters will be displayed at the top of the generated report for transparency and audit purposes.
        </div>
        
        <form method="POST" id="reportForm">
            <!-- ============================================================
                 STEP 1: REPORT TYPE SELECTION
                 ============================================================ -->
            <div class="report-card">
                <h2>1. Select Report Type</h2>
                
                <div class="report-type-cards">
                    <!-- Parts Usage Report Card -->
                    <div class="report-type-card" data-type="parts_usage" onclick="selectReportType('parts_usage')">
                        <div class="icon">📦</div>
                        <h3>Parts Usage Report</h3>
                        <p>Usage by project with budget calculations</p>
                    </div>
                    
                    <!-- Finance Report Card -->
                    <div class="report-type-card" data-type="finance" onclick="selectReportType('finance')">
                        <div class="icon">💰</div>
                        <h3>Finance Report</h3>
                        <p>All transactions with total values</p>
                    </div>
                    
                    <!-- Inventory Report Card -->
                    <div class="report-type-card" data-type="inventory" onclick="selectReportType('inventory')">
                        <div class="icon">📋</div>
                        <h3>Inventory Report</h3>
                        <p>Current stock with procurement recommendations</p>
                    </div>
                </div>
                
                <!-- Hidden input to store selected report type -->
                <input type="hidden" name="report_type" id="reportType" required>
            </div>
            
            <!-- ============================================================
                 STEP 2: DATE RANGE SELECTION
                 ============================================================ -->
            <div class="report-card">
                <h2>2. Select Date Range</h2>
                
                <div class="date-range">
                    <div class="form-group">
                        <label for="startDate">Start Date</label>
                        <input type="date" id="startDate" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="endDate">End Date</label>
                        <input type="date" id="endDate" name="end_date" required>
                    </div>
                </div>
            </div>
            
            <!-- ============================================================
                 STEP 3: OPTIONAL FILTERS
                 ============================================================ -->
            <div class="report-card">
                <h2>3. Apply Filters (Optional)</h2>
                <p style="margin-bottom:15px;color:#666;font-size:0.9rem">
                    Leave blank to include all data. Applied filters will be explicitly stated on the report.
                </p>
                
                <div class="filter-row">
                    <!-- Category Filter -->
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Supplier Filter -->
                    <div class="form-group">
                        <label for="supplier">Supplier</label>
                        <select id="supplier" name="supplier">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?php echo htmlspecialchars($sup['supplier']); ?>">
                                    <?php echo htmlspecialchars($sup['supplier']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Project Filter (only shown for Parts Usage Report) -->
                    <div class="form-group project-filter" id="projectFilterGroup">
                        <label for="project_id">Project ID</label>
                        <select id="project_id" name="project_id">
                            <option value="">All Projects</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?php echo htmlspecialchars($proj['project_id']); ?>">
                                    <?php echo htmlspecialchars($proj['project_id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- ============================================================
                 STEP 4: GENERATE REPORT
                 ============================================================ -->
            <div class="report-card">
                <h2>4. Generate Report</h2>
                
                <!-- Summary of selected options (updated dynamically via JavaScript) -->
                <div class="summary-display" id="selectedSummary">
                    <strong>Selected Options:</strong><br>
                    <span id="summaryText"></span>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="generate" value="1" class="btn-generate" 
                            id="generateBtn" <?php echo $cooldownStatus['on_cooldown'] ? 'disabled' : ''; ?>>
                        📄 Generate & View PDF
                    </button>
                    <button type="button" onclick="resetForm()" class="btn-reset">
                        🔄 Reset Form
                    </button>
                    <a href="report_log.php" class="btn-secondary">
                        📋 View Report Audit Log
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <?php require_once '../includes/footer.php'; ?>
    
    <script>
        // ====================================================================
        // COOLDOWN TIMER
        // ====================================================================
        
        let cooldownSeconds = <?php echo $cooldownStatus['seconds']; ?>;
        
        if (cooldownSeconds > 0) {
            const timerEl = document.getElementById('cooldownTimer');
            const btnEl = document.getElementById('generateBtn');
            const alertEl = document.getElementById('cooldownAlert');
            
            const countdown = setInterval(() => {
                cooldownSeconds--;
                
                if (timerEl) {
                    timerEl.textContent = cooldownSeconds;
                }
                
                if (cooldownSeconds <= 0) {
                    clearInterval(countdown);
                    
                    // Enable button
                    if (btnEl) {
                        btnEl.disabled = false;
                    }
                    
                    // Hide cooldown alert
                    if (alertEl) {
                        alertEl.style.display = 'none';
                    }
                }
            }, 1000);
        }
        
        // ====================================================================
        // INITIALIZATION: Set default dates on page load
        // ====================================================================
        
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        
        document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
        document.getElementById('endDate').value = today.toISOString().split('T')[0];
        
        // Set max date to today (prevent future dates)
        document.getElementById('startDate').max = today.toISOString().split('T')[0];
        document.getElementById('endDate').max = today.toISOString().split('T')[0];
        
        // ====================================================================
        // REPORT TYPE SELECTION
        // ====================================================================
        
        function selectReportType(type) {
            document.getElementById('reportType').value = type;
            
            document.querySelectorAll('.report-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-type="${type}"]`).classList.add('selected');
            
            const projectFilter = document.getElementById('projectFilterGroup');
            if (type === 'parts_usage') {
                projectFilter.classList.add('show');
            } else {
                projectFilter.classList.remove('show');
                document.getElementById('project_id').value = '';
            }
            
            updateSummary();
        }
        
        // ====================================================================
        // SUMMARY DISPLAY UPDATE
        // ====================================================================
        
        function updateSummary() {
            const reportType = document.getElementById('reportType').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const category = document.getElementById('category').value;
            const supplier = document.getElementById('supplier').value;
            const projectId = document.getElementById('project_id').value;
            
            const summaryDiv = document.getElementById('selectedSummary');
            const summaryText = document.getElementById('summaryText');
            
            if (reportType) {
                const typeNames = {
                    'parts_usage': 'Parts Usage Report',
                    'finance': 'Finance Report',
                    'inventory': 'Inventory Report'
                };
                
                let text = `<strong>Report:</strong> ${typeNames[reportType]}<br>`;
                text += `<strong>Date Range:</strong> ${formatDate(startDate)} to ${formatDate(endDate)}<br>`;
                text += `<strong>Filters:</strong> `;
                
                let filters = [];
                if (category) filters.push(`Category: ${category}`);
                if (supplier) filters.push(`Supplier: ${supplier}`);
                if (projectId && reportType === 'parts_usage') filters.push(`Project: ${projectId}`);
                
                text += filters.length > 0 ? filters.join(', ') : 'None (All data)';
                
                summaryText.innerHTML = text;
                summaryDiv.style.display = 'block';
            } else {
                summaryDiv.style.display = 'none';
            }
        }
        
        // ====================================================================
        // DATE FORMATTING HELPER
        // ====================================================================
        
        function formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }
        
        // ====================================================================
        // FORM RESET
        // ====================================================================
        
        function resetForm() {
            document.getElementById('reportForm').reset();
            document.getElementById('reportType').value = '';
            
            document.querySelectorAll('.report-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            document.getElementById('projectFilterGroup').classList.remove('show');
            document.getElementById('selectedSummary').style.display = 'none';
            
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
        }
        
        // ====================================================================
        // FORM VALIDATION - Ensures start date is not after end date
        // ====================================================================
        
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const reportType = document.getElementById('reportType').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!reportType) {
                e.preventDefault();
                alert('Please select a report type');
                return;
            }
            
            if (!startDate || !endDate) {
                e.preventDefault();
                alert('Please select both start and end dates');
                return;
            }
            
            // Date validation - start must be before or equal to end
            if (new Date(startDate) > new Date(endDate)) {
                e.preventDefault();
                alert('Start Date must be earlier than or equal to End Date');
                return;
            }
            
            // Cooldown check
            if (cooldownSeconds > 0) {
                e.preventDefault();
                alert('Please wait for the cooldown timer to complete.');
                return;
            }
        });
        
        // ====================================================================
        // REAL-TIME DATE VALIDATION
        // ====================================================================
        
        document.getElementById('startDate').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.getElementById('endDate');
            
            // Set minimum end date to start date
            endDateInput.min = startDate;
            
            // If end date is before start date, reset it
            if (endDateInput.value && new Date(endDateInput.value) < new Date(startDate)) {
                endDateInput.value = startDate;
            }
            
            updateSummary();
        });
        
        document.getElementById('endDate').addEventListener('change', function() {
            const endDate = this.value;
            const startDateInput = document.getElementById('startDate');
            
            // If start date is after end date, show warning
            if (startDateInput.value && new Date(startDateInput.value) > new Date(endDate)) {
                alert('End Date cannot be earlier than Start Date');
                this.value = startDateInput.value;
            }
            
            updateSummary();
        });
        
        // ====================================================================
        // EVENT LISTENERS FOR FILTER CHANGES
        // ====================================================================
        
        document.getElementById('category').addEventListener('change', updateSummary);
        document.getElementById('supplier').addEventListener('change', updateSummary);
        document.getElementById('project_id').addEventListener('change', updateSummary);
    </script>
</body>
</html>