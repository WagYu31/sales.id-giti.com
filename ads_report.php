<?php
/**
 * LAPORAN SALDO & KEUANGAN ADS (BUKU BESAR)
 * PT. LOEWIX INDONESIA
 * Built with Taste Skill (tasteskill.dev) Design System
 */

require_once 'includes/db.php';
$page_title = 'Laporan Saldo & Keuangan Ads';
require_once 'includes/header.php';

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

$where_ads   = "WHERE 1=1";
$where_sales = "WHERE 1=1";

if (!empty($start_date) && !empty($end_date)) {
    $where_ads   .= " AND DATE(tanggal_topup) BETWEEN '$start_date' AND '$end_date'";
    $where_sales .= " AND tanggal BETWEEN '$start_date' AND '$end_date'";
    $label_filter = "Periode Filter";
} else {
    $label_filter = "Keseluruhan";
}

$limit  = 20;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_data  = (int)($conn->query("SELECT COUNT(*) FROM ads_topups $where_ads")->fetch_row()[0] ?? 0);
$total_pages = ceil($total_data / $limit);

$history = $conn->query("SELECT * FROM ads_topups $where_ads ORDER BY tanggal_topup DESC, id DESC LIMIT $limit OFFSET $offset");

$current_rate = (float)($conn->query("SELECT rate_percentage FROM ads_settings ORDER BY id DESC LIMIT 1")->fetch_assoc()['rate_percentage'] ?? 15);

$summary_ads = $conn->query("SELECT SUM(topup_amount) as tot_topup FROM ads_topups $where_ads")->fetch_assoc();
$tot_topup   = (float)($summary_ads['tot_topup'] ?? 0);

$total_omzet_real = (float)($conn->query("SELECT SUM(total_amount) FROM sales_reports $where_sales")->fetch_row()[0] ?? 0);
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
:root {
    --ts-bg-surface: #FFFFFF;
    --ts-bg-subtle: #F8FAFC;
    --ts-border-subtle: #E2E8F0;
    --ts-text-primary: #0F172A;
    --ts-text-secondary: #475569;
    --ts-radius-card: 18px;
    --ts-radius-pill: 9999px;
    --ts-shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.03);
}

.ts-tabular-nums {
    font-variant-numeric: tabular-nums;
    font-feature-settings: 'tnum' on, 'lnum' on;
}

/* ── Hero Header ── */
.taste-hero {
    background: #0B132B;
    border-radius: var(--ts-radius-card);
    padding: 28px 32px;
    margin-bottom: 24px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 30px -10px rgba(11, 19, 43, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.taste-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 320px; height: 320px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.25) 0%, transparent 70%);
    pointer-events: none;
}

.taste-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.06);
    padding: 3px 12px;
    border-radius: var(--ts-radius-pill);
    border: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 11.5px;
    font-weight: 600;
    color: #93C5FD;
    margin-bottom: 10px;
}

.taste-hero-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -0.025em;
    color: #FFFFFF;
    margin-bottom: 4px;
    line-height: 1.2;
}

.taste-hero-subtitle {
    font-size: 13.5px;
    color: rgba(226, 232, 240, 0.8);
    max-width: 580px;
    margin: 0;
    line-height: 1.45;
}

/* ── Bento Stat Cards ── */
.taste-card {
    background: #FFFFFF;
    border-radius: var(--ts-radius-card);
    border: 1px solid var(--ts-border-subtle);
    box-shadow: var(--ts-shadow-card);
    margin-bottom: 20px;
    overflow: hidden;
}

.form-control-taste {
    height: 40px;
    border: 1.5px solid #E2E8F0 !important;
    border-radius: 10px !important;
    padding: 7px 12px !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #0F172A !important;
    background-color: #F8FAFC !important;
    transition: all 0.15s ease !important;
}

.form-control-taste:focus {
    background-color: #FFFFFF !important;
    border-color: #2563EB !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12) !important;
}

/* ── Modern Table Header & Cells ── */
.taste-table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.taste-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.taste-table thead th {
    background: #0F172A !important;
    color: #F8FAFC !important;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: 14px 16px !important;
    border: none !important;
    vertical-align: middle;
    white-space: nowrap;
}

.taste-table tbody tr {
    transition: background-color 0.12s ease;
    border-bottom: 1px solid #F1F5F9;
}

.taste-table tbody tr:hover {
    background-color: #F8FAFC !important;
}

.taste-table tbody td {
    padding: 14px 16px !important;
    border-top: none;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
    font-size: 13px;
    color: #1E293B;
}

/* ── Indicators & Badges ── */
.badge-boncos {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.badge-surplus {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.badge-hutang-solid {
    background: #DC2626;
    color: #FFFFFF;
    font-size: 11px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    letter-spacing: 0.02em;
}

.badge-saldo-solid {
    background: #059669;
    color: #FFFFFF;
    font-size: 11px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    letter-spacing: 0.02em;
}

.btn-delete-circle {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #FFF1F2;
    color: #E11D48;
    border: 1px solid #FECDD3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    cursor: pointer;
}

.btn-delete-circle:hover {
    background: #FFE4E6;
    color: #BE123C;
    transform: scale(1.06);
}
</style>

<!-- 1. Hero Header -->
<div class="taste-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="taste-breadcrumb">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Buku Besar Ads</span>
            </div>
            <h1 class="taste-hero-title">Buku Besar Saldo & Keuangan Ads 📖</h1>
            <p class="taste-hero-subtitle">Rekapitulasi jatah saldo iklan (Rate %), akumulasi jatah omzet, dan riwayat sisa / hutang saldo.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-warning fw-bold shadow-lg" style="border-radius:12px; padding:9px 18px; font-size:13.5px;" data-bs-toggle="modal" data-bs-target="#modalRate">
                <i class="bi bi-gear-fill me-1"></i> Set Persentase (<?php echo $current_rate; ?>%)
            </button>
        </div>
    </div>
</div>

<!-- 2. Filter & Summary Stat Cards -->
<div class="row mb-4 g-3">
    <div class="col-lg-7 col-md-12">
        <form method="GET" class="taste-card h-100 mb-0">
            <div class="card-body p-3 d-flex flex-wrap align-items-end gap-3">
                <div class="flex-grow-1" style="min-width:140px;">
                    <label class="small text-muted fw-bold mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">Mulai Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-taste" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="flex-grow-1" style="min-width:140px;">
                    <label class="small text-muted fw-bold mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-taste" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold px-3" style="height:40px; border-radius:10px;"><i class="bi bi-filter"></i> Terapkan</button>
                    <a href="ads_report.php" class="btn btn-light border fw-bold px-3 d-inline-flex align-items-center" style="height:40px; border-radius:10px;">Semua</a>
                </div>
            </div>
        </form>
    </div>
    
    <div class="col-lg-5 col-md-12">
        <div class="row g-2 h-100">
            <div class="col-6">
                <div class="taste-card h-100 mb-0 shadow-sm" style="background: linear-gradient(135deg, #065F46 0%, #059669 100%); color: #FFFFFF !important; border:none;">
                    <div class="card-body p-3 text-center d-flex flex-column justify-content-center">
                        <span class="small text-uppercase fw-bold" style="opacity:0.9; font-size:10.5px; letter-spacing:0.6px; color:#D1FAE5;">Omzet (<?php echo $label_filter; ?>)</span>
                        <h4 class="fw-extrabold mb-0 mt-1 ts-tabular-nums" style="font-family:'Plus Jakarta Sans', sans-serif; color:#FFFFFF !important; font-size:17px;">Rp <?php echo number_format($total_omzet_real, 0, ',', '.'); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="taste-card h-100 mb-0 shadow-sm" style="background: linear-gradient(135deg, #1E40AF 0%, #2563EB 100%); color: #FFFFFF !important; border:none;">
                    <div class="card-body p-3 text-center d-flex flex-column justify-content-center">
                        <span class="small text-uppercase fw-bold" style="opacity:0.9; font-size:10.5px; letter-spacing:0.6px; color:#DBEAFE;">Top-Up (<?php echo $label_filter; ?>)</span>
                        <h4 class="fw-extrabold mb-0 mt-1 ts-tabular-nums" style="font-family:'Plus Jakarta Sans', sans-serif; color:#FFFFFF !important; font-size:17px;">Rp <?php echo number_format($tot_topup, 0, ',', '.'); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. Main Data Table Card -->
<div class="taste-card">
    <div class="card-body p-0">
        <div class="taste-table-container">
            <table class="taste-table align-middle text-center">
                <thead>
                    <tr>
                        <th class="text-start ps-4">Tanggal Top-Up</th>
                        <th>Platform</th>
                        <th class="text-end">Omzet Terhitung</th>
                        <th class="text-end">Jatah / Rate</th>
                        <th class="text-end">Akumulasi Jatah</th>
                        <th class="text-end">Nominal Top-Up</th>
                        <th class="text-end pe-3">Sisa / Hutang Akhir</th>
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
                    $end_period   = str_replace($en_months, $id_months, date('d M Y', $d_end));
                    
                    $pure_jatah   = (float)$h['sales_period_total'] * ($h['quota_rate'] / 100);
                    $topup_amt    = (float)$h['topup_amount'];
                    $rem_bal      = (float)$h['remaining_balance'];

                    // Logika Boncos vs Surplus Topup
                    $is_boncos    = ($topup_amt > $pure_jatah);
                    $is_surplus   = ($topup_amt < $pure_jatah);
                    $diff_amt     = abs($pure_jatah - $topup_amt);
                    ?>
                    <tr>
                        <!-- Tanggal Topup -->
                        <td class="text-start ps-4 fw-bold text-dark">
                            <div class="ts-tabular-nums" style="font-size:13px; font-family:'Plus Jakarta Sans', sans-serif;">
                                <i class="bi bi-calendar-check text-primary me-1"></i><?php echo date('d M Y', strtotime($h['tanggal_topup'])); ?>
                            </div>
                            <small class="fw-normal text-muted ts-tabular-nums" style="font-size:11px;">
                                <i class="bi bi-clock text-muted me-1"></i><?php echo date('H:i', strtotime($h['tanggal_topup'])); ?> WIB
                            </small>
                        </td>

                        <!-- Platform -->
                        <td>
                            <span class="badge bg-dark rounded-pill px-3 py-1.5" style="font-size:11px; letter-spacing:0.5px;">
                                <?php echo htmlspecialchars($h['platform']); ?>
                            </span>
                        </td>

                        <!-- Omzet Terhitung -->
                        <td class="text-end">
                            <div class="fw-bold text-dark ts-tabular-nums" style="font-size:13px;">Rp <?php echo number_format($h['sales_period_total'], 0, ',', '.'); ?></div>
                            <div class="small text-muted opacity-75 ts-tabular-nums" style="font-size: 11px;"><?php echo $start_period; ?> – <?php echo $end_period; ?></div>
                        </td>

                        <!-- Jatah / Rate -->
                        <td class="text-end">
                            <div class="fw-bold text-dark ts-tabular-nums" style="font-size:13px;">Rp <?php echo number_format($pure_jatah, 0, ',', '.'); ?></div>
                            <div class="small text-muted opacity-75" style="font-size: 11px;">(<?php echo $h['quota_rate']; ?>%)</div>
                        </td>

                        <!-- Akumulasi Jatah -->
                        <td class="text-end fw-bold text-dark ts-tabular-nums" style="font-size:13px;">
                            Rp <?php echo number_format($h['calculated_quota'], 0, ',', '.'); ?>
                        </td>

                        <!-- Nominal Top-Up (BONCOS = MERAH MINUS, SURPLUS = HIJAU PLUS) -->
                        <td class="text-end">
                            <?php if ($is_boncos): ?>
                                <div class="fw-extrabold text-danger ts-tabular-nums" style="font-size:13.5px;">
                                    - Rp <?php echo number_format($topup_amt, 0, ',', '.'); ?>
                                </div>
                                <div>
                                    <span class="badge-boncos">
                                        <i class="bi bi-arrow-down-circle-fill"></i> Boncos (-Rp <?php echo number_format($diff_amt, 0, ',', '.'); ?>)
                                    </span>
                                </div>
                            <?php elseif ($is_surplus): ?>
                                <div class="fw-extrabold text-success ts-tabular-nums" style="font-size:13.5px; color:#059669 !important;">
                                    + Rp <?php echo number_format($topup_amt, 0, ',', '.'); ?>
                                </div>
                                <div>
                                    <span class="badge-surplus">
                                        <i class="bi bi-arrow-up-circle-fill"></i> Surplus (+Rp <?php echo number_format($diff_amt, 0, ',', '.'); ?>)
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="fw-bold text-dark ts-tabular-nums" style="font-size:13.5px;">
                                    Rp <?php echo number_format($topup_amt, 0, ',', '.'); ?>
                                </div>
                                <div>
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:10px;">Pas Jatah</span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Sisa / Hutang Akhir (HUTANG = MERAH MINUS, SISA SALDO = HIJAU PLUS) -->
                        <td class="text-end pe-3">
                            <?php if ($rem_bal < 0): ?>
                                <div class="fw-extrabold text-danger ts-tabular-nums" style="font-size:13.5px;">
                                    - Rp <?php echo number_format(abs($rem_bal), 0, ',', '.'); ?>
                                </div>
                                <div>
                                    <span class="badge-hutang-solid">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Hutang / Defisit
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="fw-extrabold text-success ts-tabular-nums" style="font-size:13.5px; color:#059669 !important;">
                                    + Rp <?php echo number_format($rem_bal, 0, ',', '.'); ?>
                                </div>
                                <div>
                                    <span class="badge-saldo-solid">
                                        <i class="bi bi-check-circle-fill"></i> Sisa Saldo
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Aksi Hapus -->
                        <td class="text-center pe-4">
                            <button type="button" class="btn-delete-circle delete-ads-btn" data-id="<?php echo $h['id']; ?>" title="Hapus riwayat top up ini">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if($history->num_rows == 0): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <div style="font-size:36px; margin-bottom:8px;">📊</div>
                            <div class="fw-bold">Tidak ada data di periode ini.</div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if($total_pages > 1): ?>
    <div class="card-footer bg-white py-3 border-top-0">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0 gap-1">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                    <a class="page-link shadow-2sm" style="border-radius:6px; font-weight:700;" href="?page=<?php echo $i; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Update Jatah Ads Rate -->
<div class="modal fade" id="modalRate" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process_ads.php?action=change_rate" method="POST" class="modal-content" style="border-radius:20px; border:none; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background:#0F172A; color:#FFF; padding:18px 24px;">
                <h5 class="fw-bold mb-0 fs-6"><i class="bi bi-gear-fill text-warning me-2"></i>Update Jatah Ads Rate (%)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <label class="form-label text-muted fw-bold mb-2" style="font-size:12px; text-transform:uppercase;">Persentase Baru (%)</label>
                <div class="input-group input-group-lg" style="max-width:240px; margin:0 auto;">
                    <input type="number" step="0.01" name="new_rate" class="form-control text-center fw-bold form-control-taste" style="font-size:22px;" value="<?php echo $current_rate; ?>" required>
                    <span class="input-group-text bg-white fw-bold">%</span>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 justify-content-center pb-4">
                <button type="button" class="btn btn-light px-4 fw-bold" style="border-radius:10px;" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold" style="border-radius:10px;"><i class="bi bi-save-fill me-1"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // SweetAlert2 Delete Confirmation
    document.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.delete-ads-btn');
        if (deleteButton) {
            event.preventDefault();
            const topupId = deleteButton.dataset.id;

            Swal.fire({
                title: 'Hapus Riwayat Top-Up?',
                text: 'Semua perhitungan jatah omzet & sisa saldo di masa depan akan disesuaikan secara otomatis.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `process_ads.php?action=delete_topup&id=${topupId}`;
                }
            });
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>