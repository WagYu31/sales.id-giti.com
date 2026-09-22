<?php
include "conn.php";
include "session.php";

header('Content-Type: application/json');

if (!isset($_GET['kegiatan_id']) || !isset($_GET['sales_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit();
}

$kegiatanId = intval($_GET['kegiatan_id']);
$salesId = intval($_GET['sales_id']);

$sql = "SELECT ks.id AS kegiatan_id, ks.jadwal, ks.status AS status_kegiatan, 
               s.nama AS nama_sales, s.id AS sales_id, sc.nama AS nama_cust,
               ps.ci_at, ps.co_at, ps.keterangan, ps.catatan_visit, ps.tipe_prospek, ps.no_invoice
        FROM team_kegiatan_sales tks
        INNER JOIN kegiatan_sales ks ON tks.id_kegiatan_sales = ks.id
        INNER JOIN sales s ON tks.id_sales = s.id
        INNER JOIN sales_customer sc ON ks.id_customer = sc.id
        LEFT JOIN pelaksanaan_sales ps ON tks.id_kegiatan_sales = ps.kegiatan_id AND tks.id_sales = ps.sales_id
        WHERE ks.id = ? AND s.id = ? AND tks.deleted_at IS NULL AND ks.deleted_at IS NULL";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $kegiatanId, $salesId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'kegiatan_id' => (int)$row['kegiatan_id'],
            'sales_id' => (int)$row['sales_id'],
            'status_kegiatan' => $row['status_kegiatan'],
            'nama_sales' => $row['nama_sales'],
            'nama_cust' => $row['nama_cust'],
            'jadwal' => !empty($row['jadwal']) ? date('Y-m-d\TH:i', strtotime($row['jadwal'])) : '',
            'ci_at' => !empty($row['ci_at']) && $row['ci_at'] != '0000-00-00 00:00:00' ? date('Y-m-d\TH:i', strtotime($row['ci_at'])) : '',
            'co_at' => !empty($row['co_at']) && $row['co_at'] != '0000-00-00 00:00:00' ? date('Y-m-d\TH:i', strtotime($row['co_at'])) : '',
            'keterangan' => $row['keterangan'] ?? '',
            'catatan_visit' => $row['catatan_visit'] ?? '',
            'tipe_prospek' => $row['tipe_prospek'] ?? 'Biasa',
            'no_invoice' => $row['no_invoice'] ?? ''
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
}
$stmt->close();
?>
