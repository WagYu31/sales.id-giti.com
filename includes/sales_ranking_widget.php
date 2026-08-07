<?php
/**
 * GRAFIK RANKING & LEADERBOARD SALES WIDGET - HIGH ENERGETIC NAILONG DRAGON (奶龙) SPRINT RUNNER EDITION
 * Menampilkan peringkat performa sales berdasarkan data Laporan Follow Up Invoice
 */

// Fetch Ranking Sales Data matching invoice_followup_report.php
$sql_ranking_all = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(fu.id) AS total_fu,
        COUNT(DISTINCT fu.customer_id) AS total_customer_fu,
        SUM(CASE WHEN fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN 1 ELSE 0 END) AS total_inv_fu,
        SUM(CASE WHEN fu.no_inv IS NOT NULL AND fu.no_inv != '' AND (
            SELECT MIN(fu_next.tgl_follow_up)
            FROM follow_ups fu_next
            WHERE fu_next.customer_id = fu.customer_id
              AND fu_next.tgl_follow_up > fu.tgl_follow_up
              AND fu_next.deleted_at IS NULL
        ) IS NOT NULL THEN 1 ELSE 0 END) AS count_sudah_inv_fu
    FROM sales s
    LEFT JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL
    LEFT JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE s.role = 'sales' OR s.role = 'superadmin' OR fu.id IS NOT NULL
    GROUP BY s.id, s.nama_lengkap
    HAVING total_fu > 0
    ORDER BY count_sudah_inv_fu DESC, total_inv_fu DESC, total_fu DESC
";

$res_ranking = $conn->query($sql_ranking_all);
$ranking_data = [];
if ($res_ranking) {
    while ($row = $res_ranking->fetch_assoc()) {
        $ranking_data[] = $row;
    }
}

$chart_labels = [];
$chart_sudah_inv_fu = [];
$chart_total_fu = [];
$chart_customer_fu = [];

foreach ($ranking_data as $rd) {
    $chart_labels[] = $rd['nama_sales'];
    $chart_sudah_inv_fu[] = (int)$rd['count_sudah_inv_fu'];
    $chart_total_fu[] = (int)$rd['total_fu'];
    $chart_customer_fu[] = (int)$rd['total_customer_fu'];
}

$top1 = $ranking_data[0] ?? null;
$top2 = $ranking_data[1] ?? null;
$top3 = $ranking_data[2] ?? null;
$total_sales_count = count($ranking_data);
?>

<!-- 3D SPATIAL & HIGH ENERGETIC NAILONG DRAGON (奶龙) SPRINT RUNNER EDITION -->
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

/* HIGH ENERGETIC NAILONG SPRINT ANIMATIONS */
.nailong-runner-character {
    position: absolute;
    width: 48px;
    height: 50px;
    pointer-events: none;
    transition: left 0.08s linear, top 0.3s ease;
    z-index: 25;
}

.nailong-svg {
    filter: drop-shadow(0 6px 14px rgba(245, 158, 11, 0.4));
    animation: nailongEnergeticRun 0.24s infinite alternate cubic-bezier(0.4, 0, 0.6, 1);
}

.nailong-head-group {
    transform-origin: 23px 16px;
    animation: nailongHeadBob 0.24s infinite alternate ease-in-out;
}

.nailong-leg-left {
    transform-origin: 15px 42px;
    animation: nailongLegSprintLeft 0.24s infinite alternate ease-in-out;
}

.nailong-leg-right {
    transform-origin: 29px 42px;
    animation: nailongLegSprintRight 0.24s infinite alternate ease-in-out;
}

.nailong-arm-left {
    transform-origin: 12px 25px;
    animation: nailongArmSprintLeft 0.24s infinite alternate ease-in-out;
}

.nailong-arm-right {
    transform-origin: 32px 25px;
    animation: nailongArmSprintRight 0.24s infinite alternate ease-in-out;
}

.cctv-rec-dot {
    animation: recBlink 0.5s infinite alternate;
}

.nailong-speed-lines line {
    animation: speedLinePuff 0.18s infinite linear;
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

@keyframes nailongEnergeticRun {
    0% { transform: translateY(0px) rotate(-10deg) scale(0.98); }
    100% { transform: translateY(-7px) rotate(8deg) scale(1.05); }
}

@keyframes nailongHeadBob {
    0% { transform: rotate(-6deg); }
    100% { transform: rotate(10deg); }
}

@keyframes nailongLegSprintLeft {
    0% { transform: rotate(-65deg) scaleY(0.8); }
    100% { transform: rotate(65deg) scaleY(1.25); }
}

@keyframes nailongLegSprintRight {
    0% { transform: rotate(65deg) scaleY(1.25); }
    100% { transform: rotate(-65deg) scaleY(0.8); }
}

@keyframes nailongArmSprintLeft {
    0% { transform: rotate(-55deg); }
    100% { transform: rotate(55deg); }
}

@keyframes nailongArmSprintRight {
    0% { transform: rotate(55deg); }
    100% { transform: rotate(-55deg); }
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

.chart-scroll-wrapper {
    position: relative;
    max-height: 330px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 6px;
}

.chart-scroll-wrapper::-webkit-scrollbar {
    width: 6px;
}
.chart-scroll-wrapper::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}

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
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.25);
}

.btn-full-leaderboard {
    background: #F1F5F9;
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
                <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 19.5px; letter-spacing: -0.4px;">
                    Leaderboard & Grafik Ranking Sales
                    <span class="badge bg-warning bg-opacity-20 text-dark border border-warning border-opacity-50 rounded-pill px-3 py-1" style="font-size: 11.5px; font-weight: 800;">🐲 High-Speed Nailong (奶龙) Sprint</span>
                </h5>
                <p class="text-muted mb-0" style="font-size: 13.5px; font-family: 'Inter', sans-serif;">Peringkat sales berdasarkan total invoice yang telah berhasil di-follow up</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="metric-btn active" id="btnMetricInvSudah" onclick="switchChartMetric('inv_sudah')">
                📄 Invoice Sudah FU
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
                        <div class="fs-3 fw-bold text-warning metric-val" id="podium1Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;"><?php echo number_format($top1['count_sudah_inv_fu'], 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium1Lbl" style="font-size: 11px;">Sudah FU Invoice</small>
                    </div>
                </div>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(245, 158, 11, 0.25);">
                    <div class="progress-bar bg-warning bg-gradient" role="progressbar" style="width: 100%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top1['total_fu']; ?> Total Activity</span>
                    <span>👥 <?php echo $top1['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 2 Silver -->
            <?php if ($top2): ?>
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
                        <div class="fs-3 fw-bold text-secondary metric-val" id="podium2Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;"><?php echo number_format($top2['count_sudah_inv_fu'], 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium2Lbl" style="font-size: 11px;">Sudah FU Invoice</small>
                    </div>
                </div>
                <?php $pct2 = ($top1 && $top1['count_sudah_inv_fu'] > 0) ? round(($top2['count_sudah_inv_fu'] / $top1['count_sudah_inv_fu']) * 100) : 0; ?>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(148, 163, 184, 0.25);">
                    <div class="progress-bar bg-secondary bg-gradient" role="progressbar" style="width: <?php echo max($pct2, 5); ?>%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top2['total_fu']; ?> Total Activity</span>
                    <span>👥 <?php echo $top2['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 3 Bronze -->
            <?php if ($top3): ?>
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
                        <div class="fs-3 fw-bold text-danger metric-val" id="podium3Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;"><?php echo number_format($top3['count_sudah_inv_fu'], 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium3Lbl" style="font-size: 11px;">Sudah FU Invoice</small>
                    </div>
                </div>
                <?php $pct3 = ($top1 && $top1['count_sudah_inv_fu'] > 0) ? round(($top3['count_sudah_inv_fu'] / $top1['count_sudah_inv_fu']) * 100) : 0; ?>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(217, 119, 6, 0.25);">
                    <div class="progress-bar bg-warning bg-gradient" role="progressbar" style="width: <?php echo max($pct3, 5); ?>%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top3['total_fu']; ?> Total Activity</span>
                    <span>👥 <?php echo $top3['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Side: Interactive 3D Chart.js Graphic with NAILONG Dragon Overlay -->
        <div class="col-lg-7 col-12">
            <div class="p-3.5 bg-light rounded-4 border h-100 d-flex flex-column justify-content-between shadow-sm position-relative">
                <div class="d-flex justify-content-between align-items-center mb-2 px-2 flex-wrap gap-2">
                    <span class="fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size: 14px;" id="chartTitle">📊 Ranking Sales (Sudah FU Invoice)</span>
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="text-muted fw-semibold" style="font-size: 11px;">Tampilkan:</span>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit(5, this)">Top 5</button>
                        <button type="button" class="top-limit-btn active" onclick="setTopLimit(10, this)">Top 10</button>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit(20, this)">Top 20</button>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit('all', this)">Semua (<?php echo $total_sales_count; ?>)</button>
                    </div>
                </div>

                <!-- Scrollable Wrapper for Canvas & NAILONG Dragon Overlays -->
                <div class="chart-scroll-wrapper" id="chartScrollWrapper">
                    <div id="chartCanvasContainer" style="position: relative; height: 280px; width: 100%;">
                        <canvas id="salesRankingChart"></canvas>
                        <!-- Container for NAILONG Dragon (奶龙) Runner Characters -->
                        <div id="cctvMascotOverlayHolder" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FULL LEADERBOARD MODAL FOR 100+ SALES -->
<div class="modal fade" id="fullLeaderboardModal" tabindex="-1" aria-labelledby="fullLeaderboardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-3.5 px-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="fullLeaderboardModalLabel" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                    🏆 Full Leaderboard Performa Tim Sales (<?php echo $total_sales_count; ?> Sales Rep)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="mb-3">
                    <input type="text" id="modalSearchSales" class="form-control rounded-pill ps-4" placeholder="Cari nama sales rep..." style="font-size: 13.5px;" onkeyup="filterModalSalesTable()">
                </div>
                <div class="table-responsive bg-white rounded-3 border shadow-sm">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 px-3 text-center" style="font-size: 12px;">RANK</th>
                                <th class="py-3 px-3" style="font-size: 12px;">NAMA SALES REPRESENTATIVE</th>
                                <th class="py-3 px-3 text-end" style="font-size: 12px;">SUDAH FU INVOICE</th>
                                <th class="py-3 px-3 text-end" style="font-size: 12px;">TOTAL ACTIVITY FU</th>
                                <th class="py-3 px-3 text-end" style="font-size: 12px;">CUSTOMER DI-FU</th>
                            </tr>
                        </thead>
                        <tbody id="modalLeaderboardTableBody">
                            <?php foreach ($ranking_data as $idx => $s): ?>
                                <?php
                                $rank = $idx + 1;
                                $rankBadge = "<span class='badge bg-light text-dark border px-2.5 py-1 fw-bold'>#{$rank}</span>";
                                if ($rank == 1) $rankBadge = "<span class='badge bg-warning text-dark fw-bold px-2.5 py-1'>🥇 #1</span>";
                                elseif ($rank == 2) $rankBadge = "<span class='badge bg-secondary text-white fw-bold px-2.5 py-1'>🥈 #2</span>";
                                elseif ($rank == 3) $rankBadge = "<span class='badge bg-danger bg-opacity-75 text-white fw-bold px-2.5 py-1'>🥉 #3</span>";
                                ?>
                                <tr class="modal-sales-row">
                                    <td class="text-center px-3"><?php echo $rankBadge; ?></td>
                                    <td class="px-3 fw-bold text-dark" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                                        <?php echo htmlspecialchars($s['nama_sales']); ?>
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-success" style="font-size: 14px;">
                                        <?php echo number_format($s['count_sudah_inv_fu'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-primary" style="font-size: 14px;">
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
let currentMetric = 'inv_sudah';
let currentTopLimit = 10;

const salesChartLabels = <?php echo json_encode($chart_labels); ?>;
const salesChartInvSudah = <?php echo json_encode($chart_sudah_inv_fu); ?>;
const salesChartTotalFU = <?php echo json_encode($chart_total_fu); ?>;
const salesChartCustomerFU = <?php echo json_encode($chart_customer_fu); ?>;

const top1Data = <?php echo json_encode($top1); ?>;
const top2Data = <?php echo json_encode($top2); ?>;
const top3Data = <?php echo json_encode($top3); ?>;

document.addEventListener("DOMContentLoaded", function() {
    renderScaledChart();
});

function getActiveDatasetValues() {
    if (currentMetric === 'inv_sudah') return { values: salesChartInvSudah, label: "Sudah FU Invoice" };
    if (currentMetric === 'total') return { values: salesChartTotalFU, label: "Total Activity FU" };
    return { values: salesChartCustomerFU, label: "Customer di-FU" };
}

function renderScaledChart() {
    const { values, label } = getActiveDatasetValues();
    
    let limit = currentTopLimit === 'all' ? values.length : parseInt(currentTopLimit);
    let slicedLabels = salesChartLabels.slice(0, limit);
    let slicedValues = values.slice(0, limit);

    // Calculate dynamic height for canvas so bars never get squished
    const container = document.getElementById('chartCanvasContainer');
    const barHeightPx = 42;
    const computedHeight = Math.max(280, slicedLabels.length * barHeightPx);
    container.style.height = `${computedHeight}px`;

    const ctx = document.getElementById('salesRankingChart').getContext('2d');
    
    if (salesChartInstance) {
        salesChartInstance.destroy();
    }

    // Clear old Nailong overlay runners
    const holder = document.getElementById('cctvMascotOverlayHolder');
    if (holder) holder.innerHTML = '';

    // 3D Gradient Fills for Chart Bars
    const gradientGold = ctx.createLinearGradient(0, 0, 400, 0);
    gradientGold.addColorStop(0, '#F59E0B');
    gradientGold.addColorStop(1, '#FCD34D');

    const gradientBlue = ctx.createLinearGradient(0, 0, 400, 0);
    gradientBlue.addColorStop(0, '#2563EB');
    gradientBlue.addColorStop(1, '#38BDF8');

    const gradientGreen = ctx.createLinearGradient(0, 0, 400, 0);
    gradientGreen.addColorStop(0, '#059669');
    gradientGreen.addColorStop(1, '#34D399');

    const gradientSilver = ctx.createLinearGradient(0, 0, 400, 0);
    gradientSilver.addColorStop(0, '#64748B');
    gradientSilver.addColorStop(1, '#94A3B8');

    const gradientBronze = ctx.createLinearGradient(0, 0, 400, 0);
    gradientBronze.addColorStop(0, '#D97706');
    gradientBronze.addColorStop(1, '#FDBA74');

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
                    '#475569',
                    '#B45309',
                    '#1D4ED8',
                    '#047857'
                ],
                borderWidth: 1.5,
                borderRadius: 10,
                barThickness: 22,
                maxBarThickness: 26
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    right: 65,
                    top: 10,
                    bottom: 10
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                onProgress: function() {
                    syncCctvMascotOverlays();
                },
                onComplete: function() {
                    syncCctvMascotOverlays();
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0F172A',
                    padding: 12,
                    titleFont: { family: 'Plus Jakarta Sans', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Inter', size: 12 },
                    cornerRadius: 12,
                    displayColors: false
                }
            },
            scales: {
                x: {
                    grace: '18%',
                    grid: { color: 'rgba(226, 232, 240, 0.7)' },
                    ticks: { font: { family: 'Inter', size: 11 }, color: '#64748B' }
                },
                y: {
                    grid: { display: false },
                    ticks: { font: { family: 'Plus Jakarta Sans', size: 12, weight: '600' }, color: '#0F172A' }
                }
            }
        }
    });
}

function syncCctvMascotOverlays() {
    const holder = document.getElementById('cctvMascotOverlayHolder');
    if (!holder || !salesChartInstance) return;

    const meta = salesChartInstance.getDatasetMeta(0);
    if (!meta || !meta.data) return;

    meta.data.forEach((bar, idx) => {
        const pos = bar.tooltipPosition();
        let runnerDiv = document.getElementById(`nailong-runner-${idx}`);

        if (!runnerDiv) {
            runnerDiv = document.createElement('div');
            runnerDiv.id = `nailong-runner-${idx}`;
            runnerDiv.className = 'nailong-runner-character';
            holder.appendChild(runnerDiv);
        }

        // Dynamically update position in sync with bar animation growth
        runnerDiv.style.left = `${pos.x + 8}px`;
        runnerDiv.style.top = `${pos.y - 25}px`;

        let rankTagHtml = '';
        if (idx === 0) rankTagHtml = '<span class="nailong-rank-tag">🥇 👑</span>';
        else if (idx === 1) rankTagHtml = '<span class="nailong-rank-tag">🥈</span>';
        else if (idx === 2) rankTagHtml = '<span class="nailong-rank-tag">🥉</span>';

        runnerDiv.innerHTML = `
            <svg width="48" height="50" viewBox="0 0 48 50" fill="none" xmlns="http://www.w3.org/2000/svg" class="nailong-svg">
                <!-- High-Speed Running Trail Lines -->
                <g class="nailong-speed-lines">
                    <line x1="2" y1="20" x2="8" y2="20" stroke="#FFCC00" stroke-width="2.2" stroke-linecap="round"/>
                    <line x1="0" y1="28" x2="6" y2="28" stroke="#FFCC00" stroke-width="2.2" stroke-linecap="round"/>
                    <line x1="3" y1="36" x2="9" y2="36" stroke="#FFCC00" stroke-width="2.2" stroke-linecap="round"/>
                </g>

                <!-- Tail -->
                <path d="M10 36 C 4 38, 2 34, 6 30 C 8 32, 10 34, 12 34 Z" fill="#FFCC00" stroke="#E6B800" stroke-width="1"/>
                
                <!-- Left Running Leg -->
                <ellipse cx="15" cy="42" rx="4.5" ry="5.5" fill="#FFCC00" stroke="#E6B800" stroke-width="1.2" class="nailong-leg-left"/>
                <!-- Right Running Leg -->
                <ellipse cx="29" cy="42" rx="4.5" ry="5.5" fill="#FFCC00" stroke="#E6B800" stroke-width="1.2" class="nailong-leg-right"/>
                
                <!-- Chubby Yellow Dragon Body & White Belly -->
                <path d="M14 20 C 10 24, 10 38, 16 42 C 20 44, 28 44, 32 42 C 38 38, 38 24, 34 20 Z" fill="#FFCC00" stroke="#E6B800" stroke-width="1.5"/>
                <ellipse cx="23" cy="32" rx="7.5" ry="8.5" fill="#FFEAA7"/>

                <!-- Short Arm Left -->
                <path d="M12 25 Q 6 28 9 32" stroke="#FFCC00" stroke-width="4.5" stroke-linecap="round" class="nailong-arm-left"/>
                <!-- Short Arm Right -->
                <path d="M32 25 Q 38 28 35 32" stroke="#FFCC00" stroke-width="4.5" stroke-linecap="round" class="nailong-arm-right"/>

                <!-- Cute NAILONG Head -->
                <g class="nailong-head-group">
                    <circle cx="23" cy="16" r="13" fill="#FFCC00" stroke="#E6B800" stroke-width="1.5"/>
                    <path d="M15 5 C 13 1, 17 2, 18 6 Z" fill="#FF9900"/>
                    <path d="M31 5 C 29 1, 33 2, 28 6 Z" fill="#FF9900"/>

                    <!-- CCTV Camera Visor / Headset for Loewix Theme -->
                    <rect x="11" y="10" width="24" height="9" rx="4.5" fill="#0F172A" stroke="#2563EB" stroke-width="1"/>
                    <circle cx="28" cy="14.5" r="3" fill="#00F0FF"/>
                    <circle cx="28" cy="14.5" r="1.2" fill="#FFFFFF"/>
                    <circle cx="15" cy="12" r="1" fill="#FF2E2E" class="cctv-rec-dot"/>

                    <!-- Blushy Pink Cheeks -->
                    <circle cx="13" cy="21" r="2.5" fill="#FF7675" opacity="0.65"/>
                    <circle cx="33" cy="21" r="2.5" fill="#FF7675" opacity="0.65"/>

                    <!-- Cute Eyes -->
                    <circle cx="17" cy="18" r="2.2" fill="#2D3436"/>
                    <circle cx="17" cy="17.2" r="0.8" fill="#FFFFFF"/>
                    <circle cx="29" cy="18" r="2.2" fill="#2D3436"/>
                    <circle cx="29" cy="17.2" r="0.8" fill="#FFFFFF"/>
                </g>
            </svg>
            ${rankTagHtml}
        `;
    });
}

function triggerNumberPop() {
    const vals = document.querySelectorAll('.metric-val');
    vals.forEach(v => {
        v.classList.remove('pop-metric');
        void v.offsetWidth; // trigger reflow
        v.classList.add('pop-metric');
    });
}

function setTopLimit(limit, btn) {
    currentTopLimit = limit;
    const buttons = document.querySelectorAll('.top-limit-btn');
    buttons.forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderScaledChart();
}

function switchChartMetric(type) {
    currentMetric = type;
    const btnInvSudah = document.getElementById('btnMetricInvSudah');
    const btnTotal = document.getElementById('btnMetricTotal');
    const btnCustomer = document.getElementById('btnMetricCustomer');

    const chartTitle = document.getElementById('chartTitle');
    const p1Val = document.getElementById('podium1Val');
    const p1Lbl = document.getElementById('podium1Lbl');
    const p2Val = document.getElementById('podium2Val');
    const p2Lbl = document.getElementById('podium2Lbl');
    const p3Val = document.getElementById('podium3Val');
    const p3Lbl = document.getElementById('podium3Lbl');

    btnInvSudah.classList.remove('active');
    btnTotal.classList.remove('active');
    btnCustomer.classList.remove('active');

    if (type === 'inv_sudah') {
        btnInvSudah.classList.add('active');
        if (chartTitle) chartTitle.innerText = '📊 Ranking Sales (Sudah FU Invoice)';
        if (p1Val && top1Data) p1Val.innerText = top1Data.count_sudah_inv_fu;
        if (p1Lbl) p1Lbl.innerText = 'Sudah FU Invoice';
        if (p2Val && top2Data) p2Val.innerText = top2Data.count_sudah_inv_fu;
        if (p2Lbl) p2Lbl.innerText = 'Sudah FU Invoice';
        if (p3Val && top3Data) p3Val.innerText = top3Data.count_sudah_inv_fu;
        if (p3Lbl) p3Lbl.innerText = 'Sudah FU Invoice';
    } else if (type === 'total') {
        btnTotal.classList.add('active');
        if (chartTitle) chartTitle.innerText = '📊 Ranking Sales (Total Activity FU)';
        if (p1Val && top1Data) p1Val.innerText = top1Data.total_fu;
        if (p1Lbl) p1Lbl.innerText = 'Total Activity FU';
        if (p2Val && top2Data) p2Val.innerText = top2Data.total_fu;
        if (p2Lbl) p2Lbl.innerText = 'Total Activity FU';
        if (p3Val && top3Data) p3Val.innerText = top3Data.total_fu;
        if (p3Lbl) p3Lbl.innerText = 'Total Activity FU';
    } else {
        btnCustomer.classList.add('active');
        if (chartTitle) chartTitle.innerText = '📊 Ranking Sales (Customer di-FU)';
        if (p1Val && top1Data) p1Val.innerText = top1Data.total_customer_fu;
        if (p1Lbl) p1Lbl.innerText = 'Customer di-FU';
        if (p2Val && top2Data) p2Val.innerText = top2Data.total_customer_fu;
        if (p2Lbl) p2Lbl.innerText = 'Customer di-FU';
        if (p3Val && top3Data) p3Val.innerText = top3Data.total_customer_fu;
        if (p3Lbl) p3Lbl.innerText = 'Customer di-FU';
    }
    triggerNumberPop();
    renderScaledChart();
}

function filterModalSalesTable() {
    const input = document.getElementById('modalSearchSales').value.toLowerCase();
    const rows = document.querySelectorAll('#modalLeaderboardTableBody tr.modal-sales-row');
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        if (text.includes(input)) {
            r.style.display = "";
        } else {
            r.style.display = "none";
        }
    });
}
</script>
