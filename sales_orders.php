<?php
/**
 * sales_orders.php
 * Daftar Riwayat Pesanan Penjualan (Sales Order List)
 */

$page_title = 'Daftar Pesanan Penjualan (Sales Order)';
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'ajax_sales_order.php';

// Filter Parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$salesFilter = isset($_GET['sales_id']) ? (int)$_GET['sales_id'] : 0;
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$where = "WHERE so.deleted_at IS NULL";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (so.so_number LIKE ? OR so.customer_name LIKE ? OR so.customer_pic LIKE ? OR so.po_number LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= "ssss";
}

if (!empty($statusFilter)) {
    $where .= " AND so.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($salesFilter > 0) {
    $where .= " AND so.sales_id = ?";
    $params[] = $salesFilter;
    $types .= "i";
}

if (!empty($startDate)) {
    $where .= " AND so.so_date >= ?";
    $params[] = $startDate;
    $types .= "s";
}

if (!empty($endDate)) {
    $where .= " AND so.so_date <= ?";
    $params[] = $endDate;
    $types .= "s";
}

// Summary Statistics
$qStats = $conn->query("SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(grand_total), 0) as total_amount,
    COALESCE(SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END), 0) as total_waiting,
    COALESCE(SUM(CASE WHEN status = 'Diproses' THEN 1 ELSE 0 END), 0) as total_processing,
    COALESCE(SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END), 0) as total_completed
FROM sales_orders WHERE deleted_at IS NULL");
$stats = $qStats ? $qStats->fetch_assoc() : [
    'total_orders' => 0, 'total_amount' => 0, 'total_waiting' => 0, 'total_processing' => 0, 'total_completed' => 0
];

// Fetch Orders
$sqlOrders = "SELECT so.*, 
                     COUNT(soi.id) as total_items,
                     COALESCE(SUM(soi.qty), 0) as total_qty
              FROM sales_orders so
              LEFT JOIN sales_order_items soi ON soi.sales_order_id = so.id
              $where
              GROUP BY so.id
              ORDER BY so.so_date DESC, so.id DESC
              LIMIT 150";

$stmt = $conn->prepare($sqlOrders);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$ordersResult = $stmt->get_result();

// Sales List for filter
$salesList = [];
$qSales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE deleted_at IS NULL ORDER BY nama_lengkap ASC");
if ($qSales) {
    while ($s = $qSales->fetch_assoc()) $salesList[] = $s;
}
?>

<style>
.so-hero {
    background: linear-gradient(135deg, #091124 0%, #172554 50%, #1D4ED8 100%);
    border-radius: 20px;
    padding: 30px 36px;
    color: #FFFFFF;
    margin-bottom: 28px;
    box-shadow: 0 10px 30px -10px rgba(29, 78, 216, 0.35);
    position: relative;
    overflow: hidden;
}

.so-hero::after {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 240px; height: 240px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
    pointer-events: none;
}

.stat-card-so {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card-so:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

.status-badge-so {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-Draft { background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; }
.status-Menunggu { background: #FEF3C7; color: #B45309; border: 1px solid #FCD34D; }
.status-Diproses { background: #DBEAFE; color: #1D4ED8; border: 1px solid #93C5FD; }
.status-Selesai { background: #D1FAE5; color: #047857; border: 1px solid #6EE7B7; }
.status-Dibatalkan { background: #FEE2E2; color: #B91C1C; border: 1px solid #FCA5A5; }

.table-so-container {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}

.table-so thead th {
    background: #F8FAFC;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 12px 14px;
    border-bottom: 2px solid #E2E8F0;
}

.table-so tbody td {
    padding: 14px;
    vertical-align: middle;
    border-bottom: 1px solid #F1F5F9;
    font-size: 13px;
}

.table-so tbody tr:hover td {
    background: #F8FAFC;
}
</style>

<!-- Hero Section -->
<div class="so-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12.5px; color:rgba(191,219,254,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Pesanan Penjualan (Sales Order)</span>
            </div>
            <h1 class="fw-bold mb-1" style="font-size:26px; font-family:'Outfit', sans-serif;">
                Pesanan Penjualan 📦
            </h1>
            <p class="mb-0 text-white-50" style="font-size:13.5px;">
                Manajemen Sales Order, penawaran harga, dan pembuatan dokumen cetak resmi CCTV Loewix.
            </p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="sales_order_form.php" class="btn btn-primary px-4 py-2.5 fw-bold shadow-lg" style="border-radius:10px; background:#2563EB; border:none;">
                <i class="bi bi-plus-lg me-1"></i> Buat Pesanan Baru
            </a>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-so border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Total Pesanan</span>
                    <h3 class="fw-bold mb-0 mt-1 font-monospace"><?php echo number_format($stats['total_orders'] ?? 0); ?></h3>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                    <i class="bi bi-cart-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-so border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Total Nilai Penjualan</span>
                    <h4 class="fw-bold mb-0 mt-1 font-monospace text-success">
                        Rp <?php echo number_format($stats['total_amount'] ?? 0, 0, ',', '.'); ?>
                    </h4>
                </div>
                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                    <i class="bi bi-cash-stack fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-so border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Menunggu Diproses</span>
                    <h3 class="fw-bold mb-0 mt-1 font-monospace text-warning"><?php echo number_format($stats['total_waiting'] ?? 0); ?></h3>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-so border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Selesai / Terkirim</span>
                    <h3 class="fw-bold mb-0 mt-1 font-monospace text-info"><?php echo number_format($stats['total_completed'] ?? 0); ?></h3>
                </div>
                <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                    <i class="bi bi-check2-all fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" action="sales_orders.php" class="row g-2 align-items-center">
            <div class="col-lg-3 col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari No. SO, Toko, PIC..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <?php
                    $optStatuses = ['Draft', 'Menunggu', 'Diproses', 'Selesai', 'Dibatalkan'];
                    foreach ($optStatuses as $st) {
                        $sel = ($statusFilter === $st) ? 'selected' : '';
                        echo "<option value='{$st}' {$sel}>Status: {$st}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <select name="sales_id" class="form-select form-select-sm">
                    <option value="">-- Semua Sales --</option>
                    <?php foreach ($salesList as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($salesFilter == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" class="form-control" title="Dari Tanggal" value="<?php echo htmlspecialchars($startDate); ?>">
                    <span class="input-group-text bg-light">s/d</span>
                    <input type="date" name="end_date" class="form-control" title="Sampai Tanggal" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
            </div>

            <div class="col-lg-2 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="sales_orders.php" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table Orders -->
<div class="table-so-container">
    <div class="table-responsive">
        <table class="table table-so mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;">No. Pesanan (SO)</th>
                    <th style="width: 110px;">Tanggal</th>
                    <th>Customer / Toko</th>
                    <th style="width: 130px;">Sales PIC</th>
                    <th style="width: 90px; text-align: center;">Item</th>
                    <th style="width: 140px; text-align: right;">Total Nilai (Rp)</th>
                    <th style="width: 110px;">Syarat Bayar</th>
                    <th style="width: 120px; text-align: center;">Status</th>
                    <th style="width: 140px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ordersResult && $ordersResult->num_rows > 0): ?>
                    <?php while ($row = $ordersResult->fetch_assoc()): ?>
                        <tr id="row-so-<?php echo $row['id']; ?>">
                            <td>
                                <a href="sales_order_print.php?id=<?php echo $row['id']; ?>" class="fw-bold font-monospace text-decoration-none text-primary" title="Klik untuk Cetak / Lihat Dokumen">
                                    <?php echo htmlspecialchars($row['so_number']); ?>
                                </a>
                                <?php if (!empty($row['po_number'])): ?>
                                    <div class="text-muted" style="font-size:11px;">PO: <?php echo htmlspecialchars($row['po_number']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-semibold text-secondary">
                                    <?php echo date('d/m/Y', strtotime($row['so_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                </div>
                                <?php if (!empty($row['customer_pic']) || !empty($row['customer_phone'])): ?>
                                    <div class="text-muted small" style="font-size:11.5px;">
                                        <?php echo htmlspecialchars($row['customer_pic'] ?? ''); ?>
                                        <?php if (!empty($row['customer_phone'])) echo ' (' . htmlspecialchars($row['customer_phone']) . ')'; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-normal px-2 py-1">
                                    <i class="bi bi-person me-1 text-primary"></i><?php echo htmlspecialchars($row['sales_name'] ?: '-'); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="fw-bold font-monospace"><?php echo (int)$row['total_qty']; ?></span>
                                <div class="text-muted" style="font-size:11px;"><?php echo (int)$row['total_items']; ?> tipe</div>
                            </td>
                            <td style="text-align: right;">
                                <div class="fw-bold text-dark font-monospace" style="font-size:14px;">
                                    Rp <?php echo number_format($row['grand_total'], 0, ',', '.'); ?>
                                </div>
                                <?php if ($row['discount_amount'] > 0): ?>
                                    <div class="text-danger small" style="font-size:11px;">Disc: Rp <?php echo number_format($row['discount_amount'], 0, ',', '.'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    <?php echo htmlspecialchars($row['payment_terms']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="dropdown">
                                    <button class="status-badge-so status-<?php echo $row['status']; ?> border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?php echo $row['status']; ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="font-size:12.5px;">
                                        <li><h6 class="dropdown-header">Ubah Status SO</h6></li>
                                        <?php foreach ($optStatuses as $stOption): ?>
                                            <li>
                                                <a class="dropdown-item btn-change-status" href="#" data-id="<?php echo $row['id']; ?>" data-status="<?php echo $stOption; ?>">
                                                    <span class="status-badge-so status-<?php echo $stOption; ?> py-0 px-2" style="font-size:10.5px;">●</span> <?php echo $stOption; ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div class="btn-group btn-group-sm">
                                    <a href="sales_order_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-outline-primary" title="Cetak Dokumen SO">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="sales_order_form.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-secondary" title="Edit Pesanan">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-delete-so" data-id="<?php echo $row['id']; ?>" data-num="<?php echo htmlspecialchars($row['so_number']); ?>" title="Hapus Pesanan">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-bold mb-1">Belum Ada Pesanan Penjualan</h6>
                            <p class="small text-muted mb-3">Mulai buat pesanan penjualan pertama untuk toko atau customer Anda.</p>
                            <a href="sales_order_form.php" class="btn btn-sm btn-primary px-3 py-2 fw-bold">
                                <i class="bi bi-plus-lg me-1"></i> Buat Pesanan Sekarang
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Ubah Status Cepat
    $('.btn-change-status').on('click', function(e) {
        e.preventDefault();
        const soId = $(this).data('id');
        const newStatus = $(this).data('status');

        $.post('ajax_sales_order.php', {
            action: 'update_status',
            id: soId,
            status: newStatus
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Gagal mengubah status.' });
            }
        }, 'json');
    });

    // Hapus SO
    $('.btn-delete-so').on('click', function() {
        const soId = $(this).data('id');
        const soNum = $(this).data('num');

        Swal.fire({
            title: 'Hapus Pesanan?',
            text: `Apakah Anda yakin ingin menghapus pesanan ${soNum}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#EF4444'
        }).then((res) => {
            if (res.isConfirmed) {
                $.post('ajax_sales_order.php', {
                    action: 'delete_sales_order',
                    id: soId
                }, function(res) {
                    if (res.success) {
                        $(`#row-so-${soId}`).fadeOut(300, function() { $(this).remove(); });
                        Swal.fire({ icon: 'success', title: 'Dihapus', text: 'Pesanan berhasil dihapus.', timer: 1500, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Gagal menghapus pesanan.' });
                    }
                }, 'json');
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
