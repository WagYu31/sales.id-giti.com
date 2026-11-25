<?php
// customer_bulk_upload_process_sales.php
// Endpoint Import Massal Customer (Excel .xlsx/.xls/.csv & Link Google Sheets) khusus akun Sales
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_import_helper.php';

$role = strtolower(trim($_SESSION['role'] ?? ''));
$isAjax = (isset($_POST['ajax']) && $_POST['ajax'] === '1') ||
          (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!isset($_SESSION['user_id']) || $role !== 'sales') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Akses ditolak. Halaman ini khusus akun Sales.']);
        exit;
    }
    die("Akses ditolak.");
}

$options = [
    'mode' => ($_POST['mode'] ?? 'import') === 'preview' ? 'preview' : 'import',
    'source_type' => ($_POST['source_type'] ?? 'file') === 'gsheet' ? 'gsheet' : 'file',
    'file' => $_FILES['customer_file'] ?? null,
    'gsheet_url' => trim((string)($_POST['gsheet_url'] ?? '')),
    'force_sales_id' => (int)$_SESSION['user_id'],
    'default_sales_id' => (int)$_SESSION['user_id'],
    'default_kategori' => trim((string)($_POST['default_kategori'] ?? 'DEALER')),
    'skip_duplicate_phone' => isset($_POST['skip_duplicate_phone']) ? ($_POST['skip_duplicate_phone'] === '1') : true,
    'skip_duplicate_store' => isset($_POST['skip_duplicate_store']) ? ($_POST['skip_duplicate_store'] === '1') : true,
];

$result = customerImportProcess($conn, $options);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($result['ok'])) {
    $sum = $result['summary'] ?? [];
    $_SESSION['upload_status'] = 'success';
    $_SESSION['flash_message'] = $result['message'] ?? ("Proses Selesai: " . ($sum['imported_count'] ?? 0) . " data customer berhasil diimpor.");
    if (!empty($result['issues'])) {
        $infoHtml = "<strong>Ringkasan baris dilewati (" . count($result['issues']) . "):</strong><br><ul class=\"text-start small mt-2\">";
        foreach (array_slice($result['issues'], 0, 25) as $msg) {
            $infoHtml .= "<li>" . htmlspecialchars($msg) . "</li>";
        }
        if (count($result['issues']) > 25) {
            $infoHtml .= "<li><em>...dan " . (count($result['issues']) - 25) . " baris lainnya.</em></li>";
        }
        $infoHtml .= "</ul>";
        $_SESSION['flash_message_info'] = $infoHtml;
    }
} else {
    $_SESSION['upload_status'] = 'error';
    $_SESSION['flash_message_error'] = $result['error'] ?? 'Gagal memproses import data.';
}

header("Location: customer_io_sales.php");
exit;
