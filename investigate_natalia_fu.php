<?php
/**
 * investigate_natalia_fu.php
 * Investigasi mendalam riwayat Follow-Up Natalia Christi:
 * Memeriksa apakah ada data follow up yang hilang di bulan kemarin (September 2026).
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   INVESTIGASI DATA FOLLOW UP NATALIA CHRISTI (BULAN KEMARIN)   \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

// 1. Cek semua akun sales atas nama Natalia / Christi
echo "1. DAFTAR AKUN SALES ATAS NAMA NATALIA / CHRISTI:\n";
$qSales = $conn->query("SELECT id, nama_lengkap, email, role, created_at, deleted_at FROM sales WHERE nama_lengkap LIKE '%Natalia%' OR nama_lengkap LIKE '%Christi%' OR email LIKE '%natalia%' OR email LIKE '%christi%'");
$nataliaIds = [];
while ($rs = $qSales->fetch_assoc()) {
    $nataliaIds[] = (int)$rs['id'];
    $del = $rs['deleted_at'] ? " [TERHAPUS: {$rs['deleted_at']}]" : " [AKTIF]";
    echo "   • ID: {$rs['id']} | Nama: '{$rs['nama_lengkap']}' | Role: {$rs['role']} | Email: {$rs['email']}{$del}\n";
}
if (empty($nataliaIds)) {
    echo "   (Tidak ada akun sales dengan nama Natalia/Christi yang ditemukan)\n";
}
echo "\n";

$idList = !empty($nataliaIds) ? implode(',', $nataliaIds) : '0';

// 2. Total Follow Up per Akun & Status Terhapus
echo "2. REKAPITULASI DARI TABEL follow_ups:\n";
$qAllFu = $conn->query("
    SELECT 
        sales_id,
        COUNT(*) as total_semua,
        SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total_aktif,
        SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as total_soft_deleted,
        MIN(tgl_follow_up) as first_fu,
        MAX(tgl_follow_up) as last_fu
    FROM follow_ups
    WHERE sales_id IN ({$idList})
    GROUP BY sales_id
");
while ($r = $qAllFu->fetch_assoc()) {
    echo "   • Sales ID {$r['sales_id']}: Total {$r['total_semua']} data (Aktif: {$r['total_aktif']}, Terhapus/Deleted: {$r['total_soft_deleted']})\n";
    echo "     Periode: {$r['first_fu']} s/d {$r['last_fu']}\n";
}
echo "\n";

// 3. Rekap Per Bulan untuk Natalia
echo "3. RIWAYAT FOLLOW UP PER BULAN (Semua Akun Natalia):\n";
$qByMonth = $conn->query("
    SELECT 
        sales_id,
        DATE_FORMAT(tgl_follow_up, '%Y-%m') as bulan,
        COUNT(*) as total_all,
        SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total_aktif,
        SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as total_terhapus,
        COUNT(DISTINCT customer_id) as cust_unik
    FROM follow_ups
    WHERE sales_id IN ({$idList})
    GROUP BY sales_id, bulan
    ORDER BY bulan DESC, sales_id ASC
");
while ($rm = $qByMonth->fetch_assoc()) {
    echo "   • [Sales ID {$rm['sales_id']}] Bulan {$rm['bulan']} : {$rm['total_aktif']} aktif, {$rm['total_terhapus']} terhapus (Customer unik: {$rm['cust_unik']})\n";
}
echo "\n";

// 4. Analisis Khusus Bulan Kemarin (September 2026: 2026-09-01 s/d 2026-09-30)
echo "4. ANALISIS BULAN SEPTEMBER 2026 (01/09/2026 - 30/09/2026):\n";
$qSep = $conn->query("
    SELECT 
        fu.id, fu.sales_id, fu.customer_id, fu.tgl_follow_up, fu.respon, fu.keterangan, fu.deleted_at,
        c.nama_toko, c.sales_id as cust_current_sales, c.deleted_at as cust_deleted_at
    FROM follow_ups fu
    LEFT JOIN customers c ON fu.customer_id = c.id
    WHERE fu.sales_id IN ({$idList})
      AND DATE(fu.tgl_follow_up) BETWEEN '2026-09-01' AND '2026-09-30'
    ORDER BY fu.tgl_follow_up ASC, fu.id ASC
");

$totalSep = 0;
$activeSep = 0;
$deletedFuSep = 0;
$orphanCustSep = 0;
$deletedCustSep = 0;
$movedCustSep = 0;
$dateBreakdown = [];

while ($row = $qSep->fetch_assoc()) {
    $totalSep++;
    $tgl = date('Y-m-d', strtotime($row['tgl_follow_up']));
    $dateBreakdown[$tgl] = ($dateBreakdown[$tgl] ?? 0) + 1;

    if ($row['deleted_at'] !== null) {
        $deletedFuSep++;
    } else {
        $activeSep++;
    }

    if ($row['nama_toko'] === null) {
        $orphanCustSep++;
    } elseif ($row['cust_deleted_at'] !== null) {
        $deletedCustSep++;
    } elseif (!in_array($row['cust_current_sales'], $nataliaIds)) {
        $movedCustSep++;
    }
}

echo "   • Total record FU di September 2026          : {$totalSep}\n";
echo "   • FU Aktif (tampil di laporan)               : {$activeSep}\n";
echo "   • FU Terhapus (deleted_at IS NOT NULL)       : {$deletedFuSep}\n";
echo "   • Customer hilang (hard delete)              : {$orphanCustSep}\n";
echo "   • Customer soft-deleted                      : {$deletedCustSep}\n";
echo "   • Customer dipindah ke sales lain saat ini   : {$movedCustSep}\n";
echo "\n";

echo "   Rincian Tanggal Follow Up Natalia di September 2026:\n";
foreach ($dateBreakdown as $d => $c) {
    echo "     - Tanggal {$d} : {$c} follow up\n";
}
echo "\n";

// 5. Cek apakah ada toko milik Natalia yang pernah di-FU oleh sales lain di September 2026
echo "5. CEK TOKO NATALIA YANG DI-FU OLEH SALES LAIN DI SEPTEMBER 2026:\n";
$qCrossFu = $conn->query("
    SELECT 
        fu.sales_id, s.nama_lengkap as nama_sales, COUNT(*) as cnt, COUNT(DISTINCT fu.customer_id) as cust_cnt
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    JOIN sales s ON fu.sales_id = s.id
    WHERE c.sales_id IN ({$idList})
      AND fu.sales_id NOT IN ({$idList})
      AND DATE(fu.tgl_follow_up) BETWEEN '2026-09-01' AND '2026-09-30'
      AND fu.deleted_at IS NULL
    GROUP BY fu.sales_id, s.nama_lengkap
");
if ($qCrossFu && $qCrossFu->num_rows > 0) {
    while ($rc = $qCrossFu->fetch_assoc()) {
        echo "   • Di-FU oleh {$rc['nama_sales']} (ID: {$rc['sales_id']}) : {$rc['cnt']} kali ({$rc['cust_cnt']} toko)\n";
    }
} else {
    echo "   (Tidak ada toko Natalia yang di-FU oleh sales lain di bulan September 2026)\n";
}
echo "\n";

// 6. Cek apakah ada FU terhapus di tabel follow_ups untuk semua sales di September 2026
echo "6. LOG FOLLOW UP TERHAPUS (DELETED_AT) BULAN SEPTEMBER 2026 (SEMUA SALES):\n";
$qDelAll = $conn->query("
    SELECT s.nama_lengkap, COUNT(*) as cnt
    FROM follow_ups fu
    JOIN sales s ON fu.sales_id = s.id
    WHERE DATE(fu.tgl_follow_up) BETWEEN '2026-09-01' AND '2026-09-30'
      AND fu.deleted_at IS NOT NULL
    GROUP BY s.nama_lengkap
");
if ($qDelAll && $qDelAll->num_rows > 0) {
    while ($rd = $qDelAll->fetch_assoc()) {
        echo "   • {$rd['nama_lengkap']} : {$rd['cnt']} FU terhapus\n";
    }
} else {
    echo "   (Tidak ada data follow_ups yang berstatus deleted_at di bulan September 2026)\n";
}
echo "\n";

// 7. Cek riwayat kegiatan_sales / kunjungan Natalia di September 2026
echo "7. DATA KUNJUNGAN / KEGIATAN SALES NATALIA DI SEPTEMBER 2026:\n";
$checkKeg = $conn->query("SHOW TABLES LIKE 'kegiatan_sales'");
if ($checkKeg && $checkKeg->num_rows > 0) {
    $qKeg = $conn->query("
        SELECT 
            COUNT(*) as total_kegiatan,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
            SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as terhapus
        FROM kegiatan_sales ks
        JOIN team_kegiatan_sales tks ON ks.id = tks.id_kegiatan_sales
        WHERE tks.id_sales IN ({$idList})
          AND DATE(ks.jadwal) BETWEEN '2026-09-01' AND '2026-09-30'
    ");
    if ($qKeg && $rk = $qKeg->fetch_assoc()) {
        echo "   • Total Jadwal Kunjungan: {$rk['total_kegiatan']} (Selesai: {$rk['selesai']}, Dihapus: {$rk['terhapus']})\n";
    }
}
echo "\n";

// 8. Rincian 3 Follow-Up Natalia yang Terhapus di September 2026
echo "8. RINCIAN 3 FOLLOW UP NATALIA YANG BERSTATUS TERHAPUS (DELETED_AT):\n";
$qDelFu = $conn->query("
    SELECT fu.id, fu.customer_id, c.nama_toko, fu.tgl_follow_up, fu.deleted_at, fu.respon, fu.keterangan
    FROM follow_ups fu
    LEFT JOIN customers c ON fu.customer_id = c.id
    WHERE fu.sales_id IN ({$idList})
      AND DATE(fu.tgl_follow_up) BETWEEN '2026-09-01' AND '2026-09-30'
      AND fu.deleted_at IS NOT NULL
");
if ($qDelFu && $qDelFu->num_rows > 0) {
    while ($rd = $qDelFu->fetch_assoc()) {
        echo "   • ID FU: {$rd['id']} | Toko: '{$rd['nama_toko']}'\n";
        echo "     Tgl FU: {$rd['tgl_follow_up']} | Dihapus Pada: {$rd['deleted_at']}\n";
        echo "     Respon: {$rd['respon']} | Ket: {$rd['keterangan']}\n\n";
    }
} else {
    echo "   (Tidak ada rincian data terhapus)\n\n";
}

// 9. Perbandingan Aktivitas Follow Up Seluruh Sales di Bulan September 2026
echo "9. PERBANDINGAN TOTAL FOLLOW UP SEMUA SALES DI BULAN SEPTEMBER 2026:\n";
$qAllSalesSep = $conn->query("
    SELECT s.id, s.nama_lengkap, COUNT(fu.id) as total_fu, COUNT(DISTINCT fu.customer_id) as cust_unik
    FROM sales s
    LEFT JOIN follow_ups fu ON s.id = fu.sales_id 
        AND DATE(fu.tgl_follow_up) BETWEEN '2026-09-01' AND '2026-09-30'
        AND fu.deleted_at IS NULL
    WHERE s.role = 'sales' AND s.deleted_at IS NULL
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_fu DESC
");
while ($ras = $qAllSalesSep->fetch_assoc()) {
    echo "   • {$ras['nama_lengkap']} (ID {$ras['id']}): {$ras['total_fu']} kali FU ({$ras['cust_unik']} toko unik)\n";
}
echo "\n";

// 10. Cek Apakah Ada Follow Up di Bulan Oktober 2026 (Bulan Ini)
echo "10. RIWAYAT FOLLOW UP NATALIA DI BULAN OKTOBER 2026 (BULAN INI):\n";
$qOct = $conn->query("
    SELECT COUNT(*) as total_oct, COUNT(DISTINCT customer_id) as cust_oct
    FROM follow_ups
    WHERE sales_id IN ({$idList})
      AND DATE(tgl_follow_up) >= '2026-10-01'
      AND deleted_at IS NULL
");
$oct = $qOct ? $qOct->fetch_assoc() : ['total_oct' => 0, 'cust_oct' => 0];
echo "   • Total FU Bulan Oktober 2026: {$oct['total_oct']} kali ({$oct['cust_oct']} toko unik)\n\n";

// 11. Cek Toko Wilayah Jawa Timur / Bali yang Di-FU Sales Lain di September 2026
echo "11. CEK APAKAH ADA TOKO DI WILAYAH JAWA TIMUR YANG DI-FU SALES LAIN (SEPTEMBER 2026):\n";
$jatimKeywords = ['surabaya', 'malang', 'sidoarjo', 'gresik', 'jember', 'banyuwangi', 'kediri', 'madiun', 'probolinggo', 'pasuruan', 'blitar', 'mojokerto', 'tuban', 'lamongan', 'bojonegoro', 'ngawi', 'magetan', 'ponorogo', 'pacitan', 'tulungagung', 'trenggalek', 'nganjuk', 'lumajang', 'bondowoso', 'situbondo', 'bangkalan', 'sampang', 'pamekasan', 'sumenep', 'jatim', 'jawa timur'];
$condJatim = [];
foreach ($jatimKeywords as $kw) {
    $condJatim[] = "ca.kota LIKE '%$kw%'";
}
$whereJatim = "(" . implode(' OR ', $condJatim) . ")";

$qJatimCross = $conn->query("
    SELECT s.nama_lengkap, COUNT(fu.id) as total_fu, COUNT(DISTINCT fu.customer_id) as cust_unik
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    JOIN customer_addresses ca ON c.id = ca.customer_id
    JOIN sales s ON fu.sales_id = s.id
    WHERE {$whereJatim}
      AND fu.sales_id NOT IN ({$idList})
      AND DATE(fu.tgl_follow_up) BETWEEN '2026-09-01' AND '2026-09-30'
      AND fu.deleted_at IS NULL
      AND c.deleted_at IS NULL
    GROUP BY s.nama_lengkap
    ORDER BY total_fu DESC
");
if ($qJatimCross && $qJatimCross->num_rows > 0) {
    while ($rjc = $qJatimCross->fetch_assoc()) {
        echo "   • {$rjc['nama_lengkap']} melakukan {$rjc['total_fu']} FU ({$rjc['cust_unik']} toko di Jatim)\n";
    }
} else {
    echo "   (Tidak ada sales lain yang mem-follow up toko di Jawa Timur di bulan September 2026)\n";
}

echo "\n=================================================================\n";

