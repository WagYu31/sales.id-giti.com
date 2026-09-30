<?php
/**
 * restore_priska_customers.php
 * Script untuk mengembalikan seluruh 500+ customer milik Priska (ID: 21)
 * sesuai wilayah penugasan lengkapnya:
 * - Aceh (Banda Aceh, Lhokseumawe, Meulaboh, Langsa, dll.)
 * - Sumatera Barat (Padang, Bukittinggi, Payakumbuh, Pariaman, Solok, dll.)
 * - Bali & Nusa Tenggara (Kupang, Denpasar, Badung, Gianyar, Ende, dll.)
 * - Kepulauan Riau & Riau (Batam, Tanjung Pinang, Bintan, dll.)
 *
 * Cara menjalankan di terminal server:
 *   cd /www/wwwroot/sales.id-giti.com
 *   php restore_priska_customers.php
 *   php restore_priska_customers.php --apply
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PEMULIHAN 500+ DATA CUSTOMER LENGKAP MILIK PRISKA             \n";
echo "=================================================================\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Error: Koneksi database gagal.\n");
}

// 1. Dapatkan ID Sales Priska
$qSales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE nama_lengkap LIKE '%Priska%' AND deleted_at IS NULL LIMIT 1");
$priska = $qSales ? $qSales->fetch_assoc() : null;
$priskaId = $priska ? (int)$priska['id'] : 21;
$priskaName = $priska ? $priska['nama_lengkap'] : 'Priska';

echo "Sales Target: {$priskaName} (ID: {$priskaId})\n\n";

// 2. Daftar Wilayah Penugasan Priska
$wilayahKeywords = [
    'Aceh' => [
        'aceh', 'banda aceh', 'lhokseumawe', 'meulaboh', 'langsa', 'subulussalam', 
        'bireuen', 'pidie', 'takengon', 'aceh barat', 'aceh besar', 'aceh utara', 
        'aceh selatan', 'aceh timur', 'aceh tengah', 'singkil', 'simeulue'
    ],
    'Sumatera Barat' => [
        'padang', 'bukittinggi', 'payakumbuh', 'pariaman', 'solok', 'pasaman', 
        'sijunjung', 'dharmasraya', 'pesisir selatan', 'tanah datar', 'sawahlunto', 
        'padang panjang', 'sumbar', 'sumatera barat', 'lubuk basung', 'lubuk sikaping'
    ],
    'Bali & NTT' => [
        'kupang', 'ntt', 'nusa tenggara timur', 'ende', 'maumere', 'labuan bajo', 
        'sumba', 'rote', 'alor', 'bali', 'denpasar', 'badung', 'singaraja', 'tabanan', 
        'gianyar', 'klungkung', 'bangli', 'karangasem', 'jembrana', 'buleleng'
    ],
    'Kepulauan Riau' => [
        'batam', 'tanjung pinang', 'tanjungpinang', 'kepulauan riau', 'bintan', 
        'karimun', 'natuna', 'anambas', 'lingga'
    ]
];

// 3. Scan Customer yang Berada di Wilayah Priska
$sql = "
    SELECT 
        c.id, 
        c.nama_toko, 
        c.sales_id,
        ca.kota, 
        ca.alamat,
        ca.provinsi,
        s.nama_lengkap as current_sales
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE c.deleted_at IS NULL AND ca.deleted_at IS NULL
";

$res = $conn->query($sql);
if (!$res) {
    die("Error query: " . $conn->error . "\n");
}

$priskaCustomers = [];
$statsWilayah = [];
$sudahDiPriska = 0;
$belumDiPriska = 0;
$currentHolders = [];

while ($row = $res->fetch_assoc()) {
    $cid = (int)$row['id'];
    if (isset($priskaCustomers[$cid])) continue;

    $haystack = strtolower(($row['kota'] ?? '') . ' ' . ($row['alamat'] ?? '') . ' ' . ($row['provinsi'] ?? ''));

    $matchedRegion = null;
    foreach ($wilayahKeywords as $regionName => $kws) {
        foreach ($kws as $kw) {
            if (strpos($haystack, $kw) !== false) {
                $matchedRegion = $regionName;
                break 2;
            }
        }
    }

    if ($matchedRegion) {
        $row['matched_region'] = $matchedRegion;
        $priskaCustomers[$cid] = $row;
        $statsWilayah[$matchedRegion] = ($statsWilayah[$matchedRegion] ?? 0) + 1;

        if ((int)$row['sales_id'] === $priskaId) {
            $sudahDiPriska++;
        } else {
            $belumDiPriska++;
            $holder = $row['current_sales'] ?? 'Belum Ada Sales';
            $currentHolders[$holder] = ($currentHolders[$holder] ?? 0) + 1;
        }
    }
}

$totalMatched = count($priskaCustomers);

echo "HASIL ANALISIS WILAYAH LENGKAP CUSTOMER PRISKA:\n";
echo "-----------------------------------------------------------------\n";
foreach ($wilayahKeywords as $reg => $kws) {
    $cnt = $statsWilayah[$reg] ?? 0;
    echo "  • {$reg}: " . number_format($cnt) . " toko\n";
}
echo "-----------------------------------------------------------------\n";
echo "TOTAL TOKO DI WILAYAH PRISKA : " . number_format($totalMatched) . " Toko\n\n";

echo "STATUS SAAT INI:\n";
echo "  ✓ Sudah tercatat atas nama Priska : " . number_format($sudahDiPriska) . " toko\n";
echo "  ⚠️ Tertinggal di sales lain      : " . number_format($belumDiPriska) . " toko\n\n";

if (!empty($currentHolders)) {
    echo "Rincian Toko Priska yang Tertahan di Akun Lain:\n";
    foreach ($currentHolders as $holder => $c) {
        echo "  - Dipegang {$holder}: {$c} toko\n";
    }
    echo "\n";
}

// 4. Eksekusi Pengembalian
$isApply = in_array('--apply', $argv ?? []);

if ($isApply) {
    echo "EKSEKUSI PEMULIHAN SELURUH TOKO KE PRISKA...\n";
    $conn->begin_transaction();

    $stmtUp = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");
    $applied = 0;

    foreach ($priskaCustomers as $cid => $data) {
        if ((int)$data['sales_id'] !== $priskaId) {
            $stmtUp->bind_param("ii", $priskaId, $cid);
            $stmtUp->execute();
            if ($conn->affected_rows > 0) $applied++;
        }
    }

    $stmtUp->close();
    $conn->commit();

    echo "\n=================================================================\n";
    echo "✅ SUKSES BESAR! Sebanyak {$applied} toko tambahan berhasil dikembalikan ke Priska!\n";
    echo "Kini Priska memegang total " . number_format($totalMatched) . " TOKO LENGKAP sesuai pembagian aslinya.\n";
    echo "Silakan refresh halaman CRM index.php filter Priska.\n";
    echo "=================================================================\n";
} else {
    echo "=================================================================\n";
    echo "💡 UNTUK MENERAPKAN PERUBAHAN:\n";
    echo "Jalankan perintah ini di terminal server:\n";
    echo "  php restore_priska_customers.php --apply\n";
    echo "=================================================================\n";
}
