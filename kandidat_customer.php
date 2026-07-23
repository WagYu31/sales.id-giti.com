<?php
$page_title = 'Kandidat Potensial Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

$filter = $_GET['filter'] ?? 'kandidat';
$allowed_filters = ['kandidat', 'potensial', 'acc_boss'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'kandidat';
}

$sql_where_conditions = ["c.deleted_at IS NULL", "c.kandidat = 'Y'"];
$params = [];
$types = '';

if ($filter === 'potensial') {
    $sql_where_conditions[] = "c.potensial = 'Y'";
    $sql_where_conditions[] = "(c.acc_boss = 'N' OR c.acc_boss IS NULL)";
} elseif ($filter === 'acc_boss') {
    $sql_where_conditions[] = "c.potensial = 'Y'";
    $sql_where_conditions[] = "c.acc_boss = 'Y'";
} else {
    $sql_where_conditions[] = "(c.potensial = 'N' OR c.potensial IS NULL)";
    $sql_where_conditions[] = "(c.acc_boss = 'N' OR c.acc_boss IS NULL)";
}

if (isset($_SESSION['role']) && $_SESSION['role'] == 'sales') {
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

$sql = "
    SELECT 
        c.id, c.nama_toko, c.kandidat, c.potensial, c.acc_boss, c.acc_boss_note, c.kategori,
        s.nama_lengkap AS nama_sales,
        GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') AS all_pics,
        GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') AS all_phones,
        GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') AS all_cities,
        (SELECT ca_inner.link_google_map FROM customer_addresses ca_inner WHERE ca_inner.customer_id = c.id AND ca_inner.deleted_at IS NULL ORDER BY ca_inner.id LIMIT 1) AS primary_map_link
    FROM customers c
    LEFT JOIN sales s ON c.sales_id = s.id
    LEFT JOIN customer_pics cp ON c.id = cp.customer_id AND cp.deleted_at IS NULL
    LEFT JOIN customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
    {$where_clause}
    GROUP BY c.id
    ORDER BY c.tgl_input DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$customers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$is_superadmin = ($_SESSION['role'] === 'superadmin');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-star-fill"></i> Kandidat Potensial Customer</h1>
</div>

<ul class="nav nav-pills mb-3">
  <li class="nav-item">
    <a class="nav-link <?php if ($filter === 'kandidat') echo 'active'; ?>" href="kandidat_customer.php?filter=kandidat">Kandidat</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php if ($filter === 'potensial') echo 'active'; ?>" href="kandidat_customer.php?filter=potensial">Potensial</a>
  </li>
   <li class="nav-item">
    <a class="nav-link <?php if ($filter === 'acc_boss') echo 'active'; ?>" href="kandidat_customer.php?filter=acc_boss">Acc Boss</a>
  </li>
</ul>

<div id="notification" class="alert" style="display:none;"></div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark" style="font-size:13px;">
                    <tr>
                        <th>Nama Toko</th>
                        <th>PIC & Kontak</th>
                        <th>Kategori</th>
                        <th>Kota</th>
                        <th>Sales</th>
                        <th>Link Maps</th>
                        <?php if ($filter === 'kandidat'): ?>
                            <th>Kandidat</th>
                        <?php endif; ?>
                        <?php if ($filter !== 'acc_boss'): ?>
                             <th>Potensial</th>
                        <?php endif; ?>
                         <?php if ($filter === 'potensial' || $filter === 'acc_boss'): ?>
                            <th>Acc Boss</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr id="customer-row-<?php echo $customer['id']; ?>">
                            <td class="text-wrap">
                                <a href="https://sales.grav-tech.com/followup_view.php?customer_id=<?php echo $customer['id']; ?>">
                                <?php echo htmlspecialchars($customer['nama_toko']); ?></a></td>
                            <td>
                                <?php
                                $pics = !empty($customer['all_pics']) ? explode('||', $customer['all_pics']) : [];
                                $phones = !empty($customer['all_phones']) ? explode('||', $customer['all_phones']) : [];
                                if (!empty($pics)) {
                                    foreach ($pics as $key => $pic_name) {
                                        $phone_number = $phones[$key] ?? '';
                                        echo '<div>' . htmlspecialchars($pic_name);
                                        if (!empty($phone_number)) {
                                            $cleaned_tel = preg_replace('/[^0-9]/', '', $phone_number);
                                            $wa_number = (substr($cleaned_tel, 0, 1) === '0') ? '62' . substr($cleaned_tel, 1) : $cleaned_tel;
                                            echo ' (<a href="https://wa.me/' . $wa_number . '" target="_blank">' . htmlspecialchars($phone_number) . '</a>)';
                                        }
                                        echo '</div>';
                                    }
                                } else { echo '-'; }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($customer['kategori'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['all_cities'] ?? '-'); ?></td>
                            <td><?php echo $customer['nama_sales'] ? htmlspecialchars($customer['nama_sales']) : '<span class="badge bg-warning">Belum Di-assign</span>'; ?></td>
                            <td>
                                <?php if (!empty($customer['primary_map_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($customer['primary_map_link']); ?>" target="_blank" class="btn btn-sm btn-success" title="Buka di Google Maps"><i class="bi bi-geo-alt-fill"></i></a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled><i class="bi bi-geo-alt-fill"></i></button>
                                <?php endif; ?>
                            </td>
                            <?php if ($filter === 'kandidat'): ?>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="kandidat" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['kandidat'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <?php endif; ?>
                             <?php if ($filter !== 'acc_boss'): ?>
                             <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="potensial" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['potensial'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <?php endif; ?>
                             <?php if ($filter === 'potensial' || $filter === 'acc_boss'): ?>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center align-items-center">
                                    <input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="acc_boss" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['acc_boss'] == 'Y') echo 'checked'; ?> <?php if (!$is_superadmin) echo 'disabled'; ?>>
                                    <?php if (!empty($customer['acc_boss_note'])): ?>
                                        <i class="bi bi-info-circle-fill ms-2 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($customer['acc_boss_note']); ?>"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center p-4">Tidak ada data customer yang cocok dengan filter ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('tbody');
    const notification = document.getElementById('notification');
    const isSuperAdmin = <?php echo $is_superadmin ? 'true' : 'false'; ?>;

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    function showNotification(message, isSuccess) {
        notification.textContent = message;
        notification.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        notification.style.display = 'block';
        setTimeout(() => { notification.style.display = 'none'; }, 3000);
    }
    
    tableBody.addEventListener('change', function(event) {
        if (event.target.classList.contains('status-checkbox')) {
            const checkbox = event.target;
            const customerId = checkbox.dataset.customerId;
            const statusType = checkbox.dataset.type;
            const newStatus = checkbox.checked ? 'Y' : 'N';

            if (statusType === 'acc_boss' && newStatus === 'Y' && isSuperAdmin) {
                Swal.fire({
                    title: 'Tambahkan Catatan (Opsional)',
                    input: 'textarea',
                    inputPlaceholder: 'Masukkan catatan untuk sales...',
                    showCancelButton: true,
                    confirmButtonText: 'Simpan',
                    cancelButtonText: 'Batal',
                    preConfirm: (note) => {
                        updateStatus(customerId, statusType, newStatus, note || null); 
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                     if (!result.isConfirmed && result.dismiss !== Swal.DismissReason.cancel) {
                         checkbox.checked = !checkbox.checked; // Revert jika dibatalkan
                     } else if (result.dismiss === Swal.DismissReason.cancel) {
                          checkbox.checked = !checkbox.checked; // Revert jika klik batal
                     }
                });
            } else {
                 updateStatus(customerId, statusType, newStatus);
            }
        }
    });

    function updateStatus(customerId, statusType, newStatus, note = null) {
        const bodyParams = {
            'customer_id': customerId,
            'status_type': statusType,
            'status_value': newStatus
        };
        if (note !== null) {
            bodyParams['acc_boss_note'] = note;
        }

        fetch('update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(bodyParams)
        })
        .then(response => {
            if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification(data.message, true);
                setTimeout(() => {
                    const row = document.getElementById('customer-row-' + customerId);
                    if (row) { // Tambahkan pengecekan null
                        row.style.transition = 'opacity 0.5s ease';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 500);
                    } else {
                         window.location.reload(); // Fallback jika baris tidak ditemukan
                    }
                }, 1000);
            } else {
                showNotification(data.message || 'Gagal memperbarui status.', false);
                const checkbox = document.querySelector(`input[data-customer-id="${customerId}"][data-type="${statusType}"]`);
                if (checkbox) checkbox.checked = !checkbox.checked;
            }
        })
        .catch(error => {
            showNotification('Terjadi kesalahan jaringan.', false);
            const checkbox = document.querySelector(`input[data-customer-id="${customerId}"][data-type="${statusType}"]`);
            if (checkbox) checkbox.checked = !checkbox.checked;
            console.error('Fetch error:', error);
        });
    }
});
</script>