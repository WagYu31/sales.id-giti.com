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
    $params[] = $active_sales_id;
    $types .= 'i';
} elseif ($filter_sales > 0) {
    $active_sales_id = $filter_sales;
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $active_sales_id;
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
    // Normalisasi keyword: hilangkan tanda hubung & tanda baca berlebih untuk pencarian multi-kata yang fleksibel
    $clean_kw = trim(preg_replace('/[\-_,]+/u', ' ', $search_keyword));
    $words = array_values(array_filter(explode(' ', $clean_kw), function($w) { 
        return mb_strlen(trim($w)) >= 2; 
    }));
    
    if (count($words) > 1) {
        $word_conditions = [];
        foreach ($words as $word) {
            $word_like = '%' . $word . '%';
            $word_conditions[] = "(
                c.nama_toko LIKE ? 
                OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?))
                OR c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND (alamat LIKE ? OR kota LIKE ?))
            )";
            array_push($params, $word_like, $word_like, $word_like, $word_like, $word_like);
            $types .= 'sssss';
        }
        $sql_where_conditions[] = "(" . implode(' AND ', $word_conditions) . ")";
    } else {
        $like_kw = '%' . $search_keyword . '%';
        $sql_where_conditions[] = "(
            c.nama_toko LIKE ? 
            OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?))
            OR c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND (alamat LIKE ? OR kota LIKE ?))
        )";
        array_push($params, $like_kw, $like_kw, $like_kw, $like_kw, $like_kw);
        $types .= 'sssss';
    }
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
        COALESCE(s.nama_lengkap, (SELECT s2.nama_lengkap FROM follow_ups fu2 JOIN sales s2 ON fu2.sales_id = s2.id WHERE fu2.customer_id = c.id AND fu2.deleted_at IS NULL ORDER BY fu2.id DESC LIMIT 1)) AS nama_sales,
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
    $r_sales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL ORDER BY nama_lengkap ASC");
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
    $stats_params[] = (int)$_SESSION['user_id'];
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

// Hitung Total Kali Aktivitas FU (Berapa kali interaksi chat/telepon dilakukan)
$stat_total_aktivitas = 0;
if ($active_sales_id > 0) {
    $stmt_act = $conn->prepare("
        SELECT COUNT(fu.id) AS total_fu
        FROM follow_ups fu
        WHERE fu.sales_id = ? AND fu.deleted_at IS NULL
    ");
    $stmt_act->bind_param("i", $active_sales_id);
    $stmt_act->execute();
    $stat_total_aktivitas = (int)($stmt_act->get_result()->fetch_assoc()['total_fu'] ?? 0);
    $stmt_act->close();
} else {
    $res_all_act = $conn->query("SELECT COUNT(*) AS total_fu FROM follow_ups WHERE deleted_at IS NULL");
    $stat_total_aktivitas = (int)($res_all_act->fetch_assoc()['total_fu'] ?? 0);
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
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
@media (max-width: 1280px) and (min-width: 992px) {
    .taste-metrics-bar {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
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
.dot-activity { background: #0284C7; box-shadow: 0 0 6px rgba(2, 132, 199, 0.6); }
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
    scroll-behavior: smooth;
    position: relative;
}
.taste-table-responsive::-webkit-scrollbar {
    height: 7px;
}
.taste-table-responsive::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 4px;
}
.taste-table-responsive::-webkit-scrollbar-thumb {
    background: #94A3B8;
    border-radius: 10px;
}
.taste-table-responsive::-webkit-scrollbar-thumb:hover {
    background: #64748B;
}
.taste-table {
    width: 100%;
    min-width: 1060px;
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
    padding: 12px 10px !important;
    border: none !important;
    white-space: nowrap !important;
}
.taste-table tbody td {
    padding: 10px 10px !important;
    border-bottom: 1px solid #F1F5F9 !important;
    vertical-align: middle !important;
    background: #FFFFFF;
    font-size: 13px;
    color: #1E293B;
}
.taste-table tbody tr:hover td {
    background: #F8FAFC !important;
}

/* Compact Checkbox Column */
.taste-table .th-col-check,
.taste-table .td-col-check {
    width: 38px;
    min-width: 38px;
    max-width: 42px;
    text-align: center;
    padding-left: 10px !important;
    padding-right: 6px !important;
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

/* WhatsApp Broadcast Styles */
.taste-btn-whatsapp {
    background: linear-gradient(135deg, #10B981, #059669);
    color: #FFFFFF !important;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 18px;
    border-radius: 10px;
    border: 1px solid #059669;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
    cursor: pointer;
    white-space: nowrap;
}
.taste-btn-whatsapp:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
}
.floating-broadcast-bar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1040;
    background: #0F172A;
    color: #FFFFFF;
    border-radius: 50px;
    padding: 8px 18px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    gap: 16px;
    animation: slideUpFloating 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideUpFloating {
    from { opacity: 0; transform: translate(-50%, 20px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}
/* WhatsApp Realistic Phone Mockup */
.wa-mockup-phone {
    border: 1px solid #CBD5E1;
    border-radius: 16px;
    overflow: hidden;
    background: #FFFFFF;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    display: flex;
    flex-direction: column;
}
.wa-mockup-header {
    background: #008069;
    color: #FFFFFF;
    padding: 9px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.wa-mockup-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #FFFFFF;
    color: #008069;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
.wa-mockup-body {
    background: #EFEAE2;
    background-image: radial-gradient(#CBD5E1 1px, transparent 1px);
    background-size: 16px 16px;
    padding: 14px;
    min-height: 280px;
    max-height: 370px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}
.wa-mockup-footer {
    background: #F0F2F5;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-top: 1px solid #E2E8F0;
}
.wa-mockup-input-dummy {
    background: #FFFFFF;
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 11.5px;
    color: #94A3B8;
    flex-grow: 1;
    border: 1px solid #E2E8F0;
}
.target-option-card {
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    padding: 9px 11px;
    background: #FFFFFF;
    cursor: pointer;
    transition: all 0.18s ease;
    height: 100%;
}
.target-option-card:hover {
    border-color: #93C5FD;
    background: #F8FAFC;
}
.target-option-card.active {
    border-color: #10B981;
    background: #ECFDF5;
    box-shadow: 0 2px 10px rgba(16, 185, 129, 0.12);
}
.wa-bubble-sent {
    background: #D9FDD3;
    color: #111B21;
    border-radius: 10px 10px 2px 10px;
    padding: 10px 14px;
    max-width: 92%;
    margin-left: auto;
    box-shadow: 0 1px 2px rgba(0,0,0,0.12);
    font-size: 12.5px;
    line-height: 1.45;
    position: relative;
    white-space: pre-wrap;
    word-break: break-word;
}
.wa-bubble-time {
    text-align: right;
    font-size: 10.5px;
    color: #667781;
    margin-top: 4px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 3px;
}
.tag-var-pill {
    background: #F1F5F9;
    color: #0F172A;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}
.tag-var-pill:hover {
    background: #E2E8F0;
    border-color: #94A3B8;
    transform: translateY(-1px);
}
.tag-var-pill:active {
    transform: scale(0.96);
}
.broadcast-log-console {
    background: #0F172A;
    color: #E2E8F0;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    line-height: 1.55;
    border-radius: 12px;
    padding: 12px;
    max-height: 210px;
    overflow-y: auto;
    white-space: pre-wrap;
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
            <button type="button" class="taste-btn-whatsapp" onclick="openBroadcastModal()" title="Kirim Pesan WhatsApp Massal ke Customer">
                <i class="bi bi-whatsapp"></i>
                <span>Broadcast WA</span>
                <span class="badge bg-white text-success rounded-pill fw-bold" id="badgeSelectedCount" style="display:none; font-size:11px;">0</span>
            </button>
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

        <!-- 3. Toko Sudah Follow Up -->
        <a href="index.php?filter_fu=sudah<?php echo $filter_sales ? '&filter_sales=' . $filter_sales : ''; ?>#customer-section" class="taste-metric-item <?php if ($filter_fu === 'sudah') echo 'active-metric'; ?>" title="Klik untuk memfilter customer yang sudah di-follow up">
            <div class="taste-metric-info">
                <div class="taste-metric-label" style="color:#2563EB;"><span class="indicator-dot dot-fu"></span> Toko Sudah FU</div>
                <div class="taste-metric-val" style="color:#1D4ED8;"><?php echo number_format($stat_sudah_fu); ?></div>
            </div>
            <div class="taste-metric-icon" style="background:#EFF6FF; color:#2563EB;">
                <i class="bi bi-shop-window"></i>
            </div>
        </a>

        <!-- 4. Total Kali Follow Up -->
        <a href="followup_report.php<?php echo $filter_sales ? '?filter_sales=' . $filter_sales : ''; ?>" class="taste-metric-item" title="Lihat Riwayat Lengkap Semua Aktivitas Chat/Telepon Follow Up">
            <div class="taste-metric-info">
                <div class="taste-metric-label" style="color:#0284C7;"><span class="indicator-dot dot-activity"></span> Total Kali FU</div>
                <div class="taste-metric-val" style="color:#0369A1;"><?php echo number_format($stat_total_aktivitas); ?></div>
            </div>
            <div class="taste-metric-icon" style="background:#F0F9FF; color:#0284C7;">
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
                        <th class="th-col-check">
                            <input type="checkbox" class="form-check-input" id="checkAllBroadcast" title="Pilih Semua di Halaman Ini" style="cursor: pointer; width: 17px; height: 17px;">
                        </th>
                        <th style="min-width: 180px;">NAMA TOKO</th>
                        <th style="min-width: 180px;">PIC & KONTAK</th>
                        <th style="min-width: 90px;">KATEGORI</th>
                        <th style="min-width: 95px;">KOTA</th>
                        <th style="min-width: 120px;">SALES</th>
                        <th class="text-center" style="min-width: 50px;">FU</th>
                        <th class="text-center" style="min-width: 70px;">KANDIDAT</th>
                        <th class="text-center" style="min-width: 60px;">DEAL</th>
                        <th class="text-center" style="min-width: 50px;">MAPS</th>
                        <th class="text-center" style="min-width: 135px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $customer): ?>
                        <?php
                        $pics = !empty($customer['all_pics']) ? explode('||', $customer['all_pics']) : [];
                        $phones = !empty($customer['all_phones']) ? explode('||', $customer['all_phones']) : [];
                        $primary_phone = '';
                        $primary_pic = '';
                        if (!empty($phones)) {
                            foreach ($phones as $k => $ph) {
                                $cleanPh = preg_replace('/[^0-9]/', '', $ph);
                                if (strlen($cleanPh) >= 9) {
                                    $primary_phone = $cleanPh;
                                    $primary_pic = $pics[$k] ?? '';
                                    break;
                                }
                            }
                        }
                        if (empty($primary_pic) && !empty($pics)) $primary_pic = $pics[0];
                        if (empty($primary_phone) && !empty($phones)) $primary_phone = preg_replace('/[^0-9]/', '', $phones[0]);
                        $city_val = trim($customer['all_cities'] ?? '-');
                        ?>
                        <tr id="customer-row-<?php echo $customer['id']; ?>">
                            <td class="td-col-check text-center">
                                <input type="checkbox" class="form-check-input row-broadcast-check"
                                       value="<?php echo $customer['id']; ?>"
                                       data-id="<?php echo $customer['id']; ?>"
                                       data-toko="<?php echo htmlspecialchars($customer['nama_toko']); ?>"
                                       data-pic="<?php echo htmlspecialchars($primary_pic); ?>"
                                       data-phone="<?php echo htmlspecialchars($primary_phone); ?>"
                                       data-city="<?php echo htmlspecialchars($city_val); ?>"
                                       data-sales="<?php echo htmlspecialchars($customer['nama_sales'] ?? 'Sales Loewix'); ?>"
                                       style="cursor: pointer; width: 17px; height: 17px;"
                                       <?php echo empty($primary_phone) ? 'disabled title="Customer ini tidak memiliki nomor WhatsApp"' : 'title="Pilih untuk WhatsApp Broadcast"'; ?>>
                            </td>
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
                            <tr><td colspan="11" class="text-center p-5 text-muted">Belum ada data customer yang sesuai dengan filter ini.</td></tr>
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

<!-- Floating Action Bar for Selected Broadcast -->
<div id="floatingBroadcastBar" class="floating-broadcast-bar" style="display:none;">
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success rounded-pill px-3 py-1.5 fw-bold" id="floatingSelectedBadge" style="font-size: 12px;">0 Toko Terpilih</span>
        <span class="text-white-50 small d-none d-md-inline" style="font-size: 12px;">Siap untuk WhatsApp broadcast</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-semibold" style="font-size: 12px;" onclick="clearSelectedBroadcast()">
            Batal
        </button>
        <button type="button" class="btn btn-sm btn-success rounded-pill px-3.5 py-1.5 fw-bold shadow-sm d-inline-flex align-items-center gap-1.5" style="font-size: 12.5px;" onclick="openBroadcastModal('selected')">
            <i class="bi bi-whatsapp"></i> Broadcast WA Terpilih
        </button>
    </div>
</div>

<!-- Modal WhatsApp Broadcast & Mass Follow-Up (OpenWA 8-Layer Anti-Ban Pro) -->
<div class="modal fade" id="modalWaBroadcast" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      
      <!-- Modal Header -->
      <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);">
        <div class="d-flex align-items-center gap-2.5">
          <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: rgba(16, 185, 129, 0.2); color: #10B981; font-size: 18px;">
            <i class="bi bi-whatsapp"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold mb-0" style="font-size: 16px;">WhatsApp Broadcast &amp; Smart Anti-Ban Engine</h5>
            <small class="text-white-50" style="font-size: 11.5px;">OpenWA Self-Hosted (8 Lapis Anti-Ban &amp; Rotasi 4 Nomor Sales) + Asisten 1-Klik</small>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="btnCloseBroadcastModal"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4" style="background: #F8FAFC;">
        
        <!-- Screen 1: Konfigurasi & Draft Pesan -->
        <div id="broadcastSetupScreen">
          <div class="row g-3">
            
            <!-- Kolom Kiri: Form Target, Pesan & Metode -->
            <div class="col-lg-7 col-12 d-flex flex-column gap-2.5">
              
              <!-- 1. Pilihan Sasaran Target -->
              <div class="card border border-light-subtle shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size: 13px;">
                    <i class="bi bi-people-fill text-primary"></i> 1. Sasaran Target Penerima
                  </span>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fw-bold" id="badgeTargetSummary" style="font-size: 11px;">
                    0 Target Siap
                  </span>
                </div>
                
                <div class="row g-2 mb-1">
                  <div class="col-sm-6 col-12">
                    <div class="target-option-card active d-flex align-items-center gap-2" id="cardTargetSelected" onclick="setBroadcastTargetMode('selected')">
                      <input class="form-check-input mt-0" type="radio" name="target_mode" id="targetModeSelected" value="selected" checked onchange="handleTargetModeChange()" style="cursor: pointer; width: 16px; height: 16px;">
                      <div class="overflow-hidden">
                        <div class="fw-bold text-dark text-truncate" style="font-size: 12px;">Toko Terpilih</div>
                        <div class="text-muted" style="font-size: 11px;"><span id="txtCountSelected" class="fw-bold text-primary">0</span> toko dicentang di tabel</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-sm-6 col-12">
                    <div class="target-option-card d-flex align-items-center gap-2" id="cardTargetFilter" onclick="setBroadcastTargetMode('filter')">
                      <input class="form-check-input mt-0" type="radio" name="target_mode" id="targetModeFilter" value="filter" onchange="handleTargetModeChange()" style="cursor: pointer; width: 16px; height: 16px;">
                      <div class="overflow-hidden">
                        <div class="fw-bold text-dark text-truncate" style="font-size: 12px;">Sesuai Filter Halaman</div>
                        <div class="text-muted" style="font-size: 11px;">Maks. 100 toko per antrean</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div id="targetWarningNoPhone" class="alert alert-warning py-1 px-2 rounded-3 mb-0 mt-1 small d-none" style="font-size: 11px;">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i> Beberapa toko yang belum memiliki nomor WA akan dilewati secara otomatis.
                </div>
              </div>

              <!-- 2. Template Cepat, Tag Variabel & Spintax Anti-Ban -->
              <div class="card border border-light-subtle shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                  <span class="fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size: 13px;">
                    <i class="bi bi-chat-quote-fill text-success"></i> 2. Template &amp; Spintax Anti-Duplikat
                  </span>
                  <select class="form-select form-select-sm fw-semibold w-auto" id="selectQuickTemplate" onchange="applyQuickTemplate()" style="font-size: 11.5px; border-radius: 8px; max-width: 250px; background-color: #F8FAFC;">
                    <option value="">-- Pilih Template Cepat --</option>
                    <option value="1">Template 1: Sapaan Singkat &amp; Katalog (Anti-Ban)</option>
                    <option value="2">Template 2: Follow Up Promo &amp; Stok</option>
                    <option value="3">Template 3: Program Mitra Installer</option>
                  </select>
                </div>

                <div class="mb-2">
                  <small class="text-muted d-block mb-1 fw-semibold" style="font-size: 11px;">
                    <i class="bi bi-cursor-fill text-primary me-0.5"></i> Sisipkan variabel &amp; variasi acak Spintax <code>{A|B|C}</code> agar tiap pesan unik:
                  </small>
                  <div class="d-flex flex-wrap gap-1.5">
                    <button type="button" class="tag-var-pill d-inline-flex align-items-center gap-1" onclick="insertVariableTag('{nama_toko}')">
                      <i class="bi bi-shop text-primary" style="font-size: 11px;"></i> {nama_toko}
                    </button>
                    <button type="button" class="tag-var-pill d-inline-flex align-items-center gap-1" onclick="insertVariableTag('{nama_pic}')">
                      <i class="bi bi-person-fill text-success" style="font-size: 11px;"></i> {nama_pic}
                    </button>
                    <button type="button" class="tag-var-pill d-inline-flex align-items-center gap-1" onclick="insertVariableTag('{kota}')">
                      <i class="bi bi-geo-alt-fill text-danger" style="font-size: 11px;"></i> {kota}
                    </button>
                    <button type="button" class="tag-var-pill d-inline-flex align-items-center gap-1" onclick="insertVariableTag('{nama_sales}')">
                      <i class="bi bi-briefcase-fill text-warning" style="font-size: 11px;"></i> {nama_sales}
                    </button>
                    <button type="button" class="tag-var-pill d-inline-flex align-items-center gap-1" onclick="insertVariableTag('{salam_waktu}')">
                      <i class="bi bi-clock-fill text-info" style="font-size: 11px;"></i> {salam_waktu}
                    </button>
                    <button type="button" class="tag-var-pill d-inline-flex align-items-center gap-1" style="background:#ECFDF5; border-color:#A7F3D0; color:#065F46;" onclick="insertVariableTag('{Halo|Selamat {salam_waktu}|Permisi|Salam kenal}')" title="Sisipkan variasi sapaan acak otomatis">
                      <i class="bi bi-shuffle" style="font-size: 11px;"></i> + Spintax Sapaan
                    </button>
                  </div>
                </div>

                <div>
                  <textarea id="broadcastMessageText" class="form-control" rows="6" placeholder="Tulis pesan broadcast Anda... Gunakan {Halo|Selamat siang|Permisi} dan {nama_toko} agar setiap pesan unik & aman dari filter spam WhatsApp." style="border-radius: 10px; font-size: 12.5px; line-height: 1.5; min-height: 145px; height: 145px; resize: vertical;"></textarea>
                  <div class="d-flex justify-content-between align-items-center mt-1 px-1">
                    <small class="text-muted" style="font-size: 10.5px;">
                      Format: <code>*tebal*</code> &bull; <code>_miring_</code> &bull; Spintax: <code>{opsi1|opsi2}</code>
                    </small>
                    <small class="text-muted fw-semibold" id="txtCharCount" style="font-size: 10.5px;">0 karakter</small>
                  </div>
                </div>
              </div>

              <!-- 3. Metode Pengiriman: OpenWA Anti-Ban Pro vs Asisten 1-Klik vs Fonnte -->
              <div class="card border border-light-subtle shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                  <div class="fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size: 13px;">
                    <i class="bi bi-shield-fill-check text-success"></i> 3. Mesin Pengirim &amp; Proteksi Anti-Ban
                  </div>
                  <span class="badge rounded-pill bg-light text-secondary border px-2.5 py-1" id="openwaModalHealthPill" style="font-size: 10.5px;">
                    <span class="spinner-border spinner-border-sm me-1" style="width:9px;height:9px;"></span> Cek OpenWA...
                  </span>
                </div>

                <div class="row g-2 mb-2">
                  <div class="col-md-5 col-12">
                    <div class="target-option-card active d-flex align-items-start gap-2" id="cardMethodOpenwa" onclick="setBroadcastSendMethod('openwa')">
                      <input class="form-check-input mt-1" type="radio" name="send_method" id="sendMethodOpenwa" value="openwa" checked onchange="handleSendMethodChange()" style="cursor: pointer; width: 16px; height: 16px;">
                      <div>
                        <div class="fw-bold text-success" style="font-size: 12px;">
                          <i class="bi bi-shield-lock-fill"></i> OpenWA Anti-Ban Pro
                        </div>
                        <div class="text-muted" style="font-size: 10px; line-height: 1.35;">
                          Otomatis, rotasi 4 nomor sales, pre-check WA, typing &amp; jeda acak.
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4 col-12">
                    <div class="target-option-card d-flex align-items-start gap-2" id="cardMethodDirect" onclick="setBroadcastSendMethod('direct')">
                      <input class="form-check-input mt-1" type="radio" name="send_method" id="sendMethodDirect" value="direct" onchange="handleSendMethodChange()" style="cursor: pointer; width: 16px; height: 16px;">
                      <div>
                        <div class="fw-bold text-primary" style="font-size: 12px;">
                          <i class="bi bi-whatsapp"></i> Asisten 1-Klik
                        </div>
                        <div class="text-muted" style="font-size: 10px; line-height: 1.35;">
                          Manual via WA resmi laptop/HP. 0% risiko.
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3 col-12">
                    <div class="target-option-card d-flex align-items-start gap-2" id="cardMethodGateway" onclick="setBroadcastSendMethod('gateway')">
                      <input class="form-check-input mt-1" type="radio" name="send_method" id="sendMethodGateway" value="gateway" onchange="handleSendMethodChange()" style="cursor: pointer; width: 16px; height: 16px;">
                      <div>
                        <div class="fw-bold text-secondary" style="font-size: 12px;">
                          <i class="bi bi-robot"></i> Fonnte
                        </div>
                        <div class="text-muted" style="font-size: 10px; line-height: 1.35;">
                          Gateway lama.
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Panel 8 Lapis Anti-Ban OpenWA -->
                <div id="boxOpenwaSettings" class="pt-2.5 border-top mt-1">
                  <div id="openwaSetupAlertModal" class="alert alert-warning py-2 px-2.5 rounded-3 small mb-2.5 d-none" style="font-size: 11px;">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <span id="openwaSetupAlertText">OpenWA belum siap.</span>
                    <?php if (strtolower(trim($_SESSION['role'] ?? '')) === 'superadmin'): ?>
                      <a href="wa_support_settings.php" class="fw-bold text-dark ms-1">Buka Pengaturan OpenWA &rarr;</a>
                    <?php endif; ?>
                  </div>

                  <div class="row g-2 mb-2">
                    <div class="col-sm-6 col-12">
                      <label class="fw-bold text-dark mb-1 d-block" style="font-size: 11px;">
                        <i class="bi bi-arrow-repeat text-success"></i> Nomor Pengirim (Multi-Session):
                      </label>
                      <select id="openwaSessionSelect" class="form-select form-select-sm fw-semibold" style="border-radius: 8px; font-size: 11.5px;">
                        <option value="auto_rotate" selected>🔄 Rotasi Otomatis Semua Nomor Ready (Paling Aman)</option>
                        <option value="my_sales">👤 Khusus Nomor Sales Saya Saja</option>
                      </select>
                    </div>
                    <div class="col-sm-6 col-12">
                      <label class="fw-bold text-dark mb-1 d-block" style="font-size: 11px;">
                        <i class="bi bi-stopwatch-fill text-primary"></i> Profil Jeda Acak &amp; Istirahat Batch:
                      </label>
                      <select id="openwaPacingProfile" class="form-select form-select-sm fw-semibold" style="border-radius: 8px; font-size: 11.5px;">
                        <option value="ultra" selected>🛡️ Ultra Aman (Acak 25–50 dtk • Istirahat 3 mnt / 10 pesan)</option>
                        <option value="normal">⚖️ Normal Aman (Acak 15–30 dtk • Istirahat 2 mnt / 15 pesan)</option>
                        <option value="fast">⚡ Cepat (Acak 8–18 dtk • Istirahat 1 mnt / 20 pesan)</option>
                      </select>
                    </div>
                  </div>

                  <div class="p-2.5 rounded-3 bg-light border" style="font-size: 11px;">
                    <div class="row g-1.5">
                      <div class="col-sm-6 col-12">
                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" id="chkOpenwaPrecheck" checked>
                          <label class="form-check-label fw-semibold text-dark" for="chkOpenwaPrecheck">Pre-Check Nomor WA (Skip nomor mati)</label>
                        </div>
                      </div>
                      <div class="col-sm-6 col-12">
                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" id="chkOpenwaDedup" checked>
                          <label class="form-check-label fw-semibold text-dark" for="chkOpenwaDedup">Anti-Spam Ganda (<span id="txtDedupDaysLabel">7</span> hari)</label>
                        </div>
                      </div>
                      <div class="col-sm-6 col-12">
                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" id="chkOpenwaTyping" checked>
                          <label class="form-check-label fw-semibold text-dark" for="chkOpenwaTyping">Simulasi Mengetik (<code>typing...</code>)</label>
                        </div>
                      </div>
                      <div class="col-sm-6 col-12">
                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" id="chkOpenwaSpintax" checked>
                          <label class="form-check-label fw-semibold text-dark" for="chkOpenwaSpintax">Spintax + Hash Unik Tiap Pesan</label>
                        </div>
                      </div>
                      <div class="col-sm-6 col-12">
                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" id="chkOpenwaLogFu" checked>
                          <label class="form-check-label fw-semibold text-dark" for="chkOpenwaLogFu">Catat Otomatis ke Follow-Up CRM</label>
                        </div>
                      </div>
                      <div class="col-sm-6 col-12">
                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" id="chkOpenwaWorkHours">
                          <label class="form-check-label fw-semibold text-dark" for="chkOpenwaWorkHours">Kunci Jam Kerja (08:00–20:00 WIB)</label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Opsi Tambahan untuk Bot Gateway Fonnte (Legacy) -->
                <div id="boxGatewaySettings" class="d-none pt-2 border-top mt-2">
                  <div class="row g-2 align-items-center">
                    <div class="col-sm-6 col-12">
                      <label class="fw-semibold small text-dark mb-1 d-block" style="font-size: 11.5px;">Jeda Aman Antar Pesan (Fonnte):</label>
                      <select id="broadcastDelaySelect" class="form-select form-select-sm fw-semibold" style="border-radius: 8px;">
                        <option value="10">10 Detik</option>
                        <option value="20" selected>20 Detik (Disarankan)</option>
                        <option value="35">35 Detik (Extra Aman)</option>
                      </select>
                    </div>
                    <div class="col-sm-6 col-12">
                      <div class="form-check mt-sm-3 pt-sm-1">
                        <input class="form-check-input" type="checkbox" id="checkAutoLogFu" checked style="cursor: pointer; width: 15px; height: 15px;">
                        <label class="form-check-label small fw-semibold text-dark ms-1" for="checkAutoLogFu" style="font-size: 11.5px; cursor: pointer;">
                          Catat ke Follow-Up CRM
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

            </div>

            <!-- Kolom Kanan: Live Preview Smartphone WhatsApp -->
            <div class="col-lg-5 col-12">
              <div class="card border border-light-subtle shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-column">
                
                <!-- Header Preview -->
                <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom gap-1">
                  <div class="fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size: 12.5px; white-space: nowrap;">
                    <i class="bi bi-phone-fill text-success"></i> Pratinjau Balon Chat
                  </div>
                  <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 rounded-pill fw-bold" style="font-size: 10px;" onclick="updateLivePreview()" title="Acak ulang contoh hasil Spintax">
                      <i class="bi bi-shuffle"></i> Acak Spintax
                    </button>
                    <div class="badge bg-light text-secondary border fw-normal text-truncate" style="max-width: 130px; font-size: 10px;" id="lblPreviewCustomerName" title="Contoh Toko">
                      Contoh: Toko CCTV
                    </div>
                  </div>
                </div>

                <!-- Realistic WhatsApp Phone Mockup -->
                <div class="wa-mockup-phone flex-grow-1">
                  
                  <!-- WhatsApp Top Header -->
                  <div class="wa-mockup-header">
                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                      <i class="bi bi-arrow-left text-white" style="font-size: 13px;"></i>
                      <div class="wa-mockup-avatar shadow-sm">
                        <i class="bi bi-shop"></i>
                      </div>
                      <div class="overflow-hidden">
                        <div class="fw-bold text-white text-truncate" style="font-size: 12px; line-height: 1.2;" id="waPhoneHeaderName">
                          Pamungkas CCTV
                        </div>
                        <div class="text-white-50" style="font-size: 9.5px; line-height: 1;">mengetik... (simulasi anti-ban)</div>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-white-50" style="font-size: 13px;">
                      <i class="bi bi-telephone-fill"></i>
                      <i class="bi bi-three-dots-vertical"></i>
                    </div>
                  </div>

                  <!-- WhatsApp Chat Scroll Body -->
                  <div class="wa-mockup-body flex-grow-1">
                    <div class="wa-bubble-sent shadow-sm">
                      <div id="waBubbleContent">
                        Halo! Ini adalah contoh pesan broadcast.
                      </div>
                      <div class="wa-bubble-time">
                        <span id="waBubbleClock">12:00</span>
                        <i class="bi bi-check2-all text-primary" style="font-size: 13px;"></i>
                      </div>
                    </div>
                  </div>

                  <!-- WhatsApp Input Bottom Bar -->
                  <div class="wa-mockup-footer">
                    <i class="bi bi-emoji-smile text-muted" style="font-size: 15px;"></i>
                    <div class="wa-mockup-input-dummy">Ketik pesan...</div>
                    <i class="bi bi-paperclip text-muted" style="font-size: 15px;"></i>
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 26px; height: 26px; background: #008069; font-size: 11px;">
                      <i class="bi bi-mic-fill"></i>
                    </div>
                  </div>

                </div>

                <div class="p-2 rounded-3 bg-light mt-2 border small text-muted d-flex align-items-center gap-1.5" style="font-size: 10.5px;">
                  <i class="bi bi-shield-check text-success fs-6 flex-shrink-0"></i>
                  <span>Setiap penerima mendapat <strong>variasi Spintax &amp; fingerprint pesan berbeda</strong> agar aman dari deteksi spam WhatsApp.</span>
                </div>

              </div>
            </div>

          </div>
        </div>

        <!-- Screen 2A: Mode Asisten 1-Klik WhatsApp Dispatcher -->
        <div id="broadcastDirectAssistantScreen" class="d-none">
          <div class="card border border-light-subtle shadow-sm rounded-4 p-4 bg-white text-center mb-3">
            
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
              <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 12px;">
                  <i class="bi bi-shield-check-fill me-1"></i> Asisten 1-Klik Aktif
                </span>
              </div>
              <div class="text-muted small fw-bold">
                Target: <span id="directCurrentIndexText" class="text-dark fs-6">1</span> dari <span id="directTotalCountText" class="text-dark fs-6">0</span> Toko
              </div>
            </div>

            <div class="progress mb-3" style="height: 10px; border-radius: 50px; background: #E2E8F0;">
              <div class="progress-bar bg-success" id="directProgressBar" role="progressbar" style="width: 0%;"></div>
            </div>

            <div class="card border border-2 border-success-subtle rounded-4 p-3.5 mb-3 bg-success-subtle bg-opacity-10 text-start shadow-sm">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <div class="badge bg-success text-white rounded-pill px-2.5 py-1 mb-1 fw-bold" style="font-size: 10.5px;">
                    🎯 TOKO YANG SEDANG DIPROSES
                  </div>
                  <h5 class="fw-bold text-dark mb-0" id="directTargetStoreName">Nama Toko Customer</h5>
                </div>
                <div class="text-end">
                  <span class="taste-wa-pill fs-6 px-3 py-1">
                    <i class="bi bi-whatsapp"></i> <span id="directTargetPhone">0812xxxx</span>
                  </span>
                </div>
              </div>
              
              <div class="d-flex flex-wrap gap-3 small text-muted mb-2.5 pt-1">
                <span><i class="bi bi-person-fill text-primary me-1"></i> PIC: <strong class="text-dark" id="directTargetPic">Bpk/Ibu</strong></span>
                <span><i class="bi bi-geo-alt-fill text-danger me-1"></i> Kota: <strong class="text-dark" id="directTargetCity">-</strong></span>
              </div>

              <div class="p-2.5 rounded-3 bg-white border text-dark small" style="font-size: 12px; max-height: 120px; overflow-y: auto; white-space: pre-wrap;" id="directTargetMessagePreview">
                Pratinjau pesan yang siap dikirim...
              </div>
            </div>

            <div class="mb-3">
              <button type="button" class="btn btn-success btn-lg rounded-pill px-5 py-3 fw-bold shadow d-inline-flex align-items-center gap-2.5" id="btnDirectSendCurrent" onclick="dispatchDirectCurrentTarget()" style="font-size: 16px;">
                <i class="bi bi-whatsapp fs-4"></i>
                <span>BUKA WHATSAPP &amp; KIRIM KE TOKO INI</span>
              </button>
              <div class="small text-muted mt-2">
                <i class="bi bi-info-circle me-1"></i> WhatsApp akan terbuka otomatis dengan chat toko &amp; pesan terketik rapi. Tekan <strong>Enter/Kirim</strong> di WA Anda!
              </div>
            </div>

            <div class="d-flex justify-content-center gap-2 pt-2 border-top">
              <button type="button" class="btn btn-light rounded-pill px-3.5 fw-semibold text-secondary btn-sm" onclick="skipDirectCurrentTarget()">
                <i class="bi bi-skip-forward-fill me-1"></i> Lewati Toko Ini
              </button>
              <button type="button" class="btn btn-outline-secondary rounded-pill px-3.5 fw-semibold btn-sm" onclick="finishBroadcastReload()">
                <i class="bi bi-stop-circle me-1"></i> Selesai / Tutup
              </button>
            </div>

          </div>
        </div>

        <!-- Screen 2B: Real-Time Anti-Ban Sending Progress (OpenWA & Fonnte) -->
        <div id="broadcastProgressScreen" class="d-none">
          <div class="card border-0 shadow-sm rounded-4 p-4 bg-white text-center mb-2">
            <div class="mb-2">
              <div class="spinner-border text-success" role="status" id="spinnerBroadcasting" style="width: 2.8rem; height: 2.8rem;">
                <span class="visually-hidden">Loading...</span>
              </div>
              <div class="rounded-circle bg-success text-white d-none align-items-center justify-content-center mx-auto" id="iconBroadcastDone" style="width: 56px; height: 56px; font-size: 26px;">
                <i class="bi bi-check-lg"></i>
              </div>
            </div>

            <h5 class="fw-bold text-dark mb-1" id="txtProgressHeading">Menjalankan OpenWA Anti-Ban Engine...</h5>
            <p class="text-muted small mb-2" id="txtProgressSub">Mohon jangan menutup tab ini sampai seluruh antrean selesai dikirim dengan aman.</p>

            <!-- Active Sender & Anti-Ban Live Pill -->
            <div class="mb-3">
              <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1.5 fw-bold" id="txtActiveSenderBadge" style="font-size: 11.5px;">
                <i class="bi bi-shield-check me-1"></i> Menyiapkan sesi pengirim...
              </span>
            </div>

            <!-- Progress Bar -->
            <div class="progress mb-2" style="height: 16px; border-radius: 50px; background: #E2E8F0;">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progressBarBroadcast" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center text-muted small fw-semibold px-1 mb-3 gap-2">
              <span>Progress: <strong class="text-dark" id="txtProgressCount">0 / 0</strong></span>
              <span>
                Terkirim: <strong class="text-success" id="txtSuccessCount">0</strong>
                &bull; Di-skip Aman: <strong class="text-primary" id="txtSkipCount">0</strong>
                &bull; Gagal: <strong class="text-danger" id="txtFailCount">0</strong>
              </span>
            </div>

            <!-- Terminal / Console Log -->
            <div class="text-start mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="fw-bold text-dark" style="font-size: 11.5px;">Log Aktivitas Anti-Ban Real-Time:</small>
                <small class="fw-bold text-primary" style="font-size: 11.5px;" id="txtLiveStatus">Menyiapkan antrean...</small>
              </div>
              <div class="broadcast-log-console" id="broadcastConsoleLog">
                [Sistem] Menyiapkan OpenWA 8-Layer Anti-Ban Engine...
              </div>
            </div>

            <!-- Action Buttons during sending -->
            <div class="d-flex justify-content-center flex-wrap gap-2">
              <button type="button" class="btn btn-outline-warning rounded-pill px-4 fw-bold btn-sm" id="btnPauseBroadcast" onclick="togglePauseBroadcastQueue()">
                <i class="bi bi-pause-circle-fill me-1"></i> Jeda Sementara
              </button>
              <button type="button" class="btn btn-outline-danger rounded-pill px-4 fw-bold btn-sm" id="btnCancelBroadcast" onclick="cancelBroadcastQueue()">
                <i class="bi bi-stop-circle-fill me-1"></i> Hentikan Broadcast
              </button>
              <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold btn-sm d-none" id="btnFinishBroadcast" data-bs-dismiss="modal" onclick="finishBroadcastReload()">
                <i class="bi bi-check2-circle me-1"></i> Selesai &amp; Muat Ulang
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer px-4 py-3 bg-white border-top d-flex justify-content-between align-items-center" id="broadcastModalFooter">
        <button type="button" class="btn btn-light rounded-pill px-3.5 fw-semibold text-secondary" data-bs-dismiss="modal">
          Tutup
        </button>
        <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2" id="btnStartBroadcast" onclick="startBroadcastQueue()">
          <i class="bi bi-shield-fill-check"></i> Mulai OpenWA Anti-Ban (<span id="txtBtnTargetCount">0</span>)
        </button>
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

    // Pastikan posisi scroll tabel customer selalu berada di paling kiri (Nama Toko terlihat) pada load/reload
    const resetTableScroll = function() {
        const tableContainer = document.getElementById('customer-table-container');
        if (tableContainer) {
            tableContainer.scrollLeft = 0;
        }
    };
    resetTableScroll();
    window.addEventListener('pageshow', resetTableScroll);

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

    // =========================================================================
    // WHATSAPP BROADCAST SYSTEM FOR CRM & LEADS
    // =========================================================================
    const msgTextarea = document.getElementById('broadcastMessageText');
    if (msgTextarea) {
        msgTextarea.addEventListener('input', updateLivePreview);
    }
});

// Broadcast Global State
let selectedBroadcastMap = new Map();
let currentBroadcastTargets = [];
let isBroadcastRunning = false;
let isBroadcastPaused = false;
let shouldStopBroadcast = false;
let openwaHealthCache = null;

// Quick Templates (Sudah memakai Spintax alami & sopan — Anti-Ban friendly!)
const QUICK_TEMPLATES = {
    '1': "{Halo|Selamat {salam_waktu}|Permisi} {nama_pic} dari *{nama_toko}* 👋\n\n{Perkenalkan, saya|Saya} *{nama_sales}* dari *Loewix CCTV Official*.\n\nKami {ingin berbagi|mau info} katalog & pricelist grosir terbaru untuk rekan toko/installer CCTV di area {kota} (garansi resmi & stok ready).\n\n{Boleh kami kirimkan|Apakah berkenan jika saya kirimkan} katalog terbarunya ke nomor ini? {Terima kasih|Makasih banyak} 🙏",
    '2': "{Selamat {salam_waktu}|Halo|Permisi} {nama_pic} (*{nama_toko}*),\n\nSemoga usaha di {kota} makin ramai dan lancar ya 🙏\n\nSaya *{nama_sales}* dari *Loewix CCTV*. {Mau mengabarkan|Sekadar info}, bulan ini ada harga spesial mitra untuk paket CCTV & IP Cam 2MP Full Color.\n\n{Apakah saat ini sedang ada kebutuhan|Lagi ada proyek atau kebutuhan} unit CCTV yang bisa kami bantu support?",
    '3': "{Salam hangat|Selamat {salam_waktu}|Halo} {nama_pic} & tim *{nama_toko}*! 🚀\n\nKami dari *Loewix CCTV* sedang buka program kemitraan khusus toko & installer area {kota}:\n✅ Harga khusus reseller/grosir\n✅ Garansi resmi & klaim unit cepat\n✅ Free desain materi promo toko\n\nJika {berminat|ingin cek pricelist}, silakan balas pesan ini ya. Salam sukses, *{nama_sales}*."
};

// Render Spintax + Variabel di browser (untuk Pratinjau & Asisten 1-Klik)
function renderSpintaxClient(template, sample, enableSpintax = true) {
    const h = new Date().getHours();
    const salamWaktu = (h >= 4 && h < 11) ? 'pagi' : ((h >= 11 && h < 15) ? 'siang' : ((h >= 15 && h < 18) ? 'sore' : 'malam'));
    let txt = String(template || '')
        .replace(/\{nama_toko\}/g, sample.nama_toko || 'Toko')
        .replace(/\{nama_pic\}/g, sample.pic || 'Bpk/Ibu')
        .replace(/\{kota\}/g, sample.city || 'Kota Anda')
        .replace(/\{nama_sales\}/g, sample.sales || 'Sales Loewix')
        .replace(/\{salam_waktu\}/g, salamWaktu);

    if (enableSpintax) {
        for (let d = 0; d < 5; d++) {
            const next = txt.replace(/\{([^{}|]+\|[^{}]+)\}/g, (_, group) => {
                const opts = group.split('|');
                return opts[Math.floor(Math.random() * opts.length)];
            });
            if (next === txt) break;
            txt = next;
        }
    }
    return txt;
}

// Update Checkbox State
function updateBroadcastSelectionUI() {
    const checkboxes = document.querySelectorAll('.row-broadcast-check:checked');
    selectedBroadcastMap.clear();
    checkboxes.forEach(cb => {
        selectedBroadcastMap.set(cb.dataset.id, {
            id: cb.dataset.id,
            nama_toko: cb.dataset.toko || 'Toko',
            pic: cb.dataset.pic || 'Bpk/Ibu',
            phone: cb.dataset.phone || '',
            city: cb.dataset.city || '-',
            sales: cb.dataset.sales || 'Sales Loewix'
        });
    });

    const count = selectedBroadcastMap.size;
    const badgeSelected = document.getElementById('badgeSelectedCount');
    const floatingBar = document.getElementById('floatingBroadcastBar');
    const floatingBadge = document.getElementById('floatingSelectedBadge');

    if (badgeSelected) {
        if (count > 0) {
            badgeSelected.textContent = count;
            badgeSelected.style.display = 'inline-block';
        } else {
            badgeSelected.style.display = 'none';
        }
    }

    if (floatingBar) {
        if (count > 0) {
            floatingBar.style.display = 'flex';
            if (floatingBadge) floatingBadge.textContent = count + ' Toko Terpilih';
        } else {
            floatingBar.style.display = 'none';
        }
    }

    const txtCountSelected = document.getElementById('txtCountSelected');
    if (txtCountSelected) txtCountSelected.textContent = count;
}

// Check All Checkbox Listener
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'checkAllBroadcast') {
        const checked = e.target.checked;
        document.querySelectorAll('.row-broadcast-check:not(:disabled)').forEach(cb => {
            cb.checked = checked;
        });
        updateBroadcastSelectionUI();
    } else if (e.target && e.target.classList.contains('row-broadcast-check')) {
        updateBroadcastSelectionUI();
        const total = document.querySelectorAll('.row-broadcast-check:not(:disabled)').length;
        const checkedTotal = document.querySelectorAll('.row-broadcast-check:checked').length;
        const checkAll = document.getElementById('checkAllBroadcast');
        if (checkAll) {
            checkAll.checked = (total > 0 && total === checkedTotal);
        }
    }
});

function clearSelectedBroadcast() {
    document.querySelectorAll('.row-broadcast-check').forEach(cb => cb.checked = false);
    const checkAll = document.getElementById('checkAllBroadcast');
    if (checkAll) checkAll.checked = false;
    updateBroadcastSelectionUI();
}

// Memuat Status Live OpenWA & Daftar Nomor Sales ke Modal Broadcast
async function loadOpenwaHealthForModal() {
    const pill = document.getElementById('openwaModalHealthPill');
    const alertBox = document.getElementById('openwaSetupAlertModal');
    const alertTxt = document.getElementById('openwaSetupAlertText');
    const sessSelect = document.getElementById('openwaSessionSelect');

    if (pill) {
        pill.className = 'badge rounded-pill bg-light text-secondary border px-2.5 py-1';
        pill.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="width:9px;height:9px;"></span> Cek OpenWA...';
    }

    try {
        const data = await fetch('ajax_wa_broadcast.php?action=openwa_health').then(r => r.json());
        openwaHealthCache = data;

        const dedupLbl = document.getElementById('txtDedupDaysLabel');
        if (dedupLbl && data.dedup_days != null) dedupLbl.textContent = data.dedup_days;

        // Smart default: centang Kunci Jam Kerja jika sedang di jam kerja (08-20 WIB); di luar jam kerja biarkan unchecked agar mudah dites
        const chkWork = document.getElementById('chkOpenwaWorkHours');
        if (chkWork) chkWork.checked = !!data.is_work_hours;

        if (sessSelect) {
            const prevVal = sessSelect.value || 'auto_rotate';
            let opts = `
                <option value="auto_rotate">🔄 Rotasi Otomatis Semua Nomor Ready (${data.healthy_senders || 0} nomor • sisa ${data.total_remaining || 0} kuota)</option>
                <option value="my_sales">👤 Khusus Nomor Sales Saya Saja</option>
            `;
            (data.sessions || []).forEach(s => {
                const icon = s.can_send ? '🟢' : (s.status === 'ready' ? '🟠' : '⚪');
                const note = s.can_send ? `sisa ${s.remaining_today}/${s.daily_limit}` : (s.is_paused ? 'Circuit Breaker' : s.status.toUpperCase());
                opts += `<option value="${escapeBroadcastHtml(s.session_id)}">${icon} ${escapeBroadcastHtml(s.label)} (${note})</option>`;
            });
            sessSelect.innerHTML = opts;
            if (Array.from(sessSelect.options).some(o => o.value === prevVal)) {
                sessSelect.value = prevVal;
            }
        }

        if (data.online && data.healthy_senders > 0) {
            if (pill) {
                pill.className = 'badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-bold';
                pill.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> OpenWA Ready (${data.healthy_senders} nomor • sisa ${data.total_remaining} kuota hari ini)`;
            }
            alertBox?.classList.add('d-none');
        } else if (data.online) {
            if (pill) {
                pill.className = 'badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 fw-bold';
                pill.innerHTML = `<i class="bi bi-qr-code me-1"></i> OpenWA Online — ${data.ready_sessions}/${data.total_sessions} Ready`;
            }
            if (alertBox && alertTxt) {
                alertBox.classList.remove('d-none');
                alertTxt.textContent = data.ready_sessions > 0
                    ? 'Semua nomor yang terhubung sudah mencapai kuota aman hari ini atau sedang cooldown.'
                    : 'OpenWA sudah online, tapi belum ada nomor WA sales yang Scan QR (status READY).';
            }
        } else {
            if (pill) {
                pill.className = 'badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 fw-bold';
                pill.innerHTML = `<i class="bi bi-wifi-off me-1"></i> OpenWA Belum Aktif`;
            }
            if (alertBox && alertTxt) {
                alertBox.classList.remove('d-none');
                alertTxt.textContent = 'Server OpenWA Docker belum menyala di VPS (port 2785). Jalankan install_openwa.sh atau gunakan Asisten 1-Klik.';
            }
        }
    } catch (err) {
        if (pill) {
            pill.className = 'badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1';
            pill.textContent = 'Gagal cek OpenWA';
        }
    }
}

// Buka Modal Broadcast
function openBroadcastModal(mode) {
    const modalEl = document.getElementById('modalWaBroadcast');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.getElementById('broadcastSetupScreen').classList.remove('d-none');
    document.getElementById('broadcastDirectAssistantScreen')?.classList.add('d-none');
    document.getElementById('broadcastProgressScreen').classList.add('d-none');
    document.getElementById('broadcastModalFooter').classList.remove('d-none');

    const msgInput = document.getElementById('broadcastMessageText');
    if (!msgInput.value.trim()) {
        msgInput.value = QUICK_TEMPLATES['1'];
    }

    const countSelected = selectedBroadcastMap.size;
    if (mode === 'selected' || countSelected > 0) {
        setBroadcastTargetMode('selected');
    } else {
        setBroadcastTargetMode('filter');
    }

    // Default ke OpenWA Anti-Ban Pro
    setBroadcastSendMethod('openwa');
    loadOpenwaHealthForModal();
    updateLivePreview();
    modal.show();
}

function setBroadcastTargetMode(mode) {
    const rSel = document.getElementById('targetModeSelected');
    const rFil = document.getElementById('targetModeFilter');
    const cSel = document.getElementById('cardTargetSelected');
    const cFil = document.getElementById('cardTargetFilter');
    if (mode === 'selected') {
        if (rSel) rSel.checked = true;
        cSel?.classList.add('active');
        cFil?.classList.remove('active');
    } else {
        if (rFil) rFil.checked = true;
        cFil?.classList.add('active');
        cSel?.classList.remove('active');
    }
    handleTargetModeChange();
}

function setBroadcastSendMethod(method) {
    const rOpenwa  = document.getElementById('sendMethodOpenwa');
    const rDirect  = document.getElementById('sendMethodDirect');
    const rGateway = document.getElementById('sendMethodGateway');
    if (method === 'openwa' && rOpenwa) rOpenwa.checked = true;
    else if (method === 'direct' && rDirect) rDirect.checked = true;
    else if (method === 'gateway' && rGateway) rGateway.checked = true;
    handleSendMethodChange();
}

function handleSendMethodChange() {
    const method = document.querySelector('input[name="send_method"]:checked')?.value || 'openwa';
    const cardOpenwa  = document.getElementById('cardMethodOpenwa');
    const cardDirect  = document.getElementById('cardMethodDirect');
    const cardGateway = document.getElementById('cardMethodGateway');
    const boxOpenwa   = document.getElementById('boxOpenwaSettings');
    const boxGateway  = document.getElementById('boxGatewaySettings');
    const btnStart    = document.getElementById('btnStartBroadcast');
    const count       = currentBroadcastTargets ? currentBroadcastTargets.length : 0;

    cardOpenwa?.classList.toggle('active', method === 'openwa');
    cardDirect?.classList.toggle('active', method === 'direct');
    cardGateway?.classList.toggle('active', method === 'gateway');
    boxOpenwa?.classList.toggle('d-none', method !== 'openwa');
    boxGateway?.classList.toggle('d-none', method !== 'gateway');

    if (btnStart) {
        if (method === 'openwa') {
            btnStart.innerHTML = `<i class="bi bi-shield-fill-check"></i> Mulai OpenWA Anti-Ban (<span id="txtBtnTargetCount">${count}</span>)`;
        } else if (method === 'direct') {
            btnStart.innerHTML = `<i class="bi bi-whatsapp"></i> Mulai Asisten 1-Klik (<span id="txtBtnTargetCount">${count}</span>)`;
        } else {
            btnStart.innerHTML = `<i class="bi bi-robot"></i> Mulai Bot Fonnte (<span id="txtBtnTargetCount">${count}</span>)`;
        }
    }
}

// =========================================================================
// ASISTEN 1-KLIK WHATSAPP DIRECT DISPATCHER (100% WA RESMI)
// =========================================================================
let directTargetIndex = 0;

function renderDirectCurrentTarget() {
    const total = currentBroadcastTargets.length;
    if (directTargetIndex >= total) {
        Swal.fire({
            icon: 'success',
            title: 'Selesai!',
            html: `Semua <b>${total} toko</b> telah selesai diproses dan tercatat ke Follow-Up CRM.`,
            confirmButtonText: 'Selesai & Refresh',
            confirmButtonColor: '#10B981'
        }).then(() => window.location.reload());
        return;
    }

    const currentNum = directTargetIndex + 1;
    const target = currentBroadcastTargets[directTargetIndex];
    const templateText = document.getElementById('broadcastMessageText').value.trim();

    const pct = Math.round((currentNum / total) * 100);
    const pBar = document.getElementById('directProgressBar');
    if (pBar) pBar.style.width = pct + '%';

    document.getElementById('directCurrentIndexText').textContent = currentNum;
    document.getElementById('directTotalCountText').textContent = total;
    document.getElementById('directTargetStoreName').textContent = target.nama_toko;
    document.getElementById('directTargetPhone').textContent = target.phone;
    document.getElementById('directTargetPic').textContent = target.pic || 'Bpk/Ibu';
    document.getElementById('directTargetCity').textContent = target.city || '-';

    const personalizedMsg = renderSpintaxClient(templateText, target, true);
    document.getElementById('directTargetMessagePreview').textContent = personalizedMsg;
}

async function dispatchDirectCurrentTarget() {
    const target = currentBroadcastTargets[directTargetIndex];
    if (!target) return;

    const personalizedMsg = document.getElementById('directTargetMessagePreview').textContent.trim();
    let cleanPhone = (target.phone || '').replace(/[^0-9]/g, '');
    if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.substring(1);
    else if (!cleanPhone.startsWith('62')) cleanPhone = '62' + cleanPhone;

    window.open(`https://api.whatsapp.com/send?phone=${cleanPhone}&text=${encodeURIComponent(personalizedMsg)}`, '_blank');

    try {
        const formData = new URLSearchParams({
            action: 'log_click_followup',
            customer_id: target.id,
            phone: cleanPhone,
            message: personalizedMsg,
            customer_name: target.nama_toko
        });
        fetch('ajax_wa_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
    } catch (e) {}

    directTargetIndex++;
    renderDirectCurrentTarget();
}

function skipDirectCurrentTarget() {
    directTargetIndex++;
    renderDirectCurrentTarget();
}

// =========================================================================
// HELPER COUNTDOWN JEDA ACAK & ISTIRAHAT BATCH (SUPPORT PAUSE & STOP)
// =========================================================================
async function waitSmartCountdown(seconds, labelPrefix) {
    for (let rem = seconds; rem > 0; rem--) {
        if (shouldStopBroadcast) return;
        while (isBroadcastPaused && !shouldStopBroadcast) {
            document.getElementById('txtLiveStatus').textContent = '⏸️ Dijeda sementara — klik Lanjutkan untuk meneruskan...';
            await new Promise(r => setTimeout(r, 400));
        }
        if (shouldStopBroadcast) return;
        const mm = Math.floor(rem / 60);
        const ss = String(rem % 60).padStart(2, '0');
        const fmt = mm > 0 ? `${mm}m ${ss}d` : `${rem} detik`;
        document.getElementById('txtLiveStatus').textContent = `${labelPrefix} ${fmt}...`;
        await new Promise(r => setTimeout(r, 1000));
    }
}

function togglePauseBroadcastQueue() {
    isBroadcastPaused = !isBroadcastPaused;
    const btn = document.getElementById('btnPauseBroadcast');
    if (btn) {
        if (isBroadcastPaused) {
            btn.className = 'btn btn-success rounded-pill px-4 fw-bold btn-sm';
            btn.innerHTML = '<i class="bi bi-play-circle-fill me-1"></i> Lanjutkan Antrean';
        } else {
            btn.className = 'btn btn-outline-warning rounded-pill px-4 fw-bold btn-sm';
            btn.innerHTML = '<i class="bi bi-pause-circle-fill me-1"></i> Jeda Sementara';
        }
    }
}

function appendBroadcastLog(line) {
    const consoleLog = document.getElementById('broadcastConsoleLog');
    if (!consoleLog) return;
    consoleLog.textContent += `[${new Date().toLocaleTimeString('id-ID')}] ${line}\n`;
    consoleLog.scrollTop = consoleLog.scrollHeight;
}

// =========================================================================
// MASTER QUEUE RUNNER: OPENWA 8-LAYER ANTI-BAN / ASISTEN 1-KLIK / FONNTE
// =========================================================================
async function startBroadcastQueue() {
    if (currentBroadcastTargets.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Tidak Ada Target',
            text: 'Silakan pilih toko yang ingin dikirimi broadcast atau gunakan mode filter.',
            confirmButtonColor: '#059669'
        });
        return;
    }

    const templateText = document.getElementById('broadcastMessageText').value.trim();
    if (!templateText) {
        Swal.fire({
            icon: 'warning',
            title: 'Pesan Kosong',
            text: 'Tuliskan pesan broadcast terlebih dahulu.',
            confirmButtonColor: '#059669'
        });
        return;
    }

    const sendMethod = document.querySelector('input[name="send_method"]:checked')?.value || 'openwa';

    // 1. MODE ASISTEN 1-KLIK
    if (sendMethod === 'direct') {
        document.getElementById('broadcastSetupScreen').classList.add('d-none');
        document.getElementById('broadcastDirectAssistantScreen').classList.remove('d-none');
        document.getElementById('broadcastProgressScreen').classList.add('d-none');
        document.getElementById('broadcastModalFooter').classList.add('d-none');
        directTargetIndex = 0;
        renderDirectCurrentTarget();
        return;
    }

    // 2. MODE OPENWA 8-LAPIS ANTI-BAN PRO
    if (sendMethod === 'openwa') {
        const profileKey = document.getElementById('openwaPacingProfile')?.value || 'ultra';
        const PACING = {
            ultra:  { label: 'Ultra Aman (25–50 dtk + istirahat 3 mnt / 10 pesan)', minSec: 25, maxSec: 50, batchSize: 10, cooldownSec: 180 },
            normal: { label: 'Normal Aman (15–30 dtk + istirahat 2 mnt / 15 pesan)', minSec: 15, maxSec: 30, batchSize: 15, cooldownSec: 120 },
            fast:   { label: 'Cepat (8–18 dtk + istirahat 1 mnt / 20 pesan)',       minSec: 8,  maxSec: 18, batchSize: 20, cooldownSec: 60  }
        };
        const pace = PACING[profileKey] || PACING.ultra;

        const sessionMode      = document.getElementById('openwaSessionSelect')?.value || 'auto_rotate';
        const precheckWa       = document.getElementById('chkOpenwaPrecheck')?.checked ? 1 : 0;
        const skipRecent       = document.getElementById('chkOpenwaDedup')?.checked ? 1 : 0;
        const simulateTyping   = document.getElementById('chkOpenwaTyping')?.checked ? 1 : 0;
        const enableSpintax    = document.getElementById('chkOpenwaSpintax')?.checked ? 1 : 0;
        const logFu            = document.getElementById('chkOpenwaLogFu')?.checked ? 1 : 0;
        const enforceWorkHours = document.getElementById('chkOpenwaWorkHours')?.checked ? 1 : 0;

        const confirmRes = await Swal.fire({
            title: 'Jalankan OpenWA Anti-Ban Pro?',
            html: `Target: <b>${currentBroadcastTargets.length} toko</b><br>Profil: <b>${pace.label}</b><br><small class="text-muted">Nomor mati &amp; toko duplikat akan dilewati otomatis demi keamanan akun WA sales.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '🚀 Ya, Mulai Aman',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#10B981'
        });
        if (!confirmRes.isConfirmed) return;

        prepareProgressScreen('Menjalankan OpenWA 8-Layer Anti-Ban Engine...');
        appendBroadcastLog(`🛡️ OpenWA Anti-Ban dimulai (${currentBroadcastTargets.length} target | Profil: ${profileKey.toUpperCase()})`);

        let successCount = 0;
        let skipCount    = 0;
        let failCount    = 0;
        let sentInBatch  = 0;
        const total      = currentBroadcastTargets.length;

        for (let i = 0; i < total; i++) {
            while (isBroadcastPaused && !shouldStopBroadcast) {
                document.getElementById('txtLiveStatus').textContent = '⏸️ Dijeda sementara...';
                await new Promise(r => setTimeout(r, 400));
            }
            if (shouldStopBroadcast) {
                appendBroadcastLog('⚠️ Pengiriman dihentikan oleh pengguna.');
                break;
            }

            const target = currentBroadcastTargets[i];
            const currentNum = i + 1;
            updateProgressBarUI(currentNum, total, `Memproses (${currentNum}/${total}): ${target.nama_toko}...`);

            let wasActuallySent = false;

            try {
                const fd = new URLSearchParams({
                    action: 'send_single_openwa',
                    phone: target.phone,
                    message: templateText,
                    customer_id: target.id,
                    customer_name: target.nama_toko,
                    customer_pic: target.pic || 'Bpk/Ibu',
                    customer_city: target.city || '-',
                    customer_sales: target.sales || 'Sales Loewix',
                    session_mode: sessionMode,
                    precheck_wa: precheckWa,
                    skip_recent: skipRecent,
                    simulate_typing: simulateTyping,
                    enable_spintax: enableSpintax,
                    log_fu: logFu,
                    enforce_work_hours: enforceWorkHours
                });

                const res = await fetch('ajax_wa_broadcast.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString()
                }).then(r => r.json());

                if (res.success && res.skipped) {
                    skipCount++;
                    const icon = res.skip_reason === 'not_wa' ? '📵' : '⏭️';
                    appendBroadcastLog(`${icon} (${currentNum}/${total}) SKIP ${target.nama_toko} — ${res.message}`);
                } else if (res.success) {
                    successCount++;
                    sentInBatch++;
                    wasActuallySent = true;
                    const senderBadge = document.getElementById('txtActiveSenderBadge');
                    if (senderBadge) {
                        senderBadge.innerHTML = `<i class="bi bi-whatsapp me-1"></i> Aktif: ${escapeBroadcastHtml(res.session_label)} • Terpakai ${res.sent_today}/${res.daily_limit} hari ini`;
                    }
                    appendBroadcastLog(`✅ (${currentNum}/${total}) TERKIRIM ke ${target.nama_toko} (${res.phone}) via [${res.session_label}] (typing ${res.typing_sec}d • kuota ${res.sent_today}/${res.daily_limit})`);
                } else {
                    failCount++;
                    appendBroadcastLog(`❌ (${currentNum}/${total}) GAGAL ${target.nama_toko}: ${res.message}`);
                    if (res.stop_queue) {
                        appendBroadcastLog(`🛑 ANTREAN DIHENTIKAN OTOMATIS DEMI KEAMANAN: ${res.message}`);
                        break;
                    }
                }
            } catch (err) {
                failCount++;
                appendBroadcastLog(`❌ (${currentNum}/${total}) Error jaringan saat menghubungi server CRM.`);
            }

            document.getElementById('txtSuccessCount').textContent = successCount;
            document.getElementById('txtSkipCount').textContent    = skipCount;
            document.getElementById('txtFailCount').textContent    = failCount;

            if (i < total - 1 && !shouldStopBroadcast) {
                if (!wasActuallySent) {
                    // Nomor di-skip (bukan WA / duplikat) -> lanjut cepat 1 detik
                    await waitSmartCountdown(1, '⏭️ Melanjutkan target berikutnya dalam');
                } else if (sentInBatch >= pace.batchSize) {
                    // Layer 4B: Batch Cooldown / Coffee Break Anti-Ban
                    sentInBatch = 0;
                    appendBroadcastLog(`☕ Istirahat Batch Anti-Ban (${pace.batchSize} pesan terkirim) — jeda aman ${pace.cooldownSec} detik agar akun tetap sehat...`);
                    await waitSmartCountdown(pace.cooldownSec, '☕ Istirahat Batch Anti-Ban:');
                } else {
                    // Layer 4A: Randomized Human Jitter Delay
                    const jitterSec = Math.floor(Math.random() * (pace.maxSec - pace.minSec + 1)) + pace.minSec;
                    await waitSmartCountdown(jitterSec, '⏳ Jeda acak manusiawi:');
                }
            }
        }

        finishProgressScreen(successCount, skipCount, failCount);
        return;
    }

    // 3. MODE FONNTE LEGACY
    const delaySec = parseInt(document.getElementById('broadcastDelaySelect').value) || 20;
    const logFu = document.getElementById('checkAutoLogFu').checked ? 1 : 0;

    const confirmRes = await Swal.fire({
        title: 'Mulai Bot Gateway Fonnte?',
        html: `Akan mengirim otomatis ke <b>${currentBroadcastTargets.length} toko</b> dengan jeda acak <b>${delaySec}–${delaySec + 10} detik</b>.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Jalankan Fonnte',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981'
    });
    if (!confirmRes.isConfirmed) return;

    prepareProgressScreen('Sedang Mengirim via Gateway Fonnte...');
    appendBroadcastLog(`🚀 Memulai antrean Fonnte (${currentBroadcastTargets.length} target)...`);

    let successCount = 0;
    let failCount    = 0;
    const total      = currentBroadcastTargets.length;

    for (let i = 0; i < total; i++) {
        if (shouldStopBroadcast) {
            appendBroadcastLog('⚠️ Pengiriman dihentikan oleh pengguna.');
            break;
        }
        const target = currentBroadcastTargets[i];
        const currentNum = i + 1;
        updateProgressBarUI(currentNum, total, `Mengirim ke: ${target.nama_toko}...`);

        const personalizedMsg = renderSpintaxClient(templateText, target, true);
        try {
            const fd = new URLSearchParams({
                action: 'send_single',
                phone: target.phone,
                message: personalizedMsg,
                customer_id: target.id,
                customer_name: target.nama_toko,
                log_fu: logFu
            });
            const sendResult = await fetch('ajax_wa_broadcast.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: fd.toString()
            }).then(r => r.json());

            if (sendResult.success) {
                successCount++;
                appendBroadcastLog(`✅ (${currentNum}/${total}) Sukses ke ${target.nama_toko} (${target.phone})`);
            } else {
                failCount++;
                appendBroadcastLog(`❌ (${currentNum}/${total}) Gagal ke ${target.nama_toko}: ${sendResult.message}`);
            }
        } catch (err) {
            failCount++;
            appendBroadcastLog(`❌ (${currentNum}/${total}) Error jaringan ke ${target.nama_toko}`);
        }

        document.getElementById('txtSuccessCount').textContent = successCount;
        document.getElementById('txtFailCount').textContent    = failCount;

        if (i < total - 1 && !shouldStopBroadcast) {
            const randWait = delaySec + Math.floor(Math.random() * 11);
            await waitSmartCountdown(randWait, '⏳ Jeda aman Fonnte:');
        }
    }

    finishProgressScreen(successCount, 0, failCount);
}

function prepareProgressScreen(headingText) {
    document.getElementById('broadcastSetupScreen').classList.add('d-none');
    document.getElementById('broadcastDirectAssistantScreen')?.classList.add('d-none');
    document.getElementById('broadcastProgressScreen').classList.remove('d-none');
    document.getElementById('broadcastModalFooter').classList.add('d-none');
    document.getElementById('spinnerBroadcasting').classList.remove('d-none');
    document.getElementById('iconBroadcastDone').classList.add('d-none');
    document.getElementById('iconBroadcastDone').classList.remove('d-flex');
    document.getElementById('btnFinishBroadcast').classList.add('d-none');
    document.getElementById('btnCancelBroadcast').classList.remove('d-none');
    document.getElementById('btnPauseBroadcast').classList.remove('d-none');
    document.getElementById('txtProgressHeading').textContent = headingText;
    document.getElementById('txtSuccessCount').textContent = '0';
    document.getElementById('txtSkipCount').textContent = '0';
    document.getElementById('txtFailCount').textContent = '0';
    document.getElementById('broadcastConsoleLog').textContent = '';

    isBroadcastRunning = true;
    isBroadcastPaused  = false;
    shouldStopBroadcast = false;
}

function updateProgressBarUI(currentNum, total, liveText) {
    const pct = Math.round((currentNum / Math.max(1, total)) * 100);
    const pBar = document.getElementById('progressBarBroadcast');
    if (pBar) {
        pBar.style.width = pct + '%';
        pBar.textContent = pct + '%';
        pBar.setAttribute('aria-valuenow', pct);
    }
    document.getElementById('txtProgressCount').textContent = `${currentNum} / ${total}`;
    document.getElementById('txtLiveStatus').textContent = liveText;
}

function finishProgressScreen(successCount, skipCount, failCount) {
    isBroadcastRunning = false;
    isBroadcastPaused  = false;
    document.getElementById('spinnerBroadcasting').classList.add('d-none');
    document.getElementById('iconBroadcastDone').classList.remove('d-none');
    document.getElementById('iconBroadcastDone').classList.add('d-flex');
    document.getElementById('txtProgressHeading').textContent = 'Pengiriman Broadcast Selesai!';
    document.getElementById('txtProgressSub').textContent = `Terkirim: ${successCount} | Di-skip Aman: ${skipCount} | Gagal: ${failCount}`;
    document.getElementById('txtLiveStatus').textContent = 'Selesai';
    document.getElementById('btnCancelBroadcast').classList.add('d-none');
    document.getElementById('btnPauseBroadcast').classList.add('d-none');
    document.getElementById('btnFinishBroadcast').classList.remove('d-none');
}

// Pergantian Mode Target (Terpilih vs Filter)
async function handleTargetModeChange() {
    const mode = document.querySelector('input[name="target_mode"]:checked')?.value || 'selected';
    const badgeSummary = document.getElementById('badgeTargetSummary');
    const btnCount = document.getElementById('txtBtnTargetCount');
    const warningNoPhone = document.getElementById('targetWarningNoPhone');

    if (mode === 'selected') {
        currentBroadcastTargets = Array.from(selectedBroadcastMap.values()).filter(t => t.phone && t.phone.length >= 9);
        const totalSelected = selectedBroadcastMap.size;
        const validCount = currentBroadcastTargets.length;
        
        if (badgeSummary) badgeSummary.textContent = `${validCount} Target Siap`;
        if (btnCount) btnCount.textContent = validCount;

        if (warningNoPhone) {
            warningNoPhone.classList.toggle('d-none', totalSelected <= validCount);
        }
    } else {
        if (badgeSummary) badgeSummary.textContent = 'Memuat target filter...';
        if (btnCount) btnCount.textContent = '...';

        const urlParams = new URLSearchParams(window.location.search);
        const postData = new URLSearchParams({
            action: 'get_filter_targets',
            filter_kota: urlParams.get('filter_kota') || '',
            filter_kategori: urlParams.get('filter_kategori') || '',
            filter_sales: urlParams.get('filter_sales') || '',
            filter_fu: urlParams.get('filter_fu') || '',
            search: urlParams.get('search') || '',
            max_limit: '100'
        });

        try {
            const data = await fetch('ajax_wa_broadcast.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: postData.toString()
            }).then(r => r.json());

            if (data.success) {
                currentBroadcastTargets = data.targets;
                if (badgeSummary) badgeSummary.textContent = `${data.total} Target (Sesuai Filter)`;
                if (btnCount) btnCount.textContent = data.total;
                warningNoPhone?.classList.add('d-none');
            } else {
                if (badgeSummary) badgeSummary.textContent = '0 Target';
                if (btnCount) btnCount.textContent = '0';
            }
        } catch (err) {
            if (badgeSummary) badgeSummary.textContent = 'Gagal memuat target';
            if (btnCount) btnCount.textContent = '0';
        }
    }
    updateLivePreview();
}

// Live WhatsApp Preview (Support Spintax + Variabel)
function updateLivePreview() {
    const text = document.getElementById('broadcastMessageText')?.value || '';
    const charCountEl = document.getElementById('txtCharCount');
    if (charCountEl) charCountEl.textContent = text.length + ' karakter';

    const sample = currentBroadcastTargets.length > 0 ? currentBroadcastTargets[0] : {
        nama_toko: 'Toko CCTV Jaya',
        pic: 'Bpk. Hendra',
        city: 'Depok',
        sales: 'Edi Suprianto'
    };

    const previewCustomerLabel = document.getElementById('lblPreviewCustomerName');
    if (previewCustomerLabel) previewCustomerLabel.textContent = `Contoh: ${sample.nama_toko}`;

    const phoneHeaderName = document.getElementById('waPhoneHeaderName');
    if (phoneHeaderName) phoneHeaderName.textContent = sample.nama_toko || 'Toko CCTV';

    const useSpintax = document.getElementById('chkOpenwaSpintax')?.checked !== false;
    const parsed = renderSpintaxClient(text, sample, useSpintax);

    const htmlParsed = escapeBroadcastHtml(parsed)
        .replace(/\*([^\*]+)\*/g, '<strong>$1</strong>')
        .replace(/_([^_]+)_/g, '<em>$1</em>')
        .replace(/\n/g, '<br>');

    const bubble = document.getElementById('waBubbleContent');
    if (bubble) bubble.innerHTML = htmlParsed;

    const clock = document.getElementById('waBubbleClock');
    if (clock) {
        const now = new Date();
        clock.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    }
}

function escapeBroadcastHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function insertVariableTag(tag) {
    const textarea = document.getElementById('broadcastMessageText');
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const before = textarea.value.substring(0, start);
    const after = textarea.value.substring(end, textarea.value.length);
    textarea.value = before + tag + after;
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    textarea.focus();
    updateLivePreview();
}

function applyQuickTemplate() {
    const sel = document.getElementById('selectQuickTemplate');
    const val = sel.value;
    if (QUICK_TEMPLATES[val]) {
        document.getElementById('broadcastMessageText').value = QUICK_TEMPLATES[val];
        updateLivePreview();
    }
}

function cancelBroadcastQueue() {
    shouldStopBroadcast = true;
    isBroadcastPaused = false;
    document.getElementById('txtLiveStatus').textContent = 'Menghentikan pengiriman...';
}

function finishBroadcastReload() {
    window.location.reload();
}
</script>