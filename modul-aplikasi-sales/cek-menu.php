<?php
/**
 * cek-menu.php - Sidebar Navigation Bridge
 */
$currFile = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? 'sales';
$userName = htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User');
$userInitials = strtoupper(substr($userName, 0, 2));
?>
<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="../customer_management.php">
            <img src="../assets/images/loewix_sales_logo_white.png" alt="Loewix Sales" onerror="this.src='../assets/images/logo.png'">
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu Utama</div>

        <a href="../index.php" class="sidebar-link">
            <i class="bi bi-trophy-fill text-warning"></i>
            <span>Dashboard & Leaderboard</span>
        </a>

        <a href="../customer_management.php" class="sidebar-link">
            <i class="bi bi-grid-1x2-fill text-primary"></i>
            <span>Dashboard Sales</span>
        </a>

        <a href="../sales_work_plan.php" class="sidebar-link">
            <i class="bi bi-calendar-check-fill"></i>
            <span>Rencana Kerja Sales</span>
        </a>
        <?php if ($userRole === 'superadmin'): ?>
        <a href="../followup_report.php" class="sidebar-link">
            <i class="bi bi-journal-check"></i>
            <span>Follow Up Report</span>
        </a>
        <?php endif; ?>
        <a href="../sales_management.php" class="sidebar-link">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Data Sales (Web)</span>
        </a>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- SEKSI KHUSUS: APLIKASI SALES (MOBILE)                     -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-label" style="margin-top: 10px; color: #38BDF8;">
            <i class="bi bi-phone me-1"></i> Aplikasi Sales (Mobile)
        </div>

        <a href="kegiatan.php" class="sidebar-link <?php echo in_array($currFile, ['kegiatan.php', 'kegiatan-selesai.php', 'kegiatan-db.php', 'kegiatan-baru.php', 'detail_kegiatan.php']) ? 'active' : ''; ?>">
            <i class="bi bi-calendar-range-fill text-primary"></i>
            <span>Jadwal Kunjungan App</span>
        </a>

        <a href="laporan-kegiatan.php" class="sidebar-link <?php echo in_array($currFile, ['laporan-kegiatan.php', 'laporan-cust.php', 'laporan-db-kegiatan.php']) ? 'active' : ''; ?>">
            <i class="bi bi-geo-alt-fill text-info"></i>
            <span>Laporan Visit (GPS)</span>
        </a>

        <a href="tiptok.php" class="sidebar-link <?php echo ($currFile === 'tiptok.php') ? 'active' : ''; ?>">
            <i class="bi bi-box-seam-fill text-warning"></i>
            <span>TIP TOK (Konsinyasi)</span>
        </a>

        <a href="customer.php" class="sidebar-link <?php echo in_array($currFile, ['customer.php', 'customer-detail.php', 'tambah-customer.php', 'edit-customer.php']) ? 'active' : ''; ?>">
            <i class="bi bi-shop-window text-success"></i>
            <span>Customer Toko/Dealer</span>
        </a>

        <a href="sales.php" class="sidebar-link <?php echo in_array($currFile, ['sales.php', 'sales-detail.php']) ? 'active' : ''; ?>">
            <i class="bi bi-person-badge-fill text-cyan"></i>
            <span>Akun & Sales App</span>
        </a>

        <a href="scraping-gmaps.php" class="sidebar-link <?php echo ($currFile === 'scraping-gmaps.php') ? 'active' : ''; ?>">
            <i class="bi bi-crosshair2 text-danger"></i>
            <span>Scraper Leads Maps</span>
        </a>

        <!-- ═════════════════════════════════════════════════════════ -->
        <!-- TOOLS & SETTINGS                                         -->
        <!-- ═════════════════════════════════════════════════════════ -->
        <div class="nav-section-label" style="margin-top: 10px;">Tools</div>

        <a href="../announcements.php" class="sidebar-link">
            <i class="bi bi-megaphone-fill"></i>
            <span>Pengumuman</span>
        </a>
        <a href="../promosi_management.php" class="sidebar-link">
            <i class="bi bi-tags-fill"></i>
            <span>Promosi</span>
        </a>
        <a href="../price_list.php" class="sidebar-link">
            <i class="bi bi-ui-checks"></i>
            <span>Price List</span>
        </a>
        <a href="../calculator_sales.php" class="sidebar-link">
            <i class="bi bi-calculator"></i>
            <span>Kalkulator Sales</span>
        </a>

        <div class="nav-section-label" style="margin-top: 10px;">Akun</div>
        <a href="../logout.php" class="sidebar-link" style="color: #F87171;">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </nav>

    <div class="p-3 border-top border-secondary border-opacity-25 d-flex align-items-center gap-3">
        <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-size: 13px;">
            <?php echo $userInitials; ?>
        </div>
        <div class="overflow-hidden">
            <div class="text-white fw-semibold text-truncate" style="font-size: 13px;"><?php echo $userName; ?></div>
            <div class="text-secondary text-uppercase text-truncate" style="font-size: 11px;"><?php echo htmlspecialchars($userRole); ?></div>
        </div>
    </div>
</aside>
