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
$chkSC = $conn->query("SHOW TABLES LIKE 'sales_customer'");
$hasSalesCustomer = ($chkSC && $chkSC->num_rows > 0);

if ($hasSalesCustomer) {
    $custJoin = "INNER JOIN sales_customer sc ON ks.id_customer = sc.id";
    $custFields = "sc.nama AS nama_cust, sc.id AS id_cust, sc.alamat AS alamat_cust, sc.kota AS kota_cust, sc.kode_customer";
} else {
    $custJoin = "INNER JOIN customers sc ON ks.id_customer = sc.id LEFT JOIN customer_addresses ca ON ca.customer_id = sc.id";
    $custFields = "sc.name AS nama_cust, sc.id AS id_cust, ca.address AS alamat_cust, ca.city AS kota_cust, sc.code AS kode_customer";
}

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
               $custFields
        FROM kegiatan_sales ks
        $custJoin
        WHERE $whereSql
        ORDER BY ks.jadwal DESC";

$result = mysqli_query($conn, $sql);
?>

<style>
/* ─── Modern Vibrant & Responsive Design Architecture for Laporan Visit ─── */
:root {
  --lp-blue-600: #2563eb;
  --lp-blue-700: #1d4ed8;
  --lp-blue-50: #eff6ff;
  --lp-blue-100: #dbeafe;
  --lp-blue-200: #bfdbfe;
  
  --lp-sky-600: #0284c7;
  --lp-sky-500: #0ea5e9;
  --lp-sky-50: #f0f9ff;
  --lp-sky-100: #e0f2fe;
  --lp-sky-200: #bae6fd;

  --lp-amber-600: #d97706;
  --lp-amber-500: #f59e0b;
  --lp-amber-700: #b45309;
  --lp-amber-50: #fffbeb;
  --lp-amber-100: #fef3c7;
  --lp-amber-200: #fde68a;

  --lp-emerald-600: #059669;
  --lp-emerald-500: #10b981;
  --lp-emerald-700: #047857;
  --lp-emerald-50: #ecfdf5;
  --lp-emerald-100: #dcfce7;
  --lp-emerald-200: #a7f3d0;

  --lp-slate-950: #020617;
  --lp-slate-900: #0f172a;
  --lp-slate-800: #1e293b;
  --lp-slate-700: #334155;
  --lp-slate-600: #475569;
  --lp-slate-500: #64748b;
  --lp-slate-400: #94a3b8;
  --lp-slate-300: #cbd5e1;
  --lp-slate-200: #e2e8f0;
  --lp-slate-100: #f1f5f9;
  --lp-slate-50: #f8fafc;
  
  --lp-card-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
  --lp-card-hover: 0 12px 28px -4px rgba(15, 23, 42, 0.12), 0 4px 10px -2px rgba(15, 23, 42, 0.06);
}

/* ─── KPI Metric Cards (Vibrant Aesthetic) ─── */
.lp-kpi-card {
  border-radius: 18px;
  padding: 20px 22px;
  box-shadow: var(--lp-card-shadow);
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.lp-kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--lp-card-hover);
}

/* Card Themes */
.lp-kpi-total {
  background: linear-gradient(145deg, #ffffff 0%, #f0f7ff 100%);
  border: 2px solid #bfdbfe;
}
.lp-kpi-total:hover {
  border-color: #3b82f6;
}
.lp-kpi-total.active-filter {
  border-color: #2563eb;
  background: linear-gradient(145deg, #ffffff 0%, #e0f2fe 100%);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25), var(--lp-card-shadow);
}
.lp-kpi-total .lp-kpi-icon-box {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  color: #ffffff;
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}
.lp-kpi-total .lp-kpi-value {
  color: #1e3a8a;
}
.lp-kpi-total .lp-kpi-badge {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1.5px solid #bfdbfe;
}

.lp-kpi-dijadwalkan {
  background: linear-gradient(145deg, #ffffff 0%, #f0f9ff 100%);
  border: 2px solid #bae6fd;
}
.lp-kpi-dijadwalkan:hover {
  border-color: #0ea5e9;
}
.lp-kpi-dijadwalkan.active-filter {
  border-color: #0284c7;
  background: linear-gradient(145deg, #ffffff 0%, #e0f2fe 100%);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25), var(--lp-card-shadow);
}
.lp-kpi-dijadwalkan .lp-kpi-icon-box {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: #ffffff;
  box-shadow: 0 6px 16px rgba(14, 165, 233, 0.35);
}
.lp-kpi-dijadwalkan .lp-kpi-value {
  color: #0369a1;
}
.lp-kpi-dijadwalkan .lp-kpi-badge {
  background: #e0f2fe;
  color: #0284c7;
  border: 1.5px solid #bae6fd;
}

.lp-kpi-diproses {
  background: linear-gradient(145deg, #ffffff 0%, #fffbeb 100%);
  border: 2px solid #fde68a;
}
.lp-kpi-diproses:hover {
  border-color: #f59e0b;
}
.lp-kpi-diproses.active-filter {
  border-color: #d97706;
  background: linear-gradient(145deg, #ffffff 0%, #fef3c7 100%);
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25), var(--lp-card-shadow);
}
.lp-kpi-diproses .lp-kpi-icon-box {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #ffffff;
  box-shadow: 0 6px 16px rgba(245, 158, 11, 0.38);
}
.lp-kpi-diproses .lp-kpi-value {
  color: #b45309;
}
.lp-kpi-diproses .lp-kpi-badge {
  background: #fef3c7;
  color: #b45309;
  border: 1.5px solid #fcd34d;
}

.lp-kpi-selesai {
  background: linear-gradient(145deg, #ffffff 0%, #ecfdf5 100%);
  border: 2px solid #a7f3d0;
}
.lp-kpi-selesai:hover {
  border-color: #10b981;
}
.lp-kpi-selesai.active-filter {
  border-color: #059669;
  background: linear-gradient(145deg, #ffffff 0%, #dcfce7 100%);
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25), var(--lp-card-shadow);
}
.lp-kpi-selesai .lp-kpi-icon-box {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #ffffff;
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}
.lp-kpi-selesai .lp-kpi-value {
  color: #047857;
}
.lp-kpi-selesai .lp-kpi-badge {
  background: #dcfce7;
  color: #15803d;
  border: 1.5px solid #86efac;
}

.lp-kpi-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.lp-kpi-label {
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--lp-slate-600);
  margin-bottom: 6px;
}

.lp-kpi-value {
  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  font-size: 34px;
  font-weight: 900;
  line-height: 1.05;
  margin: 0;
  letter-spacing: -0.03em;
}

.lp-kpi-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.lp-kpi-icon-box span.material-symbols-outlined {
  font-size: 26px;
  font-variation-settings: 'FILL' 1, 'wght' 700;
}

.lp-kpi-footer {
  margin-top: 14px;
  padding-top: 10px;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
  color: var(--lp-slate-600);
  font-weight: 700;
}

.lp-kpi-badge {
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

/* Pulsing live dot */
.lp-pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #f59e0b;
  display: inline-block;
  box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
  animation: lpPulse 1.6s infinite cubic-bezier(0.66, 0, 0, 1);
}
@keyframes lpPulse {
  0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
  70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
  100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

/* ─── Filter & Search Toolbar ─── */
.lp-toolbar-card {
  background: #ffffff;
  border: 2px solid var(--lp-slate-200);
  border-radius: 18px;
  padding: 16px 20px;
  box-shadow: var(--lp-card-shadow);
  margin-bottom: 22px;
}

.lp-input {
  height: 44px;
  border: 2px solid var(--lp-slate-300);
  border-radius: 12px;
  padding: 8px 14px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--lp-slate-950);
  transition: all 0.2s ease;
  outline: none;
  background-color: #ffffff;
}

.lp-input:focus {
  border-color: var(--lp-blue-600);
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
  background-color: #ffffff;
}

.lp-btn {
  height: 44px;
  padding: 0 16px;
  font-size: 13.5px;
  font-weight: 800;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
  cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
}

.lp-btn:active {
  transform: scale(0.98);
}

.lp-btn-primary {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: #ffffff !important;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.32);
}
.lp-btn-primary:hover {
  background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
  box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45);
  transform: translateY(-1px);
}

.lp-btn-secondary {
  background: #ffffff;
  color: var(--lp-slate-700);
  border: 2px solid var(--lp-slate-300);
}
.lp-btn-secondary:hover {
  background: var(--lp-slate-100);
  border-color: var(--lp-slate-400);
  color: var(--lp-slate-950);
  transform: translateY(-1px);
}

.lp-btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #ffffff !important;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.32);
}
.lp-btn-success:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  box-shadow: 0 6px 18px rgba(16, 185, 129, 0.45);
  transform: translateY(-1px);
}

/* ─── Customer Activity Card (Vibrant Container) ─── */
.lp-cust-card {
  background: #ffffff;
  border: 2px solid var(--lp-slate-200);
  border-radius: 18px;
  box-shadow: var(--lp-card-shadow);
  margin-bottom: 20px;
  overflow: hidden;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.lp-cust-card:hover {
  box-shadow: var(--lp-card-hover);
  border-color: #cbd5e1;
}

.lp-cust-header {
  padding: 16px 20px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  border-bottom: 2px solid var(--lp-slate-200);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.lp-cust-info {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.lp-cust-icon {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.28);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 20px;
}

.lp-cust-name {
  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  font-size: 17px;
  font-weight: 800;
  color: var(--lp-slate-950);
  margin: 0;
  line-height: 1.25;
}

.lp-cust-addr {
  font-size: 13px;
  font-weight: 500;
  color: var(--lp-slate-600);
  margin-top: 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 620px;
}

/* ─── Desktop Table Header (>= 992px) ─── */
.lp-table-head {
  padding: 11px 20px;
  background: #f1f5f9;
  border-bottom: 2px solid var(--lp-slate-200);
  display: grid;
  grid-template-columns: 140px 185px 165px 160px minmax(180px, 1fr) 115px;
  align-items: center;
  gap: 14px;
  font-size: 11.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--lp-slate-600);
}

/* ─── Item Row (Desktop Grid Default) ─── */
.lp-item-row {
  padding: 14px 20px;
  border-bottom: 1.5px solid var(--lp-slate-100);
  display: grid;
  grid-template-columns: 140px 185px 165px 160px minmax(180px, 1fr) 115px;
  align-items: center;
  gap: 14px;
  transition: background 0.15s ease;
  position: relative;
}

.lp-item-row:last-child {
  border-bottom: none;
}

.lp-item-row:hover {
  background: #f8fafc;
}

/* Sales Agent Pill */
.lp-sales-pill {
  display: flex;
  align-items: center;
  gap: 10px;
}

.lp-sales-avatar {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 13px;
  color: #ffffff;
  flex-shrink: 0;
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
}

.lp-sales-text {
  min-width: 0;
}

.lp-sales-name {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--lp-slate-950);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.2;
}

/* Note Box */
.lp-note-box {
  background: #f8fafc;
  border: 1.5px solid var(--lp-slate-300);
  border-radius: 10px;
  padding: 7px 12px;
  font-size: 13px;
  color: var(--lp-slate-900);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  max-width: 340px;
  width: fit-content;
  transition: all 0.2s ease;
  cursor: default;
}

.lp-note-box:hover {
  background: #ffffff;
  border-color: var(--lp-blue-600);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
}

.lp-note-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 290px;
  display: inline-block;
  font-weight: 600;
}

/* Action Buttons */
.lp-action-btn {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid transparent;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 14px;
  cursor: pointer;
  text-decoration: none;
}

.lp-action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
}

.lp-action-view {
  background: #eff6ff;
  color: #2563eb;
  border-color: #bfdbfe;
}
.lp-action-view:hover {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
}

.lp-action-edit {
  background: #ecfdf5;
  color: #059669;
  border-color: #a7f3d0;
}
.lp-action-edit:hover {
  background: #059669;
  color: #ffffff;
  border-color: #059669;
}

.lp-action-delete {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
}
.lp-action-delete:hover {
  background: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
}

/* Status Badges */
.lp-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.02em;
  width: fit-content;
}

.lp-status-selesai {
  background: #dcfce7;
  color: #14532d;
  border: 1.5px solid #86efac;
}

.lp-status-berjalan {
  background: #fef3c7;
  color: #78350f;
  border: 1.5px solid #fcd34d;
}

.lp-status-dijadwalkan {
  background: #f1f5f9;
  color: #1e293b;
  border: 1.5px solid #cbd5e1;
}

/* ─── RESPONSIVE BREAKPOINTS ARCHITECTURE ─── */

/* 1. Tablet & Mobile Responsive Switch (< 992px) */
@media (max-width: 991.98px) {
  .lp-item-row {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 16px;
    border-bottom: 2px solid var(--lp-slate-200);
  }

  .lp-item-row:hover {
    background: #ffffff;
  }

  /* Responsive sub-sections inside row */
  .lp-row-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
  }

  .lp-row-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    align-items: center;
    padding: 10px 12px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1.5px solid var(--lp-slate-200);
  }

  .lp-row-gps-notes {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
  }

  .lp-note-box {
    max-width: 100%;
    width: 100%;
  }

  .lp-note-text {
    max-width: 100%;
  }
}

/* 2. Mobile Specific Breakpoints (< 576px) */
@media (max-width: 575.98px) {
  .lp-kpi-card {
    padding: 14px 14px;
    border-radius: 14px;
  }

  .lp-kpi-label {
    font-size: 11px;
    margin-bottom: 4px;
  }

  .lp-kpi-value {
    font-size: 24px;
  }

  .lp-kpi-icon-box {
    width: 38px;
    height: 38px;
    border-radius: 10px;
  }

  .lp-kpi-icon-box span.material-symbols-outlined {
    font-size: 20px;
  }

  .lp-kpi-footer {
    margin-top: 10px;
    padding-top: 8px;
    font-size: 11px;
    flex-wrap: wrap;
    gap: 4px;
  }

  .lp-kpi-badge {
    font-size: 10.5px;
    padding: 2px 8px;
  }

  .lp-toolbar-card {
    padding: 14px;
    border-radius: 14px;
  }

  .lp-cust-header {
    padding: 14px;
  }

  .lp-cust-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    font-size: 18px;
  }

  .lp-cust-name {
    font-size: 15px;
  }

  .lp-cust-addr {
    max-width: 250px;
    font-size: 12px;
  }

  .lp-row-grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }
}
</style>

<div class="col-12">
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 1. TOP STAT KPI METRICS (VIBRANT REFRESH)                               -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-2 g-md-3 mb-4">
        <!-- Card 1: Total Visit -->
        <div class="col-6 col-md-3">
            <div class="lp-kpi-card lp-kpi-total <?= (empty($filterStatus)) ? 'active-filter' : ''; ?>" onclick="window.location.href='laporan-kegiatan.php?status=&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'" title="Klik untuk menampilkan semua visit">
                <div class="lp-kpi-top">
                    <div>
                        <div class="lp-kpi-label">TOTAL VISIT</div>
                        <h3 class="lp-kpi-value"><?= number_format($kpiTotal); ?></h3>
                    </div>
                    <div class="lp-kpi-icon-box">
                        <span class="material-symbols-outlined">event_available</span>
                    </div>
                </div>
                <div class="lp-kpi-footer">
                    <span><i class="bi bi-calendar-check me-1 text-primary"></i> Periode</span>
                    <span class="lp-kpi-badge"><i class="bi bi-calendar-event me-0.5"></i> <?= htmlspecialchars($dateLabel); ?></span>
                </div>
            </div>
        </div>

        <!-- Card 2: Dijadwalkan -->
        <div class="col-6 col-md-3">
            <div class="lp-kpi-card lp-kpi-dijadwalkan <?= ($filterStatus === 'dijadwalkan') ? 'active-filter' : ''; ?>" onclick="window.location.href='laporan-kegiatan.php?status=dijadwalkan&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'" title="Klik untuk filter jadwal mendatang">
                <div class="lp-kpi-top">
                    <div>
                        <div class="lp-kpi-label">DIJADWALKAN</div>
                        <h3 class="lp-kpi-value"><?= number_format($kpiDijadwalkan); ?></h3>
                    </div>
                    <div class="lp-kpi-icon-box">
                        <span class="material-symbols-outlined">calendar_month</span>
                    </div>
                </div>
                <div class="lp-kpi-footer">
                    <span><i class="bi bi-clock-history me-1 text-info"></i> Status</span>
                    <span class="lp-kpi-badge"><i class="bi bi-hourglass-top me-0.5"></i> Jadwal Mendatang</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Diproses (GPS) -->
        <div class="col-6 col-md-3">
            <div class="lp-kpi-card lp-kpi-diproses <?= ($filterStatus === 'berjalan') ? 'active-filter' : ''; ?>" onclick="window.location.href='laporan-kegiatan.php?status=berjalan&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'" title="Klik untuk filter sales sedang di lokasi">
                <div class="lp-kpi-top">
                    <div>
                        <div class="lp-kpi-label">DIPROSES (GPS)</div>
                        <h3 class="lp-kpi-value"><?= number_format($kpiBerjalan); ?></h3>
                    </div>
                    <div class="lp-kpi-icon-box">
                        <span class="material-symbols-outlined">explore</span>
                    </div>
                </div>
                <div class="lp-kpi-footer">
                    <span><i class="bi bi-geo-alt-fill me-1 text-warning"></i> Status</span>
                    <span class="lp-kpi-badge"><span class="lp-pulse-dot me-1"></span> Sedang di Lokasi</span>
                </div>
            </div>
        </div>

        <!-- Card 4: Selesai -->
        <div class="col-6 col-md-3">
            <div class="lp-kpi-card lp-kpi-selesai <?= ($filterStatus === 'selesai') ? 'active-filter' : ''; ?>" onclick="window.location.href='laporan-kegiatan.php?status=selesai&id_sales=<?= $filterSales; ?>&bulan=<?= $filterBulan; ?>&tanggal=<?= $filterTanggal; ?>'" title="Klik untuk filter kunjungan rampung">
                <div class="lp-kpi-top">
                    <div>
                        <div class="lp-kpi-label">SELESAI</div>
                        <h3 class="lp-kpi-value"><?= number_format($kpiSelesai); ?></h3>
                    </div>
                    <div class="lp-kpi-icon-box">
                        <span class="material-symbols-outlined">task_alt</span>
                    </div>
                </div>
                <div class="lp-kpi-footer">
                    <span><i class="bi bi-check2-all me-1 text-success"></i> Status</span>
                    <span class="lp-kpi-badge"><i class="bi bi-check-circle-fill me-0.5"></i> Rampung</span>
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
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute text-primary" style="left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px;"></i>
                        <input type="text" id="liveSearchCustomerInput" class="lp-input w-100 ps-4 pe-4" placeholder="Cari nama toko atau alamat..." autocomplete="off">
                        <span id="clearLiveSearch" class="position-absolute text-muted d-none" style="right: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; cursor: pointer;">
                            <i class="bi bi-x-circle-fill"></i>
                        </span>
                    </div>
                </div>

                <!-- Filter Sales Dropdown -->
                <div class="col-6 col-md-3 col-lg-2">
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
                <div class="col-6 col-md-3 col-lg-2">
                    <select name="status" class="lp-input w-100" style="font-size: 12.5px;">
                        <option value="">Semua Status</option>
                        <option value="dijadwalkan" <?= ($filterStatus === 'dijadwalkan') ? 'selected' : ''; ?>>Dijadwalkan</option>
                        <option value="berjalan" <?= ($filterStatus === 'berjalan') ? 'selected' : ''; ?>>Diproses (GPS)</option>
                        <option value="selesai" <?= ($filterStatus === 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                    </select>
                </div>

                <!-- Filter Bulan -->
                <div class="col-6 col-md-3 col-lg-2">
                    <input type="month" name="bulan" value="<?= htmlspecialchars($filterBulan); ?>" class="lp-input w-100" title="Pilih Bulan">
                </div>

                <!-- Filter Tanggal -->
                <div class="col-6 col-md-3 col-lg-1">
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($filterTanggal); ?>" class="lp-input w-100" title="Pilih Tanggal Spesifik">
                </div>

                <!-- Action Buttons -->
                <div class="col-12 col-md-6 col-lg-2 d-flex gap-1.5 justify-content-end">
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
    <!-- 3. DAFTAR KUNJUNGAN PER CUSTOMER (RESPONSIVE FEED)                      -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div id="customerListContainer">
        <?php
        $avatarGradients = [
            'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            'linear-gradient(135deg, #8b5cf6, #6d28d9)',
            'linear-gradient(135deg, #10b981, #047857)',
            'linear-gradient(135deg, #f59e0b, #b45309)',
            'linear-gradient(135deg, #ec4899, #be185d)',
            'linear-gradient(135deg, #06b6d4, #0e7490)',
            'linear-gradient(135deg, #6366f1, #4338ca)',
            'linear-gradient(135deg, #f43f5e, #be123c)'
        ];

        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $kegiatanId = $row['id'];
                $idC        = $row['id_cust'];
                $namaC      = $row['nama_cust'];
                $kodeC      = $row['kode_customer'] ?? 'CUST';
                $alamatC    = $row['alamat_cust'] ?? '';
                $kotaC      = $row['kota_cust'] ?? '';

                // Ambil tim & pelaksanaan kegiatan ini
                $sqlLapTek = "SELECT tks.*, 
                                     COALESCE(s.nama, tks.nama_sales, 'Sales') AS nama_sales, 
                                     tks.id_sales,
                                     COALESCE(NULLIF(ps.status, ''), NULLIF(ks.status, ''), 'dijadwalkan') AS status,
                                     ps.ci_at AS tgl_mulai, 
                                     ps.co_at AS tgl_selesai,
                                     ks.id AS kode_transaksi, 
                                     ks.jadwal AS tgl_visits,
                                     COALESCE(NULLIF(ps.catatan_visit, ''), NULLIF(ps.keterangan, ''), NULLIF(ks.keterangan, '')) AS hasil_visits,
                                     ps.foto_visit_url
                              FROM team_kegiatan_sales tks
                              LEFT JOIN sales s ON tks.id_sales = s.id
                              JOIN kegiatan_sales ks ON tks.id_kegiatan_sales = ks.id
                              LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = tks.id_kegiatan_sales AND (ps.sales_id = tks.id_sales OR ps.sales_id IS NULL)
                              WHERE tks.id_kegiatan_sales = '$kegiatanId' AND tks.deleted_at IS NULL";
                              
                if ($filterSales > 0) {
                    $sqlLapTek .= " AND tks.id_sales = $filterSales";
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
                $resLapTek = mysqli_query($conn, $sqlLapTek);
                $activityCount = ($resLapTek) ? mysqli_num_rows($resLapTek) : 0;
        ?>
            <!-- Customer Card -->
            <div class="lp-cust-card" data-customer-name="<?= strtolower(htmlspecialchars($namaC)); ?>" data-customer-address="<?= strtolower(htmlspecialchars($alamatC . ' ' . $kotaC)); ?>">
                <!-- Card Header -->
                <div class="lp-cust-header">
                    <div class="lp-cust-info">
                        <div class="lp-cust-icon">
                            <i class="bi bi-shop-window"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-dark text-white border font-monospace px-2.5 py-1 fw-bold" style="font-size: 11.5px; border-radius: 8px;">
                                    <?= htmlspecialchars($kodeC); ?>
                                </span>
                                <h6 class="lp-cust-name"><?= htmlspecialchars($namaC); ?></h6>
                                <?php if (!empty($kotaC)): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size: 11.5px;">
                                        <i class="bi bi-geo-alt-fill me-0.5"></i><?= htmlspecialchars($kotaC); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($alamatC)): ?>
                                <div class="lp-cust-addr" title="<?= htmlspecialchars($alamatC); ?>">
                                    <i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($alamatC); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 fw-bold" style="font-size: 12px;">
                            <i class="bi bi-person-check-fill me-1"></i> <?= max(1, $activityCount); ?> Penugasan
                        </span>
                    </div>
                </div>

                <!-- Activities Table Header (Desktop >= 992px Only) -->
                <div class="lp-table-head d-none d-lg-grid">
                    <div>STATUS &amp; ID</div>
                    <div>SALES AGENT</div>
                    <div>JADWAL VISIT</div>
                    <div>WAKTU GPS (IN / OUT)</div>
                    <div>CATATAN / HASIL VISIT</div>
                    <div class="text-end">AKSI</div>
                </div>

                <!-- Activities List -->
                <div>
                    <?php
                    if ($resLapTek && mysqli_num_rows($resLapTek) > 0) {
                        while ($rowLT = mysqli_fetch_assoc($resLapTek)) {
                            $idT = intval($rowLT["id_sales"]);
                            $namaSalesItem = trim($rowLT["nama_sales"] ?? '');
                            $hasSales = !empty($namaSalesItem) && $idT > 0 && $namaSalesItem !== 'Sales';

                            $initials = $hasSales ? strtoupper(substr($namaSalesItem, 0, 2)) : '??';
                            $colorIdx = $hasSales ? (abs(crc32($namaSalesItem)) % count($avatarGradients)) : 0;
                            $avatarBg = $hasSales ? $avatarGradients[$colorIdx] : 'linear-gradient(135deg, #64748b, #475569)';

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
                            <!-- Mobile Header: Status & Aksi (< 992px) / Column 1 on Desktop -->
                            <div class="lp-row-top d-flex d-lg-block justify-content-between align-items-center">
                                <div class="d-flex flex-wrap align-items-center gap-1.5">
                                    <?php if ($status === 'selesai'): ?>
                                        <span class="lp-status-badge lp-status-selesai">
                                            <i class="bi bi-check-circle-fill"></i> Selesai
                                        </span>
                                    <?php elseif ($status === 'berjalan'): ?>
                                        <span class="lp-status-badge lp-status-berjalan">
                                            <span class="lp-pulse-dot"></span> Diproses
                                        </span>
                                    <?php else: ?>
                                        <span class="lp-status-badge lp-status-dijadwalkan">
                                            <i class="bi bi-clock-fill"></i> Dijadwalkan
                                        </span>
                                    <?php endif; ?>

                                    <span class="badge bg-light text-dark border font-monospace fw-bold" style="font-size: 11px; border-radius: 6px; border: 1.5px solid #cbd5e1 !important;">
                                        #<?= $rowLT['kode_transaksi']; ?>
                                    </span>
                                </div>

                                <!-- Mobile Action Buttons (< 992px) -->
                                <div class="d-flex d-lg-none align-items-center gap-1.5">
                                    <button type="button" class="lp-action-btn lp-action-view detailBtn" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="<?= $idT; ?>" data-kode="<?= $rowLT['kode_transaksi']; ?>" title="Lihat Rincian & Lokasi GPS">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                    <button type="button" class="lp-action-btn lp-action-edit editVisitBtn" data-id="<?= $rowLT['kode_transaksi']; ?>" data-sales="<?= $idT; ?>" title="Edit Laporan Kunjungan">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="lp-action-btn lp-action-delete deleteVisitBtn" data-id="<?= $rowLT['kode_transaksi']; ?>" data-sales="<?= $idT; ?>" data-status="<?= $status; ?>" data-cust="<?= htmlspecialchars($namaC); ?>" title="Hapus / Reset Kunjungan">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
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
                                            <span class="text-secondary fw-semibold" style="font-size: 11px;">Sales Canvas</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="lp-sales-pill">
                                        <div class="lp-sales-avatar" style="background: #e2e8f0; color: #475569;">
                                            <i class="bi bi-person-dash fs-6"></i>
                                        </div>
                                        <div class="lp-sales-text">
                                            <span class="badge bg-light text-dark border fw-bold" style="font-size: 11.5px;">Belum Ditugaskan</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 3. Jadwal Visit -->
                            <div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark" style="font-size: 13.5px;">
                                        <i class="bi bi-calendar3 text-primary me-1"></i><?= $formattedDate; ?>
                                    </span>
                                    <span class="fw-bold text-primary" style="font-size: 12.5px; margin-left: 17px;">
                                        <i class="bi bi-clock me-0.5"></i> <?= $formattedTime; ?> WIB
                                    </span>
                                </div>
                            </div>

                            <!-- 4. Waktu Pelaksanaan GPS -->
                            <div>
                                <div class="d-flex flex-column gap-1">
                                    <?php if ($formattedTimeMli): ?>
                                        <div class="text-success fw-bold font-monospace" style="font-size: 12px;">
                                            <i class="bi bi-box-arrow-in-right"></i> IN: <?= $formattedTimeMli; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted fw-semibold" style="font-size: 12px;"><i class="bi bi-geo-alt text-muted me-1"></i>Belum Masuk</span>
                                    <?php endif; ?>

                                    <?php if ($formattedTimeSls): ?>
                                        <div class="text-primary fw-bold font-monospace" style="font-size: 12px;">
                                            <i class="bi bi-box-arrow-right"></i> OUT: <?= $formattedTimeSls; ?>
                                        </div>
                                    <?php elseif ($formattedTimeMli): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace px-1.5 py-0.5 text-start fw-bold" style="font-size: 10px; width: fit-content;">Sedang Visit</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 5. Catatan / Ringkasan Visit -->
                            <div>
                                <?php if (!empty($hslVisits)): ?>
                                    <div class="lp-note-box" title="<?= htmlspecialchars($hslVisits); ?>">
                                        <i class="bi bi-chat-quote-fill text-primary flex-shrink-0" style="font-size: 13px;"></i>
                                        <span class="lp-note-text"><?= htmlspecialchars($hslVisits); ?></span>
                                    </div>
                                <?php elseif ($status === 'selesai'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-bold" style="font-size: 12px;">
                                        <i class="bi bi-check-lg me-1"></i> Kunjungan Selesai
                                    </span>
                                <?php elseif ($status === 'berjalan'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 fw-bold" style="font-size: 12px;">
                                        <i class="bi bi-geo-alt me-1"></i> Sedang di Lokasi Toko
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fw-semibold" style="font-size: 12px; font-style: italic;">
                                        <i class="bi bi-hourglass-split me-1"></i>Menunggu kunjungan sales
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- 6. Desktop Aksi Buttons (>= 992px) -->
                            <div class="text-end d-none d-lg-block">
                                <div class="d-flex align-items-center justify-content-end gap-1.5">
                                    <button type="button" class="lp-action-btn lp-action-view detailBtn" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="<?= $idT; ?>" data-kode="<?= $rowLT['kode_transaksi']; ?>" title="Lihat Rincian & Lokasi GPS">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                    <button type="button" class="lp-action-btn lp-action-edit editVisitBtn" data-id="<?= $rowLT['kode_transaksi']; ?>" data-sales="<?= $idT; ?>" title="Edit Laporan Kunjungan">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="lp-action-btn lp-action-delete deleteVisitBtn" data-id="<?= $rowLT['kode_transaksi']; ?>" data-sales="<?= $idT; ?>" data-status="<?= $status; ?>" data-cust="<?= htmlspecialchars($namaC); ?>" title="Hapus / Reset Kunjungan">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php
                        }
                    } else {
                        // Fallback row untuk kegiatan yang belum memiliki tim di team_kegiatan_sales
                        $qSingle = mysqli_query($conn, "
                            SELECT ks.id AS kode_transaksi, ks.jadwal AS tgl_visits,
                                   COALESCE(NULLIF(ps.status, ''), NULLIF(ks.status, ''), 'dijadwalkan') AS status,
                                   ps.ci_at AS tgl_mulai, ps.co_at AS tgl_selesai,
                                   COALESCE(NULLIF(ps.catatan_visit, ''), NULLIF(ps.keterangan, ''), NULLIF(ks.keterangan, '')) AS hasil_visits,
                                   s.nama AS nama_sales,
                                   COALESCE(ps.sales_id, 0) AS id_sales
                            FROM kegiatan_sales ks
                            LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = ks.id
                            LEFT JOIN sales s ON ps.sales_id = s.id
                            WHERE ks.id = '$kegiatanId'
                            LIMIT 1
                        ");
                        $rowSingle = ($qSingle) ? mysqli_fetch_assoc($qSingle) : null;

                        $singleStatus = strtolower($rowSingle['status'] ?? ($row['status_kegiatan'] ?? 'dijadwalkan'));
                        if ($singleStatus === 'proses' || $singleStatus === 'berjalan') {
                            $statusFb = 'berjalan';
                        } elseif ($singleStatus === 'selesai') {
                            $statusFb = 'selesai';
                        } else {
                            $statusFb = 'dijadwalkan';
                        }

                        $datetimeFallback = $rowSingle['tgl_visits'] ?? $row["tgl_visits"];
                        $formattedDateFb = ($datetimeFallback && $datetimeFallback != '0000-00-00 00:00:00') ? date("d M Y", strtotime($datetimeFallback)) : '-';
                        $formattedTimeFb = ($datetimeFallback && $datetimeFallback != '0000-00-00 00:00:00') ? date("H:i", strtotime($datetimeFallback)) : '-';

                        $singleMulai = $rowSingle['tgl_mulai'] ?? null;
                        $formattedTimeMliFb = ($singleMulai && $singleMulai != '0000-00-00 00:00:00') ? date("H:i", strtotime($singleMulai)) : null;

                        $singleSelesai = $rowSingle['tgl_selesai'] ?? null;
                        $formattedTimeSlsFb = ($singleSelesai && $singleSelesai != '0000-00-00 00:00:00') ? date("H:i", strtotime($singleSelesai)) : null;

                        $hslVisitsFb = trim($rowSingle['hasil_visits'] ?? ($row['agenda_kunjungan'] ?? ''));
                        $salesNameFb = trim($rowSingle['nama_sales'] ?? '');
                        $salesIdFb = intval($rowSingle['id_sales'] ?? 0);
                    ?>
                        <div class="lp-item-row">
                            <!-- Mobile Header: Status & Aksi (< 992px) / Column 1 on Desktop -->
                            <div class="lp-row-top d-flex d-lg-block justify-content-between align-items-center">
                                <div class="d-flex flex-wrap align-items-center gap-1.5">
                                    <?php if ($statusFb === 'selesai'): ?>
                                        <span class="lp-status-badge lp-status-selesai">
                                            <i class="bi bi-check-circle-fill"></i> Selesai
                                        </span>
                                    <?php elseif ($statusFb === 'berjalan'): ?>
                                        <span class="lp-status-badge lp-status-berjalan">
                                            <span class="lp-pulse-dot"></span> Diproses
                                        </span>
                                    <?php else: ?>
                                        <span class="lp-status-badge lp-status-dijadwalkan">
                                            <i class="bi bi-clock-fill"></i> Dijadwalkan
                                        </span>
                                    <?php endif; ?>

                                    <span class="badge bg-light text-dark border font-monospace fw-bold" style="font-size: 11px; border-radius: 6px; border: 1.5px solid #cbd5e1 !important;">
                                        #<?= $row['kode_transaksi']; ?>
                                    </span>
                                </div>

                                <!-- Mobile Action Buttons (< 992px) -->
                                <div class="d-flex d-lg-none align-items-center gap-1.5">
                                    <button type="button" class="lp-action-btn lp-action-view detailBtn" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="<?= $salesIdFb; ?>" data-kode="<?= $row['kode_transaksi']; ?>" title="Lihat Rincian & Lokasi GPS">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                    <button type="button" class="lp-action-btn lp-action-edit editVisitBtn" data-id="<?= $row['kode_transaksi']; ?>" data-sales="<?= $salesIdFb; ?>" title="Edit Laporan Kunjungan">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="lp-action-btn lp-action-delete deleteVisitBtn" data-id="<?= $row['kode_transaksi']; ?>" data-sales="<?= $salesIdFb; ?>" data-status="<?= $statusFb; ?>" data-cust="<?= htmlspecialchars($namaC); ?>" title="Hapus / Reset Kunjungan">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- 2. Sales Agent -->
                            <div>
                                <?php if (!empty($salesNameFb) && $salesIdFb > 0): ?>
                                    <div class="lp-sales-pill">
                                        <div class="lp-sales-avatar" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                                            <?= strtoupper(substr($salesNameFb, 0, 2)); ?>
                                        </div>
                                        <div class="lp-sales-text">
                                            <div class="lp-sales-name"><?= htmlspecialchars($salesNameFb); ?></div>
                                            <span class="text-secondary fw-semibold" style="font-size: 11px;">Sales Canvas</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="lp-sales-pill">
                                        <div class="lp-sales-avatar" style="background: #e2e8f0; color: #475569;">
                                            <i class="bi bi-person-dash fs-6"></i>
                                        </div>
                                        <div class="lp-sales-text">
                                            <span class="badge bg-light text-dark border fw-bold" style="font-size: 11.5px;">Belum Ditugaskan</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 3. Jadwal Visit -->
                            <div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark" style="font-size: 13.5px;">
                                        <i class="bi bi-calendar3 text-primary me-1"></i><?= $formattedDateFb; ?>
                                    </span>
                                    <span class="fw-bold text-primary" style="font-size: 12.5px; margin-left: 17px;">
                                        <i class="bi bi-clock me-0.5"></i> <?= $formattedTimeFb; ?> WIB
                                    </span>
                                </div>
                            </div>

                            <!-- 4. Waktu GPS -->
                            <div>
                                <div class="d-flex flex-column gap-1">
                                    <?php if ($formattedTimeMliFb): ?>
                                        <div class="text-success fw-bold font-monospace" style="font-size: 12px;">
                                            <i class="bi bi-box-arrow-in-right"></i> IN: <?= $formattedTimeMliFb; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted fw-semibold" style="font-size: 12px;"><i class="bi bi-geo-alt text-muted me-1"></i>Belum Masuk</span>
                                    <?php endif; ?>

                                    <?php if ($formattedTimeSlsFb): ?>
                                        <div class="text-primary fw-bold font-monospace" style="font-size: 12px;">
                                            <i class="bi bi-box-arrow-right"></i> OUT: <?= $formattedTimeSlsFb; ?>
                                        </div>
                                    <?php elseif ($formattedTimeMliFb): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace px-1.5 py-0.5 text-start fw-bold" style="font-size: 10px; width: fit-content;">Sedang Visit</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 5. Catatan Visit -->
                            <div>
                                <?php if (!empty($hslVisitsFb)): ?>
                                    <div class="lp-note-box" title="<?= htmlspecialchars($hslVisitsFb); ?>">
                                        <i class="bi bi-chat-quote-fill text-primary flex-shrink-0" style="font-size: 13px;"></i>
                                        <span class="lp-note-text"><?= htmlspecialchars($hslVisitsFb); ?></span>
                                    </div>
                                <?php elseif ($statusFb === 'selesai'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-bold" style="font-size: 12px;">
                                        <i class="bi bi-check-lg me-1"></i> Kunjungan Selesai
                                    </span>
                                <?php elseif ($statusFb === 'berjalan'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 fw-bold" style="font-size: 12px;">
                                        <i class="bi bi-geo-alt me-1"></i> Sedang di Lokasi Toko
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fw-semibold" style="font-size: 12px; font-style: italic;">
                                        <i class="bi bi-hourglass-split me-1"></i>Menunggu kunjungan sales
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- 6. Desktop Aksi Buttons (>= 992px) -->
                            <div class="text-end d-none d-lg-block">
                                <div class="d-flex align-items-center justify-content-end gap-1.5">
                                    <button type="button" class="lp-action-btn lp-action-view detailBtn" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="<?= $salesIdFb; ?>" data-kode="<?= $row['kode_transaksi']; ?>" title="Lihat Rincian & Lokasi GPS">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                    <button type="button" class="lp-action-btn lp-action-edit editVisitBtn" data-id="<?= $row['kode_transaksi']; ?>" data-sales="<?= $salesIdFb; ?>" title="Edit Laporan Kunjungan">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="lp-action-btn lp-action-delete deleteVisitBtn" data-id="<?= $row['kode_transaksi']; ?>" data-sales="<?= $salesIdFb; ?>" data-status="<?= $statusFb; ?>" data-cust="<?= htmlspecialchars($namaC); ?>" title="Hapus / Reset Kunjungan">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
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
            <div class="card border-0 shadow-sm rounded-4 text-center py-5" style="background: #ffffff; border: 2px dashed var(--lp-slate-300) !important;">
                <div class="card-body">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 64px; height: 64px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-calendar-x fs-2"></i>
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