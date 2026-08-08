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
    $start_periode = '2026-09-01 00:00:00';
    $end_periode = '2026-09-30 23:59:59';
    $label_periode = 'Bulan 9 (September 2026)';
    $syarat_kat_a = 'Cust Baru per Sep 2026';
    $syarat_kat_b = 'Reaktivasi Cust Lama Sep 2026';
} else if ($selected_bulan === '10') {
    $start_periode = '2026-10-01 00:00:00';
    $end_periode = '2026-10-31 23:59:59';
    $label_periode = 'Bulan 10 (Oktober 2026)';
    $syarat_kat_a = 'Cust Baru per Okt 2026';
    $syarat_kat_b = 'Reaktivasi Cust Lama Okt 2026';
} else if ($selected_bulan === '8-10' || $selected_bulan === 'all') {
    $selected_bulan = '8-10';
    $start_periode = '2026-08-01 00:00:00';
    $end_periode = '2026-10-31 23:59:59';
    $label_periode = 'Periode Bulan 8 - 10 (Agt - Okt 2026)';
    $syarat_kat_a = 'Cust Baru Periode Agt - Okt 2026';
    $syarat_kat_b = 'Reaktivasi Cust Lama Agt - Okt 2026';
} else {
    $selected_bulan = '8';
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
        COALESCE(SUM(CASE WHEN fu.tgl_follow_up >= '{$start_periode}' AND fu.tgl_follow_up <= '{$end_periode}' AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.nominal_invoice ELSE 0 END), 0) AS total_omset_baru
    FROM sales s
    JOIN customers c ON c.sales_id = s.id AND c.deleted_at IS NULL
    LEFT JOIN follow_ups fu ON fu.customer_id = c.id AND fu.deleted_at IS NULL
    WHERE c.tgl_input >= '{$start_periode}' AND c.tgl_input <= '{$end_periode}'
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

// 2. Fetch Top Sales Kategori B: Reaktivasi Customer Lama Belanja Kembali & Omset Invoice (Mulai 1 Agt 2026)
$sql_kat_b = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT fu.customer_id) AS total_customer_reaktivasi,
        COALESCE(SUM(fu.nominal_invoice), 0) AS total_omset_reaktivasi
    FROM sales s
    JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL
    JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE fu.tgl_follow_up >= '{$start_periode}' AND fu.tgl_follow_up <= '{$end_periode}'
      AND fu.no_inv IS NOT NULL AND fu.no_inv != ''
      AND (c.tgl_input < '{$start_periode}' OR c.tgl_input IS NULL)
      AND (s.role = 'sales' OR s.role = 'superadmin')
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_omset_reaktivasi DESC, total_customer_reaktivasi DESC
    LIMIT 5
";
$res_kat_b = $conn->query($sql_kat_b);
$list_kat_b = [];
if ($res_kat_b) {
    while ($r = $res_kat_b->fetch_assoc()) {
        $list_kat_b[] = $r;
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
    background: linear-gradient(90deg, #DC2626 0%, #F59E0B 50%, #DC2626 100%);
    box-shadow: 0 2px 10px rgba(220, 38, 38, 0.4);
}

.bonus-kat-box {
    background: #0F172A;
    border: 2px solid #DC2626;
    border-radius: 22px;
    padding: 24px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.15);
    color: #FFFFFF;
}

.bonus-kat-box:hover {
    transform: translateY(-5px);
    border-color: #F59E0B;
    box-shadow: 0 20px 40px rgba(220, 38, 38, 0.25);
}

.cash-prize-badge {
    background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
    color: #FFFFFF;
    font-size: 13px;
    font-weight: 800;
    padding: 7px 18px;
    border-radius: 20px;
    border: 1.5px solid #FCD34D;
    box-shadow: 0 6px 18px rgba(220, 38, 38, 0.35);
    animation: floatCashBadge 3s infinite ease-in-out;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.rank-row-item {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 12px 16px;
    margin-bottom: 10px;
    transition: all 0.2s ease;
}

.rank-row-item:hover {
    background: rgba(30, 41, 59, 0.95);
    border-color: rgba(245, 158, 11, 0.5);
}

.rank-pill {
    width: 28px; height: 28px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
    flex-shrink: 0;
}
.rank-pill.gold { background: linear-gradient(135deg, #F59E0B, #D97706); color: #FFF; }
.rank-pill.silver { background: linear-gradient(135deg, #94A3B8, #64748B); color: #FFF; }
.rank-pill.bronze { background: linear-gradient(135deg, #D97706, #B45309); color: #FFF; }
.rank-pill.normal { background: rgba(255, 255, 255, 0.1); color: #94A3B8; }
</style>

<div class="bonus-competition-card">
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
                🇮🇩 HADIAH RP 2.000.000,- / KATEGORI
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

    <!-- 2 Main Categories Grid -->
    <div class="row g-4 align-items-stretch">
        <!-- KATEGORI A: Customer Baru Terbanyak & Nominal Invoice -->
        <div class="col-lg-6 col-12">
            <div class="bonus-kat-box">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-white mb-0 d-flex align-items-center gap-2" style="font-size: 15.5px;">
                            🚀 KATEGORI A: Customer Baru Terbanyak
                        </h6>
                        <span class="badge bg-success bg-opacity-20 text-success border border-success rounded-pill px-2.5 py-1" style="font-size: 11px; font-weight: 800;">
                            💰 BONUS RP 2.000.000,-
                        </span>
                    </div>
                    <p class="text-white-50 mb-3" style="font-size: 12px;">Mencari Customer Baru terbanyak & pencapaian omset invoice (Target Rp 200 Juta).</p>

                    <!-- Realtime Leaderboard List Category A -->
                    <div class="rank-list-holder">
                        <?php if (!empty($list_kat_a)): ?>
                            <?php foreach ($list_kat_a as $i => $item): ?>
                                <?php
                                $rClass = 'normal';
                                $rIcon = '#' . ($i + 1);
                                if ($i === 0) { $rClass = 'gold'; $rIcon = '🥇'; }
                                elseif ($i === 1) { $rClass = 'silver'; $rIcon = '🥈'; }
                                elseif ($i === 2) { $rClass = 'bronze'; $rIcon = '🥉'; }
                                $omset_a = (float)$item['total_omset_baru'];
                                $pct_target_a = min(100, round(($omset_a / $target_omset_per_bulan) * 100, 1));
                                ?>
                                <div class="rank-row-item">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rank-pill <?php echo $rClass; ?>"><?php echo $rIcon; ?></div>
                                            <div>
                                                <div class="fw-bold text-white" style="font-size: 13.5px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                                    <?php echo htmlspecialchars($item['nama_sales']); ?>
                                                </div>
                                                <small class="text-white-50" style="font-size: 11px;"><?php echo $item['total_customer_baru']; ?> Cust Baru</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-emerald-400 font-monospace" style="font-size: 13.5px; color: #34D399;">
                                                Rp <?php echo number_format($omset_a, 0, ',', '.'); ?>
                                            </div>
                                            <small class="text-warning fw-semibold" style="font-size: 10.5px;"><?php echo $pct_target_a; ?>% dari Rp 200 Juta</small>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 5px; background: rgba(255, 255, 255, 0.1); border-radius: 10px;">
                                        <div class="progress-bar bg-success bg-gradient" role="progressbar" style="width: <?php echo max($pct_target_a, 2); ?>%; border-radius: 10px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-white-50" style="font-size: 13px;">
                                🚀 Belum ada data customer baru per 1 Agustus 2026.<br>
                                <small>Ayo follow up customer baru untuk mendapatkan omset & bonus Rp 2 Juta!</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top border-secondary border-opacity-30 d-flex justify-content-between align-items-center" style="font-size: 11.5px; color: #94A3B8;">
                    <span>📌 Syarat: Cust Baru per 1 Agt 2026</span>
                    <span class="text-warning fw-bold">Pemenang: Top 1 Sales</span>
                </div>
            </div>
        </div>

        <!-- KATEGORI B: Reaktivasi Customer Lama Belanja Kembali -->
        <div class="col-lg-6 col-12">
            <div class="bonus-kat-box">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-white mb-0 d-flex align-items-center gap-2" style="font-size: 15.5px;">
                            🔥 KATEGORI B: Reaktivasi Customer Lama
                        </h6>
                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning rounded-pill px-2.5 py-1" style="font-size: 11px; font-weight: 800;">
                            💰 BONUS RP 2.000.000,-
                        </span>
                    </div>
                    <p class="text-white-50 mb-3" style="font-size: 12px;">Membangunkan Customer Lama untuk Belanja Kembali (Target Rp 200 Juta Omset Invoice).</p>

                    <!-- Realtime Leaderboard List Category B -->
                    <div class="rank-list-holder">
                        <?php if (!empty($list_kat_b)): ?>
                            <?php foreach ($list_kat_b as $i => $item): ?>
                                <?php
                                $rClass = 'normal';
                                $rIcon = '#' . ($i + 1);
                                if ($i === 0) { $rClass = 'gold'; $rIcon = '🥇'; }
                                elseif ($i === 1) { $rClass = 'silver'; $rIcon = '🥈'; }
                                elseif ($i === 2) { $rClass = 'bronze'; $rIcon = '🥉'; }
                                $omset_b = (float)$item['total_omset_reaktivasi'];
                                $pct_target_b = min(100, round(($omset_b / $target_omset_per_bulan) * 100, 1));
                                ?>
                                <div class="rank-row-item">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rank-pill <?php echo $rClass; ?>"><?php echo $rIcon; ?></div>
                                            <div>
                                                <div class="fw-bold text-white" style="font-size: 13.5px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                                    <?php echo htmlspecialchars($item['nama_sales']); ?>
                                                </div>
                                                <small class="text-white-50" style="font-size: 11px;"><?php echo $item['total_customer_reaktivasi']; ?> Cust Belanja</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold font-monospace" style="font-size: 13.5px; color: #FCD34D;">
                                                Rp <?php echo number_format($omset_b, 0, ',', '.'); ?>
                                            </div>
                                            <small class="text-warning fw-semibold" style="font-size: 10.5px;"><?php echo $pct_target_b; ?>% dari Rp 200 Juta</small>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 5px; background: rgba(255, 255, 255, 0.1); border-radius: 10px;">
                                        <div class="progress-bar bg-warning bg-gradient" role="progressbar" style="width: <?php echo max($pct_target_b, 2); ?>%; border-radius: 10px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-white-50" style="font-size: 13px;">
                                🔥 Belum ada data reaktivasi customer lama per 1 Agustus 2026.<br>
                                <small>Ayo follow up customer lama agar reaktivasi & dapatkan omset serta bonus Rp 2 Juta!</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top border-secondary border-opacity-30 d-flex justify-content-between align-items-center" style="font-size: 11.5px; color: #94A3B8;">
                    <span>📌 Syarat: Reaktivasi Cust Lama per 1 Agt 2026</span>
                    <span class="text-warning fw-bold">Pemenang: Top 1 Sales</span>
                </div>
            </div>
        </div>
    </div>
</div>
