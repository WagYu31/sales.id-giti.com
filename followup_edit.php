<?php
/**
 * EDIT FOLLOW UP & NOMINAL INVOICE
 */
$page_title = 'Edit Follow Up & Nominal Invoice';
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/media_compressor.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$fu_id = (int)$_GET['id'];
$error = '';
$success = '';

// Fetch Follow Up Data
$stmt = $conn->prepare("
    SELECT fu.*, c.nama_toko, s.nama_lengkap AS nama_sales
    FROM follow_ups fu
    JOIN customers c ON fu.customer_id = c.id
    JOIN sales s ON fu.sales_id = s.id
    WHERE fu.id = ? AND fu.deleted_at IS NULL
");
$stmt->bind_param("i", $fu_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Data Follow Up tidak ditemukan.");
}

$fu = $res->fetch_assoc();
$customer_id = $fu['customer_id'];

// Authorization Check: Superadmin or Sales creator can edit
if ($_SESSION['role'] !== 'superadmin' && $_SESSION['user_id'] != $fu['sales_id']) {
    die("Anda tidak memiliki izin untuk mengedit catatan Follow Up ini.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_inv = trim($_POST['no_inv'] ?? '');
    $nominal_invoice = floatval($_POST['nominal_invoice'] ?? 0);
    $respon_radio = $_POST['respon_radio'] ?? '';
    $respon_lainnya = trim($_POST['respon_lainnya'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    $respon = ($respon_radio === 'Lainnya') ? $respon_lainnya : $respon_radio;
    if (empty($respon)) {
        $respon = $fu['respon'];
    }

    $tgl_follow_up = $fu['tgl_follow_up'];
    if ($_SESSION['role'] === 'superadmin' && !empty($_POST['tgl_follow_up'])) {
        $tgl_follow_up = $_POST['tgl_follow_up'];
    }

    $stmt_up = $conn->prepare("
        UPDATE follow_ups 
        SET no_inv = ?, nominal_invoice = ?, respon = ?, keterangan = ?, tgl_follow_up = ?
        WHERE id = ?
    ");
    $stmt_up->bind_param("sdsssi", $no_inv, $nominal_invoice, $respon, $keterangan, $tgl_follow_up, $fu_id);

    if ($stmt_up->execute()) {
        $_SESSION['flash_message'] = "Data Follow Up & Nominal Invoice berhasil diperbarui!";
        $redirect_to = $_GET['redirect'] ?? 'followup_view.php?customer_id=' . $customer_id;
        header("Location: " . $redirect_to);
        exit();
    } else {
        $error = "Gagal memperbarui data: " . $conn->error;
    }
}
?>

<style>
.edit-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    border-radius: 24px;
    padding: 30px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    box-shadow: 0 16px 36px -10px rgba(15, 23, 42, 0.25);
    border: 1.5px solid #38BDF8;
}
</style>

<div class="container-fluid px-0">
    <!-- Hero Banner -->
    <div class="edit-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <span class="badge bg-primary bg-opacity-20 text-info border border-info rounded-pill px-3 py-1 mb-2" style="font-size: 11px; font-weight: 800;">
                    ✏️ EDIT FOLLOW UP & NOMINAL INVOICE
                </span>
                <h2 class="fw-bold mb-1" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                    Edit Follow Up: <?php echo htmlspecialchars($fu['nama_toko']); ?>
                </h2>
                <p class="text-white-50 mb-0" style="font-size: 13.5px;">Perbarui Nomor Invoice, Nominal Transaksi (Rp), atau catatan respon Follow Up ini.</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?php echo htmlspecialchars($_GET['redirect'] ?? 'followup_view.php?customer_id=' . $customer_id); ?>" class="btn btn-light border fw-bold px-4 rounded-pill">
                    ← Kembali
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 mb-4 shadow-sm"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" style="border-radius: 24px; border: 1.5px solid #E2E8F0;">
        <div class="card-body p-4 p-md-5">
            <form action="followup_edit.php?id=<?php echo $fu_id; ?>&redirect=<?php echo urlencode($_GET['redirect'] ?? ''); ?>" method="POST">
                
                <!-- Row 1: Nomor Invoice & Nominal Invoice (Rp) -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="no_inv" class="form-label fw-bold text-dark">Nomor Invoice</label>
                        <input type="text" class="form-control" id="no_inv" name="no_inv" value="<?php echo htmlspecialchars($fu['no_inv']); ?>" placeholder="Contoh: INV/2026/08/001" style="border-radius:14px; padding: 12px 16px;">
                    </div>

                    <div class="col-md-6">
                        <label for="nominal_invoice_display" class="form-label fw-bold text-success">Nominal Invoice (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold text-success" style="border-radius:14px 0 0 14px; background:#F0FDF4;">Rp</span>
                            <input type="text" class="form-control fw-bold text-success fs-5" id="nominal_invoice_display" placeholder="0" style="border-radius:0 14px 14px 0; padding: 12px 16px;" oninput="formatRupiahInput(this)" value="<?php echo ((float)$fu['nominal_invoice'] > 0) ? number_format((float)$fu['nominal_invoice'], 0, ',', '.') : ''; ?>">
                            <input type="hidden" name="nominal_invoice" id="nominal_invoice" value="<?php echo (float)$fu['nominal_invoice']; ?>">
                        </div>
                        <small class="text-muted" style="font-size: 11px;">*Ketik angka, pemisah titik (.) akan terformat otomatis (contoh: 20.000.000)</small>
                    </div>
                </div>

                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                <!-- Tanggal Follow Up (Superadmin Only) -->
                <div class="mb-4">
                    <label for="tgl_follow_up" class="form-label fw-bold text-dark">Tanggal Follow Up (Superadmin Only)</label>
                    <input type="datetime-local" class="form-control" id="tgl_follow_up" name="tgl_follow_up" value="<?php echo date('Y-m-d\TH:i', strtotime($fu['tgl_follow_up'])); ?>" style="border-radius:14px; padding: 12px 16px;">
                </div>
                <?php endif; ?>

                <!-- Respon Customer -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark d-block mb-2">Respon Customer</label>
                    <div class="row g-2">
                        <?php 
                        $responses = ["Tidak ada respon", "Tidak tertarik", "Hanya bertanya", "Muncul keinginan membeli", "Deal untuk beli"];
                        $current_respon = $fu['respon'];
                        foreach ($responses as $r):
                            $checked = ($current_respon === $r) ? 'checked' : '';
                        ?>
                            <div class="col-md-4 col-6">
                                <div class="form-check border p-3 rounded-3 bg-light">
                                    <input class="form-check-input" type="radio" name="respon_radio" id="resp_<?php echo md5($r); ?>" value="<?php echo htmlspecialchars($r); ?>" <?php echo $checked; ?>>
                                    <label class="form-check-input-label fw-bold text-dark ms-2" for="resp_<?php echo md5($r); ?>">
                                        <?php echo htmlspecialchars($r); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Keterangan / Catatan -->
                <div class="mb-4">
                    <label for="keterangan" class="form-label fw-bold text-dark">Keterangan / Catatan Follow Up</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" rows="4" style="border-radius:16px; padding: 14px 16px;"><?php echo htmlspecialchars($fu['keterangan']); ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="<?php echo htmlspecialchars($_GET['redirect'] ?? 'followup_view.php?customer_id=' . $customer_id); ?>" class="btn btn-light border fw-bold px-4 rounded-pill">Batal</a>
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm">
                        💾 Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
function formatRupiahInput(input) {
    let raw = input.value.replace(/[^0-9]/g, '');
    if (raw === '' || raw === '0') {
        input.value = '';
        document.getElementById('nominal_invoice').value = '0';
        return;
    }
    const formatted = new Intl.NumberFormat('id-ID').format(raw);
    input.value = formatted;
    document.getElementById('nominal_invoice').value = raw;
}
</script>
