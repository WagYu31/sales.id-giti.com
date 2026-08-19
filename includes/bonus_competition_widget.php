<?php
/**
 * WIDGET TRACKER REALTIME KOMPETISI BONUS SULTAN LOEWIX - TEMA KEMERDEKAAN 🇮🇩 3D EDITION
 * HADIAH UTAMA: RP 4.000.000,- (4 JUTA RUPIAH)
 * TARGET OMSET: RP 200.000.000,- (200 JUTA RUPIAH) PER BULAN PER SALES
 */

$target_omset_per_bulan = 200000000; // Rp 200.000.000,-

$target_omset_per_bulan = 200000000; // Rp 200.000.000,-

// Single Month Event: Bulan 8 (Agustus 2026)
$selected_bulan = '8';
$start_date = '2026-08-01';
$end_date = '2026-08-31';
$label_periode = 'Bulan 8 (Agustus 2026)';

// Fetch Combined Sales Leaderboard (Kat A + Kat B Combined)
// 1. Cust Baru: Input in program period (tgl_input >= 2026-08-01)
// 2. Cust Lama Reaktivasi: Input <= 2026-05-31 & NO invoice in June/July 2026
$sql_combined = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT CASE WHEN (c.tgl_input IS NOT NULL AND c.tgl_input >= '2026-08-01') THEN c.id END) AS total_cust_baru,
        COUNT(DISTINCT CASE WHEN ((c.tgl_input IS NULL OR c.tgl_input <= '2026-05-31')
                 AND NOT EXISTS (
                     SELECT 1 FROM follow_ups fu_mid 
                     WHERE fu_mid.customer_id = c.id 
                       AND fu_mid.deleted_at IS NULL 
                       AND fu_mid.no_inv IS NOT NULL AND fu_mid.no_inv != '' 
                       AND fu_mid.tgl_follow_up >= '2026-06-01 00:00:00' 
                       AND fu_mid.tgl_follow_up <= '2026-07-31 23:59:59'
                 )) THEN fu.customer_id END) AS total_cust_reaktivasi,
        COUNT(DISTINCT fu.customer_id) AS total_cust_belanja,
        COALESCE(SUM(fu.nominal_invoice), 0) AS total_omset_combined
    FROM sales s
    JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL
    JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE fu.tgl_follow_up >= '{$start_date} 00:00:00' AND fu.tgl_follow_up <= '{$end_date} 23:59:59'
      AND fu.no_inv IS NOT NULL AND fu.no_inv != ''
      AND (s.role = 'sales' OR s.role = 'superadmin')
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

<!-- 3D SULTAN BONUS COMPETITION TRACKER WIDGET - TEMA KEMERDEKAAN LOEWIX 🇮🇩 -->
<style>
@keyframes pulseMerahPutih {
    0%, 100% { box-shadow: 0 12px 36px -6px rgba(220, 38, 38, 0.3), 0 0 0 1px rgba(239, 68, 68, 0.4); }
    50% { box-shadow: 0 20px 48px -4px rgba(220, 38, 38, 0.5), 0 0 0 3px rgba(251, 191, 36, 0.6); }
}

@keyframes floatEmblem {
    0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
    50% { transform: translateY(-6px) rotate(3deg) scale(1.05); }
}

@keyframes ribbonShine {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.bonus-competition-card-3d {
    background: linear-gradient(145deg, #FFFFFF 0%, #F8FAFC 100%);
    border-radius: 28px;
    padding: 34px 38px;
    margin-bottom: 36px;
    position: relative;
    box-shadow: 0 24px 60px -14px rgba(15, 23, 42, 0.12), 0 6px 20px rgba(220, 38, 38, 0.05);
    border: 2px solid rgba(226, 232, 240, 0.95);
    overflow: hidden;
}

.bonus-competition-card-3d::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 6px;
    background: linear-gradient(90deg, #DC2626 0%, #FFFFFF 35%, #DC2626 70%, #F59E0B 100%);
    background-size: 200% 100%;
    animation: ribbonShine 4s linear infinite;
}

.cash-prize-badge-3d {
    background: linear-gradient(135deg, #B91C1C 0%, #DC2626 50%, #991B1B 100%);
    color: #FFFFFF;
    font-weight: 900;
    padding: 10px 22px;
    border-radius: 50px;
    font-size: 13px;
    letter-spacing: 0.6px;
    border: 2px solid #FCD34D;
    box-shadow: 0 10px 25px -4px rgba(220, 38, 38, 0.5), inset 0 2px 2px rgba(255, 255, 255, 0.4);
    animation: floatEmblem 3.5s ease-in-out infinite;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.merah-putih-badge {
    background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
    color: #FFFFFF;
    font-weight: 800;
    border-radius: 50px;
    padding: 4px 14px;
    font-size: 11px;
    letter-spacing: 0.5px;
    border: 1.5px solid #FCD34D;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.bonus-kat-box-3d {
    background: linear-gradient(145deg, #0F172A 0%, #1E293B 60%, #450A0A 100%);
    border-radius: 24px;
    padding: 28px 34px;
    position: relative;
    box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.15), 0 16px 36px -8px rgba(15, 23, 42, 0.35);
    border: 1.5px solid rgba(239, 68, 68, 0.25);
}

.rank-row-item-3d {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 16px;
    padding: 14px 20px;
    margin-bottom: 12px;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    backdrop-filter: blur(8px);
}

.rank-row-item-3d:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(251, 191, 36, 0.6);
    transform: translateY(-3px) scale(1.008);
    box-shadow: 0 12px 28px -6px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(245, 158, 11, 0.4);
}

.rank-pill-3d {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 900; font-size: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.25);
    flex-shrink: 0;
}

.rank-pill-3d.gold {
    background: radial-gradient(circle at 30% 30%, #FDE047, #F59E0B 60%, #B45309 100%);
    color: #78350F;
    border: 1.5px solid #FEF08A;
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.5), inset 0 2px 3px rgba(255, 255, 255, 0.6);
}

.rank-pill-3d.silver {
    background: radial-gradient(circle at 30% 30%, #F8FAFC, #94A3B8 60%, #475569 100%);
    color: #0F172A;
    border: 1.5px solid #FFFFFF;
    box-shadow: 0 6px 14px rgba(148, 163, 184, 0.4);
}

.rank-pill-3d.bronze {
    background: radial-gradient(circle at 30% 30%, #FDBA74, #D97706 60%, #7C2D12 100%);
    color: #FFF;
    border: 1.5px solid #FFEDD5;
    box-shadow: 0 6px 14px rgba(217, 119, 6, 0.4);
}

.rank-pill-3d.normal {
    background: rgba(255, 255, 255, 0.1);
    color: #94A3B8;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.detail-btn-3d {
    background: linear-gradient(135deg, #0EA5E9 0%, #0284C7 100%);
    color: #FFFFFF !important;
    font-weight: 700;
    font-size: 10px;
    padding: 3px 10px;
    border-radius: 50px;
    box-shadow: 0 3px 8px rgba(14, 165, 233, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.2s ease;
}

.detail-btn-3d:hover {
    transform: scale(1.08);
    box-shadow: 0 5px 12px rgba(14, 165, 233, 0.5);
}
</style>

<div class="bonus-competition-card-3d" id="sultan-bonus-widget">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div style="font-size: 42px; filter: drop-shadow(0 6px 12px rgba(220, 38, 38, 0.4)); animation: floatEmblem 4s ease-in-out infinite;">
                🏆
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="merah-putih-badge">🇮🇩 SPESIAL EVENT KEMERDEKAAN LOEWIX</span>
                    <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-1" style="font-size: 11px; border: 1px solid #FCD34D;">TARGET: RP 200.000.000,- / BULAN</span>
                </div>
                <h3 class="mb-0 fw-extrabold text-dark mt-1" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.5px;">
                    Kompetisi Bonus Sales Sultan Loewix 🔥
                </h3>
            </div>
        </div>
        <div class="text-end">
            <div class="cash-prize-badge-3d">
                <span>💰</span> HADIAH UTAMA RP 3.000.000,-
            </div>
        </div>
    </div>

    <!-- Single Month Info Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 p-3 bg-light rounded-pill border gap-2 shadow-sm">
        <div class="d-flex align-items-center gap-2 px-2">
            <span class="fw-bold text-dark" style="font-size: 13px;">📅 Periode Event Sultan:</span>
            <span class="badge bg-danger text-white rounded-pill px-3 py-1.5 fw-bold" style="font-size: 11.5px; border: 1px solid #FCD34D;"><?= $label_periode ?></span>
        </div>
        <div class="px-2">
            <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-1.5" style="font-size: 11.5px;">🎯 Durasi: 1 Bulan Penuh (Agustus 2026)</span>
        </div>
    </div>

    <!-- Combined 3D Leaderboard Box -->
    <div class="row g-4">
        <div class="col-12">
            <div class="bonus-kat-box-3d">
                <div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2 border-bottom border-secondary border-opacity-30 pb-3">
                        <div>
                            <h5 class="fw-bold text-white mb-1 d-flex align-items-center gap-2" style="font-size: 18px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                🏆 1 BULAN DENGAN PENJUALAN TERBAIK
                            </h5>
                            <small class="text-white-50" style="font-size: 12px;">Akumulasi omset invoice & jumlah mitra aktif per <?= $label_periode ?> (Target Rp 200 Juta Omset Invoice).</small>
                        </div>
                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning rounded-pill px-3 py-1.5" style="font-size: 12px; font-weight: 800; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);">
                            💰 TOTAL HADIAH RP 3.000.000,-
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
                                <div class="rank-row-item-3d" style="cursor: pointer;" onclick="openBonusSalesDetail(<?php echo $item['sales_id']; ?>, 'all')" title="Klik untuk lihat rincian detail <?= htmlspecialchars($item['nama_sales']); ?>">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rank-pill-3d <?php echo $rClass; ?>"><?php echo $rIcon; ?></div>
                                            <div>
                                                <div class="fw-bold text-white d-flex align-items-center gap-2" style="font-size: 15px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                                    <span><?php echo htmlspecialchars($item['nama_sales']); ?></span>
                                                    <span class="detail-btn-3d">🔍 Detail Rincian</span>
                                                </div>
                                                <small class="text-white-50" style="font-size: 11.5px;">
                                                    <strong class="text-warning"><?php echo $item['total_cust_belanja']; ?> Mitra Transaksi</strong> 
                                                    (🚀 <?php echo $item['total_cust_baru']; ?> Baru + 🔥 <?php echo $item['total_cust_reaktivasi']; ?> Reaktivasi)
                                                </small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-extrabold font-monospace" style="font-size: 16px; color: #34D399; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                                                Rp <?php echo number_format($omset_comb, 0, ',', '.'); ?>
                                            </div>
                                            <small class="text-warning fw-semibold" style="font-size: 11px;"><?php echo $pct_target_comb; ?>% dari Target Rp 200 Juta</small>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 8px; background: rgba(255, 255, 255, 0.12); border-radius: 10px; overflow: hidden;">
                                        <div class="progress-bar bg-success bg-gradient" role="progressbar" style="width: <?php echo max($pct_target_comb, 2); ?>%; border-radius: 10px; box-shadow: 0 0 10px rgba(52, 211, 153, 0.6);"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-white-50" style="font-size: 13px;">
                                🚀 Belum ada data pencapaian omset invoice sales per <?= $label_periode ?>.<br>
                                <small>Ayo tingkatkan omset invoice customer baru & reaktivasi untuk memenangkan Bonus Sultan Rp 3 Juta!</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top border-secondary border-opacity-30 d-flex flex-wrap justify-content-between align-items-center" style="font-size: 12px; color: #94A3B8;">
                    <span>📌 Syarat: Pencapaian Omset Invoice (Cust Baru + Reaktivasi) per <?= $label_periode ?></span>
                    <span class="text-warning fw-bold">Pemenang Utama: Top 1 Sales Sultan Loewix 🏆</span>
                </div>
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
            <div class="spinner-border text-danger" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3 text-muted fw-bold fs-6">Memuat rincian detail sales (Tema Kemerdekaan 🇮🇩)...</div>
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
                    <div class="fw-bold">Gagal memuat rincian detail.</div>
                    <small>${err.message}</small>
                </div>
            `;
        });
}

function switchModalBulanFilter(salesId, newBulan) {
    const modalBody = document.getElementById('bonusSalesDetailModalBody');
    modalBody.style.opacity = '0.5';

    fetch(`ajax_bonus_sales_detail.php?sales_id=${salesId}&kat=all&periode_bulan=${newBulan}`)
        .then(res => res.text())
        .then(html => {
            modalBody.innerHTML = html;
            modalBody.style.opacity = '1';
        })
        .catch(err => {
            modalBody.style.opacity = '1';
            alert('Gagal memfilter data: ' + err.message);
        });
}
</script>

<!-- MODAL CONTAINER FOR SALES DETAIL -->
<div class="modal fade" id="bonusSalesDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1150px; width: 95vw;">
        <div class="modal-content border-0 shadow-lg" id="bonusSalesDetailModalBody" style="border-radius: 24px; overflow: hidden;">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>
