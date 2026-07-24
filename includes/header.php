<?php
require_once 'auth.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$userInitials = strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 2));
$userName = htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User');
$userRole = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> — Loewix Sales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
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
    background: linear-gradient(180deg, #0B1D3A 0%, #0F2847 100%);
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 1040;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
}

.sidebar-logo {
    padding: 24px 24px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.sidebar-logo img {
    height: 52px;
    width: auto;
    filter: grayscale(1) invert(1);
    mix-blend-mode: screen;
}

.sidebar-nav {
    flex: 1;
    padding: 16px 12px;
    overflow-y: auto;
}

.nav-section-label {
    font-size: 10px;
    font-weight: 700;
    color: rgba(255,255,255,0.25);
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 16px 14px 8px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 500;
    color: rgba(255,255,255,0.55);
    text-decoration: none;
    transition: all 0.2s ease;
    margin-bottom: 2px;
    position: relative;
}

.sidebar-link i {
    font-size: 17px;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.sidebar-link:hover {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.9);
}

.sidebar-link.active {
    background: rgba(59,130,246,0.15);
    color: #60A5FA;
    font-weight: 600;
}

.sidebar-link.active::before {
    content: '';
    position: absolute;
    left: 0; top: 8px; bottom: 8px;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: #3B82F6;
}

/* Sidebar dropdown */
.sidebar-dropdown-btn {
    cursor: pointer;
}

.sidebar-dropdown-btn .chevron {
    margin-left: auto;
    font-size: 12px;
    transition: transform 0.2s ease;
}

.sidebar-dropdown-btn.open .chevron {
    transform: rotate(180deg);
}

.sidebar-submenu {
    display: none;
    padding-left: 20px;
}

.sidebar-submenu.show { display: block; }

.sidebar-submenu .sidebar-link {
    font-size: 13px;
    padding: 9px 14px;
}

/* Sidebar footer */
.sidebar-footer {
    padding: 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(255,255,255,0.04);
    text-decoration: none;
    transition: background 0.2s;
}

.sidebar-user:hover { background: rgba(255,255,255,0.08); }

.sidebar-user-avatar {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
}

.sidebar-user-info {
    flex: 1;
    min-width: 0;
}

.sidebar-user-name {
    font-size: 13px;
    font-weight: 600;
    color: rgba(255,255,255,0.85);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-user-role {
    font-size: 11px;
    color: rgba(255,255,255,0.35);
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
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    position: sticky;
    top: 0;
    z-index: 1030;
}

.topbar-search {
    position: relative;
    width: 320px;
}

.topbar-search input {
    width: 100%;
    padding: 9px 14px 9px 40px;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Inter', sans-serif;
    color: #1E293B;
    background: #F8FAFC;
    outline: none;
    transition: all 0.2s ease;
}

.topbar-search input::placeholder { color: #94A3B8; }
.topbar-search input:focus {
    border-color: #3B82F6;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.08);
}

.topbar-search i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 14px;
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.topbar-btn {
    width: 38px; height: 38px;
    border-radius: 10px;
    border: none;
    background: #F1F5F9;
    color: #64748B;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s ease;
    position: relative;
}

.topbar-btn:hover { background: #E2E8F0; color: #1E293B; }

.topbar-btn .notif-dot {
    position: absolute;
    top: 8px; right: 8px;
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #EF4444;
    border: 1.5px solid #FFFFFF;
}

.topbar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 14px 5px 5px;
    border-radius: 12px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
}

.topbar-user:hover { background: #F1F5F9; border-color: #CBD5E1; }

.topbar-user-avatar {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
}

.topbar-user-name {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
}

/* ============ CONTENT AREA ============ */
.content-area {
    flex: 1;
    padding: 28px 32px;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 991px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main-wrapper { margin-left: 0; }
    .topbar-search { width: 200px; }
}

@media (max-width: 576px) {
    .content-area { padding: 20px 16px; }
    .topbar { padding: 0 16px; }
    .topbar-search { width: 150px; }
}
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="assets/images/loewix_sales_logo.png" alt="Loewix Sales">
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
        <div class="topbar-search">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Cari menu, customer, atau fitur..." id="globalSearch">
        </div>
        <div class="topbar-actions">
            <button class="topbar-btn" title="Notifikasi">
                <i class="bi bi-bell"></i>
                <span class="notif-dot"></span>
            </button>
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
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:16px;border:none;">
      <div class="modal-header" style="border-bottom:1px solid #E2E8F0;">
        <h5 class="modal-title" id="changePasswordModalLabel" style="font-weight:700;font-size:16px;"><i class="bi bi-key-fill me-2"></i>Ganti Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="changePasswordForm">
            <div id="passwordChangeAlert" class="alert d-none" role="alert"></div>
            <div class="mb-3">
                <label for="old_password" class="form-label" style="font-size:13px;font-weight:600;">Password Lama</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="old_password" name="old_password" required style="border-radius:10px 0 0 10px;">
                    <span class="input-group-text toggle-password" style="border-radius:0 10px 10px 0;"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label" style="font-size:13px;font-weight:600;">Password Baru</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="new_password" name="new_password" required style="border-radius:10px 0 0 10px;">
                    <span class="input-group-text toggle-password" style="border-radius:0 10px 10px 0;"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label" style="font-size:13px;font-weight:600;">Konfirmasi Password Baru</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required style="border-radius:10px 0 0 10px;">
                    <span class="input-group-text toggle-password" style="border-radius:0 10px 10px 0;"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer" style="border-top:1px solid #E2E8F0;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px;">Tutup</button>
        <button type="submit" form="changePasswordForm" class="btn btn-primary" style="border-radius:10px;">Simpan</button>
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
});
</script>