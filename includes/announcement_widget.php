<!-- PAPAN PENGUMUMAN OFFICIAL SALES WIDGET -->
<div id="announcementWidgetContainer" class="mb-4" style="display: none;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%); border: 1.5px solid #E2E8F0 !important;">
        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom-0">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 38px; height: 38px; background: linear-gradient(135deg, #2563EB, #1D4ED8);">
                    📢
                </div>
                <div>
                    <h5 class="mb-0 fw-bold text-dark" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px;">
                        Papan Pengumuman Official Tim Sales
                    </h5>
                    <small class="text-muted" style="font-size: 12px;">Informasi resmi promo, price list, & pengumuman manajemen Loewix</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="announcements.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" style="font-size: 12px;">
                    <i class="bi bi-gear-fill me-1"></i> Kelola Pengumuman
                </a>
                <button type="button" class="btn-close" onclick="dismissAnnouncementWidget()" aria-label="Close"></button>
            </div>
        </div>
        <div class="card-body px-4 pt-1 pb-4">
            <div id="announcementCardsList" class="d-flex flex-column gap-3">
                <!-- Dynamic Announcements Inserted Here -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (!sessionStorage.getItem("dismissed_announcements")) {
        loadActiveAnnouncements();
    }
});

function loadActiveAnnouncements() {
    fetch('ajax_announcement_handler.php?action=fetch_active')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.data && data.data.length > 0) {
                const container = document.getElementById('announcementWidgetContainer');
                const list = document.getElementById('announcementCardsList');
                
                let html = '';
                data.data.forEach(item => {
                    let badgeClass = 'bg-info bg-opacity-10 text-info border-info';
                    let badgeIcon = 'ℹ️';
                    let badgeLabel = 'INFORMASI';
                    
                    if (item.badge_type === 'promo') {
                        badgeClass = 'bg-success bg-opacity-10 text-success border-success';
                        badgeIcon = '🚀';
                        badgeLabel = 'PROMO SPESIAL';
                    } else if (item.badge_type === 'warning') {
                        badgeClass = 'bg-warning bg-opacity-10 text-warning border-warning';
                        badgeIcon = '⚠️';
                        badgeLabel = 'PENTING';
                    } else if (item.badge_type === 'urgent') {
                        badgeClass = 'bg-danger bg-opacity-10 text-danger border-danger';
                        badgeIcon = '🚨';
                        badgeLabel = 'DARURAT';
                    }

                    const dateStr = new Date(item.created_at).toLocaleDateString('id-ID', {
                        day: 'numeric', month: 'short', year: 'numeric'
                    });

                    html += `
                    <div class="p-3 rounded-3 border bg-white shadow-xs position-relative" style="border-left: 4px solid var(--bs-primary) !important;">
                        <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge ${badgeClass} border px-2.5 py-1 rounded-pill" style="font-size: 11px; font-weight: 700;">
                                    ${badgeIcon} ${badgeLabel}
                                </span>
                                <span class="fw-bold text-dark" style="font-size: 15px; font-family: 'Plus Jakarta Sans', sans-serif;">${escapeHtmlAnn(item.title)}</span>
                            </div>
                            <small class="text-muted" style="font-size: 12px;">📅 ${dateStr} • Oleh: <strong>${escapeHtmlAnn(item.created_by)}</strong></small>
                        </div>
                        <div class="text-secondary" style="font-size: 13.5px; line-height: 1.6; white-space: pre-line;">${escapeHtmlAnn(item.content)}</div>
                    </div>`;
                });

                list.innerHTML = html;
                container.style.display = 'block';
            }
        })
        .catch(err => console.error("Error loading announcements:", err));
}

function dismissAnnouncementWidget() {
    sessionStorage.setItem("dismissed_announcements", "true");
    const container = document.getElementById('announcementWidgetContainer');
    if (container) container.style.display = 'none';
}

function escapeHtmlAnn(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
