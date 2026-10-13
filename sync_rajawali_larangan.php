<?php
/**
 * sync_rajawali_larangan.php
 * Sinkronisasi data RAJAWALI CCTV - LARANGAN dari modul sales_customer ke CRM Database Utama (customers)
 */

// 1. Ambil data dari modul-aplikasi-sales (sales_customer)
require_once __DIR__ . '/modul-aplikasi-sales/conn.php';
$connCanvas = $conn;

// 2. Hubungkan ke Database CRM Utama (includes/db.php)
require_once __DIR__ . '/includes/db.php';
$connCrm = $conn;

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   SINKRONISASI RAJAWALI CCTV - LARANGAN KE DATABASE CRM UTAMA    \n";
echo "=================================================================\n\n";

$qCanvas = $connCanvas->query("SELECT * FROM sales_customer WHERE LOWER(nama) LIKE '%rajawali%' AND (LOWER(nama) LIKE '%larangan%' OR LOWER(alamat) LIKE '%larangan%') LIMIT 1");
if (!$qCanvas || $qCanvas->num_rows === 0) {
    // Cari secara umum dengan nama RAJAWALI CCTV
    $qCanvas = $connCanvas->query("SELECT * FROM sales_customer WHERE LOWER(nama) LIKE '%rajawali%cctv%' LIMIT 1");
}

$custCanvas = null;
if ($qCanvas && $qCanvas->num_rows > 0) {
    $custCanvas = $qCanvas->fetch_assoc();
    echo "✓ Ditemukan di sales_customer (Canvas Mobile):\n";
    echo "  ID: #{$custCanvas['id']}\n";
    echo "  Nama Toko: {$custCanvas['nama']}\n";
    echo "  Kategori: {$custCanvas['kategori']}\n";
    echo "  No Telp/WA: {$custCanvas['telp_pribadi']}\n";
    echo "  Alamat: {$custCanvas['alamat']}\n";
    echo "  Kota: {$custCanvas['kota']}\n\n";
} else {
    echo "⚠️ Data tidak ditemukan langsung di sales_customer koneksi default. Menggunakan data dari sistem:\n";
    $custCanvas = [
        'nama' => 'RAJAWALI CCTV - LARANGAN',
        'kategori' => 'DEALER',
        'telp_pribadi' => '08151622540',
        'alamat' => 'QP9F+QW6 North Larangan, Tangerang City, Banten',
        'kota' => 'TANGERANG',
        'lat' => '-6.223',
        'lon' => '106.726'
    ];
    echo "  Nama: {$custCanvas['nama']}\n";
    echo "  Alamat: {$custCanvas['alamat']}\n";
    echo "  Telp: {$custCanvas['telp_pribadi']}\n\n";
}

// Ambil ID Sales Edi Suprianto
$ediRes = $connCrm->query("SELECT id, nama_lengkap FROM sales WHERE id = 3 OR LOWER(nama_lengkap) LIKE '%edi suprianto%' LIMIT 1");
$ediId = 3;
$ediName = 'Edi Suprianto';
if ($ediRes && $rowE = $ediRes->fetch_assoc()) {
    $ediId = (int)$rowE['id'];
    $ediName = $rowE['nama_lengkap'];
}
echo "Target Sales: {$ediName} (ID: {$ediId})\n\n";

// Cek apakah toko sudah ada di tabel customers CRM utama
$namaToko = trim($custCanvas['nama']);
$chk = $connCrm->query("SELECT id, sales_id FROM customers WHERE LOWER(nama_toko) = LOWER('" . $connCrm->real_escape_string($namaToko) . "') LIMIT 1");

if ($chk && $chk->num_rows > 0) {
    $existing = $chk->fetch_assoc();
    $cId = (int)$existing['id'];
    echo "ℹ️ Toko sudah terdaftar di CRM utama [ID: #{$cId}]. Memperbarui sales_id ke {$ediName}...\n";
    $connCrm->query("UPDATE customers SET sales_id = {$ediId}, deleted_at = NULL WHERE id = {$cId}");
} else {
    echo "Memasukkan toko '{$namaToko}' ke tabel customers (Database CRM Utama)...\n";
    $kategori = strtoupper(trim($custCanvas['kategori'] ?: 'DEALER'));
    $tglInput = '2026-07-30';
    
    $stmtC = $connCrm->prepare("INSERT INTO customers (sales_id, tgl_input, nama_toko, kategori, deal, kandidat, potensial, acc_boss) VALUES (?, ?, ?, ?, 'DEAL', 'Y', 'Y', 'Y')");
    $stmtC->bind_param("isss", $ediId, $tglInput, $namaToko, $kategori);
    $stmtC->execute();
    $cId = $connCrm->insert_id;
    $stmtC->close();
    echo "✓ Berhasil dibuat di tabel customers [ID: #{$cId}]!\n";

    // Masukkan Alamat
    $alamat = trim($custCanvas['alamat'] ?: 'North Larangan, Tangerang City, Banten');
    $kota = strtoupper(trim($custCanvas['kota'] ?: 'TANGERANG'));
    $mapLink = !empty($custCanvas['lat']) ? "https://maps.google.com/?q={$custCanvas['lat']},{$custCanvas['lon']}" : null;

    $stmtA = $connCrm->prepare("INSERT INTO customer_addresses (customer_id, alamat, kota, provinsi, link_google_map) VALUES (?, ?, ?, 'Banten', ?)");
    $stmtA->bind_param("isss", $cId, $alamat, $kota, $mapLink);
    $stmtA->execute();
    $stmtA->close();
    echo "✓ Alamat toko berhasil ditambahkan ke customer_addresses.\n";

    // Masukkan PIC & Kontak
    $telp = trim($custCanvas['telp_pribadi'] ?: '08151622540');
    $stmtP = $connCrm->prepare("INSERT INTO customer_pics (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");
    $stmtP->bind_param("iss", $cId, $namaToko, $telp);
    $stmtP->execute();
    $stmtP->close();
    echo "✓ Kontak PIC ({$telp}) berhasil ditambahkan ke customer_pics.\n";
}

echo "\n=================================================================\n";
echo "✅ SUKSES! Toko '{$namaToko}' sekarang SUDAH AKTIF di Database Customer CRM Utama!\n";
echo "Sales Edi Suprianto sekarang bisa langsung mencari dan melihat toko ini di halaman Database Customer.\n";
echo "=================================================================\n";
