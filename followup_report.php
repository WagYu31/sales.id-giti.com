<?php
$page_title = "Laporan Follow Up Sales";
require_once 'includes/db.php';
require_once 'includes/header.php';

if ($_SESSION['role'] !== 'superadmin') {
    die("Akses ditolak. Halaman ini hanya untuk Superadmin.");
}

function get_file_icon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return 'bi-file-earmark-pdf-fill text-danger';
        case 'doc': case 'docx': return 'bi-file-earmark-word-fill text-primary';
        case 'xls': case 'xlsx': return 'bi-file-earmark-excel-fill text-success';
        case 'jpg': case 'jpeg': case 'png': case 'gif': case 'webp': return 'bi-file-earmark-image-fill text-info';
        case 'mp4': case 'webm': case 'mkv': case 'mov': return 'bi-file-earmark-play-fill text-warning';
        case 'mp3': case 'wav': case 'ogg': case 'm4a': case 'aac': return 'bi-file-earmark-music-fill text-secondary';
        default: return 'bi-file-earmark-fill';
    }
}

$allowed_sort_columns = [
    'tgl_follow_up' => 'fu.tgl_follow_up',
    'nama_toko' => 'c.nama_toko',
    'nama_sales' => 's.nama_lengkap',
    'respon' => 'fu.respon',
    'no_inv' => 'fu.no_inv'
];

$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$selected_sales_id = isset($_GET['sales_id']) && is_numeric($_GET['sales_id']) ? (int)$_GET['sales_id'] : '';
$search_keyword = trim($_GET['search'] ?? '');
$respon_filter = trim($_GET['respon'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$limit = $_GET['limit'] ?? 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$sort_by = isset($_GET['sort_by']) && array_key_exists($_GET['sort_by'], $allowed_sort_columns) ? $_GET['sort_by'] : 'tgl_follow_up';
$sort_dir = isset($_GET['sort_dir']) && in_array(strtoupper($_GET['sort_dir']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_dir']) : 'DESC';

$sales_list_result = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL ORDER BY nama_lengkap ASC");

$base_query = "FROM follow_ups fu 
               JOIN sales s ON fu.sales_id = s.id 
               JOIN customers c ON fu.customer_id = c.id";

$conditions = ["fu.deleted_at IS NULL"];
$params = [];
$types = '';

if ($tgl_mulai && $tgl_akhir) {
    $conditions[] = "DATE(fu.tgl_follow_up) BETWEEN ? AND ?";
    array_push($params, $tgl_mulai, $tgl_akhir);
    $types .= 'ss';
}
if ($selected_sales_id) {
    $conditions[] = "fu.sales_id = ?";
    $params[] = $selected_sales_id;
    $types .= 'i';
}
if ($respon_filter) {
    if ($respon_filter === 'info') {
        $conditions[] = "(LOWER(fu.respon) LIKE '%informasi%' OR LOWER(fu.respon) LIKE '%menginformasikan%')";
    } elseif ($respon_filter === 'no_respon') {
        $conditions[] = "(fu.respon LIKE '%Tidak ada respon%' OR fu.respon LIKE '%Tidak tertarik%')";
    } else {
        $conditions[] = "fu.respon = ?";
        $params[] = $respon_filter;
        $types .= 's';
    }
}
if ($status_filter) {
    if ($status_filter === 'acc_boss') {
        $conditions[] = "c.acc_boss = 'Y'";
    } elseif ($status_filter === 'potensial') {
        $conditions[] = "c.potensial = 'Y'";
    } elseif ($status_filter === 'kandidat') {
        $conditions[] = "c.kandidat = 'Y'";
    }
}
if ($search_keyword) {
    $conditions[] = "(c.nama_toko LIKE ? OR fu.keterangan LIKE ? OR fu.no_inv LIKE ?)";
    $like_search = '%' . $search_keyword . '%';
    array_push($params, $like_search, $like_search, $like_search);
    $types .= 'sss';
}

$where_clause = " WHERE " . implode(' AND ', $conditions);

$count_sql = "SELECT COUNT(DISTINCT fu.id) as total " . $base_query . $where_clause;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$offset = ($page - 1) * ($limit == 'all' ? 1 : $limit);
$total_pages = ($limit == 'all') ? 1 : ceil($total_records / $limit);
$page = max(1, min($page, $total_pages));

$order_by_clause = " ORDER BY " . $allowed_sort_columns[$sort_by] . " " . $sort_dir;

$data_sql = "SELECT 
                fu.id, fu.customer_id, fu.tgl_follow_up, fu.respon, fu.keterangan, fu.no_inv, fu.nominal_invoice,
                fu.media1, fu.media2, fu.media3, fu.sales_id,
                s.nama_lengkap as nama_sales_fu, 
                c.nama_toko, c.kandidat, c.potensial, c.acc_boss, c.acc_boss_note
             " . $base_query . $where_clause . $order_by_clause;

if ($limit !== 'all') {
    $data_sql .= " LIMIT ?, ?";
    array_push($params, $offset, (int)$limit);
    $types .= 'ii';
}

$stmt = $conn->prepare($data_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$followups_result = $stmt->get_result();

$base_link_params = [
    'tgl_mulai' => $tgl_mulai,
    'tgl_akhir' => $tgl_akhir,
    'sales_id' => $selected_sales_id,
    'search' => $search_keyword,
    'respon' => $respon_filter,
    'status' => $status_filter,
    'limit' => $limit
];

function create_sort_link($column_name, $display_text, $current_sort_by, $current_sort_dir, $base_params) {
    $next_sort_dir = ($current_sort_by == $column_name && $current_sort_dir == 'ASC') ? 'DESC' : 'ASC';
    $link_params = array_merge($base_params, ['sort_by' => $column_name, 'sort_dir' => $next_sort_dir]);
    $icon = '<i class="bi bi-arrow-down-up opacity-40 ms-1" style="font-size:11px;"></i>';
    if ($current_sort_by == $column_name) {
        $icon = $current_sort_dir == 'ASC' ? '<i class="bi bi-sort-up-alt text-primary ms-1"></i>' : '<i class="bi bi-sort-down text-primary ms-1"></i>';
    }
    return '<a href="?' . http_build_query($link_params) . '" class="sort-header-link">' . $display_text . ' ' . $icon . '</a>';
}

$export_excel_url = 'export_followup_excel.php?' . http_build_query([
    'tgl_mulai' => $tgl_mulai,
    'tgl_akhir' => $tgl_akhir,
    'sales_id' => $selected_sales_id,
    'search' => $search_keyword,
    'respon' => $respon_filter,
    'status' => $status_filter,
    'sort_by' => $sort_by,
    'sort_dir' => $sort_dir
]);

// Query Stats Hari Ini & Total Deal
$fu_today_res = $conn->query("SELECT COUNT(*) as t FROM follow_ups WHERE DATE(tgl_follow_up) = CURRENT_DATE() AND deleted_at IS NULL");
$fu_today_count = $fu_today_res ? ($fu_today_res->fetch_assoc()['t'] ?? 0) : 0;

$fu_deal_res = $conn->query("SELECT COUNT(*) as d, SUM(nominal_invoice) as total_nom FROM follow_ups WHERE (no_inv != '' AND no_inv IS NOT NULL OR respon LIKE '%Deal%') AND deleted_at IS NULL");
$deal_data = $fu_deal_res ? $fu_deal_res->fetch_assoc() : ['d' => 0, 'total_nom' => 0];
$fu_deal_count = $deal_data['d'] ?? 0;
$fu_deal_nominal = $deal_data['total_nom'] ?? 0;
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Taste Skill Design System Tokens & Modern UI Styles -->
<style>
:root {
    --ts-bg-surface: #FFFFFF;
    --ts-bg-subtle: #F8FAFC;
    --ts-border-subtle: #E2E8F0;
    --ts-border-strong: #CBD5E1;
    --ts-text-primary: #0F172A;
    --ts-text-secondary: #475569;
    --ts-text-muted: #94A3B8;
    --ts-brand-primary: #2563EB;
    --ts-brand-gradient: linear-gradient(135deg, #0F172A 0%, #1E293B 40%, #1D4ED8 100%);
    --ts-radius-card: 20px;
    --ts-radius-pill: 9999px;
    --ts-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --ts-shadow-card: 0 10px 30px -10px rgba(15, 23, 42, 0.08), 0 4px 6px -2px rgba(15, 23, 42, 0.02);
    --ts-shadow-hover: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.05);
}

/* ── Typography & Tabular Numbers ── */
.ts-tabular-nums {
    font-variant-numeric: tabular-nums;
    font-feature-settings: 'tnum' on, 'lnum' on;
}

/* ── Hero Banner with Ambient Glow ── */
.taste-hero {
    background: linear-gradient(135deg, #091224 0%, #0F1F38 45%, #1E3A8A 100%);
    border-radius: 24px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.taste-hero::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 340px; height: 340px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.35) 0%, rgba(37, 99, 235, 0.05) 60%, transparent 70%);
    pointer-events: none;
}

.taste-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; left: 10%;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.2) 0%, transparent 70%);
    pointer-events: none;
}

.taste-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    padding: 4px 14px;
    border-radius: var(--ts-radius-pill);
    border: 1px solid rgba(255, 255, 255, 0.15);
    font-size: 11.5px;
    font-weight: 600;
    color: #93C5FD;
    margin-bottom: 12px;
}

.taste-hero-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: #FFFFFF;
    margin-bottom: 6px;
    line-height: 1.25;
}

.taste-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    max-width: 580px;
    margin: 0;
    line-height: 1.5;
}

/* ── Bento Stat Capsules ── */
.bento-stat-grid {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}

.bento-stat-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: 18px;
    padding: 16px 22px;
    min-width: 150px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease;
}

.bento-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.2);
}

.bento-stat-label {
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748B;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.bento-stat-value {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 28px;
    font-weight: 900;
    letter-spacing: -0.03em;
    color: #0F172A;
    line-height: 1.1;
}

.bento-stat-card.active-accent {
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    border-color: #34D399;
    color: #FFFFFF;
}

.bento-stat-card.active-accent .bento-stat-label {
    color: #D1FAE5;
}

.bento-stat-card.active-accent .bento-stat-value {
    color: #FFFFFF;
    text-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

/* ── Floating Filter Panel ── */
.taste-card {
    background: #FFFFFF;
    border-radius: var(--ts-radius-card);
    border: 1px solid var(--ts-border-subtle);
    box-shadow: var(--ts-shadow-card);
    margin-bottom: 24px;
    overflow: hidden;
}

.taste-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--ts-border-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #FFFFFF;
}

.taste-card-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: var(--ts-text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-label-taste {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748B;
    margin-bottom: 6px;
    display: block;
}

.form-control-taste, .form-select-taste {
    height: 42px;
    border: 1.5px solid #E2E8F0 !important;
    border-radius: 12px !important;
    padding: 8px 14px !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    color: #0F172A !important;
    background-color: #F8FAFC !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.form-control-taste:focus, .form-select-taste:focus {
    background-color: #FFFFFF !important;
    border-color: #3B82F6 !important;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12) !important;
    outline: none !important;
}

/* ── Primary Action Buttons with Tactile Depth ── */
.btn-taste-primary {
    height: 42px;
    background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
    color: #FFFFFF;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13.5px;
    font-weight: 700;
    border: none;
    border-radius: 12px;
    padding: 0 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    transition: all 0.2s ease;
    touch-action: manipulation;
}

.btn-taste-primary:hover {
    background: linear-gradient(135deg, #1D4ED8 0%, #1E40AF 100%);
    color: #FFFFFF;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.btn-taste-excel {
    height: 42px;
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    color: #FFFFFF;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13.5px;
    font-weight: 700;
    border: none;
    border-radius: 12px;
    padding: 0 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    transition: all 0.2s ease;
    text-decoration: none !important;
    touch-action: manipulation;
}

.btn-taste-excel:hover {
    background: linear-gradient(135deg, #047857 0%, #059669 100%);
    color: #FFFFFF;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}

.btn-taste-light {
    height: 42px;
    background: #F8FAFC;
    color: #475569;
    border: 1.5px solid #E2E8F0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13.5px;
    font-weight: 700;
    border-radius: 12px;
    padding: 0 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
    text-decoration: none !important;
    touch-action: manipulation;
}

.btn-taste-light:hover {
    background: #FFFFFF;
    color: #0F172A;
    border-color: #CBD5E1;
    transform: translateY(-1px);
}

/* ── Modern Table Header & Cells ── */
.taste-table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.taste-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.taste-table thead th {
    background: #0F172A !important;
    color: #F8FAFC !important;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: 16px 18px !important;
    border: none !important;
    vertical-align: middle;
    white-space: nowrap;
}

.taste-table thead th:first-child {
    border-top-left-radius: 0;
}

.taste-table thead th:last-child {
    border-top-right-radius: 0;
}

.sort-header-link {
    color: #E2E8F0 !important;
    text-decoration: none !important;
    transition: color 0.15s ease;
    display: inline-flex;
    align-items: center;
}

.sort-header-link:hover {
    color: #60A5FA !important;
}

.taste-table tbody tr {
    transition: background-color 0.15s ease, transform 0.15s ease;
    border-bottom: 1px solid #F1F5F9;
}

.taste-table tbody tr:hover {
    background-color: #F8FAFC !important;
}

.taste-table tbody td {
    padding: 16px 18px !important;
    border-top: none;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: top;
    font-size: 13.5px;
    color: #1E293B;
}

/* ── Refined Status Badges (Taste Skill Aesthetic) ── */
.badge-taste-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: var(--ts-radius-pill);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
    border: 1px solid transparent;
}

.badge-acc-boss {
    background: #ECFDF5;
    color: #065F46;
    border-color: #A7F3D0;
}

.badge-potensial {
    background: #FFFBEB;
    color: #92400E;
    border-color: #FDE68A;
}

.badge-kandidat {
    background: #F0F9FF;
    color: #075985;
    border-color: #BAE6FD;
}

/* ── Response Pills with Modern Glow ── */
.pill-respon {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: var(--ts-radius-pill);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 11.5px;
    font-weight: 700;
    white-space: nowrap;
}

.pill-respon-deal {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
}

.pill-respon-beli {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
}

.pill-respon-fu {
    background: #EEF2FF;
    color: #4338CA;
    border: 1px solid #C7D2FE;
}

.pill-respon-tanya {
    background: #FFFBEB;
    color: #B45309;
    border: 1px solid #FDE68A;
}

.pill-respon-info {
    background: #F0FDFA;
    color: #0F766E;
    border: 1px solid #99F6E4;
}

.pill-respon-no {
    background: #FFF1F2;
    color: #BE123C;
    border: 1px solid #FECDD3;
}

.pill-respon-default {
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #E2E8F0;
}

/* ── Monospace Invoice Pill ── */
.pill-inv {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
    padding: 3px 8px;
    border-radius: 6px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    font-weight: 700;
    margin-top: 5px;
}

/* ── Sales Avatar & Card Notes ── */
.sales-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.sales-avatar-taste {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
    color: #FFFFFF;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 11px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.note-box-taste {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 10px 14px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    line-height: 1.5;
    color: #334155;
}

/* ── Media Button ── */
.btn-media-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #F0FDF4;
    color: #166534;
    border: 1px solid #BBF7D0;
    border-radius: var(--ts-radius-pill);
    padding: 5px 12px;
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none !important;
    transition: all 0.15s ease;
}

.btn-media-pill:hover {
    background: #DCFCE7;
    color: #14532D;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(34, 197, 94, 0.2);
}

.btn-delete-circle {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #FFF1F2;
    color: #E11D48;
    border: 1px solid #FECDD3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    cursor: pointer;
}

.btn-delete-circle:hover {
    background: #FFE4E6;
    color: #BE123C;
    transform: scale(1.06);
}

/* ── Responsive Breakpoints ── */
@media (max-width: 991.98px) {
    .taste-hero {
        padding: 24px 20px;
        border-radius: 18px;
    }
    .bento-stat-grid {
        width: 100%;
        margin-top: 18px;
    }
    .bento-stat-card {
        flex: 1 1 calc(50% - 7px);
        min-width: 130px;
    }
}

@media (max-width: 575.98px) {
    .taste-hero-title { font-size: 22px; }
    .bento-stat-card { flex: 1 1 100%; }
}
</style>

<!-- 1. Hero Header Banner with Bento Stats -->
<div class="taste-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="taste-breadcrumb">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Follow Up Report</span>
            </div>
            <h1 class="taste-hero-title">Laporan Follow Up Sales 📊</h1>
            <p class="taste-hero-subtitle">Pantau seluruh aktivitas komunikasi, respon customer, dan konversi sales secara terpusat.</p>
        </div>
        
        <div class="bento-stat-grid mt-3 mt-lg-0">
            <div class="bento-stat-card">
                <div class="bento-stat-label">
                    <i class="bi bi-bar-chart-fill text-primary"></i> TOTAL LAPORAN
                </div>
                <div class="bento-stat-value ts-tabular-nums">
                    <?php echo number_format($total_records); ?>
                </div>
            </div>
            
            <div class="bento-stat-card active-accent">
                <div class="bento-stat-label">
                    <i class="bi bi-lightning-charge-fill"></i> HARI INI
                </div>
                <div class="bento-stat-value ts-tabular-nums">
                    <?php echo number_format($fu_today_count); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. Floating Filter Control Card -->
<div class="taste-card">
    <div class="taste-card-header">
        <h5 class="taste-card-title">
            <i class="bi bi-funnel-fill text-primary"></i> Filter Laporan Follow Up
        </h5>
    </div>
    <div class="card-body p-4">
        <form action="" method="GET" id="filter-form">
            <!-- Row 1: Search, Dari Tanggal, Sampai Tanggal -->
            <div class="row g-3 mb-3">
                <div class="col-lg-6 col-md-12 col-12">
                    <label for="search" class="form-label-taste">🔍 Cari Kata Kunci / Toko / Invoice</label>
                    <input type="text" class="form-control form-control-taste" id="search" name="search" placeholder="Ketik nama toko, no invoice, atau kata kunci catatan..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                </div>
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="tgl_mulai" class="form-label-taste">📅 Dari Tanggal</label>
                    <input type="date" class="form-control form-control-taste" id="tgl_mulai" name="tgl_mulai" value="<?php echo htmlspecialchars($tgl_mulai); ?>">
                </div>
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="tgl_akhir" class="form-label-taste">📅 Sampai Tanggal</label>
                    <input type="date" class="form-control form-control-taste" id="tgl_akhir" name="tgl_akhir" value="<?php echo htmlspecialchars($tgl_akhir); ?>">
                </div>
            </div>

            <!-- Row 2: Sales, Respon, Status, Buttons -->
            <div class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="sales_id" class="form-label-taste">👤 Pilih Sales</label>
                    <select id="sales_id" name="sales_id" class="form-select form-select-taste">
                        <option value="">Semua Sales</option>
                        <?php mysqli_data_seek($sales_list_result, 0); ?>
                        <?php while($sales = $sales_list_result->fetch_assoc()): ?>
                            <option value="<?php echo $sales['id']; ?>" <?php if ($selected_sales_id == $sales['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($sales['nama_lengkap']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="respon" class="form-label-taste">💬 Respon Follow Up</label>
                    <select id="respon" name="respon" class="form-select form-select-taste">
                        <option value="">Semua Respon</option>
                        <option value="Deal untuk beli" <?php if ($respon_filter == 'Deal untuk beli') echo 'selected'; ?>>🏆 Deal untuk beli</option>
                        <option value="Muncul keinginan membeli" <?php if ($respon_filter == 'Muncul keinginan membeli') echo 'selected'; ?>>🚀 Muncul Keinginan Membeli</option>
                        <option value="Follow Up" <?php if ($respon_filter == 'Follow Up') echo 'selected'; ?>>🔄 Follow Up Berjalan</option>
                        <option value="Hanya bertanya" <?php if ($respon_filter == 'Hanya bertanya') echo 'selected'; ?>>❓ Hanya Bertanya</option>
                        <option value="info" <?php if ($respon_filter == 'info') echo 'selected'; ?>>ℹ️ Info Customer</option>
                        <option value="no_respon" <?php if ($respon_filter == 'no_respon') echo 'selected'; ?>>❌ Tidak Ada Respon / Tertarik</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="status" class="form-label-taste">🏷️ Status Customer</label>
                    <select id="status" name="status" class="form-select form-select-taste">
                        <option value="">Semua Status</option>
                        <option value="acc_boss" <?php if ($status_filter == 'acc_boss') echo 'selected'; ?>>✔ Acc Boss</option>
                        <option value="potensial" <?php if ($status_filter == 'potensial') echo 'selected'; ?>>⭐ Potensial</option>
                        <option value="kandidat" <?php if ($status_filter == 'kandidat') echo 'selected'; ?>>👤 Kandidat</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-taste-primary flex-grow-1">
                        <i class="bi bi-search"></i> Terapkan Filter
                    </button>
                    <a href="followup_report.php" class="btn btn-taste-light" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </div>
            <input type="hidden" name="limit" value="<?php echo htmlspecialchars($limit); ?>">
        </form>
    </div>
</div>

<!-- 3. Toolbar: Limit Select & Quick Export -->
<div class="card border-0 shadow-sm mb-3" style="border-radius:18px; background:#F8FAFC; border: 1.5px solid #E2E8F0 !important;">
    <div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2.5">
            <span class="badge bg-primary text-white fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm" style="font-size:12px; padding:8px 12px; border-radius:10px; letter-spacing:0.5px;">
                <i class="bi bi-layers-fill"></i> TAMPILKAN
            </span>
            <select id="limit-select" class="form-select fw-bold border-primary text-primary shadow-sm" style="width: 105px; border-radius:10px; padding:6px 14px; font-size:14px; background-color:#FFFFFF; border-width:1.5px;">
                <option value="20" <?php if ($limit == '20') echo 'selected'; ?>>20</option>
                <option value="40" <?php if ($limit == '40') echo 'selected'; ?>>40</option>
                <option value="60" <?php if ($limit == '60') echo 'selected'; ?>>60</option>
                <option value="80" <?php if ($limit == '80') echo 'selected'; ?>>80</option>
                <option value="100" <?php if ($limit == '100') echo 'selected'; ?>>100</option>
            </select>
            <span class="text-secondary fw-semibold" style="font-size:13.5px;">entri per halaman</span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?php echo $export_excel_url; ?>" target="_blank" class="btn-taste-excel" title="Export Data Terfilter ke File Excel (.xlsx)">
                <i class="bi bi-file-earmark-excel-fill fs-6"></i> Export Excel
            </a>
            
            <div class="d-inline-flex align-items-center gap-2 px-3 py-2 bg-white rounded-pill border shadow-2sm" style="border-color:#E2E8F0; font-size:13.5px; font-weight:700; color:#1E293B;">
                <i class="bi bi-card-text text-primary"></i> 
                <?php if ($limit != 'all' && $total_records > 0): ?>
                    <span>Menampilkan</span> 
                    <span class="badge bg-primary text-white px-2 py-1 ts-tabular-nums" style="font-size:13px; border-radius:6px;"><?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $limit, $total_records)); ?></span> 
                    <span>dari</span> 
                    <span class="badge bg-dark text-white px-2 py-1 ts-tabular-nums" style="font-size:13px; border-radius:6px;"><?php echo number_format($total_records); ?></span> 
                    <span>data</span>
                <?php elseif ($total_records > 0): ?>
                    <span>Menampilkan semua</span> 
                    <span class="badge bg-primary text-white px-2 py-1 ts-tabular-nums" style="font-size:13px; border-radius:6px;"><?php echo number_format($total_records); ?></span> 
                    <span>data</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 4. Main Data Table Card -->
<div class="taste-card">
    <div class="taste-card-header">
        <h5 class="taste-card-title">
            <i class="bi bi-card-list text-primary"></i> Riwayat Semua Follow Up
        </h5>
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo $export_excel_url; ?>" target="_blank" class="btn btn-sm btn-taste-excel" style="height:36px; padding:0 14px; font-size:12.5px;" title="Export Data Follow Up ke File Excel (.xlsx)">
                <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
            </a>
            <a href="customer_management.php" class="btn btn-sm btn-taste-light" style="height:36px; padding:0 14px; font-size:12.5px;">
                <i class="bi bi-grid-fill"></i> Dashboard
            </a>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="taste-table-container">
            <table class="taste-table align-middle">
                <thead>
                    <tr>
                        <th style="width: 11%;"><?php echo create_sort_link('tgl_follow_up', 'Tanggal', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 22%;"><?php echo create_sort_link('nama_toko', 'Customer', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 13%;"><?php echo create_sort_link('nama_sales_fu', 'Sales', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 17%;"><?php echo create_sort_link('respon', 'Respon', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 21%;">Keterangan</th>
                        <th style="width: 11%;">Media</th>
                        <th style="width: 5%; text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($followups_result->num_rows > 0): ?>
                        <?php while($fu = $followups_result->fetch_assoc()): ?>
                            <tr id="followup-row-<?php echo $fu['id']; ?>">
                                <!-- Tanggal -->
                                <td class="text-nowrap" style="vertical-align:top;">
                                    <div class="fw-bold text-dark ts-tabular-nums" style="font-size:13.5px; font-family:'Plus Jakarta Sans', sans-serif;">
                                        <i class="bi bi-calendar-event text-primary me-1"></i><?php echo date('d M Y', strtotime($fu['tgl_follow_up'])); ?>
                                    </div>
                                    <div class="badge bg-light text-secondary border fw-bold mt-1 ts-tabular-nums" style="font-size:11px; border-radius:8px; padding:3px 8px;">
                                        <i class="bi bi-clock text-muted me-1"></i><?php echo date('H:i', strtotime($fu['tgl_follow_up'])); ?> WIB
                                    </div>
                                </td>

                                <!-- Customer -->
                                <td style="vertical-align:top;">
                                    <div class="fw-bold mb-1">
                                        <a href="followup_view.php?customer_id=<?php echo $fu['customer_id']; ?>" class="text-decoration-none text-dark hover-primary" style="font-size:14.5px; font-family:'Plus Jakarta Sans', sans-serif;">
                                            <i class="bi bi-shop text-primary me-1"></i><?php echo htmlspecialchars($fu['nama_toko']); ?>
                                        </a>
                                    </div>
                                    <div class="d-flex gap-1.5 flex-wrap mt-1">
                                        <?php if ($fu['acc_boss'] == 'Y'): ?>
                                            <span class="badge-taste-pill badge-acc-boss" title="<?php echo htmlspecialchars($fu['acc_boss_note']); ?>"><i class="bi bi-check-circle-fill"></i> Acc Boss</span>
                                        <?php endif; ?>
                                        <?php if ($fu['potensial'] == 'Y'): ?>
                                            <span class="badge-taste-pill badge-potensial"><i class="bi bi-star-fill"></i> Potensial</span>
                                        <?php endif; ?>
                                        <?php if ($fu['kandidat'] == 'Y'): ?>
                                            <span class="badge-taste-pill badge-kandidat"><i class="bi bi-person-fill"></i> Kandidat</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Sales -->
                                <td class="text-nowrap" style="vertical-align:top;">
                                    <div class="sales-chip">
                                        <div class="sales-avatar-taste">
                                            <?php echo strtoupper(substr($fu['nama_sales_fu'], 0, 1)); ?>
                                        </div>
                                        <span style="font-weight:700; font-size:13px; color:#1E293B;">
                                            <?php echo htmlspecialchars($fu['nama_sales_fu']); ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- Respon & Invoice -->
                                <td style="vertical-align:top;">
                                    <?php 
                                    $respon = htmlspecialchars($fu['respon']);
                                    $pillClass = 'pill-respon-default';
                                    $icon = 'bi-chat-text-fill';
                                    $responLower = strtolower($respon);
                                    $display_respon = $respon;

                                    if(in_array($respon, ['Tidak ada respon', 'Tidak tertarik'])) {
                                        $pillClass = 'pill-respon-no'; $icon = 'bi-x-circle-fill';
                                        $display_respon = ($respon === 'Tidak ada respon') ? 'No Respon' : 'Tidak Tertarik';
                                    } elseif($respon == 'Hanya bertanya') {
                                        $pillClass = 'pill-respon-tanya'; $icon = 'bi-question-circle-fill';
                                        $display_respon = 'Tanya Produk';
                                    } elseif($respon == 'Muncul keinginan membeli') {
                                        $pillClass = 'pill-respon-beli'; $icon = 'bi-arrow-up-right-circle-fill';
                                        $display_respon = 'Potensi Beli';
                                    } elseif($respon == 'Deal untuk beli') {
                                        $pillClass = 'pill-respon-deal'; $icon = 'bi-check-all';
                                        $display_respon = 'Deal / Beli';
                                    } elseif($respon == 'Follow Up') {
                                        $pillClass = 'pill-respon-fu'; $icon = 'bi-arrow-repeat';
                                    } elseif(str_contains($responLower, 'informasi') || str_contains($responLower, 'menginformasikan')) {
                                        $pillClass = 'pill-respon-info'; $icon = 'bi-info-circle-fill';
                                        $display_respon = 'Info Customer';
                                    }
                                    ?>
                                    <div>
                                        <span class="pill-respon <?php echo $pillClass; ?>" title="<?php echo $respon; ?>">
                                            <i class="bi <?php echo $icon; ?>"></i> <?php echo $display_respon; ?>
                                        </span>
                                    </div>
                                    <?php if ($fu['no_inv']): ?>
                                        <div>
                                            <div class="pill-inv"><i class="bi bi-receipt"></i> <?php echo htmlspecialchars($fu['no_inv']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Catatan / Keterangan -->
                                <td style="vertical-align:top;">
                                    <div class="note-box-taste">
                                        <?php echo nl2br(htmlspecialchars($fu['keterangan'])); ?>
                                    </div>
                                </td>

                                <!-- Media / Bukti -->
                                <td style="vertical-align:top;">
                                    <div class="d-flex flex-column gap-1.5">
                                    <?php for ($i = 1; $i <= 3; $i++): $media_file = $fu['media'.$i]; if ($media_file): 
                                        $ext = strtolower(pathinfo($media_file, PATHINFO_EXTENSION));
                                    ?>
                                        <a href="#" class="btn-media-pill" data-bs-toggle="modal" data-bs-target="#mediaModal" data-file-url="assets/uploads/<?php echo htmlspecialchars($media_file); ?>" data-file-name="<?php echo htmlspecialchars($media_file); ?>" title="Lihat Bukti Media">
                                            <i class="bi bi-whatsapp"></i> Bukti (<?php echo strtoupper($ext); ?>)
                                        </a>
                                    <?php endif; endfor; ?>
                                    </div>
                                </td>

                                <!-- Aksi -->
                                <td class="text-center" style="vertical-align:top;">
                                    <button type="button" class="btn-delete-circle delete-followup-btn" data-followup-id="<?php echo $fu['id']; ?>" title="Hapus catatan">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center p-5">
                                <div style="max-width:320px; margin:0 auto; padding:20px 0;">
                                    <div style="font-size:42px; margin-bottom:12px;">🔍</div>
                                    <h6 class="fw-bold text-dark">Tidak Ada Data Ditemukan</h6>
                                    <p class="text-muted small mb-0">Coba ubah kata kunci pencarian atau reset filter tanggal dan sales.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 5. Modern Segmented Pagination -->
<?php if ($limit != 'all' && $total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-4 mb-4">
    <ul class="pagination justify-content-center gap-1">
        <?php
            $query_params = http_build_query(array_merge($base_link_params, ['sort_by' => $sort_by, 'sort_dir' => $sort_dir]));
        ?>
        <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
            <a class="page-link shadow-2sm" style="border-radius:10px; font-weight:700;" href="?page=<?php echo $page - 1; ?>&<?php echo $query_params; ?>">
                <i class="bi bi-chevron-left"></i> Prev
            </a>
        </li>
        <li class="page-item disabled">
            <span class="page-link bg-white fw-extrabold text-dark shadow-2sm" style="border-radius:10px;">
                Halaman <?php echo $page; ?> / <?php echo $total_pages; ?>
            </span>
        </li>
        <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
            <a class="page-link shadow-2sm" style="border-radius:10px; font-weight:700;" href="?page=<?php echo $page + 1; ?>&<?php echo $query_params; ?>">
                Next <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php 
$stmt->close();
require_once 'includes/footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Delete Confirmation with SweetAlert2
    document.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.delete-followup-btn');
        if (deleteButton) {
            event.preventDefault();
            const followupId = deleteButton.dataset.followupId;

            Swal.fire({
                title: 'Hapus Follow Up?',
                text: 'Catatan follow up ini akan dihapus dari sistem.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('followup_delete.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ 'followup_id': followupId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const row = document.getElementById('followup-row-' + followupId);
                            if (row) {
                                row.style.transition = 'all 0.4s ease-out';
                                row.style.opacity = '0';
                                row.style.transform = 'scale(0.95)';
                                setTimeout(() => row.remove(), 400);
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus!',
                                text: 'Catatan follow-up berhasil dihapus.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Gagal!', data.message || 'Gagal menghapus data.', 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error!', 'Terjadi kesalahan koneksi server.', 'error');
                        console.error('Error:', error);
                    });
                }
            });
        }
    });

    const limitSelect = document.getElementById('limit-select');
    if (limitSelect) {
        limitSelect.addEventListener('change', function() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('limit', this.value);
            urlParams.set('page', '1');
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        });
    }
});
</script>