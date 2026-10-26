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

/* ============ EXECUTIVE COMMAND BAR (TASTE-SKILL COMPLIANT) ============ */
.exec-command-bar {
    background: #0B1120;
    border-radius: 20px;
    padding: 22px 28px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 30px -8px rgba(15, 23, 42, 0.35);
    border: 1px solid #1E293B;
}

.exec-command-bar::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 320px; height: 100%;
    background: radial-gradient(circle at top right, rgba(14, 165, 233, 0.12), transparent 70%);
    pointer-events: none;
}

.exec-bar-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
}

.exec-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: #94A3B8;
    letter-spacing: 0.02em;
}

.exec-pulse-dot {
    width: 6.5px;
    height: 6.5px;
    border-radius: 50%;
    background: #10B981;
    box-shadow: 0 0 8px #10B981;
    animation: execPulse 2s infinite ease-in-out;
}

@keyframes execPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.85); }
}

.exec-bar-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
}

.exec-title-area {
    min-width: 260px;
}

.exec-title {
    font-size: 22px;
    font-weight: 800;
    color: #F8FAFC;
    letter-spacing: -0.025em;
    margin: 0 0 4px 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    line-height: 1.2;
}

.exec-subtitle {
    font-size: 13px;
    color: #94A3B8;
    font-weight: 400;
    line-height: 1.5;
    margin: 0;
}

.exec-controls-area {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

/* Omni-Search Input (Cmd+K style) */
.exec-search-form {
    position: relative;
    display: flex;
    align-items: center;
}

.exec-search-icon {
    position: absolute;
    left: 12px;
    font-size: 13px;
    color: #64748B;
    pointer-events: none;
}

.exec-search-input {
    background: #1E293B;
    border: 1px solid #334155;
    border-radius: 10px;
    padding: 8px 48px 8px 34px;
    font-size: 12.5px;
    color: #F8FAFC;
    width: 250px;
    transition: all 0.2s ease;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.exec-search-input:focus {
    outline: none;
    border-color: #0EA5E9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2);
    width: 280px;
    background: #0F172A;
}

.exec-search-input::placeholder {
    color: #64748B;
    font-size: 12px;
}

.exec-search-kbd {
    position: absolute;
    right: 8px;
    background: #334155;
    border: 1px solid #475569;
    border-radius: 5px;
    padding: 1.5px 5px;
    font-size: 10px;
    color: #94A3B8;
    font-family: monospace;
    pointer-events: none;
}

.exec-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.01em;
    white-space: nowrap;
    cursor: pointer;
}

.exec-btn-primary {
    background: #0EA5E9;
    color: #FFFFFF;
    border: 1px solid #0284C7;
    box-shadow: 0 2px 8px rgba(14, 165, 233, 0.35);
}

.exec-btn-primary:hover {
    background: #0284C7;
    color: #FFFFFF;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.45);
}

.exec-btn-secondary {
    background: #1E293B;
    color: #F8FAFC;
    border: 1px solid #334155;
}

.exec-btn-secondary:hover {
    background: #334155;
    color: #FFFFFF;
    border-color: #475569;
    transform: translateY(-1px);
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

/* ============ OPERATIONAL CLUSTERS (TASTE-SKILL COMPLIANT) ============ */
.exec-cluster-wrap {
    margin-top: 10px;
    margin-bottom: 40px;
}

.exec-cluster-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    padding: 0 4px;
}

.exec-cluster-title {
    font-size: 14px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: 0.04em;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    text-transform: uppercase;
}

.exec-cluster-tag {
    font-size: 12px;
    color: #64748B;
    font-weight: 500;
}

.exec-card-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 26px;
}

.exec-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.exec-card {
    background: #FFFFFF;
    border: 1.5px solid #E2E8F0;
    border-radius: 18px;
    padding: 20px 22px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
}

.exec-card:hover {
    transform: translateY(-3px);
    border-color: #CBD5E1;
    box-shadow: 0 12px 28px -6px rgba(15, 23, 42, 0.08);
}

.exec-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 14px;
}

.exec-card-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    color: #0F172A;
    transition: all 0.2s ease;
}

.exec-card:hover .exec-card-icon-wrap {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
}

.exec-card-badge {
    font-size: 10.5px;
    font-weight: 700;
    padding: 2.5px 8px;
    border-radius: 999px;
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #E2E8F0;
    font-family: 'Geist Mono', monospace;
    white-space: nowrap;
}

.exec-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 6px 0;
    letter-spacing: -0.015em;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.exec-card-desc {
    font-size: 12.5px;
    color: #64748B;
    line-height: 1.5;
    margin-bottom: 16px;
    flex-grow: 1;
}

.exec-card-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 700;
    color: #0F172A;
    transition: gap 0.2s ease, color 0.2s ease;
}

.exec-card:hover .exec-card-action {
    gap: 8px;
    color: #0EA5E9;
}

@media (max-width: 1199px) {
    .exec-card-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
}

@media (max-width: 640px) {
    .exec-card-grid { grid-template-columns: 1fr; gap: 12px; }
    .exec-command-bar { padding: 18px 16px; }
    .exec-search-input { width: 100%; }
    .exec-controls-area { width: 100%; }
}

/* ============ ENTRANCE CHOREOGRAPHY (TASTE-SKILL STANDARD) ============ */
@keyframes choreoFadeDown {
    0% {
        opacity: 0;
        transform: translateY(-16px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes choreoFadeUp {
    0% {
        opacity: 0;
        transform: translateY(18px) scale(0.99);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.exec-command-bar {
    animation: choreoFadeDown 0.65s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.exec-cluster-wrap {
    animation: choreoFadeUp 0.65s cubic-bezier(0.16, 1, 0.3, 1) 0.32s both;
}

.exec-card-grid .exec-card-link:nth-child(1) .exec-card { animation: choreoFadeUp 0.55s cubic-bezier(0.16, 1, 0.3, 1) 0.36s both; }
.exec-card-grid .exec-card-link:nth-child(2) .exec-card { animation: choreoFadeUp 0.55s cubic-bezier(0.16, 1, 0.3, 1) 0.40s both; }
.exec-card-grid .exec-card-link:nth-child(3) .exec-card { animation: choreoFadeUp 0.55s cubic-bezier(0.16, 1, 0.3, 1) 0.44s both; }
.exec-card-grid .exec-card-link:nth-child(4) .exec-card { animation: choreoFadeUp 0.55s cubic-bezier(0.16, 1, 0.3, 1) 0.48s both; }

@media (prefers-reduced-motion: reduce) {
    .exec-command-bar, .exec-cluster-wrap, .exec-card {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
}
</style>

<!-- Executive Command Bar (Taste-Skill Compliant) -->
<div class="exec-command-bar">
    <div class="exec-bar-meta">
        <div class="exec-status-pill">
            <span class="exec-pulse-dot"></span>
            <span>LOEWIX SALES INTELLIGENCE</span>
            <span style="opacity: 0.5;">·</span>
            <span class="text-white">Live MySQL Sync</span>
            <span style="opacity: 0.5;">·</span>
            <span><?php echo number_format($totalCustomers, 0, ',', '.'); ?> Basis Toko</span>
        </div>
        <div class="d-none d-md-flex align-items-center gap-2 text-muted" style="font-size: 11px;">
            <span><i class="bi bi-shield-check text-success me-1"></i>Server Terverifikasi</span>
            <span style="opacity: 0.4;">|</span>
            <span>Ping Realtime Database</span>
        </div>
    </div>
    <div class="exec-bar-main">
        <div class="exec-title-area">
            <h1 class="exec-title">Executive Command Center</h1>
            <p class="exec-subtitle">
                Halo, <strong class="text-white"><?php echo htmlspecialchars($firstName); ?></strong>. Pantau pipeline, analitik penetrasi wilayah, dan kelola operasional sales terpadu.
            </p>
        </div>
        <div class="exec-controls-area">
            <!-- Omni-Search (Cmd+K style) -->
            <form action="index.php" method="GET" class="exec-search-form" id="execSearchForm">
                <i class="bi bi-search exec-search-icon"></i>
                <input type="text" name="search" id="execOmniInput" class="exec-search-input" placeholder="Cari toko, kota, kategori... (Enter)" autocomplete="off">
                <kbd class="exec-search-kbd">⌘K</kbd>
            </form>
            <a href="customer_add.php" class="exec-btn exec-btn-primary">
                <i class="bi bi-plus-lg"></i>
                <span>Tambah Customer</span>
            </a>
            <a href="sales_assignment.php" class="exec-btn exec-btn-secondary">
                <i class="bi bi-person-gear"></i>
                <span>Penugasan Sales</span>
            </a>
        </div>
    </div>
</div>

<?php include 'includes/announcement_widget.php'; ?>
<?php // include 'includes/competition_unified_tabs.php'; // Dinonaktifkan: Program kompetisi per semester & bonus sultan telah selesai ?>
<?php include 'includes/analytics_bento_widget.php'; ?>

<!-- 3 Stat Cards (Integrated into Analytics Bento above) -->
<?php /*
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
*/ ?>

<!-- Operational Clusters (Taste-Skill Compliant) -->
<div class="exec-cluster-wrap">
    <!-- Cluster 1: Manajemen Toko & Pelanggan -->
    <div class="exec-cluster-header">
        <h4 class="exec-cluster-title">
            <i class="bi bi-shop text-primary"></i>
            <span>Operasional Toko &amp; Pelanggan</span>
        </h4>
        <span class="exec-cluster-tag">4 Modul Terintegrasi</span>
    </div>
    <div class="exec-card-grid">
        <a href="index.php" class="exec-card-link">
            <div class="exec-card">
                <div>
                    <div class="exec-card-top">
                        <div class="exec-card-icon-wrap"><i class="bi bi-people"></i></div>
                        <span class="exec-card-badge"><?php echo number_format($totalCustomers, 0, ',', '.'); ?> Toko</span>
                    </div>
                    <h5 class="exec-card-title">Daftar Customer</h5>
                    <p class="exec-card-desc">Database seluruh toko, verifikasi alamat, dan status relasi kemitraan.</p>
                </div>
                <div class="exec-card-action">Buka Direktori <i class="bi bi-arrow-right"></i></div>
            </div>
        </a>

        <a href="kandidat_customer.php" class="exec-card-link">
            <div class="exec-card">
                <div>
                    <div class="exec-card-top">
                        <div class="exec-card-icon-wrap"><i class="bi bi-person-badge"></i></div>
                        <span class="exec-card-badge text-primary bg-primary-subtle border-primary-subtle">Prioritas Closing</span>
                    </div>
                    <h5 class="exec-card-title">Prospek &amp; Potensial</h5>
                    <p class="exec-card-desc">Kandidat toko prospektif dengan estimasi peluang transaksi tinggi.</p>
                </div>
                <div class="exec-card-action">Tinjau Prospek <i class="bi bi-arrow-right"></i></div>
            </div>
        </a>

        <a href="customer_add.php" class="exec-card-link">
            <div class="exec-card">
                <div>
                    <div class="exec-card-top">
                        <div class="exec-card-icon-wrap"><i class="bi bi-person-plus"></i></div>
                        <span class="exec-card-badge">Formulir Cepat</span>
                    </div>
                    <h5 class="exec-card-title">Registrasi Toko Baru</h5>
                    <p class="exec-card-desc">Input data identitas mitra, kategori harga, dan penetapan wilayah sales.</p>
                </div>
                <div class="exec-card-action">Registrasi Baru <i class="bi bi-arrow-right"></i></div>
            </div>
        </a>

        <a href="customer_io.php" class="exec-card-link">
            <div class="exec-card">
                <div>
                    <div class="exec-card-top">
                        <div class="exec-card-icon-wrap"><i class="bi bi-cloud-arrow-up"></i></div>
                        <span class="exec-card-badge">Format .XLSX</span>
                    </div>
                    <h5 class="exec-card-title">Import Massal Toko</h5>
                    <p class="exec-card-desc">Unggah file spreadsheet Excel untuk update atau penambahan database massal.</p>
                </div>
                <div class="exec-card-action">Mulai Import <i class="bi bi-arrow-right"></i></div>
            </div>
        </a>
    </div>

    <!-- Cluster 2: Intelijen Penjualan & Sales Tools -->
    <div class="exec-cluster-header mt-4">
        <h4 class="exec-cluster-title">
            <i class="bi bi-graph-up-arrow text-info"></i>
            <span>Intelijen Penjualan &amp; Alat Bantu</span>
        </h4>
        <span class="exec-cluster-tag">4 Modul Terintegrasi</span>
    </div>
    <div class="exec-card-grid">
        <a href="customer_bulk_download_process.php" class="exec-card-link">
            <div class="exec-card">
                <div>
                    <div class="exec-card-top">
                        <div class="exec-card-icon-wrap"><i class="bi bi-cloud-arrow-down"></i></div>
                        <span class="exec-card-badge">Cadangan Data</span>
                    </div>
                    <h5 class="exec-card-title">Export Data Pelanggan</h5>
                    <p class="exec-card-desc">Unduh rekapitulasi data toko dan histori aktivitas ke format Excel.</p>
                </div>
                <div class="exec-card-action">Unduh File <i class="bi bi-arrow-right"></i></div>
            </div>
        </a>

        <a href="customer_quality_check.php" class="exec-card-link">
            <div class="exec-card">
                <div>
                    <div class="exec-card-top">
                        <div class="exec-card-icon-wrap"><i class="bi bi-clipboard2-check"></i></div>
                        <span class="exec-card-badge">Audit Integritas</span>
                    </div>
                    <h5 class="exec-card-title">Audit Kualitas Data</h5>
                    <p class="exec-card-desc">Validasi nomor kontak WhatsApp, kelengkapan alamat, dan cegah duplikasi.</p>
                </div>
                <div class="exec-card-action">Mulai Audit <i class="bi bi-arrow-right"></i></div>
            </div>
        </a>

        <a href="invoice_followup_report.php" class="exec-card-link">
            <div class="exec-card">
                <div>
                    <div class="exec-card-top">
                        <div class="exec-card-icon-wrap"><i class="bi bi-receipt"></i></div>
                        <span class="exec-card-badge text-success bg-success-subtle border-success-subtle">173 Terverifikasi</span>
                    </div>
                    <h5 class="exec-card-title">Rekap Tagihan &amp; Invoice</h5>
                    <p class="exec-card-desc">Laporan invoice penjualan riil, realisasi omset, dan log status penagihan.</p>
                </div>
                <div class="exec-card-action">Tinjau Rekap <i class="bi bi-arrow-right"></i></div>
            </div>
        </a>

        <a href="sales_qa.php" class="exec-card-link">
            <div class="exec-card">
                <div>
                    <div class="exec-card-top">
                        <div class="exec-card-icon-wrap"><i class="bi bi-cpu"></i></div>
                        <span class="exec-card-badge">AI Copilot</span>
                    </div>
                    <h5 class="exec-card-title">Asisten Loewix AI</h5>
                    <p class="exec-card-desc">Konsultasi taktik negosiasi sales, kalkulasi margin, dan rekomendasi produk.</p>
                </div>
                <div class="exec-card-action">Buka Asisten AI <i class="bi bi-arrow-right"></i></div>
            </div>
        </a>
    </div>
</div>

<script>
document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        const input = document.getElementById('execOmniInput');
        if (input) {
            input.focus();
            input.select();
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>