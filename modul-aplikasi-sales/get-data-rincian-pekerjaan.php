<?php
/**
 * get-data-rincian-pekerjaan.php - AJAX Detail Riwayat Waktu & Kunjungan Sales
 * Modul Aplikasi Sales
 */
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";

$idSales       = isset($_POST['id_sales']) ? intval($_POST['id_sales']) : (isset($_GET['id_sales']) ? intval($_GET['id_sales']) : 0);
$kodeTransaksi = isset($_POST['kode_transaksi']) ? intval($_POST['kode_transaksi']) : (isset($_GET['kode_transaksi']) ? intval($_GET['kode_transaksi']) : 0);

if ($kodeTransaksi <= 0) {
    echo '<div class="alert alert-warning text-dark p-3">Parameter data kunjungan tidak valid.</div>';
    exit();
}

$sql = "
    SELECT 
        ks.id AS kegiatan_id,
        ks.jadwal,
        ks.keterangan AS ket_jadwal,
        ks.status AS status_kegiatan,
        ks.lat AS lat_jadwal,
        ks.lon AS lon_jadwal,
        ks.alamat_lokasi AS alamat_jadwal,
        sc.id AS customer_id,
        sc.nama AS nama_cust,
        sc.kategori AS kategori_cust,
        sc.alamat AS alamat_cust,
        sc.kota AS kota_cust,
        sc.telp_pribadi AS telp_cust,
        s.id AS sales_id,
        COALESCE(s.nama, s.nama_lengkap) AS nama_sales,
        s.nik AS nik_sales,
        s.telp AS telp_sales,
        ps.id AS pelaksanaan_id,
        ps.ci_at,
        ps.co_at,
        ps.lat_ci,
        ps.lon_ci,
        ps.lat_co,
        ps.lon_co,
        ps.catatan_visit,
        ps.nama_client,
        ps.nomer_client,
        ps.tipe_prospek,
        ps.no_invoice,
        ps.foto,
        ps.image_1,
        ps.image_2,
        ps.image_3,
        ps.image_4,
        ps.image_5,
        ps.status AS status_pelaksanaan
    FROM kegiatan_sales ks
    INNER JOIN sales_customer sc ON ks.id_customer = sc.id
    LEFT JOIN team_kegiatan_sales tks ON ks.id = tks.id_kegiatan_sales AND tks.deleted_at IS NULL
    LEFT JOIN sales s ON (tks.id_sales = s.id OR s.id = '$idSales')
    LEFT JOIN pelaksanaan_sales ps ON ks.id = ps.kegiatan_id AND (ps.sales_id = s.id OR ps.id_sales = s.id OR '$idSales' = 0)
    WHERE ks.id = '$kodeTransaksi' AND ks.deleted_at IS NULL
    ORDER BY ps.id DESC
    LIMIT 1
";

$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) == 0) {
    echo '<div class="alert alert-info p-3"><i class="fa-solid fa-circle-info me-2"></i>Data riwayat pengerjaan tidak ditemukan.</div>';
    exit();
}

$d = mysqli_fetch_assoc($res);

// Hitung durasi kerja
$durasi = "-";
if (!empty($d['ci_at']) && !empty($d['co_at']) && $d['ci_at'] != '0000-00-00 00:00:00' && $d['co_at'] != '0000-00-00 00:00:00') {
    $diffSec = strtotime($d['co_at']) - strtotime($d['ci_at']);
    if ($diffSec > 0) {
        $hrs = floor($diffSec / 3600);
        $mins = floor(($diffSec % 3600) / 60);
        $durasi = ($hrs > 0 ? "{$hrs} Jam " : "") . "{$mins} Menit";
    } else {
        $durasi = "< 1 Menit";
    }
} elseif (!empty($d['ci_at']) && empty($d['co_at'])) {
    $durasi = '<span class="text-primary fw-bold"><i class="fa-solid fa-spinner fa-spin me-1"></i>Sedang Berlangsung</span>';
}

// Prospek badge
$prospek = $d['tipe_prospek'] ?? 'Biasa';
$prospekBadge = match(strtolower($prospek)) {
    'deal', 'closing' => '<span class="badge bg-success">Deal / Closing</span>',
    'hot lead' => '<span class="badge bg-danger">Hot Lead</span>',
    'follow up' => '<span class="badge bg-info text-dark">Follow Up</span>',
    default => '<span class="badge bg-secondary">Biasa</span>'
};

// Foto bukti kunjungan
$photos = array_filter([
    $d['foto'] ?? '',
    $d['image_1'] ?? '',
    $d['image_2'] ?? '',
    $d['image_3'] ?? '',
    $d['image_4'] ?? '',
    $d['image_5'] ?? ''
]);
?>

<div class="container-fluid p-0">
    <!-- Header Ringkasan Kunjungan -->
    <div class="row g-3 mb-3">
        <!-- Customer & Toko -->
        <div class="col-12 col-md-6">
            <div class="p-3 bg-light rounded-3 h-100 border">
                <div class="text-xxs text-uppercase fw-bold text-muted mb-1">
                    <i class="fa-solid fa-store text-primary me-1"></i> Toko / Pelanggan
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($d['nama_cust'] ?? '-'); ?></h5>
                <div class="small text-muted mb-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                        <?= htmlspecialchars($d['kategori_cust'] ?? 'Toko'); ?>
                    </span>
                    <span><?= htmlspecialchars($d['kota_cust'] ?? '-'); ?></span>
                </div>
                <?php if (!empty($d['alamat_cust'])): ?>
                <div class="small text-muted">
                    <i class="fa-solid fa-location-dot me-1 text-danger"></i><?= htmlspecialchars($d['alamat_cust']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sales & Jadwal -->
        <div class="col-12 col-md-6">
            <div class="p-3 bg-light rounded-3 h-100 border">
                <div class="text-xxs text-uppercase fw-bold text-muted mb-1">
                    <i class="fa-solid fa-user-tie text-primary me-1"></i> Sales Lapangan
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($d['nama_sales'] ?? 'Sales'); ?></h5>
                <div class="small text-muted mb-2">
                    <?= !empty($d['nik_sales']) ? "NIK: " . htmlspecialchars($d['nik_sales']) . " &bull; " : ""; ?>
                    <?= !empty($d['telp_sales']) ? htmlspecialchars($d['telp_sales']) : "-"; ?>
                </div>
                <div class="small text-dark fw-semibold">
                    <i class="fa-regular fa-calendar-check me-1 text-info"></i> 
                    Rencana: <?= !empty($d['jadwal']) ? date('d M Y, H:i', strtotime($d['jadwal'])) . ' WIB' : '-'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline Waktu Pengerjaan -->
    <div class="card mb-3 border shadow-none" style="border-radius: 12px; background: #fafafa;">
        <div class="card-body p-3">
            <div class="row g-3 text-center align-items-center">
                <div class="col-4 border-end">
                    <div class="text-xxs text-uppercase fw-bold text-muted mb-1">Waktu Mulai (Clock In)</div>
                    <div class="fw-bold text-dark fs-6">
                        <?= !empty($d['ci_at']) && $d['ci_at'] != '0000-00-00 00:00:00' ? date('d/m/Y H:i', strtotime($d['ci_at'])) : '<span class="text-muted">-</span>'; ?>
                    </div>
                </div>
                <div class="col-4 border-end">
                    <div class="text-xxs text-uppercase fw-bold text-muted mb-1">Waktu Selesai (Clock Out)</div>
                    <div class="fw-bold text-dark fs-6">
                        <?= !empty($d['co_at']) && $d['co_at'] != '0000-00-00 00:00:00' ? date('d/m/Y H:i', strtotime($d['co_at'])) : '<span class="text-muted">-</span>'; ?>
                    </div>
                </div>
                <div class="col-4">
                    <div class="text-xxs text-uppercase fw-bold text-muted mb-1">Total Durasi</div>
                    <div class="fw-bold fs-6 <?= $durasi !== '-' ? 'text-success' : 'text-muted'; ?>">
                        <?= $durasi; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rincian Prospek, PIC & Catatan Visit -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
            <div class="p-3 bg-white rounded-3 border h-100">
                <div class="text-xxs text-uppercase fw-bold text-muted mb-2">Kontak PIC / Pelanggan</div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="avatar avatar-xs bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                        <i class="fa-solid fa-user text-secondary" style="font-size:12px;"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark text-sm"><?= htmlspecialchars($d['nama_client'] ?? '-'); ?></div>
                        <?php if (!empty($d['nomer_client'])): 
                            $cleanTelp = preg_replace('/\D/', '', $d['nomer_client']);
                            if (substr($cleanTelp, 0, 1) === '0') $cleanTelp = '62' . substr($cleanTelp, 1);
                        ?>
                        <a href="https://wa.me/<?= $cleanTelp; ?>" target="_blank" class="text-success small fw-semibold text-decoration-none">
                            <i class="fa-brands fa-whatsapp me-1"></i><?= htmlspecialchars($d['nomer_client']); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 pt-2 border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xxs text-uppercase fw-bold text-muted">Status Prospek:</div>
                            <div class="mt-1"><?= $prospekBadge; ?></div>
                        </div>
                        <?php if (!empty($d['no_invoice'])): ?>
                        <div class="text-end">
                            <div class="text-xxs text-uppercase fw-bold text-muted">No. Invoice:</div>
                            <span class="badge bg-dark mt-1"><i class="fa-solid fa-receipt me-1"></i><?= htmlspecialchars($d['no_invoice']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="p-3 bg-white rounded-3 border h-100">
                <div class="text-xxs text-uppercase fw-bold text-muted mb-2">Catatan Hasil Kunjungan</div>
                <div class="p-2.5 bg-light rounded-2 text-dark small" style="min-height: 80px; line-height: 1.6;">
                    <?= !empty($d['catatan_visit']) ? nl2br(htmlspecialchars($d['catatan_visit'])) : '<em class="text-muted">Tidak ada catatan hasil kunjungan.</em>'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Lokasi & Geofence GPS -->
    <?php 
        $latCI = $d['lat_ci'] ?? ($d['lat_jadwal'] ?? '');
        $lonCI = $d['lon_ci'] ?? ($d['lon_jadwal'] ?? '');
    ?>
    <?php if (!empty($latCI) && !empty($lonCI)): ?>
    <div class="p-3 bg-white rounded-3 border mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                <i class="fa-solid fa-location-crosshairs"></i>
            </div>
            <div>
                <div class="text-xxs text-uppercase fw-bold text-muted">Koordinat GPS Check-in</div>
                <div class="fw-bold text-dark small"><?= htmlspecialchars($latCI); ?>, <?= htmlspecialchars($lonCI); ?></div>
            </div>
        </div>
        <a href="https://www.google.com/maps?q=<?= htmlspecialchars($latCI); ?>,<?= htmlspecialchars($lonCI); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 mb-0">
            <i class="fa-solid fa-map-location-dot me-1"></i> Buka di Google Maps
        </a>
    </div>
    <?php endif; ?>

    <!-- Galeri Bukti Foto Kunjungan -->
    <?php if (!empty($photos)): ?>
    <div class="p-3 bg-white rounded-3 border">
        <div class="text-xxs text-uppercase fw-bold text-muted mb-2">
            <i class="fa-solid fa-images me-1 text-primary"></i> Foto Dokumentasi Visit (<?= count($photos); ?> Foto)
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($photos as $p): 
                $photoPath = (file_exists("../uploads/visit/" . $p) ? "../uploads/visit/" : (file_exists("../uploads/customer/" . $p) ? "../uploads/customer/" : "../uploads/" . $p));
            ?>
            <a href="<?= htmlspecialchars($photoPath); ?>" target="_blank" class="d-inline-block border rounded-3 overflow-hidden shadow-sm" style="width: 100px; height: 100px;">
                <img src="<?= htmlspecialchars($photoPath); ?>" alt="Dokumentasi Visit" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='../assets/images/image-placeholder.png';">
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
