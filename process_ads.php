<?php
require_once 'includes/db.php';
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function recalculate_balances($conn) {
    $topups = $conn->query("SELECT * FROM ads_topups ORDER BY tanggal_topup ASC, id ASC");
    $last_date = '2000-01-01';
    $l_rem = 0;
    
    while ($t = $topups->fetch_assoc()) {
        $t_id = $t['id'];
        $t_date = date('Y-m-d', strtotime($t['tanggal_topup']));
        
        $sales = 0;
        if ($t_date > $last_date) {
            $q_sales = $conn->query("SELECT SUM(total_amount) FROM sales_reports WHERE tanggal > '$last_date' AND tanggal <= '$t_date'");
            $sales = (float)($q_sales->fetch_row()[0] ?? 0);
            $last_date = $t_date;
        }
        
        $rate = (float)$t['quota_rate'];
        $quota = ($sales * ($rate / 100)) + $l_rem;
        $final = $quota - (float)$t['topup_amount'];
        
        $conn->query("UPDATE ads_topups SET sales_period_total = '$sales', calculated_quota = '$quota', remaining_balance = '$final', last_sale_id = 0 WHERE id = $t_id");
        
        $l_rem = $final;
    }
}

$action = $_GET['action'] ?? '';

if ($action == 'get_budget') {
    $date = $_GET['date'];
    $rate = (float)($conn->query("SELECT rate_percentage FROM ads_settings ORDER BY id DESC LIMIT 1")->fetch_assoc()['rate_percentage'] ?? 15);
    
    $last_t = $conn->query("SELECT tanggal_topup, remaining_balance FROM ads_topups WHERE DATE(tanggal_topup) <= '$date' ORDER BY tanggal_topup DESC, id DESC LIMIT 1")->fetch_assoc();
    $l_rem = (float)($last_t['remaining_balance'] ?? 0);
    $last_date = $last_t ? date('Y-m-d', strtotime($last_t['tanggal_topup'])) : '2000-01-01';
    
    $sales = 0;
    if ($date > $last_date) {
        $sales = (float)($conn->query("SELECT SUM(total_amount) FROM sales_reports WHERE tanggal > '$last_date' AND tanggal <= '$date'")->fetch_row()[0] ?? 0);
    }
    
    $budget = ($sales * ($rate / 100)) + $l_rem;
    
    echo json_encode(['budget' => $budget]);
    exit;
}

if ($action == 'topup') {
    $plat = $conn->real_escape_string($_POST['platform']);
    $t_inp = $_POST['tanggal_topup'];
    $t_now = $t_inp . " " . date('H:i:s');
    $amt_t = (float)$_POST['topup_amount'];
    
    $rate = (float)($conn->query("SELECT rate_percentage FROM ads_settings ORDER BY id DESC LIMIT 1")->fetch_assoc()['rate_percentage'] ?? 15);
    
    $conn->query("INSERT INTO ads_topups (platform, tanggal_topup, quota_rate, topup_amount, last_sale_id, sales_period_total, calculated_quota, remaining_balance) VALUES ('$plat', '$t_now', '$rate', '$amt_t', 0, 0, 0, 0)");
    
    recalculate_balances($conn);
    header("Location: sales_ads.php");
    exit;
}

if ($action == 'delete_topup') {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM ads_topups WHERE id = $id");
    recalculate_balances($conn);
    header("Location: ads_report.php");
    exit;
}

if ($action == 'upload') {
    $file = $_FILES['file_excel']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $data = $spreadsheet->getActiveSheet()->toArray(null, false, false, true);
    
    foreach ($data as $idx => $row) {
        if ($idx == 1) continue;
        
        $raw_inv = trim($row['A'] ?? '');
        $raw_tgl = trim($row['B'] ?? '');
        
        if ($raw_inv === '' || $raw_tgl === '') continue;
        
        $inv = $conn->real_escape_string($raw_inv);
        $raw = $raw_tgl;
        
        $tgl = is_numeric($raw) ? Date::excelToDateTimeObject($raw)->format('Y-m-d') : (DateTime::createFromFormat('d/m/Y', $raw) ? DateTime::createFromFormat('d/m/Y', $raw)->format('Y-m-d') : date('Y-m-d', strtotime($raw)));
        
        $plt = $conn->real_escape_string(trim($row['C'] ?? ''));
        $amt = (float)str_replace(['.', ','], '', trim($row['D'] ?? ''));
        $slr = $conn->real_escape_string(trim($row['E'] ?? ''));
        $po = $conn->real_escape_string(trim($row['F'] ?? ''));
        
        if ($conn->query("SELECT id FROM sales_reports WHERE invoice_no = '$inv' AND tanggal = '$tgl'")->num_rows == 0) {
            $conn->query("INSERT INTO sales_reports (invoice_no, tanggal, platform, total_amount, nama_penjual, no_po) VALUES ('$inv', '$tgl', '$plt', '$amt', '$slr', '$po')");
        }
    }
    recalculate_balances($conn);
    header("Location: sales_ads.php");
    exit;
}

if ($action == 'template') {
    $spreadsheet = new Spreadsheet(); 
    $sheet = $spreadsheet->getActiveSheet();
    $headers = ['Kode Invoice', 'Tanggal', 'Platform', 'Nominal', 'Nama Penjual', 'Nomor PO'];
    $col = 1; 
    foreach ($headers as $v) { 
        $sheet->setCellValue([$col, 1], $v); 
        $col++; 
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Template.xlsx"');
    $writer = new Xlsx($spreadsheet); 
    $writer->save('php://output'); 
    exit;
}

if ($action == 'change_rate') {
    $r = (float)$_POST['new_rate']; 
    $conn->query("INSERT INTO ads_settings (rate_percentage) VALUES ('$r')");
    header("Location: ads_report.php");
    exit;
}

if ($action == 'delete') {
    $id = (int)$_GET['id']; 
    $conn->query("DELETE FROM sales_reports WHERE id = $id");
    recalculate_balances($conn);
    header("Location: sales_ads.php");
    exit;
}
?>