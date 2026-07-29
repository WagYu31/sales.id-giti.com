<?php
/**
 * Halaman Kelola Pengumuman Tim Sales Loewix
 */
require_once 'includes/db.php';
include 'includes/header.php';
?>

<div class="main-content-wrapper p-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0F172A;">📢 Kelola Pengumuman Sales</h3>
            <p class="text-muted mb-0" style="font-size: 14px;">Terbitkan pemberitahuan, info promo, maupun update penting resmi untuk seluruh Tim Sales Loewix.</p>
        </div>
        <div>
            <button class="btn btn-primary px-4 py-2 rounded-3 fw-bold d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal" style="background: linear-gradient(135deg, #2563EB, #1D4ED8); border: none;">
                <i class="bi bi-plus-lg"></i> + Buat Pengumuman Baru
            </button>
        </div>
    </div>

    <!-- Announcement Table Card -->
    <div class="card border-0 rounded-4 shadow-sm" style="background: #FFFFFF;">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="announcementTable">
                    <thead style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0;">
                        <tr>
                            <th class="py-3 px-3 text-secondary" style="font-size: 13px; font-weight: 600;">TANGGAL</th>
                            <th class="py-3 px-3 text-secondary" style="font-size: 13px; font-weight: 600;">TIPE BADGE</th>
                            <th class="py-3 px-3 text-secondary" style="font-size: 13px; font-weight: 600;">JUDUL PENGUMUMAN</th>
                            <th class="py-3 px-3 text-secondary" style="font-size: 13px; font-weight: 600;">DIBUAT OLEH</th>
                            <th class="py-3 px-3 text-secondary" style="font-size: 13px; font-weight: 600;">STATUS</th>
                            <th class="py-3 px-3 text-secondary text-end" style="font-size: 13px; font-weight: 600;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody id="announcementListTbody">
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Memuat data pengumuman...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Create Announcement -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    📢 Buat Pengumuman Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createAnnouncementForm" onsubmit="handleCreateAnnouncement(event)">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Kategori / Tipe Badge</label>
                        <select name="badge_type" class="form-select rounded-3 py-2" required>
                            <option value="info">ℹ️ Informasi Umum (Info)</option>
                            <option value="promo">🚀 Promo & Penawaran Spesial (Promo)</option>
                            <option value="warning">⚠️ Perhatian Penting (Warning)</option>
                            <option value="urgent">🚨 Darurat / Urgent Notice</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Judul Pengumuman</label>
                        <input type="text" name="title" class="form-control rounded-3 py-2" placeholder="Contoh: Promo Spesial Paket CCTV Installer Bulan Ini!" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Isi Detail Pengumuman</label>
                        <textarea name="content" class="form-control rounded-3" rows="5" placeholder="Tuliskan pesan detail pengumuman yang akan dibaca oleh tim sales..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold" id="btnSubmitAnnouncement">
                        Terbitkan Pengumuman 🚀
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    loadAllAnnouncements();
});

function loadAllAnnouncements() {
    fetch('ajax_announcement_handler.php?action=fetch_all')
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('announcementListTbody');
            if (!data.data || data.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">Belum ada pengumuman yang diterbitkan.</td></tr>`;
                return;
            }

            let html = '';
            data.data.forEach(item => {
                let badgeClass = 'bg-info bg-opacity-10 text-info border-info';
                let badgeLabel = 'ℹ️ Info';
                if (item.badge_type === 'promo') {
                    badgeClass = 'bg-success bg-opacity-10 text-success border-success';
                    badgeLabel = '🚀 Promo';
                } else if (item.badge_type === 'warning') {
                    badgeClass = 'bg-warning bg-opacity-10 text-warning border-warning';
                    badgeLabel = '⚠️ Penting';
                } else if (item.badge_type === 'urgent') {
                    badgeClass = 'bg-danger bg-opacity-10 text-danger border-danger';
                    badgeLabel = '🚨 Urgent';
                }

                const createdDate = new Date(item.created_at).toLocaleDateString('id-ID', {
                    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                });

                const statusBadge = item.is_active == 1 
                    ? `<span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Aktif Tampil</span>` 
                    : `<span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">Non-Aktif</span>`;

                html += `
                <tr>
                    <td class="px-3 text-muted" style="font-size: 13px;">${createdDate}</td>
                    <td class="px-3">
                        <span class="badge ${badgeClass} border px-3 py-2 rounded-pill" style="font-size: 12px;">${badgeLabel}</span>
                    </td>
                    <td class="px-3">
                        <div class="fw-bold text-dark mb-1" style="font-size: 15px;">${escapeHtml(item.title)}</div>
                        <div class="text-muted text-truncate" style="max-width: 400px; font-size: 13px;">${escapeHtml(item.content)}</div>
                    </td>
                    <td class="px-3 text-secondary" style="font-size: 14px;">${escapeHtml(item.created_by)}</td>
                    <td class="px-3">${statusBadge}</td>
                    <td class="px-3 text-end">
                        <button onclick="toggleAnnouncementStatus(${item.id})" class="btn btn-sm btn-outline-secondary me-1 rounded-2">
                            ${item.is_active == 1 ? '<i class="bi bi-eye-slash"></i> Sembunyikan' : '<i class="bi bi-eye"></i> Tampilkan'}
                        </button>
                        <button onclick="deleteAnnouncement(${item.id})" class="btn btn-sm btn-outline-danger rounded-2">
                            <i class="bi bi-trash"></i> Hapus
                        </button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
        });
}

function handleCreateAnnouncement(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'create');

    const btn = document.getElementById('btnSubmitAnnouncement');
    btn.disabled = true;
    btn.innerHTML = 'Menerbitkan...';

    fetch('ajax_announcement_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = 'Terbitkan Pengumuman 🚀';
        if (res.status === 'success') {
            const modalEl = document.getElementById('createAnnouncementModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
            form.reset();
            loadAllAnnouncements();
        } else {
            alert(res.message || 'Gagal menerbitkan pengumuman.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = 'Terbitkan Pengumuman 🚀';
        alert('Terjadi kesalahan jaringan.');
    });
}

function toggleAnnouncementStatus(id) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('id', id);

    fetch('ajax_announcement_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            loadAllAnnouncements();
        } else {
            alert(res.message);
        }
    });
}

function deleteAnnouncement(id) {
    if (!confirm('Apakah Anda yakin ingin menghapus pengumuman ini?')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('ajax_announcement_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            loadAllAnnouncements();
        } else {
            alert(res.message);
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>

<?php include 'includes/footer.php'; ?>
