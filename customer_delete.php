<?php
// customer_delete.php
require_once 'includes/db.php';
require_once 'includes/auth.php'; 

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$customer_id = (int)$_GET['id'];

$stmt_check = $conn->prepare("SELECT sales_id, nama_toko FROM customers WHERE id = ? AND deleted_at IS NULL");
$stmt_check->bind_param("i", $customer_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    $customer = $result_check->fetch_assoc();

    if ($_SESSION['role'] == 'superadmin' || $_SESSION['user_id'] == $customer['sales_id']) {
        
        $conn->begin_transaction();
        
        try {
            $now = date("Y-m-d H:i:s");

            // 1. Soft delete di tabel utama `customers`
            $stmt_customer = $conn->prepare("UPDATE customers SET deleted_at = ? WHERE id = ?");
            $stmt_customer->bind_param("si", $now, $customer_id);
            if (!$stmt_customer->execute()) throw new Exception("Gagal menghapus data utama customer.");
            $stmt_customer->close();
            
            // 2. Soft delete di tabel `customer_pics`
            $stmt_pics = $conn->prepare("UPDATE customer_pics SET deleted_at = ? WHERE customer_id = ?");
            $stmt_pics->bind_param("si", $now, $customer_id);
            if (!$stmt_pics->execute()) throw new Exception("Gagal menghapus data PIC.");
            $stmt_pics->close();

            // PERUBAHAN: Soft delete di tabel `customer_phones` dihapus karena tabel tidak ada lagi.

            // 3. Soft delete di tabel `customer_addresses`
            $stmt_addresses = $conn->prepare("UPDATE customer_addresses SET deleted_at = ? WHERE customer_id = ?");
            $stmt_addresses->bind_param("si", $now, $customer_id);
            if (!$stmt_addresses->execute()) throw new Exception("Gagal menghapus data alamat.");
            $stmt_addresses->close();

            $conn->commit();
            $_SESSION['flash_message'] = "Customer '" . htmlspecialchars($customer['nama_toko']) . "' berhasil dihapus.";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message_error'] = "Gagal menghapus customer: " . $e->getMessage();
        }

    } else {
        $_SESSION['flash_message_error'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    }
} else {
    $_SESSION['flash_message_error'] = "Customer tidak ditemukan atau sudah dihapus.";
}
$stmt_check->close();

header("Location: index.php");
exit();
?>