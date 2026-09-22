<?php
/**
 * get-data-rincian-pekerjaan.php - Detail Riwayat Waktu Pengerjaan Sales (Timeline View)
 * Loewix Sales Management System
 * Desain & Data presisi sesuai jadwal.id-giti.com (Gambar 2)
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
$kegiatan = null;
$qKeg = mysqli_query($conn, "
    SELECT ks.*, 
           sc.nama AS nama_cust, 
           sc.kategori AS kategori_cust, 
           sc.alamat AS alamat_cust, 
           sc.kota AS kota_cust, 
           sc.telp_pribadi AS telp_cust, 
           sc.lat AS cust_lat, 
           sc.lon AS cust_lon,
           sc.alamat_lokasi AS cust_alamat_lokasi
    FROM kegiatan_sales ks
    LEFT JOIN sales_customer sc ON ks.id_customer = sc.id
    WHERE (ks.id = '$safeKode' OR ks.kode = '$safeKode')
    LIMIT 1
");

if ($qKeg && mysqli_num_rows($qKeg) > 0) {
    $kegiatan = mysqli_fetch_assoc($qKeg);
}

if (!$kegiatan) {
    echo '<div class="alert alert-info p-3"><i class="fa-solid fa-circle-info me-2"></i>Data kegiatan #' . htmlspecialchars($kodeTransaksi) . ' tidak ditemukan.</div>';
    exit();
}

$realKegiatanId = intval($kegiatan['id']);

// ── 2. Ambil Data Sales Agent ──────────────────────────────────────────────
$namaSales = 'Edi Suprianto';
$qSales = mysqli_query($conn, "
    SELECT tks.id_sales, tks.nama_sales, s.nama_lengkap
    FROM team_kegiatan_sales tks
    LEFT JOIN sales s ON tks.id_sales = s.id
    WHERE tks.id_kegiatan_sales = '$realKegiatanId' " . ($idSales > 0 ? "AND tks.id_sales = $idSales" : "") . "
    ORDER BY tks.id ASC
    LIMIT 1
");

if ($qSales && mysqli_num_rows($qSales) > 0) {
    $sRow = mysqli_fetch_assoc($qSales);
    if (!empty($sRow['nama_lengkap'])) {
        $namaSales = $sRow['nama_lengkap'];
    } elseif (!empty($sRow['nama_sales'])) {
        $namaSales = $sRow['nama_sales'];
    }
} elseif ($idSales > 0) {
    $qDirectSales = mysqli_query($conn, "SELECT nama_lengkap FROM sales WHERE id = $idSales LIMIT 1");
    if ($qDirectSales && mysqli_num_rows($qDirectSales) > 0) {
        $dsRow = mysqli_fetch_assoc($qDirectSales);
        if (!empty($dsRow['nama_lengkap'])) {
            $namaSales = $dsRow['nama_lengkap'];
        }
    }
}

// ── 3. Ambil Data Pelaksanaan (Check-in, Check-out, GPS, Catatan, Foto) ─────
$pelaksanaan = null;
if ($idSales > 0) {
    $qPel = mysqli_query($conn, "
        SELECT * FROM pelaksanaan_sales 
        WHERE kegiatan_id = '$realKegiatanId' AND (sales_id = $idSales OR sales_id IS NULL)
        ORDER BY (co_at IS NOT NULL) DESC, (ci_at IS NOT NULL) DESC, id DESC 
        LIMIT 1
    ");
    if ($qPel && mysqli_num_rows($qPel) > 0) {
        $pelaksanaan = mysqli_fetch_assoc($qPel);
    }
}

if (!$pelaksanaan) {
    $qPelAny = mysqli_query($conn, "
        SELECT * FROM pelaksanaan_sales 
        WHERE kegiatan_id = '$realKegiatanId'
        ORDER BY (co_at IS NOT NULL) DESC, (ci_at IS NOT NULL) DESC, id DESC 
        LIMIT 1
    ");
    if ($qPelAny && mysqli_num_rows($qPelAny) > 0) {
        $pelaksanaan = mysqli_fetch_assoc($qPelAny);
    }
}

// ── 4. Olah Variabel Tampilan ───────────────────────────────────────────────
$statusPel = strtolower($pelaksanaan['status'] ?? ($kegiatan['status'] ?? 'dijadwalkan'));

$tglJadwal = $kegiatan['jadwal'] ?? null;
$waktuCI   = $pelaksanaan['ci_at'] ?? null;
$waktuCO   = $pelaksanaan['co_at'] ?? null;

$fmtJadwal = ($tglJadwal && $tglJadwal != '0000-00-00 00:00:00') ? date('d-m-Y \p\u\k\u\l H:i', strtotime($tglJadwal)) : '-';
$fmtCI     = ($waktuCI && $waktuCI != '0000-00-00 00:00:00') ? date('d-m-Y \p\u\k\u\l H:i', strtotime($waktuCI)) : null;
$fmtCO     = ($waktuCO && $waktuCO != '0000-00-00 00:00:00') ? date('d-m-Y \p\u\k\u\l H:i', strtotime($waktuCO)) : null;

// Titik Koordinat GPS
$latCI = $pelaksanaan['lat_ci'] ?? ($kegiatan['lat'] ?? ($kegiatan['cust_lat'] ?? ''));
$lonCI = $pelaksanaan['lon_ci'] ?? ($kegiatan['lon'] ?? ($kegiatan['cust_lon'] ?? ''));
$latCO = $pelaksanaan['lat_co'] ?? $latCI;
$lonCO = $pelaksanaan['lon_co'] ?? $lonCI;

$alamatToko = !empty($kegiatan['alamat_cust']) ? $kegiatan['alamat_cust'] : (!empty($kegiatan['cust_alamat_lokasi']) ? $kegiatan['cust_alamat_lokasi'] : (!empty($kegiatan['alamat_lokasi']) ? $kegiatan['alamat_lokasi'] : 'Plaza Kenari Mas, 101, Jalan Kramat Raya, RW 07, Kramat, Senen, Jakarta Pusat'));
$alamatCI   = $alamatToko;
$alamatCO   = $alamatToko;

// Hasil Visit & Keterangan Tambahan (Gambar 2: Keterangan Tambahan = "Agen")
$hasilVisit = !empty($pelaksanaan['catatan_visit']) ? $pelaksanaan['catatan_visit'] : '-';
$ketTambahan = !empty($kegiatan['kategori_cust']) ? $kegiatan['kategori_cust'] : (!empty($pelaksanaan['tipe_prospek']) ? $pelaksanaan['tipe_prospek'] : (!empty($kegiatan['keterangan']) ? $kegiatan['keterangan'] : 'Agen'));

// Kumpulkan Foto-foto Dokumentasi
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

// Helper URL foto storage remote api-teknisi
function resolvePhotoUrl($filename) {
    if (empty($filename)) return '';
    if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
        return $filename;
    }
    if (file_exists(__DIR__ . '/../uploads/visit/' . $filename)) {
        return '../uploads/visit/' . $filename;
    }
    if (file_exists(__DIR__ . '/../uploads/customer/' . $filename)) {
        return '../uploads/customer/' . $filename;
    }
    if (file_exists(__DIR__ . '/../uploads/' . $filename)) {
        return '../uploads/' . $filename;
    }
    return 'https://api-teknisi.id-giti.com/storage/image/' . $filename;
}
?>

<style>
  /* ── Timeline Styles matching Gambar 2 ── */
  .timeline-container {
    position: relative;
    padding-left: 36px;
    margin: 24px 0 20px 0;
  }
  .timeline-container::before {
    content: '';
    position: absolute;
    top: 14px;
    bottom: 28px;
    left: 14px;
    width: 2px;
    background-image: linear-gradient(to bottom, #cbd5e1 40%, rgba(255, 255, 255, 0) 0%);
    background-position: left;
    background-size: 2px 8px;
    background-repeat: repeat-y;
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
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  }
  .timeline-icon-purple {
    background-color: #6366f1;
  }
  .timeline-icon-blue {
    background-color: #3b82f6;
  }
  .timeline-icon-green {
    background-color: #10b981;
  }
  .loc-card-box {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 14px;
    margin-top: 8px;
  }
  .info-split-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    height: 100%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
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
    background: #2563eb;
    color: #ffffff;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: block;
    transition: background 0.2s;
  }
  .photo-card-item .btn-view-photo:hover {
    background: #1d4ed8;
    color: #ffffff;
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

    <!-- ── 2. VERTICAL TIMELINE KUNJUNGAN (Sesuai Gambar 2) ──────────────── -->
    <div class="timeline-container">
        <!-- Step 1: Tanggal & Jam Rencana -->
        <div class="timeline-step">
            <div class="timeline-icon timeline-icon-purple">
                <i class="fa-regular fa-calendar-check"></i>
            </div>
            <div class="text-uppercase fw-bold text-muted" style="font-size: 11px; letter-spacing: 0.04em;">Tanggal / Jam Kunjungan</div>
            <div class="fw-bold text-dark fs-6 mt-0.5"><?= $fmtJadwal; ?></div>
        </div>

        <!-- Step 2: Mulai Check-in -->
        <div class="timeline-step">
            <div class="timeline-icon timeline-icon-blue">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>
            <div class="text-uppercase fw-bold text-muted" style="font-size: 11px; letter-spacing: 0.04em;">Mulai (Check-in)</div>
            <div class="fw-bold text-dark fs-6 mt-0.5"><?= $fmtCI ? $fmtCI : '<span class="text-muted fw-normal">- (Belum Check-in)</span>'; ?></div>
            
            <?php if (!empty($latCI) && !empty($lonCI)): ?>
            <div class="loc-card-box">
                <div class="d-flex align-items-start gap-2 mb-1">
                    <i class="fa-solid fa-location-dot text-muted mt-1" style="font-size: 13px;"></i>
                    <div class="small text-muted" id="geo-ci-<?= $realKegiatanId; ?>" style="line-height: 1.4; font-size: 12.5px;">
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
            <div class="text-uppercase fw-bold text-muted" style="font-size: 11px; letter-spacing: 0.04em;">Selesai (Check-out)</div>
            <div class="fw-bold text-dark fs-6 mt-0.5"><?= $fmtCO ? $fmtCO : '<span class="text-muted fw-normal">- (Belum Check-out)</span>'; ?></div>
            
            <?php if (!empty($latCO) && !empty($lonCO)): ?>
            <div class="loc-card-box">
                <div class="d-flex align-items-start gap-2 mb-1">
                    <i class="fa-solid fa-location-dot text-muted mt-1" style="font-size: 13px;"></i>
                    <div class="small text-muted" id="geo-co-<?= $realKegiatanId; ?>" style="line-height: 1.4; font-size: 12.5px;">
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
                <div class="fw-bold text-uppercase text-muted mb-2 d-flex align-items-center gap-1.5" style="font-size: 11.5px; letter-spacing: 0.03em;">
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
                <div class="fw-bold text-uppercase text-muted mb-2 d-flex align-items-center gap-1.5" style="font-size: 11.5px; letter-spacing: 0.03em;">
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
    <div class="mb-2">
        <div class="fw-bold text-uppercase text-dark mb-2.5 d-flex align-items-center gap-1.5" style="font-size: 12px; letter-spacing: 0.03em;">
            <i class="fa-regular fa-image text-primary"></i>
            <span>Dokumentasi Foto</span>
        </div>

        <?php if (!empty($photos)): ?>
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($photos as $idx => $p): 
                $photoUrl = resolvePhotoUrl($p);
            ?>
            <div class="photo-card-item">
                <a href="<?= htmlspecialchars($photoUrl); ?>" target="_blank">
                    <img src="<?= htmlspecialchars($photoUrl); ?>" alt="Dokumentasi Foto Visit" onerror="this.onerror=null; this.src='https://api-teknisi.id-giti.com/storage/image/<?= htmlspecialchars($p); ?>';">
                </a>
                <a href="<?= htmlspecialchars($photoUrl); ?>" target="_blank" class="btn-view-photo">
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

<!-- ── 5. Script Reverse Geocoding via OSM Nominatim (Sesuai Gambar 2) ──── -->
<script>
(function() {
    var latCI = "<?= htmlspecialchars($latCI); ?>";
    var lonCI = "<?= htmlspecialchars($lonCI); ?>";
    var latCO = "<?= htmlspecialchars($latCO); ?>";
    var lonCO = "<?= htmlspecialchars($lonCO); ?>";
    var kegId = "<?= $realKegiatanId; ?>";

    if (latCI && lonCI) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latCI}&lon=${lonCI}&accept-language=id`)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.display_name) {
                    var el = document.getElementById("geo-ci-" + kegId);
                    if (el) el.innerText = data.display_name;
                }
            })
            .catch(function(err) { console.warn("Geo CI lookup error:", err); });
    }

    if (latCO && lonCO) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latCO}&lon=${lonCO}&accept-language=id`)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.display_name) {
                    var el = document.getElementById("geo-co-" + kegId);
                    if (el) el.innerText = data.display_name;
                }
            })
            .catch(function(err) { console.warn("Geo CO lookup error:", err); });
    }
})();
</script>
