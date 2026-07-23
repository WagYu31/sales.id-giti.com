<?php
$page_title = 'Manajemen & KPI Sales';
require_once 'includes/db.php';
require_once 'includes/header.php';

// --- BAGIAN PENGAMBILAN DATA UNTUK KPI ---
// Query diperbarui untuk menambahkan total_invoice
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
$chart_follow_ups = [];
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
    .kpi-card { transition: all 0.2s ease-in-out; }
    .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15)!important; }
    .table-hover tbody tr { transition: background-color 0.2s ease; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-bar-chart-line-fill"></i> Sales Performance</h1>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card kpi-card shadow-sm h-100"><div class="card-body d-flex align-items-center"><div class="fs-1 text-primary me-3"><i class="bi bi-people-fill"></i></div><div><h6 class="text-muted mb-1">Total Sales Aktif</h6><h4 class="fw-bold mb-0"><?php echo $total_sales; ?></h4></div></div></div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card kpi-card shadow-sm h-100"><div class="card-body d-flex align-items-center"><div class="fs-1 text-info me-3"><i class="bi bi-person-check-fill"></i></div><div><h6 class="text-muted mb-1">Total Customer</h6><h4 class="fw-bold mb-0"><?php echo $grand_total_customer; ?></h4></div></div></div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card kpi-card shadow-sm h-100"><div class="card-body d-flex align-items-center"><div class="fs-1 text-warning me-3"><i class="bi bi-telephone-outbound-fill"></i></div><div><h6 class="text-muted mb-1">Total Follow Up</h6><h4 class="fw-bold mb-0"><?php echo $grand_total_follow_up; ?></h4></div></div></div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card kpi-card shadow-sm h-100"><div class="card-body d-flex align-items-center"><div class="fs-1 text-success me-3"><i class="bi bi-patch-check-fill"></i></div><div><h6 class="text-muted mb-1">Total Deal & Invoice</h6><h4 class="fw-bold mb-0"><?php echo $grand_total_deal; ?> / <?php echo $grand_total_invoice; ?></h4></div></div></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-graph-up"></i> Diagram Performa Sales</div>
    <div class="card-body">
        <div class="chart-container" style="position: relative; height:45vh; max-width: 1000px; margin: auto;">
            <canvas id="kpiChart"></canvas>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-5 mb-3">
    <h1><i class="bi bi-person-lines-fill"></i> Kelola Data Sales</h1>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin'): ?>
    <a href="sales_add.php" class="btn btn-primary"><i class="bi bi-person-plus-fill"></i> Tambah Sales</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover sortable-table">
                <thead class="table-dark">
                    <tr>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th class="text-center">Total Customer</th>
                        <th class="text-center">Total Follow Up</th>
                        <th class="text-center">Total Deal</th>
                        <th class="text-center">Total Invoice</th>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin'): ?>
                        <th class="text-center">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kpi_data as $sales): ?>
                        <tr>
                            <td><a href="followup_report.php?tgl_mulai=&tgl_akhir=&sales_id=<?php echo htmlspecialchars($sales['id']); ?>&limit=100" style="text-decoration:none;"><?php echo htmlspecialchars($sales['nama_lengkap']); ?></a></td>
                            <td><?php echo htmlspecialchars($sales['email']); ?></td>
                            <td class="text-center"><?php echo $sales['total_customer']; ?></td>
                            <td class="text-center"><?php echo $sales['total_follow_up']; ?></td>
                            <td class="text-center fw-bold text-success"><?php echo $sales['total_deal']; ?></td>
                            <td class="text-center fw-bold text-primary"><?php echo $sales['total_invoice']; ?></td>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin'): ?>
                            <td class="text-center">
                                <a href="sales_edit.php?id=<?php echo $sales['id']; ?>" class="btn btn-sm btn-warning" title="Edit Sales"><i class="bi bi-pencil-square"></i></a>
                                <a href="sales_delete.php?id=<?php echo $sales['id']; ?>" class="btn btn-sm btn-danger" title="Hapus Sales" onclick="return confirm('Anda yakin ingin menghapus sales ini? Customer yang ditangani akan menjadi \'Belum Di-assign\'.')"><i class="bi bi-trash"></i></a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
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
        const kpiChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Jumlah Customer',
                    data: <?php echo json_encode($chart_customer); ?>,
                    backgroundColor: '#ffc107',
                    borderColor: '#ffc107',
                    borderWidth: 1,
                    barPercentage: 0.6 // Membuat bar lebih ramping
                }, {
                    label: 'Jumlah Invoice',
                    data: <?php echo json_encode($chart_invoices); ?>,
                    backgroundColor: '#0dcaf0',
                    borderColor: '#0dcaf0',
                    borderWidth: 1,
                    barPercentage: 0.6 // Membuat bar lebih ramping
                }, {
                    label: 'Jumlah Deal',
                    data: <?php echo json_encode($chart_deals); ?>,
                    backgroundColor: '#198754',
                    borderColor: '#198754',
                    borderWidth: 1,
                    barPercentage: 0.6 // Membuat bar lebih ramping
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: {
                        display: true,
                        text: 'Perbandingan Aktivitas Sales',
                        font: { size: 18 }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: { weight: 'bold' },
                        bodyFont: { size: 14 },
                        callbacks: {
                           label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y;
                                }
                                return label;
                           }
                        }
                    }
                },
                scales: {
                    // Hapus properti 'stacked: true' agar bar tidak menumpuk
                    x: {}, 
                    y: { 
                        beginAtZero: true, 
                        ticks: { precision: 0 } 
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                borderRadius: 5,
            }
        });
    }
});
</script>