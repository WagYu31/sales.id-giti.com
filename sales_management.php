<?php
$page_title = 'Data Sales';
require_once 'includes/db.php';
require_once 'includes/header.php';

// --- BAGIAN PENGAMBILAN DATA UNTUK KPI ---
$kpi_sql = "
    SELECT 
        s.id, 
        s.nama_lengkap, 
        s.email,
        COUNT(DISTINCT c.id) AS total_customer,
        COUNT(DISTINCT f.id) AS total_follow_up,
        COUNT(DISTINCT CASE WHEN c.deal = 'Y' THEN c.id END) AS total_deal,
        COUNT(DISTINCT CASE WHEN f.no_inv IS NOT NULL AND f.no_inv != '' THEN f.id END) AS total_invoice
    FROM 
        sales s
    LEFT JOIN 
        customers c ON s.id = c.sales_id AND c.deleted_at IS NULL
    LEFT JOIN 
        follow_ups f ON s.id = f.sales_id AND f.deleted_at IS NULL
    WHERE
        s.role = 'sales' AND s.deleted_at IS NULL
    GROUP BY 
        s.id, s.nama_lengkap, s.email
    ORDER BY
        total_deal DESC, total_follow_up DESC;
";

$kpi_result = $conn->query($kpi_sql);
$kpi_data = $kpi_result ? $kpi_result->fetch_all(MYSQLI_ASSOC) : [];

// Menyiapkan data untuk Chart.js dan KPI Cards
$chart_labels = [];
$chart_customer = [];
$chart_deals = [];
$chart_invoices = [];

$total_sales = count($kpi_data);
$grand_total_customer = 0;
$grand_total_follow_up = 0;
$grand_total_deal = 0;
$grand_total_invoice = 0;

foreach ($kpi_data as $data) {
    $chart_labels[] = $data['nama_lengkap'];
    $chart_customer[] = (int) $data['total_customer'];
    $chart_deals[] = (int) $data['total_deal'];
    $chart_invoices[] = (int) $data['total_invoice'];
    
    $grand_total_customer += $data['total_customer'];
    $grand_total_follow_up += $data['total_follow_up'];
    $grand_total_deal += $data['total_deal'];
    $grand_total_invoice += $data['total_invoice'];
}
?>

<style>
/* Sales Performance Page Enhancements */
.sales-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.sales-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.sales-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.sales-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

/* Stat Cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 32px;
}

.kpi-card-v2 {
    border-radius: 20px;
    padding: 24px 26px;
    position: relative;
    overflow: hidden;
    color: #FFF;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 130px;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 8px 24px -6px rgba(0,0,0,0.12);
}

.kpi-card-v2:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 36px -8px rgba(0,0,0,0.2);
}

.kpi-card-v2.c-indigo {
    background: linear-gradient(135deg, #312E81 0%, #4F46E5 50%, #6366F1 100%);
}

.kpi-card-v2.c-blue {
    background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 50%, #3B82F6 100%);
}

.kpi-card-v2.c-amber {
    background: linear-gradient(135deg, #78350F 0%, #D97706 50%, #F59E0B 100%);
}

.kpi-card-v2.c-emerald {
    background: linear-gradient(135deg, #065F46 0%, #059669 50%, #10B981 100%);
}

.kpi-icon-glass {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: rgba(255,255,255,0.18);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.kpi-val {
    font-size: 32px;
    font-weight: 800;
    font-family: 'Plus Jakarta Sans', sans-serif;
    line-height: 1;
    letter-spacing: -1px;
}

.kpi-lbl {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgba(255,255,255,0.75);
    margin-bottom: 6px;
}

.sales-avatar-badge {
    width: 32px; height: 32px;
    border-radius: 10px;
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #FFF;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    margin-right: 10px;
    box-shadow: 0 3px 8px rgba(37,99,235,0.3);
}

.action-btn-edit {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: #FEF3C7;
    color: #D97706;
    border: 1px solid #FDE68A;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-btn-edit:hover {
    background: #F59E0B;
    color: #FFF;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245,158,11,0.3);
}

.action-btn-del {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: #FFE4E6;
    color: #E11D48;
    border: 1px solid #FECDD3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-btn-del:hover {
    background: #E11D48;
    color: #FFF;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(225,29,72,0.3);
}

@media (max-width: 1200px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 576px) {
    .kpi-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Hero Header -->
<div class="sales-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Sales Performance</span>
            </div>
            <h1 class="sales-hero-title">Sales Performance & KPI Analytics 📈</h1>
            <p class="sales-hero-subtitle">Analisis statistik performa sales, konversi deal, invoice, dan manajemen akun tim.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin'): ?>
            <a href="sales_add.php" class="btn btn-primary shadow-lg">
                <i class="bi bi-person-plus-fill"></i> Tambah Sales Baru
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- KPI Cards Grid -->
<div class="kpi-grid">
    <div class="kpi-card-v2 c-indigo">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-lbl">Total Sales Aktif</div>
                <div class="kpi-val"><?php echo number_format($total_sales); ?></div>
            </div>
            <div class="kpi-icon-glass"><i class="bi bi-people-fill"></i></div>
        </div>
    </div>
    
    <div class="kpi-card-v2 c-blue">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-lbl">Total Customer</div>
                <div class="kpi-val"><?php echo number_format($grand_total_customer); ?></div>
            </div>
            <div class="kpi-icon-glass"><i class="bi bi-person-check-fill"></i></div>
        </div>
    </div>
    
    <div class="kpi-card-v2 c-amber">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-lbl">Total Follow Up</div>
                <div class="kpi-val"><?php echo number_format($grand_total_follow_up); ?></div>
            </div>
            <div class="kpi-icon-glass"><i class="bi bi-telephone-outbound-fill"></i></div>
        </div>
    </div>
    
    <div class="kpi-card-v2 c-emerald">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="kpi-lbl">Total Deal / Invoice</div>
                <div class="kpi-val"><?php echo number_format($grand_total_deal); ?> <span style="font-size:20px; font-weight:600; opacity:0.8;">/ <?php echo number_format($grand_total_invoice); ?></span></div>
            </div>
            <div class="kpi-icon-glass"><i class="bi bi-patch-check-fill"></i></div>
        </div>
    </div>
</div>

<!-- Chart Container -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-bar-chart-line-fill"></i> Diagram Performa Sales</h5>
    </div>
    <div class="card-body p-4">
        <div class="chart-container" style="position: relative; height:42vh; width: 100%;">
            <canvas id="kpiChart"></canvas>
        </div>
    </div>
</div>

<!-- Table Container -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-person-lines-fill"></i> Data Tim Sales</h5>
        <span class="badge bg-info"><?php echo $total_sales; ?> Sales Terdaftar</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle sortable-table">
                <thead class="table-dark-header">
                    <tr>
                        <th style="width: 25%;">Nama Lengkap</th>
                        <th style="width: 25%;">Email</th>
                        <th class="text-center" style="width: 12%;">Total Customer</th>
                        <th class="text-center" style="width: 12%;">Total Follow Up</th>
                        <th class="text-center" style="width: 10%;">Total Deal</th>
                        <th class="text-center" style="width: 10%;">Total Invoice</th>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin'): ?>
                        <th class="text-center" style="width: 6%;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($kpi_data) > 0): ?>
                        <?php foreach ($kpi_data as $sales): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="sales-avatar-badge">
                                            <?php echo strtoupper(substr($sales['nama_lengkap'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <a href="followup_report.php?tgl_mulai=&tgl_akhir=&sales_id=<?php echo htmlspecialchars($sales['id']); ?>&limit=100" class="text-decoration-none text-dark fw-bold hover-primary" style="font-family:'Plus Jakarta Sans', sans-serif;">
                                                <?php echo htmlspecialchars($sales['nama_lengkap']); ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:13px; color:#64748B;"><?php echo htmlspecialchars($sales['email']); ?></td>
                                <td class="text-center fw-semibold"><?php echo number_format($sales['total_customer']); ?></td>
                                <td class="text-center fw-semibold"><?php echo number_format($sales['total_follow_up']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success" style="font-size:12px; padding:6px 12px;"><?php echo number_format($sales['total_deal']); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info" style="font-size:12px; padding:6px 12px;"><?php echo number_format($sales['total_invoice']); ?></span>
                                </td>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin'): ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="sales_edit.php?id=<?php echo $sales['id']; ?>" class="action-btn-edit" title="Edit Sales"><i class="bi bi-pencil-fill"></i></a>
                                        <a href="sales_delete.php?id=<?php echo $sales['id']; ?>" class="action-btn-del" title="Hapus Sales" onclick="return confirm('Anda yakin ingin menghapus sales ini? Customer yang ditangani akan menjadi \'Belum Di-assign\'.')"><i class="bi bi-trash-fill"></i></a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center p-5 text-muted">Tidak ada data sales terdaftar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('kpiChart')) {
        const ctx = document.getElementById('kpiChart').getContext('2d');
        
        // Gradient Colors for Chart Bars
        const gradientAmber = ctx.createLinearGradient(0, 0, 0, 300);
        gradientAmber.addColorStop(0, '#F59E0B');
        gradientAmber.addColorStop(1, '#D97706');

        const gradientBlue = ctx.createLinearGradient(0, 0, 0, 300);
        gradientBlue.addColorStop(0, '#3B82F6');
        gradientBlue.addColorStop(1, '#1D4ED8');

        const gradientEmerald = ctx.createLinearGradient(0, 0, 0, 300);
        gradientEmerald.addColorStop(0, '#10B981');
        gradientEmerald.addColorStop(1, '#059669');

        const kpiChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Jumlah Customer',
                    data: <?php echo json_encode($chart_customer); ?>,
                    backgroundColor: gradientAmber,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.55
                }, {
                    label: 'Jumlah Invoice',
                    data: <?php echo json_encode($chart_invoices); ?>,
                    backgroundColor: gradientBlue,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.55
                }, {
                    label: 'Jumlah Deal',
                    data: <?php echo json_encode($chart_deals); ?>,
                    backgroundColor: gradientEmerald,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.55
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'top',
                        labels: {
                            font: { family: 'Plus Jakarta Sans', weight: '700', size: 12 },
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#0F172A',
                        titleFont: { family: 'Plus Jakarta Sans', size: 13, weight: 'bold' },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 13 },
                        padding: 12,
                        cornerRadius: 12,
                        callbacks: {
                           label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.y !== null) { label += context.parsed.y; }
                                return label;
                           }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans', weight: '600', size: 12 }, color: '#475569' }
                    }, 
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#F1F5F9' },
                        ticks: { precision: 0, font: { family: 'Plus Jakarta Sans', size: 12 }, color: '#64748B' } 
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                }
            }
        });
    }
});
</script>