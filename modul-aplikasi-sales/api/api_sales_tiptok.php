<?php
/**
 * API Sales TIP TOK (Titip Barang di Toko / Konsinyasi)
 * Melayani aplikasi mobile Flutter Loewix Sales
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/api_db.php';

// Auto-create tables safeguard
$checkTbl = mysqli_query($conn, "SHOW TABLES LIKE 'tiptok_penitipan'");
if (!$checkTbl || mysqli_num_rows($checkTbl) == 0) {
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

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
if (empty($action) && isset($jsonInput['action'])) {
    $action = $jsonInput['action'];
}

// Cek apakah tabel sales_customer ada, jika tidak gunakan tabel customers + customer_addresses + customer_pics
$chkSC = $conn->query("SHOW TABLES LIKE 'sales_customer'");
$hasSalesCustomer = ($chkSC && $chkSC->num_rows > 0);

// ─────────────────────────────────────────────────────────────
// 1. GET DASHBOARD & LIST TITIPAN SALES
// ─────────────────────────────────────────────────────────────
if ($action === 'get_dashboard') {
    $salesId = intval($_GET['sales_id'] ?? ($jsonInput['sales_id'] ?? 0));
    $filterSales = $salesId > 0 ? " AND p.id_sales = $salesId " : "";
    $filterKunjunganSales = $salesId > 0 ? " AND k.id_sales = $salesId " : "";

    // Metrik
    $qToko = $conn->query("SELECT COUNT(DISTINCT p.id_customer) as total FROM tiptok_penitipan p WHERE p.status = 'aktif' $filterSales");
    $totalTokoAktif = $qToko ? intval($qToko->fetch_assoc()['total'] ?? 0) : 0;

    $qUnit = $conn->query("SELECT SUM(i.qty_titip) as total_titip, SUM(i.qty_sisa) as total_sisa, SUM(i.qty_terjual) as total_terjual, SUM(i.total_insentif) as total_insentif 
                          FROM tiptok_items i 
                          JOIN tiptok_penitipan p ON i.id_penitipan = p.id 
                          WHERE 1=1 $filterSales");
    $dataUnit = $qUnit ? $qUnit->fetch_assoc() : [];
    $totalTitip = intval($dataUnit['total_titip'] ?? 0);
    $totalSisa = intval($dataUnit['total_sisa'] ?? 0);
    $totalTerjual = intval($dataUnit['total_terjual'] ?? 0);
    $totalInsentif = floatval($dataUnit['total_insentif'] ?? 0);

    // Unclaimed Insentif
    $qUnclaimed = $conn->query("SELECT SUM(k.qty_terjual_kunjungan) as total_unclaimed, SUM(k.insentif_didapat) as nominal_unclaimed 
                               FROM tiptok_kunjungan k 
                               WHERE k.id_claim IS NULL AND k.qty_terjual_kunjungan > 0 $filterKunjunganSales");
    $dataUnclaimed = $qUnclaimed ? $qUnclaimed->fetch_assoc() : [];
    $unclaimedUnits = intval($dataUnclaimed['total_unclaimed'] ?? 0);
    $unclaimedNominal = floatval($dataUnclaimed['nominal_unclaimed'] ?? 0);

    $claimTarget = 50;
    $claimProgress = min(100, round(($unclaimedUnits / $claimTarget) * 100, 1));
    $isClaimEligible = ($unclaimedUnits >= $claimTarget);

    // List Penitipan
    if ($hasSalesCustomer) {
        $sqlList = "SELECT p.*, c.nama AS nama_toko, c.kategori AS kategori_customer, c.telp_pribadi AS telp_toko, 
                           c.alamat AS alamat_toko, c.kota AS kota_toko,
                           COUNT(i.id) AS total_jenis_barang,
                           SUM(i.qty_titip) AS sum_titip,
                           SUM(i.qty_sisa) AS sum_sisa,
                           SUM(i.qty_terjual) AS sum_terjual,
                           SUM(i.total_insentif) AS sum_insentif,
                           (SELECT k.no_inv FROM tiptok_kunjungan k WHERE k.id_penitipan = p.id AND k.no_inv IS NOT NULL AND k.no_inv != '' ORDER BY k.tgl_kunjungan DESC, k.id DESC LIMIT 1) AS last_no_inv,
                           (SELECT k.tgl_kunjungan FROM tiptok_kunjungan k WHERE k.id_penitipan = p.id ORDER BY k.tgl_kunjungan DESC, k.id DESC LIMIT 1) AS last_kunjungan
                    FROM tiptok_penitipan p 
                    LEFT JOIN sales_customer c ON p.id_customer = c.id 
                    LEFT JOIN tiptok_items i ON p.id = i.id_penitipan 
                    WHERE 1=1 $filterSales 
                    GROUP BY p.id 
                    ORDER BY p.id DESC";
    } else {
        $sqlList = "SELECT p.*, c.nama_toko AS nama_toko, c.kategori AS kategori_customer, pic.tlp_pic AS telp_toko, 
                           ca.alamat AS alamat_toko, ca.kota AS kota_toko,
                           COUNT(i.id) AS total_jenis_barang,
                           SUM(i.qty_titip) AS sum_titip,
                           SUM(i.qty_sisa) AS sum_sisa,
                           SUM(i.qty_terjual) AS sum_terjual,
                           SUM(i.total_insentif) AS sum_insentif,
                           (SELECT k.no_inv FROM tiptok_kunjungan k WHERE k.id_penitipan = p.id AND k.no_inv IS NOT NULL AND k.no_inv != '' ORDER BY k.tgl_kunjungan DESC, k.id DESC LIMIT 1) AS last_no_inv,
                           (SELECT k.tgl_kunjungan FROM tiptok_kunjungan k WHERE k.id_penitipan = p.id ORDER BY k.tgl_kunjungan DESC, k.id DESC LIMIT 1) AS last_kunjungan
                    FROM tiptok_penitipan p 
                    LEFT JOIN customers c ON p.id_customer = c.id 
                    LEFT JOIN customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
                    LEFT JOIN customer_pics pic ON c.id = pic.customer_id AND pic.deleted_at IS NULL
                    LEFT JOIN tiptok_items i ON p.id = i.id_penitipan 
                    WHERE 1=1 $filterSales 
                    GROUP BY p.id 
                    ORDER BY p.id DESC";
    }
    $resList = $conn->query($sqlList);
    $penitipanList = [];
    if ($resList) {
        while ($row = $resList->fetch_assoc()) {
            $idPen = intval($row['id']);
            $qItems = $conn->query("SELECT * FROM tiptok_items WHERE id_penitipan = $idPen ORDER BY id ASC");
            $items = [];
            if ($qItems) {
                while ($it = $qItems->fetch_assoc()) {
                    $items[] = [
                        'id' => intval($it['id']),
                        'nama_barang' => $it['nama_barang'],
                        'tipe_barang' => $it['tipe_barang'] ?? '',
                        'qty_titip' => intval($it['qty_titip']),
                        'qty_sisa' => intval($it['qty_sisa']),
                        'qty_terjual' => intval($it['qty_terjual']),
                        'insentif_per_unit' => floatval($it['insentif_per_unit']),
                        'total_insentif' => floatval($it['total_insentif']),
                        'status_item' => $it['status_item'],
                    ];
                }
            }
            $row['items'] = $items;
            $row['id'] = intval($row['id']);
            $row['id_customer'] = intval($row['id_customer']);
            $row['sum_titip'] = intval($row['sum_titip'] ?? 0);
            $row['sum_sisa'] = intval($row['sum_sisa'] ?? 0);
            $row['sum_terjual'] = intval($row['sum_terjual'] ?? 0);
            $row['sum_insentif'] = floatval($row['sum_insentif'] ?? 0);
            $penitipanList[] = $row;
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'metrics' => [
                'total_toko_aktif' => $totalTokoAktif,
                'total_titip' => $totalTitip,
                'total_sisa' => $totalSisa,
                'total_terjual' => $totalTerjual,
                'total_insentif' => $totalInsentif,
                'unclaimed_units' => $unclaimedUnits,
                'unclaimed_nominal' => $unclaimedNominal,
                'claim_target' => $claimTarget,
                'claim_progress' => $claimProgress,
                'is_claim_eligible' => $isClaimEligible,
            ],
            'penitipan_list' => $penitipanList,
        ]
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// 2. GET DEALERS LIST (Hanya Toko yang Terjadwal untuk Sales)
// ─────────────────────────────────────────────────────────────
if ($action === 'get_dealers') {
    $salesId = intval($_GET['sales_id'] ?? ($jsonInput['sales_id'] ?? 0));
    $search = $conn->real_escape_string($_GET['q'] ?? ($jsonInput['q'] ?? ''));
    
    if ($hasSalesCustomer) {
        $whereSearch = $search !== '' ? " AND (c.nama LIKE '%$search%' OR c.alamat LIKE '%$search%' OR c.kota LIKE '%$search%') " : "";
        if ($salesId > 0) {
            $sql = "SELECT DISTINCT c.id, c.nama, c.kategori, c.telp_pribadi, c.alamat, c.kota, ks.jadwal, ks.id AS id_kegiatan
                    FROM team_kegiatan_sales tks
                    JOIN kegiatan_sales ks ON ks.id = tks.id_kegiatan_sales AND ks.deleted_at IS NULL
                    JOIN sales_customer c  ON c.id  = ks.id_customer        AND c.deleted_at IS NULL
                    WHERE tks.id_sales = $salesId
                      AND tks.deleted_at IS NULL
                      AND DATE(ks.jadwal) = CURDATE()
                      AND ks.status NOT IN ('waiting', 'dibatalkan', 'reschedule', 'cancelled')
                      AND (ks.reschedule_reason IS NULL OR ks.reschedule_reason = '')
                      $whereSearch
                    ORDER BY ks.jadwal DESC
                    LIMIT 50";
        } else {
            $sql = "SELECT id, nama, kategori, telp_pribadi, alamat, kota 
                    FROM sales_customer c
                    WHERE deleted_at IS NULL $whereSearch 
                    ORDER BY (kategori = 'Dealer') DESC, nama ASC 
                    LIMIT 50";
        }
    } else {
        $whereSearch = $search !== '' ? " AND (c.nama_toko LIKE '%$search%' OR ca.alamat LIKE '%$search%' OR ca.kota LIKE '%$search%') " : "";
        if ($salesId > 0) {
            $sql = "SELECT DISTINCT c.id, c.nama_toko AS nama, c.kategori, pic.tlp_pic AS telp_pribadi, ca.alamat, ca.kota, ks.jadwal, ks.id AS id_kegiatan
                    FROM team_kegiatan_sales tks
                    JOIN kegiatan_sales ks ON ks.id = tks.id_kegiatan_sales AND ks.deleted_at IS NULL
                    JOIN customers c       ON c.id  = ks.id_customer        AND c.deleted_at IS NULL
                    LEFT JOIN customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
                    LEFT JOIN customer_pics pic ON c.id = pic.customer_id AND pic.deleted_at IS NULL
                    WHERE tks.id_sales = $salesId
                      AND tks.deleted_at IS NULL
                      AND DATE(ks.jadwal) = CURDATE()
                      AND ks.status NOT IN ('waiting', 'dibatalkan', 'reschedule', 'cancelled')
                      AND (ks.reschedule_reason IS NULL OR ks.reschedule_reason = '')
                      $whereSearch
                    ORDER BY ks.jadwal DESC
                    LIMIT 50";
        } else {
            $sql = "SELECT c.id, c.nama_toko AS nama, c.kategori, pic.tlp_pic AS telp_pribadi, ca.alamat, ca.kota 
                    FROM customers c
                    LEFT JOIN customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
                    LEFT JOIN customer_pics pic ON c.id = pic.customer_id AND pic.deleted_at IS NULL
                    WHERE c.deleted_at IS NULL $whereSearch 
                    ORDER BY (c.kategori = 'Dealer') DESC, c.nama_toko ASC 
                    LIMIT 50";
        }
    }
    
    $res = $conn->query($sql);
    $dealers = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $dealers[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $dealers]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// 3. CREATE PENITIPAN BARU (TITIP BARANG)
// ─────────────────────────────────────────────────────────────
if ($action === 'create_penitipan') {
    $input = !empty($jsonInput) ? $jsonInput : $_POST;
    
    $idCustomer = intval($input['id_customer'] ?? 0);
    $idSales = intval($input['id_sales'] ?? 0);
    $namaSales = trim($input['nama_sales'] ?? '');
    $tglTitip = trim($input['tgl_titip'] ?? date('Y-m-d'));
    $catatan = trim($input['catatan'] ?? '');
    $items = $input['items'] ?? [];

    if ($idCustomer <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pilih Toko / Dealer terlebih dahulu!']);
        exit;
    }
    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Minimal harus menambahkan 1 barang titipan!']);
        exit;
    }

    // Validasi Wajib Jadwal Kunjungan dari Admin pada hari ini
    if ($idSales > 0) {
        $checkJadwal = $conn->query("SELECT ks.id FROM team_kegiatan_sales tks
            JOIN kegiatan_sales ks ON ks.id = tks.id_kegiatan_sales AND ks.deleted_at IS NULL
            WHERE tks.id_sales = $idSales AND ks.id_customer = $idCustomer
              AND tks.deleted_at IS NULL
              AND DATE(ks.jadwal) = CURDATE()
              AND ks.status NOT IN ('waiting', 'dibatalkan', 'reschedule', 'cancelled')
            LIMIT 1");
        if (!$checkJadwal || $checkJadwal->num_rows == 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Penitipan ditolak: Anda belum memiliki jadwal kunjungan dari Admin untuk toko ini. Titip barang hanya dapat dilakukan jika ada jadwal kunjungan resmi dari Admin.'
            ]);
            exit;
        }
    }

    $prefix = "TP-" . date('Ymd', strtotime($tglTitip)) . "-";
    $qLast = $conn->query("SELECT kode_titip FROM tiptok_penitipan WHERE kode_titip LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    $lastNum = 0;
    if ($qLast && $rowLast = $qLast->fetch_assoc()) {
        $lastNum = intval(substr($rowLast['kode_titip'], -3));
    }
    $kodeTitip = $prefix . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO tiptok_penitipan (kode_titip, id_customer, id_sales, nama_sales, tgl_titip, status, catatan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'aktif', ?, NOW(), NOW())");
    $stmt->bind_param("siisss", $kodeTitip, $idCustomer, $idSales, $namaSales, $tglTitip, $catatan);
    
    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan penitipan: ' . $stmt->error]);
        exit;
    }
    $idPenitipan = $conn->insert_id;

    $stmtItem = $conn->prepare("INSERT INTO tiptok_items (id_penitipan, kode_titip, nama_barang, tipe_barang, qty_titip, qty_sisa, qty_terjual, insentif_per_unit, total_insentif, status_item, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0, 'titip', NOW(), NOW())");

    foreach ($items as $it) {
        $namaBarang = trim($it['nama_barang'] ?? '');
        $tipeBarang = trim($it['tipe_barang'] ?? '');
        $qtyTitip = intval($it['qty_titip'] ?? 0);
        $insentifUnit = floatval($it['insentif_per_unit'] ?? 0);

        if (!empty($namaBarang) && $qtyTitip > 0) {
            $qtySisa = $qtyTitip;
            $stmtItem->bind_param("isssiid", $idPenitipan, $kodeTitip, $namaBarang, $tipeBarang, $qtyTitip, $qtySisa, $insentifUnit);
            $stmtItem->execute();
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Penitipan barang berhasil disimpan dengan kode: ' . $kodeTitip,
        'data' => [
            'id_penitipan' => $idPenitipan,
            'kode_titip' => $kodeTitip,
        ]
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// 4. AUDIT KUNJUNGAN & CEK SISA STOK TOKO
// ─────────────────────────────────────────────────────────────
if ($action === 'audit_kunjungan') {
    $input = !empty($jsonInput) ? $jsonInput : $_POST;

    $idPenitipan = intval($input['id_penitipan'] ?? 0);
    $idSales = intval($input['id_sales'] ?? 0);
    $namaSales = trim($input['nama_sales'] ?? '');
    $tglKunjungan = trim($input['tgl_kunjungan'] ?? date('Y-m-d'));
    $catatanKunjungan = trim($input['catatan'] ?? '');
    $items = $input['items'] ?? [];

    if ($idPenitipan <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data penitipan tidak valid!']);
        exit;
    }
    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Rincian audit barang kosong!']);
        exit;
    }

    // Validasi No Invoice jika ada unit terjual
    foreach ($items as $it) {
        $idItem = intval($it['id_item'] ?? 0);
        $stokSisa = intval($it['stok_sisa'] ?? 0);
        $noInv = trim($it['no_inv'] ?? '');

        $qCur = $conn->query("SELECT * FROM tiptok_items WHERE id = $idItem AND id_penitipan = $idPenitipan");
        if ($qCur && $cur = $qCur->fetch_assoc()) {
            $stokPrev = intval($cur['qty_sisa']);
            if ($stokSisa < 0) {
                echo json_encode(['status' => 'error', 'message' => 'Stok sisa untuk barang "' . $cur['nama_barang'] . '" tidak boleh negatif!']);
                exit;
            }
            if ($stokSisa > $stokPrev) {
                echo json_encode(['status' => 'error', 'message' => 'Stok sisa (' . $stokSisa . ') tidak boleh lebih besar dari stok sebelumnya (' . $stokPrev . ')!']);
                exit;
            }
            $terjual = $stokPrev - $stokSisa;
            if ($terjual > 0 && empty($noInv)) {
                echo json_encode(['status' => 'error', 'message' => 'Terdapat ' . $terjual . ' unit "' . $cur['nama_barang'] . '" terjual. Nomor Invoice (No. INV) WAJIB DIISI!']);
                exit;
            }
        }
    }

    // Eksekusi Simpan Log Kunjungan & Update Stok
    $prefixVis = "VIS-" . date('Ymd', strtotime($tglKunjungan)) . "-";
    $qLastVis = $conn->query("SELECT kode_kunjungan FROM tiptok_kunjungan WHERE kode_kunjungan LIKE '$prefixVis%' ORDER BY id DESC LIMIT 1");
    $lastVisNum = 0;
    if ($qLastVis && $rowV = $qLastVis->fetch_assoc()) {
        $lastVisNum = intval(substr($rowV['kode_kunjungan'], -3));
    }

    $totalInsentifDidapat = 0;
    $totalTerjualKunjungan = 0;

    foreach ($items as $it) {
        $idItem = intval($it['id_item'] ?? 0);
        $stokSisa = intval($it['stok_sisa'] ?? 0);
        $noInv = trim($it['no_inv'] ?? '');
        $tglInvoice = !empty($it['tgl_invoice']) ? trim($it['tgl_invoice']) : ($terjual > 0 ? $tglKunjungan : null);

        $qCur = $conn->query("SELECT * FROM tiptok_items WHERE id = $idItem AND id_penitipan = $idPenitipan");
        if ($qCur && $cur = $qCur->fetch_assoc()) {
            $stokPrev = intval($cur['qty_sisa']);
            $terjual = max(0, $stokPrev - $stokSisa);
            $insentifUnit = floatval($cur['insentif_per_unit']);
            $insentifKunjungan = $terjual * $insentifUnit;

            $lastVisNum++;
            $kodeKunjungan = $prefixVis . str_pad($lastVisNum, 3, '0', STR_PAD_LEFT);

            $stmtLog = $conn->prepare("INSERT INTO tiptok_kunjungan (kode_kunjungan, id_penitipan, id_item, id_sales, nama_sales, tgl_kunjungan, stok_sebelumnya, stok_sisa, qty_terjual_kunjungan, no_inv, tgl_invoice, insentif_didapat, catatan_kunjungan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmtLog->bind_param("siiissiiisdds", $kodeKunjungan, $idPenitipan, $idItem, $idSales, $namaSales, $tglKunjungan, $stokPrev, $stokSisa, $terjual, $noInv, $tglInvoice, $insentifKunjungan, $catatanKunjungan);
            $stmtLog->execute();

            // Update Master Item
            $newTerjualTotal = intval($cur['qty_terjual']) + $terjual;
            $newInsentifTotal = floatval($cur['total_insentif']) + $insentifKunjungan;
            $newItemStatus = ($stokSisa == 0) ? 'habis_terjual' : 'titip';

            $stmtUpItem = $conn->prepare("UPDATE tiptok_items SET qty_sisa = ?, qty_terjual = ?, total_insentif = ?, status_item = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpItem->bind_param("iidsi", $stokSisa, $newTerjualTotal, $newInsentifTotal, $newItemStatus, $idItem);
            $stmtUpItem->execute();

            $totalInsentifDidapat += $insentifKunjungan;
            $totalTerjualKunjungan += $terjual;
        }
    }

    // Cek apakah seluruh item sudah habis terjual
    $qCheckSisa = $conn->query("SELECT SUM(qty_sisa) as total_sisa FROM tiptok_items WHERE id_penitipan = $idPenitipan");
    $totalSisaPen = $qCheckSisa ? intval($qCheckSisa->fetch_assoc()['total_sisa'] ?? 0) : 0;
    if ($totalSisaPen == 0) {
        $conn->query("UPDATE tiptok_penitipan SET status = 'selesai', updated_at = NOW() WHERE id = $idPenitipan");
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Audit stok toko berhasil disimpan!' . ($totalTerjualKunjungan > 0 ? " Terjual: $totalTerjualKunjungan unit (Insentif: Rp " . number_format($totalInsentifDidapat, 0, ',', '.') . ")" : ""),
        'data' => [
            'total_terjual' => $totalTerjualKunjungan,
            'total_insentif' => $totalInsentifDidapat,
        ]
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// 5. CLAIM INSENTIF (MIN. 50 UNIT)
// ─────────────────────────────────────────────────────────────
if ($action === 'claim_insentif') {
    $input = !empty($jsonInput) ? $jsonInput : $_POST;

    $idSales = intval($input['sales_id'] ?? ($input['id_sales'] ?? 0));
    $namaSales = trim($input['nama_sales'] ?? '');
    $catatanClaim = trim($input['catatan_claim'] ?? '');

    if ($idSales <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Identitas Sales tidak valid!']);
        exit;
    }

    // Ambil semua log penjualan yang belum diklaim
    $qUnclaimed = $conn->query("SELECT k.id as id_kunjungan, k.id_penitipan, k.id_item, k.qty_terjual_kunjungan, k.no_inv, k.insentif_didapat, i.nama_barang, i.insentif_per_unit 
                                FROM tiptok_kunjungan k 
                                JOIN tiptok_items i ON k.id_item = i.id 
                                WHERE k.id_claim IS NULL AND k.qty_terjual_kunjungan > 0 AND k.id_sales = $idSales 
                                ORDER BY k.id ASC");
    $unclaimedRows = [];
    $totalUnitTerjual = 0;
    $totalNominalInsentif = 0;

    if ($qUnclaimed) {
        while ($row = $qUnclaimed->fetch_assoc()) {
            $unclaimedRows[] = $row;
            $totalUnitTerjual += intval($row['qty_terjual_kunjungan']);
            $totalNominalInsentif += floatval($row['insentif_didapat']);
        }
    }

    if ($totalUnitTerjual < 50) {
        echo json_encode(['status' => 'error', 'message' => "Syarat klaim insentif minimal 50 unit terjual! Saat ini baru terkumpul $totalUnitTerjual unit (Kurang " . (50 - $totalUnitTerjual) . " unit lagi)."]);
        exit;
    }

    $prefixClm = "CLM-" . date('Ymd') . "-";
    $qLastClm = $conn->query("SELECT kode_claim FROM tiptok_claim WHERE kode_claim LIKE '$prefixClm%' ORDER BY id DESC LIMIT 1");
    $lastClmNum = 0;
    if ($qLastClm && $rowC = $qLastClm->fetch_assoc()) {
        $lastClmNum = intval(substr($rowC['kode_claim'], -3));
    }
    $kodeClaim = $prefixClm . str_pad($lastClmNum + 1, 3, '0', STR_PAD_LEFT);
    $tglClaim = date('Y-m-d');

    $stmtClaim = $conn->prepare("INSERT INTO tiptok_claim (kode_claim, id_sales, nama_sales, tgl_claim, total_unit_terjual, total_nominal_insentif, status_claim, catatan_claim, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'menunggu_approval', ?, NOW(), NOW())");
    $stmtClaim->bind_param("sissids", $kodeClaim, $idSales, $namaSales, $tglClaim, $totalUnitTerjual, $totalNominalInsentif, $catatanClaim);
    
    if (!$stmtClaim->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membuat pengajuan klaim: ' . $stmtClaim->error]);
        exit;
    }
    $idClaim = $conn->insert_id;

    $stmtDetail = $conn->prepare("INSERT INTO tiptok_claim_detail (id_claim, id_kunjungan_log, id_penitipan, id_item, nama_barang, no_inv, qty_terjual, insentif_per_unit, subtotal_insentif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($unclaimedRows as $item) {
        $idKunj = intval($item['id_kunjungan']);
        $idPen = intval($item['id_penitipan']);
        $idItm = intval($item['id_item']);
        $nmBrg = $item['nama_barang'];
        $inv = $item['no_inv'];
        $qty = intval($item['qty_terjual_kunjungan']);
        $insUnit = floatval($item['insentif_per_unit']);
        $subtotal = floatval($item['insentif_didapat']);

        $stmtDetail->bind_param("iiiissidd", $idClaim, $idKunj, $idPen, $idItm, $nmBrg, $inv, $qty, $insUnit, $subtotal);
        $stmtDetail->execute();

        $conn->query("UPDATE tiptok_kunjungan SET id_claim = $idClaim WHERE id = $idKunj");
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Pengajuan klaim berhasil diajukan dengan kode: $kodeClaim (Total: $totalUnitTerjual unit - Rp " . number_format($totalNominalInsentif, 0, ',', '.') . "). Menunggu persetujuan Admin.",
        'data' => [
            'id_claim' => $idClaim,
            'kode_claim' => $kodeClaim,
            'total_unit' => $totalUnitTerjual,
            'total_nominal' => $totalNominalInsentif,
        ]
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// 6. GET CLAIMS HISTORY
// ─────────────────────────────────────────────────────────────
if ($action === 'get_claims') {
    $salesId = intval($_GET['sales_id'] ?? ($jsonInput['sales_id'] ?? 0));
    $whereSales = $salesId > 0 ? " WHERE c.id_sales = $salesId " : "";

    $res = $conn->query("SELECT c.* FROM tiptok_claim c $whereSales ORDER BY c.id DESC LIMIT 50");
    $claims = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $row['total_unit_terjual'] = intval($row['total_unit_terjual']);
            $row['total_nominal_insentif'] = floatval($row['total_nominal_insentif']);
            $claims[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $claims]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali']);
