<?php
/**
 * conn.php - Modul Aplikasi Sales
 * Terhubung ke database sales.id-giti.com
 */

if (file_exists(__DIR__ . '/../includes/db.php')) {
    require_once __DIR__ . '/../includes/db.php';
} else {
    $host_only = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    $is_local = (in_array($host_only, ['localhost', '127.0.0.1']) || php_sapi_name() === 'cli');

    mysqli_report(MYSQLI_REPORT_OFF);
    $host = 'localhost';

    // 1. Try local dev credentials
    $conn = @new mysqli($host, 'root', '', 'sales_id_giti');

    // 2. Fallback to production credentials
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
        die("Koneksi gagal: " . $conn->connect_error);
    }
}

// Set karakter set ke UTF-8 & timezone Jakarta
mysqli_set_charset($conn, "utf8");
date_default_timezone_set('Asia/Jakarta');
$now = date('Y-m-d H:i:s');

// ── 1. Auto Migration: Tabel Core Aplikasi Sales ────────────────────────────
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
    `status` VARCHAR(50) DEFAULT 'selesai',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX (`kegiatan_id`),
    INDEX (`id_sales`),
    INDEX (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ── 2. Auto Migration: Kolom Tambahan Clock-in/out di pelaksanaan_sales ────
$salesMigrations = [
    "ci_at"        => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `ci_at` DATETIME NULL DEFAULT NULL COMMENT 'Waktu Clock In Sales'",
    "co_at"        => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `co_at` DATETIME NULL DEFAULT NULL COMMENT 'Waktu Clock Out Sales'",
    "lat_ci"       => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `lat_ci` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Latitude saat Clock In'",
    "lon_ci"       => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `lon_ci` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Longitude saat Clock In'",
    "lat_co"       => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `lat_co` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Latitude saat Clock Out'",
    "lon_co"       => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `lon_co` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Longitude saat Clock Out'",
    "catatan_visit"=> "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `catatan_visit` TEXT NULL DEFAULT NULL COMMENT 'Catatan hasil kunjungan'",
    "nama_client"  => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `nama_client` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nama Client / Kontak Kunjungan'",
    "nomer_client" => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `nomer_client` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Nomor Telepon Client / Kontak'",
    "tipe_prospek" => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `tipe_prospek` VARCHAR(30) NULL DEFAULT 'Biasa' COMMENT 'Kategori prospek customer'",
    "no_invoice"   => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `no_invoice` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nomor invoice opsional jika ada transaksi'",
];
foreach ($salesMigrations as $col => $sql) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM `pelaksanaan_sales` LIKE '$col'");
    if ($chk && mysqli_num_rows($chk) == 0) {
        mysqli_query($conn, $sql);
    }
}

// ── 3. Auto Migration: Rescheduled columns di kegiatan_sales ───────────────
$checkReschedFrom = mysqli_query($conn, "SHOW COLUMNS FROM `kegiatan_sales` LIKE 'rescheduled_from'");
if ($checkReschedFrom && mysqli_num_rows($checkReschedFrom) == 0) {
    mysqli_query($conn, "ALTER TABLE `kegiatan_sales` ADD COLUMN `rescheduled_from` INT NULL DEFAULT NULL COMMENT 'Reference ke ID kegiatan lama yang di-reschedule'");
}
$checkReschedReason = mysqli_query($conn, "SHOW COLUMNS FROM `kegiatan_sales` LIKE 'reschedule_reason'");
if ($checkReschedReason && mysqli_num_rows($checkReschedReason) == 0) {
    mysqli_query($conn, "ALTER TABLE `kegiatan_sales` ADD COLUMN `reschedule_reason` TEXT NULL DEFAULT NULL COMMENT 'Alasan reschedule'");
}

// ── 4. Auto Migration: TIP TOK Tables ──────────────────────────────────────
$tiptokTables = [
    "tiptok_penitipan" => "CREATE TABLE IF NOT EXISTS `tiptok_penitipan` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `kode_titip` VARCHAR(50) NOT NULL UNIQUE,
        `id_customer` INT NOT NULL,
        `id_sales` INT NULL,
        `nama_sales` VARCHAR(100) NULL,
        `tgl_titip` DATE NOT NULL,
        `status` ENUM('aktif', 'selesai', 'ditarik') DEFAULT 'aktif',
        `catatan` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`id_customer`),
        INDEX (`id_sales`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "tiptok_items" => "CREATE TABLE IF NOT EXISTS `tiptok_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_penitipan` INT NOT NULL,
        `kode_titip` VARCHAR(50) NOT NULL,
        `nama_barang` VARCHAR(255) NOT NULL,
        `tipe_barang` VARCHAR(100) NULL,
        `qty_titip` INT NOT NULL DEFAULT 0,
        `qty_sisa` INT NOT NULL DEFAULT 0,
        `qty_terjual` INT NOT NULL DEFAULT 0,
        `insentif_per_unit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_insentif` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `status_item` ENUM('titip', 'habis_terjual', 'ditarik') DEFAULT 'titip',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`id_penitipan`),
        INDEX (`kode_titip`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "tiptok_kunjungan" => "CREATE TABLE IF NOT EXISTS `tiptok_kunjungan` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `kode_kunjungan` VARCHAR(50) NOT NULL UNIQUE,
        `id_penitipan` INT NOT NULL,
        `id_item` INT NOT NULL,
        `id_sales` INT NULL,
        `nama_sales` VARCHAR(100) NULL,
        `tgl_kunjungan` DATE NOT NULL,
        `stok_sebelumnya` INT NOT NULL,
        `stok_sisa` INT NOT NULL,
        `qty_terjual_kunjungan` INT NOT NULL DEFAULT 0,
        `no_inv` VARCHAR(100) NULL,
        `tgl_invoice` DATE NULL,
        `insentif_didapat` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `catatan_kunjungan` TEXT NULL,
        `foto_kunjungan` VARCHAR(255) NULL,
        `id_claim` INT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`id_penitipan`),
        INDEX (`id_item`),
        INDEX (`id_sales`),
        INDEX (`id_claim`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "tiptok_claim" => "CREATE TABLE IF NOT EXISTS `tiptok_claim` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `kode_claim` VARCHAR(50) NOT NULL UNIQUE,
        `id_sales` INT NOT NULL,
        `nama_sales` VARCHAR(100) NOT NULL,
        `tgl_claim` DATE NOT NULL,
        `total_unit_terjual` INT NOT NULL,
        `total_nominal_insentif` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `status_claim` ENUM('menunggu_approval', 'disetujui', 'cair', 'ditolak') DEFAULT 'menunggu_approval',
        `tgl_cair` DATE NULL,
        `catatan_claim` TEXT NULL,
        `catatan_admin` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`id_sales`),
        INDEX (`status_claim`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "tiptok_claim_detail" => "CREATE TABLE IF NOT EXISTS `tiptok_claim_detail` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_claim` INT NOT NULL,
        `id_kunjungan_log` INT NOT NULL,
        `id_penitipan` INT NOT NULL,
        `id_item` INT NOT NULL,
        `nama_barang` VARCHAR(255) NOT NULL,
        `no_inv` VARCHAR(100) NULL,
        `qty_terjual` INT NOT NULL,
        `insentif_per_unit` DECIMAL(15,2) NOT NULL,
        `subtotal_insentif` DECIMAL(15,2) NOT NULL,
        INDEX (`id_claim`),
        INDEX (`id_kunjungan_log`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];
foreach ($tiptokTables as $tbl => $sql) {
    mysqli_query($conn, $sql);
}

// ── 5. Auto Migration: Tabel user_sales, wilayah, dan kolom sales ─────────
$salesTableMigrations = [
    "nama"        => "ALTER TABLE `sales` ADD COLUMN `nama` VARCHAR(150) NULL DEFAULT NULL AFTER `nama_lengkap`",
    "nik"         => "ALTER TABLE `sales` ADD COLUMN `nik` VARCHAR(50) NULL DEFAULT NULL AFTER `nama`",
    "telp"        => "ALTER TABLE `sales` ADD COLUMN `telp` VARCHAR(30) NULL DEFAULT NULL AFTER `email`",
    "id_wilayah"  => "ALTER TABLE `sales` ADD COLUMN `id_wilayah` INT NULL DEFAULT 0 AFTER `telp`",
    "foto"        => "ALTER TABLE `sales` ADD COLUMN `foto` VARCHAR(255) NULL DEFAULT NULL AFTER `password`",
    "jabatan"     => "ALTER TABLE `sales` ADD COLUMN `jabatan` VARCHAR(50) NULL DEFAULT 'Sales' AFTER `role`",
];
foreach ($salesTableMigrations as $col => $sql) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM `sales` LIKE '$col'");
    if ($chk && mysqli_num_rows($chk) == 0) {
        mysqli_query($conn, $sql);
    }
}
mysqli_query($conn, "UPDATE `sales` SET `nama` = `nama_lengkap` WHERE (`nama` IS NULL OR `nama` = '') AND `nama_lengkap` IS NOT NULL");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `user_sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sales_id` INT NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `nama` VARCHAR(150) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX (`sales_id`),
    INDEX (`username`),
    INDEX (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wilayah` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ── 6. Auto-fix reschedule ────────────────────────────────────────────────
mysqli_query($conn, "UPDATE kegiatan_sales SET status = 'dibatalkan' WHERE id IN (SELECT rescheduled_from FROM (SELECT DISTINCT rescheduled_from FROM kegiatan_sales WHERE rescheduled_from IS NOT NULL AND deleted_at IS NULL) AS t) AND status != 'dibatalkan'");
?>
