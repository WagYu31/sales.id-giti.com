<?php
session_start();
require_once 'includes/db.php'; // Path ke file koneksi database

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit();
}

// Hanya izinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit();
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$old_password = $_POST['old_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validasi input
if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Konfirmasi password baru tidak cocok.']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password baru minimal harus 6 karakter.']);
    exit();
}

// Proses ke database
try {
    // 1. Ambil hash password lama dari database
    $stmt = $conn->prepare("SELECT password FROM sales WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
        $stmt->close();
        exit();
    }
    
    $user = $result->fetch_assoc();
    $hashed_password_from_db = $user['password'];
    $stmt->close();

    // 2. Verifikasi password lama
    if (!password_verify($old_password, $hashed_password_from_db)) {
        echo json_encode(['success' => false, 'message' => 'Password lama salah.']);
        exit();
    }

    // 3. Hash password baru dan update ke database
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_stmt = $conn->prepare("UPDATE sales SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password berhasil diubah.']);
    } else {
        throw new Exception("Gagal mengupdate password.");
    }
    $update_stmt->close();

} catch (Exception $e) {
    // Tangani error
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()]);
}

$conn->close();
?>