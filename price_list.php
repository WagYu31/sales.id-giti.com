<?php
$page_title = 'Price List Loewix';
require_once 'includes/db.php';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-tags-fill"></i> Price List Produk</h1>
    <button class="btn btn-success" id="btn-open-add">
        <i class="bi bi-plus-circle"></i> Tambah Produk
    </button>
</div>

<div class="card shadow-sm mb-4 border-0 bg-light">
    <div class="card-body">
        <h5 class="card-title mb-3"><i class="bi bi-gear-fill"></i> Pengaturan Diskon (%)</h5>
        <form id="settingsForm" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Dealer Discount (%)</label>
                <input type="number" step="0.01" name="dealer_discount" id="set_dealer" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Master Dealer Discount (%)</label>
                <input type="number" step="0.01" name="master_dealer_discount" id="set_master" class="form-control" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-save"></i> Update Diskon
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4 border-0">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-9">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari kategori, tipe, atau spesifikasi...">
            </div>
            <div class="col-md-3">
                <select id="limitSelect" class="form-select">
                    <option value="25">25 per halaman</option>
                    <option value="50">50 per halaman</option>
                    <option value="100">100 per halaman</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>KATEGORI</th>
                    <th>TIPE & DESKRIPSI</th>
                    <th class="text-end">MSRP</th>
                    <th class="text-end text-primary">DEALER</th>
                    <th class="text-end text-success">MASTER DEALER</th>
                    <th class="text-center">AKSI</th>
                </tr>
            </thead>
            <tbody id="priceTableBody">
                <!--<tr><td colspan="6" class="text-center p-5"><div class="spinner-border text-primary"></div></td></tr>-->
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white border-top">
        <nav><ul class="pagination justify-content-center mb-0" id="paginationNav"></ul></nav>
    </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" id="modalHeader">
                <h5 class="modal-title" id="modalTitle">Form Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="form_action">
                    <input type="hidden" name="product_id" id="product_id">
                    <div class="mb-3"><label class="form-label fw-bold">Kategori</label><input type="text" name="category" id="category" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label fw-bold">Tipe Produk</label><input type="text" name="type" id="type" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label fw-bold">Deskripsi</label><textarea name="description" id="description" class="form-control" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label fw-bold">Harga User (MSRP)</label><div class="input-group"><span class="input-group-text">Rp</span><input type="number" name="msrp" id="msrp" class="form-control" required></div></div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPage = 1;
let pModal; // Deklarasikan dulu tanpa isi

function loadTable() {
    const s = $('#searchInput').val();
    const l = $('#limitSelect').val();
    
    $.ajax({
        url: 'ajax_price_handler.php',
        type: 'GET',
        data: { action: 'get_prices', search: s, limit: l, page: currentPage },
        dataType: 'json',
        success: function(res) {
            $('#priceTableBody').html(res.html);
            let htmlPg = '';
            if(res.pagination.total_pages > 1) {
                for (let i = 1; i <= res.pagination.total_pages; i++) {
                    htmlPg += `<li class="page-item ${i == res.pagination.current_page ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changePage(${i})">${i}</a></li>`;
                }
            }
            $('#paginationNav').html(htmlPg);
        },
        error: function(xhr) {
            $('#priceTableBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data. Cek koneksi atau file handler.</td></tr>');
            console.error(xhr.responseText);
        }
    });
}

function changePage(p) { currentPage = p; loadTable(); }

$(document).ready(function() {
    // Inisialisasi modal di sini setelah semua library (Bootstrap) termuat
    pModal = new bootstrap.Modal(document.getElementById('productModal'));
    
    loadTable();
    
    let debounceTimer;
    $('#searchInput').on('keyup', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            currentPage = 1;
            loadTable();
        }, 300);
    });

    $('#limitSelect').on('change', function() { currentPage = 1; loadTable(); });

    $('#btn-open-add').on('click', function() {
        $('#productForm')[0].reset();
        $('#product_id').val('');
        $('#form_action').val('add_product');
        $('#modalTitle').text('Tambah Produk Baru');
        $('#modalHeader').attr('class', 'modal-header bg-success text-white');
        $('#btn-submit').attr('class', 'btn btn-success').text('Simpan Produk');
        pModal.show();
    });

    $('#priceTableBody').on('click', '.btn-edit', function() {
        const id = $(this).data('id');
        $.getJSON('ajax_price_handler.php', { action: 'get_product_details', id: id }, function(res) {
            if(res.success) {
                const d = res.data;
                $('#product_id').val(d.id); 
                $('#category').val(d.category); 
                $('#type').val(d.type); 
                $('#description').val(d.description); 
                $('#msrp').val(d.msrp);
                $('#form_action').val('update_product');
                $('#modalTitle').text('Edit Produk');
                $('#modalHeader').attr('class', 'modal-header bg-warning text-dark');
                $('#btn-submit').attr('class', 'btn btn-warning').text('Update Produk');
                pModal.show();
            }
        });
    });

    $('#priceTableBody').on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        Swal.fire({ title: 'Hapus data?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!' }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_price_handler.php', { action: 'delete_product', id: id }, function(res) {
                    if(res.success) { loadTable(); Swal.fire('Berhasil!', '', 'success'); }
                }, 'json');
            }
        });
    });

    $('#productForm').on('submit', function(e) {
        e.preventDefault();
        $.post('ajax_price_handler.php', $(this).serialize(), function(res) {
            if(res.success) { pModal.hide(); loadTable(); Swal.fire('Berhasil!', '', 'success'); }
        }, 'json');
    });
    
    // Ambil nilai diskon saat halaman dimuat
    $.get('ajax_price_handler.php', { action: 'get_settings' }, function(res) {
        if(res.success) {
            $('#set_dealer').val(res.data.dealer_discount);
            $('#set_master').val(res.data.master_dealer_discount);
        }
    }, 'json');
    
    // Simpan perubahan diskon
    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        $.post('ajax_price_handler.php', { 
            action: 'update_settings', 
            dealer_discount: $('#set_dealer').val(), 
            master_dealer_discount: $('#set_master').val() 
        }, function(res) {
            if(res.success) {
                Swal.fire('Berhasil!', 'Persentase diskon diperbarui.', 'success');
                loadTable(); // Refresh tabel untuk melihat perubahan harga
            }
        }, 'json');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>