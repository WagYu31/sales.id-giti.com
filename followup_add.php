<?php
require_once 'includes/db.php';

if (!isset($_GET['customer_id'])) {
    header("Location: index.php");
    exit();
}
$customer_id = $_GET['customer_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $respon_radio = $_POST['respon_radio'] ?? '';
    $respon_lainnya = trim($_POST['respon_lainnya'] ?? '');

    if ($respon_radio === 'Lainnya' && empty($respon_lainnya)) {
        $error = "Respon Lainnya wajib diisi jika Anda memilih opsi 'Lainnya'.";
    } elseif (!isset($_FILES['media1']) || $_FILES['media1']['error'] != 0) {
        $error = "Media 1 wajib diunggah.";
    }

    if (empty($error)) {
        $tgl_follow_up = $_POST['tgl_follow_up'];
        $keterangan = $_POST['keterangan'];
        $no_inv = $_POST['no_inv'];
        $sales_id_fu = $_SESSION['user_id'];

        $respon = ($respon_radio === 'Lainnya') ? $respon_lainnya : $respon_radio;

        $media_paths = [null, null, null];
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif',
            'video/mp4', 'video/webm',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'audio/mpeg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/wav',
            'audio/x-wav',
            'audio/ogg',
            'audio/aac',
            'audio/flac',
            'audio/x-ms-wma'
        ];
        
        for ($i = 1; $i <= 3; $i++) {
            $file_key = 'media' . $i;
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
                if (in_array($_FILES[$file_key]['type'], $allowed_types) && $_FILES[$file_key]['size'] < 30000000) {
                    $target_dir = "assets/uploads/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                    
                    $original_filename = $_FILES[$file_key]["name"];
                    $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                    $random_code = bin2hex(random_bytes(3));
                    $filename = "{$sales_id_fu}_{$customer_id}_{$random_code}.{$extension}";

                    $target_file = $target_dir . $filename;
                    if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_file)) {
                        $media_paths[$i-1] = $filename;
                    } else {
                        $error .= "Gagal mengunggah {$file_key}. ";
                    }
                } else {
                     $error .= "File {$file_key} tidak valid atau terlalu besar. ";
                }
            }
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO follow_ups (customer_id, sales_id, tgl_follow_up, respon, keterangan, no_inv, media1, media2, media3) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssss", $customer_id, $sales_id_fu, $tgl_follow_up, $respon, $keterangan, $no_inv, $media_paths[0], $media_paths[1], $media_paths[2]);
            
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Catatan follow up berhasil ditambahkan!";
                header("Location: followup_view.php?customer_id=" . $customer_id);
                exit();
            } else {
                $error = "Gagal menyimpan data follow up: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$stmt_cust = $conn->prepare("SELECT id, nama_toko, sales_id FROM customers WHERE id = ? AND deleted_at IS NULL");
$stmt_cust->bind_param("i", $customer_id);
$stmt_cust->execute();
$customer_result = $stmt_cust->get_result();
if ($customer_result->num_rows === 0) {
    die("Customer tidak ditemukan.");
}
$customer = $customer_result->fetch_assoc();
$stmt_cust->close();

if ($_SESSION['role'] != 'superadmin' && $_SESSION['user_id'] != $customer['sales_id']) {
    die("Anda tidak memiliki izin untuk menambah follow up untuk customer ini.");
}

$page_title = 'Tambah Follow Up';
require_once 'includes/header.php';

$file_accept_types = "image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx";
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1><i class="bi bi-chat-left-dots-fill"></i> Tambah Follow Up</h1>
        <h5 class="text-muted">Untuk Customer: <?php echo htmlspecialchars($customer['nama_toko']); ?></h5>
    </div>
    <a href="followup_view.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-secondary">Batal</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="followup_add.php?customer_id=<?php echo $customer_id; ?>" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="tgl_follow_up" class="form-label">Tanggal & Waktu Follow Up</label>
                <input type="datetime-local" class="form-control" id="tgl_follow_up" name="tgl_follow_up" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Respon Customer</label>
                <div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="respon_radio" id="respon1" value="Tidak ada respon" checked><label class="form-check-label" for="respon1">Tidak ada respon</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="respon_radio" id="respon2" value="Tidak tertarik"><label class="form-check-label" for="respon2">Tidak tertarik</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="respon_radio" id="respon3" value="Hanya bertanya"><label class="form-check-label" for="respon3">Hanya bertanya</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="respon_radio" id="respon4" value="Muncul keinginan membeli"><label class="form-check-label" for="respon4">Muncul keinginan membeli</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="respon_radio" id="respon5" value="Deal untuk beli"><label class="form-check-label" for="respon5">Deal untuk beli</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="respon_radio" id="respon6" value="Lainnya"><label class="form-check-label" for="respon6">Lainnya</label></div>
                </div>
            </div>
            <div class="mb-3" id="respon_lainnya_container" style="display: none;">
                <label for="respon_lainnya" class="form-label">Respon Lainnya</label>
                <input type="text" class="form-control" id="respon_lainnya" name="respon_lainnya">
            </div>
            <div class="mb-3">
                <label for="keterangan" class="form-label">Keterangan / Tindak Lanjut</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label for="no_inv" class="form-label">Nomor Invoice (Opsional)</label>
                <input type="text" class="form-control" id="no_inv" name="no_inv" placeholder="Masukkan nomor invoice jika ada">
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="media1" class="form-label">Media 1 <span class="text-danger">*</span></label>
                    <input class="form-control" type="file" id="media1" name="media1" accept="<?php echo $file_accept_types; ?>" required>
                    <small style="font-size:0.8em;" class="text-danger">*Max 10mb</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="media2" class="form-label">Media 2 (Opsional)</label>
                    <input class="form-control" type="file" id="media2" name="media2" accept="<?php echo $file_accept_types; ?>">
                    <small style="font-size:0.8em;" class="text-danger">*Max 10mb</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="media3" class="form-label">Media 3 (Opsional)</label>
                    <input class="form-control" type="file" id="media3" name="media3" accept="<?php echo $file_accept_types; ?>">
                    <small style="font-size:0.8em;" class="text-danger">*Max 10mb</small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Follow Up</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const responRadios = document.querySelectorAll('input[name="respon_radio"]');
    const lainnyaContainer = document.getElementById('respon_lainnya_container');
    const lainnyaInput = document.getElementById('respon_lainnya');

    responRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'Lainnya') {
                lainnyaContainer.style.display = 'block';
                lainnyaInput.required = true;
            } else {
                lainnyaContainer.style.display = 'none';
                lainnyaInput.required = false;
                lainnyaInput.value = '';
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>