<?php
/**
 * fix_adam_jaya_fu.php
 * Skrip untuk memindahkan riwayat follow-up milik Edi Suprianto
 * yang salah masuk di CV.SMART INDONESIA ke customer yang benar: ADAM JAYA MEDIA.
 *
 * Akses via browser: https://sales.id-giti.com/fix_adam_jaya_fu.php
 * Atau via terminal : php fix_adam_jaya_fu.php --move
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    if (!isset($_SESSION['user_id'])) {
        die("<div style='font-family:sans-serif;padding:30px;color:#dc2626;background:#fef2f2;'><h3>Akses Ditolak</h3>Silakan login terlebih dahulu sebagai Superadmin di <a href='login.php'>sales.id-giti.com</a></div>");
    }
}

// 1. Deteksi Toko Sumber (CV.SMART INDONESIA atau via no invoice yang nyasar)
$sourceCust = null;

// Coba cari dari invoice 2609.INV.06866 / 2608.INV.06551
$qInv = $conn->query("
    SELECT c.*, s.nama_lengkap as nama_sales 
    FROM follow_ups fu 
    JOIN customers c ON fu.customer_id = c.id 
    LEFT JOIN sales s ON c.sales_id = s.id 
    WHERE (fu.no_inv LIKE '%06866%' OR fu.no_inv LIKE '%06551%')
    LIMIT 1
");
if ($qInv && $qInv->num_rows > 0) {
    $sourceCust = $qInv->fetch_assoc();
}

// Jika belum ketemu, cari via nama toko
if (!$sourceCust) {
    $qSource = $conn->query("
        SELECT c.*, s.nama_lengkap as nama_sales 
        FROM customers c 
        LEFT JOIN sales s ON c.sales_id = s.id 
        WHERE (c.nama_toko LIKE '%SMART INDONESIA%' OR c.nama_toko LIKE '%SMART%') AND c.deleted_at IS NULL 
        ORDER BY c.id DESC LIMIT 1
    ");
    if ($qSource && $qSource->num_rows > 0) {
        $sourceCust = $qSource->fetch_assoc();
    }
}

// 2. Deteksi Toko Tujuan (ADAM JAYA MEDIA)
$targetCust = null;
$qTarget = $conn->query("
    SELECT c.*, s.nama_lengkap as nama_sales 
    FROM customers c 
    LEFT JOIN sales s ON c.sales_id = s.id 
    WHERE c.nama_toko LIKE '%ADAM JAYA%' AND c.deleted_at IS NULL 
    ORDER BY c.id DESC LIMIT 1
");
if ($qTarget && $qTarget->num_rows > 0) {
    $targetCust = $qTarget->fetch_assoc();
}

// Ambil ID Edi Suprianto
$qEdi = $conn->query("SELECT id, nama_lengkap FROM sales WHERE id = 3 OR LOWER(nama_lengkap) LIKE '%edi suprianto%' LIMIT 1");
$ediSales = ($qEdi && $qEdi->num_rows > 0) ? $qEdi->fetch_assoc() : null;
$ediId = $ediSales ? (int)$ediSales['id'] : 3;

$sourceId = $sourceCust ? (int)$sourceCust['id'] : 0;
$targetId = $targetCust ? (int)$targetCust['id'] : 0;

// Override jika dikirim lewat GET / POST
if (isset($_REQUEST['source_id']) && is_numeric($_REQUEST['source_id'])) {
    $sourceId = (int)$_REQUEST['source_id'];
    $sQ = $conn->query("SELECT c.*, s.nama_lengkap as nama_sales FROM customers c LEFT JOIN sales s ON c.sales_id = s.id WHERE c.id = $sourceId");
    if ($sQ && $sQ->num_rows > 0) $sourceCust = $sQ->fetch_assoc();
}
if (isset($_REQUEST['target_id']) && is_numeric($_REQUEST['target_id'])) {
    $targetId = (int)$_REQUEST['target_id'];
    $tQ = $conn->query("SELECT c.*, s.nama_lengkap as nama_sales FROM customers c LEFT JOIN sales s ON c.sales_id = s.id WHERE c.id = $targetId");
    if ($tQ && $tQ->num_rows > 0) $targetCust = $tQ->fetch_assoc();
}

// 3. Ambil Follow-Up Edi Suprianto di toko sumber
$mismatchedFus = [];
if ($sourceId > 0) {
    $qFus = $conn->query("
        SELECT fu.*, s.nama_lengkap as nama_sales_fu 
        FROM follow_ups fu 
        LEFT JOIN sales s ON fu.sales_id = s.id 
        WHERE fu.customer_id = $sourceId 
          AND (fu.sales_id = $ediId OR fu.no_inv LIKE '%06866%' OR fu.no_inv LIKE '%06551%')
          AND fu.deleted_at IS NULL 
        ORDER BY fu.tgl_follow_up DESC
    ");
    if ($qFus) {
        $mismatchedFus = $qFus->fetch_all(MYSQLI_ASSOC);
    }
}

// 4. Handle POST Actions
$flash_msg = '';
$flash_type = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'move_all' && $targetId > 0 && !empty($mismatchedFus)) {
        $fuIds = array_column($mismatchedFus, 'id');
        $idList = implode(',', array_map('intval', $fuIds));
        
        $up = $conn->query("UPDATE follow_ups SET customer_id = $targetId WHERE id IN ($idList)");
        if ($up) {
            $aff = $conn->affected_rows;
            $flash_msg = "✅ Sukses! Sebanyak <strong>{$aff} riwayat follow-up</strong> berhasil dipindahkan ke toko <strong>" . htmlspecialchars($targetCust['nama_toko']) . "</strong>. Sekarang riwayat follow up sudah muncul di akun Edi Suprianto!";
            $mismatchedFus = [];
        } else {
            $flash_msg = "❌ Gagal memindahkan follow up: " . $conn->error;
            $flash_type = 'danger';
        }
    } elseif ($_POST['action'] === 'move_single' && isset($_POST['fu_id']) && $targetId > 0) {
        $fuId = (int)$_POST['fu_id'];
        $up = $conn->query("UPDATE follow_ups SET customer_id = $targetId WHERE id = $fuId");
        if ($up) {
            $flash_msg = "✅ Sukses memindahkan follow-up #{$fuId} ke " . htmlspecialchars($targetCust['nama_toko']) . "!";
            $mismatchedFus = array_filter($mismatchedFus, fn($f) => $f['id'] != $fuId);
        } else {
            $flash_msg = "❌ Gagal: " . $conn->error;
            $flash_type = 'danger';
        }
    }
}

// Mode CLI
if ($is_cli) {
    echo "=================================================================\n";
    echo "   PEMINDAHAN FOLLOW UP SALAH MASUK: SMART INDONESIA -> ADAM JAYA \n";
    echo "=================================================================\n";
    echo "Waktu: " . date('Y-m-d H:i:s') . "\n\n";

    echo "Toko Sumber (Salah Masuk) : " . ($sourceCust ? "[ID: #{$sourceCust['id']}] {$sourceCust['nama_toko']} (Sales PJ: {$sourceCust['nama_sales']})" : "TIDAK DITEMUKAN") . "\n";
    echo "Toko Tujuan (Asli/Benar)  : " . ($targetCust ? "[ID: #{$targetCust['id']}] {$targetCust['nama_toko']} (Sales PJ: {$targetCust['nama_sales']})" : "TIDAK DITEMUKAN") . "\n\n";

    echo "Ditemukan " . count($mismatchedFus) . " follow-up Edi Suprianto di toko sumber:\n";
    foreach ($mismatchedFus as $f) {
        echo " • FU #{$f['id']} | {$f['tgl_follow_up']} | Respon: {$f['respon']} | Inv: {$f['no_inv']} (Rp " . number_format($f['nominal_invoice'] ?? 0, 0, ',', '.') . ") | Ket: {$f['keterangan']}\n";
    }

    if (isset($argv[1]) && $argv[1] === '--move') {
        if ($targetId > 0 && !empty($mismatchedFus)) {
            $fuIds = array_column($mismatchedFus, 'id');
            $idList = implode(',', array_map('intval', $fuIds));
            $conn->query("UPDATE follow_ups SET customer_id = $targetId WHERE id IN ($idList)");
            echo "\n>>> BERHASIL memindahkan " . count($fuIds) . " follow up ke ADAM JAYA MEDIA (ID: $targetId)! <<<\n";
        } else {
            echo "\n>>> Tidak ada data yang perlu dipindahkan atau target toko tidak ditemukan.\n";
        }
    } else {
        echo "\nJalankan 'php fix_adam_jaya_fu.php --move' untuk mengeksekusi via CLI.\n";
    }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perbaikan Follow Up: ADAM JAYA MEDIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .main-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        .glass-header {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.9) 100%);
            border-bottom: 1px solid #334155;
            padding: 28px 36px;
        }
        .store-pill {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 18px 22px;
            height: 100%;
        }
        .table-custom {
            color: #e2e8f0;
            vertical-align: middle;
        }
        .table-custom th {
            background: #0f172a;
            color: #94a3b8;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 16px;
            border-bottom: 1px solid #334155;
        }
        .table-custom td {
            padding: 14px 16px;
            border-bottom: 1px solid #334155;
            background: transparent;
        }
        .btn-action-glow {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: none;
            color: #ffffff;
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 14px;
            box-shadow: 0 10px 20px -5px rgba(2, 132, 199, 0.4);
            transition: all 0.2s ease;
        }
        .btn-action-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -5px rgba(2, 132, 199, 0.6);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1100px;">
        <div class="main-card">
            <!-- Header -->
            <div class="glass-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <span class="badge bg-primary bg-opacity-25 text-info border border-info border-opacity-25 px-3 py-1.5 rounded-pill mb-2" style="font-size: 11.5px; font-weight: 700;">
                        🔄 REKONSILIASI RELASI FOLLOW-UP SALES
                    </span>
                    <h2 class="h3 fw-bold mb-1 text-white">Perbaikan Follow-Up: ADAM JAYA MEDIA</h2>
                    <p class="text-secondary mb-0" style="font-size: 13.5px;">
                        Pindahkan catatan follow-up Edi Suprianto yang salah masuk di CV.SMART INDONESIA ke customer aslinya.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2 text-white">
                        <i class="bi bi-house me-1"></i> Dashboard
                    </a>
                    <?php if ($targetCust): ?>
                    <a href="followup_view.php?customer_id=<?php echo $targetCust['id']; ?>" class="btn btn-sm btn-outline-info rounded-pill px-3 py-2">
                        <i class="bi bi-eye me-1"></i> Lihat ADAM JAYA MEDIA
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Body -->
            <div class="p-4 p-md-5">
                <?php if (!empty($flash_msg)): ?>
                <div class="alert alert-<?php echo $flash_type; ?> border-0 rounded-4 shadow-sm mb-4 p-3 d-flex align-items-center gap-3">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <div><?php echo $flash_msg; ?></div>
                </div>
                <?php endif; ?>

                <!-- Store Comparison Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="store-pill border-danger border-opacity-50">
                            <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-30 mb-2">
                                ❌ Lokasi Saat Ini (Salah Relasi)
                            </span>
                            <h4 class="h5 fw-bold text-white mb-1">
                                <?php echo $sourceCust ? htmlspecialchars($sourceCust['nama_toko']) : 'Tidak Ditemukan'; ?>
                            </h4>
                            <p class="text-secondary small mb-2">
                                Customer ID: <strong>#<?php echo $sourceId; ?></strong> | Sales PJ: <strong><?php echo htmlspecialchars($sourceCust['nama_sales'] ?? 'Efrina Panjaitan'); ?></strong>
                            </p>
                            <div class="text-warning small">
                                <i class="bi bi-exclamation-triangle me-1"></i> Toko ini baru diinput 14 Okt 2026, tetapi menampung riwayat follow-up & invoice milik Edi Suprianto bulan Juni–September 2026.
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="store-pill border-success border-opacity-50">
                            <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-30 mb-2">
                                ✅ Toko Tujuan Asli (Milik Edi Suprianto)
                            </span>
                            <h4 class="h5 fw-bold text-white mb-1">
                                <?php echo $targetCust ? htmlspecialchars($targetCust['nama_toko']) : 'Tidak Ditemukan'; ?>
                            </h4>
                            <p class="text-secondary small mb-2">
                                Customer ID: <strong>#<?php echo $targetId; ?></strong> | Sales PJ: <strong><?php echo htmlspecialchars($targetCust['nama_sales'] ?? 'Edi Suprianto'); ?></strong>
                            </p>
                            <div class="text-success small">
                                <i class="bi bi-check-circle me-1"></i> Toko ini yang seharusnya memiliki riwayat follow-up dan order babycam / invoice tersebut.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Follow-ups to Move -->
                <div class="card bg-slate-900 border-0 rounded-4 overflow-hidden mb-4" style="background:#0f172a; border: 1px solid #334155;">
                    <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="h6 fw-bold mb-0 text-white">
                            📋 Daftar Catatan Follow Up yang Salah Masuk (<?php echo count($mismatchedFus); ?> Record)
                        </h5>
                        <?php if (!empty($mismatchedFus) && $targetId > 0): ?>
                        <form method="POST" onsubmit="return confirm('Pindahkan semua <?php echo count($mismatchedFus); ?> catatan follow-up ini ke toko ADAM JAYA MEDIA?');">
                            <input type="hidden" name="action" value="move_all">
                            <input type="hidden" name="source_id" value="<?php echo $sourceId; ?>">
                            <input type="hidden" name="target_id" value="<?php echo $targetId; ?>">
                            <button type="submit" class="btn btn-action-glow btn-sm">
                                <i class="bi bi-arrow-right-circle me-1.5"></i> Pindahkan Semua ke ADAM JAYA MEDIA
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Sales</th>
                                    <th>Respon</th>
                                    <th>Keterangan / Invoice</th>
                                    <th>Nominal</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mismatchedFus)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-secondary">
                                        <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                        Tidak ada follow-up salah masuk yang perlu dipindahkan.<br>
                                        Semua riwayat follow-up sudah berada di customer yang sesuai!
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($mismatchedFus as $fu): ?>
                                    <tr>
                                        <td class="text-nowrap" style="font-size: 13px;">
                                            <i class="bi bi-clock me-1 text-secondary"></i>
                                            <?php echo date('d M Y, H:i', strtotime($fu['tgl_follow_up'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-20 text-info border border-info border-opacity-25">
                                                <?php echo htmlspecialchars($fu['nama_sales_fu'] ?? 'Edi Suprianto'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-50">
                                                <?php echo htmlspecialchars($fu['respon']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 13.5px;"><?php echo nl2br(htmlspecialchars($fu['keterangan'] ?? '-')); ?></div>
                                            <?php if (!empty($fu['no_inv'])): ?>
                                                <div class="mt-1">
                                                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">
                                                        <i class="bi bi-receipt me-1"></i><?php echo htmlspecialchars($fu['no_inv']); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap fw-bold text-success" style="font-size: 13.5px;">
                                            <?php if ((float)($fu['nominal_invoice'] ?? 0) > 0): ?>
                                                Rp <?php echo number_format((float)$fu['nominal_invoice'], 0, ',', '.'); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Pindahkan follow-up ini ke ADAM JAYA MEDIA?');">
                                                <input type="hidden" name="action" value="move_single">
                                                <input type="hidden" name="fu_id" value="<?php echo $fu['id']; ?>">
                                                <input type="hidden" name="source_id" value="<?php echo $sourceId; ?>">
                                                <input type="hidden" name="target_id" value="<?php echo $targetId; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                                    Pindahkan
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Guidance Footer -->
                <div class="p-3 rounded-4 bg-slate-900 border border-slate-700 text-secondary small">
                    <strong class="text-white"><i class="bi bi-info-circle me-1 text-info"></i> Mengapa Masalah Ini Terjadi?</strong><br>
                    Toko <strong>CV.SMART INDONESIA</strong> baru diinput pada 14 Oktober 2026. Namun ID-nya sempat menampung sisa follow-up dari Edi Suprianto bulan Juni–September 2026. Tombol di atas akan memindahkan relasi ID follow-up ke <strong>ADAM JAYA MEDIA</strong> secara utuh dan aman tanpa menghapus riwayat, foto, maupun nominal invoice transaksi.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
