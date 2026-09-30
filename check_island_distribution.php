<?php
/**
 * check_island_distribution.php
 * Cek siapa sales yang sebenarnya mem-follow up pulau-pulau di luar Jawa:
 * - Bali & Nusa Tenggara
 * - Kalimantan
 * - Sulawesi
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   ANALISIS PENANGGUNG JAWAB ASLI BALI, KALIMANTAN & SULAWESI   \n";
echo "=================================================================\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$regions = [
    'Bali' => ['denpasar', 'badung', 'gianyar', 'tabanan', 'buleleng', 'singaraja', 'klungkung', 'bangli', 'karangasem', 'jembrana', 'bali'],
    'NTB & NTT' => ['mataram', 'lombok', 'sumbawa', 'bima', 'kupang', 'ende', 'maumere', 'labuan bajo', 'sumba', 'rote', 'alor', 'ntt', 'ntb'],
    'Kalimantan' => ['balikpapan', 'samarinda', 'pontianak', 'banjarmasin', 'banjarbaru', 'palangkaraya', 'tarakan', 'singkawang', 'kalimantan', 'kaltim', 'kalbar', 'kalsel', 'kalteng', 'kaltara'],
    'Sulawesi' => ['makassar', 'manado', 'palu', 'kendari', 'gorontalo', 'mamuju', 'bitung', 'kotamobagu', 'bau-bau', 'sulawesi', 'sulsel', 'sulteng', 'sulut', 'sultra', 'sulbar']
];

foreach ($regions as $regName => $kws) {
    echo "=== {$regName} ===\n";
    
    // Cek total customer
    $conds = [];
    foreach ($kws as $kw) {
        $conds[] = "ca.kota LIKE '%$kw%' OR ca.alamat LIKE '%$kw%'";
    }
    $where = "(" . implode(' OR ', $conds) . ")";
    
    $totalCust = $conn->query("
        SELECT COUNT(DISTINCT c.id) 
        FROM customers c 
        JOIN customer_addresses ca ON c.id = ca.customer_id 
        WHERE c.deleted_at IS NULL AND {$where}
    ")->fetch_row()[0];
    
    echo "Total Toko di Database: {$totalCust} toko\n";
    
    // Cek siapa yang paling sering follow-up di wilayah ini
    $qFu = $conn->query("
        SELECT s.nama_lengkap, COUNT(fu.id) as cnt_fu, COUNT(DISTINCT fu.customer_id) as cnt_cust
        FROM follow_ups fu
        JOIN sales s ON fu.sales_id = s.id
        JOIN customers c ON fu.customer_id = c.id
        JOIN customer_addresses ca ON c.id = ca.customer_id
        WHERE fu.deleted_at IS NULL AND {$where}
        GROUP BY s.nama_lengkap
        ORDER BY cnt_cust DESC
    ");
    
    echo "Riwayat Sales yang Pernah Follow Up di {$regName}:\n";
    if ($qFu && $qFu->num_rows > 0) {
        while ($rf = $qFu->fetch_assoc()) {
            echo "   • {$rf['nama_lengkap']}: {$rf['cnt_cust']} customer ({$rf['cnt_fu']} kali FU)\n";
        }
    } else {
        echo "   (Belum pernah ada FU di wilayah ini)\n";
    }
    
    // Cek siapa yang saat ini memegang toko di wilayah ini
    $qHold = $conn->query("
        SELECT COALESCE(s.nama_lengkap, 'Belum Ditugaskan') as sales_name, COUNT(DISTINCT c.id) as cnt
        FROM customers c
        LEFT JOIN sales s ON c.sales_id = s.id
        JOIN customer_addresses ca ON c.id = ca.customer_id
        WHERE c.deleted_at IS NULL AND {$where}
        GROUP BY sales_name
        ORDER BY cnt DESC
    ");
    echo "Pemegang Saat Ini:\n";
    while ($rh = $qHold->fetch_assoc()) {
        echo "   -> {$rh['sales_name']}: {$rh['cnt']} toko\n";
    }
    echo "\n";
}
