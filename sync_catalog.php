<?php
/**
 * sync_catalog.php
 * Script migrasi otomatis untuk menyinkronkan 515 data katalog produk resmi dari daftar-barang Devina.xlsx
 * Bisa dijalankan via browser: https://sales.id-giti.com/sync_catalog.php
 * atau via CLI: php sync_catalog.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/sales_order_helper.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== LOEWIX SALES CATALOG SYNC ===\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n\n";

if (!$conn || $conn->connect_error) {
    die("Koneksi Database Gagal: " . ($conn ? $conn->connect_error : 'No connection'));
}

echo "1. Menyiapkan struktur tabel product_prices...\n";
ensureSalesOrderTables($conn);

$jsonFile = __DIR__ . '/includes/catalog_products.json';
if (!file_exists($jsonFile)) {
    die("Error: File data katalog $jsonFile tidak ditemukan!\n");
}

$jsonStr = file_get_contents($jsonFile);
$items = json_decode($jsonStr, true);

if (!is_array($items) || count($items) === 0) {
    die("Error: File JSON kosong atau tidak valid!\n");
}

echo "2. Mengosongkan data lama & mengimpor 515 item katalog resmi...\n";
$conn->query("TRUNCATE TABLE `product_prices`");

$stmt = $conn->prepare("INSERT INTO `product_prices` (category, type, item_code, description, unit, msrp, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    die("Prepare statement gagal: " . $conn->error . "\n");
}

$inserted = 0;
foreach ($items as $it) {
    $category = $it['category'];
    $type = $it['type'];
    $item_code = $it['item_code'] ?? null;
    $description = $it['description'] ?? '';
    $unit = $it['unit'] ?? 'UNIT';
    $msrp = (float)($it['msrp'] ?? 0);
    
    $stmt->bind_param("sssssd", $category, $type, $item_code, $description, $unit, $msrp);
    if ($stmt->execute()) {
        $inserted++;
    } else {
        echo "Warning [Gagal insert]: {$type} ({$stmt->error})\n";
    }
}
$stmt->close();

echo "Berhasil mengimpor: {$inserted} produk!\n\n";

echo "3. Statistik Database Saat Ini:\n";
$res = $conn->query("SELECT COUNT(*) as total, COUNT(DISTINCT category) as cats, MIN(CASE WHEN msrp > 0 THEN msrp ELSE NULL END) as min_p, MAX(msrp) as max_p FROM product_prices")->fetch_assoc();
echo "• Total Produk: " . $res['total'] . " Item\n";
echo "• Total Kategori: " . $res['cats'] . " Kategori\n";
echo "• Rentang Harga: Rp " . number_format($res['min_p'], 0, ',', '.') . " - Rp " . number_format($res['max_p'], 0, ',', '.') . "\n\n";

echo "✅ Selesai! Katalog produk Loewix sekarang 100% mutakhir.\n";
