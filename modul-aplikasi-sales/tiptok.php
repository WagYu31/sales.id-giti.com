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

// Query Data Master Penitipan
$sqlPenitipan = "SELECT p.*, c.nama AS nama_toko, c.kategori AS kategori_customer, c.telp_pribadi AS telp_toko, 
                        c.alamat AS alamat_toko, c.kota AS kota_toko, c.alamat_lokasi,
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
$resPenitipan = $conn->query($sqlPenitipan);
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
           SENIOR-FRIENDLY & HIGH-CONTRAST DESIGN SYSTEM FOR TIP TOK
           ═════════════════════════════════════════════════════════ */
        :root {
            --bg-canvas: #f8fafc;
            --surface-card: #ffffff;
            --border-subtle: #cbd5e1;
            --border-hover: #94a3b8;
            --text-primary: #020617;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --accent-dark: #0f172a;
            --accent-blue: #1d4ed8;
            --accent-blue-light: #eff6ff;
            --accent-emerald: #047857;
            --accent-emerald-light: #ecfdf5;
            --accent-amber: #d97706;
            --accent-amber-light: #fffbeb;
            --accent-rose: #b91c1c;
            --accent-rose-light: #fee2e2;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-primary);
            letter-spacing: -0.01em;
        }

        /* ── Header Area ── */
        .page-header-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 20px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-subtle);
        }
        .page-eyebrow {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--accent-blue);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .page-title {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 900;
            color: var(--text-primary);
            letter-spacing: -0.02em;
            margin: 0;
            line-height: 1.2;
        }
        .page-subtitle {
            font-size: 14.5px;
            font-weight: 600;
            color: var(--text-secondary);
            margin: 6px 0 0 0;
        }

        /* ── Action Buttons ── */
        .btn-taste-primary {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: -0.01em;
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
            border-color: #334155;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.22);
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
            border-color: var(--border-hover);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        /* ── Bento Metrics Grid ── */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 1100px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .metrics-grid { grid-template-columns: 1fr; } }

        .metric-card {
            background: #ffffff;
            border: 2px solid var(--border-subtle);
            border-radius: 16px;
            padding: 20px 22px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .metric-card:hover {
            border-color: var(--border-hover);
            box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.12);
            transform: translateY(-2px);
        }
        .metric-label {
            font-size: 12.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .metric-value {
            font-family: 'Outfit', sans-serif;
            font-size: 34px;
            font-weight: 900;
            color: var(--text-primary);
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin-bottom: 8px;
        }
        .metric-sub {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }

        /* Segmented Control Filter Tabs */
        .segmented-control-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 20px;
        }
        .segmented-nav {
            background: #ffffff;
            padding: 4px;
            border-radius: 12px;
            display: inline-flex;
            flex-wrap: wrap;
            gap: 4px;
            border: 2px solid var(--border-subtle);
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
        }
        .segment-btn {
            border: none;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .segment-btn:hover {
            color: var(--text-primary);
            background: #f1f5f9;
        }
        .segment-btn.active {
            background: #0f172a;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
        }
        .segment-badge {
            font-size: 12px;
            font-weight: 800;
            background: #e2e8f0;
            color: #0f172a;
            padding: 2px 8px;
            border-radius: 6px;
        }
        .segment-btn.active .segment-badge {
            background: #334155;
            color: #ffffff;
        }

        /* Search Input */
        .search-container {
            position: relative;
            min-width: 300px;
            flex: 1;
            max-width: 400px;
        }
        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            color: #475569;
            pointer-events: none;
        }
        .search-input-refined {
            width: 100%;
            height: 46px;
            background: #ffffff;
            border: 2px solid var(--border-subtle);
            border-radius: 12px;
            padding: 8px 14px 8px 40px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .search-input-refined:focus {
            border-color: var(--accent-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
        }

        /* ── Data Surface Table ── */
        .data-card {
            background: #ffffff;
            border: 2px solid var(--border-subtle);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
            margin-bottom: 24px;
        }
        .taste-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }
        .taste-table th {
            background: #f1f5f9;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-subtle);
            white-space: nowrap;
        }
        .taste-table td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1.5px solid #e2e8f0;
            font-size: 14.5px;
            color: var(--text-primary);
        }
        .taste-table tr:last-child td {
            border-bottom: none;
        }
        .taste-table tr:hover td {
            background-color: #f8fafc;
        }

        /* Badges & Micro Chips */
        .taste-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 800;
            border: 1.5px solid transparent;
            line-height: 1.2;
        }
        .badge-neutral { background: #f1f5f9; color: var(--text-secondary); border-color: #cbd5e1; }
        .badge-dealer-tag { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; font-weight: 800; }
        .badge-active-tag { background: #dcfce7; color: #15803d; border-color: #86efac; }
        .badge-invoice-tag { background: #fef3c7; color: #92400e; border-color: #fcd34d; font-family: monospace; font-size: 13px; font-weight: 800; }
        .badge-danger-tag { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }

        /* Item Row Pill */
        .taste-item-pill {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 4px 0;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
            transition: border-color 0.15s ease;
        }
        .taste-item-pill:hover {
            border-color: #cbd5e1;
        }

        /* Table Action Buttons */
        .btn-table-primary {
            background: #0f172a;
            color: #ffffff;
            border: 1.5px solid #0f172a;
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.12);
            cursor: pointer;
            text-decoration: none;
        }
        .btn-table-primary:hover {
            background: #1e293b;
            color: #ffffff;
            border-color: #1e293b;
            transform: translateY(-1px);
        }
        .btn-table-secondary {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1.5px solid #bfdbfe;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.15s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-table-secondary:hover {
            background: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
            transform: translateY(-1px);
        }
        .btn-table-warning {
            background: #fffbeb;
            color: #d97706;
            border: 1.5px solid #fcd34d;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.15s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-table-warning:hover {
            background: #fef3c7;
            color: #b45309;
            border-color: #f59e0b;
            transform: translateY(-1px);
        }
        .btn-table-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1.5px solid #fca5a5;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.15s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-table-danger:hover {
            background: #fecaca;
            color: #991b1b;
            border-color: #ef4444;
            transform: translateY(-1px);
        }

        /* Modal Styles */
        .modal-taste .modal-content {
            border-radius: 18px;
            border: 2px solid var(--border-subtle);
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
            overflow: hidden;
        }
        .modal-taste .modal-header {
            padding: 18px 24px;
            border-bottom: 2px solid var(--border-subtle);
            background: #f8fafc;
        }
        .modal-taste .modal-title {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #020617;
        }
        .form-label-taste {
            font-size: 13.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #1e293b;
            margin-bottom: 6px;
            display: block;
        }
        .form-control-taste {
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14.5px;
            font-weight: 600;
            color: #0f172a;
            transition: border-color 0.15s, box-shadow 0.15s;
            background-color: #ffffff;
        }
        .form-control-taste:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
            outline: none;
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-200">

    <?php include "cek-menu.php"; ?>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <?php include "nav-top.php"; ?>

        <div class="container-fluid py-4 px-4">

            <!-- 1. Refined Senior-Friendly Page Header -->
            <div class="page-header-container">
                <div>
                    <div class="page-eyebrow">
                        <i class="fa-solid fa-boxes-packing text-primary"></i> APLIKASI SALES / KONSINYASI
                    </div>
                    <h1 class="page-title">TIP TOK (Titip Barang Di Toko)</h1>
                    <p class="page-subtitle">
                        Manajemen penitipan stok toko dealer, laporan sisa fisik kunjungan, dan klaim insentif min. 50 unit.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn-taste-secondary" onclick="openTabKlaimInsentif()">
                        <i class="fa-solid fa-hand-holding-dollar text-warning" style="font-size: 16px;"></i> Klaim Insentif
                    </button>
                    <button class="btn-taste-primary" onclick="openModalTambahPenitipan()">
                        <i class="fa-solid fa-plus" style="font-size: 16px;"></i> Titip Barang Baru
                    </button>
                </div>
            </div>

            <!-- 2. High-Contrast Bento Metrics Grid -->
            <div class="metrics-grid">
                <!-- Metric 1: Toko Aktif -->
                <div class="metric-card">
                    <div class="metric-label">
                        <span>Toko Dealer Aktif</span>
                        <div style="background:#eff6ff; color:#1d4ed8; width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; border: 1.5px solid #bfdbfe;">
                            <i class="fa-solid fa-store" style="font-size:16px;"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo number_format($totalTokoAktif, 0, ',', '.'); ?></div>
                    <div class="metric-sub">
                        <span class="taste-badge badge-active-tag" style="font-size:12px; padding:2px 8px;"><i class="fa-solid fa-circle text-xxs me-1"></i> Aktif</span>
                        <span style="font-weight: 700; color: #334155;">dengan stok konsinyasi</span>
                    </div>
                </div>

                <!-- Metric 2: Sisa Stok di Toko -->
                <div class="metric-card">
                    <div class="metric-label">
                        <span>Sisa Stok di Toko</span>
                        <div style="background:#ecfdf5; color:#047857; width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; border: 1.5px solid #a7f3d0;">
                            <i class="fa-solid fa-boxes-stacked" style="font-size:16px;"></i>
                        </div>
                    </div>
                    <div class="metric-value" style="color: #047857;">
                        <?php echo number_format($totalUnitSisa, 0, ',', '.'); ?> 
                        <span style="font-size: 16px; font-weight: 700; color: #64748b;">/ <?php echo number_format($totalUnitTitip, 0, ',', '.'); ?> unit</span>
                    </div>
                    <div class="metric-sub">
                        Terjual: <strong class="text-danger" style="font-weight: 800; font-size: 14.5px;"><?php echo number_format($totalUnitTerjual, 0, ',', '.'); ?> unit</strong>
                    </div>
                </div>

                <!-- Metric 3: Akumulasi Insentif -->
                <div class="metric-card">
                    <div class="metric-label">
                        <span>Akumulasi Insentif</span>
                        <div style="background:#fef3c7; color:#d97706; width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; border: 1.5px solid #fcd34d;">
                            <i class="fa-solid fa-coins" style="font-size:16px;"></i>
                        </div>
                    </div>
                    <div class="metric-value" style="font-size: 28px; color: #047857;">
                        Rp <?php echo number_format($totalInsentifPool, 0, ',', '.'); ?>
                    </div>
                    <div class="metric-sub">
                        Dari total unit yang telah terjual
                    </div>
                </div>

                <!-- Metric 4: Target Klaim 50 Unit -->
                <div class="metric-card">
                    <div class="metric-label">
                        <span>Target Klaim (Min. 50 Unit)</span>
                        <span class="taste-badge <?php echo $isClaimEligible ? 'badge-active-tag' : 'badge-neutral'; ?>" style="font-size: 12px;">
                            <?php echo $isClaimEligible ? 'SIAP KLAIM' : 'PROSES'; ?>
                        </span>
                    </div>
                    <div class="metric-value">
                        <?php echo $unclaimedUnits; ?> 
                        <span style="font-size: 16px; font-weight: 700; color: #64748b;">/ 50 unit</span>
                    </div>
                    <div class="progress mt-2 mb-1" style="height: 8px; background-color: #e2e8f0; border-radius: 10px;">
                        <div class="progress-bar" style="width: <?php echo $claimProgress; ?>%; background: <?php echo $isClaimEligible ? '#047857' : 'linear-gradient(90deg, #1d4ed8, #2563eb)'; ?>; border-radius: 10px;"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size: 12.5px; font-weight: 700; color: #475569;">
                        <span><?php echo $claimProgress; ?>% tercapai</span>
                        <span style="color: <?php echo $isClaimEligible ? '#047857' : '#b91c1c'; ?>;"><?php echo $isClaimEligible ? 'Target tercapai' : "Kurang $sisaTarget unit"; ?></span>
                    </div>
                </div>
            </div>

            <!-- 3. Segmented Controls & Search Bar -->
            <div class="segmented-control-container">
                <div class="segmented-nav">
                    <button class="segment-btn active" onclick="filterTable('all', this)">
                        Semua <span class="segment-badge" id="badgeCountAll">0</span>
                    </button>
                    <button class="segment-btn" onclick="filterTable('aktif', this)">
                        Stok Aktif <span class="segment-badge" id="badgeCountAktif">0</span>
                    </button>
                    <button class="segment-btn" onclick="filterTable('terjual', this)">
                        Ada Penjualan <span class="segment-badge" id="badgeCountTerjual">0</span>
                    </button>
                    <button class="segment-btn" onclick="filterTable('selesai', this)">
                        Selesai <span class="segment-badge" id="badgeCountSelesai">0</span>
                    </button>
                    <button class="segment-btn" onclick="switchViewToClaims()">
                        <i class="fa-solid fa-receipt text-warning"></i> Tab Klaim Insentif
                    </button>
                </div>

                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="tiptokSearchInput" class="search-input-refined" placeholder="Cari toko, barang, invoice..." onkeyup="searchTiptokTable()">
                </div>
            </div>

            <!-- 4. Main Data Table -->
            <div id="viewPenitipanTable" class="data-card">
                <div class="table-responsive">
                    <table class="table taste-table" id="mainTiptokTable">
                        <thead>
                            <tr>
                                <th style="width: 4%;">#</th>
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
                                        <td class="text-center font-weight-bold" style="font-size: 14px; color: #64748b;"><?php echo $no++; ?></td>
                                        
                                        <!-- Toko -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="font-weight-bold" style="font-size: 16px; color: #020617; line-height: 1.3;"><?php echo htmlspecialchars($row['nama_toko'] ?? 'Toko Tidak Ditemukan'); ?></span>
                                                <span class="taste-badge badge-dealer-tag"><?php echo htmlspecialchars($row['kategori_customer'] ?? 'Dealer'); ?></span>
                                            </div>
                                            <div style="font-size: 13.5px; font-weight: 600; color: #334155; margin-top: 4px; line-height: 1.4;">
                                                <?php echo htmlspecialchars($row['alamat_toko'] ?? '-'); ?><?php echo !empty($row['kota_toko']) ? ', ' . htmlspecialchars($row['kota_toko']) : ''; ?>
                                            </div>
                                            <?php if (!empty($telpRaw)) : ?>
                                                <a href="https://wa.me/<?php echo $telpRaw; ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; background: #ecfdf5; color: #047857; border: 1.5px solid #a7f3d0; padding: 4px 10px; border-radius: 8px; font-size: 13px; font-weight: 800; text-decoration: none; margin-top: 6px;">
                                                    <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($row['telp_toko']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Kode & Tanggal -->
                                        <td>
                                            <div class="font-monospace" style="font-size: 13.5px; font-weight: 800; color: #020617; background: #f1f5f9; padding: 3px 8px; border-radius: 6px; border: 1.5px solid #cbd5e1; display: inline-block;">
                                                <?php echo htmlspecialchars($row['kode_titip']); ?>
                                            </div>
                                            <div style="font-size: 13.5px; font-weight: 700; color: #334155; margin-top: 4px;">
                                                <i class="fa-regular fa-calendar me-1 text-primary"></i><?php echo date('d M Y', strtotime($row['tgl_titip'])); ?>
                                            </div>
                                            <div style="font-size: 13px; font-weight: 600; color: #475569; margin-top: 2px;">
                                                Sales: <strong style="color: #020617; font-weight: 800;"><?php echo htmlspecialchars($row['nama_sales'] ?? 'Sales'); ?></strong>
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
                                                        <span class="font-weight-bold" style="font-size: 14px; color: #020617;"><?php echo htmlspecialchars($it['nama_barang']); ?></span>
                                                        <div class="d-flex align-items-center gap-2 ms-auto">
                                                            <span style="font-size: 13px; font-weight: 700; color: #475569;">
                                                                Sisa: <strong class="text-success" style="font-size: 13.5px; font-weight: 900; background: #dcfce7; border: 1.5px solid #86efac; padding: 2px 8px; border-radius: 6px;"><?php echo $sisa; ?></strong>
                                                            </span>
                                                            <?php if ($terjual > 0) : ?>
                                                                <span class="text-danger" style="font-size: 13px; font-weight: 900; background: #fee2e2; border: 1.5px solid #fca5a5; padding: 2px 8px; border-radius: 6px;">
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
                                            <div style="font-family: 'Outfit', sans-serif; font-size: 17px; font-weight: 900; color: #047857; line-height: 1.2;">
                                                Rp <?php echo number_format($sumInsentif, 0, ',', '.'); ?>
                                            </div>
                                            <div style="font-size: 13px; font-weight: 700; color: #475569; margin-top: 2px;">
                                                (Terjual: <?php echo $sumTerjual; ?> unit)
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
                                        <td style="text-align: right;">
                                            <div class="d-inline-flex gap-1.5 align-items-center">
                                                <?php if ($statusPen === 'aktif' && $sumSisa > 0) : ?>
                                                    <button class="btn-table-primary" onclick="openModalLaporKunjungan(<?php echo $idPen; ?>)" title="Lapor Kunjungan / Cek Stok Sisa">
                                                        <i class="fa-solid fa-check"></i> Cek Sisa
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-table-secondary" onclick="openModalDetailTiptok(<?php echo $idPen; ?>)" title="Lihat Riwayat Lengkap">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                                <button class="btn-table-warning" onclick="openModalEditPenitipan(<?php echo $idPen; ?>)" title="Edit Data Penitipan">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button class="btn-table-danger" onclick="hapusPenitipan(<?php echo $idPen; ?>, '<?php echo htmlspecialchars($row['kode_titip']); ?>', '<?php echo htmlspecialchars(addslashes($row['nama_toko'] ?? '')); ?>')" title="Hapus Penitipan">
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
            <div id="viewKlaimInsentif" class="data-card d-none p-4">
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
                        <div class="metric-card h-100">
                            <div class="metric-label">
                                <span>Unit Terjual Siap Klaim</span>
                                <span class="taste-badge <?php echo $isClaimEligible ? 'badge-active-tag' : 'badge-neutral'; ?>" style="font-size: 12.5px;">
                                    <?php echo $isClaimEligible ? 'SYARAT TERPENUHI (>= 50)' : 'BELUM MEMENUHI (< 50)'; ?>
                                </span>
                            </div>
                            <div class="metric-value"><?php echo $unclaimedUnits; ?> <span style="font-size: 16px; font-weight: 700; color: #64748b;">/ 50 unit minimal</span></div>
                            <div class="progress my-2" style="height: 8px; background-color: #e2e8f0; border-radius: 10px;">
                                <div class="progress-bar" style="width: <?php echo $claimProgress; ?>%; background: #047857; border-radius: 10px;"></div>
                            </div>
                            <div class="text-sm font-weight-bold">
                                <?php if ($isClaimEligible) : ?>
                                    <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i>Syarat 50 unit terpenuhi. Anda siap mengajukan klaim insentif.</span>
                                <?php else : ?>
                                    <span class="text-danger"><i class="fa-solid fa-circle-info me-1"></i>Perlu <?php echo $sisaTarget; ?> unit lagi untuk dapat mengajukan klaim insentif.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="metric-card h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="metric-label">Total Nominal Insentif Siap Cair</div>
                                <div class="metric-value text-success" style="font-size: 32px;">
                                    Rp <?php echo number_format($unclaimedNominal, 0, ',', '.'); ?>
                                </div>
                                <p class="text-sm font-weight-bold text-secondary mb-0">Total akumulasi dari unit barang yang terjual dengan No. Invoice valid.</p>
                            </div>
                            <div class="mt-3">
                                <?php if ($isClaimEligible) : ?>
                                    <button class="btn-taste-primary w-100 justify-content-center" onclick="openModalSubmitClaim()">
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
                <div class="table-responsive border rounded-3 mb-4" style="border: 2px solid #cbd5e1 !important;">
                    <table class="table taste-table mb-0" id="tableUnclaimedItems">
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
                <div class="table-responsive border rounded-3" style="border: 2px solid #cbd5e1 !important;">
                    <table class="table taste-table mb-0" id="tableClaimHistory">
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
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">Titip Barang Baru di Toko</h5>
                        <div class="text-secondary text-sm font-weight-bold">Pilih toko dealer dan input daftar barang yang dititipkan</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formTambahPenitipan" onsubmit="submitTambahPenitipan(event)">
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label-taste">Toko / Dealer Tujuan <span class="text-danger">*</span></label>
                                <select name="id_customer" id="selectDealer" class="form-control-taste w-100" required onchange="onDealerSelected()">
                                    <option value="">-- Pilih Toko Customer --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-taste">Tanggal Titip <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_titip" class="form-control-taste w-100" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div id="dealerPreview" class="p-3 mb-3 rounded-3 bg-light border d-none" style="border: 2px solid #cbd5e1 !important;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-weight-bold text-dark text-base" id="prevNamaToko">-</span>
                                <span class="taste-badge badge-dealer-tag" id="prevKategoriToko">Dealer</span>
                            </div>
                            <div class="text-sm text-secondary font-weight-bold mt-1" id="prevAlamatToko">-</div>
                            <div class="text-sm text-success font-weight-bold mt-1" id="prevTelpToko">-</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
                            <label class="form-label-taste mb-0">Daftar Barang Dititipkan <span class="text-danger">*</span></label>
                            <button type="button" class="btn-taste-secondary btn-sm py-1" onclick="tambahBarisBarang()">
                                <i class="fa-solid fa-plus me-1"></i> Tambah Baris
                            </button>
                        </div>

                        <div id="containerItemRows"></div>

                        <div class="mt-3">
                            <label class="form-label-taste">Catatan Penitipan (Opsional)</label>
                            <textarea name="catatan" class="form-control-taste w-100" rows="2" placeholder="Catatan perjanjian penitipan stok..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-light border-top">
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
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">Edit Data Penitipan Barang</h5>
                        <div class="text-secondary text-sm font-weight-bold" id="editModalSubtitle">Perbarui data toko, tanggal, atau daftar barang titipan</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formEditPenitipan" onsubmit="submitEditPenitipan(event)">
                    <input type="hidden" name="id_penitipan" id="editIdPenitipan">
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label-taste">Toko / Dealer Tujuan <span class="text-danger">*</span></label>
                                <select name="id_customer" id="editSelectDealer" class="form-control-taste w-100" required onchange="onEditDealerSelected()">
                                    <option value="">-- Pilih Toko Customer --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-taste">Tanggal Titip <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_titip" id="editTglTitip" class="form-control-taste w-100" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-taste">Status Penitipan <span class="text-danger">*</span></label>
                                <select name="status" id="editStatusPenitipan" class="form-control-taste w-100" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="selesai">Selesai</option>
                                    <option value="ditarik">Ditarik</option>
                                </select>
                            </div>
                        </div>

                        <div id="editDealerPreview" class="p-3 mb-3 rounded-3 bg-light border d-none" style="border: 2px solid #cbd5e1 !important;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-weight-bold text-dark text-base" id="editPrevNamaToko">-</span>
                                <span class="taste-badge badge-dealer-tag" id="editPrevKategoriToko">Dealer</span>
                            </div>
                            <div class="text-sm text-secondary font-weight-bold mt-1" id="editPrevAlamatToko">-</div>
                            <div class="text-sm text-success font-weight-bold mt-1" id="editPrevTelpToko">-</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
                            <label class="form-label-taste mb-0">Daftar Barang Dititipkan <span class="text-danger">*</span></label>
                            <button type="button" class="btn-taste-secondary btn-sm py-1" onclick="tambahBarisBarangEdit()">
                                <i class="fa-solid fa-plus me-1"></i> Tambah Baris
                            </button>
                        </div>

                        <div id="editContainerItemRows"></div>

                        <div class="mt-3">
                            <label class="form-label-taste">Catatan Penitipan (Opsional)</label>
                            <textarea name="catatan" id="editCatatan" class="form-control-taste w-100" rows="2" placeholder="Catatan perjanjian penitipan stok..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-light border-top">
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
    <!-- MODAL 2: LAPORAN KUNJUNGAN & CEK STOK SISA                                -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalLaporKunjungan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">Laporan Kunjungan & Cek Sisa Stok</h5>
                        <div class="text-secondary text-sm font-weight-bold">Input kondisi sisa fisik barang di toko. Wajib No. Invoice jika ada yang laku!</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formLaporKunjungan" onsubmit="submitLaporKunjungan(event)" enctype="multipart/form-data">
                    <input type="hidden" name="id_penitipan" id="kunjunganIdPenitipan">
                    <div class="modal-body p-4">
                        <div class="p-3 mb-3 rounded-3 bg-light border" style="border: 2px solid #cbd5e1 !important;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-weight-bold text-dark text-base" id="kunjunganNamaToko">-</span>
                                <span class="font-monospace text-sm font-weight-bold text-primary" id="kunjunganKodeTitip">-</span>
                            </div>
                            <div class="text-sm text-secondary font-weight-bold mt-1" id="kunjunganAlamatToko">-</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label-taste">Tanggal Kunjungan <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_kunjungan" class="form-control-taste w-100" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-taste">Foto Bukti Display / Stok (Opsional)</label>
                                <input type="file" name="foto_kunjungan" class="form-control-taste w-100" accept="image/*">
                            </div>
                        </div>

                        <div class="form-label-taste mt-3 mb-2">Audit Fisik Stok Sisa & Penjualan</div>
                        <div class="table-responsive border rounded-3 mb-3" style="border: 2px solid #cbd5e1 !important;">
                            <table class="table taste-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 32%;">NAMA BARANG</th>
                                        <th style="width: 15%; text-align: center;">STOK LALU</th>
                                        <th style="width: 18%;">SISA FISIK <span class="text-danger">*</span></th>
                                        <th style="width: 12%; text-align: center;">TERJUAL</th>
                                        <th style="width: 23%;">NO. INVOICE <span class="text-danger">*</span></th>
                                    </tr>
                                </thead>
                                <tbody id="kunjunganItemsBody"></tbody>
                            </table>
                        </div>

                        <div class="p-3 rounded-3 bg-light border text-sm font-weight-bold text-secondary mb-3 d-flex align-items-center gap-2" style="border: 1.5px solid #cbd5e1 !important;">
                            <i class="fa-solid fa-circle-info text-primary" style="font-size: 16px;"></i>
                            <span>Jika ada barang terjual (Sisa < Stok Lalu), kolom <strong>Nomor Invoice</strong> wajib diisi untuk verifikasi klaim insentif.</span>
                        </div>

                        <div>
                            <label class="form-label-taste">Catatan Kunjungan</label>
                            <textarea name="catatan_kunjungan" class="form-control-taste w-100" rows="2" placeholder="Catatan hasil audit toko..."></textarea>
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
    <!-- MODAL 3: DETAIL LENGKAP & RIWAYAT KUNJUNGAN TOKO                          -->
    <!-- ========================================================================= -->
    <div class="modal fade modal-taste" id="modalDetailTiptok" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">Detail Konsinyasi & Histori Audit</h5>
                        <div class="text-secondary text-sm font-weight-bold" id="detailKodeTitip">-</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div id="detailLoading" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-dark" role="status"></div>
                        <p class="text-muted text-sm mt-2 font-weight-bold">Memuat riwayat konsinyasi...</p>
                    </div>

                    <div id="detailContent" class="d-none">
                        <div class="p-3 mb-4 rounded-3 bg-light border" style="border: 2px solid #cbd5e1 !important;">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="font-weight-bold text-dark mb-1" id="detNamaToko">-</h5>
                                    <div class="text-sm text-secondary font-weight-bold" id="detAlamatToko">-</div>
                                    <div class="text-sm text-success font-weight-bold mt-1" id="detTelpToko">-</div>
                                </div>
                                <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                    <span class="taste-badge badge-neutral font-monospace font-weight-bold" id="detKodeTitip">-</span>
                                    <div class="text-sm text-secondary font-weight-bold mt-1">Tgl Titip: <strong id="detTglTitip" class="text-dark">-</strong></div>
                                    <div class="text-sm text-secondary font-weight-bold">Sales: <strong id="detNamaSales" class="text-dark">-</strong></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-label-taste mb-2">Rincian Stok Barang</div>
                        <div class="table-responsive border rounded-3 mb-4" style="border: 2px solid #cbd5e1 !important;">
                            <table class="table taste-table mb-0">
                                <thead>
                                    <tr>
                                        <th>NAMA BARANG</th>
                                        <th class="text-center">STOK AWAL</th>
                                        <th class="text-center">SISA STOK</th>
                                        <th class="text-center">TERJUAL</th>
                                        <th class="text-end">INSENTIF / UNIT</th>
                                        <th class="text-end">TOTAL INSENTIF</th>
                                    </tr>
                                </thead>
                                <tbody id="detItemsBody"></tbody>
                            </table>
                        </div>

                        <div class="form-label-taste mb-2">Riwayat Kunjungan & Laporan Sisa Stok</div>
                        <div class="table-responsive border rounded-3" style="border: 2px solid #cbd5e1 !important;">
                            <table class="table taste-table mb-0">
                                <thead>
                                    <tr>
                                        <th>TGL KUNJUNGAN</th>
                                        <th>SALES</th>
                                        <th>BARANG DIAUDIT</th>
                                        <th class="text-center">SISA FISIK</th>
                                        <th class="text-center">TERJUAL</th>
                                        <th>NO. INVOICE</th>
                                        <th class="text-end">INSENTIF</th>
                                        <th>CATATAN</th>
                                    </tr>
                                </thead>
                                <tbody id="detLogsBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer p-3 bg-light border-top">
                    <button type="button" class="btn-taste-secondary" data-bs-dismiss="modal">Tutup</button>
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

    <!-- JavaScript & Logic -->
    <script>
        let dealersList = [];
        let currentLoadedPenitipan = null;
        let currentClaimId = 0;
        let editItemRowIndex = 0;

        document.addEventListener('DOMContentLoaded', function() {
            updateBadgeCounts();
            loadDealers();
            tambahBarisBarang();
        });

        function updateBadgeCounts() {
            document.getElementById('badgeCountAll').textContent = '<?php echo $countAll; ?>';
            document.getElementById('badgeCountAktif').textContent = '<?php echo $countAktif; ?>';
            document.getElementById('badgeCountTerjual').textContent = '<?php echo $countTerjual; ?>';
            document.getElementById('badgeCountSelesai').textContent = '<?php echo $countSelesai; ?>';
        }

        function filterTable(category, btn) {
            document.querySelectorAll('.segment-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');

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
            const query = document.getElementById('tiptokSearchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#mainTiptokTable tbody tr.tiptok-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function switchViewToClaims() {
            document.getElementById('viewPenitipanTable').classList.add('d-none');
            document.getElementById('viewKlaimInsentif').classList.remove('d-none');
            loadClaimSummary();
        }

        function switchViewToTable() {
            document.getElementById('viewKlaimInsentif').classList.add('d-none');
            document.getElementById('viewPenitipanTable').classList.remove('d-none');
        }

        function openTabKlaimInsentif() {
            switchViewToClaims();
        }

        function loadDealers() {
            fetch('tiptok-ajax.php?action=search_dealer')
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        dealersList = res.data;
                        const sel = document.getElementById('selectDealer');
                        sel.innerHTML = '<option value="">-- Pilih Toko Customer / Dealer --</option>';
                        dealersList.forEach(d => {
                            const katBadge = d.kategori ? `[${d.kategori}] ` : '';
                            sel.innerHTML += `<option value="${d.id}">${katBadge}${d.nama} - ${d.kota || ''}</option>`;
                        });
                    }
                });
        }

        function onDealerSelected() {
            const id = document.getElementById('selectDealer').value;
            const dealer = dealersList.find(d => d.id == id);
            const prev = document.getElementById('dealerPreview');
            if (dealer) {
                document.getElementById('prevNamaToko').textContent = dealer.nama;
                document.getElementById('prevKategoriToko').textContent = dealer.kategori || 'Dealer';
                document.getElementById('prevAlamatToko').textContent = (dealer.alamat || '') + (dealer.kota ? ', ' + dealer.kota : '');
                document.getElementById('prevTelpToko').textContent = dealer.telp_pribadi ? 'WA / Telp: ' + dealer.telp_pribadi : '';
                prev.classList.remove('d-none');
            } else {
                prev.classList.add('d-none');
            }
        }

        let itemRowIndex = 0;
        function tambahBarisBarang() {
            itemRowIndex++;
            const container = document.getElementById('containerItemRows');
            const rowHtml = `
                <div class="p-3 mb-2 rounded-3 bg-light border" id="itemRow_${itemRowIndex}" style="border: 2px solid #cbd5e1 !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="taste-badge badge-neutral" style="font-size: 13px;">Item #${itemRowIndex}</span>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 mb-0 font-weight-bold" onclick="hapusBarisBarang(${itemRowIndex})">
                            <i class="fa-solid fa-trash-can me-1"></i> Hapus
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label-taste mb-1">NAMA BARANG <span class="text-danger">*</span></label>
                            <input type="text" name="items[${itemRowIndex}][nama_barang]" class="form-control-taste w-100" placeholder="CCTV Loewix 2MP Outdoor" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-taste mb-1">TIPE / KATEGORI</label>
                            <input type="text" name="items[${itemRowIndex}][tipe_barang]" class="form-control-taste w-100" placeholder="CCTV / NVR">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-taste mb-1">QTY TITIP <span class="text-danger">*</span></label>
                            <input type="number" name="items[${itemRowIndex}][qty_titip]" min="1" class="form-control-taste w-100 text-center" placeholder="Jml" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-taste mb-1">INSENTIF / UNIT (RP) <span class="text-danger">*</span></label>
                            <input type="number" name="items[${itemRowIndex}][insentif_per_unit]" min="0" step="500" class="form-control-taste w-100 text-end" placeholder="15000" required>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rowHtml);
        }

        function hapusBarisBarang(idx) {
            const el = document.getElementById(`itemRow_${idx}`);
            if (el) el.remove();
        }

        function openModalTambahPenitipan() {
            document.getElementById('formTambahPenitipan').reset();
            document.getElementById('dealerPreview').classList.add('d-none');
            document.getElementById('containerItemRows').innerHTML = '';
            itemRowIndex = 0;
            tambahBarisBarang();
            new bootstrap.Modal(document.getElementById('modalTambahPenitipan')).show();
        }

        function submitTambahPenitipan(e) {
            e.preventDefault();
            const form = document.getElementById('formTambahPenitipan');
            const formData = new FormData(form);
            formData.append('action', 'simpan_penitipan');

            const btn = document.getElementById('btnSimpanPenitipan');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Penitipan';

                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', html: res.message });
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Penitipan';
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan.' });
                });
        }

        // =========================================================================
        // EDIT PENITIPAN BARANG
        // =========================================================================
        function openModalEditPenitipan(idPenitipan) {
            document.getElementById('editIdPenitipan').value = idPenitipan;
            const container = document.getElementById('editContainerItemRows');
            container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-dark"></div> Memuat data...</div>';
            
            new bootstrap.Modal(document.getElementById('modalEditPenitipan')).show();

            fetch(`tiptok-ajax.php?action=get_detail&id=${idPenitipan}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        const m = res.data.master;
                        document.getElementById('editModalSubtitle').textContent = `Kode: ${m.kode_titip} - Sales: ${m.nama_sales || 'Sales'}`;
                        document.getElementById('editTglTitip').value = m.tgl_titip;
                        document.getElementById('editCatatan').value = m.catatan || '';
                        document.getElementById('editStatusPenitipan').value = m.status;

                        // Dropdown dealer
                        const sel = document.getElementById('editSelectDealer');
                        sel.innerHTML = '<option value="">-- Pilih Toko Customer / Dealer --</option>';
                        dealersList.forEach(d => {
                            const katBadge = d.kategori ? `[${d.kategori}] ` : '';
                            const selected = (d.id == m.id_customer) ? 'selected' : '';
                            sel.innerHTML += `<option value="${d.id}" ${selected}>${katBadge}${d.nama} - ${d.kota || ''}</option>`;
                        });
                        onEditDealerSelected();

                        // Render items
                        container.innerHTML = '';
                        editItemRowIndex = 0;
                        res.data.items.forEach(it => {
                            editItemRowIndex++;
                            const isSold = parseInt(it.qty_terjual) > 0;
                            const deleteBtn = isSold ? 
                                `<span class="taste-badge badge-danger-tag" style="font-size:12px;">Terjual ${it.qty_terjual} unit (Terkunci)</span>` : 
                                `<button type="button" class="btn btn-sm btn-link text-danger p-0 mb-0 font-weight-bold" onclick="hapusBarisBarangEdit(${editItemRowIndex})">
                                    <i class="fa-solid fa-trash-can me-1"></i> Hapus
                                 </button>`;

                            const rowHtml = `
                                <div class="p-3 mb-2 rounded-3 bg-light border" id="editItemRow_${editItemRowIndex}" style="border: 2px solid #cbd5e1 !important;">
                                    <input type="hidden" name="items[${editItemRowIndex}][id_item]" value="${it.id}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="taste-badge badge-neutral" style="font-size: 13px;">Item #${editItemRowIndex}</span>
                                        ${deleteBtn}
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <label class="form-label-taste mb-1">NAMA BARANG <span class="text-danger">*</span></label>
                                            <input type="text" name="items[${editItemRowIndex}][nama_barang]" class="form-control-taste w-100" value="${escapeHtml(it.nama_barang)}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label-taste mb-1">TIPE / KATEGORI</label>
                                            <input type="text" name="items[${editItemRowIndex}][tipe_barang]" class="form-control-taste w-100" value="${escapeHtml(it.tipe_barang || '')}">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label-taste mb-1">QTY TITIP <span class="text-danger">*</span></label>
                                            <input type="number" name="items[${editItemRowIndex}][qty_titip]" min="${it.qty_terjual || 1}" class="form-control-taste w-100 text-center" value="${it.qty_titip}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label-taste mb-1">INSENTIF / UNIT (RP) <span class="text-danger">*</span></label>
                                            <input type="number" name="items[${editItemRowIndex}][insentif_per_unit]" min="0" step="500" class="form-control-taste w-100 text-end" value="${it.insentif_per_unit}" required>
                                        </div>
                                    </div>
                                </div>
                            `;
                            container.insertAdjacentHTML('beforeend', rowHtml);
                        });
                    }
                });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function onEditDealerSelected() {
            const id = document.getElementById('editSelectDealer').value;
            const dealer = dealersList.find(d => d.id == id);
            const prev = document.getElementById('editDealerPreview');
            if (dealer) {
                document.getElementById('editPrevNamaToko').textContent = dealer.nama;
                document.getElementById('editPrevKategoriToko').textContent = dealer.kategori || 'Dealer';
                document.getElementById('editPrevAlamatToko').textContent = (dealer.alamat || '') + (dealer.kota ? ', ' + dealer.kota : '');
                document.getElementById('editPrevTelpToko').textContent = dealer.telp_pribadi ? 'WA / Telp: ' + dealer.telp_pribadi : '';
                prev.classList.remove('d-none');
            } else {
                prev.classList.add('d-none');
            }
        }

        function tambahBarisBarangEdit() {
            editItemRowIndex++;
            const container = document.getElementById('editContainerItemRows');
            const rowHtml = `
                <div class="p-3 mb-2 rounded-3 bg-light border" id="editItemRow_${editItemRowIndex}" style="border: 2px solid #cbd5e1 !important;">
                    <input type="hidden" name="items[${editItemRowIndex}][id_item]" value="0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="taste-badge badge-neutral" style="font-size: 13px;">Item Baru #${editItemRowIndex}</span>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 mb-0 font-weight-bold" onclick="hapusBarisBarangEdit(${editItemRowIndex})">
                            <i class="fa-solid fa-trash-can me-1"></i> Hapus
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label-taste mb-1">NAMA BARANG <span class="text-danger">*</span></label>
                            <input type="text" name="items[${editItemRowIndex}][nama_barang]" class="form-control-taste w-100" placeholder="Nama Barang" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-taste mb-1">TIPE / KATEGORI</label>
                            <input type="text" name="items[${editItemRowIndex}][tipe_barang]" class="form-control-taste w-100" placeholder="CCTV / NVR">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-taste mb-1">QTY TITIP <span class="text-danger">*</span></label>
                            <input type="number" name="items[${editItemRowIndex}][qty_titip]" min="1" class="form-control-taste w-100 text-center" placeholder="Jml" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-taste mb-1">INSENTIF / UNIT (RP) <span class="text-danger">*</span></label>
                            <input type="number" name="items[${editItemRowIndex}][insentif_per_unit]" min="0" step="500" class="form-control-taste w-100 text-end" placeholder="15000" required>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rowHtml);
        }

        function hapusBarisBarangEdit(idx) {
            const el = document.getElementById(`editItemRow_${idx}`);
            if (el) el.remove();
        }

        function submitEditPenitipan(e) {
            e.preventDefault();
            const form = document.getElementById('formEditPenitipan');
            const formData = new FormData(form);
            formData.append('action', 'update_penitipan');

            const btn = document.getElementById('btnSimpanEditPenitipan');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan Perubahan...';

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Perubahan';

                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Diperbarui!',
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal Menyimpan', html: res.message });
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Perubahan';
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan.' });
                });
        }

        // =========================================================================
        // HAPUS PENITIPAN BARANG
        // =========================================================================
        function hapusPenitipan(idPenitipan, kodeTitip, namaToko) {
            Swal.fire({
                title: 'Hapus Data Penitipan?',
                html: `Apakah Anda yakin ingin menghapus data penitipan <strong>[${kodeTitip}]</strong> di toko <strong>${namaToko}</strong>?<br><br><span class="text-danger font-weight-bold">Perhatian: Seluruh data barang & histori log terkait akan dihapus secara permanen!</span>`,
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
                            if (res.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil Dihapus!',
                                    text: res.message,
                                    timer: 1800,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal Menghapus', html: res.message });
                            }
                        })
                        .catch(() => {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan saat menghapus data.' });
                        });
                }
            });
        }

        function openModalLaporKunjungan(idPenitipan) {
            document.getElementById('kunjunganIdPenitipan').value = idPenitipan;
            const tbody = document.getElementById('kunjunganItemsBody');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm text-dark"></div> Memuat barang...</td></tr>';

            new bootstrap.Modal(document.getElementById('modalLaporKunjungan')).show();

            fetch(`tiptok-ajax.php?action=get_detail&id=${idPenitipan}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        const m = res.data.master;
                        document.getElementById('kunjunganNamaToko').textContent = m.nama_toko;
                        document.getElementById('kunjunganKodeTitip').textContent = m.kode_titip;
                        document.getElementById('kunjunganAlamatToko').textContent = m.alamat_toko + (m.kota_toko ? ', ' + m.kota_toko : '');

                        tbody.innerHTML = '';
                        res.data.items.forEach((it, idx) => {
                            const sisaCur = parseInt(it.qty_sisa);
                            tbody.innerHTML += `
                                <tr>
                                    <td>
                                        <input type="hidden" name="items[${idx}][id_item]" value="${it.id}">
                                        <div class="font-weight-bold" style="font-size: 14.5px; color: #020617;">${it.nama_barang}</div>
                                        <div style="font-size: 13px; font-weight: 700; color: #047857;">Insentif: Rp ${new Intl.NumberFormat('id-ID').format(it.insentif_per_unit)}/unit</div>
                                    </td>
                                    <td class="text-center font-weight-bold text-dark">
                                        <span class="taste-badge badge-neutral" style="font-size: 14px;">${sisaCur}</span>
                                    </td>
                                    <td>
                                        <input type="number" name="items[${idx}][stok_sisa]" 
                                               id="stokSisa_${idx}" 
                                               min="0" max="${sisaCur}" 
                                               class="form-control-taste w-100 text-center" 
                                               style="font-size: 15px; font-weight: 800;"
                                               value="${sisaCur}" 
                                               required 
                                               oninput="hitungTerjualRow(${idx}, ${sisaCur})">
                                    </td>
                                    <td class="text-center font-weight-bold" id="terjualDisplay_${idx}">
                                        <span class="taste-badge badge-neutral" style="font-size: 13px;">0</span>
                                    </td>
                                    <td>
                                        <input type="text" name="items[${idx}][no_inv]" 
                                               id="noInv_${idx}" 
                                               class="form-control-taste w-100 font-monospace" 
                                               style="font-size: 13.5px; font-weight: 700;"
                                               placeholder="No. Invoice">
                                    </td>
                                </tr>
                            `;
                        });
                    }
                });
        }

        function hitungTerjualRow(idx, stokPrev) {
            const valInput = document.getElementById(`stokSisa_${idx}`).value;
            const sisa = parseInt(valInput) || 0;
            const terjual = Math.max(0, stokPrev - sisa);
            const disp = document.getElementById(`terjualDisplay_${idx}`);
            const inv = document.getElementById(`noInv_${idx}`);

            if (terjual > 0) {
                disp.innerHTML = `<span class="taste-badge badge-danger-tag" style="font-size: 13px;">${terjual} Laku</span>`;
                inv.setAttribute('required', 'required');
                inv.style.borderColor = '#ef4444';
                inv.style.boxShadow = '0 0 0 2px rgba(239, 68, 68, 0.2)';
            } else {
                disp.innerHTML = `<span class="taste-badge badge-neutral" style="font-size: 13px;">0</span>`;
                inv.removeAttribute('required');
                inv.style.borderColor = '';
                inv.style.boxShadow = '';
            }
        }

        function submitLaporKunjungan(e) {
            e.preventDefault();
            const form = document.getElementById('formLaporKunjungan');
            const formData = new FormData(form);
            formData.append('action', 'simpan_kunjungan');

            const btn = document.getElementById('btnSimpanKunjungan');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Laporan';

                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Laporan Tersimpan!',
                            html: res.message,
                            confirmButtonText: 'OK'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Validasi Gagal', html: res.message });
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Laporan';
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan.' });
                });
        }

        function openModalDetailTiptok(idPenitipan) {
            document.getElementById('detailLoading').classList.remove('d-none');
            document.getElementById('detailContent').classList.add('d-none');
            new bootstrap.Modal(document.getElementById('modalDetailTiptok')).show();

            fetch(`tiptok-ajax.php?action=get_detail&id=${idPenitipan}`)
                .then(r => r.json())
                .then(res => {
                    document.getElementById('detailLoading').classList.add('d-none');
                    if (res.status === 'success') {
                        document.getElementById('detailContent').classList.remove('d-none');
                        const m = res.data.master;
                        document.getElementById('detailKodeTitip').textContent = 'Kode: ' + m.kode_titip;
                        document.getElementById('detNamaToko').textContent = m.nama_toko;
                        document.getElementById('detAlamatToko').textContent = m.alamat_toko + (m.kota_toko ? ', ' + m.kota_toko : '');
                        document.getElementById('detTelpToko').textContent = m.telp_toko ? 'WA / Telp: ' + m.telp_toko : '';
                        document.getElementById('detKodeTitip').textContent = m.kode_titip;
                        document.getElementById('detTglTitip').textContent = m.tgl_titip;
                        document.getElementById('detNamaSales').textContent = m.nama_sales || 'Sales';

                        const itemBody = document.getElementById('detItemsBody');
                        itemBody.innerHTML = '';
                        res.data.items.forEach(it => {
                            itemBody.innerHTML += `
                                <tr>
                                    <td><strong style="font-size: 14.5px; color: #020617;">${it.nama_barang}</strong> <span class="text-xs text-muted">(${it.tipe_barang || '-'})</span></td>
                                    <td class="text-center font-weight-bold">${it.qty_titip}</td>
                                    <td class="text-center font-weight-bold text-success" style="font-size: 14.5px;">${it.qty_sisa}</td>
                                    <td class="text-center font-weight-bold text-danger" style="font-size: 14.5px;">${it.qty_terjual}</td>
                                    <td class="text-end font-weight-bold">Rp ${new Intl.NumberFormat('id-ID').format(it.insentif_per_unit)}</td>
                                    <td class="text-end font-weight-bold text-success" style="font-size: 15px;">Rp ${new Intl.NumberFormat('id-ID').format(it.total_insentif)}</td>
                                </tr>
                            `;
                        });

                        const logBody = document.getElementById('detLogsBody');
                        logBody.innerHTML = '';
                        if (res.data.logs.length === 0) {
                            logBody.innerHTML = '<tr><td colspan="8" class="text-center py-3 text-muted font-weight-bold">Belum ada riwayat kunjungan audit.</td></tr>';
                        } else {
                            res.data.logs.forEach(l => {
                                const invBadge = l.no_inv ? `<span class="taste-badge badge-invoice-tag">${l.no_inv}</span>` : '-';
                                logBody.innerHTML += `
                                    <tr>
                                        <td class="font-weight-bold">${l.tgl_kunjungan}</td>
                                        <td><strong>${l.nama_sales || '-'}</strong></td>
                                        <td><strong>${l.nama_barang}</strong></td>
                                        <td class="text-center font-weight-bold text-success">${l.stok_sisa}</td>
                                        <td class="text-center font-weight-bold text-danger">${l.qty_terjual_kunjungan}</td>
                                        <td>${invBadge}</td>
                                        <td class="text-end font-weight-bold text-success">Rp ${new Intl.NumberFormat('id-ID').format(l.insentif_didapat)}</td>
                                        <td class="text-sm text-secondary font-weight-bold">${l.catatan_kunjungan || '-'}</td>
                                    </tr>
                                `;
                            });
                        }
                    }
                });
        }

        function loadClaimSummary() {
            fetch('tiptok-ajax.php?action=get_claim_summary')
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        const d = res.data;
                        const bodyUnclaimed = document.getElementById('bodyUnclaimedItems');
                        bodyUnclaimed.innerHTML = '';

                        if (d.unclaimed_items.length === 0) {
                            bodyUnclaimed.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted font-weight-bold">Tidak ada unit terjual yang menunggu klaim.</td></tr>';
                        } else {
                            d.unclaimed_items.forEach(u => {
                                bodyUnclaimed.innerHTML += `
                                    <tr>
                                        <td class="font-weight-bold">${u.tgl_kunjungan}</td>
                                        <td><strong>${u.nama_toko}</strong></td>
                                        <td><strong>${u.nama_barang}</strong></td>
                                        <td><span class="taste-badge badge-invoice-tag">${u.no_inv || '-'}</span></td>
                                        <td class="text-center font-weight-bold text-danger" style="font-size: 15px;">${u.qty_terjual_kunjungan}</td>
                                        <td class="text-end font-weight-bold">Rp ${new Intl.NumberFormat('id-ID').format(u.insentif_per_unit)}</td>
                                        <td class="text-end font-weight-bold text-success" style="font-size: 15px;">Rp ${new Intl.NumberFormat('id-ID').format(u.insentif_didapat)}</td>
                                    </tr>
                                `;
                            });
                        }

                        const bodyClaim = document.getElementById('bodyClaimHistory');
                        bodyClaim.innerHTML = '';
                        if (d.claim_history.length === 0) {
                            bodyClaim.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted font-weight-bold">Belum ada riwayat pengajuan klaim.</td></tr>';
                        } else {
                            d.claim_history.forEach(c => {
                                let stBadge = 'badge-neutral';
                                if (c.status_claim === 'disetujui') stBadge = 'badge-active-tag';
                                else if (c.status_claim === 'cair') stBadge = 'badge-active-tag';
                                else if (c.status_claim === 'menunggu_approval') stBadge = 'badge-invoice-tag';

                                bodyClaim.innerHTML += `
                                    <tr>
                                        <td class="font-monospace font-weight-bold" style="font-size: 14px;">${c.kode_claim}</td>
                                        <td><strong>${c.nama_sales}</strong></td>
                                        <td class="font-weight-bold">${c.tgl_claim}</td>
                                        <td class="text-center font-weight-bold" style="font-size: 14.5px;">${c.total_unit_terjual} Unit</td>
                                        <td class="text-end font-weight-bold text-success" style="font-size: 15px;">Rp ${new Intl.NumberFormat('id-ID').format(c.total_nominal_insentif)}</td>
                                        <td class="text-center"><span class="taste-badge ${stBadge}">${c.status_claim.toUpperCase()}</span></td>
                                        <td style="text-align: right;">
                                            <button class="btn-table-secondary" onclick="openModalDetailClaim(${c.id})">
                                                <i class="fa-solid fa-eye me-1"></i> Rincian
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                        }
                    }
                });
        }

        function openModalSubmitClaim() {
            new bootstrap.Modal(document.getElementById('modalSubmitClaim')).show();
        }

        function submitKlaimInsentif(e) {
            e.preventDefault();
            const form = document.getElementById('formSubmitClaim');
            const formData = new FormData(form);
            formData.append('action', 'ajukan_claim');

            const btn = document.getElementById('btnProsesClaim');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Memproses...';

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Kirim Pengajuan Klaim';

                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Klaim Diajukan!',
                            text: res.message,
                            confirmButtonText: 'OK'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', html: res.message });
                    }
                });
        }

        function openModalDetailClaim(idClaim) {
            currentClaimId = idClaim;
            new bootstrap.Modal(document.getElementById('modalClaimApproval')).show();

            fetch(`tiptok-ajax.php?action=get_claim_detail&id_claim=${idClaim}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        const cl = res.data.claim;
                        document.getElementById('claimKodeTitle').textContent = 'Kode Klaim: ' + cl.kode_claim;
                        document.getElementById('claimSalesName').textContent = cl.nama_sales;
                        document.getElementById('claimTgl').textContent = 'Tgl: ' + cl.tgl_claim + ' (' + cl.total_unit_terjual + ' Unit)';
                        document.getElementById('claimNominal').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(cl.total_nominal_insentif);

                        const badge = document.getElementById('claimStatusBadge');
                        badge.className = 'taste-badge ' + (cl.status_claim === 'cair' ? 'badge-active-tag' : (cl.status_claim === 'disetujui' ? 'badge-active-tag' : 'badge-invoice-tag'));
                        badge.textContent = cl.status_claim.toUpperCase();

                        const selStatus = document.getElementById('updateClaimStatusSelect');
                        if (selStatus) selStatus.value = cl.status_claim;

                        const noteAdmin = document.getElementById('updateClaimAdminNote');
                        if (noteAdmin) noteAdmin.value = cl.catatan_admin || '';

                        const tbody = document.getElementById('claimDetailItemsBody');
                        tbody.innerHTML = '';
                        res.data.details.forEach(d => {
                            tbody.innerHTML += `
                                <tr>
                                    <td><strong>${d.nama_toko}</strong></td>
                                    <td><strong>${d.nama_barang}</strong></td>
                                    <td><span class="taste-badge badge-invoice-tag">${d.no_inv || '-'}</span></td>
                                    <td class="text-center font-weight-bold" style="font-size: 14.5px;">${d.qty_terjual}</td>
                                    <td class="text-end font-weight-bold">Rp ${new Intl.NumberFormat('id-ID').format(d.insentif_per_unit)}</td>
                                    <td class="text-end font-weight-bold text-success" style="font-size: 15px;">Rp ${new Intl.NumberFormat('id-ID').format(d.subtotal_insentif)}</td>
                                </tr>
                            `;
                        });
                    }
                });
        }

        function submitUpdateClaimStatus() {
            const status = document.getElementById('updateClaimStatusSelect').value;
            const note = document.getElementById('updateClaimAdminNote').value;

            const formData = new FormData();
            formData.append('action', 'update_status_claim');
            formData.append('id_claim', currentClaimId);
            formData.append('status_claim', status);
            formData.append('catatan_admin', note);

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Sukses', text: res.message, timer: 1500, showConfirmButton: false })
                            .then(() => {
                                bootstrap.Modal.getInstance(document.getElementById('modalClaimApproval')).hide();
                                loadClaimSummary();
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: res.message });
                    }
                });
        }
    </script>
</body>
</html>
