<?php
/**
 * EXPORT EXCEL LAPORAN TRANSAKSI PENJUALAN & INVOICE PROGRAM SALES
 * DILENGKAPI DENGAN OTORISASI & PROTEKSI PERIZINAN SUPER ADMIN
 */

require_once __DIR__ . '/includes/db.php';

$vendor_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'sales'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
$current_user_name = $_SESSION['nama_lengkap'] ?? 'User';

$target_sales_id = intval($_GET['sales_id'] ?? 0);
$selected_bulan = trim($_GET['periode_bulan'] ?? '8-10');

if ($target_sales_id <= 0) {
    die("Parameter Sales ID tidak valid.");
}

// -------------------------------------------------------------
// CEK PERIZINAN SUPER ADMIN UNTUK ROLE SALES
// -------------------------------------------------------------
$is_authorized = false;

if ($current_user_role === 'superadmin') {
    $is_authorized = true;
} else {
    // Cek apakah ada izin Approved di tabel download_requests
    $stmt_perm = $conn->prepare("
        SELECT id, status, created_at 
        FROM download_requests 
        WHERE sales_id = ? 
          AND status = 'Approved'
          AND (
              (request_type = 'bonus_sales_detail' AND (target_sales_id = ? OR target_sales_id IS NULL))
              OR request_type = 'customer_list'
          )
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt_perm->bind_param('ii', $current_user_id, $target_sales_id);
    $stmt_perm->execute();
    $res_perm = $stmt_perm->get_result();

    if ($res_perm && $res_perm->num_rows > 0) {
        $perm_data = $res_perm->fetch_assoc();
        // Izin valid jika masih dalam 72 jam
        $perm_time = strtotime($perm_data['created_at']);
        if ((time() - $perm_time) <= (72 * 3600)) {
            $is_authorized = true;
        }
    }
    $stmt_perm->close();

    // Cek one-time authorization token dari session jika baru diverifikasi
    if (isset($_SESSION['temp_export_auth_' . $target_sales_id]) && $_SESSION['temp_export_auth_' . $target_sales_id] === true) {
        $is_authorized = true;
    }
}

if (!$is_authorized) {
    die("
    <div style='font-family: sans-serif; text-align: center; padding: 50px;'>
        <div style='font-size: 48px; margin-bottom: 10px;'>🔒</div>
        <h2 style='color: #DC2626;'>Akses Export Excel Dibatasi</h2>
        <p style='color: #4B5563; max-width: 500px; margin: 0 auto 20px;'>
            Fitur Export Excel memerlukan <strong>Persetujuan Resmi dari Super Admin</strong>. 
            Silakan ajukan izin unduh terlebih dahulu melalui tombol di aplikasi atau hubungi Super Admin.
        </p>
        <button onclick='window.close();' style='padding: 10px 24px; background: #2563EB; color: white; border: none; border-radius: 20px; cursor: pointer; font-weight: bold;'>Tutup</button>
    </div>
    ");
}

// -------------------------------------------------------------
// AMBIL DATA SALES & FILTER PERIODE
// -------------------------------------------------------------
$stmt_sales = $conn->prepare("SELECT id, nama_lengkap, username, role FROM sales WHERE id = ?");
$stmt_sales->bind_param('i', $target_sales_id);
$stmt_sales->execute();
$res_s = $stmt_sales->get_result();
$sales = $res_s->fetch_assoc();
$stmt_sales->close();

if (!$sales) {
    die("Data sales tidak ditemukan.");
}

$label_periode = 'Periode 3 Bulan (Agt - Okt 2026)';
$where_date_fu = "AND DATE(fu.tgl_follow_up) BETWEEN '2026-08-01' AND '2026-10-31'";

if ($selected_bulan === '8') {
    $where_date_fu = "AND DATE(fu.tgl_follow_up) BETWEEN '2026-08-01' AND '2026-08-31'";
    $label_periode = 'Bulan 8 (Agustus 2026)';
} else if ($selected_bulan === '9') {
    $where_date_fu = "AND DATE(fu.tgl_follow_up) BETWEEN '2026-09-01' AND '2026-09-30'";
    $label_periode = 'Bulan 9 (September 2026)';
} else if ($selected_bulan === '10') {
    $where_date_fu = "AND DATE(fu.tgl_follow_up) BETWEEN '2026-10-01' AND '2026-10-31'";
    $label_periode = 'Bulan 10 (Oktober 2026)';
} else if ($selected_bulan === 'all_time') {
    $where_date_fu = "";
    $label_periode = 'Semua Waktu';
}

// Query data transaksi yang valid sesuai aturan program
$sql_all = "
    SELECT 
        fu.id AS followup_id,
        fu.tgl_follow_up,
        fu.no_inv,
        fu.nominal_invoice,
        fu.sales_id,
        fu.keterangan AS catatan,
        c.id AS customer_id,
        c.nama_toko AS nama_customer,
        c.tgl_input AS tgl_input_cust,
        (SELECT MAX(fu_prev.tgl_follow_up)
         FROM follow_ups fu_prev
         WHERE fu_prev.customer_id = c.id
           AND fu_prev.deleted_at IS NULL
           AND fu_prev.no_inv IS NOT NULL AND fu_prev.no_inv != ''
           AND fu_prev.tgl_follow_up < '2026-08-01 00:00:00'
        ) AS tgl_terakhir_beli_lama,
        (SELECT cp.tlp_pic FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL LIMIT 1) AS no_hp,
        CASE 
            WHEN (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01') THEN 'A'
            ELSE 'B'
        END AS kat_type
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE fu.deleted_at IS NULL
      AND fu.sales_id = {$target_sales_id}
      AND fu.no_inv IS NOT NULL 
      AND fu.no_inv != ''
      AND (
          -- Kategori A: Customer Baru
          (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01')
          OR 
          -- Kategori B: Customer Lama Reaktivasi (Terakhir belanja <= bln 5, tidak belanja bln 6-7)
          ((c.tgl_input IS NULL OR c.tgl_input <= '2026-05-31')
           AND NOT EXISTS (
               SELECT 1 FROM follow_ups fu_mid 
               WHERE fu_mid.customer_id = c.id 
                 AND fu_mid.deleted_at IS NULL 
                 AND fu_mid.no_inv IS NOT NULL AND fu_mid.no_inv != '' 
                 AND fu_mid.tgl_follow_up >= '2026-06-01 00:00:00' 
                 AND fu_mid.tgl_follow_up <= '2026-07-31 23:59:59'
           ))
      )
      {$where_date_fu}
    ORDER BY fu.tgl_follow_up DESC
";

$res_all = $conn->query($sql_all);
$items = [];
$omset_a = 0;
$omset_b = 0;
$cust_seen_a = [];
$cust_seen_b = [];

if ($res_all) {
    while ($r = $res_all->fetch_assoc()) {
        $items[] = $r;
        $nom = (float)($r['nominal_invoice'] ?? 0);
        if ($r['kat_type'] === 'A') {
            $omset_a += $nom;
            if (!in_array($r['customer_id'], $cust_seen_a)) $cust_seen_a[] = $r['customer_id'];
        } else {
            $omset_b += $nom;
            if (!in_array($r['customer_id'], $cust_seen_b)) $cust_seen_b[] = $r['customer_id'];
        }
    }
}

$total_omset_combined = $omset_a + $omset_b;
$total_mitra_a = count($cust_seen_a);
$total_mitra_b = count($cust_seen_b);
$total_mitra_combined = count(array_unique(array_merge($cust_seen_a, $cust_seen_b)));

// -------------------------------------------------------------
// GENERATE EXCEL USING PHPSPREADSHEET
// -------------------------------------------------------------
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    // Fallback ke CSV jika PhpSpreadsheet belum terpasang
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Bonus_' . preg_replace('/[^A-Za-z0-9_]/', '_', $sales['nama_lengkap']) . '_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['NO', 'KATEGORI', 'TGL INPUT CUST', 'TERAKHIR BELI SEBELUMNYA', 'STATUS PROGRAM', 'CUSTOMER', 'NO TELEPON', 'NO INVOICE', 'TGL TRANSAKSI', 'NOMINAL OMSET (RP)']);
    foreach ($items as $idx => $r) {
        fputcsv($output, [
            $idx + 1,
            ($r['kat_type'] === 'A') ? 'Akuisisi Baru' : 'Reaktivasi Lama',
            !empty($r['tgl_input_cust']) ? date('d/m/Y', strtotime($r['tgl_input_cust'])) : '<= Mei 2026',
            !empty($r['tgl_terakhir_beli_lama']) ? date('d/m/Y', strtotime($r['tgl_terakhir_beli_lama'])) : 'Belum Ada Belanja s/d Mei',
            ($r['kat_type'] === 'A') ? 'Transaksi Perdana' : 'Dorman Bln 6 & 7',
            $r['nama_customer'],
            $r['no_hp'] ?? '-',
            $r['no_inv'],
            date('d/m/Y H:i', strtotime($r['tgl_follow_up'])),
            $r['nominal_invoice']
        ]);
    }
    fclose($output);
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Transaksi Sales');

// Matikan gridlines default jika diinginkan, tapi biarkan aktif untuk kerapian
$sheet->setShowGridLines(true);

// 1. HEADER BANNER PERUSAHAAN
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A1', 'PT. LOEWIX INDONESIA - LAPORAN TRANSAKSI PROGRAM PROGRAM 3 BULAN');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0F172A'); // Dark Slate Navy
$sheet->getRowDimension(1)->setRowHeight(32);

// 2. INFO SALES & METADATA
$sheet->setCellValue('A3', 'Nama Sales:');
$sheet->setCellValue('B3', $sales['nama_lengkap']);
$sheet->setCellValue('A4', 'Periode Program:');
$sheet->setCellValue('B4', $label_periode);

$sheet->setCellValue('F3', 'Dicetak Oleh:');
$sheet->setCellValue('G3', $current_user_name . ' (' . ucfirst($current_user_role) . ')');
$sheet->setCellValue('F4', 'Tanggal Ekspor:');
$sheet->setCellValue('G4', date('d F Y, H:i') . ' WIB');

$sheet->getStyle('A3:A4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
$sheet->getStyle('B3:B4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));
$sheet->getStyle('F3:F4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
$sheet->getStyle('G3:G4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));

// 3. KPI SUMMARY CARDS BOX
$sheet->mergeCells('A6:C6');
$sheet->setCellValue('A6', '🚀 AKUISISI CUSTOMER BARU');
$sheet->mergeCells('A7:C7');
$sheet->setCellValue('A7', 'Rp ' . number_format($omset_a, 0, ',', '.') . ' (' . $total_mitra_a . ' Mitra Baru)');

$sheet->mergeCells('D6:F6');
$sheet->setCellValue('D6', '🔥 REAKTIVASI PORTOFOLIO LAMA');
$sheet->mergeCells('D7:F7');
$sheet->setCellValue('D7', 'Rp ' . number_format($omset_b, 0, ',', '.') . ' (' . $total_mitra_b . ' Mitra Transaksi)');

$sheet->mergeCells('G6:J6');
$sheet->setCellValue('G6', '🏆 TOTAL AKUMULASI OMSET');
$sheet->mergeCells('G7:J7');
$sheet->setCellValue('G7', 'Rp ' . number_format($total_omset_combined, 0, ',', '.') . ' (' . $total_mitra_combined . ' Total Mitra)');

$sheet->getStyle('A6:C6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('DBEAFE'); // Light blue
$sheet->getStyle('A6:C6')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E40AF'));
$sheet->getStyle('A7:C7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('EFF6FF');
$sheet->getStyle('A7:C7')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));

$sheet->getStyle('D6:F6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FEF3C7'); // Light yellow
$sheet->getStyle('D6:F6')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('92400E'));
$sheet->getStyle('D7:F7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFBEB');
$sheet->getStyle('D7:F7')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('78350F'));

$sheet->getStyle('G6:J6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D1FAE5'); // Light green
$sheet->getStyle('G6:J6')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('065F46'));
$sheet->getStyle('G7:J7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('ECFDF5');
$sheet->getStyle('G7:J7')->getFont()->setBold(true)->setSize(12)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('047857'));

$sheet->getStyle('A6:J7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A6:J7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('CBD5E1'));

// 4. TABEL TRANSAKSI HEADERS
$headers = [
    'A9' => 'NO',
    'B9' => 'KATEGORI',
    'C9' => 'TGL INPUT CUSTOMER',
    'D9' => 'TERAKHIR BELI SEBELUMNYA',
    'E9' => 'STATUS PROGRAM',
    'F9' => 'CUSTOMER / TOKO',
    'G9' => 'NO TELEPON PIC',
    'H9' => 'NO. INVOICE',
    'I9' => 'TANGGAL TRANSAKSI',
    'J9' => 'NOMINAL OMSET (RP)'
];

foreach ($headers as $cell => $text) {
    $sheet->setCellValue($cell, $text);
}

$sheet->getStyle('A9:J9')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
$sheet->getStyle('A9:J9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('1E293B'); // Slate 800
$sheet->getStyle('A9:J9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(9)->setRowHeight(26);

// 5. ISI DATA TRANSAKSI
$row_num = 10;
foreach ($items as $idx => $r) {
    $sheet->setCellValue('A' . $row_num, $idx + 1);
    
    $is_a = ($r['kat_type'] === 'A');
    $sheet->setCellValue('B' . $row_num, $is_a ? '🚀 Akuisisi Baru' : '🔥 Reaktivasi Lama');
    
    $sheet->setCellValue('C' . $row_num, !empty($r['tgl_input_cust']) ? date('d M Y', strtotime($r['tgl_input_cust'])) : '<= Mei 2026');
    $sheet->setCellValue('D' . $row_num, !empty($r['tgl_terakhir_beli_lama']) ? date('d M Y', strtotime($r['tgl_terakhir_beli_lama'])) : 'Belum Ada Belanja s/d Mei');
    $sheet->setCellValue('E' . $row_num, $is_a ? '✨ Transaksi Perdana' : '✅ Dorman di Bln 6 & 7');
    
    $sheet->setCellValue('F' . $row_num, $r['nama_customer']);
    $sheet->setCellValue('G' . $row_num, !empty($r['no_hp']) ? $r['no_hp'] : '-');
    $sheet->setCellValue('H' . $row_num, $r['no_inv']);
    $sheet->setCellValue('I' . $row_num, date('d M Y H:i', strtotime($r['tgl_follow_up'])) . ' WIB');
    
    $nom = (float)($r['nominal_invoice'] ?? 0);
    $sheet->setCellValue('J' . $row_num, $nom);

    // Styling per baris
    $sheet->getStyle('A' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('I' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('J' . $row_num)->getNumberFormat()->setFormatCode('"Rp "#,##0');
    $sheet->getStyle('J' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Zebra striping
    if ($row_num % 2 == 1) {
        $sheet->getStyle('A' . $row_num . ':J' . $row_num)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F8FAFC');
    }

    $row_num++;
}

// 6. TOTAL FOOTER ROW
$sheet->mergeCells('A' . $row_num . ':I' . $row_num);
$sheet->setCellValue('A' . $row_num, 'TOTAL AKUMULASI OMSET PENJUALAN:');
$sheet->setCellValue('J' . $row_num, '=SUM(J10:J' . ($row_num - 1) . ')');

$sheet->getStyle('A' . $row_num . ':J' . $row_num)->getFont()->setBold(true)->setSize(11);
$sheet->getStyle('A' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('J' . $row_num)->getNumberFormat()->setFormatCode('"Rp "#,##0');
$sheet->getStyle('A' . $row_num . ':J' . $row_num)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2E8F0');
$sheet->getRowDimension($row_num)->setRowHeight(24);

// Border tabel
$sheet->getStyle('A9:J' . $row_num)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('CBD5E1'));

// Auto-size columns
foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Clear output buffer sebelum kirim file
if (ob_get_length()) {
    ob_end_clean();
}

$safe_sales_name = preg_replace('/[^A-Za-z0-9_]/', '_', $sales['nama_lengkap']);
$filename = "Laporan_Program_Sales_{$safe_sales_name}_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
