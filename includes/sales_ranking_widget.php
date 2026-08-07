<?php
/**
 * GRAFIK RANKING & LEADERBOARD SALES WIDGET
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
?>

<!-- 3D SPATIAL SALES RANKING LEADERBOARD WIDGET -->
<style>
.ranking-widget-card {
    background: #FFFFFF;
    border-radius: 26px;
    padding: 30px 34px;
    margin-bottom: 34px;
    position: relative;
    box-shadow: 0 20px 45px -12px rgba(15, 23, 42, 0.08), 0 4px 12px rgba(15, 23, 42, 0.02);
    border: 1.5px solid rgba(226, 232, 240, 0.9) !important;
    overflow: hidden;
}

.ranking-widget-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #F59E0B 0%, #10B981 50%, #2563EB 100%);
    box-shadow: 0 2px 10px rgba(245, 158, 11, 0.3);
}

.podium-card {
    border-radius: 20px;
    padding: 20px 22px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1.5px solid #E2E8F0;
    background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%);
}

.podium-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 30px rgba(0,0,0,0.08);
}

.podium-card.gold {
    border-color: #FCD34D !important;
    background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
    box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.25);
}

.podium-card.silver {
    border-color: #CBD5E1 !important;
    background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
    box-shadow: 0 10px 25px -5px rgba(148, 163, 184, 0.2);
}

.podium-card.bronze {
    border-color: #FDBA74 !important;
    background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
    box-shadow: 0 10px 25px -5px rgba(217, 119, 6, 0.2);
}

.podium-rank-badge {
    width: 36px; height: 36px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 16px;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.podium-rank-badge.gold { background: linear-gradient(135deg, #F59E0B, #D97706); }
.podium-rank-badge.silver { background: linear-gradient(135deg, #94A3B8, #64748B); }
.podium-rank-badge.bronze { background: linear-gradient(135deg, #D97706, #B45309); }

.chart-container-holder {
    position: relative;
    height: 280px;
    width: 100%;
}

.metric-btn {
    border: 1px solid #CBD5E1;
    background: #F8FAFC;
    color: #64748B;
    font-weight: 700;
    font-size: 12px;
    padding: 6px 14px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.metric-btn.active, .metric-btn:hover {
    background: #2563EB;
    color: #FFFFFF;
    border-color: #2563EB;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="ranking-widget-card">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 48px; height: 48px; background: linear-gradient(135deg, #F59E0B, #D97706); font-size: 24px;">
                🏆
            </div>
            <div>
                <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 19px; letter-spacing: -0.4px;">
                    Leaderboard & Grafik Ranking Sales
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1" style="font-size: 11.5px; font-weight: 800;">Sudah FU Invoice</span>
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
        </div>
    </div>

    <div class="row g-4 align-items-stretch">
        <!-- Left Side: Top 3 Podium Cards -->
        <div class="col-lg-5 col-12 d-flex flex-column gap-3">
            <!-- Top 1 Gold -->
            <?php if ($top1): ?>
            <div class="podium-card gold">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="podium-rank-badge gold">🥇</div>
                        <div>
                            <span class="badge bg-warning text-dark fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10px;">JUARA 1</span>
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 16px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top1['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold text-warning metric-val" id="podium1Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;"><?php echo number_format($top1['count_sudah_inv_fu'], 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium1Lbl" style="font-size: 11px;">Sudah FU Invoice</small>
                    </div>
                </div>
                <div class="progress mt-2" style="height: 6px; border-radius: 10px; background: rgba(245, 158, 11, 0.2);">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: 100%; border-radius: 10px;"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top1['total_fu']; ?> Total Activity</span>
                    <span>👥 <?php echo $top1['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 2 Silver -->
            <?php if ($top2): ?>
            <div class="podium-card silver">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="podium-rank-badge silver">🥈</div>
                        <div>
                            <span class="badge bg-secondary text-white fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10px;">JUARA 2</span>
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 15.5px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top2['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold text-secondary metric-val" id="podium2Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;"><?php echo number_format($top2['count_sudah_inv_fu'], 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium2Lbl" style="font-size: 11px;">Sudah FU Invoice</small>
                    </div>
                </div>
                <?php $pct2 = ($top1 && $top1['count_sudah_inv_fu'] > 0) ? round(($top2['count_sudah_inv_fu'] / $top1['count_sudah_inv_fu']) * 100) : 0; ?>
                <div class="progress mt-2" style="height: 6px; border-radius: 10px; background: rgba(148, 163, 184, 0.2);">
                    <div class="progress-bar bg-secondary" role="progressbar" style="width: <?php echo max($pct2, 5); ?>%; border-radius: 10px;"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top2['total_fu']; ?> Total Activity</span>
                    <span>👥 <?php echo $top2['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 3 Bronze -->
            <?php if ($top3): ?>
            <div class="podium-card bronze">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="podium-rank-badge bronze">🥉</div>
                        <div>
                            <span class="badge bg-danger bg-opacity-75 text-white fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10px;">JUARA 3</span>
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 15.5px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top3['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold text-danger metric-val" id="podium3Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;"><?php echo number_format($top3['count_sudah_inv_fu'], 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium3Lbl" style="font-size: 11px;">Sudah FU Invoice</small>
                    </div>
                </div>
                <?php $pct3 = ($top1 && $top1['count_sudah_inv_fu'] > 0) ? round(($top3['count_sudah_inv_fu'] / $top1['count_sudah_inv_fu']) * 100) : 0; ?>
                <div class="progress mt-2" style="height: 6px; border-radius: 10px; background: rgba(217, 119, 6, 0.2);">
                    <div class="progress-bar bg-warning bg-gradient" role="progressbar" style="width: <?php echo max($pct3, 5); ?>%; border-radius: 10px;"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 text-muted" style="font-size: 12px;">
                    <span>⚡ <?php echo $top3['total_fu']; ?> Total Activity</span>
                    <span>👥 <?php echo $top3['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Side: Interactive Chart.js Graphic -->
        <div class="col-lg-7 col-12">
            <div class="p-3.5 bg-light rounded-4 border h-100 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                    <span class="fw-bold text-dark" style="font-size: 14px;" id="chartTitle">📊 Ranking Sales (Sudah FU Invoice)</span>
                    <small class="text-muted" style="font-size: 12px;">Sesuai Laporan Invoice FU</small>
                </div>
                <div class="chart-container-holder">
                    <canvas id="salesRankingChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let salesChartInstance = null;

const salesChartLabels = <?php echo json_encode($chart_labels); ?>;
const salesChartInvSudah = <?php echo json_encode($chart_sudah_inv_fu); ?>;
const salesChartTotalFU = <?php echo json_encode($chart_total_fu); ?>;
const salesChartCustomerFU = <?php echo json_encode($chart_customer_fu); ?>;

const top1Data = <?php echo json_encode($top1); ?>;
const top2Data = <?php echo json_encode($top2); ?>;
const top3Data = <?php echo json_encode($top3); ?>;

document.addEventListener("DOMContentLoaded", function() {
    initSalesRankingChart(salesChartInvSudah, "Sudah FU Invoice");
});

function initSalesRankingChart(dataValues, datasetLabel) {
    const ctx = document.getElementById('salesRankingChart').getContext('2d');
    
    if (salesChartInstance) {
        salesChartInstance.destroy();
    }

    salesChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: salesChartLabels,
            datasets: [{
                label: datasetLabel,
                data: dataValues,
                backgroundColor: [
                    'rgba(245, 158, 11, 0.85)',
                    'rgba(148, 163, 184, 0.85)',
                    'rgba(217, 119, 6, 0.85)',
                    'rgba(37, 99, 235, 0.75)',
                    'rgba(16, 185, 129, 0.75)'
                ],
                borderColor: [
                    '#D97706',
                    '#64748B',
                    '#B45309',
                    '#1D4ED8',
                    '#059669'
                ],
                borderWidth: 1.5,
                borderRadius: 8,
                barPercentage: 0.6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0F172A',
                    padding: 12,
                    titleFont: { family: 'Plus Jakarta Sans', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Inter', size: 12 },
                    cornerRadius: 10
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(226, 232, 240, 0.6)' },
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

function switchChartMetric(type) {
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
        initSalesRankingChart(salesChartInvSudah, "Sudah FU Invoice");
    } else if (type === 'total') {
        btnTotal.classList.add('active');
        if (chartTitle) chartTitle.innerText = '📊 Ranking Sales (Total Activity FU)';
        if (p1Val && top1Data) p1Val.innerText = top1Data.total_fu;
        if (p1Lbl) p1Lbl.innerText = 'Total Activity FU';
        if (p2Val && top2Data) p2Val.innerText = top2Data.total_fu;
        if (p2Lbl) p2Lbl.innerText = 'Total Activity FU';
        if (p3Val && top3Data) p3Val.innerText = top3Data.total_fu;
        if (p3Lbl) p3Lbl.innerText = 'Total Activity FU';
        initSalesRankingChart(salesChartTotalFU, "Total Activity FU");
    } else {
        btnCustomer.classList.add('active');
        if (chartTitle) chartTitle.innerText = '📊 Ranking Sales (Customer di-FU)';
        if (p1Val && top1Data) p1Val.innerText = top1Data.total_customer_fu;
        if (p1Lbl) p1Lbl.innerText = 'Customer di-FU';
        if (p2Val && top2Data) p2Val.innerText = top2Data.total_customer_fu;
        if (p2Lbl) p2Lbl.innerText = 'Customer di-FU';
        if (p3Val && top3Data) p3Val.innerText = top3Data.total_customer_fu;
        if (p3Lbl) p3Lbl.innerText = 'Customer di-FU';
        initSalesRankingChart(salesChartCustomerFU, "Customer di-FU");
    }
}
</script>
