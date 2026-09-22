<?php
/**
 * api_db.php - Shared Database Connection for Mobile Sales APIs
 * Terhubung ke database jadwal.id-giti.com (teknisi_api_root)
 */
mysqli_report(MYSQLI_REPORT_OFF);
$host = 'localhost';

// 1. Prioritas Utama: Database Aplikasi Sales (teknisi_api_root di server)
$conn = @new mysqli($host, 'teknisi_api_root', 'OffOff@18', 'teknisi_api_root');

if ($conn->connect_error) {
    $conn = @new mysqli($host, 'teknisi_api_root', 'WagyuA531052002.', 'teknisi_api_root');
}

// 2. Prioritas Kedua: u836263092_jadwaltest
if ($conn->connect_error) {
    $conn = @new mysqli($host, 'u836263092_jadwaltest', 'Eddie@1819', 'u836263092_jadwalTest');
}

// 3. Prioritas Ketiga: Local dev root
if ($conn->connect_error) {
    $conn = @new mysqli($host, 'root', '', 'teknisi_api_root');
    if ($conn->connect_error) {
        $conn = @new mysqli($host, 'root', '', 'sales_id_giti');
    }
}

// 4. Fallback Terakhir: Database sales_id_giti produksi
if ($conn->connect_error) {
    $conn = @new mysqli($host, 'u836263092_sales', 'bkmRa2a5bDfwZLYX', 'u836263092_sales');
}

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$conn->set_charset('utf8mb4');
date_default_timezone_set('Asia/Jakarta');
$conn->query("SET time_zone = '+07:00'");
?>
