<?php
/**
 * AJAX HANDLER FOR BONUS COMPETITION SALES DETAIL MODAL (TEMA KEMERDEKAAN 🇮🇩 3D EDITION)
 */
require_once 'includes/db.php';

$sales_id = intval($_GET['sales_id'] ?? 0);
$kat = trim($_GET['kat'] ?? 'a');
$selected_bulan = trim($_GET['periode_bulan'] ?? '8');

if ($sales_id <= 0) {
    echo '<div class="alert alert-danger p-4 text-center">Invalid Sales ID.</div>';
    exit;
}

// Month Label
if ($selected_bulan === '9') {
    $start_date = '2026-09-01';
    $end_date = '2026-09-30';
    $label_periode = 'Bulan 9 (September 2026)';
} else if ($selected_bulan === '10') {
    $start_date = '2026-10-01';
    $end_date = '2026-10-31';
    $label_periode = 'Bulan 10 (Oktober 2026)';
} else if ($selected_bulan === '8-10' || $selected_bulan === 'all') {
    $start_date = '2026-08-01';
    $end_date = '2026-10-31';
    $label_periode = 'Periode Bulan 8 - 10 (Agt - Okt 2026)';
} else {
    $selected_bulan = '8';
    $start_date = '2026-08-01';
    $end_date = '2026-08-31';
    $label_periode = 'Bulan 8 (Agustus 2026)';
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

// Clean & Direct Query: Fetch all Invoice Follow-Up records matching invoice_followup_report.php
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
            WHEN (DATE(c.tgl_input) BETWEEN '{$start_date}' AND '{$end_date}') THEN 'A'
            ELSE 'B'
        END AS kat_type
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE fu.deleted_at IS NULL
      AND (fu.sales_id = {$sales_id} OR c.sales_id = {$sales_id})
      AND fu.no_inv IS NOT NULL 
      AND fu.no_inv != ''
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

<!-- MODAL HEADER TEMA KEMERDEKAAN LOEWIX 🇮🇩 3D -->
<div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 55%, #450A0A 100%); color: #FFF; border-radius: 24px 24px 0 0; padding: 28px 32px 22px; position: relative;">
    <div style="position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #DC2626 0%, #FFFFFF 50%, #DC2626 100%);"></div>
    <div class="w-100">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold" style="font-size: 11px; border: 1.5px solid #FCD34D; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);">
                        🇮🇩 DETAIL RINCIAN GABUNGAN SALES
                    </span>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold" style="font-size: 11px; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);">
                        <?= $label_periode ?>
                    </span>
                </div>
                <h3 class="fw-extrabold mb-1 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.4px;">
                    👤 <?= htmlspecialchars($sales['nama_lengkap']) ?>
                </h3>
                <small class="text-white-50" style="font-size: 12.5px;">Gabungan Kat A (Cust Baru) & Kat B (Reaktivasi Customer Lama)</small>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- 3D Metric KPI Cards Grid -->
        <div class="row g-2.5 mt-3 pt-3 border-top border-secondary border-opacity-30">
            <div class="col-12 col-md-4">
                <div class="p-3 rounded-4" style="background: rgba(14, 165, 233, 0.12); border: 1.5px solid rgba(56, 189, 248, 0.3); backdrop-filter: blur(6px); box-shadow: inset 0 1px 1px rgba(255,255,255,0.2);">
                    <small class="text-info d-block fw-extrabold" style="font-size: 11px; letter-spacing: 0.5px;">🚀 KAT A: CUST BARU</small>
                    <span class="fw-extrabold text-white font-monospace" style="font-size: 16px; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">Rp <?= number_format($omset_a, 0, ',', '.') ?></span>
                    <div class="text-white-50 mt-0.5" style="font-size: 11px;"><?= $total_cust_a ?> Customer</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 rounded-4" style="background: rgba(245, 158, 11, 0.12); border: 1.5px solid rgba(251, 191, 36, 0.3); backdrop-filter: blur(6px); box-shadow: inset 0 1px 1px rgba(255,255,255,0.2);">
                    <small class="text-warning d-block fw-extrabold" style="font-size: 11px; letter-spacing: 0.5px;">🔥 KAT B: REAKTIVASI</small>
                    <span class="fw-extrabold text-white font-monospace" style="font-size: 16px; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">Rp <?= number_format($omset_b, 0, ',', '.') ?></span>
                    <div class="text-white-50 mt-0.5" style="font-size: 11px;"><?= $total_cust_b ?> Customer Belanja</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 rounded-4" style="background: linear-gradient(135deg, rgba(220, 38, 38, 0.25) 0%, rgba(245, 158, 11, 0.25) 100%); border: 1.5px solid #FCD34D; box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);">
                    <small class="text-warning d-block fw-extrabold" style="font-size: 11px; letter-spacing: 0.5px;">🏆 TOTAL COMBINED OMSET</small>
                    <span class="fw-extrabold text-warning font-monospace" style="font-size: 17px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">Rp <?= number_format($total_omset_combined, 0, ',', '.') ?></span>
                    <div class="fw-bold mt-0.5" style="font-size: 11px; color: #34D399;"><?= $total_cust_combined ?> Customer Total (<?= $pct_target_comb ?>% Target)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-body p-4 bg-light">
    <!-- Progress Bar Card -->
    <div class="card border-0 shadow-sm p-3.5 mb-4" style="border-radius: 18px; border: 1px solid #E2E8F0;">
        <div class="d-flex justify-content-between align-items-center mb-1.5 text-dark fw-bold" style="font-size: 12.5px;">
            <span class="d-flex align-items-center gap-1.5">🎯 Target Sultan Rp 200.000.000,- / Bulan</span>
            <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold"><?= $pct_target_comb ?>% Tuntas (Rp <?= number_format($total_omset_combined, 0, ',', '.') ?>)</span>
        </div>
        <div class="progress" style="height: 12px; border-radius: 10px; background: #E2E8F0; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
            <div class="progress-bar bg-success bg-gradient" role="progressbar" style="width: <?= max($pct_target_comb, 3) ?>%; border-radius: 10px; box-shadow: 0 0 10px rgba(16, 185, 129, 0.6);"></div>
        </div>
    </div>

    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 14.5px;">
        📄 Rincian <?= count($items) ?> Transaksi Invoice Customer:
    </h6>

    <?php if (empty($items)): ?>
        <div class="text-center py-5 bg-white rounded-4 border">
            <div class="fs-1 mb-2">📦</div>
            <h6 class="fw-bold text-dark mb-1">Belum ada rincian transaksi.</h6>
            <small class="text-muted">Tidak ditemukan customer / invoice pada periode ini.</small>
        </div>
    <?php else: ?>
        <div class="table-responsive bg-white rounded-4 shadow-sm border" style="overflow: hidden;">
            <table class="table table-hover align-middle mb-0" style="font-size: 12.5px;">
                <thead style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); color: #94A3B8; font-size: 11.5px; border-bottom: 2px solid #DC2626;">
                    <tr>
                        <th class="py-3 ps-3 text-white">No</th>
                        <th class="py-3 text-white">Kategori</th>
                        <th class="py-3 text-white">Customer</th>
                        <th class="py-3 text-white">No. Invoice</th>
                        <th class="py-3 text-white">Tgl Transaksi</th>
                        <th class="py-3 text-end pe-3 text-white">Nominal Omset (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $row): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-secondary"><?= $idx + 1 ?></td>
                            <td>
                                <?php if ($row['kat_type'] === 'A'): ?>
                                    <span class="badge bg-info bg-opacity-15 text-info fw-bold border border-info border-opacity-30 px-2.5 py-1.5 rounded-pill" style="font-size: 10.5px; background: #E0F2FE; color: #0369A1;">
                                        🚀 Kat A: Baru
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-15 text-dark fw-bold border border-warning border-opacity-30 px-2.5 py-1.5 rounded-pill" style="font-size: 10.5px; background-color: #FEF3C7; color: #92400E;">
                                        🔥 Kat B: Reaktivasi
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-extrabold text-dark" style="font-size: 13px;"><?= htmlspecialchars($row['nama_customer']) ?></div>
                                <?php if (!empty($row['no_hp'])): ?>
                                    <small class="text-muted font-monospace"><i class="bi bi-telephone-fill text-secondary me-1" style="font-size: 10px;"></i><?= htmlspecialchars($row['no_hp']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['no_inv'])): ?>
                                    <span class="badge bg-dark bg-opacity-10 text-dark fw-bold border px-2.5 py-1 rounded-3 font-monospace" style="font-size: 11px; background: #F1F5F9;">
                                        <?= htmlspecialchars($row['no_inv']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fw-semibold" style="font-size: 11px;">Belum Invoice</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['tgl_follow_up'])): ?>
                                    <span class="text-dark fw-bold" style="font-size: 12px;"><?= date('d M Y', strtotime($row['tgl_follow_up'])) ?></span>
                                    <div class="text-muted" style="font-size: 10.5px;"><?= date('H:i', strtotime($row['tgl_follow_up'])) ?> WIB</div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11px;">Input: <?= date('d M Y', strtotime($row['tgl_input_cust'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if (!empty($row['nominal_invoice']) && (float)$row['nominal_invoice'] > 0): ?>
                                    <span class="fw-extrabold text-success font-monospace" style="font-size: 13.5px;">
                                        Rp <?= number_format($row['nominal_invoice'], 0, ',', '.') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11px;">Rp 0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-dark text-white fw-bold border-top" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);">
                    <tr>
                        <td colspan="5" class="ps-3 py-3 text-white">GRAND TOTAL COMBINED OMSET:</td>
                        <td class="text-end pe-3 py-3 text-warning font-monospace fs-6 fw-extrabold">
                            Rp <?= number_format($total_omset_combined, 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-footer bg-light border-top-0 pt-0 pe-4 pb-4">
    <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold btn-sm shadow-sm" data-bs-dismiss="modal">Tutup</button>
</div>
