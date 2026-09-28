<?php
/**
 * edit_kegiatan.php - Edit Jadwal Kunjungan Sales
 * Modul Aplikasi Sales — Loewix Sales Canvas
 */
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";

$pageNow = "Dashboard Sales Canvas";
$currentPage = "Today";

date_default_timezone_set('Asia/Jakarta');

$successMsg = "";
$errorMsg = "";

// 1. Ambil ID Kegiatan
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    echo "<script>alert('ID Kegiatan tidak valid!'); window.location.href='kegiatan.php';</script>";
    exit();
}

// 2. Handler POST Simpan Perubahan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jadwal = $_POST['jadwal'] ?? '';
    $visit = $_POST['visit'] ?? '';
    $id_customer = intval($_POST['id_customer'] ?? 0);
    $status = $_POST['status'] ?? 'dijadwalkan';
    $selectedSales = $_POST['sales'] ?? [];

    // Lokasi & Geofence
    $lat = !empty($_POST['lat']) ? $_POST['lat'] : NULL;
    $lon = !empty($_POST['lon']) ? $_POST['lon'] : NULL;
    $rad = !empty($_POST['radius']) ? intval($_POST['radius']) : 100;
    $location_address = !empty($_POST['location_address']) ? trim($_POST['location_address']) : NULL;

    if ($id_customer <= 0) {
        $errorMsg = "Harap pilih Customer (Toko / Mitra) terlebih dahulu.";
    } elseif (empty($selectedSales)) {
        $errorMsg = "Harap pilih minimal 1 Sales Agent penanggung jawab.";
    } elseif (empty($jadwal)) {
        $errorMsg = "Harap tentukan tanggal dan jam kunjungan.";
    } else {
        $conn->begin_transaction();
        try {
            // Update kegiatan_sales
            $stmtUp = $conn->prepare("
                UPDATE kegiatan_sales 
                SET id_customer = ?, jadwal = ?, keterangan = ?, status = ?, lat = ?, lon = ?, rad = ?, alamat_lokasi = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmtUp->bind_param("isssssisi", $id_customer, $jadwal, $visit, $status, $lat, $lon, $rad, $location_address, $id);
            $stmtUp->execute();
            $stmtUp->close();

            // Sync team_kegiatan_sales
            $stmtDelTeam = $conn->prepare("DELETE FROM team_kegiatan_sales WHERE id_kegiatan_sales = ?");
            $stmtDelTeam->bind_param("i", $id);
            $stmtDelTeam->execute();
            $stmtDelTeam->close();

            foreach ($selectedSales as $id_sales) {
                $id_sales = intval($id_sales);
                $qSales = mysqli_query($conn, "SELECT nama FROM sales WHERE id = '$id_sales' LIMIT 1");
                $namaSales = ($rSales = mysqli_fetch_assoc($qSales)) ? $rSales['nama'] : '';

                $stmtInsTeam = $conn->prepare("
                    INSERT INTO team_kegiatan_sales (id_kegiatan_sales, id_sales, nama_sales, created_at, updated_at) 
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmtInsTeam->bind_param("iis", $id, $id_sales, $namaSales);
                $stmtInsTeam->execute();
                $stmtInsTeam->close();
            }

            $conn->commit();
            $successMsg = "Perubahan jadwal kegiatan berhasil disimpan!";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}

// 3. Ambil data kegiatan saat ini
$stmtKeg = $conn->prepare("
    SELECT ks.*, c.nama AS nama_customer, c.kode_customer, c.alamat, c.telp_pribadi, c.id_wilayah AS cust_id_wilayah,
           w.nama AS nama_wilayah, c.lat AS cust_lat, c.lon AS cust_lon, c.rad AS cust_rad, c.alamat_lokasi AS cust_alamat_lokasi
    FROM kegiatan_sales ks
    LEFT JOIN sales_customer c ON ks.id_customer = c.id
    LEFT JOIN wilayah w ON c.id_wilayah = w.id
    WHERE ks.id = ? AND ks.deleted_at IS NULL
    LIMIT 1
");
$stmtKeg->bind_param("i", $id);
$stmtKeg->execute();
$dataKeg = $stmtKeg->get_result()->fetch_assoc();
$stmtKeg->close();

if (!$dataKeg) {
    echo "<!DOCTYPE html><html><head><title>Jadwal Tidak Ditemukan</title><meta charset='utf-8'></head><body style='font-family:sans-serif;text-align:center;padding:50px;'>";
    echo "<h2>Jadwal kegiatan tidak ditemukan atau telah dihapus.</h2>";
    echo "<p><a href='kegiatan.php' style='color:#2563eb;text-decoration:none;font-weight:bold;'>&larr; Kembali ke Dashboard Jadwal</a></p>";
    echo "</body></html>";
    exit();
}

// 4. Ambil tim sales yang saat ini ditugaskan
$assignedSales = [];
$qAssigned = mysqli_query($conn, "SELECT id_sales FROM team_kegiatan_sales WHERE id_kegiatan_sales = '$id' AND deleted_at IS NULL");
while ($rAssigned = mysqli_fetch_assoc($qAssigned)) {
    $assignedSales[] = (int)$rAssigned['id_sales'];
}

// 5. Ambil data list customer
$customerResult = mysqli_query($conn, "
    SELECT c.id, c.nama, c.kode_customer, c.id_wilayah, c.lat, c.lon, c.rad, c.alamat_lokasi, w.nama AS nama_wilayah 
    FROM sales_customer c 
    LEFT JOIN wilayah w ON c.id_wilayah = w.id 
    WHERE c.deleted_at IS NULL 
    ORDER BY c.nama ASC
");

// 6. Ambil data list sales
$salesResult = mysqli_query($conn, "
    SELECT s.id, s.nama, s.id_wilayah, w.nama AS nama_wilayah 
    FROM sales s 
    LEFT JOIN wilayah w ON s.id_wilayah = w.id 
    WHERE s.deleted_at IS NULL 
    ORDER BY s.nama ASC
");

// 7. Parse tanggal & jam yang tersimpan
$savedJadwal = $dataKeg['jadwal'] ?? '';
$savedTimestamp = !empty($savedJadwal) ? strtotime($savedJadwal) : time();
$savedDate = date('Y-m-d', $savedTimestamp);
$savedHour = date('H', $savedTimestamp);
$savedMinute = date('i', $savedTimestamp);

// Koordinat awal peta
$initLat = !empty($dataKeg['lat']) ? (float)$dataKeg['lat'] : (!empty($dataKeg['cust_lat']) ? (float)$dataKeg['cust_lat'] : -6.130371);
$initLon = !empty($dataKeg['lon']) ? (float)$dataKeg['lon'] : (!empty($dataKeg['cust_lon']) ? (float)$dataKeg['cust_lon'] : 106.751442);
$initRad = !empty($dataKeg['rad']) ? intval($dataKeg['rad']) : (!empty($dataKeg['cust_rad']) ? intval($dataKeg['cust_rad']) : 100);
$initAddress = !empty($dataKeg['alamat_lokasi']) ? $dataKeg['alamat_lokasi'] : (!empty($dataKeg['cust_alamat_lokasi']) ? $dataKeg['cust_alamat_lokasi'] : '');
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Edit Jadwal Kunjungan #<?php echo $id; ?> — Loewix Sales Canvas</title>
  <?php include "head.php"; ?>
  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  
  <style>
    :root {
      --kb-primary: #059669;
      --kb-primary-hover: #047857;
      --kb-primary-light: #ecfdf5;
      --kb-primary-border: #a7f3d0;
      --kb-slate-950: #020617;
      --kb-slate-900: #0f172a;
      --kb-slate-800: #1e293b;
      --kb-slate-700: #334155;
      --kb-slate-600: #475569;
      --kb-slate-500: #64748b;
      --kb-slate-400: #94a3b8;
      --kb-slate-300: #cbd5e1;
      --kb-slate-200: #e2e8f0;
      --kb-slate-100: #f1f5f9;
      --kb-slate-50: #f8fafc;
      --kb-card-shadow: 0 2px 8px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
      --kb-radius-lg: 18px;
      --kb-radius-md: 12px;
      --kb-radius-sm: 8px;
    }

    body {
      background-color: #f1f5f9 !important;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      color: var(--kb-slate-900);
      font-size: 14.5px;
      line-height: 1.5;
    }

    /* Page Header Card */
    .kb-header-card {
      background: #ffffff;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-lg);
      padding: 22px 28px;
      box-shadow: var(--kb-card-shadow);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
    }

    .kb-header-title {
      display: flex;
      align-items: center;
      gap: 18px;
    }

    .kb-header-icon {
      width: 54px;
      height: 54px;
      border-radius: var(--kb-radius-md);
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
      flex-shrink: 0;
    }

    .kb-header-icon .material-symbols-outlined {
      font-size: 30px;
      font-variation-settings: 'FILL' 1, 'wght' 700;
    }

    .kb-header-text h1 {
      font-family: 'Outfit', sans-serif;
      font-size: 23px;
      font-weight: 800;
      color: var(--kb-slate-950);
      margin: 0;
      letter-spacing: -0.02em;
    }

    .kb-header-text p {
      font-size: 14px;
      font-weight: 500;
      color: var(--kb-slate-600);
      margin: 3px 0 0;
    }

    /* Form Container & Sections */
    .kb-form-container {
      background: #ffffff;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-lg);
      box-shadow: var(--kb-card-shadow);
      overflow: hidden;
      margin-bottom: 32px;
    }

    .kb-form-body {
      padding: 34px;
    }

    .kb-section-badge {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      font-size: 13px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #065f46;
      background: #ecfdf5;
      border: 2px solid #a7f3d0;
      padding: 8px 16px;
      border-radius: 24px;
      margin-bottom: 22px;
    }

    .kb-section-badge-purple {
      color: #5b21b6;
      background: #f5f3ff;
      border-color: #c4b5fd;
    }

    .kb-section-badge-blue {
      color: #1e40af;
      background: #eff6ff;
      border-color: #93c5fd;
    }

    .kb-section-badge .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: currentColor;
    }

    .kb-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 14.5px;
      font-weight: 700;
      color: var(--kb-slate-900);
      margin-bottom: 10px;
    }

    .kb-label-icon {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .kb-label-icon .material-symbols-outlined,
    .kb-label-icon i {
      font-size: 19px;
      color: var(--kb-primary);
    }

    .kb-input {
      width: 100%;
      height: 50px;
      background-color: #ffffff;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-md);
      padding: 10px 16px;
      font-size: 15px;
      font-weight: 600;
      color: var(--kb-slate-950);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      outline: none;
    }

    .kb-input:focus {
      border-color: var(--kb-primary);
      box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.2);
      background-color: #ffffff;
    }

    .kb-input::placeholder {
      color: var(--kb-slate-500);
      font-size: 14px;
      font-weight: 500;
    }

    textarea.kb-input {
      height: auto;
      min-height: 110px;
      line-height: 1.6;
      resize: vertical;
    }

    .kb-datetime-grid {
      display: grid;
      grid-template-columns: 1fr 115px 115px;
      gap: 12px;
    }

    .kb-select {
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='%231e293b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
      background-repeat: no-repeat;
      background-position: right 12px center;
      background-size: 16px;
      padding-right: 36px !important;
      cursor: pointer;
    }

    /* Customer Dropdown */
    .kb-dropdown-wrapper {
      position: relative;
    }

    .kb-dropdown-btn {
      width: 100%;
      height: 52px;
      background: #ffffff;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-md);
      padding: 10px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
      transition: all 0.2s ease;
      text-align: left;
    }

    .kb-dropdown-btn:hover {
      border-color: var(--kb-slate-500);
      background: var(--kb-slate-50);
    }

    .kb-dropdown-btn:focus,
    .kb-dropdown-btn.active {
      border-color: var(--kb-primary);
      box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.2);
      outline: none;
    }

    .kb-dropdown-btn-content {
      display: flex;
      align-items: center;
      gap: 12px;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      flex: 1;
      font-size: 15px;
      font-weight: 600;
      color: var(--kb-slate-900);
    }

    .kb-dropdown-panel {
      display: none;
      position: absolute;
      top: calc(100% + 8px);
      left: 0;
      right: 0;
      background: #ffffff;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-md);
      box-shadow: 0 14px 36px rgba(15, 23, 42, 0.2);
      z-index: 1050;
      max-height: 320px;
      overflow-y: auto;
    }

    .kb-dropdown-search-wrap {
      position: sticky;
      top: 0;
      background: #ffffff;
      padding: 10px 12px;
      border-bottom: 2px solid var(--kb-slate-200);
      z-index: 2;
    }

    .kb-dropdown-search {
      width: 100%;
      height: 44px;
      background: var(--kb-slate-50);
      border: 2px solid var(--kb-slate-300);
      border-radius: 8px;
      padding: 8px 14px 8px 38px;
      font-size: 14.5px;
      font-weight: 600;
      color: var(--kb-slate-950);
      outline: none;
    }

    .kb-dropdown-search-icon {
      position: absolute;
      left: 24px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--kb-slate-600);
      font-size: 18px;
      pointer-events: none;
    }

    .kb-dropdown-item {
      padding: 12px 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      border-bottom: 1px solid var(--kb-slate-200);
      transition: background 0.15s ease;
    }

    .kb-dropdown-item:last-child {
      border-bottom: none;
    }

    .kb-dropdown-item:hover {
      background-color: var(--kb-primary-light);
    }

    .kb-dropdown-item.selected {
      background-color: #d1fae5;
      font-weight: 700;
    }

    /* Sales Checkboxes */
    .kb-sales-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 12px;
      max-height: 280px;
      overflow-y: auto;
      padding-right: 6px;
    }

    .sales-checkbox-item {
      position: relative;
    }

    .kb-sales-checkbox {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .kb-sales-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      background: #ffffff;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-md);
      cursor: pointer;
      transition: all 0.2s ease;
      user-select: none;
    }

    .kb-sales-card:hover {
      border-color: var(--kb-primary);
      background: var(--kb-slate-50);
    }

    .kb-sales-checkbox:checked + .kb-sales-card {
      border-color: var(--kb-primary);
      background-color: var(--kb-primary-light);
      box-shadow: 0 4px 12px rgba(5, 150, 105, 0.15);
    }

    .kb-sales-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #e2e8f0;
      color: #334155;
      font-weight: 800;
      font-size: 13px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .kb-sales-checkbox:checked + .kb-sales-card .kb-sales-avatar {
      background: var(--kb-primary);
      color: #ffffff;
    }

    .kb-sales-info {
      flex: 1;
      min-width: 0;
    }

    .kb-sales-name {
      font-size: 14px;
      font-weight: 700;
      color: var(--kb-slate-900);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .kb-sales-check-icon {
      width: 22px;
      height: 22px;
      border-radius: 6px;
      border: 2px solid var(--kb-slate-400);
      display: flex;
      align-items: center;
      justify-content: center;
      color: transparent;
      transition: all 0.2s;
    }

    .kb-sales-checkbox:checked + .kb-sales-card .kb-sales-check-icon {
      background: var(--kb-primary);
      border-color: var(--kb-primary);
      color: #ffffff;
    }

    /* Map & Geofence */
    #map {
      height: 320px;
      border-radius: var(--kb-radius-md);
      border: 2px solid var(--kb-slate-300);
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
      z-index: 1;
    }

    .kb-map-search-wrap {
      display: flex;
      gap: 8px;
    }

    .kb-radius-presets {
      display: flex;
      gap: 6px;
      margin-top: 10px;
    }

    .kb-preset-btn {
      flex: 1;
      padding: 6px 4px;
      text-align: center;
      font-size: 12px;
      font-weight: 700;
      border: 1.5px solid var(--kb-slate-300);
      border-radius: 8px;
      background: #ffffff;
      cursor: pointer;
      transition: all 0.15s;
    }

    .kb-preset-btn:hover {
      background: var(--kb-slate-100);
      border-color: var(--kb-slate-400);
    }

    .kb-preset-btn.active {
      background: var(--kb-primary);
      border-color: var(--kb-primary);
      color: #ffffff;
    }

    .kb-coord-badge {
      background: #f8fafc;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-sm);
      padding: 8px 12px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 13px;
      color: var(--kb-slate-950);
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .kb-coord-label {
      font-size: 10.5px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 800;
      color: var(--kb-slate-500);
      text-transform: uppercase;
    }

    /* Footer Action Bar */
    .kb-form-footer {
      background: #ffffff;
      border-top: 2px solid var(--kb-slate-200);
      padding: 22px 34px;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 14px;
    }

    .kb-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 24px;
      font-size: 14.5px;
      font-weight: 700;
      border-radius: var(--kb-radius-md);
      transition: all 0.2s ease;
      cursor: pointer;
      text-decoration: none;
      border: none;
    }

    .kb-btn-secondary {
      background: #ffffff;
      color: var(--kb-slate-800);
      border: 2px solid var(--kb-slate-300);
    }

    .kb-btn-secondary:hover {
      background: var(--kb-slate-100);
      border-color: var(--kb-slate-500);
      color: var(--kb-slate-950);
    }

    .kb-btn-primary {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      color: #ffffff !important;
      box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
    }

    .kb-btn-primary:hover {
      background: linear-gradient(135deg, #047857 0%, #059669 100%);
      box-shadow: 0 6px 24px rgba(5, 150, 105, 0.45);
      transform: translateY(-2px);
    }

    /* Region badges */
    .badge-region {
      font-size: 11px;
      font-weight: 800;
      padding: 3px 8px;
      border-radius: 6px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .badge-region-jkt { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
    .badge-region-jatim { background: #ffedd5; color: #9a3412; border: 1px solid #fdba74; }
    .badge-region-jateng { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .badge-region-jabar { background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }
    .badge-region-other { background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; }

    @media (max-width: 991.98px) {
      .kb-form-body { padding: 20px 16px; }
      .kb-datetime-grid { grid-template-columns: 1fr; }
      .kb-form-footer { flex-direction: column; }
      .kb-btn { width: 100%; }
      .kb-col-right { margin-top: 32px; padding-top: 24px; border-top: 2px solid var(--kb-slate-200); }
    }
  </style>
</head>

<body class="bg-light">
  <?php include "cek-menu.php"; ?>

  <main class="main-content position-relative max-height-vh-100 h-100">
    <?php include "nav-top.php"; ?>

    <div class="container-fluid px-3 px-md-4 py-2">

      <!-- Alerts -->
      <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success d-flex align-items-center gap-3 border-0 shadow-sm mb-4" style="background:#ecfdf5; color:#065f46; border-radius:12px; padding:16px 20px;">
          <i class="bi bi-check-circle-fill fs-4 text-success"></i>
          <div class="flex-grow-1">
            <div class="fw-bold fs-6">Berhasil Diperbarui!</div>
            <div class="text-sm opacity-90"><?php echo $successMsg; ?></div>
          </div>
          <a href="kegiatan.php" class="btn btn-sm btn-success px-3 py-1.5 rounded-pill fw-semibold">Kembali ke Jadwal</a>
        </div>
      <?php endif; ?>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3 border-0 shadow-sm mb-4" style="background:#fef2f2; color:#991b1b; border-radius:12px; padding:16px 20px;">
          <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
          <div class="flex-grow-1">
            <div class="fw-bold fs-6">Gagal Menyimpan</div>
            <div class="text-sm opacity-90"><?php echo $errorMsg; ?></div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <!-- ── Header Section ── -->
      <div class="kb-header-card">
        <div class="kb-header-title">
          <div class="kb-header-icon">
            <span class="material-symbols-outlined">edit_calendar</span>
          </div>
          <div class="kb-header-text">
            <div class="d-flex align-items-center gap-2 mb-1">
              <h1>Edit Jadwal Kunjungan</h1>
              <span class="badge bg-dark font-monospace px-2.5 py-1" style="font-size: 12px; letter-spacing: 0.05em;">
                <?php echo htmlspecialchars($dataKeg['kode'] ?? ('ID #' . $id)); ?>
              </span>
            </div>
            <p>Perbarui rencana jadwal canvassing sales, penugasan agent, dan perimeter geofence GPS toko.</p>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a href="detail_kegiatan.php?id=<?php echo $id; ?>" class="kb-btn kb-btn-secondary py-2 px-3" style="font-size: 13px;">
            <i class="bi bi-eye-fill text-primary"></i>
            <span>Lihat Detail</span>
          </a>
          <a href="kegiatan.php" class="kb-btn kb-btn-secondary py-2 px-3" style="font-size: 13px;">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Jadwal</span>
          </a>
        </div>
      </div>

      <!-- ── Main Form Container ── -->
      <div class="kb-form-container">
        <form method="POST" id="formEditKegiatan">
          <input type="hidden" name="id" value="<?php echo $id; ?>">

          <div class="kb-form-body">
            <div class="row g-4">

              <!-- ════ LEFT COLUMN: Detail Jadwal & Sales Assignment ════ -->
              <div class="col-lg-7 pe-lg-4" style="border-right: 2px solid #e2e8f0;">
                
                <!-- Section 1 Header -->
                <div class="kb-section-badge">
                  <span class="dot"></span>
                  <span>01. INFORMASI &amp; WAKTU KUNJUNGAN</span>
                </div>

                <!-- Jadwal Visit Date & Time -->
                <div class="mb-4">
                  <label class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-calendar3-event-fill text-success"></i>
                      <span>Tanggal &amp; Waktu Visit</span>
                    </span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 fw-bold" style="font-size: 11px;">* Wajib Diisi</span>
                  </label>
                  <div class="kb-datetime-grid">
                    <div>
                      <input type="date" class="kb-input" id="visit_date" value="<?php echo $savedDate; ?>" required>
                    </div>
                    <div>
                      <select class="kb-input kb-select" id="visit_hour" title="Jam" required></select>
                    </div>
                    <div>
                      <select class="kb-input kb-select" id="visit_minute" title="Menit" required>
                        <option value="00" <?php echo ($savedMinute === '00') ? 'selected' : ''; ?>>00</option>
                        <option value="15" <?php echo ($savedMinute === '15') ? 'selected' : ''; ?>>15</option>
                        <option value="30" <?php echo ($savedMinute === '30') ? 'selected' : ''; ?>>30</option>
                        <option value="45" <?php echo ($savedMinute === '45') ? 'selected' : ''; ?>>45</option>
                      </select>
                    </div>
                  </div>
                  <!-- Hidden combined datetime value -->
                  <input type="hidden" id="jadwal" name="jadwal" value="<?php echo htmlspecialchars($savedJadwal); ?>" required>
                </div>

                <!-- Status Kegiatan -->
                <div class="mb-4">
                  <label class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-flag-fill text-success"></i>
                      <span>Status Kegiatan Kunjungan</span>
                    </span>
                  </label>
                  <select name="status" id="status" class="kb-input kb-select">
                    <option value="dijadwalkan" <?php echo ($dataKeg['status'] === 'dijadwalkan') ? 'selected' : ''; ?>>🗓️ Dijadwalkan (Belum Visit)</option>
                    <option value="berjalan" <?php echo ($dataKeg['status'] === 'berjalan') ? 'selected' : ''; ?>>⏳ Sedang Berjalan (Sales Check-In)</option>
                    <option value="selesai" <?php echo ($dataKeg['status'] === 'selesai') ? 'selected' : ''; ?>>✅ Selesai (Check-Out Selesai)</option>
                    <option value="waiting" <?php echo ($dataKeg['status'] === 'waiting') ? 'selected' : ''; ?>>⚠️ Menunggu Persetujuan Reschedule</option>
                    <option value="dibatalkan" <?php echo ($dataKeg['status'] === 'dibatalkan') ? 'selected' : ''; ?>>❌ Dibatalkan</option>
                  </select>
                </div>

                <!-- Keperluan / Agenda Kunjungan -->
                <div class="mb-4">
                  <label for="visit" class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-chat-left-text-fill text-success"></i>
                      <span>Keperluan &amp; Agenda Kunjungan</span>
                    </span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 fw-bold" style="font-size: 11px;">* Wajib Diisi</span>
                  </label>
                  <textarea class="kb-input" name="visit" id="visit" rows="3" placeholder="Tuliskan tujuan kunjungan..." required><?php echo htmlspecialchars($dataKeg['keterangan'] ?? ''); ?></textarea>
                </div>

                <!-- Customer Dropdown Selector -->
                <div class="mb-4">
                  <label class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-shop-window text-success"></i>
                      <span>Customer (Toko / Mitra)</span>
                    </span>
                    <span class="badge bg-success px-2.5 py-1 fw-bold" id="selectedCustomerBadge" style="font-size: 11px;">Customer Aktif</span>
                  </label>
                  
                  <div class="kb-dropdown-wrapper">
                    <button type="button" class="kb-dropdown-btn" id="dropdownCustBtn">
                      <div class="kb-dropdown-btn-content" id="dropdownCustDisplay">
                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                          <?php echo htmlspecialchars($dataKeg['kode_customer'] ?? 'CUST'); ?>
                        </span>
                        <span class="fw-bold text-dark fs-7"><?php echo htmlspecialchars($dataKeg['nama_customer'] ?? 'Pilih Customer'); ?></span>
                        <?php if (!empty($dataKeg['nama_wilayah'])): ?>
                          <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">
                            <?php echo htmlspecialchars($dataKeg['nama_wilayah']); ?>
                          </span>
                        <?php endif; ?>
                      </div>
                      <i class="bi bi-chevron-down text-dark fs-6 fw-bold"></i>
                    </button>

                    <div class="kb-dropdown-panel" id="dropdownCustMenu">
                      <div class="kb-dropdown-search-wrap">
                        <i class="bi bi-search kb-dropdown-search-icon"></i>
                        <input type="text" class="kb-dropdown-search" id="dropdownCustSearch" placeholder="Ketik nama toko, kode customer, atau kota..." autocomplete="off">
                      </div>
                      <div id="dropdownCustList">
                        <?php 
                        mysqli_data_seek($customerResult, 0);
                        while ($c = mysqli_fetch_assoc($customerResult)): 
                        ?>
                          <?php 
                            $cRegion = $c['nama_wilayah'] ?? 'Tanpa Wilayah';
                            $badgeClass = 'badge-region-other';
                            if (stripos($cRegion, 'Jabodetabek') !== false) $badgeClass = 'badge-region-jkt';
                            elseif (stripos($cRegion, 'Jawa Timur') !== false) $badgeClass = 'badge-region-jatim';
                            elseif (stripos($cRegion, 'Jawa Tengah') !== false) $badgeClass = 'badge-region-jateng';
                            elseif (stripos($cRegion, 'Jawa Barat') !== false) $badgeClass = 'badge-region-jabar';
                            $isSelected = ((int)$c['id'] === (int)$dataKeg['id_customer']);
                          ?>
                          <div class="kb-dropdown-item <?php echo $isSelected ? 'selected' : ''; ?>" 
                               data-id="<?php echo $c['id']; ?>" 
                               data-nama="<?php echo htmlspecialchars($c['nama']); ?>"
                               data-kode="<?php echo htmlspecialchars($c['kode_customer'] ?? ''); ?>"
                               data-wilayah="<?php echo htmlspecialchars($cRegion); ?>"
                               data-id-wilayah="<?php echo $c['id_wilayah']; ?>"
                               data-lat="<?php echo htmlspecialchars($c['lat'] ?? ''); ?>"
                               data-lon="<?php echo htmlspecialchars($c['lon'] ?? ''); ?>"
                               data-rad="<?php echo htmlspecialchars($c['rad'] ?? '100'); ?>"
                               data-alamat="<?php echo htmlspecialchars($c['alamat_lokasi'] ?? ''); ?>">
                            <div>
                              <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark border font-monospace fw-bold" style="font-size: 11px;">
                                  <?php echo htmlspecialchars($c['kode_customer'] ?? 'CUST'); ?>
                                </span>
                                <span class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($c['nama']); ?></span>
                              </div>
                              <?php if (!empty($c['alamat_lokasi'])): ?>
                                <div class="text-xs text-muted text-truncate mt-1" style="max-width: 320px; font-weight: 500;">
                                  <i class="bi bi-geo-alt-fill text-danger"></i> <?php echo htmlspecialchars($c['alamat_lokasi']); ?>
                                </div>
                              <?php endif; ?>
                            </div>
                            <span class="badge-region <?php echo $badgeClass; ?> flex-shrink-0">
                              <?php echo htmlspecialchars($cRegion); ?>
                            </span>
                          </div>
                        <?php endwhile; ?>
                      </div>
                    </div>
                  </div>
                  <!-- Hidden Customer ID input -->
                  <input type="hidden" id="id_customer" name="id_customer" value="<?php echo $dataKeg['id_customer']; ?>" required>
                  <div class="form-text text-xs text-muted mt-2 fw-semibold" id="customerLocationNote" style="display: none;">
                    <i class="bi bi-info-circle-fill text-success"></i> Titik koordinat peta otomatis disesuaikan dari profil toko ini.
                  </div>
                </div>

                <!-- Section 2 Header -->
                <div class="kb-section-badge kb-section-badge-purple mt-4">
                  <span class="dot"></span>
                  <span>02. PENUGASAN SALES AGENT</span>
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-sm fw-bold text-dark">Pilih 1 atau lebih sales yang bertanggung jawab:</span>
                    <span id="salesFilterNotice" class="badge bg-light text-primary border" style="font-size: 11px; display: none;"></span>
                  </div>

                  <div class="kb-sales-grid" id="salesGridContainer">
                    <?php 
                    mysqli_data_seek($salesResult, 0);
                    while ($s = mysqli_fetch_assoc($salesResult)): 
                    ?>
                      <?php
                        $regionName = $s['nama_wilayah'] ?? 'Tanpa Wilayah';
                        $badgeClass = 'badge-region-other';
                        if (stripos($regionName, 'Jabodetabek') !== false) $badgeClass = 'badge-region-jkt';
                        elseif (stripos($regionName, 'Jawa Timur') !== false) $badgeClass = 'badge-region-jatim';
                        elseif (stripos($regionName, 'Jawa Tengah') !== false) $badgeClass = 'badge-region-jateng';
                        elseif (stripos($regionName, 'Jawa Barat') !== false) $badgeClass = 'badge-region-jabar';

                        $words = explode(' ', trim($s['nama']));
                        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                        $isSalesChecked = in_array((int)$s['id'], $assignedSales);
                      ?>
                      <div class="sales-checkbox-item" data-id-wilayah="<?php echo $s['id_wilayah']; ?>" data-wilayah-name="<?php echo htmlspecialchars($regionName); ?>">
                        <input class="kb-sales-checkbox" type="checkbox" name="sales[]" value="<?php echo $s['id']; ?>" id="sales_<?php echo $s['id']; ?>" <?php echo $isSalesChecked ? 'checked' : ''; ?>>
                        <label class="kb-sales-card" for="sales_<?php echo $s['id']; ?>">
                          <div class="kb-sales-avatar">
                            <?php echo $initials; ?>
                          </div>
                          <div class="kb-sales-info">
                            <div class="kb-sales-name"><?php echo htmlspecialchars($s['nama']); ?></div>
                            <div class="kb-sales-badge">
                              <span class="badge-region <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($regionName); ?></span>
                            </div>
                          </div>
                          <div class="kb-sales-check-icon">
                            <i class="bi bi-check-lg fs-6 fw-bold"></i>
                          </div>
                        </label>
                      </div>
                    <?php endwhile; ?>
                  </div>
                </div>

              </div>

              <!-- ════ RIGHT COLUMN: Peta & Geofence GPS ════ -->
              <div class="col-lg-5 kb-col-right ps-lg-4">
                
                <!-- Section 3 Header -->
                <div class="kb-section-badge kb-section-badge-blue">
                  <span class="dot"></span>
                  <span>03. TITIK LOKASI &amp; GEOFENCE GPS</span>
                </div>

                <!-- Map Search Input -->
                <div class="mb-3">
                  <label class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-geo-alt-fill text-primary"></i>
                      <span>Cari Koordinat / Alamat Toko</span>
                    </span>
                  </label>
                  <div class="kb-map-search-wrap">
                    <input type="text" id="gmap_search" class="kb-input" placeholder="Cari nama toko atau -6.123, 106.827...">
                    <button type="button" id="gmap_search_btn" class="kb-btn kb-btn-secondary px-3.5 py-2 flex-shrink-0" style="font-size: 14px;">
                      <i class="bi bi-search"></i>
                      <span>Cari</span>
                    </button>
                  </div>
                </div>

                <!-- Leaflet Map Container -->
                <div id="map"></div>

                <!-- GPS Location & Quick Actions -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                  <button type="button" id="btn_get_location" class="btn btn-sm d-flex align-items-center gap-2 py-2 px-3.5 rounded-pill fw-bold text-white" style="font-size: 13px; background: #059669; border: none; box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);">
                    <i class="bi bi-crosshair fs-6"></i>
                    <span>Dapatkan Lokasi Saya (GPS)</span>
                  </button>
                  <div class="d-flex align-items-center gap-2">
                    <span class="text-xs fw-bold text-dark font-monospace">Radius:</span>
                    <span class="badge bg-success px-2.5 py-1.5 font-monospace fs-7 fw-bold" id="slider_val"><?php echo $initRad; ?>m</span>
                  </div>
                </div>

                <!-- Geofence Radius Slider -->
                <div class="mt-3 p-3.5 bg-white rounded-3 border" style="border: 2px solid #cbd5e1 !important;">
                  <div class="d-flex justify-content-between align-items-center mb-1.5">
                    <span class="text-sm fw-bold text-dark">Radius Geofence Check-in</span>
                    <span class="text-xs text-muted fw-semibold">Jarak toleransi absen sales</span>
                  </div>
                  <input type="range" id="radius_slider" min="10" max="1000" step="10" value="<?php echo $initRad; ?>" class="w-100" style="accent-color: var(--kb-primary);">
                  
                  <!-- Quick Preset Pills -->
                  <div class="kb-radius-presets">
                    <div class="kb-preset-btn <?php echo ($initRad === 50) ? 'active' : ''; ?>" data-val="50">50 m</div>
                    <div class="kb-preset-btn <?php echo ($initRad === 100) ? 'active' : ''; ?>" data-val="100">100 m</div>
                    <div class="kb-preset-btn <?php echo ($initRad === 200) ? 'active' : ''; ?>" data-val="200">200 m</div>
                    <div class="kb-preset-btn <?php echo ($initRad === 50) ? 'active' : ''; ?>" data-val="500">500 m</div>
                    <div class="kb-preset-btn <?php echo ($initRad === 1000) ? 'active' : ''; ?>" data-val="1000">1 km</div>
                  </div>
                </div>

                <!-- Coordinate Details -->
                <div class="row g-2 mt-2">
                  <div class="col-6">
                    <div class="kb-coord-badge">
                      <span class="kb-coord-label">Latitude</span>
                      <span id="lat_display" class="fw-bold text-dark"><?php echo number_format($initLat, 6); ?></span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="kb-coord-badge">
                      <span class="kb-coord-label">Longitude</span>
                      <span id="lon_display" class="fw-bold text-dark"><?php echo number_format($initLon, 6); ?></span>
                    </div>
                  </div>
                </div>

                <!-- Reverse Geocoded Address Preview -->
                <div class="mt-3">
                  <div class="p-3 rounded-3 text-sm text-dark d-flex align-items-start gap-2.5" style="border: 2px solid #a7f3d0; background: #ecfdf5;">
                    <i class="bi bi-geo-alt-fill text-success fs-5 mt-0.5"></i>
                    <span id="location_address_text" class="fw-bold text-dark">
                      <?php echo !empty($initAddress) ? htmlspecialchars($initAddress) : 'Mengarahkan pin peta ke titik lokasi target...'; ?>
                    </span>
                  </div>
                </div>

                <!-- Hidden inputs to submit -->
                <input type="hidden" id="lat" name="lat" value="<?php echo htmlspecialchars($dataKeg['lat'] ?? $initLat); ?>">
                <input type="hidden" id="lon" name="lon" value="<?php echo htmlspecialchars($dataKeg['lon'] ?? $initLon); ?>">
                <input type="hidden" id="radius" name="radius" value="<?php echo $initRad; ?>">
                <input type="hidden" id="location_address" name="location_address" value="<?php echo htmlspecialchars($initAddress); ?>">

              </div>

            </div>
          </div>

          <!-- ── Bottom Action Footer ── -->
          <div class="kb-form-footer">
            <a href="kegiatan.php" class="kb-btn kb-btn-secondary">
              <i class="bi bi-x-lg"></i>
              <span>Batal</span>
            </a>
            <button type="submit" class="kb-btn kb-btn-primary" id="btnSubmitForm">
              <i class="bi bi-check-circle-fill"></i>
              <span>Simpan Perubahan</span>
            </button>
          </div>
        </form>
      </div>

      <?php
      include "floating-menu.php";
      include "footer.php";
      ?>
    </div>

  </main>
  
  <?php include "js-include.php"; ?>
  
  <!-- Leaflet Map JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // ─── 1. Populate Hour Select ───
      const hourSelect = document.getElementById('visit_hour');
      const savedHour = "<?php echo $savedHour; ?>";
      for (let i = 0; i < 24; i++) {
        let h = i.toString().padStart(2, '0');
        let opt = new Option(h + ':00', h);
        if (h === savedHour) opt.selected = true;
        hourSelect.add(opt);
      }

      // ─── 2. Combine Date, Hour, and Minute ───
      function combineDateTime() {
        const d = document.getElementById('visit_date').value;
        const h = document.getElementById('visit_hour').value;
        const m = document.getElementById('visit_minute').value;
        document.getElementById('jadwal').value = d ? `${d} ${h}:${m}:00` : '';
      }

      ['visit_date', 'visit_hour', 'visit_minute'].forEach(id => {
        document.getElementById(id).addEventListener('change', combineDateTime);
      });

      // ─── 3. Searchable Customer Dropdown ───
      const custBtn = document.getElementById('dropdownCustBtn');
      const custMenu = document.getElementById('dropdownCustMenu');
      const custSearch = document.getElementById('dropdownCustSearch');
      const custDisplay = document.getElementById('dropdownCustDisplay');
      const idCustInput = document.getElementById('id_customer');
      const custLocNote = document.getElementById('customerLocationNote');
      const custBadge = document.getElementById('selectedCustomerBadge');
      const salesFilterNotice = document.getElementById('salesFilterNotice');

      custBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = custMenu.style.display === 'block';
        custMenu.style.display = isOpen ? 'none' : 'block';
        custBtn.classList.toggle('active', !isOpen);
        if (!isOpen) {
          custSearch.focus();
        }
      });

      custSearch.addEventListener('input', function() {
        const query = custSearch.value.toLowerCase().trim();
        document.querySelectorAll('.kb-dropdown-item').forEach(item => {
          const text = (item.dataset.nama + ' ' + item.dataset.kode + ' ' + item.dataset.wilayah + ' ' + item.dataset.alamat).toLowerCase();
          item.style.display = text.includes(query) ? 'flex' : 'none';
        });
      });

      document.addEventListener('click', function(e) {
        if (!custBtn.contains(e.target) && !custMenu.contains(e.target)) {
          custMenu.style.display = 'none';
          custBtn.classList.remove('active');
        }
      });

      // Customer item selection
      document.querySelectorAll('.kb-dropdown-item').forEach(item => {
        item.addEventListener('click', function() {
          const id = this.dataset.id;
          const nama = this.dataset.nama;
          const kode = this.dataset.kode;
          const wilayah = this.dataset.wilayah;
          const lat = parseFloat(this.dataset.lat);
          const lon = parseFloat(this.dataset.lon);
          const rad = parseInt(this.dataset.rad) || 100;
          const alamat = this.dataset.alamat;

          idCustInput.value = id;
          custDisplay.innerHTML = `
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">${kode || 'CUST'}</span>
              <span class="fw-bold text-dark fs-7">${nama}</span>
              <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">${wilayah}</span>
            </div>
          `;

          custMenu.style.display = 'none';
          custBtn.classList.remove('active');
          custBadge.style.display = 'inline-block';

          document.querySelectorAll('.kb-dropdown-item').forEach(el => el.classList.remove('selected'));
          this.classList.add('selected');

          // Update Map position if customer has GPS coordinate
          if (!isNaN(lat) && !isNaN(lon) && lat !== 0 && lon !== 0) {
            custLocNote.style.display = 'block';
            updateAllDataNoReverse(L.latLng(lat, lon), rad, alamat);
          } else {
            custLocNote.style.display = 'none';
          }
        });
      });

      // ─── 4. Leaflet Map & Geofence Logic ───
      const initialLat = <?php echo json_encode($initLat); ?>;
      const initialLon = <?php echo json_encode($initLon); ?>;
      const initialRad = <?php echo json_encode($initRad); ?>;

      const map = L.map('map', {
        zoomControl: true,
        scrollWheelZoom: true
      }).setView([initialLat, initialLon], 15);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
      }).addTo(map);

      let marker = L.marker([initialLat, initialLon], { draggable: true }).addTo(map);
      let circle = L.circle([initialLat, initialLon], {
        radius: initialRad,
        color: '#059669',
        fillColor: '#10b981',
        fillOpacity: 0.15,
        weight: 2,
        dashArray: '6, 6'
      }).addTo(map);

      const radSlider = document.getElementById('radius_slider');
      const sliderVal = document.getElementById('slider_val');
      const latDisplay = document.getElementById('lat_display');
      const lonDisplay = document.getElementById('lon_display');
      const addrText = document.getElementById('location_address_text');

      function syncRadius(value) {
        const r = parseInt(value) || 100;
        radSlider.value = r;
        sliderVal.innerText = r >= 1000 ? (r / 1000) + ' km' : r + 'm';
        circle.setRadius(r);
        document.getElementById('radius').value = r;

        document.querySelectorAll('.kb-preset-btn').forEach(btn => {
          btn.classList.toggle('active', parseInt(btn.dataset.val) === r);
        });
      }

      radSlider.addEventListener('input', function() {
        syncRadius(this.value);
      });

      document.querySelectorAll('.kb-preset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          syncRadius(this.dataset.val);
        });
      });

      function updateAllData(latlng, rad) {
        const r = parseInt(rad) || 100;
        marker.setLatLng(latlng);
        circle.setLatLng(latlng).setRadius(r);
        map.setView(latlng, 16);

        document.getElementById('lat').value = latlng.lat;
        document.getElementById('lon').value = latlng.lng;
        latDisplay.innerText = latlng.lat.toFixed(6);
        lonDisplay.innerText = latlng.lng.toFixed(6);
        syncRadius(r);

        addrText.innerText = 'Mengambil alamat lokasi...';

        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&accept-language=id`)
          .then(res => res.json())
          .then(data => {
            const displayAddr = data?.display_name || 'Alamat tidak ditemukan pada titik ini';
            document.getElementById('location_address').value = displayAddr;
            addrText.innerText = displayAddr;
          })
          .catch(() => {
            addrText.innerText = 'Titik koordinat berhasil dikunci';
          });
      }

      function updateAllDataNoReverse(latlng, rad, address) {
        const r = parseInt(rad) || 100;
        marker.setLatLng(latlng);
        circle.setLatLng(latlng).setRadius(r);
        map.setView(latlng, 16);

        document.getElementById('lat').value = latlng.lat;
        document.getElementById('lon').value = latlng.lng;
        latDisplay.innerText = latlng.lat.toFixed(6);
        lonDisplay.innerText = latlng.lng.toFixed(6);
        syncRadius(r);

        const safeAddr = address || 'Alamat telah disetel dari database toko';
        document.getElementById('location_address').value = safeAddr;
        addrText.innerText = safeAddr;
      }

      map.on('click', function(e) {
        updateAllData(e.latlng, radSlider.value);
      });

      marker.on('dragend', function() {
        updateAllData(marker.getLatLng(), radSlider.value);
      });

      // GPS Location Button
      document.getElementById('btn_get_location').addEventListener('click', function() {
        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mencari...';

        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(
            function(pos) {
              const lat = pos.coords.latitude;
              const lon = pos.coords.longitude;
              updateAllData(L.latLng(lat, lon), radSlider.value);
              btn.disabled = false;
              btn.innerHTML = originalHtml;
            },
            function(err) {
              alert("Gagal mendapatkan lokasi GPS: " + err.message);
              btn.disabled = false;
              btn.innerHTML = originalHtml;
            },
            { enableHighAccuracy: true, timeout: 8000 }
          );
        } else {
          alert("Browser tidak mendukung Geolocation.");
          btn.disabled = false;
          btn.innerHTML = originalHtml;
        }
      });

      // Search address or coordinate
      document.getElementById('gmap_search_btn').addEventListener('click', function() {
        const query = document.getElementById('gmap_search').value.trim();
        if (!query) return;

        const coordsRegex = /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/;
        if (coordsRegex.test(query)) {
          const parts = query.split(',');
          updateAllData(L.latLng(parseFloat(parts[0]), parseFloat(parts[1])), radSlider.value);
        } else {
          fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id&accept-language=id`)
            .then(res => res.json())
            .then(data => {
              if (data && data.length > 0) {
                updateAllData(L.latLng(parseFloat(data[0].lat), parseFloat(data[0].lon)), radSlider.value);
              } else {
                alert("Alamat atau lokasi tidak ditemukan.");
              }
            })
            .catch(() => {
              alert("Terjadi kesalahan saat mencari alamat.");
            });
        }
      });

      document.getElementById('gmap_search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          document.getElementById('gmap_search_btn').click();
        }
      });

      // ─── 5. Client-Side Validation on Submit ───
      document.getElementById('formEditKegiatan').addEventListener('submit', function(e) {
        const idCust = idCustInput.value;
        const salesChecked = document.querySelectorAll('input[name="sales[]"]:checked').length;

        if (!idCust) {
          e.preventDefault();
          alert("Harap pilih Customer (Toko / Mitra) terlebih dahulu!");
          custBtn.focus();
          return false;
        }

        if (salesChecked === 0) {
          e.preventDefault();
          alert("Harap pilih minimal 1 Sales Agent yang bertugas!");
          return false;
        }

        const submitBtn = document.getElementById('btnSubmitForm');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan Perubahan...';
      });
    });
  </script>

</body>
</html>
