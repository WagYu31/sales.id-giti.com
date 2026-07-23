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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-megaphone-fill"></i> Manajemen Promosi</h1>
    <div>
        <input type="text" id="liveSearchInput" class="form-control d-inline-block w-auto me-2" placeholder="Cari promosi...">
        <button class="btn btn-primary" id="btn-add-new"><i class="bi bi-plus-circle"></i> Tambah Promosi</button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="promo-list-container">
            <div class="d-none d-lg-flex row gx-3 text-muted small fw-bold mb-2 px-2">
                <div class="col-lg-3">NAMA PROMOSI</div>
                <div class="col-lg-2">PLATFORM</div>
                <div class="col-lg-1 text-center">PRODUK</div>
                <div class="col-lg-2">DURASI & STATUS</div>
                <div class="col-lg-2">BUDGET</div>
                <div class="col-lg-2">AKSI</div>
            </div>

            <?php if (empty($promotions)): ?>
                <div class="text-center p-5 border rounded"><h5>Belum ada data promosi.</h5></div>
            <?php endif; ?>

            <?php foreach ($promotions as $promo): ?>
            <div class="card card-body mb-2 shadow-sm promo-item">
                <div class="row align-items-center gx-3">
                    <div class="col-12 col-lg-3 mb-2 mb-lg-0">
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($promo['title']); ?></h6>
                        <?php
                            $today = new DateTime(); $start = new DateTime($promo['start_date']);
                            $end = $promo['end_date'] ? new DateTime($promo['end_date']) : null;
                            if ($today < $start) {
                                echo '<span class="badge bg-primary" style="font-weight:500; font-size:12px;">Akan Datang</span>';
                            } elseif ($end === null || ($today >= $start && $today <= $end)) {
                                echo '<span class="badge bg-success" style="font-weight:500; font-size:12px;">Berlangsung</span>';
                            } else {
                                echo '<span class="badge bg-secondary" style="font-weight:500; font-size:12px;">Berakhir</span>';
                            }
                        ?>
                    </div>
                    <div class="col-6 col-lg-2">
                        <span class="fw-bold"><?php echo htmlspecialchars($promo['platform']); ?></span><br>
                        <small class="badge bg-light text-muted border" style="font-weight:400; font-size:12px;"><?php echo htmlspecialchars($promo['type']); ?></small>
                    </div>
                    <div class="col-6 col-lg-1 text-center">
                        <?php if (!empty($promo['product_list'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-container="body" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="top" data-bs-html="true" data-bs-content="<?php echo htmlspecialchars(nl2br(str_replace(',', "\n", $promo['product_list']))); ?>">
                                <i class="bi bi-box-seam"></i>
                            </button>
                        <?php else: echo '-'; endif; ?>
                    </div>
                    <div class="col-12 col-lg-2 mb-2 mb-lg-0">
                        <div class="small">
                            <?php echo date('d M Y', strtotime($promo['start_date'])); ?> - 
                            <?php echo $promo['end_date'] ? date('d M Y', strtotime($promo['end_date'])) : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="col-6 col-lg-2">
                        <a href="#" class="text-decoration-none budget-link" data-id="<?php echo $promo['id']; ?>" data-title="<?php echo htmlspecialchars($promo['title']); ?>">
                            <strong class="d-block">Rp <?php echo number_format($promo['latest_budget'] ?? 0, 0, ',', '.'); ?></strong>
                        </a>
                        <?php
                        if ($promo['end_date']) {
                            $duration = $end->diff($start)->days + 1;
                            $total_budget = ($promo['latest_budget'] ?? 0) * $duration;
                            echo '<small class="text-muted" style="font-weight:400; font-size:12px;">Est: Rp ' . number_format($total_budget, 0, ',', '.') . '</small>';
                        } else {
                            echo '<small class="text-muted" style="font-weight:400; font-size:12px;">Open-ended</small>';
                        }
                        ?>
                    </div>
                    <div class="col-6 col-lg-2 text-start">
                        <div class="btn-group btn-group-xs py-1" role="group">
                            <a href="#" class="btn btn-outline-success budget-link" data-id="<?php echo $promo['id']; ?>" data-title="<?php echo htmlspecialchars($promo['title']); ?>" title="Riwayat Budget"><i class="bi bi-cash-stack"></i></a>
                            <button class="btn btn-outline-warning btn-edit" data-id="<?php echo $promo['id']; ?>" title="Edit"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-outline-danger btn-delete" data-id="<?php echo $promo['id']; ?>" title="Hapus"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">Tambah Promosi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="promoForm">
                    <input type="hidden" name="action" id="form_action">
                    <input type="hidden" name="promo_id" id="promo_id">
                    <div class="mb-3"><label for="title" class="form-label">Nama Promosi</label><input type="text" class="form-control" name="title" id="title" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label for="type" class="form-label">Jenis Promosi</label><select name="type" id="type" class="form-select" required><option value="Ads Hard Selling">Ads Hard Selling</option><option value="Ads Soft Selling">Ads Soft Selling</option><option value="Diskon Produk">Diskon Produk</option><option value="Diskon Toko">Diskon Toko</option><option value="Cashback">Cashback</option><option value="Voucher Toko">Voucher Toko</option><option value="Voucher Produk">Voucher Produk</option><option value="Flash Sale">Flash Sale</option><option value="GMV">GMV</option><option value="Promo Lainnya">Promo Lainnya</option></select></div>
                        <div class="col-md-6 mb-3"><label for="platform" class="form-label">Platform</label><select name="platform" id="platform" class="form-select" required><option value="Tokopedia">Tokopedia</option><option value="Shopee">Shopee</option><option value="Google Ads">Google Ads</option><option value="Meta">Meta</option><option value="Tiktok Ads">Tiktok Ads</option><option value="Dealer">Dealer</option><option value="Instaler">Instaler</option></select></div>
                    </div>
                    <div class="mb-3"><label for="product_list" class="form-label">List Product (Opsional)</label><textarea class="form-control" name="product_list" id="product_list" rows="3" placeholder="Contoh: Kamera A, NVR B, Adaptor C"></textarea></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label for="start_date" class="form-label">Tanggal Mulai</label><input type="date" class="form-control" name="start_date" id="start_date" required></div>
                        <div class="col-md-6 mb-3"><label for="end_date" class="form-label">Tanggal Selesai (Kosongkan jika N/A)</label><input type="date" class="form-control" name="end_date" id="end_date"></div>
                    </div>
                    <div class="mb-3" id="initial_budget_container"><label for="daily_budget" class="form-label">Budget Harian Awal</label><div class="input-group"><span class="input-group-text">Rp</span><input type="number" class="form-control" name="daily_budget" id="daily_budget" value="0" min="0" required></div></div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" form="promoForm" class="btn btn-primary">Simpan</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="budgetHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="budgetModalTitle">Riwayat Budget</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <h6>Riwayat</h6>
                <div class="table-responsive mb-4" style="max-height: 200px;"><table class="table table-sm"><thead><tr><th>Tanggal Efektif</th><th>Jumlah Budget</th><th>Aksi</th></tr></thead><tbody id="budget-history-list"></tbody></table></div>
                <hr>
                <h6>Tambah / Ubah Budget</h6>
                <form id="budgetForm">
                    <input type="hidden" name="action" value="save_budget_entry">
                    <input type="hidden" name="promotion_id" id="budget_promotion_id">
                    <input type="hidden" name="budget_id" id="budget_id">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label for="effective_date" class="form-label">Tanggal Efektif</label><input type="date" class="form-control" name="effective_date" id="effective_date" required></div>
                        <div class="col-md-6 mb-3"><label for="budget_amount" class="form-label">Jumlah Budget Harian</label><div class="input-group"><span class="input-group-text">Rp</span><input type="number" class="form-control" name="budget_amount" id="budget_amount" value="0" min="0" required></div></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Budget</button>
                    <button type="button" id="clearBudgetForm" class="btn btn-secondary">Batal Edit</button>
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
                    if (data.success) { $(`#promo-row-${id}`).closest('.promo-item').remove(); }
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
                        <td>${b.effective_date}</td>
                        <td>${formattedAmount}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-warning btn-edit-budget" data-id="${b.id}" data-amount="${b.budget_amount}" data-date="${b.effective_date}"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger btn-delete-budget" data-id="${b.id}"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `);
            });
        } else {
            list.append('<tr><td colspan="3" class="text-center">Belum ada riwayat.</td></tr>');
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