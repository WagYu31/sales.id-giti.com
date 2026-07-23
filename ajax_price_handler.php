<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/db.php';

// Bersihkan output buffer jika ada spasi/noise dari db.php agar JSON tidak rusak
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action == 'get_prices') {
    $search = $_GET['search'] ?? '';
    $limit = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? (int)$_GET['limit'] : 25;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $where .= " AND (category LIKE ? OR type LIKE ? OR description LIKE ?)";
        $s = "%$search%";
        $params = [$s, $s, $s];
        $types = "sss";
    }

    $stmt_c = $conn->prepare("SELECT COUNT(*) as total FROM product_prices $where");
    if (!empty($types)) { $stmt_c->bind_param($types, ...$params); }
    $stmt_c->execute();
    $total_rows = $stmt_c->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    $sql = "SELECT * FROM product_prices $where ORDER BY category ASC, type ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $final_types = $types . "ii";
    $final_params = array_merge($params, [$offset, $limit]);
    $stmt->bind_param($final_types, ...$final_params);
    $stmt->execute();
    $result = $stmt->get_result();

    $conf_res = $conn->query("SELECT * FROM settings");
    $conf = [];
    while ($r = $conf_res->fetch_assoc()) { 
        $conf[$r['setting_key']] = (float)$r['setting_value']; 
    }

    $d_disc = $conf['dealer_discount'] ?? 0;
    $m_disc = $conf['master_dealer_discount'] ?? 0;

    $html = '';
    if ($result->num_rows > 0) {
        while ($p = $result->fetch_assoc()) {
            $msrp = (float)$p['msrp'];
            $p_dealer = $msrp * (1 - ($d_disc / 100));
            $p_master = $p_dealer * (1 - ($m_disc / 100));
            
            $html .= "<tr>
                        <td class='small fw-bold text-uppercase'>".htmlspecialchars($p['category'])."</td>
                        <td><div class='fw-bold'>".htmlspecialchars($p['type'])."</div><div class='small text-muted'>".nl2br(htmlspecialchars($p['description']))."</div></td>
                        <td class='text-end'>Rp ".number_format($msrp, 0, ',', '.')."</td>
                        <td class='text-end fw-bold text-primary'>Rp ".number_format($p_dealer, 0, ',', '.')."</td>
                        <td class='text-end fw-bold text-success'>Rp ".number_format($p_master, 0, ',', '.')."</td>
                        <td class='text-center'>
                            <div class='btn-group btn-group-sm'>
                                <button class='btn btn-outline-warning btn-edit' data-id='{$p['id']}'><i class='bi bi-pencil'></i></button>
                                <button class='btn btn-outline-danger btn-delete' data-id='{$p['id']}'><i class='bi bi-trash'></i></button>
                            </div>
                        </td>
                      </tr>";
        }
    } else {
        $html = '<tr><td colspan="6" class="text-center p-4">Tidak ada data produk.</td></tr>';
    }

    echo json_encode([
        'html' => $html,
        'pagination' => ['total_pages' => (int)$total_pages, 'current_page' => (int)$page]
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