<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Loewix OPcache Reset</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="card shadow-sm border-0 rounded-4 p-4 text-center" style="max-width: 480px; width: 100%;">
        <div class="mb-3">
            <span style="font-size: 48px;">⚡</span>
        </div>
        <h4 class="fw-bold mb-2">Loewix Cache Reset</h4>
        <p class="text-muted small mb-4">Membersihkan Zend OPcache dan memory cache aplikasi</p>
        
        <?php
        $success = false;
        $msg = '';
        if (function_exists('opcache_reset')) {
            if (opcache_reset()) {
                $success = true;
                $msg = 'Zend OPcache berhasil di-reset 100%! Semua file PHP baru langsung aktif seketika.';
            } else {
                $msg = 'OPcache sedang tidak aktif atau belum diaktifkan di server.';
            }
        } else {
            $msg = 'Fungsi opcache_reset tidak tersedia.';
        }
        ?>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-info'; ?> text-start rounded-3" role="alert">
            <div class="fw-bold"><?php echo $success ? '✅ Berhasil!' : 'ℹ️ Informasi:'; ?></div>
            <div class="small"><?php echo $msg; ?></div>
        </div>

        <div class="d-grid gap-2 mt-3">
            <a href="scraping_exhibitor.php" class="btn btn-primary rounded-pill fw-bold py-2">
                Buka Halaman Scraper Pameran &rarr;
            </a>
            <a href="javascript:location.reload()" class="btn btn-outline-secondary rounded-pill btn-sm py-2">
                Refresh Ulang Halaman Ini
            </a>
        </div>
    </div>
</body>
</html>