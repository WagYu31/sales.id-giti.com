<?php
/**
 * scraping_exhibitor.php - Lead Harvester & Pusat Scraping Database Pameran
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

// ════════════════════════════════════════════════════════════════════════════
// 1. AJAX / API HANDLERS
// ════════════════════════════════════════════════════════════════════════════

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
                // Check if already exists in this event
                $chk = $conn->prepare("SELECT id FROM krista_exhibitors WHERE event_name = ? AND company_name = ?");
                $chk->bind_param("ss", $event_name, $name);
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

    // Extract table rows or list cards
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

        // Check if exists
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
            // Detect header positions
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
// 2. STATS & FILTER DATA
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
.event-badge {
    background: #FEF3C7;
    color: #B45309;
    border: 1px solid #FDE68A;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-block;
}
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

    <!-- ─── BENTO HERO BANNER ─── -->
    <div class="scraper-hero">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); font-size: 12px; font-weight: 700;">
                    <i class="bi bi-broadcast text-warning"></i>
                    <span>LEAD GENERATION &amp; EXHIBITION HARVESTER</span>
                </div>
                <h2 class="fw-bold mb-2 text-white" style="letter-spacing: -0.5px;">
                    Pusat Scraping &amp; Database Pameran Industri
                </h2>
                <p class="text-white-50 mb-3" style="font-size: 14px; max-width: 680px;">
                    Kumpulkan database calon klien dari berbagai event pameran (Krista Interfood, ALLPACK, Indobuildtech, dll). Admin dapat menambah link website pameran baru kapan saja tanpa perlu bantuan IT!
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-warning px-4 py-2 fw-bold text-dark rounded-pill shadow" data-bs-toggle="modal" data-bs-target="#modalScraperCenter">
                        <i class="bi bi-plus-circle-fill me-1"></i> + Scrape Link Baru / Tambah Data
                    </button>
                    <button class="btn btn-outline-light px-4 py-2 fw-semibold rounded-pill" id="btnStartScrape" onclick="startKristaScrape()">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync / Scrape Ulang Krista
                    </button>
                    <a href="scraping_exhibitor.php?action=export_csv" id="btnExportCsv" class="btn btn-outline-light px-4 py-2 fw-semibold rounded-pill">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Excel (CSV)
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <img src="assets/images/loewix_sales_logo_white.png" alt="Loewix" style="max-height: 48px; opacity: 0.85;" onerror="this.remove()">
                <div class="small text-white-50 mt-2">Dukungan: Multi-Event URL &amp; Smart Copy-Paste</div>
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
                    <div class="small text-primary fw-medium">Peserta Pameran</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-scraper">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.12); color: #D97706;">
                    <i class="bi bi-calendar-event-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Event Terdata</div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo count($events_list); ?> Event</h3>
                    <div class="small text-warning fw-medium">Krista, ALLPACK, dll</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-card-scraper">
                <div class="stat-icon" style="background: rgba(14, 165, 233, 0.12); color: #0EA5E9;">
                    <i class="bi bi-globe2"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Lokal / Internasional</div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stat_indo); ?> / <?php echo number_format($stat_intl); ?></h3>
                    <div class="small text-info fw-medium">Indonesia &amp; Luar Negeri</div>
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
                    <div class="small text-success fw-medium">Kandidat Customer Aktif</div>
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

    <!-- ─── TABLE FILTER & DATA CARD ─── -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="border: 1px solid #E2E8F0 !important;">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Pilih Event / Pameran</label>
                    <select class="form-select form-select-sm rounded-3 fw-semibold text-primary" id="filterEvent" onchange="applyFilters()">
                        <option value="">Semua Event (Total: <?php echo number_format($stat_total); ?>)</option>
                        <?php foreach ($events_list as $ev): ?>
                            <option value="<?php echo htmlspecialchars($ev['event_name']); ?>">
                                <?php echo htmlspecialchars($ev['event_name']); ?> (<?php echo number_format($ev['cnt']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Asal Negara</label>
                    <select class="form-select form-select-sm rounded-3" id="filterCountry" onchange="applyFilters()">
                        <option value="">Semua Negara</option>
                        <?php while ($c = $countries_res->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($c['country']); ?>">
                                <?php echo htmlspecialchars($c['country']); ?> (<?php echo $c['c']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Status di CRM</label>
                    <select class="form-select form-select-sm rounded-3" id="filterStatus" onchange="applyFilters()">
                        <option value="">Semua Status</option>
                        <option value="Belum Dihubungi">Belum Masuk CRM</option>
                        <option value="Tersimpan di CRM">Sudah di CRM</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Pencarian Cepat</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control form-control-sm border-start-0 rounded-end-3" id="filterSearch" placeholder="Cari nama PT, produk, booth..." onkeyup="applyFilters()">
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
                            <th style="width: 150px;">Event / Pameran</th>
                            <th style="width: 130px;">Asal Negara</th>
                            <th style="width: 120px;">Nomor Booth</th>
                            <th>Aktivitas &amp; Kategori Produk</th>
                            <th style="width: 130px;">Status CRM</th>
                            <th class="text-end pe-4" style="width: 170px;">Aksi Sales</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php
                        $list_q = $conn->query("SELECT * FROM krista_exhibitors ORDER BY (customer_id IS NOT NULL) DESC, id ASC LIMIT 250");
                        $no = 1;
                        while ($row = $list_q->fetch_assoc()):
                            $initials = strtoupper(substr($row['company_name'], 0, 2));
                            $is_crm = !empty($row['customer_id']);
                        ?>
                        <tr id="row-<?php echo $row['id']; ?>" class="<?php echo $is_crm ? 'table-light' : ''; ?>" data-event="<?php echo htmlspecialchars($row['event_name']); ?>" data-country="<?php echo htmlspecialchars($row['country']); ?>" data-status="<?php echo htmlspecialchars($row['status_prospek']); ?>">
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
                                                <i class="bi bi-box-arrow-up-right" style="font-size: 11px;"></i> Profil Web
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="event-badge"><?php echo htmlspecialchars($row['event_name'] ?: 'Pameran'); ?></span>
                            </td>
                            <td>
                                <span class="country-pill">
                                    <?php if ($row['country'] === 'Indonesia'): ?>
                                        🇮🇩 Indonesia
                                    <?php elseif ($row['country'] === 'China'): ?>
                                        🇨🇳 China
                                    <?php elseif ($row['country'] === 'South Korea'): ?>
                                        🇰🇷 South Korea
                                    <?php elseif ($row['country'] === 'Malaysia'): ?>
                                        🇲🇾 Malaysia
                                    <?php else: ?>
                                        🌐 <?php echo htmlspecialchars($row['country'] ?: '-'); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span class="booth-badge"><?php echo htmlspecialchars($row['booth_number'] ?: '-'); ?></span>
                            </td>
                            <td class="small">
                                <?php if (!empty($row['business_activity']) && $row['business_activity'] !== '-'): ?>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['business_activity']); ?></div>
                                <?php endif; ?>
                                <div class="text-muted text-truncate" style="max-width: 320px;" title="<?php echo htmlspecialchars($row['products']); ?>">
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
                                        Belum di CRM
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <?php if (!$is_crm): ?>
                                        <button class="btn btn-sm btn-success rounded-3 btn-add-crm" onclick="addToCrm(<?php echo $row['id']; ?>, '<?php echo addslashes($row['company_name']); ?>')" title="Masukkan ke CRM sebagai Prospek">
                                            <i class="bi bi-person-plus-fill me-1"></i> + CRM
                                        </button>
                                    <?php else: ?>
                                        <a href="customer_management.php?search=<?php echo urlencode($row['company_name']); ?>" class="btn btn-sm btn-outline-success rounded-3" title="Buka di CRM">
                                            <i class="bi bi-box-arrow-right"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="https://www.google.com/search?q=<?php echo urlencode($row['company_name'] . ' ' . $row['country']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3" title="Cari di Google">
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
        <div class="card-footer bg-white py-3 px-4 text-muted small d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>Menampilkan data awal. Gunakan filter event di atas atau tombol download Excel untuk seluruh data.</div>
            <a href="scraping_exhibitor.php?action=export_csv" class="btn btn-sm btn-success rounded-pill px-3">
                <i class="bi bi-download me-1"></i> Download Data ke Excel
            </a>
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
            // Run Krista engine pagination
            $('#modalScraperCenter').modal('hide');
            startNetworkScrape(data.base_url, data.event_name);
        } else if (data.type === 'generic_preview') {
            if (data.count === 0) {
                Swal.fire('Tidak Ada Tabel Terdeteksi', data.message, 'info');
                return;
            }

            // Render Preview Table
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
    $('#btnStartScrape').prop('disabled', false).html('<i class="bi bi-arrow-repeat me-1"></i> Sync / Scrape Ulang Krista');

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
            row.find('td:nth-child(7)').html(`
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                    <i class="bi bi-check-circle-fill me-1"></i> Di CRM
                </span>
            `);
            row.find('.btn-add-crm').replaceWith(`
                <a href="customer_management.php?search=${encodeURIComponent(companyName)}" class="btn btn-sm btn-outline-success rounded-3" title="Buka di CRM">
                    <i class="bi bi-box-arrow-right"></i>
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

// 7. Client-side Filters
function applyFilters() {
    const eventVal = $('#filterEvent').val().toLowerCase();
    const country = $('#filterCountry').val().toLowerCase();
    const status = $('#filterStatus').val();
    const query = $('#filterSearch').val().toLowerCase();

    // Update export button link with active event filter
    const activeEvent = $('#filterEvent').val();
    $('#btnExportCsv').attr('href', `scraping_exhibitor.php?action=export_csv&event=${encodeURIComponent(activeEvent)}`);

    $('#tableBody tr').each(function() {
        const row = $(this);
        const rowEvent = (row.data('event') || row.find('td:nth-child(3)').text()).toLowerCase();
        const rowCountry = (row.data('country') || row.find('td:nth-child(4)').text()).toLowerCase();
        const rowStatus = row.find('td:nth-child(7)').text();
        const rowText = row.text().toLowerCase();

        let show = true;
        if (eventVal && !rowEvent.includes(eventVal)) show = false;
        if (country && !rowCountry.includes(country)) show = false;
        if (status === 'Tersimpan di CRM' && !rowStatus.includes('Di CRM')) show = false;
        if (status === 'Belum Dihubungi' && rowStatus.includes('Di CRM')) show = false;
        if (query && !rowText.includes(query)) show = false;

        row.toggle(show);
    });
}

function resetFilters() {
    $('#filterEvent').val('');
    $('#filterCountry').val('');
    $('#filterStatus').val('');
    $('#filterSearch').val('');
    applyFilters();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>

<?php include "includes/footer.php"; ?>
