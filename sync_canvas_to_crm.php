<?php
/**
 * sync_canvas_to_crm.php
 * Menyinkronkan semua customer dari modul Canvas (teknisi_api_root -> sales_customer)
 * ke database CRM/Leads (u836263092_sales -> customers, customer_addresses, customer_pics)
 * Termasuk customer seperti WAGYU A5 yang baru saja diinput.
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   SINKRONISASI SALES CANVAS CUSTOMER -> CRM & LEADS (SO)       \n";
echo "=================================================================\n\n";

// 1. Koneksi ke Database CRM
require_once __DIR__ . '/includes/db.php';
$dbCRM = $conn;
if (!$dbCRM || $dbCRM->connect_error) {
    die("Error: Koneksi DB CRM gagal!\n");
}

// 2. Koneksi ke Database Canvas
require_once __DIR__ . '/modul-aplikasi-sales/conn.php';
$dbCanvas = $conn;
if (!$dbCanvas || $dbCanvas->connect_error) {
    die("Error: Koneksi DB Canvas gagal!\n");
}

echo "1. Membaca customer dari database Canvas (teknisi_api_root.sales_customer)...\n";
$qCanvas = $dbCanvas->query("SELECT id, kode_customer, nama, kategori, telp_pribadi, email, alamat, kota, lat, lon FROM sales_customer WHERE deleted_at IS NULL");
if (!$qCanvas) {
    die("Error query Canvas: " . $dbCanvas->error . "\n");
}

$stC = $dbCRM->prepare("INSERT INTO `customers` (sales_id, tgl_input, nama_toko, kategori, deal, kandidat, potensial, acc_boss) VALUES (1, NOW(), ?, ?, 'DEAL', 'Y', 'Y', 'Y')");
$stA = $dbCRM->prepare("INSERT INTO `customer_addresses` (customer_id, alamat, kota, provinsi, link_google_map) VALUES (?, ?, ?, ?, ?)");
$stP = $dbCRM->prepare("INSERT INTO `customer_pics` (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");

$inserted = 0;
$skipped = 0;

while ($row = $qCanvas->fetch_assoc()) {
    $nama = trim($row['nama']);
    if (empty($nama)) continue;

    // Cek apakah toko sudah ada di CRM
    $chk = $dbCRM->query("SELECT id FROM `customers` WHERE `nama_toko` = '" . $dbCRM->real_escape_string($nama) . "' LIMIT 1");
    if ($chk && $chk->num_rows > 0) {
        $skipped++;
        continue;
    }

    $kategori = strtoupper($row['kategori'] ?? 'DEALER');
    $stC->bind_param("ss", $nama, $kategori);
    if ($stC->execute()) {
        $newId = $dbCRM->insert_id;
        $alamat = $row['alamat'] ?? '';
        $kota = $row['kota'] ?? '';
        $prov = '';
        $mapLink = "https://maps.google.com/?q=" . ($row['lat'] ?? '-6.1754') . "," . ($row['lon'] ?? '106.8272');
        $stA->bind_param("issss", $newId, $alamat, $kota, $prov, $mapLink);
        $stA->execute();

        $pic = $nama;
        $telp = $row['telp_pribadi'] ?? '';
        $stP->bind_param("iss", $newId, $pic, $telp);
        $stP->execute();

        $inserted++;
        echo "   + Berhasil menyinkronkan ke CRM: [{$row['kode_customer']}] {$nama}\n";
    }
}

$stC->close();
$stA->close();
$stP->close();

echo "\n=================================================================\n";
echo "✓ Total customer baru disinkronkan ke CRM : {$inserted} toko\n";
echo "✓ Total sudah ada di CRM (dilewati)        : {$skipped} toko\n";
echo "=================================================================\n";
echo "✅ SELESAI! Customer seperti WAGYU A5 kini sudah aktif di semua form & pencarian SO.\n";
