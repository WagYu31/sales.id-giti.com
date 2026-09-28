<?php
$page_title = 'Price List Loewix';
require_once 'includes/db.php';
require_once 'includes/header.php';
?>

<style>
/* Hero Header */
.price-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 30px 36px;
    margin-bottom: 24px;
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

/* Stat Overview Cards */
.stat-card-taste {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px 20px;
    box-shadow: 0 4px 15px -3px rgba(15, 23, 42, 0.04);
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    gap: 16px;
}
.stat-card-taste:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
    border-color: #cbd5e1;
}
.stat-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

/* Horizontal Category Chips Bar */
.category-chips-scroll {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 6px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}
.category-chips-scroll::-webkit-scrollbar {
    height: 5px;
}
.category-chips-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.cat-chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 15px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
    background: #ffffff;
    color: #475569;
    border: 1.5px solid #e2e8f0;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.cat-chip-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}
.cat-chip-btn.active {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
    color: #ffffff;
    border-color: #1d4ed8;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
}
.cat-chip-btn .badge-count {
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 20px;
    background: #f1f5f9;
    color: #475569;
    font-weight: 800;
}
.cat-chip-btn.active .badge-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Advanced Filter Card */
.filter-card-taste {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 18px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.05);
}
.filter-label-taste {
    font-size: 11.5px;
    font-weight: 800;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-input-taste {
    height: 42px;
    border-radius: 10px;
    border: 1.5px solid #cbd5e1;
    font-size: 13.5px;
    font-weight: 600;
    color: #0f172a;
    transition: all 0.2s ease;
}
.filter-input-taste:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

/* Active Tag Pills */
.active-filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.active-filter-tag .btn-close-tag {
    cursor: pointer;
    opacity: 0.6;
    transition: 0.15s;
    font-size: 14px;
    line-height: 1;
}
.active-filter-tag .btn-close-tag:hover {
    opacity: 1;
    color: #dc2626;
}

.table-dark-header th {
    background: #0f172a;
    color: #f8fafc;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border: none;
    padding: 14px 16px;
}
.product-row:hover {
    background-color: #f8fafc;
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
            <p class="price-hero-subtitle">Katalog daftar harga resmi produk, perhitungan otomatis diskon Dealer &amp; Master Dealer.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-primary shadow-lg" id="btn-open-add" style="border-radius: 12px; font-weight: 700; padding: 10px 20px;">
                <i class="bi bi-plus-circle-fill me-1.5"></i> Tambah Produk Baru
            </button>
        </div>
    </div>
</div>

<!-- Stat Overview Ribbon -->
<div class="row g-3 mb-4" id="statsRibbon">
    <div class="col-6 col-lg-3">
        <div class="stat-card-taste">
            <div class="stat-icon-wrapper" style="background: rgba(37, 99, 235, 0.1); color: #2563eb;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <div>
                <div class="text-xs text-muted fw-bold text-uppercase">Total Produk</div>
                <div class="h4 fw-bold text-dark mb-0 font-monospace" id="statTotalProducts">-</div>
                <div class="small text-muted" style="font-size: 11.5px;">SKU aktif terdaftar</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-taste">
            <div class="stat-icon-wrapper" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
                <i class="bi bi-grid-fill"></i>
            </div>
            <div>
                <div class="text-xs text-muted fw-bold text-uppercase">Kategori Produk</div>
                <div class="h4 fw-bold text-dark mb-0 font-monospace" id="statTotalCategories">-</div>
                <div class="small text-muted" style="font-size: 11.5px;">Grup lini produk</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-taste">
            <div class="stat-icon-wrapper" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                <i class="bi bi-tags-fill"></i>
            </div>
            <div>
                <div class="text-xs text-muted fw-bold text-uppercase">Rentang Harga (MSRP)</div>
                <div class="fw-bold text-dark mb-0 font-monospace" style="font-size: 14.5px;" id="statPriceRange">-</div>
                <div class="small text-muted" style="font-size: 11.5px;">Harga katalog resmi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-taste">
            <div class="stat-icon-wrapper" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <i class="bi bi-percent"></i>
            </div>
            <div>
                <div class="text-xs text-muted fw-bold text-uppercase">Diskon Khusus Mitra</div>
                <div class="fw-bold text-dark mb-0 font-monospace" style="font-size: 14.5px;">
                    <span class="text-primary" id="statDealerDisc">D: 20%</span> | <span class="text-success" id="statMasterDisc">MD: 35%</span>
                </div>
                <div class="small text-muted" style="font-size: 11.5px;">Margin laba otomatis</div>
            </div>
        </div>
    </div>
</div>

<!-- Category Quick Chips Bar -->
<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="filter-label-taste mb-0">
            <i class="bi bi-funnel-fill text-primary"></i> Filter Kategori Cepat:
        </div>
        <span class="text-muted" style="font-size: 12px; font-weight: 600;" id="chipActiveLabel">Semua Kategori Aktif</span>
    </div>
    <div class="category-chips-scroll" id="categoryChipsContainer">
        <button type="button" class="cat-chip-btn active" data-category="">
            <span>Semua Kategori</span>
            <span class="badge-count" id="chipCountAll">0</span>
        </button>
        <!-- Injected dynamically via AJAX -->
    </div>
</div>

<!-- Advanced Multi-Criteria Filter Card -->
<div class="filter-card-taste">
    <div class="row g-3 align-items-end">
        <!-- Search Input -->
        <div class="col-lg-4 col-md-6">
            <label class="filter-label-taste">
                <i class="bi bi-search text-primary"></i> Pencarian Produk
            </label>
            <div class="position-relative">
                <input type="text" id="searchInput" class="form-control filter-input-taste ps-4" placeholder="Ketik tipe, kategori, atau spek...">
                <i class="bi bi-search position-absolute text-muted" style="left: 12px; top: 13px; font-size: 13px;"></i>
                <button type="button" id="btnClearSearch" class="btn btn-sm position-absolute text-muted d-none" style="right: 6px; top: 6px; padding: 3px 7px;" title="Bersihkan pencarian">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
        </div>

        <!-- Filter Dropdown Kategori -->
        <div class="col-lg-3 col-md-6">
            <label class="filter-label-taste">
                <i class="bi bi-grid text-primary"></i> Pilih Kategori
            </label>
            <select id="filterCategory" class="form-select filter-input-taste">
                <option value="">Semua Kategori</option>
            </select>
        </div>

        <!-- Filter Rentang Harga -->
        <div class="col-lg-2 col-md-4">
            <label class="filter-label-taste">
                <i class="bi bi-cash text-success"></i> Rentang Harga
            </label>
            <select id="filterPriceRange" class="form-select filter-input-taste">
                <option value="">Semua Harga</option>
                <option value="under_50k">&lt; Rp 50.000</option>
                <option value="50k_150k">Rp 50k - Rp 150k</option>
                <option value="150k_500k">Rp 150k - Rp 500k</option>
                <option value="above_500k">&gt; Rp 500.000</option>
            </select>
        </div>

        <!-- Filter Urutkan (Sorting) -->
        <div class="col-lg-2 col-md-4">
            <label class="filter-label-taste">
                <i class="bi bi-sort-down text-primary"></i> Urutan
            </label>
            <select id="filterSort" class="form-select filter-input-taste">
                <option value="default">Kategori &amp; Tipe (A-Z)</option>
                <option value="price_asc">Harga Termurah (MSRP)</option>
                <option value="price_desc">Harga Tertinggi (MSRP)</option>
                <option value="type_asc">Nama Tipe (A-Z)</option>
                <option value="type_desc">Nama Tipe (Z-A)</option>
                <option value="newest">Terbaru Ditambahkan</option>
            </select>
        </div>

        <!-- Tombol Reset Filter -->
        <div class="col-lg-1 col-md-4">
            <button type="button" id="btnResetFilters" class="btn btn-outline-secondary w-100 filter-input-taste d-flex align-items-center justify-content-center gap-1.5 fw-bold" title="Reset Semua Filter">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span class="d-lg-none">Reset</span>
            </button>
        </div>
    </div>

    <!-- Active Filters & Summary Strip -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-3 border-top" id="activeFilterBar">
        <div class="d-flex align-items-center gap-2 flex-wrap" id="activeTagContainer">
            <span class="text-xs text-muted fw-bold text-uppercase me-1">Filter Aktif:</span>
            <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 11.5px;" id="noFilterBadge">Tidak ada filter aktif (Menampilkan semua)</span>
            <!-- Injected dynamic tags -->
        </div>
        <div class="text-xs text-muted fw-bold" id="resultCountText">
            Memuat data produk...
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card border-0 shadow-sm" style="border-radius: 18px; overflow: hidden; border: 1.5px solid #e2e8f0 !important;">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 px-4 py-3 bg-white border-bottom" style="border-color: #e2e8f0 !important;">
        <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 16px;">
            <i class="bi bi-tags-fill text-primary"></i> 
            <span>Daftar Produk Katalog</span>
        </h5>
        <div class="d-flex align-items-center gap-2">
            <span class="text-xs text-muted fw-bold">Tampilkan:</span>
            <select id="limitSelect" class="form-select form-select-sm" style="width: 120px; font-weight: 600; border-radius: 8px;">
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
                        <th style="width: 18%;">KATEGORI</th>
                        <th style="width: 32%;">TIPE &amp; DESKRIPSI</th>
                        <th class="text-end" style="width: 15%;">MSRP (USER)</th>
                        <th class="text-end" style="width: 15%;">DEALER</th>
                        <th class="text-end" style="width: 15%;">MASTER DEALER</th>
                        <th class="text-center" style="width: 5%;">AKSI</th>
                    </tr>
                </thead>
                <tbody id="priceTableBody">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-color: #e2e8f0 !important;">
        <div class="text-xs text-muted fw-bold" id="paginationInfo">
            Menampilkan data produk
        </div>
        <nav><ul class="pagination justify-content-center mb-0" id="paginationNav"></ul></nav>
    </div>
</div>

<!-- Modal Form Produk -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
            <div class="modal-header px-4 py-3" id="modalHeader" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold" id="modalTitle">Form Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="form_action">
                    <input type="hidden" name="product_id" id="product_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark" style="font-size: 13px;">Kategori Produk</label>
                        <input type="text" name="category" id="category" class="form-control" placeholder="mis. 2MP AHD INDOOR, RECORDER DVR" required style="border-radius: 9px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark" style="font-size: 13px;">Tipe Produk</label>
                        <input type="text" name="type" id="type" class="form-control" placeholder="mis. LX-502-AHD" required style="border-radius: 9px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark" style="font-size: 13px;">Deskripsi Spesifikasi</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Model, resolusi, fitur, dsb..." style="border-radius: 9px;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark" style="font-size: 13px;">Harga User (MSRP)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold" style="border-radius: 9px 0 0 9px;">Rp</span>
                            <input type="number" name="msrp" id="msrp" class="form-control font-monospace fw-bold" required placeholder="0" style="border-radius: 0 9px 9px 0;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 9px; font-weight: 600;">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit" style="border-radius: 9px; font-weight: 700;">
                        <i class="bi bi-save-fill me-1"></i> Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPage = 1;
let currentCategory = '';
let currentPriceRange = '';
let currentSort = 'default';
let pModal;
let initialCategoriesLoaded = false;

function loadTable() {
    const s = $('#searchInput').val().trim();
    const l = $('#limitSelect').val();
    
    // Toggle clear search button
    if (s.length > 0) {
        $('#btnClearSearch').removeClass('d-none');
    } else {
        $('#btnClearSearch').addClass('d-none');
    }

    $('#priceTableBody').html('<tr><td colspan="6" class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><div class="text-xs text-muted fw-bold">Memuat daftar produk katalog...</div></td></tr>');

    $.ajax({
        url: 'ajax_price_handler.php',
        type: 'GET',
        data: { 
            action: 'get_prices', 
            search: s, 
            category: currentCategory,
            price_range: currentPriceRange,
            sort: currentSort,
            limit: l, 
            page: currentPage 
        },
        dataType: 'json',
        success: function(res) {
            if (!res.success) {
                $('#priceTableBody').html('<tr><td colspan="6" class="text-center text-danger p-4">Gagal memuat data.</td></tr>');
                return;
            }

            $('#priceTableBody').html(res.html);

            // Update Pagination
            let htmlPg = '';
            if (res.pagination.total_pages > 1) {
                // Prev button
                if (res.pagination.current_page > 1) {
                    htmlPg += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="changePage(${res.pagination.current_page - 1})">«</a></li>`;
                }
                for (let i = 1; i <= res.pagination.total_pages; i++) {
                    htmlPg += `<li class="page-item ${i == res.pagination.current_page ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changePage(${i})">${i}</a></li>`;
                }
                // Next button
                if (res.pagination.current_page < res.pagination.total_pages) {
                    htmlPg += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="changePage(${res.pagination.current_page + 1})">»</a></li>`;
                }
            }
            $('#paginationNav').html(htmlPg);

            // Update Pagination Info
            const totalRows = res.pagination.total_rows || 0;
            const startRow = totalRows > 0 ? ((res.pagination.current_page - 1) * res.pagination.limit) + 1 : 0;
            const endRow = Math.min(startRow + (res.pagination.limit - 1), totalRows);
            $('#paginationInfo').text(totalRows > 0 ? `Menampilkan ${startRow} - ${endRow} dari ${totalRows} produk` : '0 produk ditemukan');
            $('#resultCountText').text(`Ditemukan ${totalRows} produk`);

            // Update Stats Ribbon
            if (res.stats) {
                $('#statTotalProducts').text(res.stats.total_products + ' Item');
                $('#statTotalCategories').text(res.stats.total_categories + ' Kategori');
                const minPriceFmt = new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(res.stats.min_msrp);
                const maxPriceFmt = new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(res.stats.max_msrp);
                $('#statPriceRange').text(`Rp ${minPriceFmt} - Rp ${maxPriceFmt}`);
                $('#statDealerDisc').text(`D: ${res.stats.dealer_discount}%`);
                $('#statMasterDisc').text(`MD: ${res.stats.master_dealer_discount}%`);
                $('#chipCountAll').text(res.stats.total_products);
            }

            // Populate category dropdown and chips once or when categories returned
            if (res.categories) {
                renderCategories(res.categories);
            }

            // Render Active Filter Badges
            renderActiveFilterTags(s, totalRows);
        },
        error: function(xhr) {
            $('#priceTableBody').html('<tr><td colspan="6" class="text-center text-danger p-4">Gagal memuat data. Cek koneksi atau file handler.</td></tr>');
            console.error(xhr.responseText);
        }
    });
}

function renderCategories(categories) {
    // 1. Update Horizontal Chips
    let chipsHtml = `
        <button type="button" class="cat-chip-btn ${currentCategory === '' ? 'active' : ''}" data-category="">
            <span>Semua Kategori</span>
            <span class="badge-count">${categories.reduce((acc, c) => acc + c.count, 0)}</span>
        </button>
    `;

    // 2. Update Dropdown Options
    let dropdownHtml = '<option value="">Semua Kategori</option>';

    categories.forEach(c => {
        const isActive = (currentCategory === c.name);
        chipsHtml += `
            <button type="button" class="cat-chip-btn ${isActive ? 'active' : ''}" data-category="${escapeHtml(c.name)}">
                <span>${escapeHtml(c.name)}</span>
                <span class="badge-count">${c.count}</span>
            </button>
        `;
        dropdownHtml += `<option value="${escapeHtml(c.name)}" ${isActive ? 'selected' : ''}>${escapeHtml(c.name)} (${c.count})</option>`;
    });

    $('#categoryChipsContainer').html(chipsHtml);

    // Only update dropdown if user hasn't selected another option locally
    const currentValInDropdown = $('#filterCategory').val();
    $('#filterCategory').html(dropdownHtml);
    if (currentCategory) {
        $('#filterCategory').val(currentCategory);
    }

    $('#chipActiveLabel').text(currentCategory ? `Kategori: ${currentCategory}` : 'Semua Kategori Aktif');
}

function renderActiveFilterTags(searchVal, totalCount) {
    let tags = [];

    if (searchVal) {
        tags.push({
            type: 'search',
            label: `Pencarian: "${escapeHtml(searchVal)}"`,
            clearFn: "clearSearchFilter()"
        });
    }

    if (currentCategory) {
        tags.push({
            type: 'category',
            label: `Kategori: ${escapeHtml(currentCategory)}`,
            clearFn: "clearCategoryFilter()"
        });
    }

    if (currentPriceRange) {
        const priceLabelMap = {
            'under_50k': '< Rp 50.000',
            '50k_150k': 'Rp 50k - Rp 150k',
            '150k_500k': 'Rp 150k - Rp 500k',
            'above_500k': '> Rp 500.000'
        };
        tags.push({
            type: 'price',
            label: `Harga: ${priceLabelMap[currentPriceRange] || currentPriceRange}`,
            clearFn: "clearPriceFilter()"
        });
    }

    if (currentSort !== 'default') {
        const sortLabelMap = {
            'price_asc': 'MSRP Termurah',
            'price_desc': 'MSRP Tertinggi',
            'type_asc': 'Tipe (A-Z)',
            'type_desc': 'Tipe (Z-A)',
            'newest': 'Terbaru'
        };
        tags.push({
            type: 'sort',
            label: `Urutan: ${sortLabelMap[currentSort] || currentSort}`,
            clearFn: "clearSortFilter()"
        });
    }

    if (tags.length === 0) {
        $('#activeTagContainer').html(`
            <span class="text-xs text-muted fw-bold text-uppercase me-1">Filter Aktif:</span>
            <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 11.5px;" id="noFilterBadge">Tidak ada filter aktif (Menampilkan semua)</span>
        `);
    } else {
        let tagHtml = '<span class="text-xs text-muted fw-bold text-uppercase me-1">Filter Aktif:</span>';
        tags.forEach(t => {
            tagHtml += `
                <span class="active-filter-tag">
                    ${t.label}
                    <span class="btn-close-tag" onclick="${t.clearFn}" title="Hapus filter">×</span>
                </span>
            `;
        });
        tagHtml += `
            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1 fw-bold text-decoration-none" style="font-size: 11.5px;" onclick="resetAllFilters()">
                Reset Semua
            </button>
        `;
        $('#activeTagContainer').html(tagHtml);
    }
}

function clearSearchFilter() {
    $('#searchInput').val('');
    currentPage = 1;
    loadTable();
}

function clearCategoryFilter() {
    currentCategory = '';
    $('#filterCategory').val('');
    $('.cat-chip-btn').removeClass('active');
    $('.cat-chip-btn[data-category=""]').addClass('active');
    currentPage = 1;
    loadTable();
}

function clearPriceFilter() {
    currentPriceRange = '';
    $('#filterPriceRange').val('');
    currentPage = 1;
    loadTable();
}

function clearSortFilter() {
    currentSort = 'default';
    $('#filterSort').val('default');
    currentPage = 1;
    loadTable();
}

function resetAllFilters() {
    $('#searchInput').val('');
    currentCategory = '';
    currentPriceRange = '';
    currentSort = 'default';
    $('#filterCategory').val('');
    $('#filterPriceRange').val('');
    $('#filterSort').val('default');
    $('#limitSelect').val('25');
    $('.cat-chip-btn').removeClass('active');
    $('.cat-chip-btn[data-category=""]').addClass('active');
    currentPage = 1;
    loadTable();
}

function changePage(p) { 
    currentPage = p; 
    loadTable(); 
    $('html, body').animate({ scrollTop: $('#statsRibbon').offset().top - 20 }, 200);
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

$(document).ready(function() {
    pModal = new bootstrap.Modal(document.getElementById('productModal'));
    
    loadTable();
    
    // Live Search with Debounce
    let debounceTimer;
    $('#searchInput').on('keyup', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            currentPage = 1;
            loadTable();
        }, 300);
    });

    $('#btnClearSearch').on('click', function() {
        clearSearchFilter();
    });

    // Category Chips Click Delegation
    $('#categoryChipsContainer').on('click', '.cat-chip-btn', function() {
        const cat = $(this).data('category') || '';
        currentCategory = cat;
        $('#filterCategory').val(cat);
        $('.cat-chip-btn').removeClass('active');
        $(this).addClass('active');
        currentPage = 1;
        loadTable();
    });

    // Category Dropdown Change
    $('#filterCategory').on('change', function() {
        currentCategory = $(this).val();
        $('.cat-chip-btn').removeClass('active');
        if (currentCategory === '') {
            $('.cat-chip-btn[data-category=""]').addClass('active');
        } else {
            $(`.cat-chip-btn[data-category="${currentCategory}"]`).addClass('active');
        }
        currentPage = 1;
        loadTable();
    });

    // Price Range Change
    $('#filterPriceRange').on('change', function() {
        currentPriceRange = $(this).val();
        currentPage = 1;
        loadTable();
    });

    // Sort Change
    $('#filterSort').on('change', function() {
        currentSort = $(this).val();
        currentPage = 1;
        loadTable();
    });

    // Limit Change
    $('#limitSelect').on('change', function() { 
        currentPage = 1; 
        loadTable(); 
    });

    // Reset Button Click
    $('#btnResetFilters').on('click', function() {
        resetAllFilters();
    });

    // Open Add Modal
    $('#btn-open-add').on('click', function() {
        $('#productForm')[0].reset();
        $('#product_id').val('');
        $('#form_action').val('add_product');
        $('#modalTitle').text('Tambah Produk Baru');
        $('#btn-submit').attr('class', 'btn btn-primary').html('<i class="bi bi-save-fill me-1"></i> Simpan Produk');
        pModal.show();
    });

    // Edit Product
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
                $('#modalTitle').text('Edit Produk: ' + d.type);
                $('#btn-submit').attr('class', 'btn btn-primary').html('<i class="bi bi-save-fill me-1"></i> Update Produk');
                pModal.show();
            }
        });
    });

    // Delete Product
    $('#priceTableBody').on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        Swal.fire({ 
            title: 'Hapus data produk ini?', 
            text: 'Data yang dihapus tidak dapat dipulihkan kembali.',
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#d33', 
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax_price_handler.php', { action: 'delete_product', id: id }, function(res) {
                    if(res.success) { 
                        loadTable(); 
                        Swal.fire('Berhasil!', 'Produk telah berhasil dihapus.', 'success'); 
                    }
                }, 'json');
            }
        });
    });

    // Submit Product Form
    $('#productForm').on('submit', function(e) {
        e.preventDefault();
        $.post('ajax_price_handler.php', $(this).serialize(), function(res) {
            if(res.success) { 
                pModal.hide(); 
                loadTable(); 
                Swal.fire('Berhasil!', 'Data produk berhasil disimpan.', 'success'); 
            } else {
                Swal.fire('Gagal!', 'Terjadi kendala saat menyimpan data.', 'error');
            }
        }, 'json');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>