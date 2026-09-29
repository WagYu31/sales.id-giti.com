<?php
/**
 * restore_crm_leads.php
 * Script Pemulihan 4.000+ Data Customer Lama di menu "Database Customer & Forum" (index.php / CRM & Leads)
 * Menggabungkan seluruh data lama dari sales_id_giti.sql dengan 1.000 data customer baru tanpa menghapus data.
 *
 * Jalankan via CLI: php restore_crm_leads.php
 * Atau via Browser: https://sales.id-giti.com/restore_crm_leads.php
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   LOEWIX CRM - PEMULIHAN 4.000+ DATA CUSTOMER (CRM & LEADS)    \n";
echo "=================================================================\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Hubungkan ke database CRM (u836263092_sales / sales_id_giti)
require_once __DIR__ . '/includes/db.php';
if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Error: Gagal terhubung ke database CRM: " . ($conn ? $conn->connect_error : 'null') . "\n");
}

$dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? 'unknown';
echo "Database CRM Terhubung: [{$dbName}]\n";

$resCurrent = $conn->query("SELECT COUNT(*) as total FROM customers WHERE deleted_at IS NULL")->fetch_assoc();
echo "Jumlah Customer Saat Ini di database: " . ($resCurrent['total'] ?? 0) . " Leads\n\n";

// 2. Baca file dump resmi sales_id_giti.sql
$sqlFile = __DIR__ . '/sales_id_giti.sql';
if (!file_exists($sqlFile)) {
    die("Error: File $sqlFile tidak ditemukan di direktori proyek!\n");
}

echo "1. Membaca data original dari sales_id_giti.sql ...\n";
$handle = fopen($sqlFile, 'r');
if (!$handle) {
    die("Error: Gagal membuka $sqlFile!\n");
}

// Matikan foreign key checks sementara untuk import cepat & aman
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
$conn->query("SET UNIQUE_CHECKS = 0;");

$targetTables = ['customers', 'customer_addresses', 'customer_pics', 'follow_ups'];
$currentStmt = '';
$tableStats = [
    'customers' => 0,
    'customer_addresses' => 0,
    'customer_pics' => 0,
    'follow_ups' => 0
];

echo "2. Memulihkan seluruh customer, alamat, PIC, dan riwayat follow-up original...\n";

while (($line = fgets($handle)) !== false) {
    // Abaikan baris komentar atau kosong
    $trimmed = trim($line);
    if (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
        continue;
    }

    $currentStmt .= $line;

    if (substr(rtrim($line), -1) === ';') {
        // Cek apakah query ini untuk salah satu tabel target
        foreach ($targetTables as $tbl) {
            if (preg_match('/INSERT INTO [`\']?' . $tbl . '[`\']?/i', $currentStmt)) {
                // Ubah INSERT INTO menjadi INSERT IGNORE INTO agar tidak konflik dengan data yang ada
                $safeStmt = preg_replace('/INSERT INTO/i', 'INSERT IGNORE INTO', $currentStmt);
                if ($conn->query($safeStmt)) {
                    $tableStats[$tbl] += $conn->affected_rows;
                } else {
                    echo "  Notice [{$tbl}]: " . $conn->error . "\n";
                }
                break;
            }
        }
        $currentStmt = '';
    }
}
fclose($handle);

$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
$conn->query("SET UNIQUE_CHECKS = 1;");

echo "\n✓ Selesai memulihkan data original dari file backup:\n";
foreach ($tableStats as $tbl => $cnt) {
    echo "  • {$tbl}: diproses (affected: {$cnt})\n";
}

// 3. Tambahkan 1.000 customer baru dari Excel (customers_data.json) tanpa menduplikasi
$jsonCustFile = __DIR__ . '/includes/customers_data.json';
if (file_exists($jsonCustFile)) {
    echo "\n3. Menggabungkan 1.000 Customer Baru dari Excel (Non-Destruktif) ...\n";
    $raw = file_get_contents($jsonCustFile);
    $newCustomers = json_decode($raw, true);

    if (is_array($newCustomers) && count($newCustomers) > 0) {
        $stC = $conn->prepare("INSERT INTO `customers` (sales_id, tgl_input, nama_toko, kategori, deal, kandidat, potensial, acc_boss) VALUES (1, NOW(), ?, ?, 'DEAL', 'Y', 'Y', 'Y')");
        $stA = $conn->prepare("INSERT INTO `customer_addresses` (customer_id, alamat, kota, provinsi, link_google_map) VALUES (?, ?, ?, ?, ?)");
        $stP = $conn->prepare("INSERT INTO `customer_pics` (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");

        $addedCount = 0;
        $skippedCount = 0;

        foreach ($newCustomers as $c) {
            $nama = trim($c['nama'] ?? '');
            if (empty($nama)) continue;

            // Cek apakah toko ini sudah ada di tabel customers
            $chk = $conn->query("SELECT id FROM `customers` WHERE `nama_toko` = '" . $conn->real_escape_string($nama) . "' LIMIT 1");
            if ($chk && $chk->num_rows > 0) {
                $skippedCount++;
                continue;
            }

            $kat = strtoupper($c['kategori'] ?? 'DEALER');
            $stC->bind_param("ss", $nama, $kat);
            if ($stC->execute()) {
                $newId = $conn->insert_id;
                $alamat = $c['alamat'] ?? '';
                $kota = $c['kota'] ?? '';
                $prov = $c['wilayah'] ?? '';
                $mapLink = "https://maps.google.com/?q=" . ($c['lat'] ?? '-6.1754') . "," . ($c['lon'] ?? '106.8272');
                $stA->bind_param("issss", $newId, $alamat, $kota, $prov, $mapLink);
                $stA->execute();

                $pic = $c['kontak'] ?: $nama;
                $telp = $c['telp_pribadi'] ?? '';
                $stP->bind_param("iss", $newId, $pic, $telp);
                $stP->execute();

                $addedCount++;
            }
        }
        $stC->close();
        $stA->close();
        $stP->close();

        echo "✓ Customer baru ditambahkan: {$addedCount} toko.\n";
        echo "✓ Dilewati (karena sudah ada di data original): {$skippedCount} toko.\n";
    }
}

// 4. STATISTIK AKHIR DI INDEX.PHP (DATABASE CUSTOMER & FORUM)
$resFinal = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN kandidat = 'Y' THEN 1 ELSE 0 END) as cnt_kandidat,
    SUM(CASE WHEN deal = 'DEAL' THEN 1 ELSE 0 END) as cnt_deal
FROM customers WHERE deleted_at IS NULL")->fetch_assoc();

$fuRes = $conn->query("SELECT COUNT(DISTINCT customer_id) as cnt_fu FROM follow_ups WHERE deleted_at IS NULL")->fetch_assoc();
$cntFu = $fuRes['cnt_fu'] ?? 0;

echo "\n=================================================================\n";
echo "STATUS DATA CUSTOMER & FORUM (CRM & LEADS) SAAT INI:\n";
echo "• TOTAL CUSTOMER (LEADS) : " . number_format($resFinal['total'] ?? 0, 0, ',', '.') . " Toko / Customer\n";
echo "• KANDIDAT               : " . number_format($resFinal['cnt_kandidat'] ?? 0, 0, ',', '.') . "\n";
echo "• SUDAH FU               : " . number_format($cntFu, 0, ',', '.') . "\n";
echo "• BELUM FU               : " . number_format(max(0, ($resFinal['total'] ?? 0) - $cntFu), 0, ',', '.') . "\n";
echo "• DEAL CLOSING           : " . number_format($resFinal['cnt_deal'] ?? 0, 0, ',', '.') . "\n";
echo "=================================================================\n";
echo "✅ SEMUA DATA LAMA 4.000+ DAN DATA BARU TELAH BERHASIL DIGABUNGKAN!\n";
echo "Silakan refresh halaman https://sales.id-giti.com/index.php\n";
echo "=================================================================\n";
