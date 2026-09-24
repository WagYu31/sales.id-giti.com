<?php
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";

$pageNow = "TIP TOK";
$currentPage = "Today";

$idSesi = $_SESSION["id"] ?? 0;
$role = $_SESSION["jabatan"] ?? 'Sales';
$namaSesi = $nmUser ?? ($_SESSION["nama"] ?? 'Sales');

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

// Ambil Statistik Live untuk Bento KPI
$filterSales = "";
if ($role === 'Sales') {
    $filterSales = " AND p.id_sales = '$idSesi' ";
}

// 1. Total Toko Penitipan Aktif
$qTokoAktif = $conn->query("SELECT COUNT(DISTINCT p.id_customer) as total FROM tiptok_penitipan p WHERE p.status = 'aktif' $filterSales");
$totalTokoAktif = $qTokoAktif ? ($qTokoAktif->fetch_assoc()['total'] ?? 0) : 0;

// 2. Total Unit Dititip (Stok Awal) & Total Stok Sisa di Toko
$qUnit = $conn->query("SELECT SUM(i.qty_titip) as total_titip, SUM(i.qty_sisa) as total_sisa, SUM(i.qty_terjual) as total_terjual, SUM(i.total_insentif) as total_insentif 
                      FROM tiptok_items i 
                      JOIN tiptok_penitipan p ON i.id_penitipan = p.id 
                      WHERE 1=1 $filterSales");
$dataUnit = $qUnit ? $qUnit->fetch_assoc() : [];
$totalUnitTitip = intval($dataUnit['total_titip'] ?? 0);
$totalUnitSisa = intval($dataUnit['total_sisa'] ?? 0);
$totalUnitTerjual = intval($dataUnit['total_terjual'] ?? 0);
$totalInsentifPool = floatval($dataUnit['total_insentif'] ?? 0);

// 3. Total Unit Terjual yang Belum Diklaim (Unclaimed Eligible)
$filterSalesKunjungan = ($role === 'Sales') ? " AND k.id_sales = '$idSesi' " : "";
$qUnclaimed = $conn->query("SELECT SUM(k.qty_terjual_kunjungan) as total_unclaimed, SUM(k.insentif_didapat) as nominal_unclaimed 
                           FROM tiptok_kunjungan k 
                           WHERE k.id_claim IS NULL AND k.qty_terjual_kunjungan > 0 $filterSalesKunjungan");
$dataUnclaimed = $qUnclaimed ? $qUnclaimed->fetch_assoc() : [];
$unclaimedUnits = intval($dataUnclaimed['total_unclaimed'] ?? 0);
$unclaimedNominal = floatval($dataUnclaimed['nominal_unclaimed'] ?? 0);

$claimTarget = 50;
$claimProgress = min(100, round(($unclaimedUnits / $claimTarget) * 100, 1));
$isClaimEligible = ($unclaimedUnits >= $claimTarget);
$sisaTarget = max(0, $claimTarget - $unclaimedUnits);

// Query Data Master Penitipan (Dukungan database ganda: sales_customer & customers)
$hasSalesCustomer = false;
$chkSC = $conn->query("SHOW TABLES LIKE 'sales_customer'");
if ($chkSC && $chkSC->num_rows > 0) {
    $hasSalesCustomer = true;
}

if ($hasSalesCustomer) {
    $custJoin = "LEFT JOIN sales_customer c ON p.id_customer = c.id";
    $custSelect = "c.nama AS nama_toko, c.kategori AS kategori_customer, c.telp_pribadi AS telp_toko, c.alamat AS alamat_toko, c.kota AS kota_toko, c.alamat_lokasi";
} else {
    $custJoin = "LEFT JOIN customers c ON p.id_customer = c.id";
    $custSelect = "c.nama_toko AS nama_toko, c.kategori AS kategori_customer, 
                  (SELECT tlp_pic FROM customer_pics WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS telp_toko, 
                  (SELECT alamat FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat_toko, 
                  (SELECT kota FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS kota_toko, 
                  (SELECT link_google_map FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat_lokasi";
}

$sqlPenitipan = "SELECT p.*, $custSelect,
                        COUNT(i.id) AS total_jenis_barang,
                        SUM(i.qty_titip) AS sum_titip,
                        SUM(i.qty_sisa) AS sum_sisa,
                        SUM(i.qty_terjual) AS sum_terjual,
                        SUM(i.total_insentif) AS sum_insentif,
                        (SELECT k.no_inv FROM tiptok_kunjungan k WHERE k.id_penitipan = p.id AND k.no_inv IS NOT NULL AND k.no_inv != '' ORDER BY k.tgl_kunjungan DESC, k.id DESC LIMIT 1) AS last_no_inv,
                        (SELECT k.tgl_kunjungan FROM tiptok_kunjungan k WHERE k.id_penitipan = p.id ORDER BY k.tgl_kunjungan DESC, k.id DESC LIMIT 1) AS last_kunjungan
                 FROM tiptok_penitipan p 
                 $custJoin 
                 LEFT JOIN tiptok_items i ON p.id = i.id_penitipan 
                 WHERE 1=1 $filterSales 
                 GROUP BY p.id 
                 ORDER BY p.id DESC";
$resPenitipan = $conn->query($sqlPenitipan);

// =========================================================================
// 6 PRODUK RESMI PROGRAM TIP TOK (KONSINYASI LOEWIX)
// =========================================================================
$tiptokMaster6 = [
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

// Helper to query connection
$fetchFromDbConn = function($db) {
    $list = [];
    if (!$db || $db->connect_error) return $list;
    $chk = @$db->query("SHOW TABLES LIKE 'product_prices'");
    if ($chk && $chk->num_rows > 0) {
        $res = @$db->query("SELECT id, category, type, description, msrp FROM product_prices ORDER BY category ASC, type ASC");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $list[] = [
                    'id' => (int)$row['id'],
                    'category' => $row['category'] ?? '',
                    'type' => $row['type'] ?? '',
                    'description' => $row['description'] ?? '',
                    'msrp' => (float)($row['msrp'] ?? 0)
                ];
            }
        }
    }
    return $list;
};

// Cek harga terkini di database jika ada untuk sinkronisasi harga
$dbPrices = $fetchFromDbConn($conn);
if (!empty($dbPrices)) {
    foreach ($tiptokMaster6 as &$p) {
        $cleanModel = str_replace('-', '', $p['model']);
        foreach ($dbPrices as $dbP) {
            $dbTypeClean = str_replace('-', '', $dbP['type']);
            if (stripos($dbTypeClean, $cleanModel) !== false || stripos($dbP['type'], $p['model']) !== false) {
                if ($dbP['msrp'] > 0) $p['msrp'] = (float)$dbP['msrp'];
                if (!empty($dbP['description'])) $p['description'] = $dbP['description'];
                break;
            }
        }
    }
    unset($p);
}

// Khusus TIP TOK: Hanya 6 Produk Resmi Ini yang Ditampilkan
$loewixPriceList = $tiptokMaster6;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>TIP TOK | Konsinyasi Toko & Insentif</title>
    <?php include "head.php"; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* ═════════════════════════════════════════════════════════
           VIBRANT, HIGH-CONTRAST & SENIOR-FRIENDLY DESIGN SYSTEM
           ═════════════════════════════════════════════════════════ */
        :root {
            --bg-canvas: #f1f5f9;
            --surface-card: #ffffff;
            --border-subtle: #cbd5e1;
            --border-hover: #94a3b8;
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --accent-dark: #0f172a;
            --accent-blue: #2563eb;
            --accent-blue-light: #eff6ff;
            --accent-emerald: #059669;
            --accent-emerald-light: #ecfdf5;
            --accent-amber: #d97706;
            --accent-amber-light: #fffbeb;
            --accent-purple: #7c3aed;
            --accent-purple-light: #faf5ff;
            --accent-rose: #dc2626;
            --accent-rose-light: #fee2e2;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: var(--text-primary);
            letter-spacing: -0.01em;
            min-height: 100vh;
        }

        /* ── HERO BANNER HEADER ── */
        .hero-banner-tiptok {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1e293b 100%);
            border-radius: 20px;
            padding: 26px 30px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.25);
            border: 2px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 24px;
        }
        .hero-banner-tiptok::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.18) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .hero-tag {
            font-size: 12.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: rgba(59, 130, 246, 0.25);
            color: #93c5fd;
            border: 1px solid rgba(147, 197, 253, 0.4);
            padding: 5px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }
        .hero-title {
            font-family: 'Outfit', sans-serif;
            font-size: 30px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: -0.02em;
            margin: 0;
            line-height: 1.2;
        }
        .hero-title-highlight {
            background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 14.5px;
            font-weight: 500;
            color: #cbd5e1;
            margin: 6px 0 0 0;
            max-width: 680px;
            line-height: 1.5;
        }
        .btn-hero-claim {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #ffffff;
            border: 2px solid #fbbf24;
            border-radius: 12px;
            padding: 11px 22px;
            font-size: 14.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(217, 119, 6, 0.35);
            text-decoration: none;
            cursor: pointer;
        }
        .btn-hero-claim:hover {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(217, 119, 6, 0.45);
        }
        .btn-hero-add {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: 2px solid #34d399;
            border-radius: 12px;
            padding: 11px 24px;
            font-size: 14.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.35);
            text-decoration: none;
            cursor: pointer;
        }
        .btn-hero-add:hover {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
        }
        .btn-hero-invoice {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            border: 2px solid #38bdf8;
            border-radius: 12px;
            padding: 11px 22px;
            font-size: 14.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(2, 132, 199, 0.35);
            text-decoration: none;
            cursor: pointer;
        }
        .btn-hero-invoice:hover {
            background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.45);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        @media (max-width: 1100px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .metrics-grid { grid-template-columns: 1fr; } }

        .metric-card-themed {
            border-radius: 18px;
            padding: 22px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.06);
        }
        .metric-card-themed:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -4px rgba(15, 23, 42, 0.12);
        }
        
        /* Themed Variations */
        .metric-theme-blue {
            background: linear-gradient(145deg, #ffffff 0%, #eff6ff 100%);
            border: 2px solid #bfdbfe;
            border-top: 6px solid #2563eb;
        }
        .metric-theme-emerald {
            background: linear-gradient(145deg, #ffffff 0%, #ecfdf5 100%);
            border: 2px solid #a7f3d0;
            border-top: 6px solid #059669;
        }
        .metric-theme-amber {
            background: linear-gradient(145deg, #ffffff 0%, #fffbeb 100%);
            border: 2px solid #fde68a;
            border-top: 6px solid #d97706;
        }
        .metric-theme-purple {
            background: linear-gradient(145deg, #ffffff 0%, #faf5ff 100%);
            border: 2px solid #e9d5ff;
            border-top: 6px solid #7c3aed;
        }

        .metric-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .icon-blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .icon-emerald { background: linear-gradient(135deg, #10b981, #047857); color: #fff; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        .icon-amber { background: linear-gradient(135deg, #f59e0b, #b45309); color: #fff; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
        .icon-purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3); }

        .metric-label-txt {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #475569;
        }
        .metric-val-large {
            font-family: 'Outfit', sans-serif;
            font-size: 34px;
            font-weight: 900;
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin: 8px 0;
        }

        /* ── Segmented Nav & Search ── */
        .segmented-control-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 20px;
        }
        .segmented-nav-vibrant {
            background: #ffffff;
            padding: 6px;
            border-radius: 14px;
            display: inline-flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            border: 2px solid #cbd5e1;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
        }
        .segment-btn-vibrant {
            border: 1.5px solid transparent;
            background: #ffffff;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 800;
            color: #475569;
            cursor: pointer;
            transition: all 0.18s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .segment-btn-vibrant:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #cbd5e1;
        }
        .segment-btn-vibrant.active {
            background: #0f172a !important;
            color: #ffffff !important;
            border-color: #0f172a !important;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.25) !important;
        }
        .segment-btn-vibrant.active .segment-badge-vibrant {
            background: rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }
        .segment-badge-vibrant {
            font-size: 12px;
            font-weight: 800;
            background: #e2e8f0;
            color: #0f172a;
            padding: 2px 8px;
            border-radius: 6px;
            transition: all 0.18s ease;
        }
        .btn-tab-claim-vibrant {
            background: #fffbeb;
            color: #92400e;
            border: 1.5px solid #fde68a;
        }
        .btn-tab-claim-vibrant:hover {
            background: #fef3c7;
            color: #78350f;
            border-color: #f59e0b;
        }
        .btn-tab-claim-vibrant.active {
            background: #0f172a !important;
            color: #fbbf24 !important;
            border-color: #0f172a !important;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.25) !important;
        }
        .btn-tab-claim-vibrant.active i {
            color: #fbbf24 !important;
        }

        .search-container-vibrant {
            position: relative;
            min-width: 320px;
            flex: 1;
            max-width: 420px;
        }
        .search-icon-vibrant {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: #3b82f6;
            pointer-events: none;
        }
        .search-input-vibrant {
            width: 100%;
            height: 48px;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            border-radius: 14px;
            padding: 8px 16px 8px 44px;
            font-size: 14.5px;
            font-weight: 600;
            color: #0f172a;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }
        .search-input-vibrant:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18);
        }

        /* ── Data Surface Table (Colorful Overhaul) ── */
        .data-card-vibrant {
            background: #ffffff;
            border: 2px solid #cbd5e1;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
            margin-bottom: 24px;
        }
        .table-vibrant {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }
        .table-vibrant th {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            font-size: 12.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 16px 18px;
            border-bottom: 3px solid #3b82f6;
            white-space: nowrap;
        }
        .table-vibrant td {
            padding: 16px 18px;
            vertical-align: middle;
            border-bottom: 1.5px solid #e2e8f0;
            font-size: 14.5px;
            color: #0f172a;
            background-color: #ffffff;
            transition: background-color 0.15s ease;
        }
        .table-vibrant tr:nth-child(even) td {
            background-color: #fbfcfe;
        }
        .table-vibrant tr:hover td {
            background-color: #eff6ff !important;
        }
        .table-vibrant tr:last-child td {
            border-bottom: none;
        }

        /* Badges & Micro Chips */
        .taste-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 800;
            border: 1.5px solid transparent;
            line-height: 1.2;
        }
        .badge-neutral { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
        .badge-dealer-tag { background: #dbeafe; color: #1e40af; border-color: #93c5fd; font-weight: 800; }
        .badge-active-tag { background: #dcfce7; color: #15803d; border-color: #86efac; font-weight: 800; }
        .badge-invoice-tag { background: #fef3c7; color: #92400e; border-color: #fcd34d; font-family: monospace; font-size: 13px; font-weight: 800; }
        .badge-danger-tag { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; font-weight: 800; }
        .badge-sales-tag { background: #f3e8ff; color: #6b21a8; border-color: #d8b4fe; font-weight: 800; }

        /* Item Row Pill */
        .taste-item-pill {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 4px 0;
            box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04);
            transition: all 0.15s ease;
        }
        .taste-item-pill:hover {
            border-color: #94a3b8;
            box-shadow: 0 4px 8px rgba(15, 23, 42, 0.08);
        }

        /* Modern Vibrant Table Action Buttons */
        .btn-table-primary {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: #ffffff !important;
            border: none;
            border-radius: 9px;
            height: 33px;
            padding: 0 13px;
            font-size: 12.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.32);
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            letter-spacing: 0.01em;
        }
        .btn-table-primary:hover {
            background: linear-gradient(135deg, #047857 0%, #059669 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.45);
            color: #ffffff !important;
        }
        .btn-table-primary:active {
            transform: translateY(0);
        }

        .btn-table-secondary {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: #ffffff !important;
            border: none;
            border-radius: 9px;
            width: 33px;
            height: 33px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.25);
            cursor: pointer;
            text-decoration: none;
        }
        .btn-table-secondary:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.4);
            color: #ffffff !important;
        }

        .btn-table-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #ffffff !important;
            border: none;
            border-radius: 9px;
            width: 33px;
            height: 33px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 8px rgba(245, 158, 11, 0.25);
            cursor: pointer;
            text-decoration: none;
        }
        .btn-table-warning:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(245, 158, 11, 0.4);
            color: #ffffff !important;
        }

        .btn-table-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff !important;
            border: none;
            border-radius: 9px;
            width: 33px;
            height: 33px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 8px rgba(239, 68, 68, 0.25);
            cursor: pointer;
            text-decoration: none;
        }
        .btn-table-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(239, 68, 68, 0.4);
            color: #ffffff !important;
        }

        /* Modal Styles & Smooth Scrolling Architecture */
        .modal-taste .modal-dialog {
            max-height: calc(100vh - 40px);
            margin: 20px auto;
        }
        .modal-taste .modal-content {
            border-radius: 20px;
            border: 2px solid var(--border-subtle);
            box-shadow: 0 24px 50px -12px rgba(15, 23, 42, 0.35);
            max-height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: #ffffff;
        }
        .modal-taste .modal-content > form,
        .modal-taste form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            max-height: 100%;
            overflow: hidden;
            margin-bottom: 0;
        }
        .modal-taste .modal-header {
            flex-shrink: 0;
            padding: 18px 24px;
            border-bottom: 2px solid #e2e8f0;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            z-index: 5;
        }
        .modal-taste .modal-title {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 900;
            color: #020617;
        }
        .modal-taste .modal-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding: 22px 24px;
        }
        .modal-taste .modal-footer {
            flex-shrink: 0;
            padding: 14px 24px;
            border-top: 2px solid #e2e8f0;
            background-color: #f8fafc;
            z-index: 5;
        }

        /* Sleek Modern Scrollbar for Modals */
        .modal-taste .modal-body::-webkit-scrollbar {
            width: 8px;
        }
        .modal-taste .modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 8px;
        }
        .modal-taste .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 8px;
        }
        .modal-taste .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Item Row Cards for Tambah & Edit */
        /* Premium TIP TOK Item Cards & Controls */
        .item-card-row {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 14px;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -2px rgba(0, 0, 0, 0.03);
            position: relative;
        }
        .item-card-row:hover {
            border-color: #93c5fd;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.08), 0 8px 10px -6px rgba(37, 99, 235, 0.04);
        }
        .product-select-premium {
            font-size: 13.5px;
            font-weight: 700;
            border-radius: 12px;
            border: 2px solid #cbd5e1;
            padding: 10px 14px;
            color: #0f172a;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }
        .product-select-premium:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
            outline: none;
        }
        .product-meta-card {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .incentive-badge-glow {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .summary-bar-tiptok {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.2);
        }

        /* Dedicated Rock-Solid Controls for TIP TOK Row */
        .taste-qty-stepper {
            display: flex !important;
            flex-direction: row !important;
            align-items: stretch !important;
            width: 100% !important;
            height: 44px !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 12px !important;
            background: #ffffff !important;
            overflow: hidden !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03) !important;
            transition: all 0.2s ease !important;
        }
        .taste-qty-stepper:focus-within {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
        }
        .taste-qty-stepper .stepper-btn {
            width: 44px !important;
            min-width: 44px !important;
            height: 100% !important;
            border: none !important;
            background: #f1f5f9 !important;
            color: #334155 !important;
            font-size: 18px !important;
            font-weight: 900 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            user-select: none !important;
            padding: 0 !important;
            margin: 0 !important;
            transition: background 0.15s, color 0.15s !important;
        }
        .taste-qty-stepper .stepper-btn:hover {
            background: #e2e8f0 !important;
            color: #0f172a !important;
        }
        .taste-qty-stepper .stepper-btn:active {
            background: #cbd5e1 !important;
        }
        .taste-qty-stepper input.stepper-input {
            flex: 1 1 auto !important;
            width: 100% !important;
            min-width: 0 !important;
            height: 100% !important;
            border: none !important;
            text-align: center !important;
            font-size: 16px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            background: #ffffff !important;
            padding: 0 !important;
            margin: 0 !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .taste-addon-input {
            display: flex !important;
            flex-direction: row !important;
            align-items: stretch !important;
            width: 100% !important;
            height: 44px !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 12px !important;
            background: #f8fafc !important;
            overflow: hidden !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03) !important;
        }
        .taste-addon-input .addon-label {
            padding: 0 14px !important;
            height: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: #e2e8f0 !important;
            color: #475569 !important;
            font-size: 13px !important;
            font-weight: 900 !important;
            border-right: 1.5px solid #cbd5e1 !important;
            user-select: none !important;
        }
        .taste-addon-input input.addon-input-field {
            flex: 1 1 auto !important;
            width: 100% !important;
            min-width: 0 !important;
            height: 100% !important;
            border: none !important;
            text-align: right !important;
            font-size: 15px !important;
            font-weight: 800 !important;
            color: #059669 !important;
            background: transparent !important;
            padding: 0 14px !important;
            margin: 0 !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .taste-subtotal-card {
            height: 44px !important;
            border-radius: 12px !important;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.12) 100%) !important;
            border: 1.5px solid rgba(16, 185, 129, 0.35) !important;
            padding: 0 14px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }

        /* Detail Modal Stat Cards */
        .detail-stat-card {
            border-radius: 14px;
            padding: 14px 16px;
            border: 2px solid transparent;
            transition: all 0.2s ease;
        }
        .detail-stat-card.stat-blue {
            background-color: var(--accent-blue-light);
            border-color: #bfdbfe;
        }
        .detail-stat-card.stat-emerald {
            background-color: var(--accent-emerald-light);
            border-color: #a7f3d0;
        }
        .detail-stat-card.stat-amber {
            background-color: var(--accent-amber-light);
            border-color: #fde68a;
        }
        .detail-stat-card.stat-purple {
            background-color: var(--accent-purple-light);
            border-color: #e9d5ff;
        }

        .form-label-taste {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #1e293b;
            margin-bottom: 6px;
            display: block;
        }
        .form-control-taste {
            border: 2px solid #cbd5e1;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            transition: border-color 0.15s, box-shadow 0.15s;
            background-color: #ffffff;
        }
        .form-control-taste:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
            outline: none;
        }
        .btn-taste-primary {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
            text-decoration: none;
            cursor: pointer;
        }
        .btn-taste-primary:hover {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #ffffff;
        }
        .btn-taste-secondary {
            background-color: #ffffff;
            color: var(--text-primary);
            border: 2px solid var(--border-subtle);
            border-radius: 10px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            text-decoration: none;
            cursor: pointer;
        }
        .btn-taste-secondary:hover {
            background-color: #f1f5f9;
            color: var(--text-primary);
        }
        .btn-taste-emerald {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            border: 2px solid #047857;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 13.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.15s ease;
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.2);
            text-decoration: none;
            cursor: pointer;
        }
        .btn-taste-emerald:hover {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            color: #ffffff;
        }
        .btn-taste-amber {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            color: #ffffff;
            border: 2px solid #b45309;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 13.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.15s ease;
            box-shadow: 0 3px 10px rgba(217, 119, 6, 0.2);
            text-decoration: none;
            cursor: pointer;
        }
        .btn-taste-amber:hover {
            background: linear-gradient(135deg, #b45309 0%, #92400e 100%);
            color: #ffffff;
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-200">

    <?php include "cek-menu.php"; ?>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <?php include "nav-top.php"; ?>

        <div class="container-fluid py-4 px-4">

            <!-- 1. VIBRANT HERO BANNER HEADER -->
            <div class="hero-banner-tiptok">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="hero-tag">
                            <i class="fa-solid fa-sparkles text-warning me-1"></i> APLIKASI SALES & KONSINYASI
                        </div>
                        <h1 class="hero-title">
                            TIP TOK <span class="hero-title-highlight">(Titip Barang Di Toko)</span>
                        </h1>
                        <p class="hero-desc">
                            Manajemen penitipan stok toko dealer mitra, pemantauan sisa fisik kunjungan, dan klaim reward insentif min. 50 unit.
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2.5 flex-wrap">
                        <a href="tiptok-invoice.php" class="btn-hero-invoice">
                            <i class="fa-solid fa-receipt" style="font-size: 17px;"></i>
                            <span>No. Invoice TIP TOK</span>
                        </a>
                        <button class="btn-hero-claim" onclick="openTabKlaimInsentif()">
                            <i class="fa-solid fa-hand-holding-dollar text-warning-light" style="font-size: 18px;"></i>
                            <span>Klaim Insentif</span>
                        </button>
                        <button class="btn-hero-add" onclick="openModalTambahPenitipan()">
                            <i class="fa-solid fa-circle-plus" style="font-size: 18px;"></i>
                            <span>Titip Barang Baru</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. HIGH-CONTRAST COLORFUL BENTO METRICS GRID -->
            <div class="metrics-grid">
                <!-- Metric 1: Toko Aktif (Ocean Blue Theme) -->
                <div class="metric-card-themed metric-theme-blue">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="metric-label-txt">Toko Dealer Aktif</span>
                        <div class="metric-icon-box icon-blue">
                            <i class="fa-solid fa-store"></i>
                        </div>
                    </div>
                    <div class="metric-val-large" style="color: #1d4ed8;">
                        <?php echo number_format($totalTokoAktif, 0, ',', '.'); ?>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="taste-badge badge-active-tag" style="font-size:12px; padding:3px 10px;">
                            <i class="fa-solid fa-circle text-xxs me-1"></i> Aktif
                        </span>
                        <span style="font-weight: 700; color: #334155; font-size: 13.5px;">dengan stok titipan</span>
                    </div>
                </div>

                <!-- Metric 2: Sisa Stok di Toko (Emerald Mint Theme) -->
                <div class="metric-card-themed metric-theme-emerald">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="metric-label-txt">Sisa Stok di Toko</span>
                        <div class="metric-icon-box icon-emerald">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                    <div class="metric-val-large" style="color: #047857;">
                        <?php echo number_format($totalUnitSisa, 0, ',', '.'); ?> 
                        <span style="font-size: 16px; font-weight: 800; color: #64748b;">/ <?php echo number_format($totalUnitTitip, 0, ',', '.'); ?> unit</span>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="font-size: 13.5px; font-weight: 700; color: #334155;">
                        <span>Terjual:</span>
                        <span class="taste-badge badge-danger-tag" style="font-size: 13px; padding: 2px 10px;">
                            <?php echo number_format($totalUnitTerjual, 0, ',', '.'); ?> Unit
                        </span>
                    </div>
                </div>

                <!-- Metric 3: Akumulasi Insentif (Golden Amber Theme) -->
                <div class="metric-card-themed metric-theme-amber">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="metric-label-txt">Akumulasi Insentif</span>
                        <div class="metric-icon-box icon-amber">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                    </div>
                    <div class="metric-val-large" style="font-size: 30px; color: #b45309;">
                        Rp <?php echo number_format($totalInsentifPool, 0, ',', '.'); ?>
                    </div>
                    <div style="font-size: 13px; font-weight: 700; color: #64748b;">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> Dari total unit yang telah terjual
                    </div>
                </div>

                <!-- Metric 4: Target Klaim 50 Unit (Royal Purple Theme) -->
                <div class="metric-card-themed metric-theme-purple">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="metric-label-txt">Target Klaim (Min. 50 Unit)</span>
                        <span class="taste-badge <?php echo $isClaimEligible ? 'badge-active-tag' : 'badge-neutral'; ?>" style="font-size: 12px; padding: 3px 8px;">
                            <?php echo $isClaimEligible ? 'SIAP KLAIM' : 'PROSES'; ?>
                        </span>
                    </div>
                    <div class="metric-val-large" style="color: #6d28d9;">
                        <?php echo $unclaimedUnits; ?> 
                        <span style="font-size: 16px; font-weight: 800; color: #64748b;">/ 50 unit</span>
                    </div>
                    <div class="progress mt-1 mb-1" style="height: 10px; background-color: #e2e8f0; border-radius: 20px; overflow: hidden;">
                        <div class="progress-bar" style="width: <?php echo $claimProgress; ?>%; background: <?php echo $isClaimEligible ? '#059669' : 'linear-gradient(90deg, #7c3aed, #ec4899)'; ?>; border-radius: 20px;"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size: 12.5px; font-weight: 800;">
                        <span style="color: #64748b;"><?php echo $claimProgress; ?>% tercapai</span>
                        <span style="color: <?php echo $isClaimEligible ? '#059669' : '#dc2626'; ?>;"><?php echo $isClaimEligible ? 'Target tercapai!' : "Kurang $sisaTarget unit"; ?></span>
                    </div>
                </div>
            </div>

            <!-- 3. SEGMENTED CONTROLS & SEARCH BAR -->
            <div class="segmented-control-container">
                <div class="segmented-nav-vibrant">
                    <button id="btnFilterAll" class="segment-btn-vibrant active" onclick="filterTable('all', this)">
                        <i class="fa-solid fa-list-ul me-1"></i> Semua <span class="segment-badge-vibrant" id="badgeCountAll">0</span>
                    </button>
                    <button id="btnFilterAktif" class="segment-btn-vibrant" onclick="filterTable('aktif', this)">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> Stok Aktif <span class="segment-badge-vibrant" id="badgeCountAktif">0</span>
                    </button>
                    <button id="btnFilterTerjual" class="segment-btn-vibrant" onclick="filterTable('terjual', this)">
                        <i class="fa-solid fa-fire text-warning me-1"></i> Ada Penjualan <span class="segment-badge-vibrant" id="badgeCountTerjual">0</span>
                    </button>
                    <button id="btnFilterSelesai" class="segment-btn-vibrant" onclick="filterTable('selesai', this)">
                        <i class="fa-solid fa-flag-checkered text-secondary me-1"></i> Selesai <span class="segment-badge-vibrant" id="badgeCountSelesai">0</span>
                    </button>
                    <button id="btnTabKlaimInsentif" class="segment-btn-vibrant btn-tab-claim-vibrant" onclick="switchViewToClaims(this)">
                        <i class="fa-solid fa-receipt text-warning"></i> Tab Klaim Insentif
                    </button>
                </div>

                <div class="search-container-vibrant">
                    <i class="fa-solid fa-magnifying-glass search-icon-vibrant"></i>
                    <input type="text" id="tiptokSearchInput" class="search-input-vibrant" placeholder="Cari toko, barang, invoice..." onkeyup="searchTiptokTable()">
                </div>
            </div>

            <!-- 4. MAIN DATA TABLE (VIBRANT & HIGH CONTRAST) -->
            <div id="viewPenitipanTable" class="data-card-vibrant">
                <div class="table-responsive">
                    <table class="table table-vibrant" id="mainTiptokTable">
                        <thead>
                            <tr>
                                <th style="width: 4%; text-align: center;">#</th>
                                <th style="width: 26%;">TOKO / DEALER</th>
                                <th style="width: 14%;">KODE & TGL</th>
                                <th style="width: 24%;">BARANG & MONITORING STOK</th>
                                <th style="width: 14%;">INVOICE & INSENTIF</th>
                                <th style="width: 8%;">STATUS</th>
                                <th style="width: 10%; text-align: right;">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $countAll = 0;
                            $countAktif = 0;
                            $countTerjual = 0;
                            $countSelesai = 0;

                            if ($resPenitipan && $resPenitipan->num_rows > 0) {
                                while ($row = $resPenitipan->fetch_assoc()) {
                                    $countAll++;
                                    $idPen = $row['id'];
                                    $statusPen = $row['status'];
                                    $sumSisa = intval($row['sum_sisa']);
                                    $sumTerjual = intval($row['sum_terjual']);
                                    $sumTitip = intval($row['sum_titip']);
                                    $sumInsentif = floatval($row['sum_insentif']);

                                    if ($statusPen === 'aktif' && $sumSisa > 0) $countAktif++;
                                    if ($sumTerjual > 0) $countTerjual++;
                                    if ($statusPen === 'selesai' || $statusPen === 'ditarik' || $sumSisa === 0) $countSelesai++;

                                    $qItems = $conn->query("SELECT * FROM tiptok_items WHERE id_penitipan = $idPen ORDER BY id ASC");
                                    $itemList = [];
                                    while ($it = $qItems->fetch_assoc()) {
                                        $itemList[] = $it;
                                    }

                                    $filterCat = 'all';
                                    if ($statusPen === 'aktif' && $sumSisa > 0) $filterCat .= ' aktif';
                                    if ($sumTerjual > 0) $filterCat .= ' terjual';
                                    if ($statusPen === 'selesai' || $statusPen === 'ditarik' || $sumSisa === 0) $filterCat .= ' selesai';

                                    $telpRaw = preg_replace('/\D/', '', $row['telp_toko'] ?? '');
                                    if (substr($telpRaw, 0, 1) === '0') $telpRaw = '62' . substr($telpRaw, 1);
                                    ?>
                                    <tr class="tiptok-row" data-category="<?php echo $filterCat; ?>">
                                        <td class="text-center font-weight-bold" style="font-size: 14px; color: #475569;">
                                            <span style="background: #e2e8f0; color: #0f172a; padding: 4px 8px; border-radius: 6px; font-weight: 800;"><?php echo $no++; ?></span>
                                        </td>
                                        
                                        <!-- Toko -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="font-weight-bold" style="font-size: 16px; color: #0f172a; line-height: 1.3;"><?php echo htmlspecialchars($row['nama_toko'] ?? 'Toko Tidak Ditemukan'); ?></span>
                                                <span class="taste-badge badge-dealer-tag"><?php echo htmlspecialchars($row['kategori_customer'] ?? 'Dealer'); ?></span>
                                            </div>
                                            <div style="font-size: 13.5px; font-weight: 600; color: #334155; margin-top: 4px; line-height: 1.4;">
                                                <?php echo htmlspecialchars($row['alamat_toko'] ?? '-'); ?><?php echo !empty($row['kota_toko']) ? ', ' . htmlspecialchars($row['kota_toko']) : ''; ?>
                                            </div>
                                            <?php if (!empty($telpRaw)) : ?>
                                                <a href="https://wa.me/<?php echo $telpRaw; ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; background: #dcfce7; color: #15803d; border: 1.5px solid #86efac; padding: 4px 10px; border-radius: 8px; font-size: 13px; font-weight: 800; text-decoration: none; margin-top: 6px;">
                                                    <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($row['telp_toko']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Kode & Tanggal -->
                                        <td>
                                            <div class="font-monospace" style="font-size: 13.5px; font-weight: 800; color: #0f172a; background: #f1f5f9; padding: 4px 10px; border-radius: 6px; border: 1.5px solid #cbd5e1; border-left: 4px solid #2563eb; display: inline-block;">
                                                <?php echo htmlspecialchars($row['kode_titip']); ?>
                                            </div>
                                            <div style="font-size: 13.5px; font-weight: 700; color: #334155; margin-top: 5px;">
                                                <i class="fa-regular fa-calendar me-1 text-primary"></i><?php echo date('d M Y', strtotime($row['tgl_titip'])); ?>
                                            </div>
                                            <div style="margin-top: 3px;">
                                                <span class="taste-badge badge-sales-tag" style="font-size: 12px; padding: 2px 8px;">
                                                    <i class="fa-solid fa-user-tie me-1"></i><?php echo htmlspecialchars($row['nama_sales'] ?? 'Sales'); ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Barang & Stok -->
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($itemList as $it) : 
                                                    $sisa = intval($it['qty_sisa']);
                                                    $terjual = intval($it['qty_terjual']);
                                                    $insPerUnit = floatval($it['insentif_per_unit']);
                                                ?>
                                                    <div class="taste-item-pill">
                                                        <span class="font-weight-bold" style="font-size: 14px; color: #0f172a;"><?php echo htmlspecialchars($it['nama_barang']); ?></span>
                                                        <div class="d-flex align-items-center gap-2 ms-auto">
                                                            <span style="font-size: 13px; font-weight: 700; color: #475569;">
                                                                Sisa: <strong style="font-size: 13px; font-weight: 900; background: #10b981; color: #ffffff; padding: 2px 8px; border-radius: 6px; box-shadow: 0 2px 5px rgba(16,185,129,0.3);"><?php echo $sisa; ?></strong>
                                                            </span>
                                                            <?php if ($terjual > 0) : ?>
                                                                <span style="font-size: 13px; font-weight: 900; background: #ef4444; color: #ffffff; padding: 2px 8px; border-radius: 6px; box-shadow: 0 2px 5px rgba(239,68,68,0.3);">
                                                                    Laku: <?php echo $terjual; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>

                                        <!-- Invoice & Insentif -->
                                        <td>
                                            <?php if (!empty($row['last_no_inv'])) : ?>
                                                <div class="taste-badge badge-invoice-tag mb-1">
                                                    <i class="fa-solid fa-receipt me-1"></i><?php echo htmlspecialchars($row['last_no_inv']); ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size: 13px; font-weight: 600; color: #64748b; font-style: italic; margin-bottom: 4px;">Belum ada invoice</div>
                                            <?php endif; ?>
                                            <div style="font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 900; color: #047857; line-height: 1.2;">
                                                Rp <?php echo number_format($sumInsentif, 0, ',', '.'); ?>
                                            </div>
                                            <div style="font-size: 13px; font-weight: 700; color: #475569; margin-top: 2px;">
                                                (Terjual: <strong style="color: #0f172a;"><?php echo $sumTerjual; ?> unit</strong>)
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            <?php if ($statusPen === 'aktif' && $sumSisa > 0) : ?>
                                                <span class="taste-badge badge-active-tag"><i class="fa-solid fa-circle-check me-1"></i> Aktif</span>
                                            <?php elseif ($statusPen === 'selesai' || $sumSisa === 0) : ?>
                                                <span class="taste-badge badge-neutral"><i class="fa-solid fa-circle-minus me-1"></i> Selesai</span>
                                            <?php else : ?>
                                                <span class="taste-badge badge-danger-tag"><i class="fa-solid fa-ban me-1"></i> Ditarik</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Aksi -->
                                        <td style="text-align: right; white-space: nowrap;">
                                            <div class="d-inline-flex gap-1.5 align-items-center justify-content-end">
                                                <?php if ($statusPen === 'aktif' && $sumSisa > 0) : ?>
                                                    <button type="button" class="btn-table-primary" onclick="openModalLaporKunjungan(<?php echo $idPen; ?>)" title="Lapor Kunjungan &amp; Cek Sisa Fisik">
                                                        <i class="fa-solid fa-clipboard-check"></i> Cek Sisa
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn-table-secondary" onclick="openModalDetailTiptok(<?php echo $idPen; ?>)" title="Lihat Riwayat Lengkap">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn-table-warning" onclick="openModalEditPenitipan(<?php echo $idPen; ?>)" title="Edit Data Penitipan">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button type="button" class="btn-table-danger" 
                                                        data-id="<?php echo $idPen; ?>" 
                                                        data-kode="<?php echo htmlspecialchars($row['kode_titip'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                                        data-toko="<?php echo htmlspecialchars($row['nama_toko'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                                        onclick="hapusPenitipanFromBtn(this)" 
                                                        title="Hapus Penitipan">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="d-inline-flex p-3 rounded-circle bg-light text-muted mb-2">
                                            <i class="fa-solid fa-box-open fa-2x"></i>
                                        </div>
                                        <div class="font-weight-bold text-dark text-base mb-1">Belum Ada Data Penitipan Barang</div>
                                        <p class="text-sm text-muted mb-3">Mulai catat barang konsinyasi pertama yang dititipkan di toko dealer mitra.</p>
                                        <button class="btn-taste-primary" onclick="openModalTambahPenitipan()">
                                            <i class="fa-solid fa-plus"></i> Titip Barang Baru
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 5. View Tab Klaim Insentif -->
            <div id="viewKlaimInsentif" class="data-card-vibrant d-none p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h3 class="font-weight-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Klaim Insentif Penjualan</h3>
                        <p class="text-secondary text-sm mb-0">Akumulasi unit terjual dari seluruh kunjungan toko dealer. Syarat klaim minimal <strong>50 Unit</strong>.</p>
                    </div>
                    <button class="btn-taste-secondary" onclick="switchViewToTable()">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Tabel Penitipan
                    </button>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="metric-card-themed metric-theme-purple h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="metric-label-txt">Unit Terjual Siap Klaim</span>
                                <span class="taste-badge <?php echo $isClaimEligible ? 'badge-active-tag' : 'badge-neutral'; ?>" style="font-size: 12.5px;">
                                    <?php echo $isClaimEligible ? 'SYARAT TERPENUHI (>= 50)' : 'BELUM MEMENUHI (< 50)'; ?>
                                </span>
                            </div>
                            <div class="metric-val-large" style="color: #6d28d9;"><?php echo $unclaimedUnits; ?> <span style="font-size: 16px; font-weight: 700; color: #64748b;">/ 50 unit minimal</span></div>
                            <div class="progress my-2" style="height: 10px; background-color: #e2e8f0; border-radius: 10px;">
                                <div class="progress-bar" style="width: <?php echo $claimProgress; ?>%; background: linear-gradient(90deg, #7c3aed, #ec4899); border-radius: 10px;"></div>
                            </div>
                            <div class="text-sm font-weight-bold mt-2">
                                <?php if ($isClaimEligible) : ?>
                                    <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i>Syarat 50 unit terpenuhi. Anda siap mengajukan klaim insentif.</span>
                                <?php else : ?>
                                    <span class="text-danger"><i class="fa-solid fa-circle-info me-1"></i>Perlu <?php echo $sisaTarget; ?> unit lagi untuk dapat mengajukan klaim insentif.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="metric-card-themed metric-theme-emerald h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="metric-label-txt">Total Nominal Insentif Siap Cair</span>
                                    <div class="metric-icon-box icon-emerald">
                                        <i class="fa-solid fa-wallet"></i>
                                    </div>
                                </div>
                                <div class="metric-val-large" style="font-size: 32px; color: #047857;">
                                    Rp <?php echo number_format($unclaimedNominal, 0, ',', '.'); ?>
                                </div>
                                <p class="text-sm font-weight-bold text-secondary mb-0">Total akumulasi dari unit barang yang terjual dengan No. Invoice valid.</p>
                            </div>
                            <div class="mt-3">
                                <?php if ($isClaimEligible) : ?>
                                    <button class="btn-hero-claim w-100 justify-content-center" onclick="openModalSubmitClaim()">
                                        <i class="fa-solid fa-paper-plane me-1"></i> Ajukan Klaim Insentif Sekarang
                                    </button>
                                <?php else : ?>
                                    <button class="btn-taste-secondary w-100 justify-content-center text-muted" disabled style="opacity: 0.7; cursor: not-allowed;">
                                        <i class="fa-solid fa-lock me-1"></i> Klaim Terkunci (Min. 50 Unit)
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="font-weight-bold text-dark text-sm text-uppercase mb-2" style="letter-spacing: 0.05em;">Rincian Unit Terjual Belum Diklaim</div>
                <div class="table-responsive border rounded-3 mb-4" style="border: 2px solid #cbd5e1 !important; border-radius: 14px; overflow: hidden;">
                    <table class="table table-vibrant mb-0" id="tableUnclaimedItems">
                        <thead>
                            <tr>
                                <th>TGL KUNJUNGAN</th>
                                <th>TOKO / DEALER</th>
                                <th>NAMA BARANG</th>
                                <th>NO. INVOICE</th>
                                <th class="text-center">QTY</th>
                                <th class="text-end">INSENTIF / UNIT</th>
                                <th class="text-end">SUBTOTAL</th>
                            </tr>
                        </thead>
                        <tbody id="bodyUnclaimedItems">
                            <tr><td colspan="7" class="text-center py-3 text-muted">Memuat data rincian unit...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="font-weight-bold text-dark text-sm text-uppercase mb-2" style="letter-spacing: 0.05em;">Riwayat Pengajuan Klaim</div>
                <div class="table-responsive border rounded-3" style="border: 2px solid #cbd5e1 !important; border-radius: 14px; overflow: hidden;">
                    <table class="table table-vibrant mb-0" id="tableClaimHistory">
                        <thead>
                            <tr>
                                <th>KODE KLAIM</th>
                                <th>SALES</th>
                                <th>TGL KLAIM</th>
                                <th class="text-center">TOTAL UNIT</th>
                                <th class="text-end">NOMINAL (RP)</th>
                                <th class="text-center">STATUS</th>
                                <th style="text-align: right;">AKSI</th>
                            </tr>
                        </thead>
                        <tbody id="bodyClaimHistory">
                            <tr><td colspan="7" class="text-center py-3 text-muted">Memuat riwayat klaim...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <?php include "footer.php"; ?>
    </main>

    <!-- ========================================================================= -->
    <!-- MODAL 1: TAMBAH PENITIPAN BARU                                           -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalTambahPenitipan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 18px; border:none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
                <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 18px 24px; border-radius: 18px 18px 0 0;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(37, 99, 235, 0.2); border: 1.5px solid rgba(59, 130, 246, 0.4); display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 20px;">
                            <i class="fa-solid fa-boxes-packing"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="modal-title font-weight-bold text-white mb-0">Titip Barang Baru di Toko</h5>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 11px; padding: 4px 8px; border-radius: 6px;">Program TIP TOK</span>
                            </div>
                            <div class="text-xs mt-0.5" style="color: #94a3b8;">Pilih toko mitra dan input daftar produk resmi konsinyasi Loewix</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formTambahPenitipan" onsubmit="submitTambahPenitipan(event)">
                    <div class="modal-body p-4 bg-light">
                        <!-- Toko & Tanggal Card -->
                        <div class="bg-white p-3.5 rounded-3 mb-3 border" style="border: 1.5px solid #e2e8f0 !important; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label-taste"><i class="fa-solid fa-store text-primary me-1.5"></i> TOKO / DEALER TUJUAN (MITRA TIP TOK) <span class="text-danger">*</span></label>
                                    <select name="id_customer" id="selectDealer" class="form-control-taste w-100" required onchange="onDealerSelected()">
                                        <option value="">-- Cari / Pilih Toko Mitra TIP TOK --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-taste"><i class="fa-regular fa-calendar-days text-primary me-1.5"></i> TANGGAL TITIP <span class="text-danger">*</span></label>
                                    <input type="date" name="tgl_titip" class="form-control-taste w-100 font-weight-bold" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div id="dealerPreview" class="p-3 mt-3 rounded-3 border d-none" style="background: #f8fafc; border: 1.5px dashed #93c5fd !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold text-dark fs-6" id="prevNamaToko">-</span>
                                    <span class="taste-badge badge-dealer-tag" id="prevKategoriToko">Dealer</span>
                                </div>
                                <div class="text-xs text-secondary font-weight-bold mt-1" id="prevAlamatToko">-</div>
                                <div class="text-xs text-success font-weight-bold mt-1" id="prevTelpToko">-</div>
                            </div>
                        </div>

                        <!-- Header Barang Dititipkan -->
                        <div class="d-flex justify-content-between align-items-center mb-3 mt-4 flex-wrap gap-2">
                            <div>
                                <label class="form-label-taste mb-0 text-dark" style="font-size: 13.5px;"><i class="fa-solid fa-video text-primary me-1.5"></i> DAFTAR BARANG DITITIPKAN (6 PRODUK RESMI) <span class="text-danger">*</span></label>
                                <div class="text-xs text-secondary font-weight-bold">Pilih salah satu dari 6 model kamera resmi berinsentif</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold px-3 py-1.5 mb-0" style="border-radius: 10px; font-size: 12px;" onclick="openKatalogPriceListModal('tambah')">
                                    <i class="fa-solid fa-eye me-1.5"></i> Katalog 6 Produk
                                </button>
                                <button type="button" class="btn-taste-primary btn-sm py-1.5 px-3" style="font-size: 12px; border-radius: 10px;" onclick="tambahBarisBarang()">
                                    <i class="fa-solid fa-plus me-1.5"></i> Tambah Barang
                                </button>
                            </div>
                        </div>

                        <div id="containerItemRows"></div>

                        <!-- Grand Total Summary Bar -->
                        <div class="summary-bar-tiptok my-3" id="tambahSummaryBar">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.2); display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 16px;">
                                    <i class="fa-solid fa-calculator"></i>
                                </div>
                                <div>
                                    <div class="text-xs" style="color: #94a3b8; font-weight: 700;">TOTAL TITIP FISIK</div>
                                    <div class="fw-bold text-white fs-6" id="totalQtyTitipPreview">0 Unit (0 Model)</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-xs" style="color: #34d399; font-weight: 800;"><i class="fa-solid fa-coins me-1"></i> ESTIMASI REWARD INSENTIF</div>
                                <div class="fw-bold text-success fs-5" id="totalInsentifTitipPreview" style="color: #34d399 !important;">Rp 0</div>
                            </div>
                        </div>

                        <div class="bg-white p-3.5 rounded-3 border mt-3" style="border: 1.5px solid #e2e8f0 !important;">
                            <label class="form-label-taste"><i class="fa-regular fa-note-sticky text-secondary me-1.5"></i> Catatan Penitipan (Opsional)</label>
                            <textarea name="catatan" class="form-control-taste w-100" rows="2" placeholder="Tuliskan catatan perjanjian penitipan barang toko di sini..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-white border-top">
                        <button type="button" class="btn-taste-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnSimpanPenitipan" class="btn-taste-primary">
                            <i class="fa-solid fa-check me-1"></i> Simpan Penitipan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL EDIT PENITIPAN                                                      -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalEditPenitipan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 18px; border:none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
                <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 18px 24px; border-radius: 18px 18px 0 0;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(37, 99, 235, 0.2); border: 1.5px solid rgba(59, 130, 246, 0.4); display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 20px;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="modal-title font-weight-bold text-white mb-0">Edit Data Penitipan Barang</h5>
                                <span class="badge" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); font-size: 11px; padding: 4px 8px; border-radius: 6px;">Update Data</span>
                            </div>
                            <div class="text-xs mt-0.5" style="color: #94a3b8;" id="editModalSubtitle">Perbarui data toko, tanggal, atau daftar barang titipan</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formEditPenitipan" onsubmit="submitEditPenitipan(event)">
                    <input type="hidden" name="id_penitipan" id="editIdPenitipan">
                    <div class="modal-body p-4 bg-light">
                        <div class="bg-white p-3.5 rounded-3 mb-3 border" style="border: 1.5px solid #e2e8f0 !important; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label-taste"><i class="fa-solid fa-store text-primary me-1.5"></i> TOKO / DEALER TUJUAN <span class="text-danger">*</span></label>
                                    <select name="id_customer" id="editSelectDealer" class="form-control-taste w-100" required onchange="onEditDealerSelected()">
                                        <option value="">-- Pilih Toko Mitra TIP TOK --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-taste"><i class="fa-regular fa-calendar-days text-primary me-1.5"></i> TANGGAL TITIP <span class="text-danger">*</span></label>
                                    <input type="date" name="tgl_titip" id="editTglTitip" class="form-control-taste w-100 font-weight-bold" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label-taste"><i class="fa-solid fa-signal text-primary me-1.5"></i> STATUS <span class="text-danger">*</span></label>
                                    <select name="status" id="editStatusPenitipan" class="form-control-taste w-100 font-weight-bold" required>
                                        <option value="aktif">Aktif</option>
                                        <option value="selesai">Selesai</option>
                                        <option value="ditarik">Ditarik</option>
                                    </select>
                                </div>
                            </div>

                            <div id="editDealerPreview" class="p-3 mt-3 rounded-3 border d-none" style="background: #f8fafc; border: 1.5px dashed #93c5fd !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold text-dark fs-6" id="editPrevNamaToko">-</span>
                                    <span class="taste-badge badge-dealer-tag" id="editPrevKategoriToko">Dealer</span>
                                </div>
                                <div class="text-xs text-secondary font-weight-bold mt-1" id="editPrevAlamatToko">-</div>
                                <div class="text-xs text-success font-weight-bold mt-1" id="editPrevTelpToko">-</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 mt-4 flex-wrap gap-2">
                            <div>
                                <label class="form-label-taste mb-0 text-dark" style="font-size: 13.5px;"><i class="fa-solid fa-video text-primary me-1.5"></i> DAFTAR BARANG DITITIPKAN (6 PRODUK RESMI) <span class="text-danger">*</span></label>
                                <div class="text-xs text-secondary font-weight-bold">Pilih salah satu dari 6 model kamera resmi berinsentif</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold px-3 py-1.5 mb-0" style="border-radius: 10px; font-size: 12px;" onclick="openKatalogPriceListModal('edit')">
                                    <i class="fa-solid fa-eye me-1.5"></i> Katalog 6 Produk
                                </button>
                                <button type="button" class="btn-taste-primary btn-sm py-1.5 px-3" style="font-size: 12px; border-radius: 10px;" onclick="tambahBarisBarangEdit()">
                                    <i class="fa-solid fa-plus me-1.5"></i> Tambah Barang
                                </button>
                            </div>
                        </div>

                        <div id="editContainerItemRows"></div>

                        <!-- Grand Total Summary Bar for Edit -->
                        <div class="summary-bar-tiptok my-3" id="editSummaryBar">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.2); display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 16px;">
                                    <i class="fa-solid fa-calculator"></i>
                                </div>
                                <div>
                                    <div class="text-xs" style="color: #94a3b8; font-weight: 700;">TOTAL TITIP FISIK</div>
                                    <div class="fw-bold text-white fs-6" id="editTotalQtyTitipPreview">0 Unit (0 Model)</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-xs" style="color: #34d399; font-weight: 800;"><i class="fa-solid fa-coins me-1"></i> ESTIMASI REWARD INSENTIF</div>
                                <div class="fw-bold text-success fs-5" id="editTotalInsentifTitipPreview" style="color: #34d399 !important;">Rp 0</div>
                            </div>
                        </div>

                        <div class="bg-white p-3.5 rounded-3 border mt-3" style="border: 1.5px solid #e2e8f0 !important;">
                            <label class="form-label-taste"><i class="fa-regular fa-note-sticky text-secondary me-1.5"></i> Catatan Penitipan (Opsional)</label>
                            <textarea name="catatan" id="editCatatan" class="form-control-taste w-100" rows="2" placeholder="Catatan perjanjian penitipan stok..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-white border-top">
                        <button type="button" class="btn-taste-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnSimpanEditPenitipan" class="btn-taste-primary">
                            <i class="fa-solid fa-check me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL KATALOG 6 PRODUK RESMI TIP TOK LOEWIX (PILIH PRODUK LANGSUNG)      -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalKatalogPriceList" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 20px; border:none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
                <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); color: #fff; padding: 20px 28px;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.2);">
                            <i class="fa-solid fa-tags text-warning" style="font-size: 20px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bold text-white mb-0" style="font-size: 18px;">Katalog 6 Produk Resmi TIP TOK Loewix 🏷️</h5>
                            <span class="text-xs text-white-50 font-weight-bold">Hanya 6 model kamera resmi di bawah ini yang dapat dititipkan pada program TIP TOK</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <!-- Search & Category Filters -->
                    <div class="row g-2 mb-3 align-items-center">
                        <div class="col-md-7">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0" style="border: 1.5px solid #cbd5e1; border-right: none; border-radius: 10px 0 0 10px;"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" id="katalogSearchInput" class="form-control bg-white border-start-0 ps-0" style="border: 1.5px solid #cbd5e1; border-left: none; border-radius: 0 10px 10px 0; font-size: 13.5px; font-weight: 600;" placeholder="Cari tipe barang, kategori, spesifikasi..." oninput="filterKatalogProducts()">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <select id="katalogCategorySelect" class="form-select bg-white" style="border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 13px; font-weight: 700;" onchange="filterKatalogProducts()">
                                <option value="all">Semua Kategori (6 Produk TIP TOK)</option>
                                <?php 
                                    $kats = array_unique(array_filter(array_column($loewixPriceList, 'category')));
                                    sort($kats);
                                    foreach ($kats as $cat): 
                                ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="table-responsive bg-white rounded-3 border" style="max-height: 480px; overflow-y: auto; border: 1.5px solid #cbd5e1 !important;">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background: #0f172a; color: #fff; position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th style="width: 20%; padding: 12px 14px; font-size: 11.5px; font-weight: 800; text-transform: uppercase;">KATEGORI</th>
                                    <th style="width: 36%; padding: 12px 14px; font-size: 11.5px; font-weight: 800; text-transform: uppercase;">NAMA PRODUK & MODEL</th>
                                    <th style="width: 18%; padding: 12px 14px; font-size: 11.5px; font-weight: 800; text-transform: uppercase; text-align: center;">INSENTIF / UNIT</th>
                                    <th style="width: 14%; padding: 12px 14px; font-size: 11.5px; font-weight: 800; text-transform: uppercase; text-align: right;">MSRP RESMI</th>
                                    <th style="width: 12%; padding: 12px 14px; font-size: 11.5px; font-weight: 800; text-transform: uppercase; text-align: center;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody id="katalogProductsBody">
                                <!-- Loaded dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-white border-top justify-content-between">
                    <span class="text-xs text-secondary font-weight-bold" id="katalogCountInfo">Menampilkan <?= count($loewixPriceList) ?> Produk Resmi TIP TOK</span>
                    <button type="button" class="btn btn-secondary px-4 font-weight-bold" data-bs-dismiss="modal">Tutup Katalog</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Datalist Autocomplete 6 Produk TIP TOK Loewix -->
    <datalist id="loewixPriceListDatalist">
        <?php foreach ($loewixPriceList as $p): ?>
            <option value="<?= htmlspecialchars($p['type']) ?>"><?= htmlspecialchars($p['category']) ?> [Insentif: Rp <?= number_format($p['insentif'] ?? 15000, 0, ',', '.') ?>/Unit]<?= $p['msrp'] > 0 ? ' - Rp ' . number_format($p['msrp'], 0, ',', '.') : '' ?></option>
        <?php endforeach; ?>
    </datalist>

    <!-- ========================================================================= -->
    <!-- MODAL 2: LAPORAN KUNJUNGAN & CEK STOK SISA                                -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalLaporKunjungan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 18px; border:none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);">
                <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 18px 24px; border-radius: 18px 18px 0 0;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(37, 99, 235, 0.2); border: 1.5px solid rgba(59, 130, 246, 0.4); display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 20px;">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="modal-title font-weight-bold text-white mb-0">Laporan Kunjungan & Cek Sisa Stok</h5>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 11px; padding: 4px 8px; border-radius: 6px;">Audit Fisik</span>
                            </div>
                            <div class="text-xs mt-0.5" style="color: #94a3b8;">Input kondisi sisa fisik barang di toko saat audit kunjungan</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formLaporKunjungan" onsubmit="submitLaporKunjungan(event)" enctype="multipart/form-data">
                    <input type="hidden" name="id_penitipan" id="kunjunganIdPenitipan">
                    <div class="modal-body p-4 bg-light">
                        <div class="p-3.5 mb-3 rounded-3 bg-white border" style="border: 1.5px solid #cbd5e1 !important; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-weight-bold text-dark fs-6" id="kunjunganNamaToko">-</span>
                                <span class="font-monospace text-xs font-weight-bold text-primary badge bg-primary-subtle px-2 py-1" id="kunjunganKodeTitip">-</span>
                            </div>
                            <div class="text-xs text-secondary font-weight-bold mt-1" id="kunjunganAlamatToko">-</div>
                        </div>

                        <div class="row g-3 mb-3 bg-white p-3.5 rounded-3 border" style="border: 1.5px solid #cbd5e1 !important;">
                            <div class="col-md-6">
                                <label class="form-label-taste"><i class="fa-regular fa-calendar text-primary me-1.5"></i> Tanggal Kunjungan <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_kunjungan" class="form-control-taste w-100 font-weight-bold" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-taste"><i class="fa-solid fa-camera text-primary me-1.5"></i> Foto Bukti Display / Stok (Opsional)</label>
                                <input type="file" name="foto_kunjungan" class="form-control-taste w-100" accept="image/*">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
                            <label class="form-label-taste mb-0"><i class="fa-solid fa-list-check text-primary me-1.5"></i> AUDIT FISIK STOK SISA & PENJUALAN</label>
                            <span class="text-xs text-muted font-weight-bold">Update sisa fisik di toko</span>
                        </div>
                        <div class="table-responsive border rounded-3 mb-3 bg-white" style="border: 1.5px solid #cbd5e1 !important; overflow: hidden;">
                            <table class="table taste-table mb-0">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="width: 36%;">NAMA BARANG</th>
                                        <th style="width: 16%; text-align: center;">STOK LALU</th>
                                        <th style="width: 24%; text-align: center;">SISA FISIK AKTUAL <span class="text-danger">*</span></th>
                                        <th style="width: 24%; text-align: right;">TERJUAL &amp; ESTIMASI</th>
                                    </tr>
                                </thead>
                                <tbody id="kunjunganItemsBody"></tbody>
                            </table>
                        </div>

                        <div class="p-3 rounded-3 bg-white border text-xs font-weight-bold text-secondary mb-3 d-flex align-items-center gap-2.5" style="border: 1.5px solid #cbd5e1 !important;">
                            <div style="width: 30px; height: 30px; border-radius: 8px; background: rgba(37,99,235,0.1); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                Barang terjual akan otomatis tercatat ke sistem. Nomor Faktur/Invoice dikelola secara terpusat di menu khusus <strong><a href="tiptok-invoice.php" class="text-primary text-decoration-underline">No. Invoice TIP TOK</a></strong>.
                            </div>
                        </div>

                        <div class="bg-white p-3.5 rounded-3 border" style="border: 1.5px solid #cbd5e1 !important;">
                            <label class="form-label-taste"><i class="fa-regular fa-note-sticky text-secondary me-1.5"></i> Catatan Hasil Audit Kunjungan</label>
                            <textarea name="catatan_kunjungan" class="form-control-taste w-100" rows="2" placeholder="Tuliskan catatan kondisi display toko / feedback dealer..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-light border-top">
                        <button type="button" class="btn-taste-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnSimpanKunjungan" class="btn-taste-primary">
                            <i class="fa-solid fa-check me-1"></i> Simpan Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: DETAIL LENGKAP & RIWAYAT KUNJUNGAN TOKO (PROFESIONAL & KOMPLIT)   -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalDetailTiptok" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title font-weight-bold text-dark mb-0">Detail Konsinyasi & Histori Audit</h5>
                            <span class="taste-badge badge-neutral font-monospace font-weight-bold" id="detBadgeKode">-</span>
                            <span class="taste-badge badge-active-tag" id="detBadgeStatus">Aktif</span>
                        </div>
                        <div class="text-secondary text-sm font-weight-bold" id="detailKodeTitip">Ringkasan stok titipan toko dan histori audit kunjungan</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn-taste-secondary btn-sm py-1" onclick="cetakSuratJalanDetail()" title="Cetak Surat Jalan / Bukti Titip">
                            <i class="fa-solid fa-print me-1 text-primary"></i> Cetak Surat Titip
                        </button>
                        <button type="button" class="btn-taste-emerald btn-sm py-1" onclick="shareWhatsappDetail()" title="Bagikan Ringkasan Stok ke WhatsApp">
                            <i class="fa-brands fa-whatsapp me-1"></i> Share WA
                        </button>
                        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                
                <div class="modal-body p-4">
                    <div id="detailLoading" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-dark" role="status"></div>
                        <p class="text-muted text-sm mt-2 font-weight-bold">Memuat riwayat konsinyasi...</p>
                    </div>

                    <div id="detailContent" class="d-none">
                        <!-- 1. EXECUTIVE KPI SUMMARY METRICS -->
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-3">
                                <div class="detail-stat-card stat-blue">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-xs font-weight-bold text-uppercase" style="color: #1e40af; letter-spacing: 0.05em;">Total Titip Awal</span>
                                        <i class="fa-solid fa-boxes-stacked" style="color: #2563eb; font-size: 16px;"></i>
                                    </div>
                                    <h3 class="font-weight-bolder mb-0" id="detKpiTitip" style="font-family: 'Outfit', sans-serif; color: #1e3a8a;">0 Unit</h3>
                                    <div class="text-xs font-weight-bold text-muted mt-1">Stok diserahkan</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="detail-stat-card stat-emerald">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-xs font-weight-bold text-uppercase" style="color: #065f46; letter-spacing: 0.05em;">Sisa Stok Fisik</span>
                                        <i class="fa-solid fa-warehouse" style="color: #059669; font-size: 16px;"></i>
                                    </div>
                                    <h3 class="font-weight-bolder mb-0" id="detKpiSisa" style="font-family: 'Outfit', sans-serif; color: #047857;">0 Unit</h3>
                                    <div class="text-xs font-weight-bold text-muted mt-1">Masih di toko</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="detail-stat-card stat-amber">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-xs font-weight-bold text-uppercase" style="color: #92400e; letter-spacing: 0.05em;">Total Terjual</span>
                                        <i class="fa-solid fa-cart-shopping" style="color: #d97706; font-size: 16px;"></i>
                                    </div>
                                    <h3 class="font-weight-bolder mb-0" id="detKpiTerjual" style="font-family: 'Outfit', sans-serif; color: #b45309;">0 Unit</h3>
                                    <div class="text-xs font-weight-bold text-muted mt-1" id="detKpiSellRate">0% laku terjual</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="detail-stat-card stat-purple">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-xs font-weight-bold text-uppercase" style="color: #6b21a8; letter-spacing: 0.05em;">Total Insentif</span>
                                        <i class="fa-solid fa-sack-dollar" style="color: #9333ea; font-size: 16px;"></i>
                                    </div>
                                    <h3 class="font-weight-bolder mb-0" id="detKpiInsentif" style="font-family: 'Outfit', sans-serif; color: #7e22ce;">Rp 0</h3>
                                    <div class="text-xs font-weight-bold text-muted mt-1">Reward terakumulasi</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. STORE & SALES PROFILE CARD -->
                        <div class="p-3 mb-4 rounded-3 bg-light border" style="border: 2px solid #cbd5e1 !important;">
                            <div class="row align-items-center g-3">
                                <div class="col-md-7 border-end-md">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h5 class="font-weight-bold text-dark mb-0" id="detNamaToko">-</h5>
                                        <span class="taste-badge badge-dealer-tag" id="detKategoriToko">Dealer</span>
                                    </div>
                                    <div class="text-sm text-secondary font-weight-bold" id="detAlamatToko">-</div>
                                    <div class="d-flex align-items-center gap-3 mt-2 flex-wrap">
                                        <a href="javascript:void(0)" id="detLinkWa" target="_blank" class="text-xs font-weight-bold text-success d-inline-flex align-items-center gap-1">
                                            <i class="fa-brands fa-whatsapp" style="font-size: 14px;"></i> <span id="detTelpToko">-</span>
                                        </a>
                                        <a href="javascript:void(0)" id="detLinkGmaps" target="_blank" class="text-xs font-weight-bold text-primary d-inline-flex align-items-center gap-1">
                                            <i class="fa-solid fa-location-dot" style="font-size: 13px;"></i> Lihat Peta Lokasi
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex justify-content-between text-sm">
                                            <span class="text-secondary font-weight-bold">Sales Penanggung Jawab:</span>
                                            <strong class="text-dark" id="detNamaSales">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between text-sm">
                                            <span class="text-secondary font-weight-bold">Tanggal Titip Barang:</span>
                                            <strong class="text-dark" id="detTglTitip">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between text-sm">
                                            <span class="text-secondary font-weight-bold">Audit Terakhir:</span>
                                            <strong class="text-primary" id="detLastAudit">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between text-sm mt-1" id="detRowCatatan">
                                            <span class="text-secondary font-weight-bold">Catatan:</span>
                                            <span class="text-dark font-weight-bold text-end" id="detCatatan">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. ACTION SHORTCUT BUTTONS -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div class="form-label-taste mb-0">Rincian Stok Barang Titipan</div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn-taste-primary btn-sm py-1" onclick="openCekSisaFromDetail()">
                                    <i class="fa-solid fa-clipboard-check me-1"></i> Input Cek Sisa Fisik
                                </button>
                                <button type="button" class="btn-taste-secondary btn-sm py-1" onclick="openEditFromDetail()">
                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Titipan
                                </button>
                            </div>
                        </div>

                        <!-- 4. TABEL RINCIAN STOK BARANG -->
                        <div class="table-responsive border rounded-3 mb-4" style="border: 2px solid #cbd5e1 !important; border-radius: 14px; overflow: hidden;">
                            <table class="table taste-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 32%;">NAMA BARANG & TIPE</th>
                                        <th style="width: 10%; text-align: center;">STOK AWAL</th>
                                        <th style="width: 10%; text-align: center;">SISA STOK</th>
                                        <th style="width: 10%; text-align: center;">TERJUAL</th>
                                        <th style="width: 13%; text-align: center;">STATUS</th>
                                        <th style="width: 15%; text-align: right;">INSENTIF / UNIT</th>
                                        <th style="width: 15%; text-align: right;">TOTAL INSENTIF</th>
                                    </tr>
                                </thead>
                                <tbody id="detItemsBody"></tbody>
                                <tfoot id="detItemsFoot" style="background-color: #f8fafc; border-top: 2px solid #cbd5e1; font-weight: 800;"></tfoot>
                            </table>
                        </div>

                        <!-- 5. RIWAYAT KUNJUNGAN & AUDIT SISA STOK -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div class="form-label-taste mb-0">Riwayat Audit Kunjungan & Laporan Penjualan</div>
                            <span class="text-xs font-weight-bold text-muted" id="detTotalKunjunganBadge">0 Kunjungan Terdata</span>
                        </div>
                        <div class="table-responsive border rounded-3 mb-2" style="border: 2px solid #cbd5e1 !important; border-radius: 14px; overflow: hidden;">
                            <table class="table taste-table mb-0">
                                <thead>
                                    <tr>
                                        <th>TGL KUNJUNGAN</th>
                                        <th>SALES</th>
                                        <th>BARANG DIAUDIT</th>
                                        <th class="text-center">SISA FISIK</th>
                                        <th class="text-center">LAKU</th>
                                        <th>NO. INVOICE</th>
                                        <th class="text-end">INSENTIF</th>
                                        <th class="text-center">BUKTI</th>
                                        <th>CATATAN</th>
                                    </tr>
                                </thead>
                                <tbody id="detLogsBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer p-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <div class="text-xs text-secondary font-weight-bold">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i> Data diverifikasi oleh Sistem Sales Loewix
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn-taste-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: PENGAJUAN KLAIM INSENTIF (MIN 50 UNIT)                           -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalSubmitClaim" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">Ajukan Klaim Insentif</h5>
                        <div class="text-secondary text-sm font-weight-bold">Konfirmasi pengajuan klaim insentif minimal 50 unit</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formSubmitClaim" onsubmit="submitKlaimInsentif(event)">
                    <div class="modal-body p-4">
                        <div class="p-3 mb-3 rounded-3 border text-center" style="background-color: var(--accent-emerald-light); border: 2px solid #a7f3d0 !important;">
                            <div class="text-xs font-weight-bold text-uppercase" style="color: var(--accent-emerald); letter-spacing: 0.05em;">Total Unit Siap Klaim</div>
                            <h2 class="font-weight-bolder my-1" style="color: var(--accent-emerald); font-family: 'Outfit', sans-serif; font-size: 36px;"><?php echo $unclaimedUnits; ?> Unit</h2>
                            <div class="text-sm font-weight-bold text-dark">Estimasi Nominal: <span class="text-success" style="font-size: 16px;">Rp <?php echo number_format($unclaimedNominal, 0, ',', '.'); ?></span></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-taste">Catatan Pengajuan Klaim (Opsional)</label>
                            <textarea name="catatan_claim" class="form-control-taste w-100" rows="3" placeholder="Catatan pengajuan klaim insentif..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-light border-top">
                        <button type="button" class="btn-taste-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnProsesClaim" class="btn-taste-primary">
                            <i class="fa-solid fa-paper-plane me-1"></i> Kirim Pengajuan Klaim
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 5: DETAIL KLAIM & APPROVAL (ADMIN / MANAGER)                         -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalClaimApproval" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">Rincian Pengajuan Klaim Insentif</h5>
                        <div class="text-secondary text-sm font-weight-bold" id="claimKodeTitle">-</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="p-3 mb-3 rounded-3 bg-light border" style="border: 2px solid #cbd5e1 !important;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="font-weight-bold text-dark mb-0" id="claimSalesName">-</h5>
                                <span class="text-sm text-secondary font-weight-bold" id="claimTgl">-</span>
                            </div>
                            <div class="text-end">
                                <span class="taste-badge" id="claimStatusBadge">-</span>
                                <h4 class="font-weight-bolder text-success mt-1 mb-0" id="claimNominal" style="font-family: 'Outfit', sans-serif;">-</h4>
                            </div>
                        </div>
                    </div>

                    <div class="form-label-taste mb-2">Detail Item Penjualan dalam Klaim</div>
                    <div class="table-responsive border rounded-3 mb-3" style="border: 2px solid #cbd5e1 !important;">
                        <table class="table taste-table mb-0">
                            <thead>
                                <tr>
                                    <th>TOKO DEALER</th>
                                    <th>NAMA BARANG</th>
                                    <th>NO. INVOICE</th>
                                    <th class="text-center">QTY</th>
                                    <th class="text-end">INSENTIF / UNIT</th>
                                    <th class="text-end">SUBTOTAL</th>
                                </tr>
                            </thead>
                            <tbody id="claimDetailItemsBody"></tbody>
                        </table>
                    </div>

                    <?php if ($role === 'Super Admin' || $role === 'Admin' || $role === 'Sales Manager') : ?>
                        <div class="p-3 rounded-3 bg-light border mt-3" style="border: 2px solid #cbd5e1 !important;">
                            <div class="form-label-taste mb-2">Proses Persetujuan Klaim (Admin View)</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label-taste">Ubah Status</label>
                                    <select id="updateClaimStatusSelect" class="form-control-taste w-100">
                                        <option value="menunggu_approval">Menunggu Approval</option>
                                        <option value="disetujui">Disetujui</option>
                                        <option value="cair">Cair (Selesai Dibayarkan)</option>
                                        <option value="ditolak">Ditolak</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-taste">Catatan Admin / Payout</label>
                                    <input type="text" id="updateClaimAdminNote" class="form-control-taste w-100" placeholder="No referensi transfer / catatan...">
                                </div>
                            </div>
                            <div class="text-end mt-2">
                                <button class="btn-taste-primary btn-sm" onclick="submitUpdateClaimStatus()">
                                    <i class="fa-solid fa-check me-1"></i> Simpan Status Klaim
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer p-3 bg-light border-top">
                    <button type="button" class="btn-taste-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Core JS Libraries (jQuery, Bootstrap 5 Bundle, DataTables, Select2) -->
    <?php include "js-include.php"; ?>

    <!-- JavaScript & Logic -->
    <script>
        let dealersList = [];
        let currentLoadedPenitipan = null;
        let currentClaimId = 0;
        let editItemRowIndex = 0;

        // Loewix Products from Price List (product_prices)
        const loewixProducts = <?php echo json_encode($loewixPriceList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let katalogTargetModal = 'tambah'; // 'tambah' or 'edit'

        // =========================================================================
        // HELPER FUNGSI TARIK DATA 6 PRODUK RESMI TIP TOK LOEWIX
        // =========================================================================
        function renderPriceListOptions(selectedVal = '') {
            if (!loewixProducts || loewixProducts.length === 0) {
                return '<option value="" disabled>Belum ada produk TIP TOK</option>';
            }
            const groups = {
                '2MP AHD INDOOR / OUTDOOR': [],
                '4MP IPCAM INDOOR / OUTDOOR': []
            };
            loewixProducts.forEach(p => {
                if ((p.category || '').includes('4MP')) {
                    groups['4MP IPCAM INDOOR / OUTDOOR'].push(p);
                } else {
                    groups['2MP AHD INDOOR / OUTDOOR'].push(p);
                }
            });

            let html = '';
            for (const cat in groups) {
                if (groups[cat].length === 0) continue;
                html += `<optgroup label="📹 ${escapeHtml(cat)}">`;
                groups[cat].forEach(p => {
                    const isSel = (p.type === selectedVal || p.model === selectedVal) ? 'selected' : '';
                    const priceStr = p.msrp > 0 ? ` (MSRP: Rp ${new Intl.NumberFormat('id-ID').format(p.msrp)})` : '';
                    const insentifStr = p.insentif > 0 ? ` • Insentif: Rp ${new Intl.NumberFormat('id-ID').format(p.insentif)}/Unit` : '';
                    html += `<option value="${escapeHtml(p.type)}" ${isSel}>${escapeHtml(p.type)}${insentifStr}${priceStr}</option>`;
                });
                html += `</optgroup>`;
            }
            return html;
        }

        function onSelectRowProduct(selectEl, rowIndex, prefix = '') {
            const selectedType = selectEl.value;
            const p = loewixProducts.find(item => item.type === selectedType || item.model === selectedType);
            const inputNama = document.getElementById(`inputNama_${prefix}${rowIndex}`);
            const inputTipe = document.getElementById(`inputTipe_${prefix}${rowIndex}`);
            const inputInsentif = document.getElementById(`inputInsentif_${prefix}${rowIndex}`);
            const inputQty = document.getElementById(`inputQty_${prefix}${rowIndex}`);
            const badgeEl = document.getElementById(`itemCatBadge_${prefix}${rowIndex}`);
            const metaBox = document.getElementById(`productMeta_${prefix}${rowIndex}`);
            const metaTitle = document.getElementById(`metaTitle_${prefix}${rowIndex}`);
            const metaMsrp = document.getElementById(`metaMsrp_${prefix}${rowIndex}`);
            const metaInsentif = document.getElementById(`metaInsentifPill_${prefix}${rowIndex}`);

            if (p) {
                if (inputNama) inputNama.value = p.type;
                if (inputTipe) inputTipe.value = p.category;
                if (inputInsentif) inputInsentif.value = p.insentif;
                if (inputQty && (!inputQty.value || parseInt(inputQty.value) <= 0)) {
                    inputQty.value = 1;
                }
                if (badgeEl) {
                    badgeEl.textContent = p.category;
                    badgeEl.classList.remove('d-none');
                }
                if (metaBox) {
                    metaBox.classList.remove('d-none');
                    if (metaTitle) metaTitle.textContent = `${p.type}`;
                    if (metaMsrp) metaMsrp.textContent = p.msrp > 0 ? `MSRP Resmi: Rp ${new Intl.NumberFormat('id-ID').format(p.msrp)}` : 'Produk Resmi TIP TOK';
                    if (metaInsentif) metaInsentif.innerHTML = `<i class="fa-solid fa-coins"></i> Insentif: Rp ${new Intl.NumberFormat('id-ID').format(p.insentif)} / Unit`;
                }
            } else {
                if (inputNama) inputNama.value = '';
                if (inputTipe) inputTipe.value = '';
                if (inputInsentif) inputInsentif.value = '';
                if (badgeEl) badgeEl.classList.add('d-none');
                if (metaBox) metaBox.classList.add('d-none');
            }
            recalcRowSubtotal(rowIndex, prefix);
        }

        function stepQty(rowIndex, delta, prefix = '') {
            const qtyInp = document.getElementById(`inputQty_${prefix}${rowIndex}`);
            if (!qtyInp) return;
            let current = parseInt(qtyInp.value) || 0;
            current = Math.max(1, current + delta);
            qtyInp.value = current;
            recalcRowSubtotal(rowIndex, prefix);
        }

        function recalcRowSubtotal(rowIndex, prefix = '') {
            const qtyInp = document.getElementById(`inputQty_${prefix}${rowIndex}`);
            const insentifInp = document.getElementById(`inputInsentif_${prefix}${rowIndex}`);
            const namaInp = document.getElementById(`inputNama_${prefix}${rowIndex}`);
            const subtotalEl = document.getElementById(`subtotalInsentif_${prefix}${rowIndex}`);
            
            const isSelected = namaInp && namaInp.value.trim() !== '';
            const qty = parseInt(qtyInp?.value) || 0;
            const insentif = parseFloat(insentifInp?.value) || 0;
            const subtotal = isSelected ? (qty * insentif) : 0;

            if (subtotalEl) {
                subtotalEl.textContent = `Rp ${new Intl.NumberFormat('id-ID').format(subtotal)}`;
            }
            updateModalSummary(prefix);
        }

        function updateModalSummary(prefix = '') {
            const isEdit = (prefix === 'edit_');
            const containerId = isEdit ? 'editContainerItemRows' : 'containerItemRows';
            const container = document.getElementById(containerId);
            if (!container) return;

            const itemRows = container.querySelectorAll('.item-card-row');
            let totalQty = 0;
            let totalInsentif = 0;
            let modelCount = 0;

            itemRows.forEach(row => {
                const qInp = row.querySelector(`input[id^="inputQty_${prefix}"]`);
                const insInp = row.querySelector(`input[id^="inputInsentif_${prefix}"]`);
                const nameInp = row.querySelector(`input[id^="inputNama_${prefix}"]`);

                const isSelected = nameInp && nameInp.value.trim() !== '';
                const q = parseInt(qInp?.value) || 0;
                const ins = parseFloat(insInp?.value) || 0;

                if (isSelected && q > 0) {
                    totalQty += q;
                    totalInsentif += (q * ins);
                    modelCount++;
                }
            });

            const qtyPreview = document.getElementById(isEdit ? 'editTotalQtyTitipPreview' : 'totalQtyTitipPreview');
            const modelPreview = document.getElementById(isEdit ? 'editTotalModelTitipPreview' : 'totalModelTitipPreview');
            const insentifPreview = document.getElementById(isEdit ? 'editTotalInsentifTitipPreview' : 'totalInsentifTitipPreview');

            if (qtyPreview) qtyPreview.textContent = `${totalQty} Unit (${modelCount} Model)`;
            if (modelPreview) modelPreview.textContent = modelCount;
            if (insentifPreview) insentifPreview.textContent = `Rp ${new Intl.NumberFormat('id-ID').format(totalInsentif)}`;
        }

        // =========================================================================
        // MODAL KATALOG 6 PRODUK RESMI TIP TOK BROWSER
        // =========================================================================
        function openKatalogPriceListModal(target = 'tambah') {
            katalogTargetModal = target;
            const searchInp = document.getElementById('katalogSearchInput');
            if (searchInp) searchInp.value = '';
            const catSel = document.getElementById('katalogCategorySelect');
            if (catSel) catSel.value = 'all';
            renderKatalogProducts('', 'all');
            showModalSafe('modalKatalogPriceList');
        }

        function filterKatalogProducts() {
            const search = (document.getElementById('katalogSearchInput')?.value || '').toLowerCase().trim();
            const cat = document.getElementById('katalogCategorySelect')?.value || 'all';
            renderKatalogProducts(search, cat);
        }

        function renderKatalogProducts(filterSearch = '', filterCat = 'all') {
            const tbody = document.getElementById('katalogProductsBody');
            const countInfo = document.getElementById('katalogCountInfo');
            if (!tbody) return;

            let filtered = loewixProducts.filter(p => {
                const matchCat = (filterCat === 'all' || p.category === filterCat);
                const query = filterSearch.toLowerCase();
                const matchSearch = (!query || 
                    (p.type || '').toLowerCase().includes(query) || 
                    (p.category || '').toLowerCase().includes(query) || 
                    (p.description || '').toLowerCase().includes(query) || 
                    (p.model || '').toLowerCase().includes(query)
                );
                return matchCat && matchSearch;
            });

            if (countInfo) {
                countInfo.textContent = `Menampilkan ${filtered.length} dari total ${loewixProducts.length} Produk Resmi TIP TOK`;
            }

            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted font-weight-bold">Tidak ada produk yang cocok dengan pencarian.</td></tr>';
                return;
            }

            let html = '';
            filtered.forEach(p => {
                const msrpStr = p.msrp > 0 ? `Rp ${new Intl.NumberFormat('id-ID').format(p.msrp)}` : '-';
                const descStr = p.description ? `<div class="text-xs text-muted mt-0.5" style="line-height:1.3;">${escapeHtml(p.description)}</div>` : '';
                const insentifBadge = p.insentif > 0 ? 
                    `<span class="taste-badge badge-success-tag" style="font-size: 11.5px; font-weight: 800;">Rp ${new Intl.NumberFormat('id-ID').format(p.insentif)}/Unit</span>` : '-';
                html += `
                    <tr>
                        <td>
                            <span class="taste-badge badge-dealer-tag" style="font-size: 11px;">
                                ${escapeHtml(p.category)}
                            </span>
                        </td>
                        <td>
                            <strong class="text-dark" style="font-size: 13.5px;">${escapeHtml(p.type)}</strong>
                            ${descStr}
                        </td>
                        <td class="text-center">
                            ${insentifBadge}
                        </td>
                        <td class="text-end font-weight-bold text-dark" style="font-size: 13.5px; font-family: monospace;">
                            ${msrpStr}
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-primary font-weight-bold px-3 py-1 mb-0" style="border-radius: 8px; font-size: 12px;" onclick="pilihProdukDariKatalog(${p.id})">
                                <i class="fa-solid fa-plus me-1"></i> Pilih Barang
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function pilihProdukDariKatalog(prodId) {
            const p = loewixProducts.find(item => item.id == prodId);
            if (!p) return;

            hideModalSafe('modalKatalogPriceList');

            if (katalogTargetModal === 'edit') {
                tambahBarisBarangEdit(p);
            } else {
                tambahBarisBarang(p);
            }

            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: `Produk "${p.type}" berhasil dipilih!`
            });
        }

        function escapeHtml(text) {
            if (!text && text !== 0) return '';
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function getBootstrapModal(modalId) {
            const el = document.getElementById(modalId);
            if (!el) return null;
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                return bootstrap.Modal.getOrCreateInstance(el);
            }
            return null;
        }

        function showModalSafe(modalId) {
            const m = getBootstrapModal(modalId);
            if (m) {
                m.show();
            } else if (typeof $ !== 'undefined' && $(document.getElementById(modalId)).modal) {
                $(document.getElementById(modalId)).modal('show');
            }
        }

        function hideModalSafe(modalId) {
            const m = getBootstrapModal(modalId);
            if (m) {
                m.hide();
            } else if (typeof $ !== 'undefined' && $(document.getElementById(modalId)).modal) {
                $(document.getElementById(modalId)).modal('hide');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateBadgeCounts();
            loadDealers();
            tambahBarisBarang();
        });

        function updateBadgeCounts() {
            const bAll = document.getElementById('badgeCountAll');
            const bAktif = document.getElementById('badgeCountAktif');
            const bTerjual = document.getElementById('badgeCountTerjual');
            const bSelesai = document.getElementById('badgeCountSelesai');
            if (bAll) bAll.textContent = '<?php echo $countAll; ?>';
            if (bAktif) bAktif.textContent = '<?php echo $countAktif; ?>';
            if (bTerjual) bTerjual.textContent = '<?php echo $countTerjual; ?>';
            if (bSelesai) bSelesai.textContent = '<?php echo $countSelesai; ?>';
        }

        let currentFilterCategory = 'all';

        function filterTable(category, btn) {
            currentFilterCategory = category;
            switchViewToTable();

            document.querySelectorAll('.segment-btn-vibrant').forEach(b => b.classList.remove('active'));
            if (btn) {
                btn.classList.add('active');
            } else {
                const targetBtn = document.getElementById(category === 'all' ? 'btnFilterAll' : (category === 'aktif' ? 'btnFilterAktif' : (category === 'terjual' ? 'btnFilterTerjual' : 'btnFilterSelesai')));
                if (targetBtn) targetBtn.classList.add('active');
            }

            const rows = document.querySelectorAll('#mainTiptokTable tbody tr.tiptok-row');
            rows.forEach(row => {
                const cats = row.getAttribute('data-category') || '';
                if (category === 'all' || cats.includes(category)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function searchTiptokTable() {
            const query = (document.getElementById('tiptokSearchInput')?.value || '').toLowerCase();
            const rows = document.querySelectorAll('#mainTiptokTable tbody tr.tiptok-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function switchViewToClaims(btn) {
            const vTable = document.getElementById('viewPenitipanTable');
            const vClaim = document.getElementById('viewKlaimInsentif');
            if (vTable) vTable.classList.add('d-none');
            if (vClaim) vClaim.classList.remove('d-none');
            
            // Remove active from all filter buttons and activate ONLY claim tab button
            document.querySelectorAll('.segment-btn-vibrant').forEach(b => b.classList.remove('active'));
            const claimBtn = btn || document.getElementById('btnTabKlaimInsentif');
            if (claimBtn) claimBtn.classList.add('active');

            loadClaimSummary();
        }

        function switchViewToTable() {
            const vTable = document.getElementById('viewPenitipanTable');
            const vClaim = document.getElementById('viewKlaimInsentif');
            if (vClaim) vClaim.classList.add('d-none');
            if (vTable) vTable.classList.remove('d-none');

            // Deactivate claim tab button
            const claimBtn = document.getElementById('btnTabKlaimInsentif');
            if (claimBtn) claimBtn.classList.remove('active');

            // Activate current active filter button
            const activeFilter = document.querySelector('.segment-btn-vibrant.active');
            if (!activeFilter || activeFilter === claimBtn) {
                document.querySelectorAll('.segment-btn-vibrant').forEach(b => b.classList.remove('active'));
                const targetBtn = document.getElementById(currentFilterCategory === 'all' ? 'btnFilterAll' : (currentFilterCategory === 'aktif' ? 'btnFilterAktif' : (currentFilterCategory === 'terjual' ? 'btnFilterTerjual' : 'btnFilterSelesai')));
                if (targetBtn) targetBtn.classList.add('active');
            }
        }

        function openTabKlaimInsentif() {
            switchViewToClaims();
        }

        function loadDealers() {
            fetch('tiptok-ajax.php?action=search_dealer')
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success' && Array.isArray(res.data)) {
                        dealersList = res.data;
                        const sel = document.getElementById('selectDealer');
                        if (sel) {
                            sel.innerHTML = '<option value="">-- Pilih Toko Mitra TIP TOK --</option>';
                            if (dealersList.length === 0) {
                                sel.innerHTML += '<option value="" disabled>⚠️ Belum ada toko bertanda TIP TOK. Tandai toko di menu Customer terlebih dahulu.</option>';
                            } else {
                                dealersList.forEach(d => {
                                    const katBadge = d.kategori ? `[${d.kategori}] ` : '';
                                    sel.innerHTML += `<option value="${d.id}">${katBadge}${escapeHtml(d.nama)} - ${escapeHtml(d.kota || '')}</option>`;
                                });
                            }
                        }
                    }
                })
                .catch(err => console.error('Error loading dealers:', err));
        }

        function onDealerSelected() {
            const selEl = document.getElementById('selectDealer');
            if (!selEl) return;
            const id = selEl.value;
            const dealer = dealersList.find(d => d.id == id);
            const prev = document.getElementById('dealerPreview');
            if (dealer && prev) {
                document.getElementById('prevNamaToko').textContent = dealer.nama || '-';
                document.getElementById('prevKategoriToko').textContent = dealer.kategori || 'Dealer';
                document.getElementById('prevAlamatToko').textContent = (dealer.alamat || '') + (dealer.kota ? ', ' + dealer.kota : '');
                document.getElementById('prevTelpToko').textContent = dealer.telp_pribadi ? 'WA / Telp: ' + dealer.telp_pribadi : '';
                prev.classList.remove('d-none');
            } else if (prev) {
                prev.classList.add('d-none');
            }
        }

        let itemRowIndex = 0;
        function tambahBarisBarang(prefill = null) {
            itemRowIndex++;
            const container = document.getElementById('containerItemRows');
            if (!container) return;

            const pNama = prefill ? (prefill.type || prefill.nama_barang || '') : '';
            const pTipe = prefill ? (prefill.category || prefill.tipe_barang || '') : '';
            let pInsentif = '';
            if (prefill) {
                if (prefill.insentif) pInsentif = prefill.insentif;
                else if (prefill.insentif_per_unit) pInsentif = prefill.insentif_per_unit;
                else if (pNama.includes('4MP')) pInsentif = '30000';
                else if (pNama.includes('2MP')) pInsentif = '15000';
            }
            const pQty = (prefill && prefill.qty_titip) ? prefill.qty_titip : 1;

            const rowHtml = `
                <div class="item-card-row" id="itemRow_${itemRowIndex}">
                    <input type="hidden" name="items[${itemRowIndex}][nama_barang]" id="inputNama_${itemRowIndex}" value="${escapeHtml(pNama)}">
                    <input type="hidden" name="items[${itemRowIndex}][tipe_barang]" id="inputTipe_${itemRowIndex}" value="${escapeHtml(pTipe)}">

                    <!-- Row Top Header -->
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-dark text-white px-2.5 py-1" style="font-size: 11.5px; border-radius: 8px; font-weight: 800;">
                                <i class="fa-solid fa-box me-1 text-primary"></i> Item #${itemRowIndex}
                            </span>
                            <span class="taste-badge badge-dealer-tag ${pTipe ? '' : 'd-none'}" id="itemCatBadge_${itemRowIndex}">
                                ${escapeHtml(pTipe)}
                            </span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2.5 mb-0 font-weight-bold" style="border-radius: 8px; font-size: 12px;" onclick="hapusBarisBarang(${itemRowIndex})">
                            <i class="fa-solid fa-trash-can me-1"></i> Hapus
                        </button>
                    </div>

                    <!-- Single Clean Product Selector -->
                    <div class="mb-3">
                        <label class="form-label-taste mb-1 d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-video text-primary me-1"></i> PILIH 1 DARI 6 KAMERA RESMI TIP TOK <span class="text-danger">*</span></span>
                            <span class="text-primary text-xs" style="cursor: pointer; font-weight: 700;" onclick="openKatalogPriceListModal('tambah')">
                                <i class="fa-solid fa-eye me-1"></i> Lihat Katalog Visual
                            </span>
                        </label>
                        <select id="selectProduct_${itemRowIndex}" class="form-select product-select-premium w-100" onchange="onSelectRowProduct(this, ${itemRowIndex}, '')">
                            <option value="">-- Pilih Model Kamera Resmi TIP TOK --</option>
                            ${renderPriceListOptions(pNama)}
                        </select>
                    </div>

                    <!-- Live Product Spec Meta Box -->
                    <div id="productMeta_${itemRowIndex}" class="product-meta-card ${pNama ? '' : 'd-none'} mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width: 34px; height: 34px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                <i class="fa-solid fa-video"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 13px;" id="metaTitle_${itemRowIndex}">-</div>
                                <div class="text-xs text-muted" id="metaMsrp_${itemRowIndex}">-</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="incentive-badge-glow" id="metaInsentifPill_${itemRowIndex}">
                                <i class="fa-solid fa-coins"></i> Insentif: Rp 15.000 / Unit
                            </span>
                        </div>
                    </div>

                    <!-- Qty, Insentif, & Subtotal in Bulletproof Custom Controls -->
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label-taste mb-1.5"><i class="fa-solid fa-boxes-stacked text-primary me-1"></i> JUMLAH TITIP <span class="text-danger">*</span></label>
                            <div class="taste-qty-stepper">
                                <button type="button" class="stepper-btn" onclick="stepQty(${itemRowIndex}, -1, '')" title="Kurangi">−</button>
                                <input type="number" name="items[${itemRowIndex}][qty_titip]" id="inputQty_${itemRowIndex}" min="1" class="stepper-input" value="${pQty}" required oninput="recalcRowSubtotal(${itemRowIndex}, '')">
                                <button type="button" class="stepper-btn" onclick="stepQty(${itemRowIndex}, 1, '')" title="Tambah">+</button>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label-taste mb-1.5"><i class="fa-solid fa-tag text-success me-1"></i> TARIF INSENTIF</label>
                            <div class="taste-addon-input">
                                <span class="addon-label">Rp</span>
                                <input type="number" name="items[${itemRowIndex}][insentif_per_unit]" id="inputInsentif_${itemRowIndex}" min="0" step="500" class="addon-input-field" value="${pInsentif}" placeholder="0" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <label class="form-label-taste mb-1.5 text-end d-block"><i class="fa-solid fa-coins text-warning me-1"></i> SUBTOTAL REWARD</label>
                            <div class="taste-subtotal-card">
                                <span class="text-xs text-muted font-weight-bold">Komisi:</span>
                                <span class="fw-bold text-success fs-6" id="subtotalInsentif_${itemRowIndex}">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rowHtml);
            const selEl = document.getElementById(`selectProduct_${itemRowIndex}`);
            if (pNama && selEl) {
                selEl.value = pNama;
                onSelectRowProduct(selEl, itemRowIndex, '');
            } else {
                recalcRowSubtotal(itemRowIndex, '');
            }
        }

        function hapusBarisBarang(idx) {
            const el = document.getElementById(`itemRow_${idx}`);
            if (el) el.remove();
            updateModalSummary('');
        }

        function openModalTambahPenitipan() {
            const form = document.getElementById('formTambahPenitipan');
            if (form) form.reset();
            const prev = document.getElementById('dealerPreview');
            if (prev) prev.classList.add('d-none');
            const container = document.getElementById('containerItemRows');
            if (container) container.innerHTML = '';
            itemRowIndex = 0;
            tambahBarisBarang();
            showModalSafe('modalTambahPenitipan');
        }

        function submitTambahPenitipan(e) {
            e.preventDefault();
            const form = document.getElementById('formTambahPenitipan');
            const formData = new FormData(form);
            formData.append('action', 'simpan_penitipan');

            const btn = document.getElementById('btnSimpanPenitipan');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';
            }

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Penitipan';
                    }

                    if (res && res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', html: (res && res.message) ? res.message : 'Gagal menyimpan data.' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Penitipan';
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan saat menyimpan penitipan.' });
                });
        }

        // =========================================================================
        // EDIT PENITIPAN BARANG
        // =========================================================================
        function openModalEditPenitipan(idPenitipan) {
            document.getElementById('editIdPenitipan').value = idPenitipan;
            const container = document.getElementById('editContainerItemRows');
            container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-dark"></div> Memuat data...</div>';
            
            showModalSafe('modalEditPenitipan');

            fetch(`tiptok-ajax.php?action=get_detail&id=${idPenitipan}`)
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success') {
                        const m = res.data.master;
                        document.getElementById('editModalSubtitle').textContent = `Kode: ${m.kode_titip || '-'} - Sales: ${m.nama_sales || 'Sales'}`;
                        document.getElementById('editTglTitip').value = m.tgl_titip || '';
                        document.getElementById('editCatatan').value = m.catatan || '';
                        document.getElementById('editStatusPenitipan').value = m.status || 'aktif';

                        // Dropdown dealer
                        const sel = document.getElementById('editSelectDealer');
                        if (sel) {
                            sel.innerHTML = '<option value="">-- Pilih Toko Mitra TIP TOK --</option>';
                            dealersList.forEach(d => {
                                const katBadge = d.kategori ? `[${d.kategori}] ` : '';
                                const selected = (d.id == m.id_customer) ? 'selected' : '';
                                sel.innerHTML += `<option value="${d.id}" ${selected}>${katBadge}${escapeHtml(d.nama)} - ${escapeHtml(d.kota || '')}</option>`;
                            });
                        }
                        onEditDealerSelected();

                        // Render items
                        container.innerHTML = '';
                        editItemRowIndex = 0;
                        if (!res.data.items || res.data.items.length === 0) {
                            tambahBarisBarangEdit();
                        } else {
                            res.data.items.forEach(it => {
                                editItemRowIndex++;
                                const isSold = parseInt(it.qty_terjual) > 0;
                                const deleteBtn = isSold ? 
                                    `<span class="taste-badge badge-danger-tag" style="font-size:12px;">Terjual ${it.qty_terjual} unit (Terkunci)</span>` : 
                                    `<button type="button" class="btn btn-sm btn-outline-danger py-1 px-2.5 mb-0 font-weight-bold" style="border-radius: 8px; font-size: 12px;" onclick="hapusBarisBarangEdit(${editItemRowIndex})">
                                        <i class="fa-solid fa-trash-can me-1"></i> Hapus
                                     </button>`;

                                const pInsentif = it.insentif_per_unit || (it.nama_barang.includes('4MP') ? 30000 : 15000);

                                const rowHtml = `
                                    <div class="item-card-row" id="editItemRow_${editItemRowIndex}">
                                        <input type="hidden" name="items[${editItemRowIndex}][id_item]" value="${it.id}">
                                        <input type="hidden" name="items[${editItemRowIndex}][nama_barang]" id="inputNama_edit_${editItemRowIndex}" value="${escapeHtml(it.nama_barang)}">
                                        <input type="hidden" name="items[${editItemRowIndex}][tipe_barang]" id="inputTipe_edit_${editItemRowIndex}" value="${escapeHtml(it.tipe_barang || '')}">

                                        <!-- Row Top Header -->
                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-dark text-white px-2.5 py-1" style="font-size: 11.5px; border-radius: 8px; font-weight: 800;">
                                                    <i class="fa-solid fa-box me-1 text-primary"></i> Item #${editItemRowIndex}
                                                </span>
                                                <span class="taste-badge badge-dealer-tag ${it.tipe_barang ? '' : 'd-none'}" id="itemCatBadge_edit_${editItemRowIndex}">
                                                    ${escapeHtml(it.tipe_barang || '')}
                                                </span>
                                            </div>
                                            ${deleteBtn}
                                        </div>

                                        <!-- Single Clean Product Selector -->
                                        <div class="mb-3">
                                            <label class="form-label-taste mb-1 d-flex justify-content-between align-items-center">
                                                <span><i class="fa-solid fa-video text-primary me-1"></i> PILIH 1 DARI 6 KAMERA RESMI TIP TOK <span class="text-danger">*</span></span>
                                                <span class="text-primary text-xs" style="cursor: pointer; font-weight: 700;" onclick="openKatalogPriceListModal('edit')">
                                                    <i class="fa-solid fa-eye me-1"></i> Lihat Katalog Visual
                                                </span>
                                            </label>
                                            <select id="selectProduct_edit_${editItemRowIndex}" class="form-select product-select-premium w-100" onchange="onSelectRowProduct(this, ${editItemRowIndex}, 'edit_')" ${isSold ? 'disabled' : ''}>
                                                <option value="">-- Pilih Model Kamera Resmi TIP TOK --</option>
                                                ${renderPriceListOptions(it.nama_barang)}
                                            </select>
                                        </div>

                                        <!-- Live Product Spec Meta Box -->
                                        <div id="productMeta_edit_${editItemRowIndex}" class="product-meta-card mb-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 34px; height: 34px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                    <i class="fa-solid fa-video"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark" style="font-size: 13px;" id="metaTitle_edit_${editItemRowIndex}">${escapeHtml(it.nama_barang)}</div>
                                                    <div class="text-xs text-muted" id="metaMsrp_edit_${editItemRowIndex}">Produk Resmi TIP TOK</div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="incentive-badge-glow" id="metaInsentifPill_edit_${editItemRowIndex}">
                                                    <i class="fa-solid fa-coins"></i> Insentif: Rp ${new Intl.NumberFormat('id-ID').format(pInsentif)} / Unit
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Qty, Insentif, & Subtotal in Bulletproof Custom Controls -->
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-4 col-sm-6">
                                                <label class="form-label-taste mb-1.5"><i class="fa-solid fa-boxes-stacked text-primary me-1"></i> JUMLAH TITIP <span class="text-danger">*</span></label>
                                                <div class="taste-qty-stepper">
                                                    <button type="button" class="stepper-btn" onclick="stepQty(${editItemRowIndex}, -1, 'edit_')" title="Kurangi">−</button>
                                                    <input type="number" name="items[${editItemRowIndex}][qty_titip]" id="inputQty_edit_${editItemRowIndex}" min="${Math.max(1, parseInt(it.qty_terjual) || 1)}" class="stepper-input" value="${it.qty_titip}" required oninput="recalcRowSubtotal(${editItemRowIndex}, 'edit_')">
                                                    <button type="button" class="stepper-btn" onclick="stepQty(${editItemRowIndex}, 1, 'edit_')" title="Tambah">+</button>
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-sm-6">
                                                <label class="form-label-taste mb-1.5"><i class="fa-solid fa-tag text-success me-1"></i> TARIF INSENTIF</label>
                                                <div class="taste-addon-input">
                                                    <span class="addon-label">Rp</span>
                                                    <input type="number" name="items[${editItemRowIndex}][insentif_per_unit]" id="inputInsentif_edit_${editItemRowIndex}" min="0" step="500" class="addon-input-field" value="${pInsentif}" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-4 col-sm-12">
                                                <label class="form-label-taste mb-1.5 text-end d-block"><i class="fa-solid fa-coins text-warning me-1"></i> SUBTOTAL REWARD</label>
                                                <div class="taste-subtotal-card">
                                                    <span class="text-xs text-muted font-weight-bold">Komisi:</span>
                                                    <span class="fw-bold text-success fs-6" id="subtotalInsentif_edit_${editItemRowIndex}">Rp 0</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                container.insertAdjacentHTML('beforeend', rowHtml);
                                const selEl = document.getElementById(`selectProduct_edit_${editItemRowIndex}`);
                                if (selEl && it.nama_barang) {
                                    selEl.value = it.nama_barang;
                                    onSelectRowProduct(selEl, editItemRowIndex, 'edit_');
                                }
                            });
                            updateModalSummary('edit_');
                        }
                    } else {
                        container.innerHTML = `<div class="p-3 text-center text-danger font-weight-bold">${(res && res.message) ? res.message : 'Gagal memuat data.'}</div>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    container.innerHTML = '<div class="p-3 text-center text-danger font-weight-bold">Terjadi kesalahan jaringan saat memuat data.</div>';
                });
        }

        function onEditDealerSelected() {
            const selEl = document.getElementById('editSelectDealer');
            if (!selEl) return;
            const id = selEl.value;
            const dealer = dealersList.find(d => d.id == id);
            const prev = document.getElementById('editDealerPreview');
            if (dealer && prev) {
                document.getElementById('editPrevNamaToko').textContent = dealer.nama || '-';
                document.getElementById('editPrevKategoriToko').textContent = dealer.kategori || 'Dealer';
                document.getElementById('editPrevAlamatToko').textContent = (dealer.alamat || '') + (dealer.kota ? ', ' + dealer.kota : '');
                document.getElementById('editPrevTelpToko').textContent = dealer.telp_pribadi ? 'WA / Telp: ' + dealer.telp_pribadi : '';
                prev.classList.remove('d-none');
            } else if (prev) {
                prev.classList.add('d-none');
            }
        }

        function tambahBarisBarangEdit(prefill = null) {
            editItemRowIndex++;
            const container = document.getElementById('editContainerItemRows');
            if (!container) return;

            const pNama = prefill ? (prefill.type || prefill.nama_barang || '') : '';
            const pTipe = prefill ? (prefill.category || prefill.tipe_barang || '') : '';
            let pInsentif = '';
            if (prefill) {
                if (prefill.insentif) pInsentif = prefill.insentif;
                else if (prefill.insentif_per_unit) pInsentif = prefill.insentif_per_unit;
                else if (pNama.includes('4MP')) pInsentif = '30000';
                else if (pNama.includes('2MP')) pInsentif = '15000';
            }
            const pQty = (prefill && prefill.qty_titip) ? prefill.qty_titip : 1;

            const rowHtml = `
                <div class="item-card-row" id="editItemRow_${editItemRowIndex}">
                    <input type="hidden" name="items[${editItemRowIndex}][id_item]" value="0">
                    <input type="hidden" name="items[${editItemRowIndex}][nama_barang]" id="inputNama_edit_${editItemRowIndex}" value="${escapeHtml(pNama)}">
                    <input type="hidden" name="items[${editItemRowIndex}][tipe_barang]" id="inputTipe_edit_${editItemRowIndex}" value="${escapeHtml(pTipe)}">

                    <!-- Row Top Header -->
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-dark text-white px-2.5 py-1" style="font-size: 11.5px; border-radius: 8px; font-weight: 800;">
                                <i class="fa-solid fa-box me-1 text-primary"></i> Item Baru #${editItemRowIndex}
                            </span>
                            <span class="taste-badge badge-dealer-tag ${pTipe ? '' : 'd-none'}" id="itemCatBadge_edit_${editItemRowIndex}">
                                ${escapeHtml(pTipe)}
                            </span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2.5 mb-0 font-weight-bold" style="border-radius: 8px; font-size: 12px;" onclick="hapusBarisBarangEdit(${editItemRowIndex})">
                            <i class="fa-solid fa-trash-can me-1"></i> Hapus
                        </button>
                    </div>

                    <!-- Single Clean Product Selector -->
                    <div class="mb-3">
                        <label class="form-label-taste mb-1 d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-video text-primary me-1"></i> PILIH 1 DARI 6 KAMERA RESMI TIP TOK <span class="text-danger">*</span></span>
                            <span class="text-primary text-xs" style="cursor: pointer; font-weight: 700;" onclick="openKatalogPriceListModal('edit')">
                                <i class="fa-solid fa-eye me-1"></i> Lihat Katalog Visual
                            </span>
                        </label>
                        <select id="selectProduct_edit_${editItemRowIndex}" class="form-select product-select-premium w-100" onchange="onSelectRowProduct(this, ${editItemRowIndex}, 'edit_')">
                            <option value="">-- Pilih Model Kamera Resmi TIP TOK --</option>
                            ${renderPriceListOptions(pNama)}
                        </select>
                    </div>

                    <!-- Live Product Spec Meta Box -->
                    <div id="productMeta_edit_${editItemRowIndex}" class="product-meta-card ${pNama ? '' : 'd-none'} mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width: 34px; height: 34px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                <i class="fa-solid fa-video"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 13px;" id="metaTitle_edit_${editItemRowIndex}">-</div>
                                <div class="text-xs text-muted" id="metaMsrp_edit_${editItemRowIndex}">-</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="incentive-badge-glow" id="metaInsentifPill_edit_${editItemRowIndex}">
                                <i class="fa-solid fa-coins"></i> Insentif: Rp 15.000 / Unit
                            </span>
                        </div>
                    </div>

                    <!-- Qty, Insentif, & Subtotal in Bulletproof Custom Controls -->
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label-taste mb-1.5"><i class="fa-solid fa-boxes-stacked text-primary me-1"></i> JUMLAH TITIP <span class="text-danger">*</span></label>
                            <div class="taste-qty-stepper">
                                <button type="button" class="stepper-btn" onclick="stepQty(${editItemRowIndex}, -1, 'edit_')" title="Kurangi">−</button>
                                <input type="number" name="items[${editItemRowIndex}][qty_titip]" id="inputQty_edit_${editItemRowIndex}" min="1" class="stepper-input" placeholder="0" value="${pQty}" required oninput="recalcRowSubtotal(${editItemRowIndex}, 'edit_')">
                                <button type="button" class="stepper-btn" onclick="stepQty(${editItemRowIndex}, 1, 'edit_')" title="Tambah">+</button>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label-taste mb-1.5"><i class="fa-solid fa-tag text-success me-1"></i> TARIF INSENTIF</label>
                            <div class="taste-addon-input">
                                <span class="addon-label">Rp</span>
                                <input type="number" name="items[${editItemRowIndex}][insentif_per_unit]" id="inputInsentif_edit_${editItemRowIndex}" min="0" step="500" class="addon-input-field" value="${pInsentif}" placeholder="0" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <label class="form-label-taste mb-1.5 text-end d-block"><i class="fa-solid fa-coins text-warning me-1"></i> SUBTOTAL REWARD</label>
                            <div class="taste-subtotal-card">
                                <span class="text-xs text-muted font-weight-bold">Komisi:</span>
                                <span class="fw-bold text-success fs-6" id="subtotalInsentif_edit_${editItemRowIndex}">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rowHtml);
            const selEl = document.getElementById(`selectProduct_edit_${editItemRowIndex}`);
            if (pNama && selEl) {
                selEl.value = pNama;
                onSelectRowProduct(selEl, editItemRowIndex, 'edit_');
            } else {
                recalcRowSubtotal(editItemRowIndex, 'edit_');
            }
        }

        function hapusBarisBarangEdit(idx) {
            const el = document.getElementById(`editItemRow_${idx}`);
            if (el) el.remove();
            updateModalSummary('edit_');
        }

        function submitEditPenitipan(e) {
            e.preventDefault();
            const form = document.getElementById('formEditPenitipan');
            const formData = new FormData(form);
            formData.append('action', 'update_penitipan');

            const btn = document.getElementById('btnSimpanEditPenitipan');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan Perubahan...';
            }

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Perubahan';
                    }

                    if (res && res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Diperbarui!',
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal Menyimpan', html: (res && res.message) ? res.message : 'Gagal menyimpan data.' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Perubahan';
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan saat memperbarui data.' });
                });
        }

        // =========================================================================
        // HAPUS PENITIPAN BARANG
        // =========================================================================
        function hapusPenitipanFromBtn(btn) {
            const id = btn.getAttribute('data-id');
            const kode = btn.getAttribute('data-kode');
            const toko = btn.getAttribute('data-toko');
            hapusPenitipan(id, kode, toko);
        }

        function hapusPenitipan(idPenitipan, kodeTitip, namaToko) {
            const safeKode = kodeTitip || 'Penitipan';
            const safeToko = namaToko || 'Toko';
            Swal.fire({
                title: 'Hapus Data Penitipan?',
                html: `Apakah Anda yakin ingin menghapus data penitipan <strong>[${escapeHtml(safeKode)}]</strong> di toko <strong>${escapeHtml(safeToko)}</strong>?<br><br><span class="text-danger font-weight-bold">Perhatian: Seluruh data barang & histori log terkait akan dihapus secara permanen!</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Sekarang',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData();
                    formData.append('action', 'hapus_penitipan');
                    formData.append('id_penitipan', idPenitipan);

                    fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(res => {
                            if (res && res.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil Dihapus!',
                                    text: res.message,
                                    timer: 1800,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal Menghapus', html: (res && res.message) ? res.message : 'Gagal menghapus data.' });
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan saat menghapus data.' });
                        });
                }
            });
        }

        // =========================================================================
        // CEK SISA / LAPOR KUNJUNGAN
        // =========================================================================
        function openModalLaporKunjungan(idPenitipan) {
            document.getElementById('kunjunganIdPenitipan').value = idPenitipan;
            const tbody = document.getElementById('kunjunganItemsBody');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm text-dark"></div> Memuat barang...</td></tr>';

            showModalSafe('modalLaporKunjungan');

            fetch(`tiptok-ajax.php?action=get_detail&id=${idPenitipan}`)
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success') {
                        const m = res.data.master;
                        document.getElementById('kunjunganNamaToko').textContent = m.nama_toko || 'Toko Customer';
                        document.getElementById('kunjunganKodeTitip').textContent = m.kode_titip || '';
                        document.getElementById('kunjunganAlamatToko').textContent = (m.alamat_toko || '') + (m.kota_toko ? ', ' + m.kota_toko : '');

                        tbody.innerHTML = '';
                        if (!res.data.items || res.data.items.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted font-weight-bold">Tidak ada barang titipan terdaftar.</td></tr>';
                            return;
                        }
                        res.data.items.forEach((it, idx) => {
                            const sisaCur = parseInt(it.qty_sisa) || 0;
                            const insUnit = parseFloat(it.insentif_per_unit) || 0;
                            tbody.innerHTML += `
                                <tr>
                                    <td>
                                        <input type="hidden" name="items[${idx}][id_item]" value="${it.id}">
                                        <input type="hidden" id="insentifUnit_${idx}" value="${insUnit}">
                                        <div class="font-weight-bold" style="font-size: 14px; color: #020617;">${escapeHtml(it.nama_barang)}</div>
                                        <div style="font-size: 12px; font-weight: 700; color: #059669;"><i class="fa-solid fa-coins me-1"></i> Rp ${new Intl.NumberFormat('id-ID').format(insUnit)}/unit</div>
                                    </td>
                                    <td class="text-center font-weight-bold text-dark">
                                        <span class="taste-badge badge-neutral" style="font-size: 13px;">${sisaCur} unit</span>
                                    </td>
                                    <td>
                                        <input type="number" name="items[${idx}][stok_sisa]" 
                                               id="stokSisa_${idx}" 
                                               min="0" max="${sisaCur}" 
                                               class="form-control-taste w-100 text-center font-weight-bold text-dark" 
                                               style="font-size: 15px; border-radius: 10px;"
                                               value="${sisaCur}" 
                                               required 
                                               oninput="hitungTerjualRow(${idx}, ${sisaCur})">
                                    </td>
                                    <td class="text-end font-weight-bold" id="terjualDisplay_${idx}">
                                        <span class="taste-badge badge-neutral" style="font-size: 12px;">Stok Utuh</span>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger font-weight-bold">${(res && res.message) ? res.message : 'Gagal memuat data.'}</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger font-weight-bold">Terjadi kesalahan jaringan saat memuat data barang.</td></tr>';
                });
        }

        function hitungTerjualRow(idx, stokPrev) {
            const valInput = document.getElementById(`stokSisa_${idx}`).value;
            const sisa = parseInt(valInput) || 0;
            const terjual = Math.max(0, stokPrev - sisa);
            const insUnit = parseFloat(document.getElementById(`insentifUnit_${idx}`)?.value) || 0;
            const disp = document.getElementById(`terjualDisplay_${idx}`);

            if (disp) {
                if (terjual > 0) {
                    const estSubtotal = terjual * insUnit;
                    disp.innerHTML = `
                        <div class="d-flex flex-column align-items-end">
                            <span class="taste-badge badge-danger-tag mb-1" style="font-size: 12px;">+ ${terjual} Laku</span>
                            <span class="text-xs fw-bold text-success">+ Rp ${new Intl.NumberFormat('id-ID').format(estSubtotal)}</span>
                        </div>
                    `;
                } else {
                    disp.innerHTML = `<span class="taste-badge badge-neutral" style="font-size: 12px;">Stok Utuh</span>`;
                }
            }
        }

        function submitLaporKunjungan(e) {
            e.preventDefault();
            const form = document.getElementById('formLaporKunjungan');
            const formData = new FormData(form);
            formData.append('action', 'simpan_kunjungan');

            const btn = document.getElementById('btnSimpanKunjungan');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';
            }

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Laporan';
                    }

                    if (res && res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Laporan Tersimpan!',
                            html: res.message,
                            confirmButtonText: 'OK'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Validasi Gagal', html: (res && res.message) ? res.message : 'Gagal menyimpan laporan.' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Laporan';
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan saat menyimpan laporan.' });
                });
        }

        // =========================================================================
        // DETAIL PENITIPAN / HISTORI (PROFESIONAL & EXECUTIVE ENGINE)
        // =========================================================================
        let currentDetailData = null;

        function openModalDetailTiptok(idPenitipan) {
            document.getElementById('detailLoading').classList.remove('d-none');
            document.getElementById('detailContent').classList.add('d-none');
            showModalSafe('modalDetailTiptok');

            fetch(`tiptok-ajax.php?action=get_detail&id=${idPenitipan}`)
                .then(r => r.json())
                .then(res => {
                    document.getElementById('detailLoading').classList.add('d-none');
                    if (res && res.status === 'success') {
                        currentDetailData = res.data;
                        document.getElementById('detailContent').classList.remove('d-none');
                        const m = res.data.master;
                        const summary = res.data.summary || {};
                        const items = res.data.items || [];
                        const logs = res.data.logs || [];

                        // 1. Header & Badges
                        const kodeTitip = m.kode_titip || '-';
                        document.getElementById('detBadgeKode').textContent = kodeTitip;
                        const stBadge = document.getElementById('detBadgeStatus');
                        if (stBadge) {
                            if (m.status === 'selesai') {
                                stBadge.className = 'taste-badge badge-neutral';
                                stBadge.textContent = 'Selesai';
                            } else if (m.status === 'ditarik') {
                                stBadge.className = 'taste-badge badge-danger-tag';
                                stBadge.textContent = 'Ditarik';
                            } else {
                                stBadge.className = 'taste-badge badge-active-tag';
                                stBadge.textContent = 'Aktif';
                            }
                        }
                        document.getElementById('detailKodeTitip').textContent = `Tgl Titip: ${m.tgl_titip || '-'} • Sales: ${m.nama_sales || 'Sales'}`;

                        // 2. Executive KPI Cards
                        const sumTitip = parseInt(summary.total_titip) || 0;
                        const sumSisa = parseInt(summary.total_sisa) || 0;
                        const sumTerjual = parseInt(summary.total_terjual) || 0;
                        const sumInsentif = parseFloat(summary.total_insentif) || 0;

                        document.getElementById('detKpiTitip').textContent = sumTitip + ' Unit';
                        document.getElementById('detKpiSisa').textContent = sumSisa + ' Unit';
                        document.getElementById('detKpiTerjual').textContent = sumTerjual + ' Unit';
                        document.getElementById('detKpiInsentif').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(sumInsentif);

                        const sellRate = sumTitip > 0 ? Math.round((sumTerjual / sumTitip) * 100) : 0;
                        document.getElementById('detKpiSellRate').textContent = `${sellRate}% dari stok titipan`;

                        // 3. Store & Sales Profile Card
                        document.getElementById('detNamaToko').textContent = m.nama_toko || 'Toko Customer';
                        document.getElementById('detKategoriToko').textContent = m.kategori_customer || 'Dealer';
                        document.getElementById('detAlamatToko').textContent = (m.alamat_toko || '-') + (m.kota_toko ? ', ' + m.kota_toko : '');
                        
                        const telp = m.telp_toko || '';
                        const linkWa = document.getElementById('detLinkWa');
                        const spanTelp = document.getElementById('detTelpToko');
                        if (telp) {
                            spanTelp.textContent = telp;
                            let cleanPhone = telp.replace(/[^0-9]/g, '');
                            if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.substring(1);
                            linkWa.href = `https://wa.me/${cleanPhone}`;
                            linkWa.classList.remove('d-none');
                        } else {
                            spanTelp.textContent = 'Tidak ada telepon';
                            linkWa.removeAttribute('href');
                        }

                        const linkGmaps = document.getElementById('detLinkGmaps');
                        if (m.alamat_lokasi && m.alamat_lokasi.startsWith('http')) {
                            linkGmaps.href = m.alamat_lokasi;
                            linkGmaps.classList.remove('d-none');
                        } else {
                            const queryAddr = encodeURIComponent((m.nama_toko || '') + ' ' + (m.alamat_toko || '') + ' ' + (m.kota_toko || ''));
                            linkGmaps.href = `https://www.google.com/maps/search/?api=1&query=${queryAddr}`;
                            linkGmaps.classList.remove('d-none');
                        }

                        document.getElementById('detNamaSales').textContent = m.nama_sales || 'Sales In-Charge';
                        document.getElementById('detTglTitip').textContent = m.tgl_titip || '-';

                        const lastLog = logs.length > 0 ? logs[0].tgl_kunjungan : null;
                        document.getElementById('detLastAudit').textContent = lastLog ? lastLog : 'Belum pernah diaudit';

                        const catEl = document.getElementById('detCatatan');
                        const rowCat = document.getElementById('detRowCatatan');
                        if (m.catatan && m.catatan.trim() !== '') {
                            catEl.textContent = m.catatan;
                            if (rowCat) rowCat.classList.remove('d-none');
                        } else {
                            if (rowCat) rowCat.classList.add('d-none');
                        }

                        // 4. Rincian Stok Items Table
                        const itemBody = document.getElementById('detItemsBody');
                        const itemFoot = document.getElementById('detItemsFoot');
                        itemBody.innerHTML = '';
                        if (!items || items.length === 0) {
                            itemBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted font-weight-bold">Tidak ada barang titipan terdaftar.</td></tr>';
                            if (itemFoot) itemFoot.innerHTML = '';
                        } else {
                            items.forEach((it, idx) => {
                                const qTitip = parseInt(it.qty_titip) || 0;
                                const qSisa = parseInt(it.qty_sisa) || 0;
                                const qTerjual = parseInt(it.qty_terjual) || 0;
                                const insUnit = parseFloat(it.insentif_per_unit) || 0;
                                const totalIns = parseFloat(it.total_insentif) || 0;

                                let statusBadge = '';
                                if (qTerjual === 0) {
                                    statusBadge = `<span class="taste-badge badge-active-tag" style="font-size:12px;">100% Utuh</span>`;
                                } else if (qSisa === 0) {
                                    statusBadge = `<span class="taste-badge badge-danger-tag" style="font-size:12px;">Habis Terjual</span>`;
                                } else {
                                    statusBadge = `<span class="taste-badge badge-warning-tag" style="font-size:12px;">${qTerjual} Terjual</span>`;
                                }

                                itemBody.innerHTML += `
                                    <tr>
                                        <td class="font-weight-bold text-secondary text-center">${idx + 1}</td>
                                        <td>
                                            <div class="font-weight-bold" style="font-size: 14.5px; color: #020617;">${escapeHtml(it.nama_barang)}</div>
                                            <span class="text-xs text-muted font-weight-bold">${escapeHtml(it.tipe_barang || 'Perangkat Loewix')}</span>
                                        </td>
                                        <td class="text-center font-weight-bold text-dark" style="font-size: 14.5px;">${qTitip}</td>
                                        <td class="text-center font-weight-bold text-success" style="font-size: 15px;">${qSisa}</td>
                                        <td class="text-center font-weight-bold text-danger" style="font-size: 15px;">${qTerjual}</td>
                                        <td class="text-center">${statusBadge}</td>
                                        <td class="text-end font-weight-bold" style="font-size: 13.5px;">Rp ${new Intl.NumberFormat('id-ID').format(insUnit)}</td>
                                        <td class="text-end font-weight-bold text-success" style="font-size: 15px;">Rp ${new Intl.NumberFormat('id-ID').format(totalIns)}</td>
                                    </tr>
                                `;
                            });

                            if (itemFoot) {
                                itemFoot.innerHTML = `
                                    <tr>
                                        <td colspan="2" class="text-end text-dark font-weight-bolder py-3" style="font-size: 14px; letter-spacing: 0.05em;">TOTAL KESELURUHAN:</td>
                                        <td class="text-center font-weight-bolder text-dark" style="font-size: 15px;">${sumTitip}</td>
                                        <td class="text-center font-weight-bolder text-success" style="font-size: 15px;">${sumSisa}</td>
                                        <td class="text-center font-weight-bolder text-danger" style="font-size: 15px;">${sumTerjual}</td>
                                        <td class="text-center font-weight-bold text-muted">${sellRate}% Laku</td>
                                        <td class="text-end text-muted">-</td>
                                        <td class="text-end font-weight-bolder text-success" style="font-size: 16px;">Rp ${new Intl.NumberFormat('id-ID').format(sumInsentif)}</td>
                                    </tr>
                                `;
                            }
                        }

                        // 5. Riwayat Kunjungan Logs
                        const logBody = document.getElementById('detLogsBody');
                        const totKunjunganBadge = document.getElementById('detTotalKunjunganBadge');
                        if (totKunjunganBadge) totKunjunganBadge.textContent = `${logs.length} Kunjungan Terdata`;

                        logBody.innerHTML = '';
                        if (!logs || logs.length === 0) {
                            logBody.innerHTML = `
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-clipboard-list mb-2" style="font-size: 32px; color: #94a3b8; display: block;"></i>
                                        <div class="font-weight-bold text-dark text-base">Belum Ada Riwayat Kunjungan Audit</div>
                                        <p class="text-xs mb-3 text-muted">Lakukan kunjungan ke toko untuk memeriksa sisa stok fisik dan mencatat penjualan.</p>
                                        <button type="button" class="btn-taste-primary btn-sm" onclick="openCekSisaFromDetail()">
                                            <i class="fa-solid fa-plus me-1"></i> Input Kunjungan Pertama
                                        </button>
                                    </td>
                                </tr>
                            `;
                        } else {
                            logs.forEach(l => {
                                const invBadge = l.no_inv ? `<span class="taste-badge badge-invoice-tag">${escapeHtml(l.no_inv)}</span>` : '<span class="text-muted text-xs">-</span>';
                                const lakuVal = parseInt(l.qty_terjual_kunjungan) || 0;
                                const lakuDisplay = lakuVal > 0 ? `<span class="taste-badge badge-danger-tag">${lakuVal} Laku</span>` : `<span class="taste-badge badge-neutral">0</span>`;
                                const insVal = parseFloat(l.insentif_didapat) || 0;
                                
                                let fotoBtn = '<span class="text-muted text-xs">-</span>';
                                if (l.foto_kunjungan) {
                                    fotoBtn = `<a href="uploads/tiptok/${escapeHtml(l.foto_kunjungan)}" target="_blank" class="btn btn-sm btn-outline-dark py-0 px-2 font-weight-bold" style="font-size: 11px;"><i class="fa-solid fa-image me-1"></i> Foto</a>`;
                                }

                                logBody.innerHTML += `
                                    <tr>
                                        <td class="font-weight-bold text-dark">${escapeHtml(l.tgl_kunjungan)}</td>
                                        <td><strong>${escapeHtml(l.nama_sales || 'Sales')}</strong></td>
                                        <td><strong>${escapeHtml(l.nama_barang || 'Semua Barang')}</strong></td>
                                        <td class="text-center font-weight-bold text-success" style="font-size: 14.5px;">${l.stok_sisa}</td>
                                        <td class="text-center font-weight-bold">${lakuDisplay}</td>
                                        <td>${invBadge}</td>
                                        <td class="text-end font-weight-bold text-success">Rp ${new Intl.NumberFormat('id-ID').format(insVal)}</td>
                                        <td class="text-center">${fotoBtn}</td>
                                        <td class="text-sm text-secondary font-weight-bold">${escapeHtml(l.catatan_kunjungan || '-')}</td>
                                    </tr>
                                `;
                            });
                        }
                    } else {
                        document.getElementById('detailContent').innerHTML = `<div class="p-4 text-center text-danger font-weight-bold">${(res && res.message) ? res.message : 'Gagal memuat riwayat.'}</div>`;
                        document.getElementById('detailContent').classList.remove('d-none');
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('detailLoading').classList.add('d-none');
                    document.getElementById('detailContent').innerHTML = '<div class="p-4 text-center text-danger font-weight-bold">Terjadi kesalahan jaringan saat memuat detail.</div>';
                    document.getElementById('detailContent').classList.remove('d-none');
                });
        }

        function openCekSisaFromDetail() {
            if (!currentDetailData || !currentDetailData.master) return;
            const m = currentDetailData.master;
            hideModalSafe('modalDetailTiptok');
            setTimeout(() => {
                openModalLaporKunjungan(m.id);
            }, 300);
        }

        function openEditFromDetail() {
            if (!currentDetailData || !currentDetailData.master) return;
            const m = currentDetailData.master;
            hideModalSafe('modalDetailTiptok');
            setTimeout(() => {
                openModalEditPenitipan(m.id);
            }, 300);
        }

        function shareWhatsappDetail() {
            if (!currentDetailData || !currentDetailData.master) return;
            const m = currentDetailData.master;
            const items = currentDetailData.items || [];
            const summary = currentDetailData.summary || {};

            let text = `*SURAT PENITIPAN BARANG (TIP TOK)*\n`;
            text += `*LOEWIX CCTV & SECURITY SYSTEM*\n`;
            text += `====================================\n`;
            text += `*No. Dokumen:* ${m.kode_titip || '-'}\n`;
            text += `*Toko / Dealer:* ${m.nama_toko || '-'}\n`;
            text += `*Kategori:* ${m.kategori_customer || 'Dealer'}\n`;
            text += `*Alamat:* ${m.alamat_toko || '-'}${m.kota_toko ? ', ' + m.kota_toko : ''}\n`;
            text += `*Sales:* ${m.nama_sales || '-'}\n`;
            text += `*Tgl Titip:* ${m.tgl_titip || '-'}\n`;
            text += `*Status:* ${m.status ? m.status.toUpperCase() : 'AKTIF'}\n`;
            text += `====================================\n\n`;
            text += `*RINCIAN STOK BARANG:*\n`;

            items.forEach((it, i) => {
                text += `${i + 1}. *${it.nama_barang}* (${it.tipe_barang || 'CCTV'})\n`;
                text += `   - Titip Awal: ${it.qty_titip} unit\n`;
                text += `   - Sisa Stok: ${it.qty_sisa} unit\n`;
                text += `   - Terjual: ${it.qty_terjual} unit\n`;
                text += `   - Insentif: Rp ${new Intl.NumberFormat('id-ID').format(it.insentif_per_unit)}/unit\n\n`;
            });

            text += `*TOTAL REKAP:* \n`;
            text += `📦 Total Titip: ${summary.total_titip || 0} Unit\n`;
            text += `🟢 Sisa di Toko: ${summary.total_sisa || 0} Unit\n`;
            text += `🛒 Total Terjual: ${summary.total_terjual || 0} Unit\n`;
            text += `💰 Total Insentif: Rp ${new Intl.NumberFormat('id-ID').format(summary.total_insentif || 0)}\n`;
            text += `====================================\n`;
            text += `_Dokumen otomatis dari Loewix Sales System_`;

            let phone = m.telp_toko ? m.telp_toko.replace(/[^0-9]/g, '') : '';
            if (phone.startsWith('0')) phone = '62' + phone.substring(1);

            const waUrl = phone ? 
                `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(text)}` : 
                `https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`;

            window.open(waUrl, '_blank');
        }

        function cetakSuratJalanDetail() {
            if (!currentDetailData || !currentDetailData.master) return;
            const m = currentDetailData.master;
            const items = currentDetailData.items || [];
            const summary = currentDetailData.summary || {};

            let rowsHtml = '';
            items.forEach((it, idx) => {
                rowsHtml += `
                    <tr>
                        <td style="text-align: center; border: 1px solid #333; padding: 8px;">${idx + 1}</td>
                        <td style="border: 1px solid #333; padding: 8px;"><strong>${escapeHtml(it.nama_barang)}</strong><br><span style="font-size: 12px; color: #555;">${escapeHtml(it.tipe_barang || '-')}</span></td>
                        <td style="text-align: center; border: 1px solid #333; padding: 8px; font-weight: bold;">${it.qty_titip} Unit</td>
                        <td style="text-align: center; border: 1px solid #333; padding: 8px; font-weight: bold; color: green;">${it.qty_sisa} Unit</td>
                        <td style="text-align: center; border: 1px solid #333; padding: 8px; font-weight: bold; color: red;">${it.qty_terjual} Unit</td>
                        <td style="border: 1px solid #333; padding: 8px;">Kondisi Baik & Siap Display</td>
                    </tr>
                `;
            });

            const printHtml = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Surat Penitipan Barang - ${escapeHtml(m.kode_titip || 'TIPTOK')}</title>
                    <style>
                        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 30px; color: #111; font-size: 13.5px; line-height: 1.5; }
                        .header-table { width: 100%; border-bottom: 3px double #111; padding-bottom: 12px; margin-bottom: 20px; }
                        .title { text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.05em; }
                        .doc-no { text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 20px; color: #333; }
                        .meta-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
                        .meta-table td { padding: 4px 6px; vertical-align: top; }
                        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
                        .items-table th { background: #f1f5f9; border: 1px solid #333; padding: 8px; text-align: center; font-size: 13px; }
                        .sign-table { width: 100%; border-collapse: collapse; margin-top: 40px; page-break-inside: avoid; }
                        .sign-box { text-align: center; width: 33.33%; vertical-align: top; }
                        .sign-space { height: 75px; }
                        .terms { font-size: 11.5px; color: #444; border: 1px dashed #666; padding: 10px; margin-top: 20px; border-radius: 6px; }
                        @media print {
                            @page { margin: 15mm; size: portrait; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <table class="header-table">
                        <tr>
                            <td style="width: 65%;">
                                <h2 style="margin: 0; font-size: 22px; font-weight: 900; letter-spacing: 0.05em; color: #020617;">LOEWIX SECURITY SYSTEM</h2>
                                <div style="font-size: 12px; color: #334155; font-weight: 600;">PT. Giti Indonesia • CCTV, DVR, NVR & Security Solutions</div>
                                <div style="font-size: 11px; color: #64748b;">Layanan Resmi Konsinyasi & Manajemen Stok Toko (TIP TOK)</div>
                            </td>
                            <td style="width: 35%; text-align: right; vertical-align: middle;">
                                <div style="font-size: 11.5px; font-weight: bold; color: #020617;">TANGGAL CETAK:</div>
                                <div style="font-size: 13px; font-weight: bold;">${new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</div>
                            </td>
                        </tr>
                    </table>

                    <div class="title">SURAT PENITIPAN BARANG KONSINYASI</div>
                    <div class="doc-no">NOMOR DOKUMEN: ${escapeHtml(m.kode_titip || '-')}</div>

                    <table class="meta-table">
                        <tr>
                            <td style="width: 18%; font-weight: bold;">Toko / Dealer</td>
                            <td style="width: 2%;">:</td>
                            <td style="width: 38%; font-weight: bold;">${escapeHtml(m.nama_toko || '-')} (${escapeHtml(m.kategori_customer || 'Dealer')})</td>
                            <td style="width: 18%; font-weight: bold;">Sales Penyerah</td>
                            <td style="width: 2%;">:</td>
                            <td style="width: 22%; font-weight: bold;">${escapeHtml(m.nama_sales || '-')}</td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Alamat Toko</td>
                            <td>:</td>
                            <td>${escapeHtml(m.alamat_toko || '-')}${m.kota_toko ? ', ' + escapeHtml(m.kota_toko) : ''}</td>
                            <td style="font-weight: bold;">Tanggal Penitipan</td>
                            <td>:</td>
                            <td>${escapeHtml(m.tgl_titip || '-')}</td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Kontak / Telp</td>
                            <td>:</td>
                            <td>${escapeHtml(m.telp_toko || '-')}</td>
                            <td style="font-weight: bold;">Status Berkas</td>
                            <td>:</td>
                            <td><strong>${m.status ? m.status.toUpperCase() : 'AKTIF'}</strong></td>
                        </tr>
                    </table>

                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 6%;">NO</th>
                                <th style="width: 44%;">NAMA & DESKRIPSI BARANG</th>
                                <th style="width: 12%;">QTY TITIP</th>
                                <th style="width: 12%;">SISA STOK</th>
                                <th style="width: 12%;">TERJUAL</th>
                                <th style="width: 14%;">KETERANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                            <tr style="background: #f8fafc; font-weight: bold;">
                                <td colspan="2" style="text-align: right; border: 1px solid #333; padding: 8px;">TOTAL UNIT:</td>
                                <td style="text-align: center; border: 1px solid #333; padding: 8px; font-weight: bold;">${summary.total_titip || 0} Unit</td>
                                <td style="text-align: center; border: 1px solid #333; padding: 8px; font-weight: bold; color: green;">${summary.total_sisa || 0} Unit</td>
                                <td style="text-align: center; border: 1px solid #333; padding: 8px; font-weight: bold; color: red;">${summary.total_terjual || 0} Unit</td>
                                <td style="border: 1px solid #333; padding: 8px; text-align: center;">-</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="terms">
                        <strong>Syarat & Ketentuan Konsinyasi:</strong><br>
                        1. Barang di atas diserahkan dalam kondisi baru, lengkap, dan berfungsi normal untuk didisplay/dijual di toko penerima.<br>
                        2. Kepemilikan barang tetap berada pada PT. Giti Indonesia (Loewix) sampai barang tersebut terjual dan diterbitkan invoice resmi.<br>
                        3. Pihak toko berkewajiban merawat fisik unit dari kerusakan/kehilangan, dan bersedia diaudit stok fisik secara berkala oleh Sales Loewix.
                    </div>

                    <table class="sign-table">
                        <tr>
                            <td class="sign-box">
                                <strong>Yang Menyerahkan (Sales)</strong>
                                <div class="sign-space"></div>
                                <div>( <u>${escapeHtml(m.nama_sales || '........................')}</u> )</div>
                                <div style="font-size: 11px; color: #666;">Sales Representative</div>
                            </td>
                            <td class="sign-box">
                                <strong>Yang Menerima (Toko/Dealer)</strong>
                                <div class="sign-space"></div>
                                <div>( <u>${escapeHtml(m.nama_toko || '........................')}</u> )</div>
                                <div style="font-size: 11px; color: #666;">Cap & Tanda Tangan Toko</div>
                            </td>
                            <td class="sign-box">
                                <strong>Mengetahui (Admin Sales)</strong>
                                <div class="sign-space"></div>
                                <div>( <u>Loewix Management</u> )</div>
                                <div style="font-size: 11px; color: #666;">Admin / Head Office</div>
                            </td>
                        </tr>
                    </table>

                    <script>
                        window.onload = function() {
                            window.print();
                        };
                    <\/script>
                </body>
                </html>
            `;

            const printWin = window.open('', '_blank', 'width=900,height=750');
            if (printWin) {
                printWin.document.open();
                printWin.document.write(printHtml);
                printWin.document.close();
            }
        }

        // =========================================================================
        // KLAIM INSENTIF
        // =========================================================================
        function loadClaimSummary() {
            fetch('tiptok-ajax.php?action=get_claim_summary')
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success') {
                        const d = res.data;
                        const bodyUnclaimed = document.getElementById('bodyUnclaimedItems');
                        if (bodyUnclaimed) {
                            bodyUnclaimed.innerHTML = '';
                            if (!d.unclaimed_items || d.unclaimed_items.length === 0) {
                                bodyUnclaimed.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted font-weight-bold">Tidak ada unit terjual yang menunggu klaim.</td></tr>';
                            } else {
                                d.unclaimed_items.forEach(u => {
                                    bodyUnclaimed.innerHTML += `
                                        <tr>
                                            <td class="font-weight-bold">${u.tgl_kunjungan}</td>
                                            <td><strong>${escapeHtml(u.nama_toko)}</strong></td>
                                            <td><strong>${escapeHtml(u.nama_barang)}</strong></td>
                                            <td><span class="taste-badge badge-invoice-tag">${escapeHtml(u.no_inv || '-')}</span></td>
                                            <td class="text-center font-weight-bold text-danger" style="font-size: 15px;">${u.qty_terjual_kunjungan}</td>
                                            <td class="text-end font-weight-bold">Rp ${new Intl.NumberFormat('id-ID').format(u.insentif_per_unit)}</td>
                                            <td class="text-end font-weight-bold text-success" style="font-size: 15px;">Rp ${new Intl.NumberFormat('id-ID').format(u.insentif_didapat)}</td>
                                        </tr>
                                    `;
                                });
                            }
                        }

                        const bodyClaim = document.getElementById('bodyClaimHistory');
                        if (bodyClaim) {
                            bodyClaim.innerHTML = '';
                            if (!d.claim_history || d.claim_history.length === 0) {
                                bodyClaim.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted font-weight-bold">Belum ada riwayat pengajuan klaim.</td></tr>';
                            } else {
                                d.claim_history.forEach(c => {
                                    let stBadge = 'badge-neutral';
                                    if (c.status_claim === 'disetujui' || c.status_claim === 'cair') stBadge = 'badge-active-tag';
                                    else if (c.status_claim === 'menunggu_approval') stBadge = 'badge-invoice-tag';

                                    bodyClaim.innerHTML += `
                                        <tr>
                                            <td class="font-monospace font-weight-bold" style="font-size: 14px;">${escapeHtml(c.kode_claim)}</td>
                                            <td><strong>${escapeHtml(c.nama_sales)}</strong></td>
                                            <td class="font-weight-bold">${c.tgl_claim}</td>
                                            <td class="text-center font-weight-bold" style="font-size: 14.5px;">${c.total_unit_terjual} Unit</td>
                                            <td class="text-end font-weight-bold text-success" style="font-size: 15px;">Rp ${new Intl.NumberFormat('id-ID').format(c.total_nominal_insentif)}</td>
                                            <td class="text-center"><span class="taste-badge ${stBadge}">${escapeHtml((c.status_claim || '').toUpperCase())}</span></td>
                                            <td style="text-align: right;">
                                                <button type="button" class="btn-table-secondary" onclick="openModalDetailClaim(${c.id})">
                                                    <i class="fa-solid fa-eye me-1"></i> Rincian
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                            }
                        }
                    }
                })
                .catch(err => console.error('Error loading claim summary:', err));
        }

        function openModalSubmitClaim() {
            showModalSafe('modalSubmitClaim');
        }

        function submitKlaimInsentif(e) {
            e.preventDefault();
            const form = document.getElementById('formSubmitClaim');
            const formData = new FormData(form);
            formData.append('action', 'ajukan_claim');

            const btn = document.getElementById('btnProsesClaim');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Memproses...';
            }

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Kirim Pengajuan Klaim';
                    }

                    if (res && res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Klaim Diajukan!',
                            text: res.message,
                            confirmButtonText: 'OK'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', html: (res && res.message) ? res.message : 'Gagal mengajukan klaim.' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Kirim Pengajuan Klaim';
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan saat mengajukan klaim.' });
                });
        }

        function openModalDetailClaim(idClaim) {
            currentClaimId = idClaim;
            showModalSafe('modalClaimApproval');

            fetch(`tiptok-ajax.php?action=get_claim_detail&id_claim=${idClaim}`)
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success') {
                        const cl = res.data.claim;
                        document.getElementById('claimKodeTitle').textContent = 'Kode Klaim: ' + (cl.kode_claim || '-');
                        document.getElementById('claimSalesName').textContent = cl.nama_sales || '-';
                        document.getElementById('claimTgl').textContent = 'Tgl: ' + (cl.tgl_claim || '-') + ' (' + (cl.total_unit_terjual || 0) + ' Unit)';
                        document.getElementById('claimNominal').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(cl.total_nominal_insentif || 0);

                        const badge = document.getElementById('claimStatusBadge');
                        if (badge) {
                            badge.className = 'taste-badge ' + (cl.status_claim === 'cair' || cl.status_claim === 'disetujui' ? 'badge-active-tag' : 'badge-invoice-tag');
                            badge.textContent = (cl.status_claim || '').toUpperCase();
                        }

                        const selStatus = document.getElementById('updateClaimStatusSelect');
                        if (selStatus) selStatus.value = cl.status_claim;

                        const noteAdmin = document.getElementById('updateClaimAdminNote');
                        if (noteAdmin) noteAdmin.value = cl.catatan_admin || '';

                        const tbody = document.getElementById('claimDetailItemsBody');
                        if (tbody) {
                            tbody.innerHTML = '';
                            if (!res.data.details || res.data.details.length === 0) {
                                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Tidak ada rincian item.</td></tr>';
                            } else {
                                res.data.details.forEach(d => {
                                    tbody.innerHTML += `
                                        <tr>
                                            <td><strong>${escapeHtml(d.nama_toko)}</strong></td>
                                            <td><strong>${escapeHtml(d.nama_barang)}</strong></td>
                                            <td><span class="taste-badge badge-invoice-tag">${escapeHtml(d.no_inv || '-')}</span></td>
                                            <td class="text-center font-weight-bold" style="font-size: 14.5px;">${d.qty_terjual}</td>
                                            <td class="text-end font-weight-bold">Rp ${new Intl.NumberFormat('id-ID').format(d.insentif_per_unit)}</td>
                                            <td class="text-end font-weight-bold text-success" style="font-size: 15px;">Rp ${new Intl.NumberFormat('id-ID').format(d.subtotal_insentif)}</td>
                                        </tr>
                                    `;
                                });
                            }
                        }
                    }
                })
                .catch(err => console.error('Error loading claim detail:', err));
        }

        function submitUpdateClaimStatus() {
            const selEl = document.getElementById('updateClaimStatusSelect');
            const noteEl = document.getElementById('updateClaimAdminNote');
            const status = selEl ? selEl.value : '';
            const note = noteEl ? noteEl.value : '';

            const formData = new FormData();
            formData.append('action', 'update_status_claim');
            formData.append('id_claim', currentClaimId);
            formData.append('status_claim', status);
            formData.append('catatan_admin', note);

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Sukses', text: res.message, timer: 1500, showConfirmButton: false })
                            .then(() => {
                                hideModalSafe('modalClaimApproval');
                                loadClaimSummary();
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: (res && res.message) ? res.message : 'Gagal memperbarui status.' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan.' });
                });
        }
    </script>
</body>
</html>
