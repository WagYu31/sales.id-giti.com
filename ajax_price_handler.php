<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/db.php';

// Bersihkan output buffer jika ada spasi/noise dari db.php agar JSON tidak rusak
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action == 'get_prices') {
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $price_range = trim($_GET['price_range'] ?? '');
    $min_price = (isset($_GET['min_price']) && is_numeric($_GET['min_price']) && $_GET['min_price'] !== '') ? (float)$_GET['min_price'] : null;
    $max_price = (isset($_GET['max_price']) && is_numeric($_GET['max_price']) && $_GET['max_price'] !== '') ? (float)$_GET['max_price'] : null;
    $sort = $_GET['sort'] ?? 'default';
    $limit = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? (int)$_GET['limit'] : 25;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Presets for price ranges
    if (!empty($price_range)) {
        if ($price_range === 'under_50k') {
            $max_price = 50000;
        } elseif ($price_range === '50k_150k') {
            $min_price = 50000;
            $max_price = 150000;
        } elseif ($price_range === '150k_500k') {
            $min_price = 150000;
            $max_price = 500000;
        } elseif ($price_range === 'above_500k') {
            $min_price = 500000;
        }
    }

    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $where .= " AND (category LIKE ? OR type LIKE ? OR description LIKE ? OR item_code LIKE ?)";
        $s = "%$search%";
        $params[] = $s;
        $params[] = $s;
        $params[] = $s;
        $params[] = $s;
        $types .= "ssss";
    }

    if (!empty($category)) {
        $where .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }

    if ($min_price !== null) {
        $where .= " AND msrp >= ?";
        $params[] = $min_price;
        $types .= "d";
    }

    if ($max_price !== null) {
        $where .= " AND msrp <= ?";
        $params[] = $max_price;
        $types .= "d";
    }

    // Dynamic sorting
    $order_by = "category ASC, type ASC";
    if ($sort === 'price_asc') {
        $order_by = "msrp ASC, type ASC";
    } elseif ($sort === 'price_desc') {
        $order_by = "msrp DESC, type ASC";
    } elseif ($sort === 'type_asc') {
        $order_by = "type ASC";
    } elseif ($sort === 'type_desc') {
        $order_by = "type DESC";
    } elseif ($sort === 'newest') {
        $order_by = "id DESC";
    }

    $stmt_c = $conn->prepare("SELECT COUNT(*) as total FROM product_prices $where");
    if (!empty($types)) { $stmt_c->bind_param($types, ...$params); }
    $stmt_c->execute();
    $total_rows = $stmt_c->get_result()->fetch_assoc()['total'];
    $total_pages = ($limit > 0) ? ceil($total_rows / $limit) : 1;

    if ($limit > 0) {
        $sql = "SELECT * FROM product_prices $where ORDER BY $order_by LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $final_types = $types . "ii";
        $final_params = array_merge($params, [$offset, $limit]);
        $stmt->bind_param($final_types, ...$final_params);
    } else {
        $sql = "SELECT * FROM product_prices $where ORDER BY $order_by";
        $stmt = $conn->prepare($sql);
        if (!empty($types)) { $stmt->bind_param($types, ...$params); }
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $conf = [];
    $conf_res = $conn->query("SELECT * FROM settings");
    if ($conf_res) {
        while ($r = $conf_res->fetch_assoc()) { 
            $conf[$r['setting_key']] = (float)$r['setting_value']; 
        }
    }
    $d_disc = $conf['dealer_discount'] ?? 20;
    $m_disc = $conf['master_dealer_discount'] ?? 35;

    // Global Statistics & Category Breakdown
    $stat_row = $conn->query("SELECT COUNT(*) as total_count, COUNT(DISTINCT category) as cat_count, MIN(CASE WHEN msrp > 0 THEN msrp ELSE NULL END) as min_msrp, MAX(msrp) as max_msrp FROM product_prices")->fetch_assoc();

    $cat_res = $conn->query("SELECT category, COUNT(*) as cnt FROM product_prices GROUP BY category ORDER BY cnt DESC, category ASC");
    $categories_list = [];
    if ($cat_res) {
        while ($c = $cat_res->fetch_assoc()) {
            $categories_list[] = [
                'name' => $c['category'],
                'count' => (int)$c['cnt']
            ];
        }
    }

    $html = '';
    if ($result->num_rows > 0) {
        while ($p = $result->fetch_assoc()) {
            $msrp = (float)$p['msrp'];
            $p_dealer = $msrp * (1 - ($d_disc / 100));
            $p_master = $p_dealer * (1 - ($m_disc / 100));
            
            $codeUnitBadge = !empty($p['item_code']) 
                ? "<div class='text-muted font-monospace mt-1' style='font-size: 11.5px;'><span class='badge bg-light text-secondary border me-1' style='font-size:10px;'>".htmlspecialchars($p['item_code'])."</span>Satuan: <span class='text-dark fw-semibold'>".htmlspecialchars($p['unit'] ?: 'UNIT')."</span></div>" 
                : "";

            $priceDisplay = $msrp > 0 
                ? "Rp " . number_format($msrp, 0, ',', '.') 
                : "<span class='text-muted small fw-normal' style='font-size:12px;'>Hubungi Sales</span>";

            $html .= "<tr class='product-row align-middle'>
                        <td style='vertical-align: middle;'>
                            <span class='badge' style='background: rgba(37,99,235,0.08); color: #1d4ed8; border: 1px solid rgba(37,99,235,0.25); font-size: 11px; padding: 5px 9px; font-weight: 700; border-radius: 6px; letter-spacing: 0.02em;'>
                                <i class='bi bi-tag-fill me-1 opacity-75'></i>".htmlspecialchars($p['category'])."
                            </span>
                        </td>
                        <td style='vertical-align: middle;'>
                            <div class='fw-bold text-dark' style='font-size: 14px;'>".htmlspecialchars($p['type'])."</div>
                            {$codeUnitBadge}
                        </td>
                        <td class='text-end' style='vertical-align: middle;'>
                            <div class='font-monospace fw-bold text-dark' style='font-size: 14px;'>{$priceDisplay}</div>
                        </td>
                        <td class='text-center' style='vertical-align: middle;'>
                            <div class='btn-group btn-group-sm' style='box-shadow: 0 1px 3px rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden;'>
                                <button type='button' class='btn btn-outline-info btn-desc-info' 
                                        data-id='{$p['id']}' 
                                        data-type='".htmlspecialchars($p['type'], ENT_QUOTES)."' 
                                        data-category='".htmlspecialchars($p['category'], ENT_QUOTES)."' 
                                        data-price='Rp ".number_format($msrp, 0, ',', '.')."' 
                                        data-desc='".htmlspecialchars($p['description'] ?? '', ENT_QUOTES)."' 
                                        title='Lihat Deskripsi Spesifikasi' style='padding: 5px 9px;'>
                                    <i class='bi bi-info-circle-fill'></i>
                                </button>
                                <button type='button' class='btn btn-outline-warning btn-edit' data-id='{$p['id']}' title='Edit Produk' style='padding: 5px 9px;'><i class='bi bi-pencil-square'></i></button>
                                <button type='button' class='btn btn-outline-danger btn-delete' data-id='{$p['id']}' title='Hapus Produk' style='padding: 5px 9px;'><i class='bi bi-trash3-fill'></i></button>
                            </div>
                        </td>
                      </tr>";
        }
    } else {
        $html = "<tr>
                    <td colspan='4' class='text-center py-5'>
                        <div class='d-inline-flex p-3 rounded-circle mb-3' style='background: #f1f5f9; color: #64748b;'>
                            <i class='bi bi-search fs-2'></i>
                        </div>
                        <h6 class='fw-bold text-dark mb-1'>Tidak ada produk yang cocok</h6>
                        <p class='text-muted small mb-3'>Coba ubah kata kunci pencarian atau sesuaikan filter untuk menemukan produk yang dicari.</p>
                        <button type='button' class='btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold' onclick='resetAllFilters()'>
                            <i class='bi bi-arrow-counterclockwise me-1'></i> Reset Filter
                        </button>
                    </td>
                 </tr>";
    }

    echo json_encode([
        'success' => true,
        'html' => $html,
        'pagination' => [
            'total_rows' => (int)$total_rows,
            'total_pages' => (int)$total_pages,
            'current_page' => (int)$page,
            'limit' => (int)$limit
        ],
        'categories' => $categories_list,
        'stats' => [
            'total_products' => (int)($stat_row['total_count'] ?? 0),
            'total_categories' => (int)($stat_row['cat_count'] ?? 0),
            'min_msrp' => (float)($stat_row['min_msrp'] ?? 0),
            'max_msrp' => (float)($stat_row['max_msrp'] ?? 0),
            'dealer_discount' => $d_disc,
            'master_dealer_discount' => $m_disc
        ]
    ]);
    exit;
}

if ($action == 'get_product_details') {
    $stmt = $conn->prepare("SELECT * FROM product_prices WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    echo json_encode(['success' => true, 'data' => $stmt->get_result()->fetch_assoc()]);
    exit;
}

if ($action == 'add_product') {
    $stmt = $conn->prepare("INSERT INTO product_prices (category, type, description, msrp) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssd", $_POST['category'], $_POST['type'], $_POST['description'], $_POST['msrp']);
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

if ($action == 'update_product') {
    $stmt = $conn->prepare("UPDATE product_prices SET category=?, type=?, description=?, msrp=? WHERE id=?");
    $stmt->bind_param("sssdi", $_POST['category'], $_POST['type'], $_POST['description'], $_POST['msrp'], $_POST['product_id']);
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

if ($action == 'delete_product') {
    $stmt = $conn->prepare("DELETE FROM product_prices WHERE id = ?");
    $stmt->bind_param("i", $_POST['id']);
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

if ($action == 'get_settings') {
    $res = $conn->query("SELECT * FROM settings");
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[$r['setting_key']] = $r['setting_value'];
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action == 'update_settings') {
    $dealer = $_POST['dealer_discount'];
    $master = $_POST['master_dealer_discount'];

    $conn->query("UPDATE settings SET setting_value = '$dealer' WHERE setting_key = 'dealer_discount'");
    $conn->query("UPDATE settings SET setting_value = '$master' WHERE setting_key = 'master_dealer_discount'");

    echo json_encode(['success' => true]);
    exit;
}
?>