<?php
/**
 * nav-top.php - Top Bar Navigation Bridge
 */
$pageDisplay = $pageNow ?? ($page_title ?? 'Aplikasi Sales');
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white rounded-3 shadow-sm px-3 py-2.5 mb-4 border">
    <div class="container-fluid p-0 d-flex align-items-center justify-content-between">
        <!-- Left: Mobile Toggle & Page Title -->
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-secondary d-lg-none py-1 px-2.5 rounded-2" type="button" onclick="document.getElementById('sidebar').classList.toggle('show')">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0" style="font-size: 12px;">
                        <li class="breadcrumb-item"><a href="../customer_management.php" class="text-decoration-none text-muted">Loewix Sales</a></li>
                        <li class="breadcrumb-item active text-primary fw-semibold" aria-current="page"><?php echo htmlspecialchars($pageDisplay); ?></li>
                    </ol>
                </nav>
                <h5 class="mb-0 fw-bold text-dark" style="font-family:'Outfit',sans-serif; letter-spacing: -0.01em;">
                    <?php echo htmlspecialchars($pageDisplay); ?>
                </h5>
            </div>
        </div>

        <!-- Right: Realtime Time & Back to Sales Dashboard -->
        <div class="d-flex align-items-center gap-2">
            <div class="d-none d-md-flex align-items-center gap-2 bg-light border px-3 py-1.5 rounded-pill" style="font-size: 13px;">
                <i class="bi bi-calendar-event text-primary"></i>
                <span class="fw-semibold text-dark" id="navTopLiveDate"><?php echo date('d M Y'); ?></span>
                <span class="text-muted">|</span>
                <i class="bi bi-clock-fill text-primary"></i>
                <span class="fw-bold text-primary font-monospace" id="navTopLiveTime"><?php echo date('H:i'); ?></span>
            </div>

            <a href="../customer_management.php" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1.5 rounded-pill px-3 py-1.5 fw-semibold" style="font-size: 12.5px;">
                <i class="bi bi-house-door-fill"></i>
                <span>Dashboard Utama</span>
            </a>
        </div>
    </div>
</nav>

<script>
// Live clock for nav-top
(function() {
    function updateClock() {
        const now = new Date();
        const timeEl = document.getElementById('navTopLiveTime');
        if (timeEl) {
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            timeEl.textContent = h + ':' + m + ':' + s;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();
})();
</script>
