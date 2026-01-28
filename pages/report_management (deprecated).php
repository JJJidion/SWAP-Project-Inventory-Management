<?php
/**
 * Reports Management Page: For Generating Reports
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/dompdf/autoload.inc.php'; // Importing the Dompdf Library

$pageTitle = 'Report Management';

if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit;
}

// Below is the section to test whether the dompdf library is working

use Dompdf\Dompdf; // Classes for objects so that Dompdf\Dompdf does not need to be used every time a new object is created

$html = '<img src = "includes/TPAMC Logo.png"';
$html .= '<h1 style="color: blue">Example</h1>';
$html .= "Hello <em>world</em>";

$dompdf = new Dompdf; 

$dompdf->loadHtml($html); // Passing HTML into the converter to create a pdf

$dompdf->render(); // To generate the pdf file in memory

//$dompdf->stream("amc_report.pdf"); // Sends pdf to the browser and downloads it onto the local computer immediately

$dompdf->stream("amc_report.pdf", ["Attachment" => 0]); // Shows the pdf in the browser's built-in pdf viewer

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <title>Report Management</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <?php require_once '../includes/footer.php'; ?>
</body>
</html>