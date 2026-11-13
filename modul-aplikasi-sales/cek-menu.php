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

<!-- ===== SIDEBAR FROSTED GLASS CAPSULE (MODEL GAMBAR 2) ===== -->
<aside class="sidebar" id="sidebar">
    
    <!-- 1. Mac Window Traffic Lights & Collapse Toggle -->
    <div class="sidebar-mac-header">
        <div class="sidebar-mac-dots">
            <span class="mac-dot mac-dot-red" onclick="window.location.href='../logout.php'" title="Keluar"></span>
            <span class="mac-dot mac-dot-yellow" onclick="toggleSidebarCollapse()" title="Minimize"></span>
            <span class="mac-dot mac-dot-green" onclick="toggleSidebarCollapse()" title="Perluas"></span>
        </div>
        <button type="button" class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" title="Toggle Sidebar (Expand/Mini)">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <!-- 2. User Profile Header (Top Area like Gambar 2) -->
    <div class="sidebar-profile-header">
        <div class="sidebar-avatar-ring">
            <?php echo $userInitials; ?>
            <span class="sidebar-online-indicator"></span>
        </div>
        <div class="sidebar-user-meta">
            <div class="sidebar-greeting">Good Day 👋</div>
            <div class="sidebar-user-name" title="<?php echo $userName; ?>"><?php echo $userName; ?></div>
            <span class="sidebar-role-pill" style="<?php echo $roleBadgeBg; ?>">
                <?php echo htmlspecialchars($userRole); ?>
            </span>
        </div>
    </div>

    <!-- Navigation Scroll Area -->
    <nav class="sidebar-nav">
        
        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 1. MENU UTAMA                                             -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title">
            <span>Menu: 6</span>
            <i class="bi bi-filter-right text-muted" style="font-size: 11px;"></i>
        </div>

        <a href="../customer_management.php" class="sidebar-link" title="Dashboard Utama">
            <span class="nav-icon-badge" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;">
                <i class="bi bi-grid-1x2-fill"></i>
            </span>
            <span class="nav-link-text">Dashboard Utama</span>
        </a>

        <a href="../index.php" class="sidebar-link" title="Database Customer & Forum">
            <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                <i class="bi bi-people-fill"></i>
            </span>
            <span class="nav-link-text">Database Customer &amp; Forum</span>
        </a>

        <a href="../sales_work_plan.php" class="sidebar-link" title="Rencana Kerja Sales">
            <span class="nav-icon-badge" style="background: rgba(99, 102, 241, 0.15); color: #6366F1;">
                <i class="bi bi-calendar-check-fill"></i>
            </span>
            <span class="nav-link-text">Rencana Kerja Sales</span>
        </a>

        <a href="../sales_orders.php" class="sidebar-link" title="Penawaran Penjualan (SO)">
            <span class="nav-icon-badge" style="background: rgba(37, 99, 235, 0.15); color: #2563EB;">
                <i class="bi bi-cart3"></i>
            </span>
            <span class="nav-link-text">Penawaran Penjualan (SO)</span>
        </a>

        <?php if ($userRole === 'superadmin'): ?>
        <a href="../followup_report.php" class="sidebar-link" title="Follow Up Report">
            <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                <i class="bi bi-journal-check"></i>
            </span>
            <span class="nav-link-text">Follow Up Report</span>
        </a>
        <?php endif; ?>

        <a href="../sales_management.php" class="sidebar-link" title="Data Sales (Web)">
            <span class="nav-icon-badge" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
                <i class="bi bi-graph-up-arrow"></i>
            </span>
            <span class="nav-link-text">Data Sales (Web)</span>
        </a>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 2. APLIKASI SALES CANVAS (MOBILE)                         -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title" style="margin-top: 10px;">
            <span>Canvas Mobile: 8</span>
            <i class="bi bi-phone text-muted" style="font-size: 11px;"></i>
        </div>

        <div class="sidebar-nested-card">
            <a href="kegiatan.php" class="sidebar-link <?php echo in_array($currFile, ['kegiatan.php', 'kegiatan-selesai.php', 'kegiatan-db.php', 'detail_kegiatan.php', 'edit_kegiatan.php']) ? 'active' : ''; ?>" title="Dashboard Sales Canvas">
                <span class="nav-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0EA5E9;">
                    <i class="bi bi-grid-1x2-fill"></i>
                </span>
                <span class="nav-link-text">Dashboard Sales Canvas</span>
            </a>

            <a href="kegiatan-baru.php" class="sidebar-link <?php echo ($currFile === 'kegiatan-baru.php') ? 'active' : ''; ?>" title="Tambah Kegiatan Baru">
                <span class="nav-icon-badge" style="background: rgba(37, 99, 235, 0.15); color: #2563EB;">
                    <i class="bi bi-calendar-plus-fill"></i>
                </span>
                <span class="nav-link-text">Tambah Kegiatan Baru</span>
            </a>

            <a href="laporan-kegiatan.php" class="sidebar-link <?php echo in_array($currFile, ['laporan-kegiatan.php', 'laporan-cust.php', 'laporan-db-kegiatan.php']) ? 'active' : ''; ?>" title="Laporan Visit (GPS)">
                <span class="nav-icon-badge" style="background: rgba(20, 184, 166, 0.15); color: #14B8A6;">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <span class="nav-link-text">Laporan Visit (GPS)</span>
            </a>

            <a href="tiptok.php" class="sidebar-link <?php echo ($currFile === 'tiptok.php') ? 'active' : ''; ?>" title="TIP TOK (Konsinyasi)">
                <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                    <i class="bi bi-box-seam-fill"></i>
                </span>
                <span class="nav-link-text">TIP TOK (Konsinyasi)</span>
            </a>

            <a href="tiptok-invoice.php" class="sidebar-link <?php echo ($currFile === 'tiptok-invoice.php') ? 'active' : ''; ?>" title="No. Invoice TIP TOK">
                <span class="nav-icon-badge" style="background: rgba(14, 165, 233, 0.15); color: #0EA5E9;">
                    <i class="bi bi-receipt-cutoff"></i>
                </span>
                <span class="nav-link-text">No. Invoice TIP TOK</span>
            </a>

            <a href="customer.php" class="sidebar-link <?php echo in_array($currFile, ['customer.php', 'customer-detail.php', 'tambah-customer.php', 'edit-customer.php']) ? 'active' : ''; ?>" title="Customer Toko/Dealer">
                <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                    <i class="bi bi-shop-window"></i>
                </span>
                <span class="nav-link-text">Customer Toko/Dealer</span>
            </a>

            <a href="sales.php" class="sidebar-link <?php echo in_array($currFile, ['sales.php', 'sales-detail.php']) ? 'active' : ''; ?>" title="Akun & Sales App">
                <span class="nav-icon-badge" style="background: rgba(168, 85, 247, 0.15); color: #A855F7;">
                    <i class="bi bi-person-badge-fill"></i>
                </span>
                <span class="nav-link-text">Akun &amp; Sales App</span>
            </a>

            <a href="scraping-gmaps.php" class="sidebar-link <?php echo ($currFile === 'scraping-gmaps.php') ? 'active' : ''; ?>" title="Scraper Leads Maps">
                <span class="nav-icon-badge" style="background: rgba(244, 63, 94, 0.15); color: #F43F5E;">
                    <i class="bi bi-crosshair2"></i>
                </span>
                <span class="nav-link-text">Scraper Leads Maps</span>
            </a>

            <a href="../scraping_exhibitor.php" class="sidebar-link <?php echo ($currFile === 'scraping_exhibitor.php') ? 'active' : ''; ?>" title="Scraper Database Pameran">
                <span class="nav-icon-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                    <i class="bi bi-buildings-fill"></i>
                </span>
                <span class="nav-link-text">Scraper Database Pameran</span>
            </a>
        </div>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 3. TOOLS & UTILITIES                                      -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title" style="margin-top: 10px;">
            <span>Tools &amp; Pendukung: 4</span>
            <i class="bi bi-tools text-muted" style="font-size: 11px;"></i>
        </div>

        <a href="../announcements.php" class="sidebar-link" title="Pengumuman">
            <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                <i class="bi bi-megaphone-fill"></i>
            </span>
            <span class="nav-link-text">Pengumuman</span>
        </a>

        <a href="../promosi_management.php" class="sidebar-link" title="Promosi">
            <span class="nav-icon-badge" style="background: rgba(236, 72, 153, 0.15); color: #EC4899;">
                <i class="bi bi-tags-fill"></i>
            </span>
            <span class="nav-link-text">Promosi</span>
        </a>

        <a href="../price_list.php" class="sidebar-link" title="Price List">
            <span class="nav-icon-badge" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;">
                <i class="bi bi-ui-checks"></i>
            </span>
            <span class="nav-link-text">Price List</span>
        </a>

        <a href="../calculator_sales.php" class="sidebar-link" title="Kalkulator Sales">
            <span class="nav-icon-badge" style="background: rgba(99, 102, 241, 0.15); color: #6366F1;">
                <i class="bi bi-calculator"></i>
            </span>
            <span class="nav-link-text">Kalkulator Sales</span>
        </a>

        <?php if ($userRole === 'superadmin'): ?>
        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 4. KHUSUS SUPER ADMIN                                     -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title" style="margin-top: 10px;">
            <span style="color: #D97706;">Super Admin</span>
            <i class="bi bi-shield-lock-fill text-warning" style="font-size: 11px;"></i>
        </div>

        <a href="../role_menu_access.php" class="sidebar-link <?php echo in_array($currFile, ['role_menu_access.php', 'role-menu.php']) ? 'active' : ''; ?>" title="Akses Role Menu">
            <span class="nav-icon-badge" style="background: rgba(245, 158, 11, 0.22); color: #FBBF24;">
                <i class="bi bi-shield-lock-fill"></i>
            </span>
            <span class="nav-link-text" style="font-weight: 700;">Akses Role Menu</span>
        </a>
        <?php endif; ?>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- 5. AKUN & LOGOUT                                          -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-title" style="margin-top: 10px;">
            <span>Akun</span>
            <i class="bi bi-person-circle text-muted" style="font-size: 11px;"></i>
        </div>

        <a href="../logout.php" class="sidebar-link sidebar-link-logout" title="Keluar">
            <span class="nav-icon-badge" style="background: rgba(239, 68, 68, 0.15); color: #EF4444;">
                <i class="bi bi-box-arrow-left"></i>
            </span>
            <span class="nav-link-text">Keluar (Logout)</span>
        </a>

    </nav>

    <!-- Bottom Action Card (Gambar 2 style "Create new task") -->
    <div class="sidebar-action-card">
        <a href="kegiatan-baru.php" class="sidebar-action-btn">
            <span class="action-btn-circle">+</span>
            <span class="action-btn-label">Tambah Kegiatan Baru</span>
        </a>
        <div class="sidebar-action-sub">Atau buat <a href="../sales_orders.php">Penawaran SO</a></div>
    </div>

    <!-- Mini round plus button for collapsed mode -->
    <div class="sidebar-mini-plus">
        <a href="kegiatan-baru.php" class="btn-mini-circle-plus" title="Tambah Kegiatan Baru">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>

</aside>
