<?php
/**
 * check_backup_history.php
 * Membaca file backup database aaPanel (28/29 September 2026)
 * untuk mengetahui secara pasti berapa jumlah customer masing-masing sales
 * SEBELUM pembagian kemarin diubah-ubah.
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PELACAKAN HISTORIS JUMLAH CUSTOMER SALES DARI BACKUP LAMA     \n";
echo "=================================================================\n";

$backupDirs = [
    '/www/backup/database',
    '/www/backup',
    __DIR__ . '/backup',
    __DIR__
];

$files = [];
foreach ($backupDirs as $dir) {
    if (!is_dir($dir)) continue;
    $dh = @opendir($dir);
    if ($dh) {
        while (($f = readdir($dh)) !== false) {
            if (preg_match('/\.(sql|sql\.gz)$/i', $f)) {
                $p = $dir . '/' . $f;
                $files[$f] = [
                    'path' => $p,
                    'file' => $f,
                    'size' => round(filesize($p)/1024/1024, 2),
                    'mtime' => date('Y-m-d H:i:s', filemtime($p))
                ];
            }
        }
        closedir($dh);
    }
}

if (empty($files)) {
    die("Tidak ditemukan file backup di folder /www/backup/database.\n");
}

echo "Daftar File Backup Ditemukan:\n";
foreach ($files as $f => $meta) {
    echo "  • {$f} ({$meta['size']} MB, tanggal: {$meta['mtime']})\n";
}
echo "-----------------------------------------------------------------\n\n";

// Targetkan backup 28 & 29 September sebelum insiden
$targetFile = null;
foreach ($files as $f => $meta) {
    if (strpos($f, '20260929') !== false || strpos($f, '20260928') !== false) {
        $targetFile = $meta['path'];
        break;
    }
}

if (!$targetFile) {
    // Ambil backup terlama yang ada
    uasort($files, function($a, $b) { return strcmp($a['mtime'], $b['mtime']); });
    $first = reset($files);
    $targetFile = $first['path'];
}

echo "Menganalisis isi backup: " . basename($targetFile) . " ...\n";
$isGz = (bool)preg_match('/\.gz$/i', $targetFile);
$fp = $isGz ? gzopen($targetFile, 'r') : fopen($targetFile, 'r');

if (!$fp) {
    die("Gagal membuka file backup: {$targetFile}\n");
}

$salesCounts = [];
$totalRows = 0;
$inCustomerTable = false;
$columns = [];

while (!($isGz ? gzeof($fp) : feof($fp))) {
    $line = $isGz ? gzgets($fp, 4194304) : fgets($fp, 4194304);
    if ($line === false) break;

    // Deteksi tabel customers atau sales_customer
    if (preg_match('/CREATE TABLE `(customers|sales_customer)`/i', $line, $m)) {
        $inCustomerTable = $m[1];
        $columns = [];
        continue;
    }

    if ($inCustomerTable && preg_match('/CREATE TABLE `(?!(' . $inCustomerTable . '))/i', $line)) {
        $inCustomerTable = false;
        continue;
    }

    // Deteksi INSERT INTO customers
    if ($inCustomerTable && preg_match('/INSERT INTO `' . $inCustomerTable . '`(\s*\((.*?)\))?\s*VALUES/i', $line, $m)) {
        // Parse nama kolom jika ada
        if (!empty($m[2])) {
            $cols = array_map(function($c) { return trim(str_replace('`', '', $c)); }, explode(',', $m[2]));
            $salesColIdx = array_search('sales_id', $cols);
            if ($salesColIdx === false) $salesColIdx = array_search('id_sales', $cols);
        } else {
            // Default kolom standard schema
            $salesColIdx = 4; // default estimasi posisi sales_id
        }

        // Ekstrak baris data (...), (...)
        preg_match_all('/\((.*?)\)([,;])/s', $line, $records);
        if (!empty($records[1])) {
            foreach ($records[1] as $rec) {
                $totalRows++;
                $vals = str_getcsv($rec, ',', "'");
                // Cek sales_id
                $sid = isset($vals[$salesColIdx]) ? trim($vals[$salesColIdx]) : 'NULL';
                $sid = is_numeric($sid) ? (int)$sid : 'NULL';
                $salesCounts[$sid] = ($salesCounts[$sid] ?? 0) + 1;
            }
        }
    }
}

if ($isGz) gzclose($fp); else fclose($fp);

require_once __DIR__ . '/includes/db.php';
$salesNames = [];
if (isset($conn) && !$conn->connect_error) {
    $qS = $conn->query("SELECT id, nama_lengkap FROM sales");
    if ($qS) {
        while ($rs = $qS->fetch_assoc()) {
            $salesNames[$rs['id']] = $rs['nama_lengkap'];
        }
    }
}

echo "\n=================================================================\n";
echo "HASIL JUMLAH CUSTOMER ASLI SEBELUM PEMBAGIAN BERUBAH:\n";
echo "Sumber: " . basename($targetFile) . "\n";
echo "=================================================================\n";
arsort($salesCounts);
foreach ($salesCounts as $sid => $cnt) {
    $name = $salesNames[$sid] ?? ($sid === 'NULL' ? 'Tanpa Sales / Unassigned' : "Sales ID: {$sid}");
    echo sprintf("• [ID: %-4s] %-28s : %d toko\n", $sid, $name, $cnt);
}
echo "-----------------------------------------------------------------\n";
echo "Total Customer di Backup: {$totalRows} toko\n";
echo "=================================================================\n";
