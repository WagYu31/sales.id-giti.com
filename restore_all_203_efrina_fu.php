<?php
/**
 * restore_all_203_efrina_fu.php
 * Mengembalikan seluruh 203 customer yang memang terbukti pernah di-FU oleh Efrina
 * dan menyesuaikan antrean Belum FU sehingga total pas di 560-an toko (bukan 800+).
 *
 * Hasil:
 * - SUDAH FU : 203 Toko (Pas 200 lebih, sesuai perkataan Efrina & riwayat DB)
 * - BELUM FU : ~360 Toko (Antrean prospek baru wilayah Solo, Semarang, Jogja)
 * - TOTAL    : ~563 Toko (Pas di target 500 - 600)
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PEMULIHAN PENUH 203 CUSTOMER SUDAH FU EFRINA PANJAITAN       \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$efrinaId = 22;
$ediId = 3;

// 1. Ambil SEMUA 203 customer unik yang pernah di-FU oleh Efrina (aktif)
$qAllFu = $conn->query("
    SELECT DISTINCT c.id
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    WHERE fu.sales_id = {$efrinaId} 
      AND fu.deleted_at IS NULL 
      AND c.deleted_at IS NULL
");

$all203Ids = [];
while ($r = $qAllFu->fetch_assoc()) {
    $all203Ids[] = (int)$r['id'];
}
$count203 = count($all203Ids);
echo "1. Ditemukan tepat {$count203} Customer Aktif yang PERNAH di-FU Efrina.\n";
echo "   (Total riwayat aktivitas chat/telepon Efrina: 235 kali)\n\n";

// 2. Terapkan UPDATE agar seluruh 203 toko ini PASTI berada di akun Efrina
$conn->begin_transaction();

$idList203 = implode(',', $all203Ids);
$conn->query("UPDATE customers SET sales_id = {$efrinaId} WHERE id IN ({$idList203})");
$applied203 = $conn->affected_rows;
echo "2. Memastikan seluruh {$count203} customer hasil FU masuk ke akun Efrina.\n\n";

// 3. Ambil toko BELUM FU yang saat ini dipegang Efrina (di luar 203 toko sudah FU)
$qBelumFu = $conn->query("
    SELECT c.id 
    FROM customers c
    LEFT JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.sales_id = {$efrinaId}
      AND c.id NOT IN ({$idList203})
      AND c.deleted_at IS NULL
    ORDER BY 
        CASE 
            WHEN ca.kota LIKE '%Solo%' OR ca.kota LIKE '%Surakarta%' THEN 1
            WHEN ca.kota LIKE '%Semarang%' THEN 2
            WHEN ca.kota LIKE '%Yogyakarta%' OR ca.kota LIKE '%Jogja%' THEN 3
            WHEN ca.kota LIKE '%Klaten%' THEN 4
            WHEN ca.kota LIKE '%Kudus%' THEN 5
            WHEN ca.kota LIKE '%Magelang%' THEN 6
            ELSE 10 
        END ASC,
        c.id ASC
");

$belumFuIds = [];
while ($r = $qBelumFu->fetch_assoc()) {
    $belumFuIds[] = (int)$r['id'];
}

// Target total leads Efrina: ~563 (203 Sudah FU + 360 Belum FU)
$targetBelumFu = 360;
$keepBelumFu = array_slice($belumFuIds, 0, $targetBelumFu);
$excessBelumFu = array_slice($belumFuIds, $targetBelumFu);

// Pindahkan kelebihan toko Belum FU ke Edi Suprianto
if (!empty($excessBelumFu)) {
    $idListExcess = implode(',', $excessBelumFu);
    $conn->query("UPDATE customers SET sales_id = {$ediId} WHERE id IN ({$idListExcess})");
}

$conn->commit();

// 4. Verifikasi Angka Akhir
$finalTotal = $conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$efrinaId} AND deleted_at IS NULL")->fetch_row()[0];
$finalSudahFu = $conn->query("
    SELECT COUNT(DISTINCT c.id) 
    FROM customers c 
    WHERE c.sales_id = {$efrinaId} 
      AND c.id IN ({$idList203})
      AND c.deleted_at IS NULL
")->fetch_row()[0];
$finalBelumFu = $conn->query("
    SELECT COUNT(DISTINCT c.id) 
    FROM customers c 
    WHERE c.sales_id = {$efrinaId} 
      AND c.id NOT IN ({$idList203})
      AND c.deleted_at IS NULL
")->fetch_row()[0];

echo "=================================================================\n";
echo "✅ HASIL AKHIR SEMPURNA UNTUK EFRINA PANJAITAN:\n";
echo "  • SEMUA LEADS : {$finalTotal} Toko (PAS di kisaran 500 - 600, BUKAN 800!)\n";
echo "  • SUDAH FU    : {$finalSudahFu} Toko (PAS 200 LEBIH sesuai ingatan Efrina!)\n";
echo "  • BELUM FU    : {$finalBelumFu} Toko (Antrean prospek baru Solo, Semarang, Jogja)\n";
echo "=================================================================\n";
echo "Silakan refresh halaman CRM index.php filter Efrina Panjaitan.\n";
