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
.btn-action-so.btn-delete:hover {
    background: var(--pastel-red-bg);
    border-color: var(--pastel-red-border);
    color: var(--pastel-red-text);
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
                                <a href="sales_order_print.php?id=<?php echo $row['id']; ?>" class="fw-bold font-monospace text-decoration-none text-dark" title="Klik untuk Cetak / Lihat Dokumen">
                                    <?php echo htmlspecialchars($row['so_number']); ?>
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
                                    <a href="sales_order_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-action-so" title="Cetak Dokumen SO">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="sales_order_form.php?id=<?php echo $row['id']; ?>" class="btn-action-so" title="Edit Pesanan">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn-action-so btn-delete btn-delete-so" data-id="<?php echo $row['id']; ?>" data-num="<?php echo htmlspecialchars($row['so_number']); ?>" title="Hapus Pesanan">
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
})();
</script>
