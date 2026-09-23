<?php
require_once 'auth.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$userInitials = strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 2));
$userName = htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User');
$userRole = $_SESSION['role'] ?? '';
$userRoleClean = strtolower(trim($userRole));

// Role Badge Color Mapping
$roleBadgeBg = match($userRoleClean) {
    'superadmin' => 'background: rgba(245, 158, 11, 0.18); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.4);',
    'adminsales' => 'background: rgba(168, 85, 247, 0.18); color: #D8B4FE; border: 1px solid rgba(168, 85, 247, 0.4);',
    'admin'      => 'background: rgba(59, 130, 246, 0.18); color: #93C5FD; border: 1px solid rgba(59, 130, 246, 0.4);',
    'manager'    => 'background: rgba(245, 158, 11, 0.18); color: #FDE68A; border: 1px solid rgba(245, 158, 11, 0.4);',
    'finance'    => 'background: rgba(6, 182, 212, 0.18); color: #67E8F9; border: 1px solid rgba(6, 182, 212, 0.4);',
    default      => 'background: rgba(16, 185, 129, 0.18); color: #6EE7B7; border: 1px solid rgba(16, 185, 129, 0.4);'
};

// Live Notifications Calculation (Cached for 30s for ultra-fast performance)
$notif_pending_fu = 0;
$notif_kandidat = 0;
$notif_maintenance = 0;

if (isset($conn) && isset($_SESSION['user_id'])) {
    $now_ts = time();
    if (!isset($_SESSION['notif_cache_time']) || ($now_ts - $_SESSION['notif_cache_time']) > 30 || isset($_GET['refresh_notif'])) {
        $n_sales_where = ($_SESSION['role'] === 'sales') ? " AND sales_id = " . intval($_SESSION['user_id']) : "";
        
        $rn1 = $conn->query("SELECT COUNT(*) as t FROM customers WHERE status_fu = 'Pending' AND deleted_at IS NULL {$n_sales_where}");
        if ($rn1) $notif_pending_fu = $rn1->fetch_assoc()['t'] ?? 0;
        
        $rn2 = $conn->query("SELECT COUNT(*) as t FROM customers WHERE kandidat = 'Y' AND deleted_at IS NULL {$n_sales_where}");
        if ($rn2) $notif_kandidat = $rn2->fetch_assoc()['t'] ?? 0;

        $rn3 = $conn->query("SELECT tlp_pic FROM customer_pics WHERE deleted_at IS NULL AND tlp_pic != '' GROUP BY tlp_pic HAVING COUNT(id) > 1 LIMIT 100");
        if ($rn3) $notif_maintenance = $rn3->num_rows;

        $_SESSION['notif_cache_time'] = $now_ts;
        $_SESSION['notif_pending_fu'] = $notif_pending_fu;
        $_SESSION['notif_kandidat'] = $notif_kandidat;
        $_SESSION['notif_maintenance'] = $notif_maintenance;
    } else {
        $notif_pending_fu = $_SESSION['notif_pending_fu'] ?? 0;
        $notif_kandidat = $_SESSION['notif_kandidat'] ?? 0;
        $notif_maintenance = $_SESSION['notif_maintenance'] ?? 0;
    }
}

$total_notif_count = $notif_pending_fu + $notif_kandidat + $notif_maintenance;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> — Loewix Sales</title>
    <!-- Loewix Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=2">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico?v=2">
    <link rel="apple-touch-icon" href="assets/images/favicon.png?v=2">
    <script>
    (function() {
        var savedTheme = localStorage.getItem('loewix_theme');
        if (savedTheme && savedTheme !== 'default') {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
        var savedSidebar = localStorage.getItem('loewix_sidebar');
        if (savedSidebar && savedSidebar !== 'default') {
            document.documentElement.setAttribute('data-sidebar', savedSidebar);
        } else if (savedTheme === 'solar-yellow') {
            document.documentElement.setAttribute('data-sidebar', 'yellow');
        }
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
<style>
/* ============ RESET & TYPOGRAPHY SYSTEM ============ */
*,*::before,*::after { box-sizing: border-box; }
body {
    font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #F1F5F9;
    color: #1E293B;
    margin: 0;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
    display: flex;
    min-height: 100vh;
}

h1, h2, h3, h4, h5, h6, .welcome-title, .section-title, .mc-title, .card-title, .modal-title {
    font-family: 'Outfit', 'Plus Jakarta Sans', -apple-system, sans-serif;
    letter-spacing: -0.025em;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
}

.font-monospace {
    font-family: 'JetBrains Mono', 'Plus Jakarta Sans', monospace !important;
    font-feature-settings: 'tnum' on, 'lnum' on;
}
table tr td { font-size: 0.85em; }
.input-group-text { cursor: pointer; }

/* ============ SIDEBAR PRO ============ */
.sidebar {
    width: 260px;
    min-height: 100vh;
    background: #091124;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 1040;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.4);
}

.sidebar-logo {
    padding: 22px 18px 18px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    background: rgba(255, 255, 255, 0.015);
}

.sidebar-logo::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 10%;
    width: 80%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.4), transparent);
}

.sidebar-logo img {
    height: 72px;
    max-width: 200px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.5));
    transition: transform 0.25s ease;
}
.sidebar-logo img:hover {
    transform: scale(1.03);
}

.sidebar-nav {
    flex: 1;
    padding: 16px 12px;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Scrollbar */
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 4px; }
.sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

/* Section Titles with glowing dots */
.nav-section-title {
    font-size: 10.5px;
    font-weight: 800;
    color: #94A3B8;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 14px 10px 6px;
    display: flex;
    align-items: center;
    gap: 7px;
    font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
}
.nav-section-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.dot-blue   { background: #38BDF8; box-shadow: 0 0 8px #38BDF8; }
.dot-cyan   { background: #06B6D4; box-shadow: 0 0 8px #06B6D4; }
.dot-purple { background: #A855F7; box-shadow: 0 0 8px #A855F7; }
.dot-amber  { background: #F59E0B; box-shadow: 0 0 8px #F59E0B; }
.dot-rose   { background: #F43F5E; box-shadow: 0 0 8px #F43F5E; }

/* Links */
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #94A3B8;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    margin-bottom: 3px;
    position: relative;
    border: 1px solid transparent;
}

.sidebar-link:hover {
    color: #FFFFFF;
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.08);
    transform: translateX(3px);
}

.sidebar-link.active {
    color: #FFFFFF !important;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.35) 0%, rgba(29, 78, 216, 0.2) 100%) !important;
    border-color: rgba(96, 165, 250, 0.35) !important;
    font-weight: 700;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
}

.sidebar-link.active::before {
    content: '';
    position: absolute;
    left: -2px; top: 6px; bottom: 6px;
    width: 4px;
    border-radius: 0 4px 4px 0;
    background: linear-gradient(180deg, #38BDF8, #2563EB);
    box-shadow: 0 0 10px rgba(56, 189, 248, 0.8);
}

/* Icon Badges */
.nav-icon-badge {
    width: 32px; height: 32px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s;
}

.sidebar-link:hover .nav-icon-badge {
    transform: scale(1.1);
}

.sidebar-link.active .nav-icon-badge {
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.15);
}

.nav-link-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Sidebar Dropdown */
.sidebar-dropdown-btn {
    cursor: pointer;
}
.sidebar-dropdown-btn .chevron {
    margin-left: auto;
    font-size: 11px;
    transition: transform 0.25s ease;
    color: #64748B;
}
.sidebar-dropdown-btn.open .chevron {
    transform: rotate(180deg);
    color: #38BDF8;
}
.sidebar-submenu {
    display: none;
    padding-left: 10px;
    margin-top: 2px;
}
.sidebar-submenu.show { display: block; }
.sidebar-submenu .sidebar-link {
    font-size: 12.5px;
    padding: 6px 10px;
    color: #94A3B8;
}
.sidebar-submenu .sidebar-link:hover {
    color: #FFFFFF;
}

/* Sidebar Link Logout */
.sidebar-link-logout {
    color: #F87171 !important;
}
.sidebar-link-logout:hover {
    background: rgba(239, 68, 68, 0.12) !important;
    border-color: rgba(239, 68, 68, 0.25) !important;
}

/* Sidebar Profile Footer Card */
.sidebar-profile-card {
    padding: 12px 14px;
    margin: 8px 10px 12px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
}
.sidebar-profile-card:hover {
    background: rgba(255, 255, 255, 0.07);
    border-color: rgba(255, 255, 255, 0.14);
}
.sidebar-avatar-ring {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #FFFFFF;
    font-weight: 800;
    font-size: 13px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    position: relative;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
}
.sidebar-online-indicator {
    position: absolute;
    bottom: 0; right: 0;
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #10B981;
    border: 2px solid #091124;
    box-shadow: 0 0 6px #10B981;
}
.sidebar-user-meta { min-width: 0; }
.sidebar-user-name {
    color: #FFFFFF;
    font-weight: 700;
    font-size: 13px;
    line-height: 1.2;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar-role-pill {
    font-size: 9.5px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 12px;
    display: inline-block;
    letter-spacing: 0.05em;
    line-height: 1.2;
}

/* ============ MAIN WRAPPER ============ */
.main-wrapper {
    margin-left: 260px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    max-width: calc(100vw - 260px);
    overflow-x: hidden;
}

/* ============ TOP BAR ============ */
.topbar {
    height: 64px;
    background: #FFFFFF;
    border-bottom: 1px solid #E8ECF1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    position: sticky;
    top: 0;
    z-index: 1030;
    max-width: 100%;
    box-sizing: border-box;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748B;
    font-family: 'Inter', sans-serif;
    font-size: 13.5px;
    font-weight: 500;
}

.topbar-left i {
    font-size: 15px;
    color: #94A3B8;
}

.topbar-left .topbar-date {
    color: #334155;
    font-weight: 600;
}

.topbar-left .topbar-time {
    color: #3B82F6;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

/* Right side: Actions */
.topbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.topbar-btn {
    width: 40px; height: 40px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #64748B;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 17px;
    transition: all 0.2s ease;
    position: relative;
}

.topbar-btn:hover {
    background: #F1F5F9;
    color: #3B82F6;
    border-color: #CBD5E1;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.topbar-btn .notif-dot {
    position: absolute;
    top: 8px; right: 8px;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #EF4444;
    border: 2px solid #FFFFFF;
    animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
    50% { box-shadow: 0 0 0 4px rgba(239,68,68,0); }
}

.topbar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 14px 5px 5px;
    border-radius: 14px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
}

.topbar-user:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    transform: translateY(-1px);
}

.topbar-user-avatar {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(59,130,246,0.25);
}

.topbar-user-name {
    font-size: 13px;
    font-weight: 600;
    color: #1E293B;
}

/* ============ CONTENT AREA ============ */
.content-area {
    flex: 1;
    padding: 28px 32px;
    background: #F8FAFC;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 991.98px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main-wrapper { margin-left: 0 !important; max-width: 100vw !important; width: 100% !important; }
}

@media (max-width: 576px) {
    .content-area { padding: 20px 14px; }
    .topbar { padding: 0 12px; }
}
</style>
</head>
<body>

<!-- ===== SIDEBAR PRO ===== -->
<aside class="sidebar" id="sidebar">
    <!-- Logo Header -->
    <div class="sidebar-logo">
        <a href="customer_management.php" class="d-inline-flex flex-column align-items-center text-decoration-none">
            <img src="assets/images/loewix_sales_logo_white.png" alt="Loewix Sales" onerror="this.src='assets/images/logo.png'">
        </a>
    </div>

    <!-- Navigation Scroll Area -->
    <nav class="sidebar-nav">
        
        <?php if ($userRoleClean === 'adminsales'): ?>
            <!-- ═════════════════════════════════════════════════════════ -->
            <!-- ADMIN SALES NAVIGATION                                    -->
            <!-- ═════════════════════════════════════════════════════════ -->
            <div class="nav-section-title">
                <span class="nav-section-dot dot-blue"></span>
                <span>Menu Utama</span>
            </div>

            <a href="index.php" class="sidebar-link <?php echo $currentPage=='index.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                    <i class="bi bi-trophy-fill"></i>
                </span>
                <span class="nav-link-text">Dashboard &amp; Leaderboard</span>
            </a>

            <a href="customer_management.php" class="sidebar-link <?php echo $currentPage=='customer_management.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;">
                    <i class="bi bi-grid-1x2-fill"></i>
                </span>
                <span class="nav-link-text">Dashboard Sales</span>
            </a>

            <a href="promosi_management.php" class="sidebar-link <?php echo $currentPage=='promosi_management.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(236, 72, 153, 0.15); color: #EC4899;">
                    <i class="bi bi-tags-fill"></i>
                </span>
                <span class="nav-link-text">Promosi</span>
            </a>

            <a href="sales_ads.php" class="sidebar-link <?php echo $currentPage=='sales_ads.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(249, 115, 22, 0.15); color: #F97316;">
                    <i class="bi bi-cart-check-fill"></i>
                </span>
                <span class="nav-link-text">Laporan Ads</span>
            </a>

            <a href="ads_report.php" class="sidebar-link <?php echo $currentPage=='ads_report.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(20, 184, 166, 0.15); color: #14B8A6;">
                    <i class="bi bi-bar-chart-line-fill"></i>
                </span>
                <span class="nav-link-text">Report Saldo &amp; Ads</span>
            </a>

            <a href="sales_work_plan.php" class="sidebar-link <?php echo $currentPage=='sales_work_plan.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(99, 102, 241, 0.15); color: #6366F1;">
                    <i class="bi bi-calendar-check-fill"></i>
                </span>
                <span class="nav-link-text">Rencana Kerja Sales</span>
            </a>

            <!-- APLIKASI SALES (MOBILE) -->
            <div class="nav-section-title" style="margin-top: 14px;">
                <span class="nav-section-dot dot-cyan"></span>
                <span>Aplikasi Sales Canvas (Mobile)</span>
            </div>

            <a href="modul-aplikasi-sales/kegiatan.php" class="sidebar-link <?php echo in_array($currentPage,['kegiatan.php','kegiatan-selesai.php','kegiatan-db.php'])?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0EA5E9;">
                    <i class="bi bi-grid-1x2-fill"></i>
                </span>
                <span class="nav-link-text">Dashboard Sales Canvas</span>
            </a>

            <a href="modul-aplikasi-sales/kegiatan-baru.php" class="sidebar-link <?php echo ($currentPage=='kegiatan-baru.php')?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(37, 99, 235, 0.15); color: #2563EB;">
                    <i class="bi bi-calendar-plus-fill"></i>
                </span>
                <span class="nav-link-text">Tambah Kegiatan Baru</span>
            </a>

            <a href="modul-aplikasi-sales/laporan-kegiatan.php" class="sidebar-link <?php echo in_array($currentPage,['laporan-kegiatan.php','laporan-cust.php'])?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(20, 184, 166, 0.15); color: #14B8A6;">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <span class="nav-link-text">Laporan Visit (GPS)</span>
            </a>

            <a href="modul-aplikasi-sales/tiptok.php" class="sidebar-link <?php echo $currentPage=='tiptok.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                    <i class="bi bi-box-seam-fill"></i>
                </span>
                <span class="nav-link-text">TIP TOK (Konsinyasi)</span>
            </a>

            <a href="modul-aplikasi-sales/customer.php" class="sidebar-link <?php echo in_array($currentPage,['customer.php','tambah-customer.php','edit-customer.php'])?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                    <i class="bi bi-shop-window"></i>
                </span>
                <span class="nav-link-text">Customer Toko / Dealer</span>
            </a>

        <?php else: ?>

            <!-- ═════════════════════════════════════════════════════════ -->
            <!-- 1. MENU UTAMA                                             -->
            <!-- ═════════════════════════════════════════════════════════ -->
            <div class="nav-section-title">
                <span class="nav-section-dot dot-blue"></span>
                <span>Menu Utama</span>
            </div>

            <a href="index.php" class="sidebar-link <?php echo $currentPage=='index.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                    <i class="bi bi-trophy-fill"></i>
                </span>
                <span class="nav-link-text">Dashboard &amp; Leaderboard</span>
            </a>

            <a href="customer_management.php" class="sidebar-link <?php echo $currentPage=='customer_management.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;">
                    <i class="bi bi-grid-1x2-fill"></i>
                </span>
                <span class="nav-link-text">Dashboard Sales</span>
            </a>

            <a href="sales_work_plan.php" class="sidebar-link <?php echo $currentPage=='sales_work_plan.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(99, 102, 241, 0.15); color: #6366F1;">
                    <i class="bi bi-calendar-check-fill"></i>
                </span>
                <span class="nav-link-text">Rencana Kerja Sales</span>
            </a>

            <?php if ($userRoleClean === 'superadmin'): ?>
            <a href="followup_report.php" class="sidebar-link <?php echo $currentPage=='followup_report.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                    <i class="bi bi-journal-check"></i>
                </span>
                <span class="nav-link-text">Follow Up Report</span>
            </a>
            <?php endif; ?>

            <a href="sales_management.php" class="sidebar-link <?php echo in_array($currentPage,['sales_management.php'])?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
                    <i class="bi bi-graph-up-arrow"></i>
                </span>
                <span class="nav-link-text">Data Sales (Web)</span>
            </a>

            <?php if ($userRoleClean === 'superadmin'): ?>
            <a href="sales_assignment.php" class="sidebar-link <?php echo $currentPage=='sales_assignment.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(168, 85, 247, 0.15); color: #A855F7;">
                    <i class="bi bi-people-fill"></i>
                </span>
                <span class="nav-link-text">Sales Management</span>
            </a>
            <?php endif; ?>

            <!-- ═════════════════════════════════════════════════════════ -->
            <!-- 2. APLIKASI SALES CANVAS (MOBILE)                         -->
            <!-- ═════════════════════════════════════════════════════════ -->
            <div class="nav-section-title" style="margin-top: 14px;">
                <span class="nav-section-dot dot-cyan"></span>
                <span>Aplikasi Sales Canvas (Mobile)</span>
            </div>

            <a href="modul-aplikasi-sales/kegiatan.php" class="sidebar-link <?php echo in_array($currentPage,['kegiatan.php','kegiatan-selesai.php','kegiatan-db.php'])?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0EA5E9;">
                    <i class="bi bi-grid-1x2-fill"></i>
                </span>
                <span class="nav-link-text">Dashboard Sales Canvas</span>
            </a>

            <a href="modul-aplikasi-sales/kegiatan-baru.php" class="sidebar-link <?php echo ($currentPage=='kegiatan-baru.php')?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(37, 99, 235, 0.15); color: #2563EB;">
                    <i class="bi bi-calendar-plus-fill"></i>
                </span>
                <span class="nav-link-text">Tambah Kegiatan Baru</span>
            </a>

            <a href="modul-aplikasi-sales/laporan-kegiatan.php" class="sidebar-link <?php echo in_array($currentPage,['laporan-kegiatan.php','laporan-cust.php'])?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(20, 184, 166, 0.15); color: #14B8A6;">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <span class="nav-link-text">Laporan Visit (GPS)</span>
            </a>

            <a href="modul-aplikasi-sales/tiptok.php" class="sidebar-link <?php echo $currentPage=='tiptok.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                    <i class="bi bi-box-seam-fill"></i>
                </span>
                <span class="nav-link-text">TIP TOK (Konsinyasi)</span>
            </a>

            <a href="modul-aplikasi-sales/customer.php" class="sidebar-link <?php echo in_array($currentPage,['customer.php','tambah-customer.php','edit-customer.php'])?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                    <i class="bi bi-shop-window"></i>
                </span>
                <span class="nav-link-text">Customer Toko / Dealer</span>
            </a>

            <a href="modul-aplikasi-sales/sales.php" class="sidebar-link <?php echo in_array($currentPage,['sales.php','sales-detail.php'])?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(168, 85, 247, 0.15); color: #A855F7;">
                    <i class="bi bi-person-badge-fill"></i>
                </span>
                <span class="nav-link-text">Akun &amp; Sales App</span>
            </a>

            <a href="modul-aplikasi-sales/scraping-gmaps.php" class="sidebar-link <?php echo $currentPage=='scraping-gmaps.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(244, 63, 94, 0.15); color: #F43F5E;">
                    <i class="bi bi-crosshair2"></i>
                </span>
                <span class="nav-link-text">Scraper Leads Maps</span>
            </a>

            <!-- ═════════════════════════════════════════════════════════ -->
            <!-- 3. TOOLS & UTILITIES                                      -->
            <!-- ═════════════════════════════════════════════════════════ -->
            <div class="nav-section-title" style="margin-top: 14px;">
                <span class="nav-section-dot dot-purple"></span>
                <span>Tools &amp; Pendukung</span>
            </div>

            <div>
                <a href="#" class="sidebar-link sidebar-dropdown-btn" id="toolsDropdown">
                    <span class="nav-icon-badge" style="background: rgba(139, 92, 246, 0.15); color: #8B5CF6;">
                        <i class="bi bi-tools"></i>
                    </span>
                    <span class="nav-link-text">Sales Tools</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </a>
                <div class="sidebar-submenu" id="toolsSubmenu">
                    <a href="announcements.php" class="sidebar-link <?php echo $currentPage=='announcements.php'?'active':''; ?>">
                        <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; width:26px; height:26px; font-size:12px;">
                            <i class="bi bi-megaphone-fill"></i>
                        </span>
                        <span class="nav-link-text">Pengumuman</span>
                    </a>
                    <a href="broadcast_schedule.php" class="sidebar-link <?php echo $currentPage=='broadcast_schedule.php'?'active':''; ?>">
                        <span class="nav-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0EA5E9; width:26px; height:26px; font-size:12px;">
                            <i class="bi bi-broadcast"></i>
                        </span>
                        <span class="nav-link-text">Broadcast</span>
                    </a>
                    <a href="promosi_management.php" class="sidebar-link <?php echo ($currentPage=='promosi_management.php' && $userRoleClean!='adminsales')?'active':''; ?>">
                        <span class="nav-icon-badge" style="background: rgba(236, 72, 153, 0.15); color: #EC4899; width:26px; height:26px; font-size:12px;">
                            <i class="bi bi-tags-fill"></i>
                        </span>
                        <span class="nav-link-text">Promosi</span>
                    </a>
                    <a href="price_list.php" class="sidebar-link <?php echo $currentPage=='price_list.php'?'active':''; ?>">
                        <span class="nav-icon-badge" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6; width:26px; height:26px; font-size:12px;">
                            <i class="bi bi-ui-checks"></i>
                        </span>
                        <span class="nav-link-text">Price List</span>
                    </a>
                    <a href="calculator_sales.php" class="sidebar-link <?php echo $currentPage=='calculator_sales.php'?'active':''; ?>">
                        <span class="nav-icon-badge" style="background: rgba(99, 102, 241, 0.15); color: #6366F1; width:26px; height:26px; font-size:12px;">
                            <i class="bi bi-calculator"></i>
                        </span>
                        <span class="nav-link-text">Kalkulator Sales</span>
                    </a>
                    <a href="online_tools.php" class="sidebar-link <?php echo $currentPage=='online_tools.php'?'active':''; ?>">
                        <span class="nav-icon-badge" style="background: rgba(139, 92, 246, 0.15); color: #8B5CF6; width:26px; height:26px; font-size:12px;">
                            <i class="bi bi-123"></i>
                        </span>
                        <span class="nav-link-text">Kalkulator Online</span>
                    </a>
                    <?php if (in_array($userRoleClean, ['superadmin', 'adminsales'])): ?>
                    <a href="sales_ads.php" class="sidebar-link <?php echo ($currentPage=='sales_ads.php' && $userRoleClean!='adminsales')?'active':''; ?>">
                        <span class="nav-icon-badge" style="background: rgba(249, 115, 22, 0.15); color: #F97316; width:26px; height:26px; font-size:12px;">
                            <i class="bi bi-cart-check"></i>
                        </span>
                        <span class="nav-link-text">Laporan Ads</span>
                    </a>
                    <a href="ads_report.php" class="sidebar-link <?php echo ($currentPage=='ads_report.php' && $userRoleClean!='adminsales')?'active':''; ?>">
                        <span class="nav-icon-badge" style="background: rgba(20, 184, 166, 0.15); color: #14B8A6; width:26px; height:26px; font-size:12px;">
                            <i class="bi bi-bar-chart-line"></i>
                        </span>
                        <span class="nav-link-text">Report Saldo &amp; Ads</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($userRoleClean === 'superadmin'): ?>
            <!-- ═════════════════════════════════════════════════════════ -->
            <!-- 4. KHUSUS SUPER ADMIN                                     -->
            <!-- ═════════════════════════════════════════════════════════ -->
            <div class="nav-section-title" style="margin-top: 14px;">
                <span class="nav-section-dot dot-amber"></span>
                <span style="color: #FBBF24;">Super Admin Area</span>
            </div>
            <a href="role_menu_access.php" class="sidebar-link <?php echo $currentPage=='role_menu_access.php'?'active':''; ?>">
                <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.22); color: #FBBF24;">
                    <i class="bi bi-shield-lock-fill"></i>
                </span>
                <span class="nav-link-text" style="font-weight: 700;">Akses Role Menu</span>
            </a>
            <?php endif; ?>

        <?php endif; ?>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 5. AKUN & LOGOUT                                          -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title" style="margin-top: 14px;">
            <span class="nav-section-dot dot-rose"></span>
            <span>Akun</span>
        </div>
        <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
            <span class="nav-icon-badge" style="background: rgba(148, 163, 184, 0.15); color: #94A3B8;">
                <i class="bi bi-key-fill"></i>
            </span>
            <span class="nav-link-text">Ganti Password</span>
        </a>
        <a href="logout.php" class="sidebar-link sidebar-link-logout">
            <span class="nav-icon-badge" style="background: rgba(239, 68, 68, 0.15); color: #EF4444;">
                <i class="bi bi-box-arrow-left"></i>
            </span>
            <span class="nav-link-text">Keluar (Logout)</span>
        </a>

    </nav>

    <!-- Sidebar Footer: Glassmorphism Profile Card -->
    <div class="sidebar-profile-card">
        <div class="d-flex align-items-center gap-2.5 overflow-hidden">
            <div class="sidebar-avatar-ring">
                <?php echo $userInitials; ?>
                <span class="sidebar-online-indicator"></span>
            </div>
            <div class="sidebar-user-meta overflow-hidden">
                <div class="sidebar-user-name text-truncate" title="<?php echo $userName; ?>"><?php echo $userName; ?></div>
                <span class="sidebar-role-pill text-uppercase" style="<?php echo $roleBadgeBg; ?>">
                    <?php echo htmlspecialchars($userRole); ?>
                </span>
            </div>
        </div>
    </div>
</aside>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper">
    <!-- Top Bar -->
    <header class="topbar">
        <div class="topbar-left">
            <div class="d-flex align-items-center gap-2 bg-light border px-3 py-1.5" style="border-radius:30px; font-size:13px;">
                <i class="bi bi-calendar-event text-primary"></i>
                <span class="topbar-date fw-semibold text-dark" id="liveDate">Loading...</span>
                <span style="color:#CBD5E1;">|</span>
                <i class="bi bi-clock-fill text-primary"></i>
                <span class="topbar-time fw-bold text-primary" id="liveTime">--:--</span>
                <span style="color:#CBD5E1;">|</span>
                <div class="d-inline-flex align-items-center gap-1.5" id="netSpeedContainer" title="Status & Kecepatan Jaringan Realtime">
                    <span class="network-dot-pulse fast" id="netDot"></span>
                    <i class="bi bi-wifi text-success" id="netIcon" style="font-size:12px;"></i>
                    <span class="fw-bold text-dark" id="netSpeedText" style="font-size:12px; font-family:'Plus Jakarta Sans', sans-serif;">-- ms</span>
                </div>
            </div>
        </div>
        <div class="topbar-actions">
            <!-- Theme Switcher Dropdown -->
            <div class="dropdown position-relative me-1">
                <button class="topbar-btn border-0 shadow-sm d-flex align-items-center justify-content-center" type="button" id="themeDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Ganti Mode Tema & Warna UI" style="outline:none; position:relative; background:#FFFFFF; border:1px solid #E2E8F0; width:42px; height:42px; border-radius:12px; transition:all 0.2s ease;">
                    <i class="bi bi-palette-fill text-primary" style="font-size:18px;"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="themeDropdown" style="width: 280px; border-radius: 18px; border: 1px solid #E2E8F0; padding: 12px; overflow: hidden; margin-top: 10px;">
                    <li class="px-2 py-1 mb-2 border-bottom pb-2 d-flex align-items-center justify-content-between">
                        <span class="fw-extrabold text-dark" style="font-size:13px; font-family:'Plus Jakarta Sans', sans-serif;"><i class="bi bi-palette2 me-1.5 text-primary"></i>Pilih Tema UI</span>
                        <span class="badge bg-primary-subtle text-primary fw-bold" style="font-size:10px;">8 Preset VIP</span>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between mb-1" onclick="setAppTheme('hyperion-gold')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #000000, #FFD700); display:inline-block; border:1px solid #FFD700;"></span>
                                <span class="fw-extrabold text-dark" style="font-size:12.5px;">Hyperion Gold</span>
                            </div>
                            <span class="badge text-dark font-monospace fw-extrabold" style="font-size:10px; background:#FFD700 !important;">🔥 VIP Gold</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between mb-1" onclick="setAppTheme('obsidian-diamond')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #030712, #06B6D4); display:inline-block; border:1px solid #06B6D4;"></span>
                                <span class="fw-extrabold text-dark" style="font-size:12.5px;">Obsidian Diamond</span>
                            </div>
                            <span class="badge text-white font-monospace fw-bold" style="font-size:10px; background:#0891B2 !important;">💎 Cyan Dark</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between mb-1" onclick="setAppTheme('crimson-velvet')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #0F050D, #E11D48); display:inline-block; border:1px solid #E11D48;"></span>
                                <span class="fw-extrabold text-dark" style="font-size:12.5px;">Crimson Velvet</span>
                            </div>
                            <span class="badge text-white font-monospace fw-bold" style="font-size:10px; background:#E11D48 !important;">🍷 Rose Burgundy</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between mb-1" onclick="setAppTheme('solar-yellow')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #854D0E, #FFFF00); display:inline-block; border:1px solid rgba(0,0,0,0.2);"></span>
                                <span class="fw-bold" style="font-size:12.5px;">Solar Yellow</span>
                            </div>
                            <span class="badge text-dark border font-monospace fw-extrabold" style="font-size:10px; background:#FFFF00 !important; color:#000 !important;">#FFFF00</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between mb-1" onclick="setAppTheme('default')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #0F172A, #2563EB); display:inline-block; border:1px solid rgba(0,0,0,0.1);"></span>
                                <span class="fw-bold" style="font-size:12.5px;">Royal Sapphire</span>
                            </div>
                            <span class="badge bg-light text-muted border font-monospace" style="font-size:10px;">Default</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between mb-1" onclick="setAppTheme('cyber-dark')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #0B0F19, #3B82F6); display:inline-block; border:1px solid rgba(255,255,255,0.2);"></span>
                                <span class="fw-bold" style="font-size:12.5px;">Cyber Executive</span>
                            </div>
                            <span class="badge bg-dark text-white border border-secondary font-monospace" style="font-size:10px;">Dark Mode</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between mb-1" onclick="setAppTheme('emerald-luxury')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #064E3B, #10B981); display:inline-block; border:1px solid rgba(0,0,0,0.1);"></span>
                                <span class="fw-bold" style="font-size:12.5px;">Emerald Luxury</span>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace" style="font-size:10px;">Forest Teal</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between mb-1" onclick="setAppTheme('royal-nebula')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #311042, #7C3AED); display:inline-block; border:1px solid rgba(0,0,0,0.1);"></span>
                                <span class="fw-bold" style="font-size:12.5px;">Royal Nebula</span>
                            </div>
                            <span class="badge bg-purple-subtle text-purple border font-monospace" style="font-size:10px; background:#F3E8FF; color:#6B21A8;">Violet Purple</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item rounded-3 py-2 px-2.5 d-flex align-items-center justify-content-between" onclick="setAppTheme('sunset-amber')">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:14px; height:14px; border-radius:50%; background:linear-gradient(135deg, #451A03, #EA580C); display:inline-block; border:1px solid rgba(0,0,0,0.1);"></span>
                                <span class="fw-bold" style="font-size:12.5px;">Sunset Amber</span>
                            </div>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace" style="font-size:10px;">Warm Gold</span>
                        </button>
                    </li>
                    <li class="px-2 py-1 mt-2 mb-2 border-top border-bottom pt-2 pb-2 d-flex align-items-center justify-content-between">
                        <span class="fw-extrabold text-dark" style="font-size:13px; font-family:'Plus Jakarta Sans', sans-serif;"><i class="bi bi-layout-sidebar-inset me-1.5 text-warning"></i>Warna Sidebar</span>
                        <span class="badge bg-warning-subtle text-dark fw-bold" style="font-size:10px;">Custom</span>
                    </li>
                    <div class="d-flex align-items-center justify-content-between px-1 gap-1.5 my-1">
                        <button type="button" class="btn btn-sm shadow-sm flex-grow-1 d-flex align-items-center justify-content-center" onclick="setSidebarColor('yellow')" title="Sidebar Kuning (#FFFF00)" style="background:#FFE600; border:1px solid #CA8A04; height:32px; border-radius:10px;">
                            <span class="fw-extrabold text-dark" style="font-size:11px;">💛</span>
                        </button>
                        <button type="button" class="btn btn-sm shadow-sm flex-grow-1 d-flex align-items-center justify-content-center" onclick="setSidebarColor('default')" title="Sidebar Dark Navy (Default)" style="background:#0F172A; border:1px solid #1E293B; height:32px; border-radius:10px; color:#FFF;">
                            <span style="font-size:11px;">🖤</span>
                        </button>
                        <button type="button" class="btn btn-sm shadow-sm flex-grow-1 d-flex align-items-center justify-content-center" onclick="setSidebarColor('white')" title="Sidebar Putih Bersih" style="background:#FFFFFF; border:1px solid #CBD5E1; height:32px; border-radius:10px; color:#000;">
                            <span style="font-size:11px;">🤍</span>
                        </button>
                        <button type="button" class="btn btn-sm shadow-sm flex-grow-1 d-flex align-items-center justify-content-center" onclick="setSidebarColor('emerald')" title="Sidebar Hijau Emerald" style="background:#059669; border:1px solid #047857; height:32px; border-radius:10px; color:#FFF;">
                            <span style="font-size:11px;">💚</span>
                        </button>
                        <button type="button" class="btn btn-sm shadow-sm flex-grow-1 d-flex align-items-center justify-content-center" onclick="setSidebarColor('violet')" title="Sidebar Ungu Violet" style="background:#7C3AED; border:1px solid #6D28D9; height:32px; border-radius:10px; color:#FFF;">
                            <span style="font-size:11px;">💜</span>
                        </button>
                    </div>
                </ul>
            </div>

            <!-- Notification Dropdown -->
            <div class="dropdown position-relative">
                <button class="topbar-btn border-0 shadow-sm" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi Sistem" style="outline:none; position:relative; background:#FFFFFF; border:1px solid #E2E8F0; width:42px; height:42px; border-radius:12px;">
                    <i class="bi bi-bell-fill text-warning" style="font-size:18px;"></i>
                    <?php if ($total_notif_count > 0): ?>
                        <span class="position-absolute badge rounded-pill bg-danger border border-white" style="top: -4px; right: -4px; font-size: 10px; font-weight:800; padding: 3px 6px; box-shadow:0 3px 8px rgba(239,68,68,0.4);">
                            <?php echo ($total_notif_count > 99) ? '99+' : $total_notif_count; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="notifDropdown" style="width: 320px; border-radius: 16px; border: 1px solid #E2E8F0; padding: 0; overflow: hidden; margin-top: 10px;">
                    <li class="p-3 bg-dark text-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold" style="font-size:14px; font-family:'Plus Jakarta Sans', sans-serif;"><i class="bi bi-bell-fill me-2 text-warning"></i>Notifikasi System</span>
                        <span class="badge bg-primary rounded-pill"><?php echo $total_notif_count; ?> Baru</span>
                    </li>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php if ($notif_pending_fu > 0): ?>
                        <li>
                            <a class="dropdown-item p-3 border-bottom d-flex align-items-start gap-3" href="followup_report.php" style="white-space: normal;">
                                <div class="bg-warning-subtle text-warning p-2 rounded-circle flex-shrink-0" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
                                    <i class="bi bi-clock-history fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:13px;">Follow Up Pending</div>
                                    <div class="small text-muted"><?php echo $notif_pending_fu; ?> Customer butuh tindak lanjut follow up.</div>
                                </div>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($notif_kandidat > 0): ?>
                        <li>
                            <a class="dropdown-item p-3 border-bottom d-flex align-items-start gap-3" href="kandidat_customer.php" style="white-space: normal;">
                                <div class="bg-primary-subtle text-primary p-2 rounded-circle flex-shrink-0" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
                                    <i class="bi bi-star-fill fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:13px;">Kandidat Potensial</div>
                                    <div class="small text-muted"><?php echo $notif_kandidat; ?> Customer kandidat perlu ditinjau.</div>
                                </div>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($notif_maintenance > 0): ?>
                        <li>
                            <a class="dropdown-item p-3 border-bottom d-flex align-items-start gap-3" href="customer_maintenance.php" style="white-space: normal;">
                                <div class="bg-danger-subtle text-danger p-2 rounded-circle flex-shrink-0" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
                                    <i class="bi bi-tools fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:13px;">Perbaikan Data</div>
                                    <div class="small text-muted"><?php echo $notif_maintenance; ?> Nomor duplikat/salah format terdeteksi.</div>
                                </div>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($total_notif_count == 0): ?>
                        <li class="p-4 text-center text-muted">
                            <i class="bi bi-check-circle-fill text-success fs-3"></i>
                            <div class="small mt-2 fw-semibold">Tidak ada notifikasi baru saat ini</div>
                        </li>
                        <?php endif; ?>
                    </div>
                    <li class="p-2 bg-light text-center border-top">
                        <a href="customer_management.php" class="small fw-bold text-primary text-decoration-none">Lihat Dashboard Utama →</a>
                    </li>
                </ul>
            </div>
            <div class="dropdown">
                <a class="topbar-user dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" style="text-decoration:none;">
                    <div class="topbar-user-avatar"><?php echo $userInitials; ?></div>
                    <span class="topbar-user-name"><?php echo $userName; ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="border:1px solid #E2E8F0;border-radius:12px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.1);padding:8px;">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal" style="font-size:13px;padding:8px 14px;border-radius:8px;"><i class="bi bi-key me-2"></i>Ganti Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php" style="font-size:13px;padding:8px 14px;border-radius:8px;"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <main class="content-area">

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
      <div class="modal-header" style="background:#0F172A; color:#FFF;">
        <h5 class="modal-title fw-bold" id="changePasswordModalLabel" style="font-size:16px;"><i class="bi bi-key-fill text-primary me-2"></i>Ganti Password</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form id="changePasswordForm">
            <div id="passwordChangeAlert" class="alert d-none" role="alert"></div>
            <div class="mb-3">
                <label for="old_password" class="form-label">Password Lama</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="old_password" name="old_password" required placeholder="Masukkan password saat ini">
                    <span class="input-group-text toggle-password" style="cursor:pointer;"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">Password Baru</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Minimal 6 karakter">
                    <span class="input-group-text toggle-password" style="cursor:pointer;"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Ulangi password baru">
                    <span class="input-group-text toggle-password" style="cursor:pointer;"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" form="changePasswordForm" class="btn btn-primary"><i class="bi bi-check-circle-fill me-1"></i> Simpan Password Baru</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(item => {
        item.addEventListener('click', function () {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        });
    });

    // Sidebar dropdown toggle
    const toolsBtn = document.getElementById('toolsDropdown');
    const toolsSub = document.getElementById('toolsSubmenu');
    if (toolsBtn && toolsSub) {
        // Auto-open if a submenu item is active
        if (toolsSub.querySelector('.sidebar-link.active')) {
            toolsSub.classList.add('show');
            toolsBtn.classList.add('open');
        }
        toolsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            toolsSub.classList.toggle('show');
            this.classList.toggle('open');
        });
    }

    // Change password form
    const passwordForm = document.getElementById('changePasswordForm');
    if (passwordForm) {
        const alertBox = document.getElementById('passwordChangeAlert');
        const modalEl = document.getElementById('changePasswordModal');
        const modal = new bootstrap.Modal(modalEl);

        passwordForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            alertBox.className = 'alert d-none';

            if (formData.get('new_password') !== formData.get('confirm_password')) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Konfirmasi password baru tidak cocok.';
                return;
            }

            fetch('change_password.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alertBox.className = 'alert alert-success';
                    alertBox.textContent = data.message;
                    passwordForm.reset();
                    setTimeout(() => { modal.hide(); alertBox.className = 'alert d-none'; }, 2000);
                } else {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = data.message;
                }
            })
            .catch(() => {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Terjadi kesalahan jaringan.';
            });
        });
    }

    // Mobile sidebar toggle
    const toggler = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggler) {
        toggler.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // Live Clock & Date
    function updateDateTime() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const mins = String(now.getMinutes()).padStart(2, '0');
        
        const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        
        const timeEl = document.getElementById('liveTime');
        const dateEl = document.getElementById('liveDate');
        
        if (timeEl) timeEl.textContent = hours + ':' + mins;
        if (dateEl) dateEl.textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Realtime Minimalist Network Speed Monitor
    function checkNetworkSpeed() {
        const startTime = performance.now();
        fetch('assets/css/style.css?ping=' + Date.now(), { method: 'HEAD', cache: 'no-store' })
            .then(res => {
                const latency = Math.round(performance.now() - startTime);
                updateNetUI(latency, true);
            })
            .catch(() => {
                updateNetUI(0, false);
            });
    }

    function updateNetUI(latency, isOnline) {
        const netIcon = document.getElementById('netIcon');
        const netText = document.getElementById('netSpeedText');
        const netDot = document.getElementById('netDot');
        if (!netText || !netDot) return;

        if (!isOnline || !navigator.onLine) {
            netDot.className = 'network-dot-pulse offline';
            if (netIcon) netIcon.className = 'bi bi-wifi-off text-danger';
            netText.textContent = 'Offline';
            netText.className = 'fw-bold text-danger';
            return;
        }

        let speedInfo = latency + ' ms';
        let connInfo = '';
        if (navigator.connection && navigator.connection.downlink) {
            connInfo = ' (' + navigator.connection.downlink + ' Mbps)';
        }

        if (latency < 120) {
            netDot.className = 'network-dot-pulse fast';
            if (netIcon) netIcon.className = 'bi bi-wifi text-success';
            netText.className = 'fw-bold text-success';
            netText.textContent = speedInfo;
            netText.title = 'Koneksi Sangat Cepat & Stabil' + connInfo;
        } else if (latency < 350) {
            netDot.className = 'network-dot-pulse medium';
            if (netIcon) netIcon.className = 'bi bi-wifi text-warning';
            netText.className = 'fw-bold text-warning';
            netText.textContent = speedInfo;
            netText.title = 'Koneksi Cukup Stabil' + connInfo;
        } else {
            netDot.className = 'network-dot-pulse slow';
            if (netIcon) netIcon.className = 'bi bi-wifi text-danger';
            netText.className = 'fw-bold text-danger';
            netText.textContent = speedInfo;
            netText.title = 'Koneksi Lambat' + connInfo;
        }
    }

    checkNetworkSpeed();
    setInterval(checkNetworkSpeed, 4000);
    window.addEventListener('online', checkNetworkSpeed);
    window.addEventListener('offline', checkNetworkSpeed);
});

function setAppTheme(themeVal) {
    if (!themeVal || themeVal === 'default') {
        document.documentElement.removeAttribute('data-theme');
        localStorage.removeItem('loewix_theme');
    } else {
        document.documentElement.setAttribute('data-theme', themeVal);
        localStorage.setItem('loewix_theme', themeVal);
    }
    if (themeVal === 'solar-yellow') {
        setSidebarColor('yellow');
    }
}

function setSidebarColor(colorVal) {
    if (!colorVal || colorVal === 'default') {
        document.documentElement.removeAttribute('data-sidebar');
        localStorage.removeItem('loewix_sidebar');
    } else {
        document.documentElement.setAttribute('data-sidebar', colorVal);
        localStorage.setItem('loewix_sidebar', colorVal);
    }
}
</script>