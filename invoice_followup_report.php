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
$total_count = 0;
$perlu_fu_count = 0;
$sudah_fu_count = 0;
$menunggu_fu_count = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $invoice_timestamp = strtotime($row['tgl_follow_up']);
        $next_follow_up_date = $row['next_follow_up_date'];
        
        if ($next_follow_up_date !== null) {
            $row['calculated_status'] = 'sudah';
            $sudah_fu_count++;
        } else {
            $seven_days_after_invoice = strtotime('+7 days', $invoice_timestamp);
            $now_timestamp = time();
            if ($now_timestamp < $seven_days_after_invoice) {
                $row['calculated_status'] = 'menunggu';
                $menunggu_fu_count++;
            } else {
                $row['calculated_status'] = 'perlu';
                $perlu_fu_count++;
            }
        }
        
        $follow_ups[] = $row;
        $total_count++;
    }
}

function create_sort_link_fu($column_name, $display_text, $current_sort_by, $current_sort_dir) {
    $next_sort_dir = ($current_sort_by == $column_name && $current_sort_dir == 'ASC') ? 'DESC' : 'ASC';
    $link_params = ['sort_by' => $column_name, 'sort_dir' => $next_sort_dir];
    $icon = '<i class="bi bi-arrow-down-up text-muted opacity-50 ms-1" style="font-size: 11px;"></i>';
    if ($current_sort_by == $column_name) {
        $icon = $current_sort_dir == 'ASC' ? '<i class="bi bi-sort-up-alt text-primary ms-1"></i>' : '<i class="bi bi-sort-down text-primary ms-1"></i>';
    }
    return '<a href="?' . http_build_query($link_params) . '" class="text-secondary text-decoration-none d-inline-flex align-items-center fw-bold">' . $display_text . $icon . '</a>';
}
?>

<style>
.inv-header-card {
    background: #FFFFFF;
    border-radius: 24px;
    padding: 24px 28px;
    margin-bottom: 24px;
    border: 1.5px solid #E2E8F0;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
}

.inv-header-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2563EB, #38BDF8, #818CF8);
}

.inv-stat-card {
    background: #FFFFFF;
    border: 1.5px solid #E2E8F0;
    border-radius: 18px;
    padding: 20px 22px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.02);
    transition: all 0.25s ease;
}

.inv-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(0,0,0,0.06);
    border-color: #CBD5E1;
}

.inv-table-card {
    background: #FFFFFF;
    border: 1.5px solid #E2E8F0;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    overflow: hidden;
}

.badge-soft-danger {
    background-color: #FEF2F2 !important;
    color: #B91C1C !important;
    border: 1px solid #FECACA !important;
    font-weight: 700 !important;
    font-size: 11.5px !important;
}

.badge-soft-success {
    background-color: #ECFDF5 !important;
    color: #047857 !important;
    border: 1px solid #A7F3D0 !important;
    font-weight: 700 !important;
    font-size: 11.5px !important;
}

.badge-soft-primary {
    background-color: #EFF6FF !important;
    color: #1D4ED8 !important;
    border: 1px solid #BFDBFE !important;
    font-weight: 700 !important;
    font-size: 11.5px !important;
}

.btn-detail-fu {
    background: #F1F5F9;
    color: #2563EB;
    border: 1px solid #CBD5E1;
    font-weight: 700;
    font-size: 12px;
    padding: 6px 14px;
    border-radius: 20px;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-detail-fu:hover {
    background: #2563EB;
    color: #FFFFFF;
    border-color: #2563EB;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.btn-back-dash {
    background: #F8FAFC;
    color: #475569;
    border: 1.5px solid #CBD5E1;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 20px;
    border-radius: 30px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-back-dash:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
}

.filter-pill-btn {
    border: 1px solid #CBD5E1;
    background: #F8FAFC;
    color: #64748B;
    font-weight: 700;
    font-size: 12px;
    padding: 6px 16px;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-pill-btn.active, .filter-pill-btn:hover {
    background: #2563EB;
    color: #FFFFFF;
    border-color: #2563EB;
}
</style>

<div class="main-content-wrapper p-4">
    <!-- Page Header -->
    <div class="inv-header-card d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 46px; height: 46px; background: linear-gradient(135deg, #2563EB, #1D4ED8); font-size: 22px;">
                🧾
            </div>
            <div>
                <h3 class="mb-0 fw-bold text-dark" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 20px; letter-spacing: -0.4px;">
                    Laporan Follow Up Invoice
                </h3>
                <p class="text-muted mb-0" style="font-size: 13.5px; font-family: 'Inter', sans-serif;">Riwayat penagihan invoice & pemantauan status follow up penjualan tim sales</p>
            </div>
        </div>
        <div>
            <a href="customer_management.php" class="btn-back-dash">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- 4 KPI Stat Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="inv-stat-card" style="border-top: 4px solid #2563EB;">
                <div class="text-muted fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">TOTAL INVOICE FU</div>
                <div class="fs-3 fw-bold text-dark my-1" style="font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo number_format($total_count, 0, ',', '.'); ?></div>
                <div class="text-muted" style="font-size: 12px;">Data invoice terdaftar</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="inv-stat-card" style="border-top: 4px solid #EF4444;">
                <div class="text-danger fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">🔴 PERLU FOLLOW UP</div>
                <div class="fs-3 fw-bold text-danger my-1" style="font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo number_format($perlu_fu_count, 0, ',', '.'); ?></div>
                <div class="text-muted" style="font-size: 12px;">Perlu segera ditindak</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="inv-stat-card" style="border-top: 4px solid #10B981;">
                <div class="text-success fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">🟢 SUDAH FOLLOW UP</div>
                <div class="fs-3 fw-bold text-success my-1" style="font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo number_format($sudah_fu_count, 0, ',', '.'); ?></div>
                <div class="text-muted" style="font-size: 12px;">Telah ditindaklanjuti</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="inv-stat-card" style="border-top: 4px solid #3B82F6;">
                <div class="text-primary fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">🔵 MENUNGGU FU</div>
                <div class="fs-3 fw-bold text-primary my-1" style="font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo number_format($menunggu_fu_count, 0, ',', '.'); ?></div>
                <div class="text-muted" style="font-size: 12px;">Dalam tenggat < 7 hari</div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="inv-table-card">
        <div class="p-4 border-bottom bg-white d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold text-dark me-2" style="font-size: 14px;">Filter Status:</span>
                <button class="filter-pill-btn active" onclick="filterStatus('all', this)">Semua (<?php echo $total_count; ?>)</button>
                <button class="filter-pill-btn" onclick="filterStatus('perlu', this)">🔴 Perlu FU (<?php echo $perlu_fu_count; ?>)</button>
                <button class="filter-pill-btn" onclick="filterStatus('sudah', this)">🟢 Sudah FU (<?php echo $sudah_fu_count; ?>)</button>
                <button class="filter-pill-btn" onclick="filterStatus('menunggu', this)">🔵 Menunggu (<?php echo $menunggu_fu_count; ?>)</button>
            </div>
            <div class="position-relative" style="min-width: 280px;">
                <i class="bi bi-search position-absolute text-muted" style="left: 14px; top: 10px; font-size: 14px;"></i>
                <input type="text" id="liveSearchInput" class="form-control ps-5 rounded-pill" placeholder="Cari no. invoice, toko, sales..." style="font-size: 13px; border-color: #CBD5E1;">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0;">
                    <tr>
                        <th class="py-3 px-4 text-secondary" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">
                            <?php echo create_sort_link_fu('tgl_invoice', 'Tgl. Invoice', $sort_by, $sort_dir); ?>
                        </th>
                        <th class="py-3 px-3 text-secondary" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">
                            <?php echo create_sort_link_fu('no_inv', 'No. Invoice', $sort_by, $sort_dir); ?>
                        </th>
                        <th class="py-3 px-3 text-secondary" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">
                            <?php echo create_sort_link_fu('nama_toko', 'Nama Toko / Klien', $sort_by, $sort_dir); ?>
                        </th>
                        <th class="py-3 px-3 text-secondary" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">
                            <?php echo create_sort_link_fu('nama_sales', 'Sales Rep', $sort_by, $sort_dir); ?>
                        </th>
                        <th class="py-3 px-3 text-secondary" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">STATUS FOLLOW UP</th>
                        <th class="py-3 px-4 text-secondary text-end" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">AKSI</th>
                    </tr>
                </thead>
                <tbody id="invoiceReportTableBody">
                    <?php if (!empty($follow_ups)): ?>
                        <?php foreach ($follow_ups as $fu): ?>
                            <?php
                            $status_text = '';
                            $badge_class = '';
                            $calc_status = $fu['calculated_status'];

                            if ($calc_status === 'sudah') {
                                $status_text = 'Sudah di Follow Up';
                                $badge_class = 'badge-soft-success';
                            } elseif ($calc_status === 'menunggu') {
                                $status_text = 'Menunggu Follow Up';
                                $badge_class = 'badge-soft-primary';
                            } else {
                                $status_text = 'Perlu di Follow Up';
                                $badge_class = 'badge-soft-danger';
                            }
                            $invoice_timestamp = strtotime($fu['tgl_follow_up']);
                            ?>
                            <tr class="fu-row" data-status="<?php echo $calc_status; ?>">
                                <td class="px-4 text-muted" style="font-size: 13px;">
                                    📅 <?php echo date('d M Y, H:i', $invoice_timestamp); ?>
                                </td>
                                <td class="px-3">
                                    <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-2 font-monospace" style="font-size: 13px; font-weight: 700;">
                                        <?php echo htmlspecialchars($fu['no_inv']); ?>
                                    </span>
                                </td>
                                <td class="px-3">
                                    <div class="fw-bold text-dark" style="font-size: 14.5px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                        <?php echo htmlspecialchars($fu['nama_toko']); ?>
                                    </div>
                                </td>
                                <td class="px-3 text-secondary" style="font-size: 13.5px; font-weight: 600;">
                                    👤 <?php echo htmlspecialchars($fu['nama_sales']); ?>
                                </td>
                                <td class="px-3">
                                    <span class="badge <?php echo $badge_class; ?> px-3 py-1.5 rounded-pill">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="px-4 text-end">
                                    <a href="followup_view.php?customer_id=<?php echo $fu['customer_id']; ?>" target="_blank" class="btn-detail-fu" title="Lihat Riwayat Follow Up Customer">
                                        <i class="bi bi-eye-fill"></i> Detail Follow Up
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted" style="font-size: 14px;">
                                📋 Tidak ada data follow up invoice yang ditemukan.
                            </td>
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
    const rows = tableBody.getElementsByClassName('fu-row');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const text = row.textContent || row.innerText;
            if (text.toLowerCase().indexOf(filter) > -1) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    });
});

function filterStatus(type, btn) {
    const buttons = document.querySelectorAll('.filter-pill-btn');
    buttons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.getElementsByClassName('fu-row');
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const status = row.getAttribute('data-status');
        if (type === 'all' || status === type) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    }
}
</script>