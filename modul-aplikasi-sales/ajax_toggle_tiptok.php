<?php
/**
 * ajax_toggle_tiptok.php
 * Quick toggle for marking a customer as TIP TOK (Konsinyasi)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/conn.php";
require_once __DIR__ . "/session.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Customer tidak valid.']);
    exit();
}

// Ensure column exists
$chkCol = @$conn->query("SHOW COLUMNS FROM sales_customer LIKE 'is_tiptok'");
if ($chkCol && $chkCol->num_rows == 0) {
    @$conn->query("ALTER TABLE sales_customer ADD COLUMN `is_tiptok` TINYINT(1) NOT NULL DEFAULT 0 AFTER `kategori`");
}

// Get current status
$stmt = $conn->prepare("SELECT is_tiptok, nama FROM sales_customer WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$cust = $res->fetch_assoc();
$stmt->close();

if (!$cust) {
    echo json_encode(['success' => false, 'message' => 'Customer tidak ditemukan.']);
    exit();
}

// Toggle or set value
if (isset($_POST['set_status'])) {
    $newStatus = intval($_POST['set_status']) ? 1 : 0;
} else {
    $newStatus = (intval($cust['is_tiptok']) === 1) ? 0 : 1;
}

$upStmt = $conn->prepare("UPDATE sales_customer SET is_tiptok = ?, updated_at = NOW() WHERE id = ?");
$upStmt->bind_param("ii", $newStatus, $id);
$success = $upStmt->execute();
$upStmt->close();

if ($success) {
    $msg = $newStatus === 1 
        ? "Toko '{$cust['nama']}' berhasil ditandai sebagai Mitra TIP TOK!" 
        : "Tanda TIP TOK untuk '{$cust['nama']}' berhasil dinonaktifkan.";
    echo json_encode([
        'success' => true, 
        'is_tiptok' => $newStatus, 
        'nama' => $cust['nama'],
        'message' => $msg
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status TIP TOK: ' . $conn->error]);
}
