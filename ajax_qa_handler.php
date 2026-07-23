<?php
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

header('Content-Type: application/json');

switch ($action) {
    case 'add_question':
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Judul tidak boleh kosong.']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO qa_questions (sales_id, title, body) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $body);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pertanyaan.']);
        }
        $stmt->close();
        break;

    case 'add_answer':
        $question_id = intval($_POST['question_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        if (empty($body) || empty($question_id)) {
            echo json_encode(['success' => false, 'message' => 'Jawaban tidak boleh kosong.']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO qa_answers (question_id, sales_id, body) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $question_id, $user_id, $body);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan jawaban.']);
        }
        $stmt->close();
        break;
    
    case 'delete_question':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT sales_id FROM qa_questions WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $owner_id = $stmt->get_result()->fetch_assoc()['sales_id'] ?? null;
        $stmt->close();

        if ($user_role === 'superadmin' || $user_id == $owner_id) {
            $stmt_delete = $conn->prepare("UPDATE qa_questions SET deleted_at = NOW() WHERE id = ?");
            $stmt_delete->bind_param("i", $id);
            if ($stmt_delete->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus pertanyaan.']);
            }
            $stmt_delete->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Anda tidak punya izin untuk menghapus pertanyaan ini.']);
        }
        break;

    case 'delete_answer':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT sales_id FROM qa_answers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $owner_id = $stmt->get_result()->fetch_assoc()['sales_id'] ?? null;
        $stmt->close();

        if ($user_role === 'superadmin' || $user_id == $owner_id) {
            $stmt_delete = $conn->prepare("UPDATE qa_answers SET deleted_at = NOW() WHERE id = ?");
            $stmt_delete->bind_param("i", $id);
            if ($stmt_delete->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus jawaban.']);
            }
            $stmt_delete->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Anda tidak punya izin untuk menghapus jawaban ini.']);
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
        break;
}