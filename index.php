<?php
$page_title = 'Dashboard Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

$sql_where_conditions = ["c.deleted_at IS NULL"];
$params = [];
$types = '';

if (isset($_SESSION['role']) && $_SESSION['role'] == 'sales') {
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

$sql = "
    SELECT 
        c.id, c.tgl_input, c.nama_toko, c.deal, c.kandidat, c.sales_id, c.kategori,
        s.nama_lengkap AS nama_sales,
        GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') AS all_pics,
        GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') AS all_phones,
        GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') AS all_cities,
        (SELECT ca_inner.link_google_map FROM customer_addresses ca_inner WHERE ca_inner.customer_id = c.id AND ca_inner.deleted_at IS NULL ORDER BY ca_inner.id LIMIT 1) AS primary_map_link,
        COUNT(DISTINCT fu.id) AS fu_count
    FROM 
        customers c
    LEFT JOIN 
        sales s ON c.sales_id = s.id
    LEFT JOIN 
        customer_pics cp ON c.id = cp.customer_id AND cp.deleted_at IS NULL
    LEFT JOIN 
        customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
    LEFT JOIN
        follow_ups fu ON c.id = fu.customer_id AND fu.deleted_at IS NULL
    {$where_clause}
    GROUP BY 
        c.id
    ORDER BY 
        c.tgl_input DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Daftar Customer</h1>
    <a href="customer_add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Customer</a>
</div>

<div id="notification" class="alert" style="display:none;"></div>

<div class="card">
    <div class="card-body">
        <div id="table-loading-spinner" class="text-center p-5">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="mt-3 text-muted">Memuat data customer...</h5>
        </div>
        <div class="table-responsive" id="customer-table-container" style="display: none;">
            <table class="table table-striped table-hover sortable-table">
                <thead class="table-dark" style="font-size:13px;">
                    <tr>
                        <th>Nama Toko</th>
                        <th>PIC & Kontak</th>
                        <th>Kategori</th>
                        <th>Kota</th>
                        <th>Sales</th>
                        <th>FollowUp</th>
                        <th>Kandidat</th>
                        <th>Deal</th>
                        <th>Link Maps</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr id="customer-row-<?php echo $customer['id']; ?>">
                            <td class="text-wrap"><?php echo htmlspecialchars($customer['nama_toko']); ?></td>
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
                            <td class="text-center"><?php echo $customer['fu_count']; ?></td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="kandidat" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['kandidat'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <td class="text-center">
                               <div class="form-check form-switch d-flex justify-content-center"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="deal" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['deal'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <td>
                                <?php if (!empty($customer['primary_map_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($customer['primary_map_link']); ?>" target="_blank" class="btn btn-sm btn-success" title="Buka di Google Maps"><i class="bi bi-geo-alt-fill"></i></a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled><i class="bi bi-geo-alt-fill"></i></button>
                                <?php endif; ?>
                            </td>
                            <td style="width:10%;">
                                <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-info p-2 py-1" title="Lihat Follow Up"><i class="bi bi-eye"></i></a>
                                <?php 
                                $can_edit_delete = ($_SESSION['role'] == 'superadmin') || ($_SESSION['role'] == 'sales' && $_SESSION['user_id'] == $customer['sales_id']);
                                if ($can_edit_delete): 
                                ?>
                                    <a href="customer_edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-warning p-2 py-1" title="Edit Customer"><i class="bi bi-pencil-square"></i></a>
                                    <a href="customer_delete.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-danger p-2 py-1" title="Hapus Customer" onclick="return confirm('Yakin hapus customer ini?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center">Belum ada data customer.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('.sortable-table tbody');
    const notification = document.getElementById('notification');

    function showNotification(message, isSuccess) {
        notification.textContent = message;
        notification.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        notification.style.display = 'block';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }
    
    tableBody.addEventListener('change', function(event) {
        if (event.target.classList.contains('status-checkbox')) {
            const checkbox = event.target;
            const customerId = checkbox.dataset.customerId;
            const statusType = checkbox.dataset.type;
            const newStatus = checkbox.checked ? 'Y' : 'N';
            
            fetch('update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    'customer_id': customerId,
                    'status_type': statusType,
                    'status_value': newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, true);
                } else {
                    showNotification(data.message, false);
                    checkbox.checked = !checkbox.checked;
                }
            })
            .catch(error => {
                showNotification('Terjadi kesalahan jaringan.', false);
                checkbox.checked = !checkbox.checked;
            });
        }
    });
});

window.onload = function() {
    const loader = document.getElementById('table-loading-spinner');
    const tableContainer = document.getElementById('customer-table-container');

    if (loader && tableContainer) {
        // Sembunyikan loader
        loader.style.display = 'none';
        // Tampilkan tabel
        tableContainer.style.display = 'block';
    }
};
</script>