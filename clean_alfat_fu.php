<?php
/**
 * clean_alfat_fu.php
 * Skrip untuk memeriksa dan membersihkan riwayat follow up customer ALFAT
 * yang tidak sesuai (misal follow up lama milik Edi Suprianto sebelum tanggal input ALFAT).
 *
 * Akses via browser: https://sales.id-giti.com/clean_alfat_fu.php
 * Atau via terminal: php clean_alfat_fu.php
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

// Pastikan login jika di browser
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    if (!isset($_SESSION['user_id'])) {
        die("<div style='font-family:sans-serif;padding:20px;color:red;'>Silakan login terlebih dahulu di <a href='login.php'>sales.id-giti.com</a></div>");
    }
}

// 1. Cari customer ALFAT
$qCust = $conn->query("SELECT c.*, s.nama_lengkap as nama_sales 
                       FROM customers c 
                       LEFT JOIN sales s ON c.sales_id = s.id 
                       WHERE c.nama_toko LIKE '%ALFAT%' AND c.deleted_at IS NULL 
                       ORDER BY c.id DESC");

if (!$qCust || $qCust->num_rows === 0) {
    if ($is_cli) {
        die("Customer ALFAT tidak ditemukan di database.\n");
    }
    die("<div style='font-family:sans-serif;padding:20px;'>Customer ALFAT tidak ditemukan di database.</div>");
}

$customers = $qCust->fetch_all(MYSQLI_ASSOC);

// Jika dijalankan via Terminal CLI
if ($is_cli) {
    echo "=====================================================\n";
    echo "  PEMERIKSAAN & PEMBERSIHAN DATA FOLLOW UP ALFAT\n";
    echo "=====================================================\n\n";
    foreach ($customers as $cust) {
        $cid = (int)$cust['id'];
        echo "Customer: [ID: {$cust['id']}] {$cust['nama_toko']}\n";
        echo "Sales   : {$cust['nama_sales']} (ID: {$cust['sales_id']}) | Tanggal Input: {$cust['tgl_input']}\n\n";
        
        $qFu = $conn->query("SELECT fu.*, s.nama_lengkap as nama_sales_fu 
                             FROM follow_ups fu 
                             LEFT JOIN sales s ON fu.sales_id = s.id 
                             WHERE fu.customer_id = $cid AND fu.deleted_at IS NULL 
                             ORDER BY fu.tgl_follow_up DESC");
        $fus = $qFu ? $qFu->fetch_all(MYSQLI_ASSOC) : [];
        echo "Daftar Follow Up Terhubung (" . count($fus) . " catatan):\n";
        foreach ($fus as $f) {
            $isMatch = ($f['sales_id'] == $cust['sales_id']);
            $status = $isMatch ? "[SESUAI (PRISKA)]" : "[⚠️ SALAH / EDI SUPRIANTO]";
            echo " • FU #{$f['id']} | {$f['tgl_follow_up']} | Sales: {$f['nama_sales_fu']} | Inv: {$f['no_inv']} | {$status}\n";
        }
        
        if (isset($argv[1]) && $argv[1] === '--clean') {
            $stmtClean = $conn->prepare("UPDATE follow_ups fu 
                                         JOIN customers c ON fu.customer_id = c.id 
                                         SET fu.deleted_at = NOW() 
                                         WHERE fu.customer_id = ? 
                                           AND fu.sales_id != c.sales_id 
                                           AND fu.deleted_at IS NULL");
            $stmtClean->bind_param("i", $cid);
            $stmtClean->execute();
            $aff = $stmtClean->affected_rows;
            $stmtClean->close();
            echo "\n>>> HASIL: {$aff} catatan follow-up Edi Suprianto BERHASIL DIBERSIHKAN! <<<\n";
        } else {
            echo "\nKetik 'php clean_alfat_fu.php --clean' untuk langsung membersihkan via CLI.\n";
        }
    }
    exit(0);
}

// Handle Action Clean
$msg = '';
if (isset($_POST['clean_customer_id']) && is_numeric($_POST['clean_customer_id'])) {
    $targetCustId = (int)$_POST['clean_customer_id'];
    
    // Soft delete follow up yang sales_id != sales_id customer ALFAT (Edi Suprianto dll)
    $stmtClean = $conn->prepare("UPDATE follow_ups fu 
                                 JOIN customers c ON fu.customer_id = c.id 
                                 SET fu.deleted_at = NOW() 
                                 WHERE fu.customer_id = ? 
                                   AND fu.sales_id != c.sales_id 
                                   AND fu.deleted_at IS NULL");
    $stmtClean->bind_param("i", $targetCustId);
    $stmtClean->execute();
    $affected = $stmtClean->affected_rows;
    $stmtClean->close();
    
    $msg = "✓ Berhasil membersihkan {$affected} catatan follow-up Edi Suprianto dari customer ALFAT!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pemeriksaan Follow Up Customer ALFAT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; }
        .card { border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
    </style>
</head>
<body>
<div class="container" style="max-width: 900px;">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3 class="fw-bold mb-0">🔍 Pemeriksaan Follow Up Customer ALFAT</h3>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Kembali ke Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success fw-bold"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php foreach ($customers as $cust): ?>
        <?php
        $cid = (int)$cust['id'];
        $qFu = $conn->query("SELECT fu.*, s.nama_lengkap as nama_sales_fu 
                             FROM follow_ups fu 
                             LEFT JOIN sales s ON fu.sales_id = s.id 
                             WHERE fu.customer_id = $cid AND fu.deleted_at IS NULL 
                             ORDER BY fu.tgl_follow_up DESC");
        $fus = $qFu ? $qFu->fetch_all(MYSQLI_ASSOC) : [];
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-primary">#<?php echo $cust['id']; ?> - <?php echo htmlspecialchars($cust['nama_toko']); ?></h5>
                    <span class="text-muted small">Sales Penanggung Jawab: <strong><?php echo htmlspecialchars($cust['nama_sales'] ?? 'N/A'); ?> (ID: <?php echo $cust['sales_id']; ?>)</strong> | Tanggal Input: <strong><?php echo date('d M Y', strtotime($cust['tgl_input'])); ?></strong></span>
                </div>
                <a href="followup_view.php?customer_id=<?php echo $cid; ?>" target="_blank" class="btn btn-sm btn-outline-primary">Buka Detail Customer</a>
            </div>
            <div class="card-body">
                <h6>Daftar Follow Up yang Terhubung:</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>ID FU</th>
                                <th>Tanggal FU</th>
                                <th>Sales</th>
                                <th>Respon</th>
                                <th>Keterangan</th>
                                <th>Invoice</th>
                                <th>Status Relasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fus)): ?>
                                <tr><td colspan="7" class="text-center text-muted">Tidak ada riwayat follow up.</td></tr>
                            <?php else: ?>
                                <?php foreach ($fus as $f): ?>
                                    <?php 
                                    $isMatchSales = ($f['sales_id'] == $cust['sales_id']);
                                    $isBeforeInput = (strtotime($f['tgl_follow_up']) < strtotime($cust['tgl_input']));
                                    ?>
                                    <tr class="<?php echo (!$isMatchSales || $isBeforeInput) ? 'table-warning' : ''; ?>">
                                        <td>#<?php echo $f['id']; ?></td>
                                        <td><?php echo date('d M Y, H:i', strtotime($f['tgl_follow_up'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($f['nama_sales_fu'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($f['respon']); ?></td>
                                        <td><?php echo htmlspecialchars($f['keterangan']); ?></td>
                                        <td><?php echo htmlspecialchars($f['no_inv'] ?: '-'); ?></td>
                                        <td>
                                            <?php if ($isMatchSales && !$isBeforeInput): ?>
                                                <span class="badge bg-success">✓ Sesuai (Priska)</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">⚠️ Tidak Sesuai (Edi Suprianto / Sebelum Input)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" onsubmit="return confirm('Yakin ingin menghapus follow up Edi Suprianto dari customer ALFAT ini? Hanya follow up Priska yang akan dipertahankan.')">
                    <input type="hidden" name="clean_customer_id" value="<?php echo $cid; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                        🗑️ Bersihkan Follow Up Edi Suprianto dari Customer Ini
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
