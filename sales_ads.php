<?php
$page_title = 'Laporan Penjualan Online';
require_once 'includes/db.php';
require_once 'includes/header.php';

$search = $_GET['search'] ?? '';
$platform = $_GET['platform'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$sort = $_GET['sort'] ?? 'tanggal';
$order = $_GET['order'] ?? 'DESC';

$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$active_rate = (float)($conn->query("SELECT rate_percentage FROM ads_settings ORDER BY id DESC LIMIT 1")->fetch_assoc()['rate_percentage'] ?? 15);
$total_sales_all = (float)($conn->query("SELECT SUM(total_amount) FROM sales_reports")->fetch_row()[0] ?? 0);

$last_t = $conn->query("SELECT tanggal_topup, remaining_balance FROM ads_topups ORDER BY tanggal_topup DESC, id DESC LIMIT 1")->fetch_assoc();
$l_rem = (float)($last_t['remaining_balance'] ?? 0);
$last_date = $last_t ? date('Y-m-d', strtotime($last_t['tanggal_topup'])) : '2000-01-01';
$new_sales_global = (float)($conn->query("SELECT SUM(total_amount) FROM sales_reports WHERE tanggal > '$last_date'")->fetch_row()[0] ?? 0);
$realtime_balance = ($new_sales_global * ($active_rate / 100)) + $l_rem;

$where = "WHERE (invoice_no LIKE '%$search%' OR no_po LIKE '%$search%')";
if ($platform) $where .= " AND platform = '$platform'";
if ($start_date && $end_date) $where .= " AND tanggal BETWEEN '$start_date' AND '$end_date'";

$sql = "SELECT * FROM sales_reports $where ORDER BY $sort $order LIMIT $limit OFFSET $offset";
$query = $conn->query($sql);
$total_data = $conn->query("SELECT COUNT(*) FROM sales_reports $where")->fetch_row()[0];
$total_pages = ceil($total_data / $limit);

$sum_sales = (float)($conn->query("SELECT SUM(total_amount) FROM sales_reports $where")->fetch_row()[0] ?? 0);
$where_ads = "WHERE 1=1";
if ($platform) $where_ads .= " AND platform = '$platform'";
if ($start_date && $end_date) $where_ads .= " AND DATE(tanggal_topup) BETWEEN '$start_date' AND '$end_date'";
$sum_ads = (float)($conn->query("SELECT SUM(topup_amount) FROM ads_topups $where_ads")->fetch_row()[0] ?? 0);
?>

<style>
.ads-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.ads-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.ads-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.ads-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}
</style>

<!-- Hero Header -->
<div class="ads-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Laporan Ads</span>
            </div>
            <h1 class="ads-hero-title">Laporan Penjualan Ads 📊</h1>
            <p class="ads-hero-subtitle">Monitoring saldo iklan real-time, total omzet penjualan online, dan riwayat klaim top-up ads.</p>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="row align-items-center g-3">
                    <div class="col-md-5 border-end">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px;">Sisa Jatah Saldo (Real-time)</small>
                        <h3 class="fw-extrabold mb-0 <?php echo $realtime_balance < 0 ? 'text-danger' : 'text-primary'; ?>" style="font-family:'Plus Jakarta Sans', sans-serif;">
                            Rp <?php echo number_format($realtime_balance, 0, ',', '.'); ?>
                        </h3>
                    </div>
                    <div class="col-md-4 border-end">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px;">Total Omzet Keseluruhan</small>
                        <h4 class="fw-bold mb-0 text-dark" style="font-family:'Plus Jakarta Sans', sans-serif;">Rp <?php echo number_format($total_sales_all, 0, ',', '.'); ?></h4>
                    </div>
                    <div class="col-md-3 d-flex flex-column gap-2">
                        <button class="btn btn-success btn-sm w-100 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modalTopup"><i class="bi bi-plus-circle-fill me-1"></i> Input Top-Up</button>
                        <button class="btn btn-dark btn-sm w-100 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modalUpload"><i class="bi bi-file-earmark-excel-fill me-1"></i> Upload Excel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100 p-3 bg-white">
            <h6 class="fw-bold text-uppercase text-dark mb-3" style="font-size:12px; letter-spacing:0.5px;"><i class="bi bi-funnel-fill text-primary me-2"></i>Summary Hasil Filter</h6>
            <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                <span class="text-muted">Total Penjualan:</span> 
                <span class="fw-bold text-dark">Rp <?php echo number_format($sum_sales, 0, ',', '.'); ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Top-up Ads:</span> 
                <span class="fw-bold text-success">Rp <?php echo number_format($sum_ads, 0, ',', '.'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card">
    <div class="card-header p-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari Invoice / No. PO" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="platform" class="form-select">
                    <option value="">Semua Platform</option>
                    <option value="SHOPEE" <?php if($platform=='SHOPEE') echo 'selected'; ?>>SHOPEE</option>
                    <option value="TIKTOK SHOP" <?php if($platform=='TIKTOK SHOP') echo 'selected'; ?>>TIKTOK SHOP</option>
                    <option value="TIKTOK ADS" <?php if($platform=='TIKTOK ADS') echo 'selected'; ?>>TIKTOK ADS</option>
                    <option value="TOKOPEDIA" <?php if($platform=='TOKOPEDIA') echo 'selected'; ?>>TOKOPEDIA</option>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>"></div>
            <div class="col-md-2"><input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>"></div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
                <a href="sales_ads.php" class="btn btn-light border w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark-header">
                    <tr>
                        <th class="ps-3"><a href="?sort=invoice_no&order=<?php echo $order=='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>&platform=<?php echo urlencode($platform); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="text-white text-decoration-none">Invoice ↕</a></th>
                        <th><a href="?sort=tanggal&order=<?php echo $order=='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>&platform=<?php echo urlencode($platform); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="text-white text-decoration-none">Tanggal ↕</a></th>
                        <th>Platform</th>
                        <th class="text-end">Nominal</th>
                        <th>Penjual</th>
                        <th class="text-center pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->num_rows == 0): ?>
                        <tr><td colspan="6" class="text-center p-5 text-muted">Belum ada data laporan penjualan.</td></tr>
                    <?php endif; ?>
                    <?php while($r = $query->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold text-primary ps-3" style="font-family:'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($r['invoice_no']); ?></td>
                        <td class="small fw-semibold text-muted"><?php echo date('d M Y', strtotime($r['tanggal'])); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($r['platform']); ?></span></td>
                        <td class="text-end fw-bold text-dark">Rp <?php echo number_format($r['total_amount'], 0, ',', '.'); ?></td>
                        <td class="fw-semibold text-dark" style="font-size:13px;"><?php echo htmlspecialchars($r['nama_penjual']); ?></td>
                        <td class="text-center pe-3"><a href="process_ads.php?action=delete&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus penjualan ini? Perhitungan saldo akan menyesuaikan otomatis.')"><i class="bi bi-trash-fill"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 border-top-0">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php if ($total_pages > 1): ?>
                    <?php $adjacents = 2; ?>
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&platform=<?php echo urlencode($platform); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">&laquo;</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&platform=<?php echo urlencode($platform); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">&lsaquo;</a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                        <li class="page-item disabled"><span class="page-link">&lsaquo;</span></li>
                    <?php endif; ?>

                    <?php
                    $pmin = ($page > $adjacents) ? ($page - $adjacents) : 1;
                    $pmax = ($page < ($total_pages - $adjacents)) ? ($page + $adjacents) : $total_pages;

                    if ($pmin > 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }

                    for ($i = $pmin; $i <= $pmax; $i++): 
                    ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&platform=<?php echo urlencode($platform); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($pmax < $total_pages):
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&platform=<?php echo urlencode($platform); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">&rsaquo;</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&platform=<?php echo urlencode($platform); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">&raquo;</a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link">&rsaquo;</span></li>
                        <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="modalTopup" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process_ads.php?action=topup" method="POST" class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle-fill text-success me-2"></i>Input Saldo Ads Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label">Platform (Tujuan Topup)</label>
                    <select name="platform" class="form-select" required>
                        <option value="SHOPEE">SHOPEE</option>
                        <option value="TIKTOK SHOP">TIKTOK SHOP</option>
                        <option value="TIKTOK ADS">TIKTOK ADS</option>
                        <option value="TOKOPEDIA">TOKOPEDIA</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tanggal Top-Up</label>
                    <input type="date" name="tanggal_topup" id="topup_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="p-3 bg-light rounded-3 mb-3 border text-center">
                    <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.75rem;">Estimasi Jatah Budget s/d Tanggal Dipilih:</small>
                    <h4 class="fw-bold mb-0 text-dark mt-1" id="display_budget">Rp 0</h4>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal Top-Up (Rp)</label>
                    <input type="number" name="topup_amount" class="form-control form-control-lg fw-bold text-success" required placeholder="0">
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success py-2 fw-bold"><i class="bi bi-save-fill"></i> Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalUpload" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process_ads.php?action=upload" method="POST" enctype="multipart/form-data" class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-excel-fill text-primary me-2"></i>Upload Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <input type="file" name="file_excel" class="form-control mb-3" accept=".xlsx" required>
                <a href="process_ads.php?action=template" class="btn btn-outline-primary btn-sm w-100 fw-bold"><i class="bi bi-download"></i> Download Template Excel</a>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary py-2 fw-bold"><i class="bi bi-upload"></i> Upload Data</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dInp = document.getElementById('topup_date');
    const dBud = document.getElementById('display_budget');
    function fetchBudget() {
        fetch(`process_ads.php?action=get_budget&date=${dInp.value}`)
            .then(res => res.json())
            .then(data => {
                dBud.innerText = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(data.budget);
                if (data.budget < 0) {
                    dBud.classList.remove('text-dark');
                    dBud.classList.add('text-danger');
                } else {
                    dBud.classList.remove('text-danger');
                    dBud.classList.add('text-dark');
                }
            });
    }
    dInp.addEventListener('change', fetchBudget);
    fetchBudget();
});
</script>

<?php require_once 'includes/footer.php'; ?>