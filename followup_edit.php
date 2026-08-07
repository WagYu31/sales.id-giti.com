<?php
ob_start();
/**
 * EDIT FOLLOW UP & NOMINAL INVOICE
 */
$page_title = 'Edit Follow Up & Nominal Invoice';
require_once 'includes/db.php';
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
    
    // Robust Rupiah parsing: removes dots, commas, spaces
    $raw_nominal = $_POST['nominal_invoice'] ?? '0';
    $clean_nominal = str_replace(['.', ',', ' '], '', $raw_nominal);
    $nominal_invoice = floatval($clean_nominal);

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
    
    if (!$stmt_up) {
        $error = "Database error: " . $conn->error;
    } else {
        $stmt_up->bind_param("sdsssi", $no_inv, $nominal_invoice, $respon, $keterangan, $tgl_follow_up, $fu_id);

        if ($stmt_up->execute()) {
            $_SESSION['flash_message'] = "Data Follow Up & Nominal Invoice berhasil diperbarui!";
            $redirect_to = $_POST['redirect'] ?? ($_GET['redirect'] ?? ('followup_view.php?customer_id=' . $customer_id));
            if (empty($redirect_to)) {
                $redirect_to = 'followup_view.php?customer_id=' . $customer_id;
            }
            if (!headers_sent()) {
                header("Location: " . $redirect_to);
            } else {
                echo "<script>window.location.href = '" . addslashes($redirect_to) . "';</script>";
            }
            exit();
        } else {
            $error = "Gagal memperbarui data: " . $stmt_up->error;
        }
    }
}

require_once 'includes/header.php';
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
            <form action="followup_edit.php?id=<?php echo $fu_id; ?>" method="POST">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? ''); ?>">
                
                <!-- Row 1: Nomor Invoice & Nominal Invoice (Rp) -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="no_inv" class="form-label fw-bold text-dark">Nomor Invoice</label>
                        <input type="text" class="form-control" id="no_inv" name="no_inv" value="<?php echo htmlspecialchars($fu['no_inv']); ?>" placeholder="Contoh: INV/2026/08/001" style="border-radius:14px; padding: 12px 16px;">
                    </div>

                    <div class="col-md-6">
                        <label for="nominal_invoice" class="form-label fw-bold text-success">Nominal Invoice (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold text-success" style="border-radius:14px 0 0 14px; background:#F0FDF4;">Rp</span>
                            <input type="text" class="form-control fw-bold text-success fs-5" id="nominal_invoice" name="nominal_invoice" placeholder="0" style="border-radius:0 14px 14px 0; padding: 12px 16px;" oninput="formatRupiahInput(this)" value="<?php echo ((float)$fu['nominal_invoice'] > 0) ? number_format((float)$fu['nominal_invoice'], 0, ',', '.') : ''; ?>">
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

                <!-- Respon Customer (Interactive Segmented Option Chips Grid) -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark d-block mb-2.5">
                        Respon Customer <span class="text-danger">*</span>
                    </label>
                    <?php 
                    $current_respon = $fu['respon'];
                    $preset_responses = ["Tidak ada respon", "Tidak tertarik", "Hanya bertanya", "Muncul keinginan membeli", "Deal untuk beli"];
                    $is_lainnya = !in_array($current_respon, $preset_responses);
                    ?>
                    <div class="respon-chip-grid">
                        <label class="respon-chip-card <?php echo ($current_respon === 'Tidak ada respon') ? 'active' : ''; ?>">
                            <input type="radio" name="respon_radio" value="Tidak ada respon" <?php echo ($current_respon === 'Tidak ada respon') ? 'checked' : ''; ?>>
                            <div class="chip-content">
                                <i class="bi bi-dash-circle text-secondary fs-5"></i>
                                <span>Tidak Ada Respon</span>
                            </div>
                        </label>
                        
                        <label class="respon-chip-card <?php echo ($current_respon === 'Tidak tertarik') ? 'active' : ''; ?>">
                            <input type="radio" name="respon_radio" value="Tidak tertarik" <?php echo ($current_respon === 'Tidak tertarik') ? 'checked' : ''; ?>>
                            <div class="chip-content">
                                <i class="bi bi-x-circle text-danger fs-5"></i>
                                <span>Tidak Tertarik</span>
                            </div>
                        </label>

                        <label class="respon-chip-card <?php echo ($current_respon === 'Hanya bertanya') ? 'active' : ''; ?>">
                            <input type="radio" name="respon_radio" value="Hanya bertanya" <?php echo ($current_respon === 'Hanya bertanya') ? 'checked' : ''; ?>>
                            <div class="chip-content">
                                <i class="bi bi-question-circle text-info fs-5"></i>
                                <span>Hanya Bertanya</span>
                            </div>
                        </label>

                        <label class="respon-chip-card <?php echo ($current_respon === 'Muncul keinginan membeli') ? 'active' : ''; ?>">
                            <input type="radio" name="respon_radio" value="Muncul keinginan membeli" <?php echo ($current_respon === 'Muncul keinginan membeli') ? 'checked' : ''; ?>>
                            <div class="chip-content">
                                <i class="bi bi-bag-check text-primary fs-5"></i>
                                <span>Muncul Keinginan Membeli</span>
                            </div>
                        </label>

                        <label class="respon-chip-card highlight-deal <?php echo ($current_respon === 'Deal untuk beli') ? 'active' : ''; ?>">
                            <input type="radio" name="respon_radio" value="Deal untuk beli" <?php echo ($current_respon === 'Deal untuk beli') ? 'checked' : ''; ?>>
                            <div class="chip-content">
                                <i class="bi bi-trophy-fill text-success fs-5"></i>
                                <span>🎉 Deal Untuk Beli</span>
                            </div>
                        </label>

                        <label class="respon-chip-card <?php echo ($is_lainnya) ? 'active' : ''; ?>">
                            <input type="radio" name="respon_radio" value="Lainnya" <?php echo ($is_lainnya) ? 'checked' : ''; ?>>
                            <div class="chip-content">
                                <i class="bi bi-pencil-square text-warning fs-5"></i>
                                <span>Lainnya...</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mb-4" id="respon_lainnya_container" style="display: <?php echo ($is_lainnya) ? 'block' : 'none'; ?>;">
                    <label for="respon_lainnya" class="form-label fw-bold text-dark">Respon Lainnya <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="respon_lainnya" name="respon_lainnya" value="<?php echo ($is_lainnya) ? htmlspecialchars($current_respon) : ''; ?>" placeholder="Tulis rincian respon customer..." style="border-radius:14px; padding: 12px 16px;">
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
document.addEventListener('DOMContentLoaded', function() {
    const responRadios = document.querySelectorAll('input[name="respon_radio"]');
    const lainnyaContainer = document.getElementById('respon_lainnya_container');
    const lainnyaInput = document.getElementById('respon_lainnya');

    responRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.respon-chip-card').forEach(c => c.classList.remove('active'));
            if (this.checked) {
                this.closest('.respon-chip-card').classList.add('active');
            }

            if (this.value === 'Lainnya') {
                if (lainnyaContainer) lainnyaContainer.style.display = 'block';
                if (lainnyaInput) lainnyaInput.required = true;
            } else {
                if (lainnyaContainer) lainnyaContainer.style.display = 'none';
                if (lainnyaInput) {
                    lainnyaInput.required = false;
                    lainnyaInput.value = '';
                }
            }
        });
    });
});

function formatRupiahInput(input) {
    let raw = input.value.replace(/[^0-9]/g, '');
    if (raw === '' || raw === '0') {
        input.value = '';
        return;
    }
    input.value = new Intl.NumberFormat('id-ID').format(raw);
}
</script>
