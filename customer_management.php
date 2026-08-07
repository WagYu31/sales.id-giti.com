<?php
$page_title = 'Dashboard Sales';
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
/* ============ 3D SPATIAL & MICRO-INTERACTIONS ============ */
@keyframes fadeInUp3D {
    from { opacity: 0; transform: translateY(30px) translateZ(-20px); }
    to { opacity: 1; transform: translateY(0) translateZ(0); }
}

@keyframes fadeInScale3D {
    from { opacity: 0; transform: scale(0.95) translateZ(-30px); }
    to { opacity: 1; transform: scale(1) translateZ(0); }
}

@keyframes floatGlow3D {
    0%, 100% { transform: translateY(0px) rotate(0deg) translateZ(10px); }
    50% { transform: translateY(-12px) rotate(4deg) translateZ(20px); }
}

@keyframes pulseGlow3D {
    0% { opacity: 0.6; transform: scale(1); box-shadow: 0 0 10px #34D399; }
    50% { opacity: 1; transform: scale(1.15); box-shadow: 0 0 20px #34D399; }
    100% { opacity: 0.6; transform: scale(1); box-shadow: 0 0 10px #34D399; }
}

.animate-in {
    opacity: 0;
    animation: fadeInUp3D 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-in:nth-child(1) { animation-delay: 0.05s; }
.animate-in:nth-child(2) { animation-delay: 0.1s; }
.animate-in:nth-child(3) { animation-delay: 0.15s; }
.animate-in:nth-child(4) { animation-delay: 0.2s; }
.animate-in:nth-child(5) { animation-delay: 0.25s; }
.animate-in:nth-child(6) { animation-delay: 0.3s; }
.animate-in:nth-child(7) { animation-delay: 0.35s; }
.animate-in:nth-child(8) { animation-delay: 0.4s; }

/* ============ HERO WELCOME BANNER ============ */
.welcome-banner {
    background: linear-gradient(135deg, #060B18 0%, #0F172A 40%, #1E3A8A 85%, #2563EB 100%);
    border-radius: 28px;
    padding: 42px 48px;
    margin-bottom: 34px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.45);
    border: 1.5px solid rgba(255, 255, 255, 0.18);
    animation: fadeInScale3D 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    transform-style: preserve-3d;
    perspective: 1200px;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -80px; right: -60px;
    width: 450px; height: 450px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.35) 0%, rgba(37, 99, 235, 0.05) 65%, transparent 80%);
    animation: floatGlow3D 8s ease-in-out infinite;
}

.welcome-banner::after {
    content: '';
    position: absolute;
    bottom: -100px; left: 15%;
    width: 380px; height: 380px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.25) 0%, transparent 70%);
    animation: floatGlow3D 10s ease-in-out infinite reverse;
}

.welcome-content { position: relative; z-index: 2; }

.status-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    color: #93C5FD;
    margin-bottom: 16px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.4);
}

.status-dot-pulse {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #34D399;
    animation: pulseGlow3D 2s infinite;
}

.welcome-title {
    font-size: 34px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -0.8px;
    margin: 0 0 12px 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    line-height: 1.2;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.welcome-subtitle {
    font-size: 15px;
    color: rgba(226, 232, 240, 0.9);
    font-weight: 400;
    line-height: 1.6;
    margin: 0 0 24px 0;
    font-family: 'Inter', sans-serif;
    max-width: 640px;
}

.banner-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(12px);
    border: 1.5px solid rgba(255, 255, 255, 0.3);
    color: #FFFFFF;
    padding: 10px 24px;
    border-radius: 30px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: 'Plus Jakarta Sans', sans-serif;
    box-shadow: 0 8px 20px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.4);
}

.banner-action-btn:hover {
    background: #FFFFFF;
    color: #1E40AF;
    transform: translateY(-3px) translateZ(10px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.3);
}

.banner-action-btn.primary-white {
    background: #FFFFFF;
    color: #0F172A;
    border-color: #FFFFFF;
    box-shadow: 0 10px 25px rgba(255,255,255,0.25), inset 0 1px 0 rgba(255,255,255,1);
}

.banner-action-btn.primary-white:hover {
    background: #F8FAFC;
    color: #2563EB;
    box-shadow: 0 14px 32px rgba(255,255,255,0.4);
}

/* ============ 3D STAT CARDS ============ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 38px;
    perspective: 1200px;
}

.stat-card {
    border-radius: 26px;
    padding: 32px 34px;
    position: relative;
    overflow: hidden;
    min-height: 170px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1.5px solid rgba(255, 255, 255, 0.3);
    transform-style: preserve-3d;
}

.stat-card:hover {
    transform: translateY(-8px) rotateX(3deg) scale(1.015);
}

.stat-card.blue {
    background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 50%, #38BDF8 100%);
    box-shadow: 0 20px 40px -10px rgba(37,99,235,0.45);
}

.stat-card.teal {
    background: linear-gradient(135deg, #047857 0%, #059669 50%, #34D399 100%);
    box-shadow: 0 20px 40px -10px rgba(16,185,129,0.45);
}

.stat-card.navy {
    background: linear-gradient(135deg, #6D28D9 0%, #7C3AED 50%, #C084FC 100%);
    box-shadow: 0 20px 40px -10px rgba(139,92,246,0.45);
}

.stat-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    position: relative;
    z-index: 2;
}

.stat-card-label {
    font-size: 13px;
    font-weight: 800;
    color: rgba(255,255,255,0.95);
    font-family: 'Plus Jakarta Sans', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.stat-card-icon {
    width: 52px; height: 52px;
    border-radius: 18px;
    background: rgba(255,255,255,0.22);
    backdrop-filter: blur(14px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #FFFFFF;
    box-shadow: 0 10px 22px rgba(0,0,0,0.2), inset 0 2px 4px rgba(255,255,255,0.4);
    transform: translateZ(14px);
}

.stat-card-value {
    font-size: 44px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -1.5px;
    line-height: 1;
    font-family: 'Plus Jakarta Sans', sans-serif;
    margin-bottom: 14px;
    position: relative;
    z-index: 2;
    text-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.stat-card-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 2;
}

.stat-trend {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 20px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-trend.up { background: rgba(255,255,255,0.25); color: #FFFFFF; backdrop-filter: blur(8px); }

.stat-trend-label {
    font-size: 12.5px;
    color: rgba(255,255,255,0.9);
    font-weight: 600;
    font-family: 'Inter', sans-serif;
}

/* ============ SECTION HEADER ============ */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.section-title {
    font-size: 22px;
    font-weight: 800;
    color: #0F172A;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.4px;
    margin: 0;
}

.section-subtitle {
    font-size: 14px;
    color: #64748B;
    font-weight: 500;
    margin-top: 4px;
    font-family: 'Inter', sans-serif;
}

/* ============ 3D QUICK ACCESS FEATURE CARDS ============ */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-bottom: 40px;
    perspective: 1200px;
}

.mc-link { text-decoration: none; color: inherit; display: block; }

.mc {
    background: #FFFFFF;
    border: 1.5px solid #E2E8F0;
    border-radius: 24px;
    padding: 30px 26px;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
    transform-style: preserve-3d;
}

/* Permanently visible top glowing accent line */
.mc::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--mc-color, #2563EB), var(--mc-color-end, #38BDF8));
    box-shadow: 0 2px 8px var(--mc-shadow);
}

.mc:hover {
    transform: translateY(-8px) rotateX(3deg) scale(1.02);
    border-color: var(--mc-color, #2563EB);
    box-shadow: 0 22px 45px -10px var(--mc-shadow, rgba(37,99,235,0.3));
}

/* Vibrant Dual-Tone Gradient 3D Icons */
.mc-icon {
    width: 58px; height: 58px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-bottom: 22px;
    flex-shrink: 0;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    color: #FFFFFF !important;
    transform: translateZ(14px);
}

.mc:hover .mc-icon {
    transform: translateZ(24px) scale(1.12) rotate(6deg);
}

.i-red    { background: linear-gradient(135deg, #EF4444 0%, #F43F5E 100%); box-shadow: 0 10px 24px rgba(239, 68, 68, 0.4), inset 0 2px 4px rgba(255,255,255,0.4); }
.i-blue   { background: linear-gradient(135deg, #2563EB 0%, #3B82F6 100%); box-shadow: 0 10px 24px rgba(37, 99, 235, 0.4), inset 0 2px 4px rgba(255,255,255,0.4); }
.i-cyan   { background: linear-gradient(135deg, #06B6D4 0%, #0EA5E9 100%); box-shadow: 0 10px 24px rgba(6, 182, 212, 0.4), inset 0 2px 4px rgba(255,255,255,0.4); }
.i-sky    { background: linear-gradient(135deg, #0284C7 0%, #38BDF8 100%); box-shadow: 0 10px 24px rgba(2, 132, 199, 0.4), inset 0 2px 4px rgba(255,255,255,0.4); }
.i-green  { background: linear-gradient(135deg, #10B981 0%, #059669 100%); box-shadow: 0 10px 24px rgba(16, 185, 129, 0.4), inset 0 2px 4px rgba(255,255,255,0.4); }
.i-amber  { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); box-shadow: 0 10px 24px rgba(245, 158, 11, 0.4), inset 0 2px 4px rgba(255,255,255,0.4); }
.i-violet { background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%); box-shadow: 0 10px 24px rgba(139, 92, 246, 0.4), inset 0 2px 4px rgba(255,255,255,0.4); }
.i-slate  { background: linear-gradient(135deg, #475569 0%, #0F172A 100%); box-shadow: 0 10px 24px rgba(71, 85, 105, 0.4), inset 0 2px 4px rgba(255,255,255,0.4); }

/* Accent Shadow per Link */
.mc-link:nth-child(1) .mc { --mc-color: #EF4444; --mc-color-end: #F43F5E; --mc-shadow: rgba(239, 68, 68, 0.35); }
.mc-link:nth-child(2) .mc { --mc-color: #2563EB; --mc-color-end: #3B82F6; --mc-shadow: rgba(37, 99, 235, 0.35); }
.mc-link:nth-child(3) .mc { --mc-color: #06B6D4; --mc-color-end: #0EA5E9; --mc-shadow: rgba(6, 182, 212, 0.35); }
.mc-link:nth-child(4) .mc { --mc-color: #0284C7; --mc-color-end: #38BDF8; --mc-shadow: rgba(2, 132, 199, 0.35); }
.mc-link:nth-child(5) .mc { --mc-color: #10B981; --mc-color-end: #059669; --mc-shadow: rgba(16, 185, 129, 0.35); }
.mc-link:nth-child(6) .mc { --mc-color: #F59E0B; --mc-color-end: #D97706; --mc-shadow: rgba(245, 158, 11, 0.35); }
.mc-link:nth-child(7) .mc { --mc-color: #8B5CF6; --mc-color-end: #6D28D9; --mc-shadow: rgba(139, 92, 246, 0.35); }
.mc-link:nth-child(8) .mc { --mc-color: #3B82F6; --mc-color-end: #1D4ED8; --mc-shadow: rgba(59, 130, 246, 0.35); }

.mc-title {
    font-size: 16.5px;
    font-weight: 800;
    color: #0F172A;
    margin-bottom: 8px;
    letter-spacing: -0.3px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.mc-desc {
    font-size: 13.5px;
    color: #64748B;
    line-height: 1.55;
    margin-bottom: 24px;
    flex-grow: 1;
    font-family: 'Inter', sans-serif;
}

.mc-btn-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #EFF6FF;
    color: var(--mc-color, #2563EB);
    padding: 8px 18px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transition: all 0.3s ease;
    border: 1px solid rgba(37, 99, 235, 0.15);
}

.mc:hover .mc-btn-pill {
    background: var(--mc-color, #2563EB);
    color: #FFFFFF;
    box-shadow: 0 6px 16px var(--mc-shadow);
}

@media (max-width: 1200px) {
    .menu-grid { grid-template-columns: repeat(2, 1fr); }
    .stats-grid { grid-template-columns: repeat(1, 1fr); }
}

@media (max-width: 768px) {
    .menu-grid { grid-template-columns: repeat(1, 1fr); }
    .welcome-banner { padding: 28px 22px; }
    .welcome-title { font-size: 26px; }
}
</style>

<!-- Welcome Hero Banner -->
<div class="welcome-banner">
    <div class="welcome-content">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="status-pill-badge">
                    <span class="status-dot-pulse"></span>
                    <span>Live Data Analytics Active</span>
                </div>
                <h1 class="welcome-title">Selamat Datang, <?php echo htmlspecialchars($firstName); ?> 👋</h1>
                <p class="welcome-subtitle">Kelola seluruh database customer, prospek, penugasan sales, dan laporan analytics tim dalam satu dashboard terpusat yang realtime.</p>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <a href="customer_add.php" class="banner-action-btn primary-white">
                        <i class="bi bi-person-plus-fill text-primary"></i>
                        <span>+ Tambah Customer</span>
                    </a>
                    <a href="sales_assignment.php" class="banner-action-btn">
                        <i class="bi bi-person-gear"></i>
                        <span>Penugasan Sales Cepat</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/announcement_widget.php'; ?>

<!-- 3 Stat Cards -->
<div class="stats-grid">
    <div class="stat-card blue animate-in">
        <div class="stat-card-header">
            <span class="stat-card-label">TOTAL CUSTOMER</span>
            <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
        </div>
        <div class="stat-card-value"><?php echo number_format($totalCustomers, 0, ',', '.'); ?></div>
        <div class="stat-card-footer">
            <span class="stat-trend up"><i class="bi bi-arrow-up-short"></i> +12%</span>
            <span class="stat-trend-label">vs bulan lalu</span>
        </div>
    </div>

    <div class="stat-card teal animate-in">
        <div class="stat-card-header">
            <span class="stat-card-label">PENDING FOLLOW UP</span>
            <div class="stat-card-icon"><i class="bi bi-clock-history"></i></div>
        </div>
        <div class="stat-card-value"><?php echo number_format($pendingFU, 0, ',', '.'); ?></div>
        <div class="stat-card-footer">
            <span class="stat-trend up"><i class="bi bi-exclamation-circle-fill"></i> <?php echo $pendingFU; ?></span>
            <span class="stat-trend-label">perlu ditindak</span>
        </div>
    </div>

    <div class="stat-card navy animate-in">
        <div class="stat-card-header">
            <span class="stat-card-label">CUSTOMER BARU</span>
            <div class="stat-card-icon"><i class="bi bi-person-plus-fill"></i></div>
        </div>
        <div class="stat-card-value"><?php echo number_format($thisMonthNew, 0, ',', '.'); ?></div>
        <div class="stat-card-footer">
            <span class="stat-trend up"><i class="bi bi-arrow-up-short"></i> +8%</span>
            <span class="stat-trend-label">bulan ini</span>
        </div>
    </div>
</div>

<!-- Section Header -->
<div class="section-header">
    <div>
        <h3 class="section-title">Akses Cepat Fitur Utama 🚀</h3>
        <p class="section-subtitle">Pilih menu untuk mengelola data customer dan fitur sales tools</p>
    </div>
</div>

<!-- 8 Vibrant Dual-Tone Quick Access Menu Cards -->
<div class="menu-grid">
    <a href="index.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-red"><i class="bi bi-people-fill"></i></div>
            <div class="mc-title"><span>Daftar Customer</span></div>
            <div class="mc-desc">Lihat daftar customer dan detail informasi lengkap.</div>
            <div class="mt-auto"><span class="mc-btn-pill">Buka Menu <i class="bi bi-arrow-right"></i></span></div>
        </div>
    </a>

    <a href="kandidat_customer.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-blue"><i class="bi bi-person-badge-fill"></i></div>
            <div class="mc-title"><span>Potensial Customer</span></div>
            <div class="mc-desc">Customer potensial dengan peluang konversi tinggi.</div>
            <div class="mt-auto"><span class="mc-btn-pill">Buka Menu <i class="bi bi-arrow-right"></i></span></div>
        </div>
    </a>

    <a href="customer_add.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-cyan"><i class="bi bi-person-plus-fill"></i></div>
            <div class="mc-title"><span>Tambah Customer</span></div>
            <div class="mc-desc">Tambah customer baru ke dalam sistem database.</div>
            <div class="mt-auto"><span class="mc-btn-pill">Buka Menu <i class="bi bi-arrow-right"></i></span></div>
        </div>
    </a>

    <a href="customer_io.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-sky"><i class="bi bi-cloud-upload-fill"></i></div>
            <div class="mc-title"><span>Unggah Data Customer</span></div>
            <div class="mc-desc">Import data customer dari file Excel (.XLSX).</div>
            <div class="mt-auto"><span class="mc-btn-pill">Buka Menu <i class="bi bi-arrow-right"></i></span></div>
        </div>
    </a>

    <a href="customer_bulk_download_process.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-green"><i class="bi bi-cloud-download-fill"></i></div>
            <div class="mc-title"><span>Unduh Data Customer</span></div>
            <div class="mc-desc">Export data customer ke format file Excel.</div>
            <div class="mt-auto"><span class="mc-btn-pill">Buka Menu <i class="bi bi-arrow-right"></i></span></div>
        </div>
    </a>

    <a href="customer_quality_check.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-amber"><i class="bi bi-clipboard-check-fill"></i></div>
            <div class="mc-title"><span>Kualitas Data Customer</span></div>
            <div class="mc-desc">Audit kualitas data dan validasi format.</div>
            <div class="mt-auto"><span class="mc-btn-pill">Buka Menu <i class="bi bi-arrow-right"></i></span></div>
        </div>
    </a>

    <a href="sales_invoice_report.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-violet"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div>
            <div class="mc-title"><span>Laporan Invoice FU</span></div>
            <div class="mc-desc">Laporan invoice dan riwayat follow up.</div>
            <div class="mt-auto"><span class="mc-btn-pill">Buka Menu <i class="bi bi-arrow-right"></i></span></div>
        </div>
    </a>

    <a href="sales_qa.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-slate"><i class="bi bi-robot"></i></div>
            <div class="mc-title"><span>Asisten Loewix AI</span></div>
            <div class="mc-desc">Konsultasi sales & taktik negosiasi realtime.</div>
            <div class="mt-auto"><span class="mc-btn-pill">Buka Chat AI <i class="bi bi-arrow-right"></i></span></div>
        </div>
    </a>
</div>

<?php include 'includes/footer.php'; ?>