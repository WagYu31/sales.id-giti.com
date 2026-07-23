<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses sesi tidak valid.']);
    exit;
}

header('Content-Type: application/json');

$customer_id = $_POST['customer_id'] ?? 0;
$status_type = $_POST['status_type'] ?? '';
$status_value = $_POST['status_value'] ?? 'N';
$acc_boss_note = $_POST['acc_boss_note'] ?? null;
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$allowed_columns = ['deal', 'kandidat', 'potensial', 'acc_boss', 'lost_deal'];
if (empty($customer_id) || !in_array($status_type, $allowed_columns)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid (parameter salah).']);
    exit;
}

// if ($status_type === 'acc_boss' && $user_role !== 'superadmin') {
//      http_response_code(403);
//      echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang dapat mengubah status Deal.']);
//      exit;
// }

$stmt_check = $conn->prepare("SELECT sales_id FROM customers WHERE id = ? AND deleted_at IS NULL");
$stmt_check->bind_param("i", $customer_id);
$stmt_check->execute();
$customer = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if (!$customer) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Customer tidak ditemukan.']);
    exit;
}

if ($user_role === 'sales' && $user_id != $customer['sales_id'] && $status_type !== 'acc_boss') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengubah customer ini.']);
    exit;
}

$sql_update_parts = ["{$status_type} = ?"];
$params_update = [$status_value];
$types_update = 's';

if ($status_type === 'acc_boss') {
    if ($status_value === 'Y') {
        $sql_update_parts[] = "acc_boss_note = ?";
        $params_update[] = $acc_boss_note;
        $types_update .= 's';
    } else {
        $sql_update_parts[] = "acc_boss_note = NULL";
    }
}

$params_update[] = $customer_id;
$types_update .= 'i';

$sql = "UPDATE customers SET " . implode(', ', $sql_update_parts) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types_update, ...$params_update);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status di database.']);
}
$stmt->close();
?>