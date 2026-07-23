<?php
$page_title = 'Laporan Follow Up Invoice';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// --- LOGIKA UNTUK SORTING ---
$allowed_sort_columns = [
    'tgl_invoice' => 'fu_invoice.tgl_follow_up',
    'no_inv' => 'fu_invoice.no_inv',
    'nama_toko' => 'c.nama_toko',
    'nama_sales' => 's.nama_lengkap'
];
$sort_by = isset($_GET['sort_by']) && array_key_exists($_GET['sort_by'], $allowed_sort_columns) ? $_GET['sort_by'] : 'tgl_invoice';
$sort_dir = isset($_GET['sort_dir']) && in_array(strtoupper($_GET['sort_dir']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_dir']) : 'DESC';

$params = [];
$types = '';
$sales_filter_sql = '';

if (isset($_SESSION['role']) && $_SESSION['role'] == 'sales') {
    $sales_filter_sql = " AND fu_invoice.sales_id = ? ";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$sql = "
    SELECT 
        fu_invoice.id,
        fu_invoice.tgl_follow_up,
        fu_invoice.no_inv,
        c.id AS customer_id,
        c.nama_toko,
        s.nama_lengkap AS nama_sales,
        (SELECT MIN(fu_next.tgl_follow_up)
         FROM follow_ups fu_next
         WHERE fu_next.customer_id = fu_invoice.customer_id
           AND fu_next.tgl_follow_up > fu_invoice.tgl_follow_up
           AND fu_next.deleted_at IS NULL) AS next_follow_up_date
    FROM 
        follow_ups fu_invoice
    JOIN 
        customers c ON fu_invoice.customer_id = c.id
    JOIN 
        sales s ON fu_invoice.sales_id = s.id
    WHERE 
        fu_invoice.no_inv IS NOT NULL 
        AND fu_invoice.no_inv != ''
        AND fu_invoice.deleted_at IS NULL
        AND c.deleted_at IS NULL
        {$sales_filter_sql}
    ORDER BY 
        {$allowed_sort_columns[$sort_by]} {$sort_dir}
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$follow_ups = [];
if ($result) {
    $follow_ups = $result->fetch_all(MYSQLI_ASSOC);
}

function create_sort_link_fu($column_name, $display_text, $current_sort_by, $current_sort_dir) {
    $next_sort_dir = ($current_sort_by == $column_name && $current_sort_dir == 'ASC') ? 'DESC' : 'ASC';
    $link_params = ['sort_by' => $column_name, 'sort_dir' => $next_sort_dir];
    $icon = '';
    if ($current_sort_by == $column_name) {
        $icon = $current_sort_dir == 'ASC' ? '<i class="bi bi-sort-up-alt ms-1"></i>' : '<i class="bi bi-sort-down ms-1"></i>';
    }
    return '<a href="?' . http_build_query($link_params) . '" class="text-white text-decoration-none">' . $display_text . $icon . '</a>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-receipt"></i> Laporan Follow Up Invoice</h1>
    <a href="customer_management.php" class="btn btn-secondary">Kembali</a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Riwayat Follow Up yang Menghasilkan Penjualan</span>
        <div class="w-25">
            <input type="text" id="liveSearchInput" class="form-control form-control-sm" placeholder="Cari data...">
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th><?php echo create_sort_link_fu('tgl_invoice', 'Tgl. Invoice', $sort_by, $sort_dir); ?></th>
                        <th><?php echo create_sort_link_fu('no_inv', 'No. Invoice', $sort_by, $sort_dir); ?></th>
                        <th><?php echo create_sort_link_fu('nama_toko', 'Nama Toko', $sort_by, $sort_dir); ?></th>
                        <th><?php echo create_sort_link_fu('nama_sales', 'Sales', $sort_by, $sort_dir); ?></th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="invoiceReportTableBody">
                    <?php if (!empty($follow_ups)): ?>
                        <?php foreach ($follow_ups as $fu): ?>
                            <?php
                            $status_text = '';
                            $status_class = '';
                            $invoice_timestamp = strtotime($fu['tgl_follow_up']);
                            $next_follow_up_date = $fu['next_follow_up_date'];
                            if ($next_follow_up_date !== null) {
                                $status_text = 'Sudah di Follow Up';
                                $status_class = 'bg-success';
                            } else {
                                $seven_days_after_invoice = strtotime('+7 days', $invoice_timestamp);
                                $now_timestamp = time();
                                if ($now_timestamp < $seven_days_after_invoice) {
                                    $status_text = 'Menunggu Follow Up';
                                    $status_class = 'bg-primary';
                                } else {
                                    $status_text = 'Perlu di Follow Up';
                                    $status_class = 'bg-danger';
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo date('d M Y, H:i', $invoice_timestamp); ?></td>
                                <td><strong><?php echo htmlspecialchars($fu['no_inv']); ?></strong></td>
                                <td><?php echo htmlspecialchars($fu['nama_toko']); ?></td>
                                <td><?php echo htmlspecialchars($fu['nama_sales']); ?></td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <a href="followup_view.php?customer_id=<?php echo $fu['customer_id']; ?>" target="_blank" class="btn btn-sm btn-info" title="Lihat Detail Customer">
                                        <i class="bi bi-eye"></i> Detail Follow Up
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center p-4">Tidak ada data follow up dengan nomor invoice yang ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/floating_menu.php'; ?>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearchInput');
    const tableBody = document.getElementById('invoiceReportTableBody');
    const tableRows = tableBody.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        
        for (let i = 0; i < tableRows.length; i++) {
            const row = tableRows[i];
            const rowData = row.textContent || row.innerText;
            if (rowData.toLowerCase().indexOf(filter) > -1) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    });
});
</script>