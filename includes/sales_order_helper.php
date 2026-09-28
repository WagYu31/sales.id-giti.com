<?php
/**
 * includes/sales_order_helper.php
 * Helper & Schema Migration for Modul Pesanan Penjualan (Sales Order)
 */

if (!function_exists('ensureSalesOrderTables')) {
    function ensureSalesOrderTables($conn) {
        static $executed = false;
        if ($executed) return;
        $executed = true;

        if (!$conn) return;

        // 1. Table product_prices
        $conn->query("CREATE TABLE IF NOT EXISTS `product_prices` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `category` varchar(100) NOT NULL,
            `type` varchar(150) NOT NULL,
            `description` text DEFAULT NULL,
            `msrp` decimal(15,2) NOT NULL DEFAULT 0.00,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Seed default Loewix products if empty
        $chkP = $conn->query("SELECT COUNT(*) as cnt FROM `product_prices`");
        $cntP = ($chkP && $r = $chkP->fetch_assoc()) ? (int)$r['cnt'] : 0;
        if ($cntP === 0) {
            $seeds = [
                ['2MP AHD INDOOR', 'LX-4F320-CE', 'Kamera CCTV Loewix 2MP AHD Indoor CatEyes (LX-4F320-CE)', 145000],
                ['2MP AHD OUTDOOR', 'LX-50F320-CM', 'Kamera CCTV Loewix 2MP AHD Outdoor ColorMax (LX-50F320-CM)', 170000],
                ['2MP AHD INDOOR', 'LX-4F320-CM', 'Kamera CCTV Loewix 2MP AHD Indoor ColorMax (LX-4F320-CM)', 145000],
                ['2MP AHD OUTDOOR', 'LX-50F320-CE', 'Kamera CCTV Loewix 2MP AHD Outdoor CatEyes (LX-50F320-CE)', 170000],
                ['4MP IPCAM INDOOR', 'LX-IPF40CMT02', 'Kamera CCTV Loewix 4MP IP Camera Indoor (LX-IPF40CMT02)', 350000],
                ['4MP IPCAM OUTDOOR', 'LX-IPF40CMT17', 'Kamera CCTV Loewix 4MP IP Camera Outdoor (LX-IPF40CMT17)', 380000],
                ['AKSESORIS & KABEL', 'KABEL-RG59-POWER', 'Kabel Coaxial RG59 + Power Loewix 300 Meter', 650000],
                ['POWER SUPPLY', 'PSU-12V-10A', 'Power Supply Switching Jaring 12V 10A Loewix', 95000],
                ['POWER SUPPLY', 'PSU-12V-20A', 'Power Supply Switching Jaring 12V 20A Loewix', 150000],
                ['RECORDER DVR', 'DVR-4CH-5MP', 'Digital Video Recorder Loewix 4 Channel 5MP Hybrid', 450000],
                ['RECORDER DVR', 'DVR-8CH-5MP', 'Digital Video Recorder Loewix 8 Channel 5MP Hybrid', 650000],
                ['RECORDER NVR', 'NVR-8CH-4K', 'Network Video Recorder Loewix 8 Channel 4K PoE', 850000]
            ];
            $st = $conn->prepare("INSERT INTO product_prices (category, type, description, msrp) VALUES (?, ?, ?, ?)");
            if ($st) {
                foreach ($seeds as $s) {
                    $st->bind_param("sssd", $s[0], $s[1], $s[2], $s[3]);
                    $st->execute();
                }
                $st->close();
            }
        }

        // 2. Table sales_orders
        $conn->query("CREATE TABLE IF NOT EXISTS `sales_orders` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `so_number` varchar(50) NOT NULL,
            `so_date` date NOT NULL,
            `customer_id` int(11) DEFAULT NULL,
            `customer_code` varchar(50) DEFAULT NULL,
            `customer_name` varchar(255) NOT NULL,
            `customer_address` text DEFAULT NULL,
            `customer_pic` varchar(150) DEFAULT NULL,
            `customer_phone` varchar(50) DEFAULT NULL,
            `sales_id` int(11) DEFAULT NULL,
            `sales_name` varchar(150) DEFAULT NULL,
            `payment_terms` varchar(50) DEFAULT 'C.O.D',
            `po_number` varchar(100) DEFAULT NULL,
            `shipping_address` text DEFAULT NULL,
            `shipping_date` date DEFAULT NULL,
            `shipping_method` varchar(100) DEFAULT NULL,
            `branch` varchar(100) DEFAULT 'Kantor Pusat',
            `currency` varchar(10) DEFAULT 'IDR',
            `is_taxable` tinyint(1) DEFAULT 0,
            `tax_inclusive` tinyint(1) DEFAULT 0,
            `tax_percent` decimal(5,2) DEFAULT 11.00,
            `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
            `discount_type` varchar(10) DEFAULT 'rp',
            `discount_val` decimal(15,2) DEFAULT 0.00,
            `discount_amount` decimal(15,2) DEFAULT 0.00,
            `tax_amount` decimal(15,2) DEFAULT 0.00,
            `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
            `special_notes` text DEFAULT NULL,
            `status` enum('Draft','Menunggu','Diproses','Selesai','Dibatalkan') DEFAULT 'Menunggu',
            `created_by` int(11) DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_so_number` (`so_number`),
            KEY `idx_customer` (`customer_id`),
            KEY `idx_sales` (`sales_id`),
            KEY `idx_date` (`so_date`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. Table sales_order_items
        $conn->query("CREATE TABLE IF NOT EXISTS `sales_order_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `sales_order_id` int(11) NOT NULL,
            `product_id` int(11) DEFAULT NULL,
            `item_code` varchar(100) DEFAULT NULL,
            `item_name` varchar(255) NOT NULL,
            `item_description` text DEFAULT NULL,
            `qty` int(11) NOT NULL DEFAULT 1,
            `unit` varchar(50) DEFAULT 'PCS',
            `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
            `discount_item` decimal(15,2) DEFAULT 0.00,
            `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
            `notes` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_sales_order` (`sales_order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
}
