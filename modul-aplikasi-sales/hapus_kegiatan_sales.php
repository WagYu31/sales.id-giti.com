<?php
/**
 * hapus_kegiatan_sales.php - Modul Aplikasi Sales
 * Handler AJAX untuk menghapus kegiatan sales (soft delete)
 */
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Invalid request method";
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo "ID tidak valid";
    exit();
}

$conn->begin_transaction();

try {
    // 1. Soft delete kegiatan_sales
    $stmtKs = $conn->prepare("UPDATE kegiatan_sales SET deleted_at = NOW() WHERE id = ?");
    $stmtKs->bind_param("i", $id);
    $stmtKs->execute();
    $stmtKs->close();

    // 2. Soft delete team_kegiatan_sales
    $stmtTeam = $conn->prepare("UPDATE team_kegiatan_sales SET deleted_at = NOW() WHERE id_kegiatan_sales = ?");
    $stmtTeam->bind_param("i", $id);
    $stmtTeam->execute();
    $stmtTeam->close();

    $conn->commit();
    echo "success";
} catch (Exception $e) {
    $conn->rollback();
    echo "error: " . $e->getMessage();
}
?>
