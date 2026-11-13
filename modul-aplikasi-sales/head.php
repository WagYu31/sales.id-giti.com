<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<!-- Loewix Favicon -->
<link rel="icon" type="image/png" href="../assets/images/favicon.png?v=2">
<link rel="shortcut icon" type="image/x-icon" href="../favicon.ico?v=2">
<link rel="apple-touch-icon" href="../assets/images/favicon.png?v=2">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

<!-- Bootstrap 5 & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

<!-- DataTables & Select2 -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

<!-- SweetAlert2, Leaflet & ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
(function() {
    if (localStorage.getItem('loewix_sidebar_collapsed') === 'true') {
        document.documentElement.classList.add('sidebar-collapsed');
    }
})();
window.toggleSidebarCollapse = function() {
    var isCollapsed = document.documentElement.classList.toggle('sidebar-collapsed');
    try {
        localStorage.setItem('loewix_sidebar_collapsed', isCollapsed ? 'true' : 'false');
    } catch(e) {}
};
</script>

<style>
/* ══════════════════════════════════════════════════════════════════════════
   LOEWIX SALES DESIGN SYSTEM (PLUS JAKARTA SANS & OUTFIT)
   ══════════════════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; }
body {
    font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
    background: #F1F5F9;
    color: #1E293B;
    margin: 0;
    -webkit-font-smoothing: antialiased;
    min-height: 100vh;
}

h1, h2, h3, h4, h5, h6, .card-title, .modal-title {
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.02em;
}

/* ══════════════════════════════════════════════════════════════════════════
   FROSTED GLASS FLOATING SIDEBAR (MODEL GAMBAR 2)
   ══════════════════════════════════════════════════════════════════════════ */
.sidebar {
    width: 275px;
    position: fixed;
    top: 14px;
    left: 14px;
    bottom: 14px;
    height: calc(100vh - 28px);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(28px) saturate(180%);
    -webkit-backdrop-filter: blur(28px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08), 0 2px 8px rgba(0, 0, 0, 0.03);
    z-index: 1040;
    display: flex;
    flex-direction: column;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 14px 12px;
    overflow: hidden;
}

/* Collapsed state */
.sidebar-collapsed .sidebar {
    width: 76px;
    padding: 14px 8px;
}

/* Mac Traffic Lights & Toggle Header */
.sidebar-mac-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2px 6px 12px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.7);
    margin-bottom: 8px;
}
.sidebar-mac-dots {
    display: flex;
    align-items: center;
    gap: 6px;
}
.mac-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    display: inline-block;
    cursor: pointer;
    transition: transform 0.15s ease;
}
.mac-dot:hover { transform: scale(1.15); }
.mac-dot-red    { background: #ff5f56; border: 1px solid #e0443e; }
.mac-dot-yellow { background: #ffbd2e; border: 1px solid #dea123; }
.mac-dot-green  { background: #27c93f; border: 1px solid #1aab29; }

.sidebar-collapse-btn {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    border: none;
    background: rgba(148, 163, 184, 0.12);
    color: #64748B;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.sidebar-collapse-btn:hover {
    background: rgba(37, 99, 235, 0.15);
    color: #2563EB;
}
.sidebar-collapsed .sidebar-collapse-btn i {
    transform: rotate(180deg);
}
.sidebar-collapsed .sidebar-mac-header {
    justify-content: center;
    padding-bottom: 10px;
}
.sidebar-collapsed .sidebar-mac-dots {
    display: none;
}

/* User Profile Header (Top Area like Gambar 2) */
.sidebar-profile-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 8px 12px;
    margin-bottom: 6px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.7);
    transition: all 0.2s ease;
}
.sidebar-avatar-ring {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #FFFFFF;
    font-weight: 800;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    border: 2px solid rgba(255, 255, 255, 0.9);
}
.sidebar-online-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #10B981;
    border: 2px solid #FFFFFF;
}
.sidebar-user-meta {
    flex: 1;
    min-width: 0;
}
.sidebar-greeting {
    font-size: 10.5px;
    font-weight: 600;
    color: #94A3B8;
    line-height: 1.2;
}
.sidebar-user-name {
    font-size: 13px;
    font-weight: 700;
    color: #0F172A;
    line-height: 1.25;
    margin: 1px 0 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar-role-pill {
    font-size: 9.5px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 6px;
    display: inline-block;
    letter-spacing: 0.04em;
    line-height: 1.1;
    text-transform: uppercase;
}
.sidebar-collapsed .sidebar-profile-header {
    justify-content: center;
    padding: 4px 0 10px;
}
.sidebar-collapsed .sidebar-user-meta {
    display: none !important;
}

/* Scrollable Nav Items */
.sidebar-nav {
    flex: 1;
    padding: 6px 2px;
    overflow-y: auto;
    overflow-x: hidden;
}
.sidebar-nav::-webkit-scrollbar { width: 3px; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.25); border-radius: 4px; }

/* Section Header (e.g. Menu: 6, Canvas: 7) */
.nav-section-title {
    font-size: 11px;
    font-weight: 700;
    color: #94A3B8;
    letter-spacing: 0.04em;
    padding: 10px 10px 5px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.sidebar-collapsed .nav-section-title {
    display: none !important;
}

/* Sidebar Links */
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 8px 10px;
    border-radius: 12px;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    margin-bottom: 2px;
    position: relative;
    border: 1px solid transparent;
}
.sidebar-link:hover {
    color: #0F172A;
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(255, 255, 255, 0.8);
    transform: translateX(2px);
}
.sidebar-collapsed .sidebar-link:hover {
    transform: none;
}

/* ACTIVE ITEM (Solid Vivid Blue Capsule Pill - Persis Gambar 2) */
.sidebar-link.active {
    color: #FFFFFF !important;
    background: #2563EB !important;
    font-weight: 700 !important;
    border-radius: 14px !important;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.28) !important;
}
.sidebar-link.active .nav-icon-badge {
    background: rgba(255, 255, 255, 0.22) !important;
    color: #FFFFFF !important;
}
.sidebar-link.active::after {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #FFFFFF;
    margin-left: auto;
    flex-shrink: 0;
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.8);
}

/* Icon Badges */
.nav-icon-badge {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13.5px;
    flex-shrink: 0;
    transition: all 0.2s ease;
}
.sidebar-link:hover .nav-icon-badge {
    transform: scale(1.08);
}
.sidebar-link.active .nav-icon-badge {
    transform: none;
}

.nav-link-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Sidebar Link Logout */
.sidebar-link-logout {
    color: #EF4444 !important;
}
.sidebar-link-logout:hover {
    background: rgba(239, 68, 68, 0.1) !important;
    border-color: rgba(239, 68, 68, 0.2) !important;
}

/* Nested White Card (Like "Service: 3" in Gambar 2) */
.sidebar-nested-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.95);
    border-radius: 18px;
    padding: 7px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
    margin: 4px 0 10px;
}
.sidebar-collapsed .sidebar-nested-card {
    background: transparent;
    border: none;
    padding: 0;
    box-shadow: none;
    margin: 2px 0;
}

/* Collapsed adjustments */
.sidebar-collapsed .sidebar-link {
    justify-content: center;
    padding: 8px 0;
    width: 44px;
    margin: 3px auto;
}
.sidebar-collapsed .nav-link-text,
.sidebar-collapsed .sidebar-link.active::after,
.sidebar-collapsed .chevron,
.sidebar-collapsed .sidebar-submenu {
    display: none !important;
}

/* Bottom Action Card (Gambar 2 style "Create new task") */
.sidebar-action-card {
    margin-top: 6px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.95);
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}
.sidebar-action-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 12px;
    background: #2563EB;
    color: #FFFFFF;
    font-weight: 700;
    font-size: 12px;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
    transition: all 0.2s ease;
}
.sidebar-action-btn:hover {
    background: #1D4ED8;
    color: #FFFFFF;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
}
.action-btn-circle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    line-height: 1;
}
.sidebar-action-sub {
    font-size: 10.5px;
    color: #94A3B8;
    margin-top: 6px;
}
.sidebar-action-sub a {
    color: #2563EB;
    text-decoration: none;
    font-weight: 600;
}
.sidebar-action-sub a:hover {
    text-decoration: underline;
}

.sidebar-mini-plus {
    display: none;
    justify-content: center;
    margin-top: 8px;
}
.btn-mini-circle-plus {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #2563EB;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    text-decoration: none;
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
    transition: all 0.2s ease;
}
.btn-mini-circle-plus:hover {
    background: #1D4ED8;
    color: #FFFFFF;
    transform: scale(1.08);
}
.sidebar-collapsed .sidebar-action-card {
    display: none !important;
}
.sidebar-collapsed .sidebar-mini-plus {
    display: flex !important;
}

/* ============ MAIN WRAPPER & MAIN CONTENT ============ */
.main-content, .main-wrapper {
    margin-left: 305px !important;
    min-height: 100vh;
    padding: 24px;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    max-width: calc(100vw - 305px);
}
.sidebar-collapsed .main-content,
.sidebar-collapsed .main-wrapper {
    margin-left: 106px !important;
    max-width: calc(100vw - 106px);
}

@media (max-width: 991.98px) {
    .sidebar {
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;
        height: 100vh !important;
        border-radius: 0 28px 28px 0 !important;
        transform: translateX(-105%) !important;
        width: 275px !important;
        background: rgba(255, 255, 255, 0.96) !important;
    }
    .sidebar.open, .sidebar.show {
        transform: translateX(0) !important;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.25) !important;
    }
    .main-content, .main-wrapper {
        margin-left: 0 !important;
        padding: 16px;
        max-width: 100vw !important;
        width: 100% !important;
    }
    .sidebar-collapsed .main-content,
    .sidebar-collapsed .main-wrapper {
        margin-left: 0 !important;
        max-width: 100vw !important;
    }
}

/* Material symbols adjustments */
.material-symbols-outlined {
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
}
</style>
