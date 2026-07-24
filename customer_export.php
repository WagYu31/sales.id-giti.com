<?php
$page_title = 'Ekspor Data Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['role'])) {
    die("Akses ditolak.");
}

// Pastikan tabel download_requests ada
$conn->query("
CREATE TABLE IF NOT EXISTS download_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_id INT NOT NULL,
    sales_name VARCHAR(255) NOT NULL,
    customer_ids TEXT NOT NULL,
    jumlah_data INT NOT NULL,
    alasan TEXT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    approved_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$is_superadmin = ($_SESSION['role'] === 'superadmin');
$is_sales = ($_SESSION['role'] === 'sales');
$sales_id = $_SESSION['user_id'];

// Ambil status permintaan unduh terbaru khusus Sales
$sales_request_status = null;
$sales_request_id = 0;
if ($is_sales) {
    $stmt_req = $conn->prepare("SELECT id, status, jumlah_data, created_at FROM download_requests WHERE sales_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_req->bind_param('i', $sales_id);
    $stmt_req->execute();
    $res_req = $stmt_req->get_result();
    if ($res_req && $res_req->num_rows > 0) {
        $last_req = $res_req->fetch_assoc();
        $sales_request_status = $last_req['status'];
        $sales_request_id = $last_req['id'];
    }
    $stmt_req->close();
}

// Ambil semua daftar permintaan izin unduh khusus Superadmin
$pending_requests = [];
if ($is_superadmin) {
    $res_p = $conn->query("SELECT id, sales_name, jumlah_data, alasan, status, created_at FROM download_requests ORDER BY id DESC LIMIT 50");
    if ($res_p) {
        $pending_requests = $res_p->fetch_all(MYSQLI_ASSOC);
    }
}

// Query Customer Data
$sql_where_conditions = ["c.deleted_at IS NULL"];
$params = [];
$types = '';

if ($is_sales) {
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $sales_id;
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
.exp-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.exp-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.exp-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
}

#loading-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background-color: rgba(15, 23, 42, 0.75); backdrop-filter: blur(8px); z-index: 9999;
    display: flex; justify-content: center; align-items: center;
    flex-direction: column; color: white;
}
</style>

<div id="loading-overlay" style="display: none;">
    <div class="spinner-border text-primary" role="status" style="width: 3.5rem; height: 3.5rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3 fw-bold" style="font-family:'Plus Jakarta Sans', sans-serif;">Memproses data... Mohon tunggu.</h4>
</div>

<!-- Hero Header -->
<div class="exp-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Ekspor Data Customer</span>
            </div>
            <h1 class="exp-hero-title">Ekspor & Unduh Data Customer 📥</h1>
            <p class="exp-hero-subtitle">Gunakan halaman ini untuk memilih dan mengunduh data customer sebagai file Excel.</p>
        </div>
    </div>
</div>

<!-- SUPERADMIN VIEW: Manajemen Izin Unduh Sales -->
<?php if ($is_superadmin): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:20px;">
    <div class="card-header bg-white border-bottom p-4">
        <h5 class="mb-0 fw-bold text-dark d-flex align-items-center justify-content-between">
            <span><i class="bi bi-shield-lock-fill text-warning me-2"></i> Permintaan Izin Unduh Dari Sales</span>
            <span class="badge bg-primary rounded-pill"><?php echo count($pending_requests); ?> Permintaan</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($pending_requests)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>NAMA SALES</th>
                            <th>JML DATA</th>
                            <th>ALASAN UNDUH</th>
                            <th>TANGGAL REQUEST</th>
                            <th>STATUS</th>
                            <th class="text-center">AKSI ADMIN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $req): ?>
                        <tr id="request-row-<?php echo $req['id']; ?>">
                            <td class="fw-bold text-dark">👤 <?php echo htmlspecialchars($req['sales_name']); ?></td>
                            <td><span class="badge bg-info"><?php echo $req['jumlah_data']; ?> Customer</span></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($req['alasan'] ?? 'Untuk follow up'); ?></td>
                            <td class="small"><?php echo date('d/m/Y, H:i', strtotime($req['created_at'])); ?></td>
                            <td>
                                <?php if ($req['status'] === 'Pending'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Menunggu Persetujuan</span>
                                <?php elseif ($req['status'] === 'Approved'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Disetujui</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i>Ditolak</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($req['status'] === 'Pending'): ?>
                                    <div class="d-flex justify-content-center gap-1">
                                        <button class="btn btn-sm btn-success fw-bold action-perm-btn" data-id="<?php echo $req['id']; ?>" data-action="approve_request">
                                            <i class="bi bi-check-lg me-1"></i> Setujui
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger fw-bold action-perm-btn" data-id="<?php echo $req['id']; ?>" data-action="reject_request">
                                            <i class="bi bi-x-lg me-1"></i> Tolak
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="small text-muted fw-semibold">Selesai Diproses</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center p-4 text-muted">Belum ada permintaan izin unduh dari sales.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Form Download Data Customer -->
<div class="card border-0 shadow-sm" style="border-radius:20px;">
    <div class="card-body p-4 p-md-5">
        <p class="text-muted fw-semibold">Pilih customer dari daftar di bawah ini yang datanya ingin Anda unduh sebagai file Excel.</p>
        
        <form id="downloadForm" action="customer_bulk_download_process.php" method="POST">
            <input type="hidden" name="download_token" id="download_token">
            
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <?php if ($is_superadmin): ?>
                        <button type="submit" class="btn btn-primary fw-bold shadow-sm px-4" id="btnDownloadExecute">
                            <i class="bi bi-download me-1"></i> Unduh Data Terpilih
                        </button>
                    <?php elseif ($sales_request_status === 'Approved'): ?>
                        <button type="submit" class="btn btn-success fw-bold shadow-sm px-4" id="btnDownloadExecute">
                            <i class="bi bi-check-circle-fill me-1"></i> Unduh Data Terpilih (Disetujui Superadmin)
                        </button>
                    <?php elseif ($sales_request_status === 'Pending'): ?>
                        <button type="button" class="btn btn-warning fw-bold shadow-sm px-4 text-dark" id="btnPendingNotice">
                            <i class="bi bi-hourglass-split me-1"></i> Menunggu Izin Superadmin
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-warning fw-extrabold shadow-lg px-4 text-dark" id="btnRequestPermission">
                            <i class="bi bi-shield-lock-fill me-1"></i> 🔒 Minta Izin Unduh Ke Superadmin
                        </button>
                    <?php endif; ?>
                </div>

                <div style="min-width:260px;">
                    <input type="text" id="searchInput" class="form-control fw-semibold" placeholder="🔍 Cari nama toko, kota, atau PIC..." style="border-radius:10px;">
                </div>
            </div>

            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table table-hover align-middle sortable-table mb-0" id="customerTable">
                    <thead class="table-dark-header">
                        <tr>
                            <th style="width: 5%;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th>NAMA TOKO</th>
                            <th>KATEGORI</th>
                            <th>KOTA</th>
                            <th>PIC</th>
                            <th>TELEPON</th>
                            <th>SALES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><input type="checkbox" name="customer_ids[]" value="<?php echo $customer['id']; ?>" class="customer-checkbox form-check-input"></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($customer['nama_toko']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($customer['kategori'] ?? '-'); ?></span></td>
                            <td class="small fw-semibold"><?php echo htmlspecialchars($customer['kota'] ?? '-'); ?></td>
                            <td class="small"><?php echo htmlspecialchars($customer['all_pics'] ?? '-'); ?></td>
                            <td class="small"><?php echo htmlspecialchars($customer['all_phones'] ?? '-'); ?></td>
                            <td class="small fw-semibold"><?php echo htmlspecialchars($customer['nama_lengkap'] ?? '-'); ?></td>
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
    const btnRequestPermission = document.getElementById('btnRequestPermission');
    const btnPendingNotice = document.getElementById('btnPendingNotice');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            const visibleCheckboxes = document.querySelectorAll('#customerTable tbody tr:not([style*="display: none"]) .customer-checkbox');
            visibleCheckboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
            tableRows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }

    // Handlers untuk Sales Meminta Izin Unduh
    if (btnRequestPermission) {
        btnRequestPermission.addEventListener('click', function() {
            const selectedCheckboxes = document.querySelectorAll('.customer-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Belum Ada Data Terpilih',
                    text: 'Silakan centang/pilih customer yang ingin Anda minta izin unduh terlebih dahulu.',
                });
                return;
            }

            const ids = Array.from(selectedCheckboxes).map(cb => cb.value).join(',');

            Swal.fire({
                title: '🔒 Minta Izin Unduh Ke Superadmin',
                html: `<p class="small text-muted mb-2">Anda memilih <strong>${selectedCheckboxes.length} customer</strong>. Masukkan alasan pengunduhan data:</p>
                       <textarea id="alasan_input" class="swal2-textarea" placeholder="Tulis alasan unduh (misal: Untuk follow up area Bekasi)..." style="width:100%; height:80px;"></textarea>`,
                showCancelButton: true,
                confirmButtonText: 'Kirim Permintaan Izin',
                cancelButtonText: 'Batal',
                preConfirm: () => {
                    const alasan = document.getElementById('alasan_input').value;
                    if (!alasan.trim()) {
                        Swal.showValidationMessage('Alasan unduh wajib diisi!');
                    }
                    return alasan;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    loadingOverlay.style.display = 'flex';
                    fetch('download_permission_action.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=request_download&customer_ids=${encodeURIComponent(ids)}&alasan=${encodeURIComponent(result.value)}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        loadingOverlay.style.display = 'none';
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Permintaan Dikirim! ⏳',
                                text: data.message,
                                confirmButtonText: 'Pengertian'
                            }).then(() => window.location.reload());
                        } else {
                            Swal.fire('Gagal', data.message, 'error');
                        }
                    })
                    .catch(() => {
                        loadingOverlay.style.display = 'none';
                        Swal.fire('Error', 'Terjadi kesalahan koneksi.', 'error');
                    });
                }
            });
        });
    }

    if (btnPendingNotice) {
        btnPendingNotice.addEventListener('click', function() {
            Swal.fire({
                icon: 'info',
                title: '⏳ Menunggu Persetujuan Admin',
                text: 'Permintaan izin unduh Anda sedang dalam peninjauan oleh Superadmin. Silakan cek kembali beberapa saat lagi.',
            });
        });
    }

    // Action Handler untuk Superadmin Menyetujui/Menolak Izin
    document.querySelectorAll('.action-perm-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const reqId = this.dataset.id;
            const action = this.dataset.action;
            const actionText = (action === 'approve_request') ? 'menyetujui' : 'menolak';

            if (confirm(`Apakah Anda yakin ingin ${actionText} permintaan izin unduh ini?`)) {
                fetch('download_permission_action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=${action}&request_id=${reqId}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Gagal: ' + data.message);
                    }
                });
            }
        });
    });

    // Form Submit Handler untuk Download File
    if (downloadForm) {
        downloadForm.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.customer-checkbox:checked').length;
            if (selected === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Tidak Ada Data Terpilih',
                    text: 'Silakan pilih setidaknya satu customer untuk diunduh.',
                });
                return;
            }

            loadingOverlay.style.display = 'flex';
            const token = new Date().getTime();
            tokenInput.value = token;
            document.cookie = `download_token=${token};path=/`;

            let timer = setInterval(function() {
                const cookies = document.cookie.split(';').map(c => c.trim());
                const ourCookie = cookies.find(c => c.startsWith('download_token='));

                if (!ourCookie) {
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
            }, 1000);
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>