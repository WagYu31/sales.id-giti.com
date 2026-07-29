<!-- PAPAN PENGUMUMAN OFFICIAL SALES WIDGET - ULTRA PREMIUM VIBRANT DESIGN -->
<style>
.announcement-banner-card {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 40%, #0F2942 100%);
    border-radius: 24px;
    padding: 24px 28px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 45px -12px rgba(15, 23, 42, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.announcement-banner-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3B82F6 0%, #8B5CF6 50%, #EC4899 100%);
}

.announcement-banner-card::after {
    content: '';
    position: absolute;
    top: -60px; right: -40px;
    width: 320px; height: 320px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, transparent 70%);
    pointer-events: none;
}

.announcement-item-card {
    background: #FFFFFF;
    border-radius: 18px;
    padding: 20px 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    border: 1px solid #E2E8F0;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

.announcement-item-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 32px rgba(0,0,0,0.12);
}

.announcement-badge-urgent {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
}

.announcement-badge-promo {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
}

.announcement-badge-warning {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
}

.announcement-badge-info {
    background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
}

.announcement-btn-action {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #FFFFFF;
    font-weight: 700;
    font-size: 13px;
    padding: 7px 18px;
    border-radius: 30px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.announcement-btn-action:hover {
    background: #FFFFFF;
    color: #1E3A8A;
    box-shadow: 0 6px 18px rgba(255,255,255,0.25);
}
</style>

<div id="announcementWidgetContainer" style="display: none;">
    <div class="announcement-banner-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 position-relative" style="z-index: 2;">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-lg" style="width: 44px; height: 44px; background: linear-gradient(135deg, #3B82F6, #8B5CF6); font-size: 20px;">
                    📢
                </div>
                <div>
                    <h4 class="mb-0 fw-bold text-white d-flex align-items-center gap-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 19px; letter-spacing: -0.4px;">
                        Papan Pengumuman Tim Sales
                        <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-2.5 py-1" style="font-size: 11px; font-weight: 600;" id="announcementActiveCount">1 Aktif</span>
                    </h4>
                    <p class="text-white-50 mb-0" style="font-size: 13px; font-family: 'Inter', sans-serif;">Pemberitahuan resmi promo, price list, & kabar penting dari manajemen Loewix</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                <a href="announcements.php" class="announcement-btn-action">
                    <i class="bi bi-gear-fill me-1 text-warning"></i> Kelola Pengumuman
                </a>
                <button type="button" class="btn btn-sm btn-outline-light rounded-circle border-0 p-0 d-flex align-items-center justify-content-center opacity-75 opacity-100-hover" onclick="dismissAnnouncementWidget()" style="width: 32px; height: 32px; font-size: 16px;">
                    ✕
                </button>
            </div>
        </div>

        <div id="announcementCardsList" class="d-flex flex-column gap-3 position-relative" style="z-index: 2;">
            <!-- Dynamic Announcements Inserted Here -->
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
                    countBadge.innerText = `${data.data.length} Pengumuman Aktif`;
                }

                let html = '';
                data.data.forEach(item => {
                    let badgeClass = 'announcement-badge-info';
                    let badgeIcon = 'ℹ️';
                    let badgeLabel = 'INFORMASI RESMI';
                    let borderLeftColor = '#3B82F6';
                    
                    if (item.badge_type === 'promo') {
                        badgeClass = 'announcement-badge-promo';
                        badgeIcon = '🚀';
                        badgeLabel = 'PROMO SPESIAL';
                        borderLeftColor = '#10B981';
                    } else if (item.badge_type === 'warning') {
                        badgeClass = 'announcement-badge-warning';
                        badgeIcon = '⚠️';
                        badgeLabel = 'PERHATIAN PENTING';
                        borderLeftColor = '#F59E0B';
                    } else if (item.badge_type === 'urgent') {
                        badgeClass = 'announcement-badge-urgent';
                        badgeIcon = '🚨';
                        badgeLabel = 'DARURAT / URGENT';
                        borderLeftColor = '#EF4444';
                    }

                    const dateStr = new Date(item.created_at).toLocaleDateString('id-ID', {
                        day: 'numeric', month: 'short', year: 'numeric'
                    });

                    html += `
                    <div class="announcement-item-card" style="border-left: 5px solid ${borderLeftColor};">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge ${badgeClass} px-3 py-1.5 rounded-pill" style="font-size: 11px; font-weight: 800; letter-spacing: 0.5px;">
                                    ${badgeIcon} ${badgeLabel}
                                </span>
                                <h5 class="fw-bold text-dark mb-0" style="font-size: 16px; font-family: 'Plus Jakarta Sans', sans-serif;">${escapeHtmlAnn(item.title)}</h5>
                            </div>
                            <div class="text-muted" style="font-size: 12.5px;">
                                📅 <strong>${dateStr}</strong> • Oleh: <span class="text-dark fw-bold">${escapeHtmlAnn(item.created_by)}</span>
                            </div>
                        </div>
                        <div class="text-secondary" style="font-size: 14px; line-height: 1.6; white-space: pre-line; font-family: 'Inter', sans-serif;">${escapeHtmlAnn(item.content)}</div>
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
