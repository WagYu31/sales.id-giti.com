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
    .preview-img { width: 60px; height: 60px; object-fit: cover; border-radius: 0.25rem; }
    .text-content { max-height: 4.5em; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
    .text-content.expanded { max-height: none; }
    #excel-content table { font-size: 0.8rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-calendar-check"></i> Jadwal Broadcast Sales</h1>
    <div>
        <input type="text" id="liveSearchInput" class="form-control d-inline-block w-auto me-2" placeholder="Cari...">
        <button class="btn btn-primary" id="btn-add-new"><i class="bi bi-plus-circle"></i> Tambah Jadwal</button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <?php if ($_SESSION['role'] === 'superadmin'): ?><th>Nama Sales</th><?php endif; ?>
                        <th>Gambar</th>
                        <th style="width: 30%;">Isi Teks</th>
                        <th>Data Customer</th>
                        <th>Laporan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="schedule-table-body">
                    <?php if (empty($schedules)): ?>
                        <tr><td colspan="9" class="text-center p-4">Belum ada jadwal broadcast.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; foreach ($schedules as $schedule): ?>
                    <tr id="schedule-row-<?php echo $schedule['id']; ?>">
                        <td><?php echo $i++; ?></td>
                        <td><?php echo date('d M Y', strtotime($schedule['jadwal_broadcast'])); ?></td>
                        <?php if ($_SESSION['role'] === 'superadmin'): ?><td><?php echo htmlspecialchars($schedule['sales_name']); ?></td><?php endif; ?>
                        <td>
                            <?php if(!empty($schedule['gambar_broadcast'])): ?>
                                <button class="btn btn-sm btn-outline-info btn-preview-image" 
                                        data-image-url="assets/broadcasts/images/<?php echo htmlspecialchars($schedule['gambar_broadcast']); ?>">
                                    <i class="bi bi-image"></i> Lihat
                                </button>
                            <?php endif; ?>
                        </td>
                        <td><div class="text-content" title="Klik untuk lihat selengkapnya"><?php echo htmlspecialchars($schedule['text_broadcast']); ?></div></td>
                        <td>
                            <?php if(!empty($schedule['media_excel'])): ?>
                                <a href="assets/broadcasts/media/<?php echo htmlspecialchars($schedule['media_excel']); ?>" class="btn btn-sm btn-outline-success btn-preview-excel">
                                    <i class="bi bi-file-earmark-excel"></i> Lihat
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($schedule['status'] == 'done'): ?>
                                <?php if (!empty($schedule['report_excel'])): ?>
                                    <a href="assets/broadcasts/reports/<?php echo htmlspecialchars($schedule['report_excel']); ?>" class="btn btn-sm btn-outline-success btn-preview-report">
                                        <i class="bi bi-file-earmark-text"></i> Lihat Laporan
                                    </a>
                                <?php elseif ($_SESSION['role'] === 'superadmin'): ?>
                                    <button class="btn btn-sm btn-primary btn-upload-report" data-id="<?php echo $schedule['id']; ?>">
                                        <i class="bi bi-upload"></i> Upload Laporan
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($schedule['status'] == 'done'): ?>
                                <span class="badge bg-success">Selesai</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Terjadwal</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning btn-edit" data-id="<?php echo $schedule['id']; ?>"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="<?php echo $schedule['id']; ?>"><i class="bi bi-trash"></i></button>
                            <?php if ($_SESSION['role'] === 'superadmin' && $schedule['status'] !== 'done'): ?>
                                <button class="btn btn-sm btn-success btn-done" data-id="<?php echo $schedule['id']; ?>" title="Tandai Selesai"><i class="bi bi-check-lg"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">Tambah Jadwal Broadcast</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="scheduleForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="form_action">
                    <input type="hidden" name="broadcast_id" id="broadcast_id">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label for="jadwal_broadcast" class="form-label">Tanggal Broadcast</label><input type="date" class="form-control" id="jadwal_broadcast" name="jadwal_broadcast" required></div>
                        <div class="col-md-6 mb-3"><label for="gambar_broadcast" class="form-label">Gambar Broadcast</label><input type="file" class="form-control" id="gambar_broadcast" name="gambar_broadcast" accept="image/*"><small class="text-muted" id="gambar_info"></small></div>
                    </div>
                    <div class="mb-3"><label for="text_broadcast" class="form-label">Isi Teks Broadcast</label><textarea class="form-control" id="text_broadcast" name="text_broadcast" rows="5" required></textarea></div>
                    <div class="mb-3"><label for="media_excel" class="form-label">Data Customer (File Excel)</label><input type="file" class="form-control" id="media_excel" name="media_excel" accept=".xls,.xlsx"><small class="text-muted" id="excel_info"></small></div>
                    <div class="mb-3"><label for="keterangan" class="form-label">Keterangan Lain (Opsional)</label><textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea></div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" form="scheduleForm" class="btn btn-primary">Simpan</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="reportUploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Upload Laporan Excel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="reportUploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_report">
                    <input type="hidden" name="broadcast_id" id="report_broadcast_id">
                    <div class="mb-3">
                        <label for="report_excel_file" class="form-label">Pilih File Laporan (.xls, .xlsx)</label>
                        <input type="file" class="form-control" id="report_excel_file" name="report_excel_file" accept=".xls,.xlsx" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" form="reportUploadForm" class="btn btn-primary">Upload</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="excelPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pratinjau File Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="excel-content" class="table-responsive">
                    <div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="download-excel-btn" class="btn btn-success" download><i class="bi bi-download"></i> Unduh File</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pratinjau Gambar Broadcast</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" id="modal-image-src" class="img-fluid rounded" alt="Pratinjau Gambar">
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
        excelContentDiv.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
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