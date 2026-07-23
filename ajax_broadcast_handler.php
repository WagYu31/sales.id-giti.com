<?php
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
header('Content-Type: application/json');

function handle_file_upload($file_key, $sub_folder) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
        $target_dir = "assets/broadcasts/{$sub_folder}/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $filename = uniqid() . '-' . basename($_FILES[$file_key]["name"]);
        if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_dir . $filename)) {
            return $filename;
        }
    }
    return null;
}

switch ($action) {
    case 'add_schedule':
    case 'edit_schedule':
        $id = intval($_POST['broadcast_id'] ?? 0);
        $jadwal = $_POST['jadwal_broadcast'];
        $text = $_POST['text_broadcast'];
        $keterangan = $_POST['keterangan'];

        if ($action === 'edit_schedule') {
            $stmt = $conn->prepare("SELECT * FROM sales_broadcasts WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            if ($user_role !== 'superadmin' && $user_id != $existing['sales_id']) {
                die(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
            }
        }
        
        $gambar = handle_file_upload('gambar_broadcast', 'images') ?? $existing['gambar_broadcast'] ?? null;
        $excel = handle_file_upload('media_excel', 'media') ?? $existing['media_excel'] ?? null;

        if ($action === 'add_schedule') {
            $stmt = $conn->prepare("INSERT INTO sales_broadcasts (sales_id, jadwal_broadcast, gambar_broadcast, text_broadcast, media_excel, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $user_id, $jadwal, $gambar, $text, $excel, $keterangan);
        } else {
            $stmt = $conn->prepare("UPDATE sales_broadcasts SET jadwal_broadcast=?, gambar_broadcast=?, text_broadcast=?, media_excel=?, keterangan=? WHERE id=?");
            $stmt->bind_param("sssssi", $jadwal, $gambar, $text, $excel, $keterangan, $id);
        }
        
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        break;

    case 'upload_report':
        if ($user_role !== 'superadmin') {
            echo json_encode(['success' => false, 'message' => 'Hanya superadmin yang bisa mengupload laporan.']);
            exit;
        }
        $id = intval($_POST['broadcast_id'] ?? 0);
        $report_file = handle_file_upload('report_excel_file', 'reports');

        if ($report_file) {
            $stmt = $conn->prepare("UPDATE sales_broadcasts SET report_excel = ? WHERE id = ?");
            $stmt->bind_param("si", $report_file, $id);
            $success = $stmt->execute();
            echo json_encode(['success' => $success]);
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupload file laporan.']);
        }
        break;

    case 'get_details':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM sales_broadcasts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        if ($data && ($user_role === 'superadmin' || $user_id == $data['sales_id'])) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan atau akses ditolak.']);
        }
        break;

    case 'delete_schedule':
    case 'mark_done':
        $id = intval($_POST['broadcast_id'] ?? 0);
        $stmt = $conn->prepare("SELECT sales_id FROM sales_broadcasts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $owner_id = $stmt->get_result()->fetch_assoc()['sales_id'] ?? null;

        if ($user_role === 'superadmin' || $user_id == $owner_id) {
            $sql = ($action === 'delete_schedule') 
                ? "UPDATE sales_broadcasts SET deleted_at = NOW() WHERE id = ?"
                : "UPDATE sales_broadcasts SET status = 'done' WHERE id = ?";
            $stmt_update = $conn->prepare($sql);
            $stmt_update->bind_param("i", $id);
            echo json_encode(['success' => $stmt_update->execute()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
        break;
}