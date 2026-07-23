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
    #loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        color: white;
    }
</style>

<div id="loading-overlay" style="display: none;">
    <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3">Memproses data... Mohon tunggu.</h4>
</div>

<h1><i class="bi bi-arrow-down-up"></i> Impor Data Customer</h1>
<p>Gunakan fitur ini untuk mengimpor data customer baru dalam jumlah besar. Pastikan file Excel Anda sesuai dengan format template yang disediakan.<br>
        <strong>NOTE :</strong> Untuk nomor telpon <strong>perlu diawali dengan 0</strong> bukan 62. Contoh nomor telepon yang benar : <strong>08123456789</strong></p>

<hr>

<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-cloud-arrow-up-fill"></i> Unggah (Upload) Customer via XLSX</h5>
    </div>
    <div class="card-body">
        <p>Gunakan fitur ini untuk mengimpor data customer baru dalam jumlah besar. Pastikan file Excel Anda sesuai dengan format template yang disediakan.</p>
        <a href="template_customer.xlsx" class="btn btn-success mb-3" download><i class="bi bi-file-earmark-excel"></i> Unduh Template Excel</a>
        
        <form id="upload-form" action="customer_bulk_upload_process.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="customer_file" class="form-label">Pilih file .xlsx</label>
                <input class="form-control" type="file" id="customer_file" name="customer_file" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-primary">Unggah dan Proses File</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('upload-form');
    const loadingOverlay = document.getElementById('loading-overlay');

    // --- Logika untuk menampilkan loading saat form di-submit ---
    if (uploadForm) {
        uploadForm.addEventListener('submit', function() {
            const fileInput = document.getElementById('customer_file');
            if (fileInput.files.length > 0) {
                loadingOverlay.style.display = 'flex';
            }
        });
    }

    // --- Logika untuk menampilkan pop-up setelah redirect ---
    <?php if (isset($_SESSION['upload_status'])): ?>
        
        // Perbaikan: Sembunyikan loading overlay terlebih dahulu
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
        // Hapus session setelah ditampilkan
        unset($_SESSION['upload_status']);
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_message_info']);
        unset($_SESSION['flash_message_error']);
        ?>
    <?php endif; ?>
});
</script>

<?php require_once 'includes/floating_menu.php'; ?>

<?php require_once 'includes/footer.php'; ?>