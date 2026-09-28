<?php
/**
 * approve_kegiatan.php - Modul Aplikasi Sales
 * Handler persetujuan pengajuan reschedule dari aplikasi mobile sales
 */
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE kegiatan_sales SET status = 'dijadwalkan', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: kegiatan.php?msg=approved");
exit();
?>
