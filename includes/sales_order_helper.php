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
            `category` varchar(150) NOT NULL,
            `type` varchar(255) NOT NULL,
            `item_code` varchar(50) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `unit` varchar(20) DEFAULT 'UNIT',
            `msrp` decimal(15,2) NOT NULL DEFAULT 0.00,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX (`category`),
            INDEX (`type`),
            INDEX (`item_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure new columns exist
        $cols = [
            'item_code' => "ALTER TABLE `product_prices` ADD COLUMN `item_code` VARCHAR(50) NULL AFTER `type`",
            'unit' => "ALTER TABLE `product_prices` ADD COLUMN `unit` VARCHAR(20) NULL DEFAULT 'UNIT' AFTER `description`",
            'created_at' => "ALTER TABLE `product_prices` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ];
        foreach ($cols as $col => $sql) {
            $chkCol = $conn->query("SHOW COLUMNS FROM `product_prices` LIKE '$col'");
            if ($chkCol && $chkCol->num_rows == 0) {
                @$conn->query($sql);
            }
        }
        @$conn->query("ALTER TABLE `product_prices` MODIFY COLUMN `type` VARCHAR(255) NOT NULL");
        @$conn->query("ALTER TABLE `product_prices` MODIFY COLUMN `category` VARCHAR(150) NOT NULL");

        // Auto-seed/sync from official 515 catalog JSON if empty or old seed (<500 items)
        $chkP = $conn->query("SELECT COUNT(*) as cnt FROM `product_prices`");
        $cntP = ($chkP && $r = $chkP->fetch_assoc()) ? (int)$r['cnt'] : 0;
        $jsonFile = __DIR__ . '/catalog_products.json';
        if ($cntP < 500 && file_exists($jsonFile)) {
            $jsonStr = file_get_contents($jsonFile);
            $catItems = json_decode($jsonStr, true);
            if (is_array($catItems) && count($catItems) > 0) {
                $conn->query("TRUNCATE TABLE `product_prices`");
                $st = $conn->prepare("INSERT INTO `product_prices` (category, type, item_code, description, unit, msrp) VALUES (?, ?, ?, ?, ?, ?)");
                if ($st) {
                    foreach ($catItems as $it) {
                        $cat = $it['category'];
                        $typ = $it['type'];
                        $code = $it['item_code'] ?? null;
                        $desc = $it['description'] ?? '';
                        $unt = $it['unit'] ?? 'UNIT';
                        $prc = (float)($it['msrp'] ?? 0);
                        $st->bind_param("sssssd", $cat, $typ, $code, $desc, $unt, $prc);
                        $st->execute();
                    }
                    $st->close();
                }
            }
        }

        // 2. Table sales_orders
        $conn->query("CREATE TABLE IF NOT EXISTS `sales_orders` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `so_number` varchar(50) DEFAULT NULL,
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

        // Ensure so_number allows NULL for orders where SO number from finance is pending/delayed
        $chkColSo = $conn->query("SHOW COLUMNS FROM `sales_orders` LIKE 'so_number'");
        if ($chkColSo && $rColSo = $chkColSo->fetch_assoc()) {
            if (strtoupper($rColSo['Null']) === 'NO') {
                @$conn->query("ALTER TABLE `sales_orders` MODIFY COLUMN `so_number` VARCHAR(50) NULL DEFAULT NULL");
            }
        }

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
            `discount_percent` decimal(5,2) DEFAULT 0.00,
            `discount_item` decimal(15,2) DEFAULT 0.00,
            `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
            `notes` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_sales_order` (`sales_order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Ensure discount_percent exists in sales_order_items
        $chkColDiscPct = $conn->query("SHOW COLUMNS FROM `sales_order_items` LIKE 'discount_percent'");
        if ($chkColDiscPct && $chkColDiscPct->num_rows === 0) {
            @$conn->query("ALTER TABLE `sales_order_items` ADD COLUMN `discount_percent` decimal(5,2) DEFAULT 0.00 AFTER `unit_price`");
        }

        // 4. Table product_package_bundles
        $conn->query("CREATE TABLE IF NOT EXISTS `product_package_bundles` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `package_code` varchar(50) NOT NULL,
            `package_name` varchar(255) NOT NULL,
            `item_code` varchar(50) NOT NULL,
            `item_name` varchar(255) NOT NULL,
            `qty` int(11) NOT NULL DEFAULT 1,
            `unit` varchar(20) DEFAULT 'UNIT',
            `sort_order` int(11) DEFAULT 0,
            PRIMARY KEY (`id`),
            INDEX (`package_code`),
            INDEX (`item_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $chkBundle = $conn->query("SELECT COUNT(*) as cnt FROM `product_package_bundles`");
        if ($chkBundle && ($rB = $chkBundle->fetch_assoc()) && (int)$rB['cnt'] === 0) {
            $jsonFile = __DIR__ . '/package_bundles.json';
            if (file_exists($jsonFile)) {
                $bundles = json_decode(file_get_contents($jsonFile), true);
                if (is_array($bundles)) {
                    $st = $conn->prepare("INSERT INTO `product_package_bundles` (package_code, package_name, item_code, item_name, qty, unit, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($st) {
                        foreach ($bundles as $pkgCode => $pkg) {
                            $pkgName = $pkg['package_name'] ?? '';
                            foreach (($pkg['items'] ?? []) as $sIdx => $it) {
                                $sort = $sIdx + 1;
                                $st->bind_param("ssssisi", $pkgCode, $pkgName, $it['code'], $it['name'], $it['qty'], $it['unit'], $sort);
                                $st->execute();
                            }
                        }
                        $st->close();
                    }
                }
            }
        }
    }
}
