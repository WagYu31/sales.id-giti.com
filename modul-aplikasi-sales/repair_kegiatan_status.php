<?php
/**
 * repair_kegiatan_status.php
 * Script pembersihan dan perbaikan data kunjungan:
 * 1. Memperbaiki status '0' pada kegiatan_sales menjadi 'dijadwalkan' atau 'selesai'
 * 2. Menyelesaikan kunjungan BERKAH ARIZKY SEJAHTERA (kegiatan_id = 358) yang menggantung sejak 29 Sep 2026
 * 3. Mengharmoniskan status kegiatan_sales dan pelaksanaan_sales
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/api/api_db.php';

$action = $_GET['action'] ?? 'run';

$results = [
    'status'  => 'success',
    'time'    => date('Y-m-d H:i:s'),
    'actions' => [],
];

// 1. Selesaikan kunjungan BERKAH ARIZKY SEJAHTERA (kegiatan_id = 358) jika masih 'berjalan'
$chk358 = $conn->query("SELECT id, status, ci_at, co_at FROM pelaksanaan_sales WHERE kegiatan_id = 358 LIMIT 1");
if ($chk358 && $row358 = $chk358->fetch_assoc()) {
    if (empty($row358['co_at']) || $row358['status'] === 'berjalan') {
        $ciTime = $row358['ci_at'] ?? '2026-09-29 13:55:46';
        $coTime = date('Y-m-d 14:45:00', strtotime($ciTime)); // Set checkout 50 menit setelah checkin kemarin
        
        $conn->query("UPDATE pelaksanaan_sales SET status = 'selesai', co_at = '$coTime', catatan_visit = COALESCE(catatan_visit, 'Kunjungan selesai (Auto-checkout sistem)') WHERE kegiatan_id = 358");
        $conn->query("UPDATE kegiatan_sales SET status = 'selesai', updated_at = NOW() WHERE id = 358");
        
        $results['actions'][] = [
            'type'    => 'checkout_stuck_visit',
            'kegiatan_id' => 358,
            'customer'    => 'BERKAH ARIZKY SEJAHTERA',
            'ci_at'       => $ciTime,
            'co_at'       => $coTime,
            'status'      => 'selesai'
        ];
    }
}

// 2. Perbaiki status = '0' di kegiatan_sales yang sudah ada pelaksanaan_sales 'selesai'
$sqlFixSelesai = "
    UPDATE kegiatan_sales ks
    JOIN pelaksanaan_sales ps ON ps.kegiatan_id = ks.id
    SET ks.status = 'selesai', ks.updated_at = NOW()
    WHERE (ks.status = '0' OR ks.status = '')
      AND (ps.status = 'selesai' OR ps.co_at IS NOT NULL)
";
$conn->query($sqlFixSelesai);
$fixedSelesai = $conn->affected_rows;
$results['actions'][] = [
    'type'  => 'fix_status_0_to_selesai',
    'count' => $fixedSelesai
];

// 3. Perbaiki status = '0' di kegiatan_sales yang belum pernah dikunjungi menjadi 'dijadwalkan'
$sqlFixDijadwalkan = "
    UPDATE kegiatan_sales ks
    LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = ks.id
    SET ks.status = 'dijadwalkan', ks.updated_at = NOW()
    WHERE (ks.status = '0' OR ks.status = '')
      AND (ps.id IS NULL OR (ps.ci_at IS NULL AND ps.status IS NULL))
";
$conn->query($sqlFixDijadwalkan);
$fixedDijadwalkan = $conn->affected_rows;
$results['actions'][] = [
    'type'  => 'fix_status_0_to_dijadwalkan',
    'count' => $fixedDijadwalkan
];

// 4. Periksa apakah ada kunjungan 'berjalan' lainnya yang tanggal checkin-nya sudah lewat dari hari ini
$sqlAutoCloseOldRunning = "
    UPDATE pelaksanaan_sales ps
    JOIN kegiatan_sales ks ON ks.id = ps.kegiatan_id
    SET ps.status = 'selesai',
        ps.co_at = DATE_ADD(ps.ci_at, INTERVAL 1 HOUR),
        ps.catatan_visit = COALESCE(NULLIF(ps.catatan_visit, ''), 'Kunjungan selesai otomatis (Lewat hari)'),
        ks.status = 'selesai'
    WHERE ps.status = 'berjalan'
      AND ps.co_at IS NULL
      AND DATE(ps.ci_at) < CURDATE()
";
$conn->query($sqlAutoCloseOldRunning);
$autoClosedCount = $conn->affected_rows;
$results['actions'][] = [
    'type'  => 'auto_close_overnight_running',
    'count' => $autoClosedCount
];

// 5. Cek statistik terbaru untuk Sales Edi Suprianto (id = 20)
$chkEdi = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN ps.status = 'berjalan' OR (ps.ci_at IS NOT NULL AND ps.co_at IS NULL) THEN 1 ELSE 0 END) AS berjalan,
        SUM(CASE WHEN ps.status = 'selesai' OR ps.co_at IS NOT NULL THEN 1 ELSE 0 END) AS selesai,
        SUM(CASE WHEN ps.ci_at IS NULL THEN 1 ELSE 0 END) AS belum
    FROM team_kegiatan_sales tks
    JOIN kegiatan_sales ks ON ks.id = tks.id_kegiatan_sales AND ks.deleted_at IS NULL
    LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = tks.id_kegiatan_sales AND ps.sales_id = tks.id_sales
    WHERE tks.id_sales = 20
      AND tks.deleted_at IS NULL
      AND ks.status NOT IN ('waiting', 'dibatalkan', 'reschedule', 'cancelled')
      AND (ks.reschedule_reason IS NULL OR ks.reschedule_reason = '')
");
$results['sales_edi_stats'] = $chkEdi ? $chkEdi->fetch_assoc() : null;

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
