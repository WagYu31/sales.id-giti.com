<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

header('Content-Type: application/json');

$pic_id = $_POST['pic_id'] ?? 0;
$new_phone = $_POST['new_phone_number'] ?? '';

// Validasi dan sanitasi sederhana
$cleaned_phone = preg_replace('/[^0-9]/', '', $new_phone);
if (substr($cleaned_phone, 0, 1) !== '0') {
    $cleaned_phone = '0' . $cleaned_phone;
}

if (empty($pic_id) || strlen($cleaned_phone) < 9) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid atau nomor telepon terlalu pendek.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE customer_pics SET tlp_pic = ? WHERE id = ?");
    $stmt->bind_param("si", $cleaned_phone, $pic_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Gagal memperbarui database.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>