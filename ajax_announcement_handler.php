<?php
/**
 * AJAX Handler untuk Fitur Pengumuman Sales Loewix
 */

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

require_once 'includes/db.php';

// Auto Create Table announcements if not exists
$createTableQuery = "CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `badge_type` ENUM('info', 'promo', 'warning', 'urgent') DEFAULT 'info',
    `created_by` VARCHAR(100) DEFAULT 'Admin',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$conn->query($createTableQuery);

$action = $_REQUEST['action'] ?? '';

if ($action === 'fetch_active') {
    $result = $conn->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 10");
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'fetch_all') {
    $result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $badge_type = $_POST['badge_type'] ?? 'info';
    $created_by = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Admin Sales';

    if (empty($title) || empty($content)) {
        echo json_encode(['status' => 'error', 'message' => 'Judul dan Isi Pengumuman tidak boleh kosong.']);
        exit;
    }

    if (!in_array($badge_type, ['info', 'promo', 'warning', 'urgent'])) {
        $badge_type = 'info';
    }

    $stmt = $conn->prepare("INSERT INTO announcements (title, content, badge_type, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $content, $badge_type, $created_by);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Pengumuman berhasil diterbitkan!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menerbitkan pengumuman: ' . $conn->error]);
    }
    exit;
}

if ($action === 'toggle_status') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID Pengumuman tidak valid.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE announcements SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Status pengumuman berhasil diperbarui!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status pengumuman.']);
    }
    exit;
}

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID Pengumuman tidak valid.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Pengumuman berhasil dihapus!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus pengumuman.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid.']);
