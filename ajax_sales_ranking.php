<?php
/**
 * AJAX API HANDLER FOR REALTIME SALES RANKING & LEADERBOARD WIDGET
 * Enables smooth in-place dynamic filter updates without full page reloads.
 */
header('Content-Type: application/json');
require_once 'includes/db.php';

$selected_bulan = trim($_GET['periode_bulan'] ?? '8-10');

if ($selected_bulan === '8') {
    $where_fu = "MONTH(fu.tgl_follow_up) = 8";
    $where_c = "MONTH(c.tgl_input) = 8";
    $label_periode_ranking = 'Agt 2026';
    $full_label_ranking = 'Bulan 8 (Agustus 2026)';
} else if ($selected_bulan === '9') {
    $where_fu = "MONTH(fu.tgl_follow_up) = 9";
    $where_c = "MONTH(c.tgl_input) = 9";
    $label_periode_ranking = 'Sep 2026';
    $full_label_ranking = 'Bulan 9 (September 2026)';
} else if ($selected_bulan === '10') {
    $where_fu = "MONTH(fu.tgl_follow_up) = 10";
    $where_c = "MONTH(c.tgl_input) = 10";
    $label_periode_ranking = 'Okt 2026';
    $full_label_ranking = 'Bulan 10 (Oktober 2026)';
} else {
    $selected_bulan = '8-10';
    $where_fu = "MONTH(fu.tgl_follow_up) IN (8, 9, 10)";
    $where_c = "MONTH(c.tgl_input) IN (8, 9, 10)";
    $label_periode_ranking = '3 Bulan (Agt-Okt)';
    $full_label_ranking = 'Periode 3 Bulan (Agt - Okt 2026)';
}

$target_omset_finish = 200000000;

// Query Sales Activity & Customer Baru (No WHERE clause filter so all sales reps are always returned)
$sql_ranking_all = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT CASE WHEN {$where_fu} THEN fu.id END) AS total_fu,
        COUNT(DISTINCT CASE WHEN {$where_c} THEN c.id END) AS total_cust_baru,
        COUNT(DISTINCT CASE WHEN {$where_fu} THEN fu.customer_id END) AS total_customer_fu,
        COUNT(DISTINCT CASE WHEN {$where_fu} AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.id END) AS total_inv_count,
        COALESCE(SUM(CASE WHEN {$where_fu} AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.nominal_invoice ELSE 0 END), 0) AS total_omset_invoice
    FROM sales s
    LEFT JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL
    LEFT JOIN customers c ON c.sales_id = s.id AND c.deleted_at IS NULL
    GROUP BY s.id, s.nama_lengkap
    ORDER BY (COUNT(DISTINCT CASE WHEN {$where_fu} THEN fu.id END) + COUNT(DISTINCT CASE WHEN {$where_c} THEN c.id END)) DESC
";

$res_ranking = $conn->query($sql_ranking_all);
$ranking_data = [];
if ($res_ranking) {
    while ($row = $res_ranking->fetch_assoc()) {
        $row['total_omset_invoice'] = (float)$row['total_omset_invoice'];
        $row['total_fu'] = (int)$row['total_fu'];
        $row['total_cust_baru'] = (int)$row['total_cust_baru'];
        $row['total_customer_fu'] = (int)$row['total_customer_fu'];
        $row['total_inv_count'] = (int)$row['total_inv_count'];
        $ranking_data[] = $row;
    }
}

$chart_labels = [];
$chart_omset_invoice = [];
$chart_total_fu = [];
$chart_cust_baru = [];
$chart_customer_fu = [];
$chart_inv_count = [];

foreach ($ranking_data as $rd) {
    $chart_labels[] = $rd['nama_sales'];
    $chart_omset_invoice[] = $rd['total_omset_invoice'];
    $chart_total_fu[] = $rd['total_fu'];
    $chart_cust_baru[] = $rd['total_cust_baru'];
    $chart_customer_fu[] = $rd['total_customer_fu'];
    $chart_inv_count[] = $rd['total_inv_count'];
}

echo json_encode([
    'success' => true,
    'selected_bulan' => $selected_bulan,
    'label_periode_ranking' => $label_periode_ranking,
    'full_label_ranking' => $full_label_ranking,
    'ranking_data' => $ranking_data,
    'top1' => $ranking_data[0] ?? null,
    'top2' => $ranking_data[1] ?? null,
    'top3' => $ranking_data[2] ?? null,
    'total_sales_count' => count($ranking_data),
    'chart_labels' => $chart_labels,
    'chart_omset_invoice' => $chart_omset_invoice,
    'chart_total_fu' => $chart_total_fu,
    'chart_cust_baru' => $chart_cust_baru,
    'chart_customer_fu' => $chart_customer_fu,
    'chart_inv_count' => $chart_inv_count
]);
