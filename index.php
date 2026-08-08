<?php
$page_title = 'Daftar Customer & Forum Q&A Sales';
require_once 'includes/db.php';
require_once 'includes/header.php';

// --- 1. FETCH Q&A DATA FOR FORUM SECTION ---
$sql_qa = "
    SELECT 
        q.id as question_id, q.title, q.body as question_body, q.created_at as question_created_at,
        qs.nama_lengkap as question_author, qs.id as question_author_id,
        a.id as answer_id, a.body as answer_body, a.created_at as answer_created_at,
        ans.nama_lengkap as answer_author, ans.id as answer_author_id
    FROM qa_questions q
    JOIN sales qs ON q.sales_id = qs.id
    LEFT JOIN qa_answers a ON q.id = a.question_id AND a.deleted_at IS NULL
    LEFT JOIN sales ans ON a.sales_id = ans.id
    WHERE q.deleted_at IS NULL
    ORDER BY q.created_at DESC, a.created_at ASC
";
$result_qa = $conn->query($sql_qa);
$questions = [];
if ($result_qa) {
    while ($row = $result_qa->fetch_assoc()) {
        $qid = $row['question_id'];
        if (!isset($questions[$qid])) {
            $questions[$qid] = [
                'id' => $row['question_id'],
                'title' => $row['title'],
                'body' => $row['question_body'],
                'author' => $row['question_author'],
                'author_id' => $row['question_author_id'],
                'created_at' => $row['question_created_at'],
                'answers' => []
            ];
        }
        if ($row['answer_id']) {
            $questions[$qid]['answers'][] = [
                'id' => $row['answer_id'],
                'body' => $row['answer_body'],
                'author' => $row['answer_author'],
                'author_id' => $row['answer_author_id'],
                'created_at' => $row['answer_created_at']
            ];
        }
    }
}

// --- 2. FETCH CUSTOMER DATA FOR DAFTAR CUSTOMER SECTION ---
$filter_kota = trim($_GET['filter_kota'] ?? '');
$filter_kategori = trim($_GET['filter_kategori'] ?? '');
$filter_sales = intval($_GET['filter_sales'] ?? 0);
$search_keyword = trim($_GET['search'] ?? '');

$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 25;
if ($limit <= 0) $limit = 25;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page <= 0) $page = 1;

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

if (!empty($search_keyword)) {
    $sql_where_conditions[] = "(c.nama_toko LIKE ? OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?)))";
    $like_kw = '%' . $search_keyword . '%';
    array_push($params, $like_kw, $like_kw, $like_kw);
    $types .= 'sss';
}

$where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

// Get total record count
$count_sql = "SELECT COUNT(*) as total FROM customers c {$where_clause}";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_records / $limit);
$page = max(1, min($page, max(1, $total_pages)));
$offset = ($page - 1) * $limit;

// Fetch paginated customer records
$sql = "
    SELECT 
        c.id, c.tgl_input, c.nama_toko, c.deal, c.kandidat, c.sales_id, c.kategori,
        s.nama_lengkap AS nama_sales,
        (SELECT GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL) AS all_pics,
        (SELECT GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL) AS all_phones,
        (SELECT GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.deleted_at IS NULL) AS all_cities,
        (SELECT ca.link_google_map FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.deleted_at IS NULL AND ca.link_google_map IS NOT NULL AND ca.link_google_map != '' LIMIT 1) AS primary_map_link,
        (SELECT COUNT(*) FROM follow_ups fu WHERE fu.customer_id = c.id AND fu.deleted_at IS NULL) AS fu_count
    FROM 
        customers c
    LEFT JOIN 
        sales s ON c.sales_id = s.id
    {$where_clause}
    ORDER BY 
        c.id DESC
    LIMIT ?, ?
";

$main_params = $params;
$main_types = $types;
$main_params[] = $offset;
$main_params[] = $limit;
$main_types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($main_params)) {
    $stmt->bind_param($main_types, ...$main_params);
}
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Fetch list of distinct cities
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

.answer-card {
    border: 1.5px solid #E2E8F0;
    border-left: 4px solid #2563EB !important;
    border-radius: 14px !important;
    background: #F8FAFC;
    transition: all 0.2s ease;
}

.answer-card:hover {
    background: #FFFFFF;
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}

.question-meta, .answer-meta {
    font-size: 12px;
    color: #64748B;
    font-weight: 600;
}

#questionsTable tbody tr {
    cursor: pointer;
    transition: all 0.2s ease;
}

.answer-pill-btn {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #FFF;
    border-radius: 30px;
    padding: 5px 14px;
    font-size: 11.5px;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
</style>

<!-- TOP RUNNING TEXT ANNOUNCEMENT TICKER BANNER -->
<?php include 'includes/announcement_widget.php'; ?>

<!-- UNIFIED SINGLE HERO HEADER -->
<div class="cust-hero mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Daftar Customer & Forum Q&A</span>
            </div>
            <h1 class="cust-hero-title">Daftar Customer & Forum Q&A 👥💬</h1>
            <p class="cust-hero-subtitle">Kelola database customer, PIC kontak, serta berdiskusi bersama tim sales Loewix dalam satu workspace terpadu.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <a href="customer_add.php" class="btn-add-customer-vip">
                <span class="btn-icon-badge"><i class="bi bi-plus-lg"></i></span>
                <span>Tambah Customer Baru</span>
            </a>
            <button class="btn btn-outline-light fw-bold rounded-pill px-3 py-2.5 d-inline-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addQuestionModal" style="border-width:1.5px; backdrop-filter:blur(4px);">
                <i class="bi bi-chat-square-quote-fill text-warning"></i>
                <span>Tanya Tim Sales</span>
            </button>
        </div>
    </div>
</div>

<?php include 'includes/bonus_competition_widget.php'; ?>
<?php include 'includes/sales_ranking_widget.php'; ?>

<!-- SECTION 1: COLLAPSIBLE FORUM Q&A ACCORDION -->
<div id="forum-section" class="mb-4">
    <div class="card border-0 shadow-sm" style="border-radius:18px; overflow:hidden;">
        <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom-0 collapsed" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#forumCollapseContent" aria-expanded="false">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-gradient d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width:40px; height:40px;">
                    <i class="bi bi-chat-left-dots-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold text-dark" style="font-family:'Plus Jakarta Sans', sans-serif; font-size:16px;">
                        Forum Q&A & Diskusi Sales 
                        <span class="badge bg-primary rounded-pill ms-2" style="font-size:11.5px; padding:4px 10px;"><?php echo count($questions); ?> Topik</span>
                    </h5>
                    <small class="text-muted" style="font-size:12px;">Tempat bertanya & berbagi solusi seputar produk/customer (Klik untuk buka / tutup)</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">
                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                    <i class="bi bi-plus-circle-fill me-1"></i> Buat Pertanyaan
                </button>
                <span class="btn btn-sm btn-light rounded-circle border p-0 d-inline-flex align-items-center justify-content-center" style="width:32px; height:32px;" data-bs-toggle="collapse" data-bs-target="#forumCollapseContent">
                    <i class="bi bi-chevron-down text-muted"></i>
                </span>
            </div>
        </div>

        <div class="collapse" id="forumCollapseContent">
            <div class="card-body p-4 bg-light border-top">
                <!-- Search Bar -->
                <div class="mb-3">
                    <div class="input-group shadow-2sm" style="border-radius:12px; overflow:hidden;">
                        <span class="input-group-text bg-white border-end-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                        <input type="text" id="liveSearchInput" class="form-control border-start-0 ps-1 fw-semibold" placeholder="Cari pertanyaan, kata kunci, atau nama sales..." style="height:42px; font-size:13.5px;">
                    </div>
                </div>

                <!-- Discussion Items Feed -->
                <div class="d-flex flex-column gap-2.5" id="forumFeedContainer">
                    <?php if (empty($questions)): ?>
                        <div class="text-center p-4 bg-white rounded-3 border">
                            <i class="bi bi-chat-square-dots text-primary fs-2 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark mb-1">Belum ada diskusi sales.</h6>
                            <small class="text-muted">Klik "Buat Pertanyaan" di atas untuk memulai diskusi!</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($questions as $q): 
                            $hasAnswers = count($q['answers']) > 0;
                            $ansPillStyle = $hasAnswers ? 'background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE;' : 'background:#FEF3C7; color:#92400E; border:1px solid #FDE68A;';
                        ?>
                        <div class="forum-card-item card border-0 shadow-2sm" id="question-row-<?php echo $q['id']; ?>"
                            data-question-id="<?php echo $q['id']; ?>"
                            data-title="<?php echo htmlspecialchars($q['title']); ?>"
                            data-body="<?php echo htmlspecialchars($q['body']); ?>"
                            data-author="<?php echo htmlspecialchars($q['author']); ?>"
                            data-date="<?php echo date('d M Y', strtotime($q['created_at'])); ?>"
                            data-answers='<?php echo json_encode($q['answers']); ?>'
                            style="border-radius:14px; transition:all 0.2s ease; cursor:pointer;"
                            data-bs-toggle="modal" data-bs-target="#viewQuestionModal" onclick="populateAndShowModal(this)">
                            
                            <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3" style="min-width:280px; flex: 1 1 400px;">
                                    <div class="sales-avatar-badge-small flex-shrink-0" style="width:36px; height:36px; font-size:13px; border-radius:10px;">
                                        <?php echo strtoupper(substr($q['author'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-family:'Plus Jakarta Sans', sans-serif; font-size:14.5px; line-height:1.3;">
                                            <i class="bi bi-question-circle-fill text-primary me-1"></i>
                                            <?php echo htmlspecialchars($q['title']); ?>
                                        </div>
                                        <div class="text-muted small mt-0.5" style="font-size:12.5px;">
                                            <?php echo htmlspecialchars(substr($q['body'], 0, 100)) . (strlen($q['body']) > 100 ? '...' : ''); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-3 ms-auto" onclick="event.stopPropagation();">
                                    <div class="text-end d-none d-sm-block">
                                        <div class="fw-semibold text-dark" style="font-size:12px;"><?php echo htmlspecialchars($q['author']); ?></div>
                                        <small class="text-muted" style="font-size:11px;"><?php echo date('d M Y', strtotime($q['created_at'])); ?></small>
                                    </div>
                                    <span class="badge rounded-pill fw-bold" style="<?php echo $ansPillStyle; ?> font-size:11.5px; padding:6px 14px;" data-bs-toggle="modal" data-bs-target="#viewQuestionModal" onclick="populateAndShowModal(this.closest('.forum-card-item'))">
                                        <i class="bi bi-chat-right-text-fill me-1"></i> <?php echo count($q['answers']); ?> Jawaban
                                    </span>
                                    <?php if ($_SESSION['user_id'] == $q['author_id'] || $_SESSION['role'] === 'superadmin'): ?>
                                        <button class="btn btn-sm btn-light border text-danger rounded-circle p-0 d-inline-flex align-items-center justify-content-center delete-btn" data-id="<?php echo $q['id']; ?>" data-type="question" title="Hapus Pertanyaan" style="width:30px; height:30px;">
                                            <i class="bi bi-trash-fill" style="font-size:12px;"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2: DAFTAR CUSTOMER -->
<div id="customer-section">

    <!-- Filter Toolbar Card -->
    <div class="filter-card">
        <form method="GET" action="index.php#customer-section" id="index-filter-form">
            <div class="row g-3 align-items-end mb-3">
                <!-- Cari Kata Kunci / Toko / PIC -->
                <div class="col-lg-6 col-md-12 col-12">
                    <label for="search" class="form-label text-muted fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px; text-transform:uppercase;">
                        <i class="bi bi-search text-primary me-1"></i> Cari Kata Kunci / Toko / PIC / No HP
                    </label>
                    <input type="text" name="search" id="search" class="form-control fw-semibold" placeholder="Ketik nama toko, nama PIC, atau no HP..." value="<?php echo htmlspecialchars($search_keyword); ?>" style="border-radius:12px; height:42px;">
                </div>

                <!-- Filter Kota -->
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="filter_kota" class="form-label text-muted fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px; text-transform:uppercase;">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Filter Kota / Daerah
                    </label>
                    <select name="filter_kota" id="filter_kota" class="form-select fw-semibold" style="border-radius:12px; height:42px;">
                        <option value="">Semua Daerah / Kota</option>
                        <optgroup label="📍 REGION & PROVINSI UTAMA">
                            <option value="Jawa Barat" <?php if ($filter_kota === 'Jawa Barat') echo 'selected'; ?>>🏞️ Jawa Barat</option>
                            <option value="Jawa Tengah" <?php if ($filter_kota === 'Jawa Tengah') echo 'selected'; ?>>🏯 Jawa Tengah</option>
                            <option value="Jawa Timur" <?php if ($filter_kota === 'Jawa Timur') echo 'selected'; ?>>🌊 Jawa Timur</option>
                            <option value="Jakarta Timur" <?php if ($filter_kota === 'Jakarta Timur') echo 'selected'; ?>>📍 Jakarta Timur</option>
                            <option value="Jakarta Barat" <?php if ($filter_kota === 'Jakarta Barat') echo 'selected'; ?>>📍 Jakarta Barat</option>
                            <option value="Jakarta Selatan" <?php if ($filter_kota === 'Jakarta Selatan') echo 'selected'; ?>>📍 Jakarta Selatan</option>
                            <option value="Jakarta Utara" <?php if ($filter_kota === 'Jakarta Utara') echo 'selected'; ?>>📍 Jakarta Utara</option>
                            <option value="Jakarta Pusat" <?php if ($filter_kota === 'Jakarta Pusat') echo 'selected'; ?>>📍 Jakarta Pusat</option>
                            <option value="Jakarta" <?php if ($filter_kota === 'Jakarta') echo 'selected'; ?>>🏢 DKI Jakarta (Semua)</option>
                            <option value="Banten" <?php if ($filter_kota === 'Banten') echo 'selected'; ?>>🏙️ Banten</option>
                            <option value="Yogyakarta" <?php if ($filter_kota === 'Yogyakarta') echo 'selected'; ?>>🏰 DI Yogyakarta</option>
                            <option value="Sumatera Utara" <?php if ($filter_kota === 'Sumatera Utara') echo 'selected'; ?>>🌲 Sumatera Utara</option>
                            <option value="Sumatera Selatan" <?php if ($filter_kota === 'Sumatera Selatan') echo 'selected'; ?>>🌴 Sumatera Selatan</option>
                            <option value="Riau" <?php if ($filter_kota === 'Riau') echo 'selected'; ?>>🌴 Riau & Kep. Riau</option>
                            <option value="Lampung" <?php if ($filter_kota === 'Lampung') echo 'selected'; ?>>🐘 Lampung</option>
                            <option value="Bali" <?php if ($filter_kota === 'Bali') echo 'selected'; ?>>🏝️ Bali & Nusa Tenggara</option>
                            <option value="Kalimantan" <?php if ($filter_kota === 'Kalimantan') echo 'selected'; ?>>🌲 Kalimantan</option>
                            <option value="Sulawesi" <?php if ($filter_kota === 'Sulawesi') echo 'selected'; ?>>🏝️ Sulawesi</option>
                        </optgroup>
                        <optgroup label="🏙️ DAFTAR KOTA SPESIFIK">
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>" <?php if ($filter_kota === $city) echo 'selected'; ?>>
                                    🏙️ <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <!-- Filter Kategori -->
                <div class="col-lg-3 col-md-6 col-12">
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
            </div>

            <div class="row g-3 align-items-end">
                <!-- Filter Sales (Superadmin/Adminsales only) -->
                <?php if ($_SESSION['role'] !== 'sales'): ?>
                <div class="col-lg-3 col-md-6 col-12">
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

                <!-- Entri Per Halaman -->
                <div class="col-lg-3 col-md-6 col-12">
                    <label for="limit" class="form-label text-muted fw-bold mb-1" style="font-size:11px; letter-spacing:0.5px; text-transform:uppercase;">
                        <i class="bi bi-layers-fill text-primary me-1"></i> Entri Per Halaman
                    </label>
                    <select name="limit" id="limit" class="form-select fw-semibold" style="border-radius:12px; height:42px;">
                        <option value="20" <?php if ($limit == 20) echo 'selected'; ?>>20 data per halaman</option>
                        <option value="25" <?php if ($limit == 25) echo 'selected'; ?>>25 data per halaman</option>
                        <option value="50" <?php if ($limit == 50) echo 'selected'; ?>>50 data per halaman</option>
                        <option value="100" <?php if ($limit == 100) echo 'selected'; ?>>100 data per halaman</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="<?php echo ($_SESSION['role'] !== 'sales') ? 'col-lg-6 col-md-12' : 'col-lg-9 col-md-12'; ?> col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-extrabold flex-grow-1 shadow-sm d-inline-flex align-items-center justify-content-center gap-1.5" style="border-radius:12px; height:42px; font-weight:800; white-space:nowrap; background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);">
                        <i class="bi bi-funnel-fill"></i> Terapkan Filter
                    </button>
                    <?php if (!empty($search_keyword) || !empty($filter_kota) || !empty($filter_kategori) || $filter_sales > 0 || $limit != 25): ?>
                        <a href="index.php#customer-section" class="btn btn-light border border-slate fw-bold d-inline-flex align-items-center justify-content-center gap-1" title="Reset Filter" style="border-radius:12px; height:42px; padding:0 18px; white-space:nowrap;">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
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
                            <th style="width: 17%;">NAMA TOKO</th>
                            <th style="width: 20%;">PIC & KONTAK</th>
                            <th style="width: 10%;">KATEGORI</th>
                            <th style="width: 11%;">KOTA</th>
                            <th style="width: 13%;">SALES</th>
                            <th class="text-center" style="width: 5%;">FU</th>
                            <th class="text-center" style="width: 5%;">KANDIDAT</th>
                            <th class="text-center" style="width: 5%;">DEAL</th>
                            <th class="text-center" style="width: 4%;">MAPS</th>
                            <th class="text-center" style="width: 10%;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                            <tr id="customer-row-<?php echo $customer['id']; ?>">
                                <td style="font-family:'Plus Jakarta Sans', sans-serif;">
                                    <div class="fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size:13.5px; color:#0F172A; line-height:1.35;">
                                        <i class="bi bi-shop text-primary me-1 flex-shrink-0" style="font-size:14px;"></i>
                                        <span><?php echo htmlspecialchars($customer['nama_toko']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $pics = !empty($customer['all_pics']) ? explode('||', $customer['all_pics']) : [];
                                    $phones = !empty($customer['all_phones']) ? explode('||', $customer['all_phones']) : [];
                                    if (!empty($pics)) {
                                        foreach ($pics as $key => $pic_name) {
                                            $phone_number = $phones[$key] ?? '';
                                            $display_pic = trim($pic_name);
                                            $show_name = ($display_pic !== '' && strtolower($display_pic) !== 'unknown' && strtolower($display_pic) !== strtolower(trim($customer['nama_toko'])));
                                            
                                            echo '<div class="d-flex align-items-center flex-wrap gap-1.5 small fw-semibold text-dark my-0.5">';
                                            if ($show_name) {
                                                echo '<span class="d-inline-flex align-items-center"><i class="bi bi-person-fill text-muted me-1"></i>' . htmlspecialchars($display_pic) . '</span>';
                                            }
                                            if (!empty($phone_number)) {
                                                $cleaned_tel = preg_replace('/[^0-9]/', '', $phone_number);
                                                $wa_number = (substr($cleaned_tel, 0, 1) === '0') ? '62' . substr($cleaned_tel, 1) : $cleaned_tel;
                                                echo '<a href="https://wa.me/' . $wa_number . '" target="_blank" class="badge text-success border text-decoration-none shadow-2sm" style="background:#F0FDF4; color:#15803D !important; border-color:#86EFAC !important; border-radius:20px; padding:4px 10px; font-weight:700; font-size:11.5px; display:inline-flex; align-items:center; gap:3px;"><i class="bi bi-whatsapp"></i> ' . htmlspecialchars($phone_number) . '</a>';
                                            }
                                            echo '</div>';
                                        }
                                    } else { echo '<span class="text-muted small">-</span>'; }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-semibold" style="border-radius:20px; padding:5px 12px; font-size:11.5px;"><?php echo htmlspecialchars($customer['kategori'] ?? '-'); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $city_val = trim($customer['all_cities'] ?? '');
                                    if (!empty($city_val) && $city_val !== '-'): 
                                    ?>
                                        <span class="badge fw-bold" style="background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE; border-radius:20px; padding:5px 12px; font-size:11.5px;">
                                            📍 <?php echo htmlspecialchars($city_val); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($customer['nama_sales']): ?>
                                        <div class="d-flex align-items-center gap-1.5" style="white-space:nowrap;">
                                            <div class="sales-avatar-badge-small flex-shrink-0">
                                                <?php echo strtoupper(substr($customer['nama_sales'], 0, 1)); ?>
                                            </div>
                                            <span class="fw-semibold text-dark" style="font-size:12.5px;"><?php echo htmlspecialchars($customer['nama_sales']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark fw-bold" style="border-radius:20px; padding:4px 10px;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Belum Di-assign</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold">
                                    <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" title="Lihat Riwayat Follow Up" class="text-decoration-none">
                                        <span class="badge bg-primary rounded-pill px-2.5 py-1" style="font-size:12px;"><?php echo $customer['fu_count']; ?></span>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center mb-0"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="kandidat" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['kandidat'] == 'Y') echo 'checked'; ?>></div>
                                </td>
                                <td class="text-center">
                                   <div class="form-check form-switch d-flex justify-content-center mb-0"><input class="form-check-input status-checkbox" type="checkbox" role="switch" data-type="deal" data-customer-id="<?php echo $customer['id']; ?>" <?php if ($customer['deal'] == 'Y') echo 'checked'; ?>></div>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($customer['primary_map_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($customer['primary_map_link']); ?>" target="_blank" class="btn btn-sm rounded-circle shadow-sm" style="width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; background:#ECFDF5; color:#059669; border:1px solid #A7F3D0;" title="Buka di Google Maps"><i class="bi bi-geo-alt-fill"></i></a>
                                    <?php else: ?>
                                        <button class="btn btn-sm rounded-circle border" disabled style="width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; background:#F8FAFC; color:#CBD5E1;"><i class="bi bi-geo-alt"></i></button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center gap-1.5" style="white-space:nowrap;">
                                        <a href="followup_add.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm text-white fw-bold shadow-sm d-inline-flex align-items-center justify-content-center gap-1" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border:none; border-radius:10px; padding:3px 9px; font-size:11.5px; font-weight:800;" title="Tambah Follow Up Baru">
                                            <i class="bi bi-plus-circle-fill"></i> + FU
                                        </a>
                                        <a href="followup_view.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm rounded-circle shadow-sm" style="width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; background:#EFF6FF; color:#2563EB; border:1px solid #BFDBFE;" title="Lihat Riwayat Follow Up"><i class="bi bi-eye-fill"></i></a>
                                        <?php 
                                        $can_edit_delete = ($_SESSION['role'] == 'superadmin') || ($_SESSION['role'] == 'sales' && $_SESSION['user_id'] == $customer['sales_id']);
                                        if ($can_edit_delete): 
                                        ?>
                                            <a href="customer_edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm rounded-circle shadow-sm" style="width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; background:#F8FAFC; color:#475569; border:1px solid #CBD5E1;" title="Edit Customer"><i class="bi bi-pencil-fill"></i></a>
                                            <a href="customer_delete.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm rounded-circle shadow-sm" style="width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; background:#FEF2F2; color:#DC2626; border:1px solid #FECACA;" title="Hapus Customer" onclick="return confirm('Yakin hapus customer ini?')"><i class="bi bi-trash-fill"></i></a>
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
        <!-- Pagination Footer -->
        <div class="card-footer bg-white py-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="small text-muted fw-semibold">
                Menampilkan <span class="text-dark fw-bold"><?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $limit, $total_records)); ?></span> dari <span class="text-primary fw-bold"><?php echo number_format($total_records); ?></span> customer
            </div>
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1">
                    <?php
                    $link_base_params = [
                        'filter_kota' => $filter_kota,
                        'filter_kategori' => $filter_kategori,
                        'filter_sales' => $filter_sales,
                        'search' => $search_keyword,
                        'limit' => $limit
                    ];
                    
                    if ($page > 1):
                        $prev_params = array_merge($link_base_params, ['page' => $page - 1]);
                    ?>
                        <li class="page-item"><a class="page-link px-3 py-1.5 rounded-3 fw-bold border bg-light text-dark" href="index.php?<?php echo http_build_query($prev_params); ?>#customer-section"><i class="bi bi-chevron-left me-1"></i> Sebelumnya</a></li>
                    <?php endif; ?>

                    <?php
                    $start_p = max(1, $page - 2);
                    $end_p = min($total_pages, $page + 2);
                    for ($p = $start_p; $p <= $end_p; $p++):
                        $p_params = array_merge($link_base_params, ['page' => $p]);
                        $is_act = ($p == $page);
                    ?>
                        <li class="page-item">
                            <a class="page-link px-3 py-1.5 rounded-3 fw-bold border <?php echo $is_act ? 'bg-primary text-white border-primary shadow-sm' : 'bg-white text-dark'; ?>" href="index.php?<?php echo http_build_query($p_params); ?>#customer-section">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php
                    if ($page < $total_pages):
                        $next_params = array_merge($link_base_params, ['page' => $page + 1]);
                    ?>
                        <li class="page-item"><a class="page-link px-3 py-1.5 rounded-3 fw-bold border bg-light text-dark" href="index.php?<?php echo http_build_query($next_params); ?>#customer-section">Selanjutnya <i class="bi bi-chevron-right ms-1"></i></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal View Question & Answers -->
<div class="modal fade" id="viewQuestionModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
      <div class="modal-header" style="background:#0F172A; color:#FFF;">
        <h5 class="modal-title fw-bold" id="q-modal-title" style="font-size:16px;"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="bg-light rounded-3 p-3 mb-3 border">
            <p class="mb-2 fw-semibold text-dark" id="q-modal-body" style="font-size:14.5px; line-height:1.6;"></p>
            <div class="question-meta"><i class="bi bi-person-fill text-primary"></i> Ditanyakan oleh <span id="q-modal-author" class="fw-bold text-dark"></span> pada <span id="q-modal-date"></span></div>
        </div>
        <hr class="my-3">
        <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-chat-square-text-fill text-primary me-2"></i>Jawaban Tim Sales</h6>
        <div id="q-modal-answers-list"></div>
        <form class="add-answer-form mt-4 bg-white p-3 border rounded-3">
            <input type="hidden" id="q-modal-question-id" name="question_id">
            <div class="mb-3">
                <label class="form-label fw-bold text-dark" style="font-size:13px;">Tulis Jawaban Anda</label>
                <textarea name="body" class="form-control" rows="3" placeholder="Bantu rekan sales Anda dengan jawaban yang jelas..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold"><i class="bi bi-send-fill me-1"></i> Kirim Jawaban</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Add Question -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
      <div class="modal-header" style="background:#0F172A; color:#FFF;">
        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Buat Pertanyaan Baru</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="addQuestionForm">
          <input type="hidden" name="action" value="add_question">
          <div class="mb-3">
            <label for="q_title" class="form-label fw-bold text-dark" style="font-size:13px;">Judul Pertanyaan</label>
            <input type="text" class="form-control fw-semibold" id="q_title" name="title" placeholder="mis. Password standar IP CAM Loewix?" required style="border-radius:10px;">
          </div>
          <div class="mb-3">
            <label for="q_body" class="form-label fw-bold text-dark" style="font-size:13px;">Detail Pertanyaan</label>
            <textarea class="form-control fw-medium" id="q_body" name="body" rows="4" placeholder="Jelaskan detail pertanyaan Anda..." style="border-radius:10px;"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
        <button type="submit" form="addQuestionForm" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="bi bi-send-fill me-1"></i> Kirim Pertanyaan</button>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentUserId = <?php echo $_SESSION['user_id']; ?>;
    const isSuperAdmin = '<?php echo $_SESSION['role']; ?>' === 'superadmin';
    const questionsTable = document.getElementById('questionsTable');
    const tableBody = document.querySelector('.sortable-table tbody');
    const notification = document.getElementById('notification');

    // Q&A Live Search
    const liveSearchInput = document.getElementById('liveSearchInput');
    if (liveSearchInput) {
        liveSearchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.forum-card-item').forEach(card => {
                card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }

    // Forum Container Click Listener
    const forumFeedContainer = document.getElementById('forumFeedContainer');
    if (forumFeedContainer) {
        forumFeedContainer.addEventListener('click', function(e) {
            const deleteButton = e.target.closest('.delete-btn[data-type="question"]');
            if (deleteButton) {
                e.stopPropagation();
                handleDelete(deleteButton.dataset.id, 'question');
            }
        });
    }

    // Populate dan Tampilkan Modal Lihat Pertanyaan
    const viewQuestionModal = document.getElementById('viewQuestionModal');
    window.populateAndShowModal = function(cardEl) {
        if (!cardEl) return;
        const answers = JSON.parse(cardEl.dataset.answers || '[]');
        document.getElementById('q-modal-title').textContent = cardEl.dataset.title || '';
        document.getElementById('q-modal-body').textContent = cardEl.dataset.body || '';
        document.getElementById('q-modal-author').textContent = cardEl.dataset.author || '';
        document.getElementById('q-modal-date').textContent = cardEl.dataset.date || '';
        document.getElementById('q-modal-question-id').value = cardEl.dataset.questionId || '';
        
        const answersList = document.getElementById('q-modal-answers-list');
        answersList.innerHTML = '';
        if (answers.length > 0) {
            answers.forEach(a => {
                const canDelete = isSuperAdmin || currentUserId == a.author_id;
                const deleteButtonHtml = canDelete ? `<button class="btn btn-sm btn-outline-danger delete-btn" data-id="${a.id}" data-type="answer"><i class="bi bi-trash-fill"></i></button>` : '';
                const answerDate = new Date(a.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                answersList.innerHTML += `
                    <div class="card answer-card mb-3" id="answer-${a.id}">
                        <div class="card-body">
                            <p class="card-text text-dark mb-2" style="font-size:14px; line-height:1.5;">${a.body.replace(/\n/g, '<br>')}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="answer-meta"><i class="bi bi-person-circle text-primary me-1"></i> Dijawab oleh ${a.author} pada ${answerDate}</small>
                                <div>${deleteButtonHtml}</div>
                            </div>
                        </div>
                    </div>`;
            });
        } else {
            answersList.innerHTML = '<p class="text-muted fst-italic p-3 text-center">Belum ada jawaban. Jadilah yang pertama memberikan solusi!</p>';
        }
    };

    // Submit Pertanyaan Baru
    document.getElementById('addQuestionForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Pertanyaan Anda telah diposting.' })
                    .then(() => window.location.reload());
                } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
            });
    });

    // Submit Jawaban Baru
    if (viewQuestionModal) {
        viewQuestionModal.addEventListener('submit', function(e) {
            if (e.target.classList.contains('add-answer-form')) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                formData.append('action', 'add_answer');
                fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                       Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Jawaban Anda telah dikirim.' }).then(() => window.location.reload());
                    } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
                });
            }
        });
        
        viewQuestionModal.addEventListener('click', function(e) {
            const target = e.target.closest('.delete-btn[data-type="answer"]');
            if (target) {
                handleDelete(target.dataset.id, 'answer');
            }
        });
    }

    // Fungsi terpusat untuk menghapus Q&A
    function handleDelete(id, type) {
        Swal.fire({
            title: 'Anda yakin?', text: "Data yang dihapus tidak bisa dikembalikan!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Batal', confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete_' + type);
                formData.append('id', id);

                fetch('ajax_qa_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const elementId = (type === 'question') ? 'question-row-' + id : type + '-' + id;
                        document.getElementById(elementId)?.remove();
                    } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
                });
            }
        });
    }

    // Customer Status Change Handlers
    function showNotification(message, isSuccess) {
        if (!notification) return;
        notification.textContent = message;
        notification.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        notification.style.display = 'block';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }
    
    if (tableBody) {
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
    }

    if ($.fn.select2) {
        $('#filter_kota, #filter_kategori, #filter_sales, #limit').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownAutoWidth: true
        });
    }
});
</script>