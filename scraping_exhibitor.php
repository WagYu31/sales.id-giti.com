<?php
/**
 * scraping_exhibitor.php - Lead Harvester & Pusat Database Pameran Industri
 * Mendukung Multi-Event: Krista Interfood, ALLPACK, Indobuildtech, & Website Pameran Apapun
 * Terintegrasi penuh dengan Sales CRM Loewix / GITI
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/role_permission_helper.php';

$currentPage = 'scraping_exhibitor.php';
date_default_timezone_set('Asia/Jakarta');

// ════════════════════════════════════════════════════════════════════════════
// DATABASE SCHEMA & AUTO-MIGRATION
// ════════════════════════════════════════════════════════════════════════════
$conn->set_charset("utf8mb4");
$conn->query("
CREATE TABLE IF NOT EXISTS `krista_exhibitors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(150) DEFAULT 'Krista Interfood 2026',
  `source_url` varchar(255) DEFAULT NULL,
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
  KEY `idx_company_name` (`company_name`),
  KEY `idx_country` (`country`),
  KEY `idx_event_name` (`event_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Self-healing migration for existing databases
$col_check = $conn->query("SHOW COLUMNS FROM `krista_exhibitors` LIKE 'event_name'");
if ($col_check && $col_check->num_rows == 0) {
    $conn->query("ALTER TABLE `krista_exhibitors` ADD COLUMN `event_name` varchar(150) DEFAULT 'Krista Interfood 2026' AFTER `id`");
    $conn->query("ALTER TABLE `krista_exhibitors` ADD COLUMN `source_url` varchar(255) DEFAULT NULL AFTER `event_name`");
    $conn->query("ALTER TABLE `krista_exhibitors` ADD INDEX `idx_event_name` (`event_name`)");
}
$idx_check = $conn->query("SHOW INDEX FROM `krista_exhibitors` WHERE Key_name = 'idx_exhibitor_id'");
if ($idx_check && $idx_check->num_rows > 0) {
    $conn->query("ALTER TABLE `krista_exhibitors` DROP INDEX `idx_exhibitor_id`");
}

// Self-healing migration for contact columns (Phone, Email, Website, Address, City, Province)
$contact_cols = [
    'phone' => 'varchar(100) DEFAULT NULL AFTER `booth_number`',
    'email' => 'varchar(150) DEFAULT NULL AFTER `phone`',
    'website' => 'varchar(255) DEFAULT NULL AFTER `email`',
    'address' => 'text DEFAULT NULL AFTER `website`',
    'city' => 'varchar(100) DEFAULT NULL AFTER `address`',
    'province' => 'varchar(100) DEFAULT NULL AFTER `city`'
];
foreach ($contact_cols as $cname => $cdef) {
    $chk = $conn->query("SHOW COLUMNS FROM `krista_exhibitors` LIKE '$cname'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE `krista_exhibitors` ADD COLUMN `$cname` $cdef");
    }
}

// Auto-seed missing records from JSON if count is less than 858
$curr_cnt = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE event_name = 'Krista Interfood 2026'")->fetch_assoc()['t'] ?? 0;
if ($curr_cnt < 858 && file_exists(__DIR__ . '/krista_interfood_exhibitors.json')) {
    $json_raw = file_get_contents(__DIR__ . '/krista_interfood_exhibitors.json');
    $json_data = json_decode($json_raw, true);
    if (!empty($json_data)) {
        $stmt_seed = $conn->prepare("
            INSERT INTO `krista_exhibitors` 
            (`event_name`, `source_url`, `exhibitor_id`, `company_name`, `country`, `booth_number`, `phone`, `email`, `website`, `address`, `city`, `province`, `business_activity`, `products`, `lead_type`, `detail_url`, `logo_url`)
            VALUES ('Krista Interfood 2026', 'https://sialinterfood.com', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($json_data as $d) {
            $ex_id = $d['id'] ?? null;
            $name = trim($d['company_name'] ?? '');
            $country = trim($d['country'] ?? '');
            $booth = trim($d['booth_number'] ?? '');
            $phone = trim($d['phone'] ?? '');
            $email = trim($d['email'] ?? '');
            $web = trim($d['website'] ?? '');
            $addr = trim($d['address'] ?? '');
            $city = trim($d['city'] ?? '');
            $prov = trim($d['province'] ?? '');
            $activity = trim($d['business_activity'] ?? '');
            $lead_type = trim($d['lead_type'] ?? '');
            $detail_url = trim($d['detail_url'] ?? '');
            $logo = trim($d['logo_url'] ?? '');
            $prods = is_array($d['products'] ?? '') ? implode('; ', $d['products']) : strval($d['products'] ?? '');

            // Check if exhibitor_id already exists in this event
            $chk = $conn->prepare("SELECT id FROM krista_exhibitors WHERE event_name = 'Krista Interfood 2026' AND exhibitor_id = ?");
            $chk->bind_param("i", $ex_id);
            $chk->execute();
            if ($chk->get_result()->num_rows == 0) {
                $stmt_seed->bind_param("issssssssssssssss", $ex_id, $name, $country, $booth, $phone, $email, $web, $addr, $city, $prov, $activity, $prods, $lead_type, $detail_url, $logo);
                $stmt_seed->execute();
            }
        }
    }
}

// Auto-sync contact details into existing database records if not yet enriched
$chk_contacts = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE (phone IS NOT NULL AND phone != '') OR (email IS NOT NULL AND email != '')")->fetch_assoc()['t'] ?? 0;
if ($chk_contacts < 100 && file_exists(__DIR__ . '/krista_interfood_exhibitors.json')) {
    $json_raw = file_get_contents(__DIR__ . '/krista_interfood_exhibitors.json');
    $json_data = json_decode($json_raw, true);
    if (!empty($json_data)) {
        $stmt_upd = $conn->prepare("
            UPDATE `krista_exhibitors` 
            SET `phone` = ?, `email` = ?, `website` = ?, `address` = ?, `city` = ?, `province` = ?
            WHERE `exhibitor_id` = ? OR `company_name` = ?
        ");
        foreach ($json_data as $d) {
            $ex_id = $d['id'] ?? 0;
            $cname = trim($d['company_name'] ?? '');
            $phone = trim($d['phone'] ?? '');
            $email = trim($d['email'] ?? '');
            $web = trim($d['website'] ?? '');
            $addr = trim($d['address'] ?? '');
            $city = trim($d['city'] ?? '');
            $prov = trim($d['province'] ?? '');
            if ($phone || $email || $web || $addr) {
                $stmt_upd->bind_param("ssssssis", $phone, $email, $web, $addr, $city, $prov, $ex_id, $cname);
                $stmt_upd->execute();
            }
        }
    }
}

// Country flag badge helper
function getCountryFlagBadge($country) {
    $c = trim($country ?? '');
    $flags = [
        'Indonesia' => '🇮🇩',
        'China' => '🇨🇳',
        'South Korea' => '🇰🇷',
        'Korea' => '🇰🇷',
        'Malaysia' => '🇲🇾',
        'Vietnam' => '🇻🇳',
        'Türkiye' => '🇹🇷',
        'Turkey' => '🇹🇷',
        'Saudi Arabia' => '🇸🇦',
        'Palestine' => '🇵🇸',
        'Singapore' => '🇸🇬',
        'Thailand' => '🇹🇭',
        'Japan' => '🇯🇵',
        'India' => '🇮🇳',
        'Germany' => '🇩🇪',
        'Italy' => '🇮🇹',
        'United States' => '🇺🇸',
        'USA' => '🇺🇸',
        'Taiwan' => '🇹🇼',
        'Australia' => '🇦🇺',
        'United Kingdom' => '🇬🇧',
        'France' => '🇫🇷',
        'Spain' => '🇪🇸',
        'Netherlands' => '🇳🇱'
    ];
    $flag = $flags[$c] ?? '🌐';
    return '<span class="country-pill">' . $flag . ' ' . htmlspecialchars($c ?: 'Lainnya') . '</span>';
}

// Helper: Format Contacts (Phone, WhatsApp, Email, Web, Address)
function formatContactBadges($phone, $email, $website, $address, $city) {
    $html = '<div class="d-flex flex-column gap-1 align-items-start">';
    $has_any = false;

    if (!empty($phone)) {
        $has_any = true;
        $clean_num = preg_replace('/[^0-9]/', '', $phone);
        $is_wa = false;
        $wa_link = '';
        if (substr($clean_num, 0, 2) === '08') {
            $is_wa = true;
            $wa_link = 'https://wa.me/62' . substr($clean_num, 1);
        } elseif (substr($clean_num, 0, 3) === '628') {
            $is_wa = true;
            $wa_link = 'https://wa.me/' . $clean_num;
        }

        if ($is_wa) {
            $html .= '<a href="' . $wa_link . '" target="_blank" class="contact-chip contact-wa" title="Chat WhatsApp (' . htmlspecialchars($phone) . ')"><i class="bi bi-whatsapp"></i> ' . htmlspecialchars($phone) . '</a>';
        } else {
            $html .= '<a href="tel:' . htmlspecialchars($clean_num) . '" class="contact-chip contact-phone" title="Telepon (' . htmlspecialchars($phone) . ')"><i class="bi bi-telephone-fill"></i> ' . htmlspecialchars($phone) . '</a>';
        }
    }

    if (!empty($email)) {
        $has_any = true;
        $html .= '<a href="mailto:' . htmlspecialchars($email) . '" class="contact-chip contact-email" title="Kirim Email (' . htmlspecialchars($email) . ')"><i class="bi bi-envelope-fill"></i> ' . htmlspecialchars($email) . '</a>';
    }

    if (!empty($website)) {
        $has_any = true;
        $clean_web = preg_replace('/^https?:\/\//i', '', rtrim($website, '/'));
        $web_href = (strpos($website, 'http') === 0) ? $website : 'https://' . $website;
        $html .= '<a href="' . htmlspecialchars($web_href) . '" target="_blank" class="contact-chip contact-web" title="Kunjungi Website (' . htmlspecialchars($website) . ')"><i class="bi bi-globe"></i> ' . htmlspecialchars($clean_web) . '</a>';
    }

    if (!empty($address)) {
        $has_any = true;
        $loc_text = !empty($city) ? $city : (strlen($address) > 30 ? mb_substr($address, 0, 30) . '...' : $address);
        $html .= '<span class="contact-chip contact-addr" title="' . htmlspecialchars($address) . '"><i class="bi bi-geo-alt-fill"></i> ' . htmlspecialchars($loc_text) . '</span>';
    }

    if (!$has_any) {
        $html .= '<span class="text-muted small" style="font-size: 11.5px;">-</span>';
    }

    $html .= '</div>';
    return $html;
}

// ════════════════════════════════════════════════════════════════════════════
// 1. AJAX / API HANDLERS
// ════════════════════════════════════════════════════════════════════════════

// Handle AJAX Table Data & Pagination (Ultra Robust Direct Query)
if (isset($_GET['action']) && $_GET['action'] === 'get_table_data') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = intval($_GET['limit'] ?? 50);
    if ($limit < 1 || $limit > 1000) $limit = 50;

    $event_filter = trim($_GET['event'] ?? '');
    $country_filter = trim($_GET['country'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
    $search_query = trim($_GET['q'] ?? '');

    $where = ["1=1"];

    if (!empty($event_filter)) {
        $where[] = "event_name = '" . $conn->real_escape_string($event_filter) . "'";
    }
    if (!empty($country_filter)) {
        $where[] = "country = '" . $conn->real_escape_string($country_filter) . "'";
    }
    if (!empty($status_filter)) {
        $where[] = "status_prospek = '" . $conn->real_escape_string($status_filter) . "'";
    }
    if (!empty($search_query)) {
        $q_esc = $conn->real_escape_string($search_query);
        $where[] = "(company_name LIKE '%$q_esc%' OR booth_number LIKE '%$q_esc%' OR business_activity LIKE '%$q_esc%' OR products LIKE '%$q_esc%' OR phone LIKE '%$q_esc%' OR email LIKE '%$q_esc%' OR website LIKE '%$q_esc%' OR address LIKE '%$q_esc%' OR city LIKE '%$q_esc%')";
    }

    $where_sql = implode(" AND ", $where);

    // Total filtered count
    $count_res = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE $where_sql");
    $total_rows = $count_res ? intval($count_res->fetch_assoc()['t'] ?? 0) : 0;

    $total_pages = max(1, ceil($total_rows / $limit));
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    $offset = ($page - 1) * $limit;

    // Fetch page items with direct safe LIMIT offset, limit
    $data_sql = "SELECT * FROM krista_exhibitors WHERE $where_sql ORDER BY (customer_id IS NOT NULL) DESC, id ASC LIMIT $offset, $limit";
    $d_res = $conn->query($data_sql);

    $formatted_rows = [];
    $start_no = $offset + 1;
    if ($d_res) {
        while ($r = $d_res->fetch_assoc()) {
            $formatted_rows[] = [
                'no' => $start_no++,
                'id' => $r['id'],
                'exhibitor_id' => $r['exhibitor_id'],
                'event_name' => $r['event_name'] ?: 'Krista Interfood 2026',
                'company_name' => $r['company_name'],
                'country' => $r['country'],
                'country_flag' => getCountryFlagBadge($r['country']),
                'booth_number' => $r['booth_number'] ?: '-',
                'phone' => $r['phone'] ?? '',
                'email' => $r['email'] ?? '',
                'website' => $r['website'] ?? '',
                'address' => $r['address'] ?? '',
                'city' => $r['city'] ?? '',
                'province' => $r['province'] ?? '',
                'contacts_html' => formatContactBadges($r['phone'] ?? '', $r['email'] ?? '', $r['website'] ?? '', $r['address'] ?? '', $r['city'] ?? ''),
                'business_activity' => $r['business_activity'] ?: '-',
                'products' => mb_substr($r['products'] ?: '-', 0, 200),
                'status_prospek' => $r['status_prospek'],
                'customer_id' => $r['customer_id'],
                'detail_url' => $r['detail_url'],
                'logo_url' => $r['logo_url'],
                'initials' => strtoupper(substr($r['company_name'], 0, 2))
            ];
        }
    }

    $json_out = json_encode([
        'status' => true,
        'rows' => $formatted_rows,
        'pagination' => [
            'current_page' => $page,
            'limit' => $limit,
            'total_rows' => $total_rows,
            'total_pages' => $total_pages,
            'from' => $total_rows > 0 ? $offset + 1 : 0,
            'to' => min($offset + $limit, $total_rows)
        ]
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

    if ($json_out === false) {
        echo json_encode(['status' => false, 'message' => 'JSON encode error: ' . json_last_error_msg()]);
    } else {
        echo $json_out;
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// HANDLE EXPORT EXCEL PROFESIONAL DENGAN PIVOT & MULTI-SHEET (.XLSX)
// ════════════════════════════════════════════════════════════════════════════
if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    @error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    @ini_set('display_errors', '0');

    $event_filter = trim($_GET['event'] ?? '');
    $country_filter = trim($_GET['country'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
    $search_query = trim($_GET['q'] ?? '');

    $where = ["1=1"];

    if (!empty($event_filter)) {
        $where[] = "event_name = '" . $conn->real_escape_string($event_filter) . "'";
    }
    if (!empty($country_filter)) {
        $where[] = "country = '" . $conn->real_escape_string($country_filter) . "'";
    }
    if (!empty($status_filter)) {
        $where[] = "status_prospek = '" . $conn->real_escape_string($status_filter) . "'";
    }
    if (!empty($search_query)) {
        $q_esc = $conn->real_escape_string($search_query);
        $where[] = "(company_name LIKE '%$q_esc%' OR booth_number LIKE '%$q_esc%' OR business_activity LIKE '%$q_esc%' OR products LIKE '%$q_esc%' OR phone LIKE '%$q_esc%' OR email LIKE '%$q_esc%' OR website LIKE '%$q_esc%' OR address LIKE '%$q_esc%' OR city LIKE '%$q_esc%')";
    }

    $where_sql = implode(" AND ", $where);
    $sql = "SELECT id, event_name, exhibitor_id, company_name, country, booth_number, phone, email, website, address, city, province, business_activity, products, detail_url, status_prospek, customer_id, created_at FROM krista_exhibitors WHERE $where_sql ORDER BY id ASC";
    $result = $conn->query($sql);

    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;
        }
    }

    $vendor_autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    }

    // Jika library PhpSpreadsheet tidak terpasang, redirect ke export CSV
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        header('Location: scraping_exhibitor.php?action=export_csv' . (!empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/action=[^&]*&?/', '', $_SERVER['QUERY_STRING']) : ''));
        exit;
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    // -----------------------------------------------------------------
    // 1. DATA AGGREGATION & PIVOT METRICS CALCULATION
    // -----------------------------------------------------------------
    $total_rows = count($rows);
    $total_local = 0;
    $total_intl = 0;
    $total_with_phone = 0;
    $total_with_email = 0;
    $total_with_web = 0;
    $total_complete_contact = 0;
    $total_ready_contact = 0;
    $total_crm = 0;

    $by_country = [];
    $by_sector = [
        'Mesin & Teknologi Pengolahan (Food Processing Machinery)' => [
            'name' => 'Mesin & Teknologi Pengolahan Makanan',
            'keywords' => ['processing', 'machinery', 'technology', 'conveyor', 'equipment', 'mesin', 'pengolahan', 'fryer', 'separator', 'cooker', 'mixer', 'extruder', 'industrial'],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Industri Tahan Debu & Panas, AI Monitoring Jalur Produksi, Camera Anti-Explosion'
        ],
        'Teknologi Pengemasan & Labelling (Packaging)' => [
            'name' => 'Teknologi Pengemasan & Labelling',
            'keywords' => ['packaging', 'packing', 'kemasan', 'sealing', 'label', 'printing', 'carton', 'wrapping', 'filler', 'capping', 'blister', 'pouch'],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Gudang Logistik, AI Monitoring Packing Line, BAST Rekam Pengiriman Barang'
        ],
        'Bakery, Pastry, Cokelat & Manisan' => [
            'name' => 'Bakery, Pastry, Cokelat & Manisan',
            'keywords' => ['bakery', 'pastry', 'bread', 'cake', 'confectionery', 'chocolate', 'biscuit', 'roti', 'kue', 'sweet', 'candy', 'cookies', 'wafer'],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Central Kitchen, Monitoring Display Etalase Toko, Integrasi POS Kasir Anti-Fraud'
        ],
        'Minuman, Kopi, Teh & Sirup (Beverage & Coffee)' => [
            'name' => 'Minuman, Kopi, Teh & Sirup',
            'keywords' => ['beverage', 'drink', 'coffee', 'tea', 'juice', 'syrup', 'kopi', 'teh', 'water', 'roaster', 'brewery', 'botol', 'soda'],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Outlet Roastery & Cafe, Monitoring Penyimpanan Bahan Baku, Kasir POS CCTV'
        ],
        'Daging, Unggas, Seafood & Cold Storage' => [
            'name' => 'Daging, Unggas, Seafood & Cold Storage',
            'keywords' => ['meat', 'poultry', 'chicken', 'beef', 'seafood', 'fish', 'frozen', 'daging', 'ikan', 'shrimp', 'cold storage', 'chiller', 'freezer'],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Cold Storage IP67/IP68 Anti-Kondensasi, Monitoring Suhu & Keamanan Freezer 24 Jam'
        ],
        'Bahan Baku, Bumbu & Food Ingredients' => [
            'name' => 'Bahan Baku, Bumbu & Ingredients',
            'keywords' => ['ingredient', 'flavor', 'flavour', 'spice', 'seasoning', 'additive', 'sauce', 'bumbu', 'flour', 'sugar', 'salt', 'oil', 'yeast', 'ekstrak'],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Ruang Timbang & Mixing Bahan Baku, Warehouse CCTV Perimeter, Akses Kontrol Karyawan'
        ],
        'Peralatan Dapur, Hotel & Restoran (HORECA)' => [
            'name' => 'Peralatan Dapur, Hotel & Restoran (HORECA)',
            'keywords' => ['horeca', 'hotel', 'restaurant', 'catering', 'kitchen', 'oven', 'stainless', 'refrigerator', 'utensil', 'tableware', 'dishwasher'],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Dapur Komersial Restoran, Smart AI Deteksi Asap/Api, Kamera Kasir & Tamu'
        ],
        'Distribusi, Logistik & Trading Retail' => [
            'name' => 'Distribusi, Logistik & Trading Retail',
            'keywords' => ['trading', 'distributor', 'import', 'export', 'logistics', 'retail', 'supplier', 'perdagangan', 'grosir', 'distribusi'],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Gudang Transit Distribusi, ANPR Camera Gerbang Truk, PTZ Outdoor 360 Derajat'
        ],
        'Sektor Industri Lainnya (General Industry)' => [
            'name' => 'Sektor Industri Lainnya / Multi-Produk',
            'keywords' => [],
            'total' => 0,
            'with_contact' => 0,
            'crm' => 0,
            'loewix_solution' => 'CCTV Standar Industri, Perimeter Gedung Kantor, Smart AI Absensi Wajah & Keamanan 24 Jam'
        ]
    ];

    $omnichannel_counts = [
        'Lengkap (WhatsApp & Email)' => 0,
        'Ada WhatsApp Saja (Tanpa Email)' => 0,
        'Ada Email Saja (Tanpa WA)' => 0,
        'Hanya Website / Alamat Fisik' => 0,
        'Belum Ada Kontak Langsung' => 0
    ];

    foreach ($rows as $r) {
        $c = trim($r['country'] ?? '');
        if (empty($c)) $c = 'Tidak Diketahui';
        $is_local = (strcasecmp($c, 'Indonesia') === 0);
        if ($is_local) $total_local++; else $total_intl++;

        $has_p = !empty(trim($r['phone'] ?? ''));
        $has_e = !empty(trim($r['email'] ?? ''));
        $has_w = !empty(trim($r['website'] ?? ''));

        if ($has_p) $total_with_phone++;
        if ($has_e) $total_with_email++;
        if ($has_w) $total_with_web++;
        if ($has_p && $has_e) $total_complete_contact++;
        if ($has_p || $has_e) $total_ready_contact++;

        $in_crm = (!empty($r['customer_id']) || stripos($r['status_prospek'] ?? '', 'CRM') !== false);
        if ($in_crm) $total_crm++;

        // Omnichannel category
        if ($has_p && $has_e) {
            $omnichannel_counts['Lengkap (WhatsApp & Email)']++;
        } elseif ($has_p) {
            $omnichannel_counts['Ada WhatsApp Saja (Tanpa Email)']++;
        } elseif ($has_e) {
            $omnichannel_counts['Ada Email Saja (Tanpa WA)']++;
        } elseif ($has_w || !empty(trim($r['address'] ?? ''))) {
            $omnichannel_counts['Hanya Website / Alamat Fisik']++;
        } else {
            $omnichannel_counts['Belum Ada Kontak Langsung']++;
        }

        // Country Pivot
        if (!isset($by_country[$c])) {
            $by_country[$c] = [
                'country' => $c,
                'is_local' => $is_local,
                'total' => 0,
                'with_phone' => 0,
                'with_email' => 0,
                'with_web' => 0,
                'complete' => 0,
                'crm' => 0
            ];
        }
        $by_country[$c]['total']++;
        if ($has_p) $by_country[$c]['with_phone']++;
        if ($has_e) $by_country[$c]['with_email']++;
        if ($has_w) $by_country[$c]['with_web']++;
        if ($has_p && $has_e) $by_country[$c]['complete']++;
        if ($in_crm) $by_country[$c]['crm']++;

        // Industry Sector Matching
        $text_search = strtolower(($r['business_activity'] ?? '') . ' ' . ($r['products'] ?? '') . ' ' . ($r['company_name'] ?? ''));
        $matched_sector = false;
        foreach ($by_sector as $skey => &$sdata) {
            if ($skey === 'Sektor Industri Lainnya (General Industry)') continue;
            foreach ($sdata['keywords'] as $kw) {
                if (strpos($text_search, $kw) !== false) {
                    $sdata['total']++;
                    if ($has_p || $has_e) $sdata['with_contact']++;
                    if ($in_crm) $sdata['crm']++;
                    $matched_sector = true;
                    break 2;
                }
            }
        }
        unset($sdata);
        if (!$matched_sector) {
            $by_sector['Sektor Industri Lainnya (General Industry)']['total']++;
            if ($has_p || $has_e) $by_sector['Sektor Industri Lainnya (General Industry)']['with_contact']++;
            if ($in_crm) $by_sector['Sektor Industri Lainnya (General Industry)']['crm']++;
        }
    }

    // Sort countries by total descending
    uasort($by_country, function($a, $b) {
        if ($a['total'] === $b['total']) return strcmp($a['country'], $b['country']);
        return $b['total'] - $a['total'];
    });

    // -----------------------------------------------------------------
    // 2. BUILD SHEET 1: DASHBOARD & PIVOT EKSEKUTIF
    // -----------------------------------------------------------------
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('📊 Ringkasan & Pivot');
    $sheet1->setShowGridLines(true);

    // Title banner
    $sheet1->mergeCells('A1:K1');
    $sheet1->setCellValue('A1', 'PT. LOEWIX INDONESIA — EXECUTIVE EXHIBITION & LEADS ANALYTICS');
    $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
    $sheet1->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('0F172A');
    $sheet1->getRowDimension(1)->setRowHeight(36);

    $sheet1->mergeCells('A2:K2');
    $sheet1->setCellValue('A2', 'Pusat Analisis & Database Direktori Pameran Industri — sales.id-giti.com');
    $sheet1->getStyle('A2')->getFont()->setItalic(true)->setSize(9.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('94A3B8'));
    $sheet1->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getStyle('A2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('1E293B');
    $sheet1->getRowDimension(2)->setRowHeight(20);

    // Metadata Card
    $label_ev = !empty($event_filter) ? $event_filter : 'Semua Event Pameran';
    $label_co = !empty($country_filter) ? $country_filter : 'Semua Negara Asal';
    $label_st = !empty($status_filter) ? $status_filter : 'Semua Status Prospek';
    $user_name = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Admin Sales';

    $sheet1->setCellValue('A4', 'Filter Event:');
    $sheet1->mergeCells('B4:D4');
    $sheet1->setCellValue('B4', $label_ev);
    $sheet1->setCellValue('F4', 'Tanggal Export:');
    $sheet1->mergeCells('G4:H4');
    $sheet1->setCellValue('G4', date('d/m/Y H:i') . ' WIB');

    $sheet1->setCellValue('A5', 'Filter Negara:');
    $sheet1->mergeCells('B5:D5');
    $sheet1->setCellValue('B5', $label_co);
    $sheet1->setCellValue('F5', 'Total Data Terpilih:');
    $sheet1->mergeCells('G5:H5');
    $sheet1->setCellValue('G5', number_format($total_rows) . ' Perusahaan');

    $sheet1->setCellValue('A6', 'Filter Status:');
    $sheet1->mergeCells('B6:D6');
    $sheet1->setCellValue('B6', $label_st);
    $sheet1->setCellValue('F6', 'Di-export Oleh:');
    $sheet1->mergeCells('G6:H6');
    $sheet1->setCellValue('G6', $user_name);

    $sheet1->getStyle('A4:A6')->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
    $sheet1->getStyle('A4:A6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $sheet1->getStyle('F4:F6')->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
    $sheet1->getStyle('F4:F6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $sheet1->getStyle('B4:D6')->getFont()->setBold(true)->setSize(9.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));
    $sheet1->getStyle('G4:H6')->getFont()->setBold(true)->setSize(9.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));

    // KPI Summary Tiles (Row 8-10)
    // Tile 1: Total
    $sheet1->mergeCells('B8:C8');
    $sheet1->setCellValue('B8', 'TOTAL EXHIBITOR');
    $sheet1->mergeCells('B9:C10');
    $sheet1->setCellValue('B9', number_format($total_rows));
    $sheet1->getStyle('B8:C8')->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E40AF'));
    $sheet1->getStyle('B9:C10')->getFont()->setBold(true)->setSize(18)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
    $sheet1->getStyle('B8:C10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getStyle('B8:C10')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('EFF6FF');
    $sheet1->getStyle('B8:C10')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('BFDBFE'));

    // Tile 2: Siap Hubungi
    $sheet1->mergeCells('D8:E8');
    $sheet1->setCellValue('D8', 'KONTAK SIAP HUBUNGI');
    $sheet1->mergeCells('D9:E9');
    $sheet1->setCellValue('D9', number_format($total_ready_contact));
    $sheet1->mergeCells('D10:E10');
    $pct_ready = round(($total_ready_contact / max(1, $total_rows)) * 100);
    $sheet1->setCellValue('D10', "{$pct_ready}% Penetrasi");
    $sheet1->getStyle('D8:E8')->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('166534'));
    $sheet1->getStyle('D9:E9')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('15803D'));
    $sheet1->getStyle('D10:E10')->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('166534'));
    $sheet1->getStyle('D8:E10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getStyle('D8:E10')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('F0FDF4');
    $sheet1->getStyle('D8:E10')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('BBF7D0'));

    // Tile 3: Lokal
    $sheet1->mergeCells('F8:G8');
    $sheet1->setCellValue('F8', 'EXHIBITOR LOKAL');
    $sheet1->mergeCells('F9:G9');
    $sheet1->setCellValue('F9', number_format($total_local));
    $sheet1->mergeCells('F10:G10');
    $sheet1->setCellValue('F10', '🇮🇩 Domestik');
    $sheet1->getStyle('F8:G8')->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('92400E'));
    $sheet1->getStyle('F9:G9')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('B45309'));
    $sheet1->getStyle('F10:G10')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('92400E'));
    $sheet1->getStyle('F8:G10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getStyle('F8:G10')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FEF3C7');
    $sheet1->getStyle('F8:G10')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FDE68A'));

    // Tile 4: Internasional
    $sheet1->mergeCells('H8:I8');
    $sheet1->setCellValue('H8', 'INTERNASIONAL');
    $sheet1->mergeCells('H9:I9');
    $sheet1->setCellValue('H9', number_format($total_intl));
    $sheet1->mergeCells('H10:I10');
    $sheet1->setCellValue('H10', '🌐 Global');
    $sheet1->getStyle('H8:I8')->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6B21A8'));
    $sheet1->getStyle('H9:I9')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('7E22CE'));
    $sheet1->getStyle('H10:I10')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6B21A8'));
    $sheet1->getStyle('H8:I10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getStyle('H8:I10')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('F3E8FF');
    $sheet1->getStyle('H8:I10')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E9D5FF'));

    // Tile 5: CRM
    $sheet1->mergeCells('J8:K8');
    $sheet1->setCellValue('J8', 'PROSPEK DI CRM');
    $sheet1->mergeCells('J9:K9');
    $sheet1->setCellValue('J9', number_format($total_crm));
    $sheet1->mergeCells('J10:K10');
    $sheet1->setCellValue('J10', 'Sales Pipeline');
    $sheet1->getStyle('J8:K8')->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('334155'));
    $sheet1->getStyle('J9:K9')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));
    $sheet1->getStyle('J10:K10')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
    $sheet1->getStyle('J8:K10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getStyle('J8:K10')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('F8FAFC');
    $sheet1->getStyle('J8:K10')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('CBD5E1'));

    // PIVOT TABLE 1: Persebaran Negara
    $curRow = 13;
    $sheet1->mergeCells("A12:K12");
    $sheet1->setCellValue("A12", "1. PIVOT TABEL PERSEBARAN NEGARA ASAL & TINGKAT KESIAPAN KONTAK");
    $sheet1->getStyle("A12")->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("0F172A"));
    $sheet1->getStyle("A12")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("F1F5F9");
    $sheet1->getRowDimension(12)->setRowHeight(24);

    $headers1 = [
        'A' => 'No',
        'B' => 'Negara Asal',
        'C' => 'Klasifikasi Wilayah',
        'D' => 'Total Exhibitor',
        'E' => 'Pangsa Pasar (%)',
        'F' => 'Ada No HP / WA',
        'G' => 'Ada Email',
        'H' => 'Ada Website',
        'I' => 'Kontak Lengkap (WA+Email)',
        'J' => 'Tersimpan di CRM',
        'K' => 'Kesiapan Kontak (%)'
    ];
    foreach ($headers1 as $col => $title) {
        $sheet1->setCellValue($col . $curRow, $title);
    }
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("FFFFFF"))->setSize(9.5);
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("1E3A8A");
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet1->getRowDimension($curRow)->setRowHeight(28);

    $curRow++;
    $dataStartRow1 = $curRow;
    $no1 = 1;
    foreach ($by_country as $c) {
        $sheet1->setCellValue('A' . $curRow, $no1++);
        $sheet1->setCellValue('B' . $curRow, $c['country']);
        $sheet1->setCellValue('C' . $curRow, $c['is_local'] ? '🇮🇩 Lokal' : '🌐 Internasional');
        $sheet1->setCellValue('D' . $curRow, $c['total']);
        $sheet1->setCellValue('E' . $curRow, "=D{$curRow}/" . max(1, $total_rows));
        $sheet1->setCellValue('F' . $curRow, $c['with_phone']);
        $sheet1->setCellValue('G' . $curRow, $c['with_email']);
        $sheet1->setCellValue('H' . $curRow, $c['with_web']);
        $sheet1->setCellValue('I' . $curRow, $c['complete']);
        $sheet1->setCellValue('J' . $curRow, $c['crm']);
        $sheet1->setCellValue('K' . $curRow, "=IF(D{$curRow}>0, (F{$curRow}+G{$curRow}-I{$curRow})/D{$curRow}, 0)");

        $rowBg = ($no1 % 2 === 0) ? 'FFFFFF' : 'F8FAFC';
        $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($rowBg);
        $sheet1->getStyle("A{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("C{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("D{$curRow}:J{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle("D{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet1->getStyle("E{$curRow}")->getNumberFormat()->setFormatCode('0.0%');
        $sheet1->getStyle("F{$curRow}:J{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet1->getStyle("K{$curRow}")->getNumberFormat()->setFormatCode('0.0%');
        $sheet1->getRowDimension($curRow)->setRowHeight(21);
        $curRow++;
    }
    $dataEndRow1 = $curRow - 1;

    // Summary Row for Table 1
    $sheet1->mergeCells("A{$curRow}:C{$curRow}");
    $sheet1->setCellValue("A{$curRow}", "TOTAL KESELURUHAN");
    $sheet1->setCellValue("D{$curRow}", "=SUM(D{$dataStartRow1}:D{$dataEndRow1})");
    $sheet1->setCellValue("E{$curRow}", "=SUM(E{$dataStartRow1}:E{$dataEndRow1})");
    $sheet1->setCellValue("F{$curRow}", "=SUM(F{$dataStartRow1}:F{$dataEndRow1})");
    $sheet1->setCellValue("G{$curRow}", "=SUM(G{$dataStartRow1}:G{$dataEndRow1})");
    $sheet1->setCellValue("H{$curRow}", "=SUM(H{$dataStartRow1}:H{$dataEndRow1})");
    $sheet1->setCellValue("I{$curRow}", "=SUM(I{$dataStartRow1}:I{$dataEndRow1})");
    $sheet1->setCellValue("J{$curRow}", "=SUM(J{$dataStartRow1}:J{$dataEndRow1})");
    $sheet1->setCellValue("K{$curRow}", "=IF(D{$curRow}>0, (F{$curRow}+G{$curRow}-I{$curRow})/D{$curRow}, 0)");

    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFont()->setBold(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("0F172A"));
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("E2E8F0");
    $sheet1->getStyle("A{$curRow}:C{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle("D{$curRow}:J{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet1->getStyle("E{$curRow}")->getNumberFormat()->setFormatCode('0.0%');
    $sheet1->getStyle("K{$curRow}")->getNumberFormat()->setFormatCode('0.0%');
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
    $sheet1->getRowDimension($curRow)->setRowHeight(25);

    // PIVOT TABLE 2: Sektor Industri & Rekomendasi Solusi Loewix
    $curRow += 3;
    $sheet1->mergeCells("A{$curRow}:K{$curRow}");
    $sheet1->setCellValue("A{$curRow}", "2. PIVOT TABEL SEKTOR INDUSTRI & REKOMENDASI PENETRASI CCTV / AI SECURITY LOEWIX");
    $sheet1->getStyle("A{$curRow}")->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("0F172A"));
    $sheet1->getStyle("A{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("F1F5F9");
    $sheet1->getRowDimension(12)->setRowHeight(24);

    $curRow++;
    $sheet1->setCellValue("A{$curRow}", "No");
    $sheet1->mergeCells("B{$curRow}:C{$curRow}");
    $sheet1->setCellValue("B{$curRow}", "Sektor / Bidang Industri");
    $sheet1->setCellValue("D{$curRow}", "Total Exhibitor");
    $sheet1->setCellValue("E{$curRow}", "Pangsa Pasar (%)");
    $sheet1->setCellValue("F{$curRow}", "Siap Dihubungi");
    $sheet1->setCellValue("G{$curRow}", "Tersimpan CRM");
    $sheet1->mergeCells("H{$curRow}:K{$curRow}");
    $sheet1->setCellValue("H{$curRow}", "Rekomendasi Penetrasi Solusi CCTV / AI Security Loewix");

    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("FFFFFF"))->setSize(9.5);
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("065F46");
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getRowDimension($curRow)->setRowHeight(28);

    $curRow++;
    $dataStartRow2 = $curRow;
    $no2 = 1;
    foreach ($by_sector as $s) {
        $sheet1->setCellValue('A' . $curRow, $no2++);
        $sheet1->mergeCells("B{$curRow}:C{$curRow}");
        $sheet1->setCellValue('B' . $curRow, $s['name']);
        $sheet1->setCellValue('D' . $curRow, $s['total']);
        $sheet1->setCellValue('E' . $curRow, "=D{$curRow}/" . max(1, $total_rows));
        $sheet1->setCellValue('F' . $curRow, $s['with_contact']);
        $sheet1->setCellValue('G' . $curRow, $s['crm']);
        $sheet1->mergeCells("H{$curRow}:K{$curRow}");
        $sheet1->setCellValue('H' . $curRow, $s['loewix_solution']);

        $rowBg = ($no2 % 2 === 0) ? 'FFFFFF' : 'F8FAFC';
        $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($rowBg);
        $sheet1->getStyle("A{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("D{$curRow}:G{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle("D{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet1->getStyle("E{$curRow}")->getNumberFormat()->setFormatCode('0.0%');
        $sheet1->getStyle("F{$curRow}:G{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet1->getStyle("H{$curRow}")->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("0F172A"));
        $sheet1->getRowDimension($curRow)->setRowHeight(22);
        $curRow++;
    }
    $dataEndRow2 = $curRow - 1;

    // Summary Row for Table 2
    $sheet1->mergeCells("A{$curRow}:C{$curRow}");
    $sheet1->setCellValue("A{$curRow}", "TOTAL INDUSTRI");
    $sheet1->setCellValue("D{$curRow}", "=SUM(D{$dataStartRow2}:D{$dataEndRow2})");
    $sheet1->setCellValue("E{$curRow}", "=SUM(E{$dataStartRow2}:E{$dataEndRow2})");
    $sheet1->setCellValue("F{$curRow}", "=SUM(F{$dataStartRow2}:F{$dataEndRow2})");
    $sheet1->setCellValue("G{$curRow}", "=SUM(G{$dataStartRow2}:G{$dataEndRow2})");
    $sheet1->mergeCells("H{$curRow}:K{$curRow}");
    $sheet1->setCellValue("H{$curRow}", "Target Penjualan B2B Seluruh Kluster Industri");

    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFont()->setBold(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("065F46"));
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("D1FAE5");
    $sheet1->getStyle("A{$curRow}:C{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle("D{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet1->getStyle("E{$curRow}")->getNumberFormat()->setFormatCode('0.0%');
    $sheet1->getStyle("F{$curRow}:G{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
    $sheet1->getRowDimension($curRow)->setRowHeight(25);

    // PIVOT TABLE 3: Matriks Saluran Komunikasi & Prioritas Sales
    $curRow += 3;
    $sheet1->mergeCells("A{$curRow}:K{$curRow}");
    $sheet1->setCellValue("A{$curRow}", "3. MATRIKS KESIAPAN KOMUNIKASI & STRATEGI TINDAK LANJUT SALES");
    $sheet1->getStyle("A{$curRow}")->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("0F172A"));
    $sheet1->getStyle("A{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("F1F5F9");
    $sheet1->getRowDimension($curRow)->setRowHeight(24);

    $curRow++;
    $sheet1->mergeCells("A{$curRow}:C{$curRow}");
    $sheet1->setCellValue("A{$curRow}", "Saluran Kontak Tersedia");
    $sheet1->setCellValue("D{$curRow}", "Jumlah Exhibitor");
    $sheet1->setCellValue("E{$curRow}", "Persentase (%)");
    $sheet1->mergeCells("F{$curRow}:K{$curRow}");
    $sheet1->setCellValue("F{$curRow}", "Prioritas & Rekomendasi Tindak Lanjut Tim Sales Loewix");

    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("FFFFFF"))->setSize(9.5);
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("334155");
    $sheet1->getStyle("A{$curRow}:K{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet1->getRowDimension($curRow)->setRowHeight(26);

    $curRow++;
    $strategy_map = [
        'Lengkap (WhatsApp & Email)' => 'Prioritas 1 (Tier-A Hot Lead): Hubungi via WhatsApp bisnis & kirim proposal resmi via Email pada hari yang sama.',
        'Ada WhatsApp Saja (Tanpa Email)' => 'Prioritas 2 (Tier-B Direct Call): Hubungi via chat/telepon WA untuk perkenalan & minta alamat email PIC Purchasing.',
        'Ada Email Saja (Tanpa WA)' => 'Prioritas 3 (Tier-C Proposal): Kirim company profile & katalog produk, ajukan jadwal presentasi demo.',
        'Hanya Website / Alamat Fisik' => 'Prioritas 4 (Canvasing): Kunjungi booth saat hari H pameran atau jadwalkan canvasing visit ke lokasi.',
        'Belum Ada Kontak Langsung' => 'Prioritas 5 (Research): Lakukan riset lanjutan melalui LinkedIn atau Google Search sebelum dihubungi.'
    ];

    $no3 = 1;
    foreach ($omnichannel_counts as $ochannel => $ocnt) {
        $sheet1->mergeCells("A{$curRow}:C{$curRow}");
        $sheet1->setCellValue("A{$curRow}", $ochannel);
        $sheet1->setCellValue("D{$curRow}", $ocnt);
        $sheet1->setCellValue("E{$curRow}", "=D{$curRow}/" . max(1, $total_rows));
        $sheet1->mergeCells("F{$curRow}:K{$curRow}");
        $sheet1->setCellValue("F{$curRow}", $strategy_map[$ochannel] ?? '-');

        $rowBg = ($no3 % 2 === 0) ? 'FFFFFF' : 'F8FAFC';
        $sheet1->getStyle("A{$curRow}:K{$curRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($rowBg);
        $sheet1->getStyle("A{$curRow}")->getFont()->setBold(true)->setSize(9.5);
        $sheet1->getStyle("D{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle("D{$curRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet1->getStyle("E{$curRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle("E{$curRow}")->getNumberFormat()->setFormatCode('0.0%');
        $sheet1->getStyle("F{$curRow}")->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("475569"));
        $sheet1->getRowDimension($curRow)->setRowHeight(22);
        $curRow++;
        $no3++;
    }

    // Set Column Widths for Sheet 1
    $colWidths1 = [
        'A' => 6,
        'B' => 28,
        'C' => 18,
        'D' => 15,
        'E' => 16,
        'F' => 16,
        'G' => 16,
        'H' => 18,
        'I' => 22,
        'J' => 16,
        'K' => 22
    ];
    foreach ($colWidths1 as $c => $w) {
        $sheet1->getColumnDimension($c)->setWidth($w);
    }

    // -----------------------------------------------------------------
    // 3. BUILD SHEET 2: DATABASE EXHIBITOR LENGKAP
    // -----------------------------------------------------------------
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('📋 Database Exhibitor');
    $sheet2->setShowGridLines(true);

    $sheet2->mergeCells('A1:R1');
    $sheet2->setCellValue('A1', 'PT. LOEWIX INDONESIA — DATABASE LENGKAP EXHIBITOR & LEAD PROSPEK');
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(13)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
    $sheet2->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet2->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('0F172A');
    $sheet2->getRowDimension(1)->setRowHeight(32);

    $headers2 = [
        'A' => 'No',
        'B' => 'Nama Event / Pameran',
        'C' => 'ID Exhibitor',
        'D' => 'Nama Perusahaan',
        'E' => 'Negara Asal',
        'F' => 'Tipe Wilayah',
        'G' => 'Nomor Booth',
        'H' => 'Status Kontak',
        'I' => 'No. Telepon / WhatsApp',
        'J' => 'Link Chat WhatsApp',
        'K' => 'Email Resmi',
        'L' => 'Website Resmi',
        'M' => 'Kota',
        'N' => 'Alamat Lengkap',
        'O' => 'Kategori Bisnis / Industri',
        'P' => 'Daftar Produk & Layanan',
        'Q' => 'Status Prospek CRM',
        'R' => 'Link Profil Web'
    ];

    $headRow2 = 3;
    foreach ($headers2 as $col => $title) {
        $sheet2->setCellValue($col . $headRow2, $title);
    }
    $sheet2->getStyle("A{$headRow2}:R{$headRow2}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("FFFFFF"))->setSize(9.5);
    $sheet2->getStyle("A{$headRow2}:R{$headRow2}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB("0F172A");
    $sheet2->getStyle("A{$headRow2}:R{$headRow2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet2->getRowDimension($headRow2)->setRowHeight(26);
    $sheet2->setAutoFilter("A{$headRow2}:R{$headRow2}");
    $sheet2->freezePane("A" . ($headRow2 + 1));

    $r2 = $headRow2 + 1;
    $no2 = 1;
    foreach ($rows as $item) {
        $sheet2->setCellValue('A' . $r2, $no2++);
        $sheet2->setCellValue('B' . $r2, $item['event_name'] ?? '-');
        $sheet2->setCellValue('C' . $r2, $item['id'] ?? '-');
        $sheet2->setCellValue('D' . $r2, $item['company_name'] ?? '-');
        $sheet2->setCellValue('E' . $r2, $item['country'] ?? '-');
        $sheet2->setCellValue('F' . $r2, (strcasecmp($item['country'] ?? '', 'Indonesia') === 0) ? 'Lokal' : 'Internasional');
        $sheet2->setCellValue('G' . $r2, $item['booth_number'] ?? '-');

        $has_p = !empty(trim($item['phone'] ?? ''));
        $has_e = !empty(trim($item['email'] ?? ''));
        if ($has_p && $has_e) $status_k = 'Lengkap (WA & Email)';
        elseif ($has_p) $status_k = 'Ada WhatsApp Saja';
        elseif ($has_e) $status_k = 'Ada Email Saja';
        else $status_k = 'Belum Ada Kontak';
        $sheet2->setCellValue('H' . $r2, $status_k);

        // Phone explicitly string
        $sheet2->setCellValueExplicit('I' . $r2, $item['phone'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // WA Link
        $clean_phone = preg_replace('/[^0-9]/', '', $item['phone'] ?? '');
        if (strpos($clean_phone, '0') === 0) {
            $clean_phone = '62' . substr($clean_phone, 1);
        }
        if (!empty($clean_phone)) {
            $sheet2->setCellValue('J' . $r2, "=HYPERLINK(\"https://wa.me/{$clean_phone}\", \"Chat WA\")");
            $sheet2->getStyle('J' . $r2)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('15803D'))->setUnderline(true)->setBold(true);
        } else {
            $sheet2->setCellValue('J' . $r2, '-');
        }

        // Email
        $email = trim($item['email'] ?? '');
        if (!empty($email)) {
            $sheet2->setCellValue('K' . $r2, "=HYPERLINK(\"mailto:{$email}\", \"{$email}\")");
            $sheet2->getStyle('K' . $r2)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1D4ED8'))->setUnderline(true);
        } else {
            $sheet2->setCellValue('K' . $r2, '-');
        }

        // Website
        $web = trim($item['website'] ?? '');
        if (!empty($web)) {
            $web_url = (strpos($web, 'http') === 0) ? $web : 'http://' . $web;
            $sheet2->setCellValue('L' . $r2, "=HYPERLINK(\"{$web_url}\", \"{$web}\")");
            $sheet2->getStyle('L' . $r2)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1D4ED8'))->setUnderline(true);
        } else {
            $sheet2->setCellValue('L' . $r2, '-');
        }

        $sheet2->setCellValue('M' . $r2, $item['city'] ?? '-');
        $sheet2->setCellValue('N' . $r2, $item['address'] ?? '-');
        $sheet2->setCellValue('O' . $r2, $item['business_activity'] ?? '-');
        $sheet2->setCellValue('P' . $r2, $item['products'] ?? '-');
        $sheet2->setCellValue('Q' . $r2, $item['status_prospek'] ?? 'Belum Dihubungi');

        $detail_url = trim($item['detail_url'] ?? '');
        if (!empty($detail_url)) {
            $sheet2->setCellValue('R' . $r2, "=HYPERLINK(\"{$detail_url}\", \"Buka Profil Web\")");
            $sheet2->getStyle('R' . $r2)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('4F46E5'))->setUnderline(true);
        } else {
            $sheet2->setCellValue('R' . $r2, '-');
        }

        $rowBg2 = ($no2 % 2 === 0) ? 'FFFFFF' : 'F8FAFC';
        $sheet2->getStyle("A{$r2}:R{$r2}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($rowBg2);
        $sheet2->getStyle("A{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle("C{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle("F{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle("G{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle("J{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet2->getRowDimension($r2)->setRowHeight(20);
        $r2++;
    }

    $colWidths2 = [
        'A' => 6,
        'B' => 20,
        'C' => 12,
        'D' => 38,
        'E' => 16,
        'F' => 14,
        'G' => 12,
        'H' => 20,
        'I' => 18,
        'J' => 14,
        'K' => 26,
        'L' => 24,
        'M' => 18,
        'N' => 36,
        'O' => 36,
        'P' => 36,
        'Q' => 18,
        'R' => 16
    ];
    foreach ($colWidths2 as $c => $w) {
        $sheet2->getColumnDimension($c)->setWidth($w);
    }

    // Set active sheet ke Sheet 1 agar user pertama kali melihat Pivot & Dashboard
    $spreadsheet->setActiveSheetIndex(0);

    // Stream download
    $fn_suffix = !empty($event_filter) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $event_filter) : 'Semua_Event';
    $filename = 'Loewix_Analytics_Exhibitor_' . $fn_suffix . '_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Handle Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $event_filter = trim($_GET['event'] ?? '');
    $country_filter = trim($_GET['country'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
    $search_query = trim($_GET['q'] ?? '');

    $where = ["1=1"];

    if (!empty($event_filter)) {
        $where[] = "event_name = '" . $conn->real_escape_string($event_filter) . "'";
    }
    if (!empty($country_filter)) {
        $where[] = "country = '" . $conn->real_escape_string($country_filter) . "'";
    }
    if (!empty($status_filter)) {
        $where[] = "status_prospek = '" . $conn->real_escape_string($status_filter) . "'";
    }
    if (!empty($search_query)) {
        $q_esc = $conn->real_escape_string($search_query);
        $where[] = "(company_name LIKE '%$q_esc%' OR booth_number LIKE '%$q_esc%' OR business_activity LIKE '%$q_esc%' OR products LIKE '%$q_esc%' OR phone LIKE '%$q_esc%' OR email LIKE '%$q_esc%' OR website LIKE '%$q_esc%' OR address LIKE '%$q_esc%' OR city LIKE '%$q_esc%')";
    }

    $where_sql = implode(" AND ", $where);
    $sql = "SELECT id, event_name, exhibitor_id, company_name, country, booth_number, phone, email, website, address, city, business_activity, products, detail_url, status_prospek, created_at FROM krista_exhibitors WHERE $where_sql ORDER BY id ASC";
    $result = $conn->query($sql);

    $fn_suffix = !empty($event_filter) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $event_filter) : 'Semua_Event';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Exhibitor_' . $fn_suffix . '_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['No', 'Event/Pameran', 'ID Pameran', 'Nama Perusahaan', 'Negara', 'Nomor Booth', 'No. Telepon / WA', 'Email', 'Website', 'Alamat Lengkap', 'Kota', 'Kategori Bisnis', 'Produk', 'Status Prospek', 'Link Detail'], ',', '"', "\\");

    $no = 1;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $no++,
                $row['event_name'],
                $row['exhibitor_id'],
                $row['company_name'],
                $row['country'],
                $row['booth_number'],
                $row['phone'],
                $row['email'],
                $row['website'],
                $row['address'],
                $row['city'],
                $row['business_activity'],
                $row['products'],
                $row['status_prospek'],
                $row['detail_url']
            ], ',', '"', "\\");
        }
    }
    fclose($output);
    exit;
}

// Handle Add to CRM (Customer Lead)
if (isset($_POST['action']) && $_POST['action'] === 'add_to_crm') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $ex_id = intval($_POST['id'] ?? 0);

    if ($ex_id <= 0) {
        echo json_encode(['status' => false, 'message' => 'ID Exhibitor tidak valid.']);
        exit;
    }

    $q = $conn->query("SELECT * FROM krista_exhibitors WHERE id = $ex_id");
    $exhibitor = $q ? $q->fetch_assoc() : null;

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
    $nama_toko = $conn->real_escape_string($exhibitor['company_name']);
    $kategori = !empty($exhibitor['business_activity']) && $exhibitor['business_activity'] !== '-' 
        ? "Exhibitor: " . $conn->real_escape_string($exhibitor['business_activity']) 
        : "Exhibitor " . $conn->real_escape_string($exhibitor['event_name']) . " (Booth: " . $conn->real_escape_string($exhibitor['booth_number']) . ")";
    $deal = 'N';
    $kandidat = 'Y';
    $potensial = 'Y';
    $acc_boss = 'N';
    
    $notes_parts = ["Lead otomatis dari Scraper ({$exhibitor['event_name']}, Asal: {$exhibitor['country']}, Booth: {$exhibitor['booth_number']})"];
    if (!empty($exhibitor['phone'])) $notes_parts[] = "Telp/WA: {$exhibitor['phone']}";
    if (!empty($exhibitor['email'])) $notes_parts[] = "Email: {$exhibitor['email']}";
    if (!empty($exhibitor['website'])) $notes_parts[] = "Web: {$exhibitor['website']}";
    $acc_note = $conn->real_escape_string(implode(" | ", $notes_parts));

    $ins_sql = "
        INSERT INTO customers (sales_id, tgl_input, nama_toko, kategori, deal, kandidat, potensial, acc_boss, acc_boss_note)
        VALUES ($sales_id, '$tgl_input', '$nama_toko', '$kategori', '$deal', '$kandidat', '$potensial', '$acc_boss', '$acc_note')
    ";

    if ($conn->query($ins_sql)) {
        $new_cust_id = $conn->insert_id;
        $conn->query("UPDATE krista_exhibitors SET customer_id = $new_cust_id, status_prospek = 'Tersimpan di CRM' WHERE id = $ex_id");

        // Auto insert PIC if phone exists
        if (!empty($exhibitor['phone'])) {
            $phone_esc = $conn->real_escape_string($exhibitor['phone']);
            $conn->query("INSERT INTO customer_pics (customer_id, nama_pic, tlp_pic) VALUES ($new_cust_id, 'Official / PIC', '$phone_esc')");
        }

        // Auto insert Address if address exists
        if (!empty($exhibitor['address'])) {
            $addr_esc = $conn->real_escape_string($exhibitor['address']);
            $city_esc = $conn->real_escape_string($exhibitor['city'] ?? '');
            $prov_esc = $conn->real_escape_string($exhibitor['province'] ?? '');
            $conn->query("INSERT INTO customer_addresses (customer_id, alamat, kota, provinsi) VALUES ($new_cust_id, '$addr_esc', '$city_esc', '$prov_esc')");
        }

        echo json_encode([
            'status' => true, 
            'message' => 'Berhasil menambahkan perusahaan ke database CRM sebagai Kandidat Customer!',
            'customer_id' => $new_cust_id,
            'company_name' => $exhibitor['company_name']
        ]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Gagal menyimpan ke CRM: ' . $conn->error]);
    }
    exit;
}

// Handle Live Scraping Batch for Krista Media / SIAL Network
if (isset($_POST['action']) && $_POST['action'] === 'run_scrape_page') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $page = intval($_POST['page'] ?? 1);
    if ($page < 1 || $page > 100) $page = 1;

    $event_name = trim($_POST['event_name'] ?? 'Krista Interfood 2026');
    $base_url = trim($_POST['base_url'] ?? 'https://sialinterfood.com');
    $parsed_base = parse_url($base_url, PHP_URL_SCHEME) . '://' . parse_url($base_url, PHP_URL_HOST);
    if (!$parsed_base || $parsed_base === '://') $parsed_base = 'https://sialinterfood.com';

    $url = rtrim($parsed_base, '/') . "/api/ajax?type=search&page=" . $page;
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                        "X-Requested-With: XMLHttpRequest\r\n" .
                        "Referer: " . $base_url . "\r\n",
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
            (`event_name`, `source_url`, `exhibitor_id`, `company_name`, `country`, `booth_number`, `business_activity`, `products`, `lead_type`, `detail_url`, `logo_url`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $detail_url = $ex_id ? rtrim($parsed_base, '/') . "/detail-exhibitor/{$ex_id}" : "";
            $logo = trim($d['logo'] ?? '');

            $products = $d['product_index'] ?? [];
            if (is_array($products)) {
                $prod_str = implode('; ', array_filter($products));
            } else {
                $prod_str = strval($products);
            }

            if (!empty($name)) {
                if ($ex_id) {
                    $chk = $conn->prepare("SELECT id FROM krista_exhibitors WHERE event_name = ? AND exhibitor_id = ?");
                    $chk->bind_param("si", $event_name, $ex_id);
                } else {
                    $chk = $conn->prepare("SELECT id FROM krista_exhibitors WHERE event_name = ? AND company_name = ?");
                    $chk->bind_param("ss", $event_name, $name);
                }
                $chk->execute();
                $existing = $chk->get_result()->fetch_assoc();

                if (!$existing) {
                    $stmt->bind_param("ssissssssss", $event_name, $base_url, $ex_id, $name, $country, $booth, $activity, $prod_str, $lead_type, $detail_url, $logo);
                    if ($stmt->execute()) {
                        $inserted++;
                    }
                }
            }
        }
    }

    $total_event = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE event_name = '" . $conn->real_escape_string($event_name) . "'")->fetch_assoc()['t'] ?? 0;
    $total_all = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors")->fetch_assoc()['t'] ?? 0;
    echo json_encode([
        'status' => true,
        'page' => $page,
        'added_this_page' => $inserted,
        'total_event' => $total_event,
        'total_db' => $total_all
    ]);
    exit;
}

// Handle Universal URL Scraper (HTML Table / Card Parser)
if (isset($_POST['action']) && $_POST['action'] === 'universal_scrape_url') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $target_url = trim($_POST['target_url'] ?? '');
    $event_name = trim($_POST['event_name'] ?? '');
    $scrape_mode = trim($_POST['scrape_mode'] ?? 'auto');

    if (empty($target_url) || !filter_var($target_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => false, 'message' => 'URL website tidak valid! Mohon sertakan http:// atau https://']);
        exit;
    }
    if (empty($event_name)) {
        $event_name = parse_url($target_url, PHP_URL_HOST) ?: 'Pameran Baru';
    }

    $host = strtolower(parse_url($target_url, PHP_URL_HOST));
    $is_krista = (strpos($host, 'krista') !== false || strpos($host, 'sialinterfood') !== false || strpos($host, 'allpack') !== false || strpos($host, 'allprint') !== false || strpos($host, 'indobeauty') !== false);

    if ($scrape_mode === 'krista' || ($scrape_mode === 'auto' && $is_krista)) {
        echo json_encode([
            'status' => true,
            'type' => 'krista_network',
            'base_url' => $target_url,
            'event_name' => $event_name,
            'message' => 'Website terdeteksi menggunakan Krista/SIAL Network Engine!'
        ]);
        exit;
    }

    // Generic Website Scraper
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$html || $http_code >= 400) {
        echo json_encode(['status' => false, 'message' => "Gagal mengakses website (HTTP $http_code). Website mungkin memblokir bot / butuh login. Solusi: Gunakan Tab 'Copy-Paste Cepat'!"]);
        exit;
    }

    // Extract table rows
    $items = [];
    if (preg_match_all('/<tr[^>]*>([\s\S]*?)<\/tr>/i', $html, $rows)) {
        foreach ($rows[1] as $r) {
            if (preg_match_all('/<td[^>]*>([\s\S]*?)<\/td>/i', $r, $cols)) {
                $cells = array_values(array_filter(array_map(function($c) {
                    return trim(strip_tags($c));
                }, $cols[1])));

                if (count($cells) >= 1) {
                    $c_name = $cells[0];
                    if (stripos($c_name, 'nama') !== false || stripos($c_name, 'company') !== false || strlen($c_name) < 2) continue;

                    $booth = $cells[1] ?? '-';
                    $country = $cells[2] ?? 'Indonesia';
                    $products = $cells[3] ?? '-';

                    $items[] = [
                        'company_name' => $c_name,
                        'booth_number' => $booth,
                        'country' => $country,
                        'products' => $products,
                        'detail_url' => $target_url
                    ];
                }
            }
        }
    }

    // Fallback: Card elements
    if (empty($items)) {
        if (preg_match_all('/<(?:div|li|article)[^>]*(?:class=["\'][^"\']*(?:exhibitor|company|participant|item|card)[^"\']*["\'])[^>]*>([\s\S]*?)<\/(?:div|li|article)>/i', $html, $cards)) {
            foreach ($cards[1] as $card) {
                $card_text = trim(strip_tags($card));
                $lines = array_values(array_filter(array_map('trim', explode("\n", $card_text))));
                if (!empty($lines)) {
                    $c_name = $lines[0];
                    if (strlen($c_name) > 3 && strlen($c_name) < 100) {
                        $items[] = [
                            'company_name' => $c_name,
                            'booth_number' => $lines[1] ?? '-',
                            'country' => 'Indonesia',
                            'products' => $lines[2] ?? '-',
                            'detail_url' => $target_url
                        ];
                    }
                }
            }
        }
    }

    echo json_encode([
        'status' => true,
        'type' => 'generic_preview',
        'event_name' => $event_name,
        'source_url' => $target_url,
        'count' => count($items),
        'items' => array_slice($items, 0, 150),
        'message' => count($items) > 0 ? "Ditemukan " . count($items) . " perusahaan dari website." : "Tidak dapat menemukan pola tabel/kartu standar. Disarankan gunakan Tab 'Copy-Paste Cepat'!"
    ]);
    exit;
}

// Handle Save Scraped / Preview Items
if (isset($_POST['action']) && $_POST['action'] === 'save_scraped_items') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $event_name = trim($_POST['event_name'] ?? 'Pameran Baru');
    $source_url = trim($_POST['source_url'] ?? '');
    $items = json_decode($_POST['items'] ?? '[]', true);

    if (empty($items)) {
        echo json_encode(['status' => false, 'message' => 'Tidak ada data perusahaan untuk disimpan.']);
        exit;
    }

    $saved = 0;
    $stmt = $conn->prepare("
        INSERT INTO `krista_exhibitors` 
        (`event_name`, `source_url`, `company_name`, `country`, `booth_number`, `products`, `detail_url`)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $c_name = trim($item['company_name'] ?? '');
        if (empty($c_name)) continue;

        $country = trim($item['country'] ?? 'Indonesia');
        $booth = trim($item['booth_number'] ?? '-');
        $products = trim($item['products'] ?? '-');
        $detail = trim($item['detail_url'] ?? $source_url);

        $chk = $conn->prepare("SELECT id FROM krista_exhibitors WHERE event_name = ? AND company_name = ?");
        $chk->bind_param("ss", $event_name, $c_name);
        $chk->execute();
        if ($chk->get_result()->num_rows == 0) {
            $stmt->bind_param("sssssss", $event_name, $source_url, $c_name, $country, $booth, $products, $detail);
            if ($stmt->execute()) {
                $saved++;
            }
        }
    }

    echo json_encode([
        'status' => true,
        'saved' => $saved,
        'message' => "Berhasil menyimpan $saved data perusahaan baru ke event '$event_name'!"
    ]);
    exit;
}

// Handle Smart Paste Import (Text / Table from Clipboard)
if (isset($_POST['action']) && $_POST['action'] === 'smart_paste') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $event_name = trim($_POST['event_name'] ?? '');
    $default_country = trim($_POST['default_country'] ?? 'Indonesia');
    $raw_text = trim($_POST['raw_text'] ?? '');

    if (empty($event_name)) {
        echo json_encode(['status' => false, 'message' => 'Mohon isi Nama Event / Pameran.']);
        exit;
    }
    if (empty($raw_text)) {
        echo json_encode(['status' => false, 'message' => 'Teks yang di-paste masih kosong!']);
        exit;
    }

    $lines = preg_split('/\r\n|\r|\n/', $raw_text);
    $saved = 0;

    $stmt = $conn->prepare("
        INSERT INTO `krista_exhibitors` 
        (`event_name`, `company_name`, `country`, `booth_number`, `products`, `notes`)
        VALUES (?, ?, ?, ?, ?, 'Import Teks Cepat')
    ");

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strlen($line) < 3) continue;

        $c_name = '';
        $booth = '-';
        $country = $default_country;
        $products = '-';

        // Check if tab-separated (from Excel / Web Table)
        if (strpos($line, "\t") !== false) {
            $cols = explode("\t", $line);
            $c_name = trim($cols[0] ?? '');
            $booth = trim($cols[1] ?? '-');
            $country = trim($cols[2] ?? $default_country);
            $products = trim($cols[3] ?? '-');
        } elseif (strpos($line, ";") !== false) {
            $cols = explode(";", $line);
            $c_name = trim($cols[0] ?? '');
            $booth = trim($cols[1] ?? '-');
            $country = trim($cols[2] ?? $default_country);
            $products = trim($cols[3] ?? '-');
        } else {
            // Freeform text (e.g. "1. PT Sumber Pangan - Booth A12")
            $clean_line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
            if (preg_match('/(?:booth|stand|hall|kavling)[:\s]+([a-zA-Z0-9\-\s]+)/i', $clean_line, $m)) {
                $booth = trim($m[1]);
                $c_name = trim(preg_replace('/[\-\|,\s]*(?:booth|stand|hall|kavling)[:\s]+[a-zA-Z0-9\-\s]+/i', '', $clean_line));
            } else {
                $c_name = $clean_line;
            }
        }

        if (!empty($c_name)) {
            $chk = $conn->prepare("SELECT id FROM krista_exhibitors WHERE event_name = ? AND company_name = ?");
            $chk->bind_param("ss", $event_name, $c_name);
            $chk->execute();
            if ($chk->get_result()->num_rows == 0) {
                $stmt->bind_param("sssss", $event_name, $c_name, $country, $booth, $products);
                if ($stmt->execute()) {
                    $saved++;
                }
            }
        }
    }

    echo json_encode([
        'status' => true,
        'saved' => $saved,
        'message' => "Selesai! Berhasil mengekstrak dan menyimpan $saved perusahaan ke event '$event_name'."
    ]);
    exit;
}

// Handle Import CSV File
if (isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $event_name = trim($_POST['event_name'] ?? '');

    if (empty($event_name)) {
        echo json_encode(['status' => false, 'message' => 'Mohon isi Nama Event / Pameran.']);
        exit;
    }
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => false, 'message' => 'File CSV tidak ditemukan atau gagal diunggah.']);
        exit;
    }

    $file_path = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        echo json_encode(['status' => false, 'message' => 'Gagal membaca file CSV.']);
        exit;
    }

    $saved = 0;
    $row_idx = 0;
    $header_map = [];

    $stmt = $conn->prepare("
        INSERT INTO `krista_exhibitors` 
        (`event_name`, `company_name`, `country`, `booth_number`, `products`, `notes`)
        VALUES (?, ?, ?, ?, ?, 'Import File CSV')
    ");

    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
        $row_idx++;
        if ($row_idx === 1) {
            foreach ($data as $i => $col) {
                $col_lower = strtolower(trim($col));
                if (strpos($col_lower, 'perusahaan') !== false || strpos($col_lower, 'company') !== false || strpos($col_lower, 'nama') !== false) {
                    $header_map['name'] = $i;
                } elseif (strpos($col_lower, 'booth') !== false || strpos($col_lower, 'stand') !== false) {
                    $header_map['booth'] = $i;
                } elseif (strpos($col_lower, 'negara') !== false || strpos($col_lower, 'country') !== false) {
                    $header_map['country'] = $i;
                } elseif (strpos($col_lower, 'produk') !== false || strpos($col_lower, 'product') !== false || strpos($col_lower, 'bisnis') !== false) {
                    $header_map['products'] = $i;
                }
            }
            continue;
        }

        $c_name = trim($data[$header_map['name'] ?? 0] ?? '');
        if (empty($c_name)) continue;

        $booth = trim($data[$header_map['booth'] ?? 1] ?? '-');
        $country = trim($data[$header_map['country'] ?? 2] ?? 'Indonesia');
        $products = trim($data[$header_map['products'] ?? 3] ?? '-');

        $chk = $conn->prepare("SELECT id FROM krista_exhibitors WHERE event_name = ? AND company_name = ?");
        $chk->bind_param("ss", $event_name, $c_name);
        $chk->execute();
        if ($chk->get_result()->num_rows == 0) {
            $stmt->bind_param("sssss", $event_name, $c_name, $country, $booth, $products);
            if ($stmt->execute()) {
                $saved++;
            }
        }
    }
    fclose($handle);

    echo json_encode([
        'status' => true,
        'saved' => $saved,
        'message' => "Berhasil mengimpor $saved data perusahaan dari CSV ke event '$event_name'!"
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// 2. STATS & SSR INITIAL DATA (ZERO BLANK FLASH)
// ════════════════════════════════════════════════════════════════════════════
$stat_total = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors")->fetch_assoc()['t'] ?? 0;
$stat_indo = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE country = 'Indonesia'")->fetch_assoc()['t'] ?? 0;
$stat_intl = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE country != 'Indonesia' AND country != ''")->fetch_assoc()['t'] ?? 0;
$stat_crm = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE customer_id IS NOT NULL")->fetch_assoc()['t'] ?? 0;
$stat_contacts = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE (phone IS NOT NULL AND phone != '') OR (email IS NOT NULL AND email != '')")->fetch_assoc()['t'] ?? 0;

// Events list for filter
$events_res = $conn->query("SELECT event_name, COUNT(*) as cnt FROM krista_exhibitors GROUP BY event_name ORDER BY cnt DESC");
$events_list = [];
while ($ev = $events_res->fetch_assoc()) {
    $events_list[] = $ev;
}

// Countries list for filter
$countries_res = $conn->query("SELECT country, COUNT(*) as c FROM krista_exhibitors WHERE country != '' GROUP BY country ORDER BY c DESC");

// Pre-fetch Page 1 (50 items) for instant HTML render
$ssr_initial_query = $conn->query("SELECT * FROM krista_exhibitors ORDER BY (customer_id IS NOT NULL) DESC, id ASC LIMIT 50");
$ssr_rows = [];
if ($ssr_initial_query) {
    while ($r = $ssr_initial_query->fetch_assoc()) {
        $ssr_rows[] = $r;
    }
}
$ssr_total_pages = max(1, ceil($stat_total / 50));

include "includes/header.php";
?>

<style>
/* ── Premium Modern SaaS Styling ── */
:root {
    --primary-gradient: linear-gradient(135deg, #0F172A 0%, #1E293B 40%, #1E3A8A 80%, #2563EB 100%);
    --card-border: #E2E8F0;
    --card-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
}

.scraper-hero-modern {
    background: var(--primary-gradient);
    border-radius: 24px;
    padding: 34px 40px;
    color: #fff;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 16px 36px -6px rgba(15, 23, 42, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.scraper-hero-modern::before {
    content: '';
    position: absolute;
    top: -60px;
    right: -60px;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.22) 0%, transparent 70%);
}
.scraper-hero-modern::after {
    content: '';
    position: absolute;
    bottom: -40px;
    left: 20%;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.2) 0%, transparent 70%);
}

.stat-card-executive {
    background: #fff;
    border-radius: 20px;
    padding: 22px 24px;
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}
.stat-card-executive:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.08);
    border-color: #CBD5E1;
}
.stat-icon-wrapper {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.stat-value {
    font-size: 28px;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.6px;
    color: #0F172A;
}

/* ── Modern Table & Badges ── */
.table-container-modern {
    background: #fff;
    border-radius: 20px;
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    overflow: hidden;
}
.table-modern thead th {
    background: #F8FAFC !important;
    color: #475569 !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.6px !important;
    padding: 14px 16px !important;
    border-bottom: 1px solid #E2E8F0 !important;
}
.table-modern tbody tr {
    transition: all 0.15s ease;
    border-bottom: 1px solid #F1F5F9;
}
.table-modern tbody tr:hover {
    background-color: #F8FAFC !important;
}
.table-modern tbody td {
    padding: 14px 16px !important;
    vertical-align: middle;
}

.exhibitor-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    object-fit: contain;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    padding: 3px;
    flex-shrink: 0;
}
.avatar-fallback {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #DBEAFE;
    flex-shrink: 0;
}

.booth-chip {
    background: #F0FDF4;
    color: #166534;
    border: 1px solid #BBF7D0;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.3px;
    display: inline-block;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.country-pill {
    background: #F8FAFC;
    color: #334155;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #E2E8F0;
}
.event-badge {
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #FDE68A;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-block;
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Contact Badges & Quick Action Chips */
.contact-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 500;
    padding: 2.5px 8px;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.15s ease;
    max-width: 100%;
}
.contact-wa {
    background: #DCFCE7;
    color: #15803D;
    border: 1px solid #86EFAC;
}
.contact-wa:hover {
    background: #16A34A;
    color: #FFFFFF;
    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);
}
.contact-phone {
    background: #E0F2FE;
    color: #0369A1;
    border: 1px solid #BAE6FD;
}
.contact-phone:hover {
    background: #0284C7;
    color: #FFFFFF;
}
.contact-email {
    background: #EEF2FF;
    color: #4338CA;
    border: 1px solid #C7D2FE;
}
.contact-email:hover {
    background: #4F46E5;
    color: #FFFFFF;
}
.contact-web {
    background: #F1F5F9;
    color: #334155;
    border: 1px solid #CBD5E1;
}
.contact-web:hover {
    background: #475569;
    color: #FFFFFF;
}
.contact-addr {
    background: #F8FAFC;
    color: #64748B;
    border: 1px solid #E2E8F0;
    cursor: default;
}

/* Pagination Pills */
.pagination-modern .page-link {
    border-radius: 10px;
    margin: 0 2px;
    border: 1px solid #E2E8F0;
    color: #334155;
    font-weight: 600;
    font-size: 13px;
    padding: 6px 12px;
    transition: all 0.15s ease;
}
.pagination-modern .page-item.active .page-link {
    background: #2563EB;
    border-color: #2563EB;
    color: #fff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.pagination-modern .page-link:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
}

/* Custom Nav Pills for Modal */
.nav-pills-custom .nav-link {
    border-radius: 12px;
    font-weight: 600;
    color: #64748B;
    padding: 10px 18px;
    transition: all 0.2s ease;
}
.nav-pills-custom .nav-link.active {
    background: #2563EB;
    color: #fff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
</style>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid px-4 py-3">

    <!-- ─── BENTO HERO BANNER (MODERN SAAS) ─── -->
    <div class="scraper-hero-modern">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(255,255,255,0.12); backdrop-filter: blur(12px); font-size: 12px; font-weight: 700; border: 1px solid rgba(255,255,255,0.15);">
                    <span class="spinner-grow spinner-grow-sm text-warning" style="width: 8px; height: 8px;" role="status"></span>
                    <span class="text-uppercase tracking-wider">Lead Harvester &amp; Exhibition Hub</span>
                </div>
                <h2 class="fw-bold mb-2 text-white" style="letter-spacing: -0.6px; font-size: 28px;">
                    Pusat Scraping &amp; Database Pameran Industri
                </h2>
                <p class="text-white-50 mb-4" style="font-size: 14px; max-width: 680px; line-height: 1.6;">
                    Database otomatis direktori pameran industri terbesar di Indonesia (Krista Interfood, ALLPACK, Indobuildtech, dll). Admin dapat menambah link website pameran baru kapan saja tanpa perlu bantuan IT!
                </p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button class="btn btn-warning px-4 py-2 fw-bold text-dark rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalScraperCenter" style="background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%); border: none;">
                        <i class="bi bi-plus-circle-fill me-1"></i> + Scrape Link Baru / Tambah Data
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 fw-semibold rounded-pill" id="btnStartScrape" onclick="startKristaScrape()" style="backdrop-filter: blur(8px); background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.25);">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync Krista Interfood
                    </button>
                    <!-- Export Pro Button with Dropdown -->
                    <div class="btn-group shadow-sm" role="group">
                        <button type="button" class="btn btn-success px-4 py-2 fw-bold text-white d-flex align-items-center gap-2" onclick="triggerExport('excel')" id="btnExportExcel" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none; border-top-left-radius: 50rem; border-bottom-left-radius: 50rem;">
                            <i class="bi bi-file-earmark-excel-fill"></i>
                            <span>Export Excel Pro (Pivot)</span>
                        </button>
                        <button type="button" class="btn btn-success px-2 py-2 dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" style="background: #047857; border: none; border-top-right-radius: 50rem; border-bottom-right-radius: 50rem;">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2 mt-2" style="font-size: 13px; min-width: 270px; z-index: 1050;">
                            <li>
                                <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center gap-2 fw-bold text-success" href="javascript:void(0)" onclick="triggerExport('excel')">
                                    <i class="bi bi-file-earmark-excel-fill fs-5 text-success"></i>
                                    <div>
                                        <div>Microsoft Excel (.xlsx)</div>
                                        <div class="small text-muted fw-normal">Multi-Sheet, Pivot Table, Rumus &amp; Warna</div>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center gap-2 text-secondary" href="javascript:void(0)" onclick="triggerExport('csv')" id="btnExportCsv">
                                    <i class="bi bi-file-earmark-text fs-5 text-secondary"></i>
                                    <div>
                                        <div>File CSV (.csv)</div>
                                        <div class="small text-muted fw-normal">Data mentah tanpa format warna</div>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <img src="assets/images/loewix_sales_logo_white.png" alt="Loewix" style="max-height: 48px; opacity: 0.9;" onerror="this.remove()">
                <div class="small text-white-50 mt-2">Dukungan: Multi-Event • Smart URL • Instant Paste</div>
            </div>
        </div>
    </div>

    <!-- ─── STAT CARDS (EXECUTIVE BENTO GRID) ─── -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Perusahaan -->
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-executive">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Total Perusahaan</span>
                    <div class="stat-icon-wrapper" style="background: rgba(37, 99, 235, 0.1); color: #2563EB;">
                        <i class="bi bi-buildings-fill"></i>
                    </div>
                </div>
                <div class="stat-value" id="statTotal"><?php echo number_format($stat_total); ?></div>
                <div class="small text-muted mt-1 d-flex align-items-center gap-1">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5" style="font-size: 11px;">
                        <i class="bi bi-check-circle-fill me-1"></i> Semua Pameran Terdata
                    </span>
                </div>
            </div>
        </div>

        <!-- Card 2: Kontak Siap Follow Up -->
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-executive">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Kontak HP &amp; Email</span>
                    <div class="stat-icon-wrapper" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <i class="bi bi-telephone-inbound-fill"></i>
                    </div>
                </div>
                <div class="stat-value text-success"><?php echo number_format($stat_contacts); ?> <span style="font-size: 16px; font-weight: 500; color: #64748B;">/ <?php echo number_format($stat_total); ?></span></div>
                <div class="small text-muted mt-1 d-flex align-items-center gap-1">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 11px;">
                        <i class="bi bi-whatsapp me-1"></i><?php echo round(($stat_contacts / max(1, $stat_total)) * 100); ?>% Siap Hubungi Langsung
                    </span>
                </div>
            </div>
        </div>

        <!-- Card 3: Persebaran Geografis -->
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-executive">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Persebaran Negara</span>
                    <div class="stat-icon-wrapper" style="background: rgba(14, 165, 233, 0.1); color: #0EA5E9;">
                        <i class="bi bi-globe2"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <span class="text-danger"><?php echo number_format($stat_indo); ?></span> 
                    <span style="font-size: 18px; font-weight: 400; color: #94A3B8;">/</span> 
                    <span class="text-primary"><?php echo number_format($stat_intl); ?></span>
                </div>
                <div class="small text-muted mt-1 d-flex align-items-center gap-1">
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2 py-0.5" style="font-size: 11px;">
                        🇮🇩 Lokal vs 🌐 Internasional
                    </span>
                </div>
            </div>
        </div>

        <!-- Card 4: Prospek CRM -->
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-executive">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Prospek di CRM</span>
                    <div class="stat-icon-wrapper" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
                <div class="stat-value text-success" id="statCrm"><?php echo number_format($stat_crm); ?></div>
                <div class="small text-muted mt-1 d-flex align-items-center gap-1">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 11px;">
                        Siap Difollow Up Sales
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── SCRAPING LIVE PROGRESS BOX ─── -->
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
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="scrapingProgressBar" style="width: 0%; transition: width 0.3s ease;"></div>
            </div>
            <div class="small text-muted mt-2" id="scrapingLog">Mengambil data pameran secara bertahap...</div>
        </div>
    </div>

    <!-- ─── TABLE FILTER & DATA CONTAINER ─── -->
    <div class="table-container-modern mb-5">
        
        <!-- Filter Toolbar -->
        <div class="bg-white py-3 px-4 border-bottom">
            <div class="row g-2 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-calendar-event me-1"></i>Pilih Event</label>
                    <select class="form-select form-select-sm rounded-3 fw-semibold text-primary" id="filterEvent" onchange="triggerFilterChange()">
                        <option value="">Semua Event (Total: <?php echo number_format($stat_total); ?>)</option>
                        <?php foreach ($events_list as $ev): ?>
                            <option value="<?php echo htmlspecialchars($ev['event_name']); ?>">
                                <?php echo htmlspecialchars($ev['event_name']); ?> (<?php echo number_format($ev['cnt']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-geo-alt me-1"></i>Asal Negara</label>
                    <select class="form-select form-select-sm rounded-3" id="filterCountry" onchange="triggerFilterChange()">
                        <option value="">Semua Negara</option>
                        <?php while ($c = $countries_res->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($c['country']); ?>">
                                <?php echo htmlspecialchars($c['country']); ?> (<?php echo $c['c']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-funnel me-1"></i>Status CRM</label>
                    <select class="form-select form-select-sm rounded-3" id="filterStatus" onchange="triggerFilterChange()">
                        <option value="">Semua Status</option>
                        <option value="Belum Dihubungi">Belum Masuk CRM</option>
                        <option value="Tersimpan di CRM">Sudah di CRM</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i>Pencarian Cepat</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control form-control-sm border-start-0 rounded-end-3" id="filterSearch" placeholder="Cari nama PT, produk, booth..." onkeyup="debounceSearch()">
                    </div>
                </div>
                <div class="col-lg-2 col-md-12 text-md-end mt-auto">
                    <button class="btn btn-sm btn-outline-secondary w-100 rounded-3" onclick="resetAllFilters()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Responsive (Pre-rendered by PHP, zero blank screen) -->
        <div class="table-responsive" style="min-height: 380px; position: relative;">
            
            <!-- Loading Overlay -->
            <div id="tableLoadingOverlay" class="d-none" style="position: absolute; top:0; left:0; right:0; bottom:0; background: rgba(255,255,255,0.7); backdrop-filter: blur(2px); z-index: 10; display: flex; align-items: center; justify-content: center;">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small fw-bold text-muted mt-2">Memuat Data Perusahaan...</div>
                </div>
            </div>

            <table class="table table-modern align-middle mb-0" id="tableExhibitors">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Perusahaan / Exhibitor</th>
                        <th style="width: 130px;">Event</th>
                        <th style="width: 120px;">Asal Negara</th>
                        <th style="width: 100px;">Nomor Booth</th>
                        <th style="width: 220px;">Kontak &amp; Komunikasi</th>
                        <th>Aktivitas &amp; Kategori Produk</th>
                        <th style="width: 120px;">Status CRM</th>
                        <th class="text-end pe-4" style="width: 170px;">Aksi Sales</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php
                    $no = 1;
                    foreach ($ssr_rows as $r):
                        $is_crm = !empty($r['customer_id']);
                        $tr_class = $is_crm ? 'table-light' : '';
                        $initials = strtoupper(substr($r['company_name'], 0, 2));
                        $has_logo = !empty($r['logo_url']) && strpos($r['logo_url'], 'pinpng.com') === false;
                    ?>
                    <tr id="row-<?php echo $r['id']; ?>" class="<?php echo $tr_class; ?>">
                        <td class="ps-4 text-muted small fw-semibold"><?php echo $no++; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($has_logo): ?>
                                    <img src="<?php echo htmlspecialchars($r['logo_url']); ?>" class="exhibitor-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="avatar-fallback" style="display:none;"><?php echo $initials; ?></div>
                                <?php else: ?>
                                    <div class="avatar-fallback"><?php echo $initials; ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo htmlspecialchars($r['company_name']); ?></div>
                                    <?php if (!empty($r['detail_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($r['detail_url']); ?>" target="_blank" class="small text-muted text-decoration-none">
                                            <i class="bi bi-box-arrow-up-right" style="font-size: 11px;"></i> Profil Web
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="event-badge" title="<?php echo htmlspecialchars($r['event_name']); ?>"><?php echo htmlspecialchars($r['event_name']); ?></span></td>
                        <td><?php echo getCountryFlagBadge($r['country']); ?></td>
                        <td><span class="booth-chip"><?php echo htmlspecialchars($r['booth_number'] ?: '-'); ?></span></td>
                        <td><?php echo formatContactBadges($r['phone'] ?? '', $r['email'] ?? '', $r['website'] ?? '', $r['address'] ?? '', $r['city'] ?? ''); ?></td>
                        <td class="small">
                            <?php if (!empty($r['business_activity']) && $r['business_activity'] !== '-'): ?>
                                <div class="fw-semibold text-dark mb-0.5"><?php echo htmlspecialchars($r['business_activity']); ?></div>
                            <?php endif; ?>
                            <div class="text-muted text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($r['products']); ?>">
                                <?php echo htmlspecialchars($r['products'] ?: '-'); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($is_crm): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" style="font-size: 12px;">
                                    <i class="bi bi-check-circle-fill me-1"></i> Di CRM
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill" style="font-size: 12px;">
                                    Belum di CRM
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-1">
                                <?php if (!$is_crm): ?>
                                    <button class="btn btn-sm btn-success rounded-3 btn-add-crm px-2 py-1" onclick="addToCrm(<?php echo $r['id']; ?>, '<?php echo addslashes($r['company_name']); ?>')" title="Masukkan ke CRM sebagai Prospek">
                                        <i class="bi bi-person-plus-fill me-1"></i> + CRM
                                    </button>
                                <?php else: ?>
                                    <a href="customer_management.php?search=<?php echo urlencode($r['company_name']); ?>" class="btn btn-sm btn-outline-success rounded-3 px-2 py-1" title="Buka di CRM">
                                        <i class="bi bi-box-arrow-right"></i> CRM
                                    </a>
                                <?php endif; ?>
                                <a href="https://www.google.com/search?q=<?php echo urlencode($r['company_name'] . ' ' . ($r['country'] ?? '')); ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1" title="Cari di Google">
                                    <i class="bi bi-google"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Footer: Pagination & Limit Controls -->
        <div class="bg-white py-3 px-4 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="small text-muted fw-semibold" id="paginationSummary">
                    Menampilkan <b>1</b> - <b><?php echo min(50, $stat_total); ?></b> dari <b><?php echo number_format($stat_total); ?></b> perusahaan
                </span>
                <div class="d-flex align-items-center gap-1">
                    <span class="small text-muted">Tampilkan:</span>
                    <select class="form-select form-select-sm rounded-3 py-1" id="selectPageLimit" style="width: 90px; font-size: 12px;" onchange="changeLimit()">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                        <option value="1000">Semua</option>
                    </select>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap">
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm pagination-modern mb-0" id="paginationNav">
                        <li class="page-item disabled"><span class="page-link">«</span></li>
                        <li class="page-item disabled"><span class="page-link">‹</span></li>
                        <li class="page-item active"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(1)">1</a></li>
                        <?php for ($p = 2; $p <= min(5, $ssr_total_pages); $p++): ?>
                            <li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(<?php echo $p; ?>)"><?php echo $p; ?></a></li>
                        <?php endfor; ?>
                        <?php if ($ssr_total_pages > 5): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(<?php echo $ssr_total_pages; ?>)"><?php echo $ssr_total_pages; ?></a></li>
                        <?php endif; ?>
                        <?php if ($ssr_total_pages > 1): ?>
                            <li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(2)" title="Berikutnya">›</a></li>
                            <li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(<?php echo $ssr_total_pages; ?>)" title="Halaman Terakhir">»</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">›</span></li>
                            <li class="page-item disabled"><span class="page-link">»</span></li>
                        <?php endif; ?>
                    </ul>
                </nav>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" onclick="triggerExport('excel')" id="btnFooterExport" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold d-flex align-items-center gap-1" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none;">
                        <i class="bi bi-file-earmark-excel-fill"></i>
                        <span>Export Excel Pro (Pivot)</span>
                    </button>
                    <button type="button" onclick="triggerExport('csv')" id="btnFooterExportCsv" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Download Format CSV">
                        <i class="bi bi-file-earmark-text"></i> CSV
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: PUSAT SCRAPER & TAMBAH DATA PAMERAN BARU (ADMIN TOOL)                -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalScraperCenter" tabindex="-1" aria-labelledby="modalScraperCenterLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom px-4 pt-4 pb-3">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="modalScraperCenterLabel">
                        <i class="bi bi-cpu text-primary me-2"></i>Pusat Scrape &amp; Tambah Pameran Baru
                    </h5>
                    <p class="text-muted small mb-0">Admin dapat menambahkan data peserta dari website pameran baru tanpa perlu bantuan IT.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <!-- Nav Tabs -->
                <ul class="nav nav-pills nav-pills-custom mb-4 gap-2 border-bottom pb-3" id="scraperTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-url-btn" data-bs-toggle="pill" data-bs-target="#tab-url" type="button" role="tab">
                            <i class="bi bi-globe me-1"></i> 1. Scrape Link Website
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-paste-btn" data-bs-toggle="pill" data-bs-target="#tab-paste" type="button" role="tab">
                            <i class="bi bi-clipboard-check me-1"></i> 2. Copy-Paste Cepat (Anti-Gagal)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-csv-btn" data-bs-toggle="pill" data-bs-target="#tab-csv" type="button" role="tab">
                            <i class="bi bi-file-earmark-excel me-1"></i> 3. Upload File CSV
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="scraperTabsContent">
                    
                    <!-- ── TAB 1: SCRAPE DARI URL ── -->
                    <div class="tab-pane fade show active" id="tab-url" role="tabpanel">
                        <div class="alert alert-primary border-0 rounded-3 py-2 px-3 small mb-3">
                            <i class="bi bi-info-circle-fill me-1"></i> 
                            Masukkan URL halaman peserta pameran. Sistem mendukung auto-detect untuk jaringan Krista Media (SIAL, ALLPACK, ALLPRINT, IndoBeauty, dll) dan website tabel umum.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">URL Website Pameran <span class="text-danger">*</span></label>
                            <input type="url" class="form-control rounded-3" id="inputScrapeUrl" placeholder="Contoh: https://allpack-indonesia.com/list-exhibitor atau link pameran lainnya">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-dark">Nama Event / Pameran <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="inputScrapeEvent" placeholder="Contoh: ALLPACK Indonesia 2026">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-dark">Tipe Engine Scraper</label>
                                <select class="form-select rounded-3" id="selectScrapeMode">
                                    <option value="auto">Auto-Detect (Rekomendasi Otomatis)</option>
                                    <option value="krista">Krista Media Network (SIAL, ALLPACK, ALLPRINT, dll)</option>
                                    <option value="generic">Website HTML Umum (Tabel / Card Peserta)</option>
                                </select>
                            </div>
                        </div>

                        <button class="btn btn-primary px-4 py-2 rounded-pill fw-semibold w-100 shadow-sm" id="btnRunUrlScrape" onclick="runUniversalUrlScrape()">
                            <i class="bi bi-search me-1"></i> Mulai Ambil &amp; Analisa Halaman Ini
                        </button>

                        <!-- Preview Container for Generic Web -->
                        <div id="urlPreviewContainer" class="mt-4 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-dark" id="previewTitle">Preview Hasil Ekstraksi</h6>
                                <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" id="btnSavePreview" onclick="savePreviewItems()">
                                    <i class="bi bi-save me-1"></i> Simpan Semua ke Database
                                </button>
                            </div>
                            <div class="table-responsive border rounded-3" style="max-height: 240px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0" style="font-size: 13px;">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th>Perusahaan</th>
                                            <th>Booth</th>
                                            <th>Negara</th>
                                            <th>Kategori / Produk</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ── TAB 2: COPY-PASTE CEPAT (ANTI GAGAL) ── -->
                    <div class="tab-pane fade" id="tab-paste" role="tabpanel">
                        <div class="alert alert-success border-0 rounded-3 py-2 px-3 small mb-3">
                            <i class="bi bi-lightning-charge-fill me-1"></i> 
                            <b>Fitur Paling Praktis untuk Admin:</b> Buka website pameran di browser (bahkan jika ada captcha / login), blok daftar perusahaannya (Ctrl+C), lalu paste ke kotak di bawah. Sistem otomatis mendeteksi nama PT, booth, dan produk!
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label fw-bold small text-dark">Nama Event / Pameran <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="inputPasteEvent" placeholder="Contoh: Indobuildtech 2026 / Canton Fair">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-dark">Asal Negara Default</label>
                                <input type="text" class="form-control rounded-3" id="inputPasteCountry" value="Indonesia">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Paste Teks / Tabel dari Website di Sini <span class="text-danger">*</span></label>
                            <textarea class="form-control rounded-3" id="inputPasteText" rows="7" placeholder="Contoh teks yang di-paste:&#10;PT Sumber Makmur	Booth A10	Indonesia	Mesin Kopi&#10;PT Berkah Jaya	Booth B12	Indonesia	Packaging&#10;atau teks list biasa: 1. PT Maju Bersama - Booth C-4"></textarea>
                            <div class="form-text small">Mendukung format tabel copy-paste dari Excel, website, atau teks baris bebas.</div>
                        </div>

                        <button class="btn btn-success px-4 py-2 rounded-pill fw-semibold w-100 shadow-sm" id="btnRunPaste" onclick="runSmartPaste()">
                            <i class="bi bi-check-circle-fill me-1"></i> Ekstrak &amp; Simpan ke Database
                        </button>
                    </div>

                    <!-- ── TAB 3: UPLOAD CSV ── -->
                    <div class="tab-pane fade" id="tab-csv" role="tabpanel">
                        <div class="alert alert-warning border-0 rounded-3 py-2 px-3 small mb-3">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> 
                            Upload file CSV katalog peserta dari panitia pameran. Kolom nama perusahaan, booth, dan negara akan otomatis terpetakan.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Nama Event / Pameran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="inputCsvEvent" placeholder="Contoh: Katalog Peserta Surabaya Food Expo">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Pilih File CSV <span class="text-danger">*</span></label>
                            <input type="file" class="form-control rounded-3" id="inputCsvFile" accept=".csv">
                        </div>

                        <button class="btn btn-primary px-4 py-2 rounded-pill fw-semibold w-100 shadow-sm" id="btnRunCsv" onclick="runCsvUpload()">
                            <i class="bi bi-upload me-1"></i> Upload &amp; Simpan Data
                        </button>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

<script>
// ════════════════════════════════════════════════════════════════════════════
// DATA TABLE & PAGINATION ENGINE
// ════════════════════════════════════════════════════════════════════════════
let currentPage = 1;
let currentLimit = 50;
let searchTimer = null;

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        loadTableData(1);
    }, 350);
}

function triggerFilterChange() {
    loadTableData(1);
}

function changeLimit() {
    currentLimit = parseInt($('#selectPageLimit').val()) || 50;
    loadTableData(1);
}

function resetAllFilters() {
    $('#filterEvent').val('');
    $('#filterCountry').val('');
    $('#filterStatus').val('');
    $('#filterSearch').val('');
    $('#selectPageLimit').val(50);
    currentLimit = 50;
    loadTableData(1);
}

// Trigger Export Function (Excel Pro / CSV)
function triggerExport(format = 'excel') {
    const eventVal = $('#filterEvent').val() || '';
    const countryVal = $('#filterCountry').val() || '';
    const statusVal = $('#filterStatus').val() || '';
    const queryVal = ($('#filterSearch').val() || '').trim();

    const action = (format === 'excel') ? 'export_excel' : 'export_csv';
    const params = new URLSearchParams({
        action: action,
        event: eventVal,
        country: countryVal,
        status: statusVal,
        q: queryVal
    });

    const exportUrl = `scraping_exhibitor.php?${params.toString()}`;

    if (format === 'excel') {
        Swal.fire({
            title: 'Menyiapkan Excel Pro (Pivot)...',
            html: '<div class="py-2 text-center"><div class="spinner-border text-success mb-2" role="status"></div><div class="text-muted small">Sedang merangkum data multi-sheet, pivot persebaran negara, matriks CCTV Loewix &amp; formula otomatis...</div></div>',
            timer: 2500,
            showConfirmButton: false,
            allowOutsideClick: false
        });
    }

    // Trigger file download
    window.location.href = exportUrl;
}

async function loadTableData(page = 1) {
    currentPage = page;
    const eventVal = $('#filterEvent').val() || '';
    const countryVal = $('#filterCountry').val() || '';
    const statusVal = $('#filterStatus').val() || '';
    const queryVal = ($('#filterSearch').val() || '').trim();

    // Update export button URLs
    const exportExcelUrl = `scraping_exhibitor.php?action=export_excel&event=${encodeURIComponent(eventVal)}&country=${encodeURIComponent(countryVal)}&status=${encodeURIComponent(statusVal)}&q=${encodeURIComponent(queryVal)}`;
    const exportCsvUrl = `scraping_exhibitor.php?action=export_csv&event=${encodeURIComponent(eventVal)}&country=${encodeURIComponent(countryVal)}&status=${encodeURIComponent(statusVal)}&q=${encodeURIComponent(queryVal)}`;
    $('#btnExportCsv').attr('href', exportCsvUrl);

    // Show loading overlay
    $('#tableLoadingOverlay').removeClass('d-none');

    try {
        const params = new URLSearchParams({
            action: 'get_table_data',
            page: page,
            limit: currentLimit,
            event: eventVal,
            country: countryVal,
            status: statusVal,
            q: queryVal
        });

        const res = await fetch(`scraping_exhibitor.php?${params.toString()}`);
        const text = await res.text();
        $('#tableLoadingOverlay').addClass('d-none');

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Failed to parse JSON response. Raw text:', text);
            return;
        }

        if (!data || !data.status) {
            console.warn('API returned non-status:', data);
            return;
        }

        renderTableRows(data.rows);
        renderPagination(data.pagination);

    } catch (err) {
        console.error('Error fetching data:', err);
        $('#tableLoadingOverlay').addClass('d-none');
    }
}

function formatContactBadgesJs(phone, email, website, address, city) {
    let out = '<div class="d-flex flex-column gap-1 align-items-start">';
    let hasAny = false;

    if (phone && phone.trim()) {
        hasAny = true;
        const cleanNum = phone.replace(/[^0-9]/g, '');
        let isWa = false;
        let waLink = '';
        if (cleanNum.startsWith('08')) {
            isWa = true;
            waLink = 'https://wa.me/62' + cleanNum.substring(1);
        } else if (cleanNum.startsWith('628')) {
            isWa = true;
            waLink = 'https://wa.me/' + cleanNum;
        }

        if (isWa) {
            out += `<a href="${waLink}" target="_blank" class="contact-chip contact-wa" title="Chat WhatsApp (${escapeHtml(phone)})"><i class="bi bi-whatsapp"></i> ${escapeHtml(phone)}</a>`;
        } else {
            out += `<a href="tel:${cleanNum}" class="contact-chip contact-phone" title="Telepon (${escapeHtml(phone)})"><i class="bi bi-telephone-fill"></i> ${escapeHtml(phone)}</a>`;
        }
    }

    if (email && email.trim()) {
        hasAny = true;
        out += `<a href="mailto:${escapeHtml(email)}" class="contact-chip contact-email" title="Kirim Email (${escapeHtml(email)})"><i class="bi bi-envelope-fill"></i> ${escapeHtml(email)}</a>`;
    }

    if (website && website.trim()) {
        hasAny = true;
        let cleanWeb = website.replace(/^https?:\/\//i, '').replace(/\/+$/, '');
        let webHref = website.startsWith('http') ? website : 'https://' + website;
        out += `<a href="${escapeHtml(webHref)}" target="_blank" class="contact-chip contact-web" title="Kunjungi Website (${escapeHtml(website)})"><i class="bi bi-globe"></i> ${escapeHtml(cleanWeb)}</a>`;
    }

    if (address && address.trim()) {
        hasAny = true;
        let locText = city && city.trim() ? city.trim() : (address.length > 30 ? address.substring(0, 30) + '...' : address);
        out += `<span class="contact-chip contact-addr" title="${escapeHtml(address)}"><i class="bi bi-geo-alt-fill"></i> ${escapeHtml(locText)}</span>`;
    }

    if (!hasAny) {
        out += `<span class="text-muted small" style="font-size: 11.5px;">-</span>`;
    }

    out += '</div>';
    return out;
}

function renderTableRows(rows) {
    if (!rows || rows.length === 0) {
        $('#tableBody').html(`
            <tr>
                <td colspan="9" class="text-center py-5">
                    <div class="text-muted mb-2"><i class="bi bi-inbox" style="font-size: 32px;"></i></div>
                    <div class="fw-bold text-dark">Tidak ada perusahaan ditemukan</div>
                    <div class="small text-muted">Coba ubah kata kunci pencarian atau reset filter.</div>
                </td>
            </tr>
        `);
        return;
    }

    let html = '';
    rows.forEach(r => {
        const isCrm = Boolean(r.customer_id);
        const trClass = isCrm ? 'table-light' : '';
        const logoImg = r.logo_url && !r.logo_url.includes('pinpng.com')
            ? `<img src="${escapeHtml(r.logo_url)}" class="exhibitor-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
               <div class="avatar-fallback" style="display:none;">${escapeHtml(r.initials)}</div>`
            : `<div class="avatar-fallback">${escapeHtml(r.initials)}</div>`;

        const detailLink = r.detail_url 
            ? `<a href="${escapeHtml(r.detail_url)}" target="_blank" class="small text-muted text-decoration-none">
                 <i class="bi bi-box-arrow-up-right" style="font-size: 11px;"></i> Profil Web
               </a>` 
            : '';

        const crmBadge = isCrm
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" style="font-size: 12px;">
                 <i class="bi bi-check-circle-fill me-1"></i> Di CRM
               </span>`
            : `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill" style="font-size: 12px;">
                 Belum di CRM
               </span>`;

        const crmButton = !isCrm
            ? `<button class="btn btn-sm btn-success rounded-3 btn-add-crm px-2 py-1" onclick="addToCrm(${r.id}, '${escapeJsString(r.company_name)}')" title="Masukkan ke CRM sebagai Prospek">
                 <i class="bi bi-person-plus-fill me-1"></i> + CRM
               </button>`
            : `<a href="customer_management.php?search=${encodeURIComponent(r.company_name)}" class="btn btn-sm btn-outline-success rounded-3 px-2 py-1" title="Buka di CRM">
                 <i class="bi bi-box-arrow-right"></i> CRM
               </a>`;

        const googleBtn = `<a href="https://www.google.com/search?q=${encodeURIComponent(r.company_name + ' ' + (r.country || ''))}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1" title="Cari di Google">
            <i class="bi bi-google"></i>
        </a>`;

        const contactCol = r.contacts_html || formatContactBadgesJs(r.phone, r.email, r.website, r.address, r.city);

        html += `
            <tr id="row-${r.id}" class="${trClass}">
                <td class="ps-4 text-muted small fw-semibold">${r.no}</td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        ${logoImg}
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 14px;">${escapeHtml(r.company_name)}</div>
                            ${detailLink}
                        </div>
                    </div>
                </td>
                <td><span class="event-badge" title="${escapeHtml(r.event_name)}">${escapeHtml(r.event_name)}</span></td>
                <td>${r.country_flag}</td>
                <td><span class="booth-chip">${escapeHtml(r.booth_number)}</span></td>
                <td>${contactCol}</td>
                <td class="small">
                    ${r.business_activity && r.business_activity !== '-' ? `<div class="fw-semibold text-dark mb-0.5">${escapeHtml(r.business_activity)}</div>` : ''}
                    <div class="text-muted text-truncate" style="max-width: 300px;" title="${escapeHtml(r.products)}">
                        ${escapeHtml(r.products)}
                    </div>
                </td>
                <td>${crmBadge}</td>
                <td class="text-end pe-4">
                    <div class="d-inline-flex gap-1">
                        ${crmButton}
                        ${googleBtn}
                    </div>
                </td>
            </tr>
        `;
    });

    $('#tableBody').html(html);
}

function renderPagination(p) {
    if (p.total_rows > 0) {
        $('#paginationSummary').html(`Menampilkan <b>${p.from}</b> - <b>${p.to}</b> dari <b>${p.total_rows.toLocaleString()}</b> perusahaan`);
    } else {
        $('#paginationSummary').html(`0 perusahaan ditemukan`);
    }

    if (p.total_pages <= 1) {
        $('#paginationNav').html('');
        return;
    }

    let navHtml = '';

    // First & Previous buttons
    if (p.current_page > 1) {
        navHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(1)" title="Halaman Pertama">«</a></li>`;
        navHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(${p.current_page - 1})" title="Sebelumnya">‹</a></li>`;
    } else {
        navHtml += `<li class="page-item disabled"><span class="page-link">«</span></li>`;
        navHtml += `<li class="page-item disabled"><span class="page-link">‹</span></li>`;
    }

    // Dynamic Sliding Page Numbers
    const delta = 2;
    const startPage = Math.max(1, p.current_page - delta);
    const endPage = Math.min(p.total_pages, p.current_page + delta);

    if (startPage > 1) {
        navHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(1)">1</a></li>`;
        if (startPage > 2) {
            navHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        navHtml += `<li class="page-item ${i === p.current_page ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="loadTableData(${i})">${i}</a>
        </li>`;
    }

    if (endPage < p.total_pages) {
        if (endPage < p.total_pages - 1) {
            navHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        navHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(${p.total_pages})">${p.total_pages}</a></li>`;
    }

    // Next & Last buttons
    if (p.current_page < p.total_pages) {
        navHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(${p.current_page + 1})" title="Berikutnya">›</a></li>`;
        navHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="loadTableData(${p.total_pages})" title="Halaman Terakhir">»</a></li>`;
    } else {
        navHtml += `<li class="page-item disabled"><span class="page-link">›</span></li>`;
        navHtml += `<li class="page-item disabled"><span class="page-link">»</span></li>`;
    }

    $('#paginationNav').html(navHtml);
}

// ════════════════════════════════════════════════════════════════════════════
// JAVASCRIPT HANDLERS FOR ADMIN SCRAPING
// ════════════════════════════════════════════════════════════════════════════
let previewItemsData = [];
let previewEventName = '';
let previewSourceUrl = '';

// 1. Universal URL Scrape Handler
async function runUniversalUrlScrape() {
    const url = $('#inputScrapeUrl').val().trim();
    const eventName = $('#inputScrapeEvent').val().trim();
    const mode = $('#selectScrapeMode').val();

    if (!url) {
        Swal.fire('Perhatian', 'Mohon masukkan URL website pameran.', 'warning');
        return;
    }

    const btn = $('#btnRunUrlScrape');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menganalisa halaman web...');

    try {
        const formData = new FormData();
        formData.append('action', 'universal_scrape_url');
        formData.append('target_url', url);
        formData.append('event_name', eventName || 'Pameran Baru');
        formData.append('scrape_mode', mode);

        const res = await fetch('scraping_exhibitor.php', { method: 'POST', body: formData });
        const data = await res.json();

        btn.prop('disabled', false).html('<i class="bi bi-search me-1"></i> Mulai Ambil &amp; Analisa Halaman Ini');

        if (!data.status) {
            Swal.fire('Gagal Mengambil Data', data.message, 'error');
            return;
        }

        if (data.type === 'krista_network') {
            $('#modalScraperCenter').modal('hide');
            startNetworkScrape(data.base_url, data.event_name);
        } else if (data.type === 'generic_preview') {
            if (data.count === 0) {
                Swal.fire('Tidak Ada Tabel Terdeteksi', data.message, 'info');
                return;
            }

            previewItemsData = data.items;
            previewEventName = data.event_name;
            previewSourceUrl = data.source_url;

            $('#previewTitle').text(`Ditemukan ${data.count} Perusahaan dari Website:`);
            let rowsHtml = '';
            data.items.forEach(it => {
                rowsHtml += `
                    <tr>
                        <td class="fw-bold">${escapeHtml(it.company_name)}</td>
                        <td><span class="badge bg-light text-dark border">${escapeHtml(it.booth_number)}</span></td>
                        <td>${escapeHtml(it.country)}</td>
                        <td class="text-truncate" style="max-width: 200px;">${escapeHtml(it.products)}</td>
                    </tr>
                `;
            });
            $('#previewTableBody').html(rowsHtml);
            $('#urlPreviewContainer').removeClass('d-none');
        }
    } catch (err) {
        btn.prop('disabled', false).html('<i class="bi bi-search me-1"></i> Mulai Ambil &amp; Analisa Halaman Ini');
        Swal.fire('Error', 'Terjadi kesalahan saat memproses URL: ' + err, 'error');
    }
}

// 2. Save Previewed Items
async function savePreviewItems() {
    if (!previewItemsData || previewItemsData.length === 0) return;

    const btn = $('#btnSavePreview');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');

    try {
        const formData = new FormData();
        formData.append('action', 'save_scraped_items');
        formData.append('event_name', previewEventName);
        formData.append('source_url', previewSourceUrl);
        formData.append('items', JSON.stringify(previewItemsData));

        const res = await fetch('scraping_exhibitor.php', { method: 'POST', body: formData });
        const data = await res.json();

        btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Simpan Semua ke Database');

        if (data.status) {
            Swal.fire({
                title: 'Berhasil Disimpan!',
                text: data.message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire('Gagal', data.message, 'error');
        }
    } catch (err) {
        btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Simpan Semua ke Database');
        Swal.fire('Error', 'Gagal menyimpan data.', 'error');
    }
}

// 3. Smart Paste Handler (No-Fail)
async function runSmartPaste() {
    const eventName = $('#inputPasteEvent').val().trim();
    const defaultCountry = $('#inputPasteCountry').val().trim() || 'Indonesia';
    const text = $('#inputPasteText').val().trim();

    if (!eventName) {
        Swal.fire('Perhatian', 'Mohon masukkan Nama Event / Pameran.', 'warning');
        return;
    }
    if (!text) {
        Swal.fire('Perhatian', 'Kotak teks masih kosong! Salin daftar perusahaan dari web dan paste di sini.', 'warning');
        return;
    }

    const btn = $('#btnRunPaste');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Mengekstrak &amp; menyimpan data...');

    try {
        const formData = new FormData();
        formData.append('action', 'smart_paste');
        formData.append('event_name', eventName);
        formData.append('default_country', defaultCountry);
        formData.append('raw_text', text);

        const res = await fetch('scraping_exhibitor.php', { method: 'POST', body: formData });
        const data = await res.json();

        btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill me-1"></i> Ekstrak &amp; Simpan ke Database');

        if (data.status) {
            Swal.fire({
                title: 'Ekstraksi Sukses!',
                text: data.message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire('Gagal', data.message, 'error');
        }
    } catch (err) {
        btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill me-1"></i> Ekstrak &amp; Simpan ke Database');
        Swal.fire('Error', 'Terjadi kesalahan sistem saat parsing teks.', 'error');
    }
}

// 4. CSV Upload Handler
async function runCsvUpload() {
    const eventName = $('#inputCsvEvent').val().trim();
    const fileInput = document.getElementById('inputCsvFile');

    if (!eventName) {
        Swal.fire('Perhatian', 'Mohon masukkan Nama Event / Pameran.', 'warning');
        return;
    }
    if (!fileInput.files || fileInput.files.length === 0) {
        Swal.fire('Perhatian', 'Pilih file CSV terlebih dahulu.', 'warning');
        return;
    }

    const btn = $('#btnRunCsv');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Mengunggah &amp; mengimpor...');

    try {
        const formData = new FormData();
        formData.append('action', 'import_csv');
        formData.append('event_name', eventName);
        formData.append('csv_file', fileInput.files[0]);

        const res = await fetch('scraping_exhibitor.php', { method: 'POST', body: formData });
        const data = await res.json();

        btn.prop('disabled', false).html('<i class="bi bi-upload me-1"></i> Upload &amp; Simpan Data');

        if (data.status) {
            Swal.fire({
                title: 'Import Sukses!',
                text: data.message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire('Gagal', data.message, 'error');
        }
    } catch (err) {
        btn.prop('disabled', false).html('<i class="bi bi-upload me-1"></i> Upload &amp; Simpan Data');
        Swal.fire('Error', 'Gagal memproses file CSV.', 'error');
    }
}

// 5. Krista Multi-Page Scrape Engine
let isScraping = false;
async function startKristaScrape() {
    startNetworkScrape('https://sialinterfood.com', 'Krista Interfood 2026', 43);
}

async function startNetworkScrape(baseUrl, eventName, totalPages = 43) {
    if (isScraping) return;

    const confirm = await Swal.fire({
        title: `Mulai Scraping ${eventName}?`,
        text: `Sistem akan mengambil data dari ${totalPages} halaman secara real-time dan menyimpannya ke database.`,
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
            formData.append('base_url', baseUrl);
            formData.append('event_name', eventName);

            const res = await fetch('scraping_exhibitor.php', { method: 'POST', body: formData });
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
    $('#scrapingLog').text(`Semua ${totalPages} halaman telah selesai diproses. Memuat ulang...`);
    $('#btnStartScrape').prop('disabled', false).html('<i class="bi bi-arrow-repeat me-1"></i> Sync Krista Interfood');

    Swal.fire({
        title: 'Scraping Selesai!',
        text: `Data pameran ${eventName} berhasil disinkronisasi ke database.`,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    }).then(() => {
        window.location.reload();
    });
}

// 6. Add to CRM Action
async function addToCrm(id, companyName) {
    const confirm = await Swal.fire({
        title: 'Tambah ke CRM?',
        html: `Apakah Anda ingin memasukkan <b>${escapeHtml(companyName)}</b> sebagai <b>Kandidat Customer</b> di Sales CRM?`,
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

        const res = await fetch('scraping_exhibitor.php', { method: 'POST', body: formData });
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
            row.addClass('table-light');
            row.find('td:nth-child(7)').html(`
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" style="font-size: 12px;">
                    <i class="bi bi-check-circle-fill me-1"></i> Di CRM
                </span>
            `);
            row.find('.btn-add-crm').replaceWith(`
                <a href="customer_management.php?search=${encodeURIComponent(companyName)}" class="btn btn-sm btn-outline-success rounded-3 px-2 py-1" title="Buka di CRM">
                    <i class="bi bi-box-arrow-right"></i> CRM
                </a>
            `);

            const currentCrm = parseInt($('#statCrm').text().replace(/,/g, '')) || 0;
            $('#statCrm').text((currentCrm + 1).toLocaleString());
        } else {
            Swal.fire('Gagal', data.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

function escapeJsString(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}
</script>

<?php include "includes/footer.php"; ?>
