<?php
require_once 'includes/db.php';
require_once 'includes/media_compressor.php';

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
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/aac', 'audio/flac', 'audio/x-ms-wma'
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
                        // Server-side Automatic Image WebP Optimization & Compression
                        $finalFilename = optimizeUploadedImage($target_file, 80, 1920);
                        $media_paths[$i-1] = $finalFilename;
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

<div class="card border-0 shadow-sm" style="border-radius:24px; border: 1.5px solid #E2E8F0;">
    <div class="card-body p-4 p-md-5">
        <form action="followup_add.php?customer_id=<?php echo $customer_id; ?>" method="POST" enctype="multipart/form-data">
            
            <!-- Row 1: Tanggal & Waktu Follow Up + Nomor Invoice (2-Column) -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="tgl_follow_up" class="form-label fw-bold text-dark d-flex align-items-center justify-content-between">
                        <span>Tanggal & Waktu Follow Up</span>
                        <?php if ($is_sales): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary fw-bold" style="font-size:10.5px;">
                                🔒 Realtime Server
                            </span>
                        <?php endif; ?>
                    </label>
                    <?php if ($is_sales): ?>
                        <input type="text" class="form-control bg-light fw-bold text-primary" value="<?php echo date('d/m/Y, H:i'); ?> WIB" readonly style="font-size:14.5px; cursor:not-allowed; border-radius:14px; padding: 12px 16px;">
                        <input type="hidden" name="tgl_follow_up" value="<?php echo date('Y-m-d\TH:i'); ?>">
                    <?php else: ?>
                        <input type="datetime-local" class="form-control" id="tgl_follow_up" name="tgl_follow_up" value="<?php echo date('Y-m-d\TH:i'); ?>" required style="border-radius:14px; padding: 12px 16px;">
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="no_inv" class="form-label fw-bold text-dark">Nomor Invoice (Opsional)</label>
                    <input type="text" class="form-control" id="no_inv" name="no_inv" placeholder="Masukkan nomor invoice jika ada" style="border-radius:14px; padding: 12px 16px;">
                </div>
            </div>

            <!-- Respon Customer (Interactive Segmented Option Chips Grid) -->
            <div class="mb-4">
                <label class="form-label fw-bold text-dark d-block mb-2.5">
                    Respon Customer <span class="text-danger">*</span>
                </label>
                <div class="respon-chip-grid">
                    <label class="respon-chip-card active">
                        <input type="radio" name="respon_radio" value="Tidak ada respon" checked>
                        <div class="chip-content">
                            <i class="bi bi-dash-circle text-secondary fs-5"></i>
                            <span>Tidak Ada Respon</span>
                        </div>
                    </label>
                    
                    <label class="respon-chip-card">
                        <input type="radio" name="respon_radio" value="Tidak tertarik">
                        <div class="chip-content">
                            <i class="bi bi-x-circle text-danger fs-5"></i>
                            <span>Tidak Tertarik</span>
                        </div>
                    </label>

                    <label class="respon-chip-card">
                        <input type="radio" name="respon_radio" value="Hanya bertanya">
                        <div class="chip-content">
                            <i class="bi bi-question-circle text-info fs-5"></i>
                            <span>Hanya Bertanya</span>
                        </div>
                    </label>

                    <label class="respon-chip-card">
                        <input type="radio" name="respon_radio" value="Muncul keinginan membeli">
                        <div class="chip-content">
                            <i class="bi bi-bag-check text-primary fs-5"></i>
                            <span>Muncul Keinginan Membeli</span>
                        </div>
                    </label>

                    <label class="respon-chip-card highlight-deal">
                        <input type="radio" name="respon_radio" value="Deal untuk beli">
                        <div class="chip-content">
                            <i class="bi bi-trophy-fill text-success fs-5"></i>
                            <span>🎉 Deal Untuk Beli</span>
                        </div>
                    </label>

                    <label class="respon-chip-card">
                        <input type="radio" name="respon_radio" value="Lainnya">
                        <div class="chip-content">
                            <i class="bi bi-pencil-square text-warning fs-5"></i>
                            <span>Lainnya...</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="mb-4" id="respon_lainnya_container" style="display: none;">
                <label for="respon_lainnya" class="form-label fw-bold text-dark">Respon Lainnya <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="respon_lainnya" name="respon_lainnya" placeholder="Tulis rincian respon customer..." style="border-radius:14px; padding: 12px 16px;">
            </div>

            <!-- Keterangan / Tindak Lanjut -->
            <div class="mb-4">
                <label for="keterangan" class="form-label fw-bold text-dark">Keterangan / Tindak Lanjut</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Masukkan detail pembicaraan atau rencana tindak lanjut..." style="border-radius:16px; padding: 14px 16px;"></textarea>
            </div>

            <!-- Upload Media 1, 2, 3 (Interactive Drag & Drop Upload Zone) -->
            <div class="row mb-4">
                <!-- Media 1 (Wajib Upload) -->
                <div class="col-md-4 mb-3 mb-md-0">
                    <label class="form-label fw-bold text-danger d-flex align-items-center justify-content-between mb-2">
                        <span><i class="bi bi-chat-left-text-fill me-1"></i> Bukti Chat WA (Media 1)</span>
                        <span class="badge bg-danger">WAJIB UPLOAD</span>
                    </label>
                    <div class="drop-zone-box border-danger" id="dropZone1" onclick="document.getElementById('media1').click();">
                        <input type="file" id="media1" name="media1" accept="<?php echo $file_accept_types; ?>" required style="display:none;" onchange="handleFileSelect(this, 'preview1', 'dropZone1')">
                        <div class="drop-zone-content" id="preview1">
                            <i class="bi bi-cloud-arrow-up-fill text-danger fs-1 mb-1"></i>
                            <div class="fw-bold text-dark" style="font-size:13.5px;">Tarik & Lepas File Di Sini</div>
                            <div class="text-muted" style="font-size:11.5px;">atau <span class="text-danger fw-bold">Klik untuk memilih file</span></div>
                            <div class="text-danger fw-bold mt-1" style="font-size:10.5px;">*Wajib Upload Screenshot WA (Max 10MB)</div>
                        </div>
                    </div>
                </div>

                <!-- Media 2 (Opsional) -->
                <div class="col-md-4 mb-3 mb-md-0">
                    <label class="form-label fw-bold text-dark mb-2">Media 2 (Opsional)</label>
                    <div class="drop-zone-box" id="dropZone2" onclick="document.getElementById('media2').click();">
                        <input type="file" id="media2" name="media2" accept="<?php echo $file_accept_types; ?>" style="display:none;" onchange="handleFileSelect(this, 'preview2', 'dropZone2')">
                        <div class="drop-zone-content" id="preview2">
                            <i class="bi bi-cloud-arrow-up text-primary fs-1 mb-1"></i>
                            <div class="fw-bold text-dark" style="font-size:13.5px;">Tarik & Lepas File Di Sini</div>
                            <div class="text-muted" style="font-size:11.5px;">atau <span class="text-primary fw-bold">Klik untuk memilih file</span></div>
                            <div class="text-muted mt-1" style="font-size:10.5px;">*Opsional (Max 10MB)</div>
                        </div>
                    </div>
                </div>

                <!-- Media 3 (Opsional) -->
                <div class="col-md-4">
                    <label class="form-label fw-bold text-dark mb-2">Media 3 (Opsional)</label>
                    <div class="drop-zone-box" id="dropZone3" onclick="document.getElementById('media3').click();">
                        <input type="file" id="media3" name="media3" accept="<?php echo $file_accept_types; ?>" style="display:none;" onchange="handleFileSelect(this, 'preview3', 'dropZone3')">
                        <div class="drop-zone-content" id="preview3">
                            <i class="bi bi-cloud-arrow-up text-primary fs-1 mb-1"></i>
                            <div class="fw-bold text-dark" style="font-size:13.5px;">Tarik & Lepas File Di Sini</div>
                            <div class="text-muted" style="font-size:11.5px;">atau <span class="text-primary fw-bold">Klik untuk memilih file</span></div>
                            <div class="text-muted mt-1" style="font-size:10.5px;">*Opsional (Max 10MB)</div>
                        </div>
                    </div>
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
function handleFileSelect(input, previewId, zoneId) {
    const previewEl = document.getElementById(previewId);
    const zoneEl = document.getElementById(zoneId);
    if (!previewEl || !zoneEl || !input.files || !input.files[0]) return;

    const file = input.files[0];
    const originalSizeMB = (file.size / (1024 * 1024)).toFixed(2);
    zoneEl.classList.add('has-file');

    // Auto Client-Side WebP Canvas Compression for images (except GIF)
    if (file.type.startsWith('image/') && !file.type.includes('gif')) {
        previewEl.innerHTML = `
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div class="fw-bold text-primary" style="font-size:12px;">⚡ Mengompresi Gambar (${originalSizeMB} MB)...</div>
        `;

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                const maxWidth = 1920;
                let width = img.width;
                let height = img.height;

                if (width > maxWidth || height > maxWidth) {
                    if (width >= height) {
                        height = Math.round((height / width) * maxWidth);
                        width = maxWidth;
                    } else {
                        width = Math.round((width / height) * maxWidth);
                        height = maxWidth;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(function(blob) {
                    if (!blob) {
                        renderFilePreview(file, originalSizeMB, previewEl);
                        return;
                    }

                    const rawName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                    const newFilename = rawName + '.webp';
                    const compressedFile = new File([blob], newFilename, {
                        type: 'image/webp',
                        lastModified: Date.now()
                    });

                    // Assign compressed file to File Input using DataTransfer API
                    try {
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(compressedFile);
                        input.files = dataTransfer.files;
                    } catch(err) {
                        console.warn("DataTransfer fallback:", err);
                    }

                    const newSizeMB = (compressedFile.size / (1024 * 1024)).toFixed(2);
                    const savedPct = Math.max(0, Math.round((1 - (compressedFile.size / file.size)) * 100));

                    previewEl.innerHTML = `
                        <i class="bi bi-file-earmark-image-fill text-success fs-1 mb-1"></i>
                        <div class="fw-bold text-dark text-truncate px-2" style="font-size:13px;" title="${newFilename}">${newFilename}</div>
                        <div class="badge bg-success text-white fw-bold mt-1" style="font-size:11px;">⚡ Auto-WebP (${newSizeMB} MB)</div>
                        <div class="text-success fw-bold mt-1" style="font-size:10.5px;">Ukuran Berkurang -${savedPct}% (${originalSizeMB} MB ➔ ${newSizeMB} MB)</div>
                    `;
                }, 'image/webp', 0.82);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        renderFilePreview(file, originalSizeMB, previewEl);
    }
}

function renderFilePreview(file, sizeMB, previewEl) {
    let iconClass = 'bi-file-earmark-check-fill text-success';
    if (file.type.startsWith('video/')) iconClass = 'bi-file-earmark-play-fill text-primary';
    else if (file.type.includes('pdf')) iconClass = 'bi-file-earmark-pdf-fill text-danger';

    previewEl.innerHTML = `
        <i class="bi ${iconClass} fs-1 mb-1"></i>
        <div class="fw-bold text-dark text-truncate px-2" style="font-size:13px;" title="${file.name}">${file.name}</div>
        <div class="badge bg-primary-subtle text-primary fw-bold mt-1" style="font-size:11px;">✓ Selected (${sizeMB} MB)</div>
        <div class="text-muted mt-1" style="font-size:10px;">Klik atau lepas file lain untuk mengganti</div>
    `;
}

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

    // Drag & Drop Listeners Setup
    ['dropZone1', 'dropZone2', 'dropZone3'].forEach((zoneId, idx) => {
        const zone = document.getElementById(zoneId);
        const input = document.getElementById('media' + (idx + 1));
        const previewId = 'preview' + (idx + 1);
        if (!zone || !input) return;

        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
            }, false);
        });

        zone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                input.files = files;
                handleFileSelect(input, previewId, zoneId);
            }
        }, false);
    });

    if (fuForm && media1Input) {
        fuForm.addEventListener('submit', function(e) {
            if (!media1Input.files || media1Input.files.length === 0) {
                e.preventDefault();
                alert('❌ GAGAL SIMPAN: Anda WAJIB mengunggah Bukti Chat / Screenshot WA (Media 1) terlebih dahulu sebelum dapat menyimpan Follow Up!');
                media1Input.focus();
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>