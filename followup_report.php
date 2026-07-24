<?php
$page_title = "Laporan Semua Follow Up";
require_once 'includes/db.php';
require_once 'includes/header.php';

if ($_SESSION['role'] !== 'superadmin') {
    die("Akses ditolak. Halaman ini hanya untuk Superadmin.");
}

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
    'tgl_follow_up' => 'fu.tgl_follow_up',
    'nama_toko' => 'c.nama_toko',
    'nama_sales' => 's.nama_lengkap',
    'respon' => 'fu.respon',
    'no_inv' => 'fu.no_inv'
];

$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$selected_sales_id = isset($_GET['sales_id']) && is_numeric($_GET['sales_id']) ? (int)$_GET['sales_id'] : '';
$limit = $_GET['limit'] ?? 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$sort_by = isset($_GET['sort_by']) && array_key_exists($_GET['sort_by'], $allowed_sort_columns) ? $_GET['sort_by'] : 'tgl_follow_up';
$sort_dir = isset($_GET['sort_dir']) && in_array(strtoupper($_GET['sort_dir']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_dir']) : 'DESC';

$sales_list_result = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL ORDER BY nama_lengkap ASC");

$base_query = "FROM follow_ups fu 
               JOIN sales s ON fu.sales_id = s.id 
               JOIN customers c ON fu.customer_id = c.id";

$conditions = ["fu.deleted_at IS NULL"];
$params = [];
$types = '';

if ($tgl_mulai && $tgl_akhir) {
    $conditions[] = "DATE(fu.tgl_follow_up) BETWEEN ? AND ?";
    array_push($params, $tgl_mulai, $tgl_akhir);
    $types .= 'ss';
}
if ($selected_sales_id) {
    $conditions[] = "fu.sales_id = ?";
    $params[] = $selected_sales_id;
    $types .= 'i';
}

$where_clause = " WHERE " . implode(' AND ', $conditions);

$count_sql = "SELECT COUNT(DISTINCT fu.id) as total " . $base_query . $where_clause;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$offset = ($page - 1) * ($limit == 'all' ? 1 : $limit);
$total_pages = ($limit == 'all') ? 1 : ceil($total_records / $limit);
$page = max(1, min($page, $total_pages));

$order_by_clause = " ORDER BY " . $allowed_sort_columns[$sort_by] . " " . $sort_dir;

$data_sql = "SELECT 
                fu.id, fu.customer_id, fu.tgl_follow_up, fu.respon, fu.keterangan, fu.no_inv,
                fu.media1, fu.media2, fu.media3, fu.sales_id,
                s.nama_lengkap as nama_sales_fu, 
                c.nama_toko, c.kandidat, c.potensial, c.acc_boss, c.acc_boss_note
             " . $base_query . $where_clause . $order_by_clause;

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
$followups_result = $stmt->get_result();

$base_link_params = [
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
?>

<style>
/* Page Specific Enhancements */
.report-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.report-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.report-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.report-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.respon-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.respon-deal {
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.respon-beli {
    background: linear-gradient(135deg, #2563EB 0%, #3B82F6 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.respon-tanya {
    background: linear-gradient(135deg, #D97706 0%, #F59E0B 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.respon-no {
    background: linear-gradient(135deg, #E11D48 0%, #F43F5E 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(244, 63, 94, 0.3);
}

.respon-default {
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #E2E8F0;
}

.inv-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    margin-top: 4px;
}

.sales-avatar-badge {
    width: 28px; height: 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, #475569, #1E293B);
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
<div class="report-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Follow Up Report</span>
            </div>
            <h1 class="report-hero-title">Laporan Follow Up Sales 📊</h1>
            <p class="report-hero-subtitle">Pantau seluruh aktivitas komunikasi, respon customer, dan konversi sales secara terpusat.</p>
        </div>
        <div class="d-flex align-items-center gap-3 mt-3 mt-md-0">
            <div class="bg-white bg-opacity-10 backdrop-blur rounded-4 p-3 px-4 border border-white border-opacity-10 text-center">
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,0.7); font-weight:700;">Total Records</div>
                <div style="font-size:24px; font-weight:800; color:#FFF;"><?php echo number_format($total_records); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel-fill"></i> Filter Laporan Follow Up</h5>
    </div>
    <div class="card-body">
        <form action="" method="GET" id="filter-form">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label for="tgl_mulai" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_mulai" name="tgl_mulai" value="<?php echo htmlspecialchars($tgl_mulai); ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label for="tgl_akhir" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" value="<?php echo htmlspecialchars($tgl_akhir); ?>">
                </div>
                <div class="col-md-3 col-sm-6">
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
                <div class="col-md-3 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="followup_report.php" class="btn btn-secondary" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </div>
            <input type="hidden" name="limit" value="<?php echo htmlspecialchars($limit); ?>">
        </form>
    </div>
</div>

<!-- Table Header Actions & Limit Select -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 px-1">
    <div class="d-flex align-items-center gap-2">
        <label for="limit-select" class="form-label me-1 mb-0" style="font-size:12px;">Tampilkan:</label>
        <select id="limit-select" class="form-select form-select-sm" style="width: 85px;">
            <option value="20" <?php if ($limit == '20') echo 'selected'; ?>>20</option>
            <option value="40" <?php if ($limit == '40') echo 'selected'; ?>>40</option>
            <option value="60" <?php if ($limit == '60') echo 'selected'; ?>>60</option>
            <option value="80" <?php if ($limit == '80') echo 'selected'; ?>>80</option>
            <option value="100" <?php if ($limit == '100') echo 'selected'; ?>>100</option>
        </select>
    </div>
    <div class="text-muted fw-semibold" style="font-size:13px;">
        <?php if ($limit != 'all' && $total_records > 0): ?>
            Menampilkan <span class="text-dark fw-bold"><?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $limit, $total_records)); ?></span> dari <span class="text-primary fw-bold"><?php echo number_format($total_records); ?></span> data
        <?php elseif ($total_records > 0): ?>
             Menampilkan semua <span class="text-primary fw-bold"><?php echo number_format($total_records); ?></span> data
        <?php endif; ?>
    </div>
</div>

<!-- Main Table Card -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-card-list"></i> Riwayat Semua Follow Up</h5>
        <a href="customer_management.php" class="btn btn-sm btn-secondary">
            <i class="bi bi-grid-fill"></i> Dashboard
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 12%;"><?php echo create_sort_link('tgl_follow_up', 'Tanggal', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 22%;"><?php echo create_sort_link('nama_toko', 'Customer', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 12%;"><?php echo create_sort_link('nama_sales_fu', 'Sales', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 18%;"><?php echo create_sort_link('respon', 'Respon', $sort_by, $sort_dir, $base_link_params); ?></th>
                        <th style="width: 20%;">Keterangan</th>
                        <th style="width: 10%;">Media</th>
                        <th style="width: 6%; text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($followups_result->num_rows > 0): ?>
                        <?php while($fu = $followups_result->fetch_assoc()): ?>
                            <tr id="followup-row-<?php echo $fu['id']; ?>">
                                <td class="text-nowrap" style="font-size:12.5px; font-weight:600; color:#64748B;">
                                    <i class="bi bi-clock me-1 text-primary"></i>
                                    <?php echo date('d M Y', strtotime($fu['tgl_follow_up'])); ?>
                                    <div style="font-size:11px; color:#94A3B8; margin-top:2px; font-weight:500;">
                                        <?php echo date('H:i', strtotime($fu['tgl_follow_up'])); ?> WIB
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold mb-1">
                                        <a href="followup_view.php?customer_id=<?php echo $fu['customer_id']; ?>" class="text-decoration-none text-dark hover-primary" style="font-size:14px; font-family:'Plus Jakarta Sans', sans-serif;">
                                            <?php echo htmlspecialchars($fu['nama_toko']); ?>
                                        </a>
                                    </div>
                                    <div class="d-flex gap-1 flex-wrap mt-1">
                                        <?php if ($fu['acc_boss'] == 'Y'): ?>
                                            <span class="badge bg-success" title="<?php echo htmlspecialchars($fu['acc_boss_note']); ?>"><i class="bi bi-check-circle-fill"></i> Acc Boss</span>
                                        <?php endif; ?>
                                        <?php if ($fu['potensial'] == 'Y'): ?>
                                            <span class="badge bg-warning"><i class="bi bi-star-fill"></i> Potensial</span>
                                        <?php endif; ?>
                                        <?php if ($fu['kandidat'] == 'Y'): ?>
                                            <span class="badge bg-info"><i class="bi bi-person-fill"></i> Kandidat</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    <div class="d-flex align-items-center">
                                        <div class="sales-avatar-badge">
                                            <?php echo strtoupper(substr($fu['nama_sales_fu'], 0, 1)); ?>
                                        </div>
                                        <span style="font-weight:600; font-size:13px; color:#1E293B;">
                                            <?php echo htmlspecialchars($fu['nama_sales_fu']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $respon = htmlspecialchars($fu['respon']);
                                    $pillClass = 'respon-default';
                                    $icon = 'bi-chat-text-fill';
                                    if(in_array($respon, ['Tidak ada respon', 'Tidak tertarik'])) {
                                        $pillClass = 'respon-no'; $icon = 'bi-x-circle-fill';
                                    } elseif($respon == 'Hanya bertanya') {
                                        $pillClass = 'respon-tanya'; $icon = 'bi-question-circle-fill';
                                    } elseif($respon == 'Muncul keinginan membeli') {
                                        $pillClass = 'respon-beli'; $icon = 'bi-arrow-up-right-circle-fill';
                                    } elseif($respon == 'Deal untuk beli') {
                                        $pillClass = 'respon-deal'; $icon = 'bi-check-all';
                                    }
                                    ?>
                                    <span class="respon-pill <?php echo $pillClass; ?>">
                                        <i class="bi <?php echo $icon; ?>"></i> <?php echo $respon; ?>
                                    </span>
                                    <?php if ($fu['no_inv']): ?>
                                        <div class="inv-badge"><i class="bi bi-receipt"></i> <?php echo htmlspecialchars($fu['no_inv']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:13px; color:#475569; line-height:1.5;">
                                    <?php echo nl2br(htmlspecialchars($fu['keterangan'])); ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                    <?php for ($i = 1; $i <= 3; $i++): $media_file = $fu['media'.$i]; if ($media_file): ?>
                                        <a href="#" class="btn btn-outline-secondary btn-sm text-truncate" style="max-width: 140px; font-size:11px;" data-bs-toggle="modal" data-bs-target="#mediaModal" data-file-url="assets/uploads/<?php echo htmlspecialchars($media_file); ?>" data-file-name="<?php echo htmlspecialchars($media_file); ?>">
                                            <i class="bi <?php echo get_file_icon($media_file); ?>"></i> <?php echo htmlspecialchars(substr($media_file, 14)); ?>
                                        </a>
                                    <?php endif; endfor; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger delete-followup-btn" data-followup-id="<?php echo $fu['id']; ?>" title="Hapus catatan">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center p-5 text-muted">Tidak ada data follow up ditemukan.</td></tr>
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
            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $query_params; ?>"><i class="bi bi-chevron-left me-1"></i> Prev</a>
        </li>
        <li class="page-item disabled"><span class="page-link bg-light fw-bold text-dark">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span></li>
        <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $query_params; ?>">Next <i class="bi bi-chevron-right ms-1"></i></a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="mediaModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:20px; overflow:hidden; border:none;">
      <div class="modal-header" style="background:#0F172A; color:#FFF;">
        <h5 class="modal-title" id="mediaModalLabel" style="font-weight:700; font-size:15px;">Media Viewer</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-dark d-flex justify-content-center align-items-center" id="mediaModalBody" style="min-height: 350px;">
      </div>
    </div>
  </div>
</div>

<?php 
$stmt->close();
require_once 'includes/footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mediaModal = document.getElementById('mediaModal');
    const modalTitle = document.getElementById('mediaModalLabel');
    const modalBody = document.getElementById('mediaModalBody');
    const tableBody = document.querySelector('tbody');

    mediaModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const fileUrl = button.getAttribute('data-file-url');
        const fileName = button.getAttribute('data-file-name');
        const fileExtension = fileName.split('.').pop().toLowerCase();
        
        modalTitle.textContent = fileName;
        modalBody.innerHTML = ''; 

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
            modalBody.innerHTML = `<img src="${fileUrl}" class="img-fluid rounded" style="max-height: 80vh;" alt="${fileName}">`;
        } else if (fileExtension === 'pdf') {
            modalBody.innerHTML = `<iframe src="${fileUrl}" style="width:100%; height:75vh; border:none;" frameborder="0"></iframe>`;
        } else if (['mp4', 'webm', 'mkv', 'mov'].includes(fileExtension)) {
            modalBody.innerHTML = `
                <video controls autoplay class="w-100 rounded" style="max-height: 80vh;">
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

    tableBody.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.delete-followup-btn');
        if (deleteButton) {
            if (confirm('Anda yakin ingin menghapus catatan follow-up ini?')) {
                const followupId = deleteButton.dataset.followupId;
                fetch('followup_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 'followup_id': followupId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById('followup-row-' + followupId);
                        row.style.transition = 'all 0.5s ease-out';
                        row.style.opacity = '0';
                        row.style.transform = 'scale(0.95)';
                        setTimeout(() => row.remove(), 500);
                    } else {
                        alert('Gagal menghapus: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Terjadi kesalahan jaringan.');
                    console.error('Error:', error);
                });
            }
        }
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
});
</script>