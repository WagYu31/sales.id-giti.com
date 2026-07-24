<?php
$page_title = 'Dashboard Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

// Filter params
$filter_kota = trim($_GET['filter_kota'] ?? '');
$filter_kategori = trim($_GET['filter_kategori'] ?? '');
$filter_sales = intval($_GET['filter_sales'] ?? 0);

$sql_where_conditions = ["c.deleted_at IS NULL"];
$params = [];
$types = '';

if (isset($_SESSION['role']) && $_SESSION['role'] == 'sales') {
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
} elseif ($filter_sales > 0) {
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $filter_sales;
    $types .= 'i';
}

if (!empty($filter_kota)) {
    $sql_where_conditions[] = "c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND kota LIKE ?)";
    $params[] = "%" . $filter_kota . "%";
    $types .= 's';
}

if (!empty($filter_kategori)) {
    $sql_where_conditions[] = "c.kategori = ?";
    $params[] = $filter_kategori;
    $types .= 's';
}

$where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

$sql = "
    SELECT 
        c.id, c.tgl_input, c.nama_toko, c.deal, c.kandidat, c.sales_id, c.kategori,
        s.nama_lengkap AS nama_sales,
        GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') AS all_pics,
        GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') AS all_phones,
        GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') AS all_cities,
        MIN(ca.link_google_map) AS primary_map_link,
        COUNT(DISTINCT fu.id) AS fu_count
    FROM 
        customers c
    LEFT JOIN 
        sales s ON c.sales_id = s.id
    LEFT JOIN 
        customer_pics cp ON c.id = cp.customer_id AND cp.deleted_at IS NULL
    LEFT JOIN 
        customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
    LEFT JOIN
        follow_ups fu ON c.id = fu.customer_id AND fu.deleted_at IS NULL
    {$where_clause}
    GROUP BY 
        c.id
    ORDER BY 
        c.id DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Fetch list of distinct cities (Cached in Session)
$cities = [];
if (!isset($_SESSION['cities_cache']) || isset($_GET['refresh_filter'])) {
    $r_city = $conn->query("SELECT DISTINCT TRIM(kota) AS nama_kota FROM customer_addresses WHERE deleted_at IS NULL AND kota IS NOT NULL AND TRIM(kota) != '' ORDER BY TRIM(kota) ASC");
    if ($r_city) {
        while($row = $r_city->fetch_assoc()) {
            $cities[] = $row['nama_kota'];
        }
    }
    $_SESSION['cities_cache'] = $cities;
} else {
    $cities = $_SESSION['cities_cache'];
}

// Fetch list of distinct categories (Cached in Session)
$categories = [];
if (!isset($_SESSION['categories_cache']) || isset($_GET['refresh_filter'])) {
    $r_cat = $conn->query("SELECT DISTINCT TRIM(kategori) AS nama_kategori FROM customers WHERE deleted_at IS NULL AND kategori IS NOT NULL AND TRIM(kategori) != '' ORDER BY TRIM(kategori) ASC");
    if ($r_cat) {
        while($row = $r_cat->fetch_assoc()) {
            $categories[] = $row['nama_kategori'];
        }
    }
    $_SESSION['categories_cache'] = $categories;
} else {
    $categories = $_SESSION['categories_cache'];
}

// Fetch list of sales for filter
$all_sales = [];
if ($_SESSION['role'] !== 'sales') {
    $r_sales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' ORDER BY nama_lengkap ASC");
    if ($r_sales) {
        while($row = $r_sales->fetch_assoc()) {
            $all_sales[] = $row;
        }
    }
}
?>

<style>
.cust-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 24px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.cust-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.cust-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.cust-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.sales-avatar-badge-small {
    width: 26px; height: 26px;
    border-radius: 8px;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #FFF;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    margin-right: 8px;
}

.filter-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
}
</style>

<!-- Hero Header -->
<div class="cust-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Daftar Customer</span>
            </div>
            <h1 class="cust-hero-title">Daftar Customer 👥</h1>
            <p class="cust-hero-subtitle">Kelola database seluruh customer, PIC kontak, status follow up, dan penugasan sales.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="customer_add.php" class="btn btn-primary shadow-lg fw-bold">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Customer Baru
            </a>
        </div>
    </div>
</div>

<!-- Filter Toolbar Card -->
<div class="filter-card">
    <form method="GET" action="index.php" class="row g-3 align-items-end">
        <!-- Filter Kota -->
        <div class="col-md-3 col-sm-6">
            <label for="filter_kota" class="form-label small text-muted fw-bold mb-1">
                <i class="bi bi-geo-alt-fill text-danger me-1"></i> Filter Kota
            </label>
            <select name="filter_kota" id="filter_kota" class="form-select fw-semibold" style="border-radius:10px;">
                <option value="">Semua Kota (<?php echo count($cities); ?> Kota)</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?php echo htmlspecialchars($city); ?>" <?php if ($filter_kota === $city) echo 'selected'; ?>>
                        📍 <?php echo htmlspecialchars($city); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter Kategori -->
        <div class="col-md-3 col-sm-6">
            <label for="filter_kategori" class="form-label small text-muted fw-bold mb-1">
                <i class="bi bi-tags-fill text-primary me-1"></i> Filter Kategori
            </label>
            <select name="filter_kategori" id="filter_kategori" class="form-select fw-semibold" style="border-radius:10px;">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($filter_kategori === $cat) echo 'selected'; ?>>
                        🏷️ <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter Sales (Superadmin/Adminsales only) -->
        <?php if ($_SESSION['role'] !== 'sales'): ?>
        <div class="col-md-3 col-sm-6">
            <label for="filter_sales" class="form-label small text-muted fw-bold mb-1">
                <i class="bi bi-person-badge-fill text-info me-1"></i> Filter Sales
            </label>
            <select name="filter_sales" id="filter_sales" class="form-select fw-semibold" style="border-radius:10px;">
                <option value="0">Semua Sales</option>
                <?php foreach ($all_sales as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php if ($filter_sales === intval($s['id'])) echo 'selected'; ?>>
                        👤 <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="<?php echo ($_SESSION['role'] !== 'sales') ? 'col-md-3' : 'col-md-6'; ?> col-sm-6 d-flex gap-2">
            <button type="submit" class="btn btn-primary fw-bold flex-grow-1" style="border-radius:10px;">
                <i class="bi bi-funnel-fill me-1"></i> Terapkan Filter
            </button>
            <?php if (!empty($filter_kota) || !empty($filter_kategori) || $filter_sales > 0): ?>
                <a href="index.php" class="btn btn-light border fw-bold" title="Reset Filter" style="border-radius:10px;">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div id="notification" class="alert" style="display:none;"></div>

<div class="card border-0 shadow-sm" style="border-radius:20px;">
    <div class="card-body p-0">
        <div class="table-responsive" id="customer-table-container">
            <table class="table table-hover align-middle sortable-table mb-0">
                <thead class="table-dark-header">
                    <tr>
                        <th style="width: 18%;">NAMA TOKO</th>
                        <th style="width: 20%;">PIC & KONTAK</th>
                        <th style="width: 10%;">KATEGORI</th>
                        <th style="width: 12%;">KOTA</th>
                        <th style="width: 14%;">SALES</th>
                        <th class="text-center" style="width: 7%;">FU</th>
                        <th class="text-center" style="width: 6%;">KANDIDAT</th>
                        <th class="text-center" style="width: 6%;">DEAL</th>
                        <th class="text-center" style="width: 4%;">MAPS</th>
                        <th class="text-center" style="width: 8%;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr id="customer-row-<?php echo $customer['id']; ?>">
                            <td>
                                <div class="fw-bold text-dark" style="font-family:'Plus Jakarta Sans', sans-serif;">
                                    <i class="bi bi-shop text-primary me-1"></i>
                                    <?php echo htmlspecialchars($customer['nama_toko']); ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $pics = !empty($customer['all_pics']) ? explode('||', $customer['all_pics']) : [];
                                $phones = !empty($customer['all_phones']) ? explode('||', $customer['all_phones']) : [];
                                if (!empty($pics)) {
                                    foreach ($pics as $key => $pic_name) {
                                        $phone_number = $phones[$key] ?? '';
                                        echo '<div class="small fw-semibold text-dark"><i class="bi bi-person-fill text-muted me-1"></i>' . htmlspecialchars($pic_name);
                                        if (!empty($phone_number)) {
                                            $cleaned_tel = preg_replace('/[^0-9]/', '', $phone_number);
                                            $wa_number = (substr($cleaned_tel, 0, 1) === '0') ? '62' . substr($cleaned_tel, 1) : $cleaned_tel;
                                            echo ' <a href="https://wa.me/' . $wa_number . '" target="_blank" class="badge bg-light text-success border text-decoration-none ms-1"><i class="bi bi-whatsapp me-1"></i>' . htmlspecialchars($phone_number) . '</a>';
                                        }
                                        echo '</div>';
                                    }
                                } else { echo '<span class="text-muted small">-</span>'; }
                                ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-semibold"><?php echo htmlspecialchars($customer['kategori'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold" style="font-size:11px;">
                                    <i class="bi bi-geo-alt-fill me-1"></i><?php echo htmlspecialchars($customer['all_cities'] ?? '-'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($customer['nama_sales']): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="sales-avatar-badge-small">
                                            <?php echo strtoupper(substr($customer['nama_sales'], 0, 1)); ?>
                                        </div>
                                        <span class="fw-semibold text-dark" style="font-size:12.5px;"><?php echo htmlspecialchars($customer['nama_sales']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>Belum Di-assign</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold">
                                <span class="badge bg-primary rounded-pill px-2 py-1"><?php echo $customer['fu_count']; ?></span>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="kandidat" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['kandidat'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <td class="text-center">
                               <div class="form-check form-switch d-flex justify-content-center"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="deal" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['deal'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($customer['primary_map_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($customer['primary_map_link']); ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Buka di Google Maps"><i class="bi bi-geo-alt-fill"></i></a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-geo-alt"></i></button>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary" title="Lihat Follow Up"><i class="bi bi-eye-fill"></i></a>
                                    <?php 
                                    $can_edit_delete = ($_SESSION['role'] == 'superadmin') || ($_SESSION['role'] == 'sales' && $_SESSION['user_id'] == $customer['sales_id']);
                                    if ($can_edit_delete): 
                                    ?>
                                        <a href="customer_edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Customer"><i class="bi bi-pencil-fill"></i></a>
                                        <a href="customer_delete.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-danger" title="Hapus Customer" onclick="return confirm('Yakin hapus customer ini?')"><i class="bi bi-trash-fill"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center p-5 text-muted">Belum ada data customer yang sesuai dengan filter ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('.sortable-table tbody');
    const notification = document.getElementById('notification');

    function showNotification(message, isSuccess) {
        notification.textContent = message;
        notification.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        notification.style.display = 'block';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }
    
    tableBody.addEventListener('change', function(event) {
        if (event.target.classList.contains('status-checkbox')) {
            const checkbox = event.target;
            const customerId = checkbox.dataset.customerId;
            const statusType = checkbox.dataset.type;
            const newStatus = checkbox.checked ? 'Y' : 'N';
            
            fetch('update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    'customer_id': customerId,
                    'status_type': statusType,
                    'status_value': newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, true);
                } else {
                    showNotification(data.message, false);
                    checkbox.checked = !checkbox.checked;
                }
            })
            .catch(error => {
                showNotification('Terjadi kesalahan jaringan.', false);
                checkbox.checked = !checkbox.checked;
            });
        }
    });
});
</script>