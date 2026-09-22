<?php
/**
 * proses_reschedule_admin.php
 * Admin-initiated reschedule — same logic as mobile app but:
 * - Status langsung 'dijadwalkan' (tidak perlu waiting/approve)
 * - Alasan dicatat dari admin
 */
include "conn.php";
include "session.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$kegiatanId = intval($_POST['kegiatan_id'] ?? 0);
$newJadwal  = trim($_POST['new_jadwal'] ?? '');
$reason     = trim($_POST['reason'] ?? '');

if (!$kegiatanId || !$newJadwal) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

// 1. Get old kegiatan data
$stmtOld = $conn->prepare("SELECT ks.*, sc.nama AS nama_customer FROM kegiatan_sales ks LEFT JOIN sales_customer sc ON ks.id_customer = sc.id WHERE ks.id = ? AND ks.deleted_at IS NULL");
$stmtOld->bind_param('i', $kegiatanId);
$stmtOld->execute();
$oldRow = $stmtOld->get_result()->fetch_assoc();
$stmtOld->close();

if (!$oldRow) {
    echo json_encode(['status' => 'error', 'message' => 'Kegiatan tidak ditemukan']);
    exit;
}

$customerId    = $oldRow['id_customer'];
$oldKode       = $oldRow['kode'];
$oldKeterangan = $oldRow['keterangan'];

// Get assigned sales team
$teamResult = mysqli_query($conn, "SELECT id_sales, nama_sales FROM team_kegiatan_sales WHERE id_kegiatan_sales = '$kegiatanId' AND deleted_at IS NULL");
$salesTeam = [];
while ($t = mysqli_fetch_assoc($teamResult)) {
    $salesTeam[] = $t;
}

$conn->begin_transaction();

try {
    // 2. Mark current task as cancelled
    $adminReason = "[Admin Reschedule] " . ($reason ?: 'Dijadwalkan ulang oleh admin');
    $stmtCancel = $conn->prepare("UPDATE kegiatan_sales SET status = 'dibatalkan', reschedule_reason = ?, updated_at = NOW() WHERE id = ?");
    $stmtCancel->bind_param('si', $adminReason, $kegiatanId);
    $stmtCancel->execute();
    $stmtCancel->close();

    // 3. Mark all pelaksanaan as cancelled
    $stmtPel = $conn->prepare("UPDATE pelaksanaan_sales SET status = 'dibatalkan', catatan_visit = ?, co_at = NOW(), updated_at = NOW() WHERE kegiatan_id = ?");
    $stmtPel->bind_param('si', $adminReason, $kegiatanId);
    $stmtPel->execute();
    $stmtPel->close();

    // 4. Create new kegiatan with status 'dijadwalkan' (admin doesn't need waiting)
    $newKeterangan = "[Jadwal Ulang Admin] " . $oldKeterangan;
    $stmtNew = $conn->prepare("INSERT INTO kegiatan_sales (id_customer, jadwal, keterangan, status, kode, rescheduled_from, lat, lon, rad, alamat_lokasi, created_at, updated_at) VALUES (?, ?, ?, 'dijadwalkan', ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmtNew->bind_param('isssissss', $customerId, $newJadwal, $newKeterangan, $oldKode, $kegiatanId, $oldRow['lat'], $oldRow['lon'], $oldRow['rad'], $oldRow['alamat_lokasi']);
    $stmtNew->execute();
    $newKegiatanId = $stmtNew->insert_id;
    $stmtNew->close();

    // 5. Re-assign all sales team to new task
    foreach ($salesTeam as $member) {
        $stmtAssign = $conn->prepare("INSERT INTO team_kegiatan_sales (id_kegiatan_sales, id_sales, nama_sales, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmtAssign->bind_param('iis', $newKegiatanId, $member['id_sales'], $member['nama_sales']);
        $stmtAssign->execute();
        $stmtAssign->close();
    }

    $conn->commit();
    echo json_encode([
        'status'  => 'success',
        'message' => 'Berhasil dijadwalkan ulang!',
        'data'    => [
            'old_id' => $kegiatanId,
            'new_id' => $newKegiatanId,
            'new_jadwal' => $newJadwal
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Gagal: ' . $e->getMessage()]);
}
?>
