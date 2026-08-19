<?php
/**
 * AJAX HANDLER FOR BONUS COMPETITION SALES DETAIL MODAL WITH INTERACTIVE MONTH FILTER
 */
require_once 'includes/db.php';

$sales_id = intval($_GET['sales_id'] ?? 0);
$kat = trim($_GET['kat'] ?? 'a');
$selected_bulan = trim($_GET['periode_bulan'] ?? '8');

if ($sales_id <= 0) {
    echo '<div class="alert alert-danger p-4 text-center">Invalid Sales ID.</div>';
    exit;
}

// Date Range Filtering
$where_date_fu = "";
$where_date_cust = "";

if ($selected_bulan === '9') {
    $start_date = '2026-09-01';
    $end_date = '2026-09-30';
    $label_periode = 'Bulan 9 (September 2026)';
    $where_date_fu = "AND DATE(fu.tgl_follow_up) BETWEEN '2026-09-01' AND '2026-09-30'";
    $where_date_cust = "DATE(c.tgl_input) BETWEEN '2026-09-01' AND '2026-09-30'";
} else if ($selected_bulan === '10') {
    $start_date = '2026-10-01';
    $end_date = '2026-10-31';
    $label_periode = 'Bulan 10 (Oktober 2026)';
    $where_date_fu = "AND DATE(fu.tgl_follow_up) BETWEEN '2026-10-01' AND '2026-10-31'";
    $where_date_cust = "DATE(c.tgl_input) BETWEEN '2026-10-01' AND '2026-10-31'";
} else if ($selected_bulan === '8-10') {
    $start_date = '2026-08-01';
    $end_date = '2026-10-31';
    $label_periode = 'Periode Total (Agt - Okt 2026)';
    $where_date_fu = "AND DATE(fu.tgl_follow_up) BETWEEN '2026-08-01' AND '2026-10-31'";
    $where_date_cust = "DATE(c.tgl_input) BETWEEN '2026-08-01' AND '2026-10-31'";
} else if ($selected_bulan === 'all_time') {
    $start_date = '2026-01-01';
    $end_date = '2026-12-31';
    $label_periode = 'Semua Waktu History';
    $where_date_fu = "";
    $where_date_cust = "1=1";
} else {
    $selected_bulan = '8';
    $start_date = '2026-08-01';
    $end_date = '2026-08-31';
    $label_periode = 'Bulan 8 (Agustus 2026)';
    $where_date_fu = "AND DATE(fu.tgl_follow_up) BETWEEN '2026-08-01' AND '2026-08-31'";
    $where_date_cust = "DATE(c.tgl_input) BETWEEN '2026-08-01' AND '2026-08-31'";
}

// Fetch Sales Rep Info
$stmt = $conn->prepare("SELECT id, nama_lengkap, email FROM sales WHERE id = ?");
if (!$stmt) {
    $res = $conn->query("SELECT id, nama_lengkap, email FROM sales WHERE id = {$sales_id}");
    $sales = $res ? $res->fetch_assoc() : null;
} else {
    $stmt->bind_param("i", $sales_id);
    $stmt->execute();
    $sales = $stmt->get_result()->fetch_assoc();
}

if (!$sales) {
    echo '<div class="alert alert-danger p-4 text-center">Sales tidak ditemukan.</div>';
    exit;
}

$target_omset = 200000000;

// Fetch Invoice Follow-Up records matching qualified customers:
// 1. Cust Baru: Input in program period (tgl_input >= 2026-08-01)
// 2. Cust Lama Reaktivasi: Input <= 2026-05-31 & NO invoice in June/July 2026
$sql_all = "
    SELECT 
        fu.id AS followup_id,
        fu.tgl_follow_up,
        fu.no_inv,
        fu.nominal_invoice,
        fu.sales_id,
        fu.keterangan AS catatan,
        c.id AS customer_id,
        c.nama_toko AS nama_customer,
        c.tgl_input AS tgl_input_cust,
        (SELECT cp.tlp_pic FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL LIMIT 1) AS no_hp,
        CASE 
            WHEN (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01') THEN 'A'
            ELSE 'B'
        END AS kat_type
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE fu.deleted_at IS NULL
      AND fu.sales_id = {$sales_id}
      AND fu.no_inv IS NOT NULL 
      AND fu.no_inv != ''
      AND (
          -- Kategori A: Customer Baru
          (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01')
          OR 
          -- Kategori B: Customer Lama Reaktivasi (Terakhir belanja <= bln 5, tidak belanja bln 6-7)
          ((c.tgl_input IS NULL OR c.tgl_input <= '2026-05-31')
           AND NOT EXISTS (
               SELECT 1 FROM follow_ups fu_mid 
               WHERE fu_mid.customer_id = c.id 
                 AND fu_mid.deleted_at IS NULL 
                 AND fu_mid.no_inv IS NOT NULL AND fu_mid.no_inv != '' 
                 AND fu_mid.tgl_follow_up >= '2026-06-01 00:00:00' 
                 AND fu_mid.tgl_follow_up <= '2026-07-31 23:59:59'
           ))
      )
      {$where_date_fu}
    ORDER BY fu.tgl_follow_up DESC
";

$res_all = $conn->query($sql_all);

if (!$res_all) {
    echo '<div class="alert alert-danger p-4 text-center"><strong>SQL Query Error:</strong> ' . htmlspecialchars($conn->error) . '</div>';
    exit;
}

$items = [];
$cust_seen = [];
$cust_seen_a = [];
$cust_seen_b = [];
$omset_a = 0;
$omset_b = 0;

while ($r = $res_all->fetch_assoc()) {
    $items[] = $r;
    $nom = (float)($r['nominal_invoice'] ?? 0);
    if ($r['kat_type'] === 'A') {
        $omset_a += $nom;
        if (!in_array($r['customer_id'], $cust_seen_a)) $cust_seen_a[] = $r['customer_id'];
    } else {
        $omset_b += $nom;
        if (!in_array($r['customer_id'], $cust_seen_b)) $cust_seen_b[] = $r['customer_id'];
    }
    if (!in_array($r['customer_id'], $cust_seen)) $cust_seen[] = $r['customer_id'];
}

// Fallback: If empty, fetch all follow ups by Sales Name matching invoice_followup_report.php
if (empty($items)) {
    $clean_sales_name = $conn->real_escape_string($sales['nama_lengkap']);
    $sql_fb = "
        SELECT 
            fu.id AS followup_id,
            fu.tgl_follow_up,
            fu.no_inv,
            fu.nominal_invoice,
            fu.sales_id,
            fu.keterangan AS catatan,
            c.id AS customer_id,
            c.nama_toko AS nama_customer,
            c.tgl_input AS tgl_input_cust,
            (SELECT cp.tlp_pic FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL LIMIT 1) AS no_hp,
            'B' AS kat_type
        FROM follow_ups fu
        JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
        JOIN sales s ON fu.sales_id = s.id
        WHERE s.nama_lengkap LIKE '%{$clean_sales_name}%'
          AND fu.no_inv IS NOT NULL 
          AND fu.no_inv != ''
          AND fu.deleted_at IS NULL
          {$where_date_fu}
        ORDER BY fu.tgl_follow_up DESC
    ";
    $res_fb = $conn->query($sql_fb);
    if ($res_fb) {
        while ($r = $res_fb->fetch_assoc()) {
            $items[] = $r;
            $nom = (float)($r['nominal_invoice'] ?? 0);
            $omset_b += $nom;
            if (!in_array($r['customer_id'], $cust_seen_b)) $cust_seen_b[] = $r['customer_id'];
            if (!in_array($r['customer_id'], $cust_seen)) $cust_seen[] = $r['customer_id'];
        }
    }
}

$total_omset_combined = $omset_a + $omset_b;
$total_cust_a = count($cust_seen_a);
$total_cust_b = count($cust_seen_b);
$total_cust_combined = count($cust_seen);

$pct_target_comb = min(100, round(($total_omset_combined / $target_omset) * 100, 1));
$pct_target_a = min(100, round(($omset_a / $target_omset) * 100, 1));
$pct_target_b = min(100, round(($omset_b / $target_omset) * 100, 1));
?>

<style>
.modal-table-scrollable::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}
.modal-table-scrollable::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 10px;
}
.modal-table-scrollable::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}
.modal-table-scrollable::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}

.kpi-card-clean {
    border-radius: 16px;
    padding: 16px 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kpi-card-clean:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

.filter-btn-modal {
    font-size: 11.5px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 50px;
    transition: all 0.2s ease;
}
</style>

<!-- MODAL HEADER -->
<div class="modal-header border-0 pb-0" style="background: #FFFFFF; border-radius: 24px 24px 0 0; padding: 28px 32px 20px; position: relative;">
    <div style="position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #DC2626 0%, #F59E0B 50%, #10B981 100%);"></div>
    <div class="w-100">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">
                        🇮🇩 EVENT KEMERDEKAAN LOEWIX
                    </span>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold" style="font-size: 11px;">
                        <?= $label_periode ?>
                    </span>
                </div>
                <h3 class="fw-bold mb-1 text-dark" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.4px;">
                    👤 <?= htmlspecialchars($sales['nama_lengkap']) ?>
                </h3>
                <small class="text-secondary fw-semibold" style="font-size: 13px;">Akumulasi Pencapaian Akuisisi Customer Baru & Reaktivasi Portofolio Lama</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- 3 KPI Cards Grid -->
        <div class="row g-3 mt-3 pt-2">
            <div class="col-12 col-md-4">
                <div class="kpi-card-clean" style="background: #EFF6FF; border: 1.5px solid #BFDBFE;">
                    <small class="d-block fw-bold mb-1 text-uppercase" style="font-size: 11px; color: #1E40AF;">🚀 Akuisisi Customer Baru</small>
                    <span class="fw-bold font-monospace" style="font-size: 17px; color: #1E3A8A;">Rp <?= number_format($omset_a, 0, ',', '.') ?></span>
                    <div class="fw-semibold mt-1" style="font-size: 11.5px; color: #2563EB;"><?= $total_cust_a ?> Mitra Baru</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card-clean" style="background: #FEF3C7; border: 1.5px solid #FDE68A;">
                    <small class="d-block fw-bold mb-1 text-uppercase" style="font-size: 11px; color: #92400E;">🔥 Reaktivasi Portofolio</small>
                    <span class="fw-bold font-monospace" style="font-size: 17px; color: #78350F;">Rp <?= number_format($omset_b, 0, ',', '.') ?></span>
                    <div class="fw-semibold mt-1" style="font-size: 11.5px; color: #D97706;"><?= $total_cust_b ?> Mitra Transaksi</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card-clean" style="background: #ECFDF5; border: 1.5px solid #A7F3D0;">
                    <small class="d-block fw-bold mb-1 text-uppercase" style="font-size: 11px; color: #065F46;">🏆 Total Akumulasi Omset</small>
                    <span class="fw-bold font-monospace" style="font-size: 18px; color: #047857;">Rp <?= number_format($total_omset_combined, 0, ',', '.') ?></span>
                    <div class="fw-bold mt-1" style="font-size: 11.5px; color: #059669;"><?= $total_cust_combined ?> Total Mitra (<?= $pct_target_comb ?>% Target)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-body p-4 bg-light">
    <!-- Interactive Month Filter Bar -->
    <div class="card border-0 shadow-sm p-2 mb-3 bg-white" style="border-radius: 50px; border: 1px solid #E2E8F0;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark" style="font-size: 12.5px;"><i class="bi bi-funnel-fill text-danger me-1"></i>Filter Periode:</span>
                <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold" style="font-size: 11px;"><?= $label_periode ?></span>
            </div>
            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                <button type="button" onclick="switchModalBulanFilter(<?= $sales_id ?>, '8')" class="btn filter-btn-modal <?= ($selected_bulan==='8')?'btn-danger text-white shadow-sm':'btn-outline-secondary' ?>">
                    📅 Agt (Bulan 8)
                </button>
                <button type="button" onclick="switchModalBulanFilter(<?= $sales_id ?>, '9')" class="btn filter-btn-modal <?= ($selected_bulan==='9')?'btn-warning text-dark shadow-sm':'btn-outline-secondary' ?>">
                    📅 Sep (Bulan 9)
                </button>
                <button type="button" onclick="switchModalBulanFilter(<?= $sales_id ?>, '10')" class="btn filter-btn-modal <?= ($selected_bulan==='10')?'btn-success text-white shadow-sm':'btn-outline-secondary' ?>">
                    📅 Okt (Bulan 10)
                </button>
                <button type="button" onclick="switchModalBulanFilter(<?= $sales_id ?>, '8-10')" class="btn filter-btn-modal <?= ($selected_bulan==='8-10')?'btn-primary text-white shadow-sm':'btn-outline-secondary' ?>">
                    🏆 Total (Agt - Okt)
                </button>
                <button type="button" onclick="switchModalBulanFilter(<?= $sales_id ?>, 'all_time')" class="btn filter-btn-modal <?= ($selected_bulan==='all_time')?'btn-dark text-white shadow-sm':'btn-outline-secondary' ?>">
                    🗓️ Semua Waktu
                </button>
            </div>
        </div>
    </div>

    <!-- Progress Bar Card -->
    <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius: 16px; border: 1px solid #E2E8F0;">
        <div class="d-flex justify-content-between align-items-center mb-1.5 text-dark fw-bold" style="font-size: 12.5px;">
            <span>🎯 Target Sultan Rp 200.000.000,- / Bulan</span>
            <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold"><?= $pct_target_comb ?>% Tuntas (Rp <?= number_format($total_omset_combined, 0, ',', '.') ?>)</span>
        </div>
        <div class="progress" style="height: 10px; border-radius: 10px; background: #E2E8F0;">
            <div class="progress-bar bg-success bg-gradient" role="progressbar" style="width: <?= max($pct_target_comb, 3) ?>%; border-radius: 10px;"></div>
        </div>
    </div>

    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 14.5px;">
        📄 Rincian <?= count($items) ?> Transaksi Penjualan & Invoice Customer (<?= $label_periode ?>):
    </h6>

    <?php if (empty($items)): ?>
        <div class="text-center py-5 bg-white rounded-4 border">
            <div class="fs-1 mb-2">📦</div>
            <h6 class="fw-bold text-dark mb-1">Belum ada rincian transaksi.</h6>
            <small class="text-muted">Tidak ditemukan customer / invoice pada periode <?= htmlspecialchars($label_periode) ?>.</small>
        </div>
    <?php else: ?>
        <!-- WIDE SCROLLABLE TABLE CONTAINER WITH GUARANTEED FIT -->
        <div class="modal-table-scrollable bg-white rounded-4 shadow-sm border" style="overflow-x: auto; max-height: 520px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0" style="min-width: 980px; width: 100%; font-size: 13px;">
                <thead style="background: #F8FAFC; color: #475569; font-size: 11.5px; position: sticky; top: 0; z-index: 10; border-bottom: 2px solid #E2E8F0;">
                    <tr>
                        <th class="py-3 ps-3 text-uppercase font-monospace" style="width: 50px;">NO</th>
                        <th class="py-3 text-uppercase font-monospace" style="width: 190px;">KATEGORI & TGL INPUT</th>
                        <th class="py-3 text-uppercase font-monospace" style="width: 270px;">CUSTOMER & TELEPON</th>
                        <th class="py-3 text-uppercase font-monospace" style="width: 170px;">NO. INVOICE</th>
                        <th class="py-3 text-uppercase font-monospace" style="width: 160px;">TGL TRANSAKSI</th>
                        <th class="py-3 text-uppercase font-monospace text-end pe-3" style="width: 170px;">NOMINAL OMSET</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-muted" style="white-space: nowrap;"><?= $idx + 1 ?></td>
                            <td style="white-space: nowrap;">
                                <?php if ($row['kat_type'] === 'A'): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold border border-primary border-opacity-20 px-2.5 py-1 rounded-pill" style="font-size: 11px;">
                                        🚀 Akuisisi Baru
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-20 text-dark fw-bold border border-warning border-opacity-30 px-2.5 py-1 rounded-pill" style="font-size: 11px; background-color: #FEF3C7; color: #92400E;">
                                        🔥 Reaktivasi Lama
                                    </span>
                                <?php endif; ?>

                                <div class="mt-1 text-muted" style="font-size: 11px;">
                                    <?php if (!empty($row['tgl_input_cust'])): ?>
                                        <i class="bi bi-calendar-plus text-primary me-1" style="font-size: 10.5px;"></i>Ditambahkan: <strong class="text-dark"><?= date('d M Y', strtotime($row['tgl_input_cust'])) ?></strong>
                                    <?php else: ?>
                                        <i class="bi bi-calendar-check text-secondary me-1" style="font-size: 10.5px;"></i>Ditambahkan: <strong class="text-secondary">&le; Mei 2026</strong>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="white-space: nowrap;">
                                <div class="fw-bold text-dark" style="font-size: 13.5px;"><?= htmlspecialchars($row['nama_customer']) ?></div>
                                <?php if (!empty($row['no_hp'])): ?>
                                    <small class="text-secondary font-monospace"><i class="bi bi-telephone-fill me-1" style="font-size: 10px;"></i><?= htmlspecialchars($row['no_hp']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if (!empty($row['no_inv'])): ?>
                                    <span class="badge bg-light text-dark fw-bold border px-3 py-1.5 rounded-3 font-monospace" style="font-size: 11.5px; background: #F1F5F9;">
                                        <?= htmlspecialchars($row['no_inv']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fw-semibold" style="font-size: 11px;">Belum Invoice</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if (!empty($row['tgl_follow_up'])): ?>
                                    <span class="text-dark fw-bold" style="font-size: 12px;"><?= date('d M Y', strtotime($row['tgl_follow_up'])) ?></span>
                                    <small class="text-muted d-block" style="font-size: 11px;"><?= date('H:i', strtotime($row['tgl_follow_up'])) ?> WIB</small>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11px;">Input: <?= date('d M Y', strtotime($row['tgl_input_cust'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3" style="white-space: nowrap;">
                                <?php if (!empty($row['nominal_invoice']) && (float)$row['nominal_invoice'] > 0): ?>
                                    <span class="fw-bold text-success font-monospace" style="font-size: 14px;">
                                        Rp <?= number_format($row['nominal_invoice'], 0, ',', '.') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11px;">Rp 0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light fw-bold border-top" style="position: sticky; bottom: 0; z-index: 10; background: #F8FAFC;">
                    <tr>
                        <td colspan="5" class="ps-3 py-3 text-dark">TOTAL AKUMULASI OMSET PENJUALAN:</td>
                        <td class="text-end pe-3 py-3 text-success font-monospace fs-6 fw-bold">
                            Rp <?= number_format($total_omset_combined, 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-footer bg-light border-top-0 pt-0 pe-4 pb-4">
    <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal">Tutup</button>
</div>
