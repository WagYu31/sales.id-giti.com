<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

header('Content-Type: application/json');

$keep_id = $_POST['keep_id'] ?? 0;
$delete_ids_str = $_POST['delete_ids'] ?? '';

if (empty($keep_id) || empty($delete_ids_str)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$delete_ids = explode(',', $delete_ids_str);
$placeholders = implode(',', array_fill(0, count($delete_ids), '?'));
$types = str_repeat('i', count($delete_ids));

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE customers SET deleted_at = NOW() WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$delete_ids);
    
    if (!$stmt->execute()) {
        throw new Exception('Gagal menghapus data customer duplikat.');
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>