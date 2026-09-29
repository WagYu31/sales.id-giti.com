<?php
/**
 * restore_customers.php
 * Script Pemulihan & Penyelamatan Data Lama Sales Customer
 * Dapat dijalankan via CLI: php restore_customers.php
 * Atau via Web Browser: https://sales.id-giti.com/restore_customers.php
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: text/plain; charset=utf-8');

echo "========================================================\n";
echo "   LOEWIX SALES - SCRIPT PEMULIHAN DATA CUSTOMER LAMA   \n";
echo "========================================================\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n\n";

// Muat koneksi database
require_once __DIR__ . '/modul-aplikasi-sales/conn.php';
if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Error: Gagal terhubung ke database!\n");
}

$dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? 'unknown';
echo "Database Terhubung: $dbName\n\n";

// 1. CARI BACKUP DATABASE DARI aaPanel (/www/backup/database/)
echo "[1] Memeriksa Backup Otomatis aaPanel di /www/backup/ ...\n";
$backupRoots = [
    '/www/backup',
    '/var/backups',
    __DIR__ . '/backup',
    __DIR__
];

$foundBackups = [];
foreach ($backupRoots as $root) {
    if (!is_dir($root)) continue;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $file) {
        if ($file->isFile() && preg_match('/\.(sql|sql\.gz)$/i', $file->getFilename())) {
            $path = $file->getPathname();
            $size = round(filesize($path) / 1024 / 1024, 2);
            $foundBackups[] = [
                'path' => $path,
                'file' => $file->getFilename(),
                'size_mb' => $size,
                'time' => date('Y-m-d H:i:s', filemtime($path))
            ];
        }
    }
}

if (!empty($foundBackups)) {
    echo "Ditemukan " . count($foundBackups) . " file backup:\n";
    foreach ($foundBackups as $b) {
        echo "  - {$b['file']} ({$b['size_mb']} MB, {$b['time']})\n";
    }
} else {
    echo "  Tidak ada file .sql.gz di folder backup standar.\n";
}

// 2. CARI MYSQL BINARY LOGS (Untuk merekam ulang INSERT data lama)
echo "\n[2] Memeriksa MySQL Binary Log (Binlog) ...\n";
$binlogDirs = [
    '/www/server/data',
    '/var/lib/mysql',
    '/var/log/mysql'
];

$binlogFiles = [];
foreach ($binlogDirs as $bDir) {
    if (is_dir($bDir)) {
        $files = @scandir($bDir);
        if ($files) {
            foreach ($files as $bf) {
                if (preg_match('/^(mysql-bin|binlog)\.\d+$/', $bf)) {
                    $binlogFiles[] = $bDir . '/' . $bf;
                }
            }
        }
    }
}

if (!empty($binlogFiles)) {
    echo "Ditemukan " . count($binlogFiles) . " file binlog:\n";
    foreach ($binlogFiles as $bf) {
        echo "  - $bf (" . round(filesize($bf)/1024/1024, 2) . " MB)\n";
    }
} else {
    echo "  File binlog tidak ditemukan di folder standar atau permission terbatas.\n";
}

// 3. CARI DARI TABEL TRANSAKSI (pelaksanaan_sales, kegiatan_sales, tiptok_penitipan)
echo "\n[3] Memeriksa Riwayat Client dari Tabel Transaksi Sales ...\n";
$txClients = [];
$chkPel = $conn->query("SHOW TABLES LIKE 'pelaksanaan_sales'");
if ($chkPel && $chkPel->num_rows > 0) {
    $qPel = $conn->query("
        SELECT 
            p.nama_client, 
            p.nomer_client,
            p.tipe_prospek,
            k.alamat_lokasi,
            k.lat,
            k.lon,
            k.rad,
            p.created_at
        FROM pelaksanaan_sales p
        LEFT JOIN kegiatan_sales k ON p.kegiatan_id = k.id
        WHERE p.nama_client IS NOT NULL AND TRIM(p.nama_client) != ''
        ORDER BY p.id ASC
    ");
    if ($qPel) {
        while ($row = $qPel->fetch_assoc()) {
            $nameKey = strtolower(trim($row['nama_client']));
            if (!isset($txClients[$nameKey])) {
                $txClients[$nameKey] = $row;
            }
        }
    }
}

echo "Ditemukan " . count($txClients) . " riwayat unik client dari pelaksanaan_sales:\n";
$txCount = 0;
foreach ($txClients as $c) {
    if (++$txCount <= 10) {
        echo "  • " . $c['nama_client'] . " (WA: " . ($c['nomer_client'] ?: '-') . ", Lokasi: " . ($c['alamat_lokasi'] ?: '-') . ")\n";
    }
}
if (count($txClients) > 10) {
    echo "  ... dan " . (count($txClients) - 10) . " lainnya.\n";
}

// 4. CEK APAKAH ADA BACKUP SQL TERTENTU YANG BISA LANGSUNG DI-PARSE
$restoredFromBackup = 0;
foreach ($foundBackups as $b) {
    // Scan for sales_customer
    $path = $b['path'];
    $cmd = preg_match('/\.gz$/i', $path) ? "zgrep -a -i \"INSERT INTO .sales_customer.\" " . escapeshellarg($path) : "grep -a -i \"INSERT INTO .sales_customer.\" " . escapeshellarg($path);
    $output = @shell_exec($cmd . " 2>/dev/null");
    if (!empty($output)) {
        echo "\n[!] DITEMUKAN DATA SALES_CUSTOMER PADA BACKUP: {$b['file']}!\n";
        echo "Mengekstrak dan memulihkan data...\n";
        
        // Simpan baris insert ke file sementara
        $tmpSql = sys_get_temp_dir() . '/restore_sc_' . time() . '.sql';
        file_put_contents($tmpSql, $output);
        
        // Jalankan insert non-destruktif
        // Ganti INSERT INTO menjadi INSERT IGNORE INTO agar tidak bentrok
        $safeOutput = preg_replace('/INSERT\s+INTO/i', 'INSERT IGNORE INTO', $output);
        
        // Eksekusi multi query
        if ($conn->multi_query($safeOutput)) {
            do {
                if ($res = $conn->store_result()) {
                    $res->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            echo "✓ Berhasil memulihkan data sales_customer dari {$b['file']}!\n";
            $restoredFromBackup++;
        }
    }
}

// 5. JIKA DARI PELAKSANAAN SALES ADA CLIENT YANG BELUM ADA DI SALES_CUSTOMER, RESTORE KEMBALI
echo "\n[5] Sinkronisasi Client dari Transaksi ke sales_customer ...\n";
$recoveredTx = 0;
foreach ($txClients as $nameKey => $client) {
    $nama = trim($client['nama_client']);
    if (empty($nama)) continue;

    $chkEx = $conn->query("SELECT id FROM `sales_customer` WHERE `nama` = '" . $conn->real_escape_string($nama) . "' LIMIT 1");
    if ($chkEx && $chkEx->num_rows === 0) {
        // Insert client ini kembali ke sales_customer
        $telp = $client['nomer_client'] ?? '';
        $kategori = !empty($client['tipe_prospek']) && $client['tipe_prospek'] !== 'Biasa' ? $client['tipe_prospek'] : 'Dealer';
        $alamat = $client['alamat_lokasi'] ?? '';
        $lat = $client['lat'] ?? '-6.1754';
        $lon = $client['lon'] ?? '106.8272';
        $rad = $client['rad'] ?? '100';

        $st = $conn->prepare("INSERT INTO `sales_customer` (nama, kategori, telp_pribadi, alamat, lat, lon, rad, alamat_lokasi, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if ($st) {
            $st->bind_param("ssssssss", $nama, $kategori, $telp, $alamat, $lat, $lon, $rad, $alamat);
            if ($st->execute()) {
                $recoveredTx++;
            }
            $st->close();
        }
    }
}

if ($recoveredTx > 0) {
    echo "✓ Berhasil mengembalikan $recoveredTx customer dari riwayat transaksi sales ke `sales_customer`!\n";
} else {
    echo "• Semua client dari transaksi sudah ada di `sales_customer`.\n";
}

// STATISTIK AKHIR
$qTot = $conn->query("SELECT COUNT(*) as cnt FROM sales_customer WHERE deleted_at IS NULL")->fetch_assoc();
echo "\n========================================================\n";
echo "STATUS SAAT INI:\n";
echo "Total Customer di tabel `sales_customer`: " . ($qTot['cnt'] ?? 0) . " data.\n";
echo "========================================================\n";
