<?php
/**
 * conn.php - Modul Aplikasi Sales
 * Terhubung ke database sales.id-giti.com & teknisi_api_root
 * Dioptimalkan untuk performa tinggi (Fast execution, no redundant DDL overhead)
 */

mysqli_report(MYSQLI_REPORT_OFF);
$host = 'localhost';

// 1. Prioritas Utama: Database Aplikasi Sales / Jadwal (teknisi_api_root di server)
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
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Set karakter set ke UTF-8 & timezone Jakarta
mysqli_set_charset($conn, "utf8mb4");
date_default_timezone_set('Asia/Jakarta');
$now = date('Y-m-d H:i:s');

// ── Jalankan Migrasi Hanya Jika Parameter ?run_migration=1 Diberikan ──────────
if (isset($_GET['run_migration']) && $_GET['run_migration'] === '1') {
    // 1. Core Tables
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `sales_customer` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama` VARCHAR(255) NOT NULL,
        `kategori` VARCHAR(100) NULL DEFAULT 'Toko',
        `email` VARCHAR(150) NULL,
        `alamat` TEXT NULL,
        `kota` VARCHAR(100) NULL,
        `id_wilayah` INT NULL DEFAULT 0,
        `telp_pribadi` VARCHAR(50) NULL,
        `lat` VARCHAR(50) NULL,
        `lon` VARCHAR(50) NULL,
        `rad` VARCHAR(50) NULL DEFAULT '100',
        `alamat_lokasi` TEXT NULL,
        `foto` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        INDEX (`nama`),
        INDEX (`kota`),
        INDEX (`id_wilayah`),
        INDEX (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `kegiatan_sales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_customer` INT NOT NULL,
        `jadwal` DATETIME NOT NULL,
        `keterangan` TEXT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'dijadwalkan',
        `kode` VARCHAR(50) NULL,
        `rescheduled_from` INT NULL DEFAULT NULL,
        `reschedule_reason` TEXT NULL DEFAULT NULL,
        `lat` VARCHAR(50) NULL,
        `lon` VARCHAR(50) NULL,
        `rad` VARCHAR(50) NULL DEFAULT '100',
        `alamat_lokasi` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        INDEX (`id_customer`),
        INDEX (`jadwal`),
        INDEX (`status`),
        INDEX (`rescheduled_from`),
        INDEX (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `team_kegiatan_sales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_kegiatan_sales` INT NOT NULL,
        `id_sales` INT NOT NULL,
        `nama_sales` VARCHAR(150) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        INDEX (`id_kegiatan_sales`),
        INDEX (`id_sales`),
        INDEX (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pelaksanaan_sales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `kegiatan_id` INT NOT NULL,
        `sales_id` INT NULL,
        `id_sales` INT NULL,
        `nama_sales` VARCHAR(150) NULL,
        `ci_at` DATETIME NULL DEFAULT NULL,
        `co_at` DATETIME NULL DEFAULT NULL,
        `lat_ci` VARCHAR(30) NULL DEFAULT NULL,
        `lon_ci` VARCHAR(30) NULL DEFAULT NULL,
        `lat_co` VARCHAR(30) NULL DEFAULT NULL,
        `lon_co` VARCHAR(30) NULL DEFAULT NULL,
        `catatan_visit` TEXT NULL DEFAULT NULL,
        `nama_client` VARCHAR(100) NULL DEFAULT NULL,
        `nomer_client` VARCHAR(30) NULL DEFAULT NULL,
        `tipe_prospek` VARCHAR(30) NULL DEFAULT 'Biasa',
        `no_invoice` VARCHAR(100) NULL DEFAULT NULL,
        `foto` TEXT NULL,
        `image_1` VARCHAR(255) NULL,
        `image_2` VARCHAR(255) NULL,
        `image_3` VARCHAR(255) NULL,
        `image_4` VARCHAR(255) NULL,
        `image_5` VARCHAR(255) NULL,
        `status` VARCHAR(50) DEFAULT 'selesai',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        INDEX (`kegiatan_id`),
        INDEX (`sales_id`),
        INDEX (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
?>
