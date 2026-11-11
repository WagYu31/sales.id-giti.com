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

// Auto-seed missing records from JSON if count is less than 858
$curr_cnt = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE event_name = 'Krista Interfood 2026'")->fetch_assoc()['t'] ?? 0;
if ($curr_cnt < 858 && file_exists(__DIR__ . '/krista_interfood_exhibitors.json')) {
    $json_raw = file_get_contents(__DIR__ . '/krista_interfood_exhibitors.json');
    $json_data = json_decode($json_raw, true);
    if (!empty($json_data)) {
        $stmt_seed = $conn->prepare("
            INSERT INTO `krista_exhibitors` 
            (`event_name`, `source_url`, `exhibitor_id`, `company_name`, `country`, `booth_number`, `business_activity`, `products`, `lead_type`, `detail_url`, `logo_url`)
            VALUES ('Krista Interfood 2026', 'https://sialinterfood.com', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($json_data as $d) {
            $ex_id = $d['id'] ?? null;
            $name = trim($d['company_name'] ?? '');
            $country = trim($d['country'] ?? '');
            $booth = trim($d['booth_number'] ?? '');
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
                $stmt_seed->bind_param("issssssss", $ex_id, $name, $country, $booth, $activity, $prods, $lead_type, $detail_url, $logo);
                $stmt_seed->execute();
            }
        }
    }
}

// Country flag badge helper
function getCountryFlagBadge($country) {
    $c = trim($country);
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

// ════════════════════════════════════════════════════════════════════════════
// 1. AJAX / API HANDLERS
// ════════════════════════════════════════════════════════════════════════════

// Handle AJAX Table Data & Pagination
if (isset($_GET['action']) && $_GET['action'] === 'get_table_data') {
    header('Content-Type: application/json');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = intval($_GET['limit'] ?? 50);
    if ($limit < 1 || $limit > 1000) $limit = 50;

    $event_filter = trim($_GET['event'] ?? '');
    $country_filter = trim($_GET['country'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
    $search_query = trim($_GET['q'] ?? '');

    $where = ["1=1"];
    $params = [];
    $types = "";

    if (!empty($event_filter)) {
        $where[] = "event_name = ?";
        $params[] = $event_filter;
        $types .= "s";
    }
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

    $where_sql = implode(" AND ", $where);

    // Total filtered count
    $count_sql = "SELECT COUNT(*) as t FROM krista_exhibitors WHERE $where_sql";
    $c_stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $c_stmt->bind_param($types, ...$params);
    }
    $c_stmt->execute();
    $total_rows = $c_stmt->get_result()->fetch_assoc()['t'] ?? 0;

    $total_pages = max(1, ceil($total_rows / $limit));
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    $offset = ($page - 1) * $limit;

    // Fetch page items
    $data_sql = "SELECT * FROM krista_exhibitors WHERE $where_sql ORDER BY (customer_id IS NOT NULL) DESC, id ASC LIMIT ? OFFSET ?";
    $params_data = $params;
    $params_data[] = $limit;
    $params_data[] = $offset;
    $types_data = $types . "ii";

    $d_stmt = $conn->prepare($data_sql);
    $d_stmt->bind_param($types_data, ...$params_data);
    $d_stmt->execute();
    $rows = $d_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $start_no = $offset + 1;
    $formatted_rows = [];
    foreach ($rows as $r) {
        $formatted_rows[] = [
            'no' => $start_no++,
            'id' => $r['id'],
            'exhibitor_id' => $r['exhibitor_id'],
            'event_name' => $r['event_name'] ?: 'Krista Interfood 2026',
            'company_name' => $r['company_name'],
            'country' => $r['country'],
            'country_flag' => getCountryFlagBadge($r['country']),
            'booth_number' => $r['booth_number'] ?: '-',
            'business_activity' => $r['business_activity'] ?: '-',
            'products' => $r['products'] ?: '-',
            'status_prospek' => $r['status_prospek'],
            'customer_id' => $r['customer_id'],
            'detail_url' => $r['detail_url'],
            'logo_url' => $r['logo_url'],
            'initials' => strtoupper(substr($r['company_name'], 0, 2))
        ];
    }

    echo json_encode([
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
    ]);
    exit;
}

// Handle Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $event_filter = trim($_GET['event'] ?? '');
    $country_filter = trim($_GET['country'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
    $search_query = trim($_GET['q'] ?? '');

    $where = ["1=1"];
    $params = [];
    $types = "";

    if (!empty($event_filter)) {
        $where[] = "event_name = ?";
        $params[] = $event_filter;
        $types .= "s";
    }
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

    $sql = "SELECT id, event_name, exhibitor_id, company_name, country, booth_number, business_activity, products, detail_url, status_prospek, created_at FROM krista_exhibitors WHERE " . implode(" AND ", $where) . " ORDER BY id ASC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $fn_suffix = !empty($event_filter) ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $event_filter) : 'Semua_Event';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Exhibitor_' . $fn_suffix . '_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['No', 'Event/Pameran', 'ID Pameran', 'Nama Perusahaan', 'Negara', 'Nomor Booth', 'Kategori Bisnis', 'Produk', 'Status Prospek', 'Link Detail'], ',', '"', "\\");

    $no = 1;
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $no++,
            $row['event_name'],
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
        : "Exhibitor " . $exhibitor['event_name'] . " (Booth: " . $exhibitor['booth_number'] . ")";
    $deal = 'N';
    $kandidat = 'Y';
    $potensial = 'Y';
    $acc_boss = 'N';
    $acc_note = "Lead otomatis dari Scraper ({$exhibitor['event_name']}, Asal: {$exhibitor['country']}, Booth: {$exhibitor['booth_number']})";

    $ins = $conn->prepare("
        INSERT INTO customers (sales_id, tgl_input, nama_toko, kategori, deal, kandidat, potensial, acc_boss, acc_boss_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->bind_param("issssssss", $sales_id, $tgl_input, $nama_toko, $kategori, $deal, $kandidat, $potensial, $acc_boss, $acc_note);

    if ($ins->execute()) {
        $new_cust_id = $conn->insert_id;
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

// Handle Live Scraping Batch for Krista Media / SIAL Network
if (isset($_POST['action']) && $_POST['action'] === 'run_scrape_page') {
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
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
// 2. STATS & FILTER DATA (INITIAL LOAD)
// ════════════════════════════════════════════════════════════════════════════
$stat_total = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors")->fetch_assoc()['t'] ?? 0;
$stat_indo = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE country = 'Indonesia'")->fetch_assoc()['t'] ?? 0;
$stat_intl = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE country != 'Indonesia' AND country != ''")->fetch_assoc()['t'] ?? 0;
$stat_crm = $conn->query("SELECT COUNT(*) as t FROM krista_exhibitors WHERE customer_id IS NOT NULL")->fetch_assoc()['t'] ?? 0;

// Events list for filter
$events_res = $conn->query("SELECT event_name, COUNT(*) as cnt FROM krista_exhibitors GROUP BY event_name ORDER BY cnt DESC");
$events_list = [];
while ($ev = $events_res->fetch_assoc()) {
    $events_list[] = $ev;
}

// Countries list for filter
$countries_res = $conn->query("SELECT country, COUNT(*) as c FROM krista_exhibitors WHERE country != '' GROUP BY country ORDER BY c DESC");

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
                    <a href="scraping_exhibitor.php?action=export_csv" id="btnExportCsv" class="btn btn-outline-light px-3 py-2 fw-semibold rounded-pill" style="backdrop-filter: blur(8px); background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.25);">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Excel (CSV)
                    </a>
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

        <!-- Card 2: Event Terdata -->
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-executive">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Event Pameran</span>
                    <div class="stat-icon-wrapper" style="background: rgba(245, 158, 11, 0.1); color: #D97706;">
                        <i class="bi bi-calendar2-event-fill"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($events_list); ?> <span style="font-size: 18px; font-weight: 600; color: #64748B;">Event</span></div>
                <div class="small text-muted mt-1 d-flex align-items-center gap-1">
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size: 11px;">
                        Krista, ALLPACK, &amp; Lainnya
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

        <!-- Table Responsive -->
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
                        <th style="width: 140px;">Event</th>
                        <th style="width: 130px;">Asal Negara</th>
                        <th style="width: 120px;">Nomor Booth</th>
                        <th>Aktivitas &amp; Kategori Produk</th>
                        <th style="width: 130px;">Status CRM</th>
                        <th class="text-end pe-4" style="width: 170px;">Aksi Sales</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Populated dynamically via AJAX -->
                </tbody>
            </table>
        </div>

        <!-- Table Footer: Pagination & Limit Controls -->
        <div class="bg-white py-3 px-4 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="small text-muted fw-semibold" id="paginationSummary">
                    Menampilkan 0 - 0 dari 0 perusahaan
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
                        <!-- Populated dynamically via JS -->
                    </ul>
                </nav>

                <a href="scraping_exhibitor.php?action=export_csv" id="btnFooterExport" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm">
                    <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> Download Excel
                </a>
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

// Initial Load on Document Ready
$(document).ready(function() {
    loadTableData(1);
});

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

async function loadTableData(page = 1) {
    currentPage = page;
    const eventVal = $('#filterEvent').val();
    const countryVal = $('#filterCountry').val();
    const statusVal = $('#filterStatus').val();
    const queryVal = $('#filterSearch').val().trim();

    // Update export button URLs
    const exportUrl = `scraping_exhibitor.php?action=export_csv&event=${encodeURIComponent(eventVal)}&country=${encodeURIComponent(countryVal)}&status=${encodeURIComponent(statusVal)}&q=${encodeURIComponent(queryVal)}`;
    $('#btnExportCsv').attr('href', exportUrl);
    $('#btnFooterExport').attr('href', exportUrl);

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
        const data = await res.json();

        $('#tableLoadingOverlay').addClass('d-none');

        if (!data.status) {
            $('#tableBody').html(`<tr><td colspan="8" class="text-center py-5 text-muted">Gagal memuat data.</td></tr>`);
            return;
        }

        renderTableRows(data.rows);
        renderPagination(data.pagination);

    } catch (err) {
        console.error('Error fetching data:', err);
        $('#tableLoadingOverlay').addClass('d-none');
        $('#tableBody').html(`<tr><td colspan="8" class="text-center py-5 text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Terjadi kesalahan saat memuat data.</td></tr>`);
    }
}

function renderTableRows(rows) {
    if (!rows || rows.length === 0) {
        $('#tableBody').html(`
            <tr>
                <td colspan="8" class="text-center py-5">
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
                <td class="small">
                    ${r.business_activity && r.business_activity !== '-' ? `<div class="fw-semibold text-dark mb-0.5">${escapeHtml(r.business_activity)}</div>` : ''}
                    <div class="text-muted text-truncate" style="max-width: 320px;" title="${escapeHtml(r.products)}">
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
    // Update summary text
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
