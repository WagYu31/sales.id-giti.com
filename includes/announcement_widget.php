<!-- PAPAN PENGUMUMAN OFFICIAL SALES WIDGET - 3D SPATIAL & GRID DESIGN -->
<style>
@keyframes float3D {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-4px) rotate(2deg); }
}

@keyframes pulseRed3D {
    0%, 100% { box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4); }
    50% { box-shadow: 0 8px 22px rgba(239, 68, 68, 0.7); }
}

.widget-3d-card {
    background: #FFFFFF;
    border-radius: 24px;
    padding: 28px 32px;
    margin-bottom: 32px;
    position: relative;
    box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.08), 0 4px 12px rgba(15, 23, 42, 0.02);
    border: 1.5px solid rgba(226, 232, 240, 0.9) !important;
    overflow: hidden;
    transform-style: preserve-3d;
    perspective: 1200px;
}

.widget-3d-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2563EB 0%, #8B5CF6 50%, #EC4899 100%);
    box-shadow: 0 2px 10px rgba(37, 99, 235, 0.3);
}

.avatar-3d-pulse {
    width: 48px; height: 48px;
    border-radius: 16px;
    background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    box-shadow: 0 10px 22px -4px rgba(37, 99, 235, 0.45), inset 0 2px 4px rgba(255, 255, 255, 0.35);
    animation: float3D 4s ease-in-out infinite;
    flex-shrink: 0;
}

.announcement-grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 20px;
}

.item-3d-card {
    background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%);
    border: 1.5px solid #E2E8F0;
    border-radius: 20px;
    padding: 22px 24px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.item-3d-card:hover {
    background: #FFFFFF;
    transform: translateY(-4px) scale(1.01);
    box-shadow: 0 16px 36px -6px rgba(15, 23, 42, 0.12), 0 4px 14px rgba(15, 23, 42, 0.04);
    border-color: #CBD5E1;
}

/* 3D Glossy Badges */
.badge-3d-promo {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
    color: #FFFFFF !important;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35), inset 0 1.5px 2px rgba(255, 255, 255, 0.4);
    font-weight: 800 !important;
    font-size: 11px !important;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.badge-3d-info {
    background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%) !important;
    color: #FFFFFF !important;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35), inset 0 1.5px 2px rgba(255, 255, 255, 0.4);
    font-weight: 800 !important;
    font-size: 11px !important;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.badge-3d-warning {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%) !important;
    color: #FFFFFF !important;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35), inset 0 1.5px 2px rgba(255, 255, 255, 0.4);
    font-weight: 800 !important;
    font-size: 11px !important;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.badge-3d-urgent {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%) !important;
    color: #FFFFFF !important;
    animation: pulseRed3D 2s infinite;
    font-weight: 800 !important;
    font-size: 11px !important;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.btn-3d-manage {
    background: #FFFFFF;
    color: #2563EB;
    border: 1.5px solid #CBD5E1;
    font-weight: 700;
    font-size: 13px;
    padding: 8px 22px;
    border-radius: 30px;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.8);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-3d-manage:hover {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #FFFFFF;
    border-color: #2563EB;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
}

.btn-3d-manage:hover i {
    color: #FFFFFF !important;
}

@media (max-width: 768px) {
    .announcement-grid-container {
        grid-template-columns: 1fr;
    }
}
</style>

<div id="announcementWidgetContainer">
    <div class="widget-3d-card">
        <!-- Header Section -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-3d-pulse">
                    📢
                </div>
                <div>
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 18px; letter-spacing: -0.4px;">
                        Papan Pengumuman Sales
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1" style="font-size: 11.5px; font-weight: 800; border: 1px solid rgba(37, 99, 235, 0.2);" id="announcementActiveCount">Aktif</span>
                    </h5>
                    <p class="text-muted mb-0" style="font-size: 13.5px; font-family: 'Inter', sans-serif;">Pemberitahuan resmi promo, price list, & kabar penting dari manajemen Loewix</p>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <a href="announcements.php" class="btn-3d-manage">
                    <i class="bi bi-gear-fill text-primary"></i> Kelola Pengumuman
                </a>
            </div>
        </div>

        <!-- Announcement Cards Grid -->
        <div id="announcementCardsList" class="announcement-grid-container">
            <!-- Dynamic Content Inserted via JS -->
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    loadActiveAnnouncements();
});

function loadActiveAnnouncements() {
    fetch('ajax_announcement_handler.php?action=fetch_active')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('announcementWidgetContainer');
            const list = document.getElementById('announcementCardsList');
            const countBadge = document.getElementById('announcementActiveCount');
            
            if (data.status === 'success' && data.data && data.data.length > 0) {
                if (countBadge) {
                    countBadge.innerText = `${data.data.length} Aktif`;
                }

                let html = '';
                data.data.forEach(item => {
                    let badgeClass = 'badge-3d-info';
                    let badgeIcon = 'ℹ️';
                    let badgeLabel = 'INFORMASI RESMI';
                    let borderLeftColor = '#2563EB';
                    
                    if (item.badge_type === 'promo') {
                        badgeClass = 'badge-3d-promo';
                        badgeIcon = '🚀';
                        badgeLabel = 'PROMO SPESIAL';
                        borderLeftColor = '#10B981';
                    } else if (item.badge_type === 'warning') {
                        badgeClass = 'badge-3d-warning';
                        badgeIcon = '⚠️';
                        badgeLabel = 'PERHATIAN PENTING';
                        borderLeftColor = '#F59E0B';
                    } else if (item.badge_type === 'urgent') {
                        badgeClass = 'badge-3d-urgent';
                        badgeIcon = '🚨';
                        badgeLabel = 'DARURAT / URGENT';
                        borderLeftColor = '#EF4444';
                    }

                    const dateStr = new Date(item.created_at).toLocaleDateString('id-ID', {
                        day: 'numeric', month: 'short', year: 'numeric'
                    });

                    const formattedContent = escapeHtmlAnn(item.content).replace(/\n/g, '<br>');

                    html += `
                    <div class="item-3d-card" style="border-top: 4px solid ${borderLeftColor} !important;">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2.5">
                                <span class="badge ${badgeClass} px-3 py-1.5 rounded-pill">
                                    ${badgeIcon} ${badgeLabel}
                                </span>
                                <span class="badge bg-light text-secondary border px-2.5 py-1 rounded-pill" style="font-size: 11.5px; font-weight: 500;">
                                    📅 ${dateStr}
                                </span>
                            </div>
                            <h5 class="fw-bold text-dark mb-2" style="font-size: 16.5px; font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.3px; line-height: 1.35;">${escapeHtmlAnn(item.title)}</h5>
                            <p class="text-secondary mb-3" style="font-size: 14px; line-height: 1.6; font-family: 'Inter', sans-serif;">${formattedContent}</p>
                        </div>
                        <div class="pt-2 border-top d-flex justify-content-between align-items-center" style="font-size: 12px; color: #64748B;">
                            <span>Diterbitkan Oleh:</span>
                            <span class="fw-bold text-dark">${escapeHtmlAnn(item.created_by)}</span>
                        </div>
                    </div>`;
                });

                list.innerHTML = html;
            } else {
                if (countBadge) countBadge.innerText = '0 Aktif';
                list.innerHTML = `
                <div class="item-3d-card" style="border-top: 4px solid #2563EB !important; grid-column: 1 / -1;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge badge-3d-info px-3 py-1.5 rounded-pill">ℹ️ INFORMASI</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2" style="font-size: 16.5px; font-family: 'Plus Jakarta Sans', sans-serif;">Selamat Datang di Dashboard Sales Loewix 🚀</h5>
                    <p class="text-secondary mb-0" style="font-size: 14px; line-height: 1.6; font-family: 'Inter', sans-serif;">Belum ada pengumuman aktif saat ini. Anda dapat menerbitkan pengumuman baru melalui menu <strong>Kelola Pengumuman</strong>.</p>
                </div>`;
            }
            container.style.display = 'block';
        })
        .catch(err => {
            console.error("Error loading announcements:", err);
            document.getElementById('announcementWidgetContainer').style.display = 'block';
        });
}

function escapeHtmlAnn(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
