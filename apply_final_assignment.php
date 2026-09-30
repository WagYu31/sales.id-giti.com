<?php
/**
 * apply_final_assignment.php
 * Menerapkan penugasan final:
 * - Natalia Christi (ID: 20) : Jawa Timur + Bali + Kalimantan + Sulawesi (~570 toko)
 * - Priska (ID: 21)          : Aceh + Sumbar + NTT + Kepulauan Riau (~520 toko)
 * - Efrina Panjaitan (ID: 22): Solo Raya + Semarang + DIY + Kudus/Pati (~600 toko)
 * - Edi Suprianto (ID: 3)    : Jabodetabek + Pantura Barat Jateng (ribuan toko)
 * - Excel (ID: 4)            : Jawa Barat / Banten (ribuan toko)
 *
 * Cara menjalankan:
 *   cd /www/wwwroot/sales.id-giti.com
 *   php apply_final_assignment.php
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PENERAPAN PENUGASAN FINAL 3 SALES (TARGET 500 - 600 TOKO)     \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$nataliaId = 20;

// Daftar Wilayah yang Ditambahkan ke Natalia Christi:
// 1. Bali
$baliKws = ['denpasar', 'badung', 'gianyar', 'tabanan', 'buleleng', 'singaraja', 'klungkung', 'bangli', 'karangasem', 'jembrana', 'kuta', 'sanur', 'ubud', 'bali'];

// 2. Kalimantan
$kalimantanKws = ['balikpapan', 'samarinda', 'pontianak', 'banjarmasin', 'banjarbaru', 'palangkaraya', 'tarakan', 'singkawang', 'kalimantan', 'kaltim', 'kalbar', 'kalsel', 'kalteng', 'kaltara'];

// 3. Sulawesi
$sulawesiKws = ['makassar', 'manado', 'palu', 'kendari', 'gorontalo', 'mamuju', 'bitung', 'kotamobagu', 'bau-bau', 'sulawesi', 'sulsel', 'sulteng', 'sulut', 'sultra', 'sulbar'];

$allNataliaExtra = array_merge($baliKws, $kalimantanKws, $sulawesiKws);

// Scan customer yang berada di wilayah tersebut
$sql = "
    SELECT c.id, c.sales_id, ca.kota, ca.alamat, ca.provinsi
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.deleted_at IS NULL AND ca.deleted_at IS NULL
";
$res = $conn->query($sql);

$nataliaTargetIds = [];
$statsCategory = ['Bali' => 0, 'Kalimantan' => 0, 'Sulawesi' => 0];

while ($row = $res->fetch_assoc()) {
    $cid = (int)$row['id'];
    $text = strtolower(($row['kota'] ?? '') . ' ' . ($row['alamat'] ?? '') . ' ' . ($row['provinsi'] ?? ''));

    // Cek Bali
    $isBali = false;
    foreach ($baliKws as $kw) {
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text)) {
            $isBali = true;
            break;
        }
    }
    if ($isBali) {
        $nataliaTargetIds[] = $cid;
        $statsCategory['Bali']++;
        continue;
    }

    // Cek Kalimantan
    $isKal = false;
    foreach ($kalimantanKws as $kw) {
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text)) {
            $isKal = true;
            break;
        }
    }
    if ($isKal) {
        $nataliaTargetIds[] = $cid;
        $statsCategory['Kalimantan']++;
        continue;
    }

    // Cek Sulawesi
    $isSul = false;
    foreach ($sulawesiKws as $kw) {
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text)) {
            $isSul = true;
            break;
        }
    }
    if ($isSul) {
        $nataliaTargetIds[] = $cid;
        $statsCategory['Sulawesi']++;
        continue;
    }
}

$nataliaTargetIds = array_unique($nataliaTargetIds);

echo "Rincian Wilayah yang Dialihkan ke Natalia Christi:\n";
echo "  • Bali       : {$statsCategory['Bali']} toko\n";
echo "  • Kalimantan : {$statsCategory['Kalimantan']} toko\n";
echo "  • Sulawesi   : {$statsCategory['Sulawesi']} toko\n";
echo "Total Tambahan untuk Natalia: " . count($nataliaTargetIds) . " toko\n\n";

echo "MENGUPDATE DATABASE...\n";
$conn->begin_transaction();

$stmt = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");
$affected = 0;
foreach ($nataliaTargetIds as $cid) {
    $stmt->bind_param("ii", $nataliaId, $cid);
    $stmt->execute();
    if ($conn->affected_rows > 0) $affected++;
}
$stmt->close();
$conn->commit();

echo "✅ Berhasil memindahkan {$affected} toko ke akun Natalia Christi!\n\n";

// Tampilkan Tabel Rekapitulasi Final Seluruh Sales
echo "==========================================================================================\n";
echo "   REKAPITULASI STATUS FINAL SELURUH TIM SALES (SEKARANG)                                \n";
echo "==========================================================================================\n";

$sqlOverview = "
    SELECT 
        s.id, 
        s.nama_lengkap,
        COUNT(c.id) as total_customers,
        SUM(CASE WHEN c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = s.id AND deleted_at IS NULL) THEN 1 ELSE 0 END) as sudah_fu,
        SUM(CASE WHEN c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = s.id AND deleted_at IS NULL) THEN 1 ELSE 0 END) as belum_fu,
        SUM(CASE WHEN c.kandidat = 'Y' THEN 1 ELSE 0 END) as total_kandidat,
        SUM(CASE WHEN c.deal = 'Y' THEN 1 ELSE 0 END) as total_deal
    FROM sales s
    LEFT JOIN customers c ON s.id = c.sales_id AND c.deleted_at IS NULL
    WHERE s.role = 'sales' AND s.deleted_at IS NULL
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_customers DESC
";
$resOverview = $conn->query($sqlOverview);

printf("%-5s | %-28s | %-8s | %-8s | %-8s | %-8s | %-8s\n", "ID", "NAMA SALES", "TOTAL", "SUDAH FU", "BELUM FU", "KANDIDAT", "DEAL");
echo str_repeat("-", 90) . "\n";

while ($r = $resOverview->fetch_assoc()) {
    printf(
        "%-5d | %-28s | %-8s | %-8s | %-8s | %-8s | %-8s\n",
        $r['id'],
        substr($r['nama_lengkap'], 0, 28),
        number_format($r['total_customers']),
        number_format($r['sudah_fu']),
        number_format($r['belum_fu']),
        number_format($r['total_kandidat']),
        number_format($r['total_deal'])
    );
}
echo str_repeat("=", 90) . "\n";
echo "Total Seluruh Customer: " . number_format($conn->query("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL")->fetch_row()[0]) . " Toko\n";
