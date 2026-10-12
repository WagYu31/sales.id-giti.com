<?php
/**
 * reassign_edi_stores.php
 * Script otomatis untuk mengalihkan toko Budi Luhur Abadi & Rajawali CCTV Larangan ke akun Sales Edi Suprianto (ID: 3)
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PENGALIHAN TOKO KE AKUN SALES: EDI SUPRIANTO (ID: 3)         \n";
echo "=================================================================\n\n";

// 1. Cari ID Edi Suprianto
$ediRes = $conn->query("SELECT id, nama_lengkap, email FROM sales WHERE id = 3 OR LOWER(nama_lengkap) LIKE '%edi suprianto%' LIMIT 1");
if (!$ediRes || $ediRes->num_rows === 0) {
    die("Error: Akun Edi Suprianto tidak ditemukan di database!\n");
}
$edi = $ediRes->fetch_assoc();
$ediId = (int)$edi['id'];
echo "Target Sales: {$edi['nama_lengkap']} (ID: {$ediId}, Email: {$edi['email']})\n\n";

// 2. Cari toko yang berkaitan dengan Budi Luhur dan Rajawali CCTV
$queries = [
    "BUDI LUHUR" => "SELECT c.id, c.nama_toko, c.sales_id, s.nama_lengkap as sales_lama FROM customers c LEFT JOIN sales s ON c.sales_id = s.id WHERE (c.nama_toko LIKE '%BUDI LUHUR%' OR c.id IN (SELECT customer_id FROM customer_addresses WHERE alamat LIKE '%budi luhur%')) AND c.deleted_at IS NULL",
    "RAJAWALI CCTV" => "SELECT c.id, c.nama_toko, c.sales_id, s.nama_lengkap as sales_lama FROM customers c LEFT JOIN sales s ON c.sales_id = s.id WHERE (c.nama_toko LIKE '%RAJAWALI%' OR c.id IN (SELECT customer_id FROM customer_addresses WHERE alamat LIKE '%Larangan%' AND alamat LIKE '%Rajawali%')) AND c.deleted_at IS NULL"
];

$matchedIds = [];
foreach ($queries as $label => $sql) {
    echo "Mencari toko: $label...\n";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            echo "  ✓ Ditemukan: [ID: #{$r['id']}] {$r['nama_toko']} (Sales saat ini: " . ($r['sales_lama'] ?: 'Belum ada') . ")\n";
            $matchedIds[] = (int)$r['id'];
        }
    } else {
        echo "  - Tidak ada data toko '$label' yang perlu dipindahkan.\n";
    }
}

$matchedIds = array_unique($matchedIds);

if (empty($matchedIds)) {
    echo "\nTidak ada toko yang perlu dialihkan.\n";
    exit();
}

echo "\nMengalihkan " . count($matchedIds) . " toko ke {$edi['nama_lengkap']}...\n";
$stmt = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");
$count = 0;
foreach ($matchedIds as $cid) {
    $stmt->bind_param("ii", $ediId, $cid);
    if ($stmt->execute()) {
        $count++;
    }
}
$stmt->close();

echo "✅ Berhasil memindahkan {$count} toko ke akun {$edi['nama_lengkap']}!\n";
echo "Sekarang sales Edi Suprianto sudah bisa melihat toko-toko ini di akunnya.\n";
