<?php
/**
 * sales_order_form.php
 * Form Pembuatan & Edit Pesanan Penjualan (Sales Order)
 * Desain terinspirasi Accurate Online dengan estetika modern Loewix
 */

$page_title = 'Pesanan Penjualan (Sales Order)';
require_once 'includes/db.php';
require_once 'includes/sales_order_helper.php';
ensureSalesOrderTables($conn);
require_once 'includes/header.php';

$soId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = false;
$orderData = null;
$orderItems = [];

if ($soId > 0) {
    $stmt = $conn->prepare("SELECT * FROM sales_orders WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $soId);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($orderData) {
        $isEdit = true;
        $stmtItems = $conn->prepare("SELECT * FROM sales_order_items WHERE sales_order_id = ? ORDER BY id ASC");
        $stmtItems->bind_param("i", $soId);
        $stmtItems->execute();
        $resItems = $stmtItems->get_result();
        while ($row = $resItems->fetch_assoc()) {
            $orderItems[] = $row;
        }
        $stmtItems->close();
    }
}

// Generate default SO Number jika baru
$defaultSoNumber = '';
if (!$isEdit) {
    $yymm = date('ym');
    $prefix = $yymm . ".SOL.";
    $q = $conn->query("SELECT so_number FROM sales_orders WHERE so_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) {
        $parts = explode('.', $row['so_number']);
        $lastSeq = intval(end($parts));
        $nextSeq = $lastSeq + 1;
    } else {
        $qAll = $conn->query("SELECT so_number FROM sales_orders WHERE so_number LIKE '%.SOL.%' ORDER BY id DESC LIMIT 1");
        if ($qAll && $rowAll = $qAll->fetch_assoc()) {
            $parts = explode('.', $rowAll['so_number']);
            $lastSeq = intval(end($parts));
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 7025; // Default starter sesuai request user
        }
    }
    $defaultSoNumber = $prefix . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
}

// Ambil list sales untuk opsi PIC
$salesList = [];
$qSales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE deleted_at IS NULL ORDER BY nama_lengkap ASC");
if ($qSales) {
    while ($s = $qSales->fetch_assoc()) {
        $salesList[] = $s;
    }
}

// Ambil daftar produk katalog Loewix untuk autokomplit instan di baris tabel
$catalogProducts = [];
$qProd = $conn->query("SELECT id, category, type, item_code, description, unit, msrp FROM product_prices ORDER BY category ASC, type ASC");
if ($qProd) {
    while ($p = $qProd->fetch_assoc()) {
        $rawDesc = trim($p['description'] ?? '');
        $code = !empty($p['item_code']) ? $p['item_code'] : $p['type'];
        $unit = !empty($p['unit']) ? $p['unit'] : 'UNIT';
        $catalogProducts[] = [
            'id' => (int)$p['id'],
            'category' => $p['category'],
            'type' => $p['type'],
            'code' => $code,
            'name' => $p['type'],
            'description' => $rawDesc,
            'msrp' => (float)$p['msrp'],
            'unit' => $unit
        ];
    }
}
?>

<style>
/* ═════════════════════════════════════════════════════════
   MINIMALIST EDITORIAL & WARM MONOCHROME DESIGN SYSTEM
   Accurate-inspired form with quiet luxury aesthetic
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

    /* Muted Spot Pastels */
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

.accurate-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    overflow: hidden;
    margin-bottom: 24px;
}

.accurate-tab-header {
    background: #0f172a;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #1e293b;
}

.accurate-tab-badge {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.14);
    color: #ffffff;
    font-size: 12.5px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.accurate-header-panel {
    background: #fafafa;
    border-bottom: 1px solid #e2e8f0;
    padding: 20px 24px;
}

.accurate-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 6px;
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.accurate-label.required::after {
    content: " *";
    color: #dc2626;
}

.accurate-input {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #0f172a;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.accurate-input:focus {
    border-color: #0f172a;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.06);
    outline: none;
}

.nav-accurate-tabs {
    border-bottom: 1px solid #e2e8f0;
    padding: 0 24px;
    background: #ffffff;
    display: flex;
    gap: 16px;
}

.nav-accurate-tabs .nav-link {
    border: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    padding: 12px 4px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.nav-accurate-tabs .nav-link:hover {
    color: #0f172a;
}

.nav-accurate-tabs .nav-link.active {
    color: #0f172a;
    border-bottom-color: #0f172a;
    font-weight: 700;
    background: transparent;
}

/* Tabel Item Order */
.accurate-table-wrapper {
    padding: 20px 24px;
}

.table-accurate {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.table-accurate thead th {
    background: #0f172a;
    color: #cbd5e1;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 11px 12px;
    border: none;
    white-space: nowrap;
}

.table-accurate tbody td {
    padding: 8px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    font-size: 13px;
}

.table-accurate tbody tr:last-child td {
    border-bottom: none;
}

.table-accurate tbody tr:hover td {
    background: #f8fafc;
}

.item-search-bar {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Financial Summary Footer Box */
.summary-container {
    background: #fafafa;
    border-top: 1px solid #e2e8f0;
    padding: 20px 24px;
}

.summary-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 13px;
    color: #475569;
}

.summary-row.total-row {
    border-top: 1px dashed #cbd5e1;
    margin-top: 10px;
    padding-top: 12px;
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
}

/* Minimalist Button Styles */
.btn-primary-so {
    background: #0f172a;
    color: #ffffff !important;
    border: 1px solid #0f172a;
    border-radius: 8px;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
    text-decoration: none;
    cursor: pointer;
}
.btn-primary-so:hover {
    background: #1e293b;
    border-color: #1e293b;
    color: #ffffff !important;
}

.btn-secondary-so {
    background: #ffffff;
    color: #475569 !important;
    border: 1px solid #e2e8f0;
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
.btn-secondary-so:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a !important;
}

.btn-row-delete {
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 4px 6px;
    border-radius: 4px;
    transition: all 0.15s ease;
}
.btn-row-delete:hover {
    color: #ef4444;
    background: var(--pastel-red-bg);
}

/* Select2 Customization for Minimalist Look */
.select2-container--bootstrap-5 .select2-selection {
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    font-size: 13px !important;
    min-height: 38px !important;
}
.select2-container--bootstrap-5.select2-container--focus .select2-selection {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.06) !important;
}
</style>

<!-- Hero Header Navigasi -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1" style="font-size:12.5px; color:#64748B; font-weight:600;">
            <a href="customer_management.php" class="text-decoration-none text-muted">Dashboard</a>
            <span>›</span>
            <a href="sales_orders.php" class="text-decoration-none text-dark">Pesanan Penjualan</a>
            <span>›</span>
            <span class="text-secondary"><?php echo $isEdit ? 'Edit Pesanan' : 'Data Baru'; ?></span>
        </div>
        <h2 class="fw-bold mb-0 text-dark" style="font-family:'Outfit', sans-serif; font-size:24px; letter-spacing:-0.02em;">
            <i class="bi bi-receipt me-2 text-dark"></i><?php echo $isEdit ? 'Edit Pesanan Penjualan' : 'Buat Pesanan Penjualan Baru'; ?>
        </h2>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="sales_orders.php" class="btn-secondary-so">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
        <button type="button" class="btn-primary-so" id="btnSaveSo">
            <i class="bi bi-check2 me-1"></i> <?php echo $isEdit ? 'Perbarui Pesanan' : 'Simpan Pesanan'; ?>
        </button>
        <?php if ($isEdit): ?>
        <a href="sales_order_print.php?id=<?php echo $orderData['id']; ?>" target="_blank" class="btn-secondary-so">
            <i class="bi bi-printer me-1"></i> Cetak SO
        </a>
        <?php endif; ?>
    </div>
</div>

<form id="formSalesOrder" autocomplete="off">
    <input type="hidden" name="id" id="so_id" value="<?php echo $isEdit ? $orderData['id'] : '0'; ?>">
    <input type="hidden" name="customer_code" id="customer_code" value="<?php echo htmlspecialchars($orderData['customer_code'] ?? ''); ?>">

    <div class="accurate-container">
        <!-- Accurate Top Dark Tab Header -->
        <div class="accurate-tab-header">
            <div class="d-flex align-items-center gap-2">
                <span class="accurate-tab-badge">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    Pesanan Penjualan: <?php echo $isEdit ? htmlspecialchars($orderData['so_number']) : 'Data Baru'; ?>
                </span>
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#cbd5e1; border:1px solid rgba(255,255,255,0.12); font-size:11.5px; font-weight:600; padding:5px 12px; border-radius:6px;">
                    Mata Uang: IDR (Rupiah)
                </span>
            </div>
            <div>
                <select name="status" id="status" class="form-select form-select-sm fw-semibold" style="background:#1e293b; color:#ffffff; border:1px solid #334155; border-radius:6px; font-size:12px;">
                    <?php
                    $statuses = ['Draft', 'Menunggu', 'Diproses', 'Selesai', 'Dibatalkan'];
                    $currStatus = $orderData['status'] ?? 'Menunggu';
                    foreach ($statuses as $st) {
                        $sel = ($currStatus === $st) ? 'selected' : '';
                        echo "<option value='{$st}' {$sel}>Status: {$st}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <!-- Accurate Header Panel: Customer, Tanggal, No SO -->
        <div class="accurate-header-panel">
            <div class="row g-3">
                <!-- Dipesan oleh * -->
                <div class="col-lg-5 col-md-6">
                    <label class="accurate-label required">
                        <i class="bi bi-person me-1"></i> Dipesan Oleh (Customer / Toko)
                    </label>
                    <div class="input-group">
                        <select name="customer_id" id="customer_id" class="form-select accurate-input" style="width: 100%;">
                            <?php if ($isEdit && !empty($orderData['customer_name'])): ?>
                                <option value="<?php echo $orderData['customer_id']; ?>" selected>
                                    <?php echo htmlspecialchars(($orderData['customer_code'] ? $orderData['customer_code'] . ' ' : '') . $orderData['customer_name']); ?>
                                </option>
                            <?php else: ?>
                                <option value="">-- Ketik Nama Toko / Customer --</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <input type="hidden" name="customer_name" id="customer_name" value="<?php echo htmlspecialchars($orderData['customer_name'] ?? ''); ?>">
                    <div id="customerQuickMeta" class="mt-2 text-muted small fw-semibold" style="display: <?php echo $isEdit ? 'block' : 'none'; ?>;">
                        <span id="txtCustPic"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($orderData['customer_pic'] ?? '-'); ?></span> | 
                        <span id="txtCustPhone"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($orderData['customer_phone'] ?? '-'); ?></span>
                    </div>
                </div>

                <!-- Tanggal * -->
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="accurate-label required">
                        <i class="bi bi-calendar-event me-1"></i> Tanggal
                    </label>
                    <input type="date" name="so_date" id="so_date" class="form-control accurate-input" 
                           value="<?php echo htmlspecialchars($orderData['so_date'] ?? date('Y-m-d')); ?>" required>
                </div>

                <!-- No Pesanan # * (Format: 2609.SOL.07025) -->
                <div class="col-lg-3 col-md-3 col-6">
                    <label class="accurate-label required">
                        <i class="bi bi-hash me-1"></i> No. Pesanan (SO #)
                    </label>
                    <div class="input-group">
                        <input type="text" name="so_number" id="so_number" class="form-control accurate-input font-monospace text-dark fw-bold" 
                               value="<?php echo htmlspecialchars($isEdit ? $orderData['so_number'] : $defaultSoNumber); ?>" required>
                        <button class="btn btn-outline-secondary" type="button" id="btnRefreshSoNum" title="Generate No. SO Baru" style="border-color:#cbd5e1;">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <small class="text-muted" style="font-size:11px;">Format: <strong>YYMM.SOL.XXXXX</strong></small>
                </div>

                <!-- Sales Representative -->
                <div class="col-lg-2 col-md-6">
                    <label class="accurate-label">
                        <i class="bi bi-briefcase me-1"></i> Sales PIC
                    </label>
                    <select name="sales_id" id="sales_id" class="form-select accurate-input">
                        <option value="">-- Pilih Sales --</option>
                        <?php 
                        $curSalesId = $orderData['sales_id'] ?? ($_SESSION['user_id'] ?? 0);
                        foreach ($salesList as $s): 
                            $sel = ($curSalesId == $s['id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $sel; ?>>
                                <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="sales_name" id="sales_name" value="<?php echo htmlspecialchars($orderData['sales_name'] ?? ($_SESSION['nama_lengkap'] ?? '')); ?>">
                </div>
            </div>
        </div>

        <!-- Navigation Tabs: Rincian Barang vs Info Lainnya -->
        <ul class="nav nav-accurate-tabs" id="accurateTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-items-tab" data-bs-toggle="tab" data-bs-target="#tab-items" type="button" role="tab">
                    <i class="bi bi-box-seam"></i> Rincian Barang &amp; Jasa
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-info-tab" data-bs-toggle="tab" data-bs-target="#tab-info" type="button" role="tab">
                    <i class="bi bi-info-circle"></i> Info Lainnya &amp; Pengiriman
                </button>
            </li>
        </ul>

        <!-- TAB CONTENT -->
        <div class="tab-content" id="accurateTabContent">
            
            <!-- ============================================================== -->
            <!-- TAB 1: RINCIAN BARANG & JASA                                    -->
            <!-- ============================================================== -->
            <div class="tab-pane fade show active" id="tab-items" role="tabpanel">
                <div class="accurate-table-wrapper">
                    
                    <!-- Search / Autocomplete Bar Barang -->
                    <div class="item-search-bar">
                        <div class="flex-grow-1">
                            <label class="accurate-label mb-1 d-block">
                                <i class="bi bi-search me-1"></i> Cari / Pilih Barang &amp; Jasa dari Database:
                            </label>
                            <select id="catalogProductPicker" class="form-select accurate-input" style="width:100%;">
                                <option value="">-- Ketik Nama atau Model CCTV Loewix (LX-4F320-CE, IPCAM, DVR, dll) --</option>
                            </select>
                        </div>
                        <div class="align-self-end">
                            <button type="button" class="btn-secondary-so" id="btnAddManualItem">
                                <i class="bi bi-plus-lg me-1"></i> Baris Manual
                            </button>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="table-responsive">
                        <table class="table-accurate" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 44px; text-align: center;">#</th>
                                    <th style="width: 28%;">Nama Barang &amp; Deskripsi</th>
                                    <th style="width: 15%;">Kode / SKU #</th>
                                    <th style="width: 10%; text-align: center;">Qty</th>
                                    <th style="width: 9%; text-align: center;">Satuan</th>
                                    <th style="width: 14%; text-align: right;">@Harga (Rp)</th>
                                    <th style="width: 10%; text-align: right;">Diskon (Rp)</th>
                                    <th style="width: 14%; text-align: right;">Total Harga (Rp)</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <!-- Dynamic Rows rendered here via JS -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Datalist Autocomplete Produk & Kode Katalog Loewix -->
                    <datalist id="catalogProductsDatalist">
                        <?php foreach ($catalogProducts as $cp): ?>
                            <option value="<?php echo htmlspecialchars($cp['name']); ?>">[<?php echo htmlspecialchars($cp['category']); ?>] <?php echo htmlspecialchars($cp['code']); ?><?php echo ($cp['msrp'] > 0 ? ' &mdash; Rp ' . number_format($cp['msrp'], 0, ',', '.') : ''); ?></option>
                        <?php endforeach; ?>
                    </datalist>

                    <datalist id="catalogCodesDatalist">
                        <?php foreach ($catalogProducts as $cp): ?>
                            <option value="<?php echo htmlspecialchars($cp['code']); ?>"><?php echo htmlspecialchars($cp['name']); ?><?php echo ($cp['msrp'] > 0 ? ' &mdash; Rp ' . number_format($cp['msrp'], 0, ',', '.') : ''); ?></option>
                        <?php endforeach; ?>
                    </datalist>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="badge" style="background:var(--pastel-slate-bg); color:var(--pastel-slate-text); border:1px solid var(--pastel-slate-border); padding:6px 12px; font-weight:600; font-size:12px;" id="txtTotalItemsCount">
                            0 Barang (0 Kuantitas)
                        </span>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none fw-semibold text-muted" id="btnClearAllItems">
                            <i class="bi bi-trash3 me-1 text-danger"></i> Kosongkan Semua Baris
                        </button>
                    </div>
                </div>

                <!-- Financial Summary Footer (Subtotal, Diskon, PPN, Grand Total) -->
                <div class="summary-container">
                    <div class="row justify-content-end">
                        <div class="col-lg-5 col-md-7">
                            <div class="summary-card">
                                <div class="summary-row">
                                    <span class="fw-semibold">Sub Total:</span>
                                    <span class="fw-bold font-monospace text-dark" id="lblSubtotal">Rp 0</span>
                                    <input type="hidden" name="subtotal" id="inputSubtotal" value="0">
                                </div>

                                <div class="summary-row align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold">Diskon Tambahan:</span>
                                        <select name="discount_type" id="discount_type" class="form-select form-select-sm" style="width: 75px; font-size:12px; border-color:#cbd5e1;">
                                            <option value="rp" <?php echo (($orderData['discount_type'] ?? '') === 'rp') ? 'selected' : ''; ?>>Rp</option>
                                            <option value="percent" <?php echo (($orderData['discount_type'] ?? '') === 'percent') ? 'selected' : ''; ?>>%</option>
                                        </select>
                                    </div>
                                    <div style="max-width: 160px;">
                                        <input type="number" step="any" min="0" name="discount_val" id="discount_val" 
                                               class="form-control form-control-sm text-end fw-bold font-monospace" 
                                               value="<?php echo htmlspecialchars($orderData['discount_val'] ?? '0'); ?>" style="border-color:#cbd5e1;">
                                    </div>
                                </div>
                                <div class="text-end text-muted small pe-1 pb-1" id="lblDiscountDeduction" style="display:none; font-size:11.5px; color:var(--pastel-red-text) !important;">
                                    - Rp 0
                                </div>

                                <div class="summary-row border-top pt-2">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="is_taxable" id="is_taxable" value="1" 
                                               <?php echo (!empty($orderData['is_taxable'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="is_taxable">
                                            Kena Pajak PPN (11%)
                                        </label>
                                    </div>
                                    <span class="fw-bold font-monospace text-dark" id="lblTaxAmount">Rp 0</span>
                                </div>

                                <div class="summary-row total-row">
                                    <span>Total (Grand Total):</span>
                                    <span class="fw-bold font-monospace text-dark" id="lblGrandTotal" style="font-size:20px;">Rp 0</span>
                                    <input type="hidden" name="grand_total" id="inputGrandTotal" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- TAB 2: INFO LAINNYA & PENGIRIMAN                                -->
            <!-- ============================================================== -->
            <div class="tab-pane fade" id="tab-info" role="tabpanel">
                <div class="p-4">
                    <div class="row g-4">
                        
                        <!-- Kolom Kiri: Syarat Pembayaran, PO, Alamat, Cabang -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="bi bi-receipt me-1"></i> Informasi Pembayaran &amp; Toko
                            </h6>

                            <div class="mb-3">
                                <label class="accurate-label">Syarat Pembayaran</label>
                                <select name="payment_terms" id="payment_terms" class="form-select accurate-input">
                                    <?php
                                    $terms = ['C.O.D', 'Cash', 'Transfer BCA', 'Transfer Mandiri', 'Net 7 Hari', 'Net 14 Hari', 'Net 30 Hari', 'Net 60 Hari'];
                                    $curTerm = $orderData['payment_terms'] ?? 'C.O.D';
                                    foreach ($terms as $t) {
                                        $sel = ($curTerm === $t) ? 'selected' : '';
                                        echo "<option value='{$t}' {$sel}>{$t}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="accurate-label">Nomor PO (Purchase Order Customer)</label>
                                <input type="text" name="po_number" id="po_number" class="form-control accurate-input" 
                                       placeholder="Contoh: PO/2026/09/123" value="<?php echo htmlspecialchars($orderData['po_number'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="accurate-label">Alamat Toko / Tagihan</label>
                                <textarea name="customer_address" id="customer_address" rows="3" class="form-control accurate-input" 
                                          placeholder="Alamat lengkap toko customer..."><?php echo htmlspecialchars($orderData['customer_address'] ?? ''); ?></textarea>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="accurate-label">Kontak PIC Toko</label>
                                    <input type="text" name="customer_pic" id="customer_pic" class="form-control accurate-input" 
                                           placeholder="Nama PIC" value="<?php echo htmlspecialchars($orderData['customer_pic'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="accurate-label">No. Telepon / WhatsApp</label>
                                    <input type="text" name="customer_phone" id="customer_phone" class="form-control accurate-input" 
                                           placeholder="08xxxxxxxxxx" value="<?php echo htmlspecialchars($orderData['customer_phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="accurate-label">Cabang / Gudang Asal</label>
                                <select name="branch" id="branch" class="form-select accurate-input">
                                    <option value="Kantor Pusat" <?php echo (($orderData['branch'] ?? '') === 'Kantor Pusat') ? 'selected' : ''; ?>>Kantor Pusat (Jakarta)</option>
                                    <option value="Gudang Surabaya" <?php echo (($orderData['branch'] ?? '') === 'Gudang Surabaya') ? 'selected' : ''; ?>>Gudang Surabaya</option>
                                    <option value="Gudang Semarang" <?php echo (($orderData['branch'] ?? '') === 'Gudang Semarang') ? 'selected' : ''; ?>>Gudang Semarang</option>
                                </select>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Info Pajak, Pengiriman, Catatan -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="bi bi-truck me-1"></i> Informasi Pengiriman &amp; Pajak
                            </h6>

                            <div class="card p-3 mb-3 border rounded-3" style="background:#f8fafc; border-color:#e2e8f0 !important;">
                                <span class="fw-bold text-dark small mb-2 d-block">
                                    <i class="bi bi-percent me-1"></i> Opsi Pajak
                                </span>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="tax_inclusive" id="tax_inclusive" value="1" 
                                           <?php echo (!empty($orderData['tax_inclusive'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="tax_inclusive">
                                        Harga Satuan Barang Sudah Termasuk Pajak (Inclusive)
                                    </label>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="accurate-label">Tanggal Pengiriman</label>
                                    <input type="date" name="shipping_date" id="shipping_date" class="form-control accurate-input" 
                                           value="<?php echo htmlspecialchars($orderData['shipping_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="accurate-label">Ekspedisi / Kurir Pengiriman</label>
                                    <input type="text" name="shipping_method" id="shipping_method" class="form-control accurate-input" 
                                           placeholder="JNE / J&T / Deliveree / Kurir Kantor / Ambil Sendiri" 
                                           value="<?php echo htmlspecialchars($orderData['shipping_method'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="accurate-label">Alamat Pengiriman (Jika beda dengan alamat toko)</label>
                                <textarea name="shipping_address" id="shipping_address" rows="3" class="form-control accurate-input" 
                                          placeholder="Alamat kirim / gudang tujuan..."><?php echo htmlspecialchars($orderData['shipping_address'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="accurate-label">Catatan Khusus / Special Instructions</label>
                                <textarea name="special_notes" id="special_notes" rows="3" class="form-control accurate-input" 
                                          placeholder="Catatan untuk bagian gudang / pengemasan / customer..."><?php echo htmlspecialchars($orderData['special_notes'] ?? ''); ?></textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div><!-- End Tab Content -->

    </div><!-- End Accurate Container -->

    <!-- Floating Action Bottom Bar -->
    <div class="d-flex justify-content-between align-items-center p-3 bg-white border rounded-3 shadow-sm mb-4">
        <div>
            <span class="text-muted small">Pastikan semua data barang dan customer telah sesuai sebelum menyimpan.</span>
        </div>
        <div class="d-flex gap-2">
            <a href="sales_orders.php" class="btn-secondary-so">
                Batal
            </a>
            <button type="button" class="btn-primary-so" id="btnSaveSoBottom">
                <i class="bi bi-check2 me-1"></i> <?php echo $isEdit ? 'Simpan Perubahan' : 'Simpan Pesanan'; ?>
            </button>
        </div>
    </div>
</form>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initial Item State
    let items = <?php echo json_encode($orderItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || [];

    // Format Rupiah Helper
    function formatRupiah(num) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
    }

    // 1. SELECT2 FOR CUSTOMERS
    $('#customer_id').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Ketik Nama Toko / Customer --',
        allowClear: true,
        ajax: {
            url: 'ajax_sales_order.php?action=search_customers',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return { results: data.results || [] };
            },
            cache: true
        }
    }).on('select2:select', function(e) {
        const data = e.params.data;
        $('#customer_code').val(data.customer_code || '');
        $('#customer_name').val(data.nama_toko || '');
        
        // Auto fill address and PIC
        if (data.alamat) {
            $('#customer_address').val(data.alamat);
            if (!$('#shipping_address').val()) {
                $('#shipping_address').val(data.alamat);
            }
        }
        if (data.nama_pic) $('#customer_pic').val(data.nama_pic);
        if (data.tlp_pic) $('#customer_phone').val(data.tlp_pic);
        
        // Auto fill sales if available and currently empty
        if (data.sales_id && !$('#sales_id').val()) {
            $('#sales_id').val(data.sales_id).trigger('change');
        }

        // Meta info
        $('#txtCustPic').html('<i class="bi bi-person me-1"></i>' + (data.nama_pic || '-'));
        $('#txtCustPhone').html('<i class="bi bi-telephone me-1"></i>' + (data.tlp_pic || '-'));
        $('#customerQuickMeta').fadeIn(200);
    }).on('select2:clear', function() {
        $('#customer_code').val('');
        $('#customer_name').val('');
        $('#customerQuickMeta').hide();
    });

    // 2. SELECT2 FOR CATALOG PRODUCTS
    $('#catalogProductPicker').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Ketik Nama atau Model CCTV Loewix (LX-4F320-CE, IPCAM, DVR, dll) --',
        allowClear: true,
        ajax: {
            url: 'ajax_sales_order.php?action=search_products',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return { results: data.results || [] };
            },
            cache: true
        }
    }).on('select2:select', function(e) {
        const prod = e.params.data;
        addItemRow({
            product_id: prod.id,
            item_code: prod.type || prod.code || '',
            item_name: prod.name || prod.type || '',
            item_description: prod.description || '',
            qty: 1,
            unit: prod.unit || 'PCS',
            unit_price: prod.msrp || 0,
            discount_item: 0,
            total_price: prod.msrp || 0
        });

        // Reset dropdown after adding
        $('#catalogProductPicker').val(null).trigger('change');
    });

    // 3. GENERATE NEXT SO NUMBER BUTTON
    $('#btnRefreshSoNum').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).find('i').addClass('spin');
        $.getJSON('ajax_sales_order.php?action=get_next_so_number', function(res) {
            btn.prop('disabled', false).find('i').removeClass('spin');
            if (res.success && res.so_number) {
                $('#so_number').val(res.so_number);
            }
        }).fail(function() {
            btn.prop('disabled', false).find('i').removeClass('spin');
        });
    });

    // 4. RENDER ITEMS TABLE
    function renderItemsTable() {
        const tbody = $('#itemsTableBody');
        tbody.empty();

        if (items.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                        <span class="fw-semibold text-dark d-block mb-1" style="font-size:13px;">Belum ada barang dalam pesanan ini</span>
                        <small class="text-muted">Gunakan pencarian di atas atau klik tombol <strong>+ Baris Manual</strong> untuk menambahkan barang.</small>
                    </td>
                </tr>
            `);
            $('#txtTotalItemsCount').text('0 Barang (0 Kuantitas)');
            recalculateSummary();
            return;
        }

        let totalQty = 0;

        items.forEach((item, idx) => {
            const qty = Math.max(1, parseInt(item.qty) || 1);
            const uPrice = parseFloat(item.unit_price) || 0;
            const discItem = parseFloat(item.discount_item) || 0;
            const lineTotal = qty * Math.max(0, (uPrice - discItem));
            item.total_price = lineTotal;
            totalQty += qty;

            const tr = $(`
                <tr data-index="${idx}">
                    <td style="text-align: center;">
                        <button type="button" class="btn-row-delete btn-remove-item" data-index="${idx}" title="Hapus Baris">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                    <td>
                        <input type="text" list="catalogProductsDatalist" class="form-control form-control-sm accurate-input fw-semibold item-field-name" data-index="${idx}" value="${escapeHtml(item.item_name || '')}" placeholder="Ketik / Pilih Nama Barang">
                    </td>
                    <td>
                        <input type="text" list="catalogCodesDatalist" class="form-control form-control-sm accurate-input font-monospace item-field-code" data-index="${idx}" value="${escapeHtml(item.item_code || '')}" placeholder="Kode / SKU">
                    </td>
                    <td style="text-align: center;">
                        <input type="number" min="1" step="1" class="form-control form-control-sm accurate-input text-center fw-bold item-field-qty" data-index="${idx}" value="${qty}">
                    </td>
                    <td style="text-align: center;">
                        <input type="text" class="form-control form-control-sm accurate-input text-center item-field-unit" data-index="${idx}" value="${escapeHtml(item.unit || 'PCS')}" placeholder="PCS">
                    </td>
                    <td style="text-align: right;">
                        <input type="number" min="0" step="any" class="form-control form-control-sm accurate-input text-end font-monospace item-field-price" data-index="${idx}" value="${uPrice}">
                    </td>
                    <td style="text-align: right;">
                        <input type="number" min="0" step="any" class="form-control form-control-sm accurate-input text-end font-monospace item-field-disc" data-index="${idx}" value="${discItem}">
                    </td>
                    <td style="text-align: right; font-weight: 700; font-family: 'JetBrains Mono', monospace;" class="line-total-cell">
                        ${formatRupiah(lineTotal)}
                    </td>
                </tr>
            `);

            tbody.append(tr);
        });

        $('#txtTotalItemsCount').text(`${items.length} Barang (${totalQty} Kuantitas)`);
        recalculateSummary();
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function addItemRow(newItem) {
        items.push(newItem);
        renderItemsTable();
    }

    // Manual Item Button
    $('#btnAddManualItem').on('click', function() {
        addItemRow({
            product_id: null,
            item_code: '',
            item_name: '',
            item_description: '',
            qty: 1,
            unit: 'PCS',
            unit_price: 0,
            discount_item: 0,
            total_price: 0
        });
    });

    // Remove Item
    $(document).on('click', '.btn-remove-item', function() {
        const idx = $(this).data('index');
        items.splice(idx, 1);
        renderItemsTable();
    });

    // Clear All Items
    $('#btnClearAllItems').on('click', function() {
        if (items.length === 0) return;
        Swal.fire({
            title: 'Kosongkan Barang?',
            text: 'Semua baris barang dalam pesanan ini akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Kosongkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#EF4444'
        }).then((res) => {
            if (res.isConfirmed) {
                items = [];
                renderItemsTable();
            }
        });
    });

    // Realtime Item Field Changes with Smart Catalog Autocomplete
    const catalogList = <?php echo json_encode($catalogProducts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || [];
    const catalogByName = {};
    const catalogByCode = {};
    catalogList.forEach(p => {
        if (p.name) catalogByName[p.name.trim().toLowerCase()] = p;
        if (p.code) catalogByCode[p.code.trim().toLowerCase()] = p;
    });

    $(document).on('input change', '.item-field-name', function() {
        const idx = $(this).data('index');
        const val = $(this).val();
        items[idx].item_name = val;

        const cleanVal = (val || '').trim().toLowerCase();
        const matched = catalogByName[cleanVal] || catalogByCode[cleanVal];
        if (matched) {
            items[idx].product_id = matched.id;
            if (!items[idx].item_code || items[idx].item_code === '') {
                items[idx].item_code = matched.code;
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-code`).val(matched.code);
            }
            if (!items[idx].unit_price || items[idx].unit_price == 0) {
                items[idx].unit_price = matched.msrp;
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-price`).val(matched.msrp);
            }
            if (!items[idx].unit) {
                items[idx].unit = matched.unit || 'PCS';
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-unit`).val(items[idx].unit);
            }
            if (!items[idx].item_description) {
                items[idx].item_description = matched.description;
            }
            recalculateRow(idx);
        }
    });

    $(document).on('input change', '.item-field-code', function() {
        const idx = $(this).data('index');
        const val = $(this).val();
        items[idx].item_code = val;

        const cleanVal = (val || '').trim().toLowerCase();
        const matched = catalogByCode[cleanVal];
        if (matched) {
            items[idx].product_id = matched.id;
            if (!items[idx].item_name || items[idx].item_name === '') {
                items[idx].item_name = matched.name;
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-name`).val(matched.name);
            }
            if (!items[idx].unit_price || items[idx].unit_price == 0) {
                items[idx].unit_price = matched.msrp;
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-price`).val(matched.msrp);
            }
            if (!items[idx].unit) {
                items[idx].unit = matched.unit || 'PCS';
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-unit`).val(items[idx].unit);
            }
            recalculateRow(idx);
        }
    });
    $(document).on('input change', '.item-field-qty', function() {
        const idx = $(this).data('index');
        const val = Math.max(1, parseInt($(this).val()) || 1);
        items[idx].qty = val;
        recalculateRow(idx);
    });
    $(document).on('input change', '.item-field-unit', function() {
        const idx = $(this).data('index');
        items[idx].unit = $(this).val();
    });
    $(document).on('input change', '.item-field-price', function() {
        const idx = $(this).data('index');
        const val = Math.max(0, parseFloat($(this).val()) || 0);
        items[idx].unit_price = val;
        recalculateRow(idx);
    });
    $(document).on('input change', '.item-field-disc', function() {
        const idx = $(this).data('index');
        const val = Math.max(0, parseFloat($(this).val()) || 0);
        items[idx].discount_item = val;
        recalculateRow(idx);
    });

    function recalculateRow(idx) {
        const itm = items[idx];
        const qty = Math.max(1, parseInt(itm.qty) || 1);
        const price = Math.max(0, parseFloat(itm.unit_price) || 0);
        const disc = Math.max(0, parseFloat(itm.discount_item) || 0);
        const total = qty * Math.max(0, price - disc);
        itm.total_price = total;

        $(`#itemsTableBody tr[data-index="${idx}"] .line-total-cell`).text(formatRupiah(total));
        recalculateSummary();
    }

    // 5. FINANCIAL CALCULATIONS
    function recalculateSummary() {
        let subtotal = 0;
        items.forEach(itm => {
            subtotal += (parseFloat(itm.total_price) || 0);
        });

        $('#inputSubtotal').val(subtotal);
        $('#lblSubtotal').text(formatRupiah(subtotal));

        // Additional Discount
        const discType = $('#discount_type').val();
        const discVal = parseFloat($('#discount_val').val()) || 0;
        let discAmount = 0;

        if (discType === 'percent') {
            discAmount = (subtotal * (discVal / 100));
        } else {
            discAmount = discVal;
        }
        if (discAmount > subtotal) discAmount = subtotal;

        if (discAmount > 0) {
            $('#lblDiscountDeduction').text(`- ${formatRupiah(discAmount)} (${discType === 'percent' ? discVal + '%' : 'Potongan Langsung'})`).show();
        } else {
            $('#lblDiscountDeduction').hide();
        }

        const totalBeforeTax = Math.max(0, subtotal - discAmount);

        // Tax
        const isTaxable = $('#is_taxable').is(':checked');
        const isInclusive = $('#tax_inclusive').is(':checked');
        let taxAmount = 0;
        let grandTotal = totalBeforeTax;

        if (isTaxable) {
            if (isInclusive) {
                taxAmount = totalBeforeTax - (totalBeforeTax / 1.11);
                grandTotal = totalBeforeTax;
            } else {
                taxAmount = totalBeforeTax * 0.11;
                grandTotal = totalBeforeTax + taxAmount;
            }
            $('#lblTaxAmount').text(formatRupiah(taxAmount));
        } else {
            $('#lblTaxAmount').text('Rp 0');
        }

        $('#inputGrandTotal').val(grandTotal);
        $('#lblGrandTotal').text(formatRupiah(grandTotal));
    }

    $('#discount_type, #discount_val, #is_taxable, #tax_inclusive').on('input change', function() {
        recalculateSummary();
    });

    // 6. SAVE SALES ORDER HANDLER
    function submitSalesOrder() {
        const soNum = $.trim($('#so_number').val());
        const custName = $.trim($('#customer_name').val());

        if (!soNum) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Nomor Pesanan (SO #) wajib diisi!' });
            return;
        }

        if (!custName) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Silakan pilih atau tentukan Customer terlebih dahulu!' });
            return;
        }

        if (items.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Minimal tambahkan 1 baris barang ke dalam pesanan!' });
            return;
        }

        // Validate item names
        let hasEmptyName = false;
        items.forEach(itm => {
            if (!$.trim(itm.item_name) && !$.trim(itm.item_code)) hasEmptyName = true;
        });
        if (hasEmptyName) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Setiap baris barang harus memiliki Nama atau Kode barang.' });
            return;
        }

        // Prepare FormData
        const formData = new FormData($('#formSalesOrder')[0]);
        formData.append('action', 'save_sales_order');
        formData.append('items', JSON.stringify(items));

        const btnSave = $('#btnSaveSo, #btnSaveSoBottom');
        btnSave.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Menyimpan...');

        $.ajax({
            url: 'ajax_sales_order.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                btnSave.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Simpan Pesanan');
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses!',
                        text: res.message || 'Pesanan Penjualan berhasil disimpan.',
                        showDenyButton: true,
                        confirmButtonText: '<i class="bi bi-printer me-1"></i> Cetak SO Sekarang',
                        denyButtonText: '<i class="bi bi-list-check me-1"></i> Ke Daftar Pesanan',
                        confirmButtonColor: '#0F172A',
                        denyButtonColor: '#64748B'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `sales_order_print.php?id=${res.so_id}`;
                        } else {
                            window.location.href = 'sales_orders.php';
                        }
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Terjadi kesalahan saat menyimpan pesanan.' });
                }
            },
            error: function(xhr) {
                btnSave.prop('disabled', false).html('<i class="bi bi-check2 me-1"></i> Simpan Pesanan');
                let errMsg = 'Terjadi gangguan jaringan atau server saat menyimpan data.';
                if (xhr && xhr.responseText) {
                    try {
                        let errObj = JSON.parse(xhr.responseText);
                        if (errObj && errObj.message) errMsg = errObj.message;
                    } catch(e) {}
                }
                Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
            }
        });
    }

    $('#btnSaveSo, #btnSaveSoBottom').on('click', function(e) {
        e.preventDefault();
        submitSalesOrder();
    });

    // Render items if edit mode
    renderItemsTable();
});
</script>

<?php require_once 'includes/footer.php'; ?>
