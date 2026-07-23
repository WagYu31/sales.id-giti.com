<?php
$page_title = "Laporan Status Kandidat";
require_once 'includes/db.php';
require_once 'includes/header.php';

function get_file_icon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return 'bi-file-earmark-pdf-fill text-danger';
        case 'doc': case 'docx': return 'bi-file-earmark-word-fill text-primary';
        case 'xls': case 'xlsx': return 'bi-file-earmark-excel-fill text-success';
        case 'jpg': case 'jpeg': case 'png': case 'gif': case 'webp': return 'bi-file-earmark-image-fill text-info';
        case 'mp4': case 'webm': case 'mkv': case 'mov': return 'bi-file-earmark-play-fill text-warning';
        case 'mp3': case 'wav': case 'ogg': case 'm4a': case 'aac': return 'bi-file-earmark-music-fill text-secondary';
        default: return 'bi-file-earmark-fill';
    }
}

$allowed_sort_columns = [
    'tgl_terbaru' => 'COALESCE(latest_fu.tgl_follow_up, c.tgl_input)',
    'nama_toko' => 'c.nama_toko',
    'nama_sales' => 's.nama_lengkap',
    'kota' => 'all_cities'
];

$filter = $_GET['filter'] ?? 'kandidat';
$allowed_filters = ['kandidat', 'potensial', 'lost_deal', 'acc_boss'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'kandidat';
}

$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$selected_sales_id = isset($_GET['sales_id']) && is_numeric($_GET['sales_id']) ? (int)$_GET['sales_id'] : '';
$limit = $_GET['limit'] ?? 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$sort_by = isset($_GET['sort_by']) && array_key_exists($_GET['sort_by'], $allowed_sort_columns) ? $_GET['sort_by'] : 'tgl_terbaru';
$sort_dir = isset($_GET['sort_dir']) && in_array(strtoupper($_GET['sort_dir']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_dir']) : 'DESC';

$sales_list_result = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL ORDER BY nama_lengkap ASC");

$base_query = "FROM customers c
               LEFT JOIN sales s ON c.sales_id = s.id
               LEFT JOIN customer_pics cp ON c.id = cp.customer_id AND cp.deleted_at IS NULL
               LEFT JOIN customer_addresses ca ON c.id = ca.customer_id AND ca.deleted_at IS NULL
               LEFT JOIN follow_ups latest_fu ON latest_fu.id = (
                    SELECT id FROM follow_ups fu2 
                    WHERE fu2.customer_id = c.id 
                    AND fu2.deleted_at IS NULL 
                    ORDER BY fu2.tgl_follow_up DESC, fu2.id DESC 
                    LIMIT 1
               )";

$conditions = ["c.deleted_at IS NULL"];
$params = [];
$types = '';

if ($filter === 'potensial') {
    $conditions[] = "c.kandidat = 'Y'";
    $conditions[] = "c.potensial = 'Y'";
    $conditions[] = "(c.acc_boss = 'N' OR c.acc_boss IS NULL)";
    $conditions[] = "(c.lost_deal = 'N' OR c.lost_deal IS NULL)";
} elseif ($filter === 'lost_deal') {
    $conditions[] = "c.kandidat = 'Y'";
    $conditions[] = "c.lost_deal = 'Y'";
    $conditions[] = "(c.acc_boss = 'N' OR c.acc_boss IS NULL)";
} elseif ($filter === 'acc_boss') {
    $conditions[] = "c.kandidat = 'Y'";
    $conditions[] = "c.potensial = 'Y'";
    $conditions[] = "c.acc_boss = 'Y'";
} else {
    $conditions[] = "c.kandidat = 'Y'";
    $conditions[] = "(c.potensial = 'N' OR c.potensial IS NULL)";
    $conditions[] = "(c.acc_boss = 'N' OR c.acc_boss IS NULL)";
    $conditions[] = "(c.lost_deal = 'N' OR c.lost_deal IS NULL)";
}

if ($tgl_mulai && $tgl_akhir) {
    $conditions[] = "DATE(COALESCE(latest_fu.tgl_follow_up, c.tgl_input)) BETWEEN ? AND ?";
    array_push($params, $tgl_mulai, $tgl_akhir);
    $types .= 'ss';
}

if ($selected_sales_id) {
    $conditions[] = "c.sales_id = ?";
    $params[] = $selected_sales_id;
    $types .= 'i';
}

if (isset($_SESSION['role']) && $_SESSION['role'] == 'sales') {
    $conditions[] = "c.sales_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$where_clause = " WHERE " . implode(' AND ', $conditions);
$group_clause = " GROUP BY c.id";

$count_sql = "SELECT COUNT(DISTINCT c.id) as total " . $base_query . $where_clause;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$offset = ($page - 1) * $limit;
$total_pages = ($limit == 'all') ? 1 : ceil($total_records / $limit);
$page = max(1, min($page, $total_pages));

$order_by_clause = " ORDER BY " . $allowed_sort_columns[$sort_by] . " " . $sort_dir;

$data_sql = "SELECT 
                c.id, c.nama_toko, c.kandidat, c.potensial, c.acc_boss, c.acc_boss_note, c.lost_deal, c.kategori, c.tgl_input,
                s.nama_lengkap AS nama_sales,
                GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') AS all_pics,
                GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') AS all_phones,
                GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') AS all_cities,
                (SELECT ca_inner.link_google_map FROM customer_addresses ca_inner WHERE ca_inner.customer_id = c.id AND ca_inner.deleted_at IS NULL ORDER BY ca_inner.id LIMIT 1) AS primary_map_link,
                latest_fu.tgl_follow_up as last_fu_date,
                latest_fu.respon as last_respon,
                latest_fu.media1, latest_fu.media2, latest_fu.media3
             " . $base_query . $where_clause . $group_clause . $order_by_clause;

if ($limit !== 'all') {
    $data_sql .= " LIMIT ?, ?";
    array_push($params, $offset, (int)$limit);
    $types .= 'ii';
}

$stmt = $conn->prepare($data_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers_result = $stmt->get_result();

$base_link_params = [
    'filter' => $filter,
    'tgl_mulai' => $tgl_mulai,
    'tgl_akhir' => $tgl_akhir,
    'sales_id' => $selected_sales_id,
    'limit' => $limit
];

function create_sort_link($column_name, $display_text, $current_sort_by, $current_sort_dir, $base_params) {
    $next_sort_dir = ($current_sort_by == $column_name && $current_sort_dir == 'ASC') ? 'DESC' : 'ASC';
    $link_params = array_merge($base_params, ['sort_by' => $column_name, 'sort_dir' => $next_sort_dir]);
    $icon = '';
    if ($current_sort_by == $column_name) {
        $icon = $current_sort_dir == 'ASC' ? '<i class="bi bi-sort-up-alt ms-1"></i>' : '<i class="bi bi-sort-down ms-1"></i>';
    }
    return '<a href="?' . http_build_query($link_params) . '">' . $display_text . $icon . '</a>';
}

$is_superadmin = ($_SESSION['role'] === 'superadmin');
?>

<div class="card mb-4 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-funnel-fill"></i> Filter Status Kandidat</h5>
    </div>
    <div class="card-body">
        <form action="" method="GET" id="filter-form">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="tgl_mulai" class="form-label">Update Terakhir Dari</label>
                    <input type="date" class="form-control" id="tgl_mulai" name="tgl_mulai" value="<?php echo htmlspecialchars($tgl_mulai); ?>">
                </div>
                <div class="col-md-3">
                    <label for="tgl_akhir" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" value="<?php echo htmlspecialchars($tgl_akhir); ?>">
                </div>
                <div class="col-md-3">
                    <label for="sales_id" class="form-label">Pilih Sales</label>
                    <select id="sales_id" name="sales_id" class="form-select">
                        <option value="">Semua Sales</option>
                        <?php mysqli_data_seek($sales_list_result, 0); ?>
                        <?php while($sales = $sales_list_result->fetch_assoc()): ?>
                            <option value="<?php echo $sales['id']; ?>" <?php if ($selected_sales_id == $sales['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($sales['nama_lengkap']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="kandidat_report_view.php?filter=<?php echo $filter; ?>" class="btn btn-link text-decoration-none text-center d-block mt-1">Reset</a>
                </div>
            </div>
             <input type="hidden" name="limit" value="<?php echo htmlspecialchars($limit); ?>">
        </form>
    </div>
</div>

<ul class="nav nav-pills nav-justified mb-3 border">
  <li class="nav-item">
    <a class="nav-link <?php if ($filter === 'kandidat') echo 'active'; ?>" href="?filter=kandidat">Kandidat</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php if ($filter === 'potensial') echo 'active'; ?>" href="?filter=potensial">Potensial</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php if ($filter === 'lost_deal') echo 'active'; ?>" href="?filter=lost_deal">Lost Deal</a>
  </li>
   <li class="nav-item">
    <a class="nav-link <?php if ($filter === 'acc_boss') echo 'active'; ?>" href="?filter=acc_boss">Deal</a>
  </li>
</ul>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center">
        <label for="limit-select" class="form-label me-2 mb-0">Tampilkan:</label>
        <select id="limit-select" class="form-select form-select-sm" style="width: auto;">
            <option value="20" <?php if ($limit == '20') echo 'selected'; ?>>20</option>
            <option value="40" <?php if ($limit == '40') echo 'selected'; ?>>40</option>
            <option value="60" <?php if ($limit == '60') echo 'selected'; ?>>60</option>
            <option value="80" <?php if ($limit == '80') echo 'selected'; ?>>80</option>
            <option value="100" <?php if ($limit == '100') echo 'selected'; ?>>100</option>
        </select>
    </div>
    <div class="text-muted">
        <?php if ($limit != 'all' && $total_records > 0): ?>
            Menampilkan data <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> data
        <?php elseif ($total_records > 0): ?>
             Menampilkan semua <?php echo $total_records; ?> data
        <?php endif; ?>
    </div>
</div>

<div id="notification" class="alert" style="display:none;"></div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                 <thead class="table-light">
                    <tr>
                        <th style="width: 10%;"><?php echo create_sort_link('tgl_terbaru', 'Update Terakhir', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 15%;"><?php echo create_sort_link('nama_toko', 'Nama Toko', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 20%;">PIC & Kontak</th>
                        <th style="width: 10%;"><?php echo create_sort_link('kota', 'Kota', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 10%;"><?php echo create_sort_link('nama_sales', 'Sales', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 15%;">Media Terbaru</th>
                        <th style="width: 5%;">Maps</th>
                        <th style="width: 15%;">Status Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers_result->num_rows > 0): ?>
                        <?php while($cust = $customers_result->fetch_assoc()): ?>
                            <tr id="customer-row-<?php echo $cust['id']; ?>">
                                <td class="text-nowrap small text-muted">
                                    <?php 
                                        if (!empty($cust['last_fu_date'])) {
                                            echo '<span class="text-primary fw-bold">' . date('d M Y', strtotime($cust['last_fu_date'])) . '</span>';
                                        } else {
                                            echo date('d M Y', strtotime($cust['tgl_input']));
                                        }
                                    ?>
                                </td>
                                <td>
                                    <a href="followup_view.php?customer_id=<?php echo $cust['id']; ?>" class="fw-bold text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($cust['nama_toko']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($cust['kategori'] ?? '-'); ?></small>
                                    <?php if(!empty($cust['last_respon'])): ?>
                                        <div class="mt-1 badge bg-light text-dark border"><?php echo htmlspecialchars($cust['last_respon']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php
                                    $pics = !empty($cust['all_pics']) ? explode('||', $cust['all_pics']) : [];
                                    $phones = !empty($cust['all_phones']) ? explode('||', $cust['all_phones']) : [];
                                    if (!empty($pics)) {
                                        foreach ($pics as $key => $pic_name) {
                                            $phone_number = $phones[$key] ?? '';
                                            echo '<div class="mb-1"><i class="bi bi-person"></i> ' . htmlspecialchars($pic_name);
                                            if (!empty($phone_number)) {
                                                $cleaned_tel = preg_replace('/[^0-9]/', '', $phone_number);
                                                $wa_number = (substr($cleaned_tel, 0, 1) === '0') ? '62' . substr($cleaned_tel, 1) : $cleaned_tel;
                                                echo ' <a href="https://wa.me/' . $wa_number . '" target="_blank" class="text-success text-decoration-none"><i class="bi bi-whatsapp"></i></a>';
                                            }
                                            echo '</div>';
                                        }
                                    } else { echo '-'; }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($cust['all_cities'] ?? '-'); ?></td>
                                <td><?php echo $cust['nama_sales'] ? htmlspecialchars($cust['nama_sales']) : '<span class="badge bg-warning text-dark">Unassigned</span>'; ?></td>
                                <td>
                                    <?php 
                                    $media_found = false;
                                    for ($i = 1; $i <= 3; $i++) {
                                        $media_file = $cust['media'.$i];
                                        if ($media_file) {
                                            $media_found = true;
                                            ?>
                                            <a href="#" class="btn btn-outline-secondary btn-sm mb-1 me-1 text-truncate" style="max-width: 100px;" data-bs-toggle="modal" data-bs-target="#mediaModal" data-file-url="assets/uploads/<?php echo htmlspecialchars($media_file); ?>" data-file-name="<?php echo htmlspecialchars($media_file); ?>">
                                                <i class="bi <?php echo get_file_icon($media_file); ?>"></i>
                                            </a>
                                            <?php
                                        }
                                    }
                                    if (!$media_found) echo '<span class="text-muted small">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($cust['primary_map_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($cust['primary_map_link']); ?>" target="_blank" class="btn btn-sm btn-light text-success border" title="Google Maps"><i class="bi bi-geo-alt-fill"></i></a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light border" disabled><i class="bi bi-geo-alt"></i></button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($filter === 'kandidat'): ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="kandidat" data-customer-id="<?php echo $cust['id']; ?>" <?php if ($cust['kandidat'] == 'Y') echo 'checked'; ?>>
                                            <label class="form-check-label small">Kandidat</label>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($filter === 'kandidat' || $filter === 'potensial'): ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="potensial" data-customer-id="<?php echo $cust['id']; ?>" <?php if ($cust['potensial'] == 'Y') echo 'checked'; ?>>
                                            <label class="form-check-label small">Potensial</label>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($filter === 'potensial' || $filter === 'lost_deal'): ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="lost_deal" data-customer-id="<?php echo $cust['id']; ?>" <?php if ($cust['lost_deal'] == 'Y') echo 'checked'; ?>>
                                            <label class="form-check-label small">Lost Deal</label>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($filter === 'potensial' || $filter === 'acc_boss'): ?>
                                        <div class="form-check form-switch d-flex align-items-center">
                                            <?php 
                                            // if (!$is_superadmin) echo 'disabled'; 
                                            ?>
                                            <input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="acc_boss" data-customer-id="<?php echo $cust['id']; ?>" <?php if ($cust['acc_boss'] == 'Y') echo 'checked'; ?>>
                                            <label class="form-check-label small ms-2">Deal</label>
                                            <?php if (!empty($cust['acc_boss_note'])): ?>
                                                <i class="bi bi-info-circle-fill ms-2 text-primary" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($cust['acc_boss_note']); ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center p-5 text-muted">Tidak ada data customer yang sesuai dengan filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($limit != 'all' && $total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php
            $query_params = http_build_query(array_merge($base_link_params, ['sort_by' => $sort_by, 'sort_dir' => $sort_dir]));
        ?>
        <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $query_params; ?>">Previous</a>
        </li>
        <li class="page-item disabled"><span class="page-link bg-light">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span></li>
        <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $query_params; ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="mediaModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mediaModalLabel">Media Viewer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-dark d-flex justify-content-center align-items-center" id="mediaModalBody" style="min-height: 300px;">
      </div>
    </div>
  </div>
</div>

<?php 
$stmt->close();
require_once 'includes/footer.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mediaModal = document.getElementById('mediaModal');
    const modalTitle = document.getElementById('mediaModalLabel');
    const modalBody = document.getElementById('mediaModalBody');
    const isSuperAdmin = <?php echo $is_superadmin ? 'true' : 'false'; ?>;
    const notification = document.getElementById('notification');
    
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    mediaModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const fileUrl = button.getAttribute('data-file-url');
        const fileName = button.getAttribute('data-file-name');
        const fileExtension = fileName.split('.').pop().toLowerCase();
        
        modalTitle.textContent = fileName;
        modalBody.innerHTML = ''; 

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
            modalBody.innerHTML = `<img src="${fileUrl}" class="img-fluid" style="max-height: 80vh;" alt="${fileName}">`;
        } else if (fileExtension === 'pdf') {
            modalBody.innerHTML = `<iframe src="${fileUrl}" style="width:100%; height:75vh; border:none;" frameborder="0"></iframe>`;
        } else if (['mp4', 'webm', 'mkv', 'mov'].includes(fileExtension)) {
            modalBody.innerHTML = `
                <video controls autoplay class="w-100" style="max-height: 80vh;">
                    <source src="${fileUrl}" type="video/${fileExtension === 'mkv' ? 'x-matroska' : fileExtension}">
                    Browser Anda tidak mendukung elemen video ini.
                </video>`;
        } else if (['mp3', 'wav', 'ogg', 'm4a', 'aac'].includes(fileExtension)) {
            let mimeType = 'audio/mpeg';
            if(fileExtension === 'wav') mimeType = 'audio/wav';
            if(fileExtension === 'ogg') mimeType = 'audio/ogg';
            if(fileExtension === 'm4a' || fileExtension === 'aac') mimeType = 'audio/mp4';

            modalBody.innerHTML = `
                <div class="text-center p-4 w-100">
                    <div class="mb-4"><i class="bi bi-music-note-beamed text-light" style="font-size: 4rem;"></i></div>
                    <h5 class="text-light mb-3">${fileName}</h5>
                    <audio controls autoplay class="w-100">
                        <source src="${fileUrl}" type="${mimeType}">
                        Browser Anda tidak mendukung elemen audio ini.
                    </audio>
                </div>`;
        } else {
            modalBody.innerHTML = `
                <div class="text-center p-5 bg-white rounded">
                    <p class="mb-3 text-dark">Pratinjau tidak tersedia untuk format ini.</p>
                    <a href="${fileUrl}" class="btn btn-primary" download><i class="bi bi-download"></i> Download ${fileName}</a>
                </div>`;
        }
    });

    mediaModal.addEventListener('hidden.bs.modal', function () {
        const mediaElement = modalBody.querySelector('video, audio');
        if (mediaElement) {
            mediaElement.pause();
            mediaElement.src = '';
        }
        modalBody.innerHTML = ''; 
    });

    const limitSelect = document.getElementById('limit-select');
    if (limitSelect) {
        limitSelect.addEventListener('change', function() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('limit', this.value);
            urlParams.set('page', '1');
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        });
    }

    function showNotification(message, isSuccess) {
        notification.textContent = message;
        notification.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        notification.style.display = 'block';
        setTimeout(() => { notification.style.display = 'none'; }, 3000);
    }

    document.querySelector('tbody').addEventListener('change', function(event) {
        if (event.target.classList.contains('status-checkbox')) {
            const checkbox = event.target;
            const customerId = checkbox.dataset.customerId;
            const statusType = checkbox.dataset.type;
            const newStatus = checkbox.checked ? 'Y' : 'N';

            if (statusType === 'acc_boss' && newStatus === 'Y' && isSuperAdmin) {
                Swal.fire({
                    title: 'Tambahkan Catatan (Opsional)',
                    input: 'textarea',
                    inputPlaceholder: 'Catatan untuk sales...',
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
        .then(response => response.json())
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
            console.error('Fetch error:', error);
            const checkbox = document.querySelector(`input[data-customer-id="${customerId}"][data-type="${statusType}"]`);
            if (checkbox) checkbox.checked = !checkbox.checked;
        });
    }
});
</script>