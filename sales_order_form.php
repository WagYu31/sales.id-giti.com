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
            $uPrice = (float)($row['unit_price'] ?? 0);
            if (isset($row['discount_percent']) && (float)$row['discount_percent'] > 0) {
                $row['discount_percent'] = (float)$row['discount_percent'];
            } elseif (!empty($row['discount_item']) && (float)$row['discount_item'] > 0 && $uPrice > 0) {
                $row['discount_percent'] = round(((float)$row['discount_item'] / $uPrice) * 100, 2);
            } else {
                $row['discount_percent'] = (float)($row['discount_percent'] ?? 0);
            }
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
$packageBundles = [];
$pbJsonFile = __DIR__ . '/includes/package_bundles.json';
if (file_exists($pbJsonFile)) {
    $packageBundles = json_decode(file_get_contents($pbJsonFile), true) ?: [];
}

$qProd = $conn->query("SELECT id, category, type, item_code, description, unit, msrp FROM product_prices ORDER BY category ASC, type ASC");
if ($qProd) {
    while ($p = $qProd->fetch_assoc()) {
        $rawDesc = trim($p['description'] ?? '');
        if (strpos($rawDesc, 'Kode: ') === 0 && strpos($rawDesc, 'Satuan: ') !== false) {
            $rawDesc = '';
        }
        $code = !empty($p['item_code']) ? $p['item_code'] : $p['type'];
        $unit = !empty($p['unit']) ? $p['unit'] : 'UNIT';
        $isPkg = isset($packageBundles[$code]) || (strpos($p['category'], 'PAKET') !== false) || (strpos($p['description'] ?? '', 'GROUP') !== false);
        $catalogProducts[] = [
            'id' => (int)$p['id'],
            'category' => $p['category'],
            'type' => $p['type'],
            'code' => $code,
            'name' => $p['type'],
            'description' => $rawDesc,
            'msrp' => (float)$p['msrp'],
            'unit' => $unit,
            'is_package' => $isPkg
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

.input-group:focus-within .accurate-input {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.06);
    z-index: 3;
}

.input-group:focus-within .input-group-text {
    border-color: #0f172a !important;
    background: #f1f5f9 !important;
    color: #0f172a !important;
    z-index: 3;
}

/* Executive Sub-item & Package Styling */
.table-accurate tbody tr.tr-bundle-package td {
    background-color: #f8fafc;
    border-top: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
    font-weight: 600;
}
.table-accurate tbody tr.tr-bundle-package td:first-child {
    border-left: 3px solid #0284c7;
}
.table-accurate tbody tr.tr-bundle-subitem td {
    background-color: #ffffff;
    border-bottom: 1px dashed #e2e8f0;
}
.table-accurate tbody tr.tr-bundle-subitem td:first-child {
    border-left: 3px solid #e2e8f0;
}
.table-accurate tbody tr.tr-bundle-subitem:hover td {
    background-color: #f8fafc;
}
.item-subitem-name {
    padding-left: 12px !important;
    font-weight: 500 !important;
    color: #334155 !important;
    background-color: #f8fafc !important;
    border-left: 2px solid #bae6fd !important;
}
.item-subitem-name:focus {
    background-color: #ffffff !important;
    border-left-color: #0284c7 !important;
}

/* Executive Stepper in Form */
.so-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    padding: 10px 0;
}
.so-stepper-line {
    position: absolute;
    top: 24px;
    left: 40px;
    right: 40px;
    height: 3px;
    background: #e2e8f0;
    z-index: 1;
}
.so-stepper-line-active {
    position: absolute;
    top: 24px;
    left: 40px;
    height: 3px;
    background: linear-gradient(90deg, #10b981, #0284c7);
    z-index: 2;
    transition: width 0.4s ease;
}
.so-step-item {
    position: relative;
    z-index: 3;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    cursor: pointer;
    transition: transform 0.15s ease;
}
.so-step-item:hover {
    transform: translateY(-2px);
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
    transition: all 0.25s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.so-step-item.completed .so-step-circle {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}
.so-step-item.active .so-step-circle {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.2);
    animation: pulseStep 2s infinite;
}
.so-step-label {
    margin-top: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.so-step-item.completed .so-step-label {
    color: #0f172a;
}
.so-step-item.active .so-step-label {
    color: #0284c7;
    font-weight: 700;
}
@keyframes pulseStep {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
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
        <button type="button" class="btn-secondary-so" id="btnCopyWaFormTop" title="Salin Ringkasan Format WhatsApp">
            <i class="bi bi-whatsapp text-success me-1"></i> Format WA
        </button>
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
                    Pesanan Penjualan: <?php echo $isEdit ? htmlspecialchars(!empty($orderData['so_number']) ? $orderData['so_number'] : '(Belum ada No. SO)') : 'Data Baru'; ?>
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

        <!-- Executive Workflow Stepper Tracker -->
        <div class="px-4 py-2 border-bottom d-none d-md-block" style="background:#f8fafc;" id="formStepperContainer">
            <div class="so-stepper" style="max-width: 650px; margin: 0 auto;">
                <div class="so-stepper-line"></div>
                <div class="so-stepper-line-active" id="formStepperProgress" style="width: 33%;"></div>
                
                <div class="so-step-item" data-step="Draft">
                    <div class="so-step-circle">1</div>
                    <div class="so-step-label">Draft</div>
                </div>
                <div class="so-step-item" data-step="Menunggu">
                    <div class="so-step-circle">2</div>
                    <div class="so-step-label">Menunggu</div>
                </div>
                <div class="so-step-item" data-step="Diproses">
                    <div class="so-step-circle">3</div>
                    <div class="so-step-label">Diproses</div>
                </div>
                <div class="so-step-item" data-step="Selesai">
                    <div class="so-step-circle">4</div>
                    <div class="so-step-label">Selesai</div>
                </div>
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
                    <div id="customerQuickMeta" class="mt-2 text-muted small fw-semibold d-flex flex-wrap align-items-center gap-2" style="display: <?php echo $isEdit ? 'flex !important' : 'none'; ?>;">
                        <span id="txtCustPic"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($orderData['customer_pic'] ?? '-'); ?></span> &bull; 
                        <span id="txtCustPhone"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($orderData['customer_phone'] ?? '-'); ?></span>
                        <?php if ($isEdit && !empty($orderData['customer_phone'])): 
                            $cleanCustPhone = preg_replace('/[^0-9]/', '', $orderData['customer_phone']);
                            if (substr($cleanCustPhone, 0, 1) === '0') $cleanCustPhone = '62' . substr($cleanCustPhone, 1);
                        ?>
                            <a id="btnCustWaLink" href="https://wa.me/<?php echo $cleanCustPhone; ?>" target="_blank" class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none" style="font-size:11px; padding:3px 8px; border-radius:5px;">
                                <i class="bi bi-whatsapp me-1"></i> Chat WA
                            </a>
                        <?php endif; ?>
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

                <!-- No Pesanan # (Opsional jika No. SO dari Finance belum terbit) -->
                <div class="col-lg-3 col-md-3 col-6">
                    <label class="accurate-label">
                        <i class="bi bi-hash me-1"></i> No. Pesanan (SO #)
                    </label>
                    <div class="input-group">
                        <input type="text" name="so_number" id="so_number" class="form-control accurate-input font-monospace text-dark fw-bold" 
                               placeholder="Bisa dikosongkan..."
                               value="<?php echo htmlspecialchars($isEdit ? ($orderData['so_number'] ?? '') : ''); ?>">
                        <button class="btn btn-outline-secondary" type="button" id="btnRefreshSoNum" title="Generate / Isi No. SO Otomatis" style="border-color:#cbd5e1;">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <small class="text-muted" style="font-size:11px;">Opsional &bull; Format: <strong>YYMM.SOL.XXXXX</strong> (bisa dikosongkan jika dari Finance belum ada)</small>
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
                                <option value="">-- Cari &amp; Pilih Barang / Jasa (Ketik Nama Barang, Kode 8800xxx, atau Kategori) --</option>
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
                                    <th style="width: 14%;">Kode / SKU #</th>
                                    <th style="width: 8%; text-align: center;">Qty</th>
                                    <th style="width: 8%; text-align: center;">Satuan</th>
                                    <th style="width: 14%; text-align: right;">@Harga (Rp)</th>
                                    <th style="width: 11%; text-align: right;">Diskon (%)</th>
                                    <th style="width: 15%; text-align: right;">Total Harga (Rp)</th>
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

                <!-- Financial Summary Footer (Subtotal, Diskon, PPN, Grand Total & Terbilang) -->
                <div class="summary-container">
                    <div class="row justify-content-between align-items-start g-4">
                        <!-- Kolom Kiri: Live Terbilang Rupiah & Ringkasan Dokumen -->
                        <div class="col-lg-6 col-md-5">
                            <div class="p-3 rounded-3" style="background:#ffffff; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="p-1 px-2 rounded-2" style="background:#fef3c7; color:#d97706; font-size:12px; font-weight:700;">
                                        <i class="bi bi-cash-stack me-1"></i> TERBILANG RESMI
                                    </div>
                                    <span class="text-muted small" style="font-size:11px;">(Sesuai Standar Faktur Pajak)</span>
                                </div>
                                <div class="p-2 px-3 rounded-2" style="background:#f8fafc; border:1px dashed #cbd5e1; font-size:13px; font-weight:600; color:#0f172a; font-style:italic; line-height:1.5;" id="lblFormTerbilangWord">
                                    Nol Rupiah
                                </div>
                                <div class="mt-2 text-muted small d-flex align-items-center gap-1" style="font-size:11px;">
                                    <i class="bi bi-shield-check text-success"></i> Kalimat terbilang otomatis tersinkronisasi secara real-time dan tercetak pada SO resmi.
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Executive Financial Summary Card -->
                        <div class="col-lg-5 col-md-7">
                            <div class="summary-card" style="border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04);">
                                <div class="summary-row">
                                    <span class="fw-semibold text-secondary">Sub Total:</span>
                                    <span class="fw-bold font-monospace text-dark" id="lblSubtotal">Rp 0</span>
                                    <input type="hidden" name="subtotal" id="inputSubtotal" value="0">
                                </div>

                                <div class="summary-row align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold text-secondary">Diskon Tambahan:</span>
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
                                <div class="text-end small pe-1 pb-1" id="lblDiscountDeduction" style="display:none; font-size:11.5px; color:#ef4444 !important; font-weight:600;">
                                    - Rp 0
                                </div>

                                <div class="summary-row border-top pt-2">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="is_taxable" id="is_taxable" value="1" 
                                               <?php echo (!empty($orderData['is_taxable'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold text-secondary" for="is_taxable">
                                            Kena Pajak PPN (11%)
                                        </label>
                                    </div>
                                    <span class="fw-bold font-monospace text-dark" id="lblTaxAmount">Rp 0</span>
                                </div>

                                <div class="summary-row total-row" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #ffffff; padding: 14px 16px; border-radius: 8px; margin-top: 12px;">
                                    <span style="color:#e2e8f0; font-size:13px; font-weight:600;">Total (Grand Total):</span>
                                    <span class="fw-bold font-monospace" id="lblGrandTotal" style="font-size:22px; color:#38bdf8; letter-spacing:-0.5px;">Rp 0</span>
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
            <?php if ($isEdit): ?>
            <button type="button" class="btn-secondary-so" id="btnCopyWaFormBottom" title="Salin Ringkasan Format WhatsApp">
                <i class="bi bi-whatsapp text-success me-1"></i> Format WA
            </button>
            <?php endif; ?>
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
    const packageBundles = <?php echo json_encode($packageBundles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || {};

    // Intelligent Pass: Deteksi & normalisasi paket dan komponen sub-item pada pesanan yang dibuka
    let activeInitBundleId = null;
    items.forEach((item, idx) => {
        const nameLower = (item.item_name || '').toLowerCase();
        const descLower = (item.item_description || '').toLowerCase();
        const price = parseFloat(item.unit_price) || 0;

        const isExplicitSub = (item.item_name && item.item_name.startsWith('--')) ||
                              descLower.includes('komponen paket') ||
                              descLower.includes('komponen dari') ||
                              (item.is_subitem === true);

        const isExplicitPkg = item.is_package ||
                              !!item.bundle_id ||
                              nameLower.includes('paket') ||
                              (packageBundles && (packageBundles[item.item_code] || packageBundles[item.item_name])) ||
                              (item.item_code && (item.item_code.startsWith('88003') || item.item_code === '8800513'));

        if (isExplicitPkg && price > 0) {
            item.is_package = true;
            if (!item.bundle_id) {
                item.bundle_id = 'bndl_init_' + idx + '_' + Date.now();
            }
            activeInitBundleId = item.bundle_id;
        } else if (isExplicitSub || (price === 0 && activeInitBundleId !== null)) {
            item.is_subitem = true;
            if (!item.parent_bundle_id && activeInitBundleId) {
                item.parent_bundle_id = activeInitBundleId;
            }
        } else {
            activeInitBundleId = null;
        }
    });

    // Helper: Tambahkan produk ke tabel (Otomatis pecah menjadi paket + rincian sub-items jika barang grup/paket)
    function addSelectedProduct(prod) {
        const code = (prod.code || prod.item_code || '').trim();
        const name = (prod.name || prod.type || '').trim();
        const pkg = packageBundles[code] || packageBundles[name];

        if (pkg && pkg.items && pkg.items.length > 0) {
            const bundleId = 'bndl_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
            
            // 1. Tambah baris induk paket (Harga penuh paket)
            items.push({
                product_id: prod.id || null,
                item_code: code,
                item_name: name,
                item_description: prod.description || '',
                qty: 1,
                unit: prod.unit || 'SET',
                unit_price: prod.msrp || 0,
                discount_percent: 0,
                discount_item: 0,
                total_price: prod.msrp || 0,
                is_package: true,
                bundle_id: bundleId
            });

            // 2. Tambah otomatis rincian barang grup (komponen paket) dengan harga 0
            pkg.items.forEach(sub => {
                const subName = sub.name.startsWith('--') ? sub.name : ('--' + sub.name);
                items.push({
                    product_id: null,
                    item_code: sub.code,
                    item_name: subName,
                    item_description: sub.description || ('Komponen dari ' + name),
                    qty: sub.qty,
                    base_qty: sub.qty,
                    unit: sub.unit || 'UNIT',
                    unit_price: 0,
                    discount_percent: 0,
                    discount_item: 0,
                    total_price: 0,
                    is_subitem: true,
                    parent_bundle_id: bundleId
                });
            });
            renderItemsTable();
        } else {
            // Barang satuan biasa
            addItemRow({
                product_id: prod.id || null,
                item_code: code,
                item_name: name,
                item_description: prod.description || '',
                qty: 1,
                unit: prod.unit || 'UNIT',
                unit_price: prod.msrp || 0,
                discount_percent: 0,
                discount_item: 0,
                total_price: prod.msrp || 0
            });
        }
    }

    // Format Rupiah Helper
    function formatRupiah(num) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
    }

    // Indonesian Terbilang Rupiah Helper
    function terbilangRupiah(angka) {
        angka = Math.floor(Math.abs(Number(angka) || 0));
        if (angka === 0) return 'Nol Rupiah';
        const bilangan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        function sebut(n) {
            n = Math.floor(n);
            if (n < 12) return bilangan[n];
            if (n < 20) return sebut(n - 10) + ' Belas';
            if (n < 100) return sebut(Math.floor(n / 10)) + ' Puluh ' + sebut(n % 10);
            if (n < 200) return 'Seratus ' + sebut(n - 100);
            if (n < 1000) return sebut(Math.floor(n / 100)) + ' Ratus ' + sebut(n % 100);
            if (n < 2000) return 'Seribu ' + sebut(n - 1000);
            if (n < 1000000) return sebut(Math.floor(n / 1000)) + ' Ribu ' + sebut(n % 1000);
            if (n < 1000000000) return sebut(Math.floor(n / 1000000)) + ' Juta ' + sebut(n % 1000000);
            if (n < 1000000000000) return sebut(Math.floor(n / 1000000000)) + ' Miliar ' + sebut(n % 1000000000);
            return sebut(Math.floor(n / 1000000000000)) + ' Triliun ' + sebut(n % 1000000000000);
        }
        return (sebut(angka).replace(/\s+/g, ' ').trim()) + ' Rupiah';
    }

    // Stepper Synchronization Helper
    function updateFormStepper(status) {
        const steps = ['Draft', 'Menunggu', 'Diproses', 'Selesai'];
        const curIdx = steps.indexOf(status);
        const pct = curIdx >= 0 ? (curIdx / (steps.length - 1)) * 100 : 0;
        $('#formStepperProgress').css('width', pct + '%');

        $('#formStepperContainer .so-step-item').each(function(i) {
            $(this).removeClass('active completed');
            if (i < curIdx) {
                $(this).addClass('completed');
                $(this).find('.so-step-circle').html('<i class="bi bi-check-lg"></i>');
            } else if (i === curIdx) {
                $(this).addClass('active');
                $(this).find('.so-step-circle').text(i + 1);
            } else {
                $(this).find('.so-step-circle').text(i + 1);
            }
        });
    }

    // Sync Stepper on status change & click
    $('#status').on('change', function() {
        updateFormStepper($(this).val());
    });
    $(document).on('click', '#formStepperContainer .so-step-item', function() {
        const stepName = $(this).data('step');
        if (stepName) {
            $('#status').val(stepName).trigger('change');
        }
    });
    updateFormStepper($('#status').val());

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
        placeholder: '-- Cari & Pilih Barang / Jasa (Ketik Nama Barang, Kode 8800xxx, atau Kategori) --',
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
        addSelectedProduct(prod);

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

    // 4. RENDER ITEMS TABLE (Executive BOM Hierarchy Standard)
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
            $('#txtTotalItemsCount').text('0 Baris (0 Kuantitas)');
            recalculateSummary();
            return;
        }

        let totalQty = 0;
        let packageIndex = 0;
        let subIndex = 0;
        let activeParentPkg = null;

        items.forEach((item, idx) => {
            const nameLower = (item.item_name || '').toLowerCase();
            const descLower = (item.item_description || '').toLowerCase();
            const uPrice = parseFloat(item.unit_price) || 0;

            const isExplicitSub = item.is_subitem === true ||
                                  (item.item_name && item.item_name.startsWith('--')) ||
                                  descLower.includes('komponen paket') ||
                                  descLower.includes('komponen dari');

            const isPkg = !isExplicitSub && (
                item.is_package === true ||
                !!item.bundle_id ||
                nameLower.includes('paket') ||
                (packageBundles && (packageBundles[item.item_code] || packageBundles[item.item_name])) ||
                (item.item_code && (item.item_code.startsWith('88003') || item.item_code === '8800513'))
            );

            if (isPkg) {
                activeParentPkg = item;
                packageIndex++;
                subIndex = 0;
                item.is_package = true;
            }

            const isSub = isExplicitSub || (!isPkg && uPrice === 0 && activeParentPkg !== null);
            if (isSub) {
                subIndex++;
                item.is_subitem = true;
            } else if (!isPkg) {
                activeParentPkg = null;
                packageIndex++;
                subIndex = 0;
                item.is_subitem = false;
                item.is_package = false;
            }

            const qty = Math.max(1, parseInt(item.qty) || 1);
            
            let discPct = 0;
            if (item.discount_percent !== undefined && item.discount_percent !== null) {
                discPct = parseFloat(item.discount_percent) || 0;
            } else if (item.discount_item && uPrice > 0) {
                discPct = Math.round(((parseFloat(item.discount_item) || 0) / uPrice) * 10000) / 100;
            }
            discPct = Math.min(100, Math.max(0, discPct));
            
            const discAmountPerUnit = uPrice * (discPct / 100);
            const lineTotal = isSub ? 0 : (qty * Math.max(0, (uPrice - discAmountPerUnit)));
            item.discount_percent = isSub ? 0 : discPct;
            item.discount_item = isSub ? 0 : discAmountPerUnit;
            item.total_price = lineTotal;
            totalQty += qty;

            const trClass = isSub ? 'tr-bundle-subitem' : (isPkg ? 'tr-bundle-package' : '');
            const cleanName = (item.item_name || '').replace(/^--\s*/, '').trim();

            let noColHtml = '';
            if (isPkg) {
                noColHtml = `<span class="badge" style="background:#0f172a; color:#f8fafc; font-size:11px; font-weight:700; border-radius:5px; padding:3px 7px;">${packageIndex}</span>`;
            } else if (isSub) {
                noColHtml = `
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <span style="color:#94a3b8; font-family:monospace; font-size:13px; font-weight:700;">↳</span>
                        <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:10px; font-weight:700; padding:2px 5px; border-radius:4px;">${packageIndex}.${subIndex}</span>
                    </div>
                `;
            } else {
                noColHtml = `<span class="badge" style="background:#f1f5f9; color:#334155; border:1px solid #e2e8f0; font-size:11px; font-weight:700; border-radius:5px; padding:3px 7px;">${packageIndex}</span>`;
            }

            let badgeHeader = '';
            if (isPkg) {
                badgeHeader = `
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge" style="background:linear-gradient(135deg, #0f172a, #1e293b); color:#38bdf8; border:1px solid #334155; font-size:9.5px; font-weight:700; padding:2.5px 7px; border-radius:4px; letter-spacing:0.5px;">
                            <i class="bi bi-box-seam me-1"></i>PAKET BUNDLE
                        </span>
                    </div>
                `;
            } else if (isSub) {
                badgeHeader = `
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:9.5px; font-weight:600; padding:2px 6px; border-radius:4px;">
                            <i class="bi bi-diagram-3 me-1"></i>Komponen Paket
                        </span>
                    </div>
                `;
            }

            const nameInputClass = isSub ? 'form-control form-control-sm accurate-input item-field-name item-subitem-name' : 'form-control form-control-sm accurate-input fw-semibold item-field-name text-dark';

            let priceColHtml = '';
            let discColHtml = '';
            let totalColHtml = '';

            if (isSub) {
                priceColHtml = `
                    <div class="position-relative">
                        <input type="number" min="0" step="any" class="form-control form-control-sm accurate-input text-end font-monospace item-field-price" style="background:#f8fafc; color:#94a3b8; font-size:12px;" data-index="${idx}" value="0" readonly title="Harga sudah termasuk dalam paket">
                        <span style="position:absolute; right:8px; top:6px; font-size:10px; font-weight:600; color:#059669; pointer-events:none; background:#ecfdf5; padding:1px 5px; border-radius:3px;">Termasuk</span>
                    </div>
                `;
                discColHtml = `
                    <div class="text-center text-muted small py-1" style="font-size:12px;">-</div>
                    <input type="hidden" class="item-field-disc" data-index="${idx}" value="0">
                `;
                totalColHtml = `
                    <span class="badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-size:11px; font-weight:700; padding:4px 8px; border-radius:6px; white-space:nowrap;">
                        <i class="bi bi-check2-circle me-1"></i>Termasuk Paket
                    </span>
                `;
            } else {
                priceColHtml = `
                    <input type="number" min="0" step="any" class="form-control form-control-sm accurate-input text-end font-monospace item-field-price ${isPkg ? 'fw-bold text-dark' : ''}" data-index="${idx}" value="${uPrice}">
                `;
                discColHtml = `
                    <div class="input-group input-group-sm" style="min-width: 80px;">
                        <input type="number" min="0" max="100" step="any" class="form-control form-control-sm accurate-input text-end font-monospace item-field-disc" data-index="${idx}" value="${discPct}" placeholder="0" style="border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; border-right: 0 !important;">
                        <span class="input-group-text font-monospace fw-bold" style="background: #f8fafc; color: #475569; font-size: 11.5px; padding: 0 7px; border: 1px solid #cbd5e1; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">%</span>
                    </div>
                `;
                totalColHtml = `
                    <span class="fw-bold font-monospace ${isPkg ? 'text-primary' : 'text-dark'}" style="font-size:13.5px;">${formatRupiah(lineTotal)}</span>
                `;
            }

            const tr = $(`
                <tr data-index="${idx}" class="${trClass}">
                    <td style="text-align: center; vertical-align: top; padding-top: 12px;">
                        <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                            ${noColHtml}
                            <button type="button" class="btn-row-delete btn-remove-item mt-1" data-index="${idx}" title="${isPkg ? 'Hapus Paket & Seluruh Komponennya' : 'Hapus Baris'}">
                                <i class="bi bi-trash" style="font-size:12px;"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        ${badgeHeader}
                        <input type="text" list="catalogProductsDatalist" class="${nameInputClass}" data-index="${idx}" value="${escapeHtml(cleanName)}" placeholder="Ketik / Pilih Nama Barang">
                        <input type="text" class="form-control form-control-sm accurate-input text-muted item-field-desc mt-1" style="font-size: 11px; padding: 3px 8px; background: #fafafa;" data-index="${idx}" value="${escapeHtml(item.item_description || '')}" placeholder="+ Deskripsi / Catatan Tambahan (opsional)">
                    </td>
                    <td style="vertical-align: top; padding-top: ${badgeHeader ? '32px' : '10px'};">
                        <input type="text" list="catalogCodesDatalist" class="form-control form-control-sm accurate-input font-monospace item-field-code" data-index="${idx}" value="${escapeHtml(item.item_code || '')}" placeholder="Kode / SKU">
                    </td>
                    <td style="text-align: center; vertical-align: top; padding-top: ${badgeHeader ? '32px' : '10px'};">
                        <input type="number" min="1" step="1" class="form-control form-control-sm accurate-input text-center fw-bold item-field-qty" data-index="${idx}" value="${qty}">
                    </td>
                    <td style="text-align: center; vertical-align: top; padding-top: ${badgeHeader ? '32px' : '10px'};">
                        <input type="text" class="form-control form-control-sm accurate-input text-center item-field-unit" data-index="${idx}" value="${escapeHtml(item.unit || 'UNIT')}" placeholder="UNIT">
                    </td>
                    <td style="text-align: right; vertical-align: top; padding-top: ${badgeHeader ? '32px' : '10px'};">
                        ${priceColHtml}
                    </td>
                    <td style="text-align: right; vertical-align: top; padding-top: ${badgeHeader ? '32px' : '10px'};">
                        ${discColHtml}
                    </td>
                    <td style="text-align: right; vertical-align: middle;" class="line-total-cell">
                        ${totalColHtml}
                    </td>
                </tr>
            `);
            tbody.append(tr);
        });

        $('#txtTotalItemsCount').text(`${items.length} Baris (${totalQty} Kuantitas)`);
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
            unit: 'UNIT',
            unit_price: 0,
            discount_percent: 0,
            discount_item: 0,
            total_price: 0
        });
    });

    // Remove Item
    $(document).on('click', '.btn-remove-item', function() {
        const idx = $(this).data('index');
        const target = items[idx];
        if (target && target.bundle_id) {
            // Hapus paket induk beserta seluruh rincian komponen sub-itemnya
            const bId = target.bundle_id;
            items = items.filter(it => it !== target && it.parent_bundle_id !== bId);
        } else {
            items.splice(idx, 1);
        }
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
                items[idx].unit = matched.unit || 'UNIT';
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-unit`).val(items[idx].unit);
            }
            if (!items[idx].item_description) {
                items[idx].item_description = matched.description;
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-desc`).val(matched.description);
            }

            // Cek apakah produk yang dipilih adalah paket bundle
            const pkg = packageBundles[matched.code] || packageBundles[matched.name];
            if (pkg && pkg.items && pkg.items.length > 0 && !items[idx].has_expanded_bundle) {
                items[idx].has_expanded_bundle = true;
                items[idx].is_package = true;
                const bundleId = 'bndl_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
                items[idx].bundle_id = bundleId;
                const pQty = items[idx].qty || 1;

                const subRows = pkg.items.map(sub => {
                    const subName = sub.name.startsWith('--') ? sub.name : ('--' + sub.name);
                    return {
                        product_id: null,
                        item_code: sub.code,
                        item_name: subName,
                        item_description: sub.description || ('Komponen dari ' + matched.name),
                        qty: sub.qty * pQty,
                        base_qty: sub.qty,
                        unit: sub.unit || 'UNIT',
                        unit_price: 0,
                        discount_percent: 0,
                        discount_item: 0,
                        total_price: 0,
                        is_subitem: true,
                        parent_bundle_id: bundleId
                    };
                });

                items.splice(idx + 1, 0, ...subRows);
                renderItemsTable();
                return;
            }

            recalculateRow(idx);
        }
    });

    $(document).on('input change', '.item-field-desc', function() {
        const idx = $(this).data('index');
        items[idx].item_description = $(this).val();
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
                items[idx].unit = matched.unit || 'UNIT';
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-unit`).val(items[idx].unit);
            }
            if (!items[idx].item_description) {
                items[idx].item_description = matched.description;
                $(`#itemsTableBody tr[data-index="${idx}"] .item-field-desc`).val(matched.description);
            }

            // Cek apakah kode yang dipilih adalah paket bundle
            const pkg = packageBundles[matched.code] || packageBundles[matched.name];
            if (pkg && pkg.items && pkg.items.length > 0 && !items[idx].has_expanded_bundle) {
                items[idx].has_expanded_bundle = true;
                items[idx].is_package = true;
                const bundleId = 'bndl_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
                items[idx].bundle_id = bundleId;
                const pQty = items[idx].qty || 1;

                const subRows = pkg.items.map(sub => {
                    const subName = sub.name.startsWith('--') ? sub.name : ('--' + sub.name);
                    return {
                        product_id: null,
                        item_code: sub.code,
                        item_name: subName,
                        item_description: sub.description || ('Komponen dari ' + matched.name),
                        qty: sub.qty * pQty,
                        base_qty: sub.qty,
                        unit: sub.unit || 'UNIT',
                        unit_price: 0,
                        discount_percent: 0,
                        discount_item: 0,
                        total_price: 0,
                        is_subitem: true,
                        parent_bundle_id: bundleId
                    };
                });

                items.splice(idx + 1, 0, ...subRows);
                renderItemsTable();
                return;
            }

            recalculateRow(idx);
        }
    });
    $(document).on('input change', '.item-field-qty', function() {
        const idx = $(this).data('index');
        const val = Math.max(1, parseInt($(this).val()) || 1);
        items[idx].qty = val;

        // Jika baris ini adalah paket induk, perbarui kuantitas seluruh rincian komponen sub-item
        if (items[idx].bundle_id) {
            const bId = items[idx].bundle_id;
            items.forEach((child, cIdx) => {
                if (child.parent_bundle_id === bId) {
                    const baseQ = child.base_qty || 1;
                    child.qty = baseQ * val;
                    $(`#itemsTableBody tr[data-index="${cIdx}"] .item-field-qty`).val(child.qty);
                }
            });
        }
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
        const val = Math.min(100, Math.max(0, parseFloat($(this).val()) || 0));
        items[idx].discount_percent = val;
        recalculateRow(idx);
    });

    function recalculateRow(idx) {
        const itm = items[idx];
        const qty = Math.max(1, parseInt(itm.qty) || 1);
        const price = Math.max(0, parseFloat(itm.unit_price) || 0);
        const discPct = Math.min(100, Math.max(0, parseFloat(itm.discount_percent) || 0));
        const discAmountPerUnit = price * (discPct / 100);
        const total = itm.is_subitem ? 0 : qty * Math.max(0, price - discAmountPerUnit);
        
        itm.discount_percent = itm.is_subitem ? 0 : discPct;
        itm.discount_item = itm.is_subitem ? 0 : discAmountPerUnit;
        itm.total_price = total;

        if (itm.is_subitem) {
            $(`#itemsTableBody tr[data-index="${idx}"] .line-total-cell`).html(`
                <span class="badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-size:11px; font-weight:700; padding:4px 8px; border-radius:6px; white-space:nowrap;">
                    <i class="bi bi-check2-circle me-1"></i>Termasuk Paket
                </span>
            `);
        } else {
            $(`#itemsTableBody tr[data-index="${idx}"] .line-total-cell`).html(`
                <span class="fw-bold font-monospace ${itm.is_package ? 'text-primary' : 'text-dark'}" style="font-size:13.5px;">${formatRupiah(total)}</span>
            `);
        }
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

        // Live Terbilang Rupiah Synchronization
        const terbilangText = terbilangRupiah(grandTotal);
        $('#lblFormTerbilangWord').text(terbilangText);
    }

    $('#discount_type, #discount_val, #is_taxable, #tax_inclusive').on('input change', function() {
        recalculateSummary();
    });

    // WhatsApp Summary Copy Handler in Form
    $('#btnCopyWaFormTop, #btnCopyWaFormBottom').on('click', function(e) {
        e.preventDefault();
        const soNum = $.trim($('#so_number').val()) || '(Draft / Menunggu)';
        const custName = $.trim($('#customer_name').val()) || '-';
        const salesName = $('#sales_id option:selected').text().trim() || '-';
        const grandTotal = parseFloat($('#inputGrandTotal').val()) || 0;
        const status = $('#status').val() || 'Menunggu';
        const payTerms = $('#payment_terms').val() || 'C.O.D';
        const specialNotes = $.trim($('#special_notes').val());

        let text = `*PESANAN PENJUALAN - LOEWIX CCTV*\n`;
        text += `No. Pesanan: ${soNum}\n`;
        text += `Pelanggan: ${custName}\n`;
        text += `Sales PIC: ${salesName}\n`;
        text += `Status: ${status}\n`;
        text += `-------------------------------------------\n`;
        text += `*RINCIAN BARANG:*\n`;

        let pNum = 0;
        let sNum = 0;
        let activeParent = null;

        items.forEach(function(item) {
            const nameLower = (item.item_name || '').toLowerCase();
            const descLower = (item.item_description || '').toLowerCase();
            const isExplicitSub = (item.item_name || '').trim().startsWith('--') || descLower.includes('komponen paket') || item.is_subitem === true;
            const isPkg = !isExplicitSub && (nameLower.includes('paket') || item.is_package === true || (item.item_code && (item.item_code.startsWith('88003') || item.item_code === '8800513')));

            if (isPkg) {
                activeParent = item;
                pNum++;
                sNum = 0;
            }

            const isSub = isExplicitSub || (!isPkg && parseFloat(item.unit_price) === 0 && activeParent !== null);
            const cleanName = (item.item_name || '').replace(/^--\s*/, '').trim();

            if (isPkg) {
                text += `\n📦 *${pNum}. [PAKET] ${cleanName}* (${item.qty} ${item.unit || 'SET'}) - ${formatRupiah(item.total_price)}\n`;
            } else if (isSub) {
                sNum++;
                text += `   ↳ ${pNum}.${sNum} ${cleanName} (${item.qty} ${item.unit || 'UNIT'}) [Termasuk Paket]\n`;
            } else {
                activeParent = null;
                pNum++;
                sNum = 0;
                text += `${pNum}. ${cleanName} (${item.qty} ${item.unit || 'UNIT'}) - ${formatRupiah(item.total_price)}\n`;
            }
        });

        text += `-------------------------------------------\n`;
        text += `*TOTAL PEMBAYARAN: ${formatRupiah(grandTotal)}*\n`;
        text += `_Terbilang: ${terbilangRupiah(grandTotal)}_\n`;
        text += `Syarat Bayar: ${payTerms}\n`;
        if (specialNotes) text += `Catatan: ${specialNotes}\n`;

        navigator.clipboard.writeText(text).then(function() {
            Swal.fire({
                icon: 'success',
                title: 'Format WhatsApp Disalin!',
                text: 'Rincian pesanan berhasil disalin ke clipboard.',
                timer: 2000,
                showConfirmButton: false
            });
        });
    });

    // 6. SAVE SALES ORDER HANDLER
    function submitSalesOrder() {
        const soNum = $.trim($('#so_number').val());
        const custName = $.trim($('#customer_name').val());

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
