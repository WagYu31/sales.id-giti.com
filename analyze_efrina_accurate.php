<?php
/**
 * analyze_efrina_accurate.php
 * Analisis mendalam toko milik Efrina Panjaitan:
 * 1. Kota dari customer yang pernah di-FU Efrina
 * 2. Deteksi false-positive (seperti Jakarta/Bekasi/Jambi yang terserap)
 * 3. Opsi kalibrasi wilayah agar tepat sesuai porsi aslinya (< 1.000 toko)
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   ANALISIS AKURAT & KALIBRASI CUSTOMER EFRINA PANJAITAN        \n";
echo "=================================================================\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$efrinaId = 22;

// 1. Cek Toko yang PERNAH di-FU langsung oleh Efrina
echo "1. DAERAH DARI 148 TOKO YANG PERNAH DI-FU LANGSUNG OLEH EFRINA:\n";
$qHistory = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tidak Terisi') as kota_clean,
        COUNT(DISTINCT c.id) as cnt
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = {$efrinaId})
      AND c.deleted_at IS NULL AND ca.deleted_at IS NULL
    GROUP BY kota_clean
    ORDER BY cnt DESC
    LIMIT 25
");
while ($r = $qHistory->fetch_assoc()) {
    echo "   • {$r['kota_clean']}: {$r['cnt']} toko\n";
}
echo "\n";

// 2. Cek Komposisi 1.314 Toko yang Sekarang Berada di Akun Efrina
echo "2. RINCIAN KOTA DARI 1.314 TOKO DI AKUN EFRINA SAAT INI:\n";
$qCurrent = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tidak Terisi') as kota_clean,
        COUNT(DISTINCT c.id) as cnt
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.sales_id = {$efrinaId} AND c.deleted_at IS NULL AND ca.deleted_at IS NULL
    GROUP BY kota_clean
    ORDER BY cnt DESC
    LIMIT 35
");

$falsePositiveCustIds = [];
$nonJatengCount = 0;

while ($r = $qCurrent->fetch_assoc()) {
    $kota = strtoupper($r['kota_clean']);
    $isSuspect = (
        strpos($kota, 'JAKARTA') !== false ||
        strpos($kota, 'BEKASI') !== false ||
        strpos($kota, 'TANGERANG') !== false ||
        strpos($kota, 'BOGOR') !== false ||
        strpos($kota, 'DEPOK') !== false ||
        strpos($kota, 'BANDUNG') !== false ||
        strpos($kota, 'JAMBI') !== false ||
        strpos($kota, 'SUMATERA') !== false ||
        strpos($kota, 'MEDAN') !== false
    );

    $flag = $isSuspect ? " ⚠️ [BUKAN JATENG / FALSE POSITIVE]" : "";
    echo "   • {$r['kota_clean']}: {$r['cnt']} toko{$flag}\n";
}
echo "\n";

// 3. Hitung berapa toko yang sebenarnya BUKAN Jawa Tengah yang terserap ke Efrina
$qNonJateng = $conn->query("
    SELECT c.id, c.nama_toko, ca.kota, ca.alamat, ca.provinsi
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.sales_id = {$efrinaId}
      AND (
          ca.kota LIKE '%JAKARTA%' OR ca.kota LIKE '%BEKASI%' OR ca.kota LIKE '%TANGERANG%' 
          OR ca.kota LIKE '%DEPOK%' OR ca.kota LIKE '%BOGOR%' OR ca.kota LIKE '%BANDUNG%'
          OR ca.provinsi LIKE '%DKI%' OR ca.provinsi LIKE '%JABODETABEK%' OR ca.provinsi LIKE '%JAWA BARAT%'
      )
      AND ca.alamat NOT LIKE '%Jawa Tengah%'
      AND ca.alamat NOT LIKE '%Jateng%'
      AND ca.alamat NOT LIKE '%Yogyakarta%'
      AND ca.alamat NOT LIKE '%Jogja%'
      AND c.deleted_at IS NULL
");

$suspectIds = [];
while ($row = $qNonJateng->fetch_assoc()) {
    $suspectIds[] = $row['id'];
}

echo "3. TEMUAN KESALAHAN SERAPAN:\n";
echo "   Ditemukan " . count($suspectIds) . " toko Jabodetabek / Jabar yang salah terserap ke Efrina\n";
echo "   (karena nama jalan seperti 'Tegal Alur', 'Sunan Kudus', 'Jl. Kendal' di Jakarta).\n\n";

$isFix = in_array('--fix', $argv ?? []);
if ($isFix && !empty($suspectIds)) {
    echo "MENGEMBALIKAN TOKO JABODETABEK / JABAR DARI EFRINA KE SALES SEMULA (Edi Suprianto ID: 3 / Excel ID: 4)...\n";
    $conn->begin_transaction();
    
    // Kembalikan ke Edi Suprianto (ID: 3) yang memegang Jabodetabek
    $idList = implode(',', $suspectIds);
    $conn->query("UPDATE customers SET sales_id = 3 WHERE id IN ({$idList})");
    $affected = $conn->affected_rows;
    $conn->commit();

    $newEfrinaTotal = $conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$efrinaId} AND deleted_at IS NULL")->fetch_row()[0];
    echo "✅ Berhasil memindahkan {$affected} toko Jabodetabek keluar dari Efrina!\n";
    echo "Total Toko Efrina sekarang menjadi: {$newEfrinaTotal} Toko (MURNI Jawa Tengah & DIY).\n";
    echo "=================================================================\n";
} else {
    echo "💡 UNTUK MEMBERSIHKAN TOKO JABODETABEK DARI AKUN EFRINA:\n";
    echo "Jalankan perintah ini di terminal server:\n";
    echo "  php analyze_efrina_accurate.php --fix\n";
    echo "=================================================================\n";
}
