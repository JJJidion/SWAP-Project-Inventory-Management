<?php
/**
 * Common Utility Functions
 *
 * This file contains reusable helper functions used throughout the application
 */

/**
 * Display a header with file information and VS Code button
 * Helps students identify which file they're viewing and quickly open it in VS Code
 *
 * @param string $filePath The path of the current file (use __FILE__)
 */
function dev_header($filePath)
{
    $absolutePath = realpath($filePath);
    $fileName = basename($filePath);
    $relativeFromRoot = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', $absolutePath);
    $relativeFromRoot = str_replace('\\', '/', $relativeFromRoot);

    // VS Code URL scheme
    $vscodeUrl = "vscode://file/" . $absolutePath;

    echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
    echo '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">';
    echo '<div style="color: white; flex: 1; min-width: 200px;">';
    echo '<div style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">📄 ' . htmlspecialchars($fileName) . '</div>';
    echo '<div style="font-size: 12px; opacity: 0.9; font-family: monospace;">' . htmlspecialchars($relativeFromRoot) . '</div>';
    echo '</div>';
    echo '<a href="' . htmlspecialchars($vscodeUrl) . '" style="background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; margin-top: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: all 0.3s;" onmouseover="this.style.background=\'#1976D2\'; this.style.transform=\'translateY(-2px)\'" onmouseout="this.style.background=\'#2196F3\'; this.style.transform=\'translateY(0)\'">';
    echo '💻 Open in VS Code';
    echo '</a>';
    echo '</div>';
    echo '</div>';
    echo '<hr style="border: none; border-top: 3px solid #667eea; margin: 0 0 20px 0;">';
}

/**
 * Display session and cookie information for debugging
 * Useful for learning how sessions and cookies work
 */
function session_info()
{
    echo "<pre>";
    echo "--------------------------------------------<br>";
    echo "_SESSION<br>";
    print_r($_SESSION);
    echo "_COOKIE<br>";
    print_r($_COOKIE);
    echo "session_name() = " . session_name() . "<br>";
    echo "session_id() = " . session_id() . "<br>";
    echo "</pre>";
}

/**
 * Validate POST data with optional pattern matching
 *
 * This function demonstrates:
 * - Form input validation
 * - Mandatory vs optional field checking
 * - Regular expression pattern matching
 * - Guard clause pattern (check if not valid first)
 *
 * @param string $input The name of the POST field to check
 * @param bool $mandatory Whether the field is required
 * @param string $pattern Optional regex pattern to validate against
 * @return bool True if validation passes, false otherwise
 */
function checkPost($input, $mandatory, $pattern = "")
{
    $inputValue = isset($_POST[$input]) ? $_POST[$input] : '';

    // Check if field is empty
    if (empty($inputValue)) {
        echo "<pre>$input field is empty<br></pre>";
        // If mandatory, validation fails
        if ($mandatory) {
            return false;
        }
        // Not mandatory, empty is acceptable
        echo "<pre>but $input is not mandatory<br></pre>";
        return true;
    }

    // Validate against pattern if provided
    if (strlen($pattern) > 0) {
        $isMatch = preg_match($pattern, $inputValue);
        // If pattern doesn't match
        if (!$isMatch) {
            echo "<pre>$input field has wrong format<br></pre>";
            //Validation fails
            return false;
        }
    }

    // All validations passed
    return true;
}
?>
