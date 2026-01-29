<?php
/**
 * Session Timeout & Security Check
 *
 * This script handles automatic logout after inactivity.
 * It should be included at the very top of every secure page (after config).
 */

// Ensure session is started (if not already)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Configuration
// Timeout duration in seconds (e.g., 1800 seconds = 30 minutes)
$timeout_duration = 1800; 

// 2. Check for Timeout
if (isset($_SESSION['last_activity'])) {
    // Calculate seconds since last activity
    $seconds_inactive = time() - $_SESSION['last_activity'];
    
    // Check if the user has been inactive too long
    if ($seconds_inactive >= $timeout_duration) {
        
        // --- TIMEOUT OCCURRED ---
        
        // Unset all session variables
        session_unset(); 
        
        // Destroy the session data on the server
        session_destroy(); 
        
        // Redirect to login page with a feedback message
        // Note: You might need to adjust the path based on your file structure
        header("Location: ../pages/login.php?timeout=true");
        exit;
    }
}

// 3. Update Last Activity Timestamp
// Update the session with the current time for the next check
$_SESSION['last_activity'] = time();
?>