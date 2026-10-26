<?php
$page_title = 'Price List Loewix';
require_once 'includes/db.php';
require_once 'includes/sales_order_helper.php';
ensureSalesOrderTables($conn);
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

/* =========================================================================
   SPEC & BUNDLE MODAL STYLES (EXECUTIVE POLISHED REDESIGN)
   ========================================================================= */
#specModal .modal-content {
    border-radius: 22px !important;
    border: none !important;
    overflow: hidden !important;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.4) !important;
}
#specModal .modal-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%) !important;
    padding: 16px 24px !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}
#specModal .modal-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(147, 197, 253, 0.3);
    color: #60a5fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
#specModal .spec-product-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 18px;
}
#specModal .bundle-card {
    border: 1.5px solid #bfdbfe !important;
    border-radius: 14px !important;
    overflow: hidden !important;
    background: #ffffff;
    box-shadow: 0 4px 16px -4px rgba(37, 99, 235, 0.08);
}
#specModal .bundle-card-header {
    background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
    border-bottom: 1.5px solid #dbeafe;
    padding: 11px 16px;
}
/* Crucial: override global style.css table thead rules */
#specModal table.spec-bundle-table {
    margin-bottom: 0 !important;
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    background: #ffffff !important;
}
#specModal table.spec-bundle-table thead,
#specModal table.spec-bundle-table thead tr,
#specModal table.spec-bundle-table thead th {
    background: #f8fafc !important;
    color: #475569 !important;
    font-family: inherit !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase !important;
    padding: 9px 12px !important;
    border-top: none !important;
    border-bottom: 1.5px solid #e2e8f0 !important;
    white-space: nowrap !important;
    vertical-align: middle !important;
}
#specModal table.spec-bundle-table tbody tr {
    background: #ffffff !important;
    transition: background 0.15s ease !important;
}
#specModal table.spec-bundle-table tbody tr:hover {
    background: #f0f7ff !important;
}
#specModal table.spec-bundle-table tbody tr:not(:last-child) td {
    border-bottom: 1px solid #f1f5f9 !important;
}
#specModal table.spec-bundle-table td {
    padding: 10px 12px !important;
    font-size: 12.5px !important;
    color: #1e293b !important;
    vertical-align: middle !important;
    border-top: none !important;
}
#specModal .desc-card {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 16px;
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
            <p class="price-hero-subtitle">Katalog daftar harga resmi produk Loewix.</p>
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
    <div class="col-md-4">
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
    <div class="col-md-4">
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
    <div class="col-md-4">
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
                        <th style="width: 22%;">KATEGORI</th>
                        <th style="width: 48%;">TIPE PRODUK</th>
                        <th class="text-end" style="width: 18%;">HARGA (MSRP)</th>
                        <th class="text-center" style="width: 12%;">AKSI</th>
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
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label fw-bold text-dark" style="font-size: 13px;">Nama / Tipe Produk</label>
                            <input type="text" name="type" id="type" class="form-control" placeholder="mis. ACCESS POINT RUIJIE RG-RAP2200E" required style="border-radius: 9px;">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-bold text-dark" style="font-size: 13px;">Kode / SKU #</label>
                            <input type="text" name="item_code" id="item_code" class="form-control font-monospace" placeholder="8800xxx" style="border-radius: 9px;">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label fw-bold text-dark" style="font-size: 13px;">Deskripsi / Catatan Tambahan</label>
                            <input type="text" name="description" id="description" class="form-control" placeholder="Jenis INV / spesifikasi khusus..." style="border-radius: 9px;">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-bold text-dark" style="font-size: 13px;">Satuan</label>
                            <input type="text" name="unit" id="unit" class="form-control" placeholder="UNIT / PCS / MTR / ROLL" value="UNIT" style="border-radius: 9px;">
                        </div>
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

<!-- Modal Detail Spesifikasi Produk (Accurate Style) -->
<!-- Modal Detail Spesifikasi Produk (Executive Redesign) -->
<div class="modal fade" id="specModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="modal-header-icon">
                        <i class="bi bi-box-seam-fill" id="specModalHeaderIcon"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0" id="specModalTitle" style="font-size: 16px; letter-spacing: -0.01em;">Detail &amp; Rincian Produk</h6>
                        <span class="text-white-50 small" style="font-size: 11.5px;">Katalog resmi produk Loewix • Komponen, harga, dan fitur</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body p-4 bg-white">
                <!-- Product Overview Card -->
                <div class="spec-product-card mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge" id="specModalCategory" style="background: rgba(37,99,235,0.08); color: #1d4ed8; border: 1px solid rgba(37,99,235,0.25); font-size: 11px; padding: 5px 10px; font-weight: 700; border-radius: 8px;">
                                KATEGORI
                            </span>
                            <span class="badge bg-light text-secondary border font-monospace" id="specModalCodeBadge" style="font-size: 11px; padding: 5px 9px;">
                                SKU: -
                            </span>
                        </div>
                        <div class="text-end">
                            <span class="text-xs text-muted d-block fw-semibold text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Harga MSRP</span>
                            <span class="font-monospace fw-bold text-dark" id="specModalPrice" style="font-size: 17px; color: #0f172a;">
                                Rp 0
                            </span>
                        </div>
                    </div>
                    <h5 class="fw-bold text-dark mb-0" id="specModalType" style="font-size: 17px; line-height: 1.4;">
                        Tipe Produk
                    </h5>
                </div>

                <!-- Rincian Komponen Isi Paket (Bundle Package Items) -->
                <div id="specModalBundleWrapper" class="mb-3" style="display:none;">
                    <div class="bundle-card">
                        <div class="bundle-card-header d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <span class="d-inline-flex align-items-center justify-content-center text-primary" style="width: 24px; height: 24px; border-radius: 6px; background: rgba(37,99,235,0.1);">
                                    <i class="bi bi-box-seam-fill" style="font-size: 13px;"></i>
                                </span>
                                <span class="fw-bold text-dark" style="font-size: 13px;">Daftar Komponen Isi Paket</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill font-monospace" id="specModalBundleCount" style="font-size: 11px; padding: 4px 10px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                                    0 Item
                                </span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="spec-bundle-table">
                                <thead>
                                    <tr>
                                        <th style="width: 45px; text-align: center;">NO</th>
                                        <th style="width: 105px;">KODE SKU</th>
                                        <th>NAMA BARANG / KOMPONEN</th>
                                        <th style="width: 70px; text-align: center;">QTY</th>
                                        <th style="width: 75px; text-align: center;">SATUAN</th>
                                    </tr>
                                </thead>
                                <tbody id="specModalBundleBody">
                                    <!-- Dynamic bundle rows injected here -->
                                </tbody>
                                <tfoot id="specModalBundleFoot" style="background: #f8fafc; border-top: 1.5px solid #e2e8f0;">
                                    <tr>
                                        <td colspan="3" class="ps-3 text-uppercase fw-bold text-muted font-monospace" style="font-size: 11px;">
                                            Total Unit Komponen
                                        </td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill font-monospace fw-bold" id="specModalBundleTotalQty" style="background: #1e293b; color: #ffffff; font-size: 11.5px; padding: 3px 9px;">0</span>
                                        </td>
                                        <td class="text-center text-muted font-monospace fw-semibold" style="font-size: 11px;">
                                            UNIT
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Spesifikasi Box -->
                <div class="desc-card" id="specModalDescBox">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1.5 border-bottom" style="border-color: #e2e8f0 !important;">
                        <span class="text-xs text-muted fw-bold text-uppercase d-flex align-items-center gap-1.5" style="letter-spacing: 0.04em;">
                            <i class="bi bi-file-text-fill text-primary"></i> Rincian Spesifikasi &amp; Fitur
                        </span>
                        <button type="button" class="btn btn-sm btn-link text-primary p-0 text-decoration-none fw-bold d-inline-flex align-items-center gap-1" style="font-size: 11px;" id="btnCopySpec" title="Salin Spesifikasi">
                            <i class="bi bi-clipboard"></i> <span>Salin</span>
                        </button>
                    </div>
                    <div id="specModalDesc" class="text-dark" style="font-size: 13px; line-height: 1.6; white-space: pre-wrap; word-break: break-word;">
                        -
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-top px-4 py-2.5 bg-light d-flex align-items-center justify-content-between" style="border-color: #f1f5f9 !important;">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-1.5" id="btnCopyAllDetails" style="font-size: 12px; height: 36px;">
                    <i class="bi bi-copy"></i> Salin Semua Rincian
                </button>
                <button type="button" class="btn btn-sm btn-dark rounded-pill px-4 fw-bold" data-bs-dismiss="modal" style="font-size: 12px; height: 36px;">
                    Tutup
                </button>
            </div>
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

    $('#priceTableBody').html('<tr><td colspan="4" class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><div class="text-xs text-muted fw-bold">Memuat daftar produk katalog...</div></td></tr>');

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
                $('#priceTableBody').html('<tr><td colspan="4" class="text-center text-danger p-4">Gagal memuat data.</td></tr>');
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
            $('#priceTableBody').html('<tr><td colspan="4" class="text-center text-danger p-4">Gagal memuat data. Cek koneksi atau file handler.</td></tr>');
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
                $('#item_code').val(d.item_code || ''); 
                $('#description').val(d.description || ''); 
                $('#unit').val(d.unit || 'UNIT'); 
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

    // Accurate-Style Info Icon Click
    let currentSpecText = '';
    const specModal = new bootstrap.Modal(document.getElementById('specModal'));

    $('#priceTableBody').on('click', '.btn-desc-info', function() {
        const type = $(this).data('type') || '-';
        const category = $(this).data('category') || '-';
        const price = $(this).data('price') || '-';
        const desc = $(this).data('desc') || 'Tidak ada catatan spesifikasi tambahan untuk produk ini.';
        const code = $(this).data('code') || '';
        const isPkg = $(this).data('is-pkg') == '1';

        $('#specModalType').text(type);
        $('#specModalCategory').html('<i class="bi bi-tag-fill me-1 opacity-75"></i>' + escapeHtml(category));
        $('#specModalPrice').text(price);
        $('#specModalDesc').text(desc);

        if (code) {
            $('#specModalCodeBadge').text('SKU: ' + code).show();
        } else {
            $('#specModalCodeBadge').hide();
        }

        // Reset bundle view
        $('#specModalBundleWrapper').hide();
        $('#specModalBundleBody').empty();
        $('#specModalBundleFoot').hide();
        $('#specModalBundleCount').text('0 Item');

        currentSpecText = `${type}\nKategori: ${category}\nHarga: ${price}\n\nSpesifikasi:\n${desc}`;

        // Jika merupakan produk paket, panggil AJAX untuk mengambil rincian isi paket
        if (isPkg || category.toUpperCase().includes('PAKET') || type.toUpperCase().includes('PAKET')) {
            $('#specModalHeaderIcon').attr('class', 'bi bi-box-seam-fill');
            $('#specModalTitle').text('Rincian Paket & Spesifikasi Produk');
            $('#specModalBundleWrapper').show();
            $('#specModalBundleBody').html('<tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Memuat rincian komponen paket...</td></tr>');

            $.getJSON('ajax_price_handler.php', {
                action: 'get_bundle_items',
                code: code,
                type: type
            }, function(res) {
                if (res && res.has_bundle && res.items && res.items.length > 0) {
                    $('#specModalBundleCount').text(res.items.length + ' Item');
                    let bundleRowsHtml = '';
                    let totalQty = 0;
                    let bundleText = `\n\n--- RINCIAN KOMPONEN ISI PAKET (${res.items.length} ITEM) ---`;

                    res.items.forEach((item, idx) => {
                        const itemCode = escapeHtml(item.item_code || '-');
                        let cleanName = (item.item_name || '').trim();
                        if (cleanName.startsWith('--')) {
                            cleanName = cleanName.substring(2).trim();
                        }
                        cleanName = escapeHtml(cleanName);

                        const itemQty = parseInt(item.qty, 10) || 1;
                        const itemUnit = escapeHtml(item.unit || 'UNIT');
                        totalQty += itemQty;

                        bundleText += `\n${idx + 1}. [${item.item_code}] ${item.item_name} (${itemQty} ${itemUnit})`;

                        bundleRowsHtml += `
                            <tr>
                                <td class="text-center font-monospace fw-semibold text-muted" style="font-size: 11.5px;">${idx + 1}</td>
                                <td>
                                    <span class="badge font-monospace fw-bold" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 11px; padding: 4px 8px; border-radius: 6px;">
                                        ${itemCode}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark" style="font-size: 13px; line-height: 1.4;">${cleanName}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill font-monospace fw-bold" style="background: #dbeafe; color: #1e40af; font-size: 12px; padding: 4px 10px;">
                                        ${itemQty}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-secondary border font-monospace text-uppercase" style="font-size: 10.5px; padding: 4px 8px;">
                                        ${itemUnit}
                                    </span>
                                </td>
                            </tr>
                        `;
                    });

                    $('#specModalBundleBody').html(bundleRowsHtml);
                    $('#specModalBundleTotalQty').text(totalQty);
                    $('#specModalBundleFoot').show();
                    currentSpecText += bundleText;
                } else {
                    $('#specModalBundleCount').text('0 Item');
                    $('#specModalBundleFoot').hide();
                    $('#specModalBundleBody').html(`
                        <tr>
                            <td colspan="5" class="text-center py-3 text-muted" style="font-size: 12px;">
                                <i class="bi bi-info-circle me-1"></i> Rincian komponen isi paket belum didefinisikan secara khusus untuk kode produk ini.
                            </td>
                        </tr>
                    `);
                }
            }).fail(function() {
                $('#specModalBundleFoot').hide();
                $('#specModalBundleBody').html(`
                    <tr>
                        <td colspan="5" class="text-center py-3 text-danger" style="font-size: 12px;">
                            <i class="bi bi-exclamation-triangle me-1"></i> Gagal memuat rincian isi paket.
                        </td>
                    </tr>
                `);
            });
        } else {
            $('#specModalHeaderIcon').attr('class', 'bi bi-info-circle-fill');
            $('#specModalTitle').text('Detail Spesifikasi Barang');
        }

        specModal.show();
    });

    // Copy spec button
    $('#btnCopySpec').on('click', function() {
        navigator.clipboard.writeText(currentSpecText).then(() => {
            const btn = $(this);
            const orig = btn.html();
            btn.html('<i class="bi bi-check-lg text-success me-1"></i> Tersalin!');
            setTimeout(() => btn.html(orig), 2000);
        });
    });

    // Copy all details button in footer
    $('#btnCopyAllDetails').on('click', function() {
        navigator.clipboard.writeText(currentSpecText).then(() => {
            const btn = $(this);
            const orig = btn.html();
            btn.html('<i class="bi bi-check-lg text-success me-1"></i> Rincian Lengkap Tersalin!');
            setTimeout(() => btn.html(orig), 2000);
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