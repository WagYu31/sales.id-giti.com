<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/api_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data) {
    $kegiatanId = intval($data['kegiatan_id'] ?? 0);
    $salesId    = intval($data['sales_id']    ?? 0);
    $newJadwal  = trim($data['new_jadwal']    ?? '');
    $reason     = trim($data['reason']        ?? '');
} else {
    $kegiatanId = intval($_POST['kegiatan_id'] ?? 0);
    $salesId    = intval($_POST['sales_id']    ?? 0);
    $newJadwal  = trim($_POST['new_jadwal']    ?? '');
    $reason     = trim($_POST['reason']        ?? '');
}

if (!$kegiatanId || !$salesId || empty($newJadwal) || empty($reason)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap (new_jadwal dan reason wajib diisi)']);
    exit;
}

// 1. Get original task details
$stmtOld = $conn->prepare("SELECT id_customer, kode, keterangan FROM kegiatan_sales WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmtOld->bind_param('i', $kegiatanId);
$stmtOld->execute();
$oldRow = $stmtOld->get_result()->fetch_assoc();
$stmtOld->close();

if (!$oldRow) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Jadwal kunjungan tidak ditemukan']);
    exit;
}

$customerId    = $oldRow['id_customer'];
$oldKode       = $oldRow['kode'];
$oldKeterangan = $oldRow['keterangan'];

// Start transaction to keep integrity
$conn->begin_transaction();

try {
    // 2. Mark current task as cancelled ('dibatalkan')
    $stmtCancel = $conn->prepare("UPDATE kegiatan_sales SET status = 'dibatalkan', reschedule_reason = ?, updated_at = NOW() WHERE id = ?");
    $stmtCancel->bind_param('si', $reason, $kegiatanId);
    $stmtCancel->execute();
    $stmtCancel->close();

    // 3. Mark execution as cancelled ('dibatalkan')
    $stmtChk = $conn->prepare("SELECT id FROM pelaksanaan_sales WHERE kegiatan_id = ? AND sales_id = ? LIMIT 1");
    $stmtChk->bind_param('ii', $kegiatanId, $salesId);
    $stmtChk->execute();
    $chkRow = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();
    
    $fullReason = "[Reschedule] " . $reason;
    if ($chkRow) {
        $stmtUpd = $conn->prepare("UPDATE pelaksanaan_sales SET status = 'dibatalkan', catatan_visit = ?, co_at = NOW(), updated_at = NOW() WHERE kegiatan_id = ? AND sales_id = ?");
        $stmtUpd->bind_param('sii', $fullReason, $kegiatanId, $salesId);
        $stmtUpd->execute();
        $stmtUpd->close();
    } else {
        $stmtIns = $conn->prepare("INSERT INTO pelaksanaan_sales (kegiatan_id, sales_id, status, catatan_visit, ci_at, co_at, created_at, updated_at) VALUES (?, ?, 'dibatalkan', ?, NULL, NULL, NOW(), NOW())");
        $stmtIns->bind_param('iis', $kegiatanId, $salesId, $fullReason);
        $stmtIns->execute();
        $stmtIns->close();
    }

    // 4. Create new kegiatan_sales record
    $newKeterangan = "[Jadwal Ulang] " . $oldKeterangan;
    $stmtNew = $conn->prepare("INSERT INTO kegiatan_sales (id_customer, jadwal, keterangan, status, kode, rescheduled_from, created_at, updated_at) VALUES (?, ?, ?, 'waiting', ?, ?, NOW(), NOW())");
    $stmtNew->bind_param('isssi', $customerId, $newJadwal, $newKeterangan, $oldKode, $kegiatanId);
    $stmtNew->execute();
    $newKegiatanId = $stmtNew->insert_id;
    $stmtNew->close();

    // 5. Get sales name
    $stmtS = $conn->prepare("SELECT nama FROM sales WHERE id = ? LIMIT 1");
    $stmtS->bind_param('i', $salesId);
    $stmtS->execute();
    $salesRow = $stmtS->get_result()->fetch_assoc();
    $stmtS->close();
    $salesName = $salesRow ? $salesRow['nama'] : '';

    // 6. Assign sales to the new task
    $stmtAssign = $conn->prepare("INSERT INTO team_kegiatan_sales (id_kegiatan_sales, id_sales, nama_sales, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmtAssign->bind_param('iis', $newKegiatanId, $salesId, $salesName);
    $stmtAssign->execute();
    $stmtAssign->close();

    $conn->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'Berhasil menjadwalkan ulang kunjungan!',
        'data' => [
            'old_kegiatan_id' => $kegiatanId,
            'new_kegiatan_id' => $newKegiatanId,
            'new_jadwal'      => $newJadwal
        ]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menjadwalkan ulang: ' . $e->getMessage()]);
}
?>
