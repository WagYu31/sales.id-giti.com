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

require_once 'includes/sales_order_helper.php';
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
    $results = [];
    $seenNames = [];

    // 1. CARI DARI DATABASE CANVAS (teknisi_api_root -> sales_customer)
    // Tempat customer dari menu "Customer Toko/Dealer" (seperti WAGYU A5) disimpan
    $connCanvas = null;
    if (file_exists(__DIR__ . '/modul-aplikasi-sales/conn.php')) {
        $connCanvas = (function() {
            mysqli_report(MYSQLI_REPORT_OFF);
            include __DIR__ . '/modul-aplikasi-sales/conn.php';
            return (isset($conn) && $conn && !$conn->connect_error) ? $conn : null;
        })();
    }

    if ($connCanvas) {
        $where = "WHERE deleted_at IS NULL";
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where .= " AND (nama LIKE ? OR kode_customer LIKE ? OR telp_pribadi LIKE ? OR alamat LIKE ? OR kota LIKE ?)";
            $s = "%$search%";
            $params = [$s, $s, $s, $s, $s];
            $types = "sssss";
        }
        
        $sql = "SELECT id, kode_customer, nama, kategori, telp_pribadi, email, alamat, kota
                FROM sales_customer
                $where
                ORDER BY id DESC
                LIMIT 30";
        $stmt = $connCanvas->prepare($sql);
        if ($stmt) {
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $nameKey = strtolower(trim($row['nama']));
                if (isset($seenNames[$nameKey])) continue;
                $seenNames[$nameKey] = true;

                $code = !empty($row['kode_customer']) ? "[{$row['kode_customer']}]" : "[C." . str_pad($row['id'], 5, '0', STR_PAD_LEFT) . "]";
                $fullAddress = trim(($row['alamat'] ?? '') . (!empty($row['kota']) ? ', ' . $row['kota'] : ''));
                $results[] = [
                    'id' => $row['id'],
                    'customer_code' => $row['kode_customer'] ?: $code,
                    'nama_toko' => $row['nama'],
                    'kategori' => $row['kategori'] ?? 'DEALER',
                    'text' => "{$code} {$row['nama']}" . ($row['kategori'] ? " — [{$row['kategori']}]" : ""),
                    'alamat' => $fullAddress,
                    'nama_pic' => $row['nama'],
                    'tlp_pic' => $row['telp_pribadi'] ?? '',
                    'sales_id' => '',
                    'sales_name' => ''
                ];
            }
            $stmt->close();
        }
    }

    // 2. CARI DARI DATABASE CRM (includes/db.php -> customers & addresses)
    $whereC = "WHERE c.deleted_at IS NULL";
    $paramsC = [];
    $typesC = "";
    
    if (!empty($search)) {
        $whereC .= " AND (c.nama_toko LIKE ? OR c.kategori LIKE ? OR ca.alamat LIKE ? OR ca.kota LIKE ? OR cp.nama_pic LIKE ?)";
        $s = "%$search%";
        $paramsC = [$s, $s, $s, $s, $s];
        $typesC = "sssss";
    }
    
    $sqlC = "SELECT c.id, c.nama_toko, c.kategori, c.sales_id,
                    ca.alamat, ca.kota, ca.provinsi,
                    cp.nama_pic, cp.tlp_pic,
                    COALESCE(s.nama_lengkap, '') as sales_name
             FROM customers c
             LEFT JOIN customer_addresses ca ON ca.customer_id = c.id AND ca.deleted_at IS NULL
             LEFT JOIN customer_pics cp ON cp.customer_id = c.id AND cp.deleted_at IS NULL
             LEFT JOIN sales s ON s.id = c.sales_id
             $whereC
             GROUP BY c.id
             ORDER BY c.nama_toko ASC
             LIMIT 30";
             
    $stmtC = $conn->prepare($sqlC);
    if ($stmtC) {
        if (!empty($typesC)) {
            $stmtC->bind_param($typesC, ...$paramsC);
        }
        $stmtC->execute();
        $resC = $stmtC->get_result();
        while ($row = $resC->fetch_assoc()) {
            $nameKey = strtolower(trim($row['nama_toko']));
            if (isset($seenNames[$nameKey])) continue;
            $seenNames[$nameKey] = true;

            $code = "[C." . str_pad($row['id'], 5, '0', STR_PAD_LEFT) . "]";
            $fullAddress = trim(($row['alamat'] ?? '') . ($row['kota'] ? ', ' . $row['kota'] : ''));
            
            $results[] = [
                'id' => $row['id'],
                'customer_code' => $code,
                'nama_toko' => $row['nama_toko'],
                'kategori' => $row['kategori'] ?? 'DEALER',
                'text' => "{$code} {$row['nama_toko']}" . ($row['kategori'] ? " — [{$row['kategori']}]" : ""),
                'alamat' => $fullAddress,
                'nama_pic' => $row['nama_pic'] ?: $row['nama_toko'],
                'tlp_pic' => $row['tlp_pic'] ?? '',
                'sales_id' => $row['sales_id'] ?? '',
                'sales_name' => $row['sales_name'] ?? ''
            ];
        }
        $stmtC->close();
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
        $where .= " AND (category LIKE ? OR type LIKE ? OR description LIKE ? OR item_code LIKE ?)";
        $s = "%$search%";
        $params = [$s, $s, $s, $s];
        $types = "ssss";
    }
    
    $sql = "SELECT id, category, type, item_code, description, unit, msrp FROM product_prices $where ORDER BY category ASC, type ASC LIMIT 50";
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    
    $results = [];
    while ($row = $res->fetch_assoc()) {
        $rawDesc = trim($row['description'] ?? '');
        $code = !empty($row['item_code']) ? $row['item_code'] : $row['type'];
        $unit = !empty($row['unit']) ? $row['unit'] : 'UNIT';
        $displayText = "[{$row['category']}] {$row['type']}" . (!empty($row['item_code']) ? " ({$row['item_code']})" : "") . ($row['msrp'] > 0 ? " — Rp " . number_format($row['msrp'], 0, ',', '.') : "");

        $results[] = [
            'id' => (int)$row['id'],
            'category' => $row['category'],
            'type' => $row['type'],
            'code' => $code,
            'name' => $row['type'],
            'description' => $rawDesc,
            'msrp' => (float)$row['msrp'],
            'unit' => $unit,
            'text' => $displayText
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
    
    // Normalize date format (handles YYYY-MM-DD or DD/MM/YYYY)
    $rawDate = trim($_POST['so_date'] ?? '');
    if (!empty($rawDate)) {
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $rawDate, $m)) {
            $so_date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        } else {
            $time = strtotime($rawDate);
            $so_date = $time ? date('Y-m-d', $time) : date('Y-m-d');
        }
    } else {
        $so_date = date('Y-m-d');
    }

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
    
    $rawShipDate = trim($_POST['shipping_date'] ?? '');
    $shipping_date = null;
    if (!empty($rawShipDate)) {
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $rawShipDate, $m)) {
            $shipping_date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        } else {
            $time = strtotime($rawShipDate);
            $shipping_date = $time ? date('Y-m-d', $time) : null;
        }
    }
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
    
    // SO Number is optional (can be left blank if Finance hasn't issued it yet)
    $so_number = !empty($so_number) ? $so_number : null;
    
    if (empty($customer_name)) {
        echo json_encode(['success' => false, 'message' => 'Nama Customer / Toko wajib diisi.']);
        exit;
    }
    
    if (!is_array($items) || count($items) === 0) {
        echo json_encode(['success' => false, 'message' => 'Rincian barang pesanan tidak boleh kosong. Minimal tambahkan 1 barang.']);
        exit;
    }
    
    // Cek duplikasi nomor SO HANYA jika nomor SO diisi
    if (!empty($so_number)) {
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
    }
    
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
        $discPercent = max(0, min(100, (float)($itm['discount_percent'] ?? 0)));
        if ($discPercent > 0) {
            $discItem = $uPrice * ($discPercent / 100);
        } else {
            $discItem = (float)($itm['discount_item'] ?? 0);
            if ($uPrice > 0 && $discItem > 0) {
                $discPercent = round(($discItem / $uPrice) * 100, 2);
            }
        }
        
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
            'discount_percent' => $discPercent,
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
            
            $stmt->bind_param("ssisssssissssssssiiddsddddssi",
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
            
            $stmt->bind_param("ssisssssissssssssiiddsddddssi",
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
            qty, unit, unit_price, discount_percent, discount_item, total_price, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($processedItems as $rowItm) {
            $ins->bind_param("iisssisdddds",
                $finalSoId,
                $rowItm['product_id'],
                $rowItm['item_code'],
                $rowItm['item_name'],
                $rowItm['item_description'],
                $rowItm['qty'],
                $rowItm['unit'],
                $rowItm['unit_price'],
                $rowItm['discount_percent'],
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
    } catch (Throwable $e) {
        if ($conn) $conn->rollback();
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
