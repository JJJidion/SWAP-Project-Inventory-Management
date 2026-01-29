<?php
/**
 * Parts Usage Submission Page
 * Allows users to submit claims for parts they have used
 * Uses prepared statements to prevent SQL injection
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Session timeout
require_once __DIR__ . '/../utils/session_check.php';

$pageTitle = 'Submit Parts Usage';


if (!isset($_SESSION["username"])) {
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
// FETCH PARTS FOR DROPDOWN
// ============================================================================

$parts = $pdo->query("SELECT part_name FROM inventory WHERE is_deleted = 0 ORDER BY part_name")->fetchAll();

// ============================================================================
// FORM SUBMISSION HANDLER
// ============================================================================

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_claim'])) {
    try {
        // Get and sanitize form data
        $partUsed = trim($_POST['part_used'] ?? '');
        $amountUsed = intval($_POST['amount_used'] ?? 0);
        $dateUsed = $_POST['date_used'] ?? '';
        $projectId = trim($_POST['project_id'] ?? '');
        $comments = trim($_POST['comments'] ?? '');
        $username = $_SESSION['username'];
        
        // Validation
        if (empty($partUsed)) {
            throw new Exception("Please select a part");
        }
        
        if ($amountUsed <= 0) {
            throw new Exception("Amount used must be greater than 0");
        }
        
        if ($amountUsed > 9999) {
            throw new Exception("Amount used cannot exceed 9999");
        }
        
        if (empty($dateUsed)) {
            throw new Exception("Please select a date");
        }
        
        // Validate date is not in the future
        if (strtotime($dateUsed) > time()) {
            throw new Exception("Date cannot be in the future");
        }
        
        if (empty($projectId)) {
            throw new Exception("Please enter a Project ID");
        }
        
        // Validate project ID format (alphanumeric, max 50 chars)
        if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/', $projectId)) {
            throw new Exception("Project ID can only contain letters, numbers, hyphens and underscores (max 50 characters)");
        }
        
        // Sanitize comments - remove any potentially harmful content
        // htmlspecialchars will be applied when displaying
        $comments = substr($comments, 0, 255); // Limit to 255 characters
        
        // ================================================================
        // INSERT INTO DATABASE USING PREPARED STATEMENT
        // This prevents SQL injection attacks
        // ================================================================
        
        $sql = "INSERT INTO parts_log (username, part_used, amount_used, date_used, project_id, comments, claim_submission_time) 
                VALUES (:username, :part_used, :amount_used, :date_used, :project_id, :comments, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':part_used' => $partUsed,
            ':amount_used' => $amountUsed,
            ':date_used' => $dateUsed,
            ':project_id' => $projectId,
            ':comments' => $comments
        ]);
        
        $message = "Your claim has been submitted successfully!";
        $messageType = 'success';
        
        // Clear the POST data to reset the form
        $_POST = [];
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
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
    /* Styles are here to prevent conflict with other pages */
        .form-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .form-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .form-card h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        
        .form-card .subtitle {
            color: #666;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group label .required {
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #333;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            color: #666;
        }
        
        .btn-submit {
            background-color: #333;
            color: #fff;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        .btn-submit:hover {
            background-color: #555;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="form-container">
        <div class="form-card">
            <h1>📦 Submit Parts Usage Claim</h1>
            <p class="subtitle">Record the parts you have used for your project</p>
            
            <!-- Display success/error messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Info box -->
            <div class="info-box">
                <strong>Note:</strong> All fields marked with <span style="color:#dc3545">*</span> are required. 
                Your claim will be logged with your username and submission time.
            </div>
            
            <form method="POST" id="claimForm">
                <!-- Part Selection -->
                <div class="form-group">
                    <label for="part_used">Part Used <span class="required">*</span></label>
                    <select id="part_used" name="part_used" required>
                        <option value="">-- Select a Part --</option>
                        <?php foreach ($parts as $part): ?>
                            <option value="<?php echo htmlspecialchars($part['part_name']); ?>">
                                <?php echo htmlspecialchars($part['part_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Amount Used -->
                <div class="form-group">
                    <label for="amount_used">Amount Used <span class="required">*</span></label>
                    <input type="number" id="amount_used" name="amount_used" 
                           min="1" max="9999" required placeholder="Enter quantity">
                    <small>Enter a number between 1 and 9999</small>
                </div>
                
                <!-- Date Used -->
                <div class="form-group">
                    <label for="date_used">Date Used <span class="required">*</span></label>
                    <input type="date" id="date_used" name="date_used" required>
                    <small>Cannot be a future date</small>
                </div>
                
                <!-- Project ID -->
                <div class="form-group">
                    <label for="project_id">Project ID <span class="required">*</span></label>
                    <input type="text" id="project_id" name="project_id" 
                           maxlength="50" required placeholder="e.g., A5, B2, MAINT"
                           pattern="[A-Za-z0-9_-]{1,50}">
                    <small>Letters, numbers, hyphens and underscores only (max 50 characters)</small>
                </div>
                
                <!-- Comments -->
                <div class="form-group">
                    <label for="comments">Comments (Optional)</label>
                    <textarea id="comments" name="comments" 
                              maxlength="255" placeholder="Enter any additional details about the usage..."></textarea>
                    <div class="char-counter"><span id="charCount">0</span>/255 characters</div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" name="submit_claim" class="btn-submit">
                    ✅ Submit Claim
                </button>
            </form>
        </div>
    </div>
    
    <?php require_once '../includes/footer.php'; ?>
    
    <script>
        // Set max date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date_used').max = today;
        document.getElementById('date_used').value = today;
        
        // Character counter for comments
        const commentsField = document.getElementById('comments');
        const charCount = document.getElementById('charCount');
        
        commentsField.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
        
        // Form validation
        document.getElementById('claimForm').addEventListener('submit', function(e) {
            const amountUsed = document.getElementById('amount_used').value;
            const dateUsed = document.getElementById('date_used').value;
            
            if (amountUsed <= 0 || amountUsed > 9999) {
                e.preventDefault();
                alert('Amount must be between 1 and 9999');
                return;
            }
            
            if (new Date(dateUsed) > new Date()) {
                e.preventDefault();
                alert('Date cannot be in the future');
                return;
            }
        });
        
        // Clear form after successful submission (check for success message)
        <?php if ($messageType === 'success'): ?>
        document.getElementById('claimForm').reset();
        document.getElementById('date_used').value = today;
        charCount.textContent = '0';
        <?php endif; ?>
    </script>
</body>
</html>