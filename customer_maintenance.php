<?php
$page_title = 'Perbaikan Data Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Tambahkan filter sales jika role bukan superadmin
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
    // Query yang disempurnakan untuk mengambil kategori dan jumlah follow up
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
    // Hanya tampilkan grup jika berisi lebih dari satu customer setelah difilter
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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-tools"></i> Perbaikan Data Customer</h1>
    <a href="customer_management.php" class="btn btn-secondary">Kembali</a>
</div>

<ul class="nav nav-tabs mb-3" id="maintenanceTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="duplicates-tab" data-bs-toggle="tab" data-bs-target="#duplicates" type="button" role="tab">
      <i class="bi bi-files"></i> Telepon Duplikat <span class="badge bg-danger"><?php echo $duplicate_count; ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="bad-format-tab" data-bs-toggle="tab" data-bs-target="#bad-format" type="button" role="tab">
      <i class="bi bi-telephone-x"></i> Format Salah <span class="badge bg-warning text-dark"><?php echo $bad_format_count; ?></span>
    </button>
  </li>
</ul>

<div class="tab-content" id="maintenanceTabsContent">
  <div class="tab-pane fade show active" id="duplicates" role="tabpanel">
    <div class="card">
        <div class="card-body">
            <?php if ($duplicate_count > 0): ?>
                <p>Data di bawah dikelompokkan berdasarkan nomor telepon yang sama. Pilih satu data untuk dipertahankan, maka data lainnya akan dihapus.</p>
                <div id="duplicate-container">
                    <?php foreach ($duplicate_groups as $phone => $customers): ?>
                        <div class="card mb-3 duplicate-group py-3 bg-light" id="group-<?php echo md5($phone); ?>">
                            <div class="card-header bg-white">
                                Nomor Telepon Duplikat: <strong class="text-danger user-select-all"><?php echo htmlspecialchars($phone); ?></strong>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table mb-0">
                                    <thead><tr><th style="width: 15%;">Nama Toko</th><th style="width: 5%;">Kategori</th><th style="width: 30%;">Alamat</th><th style="width: 7%;">Kota</th><th style="width: 10%;">Sales</th><th style="width: 7%; text-align:center;">Jml FU</th><th style="width: 10%;" class="text-center">Aksi</th></tr></thead>
                                    <tbody>
                                        <?php 
                                        $all_ids_in_group = array_column($customers, 'id');
                                        foreach ($customers as $customer): 
                                            $ids_to_delete = array_diff($all_ids_in_group, [$customer['id']]);
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($customer['nama_toko']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($customer['kategori']); ?></span></td>
                                            <td><?php echo htmlspecialchars($customer['all_address'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($customer['all_cities'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($customer['nama_sales'] ?? 'N/A'); ?></td>
                                            <td class="text-center"><?php echo $customer['fu_count']; ?></td>
                                            <td>
                                                <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" target="_blank" class="btn btn-sm btn-info p-0 px-1" title="Lihat Detail">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-success keep-btn p-0 px-2" 
                                                        data-keep-id="<?php echo $customer['id']; ?>" 
                                                        data-delete-ids="<?php echo implode(',', $ids_to_delete); ?>"
                                                        data-group-id="<?php echo md5($phone); ?>" title="Pertahankan data ini & hapus lainnya">
                                                    <i class="bi bi-check-circle"></i> Pertahankan
                                                </button>
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
                <div class="text-center p-4 text-success">
                    <i class="bi bi-check-circle-fill fs-2"></i>
                    <h5 class="mt-2">Luar Biasa!</h5>
                    <p class="mb-0">Tidak ditemukan data customer dengan nomor telepon duplikat.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>

  <div class="tab-pane fade" id="bad-format" role="tabpanel">
    <div class="card">
        <div class="card-body">
            <?php if ($bad_format_count > 0): ?>
                <p>Data di bawah memiliki format nomor telepon yang salah. Klik "Ubah" untuk memperbaikinya.</p>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead><tr><th>Nama Toko</th><th>Nama PIC</th><th>No. Telepon Salah</th><th>Sales</th><th>Aksi</th></tr></thead>
                        <tbody id="bad-format-tbody">
                            <?php foreach ($bad_format_customers as $customer): ?>
                            <tr id="bad-format-row-<?php echo $customer['pic_id']; ?>">
                                <td><?php echo htmlspecialchars($customer['nama_toko']); ?></td>
                                <td><?php echo htmlspecialchars($customer['nama_pic']); ?></td>
                                <td><strong class="text-danger"><?php echo htmlspecialchars($customer['tlp_pic']); ?></strong></td>
                                <td><?php echo htmlspecialchars($customer['nama_sales'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-phone-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editPhoneModal"
                                            data-pic-id="<?php echo $customer['pic_id']; ?>"
                                            data-current-phone="<?php echo htmlspecialchars($customer['tlp_pic']); ?>">
                                        <i class="bi bi-pencil-square"></i> Ubah
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                 <div class="text-center p-4 text-success">
                    <i class="bi bi-check-circle-fill fs-2"></i>
                    <h5 class="mt-2">Luar Biasa!</h5>
                    <p class="mb-0">Tidak ditemukan data customer dengan format telepon yang salah.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editPhoneModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ubah Nomor Telepon</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editPhoneForm">
          <input type="hidden" id="edit_pic_id" name="pic_id">
          <div class="mb-3">
            <label class="form-label">Nomor Saat Ini</label>
            <input type="text" id="current_phone_display" class="form-control" disabled>
          </div>
          <div class="mb-3">
            <label for="new_phone_number" class="form-label">Nomor Baru yang Benar</label>
            <input type="tel" id="new_phone_number" name="new_phone_number" class="form-control" required placeholder="Contoh: 08123456789">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" form="editPhoneForm" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/floating_menu.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logika untuk menangani data duplikat
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
                            groupCard.innerHTML = '<div class="card-body text-center text-success"><i class="bi bi-check-circle-fill"></i> Duplikasi telah diselesaikan.</div>';
                        } else {
                            alert('Gagal memproses: ' + (data.message || 'Error tidak diketahui'));
                        }
                    })
                    .catch(error => alert('Terjadi kesalahan jaringan.'));
                }
            }
        });
    }

    // Logika untuk modal edit nomor telepon
    const editPhoneModal = document.getElementById('editPhoneModal');
    const editPhoneForm = document.getElementById('editPhoneForm');
    
    editPhoneModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const picId = button.dataset.picId;
        const currentPhone = button.dataset.currentPhone;

        editPhoneModal.querySelector('#edit_pic_id').value = picId;
        editPhoneModal.querySelector('#current_phone_display').value = currentPhone;
        editPhoneModal.querySelector('#new_phone_number').value = '';
        editPhoneModal.querySelector('#new_phone_number').focus();
    });

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
                row.style.transition = 'opacity 0.5s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 500);
                bootstrap.Modal.getInstance(editPhoneModal).hide();
            } else {
                 alert('Gagal memperbarui: ' + (data.message || 'Error tidak diketahui'));
            }
        })
        .catch(error => alert('Terjadi kesalahan jaringan.'));
    });
});
</script>