<?php
/**
 * GRAFIK RANKING & LEADERBOARD SALES WIDGET - ATHLETICS RUNNING TRACK / SIRKUIT LARI EDITION
 * PERIODE: MULAI 1 AGUSTUS 2026 (SEMUA DARI 0)
 * METRIK UTAMA: TOTAL NOMINAL OMSET INVOICE (RP) DENGAN TARGET RP 200 JUTA
 */

$start_periode_ranking = '2026-08-01 00:00:00';
$target_omset_finish = 200000000; // Rp 200.000.000,-

// Fetch Ranking Sales Data (Period Agt 2026 Start From 0)
$sql_ranking_all = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COALESCE(SUM(CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.nominal_invoice ELSE 0 END), 0) AS total_omset_invoice,
        COUNT(CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' THEN fu.id ELSE NULL END) AS total_fu,
        COUNT(DISTINCT CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' THEN fu.customer_id ELSE NULL END) AS total_customer_fu,
        COUNT(CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.id ELSE NULL END) AS total_inv_count
    FROM sales s
    LEFT JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL AND fu.tgl_follow_up >= '{$start_periode_ranking}'
    LEFT JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE s.role = 'sales' OR s.role = 'superadmin' OR fu.id IS NOT NULL
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_omset_invoice DESC, total_inv_count DESC, total_fu DESC
";

$res_ranking = $conn->query($sql_ranking_all);
$ranking_data = [];
if ($res_ranking) {
    while ($row = $res_ranking->fetch_assoc()) {
        $ranking_data[] = $row;
    }
}

$chart_labels = [];
$chart_omset_invoice = [];
$chart_total_fu = [];
$chart_customer_fu = [];

foreach ($ranking_data as $rd) {
    $chart_labels[] = $rd['nama_sales'];
    $chart_omset_invoice[] = (float)$rd['total_omset_invoice'];
    $chart_total_fu[] = (int)$rd['total_fu'];
    $chart_customer_fu[] = (int)$rd['total_customer_fu'];
}

$top1 = $ranking_data[0] ?? null;
$top2 = $ranking_data[1] ?? null;
$top3 = $ranking_data[2] ?? null;
$total_sales_count = count($ranking_data);
?>

<!-- 3D SPATIAL & ATHLETICS RUNNING TRACK / SIRKUIT LARI EDITION -->
<style>
@keyframes float3DMedal {
    0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
    50% { transform: translateY(-5px) rotate(4deg) scale(1.08); }
}

@keyframes pulseGlowGold {
    0%, 100% { box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.3); }
    50% { box-shadow: 0 18px 38px -4px rgba(245, 158, 11, 0.55); }
}

@keyframes pulseGlowSilver {
    0%, 100% { box-shadow: 0 10px 25px -5px rgba(148, 163, 184, 0.25); }
    50% { box-shadow: 0 16px 32px -4px rgba(148, 163, 184, 0.45); }
}

@keyframes pulseGlowBronze {
    0%, 100% { box-shadow: 0 10px 25px -5px rgba(217, 119, 6, 0.25); }
    50% { box-shadow: 0 16px 32px -4px rgba(217, 119, 6, 0.45); }
}

@keyframes borderShimmerGold {
    0%, 100% { border-color: #FCD34D; }
    50% { border-color: #F59E0B; }
}

@keyframes popMetricAnim {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); color: #2563EB; }
    100% { transform: scale(1); }
}

/* ATHLETICS RUNNING TRACK / SIRKUIT LARI 17 AGUSTUS KEMERDEKAAN RI 🇮🇩 STYLES */
.chart-scroll-wrapper {
    position: relative;
    max-height: 370px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-top: 28px;
    padding-right: 6px;
    background: linear-gradient(180deg, #881337 0%, #991B1B 35%, #B91C1C 70%, #7F1D1D 100%);
    border-radius: 22px;
    border: 3px solid #DC2626;
    outline: 2px solid #F59E0B;
    box-shadow: inset 0 10px 30px rgba(0, 0, 0, 0.6), 0 12px 30px rgba(220, 38, 38, 0.4);
}

.chart-scroll-wrapper::-webkit-scrollbar {
    width: 6px;
}
.chart-scroll-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.25);
    border-radius: 10px;
}
.chart-scroll-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #DC2626, #F59E0B);
    border-radius: 10px;
}

.track-bg-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    pointer-events: none;
    background-image: 
        repeating-linear-gradient(0deg, transparent, transparent 43px, rgba(255, 255, 255, 0.35) 44px),
        linear-gradient(90deg, rgba(255,255,255,0.08) 1px, transparent 1px);
    background-size: 100% 44px, 10% 100%;
    z-index: 1;
}

/* GARIS FINISH MERAH PUTIH 17 AGUSTUS 🇮🇩 */
.finish-line-banner {
    position: absolute;
    right: 12px;
    top: 10px;
    bottom: 10px;
    width: 24px;
    background: repeating-linear-gradient(
        45deg,
        #DC2626,
        #DC2626 8px,
        #FFFFFF 8px,
        #FFFFFF 16px
    );
    border-radius: 4px;
    box-shadow: 0 0 16px rgba(220, 38, 38, 0.9), 0 0 8px rgba(255, 255, 255, 0.9);
    z-index: 3;
    pointer-events: none;
}

.finish-badge-top {
    position: absolute;
    right: 4px;
    top: 6px;
    background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
    color: #FFFFFF;
    font-size: 10.5px;
    font-weight: 900;
    padding: 4px 10px;
    border-radius: 14px;
    border: 1.5px solid #FCD34D;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.6);
    z-index: 4;
}

.cctv-mascot-runner {
    position: absolute;
    z-index: 10;
    cursor: pointer;
    transition: left 1.2s cubic-bezier(0.16, 1, 0.3, 1), top 0.4s ease;
    filter: drop-shadow(0 6px 12px rgba(0,0,0,0.5));
}

.cctv-mascot-runner:hover {
    transform: scale(1.15);
}

.nailong-body-wrap {
    animation: nailongSmoothWaddle 0.55s infinite alternate ease-in-out;
}

.nailong-head-group {
    transform-origin: 23px 16px;
    animation: nailongHeadBob 0.55s infinite alternate ease-in-out;
}

.nailong-leg-left {
    transform-origin: 15px 42px;
    animation: nailongLegRunLeft 0.55s infinite alternate ease-in-out;
}

.nailong-leg-right {
    transform-origin: 29px 42px;
    animation: nailongLegRunRight 0.55s infinite alternate ease-in-out;
}

.nailong-arm-left {
    transform-origin: 12px 25px;
    animation: nailongArmRunLeft 0.55s infinite alternate ease-in-out;
}

.nailong-arm-right {
    transform-origin: 32px 25px;
    animation: nailongArmRunRight 0.55s infinite alternate ease-in-out;
}

.cctv-rec-dot {
    animation: recBlink 0.8s infinite alternate;
}

.nailong-speed-lines line {
    animation: speedLinePuff 0.4s infinite linear;
}

.nailong-rank-tag {
    position: absolute;
    left: 46px;
    top: 8px;
    font-size: 13.5px;
    font-weight: 800;
    white-space: nowrap;
    filter: drop-shadow(0 2px 5px rgba(0,0,0,0.25));
}

@keyframes nailongSmoothWaddle {
    0% { transform: translateY(0px) rotate(-4deg); }
    100% { transform: translateY(-4px) rotate(4deg); }
}

@keyframes nailongHeadBob {
    0% { transform: rotate(-3deg); }
    100% { transform: rotate(4deg); }
}

@keyframes nailongLegRunLeft {
    0% { transform: rotate(-35deg) scaleY(0.9); }
    100% { transform: rotate(35deg) scaleY(1.1); }
}

@keyframes nailongLegRunRight {
    0% { transform: rotate(35deg) scaleY(1.1); }
    100% { transform: rotate(-35deg) scaleY(0.9); }
}

@keyframes nailongArmRunLeft {
    0% { transform: rotate(-30deg); }
    100% { transform: rotate(30deg); }
}

@keyframes nailongArmRunRight {
    0% { transform: rotate(30deg); }
    100% { transform: rotate(-30deg); }
}

@keyframes speedLinePuff {
    0% { opacity: 1; transform: translateX(0); }
    100% { opacity: 0; transform: translateX(-8px); }
}

@keyframes recBlink {
    0% { opacity: 0.2; }
    100% { opacity: 1; }
}

.ranking-widget-card {
    background: #FFFFFF;
    border-radius: 26px;
    padding: 32px 36px;
    margin-bottom: 34px;
    position: relative;
    box-shadow: 0 22px 48px -12px rgba(15, 23, 42, 0.09), 0 4px 14px rgba(15, 23, 42, 0.02);
    border: 1.5px solid rgba(226, 232, 240, 0.95) !important;
    overflow: hidden;
    transform-style: preserve-3d;
    perspective: 1200px;
}

.ranking-widget-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4.5px;
    background: linear-gradient(90deg, #F59E0B 0%, #10B981 50%, #2563EB 100%);
    box-shadow: 0 2px 12px rgba(245, 158, 11, 0.4);
}

.podium-card {
    border-radius: 22px;
    padding: 22px 24px;
    position: relative;
    overflow: hidden;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1.5px solid #E2E8F0;
    background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%);
    transform-style: preserve-3d;
    perspective: 800px;
}

.podium-card:hover {
    transform: translateY(-6px) rotateX(3deg) rotateY(-2deg) scale(1.015);
    box-shadow: 0 22px 42px -8px rgba(15, 23, 42, 0.14), 0 8px 18px rgba(15, 23, 42, 0.06);
}

.podium-card.gold {
    animation: borderShimmerGold 3s infinite ease-in-out, pulseGlowGold 4s infinite ease-in-out;
    background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
}

.podium-card.silver {
    animation: pulseGlowSilver 4s infinite ease-in-out;
    background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
    border-color: #CBD5E1 !important;
}

.podium-card.bronze {
    animation: pulseGlowBronze 4s infinite ease-in-out;
    background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
    border-color: #FDBA74 !important;
}

.podium-rank-badge {
    width: 42px; height: 42px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 20px;
    color: #FFFFFF;
    box-shadow: 0 8px 18px rgba(0,0,0,0.18), inset 0 2px 4px rgba(255,255,255,0.4);
    animation: float3DMedal 3.5s ease-in-out infinite;
    flex-shrink: 0;
}

.podium-rank-badge.gold { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); }
.podium-rank-badge.silver { background: linear-gradient(135deg, #94A3B8 0%, #64748B 100%); }
.podium-rank-badge.bronze { background: linear-gradient(135deg, #D97706 0%, #B45309 100%); }

.metric-btn {
    border: 1px solid #CBD5E1;
    background: #F8FAFC;
    color: #64748B;
    font-weight: 700;
    font-size: 12px;
    padding: 7px 16px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

.metric-btn:active {
    transform: scale(0.95) translateY(1px);
}

.metric-btn.active, .metric-btn:hover {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #FFFFFF;
    border-color: #2563EB;
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.top-limit-btn {
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 14px;
    border: 1px solid #CBD5E1;
    background: #FFFFFF;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
}

.top-limit-btn:active {
    transform: scale(0.94);
}

.top-limit-btn.active, .top-limit-btn:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
}

.btn-full-leaderboard {
    background: #FFFFFF;
    color: #0F172A;
    border: 1.5px solid #CBD5E1;
    font-weight: 700;
    font-size: 12.5px;
    padding: 8px 20px;
    border-radius: 20px;
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}

.btn-full-leaderboard:active {
    transform: scale(0.96);
}

.btn-full-leaderboard:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.3);
}

.pop-metric {
    animation: popMetricAnim 0.4s ease-out;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="ranking-widget-card">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="podium-rank-badge gold" style="width: 50px; height: 50px; font-size: 26px;">
                🏆
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-danger text-white fw-bold rounded-pill px-3 py-1" style="font-size: 11px; letter-spacing: 0.5px; border: 1px solid #FCD34D;">🇮🇩 EDISI SPESIAL KEMERDEKAAN 17 AGUSTUS</span>
                    <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-1" style="font-size: 11px; font-weight: 800;">🏁 Sirkuit Balap Nailong Merah Putih 🇮🇩</span>
                </div>
                <h5 class="mb-0 fw-bold text-dark mt-1" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 19.5px; letter-spacing: -0.4px;">
                    Leaderboard & Grafik Ranking Sales Rep
                </h5>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="metric-btn active" id="btnMetricOmset" onclick="switchChartMetric('omset')">
                💵 Invoice Sales (Rp)
            </button>
            <button type="button" class="metric-btn" id="btnMetricTotal" onclick="switchChartMetric('total')">
                ⚡ Total Activity FU
            </button>
            <button type="button" class="metric-btn" id="btnMetricCustomer" onclick="switchChartMetric('customer')">
                👥 Customer di-FU
            </button>
            <button type="button" class="btn-full-leaderboard ms-1" data-bs-toggle="modal" data-bs-target="#fullLeaderboardModal">
                📋 Full Leaderboard (<?php echo $total_sales_count; ?> Sales)
            </button>
        </div>
    </div>

    <div class="row g-4 align-items-stretch">
        <!-- Left Side: Top 3 3D Spatial Podium Cards -->
        <div class="col-lg-5 col-12 d-flex flex-column gap-3">
            <!-- Top 1 Gold -->
            <?php if ($top1): ?>
            <?php $top1_omset = (float)$top1['total_omset_invoice']; ?>
            <div class="podium-card gold">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="podium-rank-badge gold">🥇</div>
                        <div>
                            <span class="badge bg-warning text-dark fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10.5px; letter-spacing: 0.5px;">JUARA 1</span>
                            <h6 class="mb-0 fw-bold text-dark mt-0.5" style="font-size: 16.5px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top1['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold text-warning metric-val font-monospace" id="podium1Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;">Rp <?php echo number_format($top1_omset, 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium1Lbl" style="font-size: 11px;">Omset Invoice (Agt 2026)</small>
                    </div>
                </div>
                <?php $pct_top1 = min(100, round(($top1_omset / $target_omset_finish) * 100, 1)); ?>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(245, 158, 11, 0.25);">
                    <div class="progress-bar bg-warning bg-gradient" role="progressbar" style="width: <?php echo max(5, $pct_top1); ?>%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top1['total_fu']; ?> Activity</span>
                    <span>👥 <?php echo $top1['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 2 Silver -->
            <?php if ($top2): ?>
            <?php $top2_omset = (float)$top2['total_omset_invoice']; ?>
            <div class="podium-card silver">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="podium-rank-badge silver">🥈</div>
                        <div>
                            <span class="badge bg-secondary text-white fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10.5px; letter-spacing: 0.5px;">JUARA 2</span>
                            <h6 class="mb-0 fw-bold text-dark mt-0.5" style="font-size: 16px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top2['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold text-secondary metric-val font-monospace" id="podium2Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;">Rp <?php echo number_format($top2_omset, 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium2Lbl" style="font-size: 11px;">Omset Invoice (Agt 2026)</small>
                    </div>
                </div>
                <?php $pct2 = min(100, round(($top2_omset / $target_omset_finish) * 100, 1)); ?>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(148, 163, 184, 0.25);">
                    <div class="progress-bar bg-secondary bg-gradient" role="progressbar" style="width: <?php echo max(5, $pct2); ?>%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top2['total_fu']; ?> Activity</span>
                    <span>👥 <?php echo $top2['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 3 Bronze -->
            <?php if ($top3): ?>
            <?php $top3_omset = (float)$top3['total_omset_invoice']; ?>
            <div class="podium-card bronze">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="podium-rank-badge bronze">🥉</div>
                        <div>
                            <span class="badge bg-danger bg-opacity-75 text-white fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10.5px; letter-spacing: 0.5px;">JUARA 3</span>
                            <h6 class="mb-0 fw-bold text-dark mt-0.5" style="font-size: 16px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top3['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold text-danger metric-val font-monospace" id="podium3Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;">Rp <?php echo number_format($top3_omset, 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium3Lbl" style="font-size: 11px;">Omset Invoice (Agt 2026)</small>
                    </div>
                </div>
                <?php $pct3 = min(100, round(($top3_omset / $target_omset_finish) * 100, 1)); ?>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(217, 119, 6, 0.25);">
                    <div class="progress-bar bg-warning bg-gradient" role="progressbar" style="width: <?php echo max(5, $pct3); ?>%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top3['total_fu']; ?> Activity</span>
                    <span>👥 <?php echo $top3['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Side: Athletics Running Track Circuit Background & Chart -->
        <div class="col-lg-7 col-12">
            <div class="p-3.5 bg-light rounded-4 border h-100 d-flex flex-column justify-content-between shadow-sm position-relative">
                <div class="d-flex justify-content-between align-items-center mb-2 px-2 flex-wrap gap-2">
                    <span class="fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size: 14px;" id="chartTitle">🏁 Sirkuit Lari Sales (Omset Invoice Rp - Target 200 Juta)</span>
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="text-muted fw-semibold" style="font-size: 11px;">Tampilkan:</span>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit(5, this)">Top 5</button>
                        <button type="button" class="top-limit-btn active" onclick="setTopLimit(10, this)">Top 10</button>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit(20, this)">Top 20</button>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit('all', this)">Semua (<?php echo $total_sales_count; ?>)</button>
                    </div>
                </div>

                <!-- Athletics Track Circuit Wrapper with Checkered Finish Line -->
                <div class="chart-scroll-wrapper" id="chartScrollWrapper">
                    <div class="track-bg-overlay"></div>
                    <div class="finish-line-banner"></div>
                    <div class="finish-badge-top">FINISH 🏁 17 AGUSTUS 🇮🇩</div>

                    <div style="position: relative; width: 100%; min-height: 320px;" id="chartCanvasContainer">
                        <canvas id="salesRankingChart"></canvas>
                        <!-- Interactive Animated CCTV Mascot Runners Overlay (Nailong 奶龙 Edition) -->
                        <div id="cctvMascotOverlayHolder"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Full Leaderboard -->
<div class="modal fade" id="fullLeaderboardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header bg-gradient text-white" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 24px 24px 0 0; padding: 20px 28px;">
                <div class="d-flex align-items-center gap-2.5">
                    <div style="font-size: 24px;">🏆</div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">Full Leaderboard Sales Representative</h5>
                        <small class="text-white-50">Periode: Mulai 1 Agustus 2026 (Semua Dari 0)</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3 text-secondary" style="font-size: 12px;">PERINGKAT</th>
                                <th class="px-3 py-3 text-secondary" style="font-size: 12px;">SALES REPRESENTATIVE</th>
                                <th class="px-3 py-3 text-secondary text-end" style="font-size: 12px;">OMSET INVOICE (RP)</th>
                                <th class="px-3 py-3 text-secondary text-end" style="font-size: 12px;">TOTAL ACTIVITY</th>
                                <th class="px-3 py-3 text-secondary text-end" style="font-size: 12px;">CUST DI-FU</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking_data as $idx => $s): ?>
                                <?php 
                                $rNum = $idx + 1;
                                $badgeCls = 'bg-light text-secondary border';
                                if ($rNum === 1) $badgeCls = 'bg-warning text-dark fw-bold';
                                elseif ($rNum === 2) $badgeCls = 'bg-secondary text-white fw-bold';
                                elseif ($rNum === 3) $badgeCls = 'bg-danger text-white fw-bold';
                                ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="badge <?php echo $badgeCls; ?> rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 12px;">
                                            <?php echo $rNum; ?>
                                        </span>
                                    </td>
                                    <td class="px-3">
                                        <div class="fw-bold text-dark" style="font-size: 14px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                            <?php echo htmlspecialchars($s['nama_sales']); ?>
                                        </div>
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-success" style="font-size: 14px;">
                                        Rp <?php echo number_format((float)$s['total_omset_invoice'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-dark" style="font-size: 14px;">
                                        <?php echo number_format($s['total_fu'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-secondary" style="font-size: 14px;">
                                        <?php echo number_format($s['total_customer_fu'], 0, ',', '.'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5 px-4 justify-content-between">
                <span class="text-muted" style="font-size: 12.5px;">Data diperbarui secara realtime dari database</span>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
let salesChartInstance = null;
let currentMetric = 'omset';
let currentTopLimit = 10;

const salesChartLabels = <?php echo json_encode($chart_labels); ?>;
const salesChartOmsetInvoice = <?php echo json_encode($chart_omset_invoice); ?>;
const salesChartTotalFU = <?php echo json_encode($chart_total_fu); ?>;
const salesChartCustomerFU = <?php echo json_encode($chart_customer_fu); ?>;

const top1Data = <?php echo json_encode($top1); ?>;
const top2Data = <?php echo json_encode($top2); ?>;
const top3Data = <?php echo json_encode($top3); ?>;

document.addEventListener("DOMContentLoaded", function() {
    renderScaledChart();
});

function getActiveDatasetValues() {
    if (currentMetric === 'omset') return { values: salesChartOmsetInvoice, label: "Omset Invoice (Rp)" };
    if (currentMetric === 'total') return { values: salesChartTotalFU, label: "Total Activity FU" };
    return { values: salesChartCustomerFU, label: "Customer di-FU" };
}

function formatRupiahDisplay(num) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
}

function renderScaledChart() {
    const { values, label } = getActiveDatasetValues();
    
    let limit = currentTopLimit === 'all' ? values.length : parseInt(currentTopLimit);
    let slicedLabels = salesChartLabels.slice(0, limit);
    let slicedValues = values.slice(0, limit);

    // Calculate dynamic height for canvas so bars and top Nailong head/speech bubble never get squished or clipped
    const container = document.getElementById('chartCanvasContainer');
    const barHeightPx = 44;
    const computedHeight = Math.max(320, slicedLabels.length * barHeightPx + 60);
    container.style.height = `${computedHeight}px`;

    const ctx = document.getElementById('salesRankingChart').getContext('2d');
    
    if (salesChartInstance) {
        salesChartInstance.destroy();
    }

    // Clear old Nailong overlay runners
    const holder = document.getElementById('cctvMascotOverlayHolder');
    if (holder) holder.innerHTML = '';

    // 3D Gold & Vibrant Track Bar Gradients
    const gradientGold = ctx.createLinearGradient(0, 0, 400, 0);
    gradientGold.addColorStop(0, '#F59E0B');
    gradientGold.addColorStop(1, '#FCD34D');

    const gradientBlue = ctx.createLinearGradient(0, 0, 400, 0);
    gradientBlue.addColorStop(0, '#3B82F6');
    gradientBlue.addColorStop(1, '#60A5FA');

    const gradientGreen = ctx.createLinearGradient(0, 0, 400, 0);
    gradientGreen.addColorStop(0, '#10B981');
    gradientGreen.addColorStop(1, '#34D399');

    const gradientSilver = ctx.createLinearGradient(0, 0, 400, 0);
    gradientSilver.addColorStop(0, '#94A3B8');
    gradientSilver.addColorStop(1, '#CBD5E1');

    const gradientBronze = ctx.createLinearGradient(0, 0, 400, 0);
    gradientBronze.addColorStop(0, '#F97316');
    gradientBronze.addColorStop(1, '#FB923C');

    // Compute dynamic max for scale so bars and runners span beautifully across the track
    const maxVal = Math.max(...slicedValues, 1);
    let scaleMax = Math.ceil(maxVal * 1.25);
    if (currentMetric === 'omset') {
        scaleMax = Math.max(maxVal * 1.25, 10000000); // Dynamic Rupiah scale
    }

    salesChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: slicedLabels,
            datasets: [{
                label: label,
                data: slicedValues,
                backgroundColor: [
                    gradientGold,
                    gradientSilver,
                    gradientBronze,
                    gradientBlue,
                    gradientGreen
                ],
                borderColor: [
                    '#D97706',
                    '#64748B',
                    '#C2410C',
                    '#1D4ED8',
                    '#047857'
                ],
                borderWidth: 1.5,
                borderRadius: 14,
                borderSkipped: false,
                barThickness: 18
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 55,
                    right: 65,
                    left: 10,
                    bottom: 10
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleFont: { family: 'Plus Jakarta Sans', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Plus Jakarta Sans', size: 12.5 },
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            let val = context.raw;
                            if (currentMetric === 'omset') {
                                return ` 💰 Omset Invoice: ${formatRupiahDisplay(val)}`;
                            }
                            return ` ${context.dataset.label}: ${val}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    min: 0,
                    max: scaleMax,
                    grid: { color: 'rgba(255, 255, 255, 0.12)', lineWidth: 1 },
                    ticks: { 
                        color: '#F8FAFC', 
                        font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                        callback: function(val) {
                            if (currentMetric === 'omset') {
                                if (val >= 1000000) return (val / 1000000) + ' Jt';
                                return val;
                            }
                            return val;
                        }
                    },
                    border: { display: false }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        color: '#FFFFFF',
                        font: { family: 'Plus Jakarta Sans', size: 12.5, weight: '700' }
                    },
                    border: { display: false }
                }
            },
            animation: {
                onComplete: function() {
                    positionMascotRunners(this);
                }
            }
        }
    });
}

function positionMascotRunners(chart) {
    const holder = document.getElementById('cctvMascotOverlayHolder');
    if (!holder) return;
    holder.innerHTML = '';

    const meta = chart.getDatasetMeta(0);
    const xAxis = chart.scales.x;
    const yAxis = chart.scales.y;

    meta.data.forEach((bar, index) => {
        const value = chart.data.datasets[0].data[index];
        const labelName = chart.data.labels[index];
        const barY = bar.y;
        const barX = bar.x;

        const runnerDiv = document.createElement('div');
        runnerDiv.className = 'cctv-mascot-runner';
        
        // 45px width offset so mascot sits right on the edge of the bar, with offset for 0 value runners
        let leftPos = Math.min(barX - 18, xAxis.right - 45);
        if (value === 0) {
            leftPos = xAxis.left + 5 + (index * 4); // Stagger zero runners
        }
        const topPos = barY - 32;

        runnerDiv.style.left = `${leftPos}px`;
        runnerDiv.style.top = `${topPos}px`;
        
        let medalBadge = '';
        if (index === 0) medalBadge = '🥇';
        else if (index === 1) medalBadge = '🥈';
        else if (index === 2) medalBadge = '🥉';

        let displayValStr = (currentMetric === 'omset') ? formatRupiahDisplay(value) : value;

        runnerDiv.onclick = function() {
            triggerNailongJoySpin(runnerDiv, labelName);
        };

        // Interactive CCTV Camera + Dragon Mascot SVG Character (Nailong 奶龙 Edition)
        runnerDiv.innerHTML = `
            <div style="position: relative;">
                ${medalBadge ? `<span class="nailong-rank-tag">${medalBadge}</span>` : ''}
                <div class="nailong-body-wrap">
                    <svg width="46" height="46" viewBox="0 0 46 46" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Speed Lines / Wind Effects -->
                        <g class="nailong-speed-lines" stroke="#FDE047" stroke-width="1.5" stroke-linecap="round">
                            <line x1="2" y1="18" x2="8" y2="18" />
                            <line x1="0" y1="26" x2="6" y2="26" />
                            <line x1="3" y1="34" x2="9" y2="34" />
                        </g>

                        <!-- Tail with Cute Spike -->
                        <path d="M 12 36 Q 4 40 8 44 Q 16 42 16 36 Z" fill="#FACC15" stroke="#CA8A04" stroke-width="1.2"/>

                        <!-- Left Back Arm -->
                        <path class="nailong-arm-left" d="M 14 26 C 10 28, 8 32, 12 33" stroke="#CA8A04" stroke-width="3" stroke-linecap="round"/>

                        <!-- Left Back Leg -->
                        <ellipse class="nailong-leg-left" cx="16" cy="41" rx="4" ry="3.5" fill="#EAB308" stroke="#CA8A04" stroke-width="1.2"/>

                        <!-- Dragon Chubby Body (Yellow Golden) -->
                        <path d="M 14 22 C 14 16, 32 16, 32 22 C 34 32, 30 42, 22 42 C 14 42, 12 32, 14 22 Z" fill="#FACC15" stroke="#CA8A04" stroke-width="1.5"/>

                        <!-- Cute White Belly Patch -->
                        <ellipse cx="23" cy="32" rx="6.5" ry="7.5" fill="#FEF9C3"/>

                        <!-- Right Front Leg -->
                        <ellipse class="nailong-leg-right" cx="28" cy="41" rx="4.5" ry="4" fill="#FACC15" stroke="#CA8A04" stroke-width="1.2"/>

                        <!-- Right Front Arm -->
                        <path class="nailong-arm-right" d="M 28 26 C 34 27, 36 30, 32 32" stroke="#CA8A04" stroke-width="3.2" stroke-linecap="round"/>

                        <!-- Head & Horns Group -->
                        <g class="nailong-head-group">
                            <!-- Cute Little Dragon Horns (Orange Amber) -->
                            <path d="M 18 10 Q 17 4 20 6 Q 21 10 20 12 Z" fill="#F97316"/>
                            <path d="M 26 10 Q 25 4 28 6 Q 29 10 28 12 Z" fill="#F97316"/>

                            <!-- Big Chubby Dragon Head -->
                            <circle cx="23" cy="16" r="11" fill="#FACC15" stroke="#CA8A04" stroke-width="1.5"/>

                            <!-- Big Derpy Kawaii Eyes -->
                            <circle cx="19" cy="15" r="3.2" fill="#000000"/>
                            <circle cx="27" cy="15" r="3.2" fill="#000000"/>
                            <circle cx="20" cy="14" r="1.1" fill="#FFFFFF"/>
                            <circle cx="28" cy="14" r="1.1" fill="#FFFFFF"/>

                            <!-- Rosy Blush Cheeks -->
                            <ellipse cx="15.5" cy="18" rx="2" ry="1.2" fill="#F87171" opacity="0.85"/>
                            <ellipse cx="30.5" cy="18" rx="2" ry="1.2" fill="#F87171" opacity="0.85"/>

                            <!-- Big Happy DERP Smile Mouth -->
                            <path d="M 19 19 Q 23 24 27 19 Z" fill="#991B1B" stroke="#7F1D1D" stroke-width="0.8"/>
                            <path d="M 21 20 Q 23 22 25 20 Z" fill="#F87171"/>

                            <!-- Ikat Kepala Merah Putih 17 Agustus 🇮🇩 -->
                            <rect x="13" y="7" width="20" height="2.2" fill="#DC2626" rx="1" />
                            <rect x="13" y="9.2" width="20" height="2.2" fill="#FFFFFF" rx="1" stroke="#CBD5E1" stroke-width="0.3" />
                            <rect x="21.5" y="6" width="3" height="6" fill="#DC2626" rx="0.5" />
                            <rect x="21.5" y="9" width="3" height="3" fill="#FFFFFF" rx="0.5" />
                        </g>

                        <!-- Waving Indonesian Flag 🇮🇩 in Hand -->
                        <line x1="33" y1="18" x2="33" y2="40" stroke="#78350F" stroke-width="1.6" stroke-linecap="round"/>
                        <rect x="33" y="18" width="11" height="4.5" fill="#DC2626" rx="0.5"/>
                        <rect x="33" y="22.5" width="11" height="4.5" fill="#FFFFFF" rx="0.5" stroke="#E2E8F0" stroke-width="0.3"/>

                        <!-- CCTV Security Headset Strap & Camera Lens Mount -->
                        <rect x="13" y="12" width="20" height="2.5" rx="1.2" fill="#1E293B"/>
                        <circle cx="33" cy="14" r="4" fill="#0F172A" stroke="#38BDF8" stroke-width="1"/>
                        <circle cx="33" cy="14" r="1.8" fill="#38BDF8"/>
                        <!-- CCTV Blinking Red REC Light -->
                        <circle class="cctv-rec-dot" cx="35" cy="11" r="1.2" fill="#EF4444"/>
                    </svg>
                </div>
            </div>
        `;

        holder.appendChild(runnerDiv);
    });
}

function triggerNailongJoySpin(element, salesName) {
    element.style.animation = 'none';
    element.offsetHeight; // trigger reflow
    element.style.animation = 'nailongJoySpin 0.7s cubic-bezier(0.34, 1.56, 0.64, 1)';
    
    spawnFlyingPartyProps(element);
    showMotivationSpeechBubble(element, salesName);
}

function spawnFlyingPartyProps(parentElement) {
    const props = ['🇮🇩', '📹', '💰', '🏆', '💵', '💎', '⭐', '🔥', '💖', '🎉', '⚡', '🇮🇩', '🏆', '💵', '💎', '⭐'];
    const container = document.body;
    const rect = parentElement.getBoundingClientRect();

    for (let i = 0; i < 16; i++) {
        const propEl = document.createElement('div');
        propEl.innerText = props[Math.floor(Math.random() * props.length)];
        
        const startX = rect.left + 20;
        const startY = rect.top + 10;
        
        const angle = Math.random() * Math.PI * 2;
        const distance = 80 + Math.random() * 120;
        const targetX = startX + Math.cos(angle) * distance;
        const targetY = startY + Math.sin(angle) * distance - 40;
        
        propEl.style.cssText = `
            position: fixed;
            left: ${startX}px;
            top: ${startY}px;
            font-size: ${18 + Math.random() * 14}px;
            pointer-events: none;
            z-index: 9999;
            transition: all 0.9s cubic-bezier(0.16, 1, 0.3, 1);
            opacity: 1;
            transform: scale(0.5) rotate(0deg);
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
        `;

        container.appendChild(propEl);

        setTimeout(() => {
            propEl.style.left = `${targetX}px`;
            propEl.style.top = `${targetY}px`;
            propEl.style.opacity = '0';
            propEl.style.transform = `scale(1.4) rotate(${Math.random() * 360 - 180}deg)`;
        }, 20);

        setTimeout(() => propEl.remove(), 950);
    }
}

function showMotivationSpeechBubble(parentElement, salesName) {
    const existing = parentElement.querySelector('.nailong-speech-bubble');
    if (existing) existing.remove();

    const bubble = document.createElement('div');
    bubble.className = 'nailong-speech-bubble';
    
    const quotes = [
        `DIRGAHAYU INDONESIA! 🇮🇩 Semangat ${salesName}! 🔥💪`,
        `MERDEKA! 🇮🇩 Gas Terus ${salesName}! 🚀🏆`,
        `17 AGUSTUS GACOR! 🇮🇩 ${salesName} Mantaap! 💰💵`,
        `SULTAN KEMERDEKAAN! 🇮🇩 ${salesName} 👑🌟`,
        `JUARA LOEWIX 17 AGUSTUS! 🇮🇩 ${salesName} 💎🎉`
    ];
    const randomQuote = quotes[Math.floor(Math.random() * quotes.length)];

    bubble.style.cssText = `
        position: absolute;
        bottom: 52px;
        left: 50%;
        transform: translateX(-50%) scale(0.7);
        background: linear-gradient(135deg, #DC2626, #991B1B);
        color: #FFFFFF;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 11.5px;
        font-weight: 800;
        padding: 7px 14px;
        border-radius: 14px;
        border: 1.5px solid #FCD34D;
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.5);
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        z-index: 100;
    `;
    
    bubble.innerText = randomQuote;
    parentElement.appendChild(bubble);

    setTimeout(() => {
        bubble.style.opacity = '1';
        bubble.style.transform = 'translateX(-50%) scale(1)';
    }, 20);

    setTimeout(() => {
        bubble.style.opacity = '0';
        bubble.style.transform = 'translateX(-50%) scale(0.7)';
        setTimeout(() => bubble.remove(), 300);
    }, 2200);
}

function switchChartMetric(metricType) {
    currentMetric = metricType;
    
    document.getElementById('btnMetricOmset').classList.remove('active');
    document.getElementById('btnMetricTotal').classList.remove('active');
    document.getElementById('btnMetricCustomer').classList.remove('active');

    let titleText = '🏁 Sirkuit Lari Sales (Omset Invoice Rp - Target 200 Juta)';
    let metricLblText = 'Omset Invoice (Agt 2026)';

    if (metricType === 'omset') {
        document.getElementById('btnMetricOmset').classList.add('active');
        titleText = '🏁 Sirkuit Lari Sales (Omset Invoice Rp - Target 200 Juta)';
        metricLblText = 'Omset Invoice (Agt 2026)';
    } else if (metricType === 'total') {
        document.getElementById('btnMetricTotal').classList.add('active');
        titleText = '⚡ Sirkuit Lari Sales (Total Activity Follow Up)';
        metricLblText = 'Total Activity FU';
    } else if (metricType === 'customer') {
        document.getElementById('btnMetricCustomer').classList.add('active');
        titleText = '👥 Sirkuit Lari Sales (Jumlah Customer di-FU)';
        metricLblText = 'Customer di-FU';
    }

    document.getElementById('chartTitle').innerHTML = titleText;
    document.querySelectorAll('.metric-lbl').forEach(el => el.innerText = metricLblText);

    updatePodiumCards(metricType);
    renderScaledChart();
}

function updatePodiumCards(metricType) {
    const val1 = document.getElementById('podium1Val');
    const val2 = document.getElementById('podium2Val');
    const val3 = document.getElementById('podium3Val');

    if (val1 && top1Data) val1.innerText = getFormattedMetricValue(top1Data, metricType);
    if (val2 && top2Data) val2.innerText = getFormattedMetricValue(top2Data, metricType);
    if (val3 && top3Data) val3.innerText = getFormattedMetricValue(top3Data, metricType);

    [val1, val2, val3].forEach(el => {
        if (el) {
            el.classList.remove('pop-metric');
            void el.offsetWidth;
            el.classList.add('pop-metric');
        }
    });
}

function getFormattedMetricValue(data, metricType) {
    if (metricType === 'omset') return formatRupiahDisplay(data.total_omset_invoice);
    if (metricType === 'total') return new Intl.NumberFormat('id-ID').format(data.total_fu);
    return new Intl.NumberFormat('id-ID').format(data.total_customer_fu);
}

function setTopLimit(limit, btn) {
    currentTopLimit = limit;
    document.querySelectorAll('.top-limit-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderScaledChart();
}
</script>
