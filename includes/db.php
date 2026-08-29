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

// Auto-ensure nominal_invoice column exists in follow_ups table
$chk_col = $conn->query("SHOW COLUMNS FROM `follow_ups` LIKE 'nominal_invoice'");
if ($chk_col && $chk_col->num_rows === 0) {
    $conn->query("ALTER TABLE `follow_ups` ADD COLUMN `nominal_invoice` DECIMAL(15,2) DEFAULT 0 AFTER `no_inv`");
}

// Auto-ensure sales_work_plans table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `sales_work_plans` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `sales_id` int(11) NOT NULL,
      `tanggal` date NOT NULL,
      `nama_customer` varchar(255) NOT NULL,
      `customer_id` int(11) DEFAULT NULL,
      `aktivitas` text DEFAULT NULL,
      `kontak_customer` varchar(100) DEFAULT NULL,
      `email_customer` varchar(100) DEFAULT NULL,
      `metode_fu` varchar(100) NOT NULL DEFAULT 'Text Whatsapp',
      `hasil_fu` text DEFAULT NULL,
      `is_done` tinyint(1) NOT NULL DEFAULT 0,
      `verified_by` int(11) DEFAULT NULL,
      `verified_at` datetime DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
      `deleted_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_sales_tanggal` (`sales_id`, `tanggal`),
      KEY `idx_is_done` (`is_done`),
      KEY `idx_deleted_at` (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
?>