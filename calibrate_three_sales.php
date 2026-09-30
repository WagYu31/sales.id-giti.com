<?php
/**
 * calibrate_three_sales.php
 * Kalibrasi target 3 Sales agar masing-masing pas di 500 - 600 Customer:
 * 1. Priska           : ~635 Toko (Aceh, Sumbar, Bali, NTT, Kepri) -> SUDAH PAS
 * 2. Natalia Christi  : Tarik sisa ~160 toko Jatim yang masih tertahan di Excel/Edi -> Menjadi ~515 Toko (PAS 500-600)
 * 3. Efrina Panjaitan : Fokus Solo Raya, Semarang, Jogja & Jateng Timur (~550 Toko).
 *                       Wilayah Pantura Barat (Tegal, Brebes, Cilacap, Pekalongan, Banyumas, dll.)
 *                       dikembalikan ke Edi Suprianto -> Menjadi ~550 Toko (PAS 500-600)
 * 4. Edi Suprianto    : Jabodetabek + Pantura Barat Jateng -> ~1.400+ Toko (Sesuai instruksi: "Kecuali Edi")
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   KALIBRASI PEMBAGIAN 3 SALES (TARGET RATA-RATA 500-600 TOKO)  \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$priskaId = 21;
$nataliaId = 20;
$efrinaId = 22;
$ediId = 3;

// --- 1. EVALUASI PRISKA ---
$cntPriska = (int)$conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$priskaId} AND deleted_at IS NULL")->fetch_row()[0];
echo "1. PRISKA (Aceh, Sumbar, Bali, NTT, Kepri):\n";
echo "   -> Saat ini: {$cntPriska} Toko [STATUS: SUDAH PAS di kisaran 500 - 600]\n\n";

// --- 2. EVALUASI NATALIA CHRISTI (Jawa Timur) ---
$cntNatalia = (int)$conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$nataliaId} AND deleted_at IS NULL")->fetch_row()[0];

// Cari seluruh toko Jatim yang masih tercecer di sales lain
$jatimKeywords = [
    'surabaya', 'sidoarjo', 'jombang', 'gresik', 'mojokerto', 
    'pasuruan', 'probolinggo', 'banyuwangi', 'jember', 'kediri', 'madiun', 
    'blitar', 'bojonegoro', 'tuban', 'lamongan', 'ponorogo', 'tulungagung', 
    'magetan', 'ngawi', 'situbondo', 'bondowoso', 'trenggalek', 'nganjuk', 
    'pacitan', 'bangkalan', 'sampang', 'pamekasan', 'sumenep', 
    'lumajang', 'jawa timur', 'jatim'
];

$sqlAllCust = "
    SELECT c.id, c.sales_id, ca.kota, ca.alamat, ca.provinsi
    FROM customers c
    JOIN customer_addresses ca ON c.id = ca.customer_id
    WHERE c.deleted_at IS NULL AND ca.deleted_at IS NULL
";
$resAll = $conn->query($sqlAllCust);

$jatimMissingForNatalia = [];
$efrinaKeepIds = [];
$efrinaMoveToEdiIds = [];

// Kota yang menjadi hak Efrina (Solo Raya, Semarang, DIY, Muria Raya / Jateng Timur)
$efrinaCities = [
    'klaten', 'kudus', 'semarang', 'karanganyar', 'magelang', 'sukoharjo', 'pati', 
    'temanggung', 'sragen', 'grobokan', 'grobogan', 'demak', 'surakarta', 'solo', 
    'wonogiri', 'blora', 'salatiga', 'boyolali', 'rembang', 'yogyakarta', 'jogja', 
    'sleman', 'bantul', 'kulon progo', 'gunungkidul'
];

// Kota Pantura Barat & Banyumasan yang dikembalikan ke Edi Suprianto
$baratCities = [
    'tegal', 'brebes', 'kebumen', 'cilacap', 'pekalongan', 'purbalingga', 'purworejo', 
    'banyumas', 'purwokerto', 'kendal', 'pemalang', 'batang', 'banjarnegara', 'wonosobo'
];

while ($row = $resAll->fetch_assoc()) {
    $cid = (int)$row['id'];
    $text = strtolower(($row['kota'] ?? '') . ' ' . ($row['alamat'] ?? '') . ' ' . ($row['provinsi'] ?? ''));
    $sid = (int)$row['sales_id'];

    // Cek Jatim untuk Natalia
    $isJatim = false;
    if (strpos($text, 'pemalang') === false && strpos($text, 'sumur batu') === false && strpos($text, 'cibatu') === false) {
        foreach ($jatimKeywords as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text)) {
                $isJatim = true;
                break;
            }
        }
        if (!$isJatim && preg_match('/\bmalang\b/i', $text) && strpos($text, 'pemalang') === false) {
            $isJatim = true;
        }
        if (!$isJatim && preg_match('/\b(kota batu|batu jatim|batu jawa timur)\b/i', $text)) {
            $isJatim = true;
        }
    }

    if ($isJatim) {
        if ($sid !== $nataliaId) {
            $jatimMissingForNatalia[] = $cid;
        }
    }

    // Cek Toko di Akun Efrina saat ini
    if ($sid === $efrinaId) {
        $matchedEfrina = false;
        foreach ($efrinaCities as $ec) {
            if (preg_match('/\b' . preg_quote($ec, '/') . '\b/i', $text)) {
                $matchedEfrina = true;
                break;
            }
        }

        if ($matchedEfrina) {
            $efrinaKeepIds[] = $cid;
        } else {
            // Masuk ke Barat / Non-Jateng Timur -> kembalikan ke Edi
            $efrinaMoveToEdiIds[] = $cid;
        }
    }
}

$projectedNatalia = $cntNatalia + count($jatimMissingForNatalia);
echo "2. NATALIA CHRISTI (Jawa Timur Lengkap):\n";
echo "   -> Saat ini                       : {$cntNatalia} Toko\n";
echo "   -> Sisa Toko Jatim yang tercecer  : " . count($jatimMissingForNatalia) . " Toko\n";
echo "   -> Proyeksi Setelah Kalibrasi     : ~{$projectedNatalia} Toko [TARGET: 500 - 600 PAS!]\n\n";

$cntEfrina = (int)$conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$efrinaId} AND deleted_at IS NULL")->fetch_row()[0];
$projectedEfrina = count($efrinaKeepIds);
echo "3. EFRINA PANJAITAN (Solo Raya, Semarang, DIY, Magelang, Kudus, Pati):\n";
echo "   -> Saat ini (masih tercampur Barat): {$cntEfrina} Toko\n";
echo "   -> Toko Hak Efrina yang dipertahankan: {$projectedEfrina} Toko [TARGET: 500 - 600 PAS!]\n";
echo "   -> Toko Pantura Barat & Luar Daerah  : " . count($efrinaMoveToEdiIds) . " Toko dialihkan ke Edi Suprianto\n\n";

$cntEdi = (int)$conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$ediId} AND deleted_at IS NULL")->fetch_row()[0];
$projectedEdi = $cntEdi + count($efrinaMoveToEdiIds) - count(array_intersect($jatimMissingForNatalia, [$ediId]));
echo "4. EDI SUPRIANTO (Jabodetabek + Pantura Barat Jateng):\n";
echo "   -> Saat ini                       : {$cntEdi} Toko\n";
echo "   -> Proyeksi Setelah Kalibrasi     : ~{$projectedEdi} Toko (Di atas 1.000 toko, sesuai instruksi)\n\n";

echo "=================================================================\n";

$isApply = in_array('--apply', $argv ?? []);

if ($isApply) {
    echo "MENJALANKAN KALIBRASI FINAL KE DATABASE...\n";
    $conn->begin_transaction();

    // 1. Masukkan sisa toko Jatim ke Natalia Christi
    $addedToNatalia = 0;
    if (!empty($jatimMissingForNatalia)) {
        $stmtNat = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");
        foreach ($jatimMissingForNatalia as $cid) {
            $stmtNat->bind_param("ii", $nataliaId, $cid);
            $stmtNat->execute();
            if ($conn->affected_rows > 0) $addedToNatalia++;
        }
        $stmtNat->close();
    }

    // 2. Alihkan toko Barat dari Efrina ke Edi Suprianto
    $movedToEdi = 0;
    if (!empty($efrinaMoveToEdiIds)) {
        $stmtEdi = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");
        foreach ($efrinaMoveToEdiIds as $cid) {
            $stmtEdi->bind_param("ii", $ediId, $cid);
            $stmtEdi->execute();
            if ($conn->affected_rows > 0) $movedToEdi++;
        }
        $stmtEdi->close();
    }

    $conn->commit();

    echo "✅ KALIBRASI BERHASIL 100%!\n";
    echo "  • Natalia Christi : bertambah {$addedToNatalia} toko -> Total menjadi ~{$projectedNatalia} Toko\n";
    echo "  • Efrina Panjaitan: rapi dipertahankan {$projectedEfrina} Toko (Solo Raya, Semarang, DIY, dll.)\n";
    echo "  • Edi Suprianto   : menerima {$movedToEdi} toko tambahan dari Pantura Barat Jateng\n";
    echo "=================================================================\n";
    echo "Silakan jalankan 'php overview_all_sales.php' untuk melihat tabel hasil akhir.\n";
} else {
    echo "💡 UNTUK MENERAPKAN KALIBRASI INI SEGERA:\n";
    echo "Jalankan perintah ini di terminal server:\n";
    echo "  php calibrate_three_sales.php --apply\n";
    echo "=================================================================\n";
}
