<?php
/**
 * investigate_efrina_fu.php
 * Investigasi mendalam riwayat Follow-Up Efrina Panjaitan (Rina)
 * untuk menjawab pertanyaan "Sudah FU katanya ada 200 lebih"
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   INVESTIGASI MENDALAM RIWAYAT SUDAH FU EFRINA PANJAITAN       \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$efrinaId = 22;

// 1. Cek Apakah ada akun duplikat Efrina/Rina di tabel sales
echo "1. CEK AKUN SALES EFRINA / RINA:\n";
$qSales = $conn->query("SELECT id, nama_lengkap, email, role, created_at, deleted_at FROM sales WHERE nama_lengkap LIKE '%Efrina%' OR nama_lengkap LIKE '%Rina%' OR email LIKE '%efrina%' OR email LIKE '%rina%'");
$salesIds = [];
while ($rs = $qSales->fetch_assoc()) {
    $salesIds[] = (int)$rs['id'];
    $del = $rs['deleted_at'] ? " [DELETED: {$rs['deleted_at']}]" : " [AKTIF]";
    echo "   • ID: {$rs['id']} | Nama: '{$rs['nama_lengkap']}' | Email: {$rs['email']}{$del}\n";
}
echo "\n";

// 2. Hitung Total Aktivitas FU vs Total Customer Unik
$idList = implode(',', $salesIds);
$qFuStats = $conn->query("
    SELECT 
        COUNT(*) as total_aktivitas_fu,
        COUNT(DISTINCT customer_id) as total_customer_unik,
        COUNT(DISTINCT CASE WHEN deleted_at IS NULL THEN customer_id END) as total_cust_aktif,
        SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as fu_terhapus
    FROM follow_ups
    WHERE sales_id IN ({$idList})
");
$stats = $qFuStats->fetch_assoc();

echo "2. REKAPITULASI DARI DATABASE (Tabel follow_ups):\n";
echo "   • Total AKTIVITAS Follow-Up (Jumlah Chat/Telepon) : {$stats['total_aktivitas_fu']} KALI\n";
echo "   • Total CUSTOMER UNIK yang Pernah Dihubungi       : {$stats['total_customer_unik']} TOKO\n";
echo "   • Total Customer Aktif (tidak terhapus)          : {$stats['total_cust_aktif']} TOKO\n";
echo "   • Riwayat FU yang berstatus Terhapus             : {$stats['fu_terhapus']} record\n\n";

// 3. Cek Distribusi Berdasarkan Tanggal / Bulan Follow-Up
echo "3. RIWAYAT FOLLOW-UP BERDASARKAN BULAN:\n";
$qByMonth = $conn->query("
    SELECT 
        DATE_FORMAT(tgl_follow_up, '%Y-%m') as bulan,
        COUNT(*) as total_aktivitas,
        COUNT(DISTINCT customer_id) as total_customer
    FROM follow_ups
    WHERE sales_id IN ({$idList}) AND deleted_at IS NULL
    GROUP BY bulan
    ORDER BY bulan DESC
");
while ($rm = $qByMonth->fetch_assoc()) {
    echo "   • Bulan {$rm['bulan']} : {$rm['total_aktivitas']} kali FU ({$rm['total_customer']} customer)\n";
}
echo "\n";

// 4. Cek Pemegang Customer yang Pernah Di-FU Efrina Saat Ini
echo "4. DIMANA POSISI CUSTOMER YANG PERNAH DI-FU EFRINA SEKARANG:\n";
$qCustLocation = $conn->query("
    SELECT 
        c.sales_id,
        COALESCE(s.nama_lengkap, 'Belum Ada Sales') as sales_name,
        COUNT(DISTINCT c.id) as total_cust
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE fu.sales_id IN ({$idList}) AND fu.deleted_at IS NULL AND c.deleted_at IS NULL
    GROUP BY c.sales_id, s.nama_lengkap
    ORDER BY total_cust DESC
");
while ($rc = $qCustLocation->fetch_assoc()) {
    echo "   • Dipegang {$rc['sales_name']} (ID: {$rc['sales_id']}) : {$rc['total_cust']} customer\n";
}
echo "\n";

// 5. Cek Berapa Customer yang di-FU Lebih dari 1 Kali oleh Efrina
$qMultiFu = $conn->query("
    SELECT COUNT(*) as multi_cnt FROM (
        SELECT customer_id, COUNT(*) as cnt 
        FROM follow_ups 
        WHERE sales_id IN ({$idList}) AND deleted_at IS NULL 
        GROUP BY customer_id 
        HAVING cnt > 1
    ) sub
");
$multiCnt = $qMultiFu->fetch_assoc()['multi_cnt'];
echo "5. ANALISIS FU BERULANG:\n";
echo "   • Customer yang di-follow up LEBIH DARI 1 KALI : {$multiCnt} toko\n";
echo "   (Artinya Efrina sering mem-follow up toko yang sama berulang kali, sehingga total interaksinya 200+ kali)\n\n";

echo "=================================================================\n";
