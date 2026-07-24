<?php
require_once 'includes/db.php';

if (!isset($_GET['customer_id'])) {
    header("Location: index.php");
    exit();
}
$customer_id = $_GET['customer_id'];
$error = '';

$is_sales = ($_SESSION['role'] === 'sales');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $respon_radio = $_POST['respon_radio'] ?? '';
    $respon_lainnya = trim($_POST['respon_lainnya'] ?? '');

    if ($respon_radio === 'Lainnya' && empty($respon_lainnya)) {
        $error = "Respon Lainnya wajib diisi jika Anda memilih opsi 'Lainnya'.";
    } elseif (!isset($_FILES['media1']) || $_FILES['media1']['error'] != 0 || empty($_FILES['media1']['tmp_name'])) {
        $error = "❌ BUKTI CHAT WAJIB DIUNGGAH! Anda harus mengunggah Bukti Chat / Screenshot WA (Media 1) terlebih dahulu untuk dapat menyimpan data Follow Up.";
    }

    if (empty($error)) {
        // Jika role sales, PAKSA tanggal & waktu realtime dari server (tidak bisa diubah DevTools/form)
        if ($is_sales) {
            $tgl_follow_up = date('Y-m-d H:i:s');
        } else {
            $tgl_follow_up = $_POST['tgl_follow_up'] ?? date('Y-m-d H:i:s');
        }

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

<style>
.fu-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.fu-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.fu-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
}
</style>

<!-- Hero Header -->
<div class="fu-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <a href="followup_view.php?customer_id=<?php echo $customer_id; ?>" style="color:inherit; text-decoration:none;">Follow Up</a>
                <span>›</span>
                <span>Tambah Follow Up</span>
            </div>
            <h1 class="fu-hero-title">Tambah Follow Up Baru 💬</h1>
            <p class="fu-hero-subtitle">Customer: <strong><?php echo htmlspecialchars($customer['nama_toko']); ?></strong></p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="followup_view.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-light border fw-bold px-4">
                Batal
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4" style="border-radius:14px;"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:20px;">
    <div class="card-body p-4 p-md-5">
        <form action="followup_add.php?customer_id=<?php echo $customer_id; ?>" method="POST" enctype="multipart/form-data">
            
            <!-- Tanggal & Waktu Follow Up -->
            <div class="mb-4">
                <label for="tgl_follow_up" class="form-label fw-bold text-dark d-flex align-items-center justify-content-between">
                    <span>Tanggal & Waktu Follow Up</span>
                    <?php if ($is_sales): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary fw-bold" style="font-size:11px;">
                            🔒 Otomatis Terisi Realtime (Khusus Sales Tidak Dapat Diubah)
                        </span>
                    <?php endif; ?>
                </label>
                <?php if ($is_sales): ?>
                    <input type="text" class="form-control bg-light fw-bold text-primary" value="<?php echo date('d/m/Y, H:i'); ?> WIB" readonly style="font-size:15px; cursor:not-allowed;">
                    <input type="hidden" name="tgl_follow_up" value="<?php echo date('Y-m-d\TH:i'); ?>">
                <?php else: ?>
                    <input type="datetime-local" class="form-control" id="tgl_follow_up" name="tgl_follow_up" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                <?php endif; ?>
            </div>

            <!-- Respon Customer -->
            <div class="mb-4">
                <label class="form-label fw-bold text-dark">Respon Customer <span class="text-danger">*</span></label>
                <div class="p-3 bg-light rounded-4 border">
                    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="respon_radio" id="respon1" value="Tidak ada respon" checked><label class="form-check-label fw-semibold" for="respon1">Tidak ada respon</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="respon_radio" id="respon2" value="Tidak tertarik"><label class="form-check-label fw-semibold" for="respon2">Tidak tertarik</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="respon_radio" id="respon3" value="Hanya bertanya"><label class="form-check-label fw-semibold" for="respon3">Hanya bertanya</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="respon_radio" id="respon4" value="Muncul keinginan membeli"><label class="form-check-label fw-semibold text-primary" for="respon4">Muncul keinginan membeli</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="respon_radio" id="respon5" value="Deal untuk beli"><label class="form-check-label fw-bold text-success" for="respon5">🎉 Deal untuk beli</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="respon_radio" id="respon6" value="Lainnya"><label class="form-check-label fw-semibold" for="respon6">Lainnya</label></div>
                </div>
            </div>

            <div class="mb-4" id="respon_lainnya_container" style="display: none;">
                <label for="respon_lainnya" class="form-label fw-bold text-dark">Respon Lainnya <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="respon_lainnya" name="respon_lainnya" placeholder="Tulis respon customer...">
            </div>

            <!-- Keterangan / Tindak Lanjut -->
            <div class="mb-4">
                <label for="keterangan" class="form-label fw-bold text-dark">Keterangan / Tindak Lanjut</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Masukkan detail pembicaraan atau rencana tindak lanjut..."></textarea>
            </div>

            <!-- Nomor Invoice -->
            <div class="mb-4">
                <label for="no_inv" class="form-label fw-bold text-dark">Nomor Invoice (Opsional)</label>
                <input type="text" class="form-control" id="no_inv" name="no_inv" placeholder="Masukkan nomor invoice jika ada">
            </div>

            <!-- Upload Media 1, 2, 3 -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3 mb-md-0">
                    <label for="media1" class="form-label fw-bold text-danger">
                        <i class="bi bi-chat-left-text-fill me-1"></i> Bukti Chat / Screenshot WA (Media 1) <span class="badge bg-danger ms-1">WAJIB UPLOAD</span>
                    </label>
                    <input class="form-control border-danger border-2" type="file" id="media1" name="media1" accept="<?php echo $file_accept_types; ?>" required>
                    <small style="font-size:11px;" class="text-danger fw-bold">*Wajib upload screenshot / foto bukti chat WA (Max 10MB)</small>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <label for="media2" class="form-label fw-bold text-dark">Media 2 (Opsional)</label>
                    <input class="form-control" type="file" id="media2" name="media2" accept="<?php echo $file_accept_types; ?>">
                    <small style="font-size:11px;" class="text-muted">*Opsional (Max 10MB)</small>
                </div>
                <div class="col-md-4">
                    <label for="media3" class="form-label fw-bold text-dark">Media 3 (Opsional)</label>
                    <input class="form-control" type="file" id="media3" name="media3" accept="<?php echo $file_accept_types; ?>">
                    <small style="font-size:11px;" class="text-muted">*Opsional (Max 10MB)</small>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="followup_view.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-light border fw-bold px-4">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-check-circle-fill me-1"></i> Simpan Follow Up</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const responRadios = document.querySelectorAll('input[name="respon_radio"]');
    const lainnyaContainer = document.getElementById('respon_lainnya_container');
    const lainnyaInput = document.getElementById('respon_lainnya');
    const fuForm = document.querySelector('form');
    const media1Input = document.getElementById('media1');

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

    if (fuForm && media1Input) {
        fuForm.addEventListener('submit', function(e) {
            if (!media1Input.files || media1Input.files.length === 0) {
                e.preventDefault();
                alert('❌ GAGAL SIMPAN: Anda WAJIB mengunggah Bukti Chat / Screenshot WA (Media 1) terlebih dahulu sebelum dapat menyimpan Follow Up!');
                media1Input.focus();
                media1Input.classList.add('is-invalid');
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>