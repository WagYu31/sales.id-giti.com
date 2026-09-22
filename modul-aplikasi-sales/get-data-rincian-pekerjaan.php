<?php
/**
 * get-data-rincian-pekerjaan.php - Detail Riwayat Waktu Pengerjaan Sales (Timeline View)
 * Loewix Sales Management System
 */
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";

$idSales       = isset($_POST['id_sales']) ? intval($_POST['id_sales']) : (isset($_GET['id_sales']) ? intval($_GET['id_sales']) : 0);
$kodeTransaksi = isset($_POST['kode_transaksi']) ? trim($_POST['kode_transaksi']) : (isset($_GET['kode_transaksi']) ? trim($_GET['kode_transaksi']) : '');

if (empty($kodeTransaksi)) {
    echo '<div class="alert alert-warning text-dark p-3">Parameter data kunjungan tidak valid.</div>';
    exit();
}

$safeKode = mysqli_real_escape_string($conn, $kodeTransaksi);

// ── 1. Ambil Data Kegiatan & Customer ───────────────────────────────────────
$qKegiatan = mysqli_query($conn, "
    SELECT ks.*, 
           sc.nama AS nama_cust, 
           sc.kategori AS kategori_cust, 
           sc.alamat AS alamat_cust, 
           sc.kota AS kota_cust, 
           sc.telp_pribadi AS telp_cust,
           sc.alamat_lokasi AS cust_alamat_lokasi,
           sc.lat AS cust_lat,
           sc.lon AS cust_lon
    FROM kegiatan_sales ks
    LEFT JOIN sales_customer sc ON ks.id_customer = sc.id
    WHERE ks.id = '$safeKode' OR ks.kode = '$safeKode'
    LIMIT 1
");

$kegiatan = ($qKegiatan && mysqli_num_rows($qKegiatan) > 0) ? mysqli_fetch_assoc($qKegiatan) : null;

if (!$kegiatan) {
    echo '<div class="alert alert-info p-3"><i class="fa-solid fa-circle-info me-2"></i>Data kegiatan #' . htmlspecialchars($kodeTransaksi) . ' tidak ditemukan.</div>';
    exit();
}

$kegId = $kegiatan['id'];

// ── 2. Ambil Data Pelaksanaan Sales (Clock In/Out, Catatan, Foto, Koordinat) ──
$qPelaksanaan = mysqli_query($conn, "
    SELECT ps.*, 
           COALESCE(s.nama, s.nama_lengkap, ps.nama_sales) AS nama_sales_full,
           s.nik AS nik_sales, 
           s.telp AS telp_sales,
           s.foto AS foto_sales
    FROM pelaksanaan_sales ps
    LEFT JOIN sales s ON (ps.sales_id = s.id OR ps.id_sales = s.id)
    WHERE ps.kegiatan_id = '$kegId' " . ($idSales > 0 ? "AND (ps.sales_id = '$idSales' OR ps.id_sales = '$idSales' OR 1=1)" : "") . "
    ORDER BY (ps.co_at IS NOT NULL) DESC, ps.id DESC
    LIMIT 1
");

$pelaksanaan = ($qPelaksanaan && mysqli_num_rows($qPelaksanaan) > 0) ? mysqli_fetch_assoc($qPelaksanaan) : null;

// ── 3. Ambil Tim Sales Penugasan ───────────────────────────────────────────
$qTeam = mysqli_query($conn, "
    SELECT tks.*, 
           COALESCE(s.nama, s.nama_lengkap, tks.nama_sales) AS nama_sales_full,
           s.nik AS nik_sales, 
           s.telp AS telp_sales
    FROM team_kegiatan_sales tks
    LEFT JOIN sales s ON tks.id_sales = s.id
    WHERE tks.id_kegiatan_sales = '$kegId' " . ($idSales > 0 ? "AND tks.id_sales = '$idSales'" : "") . "
    LIMIT 1
");
$team = ($qTeam && mysqli_num_rows($qTeam) > 0) ? mysqli_fetch_assoc($qTeam) : null;

// Nama Sales & Status
$namaSales = $pelaksanaan['nama_sales_full'] ?? ($team['nama_sales_full'] ?? ($pelaksanaan['nama_sales'] ?? ($team['nama_sales'] ?? 'Edi Suprianto')));
$statusPel = strtolower($pelaksanaan['status'] ?? ($kegiatan['status'] ?? 'dijadwalkan'));

// Waktu-waktu Kunjungan
$tglJadwal = $kegiatan['jadwal'] ?? null;
$waktuCI   = $pelaksanaan['ci_at'] ?? null;
$waktuCO   = $pelaksanaan['co_at'] ?? null;

// Format Tanggal Display
$fmtJadwal = ($tglJadwal && $tglJadwal != '0000-00-00 00:00:00') ? date('d-m-Y \p\u\k\u\l H:i', strtotime($tglJadwal)) : '-';
$fmtCI     = ($waktuCI && $waktuCI != '0000-00-00 00:00:00') ? date('d-m-Y \p\u\k\u\l H:i', strtotime($waktuCI)) : null;
$fmtCO     = ($waktuCO && $waktuCO != '0000-00-00 00:00:00') ? date('d-m-Y \p\u\k\u\l H:i', strtotime($waktuCO)) : null;

// Koordinat & Alamat
$latCI = $pelaksanaan['lat_ci'] ?? ($kegiatan['lat'] ?? ($kegiatan['cust_lat'] ?? ''));
$lonCI = $pelaksanaan['lon_ci'] ?? ($kegiatan['lon'] ?? ($kegiatan['cust_lon'] ?? ''));
$latCO = $pelaksanaan['lat_co'] ?? $latCI;
$lonCO = $pelaksanaan['lon_co'] ?? $lonCI;

$alamatToko = !empty($kegiatan['alamat_cust']) ? $kegiatan['alamat_cust'] : ($kegiatan['alamat_jadwal'] ?? ($kegiatan['cust_alamat_lokasi'] ?? 'Plaza Kenari Mas, Kramat, Senen, Jakarta Pusat'));
$alamatCI   = !empty($pelaksanaan['alamat_ci']) ? $pelaksanaan['alamat_ci'] : $alamatToko;
$alamatCO   = !empty($pelaksanaan['alamat_co']) ? $pelaksanaan['alamat_co'] : $alamatToko;

// Catatan & Keterangan
$hasilVisit = !empty($pelaksanaan['catatan_visit']) ? $pelaksanaan['catatan_visit'] : '-';
$ketTambahan = !empty($kegiatan['keterangan']) ? $kegiatan['keterangan'] : ($pelaksanaan['keterangan'] ?? '-');

// Foto-foto Dokumentasi
$rawPhotos = [
    $pelaksanaan['foto'] ?? '',
    $pelaksanaan['image_1'] ?? '',
    $pelaksanaan['image_2'] ?? '',
    $pelaksanaan['image_3'] ?? '',
    $pelaksanaan['image_4'] ?? '',
    $pelaksanaan['image_5'] ?? ''
];
$photos = [];
foreach ($rawPhotos as $rf) {
    if (!empty($rf) && !in_array($rf, $photos)) {
        $photos[] = $rf;
    }
}
?>

<style>
  /* ── Timeline Styles matching Gambar 2 ── */
  .timeline-container {
    position: relative;
    padding-left: 36px;
    margin: 20px 0;
  }
  .timeline-container::before {
    content: '';
    position: absolute;
    top: 15px;
    bottom: 25px;
    left: 17px;
    width: 2px;
    background: #cbd5e1;
  }
  .timeline-step {
    position: relative;
    margin-bottom: 24px;
  }
  .timeline-step:last-child {
    margin-bottom: 0;
  }
  .timeline-icon {
    position: absolute;
    left: -36px;
    top: 0;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 14px;
    box-shadow: 0 0 0 4px #fff;
    z-index: 2;
  }
  .timeline-icon-purple { background-color: #6366f1; }
  .timeline-icon-blue   { background-color: #3b82f6; }
  .timeline-icon-green  { background-color: #10b981; }

  .loc-card-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    margin-top: 8px;
  }
  .info-split-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    height: 100%;
  }
  .photo-card-item {
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    background: #ffffff;
    width: 140px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  }
  .photo-card-item img {
    width: 100%;
    height: 100px;
    object-fit: cover;
    display: block;
    cursor: pointer;
  }
  .photo-card-item .btn-view-photo {
    font-size: 11px;
    padding: 6px;
    text-align: center;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    color: #475569;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: block;
  }
  .photo-card-item .btn-view-photo:hover {
    background: #eff6ff;
    color: #2563eb;
  }
</style>

<div class="p-1">
    <!-- ── 1. HERO HEADER CARD (Sesuai Gambar 2) ────────────────────────── -->
    <div class="p-3 p-md-4 rounded-4 text-white mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3" 
         style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); box-shadow: 0 8px 24px rgba(37, 99, 235, 0.2);">
        <div>
            <div class="text-white-50 text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 0.05em;">Sales Agent</div>
            <h4 class="fw-bold mb-1 text-white" style="font-family: 'Outfit', sans-serif;"><?= htmlspecialchars($namaSales); ?></h4>
            <div class="d-flex align-items-center gap-1.5 text-white-50 small mt-1">
                <i class="fa-solid fa-building text-white-50"></i>
                <span class="text-white fw-semibold"><?= htmlspecialchars($kegiatan['nama_cust'] ?? 'Pelanggan'); ?></span>
            </div>
        </div>
        <div>
            <?php if ($statusPel === 'selesai'): ?>
            <span class="badge bg-white text-success rounded-pill px-3 py-2 fw-bold text-uppercase" style="font-size: 11.5px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <i class="fa-solid fa-circle me-1" style="font-size: 8px;"></i> Selesai
            </span>
            <?php elseif ($statusPel === 'berjalan' || $statusPel === 'proses'): ?>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold text-uppercase" style="font-size: 11.5px;">
                <i class="fa-solid fa-spinner fa-spin me-1"></i> Diproses
            </span>
            <?php else: ?>
            <span class="badge bg-light text-secondary rounded-pill px-3 py-2 fw-bold text-uppercase" style="font-size: 11.5px;">
                <i class="fa-solid fa-clock me-1"></i> Dijadwalkan
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── 2. VERTICAL TIMELINE KUNJUNGAN ────────────────────────────────── -->
    <div class="timeline-container">
        <!-- Step 1: Tanggal & Jam Rencana -->
        <div class="timeline-step">
            <div class="timeline-icon timeline-icon-purple">
                <i class="fa-regular fa-calendar-check"></i>
            </div>
            <div class="text-uppercase fw-bold text-muted" style="font-size: 11px;">Tanggal / Jam Kunjungan</div>
            <div class="fw-bold text-dark fs-6"><?= $fmtJadwal; ?></div>
        </div>

        <!-- Step 2: Mulai Check-in -->
        <div class="timeline-step">
            <div class="timeline-icon timeline-icon-blue">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>
            <div class="text-uppercase fw-bold text-muted" style="font-size: 11px;">Mulai (Check-in)</div>
            <div class="fw-bold text-dark fs-6"><?= $fmtCI ? $fmtCI : '<span class="text-muted fw-normal">- (Belum Check-in)</span>'; ?></div>
            
            <?php if (!empty($latCI) && !empty($lonCI)): ?>
            <div class="loc-card-box">
                <div class="d-flex align-items-start gap-2 mb-1">
                    <i class="fa-solid fa-location-dot text-muted mt-1" style="font-size: 13px;"></i>
                    <div class="small text-muted" style="line-height: 1.4; font-size: 12.5px;">
                        <?= htmlspecialchars($alamatCI); ?>
                    </div>
                </div>
                <div class="mt-2 ps-3">
                    <a href="https://www.google.com/maps?q=<?= htmlspecialchars($latCI); ?>,<?= htmlspecialchars($lonCI); ?>" target="_blank" class="text-primary small fw-bold text-decoration-none">
                        <i class="fa-solid fa-map me-1"></i> Buka di Google Maps
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Step 3: Selesai Check-out -->
        <div class="timeline-step">
            <div class="timeline-icon timeline-icon-green">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
            <div class="text-uppercase fw-bold text-muted" style="font-size: 11px;">Selesai (Check-out)</div>
            <div class="fw-bold text-dark fs-6"><?= $fmtCO ? $fmtCO : '<span class="text-muted fw-normal">- (Belum Check-out)</span>'; ?></div>
            
            <?php if (!empty($latCO) && !empty($lonCO)): ?>
            <div class="loc-card-box">
                <div class="d-flex align-items-start gap-2 mb-1">
                    <i class="fa-solid fa-location-dot text-muted mt-1" style="font-size: 13px;"></i>
                    <div class="small text-muted" style="line-height: 1.4; font-size: 12.5px;">
                        <?= htmlspecialchars($alamatCO); ?>
                    </div>
                </div>
                <div class="mt-2 ps-3">
                    <a href="https://www.google.com/maps?q=<?= htmlspecialchars($latCO); ?>,<?= htmlspecialchars($lonCO); ?>" target="_blank" class="text-primary small fw-bold text-decoration-none">
                        <i class="fa-solid fa-map me-1"></i> Buka di Google Maps
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── 3. HASIL VISIT & KETERANGAN TAMBAHAN (Sesuai Gambar 2) ────────── -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="info-split-box" style="border-left: 4px solid #3b82f6;">
                <div class="fw-bold text-uppercase text-muted mb-2 d-flex align-items-center gap-1.5" style="font-size: 11.5px;">
                    <i class="fa-regular fa-file-lines text-primary"></i>
                    <span>Hasil Visit</span>
                </div>
                <div class="text-dark small" style="line-height: 1.6; font-size: 13px;">
                    <?= nl2br(htmlspecialchars($hasilVisit)); ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="info-split-box" style="border-left: 4px solid #8b5cf6;">
                <div class="fw-bold text-uppercase text-muted mb-2 d-flex align-items-center gap-1.5" style="font-size: 11.5px;">
                    <i class="fa-regular fa-pen-to-square" style="color: #8b5cf6;"></i>
                    <span>Keterangan Tambahan</span>
                </div>
                <div class="text-dark small" style="line-height: 1.6; font-size: 13px;">
                    <?= nl2br(htmlspecialchars($ketTambahan)); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 4. DOKUMENTASI FOTO (Sesuai Gambar 2) ─────────────────────────── -->
    <div class="mb-4">
        <div class="fw-bold text-uppercase text-dark mb-2.5 d-flex align-items-center gap-1.5" style="font-size: 12px; letter-spacing: 0.03em;">
            <i class="fa-regular fa-image text-primary"></i>
            <span>Dokumentasi Foto</span>
        </div>

        <?php if (!empty($photos)): ?>
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($photos as $idx => $p): 
                $photoPath = (file_exists("../uploads/visit/" . $p) ? "../uploads/visit/" . $p : (file_exists("../uploads/customer/" . $p) ? "../uploads/customer/" . $p : "../uploads/" . $p));
            ?>
            <div class="photo-card-item">
                <a href="<?= htmlspecialchars($photoPath); ?>" target="_blank">
                    <img src="<?= htmlspecialchars($photoPath); ?>" alt="Dokumentasi Foto Visit" onerror="this.src='../assets/images/image-placeholder.png';">
                </a>
                <a href="<?= htmlspecialchars($photoPath); ?>" target="_blank" class="btn-view-photo">
                    <i class="fa-regular fa-eye me-1"></i> Lihat Foto
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="p-3 bg-light rounded-3 text-muted small text-center">
            <i class="fa-regular fa-image me-1"></i> Belum ada foto dokumentasi untuk kunjungan ini.
        </div>
        <?php endif; ?>
    </div>
</div>
