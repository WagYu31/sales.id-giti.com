<?php
/**
 * AJAX HANDLER FOR BONUS COMPETITION SALES DETAIL MODAL
 */
require_once 'includes/db.php';

$sales_id = intval($_GET['sales_id'] ?? 0);
$kat = trim($_GET['kat'] ?? 'a'); // 'a' or 'b'
$selected_bulan = trim($_GET['periode_bulan'] ?? '8');

if ($sales_id <= 0) {
    echo '<div class="alert alert-danger">Invalid Sales ID.</div>';
    exit;
}

// Set date range based on selected month
if ($selected_bulan === '9') {
    $start_periode = '2026-09-01 00:00:00';
    $end_periode = '2026-09-30 23:59:59';
    $label_periode = 'Bulan 9 (September 2026)';
} else if ($selected_bulan === '10') {
    $start_periode = '2026-10-01 00:00:00';
    $end_periode = '2026-10-31 23:59:59';
    $label_periode = 'Bulan 10 (Oktober 2026)';
} else if ($selected_bulan === '8-10' || $selected_bulan === 'all') {
    $start_periode = '2026-08-01 00:00:00';
    $end_periode = '2026-10-31 23:59:59';
    $label_periode = 'Periode Bulan 8 - 10 (Agt - Okt 2026)';
} else {
    $start_periode = '2026-08-01 00:00:00';
    $end_periode = '2026-08-31 23:59:59';
    $label_periode = 'Bulan 8 (Agustus 2026)';
}

// Fetch Sales info
$stmt = $conn->prepare("SELECT id, nama_lengkap, email, no_hp FROM sales WHERE id = ?");
$stmt->bind_param("i", $sales_id);
$stmt->execute();
$sales = $stmt->get_result()->fetch_assoc();

if (!$sales) {
    echo '<div class="alert alert-danger">Sales tidak ditemukan.</div>';
    exit;
}

$target_omset = 200000000;
$items = [];
$total_omset = 0;
$total_cust_count = 0;

if ($kat === 'a') {
    $kat_title = "🚀 Kategori A: Customer Baru Terbanyak";
    $kat_desc = "Daftar Customer Baru & transaksi omset invoice per " . $label_periode;

    $sql = "
        SELECT 
            c.id AS customer_id,
            c.nama_customer,
            c.no_hp,
            c.tgl_input AS tgl_input_cust,
            fu.id AS followup_id,
            fu.no_inv,
            fu.tgl_follow_up,
            fu.nominal_invoice,
            fu.catatan
        FROM customers c
        LEFT JOIN follow_ups fu ON fu.customer_id = c.id 
            AND fu.deleted_at IS NULL 
            AND fu.tgl_follow_up >= '{$start_periode}' 
            AND fu.tgl_follow_up <= '{$end_periode}'
            AND fu.no_inv IS NOT NULL AND fu.no_inv != ''
        WHERE c.sales_id = {$sales_id}
          AND c.deleted_at IS NULL
          AND c.tgl_input >= '{$start_periode}' 
          AND c.tgl_input <= '{$end_periode}'
        ORDER BY fu.tgl_follow_up DESC, c.tgl_input DESC
    ";
    $res = $conn->query($sql);
    $cust_seen = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $items[] = $r;
            if (!empty($r['nominal_invoice'])) {
                $total_omset += (float)$r['nominal_invoice'];
            }
            if (!in_array($r['customer_id'], $cust_seen)) {
                $cust_seen[] = $r['customer_id'];
            }
        }
    }
    $total_cust_count = count($cust_seen);
} else {
    $kat_title = "🔥 Kategori B: Reaktivasi Customer Lama";
    $kat_desc = "Daftar Customer Lama yang berhasil belanja kembali per " . $label_periode;

    $sql = "
        SELECT 
            c.id AS customer_id,
            c.nama_customer,
            c.no_hp,
            c.tgl_input AS tgl_input_cust,
            fu.id AS followup_id,
            fu.no_inv,
            fu.tgl_follow_up,
            fu.nominal_invoice,
            fu.catatan
        FROM follow_ups fu
        JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
        WHERE fu.sales_id = {$sales_id}
          AND fu.deleted_at IS NULL
          AND fu.tgl_follow_up >= '{$start_periode}' 
          AND fu.tgl_follow_up <= '{$end_periode}'
          AND fu.no_inv IS NOT NULL AND fu.no_inv != ''
          AND (c.tgl_input < '{$start_periode}' OR c.tgl_input IS NULL)
        ORDER BY fu.tgl_follow_up DESC
    ";
    $res = $conn->query($sql);
    $cust_seen = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $items[] = $r;
            $total_omset += (float)$r['nominal_invoice'];
            if (!in_array($r['customer_id'], $cust_seen)) {
                $cust_seen[] = $r['customer_id'];
            }
        }
    }
    $total_cust_count = count($cust_seen);
}

$pct_target = min(100, round(($total_omset / $target_omset) * 100, 1));
?>

<!-- MODAL CONTENT -->
<div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); color: #FFF; border-radius: 20px 20px 0 0; padding: 24px 28px 18px;">
    <div class="w-100">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold mb-2" style="font-size: 11px; border: 1px solid #FCD34D;">
                    <?= $kat_title ?>
                </span>
                <h4 class="fw-bold mb-1 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                    👤 <?= htmlspecialchars($sales['nama_lengkap']) ?>
                </h4>
                <small class="text-white-50" style="font-size: 12px;"><?= $kat_desc ?></small>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Metric KPI Cards Grid -->
        <div class="row g-2 mt-3 pt-2 border-top border-secondary border-opacity-30">
            <div class="col-6 col-md-4">
                <div class="p-2.5 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10">
                    <small class="text-white-50 d-block" style="font-size: 10.5px;">TOTAL OMSET INVOICE</small>
                    <span class="fw-bold text-warning font-monospace" style="font-size: 15px;">Rp <?= number_format($total_omset, 0, ',', '.') ?></span>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="p-2.5 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10">
                    <small class="text-white-50 d-block" style="font-size: 10.5px;">PENCAPAIAN TARGET</small>
                    <span class="fw-bold text-emerald-400 font-monospace" style="font-size: 15px; color: #34D399;"><?= $pct_target ?>% <small class="text-white-50" style="font-size: 10px;">/ Rp 200M</small></span>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-2.5 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10">
                    <small class="text-white-50 d-block" style="font-size: 10.5px;"><?= ($kat==='a')?'TOTAL CUST BARU':'TOTAL CUST REAKTIVASI' ?></small>
                    <span class="fw-bold text-info" style="font-size: 15px;"><?= $total_cust_count ?> Customer</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-body p-4 bg-light">
    <!-- Progress Bar -->
    <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius: 14px;">
        <div class="d-flex justify-content-between align-items-center mb-1 text-dark fw-bold" style="font-size: 12px;">
            <span>Target Sultan Rp 200.000.000,- / Bulan</span>
            <span class="text-primary"><?= $pct_target ?>% Tuntas</span>
        </div>
        <div class="progress" style="height: 10px; border-radius: 10px; background: #E2E8F0;">
            <div class="progress-bar <?= ($kat==='a')?'bg-success':'bg-warning' ?> bg-gradient" role="progressbar" style="width: <?= max($pct_target, 3) ?>%; border-radius: 10px;"></div>
        </div>
    </div>

    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 14px;">
        📄 Detail Rincian Customer & Invoice Transaction:
    </h6>

    <?php if (empty($items)): ?>
        <div class="text-center py-4 bg-white rounded-3 border">
            <div class="fs-2 mb-2">📦</div>
            <h6 class="fw-bold text-dark mb-1">Belum ada rincian transaksi.</h6>
            <small class="text-muted">Tidak ditemukan invoice pada periode ini.</small>
        </div>
    <?php else: ?>
        <div class="table-responsive bg-white rounded-3 shadow-sm border">
            <table class="table table-hover align-middle mb-0" style="font-size: 12.5px;">
                <thead class="bg-light text-secondary" style="font-size: 11.5px;">
                    <tr>
                        <th class="py-2.5 ps-3">No</th>
                        <th class="py-2.5">Customer</th>
                        <th class="py-2.5">No. Invoice</th>
                        <th class="py-2.5">Tgl Follow Up</th>
                        <th class="py-2.5 text-end pe-3">Nominal Omset (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-muted"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_customer']) ?></div>
                                <?php if (!empty($row['no_hp'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($row['no_hp']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['no_inv'])): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold border border-primary border-opacity-20 px-2 py-1" style="font-size: 11px;">
                                        <?= htmlspecialchars($row['no_inv']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fw-semibold" style="font-size: 11px;">Belum Invoice</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['tgl_follow_up'])): ?>
                                    <span class="text-dark fw-medium"><?= date('d M Y', strtotime($row['tgl_follow_up'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if (!empty($row['nominal_invoice'])): ?>
                                    <span class="fw-bold text-success font-monospace" style="font-size: 13px;">
                                        Rp <?= number_format($row['nominal_invoice'], 0, ',', '.') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11px;">Rp 0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light fw-bold border-top">
                    <tr>
                        <td colspan="4" class="ps-3 text-dark">TOTAL OMSET INVOICE:</td>
                        <td class="text-end pe-3 text-success font-monospace fs-6">
                            Rp <?= number_format($total_omset, 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-footer bg-light border-top-0 pt-0 pe-4 pb-3">
    <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold btn-sm" data-bs-dismiss="modal">Tutup</button>
</div>
