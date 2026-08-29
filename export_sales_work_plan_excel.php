<?php
/**
 * Export Excel Rencana Kerja Sales
 * PT. Loewix Indonesia
 */

require_once __DIR__ . '/includes/db.php';

$vendor_autoload = __DIR__ . '/vendor/autoload.php';
$has_phpspreadsheet = false;
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
    if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $has_phpspreadsheet = true;
    }
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// 1. Auth Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$is_admin  = in_array($user_role, ['superadmin', 'adminsales']);

// 2. Filters
$sales_id    = $is_admin && !empty($_GET['sales_id']) ? (int)$_GET['sales_id'] : ($user_role === 'sales' ? $user_id : '');
$tahun       = !empty($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$bulan       = !empty($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$minggu      = !empty($_GET['minggu']) && is_numeric($_GET['minggu']) ? (int)$_GET['minggu'] : '';
$metode_fu   = trim($_GET['metode_fu'] ?? '');
$status_done = isset($_GET['status_done']) && $_GET['status_done'] !== '' ? (int)$_GET['status_done'] : '';
$search      = trim($_GET['search'] ?? '');

$nama_bulan_arr = [
    1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
    5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
    9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
];
$nama_bulan = $nama_bulan_arr[$bulan] ?? date('F');

// Sales info
$sales_name_display = 'SEMUA SALES';
if (!empty($sales_id)) {
    $sq = $conn->query("SELECT nama_lengkap FROM sales WHERE id = " . intval($sales_id));
    if ($sq && $srow = $sq->fetch_assoc()) {
        $sales_name_display = strtoupper($srow['nama_lengkap']);
    }
}

// Build Query
$where = ["wp.deleted_at IS NULL"];
if (!empty($sales_id)) {
    $where[] = "wp.sales_id = " . intval($sales_id);
}
if (!empty($tahun)) {
    $where[] = "YEAR(wp.tanggal) = " . intval($tahun);
}
if (!empty($bulan)) {
    $where[] = "MONTH(wp.tanggal) = " . intval($bulan);
}
if (!empty($minggu)) {
    $start_day = ($minggu - 1) * 7 + 1;
    $end_day   = min($minggu * 7, 31);
    $where[] = "DAY(wp.tanggal) BETWEEN {$start_day} AND {$end_day}";
}
if (!empty($metode_fu)) {
    $where[] = "wp.metode_fu LIKE '%" . $conn->real_escape_string($metode_fu) . "%'";
}
if ($status_done !== '') {
    $where[] = "wp.is_done = " . intval($status_done);
}
if (!empty($search)) {
    $s_esc = $conn->real_escape_string($search);
    $where[] = "(wp.nama_customer LIKE '%{$s_esc}%' OR wp.kontak_customer LIKE '%{$s_esc}%' OR wp.aktivitas LIKE '%{$s_esc}%' OR wp.hasil_fu LIKE '%{$s_esc}%')";
}

$where_sql = implode(' AND ', $where);

// Fetch Data
$sql = "
    SELECT 
        wp.*,
        s.nama_lengkap AS sales_name,
        v.nama_lengkap AS verifier_name
    FROM sales_work_plans wp
    LEFT JOIN sales s ON wp.sales_id = s.id
    LEFT JOIN sales v ON wp.verified_by = v.id
    WHERE {$where_sql}
    ORDER BY wp.tanggal ASC, wp.id ASC
";
$res = $conn->query($sql);
$data_rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// Calculate Method Totals
$cnt_phone   = 0;
$cnt_wa      = 0;
$cnt_email   = 0;
$cnt_ketemu  = 0;
$cnt_done    = 0;
$cnt_total   = count($data_rows);

foreach ($data_rows as $row) {
    $m = strtolower($row['metode_fu']);
    if (strpos($m, 'phone') !== false || strpos($m, 'call') !== false || strpos($m, 'telepon') !== false) {
        $cnt_phone++;
    } elseif (strpos($m, 'wa') !== false || strpos($m, 'what') !== false || strpos($m, 'text') !== false) {
        $cnt_wa++;
    } elseif (strpos($m, 'email') !== false || strpos($m, 'mail') !== false) {
        $cnt_email++;
    } elseif (strpos($m, 'ketemu') !== false || strpos($m, 'visit') !== false || strpos($m, 'kunjungan') !== false || strpos($m, 'langsung') !== false) {
        $cnt_ketemu++;
    }
    if ((int)$row['is_done'] === 1) {
        $cnt_done++;
    }
}

$filename_suffix = str_replace(' ', '_', $sales_name_display) . "_{$nama_bulan}_{$tahun}";
if (!empty($minggu)) {
    $filename_suffix .= "_Minggu_{$minggu}";
}

// 3. Render PhpSpreadsheet Output
if ($has_phpspreadsheet) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rencana Kerja');

    // Page Setup
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

    // Header Loewix
    $sheet->setCellValue('A2', 'RENCANA KERJA SALES LOEWIX ' . $tahun);
    $sheet->mergeCells('A2:H2');
    $sheet->getStyle('A2')->getFont()->setSize(14)->setBold(true)->setColor(new Color('FF0F172A'));
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $period_label = $nama_bulan . ' ' . $tahun;
    if (!empty($minggu)) {
        $period_label .= ' (MINGGU KE-' . $minggu . ')';
    }
    $period_label .= ' - SALES: ' . $sales_name_display;
    $sheet->setCellValue('A3', $period_label);
    $sheet->mergeCells('A3:H3');
    $sheet->getStyle('A3')->getFont()->setSize(10)->setBold(true)->setColor(new Color('FF475569'));
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Summary Box
    $sheet->setCellValue('B5', 'Follow Up Method');
    $sheet->setCellValue('C5', 'Total Setiap Metode');
    $sheet->setCellValue('D5', 'Keterangan Periode');
    $sheet->setCellValue('E5', !empty($minggu) ? 'MINGGU KE-' . $minggu : 'SEMUA MINGGU');
    
    $sheet->getStyle('B5:E5')->getFont()->setBold(true);
    $sheet->getStyle('B5:E5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');

    $summary_items = [
        ['Phone Call', $cnt_phone],
        ['Text Whatsapp', $cnt_wa],
        ['Email', $cnt_email],
        ['Ketemu Langsung', $cnt_ketemu],
        ['Total Rencana', $cnt_total],
        ['Sudah Dilakukan (Verified)', $cnt_done . ' (' . ($cnt_total > 0 ? round(($cnt_done/$cnt_total)*100, 1) : 0) . '%)'],
    ];

    $cur_row = 6;
    foreach ($summary_items as $sitem) {
        $sheet->setCellValue('B' . $cur_row, $sitem[0]);
        $sheet->setCellValue('C' . $cur_row, $sitem[1]);
        if ($sitem[0] === 'Total Rencana' || strpos($sitem[0], 'Sudah Dilakukan') !== false) {
            $sheet->getStyle('B' . $cur_row . ':C' . $cur_row)->getFont()->setBold(true);
        }
        $cur_row++;
    }
    $sheet->getStyle('B5:C' . ($cur_row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Table Header
    $tbl_header_row = $cur_row + 2;
    $headers = [
        'A' => 'No',
        'B' => 'Status',
        'C' => 'Tanggal',
        'D' => 'Sales',
        'E' => 'Nama Customer',
        'F' => 'Aktivitas yang Akan Dilakukan',
        'G' => 'Kontak Customer',
        'H' => 'Email Customer',
        'I' => 'Metode Follow Up',
        'J' => 'Hasil Follow Up',
        'K' => 'Verifikator Admin'
    ];

    foreach ($headers as $col => $title) {
        $sheet->setCellValue($col . $tbl_header_row, $title);
    }

    $header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ];
    $sheet->getStyle('A' . $tbl_header_row . ':K' . $tbl_header_row)->applyFromArray($header_style);
    $sheet->getRowDimension($tbl_header_row)->setRowHeight(28);

    // Table Rows
    $data_row_idx = $tbl_header_row + 1;
    $no = 1;

    foreach ($data_rows as $row) {
        $status_text = (int)$row['is_done'] === 1 ? 'Sudah Dilakukan' : 'Belum Dilakukan';
        $tgl_fmt = !empty($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : '-';
        $verifier_info = !empty($row['verifier_name']) ? $row['verifier_name'] . (!empty($row['verified_at']) ? ' (' . date('d/m/Y H:i', strtotime($row['verified_at'])) . ')' : '') : '-';

        $sheet->setCellValue('A' . $data_row_idx, $no);
        $sheet->setCellValue('B' . $data_row_idx, $status_text);
        $sheet->setCellValue('C' . $data_row_idx, $tgl_fmt);
        $sheet->setCellValue('D' . $data_row_idx, $row['sales_name'] ?? '-');
        $sheet->setCellValue('E' . $data_row_idx, $row['nama_customer']);
        $sheet->setCellValue('F' . $data_row_idx, $row['aktivitas'] ?? '');
        $sheet->setCellValueExplicit('G' . $data_row_idx, $row['kontak_customer'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('H' . $data_row_idx, $row['email_customer'] ?? '');
        $sheet->setCellValue('I' . $data_row_idx, $row['metode_fu'] ?? '');
        $sheet->setCellValue('J' . $data_row_idx, $row['hasil_fu'] ?? '');
        $sheet->setCellValue('K' . $data_row_idx, $verifier_info);

        // Center align
        $sheet->getStyle('A' . $data_row_idx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B' . $data_row_idx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $data_row_idx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I' . $data_row_idx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Color status cell
        if ((int)$row['is_done'] === 1) {
            $sheet->getStyle('B' . $data_row_idx)->getFont()->setColor(new Color('FF16A34A'))->setBold(true);
        } else {
            $sheet->getStyle('B' . $data_row_idx)->getFont()->setColor(new Color('FFDC2626'));
        }

        // Zebra background
        if ($no % 2 === 0) {
            $sheet->getStyle('A' . $data_row_idx . ':K' . $data_row_idx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
        }

        $no++;
        $data_row_idx++;
    }

    // Border for data table
    $last_row = $data_row_idx - 1;
    if ($last_row >= $tbl_header_row) {
        $sheet->getStyle('A' . $tbl_header_row . ':K' . $last_row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    // Auto-fit columns
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Clean output buffer before sending headers
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Rencana_Kerja_Sales_' . $filename_suffix . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} else {
    // CSV / HTML Fallback
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Rencana_Kerja_Sales_' . $filename_suffix . '.csv"');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['RENCANA KERJA SALES LOEWIX ' . $tahun]);
    fputcsv($out, [$nama_bulan . ' ' . $tahun, 'SALES: ' . $sales_name_display]);
    fputcsv($out, []);
    fputcsv($out, ['Follow Up Method', 'Total']);
    fputcsv($out, ['Phone Call', $cnt_phone]);
    fputcsv($out, ['Text Whatsapp', $cnt_wa]);
    fputcsv($out, ['Email', $cnt_email]);
    fputcsv($out, ['Ketemu Langsung', $cnt_ketemu]);
    fputcsv($out, ['Total Rencana', $cnt_total]);
    fputcsv($out, ['Sudah Dilakukan', $cnt_done]);
    fputcsv($out, []);
    fputcsv($out, ['No', 'Status', 'Tanggal', 'Sales', 'Nama Customer', 'Aktivitas', 'Kontak', 'Email', 'Metode FU', 'Hasil FU', 'Verifikator']);

    $no = 1;
    foreach ($data_rows as $row) {
        fputcsv($out, [
            $no++,
            (int)$row['is_done'] === 1 ? 'Sudah Dilakukan' : 'Belum Dilakukan',
            !empty($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : '-',
            $row['sales_name'] ?? '-',
            $row['nama_customer'],
            $row['aktivitas'] ?? '',
            $row['kontak_customer'] ?? '',
            $row['email_customer'] ?? '',
            $row['metode_fu'] ?? '',
            $row['hasil_fu'] ?? '',
            $row['verifier_name'] ?? '-'
        ]);
    }

    fclose($out);
    exit;
}
