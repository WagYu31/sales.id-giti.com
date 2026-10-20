<?php
/**
 * fix_siti_to_cekiang.php
 * Skrip untuk mengubah nama customer 'Siti' (Invoice 2609.INV.06720, Edi Suprianto)
 * menjadi 'Cekiang'.
 *
 * Cara jalankan di server:
 *   cd /www/wwwroot/sales.id-giti.com
 *   php fix_siti_to_cekiang.php
 * Atau via browser: https://sales.id-giti.com/fix_siti_to_cekiang.php
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    if (!isset($_SESSION['user_id'])) {
        die("<div style='font-family:sans-serif;padding:20px;color:red;'>Silakan login terlebih dahulu di <a href='login.php'>sales.id-giti.com</a></div>");
    }
}

echo $is_cli ? "" : "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Update Nama Siti ke Cekiang</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='p-4 bg-light'><div class='container' style='max-width:800px;'><div class='card p-4 shadow-sm rounded-4'>";

// 1. Cari berdasarkan Invoice 2609.INV.06720
$qInv = $conn->query("
    SELECT fu.id as fu_id, fu.no_inv, fu.nominal_invoice, fu.tgl_follow_up, fu.customer_id, 
           c.id as cid, c.nama_toko, c.sales_id, s.nama_lengkap as nama_sales
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE fu.no_inv LIKE '%06720%' AND fu.deleted_at IS NULL
");

$foundByInv = $qInv ? $qInv->fetch_all(MYSQLI_ASSOC) : [];

// 2. Cari customer dengan nama Siti
$qSiti = $conn->query("
    SELECT c.*, s.nama_lengkap as nama_sales 
    FROM customers c 
    LEFT JOIN sales s ON c.sales_id = s.id 
    WHERE (LOWER(c.nama_toko) = 'siti' OR LOWER(c.nama_toko) LIKE 'siti %' OR LOWER(c.nama_toko) LIKE '% siti')
      AND c.deleted_at IS NULL
");
$foundByName = $qSiti ? $qSiti->fetch_all(MYSQLI_ASSOC) : [];

// 3. Cari jika sudah ada customer bernama Cekiang / Cekiang Liem
$qCek = $conn->query("
    SELECT c.*, s.nama_lengkap as nama_sales 
    FROM customers c 
    LEFT JOIN sales s ON c.sales_id = s.id 
    WHERE LOWER(c.nama_toko) LIKE '%cekiang%' 
      AND c.deleted_at IS NULL
");
$existingCekiang = $qCek ? $qCek->fetch_all(MYSQLI_ASSOC) : [];

$output = [];
$output[] = "=================================================================";
$output[] = "   PENGUBAHAN NAMA CUSTOMER: SITI -> CEKIANG                     ";
$output[] = "=================================================================";
$output[] = "Waktu: " . date('Y-m-d H:i:s') . "\n";

// Target Customer ID yang akan diubah
$targetCustomerIds = [];

if (!empty($foundByInv)) {
    $output[] = "1. Ditemukan Transaksi Invoice 2609.INV.06720:";
    foreach ($foundByInv as $row) {
        $output[] = "   • Customer [ID: #{$row['cid']}] '{$row['nama_toko']}'";
        $output[] = "     Sales   : {$row['nama_sales']} (ID: {$row['sales_id']})";
        $output[] = "     Invoice : {$row['no_inv']} | Rp " . number_format($row['nominal_invoice'], 0, ',', '.') . " ({$row['tgl_follow_up']})";
        $targetCustomerIds[] = (int)$row['cid'];
    }
    $output[] = "";
}

if (!empty($foundByName)) {
    $output[] = "2. Ditemukan Customer bernama 'Siti':";
    foreach ($foundByName as $row) {
        $output[] = "   • Customer [ID: #{$row['id']}] '{$row['nama_toko']}' (Sales: {$row['nama_sales']})";
        $targetCustomerIds[] = (int)$row['id'];
    }
    $output[] = "";
}

$targetCustomerIds = array_unique($targetCustomerIds);

// Eksekusi Update ke 'Cekiang'
$newName = 'Cekiang';
$updatedCount = 0;

if (!empty($targetCustomerIds)) {
    foreach ($targetCustomerIds as $cid) {
        // Ambil nama lama
        $oldQ = $conn->query("SELECT nama_toko FROM customers WHERE id = $cid");
        $oldName = $oldQ ? $oldQ->fetch_assoc()['nama_toko'] : '';
        
        // Update nama_toko di tabel customers
        $stmtUp = $conn->prepare("UPDATE customers SET nama_toko = ? WHERE id = ?");
        $stmtUp->bind_param("si", $newName, $cid);
        if ($stmtUp->execute()) {
            $updatedCount++;
            $output[] = "✅ BERHASIL: Customer [ID: #{$cid}] diubah dari '{$oldName}' -> '{$newName}'";
            
            // Update juga nama_pic jika berisi 'Siti'
            $conn->query("UPDATE customer_pics SET nama_pic = '$newName' WHERE customer_id = $cid AND LOWER(nama_pic) LIKE '%siti%'");
        } else {
            $output[] = "❌ GAGAL update customer [ID: #{$cid}]: " . $stmtUp->error;
        }
        $stmtUp->close();
    }
} else {
    $output[] = "⚠️ Tidak ditemukan customer bernama 'Siti' atau transaksi invoice 2609.INV.06720 yang perlu diubah.";
}

// Info Customer Cekiang Lainnya di Sistem
if (!empty($existingCekiang)) {
    $output[] = "\n3. Informasi Toko Cekiang yang Ada di Database:";
    foreach ($existingCekiang as $c) {
        $output[] = "   • [ID: #{$c['id']}] {$c['nama_toko']} (Sales: {$c['nama_sales']})";
    }
}

$output[] = "\n=================================================================";
$output[] = "   SELESAI! Silakan refresh dashboard atau buka kembali modal.   ";
$output[] = "=================================================================";

$textResult = implode("\n", $output);

if ($is_cli) {
    echo $textResult . "\n";
} else {
    echo "<h4 class='fw-bold mb-3'>Hasil Pembaruan Nama Customer</h4>";
    echo "<pre class='bg-dark text-light p-3 rounded-3' style='font-size:13px;'>" . htmlspecialchars($textResult) . "</pre>";
    echo "<a href='index.php' class='btn btn-primary rounded-pill px-4'>Kembali ke Dashboard</a>";
    echo "</div></div></body></html>";
}
