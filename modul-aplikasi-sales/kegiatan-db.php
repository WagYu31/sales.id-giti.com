<?php
$current_date = date("Y-m-d");

// Handle Filters
if (isset($_GET['filter_wilayah'])) {
    $selectedWilayah = $_GET['filter_wilayah'];
    $_SESSION['selected_wilayah'] = $selectedWilayah;
} else {
    $selectedWilayah = $_SESSION['selected_wilayah'] ?? 'all';
}

if (isset($_GET['filter_sales'])) {
    $selectedSales = $_GET['filter_sales'];
    $_SESSION['selected_sales'] = $selectedSales;
} else {
    $selectedSales = $_SESSION['selected_sales'] ?? 'all';
}

if (isset($_GET['search_customer'])) {
    $searchCustomer = trim($_GET['search_customer']);
    $_SESSION['search_customer'] = $searchCustomer;
} else {
    $searchCustomer = $_SESSION['search_customer'] ?? '';
}

if (isset($_GET['reset_filter'])) {
    $selectedWilayah = 'all';
    $selectedSales = 'all';
    $searchCustomer = '';
    unset($_SESSION['selected_wilayah'], $_SESSION['selected_sales'], $_SESSION['search_customer']);
}

// ── Hitung summary per tab ─────────────────────────────────────────────────
$rescheduledExclusion = " AND ks.status NOT IN ('waiting', 'dibatalkan', 'reschedule', 'cancelled') AND (ks.reschedule_reason IS NULL OR ks.reschedule_reason = '') AND ks.id NOT IN (SELECT DISTINCT rescheduled_from FROM kegiatan_sales WHERE rescheduled_from IS NOT NULL AND deleted_at IS NULL) AND ks.id NOT IN (SELECT ks1.id FROM kegiatan_sales ks1 JOIN kegiatan_sales ks2 ON ks1.id_customer = ks2.id_customer AND ks1.id != ks2.id AND DATE(ks1.jadwal) <= '$current_date' AND DATE(ks2.jadwal) > DATE(ks1.jadwal) AND ks1.status = 'dijadwalkan' AND ks2.status = 'dijadwalkan' AND ks1.deleted_at IS NULL AND ks2.deleted_at IS NULL)";

$tab_meta = [
  'hari-ini'    => ['label'=>'Hari Ini',     'condition'=>"DATE(ks.jadwal) = '$current_date'" . $rescheduledExclusion,                                       'icon'=>'today',          'color'=>'#2563eb', 'bg_gradient'=>'linear-gradient(135deg, #2563eb, #1d4ed8)', 'bg_soft'=>'#eff6ff', 'badge_bg'=>'#dbeafe', 'badge_text'=>'#1e40af'],
  'akan-datang' => ['label'=>'Akan Datang',  'condition'=>"DATE(ks.jadwal) > '$current_date'" . $rescheduledExclusion,                                       'icon'=>'event',          'color'=>'#0284c7', 'bg_gradient'=>'linear-gradient(135deg, #0ea5e9, #0284c7)', 'bg_soft'=>'#f0f9ff', 'badge_bg'=>'#e0f2fe', 'badge_text'=>'#0369a1'],
  'terlewat'    => ['label'=>'Terlewat',     'condition'=>"DATE(ks.jadwal) < '$current_date' AND ks.status != 'selesai'" . $rescheduledExclusion,            'icon'=>'warning_amber',  'color'=>'#e11d48', 'bg_gradient'=>'linear-gradient(135deg, #f43f5e, #be123c)', 'bg_soft'=>'#fff1f2', 'badge_bg'=>'#ffe4e6', 'badge_text'=>'#9f1239'],
  'selesai'     => ['label'=>'Selesai',      'condition'=>"ks.status = 'selesai'",                                                                           'icon'=>'task_alt',       'color'=>'#059669', 'bg_gradient'=>'linear-gradient(135deg, #10b981, #047857)', 'bg_soft'=>'#ecfdf5', 'badge_bg'=>'#d1fae5', 'badge_text'=>'#065f46'],
  'waiting'     => ['label'=>'Waiting List', 'condition'=>"ks.status = 'waiting'",                                                                           'icon'=>'hourglass_empty','color'=>'#d97706', 'bg_gradient'=>'linear-gradient(135deg, #f59e0b, #b45309)', 'bg_soft'=>'#fffbeb', 'badge_bg'=>'#fef3c7', 'badge_text'=>'#92400e'],
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

// ── 1. Data Progress & Greeting ────────────────────────────────────────────
$totalHariIni = $counts['hari-ini'] ?? 0;
$qSelesaiToday = mysqli_query($conn, "
    SELECT COUNT(DISTINCT ks.id) AS total 
    FROM kegiatan_sales ks 
    WHERE ks.deleted_at IS NULL AND DATE(ks.jadwal) = '$current_date' AND ks.status = 'selesai'
");
$selesaiHariIni = ($qSelesaiToday && ($rSt = mysqli_fetch_assoc($qSelesaiToday))) ? (int)$rSt['total'] : 0;
$progressPercent = ($totalHariIni > 0) ? min(100, round(($selesaiHariIni / $totalHariIni) * 100)) : 0;

$hour = (int)date('H');
if ($hour >= 4 && $hour < 11) {
    $greeting = "Selamat Pagi, 👋";
    $greetingSub = "Semangat beraktivitas & capai target hari ini!";
} elseif ($hour >= 11 && $hour < 15) {
    $greeting = "Selamat Siang, 👋";
    $greetingSub = "Pantau jadwal kunjungan & follow up customer.";
} elseif ($hour >= 15 && $hour < 18) {
    $greeting = "Selamat Sore, 👋";
    $greetingSub = "Review progres kunjungan & laporan harian.";
} else {
    $greeting = "Selamat Malam, 👋";
    $greetingSub = "Istirahat sejenak & siapkan jadwal esok hari.";
}

// Helper Avatar Gradient
function getSalesGradient($name) {
    $gradients = [
        'linear-gradient(135deg, #3b82f6, #1d4ed8)', // Blue
        'linear-gradient(135deg, #8b5cf6, #6d28d9)', // Violet
        'linear-gradient(135deg, #ec4899, #be185d)', // Pink
        'linear-gradient(135deg, #10b981, #047857)', // Emerald
        'linear-gradient(135deg, #f59e0b, #b45309)', // Amber
        'linear-gradient(135deg, #06b6d4, #0e7490)', // Cyan
        'linear-gradient(135deg, #6366f1, #4338ca)', // Indigo
        'linear-gradient(135deg, #f97316, #c2410c)'  // Orange
    ];
    $idx = abs(crc32($name ?? 'Sales')) % count($gradients);
    return $gradients[$idx];
}

// ── 2. Data Trend Kunjungan (7 Hari Terakhir) ──────────────────────────────
$dates7 = [];
$counts7 = [];
for ($i = 6; $i >= 0; $i--) {
    $dStr = date('Y-m-d', strtotime("-$i days"));
    $dLabel = date('d M', strtotime("-$i days"));
    $dates7[] = $dLabel;
    
    $q7 = mysqli_query($conn, "
        SELECT COUNT(DISTINCT ks.id) AS total 
        FROM kegiatan_sales ks 
        WHERE ks.deleted_at IS NULL AND DATE(ks.jadwal) = '$dStr'
    ");
    $counts7[] = ($q7 && ($r7 = mysqli_fetch_assoc($q7))) ? (int)$r7['total'] : 0;
}

// ── 3. Data Performa Kunjungan Sales ───────────────────────────────────────
$salesNames = [];
$salesVisits = [];
$qPerf = mysqli_query($conn, "
    SELECT COALESCE(s.nama_lengkap, tks.nama_sales, 'Sales') AS nama_sales, 
           COUNT(DISTINCT ks.id) AS total 
    FROM team_kegiatan_sales tks
    JOIN kegiatan_sales ks ON tks.id_kegiatan_sales = ks.id AND ks.deleted_at IS NULL
    LEFT JOIN sales s ON tks.id_sales = s.id
    WHERE tks.deleted_at IS NULL
    GROUP BY tks.id_sales, COALESCE(s.nama_lengkap, tks.nama_sales)
    ORDER BY total DESC
    LIMIT 8
");
if ($qPerf && mysqli_num_rows($qPerf) > 0) {
    while ($pRow = mysqli_fetch_assoc($qPerf)) {
        $salesNames[] = $pRow['nama_sales'];
        $salesVisits[] = (int)$pRow['total'];
    }
}
if (empty($salesNames)) {
    $salesNames = ['Edi Suprianto', 'Ecell Imoet'];
    $salesVisits = [171, 3];
}
?>

<!-- ── 1. HERO GREETING BANNER (Ultra Modern & Berwarna) ──────────────────────────── -->
<div class="col-12 mb-4">
  <div class="hero-welcome-card">
    <div class="hero-welcome-body d-flex flex-wrap align-items-center justify-content-between gap-3">
      
      <!-- Greeting & User Info -->
      <div class="d-flex align-items-center gap-3">
        <div class="hero-avatar-badge d-none d-sm-flex">
          <span class="material-symbols-outlined" style="font-size: 28px;">account_circle</span>
        </div>
        <div>
          <div class="hero-greeting-pill">
            <span><?= $greeting; ?></span>
          </div>
          <h3 class="hero-user-name">
            <?= htmlspecialchars($nmUser ?? 'Super Admin'); ?>
          </h3>
          <div class="d-flex align-items-center gap-2 flex-wrap text-secondary" style="font-size: 12.5px;">
            <span class="hero-date-pill">
              <i class="fa-regular fa-calendar text-primary me-1"></i>
              <?= formatTanggal('EEEE, d MMMM yyyy', date('Y-m-d')); ?> • <span id="heroLiveClock" class="fw-bold text-dark font-monospace"><?= date('H:i:s'); ?></span>
            </span>
            <span class="hero-live-badge">
              <span class="hero-live-dot"></span>
              LIVE
            </span>
          </div>
        </div>
      </div>

      <!-- Right Controls: Progress & Action -->
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <!-- Progress Box -->
        <div class="hero-progress-box">
          <div class="hero-progress-info">
            <div class="hero-progress-label">Progress Hari Ini •</div>
            <div class="hero-progress-val">
              <strong><?= $selesaiHariIni; ?></strong> / <?= $totalHariIni; ?> Selesai
              <span class="hero-progress-percent">(<?= $progressPercent; ?>%)</span>
            </div>
          </div>
          <div class="hero-progress-icon-wrapper">
            <span class="material-symbols-outlined" style="font-size: 18px;">check</span>
          </div>
        </div>

        <!-- Tombol Tambah Kegiatan -->
        <a href="kegiatan-baru.php" class="hero-btn-add">
          <span class="material-symbols-outlined" style="font-size: 18px;">add_circle</span>
          <span>Tambah Kegiatan</span>
        </a>
      </div>

    </div>
  </div>
</div>

<!-- ── 2. FILTER BAR (Vibrant Category Badges) ──────────────────────────────────── -->
<div class="col-12 mb-4">
  <div class="filter-card-modern">
    <div class="card-body p-3 p-md-3.5">
      <form method="GET" action="kegiatan.php" class="row g-2.5 align-items-end">
        
        <!-- Filter Wilayah -->
        <div class="col-12 col-md-3">
          <label class="filter-label">
            <span class="filter-icon-pill" style="background:#e0f2fe; color:#0284c7;">
              <i class="fa-solid fa-map-location-dot"></i>
            </span>
            Filter Wilayah
          </label>
          <select name="filter_wilayah" class="form-select filter-select">
            <option value="all" <?= ($selectedWilayah === 'all') ? 'selected' : ''; ?>>Semua Wilayah</option>
            <?php
            $qWil = mysqli_query($conn, "SELECT * FROM wilayah WHERE deleted_at IS NULL ORDER BY nama ASC");
            if ($qWil && mysqli_num_rows($qWil) > 0) {
              while ($w = mysqli_fetch_assoc($qWil)) {
                $sel = ($selectedWilayah == $w['id']) ? 'selected' : '';
                echo "<option value='{$w['id']}' $sel>" . htmlspecialchars($w['nama']) . "</option>";
              }
            }
            ?>
          </select>
        </div>

        <!-- Filter Sales -->
        <div class="col-12 col-md-3">
          <label class="filter-label">
            <span class="filter-icon-pill" style="background:#e0e7ff; color:#4f46e5;">
              <i class="fa-solid fa-user-tie"></i>
            </span>
            Filter Sales
          </label>
          <select name="filter_sales" class="form-select filter-select">
            <option value="all" <?= ($selectedSales === 'all') ? 'selected' : ''; ?>>Semua Sales</option>
            <?php
            $qSal = mysqli_query($conn, "SELECT id, nama_lengkap FROM sales WHERE deleted_at IS NULL ORDER BY nama_lengkap ASC");
            if ($qSal && mysqli_num_rows($qSal) > 0) {
              while ($s = mysqli_fetch_assoc($qSal)) {
                $sel = ($selectedSales == $s['id']) ? 'selected' : '';
                echo "<option value='{$s['id']}' $sel>" . htmlspecialchars($s['nama_lengkap']) . "</option>";
              }
            }
            ?>
          </select>
        </div>

        <!-- Nama Customer -->
        <div class="col-12 col-md-4">
          <label class="filter-label">
            <span class="filter-icon-pill" style="background:#d1fae5; color:#059669;">
              <i class="fa-solid fa-store"></i>
            </span>
            Nama Customer
          </label>
          <div class="input-group">
            <input type="text" name="search_customer" class="form-control filter-input" placeholder="Ketik nama customer..." value="<?= htmlspecialchars($searchCustomer); ?>">
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn-filter-search">
            <i class="fa-solid fa-magnifying-glass"></i> Cari
          </button>
          <a href="kegiatan.php?reset_filter=1" class="btn-filter-reset" title="Reset Semua Filter">
            <i class="fa-solid fa-rotate-right"></i> Reset
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── 3. ANALYTICS CHARTS (Vibrant Gradient Area & Bars) ────────────────────────── -->
<div class="col-12 mb-4">
  <div class="row g-3">
    <!-- Chart 1: Trend Kunjungan (7 Hari Terakhir) -->
    <div class="col-12 col-lg-7">
      <div class="chart-card-premium h-100">
        <div class="chart-card-header d-flex align-items-center justify-content-between">
          <div class="chart-title-badge trend-badge">
            <span class="material-symbols-outlined">trending_up</span>
            <span>Trend Kunjungan (7 Hari Terakhir)</span>
          </div>
          <span class="badge bg-light text-primary fw-bold" style="font-size: 11px; border: 1px solid #bfdbfe;">
            7 Hari
          </span>
        </div>
        <div class="card-body px-2 pb-2 pt-1">
          <div id="trendKunjunganChart" style="min-height: 220px;"></div>
        </div>
      </div>
    </div>

    <!-- Chart 2: Performa Kunjungan Sales -->
    <div class="col-12 col-lg-5">
      <div class="chart-card-premium h-100">
        <div class="chart-card-header d-flex align-items-center justify-content-between">
          <div class="chart-title-badge performa-badge">
            <span class="material-symbols-outlined">leaderboard</span>
            <span>Performa Kunjungan Sales</span>
          </div>
          <span class="badge bg-light text-purple fw-bold" style="font-size: 11px; border: 1px solid #e9d5ff; color: #9333ea;">
            Top Sales
          </span>
        </div>
        <div class="card-body px-2 pb-2 pt-1">
          <div id="performaSalesChart" style="min-height: 220px;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── 4. SUMMARY STAT CARDS (Ultra Colorful & Interactive) ──────────────────────── -->
<div class="col-12 mb-4">
  <div class="row g-3">
    
    <!-- Card 1: Hari Ini -->
    <div class="col-6 col-md-3">
      <div class="stat-card-vibrant stat-theme-blue" onclick="document.getElementById('tab-hari-ini').click()">
        <div class="stat-card-top d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-theme-label">Hari Ini</div>
            <h3 class="stat-theme-count"><?php echo $counts['hari-ini']; ?></h3>
          </div>
          <div class="stat-theme-icon">
            <span class="material-symbols-outlined">today</span>
          </div>
        </div>
        <div class="stat-theme-footer">
          <span class="stat-theme-dot"></span>
          <span><?php echo formatTanggal('d MMMM yyyy', date('Y-m-d')); ?></span>
        </div>
      </div>
    </div>

    <!-- Card 2: Akan Datang -->
    <div class="col-6 col-md-3">
      <div class="stat-card-vibrant stat-theme-cyan" onclick="document.getElementById('tab-akan-datang').click()">
        <div class="stat-card-top d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-theme-label">Akan Datang</div>
            <h3 class="stat-theme-count"><?php echo $counts['akan-datang']; ?></h3>
          </div>
          <div class="stat-theme-icon">
            <span class="material-symbols-outlined">event</span>
          </div>
        </div>
        <div class="stat-theme-footer">
          <span class="stat-theme-dot"></span>
          <span>Jadwal Mendatang</span>
        </div>
      </div>
    </div>

    <!-- Card 3: Terlewat -->
    <div class="col-6 col-md-3">
      <div class="stat-card-vibrant stat-theme-rose" onclick="document.getElementById('tab-terlewat').click()">
        <div class="stat-card-top d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-theme-label">Terlewat</div>
            <h3 class="stat-theme-count"><?php echo $counts['terlewat']; ?></h3>
          </div>
          <div class="stat-theme-icon">
            <span class="material-symbols-outlined">warning_amber</span>
          </div>
        </div>
        <div class="stat-theme-footer">
          <span class="stat-theme-dot"></span>
          <span>Perlu Tindak Lanjut</span>
        </div>
      </div>
    </div>

    <!-- Card 4: Selesai -->
    <div class="col-6 col-md-3">
      <div class="stat-card-vibrant stat-theme-emerald" onclick="document.getElementById('tab-selesai').click()">
        <div class="stat-card-top d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-theme-label">Selesai</div>
            <h3 class="stat-theme-count"><?php echo $counts['selesai']; ?></h3>
          </div>
          <div class="stat-theme-icon">
            <span class="material-symbols-outlined">task_alt</span>
          </div>
        </div>
        <div class="stat-theme-footer">
          <span class="stat-theme-dot"></span>
          <span>Kunjungan Selesai</span>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ── 5. TAB NAVIGATION (Vibrant Segments) ─────────────────────────────────────── -->
<div class="col-12 mb-0">
  <div class="tab-pills-wrapper">
    <ul class="nav tab-pills" id="kegiatanTab" role="tablist">
      <?php $first = true; foreach ($tab_meta as $k => $m): ?>
      <li class="nav-item" role="presentation">
        <button class="tab-pill tab-pill-<?= $k; ?> <?php echo $first ? 'active' : ''; ?>"
                id="tab-<?php echo $k; ?>"
                data-bs-toggle="tab"
                data-bs-target="#pane-<?php echo $k; ?>"
                type="button" role="tab"
                style="--accent:<?php echo $m['color']; ?>; --soft:<?php echo $m['bg_soft']; ?>; --badge-bg:<?php echo $m['badge_bg']; ?>; --badge-txt:<?php echo $m['badge_text']; ?>;">
          <span class="material-symbols-outlined tab-icon"><?php echo $m['icon']; ?></span>
          <span class="tab-label"><?php echo $m['label']; ?></span>
          <span class="tab-badge" style="background:<?php echo $m['color']; ?>; color:#fff;"><?php echo $counts[$k]; ?></span>
        </button>
      </li>
      <?php $first = false; endforeach; ?>
    </ul>
  </div>
</div>

<!-- ── 6. TAB CONTENT (List Kunjungan) ─────────────────────────────────────────── -->
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

      <!-- Section Header (Rich Dark Slate Banner with Gradient Glow) -->
      <div class="section-header-modern">
        <div class="d-flex align-items-center gap-2">
          <span class="section-header-icon-pill" style="background: <?php echo $m['color']; ?>22; color: <?php echo $m['color']; ?>;">
            <span class="material-symbols-outlined" style="font-size: 18px;"><?php echo $m['icon']; ?></span>
          </span>
          <h6>Kegiatan <?php echo $m['label']; ?></h6>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge rounded-pill px-3 py-1.5 font-monospace fw-bold" style="background: #334155; color: #f8fafc; font-size: 11.5px; border: 1px solid #475569;">
            <?php echo $counts[$k]; ?> Kunjungan
          </span>
        </div>
      </div>

      <!-- Inline Filter Bar (Customer + Tanggal) -->
      <div class="inline-search-bar">
        <div class="inline-filter-row">
          <!-- Search Customer -->
          <div class="inline-search-wrapper">
            <span class="material-symbols-outlined inline-search-icon">search</span>
            <input type="text" class="inline-search-input" placeholder="Cari customer di tab ini..." data-tab="<?php echo $k; ?>" oninput="applyInlineFilters('<?php echo $k; ?>')">
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
          <!-- Result Count Badge -->
          <span class="inline-search-count" data-tab-count="<?php echo $k; ?>"></span>
        </div>
      </div>

      <!-- Container List Rows -->
      <div class="keg-list-container">
        <?php if (mysqli_num_rows($result) > 0): ?>

          <!-- Desktop Table Header -->
          <div class="keg-header d-none d-md-grid">
            <div><i class="fa-regular fa-clock me-1 text-primary"></i> Jadwal</div>
            <div><i class="fa-solid fa-store me-1 text-success"></i> Customer</div>
            <div><i class="fa-solid fa-user-check me-1 text-purple" style="color:#8b5cf6;"></i> Sales &amp; Status</div>
            <div><i class="fa-solid fa-location-dot me-1 text-danger"></i> Alamat</div>
            <div class="text-center"><i class="fa-solid fa-sliders me-1 text-secondary"></i> Aksi</div>
          </div>

          <?php while ($row = mysqli_fetch_assoc($result)):
            $kegiatanId = $row['id'];
            $jadwal     = date("d M Y H:i", strtotime($row['jadwal']));
            $telp       = $row['cust_nomor'] ?? '';
            if ($telp && substr($telp, 0, 1) === '0') $telp = '62' . substr($telp, 1);

            // Ambil team & pelaksanaan sales
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
            
            <!-- 1. Jadwal -->
            <div class="keg-cell">
              <span class="cell-label d-md-none">Jadwal</span>
              <div class="jadwal-badge">
                <span class="material-symbols-outlined" style="font-size:16px;color:<?php echo $borderColor;?>">schedule</span>
                <span><?php echo $jadwal; ?> WIB</span>
              </div>
            </div>

            <!-- 2. Customer -->
            <div class="keg-cell">
              <span class="cell-label d-md-none">Customer</span>
              <div class="customer-name">
                <a href="customer-detail.php?id_cust=<?php echo $row['customer_id']; ?>" class="cust-link">
                  <?php echo htmlspecialchars($row['nama_customer'] ?? '-'); ?>
                </a>
              </div>
              <?php if ($telp): ?>
              <a href="https://api.whatsapp.com/send?phone=<?php echo $telp;?>" target="_blank" class="wa-badge-pill">
                <i class="fab fa-whatsapp" style="font-size: 13px;"></i>
                <span><?php echo $row['cust_nomor']; ?></span>
              </a>
              <?php endif; ?>
              <?php if ($kegStatus === 'dibatalkan' && !empty($row['reschedule_reason'])): ?>
                <div class="reschedule-callout">
                  🔁 Dijadwalkan Ulang: "<?php echo htmlspecialchars($row['reschedule_reason']); ?>"
                </div>
              <?php endif; ?>
            </div>

            <!-- 3. Sales & Status -->
            <div class="keg-cell">
              <span class="cell-label d-md-none">Sales &amp; Status</span>
              <?php if (count($salesList) > 0): ?>
                <?php foreach ($salesList as $sl): ?>
                <div class="sales-item d-flex align-items-center gap-2 mb-2">
                  <?php if (!empty($sl['foto'])): ?>
                    <img src="https://api-teknisi.id-giti.com/storage/profile/<?php echo htmlspecialchars($sl['foto']); ?>" class="sales-avatar-img" style="border: 2px solid <?php echo $borderColor; ?>;">
                  <?php else: ?>
                    <div class="avatar-initials-gradient" style="background: <?php echo getSalesGradient($sl['nama']); ?>;">
                      <?php 
                        $words = explode(' ', trim($sl['nama'] ?? 'Sales'));
                        echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                      ?>
                    </div>
                  <?php endif; ?>
                  
                  <div class="d-flex flex-column" style="min-width: 0;">
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                      <span class="sales-name-text">
                        <?php echo htmlspecialchars($sl['nama'] ?? 'Sales'); ?>
                      </span>
                      
                      <!-- Prospek Pill -->
                      <?php if (!empty($sl['tipe_prospek']) && $sl['tipe_prospek'] !== 'Biasa'): 
                        $pColor = match($sl['tipe_prospek']) { 'Peluang'=>'#059669', 'Menengah'=>'#d97706', 'Rumit'=>'#dc2626', default=>'#475569' };
                        $pBg = match($sl['tipe_prospek']) { 'Peluang'=>'#d1fae5', 'Menengah'=>'#fef3c7', 'Rumit'=>'#fee2e2', default=>'#f1f5f9' };
                      ?>
                        <span class="prospek-badge" style="color:<?php echo $pColor; ?>; background:<?php echo $pBg; ?>; border: 1px solid <?php echo $pColor; ?>33;">
                          <i class="fa-solid fa-sparkles me-0.5"></i> <?php echo $sl['tipe_prospek']; ?>
                        </span>
                      <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-center gap-1.5 flex-wrap mt-1">
                      <!-- Status Pill -->
                      <span class="sales-status-badge <?php echo $sl['cls']; ?>">
                        <?php if ($sl['cls'] === 'status-running'): ?>
                          <span class="pulse-dot-running"></span>
                        <?php elseif ($sl['cls'] === 'status-done'): ?>
                          <i class="fa-solid fa-check me-0.5"></i>
                        <?php endif; ?>
                        <?php echo $sl['lbl']; ?>
                      </span>
                      
                      <!-- Clock In Badge -->
                      <span class="clock-badge <?= !empty($sl['ci_time']) ? 'clock-in-active' : 'clock-empty'; ?>" title="Jam Clock In">
                        📥 IN: <?= !empty($sl['ci_time']) ? $sl['ci_time'] : '--:--'; ?>
                        <?php if (!empty($sl['lat_ci']) && !empty($sl['lon_ci'])): ?>
                          <a href="https://www.google.com/maps?q=<?= $sl['lat_ci']; ?>,<?= $sl['lon_ci']; ?>" target="_blank" class="clock-map-link" title="Buka Lokasi Clock In di Maps">📍</a>
                        <?php endif; ?>
                      </span>
                      
                      <!-- Clock Out Badge -->
                      <span class="clock-badge <?= !empty($sl['co_time']) ? 'clock-out-active' : 'clock-empty'; ?>" title="Jam Clock Out">
                        📤 OUT: <?= !empty($sl['co_time']) ? $sl['co_time'] : '--:--'; ?>
                        <?php if (!empty($sl['lat_co']) && !empty($sl['lon_co'])): ?>
                          <a href="https://www.google.com/maps?q=<?= $sl['lat_co']; ?>,<?= $sl['lon_co']; ?>" target="_blank" class="clock-map-link" title="Buka Lokasi Clock Out di Maps">📍</a>
                        <?php endif; ?>
                      </span>

                      <!-- Invoice Tag -->
                      <?php if (!empty($sl['no_invoice'])): ?>
                        <span class="invoice-badge-pill" title="Nomor Invoice Penjualan">
                          <i class="fa-solid fa-file-invoice-dollar me-1"></i> <?= htmlspecialchars($sl['no_invoice']); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-muted" style="font-size:12px">Belum ada tim sales</span>
              <?php endif; ?>
            </div>

            <!-- 4. Alamat -->
            <div class="keg-cell">
              <span class="cell-label d-md-none">Alamat</span>
              <div class="alamat-text">
                <span class="material-symbols-outlined location-pin-icon">location_on</span>
                <span><?php echo htmlspecialchars($row['alamat'] ?? '-'); ?></span>
              </div>
            </div>

            <!-- 5. Aksi -->
            <div class="keg-cell keg-actions">
              <?php if ($kegStatus === 'waiting'): ?>
              <button type="button" class="btn-action btn-approve" title="Setujui Reschedule" onclick="confirmApprove(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['nama_customer'] ?? '')); ?>')">
                <span class="material-symbols-outlined">check_circle</span>
              </button>
              <?php endif; ?>
              
              <a href="detail_kegiatan.php?id=<?php echo $row['id']; ?>" class="btn-action btn-view" title="Lihat Detail Kunjungan">
                <span class="material-symbols-outlined">visibility</span>
              </a>

              <?php if ($kegStatus !== 'selesai'): ?>
              <button type="button" class="btn-action btn-resched" title="Jadwalkan Ulang" onclick="openRescheduleModal(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['nama_customer'] ?? '')); ?>', '<?php echo date('Y-m-d\TH:i', strtotime($row['jadwal'])); ?>')">
                <span class="material-symbols-outlined">event_repeat</span>
              </button>
              <?php endif; ?>

              <a href="edit_kegiatan.php?id=<?php echo $row['id']; ?>" class="btn-action btn-edit" title="Edit Jadwal">
                <span class="material-symbols-outlined">edit</span>
              </a>

              <button type="button" class="btn-action btn-delete" title="Hapus Kegiatan" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['nama_customer'] ?? '')); ?>')">
                <span class="material-symbols-outlined">delete</span>
              </button>
            </div>

          </div>
          <?php endwhile; ?>

        <?php else: ?>
          <!-- Empty State Vibrant -->
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

<!-- ── STYLES (Ultra Modern, Colorful & Responsive) ────────────────────────── -->
<style>
/* ── 1. Hero Greeting Banner ── */
.hero-welcome-card {
  background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
  border: 1px solid #e2e8f0;
  border-left: 5px solid #2563eb !important;
  border-radius: 16px;
  box-shadow: 0 4px 20px -2px rgba(37, 99, 235, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.04);
  padding: 20px 24px;
  position: relative;
  overflow: hidden;
}
.hero-welcome-card::before {
  content: '';
  position: absolute;
  top: -40px; right: -40px;
  width: 140px; height: 140px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(37,99,235,0.06) 0%, rgba(255,255,255,0) 70%);
  pointer-events: none;
}
.hero-avatar-badge {
  width: 48px; height: 48px;
  border-radius: 14px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: #ffffff;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
  flex-shrink: 0;
}
.hero-greeting-pill {
  font-size: 13px;
  font-weight: 700;
  color: #475569;
  display: inline-flex; align-items: center; gap: 4px;
  margin-bottom: 2px;
}
.hero-user-name {
  font-family: 'Outfit', sans-serif;
  font-weight: 800;
  font-size: 22px;
  color: #0f172a;
  margin: 0 0 4px 0;
  letter-spacing: -0.02em;
}
.hero-date-pill {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  color: #475569;
}
.hero-live-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
  padding: 2.5px 8px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
  font-family: monospace;
}
.hero-live-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: pulseGreen 1.8s infinite;
}
@keyframes pulseGreen {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.hero-progress-box {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  padding: 8px 16px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.hero-progress-label {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
}
.hero-progress-val {
  font-size: 13.5px;
  font-weight: 700;
  color: #0f172a;
}
.hero-progress-percent {
  font-size: 11.5px;
  font-weight: 800;
  color: #10b981;
}
.hero-progress-icon-wrapper {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}
.hero-btn-add {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #ffffff !important;
  font-size: 12.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 10px 18px;
  border-radius: 12px;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
  text-decoration: none;
  transition: all 0.22s ease;
}
.hero-btn-add:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
  color: #ffffff !important;
}

/* ── 2. Filter Bar Modern ── */
.filter-card-modern {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.04);
}
.filter-label {
  font-size: 10.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #475569;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}
.filter-icon-pill {
  width: 20px; height: 20px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
}
.filter-select, .filter-input {
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  font-size: 12.5px;
  font-weight: 600;
  color: #1e293b;
  padding: 7px 12px;
  transition: all 0.2s;
  background-color: #f8fafc;
}
.filter-select:focus, .filter-input:focus {
  background-color: #ffffff;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
  outline: none;
}
.btn-filter-search {
  flex-grow: 1;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 8px 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  box-shadow: 0 3px 10px rgba(37, 99, 235, 0.25);
  transition: all 0.2s;
  height: 38px;
}
.btn-filter-search:hover {
  transform: translateY(-1px);
  box-shadow: 0 5px 16px rgba(37, 99, 235, 0.35);
  color: #fff;
}
.btn-filter-reset {
  background: #f1f5f9;
  color: #475569;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 8px 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  text-decoration: none;
  transition: all 0.2s;
  height: 38px;
}
.btn-filter-reset:hover {
  background: #e2e8f0;
  color: #0f172a;
  transform: translateY(-1px);
}

/* ── 3. Analytics Charts Cards ── */
.chart-card-premium {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}
.chart-card-header {
  padding: 14px 18px 6px;
}
.chart-title-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.chart-title-badge .material-symbols-outlined { font-size: 18px; }
.trend-badge { color: #0284c7; }
.performa-badge { color: #9333ea; }

/* ── 4. Vibrant Stat Cards ── */
.stat-card-vibrant {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.03);
  cursor: pointer;
  overflow: hidden;
  position: relative;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.stat-card-vibrant:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.08);
}
.stat-card-top {
  padding: 16px 18px;
}
.stat-theme-label {
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 4px;
}
.stat-theme-count {
  font-family: 'Outfit', sans-serif;
  font-size: 28px;
  font-weight: 800;
  line-height: 1;
  margin: 0;
}
.stat-theme-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  transition: transform 0.25s;
}
.stat-card-vibrant:hover .stat-theme-icon {
  transform: scale(1.1) rotate(6deg);
}
.stat-theme-icon .material-symbols-outlined { font-size: 22px; }
.stat-theme-footer {
  padding: 7px 18px;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
  border-top: 1px solid rgba(0,0,0,0.04);
}
.stat-theme-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
}

/* Themes per Card */
.stat-theme-blue {
  border-left: 4.5px solid #2563eb !important;
  background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);
}
.stat-theme-blue .stat-theme-label { color: #2563eb; }
.stat-theme-blue .stat-theme-count { color: #1e3a8a; }
.stat-theme-blue .stat-theme-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
.stat-theme-blue .stat-theme-footer { background: #f0f7ff; color: #1e40af; }
.stat-theme-blue .stat-theme-dot { background: #2563eb; }

.stat-theme-cyan {
  border-left: 4.5px solid #0284c7 !important;
  background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
}
.stat-theme-cyan .stat-theme-label { color: #0284c7; }
.stat-theme-cyan .stat-theme-count { color: #075985; }
.stat-theme-cyan .stat-theme-icon { background: linear-gradient(135deg, #0ea5e9, #0284c7); box-shadow: 0 4px 12px rgba(2,132,199,0.3); }
.stat-theme-cyan .stat-theme-footer { background: #f0f9ff; color: #0369a1; }
.stat-theme-cyan .stat-theme-dot { background: #0ea5e9; }

.stat-theme-rose {
  border-left: 4.5px solid #e11d48 !important;
  background: linear-gradient(135deg, #ffffff 0%, #fff1f2 100%);
}
.stat-theme-rose .stat-theme-label { color: #e11d48; }
.stat-theme-rose .stat-theme-count { color: #9f1239; }
.stat-theme-rose .stat-theme-icon { background: linear-gradient(135deg, #f43f5e, #be123c); box-shadow: 0 4px 12px rgba(225,29,72,0.3); }
.stat-theme-rose .stat-theme-footer { background: #fff1f2; color: #be123c; }
.stat-theme-rose .stat-theme-dot { background: #f43f5e; }

.stat-theme-emerald {
  border-left: 4.5px solid #059669 !important;
  background: linear-gradient(135deg, #ffffff 0%, #ecfdf5 100%);
}
.stat-theme-emerald .stat-theme-label { color: #059669; }
.stat-theme-emerald .stat-theme-count { color: #065f46; }
.stat-theme-emerald .stat-theme-icon { background: linear-gradient(135deg, #10b981, #047857); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
.stat-theme-emerald .stat-theme-footer { background: #ecfdf5; color: #047857; }
.stat-theme-emerald .stat-theme-dot { background: #10b981; }

/* ── 5. Tab Navigation Bar ── */
.tab-pills-wrapper {
  background: #f8fafc;
  border-radius: 16px 16px 0 0;
  padding: 10px 14px 0;
  border: 1px solid #e2e8f0;
  border-bottom: none;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.tab-pills-wrapper::-webkit-scrollbar { display: none; }
.tab-pills { gap: 6px; flex-wrap: nowrap; border-bottom: none; }
.tab-pill {
  display: flex; align-items: center; gap: 7px;
  padding: 10px 18px;
  border-radius: 12px 12px 0 0;
  border: none;
  background: transparent;
  color: #64748b;
  font-size: 13px; font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  white-space: nowrap;
  flex-shrink: 0;
  touch-action: manipulation !important;
}
.tab-pill:hover { background: rgba(0,0,0,0.03); color: #1e293b; }
.tab-pill.active {
  background: #ffffff;
  color: var(--accent) !important;
  box-shadow: 0 -4px 16px rgba(0,0,0,0.05);
}
.tab-pill.active::after {
  content: '';
  position: absolute;
  bottom: 0; left: 14px; right: 14px;
  height: 3px;
  background: var(--accent);
  border-radius: 3px 3px 0 0;
}
.tab-icon { font-size: 18px; }
.tab-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 22px; height: 22px; padding: 0 8px;
  border-radius: 20px;
  font-size: 11px; font-weight: 800;
  margin-left: 2px;
}

/* ── 6. Section Header Modern ── */
.section-header-modern {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  border-radius: 10px 10px 0 0;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
  margin-top: 15px;
}
.section-header-modern h6 { 
  margin: 0; font-size: 13px; font-weight: 800; color: #ffffff; 
  letter-spacing: 0.04em; text-transform: uppercase;
}
.section-header-icon-pill {
  width: 28px; height: 28px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
}

/* ── 7. Kegiatan List & Rows ── */
.tab-pane { 
  background: transparent !important; 
  box-shadow: none !important; 
  border: none !important; 
}
.keg-list-container {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-top: none;
  border-radius: 0 0 14px 14px;
  padding: 20px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.02);
}
.keg-header {
  display: grid;
  grid-template-columns: 145px 1.25fr 1.35fr 1.4fr 115px;
  gap: 16px;
  padding: 12px 18px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 11.5px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: .5px;
  margin-bottom: 12px;
}
.keg-row {
  display: grid;
  grid-template-columns: 145px 1.25fr 1.35fr 1.4fr 115px;
  gap: 16px;
  padding: 16px 20px;
  background: #ffffff;
  border-radius: 14px;
  margin-bottom: 12px;
  border: 1px solid #e2e8f0;
  border-left: 5px solid var(--row-accent, #3b82f6);
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  align-items: center;
  box-shadow: 0 2px 8px -1px rgba(0, 0, 0, 0.03);
}
.keg-row:hover { 
  background: #ffffff; 
  transform: translateY(-2px);
  box-shadow: 0 10px 24px -2px rgba(0, 0, 0, 0.08);
  border-color: var(--row-accent);
}
.keg-cell { padding: 0; }
.cell-label { display: block; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px; }

.jadwal-badge { 
  display: inline-flex; 
  align-items: center; 
  gap: 6px; 
  font-size: 12.5px; 
  color: #1e293b; 
  font-weight: 700; 
  background: #f8fafc;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.customer-name { font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.3; }
.cust-link { color: #0f172a; text-decoration: none; transition: color 0.15s; }
.cust-link:hover { color: #2563eb; text-decoration: underline; }

.wa-badge-pill {
  font-size: 11.5px;
  color: #059669;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  padding: 2.5px 8px;
  border-radius: 6px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
  font-weight: 700;
  transition: all 0.15s;
}
.wa-badge-pill:hover {
  background: #059669;
  color: #ffffff;
}
.reschedule-callout {
  font-size: 10.5px;
  color: #e11d48;
  font-weight: 700;
  margin-top: 5px;
  background: #fff1f2;
  border: 1px solid #ffe4e6;
  padding: 3px 8px;
  border-radius: 6px;
}

/* Sales & Status Elements */
.sales-avatar-img {
  width: 34px; height: 34px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.avatar-initials-gradient {
  width: 34px; height: 34px;
  border-radius: 50%;
  color: #ffffff;
  font-size: 12px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.sales-name-text { font-size: 13px; font-weight: 700; color: #0f172a; }
.prospek-badge {
  font-size: 9px;
  font-weight: 800;
  padding: 1.5px 6px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  text-transform: uppercase;
}
.sales-status-badge { 
  font-size: 10px; 
  font-weight: 800; 
  padding: 2.5px 8px; 
  border-radius: 12px; 
  white-space: nowrap; 
  width: fit-content;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.sales-status-badge.status-scheduled { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.sales-status-badge.status-running { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
.sales-status-badge.status-done { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
.sales-status-badge.status-cancelled { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }

.pulse-dot-running {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: #0284c7;
  animation: pulseGreen 1.5s infinite;
}

.clock-badge {
  font-size: 9px;
  font-weight: 700;
  font-family: monospace;
  padding: 2px 7px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 3px;
  text-transform: uppercase;
}
.clock-in-active {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
}
.clock-out-active {
  background: #fff1f2;
  color: #be123c;
  border: 1px solid #fecdd3;
}
.clock-empty {
  background: #f8fafc;
  color: #94a3b8;
  border: 1px solid #e2e8f0;
}
.clock-map-link {
  text-decoration: none;
  font-size: 10px;
  transition: transform 0.15s;
}
.clock-map-link:hover {
  transform: scale(1.3);
}

.invoice-badge-pill {
  font-size: 9.5px;
  font-weight: 800;
  font-family: monospace;
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
  padding: 2px 7px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
}

.alamat-text {
  font-size: 12.5px;
  color: #334155;
  line-height: 1.5;
  font-weight: 500;
  display: flex;
  align-items: flex-start;
  gap: 4px;
}
.location-pin-icon {
  font-size: 16px;
  color: #e11d48;
  flex-shrink: 0;
  margin-top: 1px;
}

/* Action Buttons */
.keg-actions { display: flex; gap: 6px; align-items: center; justify-content: center; }
.btn-action {
  width: 36px; height: 36px; border-radius: 10px;
  display: inline-flex; align-items: center; justify-content: center;
  text-decoration: none; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1.5px solid transparent;
  cursor: pointer;
}
.btn-action:hover { transform: translateY(-2px) scale(1.06); }
.btn-action .material-symbols-outlined { font-size: 18px; }

.btn-view { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.btn-view:hover { background: #2563eb; color: #ffffff; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }

.btn-resched { background: #e0e7ff; color: #4f46e5; border-color: #c7d2fe; }
.btn-resched:hover { background: #4f46e5; color: #ffffff; box-shadow: 0 4px 12px rgba(79,70,229,0.3); }

.btn-edit { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
.btn-edit:hover { background: #059669; color: #ffffff; box-shadow: 0 4px 12px rgba(5,150,105,0.3); }

.btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.btn-delete:hover { background: #dc2626; color: #ffffff; box-shadow: 0 4px 12px rgba(220,38,38,0.3); }

.btn-approve { background: #ecfdf5; color: #16a34a; border-color: #86efac; }
.btn-approve:hover { background: #16a34a; color: #ffffff; box-shadow: 0 4px 12px rgba(22,163,74,0.3); }

/* ── 8. Inline Filter Bar ── */
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
  padding: 8px 36px 8px 38px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 12.5px;
  font-weight: 600;
  color: #1e293b;
  background: #ffffff;
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
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}
.inline-search-count {
  font-size: 11px;
  font-weight: 800;
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  padding: 4px 10px;
  border-radius: 20px;
  opacity: 0;
  transition: opacity 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
}
.inline-search-count.visible { opacity: 1; }
.inline-search-clear {
  position: absolute;
  right: 6px;
  width: 24px; height: 24px;
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
.inline-search-clear:hover { background: #e2e8f0; color: #0f172a; }

.keg-row.filtered-hidden { display: none !important; }
.keg-row.filtered-highlight { animation: rowHighlight 0.4s ease; }
@keyframes rowHighlight {
  0% { background: rgba(59, 130, 246, 0.08); }
  100% { background: #ffffff; }
}

/* ── 9. Empty State ── */
.empty-state-premium { 
  text-align: center; 
  padding: 50px 30px; 
  background: #ffffff; 
  border-radius: 16px; 
  border: 2px dashed #e2e8f0;
  max-width: 440px;
  margin: 30px auto;
}
.empty-icon-wrapper {
  width: 72px; height: 72px;
  margin: 0 auto;
  background: color-mix(in srgb, var(--accent) 12%, transparent);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.empty-icon-pulsing {
  font-size: 34px;
  color: var(--accent);
  animation: pulse 2s infinite ease-in-out;
}
.empty-title { font-size: 16px; font-weight: 800; color: #1e293b; }
.empty-sub { font-size: 13px; color: #64748b; margin-top: 6px; }

@keyframes pulse {
  0% { transform: scale(1); opacity: 0.8; }
  50% { transform: scale(1.08); opacity: 1; }
  100% { transform: scale(1); opacity: 0.8; }
}

/* ── 10. Responsive Breakpoints ── */
@media (max-width: 991px) {
  .hero-welcome-body {
    flex-direction: column;
    align-items: stretch;
  }
}
@media (max-width: 767px) {
  .keg-header { display: none; }
  .keg-row {
    display: flex; flex-direction: column; gap: 12px;
    border-left-width: 5px;
    padding: 16px;
    border-radius: 14px;
    align-items: stretch;
  }
  .keg-actions { 
    justify-content: flex-start; 
    margin-top: 8px; 
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 8px !important;
  }
  .btn-action {
    min-width: 38px !important;
    min-height: 38px !important;
  }
  .stat-theme-count { font-size: 24px; }
  .inline-filter-row {
    flex-direction: column;
    align-items: stretch;
  }
  .inline-search-wrapper, .inline-date-wrapper {
    max-width: 100%;
    width: 100%;
  }
}

/* ── Modals Style ── */
.modal-overlay-custom {
  display: none;
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 9999;
  justify-content: center; align-items: center;
}
.modal-overlay-custom.active { display: flex; }
.modal-card-custom {
  background: #ffffff;
  border-radius: 20px;
  padding: 32px;
  width: 90%; max-width: 420px;
  text-align: center;
  box-shadow: 0 25px 60px rgba(0,0,0,0.2);
  animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes modalSlideIn {
  from { opacity: 0; transform: scale(0.9) translateY(20px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-icon-custom {
  width: 60px; height: 60px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 16px;
}
.modal-icon-custom span { font-size: 30px; }
.modal-title-custom { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
.modal-desc-custom { font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 24px; }
.modal-actions-custom { display: flex; gap: 10px; justify-content: center; }
.modal-btn-cancel {
  padding: 10px 22px; border-radius: 10px;
  background: #f1f5f9; color: #475569;
  border: 1.5px solid #e2e8f0;
  font-weight: 700; font-size: 13px;
  cursor: pointer; transition: all 0.2s;
}
.modal-btn-cancel:hover { background: #e2e8f0; }

/* Reschedule Modal Specific */
.modal-overlay-resched {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: none; align-items: center; justify-content: center;
  z-index: 9999;
}
.modal-overlay-resched.active { display: flex; }
.modal-card-resched {
  background: #ffffff;
  border-radius: 20px;
  width: 90%; max-width: 460px;
  box-shadow: 0 25px 60px rgba(0,0,0,0.2);
  animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  overflow: hidden;
}
.resched-header {
  background: linear-gradient(135deg, #1e3a5f, #2563eb);
  padding: 22px 26px;
  color: #ffffff;
}
.resched-header h4 {
  font-size: 17px; font-weight: 800; margin: 0 0 4px;
  display: flex; align-items: center; gap: 8px;
}
.resched-header p {
  font-size: 12px; color: rgba(255,255,255,0.75); margin: 0;
}
.resched-customer-badge {
  display: inline-block;
  background: rgba(255,255,255,0.18);
  padding: 3px 12px; border-radius: 20px;
  font-size: 11.5px; font-weight: 700;
  margin-top: 8px;
  border: 1px solid rgba(255,255,255,0.15);
}
.resched-body { padding: 22px 26px; }
.resched-label {
  font-size: 11px; font-weight: 800; color: #475569;
  text-transform: uppercase; letter-spacing: 0.05em;
  margin-bottom: 6px; display: flex; align-items: center; gap: 4px;
}
.resched-input {
  width: 100%; padding: 10px 14px;
  border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 13px; font-weight: 600; color: #1e293b;
  background: #f8fafc; outline: none;
  transition: all 0.2s;
  font-family: inherit;
}
.resched-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
  background: #ffffff;
}
textarea.resched-input { resize: vertical; min-height: 70px; }
.resched-footer {
  padding: 0 26px 22px;
  display: flex; gap: 10px; justify-content: flex-end;
}
.resched-btn-submit {
  padding: 10px 22px; border-radius: 10px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff; border: none;
  font-weight: 700; font-size: 13px;
  cursor: pointer; transition: all 0.2s;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
  display: flex; align-items: center; gap: 6px;
}
.resched-btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,0.4); }
</style>

<!-- ═══ Approve Confirmation Modal ═══ -->
<div class="modal-overlay-custom" id="approveModal">
  <div class="modal-card-custom">
    <div class="modal-icon-custom" style="background: #ecfdf5; color: #059669;">
      <span class="material-symbols-outlined">check_circle</span>
    </div>
    <div class="modal-title-custom">Setujui Reschedule?</div>
    <div class="modal-desc-custom">
      Jadwal reschedule untuk kunjungan ke <strong id="approveCustomerName" class="text-dark"></strong> akan disetujui dan diaktifkan.
    </div>
    <div class="modal-actions-custom">
      <button class="modal-btn-cancel" onclick="closeApproveModal()">Batal</button>
      <button class="btn btn-success fw-bold rounded-3 px-4 py-2" id="btnConfirmApprove" onclick="executeApprove()" style="font-size: 13px;">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">check</span>
        Ya, Setujui
      </button>
    </div>
  </div>
</div>

<!-- ═══ Delete Confirmation Modal ═══ -->
<div class="modal-overlay-custom" id="deleteModal">
  <div class="modal-card-custom">
    <div class="modal-icon-custom" style="background: #fef2f2; color: #dc2626;">
      <span class="material-symbols-outlined">delete_forever</span>
    </div>
    <div class="modal-title-custom">Hapus Kegiatan?</div>
    <div class="modal-desc-custom">
      Kegiatan kunjungan ke <strong id="deleteCustomerName" class="text-danger"></strong> akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
    </div>
    <div class="modal-actions-custom">
      <button class="modal-btn-cancel" onclick="closeDeleteModal()">Batal</button>
      <button class="btn btn-danger fw-bold rounded-3 px-4 py-2" id="btnConfirmDelete" onclick="executeDelete()" style="font-size: 13px;">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">delete</span>
        Ya, Hapus
      </button>
    </div>
  </div>
</div>

<!-- ═══ Admin Reschedule Modal ═══ -->
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
          <span class="material-symbols-outlined" style="font-size:15px; color:#2563eb;">calendar_month</span>
          Tanggal &amp; Waktu Baru <span style="color:#ef4444;">*</span>
        </div>
        <input type="datetime-local" class="resched-input" id="reschedNewDate" required>
      </div>
      <div>
        <div class="resched-label">
          <span class="material-symbols-outlined" style="font-size:15px; color:#2563eb;">edit_note</span>
          Alasan Reschedule (Opsional)
        </div>
        <textarea class="resched-input" id="reschedReason" placeholder="Contoh: Toko tutup, lokasi belum siap, atau request customer..."></textarea>
      </div>
    </div>
    <div class="resched-footer">
      <button class="modal-btn-cancel" onclick="closeReschedModal()">Batal</button>
      <button class="resched-btn-submit" id="btnExecResched" onclick="executeReschedule()">
        <span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span>
        Jadwalkan Ulang
      </button>
    </div>
  </div>
</div>

<!-- ── JAVASCRIPT & CHARTS ────────────────────────────────────────────────── -->
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

let reschedId = null;

function openRescheduleModal(id, customerName, currentDate) {
  reschedId = id;
  document.getElementById('reschedCustomerBadge').textContent = '📍 ' + (customerName || 'Customer');
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

// Inline Filter Logic
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

// ── ApexCharts Initialization ──
document.addEventListener('DOMContentLoaded', function() {
  // 1. Trend Kunjungan (7 Hari Terakhir)
  const trendOptions = {
    series: [{
      name: 'Kunjungan',
      data: <?= json_encode($counts7); ?>
    }],
    chart: {
      type: 'area',
      height: 220,
      toolbar: { show: false },
      zoom: { enabled: false },
      fontFamily: 'Plus Jakarta Sans, sans-serif'
    },
    colors: ['#0284c7'],
    dataLabels: { enabled: false },
    stroke: {
      curve: 'smooth',
      width: 3.5
    },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.45,
        opacityTo: 0.05,
        stops: [0, 90, 100]
      }
    },
    markers: {
      size: 5,
      colors: ['#0284c7'],
      strokeColors: '#ffffff',
      strokeWidth: 2.5,
      hover: { size: 7 }
    },
    xaxis: {
      categories: <?= json_encode($dates7); ?>,
      labels: {
        style: {
          colors: '#64748b',
          fontSize: '11px',
          fontWeight: 700
        }
      },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: {
      labels: {
        style: {
          colors: '#64748b',
          fontSize: '11px',
          fontWeight: 700
        }
      }
    },
    grid: {
      borderColor: '#f1f5f9',
      strokeDashArray: 4,
      padding: { top: 0, right: 10, bottom: 0, left: 10 }
    },
    tooltip: {
      theme: 'light',
      y: {
        formatter: function (val) {
          return val + " Kunjungan";
        }
      }
    }
  };

  const trendChartEl = document.querySelector("#trendKunjunganChart");
  if (trendChartEl && typeof ApexCharts !== 'undefined') {
    const trendChart = new ApexCharts(trendChartEl, trendOptions);
    trendChart.render();
  }

  // 2. Performa Kunjungan Sales
  const perfOptions = {
    series: [{
      name: 'Total Kunjungan',
      data: <?= json_encode($salesVisits); ?>
    }],
    chart: {
      type: 'bar',
      height: 220,
      toolbar: { show: false },
      fontFamily: 'Plus Jakarta Sans, sans-serif'
    },
    plotOptions: {
      bar: {
        borderRadius: 8,
        columnWidth: '24%',
        distributed: false
      }
    },
    colors: ['#9333ea'],
    fill: {
      type: 'gradient',
      gradient: {
        type: 'vertical',
        shadeIntensity: 1,
        gradientToColors: ['#ec4899'],
        inverseColors: false,
        opacityFrom: 1,
        opacityTo: 1,
        stops: [0, 100]
      }
    },
    dataLabels: { enabled: false },
    legend: { show: false },
    xaxis: {
      categories: <?= json_encode($salesNames); ?>,
      labels: {
        style: {
          colors: '#64748b',
          fontSize: '11px',
          fontWeight: 700
        }
      },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: {
      labels: {
        style: {
          colors: '#64748b',
          fontSize: '11px',
          fontWeight: 700
        }
      }
    },
    grid: {
      borderColor: '#f1f5f9',
      strokeDashArray: 4,
      padding: { top: 0, right: 10, bottom: 0, left: 10 }
    },
    tooltip: {
      theme: 'light',
      y: {
        formatter: function (val) {
          return val + " Kunjungan Selesai";
        }
      }
    }
  };

  const perfChartEl = document.querySelector("#performaSalesChart");
  if (perfChartEl && typeof ApexCharts !== 'undefined') {
    const perfChart = new ApexCharts(perfChartEl, perfOptions);
    perfChart.render();
  }

  // 3. Live Clock Updater
  function updateHeroClock() {
    const clockEl = document.getElementById('heroLiveClock');
    if (clockEl) {
      const now = new Date();
      const hrs = String(now.getHours()).padStart(2, '0');
      const mins = String(now.getMinutes()).padStart(2, '0');
      const secs = String(now.getSeconds()).padStart(2, '0');
      clockEl.textContent = `${hrs}:${mins}:${secs}`;
    }
  }
  setInterval(updateHeroClock, 1000);
});
</script>
