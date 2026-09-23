<?php
/**
 * laporan-db-cust.php - Laporan Kunjungan & Visit Sales (Taste-Skill Modern Design)
 */

// Filter variables
$filterSales   = isset($_GET['id_sales']) ? intval($_GET['id_sales']) : 0;
$filterBulan   = isset($_GET['bulan']) ? trim($_GET['bulan']) : date("Y-m");
$filterTanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$filterStatus  = isset($_GET['status']) ? trim(strtolower($_GET['status'])) : '';

if (!empty($filterTanggal)) {
    $current_date = $filterTanggal;
    $dateLabel = date('d F Y', strtotime($filterTanggal));
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
               sc.nama AS nama_cust, sc.id AS id_cust, sc.alamat AS alamat_cust, sc.kota AS kota_cust, sc.kode_customer
        FROM kegiatan_sales ks
        INNER JOIN sales_customer sc ON ks.id_customer = sc.id
        WHERE $whereSql
        ORDER BY ks.jadwal DESC";

$result = mysqli_query($conn, $sql);
?>

<style>
/* ─── Taste-Skill Design Architecture for Laporan Visit ─── */
:root {
  --lp-primary: #2563eb;
  --lp-primary-hover: #1d4ed8;
  --lp-primary-light: #eff6ff;
  --lp-primary-border: #bfdbfe;
  --lp-slate-900: #0f172a;
  --lp-slate-800: #1e293b;
  --lp-slate-700: #334155;
  --lp-slate-600: #475569;
  --lp-slate-500: #64748b;
  --lp-slate-400: #94a3b8;
  --lp-slate-200: #e2e8f0;
  --lp-slate-100: #f1f5f9;
  --lp-slate-50: #f8fafc;
  --lp-card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 8px 24px -4px rgba(15, 23, 42, 0.04);
  --lp-card-hover: 0 6px 20px -2px rgba(15, 23, 42, 0.08);
}

/* ─── KPI Metric Cards ─── */
.lp-kpi-card {
  background: #ffffff;
  border: 1px solid var(--lp-slate-200);
  border-radius: 14px;
  padding: 18px 20px;
  box-shadow: var(--lp-card-shadow);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.lp-kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--lp-card-hover);
  border-color: var(--lp-slate-400);
}

.lp-kpi-card.active-filter {
  border-color: var(--lp-primary);
  background: #ffffff;
  box-shadow: 0 0 0 2px var(--lp-primary), var(--lp-card-shadow);
}

.lp-kpi-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.lp-kpi-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--lp-slate-500);
  margin-bottom: 6px;
}

.lp-kpi-value {
  font-family: 'Outfit', sans-serif;
  font-size: 30px;
  font-weight: 800;
  line-height: 1.1;
  color: var(--lp-slate-900);
  margin: 0;
  letter-spacing: -0.02em;
}

.lp-kpi-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.lp-kpi-icon span.material-symbols-outlined {
  font-size: 24px;
}

.lp-kpi-footer {
  margin-top: 14px;
  padding-top: 10px;
  border-top: 1px solid var(--lp-slate-100);
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11.5px;
  color: var(--lp-slate-400);
  font-weight: 600;
}

/* ─── Filter & Search Toolbar ─── */
.lp-toolbar-card {
  background: #ffffff;
  border: 1px solid var(--lp-slate-200);
  border-radius: 14px;
  padding: 16px 20px;
  box-shadow: var(--lp-card-shadow);
  margin-bottom: 24px;
}

.lp-input {
  height: 40px;
  border: 1.5px solid var(--lp-slate-200);
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 13px;
  color: var(--lp-slate-800);
  transition: all 0.15s ease;
  outline: none;
}

.lp-input:focus {
  border-color: var(--lp-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.lp-btn {
  height: 40px;
  padding: 0 16px;
  font-size: 12.5px;
  font-weight: 600;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.15s ease;
  border: none;
  cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
}

.lp-btn-primary {
  background: var(--lp-primary);
  color: #ffffff !important;
}
.lp-btn-primary:hover {
  background: var(--lp-primary-hover);
  transform: translateY(-1px);
}

.lp-btn-secondary {
  background: var(--lp-slate-100);
  color: var(--lp-slate-700);
  border: 1px solid var(--lp-slate-200);
}
.lp-btn-secondary:hover {
  background: var(--lp-slate-200);
  color: var(--lp-slate-900);
}

.lp-btn-success {
  background: #059669;
  color: #ffffff !important;
}
.lp-btn-success:hover {
  background: #047857;
  transform: translateY(-1px);
}

/* ─── Customer Activity Card (Clean SaaS Feed) ─── */
.lp-cust-card {
  background: #ffffff;
  border: 1px solid var(--lp-slate-200);
  border-radius: 14px;
  box-shadow: var(--lp-card-shadow);
  margin-bottom: 16px;
  overflow: hidden;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.lp-cust-card:hover {
  box-shadow: var(--lp-card-hover);
  border-color: #cbd5e1;
}

.lp-cust-header {
  padding: 14px 20px;
  background: #ffffff;
  border-bottom: 1px solid var(--lp-slate-100);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.lp-cust-info {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.lp-cust-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: var(--lp-primary-light);
  color: var(--lp-primary);
  border: 1px solid var(--lp-primary-border);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 16px;
}

.lp-cust-name {
  font-family: 'Outfit', sans-serif;
  font-size: 15px;
  font-weight: 700;
  color: var(--lp-slate-900);
  margin: 0;
  line-height: 1.2;
}

.lp-cust-addr {
  font-size: 12px;
  color: var(--lp-slate-500);
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 580px;
}

/* Table Header & Activity Item Row */
.lp-table-head {
  padding: 8px 20px;
  background: #f8fafc;
  border-bottom: 1px solid var(--lp-slate-200);
  display: grid;
  grid-template-columns: 140px 180px 170px 160px 1fr 100px;
  align-items: center;
  gap: 16px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--lp-slate-500);
}

.lp-item-row {
  padding: 12px 20px;
  border-bottom: 1px solid var(--lp-slate-100);
  display: grid;
  grid-template-columns: 140px 180px 170px 160px 1fr 100px;
  align-items: center;
  gap: 16px;
  transition: background 0.15s ease;
}

.lp-item-row:last-child {
  border-bottom: none;
}

.lp-item-row:hover {
  background: #fcfdfe;
}

.lp-sales-pill {
  display: flex;
  align-items: center;
  gap: 8px;
}

.lp-sales-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 11px;
  color: #ffffff;
  flex-shrink: 0;
}

.lp-sales-text {
  min-width: 0;
}

.lp-sales-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--lp-slate-800);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.2;
}

.lp-note-box {
  background: var(--lp-slate-50);
  border: 1px solid var(--lp-slate-200);
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 12px;
  color: var(--lp-slate-600);
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.lp-action-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid transparent;
  transition: all 0.15s ease;
  font-size: 12px;
  cursor: pointer;
  text-decoration: none;
}

.lp-action-btn:hover {
  transform: translateY(-1px);
}

.lp-action-view {
  background: #eff6ff;
  color: #2563eb;
  border-color: #dbeafe;
}
.lp-action-view:hover {
  background: #2563eb;
  color: #ffffff;
}

.lp-action-edit {
  background: #ecfdf5;
  color: #059669;
  border-color: #a7f3d0;
}
.lp-action-edit:hover {
  background: #059669;
  color: #ffffff;
}

.lp-action-delete {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
}
.lp-action-delete:hover {
  background: #dc2626;
  color: #ffffff;
}

/* Status Badges */
.lp-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.lp-status-selesai {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.lp-status-berjalan {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
}

.lp-status-dijadwalkan {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

@media (max-width: 991.98px) {
  .lp-item-row {
    grid-template-columns: 1fr;
    gap: 10px;
    padding: 14px 16px;
  }
}
</style>

<div class="col-12">
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 1. TOP STAT KPI METRICS (TASTE-SKILL STANDARD)                         -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Visit -->
        <div class="col-6 col-md-3">
            <div class="lp-kpi-card <?= (empty($filterStatus)) ? 'active-filter' : ''; ?>" onclick="window.location.href='laporan-kegiatan.php?status=&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'">
                <div class="lp-kpi-top">
                    <div>
                        <div class="lp-kpi-label">TOTAL VISIT</div>
                        <h3 class="lp-kpi-value"><?= number_format($kpiTotal); ?></h3>
                    </div>
                    <div class="lp-kpi-icon" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;">
                        <span class="material-symbols-outlined">event_available</span>
                    </div>
                </div>
                <div class="lp-kpi-footer">
                    <span><i class="bi bi-calendar-check me-1"></i> Periode</span>
                    <span class="text-dark fw-bold"><?= htmlspecialchars($dateLabel); ?></span>
                </div>
            </div>
        </div>

        <!-- Card 2: Dijadwalkan -->
        <div class="col-6 col-md-3">
            <div class="lp-kpi-card <?= ($filterStatus === 'dijadwalkan') ? 'active-filter' : ''; ?>" onclick="window.location.href='laporan-kegiatan.php?status=dijadwalkan&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'">
                <div class="lp-kpi-top">
                    <div>
                        <div class="lp-kpi-label">DIJADWALKAN</div>
                        <h3 class="lp-kpi-value" style="color: #2563eb;"><?= number_format($kpiDijadwalkan); ?></h3>
                    </div>
                    <div class="lp-kpi-icon" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">
                        <span class="material-symbols-outlined">event</span>
                    </div>
                </div>
                <div class="lp-kpi-footer">
                    <span><i class="bi bi-clock-history me-1"></i> Status</span>
                    <span class="text-primary fw-bold">Jadwal Mendatang</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Diproses -->
        <div class="col-6 col-md-3">
            <div class="lp-kpi-card <?= ($filterStatus === 'berjalan') ? 'active-filter' : ''; ?>" onclick="window.location.href='laporan-kegiatan.php?status=berjalan&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'">
                <div class="lp-kpi-top">
                    <div>
                        <div class="lp-kpi-label">DIPROSES (GPS)</div>
                        <h3 class="lp-kpi-value" style="color: #d97706;"><?= number_format($kpiBerjalan); ?></h3>
                    </div>
                    <div class="lp-kpi-icon" style="background:#fffbeb; color:#d97706; border:1px solid #fde68a;">
                        <span class="material-symbols-outlined">location_searching</span>
                    </div>
                </div>
                <div class="lp-kpi-footer">
                    <span><i class="bi bi-geo-alt-fill me-1"></i> Status</span>
                    <span class="text-warning fw-bold">Sedang di Lokasi</span>
                </div>
            </div>
        </div>

        <!-- Card 4: Selesai -->
        <div class="col-6 col-md-3">
            <div class="lp-kpi-card <?= ($filterStatus === 'selesai') ? 'active-filter' : ''; ?>" onclick="window.location.href='laporan-kegiatan.php?status=selesai&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'">
                <div class="lp-kpi-top">
                    <div>
                        <div class="lp-kpi-label">SELESAI</div>
                        <h3 class="lp-kpi-value" style="color: #059669;"><?= number_format($kpiSelesai); ?></h3>
                    </div>
                    <div class="lp-kpi-icon" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0;">
                        <span class="material-symbols-outlined">task_alt</span>
                    </div>
                </div>
                <div class="lp-kpi-footer">
                    <span><i class="bi bi-check2-all me-1"></i> Status</span>
                    <span class="text-success fw-bold">Kunjungan Rampung</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 2. CONTROL TOOLBAR (SEARCH & FILTERS)                                   -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="lp-toolbar-card">
        <form method="GET" action="laporan-kegiatan.php">
            <div class="row g-2 align-items-center">
                
                <!-- Live Search Box -->
                <div class="col-12 col-lg-3">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px;"></i>
                        <input type="text" id="liveSearchCustomerInput" class="lp-input w-100 ps-4 pe-4" placeholder="Cari nama toko atau alamat..." autocomplete="off">
                        <span id="clearLiveSearch" class="position-absolute text-muted d-none" style="right: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; cursor: pointer;">
                            <i class="bi bi-x-circle-fill"></i>
                        </span>
                    </div>
                </div>

                <!-- Filter Sales Dropdown -->
                <div class="col-6 col-lg-2">
                    <select name="id_sales" class="lp-input w-100" style="font-size: 12.5px;">
                        <option value="0">Semua Sales Agent</option>
                        <?php foreach ($salesOptions as $opt) : ?>
                            <option value="<?= $opt['id']; ?>" <?= ($filterSales == $opt['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($opt['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Status Dropdown -->
                <div class="col-6 col-lg-2">
                    <select name="status" class="lp-input w-100" style="font-size: 12.5px;">
                        <option value="">Semua Status</option>
                        <option value="dijadwalkan" <?= ($filterStatus === 'dijadwalkan') ? 'selected' : ''; ?>>Dijadwalkan</option>
                        <option value="berjalan" <?= ($filterStatus === 'berjalan') ? 'selected' : ''; ?>>Diproses (GPS)</option>
                        <option value="selesai" <?= ($filterStatus === 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                    </select>
                </div>

                <!-- Filter Bulan -->
                <div class="col-6 col-lg-2">
                    <input type="month" name="bulan" value="<?= htmlspecialchars($filterBulan); ?>" class="lp-input w-100" title="Pilih Bulan">
                </div>

                <!-- Filter Tanggal -->
                <div class="col-6 col-lg-1">
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($filterTanggal); ?>" class="lp-input w-100" title="Pilih Tanggal Spesifik">
                </div>

                <!-- Action Buttons -->
                <div class="col-12 col-lg-2 d-flex gap-1.5 justify-content-end">
                    <button type="submit" class="lp-btn lp-btn-primary flex-grow-1" title="Terapkan Filter">
                        <i class="bi bi-funnel-fill"></i>
                        <span>Filter</span>
                    </button>
                    <a href="laporan-kegiatan.php" class="lp-btn lp-btn-secondary px-2.5" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <button type="button" class="lp-btn lp-btn-success px-2.5" data-bs-toggle="modal" data-bs-target="#syncSheetsModal" title="Sync ke Google Sheets">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    </button>
                </div>

            </div>
        </form>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 3. DAFTAR KUNJUNGAN PER CUSTOMER (CLEAN FEED)                           -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div id="customerListContainer">
        <?php
        $avatarColors = ['#2563eb', '#7c3aed', '#059669', '#d97706', '#db2777', '#0891b2', '#4f46e5'];

        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $kegiatanId = $row['id'];
                $idC        = $row['id_cust'];
                $namaC      = $row['nama_cust'];
                $kodeC      = $row['kode_customer'] ?? 'CUST';
                $alamatC    = $row['alamat_cust'] ?? '';
                $kotaC      = $row['kota_cust'] ?? '';

                // Ambil tim & pelaksanaan kegiatan ini secara komprehensif
                $sqlLapTek = "SELECT 
                                     tks.id AS id_tks,
                                     COALESCE(tks.id_sales, ps.sales_id, 0) AS id_sales,
                                     COALESCE(s.nama, tks.nama_sales, ps_s.nama, '') AS nama_sales,
                                     COALESCE(NULLIF(ps.status, ''), NULLIF(ks.status, ''), 'dijadwalkan') AS status,
                                     ps.ci_at AS tgl_mulai, 
                                     ps.co_at AS tgl_selesai,
                                     ks.id AS kode_transaksi, 
                                     ks.jadwal AS tgl_visits,
                                     COALESCE(NULLIF(ps.catatan_visit, ''), NULLIF(ps.keterangan, ''), NULLIF(ks.keterangan, '')) AS hasil_visits,
                                     ps.foto_visit_url
                              FROM kegiatan_sales ks
                              LEFT JOIN team_kegiatan_sales tks ON ks.id = tks.id_kegiatan_sales AND tks.deleted_at IS NULL
                              LEFT JOIN sales s ON tks.id_sales = s.id
                              LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = ks.id AND (ps.sales_id = tks.id_sales OR tks.id_sales IS NULL)
                              LEFT JOIN sales ps_s ON ps.sales_id = ps_s.id
                              WHERE ks.id = '$kegiatanId' AND ks.deleted_at IS NULL";
                              
                if ($filterSales > 0) {
                    $sqlLapTek .= " AND (tks.id_sales = $filterSales OR ps.sales_id = $filterSales)";
                }
                if (!empty($filterStatus)) {
                    if ($filterStatus === 'dijadwalkan') {
                        $sqlLapTek .= " AND (ps.status IS NULL OR ps.status = 'dijadwalkan' OR ps.status = '') AND ks.status != 'selesai'";
                    } else if ($filterStatus === 'berjalan') {
                        $sqlLapTek .= " AND (ps.status IN ('berjalan', 'proses') OR ks.status = 'berjalan')";
                    } else if ($filterStatus === 'selesai') {
                        $sqlLapTek .= " AND (ps.status = 'selesai' OR ks.status = 'selesai')";
                    }
                }
                $sqlLapTek .= " GROUP BY COALESCE(tks.id, ps.id, ks.id)";
                $resLapTek = mysqli_query($conn, $sqlLapTek);
                $activityCount = ($resLapTek) ? mysqli_num_rows($resLapTek) : 0;
        ?>
            <!-- Customer Card -->
            <div class="lp-cust-card" data-customer-name="<?= strtolower(htmlspecialchars($namaC)); ?>" data-customer-address="<?= strtolower(htmlspecialchars($alamatC . ' ' . $kotaC)); ?>">
                <!-- Card Header -->
                <div class="lp-cust-header">
                    <div class="lp-cust-info">
                        <div class="lp-cust-icon">
                            <i class="bi bi-shop"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-light text-dark border font-monospace px-2 py-0.5" style="font-size: 10px;">
                                    <?= htmlspecialchars($kodeC); ?>
                                </span>
                                <h6 class="lp-cust-name"><?= htmlspecialchars($namaC); ?></h6>
                                <?php if (!empty($kotaC)): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5" style="font-size: 10px;">
                                        <i class="bi bi-geo-alt-fill me-0.5"></i><?= htmlspecialchars($kotaC); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($alamatC)): ?>
                                <div class="lp-cust-addr" title="<?= htmlspecialchars($alamatC); ?>">
                                    <i class="bi bi-pin-map text-muted me-1"></i><?= htmlspecialchars($alamatC); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1" style="font-size: 11px; font-weight: 600;">
                            <?= max(1, $activityCount); ?> Penugasan
                        </span>
                    </div>
                </div>

                <!-- Activities Table Header (Desktop Only) -->
                <div class="lp-table-head d-none d-lg-grid">
                    <div>Status &amp; ID</div>
                    <div>Sales Agent</div>
                    <div>Jadwal Visit</div>
                    <div>Waktu GPS (In / Out)</div>
                    <div>Catatan / Hasil Visit</div>
                    <div class="text-end">Aksi</div>
                </div>

                <!-- Activities List -->
                <div>
                    <?php
                    if ($resLapTek && mysqli_num_rows($resLapTek) > 0) {
                        while ($rowLT = mysqli_fetch_assoc($resLapTek)) {
                            $idT = intval($rowLT["id_sales"]);
                            $namaSalesItem = trim($rowLT["nama_sales"] ?? '');
                            $hasSales = !empty($namaSalesItem) && $idT > 0;

                            $initials = $hasSales ? strtoupper(substr($namaSalesItem, 0, 2)) : '??';
                            $colorIdx = $hasSales ? (abs(crc32($namaSalesItem)) % count($avatarColors)) : 0;
                            $avatarBg = $hasSales ? $avatarColors[$colorIdx] : '#94a3b8';

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
                        <div class="lp-item-row">
                            <!-- 1. Status & ID -->
                            <div>
                                <div class="d-flex flex-column gap-1">
                                    <?php if ($status === 'selesai'): ?>
                                        <span class="lp-status-badge lp-status-selesai">
                                            <i class="bi bi-check-circle-fill"></i> Selesai
                                        </span>
                                    <?php elseif ($status === 'berjalan'): ?>
                                        <span class="lp-status-badge lp-status-berjalan">
                                            <span class="spinner-grow spinner-grow-sm" style="width: 6px; height: 6px;"></span> Diproses
                                        </span>
                                    <?php else: ?>
                                        <span class="lp-status-badge lp-status-dijadwalkan">
                                            <i class="bi bi-clock"></i> Dijadwalkan
                                        </span>
                                    <?php endif; ?>

                                    <span class="badge bg-light text-muted border font-monospace mt-0.5 text-start" style="font-size: 10px; width: fit-content;">
                                        #<?= $rowLT['kode_transaksi']; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- 2. Sales Agent -->
                            <div>
                                <?php if ($hasSales): ?>
                                    <div class="lp-sales-pill">
                                        <div class="lp-sales-avatar" style="background: <?= $avatarBg; ?>;">
                                            <?= $initials; ?>
                                        </div>
                                        <div class="lp-sales-text">
                                            <div class="lp-sales-name"><?= htmlspecialchars($namaSalesItem); ?></div>
                                            <span class="text-muted" style="font-size: 10.5px;">Sales Canvas</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="lp-sales-pill">
                                        <div class="lp-sales-avatar" style="background: #e2e8f0; color: #64748b;">
                                            <i class="bi bi-person-dash"></i>
                                        </div>
                                        <div class="lp-sales-text">
                                            <span class="badge bg-light text-muted border" style="font-size: 10.5px;">Belum Ditugaskan</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 3. Jadwal Visit -->
                            <div>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-dark" style="font-size: 12.5px;">
                                        <i class="bi bi-calendar3 text-primary me-1"></i><?= $formattedDate; ?>
                                    </span>
                                    <span class="text-muted fw-bold" style="font-size: 11px; margin-left: 17px;">
                                        <?= $formattedTime; ?> WIB
                                    </span>
                                </div>
                            </div>

                            <!-- 4. Waktu Pelaksanaan GPS -->
                            <div>
                                <div class="d-flex flex-column gap-0.5">
                                    <?php if ($formattedTimeMli): ?>
                                        <div class="text-success fw-bold font-monospace" style="font-size: 11px;">
                                            <i class="bi bi-box-arrow-in-right"></i> IN: <?= $formattedTimeMli; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 11.5px;"><i class="bi bi-geo-alt text-muted me-1"></i>Belum Masuk</span>
                                    <?php endif; ?>

                                    <?php if ($formattedTimeSls): ?>
                                        <div class="text-primary fw-bold font-monospace" style="font-size: 11px;">
                                            <i class="bi bi-box-arrow-right"></i> OUT: <?= $formattedTimeSls; ?>
                                        </div>
                                    <?php elseif ($formattedTimeMli): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace px-1.5 py-0.5 text-start" style="font-size: 9.5px; width: fit-content;">Sedang Visit</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 5. Catatan / Ringkasan Visit -->
                            <div>
                                <?php if (!empty($hslVisits)): ?>
                                    <div class="lp-note-box" title="<?= htmlspecialchars($hslVisits); ?>">
                                        <i class="bi bi-chat-quote text-primary me-1"></i>
                                        <?= htmlspecialchars($hslVisits); ?>
                                    </div>
                                <?php elseif ($status === 'selesai'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 11px;">
                                        <i class="bi bi-check me-1"></i> Kunjungan Selesai
                                    </span>
                                <?php elseif ($status === 'berjalan'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" style="font-size: 11px;">
                                        <i class="bi bi-geo-alt me-1"></i> Sedang di Lokasi Toko
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11.5px; font-style: italic;">
                                        <i class="bi bi-hourglass-split me-1"></i>Menunggu kunjungan sales
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- 6. Aksi Buttons -->
                            <div class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <!-- View Detail Modal -->
                                    <button type="button" class="lp-action-btn lp-action-view detailBtn" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="<?= $idT; ?>" data-kode="<?= $rowLT['kode_transaksi']; ?>" title="Lihat Rincian & Lokasi GPS">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>

                                    <!-- Edit Visit Modal -->
                                    <button type="button" class="lp-action-btn lp-action-edit editVisitBtn" data-id="<?= $rowLT['kode_transaksi']; ?>" data-sales="<?= $idT; ?>" title="Edit Laporan Kunjungan">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Delete / Reset Visit -->
                                    <button type="button" class="lp-action-btn lp-action-delete deleteVisitBtn" data-id="<?= $rowLT['kode_transaksi']; ?>" data-sales="<?= $idT; ?>" data-status="<?= $status; ?>" data-cust="<?= htmlspecialchars($namaC); ?>" title="Hapus / Reset Kunjungan">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php
                        }
                    } ?>
                </div>
            </div>
        <?php
            }
        } else {
        ?>
            <!-- Empty State -->
            <div class="card border-0 shadow-sm rounded-4 text-center py-5" style="background: #ffffff; border: 1px solid var(--lp-slate-200) !important;">
                <div class="card-body">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 60px; height: 60px; background: #f1f5f9; color: #64748b;">
                        <i class="bi bi-calendar-x fs-3"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1" style="font-family:'Outfit',sans-serif;">Data Kunjungan Tidak Ditemukan</h5>
                    <p class="text-muted small mb-3">Tidak ada riwayat kunjungan yang sesuai dengan filter yang dipilih.</p>
                    <a href="laporan-kegiatan.php" class="lp-btn lp-btn-primary px-3 py-2 d-inline-flex">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
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
            <div class="modal-header border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-flex align-items-center justify-content-center rounded-circle" style="width: 34px; height: 34px; background: #ecfdf5; color: #059669;">
                        <i class="bi bi-file-earmark-spreadsheet-fill fs-6"></i>
                    </span>
                    <h5 class="modal-title font-weight-bold text-dark fs-6 mb-0" id="syncSheetsModalLabel">
                        Sync ke Google Sheets
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="syncSheetsForm">
                <div class="modal-body py-3 px-4">
                    <div class="alert alert-info text-white text-xs border-0 mb-3" style="background: linear-gradient(135deg, #1d4ed8, #2563eb); border-radius: 12px; line-height: 1.5;">
                        <i class="bi bi-info-circle-fill me-1 text-sm"></i>
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
                <div class="modal-footer border-top py-2.5 px-4 bg-light">
                    <button type="button" class="btn btn-sm btn-link text-secondary mb-0" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success mb-0 fw-bold px-3 py-2 rounded-2" id="btnDoSync" style="background: #059669;">
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
            <div class="modal-header border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-flex align-items-center justify-content-center rounded-circle" style="width: 34px; height: 34px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-pencil-square fs-6"></i>
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
                
                <div class="modal-body py-3 px-4">
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
                <div class="modal-footer border-top py-2.5 px-4 bg-light">
                    <button type="button" class="btn btn-sm btn-link text-secondary mb-0" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary mb-0 fw-bold px-3 py-2 rounded-2" style="background: #2563eb;">Simpan Perubahan</button>
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
    const cards = document.querySelectorAll('.lp-cust-card');

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