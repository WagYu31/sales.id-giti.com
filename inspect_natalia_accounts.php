<?php
/**
 * inspect_natalia_accounts.php
 * Investigasi akun "Natalia" vs "Natalia Christi"
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   INVESTIGASI AKUN GANDA: 'Natalia' VS 'Natalia Christi'        \n";
echo "=================================================================\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi database gagal.\n");
}

$res = $conn->query("SELECT id, nama_lengkap, email, role, created_at, deleted_at FROM sales WHERE nama_lengkap LIKE '%Natalia%'");
$accounts = [];
while ($row = $res->fetch_assoc()) {
    $accounts[] = $row;
}

echo "Daftar Akun di Database:\n";
foreach ($accounts as $acc) {
    $del = $acc['deleted_at'] ? " (DELETED: {$acc['deleted_at']})" : " (AKTIF)";
    echo "ID: {$acc['id']} | Nama: '{$acc['nama_lengkap']}' | Email: {$acc['email']}{$del}\n";
    
    // Hitung customer saat ini
    $cCust = $conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$acc['id']} AND deleted_at IS NULL")->fetch_row()[0];
    
    // Hitung follow-up
    $cFu = $conn->query("SELECT COUNT(DISTINCT customer_id) as c_cnt, COUNT(*) as f_cnt FROM follow_ups WHERE sales_id = {$acc['id']} AND deleted_at IS NULL")->fetch_assoc();
    
    echo "  -> Toko dipegang saat ini : {$cCust} toko\n";
    echo "  -> Customer pernah di-FU  : {$cFu['c_cnt']} customer ({$cFu['f_cnt']} aktivitas FU)\n\n";
}

echo "=================================================================\n";
