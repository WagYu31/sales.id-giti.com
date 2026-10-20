<?php
/**
 * clean_alfat_fu.php
 * Skrip untuk MEMINDAHKAN (bukan menghapus!) riwayat follow up Edi Suprianto
 * dari customer ALFAT ke customer aslinya: BUDI LUHUR ABADI.
 *
 * Akses via browser: https://sales.id-giti.com/clean_alfat_fu.php
 * Atau via terminal: php clean_alfat_fu.php --move
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

// 2. Cari customer BUDI LUHUR ABADI sebagai target pemindahan
$qBudi = $conn->query("SELECT c.*, s.nama_lengkap as nama_sales 
                       FROM customers c 
                       LEFT JOIN sales s ON c.sales_id = s.id 
                       WHERE c.nama_toko LIKE '%BUDI LUHUR%' AND c.deleted_at IS NULL 
                       ORDER BY c.id DESC LIMIT 1");
$budiLuhur = ($qBudi && $qBudi->num_rows > 0) ? $qBudi->fetch_assoc() : null;

// Handle Action Reassign / Move
$msg = '';
$msg_type = 'success';

if (isset($_POST['action']) && $_POST['action'] === 'move' && isset($_POST['target_customer_id']) && is_numeric($_POST['target_customer_id'])) {
    $targetCustId = (int)$_POST['target_customer_id'];
    $sourceCustId = (int)$_POST['source_customer_id'];
    
    // Pindahkan follow up yang sales_id != sales_id customer ALFAT (Edi Suprianto) ke customer target
    $stmtMove = $conn->prepare("UPDATE follow_ups fu 
                                JOIN customers c ON fu.customer_id = c.id 
                                SET fu.customer_id = ? 
                                WHERE fu.customer_id = ? 
                                  AND fu.sales_id != c.sales_id 
                                  AND fu.deleted_at IS NULL");
    $stmtMove->bind_param("ii", $targetCustId, $sourceCustId);
    $stmtMove->execute();
    $affected = $stmtMove->affected_rows;
    $stmtMove->close();
    
    $msg = "✅ BERHASIL MEMINDAHKAN {$affected} catatan follow-up Edi Suprianto ke customer BUDI LUHUR ABADI! Data TIDAK DIHAPUS, semua invoice dan riwayat tetap utuh.";
}

// Jika dijalankan via Terminal CLI
if ($is_cli) {
    echo "=================================================================\n";
    echo "  PEMERIKSAAN & PENGALIHAN FOLLOW UP (ALFAT -> BUDI LUHUR ABADI)\n";
    echo "=================================================================\n\n";
    
    if (!$budiLuhur) {
        die("Error: Toko target 'BUDI LUHUR ABADI' tidak ditemukan di database.\n");
    }
    
    echo "Toko Tujuan  : [ID: #{$budiLuhur['id']}] {$budiLuhur['nama_toko']}\n";
    echo "Sales Tujuan : {$budiLuhur['nama_sales']} (ID: {$budiLuhur['sales_id']})\n\n";
    
    foreach ($customers as $cust) {
        $cid = (int)$cust['id'];
        echo "Toko Sumber  : [ID: #{$cust['id']}] {$cust['nama_toko']}\n";
        echo "Sales Sumber : {$cust['nama_sales']} (ID: {$cust['sales_id']}) | Tanggal Input: {$cust['tgl_input']}\n\n";
        
        $qFu = $conn->query("SELECT fu.*, s.nama_lengkap as nama_sales_fu 
                             FROM follow_ups fu 
                             LEFT JOIN sales s ON fu.sales_id = s.id 
                             WHERE fu.customer_id = $cid AND fu.deleted_at IS NULL 
                             ORDER BY fu.tgl_follow_up DESC");
        $fus = $qFu ? $qFu->fetch_all(MYSQLI_ASSOC) : [];
        echo "Daftar Follow Up di Toko ALFAT (" . count($fus) . " catatan):\n";
        $canMoveCount = 0;
        foreach ($fus as $f) {
            $isMatch = ($f['sales_id'] == $cust['sales_id']);
            $status = $isMatch ? "[TETAP DI ALFAT (PRISKA)]" : "[AKAN DIPINDAHKAN KE BUDI LUHUR]";
            if (!$isMatch) $canMoveCount++;
            echo " • FU #{$f['id']} | {$f['tgl_follow_up']} | Sales: {$f['nama_sales_fu']} | Inv: " . ($f['no_inv'] ?: '-') . " | {$status}\n";
        }
        
        if (isset($argv[1]) && in_array($argv[1], ['--move', '--reassign'])) {
            $stmtMove = $conn->prepare("UPDATE follow_ups fu 
                                        JOIN customers c ON fu.customer_id = c.id 
                                        SET fu.customer_id = ? 
                                        WHERE fu.customer_id = ? 
                                          AND fu.sales_id != c.sales_id 
                                          AND fu.deleted_at IS NULL");
            $budiId = (int)$budiLuhur['id'];
            $stmtMove->bind_param("ii", $budiId, $cid);
            $stmtMove->execute();
            $aff = $stmtMove->affected_rows;
            $stmtMove->close();
            echo "\n>>> HASIL: {$aff} catatan follow-up Edi Suprianto BERHASIL DIPINDAHKAN KE BUDI LUHUR ABADI! <<<\n";
            echo ">>> TIDAK ADA DATA YANG DIHAPUS. <<<\n";
        } else {
            echo "\nJalankan perintah berikut untuk memindahkan (tanpa menghapus data):\n";
            echo "php clean_alfat_fu.php --move\n";
        }
    }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengalihan Relasi Follow Up: ALFAT ke BUDI LUHUR ABADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; }
        .card { border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .hero { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 24px; border-radius: 18px; margin-bottom: 24px; }
    </style>
</head>
<body>
<div class="container" style="max-width: 960px;">
    <div class="hero d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-arrow-left-right text-warning"></i> Pengalihan Relasi Follow Up</h4>
            <p class="mb-0 text-white-50" style="font-size: 13.5px;">Memindahkan catatan follow-up Edi Suprianto ke toko aslinya (BUDI LUHUR ABADI) secara aman <strong>tanpa menghapus data</strong>.</p>
        </div>
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">Kembali ke Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success fw-bold d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill fs-4 me-2"></i>
            <div><?php echo htmlspecialchars($msg); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($budiLuhur): ?>
    <div class="alert alert-info border-info d-flex align-items-center mb-4">
        <i class="bi bi-info-circle-fill fs-4 text-info me-3"></i>
        <div>
            <strong>Toko Tujuan Terdeteksi:</strong> #<?php echo $budiLuhur['id']; ?> - <strong><?php echo htmlspecialchars($budiLuhur['nama_toko']); ?></strong> 
            (Sales: <strong><?php echo htmlspecialchars($budiLuhur['nama_sales']); ?></strong>).
            <br>
            <span class="small text-muted">Follow-up milik Edi Suprianto (termasuk Invoice Rp 2.200.000 No: 2609.INV.06862) akan dialihkan ke toko ini.</span>
        </div>
    </div>
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
        $ediFuCount = 0;
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-primary">Toko: #<?php echo $cust['id']; ?> - <?php echo htmlspecialchars($cust['nama_toko']); ?></h5>
                    <span class="text-muted small">Sales Penanggung Jawab: <strong><?php echo htmlspecialchars($cust['nama_sales'] ?? 'N/A'); ?></strong> | Tanggal Input: <strong><?php echo date('d M Y', strtotime($cust['tgl_input'])); ?></strong></span>
                </div>
                <a href="followup_view.php?customer_id=<?php echo $cid; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right"></i> Buka Detail Customer
                </a>
            </div>
            <div class="card-body">
                <h6>Daftar Follow Up Saat Ini:</h6>
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
                                <th>Tindakan Sistem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fus)): ?>
                                <tr><td colspan="7" class="text-center text-muted">Tidak ada riwayat follow up.</td></tr>
                            <?php else: ?>
                                <?php foreach ($fus as $f): ?>
                                    <?php 
                                    $isMatchSales = ($f['sales_id'] == $cust['sales_id']);
                                    if (!$isMatchSales) $ediFuCount++;
                                    ?>
                                    <tr class="<?php echo (!$isMatchSales) ? 'table-warning' : ''; ?>">
                                        <td>#<?php echo $f['id']; ?></td>
                                        <td class="text-nowrap"><?php echo date('d M Y, H:i', strtotime($f['tgl_follow_up'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($f['nama_sales_fu'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($f['respon']); ?></td>
                                        <td><?php echo htmlspecialchars($f['keterangan']); ?></td>
                                        <td class="text-nowrap"><?php echo htmlspecialchars($f['no_inv'] ?: '-'); ?></td>
                                        <td>
                                            <?php if ($isMatchSales): ?>
                                                <span class="badge bg-success">✓ Tetap di ALFAT (Priska)</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">🔄 Akan dipindahkan ke BUDI LUHUR</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($ediFuCount > 0 && $budiLuhur): ?>
                <form method="POST" onsubmit="return confirm('Pindahkan <?php echo $ediFuCount; ?> catatan follow up Edi Suprianto ke BUDI LUHUR ABADI? Data tidak akan dihapus dan tetap aman.')">
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="source_customer_id" value="<?php echo $cid; ?>">
                    <input type="hidden" name="target_customer_id" value="<?php echo $budiLuhur['id']; ?>">
                    <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-arrow-right-circle"></i> Pindahkan <?php echo $ediFuCount; ?> Follow Up Edi Suprianto ke BUDI LUHUR ABADI (Data Tetap Utuh)
                    </button>
                </form>
                <?php elseif ($ediFuCount === 0): ?>
                <div class="alert alert-success mb-0 py-2">
                    <i class="bi bi-check-all"></i> Semua riwayat follow up di toko ini sudah sesuai dengan sales penanggung jawab (Priska).
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
