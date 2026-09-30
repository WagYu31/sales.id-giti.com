<?php
/**
 * restore_natalia_customers.php
 * Script untuk menganalisis dan mengembalikan customer milik Natalia Christi
 * sesuai wilayah penugasan aslinya (Jawa Timur & sekitarnya).
 *
 * Cara menjalankan di terminal server:
 *   cd /www/wwwroot/sales.id-giti.com
 *   php restore_natalia_customers.php
 *   php restore_natalia_customers.php --apply
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   ANALISIS & PEMULIHAN DATA CUSTOMER NATALIA CHRISTI            \n";
echo "=================================================================\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Error: Koneksi database gagal.\n");
}

// 1. Dapatkan ID Sales Natalia Christi
$qSales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE nama_lengkap LIKE '%Natalia%' AND deleted_at IS NULL LIMIT 1");
$natalia = $qSales ? $qSales->fetch_assoc() : null;

if (!$natalia) {
    die("Error: Sales dengan nama 'Natalia' tidak ditemukan di database.\n");
}

$nataliaId = (int)$natalia['id'];
$nataliaName = $natalia['nama_lengkap'];

echo "Sales Target: {$nataliaName} (ID: {$nataliaId})\n\n";

// 2. Analisis Toko yang saat ini dipegang Natalia
$qCurrent = $conn->query("SELECT COUNT(*) as cnt FROM customers WHERE sales_id = {$nataliaId} AND deleted_at IS NULL");
$cntCurrent = $qCurrent ? (int)$qCurrent->fetch_assoc()['cnt'] : 0;
echo "1. Toko yang saat ini tercatat di akun Natalia: {$cntCurrent} Toko\n";

// 3. Analisis Riwayat Follow-Up oleh Natalia
$qFu = $conn->query("
    SELECT 
        COUNT(DISTINCT customer_id) as cust_count,
        COUNT(*) as fu_count
    FROM follow_ups 
    WHERE sales_id = {$nataliaId} AND deleted_at IS NULL
");
$fuData = $qFu ? $qFu->fetch_assoc() : ['cust_count' => 0, 'fu_count' => 0];
echo "2. Riwayat Follow Up oleh Natalia:\n";
echo "   - Jumlah Customer Pernah Dihubungi : {$fuData['cust_count']} Customer\n";
echo "   - Total Riwayat Aktivitas FU       : {$fuData['fu_count']} kali\n\n";

// 4. Analisis Kota & Wilayah dari Toko yang Dipegang / Di-FU Natalia
$qSampleCities = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tidak Diketahui') as kota_clean,
        COUNT(DISTINCT c.id) as cnt
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE (c.sales_id = {$nataliaId} OR c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = {$nataliaId} AND deleted_at IS NULL))
      AND c.deleted_at IS NULL AND ca.deleted_at IS NULL
    GROUP BY kota_clean
    ORDER BY cnt DESC
    LIMIT 20
");

echo "3. Kota/Daerah Dominan Milik Natalia Saat Ini:\n";
if ($qSampleCities && $qSampleCities->num_rows > 0) {
    while ($rc = $qSampleCities->fetch_assoc()) {
        echo "   • {$rc['kota_clean']}: {$rc['cnt']} toko\n";
    }
} else {
    echo "   (Belum ada data)\n";
}
echo "\n";

// 5. Daftar Kata Kunci Wilayah Jawa Timur (Wilayah Natalia)
$jatimKeywords = [
    'surabaya', 'malang', 'sidoarjo', 'jombang', 'gresik', 'mojokerto', 
    'pasuruan', 'probolinggo', 'banyuwangi', 'jember', 'kediri', 'madiun', 
    'blitar', 'bojonegoro', 'tuban', 'lamongan', 'ponorogo', 'tulungagung', 
    'magetan', 'ngawi', 'situbondo', 'bondowoso', 'trenggalek', 'nganjuk', 
    'pacitan', 'bangkalan', 'sampang', 'pamekasan', 'sumenep', 'batu', 
    'lumajang', 'jawa timur', 'jatim'
];

// 6. Scan Seluruh Database untuk Wilayah Jawa Timur
$sqlAll = "
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

$resAll = $conn->query($sqlAll);
$matchedCustomers = [];
$statsByCity = [];
$holderStats = [];
$sudahDiNatalia = 0;
$belumDiNatalia = 0;

if ($resAll) {
    while ($row = $resAll->fetch_assoc()) {
        $cid = (int)$row['id'];
        if (isset($matchedCustomers[$cid])) continue;

        $haystack = strtolower(($row['kota'] ?? '') . ' ' . ($row['alamat'] ?? '') . ' ' . ($row['provinsi'] ?? ''));

        // Cek apakah masuk wilayah Jatim atau pernah di-FU Natalia
        $isJatim = false;
        $matchedCity = null;
        foreach ($jatimKeywords as $kw) {
            if (strpos($haystack, $kw) !== false) {
                $isJatim = true;
                $matchedCity = ucfirst($kw);
                break;
            }
        }

        if ($isJatim) {
            $row['matched_city'] = $matchedCity;
            $matchedCustomers[$cid] = $row;
            
            $cityDisplay = !empty($row['kota']) ? trim($row['kota']) : $matchedCity;
            $statsByCity[$cityDisplay] = ($statsByCity[$cityDisplay] ?? 0) + 1;

            if ((int)$row['sales_id'] === $nataliaId) {
                $sudahDiNatalia++;
            } else {
                $belumDiNatalia++;
                $holder = $row['current_sales'] ?? 'Belum Ada Sales';
                $holderStats[$holder] = ($holderStats[$holder] ?? 0) + 1;
            }
        }
    }
}

$totalJatim = count($matchedCustomers);

echo "4. ANALISIS TOTAL POTENSI TOKO WILAYAH JAWA TIMUR (NATALIA):\n";
echo "-----------------------------------------------------------------\n";
echo "TOTAL TOKO TERDETEKSI DI JAWA TIMUR : " . number_format($totalJatim) . " Toko\n";
echo "  ✓ Sudah di akun Natalia           : " . number_format($sudahDiNatalia) . " toko\n";
echo "  ⚠️ Masih tertahan di sales lain   : " . number_format($belumDiNatalia) . " toko\n\n";

if (!empty($holderStats)) {
    echo "Rincian Toko Jatim yang Tertahan di Sales Lain:\n";
    arsort($holderStats);
    foreach ($holderStats as $holder => $c) {
        echo "  - Dipegang {$holder}: {$c} toko\n";
    }
    echo "\n";
}

echo "Rincian Kota/Kabupaten Jawa Timur Terbanyak:\n";
arsort($statsByCity);
$top10 = array_slice($statsByCity, 0, 15, true);
foreach ($top10 as $city => $c) {
    echo "  • {$city}: {$c} toko\n";
}
echo "-----------------------------------------------------------------\n\n";

// 7. Eksekusi Pengembalian jika parameter --apply dipasang
$isApply = in_array('--apply', $argv ?? []);

if ($isApply) {
    echo "EKSEKUSI PEMULIHAN SELURUH TOKO JAWA TIMUR KE NATALIA CHRISTI...\n";
    $conn->begin_transaction();

    $stmtUp = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");
    $applied = 0;

    foreach ($matchedCustomers as $cid => $data) {
        if ((int)$data['sales_id'] !== $nataliaId) {
            $stmtUp->bind_param("ii", $nataliaId, $cid);
            $stmtUp->execute();
            if ($conn->affected_rows > 0) $applied++;
        }
    }

    $stmtUp->close();
    $conn->commit();

    echo "\n=================================================================\n";
    echo "✅ SUKSES BESAR! Sebanyak {$applied} toko tambahan berhasil dikembalikan ke Natalia Christi!\n";
    echo "Kini Natalia memegang total " . number_format($totalJatim) . " TOKO di wilayah Jawa Timur.\n";
    echo "Silakan refresh halaman CRM index.php filter Natalia Christi.\n";
    echo "=================================================================\n";
} else {
    echo "=================================================================\n";
    echo "💡 UNTUK MENERAPKAN PERUBAHAN:\n";
    echo "Jalankan perintah ini di terminal server:\n";
    echo "  php restore_natalia_customers.php --apply\n";
    echo "=================================================================\n";
}
