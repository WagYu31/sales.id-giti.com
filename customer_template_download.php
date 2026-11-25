<?php
// customer_template_download.php
// Generator Template Resmi Import Customer (Excel .xlsx & CSV untuk Google Sheets)
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!in_array($role, ['superadmin', 'adminsales', 'sales'], true)) {
    http_response_code(403);
    die("Akses ditolak.");
}

$isSalesOnly = ($role === 'sales') || (isset($_GET['mode']) && $_GET['mode'] === 'sales');
$format = strtolower(trim($_GET['format'] ?? 'xlsx'));
if (!in_array($format, ['xlsx', 'csv'], true)) {
    $format = 'xlsx';
}

// Ambil daftar sales aktif untuk Sheet Panduan (khusus superadmin / adminsales)
$salesList = [];
if (!$isSalesOnly) {
    $resSales = $conn->query("SELECT id, nama_lengkap, role FROM sales WHERE deleted_at IS NULL ORDER BY nama_lengkap ASC");
    if ($resSales) {
        while ($row = $resSales->fetch_assoc()) {
            $salesList[] = $row;
        }
    }
}

$today = date('Y-m-d');
$sampleSales1 = $salesList[0]['nama_lengkap'] ?? 'Nama Sales 1';
$sampleSales2 = $salesList[1]['nama_lengkap'] ?? ($salesList[0]['nama_lengkap'] ?? '');

if ($isSalesOnly) {
    $headers = [
        'nama_toko',
        'kategori',
        'nama_pic',
        'tlp_pic',
        'alamat',
        'kota',
        'provinsi',
        'link_google_map',
        'tgl_input'
    ];
    $sampleRows = [
        [
            'CONTOH - Toko Berkah CCTV',
            'INSTALLER',
            'Bpk. Budi Santoso',
            '081234567890',
            'Jl. Mangga Dua Raya Blok B No. 14',
            'Jakarta Utara',
            'DKI Jakarta',
            'https://maps.google.com/?q=-6.1352,106.8133',
            $today
        ],
        [
            'CONTOH - CV Mandiri Jaya Security',
            'DEALER',
            'Ibu Siti Aminah',
            '085712345678',
            'Ruko Sudirman Square No. 7, Bandung Kulon',
            'Bandung',
            'Jawa Barat',
            'https://maps.google.com/?q=-6.9175,107.6191',
            $today
        ]
    ];
} else {
    $headers = [
        'nama_toko',
        'kategori',
        'nama_pic',
        'tlp_pic',
        'alamat',
        'kota',
        'provinsi',
        'link_google_map',
        'sales_name',
        'tgl_input'
    ];
    $sampleRows = [
        [
            'CONTOH - Toko Berkah CCTV',
            'INSTALLER',
            'Bpk. Budi Santoso',
            '081234567890',
            'Jl. Mangga Dua Raya Blok B No. 14',
            'Jakarta Utara',
            'DKI Jakarta',
            'https://maps.google.com/?q=-6.1352,106.8133',
            $sampleSales1,
            $today
        ],
        [
            'CONTOH - CV Mandiri Jaya Security',
            'DEALER',
            'Ibu Siti Aminah',
            '085712345678',
            'Ruko Sudirman Square No. 7, Bandung Kulon',
            'Bandung',
            'Jawa Barat',
            'https://maps.google.com/?q=-6.9175,107.6191',
            $sampleSales2,
            $today
        ]
    ];
}

$filenameBase = $isSalesOnly ? 'template_import_customer_sales' : 'template_import_customer_crm';

// Opsi 1: Download CSV (UTF-8 BOM, sangat praktis untuk Google Sheets)
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM agar Excel & Google Sheets membaca karakter dengan benar
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($sampleRows as $sRow) {
        fputcsv($out, $sRow);
    }
    fclose($out);
    exit;
}

// Opsi 2: Download XLSX (Styled + Dropdown Kategori + Format Text No. HP + Sheet Daftar Sales & Panduan)
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php'
];
foreach ($autoloadPaths as $ap) {
    if (file_exists($ap)) {
        require_once $ap;
        break;
    }
}

if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    // Fallback otomatis ke CSV bila PhpSpreadsheet tidak ditemukan
    header('Location: customer_template_download.php?format=csv' . ($isSalesOnly ? '&mode=sales' : ''));
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Loewix Sales CRM')
    ->setTitle('Template Import Database Customer')
    ->setDescription('Template resmi import database customer via Excel dan Google Sheets.');

// ═════════════════════════════════════════════════════════════════════════
// SHEET 1: Data Customer
// ═════════════════════════════════════════════════════════════════════════
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Customer');

$colCount = count($headers);
$lastColLetter = Coordinate::stringFromColumnIndex($colCount);

// Tulis Header di baris 1
foreach ($headers as $idx => $h) {
    $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
    $sheet->setCellValue($colLetter . '1', $h);
}

// Styling Header Baris 1
$sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11,
        'name' => 'Calibri'
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1E3A8A']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1']
        ]
    ]
]);
$sheet->getRowDimension(1)->setRowHeight(28);

// Tulis 2 Baris Contoh (otomatis dilewati sistem saat import jika diawali "CONTOH -")
$rowIdx = 2;
foreach ($sampleRows as $sRow) {
    foreach ($sRow as $cIdx => $val) {
        $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
        // Paksa kolom tlp_pic (index 3 / kolom D) sebagai explicit string agar angka 0 di depan tidak hilang
        if ($cIdx === 3) {
            $sheet->setCellValueExplicit($colLetter . $rowIdx, (string)$val, DataType::TYPE_STRING);
        } else {
            $sheet->setCellValue($colLetter . $rowIdx, $val);
        }
    }
    $sheet->getStyle("A{$rowIdx}:{$lastColLetter}{$rowIdx}")->applyFromArray([
        'font' => [
            'italic' => true,
            'color' => ['rgb' => '475569'],
            'size' => 10.5
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EFF6FF']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DBEAFE']
            ]
        ]
    ]);
    $sheet->getRowDimension($rowIdx)->setRowHeight(22);
    $rowIdx++;
}

// Format kolom D (tlp_pic) sebagai TEXT untuk baris 2-1000 agar angka 0 depan aman saat user mengetik di Excel
$sheet->getStyle('D2:D1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

// Format kolom tgl_input sebagai YYYY-MM-DD
$tglColLetter = $isSalesOnly ? 'I' : 'J';
$sheet->getStyle("{$tglColLetter}2:{$tglColLetter}1000")->getNumberFormat()->setFormatCode('yyyy-mm-dd');

// Dropdown Data Validation untuk Kolom B (kategori) pada baris 2-500
$validation = $sheet->getCell('B2')->getDataValidation();
$validation->setType(DataValidation::TYPE_LIST);
$validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
$validation->setAllowBlank(true);
$validation->setShowInputMessage(true);
$validation->setShowErrorMessage(true);
$validation->setShowDropDown(true);
$validation->setPromptTitle('Pilih Kategori Toko');
$validation->setPrompt('Pilih salah satu: INSTALLER, MASTER DEALER, DEALER, atau USER.');
$validation->setErrorTitle('Kategori Tidak Standar');
$validation->setError('Disarankan memilih: INSTALLER, MASTER DEALER, DEALER, atau USER.');
$validation->setFormula1('"INSTALLER,MASTER DEALER,DEALER,USER"');
$sheet->setDataValidation('B2:B500', $validation);

// Freeze header row & aktifkan AutoFilter
$sheet->freezePane('A2');
$sheet->setAutoFilter("A1:{$lastColLetter}1");

// Lebar kolom yang rapi & enak dibaca
$widths = $isSalesOnly
    ? ['A' => 30, 'B' => 18, 'C' => 24, 'D' => 18, 'E' => 42, 'F' => 18, 'G' => 18, 'H' => 36, 'I' => 14]
    : ['A' => 30, 'B' => 18, 'C' => 24, 'D' => 18, 'E' => 42, 'F' => 18, 'G' => 18, 'H' => 36, 'I' => 24, 'J' => 14];
foreach ($widths as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}

// ═════════════════════════════════════════════════════════════════════════
// SHEET 2: Panduan & Daftar Sales
// ═════════════════════════════════════════════════════════════════════════
$guideSheet = $spreadsheet->createSheet();
$guideSheet->setTitle('Panduan & Daftar Sales');

$guideSheet->setCellValue('A1', 'PANDUAN PENGISIAN TEMPLATE IMPORT CUSTOMER (EXCEL & GOOGLE SHEETS)');
$guideSheet->mergeCells('A1:D1');
$guideSheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]
]);
$guideSheet->getRowDimension(1)->setRowHeight(30);

$guideRows = [
    ['Kolom', 'Status', 'Format / Pilihan', 'Keterangan'],
    ['nama_toko', 'WAJIB', 'Teks (maks 150 karakter)', 'Nama toko / perusahaan / instalator. Baris yang diawali "CONTOH -" otomatis dilewati sistem.'],
    ['kategori', 'Opsional', 'INSTALLER / MASTER DEALER / DEALER / USER', 'Gunakan dropdown di kolom B. Bila kosong otomatis mengikuti default (DEALER).'],
    ['nama_pic', 'Opsional', 'Teks (maks 100 karakter)', 'Nama owner / PIC kontak. Bila kosong namun tlp_pic terisi, otomatis diisi "Owner".'],
    ['tlp_pic', 'Dianjurkan', 'Teks diawali 0 (contoh: 081234567890)', 'Boleh format 08..., +628..., atau 628... (otomatis dinormalisasi ke 08... oleh sistem).'],
    ['alamat', 'Opsional', 'Teks bebas', 'Alamat lengkap toko / gudang / lokasi proyek.'],
    ['kota', 'Opsional', 'Teks (contoh: Jakarta Utara, Bandung)', 'Nama kota atau kabupaten.'],
    ['provinsi', 'Opsional', 'Teks (contoh: DKI Jakarta, Jawa Barat)', 'Nama provinsi.'],
    ['link_google_map', 'Opsional', 'URL https://maps.google.com/...', 'Link lokasi Google Maps toko (opsional).'],
];
if (!$isSalesOnly) {
    $guideRows[] = ['sales_name', 'Opsional', 'Sesuai Daftar Sales di bawah', 'Kosongkan jika tidak di-assign (Umum), atau salin persis dari daftar Sales aktif di bawah.'];
}
$guideRows[] = ['tgl_input', 'Opsional', 'YYYY-MM-DD (contoh: ' . $today . ')', 'Kosongkan agar otomatis diisi tanggal hari ini saat import.'];

$gRow = 3;
foreach ($guideRows as $idx => $gr) {
    $guideSheet->fromArray($gr, null, "A{$gRow}");
    if ($idx === 0) {
        $guideSheet->getStyle("A{$gRow}:D{$gRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
    } else {
        $guideSheet->getStyle("A{$gRow}:D{$gRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]]
        ]);
        if ($gr[1] === 'WAJIB') {
            $guideSheet->getStyle("B{$gRow}")->getFont()->setBold(true)->getColor()->setRGB('DC2626');
        }
    }
    $gRow++;
}

$gRow += 1;
$guideSheet->setCellValue("A{$gRow}", 'CARA IMPORT DARI GOOGLE SPREADSHEET (GOOGLE SHEETS):');
$guideSheet->mergeCells("A{$gRow}:D{$gRow}");
$guideSheet->getStyle("A{$gRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('047857');
$gRow++;

$gsSteps = [
    '1. Buka Google Sheets (sheets.google.com) -> File -> Impor -> Upload file template ini.',
    '2. Isi data customer pada tab "Data Customer" (hapus atau biarkan baris CONTOH, sistem otomatis melewati baris CONTOH).',
    '3. Klik tombol "Bagikan" (Share) di kanan atas -> Akses Umum ubah ke "Siapa saja yang memiliki link" (Pelihat/Viewer) -> Salin Link.',
    '4. Buka menu Import Customer di CRM -> pilih tab "Link Google Sheets" -> tempel link -> klik Pratinjau lalu Import!'
];
foreach ($gsSteps as $st) {
    $guideSheet->setCellValue("A{$gRow}", $st);
    $guideSheet->mergeCells("A{$gRow}:D{$gRow}");
    $gRow++;
}

if (!$isSalesOnly && !empty($salesList)) {
    $gRow += 1;
    $guideSheet->setCellValue("A{$gRow}", 'DAFTAR NAMA SALES AKTIF DI SISTEM (UNTUK KOLOM sales_name)');
    $guideSheet->mergeCells("A{$gRow}:C{$gRow}");
    $guideSheet->getStyle("A{$gRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']]
    ]);
    $gRow++;

    $guideSheet->fromArray(['ID Sales', 'Nama Lengkap (Salin ke kolom sales_name)', 'Role'], null, "A{$gRow}");
    $guideSheet->getStyle("A{$gRow}:C{$gRow}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']]
    ]);
    $gRow++;

    foreach ($salesList as $sl) {
        $guideSheet->setCellValue("A{$gRow}", $sl['id']);
        $guideSheet->setCellValue("B{$gRow}", $sl['nama_lengkap']);
        $guideSheet->setCellValue("C{$gRow}", strtoupper($sl['role']));
        $guideSheet->getStyle("A{$gRow}:C{$gRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]]
        ]);
        $gRow++;
    }
}

$guideSheet->getColumnDimension('A')->setWidth(20);
$guideSheet->getColumnDimension('B')->setWidth(36);
$guideSheet->getColumnDimension('C')->setWidth(38);
$guideSheet->getColumnDimension('D')->setWidth(62);

// Pastikan Sheet 1 aktif saat file dibuka
$spreadsheet->setActiveSheetIndex(0);

// Bersihkan output buffer bila ada agar file XLSX tidak korup
if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
