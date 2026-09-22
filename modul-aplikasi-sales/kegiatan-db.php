<?php
$current_date = date("Y-m-d");
$selectedWilayah = $_SESSION['selected_wilayah'] ?? 'all';
$selectedSales = $_SESSION['selected_sales'] ?? 'all';
$searchCustomer = $_SESSION['search_customer'] ?? '';

// ── Hitung summary per tab ─────────────────────────────────────────────────
$rescheduledExclusion = " AND ks.status NOT IN ('waiting', 'dibatalkan', 'reschedule', 'cancelled') AND (ks.reschedule_reason IS NULL OR ks.reschedule_reason = '') AND ks.id NOT IN (SELECT DISTINCT rescheduled_from FROM kegiatan_sales WHERE rescheduled_from IS NOT NULL AND deleted_at IS NULL) AND ks.id NOT IN (SELECT ks1.id FROM kegiatan_sales ks1 JOIN kegiatan_sales ks2 ON ks1.id_customer = ks2.id_customer AND ks1.id != ks2.id AND DATE(ks1.jadwal) <= '$current_date' AND DATE(ks2.jadwal) > DATE(ks1.jadwal) AND ks1.status = 'dijadwalkan' AND ks2.status = 'dijadwalkan' AND ks1.deleted_at IS NULL AND ks2.deleted_at IS NULL)";

$tab_meta = [
  'hari-ini'    => ['label'=>'Hari Ini',     'condition'=>"DATE(ks.jadwal) = '$current_date'" . $rescheduledExclusion,                                       'icon'=>'today',        'color'=>'#1e293b'],
  'akan-datang' => ['label'=>'Akan Datang',  'condition'=>"DATE(ks.jadwal) > '$current_date'" . $rescheduledExclusion,                                       'icon'=>'event',        'color'=>'#3b82f6'],
  'terlewat'    => ['label'=>'Terlewat',     'condition'=>"DATE(ks.jadwal) < '$current_date' AND ks.status != 'selesai'" . $rescheduledExclusion,            'icon'=>'event_busy',   'color'=>'#ef4444'],
  'selesai'     => ['label'=>'Selesai',      'condition'=>"ks.status = 'selesai'",                                                                           'icon'=>'task_alt',     'color'=>'#10b981'],
  'waiting'     => ['label'=>'Waiting List', 'condition'=>"ks.status = 'waiting'",                                                                           'icon'=>'hourglass_empty','color'=>'#f59e0b'],
];

$counts = [];
foreach ($tab_meta as $k => $m) {
  $queryStr = "SELECT COUNT(DISTINCT ks.id) AS c FROM kegiatan_sales ks 
               LEFT JOIN sales_customer c ON ks.id_customer = c.id ";
               
  if ($selectedSales !== 'all') {
      $queryStr .= "INNER JOIN team_kegiatan_sales tks ON ks.id = tks.id_kegiatan_sales ";
  }
  
  $queryStr .= "WHERE ks.deleted_at IS NULL AND {$m['condition']} ";
  
  if ($selectedWilayah !== 'all') {
      $queryStr .= "AND c.id_wilayah = '$selectedWilayah' ";
  }
  if ($selectedSales !== 'all') {
      $queryStr .= "AND tks.id_sales = '$selectedSales' AND tks.deleted_at IS NULL ";
  }
  if (!empty($searchCustomer)) {
      $safeSearch = mysqli_real_escape_string($conn, $searchCustomer);
      $queryStr .= "AND c.nama LIKE '%$safeSearch%' ";
  }
  
  $r = mysqli_query($conn, $queryStr);
  $counts[$k] = ($r && ($row = mysqli_fetch_assoc($r))) ? (int)$row['c'] : 0;
}
?>

<!-- ── SUMMARY STAT CARDS (Sama dengan Web Teknisi) ─────────────────────────── -->
<div class="col-12 mb-4">
  <div class="row g-3">
    <!-- Card 1: Hari Ini -->
    <div class="col-6 col-md-3">
      <div class="stat-card-premium" style="border-left: 4px solid #64748b;" onclick="document.getElementById('tab-hari-ini').click()">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label-premium">Hari Ini</p>
              <h3 class="stat-count-premium"><?php echo $counts['hari-ini']; ?></h3>
            </div>
            <div class="stat-icon-premium" style="background-color: #f1f5f9; color: #475569;">
              <span class="material-symbols-outlined" style="color: #475569;">event_available</span>
            </div>
          </div>
        </div>
        <div class="stat-footer-premium">
          <p><?php echo date('d F Y'); ?></p>
        </div>
      </div>
    </div>

    <!-- Card 2: Akan Datang -->
    <div class="col-6 col-md-3">
      <div class="stat-card-premium" style="border-left: 4px solid #3b82f6;" onclick="document.getElementById('tab-akan-datang').click()">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label-premium">Akan Datang</p>
              <h3 class="stat-count-premium"><?php echo $counts['akan-datang']; ?></h3>
            </div>
            <div class="stat-icon-premium" style="background-color: #eff6ff; color: #2563eb;">
              <span class="material-symbols-outlined" style="color: #2563eb;">event</span>
            </div>
          </div>
        </div>
        <div class="stat-footer-premium">
          <p>Jadwal Mendatang</p>
        </div>
      </div>
    </div>

    <!-- Card 3: Terlewat -->
    <div class="col-6 col-md-3">
      <div class="stat-card-premium" style="border-left: 4px solid #f43f5e;" onclick="document.getElementById('tab-terlewat').click()">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label-premium">Terlewat</p>
              <h3 class="stat-count-premium"><?php echo $counts['terlewat']; ?></h3>
            </div>
            <div class="stat-icon-premium" style="background-color: #fff1f2; color: #e11d48;">
              <span class="material-symbols-outlined" style="color: #e11d48;">warning_amber</span>
            </div>
          </div>
        </div>
        <div class="stat-footer-premium">
          <p>Perlu Tindak Lanjut</p>
        </div>
      </div>
    </div>

    <!-- Card 4: Selesai -->
    <div class="col-6 col-md-3">
      <div class="stat-card-premium" style="border-left: 4px solid #10b981;" onclick="document.getElementById('tab-selesai').click()">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="stat-label-premium">Selesai</p>
              <h3 class="stat-count-premium"><?php echo $counts['selesai']; ?></h3>
            </div>
            <div class="stat-icon-premium" style="background-color: #ecfdf5; color: #059669;">
              <span class="material-symbols-outlined" style="color: #059669;">check_circle</span>
            </div>
          </div>
        </div>
        <div class="stat-footer-premium">
          <p>Kunjungan Selesai</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── TAB NAVIGATION ─────────────────────────────────────────────────────── -->
<div class="col-12 mb-0">
  <div class="tab-pills-wrapper">
    <ul class="nav tab-pills" id="kegiatanTab" role="tablist">
      <?php $first = true; foreach ($tab_meta as $k => $m): ?>
      <li class="nav-item" role="presentation">
        <button class="tab-pill <?php echo $first ? 'active' : ''; ?>"
                id="tab-<?php echo $k; ?>"
                data-bs-toggle="tab"
                data-bs-target="#pane-<?php echo $k; ?>"
                type="button" role="tab"
                style="--accent:<?php echo $m['color']; ?>">
          <span class="material-symbols-outlined tab-icon"><?php echo $m['icon']; ?></span>
          <?php echo $m['label']; ?>
          <span class="tab-badge" style="background:<?php echo $m['color']; ?>"><?php echo $counts[$k]; ?></span>
        </button>
      </li>
      <?php $first = false; endforeach; ?>
    </ul>
  </div>
</div>

<!-- ── TAB CONTENT ────────────────────────────────────────────────────────── -->
<div class="col-12">
  <div class="tab-content" id="kegiatanTabContent">
    <?php $first = true; foreach ($tab_meta as $k => $m):
      $sql = "SELECT DISTINCT ks.*, c.nama AS nama_customer, c.telp_pribadi AS cust_nomor, c.alamat, c.id AS customer_id
              FROM kegiatan_sales ks
              LEFT JOIN sales_customer c ON ks.id_customer = c.id ";
      
      if ($selectedSales !== 'all') {
          $sql .= "INNER JOIN team_kegiatan_sales tks ON ks.id = tks.id_kegiatan_sales ";
      }
      
      $sql .= "WHERE ks.deleted_at IS NULL AND {$m['condition']} ";
      
      if ($selectedWilayah !== 'all') {
          $sql .= "AND c.id_wilayah = '$selectedWilayah' ";
      }
      
      if ($selectedSales !== 'all') {
          $sql .= "AND tks.id_sales = '$selectedSales' AND tks.deleted_at IS NULL ";
      }
      
      if (!empty($searchCustomer)) {
          $safeSearch = mysqli_real_escape_string($conn, $searchCustomer);
          $sql .= "AND c.nama LIKE '%$safeSearch%' ";
      }
      
      $sql .= "ORDER BY ks.jadwal ASC";
      $result = mysqli_query($conn, $sql);
      $borderColor = $m['color'];
    ?>
    <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>"
         id="pane-<?php echo $k; ?>" role="tabpanel">

      <!-- Section Header (Sama dengan Web Teknisi) -->
      <div class="section-header-premium">
        <h6>
          <span class="material-symbols-outlined" style="font-size: 18px; color: #fff; vertical-align: middle; margin-right: 6px;"><?php echo $m['icon']; ?></span>
          Kegiatan <?php echo $m['label']; ?>
        </h6>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-light text-dark font-weight-bold" style="font-size: 11px;"><?php echo $counts[$k]; ?> Kunjungan</span>
        </div>
      </div>

      <!-- Inline Filter Bar (Customer + Tanggal) -->
      <div class="inline-search-bar">
        <div class="inline-filter-row">
          <!-- Search Customer -->
          <div class="inline-search-wrapper">
            <span class="material-symbols-outlined inline-search-icon">search</span>
            <input type="text" class="inline-search-input" placeholder="Cari nama customer..." data-tab="<?php echo $k; ?>" oninput="applyInlineFilters('<?php echo $k; ?>')">
            <button type="button" class="inline-search-clear" data-clear="name" data-tab="<?php echo $k; ?>" onclick="clearField(this, 'name')" style="display:none;">
              <span class="material-symbols-outlined" style="font-size:16px;">close</span>
            </button>
          </div>
          <!-- Filter Tanggal (tidak untuk tab Hari Ini) -->
          <?php if ($k !== 'hari-ini'): ?>
          <div class="inline-date-wrapper">
            <span class="material-symbols-outlined inline-date-icon">calendar_month</span>
            <input type="date" class="inline-date-input" data-tab="<?php echo $k; ?>" onchange="applyInlineFilters('<?php echo $k; ?>')">
            <button type="button" class="inline-search-clear" data-clear="date" data-tab="<?php echo $k; ?>" onclick="clearField(this, 'date')" style="display:none;">
              <span class="material-symbols-outlined" style="font-size:16px;">close</span>
            </button>
          </div>
          <?php endif; ?>
          <!-- Result Count -->
          <span class="inline-search-count" data-tab-count="<?php echo $k; ?>"></span>
        </div>
      </div>

      <div class="keg-list-container">
        <?php if (mysqli_num_rows($result) > 0): ?>

          <!-- Desktop header -->
          <div class="keg-header d-none d-md-grid">
            <div>Jadwal</div>
            <div>Customer</div>
            <div>Sales &amp; Status</div>
            <div>Alamat</div>
            <div class="text-center">Aksi</div>
          </div>

          <?php while ($row = mysqli_fetch_assoc($result)):
            $kegiatanId = $row['id'];
            $jadwal     = date("d M Y H:i", strtotime($row['jadwal']));
            $telp       = $row['cust_nomor'] ?? '';
            if ($telp && substr($telp, 0, 1) === '0') $telp = '62' . substr($telp, 1);

            // Ambil sales
            $salesList = [];
            $sqlSales  = "SELECT s.nama AS nama_sales, s.foto AS foto_sales, ps.status AS status_pelaksanaan, ps.ci_at, ps.co_at, ps.lat_ci, ps.lon_ci, ps.lat_co, ps.lon_co, ps.tipe_prospek, ps.no_invoice
                          FROM team_kegiatan_sales tks
                          LEFT JOIN sales s ON tks.id_sales = s.id
                          LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = tks.id_kegiatan_sales AND ps.sales_id = tks.id_sales
                          WHERE tks.id_kegiatan_sales = '$kegiatanId' AND tks.deleted_at IS NULL";
            $resSales  = mysqli_query($conn, $sqlSales);
            while ($s = mysqli_fetch_assoc($resSales)) {
              $st   = strtolower($s['status_pelaksanaan'] ?? 'dijadwalkan');
              $cls  = match($st) { 'berjalan'=>'status-running','selesai'=>'status-done','dibatalkan'=>'status-cancelled', default=>'status-scheduled' };
              $lbl  = match($st) { 'berjalan'=>'Berjalan','selesai'=>'Selesai','dibatalkan'=>'Dibatalkan', default=>'Dijadwalkan' };
              $ci_time = !empty($s['ci_at']) ? date('H:i', strtotime($s['ci_at'])) : '';
              $co_time = !empty($s['co_at']) ? date('H:i', strtotime($s['co_at'])) : '';
              
              $salesList[] = [
                'nama' => $s['nama_sales'],
                'foto' => $s['foto_sales'],
                'cls' => $cls,
                'lbl' => $lbl,
                'ci_time' => $ci_time,
                'co_time' => $co_time,
                'lat_ci' => $s['lat_ci'] ?? '',
                'lon_ci' => $s['lon_ci'] ?? '',
                'lat_co' => $s['lat_co'] ?? '',
                'lon_co' => $s['lon_co'] ?? '',
                'tipe_prospek' => $s['tipe_prospek'] ?? 'Biasa',
                'no_invoice' => $s['no_invoice'] ?? ''
              ];
            }
            $kegStatus = strtolower($row['status'] ?? 'dijadwalkan');
            $rowAccent = ($kegStatus === 'dibatalkan') ? '#ef4444' : $borderColor;
          ?>

          <div class="keg-row" style="--row-accent:<?php echo $rowAccent; ?>" data-customer="<?php echo strtolower(htmlspecialchars($row['nama_customer'] ?? '')); ?>" data-date="<?php echo date('Y-m-d', strtotime($row['jadwal'])); ?>">
            <!-- Jadwal -->
            <div class="keg-cell">
              <span class="cell-label d-md-none">Jadwal</span>
              <div class="jadwal-badge">
                <span class="material-symbols-outlined" style="font-size:16px;color:<?php echo $borderColor;?>">schedule</span>
                <?php echo $jadwal; ?> WIB
              </div>
            </div>

            <!-- Customer -->
            <div class="keg-cell">
              <span class="cell-label d-md-none">Customer</span>
              <div class="customer-name">
                <a href="customer-detail.php?id_cust=<?php echo $row['customer_id']; ?>" class="cust-link">
                  <?php echo htmlspecialchars($row['nama_customer'] ?? '-'); ?>
                </a>
              </div>
              <?php if ($telp): ?>
              <a href="https://api.whatsapp.com/send?phone=<?php echo $telp;?>" target="_blank" class="wa-link">
                <i class="fab fa-whatsapp"></i> <?php echo $row['cust_nomor']; ?>
              </a>
              <?php endif; ?>
              <?php if ($kegStatus === 'dibatalkan' && !empty($row['reschedule_reason'])): ?>
                <div style="font-size:10px; color:#ef4444; font-weight:600; margin-top:4px;">
                  🔁 Dijadwalkan Ulang: "<?php echo htmlspecialchars($row['reschedule_reason']); ?>"
                </div>
              <?php endif; ?>
            </div>

            <!-- Sales -->
            <div class="keg-cell">
              <span class="cell-label d-md-none">Sales</span>
              <?php if (count($salesList) > 0): ?>
                <?php foreach ($salesList as $sl): ?>
                <div class="sales-item d-flex align-items-center gap-2 mb-2">
                  <?php if (!empty($sl['foto'])): ?>
                    <img src="https://api-teknisi.id-giti.com/storage/profile/<?php echo htmlspecialchars($sl['foto']); ?>" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1.5px solid <?php echo $borderColor; ?>;">
                  <?php else: ?>
                    <div class="avatar-initials" style="background-color: <?php echo $borderColor; ?>;">
                      <?php 
                        $words = explode(' ', $sl['nama']);
                        echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                      ?>
                    </div>
                  <?php endif; ?>
                  <div class="d-flex flex-column">
                    <span class="sales-name">
                      <?php echo htmlspecialchars($sl['nama']); ?>
                      <?php if (!empty($sl['tipe_prospek']) && $sl['tipe_prospek'] !== 'Biasa'): 
                        $pColor = match($sl['tipe_prospek']) { 'Peluang'=>'#10b981', 'Menengah'=>'#f59e0b', 'Rumit'=>'#ef4444', default=>'#64748b' };
                        $pBg = match($sl['tipe_prospek']) { 'Peluang'=>'#d1fae5', 'Menengah'=>'#fef3c7', 'Rumit'=>'#fee2e2', default=>'#f1f5f9' };
                      ?>
                        <span style="font-size:8px; font-weight:bold; color:<?php echo $pColor; ?>; background:<?php echo $pBg; ?>; padding: 1px 5px; border-radius: 8px; margin-left: 4px; display:inline-block; vertical-align:middle;"><?php echo $sl['tipe_prospek']; ?></span>
                      <?php endif; ?>
                    </span>
                    <div class="d-flex align-items-center gap-1 flex-wrap mt-1">
                      <span class="sales-status-badge <?php echo $sl['cls']; ?>" style="<?php echo ($sl['cls'] === 'status-cancelled') ? 'background:#fee2e2;color:#ef4444;border-color:#fca5a5;' : ''; ?>"><?php echo $sl['lbl']; ?></span>
                      
                      <!-- Always Show Clock In -->
                      <span class="badge bg-light <?php echo !empty($sl['ci_time']) ? 'text-success' : 'text-muted'; ?> font-weight-bold" style="font-size: 9px; padding: 2px 6px; border: 1px solid <?php echo !empty($sl['ci_time']) ? '#d1fae5' : '#e2e8f0'; ?>; border-radius: 4px; font-family: monospace; text-transform: uppercase; display: inline-flex; align-items: center; gap: 2px;" title="Jam Clock In">
                        📥 IN: <?php echo !empty($sl['ci_time']) ? $sl['ci_time'] : '--:--'; ?>
                        <?php if (!empty($sl['lat_ci']) && !empty($sl['lon_ci'])): ?>
                          <a href="https://www.google.com/maps?q=<?php echo $sl['lat_ci']; ?>,<?php echo $sl['lon_ci']; ?>" target="_blank" style="text-decoration: none; font-size:10px; line-height:1;" title="Lokasi Clock In">📍</a>
                        <?php endif; ?>
                      </span>
                      
                      <!-- Always Show Clock Out -->
                      <span class="badge bg-light <?php echo !empty($sl['co_time']) ? 'text-danger' : 'text-muted'; ?> font-weight-bold" style="font-size: 9px; padding: 2px 6px; border: 1px solid <?php echo !empty($sl['co_time']) ? '#fee2e2' : '#e2e8f0'; ?>; border-radius: 4px; font-family: monospace; text-transform: uppercase; display: inline-flex; align-items: center; gap: 2px;" title="Jam Clock Out">
                        📤 OUT: <?php echo !empty($sl['co_time']) ? $sl['co_time'] : '--:--'; ?>
                        <?php if (!empty($sl['lat_co']) && !empty($sl['lon_co'])): ?>
                          <a href="https://www.google.com/maps?q=<?php echo $sl['lat_co']; ?>,<?php echo $sl['lon_co']; ?>" target="_blank" style="text-decoration: none; font-size:10px; line-height:1;" title="Lokasi Clock Out">📍</a>
                        <?php endif; ?>
                      </span>

                      <!-- Invoice tag if present -->
                      <?php if (!empty($sl['no_invoice'])): ?>
                        <span class="badge bg-light text-success font-weight-bold" style="font-size: 9px; padding: 2px 6px; border: 1px solid #10b98140; border-radius: 4px; font-family: monospace; display: inline-flex; align-items: center; gap: 2px;" title="Nomor Invoice Penjualan">
                          📄 INV: <?php echo htmlspecialchars($sl['no_invoice']); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-muted" style="font-size:12px">Belum ada sales</span>
              <?php endif; ?>
            </div>

            <!-- Alamat -->
            <div class="keg-cell">
              <span class="cell-label d-md-none">Alamat</span>
              <div class="alamat-text">
                <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle; margin-right: 2px;">location_on</span>
                <?php echo htmlspecialchars($row['alamat'] ?? '-'); ?>
              </div>
            </div>

            <!-- Aksi -->
            <div class="keg-cell keg-actions">
              <?php if ($kegStatus === 'waiting'): ?>
              <button type="button" class="btn-action" title="Setujui Reschedule" style="background:#e8f5e9; color:#2e7d32; border:1.5px solid #a5d6a7; cursor:pointer;" onclick="confirmApprove(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['nama_customer'] ?? '')); ?>')">
                <span class="material-symbols-outlined">check_circle</span>
              </button>
              <?php endif; ?>
              <a href="detail_kegiatan.php?id=<?php echo $row['id']; ?>" class="btn-action btn-view" title="Lihat Detail">
                <span class="material-symbols-outlined">visibility</span>
              </a>
              <?php if ($kegStatus !== 'selesai'): ?>
              <button type="button" class="btn-action" title="Jadwalkan Ulang" style="background:#eff6ff; color:#2563eb; border:1.5px solid #bfdbfe; cursor:pointer;" onclick="openRescheduleModal(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['nama_customer'] ?? '')); ?>', '<?php echo date('Y-m-d\TH:i', strtotime($row['jadwal'])); ?>')">
                <span class="material-symbols-outlined">event_repeat</span>
              </button>
              <?php endif; ?>
              <a href="edit_kegiatan.php?id=<?php echo $row['id']; ?>" class="btn-action btn-edit" title="Edit Jadwal">
                <span class="material-symbols-outlined">edit</span>
              </a>
              <button type="button" class="btn-action btn-delete" title="Hapus" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['nama_customer'] ?? '')); ?>')">
                <span class="material-symbols-outlined">delete</span>
              </button>
            </div>
          </div>

          <?php endwhile; ?>

        <?php else: ?>
          <div class="empty-state-premium" style="--accent: <?php echo $borderColor; ?>;">
            <div class="empty-icon-wrapper">
              <span class="material-symbols-outlined empty-icon-pulsing"><?php echo $m['icon']; ?></span>
            </div>
            <h5 class="empty-title mt-3">Tidak Ada Kegiatan</h5>
            <p class="empty-sub text-muted">Belum ada jadwal kunjungan untuk kategori <strong>"<?php echo $m['label']; ?>"</strong></p>
          </div>
        <?php endif; ?>
      </div>

    </div>
    <?php $first = false; endforeach; ?>
  </div>
</div>

<!-- ── STYLES ─────────────────────────────────────────────────────────────── -->
<style>
/* ── Premium Stat Cards (Sama dengan Web Teknisi) ── */
.stat-card-premium {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(148, 163, 184, 0.04);
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  background: #ffffff !important;
}
.stat-card-premium:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(148, 163, 184, 0.1) !important;
}
.stat-label-premium {
  font-size: 11px;
  font-weight: 700;
  color: #64748b !important;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin: 0 0 6px 0;
}
.stat-count-premium {
  font-size: 30px;
  font-weight: 800;
  color: #0f172a !important;
  margin: 0;
  line-height: 1;
}
.stat-icon-premium {
  width: 42px; height: 42px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  transition: transform 0.25s;
}
.stat-card-premium:hover .stat-icon-premium {
  transform: scale(1.1) rotate(5deg);
}
.stat-icon-premium .material-symbols-outlined {
  font-size: 22px;
}
.stat-footer-premium {
  padding: 8px 16px;
  background: #f8fafc;
  border-top: 1px solid #f1f5f9;
}
.stat-footer-premium p {
  font-size: 11px;
  color: #64748b !important;
  margin: 0;
  font-weight: 600;
}

/* ── Section Header Premium (Sama dengan Web Teknisi) ── */
.section-header-premium {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px; background: #1e293b; border-radius: 10px 10px 0 0;
  transition: background 0.2s;
  margin-top: 15px;
}
.section-header-premium h6 { 
  margin: 0; font-size: 13px; font-weight: 700; color: #fff; 
  letter-spacing: 0.04em; text-transform: uppercase;
  display: flex; align-items: center;
}

/* ── Tab Pills ── */
.tab-pills-wrapper {
  background: #f8fafc;
  border-radius: 16px 16px 0 0;
  padding: 12px 14px 0;
  border: 1px solid #e2e8f0;
  border-bottom: none;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.tab-pills-wrapper::-webkit-scrollbar { display: none; }
.tab-pills { gap: 6px; flex-wrap: nowrap; border-bottom: none; }
.tab-pill {
  display: flex; align-items: center; gap: 6px;
  padding: 10px 18px;
  border-radius: 12px 12px 0 0;
  border: none;
  background: transparent;
  color: #64748b;
  font-size: 13.5px; font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  white-space: nowrap;
  flex-shrink: 0;
  touch-action: manipulation !important;
}
.tab-pill:hover { background: rgba(0,0,0,0.03); color: #1e293b; }
.tab-pill.active {
  background: #fff;
  color: var(--accent);
  box-shadow: 0 -4px 12px rgba(0,0,0,0.04);
}
.tab-icon { font-size: 18px; }
.tab-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 22px; height: 22px; padding: 0 8px;
  border-radius: 20px;
  color: #fff;
  font-size: 11px; font-weight: 700;
  margin-left: 4px;
}

/* ── Kegiatan List Pane ── */
.tab-pane { 
  background: transparent !important; 
  box-shadow: none !important; 
  border: none !important;
}
.keg-list-container {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-top: none;
  border-radius: 0 0 12px 12px;
  padding: 20px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.02);
}

.keg-header {
  display: grid;
  grid-template-columns: 140px 1.2fr 1.2fr 1.5fr 110px;
  gap: 16px;
  padding: 12px 16px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px;
  margin-bottom: 10px;
}
.keg-row {
  display: grid;
  grid-template-columns: 140px 1.2fr 1.2fr 1.5fr 110px;
  gap: 16px;
  padding: 18px 20px;
  background: #fff;
  border-radius: 12px;
  margin-bottom: 12px;
  border: 1px solid #e2e8f0;
  border-left: 5px solid var(--row-accent, #3b82f6);
  transition: all 0.22s ease;
  align-items: center;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
}
.keg-row:hover { 
  background: #fff; 
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
  border-color: var(--row-accent);
}
.keg-cell { padding: 0; }
.cell-label { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px; }

.jadwal-badge { 
  display: inline-flex; 
  align-items: center; 
  gap: 6px; 
  font-size: 13px; 
  color: #334155; 
  font-weight: 600; 
  background: #f8fafc;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.customer-name { font-size: 14px; font-weight: 700; color: #1e293b; }
.cust-link { color: #1e293b; text-decoration: none; transition: color 0.15s; }
.cust-link:hover { color: #3b82f6; }
.wa-link { font-size: 12px; color: #10b981; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; font-weight: 500; }
.wa-link:hover { text-decoration: underline; }

/* Sales item style */
.avatar-initials {
  width: 32px; height: 32px;
  border-radius: 50%;
  color: #fff;
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.sales-name { font-size: 13px; font-weight: 600; color: #1e293b; }
.sales-status-badge { 
  font-size: 10px; 
  font-weight: 700; 
  padding: 2px 8px; 
  border-radius: 12px; 
  white-space: nowrap; 
  width: fit-content;
}
.sales-status-badge.status-scheduled { background: #f1f5f9; color: #64748b; }
.sales-status-badge.status-running { background: #dbeafe; color: #2563eb; }
.sales-status-badge.status-done { background: #d1fae5; color: #059669; }

.alamat-text { font-size: 13px; color: #475569; line-height: 1.5; font-weight: 400; }

.keg-actions { display: flex; gap: 8px; align-items: center; justify-content: center; }
.btn-action {
  width: 36px; height: 36px; border-radius: 10px;
  display: inline-flex; align-items: center; justify-content: center;
  text-decoration: none; transition: all 0.2s;
  border: 1px solid transparent;
}
.btn-action:hover { transform: scale(1.08); }
.btn-action .material-symbols-outlined { font-size: 18px; }
.btn-view { background: #eff6ff; color: #2563eb; border-color: #dbeafe; }
.btn-view:hover { background: #2563eb; color: #fff; }
.btn-edit { background: #ecfdf5; color: #059669; border-color: #d1fae5; }
.btn-edit:hover { background: #059669; color: #fff; }
.btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; cursor: pointer; }
.btn-delete:hover { background: #dc2626; color: #fff; }

/* ── Delete Confirmation Modal ── */
.modal-overlay-delete {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  z-index: 9999;
  justify-content: center; align-items: center;
}
.modal-overlay-delete.active { display: flex; }
.modal-card-delete {
  background: #fff;
  border-radius: 20px;
  padding: 36px;
  width: 90%; max-width: 420px;
  text-align: center;
  box-shadow: 0 25px 60px rgba(0,0,0,0.15);
  animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes modalSlideIn {
  from { opacity: 0; transform: scale(0.85) translateY(20px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-icon-delete {
  width: 64px; height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #fef2f2, #fecaca);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px;
}
.modal-icon-delete span { font-size: 30px; color: #dc2626; }
.modal-title-delete { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
.modal-desc-delete { font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 28px; }
.modal-desc-delete strong { color: #dc2626; }
.modal-actions-delete { display: flex; gap: 12px; justify-content: center; }
.modal-btn-cancel {
  padding: 12px 28px; border-radius: 12px;
  background: #f1f5f9; color: #475569;
  border: 1.5px solid #e2e8f0;
  font-weight: 600; font-size: 14px;
  cursor: pointer; transition: all 0.2s;
}
.modal-btn-cancel:hover { background: #e2e8f0; }
.modal-btn-confirm-delete {
  padding: 12px 28px; border-radius: 12px;
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  color: #fff; border: none;
  font-weight: 700; font-size: 14px;
  cursor: pointer; transition: all 0.2s;
  box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3);
}
.modal-btn-confirm-delete:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(220,38,38,0.4); }

/* ── Premium Empty State ── */
.empty-state-premium { 
  text-align: center; 
  padding: 60px 40px; 
  background: #fff; 
  border-radius: 16px; 
  border: 2px dashed #e2e8f0;
  max-width: 480px;
  margin: 30px auto;
}
.empty-icon-wrapper {
  width: 80px; height: 80px;
  margin: 0 auto;
  background: color-mix(in srgb, var(--accent) 10%, transparent);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.empty-icon-pulsing {
  font-size: 38px;
  color: var(--accent);
  animation: pulse 2s infinite ease-in-out;
}
.empty-title { font-size: 16px; font-weight: 700; color: #334155; }
.empty-sub { font-size: 13px; color: #64748b; margin-top: 6px; }

@keyframes pulse {
  0% { transform: scale(1); opacity: 0.8; }
  50% { transform: scale(1.08); opacity: 1; }
  100% { transform: scale(1); opacity: 0.8; }
}

/* ── Mobile Responsive ── */
@media (max-width: 767px) {
  .keg-header { display: none; }
  .keg-row {
    display: flex; flex-direction: column; gap: 12px;
    border-left-width: 5px;
    padding: 16px 16px;
    border-radius: 14px;
    align-items: stretch;
  }
  .keg-cell { padding: 0; }
  .keg-actions { 
    justify-content: flex-start; 
    margin-top: 8px; 
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 10px !important;
  }
  .btn-action {
    min-width: 40px !important;
    min-height: 40px !important;
    border-radius: 10px !important;
    touch-action: manipulation !important;
  }
  .btn-action .material-symbols-outlined {
    font-size: 20px !important;
  }
  .stat-count-premium { font-size: 24px; }
  .empty-state-premium { padding: 30px 16px; margin: 15px auto; }
  .inline-filter-row {
    flex-direction: column !important;
    gap: 8px !important;
    align-items: stretch !important;
  }
  .inline-search-wrapper, .inline-date-wrapper {
    width: 100% !important;
    min-width: 100% !important;
  }
}

/* ── Approve Confirmation Modal ── */
.modal-overlay-approve {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  z-index: 9999;
  justify-content: center; align-items: center;
}
.modal-overlay-approve.active { display: flex; }
.modal-card-approve {
  background: #fff;
  border-radius: 20px;
  padding: 36px;
  width: 90%; max-width: 420px;
  text-align: center;
  box-shadow: 0 25px 60px rgba(0,0,0,0.15);
  animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-icon-approve {
  width: 64px; height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px;
}
.modal-icon-approve span { font-size: 30px; color: #2e7d32; }
.modal-title-approve { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
.modal-desc-approve { font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 28px; }
.modal-desc-approve strong { color: #2e7d32; }
.modal-btn-confirm-approve {
  padding: 12px 28px; border-radius: 12px;
  background: linear-gradient(135deg, #2e7d32, #1b5e20);
  color: #fff; border: none;
  font-weight: 700; font-size: 14px;
  cursor: pointer; transition: all 0.2s;
  box-shadow: 0 4px 14px rgba(46, 125, 50, 0.3);
}
.modal-btn-confirm-approve:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(46,125,50,0.4); }
</style>

<!-- ═══ Approve Confirmation Modal ═══ -->
<div class="modal-overlay-approve" id="approveModal">
  <div class="modal-card-approve">
    <div class="modal-icon-approve">
      <span class="material-symbols-outlined">check_circle</span>
    </div>
    <div class="modal-title-approve">Setujui Reschedule?</div>
    <div class="modal-desc-approve">
      Jadwal reschedule untuk kunjungan ke <strong id="approveCustomerName"></strong> akan disetujui dan diaktifkan.
    </div>
    <div class="modal-actions-delete">
      <button class="modal-btn-cancel" onclick="closeApproveModal()">Batal</button>
      <button class="modal-btn-confirm-approve" id="btnConfirmApprove" onclick="executeApprove()">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">check</span>
        Ya, Setujui
      </button>
    </div>
  </div>
</div>

<!-- ═══ Delete Confirmation Modal ═══ -->
<div class="modal-overlay-delete" id="deleteModal">
  <div class="modal-card-delete">
    <div class="modal-icon-delete">
      <span class="material-symbols-outlined">delete_forever</span>
    </div>
    <div class="modal-title-delete">Hapus Kegiatan?</div>
    <div class="modal-desc-delete">
      Kegiatan kunjungan ke <strong id="deleteCustomerName"></strong> akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
    </div>
    <div class="modal-actions-delete">
      <button class="modal-btn-cancel" onclick="closeDeleteModal()">Batal</button>
      <button class="modal-btn-confirm-delete" id="btnConfirmDelete" onclick="executeDelete()">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">delete</span>
        Ya, Hapus
      </button>
    </div>
  </div>
</div>

<!-- ═══ Admin Reschedule Modal ═══ -->
<style>
.modal-overlay-resched {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: none; align-items: center; justify-content: center;
  z-index: 9999;
}
.modal-overlay-resched.active { display: flex; }
.modal-card-resched {
  background: #fff;
  border-radius: 20px;
  width: 90%; max-width: 460px;
  box-shadow: 0 25px 60px rgba(0,0,0,0.15);
  animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  overflow: hidden;
}
.resched-header {
  background: linear-gradient(135deg, #1e3a5f, #2563eb);
  padding: 24px 28px;
  color: #fff;
  position: relative;
  overflow: hidden;
}
.resched-header::before {
  content: '';
  position: absolute;
  top: -30px; right: -10px;
  width: 100px; height: 100px;
  border-radius: 50%;
  background: rgba(255,255,255,0.06);
}
.resched-header h4 {
  font-size: 17px; font-weight: 800; margin: 0 0 4px;
  display: flex; align-items: center; gap: 8px;
}
.resched-header p {
  font-size: 12px; color: rgba(255,255,255,0.7); margin: 0;
}
.resched-body {
  padding: 24px 28px;
}
.resched-label {
  font-size: 11px; font-weight: 700; color: #475569;
  text-transform: uppercase; letter-spacing: 0.05em;
  margin-bottom: 6px; display: flex; align-items: center; gap: 4px;
}
.resched-input {
  width: 100%; padding: 10px 14px;
  border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 13px; font-weight: 500; color: #1e293b;
  background: #f8fafc; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  font-family: inherit;
}
.resched-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  background: #fff;
}
textarea.resched-input {
  resize: vertical; min-height: 70px;
}
.resched-footer {
  padding: 0 28px 24px;
  display: flex; gap: 10px; justify-content: flex-end;
}
.resched-btn-cancel {
  padding: 10px 20px; border-radius: 10px;
  background: #f1f5f9; color: #475569;
  border: 1.5px solid #e2e8f0;
  font-weight: 700; font-size: 13px;
  cursor: pointer; transition: all 0.2s;
}
.resched-btn-cancel:hover { background: #e2e8f0; }
.resched-btn-submit {
  padding: 10px 24px; border-radius: 10px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff; border: none;
  font-weight: 700; font-size: 13px;
  cursor: pointer; transition: all 0.2s;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
  display: flex; align-items: center; gap: 6px;
}
.resched-btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,0.4); }
.resched-btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.resched-customer-badge {
  display: inline-block;
  background: rgba(255,255,255,0.15);
  padding: 3px 12px; border-radius: 20px;
  font-size: 11px; font-weight: 700;
  margin-top: 6px;
  border: 1px solid rgba(255,255,255,0.1);
}
</style>

<div class="modal-overlay-resched" id="reschedModal">
  <div class="modal-card-resched">
    <div class="resched-header">
      <h4>
        <span class="material-symbols-outlined" style="font-size:22px;">event_repeat</span>
        Jadwalkan Ulang
      </h4>
      <p>Buat jadwal baru untuk kunjungan ini</p>
      <div class="resched-customer-badge" id="reschedCustomerBadge"></div>
    </div>
    <div class="resched-body">
      <div style="margin-bottom: 16px;">
        <div class="resched-label">
          <span class="material-symbols-outlined" style="font-size:14px; color:#3b82f6;">calendar_month</span>
          Tanggal & Waktu Baru <span style="color:#ef4444;">*</span>
        </div>
        <input type="datetime-local" class="resched-input" id="reschedNewDate" required>
      </div>
      <div>
        <div class="resched-label">
          <span class="material-symbols-outlined" style="font-size:14px; color:#3b82f6;">edit_note</span>
          Alasan Reschedule (Opsional)
        </div>
        <textarea class="resched-input" id="reschedReason" placeholder="Contoh: Customer minta ganti hari, lokasi belum siap, dll."></textarea>
      </div>
    </div>
    <div class="resched-footer">
      <button class="resched-btn-cancel" onclick="closeReschedModal()">Batal</button>
      <button class="resched-btn-submit" id="btnExecResched" onclick="executeReschedule()">
        <span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span>
        Jadwalkan Ulang
      </button>
    </div>
  </div>
</div>

<script>
let deleteId = null;

function confirmDelete(id, customerName) {
  deleteId = id;
  document.getElementById('deleteCustomerName').textContent = customerName || 'customer ini';
  document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('active');
  deleteId = null;
}

// Close modal on overlay click
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});

function executeDelete() {
  if (!deleteId) return;
  
  const btn = document.getElementById('btnConfirmDelete');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;animation:spin 1s linear infinite;">progress_activity</span> Menghapus...';

  fetch('hapus_kegiatan_sales.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'id=' + deleteId
  })
  .then(res => res.text())
  .then(result => {
    if (result.trim() === 'success') {
      closeDeleteModal();
      window.location.reload();
    } else {
      alert('Gagal menghapus kegiatan. Silakan coba lagi.');
      btn.disabled = false;
      btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">delete</span> Ya, Hapus';
    }
  })
  .catch(() => {
    alert('Terjadi kesalahan jaringan.');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">delete</span> Ya, Hapus';
  });
}

let approveId = null;

function confirmApprove(id, customerName) {
  approveId = id;
  document.getElementById('approveCustomerName').textContent = customerName || 'customer ini';
  document.getElementById('approveModal').classList.add('active');
}

function closeApproveModal() {
  document.getElementById('approveModal').classList.remove('active');
  approveId = null;
}

// Close modal on overlay click
document.getElementById('approveModal').addEventListener('click', function(e) {
  if (e.target === this) closeApproveModal();
});

function executeApprove() {
  if (!approveId) return;
  
  const btn = document.getElementById('btnConfirmApprove');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;animation:spin 1s linear infinite;">progress_activity</span> Menyetujui...';

  window.location.href = 'approve_kegiatan.php?id=' + approveId;
}
</script>

<!-- ═══ Admin Reschedule Script ═══ -->
<script>
let reschedId = null;

function openRescheduleModal(id, customerName, currentDate) {
  reschedId = id;
  document.getElementById('reschedCustomerBadge').textContent = '\ud83d\udccd ' + (customerName || 'Customer');
  const dt = new Date(currentDate);
  dt.setDate(dt.getDate() + 1);
  const y = dt.getFullYear();
  const m = String(dt.getMonth() + 1).padStart(2, '0');
  const d = String(dt.getDate()).padStart(2, '0');
  const h = String(dt.getHours()).padStart(2, '0');
  const min = String(dt.getMinutes()).padStart(2, '0');
  document.getElementById('reschedNewDate').value = y + '-' + m + '-' + d + 'T' + h + ':' + min;
  document.getElementById('reschedReason').value = '';
  document.getElementById('reschedModal').classList.add('active');
}

function closeReschedModal() {
  document.getElementById('reschedModal').classList.remove('active');
  reschedId = null;
}

document.getElementById('reschedModal').addEventListener('click', function(e) {
  if (e.target === this) closeReschedModal();
});

function executeReschedule() {
  if (!reschedId) return;
  const newDate = document.getElementById('reschedNewDate').value;
  const reason = document.getElementById('reschedReason').value;

  if (!newDate) {
    alert('Silakan pilih tanggal & waktu baru!');
    return;
  }

  const btn = document.getElementById('btnExecResched');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;animation:spin 1s linear infinite;">progress_activity</span> Memproses...';

  fetch('proses_reschedule_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'kegiatan_id=' + reschedId + '&new_jadwal=' + encodeURIComponent(newDate) + '&reason=' + encodeURIComponent(reason)
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      closeReschedModal();
      window.location.reload();
    } else {
      alert(data.message || 'Gagal menjadwalkan ulang.');
      btn.disabled = false;
      btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span> Jadwalkan Ulang';
    }
  })
  .catch(() => {
    alert('Terjadi kesalahan jaringan.');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span> Jadwalkan Ulang';
  });
}
</script>

<!-- ── Inline Filter Styles ── -->
<style>
.inline-search-bar {
  padding: 12px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}
.inline-filter-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.inline-search-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 200px;
  max-width: 340px;
}
.inline-date-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 160px;
  max-width: 200px;
}
.inline-search-icon, .inline-date-icon {
  position: absolute;
  left: 12px;
  font-size: 18px;
  color: #94a3b8;
  pointer-events: none;
  transition: color 0.2s;
  z-index: 1;
}
.inline-search-input, .inline-date-input {
  width: 100%;
  padding: 9px 36px 9px 40px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
  background: #fff;
  outline: none;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  font-family: inherit;
}
.inline-search-input::placeholder {
  color: #94a3b8;
  font-weight: 400;
}
.inline-search-input:focus, .inline-date-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.inline-search-input:focus ~ .inline-search-icon,
.inline-search-input:not(:placeholder-shown) ~ .inline-search-icon {
  color: #3b82f6;
}
.inline-date-input:focus ~ .inline-date-icon {
  color: #3b82f6;
}
.inline-date-input::-webkit-calendar-picker-indicator {
  opacity: 0;
  position: absolute;
  right: 8px;
  width: 24px;
  height: 24px;
  cursor: pointer;
}
.inline-search-count {
  font-size: 10px;
  font-weight: 700;
  color: #64748b;
  background: #f1f5f9;
  padding: 3px 10px;
  border-radius: 6px;
  opacity: 0;
  transition: opacity 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
}
.inline-search-count.visible {
  opacity: 1;
}
.inline-search-clear {
  position: absolute;
  right: 6px;
  width: 24px;
  height: 24px;
  border: none;
  background: #f1f5f9;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  transition: all 0.15s;
  padding: 0;
  z-index: 1;
}
.inline-search-clear:hover {
  background: #e2e8f0;
  color: #1e293b;
}
.keg-row.filtered-hidden {
  display: none !important;
}
.keg-row.filtered-highlight {
  animation: rowHighlight 0.4s ease;
}
@keyframes rowHighlight {
  0% { background: rgba(59, 130, 246, 0.08); }
  100% { background: transparent; }
}
@media (max-width: 767px) {
  .inline-filter-row {
    flex-direction: column;
  }
  .inline-search-wrapper, .inline-date-wrapper {
    max-width: 100%;
    width: 100%;
  }
}
</style>

<!-- ── Inline Filter Script ── -->
<script>
function applyInlineFilters(tabKey) {
  const pane = document.getElementById('pane-' + tabKey);
  if (!pane) return;

  const nameInput = pane.querySelector('.inline-search-input[data-tab="' + tabKey + '"]');
  const dateInput = pane.querySelector('.inline-date-input[data-tab="' + tabKey + '"]');
  const countEl = pane.querySelector('[data-tab-count="' + tabKey + '"]');
  const nameClear = pane.querySelector('.inline-search-clear[data-clear="name"][data-tab="' + tabKey + '"]');
  const dateClear = pane.querySelector('.inline-search-clear[data-clear="date"][data-tab="' + tabKey + '"]');
  const rows = pane.querySelectorAll('.keg-row');

  const query = (nameInput ? nameInput.value.toLowerCase().trim() : '');
  const dateVal = (dateInput ? dateInput.value : '');

  // Show/hide clear buttons
  if (nameClear) nameClear.style.display = query.length > 0 ? 'flex' : 'none';
  if (dateClear) dateClear.style.display = dateVal.length > 0 ? 'flex' : 'none';

  const hasFilter = query.length > 0 || dateVal.length > 0;
  let matched = 0;
  let total = rows.length;

  rows.forEach(row => {
    const name = row.dataset.customer || '';
    const rowDate = row.dataset.date || '';

    const nameMatch = (query === '' || name.includes(query));
    const dateMatch = (dateVal === '' || rowDate === dateVal);

    if (nameMatch && dateMatch) {
      row.classList.remove('filtered-hidden');
      if (hasFilter) {
        row.classList.add('filtered-highlight');
        setTimeout(() => row.classList.remove('filtered-highlight'), 400);
      }
      matched++;
    } else {
      row.classList.add('filtered-hidden');
      row.classList.remove('filtered-highlight');
    }
  });

  // Update count badge
  if (hasFilter) {
    countEl.textContent = matched + '/' + total;
    countEl.classList.add('visible');
  } else {
    countEl.classList.remove('visible');
  }
}

function clearField(btn, fieldType) {
  const tabKey = btn.dataset.tab;
  const pane = document.getElementById('pane-' + tabKey);
  if (fieldType === 'name') {
    const input = pane.querySelector('.inline-search-input[data-tab="' + tabKey + '"]');
    input.value = '';
    input.focus();
  } else if (fieldType === 'date') {
    const input = pane.querySelector('.inline-date-input[data-tab="' + tabKey + '"]');
    input.value = '';
  }
  applyInlineFilters(tabKey);
}
</script>
