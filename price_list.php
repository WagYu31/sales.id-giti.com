<?php
$page_title = 'Price List Loewix';
require_once 'includes/db.php';
require_once 'includes/header.php';
?>

<style>
.price-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.price-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.price-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.price-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.settings-box {
    background: linear-gradient(135deg, #F8FAFC 0%, #EFF6FF 100%);
    border: 1.5px solid #DBEAFE;
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 24px;
}
</style>

<!-- Hero Header -->
<div class="price-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Price List Produk</span>
            </div>
            <h1 class="price-hero-title">Price List Produk Loewix 🏷️</h1>
            <p class="price-hero-subtitle">Katalog daftar harga resmi produk, perhitungan otomatis diskon Dealer & Master Dealer.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-primary shadow-lg" id="btn-open-add">
                <i class="bi bi-plus-circle-fill"></i> Tambah Produk Baru
            </button>
        </div>
    </div>
</div>

<!-- Settings Box Card -->
<div class="settings-box">
    <h5 class="fw-bold mb-3 text-dark" style="font-family:'Plus Jakarta Sans', sans-serif; font-size:15px;">
        <i class="bi bi-gear-fill text-primary me-2"></i> Pengaturan Diskon (%)
    </h5>
    <form id="settingsForm" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Dealer Discount (%)</label>
            <div class="input-group">
                <input type="number" step="0.01" name="dealer_discount" id="set_dealer" class="form-control" required>
                <span class="input-group-text bg-white fw-bold">%</span>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Master Dealer Discount (%)</label>
            <div class="input-group">
                <input type="number" step="0.01" name="master_dealer_discount" id="set_master" class="form-control" required>
                <span class="input-group-text bg-white fw-bold">%</span>
            </div>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-save-fill"></i> Update Persentase Diskon
            </button>
        </div>
    </form>
</div>

<!-- Main Table Card -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0"><i class="bi bi-tags-fill"></i> Katalog Harga Produk</h5>
        <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0" style="max-width: 450px;">
            <div class="input-group flex-grow-1">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Cari kategori, tipe, atau spesifikasi...">
            </div>
            <select id="limitSelect" class="form-select" style="width: 130px;">
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
                <option value="100">100 / hal</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark-header">
                    <tr>
                        <th style="width: 15%;">KATEGORI</th>
                        <th style="width: 35%;">TIPE & DESKRIPSI</th>
                        <th class="text-end" style="width: 14%;">MSRP</th>
                        <th class="text-end" style="width: 14%;">DEALER</th>
                        <th class="text-end" style="width: 14%;">MASTER DEALER</th>
                        <th class="text-center" style="width: 8%;">AKSI</th>
                    </tr>
                </thead>
                <tbody id="priceTableBody">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top-0 py-3">
        <nav><ul class="pagination justify-content-center mb-0" id="paginationNav"></ul></nav>
    </div>
</div>

<!-- Modal Form Produk -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" id="modalHeader" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold" id="modalTitle">Form Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="form_action">
                    <input type="hidden" name="product_id" id="product_id">
                    <div class="mb-3">
                        <label class="form-label">Kategori Produk</label>
                        <input type="text" name="category" id="category" class="form-control" placeholder="mis. ACCESSORIES, IP CAM, DVR" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipe Produk</label>
                        <input type="text" name="type" id="type" class="form-control" placeholder="mis. ADAPTOR 12V-1A" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi Spesifikasi</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Model, tegangan, arus output, dsb..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga User (MSRP)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="msrp" id="msrp" class="form-control" required placeholder="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit"><i class="bi bi-save-fill"></i> Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPage = 1;
let pModal;

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
            $('#priceTableBody').html('<tr><td colspan="6" class="text-center text-danger p-4">Gagal memuat data. Cek koneksi atau file handler.</td></tr>');
            console.error(xhr.responseText);
        }
    });
}

function changePage(p) { currentPage = p; loadTable(); }

$(document).ready(function() {
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
        $('#btn-submit').attr('class', 'btn btn-primary').html('<i class="bi bi-save-fill"></i> Simpan Produk');
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
                $('#btn-submit').attr('class', 'btn btn-primary').html('<i class="bi bi-save-fill"></i> Update Produk');
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
                loadTable();
            }
        }, 'json');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>