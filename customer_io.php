<?php
$page_title = 'Impor Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

// Halaman ini hanya untuk Superadmin
if ($_SESSION['role'] !== 'superadmin') {
    die("Akses ditolak. Halaman ini hanya untuk Superadmin.");
}

// Query untuk mengambil semua data customer beserta detailnya untuk tabel unduhan
$customers = [];
$result = $conn->query("
    SELECT 
        c.id, 
        c.nama_toko, 
        ca.kota,
        GROUP_CONCAT(DISTINCT cp.nama_pic SEPARATOR ', ') as all_pics,
        GROUP_CONCAT(DISTINCT cp.tlp_pic SEPARATOR ', ') as all_phones
    FROM customers c
    LEFT JOIN (SELECT customer_id, MIN(kota) as kota FROM customer_addresses GROUP BY customer_id) ca ON c.id = ca.customer_id
    LEFT JOIN customer_pics cp ON c.id = cp.customer_id AND cp.deleted_at IS NULL
    WHERE c.deleted_at IS NULL 
    GROUP BY c.id, c.nama_toko, ca.kota
    ORDER BY c.nama_toko ASC
");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}
?>

<style>
.io-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.io-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.io-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.io-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 650px;
}

.upload-dropzone {
    border: 2px dashed #3B82F6;
    background: #F8FAFF;
    border-radius: 16px;
    padding: 36px 24px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-dropzone:hover {
    background: #EFF6FF;
    border-color: #1D4ED8;
    transform: translateY(-2px);
}

#loading-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background-color: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    color: white;
}
</style>

<div id="loading-overlay" style="display: none;">
    <div class="spinner-border text-primary" role="status" style="width: 3.5rem; height: 3.5rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3 fw-bold" style="font-family:'Plus Jakarta Sans', sans-serif;">Memproses data... Mohon tunggu.</h4>
</div>

<!-- Hero Header -->
<div class="io-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Unggah Data Customer</span>
            </div>
            <h1 class="io-hero-title">Unggah & Impor Data Customer 🚀</h1>
            <p class="io-hero-subtitle">Import data customer baru dalam jumlah besar via file Excel (.xlsx). Pastikan format nomor telepon diawali angka <strong>0</strong> (contoh: <code>08123456789</code>).</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="template_customer.xlsx" class="btn btn-emerald text-white fw-bold shadow-lg" download style="background: linear-gradient(135deg, #059669 0%, #10B981 100%); border:none; padding:10px 20px; border-radius:12px;">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Unduh Template Excel
            </a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0" style="border-radius:20px;">
    <div class="card-body p-4 p-md-5">
        <form id="upload-form" action="customer_bulk_upload_process.php" method="POST" enctype="multipart/form-data">
            <div class="upload-dropzone mb-4" onclick="document.getElementById('customer_file').click();">
                <div class="mb-3">
                    <i class="bi bi-cloud-arrow-up-fill text-primary" style="font-size: 52px;"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1" style="font-family:'Plus Jakarta Sans', sans-serif;">Pilih atau Tarik File Excel (.XLSX) Ke Sini</h5>
                <p class="text-muted small mb-0">Ukuran file maksimal 10MB. Gunakan template standar yang telah disediakan.</p>
                <input class="d-none" type="file" id="customer_file" name="customer_file" accept=".xlsx" required onchange="updateFileName(this)">
                <div id="file-name-display" class="mt-3 fw-bold text-success" style="display:none;"></div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="customer_management.php" class="btn btn-light border fw-bold px-4">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-upload me-1"></i> Unggah dan Proses File</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function updateFileName(input) {
    const fileNameDisplay = document.getElementById('file-name-display');
    if (input.files && input.files[0]) {
        fileNameDisplay.textContent = '📄 File Terpilih: ' + input.files[0].name;
        fileNameDisplay.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('upload-form');
    const loadingOverlay = document.getElementById('loading-overlay');

    if (uploadForm) {
        uploadForm.addEventListener('submit', function() {
            const fileInput = document.getElementById('customer_file');
            if (fileInput.files.length > 0) {
                loadingOverlay.style.display = 'flex';
            }
        });
    }

    <?php if (isset($_SESSION['upload_status'])): ?>
        if(loadingOverlay) {
            loadingOverlay.style.display = 'none';
        }

        <?php if ($_SESSION['upload_status'] == 'success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Upload Berhasil!',
                html: '<?php echo addslashes($_SESSION['flash_message']); ?>' + 
                      '<?php if (isset($_SESSION['flash_message_info'])) echo '<br><br>' . addslashes($_SESSION['flash_message_info']); ?>',
                showConfirmButton: true
            });
        <?php elseif ($_SESSION['upload_status'] == 'error'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Upload Gagal',
                html: '<?php echo addslashes($_SESSION['flash_message_error']); ?>',
                showConfirmButton: true
            });
        <?php endif; ?>
        
        <?php
        unset($_SESSION['upload_status']);
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_message_info']);
        unset($_SESSION['flash_message_error']);
        ?>
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>