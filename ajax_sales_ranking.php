<?php
/**
 * AJAX API HANDLER FOR REALTIME SALES RANKING & LEADERBOARD WIDGET
 * Enables smooth in-place dynamic filter updates without full page reloads.
 */
header('Content-Type: application/json');
require_once 'includes/db.php';

$selected_bulan = trim($_GET['periode_bulan'] ?? '8-10');

if ($selected_bulan === '8') {
    $start_periode_ranking = '2026-08-01 00:00:00';
    $end_periode_ranking = '2026-08-31 23:59:59';
    $label_periode_ranking = 'Agt 2026';
    $full_label_ranking = 'Bulan 8 (Agustus 2026)';
} else if ($selected_bulan === '9') {
    $start_periode_ranking = '2026-09-01 00:00:00';
    $end_periode_ranking = '2026-09-30 23:59:59';
    $label_periode_ranking = 'Sep 2026';
    $full_label_ranking = 'Bulan 9 (September 2026)';
} else if ($selected_bulan === '10') {
    $start_periode_ranking = '2026-10-01 00:00:00';
    $end_periode_ranking = '2026-10-31 23:59:59';
    $label_periode_ranking = 'Okt 2026';
    $full_label_ranking = 'Bulan 10 (Oktober 2026)';
} else {
    $selected_bulan = '8-10';
    $start_periode_ranking = '2026-08-01 00:00:00';
    $end_periode_ranking = '2026-10-31 23:59:59';
    $label_periode_ranking = '3 Bulan (Agt-Okt)';
    $full_label_ranking = 'Periode 3 Bulan (Agt - Okt 2026)';
}

$target_omset_finish = 200000000;

// Query Sales Activity & Customer Baru (Primary Focus: Follow Up & Penambahan Customer Baru)
$sql_ranking_all = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}' THEN fu.id END) AS total_fu,
        COUNT(DISTINCT CASE WHEN c.tgl_input >= '{$start_periode_ranking}' AND c.tgl_input <= '{$end_periode_ranking}' THEN c.id END) AS total_cust_baru,
        COUNT(DISTINCT CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}' THEN fu.customer_id END) AS total_customer_fu,
        COUNT(DISTINCT CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}' AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.id END) AS total_inv_count,
        COALESCE(SUM(CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}' AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.nominal_invoice ELSE 0 END), 0) AS total_omset_invoice
    FROM sales s
    LEFT JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL AND fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}'
    LEFT JOIN customers c ON c.sales_id = s.id AND c.deleted_at IS NULL AND c.tgl_input >= '{$start_periode_ranking}' AND c.tgl_input <= '{$end_periode_ranking}'
    WHERE s.deleted_at IS NULL OR fu.id IS NOT NULL OR c.id IS NOT NULL
    GROUP BY s.id, s.nama_lengkap
    ORDER BY (total_fu + total_cust_baru) DESC, total_fu DESC, total_cust_baru DESC
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
