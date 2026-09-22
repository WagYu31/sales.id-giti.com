<?php
/**
 * api_db.php - Shared Database Connection for Mobile Sales APIs
 */
mysqli_report(MYSQLI_REPORT_OFF);
$host = 'localhost';

// 1. Try local dev
$conn = @new mysqli($host, 'root', '', 'sales_id_giti');

// 2. Fallback to production server credentials
if ($conn->connect_error) {
    $user_prod = 'u836263092_sales';
    $pass_prod = 'bkmRa2a5bDfwZLYX';
    $db_prod   = 'u836263092_sales';
    $conn = @new mysqli($host, $user_prod, $pass_prod, $db_prod);
    
    if ($conn->connect_error) {
        $conn = @new mysqli($host, $user_prod, $pass_prod, 'sales_id_giti');
    }
}

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$conn->set_charset('utf8');
date_default_timezone_set('Asia/Jakarta');
$conn->query("SET time_zone = '+07:00'");
?>
