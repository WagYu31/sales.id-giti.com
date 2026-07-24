<?php
$page_title = 'Jadwal Broadcast Sales';
require_once 'includes/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'sales'])) {
    header('Location: login.php');
    exit();
}

$sql_where = "b.deleted_at IS NULL";
$params = [];
$types = '';
if ($_SESSION['role'] === 'sales') {
    $sql_where .= " AND b.sales_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$sql = "
    SELECT b.*, s.nama_lengkap as sales_name
    FROM sales_broadcasts b
    JOIN sales s ON b.sales_id = s.id
    WHERE {$sql_where}
    ORDER BY b.jadwal_broadcast DESC
";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$schedules = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<style>
.bc-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.bc-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.bc-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.bc-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.text-content { 
    max-height: 4.2em; 
    overflow: hidden; 
    text-overflow: ellipsis; 
    cursor: pointer; 
    font-size: 13px;
    line-height: 1.5;
    color: #334155;
}
.text-content.expanded { max-height: none; }
#excel-content table { font-size: 0.85rem; }

.sales-avatar-badge-small {
    width: 28px; height: 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: #FFF;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    margin-right: 8px;
}
</style>

<!-- Hero Header -->
<div class="bc-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Jadwal Broadcast Sales</span>
            </div>
            <h1 class="bc-hero-title">Jadwal Broadcast Sales 📢</h1>
            <p class="bc-hero-subtitle">Kelola pesan broadcast promosi, pengiriman materi, dan laporan hasil broadcast tim sales.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-primary shadow-lg" id="btn-add-new">
                <i class="bi bi-plus-circle-fill"></i> Tambah Jadwal Baru
            </button>
        </div>
    </div>
</div>

<!-- Main Card -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0"><i class="bi bi-calendar-check-fill"></i> Daftar Broadcast</h5>
        <div style="min-width: 260px;">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="liveSearchInput" class="form-control border-start-0 ps-0" placeholder="Cari broadcast...">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark-header">
                    <tr>
                        <th style="width: 4%;">No</th>
                        <th style="width: 12%;">Tanggal</th>
                        <?php if ($_SESSION['role'] === 'superadmin'): ?><th style="width: 15%;">Nama Sales</th><?php endif; ?>
                        <th style="width: 10%;">Gambar</th>
                        <th style="width: 30%;">Isi Teks</th>
                        <th style="width: 12%;">Data Customer</th>
                        <th style="width: 12%;">Laporan</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 10%; text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="schedule-table-body">
                    <?php if (empty($schedules)): ?>
                        <tr><td colspan="9" class="text-center p-5 text-muted">Belum ada jadwal broadcast terdaftar.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($schedules as $schedule): ?>
                    <tr id="schedule-row-<?php echo $schedule['id']; ?>">
                        <td class="fw-bold text-muted"><?php echo $i++; ?></td>
                        <td class="text-nowrap" style="font-size:12.5px; font-weight:600; color:#475569;">
                            <i class="bi bi-calendar-event text-primary me-1"></i>
                            <?php echo date('d M Y', strtotime($schedule['jadwal_broadcast'])); ?>
                        </td>
                        <?php if ($_SESSION['role'] === 'superadmin'): ?>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="sales-avatar-badge-small">
                                    <?php echo strtoupper(substr($schedule['sales_name'], 0, 1)); ?>
                                </div>
                                <span class="fw-semibold text-dark" style="font-size:13px;"><?php echo htmlspecialchars($schedule['sales_name']); ?></span>
                            </div>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php if(!empty($schedule['gambar_broadcast'])): ?>
                                <button class="btn btn-sm btn-outline-info btn-preview-image" 
                                        data-image-url="assets/broadcasts/images/<?php echo htmlspecialchars($schedule['gambar_broadcast']); ?>">
                                    <i class="bi bi-image-fill"></i> Lihat
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="text-content" title="Klik untuk lihat selengkapnya">
                                <?php echo htmlspecialchars($schedule['text_broadcast']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if(!empty($schedule['media_excel'])): ?>
                                <a href="assets/broadcasts/media/<?php echo htmlspecialchars($schedule['media_excel']); ?>" class="btn btn-sm btn-outline-secondary btn-preview-excel">
                                    <i class="bi bi-file-earmark-excel-fill text-success"></i> Data Excel
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($schedule['status'] == 'done'): ?>
                                <?php if (!empty($schedule['report_excel'])): ?>
                                    <a href="assets/broadcasts/reports/<?php echo htmlspecialchars($schedule['report_excel']); ?>" class="btn btn-sm btn-outline-success btn-preview-report">
                                        <i class="bi bi-file-earmark-text-fill"></i> Laporan
                                    </a>
                                <?php elseif ($_SESSION['role'] === 'superadmin'): ?>
                                    <button class="btn btn-sm btn-primary btn-upload-report" data-id="<?php echo $schedule['id']; ?>">
                                        <i class="bi bi-upload"></i> Upload
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">Belum ada</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($schedule['status'] == 'done'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Selesai</span>
                            <?php else: ?>
                                <span class="badge bg-warning"><i class="bi bi-clock-fill"></i> Terjadwal</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="<?php echo $schedule['id']; ?>" title="Edit"><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $schedule['id']; ?>" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                <?php if ($_SESSION['role'] === 'superadmin' && $schedule['status'] !== 'done'): ?>
                                    <button class="btn btn-sm btn-success btn-done" data-id="<?php echo $schedule['id']; ?>" title="Tandai Selesai"><i class="bi bi-check-lg"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Modals -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold" id="modalTitle">Tambah Jadwal Broadcast</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="scheduleForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="form_action">
                    <input type="hidden" name="broadcast_id" id="broadcast_id">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="jadwal_broadcast" class="form-label">Tanggal Broadcast</label>
                            <input type="date" class="form-control" id="jadwal_broadcast" name="jadwal_broadcast" required>
                        </div>
                        <div class="col-md-6">
                            <label for="gambar_broadcast" class="form-label">Gambar Broadcast</label>
                            <input type="file" class="form-control" id="gambar_broadcast" name="gambar_broadcast" accept="image/*">
                            <small class="text-muted" id="gambar_info"></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="text_broadcast" class="form-label">Isi Teks Broadcast</label>
                        <textarea class="form-control" id="text_broadcast" name="text_broadcast" rows="5" required placeholder="Tulis isi pesan promosi broadcast..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="media_excel" class="form-label">Data Customer (File Excel)</label>
                        <input type="file" class="form-control" id="media_excel" name="media_excel" accept=".xls,.xlsx">
                        <small class="text-muted" id="excel_info"></small>
                    </div>
                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan Lain (Opsional)</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="scheduleForm" class="btn btn-primary"><i class="bi bi-save-fill"></i> Simpan Jadwal</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reportUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold">Upload Laporan Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="reportUploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_report">
                    <input type="hidden" name="broadcast_id" id="report_broadcast_id">
                    <div class="mb-3">
                        <label for="report_excel_file" class="form-label">Pilih File Laporan (.xls, .xlsx)</label>
                        <input type="file" class="form-control" id="report_excel_file" name="report_excel_file" accept=".xls,.xlsx" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="reportUploadForm" class="btn btn-primary"><i class="bi bi-upload"></i> Upload Laporan</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="excelPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold">Pratinjau File Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="excel-content" class="table-responsive">
                    <div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <a href="#" id="download-excel-btn" class="btn btn-success" download><i class="bi bi-download"></i> Unduh File</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold">Pratinjau Gambar Broadcast</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img src="" id="modal-image-src" class="img-fluid rounded-3 shadow" alt="Pratinjau Gambar">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    const excelPreviewModal = new bootstrap.Modal(document.getElementById('excelPreviewModal'));
    const imagePreviewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    const reportUploadModal = new bootstrap.Modal(document.getElementById('reportUploadModal'));

    const scheduleForm = document.getElementById('scheduleForm');
    const reportUploadForm = document.getElementById('reportUploadForm');
    const tableBody = document.getElementById('schedule-table-body');
    
    document.getElementById('btn-add-new').addEventListener('click', function() {
        scheduleForm.reset();
        document.getElementById('modalTitle').textContent = 'Tambah Jadwal Broadcast';
        document.getElementById('form_action').value = 'add_schedule';
        document.getElementById('broadcast_id').value = '';
        document.getElementById('gambar_info').textContent = '';
        document.getElementById('excel_info').textContent = '';
        scheduleModal.show();
    });
    
    async function showExcelPreview(fileUrl) {
        document.getElementById('download-excel-btn').setAttribute('href', fileUrl);
        const excelContentDiv = document.getElementById('excel-content');
        excelContentDiv.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        excelPreviewModal.show();
        
        try {
            const response = await fetch(fileUrl);
            if (!response.ok) throw new Error('File tidak ditemukan atau gagal diakses.');
            
            const arrayBuffer = await response.arrayBuffer();
            const data = new Uint8Array(arrayBuffer);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const html = XLSX.utils.sheet_to_html(worksheet);

            excelContentDiv.innerHTML = html;
            excelContentDiv.querySelector('table').classList.add('table', 'table-bordered', 'table-striped');
        } catch (error) {
            excelContentDiv.innerHTML = `<div class="alert alert-danger">Gagal memuat pratinjau: ${error.message}</div>`;
        }
    }

    tableBody.addEventListener('click', async function(e) {
        const target = e.target;
        
        if (target.classList.contains('text-content')) {
            target.classList.toggle('expanded');
        }

        const btnPreviewImage = target.closest('.btn-preview-image');
        if (btnPreviewImage) {
            document.getElementById('modal-image-src').setAttribute('src', btnPreviewImage.dataset.imageUrl);
            imagePreviewModal.show();
        }

        const btnPreviewExcel = target.closest('.btn-preview-excel');
        if (btnPreviewExcel) {
            e.preventDefault();
            showExcelPreview(btnPreviewExcel.getAttribute('href'));
        }
        
        const btnPreviewReport = target.closest('.btn-preview-report');
        if (btnPreviewReport) {
            e.preventDefault();
            showExcelPreview(btnPreviewReport.getAttribute('href'));
        }

        const btnEdit = target.closest('.btn-edit');
        if (btnEdit) {
            const id = btnEdit.dataset.id;
            const response = await fetch(`ajax_broadcast_handler.php?action=get_details&id=${id}`);
            const data = await response.json();
            if (data.success) {
                const d = data.data;
                scheduleForm.reset();
                document.getElementById('modalTitle').textContent = 'Edit Jadwal Broadcast';
                document.getElementById('form_action').value = 'edit_schedule';
                document.getElementById('broadcast_id').value = d.id;
                document.getElementById('jadwal_broadcast').value = d.jadwal_broadcast;
                document.getElementById('text_broadcast').value = d.text_broadcast;
                document.getElementById('keterangan').value = d.keterangan;
                document.getElementById('gambar_info').textContent = 'File saat ini: ' + d.gambar_broadcast;
                document.getElementById('excel_info').textContent = 'File saat ini: ' + d.media_excel;
                scheduleModal.show();
            }
        }
        
        const btnDelete = target.closest('.btn-delete');
        if (btnDelete) {
            const id = btnDelete.dataset.id;
            Swal.fire({
                title: 'Anda yakin?', text: "Jadwal akan dihapus.", icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Batal', confirmButtonText: 'Ya, hapus!'
            }).then(result => {
                if(result.isConfirmed) handleAction('delete_schedule', id);
            });
        }

        const btnDone = target.closest('.btn-done');
        if (btnDone) {
            const id = btnDone.dataset.id;
            Swal.fire({
                title: 'Tandai Selesai?', text: "Status jadwal akan diubah menjadi 'Selesai'.", icon: 'question',
                showCancelButton: true, confirmButtonColor: '#198754', cancelButtonText: 'Batal', confirmButtonText: 'Ya, Tandai!'
            }).then(result => {
                if(result.isConfirmed) handleAction('mark_done', id);
            });
        }

        const btnUploadReport = target.closest('.btn-upload-report');
        if (btnUploadReport) {
            const id = btnUploadReport.dataset.id;
            document.getElementById('report_broadcast_id').value = id;
            reportUploadModal.show();
        }
    });

    scheduleForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax_broadcast_handler.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            if (data.success) {
                scheduleModal.hide();
                Swal.fire({ icon: 'success', title: 'Berhasil!', timer: 1500, showConfirmButton: false }).then(() => window.location.reload());
            } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
        });
    });

    reportUploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax_broadcast_handler.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            if (data.success) {
                reportUploadModal.hide();
                Swal.fire({ icon: 'success', title: 'Laporan Berhasil Diupload!', timer: 1500, showConfirmButton: false }).then(() => window.location.reload());
            } else { Swal.fire({ icon: 'error', title: 'Gagal', text: data.message }); }
        });
    });
    
    function handleAction(action, id) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('broadcast_id', id);
        fetch('ajax_broadcast_handler.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            if (data.success) window.location.reload();
            else Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
        });
    }

    document.getElementById('liveSearchInput').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        document.querySelectorAll('#schedule-table-body tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>