<?php
// sales_delete.php
// LOGIKA UNTUK MENJAGA INTEGRITAS DATA CUSTOMER SUDAH SANGAT TEPAT.

require_once 'includes/db.php';
require_once 'includes/auth.php'; // Pastikan user login dan session ada

// Proteksi Halaman
if ($_SESSION['role'] !== 'superadmin' || !isset($_GET['id'])) {
    // Redirect atau tampilkan pesan error, lalu hentikan eksekusi
    $_SESSION['flash_message_error'] = "Akses ditolak.";
    header("Location: index.php");
    exit();
}

$sales_id = (int)$_GET['id'];

// Pastikan superadmin tidak menghapus dirinya sendiri
if ($sales_id == $_SESSION['user_id']) {
    $_SESSION['flash_message_error'] = "Anda tidak dapat menghapus akun Anda sendiri.";
    header("Location: sales_management.php");
    exit();
}

// 1. Set sales_id menjadi NULL untuk semua customer yang ditangani sales ini
// Ini memastikan customer tidak ikut "hilang" dan bisa di-assign ke sales lain
$stmt_reassign = $conn->prepare("UPDATE customers SET sales_id = NULL WHERE sales_id = ?");
$stmt_reassign->bind_param("i", $sales_id);
$stmt_reassign->execute();
$stmt_reassign->close();

// 2. Lakukan Soft Delete pada data sales
$stmt_soft_delete = $conn->prepare("UPDATE sales SET deleted_at = NOW(), email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()) WHERE id = ?");
$stmt_soft_delete->bind_param("i", $sales_id);
if ($stmt_soft_delete->execute()) {
    $_SESSION['flash_message'] = "Sales berhasil dihapus.";
} else {
    $_SESSION['flash_message_error'] = "Gagal menghapus sales.";
}
$stmt_soft_delete->close();

header("Location: sales_management.php");
exit();