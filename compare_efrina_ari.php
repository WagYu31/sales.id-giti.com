<?php
/**
 * compare_efrina_ari.php
 * Cek pembagian sebenarnya antara Efrina Panjaitan dan Ari Wahyudi (atau sales lain)
 * berdasarkan riwayat follow up mereka di Jawa Tengah.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   ANALISIS PEMBAGIAN ASLI WILAYAH JAWA TENGAH                   \n";
echo "=================================================================\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

// 1. Cek Riwayat Follow Up Efrina (ID: 22)
echo "1. DAERAH YANG DIFOLLOW-UP EFRINA PANJAITAN (ID: 22):\n";
$qEf = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tidak Terisi') as kota,
        COUNT(DISTINCT c.id) as cnt
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE fu.sales_id = 22 AND fu.deleted_at IS NULL
    GROUP BY kota
    ORDER BY cnt DESC
");
while ($r = $qEf->fetch_assoc()) {
    echo "   • {$r['kota']}: {$r['cnt']} toko\n";
}
echo "\n";

// 2. Cek Riwayat Follow Up Ari Wahyudi (ID: 13)
echo "2. DAERAH YANG DIFOLLOW-UP ARI WAHYUDI (ID: 13):\n";
$qAri = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tidak Terisi') as kota,
        COUNT(DISTINCT c.id) as cnt
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE fu.sales_id = 13 AND fu.deleted_at IS NULL
    GROUP BY kota
    ORDER BY cnt DESC
");
while ($r = $qAri->fetch_assoc()) {
    echo "   • {$r['kota']}: {$r['cnt']} toko\n";
}
echo "\n";

// 3. Cek Status Ari Wahyudi di tabel sales
$sAri = $conn->query("SELECT id, nama_lengkap, role, deleted_at FROM sales WHERE id = 13")->fetch_assoc();
echo "Status Akun Ari Wahyudi (ID: 13): " . json_encode($sAri) . "\n\n";

// 4. Hitung Total Toko jika dibagi per zona:
// Zona 1: Solo Raya & Semarang & Jogja & Sekitarnya (Klaten, Kudus, Semarang, Karanganyar, Magelang, Sukoharjo, Pati, Temanggung, Sragen, Grobogan, Demak, Surakarta, Wonogiri, Blora, Salatiga, Boyolali, Rembang, Yogyakarta)
$zonaPusatTimur = [
    'klaten', 'kudus', 'semarang', 'karanganyar', 'magelang', 'sukoharjo', 'pati', 
    'temanggung', 'sragen', 'grobokan', 'grobogan', 'demak', 'surakarta', 'wonogiri', 
    'blora', 'salatiga', 'boyolali', 'rembang', 'yogyakarta', 'jogja', 'sleman', 'bantul'
];

// Zona 2: Pantura Barat & Banyumasan (Tegal, Brebes, Kebumen, Cilacap, Pekalongan, Purbalingga, Purworejo, Banyumas, Kendal, Pemalang, Batang, Banjarnegara, Wonosobo)
$zonaBarat = [
    'tegal', 'brebes', 'kebumen', 'cilacap', 'pekalongan', 'purbalingga', 'purworejo', 
    'banyumas', 'kendal', 'pemalang', 'batang', 'banjarnegara', 'wonosobo'
];

$sqlPusat = "SELECT COUNT(DISTINCT c.id) FROM customers c JOIN customer_addresses ca ON c.id = ca.customer_id WHERE c.deleted_at IS NULL AND (";
$orPusat = [];
foreach ($zonaPusatTimur as $z) {
    $orPusat[] = "ca.kota LIKE '%$z%' OR ca.alamat LIKE '%$z%'";
}
$sqlPusat .= implode(' OR ', $orPusat) . ")";
$cntPusat = $conn->query($sqlPusat)->fetch_row()[0];

$sqlBarat = "SELECT COUNT(DISTINCT c.id) FROM customers c JOIN customer_addresses ca ON c.id = ca.customer_id WHERE c.deleted_at IS NULL AND (";
$orBarat = [];
foreach ($zonaBarat as $z) {
    $orBarat[] = "ca.kota LIKE '%$z%' OR ca.alamat LIKE '%$z%'";
}
$sqlBarat .= implode(' OR ', $orBarat) . ")";
$cntBarat = $conn->query($sqlBarat)->fetch_row()[0];

echo "POTENSI JUMLAH CUSTOMER PER ZONA DI JAWA TENGAH:\n";
echo "• Zona Solo Raya, Semarang & DIY (Klaten, Kudus, Semarang, Magelang, Solo, Jogja, dll) : ~{$cntPusat} toko\n";
echo "• Zona Pantura Barat & Banyumas (Tegal, Brebes, Cilacap, Pekalongan, Purbalingga, dll)    : ~{$cntBarat} toko\n";
echo "=================================================================\n";
