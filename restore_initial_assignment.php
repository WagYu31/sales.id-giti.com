<?php
/**
 * restore_initial_assignment.php
 * Script untuk memeriksa dan mengembalikan pembagian customer ke sales seperti semula.
 *
 * Cara menjalankan di terminal server:
 *   cd /www/wwwroot/sales.id-giti.com
 *   php restore_initial_assignment.php
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PEMERIKSAAN & PEMULIHAN PEMBAGIAN CUSTOMER SALES SEMULA       \n";
echo "=================================================================\n";
echo "Waktu: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Error: Koneksi DB gagal.\n");
}

// 1. Cek Sales Aktif
$salesList = [];
$resSales = $conn->query("SELECT id, nama_lengkap, role FROM sales WHERE deleted_at IS NULL ORDER BY id ASC");
while ($s = $resSales->fetch_assoc()) {
    $salesList[$s['id']] = $s['nama_lengkap'];
}

echo "1. DAFTAR SALES AKTIF:\n";
foreach ($salesList as $sid => $sname) {
    echo "   • [ID: {$sid}] {$sname}\n";
}

// 2. Cek Distribusi Saat Ini
echo "\n2. DISTRIBUSI CUSTOMER SAAT INI:\n";
$resCur = $conn->query("
    SELECT c.sales_id, COUNT(*) as total 
    FROM customers c 
    WHERE c.deleted_at IS NULL 
    GROUP BY c.sales_id 
    ORDER BY total DESC
");
$totalCust = 0;
while ($r = $resCur->fetch_assoc()) {
    $sid = $r['sales_id'] ?? 'NULL';
    $sname = $salesList[$sid] ?? ($sid === 'NULL' ? '⚠️ Belum Ada Sales' : 'Unknown');
    echo "   • {$sname} (ID: {$sid}): " . number_format($r['total']) . " toko\n";
    $totalCust += (int)$r['total'];
}
echo "   Total Customer: " . number_format($totalCust) . " toko\n";

// 3. Cari File Backup Database di Server
echo "\n3. MEMERIKSA FILE BACKUP DATABASE DI SERVER (/www/backup/database/) ...\n";
$backupDirs = [
    '/www/backup/database',
    '/www/backup',
    __DIR__,
    __DIR__ . '/backup'
];

$foundBackups = [];
$permNotice = false;
foreach ($backupDirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = @scandir($dir);
    if ($files === false) {
        $out = @shell_exec("sudo ls -1 " . escapeshellarg($dir) . " 2>/dev/null");
        if ($out) {
            $files = array_filter(explode("\n", trim($out)));
        } else {
            $permNotice = true;
            continue;
        }
    }
    foreach ($files as $f) {
        if (preg_match('/\.(sql|sql\.gz)$/i', $f)) {
            $fullPath = $dir . '/' . $f;
            $sz = file_exists($fullPath) ? round(filesize($fullPath) / 1024 / 1024, 2) : 0;
            $mt = file_exists($fullPath) ? date('Y-m-d H:i:s', filemtime($fullPath)) : '-';
            $foundBackups[] = [
                'path' => $fullPath,
                'name' => $f,
                'size' => $sz,
                'mtime' => $mt
            ];
        }
    }
}

if (!empty($foundBackups)) {
    echo "Ditemukan " . count($foundBackups) . " file backup:\n";
    foreach ($foundBackups as $idx => $b) {
        echo "   [" . ($idx + 1) . "] {$b['name']} ({$b['size']} MB, tanggal: {$b['mtime']})\n";
    }
} else {
    echo "   Tidak ditemukan file backup yang dapat dibaca.\n";
    if ($permNotice) {
        echo "   (💡 Tip: Jalankan dengan `sudo php restore_initial_assignment.php` untuk membaca folder /www/backup)\n";
    }
}

// 4. Analisis Mode Eksekusi
$mode = $argv[1] ?? '';

if ($mode === '--restore-backup' && isset($argv[2])) {
    $targetFile = $argv[2];
    if (!file_exists($targetFile)) {
        die("Error: File backup $targetFile tidak ditemukan!\n");
    }
    echo "\nMemulihkan sales_id customer dari file backup: $targetFile ...\n";
    
    $isGz = (bool)preg_match('/\.gz$/i', $targetFile);
    $handle = $isGz ? gzopen($targetFile, 'r') : fopen($targetFile, 'r');
    if (!$handle) die("Gagal membuka file backup.\n");

    $inCustomers = false;
    $updated = 0;
    $stmtUp = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");

    while (!($isGz ? gzeof($handle) : feof($handle))) {
        $line = $isGz ? gzgets($handle, 65536) : fgets($handle);
        if ($line === false) break;

        if (stripos($line, 'INSERT INTO `customers`') !== false || stripos($line, 'INSERT INTO customers') !== false) {
            $inCustomers = true;
        }

        if ($inCustomers) {
            // Cocokkan pola: (id, sales_id, ...)
            if (preg_match_all('/\((\d+),\s*(\d+|NULL),/i', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $cId = (int)$m[1];
                    $sId = ($m[2] === 'NULL' || $m[2] === null) ? null : (int)$m[2];
                    if ($sId !== null && isset($salesList[$sId])) {
                        $stmtUp->bind_param("ii", $sId, $cId);
                        $stmtUp->execute();
                        if ($conn->affected_rows > 0) $updated++;
                    }
                }
            }
            if (strpos($line, ';') !== false) {
                $inCustomers = false;
            }
        }
    }
    $isGz ? gzclose($handle) : fclose($handle);
    $stmtUp->close();
    echo "✓ Selesai! Sebanyak {$updated} customer berhasil dikembalikan penugasan sales-nya sesuai backup.\n";

} elseif ($mode === '--divide-evenly') {
    // Mode Pembagian Merata ke Sales Aktif
    echo "\n4. MEMBAGI RATA CUSTOMER KE SALES AKTIF...\n";

    $customIds = [];
    foreach ($argv as $arg) {
        if (strpos($arg, '--sales-ids=') === 0) {
            $raw = substr($arg, strlen('--sales-ids='));
            $customIds = array_filter(array_map('intval', explode(',', $raw)));
        }
    }

    $activeSales = [];
    if (!empty($customIds)) {
        $inList = implode(',', $customIds);
        $resAS = $conn->query("SELECT id, nama_lengkap FROM sales WHERE id IN ($inList) AND deleted_at IS NULL ORDER BY id ASC");
    } else {
        $resAS = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL ORDER BY id ASC");
    }

    while ($as = $resAS->fetch_assoc()) {
        $activeSales[] = $as;
    }

    if (empty($activeSales)) {
        die("Error: Tidak ada sales aktif ditemukan.\n");
    }

    $salesCount = count($activeSales);
    echo "Membagi ke {$salesCount} sales:\n";
    foreach ($activeSales as $as) {
        echo "   • [ID: {$as['id']}] {$as['nama_lengkap']}\n";
    }

    // Ambil semua customer ID
    $qAllCust = $conn->query("SELECT id FROM customers WHERE deleted_at IS NULL ORDER BY id ASC");
    $allIds = [];
    while ($row = $qAllCust->fetch_assoc()) {
        $allIds[] = (int)$row['id'];
    }

    $totalToDivide = count($allIds);
    $perSales = ceil($totalToDivide / $salesCount);
    echo "Total {$totalToDivide} toko dibagi ke {$salesCount} sales (~{$perSales} toko per sales).\n\n";

    $conn->begin_transaction();
    $stmtDivide = $conn->prepare("UPDATE customers SET sales_id = ? WHERE id = ?");

    $assignedCount = [];
    foreach ($activeSales as $as) {
        $assignedCount[$as['id']] = 0;
    }

    foreach ($allIds as $idx => $cid) {
        $targetSalesIndex = $idx % $salesCount;
        $targetSales = $activeSales[$targetSalesIndex];
        $tsId = $targetSales['id'];

        $stmtDivide->bind_param("ii", $tsId, $cid);
        $stmtDivide->execute();
        $assignedCount[$tsId]++;
    }

    $stmtDivide->close();
    $conn->commit();

    echo "✓ PEMBAGIAN MERATA BERHASIL DITERAPKAN:\n";
    foreach ($activeSales as $as) {
        echo "   • {$as['nama_lengkap']}: " . number_format($assignedCount[$as['id']]) . " toko\n";
    }

} else {
    echo "\n=================================================================\n";
    echo "PANDUAN OPSI PEMULIHAN:\n";
    echo "1. Untuk mengembalikan sesuai file backup tanggal tertentu:\n";
    echo "   php restore_initial_assignment.php --restore-backup /path/ke/file_backup.sql.gz\n\n";
    echo "2. Untuk membagi rata seluruh customer ke semua sales aktif:\n";
    echo "   php restore_initial_assignment.php --divide-evenly\n";
    echo "=================================================================\n";
}
