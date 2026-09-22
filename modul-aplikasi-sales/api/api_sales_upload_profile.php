<?php
/**
 * API Sales Upload Profile Photo
 * POST: sales_id, image (base64 string)
 */

ini_set('memory_limit', '128M');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/api_db.php';

// Auto-migration: Ensure 'foto' column exists in 'sales' table
$check = $conn->query("SHOW COLUMNS FROM `sales` LIKE 'foto'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE `sales` ADD COLUMN `foto` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Foto Profil Sales'");
}

// Parse inputs
$salesId   = intval($_POST['sales_id'] ?? 0);
$base64Str = trim($_POST['image'] ?? '');

if ($salesId <= 0 || empty($base64Str)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'sales_id dan image wajib dikirim']);
    exit;
}

// Clean base64 data prefix if present
if (preg_match('/^data:\w+\/\w+;base64,/', $base64Str)) {
    $base64Str = substr($base64Str, strpos($base64Str, ',') + 1);
}

$decoded = base64_decode($base64Str, true);
if ($decoded === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Format file tidak valid (Gagal decode base64)']);
    exit;
}

// Determine file extension
$ext = 'jpg';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($decoded);
    if (strpos($mimeType, 'png') !== false) {
        $ext = 'png';
    } elseif (strpos($mimeType, 'webp') !== false) {
        $ext = 'webp';
    }
}

// Ensure storage directory exists
$storage_dir = __DIR__ . '/storage/profile/';
if (!is_dir($storage_dir)) {
    mkdir($storage_dir, 0775, true);
}

// Verify if user exists
$chkUser = $conn->prepare("SELECT id, foto FROM sales WHERE id = ? LIMIT 1");
$chkUser->bind_param('i', $salesId);
$chkUser->execute();
$user = $chkUser->get_result()->fetch_assoc();
$chkUser->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Sales tidak ditemukan']);
    exit;
}

// Save file
$filename = 'sales_' . $salesId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$file_path = $storage_dir . $filename;

if (file_put_contents($file_path, $decoded)) {
    // Delete old profile picture if exists and exists in directory
    if (!empty($user['foto'])) {
        $old_path = $storage_dir . $user['foto'];
        if (file_exists($old_path)) {
            @unlink($old_path);
        }
    }

    // Update database
    $stmt = $conn->prepare("UPDATE sales SET foto = ? WHERE id = ?");
    $stmt->bind_param('si', $filename, $salesId);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'status'    => 'success',
            'message'   => 'Foto profil berhasil diunggah',
            'photo'     => $filename,
            'photo_url' => $filename
        ]);
        exit;
    }
    $stmt->close();
}

http_response_code(500);
echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file di server']);
?>
