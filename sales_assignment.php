<?php
$page_title = 'Penugasan Sales Cepat';
require_once 'includes/db.php';
require_once 'includes/header.php';

if ($_SESSION['role'] !== 'superadmin') {
    die("Akses ditolak. Halaman ini hanya untuk Superadmin.");
}

$sales_list = [];
$result_sales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL ORDER BY nama_lengkap ASC");
if($result_sales) {
    while($row = $result_sales->fetch_assoc()){
        $sales_list[] = $row;
    }
}

$customers = [];
$sql = "
    SELECT 
        c.id, 
        c.nama_toko,
        c.kategori,
        ca.alamat,
        ca.kota,
        cp.nama_pic,
        cp.tlp_pic,
        s.nama_lengkap as sales_penanggung_jawab
    FROM customers c
    LEFT JOIN sales s ON c.sales_id = s.id
    LEFT JOIN (SELECT customer_id, MIN(alamat) as alamat, MIN(kota) as kota FROM customer_addresses WHERE deleted_at IS NULL GROUP BY customer_id) ca ON c.id = ca.customer_id
    LEFT JOIN (SELECT customer_id, MIN(nama_pic) as nama_pic, MIN(tlp_pic) as tlp_pic FROM customer_pics WHERE deleted_at IS NULL GROUP BY customer_id) cp ON c.id = cp.customer_id
    WHERE (c.deal IS NULL OR c.deal != 'Y') AND c.deleted_at IS NULL
    ORDER BY c.nama_toko ASC
";
$result_cust = $conn->query($sql);
if($result_cust) {
    while($row = $result_cust->fetch_assoc()){
        $customers[] = $row;
    }
}
?>

<h1><i class="bi bi-person-check-fill"></i> Penugasan Sales Cepat</h1>
<p class="text-muted">Pilih satu atau lebih customer dari tabel, lalu pilih sales dari menu "Aksi Massal" untuk menugaskan mereka.</p>

<div class="card">
    <div class="card-body">
        <div class="border rounded p-2 mb-3 bg-light">
            <div class="row align-items-center">
                <div class="col-md-auto">
                     <span class="fw-bold">Aksi untuk item terpilih:</span>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <label class="input-group-text" for="sales-select-action">Tugaskan ke</label>
                        <select id="sales-select-action" class="form-select">
                            <option value="">-- Pilih Sales --</option>
                            <?php foreach($sales_list as $sales): ?>
                                <option value="<?php echo $sales['id']; ?>" data-sales-name="<?php echo htmlspecialchars($sales['nama_lengkap']); ?>"><?php echo htmlspecialchars($sales['nama_lengkap']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-auto">
                    <button id="btn-apply-assignment" class="btn btn-primary">Terapkan</button>
                </div>
                <div class="col-md-auto ms-auto">
                    <button id="btn-unassign-selected" class="btn btn-warning">Lepas Tugas</button>
                </div>
            </div>
        </div>

        <input type="text" id="searchInput" class="form-control mb-2" placeholder="Cari customer, kategori, kota, PIC, atau nama sales...">
        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
            <table class="table table-hover table-sm" id="assignmentTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;"><input class="form-check-input" type="checkbox" id="selectAll"></th>
                        <th>Nama Customer</th>
                        <th>Nama PIC</th>
                        <th>Telepon PIC</th>
                        <th>Kategori</th>
                        <th>Alamat</th>
                        <th>Kota</th>
                        <th>Sales Penanggung Jawab</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($customers as $cust): ?>
                    <tr data-customer-id="<?php echo $cust['id']; ?>">
                        <td><input class="form-check-input customer-checkbox" type="checkbox"></td>
                        <td><?php echo htmlspecialchars($cust['nama_toko']); ?></td>
                        <td><?php echo htmlspecialchars($cust['nama_pic'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($cust['tlp_pic'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($cust['kategori'] ?? '-'); ?></td>
                        <td class="text-wrap"><?php echo htmlspecialchars($cust['alamat'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($cust['kota'] ?? '-'); ?></td>
                        <td class="sales-name-cell"><?php echo $cust['sales_penanggung_jawab'] ? htmlspecialchars($cust['sales_penanggung_jawab']) : '<span class="text-muted fst-italic">Belum ada</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="notification-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999; width: 350px;"></div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    
    function showNotification(message, isSuccess = true) {
        const a_class = isSuccess ? 'alert-success' : 'alert-danger';
        const notif = $(`<div class="alert ${a_class} alert-dismissible fade show" role="alert">
                             ${message}
                             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                           </div>`);
        $('#notification-container').append(notif);
        setTimeout(() => notif.alert('close'), 3000);
    }

    $('#searchInput').on('keyup', function() {
        $('#selectAll').prop('checked', false);
        const filter = $(this).val().toLowerCase();
        $('#assignmentTable tbody tr').each(function() {
            const rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.includes(filter));
        });
    });

    $('#selectAll').on('change', function() {
        $('#assignmentTable tbody tr:visible .customer-checkbox').prop('checked', this.checked);
    });

    function performAction(action, salesId = null) {
        let customerIds = [];
        $('#assignmentTable tbody .customer-checkbox:checked').each(function() {
            customerIds.push($(this).closest('tr').data('customer-id'));
        });

        if (customerIds.length === 0) {
            alert('Silakan pilih minimal satu customer.');
            return;
        }

        $.ajax({
            url: 'assignment_process.php',
            type: 'POST',
            data: { 
                action: action, 
                customer_ids: customerIds, 
                sales_id: salesId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification(response.message || 'Aksi berhasil diterapkan.');
                    
                    let salesName = $('#sales-select-action').find('option:selected').text();
                    customerIds.forEach(function(id) {
                        const newName = (action === 'assign_customers') ? salesName : '<span class="text-muted fst-italic">Belum ada</span>';
                        $('tr[data-customer-id="' + id + '"]').find('.sales-name-cell').html(newName);
                    });
                    
                    $('.customer-checkbox, #selectAll').prop('checked', false);
                } else {
                    showNotification(response.message || 'Terjadi kesalahan.', false);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                showNotification('Error: Tidak dapat terhubung ke server. ' + textStatus, false);
            }
        });
    }

    $('#btn-apply-assignment').on('click', function() {
        let salesId = $('#sales-select-action').val();
        if (!salesId) {
            alert('Silakan pilih sales yang akan ditugaskan.');
            return;
        }
        performAction('assign_customers', salesId);
    });
    
    $('#btn-unassign-selected').on('click', function() {
        if (confirm('Anda yakin ingin melepas tugas dari customer yang dipilih?')) {
            performAction('unassign_customers');
        }
    });

});
</script>

<?php require_once 'includes/footer.php'; ?>