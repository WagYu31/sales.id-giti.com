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
   PRO SIDEBAR DESIGN SYSTEM (ULTRA SLEEK & VIBRANT)
   ══════════════════════════════════════════════════════════════════════════ */
.sidebar {
    width: 270px;
    min-height: 100vh;
    background: linear-gradient(180deg, #091124 0%, #0D1832 50%, #0F1D3D 100%);
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 1040;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.2);
}

.sidebar-logo {
    padding: 24px 20px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    position: relative;
    background: radial-gradient(ellipse at 50% 0%, rgba(59, 130, 246, 0.12), transparent 70%);
}
.sidebar-logo::after {
    content: '';
    position: absolute;
    bottom: -1px; left: 15%; width: 70%; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.4), transparent);
}
.sidebar-logo img {
    height: 52px;
    max-width: 100%;
    object-fit: contain;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
    transition: transform 0.2s ease;
}
.sidebar-logo:hover img {
    transform: scale(1.02);
}

.sidebar-nav {
    flex: 1;
    padding: 16px 12px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 4px; }

/* Section Header Titles */
.nav-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94A3B8;
    padding: 14px 10px 6px;
}
.nav-section-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dot-blue { background: #38BDF8; box-shadow: 0 0 6px rgba(56, 189, 248, 0.6); }
.dot-cyan { background: #22D3EE; box-shadow: 0 0 6px rgba(34, 211, 238, 0.6); }
.dot-purple { background: #C084FC; box-shadow: 0 0 6px rgba(192, 132, 252, 0.6); }
.dot-amber { background: #FBBF24; box-shadow: 0 0 6px rgba(251, 191, 36, 0.6); }
.dot-rose { background: #F87171; box-shadow: 0 0 6px rgba(248, 113, 113, 0.6); }

/* Sidebar Links */
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    color: #CBD5E1;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    border-radius: 12px;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    margin-bottom: 3px;
    position: relative;
    border: 1px solid transparent;
}
.sidebar-link:hover {
    color: #FFFFFF;
    background: rgba(255, 255, 255, 0.07);
    transform: translateX(3px);
    border-color: rgba(255, 255, 255, 0.05);
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
    padding: 14px 16px;
    margin: 10px 12px 14px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    backdrop-filter: blur(10px);
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

.main-content, .main-wrapper {
    margin-left: 270px;
    min-height: 100vh;
    padding: 24px;
    display: flex;
    flex-direction: column;
}

@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main-content, .main-wrapper {
        margin-left: 0;
        padding: 16px;
    }
}

/* Material symbols adjustments */
.material-symbols-outlined {
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
}
</style>
