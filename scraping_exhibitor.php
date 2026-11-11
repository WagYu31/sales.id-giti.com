<?php
/**
 * scraping_exhibitor.php - Scraper & Database Exhibitor Krista / SIAL Interfood
 * Terintegrasi penuh dengan Sales CRM Loewix / GITI
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/role_permission_helper.php';

$currentPage = 'scraping_exhibitor.php';
date_default_timezone_set('Asia/Jakarta');

// Auto-ensure krista_exhibitors table
$conn->query("
CREATE TABLE IF NOT EXISTS `krista_exhibitors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exhibitor_id` int(11) DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `booth_number` varchar(100) DEFAULT NULL,
  `business_activity` text DEFAULT NULL,
  `products` text DEFAULT NULL,
  `lead_type` varchar(100) DEFAULT NULL,
  `detail_url` varchar(255) DEFAULT NULL,
  `logo_url` text DEFAULT NULL,
  `status_prospek` varchar(50) DEFAULT 'Belum Dihubungi',
  `customer_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_exhibitor_id` (`exhibitor_id`),
  KEY `idx_company_name` (`company_name`),
  KEY `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ════════════════════════════════════════════════════════════════════════════
// 1. AJAX / API HANDLERS
// ════════════════════════════════════════════════════════════════════════════

// Handle Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $country_filter = trim($_GET['country'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
    $search_query = trim($_GET['q'] ?? '');

    $where = ["1=1"];
    $params = [];
    $types = "";

    if (!empty($country_filter)) {
        $where[] = "country = ?";
        $params[] = $country_filter;
        $types .= "s";
    }
    if (!empty($status_filter)) {
        $where[] = "status_prospek = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    if (!empty($search_query)) {
        $where[] = "(company_name LIKE ? OR booth_number LIKE ? OR business_activity LIKE ? OR products LIKE ?)";
        $search_like = "%$search_query%";
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
        $types .= "ssss";
    }

    $sql = "SELECT id, exhibitor_id, company_name, country, booth_number, business_activity, products, detail_url, status_prospek, created_at FROM krista_exhibitors WHERE " . implode(" AND ", $where) . " ORDER BY id ASC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Exhibitor_Krista_Interfood_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['No', 'ID Pameran', 'Nama Perusahaan', 'Negara', 'Nomor Booth', 'Kategori Bisnis', 'Produk', 'Status Prospek', 'Link Detail'], ',', '"', "\\");

    $no = 1;
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $no++,
            $row['exhibitor_id'],
            $row['company_name'],
            $row['country'],
            $row['booth_number'],
            $row['business_activity'],
            $row['products'],
            $row['status_prospek'],
            $row['detail_url']
        ], ',', '"', "\\");
    }
    fclose($output);
    exit;
}

// Handle Add to CRM (Customer Lead)
if (isset($_POST['action']) && $_POST['action'] === 'add_to_crm') {
    header('Content-Type: application/json');
    $ex_id = intval($_POST['id'] ?? 0);

    if ($ex_id <= 0) {
        echo json_encode(['status' => false, 'message' => 'ID Exhibitor tidak valid.']);
        exit;
    }

    $q = $conn->prepare("SELECT * FROM krista_exhibitors WHERE id = ?");
    $q->bind_param("i", $ex_id);
    $q->execute();
    $exhibitor = $q->get_result()->fetch_assoc();

    if (!$exhibitor) {
        echo json_encode(['status' => false, 'message' => 'Data exhibitor tidak ditemukan.']);
        exit;
    }

    if (!empty($exhibitor['customer_id'])) {
        echo json_encode(['status' => true, 'message' => 'Perusahaan ini sudah terdaftar di CRM!', 'customer_id' => $exhibitor['customer_id']]);
        exit;
    }

    // Insert into customers table
    $sales_id = intval($_SESSION['id_user'] ?? $_SESSION['user_id'] ?? 1);
    $tgl_input = date('Y-m-d');
    $nama_toko = $exhibitor['company_name'];
    $kategori = !empty($exhibitor['business_activity']) && $exhibitor['business_activity'] !== '-' 
        ? "Exhibitor: " . $exhibitor['business_activity'] 
        : "Exhibitor Krista Interfood (Booth: " . $exhibitor['booth_number'] . ")";
    $deal = 'N';
    $kandidat = 'Y';
    $potensial = 'Y';
    $acc_boss = 'N';
    $acc_note = "Lead otomatis dari Scraper Krista Interfood (Asal: {$exhibitor['country']}, Booth: {$exhibitor['booth_number']})";

    $ins = $conn->prepare("
        INSERT INTO customers (sales_id, tgl_input, nama_toko, kategori, deal, kandidat, potensial, acc_boss, acc_boss_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->bind_param("issssssss", $sales_id, $tgl_input, $nama_toko, $kategori, $deal, $kandidat, $potensial, $acc_boss, $acc_note);

    if ($ins->execute()) {
        $new_cust_id = $conn->insert_id;
        // Update krista_exhibitors
        $up = $conn->prepare("UPDATE krista_exhibitors SET customer_id = ?, status_prospek = 'Tersimpan di CRM' WHERE id = ?");
        $up->bind_param("ii", $new_cust_id, $ex_id);
        $up->execute();

        echo json_encode([
            'status' => true, 
            'message' => 'Berhasil menambahkan perusahaan ke database CRM sebagai Kandidat Customer!',
            'customer_id' => $new_cust_id,
            'company_name' => $nama_toko
        ]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Gagal menyimpan ke CRM: ' . $conn->error]);
    }
    exit;
}

// Handle Live Scraping Batch
if (isset($_POST['action']) && $_POST['action'] === 'run_scrape_page') {
    header('Content-Type: application/json');
    $page = intval($_POST['page'] ?? 1);
    if ($page < 1 || $page > 50) $page = 1;

    $url = "https://sialinterfood.com/api/ajax?type=search&page=" . $page;
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                        "X-Requested-With: XMLHttpRequest\r\n" .
                        "Referer: https://sialinterfood.com/list-exhibitor\r\n",
            "timeout" => 15
        ]
    ];
    $context = stream_context_create($opts);
    $res = @file_get_contents($url, false, $context);

    if (!$res) {
        echo json_encode(['status' => false, 'message' => "Gagal mengambil data halaman $page"]);
        exit;
    }

    $json_res = json_decode($res, true);
    $html_content = $json_res['html'] ?? '';

    $matches = [];
    preg_match_all('/<textarea[^>]*data-item[^>]*>([\s\S]*?)<\/textarea>/i', $html_content, $matches);

    $inserted = 0;
    if (!empty($matches[1])) {
        $stmt = $conn->prepare("
            INSERT INTO `krista_exhibitors` 
            (`exhibitor_id`, `company_name`, `country`, `booth_number`, `business_activity`, `products`, `lead_type`, `detail_url`, `logo_url`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                `company_name` = VALUES(`company_name`),
                `country` = VALUES(`country`),
                `booth_number` = VALUES(`booth_number`),
                `business_activity` = VALUES(`business_activity`),
                `products` = VALUES(`products`),
                `lead_type` = VALUES(`lead_type`),
                `detail_url` = VALUES(`detail_url`),
                `logo_url` = VALUES(`logo_url`)
        ");

        foreach ($matches[1] as $raw_item) {
            $decoded_raw = html_entity_decode(trim($raw_item), ENT_QUOTES, 'UTF-8');
            $d = json_decode($decoded_raw, true);
            if (!$d) continue;

            $ex_id = $d['exhibitor_exhibition_id'] ?? null;
            $name = trim($d['exhibitor_name'] ?? '');
            $country = trim($d['country'] ?? '');
            $booth = trim($d['booth_number'] ?? '');
            $activity = trim($d['business_activity'] ?? '');
            $lead_type = trim($d['exhibitor_lead_type'] ?? '');
            $detail_url = $ex_id ? "https://sialinterfood.com/detail-exhibitor/{$ex_id}" : "";
            $logo = trim($d['logo'] ?? '');

            $products = $d['product_index'] ?? [];
            if (is_array($products)) {
                $prod_str = implode('; ', array_filter($products));
            } else {
                $prod_str = strval($products);
            }

            if (!empty($name)) {
                $stmt->bind_param("issssssss", $ex_id, $name, $country, $booth, $activity, $prod_str, $lead_type, $detail_url, $logo);
                if ($stmt->execute()) {
                    $inserted++;
                }
            }
        }
    }

    $total_all = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors")->fetch_assoc()['t'] ?? 0;
    echo json_encode([
        'status' => true,
        'page' => $page,
        'added_this_page' => $inserted,
        'total_db' => $total_all
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// 2. STATS DATA
// ════════════════════════════════════════════════════════════════════════════
$stat_total = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors")->fetch_assoc()['t'] ?? 0;
$stat_indo = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE country = 'Indonesia'")->fetch_assoc()['t'] ?? 0;
$stat_intl = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE country != 'Indonesia' AND country != ''")->fetch_assoc()['t'] ?? 0;
$stat_crm = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE customer_id IS NOT NULL")->fetch_assoc()['t'] ?? 0;

// Countries list for filter
$countries_res = $conn->query("SELECT country, COUNT(*) as c FROM krista_exhibitors WHERE country != '' GROUP BY country ORDER BY c DESC");

include "includes/header.php";
?>

<style>
/* ── Premium Styling for Scraper Page ── */
.scraper-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #0284C7 100%);
    border-radius: 20px;
    padding: 32px 36px;
    color: #fff;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.15);
}
.scraper-hero::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.25) 0%, transparent 70%);
}
.stat-card-scraper {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 16px;
}
.stat-card-scraper:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
}
.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.exhibitor-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    object-fit: contain;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    padding: 4px;
}
.avatar-fallback {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
    color: #fff;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #DBEAFE;
}
.booth-badge {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.5px;
    display: inline-block;
}
.country-pill {
    background: #F1F5F9;
    color: #334155;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.progress-bar-animated-custom {
    transition: width 0.3s ease;
}
</style>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid px-4 py-3">

    <!-- ─── BENTO HERO BANNER ─── -->
    <div class="scraper-hero">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); font-size: 12px; font-weight: 700;">
                    <i class="bi bi-broadcast text-warning"></i>
                    <span>LEAD GENERATION TOOL</span>
                </div>
                <h2 class="fw-bold mb-2 text-white" style="letter-spacing: -0.5px;">
                    Scraper Database Exhibitor — Krista / SIAL Interfood
                </h2>
                <p class="text-white-50 mb-3" style="font-size: 14px; max-width: 650px;">
                    Database otomatis seluruh perusahaan pameran makanan, minuman, dan teknologi kuliner terbesar di Indonesia. Temukan prospek klien baru dan konversi langsung ke database Sales CRM dengan satu klik.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-warning px-4 py-2 fw-bold text-dark rounded-pill shadow-sm" id="btnStartScrape" onclick="startLiveScrape()">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync / Scrape Ulang
                    </button>
                    <a href="scraping_exhibitor.php?action=export_csv" class="btn btn-outline-light px-4 py-2 fw-semibold rounded-pill">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Excel (CSV)
                    </a>
                    <a href="https://sialinterfood.com/list-exhibitor" target="_blank" class="btn btn-link text-white-50 text-decoration-none">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Kunjungi Web Asli
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <img src="assets/images/loewix_sales_logo_white.png" alt="Loewix" style="max-height: 48px; opacity: 0.85;" onerror="this.remove()">
                <div class="small text-white-50 mt-2">Sumber: kristainterfood.com / sialinterfood.com</div>
            </div>
        </div>
    </div>

    <!-- ─── STAT CARDS ─── -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-scraper">
                <div class="stat-icon" style="background: rgba(37, 99, 235, 0.12); color: #2563EB;">
                    <i class="bi bi-buildings-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Perusahaan</div>
                    <h3 class="fw-bold mb-0 text-dark" id="statTotal"><?php echo number_format($stat_total); ?></h3>
                    <div class="small text-primary fw-medium">Peserta Terdata</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-scraper">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.12); color: #EF4444;">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Perusahaan Lokal</div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stat_indo); ?></h3>
                    <div class="small text-danger fw-medium">Indonesia (48%)</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-scraper">
                <div class="stat-icon" style="background: rgba(14, 165, 233, 0.12); color: #0EA5E9;">
                    <i class="bi bi-globe2"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Perusahaan Internasional</div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stat_intl); ?></h3>
                    <div class="small text-info fw-medium">China, Korea, dll.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-scraper">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10B981;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Prospek di CRM</div>
                    <h3 class="fw-bold mb-0 text-dark" id="statCrm"><?php echo number_format($stat_crm); ?></h3>
                    <div class="small text-success fw-medium">Kandidat Customer</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── SCRAPING LIVE PROGRESS BOX (Hidden by default) ─── -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 d-none" id="scrapingProgressBox" style="background: #F8FAFC; border: 1px solid #CBD5E1 !important;">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="fw-bold text-dark" id="scrapingProgressText">Memulai Scraping...</span>
                </div>
                <span class="badge bg-primary rounded-pill px-3 py-1" id="scrapingPercent">0%</span>
            </div>
            <div class="progress rounded-pill" style="height: 10px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary progress-bar-animated-custom" id="scrapingProgressBar" style="width: 0%;"></div>
            </div>
            <div class="small text-muted mt-2" id="scrapingLog">Mengambil halaman pameran secara bertahap...</div>
        </div>
    </div>

    <!-- ─── TABLE FILTER & DATA CARD ─── -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="border: 1px solid #E2E8F0 !important;">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Filter Asal Negara</label>
                    <select class="form-select form-select-sm rounded-3" id="filterCountry" onchange="applyFilters()">
                        <option value="">Semua Negara (All Countries)</option>
                        <?php while ($c = $countries_res->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($c['country']); ?>">
                                <?php echo htmlspecialchars($c['country']); ?> (<?php echo $c['c']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Status di CRM</label>
                    <select class="form-select form-select-sm rounded-3" id="filterStatus" onchange="applyFilters()">
                        <option value="">Semua Status</option>
                        <option value="Belum Dihubungi">Belum Masuk CRM</option>
                        <option value="Tersimpan di CRM">Sudah di CRM</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Pencarian Cepat</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control form-control-sm border-start-0 rounded-end-3" id="filterSearch" placeholder="Cari nama PT, produk, nomor booth..." onkeyup="applyFilters()">
                    </div>
                </div>
                <div class="col-md-2 text-md-end mt-auto">
                    <button class="btn btn-sm btn-outline-secondary w-100 rounded-3" onclick="resetFilters()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableExhibitors">
                    <thead class="bg-light text-muted small fw-bold">
                        <tr>
                            <th class="ps-4" style="width: 50px;">No</th>
                            <th>Perusahaan / Exhibitor</th>
                            <th style="width: 140px;">Asal Negara</th>
                            <th style="width: 120px;">Nomor Booth</th>
                            <th>Aktivitas &amp; Kategori Produk</th>
                            <th style="width: 130px;">Status CRM</th>
                            <th class="text-end pe-4" style="width: 170px;">Aksi Sales</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Data populated via server-render / datatable -->
                        <?php
                        $list_q = $conn->query("SELECT * FROM krista_exhibitors ORDER BY (customer_id IS NOT NULL) DESC, id ASC LIMIT 200");
                        $no = 1;
                        while ($row = $list_q->fetch_assoc()):
                            $initials = strtoupper(substr($row['company_name'], 0, 2));
                            $is_crm = !empty($row['customer_id']);
                        ?>
                        <tr id="row-<?php echo $row['id']; ?>" class="<?php echo $is_crm ? 'table-light' : ''; ?>">
                            <td class="ps-4 text-muted small"><?php echo $no++; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($row['logo_url']) && strpos($row['logo_url'], 'pinpng.com') === false): ?>
                                        <img src="<?php echo htmlspecialchars($row['logo_url']); ?>" class="exhibitor-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="avatar-fallback" style="display:none;"><?php echo $initials; ?></div>
                                    <?php else: ?>
                                        <div class="avatar-fallback"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo htmlspecialchars($row['company_name']); ?></div>
                                        <?php if (!empty($row['detail_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['detail_url']); ?>" target="_blank" class="small text-muted text-decoration-none">
                                                <i class="bi bi-box-arrow-up-right" style="font-size: 11px;"></i> Lihat Profil Pameran
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="country-pill">
                                    <?php echo htmlspecialchars($row['country'] ?: '-'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="booth-badge">
                                    <?php echo htmlspecialchars($row['booth_number'] ?: '-'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['business_activity']) && $row['business_activity'] !== '-'): ?>
                                    <div class="small fw-semibold text-dark"><?php echo htmlspecialchars($row['business_activity']); ?></div>
                                <?php endif; ?>
                                <div class="small text-muted text-truncate" style="max-width: 320px;" title="<?php echo htmlspecialchars($row['products']); ?>">
                                    <?php echo htmlspecialchars($row['products'] ?: '-'); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($is_crm): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> Di CRM
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">
                                        Belum Ada
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <?php if ($is_crm): ?>
                                        <a href="customer_management.php?search=<?php echo urlencode($row['company_name']); ?>" class="btn btn-sm btn-outline-success rounded-3" title="Buka di CRM">
                                            <i class="bi bi-box-arrow-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-primary rounded-3 btn-add-crm" onclick="addToCrm(<?php echo $row['id']; ?>, '<?php echo addslashes($row['company_name']); ?>')" title="Jadikan Kandidat Customer">
                                            <i class="bi bi-person-plus-fill me-1"></i> + CRM
                                        </button>
                                    <?php endif; ?>
                                    <a href="https://www.google.com/search?q=<?php echo urlencode($row['company_name'] . ' telepon email kontak'); ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3" title="Cari Kontak di Google">
                                        <i class="bi bi-google"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-3 px-4 text-muted small d-flex justify-content-between align-items-center">
            <div>Menampilkan 200 data pertama. Gunakan tombol download Excel di atas untuk mendapatkan seluruh 858 data lengkap.</div>
            <a href="scraping_exhibitor.php?action=export_csv" class="btn btn-sm btn-success rounded-pill px-3">
                <i class="bi bi-download me-1"></i> Export 858 Data ke Excel
            </a>
        </div>
    </div>

</div>

<script>
// Live Scraping Functionality
let isScraping = false;
async function startLiveScrape() {
    if (isScraping) return;
    
    const confirm = await Swal.fire({
        title: 'Mulai Scraping Krista Interfood?',
        text: 'Sistem akan mengambil data dari 43 halaman pameran secara real-time dan menyimpannya ke database.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Jalankan Sekarang',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#2563eb'
    });

    if (!confirm.isConfirmed) return;

    isScraping = true;
    $('#scrapingProgressBox').removeClass('d-none');
    $('#btnStartScrape').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Mengambil data...');

    const totalPages = 43;
    let totalAdded = 0;

    for (let p = 1; p <= totalPages; p++) {
        const percent = Math.round((p / totalPages) * 100);
        $('#scrapingProgressBar').css('width', percent + '%');
        $('#scrapingPercent').text(percent + '%');
        $('#scrapingProgressText').text(`Sedang scraping halaman ${p} dari ${totalPages}...`);
        $('#scrapingLog').text(`Mengunduh data pameran dan parsing data perusahaan...`);

        try {
            const formData = new FormData();
            formData.append('action', 'run_scrape_page');
            formData.append('page', p);

            const res = await fetch('scraping_exhibitor.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status) {
                totalAdded += data.added_this_page;
                $('#statTotal').text(data.total_db.toLocaleString());
            }
        } catch (err) {
            console.error('Error page ' + p, err);
        }
    }

    isScraping = false;
    $('#scrapingProgressText').html('<span class="text-success"><i class="bi bi-check-circle-fill"></i> Scraping Selesai!</span>');
    $('#scrapingLog').text(`Semua 43 halaman telah selesai diproses. Memuat ulang halaman...`);
    $('#btnStartScrape').prop('disabled', false).html('<i class="bi bi-arrow-repeat me-1"></i> Sync / Scrape Ulang');

    Swal.fire({
        title: 'Scraping Berhasil!',
        text: `Data exhibitor berhasil disinkronisasi ke database. Halaman akan diperbarui.`,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    }).then(() => {
        window.location.reload();
    });
}

// Add to CRM Action
async function addToCrm(id, companyName) {
    const confirm = await Swal.fire({
        title: 'Tambah ke CRM?',
        html: `Apakah Anda ingin memasukkan <b>${companyName}</b> sebagai <b>Kandidat Customer</b> di Sales CRM?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Tambahkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981'
    });

    if (!confirm.isConfirmed) return;

    try {
        const formData = new FormData();
        formData.append('action', 'add_to_crm');
        formData.append('id', id);

        const res = await fetch('scraping_exhibitor.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status) {
            Swal.fire({
                title: 'Berhasil!',
                text: data.message,
                icon: 'success',
                timer: 1800,
                showConfirmButton: false
            });

            // Update row UI
            const row = $(`#row-${id}`);
            row.find('td:nth-child(6)').html(`
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                    <i class="bi bi-check-circle-fill me-1"></i> Di CRM
                </span>
            `);
            row.find('.btn-add-crm').replaceWith(`
                <a href="customer_management.php?search=${encodeURIComponent(companyName)}" class="btn btn-sm btn-outline-success rounded-3" title="Buka di CRM">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            `);

            // Update counter
            const currentCrm = parseInt($('#statCrm').text().replace(/,/g, '')) || 0;
            $('#statCrm').text((currentCrm + 1).toLocaleString());
        } else {
            Swal.fire('Gagal', data.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
    }
}

// Client-side quick filter
function applyFilters() {
    const country = $('#filterCountry').val().toLowerCase();
    const status = $('#filterStatus').val();
    const query = $('#filterSearch').val().toLowerCase();

    $('#tableBody tr').each(function() {
        const row = $(this);
        const rowCountry = row.find('td:nth-child(3)').text().toLowerCase();
        const rowStatus = row.find('td:nth-child(6)').text();
        const rowText = row.text().toLowerCase();

        let show = true;
        if (country && !rowCountry.includes(country)) show = false;
        if (status === 'Tersimpan di CRM' && !rowStatus.includes('Di CRM')) show = false;
        if (status === 'Belum Dihubungi' && rowStatus.includes('Di CRM')) show = false;
        if (query && !rowText.includes(query)) show = false;

        row.toggle(show);
    });
}

function resetFilters() {
    $('#filterCountry').val('');
    $('#filterStatus').val('');
    $('#filterSearch').val('');
    applyFilters();
}
</script>

<?php include "includes/footer.php"; ?>
