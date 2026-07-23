<?php
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

switch ($action) {
    case 'add_promo':
        $conn->begin_transaction();
        try {
            $title = $_POST['title'];
            $type = $_POST['type'];
            $product_list = $_POST['product_list'];
            $platform = $_POST['platform'];
            $start_date = $_POST['start_date'];
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $daily_budget = $_POST['daily_budget'] ?? 0;

            $stmt = $conn->prepare("INSERT INTO promotions (title, type, product_list, platform, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $title, $type, $product_list, $platform, $start_date, $end_date, $user_id);
            $stmt->execute();
            $promo_id = $conn->insert_id;

            $stmt_budget = $conn->prepare("INSERT INTO promotion_budgets (promotion_id, budget_amount, effective_date, created_by) VALUES (?, ?, ?, ?)");
            $stmt_budget->bind_param("idsi", $promo_id, $daily_budget, $start_date, $user_id);
            $stmt_budget->execute();
            
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update_promo':
        $id = intval($_POST['promo_id'] ?? 0);
        $title = $_POST['title'];
        $type = $_POST['type'];
        $product_list = $_POST['product_list'];
        $platform = $_POST['platform'];
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $stmt = $conn->prepare("UPDATE promotions SET title=?, type=?, product_list=?, platform=?, start_date=?, end_date=? WHERE id=?");
        $stmt->bind_param("ssssssi", $title, $type, $product_list, $platform, $start_date, $end_date, $id);
        echo json_encode(['success' => $stmt->execute()]);
        break;

    case 'get_promo_details':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM promotions WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'data' => $stmt->get_result()->fetch_assoc()]);
        break;

    case 'delete_promo':
        $id = intval($_POST['promo_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE promotions SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo json_encode(['success' => $stmt->execute()]);
        break;

    case 'get_budget_history':
        $id = intval($_GET['promo_id'] ?? 0);
        $result = $conn->query("SELECT * FROM promotion_budgets WHERE promotion_id = $id ORDER BY effective_date DESC");
        echo json_encode(['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'save_budget_entry':
        $promo_id = intval($_POST['promotion_id']);
        $budget_id = intval($_POST['budget_id']);
        $amount = $_POST['budget_amount'];
        $date = $_POST['effective_date'];
        
        if (empty($promo_id) || empty($amount) || empty($date)) {
             echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
             exit;
        }

        if (empty($budget_id)) { // Add new
            $stmt = $conn->prepare("INSERT INTO promotion_budgets (promotion_id, budget_amount, effective_date, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idsi", $promo_id, $amount, $date, $user_id);
        } else { // Update existing
            $stmt = $conn->prepare("UPDATE promotion_budgets SET budget_amount=?, effective_date=? WHERE id=?");
            $stmt->bind_param("dsi", $amount, $date, $budget_id);
        }
        echo json_encode(['success' => $stmt->execute()]);
        break;

    case 'delete_budget_entry':
        $id = intval($_POST['budget_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM promotion_budgets WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo json_encode(['success' => $stmt->execute()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
        break;
}