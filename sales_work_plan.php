<?php
/**
 * Rencana Kerja Sales
 * PT. Loewix Indonesia - Sales Management System
 */

$page_title = 'Rencana Kerja Sales';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'sales';
$user_name = $_SESSION['nama_lengkap'] ?? 'User';
$is_admin  = in_array($user_role, ['superadmin', 'adminsales']);

// Fetch list of sales for filter dropdown (if admin)
$sales_list = [];
if ($is_admin) {
    $sq = $conn->query("SELECT id, nama_lengkap, email FROM sales WHERE role = 'sales' AND deleted_at IS NULL ORDER BY nama_lengkap ASC");
    if ($sq) {
        $sales_list = $sq->fetch_all(MYSQLI_ASSOC);
    }
}

$current_year  = (int)date('Y');
$current_month = (int)date('n');
$current_day   = (int)date('j');
$current_week  = (int)ceil($current_day / 7);

$bulan_nama = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<style>
/* ================= PAGE HERO ================= */
.workplan-hero {
    background: linear-gradient(135deg, #0A192F 0%, #1E3A8A 50%, #2563EB 100%);
    border-radius: 22px;
    padding: 30px 36px;
    margin-bottom: 24px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.45);
}

.workplan-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
}

.workplan-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
}

.workplan-hero-subtitle {
    font-size: 13.5px;
    color: rgba(226, 232, 240, 0.88);
    margin: 0;
    max-width: 680px;
    line-height: 1.5;
}

/* ================= SUMMARY STAT CARDS ================= */
.stat-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.method-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}

.method-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px -4px rgba(0,0,0,0.08);
    border-color: #CBD5E1;
}

.method-card .icon-box {
    width: 42px; height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
    margin-bottom: 12px;
}

.method-card .card-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.method-card .card-value {
    font-size: 24px;
    font-weight: 800;
    color: #0F172A;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    line-height: 1;
}

/* Specific Card Themes */
.card-phone .icon-box { background: #EFF6FF; color: #2563EB; }
.card-phone:hover { border-color: #93C5FD; }

.card-wa .icon-box { background: #ECFDF5; color: #10B981; }
.card-wa:hover { border-color: #86EFAC; }

.card-email .icon-box { background: #FEF3C7; color: #D97706; }
.card-email:hover { border-color: #FDE68A; }

.card-ketemu .icon-box { background: #F3E8FF; color: #8B5CF6; }
.card-ketemu:hover { border-color: #D8B4FE; }

.card-total .icon-box { background: #F1F5F9; color: #475569; }
.card-total:hover { border-color: #CBD5E1; }

.card-progress .icon-box { background: #E0E7FF; color: #4F46E5; }
.card-progress:hover { border-color: #A5B4FC; }

/* ================= FILTER TOOLBAR ================= */
.filter-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.filter-title {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-label-custom {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 5px;
}

.form-select-custom, .form-control-custom {
    border-radius: 10px;
    border: 1px solid #CBD5E1;
    font-size: 13px;
    padding: 7px 12px;
    background-color: #F8FAFC;
    transition: all 0.2s ease;
}

.form-select-custom:focus, .form-control-custom:focus {
    border-color: #2563EB;
    background-color: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

/* ================= WORK PLAN TABLE ================= */
.table-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
}

.table-header-box {
    padding: 20px 24px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    background: #FAFBFD;
}

.table-header-box h2 {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.custom-table {
    margin-bottom: 0;
    width: 100%;
}

.custom-table th {
    background: #0F172A;
    color: #F8FAFC;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 13px 14px;
    border: none;
    vertical-align: middle;
    white-space: nowrap;
}

.custom-table td {
    padding: 13px 14px;
    vertical-align: middle;
    font-size: 13px;
    color: #334155;
    border-bottom: 1px solid #F1F5F9;
}

.custom-table tbody tr {
    transition: background-color 0.15s ease;
}

.custom-table tbody tr:hover {
    background-color: #F8FAFC;
}

.custom-table tbody tr.row-done {
    background-color: rgba(240, 253, 244, 0.45);
}

/* Status Check Switch */
.status-switch-container {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.form-check-input.admin-check {
    width: 22px;
    height: 22px;
    cursor: pointer;
    border: 2px solid #94A3B8;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.form-check-input.admin-check:checked {
    background-color: #10B981;
    border-color: #10B981;
}

.form-check-input.sales-disabled {
    cursor: not-allowed !important;
    opacity: 0.75;
}

/* Method Badges */
.badge-method {
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.badge-method-phone { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
.badge-method-wa { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
.badge-method-email { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.badge-method-ketemu { background: #F3E8FF; color: #6D28D9; border: 1px solid #DDD6FE; }
.badge-method-other { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }

/* Status Badges */
.badge-status-done {
    background: #DCFCE7;
    color: #15803D;
    border: 1px solid #86EFAC;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-status-pending {
    background: #F1F5F9;
    color: #64748B;
    border: 1px solid #CBD5E1;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Action Icons */
.btn-action-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    text-decoration: none;
    color: #64748B;
    background: #F8FAFC;
}

.btn-action-icon:hover {
    color: #2563EB;
    background: #EFF6FF;
    border-color: #BFDBFE;
    transform: translateY(-1px);
}

.btn-action-icon.btn-delete:hover {
    color: #EF4444;
    background: #FEF2F2;
    border-color: #FECACA;
}

/* Multi-row Batch Table */
.batch-table th {
    background: #F1F5F9;
    color: #334155;
    font-size: 11.5px;
    font-weight: 700;
    padding: 8px;
    border: 1px solid #E2E8F0;
}

.batch-table td {
    padding: 6px;
    border: 1px solid #E2E8F0;
}

.batch-table input, .batch-table select, .batch-table textarea {
    font-size: 12px;
    border-radius: 6px;
    border: 1px solid #CBD5E1;
    padding: 5px 8px;
    width: 100%;
}
</style>

<!-- ================= HERO HEADER ================= -->
<div class="workplan-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Rencana Kerja Sales</span>
            </div>
            <h1 class="workplan-hero-title">Rencana Kerja Sales 📅</h1>
            <p class="workplan-hero-subtitle">
                Susun jadwal rencana kerja, follow up prospek, pantau realisasi mingguan, serta verifikasi aktivitas sales.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0">
            <button type="button" class="btn btn-light fw-bold text-primary shadow-sm" id="btn-open-batch-add" style="border-radius: 12px; font-size:13.5px; padding:9px 16px;">
                <i class="bi bi-table me-1.5"></i> Input Cepat (Multi-Baris)
            </button>
            <button type="button" class="btn btn-primary fw-bold shadow-lg" id="btn-open-add" style="background:#2563EB; border-color:#2563EB; border-radius: 12px; font-size:13.5px; padding:9px 18px;">
                <i class="bi bi-plus-circle-fill me-1.5"></i> Tambah Rencana
            </button>
            <button type="button" class="btn btn-outline-light fw-bold shadow-sm" id="btn-export-excel" style="border-radius: 12px; font-size:13.5px; padding:9px 16px;">
                <i class="bi bi-file-earmark-excel-fill text-success me-1.5"></i> Unduh Excel
            </button>
        </div>
    </div>
</div>

<!-- ================= SUMMARY KPI CARDS (Sesuai Spreadsheet) ================= -->
<div class="stat-cards-grid" id="summary-container">
    <div class="method-card card-phone">
        <div class="icon-box"><i class="bi bi-telephone-fill"></i></div>
        <div class="card-label">Phone Call</div>
        <div class="card-value" id="kpi-phone">0</div>
    </div>
    <div class="method-card card-wa">
        <div class="icon-box"><i class="bi bi-whatsapp"></i></div>
        <div class="card-label">Text Whatsapp</div>
        <div class="card-value" id="kpi-wa">0</div>
    </div>
    <div class="method-card card-email">
        <div class="icon-box"><i class="bi bi-envelope-fill"></i></div>
        <div class="card-label">Email</div>
        <div class="card-value" id="kpi-email">0</div>
    </div>
    <div class="method-card card-ketemu">
        <div class="icon-box"><i class="bi bi-people-fill"></i></div>
        <div class="card-label">Ketemu Langsung</div>
        <div class="card-value" id="kpi-ketemu">0</div>
    </div>
    <div class="method-card card-total">
        <div class="icon-box"><i class="bi bi-clipboard2-check-fill"></i></div>
        <div class="card-label">Total Rencana</div>
        <div class="card-value" id="kpi-total">0</div>
    </div>
    <div class="method-card card-progress">
        <div class="icon-box"><i class="bi bi-patch-check-fill"></i></div>
        <div class="card-label">Sudah Dilakukan</div>
        <div class="d-flex align-items-baseline gap-2">
            <div class="card-value" id="kpi-done">0</div>
            <span class="badge bg-success-subtle text-success fw-bold" id="kpi-percentage" style="font-size:11px;">0%</span>
        </div>
    </div>
</div>

<!-- ================= FILTER TOOLBAR ================= -->
<div class="filter-card">
    <div class="filter-title">
        <i class="bi bi-funnel-fill text-primary"></i> Filter & Pencarian Rencana Kerja
    </div>
    <div class="row g-3">
        <?php if ($is_admin): ?>
        <div class="col-md-3 col-sm-6">
            <label class="form-label-custom"><i class="bi bi-person-badge me-1"></i>Pilih Sales</label>
            <select class="form-select form-select-custom" id="filter-sales">
                <option value="">-- Semua Sales --</option>
                <?php foreach ($sales_list as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nama_lengkap']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <input type="hidden" id="filter-sales" value="<?php echo $user_id; ?>">
        <?php endif; ?>

        <div class="col-md-2 col-sm-6">
            <label class="form-label-custom"><i class="bi bi-calendar-month me-1"></i>Bulan</label>
            <select class="form-select form-select-custom" id="filter-bulan">
                <option value="">Semua Bulan</option>
                <?php foreach ($bulan_nama as $num => $nama): ?>
                    <option value="<?php echo $num; ?>" <?php echo $num == $current_month ? 'selected' : ''; ?>>
                        <?php echo $nama; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2 col-sm-6">
            <label class="form-label-custom"><i class="bi bi-calendar-date me-1"></i>Tahun</label>
            <select class="form-select form-select-custom" id="filter-tahun">
                <?php for ($y = $current_year - 1; $y <= $current_year + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-md-2 col-sm-6">
            <label class="form-label-custom"><i class="bi bi-calendar-week me-1"></i>Minggu Ke-</label>
            <select class="form-select form-select-custom" id="filter-minggu">
                <option value="">Semua Minggu</option>
                <option value="1">Minggu 1 (Tgl 1 - 7)</option>
                <option value="2">Minggu 2 (Tgl 8 - 14)</option>
                <option value="3">Minggu 3 (Tgl 15 - 21)</option>
                <option value="4">Minggu 4 (Tgl 22 - 28)</option>
                <option value="5">Minggu 5 (Tgl 29 - 31)</option>
            </select>
        </div>

        <div class="col-md-3 col-sm-6">
            <label class="form-label-custom"><i class="bi bi-chat-dots me-1"></i>Metode Follow Up</label>
            <select class="form-select form-select-custom" id="filter-metode">
                <option value="">Semua Metode</option>
                <option value="Phone Call">Phone Call</option>
                <option value="Text Whatsapp">Text Whatsapp</option>
                <option value="Email">Email</option>
                <option value="Ketemu Langsung">Ketemu Langsung</option>
            </select>
        </div>

        <div class="col-md-3 col-sm-6">
            <label class="form-label-custom"><i class="bi bi-check2-circle me-1"></i>Status Verifikasi</label>
            <select class="form-select form-select-custom" id="filter-status">
                <option value="">Semua Status</option>
                <option value="1">Sudah Dilakukan (Verified)</option>
                <option value="0">Belum Dilakukan</option>
            </select>
        </div>

        <div class="col-md-6 col-sm-12">
            <label class="form-label-custom"><i class="bi bi-search me-1"></i>Cari Customer / Kontak / Aktivitas</label>
            <div class="input-group">
                <input type="text" class="form-control form-control-custom" id="filter-search" placeholder="Ketik nama toko, nomor HP, atau catatan...">
                <button class="btn btn-outline-secondary" type="button" id="btn-clear-search" title="Bersihkan Pencarian">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <div class="col-md-3 col-sm-12 d-flex align-items-end">
            <button type="button" class="btn btn-light w-100 fw-semibold text-secondary border" id="btn-reset-filters" style="border-radius:10px; font-size:13px; padding:7.5px;">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
            </button>
        </div>
    </div>
</div>

<!-- ================= DATA TABLE CARD ================= -->
<div class="table-card">
    <div class="table-header-box">
        <h2>
            <i class="bi bi-table text-primary"></i>
            Daftar Rencana Kerja
            <span class="badge bg-primary-subtle text-primary fw-bold" id="badge-total-records" style="font-size:11px; border-radius:10px;">0 Data</span>
        </h2>
        
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted" style="font-size:12px;">
                <?php if ($is_admin): ?>
                    <span class="text-success fw-bold"><i class="bi bi-shield-check"></i> Mode Admin:</span> Klik centang untuk memverifikasi realisasi.
                <?php else: ?>
                    <span class="text-info fw-bold"><i class="bi bi-info-circle"></i> Info:</span> Status verifikasi dicentang oleh Admin.
                <?php endif; ?>
            </span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table custom-table" id="workplan-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 40px;">No</th>
                    <th class="text-center" style="width: 140px;">Status Realisasi</th>
                    <th style="width: 100px;">Tanggal</th>
                    <?php if ($is_admin): ?>
                    <th style="width: 120px;">Sales</th>
                    <?php endif; ?>
                    <th>Nama Customer</th>
                    <th>Aktivitas yang Akan Dilakukan</th>
                    <th style="width: 130px;">Kontak Customer</th>
                    <th style="width: 130px;">Email</th>
                    <th style="width: 130px;">Metode Follow Up</th>
                    <th>Hasil Follow Up</th>
                    <th class="text-center" style="width: 90px;">Aksi</th>
                </tr>
            </thead>
            <tbody id="workplan-tbody">
                <tr>
                    <td colspan="<?php echo $is_admin ? '11' : '10'; ?>" class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <div>Memuat data rencana kerja...</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODAL SINGLE ADD / EDIT ================= -->
<div class="modal fade" id="planModal" tabindex="-1" aria-labelledby="planModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 18px; border:none; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
            <div class="modal-header border-bottom py-3 px-4 bg-light" style="border-radius: 18px 18px 0 0;">
                <h5 class="modal-title fw-bold text-dark" id="planModalTitle">
                    <i class="bi bi-calendar-plus text-primary me-2"></i>Tambah Rencana Kerja
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="planForm">
                <input type="hidden" id="plan_id" name="id" value="">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <?php if ($is_admin): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12.5px;">Sales Penanggung Jawab <span class="text-danger">*</span></label>
                            <select class="form-select form-select-custom" id="modal_sales_id" name="sales_id" required>
                                <?php foreach ($sales_list as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $user_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" id="modal_sales_id" name="sales_id" value="<?php echo $user_id; ?>">
                        <?php endif; ?>

                        <div class="col-md-<?php echo $is_admin ? '6' : '12'; ?>">
                            <label class="form-label fw-semibold" style="font-size:12.5px;">Tanggal Rencana <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="modal_tanggal" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold mb-0" style="font-size:12.5px;">
                                    <i class="bi bi-shop text-primary me-1"></i>Nama Customer / Toko <span class="text-danger">*</span>
                                </label>
                                <span class="badge bg-primary-subtle text-primary border" style="font-size:10px;">
                                    <i class="bi bi-database-check me-1"></i>Database Customer Terhubung
                                </span>
                            </div>
                            <select class="form-select form-select-custom" id="modal_select_customer" style="width: 100%;" required>
                                <option value="">-- Cari nama customer di database atau ketik baru --</option>
                            </select>
                            <input type="hidden" id="modal_nama_customer" name="nama_customer" value="">
                            <input type="hidden" id="modal_customer_id" name="customer_id" value="">
                            <small class="text-muted d-block mt-1" style="font-size:11px;">
                                <i class="bi bi-info-circle me-1"></i>Ketik nama toko untuk mencari dari database customer. Nomor HP/WA akan terisi otomatis. Anda juga dapat mengetik nama customer baru.
                            </small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12.5px;">Kontak Customer (No. HP / WA)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border"><i class="bi bi-whatsapp text-success"></i></span>
                                <input type="text" class="form-control form-control-custom" id="modal_kontak_customer" name="kontak_customer" placeholder="Contoh: 08123456789">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12.5px;">Email Customer</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border"><i class="bi bi-envelope text-primary"></i></span>
                                <input type="email" class="form-control form-control-custom" id="modal_email_customer" name="email_customer" placeholder="customer@example.com">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="font-size:12.5px;">Metode Follow Up <span class="text-danger">*</span></label>
                            <select class="form-select form-select-custom" id="modal_metode_fu" name="metode_fu" required>
                                <option value="Text Whatsapp">💬 Text Whatsapp</option>
                                <option value="Phone Call">📞 Phone Call</option>
                                <option value="Email">✉️ Email</option>
                                <option value="Ketemu Langsung">🤝 Ketemu Langsung (Kunjungan)</option>
                                <option value="Lainnya">📋 Lainnya</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="font-size:12.5px;">Aktivitas yang Akan Dilakukan <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-custom" id="modal_aktivitas" name="aktivitas" rows="3" placeholder="Contoh: Menawarkan paket CCTV 2MP AHD, info program cashback, follow up penawaran minggu lalu..." required></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="font-size:12.5px;">Hasil Follow Up (Catatan / Respon)</label>
                            <textarea class="form-control form-control-custom" id="modal_hasil_fu" name="hasil_fu" rows="2" placeholder="Tuliskan hasil follow up seperti apakah customer tertarik, sudah order, atau alasan belum tertarik..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top" style="border-radius: 0 0 18px 18px;">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal" style="border-radius:10px;">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="btn-save-plan" style="border-radius:10px; background:#2563EB;">
                        <i class="bi bi-check-circle-fill me-1"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL BATCH MULTI-ROW ADD ================= -->
<div class="modal fade" id="batchModal" tabindex="-1" aria-labelledby="batchModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 18px; border:none; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
            <div class="modal-header border-bottom py-3 px-4 bg-light" style="border-radius: 18px 18px 0 0;">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="batchModalTitle">
                        <i class="bi bi-table text-primary me-2"></i>Input Cepat Rencana Kerja (Multi-Baris)
                    </h5>
                    <p class="text-muted mb-0" style="font-size:12px;">Isi rencana kerja beberapa customer sekaligus persis seperti bekerja di spreadsheet.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php if ($is_admin): ?>
                <div class="mb-3 d-flex align-items-center gap-2">
                    <label class="form-label-custom mb-0"><i class="bi bi-person-badge"></i> Sales Penanggung Jawab:</label>
                    <select class="form-select form-select-custom" id="batch_sales_id" style="max-width: 250px;">
                        <?php foreach ($sales_list as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $user_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" id="batch_sales_id" value="<?php echo $user_id; ?>">
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table batch-table" id="batch-input-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th style="width: 140px;">Tanggal</th>
                                <th style="width: 200px;">Nama Customer *</th>
                                <th style="width: 140px;">Kontak HP/WA</th>
                                <th style="width: 150px;">Metode Follow Up</th>
                                <th>Aktivitas yang Akan Dilakukan</th>
                                <th>Hasil Follow Up</th>
                                <th style="width: 40px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="batch-tbody">
                            <!-- Rows injected by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btn-add-batch-row" style="border-radius:8px;">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Baris Baru
                    </button>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 border-top" style="border-radius: 0 0 18px 18px;">
                <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal" style="border-radius:10px;">Batal</button>
                <button type="button" class="btn btn-primary fw-bold px-4" id="btn-save-batch" style="border-radius:10px; background:#2563EB;">
                    <i class="bi bi-cloud-upload-fill me-1"></i> Simpan Semua Baris
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const CURRENT_USER_ID = <?php echo $user_id; ?>;
    const TODAY_STR = '<?php echo date('Y-m-d'); ?>';

    const planModal = new bootstrap.Modal(document.getElementById('planModal'));
    const batchModal = new bootstrap.Modal(document.getElementById('batchModal'));

    // -------------------------------------------------------------
    // 1. LOAD DATA & SUMMARY
    // -------------------------------------------------------------
    function getFilterParams() {
        return {
            sales_id: document.getElementById('filter-sales').value,
            bulan: document.getElementById('filter-bulan').value,
            tahun: document.getElementById('filter-tahun').value,
            minggu: document.getElementById('filter-minggu').value,
            metode_fu: document.getElementById('filter-metode').value,
            status_done: document.getElementById('filter-status').value,
            search: document.getElementById('filter-search').value
        };
    }

    function loadSummary() {
        const params = new URLSearchParams(getFilterParams());
        params.append('action', 'get_summary');

        fetch('ajax_sales_work_plan_handler.php?' + params.toString())
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const d = res.data;
                    document.getElementById('kpi-phone').innerText = d.total_phone;
                    document.getElementById('kpi-wa').innerText = d.total_wa;
                    document.getElementById('kpi-email').innerText = d.total_email;
                    document.getElementById('kpi-ketemu').innerText = d.total_ketemu;
                    document.getElementById('kpi-total').innerText = d.total_rencana;
                    document.getElementById('kpi-done').innerText = d.total_done + ' / ' + d.total_rencana;
                    document.getElementById('kpi-percentage').innerText = d.percentage + '% Selesai';
                }
            })
            .catch(err => console.error('Error load summary:', err));
    }

    function loadPlans() {
        const tbody = document.getElementById('workplan-tbody');
        tbody.innerHTML = `
            <tr>
                <td colspan="${IS_ADMIN ? 11 : 10}" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                    <div>Memuat data rencana kerja...</div>
                </td>
            </tr>
        `;

        const params = new URLSearchParams(getFilterParams());
        params.append('action', 'list_plans');

        fetch('ajax_sales_work_plan_handler.php?' + params.toString())
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    tbody.innerHTML = `<tr><td colspan="${IS_ADMIN ? 11 : 10}" class="text-center py-4 text-danger">${res.message || 'Gagal memuat data.'}</td></tr>`;
                    return;
                }

                document.getElementById('badge-total-records').innerText = (res.total || 0) + ' Data';

                if (!res.data || res.data.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${IS_ADMIN ? 11 : 10}" class="text-center py-5 text-muted">
                                <i class="bi bi-clipboard-x" style="font-size: 32px; color: #94A3B8;"></i>
                                <div class="mt-2 fw-semibold">Tidak ada data rencana kerja untuk periode/filter ini.</div>
                                <small>Klik tombol "Tambah Rencana" untuk membuat jadwal rencana kerja baru.</small>
                            </td>
                        </tr>
                    `;
                    return;
                }

                let html = '';
                res.data.forEach((row, idx) => {
                    const isDone = row.is_done === 1;
                    const rowClass = isDone ? 'row-done' : '';

                    // Method Badge
                    let methodBadge = '';
                    const mLower = (row.metode_fu || '').toLowerCase();
                    if (mLower.includes('phone') || mLower.includes('call') || mLower.includes('telepon')) {
                        methodBadge = `<span class="badge-method badge-method-phone"><i class="bi bi-telephone-fill"></i> ${row.metode_fu}</span>`;
                    } else if (mLower.includes('wa') || mLower.includes('what') || mLower.includes('text')) {
                        methodBadge = `<span class="badge-method badge-method-wa"><i class="bi bi-whatsapp"></i> ${row.metode_fu}</span>`;
                    } else if (mLower.includes('email') || mLower.includes('mail')) {
                        methodBadge = `<span class="badge-method badge-method-email"><i class="bi bi-envelope-fill"></i> ${row.metode_fu}</span>`;
                    } else if (mLower.includes('ketemu') || mLower.includes('visit') || mLower.includes('kunjungan') || mLower.includes('langsung')) {
                        methodBadge = `<span class="badge-method badge-method-ketemu"><i class="bi bi-people-fill"></i> ${row.metode_fu}</span>`;
                    } else {
                        methodBadge = `<span class="badge-method badge-method-other"><i class="bi bi-chat-left-dots-fill"></i> ${row.metode_fu || '-'}</span>`;
                    }

                    // Contact Links
                    let contactDisplay = '-';
                    if (row.kontak_customer) {
                        const cleanPhone = row.kontak_customer.replace(/[^0-9]/g, '');
                        let waNumber = cleanPhone;
                        if (waNumber.startsWith('0')) {
                            waNumber = '62' + waNumber.substring(1);
                        }
                        contactDisplay = `
                            <div class="d-flex align-items-center gap-1.5">
                                <span class="font-monospace fw-semibold" style="font-size:12.5px;">${row.kontak_customer}</span>
                                <a href="https://wa.me/${waNumber}" target="_blank" class="text-success ms-1" title="Chat via WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                                <a href="tel:${cleanPhone}" class="text-primary ms-1" title="Telepon">
                                    <i class="bi bi-telephone"></i>
                                </a>
                            </div>
                        `;
                    }

                    let emailDisplay = '-';
                    if (row.email_customer) {
                        emailDisplay = `<a href="mailto:${row.email_customer}" class="text-decoration-none text-dark" style="font-size:12px;">${row.email_customer}</a>`;
                    }

                    // Checkbox / Verification Status
                    let statusHtml = '';
                    if (IS_ADMIN) {
                        // Admin interactive toggle
                        const verifierTooltip = isDone && row.verifier_name 
                            ? `Diverifikasi oleh: ${row.verifier_name} (${row.verified_at ? row.verified_at.substring(0, 16) : ''})`
                            : 'Klik untuk memverifikasi bahwa rencana kerja sudah dilakukan';

                        statusHtml = `
                            <div class="status-switch-container" title="${verifierTooltip}">
                                <input class="form-check-input admin-check toggle-done-check" type="checkbox" data-id="${row.id}" ${isDone ? 'checked' : ''}>
                                <span class="${isDone ? 'badge-status-done' : 'badge-status-pending'}">
                                    ${isDone ? '<i class="bi bi-check2"></i> Selesai' : 'Belum'}
                                </span>
                            </div>
                        `;
                    } else {
                        // Sales read-only
                        statusHtml = `
                            <div class="status-switch-container" title="Status verifikasi oleh Admin">
                                <input class="form-check-input admin-check sales-disabled" type="checkbox" ${isDone ? 'checked' : ''} disabled>
                                <span class="${isDone ? 'badge-status-done' : 'badge-status-pending'}">
                                    ${isDone ? '<i class="bi bi-check2"></i> Selesai' : 'Belum'}
                                </span>
                            </div>
                        `;
                    }

                    // Action buttons
                    let actionHtml = '';
                    if (row.can_edit) {
                        actionHtml = `
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                <button type="button" class="btn-action-icon btn-edit-plan" data-id="${row.id}" title="Edit Rencana Kerja">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button type="button" class="btn-action-icon btn-delete btn-delete-plan" data-id="${row.id}" data-name="${escapeHtml(row.nama_customer)}" title="Hapus Rencana Kerja">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                        `;
                    } else {
                        actionHtml = `<span class="text-muted" style="font-size:11px;">-</span>`;
                    }

                    html += `
                        <tr class="${rowClass}" id="plan-row-${row.id}">
                            <td class="text-center fw-semibold text-secondary">${idx + 1}</td>
                            <td class="text-center">${statusHtml}</td>
                            <td class="fw-semibold text-nowrap"><i class="bi bi-calendar3 text-muted me-1"></i>${row.tgl_formatted}</td>
                            ${IS_ADMIN ? `<td class="fw-semibold text-primary text-nowrap">${escapeHtml(row.sales_name || '-')}</td>` : ''}
                            <td class="fw-bold text-dark">${escapeHtml(row.nama_customer)}</td>
                            <td style="max-width:260px;">${escapeHtml(row.aktivitas || '-')}</td>
                            <td>${contactDisplay}</td>
                            <td>${emailDisplay}</td>
                            <td>${methodBadge}</td>
                            <td style="max-width:220px;" class="text-muted fst-italic">${escapeHtml(row.hasil_fu || '-')}</td>
                            <td class="text-center">${actionHtml}</td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;
                attachTableEvents();
            })
            .catch(err => {
                console.error('Error load plans:', err);
                tbody.innerHTML = `<tr><td colspan="${IS_ADMIN ? 11 : 10}" class="text-center py-4 text-danger">Terjadi kesalahan koneksi saat memuat data.</td></tr>`;
            });
    }

    function refreshAll() {
        loadSummary();
        loadPlans();
    }

    // -------------------------------------------------------------
    // 2. TABLE EVENTS (Toggle Done, Edit, Delete)
    // -------------------------------------------------------------
    function attachTableEvents() {
        // Toggle Done (Admin Only)
        if (IS_ADMIN) {
            document.querySelectorAll('.toggle-done-check').forEach(chk => {
                chk.addEventListener('change', function() {
                    const id = this.getAttribute('data-id');
                    const isDone = this.checked ? 1 : 0;
                    const rowElem = document.getElementById('plan-row-' + id);

                    const formData = new FormData();
                    formData.append('action', 'toggle_done');
                    formData.append('id', id);
                    formData.append('is_done', isDone);

                    fetch('ajax_sales_work_plan_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            if (isDone) {
                                rowElem.classList.add('row-done');
                            } else {
                                rowElem.classList.remove('row-done');
                            }
                            loadSummary(); // Refresh KPI counter
                        } else {
                            chk.checked = !chk.checked; // Revert
                            Swal.fire('Gagal', res.message || 'Tidak dapat memperbarui status.', 'error');
                        }
                    })
                    .catch(err => {
                        chk.checked = !chk.checked;
                        Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                    });
                });
            });
        }

        // Edit Plan
        document.querySelectorAll('.btn-edit-plan').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                fetch(`ajax_sales_work_plan_handler.php?action=get_plan&id=${id}`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            const d = res.data;
                            document.getElementById('plan_id').value = d.id;
                            if (document.getElementById('modal_sales_id')) {
                                document.getElementById('modal_sales_id').value = d.sales_id;
                            }
                            document.getElementById('modal_tanggal').value = d.tanggal;
                            document.getElementById('modal_nama_customer').value = d.nama_customer;
                            document.getElementById('modal_customer_id').value = d.customer_id || '';
                            document.getElementById('modal_kontak_customer').value = d.kontak_customer || '';
                            document.getElementById('modal_email_customer').value = d.email_customer || '';
                            document.getElementById('modal_metode_fu').value = d.metode_fu || 'Text Whatsapp';
                            document.getElementById('modal_aktivitas').value = d.aktivitas || '';
                            document.getElementById('modal_hasil_fu').value = d.hasil_fu || '';

                            // Set Select2 option for editing
                            if (typeof $ !== 'undefined' && $('#modal_select_customer').data('select2')) {
                                const newOption = new Option(d.nama_customer, d.customer_id || d.nama_customer, true, true);
                                $('#modal_select_customer').empty().append(newOption).trigger('change');
                            }

                            document.getElementById('planModalTitle').innerHTML = '<i class="bi bi-pencil-square text-primary me-2"></i>Edit Rencana Kerja';
                            planModal.show();
                        } else {
                            Swal.fire('Gagal', res.message || 'Data tidak ditemukan.', 'error');
                        }
                    })
                    .catch(err => Swal.fire('Error', 'Gagal mengambil data.', 'error'));
            });
        });

        // Delete Plan
        document.querySelectorAll('.btn-delete-plan').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');

                Swal.fire({
                    title: 'Hapus Rencana Kerja?',
                    text: `Apakah Anda yakin ingin menghapus rencana kerja untuk "${name}"?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#EF4444',
                    cancelButtonColor: '#64748B',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const fd = new FormData();
                        fd.append('action', 'delete_plan');
                        fd.append('id', id);

                        fetch('ajax_sales_work_plan_handler.php', {
                            method: 'POST',
                            body: fd
                        })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Terhapus!',
                                    text: res.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                refreshAll();
                            } else {
                                Swal.fire('Gagal', res.message || 'Gagal menghapus.', 'error');
                            }
                        })
                        .catch(err => Swal.fire('Error', 'Terjadi kesalahan server.', 'error'));
                    }
                });
            });
        });
    }

    // -------------------------------------------------------------
    // 3. FILTER EVENT LISTENERS
    // -------------------------------------------------------------
    ['filter-sales', 'filter-bulan', 'filter-tahun', 'filter-minggu', 'filter-metode', 'filter-status'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', refreshAll);
        }
    });

    let searchTimer = null;
    document.getElementById('filter-search').addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(refreshAll, 350);
    });

    document.getElementById('btn-clear-search').addEventListener('click', function() {
        document.getElementById('filter-search').value = '';
        refreshAll();
    });

    document.getElementById('btn-reset-filters').addEventListener('click', function() {
        if (document.getElementById('filter-sales') && IS_ADMIN) {
            document.getElementById('filter-sales').value = '';
        }
        document.getElementById('filter-bulan').value = '<?php echo $current_month; ?>';
        document.getElementById('filter-tahun').value = '<?php echo $current_year; ?>';
        document.getElementById('filter-minggu').value = '';
        document.getElementById('filter-metode').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-search').value = '';
        refreshAll();
    });

    // Export Excel Button
    document.getElementById('btn-export-excel').addEventListener('click', function() {
        const params = new URLSearchParams(getFilterParams());
        window.location.href = 'export_sales_work_plan_excel.php?' + params.toString();
    });

    // -------------------------------------------------------------
    // 4. SELECT2 CUSTOMER INITIALIZATION
    // -------------------------------------------------------------
    function initCustomerSelect2() {
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('#modal_select_customer').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#planModal'),
                placeholder: '🔍 Cari & pilih customer dari database (atau ketik baru)...',
                allowClear: true,
                tags: true,
                ajax: {
                    url: 'ajax_sales_work_plan_handler.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        const selectedSalesId = document.getElementById('modal_sales_id') ? document.getElementById('modal_sales_id').value : '';
                        return {
                            action: 'search_customer',
                            sales_id: selectedSalesId,
                            q: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results || [],
                            pagination: {
                                more: data.pagination ? data.pagination.more : false
                            }
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0,
                templateResult: function(item) {
                    if (item.loading) return item.text;
                    if (!item.nama_toko) {
                        return $(`<div><span class="badge bg-secondary me-1">Custom Baru</span> <strong>${escapeHtml(item.text)}</strong></div>`);
                    }
                    const kotaBadge = item.kota ? `<span class="badge bg-light text-secondary border me-1">${escapeHtml(item.kota)}</span>` : '';
                    const phoneBadge = item.phone ? `<span class="badge bg-success-subtle text-success border"><i class="bi bi-telephone"></i> ${escapeHtml(item.phone)}</span>` : '';
                    return $(`
                        <div class="py-1">
                            <div class="fw-bold text-dark">${escapeHtml(item.nama_toko)}</div>
                            <div class="d-flex align-items-center gap-1 mt-1" style="font-size:11px;">
                                ${kotaBadge}
                                ${phoneBadge}
                                ${item.pic ? `<span class="text-muted ms-1">PIC: ${escapeHtml(item.pic)}</span>` : ''}
                            </div>
                        </div>
                    `);
                },
                templateSelection: function(item) {
                    return item.nama_toko || item.text || 'Pilih Customer...';
                }
            }).on('select2:select', function(e) {
                const data = e.params.data;
                if (data.id && !isNaN(data.id)) {
                    document.getElementById('modal_customer_id').value = data.id;
                    document.getElementById('modal_nama_customer').value = data.nama_toko || data.text;
                    if (data.phone) {
                        document.getElementById('modal_kontak_customer').value = data.phone;
                    }
                } else {
                    document.getElementById('modal_customer_id').value = '';
                    document.getElementById('modal_nama_customer').value = data.text;
                }
            }).on('select2:clear', function(e) {
                document.getElementById('modal_customer_id').value = '';
                document.getElementById('modal_nama_customer').value = '';
            });
        }
    }

    // Modal open add
    document.getElementById('btn-open-add').addEventListener('click', function() {
        document.getElementById('planForm').reset();
        document.getElementById('plan_id').value = '';
        document.getElementById('modal_customer_id').value = '';
        document.getElementById('modal_nama_customer').value = '';
        document.getElementById('modal_tanggal').value = TODAY_STR;
        document.getElementById('modal_metode_fu').value = 'Text Whatsapp';
        
        if (typeof $ !== 'undefined' && $('#modal_select_customer').data('select2')) {
            $('#modal_select_customer').val(null).trigger('change');
        }

        document.getElementById('planModalTitle').innerHTML = '<i class="bi bi-calendar-plus text-primary me-2"></i>Tambah Rencana Kerja';
        planModal.show();
    });

    // Reset customer selection when admin changes sales in modal
    if (document.getElementById('modal_sales_id') && IS_ADMIN) {
        document.getElementById('modal_sales_id').addEventListener('change', function() {
            if (typeof $ !== 'undefined' && $('#modal_select_customer').data('select2')) {
                $('#modal_select_customer').val(null).trigger('change');
            }
        });
    }

    // Ensure Select2 is initialized when modal is shown
    document.getElementById('planModal').addEventListener('shown.bs.modal', function () {
        initCustomerSelect2();
    });

    document.getElementById('planForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Ensure nama_customer is filled from Select2 if not yet set
        if (!document.getElementById('modal_nama_customer').value) {
            const selVal = $('#modal_select_customer').val();
            const selText = $('#modal_select_customer').find(':selected').text();
            if (selVal) {
                document.getElementById('modal_nama_customer').value = selText.split(' (')[0].trim() || selVal;
            }
        }

        if (!document.getElementById('modal_nama_customer').value.trim()) {
            Swal.fire('Perhatian', 'Nama Customer / Toko wajib dipilih atau diisi.', 'warning');
            return;
        }

        const planId = document.getElementById('plan_id').value;
        const action = planId ? 'update_plan' : 'add_plan';

        const formData = new FormData(this);
        formData.append('action', action);

        const btnSave = document.getElementById('btn-save-plan');
        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

        fetch('ajax_sales_work_plan_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Simpan Data';

            if (res.success) {
                planModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: res.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                refreshAll();
            } else {
                Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
            }
        })
        .catch(err => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Simpan Data';
            Swal.fire('Error', 'Terjadi kesalahan koneksi.', 'error');
        });
    });

    // -------------------------------------------------------------
    // 5. BATCH MULTI-ROW ADD MODAL
    // -------------------------------------------------------------
    function createBatchRow(idx) {
        return `
            <tr class="batch-row" id="batch-row-${idx}">
                <td class="text-center fw-semibold text-muted align-middle">${idx}</td>
                <td><input type="date" class="b-tanggal" value="${TODAY_STR}"></td>
                <td>
                    <input type="text" class="b-nama" list="customer-names-datalist" placeholder="Nama Toko (Ketik untuk cari)...">
                </td>
                <td><input type="text" class="b-kontak" placeholder="08xx..."></td>
                <td>
                    <select class="b-metode">
                        <option value="Text Whatsapp">Text Whatsapp</option>
                        <option value="Phone Call">Phone Call</option>
                        <option value="Email">Email</option>
                        <option value="Ketemu Langsung">Ketemu Langsung</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </td>
                <td><textarea class="b-aktivitas" rows="1" placeholder="Aktivitas yang akan dilakukan..."></textarea></td>
                <td><textarea class="b-hasil" rows="1" placeholder="Hasil follow up..."></textarea></td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm text-danger p-0 btn-remove-batch" title="Hapus Baris">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    let batchRowIndex = 1;
    function initBatchRows() {
        const tbody = document.getElementById('batch-tbody');
        tbody.innerHTML = '';
        batchRowIndex = 1;
        for (let i = 1; i <= 5; i++) {
            tbody.insertAdjacentHTML('beforeend', createBatchRow(batchRowIndex++));
        }
        attachBatchRowEvents();
    }

    // Populate Datalist for Batch Rows from Customer Database (Filtered by Sales)
    function loadCustomerDatalist() {
        const targetSales = IS_ADMIN && document.getElementById('batch_sales_id') ? document.getElementById('batch_sales_id').value : CURRENT_USER_ID;
        fetch(`ajax_sales_work_plan_handler.php?action=search_customer&sales_id=${targetSales}&limit=100`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.results) {
                    let dlist = document.getElementById('customer-names-datalist');
                    if (!dlist) {
                        dlist = document.createElement('datalist');
                        dlist.id = 'customer-names-datalist';
                        document.body.appendChild(dlist);
                    }
                    let opts = '';
                    res.results.forEach(c => {
                        opts += `<option value="${escapeHtml(c.nama_toko)}" data-phone="${escapeHtml(c.phone || '')}">${c.kota ? '(' + escapeHtml(c.kota) + ')' : ''}</option>`;
                    });
                    dlist.innerHTML = opts;
                }
            })
            .catch(e => console.warn(e));
    }
    loadCustomerDatalist();

    if (document.getElementById('batch_sales_id') && IS_ADMIN) {
        document.getElementById('batch_sales_id').addEventListener('change', loadCustomerDatalist);
    }

    document.getElementById('btn-open-batch-add').addEventListener('click', function() {
        initBatchRows();
        batchModal.show();
    });

    document.getElementById('btn-add-batch-row').addEventListener('click', function() {
        document.getElementById('batch-tbody').insertAdjacentHTML('beforeend', createBatchRow(batchRowIndex++));
        attachBatchRowEvents();
    });

    function attachBatchRowEvents() {
        document.querySelectorAll('.btn-remove-batch').forEach(btn => {
            btn.onclick = function() {
                const tr = this.closest('tr');
                if (document.querySelectorAll('.batch-row').length > 1) {
                    tr.remove();
                } else {
                    tr.querySelector('.b-nama').value = '';
                    tr.querySelector('.b-kontak').value = '';
                    tr.querySelector('.b-aktivitas').value = '';
                    tr.querySelector('.b-hasil').value = '';
                }
            };
        });

        // Auto-fill phone if selected from datalist in batch row
        document.querySelectorAll('.b-nama').forEach(input => {
            input.onchange = function() {
                const val = this.value;
                const dlist = document.getElementById('customer-names-datalist');
                if (dlist) {
                    const opt = dlist.querySelector(`option[value="${val}"]`);
                    if (opt && opt.getAttribute('data-phone')) {
                        const tr = this.closest('tr');
                        const phoneInput = tr.querySelector('.b-kontak');
                        if (!phoneInput.value) {
                            phoneInput.value = opt.getAttribute('data-phone');
                        }
                    }
                }
            };
        });
    }

    document.getElementById('btn-save-batch').addEventListener('click', function() {
        const rows = [];
        document.querySelectorAll('.batch-row').forEach(tr => {
            const nama = tr.querySelector('.b-nama').value.trim();
            if (nama) {
                rows.push({
                    tanggal: tr.querySelector('.b-tanggal').value,
                    nama_customer: nama,
                    kontak_customer: tr.querySelector('.b-kontak').value.trim(),
                    metode_fu: tr.querySelector('.b-metode').value,
                    aktivitas: tr.querySelector('.b-aktivitas').value.trim(),
                    hasil_fu: tr.querySelector('.b-hasil').value.trim()
                });
            }
        });

        if (rows.length === 0) {
            Swal.fire('Perhatian', 'Harap isi minimal 1 nama customer pada tabel.', 'warning');
            return;
        }

        const salesId = document.getElementById('batch_sales_id').value;
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

        const fd = new FormData();
        fd.append('action', 'batch_add_plan');
        fd.append('sales_id', salesId);
        fd.append('rows', JSON.stringify(rows));

        fetch('ajax_sales_work_plan_handler.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-upload-fill me-1"></i> Simpan Semua Baris';

            if (res.success) {
                batchModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: res.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                refreshAll();
            } else {
                Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-upload-fill me-1"></i> Simpan Semua Baris';
            Swal.fire('Error', 'Terjadi kesalahan koneksi.', 'error');
        });
    });

    // Helper HTML Escape
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Initial Load
    refreshAll();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
