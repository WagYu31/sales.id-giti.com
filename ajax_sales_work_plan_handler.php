<?php
/**
 * AJAX Handler for Sales Work Plans (Rencana Kerja Sales)
 * PT. Loewix Indonesia
 */

require_once __DIR__ . '/includes/db.php';
global $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi telah berakhir. Silakan login kembali.']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['nama_lengkap'] ?? 'User';
$is_admin  = in_array($user_role, ['superadmin', 'adminsales']);

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// Helper to determine week of the month (1-5)
function getWeekOfMonth($dateStr) {
    if (empty($dateStr)) return 1;
    $time = strtotime($dateStr);
    $day = (int)date('j', $time);
    return (int)ceil($day / 7);
}

// Build base WHERE clause from filters
function buildWhereClause($conn, $user_role, $user_id, $params) {
    $where = ["wp.deleted_at IS NULL"];
    
    // Role filter
    if ($user_role === 'sales') {
        $where[] = "wp.sales_id = " . intval($user_id);
    } elseif (!empty($params['sales_id'])) {
        $where[] = "wp.sales_id = " . intval($params['sales_id']);
    }
    
    // Year filter
    if (!empty($params['tahun'])) {
        $where[] = "YEAR(wp.tanggal) = " . intval($params['tahun']);
    }
    
    // Month filter
    if (!empty($params['bulan'])) {
        $where[] = "MONTH(wp.tanggal) = " . intval($params['bulan']);
    }
    
    // Week filter (1-5)
    if (!empty($params['minggu']) && is_numeric($params['minggu'])) {
        $minggu = intval($params['minggu']);
        $start_day = ($minggu - 1) * 7 + 1;
        $end_day   = min($minggu * 7, 31);
        $where[] = "DAY(wp.tanggal) BETWEEN {$start_day} AND {$end_day}";
    }
    
    // Custom Date Range filter (optional)
    if (!empty($params['tgl_mulai'])) {
        $tgl_mulai = $conn->real_escape_string($params['tgl_mulai']);
        $where[] = "wp.tanggal >= '{$tgl_mulai}'";
    }
    if (!empty($params['tgl_akhir'])) {
        $tgl_akhir = $conn->real_escape_string($params['tgl_akhir']);
        $where[] = "wp.tanggal <= '{$tgl_akhir}'";
    }
    
    // Method filter
    if (!empty($params['metode_fu'])) {
        $metode = $conn->real_escape_string($params['metode_fu']);
        $where[] = "wp.metode_fu LIKE '%{$metode}%'";
    }
    
    // Status done filter (1 = done, 0 = pending)
    if (isset($params['status_done']) && $params['status_done'] !== '') {
        $where[] = "wp.is_done = " . intval($params['status_done']);
    }
    
    // Search keyword
    if (!empty($params['search'])) {
        $search = $conn->real_escape_string(trim($params['search']));
        $where[] = "(wp.nama_customer LIKE '%{$search}%' OR wp.kontak_customer LIKE '%{$search}%' OR wp.email_customer LIKE '%{$search}%' OR wp.aktivitas LIKE '%{$search}%' OR wp.hasil_fu LIKE '%{$search}%')";
    }
    
    return implode(' AND ', $where);
}

switch ($action) {

    // -------------------------------------------------------------
    // 1. GET SUMMARY COUNTERS (Metode FU, Total, % Done)
    // -------------------------------------------------------------
    case 'get_summary':
        try {
            $where = buildWhereClause($conn, $user_role, $user_id, $_GET);
            
            $sql = "
                SELECT 
                    COUNT(*) AS total_rencana,
                    SUM(CASE WHEN wp.is_done = 1 THEN 1 ELSE 0 END) AS total_done,
                    SUM(CASE WHEN wp.is_done = 0 THEN 1 ELSE 0 END) AS total_pending,
                    SUM(CASE WHEN LOWER(wp.metode_fu) LIKE '%phone%' OR LOWER(wp.metode_fu) LIKE '%call%' OR LOWER(wp.metode_fu) LIKE '%telepon%' THEN 1 ELSE 0 END) AS total_phone,
                    SUM(CASE WHEN LOWER(wp.metode_fu) LIKE '%wa%' OR LOWER(wp.metode_fu) LIKE '%what%' OR LOWER(wp.metode_fu) LIKE '%text%' THEN 1 ELSE 0 END) AS total_wa,
                    SUM(CASE WHEN LOWER(wp.metode_fu) LIKE '%email%' OR LOWER(wp.metode_fu) LIKE '%mail%' THEN 1 ELSE 0 END) AS total_email,
                    SUM(CASE WHEN LOWER(wp.metode_fu) LIKE '%ketemu%' OR LOWER(wp.metode_fu) LIKE '%visit%' OR LOWER(wp.metode_fu) LIKE '%kunjungan%' OR LOWER(wp.metode_fu) LIKE '%langsung%' THEN 1 ELSE 0 END) AS total_ketemu,
                    SUM(CASE WHEN 
                        LOWER(wp.metode_fu) NOT LIKE '%phone%' AND LOWER(wp.metode_fu) NOT LIKE '%call%' AND LOWER(wp.metode_fu) NOT LIKE '%telepon%' AND
                        LOWER(wp.metode_fu) NOT LIKE '%wa%' AND LOWER(wp.metode_fu) NOT LIKE '%what%' AND LOWER(wp.metode_fu) NOT LIKE '%text%' AND
                        LOWER(wp.metode_fu) NOT LIKE '%email%' AND LOWER(wp.metode_fu) NOT LIKE '%mail%' AND
                        LOWER(wp.metode_fu) NOT LIKE '%ketemu%' AND LOWER(wp.metode_fu) NOT LIKE '%visit%' AND LOWER(wp.metode_fu) NOT LIKE '%kunjungan%' AND LOWER(wp.metode_fu) NOT LIKE '%langsung%'
                    THEN 1 ELSE 0 END) AS total_lainnya
                FROM sales_work_plans wp
                WHERE {$where}
            ";
            
            $res = $conn->query($sql);
            $summary = $res ? $res->fetch_assoc() : [];
            
            $total_rencana = (int)($summary['total_rencana'] ?? 0);
            $total_done    = (int)($summary['total_done'] ?? 0);
            $total_pending = (int)($summary['total_pending'] ?? 0);
            $percentage    = $total_rencana > 0 ? round(($total_done / $total_rencana) * 100, 1) : 0;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_rencana'  => $total_rencana,
                    'total_done'     => $total_done,
                    'total_pending'  => $total_pending,
                    'percentage'     => $percentage,
                    'total_phone'    => (int)($summary['total_phone'] ?? 0),
                    'total_wa'       => (int)($summary['total_wa'] ?? 0),
                    'total_email'    => (int)($summary['total_email'] ?? 0),
                    'total_ketemu'   => (int)($summary['total_ketemu'] ?? 0),
                    'total_lainnya'  => (int)($summary['total_lainnya'] ?? 0),
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // 2. LIST WORK PLANS (Data Table)
    // -------------------------------------------------------------
    case 'list_plans':
        try {
            $where = buildWhereClause($conn, $user_role, $user_id, $_GET);
            
            $sql = "
                SELECT 
                    wp.*,
                    s.nama_lengkap AS sales_name,
                    s.email AS sales_email,
                    v.nama_lengkap AS verifier_name
                FROM sales_work_plans wp
                LEFT JOIN sales s ON wp.sales_id = s.id
                LEFT JOIN sales v ON wp.verified_by = v.id
                WHERE {$where}
                ORDER BY wp.tanggal DESC, wp.id DESC
            ";
            
            $res = $conn->query($sql);
            $rows = [];
            
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $r['minggu_ke'] = getWeekOfMonth($r['tanggal']);
                    $r['tgl_formatted'] = !empty($r['tanggal']) ? date('d/m/Y', strtotime($r['tanggal'])) : '-';
                    $r['is_done'] = (int)$r['is_done'];
                    $r['can_edit'] = ($is_admin || (int)$r['sales_id'] === $user_id);
                    $r['can_verify'] = $is_admin;
                    $rows[] = $r;
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $rows,
                'total' => count($rows),
                'is_admin' => $is_admin
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // 3. ADD SINGLE WORK PLAN
    // -------------------------------------------------------------
    case 'add_plan':
        try {
            $target_sales_id = $is_admin && !empty($_POST['sales_id']) ? intval($_POST['sales_id']) : $user_id;
            $tanggal         = trim($_POST['tanggal'] ?? date('Y-m-d'));
            $nama_customer   = trim($_POST['nama_customer'] ?? '');
            $customer_id     = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
            $aktivitas       = trim($_POST['aktivitas'] ?? '');
            $kontak_customer = trim($_POST['kontak_customer'] ?? '');
            $email_customer  = trim($_POST['email_customer'] ?? '');
            $metode_fu       = trim($_POST['metode_fu'] ?? 'Text Whatsapp');
            $hasil_fu        = trim($_POST['hasil_fu'] ?? '');
            
            if (empty($nama_customer)) {
                echo json_encode(['success' => false, 'message' => 'Nama Customer wajib diisi.']);
                exit;
            }
            if (empty($tanggal)) {
                echo json_encode(['success' => false, 'message' => 'Tanggal rencana kerja wajib diisi.']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO sales_work_plans 
                (sales_id, tanggal, nama_customer, customer_id, aktivitas, kontak_customer, email_customer, metode_fu, hasil_fu) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ississsss",
                $target_sales_id,
                $tanggal,
                $nama_customer,
                $customer_id,
                $aktivitas,
                $kontak_customer,
                $email_customer,
                $metode_fu,
                $hasil_fu
            );
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Rencana kerja berhasil ditambahkan.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data: ' . $stmt->error]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // 4. BATCH ADD WORK PLANS (Multi-row input like Spreadsheet)
    // -------------------------------------------------------------
    case 'batch_add_plan':
        $conn->begin_transaction();
        try {
            $target_sales_id = $is_admin && !empty($_POST['sales_id']) ? intval($_POST['sales_id']) : $user_id;
            $rows_json = $_POST['rows'] ?? '[]';
            $rows = json_decode($rows_json, true);
            
            if (!is_array($rows) || empty($rows)) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Tidak ada baris data yang dikirim.']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO sales_work_plans 
                (sales_id, tanggal, nama_customer, customer_id, aktivitas, kontak_customer, email_customer, metode_fu, hasil_fu) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $inserted_count = 0;
            foreach ($rows as $row) {
                $nama_customer = trim($row['nama_customer'] ?? '');
                if (empty($nama_customer)) continue; // Skip blank rows
                
                $tanggal         = !empty($row['tanggal']) ? trim($row['tanggal']) : date('Y-m-d');
                $customer_id     = !empty($row['customer_id']) ? intval($row['customer_id']) : null;
                $aktivitas       = trim($row['aktivitas'] ?? '');
                $kontak_customer = trim($row['kontak_customer'] ?? '');
                $email_customer  = trim($row['email_customer'] ?? '');
                $metode_fu       = !empty($row['metode_fu']) ? trim($row['metode_fu']) : 'Text Whatsapp';
                $hasil_fu        = trim($row['hasil_fu'] ?? '');
                
                $stmt->bind_param(
                    "ississsss",
                    $target_sales_id,
                    $tanggal,
                    $nama_customer,
                    $customer_id,
                    $aktivitas,
                    $kontak_customer,
                    $email_customer,
                    $metode_fu,
                    $hasil_fu
                );
                $stmt->execute();
                $inserted_count++;
            }
            
            if ($inserted_count === 0) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Pastikan minimal 1 baris diisi nama customernya.']);
                exit;
            }
            
            $conn->commit();
            echo json_encode([
                'success' => true,
                'count' => $inserted_count,
                'message' => "Berhasil menyimpan {$inserted_count} baris rencana kerja."
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // 5. GET PLAN DETAILS (For Edit Modal)
    // -------------------------------------------------------------
    case 'get_plan':
        try {
            $id = intval($_GET['id'] ?? 0);
            $stmt = $conn->prepare("
                SELECT wp.*, s.nama_lengkap AS sales_name 
                FROM sales_work_plans wp
                LEFT JOIN sales s ON wp.sales_id = s.id
                WHERE wp.id = ? AND wp.deleted_at IS NULL
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
                exit;
            }
            
            // Permissions
            if (!$is_admin && (int)$data['sales_id'] !== $user_id) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin untuk melihat data ini.']);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // 6. UPDATE WORK PLAN
    // -------------------------------------------------------------
    case 'update_plan':
        try {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID data tidak valid.']);
                exit;
            }
            
            // Check existing
            $chk = $conn->query("SELECT sales_id FROM sales_work_plans WHERE id = {$id} AND deleted_at IS NULL");
            $curr = $chk ? $chk->fetch_assoc() : null;
            if (!$curr) {
                echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
                exit;
            }
            
            if (!$is_admin && (int)$curr['sales_id'] !== $user_id) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda hanya dapat mengedit rencana kerja milik Anda.']);
                exit;
            }
            
            $target_sales_id = $is_admin && !empty($_POST['sales_id']) ? intval($_POST['sales_id']) : (int)$curr['sales_id'];
            $tanggal         = trim($_POST['tanggal'] ?? date('Y-m-d'));
            $nama_customer   = trim($_POST['nama_customer'] ?? '');
            $customer_id     = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
            $aktivitas       = trim($_POST['aktivitas'] ?? '');
            $kontak_customer = trim($_POST['kontak_customer'] ?? '');
            $email_customer  = trim($_POST['email_customer'] ?? '');
            $metode_fu       = trim($_POST['metode_fu'] ?? 'Text Whatsapp');
            $hasil_fu        = trim($_POST['hasil_fu'] ?? '');
            
            if (empty($nama_customer)) {
                echo json_encode(['success' => false, 'message' => 'Nama Customer wajib diisi.']);
                exit;
            }
            
            $stmt = $conn->prepare("
                UPDATE sales_work_plans 
                SET sales_id=?, tanggal=?, nama_customer=?, customer_id=?, aktivitas=?, kontak_customer=?, email_customer=?, metode_fu=?, hasil_fu=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "ississsssi",
                $target_sales_id,
                $tanggal,
                $nama_customer,
                $customer_id,
                $aktivitas,
                $kontak_customer,
                $email_customer,
                $metode_fu,
                $hasil_fu,
                $id
            );
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Data rencana kerja berhasil diperbarui.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data: ' . $stmt->error]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // 7. DELETE WORK PLAN (Soft Delete)
    // -------------------------------------------------------------
    case 'delete_plan':
        try {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID data tidak valid.']);
                exit;
            }
            
            $chk = $conn->query("SELECT sales_id FROM sales_work_plans WHERE id = {$id} AND deleted_at IS NULL");
            $curr = $chk ? $chk->fetch_assoc() : null;
            if (!$curr) {
                echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
                exit;
            }
            
            if (!$is_admin && (int)$curr['sales_id'] !== $user_id) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda hanya dapat menghapus rencana kerja milik Anda.']);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE sales_work_plans SET deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Rencana kerja berhasil dihapus.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . $stmt->error]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // 8. TOGGLE DONE (ADMIN ONLY VERIFICATION)
    // -------------------------------------------------------------
    case 'toggle_done':
        try {
            // STRICT ROLE ACCESS CONTROL
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Akses Ditolak: Hanya Admin (Superadmin & Adminsales) yang memiliki hak untuk memverifikasi/mencentang status Sudah Dilakukan.'
                ]);
                exit;
            }
            
            $id      = intval($_POST['id'] ?? 0);
            $is_done = !empty($_POST['is_done']) && $_POST['is_done'] == '1' ? 1 : 0;
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID data tidak valid.']);
                exit;
            }
            
            if ($is_done === 1) {
                $stmt = $conn->prepare("
                    UPDATE sales_work_plans 
                    SET is_done = 1, verified_by = ?, verified_at = NOW() 
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->bind_param("ii", $user_id, $id);
            } else {
                $stmt = $conn->prepare("
                    UPDATE sales_work_plans 
                    SET is_done = 0, verified_by = NULL, verified_at = NULL 
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->bind_param("i", $id);
            }
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'is_done' => $is_done,
                    'verifier_name' => $is_done ? $user_name : null,
                    'verified_at' => $is_done ? date('d/m/Y H:i') : null,
                    'message' => $is_done ? 'Status diverifikasi: Sudah Dilakukan.' : 'Status diubah: Belum Dilakukan.'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengubah status: ' . $stmt->error]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // -------------------------------------------------------------
    // 9. SEARCH CUSTOMER AUTOCOMPLETE (SELECT2 COMPATIBLE - UNIFIED SEARCH)
    // -------------------------------------------------------------
    case 'search_customer':
        try {
            $q = trim($_GET['q'] ?? $_GET['term'] ?? '');
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(100, intval($_GET['limit'] ?? 30)));
            $offset = ($page - 1) * $limit;
            
            // Scope target sales if provided or if sales role
            $target_sales_id = 0;
            if ($user_role === 'sales') {
                $target_sales_id = $user_id;
            } elseif (!empty($_GET['sales_id'])) {
                $target_sales_id = intval($_GET['sales_id']);
            }

            $raw_items = [];
            $seen_keys = [];

            // -------------------------------------------------------------
            // A. Search in sales_customer (Master Toko/Dealer from Canvas DB & Main DB)
            // -------------------------------------------------------------
            // 1. Establish connection to Canvas DB (teknisi_api_root) where customer.php operates
            $sc_databases = [];
            
            mysqli_report(MYSQLI_REPORT_OFF);
            $host = 'localhost';
            $cCanvas = @new mysqli($host, 'teknisi_api_root', 'OffOff@18', 'teknisi_api_root');
            if ($cCanvas && $cCanvas->connect_error) {
                $cCanvas = @new mysqli($host, 'teknisi_api_root', 'WagyuA531052002.', 'teknisi_api_root');
            }
            if ($cCanvas && $cCanvas->connect_error) {
                $cCanvas = @new mysqli($host, 'u836263092_jadwaltest', 'Eddie@1819', 'u836263092_jadwalTest');
            }
            if ($cCanvas && $cCanvas->connect_error && file_exists(__DIR__ . '/modul-aplikasi-sales/conn.php')) {
                $cCanvas = (function() {
                    mysqli_report(MYSQLI_REPORT_OFF);
                    include __DIR__ . '/modul-aplikasi-sales/conn.php';
                    return (isset($conn) && $conn && !$conn->connect_error) ? $conn : null;
                })();
            }

            if ($cCanvas && !$cCanvas->connect_error) {
                $cCanvas->set_charset("utf8mb4");
                $sc_databases[] = $cCanvas;
            }

            // 2. Also query main CRM DB ($conn) if different from Canvas DB
            if ($conn && !$conn->connect_error) {
                $is_same = false;
                foreach ($sc_databases as $existingDb) {
                    if (isset($existingDb->thread_id, $conn->thread_id) && $existingDb->thread_id === $conn->thread_id) {
                        $is_same = true;
                        break;
                    }
                }
                if (!$is_same) {
                    $sc_databases[] = $conn;
                }
            }

            // 3. Query sales_customer across all available databases
            foreach ($sc_databases as $db) {
                $where_sc = ["deleted_at IS NULL"];
                if (!empty($q)) {
                    $q_esc = $db->real_escape_string($q);
                    $where_sc[] = "(
                        nama LIKE '%{$q_esc}%'
                        OR kode_customer LIKE '%{$q_esc}%'
                        OR telp_pribadi LIKE '%{$q_esc}%'
                        OR kota LIKE '%{$q_esc}%'
                        OR alamat LIKE '%{$q_esc}%'
                        OR email LIKE '%{$q_esc}%'
                    )";
                }
                $where_sc_sql = implode(' AND ', $where_sc);
                $sql_sc = "
                    SELECT 
                        id, 
                        kode_customer, 
                        nama, 
                        kategori, 
                        kota, 
                        telp_pribadi, 
                        alamat, 
                        email 
                    FROM sales_customer 
                    WHERE {$where_sc_sql} 
                    ORDER BY nama ASC 
                    LIMIT 150
                ";
                $res_sc = $db->query($sql_sc);
                if ($res_sc) {
                    while ($r = $res_sc->fetch_assoc()) {
                        $norm_key = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $r['nama']));
                        if ($norm_key !== '' && isset($seen_keys[$norm_key])) {
                            continue;
                        }

                        $phone = !empty($r['telp_pribadi']) ? trim($r['telp_pribadi']) : '';
                        $kota  = !empty($r['kota']) ? trim($r['kota']) : '';
                        $kode  = !empty($r['kode_customer']) ? trim($r['kode_customer']) : '';
                        $kat   = !empty($r['kategori']) ? trim($r['kategori']) : '';
                        
                        $extra_info = [];
                        if ($kode) $extra_info[] = $kode;
                        if ($kat) $extra_info[] = $kat;
                        if ($kota) $extra_info[] = $kota;
                        if ($phone) $extra_info[] = $phone;
                        $info_str = !empty($extra_info) ? ' (' . implode(' • ', $extra_info) . ')' : '';

                        $item = [
                            'id' => (int)$r['id'],
                            'text' => $r['nama'] . $info_str,
                            'nama_toko' => $r['nama'],
                            'phone' => $phone,
                            'kota' => $kota,
                            'pic' => '',
                            'kategori' => $kat,
                            'kode_customer' => $kode,
                            'alamat' => $r['alamat'] ?? '',
                            'email' => $r['email'] ?? '',
                            'source' => 'sales_customer',
                            'is_own_sales' => 1
                        ];

                        $raw_items[] = $item;
                        if ($norm_key !== '') {
                            $seen_keys[$norm_key] = true;
                        }
                    }
                }
            }

            // -------------------------------------------------------------
            // B. Search in customers (Leads & Follow-up database)
            // -------------------------------------------------------------
            $where_c = ["c.deleted_at IS NULL"];
            if (!empty($q)) {
                $q_esc = $conn->real_escape_string($q);
                $where_c[] = "(
                    c.nama_toko LIKE '%{$q_esc}%'
                    OR c.id IN (SELECT cp2.customer_id FROM customer_pics cp2 WHERE (cp2.tlp_pic LIKE '%{$q_esc}%' OR cp2.nama_pic LIKE '%{$q_esc}%') AND cp2.deleted_at IS NULL)
                    OR c.id IN (SELECT ca2.customer_id FROM customer_addresses ca2 WHERE (ca2.kota LIKE '%{$q_esc}%' OR ca2.alamat LIKE '%{$q_esc}%') AND ca2.deleted_at IS NULL)
                )";
            }
            $where_c_sql = implode(' AND ', $where_c);
            
            // Order: prioritize logged-in sales's own leads if target_sales_id > 0
            $order_c = "c.nama_toko ASC";
            if ($target_sales_id > 0) {
                $order_c = "CASE WHEN c.sales_id = {$target_sales_id} THEN 0 ELSE 1 END, c.nama_toko ASC";
            }

            $sql_c = "
                SELECT 
                    c.id, 
                    c.sales_id,
                    c.nama_toko, 
                    c.kategori,
                    (SELECT cp.tlp_pic FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL AND cp.tlp_pic IS NOT NULL AND cp.tlp_pic != '' LIMIT 1) as tlp_pic,
                    (SELECT cp.nama_pic FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL AND cp.nama_pic != 'unknown' LIMIT 1) as nama_pic,
                    (SELECT ca.kota FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.deleted_at IS NULL AND ca.kota IS NOT NULL LIMIT 1) as kota,
                    (SELECT ca.alamat FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.deleted_at IS NULL LIMIT 1) as alamat
                FROM customers c
                WHERE {$where_c_sql}
                ORDER BY {$order_c}
                LIMIT 150
            ";
            $res_c = $conn->query($sql_c);
            if ($res_c) {
                while ($r = $res_c->fetch_assoc()) {
                    $norm_key = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $r['nama_toko']));
                    
                    // Deduplicate if already present from sales_customer
                    if ($norm_key !== '' && isset($seen_keys[$norm_key])) {
                        continue;
                    }

                    $phone = !empty($r['tlp_pic']) ? trim($r['tlp_pic']) : '';
                    $kota  = !empty($r['kota']) ? trim($r['kota']) : '';
                    $pic   = !empty($r['nama_pic']) && $r['nama_pic'] !== 'unknown' ? trim($r['nama_pic']) : '';
                    $kat   = !empty($r['kategori']) ? trim($r['kategori']) : '';
                    
                    $extra_info = [];
                    if ($kat) $extra_info[] = $kat;
                    if ($kota) $extra_info[] = $kota;
                    if ($phone) $extra_info[] = $phone;
                    if ($pic) $extra_info[] = "PIC: " . $pic;
                    $info_str = !empty($extra_info) ? ' (' . implode(' • ', $extra_info) . ')' : '';

                    $is_own = ($target_sales_id > 0 && (int)$r['sales_id'] === $target_sales_id) ? 1 : 0;

                    $item = [
                        'id' => (int)$r['id'],
                        'text' => $r['nama_toko'] . $info_str,
                        'nama_toko' => $r['nama_toko'],
                        'phone' => $phone,
                        'kota' => $kota,
                        'pic' => $pic,
                        'kategori' => $kat,
                        'kode_customer' => '',
                        'alamat' => $r['alamat'] ?? '',
                        'email' => '',
                        'source' => 'customers',
                        'is_own_sales' => $is_own
                    ];

                    $raw_items[] = $item;
                    if ($norm_key !== '') {
                        $seen_keys[$norm_key] = true;
                    }
                }
            }

            // -------------------------------------------------------------
            // C. Smart Scoring & Sorting
            // -------------------------------------------------------------
            if (!empty($q)) {
                $q_lower = strtolower($q);
                usort($raw_items, function($a, $b) use ($q_lower) {
                    $scoreA = 0;
                    $scoreB = 0;
                    
                    $nameA = strtolower(trim($a['nama_toko']));
                    $nameB = strtolower(trim($b['nama_toko']));
                    $kodeA = strtolower(trim($a['kode_customer'] ?? ''));
                    $kodeB = strtolower(trim($b['kode_customer'] ?? ''));

                    // Exact match
                    if ($nameA === $q_lower) $scoreA += 100;
                    if ($nameB === $q_lower) $scoreB += 100;
                    
                    // Code match
                    if ($kodeA !== '' && $kodeA === $q_lower) $scoreA += 90;
                    if ($kodeB !== '' && $kodeB === $q_lower) $scoreB += 90;

                    // Prefix match
                    if (strpos($nameA, $q_lower) === 0) $scoreA += 80;
                    if (strpos($nameB, $q_lower) === 0) $scoreB += 80;

                    // Word boundary match
                    if ($scoreA < 80 && preg_match('/\b' . preg_quote($q_lower, '/') . '\b/i', $nameA)) $scoreA += 70;
                    if ($scoreB < 80 && preg_match('/\b' . preg_quote($q_lower, '/') . '\b/i', $nameB)) $scoreB += 70;

                    // Substring match
                    if ($scoreA < 70 && strpos($nameA, $q_lower) !== false) $scoreA += 60;
                    if ($scoreB < 70 && strpos($nameB, $q_lower) !== false) $scoreB += 60;

                    // Has customer code bonus (master verified store)
                    if (!empty($a['kode_customer'])) $scoreA += 5;
                    if (!empty($b['kode_customer'])) $scoreB += 5;

                    // Sales's own customer bonus
                    if (!empty($a['is_own_sales'])) $scoreA += 2;
                    if (!empty($b['is_own_sales'])) $scoreB += 2;

                    if ($scoreA !== $scoreB) {
                        return $scoreB - $scoreA;
                    }
                    
                    // Shorter store name first
                    $lenDiff = strlen($a['nama_toko']) - strlen($b['nama_toko']);
                    if ($lenDiff !== 0) {
                        return $lenDiff;
                    }

                    return strcasecmp($a['nama_toko'], $b['nama_toko']);
                });
            }

            $total_records = count($raw_items);
            $results = array_slice($raw_items, $offset, $limit);

            echo json_encode([
                'success' => true,
                'results' => $results,
                'pagination' => [
                    'more' => ($offset + count($results)) < $total_records
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
        break;
}

