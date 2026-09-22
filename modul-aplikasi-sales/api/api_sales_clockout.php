<?php
/**
 * API Sales Clock Out — Selesai kunjungan customer & upload dokumentasi foto/video
 * POST: kegiatan_id, sales_id, latitude, longitude, catatan_visit, [is_mock], [image_satu], [image_dua], [image_tiga], [image_empat], [image_lima]
 */

// Raise limits for large video uploads (base64 encoded)
ini_set('memory_limit', '512M');
ini_set('post_max_size', '200M');
ini_set('upload_max_filesize', '200M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/api_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Support raw JSON body for large base64 uploads
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data) {
    $kegiatanId  = intval($data['kegiatan_id']   ?? 0);
    $salesId     = intval($data['sales_id']      ?? 0);
    $lat         = trim($data['latitude']        ?? '');
    $lon         = trim($data['longitude']       ?? '');
    $catatan     = trim($data['catatan_visit']   ?? '');
    $namaClient  = trim($data['nama_client']     ?? '');
    $nomerClient = trim($data['nomer_client']    ?? '');
    $isMock      = intval($data['is_mock']       ?? 0);
    
    $telpCustomer = trim($data['telp_customer']  ?? '');
    $namaCustomer = trim($data['nama_customer']  ?? '');
    $tipeProspek  = trim($data['tipe_prospek']   ?? 'Biasa');
    $noInvoice    = trim($data['no_invoice']     ?? '');
    
    $image_satu  = $data['image_satu']  ?? '';
    $image_dua   = $data['image_dua']   ?? '';
    $image_tiga  = $data['image_tiga']  ?? '';
    $image_empat = $data['image_empat'] ?? '';
    $image_lima  = $data['image_lima']  ?? '';
} else {
    $kegiatanId  = intval($_POST['kegiatan_id']   ?? 0);
    $salesId     = intval($_POST['sales_id']      ?? 0);
    $lat         = trim($_POST['latitude']        ?? '');
    $lon         = trim($_POST['longitude']       ?? '');
    $catatan     = trim($_POST['catatan_visit']   ?? '');
    $namaClient  = trim($_POST['nama_client']     ?? '');
    $nomerClient = trim($_POST['nomer_client']    ?? '');
    $isMock      = intval($_POST['is_mock']       ?? 0);
    
    $telpCustomer = trim($_POST['telp_customer']  ?? '');
    $namaCustomer = trim($_POST['nama_customer']  ?? '');
    $tipeProspek  = trim($_POST['tipe_prospek']   ?? 'Biasa');
    $noInvoice    = trim($_POST['no_invoice']     ?? '');
    
    $image_satu  = $_POST['image_satu']  ?? '';
    $image_dua   = $_POST['image_dua']   ?? '';
    $image_tiga  = $_POST['image_tiga']  ?? '';
    $image_empat = $_POST['image_empat'] ?? '';
    $image_lima  = $_POST['image_lima']  ?? '';
}

if (!$kegiatanId || !$salesId || empty($lat) || empty($lon) || empty($namaClient) || empty($nomerClient)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap (Nama client dan nomor client wajib diisi)']);
    exit;
}

// Blok Fake GPS (Bypass khusus untuk akun testing sales ID 14 di simulator)
if ($isMock === 1 && $salesId !== 14) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'code'    => 'FAKE_GPS_DETECTED',
        'message' => 'Fake GPS terdeteksi. Gunakan lokasi GPS asli perangkat Anda.',
    ]);
    exit;
}

// ── Validasi Geofence ──────────────────────────────────
$geoCheck = $conn->prepare("
    SELECT ks.lat AS lat_kegiatan, ks.lon AS lon_kegiatan, ks.rad,
           c.lat  AS lat_customer,  c.lon  AS lon_customer
    FROM kegiatan_sales ks
    LEFT JOIN sales_customer c ON c.id = ks.id_customer
    WHERE ks.id = ? LIMIT 1
");
$geoCheck->bind_param('i', $kegiatanId);
$geoCheck->execute();
$geoData = $geoCheck->get_result()->fetch_assoc();

if ($geoData) {
    $targetLat = !empty($geoData['lat_kegiatan']) ? (float)$geoData['lat_kegiatan'] : (!empty($geoData['lat_customer']) ? (float)$geoData['lat_customer'] : null);
    $targetLon = !empty($geoData['lon_kegiatan']) ? (float)$geoData['lon_kegiatan'] : (!empty($geoData['lon_customer']) ? (float)$geoData['lon_customer'] : null);
    $maxRadius = !empty($geoData['rad']) ? (int)$geoData['rad'] : 100;

    if ($targetLat !== null && $targetLon !== null) {
        $earthRadius = 6371000;
        $latFrom = deg2rad((float)$lat);
        $lonFrom = deg2rad((float)$lon);
        $latTo   = deg2rad($targetLat);
        $lonTo   = deg2rad($targetLon);
        $dLat = $latTo - $latFrom;
        $dLon = $lonTo - $lonFrom;
        $a = sin($dLat/2) * sin($dLat/2) + cos($latFrom) * cos($latTo) * sin($dLon/2) * sin($dLon/2);
        $distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1-$a));

        if ($distance > $maxRadius) {
            http_response_code(403);
            echo json_encode([
                'status'  => 'error',
                'code'    => 'OUT_OF_GEOFENCE',
                'message' => 'Anda berada ' . round($distance) . 'm dari lokasi tujuan. Jarak maksimal: ' . $maxRadius . 'm.',
            ]);
            exit;
        }
    }
}

$now = date('Y-m-d H:i:s');

// Cek sudah Clock In
$chk = $conn->prepare("SELECT id, ci_at, co_at FROM pelaksanaan_sales WHERE kegiatan_id = ? AND sales_id = ? LIMIT 1");
$chk->bind_param('ii', $kegiatanId, $salesId);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();

if (!$existing || empty($existing['ci_at'])) {
    echo json_encode(['status' => 'error', 'message' => 'Anda belum Clock In untuk kunjungan ini']);
    exit;
}

if (!empty($existing['co_at'])) {
    echo json_encode(['status' => 'error', 'message' => 'Anda sudah Clock Out untuk kunjungan ini']);
    exit;
}

// Hitung durasi kunjungan
$ci    = new DateTime($existing['ci_at']);
$co    = new DateTime($now);
$diff  = $ci->diff($co);
$durasi = sprintf('%02d:%02d:%02d', $diff->h + ($diff->days * 24), $diff->i, $diff->s);

// Decode dan simpan base64 images
$saved_images = ['image_1' => null, 'image_2' => null, 'image_3' => null, 'image_4' => null, 'image_5' => null];
$image_inputs = [
    'image_1' => $image_satu,
    'image_2' => $image_dua,
    'image_3' => $image_tiga,
    'image_4' => $image_empat,
    'image_5' => $image_lima
];

$storage_dir = __DIR__ . '/storage/image/';
if (!is_dir($storage_dir)) {
    mkdir($storage_dir, 0775, true);
}

foreach ($image_inputs as $key => $base64_str) {
    if (!empty($base64_str)) {
        // Clean base64 data prefix if present
        if (preg_match('/^data:\w+\/\w+;base64,/', $base64_str)) {
            $base64_str = substr($base64_str, strpos($base64_str, ',') + 1);
        }
        
        $decoded = base64_decode($base64_str, true);
        if ($decoded !== false) {
            $fn = bin2hex(random_bytes(16));
            $ext = 'jpg'; // default
            
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($decoded);
                if (strpos($mimeType, 'video') !== false) {
                    $ext = 'mp4';
                } elseif (strpos($mimeType, 'png') !== false) {
                    $ext = 'png';
                }
            }
            
            $filename = $fn . '.' . $ext;
            if (file_put_contents($storage_dir . $filename, $decoded)) {
                $saved_images[$key] = $filename;
            }
        }
    }
}

// Update customer info if telp_customer or nama_customer is provided
if (!empty($telpCustomer) || !empty($namaCustomer)) {
    // Get customer_id of this kegiatan
    $stmtCust = $conn->prepare("SELECT id_customer FROM kegiatan_sales WHERE id = ? LIMIT 1");
    $stmtCust->bind_param('i', $kegiatanId);
    $stmtCust->execute();
    $custRow = $stmtCust->get_result()->fetch_assoc();
    $stmtCust->close();
    
    if ($custRow) {
        $customerId = $custRow['id_customer'];
        if (!empty($telpCustomer) && !empty($namaCustomer)) {
            $updCust = $conn->prepare("UPDATE sales_customer SET telp_pribadi = ?, nama = ? WHERE id = ?");
            $updCust->bind_param('ssi', $telpCustomer, $namaCustomer, $customerId);
            $updCust->execute();
            $updCust->close();
        } elseif (!empty($telpCustomer)) {
            $updCust = $conn->prepare("UPDATE sales_customer SET telp_pribadi = ? WHERE id = ?");
            $updCust->bind_param('si', $telpCustomer, $customerId);
            $updCust->execute();
            $updCust->close();
        } elseif (!empty($namaCustomer)) {
            $updCust = $conn->prepare("UPDATE sales_customer SET nama = ? WHERE id = ?");
            $updCust->bind_param('si', $namaCustomer, $customerId);
            $updCust->execute();
            $updCust->close();
        }
    }
}

// Auto fill/update customer phone number if it is provided
if (!empty($nomerClient)) {
    $stmtCust = $conn->prepare("SELECT id_customer FROM kegiatan_sales WHERE id = ? LIMIT 1");
    $stmtCust->bind_param('i', $kegiatanId);
    $stmtCust->execute();
    $custRow = $stmtCust->get_result()->fetch_assoc();
    $stmtCust->close();
    
    if ($custRow) {
        $customerId = $custRow['id_customer'];
        $updCust = $conn->prepare("UPDATE sales_customer SET telp_pribadi = ?, updated_at = NOW() WHERE id = ?");
        $updCust->bind_param('si', $nomerClient, $customerId);
        $updCust->execute();
        $updCust->close();
    }
}

// Update clock out beserta dengan link foto dokumentasi
$upd = $conn->prepare("UPDATE pelaksanaan_sales SET co_at = ?, lat_co = ?, lon_co = ?, catatan_visit = ?, status = 'selesai', image_1 = ?, image_2 = ?, image_3 = ?, image_4 = ?, image_5 = ?, nama_client = ?, nomer_client = ?, tipe_prospek = ?, no_invoice = ?, updated_at = NOW() WHERE id = ?");
$upd->bind_param('sssssssssssssi', $now, $lat, $lon, $catatan, $saved_images['image_1'], $saved_images['image_2'], $saved_images['image_3'], $saved_images['image_4'], $saved_images['image_5'], $namaClient, $nomerClient, $tipeProspek, $noInvoice, $existing['id']);
$upd->execute();

// Cascade: update kegiatan jika semua sales sudah selesai
$chkAll = $conn->query("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN ps.status = 'selesai' THEN 1 ELSE 0 END) AS selesai_count
    FROM team_kegiatan_sales tks
    LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = tks.id_kegiatan_sales AND ps.sales_id = tks.id_sales
    WHERE tks.id_kegiatan_sales = $kegiatanId AND tks.deleted_at IS NULL
");
$allRow = $chkAll->fetch_assoc();
if ($allRow && $allRow['total'] > 0 && $allRow['total'] == $allRow['selesai_count']) {
    $conn->query("UPDATE kegiatan_sales SET status = 'selesai', updated_at = NOW() WHERE id = $kegiatanId");
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Clock Out berhasil. Kunjungan selesai!',
    'data'    => [
        'kegiatan_id' => $kegiatanId,
        'sales_id'    => $salesId,
        'ci_at'       => $existing['ci_at'],
        'co_at'       => $now,
        'durasi'      => $durasi,
        'lat_co'      => $lat,
        'lon_co'      => $lon,
        'image_1'     => $saved_images['image_1'],
        'image_2'     => $saved_images['image_2'],
        'image_3'     => $saved_images['image_3'],
        'image_4'     => $saved_images['image_4'],
        'image_5'     => $saved_images['image_5']
    ],
]);
?>
