<?php
include "conn.php";
include "session.php";
include "get-user-data.php";
$pageNow = "Kegiatan Baru";
$currentPage = "Today";

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <?php include "head.php"; ?>
  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    /* ── Premium Form Card Styling ── */
    .card-premium {
      background: #fff;
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
      margin-bottom: 24px;
    }
    
    .card-body-premium {
      padding: 40px;
    }

    /* ── Form Inputs ── */
    .form-group-premium {
      margin-bottom: 24px;
    }
    
    .form-label-premium {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 11px;
      font-weight: 700;
      color: #64748b;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    
    .input-premium {
      width: 100%;
      height: 48px !important;
      border: 1.5px solid #e2e8f0;
      border-radius: 12px;
      padding: 10px 16px !important;
      font-size: 14px;
      color: #1e293b;
      background-color: #fff;
      transition: all 0.2s ease-in-out;
      box-sizing: border-box;
    }
    
    .input-premium:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
      outline: none;
      background-color: #fff;
    }

    /* ── Custom Styled Select arrow to match inputs ── */
    select.input-premium {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
      background-repeat: no-repeat;
      background-position: right 14px center;
      background-size: 16px;
      padding-right: 40px !important;
      cursor: pointer;
    }

    textarea.input-premium {
      height: auto !important;
      min-height: 120px;
      line-height: 1.6;
      resize: vertical;
    }

    /* ── Date/Time Group alignment ── */
    .datetime-row {
      display: flex;
      gap: 12px;
    }
    .datetime-col-date {
      flex: 2;
    }
    .datetime-col-time {
      flex: 1;
    }

    /* ── Custom Dropdown styling matching technician flow ── */
    .dropdown-container {
      position: relative;
    }
    
    .dropdown-button-cust {
      width: 100%;
      height: 48px;
      padding: 10px 16px;
      border: 1.5px solid #e2e8f0;
      border-radius: 12px;
      background: #fff;
      text-align: left;
      font-size: 14px;
      color: #1e293b;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-sizing: border-box;
    }
    
    .dropdown-button-cust:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
      outline: none;
    }
    
    .dropdown-menu-cust {
      display: none;
      position: absolute;
      background: white;
      border: 1.5px solid #cbd5e1;
      width: 100%;
      z-index: 1000;
      max-height: 250px;
      overflow-y: auto;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      margin-top: 6px;
    }
    
    .dropdown-search-cust {
      width: 100%;
      padding: 12px 16px;
      border: none;
      border-bottom: 1.5px solid #e2e8f0;
      position: sticky;
      top: 0;
      outline: none;
      font-size: 14px;
      background: #f8fafc;
    }
    
    .dropdown-item-cust {
      padding: 12px 16px;
      cursor: pointer;
      font-size: 14px;
      color: #334155;
      border-bottom: 1px solid #f1f5f9;
      transition: background-color 0.15s ease;
    }
    
    .dropdown-item-cust:hover {
      background-color: #f0f7ff;
      color: #1d4ed8;
    }

    /* ── Map Container ── */
    #map {
      height: 310px;
      width: 100%;
      border-radius: 14px;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 4px 12px rgba(0,0,0,0.02);
      margin-top: 10px;
    }

    /* ── Sales Select Card Grid ── */
    .sales-select-card {
      display: block;
      cursor: pointer;
      margin-bottom: 0;
      user-select: none;
    }
    
    .sales-card-content {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 18px;
      background: #fff;
      border: 1.5px solid #e2e8f0;
      border-radius: 14px;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
    }
    
    .sales-card-content:hover {
      border-color: #cbd5e1;
      background: #f8fafc;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }
    
    .sales-select-input:checked + .sales-card-content {
      background: #f0f7ff;
      border-color: #3b82f6;
      box-shadow: 0 6px 16px rgba(59, 130, 246, 0.08);
    }
    
    .avatar-initials-small {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
      color: #4338ca;
      font-size: 13px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      transition: all 0.25s ease;
      flex-shrink: 0;
    }
    
    .sales-select-input:checked + .sales-card-content .avatar-initials-small {
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      color: #fff;
    }
    
    .sales-card-details {
      display: flex;
      flex-direction: column;
    }
    
    .sales-card-name {
      font-size: 13.5px; font-weight: 700;
      color: #1e293b;
      line-height: 1.2;
    }
    
    .sales-card-role {
      font-size: 10.5px; color: #64748b;
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .sales-card-checkbox {
      margin-left: auto;
      color: #cbd5e1;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
    }
    
    .sales-card-checkbox span {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      font-size: 22px;
    }
    
    .sales-select-input:checked + .sales-card-content .sales-card-checkbox {
      color: #3b82f6;
    }
    
    .sales-select-input:checked + .sales-card-content .sales-card-checkbox span {
      font-variation-settings: 'FILL' 1, 'wght' 700, 'GRAD' 0, 'opsz' 24;
    }

    /* ── Submit Button ── */
    .btn-submit-premium {
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%);
      color: #fff !important;
      border: none;
      border-radius: 12px;
      padding: 14px 32px;
      font-size: 14px; font-weight: 700;
      display: inline-flex; align-items: center; gap: 8px;
      box-shadow: 0 4px 20px rgba(37, 99, 235, 0.25);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
    }
    
    .btn-submit-premium:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(37, 99, 235, 0.4);
    }
    
    .btn-submit-premium:active {
      transform: translateY(0);
    }
    
    .btn-submit-premium .material-symbols-outlined {
      font-size: 20px;
    }

    input[type="checkbox"] {
      -webkit-appearance: checkbox;
      -moz-appearance: checkbox;
      appearance: checkbox;
    }
    
    <?php include "css/floating-menu2.css";?>
  
    /* ── Mobile Responsive Enhancements ── */
    @media (max-width: 991.98px) {
      .card-body-premium {
        padding: 24px 16px !important;
      }
      .col-lg-5[style*="padding-left: 32px;"] {
        padding-left: calc(var(--bs-gutter-x) * .5) !important;
        margin-top: 24px;
      }
      .datetime-row {
        flex-wrap: wrap !important;
        gap: 8px !important;
      }
      .datetime-col-date {
        flex: 1 1 100% !important;
      }
      .datetime-col-time {
        flex: 1 1 calc(50% - 4px) !important;
      }
      .btn-submit-premium {
        width: 100% !important;
        justify-content: center !important;
        padding: 14px 20px !important;
        font-size: 15px !important;
        min-height: 48px !important;
        touch-action: manipulation !important;
      }
      .sales-select-card {
        margin-bottom: 8px;
        touch-action: manipulation !important;
      }
      .sales-card-content {
        padding: 12px 14px;
        min-height: 52px;
      }
      #map {
        height: 260px !important;
      }
      .dropdown-button-cust {
        height: 48px !important;
        touch-action: manipulation !important;
      }
      .dropdown-menu-cust {
        max-height: 280px !important;
        -webkit-overflow-scrolling: touch !important;
      }
      .dropdown-item-cust {
        padding: 14px 16px !important;
        touch-action: manipulation !important;
      }
    }

  </style>
</head>

<body class="g-sidenav-show bg-gray-200">
  <?php include "cek-menu.php"; ?>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
    <?php
    include "nav-top.php";
    $todayDate = formatTanggal('dd MMMM yyyy');
    ?>

    <div class="container-fluid py-4">

      <div class="card-premium">
        
        <!-- Premium Gradient Header -->
        <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 40%,#2563eb 100%);padding:28px 36px;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-40px;right:-20px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
            <div style="position:absolute;bottom:-50px;right:100px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,0.03);"></div>
            <div style="position:absolute;top:10px;right:30px;width:60px;height:60px;border-radius:50%;background:rgba(59,130,246,0.2);"></div>
            <div style="display:flex;align-items:center;gap:14px;position:relative;z-index:1;">
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.12);backdrop-filter:blur(12px);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.1);">
                    <span class="material-symbols-outlined" style="color:#fff;font-size:22px;">add_task</span>
                </div>
                <div>
                    <h5 style="color:#fff;margin:0;font-size:18px;font-weight:700;letter-spacing:-0.3px;">Form Tambah Kegiatan Sales Baru</h5>
                    <p style="color:rgba(255,255,255,0.6);margin:0;font-size:12px;margin-top:2px;">Buat, jadwalkan, dan tentukan lokasi geofence kunjungan sales</p>
                </div>
            </div>
        </div>

        <div class="card-body-premium">
          <?php
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

          // Set timezone ke Jakarta
          date_default_timezone_set('Asia/Jakarta');

          if ($_SERVER['REQUEST_METHOD'] === 'POST') {
              $jadwal = $_POST['jadwal'];
              $visit = $_POST['visit'];
              $id_customer = $_POST['id_customer'];
              
              // Kolom Lokasi
              $lat = !empty($_POST['lat']) ? $_POST['lat'] : NULL;
              $lon = !empty($_POST['lon']) ? $_POST['lon'] : NULL;
              $rad = !empty($_POST['radius']) ? $_POST['radius'] : NULL;
              $location_address = !empty($_POST['location_address']) ? $_POST['location_address'] : NULL;

              $status = 'dijadwalkan';
              $selectedSales = $_POST['sales'] ?? [];

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
              $stmt->execute();
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

              echo "<div class='alert alert-success text-white font-weight-bold mb-4' style='background: #10b981; border: none; border-radius: 10px; padding: 14px 20px;'>
                      <span class='material-symbols-outlined' style='vertical-align: middle; margin-right: 8px;'>check_circle</span>
                      Kegiatan kunjungan sales berhasil ditambahkan!
                    </div>";
          }
          ?>

          <form method="POST">
            <div class="row">
              
              <!-- LEFT COLUMN: Form Fields -->
              <div class="col-lg-7" style="border-right: 1px solid #f1f5f9; padding-right: 32px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <div style="width:3px;height:16px;background:#3b82f6;border-radius:2px;"></div>
                    <span style="font-size:12px;font-weight:800;color:#1e293b;text-transform: uppercase; letter-spacing: 0.05em;">Informasi Kegiatan</span>
                </div>

                <div class="form-group-premium">
                  <label class="form-label-premium">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">event</span> Jadwal Visit
                  </label>
                  <div class="datetime-row">
                    <div class="datetime-col-date">
                      <input type="date" class="input-premium" id="visit_date" required>
                    </div>
                    <div class="datetime-col-time">
                      <select class="input-premium" id="visit_hour" required></select>
                    </div>
                    <div class="datetime-col-time">
                      <select class="input-premium" id="visit_minute" required>
                        <option value="00">00</option>
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="45">45</option>
                      </select>
                    </div>
                  </div>
                  <!-- Hidden field to submit combined datetime -->
                  <input type="hidden" id="jadwal" name="jadwal">
                </div>

                <div class="form-group-premium">
                  <label for="visit" class="form-label-premium">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">notes</span> Keperluan Kunjungan
                  </label>
                  <textarea class="input-premium" name="visit" rows="4" placeholder="Tuliskan keterangan detail rencana kunjungan sales..." required></textarea>
                </div>

                <!-- Custom Searchable Dropdown for Customers -->
                <div class="form-group-premium">
                  <label class="form-label-premium">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">person</span> Pilih Customer (Toko/Mitra)
                  </label>
                  <div class="dropdown-container">
                    <button type="button" class="dropdown-button-cust" id="dropdownCustBtn">
                      <span>-- Pilih Customer --</span>
                      <span class="material-symbols-outlined" style="font-size:18px; color:#64748b;">expand_more</span>
                    </button>
                    <div class="dropdown-menu-cust" id="dropdownCustMenu">
                      <input type="text" class="dropdown-search-cust" id="dropdownCustSearch" placeholder="Cari nama atau wilayah customer...">
                      <?php while ($c = mysqli_fetch_assoc($customerResult)): ?>
                        <?php $cRegion = $c['nama_wilayah'] ?? 'Tanpa Wilayah'; ?>
                        <div class="dropdown-item-cust" 
                             data-id="<?php echo $c['id']; ?>" 
                             data-id-wilayah="<?php echo $c['id_wilayah']; ?>"
                             data-lat="<?php echo htmlspecialchars($c['lat'] ?? ''); ?>"
                             data-lon="<?php echo htmlspecialchars($c['lon'] ?? ''); ?>"
                             data-rad="<?php echo htmlspecialchars($c['rad'] ?? ''); ?>"
                             data-alamat="<?php echo htmlspecialchars($c['alamat_lokasi'] ?? ''); ?>">
                          <?php 
                            $kodeLabel = !empty($c['kode_customer']) ? $c['kode_customer'] . ' | ' : '';
                            echo htmlspecialchars($kodeLabel . $c['nama'] . ' - [' . $cRegion . ']'); 
                          ?>
                        </div>
                      <?php endwhile; ?>
                    </div>
                  </div>
                  <input type="hidden" id="id_customer" name="id_customer" required>
                </div>

                <div class="form-group-premium">
                  <label class="form-label-premium">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">group</span> Pilih Sales Agent Terlibat
                  </label>
                  <div class="row g-3" id="sales-list-container">
                    <?php while ($s = mysqli_fetch_assoc($salesResult)): ?>
                      <?php
                        $regionName = $s['nama_wilayah'] ?? 'Tanpa Wilayah';
                        if (stripos($regionName, 'Jabodetabek') !== false) {
                            $badgeStyle = 'background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: #fff;';
                        } elseif (stripos($regionName, 'Jawa Timur') !== false) {
                            $badgeStyle = 'background: linear-gradient(135deg, #7c2d12, #ea580c); color: #fff;';
                        } elseif (stripos($regionName, 'Jawa Tengah') !== false) {
                            $badgeStyle = 'background: linear-gradient(135deg, #065f46, #10b981); color: #fff;';
                        } elseif (stripos($regionName, 'Jawa Barat') !== false) {
                            $badgeStyle = 'background: linear-gradient(135deg, #5b21b6, #8b5cf6); color: #fff;';
                        } else {
                            $badgeStyle = 'background: linear-gradient(135deg, #374151, #4b5563); color: #fff;';
                        }
                      ?>
                      <div class="col-md-6 sales-checkbox-item" data-id-wilayah="<?php echo $s['id_wilayah']; ?>">
                        <label class="sales-select-card" for="sales<?php echo $s['id']; ?>">
                          <input class="sales-select-input d-none" type="checkbox" name="sales[]" value="<?php echo $s['id']; ?>" id="sales<?php echo $s['id']; ?>">
                          <div class="sales-card-content">
                            <div class="avatar-initials-small">
                              <?php 
                                $words = explode(' ', $s['nama']);
                                echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                              ?>
                            </div>
                            <div class="sales-card-details">
                              <span class="sales-card-name"><?php echo htmlspecialchars($s['nama']); ?></span>
                              <span class="sales-card-role">
                                Sales Agent 
                                <span class="badge" style="font-size: 8px; padding: 3px 6px; letter-spacing: 0.05em; font-weight: 700; text-transform: uppercase; <?= $badgeStyle; ?>">
                                  <?php echo htmlspecialchars($regionName); ?>
                                </span>
                              </span>
                            </div>
                            <div class="sales-card-checkbox">
                              <span class="material-symbols-outlined">check_circle</span>
                            </div>
                          </div>
                        </label>
                      </div>
                    <?php endwhile; ?>
                  </div>
                </div>
              </div>

              <!-- RIGHT COLUMN: Map Selector -->
              <div class="col-lg-5" style="padding-left: 32px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <div style="width:3px;height:16px;background:#10b981;border-radius:2px;"></div>
                    <span style="font-size:12px;font-weight:800;color:#1e293b;text-transform: uppercase; letter-spacing: 0.05em;">Lokasi &amp; Peta</span>
                    <span style="font-size:11px;color:#94a3b8;font-weight:400;margin-left:4px;">(Opsional)</span>
                </div>

                <div class="form-group-premium">
                  <label class="form-label-premium">
                    <i class="fa-solid fa-magnifying-glass text-xs me-1 text-primary"></i> Cari Koordinat / Alamat
                  </label>
                  <div class="d-flex gap-2">
                    <input type="text" id="gmap_search" class="input-premium" placeholder="Contoh: Jawa Timur atau -6.175, 106.827...">
                    <button type="button" id="gmap_search_btn" class="btn bg-gradient-info text-white font-weight-bold" style="border-radius:10px; padding: 12px 18px; font-size:11px; display:inline-flex; align-items:center; gap:4px; margin-bottom:0;">
                      <i class="fa-solid fa-magnifying-glass text-xs"></i> CARI
                    </button>
                  </div>
                </div>

                <!-- Leaflet Map widget -->
                <div id="map"></div>

                <!-- GPS button and Radius input -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                  <button type="button" id="btn_get_location" class="btn btn-outline-primary btn-sm mb-0 d-flex align-items-center gap-1 font-weight-bold" style="border-radius: 8px; font-size: 11px;">
                    <i class="fa-solid fa-location-crosshairs text-xs"></i> Dapatkan Lokasi Saya
                  </button>
                  <div class="d-flex align-items-center gap-2">
                    <span class="text-xs text-secondary font-weight-bold">Radius:</span>
                    <input type="number" id="radius_input" class="input-premium" value="100" style="font-size:12px; padding: 6px 8px !important; text-align:center; width: 60px; border-radius: 8px; margin-bottom: 0;">
                    <span class="text-xs text-secondary font-weight-bold">Meter</span>
                  </div>
                </div>

                <!-- Radius Slider -->
                <div class="mt-3">
                  <label class="form-label-premium d-flex justify-content-between mb-1" style="font-size: 10px; color:#64748b;">
                    <span>Sesuaikan Geofence Radius (Meter)</span>
                    <span id="slider_val" class="text-primary font-weight-bold">100m</span>
                  </label>
                  <input type="range" id="radius_slider" min="10" max="1000" step="10" value="100" class="form-range w-100" style="accent-color: #3b82f6;">
                </div>

                <!-- Latitude and Longitude readouts -->
                <div class="row g-2 mt-2">
                  <div class="col-6">
                    <label class="form-label-premium" style="font-size: 9px; color:#64748b;">Latitude</label>
                    <input type="text" id="lat_display" class="input-premium" placeholder="-6.xxxxx" style="font-family: monospace; font-size:12px; padding: 8px 12px !important; background:#f8fafc; border-radius: 8px;" readonly>
                  </div>
                  <div class="col-6">
                    <label class="form-label-premium" style="font-size: 9px; color:#64748b;">Longitude</label>
                    <input type="text" id="lon_display" class="input-premium" placeholder="106.xxxxx" style="font-family: monospace; font-size:12px; padding: 8px 12px !important; background:#f8fafc; border-radius: 8px;" readonly>
                  </div>
                </div>

                <!-- Hidden parameters to submit -->
                <input type="hidden" id="lat" name="lat">
                <input type="hidden" id="lon" name="lon">
                <input type="hidden" id="radius" name="radius" value="100">
                <input type="hidden" id="location_address" name="location_address">
              </div>

            </div>

            <div class="mt-5">
              <button type="submit" class="btn-submit-premium" style="width: 100%; justify-content: center;">
                <span class="material-symbols-outlined">save</span>
                Simpan Rencana Kegiatan &amp; Lokasi
              </button>
            </div>
          </form>
        </div>

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

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- Auto-Filter & Map Script -->
  <script>
    // ── Auto-Filter Sales based on Customer Region ──
    function filterSales(customerWilayah) {
      document.querySelectorAll('.sales-checkbox-item').forEach(item => {
        const salesWilayah = item.getAttribute('data-id-wilayah');
        if (!customerWilayah || customerWilayah === "" || salesWilayah === customerWilayah) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
          const checkbox = item.querySelector('.sales-select-input');
          if (checkbox) checkbox.checked = false;
        }
      });
    }

    // ── Custom Dropdown Event Listeners ──
    document.addEventListener('DOMContentLoaded', function() {
      const custBtn = document.getElementById('dropdownCustBtn');
      const custMenu = document.getElementById('dropdownCustMenu');
      const custSearch = document.getElementById('dropdownCustSearch');
      const idCustInput = document.getElementById('id_customer');

      custBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        custMenu.style.display = custMenu.style.display === 'block' ? 'none' : 'block';
        if (custMenu.style.display === 'block') {
          custSearch.focus();
        }
      });

      custSearch.addEventListener('keyup', function() {
        const filter = custSearch.value.toUpperCase();
        document.querySelectorAll('.dropdown-item-cust').forEach(item => {
          const txtValue = item.textContent || item.innerText;
          item.style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
        });
      });

      document.querySelectorAll('.dropdown-item-cust').forEach(item => {
        item.addEventListener('click', function() {
          custBtn.querySelector('span:first-child').innerText = this.innerText;
          idCustInput.value = this.dataset.id;
          custMenu.style.display = 'none';

          // Run the filter
          const customerWilayah = this.dataset.idWilayah;
          filterSales(customerWilayah);

          // Autofill location from customer profile if set
          const cLat = parseFloat(this.dataset.lat);
          const cLon = parseFloat(this.dataset.lon);
          const cRad = parseInt(this.dataset.rad) || 100;
          const cAlamat = this.dataset.alamat;

          if (!isNaN(cLat) && !isNaN(cLon)) {
            updateAllDataNoReverse(L.latLng(cLat, cLon), cRad, cAlamat);
          }
        });
      });

      // Close dropdown when clicking outside
      window.addEventListener('click', function() {
        custMenu.style.display = 'none';
      });

      custMenu.addEventListener('click', function(e) {
        e.stopPropagation();
      });

      // ── Populate Hour Select ──
      const hourSelect = document.getElementById('visit_hour');
      for (let i = 0; i < 24; i++) {
        let h = i.toString().padStart(2, '0');
        hourSelect.add(new Option(h, h));
      }

      // ── Set default date/time values ──
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

      // Initial combination
      combineDateTime();

      // Listen to changes
      ['visit_date', 'visit_hour', 'visit_minute'].forEach(id => {
        document.getElementById(id).addEventListener('change', combineDateTime);
      });

      const defaultLat = -6.13037113;
      const defaultLon = 106.75144230;
      const defaultRad = 100;

      // Init Map
      const map = L.map('map').setView([defaultLat, defaultLon], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);

      let marker = L.marker([defaultLat, defaultLon], { draggable: true }).addTo(map);
      let circle = L.circle([defaultLat, defaultLon], {
        radius: defaultRad,
        color: '#2563eb', // Blue border
        fillColor: '#3b82f6', // Light blue fill
        fillOpacity: 0.12,
        weight: 1.5,
        dashArray: '5, 5' // Dashed line
      }).addTo(map);

      const radInput = document.getElementById('radius_input');
      const radSlider = document.getElementById('radius_slider');
      const sliderVal = document.getElementById('slider_val');

      function syncRadius(value) {
        const r = parseInt(value) || defaultRad;
        radInput.value = r;
        radSlider.value = r;
        sliderVal.innerText = r + 'm';
        circle.setRadius(r);
        document.getElementById('radius').value = r;
      }

      radInput.addEventListener('input', function() {
        syncRadius(this.value);
      });

      radSlider.addEventListener('input', function() {
        syncRadius(this.value);
      });

      function updateAllData(latlng, rad) {
        const r = parseInt(rad) || defaultRad;
        marker.setLatLng(latlng);
        circle.setLatLng(latlng).setRadius(r);
        map.setView(latlng, 16);

        // Populate Form Fields
        document.getElementById('lat').value = latlng.lat;
        document.getElementById('lon').value = latlng.lng;
        document.getElementById('lat_display').value = latlng.lat.toFixed(6);
        document.getElementById('lon_display').value = latlng.lng.toFixed(6);
        syncRadius(r);

        // Reverse Geocoding via Nominatim API
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&accept-language=id`)
          .then(res => res.json())
          .then(data => {
            document.getElementById('location_address').value = data?.display_name || '';
          })
          .catch(() => {
            document.getElementById('location_address').value = '';
          });
      }

      // Instant Update helper to prevent redundant network reverse geocodes
      function updateAllDataNoReverse(latlng, rad, address) {
        const r = parseInt(rad) || defaultRad;
        marker.setLatLng(latlng);
        circle.setLatLng(latlng).setRadius(r);
        map.setView(latlng, 16);

        document.getElementById('lat').value = latlng.lat;
        document.getElementById('lon').value = latlng.lng;
        document.getElementById('lat_display').value = latlng.lat.toFixed(6);
        document.getElementById('lon_display').value = latlng.lng.toFixed(6);
        syncRadius(r);
        document.getElementById('location_address').value = address || '';
      }

      // Map click event
      map.on('click', function(e) {
        updateAllData(e.latlng, radInput.value);
      });

      // Marker drag event
      marker.on('dragend', function() {
        updateAllData(marker.getLatLng(), radInput.value);
      });

      // Dapatkan Lokasi Saya button event
      document.getElementById('btn_get_location').addEventListener('click', function() {
        const btn = this;
        const origContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mencari Lokasi...';

        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(
            function(position) {
              const lat = position.coords.latitude;
              const lon = position.coords.longitude;
              updateAllData(L.latLng(lat, lon), radInput.value);
              btn.disabled = false;
              btn.innerHTML = origContent;
            },
            function(error) {
              alert("Gagal mendapatkan lokasi: " + error.message);
              btn.disabled = false;
              btn.innerHTML = origContent;
            },
            { enableHighAccuracy: true, timeout: 5000 }
          );
        } else {
          alert("Browser Anda tidak mendukung pencarian lokasi (Geolocation).");
          btn.disabled = false;
          btn.innerHTML = origContent;
        }
      });

      // Address / Coordinates Search
      document.getElementById('gmap_search_btn').addEventListener('click', function() {
        const query = document.getElementById('gmap_search').value.trim();
        if (query === "") return;

        // Check if query is latitude, longitude
        const coordsRegex = /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/;
        if (coordsRegex.test(query)) {
          const parts = query.split(',');
          const lat = parseFloat(parts[0]);
          const lon = parseFloat(parts[1]);
          updateAllData(L.latLng(lat, lon), radInput.value);
        } else {
          // Geocode Search Nominatim
          fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id&accept-language=id`)
            .then(res => res.json())
            .then(data => {
              if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lon = parseFloat(data[0].lon);
                updateAllData(L.latLng(lat, lon), radInput.value);
              } else {
                alert("Alamat atau koordinat tidak ditemukan.");
              }
            });
        }
      });

      // Init inputs with default values
      updateAllData(L.latLng(defaultLat, defaultLon), defaultRad);
    });
  </script>

</body>

</html>