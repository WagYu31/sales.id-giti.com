<?php
/**
 * AJAX API HANDLER FOR REALTIME SALES RANKING & LEADERBOARD WIDGET
 * Enables smooth in-place dynamic filter updates without full page reloads.
 */
header('Content-Type: application/json');
require_once 'includes/db.php';

$selected_bulan = trim($_GET['periode_bulan'] ?? '8-10');

if ($selected_bulan === '8') {
    $start_date = '2026-08-01';
    $end_date = '2026-08-31';
    $start_dt = '2026-08-01 00:00:00';
    $end_dt = '2026-08-31 23:59:59';
    $label_periode_ranking = 'Agt 2026';
    $full_label_ranking = 'Bulan 8 (Agustus 2026)';
} else if ($selected_bulan === '9') {
    $start_date = '2026-09-01';
    $end_date = '2026-09-30';
    $start_dt = '2026-09-01 00:00:00';
    $end_dt = '2026-09-30 23:59:59';
    $label_periode_ranking = 'Sep 2026';
    $full_label_ranking = 'Bulan 9 (September 2026)';
} else if ($selected_bulan === '10') {
    $start_date = '2026-10-01';
    $end_date = '2026-10-31';
    $start_dt = '2026-10-01 00:00:00';
    $end_dt = '2026-10-31 23:59:59';
    $label_periode_ranking = 'Okt 2026';
    $full_label_ranking = 'Bulan 10 (Oktober 2026)';
} else {
    $selected_bulan = '8-10';
    $start_date = '2026-08-01';
    $end_date = '2026-10-31';
    $start_dt = '2026-08-01 00:00:00';
    $end_dt = '2026-10-31 23:59:59';
    $label_periode_ranking = '3 Bulan (Agt-Okt)';
    $full_label_ranking = 'Periode 3 Bulan (Agt - Okt 2026)';
}

$target_omset_finish = 200000000;

// Program Rule:
// 1. Customer Baru: Di-input oleh sales pada periode program (tgl_input >= 2026-08-01) dan ada belanja invoice di periode program
// 2. Customer Lama: Terdaftar sebelum bulan 6 (tgl_input <= 2026-05-31) & TIDAK PERNAH belanja di bulan 6 atau 7 2026 (terakhir belanja <= bulan 5)
$sql_ranking_all = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        
        -- Customer Baru Input in Period
        (SELECT COUNT(c_sub.id) 
         FROM customers c_sub 
         WHERE c_sub.sales_id = s.id 
           AND c_sub.deleted_at IS NULL
           AND c_sub.tgl_input IS NOT NULL 
           AND c_sub.tgl_input >= '{$start_date}' 
           AND c_sub.tgl_input <= '{$end_date}'
        ) AS total_cust_baru,

        -- Total Activity FU on Qualified Customers (Cust Baru + Cust Lama Reaktivasi)
        COUNT(DISTINCT CASE 
            WHEN c.id IS NOT NULL AND (
                -- Kategori 1: Customer Baru Program
                (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01' AND c.tgl_input <= '{$end_date}')
                OR 
                -- Kategori 2: Customer Lama (terakhir belanja <= bulan 5, tidak belanja di bulan 6-7)
                ((c.tgl_input IS NULL OR c.tgl_input <= '2026-05-31')
                 AND NOT EXISTS (
                     SELECT 1 FROM follow_ups fu_mid 
                     WHERE fu_mid.customer_id = c.id 
                       AND fu_mid.deleted_at IS NULL 
                       AND fu_mid.no_inv IS NOT NULL AND fu_mid.no_inv != '' 
                       AND fu_mid.tgl_follow_up >= '2026-06-01 00:00:00' 
                       AND fu_mid.tgl_follow_up <= '2026-07-31 23:59:59'
                 ))
            )
            AND fu.tgl_follow_up >= '{$start_dt}' AND fu.tgl_follow_up <= '{$end_dt}'
            THEN fu.id 
        END) AS total_fu,

        -- Total Distinct Qualified Customers di-FU
        COUNT(DISTINCT CASE 
            WHEN c.id IS NOT NULL AND (
                -- Kategori 1: Customer Baru
                (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01' AND c.tgl_input <= '{$end_date}')
                OR 
                -- Kategori 2: Customer Lama Reaktivasi
                ((c.tgl_input IS NULL OR c.tgl_input <= '2026-05-31')
                 AND NOT EXISTS (
                     SELECT 1 FROM follow_ups fu_mid 
                     WHERE fu_mid.customer_id = c.id 
                       AND fu_mid.deleted_at IS NULL 
                       AND fu_mid.no_inv IS NOT NULL AND fu_mid.no_inv != '' 
                       AND fu_mid.tgl_follow_up >= '2026-06-01 00:00:00' 
                       AND fu_mid.tgl_follow_up <= '2026-07-31 23:59:59'
                 ))
            )
            AND fu.tgl_follow_up >= '{$start_dt}' AND fu.tgl_follow_up <= '{$end_dt}'
            THEN fu.customer_id 
        END) AS total_customer_fu,

        -- Total Jumlah Invoice yang Valid (Cust Baru + Cust Lama Reaktivasi yang Belanja)
        COUNT(DISTINCT CASE 
            WHEN c.id IS NOT NULL AND (
                -- Kategori 1: Customer Baru
                (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01' AND c.tgl_input <= '{$end_date}')
                OR 
                -- Kategori 2: Customer Lama Reaktivasi
                ((c.tgl_input IS NULL OR c.tgl_input <= '2026-05-31')
                 AND NOT EXISTS (
                     SELECT 1 FROM follow_ups fu_mid 
                     WHERE fu_mid.customer_id = c.id 
                       AND fu_mid.deleted_at IS NULL 
                       AND fu_mid.no_inv IS NOT NULL AND fu_mid.no_inv != '' 
                       AND fu_mid.tgl_follow_up >= '2026-06-01 00:00:00' 
                       AND fu_mid.tgl_follow_up <= '2026-07-31 23:59:59'
                 ))
            )
            AND fu.no_inv IS NOT NULL AND fu.no_inv != ''
            AND fu.tgl_follow_up >= '{$start_dt}' AND fu.tgl_follow_up <= '{$end_dt}'
            THEN fu.id 
        END) AS total_inv_count,

        -- Total Omset Invoice yang Valid (Rp)
        COALESCE(SUM(CASE 
            WHEN c.id IS NOT NULL AND (
                -- Kategori 1: Customer Baru
                (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01' AND c.tgl_input <= '{$end_date}')
                OR 
                -- Kategori 2: Customer Lama Reaktivasi
                ((c.tgl_input IS NULL OR c.tgl_input <= '2026-05-31')
                 AND NOT EXISTS (
                     SELECT 1 FROM follow_ups fu_mid 
                     WHERE fu_mid.customer_id = c.id 
                       AND fu_mid.deleted_at IS NULL 
                       AND fu_mid.no_inv IS NOT NULL AND fu_mid.no_inv != '' 
                       AND fu_mid.tgl_follow_up >= '2026-06-01 00:00:00' 
                       AND fu_mid.tgl_follow_up <= '2026-07-31 23:59:59'
                 ))
            )
            AND fu.no_inv IS NOT NULL AND fu.no_inv != ''
            AND fu.tgl_follow_up >= '{$start_dt}' AND fu.tgl_follow_up <= '{$end_dt}'
            THEN fu.nominal_invoice 
            ELSE 0 
        END), 0) AS total_omset_invoice

    FROM sales s
    LEFT JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL
    LEFT JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE s.role = 'sales' OR s.role = 'superadmin'
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_fu DESC, total_inv_count DESC, total_omset_invoice DESC
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
