<?php
/**
 * inspect_and_fix_sales.php
 * Memeriksa data penugasan sales dan menyinkronkan kembali sales_id ke tabel customers
 * berdasarkan riwayat follow-up dan penugasan riil sales.
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PEMERIKSAAN & PENYINKRONAN DATA CUSTOMER PER SALES PIC        \n";
echo "=================================================================\n\n";

require_once __DIR__ . '/includes/db.php';
if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Error: Koneksi DB gagal: " . ($conn ? $conn->connect_error : 'null') . "\n");
}

// 1. Cek Daftar Sales
echo "1. DAFTAR SALES DI DATABASE:\n";
$salesList = [];
$qSales = $conn->query("SELECT id, nama_lengkap, email, role FROM sales WHERE deleted_at IS NULL ORDER BY id ASC");
while ($s = $qSales->fetch_assoc()) {
    $salesList[$s['id']] = $s['nama_lengkap'];
    echo "   [ID: {$s['id']}] {$s['nama_lengkap']} ({$s['role']})\n";
}

// 2. Cek Riwayat Follow Up per Sales
echo "\n2. RIWAYAT FOLLOW-UP PER SALES DI TABEL `follow_ups`:\n";
$qFu = $conn->query("
    SELECT sales_id, COUNT(*) as total_fu, COUNT(DISTINCT customer_id) as total_customers 
    FROM follow_ups 
    WHERE deleted_at IS NULL 
    GROUP BY sales_id
");
while ($fu = $qFu->fetch_assoc()) {
    $sName = $salesList[$fu['sales_id']] ?? 'Unknown Sales';
    echo "   - {$sName} (ID: {$fu['sales_id']}): {$fu['total_fu']} follow-up ({$fu['total_customers']} toko unik)\n";
}

// 3. Cek Status customers.sales_id saat ini
echo "\n3. DISTRIBUSI CUSTOMER SAAT INI DI TABEL `customers` (sebelum diperbaiki):\n";
$qCust = $conn->query("
    SELECT sales_id, COUNT(*) as total 
    FROM customers 
    WHERE deleted_at IS NULL 
    GROUP BY sales_id
");
while ($c = $qCust->fetch_assoc()) {
    $sId = $c['sales_id'] ?? 'NULL';
    $sName = $salesList[$sId] ?? ($sId === 'NULL' ? 'Belum Di-assign' : 'Unknown');
    echo "   - {$sName} (sales_id: {$sId}): {$c['total']} customer\n";
}

// 4. Sinkronkan customers.sales_id berdasarkan follow-up terbaru masing-masing sales!
echo "\n4. MENYINKRONKAN customers.sales_id BERDASARKAN FOLLOW-UP TERBARU...\n";
$syncSql = "
    UPDATE customers c
    JOIN (
        SELECT fu1.customer_id, fu1.sales_id
        FROM follow_ups fu1
        INNER JOIN (
            SELECT customer_id, MAX(id) as max_id
            FROM follow_ups
            WHERE deleted_at IS NULL
            GROUP BY customer_id
        ) fu2 ON fu1.id = fu2.max_id
    ) latest_fu ON c.id = latest_fu.customer_id
    SET c.sales_id = latest_fu.sales_id
";

if ($conn->query($syncSql)) {
    echo "✓ Berhasil menyinkronkan {$conn->affected_rows} customer ke sales PIC masing-masing berdasarkan riwayat Follow-Up!\n";
} else {
    echo "Error sinkronisasi: " . $conn->error . "\n";
}

// 5. Cek juga dari sales_work_plans jika ada customer yang sudah direncanakan
$chkSwp = $conn->query("SHOW TABLES LIKE 'sales_work_plans'");
if ($chkSwp && $chkSwp->num_rows > 0) {
    echo "\n5. MENYINKRONKAN DARI TABEL `sales_work_plans`...\n";
    $swpSql = "
        UPDATE customers c
        JOIN (
            SELECT customer_id, sales_id
            FROM sales_work_plans
            WHERE customer_id IS NOT NULL AND deleted_at IS NULL
            GROUP BY customer_id
        ) swp ON c.id = swp.customer_id
        SET c.sales_id = swp.sales_id
        WHERE c.sales_id IS NULL OR c.sales_id = 1
    ";
    if ($conn->query($swpSql)) {
        echo "✓ Berhasil menyinkronkan {$conn->affected_rows} customer dari Rencana Kerja Sales!\n";
    }
}

// 6. Cek Hasil Distribusi Setelah Diperbaiki
echo "\n6. DISTRIBUSI CUSTOMER SETELAH DIPERBAIKI:\n";
$qAfter = $conn->query("
    SELECT c.sales_id, COUNT(*) as total_customers,
           SUM(CASE WHEN c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE deleted_at IS NULL) THEN 1 ELSE 0 END) as sudah_fu,
           SUM(CASE WHEN c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE deleted_at IS NULL) THEN 1 ELSE 0 END) as belum_fu
    FROM customers c 
    WHERE c.deleted_at IS NULL 
    GROUP BY c.sales_id
");
while ($a = $qAfter->fetch_assoc()) {
    $sId = $a['sales_id'] ?? 'NULL';
    $sName = $salesList[$sId] ?? ($sId === 'NULL' ? 'Belum Di-assign' : 'Unknown');
    echo "   • {$sName} (ID: {$sId}): TOTAL {$a['total_customers']} Customer (Sudah FU: {$a['sudah_fu']}, Belum FU: {$a['belum_fu']})\n";
}

echo "\n=================================================================\n";
echo "✅ Selesai! Data customer untuk setiap sales PIC kini sudah aktif.\n";
echo "=================================================================\n";
