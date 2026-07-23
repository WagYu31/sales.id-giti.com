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

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card shadow-lg border-0 h-100">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="row w-100 text-center align-items-center">
                    <div class="col-md-4 border-end">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1">Sisa Jatah Saldo (Real-time)</small>
                        <h3 class="fw-bold mb-0 <?php echo $realtime_balance < 0 ? 'text-danger' : 'text-primary'; ?>">
                            Rp <?php echo number_format($realtime_balance, 0, ',', '.'); ?>
                        </h3>
                    </div>
                    <div class="col-md-4 border-end">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1">Total Penjualan Keseluruhan</small>
                        <h4 class="fw-bold mb-0 text-dark">Rp <?php echo number_format($total_sales_all, 0, ',', '.'); ?></h4>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-success btn-sm w-100 mb-2 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modalTopup"><i class="bi bi-plus-circle"></i> Input Top-Up</button>
                        <button class="btn btn-dark btn-sm w-100 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modalUpload"><i class="bi bi-file-earmark-excel"></i> Upload Excel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 bg-white p-3">
            <small class="text-muted fw-bold text-uppercase mb-2">Summary Hasil Filter</small>
            <div class="d-flex justify-content-between border-bottom py-2"><span>Penjualan:</span> <span class="fw-bold">Rp <?php echo number_format($sum_sales, 0, ',', '.'); ?></span></div>
            <div class="d-flex justify-content-between py-2"><span>Top-up Ads:</span> <span class="fw-bold text-success">Rp <?php echo number_format($sum_ads, 0, ',', '.'); ?></span></div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-dark py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Cari Invoice / No. PO" value="<?php echo $search; ?>"></div>
            <div class="col-md-2">
                <select name="platform" class="form-select">
                    <option value="">Semua Platform</option>
                    <option value="SHOPEE" <?php if($platform=='SHOPEE') echo 'selected'; ?>>SHOPEE</option>
                    <option value="TIKTOK SHOP" <?php if($platform=='TIKTOK SHOP') echo 'selected'; ?>>TIKTOK SHOP</option>
                    <option value="TIKTOK ADS" <?php if($platform=='TIKTOK ADS') echo 'selected'; ?>>TIKTOK ADS</option>
                    <option value="TOKOPEDIA" <?php if($platform=='TOKOPEDIA') echo 'selected'; ?>>TOKOPEDIA</option>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>"></div>
            <div class="col-md-2"><input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>"></div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="sales_ads.php" class="btn btn-light border w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3"><a href="?sort=invoice_no&order=<?php echo $order=='ASC'?'DESC':'ASC'; ?>&search=<?php echo $search; ?>&platform=<?php echo $platform; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="text-dark text-decoration-none">Invoice ↕</a></th>
                    <th><a href="?sort=tanggal&order=<?php echo $order=='ASC'?'DESC':'ASC'; ?>&search=<?php echo $search; ?>&platform=<?php echo $platform; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="text-dark text-decoration-none">Tanggal ↕</a></th>
                    <th>Platform</th>
                    <th class="text-end">Nominal</th>
                    <th>Penjual</th>
                    <th class="text-center pe-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($r = $query->fetch_assoc()): ?>
                <tr>
                    <td class="fw-bold text-primary ps-3"><?php echo $r['invoice_no']; ?></td>
                    <td><?php echo date('d M Y', strtotime($r['tanggal'])); ?></td>
                    <td><span class="badge bg-secondary"><?php echo $r['platform']; ?></span></td>
                    <td class="text-end fw-bold">Rp <?php echo number_format($r['total_amount'], 0, ',', '.'); ?></td>
                    <td><?php echo $r['nama_penjual']; ?></td>
                    <td class="text-center pe-3"><a href="process_ads.php?action=delete&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus penjualan ini? Perhitungan saldo akan menyesuaikan otomatis.')"><i class="bi bi-trash"></i></a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php if ($total_pages > 1): ?>
                    
                    <?php 
                    // Tentukan radius halaman di sekitar halaman aktif
                    $adjacents = 2; 
                    ?>
    
                    <!-- Tombol First dan Prev -->
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
    
                    <!-- Logika penomoran halaman -->
                    <?php
                    $pmin = ($page > $adjacents) ? ($page - $adjacents) : 1;
                    $pmax = ($page < ($total_pages - $adjacents)) ? ($page + $adjacents) : $total_pages;
    
                    // Tampilkan halaman pertama jika tersembunyi oleh radius
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
    
                    <!-- Tombol Next dan Last -->
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
    <div class="modal-dialog">
        <form action="process_ads.php?action=topup" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="fw-bold mb-0">Input Saldo Ads Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="fw-bold small mb-1">Platform (Tujuan Topup)</label>
                    <select name="platform" class="form-select" required>
                        <option value="SHOPEE">SHOPEE</option>
                        <option value="TIKTOK SHOP">TIKTOK SHOP</option>
                        <option value="TIKTOK ADS">TIKTOK ADS</option>
                        <option value="TOKOPEDIA">TOKOPEDIA</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="fw-bold small mb-1">Tanggal Top-Up</label>
                    <input type="date" name="tanggal_topup" id="topup_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="p-3 bg-light rounded mb-3 border text-center">
                    <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.75rem;">Estimasi Jatah Budget s/d Tanggal Dipilih:</small>
                    <h4 class="fw-bold mb-0 text-dark mt-1" id="display_budget">Rp 0</h4>
                </div>
                <div class="mb-3">
                    <label class="fw-bold small mb-1">Nominal Top-Up (Rp)</label>
                    <input type="number" name="topup_amount" class="form-control form-control-lg fw-bold text-success" required>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success w-100 py-2 fw-bold">Simpan Transaksi</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalUpload" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_ads.php?action=upload" method="POST" enctype="multipart/form-data" class="modal-content border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="fw-bold mb-0">Upload Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <input type="file" name="file_excel" class="form-control mb-3" accept=".xlsx" required>
                <a href="process_ads.php?action=template" class="btn btn-outline-primary btn-sm w-100 fw-bold">Download Template Excel</a>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-dark w-100 py-2 fw-bold">Upload Data</button></div>
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