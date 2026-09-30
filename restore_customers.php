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

function safe_shell_exec($cmd) {
    if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))))) {
        return @shell_exec($cmd);
    }
    return false;
}

// 1. CARI BACKUP DATABASE DARI aaPanel (/www/backup/database/)
echo "[1] Memeriksa Backup Otomatis aaPanel di /www/backup/ ...\n";
safe_shell_exec("cp -n /www/backup/database/db_teknisi_api_root* " . escapeshellarg(__DIR__));
safe_shell_exec("cp -n /www/backup/db_teknisi_api_root* " . escapeshellarg(__DIR__));
safe_shell_exec("cp -n /www/backup/database/*teknisi* " . escapeshellarg(__DIR__));

$backupRoots = [
    __DIR__,
    __DIR__ . '/backup',
    '/www/backup/database',
    '/www/backup',
    '/var/backups',
];

$foundBackups = [];
foreach ($backupRoots as $root) {
    if (!is_dir($root)) continue;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            if ($file->isFile() && preg_match('/\.(sql|sql\.gz)$/i', $file->getFilename())) {
                $path = $file->getPathname();
                $size = round(filesize($path) / 1024 / 1024, 2);
                $foundBackups[$path] = [
                    'path' => $path,
                    'file' => $file->getFilename(),
                    'size_mb' => $size,
                    'time' => date('Y-m-d H:i:s', filemtime($path))
                ];
            }
        }
    } catch (Exception $e) {
        // Abaikan folder tanpa izin baca
    }
}
$foundBackups = array_values($foundBackups);

// Tambahkan pengecekan langsung ke file backup spesifik 28 & 29 Sep
$directPaths = [
    '/www/backup/database/db_teknisi_api_root_20260929_013008_mysql_data.sql.gz',
    '/www/backup/database/db_teknisi_api_root_20260928_013009_mysql_data.sql.gz',
    __DIR__ . '/db_teknisi_api_root_20260929_013008_mysql_data.sql.gz',
    __DIR__ . '/db_teknisi_api_root_20260928_013009_mysql_data.sql.gz',
];
foreach ($directPaths as $dp) {
    if (file_exists($dp)) {
        $foundBackups[$dp] = [
            'path' => $dp,
            'file' => basename($dp),
            'size_mb' => round(filesize($dp) / 1024 / 1024, 2),
            'time' => date('Y-m-d H:i:s', filemtime($dp))
        ];
    }
}
$foundBackups = array_values($foundBackups);

if (!empty($foundBackups)) {
    echo "Ditemukan " . count($foundBackups) . " file backup:\n";
    foreach ($foundBackups as $b) {
        echo "  - {$b['file']} ({$b['size_mb']} MB, {$b['time']})\n";
    }
} else {
    echo "  Tidak ada file .sql.gz di folder backup standar.\n";
}

// 2. CEK TABEL CADANGAN EXCEL
// Simpan salinan data customer saat ini agar data 1.000 customer Excel tetap tersimpan aman
$conn->query("CREATE TABLE IF NOT EXISTS `sales_customer_excel_backup` AS SELECT * FROM `sales_customer`");

// 3. PROSES RESTORE DARI FILE BACKUP (SEBELUM TRUNCATE 29 SEP)
echo "\n[2] Memproses File Backup Database Asli (Target: 29 & 28 September 2026)...\n";

$goldenBackups = [];
$otherCandidates = [];

foreach ($foundBackups as $b) {
    $fName = $b['file'];
    if (strpos($fName, '20260930') !== false) {
        continue;
    }
    
    if (stripos($fName, 'teknisi_api_root') !== false && (strpos($fName, '20260929') !== false || strpos($fName, '20260928') !== false)) {
        $goldenBackups[] = $b;
    } elseif (stripos($fName, 'teknisi_root') !== false && (strpos($fName, '20260929') !== false || strpos($fName, '20260928') !== false)) {
        $otherCandidates[] = $b;
    } elseif (stripos($fName, 'sales_customer') !== false) {
        $goldenBackups[] = $b;
    }
}

$candidateBackups = array_merge($goldenBackups, $otherCandidates);

$restoredFromBackup = 0;
foreach ($candidateBackups as $b) {
    $path = $b['path'];
    $bFile = $b['file'];
    $isGz = (bool)preg_match('/\.gz$/i', $path);

    echo "\n--> Memeriksa isi file: {$bFile} ...\n";

    $gz = $isGz ? @gzopen($path, 'r') : @fopen($path, 'r');
    if (!$gz) {
        echo "  [x] Gagal membuka file $bFile (periksa permission file)\n";
        continue;
    }

    $capturing = false;
    $createTableLines = [];
    $insertLines = [];
    $foundTable = false;

    while (!($isGz ? gzeof($gz) : feof($gz))) {
        $line = $isGz ? gzgets($gz, 1048576) : fgets($gz, 1048576);
        if ($line === false) break;

        if (stripos($line, 'CREATE TABLE `sales_customer`') !== false || stripos($line, 'CREATE TABLE IF NOT EXISTS `sales_customer`') !== false) {
            $capturing = true;
            $foundTable = true;
            $createTableLines[] = $line;
            continue;
        }

        if ($capturing) {
            // Berhenti jika sudah berpindah ke tabel berikutnya
            if (preg_match('/^-- Table structure for table `(?!sales_customer)/i', $line) ||
                preg_match('/^DROP TABLE IF EXISTS `(?!sales_customer)/i', $line) ||
                preg_match('/^CREATE TABLE `(?!sales_customer)/i', $line)) {
                break;
            }

            if (!empty($createTableLines) && empty($insertLines)) {
                $createTableLines[] = $line;
            }

            if (stripos($line, 'INSERT INTO `sales_customer`') !== false || stripos($line, 'INSERT INTO sales_customer') !== false) {
                $insertLines[] = $line;
            }
        }
    }
    if ($isGz) gzclose($gz); else fclose($gz);

    if (!$foundTable || empty($insertLines)) {
        echo "  [-] Tidak ditemukan data `sales_customer` di $bFile\n";
        continue;
    }

    echo "  [✓] Ditemukan skema dan " . count($insertLines) . " blok INSERT di {$bFile}!\n";
    echo "  Mengekstrak data ke tabel sementara `sales_customer_restore_temp`...\n";

    $conn->query("DROP TABLE IF EXISTS `sales_customer_restore_temp`");
    
    $createSql = implode("", $createTableLines);
    $createSql = str_replace('`sales_customer`', '`sales_customer_restore_temp`', $createSql);
    $createSql = preg_replace('/CREATE TABLE (IF NOT EXISTS )?sales_customer/i', 'CREATE TABLE IF NOT EXISTS `sales_customer_restore_temp`', $createSql);
    $createSql = preg_replace('/;\s*\/\*!.*$/s', ';', $createSql);

    $resCreate = $conn->query($createSql);
    if (!$resCreate) {
        $conn->query("CREATE TABLE `sales_customer_restore_temp` LIKE `sales_customer`");
    }

    $insertedRows = 0;
    foreach ($insertLines as $idx => $ins) {
        $insClean = str_replace('`sales_customer`', '`sales_customer_restore_temp`', $ins);
        $insClean = preg_replace('/INSERT INTO sales_customer /i', 'INSERT INTO `sales_customer_restore_temp` ', $insClean);
        $insClean = trim($insClean);
        if (substr($insClean, -1) !== ';') {
            $insClean .= ';';
        }

        if ($conn->query($insClean)) {
            $insertedRows += $conn->affected_rows;
        } else {
            echo "  [x] Error pada blok INSERT #" . ($idx + 1) . ": " . $conn->error . "\n";
        }
    }

    $chkTemp = $conn->query("SELECT COUNT(*) as cnt FROM `sales_customer_restore_temp`");
    $cntTemp = ($chkTemp && $rT = $chkTemp->fetch_assoc()) ? (int)$rT['cnt'] : 0;

    echo "  [✓] Berhasil memasukkan {$cntTemp} baris customer asli ke staging table!\n";

    if ($cntTemp > 0) {
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
            echo "  [★] SUKSES BESAR: {$cntTemp} customer original dari {$bFile} berhasil dipulihkan ke `sales_customer`!\n";
            $restoredFromBackup += $cntTemp;

            $conn->query("DROP TABLE IF EXISTS `sales_customer_original_archive`");
            $conn->query("CREATE TABLE `sales_customer_original_archive` AS SELECT * FROM `sales_customer_restore_temp`");

            break;
        } else {
            echo "  [x] Error penggabungan data: " . $conn->error . "\n";
        }
    }
}

// 4. PASTIKAN KODE CUSTOMER & ALAMAT TERISI
$conn->query("UPDATE `sales_customer` SET `kode_customer` = CONCAT('CUST-', LPAD(id, 4, '0')) WHERE (`kode_customer` IS NULL OR `kode_customer` = '')");
$conn->query("UPDATE `sales_customer` SET `alamat_lokasi` = alamat WHERE (`alamat_lokasi` IS NULL OR `alamat_lokasi` = '') AND alamat IS NOT NULL");

// 5. UPDATE SEMUA FILE API_SALES_TASK DI SERVER AGAR SORTING DESCENDING (TERBARU DI ATAS)
echo "\n[3] Memperbarui Sorting api_sales_task.php (Terbaru Di Atas)... \n";

// Salin langsung file terbaru dari repository teknisi-api-github ke api-teknisi.id-giti.com
$sourceApi = '/www/wwwroot/teknisi-api-github.id-giti.com/public/api_sales_task.php';
if (!file_exists($sourceApi)) {
    $sourceApi = __DIR__ . '/modul-aplikasi-sales/api/api_sales_task.php';
}

if (file_exists($sourceApi)) {
    @copy($sourceApi, '/www/wwwroot/api-teknisi.id-giti.com/api_sales_task.php');
    @copy($sourceApi, '/www/wwwroot/api-teknisi.id-giti.com/public/api_sales_task.php');
}

$targetApiDirs = [
    __DIR__ . '/modul-aplikasi-sales/api/api_sales_task.php',
    __DIR__ . '/modul-aplikasi-sales/api_sales_task.php',
    '/www/wwwroot/api-teknisi.id-giti.com/public/api_sales_task.php',
    '/www/wwwroot/api-teknisi.id-giti.com/api_sales_task.php',
    '/www/wwwroot/teknisi-api.id-giti.com/public/api_sales_task.php',
    '/www/wwwroot/teknisi-api.id-giti.com/api_sales_task.php',
    '/www/wwwroot/teknisi-api-github.id-giti.com/public/api_sales_task.php',
    '/www/wwwroot/jadwal.id-giti.com/teknisi-api-github.id-giti.com/public/api_sales_task.php',
    '/www/wwwroot/jadwal.id-giti.com/modul-aplikasi-sales/api/api_sales_task.php',
];

$glob1 = glob('/www/wwwroot/*/public/api_sales_task.php') ?: [];
$glob2 = glob('/www/wwwroot/*/api_sales_task.php') ?: [];
$glob3 = glob('/www/wwwroot/*/*/public/api_sales_task.php') ?: [];
$targetApiDirs = array_unique(array_merge($targetApiDirs, $glob1, $glob2, $glob3));

$sortUpdatedCount = 0;
foreach ($targetApiDirs as $apiFile) {
    if (file_exists($apiFile) && is_writable($apiFile)) {
        $content = file_get_contents($apiFile);
        if (strpos($content, '$orderClause = ($filter === \'all\')') === false) {
            $content = str_replace(
                'ORDER BY ks.jadwal ASC, ks.id ASC',
                'ORDER BY ks.jadwal DESC, ks.id DESC',
                $content
            );
            file_put_contents($apiFile, $content);
            $sortUpdatedCount++;
            echo "  ✓ Diperbarui: $apiFile\n";
        }
    }
}
if ($sortUpdatedCount === 0) {
    echo "  (Semua file api_sales_task yang terdeteksi sudah menggunakan sorting DESC atau diatur via git)\n";
}

// 7. STATUS AKHIR
$qTot = $conn->query("SELECT COUNT(*) as cnt FROM sales_customer WHERE deleted_at IS NULL")->fetch_assoc();
$qSample = $conn->query("SELECT id, nama, alamat, telp_pribadi FROM sales_customer WHERE id IN (22, 15, 16, 36, 46) ORDER BY id ASC");

echo "\n========================================================\n";
echo "STATUS SAAT INI:\n";
echo "Total Customer Aktif: " . ($qTot['cnt'] ?? 0) . " data.\n";
echo "Sampel ID Kunjungan Sales:\n";
while ($s = $qSample->fetch_assoc()) {
    echo "  - ID {$s['id']}: " . $s['nama'] . " | " . mb_substr($s['alamat'] ?? '', 0, 40) . "\n";
}
echo "========================================================\n";
echo "SELESAI!\n";
