<?php
/**
 * inspect_efrina_stores.php
 * Investigasi detail seluruh toko yang saat ini ada di akun Efrina Panjaitan (1.184 toko)
 * untuk menemukan toko yang bukan wilayah aslinya.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   INVESTIGASI RINCIAN TOKO EFRINA PANJAITAN                     \n";
echo "=================================================================\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$efrinaId = 22;

// 1. Cek Toko Bengkulu atau Sumatera di Efrina
echo "1. CEK TOKO DI LUAR JAWA TENGAH & DIY PADA AKUN EFRINA:\n";
$qSumatera = $conn->query("
    SELECT c.id, c.nama_toko, ca.kota, ca.provinsi, ca.alamat,
           (SELECT COUNT(*) FROM follow_ups WHERE customer_id = c.id AND sales_id = {$efrinaId}) as fu_by_efrina,
           (SELECT s.nama_lengkap FROM follow_ups fu JOIN sales s ON fu.sales_id = s.id WHERE fu.customer_id = c.id ORDER BY fu.id DESC LIMIT 1) as last_fu_by
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.sales_id = {$efrinaId}
      AND (
          ca.kota LIKE '%Bengkulu%' OR ca.kota LIKE '%Lampung%' OR ca.kota LIKE '%Palembang%' 
          OR ca.kota LIKE '%Jambi%' OR ca.kota LIKE '%Medan%' OR ca.kota LIKE '%Riau%'
          OR ca.kota LIKE '%Padang%' OR ca.kota LIKE '%Aceh%' OR ca.kota LIKE '%Batam%'
          OR ca.provinsi LIKE '%Sumatera%' OR ca.provinsi LIKE '%Bengkulu%' OR ca.provinsi LIKE '%Lampung%'
      )
      AND c.deleted_at IS NULL
");

$sumateraCount = 0;
while ($r = $qSumatera->fetch_assoc()) {
    $sumateraCount++;
    echo "   • [ID: {$r['id']}] {$r['nama_toko']} | Kota: {$r['kota']} | FU Efrina: {$r['fu_by_efrina']} | Terakhir di-FU oleh: {$r['last_fu_by']}\n";
    echo "     Alamat: {$r['alamat']}\n";
}
echo "Total Toko Luar Pulau / Sumatera di Akun Efrina: {$sumateraCount} toko\n\n";

// 2. Rekapitulasi Berdasarkan Provinsi di Akun Efrina
echo "2. REKAPITULASI PROVINSI TOKO DI AKUN EFRINA:\n";
$qProv = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.provinsi), ''), 'Tidak Terisi') as prov_clean,
        COUNT(DISTINCT c.id) as cnt
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.sales_id = {$efrinaId} AND c.deleted_at IS NULL
    GROUP BY prov_clean
    ORDER BY cnt DESC
");
while ($r = $qProv->fetch_assoc()) {
    echo "   • {$r['prov_clean']}: {$r['cnt']} toko\n";
}
echo "\n";

// 3. Rincian Kota di Akun Efrina (Top 30)
echo "3. TOP 30 KOTA DI AKUN EFRINA:\n";
$qKota = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tidak Terisi') as kota_clean,
        COUNT(DISTINCT c.id) as cnt
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.sales_id = {$efrinaId} AND c.deleted_at IS NULL
    GROUP BY kota_clean
    ORDER BY cnt DESC
    LIMIT 30
");
while ($r = $qKota->fetch_assoc()) {
    echo "   • {$r['kota_clean']}: {$r['cnt']} toko\n";
}
echo "=================================================================\n";
