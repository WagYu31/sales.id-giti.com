<?php
/**
 * inspect_natalia_accounts.php
 * Investigasi akun "Natalia" vs "Natalia Christi" & Opsi Penggabungan (Merge)
 *
 * Cara menjalankan:
 *   cd /www/wwwroot/sales.id-giti.com
 *   php inspect_natalia_accounts.php
 *   php inspect_natalia_accounts.php --merge
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

$res = $conn->query("SELECT id, nama_lengkap, email, role, created_at, deleted_at FROM sales WHERE nama_lengkap LIKE '%Natalia%' ORDER BY id ASC");
$accounts = [];
while ($row = $res->fetch_assoc()) {
    $accounts[] = $row;
}

if (empty($accounts)) {
    echo "Tidak ditemukan akun sales dengan nama Natalia.\n";
    exit;
}

echo "Daftar Akun Ditemukan:\n";
foreach ($accounts as $acc) {
    $del = $acc['deleted_at'] ? " [NONAKTIF / DELETED]" : " [AKTIF]";
    echo "• ID: {$acc['id']} | Nama: '{$acc['nama_lengkap']}' | Email: {$acc['email']}{$del}\n";
    
    // Hitung customer saat ini
    $cCust = $conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$acc['id']} AND deleted_at IS NULL")->fetch_row()[0];
    
    // Hitung follow-up
    $cFu = $conn->query("SELECT COUNT(DISTINCT customer_id) as c_cnt, COUNT(*) as f_cnt FROM follow_ups WHERE sales_id = {$acc['id']} AND deleted_at IS NULL")->fetch_assoc();
    
    echo "  -> Toko dipegang saat ini : {$cCust} toko\n";
    echo "  -> Riwayat follow-up      : {$cFu['c_cnt']} customer ({$cFu['f_cnt']} kali FU)\n\n";
}

echo "=================================================================\n";

// Analisis siapa akun utama dan siapa akun duplikat
if (count($accounts) >= 2) {
    // Tentukan akun target (biasanya yang namanya lebih lengkap: Natalia Christi)
    $targetAcc = null;
    $sourceAcc = null;

    foreach ($accounts as $a) {
        if (stripos($a['nama_lengkap'], 'Christi') !== false) {
            $targetAcc = $a;
        } else {
            $sourceAcc = $a;
        }
    }

    if (!$targetAcc) $targetAcc = $accounts[1];
    if (!$sourceAcc) $sourceAcc = $accounts[0];

    echo "ANALISIS AKUN:\n";
    echo "  - Akun Utama yang Digunakan : '{$targetAcc['nama_lengkap']}' (ID: {$targetAcc['id']})\n";
    echo "  - Akun Duplikat / Lama      : '{$sourceAcc['nama_lengkap']}' (ID: {$sourceAcc['id']})\n\n";

    $isMerge = in_array('--merge', $argv ?? []);
    if ($isMerge) {
        echo "EKSEKUSI PENGGABUNGAN KE AKUN UTAMA (ID: {$targetAcc['id']})...\n";
        $conn->begin_transaction();

        // 1. Pindahkan seluruh customer dari akun lama ke akun utama
        $stmtC = $conn->prepare("UPDATE customers SET sales_id = ? WHERE sales_id = ?");
        $stmtC->bind_param("ii", $targetAcc['id'], $sourceAcc['id']);
        $stmtC->execute();
        $movedCust = $conn->affected_rows;
        $stmtC->close();

        // 2. Pindahkan seluruh follow_ups dari akun lama ke akun utama
        $stmtF = $conn->prepare("UPDATE follow_ups SET sales_id = ? WHERE sales_id = ?");
        $stmtF->bind_param("ii", $targetAcc['id'], $sourceAcc['id']);
        $stmtF->execute();
        $movedFu = $conn->affected_rows;
        $stmtF->close();

        // 3. Nonaktifkan akun duplikat (soft delete) agar tidak muncul dobel di dropdown
        $stmtD = $conn->prepare("UPDATE sales SET deleted_at = NOW() WHERE id = ?");
        $stmtD->bind_param("ii", $sourceAcc['id']);
        $stmtD->execute();
        $stmtD->close();

        $conn->commit();

        echo "✅ SUKSES MERGE!\n";
        echo "  - {$movedCust} toko berhasil dialihkan ke {$targetAcc['nama_lengkap']}\n";
        echo "  - {$movedFu} riwayat follow up dialihkan ke {$targetAcc['nama_lengkap']}\n";
        echo "  - Akun duplikat '{$sourceAcc['nama_lengkap']}' (ID: {$sourceAcc['id']}) dinonaktifkan sehingga dropdown bersih tidak dobel lagi.\n";
        echo "=================================================================\n";
    } else {
        echo "💡 UNTUK MENGGABUNGKAN AKUN & MENGHILANGKAN NAMA DOBEL DI DROPDOWN:\n";
        echo "Jalankan perintah ini di terminal server:\n";
        echo "  php inspect_natalia_accounts.php --merge\n";
        echo "=================================================================\n";
    }
}
