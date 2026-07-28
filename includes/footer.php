<?php
// includes/footer.php
?>
    </main><!-- end content-area -->

    <footer style="text-align:center;padding:20px 32px;border-top:1px solid #E2E8F0;">
        <p style="margin:0;font-size:12px;font-weight:500;color:#94A3B8;font-family:'Inter',sans-serif;">
            &copy; <?php echo date('Y'); ?> Loewix Sales. All Rights Reserved.
        </p>
    </footer>
</div><!-- end main-wrapper -->

<!-- GLOBAL HIGH-END ENTERPRISE LIGHTBOX MEDIA VIEWER MODAL -->
<div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(12px);">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content shadow-2xl" style="border-radius:24px; overflow:hidden; border:1px solid rgba(255,255,255,0.15); background:#0F172A;">
      
      <!-- Lightbox Header -->
      <div class="modal-header d-flex flex-wrap align-items-center justify-content-between px-4 py-3" style="background: rgba(15, 23, 42, 0.95); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
        <div class="d-flex align-items-center gap-2 text-truncate" style="max-width: 45%;">
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-bold" id="modalFileTypeBadge" style="font-size:12px;">📷 Media</span>
          <h5 class="modal-title text-white fw-bold text-truncate m-0" id="mediaModalLabel" style="font-size:14.5px;">Media Viewer</h5>
        </div>

        <!-- Quick Toolbar Controls -->
        <div class="d-flex align-items-center gap-2">
            <div id="imageControlGroup" class="d-flex align-items-center gap-1 bg-white bg-opacity-10 p-1 rounded-pill border border-white border-opacity-10 me-2" style="display:none;">
                <button type="button" class="btn btn-sm text-white border-0 px-2.5 py-1 hover-scale" id="btnZoomIn" title="Zoom In (+)">
                    <i class="bi bi-zoom-in fs-6"></i>
                </button>
                <button type="button" class="btn btn-sm text-white border-0 px-2.5 py-1 hover-scale" id="btnZoomOut" title="Zoom Out (-)">
                    <i class="bi bi-zoom-out fs-6"></i>
                </button>
                <button type="button" class="btn btn-sm text-white border-0 px-2.5 py-1 hover-scale" id="btnRotate" title="Putar Gambar (90°)">
                    <i class="bi bi-arrow-clockwise fs-6"></i>
                </button>
                <button type="button" class="btn btn-sm text-white border-0 px-2.5 py-1 hover-scale" id="btnResetZoom" title="Reset Tampilan">
                    <i class="bi bi-aspect-ratio fs-6"></i>
                </button>
            </div>

            <a id="btnDownloadMedia" href="#" download class="btn btn-sm btn-outline-light rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1" style="font-size:12px;">
                <i class="bi bi-download"></i> <span class="d-none d-sm-inline">Download</span>
            </a>
            <a id="btnOpenNewTab" href="#" target="_blank" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1" style="font-size:12px;">
                <i class="bi bi-box-arrow-up-right"></i> <span class="d-none d-sm-inline">Tab Baru</span>
            </a>
            <button type="button" class="btn-close btn-close-white opacity-100 ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>

      <!-- Lightbox Body Canvas -->
      <div class="modal-body p-0 bg-dark d-flex justify-content-center align-items-center overflow-hidden position-relative" id="mediaModalBody" style="min-height: 520px; max-height: 85vh; background: radial-gradient(circle, #1E293B 0%, #090D16 100%) !important;">
      </div>

    </div>
  </div>
</div>

<!-- GLOBAL FLOATING AI ASSISTANT LIVE CHAT WIDGET -->
<style>
.ai-floating-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1E40AF 0%, #2563EB 50%, #38BDF8 100%);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    box-shadow: 0 10px 28px rgba(37, 99, 235, 0.45);
    cursor: pointer;
    z-index: 99999;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    border: 2px solid rgba(255, 255, 255, 0.4);
}

.ai-floating-btn:hover {
    transform: scale(1.1) rotate(6deg);
    box-shadow: 0 14px 36px rgba(37, 99, 235, 0.6);
}

.ai-floating-btn .online-ping {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 13px;
    height: 13px;
    background: #34D399;
    border-radius: 50%;
    border: 2.5px solid #0F172A;
    box-shadow: 0 0 8px #34D399;
}

.ai-chat-drawer {
    position: fixed;
    bottom: 92px;
    right: 24px;
    width: 410px;
    max-width: calc(100vw - 32px);
    height: 560px;
    max-height: calc(100vh - 110px);
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    z-index: 99998;
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.9);
    animation: drawerPop 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes drawerPop {
    from { opacity: 0; transform: translateY(18px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.ai-drawer-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 10px 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.btn-drawer-copy {
    background: #F1F5F9;
    border: 1px solid #CBD5E1;
    color: #334155;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10.5px;
    font-weight: 700;
    cursor: pointer;
}
.btn-drawer-copy:hover {
    background: #2563EB; color: #FFF; border-color: #2563EB;
}
</style>

<div class="ai-floating-btn" id="aiFloatingBtn" title="Tanya Asisten Sales Loewix AI">
    <i class="bi bi-robot"></i>
    <span class="online-ping"></span>
</div>

<div class="ai-chat-drawer" id="aiChatDrawer">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between px-3 py-2.5" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-primary bg-gradient d-flex align-items-center justify-content-center text-white fw-bold" style="width:34px; height:34px; font-size:18px;">
                <i class="bi bi-robot"></i>
            </div>
            <div>
                <h6 class="text-white fw-bold m-0" style="font-size:13.5px; font-family:'Plus Jakarta Sans', sans-serif;">Asisten Sales Loewix AI</h6>
                <small class="text-success fw-bold" style="font-size:10.5px;">🟢 Real-Time Sales & Negotiation Coach</small>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white btn-sm opacity-100" id="aiChatDrawerClose"></button>
    </div>

    <!-- Chat Canvas -->
    <div class="p-3 overflow-y-auto flex-grow-1 bg-light d-flex flex-column gap-2" id="aiDrawerCanvas" style="font-size:12.5px;">
        <div class="ai-drawer-card border-start border-4 border-primary">
            <strong class="text-dark">👋 Halo Sales Loewix!</strong><br>
            Ada pertanyaan customer, penolakan harga, atau konsultasi prospek? Ketik di bawah untuk 3 taktik skrip WA & upsell otomatis.
        </div>
    </div>

    <!-- Quick Prompts Bar -->
    <div class="px-2 py-1.5 bg-white border-top border-slate-200 d-flex gap-1 overflow-x-auto" style="white-space:nowrap;">
        <span class="badge bg-light text-dark border px-2 py-1 rounded-pill cursor-pointer" onclick="sendDrawerPrompt('Customer minta diskon 20%, cara negosiasi agar margin aman?')">🤝 Nego Diskon</span>
        <span class="badge bg-light text-dark border px-2 py-1 rounded-pill cursor-pointer" onclick="sendDrawerPrompt('Customer membandingkan harga Loewix dengan merek murah lain')">⚖️ Nego Merek</span>
        <span class="badge bg-light text-dark border px-2 py-1 rounded-pill cursor-pointer" onclick="sendDrawerPrompt('Saya dapat customer Kepala Sekolah, skrip penawarannya gimana?')">🎓 Kepala Sekolah</span>
    </div>

    <!-- Input Form -->
    <form class="p-2 bg-white border-top border-slate-200 d-flex gap-1.5" id="aiDrawerForm">
        <input type="text" id="aiDrawerInput" class="form-control form-control-sm rounded-pill px-3" placeholder="Ketik pertanyaan / konsultasi..." autocomplete="off">
        <button type="submit" class="btn btn-primary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width:34px; height:34px; flex-shrink:0;">
            <i class="bi bi-send-fill" style="font-size:12px;"></i>
        </button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
let currentZoom = 1;
let currentRotation = 0;

function updateImageTransform(imgEl) {
    if (!imgEl) return;
    imgEl.style.transform = `scale(${currentZoom}) rotate(${currentRotation}deg)`;
    imgEl.style.transition = 'transform 0.25s cubic-bezier(0.16, 1, 0.3, 1)';
}

document.addEventListener('DOMContentLoaded', function() {
    // --- LIGHTBOX MODAL ENGINE ---
    const mediaModal = document.getElementById('mediaModal');
    if (mediaModal) {
        const modalTitle = document.getElementById('mediaModalLabel');
        const modalBadge = document.getElementById('modalFileTypeBadge');
        const modalBody = document.getElementById('mediaModalBody');
        const btnDownload = document.getElementById('btnDownloadMedia');
        const btnOpenTab = document.getElementById('btnOpenNewTab');
        const imgControlGroup = document.getElementById('imageControlGroup');

        mediaModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const fileUrl = button.getAttribute('data-file-url');
            const fileName = button.getAttribute('data-file-name') || 'Media File';
            const ext = fileName.split('.').pop().toLowerCase();

            currentZoom = 1;
            currentRotation = 0;

            modalTitle.textContent = fileName;
            if (btnDownload) { btnDownload.href = fileUrl; btnDownload.download = fileName; }
            if (btnOpenTab) { btnOpenTab.href = fileUrl; }

            modalBody.innerHTML = '';
            if (imgControlGroup) imgControlGroup.style.display = 'none';

            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
                if (modalBadge) {
                    modalBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-bold';
                    modalBadge.innerHTML = '🖼️ Bukti Chat (Gambar)';
                }
                if (imgControlGroup) imgControlGroup.style.display = 'inline-flex';

                modalBody.innerHTML = `
                    <div class="w-100 h-100 d-flex justify-content-center align-items-center p-3 overflow-hidden">
                        <img id="lightboxImage" src="${fileUrl}" class="img-fluid rounded-3 shadow-2xl" style="max-height:78vh; object-fit:contain; cursor:grab; user-select:none;" alt="${fileName}">
                    </div>
                `;

                const imgEl = document.getElementById('lightboxImage');
                if (imgEl) {
                    modalBody.onwheel = function(e) {
                        e.preventDefault();
                        if (e.deltaY < 0) currentZoom = Math.min(currentZoom + 0.15, 4);
                        else currentZoom = Math.max(currentZoom - 0.15, 0.5);
                        updateImageTransform(imgEl);
                    };

                    imgEl.ondblclick = function() {
                        currentZoom = 1;
                        currentRotation = 0;
                        updateImageTransform(imgEl);
                    };
                }

            } else if (ext === 'pdf') {
                if (modalBadge) {
                    modalBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 rounded-pill fw-bold';
                    modalBadge.innerHTML = '📄 Dokumen PDF';
                }
                modalBody.innerHTML = `<iframe src="${fileUrl}" style="width:100%; height:78vh; border:none;" frameborder="0"></iframe>`;

            } else if (['mp4', 'webm', 'mov', 'mkv'].includes(ext)) {
                if (modalBadge) {
                    modalBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-bold';
                    modalBadge.innerHTML = '🎥 Video';
                }
                modalBody.innerHTML = `
                    <video controls autoplay class="w-100 rounded-3 shadow-2xl" style="max-height:78vh;">
                        <source src="${fileUrl}">
                        Browser Anda tidak mendukung elemen video ini.
                    </video>
                `;

            } else if (['mp3', 'wav', 'ogg', 'm4a', 'aac'].includes(ext)) {
                if (modalBadge) {
                    modalBadge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1.5 rounded-pill fw-bold';
                    modalBadge.innerHTML = '🎵 Audio Voice';
                }
                modalBody.innerHTML = `
                    <div class="text-center p-5 w-100">
                        <div class="mb-4"><i class="bi bi-music-note-beamed text-info" style="font-size: 5rem;"></i></div>
                        <h5 class="text-white mb-3 fw-bold">${fileName}</h5>
                        <audio controls autoplay class="w-75">
                            <source src="${fileUrl}">
                        </audio>
                    </div>
                `;

            } else {
                if (modalBadge) {
                    modalBadge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1.5 rounded-pill fw-bold';
                    modalBadge.innerHTML = '📁 File Document';
                }
                modalBody.innerHTML = `
                    <div class="text-center p-5 text-white">
                        <i class="bi bi-file-earmark-arrow-down text-primary fs-1 mb-3"></i>
                        <h5 class="text-white fw-bold mb-3">${fileName}</h5>
                        <p class="text-muted small mb-4">Pratinjau tidak tersedia untuk format ini.</p>
                        <a href="${fileUrl}" class="btn btn-primary fw-bold px-4 py-2 rounded-pill" download><i class="bi bi-download me-1"></i> Download File</a>
                    </div>
                `;
            }
        });

        document.getElementById('btnZoomIn')?.addEventListener('click', () => {
            currentZoom = Math.min(currentZoom + 0.25, 4);
            updateImageTransform(document.getElementById('lightboxImage'));
        });
        document.getElementById('btnZoomOut')?.addEventListener('click', () => {
            currentZoom = Math.max(currentZoom - 0.25, 0.5);
            updateImageTransform(document.getElementById('lightboxImage'));
        });
        document.getElementById('btnRotate')?.addEventListener('click', () => {
            currentRotation = (currentRotation + 90) % 360;
            updateImageTransform(document.getElementById('lightboxImage'));
        });
        document.getElementById('btnResetZoom')?.addEventListener('click', () => {
            currentZoom = 1;
            currentRotation = 0;
            updateImageTransform(document.getElementById('lightboxImage'));
        });

        mediaModal.addEventListener('hidden.bs.modal', function() {
            const media = modalBody.querySelector('video, audio');
            if (media) { media.pause(); media.src = ''; }
            modalBody.innerHTML = '';
            modalBody.onwheel = null;
        });
    }

    // --- GLOBAL FLOATING AI CHAT WIDGET ENGINE ---
    const floatBtn = document.getElementById('aiFloatingBtn');
    const drawer = document.getElementById('aiChatDrawer');
    const closeBtn = document.getElementById('aiChatDrawerClose');
    const drawerForm = document.getElementById('aiDrawerForm');
    const drawerInput = document.getElementById('aiDrawerInput');
    const drawerCanvas = document.getElementById('aiDrawerCanvas');

    if (floatBtn && drawer) {
        floatBtn.addEventListener('click', () => {
            drawer.style.display = (drawer.style.display === 'flex') ? 'none' : 'flex';
            if (drawer.style.display === 'flex') drawerInput?.focus();
        });

        closeBtn?.addEventListener('click', () => {
            drawer.style.display = 'none';
        });

        window.sendDrawerPrompt = function(promptText) {
            if (drawerInput) {
                drawerInput.value = promptText;
                drawerForm.dispatchEvent(new Event('submit'));
            }
        };

        drawerForm?.addEventListener('submit', function(e) {
            e.preventDefault();
            const text = drawerInput.value.trim();
            if (!text) return;

            // User Bubble
            const userMsg = document.createElement('div');
            userMsg.className = 'text-end mb-2';
            userMsg.innerHTML = `<span class="bg-primary text-white p-2 px-3 rounded-pill d-inline-block fw-bold">${escapeHtml(text)}</span>`;
            drawerCanvas.appendChild(userMsg);
            drawerInput.value = '';
            drawerCanvas.scrollTop = drawerCanvas.scrollHeight;

            // Thinking Indicator
            const thinkDiv = document.createElement('div');
            thinkDiv.className = 'ai-drawer-card mb-2 text-primary fw-bold';
            thinkDiv.id = 'drawerThinking';
            thinkDiv.innerHTML = '<i class="bi bi-cpu spinner-border spinner-border-sm me-1"></i> Asisten Loewix AI sedang menyusun 3 taktik...';
            drawerCanvas.appendChild(thinkDiv);
            drawerCanvas.scrollTop = drawerCanvas.scrollHeight;

            fetch('ai_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: text })
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('drawerThinking')?.remove();
                if (data.answers && data.answers.length > 0) {
                    data.answers.forEach((ans, idx) => {
                        let itemType = (typeof ans === 'object' && ans.type) ? ans.type : `Opsi ${idx+1}`;
                        let itemStrategy = (typeof ans === 'object' && ans.strategy) ? ans.strategy : '';
                        let itemText = (typeof ans === 'object' && ans.text) ? ans.text : (typeof ans === 'string' ? ans : '');
                        let itemProduct = (typeof ans === 'object' && ans.product_recommendation) ? ans.product_recommendation : '';

                        const escapedText = escapeHtml(itemText);
                        const card = document.createElement('div');
                        card.className = 'ai-drawer-card mb-2';
                        card.innerHTML = `
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 rounded-pill fw-bold" style="font-size:10px;">${escapeHtml(itemType)}</span>
                                <button type="button" class="btn-drawer-copy" onclick="copyDrawerText(this, \`${escapedText.replace(/`/g, '\\`')}\`)">📋 Salin</button>
                            </div>
                            ${itemStrategy ? `<div class="text-muted mb-1" style="font-size:11px;"><strong>💡 Tips:</strong> ${escapeHtml(itemStrategy)}</div>` : ''}
                            <div class="text-dark fw-normal mb-1"><strong>💬 Teks WA:</strong><br>${escapedText}</div>
                            ${itemProduct ? `<div class="badge bg-light text-primary border" style="font-size:10px;">📦 ${escapeHtml(itemProduct)}</div>` : ''}
                        `;
                        drawerCanvas.appendChild(card);
                    });
                } else {
                    const errCard = document.createElement('div');
                    errCard.className = 'ai-drawer-card mb-2 text-danger';
                    errCard.textContent = 'Gagal mendapatkan jawaban dari server.';
                    drawerCanvas.appendChild(errCard);
                }
                drawerCanvas.scrollTop = drawerCanvas.scrollHeight;
            })
            .catch(err => {
                document.getElementById('drawerThinking')?.remove();
                console.error(err);
            });
        });

        window.copyDrawerText = function(btnEl, text) {
            navigator.clipboard.writeText(text).then(() => {
                const orig = btnEl.innerHTML;
                btnEl.innerHTML = '✓ Disalin!';
                btnEl.style.background = '#10B981';
                btnEl.style.color = '#FFF';
                setTimeout(() => {
                    btnEl.innerHTML = orig;
                    btnEl.style.background = '#F1F5F9';
                    btnEl.style.color = '#334155';
                }, 2000);
            });
        };

        function escapeHtml(text) {
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
    }

    if ($('.sortable-table').length) {
        $('.sortable-table').DataTable({
            "order": [],
            "paging": false,
            "info": false,
            "searching": false,
            "deferRender": true,
            "language": {
                "sProcessing":   "Sedang memproses...",
                "sZeroRecords":  "Tidak ditemukan data yang sesuai"
            }
        });
    }
});
</script>
</body>
</html>