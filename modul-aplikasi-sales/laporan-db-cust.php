<?php
/**
 * laporan-db-cust.php - Laporan Kunjungan & Visit Sales (Modern & User-Friendly)
 * Loewix Sales Management System
 */

// Filter variables
$filterSales   = isset($_GET['id_sales']) ? intval($_GET['id_sales']) : 0;
$filterBulan   = isset($_GET['bulan']) ? trim($_GET['bulan']) : date("Y-m");
$filterTanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$filterStatus  = isset($_GET['status']) ? trim(strtolower($_GET['status'])) : '';

if (!empty($filterTanggal)) {
    $current_date = $filterTanggal;
    $dateLabel = date('d M Y', strtotime($filterTanggal));
} else {
    $current_date = $filterBulan . '-01';
    $dateLabel = date('F Y', strtotime($filterBulan . '-01'));
}

// Fetch sales list for dropdown
$resSalesList = mysqli_query($conn, "SELECT id, nama FROM sales WHERE deleted_at IS NULL ORDER BY nama ASC");
$salesOptions = [];
if ($resSalesList) {
    while ($rS = mysqli_fetch_assoc($resSalesList)) {
        $salesOptions[] = $rS;
    }
}

// ── 1. Hitung Statistik KPI untuk Periode Terpilih ─────────────────────────
$statsWhere = ["ks.deleted_at IS NULL"];
if ($filterSales > 0) {
    $statsWhere[] = "ks.id IN (SELECT id_kegiatan_sales FROM team_kegiatan_sales WHERE id_sales = $filterSales AND deleted_at IS NULL)";
}
if (!empty($filterTanggal)) {
    $statsWhere[] = "DATE(ks.jadwal) = '" . mysqli_real_escape_string($conn, $filterTanggal) . "'";
} else if (!empty($filterBulan)) {
    $statsWhere[] = "DATE_FORMAT(ks.jadwal, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filterBulan) . "'";
}
$statsWhereSql = implode(" AND ", $statsWhere);

// Total
$qTotal = mysqli_query($conn, "SELECT COUNT(DISTINCT ks.id) as total FROM kegiatan_sales ks WHERE $statsWhereSql");
$kpiTotal = ($qTotal && $rT = mysqli_fetch_assoc($qTotal)) ? (int)$rT['total'] : 0;

// Selesai
$qSelesai = mysqli_query($conn, "
    SELECT COUNT(DISTINCT ks.id) as total 
    FROM kegiatan_sales ks 
    LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = ks.id 
    WHERE $statsWhereSql AND (ps.status = 'selesai' OR ks.status = 'selesai')
");
$kpiSelesai = ($qSelesai && $rS = mysqli_fetch_assoc($qSelesai)) ? (int)$rS['total'] : 0;

// Berjalan / Proses
$qBerjalan = mysqli_query($conn, "
    SELECT COUNT(DISTINCT ks.id) as total 
    FROM kegiatan_sales ks 
    LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = ks.id 
    WHERE $statsWhereSql AND (ps.status IN ('berjalan', 'proses') OR ks.status = 'berjalan')
");
$kpiBerjalan = ($qBerjalan && $rB = mysqli_fetch_assoc($qBerjalan)) ? (int)$rB['total'] : 0;

// Dijadwalkan
$kpiDijadwalkan = max(0, $kpiTotal - $kpiSelesai - $kpiBerjalan);
$pctSelesai = ($kpiTotal > 0) ? round(($kpiSelesai / $kpiTotal) * 100) : 0;

// ── 2. Build Query Utama Kunjungan ─────────────────────────────────────────
$whereClauses = ["ks.deleted_at IS NULL"];
if ($filterSales > 0) {
    $whereClauses[] = "ks.id IN (SELECT id_kegiatan_sales FROM team_kegiatan_sales WHERE id_sales = $filterSales AND deleted_at IS NULL)";
}
if (!empty($filterTanggal)) {
    $whereClauses[] = "DATE(ks.jadwal) = '" . mysqli_real_escape_string($conn, $filterTanggal) . "'";
} else if (!empty($filterBulan)) {
    $whereClauses[] = "DATE_FORMAT(ks.jadwal, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filterBulan) . "'";
}
if (!empty($filterStatus)) {
    if ($filterStatus === 'dijadwalkan') {
        $whereClauses[] = "ks.id NOT IN (SELECT kegiatan_id FROM pelaksanaan_sales WHERE status IN ('selesai', 'berjalan', 'proses')) AND ks.status != 'selesai'";
    } else if ($filterStatus === 'berjalan') {
        $whereClauses[] = "(ks.id IN (SELECT kegiatan_id FROM pelaksanaan_sales WHERE status IN ('berjalan', 'proses')) OR ks.status = 'berjalan')";
    } else if ($filterStatus === 'selesai') {
        $whereClauses[] = "(ks.id IN (SELECT kegiatan_id FROM pelaksanaan_sales WHERE status = 'selesai') OR ks.status = 'selesai')";
    }
}

$whereSql = implode(" AND ", $whereClauses);

$sql = "SELECT ks.id, ks.id AS kode_transaksi, ks.jadwal AS tgl_visits, ks.status AS status_kegiatan,
               sc.nama AS nama_cust, sc.id AS id_cust, sc.alamat AS alamat_cust, sc.kota AS kota_cust
        FROM kegiatan_sales ks
        INNER JOIN sales_customer sc ON ks.id_customer = sc.id
        WHERE $whereSql
        ORDER BY ks.jadwal DESC";

$result = mysqli_query($conn, $sql);
?>

<style>
/* ── LOEWIX MODERN REPORT DESIGN SYSTEM ─────────────────────────────────── */
.kpi-card {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.07);
}
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 4px;
}
.kpi-card.kpi-total::before { background: #3b82f6; }
.kpi-card.kpi-selesai::before { background: #10b981; }
.kpi-card.kpi-berjalan::before { background: #f59e0b; }
.kpi-card.kpi-jadwal::before { background: #64748b; }

.customer-report-card {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.03);
    transition: all 0.25s ease;
    overflow: hidden;
    margin-bottom: 20px;
}
.customer-report-card:hover {
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    border-color: #cbd5e1;
}
.customer-card-header {
    background: #ffffff;
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.report-table-header {
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}

.report-item-row {
    padding: 14px 20px;
    border-bottom: 1px solid #f8fafc;
    transition: background 0.2s;
}
.report-item-row:last-child {
    border-bottom: none;
}
.report-item-row:hover {
    background: #fcfdfe;
}

.avatar-initial {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    color: #ffffff;
    flex-shrink: 0;
}

.action-btn-modern {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid transparent;
    transition: all 0.15s ease;
    font-size: 13px;
}
.action-btn-modern:hover {
    transform: translateY(-1px);
}
.action-btn-view {
    background: #eff6ff;
    color: #2563eb;
    border-color: #dbeafe;
}
.action-btn-view:hover {
    background: #2563eb;
    color: #ffffff;
}
.action-btn-edit {
    background: #ecfdf5;
    color: #059669;
    border-color: #a7f3d0;
}
.action-btn-edit:hover {
    background: #059669;
    color: #ffffff;
}
.action-btn-delete {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
.action-btn-delete:hover {
    background: #dc2626;
    color: #ffffff;
}

.note-bubble {
    background: #f8fafc;
    border-left: 3px solid #3b82f6;
    padding: 8px 12px;
    border-radius: 0 8px 8px 0;
    font-size: 12px;
    color: #334155;
    line-height: 1.4;
    word-break: break-word;
}

.quick-filter-chip {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #e2e8f0;
    color: #475569;
    background: #ffffff;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.quick-filter-chip:hover, .quick-filter-chip.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}
</style>

<div class="col-12">
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 1. TOP KPI SUMMARY STATS CARDS                                          -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Total Kunjungan -->
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-total p-3 p-md-3.5" onclick="window.location.href='laporan-kegiatan.php?status=&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 10.5px; letter-spacing: 0.04em;">Total Visit</span>
                    <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 34px; height: 34px; background: #eff6ff; color: #2563eb;">
                        <i class="fa-solid fa-route" style="font-size: 15px;"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bolder mb-0 text-dark" style="font-family:'Outfit',sans-serif; font-size: 26px;"><?= number_format($kpiTotal); ?></h3>
                    <span class="text-muted small">kegiatan</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">
                    <i class="fa-regular fa-calendar-check me-1"></i>Periode: <?= htmlspecialchars($dateLabel); ?>
                </div>
            </div>
        </div>

        <!-- Selesai (Completed) -->
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-selesai p-3 p-md-3.5" onclick="window.location.href='laporan-kegiatan.php?status=selesai&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 10.5px; letter-spacing: 0.04em;">Visit Selesai</span>
                    <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 34px; height: 34px; background: #ecfdf5; color: #10b981;">
                        <i class="fa-solid fa-circle-check" style="font-size: 15px;"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bolder mb-0 text-success" style="font-family:'Outfit',sans-serif; font-size: 26px;"><?= number_format($kpiSelesai); ?></h3>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" style="font-size: 10px;"><?= $pctSelesai; ?>%</span>
                </div>
                <div class="text-success mt-1" style="font-size: 11px;">
                    <i class="fa-solid fa-check-double me-1"></i>Laporan pengerjaan lengkap
                </div>
            </div>
        </div>

        <!-- Sedang Diproses -->
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-berjalan p-3 p-md-3.5" onclick="window.location.href='laporan-kegiatan.php?status=berjalan&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 10.5px; letter-spacing: 0.04em;">Diproses / Clock In</span>
                    <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 34px; height: 34px; background: #fffbeb; color: #f59e0b;">
                        <i class="fa-solid fa-person-walking-arrow-right" style="font-size: 15px;"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bolder mb-0 text-warning" style="font-family:'Outfit',sans-serif; font-size: 26px;"><?= number_format($kpiBerjalan); ?></h3>
                    <span class="text-muted small">di lokasi</span>
                </div>
                <div class="text-warning mt-1" style="font-size: 11px;">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i>Menunggu clock-out
                </div>
            </div>
        </div>

        <!-- Dijadwalkan -->
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-jadwal p-3 p-md-3.5" onclick="window.location.href='laporan-kegiatan.php?status=dijadwalkan&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 10.5px; letter-spacing: 0.04em;">Dijadwalkan</span>
                    <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 34px; height: 34px; background: #f1f5f9; color: #64748b;">
                        <i class="fa-regular fa-calendar-clock" style="font-size: 15px;"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bolder mb-0 text-secondary" style="font-family:'Outfit',sans-serif; font-size: 26px;"><?= number_format($kpiDijadwalkan); ?></h3>
                    <span class="text-muted small">terdaftar</span>
                </div>
                <div class="text-secondary mt-1" style="font-size: 11px;">
                    <i class="fa-regular fa-hourglass-half me-1"></i>Belum dikerjakan
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 2. MODERN FILTER & TOOLBAR CARD                                         -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: #ffffff; border: 1px solid #e2e8f0 !important;">
        <div class="card-body p-3 p-md-4">
            <!-- Header Filter & Action Buttons -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-flex align-items-center justify-content-center rounded-3" style="width: 32px; height: 32px; background: #eff6ff; color: #2563eb;">
                        <i class="fa-solid fa-sliders" style="font-size: 14px;"></i>
                    </span>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-family:'Outfit',sans-serif;">Filter & Pencarian Laporan</h6>
                        <span class="text-muted small">Saring data berdasarkan sales, status kunjungan, dan rentang tanggal</span>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1.5 rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#syncSheetsModal">
                        <i class="fa-solid fa-file-excel"></i>
                        <span>Sync Google Sheets</span>
                    </button>
                    <a href="laporan-kegiatan.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1.5 rounded-3 px-3 py-2 fw-semibold">
                        <i class="fa-solid fa-rotate-right"></i>
                        <span>Reset</span>
                    </a>
                </div>
            </div>

            <!-- Form Filter Utama -->
            <form method="GET" action="laporan-kegiatan.php" class="row g-3">
                <!-- Sales Agent -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label text-uppercase fw-bold text-secondary mb-1" style="font-size: 10.5px; letter-spacing: 0.04em;">
                        <i class="fa-solid fa-user-tie text-primary me-1"></i> Sales Agent
                    </label>
                    <select name="id_sales" class="form-select form-select-sm text-dark" style="border-radius: 8px; font-size: 13px;">
                        <option value="0">-- Semua Sales Agent --</option>
                        <?php foreach ($salesOptions as $opt) : ?>
                            <option value="<?= $opt['id']; ?>" <?= ($filterSales == $opt['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($opt['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Kunjungan -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label text-uppercase fw-bold text-secondary mb-1" style="font-size: 10.5px; letter-spacing: 0.04em;">
                        <i class="fa-solid fa-filter text-primary me-1"></i> Status Kunjungan
                    </label>
                    <select name="status" class="form-select form-select-sm text-dark" style="border-radius: 8px; font-size: 13px;">
                        <option value="">-- Semua Status --</option>
                        <option value="selesai" <?= ($filterStatus === 'selesai') ? 'selected' : ''; ?>>✅ Selesai (Completed)</option>
                        <option value="berjalan" <?= ($filterStatus === 'berjalan') ? 'selected' : ''; ?>>⏳ Diproses (In Progress)</option>
                        <option value="dijadwalkan" <?= ($filterStatus === 'dijadwalkan') ? 'selected' : ''; ?>>📅 Dijadwalkan (Upcoming)</option>
                    </select>
                </div>

                <!-- Filter Bulan -->
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label text-uppercase fw-bold text-secondary mb-1" style="font-size: 10.5px; letter-spacing: 0.04em;">
                        <i class="fa-regular fa-calendar-days text-primary me-1"></i> Periode Bulan
                    </label>
                    <input type="month" name="bulan" value="<?= htmlspecialchars($filterBulan); ?>" class="form-control form-control-sm text-dark" style="border-radius: 8px; font-size: 13px;">
                </div>

                <!-- Filter Tanggal Spesifik -->
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label text-uppercase fw-bold text-secondary mb-1" style="font-size: 10.5px; letter-spacing: 0.04em;">
                        <i class="fa-regular fa-calendar-day text-primary me-1"></i> Tanggal Spesifik
                    </label>
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($filterTanggal); ?>" class="form-control form-control-sm text-dark" style="border-radius: 8px; font-size: 13px;">
                </div>

                <!-- Tombol Submit Cari -->
                <div class="col-12 col-lg-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold text-uppercase d-inline-flex align-items-center justify-content-center gap-1.5 py-2" style="background: #2563eb; border-radius: 8px; font-size: 12.5px; letter-spacing: 0.03em;">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Cari Laporan</span>
                    </button>
                </div>
            </form>

            <!-- Quick Chips & Live Instant Filter -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3 pt-3 border-top">
                <!-- Quick Filter Chips -->
                <div class="d-flex flex-wrap align-items-center gap-1.5">
                    <span class="text-muted small fw-semibold me-1">Pintasan:</span>
                    <a href="laporan-kegiatan.php?tanggal=<?= date('Y-m-d'); ?>" class="quick-filter-chip <?= ($filterTanggal === date('Y-m-d')) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-day"></i> Hari Ini
                    </a>
                    <a href="laporan-kegiatan.php?bulan=<?= date('Y-m'); ?>" class="quick-filter-chip <?= ($filterBulan === date('Y-m') && empty($filterTanggal)) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-days"></i> Bulan Ini
                    </a>
                    <a href="laporan-kegiatan.php?status=selesai&bulan=<?= $filterBulan; ?>" class="quick-filter-chip <?= ($filterStatus === 'selesai') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-check text-success"></i> Selesai Saja
                    </a>
                </div>

                <!-- Instant Live Filter Input -->
                <div class="position-relative" style="min-width: 260px; max-width: 380px; width: 100%;">
                    <i class="fa-solid fa-store position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%); font-size: 12px;"></i>
                    <input type="text" id="liveSearchCustomerInput" class="form-control form-control-sm ps-4 pe-4" placeholder="Ketik cepat nama toko / alamat..." style="border-radius: 20px; font-size: 12.5px; border-color: #cbd5e1;">
                    <span id="clearLiveSearch" class="position-absolute text-muted cursor-pointer d-none" style="right: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; cursor: pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 3. DAFTAR KUNJUNGAN PER CUSTOMER (MODERN CARDS)                         -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div id="customerListContainer">
        <?php
        $avatarColors = ['#2563eb', '#7c3aed', '#059669', '#d97706', '#db2777', '#0891b2', '#4f46e5'];
        $totalRenderedCards = 0;

        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $kegiatanId = $row['id'];
                $idC        = $row['id_cust'];
                $namaC      = $row['nama_cust'];
                $alamatC    = $row['alamat_cust'] ?? '';
                $kotaC      = $row['kota_cust'] ?? '';
                $totalRenderedCards++;

                // Ambil tim & pelaksanaan kegiatan ini
                $sqlLapTek = "SELECT tks.*, 
                                     COALESCE(s.nama, tks.nama_sales, 'Sales') AS nama_sales, 
                                     tks.id_sales,
                                     IFNULL(ps.status, ks.status) AS status,
                                     ps.ci_at AS tgl_mulai, 
                                     ps.co_at AS tgl_selesai,
                                     ks.id AS kode_transaksi, 
                                     ks.jadwal AS tgl_visits,
                                     COALESCE(NULLIF(ps.catatan_visit, ''), ps.keterangan) AS hasil_visits,
                                     ps.foto_visit_url
                              FROM team_kegiatan_sales tks
                              LEFT JOIN sales s ON tks.id_sales = s.id
                              JOIN kegiatan_sales ks ON tks.id_kegiatan_sales = ks.id
                              LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = tks.id_kegiatan_sales AND ps.sales_id = tks.id_sales
                              WHERE tks.id_kegiatan_sales = '$kegiatanId' AND tks.deleted_at IS NULL";
                              
                if ($filterSales > 0) {
                    $sqlLapTek .= " AND tks.id_sales = $filterSales";
                }
                if (!empty($filterStatus)) {
                    if ($filterStatus === 'dijadwalkan') {
                        $sqlLapTek .= " AND (ps.status IS NULL OR ps.status = 'dijadwalkan' OR ps.status = '')";
                    } else if ($filterStatus === 'berjalan') {
                        $sqlLapTek .= " AND ps.status IN ('berjalan', 'proses')";
                    } else if ($filterStatus === 'selesai') {
                        $sqlLapTek .= " AND (ps.status = 'selesai' OR ks.status = 'selesai')";
                    }
                }
                $resLapTek = mysqli_query($conn, $sqlLapTek);
                $activityCount = ($resLapTek) ? mysqli_num_rows($resLapTek) : 0;
        ?>
            <!-- Customer Card -->
            <div class="customer-report-card" data-customer-name="<?= strtolower(htmlspecialchars($namaC)); ?>" data-customer-address="<?= strtolower(htmlspecialchars($alamatC . ' ' . $kotaC)); ?>">
                <!-- Card Header -->
                <div class="customer-card-header">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 38px; height: 38px; background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe;">
                            <i class="fa-solid fa-store" style="font-size: 15px;"></i>
                        </div>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h6 class="mb-0 text-dark fw-bold" style="font-family:'Outfit',sans-serif; font-size: 15.5px; letter-spacing: -0.01em;">
                                    <?= htmlspecialchars($namaC); ?>
                                </h6>
                                <?php if (!empty($kotaC)): ?>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 font-monospace" style="font-size: 10px;">
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i><?= htmlspecialchars($kotaC); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($alamatC)): ?>
                                <div class="text-muted text-truncate" style="font-size: 12px; max-width: 550px;">
                                    <?= htmlspecialchars($alamatC); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1" style="font-size: 11px; font-weight: 600;">
                            <?= max(1, $activityCount); ?> Penugasan
                        </span>
                    </div>
                </div>

                <!-- Desktop Table Header -->
                <div class="report-table-header d-none d-lg-flex row gx-3 align-items-center m-0">
                    <div class="col-lg-2">Status & ID</div>
                    <div class="col-lg-2">Sales Agent</div>
                    <div class="col-lg-2">Jadwal Visit</div>
                    <div class="col-lg-2">Waktu Pelaksanaan</div>
                    <div class="col-lg-3">Hasil / Catatan Visit</div>
                    <div class="col-lg-1 text-end">Aksi</div>
                </div>

                <!-- Activities List -->
                <div class="p-0">
                    <?php
                    if ($resLapTek && mysqli_num_rows($resLapTek) > 0) {
                        while ($rowLT = mysqli_fetch_assoc($resLapTek)) {
                            $idT = $rowLT["id_sales"];
                            $namaSalesItem = $rowLT["nama_sales"];
                            $initials = strtoupper(substr($namaSalesItem, 0, 2));
                            $colorIdx = abs(crc32($namaSalesItem)) % count($avatarColors);
                            $avatarBg = $avatarColors[$colorIdx];

                            $hslVisits = trim($rowLT['hasil_visits'] ?? '');
                            $datetime = $rowLT["tgl_visits"];
                            $formattedDate = ($datetime && $datetime != '0000-00-00 00:00:00') ? date("d M Y", strtotime($datetime)) : '-';
                            $formattedTime = ($datetime && $datetime != '0000-00-00 00:00:00') ? date("H:i", strtotime($datetime)) : '-';

                            $tglMulai = $rowLT["tgl_mulai"];
                            $formattedTimeMli = ($tglMulai && $tglMulai != '0000-00-00 00:00:00') ? date("H:i", strtotime($tglMulai)) : null;

                            $tglSelesai = $rowLT["tgl_selesai"];
                            $formattedTimeSls = ($tglSelesai && $tglSelesai != '0000-00-00 00:00:00') ? date("H:i", strtotime($tglSelesai)) : null;

                            $rawStatus = strtolower($rowLT['status'] ?? 'dijadwalkan');
                            if ($rawStatus === 'proses' || $rawStatus === 'berjalan') {
                                $status = 'berjalan';
                            } elseif ($rawStatus === 'selesai') {
                                $status = 'selesai';
                            } else {
                                $status = 'dijadwalkan';
                            }
                    ?>
                        <div class="report-item-row row gx-3 align-items-center m-0">
                            <!-- 1. Status & ID -->
                            <div class="col-12 col-lg-2 mb-2 mb-lg-0">
                                <div class="d-flex flex-wrap align-items-center gap-1.5">
                                    <?php if ($status === 'selesai'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 11px;">
                                            <i class="fa-solid fa-circle-check"></i> Selesai
                                        </span>
                                    <?php elseif ($status === 'berjalan'): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 11px;">
                                            <span class="spinner-grow spinner-grow-sm" style="width: 7px; height: 7px;"></span> Diproses
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 11px;">
                                            <i class="fa-regular fa-clock"></i> Dijadwalkan
                                        </span>
                                    <?php endif; ?>

                                    <a href="javascript:void(0)" class="badge bg-light text-primary border font-monospace text-decoration-none px-2 py-1 detailBtn" data-id="<?= $idT; ?>" data-kode="<?= $rowLT['kode_transaksi']; ?>" title="Lihat Riwayat Waktu" style="font-size: 11px;">
                                        #<?= $rowLT['kode_transaksi']; ?>
                                    </a>
                                </div>
                            </div>

                            <!-- 2. Sales Agent -->
                            <div class="col-12 col-lg-2 mb-2 mb-lg-0">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-initial" style="background: <?= $avatarBg; ?>;">
                                        <?= $initials; ?>
                                    </div>
                                    <div>
                                        <div class="text-dark fw-bold" style="font-size: 13px; line-height: 1.2;">
                                            <?= htmlspecialchars($namaSalesItem); ?>
                                        </div>
                                        <span class="text-muted" style="font-size: 11px;">Sales Team</span>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Jadwal Visit -->
                            <div class="col-6 col-lg-2 mb-2 mb-lg-0">
                                <div class="d-flex flex-column">
                                    <span class="text-dark fw-semibold" style="font-size: 12.5px;">
                                        <i class="fa-regular fa-calendar text-primary me-1"></i><?= $formattedDate; ?>
                                    </span>
                                    <span class="text-secondary fw-bold ms-3.5" style="font-size: 11px;">
                                        <i class="fa-regular fa-clock text-muted me-1"></i><?= $formattedTime; ?> WIB
                                    </span>
                                </div>
                            </div>

                            <!-- 4. Waktu Pelaksanaan (Clock In & Out) -->
                            <div class="col-6 col-lg-2 mb-2 mb-lg-0">
                                <div class="d-flex flex-column gap-1">
                                    <?php if ($formattedTimeMli): ?>
                                        <div class="d-inline-flex align-items-center gap-1 text-success fw-bold font-monospace" style="font-size: 11.5px;">
                                            <i class="fa-solid fa-arrow-right-to-bracket text-success"></i> IN: <?= $formattedTimeMli; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 12px;"><i class="fa-solid fa-minus text-muted me-1"></i> Belum Masuk</span>
                                    <?php endif; ?>

                                    <?php if ($formattedTimeSls): ?>
                                        <div class="d-inline-flex align-items-center gap-1 text-primary fw-bold font-monospace" style="font-size: 11.5px;">
                                            <i class="fa-solid fa-arrow-right-from-bracket text-primary"></i> OUT: <?= $formattedTimeSls; ?>
                                        </div>
                                    <?php elseif ($formattedTimeMli): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace px-1.5 py-0.5 text-start" style="font-size: 9.5px; width: fit-content;">Sedang Visit</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 5. Hasil & Catatan Visit -->
                            <div class="col-12 col-lg-3 mb-2 mb-lg-0">
                                <?php if (!empty($hslVisits)): ?>
                                    <div class="note-bubble">
                                        <i class="fa-solid fa-quote-left text-muted me-1" style="font-size: 10px;"></i>
                                        <?= htmlspecialchars(mb_strimwidth($hslVisits, 0, 100, '...')); ?>
                                    </div>
                                <?php elseif ($status === 'selesai'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 11px;">
                                        <i class="fa-solid fa-check me-1"></i> Kunjungan Selesai
                                    </span>
                                <?php elseif ($status === 'berjalan'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" style="font-size: 11px;">
                                        <i class="fa-solid fa-location-dot me-1"></i> Sedang di Lokasi Toko
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 11px;">
                                        <i class="fa-regular fa-clock me-1"></i> Menunggu Sales
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- 6. Aksi Buttons -->
                            <div class="col-12 col-lg-1 text-lg-end mt-2 mt-lg-0">
                                <div class="d-flex align-items-center justify-content-start justify-content-lg-end gap-1.5">
                                    <!-- View Detail Modal -->
                                    <button type="button" class="action-btn-modern action-btn-view detailBtn" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="<?= $idT; ?>" data-kode="<?= $rowLT['kode_transaksi']; ?>" title="Lihat Rincian & Lokasi (GPS)">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <!-- Edit Visit Modal -->
                                    <button type="button" class="action-btn-modern action-btn-edit editVisitBtn" data-id="<?= $rowLT['kode_transaksi']; ?>" data-sales="<?= $idT; ?>" title="Edit Laporan Kunjungan">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Delete / Reset Visit -->
                                    <button type="button" class="action-btn-modern action-btn-delete deleteVisitBtn" data-id="<?= $rowLT['kode_transaksi']; ?>" data-sales="<?= $idT; ?>" data-status="<?= $status; ?>" data-cust="<?= htmlspecialchars($namaC); ?>" title="Hapus / Reset Kunjungan">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php
                        }
                    } else {
                        // Fallback row jika team belum terdaftar
                        $datetimeFallback = $row["tgl_visits"];
                        $formattedDateFb = ($datetimeFallback && $datetimeFallback != '0000-00-00 00:00:00') ? date("d M Y", strtotime($datetimeFallback)) : '-';
                        $formattedTimeFb = ($datetimeFallback && $datetimeFallback != '0000-00-00 00:00:00') ? date("H:i", strtotime($datetimeFallback)) : '-';
                    ?>
                        <div class="report-item-row row gx-3 align-items-center m-0">
                            <div class="col-12 col-lg-2 mb-2 mb-lg-0">
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size: 11px;">
                                    <i class="fa-regular fa-clock me-1"></i> Dijadwalkan
                                </span>
                                <span class="badge bg-light text-primary border font-monospace px-2 py-1 ms-1" style="font-size: 11px;">
                                    #<?= $row['kode_transaksi']; ?>
                                </span>
                            </div>
                            <div class="col-12 col-lg-2 mb-2 mb-lg-0">
                                <span class="text-muted small">Sales belum ditugaskan</span>
                            </div>
                            <div class="col-6 col-lg-2 mb-2 mb-lg-0">
                                <span class="text-dark fw-semibold" style="font-size: 12.5px;"><?= $formattedDateFb; ?></span>
                                <span class="text-muted ms-1 small"><?= $formattedTimeFb; ?> WIB</span>
                            </div>
                            <div class="col-6 col-lg-2 mb-2 mb-lg-0">
                                <span class="text-muted small">—</span>
                            </div>
                            <div class="col-12 col-lg-3 mb-2 mb-lg-0">
                                <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 11px;">Menunggu Penugasan</span>
                            </div>
                            <div class="col-12 col-lg-1 text-lg-end mt-2 mt-lg-0">
                                <button type="button" class="action-btn-modern action-btn-view detailBtn" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="0" data-kode="<?= $row['kode_transaksi']; ?>" title="Lihat Rincian">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php
            }
        } else {
        ?>
            <!-- Empty State -->
            <div class="card border-0 shadow-sm rounded-4 text-center py-5" style="background: #ffffff; border: 1px solid #e2e8f0 !important;">
                <div class="card-body">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 64px; height: 64px; background: #f1f5f9; color: #94a3b8;">
                        <i class="fa-solid fa-clipboard-question" style="font-size: 28px;"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1" style="font-family:'Outfit',sans-serif;">Data Laporan Kunjungan Tidak Ditemukan</h5>
                    <p class="text-muted small mb-3">Tidak ada jadwal atau riwayat kunjungan yang sesuai dengan filter yang dipilih.</p>
                    <a href="laporan-kegiatan.php" class="btn btn-sm btn-primary rounded-3 px-3 py-2 fw-semibold" style="background: #2563eb;">
                        <i class="fa-solid fa-rotate-right me-1"></i> Reset Semua Filter
                    </a>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: SYNC GOOGLE SHEETS                                                  -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="syncSheetsModal" tabindex="-1" aria-labelledby="syncSheetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-flex align-items-center justify-content-center rounded-circle" style="width: 34px; height: 34px; background: #ecfdf5; color: #059669;">
                        <i class="fa-solid fa-file-excel"></i>
                    </span>
                    <h5 class="modal-title font-weight-bold text-dark fs-6 mb-0" id="syncSheetsModalLabel">
                        Sync ke Google Sheets
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="syncSheetsForm">
                <div class="modal-body py-3">
                    <div class="alert alert-info text-white text-xs border-0 mb-3" style="background: linear-gradient(135deg, #1d4ed8, #3b82f6); border-radius: 12px; line-height: 1.5;">
                        <i class="fa-solid fa-circle-info me-1.5 text-sm"></i>
                        Pastikan Anda telah membagikan Spreadsheet target sebagai <strong>Editor</strong> ke email service account berikut:<br>
                        <code class="text-white bg-dark px-2 py-1 mt-1.5 d-inline-block rounded select-all" style="font-family: monospace; font-size: 11px;">sheets-sync@loewix-sales.iam.gserviceaccount.com</code>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">ID Spreadsheet atau URL</label>
                        <input type="text" name="spreadsheet_id" id="sheetIdInput" class="form-control border p-2 text-sm" 
                               placeholder="Masukkan ID / Link Google Sheets" 
                               value="19OV073XNHmo7zACGOpYPyEcmodIZmEv4wzFq7Fg_uoU" style="border-radius: 8px;" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Nama Sheet (Tab)</label>
                        <input type="text" name="sheet_name" id="sheetNameInput" class="form-control border p-2 text-sm" 
                               placeholder="Contoh: Sheet1" value="Sheet1" style="border-radius: 8px;" required>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Bulan</label>
                            <input type="text" id="displayBulanInput" class="form-control border p-2 text-xs bg-light" value="<?= date('F Y', strtotime($filterBulan . '-01')); ?>" style="border-radius: 8px;" readonly disabled>
                            <input type="hidden" id="hiddenBulanInput" name="bulan" value="<?= $filterBulan; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Tanggal</label>
                            <input type="text" id="displayTanggalInput" class="form-control border p-2 text-xs bg-light" value="<?= !empty($filterTanggal) ? date('d M Y', strtotime($filterTanggal)) : '-'; ?>" style="border-radius: 8px;" readonly disabled>
                            <input type="hidden" id="hiddenTanggalInput" name="tanggal" value="<?= $filterTanggal; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Status Kegiatan</label>
                            <?php
                            $selectedStatusName = "Semua Status";
                            if ($filterStatus === 'selesai') $selectedStatusName = "Selesai";
                            elseif ($filterStatus === 'berjalan') $selectedStatusName = "Diproses";
                            elseif ($filterStatus === 'dijadwalkan') $selectedStatusName = "Dijadwalkan";
                            ?>
                            <input type="text" id="displayStatusInput" class="form-control border p-2 text-xs bg-light" value="<?= $selectedStatusName; ?>" style="border-radius: 8px;" readonly disabled>
                            <input type="hidden" id="hiddenStatusInput" name="status" value="<?= $filterStatus; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Sales Agent</label>
                            <?php
                            $selectedSalesName = "Semua Sales";
                            if ($filterSales > 0) {
                                foreach ($salesOptions as $opt) {
                                    if ($opt['id'] == $filterSales) {
                                        $selectedSalesName = $opt['nama'];
                                        break;
                                    }
                                }
                            }
                            ?>
                            <input type="text" id="displaySalesInput" class="form-control border p-2 text-xs bg-light" value="<?= htmlspecialchars($selectedSalesName); ?>" style="border-radius: 8px;" readonly disabled>
                            <input type="hidden" id="hiddenSalesInput" name="id_sales" value="<?= $filterSales; ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-secondary mb-0" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success mb-0 fw-bold" id="btnDoSync" style="border-radius: 8px; background: #059669;">
                        <span id="syncSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                        Mulai Sync
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: EDIT LAPORAN KUNJUNGAN                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editVisitModal" tabindex="-1" aria-labelledby="editVisitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-flex align-items-center justify-content-center rounded-circle" style="width: 34px; height: 34px; background: #eff6ff; color: #2563eb;">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </span>
                    <h5 class="modal-title font-weight-bold text-dark fs-6 mb-0" id="editVisitModalLabel">
                        Edit Laporan Kunjungan
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editVisitForm">
                <input type="hidden" name="kegiatan_id" id="edit_kegiatan_id">
                <input type="hidden" name="sales_id" id="edit_sales_id">
                <input type="hidden" name="status_kegiatan" id="edit_status_kegiatan">
                
                <div class="modal-body py-3">
                    <div class="row">
                        <!-- Customer & Sales (Readonly) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Customer</label>
                            <input type="text" id="edit_customer_name" class="form-control border p-2 text-sm bg-light" readonly disabled style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Sales Agent</label>
                            <input type="text" id="edit_sales_name" class="form-control border p-2 text-sm bg-light" readonly disabled style="border-radius: 8px;">
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Jadwal Kunjungan (Editable) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Jadwal Kunjungan</label>
                            <input type="datetime-local" name="jadwal" id="edit_jadwal" class="form-control border p-2 text-sm" style="border-radius: 8px;" required>
                        </div>
                        <!-- Tipe Prospek -->
                        <div class="col-md-6 mb-3 execution-field">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Tipe Prospek</label>
                            <select name="tipe_prospek" id="edit_tipe_prospek" class="form-select border p-2 text-sm" style="border-radius: 8px;">
                                <option value="Biasa">Biasa</option>
                                <option value="Peluang">Peluang</option>
                                <option value="Rumit">Rumit</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row execution-field">
                        <!-- Clock In (Editable) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Waktu Mulai (Clock In)</label>
                            <input type="datetime-local" name="ci_at" id="edit_ci_at" class="form-control border p-2 text-sm" style="border-radius: 8px;">
                        </div>
                        <!-- Clock Out (Editable) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Waktu Selesai (Clock Out)</label>
                            <input type="datetime-local" name="co_at" id="edit_co_at" class="form-control border p-2 text-sm" style="border-radius: 8px;">
                        </div>
                    </div>

                    <div class="row execution-field">
                        <!-- No Invoice (Editable) -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Nomor Invoice</label>
                            <input type="text" name="no_invoice" id="edit_no_invoice" class="form-control border p-2 text-sm" style="border-radius: 8px;" placeholder="Contoh: INV.12345">
                        </div>
                    </div>
                    
                    <!-- Hasil Kunjungan (Editable) -->
                    <div class="mb-3 execution-field">
                        <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Hasil Kunjungan / Keterangan</label>
                        <textarea name="keterangan" id="edit_keterangan" rows="2" class="form-control border p-2 text-sm" style="border-radius: 8px;" placeholder="Hasil kunjungan..."></textarea>
                    </div>
                    
                    <!-- Catatan Tambahan (Editable) -->
                    <div class="mb-3 execution-field">
                        <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Catatan Tambahan</label>
                        <textarea name="catatan_visit" id="edit_catatan_visit" rows="2" class="form-control border p-2 text-sm" style="border-radius: 8px;" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-secondary mb-0" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary mb-0 fw-bold" style="border-radius: 8px; background: #2563eb;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // ── Live Instant Search Customer ──
    const searchInput = document.getElementById('liveSearchCustomerInput');
    const clearBtn = document.getElementById('clearLiveSearch');
    const cards = document.querySelectorAll('.customer-report-card');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            if (clearBtn) {
                clearBtn.classList.toggle('d-none', query.length === 0);
            }

            cards.forEach(card => {
                const name = card.getAttribute('data-customer-name') || '';
                const addr = card.getAttribute('data-customer-address') || '';
                if (query === '' || name.includes(query) || addr.includes(query)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }
    }

    // ── Draggable Modals ──
    function makeModalDraggable(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const dialog = modal.querySelector('.modal-dialog');
        const header = modal.querySelector('.modal-header');
        if (!dialog || !header) return;
        
        header.style.cursor = 'move';
        let isDragging = false;
        let startX, startY;
        let modalLeft = 0, modalTop = 0;
        
        modal.addEventListener('show.bs.modal', () => {
            dialog.style.left = '0px';
            dialog.style.top = '0px';
            modalLeft = 0;
            modalTop = 0;
        });
        
        header.addEventListener('mousedown', (e) => {
            if (e.target.closest('.btn-close') || e.target.closest('button')) return;
            isDragging = true;
            startX = e.clientX - modalLeft;
            startY = e.clientY - modalTop;
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            document.body.style.userSelect = 'none';
            dialog.style.transition = 'none';
        });
        
        function onMouseMove(e) {
            if (!isDragging) return;
            modalLeft = e.clientX - startX;
            modalTop = e.clientY - startY;
            dialog.style.left = modalLeft + 'px';
            dialog.style.top = modalTop + 'px';
        }
        
        function onMouseUp() {
            isDragging = false;
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            document.body.style.userSelect = '';
            dialog.style.transition = '';
        }
    }

    makeModalDraggable('syncSheetsModal');
    makeModalDraggable('editVisitModal');
    makeModalDraggable('detailModal');

    // ── Sync Google Sheets Modal Setup ──
    $("#syncSheetsModal").on('show.bs.modal', function() {
        const topSalesSelect = $('select[name="id_sales"]');
        const topStatusSelect = $('select[name="status"]');
        const topBulanInput = $('input[name="bulan"]');
        const topTanggalInput = $('input[name="tanggal"]');
        
        const selectedSalesId = topSalesSelect.val() || '0';
        const selectedSalesName = topSalesSelect.find('option:selected').text().trim() || 'Semua Sales';
        const selectedStatus = topStatusSelect.val() || '';
        const selectedStatusName = topStatusSelect.find('option:selected').text().trim() || 'Semua Status';
        const selectedBulan = topBulanInput.val() || '';
        const selectedTanggal = topTanggalInput.val() || '';
        
        $("#displaySalesInput").val(selectedSalesName);
        $("#hiddenSalesInput").val(selectedSalesId);
        $("#displayStatusInput").val(selectedStatusName);
        $("#hiddenStatusInput").val(selectedStatus);
        $("#hiddenBulanInput").val(selectedBulan);
        $("#hiddenTanggalInput").val(selectedTanggal);
    });

    $("#syncSheetsForm").on('submit', function(e) {
        e.preventDefault();
        const btn = $("#btnDoSync");
        const spinner = $("#syncSpinner");
        btn.prop('disabled', true);
        spinner.removeClass('d-none');
        
        $.ajax({
            url: "proses-sync-sheets.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
                if (response.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Berhasil', response.message, 'success');
                    } else {
                        alert(response.message);
                    }
                    $("#syncSheetsModal").modal('hide');
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Gagal', response.message, 'error');
                    } else {
                        alert("Gagal: " + response.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
                let errMsg = error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errMsg = xhr.responseJSON.message;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Terjadi kesalahan: ' + errMsg, 'error');
                } else {
                    alert("Terjadi kesalahan: " + errMsg);
                }
            }
        });
    });

    // ── Edit Visit Action ──
    $(".editVisitBtn").click(function() {
        const kegiatanId = $(this).data("id");
        const salesId = $(this).data("sales");
        
        $.ajax({
            url: "get_visit_details.php",
            type: "GET",
            data: { kegiatan_id: kegiatanId, sales_id: salesId },
            dataType: "json",
            success: function(res) {
                if (res.status === 'success') {
                    const d = res.data;
                    $("#edit_kegiatan_id").val(d.kegiatan_id);
                    $("#edit_sales_id").val(d.sales_id);
                    $("#edit_status_kegiatan").val(d.status_kegiatan);
                    $("#edit_customer_name").val(d.nama_cust);
                    $("#edit_sales_name").val(d.nama_sales);
                    $("#edit_jadwal").val(d.jadwal);
                    
                    if (d.status_kegiatan === 'selesai' || d.status_kegiatan === 'berjalan' || d.status_kegiatan === 'proses') {
                        $(".execution-field").show();
                        $("#edit_ci_at").val(d.ci_at);
                        $("#edit_co_at").val(d.co_at);
                        $("#edit_tipe_prospek").val(d.tipe_prospek);
                        $("#edit_no_invoice").val(d.no_invoice);
                        $("#edit_keterangan").val(d.keterangan);
                        $("#edit_catatan_visit").val(d.catatan_visit);
                    } else {
                        $(".execution-field").hide();
                    }
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editVisitModal'));
                    editModal.show();
                } else {
                    alert("Gagal mengambil data rincian: " + res.message);
                }
            },
            error: function() {
                alert("Terjadi kesalahan saat memuat rincian kunjungan.");
            }
        });
    });

    $("#editVisitForm").submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: "proses-edit-kunjungan.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(res) {
                if (res.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Berhasil', res.message, 'success').then(() => location.reload());
                    } else {
                        alert(res.message);
                        location.reload();
                    }
                } else {
                    alert("Gagal: " + res.message);
                }
            },
            error: function() {
                alert("Terjadi kesalahan saat menyimpan perubahan.");
            }
        });
    });

    // ── Delete / Reset Visit Action ──
    $(".deleteVisitBtn").click(function() {
        const kegiatanId = $(this).data("id");
        const salesId = $(this).data("sales");
        const status = $(this).data("status");
        const custName = $(this).data("cust");
        
        let confirmText = (status === 'selesai' || status === 'berjalan')
            ? `Hapus laporan kunjungan "${custName}"? Tindakan ini akan mengembalikan status menjadi "Dijadwalkan".`
            : `Hapus jadwal kunjungan ke "${custName}" secara permanen?`;
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: confirmText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeDeleteVisit(kegiatanId, salesId, status);
                }
            });
        } else {
            if (confirm(confirmText)) {
                executeDeleteVisit(kegiatanId, salesId, status);
            }
        }
    });

    function executeDeleteVisit(kegiatanId, salesId, status) {
        $.ajax({
            url: "proses-hapus-kunjungan.php",
            type: "POST",
            data: { kegiatan_id: kegiatanId, sales_id: salesId, status_kegiatan: status },
            dataType: "json",
            success: function(res) {
                if (res.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Berhasil', res.message, 'success').then(() => location.reload());
                    } else {
                        alert(res.message);
                        location.reload();
                    }
                } else {
                    alert("Gagal: " + res.message);
                }
            },
            error: function() {
                alert("Terjadi kesalahan saat memproses penghapusan.");
            }
        });
    }
});
</script>