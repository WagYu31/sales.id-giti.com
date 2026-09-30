<?php
/**
 * overview_all_sales.php
 * Ringkasan Pembagian Customer ke Seluruh Sales Aktif di Database Server
 *
 * Cara menjalankan:
 *   cd /www/wwwroot/sales.id-giti.com
 *   php overview_all_sales.php
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   RINGKASAN STATUS PEMBAGIAN CUSTOMER SELURUH TIM SALES         \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Error: Gagal terhubung ke database!\n");
}

$sql = "
    SELECT 
        s.id, 
        s.nama_lengkap,
        COUNT(c.id) as total_customers,
        SUM(CASE WHEN c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = s.id AND deleted_at IS NULL) THEN 1 ELSE 0 END) as sudah_fu,
        SUM(CASE WHEN c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = s.id AND deleted_at IS NULL) THEN 1 ELSE 0 END) as belum_fu,
        SUM(CASE WHEN c.kandidat = 'Y' THEN 1 ELSE 0 END) as total_kandidat,
        SUM(CASE WHEN c.deal = 'Y' THEN 1 ELSE 0 END) as total_deal
    FROM sales s
    LEFT JOIN customers c ON s.id = c.sales_id AND c.deleted_at IS NULL
    WHERE s.role = 'sales' AND s.deleted_at IS NULL
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_customers DESC
";

$res = $conn->query($sql);

printf("%-5s | %-28s | %-8s | %-8s | %-8s | %-8s | %-8s\n", "ID", "NAMA SALES", "TOTAL", "SUDAH FU", "BELUM FU", "KANDIDAT", "DEAL");
echo str_repeat("-", 90) . "\n";

while ($r = $res->fetch_assoc()) {
    printf(
        "%-5d | %-28s | %-8s | %-8s | %-8s | %-8s | %-8s\n",
        $r['id'],
        substr($r['nama_lengkap'], 0, 28),
        number_format($r['total_customers']),
        number_format($r['sudah_fu']),
        number_format($r['belum_fu']),
        number_format($r['total_kandidat']),
        number_format($r['total_deal'])
    );
}

echo str_repeat("=", 90) . "\n";
echo "Total Seluruh Customer di Database: " . number_format($conn->query("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL")->fetch_row()[0]) . " Toko\n";
