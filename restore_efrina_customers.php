<?php
/**
 * restore_efrina_customers.php
 * Script untuk menganalisis dan mengembalikan customer milik Efrina Panjaitan (Rina)
 * sesuai wilayah penugasan aslinya: JAWA TENGAH & D.I. YOGYAKARTA.
 *
 * Cara menjalankan di terminal server:
 *   cd /www/wwwroot/sales.id-giti.com
 *   php restore_efrina_customers.php
 *   php restore_efrina_customers.php --apply
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   ANALISIS & PEMULIHAN DATA CUSTOMER EFRINA PANJAITAN (RINA)    \n";
echo "=================================================================\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Error: Koneksi database gagal.\n");
}

// 1. Dapatkan ID Sales Efrina Panjaitan
$qSales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE (nama_lengkap LIKE '%Efrina%' OR nama_lengkap LIKE '%Rina%') AND deleted_at IS NULL LIMIT 1");
$efrina = $qSales ? $qSales->fetch_assoc() : null;

if (!$efrina) {
    die("Error: Sales dengan nama 'Efrina' tidak ditemukan di database.\n");
}

$efrinaId = (int)$efrina['id'];
$efrinaName = $efrina['nama_lengkap'];

echo "Sales Target: {$efrinaName} (ID: {$efrinaId})\n\n";

// 2. Analisis Toko yang saat ini dipegang Efrina
$qCurrent = $conn->query("SELECT COUNT(*) as cnt FROM customers WHERE sales_id = {$efrinaId} AND deleted_at IS NULL");
$cntCurrent = $qCurrent ? (int)$qCurrent->fetch_assoc()['cnt'] : 0;
echo "1. Toko yang saat ini tercatat di akun Efrina: {$cntCurrent} Toko\n";

// 3. Analisis Riwayat Follow-Up oleh Efrina
$qFu = $conn->query("
    SELECT 
        COUNT(DISTINCT customer_id) as cust_count,
        COUNT(*) as fu_count
    FROM follow_ups 
    WHERE sales_id = {$efrinaId} AND deleted_at IS NULL
");
$fuData = $qFu ? $qFu->fetch_assoc() : ['cust_count' => 0, 'fu_count' => 0];
echo "2. Riwayat Follow Up oleh Efrina:\n";
echo "   - Jumlah Customer Pernah Dihubungi : {$fuData['cust_count']} Customer\n";
echo "   - Total Riwayat Aktivitas FU       : {$fuData['fu_count']} kali\n\n";

// 4. Analisis Kota & Wilayah yang Dipegang / Di-FU Efrina Saat Ini
$qSampleCities = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tidak Diketahui') as kota_clean,
        COUNT(DISTINCT c.id) as cnt
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE (c.sales_id = {$efrinaId} OR c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = {$efrinaId} AND deleted_at IS NULL))
      AND c.deleted_at IS NULL AND ca.deleted_at IS NULL
    GROUP BY kota_clean
    ORDER BY cnt DESC
    LIMIT 20
");

echo "3. Kota/Daerah Dominan Milik Efrina Saat Ini:\n";
if ($qSampleCities && $qSampleCities->num_rows > 0) {
    while ($rc = $qSampleCities->fetch_assoc()) {
        echo "   • {$rc['kota_clean']}: {$rc['cnt']} toko\n";
    }
} else {
    echo "   (Belum ada data)\n";
}
echo "\n";

// 5. Logika Akurat Deteksi Wilayah Jawa Tengah & D.I. Yogyakarta
function is_true_jateng_diy($kota, $alamat, $provinsi) {
    $text = strtolower(($kota ?? '') . ' ' . ($alamat ?? '') . ' ' . ($provinsi ?? ''));
    
    // Jangan sampai tercampur dengan Solok (Sumbar)
    if (strpos($text, 'solok') !== false && strpos($text, 'solo') !== false) {
        $text = str_replace('solok', '', $text);
    }

    // Jangan sampai tercampur dengan Jawa Timur
    if (strpos($text, 'jawa timur') !== false || strpos($text, 'jatim') !== false) {
        return false;
    }

    $jatengKeywords = [
        'semarang', 'surakarta', 'yogyakarta', 'jogja', 'jogjakarta', 'sleman', 'bantul', 
        'kulon progo', 'gunungkidul', 'gunung kidul',
        'kudus', 'pati', 'jepara', 'rembang', 'blora', 'demak', 'grobogan', 'purwodadi',
        'magelang', 'salatiga', 'klaten', 'boyolali', 'sukoharjo', 'karanganyar', 'wonogiri', 'sragen',
        'purwokerto', 'banyumas', 'cilacap', 'purbalingga', 'banjarnegara', 'kebumen', 'purworejo', 
        'wonosobo', 'temanggung',
        'tegal', 'pekalongan', 'brebes', 'pemalang', 'batang', 'kendal',
        'jawa tengah', 'jateng', 'd.i. yogyakarta', 'diy'
    ];

    foreach ($jatengKeywords as $kw) {
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text)) {
            return ucfirst($kw);
        }
    }

    // Cek kata 'solo' secara tegas batas kata
    if (preg_match('/\bsolo\b/i', $text)) {
        return 'Solo';
    }

    return false;
}

// 6. Scan Seluruh Database untuk Wilayah Jawa Tengah & DIY
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
$sudahDiEfrina = 0;
$belumDiEfrina = 0;

if ($resAll) {
    while ($row = $resAll->fetch_assoc()) {
        $cid = (int)$row['id'];
        if (isset($matchedCustomers[$cid])) continue;

        $matchedRegion = is_true_jateng_diy($row['kota'], $row['alamat'], $row['provinsi']);

        if ($matchedRegion) {
            $row['matched_city'] = $matchedRegion;
            $matchedCustomers[$cid] = $row;
            
            $cityDisplay = !empty($row['kota']) && stripos($row['kota'], 'JAKARTA') === false ? trim($row['kota']) : $matchedRegion;
            $statsByCity[$cityDisplay] = ($statsByCity[$cityDisplay] ?? 0) + 1;

            if ((int)$row['sales_id'] === $efrinaId) {
                $sudahDiEfrina++;
            } else {
                $belumDiEfrina++;
                $holder = $row['current_sales'] ?? 'Belum Ada Sales';
                $holderStats[$holder] = ($holderStats[$holder] ?? 0) + 1;
            }
        }
    }
}

$totalJateng = count($matchedCustomers);

echo "4. ANALISIS TOTAL POTENSI TOKO WILAYAH JAWA TENGAH & DIY (EFRINA):\n";
echo "-----------------------------------------------------------------\n";
echo "TOTAL TOKO TERDETEKSI DI JATENG & DIY : " . number_format($totalJateng) . " Toko\n";
echo "  ✓ Sudah di akun Efrina              : " . number_format($sudahDiEfrina) . " toko\n";
echo "  ⚠️ Masih tertahan di sales lain      : " . number_format($belumDiEfrina) . " toko\n\n";

if (!empty($holderStats)) {
    echo "Rincian Toko Jateng & DIY yang Tertahan di Sales Lain:\n";
    arsort($holderStats);
    foreach ($holderStats as $holder => $c) {
        echo "  - Dipegang {$holder}: {$c} toko\n";
    }
    echo "\n";
}

echo "Rincian Kota/Kabupaten Jawa Tengah & DIY Terbanyak:\n";
arsort($statsByCity);
$top15 = array_slice($statsByCity, 0, 15, true);
foreach ($top15 as $city => $c) {
    echo "  • {$city}: {$c} toko\n";
}
echo "-----------------------------------------------------------------\n\n";

// 7. Eksekusi Pengembalian jika parameter --apply dipasang
$isApply = in_array('--apply', $argv ?? []);

if ($isApply) {
    echo "EKSEKUSI PEMULIHAN SELURUH TOKO JAWA TENGAH & DIY KE EFRINA PANJAITAN...\n";
    $conn->begin_transaction();

    $stmtUp = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");
    $applied = 0;

    foreach ($matchedCustomers as $cid => $data) {
        if ((int)$data['sales_id'] !== $efrinaId) {
            $stmtUp->bind_param("ii", $efrinaId, $cid);
            $stmtUp->execute();
            if ($conn->affected_rows > 0) $applied++;
        }
    }

    $stmtUp->close();
    $conn->commit();

    echo "\n=================================================================\n";
    echo "✅ SUKSES BESAR! Sebanyak {$applied} toko tambahan berhasil dikembalikan ke Efrina Panjaitan!\n";
    echo "Kini Efrina memegang total " . number_format($totalJateng) . " TOKO di wilayah Jawa Tengah & DIY.\n";
    echo "Silakan refresh halaman CRM index.php filter Efrina Panjaitan.\n";
    echo "=================================================================\n";
} else {
    echo "=================================================================\n";
    echo "💡 UNTUK MENERAPKAN PERUBAHAN:\n";
    echo "Jalankan perintah ini di terminal server:\n";
    echo "  php restore_efrina_customers.php --apply\n";
    echo "=================================================================\n";
}
