<?php
/**
 * WIDGET TRACKER REALTIME KOMPETISI BONUS SULTAN LOEWIX - RP 4.000.000,-
 * PERIODE: MULAI 1 AGUSTUS 2026 (SEMUA DARI 0)
 * TARGET OMSET: RP 200.000.000,- (200 JUTA RUPIAH) PER BULAN PER SALES
 * METRIK: NOMINAL OMSET INVOICE SALES
 */

$target_omset_per_bulan = 200000000; // Rp 200.000.000,-

// Parse month filter (Bulan 8, 9, 10 or 8-10)
$selected_bulan = trim($_GET['periode_bulan'] ?? '8');

if ($selected_bulan === '9') {
    $start_date = '2026-09-01';
    $end_date = '2026-09-30';
    $start_periode = '2026-09-01 00:00:00';
    $end_periode = '2026-09-30 23:59:59';
    $label_periode = 'Bulan 9 (September 2026)';
    $syarat_kat_a = 'Cust Baru per Sep 2026';
    $syarat_kat_b = 'Reaktivasi Cust Lama Sep 2026';
} else if ($selected_bulan === '10') {
    $start_date = '2026-10-01';
    $end_date = '2026-10-31';
    $start_periode = '2026-10-01 00:00:00';
    $end_periode = '2026-10-31 23:59:59';
    $label_periode = 'Bulan 10 (Oktober 2026)';
    $syarat_kat_a = 'Cust Baru per Okt 2026';
    $syarat_kat_b = 'Reaktivasi Cust Lama Okt 2026';
} else if ($selected_bulan === '8-10' || $selected_bulan === 'all') {
    $selected_bulan = '8-10';
    $start_date = '2026-08-01';
    $end_date = '2026-10-31';
    $start_periode = '2026-08-01 00:00:00';
    $end_periode = '2026-10-31 23:59:59';
    $label_periode = 'Periode Bulan 8 - 10 (Agt - Okt 2026)';
    $syarat_kat_a = 'Cust Baru Periode Agt - Okt 2026';
    $syarat_kat_b = 'Reaktivasi Cust Lama Agt - Okt 2026';
} else {
    $selected_bulan = '8';
    $start_date = '2026-08-01';
    $end_date = '2026-08-31';
    $start_periode = '2026-08-01 00:00:00';
    $end_periode = '2026-08-31 23:59:59';
    $label_periode = 'Bulan 8 (Agustus 2026)';
    $syarat_kat_a = 'Cust Baru per 1 Agt 2026';
    $syarat_kat_b = 'Reaktivasi Cust Lama per 1 Agt 2026';
}

// 1. Fetch Top Sales Kategori A: Customer Baru Terbanyak & Omset Invoice (Mulai 1 Agt 2026)
$sql_kat_a = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT c.id) AS total_customer_baru,
        COALESCE(SUM(CASE WHEN DATE(fu.tgl_follow_up) BETWEEN '{$start_date}' AND '{$end_date}' AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.nominal_invoice ELSE 0 END), 0) AS total_omset_baru
    FROM sales s
    JOIN customers c ON c.sales_id = s.id AND c.deleted_at IS NULL
    LEFT JOIN follow_ups fu ON fu.customer_id = c.id AND fu.deleted_at IS NULL
    WHERE DATE(c.tgl_input) BETWEEN '{$start_date}' AND '{$end_date}'
      AND (s.role = 'sales' OR s.role = 'superadmin')
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_omset_baru DESC, total_customer_baru DESC
    LIMIT 5
";
$res_kat_a = $conn->query($sql_kat_a);
$list_kat_a = [];
if ($res_kat_a) {
    while ($r = $res_kat_a->fetch_assoc()) {
        $list_kat_a[] = $r;
    }
}

// Fetch Combined Sales Leaderboard (Kat A + Kat B Combined)
$sql_combined = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT CASE WHEN DATE(c.tgl_input) BETWEEN '{$start_date}' AND '{$end_date}' THEN c.id END) AS total_cust_baru,
        COUNT(DISTINCT CASE WHEN (DATE(c.tgl_input) < '{$start_date}' OR c.tgl_input IS NULL OR c.tgl_input = '' OR c.tgl_input LIKE '0000%') THEN fu.customer_id END) AS total_cust_reaktivasi,
        COUNT(DISTINCT fu.customer_id) AS total_cust_belanja,
        COALESCE(SUM(fu.nominal_invoice), 0) AS total_omset_combined
    FROM sales s
    JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL
    JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE DATE(fu.tgl_follow_up) BETWEEN '{$start_date}' AND '{$end_date}'
      AND fu.no_inv IS NOT NULL AND fu.no_inv != ''
      AND (s.role = 'sales' OR s.role = 'superadmin')
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_omset_combined DESC, total_cust_belanja DESC
    LIMIT 10
";
$res_combined = $conn->query($sql_combined);
$list_combined = [];
if ($res_combined) {
    while ($r = $res_combined->fetch_assoc()) {
        $list_combined[] = $r;
    }
}
?>

<!-- 3D SULTAN BONUS COMPETITION TRACKER WIDGET - EVENT KEMERDEKAAN LOEWIX 🇮🇩 -->
<style>
@keyframes pulseGoldGlow {
    0%, 100% { box-shadow: 0 10px 30px -5px rgba(220, 38, 38, 0.15); }
    50% { box-shadow: 0 18px 40px -4px rgba(220, 38, 38, 0.3); }
}

@keyframes floatCashBadge {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-4px) rotate(2deg); }
}

.bonus-competition-card {
    background: #FFFFFF;
    border-radius: 26px;
    padding: 32px 36px;
    margin-bottom: 34px;
    position: relative;
    box-shadow: 0 22px 48px -12px rgba(15, 23, 42, 0.09), 0 4px 14px rgba(15, 23, 42, 0.02);
    border: 1.5px solid rgba(226, 232, 240, 0.95) !important;
    overflow: hidden;
}

.bonus-competition-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4.5px;
    background: linear-gradient(90deg, #DC2626 0%, #F59E0B 50%, #10B981 100%);
}

.cash-prize-badge {
    background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
    color: #FFFFFF;
    font-weight: 800;
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 12px;
    letter-spacing: 0.5px;
    box-shadow: 0 8px 20px -4px rgba(220, 38, 38, 0.4);
    animation: floatCashBadge 3s ease-in-out infinite;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.bonus-kat-box {
    background: linear-gradient(145deg, #0F172A 0%, #1E293B 100%);
    border-radius: 20px;
    padding: 24px 28px;
    height: 100%;
    position: relative;
    box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.1), 0 12px 24px -6px rgba(15, 23, 42, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.rank-row-item {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 14px;
    padding: 12px 16px;
    margin-bottom: 10px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.rank-row-item:hover {
    background: rgba(255, 255, 255, 0.09);
    border-color: rgba(251, 191, 36, 0.4);
    transform: translateY(-2px);
}

.rank-pill {
    width: 28px; height: 28px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 11px;
}

.rank-pill.gold { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color: #FFF; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.4); }
.rank-pill.silver { background: linear-gradient(135deg, #94A3B8 0%, #64748B 100%); color: #FFF; }
.rank-pill.bronze { background: linear-gradient(135deg, #D97706 0%, #B45309 100%); color: #FFF; }
.rank-pill.normal { background: rgba(255, 255, 255, 0.1); color: #94A3B8; }
</style>

<div class="bonus-competition-card" id="sultan-bonus-widget">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div style="font-size: 38px; filter: drop-shadow(0 4px 10px rgba(220, 38, 38, 0.4));">
                🎁
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-danger text-white fw-bold rounded-pill px-3 py-1" style="font-size: 11px; letter-spacing: 0.5px; border: 1px solid #FCD34D;">🇮🇩 EVENT KEMERDEKAAN LOEWIX</span>
                    <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-1" style="font-size: 11px;">TARGET: RP 200.000.000,- / BULAN</span>
                </div>
                <h4 class="mb-0 fw-bold text-dark mt-1" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.4px;">
                    Kompetisi Bonus Sales Sultan Loewix 🔥
                </h4>
            </div>
        </div>
        <div class="text-end">
            <div class="cash-prize-badge">
                💰 HADIAH BONUS RP 4.000.000,-
            </div>
        </div>
    </div>

    <!-- Month Filter Bar (Bulan 8 - 10) -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 p-2 bg-light rounded-pill border gap-2">
        <div class="d-flex align-items-center gap-2 px-2">
            <span class="fw-bold text-dark" style="font-size: 12.5px;">📅 Filter Periode:</span>
            <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold" style="font-size: 11px; border: 1px solid #FCD34D;"><?= $label_periode ?></span>
        </div>
        <div class="d-flex align-items-center gap-1.5 flex-wrap">
            <a href="?periode_bulan=8" class="btn btn-sm <?= ($selected_bulan==='8')?'btn-danger fw-bold text-white shadow-sm':'btn-outline-secondary' ?> rounded-pill px-3 py-1" style="font-size: 11.5px;">
                📅 Agt (Bulan 8)
            </a>
            <a href="?periode_bulan=9" class="btn btn-sm <?= ($selected_bulan==='9')?'btn-warning fw-bold text-dark shadow-sm':'btn-outline-secondary' ?> rounded-pill px-3 py-1" style="font-size: 11.5px;">
                📅 Sep (Bulan 9)
            </a>
            <a href="?periode_bulan=10" class="btn btn-sm <?= ($selected_bulan==='10')?'btn-success fw-bold text-white shadow-sm':'btn-outline-secondary' ?> rounded-pill px-3 py-1" style="font-size: 11.5px;">
                📅 Okt (Bulan 10)
            </a>
            <a href="?periode_bulan=8-10" class="btn btn-sm <?= ($selected_bulan==='8-10')?'btn-primary fw-bold text-white shadow-sm':'btn-outline-secondary' ?> rounded-pill px-3 py-1" style="font-size: 11.5px;">
                🏆 Total (Bulan 8-10)
            </a>
        </div>
    </div>

    <!-- Combined Leaderboard Box -->
    <div class="row g-4">
        <div class="col-12">
            <div class="bonus-kat-box" style="padding: 28px 32px;">
                <div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div>
                            <h5 class="fw-bold text-white mb-1 d-flex align-items-center gap-2" style="font-size: 17px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                🏆 Leaderboard Perolehan Sultan Sales (Gabungan Cust Baru & Reaktivasi)
                            </h5>
                            <small class="text-white-50" style="font-size: 12px;">Total pencapaian omset invoice & jumlah customer belanja per <?= $label_periode ?> (Target Rp 200 Juta Omset Invoice).</small>
                        </div>
                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning rounded-pill px-3 py-1.5" style="font-size: 12px; font-weight: 800;">
                            💰 TOTAL BONUS RP 4.000.000,-
                        </span>
                    </div>

                    <!-- Realtime Combined Leaderboard List -->
                    <div class="rank-list-holder mt-3">
                        <?php if (!empty($list_combined)): ?>
                            <?php foreach ($list_combined as $i => $item): ?>
                                <?php
                                $rClass = 'normal';
                                $rIcon = '#' . ($i + 1);
                                if ($i === 0) { $rClass = 'gold'; $rIcon = '🥇'; }
                                elseif ($i === 1) { $rClass = 'silver'; $rIcon = '🥈'; }
                                elseif ($i === 2) { $rClass = 'bronze'; $rIcon = '🥉'; }
                                $omset_comb = (float)$item['total_omset_combined'];
                                $pct_target_comb = min(100, round(($omset_comb / $target_omset_per_bulan) * 100, 1));
                                ?>
                                <div class="rank-row-item" style="cursor: pointer;" onclick="openBonusSalesDetail(<?php echo $item['sales_id']; ?>, 'all')" title="Klik untuk lihat rincian detail <?= htmlspecialchars($item['nama_sales']); ?>">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-1.5 gap-2">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rank-pill <?php echo $rClass; ?>"><?php echo $rIcon; ?></div>
                                            <div>
                                                <div class="fw-bold text-white d-flex align-items-center gap-2" style="font-size: 14.5px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                                    <span><?php echo htmlspecialchars($item['nama_sales']); ?></span>
                                                    <span class="badge bg-white bg-opacity-10 text-info fw-normal" style="font-size: 10px; padding: 3px 8px;">🔍 Detail Rincian</span>
                                                </div>
                                                <small class="text-white-50" style="font-size: 11.5px;">
                                                    <strong class="text-warning"><?php echo $item['total_cust_belanja']; ?> Cust Belanja</strong> 
                                                    (🚀 <?php echo $item['total_cust_baru']; ?> Cust Baru + 🔥 <?php echo $item['total_cust_reaktivasi']; ?> Reaktivasi)
                                                </small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-emerald-400 font-monospace" style="font-size: 15px; color: #34D399;">
                                                Rp <?php echo number_format($omset_comb, 0, ',', '.'); ?>
                                            </div>
                                            <small class="text-warning fw-semibold" style="font-size: 11px;"><?php echo $pct_target_comb; ?>% dari Target Rp 200 Juta</small>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 7px; background: rgba(255, 255, 255, 0.1); border-radius: 10px;">
                                        <div class="progress-bar bg-success bg-gradient" role="progressbar" style="width: <?php echo max($pct_target_comb, 2); ?>%; border-radius: 10px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-white-50" style="font-size: 13px;">
                                🚀 Belum ada data pencapaian omset invoice sales per <?= $label_periode ?>.<br>
                                <small>Ayo tingkatkan omset invoice customer baru & reaktivasi untuk memenangkan Bonus Sultan Rp 4 Juta!</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top border-secondary border-opacity-30 d-flex flex-wrap justify-content-between align-items-center" style="font-size: 12px; color: #94A3B8;">
                    <span>📌 Syarat: Pencapaian Omset Invoice (Cust Baru + Reaktivasi) per <?= $label_periode ?></span>
                    <span class="text-warning fw-bold">Pemenang Utama: Top 1 Sales Sultan Loewix 🏆</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Sales Bonus Sultan -->
<div class="modal fade" id="bonusSalesDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 22px; overflow: hidden;" id="bonusSalesDetailModalBody">
            <div class="p-5 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="mt-2 text-muted fw-bold">Memuat rincian detail sales...</div>
            </div>
        </div>
    </div>
</div>

<script>
function openBonusSalesDetail(salesId, kat) {
    const modalEl = document.getElementById('bonusSalesDetailModal');
    const modalBody = document.getElementById('bonusSalesDetailModalBody');
    const selectedBulan = '<?= $selected_bulan ?>';

    modalBody.innerHTML = `
        <div class="p-5 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2 text-muted fw-bold">Memuat rincian detail sales...</div>
        </div>
    `;

    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();

    fetch(`ajax_bonus_sales_detail.php?sales_id=${salesId}&kat=${kat}&periode_bulan=${selectedBulan}`)
        .then(res => res.text())
        .then(html => {
            modalBody.innerHTML = html;
        })
        .catch(err => {
            modalBody.innerHTML = `
                <div class="p-4 text-center text-danger">
                    <div class="fs-2 mb-2">⚠️</div>
                    <div class="fw-bold">Gagal memuat detail sales.</div>
                    <small class="text-muted">Silakan coba beberapa saat lagi.</small>
                </div>
            `;
        });
}
</script>
