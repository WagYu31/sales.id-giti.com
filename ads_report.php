<?php
require_once 'includes/db.php';
session_start();
$page_title = 'Laporan Saldo & Keuangan Ads';
require_once 'includes/header.php';

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_ads = "WHERE 1=1";
$where_sales = "WHERE 1=1";

if (!empty($start_date) && !empty($end_date)) {
    $where_ads .= " AND DATE(tanggal_topup) BETWEEN '$start_date' AND '$end_date'";
    $where_sales .= " AND tanggal BETWEEN '$start_date' AND '$end_date'";
    $label_filter = "Periode Filter";
} else {
    $label_filter = "Keseluruhan";
}

$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_data = $conn->query("SELECT COUNT(*) FROM ads_topups $where_ads")->fetch_row()[0];
$total_pages = ceil($total_data / $limit);

$history = $conn->query("SELECT * FROM ads_topups $where_ads ORDER BY tanggal_topup DESC, id DESC LIMIT $limit OFFSET $offset");

$current_rate = (float)($conn->query("SELECT rate_percentage FROM ads_settings ORDER BY id DESC LIMIT 1")->fetch_assoc()['rate_percentage'] ?? 15);

$summary_ads = $conn->query("SELECT SUM(topup_amount) as tot_topup FROM ads_topups $where_ads")->fetch_assoc();
$tot_topup = $summary_ads['tot_topup'] ?? 0;

$total_omzet_real = (float)($conn->query("SELECT SUM(total_amount) FROM sales_reports $where_sales")->fetch_row()[0] ?? 0);
?>

<style>
.ledger-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.ledger-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.ledger-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.ledger-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}
</style>

<!-- Hero Header -->
<div class="ledger-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Buku Besar Ads</span>
            </div>
            <h1 class="ledger-hero-title">Buku Besar Saldo & Keuangan Ads 📖</h1>
            <p class="ledger-hero-subtitle">Rekapitulasi jatah saldo iklan (Rate %), akumulasi jatah omzet, dan riwayat sisa / hutang saldo.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-warning fw-bold shadow-lg" data-bs-toggle="modal" data-bs-target="#modalRate">
                <i class="bi bi-gear-fill me-1"></i> Set Persentase (<?php echo $current_rate; ?>%)
            </button>
        </div>
    </div>
</div>

<div class="row mb-4 g-3">
    <div class="col-md-7">
        <form method="GET" class="card h-100">
            <div class="card-body p-3 d-flex align-items-end gap-3">
                <div class="flex-grow-1">
                    <label class="small text-muted fw-bold mb-1">Mulai Tanggal</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="flex-grow-1">
                    <label class="small text-muted fw-bold mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold px-3"><i class="bi bi-filter"></i> Terapkan</button>
                    <a href="ads_report.php" class="btn btn-light border fw-bold px-3">Semua</a>
                </div>
            </div>
        </form>
    </div>
    <div class="col-md-5">
        <div class="row g-2 h-100">
            <div class="col-6">
                <div class="card bg-success text-white h-100 border-0 shadow-sm" style="border-radius:16px;">
                    <div class="card-body p-3 text-center d-flex flex-column justify-content-center">
                        <span class="small text-uppercase fw-bold opacity-85">Omzet (<?php echo $label_filter; ?>)</span>
                        <h5 class="fw-extrabold mb-0 mt-1" style="font-family:'Plus Jakarta Sans', sans-serif;">Rp <?php echo number_format($total_omzet_real, 0, ',', '.'); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-primary text-white h-100 border-0 shadow-sm" style="border-radius:16px;">
                    <div class="card-body p-3 text-center d-flex flex-column justify-content-center">
                        <span class="small text-uppercase fw-bold opacity-85">Top-Up (<?php echo $label_filter; ?>)</span>
                        <h5 class="fw-extrabold mb-0 mt-1" style="font-family:'Plus Jakarta Sans', sans-serif;">Rp <?php echo number_format($tot_topup, 0, ',', '.'); ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-dark-header">
                    <tr>
                        <th class="py-3 text-start ps-4">Tanggal Top-Up</th>
                        <th>Platform</th>
                        <th class="text-end">Omzet Terhitung</th>
                        <th class="text-end">Jatah / Rate</th>
                        <th class="text-end">Akumulasi Jatah</th>
                        <th class="text-end">Nominal Top-Up</th>
                        <th class="text-end">Sisa / Hutang Akhir</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($h = $history->fetch_assoc()): ?>
                    <?php
                    $en_months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    $id_months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                    
                    $prev_q = $conn->query("SELECT tanggal_topup FROM ads_topups WHERE tanggal_topup < '{$h['tanggal_topup']}' ORDER BY tanggal_topup DESC, id DESC LIMIT 1");
                    if ($prev_q->num_rows > 0) {
                        $prev_date = $prev_q->fetch_assoc()['tanggal_topup'];
                        $d_start = strtotime(date('Y-m-d', strtotime($prev_date)) . ' +1 day');
                    } else {
                        $first_sale_q = $conn->query("SELECT MIN(tanggal) as min_tgl FROM sales_reports");
                        $min_tgl = $first_sale_q->fetch_assoc()['min_tgl'];
                        if ($min_tgl) {
                            $d_start = strtotime($min_tgl);
                        } else {
                            $d_start = strtotime($h['tanggal_topup']);
                        }
                    }
                    
                    $d_end = strtotime($h['tanggal_topup']);
                    if ($d_start > $d_end) {
                        $d_start = $d_end;
                    }
                    
                    $start_period = str_replace($en_months, $id_months, date('d M Y', $d_start));
                    $end_period = str_replace($en_months, $id_months, date('d M Y', $d_end));
                    
                    $pure_jatah = $h['sales_period_total'] * ($h['quota_rate'] / 100);
                    
                    $topup_color = 'text-dark';
                    if ($h['topup_amount'] > $pure_jatah) {
                        $topup_color = 'text-danger';
                    } elseif ($h['topup_amount'] < $pure_jatah) {
                        $topup_color = 'text-success';
                    }
                    ?>
                    <tr>
                        <td class="text-start ps-4 fw-bold text-dark">
                            <?php echo date('d M Y', strtotime($h['tanggal_topup'])); ?><br>
                            <small class="fw-normal text-muted" style="font-size:11px;"><?php echo date('H:i', strtotime($h['tanggal_topup'])); ?></small>
                        </td>
                        <td><span class="badge bg-secondary px-3"><?php echo htmlspecialchars($h['platform']); ?></span></td>
                        <td class="text-end">
                            <div class="fw-bold text-dark">Rp <?php echo number_format($h['sales_period_total'], 0, ',', '.'); ?></div>
                            <div class="small text-muted opacity-75" style="font-size: 0.75rem;"><?php echo $start_period; ?> - <?php echo $end_period; ?></div>
                        </td>
                        <td class="text-end">
                            <div class="fw-bold text-dark">Rp <?php echo number_format($pure_jatah, 0, ',', '.'); ?></div>
                            <div class="small text-muted opacity-75" style="font-size: 0.75rem;">(<?php echo $h['quota_rate']; ?>%)</div>
                        </td>
                        <td class="text-end fw-bold text-dark">Rp <?php echo number_format($h['calculated_quota'], 0, ',', '.'); ?></td>
                        <td class="text-end fw-bold <?php echo $topup_color; ?>">+ Rp <?php echo number_format($h['topup_amount'], 0, ',', '.'); ?></td>
                        <td class="text-end fw-bold">
                            <?php if($h['remaining_balance'] < 0): ?>
                                <span class="text-danger">Rp <?php echo number_format($h['remaining_balance'], 0, ',', '.'); ?></span>
                                <div class="small fw-normal text-danger opacity-75" style="font-size:11px;">Hutang</div>
                            <?php else: ?>
                                <span class="text-primary">Rp <?php echo number_format($h['remaining_balance'], 0, ',', '.'); ?></span>
                                <div class="small fw-normal text-primary opacity-75" style="font-size:11px;">Sisa Saldo</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <a href="process_ads.php?action=delete_topup&id=<?php echo $h['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus riwayat top up ini? Semua perhitungan jatah di masa depan akan disesuaikan secara otomatis.')"><i class="bi bi-trash-fill"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($history->num_rows == 0): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">Tidak ada data di periode ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if($total_pages > 1): ?>
    <div class="card-footer bg-white py-3 border-top-0">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalRate" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process_ads.php?action=change_rate" method="POST" class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="fw-bold mb-0"><i class="bi bi-gear-fill text-warning me-2"></i>Update Jatah Ads Rate</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <label class="form-label">Persentase Baru (%)</label>
                <div class="input-group input-group-lg">
                    <input type="number" step="0.01" name="new_rate" class="form-control text-center fw-bold" value="<?php echo $current_rate; ?>" required>
                    <span class="input-group-text bg-white fw-bold">%</span>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary py-2 fw-bold"><i class="bi bi-save-fill"></i> Simpan Perubahan Rate</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>