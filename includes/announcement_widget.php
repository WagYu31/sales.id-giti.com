<!-- PAPAN PENGUMUMAN OFFICIAL SALES WIDGET - ISO ENTERPRISE STANDARD DESIGN -->
<style>
.iso-announcement-card {
    background: #FFFFFF;
    border-radius: 20px;
    padding: 24px 28px;
    margin-bottom: 28px;
    position: relative;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    border: 1px solid #E2E8F0 !important;
    overflow: hidden;
}

.iso-announcement-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3.5px;
    background: linear-gradient(90deg, #2563EB 0%, #38BDF8 50%, #818CF8 100%);
}

.iso-announcement-item {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 16px 20px;
    transition: all 0.2s ease;
    position: relative;
}

.iso-announcement-item:hover {
    background: #FFFFFF;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
    border-color: #CBD5E1;
}

/* ISO Standard Soft Badges */
.badge-iso-promo {
    background-color: #ECFDF5 !important;
    color: #047857 !important;
    border: 1px solid #A7F3D0 !important;
    font-weight: 700 !important;
    font-size: 11px !important;
    letter-spacing: 0.3px;
}

.badge-iso-info {
    background-color: #EFF6FF !important;
    color: #1D4ED8 !important;
    border: 1px solid #BFDBFE !important;
    font-weight: 700 !important;
    font-size: 11px !important;
    letter-spacing: 0.3px;
}

.badge-iso-warning {
    background-color: #FFFBEB !important;
    color: #B45309 !important;
    border: 1px solid #FDE68A !important;
    font-weight: 700 !important;
    font-size: 11px !important;
    letter-spacing: 0.3px;
}

.badge-iso-urgent {
    background-color: #FEF2F2 !important;
    color: #B91C1C !important;
    border: 1px solid #FECACA !important;
    font-weight: 700 !important;
    font-size: 11px !important;
    letter-spacing: 0.3px;
}

.btn-iso-manage {
    background: #F1F5F9;
    color: #2563EB;
    border: 1px solid #CBD5E1;
    font-weight: 600;
    font-size: 12.5px;
    padding: 6px 16px;
    border-radius: 30px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-iso-manage:hover {
    background: #2563EB;
    color: #FFFFFF;
    border-color: #2563EB;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}
</style>

<div id="announcementWidgetContainer" style="display: none;">
    <div class="iso-announcement-card">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 40px; height: 40px; background: linear-gradient(135deg, #2563EB, #1D4ED8); font-size: 18px;">
                    📢
                </div>
                <div>
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 17px; letter-spacing: -0.3px;">
                        Papan Pengumuman Sales
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1" style="font-size: 11.5px; font-weight: 700;" id="announcementActiveCount">1 Aktif</span>
                    </h5>
                    <p class="text-muted mb-0" style="font-size: 13px; font-family: 'Inter', sans-serif;">Pemberitahuan resmi promo, price list, & kabar penting dari manajemen Loewix</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                <a href="announcements.php" class="btn-iso-manage">
                    <i class="bi bi-gear-fill me-1 text-primary"></i> Kelola Pengumuman
                </a>
                <button type="button" class="btn btn-sm btn-light border rounded-circle p-0 d-flex align-items-center justify-content-center text-muted" onclick="dismissAnnouncementWidget()" style="width: 32px; height: 32px; font-size: 14px;" title="Tutup Pemberitahuan">
                    ✕
                </button>
            </div>
        </div>

        <!-- Announcement Cards List -->
        <div id="announcementCardsList" class="d-flex flex-column gap-3">
            <!-- Dynamic Content Inserted via JS -->
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
                const countBadge = document.getElementById('announcementActiveCount');
                
                if (countBadge) {
                    countBadge.innerText = `${data.data.length} Aktif`;
                }

                let html = '';
                data.data.forEach(item => {
                    let badgeClass = 'badge-iso-info';
                    let badgeIcon = 'ℹ️';
                    let badgeLabel = 'INFORMASI';
                    let borderLeftColor = '#2563EB';
                    
                    if (item.badge_type === 'promo') {
                        badgeClass = 'badge-iso-promo';
                        badgeIcon = '🚀';
                        badgeLabel = 'PROMO SPESIAL';
                        borderLeftColor = '#10B981';
                    } else if (item.badge_type === 'warning') {
                        badgeClass = 'badge-iso-warning';
                        badgeIcon = '⚠️';
                        badgeLabel = 'PERHATIAN PENTING';
                        borderLeftColor = '#F59E0B';
                    } else if (item.badge_type === 'urgent') {
                        badgeClass = 'badge-iso-urgent';
                        badgeIcon = '🚨';
                        badgeLabel = 'DARURAT / URGENT';
                        borderLeftColor = '#EF4444';
                    }

                    const dateStr = new Date(item.created_at).toLocaleDateString('id-ID', {
                        day: 'numeric', month: 'short', year: 'numeric'
                    });

                    html += `
                    <div class="iso-announcement-item" style="border-left: 4px solid ${borderLeftColor} !important;">
                        <div class="d-flex justify-content-between align-items-center mb-1.5 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge ${badgeClass} px-2.5 py-1 rounded-pill">
                                    ${badgeIcon} ${badgeLabel}
                                </span>
                                <h6 class="fw-bold text-dark mb-0" style="font-size: 15px; font-family: 'Plus Jakarta Sans', sans-serif;">${escapeHtmlAnn(item.title)}</h6>
                            </div>
                            <div class="text-muted" style="font-size: 12px; font-family: 'Inter', sans-serif;">
                                📅 ${dateStr} • Oleh: <strong class="text-secondary">${escapeHtmlAnn(item.created_by)}</strong>
                            </div>
                        </div>
                        <div class="text-secondary" style="font-size: 13.5px; line-height: 1.6; white-space: pre-line; font-family: 'Inter', sans-serif;">${escapeHtmlAnn(item.content)}</div>
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
