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
/* ============ ANIMATIONS ============ */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInScale {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
}

@keyframes pulse-ring {
    0% { transform: scale(0.9); opacity: 0.7; }
    50% { transform: scale(1.05); opacity: 0.3; }
    100% { transform: scale(0.9); opacity: 0.7; }
}

.animate-in {
    opacity: 0;
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #1E40AF 100%);
    border-radius: 20px;
    padding: 36px 40px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    animation: fadeInScale 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 400px; height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 70%);
    animation: pulse-ring 6s ease-in-out infinite;
}

.welcome-banner::after {
    content: '';
    position: absolute;
    bottom: -30%; left: 10%;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99,102,241,0.1) 0%, transparent 70%);
    animation: pulse-ring 8s ease-in-out infinite reverse;
}

.welcome-content { position: relative; z-index: 2; }

.welcome-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    color: rgba(148,163,184,0.7);
    margin-bottom: 14px;
    font-family: 'Inter', sans-serif;
}

.welcome-breadcrumb a { color: rgba(147,197,253,0.8); text-decoration: none; }
.welcome-breadcrumb span { color: rgba(148,163,184,0.4); }

.welcome-title {
    font-size: 28px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -0.5px;
    margin: 0 0 8px 0;
    font-family: 'Inter', sans-serif;
    line-height: 1.2;
}

.welcome-subtitle {
    font-size: 14.5px;
    color: rgba(203,213,225,0.7);
    font-weight: 400;
    line-height: 1.6;
    margin: 0;
    font-family: 'Inter', sans-serif;
    max-width: 600px;
}

/* ============ STAT CARDS ============ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 36px;
}

.stat-card {
    border-radius: 20px;
    padding: 28px 30px;
    position: relative;
    overflow: hidden;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
}

.stat-card:hover {
    transform: translateY(-4px) scale(1.01);
}

/* Decorative orbs */
.stat-card::before {
    content: '';
    position: absolute;
    top: -40px; right: -30px;
    width: 140px; height: 140px;
    border-radius: 50%;
    background: rgba(255,255,255,0.07);
}

.stat-card::after {
    content: '';
    position: absolute;
    bottom: -30px; left: 20%;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
}

/* Gradient backgrounds with glow */
.stat-card.blue {
    background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
    box-shadow: 0 8px 32px -6px rgba(59,130,246,0.4);
}
.stat-card.blue:hover { box-shadow: 0 16px 48px -8px rgba(59,130,246,0.5); }

.stat-card.teal {
    background: linear-gradient(135deg, #134E4A 0%, #14B8A6 100%);
    box-shadow: 0 8px 32px -6px rgba(20,184,166,0.35);
}
.stat-card.teal:hover { box-shadow: 0 16px 48px -8px rgba(20,184,166,0.45); }

.stat-card.navy {
    background: linear-gradient(135deg, #312E81 0%, #7C3AED 100%);
    box-shadow: 0 8px 32px -6px rgba(124,58,237,0.35);
}
.stat-card.navy:hover { box-shadow: 0 16px 48px -8px rgba(124,58,237,0.45); }

/* Card top row */
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
    font-weight: 600;
    color: rgba(255,255,255,0.65);
    font-family: 'Inter', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.stat-card-icon {
    width: 46px; height: 46px;
    border-radius: 14px;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: rgba(255,255,255,0.9);
}

/* Value */
.stat-card-value {
    font-size: 38px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -1.5px;
    line-height: 1;
    font-family: 'Inter', sans-serif;
    margin-bottom: 10px;
    position: relative;
    z-index: 2;
}

/* Footer */
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
    gap: 2px;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
}

.stat-trend.up { background: rgba(52,211,153,0.2); color: #6EE7B7; }
.stat-trend.down { background: rgba(248,113,113,0.2); color: #FCA5A5; }

.stat-trend-label {
    font-size: 12px;
    color: rgba(255,255,255,0.5);
    font-weight: 500;
    font-family: 'Inter', sans-serif;
}

/* ============ SECTION HEADER ============ */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #0F172A;
    font-family: 'Inter', sans-serif;
    letter-spacing: -0.3px;
}

.section-subtitle {
    font-size: 13px;
    color: #94A3B8;
    font-weight: 500;
    margin-top: 3px;
    font-family: 'Inter', sans-serif;
}

/* ============ MENU CARDS ============ */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}

.mc-link { text-decoration: none; color: inherit; display: block; }

.mc {
    background: #FFFFFF;
    border: 1px solid #EEF2F6;
    border-radius: 18px;
    padding: 28px;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

.mc::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--mc-color, #3B82F6), var(--mc-color-end, #60A5FA));
    opacity: 0;
    transition: opacity 0.35s ease;
}

.mc:hover::before { opacity: 1; }

.mc:hover {
    border-color: transparent;
    background: #FFFFFF;
    box-shadow: 0 12px 40px -8px rgba(0,0,0,0.1), 0 4px 12px -4px rgba(0,0,0,0.04);
    transform: translateY(-5px);
}

.mc-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 18px;
    flex-shrink: 0;
    transition: transform 0.3s ease;
}

.mc:hover .mc-icon {
    transform: scale(1.08);
}

.mc-title {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
    margin-bottom: 8px;
    letter-spacing: -0.2px;
    font-family: 'Inter', sans-serif;
}

.mc-desc {
    font-size: 13px;
    color: #94A3B8;
    line-height: 1.6;
    font-weight: 400;
    flex-grow: 1;
    font-family: 'Inter', sans-serif;
}

.mc-arrow {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 18px;
    font-size: 13px;
    font-weight: 600;
    color: #3B82F6;
    transition: all 0.3s ease;
    font-family: 'Inter', sans-serif;
}

.mc:hover .mc-arrow {
    gap: 12px;
    color: #1D4ED8;
}

/* Icon backgrounds & colors - softer pastels */
.i-red    { background: linear-gradient(135deg, #FEF2F2, #FECACA); color: #DC2626; }
.i-blue   { background: linear-gradient(135deg, #EFF6FF, #BFDBFE); color: #2563EB; }
.i-cyan   { background: linear-gradient(135deg, #ECFEFF, #A5F3FC); color: #0891B2; }
.i-sky    { background: linear-gradient(135deg, #F0F9FF, #BAE6FD); color: #0284C7; }
.i-green  { background: linear-gradient(135deg, #F0FDF4, #BBF7D0); color: #16A34A; }
.i-amber  { background: linear-gradient(135deg, #FFFBEB, #FDE68A); color: #D97706; }
.i-violet { background: linear-gradient(135deg, #F5F3FF, #DDD6FE); color: #7C3AED; }
.i-slate  { background: linear-gradient(135deg, #F1F5F9, #CBD5E1); color: #334155; }

/* Menu card accent colors */
.mc-link:nth-child(1) .mc { --mc-color: #DC2626; --mc-color-end: #F87171; }
.mc-link:nth-child(2) .mc { --mc-color: #2563EB; --mc-color-end: #60A5FA; }
.mc-link:nth-child(3) .mc { --mc-color: #0891B2; --mc-color-end: #22D3EE; }
.mc-link:nth-child(4) .mc { --mc-color: #0284C7; --mc-color-end: #38BDF8; }
.mc-link:nth-child(5) .mc { --mc-color: #16A34A; --mc-color-end: #4ADE80; }
.mc-link:nth-child(6) .mc { --mc-color: #D97706; --mc-color-end: #FBBF24; }
.mc-link:nth-child(7) .mc { --mc-color: #7C3AED; --mc-color-end: #A78BFA; }
.mc-link:nth-child(8) .mc { --mc-color: #334155; --mc-color-end: #64748B; }

/* Badge */
.mc-badge {
    display: inline-block;
    font-size: 9px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    background: linear-gradient(135deg, #818CF8, #6366F1);
    color: #FFFFFF;
    margin-left: 8px;
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    box-shadow: 0 2px 6px rgba(99,102,241,0.3);
}

/* ============ RESPONSIVE ============ */
@media (max-width: 1200px) { .menu-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 991px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .menu-grid { grid-template-columns: repeat(2, 1fr); }
    .welcome-banner { padding: 28px 24px; }
    .welcome-title { font-size: 24px; }
}
@media (max-width: 576px) {
    .stats-grid, .menu-grid { grid-template-columns: 1fr; }
    .welcome-title { font-size: 20px; }
    .stat-card-value { font-size: 26px; }
    .welcome-banner { padding: 24px 20px; border-radius: 14px; }
}
</style>

<!-- WELCOME BANNER -->
<div class="welcome-banner">
    <div class="welcome-content">
        <div class="welcome-breadcrumb">
            <a href="customer_management.php">Dashboard</a>
            <span>›</span>
            Customer Management
        </div>
        <h1 class="welcome-title">Selamat Datang, <?php echo htmlspecialchars($firstName); ?> 👋</h1>
        <p class="welcome-subtitle">Kelola seluruh data customer, prospek, dan laporan tim sales Anda dalam satu dashboard terpusat.</p>
    </div>
</div>

<!-- STATS -->
<div class="stats-grid">
    <div class="stat-card blue animate-in">
        <div class="stat-card-header">
            <span class="stat-card-label">Total Customer</span>
            <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
        </div>
        <div class="stat-card-value"><?php echo number_format($totalCustomers); ?></div>
        <div class="stat-card-footer">
            <span class="stat-trend up"><i class="bi bi-arrow-up-short"></i> 12%</span>
            <span class="stat-trend-label">vs bulan lalu</span>
        </div>
    </div>
    <div class="stat-card teal animate-in">
        <div class="stat-card-header">
            <span class="stat-card-label">Pending Follow Up</span>
            <div class="stat-card-icon"><i class="bi bi-clock-history"></i></div>
        </div>
        <div class="stat-card-value"><?php echo number_format($pendingFU); ?></div>
        <div class="stat-card-footer">
            <span class="stat-trend down"><i class="bi bi-arrow-down-short"></i> 2</span>
            <span class="stat-trend-label">perlu ditindak</span>
        </div>
    </div>
    <div class="stat-card navy animate-in">
        <div class="stat-card-header">
            <span class="stat-card-label">Customer Baru</span>
            <div class="stat-card-icon"><i class="bi bi-person-plus-fill"></i></div>
        </div>
        <div class="stat-card-value"><?php echo number_format($thisMonthNew); ?></div>
        <div class="stat-card-footer">
            <span class="stat-trend up"><i class="bi bi-arrow-up-short"></i> 8%</span>
            <span class="stat-trend-label">bulan ini</span>
        </div>
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
        <div class="mc animate-in">
            <div class="mc-icon i-red"><i class="bi bi-people-fill"></i></div>
            <div class="mc-title">Daftar Customer</div>
            <div class="mc-desc">Lihat daftar customer dan detail informasi lengkap.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="kandidat_report_view.php" class="mc-link" target="_blank">
        <div class="mc animate-in">
            <div class="mc-icon i-blue"><i class="bi bi-person-check-fill"></i></div>
            <div class="mc-title">Potensial Customer</div>
            <div class="mc-desc">Customer potensial dengan peluang konversi tinggi.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="customer_add.php" class="mc-link" target="_blank">
        <div class="mc animate-in">
            <div class="mc-icon i-cyan"><i class="bi bi-person-plus-fill"></i></div>
            <div class="mc-title">Tambah Customer</div>
            <div class="mc-desc">Tambah customer baru ke dalam sistem database.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="<?php echo ($userRole == 'superadmin') ? 'customer_io.php' : 'customer_io_sales.php'; ?>" class="mc-link" target="_blank">
        <div class="mc animate-in">
            <div class="mc-icon i-sky"><i class="bi bi-cloud-upload-fill"></i></div>
            <div class="mc-title">Unggah Data Customer</div>
            <div class="mc-desc">Import data customer dari file Excel (XLSX).</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="customer_export.php" class="mc-link" target="_blank">
        <div class="mc animate-in">
            <div class="mc-icon i-green"><i class="bi bi-cloud-download-fill"></i></div>
            <div class="mc-title">Unduh Data Customer</div>
            <div class="mc-desc">Export data customer ke format file Excel.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="customer_maintenance.php" class="mc-link" target="_blank">
        <div class="mc animate-in">
            <div class="mc-icon i-amber"><i class="bi bi-clipboard-check-fill"></i></div>
            <div class="mc-title">Kualitas Data Customer</div>
            <div class="mc-desc">Audit kualitas data dan validasi format.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="invoice_followup_report.php" class="mc-link" target="_blank">
        <div class="mc animate-in">
            <div class="mc-icon i-violet"><i class="bi bi-receipt"></i></div>
            <div class="mc-title">Laporan Invoice FU</div>
            <div class="mc-desc">Laporan invoice dan riwayat follow up.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
    <a href="sales_assistant.html" target="_blank" class="mc-link">
        <div class="mc animate-in">
            <div class="mc-icon i-slate"><i class="bi bi-robot"></i></div>
            <div class="mc-title">Asisten Loewix <span class="mc-badge">Beta</span></div>
            <div class="mc-desc">AI assistant untuk menjawab pertanyaan customer.</div>
            <div class="mc-arrow">Buka <i class="bi bi-arrow-right"></i></div>
        </div>
    </a>
</div>

<?php require_once 'includes/footer.php'; ?>