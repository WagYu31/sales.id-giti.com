<?php
$page_title = 'Customer Management';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get live stats
$totalCustomers = 0; $pendingFU = 0; $thisMonthNew = 0;
$r1 = $conn->query("SELECT COUNT(*) as t FROM customers WHERE deleted_at IS NULL");
if ($r1) $totalCustomers = $r1->fetch_assoc()['t'] ?? 0;
$r2 = $conn->query("SELECT COUNT(*) as t FROM customers WHERE status_fu = 'Pending' AND deleted_at IS NULL");
if ($r2) $pendingFU = $r2->fetch_assoc()['t'] ?? 0;
$r3 = $conn->query("SELECT COUNT(*) as t FROM customers WHERE MONTH(created_at)=MONTH(CURRENT_DATE()) AND YEAR(created_at)=YEAR(CURRENT_DATE()) AND deleted_at IS NULL");
if ($r3) $thisMonthNew = $r3->fetch_assoc()['t'] ?? 0;

$firstName = explode(' ', $_SESSION['nama_lengkap'] ?? 'User')[0];
?>

<style>
/* ============ WELCOME ============ */
.welcome-section { margin-bottom: 28px; }
.welcome-title {
    font-size: 28px; font-weight: 800; color: #0F172A;
    letter-spacing: -0.5px; margin-bottom: 6px;
}
.welcome-sub {
    font-size: 14px; color: #64748B; font-weight: 400; line-height: 1.5;
}

/* ============ STAT CARDS ============ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}

.stat-card {
    border-radius: 16px;
    padding: 24px 28px;
    position: relative;
    overflow: hidden;
    min-height: 120px;
}

.stat-card::after {
    content: '';
    position: absolute;
    right: -10px; bottom: -10px;
    width: 80px; height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
}

.stat-card.blue {
    background: linear-gradient(135deg, #1E3A5F 0%, #1E40AF 100%);
}
.stat-card.teal {
    background: linear-gradient(135deg, #134E4A 0%, #0D9488 100%);
}
.stat-card.navy {
    background: linear-gradient(135deg, #1E293B 0%, #334155 100%);
}

.stat-value {
    font-size: 36px; font-weight: 800; color: #FFFFFF;
    letter-spacing: -1px; line-height: 1;
}

.stat-label {
    font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.5);
    text-transform: uppercase; letter-spacing: 1px; margin-top: 8px;
}

.stat-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 700; padding: 3px 8px;
    border-radius: 6px; margin-top: 10px;
}
.stat-badge.up { background: rgba(52,211,153,0.15); color: #34D399; }
.stat-badge.down { background: rgba(248,113,113,0.15); color: #F87171; }

.stat-icon {
    position: absolute;
    top: 20px; right: 24px;
    width: 40px; height: 40px;
    border-radius: 10px;
    background: rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.6);
    font-size: 18px;
}

/* ============ SECTION HEADER ============ */
.section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 18px;
}
.section-title { font-size: 17px; font-weight: 700; color: #1E293B; }
.section-subtitle { font-size: 13px; color: #94A3B8; font-weight: 500; margin-top: 2px; }

/* ============ MENU CARDS ============ */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.mc-link { text-decoration: none; color: inherit; display: block; }

.mc {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 22px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
}

.mc:hover {
    border-color: transparent;
    box-shadow: 0 8px 25px -5px rgba(0,0,0,0.08);
    transform: translateY(-3px);
}

.mc-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 14px; flex-shrink: 0;
}

.mc-title {
    font-size: 14px; font-weight: 700; color: #1E293B;
    margin-bottom: 6px; letter-spacing: -0.2px;
}

.mc-desc {
    font-size: 12.5px; color: #94A3B8; line-height: 1.5;
    font-weight: 400; flex-grow: 1;
}

.mc-arrow {
    display: flex; align-items: center; gap: 5px;
    margin-top: 14px;
    font-size: 12px; font-weight: 600;
    color: #3B82F6;
    transition: gap 0.2s ease;
}

.mc:hover .mc-arrow { gap: 9px; }

/* Icon colors */
.i-red    { background: #FEF2F2; color: #DC2626; }
.i-blue   { background: #EFF6FF; color: #2563EB; }
.i-cyan   { background: #ECFEFF; color: #0891B2; }
.i-sky    { background: #F0F9FF; color: #0284C7; }
.i-green  { background: #F0FDF4; color: #16A34A; }
.i-amber  { background: #FFFBEB; color: #D97706; }
.i-violet { background: #F5F3FF; color: #7C3AED; }
.i-slate  { background: #F1F5F9; color: #334155; }

/* Badge */
.mc-badge {
    display: inline-block;
    font-size: 10px; font-weight: 700;
    padding: 2px 7px; border-radius: 5px;
    background: #DBEAFE; color: #1D4ED8;
    margin-left: 6px; vertical-align: middle;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 1200px) { .menu-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 991px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .menu-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 576px) {
    .stats-grid, .menu-grid { grid-template-columns: 1fr; }
    .welcome-title { font-size: 22px; }
    .stat-value { font-size: 28px; }
}
</style>

<!-- WELCOME -->
<div class="welcome-section">
    <h1 class="welcome-title">Selamat Datang, <?php echo htmlspecialchars($firstName); ?> 👋</h1>
    <p class="welcome-sub">Kelola seluruh data customer, prospek, dan laporan tim sales Anda dalam satu dashboard terpusat.</p>
</div>

<!-- STATS -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
        <div class="stat-value"><?php echo number_format($totalCustomers); ?></div>
        <div class="stat-label">Total Customer</div>
        <div class="stat-badge up"><i class="bi bi-arrow-up-short"></i> 12%</div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
        <div class="stat-value"><?php echo number_format($pendingFU); ?></div>
        <div class="stat-label">Pending Follow Up</div>
        <div class="stat-badge down"><i class="bi bi-arrow-down-short"></i> 2</div>
    </div>
    <div class="stat-card navy">
        <div class="stat-icon"><i class="bi bi-person-plus-fill"></i></div>
        <div class="stat-value"><?php echo number_format($thisMonthNew); ?></div>
        <div class="stat-label">Customer Baru</div>
        <div class="stat-badge up"><i class="bi bi-arrow-up-short"></i> 8%</div>
    </div>
</div>

<!-- QUICK ACCESS -->
<div class="section-header">
    <div>
        <div class="section-title">Akses Cepat</div>
        <div class="section-subtitle">Pilih menu untuk mengelola data customer</div>
    </div>
</div>

<div class="menu-grid">
    <a href="index.php" class="mc-link" target="_blank">
        <div class="mc">
            <div class="mc-icon i-red"><i class="bi bi-people-fill"></i></div>
            <div class="mc-title">Daftar Customer</div>
            <div class="mc-desc">Daftar customer dan detail informasi lengkap.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="kandidat_report_view.php" class="mc-link" target="_blank">
        <div class="mc">
            <div class="mc-icon i-blue"><i class="bi bi-person-check-fill"></i></div>
            <div class="mc-title">Potensial Customer</div>
            <div class="mc-desc">Potensial customer dengan peluang konversi tinggi.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="customer_add.php" class="mc-link" target="_blank">
        <div class="mc">
            <div class="mc-icon i-cyan"><i class="bi bi-person-plus-fill"></i></div>
            <div class="mc-title">Tambah Customer</div>
            <div class="mc-desc">Tambah customer baru ke dalam sistem.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="<?php echo ($userRole == 'superadmin') ? 'customer_io.php' : 'customer_io_sales.php'; ?>" class="mc-link" target="_blank">
        <div class="mc">
            <div class="mc-icon i-sky"><i class="bi bi-cloud-upload-fill"></i></div>
            <div class="mc-title">Unggah Data Customer</div>
            <div class="mc-desc">Unggah data customer dari file Excel (XLSX).</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="customer_export.php" class="mc-link" target="_blank">
        <div class="mc">
            <div class="mc-icon i-green"><i class="bi bi-cloud-download-fill"></i></div>
            <div class="mc-title">Unduh Data Customer</div>
            <div class="mc-desc">Unduh data customer ke format file Excel.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="customer_maintenance.php" class="mc-link" target="_blank">
        <div class="mc">
            <div class="mc-icon i-amber"><i class="bi bi-clipboard-check-fill"></i></div>
            <div class="mc-title">Kualitas Data Customer</div>
            <div class="mc-desc">Kualitas data customer dan validasi format.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="invoice_followup_report.php" class="mc-link" target="_blank">
        <div class="mc">
            <div class="mc-icon i-violet"><i class="bi bi-receipt"></i></div>
            <div class="mc-title">Laporan Invoice FU</div>
            <div class="mc-desc">Laporan Invoice FU dan riwayat follow up.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="sales_assistant.html" target="_blank" class="mc-link">
        <div class="mc">
            <div class="mc-icon i-slate"><i class="bi bi-robot"></i></div>
            <div class="mc-title">Asisten Loewix <span class="mc-badge">Beta</span></div>
            <div class="mc-desc">Asisten Loewix AI untuk menjawab pertanyaan customer.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
</div>

<?php require_once 'includes/footer.php'; ?>