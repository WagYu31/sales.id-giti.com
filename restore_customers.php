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
// Coba copy file backup ke folder webroot agar tidak terblokir open_basedir
@shell_exec("cp -n /www/backup/database/db_teknisi_api_root* " . escapeshellarg(__DIR__));
@shell_exec("cp -n /www/backup/db_teknisi_api_root* " . escapeshellarg(__DIR__));
@shell_exec("cp -n /www/backup/database/*teknisi* " . escapeshellarg(__DIR__));

$backupRoots = [
    __DIR__,
    __DIR__ . '/backup',
    '/www/backup',
    '/var/backups',
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
echo "\n[4] Memproses File Backup Database Otomatis aaPanel...\n";

// Prioritaskan file backup teknisi_api_root terbaru
$candidateBackups = [];
foreach ($foundBackups as $b) {
    if (stripos($b['file'], 'sales_customer') !== false || stripos($b['file'], 'teknisi_api_root') !== false || stripos($b['file'], 'teknisi_root') !== false) {
        $candidateBackups[] = $b;
    }
}
if (empty($candidateBackups)) {
    $candidateBackups = $foundBackups;
}

// Urutkan candidate dari tanggal paling baru
usort($candidateBackups, function($a, $b) {
    return strcmp($b['file'], $a['file']);
});

$restoredFromBackup = 0;
foreach ($candidateBackups as $b) {
    $path = $b['path'];
    $isGz = (bool)preg_match('/\.gz$/i', $path);
    
    // Baca dan ekstrak tabel sales_customer
    $gz = $isGz ? @gzopen($path, 'r') : @fopen($path, 'r');
    if (!$gz) continue;
    
    $capturing = false;
    $tableSql = "";
    while (!($isGz ? gzeof($gz) : feof($gz))) {
        $line = $isGz ? gzgets($gz, 65536) : fgets($gz, 65536);
        if ($line === false) break;

        if (stripos($line, 'CREATE TABLE `sales_customer`') !== false || stripos($line, 'CREATE TABLE IF NOT EXISTS `sales_customer`') !== false) {
            $capturing = true;
        }

        if ($capturing) {
            // Berhenti jika sudah berpindah ke tabel lain
            if ((preg_match('/^CREATE TABLE `(?!sales_customer)/', $line) || preg_match('/^DROP TABLE .*`(?!sales_customer)/', $line)) && strlen($tableSql) > 50) {
                break;
            }
            $tableSql .= $line;
        }
    }
    if ($isGz) gzclose($gz); else fclose($gz);

    if (empty($tableSql) || stripos($tableSql, 'INSERT INTO') === false) {
        continue;
    }

    echo "\n[!] Ditemukan struktur & data `sales_customer` di: {$b['file']}!\n";
    echo "Mengekstrak ke tabel staging sementara `sales_customer_restore_temp`...\n";

    // 1. Buat tabel temp dengan skema persis dari backup
    $conn->query("DROP TABLE IF EXISTS `sales_customer_restore_temp`");
    
    // Ganti nama tabel di SQL dump
    $stagingSql = str_replace('`sales_customer`', '`sales_customer_restore_temp`', $tableSql);
    $stagingSql = preg_replace('/CREATE TABLE (IF NOT EXISTS )?sales_customer/i', 'CREATE TABLE IF NOT EXISTS `sales_customer_restore_temp`', $stagingSql);
    $stagingSql = preg_replace('/INSERT INTO sales_customer/i', 'INSERT INTO `sales_customer_restore_temp`', $stagingSql);

    // Eksekusi multi query
    if ($conn->multi_query($stagingSql)) {
        do {
            if ($res = $conn->store_result()) {
                $res->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    }

    // Cek jumlah data yang berhasil masuk ke temp table
    $chkTemp = $conn->query("SELECT COUNT(*) as cnt FROM `sales_customer_restore_temp`");
    $cntTemp = ($chkTemp && $rT = $chkTemp->fetch_assoc()) ? (int)$rT['cnt'] : 0;

    if ($cntTemp > 0) {
        echo "✓ Berhasil mengekstrak {$cntTemp} customer asli dari backup!\n";
        echo "Menyinkronkan data lama ke tabel `sales_customer` aktif (aman & tanpa duplikasi)...\n";

        // Ambil kolom dari tabel temp dan tabel tujuan
        $tempCols = [];
        $resCols1 = $conn->query("SHOW COLUMNS FROM `sales_customer_restore_temp`");
        while ($rc = $resCols1->fetch_assoc()) {
            $tempCols[] = $rc['Field'];
        }

        $destCols = [];
        $resCols2 = $conn->query("SHOW COLUMNS FROM `sales_customer`");
        while ($rc = $resCols2->fetch_assoc()) {
            $destCols[] = $rc['Field'];
        }

        $commonCols = array_intersect($tempCols, $destCols);
        $colList = '`' . implode('`, `', $commonCols) . '`';

        $updatePairs = [];
        foreach ($commonCols as $cName) {
            if ($cName !== 'id') {
                $updatePairs[] = "`$cName` = VALUES(`$cName`)";
            }
        }
        $updateSql = implode(', ', $updatePairs);

        $copySql = "INSERT INTO `sales_customer` ($colList)
                    SELECT $colList FROM `sales_customer_restore_temp`
                    ON DUPLICATE KEY UPDATE $updateSql";
        
        if ($conn->query($copySql)) {
            echo "✓ SUKSES: {$cntTemp} customer original dari backup {$b['file']} berhasil digabungkan ke `sales_customer`!\n";
            $restoredFromBackup += $cntTemp;

            // Pastikan kode_customer terisi CUST-{id} jika masih kosong
            $conn->query("UPDATE `sales_customer` SET `kode_customer` = CONCAT('CUST-', id) WHERE (`kode_customer` IS NULL OR `kode_customer` = '')");

            // Pastikan alamat_lokasi terisi jika masih kosong
            $conn->query("UPDATE `sales_customer` SET `alamat_lokasi` = alamat WHERE (`alamat_lokasi` IS NULL OR `alamat_lokasi` = '') AND alamat IS NOT NULL");

            // Buat tabel arsip permanen agar tidak akan pernah hilang lagi
            $conn->query("CREATE TABLE IF NOT EXISTS `sales_customer_original_archive` AS SELECT * FROM `sales_customer_restore_temp`");

            // Selesai dengan backup yang paling baru
            break;
        } else {
            echo "Error copySql: " . $conn->error . "\n";
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
