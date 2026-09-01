<?php
/**
 * CLI HELPER TO CHECK ALL INVOICE AND TRANSACTION DATA FOR A SALES
 * Usage in terminal:
 *   php check_sales_invoices.php
 *   php check_sales_invoices.php "Edi Suprianto"
 *   php check_sales_invoices.php 3
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run via CLI terminal.");
}

require_once __DIR__ . '/includes/db.php';

$search_target = $argv[1] ?? 'Edi Suprianto';

// Find sales
if (is_numeric($search_target)) {
    $stmt = $conn->prepare("SELECT id, nama_lengkap, email, role FROM sales WHERE id = ?");
    $stmt->bind_param("i", $search_target);
} else {
    $like = '%' . $search_target . '%';
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, email, role 
        FROM sales 
        WHERE nama_lengkap LIKE ? 
        ORDER BY CASE WHEN email LIKE '%_deleted_%' THEN 1 ELSE 0 END ASC, id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $like);
}
$stmt->execute();
$sales = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sales) {
    echo "\n❌ Sales dengan pencarian '{$search_target}' tidak ditemukan di database.\n\n";
    exit(1);
}

$sales_id = (int)$sales['id'];
$sales_name = $sales['nama_lengkap'];

echo "\n========================================================================================================\n";
echo "📊 LAPORAN PENGECEKAN DATA INVOICE & TRANSAKSI DATABASE SALES\n";
echo "========================================================================================================\n";
echo "• ID Sales       : {$sales_id}\n";
echo "• Nama Lengkap   : {$sales_name}\n";
echo "• Email          : {$sales['email']}\n";
echo "• Role Akun      : {$sales['role']}\n";
echo "========================================================================================================\n\n";

// --- 1. PERIODE PROGRAM LOMBA (1 AGUSTUS 2026 - 31 OKTOBER 2026) ---
echo "🏆 [BAGIAN 1] TRANSAKSI PERIODE LOMBA 3 BULAN (01 AGT 2026 s/d 31 OKT 2026):\n";
echo str_repeat("-", 104) . "\n";
echo sprintf("%-4s | %-28s | %-18s | %-16s | %-16s | %-10s\n", "NO", "NAMA TOKO", "NO INVOICE", "NOMINAL OMSET", "TGL TRANSAKSI", "KATEGORI");
echo str_repeat("-", 104) . "\n";

$sql_program = "
    SELECT 
        fu.id AS fu_id,
        fu.no_inv,
        fu.nominal_invoice,
        fu.tgl_follow_up,
        c.id AS customer_id,
        c.nama_toko,
        c.tgl_input,
        CASE 
            WHEN (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01') THEN 'Akuisisi Baru'
            ELSE 'Reaktivasi Lama'
        END AS kategori_program
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE fu.sales_id = {$sales_id}
      AND fu.deleted_at IS NULL
      AND fu.no_inv IS NOT NULL AND TRIM(fu.no_inv) != ''
      AND DATE(fu.tgl_follow_up) BETWEEN '2026-08-01' AND '2026-10-31'
    ORDER BY fu.tgl_follow_up ASC
";

$res_prog = $conn->query($sql_program);
$no_p = 0;
$total_omset_p = 0;
$total_baru = 0;
$total_reaktivasi = 0;

if ($res_prog && $res_prog->num_rows > 0) {
    while ($r = $res_prog->fetch_assoc()) {
        $no_p++;
        $nom = (float)$r['nominal_invoice'];
        $total_omset_p += $nom;
        if ($r['kategori_program'] === 'Akuisisi Baru') {
            $total_baru += $nom;
        } else {
            $total_reaktivasi += $nom;
        }
        echo sprintf(
            "%-4d | %-28s | %-18s | Rp %-13s | %-16s | %-10s\n",
            $no_p,
            substr($r['nama_toko'], 0, 28),
            $r['no_inv'],
            number_format($nom, 0, ',', '.'),
            substr($r['tgl_follow_up'], 0, 16),
            $r['kategori_program']
        );
    }
} else {
    echo "  (Tidak ada transaksi invoice pada rentang tanggal 1 Agt - 31 Okt 2026)\n";
}

echo str_repeat("-", 104) . "\n";
echo "• TOTAL TRANSAKSI LOMBA   : {$no_p} Transaksi\n";
echo "• OMSET AKUISISI BARU     : Rp " . number_format($total_baru, 0, ',', '.') . "\n";
echo "• OMSET REAKTIVASI LAMA   : Rp " . number_format($total_reaktivasi, 0, ',', '.') . "\n";
echo "• TOTAL AKUMULASI OMSET   : Rp " . number_format($total_omset_p, 0, ',', '.') . "\n\n";

// --- 2. SEMUA TRANSAKSI SEPANJANG WAKTU (ALL-TIME HISTORY) ---
echo "🗓️ [BAGIAN 2] SEMUA RIWAYAT TRANSAKSI INVOICE SEPANJANG WAKTU (ALL-TIME):\n";
echo str_repeat("-", 104) . "\n";
echo sprintf("%-4s | %-28s | %-18s | %-16s | %-19s | %-6s\n", "NO", "NAMA TOKO", "NO INVOICE", "NOMINAL OMSET", "TGL TRANSAKSI", "STATUS");
echo str_repeat("-", 104) . "\n";

$sql_all = "
    SELECT 
        fu.id AS fu_id,
        fu.no_inv,
        fu.nominal_invoice,
        fu.tgl_follow_up,
        c.nama_toko,
        fu.deleted_at
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    WHERE fu.sales_id = {$sales_id}
      AND fu.no_inv IS NOT NULL AND TRIM(fu.no_inv) != ''
    ORDER BY fu.tgl_follow_up ASC
";

$res_all = $conn->query($sql_all);
$no_a = 0;
$total_omset_a = 0;

if ($res_all && $res_all->num_rows > 0) {
    while ($r = $res_all->fetch_assoc()) {
        $no_a++;
        $nom = (float)$r['nominal_invoice'];
        $status_label = ($r['deleted_at'] !== null) ? 'Dihapus' : 'Aktif';
        if ($r['deleted_at'] === null) {
            $total_omset_a += $nom;
        }
        echo sprintf(
            "%-4d | %-28s | %-18s | Rp %-13s | %-19s | %-6s\n",
            $no_a,
            substr($r['nama_toko'], 0, 28),
            $r['no_inv'],
            number_format($nom, 0, ',', '.'),
            $r['tgl_follow_up'],
            $status_label
        );
    }
} else {
    echo "  (Tidak ada riwayat invoice di database)\n";
}

echo str_repeat("-", 104) . "\n";
echo "• TOTAL SELURUH TRANSAKSI : {$no_a} Invoice\n";
echo "• TOTAL OMSET ALL-TIME    : Rp " . number_format($total_omset_a, 0, ',', '.') . "\n";
echo "========================================================================================================\n\n";
