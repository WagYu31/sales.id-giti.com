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

// Category Breakdown for Analytics Widget
$catCounts = ['DEALER' => 0, 'INSTALLER' => 0, 'USER' => 0, 'UMUM' => 0];
$rc = $conn->query("SELECT kategori, COUNT(*) as c FROM customers WHERE deleted_at IS NULL GROUP BY kategori");
if ($rc) {
    while ($row = $rc->fetch_assoc()) {
        $k = strtoupper(trim($row['kategori'] ?? 'UMUM'));
        if (isset($catCounts[$k])) $catCounts[$k] = (int)$row['c'];
        else $catCounts['UMUM'] += (int)$row['c'];
    }
}
$sumCats = array_sum($catCounts) ?: 1;

// Top Cities Breakdown
$topCities = [];
$rCity = $conn->query("SELECT kota, COUNT(*) as total FROM customer_addresses WHERE deleted_at IS NULL AND kota != '' AND kota != '-' GROUP BY kota ORDER BY total DESC LIMIT 4");
if ($rCity) {
    while ($row = $rCity->fetch_assoc()) {
        $topCities[] = $row;
    }
}

$firstName = explode(' ', $_SESSION['nama_lengkap'] ?? 'User')[0];
?>

<style>
/* ============ ANIMATIONS & MICRO-INTERACTIONS ============ */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInScale {
    from { opacity: 0; transform: scale(0.96); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes floatGlow {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(4deg); }
}

@keyframes pulseGlow {
    0% { opacity: 0.6; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.1); }
    100% { opacity: 0.6; transform: scale(1); }
}

.animate-in {
    opacity: 0;
    animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
    background: linear-gradient(135deg, #030712 0%, #0F172A 40%, #1E40AF 100%);
    border-radius: 28px;
    padding: 42px 48px;
    margin-bottom: 34px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.15);
    animation: fadeInScale 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -80px; right: -60px;
    width: 450px; height: 450px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0.05) 65%, transparent 80%);
    animation: floatGlow 8s ease-in-out infinite;
}

.welcome-banner::after {
    content: '';
    position: absolute;
    bottom: -100px; left: 15%;
    width: 380px; height: 380px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.22) 0%, transparent 70%);
    animation: floatGlow 10s ease-in-out infinite reverse;
}

.welcome-content { position: relative; z-index: 2; }

.status-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.22);
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    color: #93C5FD;
    margin-bottom: 16px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.15);
}

.status-dot-pulse {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #34D399;
    box-shadow: 0 0 12px #34D399;
    animation: pulseGlow 2s infinite;
}

.welcome-title {
    font-size: 34px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -0.8px;
    margin: 0 0 12px 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    line-height: 1.2;
}

.welcome-subtitle {
    font-size: 15px;
    color: rgba(226, 232, 240, 0.88);
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
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #FFFFFF;
    padding: 10px 22px;
    border-radius: 30px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.banner-action-btn:hover {
    background: #FFFFFF;
    color: #1E40AF;
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.25);
}

.banner-action-btn.primary-white {
    background: #FFFFFF;
    color: #0F172A;
    border-color: #FFFFFF;
    box-shadow: 0 8px 20px rgba(255,255,255,0.2);
}

.banner-action-btn.primary-white:hover {
    background: #F8FAFC;
    color: #2563EB;
}

/* ============ STAT CARDS ============ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px;
    margin-bottom: 38px;
}

.stat-card {
    border-radius: 26px;
    padding: 32px 34px;
    position: relative;
    overflow: hidden;
    min-height: 165px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid rgba(255,255,255,0.22);
}

.stat-card:hover {
    transform: translateY(-8px) scale(1.02);
}

.stat-card.blue {
    background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 50%, #38BDF8 100%);
    box-shadow: 0 16px 36px -8px rgba(37,99,235,0.45);
}

.stat-card.teal {
    background: linear-gradient(135deg, #047857 0%, #059669 50%, #34D399 100%);
    box-shadow: 0 16px 36px -8px rgba(16,185,129,0.4);
}

.stat-card.navy {
    background: linear-gradient(135deg, #6D28D9 0%, #7C3AED 50%, #C084FC 100%);
    box-shadow: 0 16px 36px -8px rgba(139,92,246,0.4);
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
    color: rgba(255,255,255,0.92);
    font-family: 'Plus Jakarta Sans', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.stat-card-icon {
    width: 50px; height: 50px;
    border-radius: 18px;
    background: rgba(255,255,255,0.22);
    backdrop-filter: blur(12px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #FFFFFF;
    box-shadow: 0 8px 18px rgba(0,0,0,0.18);
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
}

.stat-trend.up { background: rgba(255,255,255,0.25); color: #FFFFFF; backdrop-filter: blur(8px); }

.stat-trend-label {
    font-size: 12.5px;
    color: rgba(255,255,255,0.85);
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

/* ============ VIBRANT MENU CARDS ============ */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
    margin-bottom: 40px;
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
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
}

/* Permanently visible top glowing accent line */
.mc::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--mc-color, #2563EB), var(--mc-color-end, #38BDF8));
}

.mc:hover {
    transform: translateY(-8px);
    border-color: var(--mc-color, #2563EB);
    box-shadow: 0 20px 40px -10px var(--mc-shadow, rgba(37,99,235,0.25));
}

/* Vibrant Dual-Tone Gradient 3D Icons */
.mc-icon {
    width: 56px; height: 56px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-bottom: 22px;
    flex-shrink: 0;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    color: #FFFFFF !important;
}

.mc:hover .mc-icon {
    transform: scale(1.12) rotate(5deg);
}

.i-red    { background: linear-gradient(135deg, #EF4444 0%, #F43F5E 100%); box-shadow: 0 8px 22px rgba(239, 68, 68, 0.35); }
.i-blue   { background: linear-gradient(135deg, #2563EB 0%, #3B82F6 100%); box-shadow: 0 8px 22px rgba(37, 99, 235, 0.35); }
.i-cyan   { background: linear-gradient(135deg, #06B6D4 0%, #0EA5E9 100%); box-shadow: 0 8px 22px rgba(6, 182, 212, 0.35); }
.i-sky    { background: linear-gradient(135deg, #0284C7 0%, #38BDF8 100%); box-shadow: 0 8px 22px rgba(2, 132, 199, 0.35); }
.i-green  { background: linear-gradient(135deg, #10B981 0%, #059669 100%); box-shadow: 0 8px 22px rgba(16, 185, 129, 0.35); }
.i-amber  { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); box-shadow: 0 8px 22px rgba(245, 158, 11, 0.35); }
.i-violet { background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%); box-shadow: 0 8px 22px rgba(139, 92, 246, 0.35); }
.i-slate  { background: linear-gradient(135deg, #475569 0%, #0F172A 100%); box-shadow: 0 8px 22px rgba(71, 85, 105, 0.35); }

/* Accent Shadow per Link */
.mc-link:nth-child(1) .mc { --mc-color: #EF4444; --mc-color-end: #F43F5E; --mc-shadow: rgba(239, 68, 68, 0.3); }
.mc-link:nth-child(2) .mc { --mc-color: #2563EB; --mc-color-end: #3B82F6; --mc-shadow: rgba(37, 99, 235, 0.3); }
.mc-link:nth-child(3) .mc { --mc-color: #06B6D4; --mc-color-end: #0EA5E9; --mc-shadow: rgba(6, 182, 212, 0.3); }
.mc-link:nth-child(4) .mc { --mc-color: #0284C7; --mc-color-end: #38BDF8; --mc-shadow: rgba(2, 132, 199, 0.3); }
.mc-link:nth-child(5) .mc { --mc-color: #10B981; --mc-color-end: #059669; --mc-shadow: rgba(16, 185, 129, 0.3); }
.mc-link:nth-child(6) .mc { --mc-color: #F59E0B; --mc-color-end: #D97706; --mc-shadow: rgba(245, 158, 11, 0.3); }
.mc-link:nth-child(7) .mc { --mc-color: #8B5CF6; --mc-color-end: #6D28D9; --mc-shadow: rgba(139, 92, 246, 0.3); }
.mc-link:nth-child(8) .mc { --mc-color: #3B82F6; --mc-color-end: #1D4ED8; --mc-shadow: rgba(59, 130, 246, 0.3); }

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
    color: #475569;
    line-height: 1.6;
    font-weight: 400;
    flex-grow: 1;
    font-family: 'Inter', sans-serif;
}

.mc-btn-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 22px;
    font-size: 13px;
    font-weight: 700;
    color: #2563EB;
    background: #EFF6FF;
    border: 1px solid #BFDBFE;
    padding: 8px 18px;
    border-radius: 30px;
    transition: all 0.3s ease;
    font-family: 'Plus Jakarta Sans', sans-serif;
    width: fit-content;
}

.mc:hover .mc-btn-pill {
    background: var(--mc-color, #2563EB);
    color: #FFFFFF;
    border-color: var(--mc-color, #2563EB);
    box-shadow: 0 4px 14px var(--mc-shadow, rgba(37,99,235,0.3));
}

.mc:hover .mc-btn-pill i {
    transform: translateX(4px);
}

.mc-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 20px;
    background: linear-gradient(135deg, #6366F1, #4F46E5);
    color: #FFFFFF;
    margin-left: 6px;
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    box-shadow: 0 2px 8px rgba(99,102,241,0.4);
}

/* ============ EXECUTIVE INSIGHTS WIDGET GRID ============ */
.executive-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-top: 20px;
}

.insight-card {
    background: #FFFFFF;
    border-radius: 26px;
    padding: 30px 34px;
    border: 1.5px solid #E2E8F0;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.03);
}

.cat-meter-bar {
    height: 10px;
    border-radius: 20px;
    background: #F1F5F9;
    overflow: hidden;
    display: flex;
    margin: 16px 0 24px 0;
}

.cat-meter-fill {
    height: 100%;
    transition: width 0.6s ease;
}

@media (max-width: 1200px) {
    .menu-grid { grid-template-columns: repeat(2, 1fr); }
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
    .executive-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .menu-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr; }
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

<!-- Akses Cepat Menu Grid -->
<div class="section-header">
    <div>
        <h2 class="section-title">Akses Cepat Fitur Utama 🚀</h2>
        <p class="section-subtitle">Pilih menu untuk mengelola data customer dan fitur sales tools</p>
    </div>
</div>

<div class="menu-grid">
    <a href="index.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-red"><i class="bi bi-people-fill"></i></div>
            <div class="mc-title">Daftar Customer</div>
            <div class="mc-desc">Lihat daftar customer dan detail informasi lengkap.</div>
            <div class="mc-btn-pill"><span>Buka Menu</span> <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>

    <a href="kandidat_customer.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-blue"><i class="bi bi-person-check-fill"></i></div>
            <div class="mc-title">Potensial Customer</div>
            <div class="mc-desc">Customer potensial dengan peluang konversi tinggi.</div>
            <div class="mc-btn-pill"><span>Buka Menu</span> <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>

    <a href="customer_add.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-cyan"><i class="bi bi-person-plus-fill"></i></div>
            <div class="mc-title">Tambah Customer</div>
            <div class="mc-desc">Tambah customer baru ke dalam sistem database.</div>
            <div class="mc-btn-pill"><span>Buka Menu</span> <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>

    <a href="customer_io.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-sky"><i class="bi bi-cloud-arrow-up-fill"></i></div>
            <div class="mc-title">Unggah Data Customer</div>
            <div class="mc-desc">Import data customer dari file Excel (XLSX).</div>
            <div class="mc-btn-pill"><span>Buka Menu</span> <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>

    <a href="customer_export.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-green"><i class="bi bi-cloud-arrow-down-fill"></i></div>
            <div class="mc-title">Unduh Data Customer</div>
            <div class="mc-desc">Export data customer ke format file Excel.</div>
            <div class="mc-btn-pill"><span>Buka Menu</span> <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>

    <a href="customer_maintenance.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-amber"><i class="bi bi-clipboard-check-fill"></i></div>
            <div class="mc-title">Kualitas Data Customer</div>
            <div class="mc-desc">Audit kualitas data dan validasi format.</div>
            <div class="mc-btn-pill"><span>Buka Menu</span> <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>

    <a href="followup_report.php" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-violet"><i class="bi bi-journal-text"></i></div>
            <div class="mc-title">Laporan Invoice FU</div>
            <div class="mc-desc">Laporan invoice dan riwayat follow up.</div>
            <div class="mc-btn-pill"><span>Buka Menu</span> <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>

    <a href="sales_assistant.html" class="mc-link animate-in">
        <div class="mc">
            <div class="mc-icon i-slate"><i class="bi bi-robot"></i></div>
            <div class="mc-title">Asisten Loewix <span class="mc-badge">BETA</span></div>
            <div class="mc-desc">AI assistant untuk menjawab pertanyaan customer.</div>
            <div class="mc-btn-pill"><span>Buka Menu</span> <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
</div>

<!-- EXECUTIVE ANALYTICS INSIGHTS -->
<div class="executive-grid animate-in">
    <div class="insight-card">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <h3 class="fw-extrabold text-dark m-0" style="font-size:18px; font-family:'Plus Jakarta Sans', sans-serif;">📊 Distibusi Kategori Customer</h3>
                <p class="text-muted small m-0">Ringkasan komposisi segmen customer di database</p>
            </div>
            <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill">Total: <?php echo number_format($totalCustomers, 0, ',', '.'); ?></span>
        </div>

        <div class="cat-meter-bar">
            <?php 
            $pctDealer = round(($catCounts['DEALER'] / $sumCats) * 100, 1);
            $pctInstaller = round(($catCounts['INSTALLER'] / $sumCats) * 100, 1);
            $pctUser = round(($catCounts['USER'] / $sumCats) * 100, 1);
            $pctUmum = round(($catCounts['UMUM'] / $sumCats) * 100, 1);
            ?>
            <div class="cat-meter-fill" style="width: <?php echo $pctDealer; ?>%; background: #0891B2;" title="Dealer: <?php echo $pctDealer; ?>%"></div>
            <div class="cat-meter-fill" style="width: <?php echo $pctInstaller; ?>%; background: #4338CA;" title="Installer: <?php echo $pctInstaller; ?>%"></div>
            <div class="cat-meter-fill" style="width: <?php echo $pctUser; ?>%; background: #D97706;" title="User: <?php echo $pctUser; ?>%"></div>
            <div class="cat-meter-fill" style="width: <?php echo $pctUmum; ?>%; background: #64748B;" title="Umum: <?php echo $pctUmum; ?>%"></div>
        </div>

        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-4" style="background:#ECFEFF; border:1px solid #A5F3FC;">
                    <div class="small fw-bold text-muted mb-1">DEALER</div>
                    <div class="fs-5 fw-extrabold text-dark"><?php echo number_format($catCounts['DEALER'], 0, ',', '.'); ?></div>
                    <div class="small fw-bold text-info" style="color:#0891B2 !important;"><?php echo $pctDealer; ?>%</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-4" style="background:#EEF2FF; border:1px solid #C7D2FE;">
                    <div class="small fw-bold text-muted mb-1">INSTALLER</div>
                    <div class="fs-5 fw-extrabold text-dark"><?php echo number_format($catCounts['INSTALLER'], 0, ',', '.'); ?></div>
                    <div class="small fw-bold text-indigo" style="color:#4338CA !important;"><?php echo $pctInstaller; ?>%</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-4" style="background:#FEF3C7; border:1px solid #FDE68A;">
                    <div class="small fw-bold text-muted mb-1">USER</div>
                    <div class="fs-5 fw-extrabold text-dark"><?php echo number_format($catCounts['USER'], 0, ',', '.'); ?></div>
                    <div class="small fw-bold text-warning" style="color:#D97706 !important;"><?php echo $pctUser; ?>%</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-4" style="background:#F1F5F9; border:1px solid #CBD5E1;">
                    <div class="small fw-bold text-muted mb-1">UMUM</div>
                    <div class="fs-5 fw-extrabold text-dark"><?php echo number_format($catCounts['UMUM'], 0, ',', '.'); ?></div>
                    <div class="small fw-bold text-secondary"><?php echo $pctUmum; ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="insight-card">
        <h3 class="fw-extrabold text-dark mb-2" style="font-size:18px; font-family:'Plus Jakarta Sans', sans-serif;">📍 Top Wilayah Customer</h3>
        <p class="text-muted small mb-3">Kota dengan konsentrasi customer terbanyak</p>
        <div class="d-flex flex-column gap-2.5">
            <?php if (!empty($topCities)): ?>
                <?php foreach($topCities as $idx => $tc): ?>
                    <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3" style="background:#F8FAFC; border:1px solid #E2E8F0;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary text-white fw-bold" style="width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:11px;"><?php echo $idx+1; ?></span>
                            <span class="fw-bold text-dark small">📍 <?php echo htmlspecialchars($tc['kota']); ?></span>
                        </div>
                        <span class="badge bg-primary-subtle text-primary fw-extrabold" style="font-size:12px;"><?php echo number_format($tc['total'], 0, ',', '.'); ?> Stores</span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted small italic">Belum ada data wilayah.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>