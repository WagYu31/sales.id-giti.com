<?php
/**
 * PAPAN PENGUMUMAN OFFICIAL SALES WIDGET - TOP RUNNING TEXT TICKER EDITION
 * DILETAK KAN DI PALING ATAS DASHBOARD (DI BAWAH NAVBAR / SEBELUM HERO WELCOME)
 */
?>
<style>
/* === TOP RUNNING TEXT TICKER BAR STYLES === */
.top-announcement-ticker-bar {
    background: linear-gradient(90deg, #0F172A 0%, #1E293B 60%, #0F172A 100%);
    border-radius: 16px;
    padding: 0 16px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    position: relative;
    border: 1.5px solid rgba(51, 65, 85, 0.8);
    box-shadow: 0 8px 24px -6px rgba(15, 23, 42, 0.3);
    overflow: hidden;
}

.top-announcement-ticker-bar::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #DC2626 0%, #F59E0B 50%, #2563EB 100%);
}

.ticker-left-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
    color: #FFFFFF;
    font-size: 11.5px;
    font-weight: 800;
    padding: 5px 12px;
    border-radius: 12px;
    border: 1px solid #FCD34D;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.4);
    white-space: nowrap;
    flex-shrink: 0;
    z-index: 2;
}

.ticker-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #4ADE80;
    box-shadow: 0 0 8px #4ADE80;
    animation: liveDotPulse 1.5s infinite alternate;
}

@keyframes liveDotPulse {
    0% { opacity: 0.4; transform: scale(0.8); }
    100% { opacity: 1; transform: scale(1.2); }
}

.ticker-track-viewport {
    flex: 1;
    overflow: hidden;
    margin: 0 16px;
    position: relative;
    height: 100%;
    display: flex;
    align-items: center;
}

.ticker-track-content {
    display: flex;
    align-items: center;
    white-space: nowrap;
    animation: tickerMarqueeScroll 28s linear infinite;
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
    gap: 8px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13px;
    color: #F8FAFC;
    padding-right: 28px;
}

.ticker-item-tag {
    font-size: 10.5px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 8px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.ticker-tag-urgent { background: rgba(239, 68, 68, 0.25); color: #FCA5A5; border: 1px solid rgba(239, 68, 68, 0.4); }
.ticker-tag-promo  { background: rgba(16, 185, 129, 0.25); color: #6EE7B7; border: 1px solid rgba(16, 185, 129, 0.4); }
.ticker-tag-warning{ background: rgba(245, 158, 11, 0.25); color: #FDE047; border: 1px solid rgba(245, 158, 11, 0.4); }
.ticker-tag-info   { background: rgba(59, 130, 246, 0.25); color: #93C5FD; border: 1px solid rgba(59, 130, 246, 0.4); }

.ticker-right-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    z-index: 2;
}

.btn-ticker-view {
    background: rgba(255, 255, 255, 0.1);
    color: #FFFFFF;
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 14px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
    white-space: nowrap;
}

.btn-ticker-view:hover {
    background: #2563EB;
    color: #FFFFFF;
    border-color: #2563EB;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.btn-ticker-manage {
    background: #FFFFFF;
    color: #0F172A;
    border: 1px solid #CBD5E1;
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-ticker-manage:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
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
        <!-- Left Badge -->
        <div class="ticker-left-badge">
            <div class="ticker-live-dot"></div>
            <span>📢 PENGUMUMAN</span>
        </div>

        <!-- Center Running Text Viewport -->
        <div class="ticker-track-viewport" onclick="openAnnouncementModal()">
            <div class="ticker-track-content" id="tickerTrackContentHolder">
                <!-- Dynamically Inserted Ticker Items -->
            </div>
        </div>

        <!-- Right Action Buttons -->
        <div class="ticker-right-actions">
            <button type="button" class="btn-ticker-view" onclick="openAnnouncementModal()">
                📋 Lihat Semua (<span id="tickerActiveCount">0</span>)
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
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 24px 24px 0 0; padding: 20px 28px;">
                <div class="d-flex align-items-center gap-2.5">
                    <div style="font-size: 24px;">📢</div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">Papan Pengumuman Resmi Sales</h5>
                        <small class="text-white-50">Pemberitahuan promo, price list, & kabar penting dari manajemen Loewix</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div id="announcementModalGridList" class="announcement-modal-grid">
                    <!-- Dynamic Cards Rendered Here -->
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5 px-4 justify-content-between">
                <span class="text-muted" style="font-size: 12.5px;">Diperbarui secara realtime oleh tim manajemen</span>
                <a href="announcements.php" class="btn btn-outline-primary rounded-pill px-4 btn-sm fw-bold">Kelola Pengumuman</a>
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
                            <span class="fw-bold">${titleStr}</span>
                            <span class="text-white-50">— ${snippetStr}</span>
                            <span class="ms-2 me-2 text-warning">⭐</span>
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
        let badgeClass = 'bg-primary';
        let badgeIcon = 'ℹ️';
        let badgeLabel = 'INFORMASI RESMI';
        let borderTopColor = '#2563EB';

        if (item.badge_type === 'promo') {
            badgeClass = 'bg-success';
            badgeIcon = '🚀';
            badgeLabel = 'PROMO SPESIAL';
            borderTopColor = '#10B981';
        } else if (item.badge_type === 'warning') {
            badgeClass = 'bg-warning text-dark';
            badgeIcon = '⚠️';
            badgeLabel = 'PERHATIAN PENTING';
            borderTopColor = '#F59E0B';
        } else if (item.badge_type === 'urgent') {
            badgeClass = 'bg-danger';
            badgeIcon = '🚨';
            badgeLabel = 'DARURAT / URGENT';
            borderTopColor = '#EF4444';
        }

        const dateStr = new Date(item.created_at).toLocaleDateString('id-ID', {
            day: 'numeric', month: 'short', year: 'numeric'
        });

        const formattedContent = escapeHtmlAnn(item.content).replace(/\n/g, '<br>');

        html += `
        <div class="card border-0 shadow-sm p-3.5 bg-white" style="border-radius: 18px; border-top: 4px solid ${borderTopColor} !important;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge ${badgeClass} px-3 py-1.5 rounded-pill fw-bold" style="font-size: 11px;">
                    ${badgeIcon} ${badgeLabel}
                </span>
                <small class="text-muted fw-semibold">📅 ${dateStr}</small>
            </div>
            <h6 class="fw-bold text-dark mb-2" style="font-size: 16px; font-family: 'Plus Jakarta Sans', sans-serif;">${escapeHtmlAnn(item.title)}</h6>
            <p class="text-secondary mb-3" style="font-size: 13.5px; line-height: 1.6; font-family: 'Inter', sans-serif;">${formattedContent}</p>
            <div class="pt-2 border-top d-flex justify-content-between align-items-center" style="font-size: 11.5px; color: #64748B;">
                <span>Diterbitkan Oleh:</span>
                <span class="fw-bold text-dark">${escapeHtmlAnn(item.created_by)}</span>
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
