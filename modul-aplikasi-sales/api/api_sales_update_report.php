<?php
/**
 * API Sales Update Report — Edit catatan & documentation visit after clock out
 * POST: kegiatan_id, sales_id, catatan_visit, [image_satu], [image_dua], [image_tiga], [image_empat], [image_lima]
 */
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
    $catatan     = trim($data['catatan_visit']   ?? '');
    
    $image_satu  = $data['image_satu']  ?? '';
    $image_dua   = $data['image_dua']   ?? '';
    $image_tiga  = $data['image_tiga']  ?? '';
    $image_empat = $data['image_empat'] ?? '';
    $image_lima  = $data['image_lima']  ?? '';
} else {
    $kegiatanId  = intval($_POST['kegiatan_id']   ?? 0);
    $salesId     = intval($_POST['sales_id']      ?? 0);
    $catatan     = trim($_POST['catatan_visit']   ?? '');
    
    $image_satu  = $_POST['image_satu']  ?? '';
    $image_dua   = $_POST['image_dua']   ?? '';
    $image_tiga  = $_POST['image_tiga']  ?? '';
    $image_empat = $_POST['image_empat'] ?? '';
    $image_lima  = $_POST['image_lima']  ?? '';
}

if (!$kegiatanId || !$salesId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'kegiatan_id dan sales_id wajib diisi']);
    exit;
}

// Cek data pelaksanaan_sales
$chk = $conn->prepare("SELECT * FROM pelaksanaan_sales WHERE kegiatan_id = ? AND sales_id = ? LIMIT 1");
$chk->bind_param('ii', $kegiatanId, $salesId);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();

if (!$existing) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Laporan kunjungan tidak ditemukan. Silahkan clock in dan clock out terlebih dahulu.']);
    exit;
}

$storage_dir = __DIR__ . '/storage/image/';
if (!is_dir($storage_dir)) {
    mkdir($storage_dir, 0775, true);
}

$image_inputs = [
    'image_1' => $image_satu,
    'image_2' => $image_dua,
    'image_3' => $image_tiga,
    'image_4' => $image_empat,
    'image_5' => $image_lima
];

$saved_images = [
    'image_1' => $existing['image_1'],
    'image_2' => $existing['image_2'],
    'image_3' => $existing['image_3'],
    'image_4' => $existing['image_4'],
    'image_5' => $existing['image_5']
];

foreach ($image_inputs as $col => $input_val) {
    $input_val = trim($input_val);
    $current_file = $existing[$col];

    if (empty($input_val)) {
        // User deleted/removed the photo in this slot
        if (!empty($current_file) && file_exists($storage_dir . $current_file)) {
            @unlink($storage_dir . $current_file);
        }
        $saved_images[$col] = null;
    } else {
        // Check if it's base64 or a filename
        $is_base64 = false;
        if (preg_match('/^data:\w+\/\w+;base64,/', $input_val)) {
            $is_base64 = true;
            $input_val = substr($input_val, strpos($input_val, ',') + 1);
        } elseif (strlen($input_val) > 200 && strpos($input_val, '.') === false) {
            $is_base64 = true;
        }

        if ($is_base64) {
            // Delete old file if present
            if (!empty($current_file) && file_exists($storage_dir . $current_file)) {
                @unlink($storage_dir . $current_file);
            }

            // Save new base64 image
            $decoded = base64_decode($input_val, true);
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
                    $saved_images[$col] = $filename;
                }
            }
        } else {
            // It's the existing filename, keep it as is
            $saved_images[$col] = $input_val;
        }
    }
}

// Update database
$upd = $conn->prepare("
    UPDATE pelaksanaan_sales 
    SET catatan_visit = ?, 
        image_1 = ?, 
        image_2 = ?, 
        image_3 = ?, 
        image_4 = ?, 
        image_5 = ?, 
        updated_at = NOW() 
    WHERE id = ?
");
$upd->bind_param(
    'ssssssi', 
    $catatan, 
    $saved_images['image_1'], 
    $saved_images['image_2'], 
    $saved_images['image_3'], 
    $saved_images['image_4'], 
    $saved_images['image_5'], 
    $existing['id']
);
$upd->execute();

echo json_encode([
    'status'  => 'success',
    'message' => 'Laporan kunjungan berhasil diperbarui!',
    'data'    => [
        'kegiatan_id'   => $kegiatanId,
        'sales_id'      => $salesId,
        'catatan_visit' => $catatan,
        'image_1'       => $saved_images['image_1'],
        'image_2'       => $saved_images['image_2'],
        'image_3'       => $saved_images['image_3'],
        'image_4'       => $saved_images['image_4'],
        'image_5'       => $saved_images['image_5']
    ],
]);
?>
