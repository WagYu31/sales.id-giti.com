<?php
include "conn.php";
include "session.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$kegiatanId = intval($_POST['kegiatan_id']);
$salesId = intval($_POST['sales_id']);
$statusKegiatan = $_POST['status_kegiatan'];
$jadwal = $_POST['jadwal'];

$conn->begin_transaction();

try {
    // 1. Update kegiatan_sales table
    $sqlKs = "UPDATE kegiatan_sales SET jadwal = ? WHERE id = ?";
    $stmtKs = $conn->prepare($sqlKs);
    $stmtKs->bind_param("si", $jadwal, $kegiatanId);
    $stmtKs->execute();
    $stmtKs->close();
    
    // 2. If status is selesai or berjalan, update pelaksanaan_sales table
    if (in_array($statusKegiatan, ['selesai', 'berjalan', 'proses'])) {
        $ci_at = !empty($_POST['ci_at']) ? $_POST['ci_at'] : null;
        $co_at = !empty($_POST['co_at']) ? $_POST['co_at'] : null;
        $tipe_prospek = $_POST['tipe_prospek'];
        $no_invoice = $_POST['no_invoice'];
        $keterangan = $_POST['keterangan'];
        $catatan_visit = $_POST['catatan_visit'];
        
        $sqlPs = "UPDATE pelaksanaan_sales 
                  SET ci_at = ?, co_at = ?, tipe_prospek = ?, no_invoice = ?, keterangan = ?, catatan_visit = ? 
                  WHERE kegiatan_id = ? AND sales_id = ?";
        $stmtPs = $conn->prepare($sqlPs);
        $stmtPs->bind_param("ssssssii", $ci_at, $co_at, $tipe_prospek, $no_invoice, $keterangan, $catatan_visit, $kegiatanId, $salesId);
        $stmtPs->execute();
        $stmtPs->close();
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Berhasil memperbarui data kunjungan']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui data: ' . $e->getMessage()]);
}
?>
