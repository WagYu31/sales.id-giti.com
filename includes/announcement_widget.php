<?php
/**
 * PAPAN PENGUMUMAN OFFICIAL SALES WIDGET - ULTRA CLEAN LIGHT TICKER BANNER
 * DILETAK KAN DI PALING ATAS DASHBOARD (DI BAWAH NAVBAR / SEBELUM HERO WELCOME)
 */
?>
<style>
/* === ULTRA CLEAN LIGHT TICKER BANNER === */
.top-announcement-ticker-bar {
    background: #FFFFFF;
    border-radius: 18px;
    padding: 0 18px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    position: relative;
    border: 1.5px solid #E2E8F0;
    box-shadow: 0 8px 24px -8px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.02);
    overflow: hidden;
}

.top-announcement-ticker-bar::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3.5px;
    background: linear-gradient(90deg, #DC2626 0%, #F59E0B 50%, #2563EB 100%);
    box-shadow: 0 1px 6px rgba(220, 38, 38, 0.3);
}

.ticker-badge-right {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
    color: #FFFFFF;
    font-size: 11.5px;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 14px;
    border: 1px solid #FCD34D;
    box-shadow: 0 3px 10px rgba(220, 38, 38, 0.35);
    white-space: nowrap;
    flex-shrink: 0;
    z-index: 5;
}

.ticker-live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22C55E;
    box-shadow: 0 0 10px #22C55E;
    animation: liveDotPulse 1.5s infinite alternate;
}

@keyframes liveDotPulse {
    0% { opacity: 0.4; transform: scale(0.8); }
    100% { opacity: 1; transform: scale(1.25); }
}

.ticker-track-viewport {
    flex: 1;
    overflow: hidden;
    margin: 0 16px 0 0;
    position: relative;
    height: 100%;
    display: flex;
    align-items: center;
    -webkit-mask-image: linear-gradient(to right, transparent 0, black 16px, black calc(100% - 24px), transparent 100%);
    mask-image: linear-gradient(to right, transparent 0, black 16px, black calc(100% - 24px), transparent 100%);
}

.ticker-track-content {
    display: flex;
    align-items: center;
    white-space: nowrap;
    animation: tickerMarqueeScroll 12s linear infinite;
    cursor: pointer;
}

.ticker-track-viewport:hover .ticker-track-content {
    animation-play-state: paused;
}

@keyframes tickerMarqueeScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.ticker-item-inline {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13.5px;
    color: #1E293B;
    padding-right: 32px;
}

.ticker-item-tag {
    font-size: 10.5px;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.ticker-tag-urgent { background: linear-gradient(135deg, #EF4444, #DC2626); color: #FFFFFF; }
.ticker-tag-promo  { background: linear-gradient(135deg, #10B981, #059669); color: #FFFFFF; }
.ticker-tag-warning{ background: linear-gradient(135deg, #F59E0B, #D97706); color: #FFFFFF; }
.ticker-tag-info   { background: linear-gradient(135deg, #3B82F6, #1D4ED8); color: #FFFFFF; }

.ticker-right-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    z-index: 5;
}

.btn-ticker-view {
    background: #EFF6FF;
    color: #2563EB;
    border: 1.5px solid #BFDBFE;
    font-size: 12px;
    font-weight: 700;
    padding: 5px 16px;
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-ticker-view:hover {
    background: #2563EB;
    color: #FFFFFF;
    border-color: #2563EB;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    transform: translateY(-1px);
}

.btn-ticker-manage {
    background: #F8FAFC;
    color: #475569;
    border: 1.5px solid #CBD5E1;
    font-size: 12px;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-ticker-manage:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
    transform: translateY(-1px);
}

/* Modal Grid Styles */
.announcement-modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 16px;
}
</style>

<!-- TOP RUNNING TEXT TICKER BAR CONTAINER -->
<div id="topAnnouncementTickerContainer" style="display: none;">
    <div class="top-announcement-ticker-bar">
        <!-- Running Text Viewport (Left Side) -->
        <div class="ticker-track-viewport" onclick="openAnnouncementModal()">
            <div class="ticker-track-content" id="tickerTrackContentHolder">
                <!-- Dynamically Inserted Ticker Items -->
            </div>
        </div>

        <!-- Right Group: Speaker Logo & Badge + Action Buttons -->
        <div class="ticker-right-actions">
            <div class="ticker-badge-right">
                <div class="ticker-live-dot"></div>
                <span>📢 PENGUMUMAN</span>
            </div>
            <button type="button" class="btn-ticker-view" onclick="openAnnouncementModal()">
                <span>📋 Lihat Detail</span>
                <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size: 10.5px;" id="tickerActiveCount">0</span>
            </button>
            <a href="announcements.php" class="btn-ticker-manage">
                ⚙️ Kelola
            </a>
        </div>
    </div>
</div>

<!-- MODAL FULL DETAIL PENGUMUMAN SALES -->
<div class="modal fade" id="announcementFullModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-2xl" style="border-radius: 28px; overflow: hidden; background: #F8FAFC;">
            <!-- Modern Premium Hero Header -->
            <div class="modal-header text-white border-0 position-relative" style="background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%); padding: 24px 32px; overflow: hidden;">
                <!-- Decorative Ambient Light Orbs -->
                <div style="position: absolute; top: -60px; right: -60px; width: 220px; height: 220px; border-radius: 50%; background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, transparent 70%); pointer-events: none;"></div>
                <div style="position: absolute; bottom: -40px; left: -40px; width: 160px; height: 160px; border-radius: 50%; background: radial-gradient(circle, rgba(245, 158, 11, 0.2) 0%, transparent 70%); pointer-events: none;"></div>
                
                <div class="d-flex align-items-center gap-3.5 position-relative" style="z-index: 2;">
                    <div style="width: 52px; height: 52px; background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 26px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);">
                        📢
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-warning text-dark fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10px; letter-spacing: 0.5px;">RESMI & TERVERIFIKASI</span>
                            <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-2.5 py-0.5" style="font-size: 10px;">LOEWIX MANAJEMEN</span>
                        </div>
                        <h5 class="modal-title fw-extrabold mb-0 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 20px; letter-spacing: -0.4px;">Papan Pengumuman Resmi Sales</h5>
                        <p class="mb-0 text-white-50 mt-0.5" style="font-size: 12.5px;">Pemberitahuan promo, price list, & kabar penting dari manajemen Loewix</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white position-relative" style="z-index: 2;" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4" style="background-color: #F1F5F9;">
                <div id="announcementModalGridList" class="announcement-modal-grid">
                    <!-- Dynamic Cards Rendered Here -->
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top border-slate-200 py-3 px-4 justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 12.5px;">
                    <span class="d-inline-block rounded-circle bg-success" style="width: 8px; height: 8px; box-shadow: 0 0 8px #10B981;"></span>
                    <span class="fw-semibold text-slate-600">Diperbarui secara realtime oleh tim manajemen</span>
                </div>
                <a href="announcements.php" class="btn btn-primary rounded-pill px-4 py-2 text-sm fw-bold shadow-sm d-inline-flex align-items-center gap-1.5" style="background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); border: none;">
                    ⚙️ Kelola Pengumuman
                </a>
            </div>
        </div>
    </div>
</div>

<script>
let cachedAnnouncementsData = [];

document.addEventListener("DOMContentLoaded", function() {
    loadActiveAnnouncementsTicker();
});

function loadActiveAnnouncementsTicker() {
    fetch('ajax_announcement_handler.php?action=fetch_active')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('topAnnouncementTickerContainer');
            const trackHolder = document.getElementById('tickerTrackContentHolder');
            const countSpan = document.getElementById('tickerActiveCount');

            if (data.status === 'success' && data.data && data.data.length > 0) {
                cachedAnnouncementsData = data.data;
                if (countSpan) countSpan.innerText = data.data.length;

                let tickerItemsHtml = '';
                data.data.forEach(item => {
                    let tagClass = 'ticker-tag-info';
                    let tagLabel = 'INFO';
                    if (item.badge_type === 'promo') { tagClass = 'ticker-tag-promo'; tagLabel = 'PROMO'; }
                    else if (item.badge_type === 'warning') { tagClass = 'ticker-tag-warning'; tagLabel = 'PERHATIAN'; }
                    else if (item.badge_type === 'urgent') { tagClass = 'ticker-tag-urgent'; tagLabel = 'DARURAT'; }

                    const titleStr = escapeHtmlAnn(item.title);
                    const snippetStr = escapeHtmlAnn(item.content).replace(/(\r\n|\n|\r)/gm, " ");

                    tickerItemsHtml += `
                        <div class="ticker-item-inline">
                            <span class="ticker-item-tag ${tagClass}">${tagLabel}</span>
                            <span class="fw-bold text-dark">${titleStr}</span>
                            <span class="text-secondary">— ${snippetStr}</span>
                            <span class="ms-2 me-2 text-warning fw-bold">⭐</span>
                        </div>
                    `;
                });

                // Duplicate sequence twice to create a seamless infinite loop marquee
                trackHolder.innerHTML = tickerItemsHtml + tickerItemsHtml;
                container.style.display = 'block';

                // Render full cards for modal
                renderAnnouncementModalCards(data.data);
            } else {
                container.style.display = 'none';
            }
        })
        .catch(err => {
            console.error("Error loading announcement ticker:", err);
        });
}

function renderAnnouncementModalCards(dataList) {
    const modalGrid = document.getElementById('announcementModalGridList');
    if (!modalGrid) return;

    let html = '';
    dataList.forEach(item => {
        let badgeStyle = 'background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #FFF;';
        let badgeIcon = 'ℹ️';
        let badgeLabel = 'INFORMASI RESMI';
        let accentGradient = 'linear-gradient(90deg, #3B82F6, #60A5FA)';
        let shadowStyle = 'box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);';

        if (item.badge_type === 'promo') {
            badgeStyle = 'background: linear-gradient(135deg, #059669, #10B981); color: #FFF;';
            badgeIcon = '🚀';
            badgeLabel = 'PROMO SPESIAL';
            accentGradient = 'linear-gradient(90deg, #10B981, #34D399)';
            shadowStyle = 'box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.12), 0 4px 6px -2px rgba(0, 0, 0, 0.05);';
        } else if (item.badge_type === 'warning') {
            badgeStyle = 'background: linear-gradient(135deg, #D97706, #F59E0B); color: #FFF;';
            badgeIcon = '⚠️';
            badgeLabel = 'PERHATIAN PENTING';
            accentGradient = 'linear-gradient(90deg, #F59E0B, #FCD34D)';
            shadowStyle = 'box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.15), 0 4px 6px -2px rgba(0, 0, 0, 0.05);';
        } else if (item.badge_type === 'urgent') {
            badgeStyle = 'background: linear-gradient(135deg, #DC2626, #EF4444); color: #FFF;';
            badgeIcon = '🚨';
            badgeLabel = 'DARURAT / URGENT';
            accentGradient = 'linear-gradient(90deg, #EF4444, #F87171)';
            shadowStyle = 'box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.18), 0 4px 6px -2px rgba(0, 0, 0, 0.05);';
        }

        const dateStr = new Date(item.created_at).toLocaleDateString('id-ID', {
            day: 'numeric', month: 'short', year: 'numeric'
        });

        const formattedContent = escapeHtmlAnn(item.content).replace(/\n/g, '<br>');

        html += `
        <div class="card border-0 p-4 bg-white position-relative" style="border-radius: 22px; ${shadowStyle}">
            <!-- Top Gradient Accent Line -->
            <div style="position: absolute; top: 0; left: 24px; right: 24px; height: 4px; background: ${accentGradient}; border-radius: 0 0 8px 8px;"></div>
            
            <div class="d-flex justify-content-between align-items-center mb-3 mt-1">
                <span class="badge px-3 py-1.5 rounded-pill fw-bold d-inline-flex align-items-center gap-1.5" style="${badgeStyle} font-size: 11px; letter-spacing: 0.3px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <span>${badgeIcon}</span> ${badgeLabel}
                </span>
                <div class="d-flex align-items-center gap-1 text-slate-500 fw-semibold" style="font-size: 11.5px; background: #F1F5F9; color: #475569; padding: 4px 12px; border-radius: 20px;">
                    <span>📅</span> ${dateStr}
                </div>
            </div>
            
            <h6 class="fw-extrabold text-slate-900 mb-2.5" style="font-size: 17.5px; font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.3px; line-height: 1.35; color: #0F172A;">
                ${escapeHtmlAnn(item.title)}
            </h6>
            
            <div class="text-slate-600 mb-3.5" style="font-size: 14px; line-height: 1.65; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; background: #F8FAFC; padding: 14px 16px; border-radius: 14px; border: 1px solid #E2E8F0;">
                ${formattedContent}
            </div>
            
            <div class="pt-2.5 border-top border-slate-100 d-flex justify-content-between align-items-center" style="font-size: 12px; color: #64748B;">
                <div class="d-flex align-items-center gap-1.5">
                    <span style="font-size: 14px;">✍️</span>
                    <span>Diterbitkan Oleh:</span>
                </div>
                <span class="badge bg-slate-100 text-slate-800 fw-bold px-2.5 py-1 rounded-pill" style="font-size: 11.5px; background: #E2E8F0; color: #0F172A;">
                    ${escapeHtmlAnn(item.created_by)}
                </span>
            </div>
        </div>`;
    });

    modalGrid.innerHTML = html;
}

function openAnnouncementModal() {
    const modalEl = document.getElementById('announcementFullModal');
    if (modalEl) {
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

function escapeHtmlAnn(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
