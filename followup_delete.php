<?php
// followup_delete.php
// FILE INI SUDAH BENAR DAN TIDAK MEMERLUKAN PERUBAHAN.

require_once 'includes/db.php';
require_once 'includes/auth.php'; // Pastikan session dimulai dan user terautentikasi

header('Content-Type: application/json');

// Validasi & Keamanan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Akses tidak sah.']);
    exit();
}

if (!isset($_POST['followup_id']) || !filter_var($_POST['followup_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'ID Follow-up tidak valid.']);
    exit();
}

$followup_id = $_POST['followup_id'];

// Otorisasi: Hanya superadmin atau sales yang membuat follow-up yang boleh menghapus
$stmt_check = $conn->prepare("SELECT sales_id FROM follow_ups WHERE id = ? AND deleted_at IS NULL");
$stmt_check->bind_param("i", $followup_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Catatan follow-up tidak ditemukan.']);
    $stmt_check->close();
    exit();
}

$follow_up = $result_check->fetch_assoc();

if ($_SESSION['role'] !== 'superadmin' && $_SESSION['user_id'] != $follow_up['sales_id']) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus catatan ini.']);
    $stmt_check->close();
    exit();
}
$stmt_check->close();

// Proses Soft Delete
$stmt_delete = $conn->prepare("UPDATE follow_ups SET deleted_at = NOW() WHERE id = ?");
$stmt_delete->bind_param("i", $followup_id);

if ($stmt_delete->execute()) {
    echo json_encode(['success' => true, 'message' => 'Catatan follow-up berhasil dihapus.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus catatan dari database.']);
}

$stmt_delete->close();
$conn->close();
?>