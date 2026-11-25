<?php
// customer_io_sales.php
// Halaman Import Massal Database Customer (Excel & Google Sheets) khusus akun Sales
$page_title = 'Import Customer (Excel & Google Sheets)';
require_once 'includes/db.php';

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (in_array($userRole, ['superadmin', 'adminsales'], true)) {
    header('Location: customer_io.php');
    exit();
}
if ($userRole !== 'sales') {
    die("Akses ditolak.");
}

$salesNameSession = $_SESSION['nama_lengkap'] ?? 'Akun Anda';
require_once 'includes/header.php';
?>

<style>
.io-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1d4ed8 100%);
    border-radius: 20px;
    padding: 26px 30px;
    margin-bottom: 24px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 35px -10px rgba(15, 23, 42, 0.45);
}
.io-hero-title {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    color: #FFFFFF;
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
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s ease;
}
.io-source-tab.active-excel { border-color: #2563EB; background: #EFF6FF; }
.io-source-tab.active-gsheet { border-color: #059669; background: #ECFDF5; }
.upload-dropzone {
    border: 2px dashed #93C5FD;
    background: #F8FAFF;
    border-radius: 16px;
    padding: 32px 24px;
    text-align: center;
    cursor: pointer;
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
    margin-bottom: 16px;
}
@media (max-width: 768px) {
    .io-source-tabs { grid-template-columns: 1fr; }
    .io-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
.io-kpi-box { border-radius: 12px; padding: 12px 14px; border: 1px solid #E2E8F0; background: #FFFFFF; }
.preview-table-wrap { max-height: 360px; overflow: auto; border: 1px solid #E2E8F0; border-radius: 12px; }
#loading-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background-color: rgba(15, 23, 42, 0.72); backdrop-filter: blur(6px);
    z-index: 9999; display: none; justify-content: center; align-items: center;
    flex-direction: column; color: #FFFFFF;
}
</style>

<div id="loading-overlay">
    <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;"></div>
    <h5 class="mt-3 fw-bold mb-1" id="loading-title">Memproses data spreadsheet...</h5>
</div>

<div class="io-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="io-hero-title"><i class="bi bi-file-earmark-spreadsheet-fill me-2 text-info"></i>Import Customer via Excel &amp; Google Sheets</h1>
            <p class="mb-0 text-white-50" style="font-size:13.5px;">
                Semua customer yang Anda import otomatis masuk ke akun Sales: <strong class="text-white"><?php echo htmlspecialchars($salesNameSession); ?></strong>.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="customer_template_download.php?format=xlsx&mode=sales" class="btn fw-bold text-white" style="background: linear-gradient(135deg, #059669, #10B981); border:none; border-radius:12px;">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Unduh Template Excel
            </a>
            <a href="customer_template_download.php?format=csv&mode=sales" class="btn fw-bold text-white" style="background: rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.3); border-radius:12px;">
                <i class="bi bi-filetype-csv me-1"></i> Template CSV
            </a>
            <a href="index.php" class="btn btn-light fw-bold" style="border-radius:12px;">
                <i class="bi bi-arrow-left me-1"></i> Database
            </a>
        </div>
    </div>
</div>

<div class="io-card mb-4">
    <div class="io-card-header">
        <h5 class="fw-bold mb-0" style="font-size:15px;"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Unggah File atau Tempel Link Google Sheets</h5>
        <button type="button" class="btn btn-sm btn-outline-success fw-bold" style="border-radius:8px;" onclick="copySalesHeader()">
            <i class="bi bi-clipboard-check me-1"></i> Salin Header untuk Google Sheets
        </button>
    </div>
    <div class="p-4">
        <div class="io-source-tabs">
            <button type="button" class="io-source-tab active-excel" id="tabBtnFile" onclick="switchSource('file')">
                <i class="bi bi-file-earmark-excel-fill text-primary fs-3"></i>
                <div>
                    <div class="fw-bold text-dark">Upload File Excel / CSV</div>
                    <div class="text-muted small">.xlsx, .xls, atau .csv</div>
                </div>
            </button>
            <button type="button" class="io-source-tab" id="tabBtnGsheet" onclick="switchSource('gsheet')">
                <i class="bi bi-table text-success fs-3"></i>
                <div>
                    <div class="fw-bold text-dark">Link Google Sheets</div>
                    <div class="text-muted small">Tarik langsung dari URL</div>
                </div>
            </button>
        </div>

        <div id="panelSourceFile" class="mb-4">
            <div class="upload-dropzone" onclick="document.getElementById('customer_file').click();">
                <i class="bi bi-cloud-arrow-up-fill text-primary" style="font-size:44px;"></i>
                <h6 class="fw-bold mt-2 mb-1">Klik untuk Pilih File Excel / CSV</h6>
                <p class="text-muted small mb-0">Mendukung .xlsx, .xls, dan .csv (Maks. 15 MB)</p>
                <input class="d-none" type="file" id="customer_file" accept=".xlsx,.xls,.csv" onchange="showFile(this)">
                <div id="file-badge" class="mt-2 fw-bold text-success small" style="display:none;"></div>
            </div>
        </div>

        <div id="panelSourceGsheet" class="mb-4" style="display:none;">
            <div class="gsheet-panel">
                <label class="form-label fw-bold text-success">Link Google Spreadsheet (Akses: Siapa saja yang memiliki link - Pelihat)</label>
                <input type="url" class="form-control bg-white" id="gsheet_url" placeholder="https://docs.google.com/spreadsheets/d/1ABCxyz.../edit" style="border-radius:10px;">
            </div>
        </div>

        <div class="row g-3 p-3 bg-light rounded-3 border mb-4">
            <div class="col-md-5">
                <label class="form-label fw-bold small text-secondary mb-1">Default Kategori (jika kosong)</label>
                <select id="default_kategori" class="form-select form-select-sm" style="border-radius:8px;">
                    <option value="DEALER" selected>DEALER</option>
                    <option value="INSTALLER">INSTALLER</option>
                    <option value="MASTER DEALER">MASTER DEALER</option>
                    <option value="USER">USER</option>
                </select>
            </div>
            <div class="col-md-7 d-flex align-items-end flex-wrap gap-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="skip_duplicate_phone" checked>
                    <label class="form-check-label small fw-semibold" for="skip_duplicate_phone">Lewati No. HP duplikat</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="skip_duplicate_store" checked>
                    <label class="form-check-label small fw-semibold" for="skip_duplicate_store">Lewati Nama Toko duplikat</label>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-primary fw-bold px-4" style="border-radius:10px;" onclick="runSalesImport('preview')">
                <i class="bi bi-eye-fill me-1"></i> Pratinjau Dulu
            </button>
            <button type="button" class="btn btn-primary fw-bold px-4" style="border-radius:10px;" onclick="runSalesImport('import')">
                <i class="bi bi-database-fill-add me-1"></i> Import Sekarang
            </button>
        </div>
    </div>
</div>

<div class="io-card mb-4" id="resultPanel" style="display:none;">
    <div class="io-card-header">
        <h6 class="fw-bold mb-0" id="resultTitle">Hasil Pratinjau</h6>
        <a href="index.php" class="btn btn-sm btn-primary fw-bold" id="btnToDb" style="display:none; border-radius:8px;">Lihat Database</a>
    </div>
    <div class="p-4">
        <div class="io-kpi-grid">
            <div class="io-kpi-box"><div class="small text-muted fw-bold">Siap / Masuk</div><div class="fs-4 fw-bold text-success" id="kReady">0</div></div>
            <div class="io-kpi-box"><div class="small text-muted fw-bold">Duplikat</div><div class="fs-4 fw-bold text-warning" id="kDup">0</div></div>
            <div class="io-kpi-box"><div class="small text-muted fw-bold">Error</div><div class="fs-4 fw-bold text-danger" id="kErr">0</div></div>
            <div class="io-kpi-box"><div class="small text-muted fw-bold">Contoh</div><div class="fs-4 fw-bold text-secondary" id="kEx">0</div></div>
        </div>
        <div class="preview-table-wrap mb-3">
            <table class="table table-sm table-hover mb-0" style="font-size:12.5px;">
                <thead class="table-light">
                    <tr><th>#</th><th>Status</th><th>Nama Toko</th><th>Kategori</th><th>PIC</th><th>No. HP</th><th>Kota</th><th>Keterangan</th></tr>
                </thead>
                <tbody id="resultTbody"></tbody>
            </table>
        </div>
        <div id="confirmBar" class="text-end" style="display:none;">
            <button type="button" class="btn btn-success fw-bold px-4" style="border-radius:10px;" onclick="runSalesImport('import')">
                <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi &amp; Simpan ke Database
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let srcType = 'file';
function switchSource(t) {
    srcType = t;
    document.getElementById('tabBtnFile').classList.toggle('active-excel', t === 'file');
    document.getElementById('tabBtnGsheet').classList.toggle('active-gsheet', t === 'gsheet');
    document.getElementById('panelSourceFile').style.display = t === 'file' ? 'block' : 'none';
    document.getElementById('panelSourceGsheet').style.display = t === 'gsheet' ? 'block' : 'none';
}
function showFile(inp) {
    const b = document.getElementById('file-badge');
    if (inp.files && inp.files[0]) {
        b.textContent = 'File dipilih: ' + inp.files[0].name;
        b.style.display = 'block';
    }
}
function copySalesHeader() {
    const tsv = ['nama_toko','kategori','nama_pic','tlp_pic','alamat','kota','provinsi','link_google_map','tgl_input'].join('\t');
    navigator.clipboard.writeText(tsv).then(() => {
        Swal.fire({ icon: 'success', title: 'Header Tersalin!', text: 'Tempel (Ctrl+V) di sel A1 Google Sheets baru.', timer: 2500 });
    });
}
function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
async function runSalesImport(mode) {
    const f = document.getElementById('customer_file');
    const g = document.getElementById('gsheet_url');
    if (srcType === 'file' && (!f.files || !f.files[0])) {
        Swal.fire({ icon: 'warning', title: 'Pilih File Dulu' }); return;
    }
    if (srcType === 'gsheet' && !g.value.trim()) {
        Swal.fire({ icon: 'warning', title: 'Tempel Link Google Sheets Dulu' }); return;
    }
    document.getElementById('loading-overlay').style.display = 'flex';
    try {
        const fd = new FormData();
        fd.append('ajax', '1');
        fd.append('mode', mode);
        fd.append('source_type', srcType);
        if (srcType === 'file') fd.append('customer_file', f.files[0]);
        else fd.append('gsheet_url', g.value.trim());
        fd.append('default_kategori', document.getElementById('default_kategori').value);
        fd.append('skip_duplicate_phone', document.getElementById('skip_duplicate_phone').checked ? '1' : '0');
        fd.append('skip_duplicate_store', document.getElementById('skip_duplicate_store').checked ? '1' : '0');

        const res = await fetch('customer_bulk_upload_process_sales.php', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} });
        const data = await res.json();
        document.getElementById('loading-overlay').style.display = 'none';
        if (!data || !data.ok) {
            Swal.fire({ icon: 'error', title: 'Gagal', text: data ? data.error : 'Error server' }); return;
        }
        const sum = data.summary || {};
        const isImp = data.mode === 'import';
        document.getElementById('resultTitle').textContent = isImp ? 'Hasil Import Selesai' : 'Pratinjau Data (Belum Disimpan)';
        document.getElementById('kReady').textContent = isImp ? (sum.imported_count || 0) : (sum.ready_count || 0);
        document.getElementById('kDup').textContent = sum.duplicate_count || 0;
        document.getElementById('kErr').textContent = sum.error_count || 0;
        document.getElementById('kEx').textContent = sum.example_count || 0;
        document.getElementById('resultTbody').innerHTML = (data.preview_rows || []).map(r =>
            '<tr><td>#' + esc(r.row_num) + '</td><td>' + esc(r.status) + '</td><td class="fw-bold">' + esc(r.nama_toko) + '</td><td>' + esc(r.kategori) + '</td><td>' + esc(r.nama_pic) + '</td><td><code>' + esc(r.tlp_pic) + '</code></td><td>' + esc(r.kota) + '</td><td class="small text-muted">' + esc(r.reason) + '</td></tr>'
        ).join('');
        document.getElementById('confirmBar').style.display = (!isImp && (sum.ready_count || 0) > 0) ? 'block' : 'none';
        document.getElementById('btnToDb').style.display = isImp ? 'inline-block' : 'none';
        document.getElementById('resultPanel').style.display = 'block';
        if (isImp) {
            Swal.fire({ icon: (sum.imported_count || 0) > 0 ? 'success' : 'info', title: 'Selesai', text: data.message });
        }
    } catch (e) {
        document.getElementById('loading-overlay').style.display = 'none';
        Swal.fire({ icon: 'error', title: 'Error', text: e.message });
    }
}
</script>

<?php require_once 'includes/floating_menu.php'; ?>
<?php require_once 'includes/footer.php'; ?>
