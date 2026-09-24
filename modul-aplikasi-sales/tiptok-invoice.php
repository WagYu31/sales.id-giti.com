<?php
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";

$pageNow = "No. Invoice TIP TOK";
$currentPage = "Today";

$idSesi = $_SESSION["id"] ?? 0;
$role = $_SESSION["jabatan"] ?? 'Sales';
$namaSesi = $nmUser ?? ($_SESSION["nama"] ?? 'Sales');

// Check sales_customer vs customers table
$hasSalesCustomer = false;
$chkSC = $conn->query("SHOW TABLES LIKE 'sales_customer'");
if ($chkSC && $chkSC->num_rows > 0) {
    $hasSalesCustomer = true;
}

// Initial counts for PHP preload
$whereSales = ($role === 'Sales') ? " AND (p.id_sales = '$idSesi' OR k.id_sales = '$idSesi') " : "";
$qInitStats = $conn->query("SELECT 
    COUNT(k.id) AS total_trx,
    SUM(k.qty_terjual_kunjungan) AS total_unit,
    SUM(CASE WHEN k.no_inv IS NULL OR TRIM(k.no_inv) = '' THEN k.qty_terjual_kunjungan ELSE 0 END) AS pending_unit,
    SUM(CASE WHEN k.no_inv IS NULL OR TRIM(k.no_inv) = '' THEN 1 ELSE 0 END) AS pending_trx,
    SUM(CASE WHEN k.no_inv IS NOT NULL AND TRIM(k.no_inv) != '' THEN k.qty_terjual_kunjungan ELSE 0 END) AS invoiced_unit,
    SUM(k.insentif_didapat) AS total_insentif
    FROM tiptok_kunjungan k 
    JOIN tiptok_penitipan p ON k.id_penitipan = p.id
    WHERE k.qty_terjual_kunjungan > 0 $whereSales");
$initStat = $qInitStats ? $qInitStats->fetch_assoc() : [];
$statTotalUnit = intval($initStat['total_unit'] ?? 0);
$statPendingUnit = intval($initStat['pending_unit'] ?? 0);
$statPendingTrx = intval($initStat['pending_trx'] ?? 0);
$statInvoicedUnit = intval($initStat['invoiced_unit'] ?? 0);
$statTotalInsentif = floatval($initStat['total_insentif'] ?? 0);

// Preload Sales PIC list
$colNamaSales = "nama_lengkap";
$chkColSales = @$conn->query("SHOW COLUMNS FROM sales LIKE 'nama_lengkap'");
if (!$chkColSales || $chkColSales->num_rows == 0) {
    $colNamaSales = "nama";
}
$qSalesList = $conn->query("SELECT id, $colNamaSales AS nama_sales, role FROM sales WHERE deleted_at IS NULL ORDER BY (role = 'sales') DESC, $colNamaSales ASC");
$salesOptionList = [];
if ($qSalesList) {
    while ($sRow = $qSalesList->fetch_assoc()) {
        $salesOptionList[] = [
            'id' => intval($sRow['id']),
            'nama' => $sRow['nama_sales'] ?? 'Sales',
            'jabatan' => $sRow['role'] ?? 'Sales'
        ];
    }
}

// Preload Dealers list
$dealerOptionList = [];
if ($hasSalesCustomer) {
    $qDealersPreload = $conn->query("SELECT id, kode_customer, nama, kategori, telp_pribadi, alamat, kota, alamat_lokasi 
                                     FROM sales_customer 
                                     WHERE deleted_at IS NULL 
                                     ORDER BY (kategori = 'Dealer') DESC, nama ASC LIMIT 500");
} else {
    $qDealersPreload = $conn->query("SELECT c.id, c.id AS kode_customer, c.nama_toko AS nama, c.kategori, 
                                            (SELECT tlp_pic FROM customer_pics WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS telp_pribadi,
                                            (SELECT alamat FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat,
                                            (SELECT kota FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS kota,
                                            (SELECT link_google_map FROM customer_addresses WHERE customer_id = c.id AND deleted_at IS NULL LIMIT 1) AS alamat_lokasi
                                     FROM customers c 
                                     WHERE c.deleted_at IS NULL 
                                     ORDER BY (c.kategori = 'DEALER') DESC, c.nama_toko ASC LIMIT 500");
}
if ($qDealersPreload) {
    while ($dRow = $qDealersPreload->fetch_assoc()) {
        $dealerOptionList[] = [
            'id' => intval($dRow['id']),
            'kode_customer' => $dRow['kode_customer'] ?? '',
            'nama' => $dRow['nama'] ?? '',
            'kategori' => $dRow['kategori'] ?? 'Dealer',
            'telp_pribadi' => $dRow['telp_pribadi'] ?? '',
            'alamat' => $dRow['alamat'] ?? '',
            'kota' => $dRow['kota'] ?? '',
            'alamat_lokasi' => $dRow['alamat_lokasi'] ?? ''
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>No. Invoice TIP TOK | Loewix Sales</title>
    <?php include "head.php"; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-heading: 'Outfit', sans-serif;
            --primary-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --accent-blue: #2563eb;
            --accent-emerald: #059669;
            --accent-amber: #d97706;
            --accent-rose: #e11d48;
            --card-radius: 16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #0f172a;
            min-height: 100vh;
        }

        /* Hero Banner */
        .hero-banner-invoice {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            border-radius: var(--card-radius);
            padding: 26px 30px;
            color: #ffffff;
            box-shadow: 0 12px 30px -10px rgba(15, 23, 42, 0.4);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 24px;
        }
        .hero-banner-invoice::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.25) 0%, rgba(14, 165, 233, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-title {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
            color: #ffffff;
        }

        .hero-subtitle {
            font-size: 13.5px;
            color: #94a3b8;
            margin-bottom: 0;
            font-weight: 500;
        }

        /* Metric Bento Cards */
        .metric-card-inv {
            background: #ffffff;
            border-radius: var(--card-radius);
            padding: 20px 22px;
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.04);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .metric-card-inv:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px -5px rgba(0, 0, 0, 0.08);
        }

        .metric-card-inv .metric-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .metric-card-inv .metric-value {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 2px;
        }
        .metric-card-inv .metric-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .theme-blue .metric-icon-box { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
        .theme-blue .metric-value { color: #1e40af; }

        .theme-amber .metric-icon-box { background: rgba(217, 119, 6, 0.12); color: #d97706; }
        .theme-amber .metric-value { color: #b45309; }

        .theme-emerald .metric-icon-box { background: rgba(5, 150, 105, 0.12); color: #059669; }
        .theme-emerald .metric-value { color: #047857; }

        .theme-purple .metric-icon-box { background: rgba(147, 51, 234, 0.12); color: #9333ea; }
        .theme-purple .metric-value { color: #7e22ce; }

        /* Filter Controls */
        .filter-panel-card {
            background: #ffffff;
            border-radius: var(--card-radius);
            padding: 18px 24px;
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            margin-bottom: 20px;
        }

        .segment-filter-group {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            gap: 4px;
            border: 1px solid #e2e8f0;
        }

        .segment-btn {
            border: none;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .segment-btn:hover {
            color: #0f172a;
        }
        .segment-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Search input */
        .search-input-box {
            position: relative;
        }
        .search-input-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }
        .search-input-box input {
            padding-left: 38px;
            border-radius: 12px;
            border: 2px solid #cbd5e1;
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            height: 42px;
        }
        .search-input-box input:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            outline: none;
        }

        /* Batch Action Floating Bar */
        .batch-action-bar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 14px;
            padding: 12px 20px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3);
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            animation: slideDown 0.2s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Data Table */
        .data-card-inv {
            background: #ffffff;
            border-radius: var(--card-radius);
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 6px 20px -4px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .table-invoice {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-invoice thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 16px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .table-invoice tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #1e293b;
        }
        .table-invoice tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Badges */
        .badge-pending-inv {
            background: #fef3c7;
            color: #b45309;
            border: 1.5px solid #fde68a;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-verified-inv {
            background: #ecfdf5;
            color: #047857;
            border: 1.5px solid #a7f3d0;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Buttons */
        .btn-brand-primary {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);
            text-decoration: none;
        }
        .btn-brand-primary:hover {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-brand-amber {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-brand-amber:hover {
            background: linear-gradient(135deg, #b45309 0%, #92400e 100%);
            color: #ffffff;
        }

        .btn-brand-secondary {
            background: #ffffff;
            color: #334155;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-brand-secondary:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        /* Form Inputs in Modal */
        .form-label-taste {
            font-size: 12.5px;
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
            padding: 9px 14px;
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

        /* ------------------------------------------------------------- */
        /* 3D TACTILE LEADERBOARD WIDGET (taste-skill aligned)           */
        /* ------------------------------------------------------------- */
        .leaderboard-section {
            margin-bottom: 24px;
        }
        .leaderboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .leaderboard-title {
            font-family: var(--font-heading);
            font-size: 19px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 2px;
            letter-spacing: -0.01em;
        }
        .leaderboard-subtitle {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 600;
        }

        /* 3D Tactile Sales Card */
        .sales-card-3d {
            position: relative;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            padding: 18px 20px;
            border: 1.5px solid #e2e8f0;
            box-shadow: 
                0 10px 20px -5px rgba(15, 23, 42, 0.08),
                0 4px 6px -2px rgba(15, 23, 42, 0.03),
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                inset 0 -3px 0 rgba(226, 232, 240, 0.7);
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            overflow: hidden;
        }
        .sales-card-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: transparent;
            transition: all 0.2s ease;
        }
        .sales-card-3d:hover {
            transform: translateY(-5px) scale(1.015);
            box-shadow: 
                0 20px 30px -8px rgba(15, 23, 42, 0.16),
                0 8px 12px -4px rgba(15, 23, 42, 0.06),
                inset 0 1px 0 rgba(255, 255, 255, 1);
            border-color: #cbd5e1;
        }
        .sales-card-3d:active {
            transform: translateY(-1px) scale(0.995);
            box-shadow: 0 6px 12px -3px rgba(15, 23, 42, 0.1);
        }

        /* Rank 1 (Gold 3D) */
        .sales-card-3d.rank-1 {
            background: linear-gradient(145deg, #ffffff 0%, #fffbeb 50%, #fef3c7 100%);
            border-color: #fde68a;
            box-shadow: 
                0 14px 26px -6px rgba(217, 119, 6, 0.2),
                0 4px 6px -2px rgba(217, 119, 6, 0.06),
                inset 0 1px 0 rgba(255, 255, 255, 1),
                inset 0 -3px 0 #fcd34d;
        }
        .sales-card-3d.rank-1::before {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        /* Rank 2 (Silver 3D) */
        .sales-card-3d.rank-2 {
            background: linear-gradient(145deg, #ffffff 0%, #f1f5f9 50%, #e2e8f0 100%);
            border-color: #cbd5e1;
            box-shadow: 
                0 12px 24px -6px rgba(100, 116, 139, 0.16),
                0 4px 6px -2px rgba(100, 116, 139, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 1),
                inset 0 -3px 0 #94a3b8;
        }
        .sales-card-3d.rank-2::before {
            background: linear-gradient(90deg, #94a3b8, #64748b);
        }

        /* Rank 3 (Bronze 3D) */
        .sales-card-3d.rank-3 {
            background: linear-gradient(145deg, #ffffff 0%, #fff7ed 50%, #ffedd5 100%);
            border-color: #fed7aa;
            box-shadow: 
                0 12px 24px -6px rgba(194, 65, 12, 0.15),
                0 4px 6px -2px rgba(194, 65, 12, 0.04),
                inset 0 1px 0 rgba(255, 255, 255, 1),
                inset 0 -3px 0 #fdba74;
        }
        .sales-card-3d.rank-3::before {
            background: linear-gradient(90deg, #fb923c, #c2410c);
        }

        /* Active Selected State for 3D Card */
        .sales-card-3d.is-active-filter {
            border-color: #0284c7 !important;
            box-shadow: 
                0 0 0 3.5px rgba(2, 132, 199, 0.35),
                0 16px 30px -6px rgba(2, 132, 199, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 1) !important;
            background: linear-gradient(145deg, #f0f9ff 0%, #e0f2fe 100%) !important;
        }

        /* 3D Medal Emblem */
        .medal-badge-3d {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 900;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }
        .medal-gold {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        .medal-silver {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        .medal-bronze {
            background: linear-gradient(135deg, #fb923c 0%, #c2410c 100%);
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        .medal-default {
            background: #e2e8f0;
            color: #475569;
        }

        /* 3D Sales Avatar */
        .sales-avatar-3d {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            color: #38bdf8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: 15px;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.15);
        }

        /* 3D Claim Meter */
        .progress-meter-3d {
            background: #e2e8f0;
            border-radius: 8px;
            height: 8px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        }
        .progress-meter-bar-3d {
            height: 100%;
            border-radius: 8px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 1px 3px rgba(16, 185, 129, 0.4);
        }

        /* View Switcher */
        .view-switch-group {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            gap: 4px;
            border: 1px solid #e2e8f0;
        }
        .view-switch-btn {
            border: none;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .view-switch-btn:hover {
            color: #0f172a;
        }
        .view-switch-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-200">

    <?php include "cek-menu.php"; ?>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <?php include "nav-top.php"; ?>

        <div class="container-fluid py-4 px-4">

            <!-- 1. HERO BANNER HEADER -->
            <div class="hero-banner-invoice">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge" style="background: rgba(14, 165, 233, 0.2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px;">
                                <i class="fa-solid fa-receipt me-1"></i> MODUL FAKTUR KHUSUS
                            </span>
                            <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px;">
                                Program TIP TOK
                            </span>
                        </div>
                        <h1 class="hero-title">
                            Halaman No. Invoice TIP TOK
                        </h1>
                        <p class="hero-subtitle">
                            Kelola pencatatan nomor faktur/invoice untuk seluruh barang konsinyasi yang telah terjual pada audit toko mitra.
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="tiptok.php" class="btn btn-outline-light mb-0 font-weight-bold" style="border-radius: 10px; padding: 9px 16px; font-size: 13px;">
                            <i class="fa-solid fa-box-archive me-1.5"></i> Tabel Penitipan Stok
                        </a>
                        <button type="button" class="btn btn-primary mb-0 font-weight-bold" style="border-radius: 10px; padding: 9px 16px; font-size: 13px;" onclick="loadInvoicesData()">
                            <i class="fa-solid fa-rotate me-1.5"></i> Refresh Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. LIVE METRIC BENTO CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-sm-6">
                    <div class="metric-card-inv theme-blue">
                        <div>
                            <div class="metric-icon-box"><i class="fa-solid fa-boxes-packing"></i></div>
                            <div class="metric-value" id="statTotalUnit"><?php echo number_format($statTotalUnit); ?> Unit</div>
                            <div class="metric-label">Total Unit Terjual</div>
                        </div>
                        <div class="text-xs text-muted font-weight-bold mt-2">Dari seluruh audit kunjungan</div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="metric-card-inv theme-amber">
                        <div>
                            <div class="metric-icon-box"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                            <div class="metric-value" id="statPendingUnit"><?php echo number_format($statPendingUnit); ?> Unit</div>
                            <div class="metric-label">Belum Ada No. Invoice</div>
                        </div>
                        <div class="text-xs font-weight-bold mt-2" style="color: #b45309;" id="statPendingTrxText"><?php echo $statPendingTrx; ?> transaksi menunggu invoice</div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="metric-card-inv theme-emerald">
                        <div>
                            <div class="metric-icon-box"><i class="fa-solid fa-file-circle-check"></i></div>
                            <div class="metric-value" id="statInvoicedUnit"><?php echo number_format($statInvoicedUnit); ?> Unit</div>
                            <div class="metric-label">Sudah Ber-Invoice</div>
                        </div>
                        <div class="text-xs text-success font-weight-bold mt-2">Faktur tercatat &amp; siap klaim</div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="metric-card-inv theme-purple">
                        <div>
                            <div class="metric-icon-box"><i class="fa-solid fa-coins"></i></div>
                            <div class="metric-value" id="statTotalInsentif">Rp <?php echo number_format($statTotalInsentif, 0, ',', '.'); ?></div>
                            <div class="metric-label">Total Reward Terjual</div>
                        </div>
                        <div class="text-xs text-muted font-weight-bold mt-2">Estimasi insentif unit laku</div>
                    </div>
                </div>
            </div>

            <!-- 2.5 3D TACTILE LEADERBOARD WIDGET -->
            <div class="leaderboard-section" id="leaderboardSection">
                <div class="leaderboard-header">
                    <div>
                        <div class="leaderboard-title">
                            <i class="fa-solid fa-trophy text-warning"></i> Top Sales Performer TIP TOK
                        </div>
                        <div class="leaderboard-subtitle">
                            Peringkat sales berdasarkan unit faktur penjualan &amp; progress insentif. Klik card untuk filter cepat.
                        </div>
                    </div>
                    <div id="activeSalesFilterBadge" class="d-none align-items-center gap-2">
                        <span class="badge" style="background: rgba(2, 132, 199, 0.15); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.35); font-size: 12px; padding: 6px 12px; border-radius: 20px; font-weight: 800;">
                            <i class="fa-solid fa-filter me-1"></i> Filter Aktif: <span id="activeSalesNameLabel">-</span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-danger mb-0 py-1 px-2.5 font-weight-bold" style="border-radius: 8px; font-size: 11.5px;" onclick="resetSalesFilter()">
                            <i class="fa-solid fa-xmark me-1"></i> Reset Filter
                        </button>
                    </div>
                </div>
                <div class="row g-3" id="leaderboardCardsRow">
                    <div class="col-12 text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary mb-1"></div>
                        <div class="text-xs text-muted font-weight-bold">Memuat leaderboard performa sales...</div>
                    </div>
                </div>
            </div>

            <!-- 3. FILTER & SEARCH CONTROLS -->
            <div class="filter-panel-card">
                <div class="row g-3 align-items-center justify-content-between">
                    <div class="col-xl-6 col-lg-7 col-md-12">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="view-switch-group">
                                <button type="button" class="view-switch-btn active" id="btnViewList" onclick="switchTableView('list')">
                                    <i class="fa-solid fa-list-check"></i> List Transaksi
                                </button>
                                <button type="button" class="view-switch-btn" id="btnViewRecap" onclick="switchTableView('recap')">
                                    <i class="fa-solid fa-trophy"></i> Rekap Performa Sales
                                </button>
                            </div>
                            <div class="segment-filter-group" id="statusFilterGroup">
                                <button type="button" class="segment-btn active" id="btnFilterAll" onclick="setFilterStatus('all', this)">
                                    <i class="fa-solid fa-list"></i> Semua
                                </button>
                                <button type="button" class="segment-btn" id="btnFilterPending" onclick="setFilterStatus('pending', this)">
                                    <i class="fa-solid fa-triangle-exclamation text-warning"></i> Belum Invoice 
                                    <span class="badge bg-warning text-dark px-1.5 py-0.5" id="badgePendingCount" style="font-size: 10.5px;"><?php echo $statPendingTrx; ?></span>
                                </button>
                                <button type="button" class="segment-btn" id="btnFilterInvoiced" onclick="setFilterStatus('invoiced', this)">
                                    <i class="fa-solid fa-circle-check text-success"></i> Ber-Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-5 col-md-12">
                        <div class="d-flex gap-2 justify-content-lg-end flex-wrap align-items-center">
                            <div class="quick-sales-box flex-grow-1" style="max-width: 240px;">
                                <select id="quickSalesSelect" class="form-control-taste w-100" style="height: 42px; font-size: 13px;" onchange="handleQuickSalesChange(this.value)">
                                    <option value="0">👤 Semua Sales PIC</option>
                                </select>
                            </div>
                            <div class="search-input-box flex-grow-1" style="max-width: 260px;">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari toko, barang, inv..." oninput="handleSearchInput()">
                            </div>
                            <button type="button" class="btn btn-outline-secondary mb-0 px-3 font-weight-bold" style="border-radius: 12px; height: 42px;" onclick="toggleAdvancedFilters()">
                                <i class="fa-solid fa-filter me-1"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Advanced Filters -->
                <div id="advancedFilterRow" class="row g-3 mt-2 pt-3 border-top d-none">
                    <div class="col-md-4">
                        <label class="form-label-taste mb-1">Filter Toko / Dealer Mitra</label>
                        <select id="filterDealerSelect" class="form-control-taste w-100" onchange="loadInvoicesData()">
                            <option value="0">-- Semua Toko Dealer --</option>
                            <?php foreach ($dealerOptionList as $d) : 
                                $katBadge = !empty($d['kategori']) ? '[' . $d['kategori'] . '] ' : '';
                                $kotaText = !empty($d['kota']) ? ' - ' . $d['kota'] : '';
                            ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($katBadge . $d['nama'] . $kotaText); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($role !== 'Sales') : ?>
                        <div class="col-md-3">
                            <label class="form-label-taste mb-1">Filter Sales PIC (Detail)</label>
                            <select id="filterSalesSelect" class="form-control-taste w-100" onchange="loadInvoicesData()">
                                <option value="0">-- Semua Sales PIC --</option>
                                <?php foreach ($salesOptionList as $s) : ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars($s['nama']); ?><?php echo !empty($s['jabatan']) ? ' (' . htmlspecialchars($s['jabatan']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-<?php echo ($role === 'Sales') ? '4' : '3'; ?>">
                        <label class="form-label-taste mb-1">Tanggal Mulai Audit</label>
                        <input type="date" id="filterTglMulai" class="form-control-taste w-100" onchange="loadInvoicesData()">
                    </div>
                    <div class="col-md-<?php echo ($role === 'Sales') ? '4' : '2'; ?>">
                        <label class="form-label-taste mb-1">Tanggal Akhir Audit</label>
                        <input type="date" id="filterTglAkhir" class="form-control-taste w-100" onchange="loadInvoicesData()">
                    </div>
                </div>
            </div>

            <!-- 4. BATCH ACTION BAR (Shown when checkboxes are checked) -->
            <div id="batchActionBar" class="batch-action-bar d-none">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(14, 165, 233, 0.2); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-6" id="batchSelectedText">0 Item Terpilih</div>
                        <div class="text-xs text-muted" id="batchSubtotalInfo">Total: 0 Unit | Estimasi Reward: Rp 0</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light mb-0 font-weight-bold" onclick="unselectAllCheckboxes()">
                        Batal
                    </button>
                    <button type="button" class="btn btn-sm btn-success mb-0 font-weight-bold px-3" onclick="openBatchInvoiceModal()">
                        <i class="fa-solid fa-file-pen me-1.5"></i> Input No. Invoice Kolektif
                    </button>
                </div>
            </div>

            <!-- 5. INVOICE DATA TABLE (VIEW 1: LIST TRANSAKSI) -->
            <div class="data-card-inv" id="containerViewList">
                <div class="table-responsive">
                    <table class="table table-invoice" id="tableInvoices">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">
                                    <input type="checkbox" id="checkAllItems" class="form-check-input" style="cursor: pointer;" onchange="toggleCheckAll(this)">
                                </th>
                                <th style="width: 26%;">TOKO / DEALER MITRA</th>
                                <th style="width: 24%;">PRODUK &amp; TERJUAL</th>
                                <th style="width: 14%; text-align: right;">REWARD INSENTIF</th>
                                <th style="width: 16%;">TGL &amp; KODE AUDIT</th>
                                <th style="width: 20%;">STATUS &amp; NO. INVOICE</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody">
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="spinner-border spinner-border-sm text-dark mb-2"></div>
                                    <div class="text-xs text-muted font-weight-bold">Memuat daftar invoice TIP TOK...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 6. SALES PERFORMANCE RECAP TABLE (VIEW 2: REKAP SALES) -->
            <div class="data-card-inv d-none" id="containerViewRecap">
                <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light flex-wrap gap-2">
                    <div>
                        <h6 class="font-weight-bold text-dark mb-0"><i class="fa-solid fa-ranking-star text-warning me-1.5"></i> Rekapitulasi Performa &amp; Faktur Seluruh Sales</h6>
                        <span class="text-xs text-muted font-weight-bold">Diurutkan berdasarkan unit ber-invoice &amp; total terjual</span>
                    </div>
                    <span class="badge text-white px-3 py-1.5" style="background: #0f172a; border-radius: 20px; font-size: 11.5px; font-weight: 800;">
                        Target Klaim: Min. 50 Unit
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-invoice" id="tableSalesRecap">
                        <thead>
                            <tr>
                                <th style="width: 10%; text-align: center;">PERINGKAT</th>
                                <th style="width: 24%;">PROFIL SALES</th>
                                <th style="width: 12%; text-align: center;">TOKO MITRA</th>
                                <th style="width: 22%;">TERJUAL &amp; TARGET KLAIM</th>
                                <th style="width: 16%;">STATUS FAKTUR</th>
                                <th style="width: 16%; text-align: right;">TOTAL INSENTIF</th>
                            </tr>
                        </thead>
                        <tbody id="salesRecapTableBody">
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="spinner-border spinner-border-sm text-dark mb-2"></div>
                                    <div class="text-xs text-muted font-weight-bold">Memuat data performa sales...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <?php include "footer.php"; ?>
    </main>

    <!-- ========================================================================= -->
    <!-- MODAL 1: INPUT / EDIT SINGLE NO. INVOICE                                  -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modalSingleInvoice" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border:none; box-shadow: 0 25px 50px -12px rgba(15,23,42,0.35); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 20px 24px;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(56, 189, 248, 0.15); border: 1.5px solid rgba(56, 189, 248, 0.35); display: flex; align-items: center; justify-content: center; color: #38bdf8; font-size: 19px;">
                            <i class="fa-solid fa-file-invoice"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bold text-white mb-0" id="singleModalTitle">Input No. Invoice</h5>
                            <div class="text-xs mt-0.5" style="color: #94a3b8;" id="singleModalSubtitle">Tetapkan nomor faktur penjualan TIP TOK</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formSingleInvoice" onsubmit="submitSingleInvoice(event)">
                    <input type="hidden" name="id_kunjungan" id="singleIdKunjungan">
                    <div class="modal-body p-4 bg-white">
                        
                        <!-- Info Card Ringkasan Transaksi -->
                        <div class="p-3 mb-4 rounded-3" style="background: #f8fafc; border: 1.5px solid #e2e8f0;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-dark fs-6" id="singlePrevNamaToko">-</span>
                                <span class="badge text-white px-2.5 py-1" style="background: #0f172a; border-radius: 20px; font-size: 11px; font-weight: 800;" id="singlePrevQty">0 Unit</span>
                            </div>
                            <div class="text-xs text-secondary font-weight-bold" id="singlePrevNamaBarang">-</div>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-2 mt-2" style="background: #ecfdf5; border: 1px solid #a7f3d0;">
                                <span class="text-xs text-success font-weight-bold"><i class="fa-solid fa-coins me-1"></i> Subtotal Reward Insentif:</span>
                                <span class="text-xs font-weight-bold text-success fs-6" id="singlePrevInsentif">Rp 0</span>
                            </div>
                        </div>

                        <!-- Form Input Groups (Clean & Modern) -->
                        <div class="mb-3">
                            <label class="form-label font-weight-bold text-dark text-xs text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                <i class="fa-solid fa-receipt text-primary"></i> NOMOR INVOICE / FAKTUR <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="no_inv" id="singleInputNoInv" class="form-control form-control-lg font-monospace fs-6 font-weight-bold" placeholder="Contoh: INV/2026/09/001" style="border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 10px 14px; background: #ffffff;" required>
                            <div class="form-text text-xs text-muted mt-1">Masukkan nomor faktur resmi yang diterbitkan untuk toko ini.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold text-dark text-xs text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                <i class="fa-regular fa-calendar-days text-primary"></i> TANGGAL INVOICE / FAKTUR <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="tgl_invoice" id="singleInputTglInv" class="form-control font-weight-bold" style="border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 10px 14px; background: #ffffff;" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-1">
                            <label class="form-label font-weight-bold text-dark text-xs text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                <i class="fa-regular fa-note-sticky text-secondary"></i> CATATAN INVOICE (OPSIONAL)
                            </label>
                            <textarea name="catatan_invoice" id="singleInputCatatan" class="form-control" rows="2" placeholder="Catatan nomor faktur / referensi..." style="border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 10px 14px; background: #ffffff;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-white border-top d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light px-4 font-weight-bold" style="border-radius: 10px; border: 1.5px solid #e2e8f0; color: #475569;" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnSaveSingleInvoice" class="btn btn-dark px-4 font-weight-bold" style="border-radius: 10px; background: #0f172a; color: white;">
                            <i class="fa-solid fa-check me-1"></i> Simpan Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: INPUT BATCH / KOLEKTIF NO. INVOICE                               -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modalBatchInvoice" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border:none; box-shadow: 0 25px 50px -12px rgba(15,23,42,0.35); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 20px 24px;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); border: 1.5px solid rgba(16, 185, 129, 0.35); display: flex; align-items: center; justify-content: center; color: #34d399; font-size: 19px;">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bold text-white mb-0">Input No. Invoice Kolektif</h5>
                            <div class="text-xs mt-0.5" style="color: #94a3b8;">Tetapkan satu No. Invoice ke beberapa transaksi sekaligus</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formBatchInvoice" onsubmit="submitBatchInvoice(event)">
                    <div class="modal-body p-4 bg-white">
                        <div class="p-3 mb-4 rounded-3" style="background: #f8fafc; border: 1.5px solid #e2e8f0;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark fs-6" id="batchModalSelectedCount">0 Transaksi Terpilih</span>
                                <span class="badge text-white px-2.5 py-1" style="background: #059669; border-radius: 20px; font-size: 11px; font-weight: 800;" id="batchModalTotalUnit">0 Unit</span>
                            </div>
                            <div class="text-xs text-muted mt-1.5" id="batchModalDealerSummary">-</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold text-dark text-xs text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                <i class="fa-solid fa-receipt text-primary"></i> NOMOR INVOICE KOLEKTIF <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="no_inv" id="batchInputNoInv" class="form-control form-control-lg font-monospace fs-6 font-weight-bold" placeholder="Contoh: INV/2026/09/001" style="border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 10px 14px; background: #ffffff;" required>
                            <div class="form-text text-xs text-muted mt-1">Nomor ini akan disimpan ke semua item yang Anda centang.</div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label font-weight-bold text-dark text-xs text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                <i class="fa-regular fa-calendar-days text-primary"></i> TANGGAL INVOICE / FAKTUR <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="tgl_invoice" id="batchInputTglInv" class="form-control font-weight-bold" style="border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 10px 14px; background: #ffffff;" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer p-3 bg-white border-top d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light px-4 font-weight-bold" style="border-radius: 10px; border: 1.5px solid #e2e8f0; color: #475569;" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" id="btnSaveBatchInvoice" class="btn btn-dark px-4 font-weight-bold" style="border-radius: 10px; background: #0f172a; color: white;">
                            <i class="fa-solid fa-check me-1"></i> Terapkan ke Semua Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Core -->
    <?php include "js-include.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let invoicesData = [];
        let leaderboardData = [];
        let currentStatusFilter = 'all';
        let currentSalesFilter = 0;
        let currentSalesName = '';
        let currentTableView = 'list'; // 'list' | 'recap'
        let selectedTrxIds = new Set();
        let searchTimeout = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadInvoicesData();
            loadDealerFilterOptions();
            loadSalesFilterOptions();
        });

        function showModalSafe(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
            modal.show();
        }

        function hideModalSafe(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const modal = bootstrap.Modal.getInstance(el);
            if (modal) modal.hide();
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function toggleAdvancedFilters() {
            const row = document.getElementById('advancedFilterRow');
            if (row) row.classList.toggle('d-none');
        }

        function setFilterStatus(status, btn) {
            currentStatusFilter = status;
            document.querySelectorAll('#statusFilterGroup .segment-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            loadInvoicesData();
        }

        function handleSearchInput() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadInvoicesData();
            }, 300);
        }

        function loadDealerFilterOptions() {
            fetch('tiptok-ajax.php?action=search_dealer')
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success' && Array.isArray(res.data) && res.data.length > 0) {
                        const sel = document.getElementById('filterDealerSelect');
                        if (sel) {
                            const curVal = sel.value;
                            let html = '<option value="0">-- Semua Toko Dealer --</option>';
                            res.data.forEach(d => {
                                const isSel = (d.id == curVal) ? 'selected' : '';
                                html += `<option value="${d.id}" ${isSel}>${escapeHtml(d.nama)} - ${escapeHtml(d.kota || '')}</option>`;
                            });
                            sel.innerHTML = html;
                        }
                    }
                })
                .catch(err => console.error(err));
        }

        function loadSalesFilterOptions() {
            fetch('tiptok-ajax.php?action=get_sales_list')
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success' && Array.isArray(res.data) && res.data.length > 0) {
                        const sel = document.getElementById('filterSalesSelect');
                        if (sel) {
                            const curVal = sel.value;
                            let html = '<option value="0">-- Semua Sales PIC --</option>';
                            res.data.forEach(s => {
                                const jabText = s.jabatan ? ` (${s.jabatan})` : '';
                                const isSel = (s.id == curVal) ? 'selected' : '';
                                html += `<option value="${s.id}" ${isSel}>${escapeHtml(s.nama)}${escapeHtml(jabText)}</option>`;
                            });
                            sel.innerHTML = html;
                        }
                    }
                })
                .catch(err => console.error(err));
        }

        function loadInvoicesData() {
            const tbody = document.getElementById('invoicesTableBody');
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5"><div class="spinner-border spinner-border-sm text-dark mb-2"></div><div class="text-xs text-muted font-weight-bold">Memuat data invoice...</div></td></tr>`;
            
            const search = document.getElementById('searchInput')?.value.trim() || '';
            const dealer = document.getElementById('filterDealerSelect')?.value || '0';
            const sales = currentSalesFilter > 0 ? currentSalesFilter : (document.getElementById('filterSalesSelect')?.value || '0');
            const tglMulai = document.getElementById('filterTglMulai')?.value || '';
            const tglAkhir = document.getElementById('filterTglAkhir')?.value || '';

            const params = new URLSearchParams({
                action: 'get_tiptok_invoices',
                status_inv: currentStatusFilter,
                search: search,
                id_customer: dealer,
                id_sales: sales,
                tgl_mulai: tglMulai,
                tgl_akhir: tglAkhir
            });

            fetch(`tiptok-ajax.php?${params.toString()}`)
                .then(r => r.json())
                .then(res => {
                    if (res && res.status === 'success') {
                        invoicesData = res.data.items || [];
                        leaderboardData = res.data.leaderboard || [];
                        updateMetricCards(res.data.global_stats);
                        render3DLeaderboard(leaderboardData);
                        populateQuickSalesDropdown(leaderboardData);
                        renderInvoicesTable(invoicesData);
                        renderSalesRecapTable(leaderboardData);
                    } else {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger font-weight-bold">${(res && res.message) ? res.message : 'Gagal memuat data.'}</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger font-weight-bold">Terjadi kesalahan jaringan saat memuat data.</td></tr>`;
                });
        }

        function updateMetricCards(stats) {
            if (!stats) return;
            document.getElementById('statTotalUnit').textContent = `${new Intl.NumberFormat('id-ID').format(stats.grand_total_unit || 0)} Unit`;
            document.getElementById('statPendingUnit').textContent = `${new Intl.NumberFormat('id-ID').format(stats.grand_pending_unit || 0)} Unit`;
            document.getElementById('statPendingTrxText').textContent = `${stats.grand_pending_trx || 0} transaksi menunggu invoice`;
            document.getElementById('statInvoicedUnit').textContent = `${new Intl.NumberFormat('id-ID').format(stats.grand_invoiced_unit || 0)} Unit`;
            document.getElementById('statTotalInsentif').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(stats.grand_total_insentif || 0)}`;
            
            const badgePending = document.getElementById('badgePendingCount');
            if (badgePending) badgePending.textContent = stats.grand_pending_trx || 0;
        }

        function render3DLeaderboard(leaderboard) {
            const container = document.getElementById('leaderboardCardsRow');
            if (!container) return;

            if (!leaderboard || leaderboard.length === 0) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="p-4 text-center rounded-3 bg-white border" style="border: 1.5px dashed #cbd5e1 !important;">
                            <i class="fa-solid fa-trophy fa-2x text-muted mb-2"></i>
                            <div class="font-weight-bold text-dark text-sm">Belum Ada Transaksi Terjual</div>
                            <div class="text-xs text-muted">Leaderboard akan aktif otomatis saat sales melakukan audit unit laku.</div>
                        </div>
                    </div>
                `;
                return;
            }

            let html = '';
            leaderboard.slice(0, 4).forEach((s, idx) => {
                const rank = idx + 1;
                const rankClass = rank === 1 ? 'rank-1' : (rank === 2 ? 'rank-2' : (rank === 3 ? 'rank-3' : ''));
                const medalClass = rank === 1 ? 'medal-gold' : (rank === 2 ? 'medal-silver' : (rank === 3 ? 'medal-bronze' : 'medal-default'));
                const medalIcon = rank === 1 ? '🥇' : (rank === 2 ? '🥈' : (rank === 3 ? '🥉' : `#${rank}`));
                const isActive = (currentSalesFilter == s.id_sales && s.id_sales > 0) || (currentSalesName === s.nama_sales && currentSalesName !== '');
                
                const initials = s.nama_sales ? s.nama_sales.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() : 'SL';

                html += `
                    <div class="col-xl-3 col-md-6">
                        <div class="sales-card-3d ${rankClass} ${isActive ? 'is-active-filter' : ''}" onclick="selectSalesFilter(${s.id_sales}, '${escapeHtml(s.nama_sales)}')">
                            <div>
                                <!-- Top: Rank Medal & Profile -->
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="sales-avatar-3d">
                                            ${escapeHtml(initials)}
                                        </div>
                                        <div>
                                            <div class="font-weight-bold text-dark" style="font-size: 14.5px; line-height: 1.2;">
                                                ${escapeHtml(s.nama_sales)}
                                            </div>
                                            <div class="text-xs text-muted font-weight-bold mt-0.5">
                                                <i class="fa-solid fa-store me-1"></i> ${s.total_toko} Toko Mitra
                                            </div>
                                        </div>
                                    </div>
                                    <div class="medal-badge-3d ${medalClass}">
                                        ${medalIcon}
                                    </div>
                                </div>

                                <!-- Mid: Invoiced Unit & Total Laku -->
                                <div class="p-2.5 rounded-3 mb-2.5" style="background: rgba(255,255,255,0.75); border: 1px solid rgba(226,232,240,0.8);">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-xs font-weight-bold text-secondary">Faktur / Invoice:</span>
                                        <span class="badge text-white px-2 py-0.5" style="background: #047857; font-size: 11px; font-weight: 800; border-radius: 6px;">
                                            <i class="fa-solid fa-file-invoice me-1"></i> ${s.invoiced_unit} Unit (${s.count_invoices} INV)
                                        </span>
                                    </div>
                                    ${s.pending_unit > 0 ? `
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-xs font-weight-bold" style="color: #b45309;">Pending Invoice:</span>
                                            <span class="badge bg-warning text-dark px-2 py-0.5" style="font-size: 10.5px; font-weight: 800; border-radius: 6px;">
                                                ${s.pending_unit} Unit (${s.count_pending_trx} Trx)
                                            </span>
                                        </div>
                                    ` : ''}
                                </div>

                                <!-- Milestone Progress towards 50 Units -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1 text-xs font-weight-bold">
                                        <span class="text-muted">Target Klaim (Min. 50):</span>
                                        <span class="text-dark">${s.invoiced_unit} / 50 (${s.claim_progress}%)</span>
                                    </div>
                                    <div class="progress-meter-3d">
                                        <div class="progress-meter-bar-3d" style="width: ${s.claim_progress}%;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom: Total Reward Insentif & Filter Hint -->
                            <div class="pt-2 border-top d-flex align-items-center justify-content-between mt-1">
                                <div>
                                    <div class="text-xs text-muted font-weight-bold">Total Insentif</div>
                                    <div style="font-family: var(--font-heading); font-weight: 800; font-size: 15px; color: #047857;">
                                        Rp ${new Intl.NumberFormat('id-ID').format(s.total_insentif)}
                                    </div>
                                </div>
                                <div class="text-xs font-weight-bold ${isActive ? 'text-primary' : 'text-muted'}">
                                    ${isActive ? '<i class="fa-solid fa-check-circle text-primary"></i> Aktif' : '<i class="fa-solid fa-arrow-right"></i> Filter'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function populateQuickSalesDropdown(leaderboard) {
            const sel = document.getElementById('quickSalesSelect');
            if (!sel) return;

            let html = '<option value="0">👤 Semua Sales PIC</option>';
            if (leaderboard && leaderboard.length > 0) {
                leaderboard.forEach(s => {
                    const medal = s.rank === 1 ? '🥇 ' : (s.rank === 2 ? '🥈 ' : (s.rank === 3 ? '🥉 ' : ''));
                    const isSel = (currentSalesFilter == s.id_sales && s.id_sales > 0) ? 'selected' : '';
                    html += `<option value="${s.id_sales}" data-name="${escapeHtml(s.nama_sales)}" ${isSel}>${medal}${escapeHtml(s.nama_sales)} (${s.invoiced_unit} Unit / ${s.count_invoices} INV)</option>`;
                });
            }
            sel.innerHTML = html;
        }

        function handleQuickSalesChange(val) {
            const sel = document.getElementById('quickSalesSelect');
            const opt = sel.options[sel.selectedIndex];
            const name = opt ? (opt.getAttribute('data-name') || '') : '';
            selectSalesFilter(parseInt(val) || 0, name);
        }

        function selectSalesFilter(idSales, nameSales) {
            if (currentSalesFilter === idSales && currentSalesFilter > 0) {
                resetSalesFilter();
                return;
            }

            currentSalesFilter = idSales;
            currentSalesName = nameSales || '';

            const filterBadge = document.getElementById('activeSalesFilterBadge');
            const nameLabel = document.getElementById('activeSalesNameLabel');
            if (currentSalesFilter > 0 || currentSalesName !== '') {
                if (filterBadge) filterBadge.classList.remove('d-none');
                if (filterBadge) filterBadge.classList.add('d-flex');
                if (nameLabel) nameLabel.textContent = currentSalesName || `Sales #${currentSalesFilter}`;
            } else {
                if (filterBadge) filterBadge.classList.add('d-none');
                if (filterBadge) filterBadge.classList.remove('d-flex');
            }

            const quickSel = document.getElementById('quickSalesSelect');
            if (quickSel) quickSel.value = currentSalesFilter;

            const advSel = document.getElementById('filterSalesSelect');
            if (advSel) advSel.value = currentSalesFilter;

            loadInvoicesData();
        }

        function resetSalesFilter() {
            currentSalesFilter = 0;
            currentSalesName = '';

            const filterBadge = document.getElementById('activeSalesFilterBadge');
            if (filterBadge) {
                filterBadge.classList.add('d-none');
                filterBadge.classList.remove('d-flex');
            }

            const quickSel = document.getElementById('quickSalesSelect');
            if (quickSel) quickSel.value = "0";

            const advSel = document.getElementById('filterSalesSelect');
            if (advSel) advSel.value = "0";

            loadInvoicesData();
        }

        function switchTableView(view) {
            currentTableView = view;
            const btnList = document.getElementById('btnViewList');
            const btnRecap = document.getElementById('btnViewRecap');
            const containerList = document.getElementById('containerViewList');
            const containerRecap = document.getElementById('containerViewRecap');
            const statusGroup = document.getElementById('statusFilterGroup');

            if (view === 'recap') {
                if (btnList) btnList.classList.remove('active');
                if (btnRecap) btnRecap.classList.add('active');
                if (containerList) containerList.classList.add('d-none');
                if (containerRecap) containerRecap.classList.remove('d-none');
                if (statusGroup) statusGroup.classList.add('d-none');
            } else {
                if (btnList) btnList.classList.add('active');
                if (btnRecap) btnRecap.classList.remove('active');
                if (containerList) containerList.classList.remove('d-none');
                if (containerRecap) containerRecap.classList.add('d-none');
                if (statusGroup) statusGroup.classList.remove('d-none');
            }
        }

        function renderSalesRecapTable(leaderboard) {
            const tbody = document.getElementById('salesRecapTableBody');
            if (!tbody) return;

            if (!leaderboard || leaderboard.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="d-inline-flex p-3 rounded-circle bg-light text-muted mb-2">
                                <i class="fa-solid fa-trophy fa-2x"></i>
                            </div>
                            <div class="font-weight-bold text-dark text-base mb-1">Belum Ada Rekap Sales</div>
                            <p class="text-sm text-muted mb-0">Belum ada data transaksi terjual yang tercatat.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            leaderboard.forEach(s => {
                const medalClass = s.rank === 1 ? 'medal-gold' : (s.rank === 2 ? 'medal-silver' : (s.rank === 3 ? 'medal-bronze' : 'medal-default'));
                const medalText = s.rank === 1 ? '🥇 #1' : (s.rank === 2 ? '🥈 #2' : (s.rank === 3 ? '🥉 #3' : `#${s.rank}`));
                const initials = s.nama_sales ? s.nama_sales.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() : 'SL';

                html += `
                    <tr>
                        <td class="text-center">
                            <span class="medal-badge-3d ${medalClass}">
                                ${medalText}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2.5">
                                <div class="sales-avatar-3d" style="width: 36px; height: 36px; font-size: 13px;">
                                    ${escapeHtml(initials)}
                                </div>
                                <div>
                                    <div class="font-weight-bold text-dark" style="font-size: 14px;">${escapeHtml(s.nama_sales)}</div>
                                    <div class="text-xs text-muted font-weight-bold">Sales Canvas TIP TOK</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark px-2.5 py-1" style="font-size: 12px; font-weight: 800; border: 1px solid #cbd5e1;">
                                <i class="fa-solid fa-store text-primary me-1"></i> ${s.total_toko} Toko
                            </span>
                        </td>
                        <td>
                            <div class="d-flex justify-content-between align-items-center mb-1 text-xs font-weight-bold">
                                <span class="text-dark">Laku: <strong>${s.total_terjual} Unit</strong></span>
                                <span class="text-muted">${s.claim_progress}% Menuju Klaim</span>
                            </div>
                            <div class="progress-meter-3d mb-1">
                                <div class="progress-meter-bar-3d" style="width: ${s.claim_progress}%;"></div>
                            </div>
                            <div class="text-xs text-muted">
                                ${s.units_needed > 0 ? `Kurang <span class="text-danger font-weight-bold">${s.units_needed} Unit</span> untuk klaim insentif` : '<span class="text-success font-weight-bold"><i class="fa-solid fa-circle-check"></i> Memenuhi Syarat Klaim!</span>'}
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <span class="badge-verified-inv" style="font-size: 11px;">
                                    <i class="fa-solid fa-file-invoice"></i> ${s.invoiced_unit} Unit Ber-Invoice (${s.count_invoices} Faktur)
                                </span>
                                ${s.pending_unit > 0 ? `
                                    <span class="badge-pending-inv" style="font-size: 10.5px;">
                                        <i class="fa-solid fa-clock"></i> ${s.pending_unit} Unit Belum Invoice
                                    </span>
                                ` : ''}
                            </div>
                        </td>
                        <td class="text-end">
                            <div style="font-family: var(--font-heading); font-size: 16px; font-weight: 800; color: #047857;">
                                Rp ${new Intl.NumberFormat('id-ID').format(s.total_insentif)}
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2.5 font-weight-bold mt-1 mb-0" style="border-radius: 8px; font-size: 11.5px;" onclick="selectSalesFilterAndSwitch(${s.id_sales}, '${escapeHtml(s.nama_sales)}')">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Lihat Faktur
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function selectSalesFilterAndSwitch(idSales, nameSales) {
            switchTableView('list');
            selectSalesFilter(idSales, nameSales);
        }

        function renderInvoicesTable(items) {
            const tbody = document.getElementById('invoicesTableBody');
            selectedTrxIds.clear();
            updateBatchActionBar();

            const checkAll = document.getElementById('checkAllItems');
            if (checkAll) checkAll.checked = false;

            if (!items || items.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="d-inline-flex p-3 rounded-circle bg-light text-muted mb-2">
                                <i class="fa-solid fa-receipt fa-2x"></i>
                            </div>
                            <div class="font-weight-bold text-dark text-base mb-1">Tidak Ada Data Penjualan Ditemukan</div>
                            <p class="text-sm text-muted mb-0">Belum ada transaksi audit penjualan yang sesuai dengan filter pencarian.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            items.forEach(it => {
                const isPending = (!it.no_inv || it.no_inv.trim() === '');
                const qty = parseInt(it.qty_terjual_kunjungan) || 0;
                const insUnit = parseFloat(it.insentif_per_unit) || 0;
                const subtotalIns = parseFloat(it.insentif_didapat) || (qty * insUnit);
                const isClaimed = (it.id_claim && parseInt(it.id_claim) > 0);

                const statusPill = isPending ? `
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <span class="badge-pending-inv">
                            <i class="fa-solid fa-clock"></i> Belum Diinput
                        </span>
                        <button type="button" class="btn-brand-amber" onclick="openSingleInvoiceModal(${it.id_kunjungan})">
                            <i class="fa-solid fa-plus"></i> Input Invoice
                        </button>
                    </div>
                ` : `
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div>
                            <span class="badge-verified-inv">
                                <i class="fa-solid fa-file-invoice"></i> ${escapeHtml(it.no_inv)}
                            </span>
                            ${it.tgl_invoice ? `<div class="text-xs text-muted font-weight-bold mt-1"><i class="fa-regular fa-calendar me-1"></i> ${escapeHtml(it.tgl_invoice)}</div>` : ''}
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 mb-0" style="border-radius: 6px;" title="Edit No. Invoice" onclick="openSingleInvoiceModal(${it.id_kunjungan})">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            ${!isClaimed ? `
                                <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 mb-0" style="border-radius: 6px;" title="Reset No. Invoice" onclick="hapusInvoiceSingle(${it.id_kunjungan}, '${escapeHtml(it.no_inv)}')">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            ` : `
                                <span class="badge bg-light text-muted" style="font-size:10px;" title="Terkunci dalam klaim">Terkunci</span>
                            `}
                        </div>
                    </div>
                `;

                html += `
                    <tr id="rowTrx_${it.id_kunjungan}">
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input row-checkbox" value="${it.id_kunjungan}" style="cursor: pointer;" onchange="onRowCheckboxChange(this, ${it.id_kunjungan})">
                        </td>
                        <td>
                            <div class="font-weight-bold text-dark" style="font-size: 14px;">${escapeHtml(it.nama_toko || 'Toko Mitra')}</div>
                            <div class="d-flex align-items-center gap-2 mt-0.5">
                                ${it.kategori_toko ? `<span class="badge bg-light text-secondary px-2 py-0.5" style="font-size: 10.5px; border: 1px solid #cbd5e1;">${escapeHtml(it.kategori_toko)}</span>` : ''}
                                <span class="text-xs text-muted font-weight-bold">${escapeHtml(it.kota_toko || '')}</span>
                            </div>
                        </td>
                        <td>
                            <div class="font-weight-bold text-dark" style="font-size: 13.5px;">${escapeHtml(it.nama_barang)}</div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="badge bg-danger text-white px-2 py-0.5" style="font-size: 11.5px; font-weight: 800;">
                                    Laku: ${qty} Unit
                                </span>
                                <span class="text-xs text-muted font-weight-bold">(@ Rp ${new Intl.NumberFormat('id-ID').format(insUnit)})</span>
                            </div>
                        </td>
                        <td class="text-end">
                            <div style="font-family: var(--font-heading); font-size: 16px; font-weight: 800; color: #047857;">
                                Rp ${new Intl.NumberFormat('id-ID').format(subtotalIns)}
                            </div>
                            <div class="text-xs text-muted font-weight-bold">Estimasi Reward</div>
                        </td>
                        <td>
                            <div class="font-weight-bold text-dark" style="font-size: 13px;"><i class="fa-regular fa-calendar-check text-primary me-1"></i> ${escapeHtml(it.tgl_kunjungan)}</div>
                            <div class="font-monospace text-xs text-secondary font-weight-bold mt-0.5">${escapeHtml(it.kode_kunjungan)}</div>
                            <div class="mt-1">
                                <span class="badge" style="background: rgba(37,99,235,0.1); color: #1d4ed8; border: 1px solid rgba(37,99,235,0.25); font-size: 11px; padding: 3px 8px; border-radius: 6px; font-weight: 700;">
                                    <i class="fa-solid fa-user-tie me-1"></i> ${escapeHtml(it.nama_sales || 'Sales')}
                                </span>
                            </div>
                        </td>
                        <td>
                            ${statusPill}
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function onRowCheckboxChange(cb, id) {
            if (cb.checked) {
                selectedTrxIds.add(id);
            } else {
                selectedTrxIds.delete(id);
            }
            updateBatchActionBar();
        }

        function toggleCheckAll(master) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = master.checked;
                const id = parseInt(cb.value);
                if (master.checked) selectedTrxIds.add(id);
                else selectedTrxIds.delete(id);
            });
            updateBatchActionBar();
        }

        function unselectAllCheckboxes() {
            selectedTrxIds.clear();
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            const checkAll = document.getElementById('checkAllItems');
            if (checkAll) checkAll.checked = false;
            updateBatchActionBar();
        }

        function updateBatchActionBar() {
            const bar = document.getElementById('batchActionBar');
            const count = selectedTrxIds.size;
            if (count > 0) {
                bar.classList.remove('d-none');
                
                let sumUnit = 0;
                let sumIns = 0;
                selectedTrxIds.forEach(id => {
                    const it = invoicesData.find(x => x.id_kunjungan == id);
                    if (it) {
                        sumUnit += (parseInt(it.qty_terjual_kunjungan) || 0);
                        sumIns += (parseFloat(it.insentif_didapat) || 0);
                    }
                });

                document.getElementById('batchSelectedText').textContent = `${count} Transaksi Penjualan Terpilih`;
                document.getElementById('batchSubtotalInfo').textContent = `Total Fisik: ${sumUnit} Unit | Total Estimasi Insentif: Rp ${new Intl.NumberFormat('id-ID').format(sumIns)}`;
            } else {
                bar.classList.add('d-none');
            }
        }

        // =========================================================================
        // MODAL SINGLE INVOICE
        // =========================================================================
        function openSingleInvoiceModal(idKunjungan) {
            const it = invoicesData.find(x => x.id_kunjungan == idKunjungan);
            if (!it) return;

            document.getElementById('singleIdKunjungan').value = idKunjungan;
            document.getElementById('singlePrevNamaToko').textContent = it.nama_toko || 'Toko Mitra';
            document.getElementById('singlePrevNamaBarang').textContent = it.nama_barang || '-';
            document.getElementById('singlePrevQty').textContent = `Terjual: ${it.qty_terjual_kunjungan} Unit`;
            document.getElementById('singlePrevInsentif').textContent = `Subtotal Reward: Rp ${new Intl.NumberFormat('id-ID').format(it.insentif_didapat || 0)}`;

            document.getElementById('singleInputNoInv').value = it.no_inv || '';
            document.getElementById('singleInputTglInv').value = it.tgl_invoice || '<?php echo date('Y-m-d'); ?>';
            document.getElementById('singleInputCatatan').value = it.catatan_kunjungan || '';

            const isEdit = (it.no_inv && it.no_inv.trim() !== '');
            document.getElementById('singleModalTitle').textContent = isEdit ? 'Edit No. Invoice' : 'Input No. Invoice';
            document.getElementById('singleModalSubtitle').textContent = isEdit ? 'Perbarui nomor faktur penjualan yang telah diinput' : 'Tetapkan nomor faktur penjualan TIP TOK';

            showModalSafe('modalSingleInvoice');
        }

        function submitSingleInvoice(e) {
            e.preventDefault();
            const form = document.getElementById('formSingleInvoice');
            const formData = new FormData(form);
            formData.append('action', 'simpan_invoice_item');

            const btn = document.getElementById('btnSaveSingleInvoice');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';
            }

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Invoice';
                    }
                    if (res && res.status === 'success') {
                        hideModalSafe('modalSingleInvoice');
                        Swal.fire({
                            icon: 'success',
                            title: 'Tersimpan!',
                            text: res.message || 'Nomor Invoice berhasil disimpan.',
                            timer: 1600,
                            showConfirmButton: false
                        });
                        loadInvoicesData();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: (res && res.message) ? res.message : 'Terjadi kesalahan.' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Simpan Invoice';
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan.' });
                });
        }

        // =========================================================================
        // MODAL BATCH INVOICE
        // =========================================================================
        function openBatchInvoiceModal() {
            const count = selectedTrxIds.size;
            if (count === 0) {
                Swal.fire({ icon: 'warning', title: 'Pilih Transaksi', text: 'Centang minimal satu transaksi untuk mengisi No. Invoice.' });
                return;
            }

            let sumUnit = 0;
            let tokoSet = new Set();
            selectedTrxIds.forEach(id => {
                const it = invoicesData.find(x => x.id_kunjungan == id);
                if (it) {
                    sumUnit += (parseInt(it.qty_terjual_kunjungan) || 0);
                    if (it.nama_toko) tokoSet.add(it.nama_toko);
                }
            });

            document.getElementById('batchModalSelectedCount').textContent = `${count} Transaksi Terpilih`;
            document.getElementById('batchModalTotalUnit').textContent = `${sumUnit} Unit Terjual`;
            document.getElementById('batchModalDealerSummary').textContent = `Toko: ${Array.from(tokoSet).join(', ') || '-'}`;
            document.getElementById('batchInputNoInv').value = '';
            document.getElementById('batchInputTglInv').value = '<?php echo date('Y-m-d'); ?>';

            showModalSafe('modalBatchInvoice');
        }

        function submitBatchInvoice(e) {
            e.preventDefault();
            const noInv = document.getElementById('batchInputNoInv').value.trim();
            const tglInv = document.getElementById('batchInputTglInv').value;

            if (!noInv) {
                Swal.fire({ icon: 'warning', title: 'Wajib Diisi', text: 'Silakan isi Nomor Invoice kolektif.' });
                return;
            }

            const formData = new FormData();
            formData.append('action', 'simpan_batch_invoice');
            formData.append('no_inv', noInv);
            formData.append('tgl_invoice', tglInv);
            formData.append('id_kunjungan_list', Array.from(selectedTrxIds).join(','));

            const btn = document.getElementById('btnSaveBatchInvoice');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';
            }

            fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Terapkan ke Semua Item';
                    }
                    if (res && res.status === 'success') {
                        hideModalSafe('modalBatchInvoice');
                        Swal.fire({
                            icon: 'success',
                            title: 'Sukses!',
                            text: res.message || 'No. Invoice kolektif berhasil diterapkan.',
                            timer: 1800,
                            showConfirmButton: false
                        });
                        loadInvoicesData();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: (res && res.message) ? res.message : 'Gagal menyimpan invoice batch.' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Terapkan ke Semua Item';
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan.' });
                });
        }

        // =========================================================================
        // HAPUS / RESET NO. INVOICE
        // =========================================================================
        function hapusInvoiceSingle(idKunjungan, currentNoInv) {
            Swal.fire({
                title: 'Reset No. Invoice?',
                html: `Apakah Anda yakin ingin menghapus nomor faktur <strong>${escapeHtml(currentNoInv)}</strong> dari transaksi ini?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> Ya, Reset Invoice',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'hapus_invoice_item');
                    formData.append('id_kunjungan', idKunjungan);

                    fetch('tiptok-ajax.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(res => {
                            if (res && res.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Direset!',
                                    text: res.message || 'No. Invoice berhasil direset.',
                                    timer: 1400,
                                    showConfirmButton: false
                                });
                                loadInvoicesData();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal', text: (res && res.message) ? res.message : 'Gagal mereset invoice.' });
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan jaringan.' });
                        });
                }
            });
        }
    </script>
</body>

</html>
