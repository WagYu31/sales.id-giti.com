<?php
/**
 * GRAFIK RANKING & LEADERBOARD SALES WIDGET - 3D SPATIAL & CCTV MASCOT RUNNER EDITION
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

<!-- 3D SPATIAL & CCTV MASCOT RUNNER EDITION -->
<style>
@keyframes float3DMedal {
    0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
    50% { transform: translateY(-5px) rotate(4deg) scale(1.08); }
}

@keyframes cctvRunnerBob {
    0%, 100% { transform: translateY(0px) rotate(-3deg); }
    50% { transform: translateY(-4px) rotate(3deg); }
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

.cctv-mascot-runner-badge {
    animation: cctvRunnerBob 1.8s ease-in-out infinite;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #0F172A, #1E293B);
    color: #38BDF8;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
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
                    <span class="cctv-mascot-runner-badge">📹🏃‍♂️ Loewix CCTV Runner</span>
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

        <!-- Right Side: Interactive & Scalable 3D Chart.js Graphic with CCTV Mascot Plugin -->
        <div class="col-lg-7 col-12">
            <div class="p-3.5 bg-light rounded-4 border h-100 d-flex flex-column justify-content-between shadow-sm">
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

                <!-- Scrollable Wrapper ensuring optimal bar height even with 100+ sales -->
                <div class="chart-scroll-wrapper">
                    <div id="chartCanvasContainer" style="position: relative; height: 280px; width: 100%;">
                        <canvas id="salesRankingChart"></canvas>
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

// Custom Chart.js Plugin: CCTV-Head Cartoon Character Mascot Running on Bars
const cctvMascotRunnerPlugin = {
    id: 'cctvMascotRunnerPlugin',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const meta = chart.getDatasetMeta(0);

        meta.data.forEach((bar, index) => {
            const { x, y } = bar.tooltipPosition();
            
            ctx.save();
            ctx.translate(x + 10, y);
            
            ctx.font = 'bold 15px sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            
            if (index === 0) {
                // Juara 1: Golden Loewix CCTV Mascot Runner with Flame & Crown
                ctx.fillText('📹🏃‍♂️💨 🥇👑', 2, 0);
            } else if (index === 1) {
                // Juara 2: Silver Loewix CCTV Mascot Runner
                ctx.fillText('📹🏃‍♂️💨 🥈', 2, 0);
            } else if (index === 2) {
                // Juara 3: Bronze Loewix CCTV Mascot Runner
                ctx.fillText('📹🏃‍♂️ 🥉', 2, 0);
            } else {
                // Standard Loewix CCTV Mascot Runner
                ctx.fillText('📹🏃‍♂️', 2, 0);
            }
            
            ctx.restore();
        });
    }
};

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
    const barHeightPx = 38;
    const computedHeight = Math.max(280, slicedLabels.length * barHeightPx);
    container.style.height = `${computedHeight}px`;

    const ctx = document.getElementById('salesRankingChart').getContext('2d');
    
    if (salesChartInstance) {
        salesChartInstance.destroy();
    }

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
            animation: {
                duration: 1400,
                easing: 'easeOutQuart'
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
                    grid: { color: 'rgba(226, 232, 240, 0.7)' },
                    ticks: { font: { family: 'Inter', size: 11 }, color: '#64748B' }
                },
                y: {
                    grid: { display: false },
                    ticks: { font: { family: 'Plus Jakarta Sans', size: 12, weight: '600' }, color: '#0F172A' }
                }
            }
        },
        plugins: [cctvMascotRunnerPlugin]
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
