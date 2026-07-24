<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

header('Content-Type: application/json');

// Opsi 1: Otomatis Bersihkan Semua Duplikat (1-Click Auto Clean)
if (isset($_POST['auto_clean_all']) && $_POST['auto_clean_all'] == '1') {
    $conn->begin_transaction();
    try {
        $duplicate_phones_query = "SELECT tlp_pic FROM customer_pics WHERE deleted_at IS NULL AND tlp_pic != '' GROUP BY tlp_pic HAVING COUNT(id) > 1";
        $result_duplicates = $conn->query($duplicate_phones_query);
        
        $cleaned_groups = 0;
        $deleted_total = 0;

        if ($result_duplicates) {
            while ($row = $result_duplicates->fetch_assoc()) {
                $phone = $row['tlp_pic'];
                $sql = "
                    SELECT c.id, COUNT(DISTINCT fu.id) AS fu_count
                    FROM customers c
                    JOIN customer_pics cp ON c.id = cp.customer_id
                    LEFT JOIN follow_ups fu ON c.id = fu.customer_id AND fu.deleted_at IS NULL
                    WHERE cp.tlp_pic = ? AND c.deleted_at IS NULL AND cp.deleted_at IS NULL
                    GROUP BY c.id
                    ORDER BY fu_count DESC, c.id ASC";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('s', $phone);
                $stmt->execute();
                $cust_res = $stmt->get_result();
                $cust_rows = $cust_res->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                if (count($cust_rows) > 1) {
                    $keep_id = $cust_rows[0]['id'];
                    $to_delete_ids = [];
                    for ($i = 1; $i < count($cust_rows); $i++) {
                        $to_delete_ids[] = $cust_rows[$i]['id'];
                    }

                    if (!empty($to_delete_ids)) {
                        $placeholders = implode(',', array_fill(0, count($to_delete_ids), '?'));
                        $types = str_repeat('i', count($to_delete_ids));
                        $stmt_del = $conn->prepare("UPDATE customers SET deleted_at = NOW() WHERE id IN ($placeholders)");
                        $stmt_del->bind_param($types, ...$to_delete_ids);
                        $stmt_del->execute();
                        $deleted_total += $stmt_del->affected_rows;
                        $stmt_del->close();
                        $cleaned_groups++;
                    }
                }
            }
        }

        $conn->commit();
        // Clear caches
        unset($_SESSION['notif_cache_time']);
        unset($_SESSION['cities_cache']);

        echo json_encode([
            'success' => true, 
            'message' => "Berhasil merapikan {$cleaned_groups} grup duplikat ({$deleted_total} data toko duplikat dibersihkan otomatis)."
        ]);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal merapikan otomatis: ' . $e->getMessage()]);
        exit;
    }
}

// Opsi 2: Manual Pertahankan 1 Data spesifik
$keep_id = $_POST['keep_id'] ?? 0;
$delete_ids_str = $_POST['delete_ids'] ?? '';

if (empty($keep_id) || empty($delete_ids_str)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$delete_ids = explode(',', $delete_ids_str);
$placeholders = implode(',', array_fill(0, count($delete_ids), '?'));
$types = str_repeat('i', count($delete_ids));

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE customers SET deleted_at = NOW() WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$delete_ids);
    
    if (!$stmt->execute()) {
        throw new Exception('Gagal menghapus data customer duplikat.');
    }
    
    $conn->commit();
    // Clear caches
    unset($_SESSION['notif_cache_time']);
    unset($_SESSION['cities_cache']);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>