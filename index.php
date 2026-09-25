<?php
$page_title = 'Daftar Customer & Forum Q&A Sales';
require_once 'includes/db.php';
require_once 'includes/header.php';

// --- 1. FETCH Q&A DATA FOR FORUM SECTION ---
$sql_qa = "
    SELECT 
        q.id as question_id, q.title, q.body as question_body, q.created_at as question_created_at,
        qs.nama_lengkap as question_author, qs.id as question_author_id,
        a.id as answer_id, a.body as answer_body, a.created_at as answer_created_at,
        ans.nama_lengkap as answer_author, ans.id as answer_author_id
    FROM qa_questions q
    JOIN sales qs ON q.sales_id = qs.id
    LEFT JOIN qa_answers a ON q.id = a.question_id AND a.deleted_at IS NULL
    LEFT JOIN sales ans ON a.sales_id = ans.id
    WHERE q.deleted_at IS NULL
    ORDER BY q.created_at DESC, a.created_at ASC
";
$result_qa = $conn->query($sql_qa);
$questions = [];
if ($result_qa) {
    while ($row = $result_qa->fetch_assoc()) {
        $qid = $row['question_id'];
        if (!isset($questions[$qid])) {
            $questions[$qid] = [
                'id' => $row['question_id'],
                'title' => $row['title'],
                'body' => $row['question_body'],
                'author' => $row['question_author'],
                'author_id' => $row['question_author_id'],
                'created_at' => $row['question_created_at'],
                'answers' => []
            ];
        }
        if ($row['answer_id']) {
            $questions[$qid]['answers'][] = [
                'id' => $row['answer_id'],
                'body' => $row['answer_body'],
                'author' => $row['answer_author'],
                'author_id' => $row['answer_author_id'],
                'created_at' => $row['answer_created_at']
            ];
        }
    }
}

// --- 2. FETCH CUSTOMER DATA FOR DAFTAR CUSTOMER SECTION ---
$filter_kota = trim($_GET['filter_kota'] ?? '');
$filter_kategori = trim($_GET['filter_kategori'] ?? '');
$filter_sales = intval($_GET['filter_sales'] ?? 0);
$filter_fu = trim($_GET['filter_fu'] ?? '');
$search_keyword = trim($_GET['search'] ?? '');

$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 25;
if ($limit <= 0) $limit = 25;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page <= 0) $page = 1;

$sql_where_conditions = ["c.deleted_at IS NULL"];
$params = [];
$types = '';

$active_sales_id = 0;
if (isset($_SESSION['role']) && $_SESSION['role'] == 'sales') {
    $active_sales_id = (int)$_SESSION['user_id'];
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
} elseif ($filter_sales > 0) {
    $active_sales_id = $filter_sales;
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $filter_sales;
    $types .= 'i';
}

if (!empty($filter_kota)) {
    $sql_where_conditions[] = "c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND kota LIKE ?)";
    $params[] = "%" . $filter_kota . "%";
    $types .= 's';
}

if (!empty($filter_kategori)) {
    $sql_where_conditions[] = "c.kategori = ?";
    $params[] = $filter_kategori;
    $types .= 's';
}

if ($filter_fu === 'sudah') {
    if ($active_sales_id > 0) {
        $sql_where_conditions[] = "c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = ? AND deleted_at IS NULL)";
        $params[] = $active_sales_id;
        $types .= 'i';
    } else {
        $sql_where_conditions[] = "c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE deleted_at IS NULL)";
    }
} elseif ($filter_fu === 'belum') {
    if ($active_sales_id > 0) {
        $sql_where_conditions[] = "c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = ? AND deleted_at IS NULL)";
        $params[] = $active_sales_id;
        $types .= 'i';
    } else {
        $sql_where_conditions[] = "c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE deleted_at IS NULL)";
    }
}

if (!empty($search_keyword)) {
    $sql_where_conditions[] = "(c.nama_toko LIKE ? OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?)))";
    $like_kw = '%' . $search_keyword . '%';
    array_push($params, $like_kw, $like_kw, $like_kw);
    $types .= 'sss';
}

$where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

// Get total record count
$count_sql = "SELECT COUNT(*) as total FROM customers c {$where_clause}";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_records / $limit);
$page = max(1, min($page, max(1, $total_pages)));
$offset = ($page - 1) * $limit;

// Fetch paginated customer records
$sql = "
    SELECT 
        c.id, c.tgl_input, c.nama_toko, c.deal, c.kandidat, c.sales_id, c.kategori,
        s.nama_lengkap AS nama_sales,
        (SELECT GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL) AS all_pics,
        (SELECT GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL) AS all_phones,
        (SELECT GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.deleted_at IS NULL) AS all_cities,
        (SELECT ca.link_google_map FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.deleted_at IS NULL AND ca.link_google_map IS NOT NULL AND ca.link_google_map != '' LIMIT 1) AS primary_map_link,
        (SELECT COUNT(*) FROM follow_ups fu WHERE fu.customer_id = c.id AND fu.sales_id = c.sales_id AND fu.deleted_at IS NULL) AS fu_count,
        (SELECT COUNT(*) FROM follow_ups fu WHERE fu.customer_id = c.id AND fu.deleted_at IS NULL) AS total_fu_all_time
    FROM 
        customers c
    LEFT JOIN 
        sales s ON c.sales_id = s.id
    {$where_clause}
    ORDER BY 
        c.id DESC
    LIMIT ?, ?
";

$main_params = $params;
$main_types = $types;
$main_params[] = $offset;
$main_params[] = $limit;
$main_types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($main_params)) {
    $stmt->bind_param($main_types, ...$main_params);
}
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Fetch list of distinct cities
$cities = [];
if (!isset($_SESSION['cities_cache']) || isset($_GET['refresh_filter'])) {
    $r_city = $conn->query("SELECT DISTINCT TRIM(kota) AS nama_kota FROM customer_addresses WHERE deleted_at IS NULL AND kota IS NOT NULL AND TRIM(kota) != '' ORDER BY TRIM(kota) ASC");
    if ($r_city) {
        while($row = $r_city->fetch_assoc()) {
            $cities[] = $row['nama_kota'];
        }
    }
    $_SESSION['cities_cache'] = $cities;
} else {
    $cities = $_SESSION['cities_cache'];
}

// Fetch list of distinct categories
$categories = [];
if (!isset($_SESSION['categories_cache']) || isset($_GET['refresh_filter'])) {
    $r_cat = $conn->query("SELECT DISTINCT TRIM(kategori) AS nama_kategori FROM customers WHERE deleted_at IS NULL AND kategori IS NOT NULL AND TRIM(kategori) != '' ORDER BY TRIM(kategori) ASC");
    if ($r_cat) {
        while($row = $r_cat->fetch_assoc()) {
            $categories[] = $row['nama_kategori'];
        }
    }
    $_SESSION['categories_cache'] = $categories;
} else {
    $categories = $_SESSION['categories_cache'];
}

// Fetch list of sales for filter
$all_sales = [];
if ($_SESSION['role'] !== 'sales') {
    $r_sales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' ORDER BY nama_lengkap ASC");
    if ($r_sales) {
        while($row = $r_sales->fetch_assoc()) {
            $all_sales[] = $row;
        }
    }
}

// --- 3. FETCH METRICS FOR KPI STATS CARDS ---
$stats_where = ["c.deleted_at IS NULL"];
$stats_params = [];
$stats_types = '';

if (isset($_SESSION['role']) && $_SESSION['role'] == 'sales') {
    $stats_where[] = "c.sales_id = ?";
    $stats_params[] = $_SESSION['user_id'];
    $stats_types .= 'i';
} elseif ($filter_sales > 0) {
    $stats_where[] = "c.sales_id = ?";
    $stats_params[] = $filter_sales;
    $stats_types .= 'i';
}

$stats_where_sql = implode(' AND ', $stats_where);

if ($active_sales_id > 0) {
    $stats_query = "
        SELECT 
            COUNT(*) as total_customers,
            SUM(CASE WHEN c.id IN (SELECT DISTINCT fu_sub.customer_id FROM follow_ups fu_sub WHERE fu_sub.sales_id = ? AND fu_sub.deleted_at IS NULL) THEN 1 ELSE 0 END) as count_sudah_fu,
            SUM(CASE WHEN c.id NOT IN (SELECT DISTINCT fu_sub.customer_id FROM follow_ups fu_sub WHERE fu_sub.sales_id = ? AND fu_sub.deleted_at IS NULL) THEN 1 ELSE 0 END) as count_belum_fu,
            SUM(CASE WHEN c.kandidat = 'Y' THEN 1 ELSE 0 END) as count_kandidat,
            SUM(CASE WHEN c.deal = 'Y' THEN 1 ELSE 0 END) as count_deal
        FROM customers c
        WHERE {$stats_where_sql}
    ";
    $stats_fu_params = array_merge([$active_sales_id, $active_sales_id], $stats_params);
    $stats_fu_types  = 'ii' . $stats_types;
} else {
    $stats_query = "
        SELECT 
            COUNT(*) as total_customers,
            SUM(CASE WHEN c.id IN (SELECT DISTINCT fu_sub.customer_id FROM follow_ups fu_sub WHERE fu_sub.deleted_at IS NULL) THEN 1 ELSE 0 END) as count_sudah_fu,
            SUM(CASE WHEN c.id NOT IN (SELECT DISTINCT fu_sub.customer_id FROM follow_ups fu_sub WHERE fu_sub.deleted_at IS NULL) THEN 1 ELSE 0 END) as count_belum_fu,
            SUM(CASE WHEN c.kandidat = 'Y' THEN 1 ELSE 0 END) as count_kandidat,
            SUM(CASE WHEN c.deal = 'Y' THEN 1 ELSE 0 END) as count_deal
        FROM customers c
        WHERE {$stats_where_sql}
    ";
    $stats_fu_params = $stats_params;
    $stats_fu_types  = $stats_types;
}

$stmt_stats = $conn->prepare($stats_query);
if (!empty($stats_fu_params)) {
    $stmt_stats->bind_param($stats_fu_types, ...$stats_fu_params);
}
$stmt_stats->execute();
$stats_row = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

$stat_total    = (int)($stats_row['total_customers'] ?? 0);
$stat_sudah_fu = (int)($stats_row['count_sudah_fu'] ?? 0);
$stat_belum_fu = (int)($stats_row['count_belum_fu'] ?? 0);
$stat_kandidat = (int)($stats_row['count_kandidat'] ?? 0);
$stat_deal     = (int)($stats_row['count_deal'] ?? 0);

// Sync with Leaderboard Activity FU count
if ($active_sales_id > 0) {
    $stmt_act = $conn->prepare("
        SELECT COUNT(DISTINCT fu.id) AS total_fu
        FROM follow_ups fu
        JOIN customers c ON fu.customer_id = c.id
        WHERE fu.sales_id = ? AND fu.deleted_at IS NULL
          AND fu.tgl_follow_up >= '2026-08-01 00:00:00' AND fu.tgl_follow_up <= '2026-10-31 23:59:59'
    ");
    $stmt_act->bind_param("i", $active_sales_id);
    $stmt_act->execute();
    $act_res = (int)($stmt_act->get_result()->fetch_assoc()['total_fu'] ?? 0);
    $stmt_act->close();
    if ($act_res > 0) {
        $stat_sudah_fu = $act_res;
    }
}
?>

<style>
/* =========================================================
   TASTE-SKILL DESIGN SYSTEM — SALES WORKSPACE & CRM
   (Linear / Raycast / Stripe Grade Interface)
   ========================================================= */

:root {
    --taste-bg: #F8FAFC;
    --taste-card: #FFFFFF;
    --taste-border: #E2E8F0;
    --taste-border-focus: #3B82F6;
    --taste-text-main: #0F172A;
    --taste-text-muted: #64748B;
    --taste-text-sub: #94A3B8;
    --taste-primary: #0F172A;
    --taste-primary-hover: #1E293B;
    --taste-accent: #2563EB;
    --taste-emerald: #10B981;
    --taste-amber: #F59E0B;
    --taste-purple: #8B5CF6;
}

/* Page Container Header */
.taste-header {
    background: #FFFFFF;
    border: 1px solid var(--taste-border);
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.04);
}
.taste-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    color: var(--taste-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 8px;
}
.taste-breadcrumb .pulse-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #EFF6FF;
    color: #2563EB;
    border: 1px solid #DBEAFE;
    padding: 2px 8px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 800;
}
.taste-breadcrumb .pulse-badge .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #2563EB;
    box-shadow: 0 0 6px rgba(37, 99, 235, 0.6);
    animation: taste-pulse 2s infinite ease-in-out;
}
@keyframes taste-pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.5; }
}
.taste-title {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 24px;
    font-weight: 800;
    color: var(--taste-text-main);
    letter-spacing: -0.025em;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.taste-total-pill {
    background: #F1F5F9;
    color: #334155;
    font-size: 12px;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: 50px;
    border: 1px solid #E2E8F0;
    letter-spacing: 0;
}
.taste-subtitle {
    font-size: 13.5px;
    color: var(--taste-text-muted);
    margin: 0;
    max-width: 680px;
    line-height: 1.5;
}

/* Action Buttons */
.taste-btn-primary {
    background: #0F172A;
    color: #FFFFFF !important;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 18px;
    border-radius: 10px;
    border: 1px solid #0F172A;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.08);
    cursor: pointer;
    white-space: nowrap;
}
.taste-btn-primary:hover {
    background: #1E293B;
    border-color: #1E293B;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}
.taste-btn-secondary {
    background: #FFFFFF;
    color: #334155 !important;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 16px;
    border-radius: 10px;
    border: 1.5px solid var(--taste-border);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none !important;
    transition: all 0.2s ease;
    cursor: pointer;
    white-space: nowrap;
}
.taste-btn-secondary:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
    color: #0F172A !important;
    transform: translateY(-1px);
}

/* Status Filter Metrics Bar (Linear Segmented Style) */
.taste-metrics-bar {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
@media (max-width: 991px) {
    .taste-metrics-bar {
        display: flex;
        overflow-x: auto;
        gap: 10px;
        padding-bottom: 6px;
        -webkit-overflow-scrolling: touch;
    }
    .taste-metric-item {
        flex: 0 0 210px;
        min-width: 210px;
    }
}
.taste-metric-item {
    background: #FFFFFF;
    border: 1.5px solid var(--taste-border);
    border-radius: 14px;
    padding: 14px 16px;
    text-decoration: none !important;
    color: inherit !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    position: relative;
    overflow: hidden;
}
.taste-metric-item:hover {
    transform: translateY(-2px);
    border-color: #CBD5E1;
    box-shadow: 0 6px 16px -4px rgba(15, 23, 42, 0.08);
}
.taste-metric-item.active-metric {
    border-color: #2563EB !important;
    background: #F8FAFC !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12), 0 4px 12px -2px rgba(37, 99, 235, 0.1) !important;
}
.taste-metric-item.active-metric::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: #2563EB;
}
.taste-metric-info {
    display: flex;
    flex-direction: column;
}
.taste-metric-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--taste-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 3px;
}
.taste-metric-label .indicator-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
}
.dot-all { background: #64748B; }
.dot-pending { background: #F59E0B; box-shadow: 0 0 6px rgba(245, 158, 11, 0.6); }
.dot-fu { background: #2563EB; box-shadow: 0 0 6px rgba(37, 99, 235, 0.6); }
.dot-kandidat { background: #8B5CF6; box-shadow: 0 0 6px rgba(139, 92, 246, 0.6); }
.dot-deal { background: #10B981; box-shadow: 0 0 6px rgba(16, 185, 129, 0.6); }

.taste-metric-val {
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    font-size: 22px;
    font-weight: 800;
    color: var(--taste-text-main);
    letter-spacing: -0.02em;
    line-height: 1.1;
}
.taste-metric-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

/* Command & Filter Bar */
.taste-filter-card {
    background: #FFFFFF;
    border: 1px solid var(--taste-border);
    border-radius: 16px;
    padding: 18px 22px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
.taste-input-group {
    position: relative;
    width: 100%;
}
.taste-input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 15px;
    pointer-events: none;
}
.taste-input {
    width: 100%;
    height: 42px;
    padding: 0 14px 0 40px;
    border-radius: 10px;
    border: 1.5px solid var(--taste-border);
    font-size: 13.5px;
    font-weight: 600;
    color: var(--taste-text-main);
    background: #FFFFFF;
    transition: all 0.2s ease;
}
.taste-input:focus {
    border-color: #2563EB;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}
.taste-select {
    height: 42px;
    border-radius: 10px;
    border: 1.5px solid var(--taste-border);
    font-size: 13px;
    font-weight: 600;
    color: var(--taste-text-main);
    padding: 0 12px;
    background-color: #FFFFFF;
    transition: all 0.2s ease;
}
.taste-select:focus {
    border-color: #2563EB;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}
.taste-filter-label {
    font-size: 11px;
    font-weight: 800;
    color: var(--taste-text-muted);
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.taste-btn-apply {
    height: 42px;
    padding: 0 20px;
    border-radius: 10px;
    background: #2563EB;
    color: #FFFFFF;
    border: none;
    font-size: 13px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: all 0.2s ease;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
    white-space: nowrap;
}
.taste-btn-apply:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
}
.taste-btn-reset {
    height: 42px;
    padding: 0 16px;
    border-radius: 10px;
    background: #F8FAFC;
    color: var(--taste-text-muted);
    border: 1.5px solid var(--taste-border);
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none !important;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.taste-btn-reset:hover {
    background: #F1F5F9;
    color: var(--taste-text-main);
    border-color: #CBD5E1;
}

/* Forum Q&A Card */
.taste-forum-card {
    background: #FFFFFF;
    border: 1px solid var(--taste-border);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
.taste-forum-header {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #FFFFFF;
    cursor: pointer;
    user-select: none;
}
.forum-item-row {
    background: #FFFFFF;
    border: 1px solid var(--taste-border);
    border-radius: 12px;
    padding: 14px 18px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.forum-item-row:hover {
    border-color: #93C5FD;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.08);
    transform: translateY(-1px);
}
.author-pill {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #FFFFFF;
    font-weight: 800;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Linear-Grade Customer Table */
.taste-table-card {
    background: #FFFFFF;
    border: 1px solid var(--taste-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 16px -4px rgba(15, 23, 42, 0.04);
    margin-bottom: 24px;
}
.taste-table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.taste-table-responsive::-webkit-scrollbar {
    height: 7px;
}
.taste-table-responsive::-webkit-scrollbar-track {
    background: #F8FAFC;
}
.taste-table-responsive::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}
.taste-table {
    width: 100%;
    min-width: 1100px;
    border-collapse: separate;
    border-spacing: 0;
    margin-bottom: 0;
}
.taste-table thead th {
    background: #0F172A !important;
    color: #F8FAFC !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    letter-spacing: 0.06em !important;
    text-transform: uppercase !important;
    padding: 14px 14px !important;
    border: none !important;
    white-space: nowrap !important;
}
.taste-table tbody td {
    padding: 12px 14px !important;
    border-bottom: 1px solid #F1F5F9 !important;
    vertical-align: middle !important;
    background: #FFFFFF;
    font-size: 13px;
    color: #1E293B;
}
.taste-table tbody tr:hover td {
    background: #F8FAFC !important;
}

/* Shop Avatar & Badges */
.taste-shop-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: #EFF6FF;
    color: #2563EB;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}
.taste-wa-pill {
    background: #ECFDF5;
    color: #047857 !important;
    border: 1px solid #A7F3D0;
    border-radius: 50px;
    padding: 3px 10px;
    font-weight: 700;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none !important;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.taste-wa-pill:hover {
    background: #D1FAE5;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}
.taste-category-pill {
    background: #F8FAFC;
    color: #334155;
    border: 1px solid #E2E8F0;
    border-radius: 50px;
    padding: 3px 9px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}
.taste-city-pill {
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
    border-radius: 50px;
    padding: 3px 9px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}
.taste-sales-badge {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #FFFFFF;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10.5px;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    flex-shrink: 0;
}
.taste-fu-pill {
    background: #2563EB;
    color: #FFFFFF;
    border-radius: 50px;
    padding: 2px 8px;
    font-size: 11.5px;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    display: inline-block;
    text-decoration: none;
    transition: all 0.2s ease;
}
.taste-fu-pill:hover {
    background: #1D4ED8;
    transform: scale(1.08);
    color: #FFFFFF;
}
.taste-fu-pill.empty {
    background: #F1F5F9;
    color: #64748B;
    border: 1px solid #CBD5E1;
}

/* Action Icons */
.btn-quick-fu {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: #FFFFFF !important;
    font-weight: 800;
    font-size: 11px;
    border: none;
    border-radius: 8px;
    padding: 4px 9px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
    text-decoration: none !important;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.btn-quick-fu:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.35);
}
.taste-icon-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11.5px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    text-decoration: none;
    flex-shrink: 0;
}
.taste-icon-btn:hover {
    transform: translateY(-1px);
}
.taste-icon-btn.view { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
.taste-icon-btn.view:hover { background: #DBEAFE; color: #1D4ED8; }
.taste-icon-btn.edit { background: #F8FAFC; color: #475569; border-color: #CBD5E1; }
.taste-icon-btn.edit:hover { background: #E2E8F0; color: #0F172A; }
.taste-icon-btn.delete { background: #FEF2F2; color: #DC2626; border-color: #FECACA; }
.taste-icon-btn.delete:hover { background: #FEE2E2; color: #B91C1C; }
.taste-icon-btn.map { background: #ECFDF5; color: #059669; border-color: #A7F3D0; }
.taste-icon-btn.map:hover { background: #D1FAE5; color: #047857; }
.taste-icon-btn.map-disabled { background: #F8FAFC; color: #CBD5E1; border-color: #E2E8F0; cursor: not-allowed; }

.answer-card {
    border: 1px solid var(--taste-border);
    border-left: 3px solid #2563EB !important;
    border-radius: 10px !important;
    background: #F8FAFC;
}
</style>

<!-- TASTE-SKILL WORKSPACE HEADER -->
<div class="taste-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="taste-breadcrumb">
                <span class="pulse-badge"><span class="dot"></span> CRM & LEADS</span>
                <span>/</span>
                <span>Loewix Sales Workspace</span>
            </div>
            <h1 class="taste-title">
                Database Customer
                <span class="taste-total-pill"><?php echo number_format($stat_total); ?> Leads</span>
            </h1>
            <p class="taste-subtitle">Direktori pelanggan, riwayat PIC & kontak WhatsApp, koordinasi follow-up tim sales, dan status closing.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="customer_add.php" class="taste-btn-primary">
                <i class="bi bi-plus-lg"></i>
                <span>Tambah Customer</span>
            </a>
            <button class="taste-btn-secondary" data-bs-toggle="collapse" data-bs-target="#forumCollapseContent">
                <i class="bi bi-chat-left-text-fill text-primary"></i>
                <span>Forum Q&A (<?php echo count($questions); ?>)</span>
            </button>
        </div>
    </div>
</div>

<!-- SECTION 1: COLLAPSIBLE FORUM Q&A ACCORDION -->
<div id="forum-section" class="taste-forum-card">
    <div class="taste-forum-header" data-bs-toggle="collapse" data-bs-target="#forumCollapseContent" aria-expanded="false">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width:36px; height:36px; font-size:14px;">
                <i class="bi bi-chat-left-dots-fill"></i>
            </div>
            <div>
                <div class="fw-bold text-dark" style="font-size:14.5px;">
                    Forum Diskusi & Q&A Sales
                    <span class="badge bg-primary rounded-pill ms-1" style="font-size:11px;"><?php echo count($questions); ?> Topik</span>
                </div>
                <small class="text-muted" style="font-size:12px;">Tempat bertanya & berbagi solusi seputar customer/produk (Klik untuk buka/tutup)</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">
            <button class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold shadow-sm d-inline-flex align-items-center gap-1" style="font-size:12px;" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                <i class="bi bi-plus-circle-fill"></i> Buat Pertanyaan
            </button>
            <span class="btn btn-sm btn-light rounded-circle border p-0 d-inline-flex align-items-center justify-content-center" style="width:30px; height:30px;" data-bs-toggle="collapse" data-bs-target="#forumCollapseContent">
                <i class="bi bi-chevron-down text-muted" style="font-size:12px;"></i>
            </span>
        </div>
    </div>

    <div class="collapse" id="forumCollapseContent">
        <div class="p-3 bg-light border-top">
            <div class="mb-3">
                <div class="input-group" style="border-radius:10px; overflow:hidden;">
                    <span class="input-group-text bg-white border-end-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                    <input type="text" id="liveSearchInput" class="form-control border-start-0 ps-1 fw-semibold" placeholder="Cari pertanyaan, kata kunci, atau nama sales..." style="height:40px; font-size:13px;">
                </div>
            </div>

            <div class="d-flex flex-column gap-2" id="forumFeedContainer">
                <?php if (empty($questions)): ?>
                    <div class="text-center p-4 bg-white rounded-3 border">
                        <i class="bi bi-chat-square-dots text-primary fs-3 mb-1 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1" style="font-size:14px;">Belum ada diskusi sales.</h6>
                        <small class="text-muted">Klik "Buat Pertanyaan" di atas untuk memulai diskusi!</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($questions as $q): 
                        $hasAnswers = count($q['answers']) > 0;
                        $ansPillStyle = $hasAnswers ? 'background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE;' : 'background:#FEF3C7; color:#92400E; border:1px solid #FDE68A;';
                    ?>
                    <div class="forum-item-row" id="question-row-<?php echo $q['id']; ?>"
                        data-question-id="<?php echo $q['id']; ?>"
                        data-title="<?php echo htmlspecialchars($q['title']); ?>"
                        data-body="<?php echo htmlspecialchars($q['body']); ?>"
                        data-author="<?php echo htmlspecialchars($q['author']); ?>"
                        data-date="<?php echo date('d M Y', strtotime($q['created_at'])); ?>"
                        data-answers='<?php echo json_encode($q['answers']); ?>'
                        data-bs-toggle="modal" data-bs-target="#viewQuestionModal" onclick="populateAndShowModal(this)">
                        
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3" style="min-width:260px; flex: 1 1 350px;">
                                <div class="author-pill">
                                    <?php echo strtoupper(substr($q['author'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:13.5px; line-height:1.3;">
                                        <?php echo htmlspecialchars($q['title']); ?>
                                    </div>
                                    <div class="text-muted small" style="font-size:12px;">
                                        <?php echo htmlspecialchars(substr($q['body'], 0, 90)) . (strlen($q['body']) > 90 ? '...' : ''); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3 ms-auto" onclick="event.stopPropagation();">
                                <div class="text-end d-none d-sm-block">
                                    <div class="fw-semibold text-dark" style="font-size:11.5px;"><?php echo htmlspecialchars($q['author']); ?></div>
                                    <small class="text-muted" style="font-size:10.5px;"><?php echo date('d M Y', strtotime($q['created_at'])); ?></small>
                                </div>
                                <span class="badge rounded-pill fw-bold" style="<?php echo $ansPillStyle; ?> font-size:11px; padding:5px 12px;" data-bs-toggle="modal" data-bs-target="#viewQuestionModal" onclick="populateAndShowModal(this.closest('.forum-item-row'))">
                                    <i class="bi bi-chat-right-text-fill me-1"></i> <?php echo count($q['answers']); ?> Jawaban
                                </span>
                                <?php if ($_SESSION['user_id'] == $q['author_id'] || $_SESSION['role'] === 'superadmin'): ?>
                                    <button class="btn btn-sm btn-light border text-danger rounded-circle p-0 d-inline-flex align-items-center justify-content-center delete-btn" data-id="<?php echo $q['id']; ?>" data-type="question" title="Hapus Pertanyaan" style="width:28px; height:28px;">
                                        <i class="bi bi-trash-fill" style="font-size:11px;"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2: DAFTAR CUSTOMER -->
<div id="customer-section">

    <!-- KPI Status Segment Bar (Linear Style) -->
    <div class="taste-metrics-bar">
        <!-- 1. Total Customer -->
        <a href="index.php#customer-section" class="taste-metric-item <?php if (empty($filter_fu)) echo 'active-metric'; ?>" title="Klik untuk menampilkan semua customer">
            <div class="taste-metric-info">
                <div class="taste-metric-label"><span class="indicator-dot dot-all"></span> Semua</div>
                <div class="taste-metric-val"><?php echo number_format($stat_total); ?></div>
            </div>
            <div class="taste-metric-icon" style="background:#F1F5F9; color:#334155;">
                <i class="bi bi-people-fill"></i>
            </div>
        </a>

        <!-- 2. Belum Follow Up -->
        <a href="index.php?filter_fu=belum<?php echo $filter_sales ? '&filter_sales=' . $filter_sales : ''; ?>#customer-section" class="taste-metric-item <?php if ($filter_fu === 'belum') echo 'active-metric'; ?>" title="Klik untuk memfilter customer belum follow up">
            <div class="taste-metric-info">
                <div class="taste-metric-label" style="color:#D97706;"><span class="indicator-dot dot-pending"></span> Belum FU</div>
                <div class="taste-metric-val" style="color:#B45309;"><?php echo number_format($stat_belum_fu); ?></div>
            </div>
            <div class="taste-metric-icon" style="background:#FEF3C7; color:#D97706;">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </a>

        <!-- 3. Sudah Follow Up -->
        <a href="index.php?filter_fu=sudah<?php echo $filter_sales ? '&filter_sales=' . $filter_sales : ''; ?>#customer-section" class="taste-metric-item <?php if ($filter_fu === 'sudah') echo 'active-metric'; ?>" title="Klik untuk memfilter customer sudah follow up">
            <div class="taste-metric-info">
                <div class="taste-metric-label" style="color:#2563EB;"><span class="indicator-dot dot-fu"></span> Sudah FU</div>
                <div class="taste-metric-val" style="color:#1D4ED8;"><?php echo number_format($stat_sudah_fu); ?></div>
            </div>
            <div class="taste-metric-icon" style="background:#EFF6FF; color:#2563EB;">
                <i class="bi bi-chat-left-dots-fill"></i>
            </div>
        </a>

        <!-- 4. Kandidat -->
        <a href="kandidat_customer.php?filter=kandidat" class="taste-metric-item" title="Buka Halaman Kandidat Customer">
            <div class="taste-metric-info">
                <div class="taste-metric-label" style="color:#7C3AED;"><span class="indicator-dot dot-kandidat"></span> Kandidat</div>
                <div class="taste-metric-val" style="color:#6D28D9;"><?php echo number_format($stat_kandidat); ?></div>
            </div>
            <div class="taste-metric-icon" style="background:#FAF5FF; color:#7C3AED;">
                <i class="bi bi-star-fill"></i>
            </div>
        </a>

        <!-- 5. Deal -->
        <a href="kandidat_customer.php?filter=acc_boss" class="taste-metric-item" title="Buka Halaman Customer Deal">
            <div class="taste-metric-info">
                <div class="taste-metric-label" style="color:#059669;"><span class="indicator-dot dot-deal"></span> Deal Closing</div>
                <div class="taste-metric-val" style="color:#047857;"><?php echo number_format($stat_deal); ?></div>
            </div>
            <div class="taste-metric-icon" style="background:#ECFDF5; color:#059669;">
                <i class="bi bi-patch-check-fill"></i>
            </div>
        </a>
    </div>

    <!-- Filter Control Card -->
    <div class="taste-filter-card">
        <form method="GET" action="index.php#customer-section" id="index-filter-form">
            <div class="row g-2.5 align-items-end mb-2.5">
                <!-- Search Input -->
                <div class="col-lg-6 col-md-12 col-12">
                    <label for="search" class="taste-filter-label">
                        <i class="bi bi-search text-primary"></i> Cari Toko / PIC / No HP
                    </label>
                    <div class="taste-input-group">
                        <i class="bi bi-search taste-input-icon"></i>
                        <input type="text" name="search" id="search" class="taste-input" placeholder="Ketik nama toko, PIC, atau no telp..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                    </div>
                </div>

                <!-- Filter Kota -->
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="filter_kota" class="taste-filter-label">
                        <i class="bi bi-geo-alt-fill text-danger"></i> Kota / Daerah
                    </label>
                    <select name="filter_kota" id="filter_kota" class="form-select taste-select">
                        <option value="">Semua Daerah / Kota</option>
                        <optgroup label="📍 REGION & PROVINSI">
                            <option value="Jawa Barat" <?php if ($filter_kota === 'Jawa Barat') echo 'selected'; ?>>🏞️ Jawa Barat</option>
                            <option value="Jawa Tengah" <?php if ($filter_kota === 'Jawa Tengah') echo 'selected'; ?>>🏯 Jawa Tengah</option>
                            <option value="Jawa Timur" <?php if ($filter_kota === 'Jawa Timur') echo 'selected'; ?>>🌊 Jawa Timur</option>
                            <option value="Jakarta Timur" <?php if ($filter_kota === 'Jakarta Timur') echo 'selected'; ?>>📍 Jakarta Timur</option>
                            <option value="Jakarta Barat" <?php if ($filter_kota === 'Jakarta Barat') echo 'selected'; ?>>📍 Jakarta Barat</option>
                            <option value="Jakarta Selatan" <?php if ($filter_kota === 'Jakarta Selatan') echo 'selected'; ?>>📍 Jakarta Selatan</option>
                            <option value="Jakarta Utara" <?php if ($filter_kota === 'Jakarta Utara') echo 'selected'; ?>>📍 Jakarta Utara</option>
                            <option value="Jakarta Pusat" <?php if ($filter_kota === 'Jakarta Pusat') echo 'selected'; ?>>📍 Jakarta Pusat</option>
                            <option value="Jakarta" <?php if ($filter_kota === 'Jakarta') echo 'selected'; ?>>🏢 DKI Jakarta (Semua)</option>
                            <option value="Banten" <?php if ($filter_kota === 'Banten') echo 'selected'; ?>>🏙️ Banten</option>
                            <option value="Yogyakarta" <?php if ($filter_kota === 'Yogyakarta') echo 'selected'; ?>>🏰 DI Yogyakarta</option>
                            <option value="Sumatera Utara" <?php if ($filter_kota === 'Sumatera Utara') echo 'selected'; ?>>🌲 Sumatera Utara</option>
                            <option value="Sumatera Selatan" <?php if ($filter_kota === 'Sumatera Selatan') echo 'selected'; ?>>🌴 Sumatera Selatan</option>
                            <option value="Riau" <?php if ($filter_kota === 'Riau') echo 'selected'; ?>>🌴 Riau & Kep. Riau</option>
                            <option value="Lampung" <?php if ($filter_kota === 'Lampung') echo 'selected'; ?>>🐘 Lampung</option>
                            <option value="Bali" <?php if ($filter_kota === 'Bali') echo 'selected'; ?>>🏝️ Bali & Nusa Tenggara</option>
                            <option value="Kalimantan" <?php if ($filter_kota === 'Kalimantan') echo 'selected'; ?>>🌲 Kalimantan</option>
                            <option value="Sulawesi" <?php if ($filter_kota === 'Sulawesi') echo 'selected'; ?>>🏝️ Sulawesi</option>
                        </optgroup>
                        <optgroup label="🏙️ DAFTAR KOTA">
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>" <?php if ($filter_kota === $city) echo 'selected'; ?>>
                                    🏙️ <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <!-- Filter Kategori -->
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="filter_kategori" class="taste-filter-label">
                        <i class="bi bi-tags-fill text-primary"></i> Kategori
                    </label>
                    <select name="filter_kategori" id="filter_kategori" class="form-select taste-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($filter_kategori === $cat) echo 'selected'; ?>>
                                🏷️ <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-2.5 align-items-end">
                <!-- Filter Sales -->
                <?php if ($_SESSION['role'] !== 'sales'): ?>
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="filter_sales" class="taste-filter-label">
                        <i class="bi bi-person-badge-fill text-info"></i> Sales PIC
                    </label>
                    <select name="filter_sales" id="filter_sales" class="form-select taste-select">
                        <option value="">Semua Sales</option>
                        <?php foreach ($all_sales as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php if ($filter_sales === intval($s['id'])) echo 'selected'; ?>>
                                👤 <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Filter Status Follow Up -->
                <div class="<?php echo ($_SESSION['role'] !== 'sales') ? 'col-lg-3 col-md-6' : 'col-lg-4 col-md-6'; ?> col-12">
                    <label for="filter_fu" class="taste-filter-label">
                        <i class="bi bi-telephone-outbound-fill text-success"></i> Status Follow Up
                    </label>
                    <select name="filter_fu" id="filter_fu" class="form-select taste-select">
                        <option value="">Semua Status FU</option>
                        <option value="sudah" <?php if ($filter_fu === 'sudah') echo 'selected'; ?>>✅ Sudah Di-FU</option>
                        <option value="belum" <?php if ($filter_fu === 'belum') echo 'selected'; ?>>⏳ Belum Di-FU</option>
                    </select>
                </div>

                <!-- Entri Per Halaman -->
                <div class="<?php echo ($_SESSION['role'] !== 'sales') ? 'col-lg-2 col-md-6' : 'col-lg-3 col-md-6'; ?> col-12">
                    <label for="limit" class="taste-filter-label">
                        <i class="bi bi-layers-fill text-primary"></i> Entri Per Halaman
                    </label>
                    <select name="limit" id="limit" class="form-select taste-select">
                        <option value="20" <?php if ($limit == 20) echo 'selected'; ?>>20 data</option>
                        <option value="25" <?php if ($limit == 25) echo 'selected'; ?>>25 data</option>
                        <option value="50" <?php if ($limit == 50) echo 'selected'; ?>>50 data</option>
                        <option value="100" <?php if ($limit == 100) echo 'selected'; ?>>100 data</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="<?php echo ($_SESSION['role'] !== 'sales') ? 'col-lg-4 col-md-12' : 'col-lg-5 col-md-12'; ?> col-12 d-flex gap-2">
                    <button type="submit" class="taste-btn-apply flex-grow-1">
                        <i class="bi bi-funnel-fill"></i> Terapkan Filter
                    </button>
                    <?php if (!empty($search_keyword) || !empty($filter_kota) || !empty($filter_kategori) || $filter_sales > 0 || !empty($filter_fu) || $limit != 25): ?>
                        <a href="index.php#customer-section" class="taste-btn-reset" title="Reset Filter">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div id="notification" class="alert" style="display:none;"></div>

    <!-- Data Table Card Container -->
    <div class="taste-table-card">
        <div class="taste-table-responsive" id="customer-table-container">
            <table class="table align-middle taste-table mb-0">
                <thead>
                    <tr>
                        <th style="min-width: 190px;">NAMA TOKO</th>
                        <th style="min-width: 190px;">PIC & KONTAK</th>
                        <th style="min-width: 100px;">KATEGORI</th>
                        <th style="min-width: 120px;">KOTA</th>
                        <th style="min-width: 130px;">SALES</th>
                        <th class="text-center" style="min-width: 60px;">FU</th>
                        <th class="text-center" style="min-width: 75px;">KANDIDAT</th>
                        <th class="text-center" style="min-width: 65px;">DEAL</th>
                        <th class="text-center" style="min-width: 55px;">MAPS</th>
                        <th class="text-center" style="min-width: 150px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr id="customer-row-<?php echo $customer['id']; ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="taste-shop-icon">
                                        <i class="bi bi-shop"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size:13.5px; line-height:1.35;">
                                            <?php echo htmlspecialchars($customer['nama_toko']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $pics = !empty($customer['all_pics']) ? explode('||', $customer['all_pics']) : [];
                                $phones = !empty($customer['all_phones']) ? explode('||', $customer['all_phones']) : [];
                                if (!empty($pics)) {
                                    foreach ($pics as $key => $pic_name) {
                                        $phone_number = $phones[$key] ?? '';
                                        $display_pic = trim($pic_name);
                                        $show_name = ($display_pic !== '' && strtolower($display_pic) !== 'unknown' && strtolower($display_pic) !== strtolower(trim($customer['nama_toko'])));
                                        
                                        echo '<div class="d-flex align-items-center flex-wrap gap-1.5 small fw-semibold text-dark my-1">';
                                        if ($show_name) {
                                            echo '<span class="d-inline-flex align-items-center text-muted" style="font-size:11.5px;"><i class="bi bi-person-fill me-1 text-primary"></i>' . htmlspecialchars($display_pic) . '</span>';
                                        }
                                        if (!empty($phone_number)) {
                                            $cleaned_tel = preg_replace('/[^0-9]/', '', $phone_number);
                                            $wa_number = (substr($cleaned_tel, 0, 1) === '0') ? '62' . substr($cleaned_tel, 1) : $cleaned_tel;
                                            echo '<a href="https://wa.me/' . $wa_number . '" target="_blank" class="taste-wa-pill"><i class="bi bi-whatsapp"></i> ' . htmlspecialchars($phone_number) . '</a>';
                                        }
                                        echo '</div>';
                                    }
                                } else { echo '<span class="text-muted small">-</span>'; }
                                ?>
                            </td>
                            <td>
                                <span class="taste-category-pill"><?php echo htmlspecialchars($customer['kategori'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <?php 
                                $city_val = trim($customer['all_cities'] ?? '');
                                if (!empty($city_val) && $city_val !== '-'): 
                                ?>
                                    <span class="taste-city-pill">
                                        📍 <?php echo htmlspecialchars($city_val); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($customer['nama_sales']): ?>
                                    <div class="d-flex align-items-center gap-2" style="white-space:nowrap;">
                                        <div class="taste-sales-badge">
                                            <?php echo strtoupper(substr($customer['nama_sales'], 0, 1)); ?>
                                        </div>
                                        <span class="fw-semibold text-dark" style="font-size:12.5px;"><?php echo htmlspecialchars($customer['nama_sales']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark fw-bold rounded-pill px-2.5 py-1" style="font-size:11px;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Belum Di-assign</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" title="<?php echo ($customer['total_fu_all_time'] > $customer['fu_count']) ? 'Di-FU sales saat ini: ' . $customer['fu_count'] . 'x (Total riwayat lama: ' . $customer['total_fu_all_time'] . 'x)' : 'Lihat Riwayat Follow Up (' . $customer['fu_count'] . ')'; ?>" class="text-decoration-none">
                                    <span class="taste-fu-pill <?php echo $customer['fu_count'] > 0 ? '' : 'empty'; ?>"><?php echo $customer['fu_count']; ?></span>
                                </a>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center mb-0"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="kandidat" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['kandidat'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <td class="text-center">
                               <div class="form-check form-switch d-flex justify-content-center mb-0"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="deal" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['deal'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($customer['primary_map_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($customer['primary_map_link']); ?>" target="_blank" class="taste-icon-btn map" title="Buka di Google Maps"><i class="bi bi-geo-alt-fill"></i></a>
                                <?php else: ?>
                                    <button class="taste-icon-btn map-disabled" disabled title="Tidak ada koordinat maps"><i class="bi bi-geo-alt"></i></button>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1.5" style="white-space:nowrap;">
                                    <a href="followup_add.php?customer_id=<?php echo $customer['id']; ?>" class="btn-quick-fu" title="Tambah Follow Up Baru">
                                        <i class="bi bi-plus-circle-fill"></i> + FU
                                    </a>
                                    <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" class="taste-icon-btn view" title="Lihat Riwayat Follow Up"><i class="bi bi-eye-fill"></i></a>
                                    <?php 
                                    $can_edit_delete = ($_SESSION['role'] == 'superadmin') || ($_SESSION['role'] == 'sales' && $_SESSION['user_id'] == $customer['sales_id']);
                                    if ($can_edit_delete): 
                                    ?>
                                        <a href="customer_edit.php?id=<?php echo $customer['id']; ?>" class="taste-icon-btn edit" title="Edit Customer"><i class="bi bi-pencil-fill"></i></a>
                                        <a href="customer_delete.php?id=<?php echo $customer['id']; ?>" class="taste-icon-btn delete" title="Hapus Customer" onclick="return confirm('Yakin hapus customer ini?')"><i class="bi bi-trash-fill"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="text-center p-5 text-muted">Belum ada data customer yang sesuai dengan filter ini.</td></tr>
                        <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination Footer -->
        <div class="card-footer bg-white py-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="small text-muted fw-semibold">
                Menampilkan <span class="text-dark fw-bold"><?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $limit, $total_records)); ?></span> dari <span class="text-primary fw-bold"><?php echo number_format($total_records); ?></span> customer
            </div>
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1">
                    <?php
                    $link_base_params = [
                        'filter_kota' => $filter_kota,
                        'filter_kategori' => $filter_kategori,
                        'filter_sales' => $filter_sales,
                        'filter_fu' => $filter_fu,
                        'search' => $search_keyword,
                        'limit' => $limit
                    ];
                    
                    if ($page > 1):
                        $prev_params = array_merge($link_base_params, ['page' => $page - 1]);
                    ?>
                        <li class="page-item"><a class="page-link px-3 py-1.5 rounded-3 fw-bold border bg-light text-dark" href="index.php?<?php echo http_build_query($prev_params); ?>#customer-section"><i class="bi bi-chevron-left me-1"></i> Sebelumnya</a></li>
                    <?php endif; ?>

                    <?php
                    $start_p = max(1, $page - 2);
                    $end_p = min($total_pages, $page + 2);
                    for ($p = $start_p; $p <= $end_p; $p++):
                        $p_params = array_merge($link_base_params, ['page' => $p]);
                        $is_act = ($p == $page);
                    ?>
                        <li class="page-item">
                            <a class="page-link px-3 py-1.5 rounded-3 fw-bold border <?php echo $is_act ? 'bg-primary text-white border-primary shadow-sm' : 'bg-white text-dark'; ?>" href="index.php?<?php echo http_build_query($p_params); ?>#customer-section">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php
                    if ($page < $total_pages):
                        $next_params = array_merge($link_base_params, ['page' => $page + 1]);
                    ?>
                        <li class="page-item"><a class="page-link px-3 py-1.5 rounded-3 fw-bold border bg-light text-dark" href="index.php?<?php echo http_build_query($next_params); ?>#customer-section">Selanjutnya <i class="bi bi-chevron-right ms-1"></i></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal View Question & Answers -->
<div class="modal fade" id="viewQuestionModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:16px; border:none; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.15);">
      <div class="modal-header" style="background:#0F172A; color:#FFF;">
        <h5 class="modal-title fw-bold" id="q-modal-title" style="font-size:15px;"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="bg-light rounded-3 p-3 mb-3 border">
            <p class="mb-2 fw-semibold text-dark" id="q-modal-body" style="font-size:14px; line-height:1.6;"></p>
            <div class="question-meta"><i class="bi bi-person-fill text-primary"></i> Ditanyakan oleh <span id="q-modal-author" class="fw-bold text-dark"></span> pada <span id="q-modal-date"></span></div>
        </div>
        <hr class="my-3">
        <h6 class="fw-bold mb-3 text-dark" style="font-size:14px;"><i class="bi bi-chat-square-text-fill text-primary me-2"></i>Jawaban Tim Sales</h6>
        <div id="q-modal-answers-list"></div>
        <form class="add-answer-form mt-4 bg-white p-3 border rounded-3">
            <input type="hidden" id="q-modal-question-id" name="question_id">
            <div class="mb-3">
                <label class="form-label fw-bold text-dark" style="font-size:13px;">Tulis Jawaban Anda</label>
                <textarea name="body" class="form-control" rows="3" placeholder="Bantu rekan sales Anda dengan jawaban yang jelas..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold"><i class="bi bi-send-fill me-1"></i> Kirim Jawaban</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Add Question -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; border:none; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.15);">
      <div class="modal-header" style="background:#0F172A; color:#FFF;">
        <h5 class="modal-title fw-bold" style="font-size:15px;"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Buat Pertanyaan Baru</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="addQuestionForm">
          <input type="hidden" name="action" value="add_question">
          <div class="mb-3">
            <label for="q_title" class="form-label fw-bold text-dark" style="font-size:13px;">Judul Pertanyaan</label>
            <input type="text" class="form-control fw-semibold" id="q_title" name="title" placeholder="mis. Password standar IP CAM Loewix?" required style="border-radius:10px;">
          </div>
          <div class="mb-3">
            <label for="q_body" class="form-label fw-bold text-dark" style="font-size:13px;">Detail Pertanyaan</label>
            <textarea class="form-control fw-medium" id="q_body" name="body" rows="4" placeholder="Jelaskan detail pertanyaan Anda..." style="border-radius:10px;"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
        <button type="submit" form="addQuestionForm" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="bi bi-send-fill me-1"></i> Kirim Pertanyaan</button>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentUserId = <?php echo $_SESSION['user_id']; ?>;
    const isSuperAdmin = '<?php echo $_SESSION['role']; ?>' === 'superadmin';
    const questionsTable = document.getElementById('questionsTable');
    const tableBody = document.querySelector('.taste-table tbody') || document.querySelector('.brandkit-table tbody') || document.querySelector('table tbody');
    const notification = document.getElementById('notification');

    // Q&A Live Search
    const liveSearchInput = document.getElementById('liveSearchInput');
    if (liveSearchInput) {
        liveSearchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.forum-item-row, .forum-card-item').forEach(card => {
                card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }

    // Forum Container Click Listener
    const forumFeedContainer = document.getElementById('forumFeedContainer');
    if (forumFeedContainer) {
        forumFeedContainer.addEventListener('click', function(e) {
            const deleteButton = e.target.closest('.delete-btn[data-type="question"]');
            if (deleteButton) {
                e.stopPropagation();
                handleDelete(deleteButton.dataset.id, 'question');
            }
        });
    }

    // Populate dan Tampilkan Modal Lihat Pertanyaan
    const viewQuestionModal = document.getElementById('viewQuestionModal');
    window.populateAndShowModal = function(cardEl) {
        if (!cardEl) return;
        const answers = JSON.parse(cardEl.dataset.answers || '[]');
        document.getElementById('q-modal-title').textContent = cardEl.dataset.title || '';
        document.getElementById('q-modal-body').textContent = cardEl.dataset.body || '';
        document.getElementById('q-modal-author').textContent = cardEl.dataset.author || '';
        document.getElementById('q-modal-date').textContent = cardEl.dataset.date || '';
        document.getElementById('q-modal-question-id').value = cardEl.dataset.questionId || '';
        
        const answersList = document.getElementById('q-modal-answers-list');
        answersList.innerHTML = '';
        if (answers.length > 0) {
            answers.forEach(a => {
                const canDelete = isSuperAdmin || currentUserId == a.author_id;
                const deleteButtonHtml = canDelete ? `<button class="btn btn-sm btn-outline-danger delete-btn" data-id="${a.id}" data-type="answer"><i class="bi bi-trash-fill"></i></button>` : '';
                const answerDate = new Date(a.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                answersList.innerHTML += `
                    <div class="card answer-card mb-3" id="answer-${a.id}">
                        <div class="card-body">
                            <p class="card-text text-dark mb-2" style="font-size:14px; line-height:1.5;">${a.body.replace(/\n/g, '<br>')}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="answer-meta"><i class="bi bi-person-circle text-primary me-1"></i> Dijawab oleh ${a.author} pada ${answerDate}</small>
                                <div>${deleteButtonHtml}</div>
                            </div>
                        </div>
                    </div>`;
            });
        } else {
            answersList.innerHTML = '<p class="text-muted fst-italic p-3 text-center">Belum ada jawaban. Jadilah yang pertama memberikan solusi!</p>';
        }
    };

    // Submit Pertanyaan Baru
    document.getElementById('addQuestionForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Pertanyaan Anda telah diposting.' })
                    .then(() => window.location.reload());
                } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
            });
    });

    // Submit Jawaban Baru
    if (viewQuestionModal) {
        viewQuestionModal.addEventListener('submit', function(e) {
            if (e.target.classList.contains('add-answer-form')) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                formData.append('action', 'add_answer');
                fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                       Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Jawaban Anda telah dikirim.' }).then(() => window.location.reload());
                    } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
                });
            }
        });
        
        viewQuestionModal.addEventListener('click', function(e) {
            const target = e.target.closest('.delete-btn[data-type="answer"]');
            if (target) {
                handleDelete(target.dataset.id, 'answer');
            }
        });
    }

    // Fungsi terpusat untuk menghapus Q&A
    function handleDelete(id, type) {
        Swal.fire({
            title: 'Anda yakin?', text: "Data yang dihapus tidak bisa dikembalikan!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Batal', confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete_' + type);
                formData.append('id', id);

                fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const elementId = (type === 'question') ? 'question-row-' + id : type + '-' + id;
                        document.getElementById(elementId)?.remove();
                    } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
                });
            }
        });
    }

    // Customer Status Change Handlers
    function showNotification(message, isSuccess) {
        if (!notification) return;
        notification.textContent = message;
        notification.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        notification.style.display = 'block';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }
    
    if (tableBody) {
        tableBody.addEventListener('change', function(event) {
            if (event.target.classList.contains('status-checkbox')) {
                const checkbox = event.target;
                const customerId = checkbox.dataset.customerId;
                const statusType = checkbox.dataset.type;
                const newStatus = checkbox.checked ? 'Y' : 'N';
                
                fetch('update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        'customer_id': customerId,
                        'status_type': statusType,
                        'status_value': newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, true);
                    } else {
                        showNotification(data.message, false);
                        checkbox.checked = !checkbox.checked;
                    }
                })
                .catch(error => {
                    showNotification('Terjadi kesalahan jaringan.', false);
                    checkbox.checked = !checkbox.checked;
                });
            }
        });
    }

    if ($.fn.select2) {
        $('#filter_kota, #filter_kategori, #filter_sales, #filter_fu, #limit').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownAutoWidth: true
        });
    }
});
</script>