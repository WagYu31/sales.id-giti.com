<?php
// update_deal_status.php
// KODE INI SUDAH SANGAT BAIK, AMAN, DAN TIDAK MEMERLUKAN PERUBAHAN.

require_once 'includes/db.php';
require_once 'includes/auth.php'; // Pastikan user login dan session ada

// Set header ke JSON karena kita akan mengembalikan response JSON
header('Content-Type: application/json');

// 1. Validasi dasar & Keamanan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak valid.']);
    exit();
}

if (!isset($_POST['customer_id']) || !isset($_POST['deal_status'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit();
}

// 2. Ambil & Sanitasi Data
$customer_id = filter_var($_POST['customer_id'], FILTER_VALIDATE_INT);
$deal_status = $_POST['deal_status'] === 'Y' ? 'Y' : 'N'; // Hanya terima 'Y' atau 'N'

if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'ID Customer tidak valid.']);
    exit();
}

// 3. Otorisasi: Pastikan user berhak mengubah customer ini
$stmt_check = $conn->prepare("SELECT sales_id FROM customers WHERE id = ? AND deleted_at IS NULL");
$stmt_check->bind_param("i", $customer_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
if ($result_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Customer tidak ditemukan.']);
    $stmt_check->close();
    exit();
}
$customer = $result_check->fetch_assoc();

if ($_SESSION['role'] !== 'superadmin' && $_SESSION['user_id'] != $customer['sales_id']) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak punya izin untuk mengubah customer ini.']);
    $stmt_check->close();
    exit();
}
$stmt_check->close();

// 4. Update Database
$stmt_update = $conn->prepare("UPDATE customers SET deal = ? WHERE id = ?");
$stmt_update->bind_param("si", $deal_status, $customer_id);

if ($stmt_update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status deal berhasil diperbarui.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui database.']);
}

$stmt_update->close();
$conn->close();
?>