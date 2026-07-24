<?php
require '../vendor/autoload.php';
require_once 'includes/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'sales'])) {
    die("Akses ditolak.");
}

// Cek Wajib Izin Superadmin Khusus Sales
if ($_SESSION['role'] === 'sales') {
    $chk_perm = $conn->prepare("SELECT id FROM download_requests WHERE sales_id = ? AND status = 'Approved' ORDER BY id DESC LIMIT 1");
    $chk_perm->bind_param('i', $_SESSION['user_id']);
    $chk_perm->execute();
    $res_perm = $chk_perm->get_result();
    if ($res_perm->num_rows === 0) {
        die("AKSES DITOLAK: Anda belum memiliki izin dari Superadmin untuk mengunduh data customer.");
    }
    $chk_perm->close();
}

if (isset($_POST['customer_ids']) && is_array($_POST['customer_ids'])) {
    $customer_ids = array_map('intval', $_POST['customer_ids']);
    if (empty($customer_ids)) {
        $_SESSION['flash_message_error'] = "Tidak ada customer yang dipilih.";
        header("Location: customer_export.php");
        exit();
    }

    $placeholders = implode(',', array_fill(0, count($customer_ids), '?'));
    
    $sql_where_conditions = ["c.id IN ({$placeholders})"];
    $params = $customer_ids;
    $types = str_repeat('i', count($customer_ids));

    if ($_SESSION['role'] === 'sales') {
        $sql_where_conditions[] = "c.sales_id = ?";
        $params[] = $_SESSION['user_id'];
        $types .= 'i';
    }
    
    $where_clause = implode(' AND ', $sql_where_conditions);

    // --- Query Diperbarui untuk Memisahkan Alamat, Kota, Provinsi ---
    $sql = "
        SELECT 
            c.nama_toko,
            c.kategori,
            GROUP_CONCAT(DISTINCT cp.nama_pic SEPARATOR ', ') as pics,
            GROUP_CONCAT(DISTINCT cp.tlp_pic SEPARATOR ', ') as phones,
            GROUP_CONCAT(DISTINCT ca.alamat SEPARATOR '; ') as addresses,
            GROUP_CONCAT(DISTINCT ca.kota SEPARATOR '; ') as cities,
            GROUP_CONCAT(DISTINCT ca.provinsi SEPARATOR '; ') as provinces,
            s.nama_lengkap as sales_pj
        FROM customers c
        LEFT JOIN customer_pics cp ON c.id = cp.customer_id AND cp.deleted_at IS NULL
        LEFT JOIN customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
        LEFT JOIN sales s ON c.sales_id = s.id AND s.deleted_at IS NULL
        WHERE {$where_clause}
        GROUP BY c.id, c.nama_toko, c.kategori, s.nama_lengkap
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Customer');

    // --- Header Diperbarui ---
    $headers = ['Nama Toko', 'Kategori', 'PIC', 'Telepon', 'Alamat', 'Kota', 'Provinsi', 'Sales Penanggung Jawab'];
    $sheet->fromArray($headers, NULL, 'A1');
    
    foreach (range('A', 'H') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    $row_num = 2;
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row_num, $row['nama_toko']);
        $sheet->setCellValue('B' . $row_num, $row['kategori']);
        $sheet->setCellValue('C' . $row_num, $row['pics']);
        $sheet->setCellValue('D' . $row_num, $row['phones']);
        $sheet->setCellValue('E' . $row_num, $row['addresses']);
        $sheet->setCellValue('F' . $row_num, $row['cities']);
        $sheet->setCellValue('G' . $row_num, $row['provinces']);
        $sheet->setCellValue('H' . $row_num, $row['sales_pj']);
        $row_num++;
    }

    // --- Hapus Cookie Tanda Download Berhasil ---
    if (isset($_POST['download_token'])) {
        $token = $_POST['download_token'];
        setcookie('download_token', $token, time() - 3600, "/"); // Hapus cookie
    }

    $filename = "data_customer_" . date('Ymd_His') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();

} else {
    $_SESSION['flash_message_error'] = "Tidak ada customer yang dipilih.";
    header("Location: customer_export.php");
    exit();
}