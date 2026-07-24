<?php
require_once 'auth.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$userInitials = strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 2));
$userName = htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User');
$userRole = $_SESSION['role'] ?? '';

// Live Notifications Calculation
$notif_pending_fu = 0;
$notif_kandidat = 0;
$notif_maintenance = 0;

if (isset($conn) && isset($_SESSION['user_id'])) {
    $n_sales_where = ($_SESSION['role'] === 'sales') ? " AND sales_id = " . intval($_SESSION['user_id']) : "";
    
    $rn1 = $conn->query("SELECT COUNT(*) as t FROM customers WHERE status_fu = 'Pending' AND deleted_at IS NULL {$n_sales_where}");
    if ($rn1) $notif_pending_fu = $rn1->fetch_assoc()['t'] ?? 0;
    
    $rn2 = $conn->query("SELECT COUNT(*) as t FROM customers WHERE kandidat = 'Y' AND deleted_at IS NULL {$n_sales_where}");
    if ($rn2) $notif_kandidat = $rn2->fetch_assoc()['t'] ?? 0;

    $rn3 = $conn->query("SELECT tlp_pic FROM customer_pics WHERE deleted_at IS NULL AND tlp_pic != '' GROUP BY tlp_pic HAVING COUNT(id) > 1");
    if ($rn3) $notif_maintenance = $rn3->num_rows;
}

$total_notif_count = $notif_pending_fu + $notif_kandidat + $notif_maintenance;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> — Loewix Sales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
<style>
/* ============ RESET & GLOBALS ============ */
*,*::before,*::after { box-sizing: border-box; }
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #F1F5F9;
    color: #1E293B;
    margin: 0;
    -webkit-font-smoothing: antialiased;
    display: flex;
    min-height: 100vh;
}
table tr td { font-size: 0.85em; }
.input-group-text { cursor: pointer; }

/* ============ SIDEBAR ============ */
.sidebar {
    width: 260px;
    min-height: 100vh;
    background: linear-gradient(180deg, #06132B 0%, #0A1E3D 50%, #0D2444 100%);
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 1040;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
    border-right: 1px solid rgba(59,130,246,0.08);
}

.sidebar-logo {
    padding: 28px 24px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    text-align: left;
    padding-left: 28px;
    position: relative;
}

.sidebar-logo::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 20%;
    width: 60%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(59,130,246,0.3), transparent);
}

.sidebar-logo img {
    height: 72px;
    width: auto;
}

.sidebar-nav {
    flex: 1;
    padding: 20px 14px;
    overflow-y: auto;
}

/* Scrollbar */
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

.nav-section-label {
    font-size: 10px;
    font-weight: 700;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    padding: 20px 14px 8px;
    font-family: 'Inter', sans-serif;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 500;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    transition: all 0.2s cubic-bezier(.4,0,.2,1);
    margin-bottom: 2px;
    position: relative;
}

.sidebar-link i {
    font-size: 17px;
    width: 22px;
    text-align: center;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.sidebar-link:hover {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.95);
    transform: translateX(2px);
}

.sidebar-link:hover i {
    color: #60A5FA;
}

.sidebar-link.active {
    background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(29,78,216,0.12));
    color: #93C5FD;
    font-weight: 600;
    box-shadow: 0 0 20px rgba(59,130,246,0.08);
}

.sidebar-link.active i {
    color: #60A5FA;
}

.sidebar-link.active::before {
    content: '';
    position: absolute;
    left: 0; top: 6px; bottom: 6px;
    width: 3px;
    border-radius: 0 4px 4px 0;
    background: linear-gradient(180deg, #3B82F6, #60A5FA);
    box-shadow: 0 0 8px rgba(59,130,246,0.4);
}

/* Sidebar dropdown */
.sidebar-dropdown-btn {
    cursor: pointer;
}

.sidebar-dropdown-btn .chevron {
    margin-left: auto;
    font-size: 12px;
    transition: transform 0.25s ease;
    color: rgba(255,255,255,0.3);
}

.sidebar-dropdown-btn.open .chevron {
    transform: rotate(180deg);
    color: #60A5FA;
}

.sidebar-submenu {
    display: none;
    padding-left: 20px;
    margin-top: 2px;
}

.sidebar-submenu.show { display: block; }

.sidebar-submenu .sidebar-link {
    font-size: 13px;
    padding: 8px 14px;
    color: rgba(255,255,255,0.4);
}

.sidebar-submenu .sidebar-link:hover {
    color: rgba(255,255,255,0.85);
}

/* Sidebar footer */
.sidebar-footer {
    padding: 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    position: relative;
}

.sidebar-footer::before {
    content: '';
    position: absolute;
    top: -1px;
    left: 20%;
    width: 60%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(59,130,246,0.2), transparent);
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    text-decoration: none;
    transition: all 0.2s ease;
}

.sidebar-user:hover {
    background: rgba(255,255,255,0.08);
    border-color: rgba(255,255,255,0.1);
    transform: translateY(-1px);
}

.sidebar-user-avatar {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(59,130,246,0.3);
}

.sidebar-user-info {
    flex: 1;
    min-width: 0;
}

.sidebar-user-name {
    font-size: 13px;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-user-role {
    font-size: 11px;
    color: rgba(255,255,255,0.4);
    font-weight: 500;
    text-transform: capitalize;
}

/* ============ MAIN WRAPPER ============ */
.main-wrapper {
    margin-left: 260px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ============ TOP BAR ============ */
.topbar {
    height: 64px;
    background: #FFFFFF;
    border-bottom: 1px solid #E8ECF1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    position: sticky;
    top: 0;
    z-index: 1030;
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
@media (max-width: 991px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main-wrapper { margin-left: 0; }

}

@media (max-width: 576px) {
    .content-area { padding: 20px 16px; }
    .topbar { padding: 0 16px; }

}
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="assets/images/loewix_sales_logo_white.png" alt="Loewix Sales">
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu</div>

        <?php if ($userRole == 'adminsales'): ?>
            <a href="promosi_management.php" class="sidebar-link <?php echo $currentPage=='promosi_management.php'?'active':''; ?>">
                <i class="bi bi-tags-fill"></i> Promosi
            </a>
            <a href="sales_ads.php" class="sidebar-link <?php echo $currentPage=='sales_ads.php'?'active':''; ?>">
                <i class="bi bi-cart-check-fill"></i> Laporan Ads
            </a>
            <a href="ads_report.php" class="sidebar-link <?php echo $currentPage=='ads_report.php'?'active':''; ?>">
                <i class="bi bi-bar-chart-line-fill"></i> Report Saldo & Ads
            </a>
        <?php else: ?>
            <a href="customer_management.php" class="sidebar-link <?php echo $currentPage=='customer_management.php'?'active':''; ?>">
                <i class="bi bi-grid-1x2-fill"></i> Customer Management
            </a>
            <?php if ($userRole == 'superadmin'): ?>
            <a href="followup_report.php" class="sidebar-link <?php echo $currentPage=='followup_report.php'?'active':''; ?>">
                <i class="bi bi-journal-check"></i> Follow Up Report
            </a>
            <?php endif; ?>
            <a href="sales_management.php" class="sidebar-link <?php echo in_array($currentPage,['sales_management.php'])?'active':''; ?>">
                <i class="bi bi-graph-up-arrow"></i> Sales Performance
            </a>
            <?php if ($userRole == 'superadmin'): ?>
            <a href="sales_assignment.php" class="sidebar-link <?php echo $currentPage=='sales_assignment.php'?'active':''; ?>">
                <i class="bi bi-people-fill"></i> Sales Management
            </a>
            <?php endif; ?>
            <a href="sales_qa.php" class="sidebar-link <?php echo $currentPage=='sales_qa.php'?'active':''; ?>">
                <i class="bi bi-chat-left-dots-fill"></i> Forum Q&A Sales
            </a>

            <div class="nav-section-label" style="margin-top:8px;">Tools</div>

            <div>
                <a href="#" class="sidebar-link sidebar-dropdown-btn" id="toolsDropdown">
                    <i class="bi bi-tools"></i> Sales Tools
                    <i class="bi bi-chevron-down chevron"></i>
                </a>
                <div class="sidebar-submenu" id="toolsSubmenu">
                    <a href="broadcast_schedule.php" class="sidebar-link <?php echo $currentPage=='broadcast_schedule.php'?'active':''; ?>">
                        <i class="bi bi-megaphone"></i> Broadcast
                    </a>
                    <a href="promosi_management.php" class="sidebar-link <?php echo ($currentPage=='promosi_management.php' && $userRole!='adminsales')?'active':''; ?>">
                        <i class="bi bi-tags"></i> Promosi
                    </a>
                    <a href="price_list.php" class="sidebar-link <?php echo $currentPage=='price_list.php'?'active':''; ?>">
                        <i class="bi bi-ui-checks"></i> Price List
                    </a>
                    <a href="calculator_sales.php" class="sidebar-link <?php echo $currentPage=='calculator_sales.php'?'active':''; ?>">
                        <i class="bi bi-calculator"></i> Kalkulator Sales
                    </a>
                    <a href="online_tools.php" class="sidebar-link <?php echo $currentPage=='online_tools.php'?'active':''; ?>">
                        <i class="bi bi-123"></i> Kalkulator Online
                    </a>
                    <?php if (in_array($userRole, ['superadmin', 'adminsales'])): ?>
                    <a href="sales_ads.php" class="sidebar-link <?php echo ($currentPage=='sales_ads.php' && $userRole!='adminsales')?'active':''; ?>">
                        <i class="bi bi-cart-check"></i> Laporan Ads
                    </a>
                    <a href="ads_report.php" class="sidebar-link <?php echo ($currentPage=='ads_report.php' && $userRole!='adminsales')?'active':''; ?>">
                        <i class="bi bi-bar-chart-line"></i> Report Saldo & Ads
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nav-section-label" style="margin-top:8px;">Akun</div>
            <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                <i class="bi bi-key-fill"></i> Ganti Password
            </a>
            <a href="logout.php" class="sidebar-link" style="color:rgba(248,113,113,0.7);">
                <i class="bi bi-box-arrow-left"></i> Logout
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?php echo $userInitials; ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo $userName; ?></div>
                <div class="sidebar-user-role"><?php echo $userRole; ?></div>
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
            </div>
        </div>
        <div class="topbar-actions">
            <!-- Notification Dropdown -->
            <div class="dropdown position-relative">
                <button class="topbar-btn border-0 shadow-sm" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi Sistem" style="outline:none; position:relative; background:#FFFFFF; border:1px solid #E2E8F0; width:42px; height:42px; border-radius:12px;">
                    <i class="bi bi-bell-fill text-warning" style="font-size:18px;"></i>
                    <?php if ($total_notif_count > 0): ?>
                        <span class="position-absolute translate-middle badge rounded-pill bg-danger border border-white" style="top: 2px; right: -12px; font-size: 10px; font-weight:800; padding: 3px 7px; box-shadow:0 3px 8px rgba(239,68,68,0.4);">
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
});
</script>