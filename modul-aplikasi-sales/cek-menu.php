<?php
/**
 * cek-menu.php - Sidebar Navigation Bridge (Ultra Professional & Vibrant)
 */
$currFile = basename($_SERVER['PHP_SELF']);
$userRole = strtolower(trim($_SESSION['role'] ?? 'sales'));
$userName = htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User');
$userInitials = strtoupper(substr($userName, 0, 2));

// Role Badge Color Mapping
$roleBadgeBg = match($userRole) {
    'superadmin' => 'background: rgba(245, 158, 11, 0.18); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.4);',
    'adminsales' => 'background: rgba(168, 85, 247, 0.18); color: #D8B4FE; border: 1px solid rgba(168, 85, 247, 0.4);',
    'admin'      => 'background: rgba(59, 130, 246, 0.18); color: #93C5FD; border: 1px solid rgba(59, 130, 246, 0.4);',
    'manager'    => 'background: rgba(245, 158, 11, 0.18); color: #FDE68A; border: 1px solid rgba(245, 158, 11, 0.4);',
    'finance'    => 'background: rgba(6, 182, 212, 0.18); color: #67E8F9; border: 1px solid rgba(6, 182, 212, 0.4);',
    default      => 'background: rgba(16, 185, 129, 0.18); color: #6EE7B7; border: 1px solid rgba(16, 185, 129, 0.4);'
};
?>

<!-- ===== SIDEBAR PRO ===== -->
<aside class="sidebar" id="sidebar">
    
    <!-- Logo Header -->
    <div class="sidebar-logo">
        <a href="../customer_management.php" class="d-inline-flex flex-column align-items-center text-decoration-none">
            <img src="../assets/images/loewix_sales_logo_white.png" alt="Loewix Sales" onerror="this.src='../assets/images/logo.png'">
        </a>
    </div>

    <!-- Navigation Scroll Area -->
    <nav class="sidebar-nav">
        
        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 1. MENU UTAMA                                             -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title">
            <span class="nav-section-dot dot-blue"></span>
            <span>Menu Utama</span>
        </div>

        <a href="../index.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                <i class="bi bi-trophy-fill"></i>
            </span>
            <span class="nav-link-text">Dashboard &amp; Leaderboard</span>
        </a>

        <a href="../customer_management.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;">
                <i class="bi bi-grid-1x2-fill"></i>
            </span>
            <span class="nav-link-text">Dashboard Sales</span>
        </a>

        <a href="../sales_work_plan.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(99, 102, 241, 0.15); color: #6366F1;">
                <i class="bi bi-calendar-check-fill"></i>
            </span>
            <span class="nav-link-text">Rencana Kerja Sales</span>
        </a>

        <?php if ($userRole === 'superadmin'): ?>
        <a href="../followup_report.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                <i class="bi bi-journal-check"></i>
            </span>
            <span class="nav-link-text">Follow Up Report</span>
        </a>
        <?php endif; ?>

        <a href="../sales_management.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
                <i class="bi bi-graph-up-arrow"></i>
            </span>
            <span class="nav-link-text">Data Sales (Web)</span>
        </a>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 2. APLIKASI SALES CANVAS (MOBILE)                         -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title" style="margin-top: 14px;">
            <span class="nav-section-dot dot-cyan"></span>
            <span>Aplikasi Sales Canvas (Mobile)</span>
        </div>

        <a href="kegiatan.php" class="sidebar-link <?php echo in_array($currFile, ['kegiatan.php', 'kegiatan-selesai.php', 'kegiatan-db.php', 'detail_kegiatan.php']) ? 'active' : ''; ?>">
            <span class="nav-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0EA5E9;">
                <i class="bi bi-grid-1x2-fill"></i>
            </span>
            <span class="nav-link-text">Dashboard Sales Canvas</span>
        </a>

        <a href="kegiatan-baru.php" class="sidebar-link <?php echo ($currFile === 'kegiatan-baru.php') ? 'active' : ''; ?>">
            <span class="nav-icon-badge" style="background: rgba(37, 99, 235, 0.15); color: #2563EB;">
                <i class="bi bi-calendar-plus-fill"></i>
            </span>
            <span class="nav-link-text">Tambah Kegiatan Baru</span>
        </a>

        <a href="laporan-kegiatan.php" class="sidebar-link <?php echo in_array($currFile, ['laporan-kegiatan.php', 'laporan-cust.php', 'laporan-db-kegiatan.php']) ? 'active' : ''; ?>">
            <span class="nav-icon-badge" style="background: rgba(20, 184, 166, 0.15); color: #14B8A6;">
                <i class="bi bi-geo-alt-fill"></i>
            </span>
            <span class="nav-link-text">Laporan Visit (GPS)</span>
        </a>

        <a href="tiptok.php" class="sidebar-link <?php echo ($currFile === 'tiptok.php') ? 'active' : ''; ?>">
            <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                <i class="bi bi-box-seam-fill"></i>
            </span>
            <span class="nav-link-text">TIP TOK (Konsinyasi)</span>
        </a>

        <a href="tiptok-invoice.php" class="sidebar-link <?php echo ($currFile === 'tiptok-invoice.php') ? 'active' : ''; ?>">
            <span class="nav-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0EA5E9;">
                <i class="bi bi-receipt-cutoff"></i>
            </span>
            <span class="nav-link-text">No. Invoice TIP TOK</span>
        </a>

        <a href="customer.php" class="sidebar-link <?php echo in_array($currFile, ['customer.php', 'customer-detail.php', 'tambah-customer.php', 'edit-customer.php']) ? 'active' : ''; ?>">
            <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                <i class="bi bi-shop-window"></i>
            </span>
            <span class="nav-link-text">Customer Toko/Dealer</span>
        </a>

        <a href="sales.php" class="sidebar-link <?php echo in_array($currFile, ['sales.php', 'sales-detail.php']) ? 'active' : ''; ?>">
            <span class="nav-icon-badge" style="background: rgba(168, 85, 247, 0.15); color: #A855F7;">
                <i class="bi bi-person-badge-fill"></i>
            </span>
            <span class="nav-link-text">Akun &amp; Sales App</span>
        </a>

        <a href="scraping-gmaps.php" class="sidebar-link <?php echo ($currFile === 'scraping-gmaps.php') ? 'active' : ''; ?>">
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

        <a href="../announcements.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                <i class="bi bi-megaphone-fill"></i>
            </span>
            <span class="nav-link-text">Pengumuman</span>
        </a>

        <a href="../promosi_management.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(236, 72, 153, 0.15); color: #EC4899;">
                <i class="bi bi-tags-fill"></i>
            </span>
            <span class="nav-link-text">Promosi</span>
        </a>

        <a href="../price_list.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;">
                <i class="bi bi-ui-checks"></i>
            </span>
            <span class="nav-link-text">Price List</span>
        </a>

        <a href="../calculator_sales.php" class="sidebar-link">
            <span class="nav-icon-badge" style="background: rgba(99, 102, 241, 0.15); color: #6366F1;">
                <i class="bi bi-calculator"></i>
            </span>
            <span class="nav-link-text">Kalkulator Sales</span>
        </a>

        <?php if ($userRole === 'superadmin'): ?>
        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 4. KHUSUS SUPER ADMIN                                     -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title" style="margin-top: 14px;">
            <span class="nav-section-dot dot-amber"></span>
            <span style="color: #FBBF24;">Super Admin Area</span>
        </div>

        <a href="../role_menu_access.php" class="sidebar-link <?php echo in_array($currFile, ['role_menu_access.php', 'role-menu.php']) ? 'active' : ''; ?>">
            <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.22); color: #FBBF24;">
                <i class="bi bi-shield-lock-fill"></i>
            </span>
            <span class="nav-link-text" style="font-weight: 700;">Akses Role Menu</span>
        </a>
        <?php endif; ?>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 5. AKUN & LOGOUT                                          -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title" style="margin-top: 14px;">
            <span class="nav-section-dot dot-rose"></span>
            <span>Akun</span>
        </div>

        <a href="../logout.php" class="sidebar-link sidebar-link-logout">
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
