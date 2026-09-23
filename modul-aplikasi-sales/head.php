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

<!-- SweetAlert2 & Leaflet -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

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

/* Sidebar styling matching sales.id-giti.com */
.sidebar {
    width: 260px;
    min-height: 100vh;
    background: linear-gradient(180deg, #06132B 0%, #0A1E3D 50%, #0D2444 100%);
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 1040;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(59,130,246,0.08);
}

.sidebar-logo {
    padding: 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sidebar-logo img {
    height: 60px;
    max-width: 100%;
    object-fit: contain;
}

.sidebar-nav {
    flex: 1;
    padding: 16px 12px;
    overflow-y: auto;
}
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

.nav-section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #60A5FA;
    padding: 12px 12px 6px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: #94A3B8;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.2s ease;
    margin-bottom: 2px;
}
.sidebar-link:hover {
    color: #FFFFFF;
    background: rgba(255, 255, 255, 0.06);
}
.sidebar-link.active {
    color: #FFFFFF;
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.sidebar-link i {
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.main-content, .main-wrapper {
    margin-left: 260px;
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
