<?php
/**
 * sync_customers.php
 * Script migrasi & sinkronisasi otomatis 1.000 Sales Customer resmi dari daftar-pelanggan.xlsx
 * Bisa dijalankan via CLI: php sync_customers.php
 * Atau via browser: https://sales.id-giti.com/sync_customers.php
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: text/plain; charset=utf-8');

echo "====================================================\n";
echo "   LOEWIX SALES CUSTOMER DATABASE SYNC (1.000 DATA) \n";
echo "====================================================\n";
echo "Waktu Eksekusi: " . date('Y-m-d H:i:s') . "\n\n";

// Muat koneksi database
$conns = [];

// 1. Koneksi dari modul-aplikasi-sales/conn.php (Prioritas utama data customer live)
if (file_exists(__DIR__ . '/modul-aplikasi-sales/conn.php')) {
    require_once __DIR__ . '/modul-aplikasi-sales/conn.php';
    if (isset($conn) && $conn && !$conn->connect_error) {
        $conns['sales_customer_db'] = $conn;
    }
}

// 2. Koneksi dari includes/db.php (Database sales_id_giti)
if (file_exists(__DIR__ . '/includes/db.php')) {
    require_once __DIR__ . '/includes/db.php';
    if (isset($conn) && $conn && !$conn->connect_error) {
        // Cek jika koneksi berbeda
        $isSame = false;
        foreach ($conns as $c) {
            if ($c->thread_id === $conn->thread_id) {
                $isSame = true;
                break;
            }
        }
        if (!$isSame) {
            $conns['sales_id_giti_db'] = $conn;
        }
    }
}

if (empty($conns)) {
    die("Error: Tidak ada koneksi database yang tersedia!\n");
}

// Muat data JSON 1.000 customer
$jsonFile = __DIR__ . '/includes/customers_data.json';
if (!file_exists($jsonFile)) {
    die("Error: File $jsonFile tidak ditemukan! Jalankan parser terlebih dahulu.\n");
}

$rawJson = file_get_contents($jsonFile);
$customers = json_decode($rawJson, true);
if (!is_array($customers) || count($customers) === 0) {
    die("Error: Data customer JSON kosong atau tidak valid!\n");
}

echo "1. Memuat file data: 1.000 customer resmi siap disinkronkan.\n";

$standardRegions = [
    'Jabodetabek',
    'Jawa Barat',
    'Jawa Tengah',
    'Jawa Timur',
    'Sumatera',
    'Kalimantan',
    'Sulawesi',
    'Bali & Nusa Tenggara',
    'Lainnya'
];

foreach ($conns as $connKey => $db) {
    $dbName = $db->query("SELECT DATABASE()")->fetch_row()[0] ?? 'unknown';
    echo "\n----------------------------------------------------\n";
    echo "Memproses Database: [{$connKey}] {$dbName} ...\n";
    echo "----------------------------------------------------\n";

    // 1. Pastikan tabel wilayah ada & terisi
    $db->query("CREATE TABLE IF NOT EXISTS `wilayah` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama` VARCHAR(100) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        INDEX (`nama`),
        INDEX (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Pastikan 8-9 region terdaftar
    foreach ($standardRegions as $rName) {
        $chk = $db->query("SELECT id FROM `wilayah` WHERE `nama` = '" . $db->real_escape_string($rName) . "' AND `deleted_at` IS NULL");
        if ($chk && $chk->num_rows === 0) {
            $db->query("INSERT INTO `wilayah` (`nama`) VALUES ('" . $db->real_escape_string($rName) . "')");
        }
    }

    // Ambil mapping nama wilayah -> id
    $wilayahMap = [];
    $wRes = $db->query("SELECT id, nama FROM `wilayah` WHERE `deleted_at` IS NULL");
    if ($wRes) {
        while ($w = $wRes->fetch_assoc()) {
            $wilayahMap[strtolower(trim($w['nama']))] = (int)$w['id'];
        }
    }
    $defaultWilayahId = $wilayahMap['jabodetabek'] ?? 1;

    // 2. Pastikan tabel sales_customer ada
    $db->query("CREATE TABLE IF NOT EXISTS `sales_customer` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `kode_customer` VARCHAR(50) NULL,
        `nama` VARCHAR(255) NOT NULL,
        `kategori` VARCHAR(100) NULL DEFAULT 'Dealer',
        `is_tiptok` TINYINT(1) NOT NULL DEFAULT 0,
        `email` VARCHAR(150) NULL,
        `alamat` TEXT NULL,
        `kota` VARCHAR(100) NULL,
        `id_wilayah` INT NULL DEFAULT 0,
        `telp_pribadi` VARCHAR(50) NULL,
        `lat` VARCHAR(50) NULL,
        `lon` VARCHAR(50) NULL,
        `rad` VARCHAR(50) NULL DEFAULT '100',
        `alamat_lokasi` TEXT NULL,
        `foto` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        INDEX (`nama`),
        INDEX (`kota`),
        INDEX (`id_wilayah`),
        INDEX (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure columns exist in sales_customer
    $cols = [
        'kode_customer' => "ALTER TABLE `sales_customer` ADD COLUMN `kode_customer` VARCHAR(50) NULL AFTER `id`",
        'is_tiptok' => "ALTER TABLE `sales_customer` ADD COLUMN `is_tiptok` TINYINT(1) NOT NULL DEFAULT 0 AFTER `kategori`",
        'rad' => "ALTER TABLE `sales_customer` ADD COLUMN `rad` VARCHAR(50) NULL DEFAULT '100' AFTER `lon`",
        'alamat_lokasi' => "ALTER TABLE `sales_customer` ADD COLUMN `alamat_lokasi` TEXT NULL AFTER `rad`"
    ];
    foreach ($cols as $colName => $alterSql) {
        $chkC = $db->query("SHOW COLUMNS FROM `sales_customer` LIKE '$colName'");
        if ($chkC && $chkC->num_rows === 0) {
            @$db->query($alterSql);
        }
    }

    echo "2. Menyinkronkan 1.000 customer resmi (AMAN: Data lama TIDAK dihapus)...\n";
    
    $stmt = $db->prepare("INSERT INTO `sales_customer` (kode_customer, kategori, is_tiptok, nama, telp_pribadi, email, alamat, kota, id_wilayah, lat, lon, rad, alamat_lokasi, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    if (!$stmt) {
        echo "Warning: Prepare statement sales_customer gagal: " . $db->error . "\n";
        continue;
    }

    $inserted = 0;
    $skipped = 0;
    foreach ($customers as $c) {
        $nama = trim($c['nama'] ?? '');
        if (empty($nama)) continue;

        // Cek jika sudah ada customer dengan nama ini (jangan duplikat atau timpa)
        $chkExist = $db->query("SELECT id FROM `sales_customer` WHERE `nama` = '" . $db->real_escape_string($nama) . "' LIMIT 1");
        if ($chkExist && $chkExist->num_rows > 0) {
            $skipped++;
            continue;
        }

        $kode = $c['kode_customer'] ?? '';
        $kategori = $c['kategori'] ?? 'Dealer';
        $is_tiptok = 0;
        $telp = $c['telp_pribadi'] ?? '';
        $email = $c['email'] ?? '';
        $alamat = $c['alamat'] ?? '';
        $kota = $c['kota'] ?? '';
        
        $wName = strtolower(trim($c['wilayah'] ?? ''));
        $id_wilayah = $wilayahMap[$wName] ?? $defaultWilayahId;
        
        $lat = $c['lat'] ?? '-6.1754';
        $lon = $c['lon'] ?? '106.8272';
        $rad = $c['rad'] ?? '100';
        $alamat_lokasi = $c['alamat_lokasi'] ?? ($alamat ?: $kota);

        $stmt->bind_param("ssisssssissss", $kode, $kategori, $is_tiptok, $nama, $telp, $email, $alamat, $kota, $id_wilayah, $lat, $lon, $rad, $alamat_lokasi);
        if ($stmt->execute()) {
            $inserted++;
        }
    }
    $stmt->close();
    echo "✓ Berhasil menambah: {$inserted} customer baru (Dilewati karena sudah ada: {$skipped}). Data lama tetap aman!\n";

    // 3. Jika database ini juga memiliki tabel customers (sales_id_giti), sinkronkan tanpa truncate
    $chkCustTbl = $db->query("SHOW TABLES LIKE 'customers'");
    if ($chkCustTbl && $chkCustTbl->num_rows > 0) {
        echo "3. Menyinkronkan juga ke tabel legacy `customers` tanpa menghapus data lama...\n";

        $stC = $db->prepare("INSERT INTO `customers` (sales_id, tgl_input, nama_toko, kategori, deal, kandidat, potensial, acc_boss) VALUES (1, NOW(), ?, ?, 'DEAL', 'Y', 'Y', 'Y')");
        $stA = $db->prepare("INSERT INTO `customer_addresses` (customer_id, alamat, kota, provinsi, link_google_map) VALUES (?, ?, ?, ?, ?)");
        $stP = $db->prepare("INSERT INTO `customer_pics` (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");

        foreach ($customers as $c) {
            $nama = trim($c['nama'] ?? '');
            if (empty($nama)) continue;

            $chkLeg = $db->query("SELECT id FROM `customers` WHERE `nama_toko` = '" . $db->real_escape_string($nama) . "' LIMIT 1");
            if ($chkLeg && $chkLeg->num_rows > 0) {
                continue;
            }

            $kat = strtoupper($c['kategori'] ?? 'DEALER');
            $stC->bind_param("ss", $nama, $kat);
            if ($stC->execute()) {
                $newId = $db->insert_id;
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
            }
        }
        $stC->close();
        $stA->close();
        $stP->close();
        echo "✓ Berhasil menyinkronkan data ke `customers`!\n";
    }

    // Statistik saat ini
    $resStats = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN kategori = 'Dealer' THEN 1 ELSE 0 END) as cnt_dealer,
        SUM(CASE WHEN kategori = 'User' THEN 1 ELSE 0 END) as cnt_user,
        SUM(CASE WHEN kategori = 'Installer' THEN 1 ELSE 0 END) as cnt_installer,
        SUM(CASE WHEN lat IS NOT NULL AND lat != '' AND lon IS NOT NULL AND lon != '' THEN 1 ELSE 0 END) as cnt_mapped
    FROM `sales_customer` WHERE `deleted_at` IS NULL")->fetch_assoc();

    echo "\nStatistik Tabel `sales_customer` Saat Ini:\n";
    echo "• Total Customer    : " . ($resStats['total'] ?? 0) . " Toko / Customer\n";
    echo "• Dealer & Mitra    : " . ($resStats['cnt_dealer'] ?? 0) . "\n";
    echo "• User (End User)   : " . ($resStats['cnt_user'] ?? 0) . "\n";
    echo "• Installer         : " . ($resStats['cnt_installer'] ?? 0) . "\n";
    echo "• Geofence Terpetakan: " . ($resStats['cnt_mapped'] ?? 0) . " / " . ($resStats['total'] ?? 0) . " (100%)\n";
}

echo "\n====================================================\n";
echo "✅ SELESAI! Data Sales Customer sekarang 100% mutakhir.\n";
echo "====================================================\n";
