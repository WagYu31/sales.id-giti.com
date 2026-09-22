<?php
include "conn.php";

// Auto migration: Add Sales App clock-in/out columns to pelaksanaan_sales if not exist
$salesMigrations = [
    "ci_at"        => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `ci_at` DATETIME NULL DEFAULT NULL COMMENT 'Waktu Clock In Sales'",
    "co_at"        => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `co_at` DATETIME NULL DEFAULT NULL COMMENT 'Waktu Clock Out Sales'",
    "lat_ci"       => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `lat_ci` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Latitude saat Clock In'",
    "lon_ci"       => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `lon_ci` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Longitude saat Clock In'",
    "lat_co"       => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `lat_co` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Latitude saat Clock Out'",
    "lon_co"       => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `lon_co` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Longitude saat Clock Out'",
    "catatan_visit"=> "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `catatan_visit` TEXT NULL DEFAULT NULL COMMENT 'Catatan hasil kunjungan'",
    "nama_client"  => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `nama_client` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nama Client / Kontak Kunjungan'",
    "nomer_client" => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `nomer_client` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Nomor Telepon Client / Kontak'",
    "tipe_prospek" => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `tipe_prospek` VARCHAR(30) NULL DEFAULT 'Biasa' COMMENT 'Kategori prospek customer'",
    "no_invoice"   => "ALTER TABLE `pelaksanaan_sales` ADD COLUMN `no_invoice` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nomor invoice opsional jika ada transaksi'",
];
foreach ($salesMigrations as $col => $sql) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM `pelaksanaan_sales` LIKE '$col'");
    if ($chk && mysqli_num_rows($chk) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Auto migration: Add rescheduled columns to kegiatan_sales table
$checkReschedFrom = mysqli_query($conn, "SHOW COLUMNS FROM `kegiatan_sales` LIKE 'rescheduled_from'");
if ($checkReschedFrom && mysqli_num_rows($checkReschedFrom) == 0) {
    mysqli_query($conn, "ALTER TABLE `kegiatan_sales` ADD COLUMN `rescheduled_from` INT NULL DEFAULT NULL COMMENT 'Reference ke ID kegiatan lama yang di-reschedule'");
}
$checkReschedReason = mysqli_query($conn, "SHOW COLUMNS FROM `kegiatan_sales` LIKE 'reschedule_reason'");
if ($checkReschedReason && mysqli_num_rows($checkReschedReason) == 0) {
    mysqli_query($conn, "ALTER TABLE `kegiatan_sales` ADD COLUMN `reschedule_reason` TEXT NULL DEFAULT NULL COMMENT 'Alasan reschedule'");
}

include "session.php";
include "get-user-data.php";
$pageNow = "Dashboard";
$currentPage = "Today";

if (!isset($_GET['id'])) {
    echo "ID Kegiatan tidak ditemukan.";
    exit;
}

$idKegiatan = $_GET['id'];

// Ambil data kegiatan beserta koordinat customer
$sqlKegiatan = "SELECT ks.*, c.nama AS nama_customer, c.telp_pribadi, c.alamat, w.nama AS nama_wilayah, c.kategori AS kategori_customer, c.foto AS foto_customer,
                       c.lat AS cust_lat, c.lon AS cust_lon, c.rad AS cust_rad, c.alamat_lokasi AS cust_alamat_lokasi
                FROM kegiatan_sales ks
                LEFT JOIN sales_customer c ON ks.id_customer = c.id
                LEFT JOIN wilayah w ON c.id_wilayah = w.id
                WHERE ks.id = '$idKegiatan' AND ks.deleted_at IS NULL";

$resultKegiatan = mysqli_query($conn, $sqlKegiatan);
$data = mysqli_fetch_assoc($resultKegiatan);

// Ambil tim sales
$sqlSales = "SELECT s.nama AS nama_sales, s.foto AS foto_sales, ps.status, ps.keterangan, ps.image_1, ps.image_2, ps.image_3, ps.record,
                    ps.ci_at, ps.co_at, ps.lat_ci, ps.lon_ci, ps.lat_co, ps.lon_co, ps.catatan_visit,
                    ps.nama_client, ps.nomer_client, ps.tipe_prospek, ps.no_invoice
             FROM team_kegiatan_sales tks
             LEFT JOIN sales s ON tks.id_sales = s.id
             LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = tks.id_kegiatan_sales AND ps.sales_id = tks.id_sales
             WHERE tks.id_kegiatan_sales = '$idKegiatan' AND tks.deleted_at IS NULL";

$resultSales = mysqli_query($conn, $sqlSales);
$sales_count = mysqli_num_rows($resultSales);

// Cek apakah lokasi koordinat tersedia — prioritaskan kegiatan, fallback ke customer
$map_lat = !empty($data['lat']) ? $data['lat'] : ($data['cust_lat'] ?? null);
$map_lon = !empty($data['lon']) ? $data['lon'] : ($data['cust_lon'] ?? null);
$map_rad = !empty($data['rad']) ? $data['rad'] : ($data['cust_rad'] ?? 100);
$has_coords = !empty($map_lat) && !empty($map_lon);
$left_col_class = $has_coords ? "col-lg-7" : "col-lg-12";
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <?php include "head.php"; ?>
  
  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  
  <!-- Premium Font -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    /* ── Premium Styling Refinements ── */
    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    }
    
    .card-premium {
      background: #fff;
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.02);
      margin-bottom: 24px;
    }
    
    .section-header-premium {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 28px;
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
      color: #fff;
    }
    
    .section-header-premium h6 {
      margin: 0;
      font-size: 14px;
      font-weight: 700;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .card-body-premium {
      padding: 32px 36px;
    }

    /* ── Hero Details Layout ── */
    .hero-detail-card {
      background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
      border-radius: 20px;
      padding: 32px 36px;
      color: #fff;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
      margin-bottom: 24px;
    }
    .hero-decor-circle {
      position: absolute;
      top: -60px;
      right: -40px;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.04);
      z-index: 1;
    }
    .hero-decor-circle-2 {
      position: absolute;
      bottom: -80px;
      right: 120px;
      width: 140px;
      height: 140px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.03);
      z-index: 1;
    }

    .hero-avatar {
      width: 68px;
      height: 68px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      font-weight: 800;
      color: #fff;
      border: 2px solid rgba(255,255,255,0.25);
      box-shadow: 0 6px 16px rgba(0,0,0,0.1);
      flex-shrink: 0;
    }
    
    .hero-info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 24px;
      margin-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, 0.12);
      padding-top: 24px;
    }

    .hero-info-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .hero-info-label {
      font-size: 10px;
      font-weight: 800;
      color: rgba(255, 255, 255, 0.5);
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .hero-info-value {
      font-size: 14px;
      font-weight: 600;
      color: #fff;
    }

    .category-badge {
      font-size: 9.5px;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 8px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      display: inline-block;
      box-shadow: 0 2px 4px rgba(0,0,0,0.02);
      border: 1px solid rgba(0,0,0,0.03);
    }
    .badge-dealer { background: #eff6ff; color: #1e40af; border-color: #dbeafe; }
    .badge-installer { background: #faf5ff; color: #6b21a8; border-color: #f3e8ff; }
    .badge-user { background: #ecfdf5; color: #065f46; border-color: #d1fae5; }
    .badge-default { background: #f8fafc; color: #475569; border-color: #e2e8f0; }

    /* ── Laporan Card Redesign ── */
    .sales-laporan-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 28px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.01);
      transition: all 0.25s ease;
      position: relative;
    }
    .sales-laporan-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.04);
      border-color: #cbd5e1;
    }
    
    .sales-header-strip {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1.5px solid #f1f5f9;
      padding-bottom: 18px;
      margin-bottom: 20px;
    }

    .sales-profile {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .sales-initial {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 13px;
      color: #fff;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }

    .sales-name-label {
      font-size: 15px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.2;
    }
    
    .status-badge-sales {
      font-size: 9.5px;
      font-weight: 800;
      padding: 4px 12px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .badge-selesai { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .badge-berjalan { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
    .badge-dijadwalkan { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    /* Absensi Timeline Box */
    .timeline-absensi-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 18px 20px;
      display: flex;
      position: relative;
      margin-bottom: 20px;
    }
    
    .timeline-divider {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 1px;
      height: 70%;
      background: #cbd5e1;
    }
    
    .timeline-node {
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    
    .node-header {
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 6px;
    }
    
    .node-time {
      font-size: 13.5px;
      font-weight: 800;
      color: #1e293b;
      font-family: monospace;
    }
    
    .node-map-btn {
      font-size: 11px;
      font-weight: 700;
      color: #2563eb;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      margin-top: 8px;
      width: fit-content;
      transition: color 0.15s;
    }
    .node-map-btn:hover {
      color: #1d4ed8;
      text-decoration: underline;
    }
    
    /* Laporan Note Styling */
    .laporan-note-container {
      background: #f8fafc;
      border-left: 4px solid #3b82f6;
      border-radius: 0 12px 12px 0;
      padding: 16px 20px;
      margin-bottom: 20px;
    }
    
    .laporan-note-title {
      font-size: 10px;
      font-weight: 800;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 6px;
    }
    
    .laporan-note-text {
      font-size: 13.5px;
      color: #334155;
      font-weight: 500;
      line-height: 1.5;
      font-style: italic;
    }

    /* Photos Grid styling */
    .doc-photo-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 20px;
    }
    
    .doc-photo-wrapper {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      border: 1.5px solid #e2e8f0;
      width: 110px;
      height: 110px;
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      background: #f8fafc;
    }
    .doc-photo-wrapper img, .doc-photo-wrapper video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .doc-photo-wrapper:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.2), 0 4px 6px -4px rgba(59, 130, 246, 0.2);
      border-color: #3b82f6;
    }

    .audio-player-wrapper {
      background: #f8fafc;
      border: 1.5px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px;
    }

    audio.premium-audio {
      width: 100%;
      border-radius: 30px;
      outline: none;
    }
    
    .wa-pill-hero {
      background: #25D366;
      color: #fff !important;
      font-size: 11px;
      font-weight: 800;
      padding: 6px 14px;
      border-radius: 30px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 4px 12px rgba(37, 211, 102, 0.25);
      transition: all 0.2s;
      width: fit-content;
      margin-top: 4px;
    }
    
    .wa-pill-hero:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(37, 211, 102, 0.35);
      background: #20ba59;
    }

    /* Maps style */
    #map_detail {
      height: 380px;
      width: 100%;
      border-radius: 12px;
      border: 1.5px solid #e2e8f0;
    }

    <?php include "css/floating-menu2.css";?>
  </style>
</head>

<body class="g-sidenav-show bg-gray-200">
  <?php include "cek-menu.php"; ?>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
    <?php include "nav-top.php"; ?>

    <div class="container-fluid py-4">

      <!-- Hero Header Card (Premium Redesign) -->
      <div class="hero-detail-card">
        <div class="hero-decor-circle"></div>
        <div class="hero-decor-circle-2"></div>
        
        <div style="display:flex; align-items:center; gap:20px; position:relative; z-index:2;">
          <div class="hero-avatar">
            <?php 
              $words = explode(' ', $data['nama_customer'] ?? '');
              echo strtoupper(substr($words[0] ?? '', 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
            ?>
          </div>
          <div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
              <h2 style="color:#fff; margin:0; font-size:22px; font-weight:800; letter-spacing:-0.4px;"><?php echo htmlspecialchars($data['nama_customer'] ?? '-'); ?></h2>
              <?php 
                $kat = htmlspecialchars($data['kategori_customer'] ?? '');
                $badgeClass = match($kat) {
                  'Dealer' => 'badge-dealer',
                  'Installer' => 'badge-installer',
                  'User' => 'badge-user',
                  default => 'badge-default'
                };
              ?>
              <span class="category-badge <?= $badgeClass; ?>"><?= $kat; ?></span>
            </div>
            
            <p style="color:rgba(255,255,255,0.7); margin:4px 0 0 0; font-size:12px; font-weight:500;">
              Wilayah: <strong style="color:#fff;"><?= htmlspecialchars($data['nama_wilayah'] ?? 'Tanpa Wilayah'); ?></strong>
            </p>
            <?php if (!empty($data['kode'])): 
              $kodeParts = explode('/', $data['kode']);
              $kodeCust = $kodeParts[0] ?? '';
            ?>
            <div style="margin-top:6px;">
              <span style="background:rgba(255,255,255,0.15); color:#fff; font-size:11px; font-weight:700; padding:4px 12px; border-radius:6px; font-family:monospace; letter-spacing:0.5px; border:1px solid rgba(255,255,255,0.2);">
                🏷️ <?= htmlspecialchars($kodeCust); ?>
              </span>
            </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="hero-info-grid">
          <div class="hero-info-item">
            <span class="hero-info-label">Jadwal Kunjungan</span>
            <span class="hero-info-value" style="display:inline-flex; align-items:center; gap:4px; font-family:monospace; font-size:13.5px;">
              📅 <?php echo date('d M Y, H:i', strtotime($data['jadwal'])); ?> WIB
            </span>
          </div>
          
          <div class="hero-info-item">
            <span class="hero-info-label">Nomor Telepon Whatsapp</span>
            <span class="hero-info-value">
              <?php if (!empty($data['telp_pribadi'])): ?>
                <a href="https://wa.me/<?php echo htmlspecialchars($data['telp_pribadi']); ?>" target="_blank" class="wa-pill-hero">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.949h.004c4.368 0 7.927-3.558 7.93-7.93a7.9 7.9 0 0 0 -2.327-5.607z"/>
                  </svg>
                  <?php echo htmlspecialchars(preg_replace('/^62/', '0', $data['telp_pribadi'])); ?>
                </a>
              <?php else: ?>
                -
              <?php endif; ?>
            </span>
          </div>
          
          <div class="hero-info-item" style="grid-column: span 2;">
            <span class="hero-info-label">Alamat Toko Customer</span>
            <span class="hero-info-value" style="font-weight: 500; opacity: 0.95; line-height: 1.4;">
              📍 <?php echo htmlspecialchars($data['alamat'] ?? '-'); ?>
            </span>
          </div>
        </div>
      </div>

      <!-- MAIN CONTENT ROW: Split into left (form details/report) and right (interactive map) -->
      <div class="row">
        
        <!-- Left Column: Details & Reports -->
        <div class="<?= $left_col_class; ?>">
          
          <!-- Dokumentasi Toko / Customer (Up to 5 Photos) -->
          <?php 
          $foto_json = $data['foto_customer'] ?? '';
          $customer_photos = [];
          if (!empty($foto_json)) {
              $customer_photos = json_decode($foto_json, true);
              if (!is_array($customer_photos)) {
                  $customer_photos = [];
              }
          }
          if (!empty($customer_photos)):
          ?>
          <div class="card-premium">
            <div class="section-header-premium" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
              <h6>
                <span class="material-symbols-outlined" style="font-size:18px;">photo_library</span>
                Dokumentasi Toko / Lokasi Customer
              </h6>
            </div>
            <div class="card-body-premium">
              <div class="row g-3">
                <?php foreach ($customer_photos as $p): ?>
                  <?php if (file_exists("../uploads/customer/" . $p)): ?>
                    <div class="col-6 col-md-3">
                      <a href="../uploads/customer/<?= htmlspecialchars($p); ?>" target="_blank" style="display:block; border-radius:12px; overflow:hidden; border:1.5px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.02); transition:all 0.22s;" onmouseover="this.style.transform='scale(1.04)'; this.style.borderColor='#3b82f6';" onmouseout="this.style.transform='scale(1)'; this.style.borderColor='#e2e8f0';">
                        <img src="../uploads/customer/<?= htmlspecialchars($p); ?>" style="width:100%; height:120px; object-fit:cover;">
                      </a>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Detail Kunjungan Plan Card (keterangan) -->
          <?php if (!empty($data['keterangan'])): ?>
          <div class="card-premium">
            <div class="section-header-premium" style="background:#0f172a;">
              <h6>
                <span class="material-symbols-outlined">notes</span>
                Keperluan &amp; Keterangan Rencana Kunjungan
              </h6>
            </div>
            <div class="card-body-premium">
              <div style="font-size:14.5px; color:#334155; line-height:1.6; font-weight:500;">
                <?php echo nl2br(htmlspecialchars($data['keterangan'])); ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Tim Sales & Laporan Lapangan Section - ALL VISITS -->
          <?php
          // Query SEMUA kegiatan untuk customer yang sama
          $customerId = $data['id_customer'];
          $sqlAllKegiatan = "SELECT ks.id, ks.kode, ks.jadwal, ks.keterangan, ks.status
                             FROM kegiatan_sales ks
                             WHERE ks.id_customer = '$customerId' AND ks.deleted_at IS NULL
                             ORDER BY ks.jadwal DESC";
          $resultAllKegiatan = mysqli_query($conn, $sqlAllKegiatan);
          $totalKegiatan = mysqli_num_rows($resultAllKegiatan);
          
          while ($kegRow = mysqli_fetch_assoc($resultAllKegiatan)):
            $kegId = $kegRow['id'];
            $kegKode = $kegRow['kode'] ?? '';
            $kegKodeParts = explode('/', $kegKode);
            $kegKjg = $kegKodeParts[1] ?? $kegKode;
            $isCurrentKegiatan = ($kegId == $idKegiatan);
            
            // Query sales team untuk kegiatan ini
            $sqlKegSales = "SELECT s.nama AS nama_sales, s.foto AS foto_sales, ps.status, ps.keterangan, ps.image_1, ps.image_2, ps.image_3, ps.record,
                                   ps.ci_at, ps.co_at, ps.lat_ci, ps.lon_ci, ps.lat_co, ps.lon_co, ps.catatan_visit,
                                   ps.nama_client, ps.nomer_client, ps.tipe_prospek, ps.no_invoice
                             FROM team_kegiatan_sales tks
                             LEFT JOIN sales s ON tks.id_sales = s.id
                             LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = tks.id_kegiatan_sales AND ps.sales_id = tks.id_sales
                             WHERE tks.id_kegiatan_sales = '$kegId' AND tks.deleted_at IS NULL";
            $resultKegSales = mysqli_query($conn, $sqlKegSales);
            $kegSalesCount = mysqli_num_rows($resultKegSales);
            
            // Status kegiatan
            $kegStatus = strtolower($kegRow['status'] ?? 'dijadwalkan');
            $kegStatusLabel = match($kegStatus) {
              'selesai' => 'Selesai',
              'berjalan' => 'Berjalan',
              'dibatalkan' => 'Dibatalkan',
              default => 'Dijadwalkan'
            };
          ?>
          <div class="card-premium" style="<?= $isCurrentKegiatan ? 'border: 2px solid #3b82f6;' : ''; ?>">
            <div class="section-header-premium" style="justify-content: space-between; <?= !$isCurrentKegiatan ? 'background: linear-gradient(135deg, #1e293b 0%, #334155 100%);' : ''; ?>">
              <h6 style="display:flex; align-items:center; gap:10px;">
                <span class="material-symbols-outlined">groups</span>
                Tim Sales & Laporan Lapangan
                <?php if (!empty($kegRow['jadwal'])): ?>
                  <span style="font-size:11px; font-weight:500; opacity:0.7; font-family:monospace;">
                    📅 <?= date('d M Y, H:i', strtotime($kegRow['jadwal'])); ?> WIB
                  </span>
                <?php endif; ?>
              </h6>
              <div style="display:flex; align-items:center; gap:8px;">
                <?php if (!empty($kegKjg)): ?>
                <span style="background:rgba(255,255,255,0.15); color:#fff; font-size:12px; font-weight:700; padding:5px 14px; border-radius:8px; font-family:monospace; letter-spacing:0.5px; border:1px solid rgba(255,255,255,0.25);">
                  🏷️ <?= htmlspecialchars($kegKjg); ?>
                </span>
                <?php endif; ?>
                <span style="background:<?= match($kegStatus) { 'selesai' => '#10b981', 'berjalan' => '#3b82f6', 'dibatalkan' => '#ef4444', default => '#64748b' }; ?>; color:#fff; font-size:10px; font-weight:700; padding:4px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em;">
                  <?= $kegStatusLabel; ?>
                </span>
              </div>
            </div>
            <div class="card-body-premium">
              <!-- Reschedule Info Notice -->
              <?php if (strtolower($kegRow['status'] ?? '') === 'dibatalkan' && !empty($kegRow['reschedule_reason'])): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; border-radius: 12px; padding: 12px 16px;">
                  <span class="material-symbols-outlined" style="font-size: 20px;">event_busy</span>
                  <div style="font-size: 13.5px; font-weight: 500;">
                    <strong>Jadwal Ulang:</strong> Kunjungan ini dibatalkan untuk dijadwalkan kembali. <br>
                    <span style="opacity: 0.85;">💬 Alasan: <?= htmlspecialchars($kegRow['reschedule_reason']); ?></span>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($kegSalesCount > 0): ?>
              <div class="row g-4">
                <?php while ($row = mysqli_fetch_assoc($resultKegSales)): 
                  $status = strtolower($row['status'] ?? 'dijadwalkan');
                  $badgeClass = match($status) {
                    'selesai' => 'badge-selesai',
                    'berjalan' => 'badge-berjalan',
                    'dibatalkan' => 'badge-dibatalkan',
                    default => 'badge-dijadwalkan'
                  };
                  $colorTheme = match($status) {
                    'selesai' => '#10b981',
                    'berjalan' => '#3b82f6',
                    'dibatalkan' => '#ef4444',
                    default => '#64748b'
                  };
                  
                  $card_grid_class = "col-12";
                  if (!$has_coords) {
                      if ($kegSalesCount > 1) {
                          $card_grid_class = "col-md-6";
                      }
                  }
                ?>
                  <div class="<?= $card_grid_class; ?>">
                    <div class="sales-laporan-card">
                      
                      <!-- Card Header: Sales & Status -->
                      <div class="sales-header-strip">
                        <div class="sales-profile">
                          <?php if (!empty($row['foto_sales'])): ?>
                            <img src="https://api-teknisi.id-giti.com/storage/profile/<?= htmlspecialchars($row['foto_sales']); ?>" class="sales-avatar-img" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid <?= $colorTheme; ?>; margin-right: 8px;">
                          <?php else: ?>
                            <div class="sales-initial" style="background: <?= $colorTheme; ?>;">
                              <?php 
                                $words = explode(' ', $row['nama_sales'] ?? '');
                                echo strtoupper(substr($words[0] ?? '', 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                              ?>
                            </div>
                          <?php endif; ?>
                          <span class="sales-name-label"><?= htmlspecialchars($row['nama_sales'] ?? '-'); ?></span>
                          <?php 
                            $prospek = $row['tipe_prospek'] ?? 'Biasa';
                            if ($prospek !== 'Biasa') {
                                $pColor = match($prospek) {
                                    'Peluang' => '#10b981',
                                    'Menengah' => '#f59e0b',
                                    'Rumit' => '#ef4444',
                                    default => '#64748b'
                                };
                                $pBg = match($prospek) {
                                    'Peluang' => '#d1fae5',
                                    'Menengah' => '#fef3c7',
                                    'Rumit' => '#fee2e2',
                                    default => '#f1f5f9'
                                };
                                echo '<span style="font-size:10px; font-weight:bold; color:'.$pColor.'; background:'.$pBg.'; padding: 2px 8px; border-radius: 12px; margin-left: 8px;">'.$prospek.'</span>';
                            }
                          ?>
                        </div>
                        <span class="status-badge-sales <?= $badgeClass; ?>" style="background: <?= $colorTheme; ?>20; color: <?= $colorTheme; ?>; border: 1.5px solid <?= $colorTheme; ?>30; font-weight: bold;">
                          <?= htmlspecialchars(ucfirst($row['status'] ?? 'Dijadwalkan')); ?>
                        </span>
                      </div>

                      <!-- ABSENSI TIMELINE BOX -->
                      <div class="timeline-absensi-box">
                        <div class="timeline-divider"></div>
                        
                        <!-- Clock In Node -->
                        <div class="timeline-node">
                          <span class="node-header" style="color: #10b981;">
                            <span class="material-symbols-outlined" style="font-size:13px; font-variation-settings:'FILL' 1;">login</span>
                            Clock In
                          </span>
                          <span class="node-time">
                            <?= !empty($row['ci_at']) ? date('d M, H:i', strtotime($row['ci_at'])) . ' WIB' : '<span class="text-muted font-weight-normal" style="font-size:11px; font-family:sans-serif;">Belum In</span>'; ?>
                          </span>
                          <?php if (!empty($row['lat_ci']) && !empty($row['lon_ci'])): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $row['lat_ci']; ?>,<?= $row['lon_ci']; ?>" target="_blank" class="node-map-btn">
                              <span class="material-symbols-outlined" style="font-size:13px;">explore</span> Lokasi CI
                            </a>
                          <?php endif; ?>
                        </div>
                        
                        <!-- Clock Out Node -->
                        <div class="timeline-node" style="padding-left: 20px;">
                          <span class="node-header" style="color: #ef4444;">
                            <span class="material-symbols-outlined" style="font-size:13px; font-variation-settings:'FILL' 1;">logout</span>
                            Clock Out
                          </span>
                          <span class="node-time">
                            <?= !empty($row['co_at']) ? date('d M, H:i', strtotime($row['co_at'])) . ' WIB' : '<span class="text-muted font-weight-normal" style="font-size:11px; font-family:sans-serif;">Belum Out</span>'; ?>
                          </span>
                          <?php if (!empty($row['lat_co']) && !empty($row['lon_co'])): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $row['lat_co']; ?>,<?= $row['lon_co']; ?>" target="_blank" class="node-map-btn">
                              <span class="material-symbols-outlined" style="font-size:13px;">explore</span> Lokasi CO
                            </a>
                          <?php endif; ?>
                        </div>
                      </div>

                      <!-- Client Contact Info -->
                      <?php if (!empty($row['nama_client']) || !empty($row['nomer_client'])): ?>
                        <div class="laporan-note-container" style="border-left-color: #3b82f6; margin-bottom: 12px;">
                          <div class="laporan-note-title" style="color: #3b82f6; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">contact_phone</span> Kontak Client / Hubungan
                          </div>
                          <div class="laporan-note-text" style="display: flex; flex-direction: column; gap: 4px; font-size: 13px; font-weight: 500; color: #334155;">
                            <?php if (!empty($row['nama_client'])): ?>
                              <span>👤 Nama Client: <strong style="color: #1e293b;"><?= htmlspecialchars($row['nama_client']); ?></strong></span>
                            <?php endif; ?>
                            <?php if (!empty($row['nomer_client'])): ?>
                              <span>📞 Telepon: <strong style="color: #1e293b;"><?= htmlspecialchars($row['nomer_client']); ?></strong>
                              <?php 
                                $cleanPhone = preg_replace('/[^0-9]/', '', $row['nomer_client']);
                                if (str_starts_with($cleanPhone, '0')) {
                                  $cleanPhone = '62' . substr($cleanPhone, 1);
                                }
                              ?>
                              <a href="https://wa.me/<?= $cleanPhone; ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; background: #25d366; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px; text-decoration: none; font-weight: bold;">
                                Hubungi WA
                              </a>
                              </span>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endif; ?>

                      <!-- Invoice Info -->
                      <?php if (!empty($row['no_invoice'])): ?>
                        <div class="laporan-note-container" style="border-left-color: #10b981; margin-bottom: 12px; background: #f0fdf4;">
                          <div class="laporan-note-title" style="color: #10b981; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                            <span class="material-symbols-outlined" style="font-size: 16px;">receipt</span> Transaksi Penjualan
                          </div>
                          <div class="laporan-note-text" style="font-size: 13px; font-weight: 500; color: #14532d;">
                            <span>📄 No. Invoice: <strong style="color: #14532d;"><?= htmlspecialchars($row['no_invoice']); ?></strong></span>
                          </div>
                        </div>
                      <?php endif; ?>

                      <!-- Notes field -->
                      <?php 
                      $catatan = !empty($row['catatan_visit']) ? $row['catatan_visit'] : (!empty($row['keterangan']) ? $row['keterangan'] : '');
                      if (!empty($catatan)): 
                      ?>
                        <div class="laporan-note-container">
                          <div class="laporan-note-title">Catatan Kunjungan</div>
                          <div class="laporan-note-text">"<?= htmlspecialchars($catatan); ?>"</div>
                        </div>
                      <?php else: ?>
                        <div class="laporan-note-container" style="border-left-color: #e2e8f0;">
                          <div class="laporan-note-title">Catatan Kunjungan</div>
                          <div class="laporan-note-text text-muted" style="font-size:12px;">Tidak ada catatan lapangan.</div>
                        </div>
                      <?php endif; ?>

                      <!-- Dokumentasi Foto & Video -->
                      <?php if (!empty($row['image_1']) || !empty($row['image_2']) || !empty($row['image_3']) || !empty($row['image_4']) || !empty($row['image_5'])): ?>
                        <div style="margin-bottom: 20px;">
                          <span class="info-label" style="font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 8px;">Dokumentasi Foto &amp; Video Lapangan</span>
                          <div class="doc-photo-grid">
                            <?php foreach (['image_1', 'image_2', 'image_3', 'image_4', 'image_5'] as $img): ?>
                              <?php if (!empty($row[$img])): 
                                $ext = strtolower(pathinfo($row[$img], PATHINFO_EXTENSION));
                                $is_video = in_array($ext, ['mp4', 'webm', 'mov', '3gp', 'avi', 'ogg']);
                              ?>
                                <a href="https://api-teknisi.id-giti.com/storage/image/<?php echo $row[$img]; ?>" target="_blank" class="doc-photo-wrapper" style="position: relative; display: block;">
                                  <?php if ($is_video): ?>
                                    <video src="https://api-teknisi.id-giti.com/storage/image/<?php echo $row[$img]; ?>" style="width: 100%; height: 100%; object-fit: cover;" muted playsinline></video>
                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                      <span class="material-symbols-outlined" style="color: #fff; font-size: 28px; font-variation-settings: 'FILL' 1;">play_circle</span>
                                    </div>
                                  <?php else: ?>
                                    <img src="https://api-teknisi.id-giti.com/storage/image/<?php echo $row[$img]; ?>" alt="Dokumentasi">
                                  <?php endif; ?>
                                </a>
                              <?php endif; ?>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      <?php endif; ?>

                      <!-- Rekaman Audio -->
                      <?php if (!empty($row['record'])): ?>
                        <div>
                          <span class="info-label" style="font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 8px;">Rekaman Laporan Suara</span>
                          <div class="audio-player-wrapper">
                            <audio controls class="premium-audio">
                              <source src="https://api-teknisi.id-giti.com/storage/record/<?php echo $row['record']; ?>" type="audio/mpeg">
                              <source src="https://api-teknisi.id-giti.com/storage/record/<?php echo $row['record']; ?>" type="audio/aac">
                              <source src="https://api-teknisi.id-giti.com/storage/record/<?php echo $row['record']; ?>" type="audio/x-aac">
                              <source src="https://api-teknisi.id-giti.com/storage/record/<?php echo $row['record']; ?>" type="audio/mp4">
                              Browser Anda tidak mendukung pemutar suara.
                            </audio>
                          </div>
                        </div>
                      <?php endif; ?>
                      
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
              <?php else: ?>
                <div class="text-center py-5 text-muted">
                  <span class="material-symbols-outlined" style="font-size: 44px; color: #cbd5e1; display: block; margin-bottom: 8px;">groups</span>
                  <p style="font-size: 14px; font-weight: 600; color:#64748b; margin: 0;">Belum ada sales terdaftar untuk kegiatan kunjungan ini.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endwhile; ?>

        </div>

        <!-- Right Column: Interactive Map -->
        <?php if ($has_coords): ?>
        <div class="col-lg-5">
          <div class="card-premium h-100" style="min-height: 480px; display: flex; flex-direction: column;">
            <div class="section-header-premium" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);">
              <h6>
                <span class="material-symbols-outlined" style="font-size:18px;">map</span>
                Peta Geofence Lokasi Customer
              </h6>
            </div>
            <div class="card-body-premium" style="flex: 1; display: flex; flex-direction: column; padding: 24px;">
              <!-- Map Div -->
              <div id="map_detail" style="flex: 1; min-height: 380px;"></div>
              
              <!-- Address Details geocoder -->
              <div class="mt-3 p-3 bg-light rounded-3" style="font-size: 13px; color: #475569; line-height: 1.4;">
                <span style="font-weight: 700; color: #0f172a; display: block; margin-bottom: 4px;">Alamat Peta Geofence:</span>
                <span><?= htmlspecialchars($data['cust_alamat_lokasi'] ?? 'Alamat geocoder tidak tersedia.'); ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

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
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
      var options = {
        damping: '0.5'
      }
      Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
  </script>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- Map Initialization -->
  <?php if ($has_coords): ?>
  <script>
    setTimeout(() => {
        const lat = <?= floatval($map_lat); ?>;
        const lon = <?= floatval($map_lon); ?>;
        const rad = <?= intval($map_rad); ?>;
        const latlng = L.latLng(lat, lon);
        
        const map = L.map('map_detail').setView(latlng, 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        L.marker(latlng).addTo(map);
        L.circle(latlng, { radius: rad, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.15 }).addTo(map);
    }, 250);
  </script>
  <?php endif; ?>

</body>

</html>