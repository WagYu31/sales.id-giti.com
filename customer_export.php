<?php
$page_title = 'Impor & Ekspor Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

// Halaman ini hanya untuk Superadmin dan Sales
if (!isset($_SESSION['role'])) {
    die("Akses ditolak.");
}

$sql_where_conditions = ["c.deleted_at IS NULL"];
$params = [];
$types = '';

if ($_SESSION['role'] === 'sales') {
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

$sql = "
    SELECT 
        c.id, c.nama_toko, ca.kota, c.kategori, s.nama_lengkap,
        GROUP_CONCAT(DISTINCT cp.nama_pic SEPARATOR ', ') as all_pics,
        GROUP_CONCAT(DISTINCT cp.tlp_pic SEPARATOR ', ') as all_phones
    FROM customers c
    LEFT JOIN (
        SELECT customer_id, MIN(kota) as kota FROM customer_addresses WHERE deleted_at IS NULL GROUP BY customer_id
    ) ca ON c.id = ca.customer_id
    LEFT JOIN customer_pics cp ON c.id = cp.customer_id AND cp.deleted_at IS NULL
    LEFT JOIN sales s ON s.id = c.sales_id AND s.deleted_at IS NULL
    {$where_clause}
    GROUP BY c.id, c.nama_toko, ca.kota, c.kategori, s.nama_lengkap
    ORDER BY c.nama_toko ASC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$customers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    #loading-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.6); z-index: 9999;
        display: flex; justify-content: center; align-items: center;
        flex-direction: column; color: white;
    }
</style>

<div id="loading-overlay" style="display: none;">
    <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3">Memproses data... Mohon tunggu.</h4>
</div>

<h1><i class="bi bi-arrow-down-up"></i> Ekspor Data Customer</h1>
<p class="text-muted">Gunakan halaman ini untuk mengunduh data untuk keperluan follow-up.</p>
<hr>

<div class="card">
    <div class="card-header">
         <h5><i class="bi bi-cloud-arrow-down-fill"></i> Unduh (Download) Data Customer</h5>
    </div>
    <div class="card-body">
         <p>Pilih customer dari daftar di bawah ini yang datanya ingin Anda unduh sebagai file Excel.</p>
        <form id="downloadForm" action="customer_bulk_download_process.php" method="POST">
            <input type="hidden" name="download_token" id="download_token">
            <div class="d-flex justify-content-between mb-3">
                <button type="submit" class="btn btn-info"><i class="bi bi-download"></i> Unduh Data Terpilih</button>
                <input type="text" id="searchInput" class="form-control w-50" placeholder="Cari nama toko, kota, atau PIC...">
            </div>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover" id="customerTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;"><input type="checkbox" id="selectAll"></th>
                            <th>Nama Toko</th>
                            <th>Kategori</th>
                            <th>Kota</th>
                            <th>PIC</th>
                            <th>Telepon</th>
                            <th>Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><input type="checkbox" name="customer_ids[]" value="<?php echo $customer['id']; ?>" class="customer-checkbox"></td>
                            <td class="text-wrap"><?php echo htmlspecialchars($customer['nama_toko']); ?></td>
                            <td><?php echo htmlspecialchars($customer['kategori'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['kota'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['all_pics'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['all_phones'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['nama_lengkap'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#customerTable tbody tr');
    const downloadForm = document.getElementById('downloadForm');
    const loadingOverlay = document.getElementById('loading-overlay');
    const tokenInput = document.getElementById('download_token');

    selectAllCheckbox.addEventListener('change', function(e) {
        const visibleCheckboxes = document.querySelectorAll('#customerTable tbody tr:not([style*="display: none"]) .customer-checkbox');
        visibleCheckboxes.forEach(checkbox => {
            checkbox.checked = e.target.checked;
        });
    });

    searchInput.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        selectAllCheckbox.checked = false;
        tableRows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });

    // --- Logika Baru untuk Notifikasi Pop-up ---
    downloadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selected = document.querySelectorAll('.customer-checkbox:checked').length;
        if (selected === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Tidak Ada Data Terpilih',
                text: 'Silakan pilih setidaknya satu customer untuk diunduh.',
            });
            return;
        }

        loadingOverlay.style.display = 'flex';
        const token = new Date().getTime(); // Buat token unik
        tokenInput.value = token;
        
        // Buat cookie untuk dilacak
        document.cookie = `download_token=${token};path=/`;

        let timer = setInterval(function() {
            const cookies = document.cookie.split(';').map(c => c.trim());
            const ourCookie = cookies.find(c => c.startsWith('download_token='));

            if (!ourCookie) { // Jika cookie sudah dihapus oleh backend
                clearInterval(timer);
                loadingOverlay.style.display = 'none';
                Swal.fire({
                    icon: 'success',
                    title: 'Download Berhasil!',
                    text: 'File Excel Anda sedang diunduh.',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }, 1000); // Cek setiap 1 detik

        // Lanjutkan submit form setelah semua siap
        this.submit();
    });
});
</script>

<?php require_once 'includes/floating_menu.php'; ?>
<?php require_once 'includes/footer.php'; ?>