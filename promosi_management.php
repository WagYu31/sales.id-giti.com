<?php
$page_title = 'Manajemen Promosi';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$sql = "
    SELECT p.*, 
           (SELECT pb.budget_amount 
            FROM promotion_budgets pb 
            WHERE pb.promotion_id = p.id 
            ORDER BY pb.effective_date DESC 
            LIMIT 1) as latest_budget
    FROM promotions p 
    WHERE p.deleted_at IS NULL 
    ORDER BY p.start_date DESC
";
$result = $conn->query($sql);
$promotions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<style>
.promo-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.promo-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.promo-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.promo-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.promo-card-row {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 12px;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.promo-card-row:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px -6px rgba(0,0,0,0.08);
    border-color: #BFDBFE;
}

.platform-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: 13px;
    color: #0F172A;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.budget-badge {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
    padding: 6px 14px;
    border-radius: 10px;
    display: inline-block;
    transition: all 0.2s ease;
}

.budget-badge:hover {
    background: #2563EB;
    color: #FFF;
    border-color: #2563EB;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
</style>

<!-- Hero Header -->
<div class="promo-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Manajemen Promosi</span>
            </div>
            <h1 class="promo-hero-title">Manajemen Promosi 📣</h1>
            <p class="promo-hero-subtitle">Kelola campaign iklan, budget harian marketplace & ads, serta durasi promosi produk.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-primary shadow-lg" id="btn-add-new">
                <i class="bi bi-plus-circle-fill"></i> Tambah Promosi Baru
            </button>
        </div>
    </div>
</div>

<!-- Main Card Container -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0"><i class="bi bi-tags-fill"></i> Daftar Campaign Promosi</h5>
        <div style="min-width: 260px;">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="liveSearchInput" class="form-control border-start-0 ps-0" placeholder="Cari promosi...">
            </div>
        </div>
    </div>
    <div class="card-body">
        <div id="promo-list-container">
            <!-- Table Header Row -->
            <div class="d-none d-lg-flex row gx-3 text-muted small fw-bold mb-3 px-3 py-2 rounded-3" style="background:#0F172A; color:#FFF !important; letter-spacing:0.8px; font-family:'Plus Jakarta Sans', sans-serif;">
                <div class="col-lg-3">NAMA PROMOSI</div>
                <div class="col-lg-2">PLATFORM & TIPE</div>
                <div class="col-lg-1 text-center">PRODUK</div>
                <div class="col-lg-2">DURASI</div>
                <div class="col-lg-2">BUDGET HARIAN</div>
                <div class="col-lg-2 text-center">AKSI</div>
            </div>

            <?php if (empty($promotions)): ?>
                <div class="text-center p-5 border rounded-3 bg-light">
                    <h5 class="text-muted">Belum ada data promosi terdaftar.</h5>
                </div>
            <?php endif; ?>

            <?php foreach ($promotions as $promo): ?>
            <div class="promo-card-row promo-item" id="promo-row-<?php echo $promo['id']; ?>">
                <div class="row align-items-center gx-3">
                    <div class="col-12 col-lg-3 mb-2 mb-lg-0">
                        <h6 class="fw-bold mb-1" style="font-family:'Plus Jakarta Sans', sans-serif; color:#0F172A;">
                            <?php echo htmlspecialchars($promo['title']); ?>
                        </h6>
                        <?php
                            $today = new DateTime(); $start = new DateTime($promo['start_date']);
                            $end = $promo['end_date'] ? new DateTime($promo['end_date']) : null;
                            if ($today < $start) {
                                echo '<span class="badge bg-info"><i class="bi bi-clock"></i> Akan Datang</span>';
                            } elseif ($end === null || ($today >= $start && $today <= $end)) {
                                echo '<span class="badge bg-success"><i class="bi bi-play-circle-fill"></i> Berlangsung</span>';
                            } else {
                                echo '<span class="badge bg-secondary"><i class="bi bi-check-circle"></i> Berakhir</span>';
                            }
                        ?>
                    </div>
                    <div class="col-6 col-lg-2">
                        <div class="platform-tag">
                            <i class="bi bi-shop text-primary"></i> <?php echo htmlspecialchars($promo['platform']); ?>
                        </div>
                        <div class="mt-1">
                            <span class="badge bg-light text-dark border fw-semibold"><?php echo htmlspecialchars($promo['type']); ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-lg-1 text-center">
                        <?php if (!empty($promo['product_list'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-container="body" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="top" data-bs-html="true" data-bs-content="<?php echo htmlspecialchars(nl2br(str_replace(',', "\n", $promo['product_list']))); ?>">
                                <i class="bi bi-box-seam-fill"></i>
                            </button>
                        <?php else: echo '<span class="text-muted small">-</span>'; endif; ?>
                    </div>
                    <div class="col-12 col-lg-2 mb-2 mb-lg-0">
                        <div class="small fw-semibold text-dark">
                            <i class="bi bi-calendar-range text-muted me-1"></i>
                            <?php echo date('d M Y', strtotime($promo['start_date'])); ?>
                        </div>
                        <div class="small text-muted" style="font-size:11.5px;">
                            s/d <?php echo $promo['end_date'] ? date('d M Y', strtotime($promo['end_date'])) : 'Open-ended'; ?>
                        </div>
                    </div>
                    <div class="col-6 col-lg-2">
                        <a href="#" class="text-decoration-none budget-link" data-id="<?php echo $promo['id']; ?>" data-title="<?php echo htmlspecialchars($promo['title']); ?>">
                            <div class="budget-badge">
                                <strong class="d-block" style="font-size:14px;">Rp <?php echo number_format($promo['latest_budget'] ?? 0, 0, ',', '.'); ?></strong>
                                <?php
                                if ($promo['end_date']) {
                                    $duration = $end->diff($start)->days + 1;
                                    $total_budget = ($promo['latest_budget'] ?? 0) * $duration;
                                    echo '<small style="font-size:11px; opacity:0.85;">Est: Rp ' . number_format($total_budget, 0, ',', '.') . '</small>';
                                } else {
                                    echo '<small style="font-size:11px; opacity:0.85;">Open-ended</small>';
                                }
                                ?>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-lg-2 text-center">
                        <div class="d-flex justify-content-center gap-1 py-1">
                            <a href="#" class="btn btn-sm btn-outline-success budget-link" data-id="<?php echo $promo['id']; ?>" data-title="<?php echo htmlspecialchars($promo['title']); ?>" title="Riwayat Budget"><i class="bi bi-cash-stack"></i></a>
                            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="<?php echo $promo['id']; ?>" title="Edit"><i class="bi bi-pencil-fill"></i></button>
                            <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $promo['id']; ?>" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold" id="modalTitle">Tambah Promosi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="promoForm">
                    <input type="hidden" name="action" id="form_action">
                    <input type="hidden" name="promo_id" id="promo_id">
                    <div class="mb-3">
                        <label for="title" class="form-label">Nama Promosi</label>
                        <input type="text" class="form-control" name="title" id="title" placeholder="mis. FLASH SALE WIFI CAMERA" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Jenis Promosi</label>
                            <select name="type" id="type" class="form-select" required>
                                <option value="Ads Hard Selling">Ads Hard Selling</option>
                                <option value="Ads Soft Selling">Ads Soft Selling</option>
                                <option value="Diskon Produk">Diskon Produk</option>
                                <option value="Diskon Toko">Diskon Toko</option>
                                <option value="Cashback">Cashback</option>
                                <option value="Voucher Toko">Voucher Toko</option>
                                <option value="Voucher Produk">Voucher Produk</option>
                                <option value="Flash Sale">Flash Sale</option>
                                <option value="GMV">GMV</option>
                                <option value="Promo Lainnya">Promo Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="platform" class="form-label">Platform</label>
                            <select name="platform" id="platform" class="form-select" required>
                                <option value="Tokopedia">Tokopedia</option>
                                <option value="Shopee">Shopee</option>
                                <option value="Google Ads">Google Ads</option>
                                <option value="Meta">Meta</option>
                                <option value="Tiktok Ads">Tiktok Ads</option>
                                <option value="Dealer">Dealer</option>
                                <option value="Instaler">Instaler</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="product_list" class="form-label">List Product (Opsional)</label>
                        <textarea class="form-control" name="product_list" id="product_list" rows="3" placeholder="Contoh: Kamera A, NVR B, Adaptor C"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" name="start_date" id="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Tanggal Selesai (Kosongkan jika Open-ended)</label>
                            <input type="date" class="form-control" name="end_date" id="end_date">
                        </div>
                    </div>
                    <div class="mb-3" id="initial_budget_container">
                        <label for="daily_budget" class="form-label">Budget Harian Awal</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" name="daily_budget" id="daily_budget" value="0" min="0" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="promoForm" class="btn btn-primary"><i class="bi bi-save-fill"></i> Simpan Promosi</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="budgetHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold" id="budgetModalTitle">Riwayat Budget</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-history me-2 text-primary"></i>Riwayat Perubahan Budget</h6>
                <div class="table-responsive mb-4" style="max-height: 220px;">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark-header">
                            <tr>
                                <th>Tanggal Efektif</th>
                                <th>Jumlah Budget</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="budget-history-list"></tbody>
                    </table>
                </div>
                <hr>
                <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Tambah / Ubah Budget Harian</h6>
                <form id="budgetForm">
                    <input type="hidden" name="action" value="save_budget_entry">
                    <input type="hidden" name="promotion_id" id="budget_promotion_id">
                    <input type="hidden" name="budget_id" id="budget_id">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="effective_date" class="form-label">Tanggal Efektif</label>
                            <input type="date" class="form-control" name="effective_date" id="effective_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="budget_amount" class="form-label">Jumlah Budget Harian</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="budget_amount" id="budget_amount" value="0" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill"></i> Simpan Budget</button>
                        <button type="button" id="clearBudgetForm" class="btn btn-secondary">Batal Edit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    new bootstrap.Popover(document.body, { selector: '[data-bs-toggle="popover"]' });
    const promoModal = new bootstrap.Modal($('#promoModal')[0]);
    const budgetModal = new bootstrap.Modal($('#budgetHistoryModal')[0]);

    $('#btn-add-new').on('click', function() {
        $('#promoForm')[0].reset();
        $('#modalTitle').text('Tambah Promosi Baru');
        $('#form_action').val('add_promo');
        $('#promo_id').val('');
        $('#initial_budget_container').show();
        $('#end_date').prop('required', false);
        promoModal.show();
    });

    $('#promo-list-container').on('click', '.btn-edit', async function() {
        const id = $(this).data('id');
        const response = await fetch(`ajax_promosi_handler.php?action=get_promo_details&id=${id}`);
        const data = await response.json();
        if (data.success) {
            const d = data.data;
            $('#modalTitle').text('Edit Promosi');
            $('#form_action').val('update_promo');
            $('#promo_id').val(d.id);
            $('#title').val(d.title);
            $('#type').val(d.type);
            $('#product_list').val(d.product_list);
            $('#platform').val(d.platform);
            $('#start_date').val(d.start_date);
            $('#end_date').val(d.end_date);
            $('#initial_budget_container').hide();
            promoModal.show();
        } else { Swal.fire('Error', data.message, 'error'); }
    });
    
    $('#promo-list-container').on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Anda yakin?', text: "Promosi ini akan dihapus.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Batal', confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_promosi_handler.php', { action: 'delete_promo', promo_id: id }, function(data) {
                    if (data.success) { $(`#promo-row-${id}`).remove(); }
                    else { Swal.fire('Error', data.message, 'error'); }
                }, 'json');
            }
        });
    });

    $('#promoForm').on('submit', function(e) { e.preventDefault(); $.ajax({
        type: 'POST', url: 'ajax_promosi_handler.php', data: $(this).serialize(), dataType: 'json',
        success: function(data) {
            if (data.success) {
                promoModal.hide();
                Swal.fire({ icon: 'success', title: 'Berhasil!', timer: 1500, showConfirmButton: false })
                .then(() => window.location.reload());
            } else { Swal.fire('Error', data.message, 'error'); }
        }
    }); });

    $('#liveSearchInput').on('keyup', function() {
        const filter = $(this).val().toLowerCase();
        $('.promo-item').each(function() {
            $(this).toggle($(this).text().toLowerCase().includes(filter));
        });
    });

    $('#promo-list-container').on('click', '.budget-link', function(e) {
        e.preventDefault();
        const promoId = $(this).data('id');
        const promoTitle = $(this).data('title');
        $('#budgetModalTitle').text('Riwayat Budget untuk: ' + promoTitle);
        $('#budget_promotion_id').val(promoId);
        loadBudgetHistory(promoId);
        budgetModal.show();
    });

    async function loadBudgetHistory(promoId) {
        const response = await fetch(`ajax_promosi_handler.php?action=get_budget_history&promo_id=${promoId}`);
        const data = await response.json();
        const list = $('#budget-history-list');
        list.empty();
        if (data.success && data.data.length > 0) {
            data.data.forEach(b => {
                const formattedAmount = 'Rp ' + parseFloat(b.budget_amount).toLocaleString('id-ID');
                list.append(`
                    <tr id="budget-row-${b.id}">
                        <td class="fw-semibold text-dark">${b.effective_date}</td>
                        <td class="fw-bold text-primary">${formattedAmount}</td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-warning btn-edit-budget" data-id="${b.id}" data-amount="${b.budget_amount}" data-date="${b.effective_date}"><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-delete-budget" data-id="${b.id}"><i class="bi bi-trash-fill"></i></button>
                            </div>
                        </td>
                    </tr>
                `);
            });
        } else {
            list.append('<tr><td colspan="3" class="text-center text-muted p-4">Belum ada riwayat budget.</td></tr>');
        }
        $('#budgetForm')[0].reset();
        $('#budget_id').val('');
    }

    $('#budgetForm').on('submit', function(e) { e.preventDefault(); $.ajax({
        type: 'POST', url: 'ajax_promosi_handler.php', data: $(this).serialize(), dataType: 'json',
        success: function(data) {
            if (data.success) {
                loadBudgetHistory($('#budget_promotion_id').val());
            } else { Swal.fire('Error', data.message, 'error'); }
        }
    }); });

    $('#clearBudgetForm').on('click', function() {
        $('#budgetForm')[0].reset();
        $('#budget_id').val('');
    });

    $('#budgetHistoryModal').on('click', '.btn-edit-budget', function() {
        $('#budget_id').val($(this).data('id'));
        $('#budget_amount').val($(this).data('amount'));
        $('#effective_date').val($(this).data('date'));
    });

    $('#budgetHistoryModal').on('click', '.btn-delete-budget', function() {
        const budgetId = $(this).data('id');
        const promoId = $('#budget_promotion_id').val();
        Swal.fire({
            title: 'Hapus entri budget?', text: "Tindakan ini tidak bisa dibatalkan.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Batal', confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_promosi_handler.php', { action: 'delete_budget_entry', budget_id: budgetId }, function(data) {
                    if (data.success) { loadBudgetHistory(promoId); }
                    else { Swal.fire('Error', data.message, 'error'); }
                }, 'json');
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>