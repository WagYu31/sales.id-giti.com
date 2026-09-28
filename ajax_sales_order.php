<?php
/**
 * ajax_sales_order.php
 * Backend Handler untuk Modul Pesanan Penjualan (Sales Order)
 * Format No SO: 2609.SOL.07025
 */

error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/db.php';

// Bersihkan output buffer jika ada output liar
if (ob_get_length()) ob_clean();

header('Content-Type: application/json; charset=utf-8');

// =========================================================================
// AUTO-MIGRATION / ENSURE TABLES EXIST
// =========================================================================
function ensureSalesOrderTables($conn) {
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

ensureSalesOrderTables($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// =========================================================================
// ACTION: GET NEXT SO NUMBER (Format: 2609.SOL.07025)
// =========================================================================
if ($action === 'get_next_so_number') {
    $yymm = date('ym'); // e.g. 2609
    $prefix = $yymm . ".SOL.";
    
    // Cari nomor terakhir dengan prefix bulan ini
    $q = $conn->query("SELECT so_number FROM sales_orders WHERE so_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) {
        $parts = explode('.', $row['so_number']);
        $lastSeq = intval(end($parts));
        $nextSeq = $lastSeq + 1;
    } else {
        // Jika belum ada di bulan ini, cek apakah ada SO sebelumnya untuk melanjutkan sequence
        $qAll = $conn->query("SELECT so_number FROM sales_orders WHERE so_number LIKE '%.SOL.%' ORDER BY id DESC LIMIT 1");
        if ($qAll && $rowAll = $qAll->fetch_assoc()) {
            $parts = explode('.', $rowAll['so_number']);
            $lastSeq = intval(end($parts));
            $nextSeq = $lastSeq + 1;
        } else {
            // Default awal sesuai contoh user: 07025
            $nextSeq = 7025;
        }
    }
    
    $generatedSo = $prefix . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
    echo json_encode(['success' => true, 'so_number' => $generatedSo]);
    exit;
}

// =========================================================================
// ACTION: SEARCH CUSTOMERS (FOR SELECT2)
// =========================================================================
if ($action === 'search_customers') {
    $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
    
    $where = "WHERE c.deleted_at IS NULL";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where .= " AND (c.nama_toko LIKE ? OR c.kategori LIKE ? OR ca.alamat LIKE ? OR ca.kota LIKE ? OR cp.nama_pic LIKE ?)";
        $s = "%$search%";
        $params = [$s, $s, $s, $s, $s];
        $types = "sssss";
    }
    
    $sql = "SELECT c.id, c.nama_toko, c.kategori, c.sales_id,
                   ca.alamat, ca.kota, ca.provinsi,
                   cp.nama_pic, cp.tlp_pic,
                   s.nama_lengkap as sales_name
            FROM customers c
            LEFT JOIN customer_addresses ca ON ca.customer_id = c.id AND ca.deleted_at IS NULL
            LEFT JOIN customer_pics cp ON cp.customer_id = c.id AND cp.deleted_at IS NULL
            LEFT JOIN sales s ON s.id = c.sales_id
            $where
            GROUP BY c.id
            ORDER BY c.nama_toko ASC
            LIMIT 30";
            
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    
    $results = [];
    while ($row = $res->fetch_assoc()) {
        $code = "[C." . str_pad($row['id'], 5, '0', STR_PAD_LEFT) . "]";
        $fullAddress = trim(($row['alamat'] ?? '') . ($row['kota'] ? ', ' . $row['kota'] : ''));
        
        $results[] = [
            'id' => $row['id'],
            'customer_code' => $code,
            'nama_toko' => $row['nama_toko'],
            'kategori' => $row['kategori'] ?? 'DEALER',
            'text' => "{$code} {$row['nama_toko']}" . ($row['kategori'] ? " — [{$row['kategori']}]" : ""),
            'alamat' => $fullAddress,
            'nama_pic' => $row['nama_pic'] ?? '',
            'tlp_pic' => $row['tlp_pic'] ?? '',
            'sales_id' => $row['sales_id'] ?? '',
            'sales_name' => $row['sales_name'] ?? ''
        ];
    }
    
    echo json_encode(['results' => $results]);
    exit;
}

// =========================================================================
// ACTION: SEARCH PRODUCTS (FOR CATALOG PICKER)
// =========================================================================
if ($action === 'search_products') {
    $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
    
    $where = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where .= " AND (category LIKE ? OR type LIKE ? OR description LIKE ?)";
        $s = "%$search%";
        $params = [$s, $s, $s];
        $types = "sss";
    }
    
    $sql = "SELECT id, category, type, description, msrp FROM product_prices $where ORDER BY category ASC, type ASC LIMIT 40";
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    
    $results = [];
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'category' => $row['category'],
            'type' => $row['type'],
            'code' => $row['type'],
            'name' => $row['description'] ? $row['description'] : $row['type'],
            'description' => $row['description'] ?? '',
            'msrp' => (float)$row['msrp'],
            'unit' => 'PCS',
            'text' => "[{$row['category']}] {$row['type']} — Rp " . number_format($row['msrp'], 0, ',', '.')
        ];
    }
    
    echo json_encode(['results' => $results]);
    exit;
}

// =========================================================================
// ACTION: SAVE SALES ORDER (INSERT OR UPDATE)
// =========================================================================
if ($action === 'save_sales_order') {
    $so_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    $so_number = trim($_POST['so_number'] ?? '');
    $so_date = trim($_POST['so_date'] ?? date('Y-m-d'));
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customer_code = trim($_POST['customer_code'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $customer_pic = trim($_POST['customer_pic'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    
    $sales_id = !empty($_POST['sales_id']) ? (int)$_POST['sales_id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
    $sales_name = trim($_POST['sales_name'] ?? ($_SESSION['nama_lengkap'] ?? ''));
    
    $payment_terms = trim($_POST['payment_terms'] ?? 'C.O.D');
    $po_number = trim($_POST['po_number'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? ($customer_address ?: ''));
    $shipping_date = !empty($_POST['shipping_date']) ? $_POST['shipping_date'] : null;
    $shipping_method = trim($_POST['shipping_method'] ?? '');
    $branch = trim($_POST['branch'] ?? 'Kantor Pusat');
    $currency = 'IDR';
    
    $is_taxable = !empty($_POST['is_taxable']) ? 1 : 0;
    $tax_inclusive = !empty($_POST['tax_inclusive']) ? 1 : 0;
    $tax_percent = 11.00;
    
    $discount_type = trim($_POST['discount_type'] ?? 'rp');
    $discount_val = (float)($_POST['discount_val'] ?? 0);
    $special_notes = trim($_POST['special_notes'] ?? '');
    $status = trim($_POST['status'] ?? 'Menunggu');
    
    // Parse Items JSON
    $itemsJson = $_POST['items'] ?? '[]';
    $items = json_decode($itemsJson, true);
    
    if (empty($so_number)) {
        echo json_encode(['success' => false, 'message' => 'Nomor Pesanan (No. SO) wajib diisi.']);
        exit;
    }
    
    if (empty($customer_name)) {
        echo json_encode(['success' => false, 'message' => 'Nama Customer / Toko wajib diisi.']);
        exit;
    }
    
    if (!is_array($items) || count($items) === 0) {
        echo json_encode(['success' => false, 'message' => 'Rincian barang pesanan tidak boleh kosong. Minimal tambahkan 1 barang.']);
        exit;
    }
    
    // Cek duplikasi nomor SO jika baru atau ganti nomor
    if ($so_id > 0) {
        $chkSo = $conn->prepare("SELECT id FROM sales_orders WHERE so_number = ? AND id != ? AND deleted_at IS NULL");
        $chkSo->bind_param("si", $so_number, $so_id);
    } else {
        $chkSo = $conn->prepare("SELECT id FROM sales_orders WHERE so_number = ? AND deleted_at IS NULL");
        $chkSo->bind_param("s", $so_number);
    }
    $chkSo->execute();
    if ($chkSo->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => "Nomor SO '{$so_number}' sudah digunakan. Silakan gunakan nomor lain atau klik perbarui."]);
        exit;
    }
    $chkSo->close();
    
    // Hitung Finansial (Subtotal, Diskon, Pajak, Grand Total)
    $subtotal = 0;
    $processedItems = [];
    
    foreach ($items as $itm) {
        $pId = !empty($itm['product_id']) ? (int)$itm['product_id'] : null;
        $iCode = trim($itm['item_code'] ?? '');
        $iName = trim($itm['item_name'] ?? '');
        $iDesc = trim($itm['item_description'] ?? '');
        $qty = max(1, (int)($itm['qty'] ?? 1));
        $unit = trim($itm['unit'] ?? 'PCS');
        $uPrice = (float)($itm['unit_price'] ?? 0);
        $discItem = (float)($itm['discount_item'] ?? 0);
        
        $lineTotal = $qty * max(0, ($uPrice - $discItem));
        $subtotal += $lineTotal;
        
        $processedItems[] = [
            'product_id' => $pId,
            'item_code' => $iCode,
            'item_name' => $iName ?: $iCode,
            'item_description' => $iDesc,
            'qty' => $qty,
            'unit' => $unit ?: 'PCS',
            'unit_price' => $uPrice,
            'discount_item' => $discItem,
            'total_price' => $lineTotal,
            'notes' => trim($itm['notes'] ?? '')
        ];
    }
    
    // Diskon Tambahan
    if ($discount_type === 'percent') {
        $discount_amount = ($subtotal * ($discount_val / 100));
    } else {
        $discount_amount = $discount_val;
    }
    if ($discount_amount > $subtotal) $discount_amount = $subtotal;
    
    $totalBeforeTax = max(0, $subtotal - $discount_amount);
    
    // Pajak PPN (11%)
    $tax_amount = 0;
    if ($is_taxable) {
        if ($tax_inclusive) {
            $tax_amount = $totalBeforeTax - ($totalBeforeTax / 1.11);
            $grand_total = $totalBeforeTax;
        } else {
            $tax_amount = $totalBeforeTax * 0.11;
            $grand_total = $totalBeforeTax + $tax_amount;
        }
    } else {
        $grand_total = $totalBeforeTax;
    }
    
    $conn->begin_transaction();
    try {
        if ($so_id > 0) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE sales_orders SET 
                so_number = ?, so_date = ?, customer_id = ?, customer_code = ?, customer_name = ?,
                customer_address = ?, customer_pic = ?, customer_phone = ?, sales_id = ?, sales_name = ?,
                payment_terms = ?, po_number = ?, shipping_address = ?, shipping_date = ?, shipping_method = ?,
                branch = ?, currency = ?, is_taxable = ?, tax_inclusive = ?, tax_percent = ?,
                subtotal = ?, discount_type = ?, discount_val = ?, discount_amount = ?, tax_amount = ?,
                grand_total = ?, special_notes = ?, status = ?
                WHERE id = ?");
            
            $stmt->bind_param("ssisssssissssssssiidddddsssi",
                $so_number, $so_date, $customer_id, $customer_code, $customer_name,
                $customer_address, $customer_pic, $customer_phone, $sales_id, $sales_name,
                $payment_terms, $po_number, $shipping_address, $shipping_date, $shipping_method,
                $branch, $currency, $is_taxable, $tax_inclusive, $tax_percent,
                $subtotal, $discount_type, $discount_val, $discount_amount, $tax_amount,
                $grand_total, $special_notes, $status, $so_id
            );
            $stmt->execute();
            $stmt->close();
            
            // Delete old items
            $del = $conn->prepare("DELETE FROM sales_order_items WHERE sales_order_id = ?");
            $del->bind_param("i", $so_id);
            $del->execute();
            $del->close();
            
            $finalSoId = $so_id;
        } else {
            // INSERT
            $created_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $stmt = $conn->prepare("INSERT INTO sales_orders (
                so_number, so_date, customer_id, customer_code, customer_name,
                customer_address, customer_pic, customer_phone, sales_id, sales_name,
                payment_terms, po_number, shipping_address, shipping_date, shipping_method,
                branch, currency, is_taxable, tax_inclusive, tax_percent,
                subtotal, discount_type, discount_val, discount_amount, tax_amount,
                grand_total, special_notes, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("ssisssssissssssssiidddddsssi",
                $so_number, $so_date, $customer_id, $customer_code, $customer_name,
                $customer_address, $customer_pic, $customer_phone, $sales_id, $sales_name,
                $payment_terms, $po_number, $shipping_address, $shipping_date, $shipping_method,
                $branch, $currency, $is_taxable, $tax_inclusive, $tax_percent,
                $subtotal, $discount_type, $discount_val, $discount_amount, $tax_amount,
                $grand_total, $special_notes, $status, $created_by
            );
            $stmt->execute();
            $finalSoId = $stmt->insert_id;
            $stmt->close();
        }
        
        // Insert items
        $ins = $conn->prepare("INSERT INTO sales_order_items (
            sales_order_id, product_id, item_code, item_name, item_description,
            qty, unit, unit_price, discount_item, total_price, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($processedItems as $rowItm) {
            $ins->bind_param("iisssisddds",
                $finalSoId,
                $rowItm['product_id'],
                $rowItm['item_code'],
                $rowItm['item_name'],
                $rowItm['item_description'],
                $rowItm['qty'],
                $rowItm['unit'],
                $rowItm['unit_price'],
                $rowItm['discount_item'],
                $rowItm['total_price'],
                $rowItm['notes']
            );
            $ins->execute();
        }
        $ins->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Pesanan Penjualan berhasil disimpan!',
            'so_id' => $finalSoId,
            'so_number' => $so_number
        ]);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// ACTION: UPDATE STATUS
// =========================================================================
if ($action === 'update_status') {
    $so_id = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    $allowed = ['Draft', 'Menunggu', 'Diproses', 'Selesai', 'Dibatalkan'];
    if (!in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid.']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE sales_orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $so_id);
    $res = $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => $res, 'message' => $res ? 'Status pesanan berhasil diperbarui.' : 'Gagal memperbarui status.']);
    exit;
}

// =========================================================================
// ACTION: DELETE SALES ORDER (SOFT DELETE)
// =========================================================================
if ($action === 'delete_sales_order') {
    $so_id = (int)($_POST['id'] ?? 0);
    if ($so_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID pesanan tidak valid.']);
        exit;
    }
    
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE sales_orders SET deleted_at = ? WHERE id = ?");
    $stmt->bind_param("si", $now, $so_id);
    $res = $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => $res, 'message' => $res ? 'Pesanan berhasil dihapus.' : 'Gagal menghapus pesanan.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
exit;
