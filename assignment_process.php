<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

// Validasi session dan role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda harus login sebagai Superadmin.']);
    exit();
}

// Validasi input dasar
if (!isset($_POST['action']) || !isset($_POST['customer_ids']) || !is_array($_POST['customer_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid atau data tidak lengkap.']);
    exit();
}

$action = $_POST['action'];
$customer_ids = array_map('intval', $_POST['customer_ids']); // Pastikan semua ID adalah integer

if (empty($customer_ids)) {
     echo json_encode(['success' => false, 'message' => 'Tidak ada customer yang dipilih.']);
    exit();
}

// Siapkan query dinamis untuk klausa IN (...)
$placeholders = implode(',', array_fill(0, count($customer_ids), '?'));
// Siapkan tipe data untuk bind_param
$types = str_repeat('i', count($customer_ids));

$conn->begin_transaction();

try {
    if ($action === 'assign_customers') {
        // Validasi tambahan untuk aksi 'assign'
        if (!isset($_POST['sales_id']) || !is_numeric($_POST['sales_id'])) {
             throw new Exception("Sales belum dipilih atau tidak valid.");
        }
        $sales_id = (int)$_POST['sales_id'];

        $sql = "UPDATE customers SET sales_id = ? WHERE id IN ({$placeholders})";
        $stmt = $conn->prepare($sql);
        
        // Gabungkan tipe dan parameter untuk bind_param
        $bind_types = 'i' . $types;
        $bind_params = array_merge([$sales_id], $customer_ids);
        
        $stmt->bind_param($bind_types, ...$bind_params);
        $message = count($customer_ids) . " customer berhasil ditugaskan.";

    } elseif ($action === 'unassign_customers') {
        $sql = "UPDATE customers SET sales_id = NULL WHERE id IN ({$placeholders})";
        $stmt = $conn->prepare($sql);
        
        // bind_param hanya memerlukan ID customer
        $stmt->bind_param($types, ...$customer_ids);
        $message = "Tugas untuk " . count($customer_ids) . " customer berhasil dilepas.";

    } else {
        throw new Exception("Aksi tidak dikenal.");
    }

    $stmt->execute();
    
    // Cek apakah ada baris yang benar-benar terpengaruh
    if ($stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        // Bisa terjadi jika customer_ids tidak ada di DB, tapi kita anggap bukan error fatal
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Aksi selesai, tidak ada perubahan data.']);
    }
    $stmt->close();

} catch (Exception $e) {
    $conn->rollback();
    // Berikan pesan error yang lebih spesifik jika memungkinkan
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>