<?php
/**
 * ajax_wa_broadcast.php
 * Backend AJAX untuk Eksekusi Fitur WhatsApp Broadcast CRM & Leads (Loewix Sales Workspace)
 * Mendukung pengiriman per antrean (queue), personalisasi variabel, pencatatan otomatis follow-up,
 * serta pengambilan target massal berbasis filter.
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verifikasi sesi login sales / superadmin
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi login telah berakhir. Silakan login kembali.'
    ]);
    exit();
}

$action = trim($_POST['action'] ?? ($_GET['action'] ?? ''));

// Helper: Ambil Token Fonnte & Nomor Pengaturan
function getGatewayCredentials($conn) {
    $tokenGateway = '';
    $configFile = __DIR__ . '/includes/wa_gateway_config.json';
    if (file_exists($configFile)) {
        $cfg = json_decode(@file_get_contents($configFile), true);
        if (!empty($cfg['fonnte_token'])) {
            $tokenGateway = trim($cfg['fonnte_token']);
        }
    }
    if (empty($tokenGateway)) {
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key IN ('fonnte_token', 'wa_gateway_token') AND setting_value IS NOT NULL AND setting_value != '' LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $tokenGateway = trim($row['setting_value']);
        }
    }
    return $tokenGateway;
}

// -----------------------------------------------------------------------------
// 1. ACTION: CHECK TOKEN STATUS
// -----------------------------------------------------------------------------
if ($action === 'check_token') {
    $token = getGatewayCredentials($conn);
    echo json_encode([
        'success' => true,
        'has_token' => !empty($token),
        'token_preview' => !empty($token) ? substr($token, 0, 6) . '...' : ''
    ]);
    exit();
}

// -----------------------------------------------------------------------------
// 2. ACTION: SEND SINGLE BROADCAST ITEM (Untuk Antrean Real-Time di Browser)
// -----------------------------------------------------------------------------
if ($action === 'send_single') {
    $rawPhone    = trim($_POST['phone'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    $customerId  = (int)($_POST['customer_id'] ?? 0);
    $customerName= trim($_POST['customer_name'] ?? '');
    $logFu       = (int)($_POST['log_fu'] ?? 1);

    if (empty($rawPhone)) {
        echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp tujuan kosong.']);
        exit();
    }
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Pesan broadcast tidak boleh kosong.']);
        exit();
    }

    // Normalisasi Nomor WhatsApp Indonesia
    $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
    if (substr($cleanPhone, 0, 1) === '0') {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    } elseif (substr($cleanPhone, 0, 2) !== '62') {
        $cleanPhone = '62' . $cleanPhone;
    }

    if (strlen($cleanPhone) < 9) {
        echo json_encode(['success' => false, 'message' => "Nomor telepon ({$rawPhone}) tidak valid."]);
        exit();
    }

    $token = getGatewayCredentials($conn);
    if (empty($token)) {
        echo json_encode([
            'success' => false,
            'message' => 'Token API Fonnte belum dipasang di sistem. Silakan isi token di menu Pengaturan WA Gateway terlebih dahulu.'
        ]);
        exit();
    }

    // Kirim ke API Fonnte
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'target' => $cleanPhone,
            'message' => $message,
            'countryCode' => '62'
        ],
        CURLOPT_HTTPHEADER => [
            "Authorization: {$token}"
        ],
    ]);

    $response = curl_exec($curl);
    $curlErr  = curl_error($curl);
    curl_close($curl);

    if ($curlErr) {
        echo json_encode(['success' => false, 'message' => 'Gagal koneksi ke Fonnte: ' . $curlErr]);
        exit();
    }

    $respData = json_decode($response, true);
    $isSuccess = isset($respData['status']) && ($respData['status'] === true || $respData['status'] === 1 || $respData['status'] === 'true');

    if ($isSuccess) {
        // Catat Otomatis ke Riwayat Follow-Up CRM jika opsi dicentang
        if ($logFu === 1 && $customerId > 0) {
            $activeSalesId = (int)($_SESSION['user_id'] ?? 1);
            $tglFu = date('Y-m-d H:i:s');
            $respon = 'Broadcast WhatsApp';
            $keterangan = "Pesan Broadcast Terkirim ke {$cleanPhone}:\n" . $message;

            $stmtFu = $conn->prepare("INSERT INTO follow_ups (customer_id, sales_id, tgl_follow_up, respon, keterangan) VALUES (?, ?, ?, ?, ?)");
            if ($stmtFu) {
                $stmtFu->bind_param("iisss", $customerId, $activeSalesId, $tglFu, $respon, $keterangan);
                $stmtFu->execute();
                $stmtFu->close();
            }
        }

        echo json_encode([
            'success' => true,
            'phone' => $cleanPhone,
            'customer_name' => $customerName,
            'fonnte_status' => $respData['status'] ?? true,
            'detail' => $respData['detail'] ?? 'Terkirim'
        ]);
    } else {
        $errorReason = $respData['reason'] ?? ($respData['message'] ?? 'Ditolak oleh WhatsApp / Fonnte');
        echo json_encode([
            'success' => false,
            'phone' => $cleanPhone,
            'customer_name' => $customerName,
            'message' => $errorReason
        ]);
    }
    exit();
}

// -----------------------------------------------------------------------------
// 3. ACTION: GET FILTER TARGETS (Mengambil semua target customer sesuai filter aktif)
// -----------------------------------------------------------------------------
if ($action === 'get_filter_targets') {
    $filter_kota = trim($_POST['filter_kota'] ?? '');
    $filter_kategori = trim($_POST['filter_kategori'] ?? '');
    $filter_sales = intval($_POST['filter_sales'] ?? 0);
    $filter_fu = trim($_POST['filter_fu'] ?? '');
    $search_keyword = trim($_POST['search'] ?? '');
    $max_limit = isset($_POST['max_limit']) ? max(1, min(500, (int)$_POST['max_limit'])) : 100;

    $sql_where_conditions = ["c.deleted_at IS NULL"];
    $params = [];
    $types = '';

    $active_sales_id = 0;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'sales') {
        $active_sales_id = (int)$_SESSION['user_id'];
        $sql_where_conditions[] = "c.sales_id = ?";
        $params[] = $active_sales_id;
        $types .= 'i';
    } elseif ($filter_sales > 0) {
        $active_sales_id = $filter_sales;
        $sql_where_conditions[] = "c.sales_id = ?";
        $params[] = $active_sales_id;
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

    if (!empty($search_keyword)) {
        $clean_kw = trim(preg_replace('/[\-_,]+/u', ' ', $search_keyword));
        $words = array_values(array_filter(explode(' ', $clean_kw), function($w) { 
            return mb_strlen(trim($w)) >= 2; 
        }));
        
        if (count($words) > 1) {
            $word_conditions = [];
            foreach ($words as $word) {
                $word_like = '%' . $word . '%';
                $word_conditions[] = "(
                    c.nama_toko LIKE ? 
                    OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?))
                    OR c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND (alamat LIKE ? OR kota LIKE ?))
                )";
                array_push($params, $word_like, $word_like, $word_like, $word_like, $word_like);
                $types .= 'sssss';
            }
            $sql_where_conditions[] = "(" . implode(' AND ', $word_conditions) . ")";
        } else {
            $like_kw = '%' . $search_keyword . '%';
            $sql_where_conditions[] = "(
                c.nama_toko LIKE ? 
                OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?))
                OR c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND (alamat LIKE ? OR kota LIKE ?))
            )";
            array_push($params, $like_kw, $like_kw, $like_kw, $like_kw, $like_kw);
            $types .= 'sssss';
        }
    }

    // Pastikan hanya ambil customer yang memiliki nomor telepon
    $sql_where_conditions[] = "c.id IN (SELECT DISTINCT customer_id FROM customer_pics WHERE deleted_at IS NULL AND tlp_pic IS NOT NULL AND TRIM(tlp_pic) != '')";

    $where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

    $sql = "
        SELECT 
            c.id, c.nama_toko, c.kategori,
            COALESCE(s.nama_lengkap, 'Sales Loewix') AS nama_sales,
            (SELECT GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL) AS all_pics,
            (SELECT GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL) AS all_phones,
            (SELECT GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.deleted_at IS NULL) AS all_cities
        FROM 
            customers c
        LEFT JOIN 
            sales s ON c.sales_id = s.id
        {$where_clause}
        ORDER BY 
            c.id DESC
        LIMIT ?
    ";

    $params[] = $max_limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $targets = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pics = !empty($row['all_pics']) ? explode('||', $row['all_pics']) : [];
            $phones = !empty($row['all_phones']) ? explode('||', $row['all_phones']) : [];
            
            $validPhone = '';
            $validPic = '';
            foreach ($phones as $idx => $ph) {
                $cleaned = preg_replace('/[^0-9]/', '', $ph);
                if (strlen($cleaned) >= 9) {
                    $validPhone = $ph;
                    $validPic = $pics[$idx] ?? '';
                    break;
                }
            }

            if (!empty($validPhone)) {
                $targets[] = [
                    'id' => (int)$row['id'],
                    'nama_toko' => trim($row['nama_toko']),
                    'pic' => trim($validPic),
                    'phone' => trim($validPhone),
                    'city' => trim($row['all_cities'] ?? '-'),
                    'sales' => trim($row['nama_sales'] ?? 'Sales Loewix')
                ];
            }
        }
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'total' => count($targets),
        'targets' => $targets
    ]);
    exit();
}

// -----------------------------------------------------------------------------
// 4. ACTION: LOG CLICK FOLLOW-UP (Untuk Mode Asisten 1-Klik / Direct WhatsApp)
// -----------------------------------------------------------------------------
if ($action === 'log_click_followup') {
    $customerId  = (int)($_POST['customer_id'] ?? 0);
    $rawPhone    = trim($_POST['phone'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    $customerName= trim($_POST['customer_name'] ?? '');

    if ($customerId > 0) {
        $activeSalesId = (int)($_SESSION['user_id'] ?? 1);
        $tglFu = date('Y-m-d H:i:s');
        $respon = 'Broadcast WhatsApp (1-Klik)';
        $keterangan = "Pesan Broadcast Terkirim ke {$rawPhone} ({$customerName}):\n" . $message;

        $stmtFu = $conn->prepare("INSERT INTO follow_ups (customer_id, sales_id, tgl_follow_up, respon, keterangan) VALUES (?, ?, ?, ?, ?)");
        if ($stmtFu) {
            $stmtFu->bind_param("iisss", $customerId, $activeSalesId, $tglFu, $respon, $keterangan);
            $stmtFu->execute();
            $stmtFu->close();
        }
    }
    echo json_encode(['success' => true, 'message' => 'Follow up tercatat']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);

