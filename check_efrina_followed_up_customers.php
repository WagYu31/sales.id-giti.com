<?php
/**
 * check_efrina_followed_up_customers.php
 * Memeriksa seluruh 200+ customer yang pernah di-FU oleh Efrina Panjaitan
 * dan mengembalikan customer yang pernah di-FU Efrina kembali ke akun Efrina.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PEMERIKSAAN 200+ CUSTOMER YANG PERNAH DI-FU OLEH EFRINA       \n";
echo "=================================================================\n";
echo "Waktu Server: " . date('Y-m-d H:i:s') . "\n\n";

if (!isset($conn) || !$conn || $conn->connect_error) {
    die("Koneksi DB gagal.\n");
}

$efrinaId = 22;

// 1. Total Customer Unik yang tercatat pernah di-FU oleh Efrina di tabel follow_ups
$qTotalFu = $conn->query("
    SELECT COUNT(DISTINCT customer_id) as total_cust, COUNT(*) as total_act
    FROM follow_ups
    WHERE sales_id = {$efrinaId} AND deleted_at IS NULL
");
$fuStats = $qTotalFu->fetch_assoc();
$totalCustFu = (int)$fuStats['total_cust'];
$totalActFu = (int)$fuStats['total_act'];

echo "1. TOTAL RIWAYAT FOLLOW-UP EFRINA PANJAITAN:\n";
echo "   • Total Customer Unik Pernah Di-FU : {$totalCustFu} Customer\n";
echo "   • Total Riwayat Aktivitas Chat/FU  : {$totalActFu} Kali\n\n";

// 2. Dimana posisi customer-customer yang pernah di-FU Efrina tersebut saat ini?
$qWhereNow = $conn->query("
    SELECT 
        c.sales_id,
        COALESCE(s.nama_lengkap, 'Belum Ada Sales') as sales_name,
        COUNT(DISTINCT c.id) as cnt
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE fu.sales_id = {$efrinaId} AND fu.deleted_at IS NULL AND c.deleted_at IS NULL
    GROUP BY c.sales_id, s.nama_lengkap
    ORDER BY cnt DESC
");

echo "2. STATUS PEMEGANG CUSTOMER YANG PERNAH DI-FU EFRINA SAAT INI:\n";
$missingFromEfrina = 0;
while ($r = $qWhereNow->fetch_assoc()) {
    $flag = ((int)$r['sales_id'] === $efrinaId) ? "✓ [DI AKUN EFRINA]" : "⚠️ [TERLEPAS DI SALES LAIN]";
    if ((int)$r['sales_id'] !== $efrinaId) {
        $missingFromEfrina += (int)$r['cnt'];
    }
    echo "   • {$r['sales_name']} (ID: {$r['sales_id']}) : {$r['cnt']} customer {$flag}\n";
}
echo "\n";

echo "Ringkasan:\n";
echo "  -> Sebanyak {$missingFromEfrina} customer yang SUDAH PERNAH DI-FU EFRINA terlepas ke sales lain.\n";
echo "  -> Inilah kenapa Efrina komplain, karena hasil kerja follow-up-nya tidak muncul di akunnya!\n\n";

// 3. Rincian Kota dari Customer yang Terlepas
echo "3. KOTA DARI CUSTOMER HASIL FOLLOW-UP EFRINA YANG TERLEPAS:\n";
$qCities = $conn->query("
    SELECT 
        COALESCE(NULLIF(TRIM(ca.kota), ''), 'Tidak Terisi') as kota,
        s.nama_lengkap as pemegang_sekarang,
        COUNT(DISTINCT c.id) as cnt
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    JOIN customer_addresses ca ON c.id = ca.customer_id
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE fu.sales_id = {$efrinaId} AND c.sales_id != {$efrinaId}
      AND fu.deleted_at IS NULL AND c.deleted_at IS NULL
    GROUP BY kota, s.nama_lengkap
    ORDER BY cnt DESC
    LIMIT 20
");
while ($rc = $qCities->fetch_assoc()) {
    echo "   • {$rc['kota']} ({$rc['cnt']} toko) - saat ini dipegang: {$rc['pemegang_sekarang']}\n";
}
echo "-----------------------------------------------------------------\n\n";

// 4. Opsi Pengembalian Otomatis
$isApply = in_array('--apply', $argv ?? []);

if ($isApply) {
    echo "MENGEMBALIKAN SELURUH CUSTOMER YANG PERNAH DI-FU EFRINA KE AKUN EFRINA...\n";
    $conn->begin_transaction();

    // Update customers yang pernah di-FU Efrina agar sales_id = 22
    $sqlRestore = "
        UPDATE customers c
        SET c.sales_id = {$efrinaId}
        WHERE c.id IN (
            SELECT DISTINCT customer_id 
            FROM follow_ups 
            WHERE sales_id = {$efrinaId} AND deleted_at IS NULL
        )
        AND c.sales_id != {$efrinaId}
        AND c.deleted_at IS NULL
    ";
    $conn->query($sqlRestore);
    $affected = $conn->affected_rows;
    $conn->commit();

    $newTotal = $conn->query("SELECT COUNT(*) FROM customers WHERE sales_id = {$efrinaId} AND deleted_at IS NULL")->fetch_row()[0];
    $newSudahFu = $conn->query("
        SELECT COUNT(DISTINCT c.id) 
        FROM customers c 
        WHERE c.sales_id = {$efrinaId} 
          AND c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = {$efrinaId} AND deleted_at IS NULL)
          AND c.deleted_at IS NULL
    ")->fetch_row()[0];

    echo "✅ SUKSES BESAR! Sebanyak {$affected} customer yang pernah di-FU Efrina berhasil dikembalikan!\n";
    echo "  • Total Customer Efrina Sekarang : {$newTotal} toko\n";
    echo "  • Angka SUDAH FU Efrina Sekarang : {$newSudahFu} toko (Semua hasil kerja FU Efrina kembali utuh!)\n";
    echo "=================================================================\n";
    echo "Silakan refresh halaman CRM index.php filter Efrina Panjaitan.\n";
} else {
    echo "💡 UNTUK MENGEMBALIKAN CUSTOMER HASIL FU EFRINA SEGERA:\n";
    echo "Jalankan perintah ini di terminal server:\n";
    echo "  php check_efrina_followed_up_customers.php --apply\n";
    echo "=================================================================\n";
}
