<?php
$page_title = 'Kandidat Potensial Customer';
require_once 'includes/db.php';
require_once 'includes/header.php';

$filter = $_GET['filter'] ?? 'kandidat';
$allowed_filters = ['kandidat', 'potensial', 'acc_boss'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'kandidat';
}

$search_keyword = trim($_GET['search'] ?? '');
$filter_kota = trim($_GET['filter_kota'] ?? '');
$filter_kategori = trim($_GET['filter_kategori'] ?? '');
$filter_sales = intval($_GET['filter_sales'] ?? 0);
$filter_fu = trim($_GET['filter_fu'] ?? '');

$sql_where_conditions = ["c.deleted_at IS NULL", "c.kandidat = 'Y'"];
$params = [];
$types = '';

if ($filter === 'potensial') {
    $sql_where_conditions[] = "c.potensial = 'Y'";
    $sql_where_conditions[] = "(c.acc_boss = 'N' OR c.acc_boss IS NULL)";
} elseif ($filter === 'acc_boss') {
    $sql_where_conditions[] = "c.potensial = 'Y'";
    $sql_where_conditions[] = "c.acc_boss = 'Y'";
} else {
    $sql_where_conditions[] = "(c.potensial = 'N' OR c.potensial IS NULL)";
    $sql_where_conditions[] = "(c.acc_boss = 'N' OR c.acc_boss IS NULL)";
}

$active_sales_id = 0;
if (isset($_SESSION['role']) && $_SESSION['role'] == 'sales') {
    $active_sales_id = (int)$_SESSION['user_id'];
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
} elseif ($filter_sales > 0) {
    $active_sales_id = $filter_sales;
    $sql_where_conditions[] = "c.sales_id = ?";
    $params[] = $filter_sales;
    $types .= 'i';
}

if (!empty($search_keyword)) {
    $sql_where_conditions[] = "(c.nama_toko LIKE ? OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?)))";
    $like_kw = '%' . $search_keyword . '%';
    array_push($params, $like_kw, $like_kw, $like_kw);
    $types .= 'sss';
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

if ($filter_fu === 'sudah') {
    if ($active_sales_id > 0) {
        $sql_where_conditions[] = "c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = ? AND deleted_at IS NULL)";
        $params[] = $active_sales_id;
        $types .= 'i';
    } else {
        $sql_where_conditions[] = "c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE deleted_at IS NULL)";
    }
} elseif ($filter_fu === 'belum') {
    if ($active_sales_id > 0) {
        $sql_where_conditions[] = "c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = ? AND deleted_at IS NULL)";
        $params[] = $active_sales_id;
        $types .= 'i';
    } else {
        $sql_where_conditions[] = "c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE deleted_at IS NULL)";
    }
}

$where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

$sql = "
    SELECT 
        c.id, c.nama_toko, c.kandidat, c.potensial, c.acc_boss, c.acc_boss_note, c.kategori,
        s.nama_lengkap AS nama_sales,
        GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') AS all_pics,
        GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') AS all_phones,
        GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') AS all_cities,
        (SELECT ca_inner.link_google_map FROM customer_addresses ca_inner WHERE ca_inner.customer_id = c.id AND ca_inner.deleted_at IS NULL ORDER BY ca_inner.id LIMIT 1) AS primary_map_link
    FROM customers c
    LEFT JOIN sales s ON c.sales_id = s.id
    LEFT JOIN customer_pics cp ON c.id = cp.customer_id AND cp.deleted_at IS NULL
    LEFT JOIN customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
    {$where_clause}
    GROUP BY c.id
    ORDER BY c.tgl_input DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$customers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$is_superadmin = ($_SESSION['role'] === 'superadmin');

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

// Fetch list of distinct categories
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

// Fetch list of sales for filter dropdown
$all_sales = [];
if ($_SESSION['role'] !== 'sales') {
    $r_sales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE status = 'aktif' ORDER BY nama_lengkap ASC");
    if ($r_sales) {
        while($row = $r_sales->fetch_assoc()) {
            $all_sales[] = $row;
        }
    }
}
?>

<style>
.kandidat-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.kandidat-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.kandidat-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.kandidat-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.nav-pills-custom .nav-link {
    border-radius: 12px;
    padding: 10px 22px;
    font-size: 14px;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: #475569;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    transition: all 0.25s ease;
}

.nav-pills-custom .nav-link:hover {
    color: #2563EB;
    border-color: #BFDBFE;
    background: #EFF6FF;
}

.nav-pills-custom .nav-link.active {
    background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%) !important;
    color: #FFFFFF !important;
    border-color: #2563EB !important;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35) !important;
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
</style>

<!-- Hero Header -->
<div class="kandidat-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Kandidat Potensial</span>
            </div>
            <h1 class="kandidat-hero-title">Kandidat Potensial Customer ⭐</h1>
            <p class="kandidat-hero-subtitle">Filter & klasifikasi customer prospek tinggi, kandidat utama, serta verifikasi Acc Boss.</p>
        </div>
    </div>
</div>

<!-- Tab Filter Pills -->
<div class="d-flex align-items-center mb-4">
    <ul class="nav nav-pills nav-pills-custom gap-2">
        <li class="nav-item">
            <a class="nav-link <?php if ($filter === 'kandidat') echo 'active'; ?>" href="kandidat_customer.php?filter=kandidat">
                <i class="bi bi-star me-1"></i> Kandidat
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($filter === 'potensial') echo 'active'; ?>" href="kandidat_customer.php?filter=potensial">
                <i class="bi bi-graph-up-arrow me-1"></i> Potensial
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($filter === 'acc_boss') echo 'active'; ?>" href="kandidat_customer.php?filter=acc_boss">
                <i class="bi bi-shield-check me-1"></i> Acc Boss
            </a>
        </li>
    </ul>
</div>

<!-- Filter Suite Card -->
<div class="card mb-4 border-0 shadow-sm" style="border-radius:18px;">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="mb-0 text-dark fw-bold" style="font-size:15px;"><i class="bi bi-funnel-fill text-primary me-1"></i> Filter Data Customer</h5>
        <?php if (!empty($search_keyword) || !empty($filter_kota) || !empty($filter_kategori) || $filter_sales > 0 || !empty($filter_fu)): ?>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold" style="border-radius:8px;">Filter Aktif</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-4">
        <form action="" method="GET" id="kandidat-filter-form">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">

            <div class="row g-3 align-items-end">
                <!-- Filter Kata Kunci / Search -->
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="search" class="form-label fw-bold text-muted mb-1" style="font-size:11px; letter-spacing:0.5px; text-transform:uppercase;">
                        <i class="bi bi-search text-primary me-1"></i> Cari Kata Kunci / Toko
                    </label>
                    <input type="text" name="search" id="search" class="form-control fw-semibold" placeholder="Nama toko, PIC, atau hp..." value="<?php echo htmlspecialchars($search_keyword); ?>" style="border-radius:12px; height:42px;">
                </div>

                <!-- Filter Kota -->
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="filter_kota" class="form-label text-muted fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px; text-transform:uppercase;">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Filter Kota
                    </label>
                    <input type="text" name="filter_kota" id="filter_kota" class="form-control fw-semibold" list="kota_list" placeholder="Pilih atau ketik kota..." value="<?php echo htmlspecialchars($filter_kota); ?>" style="border-radius:12px; height:42px;">
                    <datalist id="kota_list">
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Filter Kategori -->
                <div class="col-lg-2 col-md-6 col-12">
                    <label for="filter_kategori" class="form-label text-muted fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px; text-transform:uppercase;">
                        <i class="bi bi-tags-fill text-primary me-1"></i> Filter Kategori
                    </label>
                    <select name="filter_kategori" id="filter_kategori" class="form-select fw-semibold" style="border-radius:12px; height:42px;">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($filter_kategori === $cat) echo 'selected'; ?>>
                                🏷️ <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Status Follow Up -->
                <div class="col-lg-2 col-md-6 col-12">
                    <label for="filter_fu" class="form-label text-muted fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px; text-transform:uppercase;">
                        <i class="bi bi-telephone-outbound-fill text-success me-1"></i> Status FU
                    </label>
                    <select name="filter_fu" id="filter_fu" class="form-select fw-semibold" style="border-radius:12px; height:42px;">
                        <option value="">Semua Status FU</option>
                        <option value="sudah" <?php if ($filter_fu === 'sudah') echo 'selected'; ?>>✅ Sudah FU</option>
                        <option value="belum" <?php if ($filter_fu === 'belum') echo 'selected'; ?>>⏳ Belum FU</option>
                    </select>
                </div>

                <!-- Filter Sales (Superadmin/Adminsales) -->
                <?php if ($_SESSION['role'] !== 'sales'): ?>
                <div class="col-lg-2 col-md-6 col-12">
                    <label for="filter_sales" class="form-label text-muted fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px; text-transform:uppercase;">
                        <i class="bi bi-person-badge-fill text-info me-1"></i> Filter Sales
                    </label>
                    <select name="filter_sales" id="filter_sales" class="form-select fw-semibold" style="border-radius:12px; height:42px;">
                        <option value="">Semua Sales</option>
                        <?php foreach ($all_sales as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php if ($filter_sales === intval($s['id'])) echo 'selected'; ?>>
                                👤 <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="<?php echo ($_SESSION['role'] !== 'sales') ? 'col-lg-12 col-md-12' : 'col-lg-2 col-md-6'; ?> col-12 d-flex gap-2 justify-content-end">
                    <button type="submit" class="btn btn-primary fw-extrabold flex-grow-1 shadow-sm d-inline-flex align-items-center justify-content-center gap-1.5" style="height:42px; border-radius:12px; white-space:nowrap; background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);">
                        <i class="bi bi-funnel-fill"></i> Terapkan Filter
                    </button>
                    <?php if (!empty($search_keyword) || !empty($filter_kota) || !empty($filter_kategori) || $filter_sales > 0 || !empty($filter_fu)): ?>
                        <a href="kandidat_customer.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-light border border-slate fw-bold d-inline-flex align-items-center justify-content-center gap-1" title="Reset Filter" style="height:42px; padding:0 16px; border-radius:12px; white-space:nowrap;">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="notification" class="alert" style="display:none;"></div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark-header">
                    <tr>
                        <th style="width: 25%;">NAMA TOKO</th>
                        <th style="width: 25%;">PIC & KONTAK</th>
                        <th style="width: 12%;">KATEGORI</th>
                        <th style="width: 12%;">KOTA</th>
                        <th style="width: 14%;">SALES</th>
                        <th class="text-center" style="width: 4%;">MAPS</th>
                        <?php if ($filter === 'kandidat'): ?>
                            <th class="text-center" style="width: 8%;">KANDIDAT</th>
                        <?php endif; ?>
                        <?php if ($filter !== 'acc_boss'): ?>
                             <th class="text-center" style="width: 8%;">POTENSIAL</th>
                        <?php endif; ?>
                         <?php if ($filter === 'potensial' || $filter === 'acc_boss'): ?>
                            <th class="text-center" style="width: 8%;">ACC BOSS</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr id="customer-row-<?php echo $customer['id']; ?>">
                            <td>
                                <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" class="fw-bold text-dark text-decoration-none" style="font-family:'Plus Jakarta Sans', sans-serif;">
                                    <i class="bi bi-shop text-primary me-1"></i>
                                    <?php echo htmlspecialchars($customer['nama_toko']); ?>
                                </a>
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
                            <td><span class="badge bg-light text-dark border fw-semibold"><?php echo htmlspecialchars($customer['kategori'] ?? '-'); ?></span></td>
                            <td class="small fw-semibold text-muted"><?php echo htmlspecialchars($customer['all_cities'] ?? '-'); ?></td>
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
                            <td class="text-center">
                                <?php if (!empty($customer['primary_map_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($customer['primary_map_link']); ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Buka di Google Maps"><i class="bi bi-geo-alt-fill"></i></a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-geo-alt"></i></button>
                                <?php endif; ?>
                            </td>
                            <?php if ($filter === 'kandidat'): ?>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="kandidat" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['kandidat'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <?php endif; ?>
                             <?php if ($filter !== 'acc_boss'): ?>
                             <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="potensial" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['potensial'] == 'Y') echo 'checked'; ?>></div>
                            </td>
                            <?php endif; ?>
                             <?php if ($filter === 'potensial' || $filter === 'acc_boss'): ?>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center align-items-center">
                                    <input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="acc_boss" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['acc_boss'] == 'Y') echo 'checked'; ?> <?php if (!$is_superadmin) echo 'disabled'; ?>>
                                    <?php if (!empty($customer['acc_boss_note'])): ?>
                                        <i class="bi bi-info-circle-fill ms-2 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($customer['acc_boss_note']); ?>"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center p-5 text-muted">Tidak ada data customer yang cocok dengan filter ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('tbody');
    const notification = document.getElementById('notification');
    const isSuperAdmin = <?php echo $is_superadmin ? 'true' : 'false'; ?>;

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    function showNotification(message, isSuccess) {
        notification.textContent = message;
        notification.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        notification.style.display = 'block';
        setTimeout(() => { notification.style.display = 'none'; }, 3000);
    }
    
    tableBody.addEventListener('change', function(event) {
        if (event.target.classList.contains('status-checkbox')) {
            const checkbox = event.target;
            const customerId = checkbox.dataset.customerId;
            const statusType = checkbox.dataset.type;
            const newStatus = checkbox.checked ? 'Y' : 'N';

            if (statusType === 'acc_boss' && newStatus === 'Y' && isSuperAdmin) {
                Swal.fire({
                    title: 'Tambahkan Catatan (Opsional)',
                    input: 'textarea',
                    inputPlaceholder: 'Masukkan catatan untuk sales...',
                    showCancelButton: true,
                    confirmButtonText: 'Simpan',
                    cancelButtonText: 'Batal',
                    preConfirm: (note) => {
                        updateStatus(customerId, statusType, newStatus, note || null); 
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                     if (!result.isConfirmed && result.dismiss !== Swal.DismissReason.cancel) {
                         checkbox.checked = !checkbox.checked;
                     } else if (result.dismiss === Swal.DismissReason.cancel) {
                          checkbox.checked = !checkbox.checked;
                     }
                });
            } else {
                 updateStatus(customerId, statusType, newStatus);
            }
        }
    });

    function updateStatus(customerId, statusType, newStatus, note = null) {
        const bodyParams = {
            'customer_id': customerId,
            'status_type': statusType,
            'status_value': newStatus
        };
        if (note !== null) {
            bodyParams['acc_boss_note'] = note;
        }

        fetch('update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(bodyParams)
        })
        .then(response => {
            if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification(data.message, true);
                setTimeout(() => {
                    const row = document.getElementById('customer-row-' + customerId);
                    if (row) {
                        row.style.transition = 'opacity 0.5s ease';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 500);
                    } else {
                         window.location.reload();
                    }
                }, 1000);
            } else {
                showNotification(data.message || 'Gagal memperbarui status.', false);
                const checkbox = document.querySelector(`input[data-customer-id="${customerId}"][data-type="${statusType}"]`);
                if (checkbox) checkbox.checked = !checkbox.checked;
            }
        })
        .catch(error => {
            showNotification('Terjadi kesalahan jaringan.', false);
            const checkbox = document.querySelector(`input[data-customer-id="${customerId}"][data-type="${statusType}"]`);
            if (checkbox) checkbox.checked = !checkbox.checked;
            console.error('Fetch error:', error);
        });
    }
});
</script>