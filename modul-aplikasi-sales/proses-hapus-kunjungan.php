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

$conn->begin_transaction();

try {
    if (in_array($statusKegiatan, ['selesai', 'berjalan', 'proses'])) {
        // Rollback: delete pelaksanaan_sales record and change status back to 'dijadwalkan'
        $sqlDel = "DELETE FROM pelaksanaan_sales WHERE kegiatan_id = ? AND sales_id = ?";
        $stmtDel = $conn->prepare($sqlDel);
        $stmtDel->bind_param("ii", $kegiatanId, $salesId);
        $stmtDel->execute();
        $stmtDel->close();
        
        $sqlUp = "UPDATE kegiatan_sales SET status = 'dijadwalkan' WHERE id = ?";
        $stmtUp = $conn->prepare($sqlUp);
        $stmtUp->bind_param("i", $kegiatanId);
        $stmtUp->execute();
        $stmtUp->close();
        
        $message = 'Laporan kunjungan berhasil dihapus dan status dikembalikan menjadi dijadwalkan';
    } else {
        // Soft delete the kegiatan
        $sqlUp = "UPDATE kegiatan_sales SET deleted_at = NOW() WHERE id = ?";
        $stmtUp = $conn->prepare($sqlUp);
        $stmtUp->bind_param("i", $kegiatanId);
        $stmtUp->execute();
        $stmtUp->close();
        
        $message = 'Kunjungan berhasil dihapus secara permanen';
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => $message]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data: ' . $e->getMessage()]);
}
?>
