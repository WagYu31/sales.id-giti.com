<?php
/**
 * get-data-rincian-pekerjaan.php - AJAX Detail Riwayat Waktu & Kunjungan Sales
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

// ── 1. Query Data Kegiatan Utama & Customer ─────────────────────────────────
$kegiatan = null;
$qKegiatan = mysqli_query($conn, "
    SELECT ks.*, 
           sc.nama AS nama_cust, 
           sc.kategori AS kategori_cust, 
           sc.alamat AS alamat_cust, 
           sc.kota AS kota_cust, 
           sc.telp_pribadi AS telp_cust, 
           sc.foto AS foto_cust
    FROM kegiatan_sales ks
    LEFT JOIN sales_customer sc ON ks.id_customer = sc.id
    WHERE ks.id = '$safeKode' OR ks.kode = '$safeKode'
    LIMIT 1
");

if ($qKegiatan && mysqli_num_rows($qKegiatan) > 0) {
    $kegiatan = mysqli_fetch_assoc($qKegiatan);
}

// Fallback jika tidak ditemukan di kegiatan_sales, coba cari di kegiatan lama
if (!$kegiatan) {
    $qOldKeg = mysqli_query($conn, "
        SELECT k.*, 
               c.nama AS nama_cust, 
               c.alamat AS alamat_cust, 
               c.kota AS kota_cust, 
               c.no_tlp AS telp_cust
        FROM kegiatan k
        LEFT JOIN customer c ON k.id_cust = c.id_cust
        WHERE k.id_kegiatan = '$safeKode' OR k.kode_transaksi = '$safeKode'
        LIMIT 1
    ");
    if ($qOldKeg && mysqli_num_rows($qOldKeg) > 0) {
        $kegiatan = mysqli_fetch_assoc($qOldKeg);
    }
}

if (!$kegiatan) {
    echo '<div class="alert alert-info p-3"><i class="fa-solid fa-circle-info me-2"></i>Data kegiatan #' . htmlspecialchars($kodeTransaksi) . ' tidak ditemukan di database.</div>';
    exit();
}

// ── 2. Query Data Pelaksanaan (Clock In/Out, Catatan, Foto, Prospek) ─────────
$pelaksanaan = null;
$pelaksanaSql = "
    SELECT ps.*, 
           COALESCE(s.nama, s.nama_lengkap, ps.nama_sales) AS nama_sales_full,
           s.nik AS nik_sales, 
           s.telp AS telp_sales
    FROM pelaksanaan_sales ps
    LEFT JOIN sales s ON (ps.sales_id = s.id OR ps.id_sales = s.id)
    WHERE ps.kegiatan_id = '{$kegiatan['id']}'
";
if ($idSales > 0) {
    $pelaksanaSql .= " AND (ps.sales_id = '$idSales' OR ps.id_sales = '$idSales')";
}
$pelaksanaSql .= " ORDER BY ps.id DESC LIMIT 1";

$qPelaksanaan = mysqli_query($conn, $pelaksanaSql);
if ($qPelaksanaan && mysqli_num_rows($qPelaksanaan) > 0) {
    $pelaksanaan = mysqli_fetch_assoc($qPelaksanaan);
}

// ── 3. Query Data Tim Sales Penugasan (Jika pelaksanaan belum ada) ───────────
$salesInfo = null;
$salesSql = "
    SELECT tks.*, 
           COALESCE(s.nama, s.nama_lengkap, tks.nama_sales) AS nama_sales_full,
           s.nik AS nik_sales, 
           s.telp AS telp_sales
    FROM team_kegiatan_sales tks
    LEFT JOIN sales s ON tks.id_sales = s.id
    WHERE tks.id_kegiatan_sales = '{$kegiatan['id']}'
";
if ($idSales > 0) {
    $salesSql .= " AND tks.id_sales = '$idSales'";
}
$salesSql .= " LIMIT 1";

$qSales = mysqli_query($conn, $salesSql);
if ($qSales && mysqli_num_rows($qSales) > 0) {
    $salesInfo = mysqli_fetch_assoc($qSales);
}

// Nama Sales Final
$namaSalesFinal = $pelaksanaan['nama_sales_full'] ?? ($salesInfo['nama_sales_full'] ?? ($pelaksanaan['nama_sales'] ?? ($salesInfo['nama_sales'] ?? 'Edi Suprianto')));
$nikSalesFinal  = $pelaksanaan['nik_sales'] ?? ($salesInfo['nik_sales'] ?? '');
$telpSalesFinal = $pelaksanaan['telp_sales'] ?? ($salesInfo['telp_sales'] ?? '');

// Waktu Mulai & Selesai
$waktuMulai   = $pelaksanaan['ci_at'] ?? ($kegiatan['tgl_mulai'] ?? null);
$waktuSelesai = $pelaksanaan['co_at'] ?? ($kegiatan['tgl_selesai'] ?? null);
$jadwalRencana = $kegiatan['jadwal'] ?? ($kegiatan['tgl_request'] ?? null);

// Hitung Durasi
$durasi = "-";
if (!empty($waktuMulai) && !empty($waktuSelesai) && $waktuMulai != '0000-00-00 00:00:00' && $waktuSelesai != '0000-00-00 00:00:00') {
    $diffSec = strtotime($waktuSelesai) - strtotime($waktuMulai);
    if ($diffSec > 0) {
        $hrs = floor($diffSec / 3600);
        $mins = floor(($diffSec % 3600) / 60);
        $durasi = ($hrs > 0 ? "{$hrs} Jam " : "") . "{$mins} Menit";
    } else {
        $durasi = "< 1 Menit";
    }
} elseif (!empty($waktuMulai) && empty($waktuSelesai)) {
    $durasi = '<span class="text-primary fw-bold"><i class="fa-solid fa-spinner fa-spin me-1"></i>Sedang Berlangsung</span>';
}

// Prospek & Invoice
$prospek = $pelaksanaan['tipe_prospek'] ?? 'Biasa';
$prospekBadge = match(strtolower($prospek)) {
    'deal', 'closing' => '<span class="badge bg-success">Deal / Closing</span>',
    'hot lead' => '<span class="badge bg-danger">Hot Lead</span>',
    'follow up' => '<span class="badge bg-info text-dark">Follow Up</span>',
    default => '<span class="badge bg-secondary">Biasa</span>'
};

$noInvoice = $pelaksanaan['no_invoice'] ?? ($kegiatan['invoice'] ?? '');
$catatanVisit = $pelaksanaan['catatan_visit'] ?? ($kegiatan['keterangan'] ?? ($kegiatan['ket_finish'] ?? ''));

// Kontak PIC
$namaPIC = $pelaksanaan['nama_client'] ?? ($kegiatan['nama_cust'] ?? '-');
$nomerPIC = $pelaksanaan['nomer_client'] ?? ($kegiatan['telp_cust'] ?? '');

// Lokasi GPS
$latCI = $pelaksanaan['lat_ci'] ?? ($kegiatan['lat'] ?? '');
$lonCI = $pelaksanaan['lon_ci'] ?? ($kegiatan['lon'] ?? '');

// Foto Bukti
$photos = array_filter([
    $pelaksanaan['foto'] ?? '',
    $pelaksanaan['image_1'] ?? '',
    $pelaksanaan['image_2'] ?? '',
    $pelaksanaan['image_3'] ?? '',
    $pelaksanaan['image_4'] ?? '',
    $pelaksanaan['image_5'] ?? '',
    $kegiatan['foto_cust'] ?? ''
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
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($kegiatan['nama_cust'] ?? '-'); ?></h5>
                <div class="small text-muted mb-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                        <?= htmlspecialchars($kegiatan['kategori_cust'] ?? 'Toko'); ?>
                    </span>
                    <span><?= htmlspecialchars($kegiatan['kota_cust'] ?? '-'); ?></span>
                </div>
                <?php if (!empty($kegiatan['alamat_cust'])): ?>
                <div class="small text-muted">
                    <i class="fa-solid fa-location-dot me-1 text-danger"></i><?= htmlspecialchars($kegiatan['alamat_cust']); ?>
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
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($namaSalesFinal); ?></h5>
                <div class="small text-muted mb-2">
                    <?= !empty($nikSalesFinal) ? "NIK: " . htmlspecialchars($nikSalesFinal) . " &bull; " : ""; ?>
                    <?= !empty($telpSalesFinal) ? htmlspecialchars($telpSalesFinal) : "-"; ?>
                </div>
                <div class="small text-dark fw-semibold">
                    <i class="fa-regular fa-calendar-check me-1 text-info"></i> 
                    Rencana Jadwal: <?= !empty($jadwalRencana) ? date('d M Y, H:i', strtotime($jadwalRencana)) . ' WIB' : '-'; ?>
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
                        <?= !empty($waktuMulai) && $waktuMulai != '0000-00-00 00:00:00' ? date('d/m/Y H:i', strtotime($waktuMulai)) : '<span class="text-muted">-</span>'; ?>
                    </div>
                </div>
                <div class="col-4 border-end">
                    <div class="text-xxs text-uppercase fw-bold text-muted mb-1">Waktu Selesai (Clock Out)</div>
                    <div class="fw-bold text-dark fs-6">
                        <?= !empty($waktuSelesai) && $waktuSelesai != '0000-00-00 00:00:00' ? date('d/m/Y H:i', strtotime($waktuSelesai)) : '<span class="text-muted">-</span>'; ?>
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
                        <div class="fw-bold text-dark text-sm"><?= htmlspecialchars($namaPIC); ?></div>
                        <?php if (!empty($nomerPIC)): 
                            $cleanTelp = preg_replace('/\D/', '', $nomerPIC);
                            if (substr($cleanTelp, 0, 1) === '0') $cleanTelp = '62' . substr($cleanTelp, 1);
                        ?>
                        <a href="https://wa.me/<?= $cleanTelp; ?>" target="_blank" class="text-success small fw-semibold text-decoration-none">
                            <i class="fa-brands fa-whatsapp me-1"></i><?= htmlspecialchars($nomerPIC); ?>
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
                        <?php if (!empty($noInvoice)): ?>
                        <div class="text-end">
                            <div class="text-xxs text-uppercase fw-bold text-muted">No. Invoice:</div>
                            <span class="badge bg-dark mt-1"><i class="fa-solid fa-receipt me-1"></i><?= htmlspecialchars($noInvoice); ?></span>
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
                    <?= !empty($catatanVisit) ? nl2br(htmlspecialchars($catatanVisit)) : '<em class="text-muted">Tidak ada catatan hasil kunjungan.</em>'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Lokasi & Geofence GPS -->
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
