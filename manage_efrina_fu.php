<?php
/**
 * manage_efrina_fu.php
 * Skrip untuk memeriksa, memindahkan, atau membersihkan follow up Efrina Panjaitan
 * yang nyasar di customer BUDI LUHUR ABADI.
 *
 * Akses via browser: https://sales.id-giti.com/manage_efrina_fu.php
 * Atau via terminal: php manage_efrina_fu.php
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    if (!isset($_SESSION['user_id'])) {
        die("<div style='font-family:sans-serif;padding:20px;color:red;'>Silakan login terlebih dahulu di <a href='login.php'>sales.id-giti.com</a></div>");
    }
}

// 1. Cari customer BUDI LUHUR ABADI
$qBudi = $conn->query("SELECT c.*, s.nama_lengkap as nama_sales 
                       FROM customers c 
                       LEFT JOIN sales s ON c.sales_id = s.id 
                       WHERE c.nama_toko LIKE '%BUDI LUHUR%' AND c.deleted_at IS NULL 
                       ORDER BY c.id DESC LIMIT 1");
$budi = ($qBudi && $qBudi->num_rows > 0) ? $qBudi->fetch_assoc() : null;

if (!$budi) {
    die("Customer BUDI LUHUR ABADI tidak ditemukan.\n");
}
$budiId = (int)$budi['id'];

// 2. Ambil follow-up Efrina Panjaitan di customer BUDI LUHUR ABADI
$qEfrinaFu = $conn->query("SELECT fu.*, s.nama_lengkap as nama_sales_fu 
                           FROM follow_ups fu 
                           JOIN sales s ON fu.sales_id = s.id 
                           WHERE fu.customer_id = $budiId 
                             AND (s.nama_lengkap LIKE '%Efrina%' OR fu.sales_id != {$budi['sales_id']}) 
                             AND fu.sales_id != 3 
                             AND fu.deleted_at IS NULL 
                           ORDER BY fu.tgl_follow_up DESC");
$efrinaFus = $qEfrinaFu ? $qEfrinaFu->fetch_all(MYSQLI_ASSOC) : [];

// Cari ID Efrina
$qEfrinaSales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE nama_lengkap LIKE '%Efrina%' LIMIT 1");
$efrinaSales = ($qEfrinaSales && $qEfrinaSales->num_rows > 0) ? $qEfrinaSales->fetch_assoc() : null;
$efrinaSalesId = $efrinaSales ? (int)$efrinaSales['id'] : 22;

// 3. Cari toko-toko kandidat milik Efrina Panjaitan yang mengandung kata 'yono'
$candidateStores = [];
if ($efrinaSalesId) {
    $qCand = $conn->query("
        SELECT DISTINCT c.id, c.nama_toko 
        FROM customers c 
        LEFT JOIN customer_pics cp ON c.id = cp.customer_id 
        WHERE c.sales_id = $efrinaSalesId 
          AND c.deleted_at IS NULL 
          AND (c.nama_toko LIKE '%yono%' OR cp.nama_pic LIKE '%yono%')
    ");
    if ($qCand) {
        $candidateStores = $qCand->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle Form Actions
$msg = '';
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['fu_id'])) {
        $fId = (int)$_POST['fu_id'];
        $stmtDel = $conn->prepare("UPDATE follow_ups SET deleted_at = NOW() WHERE id = ?");
        $stmtDel->bind_param("i", $fId);
        $stmtDel->execute();
        $stmtDel->close();
        $msg = "✓ Berhasil menghapus catatan follow up Efrina Panjaitan dari BUDI LUHUR ABADI.";
    } elseif ($_POST['action'] === 'move' && isset($_POST['fu_id']) && isset($_POST['target_cust_id'])) {
        $fId = (int)$_POST['fu_id'];
        $tCustId = (int)$_POST['target_cust_id'];
        $stmtMv = $conn->prepare("UPDATE follow_ups SET customer_id = ? WHERE id = ?");
        $stmtMv->bind_param("ii", $tCustId, $fId);
        $stmtMv->execute();
        $stmtMv->close();
        $msg = "✓ Berhasil memindahkan catatan follow up ke toko tujuan (ID: $tCustId)! Data tetap utuh.";
    }
}

// Handle CLI
if ($is_cli) {
    echo "=================================================================\n";
    echo "  PEMERIKSAAN FOLLOW UP EFRINA PANJAITAN DI BUDI LUHUR ABADI\n";
    echo "=================================================================\n\n";
    echo "Toko        : [ID: #{$budi['id']}] {$budi['nama_toko']}\n";
    echo "Sales Toko  : {$budi['nama_sales']} (ID: {$budi['sales_id']})\n\n";
    
    echo "Daftar Follow Up Efrina yang Ditemukan (" . count($efrinaFus) . " catatan):\n";
    foreach ($efrinaFus as $f) {
        echo " • FU ID: #{$f['id']} | {$f['tgl_follow_up']} | Sales: {$f['nama_sales_fu']}\n";
        echo "   Respon    : {$f['respon']}\n";
        echo "   Keterangan: {$f['keterangan']}\n\n";
        
        if (isset($argv[1]) && $argv[1] === '--remove') {
            $stmtDel = $conn->prepare("UPDATE follow_ups SET deleted_at = NOW() WHERE id = ?");
            $fid = (int)$f['id'];
            $stmtDel->bind_param("i", $fid);
            $stmtDel->execute();
            $stmtDel->close();
            echo ">>> HASIL: Follow up #{$f['id']} BERHASIL DIHAPUS dari Budi Luhur Abadi! <<<\n";
        } elseif (isset($argv[1]) && $argv[1] === '--move' && isset($argv[2])) {
            $targetId = (int)$argv[2];
            $stmtMv = $conn->prepare("UPDATE follow_ups SET customer_id = ? WHERE id = ?");
            $fid = (int)$f['id'];
            $stmtMv->bind_param("ii", $targetId, $fid);
            $stmtMv->execute();
            $stmtMv->close();
            echo ">>> HASIL: Follow up #{$f['id']} BERHASIL DIPINDAHKAN ke customer #{$targetId}! <<<\n";
        }
    }
    
    if (empty($argv[1])) {
        echo "Pilihan perintah terminal:\n";
        echo "  1. Hapus dari Budi Luhur: php manage_efrina_fu.php --remove\n";
        echo "  2. Pindahkan ke toko lain: php manage_efrina_fu.php --move <ID_TOKO_TUJUAN>\n";
    }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pemeriksaan Follow Up Efrina di Budi Luhur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; }
        .card { border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
    </style>
</head>
<body>
<div class="container" style="max-width: 900px;">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1">🔍 Follow Up Efrina Panjaitan di Toko BUDI LUHUR ABADI</h4>
            <p class="text-muted small mb-0">Kelola catatan follow up Efrina (Pak Yono) yang tercatat di toko Budi Luhur Abadi.</p>
        </div>
        <a href="followup_view.php?customer_id=<?php echo $budiId; ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Lihat Budi Luhur</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success fw-bold"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if (empty($efrinaFus)): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-4 me-2"></i>
            <div>Tidak ada follow up Efrina Panjaitan yang nyasar di BUDI LUHUR ABADI. Semua riwayat sudah rapi!</div>
        </div>
    <?php else: ?>
        <?php foreach ($efrinaFus as $fu): ?>
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning bg-opacity-10 py-3">
                    <span class="badge bg-warning text-dark">Follow Up Terdeteksi: ID #<?php echo $fu['id']; ?></span>
                    <strong class="ms-2"><?php echo htmlspecialchars($fu['nama_sales_fu']); ?></strong> | <?php echo date('d M Y, H:i', strtotime($fu['tgl_follow_up'])); ?>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Respon:</strong> <?php echo htmlspecialchars($fu['respon']); ?></p>
                    <p class="mb-3"><strong>Keterangan:</strong> <em>"<?php echo htmlspecialchars($fu['keterangan']); ?>"</em></p>

                    <hr>
                    <h6>Pilih Tindakan untuk Catatan Ini:</h6>
                    <div class="row g-3 mt-1">
                        <!-- Opsi 1: Pindahkan -->
                        <div class="col-md-7">
                            <form method="POST" class="p-3 bg-light rounded-3 border">
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="fu_id" value="<?php echo $fu['id']; ?>">
                                <label class="form-label fw-bold small">Pindahkan ke Toko Lain Milik Efrina:</label>
                                <select name="target_cust_id" class="form-select form-select-sm mb-2" required>
                                    <option value="">-- Pilih Toko Tujuan --</option>
                                    <?php
                                    $qAllEfrina = $conn->query("SELECT id, nama_toko FROM customers WHERE sales_id = $efrinaSalesId AND deleted_at IS NULL ORDER BY nama_toko ASC");
                                    if ($qAllEfrina) {
                                        while ($t = $qAllEfrina->fetch_assoc()) {
                                            $isYono = (stripos($t['nama_toko'], 'yono') !== false) ? ' 🌟 (Pak Yono)' : '';
                                            echo '<option value="' . $t['id'] . '">' . htmlspecialchars($t['nama_toko']) . $isYono . ' (ID: ' . $t['id'] . ')</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100">
                                    🔄 Pindahkan Catatan Ini
                                </button>
                            </form>
                        </div>

                        <!-- Opsi 2: Hapus -->
                        <div class="col-md-5">
                            <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <label class="form-label fw-bold small text-danger">Hapus dari Budi Luhur:</label>
                                    <p class="small text-muted mb-2">Jika catatan ini tidak diperlukan / salah ketik.</p>
                                </div>
                                <form method="POST" onsubmit="return confirm('Yakin ingin menghapus catatan follow up Efrina ini dari Budi Luhur Abadi?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="fu_id" value="<?php echo $fu['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill w-100">
                                        🗑️ Hapus Catatan Ini
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
