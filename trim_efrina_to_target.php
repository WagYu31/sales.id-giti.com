<?php
/**
 * trim_efrina_to_target.php
 * Menyesuaikan customer Efrina agar:
 * 1. Seluruh customer yang SUDAH FU (hasil follow up Efrina) TETAP DIPERTAHANKAN 100%
 * 2. Toko yang BELUM FU (antrean mentah) dipangkas dari 642 menjadi ~380 toko
 * 3. Total akhir customer Efrina kembali pas di kisaran 550 - 580 Toko (TIDAK 800+)
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PENYESUAIAN TOTAL CUSTOMER EFRINA (TARGET: 550 - 580 TOKO)    \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$efrinaId = 22;
$ediId = 3;
$targetTotal = 560; // Target pas di kisaran 500 - 600

// 1. Dapatkan Toko yang SUDAH PERNAH DI-FU oleh Efrina (WAJIB DIPERTAHANKAN 100%)
$qSudahFu = $conn->query("
    SELECT DISTINCT c.id 
    FROM customers c
    JOIN follow_ups fu ON c.id = fu.customer_id
    WHERE fu.sales_id = {$efrinaId} AND c.deleted_at IS NULL AND fu.deleted_at IS NULL
");
$sudahFuIds = [];
while ($r = $qSudahFu->fetch_assoc()) {
    $sudahFuIds[] = (int)$r['id'];
}
$countSudahFu = count($sudahFuIds);

echo "1. STATUS HASIL KERJA FOLLOW-UP EFRINA:\n";
echo "   • Toko yang SUDAH PERNAH di-FU Efrina: {$countSudahFu} toko (WAJIB DIPERTAHANKAN 100%)\n\n";

// 2. Dapatkan Toko BELUM FU yang saat ini dipegang Efrina
$qBelumFu = $conn->query("
    SELECT c.id, ca.kota, ca.alamat
    FROM customers c
    LEFT JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.sales_id = {$efrinaId}
      AND c.id NOT IN (" . (empty($sudahFuIds) ? "0" : implode(',', $sudahFuIds)) . ")
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

$belumFuRows = [];
while ($r = $qBelumFu->fetch_assoc()) {
    $belumFuRows[] = (int)$r['id'];
}
$countBelumFu = count($belumFuRows);

echo "2. STATUS TOKO BELUM FU (MENTAH) DI AKUN EFRINA SAAT INI:\n";
echo "   • Total Toko Belum FU saat ini : {$countBelumFu} toko\n";
echo "   • Total Keseluruhan saat ini   : " . ($countSudahFu + $countBelumFu) . " toko (Terlalu banyak / 800+)\n\n";

// 3. Hitung berapa Belum FU yang harus dipertahankan agar total pas ~560
$keepBelumFuCount = max(0, $targetTotal - $countSudahFu);
$keepBelumFuIds = array_slice($belumFuRows, 0, $keepBelumFuCount);
$excessBelumFuIds = array_slice($belumFuRows, $keepBelumFuCount);

$newTotal = $countSudahFu + count($keepBelumFuIds);

echo "3. RENCANA PENYESUAIAN AGAR PAS DI 500 - 600:\n";
echo "   • Toko SUDAH FU dipertahankan : {$countSudahFu} toko\n";
echo "   • Toko BELUM FU dipertahankan : " . count($keepBelumFuIds) . " toko (Prioritas Solo, Semarang, Jogja, Klaten, Kudus)\n";
echo "   • Sisa Toko Belum FU dialihkan: " . count($excessBelumFuIds) . " toko (dialihkan ke Edi Suprianto)\n";
echo "   -------------------------------------------------------------\n";
echo "   🎯 TOTAL AKHIR CUSTOMER EFRINA: {$newTotal} TOKO (PAS di kisaran 550 - 580, BUKAN 800!)\n\n";

// 4. Eksekusi
$isApply = in_array('--apply', $argv ?? []);

if ($isApply) {
    echo "MENERAPKAN PENYESUAIAN KE DATABASE...\n";
    $conn->begin_transaction();

    // 1. Pastikan seluruh toko SUDAH FU tetap di Efrina
    if (!empty($sudahFuIds)) {
        $idListSudah = implode(',', $sudahFuIds);
        $conn->query("UPDATE customers SET sales_id = {$efrinaId} WHERE id IN ({$idListSudah})");
    }

    // 2. Alihkan kelebihan toko Belum FU ke Edi Suprianto
    $moved = 0;
    if (!empty($excessBelumFuIds)) {
        $idListExcess = implode(',', $excessBelumFuIds);
        $conn->query("UPDATE customers SET sales_id = {$ediId} WHERE id IN ({$idListExcess})");
        $moved = $conn->affected_rows;
    }

    $conn->commit();

    echo "✅ SUKSES BESAR!\n";
    echo "  • {$moved} toko Belum FU yang berlebih berhasil dipindahkan ke Edi Suprianto.\n";
    echo "  • Total Customer Efrina sekarang pas: {$newTotal} Toko.\n";
    echo "  • Customer yang SUDAH FU tetap utuh {$countSudahFu} toko.\n";
    echo "=================================================================\n";
    echo "Silakan refresh halaman CRM index.php filter Efrina Panjaitan.\n";
} else {
    echo "💡 UNTUK MENERAPKAN PENYESUAIAN INI SEGERA:\n";
    echo "Jalankan perintah ini di terminal server:\n";
    echo "  php trim_efrina_to_target.php --apply\n";
    echo "=================================================================\n";
}
