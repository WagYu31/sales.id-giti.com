<?php
// customer_io.php
// Halaman Import Massal Database Customer via Excel (.xlsx/.xls/.csv) & Google Sheets
$page_title = 'Import Customer (Excel & Google Sheets)';
require_once 'includes/db.php';

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if ($userRole === 'sales') {
    header('Location: customer_io_sales.php');
    exit();
}
if (!in_array($userRole, ['superadmin', 'adminsales'], true)) {
    die("Akses ditolak. Halaman ini hanya untuk Superadmin / Admin Sales.");
}

// Ambil daftar sales aktif untuk dropdown Default Sales PIC & kartu referensi
$salesList = [];
$resSales = $conn->query("SELECT id, nama_lengkap, role FROM sales WHERE deleted_at IS NULL ORDER BY nama_lengkap ASC");
if ($resSales) {
    while ($row = $resSales->fetch_assoc()) {
        $salesList[] = $row;
    }
}

$totalExistingCust = 0;
$resCount = $conn->query("SELECT COUNT(*) AS cnt FROM customers WHERE deleted_at IS NULL");
if ($resCount && ($rCnt = $resCount->fetch_assoc())) {
    $totalExistingCust = (int)$rCnt['cnt'];
}

require_once 'includes/header.php';
?>

<style>
.io-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1d4ed8 100%);
    border-radius: 20px;
    padding: 28px 32px;
    margin-bottom: 24px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 35px -10px rgba(15, 23, 42, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.12);
}
.io-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -40px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.28) 0%, transparent 70%);
    pointer-events: none;
}
.io-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 12px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    font-size: 11.5px;
    font-weight: 700;
    color: #93c5fd;
    margin-bottom: 10px;
}
.io-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.4px;
    color: #FFFFFF;
}
.io-hero-subtitle {
    font-size: 13.5px;
    color: rgba(226, 232, 240, 0.9);
    margin: 0;
    max-width: 660px;
    line-height: 1.55;
}
.io-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.io-card-header {
    padding: 16px 22px;
    border-bottom: 1px solid #F1F5F9;
    background: #F8FAFC;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.io-card-title {
    font-size: 15px;
    font-weight: 800;
    color: #0F172A;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.io-source-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 20px;
}
.io-source-tab {
    border: 2px solid #E2E8F0;
    border-radius: 14px;
    padding: 14px 16px;
    background: #FFFFFF;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 12px;
}
.io-source-tab:hover {
    border-color: #93C5FD;
    background: #F8FAFF;
}
.io-source-tab.active-excel {
    border-color: #2563EB;
    background: #EFF6FF;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.12);
}
.io-source-tab.active-gsheet {
    border-color: #059669;
    background: #ECFDF5;
    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.14);
}
.io-tab-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.upload-dropzone {
    border: 2px dashed #93C5FD;
    background: #F8FAFF;
    border-radius: 16px;
    padding: 34px 24px;
    text-align: center;
    transition: all 0.25s ease;
    cursor: pointer;
}
.upload-dropzone:hover,
.upload-dropzone.dragover {
    background: #EFF6FF;
    border-color: #2563EB;
    transform: translateY(-1px);
}
.gsheet-panel {
    border: 2px solid #A7F3D0;
    background: #F0FDF4;
    border-radius: 16px;
    padding: 20px;
}
.io-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}
@media (max-width: 768px) {
    .io-source-tabs { grid-template-columns: 1fr; }
    .io-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
.io-kpi-box {
    border-radius: 14px;
    padding: 14px 16px;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
}
.io-kpi-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748B;
}
.io-kpi-val {
    font-size: 24px;
    font-weight: 800;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    line-height: 1.2;
    margin-top: 2px;
}
.col-spec-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px;
    border-bottom: 1px solid #F1F5F9;
    font-size: 12.5px;
    gap: 8px;
}
.col-spec-row:last-child { border-bottom: none; }
.col-code-pill {
    font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 11.5px;
    font-weight: 700;
    background: #F1F5F9;
    color: #1E293B;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    padding: 2px 7px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.col-code-pill:hover {
    background: #DBEAFE;
    border-color: #93C5FD;
    color: #1D4ED8;
}
.sales-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 999px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sales-chip:hover {
    background: #ECFDF5;
    border-color: #6EE7B7;
    color: #047857;
}
.preview-table-wrap {
    max-height: 400px;
    overflow: auto;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
}
.preview-table-wrap table {
    font-size: 12.5px;
    margin-bottom: 0;
}
.preview-table-wrap thead th {
    position: sticky;
    top: 0;
    background: #F8FAFC;
    z-index: 2;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #475569;
    white-space: nowrap;
}
#loading-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background-color: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: none;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    color: #FFFFFF;
}
</style>

<div id="loading-overlay">
    <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;"></div>
    <h5 class="mt-3 fw-bold mb-1" id="loading-title">Memproses data spreadsheet...</h5>
    <p class="small text-white-50 mb-0" id="loading-sub">Mohon tunggu, sedang membaca kolom dan memeriksa duplikat.</p>
</div>

<!-- HERO HEADER -->
<div class="io-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:2;">
        <div>
            <div class="io-breadcrumb">
                <a href="index.php" style="color:inherit; text-decoration:none;">Database Customer</a>
                <span>›</span>
                <span>Import Excel &amp; Google Sheets</span>
                <span class="ms-1 badge bg-white text-dark rounded-pill" style="font-size:10.5px;"><?php echo number_format($totalExistingCust); ?> Terdaftar</span>
            </div>
            <h1 class="io-hero-title"><i class="bi bi-file-earmark-spreadsheet-fill me-2 text-info"></i>Import Customer via Excel &amp; Google Sheets</h1>
            <p class="io-hero-subtitle">
                Tambah puluhan hingga ribuan data toko sekaligus. Mendukung upload file <strong>.XLSX / .XLS / .CSV</strong> maupun tarik langsung dari <strong>Link Google Spreadsheet</strong> dengan deteksi header cerdas &amp; anti-duplikat otomatis.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="customer_template_download.php?format=xlsx" class="btn fw-bold text-white shadow-sm d-inline-flex align-items-center gap-2" style="background: linear-gradient(135deg, #059669, #10B981); border:none; padding:10px 18px; border-radius:12px;">
                <i class="bi bi-file-earmark-excel-fill"></i> Unduh Template Excel (.XLSX)
            </a>
            <a href="customer_template_download.php?format=csv" class="btn fw-bold d-inline-flex align-items-center gap-2" style="background: rgba(255,255,255,0.14); color:#FFFFFF; border:1px solid rgba(255,255,255,0.28); padding:10px 16px; border-radius:12px;">
                <i class="bi bi-filetype-csv"></i> Template CSV
            </a>
            <a href="index.php" class="btn fw-bold d-inline-flex align-items-center gap-2" style="background:#FFFFFF; color:#0F172A; border:none; padding:10px 16px; border-radius:12px;">
                <i class="bi bi-arrow-left"></i> Database
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- KOLOM KIRI: FORM IMPORT + PRATINJAU -->
    <div class="col-lg-8">
        <div class="io-card mb-4">
            <div class="io-card-header">
                <h5 class="io-card-title">
                    <i class="bi bi-cloud-arrow-up-fill text-primary"></i>
                    Pilih Sumber Data Import
                </h5>
                <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2" style="border-radius:8px; font-size:11px;">
                    Maks. 5.000 Baris / Proses
                </span>
            </div>
            <div class="p-4">
                <form id="import-form" action="customer_bulk_upload_process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="source_type" id="source_type" value="file">

                    <!-- PILIHAN TAB SUMBER DATA -->
                    <div class="io-source-tabs">
                        <button type="button" class="io-source-tab active-excel" id="tabBtnFile" onclick="switchImportSource('file')">
                            <div class="io-tab-icon" style="background:#DBEAFE; color:#1D4ED8;">
                                <i class="bi bi-file-earmark-excel-fill"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size:14px;">Upload File Excel / CSV</div>
                                <div class="text-muted" style="font-size:12px;">File .xlsx, .xls, atau .csv dari komputer</div>
                            </div>
                        </button>
                        <button type="button" class="io-source-tab" id="tabBtnGsheet" onclick="switchImportSource('gsheet')">
                            <div class="io-tab-icon" style="background:#D1FAE5; color:#059669;">
                                <i class="bi bi-table"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size:14px;">Link Google Sheets Langsung</div>
                                <div class="text-muted" style="font-size:12px;">Tempel URL Google Spreadsheet tanpa download</div>
                            </div>
                        </button>
                    </div>

                    <!-- PANEL 1: UPLOAD FILE EXCEL / CSV -->
                    <div id="panelSourceFile" class="mb-4">
                        <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('customer_file').click();">
                            <div class="mb-2">
                                <i class="bi bi-cloud-arrow-up-fill text-primary" style="font-size: 46px;"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1" style="font-size:15px;">Klik untuk Pilih atau Tarik File ke Sini</h6>
                            <p class="text-muted small mb-0">Mendukung <strong>.XLSX</strong>, <strong>.XLS</strong>, dan <strong>.CSV</strong> (Maksimal 15 MB)</p>
                            <input class="d-none" type="file" id="customer_file" name="customer_file" accept=".xlsx,.xls,.csv" onchange="handleFileSelect(this)">
                            <div id="file-selected-badge" class="mt-3" style="display:none;">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-bold" style="font-size:12.5px; border-radius:10px;">
                                    <i class="bi bi-file-earmark-check-fill me-1"></i>
                                    <span id="file-name-text"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- PANEL 2: GOOGLE SHEETS URL -->
                    <div id="panelSourceGsheet" class="mb-4" style="display:none;">
                        <div class="gsheet-panel">
                            <label for="gsheet_url" class="form-label fw-bold text-success mb-2" style="font-size:13px;">
                                <i class="bi bi-link-45deg me-1"></i> Link Google Spreadsheet
                            </label>
                            <input type="url" class="form-control form-control-lg bg-white" id="gsheet_url" name="gsheet_url"
                                   placeholder="https://docs.google.com/spreadsheets/d/1ABCxyz.../edit?gid=0#gid=0"
                                   style="border-radius:12px; border:1.5px solid #6EE7B7; font-size:13.5px;">
                            <div class="mt-3 p-3 bg-white rounded-3 border border-success-subtle" style="font-size:12.5px; color:#334155;">
                                <div class="fw-bold text-success mb-1"><i class="bi bi-shield-check me-1"></i>Pastikan Izin Akses Google Sheets Sudah Dibuka:</div>
                                <ol class="mb-0 ps-3">
                                    <li>Buka Google Sheets Anda &rarr; klik tombol <strong>Bagikan (Share)</strong> di kanan atas.</li>
                                    <li>Pada bagian <strong>Akses Umum</strong>, ubah menjadi <strong>Siapa saja yang memiliki link</strong> (peran <strong>Pelihat / Viewer</strong>).</li>
                                    <li>Klik <strong>Salin link</strong>, lalu tempel pada kolom di atas. (Jika ada beberapa tab sheet, buka tab yang diinginkan dulu lalu salin URL lengkap yang ada <code>#gid=...</code>).</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- PENGATURAN IMPORT -->
                    <div class="p-3 rounded-4 border bg-light mb-4">
                        <div class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size:13px;">
                            <i class="bi bi-sliders text-primary"></i> Pengaturan &amp; Proteksi Anti-Duplikat
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary mb-1" style="font-size:12px;">Default Kategori (jika kolom kategori kosong)</label>
                                <select name="default_kategori" id="default_kategori" class="form-select" style="border-radius:10px; font-size:13px;">
                                    <option value="DEALER" selected>DEALER (Default)</option>
                                    <option value="INSTALLER">INSTALLER</option>
                                    <option value="MASTER DEALER">MASTER DEALER</option>
                                    <option value="USER">USER</option>
                                    <option value="">Biarkan Kosong</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary mb-1" style="font-size:12px;">Default Sales PIC (jika kolom sales_name kosong)</label>
                                <select name="default_sales_id" id="default_sales_id" class="form-select" style="border-radius:10px; font-size:13px;">
                                    <option value="">-- Umum (Tidak Di-assign) --</option>
                                    <?php foreach ($salesList as $sl): ?>
                                        <option value="<?php echo (int)$sl['id']; ?>">
                                            <?php echo htmlspecialchars($sl['nama_lengkap']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-3 pt-1">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="skip_duplicate_phone" name="skip_duplicate_phone" value="1" checked>
                                        <label class="form-check-label fw-semibold text-dark" for="skip_duplicate_phone" style="font-size:12.5px;">
                                            Lewati jika Nomor HP/WA sudah terdaftar
                                        </label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="skip_duplicate_store" name="skip_duplicate_store" value="1" checked>
                                        <label class="form-check-label fw-semibold text-dark" for="skip_duplicate_store" style="font-size:12.5px;">
                                            Lewati jika Nama Toko sudah terdaftar
                                        </label>
                                    </div>
                                </div>
                                <div class="text-muted mt-1" style="font-size:11.5px;">
                                    <i class="bi bi-info-circle me-1"></i>Tip: Dengan proteksi duplikat aktif, Anda bisa menambahkan baris baru di Google Sheets yang sama dan import ulang kapan saja — data lama otomatis dilewati, hanya data baru yang masuk.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TOMBOL AKSI -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <a href="customer_add.php" class="btn btn-light border fw-bold px-3" style="border-radius:10px;">
                            <i class="bi bi-person-plus me-1"></i> Input Manual 1 Customer
                        </a>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary fw-bold px-4" style="border-radius:10px;" onclick="runImportAction('preview')">
                                <i class="bi bi-eye-fill me-1"></i> Pratinjau &amp; Cek Data Dulu
                            </button>
                            <button type="button" class="btn btn-primary fw-bold px-4 shadow-sm" style="border-radius:10px;" onclick="runImportAction('import')">
                                <i class="bi bi-database-fill-add me-1"></i> Import ke Database Sekarang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- HASIL PRATINJAU / HASIL IMPORT -->
        <div class="io-card mb-4" id="importResultPanel" style="display:none;">
            <div class="io-card-header">
                <h5 class="io-card-title" id="resultPanelTitle">
                    <i class="bi bi-table text-primary"></i> Hasil Pratinjau Data
                </h5>
                <span class="badge bg-light text-dark border fw-bold" id="resultSourceMeta"></span>
            </div>
            <div class="p-4">
                <div id="resultBanner" class="mb-3"></div>

                <!-- 4 KPI RINGKASAN -->
                <div class="io-kpi-grid">
                    <div class="io-kpi-box" style="border-left:4px solid #10B981;">
                        <div class="io-kpi-label" id="kpiReadyLabel">Siap Masuk DB</div>
                        <div class="io-kpi-val text-success" id="kpiReadyVal">0</div>
                    </div>
                    <div class="io-kpi-box" style="border-left:4px solid #F59E0B;">
                        <div class="io-kpi-label">Duplikat Dilewati</div>
                        <div class="io-kpi-val text-warning" id="kpiDupVal">0</div>
                    </div>
                    <div class="io-kpi-box" style="border-left:4px solid #EF4444;">
                        <div class="io-kpi-label">Error / Kosong</div>
                        <div class="io-kpi-val text-danger" id="kpiErrVal">0</div>
                    </div>
                    <div class="io-kpi-box" style="border-left:4px solid #64748B;">
                        <div class="io-kpi-label">Contoh Template</div>
                        <div class="io-kpi-val text-secondary" id="kpiExVal">0</div>
                    </div>
                </div>

                <!-- KOLOM TERDETEKSI -->
                <div class="mb-3">
                    <div class="fw-bold text-secondary mb-2" style="font-size:11.5px; text-transform:uppercase; letter-spacing:0.04em;">
                        <i class="bi bi-check2-circle text-success me-1"></i>Kolom Sistem Terdeteksi Otomatis:
                    </div>
                    <div id="detectedHeadersChips" class="d-flex flex-wrap gap-2"></div>
                </div>

                <!-- TABEL PRATINJAU 40 BARIS PERTAMA -->
                <div class="preview-table-wrap mb-3">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Baris</th>
                                <th>Status</th>
                                <th>Nama Toko</th>
                                <th>Kategori</th>
                                <th>PIC</th>
                                <th>No. HP / WA</th>
                                <th>Kota</th>
                                <th>Sales PIC</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody"></tbody>
                    </table>
                </div>

                <!-- LOG BARIS DILEWATI -->
                <div id="issuesBox" class="alert alert-warning mb-3" style="display:none; border-radius:12px; font-size:12.5px;">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Daftar Baris Dilewati / Catatan (<span id="issuesCount">0</span>):</div>
                    <ul id="issuesList" class="mb-0 ps-3" style="max-height:160px; overflow:auto;"></ul>
                </div>

                <!-- BAR KONFIRMASI -->
                <div id="confirmImportBar" class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 rounded-3 bg-success-subtle border border-success-subtle" style="display:none !important;">
                    <div class="fw-bold text-success" style="font-size:13.5px;">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        <span id="confirmImportText">Siap menyimpan data ke database.</span>
                    </div>
                    <button type="button" class="btn btn-success fw-bold px-4 shadow-sm" style="border-radius:10px;" onclick="runImportAction('import')">
                        <i class="bi bi-cloud-check-fill me-1"></i> Konfirmasi &amp; Simpan ke Database
                    </button>
                </div>

                <div id="postImportBar" class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 rounded-3 bg-primary-subtle border border-primary-subtle" style="display:none !important;">
                    <div class="fw-bold text-primary" style="font-size:13.5px;" id="postImportText"></div>
                    <a href="index.php" class="btn btn-primary fw-bold px-4" style="border-radius:10px;">
                        <i class="bi bi-people-fill me-1"></i> Lihat di Database Customer
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- KOLOM KANAN: STRUKTUR TEMPLATE & DAFTAR SALES -->
    <div class="col-lg-4">
        <!-- STRUKTUR KOLOM TEMPLATE -->
        <div class="io-card mb-4">
            <div class="io-card-header">
                <h5 class="io-card-title">
                    <i class="bi bi-list-columns-reverse text-success"></i>
                    Format Kolom Template
                </h5>
                <button type="button" class="btn btn-sm btn-outline-success fw-bold" style="border-radius:8px; font-size:11.5px;" onclick="copyGoogleSheetsHeader()" title="Salin baris header lalu tekan Ctrl+V di sel A1 Google Sheets">
                    <i class="bi bi-clipboard-check me-1"></i> Salin Header
                </button>
            </div>
            <div class="p-3">
                <p class="text-muted mb-2" style="font-size:12px; line-height:1.5;">
                    Klik tombol <strong>Unduh Template Excel</strong> di atas, atau klik <strong>Salin Header</strong> lalu tempel (<code>Ctrl+V</code>) di sel <code>A1</code> Google Sheets baru. Urutan kolom juga fleksibel karena sistem membaca nama header otomatis.
                </p>
                <div class="border rounded-3 overflow-hidden">
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('nama_toko')">nama_toko</span> <span class="badge bg-danger ms-1" style="font-size:10px;">WAJIB</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">Nama toko / PT</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('kategori')">kategori</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">INSTALLER / DEALER / MD / USER</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('nama_pic')">nama_pic</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">Nama Owner / PIC</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('tlp_pic')">tlp_pic</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">No HP/WA (08.. / +62..)</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('alamat')">alamat</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">Alamat lengkap toko</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('kota')">kota</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">Kota / Kabupaten</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('provinsi')">provinsi</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">Provinsi</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('link_google_map')">link_google_map</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">URL Google Maps</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('sales_name')">sales_name</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">Nama Sales PIC</span>
                    </div>
                    <div class="col-spec-row">
                        <div><span class="col-code-pill" onclick="copyText('tgl_input')">tgl_input</span></div>
                        <span class="text-muted text-end" style="font-size:11.5px;">YYYY-MM-DD (otomatis)</span>
                    </div>
                </div>
                <div class="mt-3 p-2 rounded-3 bg-primary-subtle text-primary" style="font-size:11.5px; line-height:1.5;">
                    <i class="bi bi-magic me-1"></i><strong>Cerdas &amp; Aman:</strong> Format nomor HP <code>62812...</code>, <code>+62812...</code>, atau <code>812...</code> (angka 0 hilang di Excel) otomatis diperbaiki menjadi <code>0812...</code>!
                </div>
            </div>
        </div>

        <!-- DAFTAR NAMA SALES AKTIF -->
        <div class="io-card">
            <div class="io-card-header">
                <h5 class="io-card-title">
                    <i class="bi bi-person-badge-fill text-primary"></i>
                    Daftar Sales Aktif (<?php echo count($salesList); ?>)
                </h5>
                <span class="text-muted" style="font-size:11px;">Klik untuk salin</span>
            </div>
            <div class="p-3">
                <p class="text-muted mb-2" style="font-size:12px;">
                    Gunakan nama berikut untuk mengisi kolom <code>sales_name</code> (atau kosongkan agar berstatus Umum):
                </p>
                <div class="d-flex flex-wrap gap-2" style="max-height:220px; overflow-y:auto;">
                    <?php if (empty($salesList)): ?>
                        <span class="text-muted small">Belum ada data sales aktif.</span>
                    <?php else: ?>
                        <?php foreach ($salesList as $sl): ?>
                            <span class="sales-chip" onclick="copyText('<?php echo htmlspecialchars(addslashes($sl['nama_lengkap']), ENT_QUOTES); ?>')" title="Klik untuk menyalin nama sales">
                                <i class="bi bi-person-fill text-primary"></i>
                                <?php echo htmlspecialchars($sl['nama_lengkap']); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentSourceType = 'file';

function switchImportSource(type) {
    currentSourceType = type;
    document.getElementById('source_type').value = type;

    const btnFile = document.getElementById('tabBtnFile');
    const btnGsheet = document.getElementById('tabBtnGsheet');
    const panelFile = document.getElementById('panelSourceFile');
    const panelGsheet = document.getElementById('panelSourceGsheet');

    if (type === 'gsheet') {
        btnFile.classList.remove('active-excel');
        btnGsheet.classList.add('active-gsheet');
        panelFile.style.display = 'none';
        panelGsheet.style.display = 'block';
    } else {
        btnGsheet.classList.remove('active-gsheet');
        btnFile.classList.add('active-excel');
        panelGsheet.style.display = 'none';
        panelFile.style.display = 'block';
    }
}

function handleFileSelect(input) {
    const badge = document.getElementById('file-selected-badge');
    const text = document.getElementById('file-name-text');
    if (input.files && input.files[0]) {
        const f = input.files[0];
        const sizeKb = (f.size / 1024).toFixed(1);
        text.textContent = f.name + ' (' + sizeKb + ' KB)';
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}

// Drag & drop support
(function() {
    const dz = document.getElementById('dropzone');
    const fileInput = document.getElementById('customer_file');
    if (!dz || !fileInput) return;

    ['dragenter', 'dragover'].forEach(evt => {
        dz.addEventListener(evt, e => {
            e.preventDefault();
            e.stopPropagation();
            dz.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dz.addEventListener(evt, e => {
            e.preventDefault();
            e.stopPropagation();
            dz.classList.remove('dragover');
        });
    });
    dz.addEventListener('drop', e => {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect(fileInput);
        }
    });
})();

function copyText(str) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(str).then(() => showCopyToast('Disalin: ' + str));
    } else {
        const ta = document.createElement('textarea');
        ta.value = str;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showCopyToast('Disalin: ' + str);
    }
}

function copyGoogleSheetsHeader() {
    const headerTSV = ['nama_toko','kategori','nama_pic','tlp_pic','alamat','kota','provinsi','link_google_map','sales_name','tgl_input'].join('\t');
    copyText(headerTSV);
    Swal.fire({
        icon: 'success',
        title: 'Header Template Tersalin!',
        html: 'Buka <strong>Google Sheets</strong> baru, klik sel <code>A1</code>, lalu tekan <strong>Ctrl + V</strong> (atau <strong>Cmd + V</strong> di Mac) untuk langsung membuat 10 kolom resmi sistem.',
        timer: 3500,
        showConfirmButton: true
    });
}

function showCopyToast(msg) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: msg,
        showConfirmButton: false,
        timer: 1800
    });
}

function escapeHtml(val) {
    return String(val ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function runImportAction(mode) {
    const fileInput = document.getElementById('customer_file');
    const gsInput = document.getElementById('gsheet_url');

    if (currentSourceType === 'file') {
        if (!fileInput.files || fileInput.files.length === 0) {
            Swal.fire({ icon: 'warning', title: 'File Belum Dipilih', text: 'Silakan pilih file Excel (.xlsx/.xls) atau .csv terlebih dahulu.' });
            return;
        }
    } else {
        if (!gsInput.value.trim()) {
            Swal.fire({ icon: 'warning', title: 'Link Kosong', text: 'Silakan tempel link Google Sheets terlebih dahulu.' });
            return;
        }
    }

    const overlay = document.getElementById('loading-overlay');
    const loadTitle = document.getElementById('loading-title');
    const loadSub = document.getElementById('loading-sub');
    loadTitle.textContent = mode === 'preview' ? 'Membaca & Memvalidasi Data...' : 'Menyimpan Data ke Database...';
    loadSub.textContent = currentSourceType === 'gsheet'
        ? 'Mengunduh Google Sheets dan mencocokkan kolom serta duplikat.'
        : 'Memproses spreadsheet dan memeriksa duplikat nomor/toko.';
    overlay.style.display = 'flex';

    try {
        const fd = new FormData();
        fd.append('ajax', '1');
        fd.append('mode', mode);
        fd.append('source_type', currentSourceType);
        if (currentSourceType === 'file') {
            fd.append('customer_file', fileInput.files[0]);
        } else {
            fd.append('gsheet_url', gsInput.value.trim());
        }
        fd.append('default_kategori', document.getElementById('default_kategori').value);
        fd.append('default_sales_id', document.getElementById('default_sales_id').value);
        fd.append('skip_duplicate_phone', document.getElementById('skip_duplicate_phone').checked ? '1' : '0');
        fd.append('skip_duplicate_store', document.getElementById('skip_duplicate_store').checked ? '1' : '0');

        const resp = await fetch('customer_bulk_upload_process.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await resp.json();
        overlay.style.display = 'none';

        if (!data || !data.ok) {
            Swal.fire({
                icon: 'error',
                title: mode === 'preview' ? 'Gagal Membaca Data' : 'Import Gagal',
                html: escapeHtml(data && data.error ? data.error : 'Terjadi kesalahan saat memproses data.')
            });
            return;
        }

        renderResultPanel(data);

        if (data.mode === 'import') {
            const sum = data.summary || {};
            Swal.fire({
                icon: (sum.imported_count || 0) > 0 ? 'success' : 'info',
                title: (sum.imported_count || 0) > 0 ? 'Import Berhasil!' : 'Tidak Ada Data Baru',
                html: '<strong>' + escapeHtml(data.message || '') + '</strong>' +
                      (sum.duplicate_count > 0 ? '<br><span class="text-muted small">' + sum.duplicate_count + ' baris duplikat otomatis dilewati.</span>' : '')
            });
        }
    } catch (err) {
        overlay.style.display = 'none';
        Swal.fire({
            icon: 'error',
            title: 'Koneksi / Server Error',
            text: 'Gagal memproses permintaan: ' + err.message
        });
    }
}

function renderResultPanel(data) {
    const panel = document.getElementById('importResultPanel');
    const sum = data.summary || {};
    const isImport = data.mode === 'import';

    document.getElementById('resultPanelTitle').innerHTML = isImport
        ? '<i class="bi bi-check-circle-fill text-success"></i> Ringkasan Hasil Import'
        : '<i class="bi bi-eye-fill text-primary"></i> Pratinjau &amp; Validasi Data (Belum Disimpan)';
    document.getElementById('resultSourceMeta').textContent = (sum.source_label || '') + ' • Sheet: ' + (sum.sheet_name || 'Sheet1');

    document.getElementById('kpiReadyLabel').textContent = isImport ? 'Berhasil Masuk DB' : 'Siap Masuk DB';
    document.getElementById('kpiReadyVal').textContent = isImport ? (sum.imported_count || 0) : (sum.ready_count || 0);
    document.getElementById('kpiDupVal').textContent = sum.duplicate_count || 0;
    document.getElementById('kpiErrVal').textContent = sum.error_count || 0;
    document.getElementById('kpiExVal').textContent = sum.example_count || 0;

    // Detected headers chips
    const chipsEl = document.getElementById('detectedHeadersChips');
    chipsEl.innerHTML = '';
    const detected = data.detected_headers || {};
    Object.keys(detected).forEach(field => {
        const span = document.createElement('span');
        span.className = 'badge bg-success-subtle text-success border border-success-subtle px-2 py-1';
        span.style.fontSize = '11.5px';
        span.innerHTML = '<i class="bi bi-check-lg me-1"></i>' + escapeHtml(field) + ' <span class="text-muted">(' + escapeHtml(detected[field]) + ')</span>';
        chipsEl.appendChild(span);
    });

    // Preview rows table
    const tbody = document.getElementById('previewTableBody');
    tbody.innerHTML = '';
    const rows = data.preview_rows || [];
    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Tidak ada baris data ditemukan di bawah baris header.</td></tr>';
    } else {
        rows.forEach(r => {
            let statusBadge = '<span class="badge bg-success">Siap Masuk</span>';
            if (r.status === 'valid' && isImport) statusBadge = '<span class="badge bg-success">Tersimpan</span>';
            else if (r.status === 'duplicate') statusBadge = '<span class="badge bg-warning text-dark">Duplikat</span>';
            else if (r.status === 'error') statusBadge = '<span class="badge bg-danger">Error</span>';
            else if (r.status === 'example') statusBadge = '<span class="badge bg-secondary">Contoh</span>';

            const tr = document.createElement('tr');
            if (r.status === 'duplicate') tr.className = 'table-warning';
            else if (r.status === 'error') tr.className = 'table-danger';
            else if (r.status === 'example') tr.className = 'table-light text-muted';

            tr.innerHTML =
                '<td class="fw-bold text-muted">#' + escapeHtml(r.row_num) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td class="fw-semibold">' + escapeHtml(r.nama_toko) + '</td>' +
                '<td><span class="badge bg-light text-dark border">' + escapeHtml(r.kategori || '-') + '</span></td>' +
                '<td>' + escapeHtml(r.nama_pic) + '</td>' +
                '<td><code>' + escapeHtml(r.tlp_pic) + '</code></td>' +
                '<td>' + escapeHtml(r.kota) + '</td>' +
                '<td>' + escapeHtml(r.sales_display) + '</td>' +
                '<td class="small text-muted">' + escapeHtml(r.reason) + '</td>';
            tbody.appendChild(tr);
        });
    }

    // Issues list
    const issues = data.issues || [];
    const issuesBox = document.getElementById('issuesBox');
    const issuesList = document.getElementById('issuesList');
    if (issues.length > 0) {
        document.getElementById('issuesCount').textContent = issues.length;
        issuesList.innerHTML = issues.map(msg => '<li>' + escapeHtml(msg) + '</li>').join('');
        issuesBox.style.display = 'block';
    } else {
        issuesBox.style.display = 'none';
    }

    // Confirm vs Post-Import bars
    const confirmBar = document.getElementById('confirmImportBar');
    const postBar = document.getElementById('postImportBar');
    if (!isImport && (sum.ready_count || 0) > 0) {
        confirmBar.style.setProperty('display', 'flex', 'important');
        document.getElementById('confirmImportText').textContent =
            sum.ready_count + ' data customer valid siap disimpan ke database' +
            (sum.duplicate_count > 0 ? ' (' + sum.duplicate_count + ' duplikat akan dilewati).' : '.');
        postBar.style.setProperty('display', 'none', 'important');
    } else if (isImport) {
        confirmBar.style.setProperty('display', 'none', 'important');
        postBar.style.setProperty('display', 'flex', 'important');
        document.getElementById('postImportText').textContent = data.message || 'Proses import selesai.';
    } else {
        confirmBar.style.setProperty('display', 'none', 'important');
        postBar.style.setProperty('display', 'none', 'important');
    }

    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Tampilkan session flash bila diakses via non-AJAX submit lama
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['upload_status'])): ?>
        <?php if ($_SESSION['upload_status'] === 'success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Import Berhasil!',
                html: '<?php echo addslashes($_SESSION['flash_message'] ?? ''); ?>' +
                      '<?php if (!empty($_SESSION['flash_message_info'])) echo '<br><br>' . addslashes($_SESSION['flash_message_info']); ?>',
                showConfirmButton: true
            });
        <?php elseif ($_SESSION['upload_status'] === 'error'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Import Gagal',
                html: '<?php echo addslashes($_SESSION['flash_message_error'] ?? ''); ?>',
                showConfirmButton: true
            });
        <?php endif; ?>
        <?php
        unset($_SESSION['upload_status'], $_SESSION['flash_message'], $_SESSION['flash_message_info'], $_SESSION['flash_message_error']);
        ?>
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>
