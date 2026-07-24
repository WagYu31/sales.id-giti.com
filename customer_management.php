<?php
$page_title = 'Customer Management';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get stats for dashboard
$totalCustomers = 0;
$pendingFU = 0;
$thisMonthNew = 0;

$res1 = $conn->query("SELECT COUNT(*) as total FROM customers WHERE deleted_at IS NULL");
if ($res1) { $totalCustomers = $res1->fetch_assoc()['total'] ?? 0; }

$res2 = $conn->query("SELECT COUNT(*) as total FROM customers WHERE status_fu = 'Pending' AND deleted_at IS NULL");
if ($res2) { $pendingFU = $res2->fetch_assoc()['total'] ?? 0; }

$res3 = $conn->query("SELECT COUNT(*) as total FROM customers WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND deleted_at IS NULL");
if ($res3) { $thisMonthNew = $res3->fetch_assoc()['total'] ?? 0; }
?>

<style>
/* ============ HERO SECTION ============ */
.hero-section {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #1E40AF 100%);
    border-radius: 20px;
    padding: 40px 48px;
    margin-bottom: 32px;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        radial-gradient(circle at 80% 20%, rgba(59,130,246,0.15) 0%, transparent 50%),
        radial-gradient(circle at 20% 80%, rgba(99,102,241,0.1) 0%, transparent 50%);
}

/* Grid pattern overlay */
.hero-section::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 100px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
    color: #93C5FD;
    letter-spacing: 0.3px;
    margin-bottom: 16px;
}

.hero-badge .dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #34D399;
    animation: pulse-dot 2s ease infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}

.hero-title {
    font-size: 32px;
    font-weight: 800;
    color: #FFFFFF;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
    line-height: 1.2;
}

.hero-desc {
    font-size: 15px;
    color: rgba(255,255,255,0.6);
    font-weight: 400;
    max-width: 520px;
    line-height: 1.6;
}

/* ============ STAT CARDS ============ */
.stats-row {
    display: flex;
    gap: 16px;
    margin-top: 28px;
}

.stat-card {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 18px 24px;
    min-width: 160px;
}

.stat-card .stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -0.5px;
    line-height: 1;
}

.stat-card .stat-label {
    font-size: 12px;
    font-weight: 600;
    color: rgba(255,255,255,0.45);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-top: 6px;
}

/* ============ SECTION TITLE ============ */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1E293B;
    letter-spacing: -0.3px;
}

.section-subtitle {
    font-size: 13px;
    color: #94A3B8;
    font-weight: 500;
}

/* ============ MENU CARDS ============ */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.menu-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.menu-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 28px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.menu-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 3px;
    background: linear-gradient(90deg, var(--card-accent), transparent);
    opacity: 0;
    transition: opacity 0.25s ease;
}

.menu-card:hover {
    border-color: transparent;
    box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08), 0 0 0 1px rgba(30,64,175,0.06);
    transform: translateY(-4px);
}

.menu-card:hover::before {
    opacity: 1;
}

.menu-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 18px;
    flex-shrink: 0;
}

.menu-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 6px;
    letter-spacing: -0.2px;
}

.menu-card-desc {
    font-size: 13px;
    color: #94A3B8;
    line-height: 1.5;
    font-weight: 400;
    flex-grow: 1;
}

.menu-card-arrow {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 16px;
    font-size: 12px;
    font-weight: 600;
    color: #94A3B8;
    transition: all 0.2s ease;
}

.menu-card:hover .menu-card-arrow {
    color: #1E40AF;
    gap: 10px;
}

/* Card color variants */
.icon-red    { background: #FEF2F2; color: #DC2626; }
.icon-blue   { background: #EFF6FF; color: #2563EB; }
.icon-gray   { background: #F1F5F9; color: #475569; }
.icon-sky    { background: #F0F9FF; color: #0284C7; }
.icon-green  { background: #F0FDF4; color: #16A34A; }
.icon-amber  { background: #FFFBEB; color: #D97706; }
.icon-indigo { background: #EEF2FF; color: #4F46E5; }
.icon-slate  { background: #F1F5F9; color: #334155; }

/* ============ RESPONSIVE ============ */
@media (max-width: 991px) {
    .menu-grid { grid-template-columns: repeat(2, 1fr); }
    .hero-section { padding: 32px; }
    .hero-title { font-size: 26px; }
    .stats-row { flex-wrap: wrap; }
}

@media (max-width: 576px) {
    .menu-grid { grid-template-columns: 1fr; }
    .hero-section { padding: 24px; border-radius: 16px; }
    .hero-title { font-size: 22px; }
    .stat-card { min-width: auto; flex: 1; }
}
</style>

<!-- ===== HERO SECTION ===== -->
<div class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">
            <span class="dot"></span>
            Sistem Aktif
        </div>
        <h1 class="hero-title">Pusat Manajemen Customer</h1>
        <p class="hero-desc">Kelola seluruh data customer, prospek, dan laporan tim sales Anda dalam satu dashboard terpusat.</p>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($totalCustomers); ?></div>
                <div class="stat-label">Total Customer</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($pendingFU); ?></div>
                <div class="stat-label">Pending Follow Up</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($thisMonthNew); ?></div>
                <div class="stat-label">Customer Baru</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== SECTION: MENU CARDS ===== -->
<div class="section-header">
    <div>
        <div class="section-title">Akses Cepat</div>
        <div class="section-subtitle">Pilih menu untuk mengelola data customer</div>
    </div>
</div>

<div class="menu-grid">

    <!-- 1. Daftar Customer -->
    <a href="index.php" class="menu-card-link" target="_blank">
        <div class="menu-card" style="--card-accent: #DC2626;">
            <div class="menu-card-icon icon-red">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="menu-card-title">Daftar Customer</div>
            <div class="menu-card-desc">Lihat detail seluruh data customer yang terdaftar di sistem.</div>
            <div class="menu-card-arrow">
                Buka <i class="bi bi-arrow-right"></i>
            </div>
        </div>
    </a>

    <!-- 2. Potensial Customer -->
    <a href="kandidat_report_view.php" class="menu-card-link" target="_blank">
        <div class="menu-card" style="--card-accent: #2563EB;">
            <div class="menu-card-icon icon-blue">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <div class="menu-card-title">Potensial Customer</div>
            <div class="menu-card-desc">Lihat daftar customer yang memiliki potensi tinggi untuk konversi.</div>
            <div class="menu-card-arrow">
                Buka <i class="bi bi-arrow-right"></i>
            </div>
        </div>
    </a>

    <!-- 3. Tambah Customer -->
    <a href="customer_add.php" class="menu-card-link" target="_blank">
        <div class="menu-card" style="--card-accent: #475569;">
            <div class="menu-card-icon icon-gray">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <div class="menu-card-title">Tambah Customer</div>
            <div class="menu-card-desc">Input data customer baru dengan lengkap ke dalam sistem.</div>
            <div class="menu-card-arrow">
                Buka <i class="bi bi-arrow-right"></i>
            </div>
        </div>
    </a>

    <!-- 4. Unggah Data Customer -->
    <a href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin') ? 'customer_io.php' : 'customer_io_sales.php'; ?>" class="menu-card-link" target="_blank">
        <div class="menu-card" style="--card-accent: #0284C7;">
            <div class="menu-card-icon icon-sky">
                <i class="bi bi-cloud-upload-fill"></i>
            </div>
            <div class="menu-card-title">Unggah Data Customer</div>
            <div class="menu-card-desc">Impor data customer dalam jumlah besar dari file Excel (XLSX).</div>
            <div class="menu-card-arrow">
                Buka <i class="bi bi-arrow-right"></i>
            </div>
        </div>
    </a>

    <!-- 5. Unduh Data Customer -->
    <a href="customer_export.php" class="menu-card-link" target="_blank">
        <div class="menu-card" style="--card-accent: #16A34A;">
            <div class="menu-card-icon icon-green">
                <i class="bi bi-cloud-download-fill"></i>
            </div>
            <div class="menu-card-title">Unduh Data Customer</div>
            <div class="menu-card-desc">Ekspor semua data customer ke dalam format file Excel.</div>
            <div class="menu-card-arrow">
                Buka <i class="bi bi-arrow-right"></i>
            </div>
        </div>
    </a>

    <!-- 6. Kualitas Data Customer -->
    <a href="customer_maintenance.php" class="menu-card-link" target="_blank">
        <div class="menu-card" style="--card-accent: #D97706;">
            <div class="menu-card-icon icon-amber">
                <i class="bi bi-clipboard-check-fill"></i>
            </div>
            <div class="menu-card-title">Kualitas Data Customer</div>
            <div class="menu-card-desc">Periksa dan perbaiki data yang duplikat atau salah format.</div>
            <div class="menu-card-arrow">
                Buka <i class="bi bi-arrow-right"></i>
            </div>
        </div>
    </a>

    <!-- 7. Laporan Invoice FU -->
    <a href="invoice_followup_report.php" class="menu-card-link" target="_blank">
        <div class="menu-card" style="--card-accent: #4F46E5;">
            <div class="menu-card-icon icon-indigo">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="menu-card-title">Laporan Invoice FU</div>
            <div class="menu-card-desc">Lihat riwayat follow up yang menghasilkan invoice dan perlu tindak lanjut.</div>
            <div class="menu-card-arrow">
                Buka <i class="bi bi-arrow-right"></i>
            </div>
        </div>
    </a>

    <!-- 8. Asisten Loewix -->
    <a href="sales_assistant.html" target="_blank" class="menu-card-link">
        <div class="menu-card" style="--card-accent: #334155;">
            <div class="menu-card-icon icon-slate">
                <i class="bi bi-robot"></i>
            </div>
            <div class="menu-card-title">Asisten Loewix <span style="font-size:11px;font-weight:600;color:#94A3B8;background:#F1F5F9;padding:2px 8px;border-radius:6px;margin-left:6px;">Beta</span></div>
            <div class="menu-card-desc">AI assistant untuk membantu menjawab pertanyaan customer secara cepat.</div>
            <div class="menu-card-arrow">
                Buka <i class="bi bi-arrow-right"></i>
            </div>
        </div>
    </a>

</div>

<?php
require_once 'includes/footer.php';
?>