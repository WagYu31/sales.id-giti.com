<?php
$page_title = 'Perbaikan Data Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$sales_filter_sql = '';
$params = [];
$types = '';
if ($_SESSION['role'] === 'sales') {
    $sales_filter_sql = " AND c.sales_id = ? ";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

// 1. Ambil semua data customer yang terkait dengan nomor telepon duplikat
$duplicate_phones_query = "SELECT tlp_pic FROM customer_pics WHERE deleted_at IS NULL AND tlp_pic != '' GROUP BY tlp_pic HAVING COUNT(id) > 1";
$result_duplicates = $conn->query($duplicate_phones_query);
$duplicate_groups = [];
while ($row = $result_duplicates->fetch_assoc()) {
    $dup_phone = $row['tlp_pic'];
    $sql = "
        SELECT 
            c.id, c.nama_toko, c.kategori,
            s.nama_lengkap AS nama_sales, 
            GROUP_CONCAT(DISTINCT ca.kota SEPARATOR ', ') AS all_cities,
            GROUP_CONCAT(DISTINCT ca.alamat SEPARATOR ', ') AS all_address,
            COUNT(DISTINCT fu.id) AS fu_count
        FROM customers c
        JOIN customer_pics cp ON c.id = cp.customer_id
        LEFT JOIN sales s ON c.sales_id = s.id
        LEFT JOIN customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
        LEFT JOIN follow_ups fu ON c.id = fu.customer_id AND fu.deleted_at IS NULL
        WHERE cp.tlp_pic = ? AND c.deleted_at IS NULL AND cp.deleted_at IS NULL {$sales_filter_sql}
        GROUP BY c.id ORDER BY c.nama_toko";
    
    $stmt = $conn->prepare($sql);
    $current_params = array_merge([$dup_phone], $params);
    $current_types = 's' . $types;

    if (!empty($params)) {
        $stmt->bind_param($current_types, ...$current_params);
    } else {
        $stmt->bind_param('s', $dup_phone);
    }

    $stmt->execute();
    $customers_result = $stmt->get_result();
    if ($customers_result->num_rows > 1) {
        $duplicate_groups[$dup_phone] = $customers_result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// 2. Ambil data customer dengan format telepon salah (dan bukan duplikat)
$duplicate_phones_list = array_keys($duplicate_groups);
$bad_format_customers = [];
$in_clause = !empty($duplicate_phones_list) ? "AND cp.tlp_pic NOT IN ('" . implode("','", array_map([$conn, 'real_escape_string'], $duplicate_phones_list)) . "')" : "";

$sql_bad_format = "
    SELECT DISTINCT c.id, c.nama_toko, s.nama_lengkap as nama_sales, cp.id as pic_id, cp.nama_pic, cp.tlp_pic
    FROM customers c
    JOIN customer_pics cp ON c.id = cp.customer_id
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE c.deleted_at IS NULL AND cp.deleted_at IS NULL
    AND (cp.tlp_pic IS NOT NULL AND cp.tlp_pic != '' AND cp.tlp_pic NOT LIKE '0%')
    {$in_clause} {$sales_filter_sql}";

$stmt_bad = $conn->prepare($sql_bad_format);
if (!empty($params)) {
    $stmt_bad->bind_param($types, ...$params);
}
$stmt_bad->execute();
$result_bad_format = $stmt_bad->get_result();
$bad_format_customers = $result_bad_format->fetch_all(MYSQLI_ASSOC);
$stmt_bad->close();

$duplicate_count = count($duplicate_groups);
$bad_format_count = count($bad_format_customers);
?>

<style>
.maint-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.maint-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.maint-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.nav-tabs-maint .nav-link {
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 10px 22px;
    font-weight: 700;
    color: #475569;
    background: #FFFFFF;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transition: all 0.25s ease;
}

.nav-tabs-maint .nav-link.active {
    background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%) !important;
    color: #FFFFFF !important;
    border-color: #2563EB !important;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
}
</style>

<!-- Hero Header -->
<div class="maint-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Kualitas Data Customer</span>
            </div>
            <h1 class="maint-hero-title">Perbaikan & Kualitas Data Customer 🛠️</h1>
            <p class="maint-hero-subtitle">Audit otomatis nomor telepon duplikat dan koreksi format nomor telepon yang tidak diawali angka 0.</p>
        </div>
        <?php if ($duplicate_count > 0): ?>
        <div class="mt-3 mt-md-0">
            <button id="btn-auto-clean-all" class="btn btn-warning fw-extrabold shadow-lg px-4 py-2.5 rounded-3 d-inline-flex align-items-center gap-2" style="font-weight:800;">
                <i class="bi bi-magic fs-5"></i>
                <span>⚡ Otomatis Rapikan Semua (<?php echo $duplicate_count; ?> Grup)</span>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-pills nav-tabs-maint gap-2 mb-4" id="maintenanceTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="duplicates-tab" data-bs-toggle="tab" data-bs-target="#duplicates" type="button" role="tab">
      <i class="bi bi-files me-1"></i> Telepon Duplikat <span class="badge bg-danger ms-1"><?php echo $duplicate_count; ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="bad-format-tab" data-bs-toggle="tab" data-bs-target="#bad-format" type="button" role="tab">
      <i class="bi bi-telephone-x me-1"></i> Format Salah <span class="badge bg-warning text-dark ms-1"><?php echo $bad_format_count; ?></span>
    </button>
  </li>
</ul>

<div class="tab-content" id="maintenanceTabsContent">
  <div class="tab-pane fade show active" id="duplicates" role="tabpanel">
    <div class="card border-0 shadow-sm" style="border-radius:20px;">
        <div class="card-body p-4">
            <?php if ($duplicate_count > 0): ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
                    <p class="text-muted small fw-semibold mb-0">
                        Data di bawah dikelompokkan berdasarkan nomor telepon yang sama. Pilih data yang ingin dipertahankan, atau klik tombol <strong>Otomatis Rapikan Semua</strong> di atas.
                    </p>
                    <button class="btn btn-sm btn-outline-warning fw-bold px-3 btn-auto-clean-all-sub">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Bersihkan <?php echo $duplicate_count; ?> Duplikat Otomatis
                    </button>
                </div>
                <div id="duplicate-container">
                    <?php foreach ($duplicate_groups as $phone => $customers): ?>
                        <div class="card mb-3 border-0 shadow-sm overflow-hidden" id="group-<?php echo md5($phone); ?>" style="border-radius:16px;">
                            <div class="card-header bg-light border-bottom d-flex align-items-center justify-content-between py-3">
                                <div>
                                    <span class="small text-muted fw-bold uppercase">Nomor Telepon Duplikat:</span>
                                    <strong class="text-danger fs-6 ms-1 user-select-all"><?php echo htmlspecialchars($phone); ?></strong>
                                </div>
                                <span class="badge bg-danger rounded-pill"><?php echo count($customers); ?> Toko</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-dark-header" style="font-size:12px;">
                                        <tr>
                                            <th>NAMA TOKO</th>
                                            <th>KATEGORI</th>
                                            <th>ALAMAT</th>
                                            <th>KOTA</th>
                                            <th>SALES</th>
                                            <th class="text-center">JML FU</th>
                                            <th class="text-center">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $all_ids_in_group = array_column($customers, 'id');
                                        foreach ($customers as $customer): 
                                            $ids_to_delete = array_diff($all_ids_in_group, [$customer['id']]);
                                        ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($customer['nama_toko']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($customer['kategori']); ?></span></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($customer['all_address'] ?? 'N/A'); ?></td>
                                            <td class="small fw-semibold"><?php echo htmlspecialchars($customer['all_cities'] ?? 'N/A'); ?></td>
                                            <td class="small fw-semibold"><?php echo htmlspecialchars($customer['nama_sales'] ?? 'N/A'); ?></td>
                                            <td class="text-center fw-bold"><span class="badge bg-primary rounded-pill"><?php echo $customer['fu_count']; ?></span></td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-success keep-btn fw-bold px-3 shadow-sm" 
                                                            data-keep-id="<?php echo $customer['id']; ?>" 
                                                            data-delete-ids="<?php echo implode(',', $ids_to_delete); ?>"
                                                            data-group-id="<?php echo md5($phone); ?>">
                                                        <i class="bi bi-check-circle-fill me-1"></i> Pertahankan
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-5 text-success">
                    <i class="bi bi-check-circle-fill" style="font-size:48px;"></i>
                    <h5 class="mt-3 fw-bold" style="font-family:'Plus Jakarta Sans', sans-serif;">Luar Biasa! Data Sangat Bersih</h5>
                    <p class="text-muted mb-0">Tidak ditemukan data customer dengan nomor telepon duplikat.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>

  <div class="tab-pane fade" id="bad-format" role="tabpanel">
    <div class="card border-0 shadow-sm" style="border-radius:20px;">
        <div class="card-body p-4">
            <?php if ($bad_format_count > 0): ?>
                <p class="text-muted small fw-semibold mb-3">Data di bawah memiliki format nomor telepon yang salah (tidak diawali 0). Klik "Ubah" untuk memperbaikinya.</p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark-header">
                            <tr>
                                <th>NAMA TOKO</th>
                                <th>NAMA PIC</th>
                                <th>NO. TELEPON SALAH</th>
                                <th>SALES</th>
                                <th class="text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody id="bad-format-tbody">
                            <?php foreach ($bad_format_customers as $customer): ?>
                            <tr id="bad-format-row-<?php echo $customer['pic_id']; ?>">
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($customer['nama_toko']); ?></td>
                                <td><?php echo htmlspecialchars($customer['nama_pic']); ?></td>
                                <td><span class="badge bg-danger-subtle text-danger border border-danger fw-bold"><?php echo htmlspecialchars($customer['tlp_pic']); ?></span></td>
                                <td class="small fw-semibold"><?php echo htmlspecialchars($customer['nama_sales'] ?? 'N/A'); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary edit-phone-btn fw-bold px-3 shadow-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editPhoneModal"
                                            data-pic-id="<?php echo $customer['pic_id']; ?>"
                                            data-current-phone="<?php echo htmlspecialchars($customer['tlp_pic']); ?>">
                                        <i class="bi bi-pencil-square me-1"></i> Ubah
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                 <div class="text-center p-5 text-success">
                    <i class="bi bi-check-circle-fill" style="font-size:48px;"></i>
                    <h5 class="mt-3 fw-bold" style="font-family:'Plus Jakarta Sans', sans-serif;">Luar Biasa! Semua Format Benar</h5>
                    <p class="text-muted mb-0">Tidak ditemukan data customer dengan format telepon yang salah.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editPhoneModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold" style="font-family:'Plus Jakarta Sans', sans-serif;">Ubah Nomor Telepon</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="editPhoneForm">
          <input type="hidden" id="edit_pic_id" name="pic_id">
          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">Nomor Saat Ini</label>
            <input type="text" id="current_phone_display" class="form-control bg-light" disabled>
          </div>
          <div class="mb-3">
            <label for="new_phone_number" class="form-label small fw-bold text-muted">Nomor Baru yang Benar</label>
            <input type="tel" id="new_phone_number" name="new_phone_number" class="form-control" required placeholder="Contoh: 08123456789">
          </div>
        </form>
      </div>
      <div class="modal-footer border-top p-3">
        <button type="button" class="btn btn-light border fw-bold px-4" data-bs-dismiss="modal">Batal</button>
        <button type="submit" form="editPhoneForm" class="btn btn-primary fw-bold px-4 shadow-sm">Simpan Perubahan</button>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnAutoCleanMain = document.getElementById('btn-auto-clean-all');
    const btnAutoCleanSub = document.querySelector('.btn-auto-clean-all-sub');

    function handleAutoCleanAll() {
        if (confirm('⚡ OTOMATIS RAPIKAN SEMUA DUPLIKAT?\n\nSistem akan secara otomatis mempertahankan 1 data toko terbaik/terlengkap di setiap grup dan menghapus data duplikat lainnya.\n\nApakah Anda yakin ingin melanjutkan?')) {
            const btn = btnAutoCleanMain || btnAutoCleanSub;
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses...';
            }

            fetch('resolve_duplicates.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'auto_clean_all=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('🎉 SUKSES!\n\n' + data.message);
                    window.location.reload();
                } else {
                    alert('Gagal memproses: ' + (data.message || 'Error tidak diketahui'));
                    if (btn) btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Terjadi kesalahan jaringan.');
                if (btn) btn.disabled = false;
            });
        }
    }

    if (btnAutoCleanMain) btnAutoCleanMain.addEventListener('click', handleAutoCleanAll);
    if (btnAutoCleanSub) btnAutoCleanSub.addEventListener('click', handleAutoCleanAll);

    const duplicateContainer = document.getElementById('duplicate-container');
    if (duplicateContainer) {
        duplicateContainer.addEventListener('click', function(e) {
            const keepButton = e.target.closest('.keep-btn');
            if (keepButton) {
                const keepId = keepButton.dataset.keepId;
                const deleteIds = keepButton.dataset.deleteIds;
                const groupId = keepButton.dataset.groupId;

                if (confirm('Anda yakin ingin mempertahankan data ini dan menghapus ' + deleteIds.split(',').length + ' data duplikat lainnya dalam grup ini?')) {
                    fetch('resolve_duplicates.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `keep_id=${keepId}&delete_ids=${deleteIds}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const groupCard = document.getElementById('group-' + groupId);
                            groupCard.classList.add('border-success');
                            groupCard.innerHTML = '<div class="card-body text-center text-success py-4"><i class="bi bi-check-circle-fill fs-3 me-2"></i> Duplikasi telah diselesaikan.</div>';
                        } else {
                            alert('Gagal memproses: ' + (data.message || 'Error tidak diketahui'));
                        }
                    })
                    .catch(error => alert('Terjadi kesalahan jaringan.'));
                }
            }
        });
    }

    const editPhoneModal = document.getElementById('editPhoneModal');
    const editPhoneForm = document.getElementById('editPhoneForm');
    
    if (editPhoneModal) {
        editPhoneModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const picId = button.dataset.picId;
            const currentPhone = button.dataset.currentPhone;

            editPhoneModal.querySelector('#edit_pic_id').value = picId;
            editPhoneModal.querySelector('#current_phone_display').value = currentPhone;
            editPhoneModal.querySelector('#new_phone_number').value = '';
            editPhoneModal.querySelector('#new_phone_number').focus();
        });
    }

    if (editPhoneForm) {
        editPhoneForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const picId = formData.get('pic_id');
            
            fetch('update_phone.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById('bad-format-row-' + picId);
                    if (row) {
                        row.style.transition = 'opacity 0.5s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 500);
                    }
                    bootstrap.Modal.getInstance(editPhoneModal).hide();
                } else {
                     alert('Gagal memperbarui: ' + (data.message || 'Error tidak diketahui'));
                }
            })
            .catch(error => alert('Terjadi kesalahan jaringan.'));
        });
    }
});
</script>