<?php
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
    exit;
}

// Safeguard: Pastikan tabel TIP TOK sudah ada sebelum query
$checkTbl = mysqli_query($conn, "SHOW TABLES LIKE 'tiptok_penitipan'");
if (!$checkTbl || mysqli_num_rows($checkTbl) == 0) {
    if (isset($tiptokTables) && is_array($tiptokTables)) {
        foreach ($tiptokTables as $tbl => $sql) {
            mysqli_query($conn, $sql);
        }
    } else {
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tiptok_penitipan` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tiptok_items` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tiptok_kunjungan` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tiptok_claim` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `tiptok_claim_detail` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
}

$idUser = $_SESSION['id'];
$namaUser = $_SESSION['nama'] ?? ($nmUser ?? 'Sales');
$jabatanUser = $_SESSION['jabatan'] ?? ($role ?? 'Sales');

$hasSalesCustomer = false;
$chkSC = $conn->query("SHOW TABLES LIKE 'sales_customer'");
if ($chkSC && $chkSC->num_rows > 0) {
    $hasSalesCustomer = true;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if (empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak ditentukan.']);
    exit;
}

// -------------------------------------------------------------
// 1. CARI CUSTOMER DEALER (AUTOCOMPLETE / SELECT)
// -------------------------------------------------------------
if ($action === 'search_dealer') {
    $q = trim($_GET['q'] ?? '');
    $qLike = "%$q%";
    
    // Check if table tiptok_penitipan exists
    $hasTiptokTbl = false;
    $chkTiptok = @$conn->query("SHOW TABLES LIKE 'tiptok_penitipan'");
    if ($chkTiptok && $chkTiptok->num_rows > 0) {
        $hasTiptokTbl = true;
    }

    if ($hasSalesCustomer) {
        // Ensure is_tiptok column exists
        $colCheck = @$conn->query("SHOW COLUMNS FROM sales_customer LIKE 'is_tiptok'");
        if (!$colCheck || $colCheck->num_rows == 0) {
            @$conn->query("ALTER TABLE sales_customer ADD COLUMN is_tiptok TINYINT(1) NOT NULL DEFAULT 0 AFTER kategori");
        }

        // Only show stores marked as TIP TOK or with existing consignment records
        $whereTiptok = " (is_tiptok = 1";
        if ($hasTiptokTbl) {
            $whereTiptok .= " OR id IN (SELECT DISTINCT id_customer FROM tiptok_penitipan WHERE deleted_at IS NULL)";
        }
        $whereTiptok .= ") ";

        if (!empty($q)) {
            $stmt = $conn->prepare("SELECT id, kode_customer, nama, kategori, telp_pribadi, alamat, kota, alamat_lokasi 
                                   FROM sales_customer 
                                   WHERE deleted_at IS NULL 
                                     AND $whereTiptok
                                     AND (nama LIKE ? OR telp_pribadi LIKE ? OR alamat LIKE ? OR kota LIKE ?) 
                                   ORDER BY (kategori = 'Dealer') DESC, nama ASC LIMIT 100");
            $stmt->bind_param("ssss", $qLike, $qLike, $qLike, $qLike);
        } else {
            $stmt = $conn->prepare("SELECT id, kode_customer, nama, kategori, telp_pribadi, alamat, kota, alamat_lokasi 
                                   FROM sales_customer 
                                   WHERE deleted_at IS NULL 
                                     AND $whereTiptok
                                   ORDER BY (kategori = 'Dealer') DESC, nama ASC LIMIT 100");
        }
    } else {
        if (!empty($q)) {
            $stmt = $conn->prepare("SELECT c.id, c.id AS kode_customer, c.nama_toko AS nama, c.kategori, 
                                           (SELECT tlp_pic FROM customer_pics WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS telp_pribadi,
                                           (SELECT alamat FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat,
                                           (SELECT kota FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS kota,
                                           (SELECT link_google_map FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat_lokasi
                                   FROM customers c 
                                   WHERE c.deleted_at IS NULL 
                                     AND (c.nama_toko LIKE ? OR c.kategori LIKE ? OR EXISTS (SELECT 1 FROM customer_addresses ca WHERE ca.customer_id = c.id AND (ca.alamat LIKE ? OR ca.kota LIKE ?))) 
                                   ORDER BY (c.kategori = 'DEALER') DESC, c.nama_toko ASC LIMIT 100");
            $stmt->bind_param("ssss", $qLike, $qLike, $qLike, $qLike);
        } else {
            $stmt = $conn->prepare("SELECT c.id, c.id AS kode_customer, c.nama_toko AS nama, c.kategori, 
                                           (SELECT tlp_pic FROM customer_pics WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS telp_pribadi,
                                           (SELECT alamat FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat,
                                           (SELECT kota FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS kota,
                                           (SELECT link_google_map FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat_lokasi
                                   FROM customers c 
                                   WHERE c.deleted_at IS NULL 
                                   ORDER BY (c.kategori = 'DEALER') DESC, c.nama_toko ASC LIMIT 100");
        }
    }
    
    $dealers = [];
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $dealers[] = $row;
        }
        $stmt->close();
    }
    
    echo json_encode(['status' => 'success', 'data' => $dealers]);
    exit;
}

// -------------------------------------------------------------
// 1.1 CARI / AMBIL DAFTAR 6 PRODUK RESMI TIP TOK LOEWIX
// -------------------------------------------------------------
if ($action === 'get_product_prices') {
    $masterProducts = [
        [
            'id' => 1,
            'category' => '2MP AHD INDOOR',
            'type' => '2MP AHD INDOOR LX-4F320-CE',
            'model' => 'LX-4F320-CE',
            'description' => 'Kamera CCTV Loewix 2MP AHD Indoor CatEyes (LX-4F320-CE)',
            'msrp' => 145000,
            'insentif' => 15000
        ],
        [
            'id' => 2,
            'category' => '2MP AHD OUTDOOR',
            'type' => '2MP AHD OUTDOOR LX-50F320-CM',
            'model' => 'LX-50F320-CM',
            'description' => 'Kamera CCTV Loewix 2MP AHD Outdoor ColorMax (LX-50F320-CM)',
            'msrp' => 170000,
            'insentif' => 15000
        ],
        [
            'id' => 3,
            'category' => '2MP AHD INDOOR',
            'type' => '2MP AHD INDOOR LX-4F320-CM',
            'model' => 'LX-4F320-CM',
            'description' => 'Kamera CCTV Loewix 2MP AHD Indoor ColorMax (LX-4F320-CM)',
            'msrp' => 145000,
            'insentif' => 15000
        ],
        [
            'id' => 4,
            'category' => '2MP AHD OUTDOOR',
            'type' => '2MP AHD OUTDOOR LX-50F320-CE',
            'model' => 'LX-50F320-CE',
            'description' => 'Kamera CCTV Loewix 2MP AHD Outdoor CatEyes (LX-50F320-CE)',
            'msrp' => 170000,
            'insentif' => 15000
        ],
        [
            'id' => 5,
            'category' => '4MP IPCAM INDOOR',
            'type' => '4MP IPCAM INDOOR LX-IPF40CMT02',
            'model' => 'LX-IPF40CMT02',
            'description' => 'Kamera CCTV Loewix 4MP IP Camera Indoor (LX-IPF40CMT02)',
            'msrp' => 350000,
            'insentif' => 30000
        ],
        [
            'id' => 6,
            'category' => '4MP IPCAM OUTDOOR',
            'type' => '4MP IPCAM OUTDOOR LX-IPF40CMT17',
            'model' => 'LX-IPF40CMT17',
            'description' => 'Kamera CCTV Loewix 4MP IP Camera Outdoor (LX-IPF40CMT17)',
            'msrp' => 380000,
            'insentif' => 30000
        ]
    ];

    // Cek harga terkini di database jika ada
    $chk = @$conn->query("SHOW TABLES LIKE 'product_prices'");
    if ($chk && $chk->num_rows > 0) {
        $res = @$conn->query("SELECT category, type, description, msrp FROM product_prices");
        if ($res && $res->num_rows > 0) {
            $dbPrices = [];
            while ($row = $res->fetch_assoc()) {
                $dbPrices[] = $row;
            }
            foreach ($masterProducts as &$p) {
                $cleanModel = str_replace('-', '', $p['model']);
                foreach ($dbPrices as $dbP) {
                    $dbTypeClean = str_replace('-', '', $dbP['type']);
                    if (stripos($dbTypeClean, $cleanModel) !== false || stripos($dbP['type'], $p['model']) !== false) {
                        if ((float)$dbP['msrp'] > 0) $p['msrp'] = (float)$dbP['msrp'];
                        if (!empty($dbP['description'])) $p['description'] = $dbP['description'];
                        break;
                    }
                }
            }
            unset($p);
        }
    }

    $q = trim($_GET['q'] ?? '');
    if (!empty($q)) {
        $filtered = [];
        $qLower = strtolower($q);
        $cleanQ = str_replace(['-', ' ', '_'], '', $qLower);
        foreach ($masterProducts as $p) {
            $cleanType = str_replace(['-', ' ', '_'], '', strtolower($p['type']));
            $cleanModel = str_replace(['-', ' ', '_'], '', strtolower($p['model']));
            if (strpos($cleanType, $cleanQ) !== false ||
                strpos(strtolower($p['category']), $qLower) !== false ||
                strpos(strtolower($p['description']), $qLower) !== false ||
                strpos($cleanModel, $cleanQ) !== false) {
                $filtered[] = $p;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $filtered]);
    } else {
        echo json_encode(['status' => 'success', 'data' => $masterProducts]);
    }
    exit;
}

// -------------------------------------------------------------
// 1.5. GET DAFTAR SALES PIC (UNTUK DROPDOWN & FILTER SALES)
// -------------------------------------------------------------
if ($action === 'get_sales_list' || $action === 'search_sales') {
    $colNama = "nama_lengkap";
    $chkCol = @$conn->query("SHOW COLUMNS FROM sales LIKE 'nama_lengkap'");
    if (!$chkCol || $chkCol->num_rows == 0) {
        $colNama = "nama";
    }

    $sql = "SELECT id, $colNama AS nama, role AS jabatan, email FROM sales WHERE deleted_at IS NULL ORDER BY (role = 'sales') DESC, $colNama ASC";
    $res = $conn->query($sql);
    $salesList = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $salesList[] = [
                'id' => intval($r['id']),
                'nama' => $r['nama'] ?? 'Sales',
                'jabatan' => $r['jabatan'] ?? 'Sales',
                'email' => $r['email'] ?? ''
            ];
        }
    }
    echo json_encode(['status' => 'success', 'data' => $salesList]);
    exit;
}

// -------------------------------------------------------------
// 2. SIMPAN PENITIPAN BARU (MASTER + MULTI ITEM)
// -------------------------------------------------------------
if ($action === 'simpan_penitipan') {
    $id_customer = intval($_POST['id_customer'] ?? 0);
    $tgl_titip = trim($_POST['tgl_titip'] ?? date('Y-m-d'));
    $catatan = trim($_POST['catatan'] ?? '');
    $items = $_POST['items'] ?? [];
    $id_sales_input = intval($_POST['id_sales'] ?? 0);

    $target_id_sales = $idUser;
    $target_nama_sales = $namaUser;

    if ($id_sales_input > 0 && $jabatanUser !== 'Sales') {
        $target_id_sales = $id_sales_input;
        $chkS = $conn->query("SELECT nama_lengkap FROM sales WHERE id = '$target_id_sales' LIMIT 1");
        if (!$chkS || $chkS->num_rows == 0) {
            $chkS = $conn->query("SELECT nama FROM sales WHERE id = '$target_id_sales' LIMIT 1");
        }
        if ($chkS && $chkS->num_rows > 0) {
            $rowS = $chkS->fetch_assoc();
            $target_nama_sales = $rowS['nama_lengkap'] ?? ($rowS['nama'] ?? $namaUser);
        }
    }

    if ($id_customer <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Silakan pilih toko/dealer tujuan.']);
        exit;
    }

    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Wajib menginput minimal 1 barang titipan.']);
        exit;
    }

    // Generate kode titip: TPT-YYYYMMDD-XXXX
    $datePart = date('Ymd', strtotime($tgl_titip));
    $prefix = "TPT-$datePart-";
    $queryLast = $conn->query("SELECT kode_titip FROM tiptok_penitipan WHERE kode_titip LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if ($queryLast && $queryLast->num_rows > 0) {
        $lastCode = $queryLast->fetch_assoc()['kode_titip'];
        $lastSeq = intval(substr($lastCode, strlen($prefix)));
        $nextNum = $lastSeq + 1;
    }
    $kode_titip = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    // Insert master penitipan
    $stmtMaster = $conn->prepare("INSERT INTO tiptok_penitipan (kode_titip, id_customer, id_sales, nama_sales, tgl_titip, status, catatan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'aktif', ?, NOW(), NOW())");
    $stmtMaster->bind_param("siisss", $kode_titip, $id_customer, $target_id_sales, $target_nama_sales, $tgl_titip, $catatan);
    
    if (!$stmtMaster->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data master: ' . $stmtMaster->error]);
        $stmtMaster->close();
        exit;
    }
    $id_penitipan = $stmtMaster->insert_id;
    $stmtMaster->close();

    // Insert Items
    $stmtItem = $conn->prepare("INSERT INTO tiptok_items (id_penitipan, kode_titip, nama_barang, tipe_barang, qty_titip, qty_sisa, qty_terjual, insentif_per_unit, total_insentif, status_item, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0, 'titip', NOW(), NOW())");

    $validCount = 0;
    foreach ($items as $item) {
        $nama_barang = trim($item['nama_barang'] ?? '');
        $tipe_barang = trim($item['tipe_barang'] ?? '');
        $qty_titip = intval($item['qty_titip'] ?? 0);
        $insentif_per_unit = floatval(str_replace(['.', ','], ['', '.'], $item['insentif_per_unit'] ?? 0));

        if (!empty($nama_barang) && $qty_titip > 0) {
            $qty_sisa = $qty_titip;
            $stmtItem->bind_param("isssiid", $id_penitipan, $kode_titip, $nama_barang, $tipe_barang, $qty_titip, $qty_sisa, $insentif_per_unit);
            $stmtItem->execute();
            $validCount++;
        }
    }
    $stmtItem->close();

    if ($validCount === 0) {
        // Rollback master if no items valid
        $conn->query("DELETE FROM tiptok_penitipan WHERE id = $id_penitipan");
        echo json_encode(['status' => 'error', 'message' => 'Barang titipan tidak valid. Pastikan nama barang dan jumlah diisi dengan benar.']);
        exit;
    }

    echo json_encode([
        'status' => 'success', 
        'message' => "Penitipan barang berhasil disimpan ($validCount item) dengan Kode: $kode_titip",
        'kode_titip' => $kode_titip,
        'id_penitipan' => $id_penitipan
    ]);
    exit;
}

// -------------------------------------------------------------
// 3. GET DETAIL PENITIPAN, BARANG, & LOG KUNJUNGAN
// -------------------------------------------------------------
if ($action === 'get_detail') {
    $id_penitipan = intval($_GET['id'] ?? 0);
    if ($id_penitipan <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID penitipan tidak valid.']);
        exit;
    }

    // Master & Customer
    if ($hasSalesCustomer) {
        $sqlM = "SELECT p.*, c.nama AS nama_toko, c.kategori AS kategori_customer, c.telp_pribadi AS telp_toko, 
                        c.alamat AS alamat_toko, c.kota AS kota_toko, c.alamat_lokasi 
                 FROM tiptok_penitipan p 
                 LEFT JOIN sales_customer c ON p.id_customer = c.id 
                 WHERE p.id = ?";
    } else {
        $sqlM = "SELECT p.*, c.nama_toko AS nama_toko, c.kategori AS kategori_customer, 
                        (SELECT tlp_pic FROM customer_pics WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS telp_toko, 
                        (SELECT alamat FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat_toko, 
                        (SELECT kota FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS kota_toko, 
                        (SELECT link_google_map FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat_lokasi 
                 FROM tiptok_penitipan p 
                 LEFT JOIN customers c ON p.id_customer = c.id 
                 WHERE p.id = ?";
    }
    $stmt = $conn->prepare($sqlM);
    $stmt->bind_param("i", $id_penitipan);
    $stmt->execute();
    $resMaster = $stmt->get_result();
    $master = $resMaster->fetch_assoc();
    $stmt->close();

    if (!$master) {
        echo json_encode(['status' => 'error', 'message' => 'Data penitipan tidak ditemukan.']);
        exit;
    }

    // Items
    $stmtItems = $conn->prepare("SELECT * FROM tiptok_items WHERE id_penitipan = ? ORDER BY id ASC");
    $stmtItems->bind_param("i", $id_penitipan);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();
    $items = [];
    $totalTitip = 0;
    $totalSisa = 0;
    $totalTerjual = 0;
    $totalInsentif = 0;

    while ($row = $resItems->fetch_assoc()) {
        $totalTitip += intval($row['qty_titip']);
        $totalSisa += intval($row['qty_sisa']);
        $totalTerjual += intval($row['qty_terjual']);
        $totalInsentif += floatval($row['total_insentif']);
        $items[] = $row;
    }
    $stmtItems->close();

    // Visit logs
    $stmtLogs = $conn->prepare("SELECT k.*, i.nama_barang, i.tipe_barang, i.insentif_per_unit 
                               FROM tiptok_kunjungan k 
                               LEFT JOIN tiptok_items i ON k.id_item = i.id 
                               WHERE k.id_penitipan = ? 
                               ORDER BY k.tgl_kunjungan DESC, k.id DESC");
    $stmtLogs->bind_param("i", $id_penitipan);
    $stmtLogs->execute();
    $resLogs = $stmtLogs->get_result();
    $logs = [];
    while ($l = $resLogs->fetch_assoc()) {
        $logs[] = $l;
    }
    $stmtLogs->close();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'master' => $master,
            'items' => $items,
            'logs' => $logs,
            'summary' => [
                'total_titip' => $totalTitip,
                'total_sisa' => $totalSisa,
                'total_terjual' => $totalTerjual,
                'total_insentif' => $totalInsentif
            ]
        ]
    ]);
    exit;
}

// -------------------------------------------------------------
// 4. SIMPAN LAPORAN KUNJUNGAN & CEK STOK SISA
// -------------------------------------------------------------
if ($action === 'simpan_kunjungan') {
    $id_penitipan = intval($_POST['id_penitipan'] ?? 0);
    $tgl_kunjungan = trim($_POST['tgl_kunjungan'] ?? date('Y-m-d'));
    $catatan_kunjungan = trim($_POST['catatan_kunjungan'] ?? '');
    $items = $_POST['items'] ?? [];

    if ($id_penitipan <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Penitipan tidak valid.']);
        exit;
    }

    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Data cek sisa barang tidak boleh kosong.']);
        exit;
    }

    // Handle foto bukti audit opsional
    $foto_filename = null;
    if (isset($_FILES['foto_kunjungan']) && $_FILES['foto_kunjungan']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/tiptok/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileExt = strtolower(pathinfo($_FILES['foto_kunjungan']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExt, $allowed)) {
            $foto_filename = 'tiptok_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            move_uploaded_file($_FILES['foto_kunjungan']['tmp_name'], $uploadDir . $foto_filename);
        }
    }

    // Validasi & Update stok
    $allUpdated = true;
    $totalTerjualPeriode = 0;
    $totalInsentifPeriode = 0;
    $errors = [];

    // Cek dulu semua item apakah jika ada penjualan maka No Invoice sudah diisi
    foreach ($items as $item) {
        $id_item = intval($item['id_item'] ?? 0);
        $stok_sisa_baru = intval($item['stok_sisa'] ?? 0);
        $no_inv = trim($item['no_inv'] ?? '');
        $tgl_invoice = !empty($item['tgl_invoice']) ? $item['tgl_invoice'] : $tgl_kunjungan;

        // Ambil data item saat ini
        $qCur = $conn->query("SELECT * FROM tiptok_items WHERE id = $id_item AND id_penitipan = $id_penitipan");
        if (!$qCur || $qCur->num_rows === 0) {
            $errors[] = "Item ID $id_item tidak ditemukan.";
            continue;
        }
        $curItem = $qCur->fetch_assoc();
        $stok_sebelumnya = intval($curItem['qty_sisa']);

        if ($stok_sisa_baru > $stok_sebelumnya) {
            $errors[] = "Stok sisa '{$curItem['nama_barang']}' ($stok_sisa_baru) tidak boleh lebih besar dari stok sebelumnya ($stok_sebelumnya).";
            continue;
        }

        $terjual = $stok_sebelumnya - $stok_sisa_baru;
        // Penjualan tidak lagi memblokir jika No. Invoice kosong (karena No. Invoice diinput di halaman khusus)
    }

    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'message' => implode('<br>', $errors)]);
        exit;
    }

    // Generate prefix kode kunjungan: VIS-TPT-YYYYMMDD-XXXX
    $datePart = date('Ymd', strtotime($tgl_kunjungan));
    $prefixVis = "VIS-TPT-$datePart-";
    $qLastVis = $conn->query("SELECT kode_kunjungan FROM tiptok_kunjungan WHERE kode_kunjungan LIKE '$prefixVis%' ORDER BY id DESC LIMIT 1");
    $nextVisNum = 1;
    if ($qLastVis && $qLastVis->num_rows > 0) {
        $lastVis = $qLastVis->fetch_assoc()['kode_kunjungan'];
        $lastSeq = intval(substr($lastVis, strlen($prefixVis)));
        $nextVisNum = $lastSeq + 1;
    }

    // Ambil data sales dari master penitipan jika input dilakukan oleh level admin
    $qMasterPen = $conn->query("SELECT id_sales, nama_sales FROM tiptok_penitipan WHERE id = $id_penitipan LIMIT 1");
    $mPen = $qMasterPen ? $qMasterPen->fetch_assoc() : null;

    $kunjungan_id_sales = $idUser;
    $kunjungan_nama_sales = $namaUser;

    if ($jabatanUser !== 'Sales' && !empty($mPen['nama_sales'])) {
        $kunjungan_id_sales = intval($mPen['id_sales']) > 0 ? intval($mPen['id_sales']) : $idUser;
        $kunjungan_nama_sales = $mPen['nama_sales'];
    }

    // Eksekusi update dan insert log
    foreach ($items as $item) {
        $id_item = intval($item['id_item'] ?? 0);
        $stok_sisa_baru = intval($item['stok_sisa'] ?? 0);
        $no_inv = trim($item['no_inv'] ?? '');
        $tgl_invoice = !empty($item['tgl_invoice']) ? $item['tgl_invoice'] : $tgl_kunjungan;

        $qCur = $conn->query("SELECT * FROM tiptok_items WHERE id = $id_item AND id_penitipan = $id_penitipan");
        $curItem = $qCur->fetch_assoc();
        $stok_sebelumnya = intval($curItem['qty_sisa']);
        $terjual = $stok_sebelumnya - $stok_sisa_baru;
        $insentif_unit = floatval($curItem['insentif_per_unit']);
        $insentif_didapat = $terjual * $insentif_unit;

        $kode_kunjungan = $prefixVis . str_pad($nextVisNum++, 4, '0', STR_PAD_LEFT);

        // Insert log kunjungan
        $stmtLog = $conn->prepare("INSERT INTO tiptok_kunjungan (kode_kunjungan, id_penitipan, id_item, id_sales, nama_sales, tgl_kunjungan, stok_sebelumnya, stok_sisa, qty_terjual_kunjungan, no_inv, tgl_invoice, insentif_didapat, catatan_kunjungan, foto_kunjungan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmtLog->bind_param("siiissiiissdss", $kode_kunjungan, $id_penitipan, $id_item, $kunjungan_id_sales, $kunjungan_nama_sales, $tgl_kunjungan, $stok_sebelumnya, $stok_sisa_baru, $terjual, $no_inv, $tgl_invoice, $insentif_didapat, $catatan_kunjungan, $foto_filename);
        $stmtLog->execute();
        $stmtLog->close();

        // Update item
        $newTerjualTotal = intval($curItem['qty_terjual']) + $terjual;
        $newInsentifTotal = floatval($curItem['total_insentif']) + $insentif_didapat;
        $newItemStatus = ($stok_sisa_baru == 0) ? 'habis_terjual' : 'titip';

        $stmtUpItem = $conn->prepare("UPDATE tiptok_items SET qty_sisa = ?, qty_terjual = ?, total_insentif = ?, status_item = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpItem->bind_param("iidsi", $stok_sisa_baru, $newTerjualTotal, $newInsentifTotal, $newItemStatus, $id_item);
        $stmtUpItem->execute();
        $stmtUpItem->close();

        $totalTerjualPeriode += $terjual;
        $totalInsentifPeriode += $insentif_didapat;
    }

    // Cek apakah seluruh barang di penitipan ini sudah habis terjual
    $qCheckSisa = $conn->query("SELECT SUM(qty_sisa) as total_sisa FROM tiptok_items WHERE id_penitipan = $id_penitipan");
    $totalSisaPenitipan = $qCheckSisa->fetch_assoc()['total_sisa'] ?? 0;
    if (intval($totalSisaPenitipan) === 0) {
        $conn->query("UPDATE tiptok_penitipan SET status = 'selesai', updated_at = NOW() WHERE id = $id_penitipan");
    }

    $msgTerjual = ($totalTerjualPeriode > 0) 
        ? " Terjual: $totalTerjualPeriode unit (Estimasi Insentif: Rp " . number_format($totalInsentifPeriode, 0, ',', '.') . ")"
        : " (Tidak ada barang terjual pada kunjungan ini)";

    echo json_encode([
        'status' => 'success',
        'message' => "Laporan kunjungan berhasil disimpan!" . $msgTerjual,
        'terjual' => $totalTerjualPeriode,
        'insentif' => $totalInsentifPeriode
    ]);
    exit;
}

// -------------------------------------------------------------
// 5. GET CLAIM SUMMARY & UNCLAIMED ITEMS (MIN 50 UNIT)
// -------------------------------------------------------------
if ($action === 'get_claim_summary') {
    // Ambil data kunjungan yang menghasilkan penjualan dan belum masuk ke claim
    $whereSales = "";
    if ($jabatanUser === 'Sales') {
        $whereSales = " AND (p.id_sales = '$idUser' OR k.id_sales = '$idUser') ";
    }

    $custJoin = $hasSalesCustomer ? "JOIN sales_customer c ON p.id_customer = c.id" : "JOIN customers c ON p.id_customer = c.id";
    $custField = $hasSalesCustomer ? "c.nama" : "c.nama_toko";

    $sql = "SELECT k.id AS id_kunjungan, k.id_penitipan, k.id_item, k.kode_kunjungan, 
                   COALESCE(NULLIF(p.nama_sales, ''), k.nama_sales) AS nama_sales, 
                   k.tgl_kunjungan, 
                   k.qty_terjual_kunjungan, k.no_inv, k.tgl_invoice, k.insentif_didapat, 
                   i.nama_barang, i.tipe_barang, i.insentif_per_unit, 
                   p.kode_titip, $custField AS nama_toko 
            FROM tiptok_kunjungan k 
            JOIN tiptok_items i ON k.id_item = i.id 
            JOIN tiptok_penitipan p ON k.id_penitipan = p.id 
            $custJoin 
            WHERE k.id_claim IS NULL 
              AND k.qty_terjual_kunjungan > 0 
              $whereSales 
            ORDER BY k.tgl_kunjungan ASC, k.id ASC";

    $res = $conn->query($sql);
    $unclaimedItems = [];
    $totalUnit = 0;
    $totalNominal = 0;

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $totalUnit += intval($r['qty_terjual_kunjungan']);
            $totalNominal += floatval($r['insentif_didapat']);
            $unclaimedItems[] = $r;
        }
    }

    // Ambil riwayat claim yang pernah diajukan
    $whereClaimSales = "";
    if ($jabatanUser === 'Sales') {
        $whereClaimSales = " WHERE c.id_sales = '$idUser' ";
    }
    $resClaims = $conn->query("SELECT c.* FROM tiptok_claim c $whereClaimSales ORDER BY c.id DESC");
    $claimHistory = [];
    if ($resClaims) {
        while ($cl = $resClaims->fetch_assoc()) {
            $claimHistory[] = $cl;
        }
    }

    $isEligible = ($totalUnit >= 50);
    $progress = min(100, round(($totalUnit / 50) * 100, 1));

    echo json_encode([
        'status' => 'success',
        'data' => [
            'unclaimed_items' => $unclaimedItems,
            'total_unit_terjual' => $totalUnit,
            'total_nominal_insentif' => $totalNominal,
            'is_eligible' => $isEligible,
            'target_unit' => 50,
            'sisa_menuju_target' => max(0, 50 - $totalUnit),
            'progress_percentage' => $progress,
            'claim_history' => $claimHistory
        ]
    ]);
    exit;
}

// -------------------------------------------------------------
// 6. AJUKAN KLAIM INSENTIF (MIN 50 UNIT)
// -------------------------------------------------------------
if ($action === 'ajukan_claim') {
    $catatan_claim = trim($_POST['catatan_claim'] ?? '');
    
    $whereSales = "";
    if ($jabatanUser === 'Sales') {
        $whereSales = " AND k.id_sales = '$idUser' ";
    }

    // Query eligible unclaimed visit items
    $sql = "SELECT k.id AS id_kunjungan, k.id_penitipan, k.id_item, k.id_sales, k.nama_sales, 
                   k.qty_terjual_kunjungan, k.no_inv, k.insentif_didapat, 
                   i.nama_barang, i.insentif_per_unit 
            FROM tiptok_kunjungan k 
            JOIN tiptok_items i ON k.id_item = i.id 
            WHERE k.id_claim IS NULL 
              AND k.qty_terjual_kunjungan > 0 
              $whereSales 
            ORDER BY k.tgl_kunjungan ASC, k.id ASC";

    $res = $conn->query($sql);
    $itemsToClaim = [];
    $totalUnit = 0;
    $totalNominal = 0;

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $totalUnit += intval($r['qty_terjual_kunjungan']);
            $totalNominal += floatval($r['insentif_didapat']);
            $itemsToClaim[] = $r;
        }
    }

    if ($totalUnit < 50) {
        echo json_encode([
            'status' => 'error', 
            'message' => "Syarat minimal klaim adalah 50 unit terjual. Total unit Anda saat ini baru $totalUnit unit."
        ]);
        exit;
    }

    // Generate kode claim: CLM-YYYYMMDD-XXXX
    $datePart = date('Ymd');
    $prefixClm = "CLM-$datePart-";
    $qLastClm = $conn->query("SELECT kode_claim FROM tiptok_claim WHERE kode_claim LIKE '$prefixClm%' ORDER BY id DESC LIMIT 1");
    $nextClmNum = 1;
    if ($qLastClm && $qLastClm->num_rows > 0) {
        $lastClm = $qLastClm->fetch_assoc()['kode_claim'];
        $lastSeq = intval(substr($lastClm, strlen($prefixClm)));
        $nextClmNum = $lastSeq + 1;
    }
    $kode_claim = $prefixClm . str_pad($nextClmNum, 4, '0', STR_PAD_LEFT);
    $tgl_claim = date('Y-m-d');

    // Insert master claim
    $stmtClaim = $conn->prepare("INSERT INTO tiptok_claim (kode_claim, id_sales, nama_sales, tgl_claim, total_unit_terjual, total_nominal_insentif, status_claim, catatan_claim, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'menunggu_approval', ?, NOW(), NOW())");
    $stmtClaim->bind_param("sissids", $kode_claim, $idUser, $namaUser, $tgl_claim, $totalUnit, $totalNominal, $catatan_claim);
    
    if (!$stmtClaim->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membuat pengajuan klaim: ' . $stmtClaim->error]);
        $stmtClaim->close();
        exit;
    }
    $id_claim = $stmtClaim->insert_id;
    $stmtClaim->close();

    // Insert detail claim & update kunjungan log
    $stmtDetail = $conn->prepare("INSERT INTO tiptok_claim_detail (id_claim, id_kunjungan_log, id_penitipan, id_item, nama_barang, no_inv, qty_terjual, insentif_per_unit, subtotal_insentif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($itemsToClaim as $item) {
        $stmtDetail->bind_param("iiiissidd", $id_claim, $item['id_kunjungan'], $item['id_penitipan'], $item['id_item'], $item['nama_barang'], $item['no_inv'], $item['qty_terjual_kunjungan'], $item['insentif_per_unit'], $item['insentif_didapat']);
        $stmtDetail->execute();

        // Bind kunjungan record to this claim
        $conn->query("UPDATE tiptok_kunjungan SET id_claim = $id_claim WHERE id = {$item['id_kunjungan']}");
    }
    $stmtDetail->close();

    echo json_encode([
        'status' => 'success',
        'message' => "Pengajuan klaim insentif ($totalUnit unit - Rp " . number_format($totalNominal, 0, ',', '.') . ") berhasil diajukan dengan Kode: $kode_claim. Menunggu verifikasi tim admin.",
        'kode_claim' => $kode_claim,
        'id_claim' => $id_claim
    ]);
    exit;
}

// -------------------------------------------------------------
// 7. GET DETAIL CLAIM LENGKAP
// -------------------------------------------------------------
if ($action === 'get_claim_detail') {
    $id_claim = intval($_GET['id_claim'] ?? 0);
    if ($id_claim <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID klaim tidak valid.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM tiptok_claim WHERE id = ?");
    $stmt->bind_param("i", $id_claim);
    $stmt->execute();
    $claim = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$claim) {
        echo json_encode(['status' => 'error', 'message' => 'Data klaim tidak ditemukan.']);
        exit;
    }

    $custJoin = $hasSalesCustomer ? "JOIN sales_customer c ON p.id_customer = c.id" : "JOIN customers c ON p.id_customer = c.id";
    $custField = $hasSalesCustomer ? "c.nama" : "c.nama_toko";

    $stmtDetails = $conn->prepare("SELECT d.*, p.kode_titip, $custField AS nama_toko 
                                  FROM tiptok_claim_detail d 
                                  JOIN tiptok_penitipan p ON d.id_penitipan = p.id 
                                  $custJoin 
                                  WHERE d.id_claim = ? 
                                  ORDER BY d.id ASC");
    $stmtDetails->bind_param("i", $id_claim);
    $stmtDetails->execute();
    $resDetails = $stmtDetails->get_result();
    $details = [];
    while ($d = $resDetails->fetch_assoc()) {
        $details[] = $d;
    }
    $stmtDetails->close();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'claim' => $claim,
            'details' => $details
        ]
    ]);
    exit;
}

// -------------------------------------------------------------
// 8. UPDATE STATUS KLAIM (ADMIN / SALES MANAGER APPROVAL)
// -------------------------------------------------------------
if ($action === 'update_status_claim') {
    if ($jabatanUser !== 'Super Admin' && $jabatanUser !== 'Admin' && $jabatanUser !== 'Sales Manager') {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk memproses persetujuan klaim insentif.']);
        exit;
    }

    $id_claim = intval($_POST['id_claim'] ?? 0);
    $status_claim = trim($_POST['status_claim'] ?? '');
    $catatan_admin = trim($_POST['catatan_admin'] ?? '');

    $allowedStatus = ['menunggu_approval', 'disetujui', 'cair', 'ditolak'];
    if (!in_array($status_claim, $allowedStatus)) {
        echo json_encode(['status' => 'error', 'message' => 'Status klaim tidak valid.']);
        exit;
    }

    $tgl_cair_sql = ($status_claim === 'cair') ? "tgl_cair = NOW()," : "";

    $stmt = $conn->prepare("UPDATE tiptok_claim SET status_claim = ?, $tgl_cair_sql catatan_admin = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $status_claim, $catatan_admin, $id_claim);
    
    if ($stmt->execute()) {
        // Jika ditolak, unbind id_claim pada tiptok_kunjungan agar bisa diajukan kembali di kemudian hari
        if ($status_claim === 'ditolak') {
            $conn->query("UPDATE tiptok_kunjungan SET id_claim = NULL WHERE id_claim = $id_claim");
        }
        echo json_encode(['status' => 'success', 'message' => "Status klaim berhasil diperbarui menjadi: " . strtoupper(str_replace('_', ' ', $status_claim))]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status klaim: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// -------------------------------------------------------------
// 9. TARIK BARANG / UBAH STATUS PENITIPAN
// -------------------------------------------------------------
if ($action === 'update_status_penitipan') {
    $id_penitipan = intval($_POST['id_penitipan'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if (!in_array($status, ['aktif', 'selesai', 'ditarik'])) {
        echo json_encode(['status' => 'error', 'message' => 'Status penitipan tidak valid.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tiptok_penitipan SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $id_penitipan);
    if ($stmt->execute()) {
        if ($status === 'ditarik') {
            $conn->query("UPDATE tiptok_items SET status_item = 'ditarik', updated_at = NOW() WHERE id_penitipan = $id_penitipan AND qty_sisa > 0");
        }
        echo json_encode(['status' => 'success', 'message' => "Status penitipan berhasil diperbarui menjadi: " . strtoupper($status)]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// -------------------------------------------------------------
// 10. EDIT / UPDATE PENITIPAN BARANG
// -------------------------------------------------------------
if ($action === 'update_penitipan') {
    $id_penitipan = intval($_POST['id_penitipan'] ?? 0);
    $id_customer = intval($_POST['id_customer'] ?? 0);
    $tgl_titip = trim($_POST['tgl_titip'] ?? date('Y-m-d'));
    $catatan = trim($_POST['catatan'] ?? '');
    $status = trim($_POST['status'] ?? 'aktif');
    $items = $_POST['items'] ?? [];

    if ($id_penitipan <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID penitipan tidak valid.']);
        exit;
    }

    if ($id_customer <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Silakan pilih toko/dealer tujuan.']);
        exit;
    }

    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Wajib ada minimal 1 barang titipan.']);
        exit;
    }

    // Cek keberadaan data master
    $qCheck = $conn->query("SELECT * FROM tiptok_penitipan WHERE id = $id_penitipan");
    if (!$qCheck || $qCheck->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data penitipan tidak ditemukan.']);
        exit;
    }
    $master = $qCheck->fetch_assoc();

    if ($jabatanUser === 'Sales' && $master['id_sales'] != $idUser) {
        echo json_encode(['status' => 'error', 'message' => 'Anda hanya dapat mengedit data penitipan milik Anda sendiri.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        $target_id_sales = intval($_POST['id_sales'] ?? ($master['id_sales'] ?? $idUser));
        $target_nama_sales = $master['nama_sales'] ?? $namaUser;

        if ($target_id_sales > 0 && $jabatanUser !== 'Sales') {
            $chkS = $conn->query("SELECT nama_lengkap FROM sales WHERE id = '$target_id_sales' LIMIT 1");
            if (!$chkS || $chkS->num_rows == 0) {
                $chkS = $conn->query("SELECT nama FROM sales WHERE id = '$target_id_sales' LIMIT 1");
            }
            if ($chkS && $chkS->num_rows > 0) {
                $rowS = $chkS->fetch_assoc();
                $target_nama_sales = $rowS['nama_lengkap'] ?? ($rowS['nama'] ?? $target_nama_sales);
            }
        }

        // Update master
        $stmtUp = $conn->prepare("UPDATE tiptok_penitipan SET id_customer = ?, id_sales = ?, nama_sales = ?, tgl_titip = ?, catatan = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmtUp->bind_param("iissssi", $id_customer, $target_id_sales, $target_nama_sales, $tgl_titip, $catatan, $status, $id_penitipan);
        $stmtUp->execute();
        $stmtUp->close();

        // Sync sales to related kunjungan logs if updated
        if ($target_id_sales > 0) {
            $safeSalesName = $conn->real_escape_string($target_nama_sales);
            $conn->query("UPDATE tiptok_kunjungan SET id_sales = '$target_id_sales', nama_sales = '$safeSalesName' WHERE id_penitipan = $id_penitipan");
        }

        // Ambil existing items di database
        $qExist = $conn->query("SELECT * FROM tiptok_items WHERE id_penitipan = $id_penitipan");
        $existItems = [];
        while ($row = $qExist->fetch_assoc()) {
            $existItems[$row['id']] = $row;
        }

        $submittedItemIds = [];
        $kode_titip = $master['kode_titip'];

        foreach ($items as $item) {
            $id_item = intval($item['id_item'] ?? 0);
            $nama_barang = trim($item['nama_barang'] ?? '');
            $tipe_barang = trim($item['tipe_barang'] ?? '');
            $qty_titip = intval($item['qty_titip'] ?? 0);
            $insentif_per_unit = floatval(str_replace(['.', ','], ['', '.'], $item['insentif_per_unit'] ?? 0));

            if (empty($nama_barang) || $qty_titip <= 0) continue;

            if ($id_item > 0 && isset($existItems[$id_item])) {
                // Update existing item
                $submittedItemIds[] = $id_item;
                $prevItem = $existItems[$id_item];
                $terjual = intval($prevItem['qty_terjual']);

                if ($qty_titip < $terjual) {
                    throw new Exception("Qty Titip '{$nama_barang}' ($qty_titip) tidak boleh lebih kecil dari jumlah yang sudah terjual ($terjual).");
                }

                $qty_sisa = $qty_titip - $terjual;
                $total_insentif = $terjual * $insentif_per_unit;
                $status_item = ($qty_sisa === 0 && $terjual > 0) ? 'habis_terjual' : 'titip';

                $stmtItemUp = $conn->prepare("UPDATE tiptok_items SET nama_barang = ?, tipe_barang = ?, qty_titip = ?, qty_sisa = ?, insentif_per_unit = ?, total_insentif = ?, status_item = ?, updated_at = NOW() WHERE id = ? AND id_penitipan = ?");
                $stmtItemUp->bind_param("ssiiddsii", $nama_barang, $tipe_barang, $qty_titip, $qty_sisa, $insentif_per_unit, $total_insentif, $status_item, $id_item, $id_penitipan);
                $stmtItemUp->execute();
                $stmtItemUp->close();
            } else {
                // Insert new item added during edit
                $qty_sisa = $qty_titip;
                $stmtItemIns = $conn->prepare("INSERT INTO tiptok_items (id_penitipan, kode_titip, nama_barang, tipe_barang, qty_titip, qty_sisa, qty_terjual, insentif_per_unit, total_insentif, status_item, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0, 'titip', NOW(), NOW())");
                $stmtItemIns->bind_param("isssiid", $id_penitipan, $kode_titip, $nama_barang, $tipe_barang, $qty_titip, $qty_sisa, $insentif_per_unit);
                $stmtItemIns->execute();
                $submittedItemIds[] = $stmtItemIns->insert_id;
                $stmtItemIns->close();
            }
        }

        // Hapus items yang di-remove oleh user (hanya jika belum ada penjualan)
        foreach ($existItems as $exId => $exItem) {
            if (!in_array($exId, $submittedItemIds)) {
                if ($exItem['qty_terjual'] > 0) {
                    throw new Exception("Barang '{$exItem['nama_barang']}' tidak dapat dihapus karena sudah ada penjualan tercatat ({$exItem['qty_terjual']} unit).");
                }
                $conn->query("DELETE FROM tiptok_items WHERE id = $exId AND id_penitipan = $id_penitipan");
            }
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Perubahan data penitipan [{$kode_titip}] berhasil disimpan."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------
// 11. HAPUS DATA PENITIPAN BARANG
// -------------------------------------------------------------
if ($action === 'hapus_penitipan') {
    $id_penitipan = intval($_POST['id_penitipan'] ?? 0);
    if ($id_penitipan <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID penitipan tidak valid.']);
        exit;
    }

    // Cek data master
    $qCheck = $conn->query("SELECT * FROM tiptok_penitipan WHERE id = $id_penitipan");
    if (!$qCheck || $qCheck->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data penitipan tidak ditemukan.']);
        exit;
    }
    $master = $qCheck->fetch_assoc();

    if ($jabatanUser === 'Sales' && $master['id_sales'] != $idUser) {
        echo json_encode(['status' => 'error', 'message' => 'Anda hanya dapat menghapus data penitipan milik Anda sendiri.']);
        exit;
    }

    // Cek apakah ada kunjungan yang sudah masuk klaim
    $qClaimed = $conn->query("SELECT COUNT(*) as total FROM tiptok_kunjungan WHERE id_penitipan = $id_penitipan AND id_claim IS NOT NULL");
    $claimedCount = $qClaimed ? ($qClaimed->fetch_assoc()['total'] ?? 0) : 0;
    if ($claimedCount > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Penitipan ini tidak dapat dihapus karena sebagian penjualan sudah masuk ke dalam proses klaim insentif.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM tiptok_claim_detail WHERE id_penitipan = $id_penitipan");
        $conn->query("DELETE FROM tiptok_kunjungan WHERE id_penitipan = $id_penitipan");
        $conn->query("DELETE FROM tiptok_items WHERE id_penitipan = $id_penitipan");
        $conn->query("DELETE FROM tiptok_penitipan WHERE id = $id_penitipan");
        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => "Data penitipan [{$master['kode_titip']}] berhasil dihapus secara permanen."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data: ' . $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------
// 12. GET DAFTAR INVOICE PENJUALAN TIP TOK (UNTUK HALAMAN KHUSUS)
// -------------------------------------------------------------
if ($action === 'get_tiptok_invoices') {
    $statusInv = $_GET['status_inv'] ?? 'all'; // all, pending, invoiced
    $search = trim($_GET['search'] ?? '');
    $id_dealer = intval($_GET['id_customer'] ?? 0);
    $id_sales_filter = intval($_GET['id_sales'] ?? 0);
    $tgl_mulai = trim($_GET['tgl_mulai'] ?? '');
    $tgl_akhir = trim($_GET['tgl_akhir'] ?? '');

    // Self-healing database: sync id_sales pada tiptok_penitipan berdasarkan nama_sales di tabel sales
    @$conn->query("UPDATE tiptok_penitipan p 
                   JOIN sales s ON (s.nama_lengkap = p.nama_sales OR s.nama = p.nama_sales OR p.nama_sales LIKE CONCAT('%', s.nama_lengkap, '%')) 
                   SET p.id_sales = s.id 
                   WHERE p.nama_sales IS NOT NULL AND p.nama_sales != '' AND (p.id_sales IS NULL OR p.id_sales = 0 OR p.id_sales != s.id)");

    // Auto-sync kunjungan sales dengan sales penitipan jika ada perbedaan
    @$conn->query("UPDATE tiptok_kunjungan k 
                   JOIN tiptok_penitipan p ON k.id_penitipan = p.id 
                   SET k.id_sales = p.id_sales, k.nama_sales = p.nama_sales 
                   WHERE p.nama_sales IS NOT NULL AND p.nama_sales != '' 
                     AND (k.nama_sales != p.nama_sales OR k.id_sales != p.id_sales OR k.id_sales IS NULL OR k.id_sales = 0)");

    $where = ["k.qty_terjual_kunjungan > 0"];
    $salesNameFilter = '';

    if ($jabatanUser === 'Sales') {
        $where[] = "(p.id_sales = '$idUser' OR k.id_sales = '$idUser' OR p.nama_sales = '" . $conn->real_escape_string($namaUser) . "')";
    } elseif ($id_sales_filter > 0) {
        $qS = $conn->query("SELECT nama_lengkap, nama FROM sales WHERE id = '$id_sales_filter' LIMIT 1");
        if ($qS && $qS->num_rows > 0) {
            $rowS = $qS->fetch_assoc();
            $salesNameFilter = !empty($rowS['nama_lengkap']) ? $rowS['nama_lengkap'] : ($rowS['nama'] ?? '');
        }

        if (!empty($salesNameFilter)) {
            $safeNameFilter = $conn->real_escape_string($salesNameFilter);
            $where[] = "(p.id_sales = '$id_sales_filter' OR k.id_sales = '$id_sales_filter' OR p.nama_sales = '$safeNameFilter' OR k.nama_sales = '$safeNameFilter' OR p.nama_sales LIKE '%$safeNameFilter%' OR k.nama_sales LIKE '%$safeNameFilter%')";
        } else {
            $where[] = "(p.id_sales = '$id_sales_filter' OR k.id_sales = '$id_sales_filter')";
        }
    }

    if ($statusInv === 'pending') {
        $where[] = "(k.no_inv IS NULL OR TRIM(k.no_inv) = '')";
    } elseif ($statusInv === 'invoiced') {
        $where[] = "(k.no_inv IS NOT NULL AND TRIM(k.no_inv) != '')";
    }

    if ($id_dealer > 0) {
        $where[] = "p.id_customer = '$id_dealer'";
    }

    if (!empty($tgl_mulai)) {
        $safeMulai = $conn->real_escape_string($tgl_mulai);
        $where[] = "k.tgl_kunjungan >= '$safeMulai'";
    }
    if (!empty($tgl_akhir)) {
        $safeAkhir = $conn->real_escape_string($tgl_akhir);
        $where[] = "k.tgl_kunjungan <= '$safeAkhir'";
    }

    $custJoin = $hasSalesCustomer ? "JOIN sales_customer c ON p.id_customer = c.id" : "JOIN customers c ON p.id_customer = c.id";
    $custField = $hasSalesCustomer ? "c.nama" : "c.nama_toko";
    $custKatField = $hasSalesCustomer ? "c.kategori" : "c.kategori";
    $custKotaField = $hasSalesCustomer ? "c.kota" : "(SELECT kota FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1)";

    if (!empty($search)) {
        $safeSearch = $conn->real_escape_string($search);
        $where[] = "($custField LIKE '%$safeSearch%' OR p.kode_titip LIKE '%$safeSearch%' OR k.kode_kunjungan LIKE '%$safeSearch%' OR i.nama_barang LIKE '%$safeSearch%' OR k.no_inv LIKE '%$safeSearch%' OR p.nama_sales LIKE '%$safeSearch%' OR k.nama_sales LIKE '%$safeSearch%')";
    }

    $whereClause = implode(" AND ", $where);

    $sql = "SELECT k.id AS id_kunjungan, k.id_penitipan, k.id_item, 
                   COALESCE(NULLIF(p.id_sales, 0), k.id_sales) AS id_sales, 
                   COALESCE(NULLIF(p.nama_sales, ''), k.nama_sales) AS nama_sales, 
                   k.kode_kunjungan, k.tgl_kunjungan, k.stok_sebelumnya, k.stok_sisa, 
                   k.qty_terjual_kunjungan, k.no_inv, k.tgl_invoice, k.insentif_didapat, 
                   k.catatan_kunjungan, k.foto_kunjungan, k.id_claim, k.created_at,
                   i.nama_barang, i.tipe_barang, i.insentif_per_unit,
                   p.kode_titip, p.tgl_titip, p.id_customer,
                   $custField AS nama_toko,
                   $custKatField AS kategori_toko,
                   $custKotaField AS kota_toko
            FROM tiptok_kunjungan k
            JOIN tiptok_items i ON k.id_item = i.id
            JOIN tiptok_penitipan p ON k.id_penitipan = p.id
            $custJoin
            WHERE $whereClause
            ORDER BY (k.no_inv IS NULL OR k.no_inv = '') DESC, k.tgl_kunjungan DESC, k.id DESC";

    $res = $conn->query($sql);
    $items = [];
    $totalUnitTerjual = 0;
    $totalPendingInvoice = 0;
    $totalSudahInvoice = 0;
    $totalNominalInsentif = 0;

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $qty = intval($r['qty_terjual_kunjungan']);
            $hasInv = (!empty($r['no_inv']) && trim($r['no_inv']) !== '');
            $totalUnitTerjual += $qty;
            if ($hasInv) {
                $totalSudahInvoice += $qty;
            } else {
                $totalPendingInvoice += $qty;
            }
            $totalNominalInsentif += floatval($r['insentif_didapat']);
            $items[] = $r;
        }
    }

    // Global stats dengan filter sales jika dipilih
    $whereStatSales = "";
    if ($jabatanUser === 'Sales') {
        $whereStatSales = " AND (p.id_sales = '$idUser' OR k.id_sales = '$idUser' OR p.nama_sales = '" . $conn->real_escape_string($namaUser) . "')";
    } elseif ($id_sales_filter > 0) {
        if (!empty($salesNameFilter)) {
            $safeNameFilter = $conn->real_escape_string($salesNameFilter);
            $whereStatSales = " AND (p.id_sales = '$id_sales_filter' OR k.id_sales = '$id_sales_filter' OR p.nama_sales = '$safeNameFilter' OR k.nama_sales = '$safeNameFilter' OR p.nama_sales LIKE '%$safeNameFilter%' OR k.nama_sales LIKE '%$safeNameFilter%')";
        } else {
            $whereStatSales = " AND (p.id_sales = '$id_sales_filter' OR k.id_sales = '$id_sales_filter')";
        }
    }

    $qStat = $conn->query("SELECT 
        SUM(k.qty_terjual_kunjungan) AS grand_total_unit,
        SUM(CASE WHEN k.no_inv IS NULL OR TRIM(k.no_inv) = '' THEN k.qty_terjual_kunjungan ELSE 0 END) AS grand_pending_unit,
        SUM(CASE WHEN k.no_inv IS NOT NULL AND TRIM(k.no_inv) != '' THEN k.qty_terjual_kunjungan ELSE 0 END) AS grand_invoiced_unit,
        SUM(CASE WHEN k.no_inv IS NULL OR TRIM(k.no_inv) = '' THEN 1 ELSE 0 END) AS grand_pending_trx,
        SUM(k.insentif_didapat) AS grand_total_insentif
        FROM tiptok_kunjungan k 
        JOIN tiptok_penitipan p ON k.id_penitipan = p.id
        WHERE k.qty_terjual_kunjungan > 0 $whereStatSales");
    $stats = $qStat ? $qStat->fetch_assoc() : [];

    // Leaderboard & Sales Recap calculation (ranked by Invoiced Unit -> Total Terjual -> Insentif)
    $qSalesLeaderboard = $conn->query("SELECT 
        COALESCE(NULLIF(p.id_sales, 0), k.id_sales, 0) AS id_sales,
        COALESCE(NULLIF(p.nama_sales, ''), k.nama_sales, 'Sales') AS nama_sales,
        COUNT(DISTINCT p.id_customer) AS total_toko,
        COUNT(DISTINCT k.id_penitipan) AS total_penitipan,
        SUM(k.qty_terjual_kunjungan) AS total_terjual,
        SUM(CASE WHEN k.no_inv IS NOT NULL AND TRIM(k.no_inv) != '' THEN k.qty_terjual_kunjungan ELSE 0 END) AS invoiced_unit,
        SUM(CASE WHEN k.no_inv IS NULL OR TRIM(k.no_inv) = '' THEN k.qty_terjual_kunjungan ELSE 0 END) AS pending_unit,
        COUNT(DISTINCT CASE WHEN k.no_inv IS NOT NULL AND TRIM(k.no_inv) != '' THEN k.no_inv ELSE NULL END) AS count_invoices,
        COUNT(CASE WHEN k.no_inv IS NULL OR TRIM(k.no_inv) = '' THEN 1 ELSE NULL END) AS count_pending_trx,
        SUM(k.insentif_didapat) AS total_insentif
        FROM tiptok_kunjungan k
        JOIN tiptok_penitipan p ON k.id_penitipan = p.id
        WHERE k.qty_terjual_kunjungan > 0
        GROUP BY COALESCE(NULLIF(p.id_sales, 0), k.id_sales, 0), COALESCE(NULLIF(p.nama_sales, ''), k.nama_sales, 'Sales')
        ORDER BY invoiced_unit DESC, total_terjual DESC, total_insentif DESC");

    $leaderboard = [];
    $rank = 1;
    if ($qSalesLeaderboard) {
        while ($sRow = $qSalesLeaderboard->fetch_assoc()) {
            $invUnit = intval($sRow['invoiced_unit'] ?? 0);
            $totTerjual = intval($sRow['total_terjual'] ?? 0);
            $pctClaim = min(100, round(($invUnit / 50) * 100));
            $sRow['rank'] = $rank++;
            $sRow['id_sales'] = intval($sRow['id_sales']);
            $sRow['total_toko'] = intval($sRow['total_toko']);
            $sRow['total_penitipan'] = intval($sRow['total_penitipan']);
            $sRow['total_terjual'] = $totTerjual;
            $sRow['invoiced_unit'] = $invUnit;
            $sRow['pending_unit'] = intval($sRow['pending_unit'] ?? 0);
            $sRow['count_invoices'] = intval($sRow['count_invoices'] ?? 0);
            $sRow['count_pending_trx'] = intval($sRow['count_pending_trx'] ?? 0);
            $sRow['total_insentif'] = floatval($sRow['total_insentif'] ?? 0);
            $sRow['claim_progress'] = $pctClaim;
            $sRow['claim_target'] = 50;
            $sRow['units_needed'] = max(0, 50 - $invUnit);
            $leaderboard[] = $sRow;
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'items' => $items,
            'leaderboard' => $leaderboard,
            'filtered_stats' => [
                'total_unit' => $totalUnitTerjual,
                'total_pending' => $totalPendingInvoice,
                'total_invoiced' => $totalSudahInvoice,
                'total_insentif' => $totalNominalInsentif,
            ],
            'global_stats' => [
                'grand_total_unit' => intval($stats['grand_total_unit'] ?? 0),
                'grand_pending_unit' => intval($stats['grand_pending_unit'] ?? 0),
                'grand_invoiced_unit' => intval($stats['grand_invoiced_unit'] ?? 0),
                'grand_pending_trx' => intval($stats['grand_pending_trx'] ?? 0),
                'grand_total_insentif' => floatval($stats['grand_total_insentif'] ?? 0)
            ]
        ]
    ]);
    exit;
}

// -------------------------------------------------------------
// 13. SIMPAN NO. INVOICE SINGLE ITEM
// -------------------------------------------------------------
if ($action === 'simpan_invoice_item') {
    $id_kunjungan = intval($_POST['id_kunjungan'] ?? 0);
    $no_inv = trim($_POST['no_inv'] ?? '');
    $tgl_invoice = trim($_POST['tgl_invoice'] ?? '');
    $catatan = trim($_POST['catatan_invoice'] ?? '');

    if ($id_kunjungan <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID Transaksi Kunjungan tidak valid.']);
        exit;
    }
    if (empty($no_inv)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor Invoice wajib diisi.']);
        exit;
    }
    if (empty($tgl_invoice)) {
        $tgl_invoice = date('Y-m-d');
    }

    $stmt = $conn->prepare("UPDATE tiptok_kunjungan SET no_inv = ?, tgl_invoice = ? WHERE id = ?");
    $stmt->bind_param("ssi", $no_inv, $tgl_invoice, $id_kunjungan);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => "Nomor Invoice [{$no_inv}] berhasil disimpan."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan invoice: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// -------------------------------------------------------------
// 14. SIMPAN BATCH / KOLEKTIF NO. INVOICE (MULTI ITEMS)
// -------------------------------------------------------------
if ($action === 'simpan_batch_invoice') {
    $idListRaw = $_POST['id_kunjungan_list'] ?? [];
    $no_inv = trim($_POST['no_inv'] ?? '');
    $tgl_invoice = trim($_POST['tgl_invoice'] ?? '');

    if (empty($no_inv)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor Invoice kolektif wajib diisi.']);
        exit;
    }
    if (empty($tgl_invoice)) {
        $tgl_invoice = date('Y-m-d');
    }

    $idArray = [];
    if (is_array($idListRaw)) {
        foreach ($idListRaw as $id) {
            $v = intval($id);
            if ($v > 0) $idArray[] = $v;
        }
    } elseif (is_string($idListRaw)) {
        $exploded = explode(',', $idListRaw);
        foreach ($exploded as $id) {
            $v = intval(trim($id));
            if ($v > 0) $idArray[] = $v;
        }
    }

    if (empty($idArray)) {
        echo json_encode(['status' => 'error', 'message' => 'Pilih minimal satu item barang untuk mengisi No. Invoice.']);
        exit;
    }

    $idString = implode(',', $idArray);
    $safeInv = $conn->real_escape_string($no_inv);
    $safeTgl = $conn->real_escape_string($tgl_invoice);

    $upd = $conn->query("UPDATE tiptok_kunjungan SET no_inv = '$safeInv', tgl_invoice = '$safeTgl' WHERE id IN ($idString)");
    if ($upd) {
        $count = count($idArray);
        echo json_encode(['status' => 'success', 'message' => "Berhasil menetapkan No. Invoice [{$no_inv}] ke {$count} transaksi barang terpilih."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate batch invoice: ' . $conn->error]);
    }
    exit;
}

// -------------------------------------------------------------
// 15. HAPUS / RESET NO. INVOICE DARI ITEM
// -------------------------------------------------------------
if ($action === 'hapus_invoice_item') {
    $id_kunjungan = intval($_POST['id_kunjungan'] ?? 0);
    if ($id_kunjungan <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID Transaksi Kunjungan tidak valid.']);
        exit;
    }

    // Cek apakah item sudah diajukan claim
    $qCheck = $conn->query("SELECT id_claim, no_inv FROM tiptok_kunjungan WHERE id = $id_kunjungan");
    if ($qCheck && $qCheck->num_rows > 0) {
        $row = $qCheck->fetch_assoc();
        if (!empty($row['id_claim'])) {
            echo json_encode(['status' => 'error', 'message' => 'No. Invoice tidak dapat dihapus karena transaksi ini sudah masuk proses klaim insentif.']);
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE tiptok_kunjungan SET no_inv = NULL, tgl_invoice = NULL WHERE id = ?");
    $stmt->bind_param("i", $id_kunjungan);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'No. Invoice berhasil direset.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mereset invoice: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// Default error
echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali.']);
exit;
