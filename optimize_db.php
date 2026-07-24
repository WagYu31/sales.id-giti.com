<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

$queries = [
    "CREATE INDEX idx_cust_del_tgl ON customers(deleted_at, tgl_input)",
    "CREATE INDEX idx_cust_sales ON customers(deleted_at, sales_id)",
    "CREATE INDEX idx_cust_status ON customers(deleted_at, status_fu)",
    "CREATE INDEX idx_cust_kandidat ON customers(deleted_at, kandidat)",
    "CREATE INDEX idx_pics_tlp ON customer_pics(deleted_at, tlp_pic)",
    "CREATE INDEX idx_pics_cust ON customer_pics(deleted_at, customer_id)",
    "CREATE INDEX idx_addr_kota ON customer_addresses(deleted_at, kota)",
    "CREATE INDEX idx_addr_cust ON customer_addresses(deleted_at, customer_id)",
    "CREATE INDEX idx_fu_cust ON follow_ups(deleted_at, customer_id)"
];

$results = [];
foreach ($queries as $q) {
    try {
        if ($conn->query($q)) {
            $results[] = "SUCCESS: " . $q;
        } else {
            $results[] = "EXISTS/SKIP: " . $conn->error;
        }
    } catch (Exception $e) {
        $results[] = "EXISTS/SKIP: " . $e->getMessage();
    }
}

// Clear cached sessions
unset($_SESSION['notif_cache_time']);
unset($_SESSION['cities_cache']);
unset($_SESSION['categories_cache']);

echo json_encode([
    'status' => 'success',
    'message' => 'Database Indexes Created & Caches Reset Successfully!',
    'details' => $results
], JSON_PRETTY_PRINT);
