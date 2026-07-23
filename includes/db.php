<?php

$host_only = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
$is_local = (in_array($host_only, ['localhost', '127.0.0.1']) || php_sapi_name() === 'cli');

mysqli_report(MYSQLI_REPORT_OFF);

if ($is_local) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db_name = 'sales_id_giti';
    $conn = @new mysqli($host, $user, $pass, $db_name);
} else {
    $host = 'localhost';
    $user = 'u836263092_sales';
    $pass = 'bkmRa2a5bDfwZLYX';
    $db_name = 'u836263092_sales';
    
    $conn = @new mysqli($host, $user, $pass, $db_name);
    
    if ($conn->connect_error) {
        $conn = @new mysqli($host, $user, $pass, 'sales_id_giti');
    }
}

if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>