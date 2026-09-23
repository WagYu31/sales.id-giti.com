<?php
include "conn.php";
include "session.php";
include "get-user-data.php";
$pageNow = "Tambah Kegiatan Baru";
$currentPage = "Today";

// Set timezone ke Jakarta
date_default_timezone_set('Asia/Jakarta');

$successMsg = "";
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jadwal = $_POST['jadwal'] ?? '';
    $visit = $_POST['visit'] ?? '';
    $id_customer = $_POST['id_customer'] ?? '';
    
    // Kolom Lokasi
    $lat = !empty($_POST['lat']) ? $_POST['lat'] : NULL;
    $lon = !empty($_POST['lon']) ? $_POST['lon'] : NULL;
    $rad = !empty($_POST['radius']) ? intval($_POST['radius']) : 100;
    $location_address = !empty($_POST['location_address']) ? trim($_POST['location_address']) : NULL;

    $status = 'dijadwalkan';
    $selectedSales = $_POST['sales'] ?? [];

    if (empty($id_customer)) {
        $errorMsg = "Harap pilih Customer (Toko/Mitra) terlebih dahulu.";
    } elseif (empty($selectedSales)) {
        $errorMsg = "Harap pilih minimal 1 Sales Agent penanggung jawab.";
    } elseif (empty($jadwal)) {
        $errorMsg = "Harap tentukan tanggal dan jam kunjungan.";
    } else {
        // Auto-generate kode kunjungan: CUST-XXX/KJG-YYY
        $custKodeQuery = $conn->prepare("SELECT kode_customer FROM sales_customer WHERE id = ? LIMIT 1");
        $custKodeQuery->bind_param("i", $id_customer);
        $custKodeQuery->execute();
        $custKodeResult = $custKodeQuery->get_result()->fetch_assoc();
        $kode_customer = $custKodeResult['kode_customer'] ?? 'CUST-000';
        $custKodeQuery->close();

        // Hitung jumlah kunjungan sebelumnya untuk customer ini
        $countQuery = $conn->prepare("SELECT COUNT(*) AS total FROM kegiatan_sales WHERE id_customer = ? AND deleted_at IS NULL");
        $countQuery->bind_param("i", $id_customer);
        $countQuery->execute();
        $countResult = $countQuery->get_result()->fetch_assoc();
        $kjgNum = ($countResult['total'] ?? 0) + 1;
        $countQuery->close();

        $kode_kegiatan = $kode_customer . '/KJG-' . str_pad($kjgNum, 3, '0', STR_PAD_LEFT);

        // Insert ke kegiatan_sales
        $stmt = $conn->prepare("
            INSERT INTO kegiatan_sales (kode, jadwal, keterangan, id_customer, status, lat, lon, rad, alamat_lokasi, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("ssssissss", $kode_kegiatan, $jadwal, $visit, $id_customer, $status, $lat, $lon, $rad, $location_address);
        
        if ($stmt->execute()) {
            $kegiatanId = $stmt->insert_id;
            $stmt->close();

            // Insert ke team_kegiatan_sales
            foreach ($selectedSales as $id_sales) {
                $getNama = mysqli_query($conn, "SELECT nama FROM sales WHERE id = '$id_sales' LIMIT 1");
                $namaSales = mysqli_fetch_assoc($getNama)['nama'] ?? '';

                $stmtTeam = $conn->prepare("
                    INSERT INTO team_kegiatan_sales (id_kegiatan_sales, id_sales, nama_sales, created_at, updated_at) 
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmtTeam->bind_param("iis", $kegiatanId, $id_sales, $namaSales);
                $stmtTeam->execute();
                $stmtTeam->close();
            }

            $successMsg = "Kegiatan kunjungan sales berhasil dijadwalkan dengan kode: " . htmlspecialchars($kode_kegiatan);
        } else {
            $errorMsg = "Gagal menyimpan jadwal kegiatan: " . $conn->error;
        }
    }
}

// Ambil customer beserta nama wilayahnya dan lokasi GPS
$customerResult = mysqli_query($conn, "
    SELECT c.id, c.nama, c.kode_customer, c.id_wilayah, c.lat, c.lon, c.rad, c.alamat_lokasi, w.nama AS nama_wilayah 
    FROM sales_customer c 
    LEFT JOIN wilayah w ON c.id_wilayah = w.id 
    WHERE c.deleted_at IS NULL 
    ORDER BY c.nama ASC
");

// Ambil sales beserta nama wilayahnya
$salesResult = mysqli_query($conn, "
    SELECT s.id, s.nama, s.id_wilayah, w.nama AS nama_wilayah 
    FROM sales s 
    LEFT JOIN wilayah w ON s.id_wilayah = w.id 
    WHERE s.deleted_at IS NULL 
    ORDER BY s.nama ASC
");
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Tambah Kegiatan Baru — Loewix Sales Canvas</title>
  <?php include "head.php"; ?>
  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  
  <style>
    /* ─── Taste-Skill Design Architecture ─── */
    :root {
      --kb-primary: #2563eb;
      --kb-primary-hover: #1d4ed8;
      --kb-primary-light: #eff6ff;
      --kb-primary-border: #bfdbfe;
      --kb-slate-900: #0f172a;
      --kb-slate-800: #1e293b;
      --kb-slate-700: #334155;
      --kb-slate-600: #475569;
      --kb-slate-500: #64748b;
      --kb-slate-400: #94a3b8;
      --kb-slate-200: #e2e8f0;
      --kb-slate-100: #f1f5f9;
      --kb-slate-50: #f8fafc;
      --kb-card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 8px 24px -4px rgba(15, 23, 42, 0.04);
      --kb-card-hover: 0 4px 20px -2px rgba(15, 23, 42, 0.08);
      --kb-radius-lg: 16px;
      --kb-radius-md: 12px;
      --kb-radius-sm: 8px;
    }

    body {
      background-color: #f8fafc !important;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      color: var(--kb-slate-800);
    }

    /* ─── Page Header Card ─── */
    .kb-header-card {
      background: #ffffff;
      border: 1px solid var(--kb-slate-200);
      border-radius: var(--kb-radius-lg);
      padding: 20px 24px;
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
      gap: 16px;
    }

    .kb-header-icon {
      width: 48px;
      height: 48px;
      border-radius: var(--kb-radius-md);
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      color: var(--kb-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--kb-primary-border);
      flex-shrink: 0;
    }

    .kb-header-icon .material-symbols-outlined {
      font-size: 26px;
      font-variation-settings: 'FILL' 1, 'wght' 600;
    }

    .kb-header-text h1 {
      font-family: 'Outfit', sans-serif;
      font-size: 20px;
      font-weight: 700;
      color: var(--kb-slate-900);
      margin: 0;
      letter-spacing: -0.02em;
    }

    .kb-header-text p {
      font-size: 13px;
      color: var(--kb-slate-500);
      margin: 2px 0 0;
    }

    /* ─── Form Container & Sections ─── */
    .kb-form-container {
      background: #ffffff;
      border: 1px solid var(--kb-slate-200);
      border-radius: var(--kb-radius-lg);
      box-shadow: var(--kb-card-shadow);
      overflow: hidden;
      margin-bottom: 32px;
    }

    .kb-form-body {
      padding: 32px;
    }

    .kb-section-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--kb-slate-700);
      background: var(--kb-slate-100);
      border: 1px solid var(--kb-slate-200);
      padding: 6px 12px;
      border-radius: 20px;
      margin-bottom: 18px;
    }

    .kb-section-badge .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--kb-primary);
    }

    /* ─── Modern Form Controls ─── */
    .kb-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12.5px;
      font-weight: 600;
      color: var(--kb-slate-700);
      margin-bottom: 8px;
    }

    .kb-label-icon {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .kb-label-icon .material-symbols-outlined,
    .kb-label-icon i {
      font-size: 16px;
      color: var(--kb-primary);
    }

    .kb-input-group {
      position: relative;
    }

    .kb-input {
      width: 100%;
      height: 46px;
      background-color: #ffffff;
      border: 1.5px solid var(--kb-slate-200);
      border-radius: var(--kb-radius-md);
      padding: 10px 14px;
      font-size: 13.5px;
      color: var(--kb-slate-800);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      outline: none;
    }

    .kb-input:focus {
      border-color: var(--kb-primary);
      box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.12);
      background-color: #ffffff;
    }

    .kb-input::placeholder {
      color: var(--kb-slate-400);
      font-size: 13px;
    }

    textarea.kb-input {
      height: auto;
      min-height: 105px;
      line-height: 1.6;
      resize: vertical;
    }

    /* ─── Datetime Row ─── */
    .kb-datetime-grid {
      display: grid;
      grid-template-columns: 1fr 100px 100px;
      gap: 10px;
    }

    .kb-select {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
      background-repeat: no-repeat;
      background-position: right 10px center;
      background-size: 14px;
      padding-right: 32px !important;
      cursor: pointer;
    }

    /* ─── Searchable Customer Dropdown ─── */
    .kb-dropdown-wrapper {
      position: relative;
    }

    .kb-dropdown-btn {
      width: 100%;
      height: 48px;
      background: #ffffff;
      border: 1.5px solid var(--kb-slate-200);
      border-radius: var(--kb-radius-md);
      padding: 10px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
      transition: all 0.2s ease;
      text-align: left;
    }

    .kb-dropdown-btn:hover {
      border-color: var(--kb-slate-400);
      background: var(--kb-slate-50);
    }

    .kb-dropdown-btn:focus,
    .kb-dropdown-btn.active {
      border-color: var(--kb-primary);
      box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.12);
      outline: none;
    }

    .kb-dropdown-btn-content {
      display: flex;
      align-items: center;
      gap: 10px;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      flex: 1;
    }

    .kb-dropdown-panel {
      display: none;
      position: absolute;
      top: calc(100% + 6px);
      left: 0;
      right: 0;
      background: #ffffff;
      border: 1px solid var(--kb-slate-200);
      border-radius: var(--kb-radius-md);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
      z-index: 1050;
      max-height: 280px;
      overflow-y: auto;
    }

    .kb-dropdown-search-wrap {
      position: sticky;
      top: 0;
      background: #ffffff;
      padding: 8px 10px;
      border-bottom: 1px solid var(--kb-slate-200);
      z-index: 2;
    }

    .kb-dropdown-search {
      width: 100%;
      height: 38px;
      background: var(--kb-slate-50);
      border: 1px solid var(--kb-slate-200);
      border-radius: 8px;
      padding: 6px 12px 6px 34px;
      font-size: 13px;
      outline: none;
    }

    .kb-dropdown-search:focus {
      border-color: var(--kb-primary);
      background: #ffffff;
    }

    .kb-dropdown-search-icon {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--kb-slate-400);
      font-size: 15px;
      pointer-events: none;
    }

    .kb-dropdown-item {
      padding: 10px 14px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      border-bottom: 1px solid var(--kb-slate-100);
      transition: background 0.15s ease;
    }

    .kb-dropdown-item:last-child {
      border-bottom: none;
    }

    .kb-dropdown-item:hover {
      background-color: var(--kb-primary-light);
    }

    .kb-dropdown-item.selected {
      background-color: var(--kb-primary-light);
      font-weight: 600;
    }

    /* ─── Sales Agent Cards ─── */
    .kb-sales-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }

    .kb-sales-card {
      position: relative;
      background: #ffffff;
      border: 1.5px solid var(--kb-slate-200);
      border-radius: var(--kb-radius-md);
      padding: 12px 14px;
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      user-select: none;
    }

    .kb-sales-card:hover {
      border-color: var(--kb-slate-400);
      background: var(--kb-slate-50);
      transform: translateY(-1px);
    }

    .kb-sales-checkbox {
      display: none;
    }

    .kb-sales-checkbox:checked + .kb-sales-card {
      border-color: var(--kb-primary);
      background: var(--kb-primary-light);
      box-shadow: 0 0 0 1px var(--kb-primary);
    }

    .kb-sales-avatar {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
      color: var(--kb-slate-700);
      font-size: 13px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: all 0.2s ease;
    }

    .kb-sales-checkbox:checked + .kb-sales-card .kb-sales-avatar {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      color: #ffffff;
    }

    .kb-sales-info {
      flex: 1;
      min-width: 0;
    }

    .kb-sales-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--kb-slate-900);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.3;
    }

    .kb-sales-badge {
      font-size: 10px;
      font-weight: 600;
      color: var(--kb-slate-500);
      margin-top: 2px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .kb-sales-check-icon {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 1.5px solid var(--kb-slate-300);
      display: flex;
      align-items: center;
      justify-content: center;
      color: transparent;
      transition: all 0.2s ease;
      flex-shrink: 0;
    }

    .kb-sales-checkbox:checked + .kb-sales-card .kb-sales-check-icon {
      background: var(--kb-primary);
      border-color: var(--kb-primary);
      color: #ffffff;
    }

    /* ─── Map & Geofence Elements ─── */
    #map {
      height: 290px;
      width: 100%;
      border-radius: var(--kb-radius-md);
      border: 1.5px solid var(--kb-slate-200);
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.04);
      z-index: 1;
    }

    .kb-map-search-wrap {
      display: flex;
      gap: 8px;
      margin-bottom: 12px;
    }

    .kb-radius-presets {
      display: flex;
      gap: 6px;
      margin-top: 8px;
    }

    .kb-preset-btn {
      flex: 1;
      padding: 5px 8px;
      font-size: 11px;
      font-weight: 600;
      background: var(--kb-slate-100);
      border: 1px solid var(--kb-slate-200);
      border-radius: 6px;
      color: var(--kb-slate-600);
      cursor: pointer;
      transition: all 0.15s ease;
      text-align: center;
    }

    .kb-preset-btn:hover,
    .kb-preset-btn.active {
      background: var(--kb-primary-light);
      border-color: var(--kb-primary-border);
      color: var(--kb-primary);
    }

    .kb-coord-badge {
      background: var(--kb-slate-50);
      border: 1px solid var(--kb-slate-200);
      border-radius: var(--kb-radius-sm);
      padding: 8px 12px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11.5px;
      color: var(--kb-slate-700);
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .kb-coord-label {
      font-size: 9.5px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 700;
      color: var(--kb-slate-400);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    /* ─── Footer Action Bar ─── */
    .kb-form-footer {
      background: #ffffff;
      border-top: 1px solid var(--kb-slate-200);
      padding: 20px 32px;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
    }

    .kb-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 24px;
      font-size: 13.5px;
      font-weight: 600;
      border-radius: var(--kb-radius-md);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      text-decoration: none;
      border: none;
    }

    .kb-btn-secondary {
      background: var(--kb-slate-100);
      color: var(--kb-slate-700);
      border: 1px solid var(--kb-slate-200);
    }

    .kb-btn-secondary:hover {
      background: var(--kb-slate-200);
      color: var(--kb-slate-900);
    }

    .kb-btn-primary {
      background: var(--kb-primary);
      color: #ffffff !important;
      box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
    }

    .kb-btn-primary:hover {
      background: var(--kb-primary-hover);
      box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
      transform: translateY(-1px);
    }

    .kb-btn-primary:active {
      transform: translateY(0);
    }

    /* ─── Region Badges ─── */
    .badge-region {
      font-size: 10px;
      font-weight: 700;
      padding: 3px 7px;
      border-radius: 6px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .badge-region-jkt { background: #dbeafe; color: #1e40af; }
    .badge-region-jatim { background: #ffedd5; color: #9a3412; }
    .badge-region-jateng { background: #dcfce7; color: #166534; }
    .badge-region-jabar { background: #f3e8ff; color: #6b21a8; }
    .badge-region-other { background: #f1f5f9; color: #475569; }

    /* Custom range styling */
    input[type=range].kb-range {
      accent-color: var(--kb-primary);
    }

    /* ─── Responsive Media Queries ─── */
    @media (max-width: 991.98px) {
      .kb-form-body {
        padding: 20px 16px;
      }
      .kb-sales-grid {
        grid-template-columns: 1fr;
      }
      .kb-datetime-grid {
        grid-template-columns: 1fr;
      }
      .kb-form-footer {
        padding: 16px;
        flex-direction: column;
      }
      .kb-btn {
        width: 100%;
      }
      .kb-col-right {
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--kb-slate-200);
      }
    }
  </style>
</head>

<body class="bg-light">
  <?php include "cek-menu.php"; ?>

  <main class="main-content position-relative max-height-vh-100 h-100">
    <?php
    include "nav-top.php";
    ?>

    <div class="container-fluid px-3 px-md-4 py-2">

      <!-- Notification Alerts -->
      <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success d-flex align-items-center gap-3 border-0 shadow-sm mb-4" style="background:#ecfdf5; color:#065f46; border-radius:12px; padding:16px 20px;">
          <i class="bi bi-check-circle-fill fs-4 text-success"></i>
          <div class="flex-grow-1">
            <div class="fw-bold fs-6">Berhasil Dijadwalkan!</div>
            <div class="text-sm opacity-90"><?php echo $successMsg; ?></div>
          </div>
          <a href="kegiatan.php" class="btn btn-sm btn-success px-3 py-1.5 rounded-pill fw-semibold">Lihat Jadwal</a>
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
            <span class="material-symbols-outlined">add_task</span>
          </div>
          <div class="kb-header-text">
            <h1>Tambah Jadwal Kunjungan Baru</h1>
            <p>Buat jadwal canvassing sales, tugaskan agent, dan tentukan perimeter geofence GPS toko.</p>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a href="kegiatan.php" class="kb-btn kb-btn-secondary py-2 px-3" style="font-size: 12.5px;">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Jadwal</span>
          </a>
        </div>
      </div>

      <!-- ── Main Form Container ── -->
      <div class="kb-form-container">
        <form method="POST" id="formTambahKegiatan">
          <div class="kb-form-body">
            <div class="row g-4">

              <!-- ════ LEFT COLUMN: Detail Jadwal & Sales Assignment ════ -->
              <div class="col-lg-7 pe-lg-4" style="border-right: 1px solid #f1f5f9;">
                
                <!-- Section 1 Header -->
                <div class="kb-section-badge">
                  <span class="dot"></span>
                  <span>01. Informasi &amp; Jadwal Kunjungan</span>
                </div>

                <!-- Jadwal Visit Date & Time -->
                <div class="mb-4">
                  <label class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-calendar3"></i>
                      <span>Tanggal &amp; Waktu Visit</span>
                    </span>
                    <span class="text-xs text-muted">Wajib diisi</span>
                  </label>
                  <div class="kb-datetime-grid">
                    <div>
                      <input type="date" class="kb-input" id="visit_date" required>
                    </div>
                    <div>
                      <select class="kb-input kb-select" id="visit_hour" title="Jam" required></select>
                    </div>
                    <div>
                      <select class="kb-input kb-select" id="visit_minute" title="Menit" required>
                        <option value="00">00</option>
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="45">45</option>
                      </select>
                    </div>
                  </div>
                  <!-- Hidden combined datetime value -->
                  <input type="hidden" id="jadwal" name="jadwal" required>
                </div>

                <!-- Keperluan / Agenda Kunjungan -->
                <div class="mb-4">
                  <label for="visit" class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-chat-left-text"></i>
                      <span>Keperluan &amp; Agenda Kunjungan</span>
                    </span>
                    <span class="text-xs text-muted">Wajib diisi</span>
                  </label>
                  <textarea class="kb-input" name="visit" id="visit" rows="3" placeholder="Tuliskan tujuan kunjungan (contoh: Follow-up penawaran CCTV Loewix, audit display toko, atau demo produk baru)..." required></textarea>
                </div>

                <!-- Customer Dropdown Selector -->
                <div class="mb-4">
                  <label class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-shop"></i>
                      <span>Pilih Customer (Toko / Mitra)</span>
                    </span>
                    <span class="text-xs text-primary fw-bold" id="selectedCustomerBadge" style="display:none;">Customer Terpilih</span>
                  </label>
                  
                  <div class="kb-dropdown-wrapper">
                    <button type="button" class="kb-dropdown-btn" id="dropdownCustBtn">
                      <div class="kb-dropdown-btn-content" id="dropdownCustDisplay">
                        <i class="bi bi-search text-muted"></i>
                        <span class="text-muted">-- Cari &amp; Pilih Customer Toko --</span>
                      </div>
                      <i class="bi bi-chevron-down text-muted fs-6"></i>
                    </button>

                    <div class="kb-dropdown-panel" id="dropdownCustMenu">
                      <div class="kb-dropdown-search-wrap">
                        <i class="bi bi-search kb-dropdown-search-icon"></i>
                        <input type="text" class="kb-dropdown-search" id="dropdownCustSearch" placeholder="Ketik nama toko, kode, atau kota..." autocomplete="off">
                      </div>
                      <div id="dropdownCustList">
                        <?php while ($c = mysqli_fetch_assoc($customerResult)): ?>
                          <?php 
                            $cRegion = $c['nama_wilayah'] ?? 'Tanpa Wilayah';
                            $badgeClass = 'badge-region-other';
                            if (stripos($cRegion, 'Jabodetabek') !== false) $badgeClass = 'badge-region-jkt';
                            elseif (stripos($cRegion, 'Jawa Timur') !== false) $badgeClass = 'badge-region-jatim';
                            elseif (stripos($cRegion, 'Jawa Tengah') !== false) $badgeClass = 'badge-region-jateng';
                            elseif (stripos($cRegion, 'Jawa Barat') !== false) $badgeClass = 'badge-region-jabar';
                          ?>
                          <div class="kb-dropdown-item" 
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
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 10px;">
                                  <?php echo htmlspecialchars($c['kode_customer'] ?? 'CUST'); ?>
                                </span>
                                <span class="fw-semibold text-dark fs-7"><?php echo htmlspecialchars($c['nama']); ?></span>
                              </div>
                              <?php if (!empty($c['alamat_lokasi'])): ?>
                                <div class="text-xs text-muted text-truncate mt-0.5" style="max-width: 320px;">
                                  <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($c['alamat_lokasi']); ?>
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
                  <input type="hidden" id="id_customer" name="id_customer" required>
                  <div class="form-text text-xs text-muted mt-1.5" id="customerLocationNote" style="display: none;">
                    <i class="bi bi-info-circle text-primary"></i> Titik koordinat peta otomatis disesuaikan dari profil toko ini.
                  </div>
                </div>

                <!-- Section 2 Header -->
                <div class="kb-section-badge mt-4">
                  <span class="dot"></span>
                  <span>02. Penugasan Sales Agent</span>
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-xs text-muted">Pilih 1 atau lebih sales yang bertanggung jawab dalam kunjungan ini:</span>
                    <span id="salesFilterNotice" class="badge bg-light text-primary border" style="font-size: 10px; display: none;"></span>
                  </div>

                  <div class="kb-sales-grid" id="salesGridContainer">
                    <?php while ($s = mysqli_fetch_assoc($salesResult)): ?>
                      <?php
                        $regionName = $s['nama_wilayah'] ?? 'Tanpa Wilayah';
                        $badgeClass = 'badge-region-other';
                        if (stripos($regionName, 'Jabodetabek') !== false) $badgeClass = 'badge-region-jkt';
                        elseif (stripos($regionName, 'Jawa Timur') !== false) $badgeClass = 'badge-region-jatim';
                        elseif (stripos($regionName, 'Jawa Tengah') !== false) $badgeClass = 'badge-region-jateng';
                        elseif (stripos($regionName, 'Jawa Barat') !== false) $badgeClass = 'badge-region-jabar';

                        $words = explode(' ', trim($s['nama']));
                        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                      ?>
                      <div class="sales-checkbox-item" data-id-wilayah="<?php echo $s['id_wilayah']; ?>" data-wilayah-name="<?php echo htmlspecialchars($regionName); ?>">
                        <input class="kb-sales-checkbox" type="checkbox" name="sales[]" value="<?php echo $s['id']; ?>" id="sales_<?php echo $s['id']; ?>">
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
                            <i class="bi bi-check fs-6"></i>
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
                <div class="kb-section-badge">
                  <span class="dot" style="background: #10b981;"></span>
                  <span>03. Titik Lokasi &amp; Geofence GPS</span>
                </div>

                <!-- Map Search Input -->
                <div class="mb-3">
                  <label class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-pin-map text-success"></i>
                      <span>Cari Koordinat / Alamat Toko</span>
                    </span>
                  </label>
                  <div class="kb-map-search-wrap">
                    <input type="text" id="gmap_search" class="kb-input" placeholder="Cari nama lokasi atau -6.123, 106.827...">
                    <button type="button" id="gmap_search_btn" class="kb-btn kb-btn-secondary px-3 py-2 flex-shrink-0" style="font-size: 12.5px;">
                      <i class="bi bi-search"></i>
                      <span>Cari</span>
                    </button>
                  </div>
                </div>

                <!-- Leaflet Map Container -->
                <div id="map"></div>

                <!-- GPS Location & Quick Actions -->
                <div class="d-flex justify-content-between align-items-center mt-2.5">
                  <button type="button" id="btn_get_location" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5 py-1.5 px-3 rounded-pill fw-semibold" style="font-size: 11.5px;">
                    <i class="bi bi-crosshair fs-6"></i>
                    <span>Dapatkan Lokasi Saya (GPS)</span>
                  </button>
                  <div class="d-flex align-items-center gap-1.5">
                    <span class="text-xs text-muted font-monospace">Radius:</span>
                    <span class="badge bg-primary px-2 py-1 font-monospace" id="slider_val">100m</span>
                  </div>
                </div>

                <!-- Geofence Radius Slider -->
                <div class="mt-3 p-3 bg-light rounded-3 border">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-xs fw-bold text-dark">Radius Geofence Check-in</span>
                    <span class="text-xs text-muted">Maksimal jarak toleransi absen</span>
                  </div>
                  <input type="range" id="radius_slider" min="10" max="1000" step="10" value="100" class="kb-range w-100">
                  
                  <!-- Quick Preset Pills -->
                  <div class="kb-radius-presets">
                    <div class="kb-preset-btn" data-val="50">50 m</div>
                    <div class="kb-preset-btn active" data-val="100">100 m</div>
                    <div class="kb-preset-btn" data-val="200">200 m</div>
                    <div class="kb-preset-btn" data-val="500">500 m</div>
                    <div class="kb-preset-btn" data-val="1000">1 km</div>
                  </div>
                </div>

                <!-- Coordinate Details -->
                <div class="row g-2 mt-2">
                  <div class="col-6">
                    <div class="kb-coord-badge">
                      <span class="kb-coord-label">Latitude</span>
                      <span id="lat_display" class="fw-bold text-dark">-6.130371</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="kb-coord-badge">
                      <span class="kb-coord-label">Longitude</span>
                      <span id="lon_display" class="fw-bold text-dark">106.751442</span>
                    </div>
                  </div>
                </div>

                <!-- Reverse Geocoded Address Preview -->
                <div class="mt-2.5">
                  <div class="p-2.5 bg-light rounded-2 border text-xs text-muted d-flex align-items-start gap-2">
                    <i class="bi bi-geo-alt-fill text-primary mt-0.5"></i>
                    <span id="location_address_text" class="text-truncate-2">Mengarahkan pin peta ke titik lokasi target...</span>
                  </div>
                </div>

                <!-- Hidden inputs to submit -->
                <input type="hidden" id="lat" name="lat">
                <input type="hidden" id="lon" name="lon">
                <input type="hidden" id="radius" name="radius" value="100">
                <input type="hidden" id="location_address" name="location_address">

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
              <i class="bi bi-calendar-check-fill"></i>
              <span>Simpan Rencana Kegiatan</span>
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

  <!-- Auto-Filter, Map & Interaction Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // ─── 1. Populate Hour Select ───
      const hourSelect = document.getElementById('visit_hour');
      for (let i = 0; i < 24; i++) {
        let h = i.toString().padStart(2, '0');
        hourSelect.add(new Option(h + ':00', h));
      }

      // ─── 2. Set default date/time values ───
      const today = new Date();
      const dd = String(today.getDate()).padStart(2, '0');
      const mm = String(today.getMonth() + 1).padStart(2, '0');
      const yyyy = today.getFullYear();
      document.getElementById('visit_date').value = `${yyyy}-${mm}-${dd}`;
      document.getElementById('visit_hour').value = "09";
      document.getElementById('visit_minute').value = "00";

      // Combine Date, Hour, and Minute
      function combineDateTime() {
        const d = document.getElementById('visit_date').value;
        const h = document.getElementById('visit_hour').value;
        const m = document.getElementById('visit_minute').value;
        document.getElementById('jadwal').value = d ? `${d} ${h}:${m}:00` : '';
      }
      combineDateTime();

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

      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        if (!custBtn.contains(e.target) && !custMenu.contains(e.target)) {
          custMenu.style.display = 'none';
          custBtn.classList.remove('active');
        }
      });

      // ─── 4. Auto-Filter Sales by Region ───
      function filterSalesByRegion(wilayahId, wilayahName) {
        let matchCount = 0;
        document.querySelectorAll('.sales-checkbox-item').forEach(item => {
          const sWilayah = item.getAttribute('data-id-wilayah');
          if (!wilayahId || wilayahId === "" || sWilayah === wilayahId) {
            item.style.display = 'block';
            matchCount++;
          } else {
            item.style.display = 'none';
            const cb = item.querySelector('.kb-sales-checkbox');
            if (cb) cb.checked = false;
          }
        });

        if (wilayahName && matchCount > 0) {
          salesFilterNotice.textContent = 'Wilayah: ' + wilayahName;
          salesFilterNotice.style.display = 'inline-block';
        } else {
          salesFilterNotice.style.display = 'none';
        }
      }

      // Customer item selection
      document.querySelectorAll('.kb-dropdown-item').forEach(item => {
        item.addEventListener('click', function() {
          const id = this.dataset.id;
          const nama = this.dataset.nama;
          const kode = this.dataset.kode;
          const wilayah = this.dataset.wilayah;
          const idWilayah = this.dataset.idWilayah;
          const lat = parseFloat(this.dataset.lat);
          const lon = parseFloat(this.dataset.lon);
          const rad = parseInt(this.dataset.rad) || 100;
          const alamat = this.dataset.alamat;

          idCustInput.value = id;
          custDisplay.innerHTML = `
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">${kode || 'CUST'}</span>
              <span class="fw-bold text-dark fs-7">${nama}</span>
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 10px;">${wilayah}</span>
            </div>
          `;

          custMenu.style.display = 'none';
          custBtn.classList.remove('active');
          custBadge.style.display = 'inline-block';

          // Mark active in dropdown
          document.querySelectorAll('.kb-dropdown-item').forEach(el => el.classList.remove('selected'));
          this.classList.add('selected');

          // Filter Sales Agent list
          filterSalesByRegion(idWilayah, wilayah);

          // Update Map position if customer has GPS coordinate
          if (!isNaN(lat) && !isNaN(lon) && lat !== 0 && lon !== 0) {
            custLocNote.style.display = 'block';
            updateAllDataNoReverse(L.latLng(lat, lon), rad, alamat);
          } else {
            custLocNote.style.display = 'none';
          }
        });
      });

      // ─── 5. Leaflet Map & Geofence Logic ───
      const defaultLat = -6.13037113;
      const defaultLon = 106.75144230;
      const defaultRad = 100;

      const map = L.map('map', {
        zoomControl: true,
        scrollWheelZoom: true
      }).setView([defaultLat, defaultLon], 14);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
      }).addTo(map);

      let marker = L.marker([defaultLat, defaultLon], { draggable: true }).addTo(map);
      let circle = L.circle([defaultLat, defaultLon], {
        radius: defaultRad,
        color: '#2563eb',
        fillColor: '#3b82f6',
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
        const r = parseInt(value) || defaultRad;
        radSlider.value = r;
        sliderVal.innerText = r >= 1000 ? (r / 1000) + ' km' : r + 'm';
        circle.setRadius(r);
        document.getElementById('radius').value = r;

        // Sync preset buttons
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
        const r = parseInt(rad) || defaultRad;
        marker.setLatLng(latlng);
        circle.setLatLng(latlng).setRadius(r);
        map.setView(latlng, 16);

        document.getElementById('lat').value = latlng.lat;
        document.getElementById('lon').value = latlng.lng;
        latDisplay.innerText = latlng.lat.toFixed(6);
        lonDisplay.innerText = latlng.lng.toFixed(6);
        syncRadius(r);

        addrText.innerText = 'Mengambil alamat lokasi...';

        // Reverse Geocoding
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
        const r = parseInt(rad) || defaultRad;
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

      // Enter key on search input
      document.getElementById('gmap_search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          document.getElementById('gmap_search_btn').click();
        }
      });

      // Initialize default coordinates
      updateAllData(L.latLng(defaultLat, defaultLon), defaultRad);

      // ─── 6. Client-Side Validation on Submit ───
      document.getElementById('formTambahKegiatan').addEventListener('submit', function(e) {
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
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan Jadwal...';
      });
    });
  </script>

</body>

</html>