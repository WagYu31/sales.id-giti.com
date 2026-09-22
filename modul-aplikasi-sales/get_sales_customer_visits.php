<?php
include "conn.php";
include "session.php";

header('Content-Type: application/json');

if (!isset($_GET['customer_id']) || !isset($_GET['sales_id']) || empty($_GET['customer_id']) || empty($_GET['sales_id'])) {
    echo json_encode([]);
    exit();
}

$customerId = intval($_GET['customer_id']);
$salesId = intval($_GET['sales_id']);

$sql = "SELECT ks.jadwal, ks.status AS status_kegiatan, ks.reschedule_reason, ps.ci_at, ps.co_at, ps.catatan_visit, ps.image_1, ps.image_2, ps.image_3, ps.image_4, ps.image_5, ps.tipe_prospek, ps.no_invoice, s.nama AS nama_sales
        FROM team_kegiatan_sales tks
        INNER JOIN kegiatan_sales ks ON tks.id_kegiatan_sales = ks.id
        INNER JOIN sales s ON tks.id_sales = s.id
        LEFT JOIN pelaksanaan_sales ps ON tks.id_kegiatan_sales = ps.kegiatan_id AND tks.id_sales = ps.sales_id
        WHERE ks.id_customer = ? AND tks.id_sales = ? AND ks.status IN ('selesai', 'dibatalkan') AND tks.deleted_at IS NULL AND ks.deleted_at IS NULL
        ORDER BY COALESCE(ps.co_at, ks.updated_at) DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $customerId, $salesId);
$stmt->execute();
$result = $stmt->get_result();

$visits = [];
while ($row = $result->fetch_assoc()) {
    $images = [];
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($row["image_$i"])) {
            $images[] = $row["image_$i"];
        }
    }
    
    $visits[] = [
        'nama_sales' => $row['nama_sales'],
        'status_kegiatan' => $row['status_kegiatan'],
        'reschedule_reason' => htmlspecialchars($row['reschedule_reason'] ?? ''),
        'jadwal' => !empty($row['jadwal']) ? date('d M Y', strtotime($row['jadwal'])) : '-',
        'ci_at' => !empty($row['ci_at']) ? date('d M Y, H:i', strtotime($row['ci_at'])) . ' WIB' : '-',
        'co_at' => !empty($row['co_at']) ? date('d M Y, H:i', strtotime($row['co_at'])) . ' WIB' : '-',
        'catatan_visit' => htmlspecialchars($row['catatan_visit'] ?? ''),
        'tipe_prospek' => htmlspecialchars($row['tipe_prospek'] ?? 'Biasa'),
        'no_invoice' => htmlspecialchars($row['no_invoice'] ?? ''),
        'images' => $images
    ];
}
$stmt->close();

echo json_encode($visits);
?>
