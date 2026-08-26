<?php
/**
 * EXPORT EXCEL LAPORAN FOLLOW UP SALES
 * PT. LOEWIX INDONESIA - SALES MANAGEMENT SYSTEM
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
use PhpOffice\PhpSpreadsheet\Style\Color;

// -------------------------------------------------------------
// 1. CEK OTORISASI & AKSES USER
// -------------------------------------------------------------
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$current_user_id   = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
$current_user_name = $_SESSION['nama_lengkap'] ?? 'Super Admin';

// Hanya role yang memiliki hak akses
if (!in_array($current_user_role, ['superadmin', 'admin', 'sales'])) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengunduh laporan ini.");
}

// -------------------------------------------------------------
// 2. TANGKAP PARAMETER FILTER DARI GET
// -------------------------------------------------------------
$tgl_mulai         = trim($_GET['tgl_mulai'] ?? '');
$tgl_akhir         = trim($_GET['tgl_akhir'] ?? '');
$selected_sales_id = isset($_GET['sales_id']) && is_numeric($_GET['sales_id']) ? (int)$_GET['sales_id'] : '';
$search_keyword    = trim($_GET['search'] ?? '');
$respon_filter     = trim($_GET['respon'] ?? '');
$status_filter     = trim($_GET['status'] ?? '');
$sort_by           = trim($_GET['sort_by'] ?? 'tgl_follow_up');
$sort_dir          = in_array(strtoupper(trim($_GET['sort_dir'] ?? 'DESC')), ['ASC', 'DESC']) ? strtoupper(trim($_GET['sort_dir'] ?? 'DESC')) : 'DESC';

// Jika role sales, batasi data hanya miliknya kecuali jika superadmin
if ($current_user_role === 'sales') {
    $selected_sales_id = $current_user_id;
}

$allowed_sort_columns = [
    'tgl_follow_up' => 'fu.tgl_follow_up',
    'nama_toko'     => 'c.nama_toko',
    'nama_sales'    => 's.nama_lengkap',
    'respon'        => 'fu.respon',
    'no_inv'        => 'fu.no_inv'
];
$order_col = $allowed_sort_columns[$sort_by] ?? 'fu.tgl_follow_up';

// -------------------------------------------------------------
// 3. BANGUN QUERY FILTER
// -------------------------------------------------------------
$conditions = ["fu.deleted_at IS NULL"];
$params     = [];
$types      = '';

// Filter Tanggal
if ($tgl_mulai && $tgl_akhir) {
    $conditions[] = "DATE(fu.tgl_follow_up) BETWEEN ? AND ?";
    array_push($params, $tgl_mulai, $tgl_akhir);
    $types .= 'ss';
    $label_periode = date('d/m/Y', strtotime($tgl_mulai)) . " s/d " . date('d/m/Y', strtotime($tgl_akhir));
} elseif ($tgl_mulai) {
    $conditions[] = "DATE(fu.tgl_follow_up) >= ?";
    array_push($params, $tgl_mulai);
    $types .= 's';
    $label_periode = "Mulai " . date('d/m/Y', strtotime($tgl_mulai));
} elseif ($tgl_akhir) {
    $conditions[] = "DATE(fu.tgl_follow_up) <= ?";
    array_push($params, $tgl_akhir);
    $types .= 's';
    $label_periode = "Sampai " . date('d/m/Y', strtotime($tgl_akhir));
} else {
    $label_periode = "Semua Waktu";
}

// Filter Sales
$label_sales = "Semua Sales";
if ($selected_sales_id) {
    $conditions[] = "fu.sales_id = ?";
    $params[] = $selected_sales_id;
    $types .= 'i';

    $stmt_s = $conn->prepare("SELECT nama_lengkap FROM sales WHERE id = ?");
    if ($stmt_s) {
        $stmt_s->bind_param('i', $selected_sales_id);
        $stmt_s->execute();
        $res_s = $stmt_s->get_result();
        if ($res_s && $row_s = $res_s->fetch_assoc()) {
            $label_sales = $row_s['nama_lengkap'];
        }
        $stmt_s->close();
    }
}

// Filter Respon
$label_respon = "Semua Respon";
if ($respon_filter) {
    if ($respon_filter === 'info') {
        $conditions[] = "(LOWER(fu.respon) LIKE '%informasi%' OR LOWER(fu.respon) LIKE '%menginformasikan%')";
        $label_respon = "Info Customer";
    } elseif ($respon_filter === 'no_respon') {
        $conditions[] = "(fu.respon LIKE '%Tidak ada respon%' OR fu.respon LIKE '%Tidak tertarik%')";
        $label_respon = "Tidak Ada Respon / Tertarik";
    } else {
        $conditions[] = "fu.respon = ?";
        $params[] = $respon_filter;
        $types .= 's';
        $label_respon = $respon_filter;
    }
}

// Filter Status Customer
$label_status = "Semua Status";
if ($status_filter) {
    if ($status_filter === 'acc_boss') {
        $conditions[] = "c.acc_boss = 'Y'";
        $label_status = "Acc Boss";
    } elseif ($status_filter === 'potensial') {
        $conditions[] = "c.potensial = 'Y'";
        $label_status = "Potensial";
    } elseif ($status_filter === 'kandidat') {
        $conditions[] = "c.kandidat = 'Y'";
        $label_status = "Kandidat";
    }
}

// Filter Search Keyword
if ($search_keyword) {
    $conditions[] = "(c.nama_toko LIKE ? OR fu.keterangan LIKE ? OR fu.no_inv LIKE ?)";
    $like_search = '%' . $search_keyword . '%';
    array_push($params, $like_search, $like_search, $like_search);
    $types .= 'sss';
}

$where_clause = " WHERE " . implode(' AND ', $conditions);

// -------------------------------------------------------------
// 4. EKSEKUSI QUERY DATA FOLLOW UP
// -------------------------------------------------------------
$sql = "
    SELECT 
        fu.id, 
        fu.customer_id, 
        fu.tgl_follow_up, 
        fu.respon, 
        fu.keterangan, 
        fu.no_inv,
        fu.nominal_invoice,
        fu.media1, 
        fu.media2, 
        fu.media3, 
        fu.sales_id,
        s.nama_lengkap AS nama_sales_fu,
        c.nama_toko, 
        c.kategori AS kategori_customer,
        c.kandidat, 
        c.potensial, 
        c.acc_boss, 
        c.acc_boss_note,
        ca.kota,
        ca.alamat,
        ca.provinsi,
        cp.all_pics,
        cp.all_phones
    FROM follow_ups fu
    JOIN sales s ON fu.sales_id = s.id
    JOIN customers c ON fu.customer_id = c.id
    LEFT JOIN (
        SELECT customer_id, MIN(kota) AS kota, MIN(alamat) AS alamat, MIN(provinsi) AS provinsi
        FROM customer_addresses 
        WHERE deleted_at IS NULL 
        GROUP BY customer_id
    ) ca ON c.id = ca.customer_id
    LEFT JOIN (
        SELECT customer_id, 
               GROUP_CONCAT(DISTINCT nama_pic SEPARATOR ', ') AS all_pics,
               GROUP_CONCAT(DISTINCT tlp_pic SEPARATOR ', ') AS all_phones
        FROM customer_pics 
        WHERE deleted_at IS NULL 
        GROUP BY customer_id
    ) cp ON c.id = cp.customer_id
    {$where_clause}
    ORDER BY {$order_col} {$sort_dir}
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$items = [];
$total_nominal_deal = 0;
$total_deal_count   = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
        $nom = (float)($row['nominal_invoice'] ?? 0);
        $total_nominal_deal += $nom;
        if (!empty($row['no_inv']) || stripos($row['respon'] ?? '', 'Deal') !== false) {
            $total_deal_count++;
        }
    }
}
$stmt->close();
$total_data = count($items);

// -------------------------------------------------------------
// 5. GENERATE FILE EXCEL MENGGUNAKAN PHPSPREADSHEET
// -------------------------------------------------------------
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    // Fallback CSV jika library belum terpasang
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Laporan_Follow_Up_Sales_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['NO', 'TANGGAL', 'WAKTU', 'SALES', 'CUSTOMER / TOKO', 'KATEGORI', 'STATUS CUSTOMER', 'KOTA', 'ALAMAT', 'PIC', 'TELEPON', 'RESPON FOLLOW UP', 'CATATAN / KETERANGAN', 'NO INVOICE', 'NOMINAL (RP)', 'BUKTI MEDIA']);
    
    foreach ($items as $idx => $r) {
        $status_tags = [];
        if ($r['acc_boss'] === 'Y') $status_tags[] = 'Acc Boss';
        if ($r['potensial'] === 'Y') $status_tags[] = 'Potensial';
        if ($r['kandidat'] === 'Y') $status_tags[] = 'Kandidat';
        $status_str = !empty($status_tags) ? implode(', ', $status_tags) : 'Reguler';

        $media_arr = array_filter([$r['media1'], $r['media2'], $r['media3']]);
        $media_str = !empty($media_arr) ? implode(', ', $media_arr) : '-';

        fputcsv($output, [
            $idx + 1,
            date('d/m/Y', strtotime($r['tgl_follow_up'])),
            date('H:i', strtotime($r['tgl_follow_up'])) . ' WIB',
            $r['nama_sales_fu'],
            $r['nama_toko'],
            $r['kategori_customer'] ?? '-',
            $status_str,
            $r['kota'] ?? '-',
            $r['alamat'] ?? '-',
            $r['all_pics'] ?? '-',
            $r['all_phones'] ?? '-',
            $r['respon'] ?? '-',
            $r['keterangan'] ?? '-',
            $r['no_inv'] ?? '-',
            $r['nominal_invoice'] ?? 0,
            $media_str
        ]);
    }
    fclose($output);
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Follow Up Sales');

// Aktifkan tampilan Gridlines
$sheet->setShowGridLines(true);

// -------------------------------------------------------------
// A. CORPORATE BRANDING HEADER (BARIS 1-2)
// -------------------------------------------------------------
$sheet->mergeCells('A1:P1');
$sheet->setCellValue('A1', 'PT. LOEWIX INDONESIA — LAPORAN REKAPITULASI FOLLOW UP & AKTIVITAS SALES');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->setColor(new Color('FFFFFF'));
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0F172A'); // Dark Navy 900
$sheet->getRowDimension(1)->setRowHeight(34);

$sheet->mergeCells('A2:P2');
$sheet->setCellValue('A2', 'Official Surveillance & Security Intelligent Management System — sales.id-giti.com');
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9.5)->setColor(new Color('94A3B8'));
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('1E293B');
$sheet->getRowDimension(2)->setRowHeight(20);

// -------------------------------------------------------------
// B. METADATA FILTER & INFORMASI EXPORT (BARIS 4-6)
// -------------------------------------------------------------
$sheet->setCellValue('A4', 'Filter Periode:');
$sheet->setCellValue('B4', $label_periode);
$sheet->setCellValue('A5', 'Filter Sales:');
$sheet->setCellValue('B5', $label_sales);
$sheet->setCellValue('A6', 'Filter Respon:');
$sheet->setCellValue('B6', $label_respon);

$sheet->setCellValue('F4', 'Filter Status Customer:');
$sheet->setCellValue('G4', $label_status);
$sheet->setCellValue('F5', 'Kata Kunci Pencarian:');
$sheet->setCellValue('G5', !empty($search_keyword) ? $search_keyword : '(Semua Data)');
$sheet->setCellValue('F6', 'Total Data Ditemukan:');
$sheet->setCellValue('G6', number_format($total_data) . ' Baris Aktivitas Follow Up');

$sheet->setCellValue('L4', 'Tanggal Export:');
$sheet->setCellValue('M4', date('d F Y, H:i') . ' WIB');
$sheet->setCellValue('L5', 'Diexport Oleh:');
$sheet->setCellValue('M5', $current_user_name . ' (' . ucfirst($current_user_role) . ')');
$sheet->setCellValue('L6', 'Total Nominal Deal:');
$sheet->setCellValue('M6', $total_nominal_deal);

// Styling block metadata
$sheet->getStyle('A4:A6')->getFont()->setBold(true)->setSize(9.5)->setColor(new Color('475569'));
$sheet->getStyle('F4:F6')->getFont()->setBold(true)->setSize(9.5)->setColor(new Color('475569'));
$sheet->getStyle('L4:L6')->getFont()->setBold(true)->setSize(9.5)->setColor(new Color('475569'));
$sheet->getStyle('B4:B6')->getFont()->setSize(9.5)->setColor(new Color('0F172A'));
$sheet->getStyle('G4:G6')->getFont()->setSize(9.5)->setColor(new Color('0F172A'));
$sheet->getStyle('M4:M6')->getFont()->setSize(9.5)->setColor(new Color('0F172A'));
$sheet->getStyle('M6')->getNumberFormat()->setFormatCode('"Rp "#,##0');
$sheet->getStyle('M6')->getFont()->setBold(true)->setColor(new Color('059669'));

// -------------------------------------------------------------
// C. TABLE HEADERS (BARIS 8)
// -------------------------------------------------------------
$headers = [
    'A' => 'NO',
    'B' => 'TANGGAL',
    'C' => 'WAKTU',
    'D' => 'SALES AGENT',
    'E' => 'NAMA CUSTOMER / TOKO',
    'F' => 'KATEGORI',
    'G' => 'STATUS',
    'H' => 'KOTA',
    'I' => 'ALAMAT LENGKAP',
    'J' => 'NAMA PIC',
    'K' => 'NO. TELEPON / WA',
    'L' => 'RESPON FOLLOW UP',
    'M' => 'CATATAN / KETERANGAN FOLLOW UP',
    'N' => 'NO. INVOICE',
    'O' => 'NOMINAL (RP)',
    'P' => 'BUKTI / MEDIA'
];

foreach ($headers as $col => $title) {
    $sheet->setCellValue($col . '8', $title);
}

$sheet->getStyle('A8:P8')->getFont()->setBold(true)->setSize(10)->setColor(new Color('FFFFFF'));
$sheet->getStyle('A8:P8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A8:P8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('1E3A8A'); // Royal Blue Header
$sheet->getRowDimension(8)->setRowHeight(28);

// -------------------------------------------------------------
// D. ISI DATA ROWS (BARIS 9 DST)
// -------------------------------------------------------------
$row_num = 9;

foreach ($items as $idx => $r) {
    $status_tags = [];
    if ($r['acc_boss'] === 'Y') $status_tags[] = 'Acc Boss';
    if ($r['potensial'] === 'Y') $status_tags[] = 'Potensial';
    if ($r['kandidat'] === 'Y') $status_tags[] = 'Kandidat';
    $status_str = !empty($status_tags) ? implode(', ', $status_tags) : 'Reguler';

    $media_arr = array_filter([$r['media1'], $r['media2'], $r['media3']]);
    $media_str = !empty($media_arr) ? implode(', ', $media_arr) : '-';

    $tgl_dt = !empty($r['tgl_follow_up']) ? strtotime($r['tgl_follow_up']) : time();
    $nom_val = (float)($r['nominal_invoice'] ?? 0);

    $sheet->setCellValue('A' . $row_num, $idx + 1);
    $sheet->setCellValue('B' . $row_num, date('d/m/Y', $tgl_dt));
    $sheet->setCellValue('C' . $row_num, date('H:i', $tgl_dt) . ' WIB');
    $sheet->setCellValue('D' . $row_num, $r['nama_sales_fu'] ?? '-');
    $sheet->setCellValue('E' . $row_num, $r['nama_toko'] ?? '-');
    $sheet->setCellValue('F' . $row_num, strtoupper($r['kategori_customer'] ?? '-'));
    $sheet->setCellValue('G' . $row_num, $status_str);
    $sheet->setCellValue('H' . $row_num, $r['kota'] ?? '-');
    $sheet->setCellValue('I' . $row_num, $r['alamat'] ?? '-');
    $sheet->setCellValue('J' . $row_num, $r['all_pics'] ?? '-');
    $sheet->setCellValue('K' . $row_num, $r['all_phones'] ?? '-');
    $sheet->setCellValue('L' . $row_num, $r['respon'] ?? '-');
    $sheet->setCellValue('M' . $row_num, $r['keterangan'] ?? '-');
    $sheet->setCellValue('N' . $row_num, !empty($r['no_inv']) ? $r['no_inv'] : '-');
    $sheet->setCellValue('O' . $row_num, $nom_val);
    $sheet->setCellValue('P' . $row_num, $media_str);

    // Format & Alignments
    $sheet->getStyle('A' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('E' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
    $sheet->getStyle('F' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('I' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
    $sheet->getStyle('J' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('K' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('L' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
    $sheet->getStyle('M' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
    $sheet->getStyle('N' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('O' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('P' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);

    $sheet->getStyle('O' . $row_num)->getNumberFormat()->setFormatCode('"Rp "#,##0');

    // Zebra Row Striping
    if ($idx % 2 === 1) {
        $sheet->getStyle('A' . $row_num . ':P' . $row_num)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F8FAFC');
    }

    // Highlight jika respon = Deal
    if (!empty($r['no_inv']) || stripos($r['respon'] ?? '', 'Deal') !== false) {
        $sheet->getStyle('L' . $row_num)->getFont()->setBold(true)->setColor(new Color('059669'));
        $sheet->getStyle('N' . $row_num)->getFont()->setBold(true)->setColor(new Color('1D4ED8'));
        $sheet->getStyle('O' . $row_num)->getFont()->setBold(true)->setColor(new Color('059669'));
    }

    $row_num++;
}

// -------------------------------------------------------------
// E. TOTAL / FOOTER SUMMARY (BARIS AKHIR)
// -------------------------------------------------------------
$last_data_row = $row_num - 1;
$total_row = $row_num;

$sheet->mergeCells('A' . $total_row . ':N' . $total_row);
$sheet->setCellValue('A' . $total_row, 'TOTAL NILAI TRANSAKSI DEAL:');
$sheet->setCellValue('O' . $total_row, "=SUM(O9:O{$last_data_row})");
$sheet->setCellValue('P' . $total_row, number_format($total_data) . ' Data');

$sheet->getStyle('A' . $total_row . ':P' . $total_row)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle('A' . $total_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('O' . $total_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('P' . $total_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('O' . $total_row)->getNumberFormat()->setFormatCode('"Rp "#,##0');

$sheet->getStyle('A' . $total_row . ':P' . $total_row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2E8F0');
$sheet->getRowDimension($total_row)->setRowHeight(24);

// -------------------------------------------------------------
// F. BORDERS & STYLING PENYEMPURNAAN
// -------------------------------------------------------------
$all_table_range = 'A8:P' . $total_row;
$sheet->getStyle($all_table_range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('CBD5E1');

// Double line pada total summary
$sheet->getStyle('A' . $total_row . ':P' . $total_row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setARGB('475569');

// -------------------------------------------------------------
// G. AUTO-COLUMN WIDTHS & FIXED SIZING
// -------------------------------------------------------------
$column_widths = [
    'A' => 6,   // No
    'B' => 13,  // Tanggal
    'C' => 12,  // Waktu
    'D' => 22,  // Sales
    'E' => 30,  // Customer
    'F' => 14,  // Kategori
    'G' => 16,  // Status
    'H' => 20,  // Kota
    'I' => 38,  // Alamat
    'J' => 22,  // PIC
    'K' => 18,  // Telepon
    'L' => 26,  // Respon
    'M' => 45,  // Catatan
    'N' => 18,  // No Invoice
    'O' => 20,  // Nominal
    'P' => 24   // Media
];

foreach ($column_widths as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

// Freeze header table pada baris 9 (data pertama)
$sheet->freezePane('A9');

// Aktifkan AutoFilter pada kolom header
$sheet->setAutoFilter('A8:P' . $last_data_row);

// -------------------------------------------------------------
// H. OUTPUT KE BROWSER SEBAGAI ATTACHMENT XLSX
// -------------------------------------------------------------
$clean_date_str = date('Ymd_His');
$filename = "Laporan_Follow_Up_Sales_{$clean_date_str}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); // IE 9
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header('Pragma: public'); // HTTP/1.0

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
