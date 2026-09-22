<?php
/**
 * API Sales Task — Daftar kunjungan sales
 * GET: sales_id, filter (today|all)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/api_db.php';

// Auto-fix 1: Mark old tasks referenced in rescheduled_from as 'dibatalkan'
$conn->query("UPDATE kegiatan_sales SET status = 'dibatalkan' WHERE id IN (SELECT rescheduled_from FROM (SELECT DISTINCT rescheduled_from FROM kegiatan_sales WHERE rescheduled_from IS NOT NULL AND deleted_at IS NULL) AS t) AND status != 'dibatalkan'");

// Auto-fix 2: Mark older unstarted tasks (<= Today or earlier than a newer active schedule) as 'dibatalkan'
$sqlAutoResched = "UPDATE kegiatan_sales ks_old
JOIN kegiatan_sales ks_new 
  ON ks_old.id_customer = ks_new.id_customer 
 AND ks_old.id != ks_new.id
 AND DATE(ks_new.jadwal) > DATE(ks_old.jadwal)
 AND ks_old.status = 'dijadwalkan'
 AND ks_new.status = 'dijadwalkan'
 AND ks_old.deleted_at IS NULL
 AND ks_new.deleted_at IS NULL
SET ks_old.status = 'dibatalkan', 
    ks_old.reschedule_reason = CONCAT('[Reschedule] Dijadwalkan ulang ke tanggal ', DATE_FORMAT(ks_new.jadwal, '%d %b %Y %H:%i'))";
$conn->query($sqlAutoResched);

$salesId = intval($_GET['sales_id'] ?? 0);
$filter  = trim($_GET['filter'] ?? 'today');

if (!$salesId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'sales_id wajib diisi']);
    exit;
}

$dateFilter = '';
if ($filter === 'today') {
    $dateFilter = "AND DATE(ks.jadwal) = CURDATE()";
} elseif ($filter === 'upcoming') {
    $dateFilter = "AND DATE(ks.jadwal) > CURDATE()";
}

$sql = "
    SELECT
        ks.id              AS kegiatan_id,
        ks.jadwal,
        ks.keterangan,
        ks.status          AS status_kegiatan,
        ks.kode,
        c.id               AS customer_id,
        c.nama             AS nama_customer,
        c.telp_pribadi     AS telp_customer,
        c.alamat           AS alamat_customer,
        c.kota             AS kota_customer,
        c.foto             AS foto_customer,
        c.lat              AS lat_customer,
        c.lon              AS lon_customer,
        ks.lat             AS lat_kegiatan,
        ks.lon             AS lon_kegiatan,
        ks.rad             AS rad_geofence,
        ps.id              AS pelaksanaan_id,
        ps.status          AS status_kunjungan,
        ps.ci_at,
        ps.co_at,
        ps.lat_ci,
        ps.lon_ci,
        ps.lat_co,
        ps.lon_co,
        ps.catatan_visit,
        ps.image_1,
        ps.image_2,
        ps.image_3,
        ps.image_4,
        ps.image_5
    FROM team_kegiatan_sales tks
    JOIN kegiatan_sales ks ON ks.id = tks.id_kegiatan_sales AND ks.deleted_at IS NULL
    JOIN sales_customer c  ON c.id  = ks.id_customer        AND c.deleted_at IS NULL
    LEFT JOIN pelaksanaan_sales ps
        ON ps.kegiatan_id = tks.id_kegiatan_sales
        AND ps.sales_id   = tks.id_sales
    WHERE tks.id_sales = ?
      AND tks.deleted_at IS NULL
      AND ks.status NOT IN ('waiting', 'dibatalkan', 'reschedule', 'cancelled')
      AND (ks.reschedule_reason IS NULL OR ks.reschedule_reason = '')
      $dateFilter
    ORDER BY ks.jadwal ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $salesId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'status' => 'success',
    'filter' => $filter,
    'total'  => count($rows),
    'data'   => $rows,
]);
