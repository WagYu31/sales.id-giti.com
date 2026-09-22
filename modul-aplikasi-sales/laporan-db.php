<?php
/**
 * laporan-db.php - Laporan Visit & Check-in GPS Mobile Sales
 * Loewix Sales Management System
 */

// Filter Parameter Handling
$filterBulan   = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m');
$filterTanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$filterSales   = isset($_GET['sales_id']) ? trim($_GET['sales_id']) : 'all';
$filterProspek = isset($_GET['prospek']) ? trim($_GET['prospek']) : 'all';
$searchQuery   = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base Query Condition
$whereClause = ["ps.deleted_at IS NULL"];

// Date Filtering
if (!empty($filterTanggal)) {
    $safeTgl = mysqli_real_escape_string($conn, $filterTanggal);
    $whereClause[] = "(DATE(ps.ci_at) = '$safeTgl' OR DATE(ps.created_at) = '$safeTgl')";
} elseif (!empty($filterBulan)) {
    $safeBulan = mysqli_real_escape_string($conn, $filterBulan);
    $whereClause[] = "(DATE_FORMAT(ps.ci_at, '%Y-%m') = '$safeBulan' OR (ps.ci_at IS NULL AND DATE_FORMAT(ps.created_at, '%Y-%m') = '$safeBulan'))";
}

// Sales Filtering
if ($filterSales !== 'all' && !empty($filterSales)) {
    $safeSales = mysqli_real_escape_string($conn, $filterSales);
    $whereClause[] = "(ps.id_sales = '$safeSales' OR ps.sales_id = '$safeSales')";
}

// Prospek Filtering
if ($filterProspek !== 'all' && !empty($filterProspek)) {
    $safeProspek = mysqli_real_escape_string($conn, $filterProspek);
    $whereClause[] = "ps.tipe_prospek = '$safeProspek'";
}

// Search Query Filtering
if (!empty($searchQuery)) {
    $safeSearch = mysqli_real_escape_string($conn, $searchQuery);
    $whereClause[] = "(sc.nama LIKE '%$safeSearch%' OR ps.nama_client LIKE '%$safeSearch%' OR ps.catatan_visit LIKE '%$safeSearch%' OR ps.no_invoice LIKE '%$safeSearch%' OR ps.nama_sales LIKE '%$safeSearch%')";
}

$whereSql = implode(" AND ", $whereClause);

// Query Summary KPI
$summaryQuery = "
    SELECT 
        COUNT(DISTINCT ps.id) AS total_visit,
        SUM(CASE WHEN ps.status = 'selesai' OR ps.co_at IS NOT NULL THEN 1 ELSE 0 END) AS total_selesai,
        COUNT(DISTINCT ks.id_customer) AS total_toko,
        COUNT(DISTINCT COALESCE(ps.id_sales, ps.sales_id, ps.nama_sales)) AS total_sales_aktif
    FROM pelaksanaan_sales ps
    LEFT JOIN kegiatan_sales ks ON ps.kegiatan_id = ks.id
    LEFT JOIN sales_customer sc ON ks.id_customer = sc.id
    WHERE $whereSql
";
$resSummary = mysqli_query($conn, $summaryQuery);
$summaryData = ($resSummary && $rowSum = mysqli_fetch_assoc($resSummary)) ? $rowSum : [
    'total_visit' => 0,
    'total_selesai' => 0,
    'total_toko' => 0,
    'total_sales_aktif' => 0
];

// Query Data Kunjungan Visit GPS
$dataQuery = "
    SELECT 
        ps.*,
        ks.jadwal AS jadwal_rencana,
        ks.keterangan AS ket_jadwal,
        ks.kode AS kode_kegiatan,
        ks.lat AS lat_jadwal,
        ks.lon AS lon_jadwal,
        sc.nama AS nama_customer,
        sc.kategori AS kategori_customer,
        sc.alamat AS alamat_customer,
        sc.kota AS kota_customer,
        sc.telp_pribadi AS telp_customer,
        sc.foto AS foto_customer,
        s.nama_lengkap AS sales_lengkap,
        s.nik AS sales_nik,
        s.telp AS sales_telp,
        w.nama AS nama_wilayah
    FROM pelaksanaan_sales ps
    LEFT JOIN kegiatan_sales ks ON ps.kegiatan_id = ks.id
    LEFT JOIN sales_customer sc ON ks.id_customer = sc.id
    LEFT JOIN sales s ON (ps.id_sales = s.id OR ps.sales_id = s.id)
    LEFT JOIN wilayah w ON sc.id_wilayah = w.id
    WHERE $whereSql
    ORDER BY COALESCE(ps.ci_at, ps.created_at) DESC
";
$resData = mysqli_query($conn, $dataQuery);

// Fetch List Sales for Filter Dropdown
$salesListQuery = mysqli_query($conn, "SELECT id, COALESCE(nama, nama_lengkap) AS nama_sales, nik FROM sales WHERE deleted_at IS NULL ORDER BY nama_sales ASC");
?>

<style>
  .filter-panel {
    background: #ffffff;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    border: 1px solid #e2e8f0;
    margin-bottom: 24px;
  }
  .stat-card-visit {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    border: 1px solid #e2e8f0;
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
  }
  .stat-card-visit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
  }
  .stat-label-visit {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 6px;
  }
  .stat-val-visit {
    font-size: 26px;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    color: #0f172a;
    margin: 0;
    line-height: 1;
  }
  .stat-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
  }
  .report-table-card {
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    border: 1px solid #e2e8f0;
    overflow: hidden;
  }
  .report-table thead th {
    background-color: #0f172a;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 14px 16px;
    vertical-align: middle;
    border: none;
  }
  .report-table tbody td {
    padding: 16px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
  }
  .report-table tbody tr:hover {
    background-color: #f8fafc;
  }
  .badge-prospek {
    font-size: 11px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 20px;
    display: inline-block;
  }
  .prospek-deal { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
  .prospek-hot { background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
  .prospek-biasa { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
  .prospek-fu { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

  .gps-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    background-color: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 4px 8px;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.15s;
  }
  .gps-badge:hover {
    background-color: #22c55e;
    color: #ffffff;
  }
  .photo-thumb-wrap {
    position: relative;
    width: 44px;
    height: 44px;
    border-radius: 8px;
    overflow: hidden;
    border: 1.5px solid #e2e8f0;
    cursor: pointer;
    background: #f1f5f9;
    display: inline-block;
  }
  .photo-thumb-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s;
  }
  .photo-thumb-wrap:hover img {
    transform: scale(1.1);
  }
  .initial-badge-sm {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: #fff;
    font-weight: 700;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
</style>

<div class="col-12">
  <!-- ── 1. HEADER & ACTION TITLE ────────────────────────────────────────── -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1" style="font-family:'Outfit',sans-serif; color:#0f172a;">
        <i class="bi bi-geo-alt-fill text-primary me-2"></i>Laporan Visit & Log GPS Sales
      </h4>
      <p class="text-muted small mb-0">Rekapitulasi aktivitas check-in, tracking lokasi GPS, hasil visit & dokumentasi foto dari aplikasi mobile sales.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="kegiatan.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 fw-semibold">
        <i class="bi bi-calendar-check me-1"></i> Jadwal Penugasan
      </a>
      <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-3 py-2 fw-semibold shadow-sm">
        <i class="bi bi-printer me-1"></i> Cetak Laporan
      </button>
    </div>
  </div>

  <!-- ── 2. FILTER CONTROLS ─────────────────────────────────────────────── -->
  <div class="filter-panel">
    <form method="GET" action="" class="row g-3 align-items-end">
      <!-- Filter Bulan -->
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label small fw-bold text-muted mb-1">Periode Bulan</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar-month text-muted"></i></span>
          <input type="month" name="bulan" class="form-control border-start-0" value="<?= htmlspecialchars($filterBulan); ?>">
        </div>
      </div>

      <!-- Filter Tanggal Spesifik -->
      <div class="col-12 col-sm-6 col-md-2">
        <label class="form-label small fw-bold text-muted mb-1">Tanggal Spesifik</label>
        <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($filterTanggal); ?>">
      </div>

      <!-- Filter Sales -->
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label small fw-bold text-muted mb-1">Sales Lapangan</label>
        <select name="sales_id" class="form-select form-select-sm">
          <option value="all">-- Semua Sales --</option>
          <?php if ($salesListQuery): while ($s = mysqli_fetch_assoc($salesListQuery)): ?>
            <option value="<?= $s['id']; ?>" <?= ($filterSales == $s['id']) ? 'selected' : ''; ?>>
              <?= htmlspecialchars($s['nama_sales']); ?> <?= !empty($s['nik']) ? '('.htmlspecialchars($s['nik']).')' : ''; ?>
            </option>
          <?php endwhile; endif; ?>
        </select>
      </div>

      <!-- Filter Prospek -->
      <div class="col-12 col-sm-6 col-md-2">
        <label class="form-label small fw-bold text-muted mb-1">Tipe Prospek</label>
        <select name="prospek" class="form-select form-select-sm">
          <option value="all" <?= ($filterProspek == 'all') ? 'selected' : ''; ?>>Semua Tipe</option>
          <option value="Deal" <?= ($filterProspek == 'Deal') ? 'selected' : ''; ?>>Deal / Closing</option>
          <option value="Hot Lead" <?= ($filterProspek == 'Hot Lead') ? 'selected' : ''; ?>>Hot Lead</option>
          <option value="Follow Up" <?= ($filterProspek == 'Follow Up') ? 'selected' : ''; ?>>Follow Up</option>
          <option value="Biasa" <?= ($filterProspek == 'Biasa') ? 'selected' : ''; ?>>Biasa / Prospek</option>
        </select>
      </div>

      <!-- Search Keyword -->
      <div class="col-12 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold rounded-3 py-2">
          <i class="bi bi-search me-1"></i> Filter
        </button>
        <a href="laporan-kegiatan.php" class="btn btn-light btn-sm fw-semibold rounded-3 py-2 px-3 text-muted" title="Reset Filter">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- ── 3. SUMMARY STATS CARDS ─────────────────────────────────────────── -->
  <div class="row g-3 mb-4">
    <!-- Card 1: Total Visit -->
    <div class="col-6 col-md-3">
      <div class="stat-card-visit" style="border-left: 4px solid #3b82f6;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label-visit">Total Kunjungan</div>
            <h3 class="stat-val-visit"><?= number_format($summaryData['total_visit'] ?? 0); ?></h3>
            <span class="text-muted" style="font-size: 11px;">Check-in terdaftar</span>
          </div>
          <div class="stat-icon-wrap" style="background:#eff6ff; color:#3b82f6;">
            <i class="bi bi-geo-alt"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Card 2: Visit Selesai -->
    <div class="col-6 col-md-3">
      <div class="stat-card-visit" style="border-left: 4px solid #10b981;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label-visit">Visit Selesai (CO)</div>
            <h3 class="stat-val-visit text-success"><?= number_format($summaryData['total_selesai'] ?? 0); ?></h3>
            <span class="text-muted" style="font-size: 11px;">Laporan tuntas</span>
          </div>
          <div class="stat-icon-wrap" style="background:#ecfdf5; color:#10b981;">
            <i class="bi bi-check2-circle"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Card 3: Toko Dikunjungi -->
    <div class="col-6 col-md-3">
      <div class="stat-card-visit" style="border-left: 4px solid #f59e0b;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label-visit">Toko / Mitra Terkunjungi</div>
            <h3 class="stat-val-visit text-warning"><?= number_format($summaryData['total_toko'] ?? 0); ?></h3>
            <span class="text-muted" style="font-size: 11px;">Entitas pelanggan</span>
          </div>
          <div class="stat-icon-wrap" style="background:#fffbeb; color:#f59e0b;">
            <i class="bi bi-shop"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Card 4: Sales Aktif -->
    <div class="col-6 col-md-3">
      <div class="stat-card-visit" style="border-left: 4px solid #8b5cf6;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label-visit">Sales Aktif Visit</div>
            <h3 class="stat-val-visit text-purple" style="color:#8b5cf6;"><?= number_format($summaryData['total_sales_aktif'] ?? 0); ?></h3>
            <span class="text-muted" style="font-size: 11px;">Personil bergerak</span>
          </div>
          <div class="stat-icon-wrap" style="background:#f5f3ff; color:#8b5cf6;">
            <i class="bi bi-people"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── 4. TABLE LOG VISIT GPS ─────────────────────────────────────────── -->
  <div class="report-table-card">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-white">
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary rounded-pill px-2.5 py-1.5" style="font-size:11px;">
          <i class="bi bi-list-check me-1"></i> Data Log
        </span>
        <span class="text-muted small">
          Periode: <strong><?= !empty($filterTanggal) ? formatTanggal('dd MMMM yyyy', $filterTanggal) : formatTanggal('MMMM yyyy', $filterBulan.'-01'); ?></strong>
        </span>
      </div>
      <div class="text-muted small">
        Menampilkan <strong><?= $resData ? mysqli_num_rows($resData) : 0; ?></strong> Kunjungan
      </div>
    </div>

    <div class="table-responsive">
      <table class="table report-table mb-0">
        <thead>
          <tr>
            <th style="width: 50px; text-align: center;">No</th>
            <th style="width: 170px;">Waktu & Durasi</th>
            <th style="width: 180px;">Sales Person</th>
            <th>Toko / Customer Mitra</th>
            <th style="width: 170px;">PIC & Kontak</th>
            <th style="width: 160px;">Hasil & Prospek</th>
            <th style="width: 150px;">Lokasi GPS</th>
            <th style="width: 90px; text-align: center;">Foto</th>
            <th style="width: 70px; text-align: center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if ($resData && mysqli_num_rows($resData) > 0): 
            $no = 1;
            while ($row = mysqli_fetch_assoc($resData)):
              // Format Waktu CI & CO
              $ciTime = $row['ci_at'] ? date('H:i', strtotime($row['ci_at'])) : '-';
              $ciDate = $row['ci_at'] ? date('d M Y', strtotime($row['ci_at'])) : ($row['created_at'] ? date('d M Y', strtotime($row['created_at'])) : '-');
              $coTime = $row['co_at'] ? date('H:i', strtotime($row['co_at'])) : null;
              
              // Hitung Durasi
              $durasiText = "-";
              if ($row['ci_at'] && $row['co_at']) {
                  $diffSeconds = strtotime($row['co_at']) - strtotime($row['ci_at']);
                  if ($diffSeconds > 0) {
                      $hours = floor($diffSeconds / 3600);
                      $minutes = floor(($diffSeconds % 3600) / 60);
                      if ($hours > 0) {
                          $durasiText = "{$hours}j {$minutes}m";
                      } else {
                          $durasiText = "{$minutes} menit";
                      }
                  } else {
                      $durasiText = "< 1 menit";
                  }
              } elseif ($row['ci_at'] && !$row['co_at']) {
                  $durasiText = '<span class="text-primary fw-semibold"><i class="bi bi-clock-history"></i> Berlangsung</span>';
              }

              // Prospek class
              $prospek = $row['tipe_prospek'] ?? 'Biasa';
              $prospekClass = 'prospek-biasa';
              if (stripos($prospek, 'Deal') !== false || stripos($prospek, 'Closing') !== false) {
                  $prospekClass = 'prospek-deal';
              } elseif (stripos($prospek, 'Hot') !== false) {
                  $prospekClass = 'prospek-hot';
              } elseif (stripos($prospek, 'Follow') !== false) {
                  $prospekClass = 'prospek-fu';
              }

              // Sales name
              $salesName = $row['sales_lengkap'] ?? ($row['nama_sales'] ?? 'Sales');
              $salesWords = explode(' ', trim($salesName));
              $initials = strtoupper(substr($salesWords[0], 0, 1) . (isset($salesWords[1]) ? substr($salesWords[1], 0, 1) : ''));

              // Customer info
              $custName = $row['nama_customer'] ?? ($row['nama_client'] ?? 'Kunjungan Langsung');
              $custKota = $row['kota_customer'] ?? ($row['nama_wilayah'] ?? '');

              // GPS Coordinates
              $latCI = $row['lat_ci'] ?? ($row['lat_jadwal'] ?? '');
              $lonCI = $row['lon_ci'] ?? ($row['lon_jadwal'] ?? '');

              // Photos
              $photos = array_filter([
                  $row['foto'] ?? '',
                  $row['image_1'] ?? '',
                  $row['image_2'] ?? '',
                  $row['image_3'] ?? '',
                  $row['image_4'] ?? '',
                  $row['image_5'] ?? ''
              ]);
              $firstPhoto = !empty($photos) ? reset($photos) : '';
          ?>
          <tr>
            <!-- No -->
            <td style="text-align: center; font-weight: 600; color: #94a3b8;"><?= $no++; ?></td>
            
            <!-- Waktu & Durasi -->
            <td>
              <div class="fw-bold text-dark" style="font-size: 13px;"><?= htmlspecialchars($ciDate); ?></div>
              <div class="d-flex align-items-center gap-1 small text-muted mt-0.5">
                <i class="bi bi-clock text-primary"></i> 
                <span><?= $ciTime; ?> <?= $coTime ? " - {$coTime}" : ""; ?></span>
              </div>
              <div class="mt-1">
                <span class="badge bg-light text-dark border px-2 py-0.5" style="font-size: 10.5px; font-weight: 600;">
                  <?= $durasiText; ?>
                </span>
              </div>
            </td>

            <!-- Sales Person -->
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="initial-badge-sm"><?= $initials; ?></div>
                <div>
                  <div class="fw-bold text-dark text-truncate" style="max-width: 130px; font-size: 13px;" title="<?= htmlspecialchars($salesName); ?>">
                    <?= htmlspecialchars($salesName); ?>
                  </div>
                  <?php if (!empty($row['sales_nik'])): ?>
                  <span class="badge bg-light text-muted border" style="font-size: 10px;"><?= htmlspecialchars($row['sales_nik']); ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </td>

            <!-- Toko / Customer Mitra -->
            <td>
              <div class="fw-bold text-dark" style="font-size: 13.5px;">
                <?= htmlspecialchars($custName); ?>
              </div>
              <div class="d-flex align-items-center gap-1 text-muted small mt-0.5">
                <?php if (!empty($row['kategori_customer'])): ?>
                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;"><?= htmlspecialchars($row['kategori_customer']); ?></span>
                <?php endif; ?>
                <?php if (!empty($custKota)): ?>
                <span><i class="bi bi-geo-alt text-muted" style="font-size:11px;"></i> <?= htmlspecialchars($custKota); ?></span>
                <?php endif; ?>
              </div>
              <?php if (!empty($row['alamat_customer'])): ?>
              <div class="text-muted small text-truncate mt-1" style="max-width: 260px; font-size: 11.5px;" title="<?= htmlspecialchars($row['alamat_customer']); ?>">
                <?= htmlspecialchars($row['alamat_customer']); ?>
              </div>
              <?php endif; ?>
            </td>

            <!-- PIC & Kontak -->
            <td>
              <?php if (!empty($row['nama_client'])): ?>
              <div class="fw-semibold text-dark" style="font-size: 12.5px;">
                <i class="bi bi-person text-muted me-1"></i><?= htmlspecialchars($row['nama_client']); ?>
              </div>
              <?php else: ?>
              <span class="text-muted small">-</span>
              <?php endif; ?>

              <?php if (!empty($row['nomer_client']) || !empty($row['telp_customer'])): 
                $telp = !empty($row['nomer_client']) ? $row['nomer_client'] : $row['telp_customer'];
                $cleanTelp = preg_replace('/\D/', '', $telp);
                if (substr($cleanTelp, 0, 1) === '0') $cleanTelp = '62' . substr($cleanTelp, 1);
              ?>
              <div class="mt-1">
                <a href="https://wa.me/<?= $cleanTelp; ?>" target="_blank" class="text-success small fw-semibold text-decoration-none">
                  <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($telp); ?>
                </a>
              </div>
              <?php endif; ?>
            </td>

            <!-- Hasil & Prospek -->
            <td>
              <div>
                <span class="badge-prospek <?= $prospekClass; ?>">
                  <?= htmlspecialchars($prospek); ?>
                </span>
              </div>
              <?php if (!empty($row['no_invoice'])): ?>
              <div class="mt-1 small">
                <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 10px;">
                  <i class="bi bi-receipt me-1"></i><?= htmlspecialchars($row['no_invoice']); ?>
                </span>
              </div>
              <?php endif; ?>
              <?php if (!empty($row['catatan_visit'])): ?>
              <div class="text-muted small text-truncate mt-1" style="max-width: 170px; font-size: 11.5px;" title="<?= htmlspecialchars($row['catatan_visit']); ?>">
                "<?= htmlspecialchars($row['catatan_visit']); ?>"
              </div>
              <?php endif; ?>
            </td>

            <!-- Lokasi GPS -->
            <td>
              <?php if (!empty($latCI) && !empty($lonCI)): ?>
              <a href="https://www.google.com/maps?q=<?= htmlspecialchars($latCI); ?>,<?= htmlspecialchars($lonCI); ?>" target="_blank" class="gps-badge" title="Buka Koordinat GPS di Google Maps">
                <i class="bi bi-geo-alt-fill text-danger"></i>
                <span><?= number_format((float)$latCI, 4); ?>, <?= number_format((float)$lonCI, 4); ?></span>
              </a>
              <?php else: ?>
              <span class="text-muted small"><i class="bi bi-geo text-muted"></i> No GPS</span>
              <?php endif; ?>
            </td>

            <!-- Foto -->
            <td style="text-align: center;">
              <?php if (!empty($firstPhoto)): 
                $photoPath = (file_exists("../uploads/visit/" . $firstPhoto) ? "../uploads/visit/" : (file_exists("../uploads/customer/" . $firstPhoto) ? "../uploads/customer/" : "../uploads/" . $firstPhoto));
              ?>
              <div class="photo-thumb-wrap" onclick="viewPhotoModal('<?= htmlspecialchars($photoPath); ?>', '<?= htmlspecialchars($custName); ?>')">
                <img src="<?= htmlspecialchars($photoPath); ?>" alt="Bukti Visit" onerror="this.src='../assets/images/image-placeholder.png';">
              </div>
              <?php if (count($photos) > 1): ?>
              <div style="font-size: 10px; color:#64748b; font-weight:700;">+<?= count($photos)-1; ?> foto</div>
              <?php endif; ?>
              <?php else: ?>
              <span class="text-muted small">-</span>
              <?php endif; ?>
            </td>

            <!-- Aksi Detail -->
            <td style="text-align: center;">
              <button type="button" class="btn btn-sm btn-outline-primary rounded-circle p-2" 
                      onclick='showDetailVisit(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' 
                      title="Lihat Rincian Kunjungan">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr>
            <td colspan="9" class="text-center py-5">
              <div class="py-4">
                <div class="mb-3">
                  <i class="bi bi-geo-alt text-muted" style="font-size: 48px; opacity: 0.3;"></i>
                </div>
                <h6 class="fw-bold text-dark">Belum Ada Data Laporan Visit</h6>
                <p class="text-muted small mb-0">Tidak ada catatan kunjungan GPS yang sesuai dengan kriteria filter yang dipilih.</p>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── MODAL VIEW FOTO BESAR ─────────────────────────────────────────────── -->
<div class="modal fade" id="modalViewPhoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 overflow-hidden shadow">
      <div class="modal-header bg-dark text-white border-0 py-3">
        <h6 class="modal-title fw-bold text-white mb-0" id="photoModalTitle">Bukti Foto Visit</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 text-center bg-black">
        <img id="imgModalPreview" src="" style="max-height: 80vh; max-width: 100%; object-fit: contain;">
      </div>
    </div>
  </div>
</div>

<!-- ── MODAL RINCIAN DETAIL VISIT ───────────────────────────────────────── -->
<div class="modal fade" id="modalDetailVisit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 rounded-4 shadow">
      <div class="modal-header bg-dark text-white border-0 py-3">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-info-circle text-primary fs-5"></i>
          <h6 class="modal-title fw-bold text-white mb-0">Rincian Kunjungan Sales</h6>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="modalDetailContent">
        <!-- Dynamically Populated -->
      </div>
    </div>
  </div>
</div>

<script>
function viewPhotoModal(src, title) {
  document.getElementById('imgModalPreview').src = src;
  document.getElementById('photoModalTitle').innerText = 'Foto Kunjungan: ' + title;
  var modal = new bootstrap.Modal(document.getElementById('modalViewPhoto'));
  modal.show();
}

function showDetailVisit(data) {
  var content = `
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <div class="p-3 bg-light rounded-3 h-100">
          <div class="text-xs text-uppercase fw-bold text-muted mb-2">Informasi Toko / Pelanggan</div>
          <h5 class="fw-bold text-dark mb-1">${data.nama_customer || data.nama_client || '-'}</h5>
          <div class="text-muted small mb-2"><i class="bi bi-tag me-1"></i>${data.kategori_customer || 'Toko'} &bull; ${data.kota_customer || data.nama_wilayah || '-'}</div>
          <div class="small text-muted mb-3"><i class="bi bi-geo-alt me-1"></i>${data.alamat_customer || '-'}</div>
          
          <div class="border-top pt-2 mt-2">
            <div class="text-xs fw-bold text-muted mb-1">Kontak PIC / Toko:</div>
            <div class="fw-semibold text-dark small">${data.nama_client || '-'} (${data.nomer_client || data.telp_customer || '-'})</div>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6">
        <div class="p-3 bg-light rounded-3 h-100">
          <div class="text-xs text-uppercase fw-bold text-muted mb-2">Waktu & Sales Person</div>
          <div class="fw-bold text-dark mb-1"><i class="bi bi-person-badge text-primary me-1"></i>${data.sales_lengkap || data.nama_sales || 'Sales'}</div>
          <div class="text-muted small mb-3">NIK: ${data.sales_nik || '-'} | Telp: ${data.sales_telp || '-'}</div>
          
          <div class="border-top pt-2">
            <div class="row g-2 small">
              <div class="col-6">
                <div class="text-muted">Clock In:</div>
                <div class="fw-bold text-dark">${data.ci_at || '-'}</div>
              </div>
              <div class="col-6">
                <div class="text-muted">Clock Out:</div>
                <div class="fw-bold text-dark">${data.co_at || '-'}</div>
              </div>
            </div>
          </div>

          <div class="border-top pt-2 mt-2">
            <div class="text-muted small">Status Prospek:</div>
            <span class="badge bg-primary mt-1">${data.tipe_prospek || 'Biasa'}</span>
            ${data.no_invoice ? `<span class="badge bg-success ms-1">Inv: ${data.no_invoice}</span>` : ''}
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="p-3 bg-light rounded-3">
          <div class="text-xs text-uppercase fw-bold text-muted mb-1">Catatan Hasil Kunjungan:</div>
          <p class="text-dark small mb-0">${data.catatan_visit ? data.catatan_visit.replace(/\\n/g, '<br>') : '<em>Tidak ada catatan visit.</em>'}</p>
        </div>
      </div>

      ${(data.lat_ci && data.lon_ci) ? `
      <div class="col-12">
        <div class="p-3 border rounded-3 d-flex align-items-center justify-content-between">
          <div>
            <div class="text-xs text-uppercase fw-bold text-muted">Lokasi GPS Check-in:</div>
            <div class="fw-semibold text-dark small">${data.lat_ci}, ${data.lon_ci}</div>
          </div>
          <a href="https://www.google.com/maps?q=${data.lat_ci},${data.lon_ci}" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3">
            <i class="bi bi-map me-1"></i> Buka Google Maps
          </a>
        </div>
      </div>
      ` : ''}
    </div>
  `;

  document.getElementById('modalDetailContent').innerHTML = content;
  var modal = new bootstrap.Modal(document.getElementById('modalDetailVisit'));
  modal.show();
}
</script>