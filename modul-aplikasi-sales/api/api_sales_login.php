<?php
/**
 * API Sales Login
 * POST: nik, password
 * Login via NIK sales → user_sales password
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/api_db.php';

$username = trim($_POST['username'] ?? $_POST['nik'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username dan password wajib diisi']);
    exit;
}

// Step 1: Cari sales berdasarkan username (nama) ATAU nik
$stmt = $conn->prepare("SELECT id, nik, nama, telp, jabatan, foto FROM sales WHERE (nama = ? OR nik = ?) AND deleted_at IS NULL LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query error: ' . $conn->error]);
    exit;
}
$stmt->bind_param('ss', $username, $username);
$stmt->execute();
$sales = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sales) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Username tidak ditemukan']);
    exit;
}

// Step 2: Cari akun user_sales berdasarkan sales_id
$stmt2 = $conn->prepare("SELECT id, password FROM user_sales WHERE sales_id = ? AND deleted_at IS NULL LIMIT 1");
if (!$stmt2) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query error: ' . $conn->error]);
    exit;
}
$stmt2->bind_param('i', $sales['id']);
$stmt2->execute();
$userSales = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

if (!$userSales) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Akun belum terdaftar']);
    exit;
}

// Step 3: Verifikasi password
$passOk = password_verify($password, $userSales['password']) || $password === $userSales['password'];
if (!$passOk) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Password salah']);
    exit;
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Login berhasil',
    'data'    => [
        'id'      => (int)$sales['id'],
        'nik'     => $sales['nik'],
        'nama'    => $sales['nama'],
        'no_tlp'  => $sales['telp'] ?? '',
        'jabatan' => $sales['jabatan'] ?? 'Sales',
        'foto'    => $sales['foto'] ?? '',
    ],
]);
