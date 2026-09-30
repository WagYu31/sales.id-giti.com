<?php
/**
 * find_all_natalia_territories.php
 * Mencari seluruh wilayah/kota yang pernah di-follow up oleh Natalia Christi
 * untuk menemukan wilayah apa saja yang aslinya dipegang oleh Natalia hingga mencapai 500-600 customer.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PELACAKAN LENGKAP WILAYAH ASLI NATALIA CHRISTI                \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$nataliaId = 20;

// 1. Cek Seluruh Kota & Provinsi dari 278 Customer yang Pernah Di-FU oleh Natalia
echo "1. DAFTAR KOTA & PROVINSI DARI CUSTOMER YANG PERNAH DI-FU NATALIA:\n";
$qHistory = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.provinsi), ''), 'Tanpa Provinsi') as prov,
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tanpa Kota') as kota,
        COUNT(DISTINCT c.id) as cnt
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE fu.sales_id = {$nataliaId} AND fu.deleted_at IS NULL
    GROUP BY prov, kota
    ORDER BY cnt DESC
");

$provincesFu = [];
while ($r = $qHistory->fetch_assoc()) {
    echo "   • [{$r['prov']}] {$r['kota']}: {$r['cnt']} toko\n";
    $p = trim($r['prov']);
    if ($p !== 'Tanpa Provinsi') {
        $provincesFu[$p] = ($provincesFu[$p] ?? 0) + $r['cnt'];
    }
}
echo "\n";

echo "2. REKAPITULASI PROVINSI DARI RIWAYAT FU NATALIA:\n";
foreach ($provincesFu as $prov => $cnt) {
    echo "   • {$prov}: {$cnt} customer di-FU\n";
}
echo "\n";

// 3. Cek Potensi Customer di Database untuk Provinsi-Provinsi Tersebut
echo "3. POTENSI SELURUH CUSTOMER DI DATABASE UNTUK PROVINSI TERSEBUT:\n";
$sqlTotalByProv = "
    SELECT 
        COALESCE(NULLIF(TRIM(ca.provinsi), ''), 'Tidak Terisi') as prov,
        COUNT(DISTINCT c.id) as total_db,
        SUM(CASE WHEN c.sales_id = {$nataliaId} THEN 1 ELSE 0 END) as sudah_di_natalia,
        SUM(CASE WHEN c.sales_id != {$nataliaId} THEN 1 ELSE 0 END) as di_sales_lain
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.deleted_at IS NULL
    GROUP BY prov
    ORDER BY total_db DESC
";
$resProv = $conn->query($sqlTotalByProv);
while ($r = $resProv->fetch_assoc()) {
    echo "   • {$r['prov']}: Total {$r['total_db']} toko (Di Natalia: {$r['sudah_di_natalia']}, Di Sales Lain: {$r['di_sales_lain']})\n";
}
echo "\n";

// 4. Periksa Kota-kota Jawa Timur yang Mungkin Belum Masuk ke Natalia (Misal penulisan 'SBY', 'KAB. SIDOARJO', dll)
echo "4. CEK KOTA DI JAWA TIMUR YANG BELUM MASUK KE NATALIA:\n";
$qUnassignedJatim = $conn->query("
    SELECT 
        ca.kota, ca.alamat, s.nama_lengkap as current_holder, COUNT(*) as cnt
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE c.sales_id != {$nataliaId}
      AND (
          ca.provinsi LIKE '%Timur%' OR ca.alamat LIKE '%Jawa Timur%' OR ca.alamat LIKE '%Jatim%'
          OR ca.kota LIKE '%Surabaya%' OR ca.kota LIKE '%Malang%' OR ca.kota LIKE '%Sidoarjo%'
          OR ca.kota LIKE '%Jember%' OR ca.kota LIKE '%Kediri%' OR ca.kota LIKE '%Madiun%'
          OR ca.kota LIKE '%Banyuwangi%' OR ca.kota LIKE '%Gresik%' OR ca.kota LIKE '%Pasuruan%'
          OR ca.kota LIKE '%Probolinggo%' OR ca.kota LIKE '%Tuban%' OR ca.kota LIKE '%Bojonegoro%'
          OR ca.kota LIKE '%Blitar%' OR ca.kota LIKE '%Lamongan%' OR ca.kota LIKE '%Mojokerto%'
      )
      AND c.deleted_at IS NULL
    GROUP BY ca.kota, s.nama_lengkap
    ORDER BY cnt DESC
    LIMIT 20
");

$countMissingJatim = 0;
while ($r = $qUnassignedJatim->fetch_assoc()) {
    $countMissingJatim += (int)$r['cnt'];
    echo "   • {$r['kota']} ({$r['cnt']} toko) - Masih dipegang: {$r['current_holder']}\n";
}
echo "Total Toko Jatim yang masih tercecer di sales lain: {$countMissingJatim} toko\n\n";

echo "=================================================================\n";
