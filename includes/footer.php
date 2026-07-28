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
    const mediaModal = document.getElementById('mediaModal');
    if (!mediaModal) return;

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
                // Mousewheel Zoom
                modalBody.onwheel = function(e) {
                    e.preventDefault();
                    if (e.deltaY < 0) currentZoom = Math.min(currentZoom + 0.15, 4);
                    else currentZoom = Math.max(currentZoom - 0.15, 0.5);
                    updateImageTransform(imgEl);
                };

                // Double Click Reset
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

    // Control Button Listeners
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