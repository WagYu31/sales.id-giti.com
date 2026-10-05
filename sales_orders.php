<?php
/**
 * sales_orders.php
 * Daftar Riwayat Pesanan Penjualan (Sales Order List)
 */

$page_title = 'Daftar Pesanan Penjualan (Sales Order)';
require_once 'includes/db.php';
require_once 'includes/sales_order_helper.php';
ensureSalesOrderTables($conn);
require_once 'includes/header.php';

// Filter Parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$salesFilter = isset($_GET['sales_id']) ? (int)$_GET['sales_id'] : 0;
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$where = "WHERE so.deleted_at IS NULL";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (so.so_number LIKE ? OR so.customer_name LIKE ? OR so.customer_pic LIKE ? OR so.po_number LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= "ssss";
}

if (!empty($statusFilter)) {
    $where .= " AND so.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($salesFilter > 0) {
    $where .= " AND so.sales_id = ?";
    $params[] = $salesFilter;
    $types .= "i";
}

if (!empty($startDate)) {
    $where .= " AND so.so_date >= ?";
    $params[] = $startDate;
    $types .= "s";
}

if (!empty($endDate)) {
    $where .= " AND so.so_date <= ?";
    $params[] = $endDate;
    $types .= "s";
}

// Summary Statistics (Respects active filters)
$sqlStats = "SELECT 
    COUNT(DISTINCT so.id) as total_orders,
    COALESCE(SUM(so.grand_total), 0) as total_amount,
    COALESCE(SUM(CASE WHEN so.status = 'Menunggu' THEN 1 ELSE 0 END), 0) as total_waiting,
    COALESCE(SUM(CASE WHEN so.status = 'Diproses' THEN 1 ELSE 0 END), 0) as total_processing,
    COALESCE(SUM(CASE WHEN so.status = 'Selesai' THEN 1 ELSE 0 END), 0) as total_completed
FROM sales_orders so $where";

$stmtStats = $conn->prepare($sqlStats);
if (!empty($types)) {
    $stmtStats->bind_param($types, ...$params);
}
$stmtStats->execute();
$statsRes = $stmtStats->get_result();
$stats = $statsRes ? $statsRes->fetch_assoc() : [
    'total_orders' => 0, 'total_amount' => 0, 'total_waiting' => 0, 'total_processing' => 0, 'total_completed' => 0
];

$isFiltered = (!empty($search) || !empty($statusFilter) || $salesFilter > 0 || !empty($startDate) || !empty($endDate));

// Fetch Orders
$sqlOrders = "SELECT so.*, 
                     COUNT(soi.id) as total_items,
                     COALESCE(SUM(soi.qty), 0) as total_qty
              FROM sales_orders so
              LEFT JOIN sales_order_items soi ON soi.sales_order_id = so.id
              $where
              GROUP BY so.id
              ORDER BY so.so_date DESC, so.id DESC
              LIMIT 150";

$stmt = $conn->prepare($sqlOrders);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$ordersResult = $stmt->get_result();

// Sales List for filter
$salesList = [];
$qSales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE deleted_at IS NULL ORDER BY nama_lengkap ASC");
if ($qSales) {
    while ($s = $qSales->fetch_assoc()) $salesList[] = $s;
}
?>

<style>
/* ═════════════════════════════════════════════════════════
   MINIMALIST EDITORIAL & WARM MONOCHROME DESIGN SYSTEM
   Refined, quiet luxury UI with muted spot pastels
   ═════════════════════════════════════════════════════════ */
:root {
    --bg-canvas: #f8fafc;
    --surface-card: #ffffff;
    --border-subtle: #e2e8f0;
    --border-hover: #cbd5e1;
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #94a3b8;
    --accent-dark: #0f172a;

    /* Muted Spot Pastels (Quiet Luxury) */
    --pastel-green-bg: #edf3ec;
    --pastel-green-text: #2d5a27;
    --pastel-green-border: #d1e7dd;

    --pastel-red-bg: #fdebec;
    --pastel-red-text: #8f2d2a;
    --pastel-red-border: #f8d7da;

    --pastel-amber-bg: #fdf6e2;
    --pastel-amber-text: #855d00;
    --pastel-amber-border: #ffebaa;

    --pastel-blue-bg: #e1f3fe;
    --pastel-blue-text: #1e5c8a;
    --pastel-blue-border: #bee5eb;

    --pastel-slate-bg: #f1f5f9;
    --pastel-slate-text: #475569;
    --pastel-slate-border: #e2e8f0;
}

/* ── Hero Banner (Executive Architectural Minimalist) ── */
.so-hero-minimal {
    background: #0f172a;
    border-radius: 14px;
    padding: 24px 28px;
    color: #ffffff;
    border: 1px solid #1e293b;
    margin-bottom: 20px;
    position: relative;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.so-hero-minimal::after,
.so-hero-minimal::before {
    display: none !important;
}
.hero-tag {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    background: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.14);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}
.hero-title {
    font-size: 24px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.02em;
    margin: 0;
    line-height: 1.25;
}
.hero-desc {
    font-size: 13.5px;
    font-weight: 400;
    color: #94a3b8;
    margin: 6px 0 0 0;
    max-width: 620px;
    line-height: 1.5;
}
.btn-hero-primary {
    background: #ffffff;
    color: #0f172a !important;
    border: 1px solid #ffffff;
    border-radius: 8px;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    text-decoration: none;
    cursor: pointer;
}
.btn-hero-primary:hover {
    background: #f1f5f9;
    color: #0f172a !important;
    border-color: #f1f5f9;
    transform: translateY(-1px);
}
.btn-hero-outline {
    background: rgba(255, 255, 255, 0.06);
    color: #cbd5e1 !important;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
    text-decoration: none;
    cursor: pointer;
}
.btn-hero-outline:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.28);
    color: #ffffff !important;
    transform: translateY(-1px);
}

/* ── Bento Metrics Grid ── */
.metrics-grid-so {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 1100px) { .metrics-grid-so { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .metrics-grid-so { grid-template-columns: 1fr; } }

.metric-card-so {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.metric-card-so:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
.metric-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.metric-card-label {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.metric-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
}
.metric-card-val {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    line-height: 1.15;
    margin-bottom: 8px;
}
.metric-card-footer {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
}

/* ── Filter Card ── */
.filter-card-so {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 20px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.filter-input-so {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    color: #0f172a;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.filter-input-so:focus {
    border-color: #0f172a;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.06);
    outline: none;
}
.filter-addon-so {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 13px;
}
.btn-filter-submit {
    background: #0f172a;
    color: #ffffff;
    border: 1px solid #0f172a;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.15s ease;
}
.btn-filter-submit:hover {
    background: #1e293b;
    color: #ffffff;
}
.btn-filter-reset {
    background: #ffffff;
    color: #64748b;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}
.btn-filter-reset:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #cbd5e1;
}

/* ── Table Container & Headers ── */
.table-so-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    overflow: visible !important;
}
.table-responsive {
    overflow-x: auto;
    overflow-y: visible;
    min-height: 280px;
}
@media (min-width: 992px) {
    .table-responsive {
        overflow: visible !important;
    }
}
.table-so .dropdown-menu {
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1) !important;
    z-index: 1060 !important;
    min-width: 170px;
    padding: 6px;
}
.table-so .dropdown-item {
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 500;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    transition: all 0.12s ease;
}
.table-so .dropdown-item:hover {
    background-color: #f1f5f9;
}
.table-so .dropdown-item.active {
    background-color: #f8fafc;
    color: #0f172a;
    font-weight: 700;
}
.table-so {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}
.table-so thead th {
    background: #0f172a;
    color: #cbd5e1;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 13px 16px;
    border: none;
    white-space: nowrap;
}
.table-so tbody td {
    padding: 13px 16px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #1e293b;
}
.table-so tbody tr:last-child td {
    border-bottom: none;
}
.table-so tbody tr:hover td {
    background: #f8fafc;
}

/* ── Minimalist Status Badges (Muted Spot Pastels) ── */
.status-badge-so {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.15s ease;
}
.status-Draft {
    background: var(--pastel-slate-bg);
    color: var(--pastel-slate-text);
    border: 1px solid var(--pastel-slate-border);
}
.status-Menunggu {
    background: var(--pastel-amber-bg);
    color: var(--pastel-amber-text);
    border: 1px solid var(--pastel-amber-border);
}
.status-Diproses {
    background: var(--pastel-blue-bg);
    color: var(--pastel-blue-text);
    border: 1px solid var(--pastel-blue-border);
}
.status-Selesai {
    background: var(--pastel-green-bg);
    color: var(--pastel-green-text);
    border: 1px solid var(--pastel-green-border);
}
.status-Dibatalkan {
    background: var(--pastel-red-bg);
    color: var(--pastel-red-text);
    border: 1px solid var(--pastel-red-border);
}

/* ── Minimalist Action Buttons ── */
.btn-action-so {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    transition: all 0.15s ease;
    text-decoration: none;
    box-shadow: none;
}
.btn-action-so:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}
.btn-action-so.btn-view-so:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #2563eb;
}
.btn-action-so.btn-delete:hover {
    background: var(--pastel-red-bg);
    border-color: var(--pastel-red-border);
    color: var(--pastel-red-text);
}

/* ── Next-Gen Executive Modal Detail SO Styling ── */
#modalDetailSo .modal-content {
    border-radius: 20px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35);
    overflow: hidden;
    background: #ffffff;
}
#modalDetailSo .modal-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    padding: 18px 26px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
#modalDetailSo .header-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(56, 189, 248, 0.15);
    border: 1px solid rgba(56, 189, 248, 0.3);
    color: #38bdf8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
#modalDetailSo .modal-so-badge {
    background: rgba(255, 255, 255, 0.12);
    color: #f8fafc;
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    padding: 4px 10px;
    border-radius: 8px;
    letter-spacing: 0.03em;
}
#modalDetailSo .btn-copy-header {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #cbd5e1;
    font-size: 11.5px;
    padding: 3px 8px;
    border-radius: 6px;
    transition: all 0.15s ease;
    cursor: pointer;
}
#modalDetailSo .btn-copy-header:hover {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Stepper Status Bar */
.so-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    padding: 14px 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 22px;
}
.so-stepper-progress-line {
    position: absolute;
    top: 28px;
    left: 45px;
    right: 45px;
    height: 3px;
    background: #e2e8f0;
    z-index: 1;
}
.so-stepper-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #2563eb 100%);
    transition: width 0.4s ease;
    width: 0%;
}
.so-step-item {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    background: #f8fafc;
    padding: 0 10px;
}
.so-step-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.2s ease;
}
.so-step-item.completed .so-step-circle {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}
.so-step-item.active .so-step-circle {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
    animation: pulseStep 2s infinite;
}
@keyframes pulseStep {
    0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); }
    70% { box-shadow: 0 0 0 8px rgba(37, 99, 235, 0); }
    100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
}
.so-step-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}
.so-step-item.completed .so-step-label,
.so-step-item.active .so-step-label {
    color: #0f172a;
    font-weight: 700;
}

/* Info Cards */
.so-info-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 18px;
    height: 100%;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}
.so-info-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 16px -4px rgba(15, 23, 42, 0.08);
}
.so-info-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}
.so-info-card.card-blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.so-info-card.card-emerald::before { background: linear-gradient(90deg, #10b981, #34d399); }
.so-info-card.card-violet::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

.so-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.so-card-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}
.icon-blue { background: #eff6ff; color: #2563eb; }
.icon-emerald { background: #ecfdf5; color: #059669; }
.icon-violet { background: #f5f3ff; color: #7c3aed; }

/* Enterprise Items Table */
.so-items-container {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
}
.table-so-detail {
    font-size: 12.5px;
    margin-bottom: 0;
}
.table-so-detail th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 12px 14px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.table-so-detail td {
    padding: 11px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.row-parent-package {
    background: #f8fafc !important;
    border-left: 3px solid #2563eb !important;
}
.row-parent-package td {
    font-weight: 600;
}
.row-bundle-subitem {
    background: #fbfcfe !important;
}
.row-bundle-subitem td {
    padding-top: 8px;
    padding-bottom: 8px;
}

/* Executive Financial Summary Card */
.so-summary-box {
    background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    padding: 20px 22px;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3);
}
.so-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 13px;
    color: #cbd5e1;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.so-summary-grand {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 14px;
    margin-top: 6px;
}
.so-grand-val {
    font-size: 24px;
    font-weight: 800;
    color: #38bdf8;
    font-family: 'JetBrains Mono', monospace;
    letter-spacing: -0.02em;
}
.so-terbilang-box {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 10px 14px;
    margin-top: 14px;
    font-size: 11.5px;
    color: #94a3b8;
    font-style: italic;
    line-height: 1.4;
}
</style>

<!-- 1. Minimalist Hero Banner Header -->
<div class="so-hero-minimal">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="hero-tag">
                <i class="bi bi-box-seam me-1"></i> APLIKASI SALES &amp; PESANAN
            </div>
            <h1 class="hero-title">
                Pesanan Penjualan
            </h1>
            <p class="hero-desc">
                Manajemen Sales Order, penawaran harga, dan pembuatan dokumen cetak resmi CCTV Loewix.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="modul-aplikasi-sales/tiptok.php" class="btn-hero-outline">
                <i class="bi bi-box-seam"></i>
                <span>Titip Barang</span>
            </a>
            <a href="modul-aplikasi-sales/tiptok-invoice.php" class="btn-hero-outline">
                <i class="bi bi-receipt"></i>
                <span>No. Invoice TIP TOK</span>
            </a>
            <a href="sales_order_form.php" class="btn-hero-primary">
                <i class="bi bi-plus-lg"></i>
                <span>Buat Pesanan Baru</span>
            </a>
            <a href="modul-aplikasi-sales/tiptok.php" class="btn-hero-outline">
                <i class="bi bi-cash-coin"></i>
                <span>Klaim Insentif</span>
            </a>
        </div>
    </div>
</div>

<!-- 2. Minimalist Bento Metrics Grid -->
<div class="metrics-grid-so">
    <!-- Metric 1: Total Pesanan -->
    <div class="metric-card-so">
        <div>
            <div class="metric-card-header">
                <span class="metric-card-label">Total Pesanan</span>
                <div class="metric-card-icon">
                    <i class="bi bi-cart-check"></i>
                </div>
            </div>
            <div class="metric-card-val font-monospace">
                <?php echo number_format($stats['total_orders'] ?? 0); ?>
            </div>
        </div>
        <div class="metric-card-footer">
            <span class="badge" style="background:var(--pastel-slate-bg); color:var(--pastel-slate-text); border:1px solid var(--pastel-slate-border); font-weight:600;">
                <?php echo $isFiltered ? 'Sesuai Filter' : 'Semua Pesanan'; ?>
            </span>
            <span>transaksi terdaftar</span>
        </div>
    </div>

    <!-- Metric 2: Total Nilai Penjualan -->
    <div class="metric-card-so">
        <div>
            <div class="metric-card-header">
                <span class="metric-card-label">Total Nilai Penjualan</span>
                <div class="metric-card-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            <div class="metric-card-val font-monospace" style="font-size:22px;">
                Rp <?php echo number_format($stats['total_amount'] ?? 0, 0, ',', '.'); ?>
            </div>
        </div>
        <div class="metric-card-footer">
            <span class="badge" style="background:var(--pastel-slate-bg); color:var(--pastel-slate-text); border:1px solid var(--pastel-slate-border); font-weight:600;">
                Akumulasi SO
            </span>
            <span>gross sales</span>
        </div>
    </div>

    <!-- Metric 3: Menunggu Diproses -->
    <div class="metric-card-so">
        <div>
            <div class="metric-card-header">
                <span class="metric-card-label">Menunggu Diproses</span>
                <div class="metric-card-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
            </div>
            <div class="metric-card-val font-monospace">
                <?php echo number_format($stats['total_waiting'] ?? 0); ?>
            </div>
        </div>
        <div class="metric-card-footer">
            <span class="badge" style="background:var(--pastel-amber-bg); color:var(--pastel-amber-text); border:1px solid var(--pastel-amber-border); font-weight:700;">
                <i class="bi bi-hourglass-split me-1"></i> Perlu Tindakan
            </span>
            <span>antrean proses</span>
        </div>
    </div>

    <!-- Metric 4: Selesai / Terkirim -->
    <div class="metric-card-so">
        <div>
            <div class="metric-card-header">
                <span class="metric-card-label">Selesai / Terkirim</span>
                <div class="metric-card-icon">
                    <i class="bi bi-check2-all"></i>
                </div>
            </div>
            <div class="metric-card-val font-monospace">
                <?php echo number_format($stats['total_completed'] ?? 0); ?>
            </div>
        </div>
        <div class="metric-card-footer">
            <span class="badge" style="background:var(--pastel-green-bg); color:var(--pastel-green-text); border:1px solid var(--pastel-green-border); font-weight:700;">
                <i class="bi bi-check2-circle me-1"></i> Terpenuhi
            </span>
            <span>sukses terkirim</span>
        </div>
    </div>
</div>

<!-- 3. Minimalist Filter Bar -->
<div class="filter-card-so">
    <form method="GET" action="sales_orders.php" class="row g-2 align-items-center">
        <div class="col-lg-3 col-md-6">
            <div class="input-group input-group-sm">
                <span class="input-group-text filter-addon-so"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control filter-input-so" placeholder="Cari No. SO, Toko, PIC..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>

        <div class="col-lg-2 col-md-3 col-6">
            <select name="status" class="form-select form-select-sm filter-input-so">
                <option value="">-- Semua Status --</option>
                <?php
                $optStatuses = ['Draft', 'Menunggu', 'Diproses', 'Selesai', 'Dibatalkan'];
                foreach ($optStatuses as $st) {
                    $sel = ($statusFilter === $st) ? 'selected' : '';
                    echo "<option value='{$st}' {$sel}>Status: {$st}</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-lg-2 col-md-3 col-6">
            <select name="sales_id" class="form-select form-select-sm filter-input-so">
                <option value="">-- Semua Sales --</option>
                <?php foreach ($salesList as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo ($salesFilter == $s['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="input-group input-group-sm">
                <input type="date" name="start_date" class="form-control filter-input-so" title="Dari Tanggal" value="<?php echo htmlspecialchars($startDate); ?>">
                <span class="input-group-text filter-addon-so">s/d</span>
                <input type="date" name="end_date" class="form-control filter-input-so" title="Sampai Tanggal" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
        </div>

        <div class="col-lg-2 col-md-6 d-flex gap-2">
            <button type="submit" class="btn-filter-submit w-100">
                <i class="bi bi-funnel"></i>
                <span>Filter</span>
            </button>
            <?php if ($isFiltered): ?>
                <a href="sales_orders.php" class="btn-filter-reset" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- 4. Minimalist Orders Table -->
<div class="table-so-container">
    <div class="table-responsive">
        <table class="table table-so mb-0">
            <thead>
                <tr>
                    <th style="width: 150px;">No. Pesanan (SO)</th>
                    <th style="width: 110px;">Tanggal</th>
                    <th>Customer / Toko</th>
                    <th style="width: 140px;">Sales PIC</th>
                    <th style="width: 90px; text-align: center;">Item</th>
                    <th style="width: 150px; text-align: right;">Total Nilai (Rp)</th>
                    <th style="width: 120px;">Syarat Bayar</th>
                    <th style="width: 130px; text-align: center;">Status</th>
                    <th style="width: 120px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ordersResult && $ordersResult->num_rows > 0): ?>
                    <?php 
                    $totalOrdersCount = $ordersResult->num_rows;
                    $orderIndex = 0;
                    while ($row = $ordersResult->fetch_assoc()): 
                        $orderIndex++;
                        $isBottomRow = ($orderIndex >= $totalOrdersCount - 1 && $totalOrdersCount > 1);
                    ?>
                        <tr id="row-so-<?php echo $row['id']; ?>">
                            <td>
                                <a href="javascript:void(0)" class="fw-bold font-monospace text-decoration-none text-dark btn-view-so" data-id="<?php echo $row['id']; ?>" title="Klik untuk Lihat Detail Pesanan">
                                    <?php if (!empty($row['so_number'])): ?>
                                        <?php echo htmlspecialchars($row['so_number']); ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:11px; font-family:inherit; font-weight:600; padding:4px 8px;">
                                            <i class="bi bi-hourglass-split me-1"></i>Menunggu No. SO
                                        </span>
                                    <?php endif; ?>
                                </a>
                                <?php if (!empty($row['po_number'])): ?>
                                    <div class="text-muted" style="font-size:11px;">PO: <?php echo htmlspecialchars($row['po_number']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-secondary fw-semibold">
                                    <?php echo date('d/m/Y', strtotime($row['so_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                </div>
                                <?php if (!empty($row['customer_pic']) || !empty($row['customer_phone'])): ?>
                                    <div class="text-muted" style="font-size:11.5px;">
                                        <?php echo htmlspecialchars($row['customer_pic'] ?? ''); ?>
                                        <?php if (!empty($row['customer_phone'])) echo ' (' . htmlspecialchars($row['customer_phone']) . ')'; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background:var(--pastel-slate-bg); color:var(--pastel-slate-text); border:1px solid var(--pastel-slate-border); font-weight:600;">
                                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($row['sales_name'] ?: '-'); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="fw-bold font-monospace"><?php echo (int)$row['total_qty']; ?></span>
                                <div class="text-muted" style="font-size:11px;"><?php echo (int)$row['total_items']; ?> tipe</div>
                            </td>
                            <td style="text-align: right;">
                                <div class="fw-bold text-dark font-monospace" style="font-size:13.5px;">
                                    Rp <?php echo number_format($row['grand_total'], 0, ',', '.'); ?>
                                </div>
                                <?php if ($row['discount_amount'] > 0): ?>
                                    <div class="small" style="font-size:11px; color:var(--pastel-red-text);">Disc: Rp <?php echo number_format($row['discount_amount'], 0, ',', '.'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background:#ffffff; color:#475569; border:1px solid #e2e8f0; font-weight:500;">
                                    <?php echo htmlspecialchars($row['payment_terms']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="dropdown <?php echo $isBottomRow ? 'dropup' : ''; ?>">
                                    <button class="status-badge-so status-<?php echo $row['status']; ?> dropdown-toggle" 
                                            type="button" 
                                            data-bs-toggle="dropdown" 
                                            data-bs-auto-close="true"
                                            data-bs-boundary="viewport"
                                            data-bs-popper-config='{"strategy":"fixed"}'
                                            aria-expanded="false">
                                        <?php echo $row['status']; ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border" style="font-size:12.5px; border-color:#e2e8f0 !important; border-radius:10px; z-index: 1060; min-width: 170px;">
                                        <li><h6 class="dropdown-header text-uppercase" style="font-size:10.5px; letter-spacing:0.04em;">Ubah Status SO</h6></li>
                                        <?php foreach ($optStatuses as $stOption): ?>
                                            <li>
                                                <a class="dropdown-item btn-change-status py-1.5 <?php echo ($row['status'] === $stOption) ? 'active fw-bold' : ''; ?>" 
                                                    href="javascript:void(0)" 
                                                    data-id="<?php echo $row['id']; ?>" 
                                                    data-status="<?php echo $stOption; ?>">
                                                    <span class="status-badge-so status-<?php echo $stOption; ?> py-0 px-2 me-1" style="font-size:10px;">●</span> 
                                                    <span><?php echo $stOption; ?></span>
                                                    <?php if ($row['status'] === $stOption): ?>
                                                        <i class="bi bi-check2 ms-auto text-primary"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" class="btn-action-so btn-view-so" data-id="<?php echo $row['id']; ?>" title="Lihat Detail Pesanan">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="sales_order_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-action-so" title="Cetak Dokumen SO">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="sales_order_form.php?id=<?php echo $row['id']; ?>" class="btn-action-so" title="Edit Pesanan">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn-action-so btn-delete btn-delete-so" data-id="<?php echo $row['id']; ?>" data-num="<?php echo htmlspecialchars(!empty($row['so_number']) ? $row['so_number'] : ('ID #' . $row['id'])); ?>" title="Hapus Pesanan">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-bold mb-1 text-dark">Belum Ada Pesanan Penjualan</h6>
                            <p class="small text-muted mb-3">Mulai buat pesanan penjualan pertama untuk toko atau customer Anda.</p>
                            <a href="sales_order_form.php" class="btn-filter-submit px-3 py-2 text-decoration-none">
                                <i class="bi bi-plus-lg me-1"></i> Buat Pesanan Sekarang
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Detail Sales Order (Next-Gen Executive View) -->
<div class="modal fade" id="modalDetailSo" tabindex="-1" aria-labelledby="modalDetailSoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <!-- Sleek Dark Gradient Header -->
            <div class="modal-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon-box shadow-sm">
                        <i class="bi bi-receipt-cutoff fs-4"></i>
                    </div>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h5 class="modal-title fw-bold text-white mb-0" id="modalDetailSoLabel" style="letter-spacing: -0.01em;">Pesanan Penjualan</h5>
                            <span id="modalDetailSoNumBadge" class="modal-so-badge"></span>
                            <button type="button" class="btn btn-copy-header" id="btnCopySoNum" title="Salin Nomor SO">
                                <i class="bi bi-copy me-1"></i> Salin
                            </button>
                        </div>
                        <div class="text-white-50 mt-0.5" style="font-size: 11.5px;">
                            <span id="modalDetailSoSubtitle">Memuat data...</span>
                            <span class="mx-1">•</span>
                            <span id="modalDetailBranch">Kantor Pusat</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="modalDetailStatusBadge"></span>
                    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body p-4" style="background: #ffffff;">
                <!-- Loading State -->
                <div id="modalDetailLoading" class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 2.8rem; height: 2.8rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-dark fw-semibold mb-1">Memuat Rincian Pesanan...</p>
                    <p class="text-muted small mb-0">Sinkronisasi data pesanan, rincian produk, dan komponen paket...</p>
                </div>

                <!-- Error State -->
                <div id="modalDetailError" class="alert alert-danger d-none my-3 rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i>
                    <span id="modalDetailErrorMessage">Gagal memuat detail pesanan.</span>
                </div>

                <!-- Content State -->
                <div id="modalDetailBody" class="d-none">
                    <!-- 1. Interactive Order Stepper Progress Bar -->
                    <div class="so-stepper" id="modalDetailStepper">
                        <div class="so-stepper-progress-line">
                            <div class="so-stepper-progress-fill" id="modalStepperFill"></div>
                        </div>
                        <div class="so-step-item" id="step-Draft">
                            <div class="so-step-circle"><i class="bi bi-file-earmark-text"></i></div>
                            <span class="so-step-label">Draft SO</span>
                        </div>
                        <div class="so-step-item" id="step-Menunggu">
                            <div class="so-step-circle"><i class="bi bi-hourglass-split"></i></div>
                            <span class="so-step-label">Menunggu</span>
                        </div>
                        <div class="so-step-item" id="step-Diproses">
                            <div class="so-step-circle"><i class="bi bi-gear-wide-connected"></i></div>
                            <span class="so-step-label">Diproses</span>
                        </div>
                        <div class="so-step-item" id="step-Selesai">
                            <div class="so-step-circle"><i class="bi bi-check2-circle"></i></div>
                            <span class="so-step-label">Selesai / Terkirim</span>
                        </div>
                    </div>

                    <!-- 2. High-Contrast Executive Info Cards -->
                    <div class="row g-3 mb-4">
                        <!-- Card 1: Data Pesanan -->
                        <div class="col-md-4">
                            <div class="so-info-card card-blue">
                                <div class="so-card-header">
                                    <div class="so-card-icon icon-blue">
                                        <i class="bi bi-file-earmark-ruled"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 13px;">Data Pesanan</div>
                                        <div class="text-muted" style="font-size: 11px;">Identitas &amp; Termin</div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between py-1.5 border-bottom" style="font-size: 12.5px;">
                                    <span class="text-muted">No. Pesanan:</span>
                                    <span class="fw-bold font-monospace text-dark" id="modalSoNumber">-</span>
                                </div>
                                <div class="d-flex justify-content-between py-1.5 border-bottom" style="font-size: 12.5px;">
                                    <span class="text-muted">Tanggal SO:</span>
                                    <span class="fw-semibold text-dark" id="modalSoDate">-</span>
                                </div>
                                <div class="d-flex justify-content-between py-1.5 border-bottom" style="font-size: 12.5px;">
                                    <span class="text-muted">No. PO Cust:</span>
                                    <span class="fw-semibold text-dark" id="modalSoPo">-</span>
                                </div>
                                <div class="d-flex justify-content-between py-1.5" style="font-size: 12.5px;">
                                    <span class="text-muted">Syarat Bayar:</span>
                                    <span id="modalSoTerms">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Pelanggan / Toko -->
                        <div class="col-md-4">
                            <div class="so-info-card card-emerald">
                                <div class="so-card-header">
                                    <div class="so-card-icon icon-emerald">
                                        <i class="bi bi-shop"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 13px;">Pelanggan / Toko</div>
                                        <div class="text-muted" style="font-size: 11px;">Kontak &amp; Alamat</div>
                                    </div>
                                </div>
                                <div class="fw-bold text-dark mb-1" style="font-size: 13.5px;" id="modalCustomerName">-</div>
                                <div class="text-muted mb-2 d-flex flex-wrap align-items-center gap-1" style="font-size: 12px;">
                                    <span><i class="bi bi-person me-1"></i><span id="modalCustomerPic">-</span></span>
                                    <span class="mx-1 text-muted">•</span>
                                    <span id="modalCustomerPhone">-</span>
                                </div>
                                <div class="text-secondary small pt-1.5 border-top d-flex justify-content-between align-items-start" style="font-size: 11.5px; line-height: 1.4;">
                                    <span id="modalCustomerAddress" class="text-truncate-2">-</span>
                                    <span id="modalCustomerMapLink" class="ms-1 flex-shrink-0"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Logistik & Sales PIC -->
                        <div class="col-md-4">
                            <div class="so-info-card card-violet">
                                <div class="so-card-header">
                                    <div class="so-card-icon icon-violet">
                                        <i class="bi bi-truck"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 13px;">Logistik &amp; Sales</div>
                                        <div class="text-muted" style="font-size: 11px;">Ekspedisi &amp; PIC</div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between py-1.5 border-bottom" style="font-size: 12.5px;">
                                    <span class="text-muted">Sales PIC:</span>
                                    <span class="fw-semibold text-dark" id="modalSalesName">-</span>
                                </div>
                                <div class="d-flex justify-content-between py-1.5 border-bottom" style="font-size: 12.5px;">
                                    <span class="text-muted">Tgl Kirim:</span>
                                    <span class="fw-semibold text-dark" id="modalShippingDate">-</span>
                                </div>
                                <div class="d-flex justify-content-between py-1.5 border-bottom" style="font-size: 12.5px;">
                                    <span class="text-muted">Metode Kirim:</span>
                                    <span class="badge bg-light text-dark border" id="modalShippingMethod">-</span>
                                </div>
                                <div class="py-1.5" style="font-size: 11.5px;">
                                    <span class="text-muted d-block">Alamat Pengiriman:</span>
                                    <span class="text-secondary" id="modalShippingAddress">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Enterprise BOM Items Table (Hierarchical Breakdown) -->
                    <div class="so-items-container mb-4">
                        <div class="bg-light py-2.5 px-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark" style="font-size: 12.5px;">
                                    <i class="bi bi-boxes text-primary me-1"></i> Rincian Produk &amp; Komponen
                                </span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 11px;" id="modalTotalItemsBadge">0 Item</span>
                            </div>
                            <div class="text-muted" style="font-size: 11.5px;">
                                <i class="bi bi-info-circle me-1"></i> Komponen paket otomatis ditandai <em>Termasuk Paket</em>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-so-detail mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px; text-align: center;">No</th>
                                        <th style="width: 130px;">Kode / SKU</th>
                                        <th>Nama Barang &amp; Deskripsi Spesifikasi</th>
                                        <th style="width: 75px; text-align: center;">Qty</th>
                                        <th style="width: 75px; text-align: center;">Satuan</th>
                                        <th style="width: 140px; text-align: right;">Harga Satuan</th>
                                        <th style="width: 90px; text-align: center;">Diskon (%)</th>
                                        <th style="width: 150px; text-align: right;">Total Harga</th>
                                    </tr>
                                </thead>
                                <tbody id="modalItemsTableBody">
                                    <!-- Populated dynamically via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 4. Modern Financial Summary Box & Special Notes -->
                    <div class="row g-3">
                        <!-- Notes Card -->
                        <div class="col-md-6">
                            <div class="p-3 rounded-4 h-100" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="text-uppercase fw-bold text-secondary mb-2" style="font-size: 11px; letter-spacing: 0.05em;">
                                    <i class="bi bi-chat-left-quote text-primary me-1"></i> Catatan Khusus Pesanan
                                </div>
                                <div class="text-dark p-2.5 rounded-3 bg-white border" style="font-size: 12.5px; min-height: 80px; white-space: pre-wrap;" id="modalSpecialNotes">
                                    <em>Tidak ada catatan khusus.</em>
                                </div>
                                <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between text-muted" style="font-size: 11px;">
                                    <span>Status Dokumen: <strong>Resmi &amp; Valid</strong></span>
                                    <span id="modalCreatedAtInfo"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Summary Dark Card -->
                        <div class="col-md-6">
                            <div class="so-summary-box">
                                <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom border-secondary border-opacity-25">
                                    <span class="text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 0.05em; color: #94a3b8;">
                                        <i class="bi bi-calculator me-1"></i> Ringkasan Finansial
                                    </span>
                                    <span class="badge bg-white bg-opacity-10 text-white font-monospace" style="font-size: 10.5px;">Mata Uang: IDR</span>
                                </div>

                                <div class="so-summary-row">
                                    <span>Subtotal Produk:</span>
                                    <span class="font-monospace fw-semibold text-white" id="modalSubtotal">Rp 0</span>
                                </div>

                                <div class="so-summary-row d-none" id="modalDiscountRow">
                                    <span class="text-warning"><i class="bi bi-tag-fill me-1"></i> Potongan Diskon:</span>
                                    <span class="font-monospace fw-bold text-danger" id="modalDiscount">Rp 0</span>
                                </div>

                                <div class="so-summary-row d-none" id="modalTaxRow">
                                    <span id="modalTaxLabel">PPN (11%):</span>
                                    <span class="font-monospace fw-semibold text-white" id="modalTax">Rp 0</span>
                                </div>

                                <div class="so-summary-grand">
                                    <div>
                                        <span class="text-uppercase fw-bold text-white d-block" style="font-size: 12px; letter-spacing: 0.03em;">TOTAL AKHIR</span>
                                        <span class="text-white-50" style="font-size: 11px;">Sudah termasuk PPN &amp; Diskon</span>
                                    </div>
                                    <div class="so-grand-val" id="modalGrandTotal">Rp 0</div>
                                </div>

                                <div class="so-terbilang-box" id="modalTerbilangBox">
                                    <i class="bi bi-translate me-1 text-info"></i> <strong>Terbilang:</strong> <span id="modalTerbilangText">Nol Rupiah</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Executive Footer with Quick Share -->
            <div class="modal-footer px-4 py-3 bg-light border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-success px-3" id="modalBtnCopyWa" title="Salin Ringkasan Pesanan untuk dikirim ke WhatsApp">
                        <i class="bi bi-whatsapp me-1.5"></i> Salin Format WA
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal" style="font-size: 13px;">
                        Tutup
                    </button>
                    <a href="#" id="modalBtnEdit" class="btn btn-outline-primary px-3" style="font-size: 13px;">
                        <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                    </a>
                    <a href="#" id="modalBtnPrint" target="_blank" class="btn btn-primary px-3" style="font-size: 13px; background: #0f172a; border-color: #0f172a;">
                        <i class="bi bi-printer me-1"></i> Cetak Dokumen Resmi
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function() {
    // 1. Inisialisasi dropdown Bootstrap dengan strategy: fixed untuk membebaskan dari overflow container
    function initSoDropdowns() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
            document.querySelectorAll('.table-so .dropdown-toggle').forEach(function(el) {
                try {
                    new bootstrap.Dropdown(el, {
                        popperConfig: function(defaultBsPopperConfig) {
                            return Object.assign({}, defaultBsPopperConfig, {
                                strategy: 'fixed'
                            });
                        }
                    });
                } catch(e) {
                    console.warn('Bootstrap dropdown init:', e);
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSoDropdowns);
    } else {
        initSoDropdowns();
    }

    // 2. Ubah Status Cepat (Native listener agar kebal terhadap library conflict)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-change-status');
        if (!btn) return;
        e.preventDefault();

        const soId = btn.getAttribute('data-id');
        const newStatus = btn.getAttribute('data-status');
        if (!soId || !newStatus) return;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Mengubah Status...',
                text: 'Memperbarui status menjadi "' + newStatus + '"',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
        }

        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('id', soId);
        formData.append('status', newStatus);

        fetch('ajax_sales_order.php', {
            method: 'POST',
            body: formData
        })
        .then(function(res) {
            return res.json();
        })
        .then(function(res) {
            if (res && res.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Status Berhasil Diubah!',
                        text: res.message || ('Status pesanan diperbarui menjadi ' + newStatus),
                        timer: 1000,
                        showConfirmButton: false
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    location.reload();
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Mengubah Status',
                        text: (res && res.message) ? res.message : 'Gagal mengubah status.'
                    });
                } else {
                    alert((res && res.message) ? res.message : 'Gagal mengubah status.');
                }
            }
        })
        .catch(function(err) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Jaringan',
                    text: 'Gagal menghubungi server (' + (err.message || 'Network error') + ')'
                });
            } else {
                alert('Gagal menghubungi server: ' + (err.message || 'Network error'));
            }
        });
    });

    // 3. Hapus Pesanan SO
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete-so');
        if (!btn) return;
        e.preventDefault();

        const soId = btn.getAttribute('data-id');
        const soNum = btn.getAttribute('data-num') || '';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Hapus Pesanan?',
                text: 'Apakah Anda yakin ingin menghapus pesanan ' + soNum + '?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#EF4444'
            }).then(function(res) {
                if (res.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        allowOutsideClick: false,
                        didOpen: function() {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData();
                    formData.append('action', 'delete_sales_order');
                    formData.append('id', soId);

                    fetch('ajax_sales_order.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(delRes) {
                        if (delRes && delRes.success) {
                            const row = document.getElementById('row-so-' + soId);
                            if (row) {
                                row.style.transition = 'opacity 0.3s';
                                row.style.opacity = '0';
                                setTimeout(function() { row.remove(); }, 300);
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Dihapus',
                                text: 'Pesanan berhasil dihapus.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: (delRes && delRes.message) ? delRes.message : 'Gagal menghapus pesanan.'
                            });
                        }
                    })
                    .catch(function(err) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Koneksi Gagal',
                            text: 'Gagal menghubungi server: ' + (err.message || 'Network error')
                        });
                    });
                }
            });
        } else {
            if (confirm('Apakah Anda yakin ingin menghapus pesanan ' + soNum + '?')) {
                const formData = new FormData();
                formData.append('action', 'delete_sales_order');
                formData.append('id', soId);
                fetch('ajax_sales_order.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(r => { if (r.success) location.reload(); else alert(r.message); });
            }
        }
    });

    // 4. Modal Detail SO (Next-Gen Executive View)
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatRp(val) {
        const num = parseFloat(val) || 0;
        return 'Rp ' + Math.round(num).toLocaleString('id-ID');
    }

    // Generator Terbilang Rupiah Resmi Indonesia (PHP/Accurate standard)
    function terbilangRupiah(nilai) {
        nilai = Math.floor(Math.abs(Number(nilai) || 0));
        if (nilai === 0) return 'Nol Rupiah';
        const huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
        
        function sebut(n) {
            if (n < 12) return " " + huruf[n];
            if (n < 20) return sebut(n - 10) + " Belas";
            if (n < 100) return sebut(Math.floor(n / 10)) + " Puluh" + sebut(n % 10);
            if (n < 200) return " Seratus" + sebut(n - 100);
            if (n < 1000) return sebut(Math.floor(n / 100)) + " Ratus" + sebut(n % 100);
            if (n < 2000) return " Seribu" + sebut(n - 1000);
            if (n < 1000000) return sebut(Math.floor(n / 1000)) + " Ribu" + sebut(n % 1000);
            if (n < 1000000000) return sebut(Math.floor(n / 1000000)) + " Juta" + sebut(n % 1000000);
            if (n < 1000000000000) return sebut(Math.floor(n / 1000000000)) + " Milyar" + sebut(n % 1000000000);
            return sebut(Math.floor(n / 1000000000000)) + " Triliun" + sebut(n % 1000000000000);
        }
        
        return sebut(nilai).trim() + " Rupiah";
    }

    let currentLoadedOrder = null;
    let currentLoadedItems = [];

    function openSoDetail(soId) {
        const modalEl = document.getElementById('modalDetailSo');
        if (!modalEl) return;

        const loadingEl = document.getElementById('modalDetailLoading');
        const bodyEl = document.getElementById('modalDetailBody');
        const errorEl = document.getElementById('modalDetailError');

        // Reset state
        loadingEl.classList.remove('d-none');
        bodyEl.classList.add('d-none');
        errorEl.classList.add('d-none');
        document.getElementById('modalDetailSoSubtitle').textContent = 'Memuat data pesanan...';
        document.getElementById('modalDetailSoNumBadge').innerHTML = '';
        document.getElementById('modalDetailStatusBadge').innerHTML = '';

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        }

        fetch('ajax_sales_order.php?action=get_order_detail&id=' + encodeURIComponent(soId))
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (!res || !res.success || !res.order) {
                    loadingEl.classList.add('d-none');
                    errorEl.classList.remove('d-none');
                    document.getElementById('modalDetailErrorMessage').textContent = (res && res.message) ? res.message : 'Pesanan tidak ditemukan.';
                    return;
                }

                const order = res.order;
                const items = res.items || [];
                currentLoadedOrder = order;
                currentLoadedItems = items;

                // 1. Header & Badges
                document.getElementById('modalDetailSoSubtitle').textContent = 'Dibuat: ' + (order.created_at_formatted || '-');
                document.getElementById('modalDetailBranch').textContent = (order.branch || 'Kantor Pusat') + ' • ' + (order.currency || 'IDR');

                const soNumDisplay = order.so_number || 'Menunggu No. SO';
                document.getElementById('modalDetailSoNumBadge').textContent = soNumDisplay;

                const copyBtn = document.getElementById('btnCopySoNum');
                if (copyBtn) {
                    copyBtn.onclick = function() {
                        if (order.so_number) {
                            navigator.clipboard.writeText(order.so_number);
                            copyBtn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i> Disalin!';
                            setTimeout(() => {
                                copyBtn.innerHTML = '<i class="bi bi-copy me-1"></i> Salin';
                            }, 1800);
                        } else {
                            alert('Nomor SO belum diterbitkan.');
                        }
                    };
                }

                // Status Badge with icon
                let stIcon = 'bi-circle-fill';
                if (order.status === 'Draft') stIcon = 'bi-file-earmark-text';
                else if (order.status === 'Menunggu') stIcon = 'bi-hourglass-split';
                else if (order.status === 'Diproses') stIcon = 'bi-gear-wide-connected';
                else if (order.status === 'Selesai') stIcon = 'bi-check-circle-fill';
                else if (order.status === 'Dibatalkan') stIcon = 'bi-x-circle-fill';

                document.getElementById('modalDetailStatusBadge').innerHTML = 
                    `<span class="status-badge-so status-${escapeHtml(order.status)} shadow-sm">
                        <i class="bi ${stIcon} me-1"></i> ${escapeHtml(order.status)}
                    </span>`;

                // 2. Stepper Status Update
                const steps = ['Draft', 'Menunggu', 'Diproses', 'Selesai'];
                const stIndex = steps.indexOf(order.status);
                const fillPercent = [15, 45, 78, 100];

                steps.forEach(function(sName, sIdx) {
                    const stepEl = document.getElementById('step-' + sName);
                    if (stepEl) {
                        stepEl.classList.remove('completed', 'active');
                        if (order.status === 'Dibatalkan') {
                            // Canceled state
                        } else if (sIdx < stIndex) {
                            stepEl.classList.add('completed');
                            stepEl.querySelector('.so-step-circle').innerHTML = '<i class="bi bi-check-lg"></i>';
                        } else if (sIdx === stIndex) {
                            stepEl.classList.add('active');
                            stepEl.querySelector('.so-step-circle').innerHTML = `<i class="bi ${stIcon}"></i>`;
                        } else {
                            stepEl.querySelector('.so-step-circle').innerHTML = (sIdx + 1);
                        }
                    }
                });

                const stepperFill = document.getElementById('modalStepperFill');
                if (stepperFill) {
                    stepperFill.style.width = (stIndex >= 0 ? fillPercent[stIndex] : 0) + '%';
                }

                // 3. Info Cards
                // Data Pesanan
                document.getElementById('modalSoNumber').textContent = order.so_number || '(Belum Diterbitkan)';
                document.getElementById('modalSoDate').textContent = order.so_date_formatted || '-';
                document.getElementById('modalSoPo').textContent = order.po_number || '-';

                // Syarat Bayar Badge
                let termsBadge = `<span class="badge bg-light text-dark border">${escapeHtml(order.payment_terms || 'C.O.D')}</span>`;
                if ((order.payment_terms || '').toLowerCase().includes('bca') || (order.payment_terms || '').toLowerCase().includes('transfer')) {
                    termsBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold"><i class="bi bi-credit-card me-1"></i>${escapeHtml(order.payment_terms)}</span>`;
                } else if ((order.payment_terms || '').toLowerCase().includes('cash') || (order.payment_terms || '').toLowerCase().includes('c.o.d')) {
                    termsBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold"><i class="bi bi-cash me-1"></i>${escapeHtml(order.payment_terms)}</span>`;
                }
                document.getElementById('modalSoTerms').innerHTML = termsBadge;

                // Pelanggan / Toko
                let custTitle = escapeHtml(order.customer_name);
                if (order.customer_code) {
                    custTitle += ` <span class="badge bg-secondary-subtle text-secondary border font-monospace ms-1" style="font-size:10px;">${escapeHtml(order.customer_code)}</span>`;
                }
                document.getElementById('modalCustomerName').innerHTML = custTitle;
                document.getElementById('modalCustomerPic').textContent = order.customer_pic || '-';

                if (order.customer_phone) {
                    const cleanPhone = order.customer_phone.replace(/[^0-9]/g, '');
                    document.getElementById('modalCustomerPhone').innerHTML = 
                        `<a href="https://wa.me/${cleanPhone}" target="_blank" class="btn btn-sm btn-outline-success py-0 px-2 fw-semibold rounded-pill" style="font-size:11px;">
                            <i class="bi bi-whatsapp me-1"></i> ${escapeHtml(order.customer_phone)}
                        </a>`;
                } else {
                    document.getElementById('modalCustomerPhone').textContent = '-';
                }

                document.getElementById('modalCustomerAddress').textContent = order.customer_address || 'Tidak ada catatan alamat.';
                if (order.customer_address && order.customer_address.trim().length > 3) {
                    const mapsQuery = encodeURIComponent(order.customer_address);
                    document.getElementById('modalCustomerMapLink').innerHTML = 
                        `<a href="https://www.google.com/maps/search/?api=1&query=${mapsQuery}" target="_blank" class="badge bg-light text-primary border text-decoration-none" title="Buka di Google Maps" style="font-size:10.5px;">
                            <i class="bi bi-geo-alt-fill text-danger me-0.5"></i> Peta
                        </a>`;
                } else {
                    document.getElementById('modalCustomerMapLink').innerHTML = '';
                }

                // Logistik & Sales PIC
                document.getElementById('modalSalesName').textContent = order.sales_name || '-';
                document.getElementById('modalShippingDate').textContent = order.shipping_date_formatted || '-';
                document.getElementById('modalShippingMethod').textContent = order.shipping_method || 'Kurir Toko / Standar';
                document.getElementById('modalShippingAddress').textContent = order.shipping_address || order.customer_address || '-';

                // 4. Enterprise Hierarchical Items Table (BOM Breakdown)
                let itemsHtml = '';
                let totalQtyCount = 0;
                let currentParentNum = 0;
                let currentSubNum = 0;
                let activeParentPackage = null;

                if (items.length === 0) {
                    itemsHtml = '<tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-1 text-secondary opacity-50"></i>Belum ada barang dalam pesanan ini.</td></tr>';
                } else {
                    items.forEach(function(item) {
                        const nameLower = (item.item_name || '').toLowerCase();
                        const descLower = (item.item_description || '').toLowerCase();
                        const notesLower = (item.notes || '').toLowerCase();

                        // Detect explicit sub-item markers
                        const isExplicitSub = (item.item_name || '').trim().startsWith('--') ||
                                              descLower.includes('komponen paket') ||
                                              notesLower.includes('komponen paket') ||
                                              item.is_subitem === true;

                        // Detect package bundle
                        const isPkg = !isExplicitSub && (nameLower.includes('paket') || item.is_package === true || (item.item_code && item.item_code.startsWith('88003') || item.item_code === '8800513'));

                        if (isPkg) {
                            activeParentPackage = item;
                            currentParentNum++;
                            currentSubNum = 0;
                        }

                        // Determine if sub-item
                        const isSub = isExplicitSub || (!isPkg && parseFloat(item.unit_price) === 0 && activeParentPackage !== null);

                        if (isSub && !isPkg) {
                            currentSubNum++;
                        } else if (!isPkg && !isSub) {
                            activeParentPackage = null;
                            currentParentNum++;
                            currentSubNum = 0;
                        }

                        const qty = parseInt(item.qty || 1);
                        totalQtyCount += qty;

                        const cleanName = (item.item_name || '').replace(/^--\s*/, '').trim();
                        const itemCode = item.item_code ? escapeHtml(item.item_code) : '-';

                        if (isPkg) {
                            // Package Parent Row
                            itemsHtml += `
                                <tr class="row-parent-package">
                                    <td style="text-align:center;">
                                        <span class="badge bg-primary text-white font-monospace px-2 py-1" style="font-size:11px;">#${currentParentNum}</span>
                                    </td>
                                    <td>
                                        <span class="font-monospace fw-bold text-dark">${itemCode}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center flex-wrap gap-1 mb-0.5">
                                            <span class="fw-bold text-dark" style="font-size:13px;">${escapeHtml(cleanName)}</span>
                                            <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size:9.5px; font-weight:700; letter-spacing:0.04em;">
                                                <i class="bi bi-box-seam me-1"></i>PAKET BUNDLE
                                            </span>
                                        </div>
                                        ${item.item_description ? `<div class="text-muted small" style="font-size:11.5px;">${escapeHtml(item.item_description)}</div>` : ''}
                                    </td>
                                    <td style="text-align:center;"><span class="fw-bold font-monospace fs-6">${qty}</span></td>
                                    <td style="text-align:center;"><span class="badge bg-white text-dark border small">${escapeHtml(item.unit || 'SET')}</span></td>
                                    <td style="text-align:right;"><span class="font-monospace fw-semibold">${formatRp(item.unit_price)}</span></td>
                                    <td style="text-align:center;">${item.discount_percent > 0 ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">${Number(item.discount_percent)}%</span>` : '-'}</td>
                                    <td style="text-align:right;"><span class="font-monospace fw-bold text-primary fs-6">${formatRp(item.total_price)}</span></td>
                                </tr>
                            `;
                        } else if (isSub) {
                            // Bundled Component (Sub-Item Row)
                            const subNumber = (currentParentNum > 0 ? `${currentParentNum}.${currentSubNum}` : `↳ ${currentSubNum}`);
                            itemsHtml += `
                                <tr class="row-bundle-subitem">
                                    <td style="text-align:center;">
                                        <span class="text-muted font-monospace" style="font-size:11px;">${subNumber}</span>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-secondary small">${itemCode}</span>
                                    </td>
                                    <td class="subitem-tree-col">
                                        <span class="subitem-tree-branch"></span>
                                        <div class="ps-2">
                                            <div class="d-flex align-items-center gap-1.5">
                                                <span class="badge bg-light text-secondary border px-1.5 py-0.5" style="font-size:9.5px;">
                                                    <i class="bi bi-diagram-2 text-primary me-0.5"></i>Komponen
                                                </span>
                                                <span class="text-dark fw-medium" style="font-size:12.5px;">${escapeHtml(cleanName)}</span>
                                            </div>
                                            ${item.item_description ? `<div class="text-muted small ps-3 mt-0.5" style="font-size:11px;">${escapeHtml(item.item_description)}</div>` : ''}
                                        </div>
                                    </td>
                                    <td style="text-align:center;"><span class="font-monospace text-secondary fw-semibold">${qty}</span></td>
                                    <td style="text-align:center;"><span class="text-muted small">${escapeHtml(item.unit || 'UNIT')}</span></td>
                                    <td style="text-align:right;">
                                        <span class="badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-size:10.5px; font-weight:600;">
                                            <i class="bi bi-check2 me-1"></i>Termasuk Paket
                                        </span>
                                    </td>
                                    <td style="text-align:center;"><span class="text-muted small">-</span></td>
                                    <td style="text-align:right;">
                                        <span class="badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-size:10.5px; font-weight:600;">
                                            <i class="bi bi-check2 me-1"></i>Termasuk Paket
                                        </span>
                                    </td>
                                </tr>
                            `;
                        } else {
                            // Standalone Regular Product
                            itemsHtml += `
                                <tr>
                                    <td style="text-align:center;"><span class="fw-bold font-monospace text-dark" style="font-size:11.5px;">${currentParentNum}</span></td>
                                    <td><span class="font-monospace text-dark">${itemCode}</span></td>
                                    <td>
                                        <div class="fw-bold text-dark" style="font-size:13px;">${escapeHtml(cleanName)}</div>
                                        ${item.item_description ? `<div class="text-muted small" style="font-size:11.5px;">${escapeHtml(item.item_description)}</div>` : ''}
                                    </td>
                                    <td style="text-align:center;"><span class="fw-bold font-monospace">${qty}</span></td>
                                    <td style="text-align:center;"><span class="text-muted small">${escapeHtml(item.unit || 'UNIT')}</span></td>
                                    <td style="text-align:right;"><span class="font-monospace">${formatRp(item.unit_price)}</span></td>
                                    <td style="text-align:center;">${item.discount_percent > 0 ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">${Number(item.discount_percent)}%</span>` : '-'}</td>
                                    <td style="text-align:right;"><span class="font-monospace fw-bold text-dark">${formatRp(item.total_price)}</span></td>
                                </tr>
                            `;
                        }
                    });
                }

                document.getElementById('modalItemsTableBody').innerHTML = itemsHtml;
                document.getElementById('modalTotalItemsBadge').textContent = `${items.length} Baris (${totalQtyCount} Kuantitas)`;

                // 5. Special Notes
                const notesEl = document.getElementById('modalSpecialNotes');
                if (order.special_notes && order.special_notes.trim() !== '') {
                    notesEl.innerHTML = escapeHtml(order.special_notes);
                } else {
                    notesEl.innerHTML = '<em class="text-muted">Tidak ada catatan khusus untuk pesanan ini.</em>';
                }

                // 6. Financial Summary Box
                document.getElementById('modalSubtotal').textContent = formatRp(order.subtotal);

                const discRow = document.getElementById('modalDiscountRow');
                const discAmt = parseFloat(order.discount_amount) || 0;
                if (discAmt > 0) {
                    discRow.classList.remove('d-none');
                    document.getElementById('modalDiscount').textContent = '- ' + formatRp(discAmt);
                } else {
                    discRow.classList.add('d-none');
                }

                const taxRow = document.getElementById('modalTaxRow');
                const taxAmt = parseFloat(order.tax_amount) || 0;
                if (parseInt(order.is_taxable) === 1 && taxAmt > 0) {
                    taxRow.classList.remove('d-none');
                    document.getElementById('modalTaxLabel').textContent = 'PPN (' + Number(order.tax_percent || 11) + '%):';
                    document.getElementById('modalTax').textContent = formatRp(taxAmt);
                } else {
                    taxRow.classList.add('d-none');
                }

                document.getElementById('modalGrandTotal').textContent = formatRp(order.grand_total);

                // Terbilang Rupiah
                const terbilangText = terbilangRupiah(order.grand_total);
                document.getElementById('modalTerbilangText').textContent = terbilangText;

                // 7. Footer Actions
                document.getElementById('modalCreatedAtInfo').textContent = 'ID Database: #' + order.id;
                document.getElementById('modalBtnPrint').href = 'sales_order_print.php?id=' + order.id;
                document.getElementById('modalBtnEdit').href = 'sales_order_form.php?id=' + order.id;

                loadingEl.classList.add('d-none');
                bodyEl.classList.remove('d-none');
            })
            .catch(function(err) {
                loadingEl.classList.add('d-none');
                errorEl.classList.remove('d-none');
                document.getElementById('modalDetailErrorMessage').textContent = 'Terjadi kesalahan saat memuat data: ' + (err.message || 'Network error');
            });
    }

    // WhatsApp Summary Copy Handler
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#modalBtnCopyWa');
        if (!btn || !currentLoadedOrder) return;
        e.preventDefault();

        const order = currentLoadedOrder;
        const items = currentLoadedItems || [];

        let text = `*PESANAN PENJUALAN - LOEWIX CCTV*\n`;
        text += `No. Pesanan: ${order.so_number || '(Draft / Menunggu)'}\n`;
        text += `Tanggal SO: ${order.so_date_formatted || '-'}\n`;
        text += `Pelanggan: ${order.customer_name}\n`;
        if (order.customer_pic) text += `PIC Toko: ${order.customer_pic} (${order.customer_phone || '-'})\n`;
        text += `Sales PIC: ${order.sales_name || '-'}\n`;
        text += `Status: ${order.status}\n`;
        text += `-------------------------------------------\n`;
        text += `*RINCIAN BARANG:*\n`;

        let pNum = 0;
        let sNum = 0;
        let activeParent = null;

        items.forEach(function(item) {
            const nameLower = (item.item_name || '').toLowerCase();
            const descLower = (item.item_description || '').toLowerCase();
            const isExplicitSub = (item.item_name || '').trim().startsWith('--') || descLower.includes('komponen paket') || item.is_subitem === true;
            const isPkg = !isExplicitSub && (nameLower.includes('paket') || item.is_package === true || (item.item_code && item.item_code.startsWith('88003') || item.item_code === '8800513'));

            if (isPkg) {
                activeParent = item;
                pNum++;
                sNum = 0;
            }

            const isSub = isExplicitSub || (!isPkg && parseFloat(item.unit_price) === 0 && activeParent !== null);
            const cleanName = (item.item_name || '').replace(/^--\s*/, '').trim();

            if (isPkg) {
                text += `\n📦 *${pNum}. [PAKET] ${cleanName}* (${item.qty} ${item.unit || 'SET'}) - ${formatRp(item.total_price)}\n`;
            } else if (isSub) {
                sNum++;
                text += `   ↳ ${pNum}.${sNum} ${cleanName} (${item.qty} ${item.unit || 'UNIT'}) [Termasuk Paket]\n`;
            } else {
                activeParent = null;
                pNum++;
                sNum = 0;
                text += `${pNum}. ${cleanName} (${item.qty} ${item.unit || 'UNIT'}) - ${formatRp(item.total_price)}\n`;
            }
        });

        text += `-------------------------------------------\n`;
        text += `*TOTAL PEMBAYARAN: ${formatRp(order.grand_total)}*\n`;
        text += `_Terbilang: ${terbilangRupiah(order.grand_total)}_\n`;
        text += `Syarat Bayar: ${order.payment_terms || 'C.O.D'}\n`;
        if (order.special_notes) text += `Catatan: ${order.special_notes}\n`;

        navigator.clipboard.writeText(text).then(function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Format WhatsApp Disalin!',
                    text: 'Rincian pesanan berhasil disalin ke clipboard. Silakan tempel (Paste) di chat WhatsApp.',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                alert('Format WhatsApp berhasil disalin!');
            }
        });
    });

    // Trigger button view
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-view-so');
        if (!btn) return;
        e.preventDefault();

        const soId = btn.getAttribute('data-id');
        if (soId) {
            openSoDetail(soId);
        }
    });
})();
</script>
