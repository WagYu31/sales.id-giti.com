<?php
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";
$pageNow = "Data Customer";
$currentPage = "Today";

date_default_timezone_set('Asia/Jakarta');

// Soft Delete
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // Get current photos and delete files
    $getFoto = $conn->prepare("SELECT foto FROM sales_customer WHERE id = ?");
    $getFoto->bind_param("i", $id);
    $getFoto->execute();
    $resFoto = $getFoto->get_result()->fetch_assoc();
    $foto_json = $resFoto['foto'] ?? '';
    $getFoto->close();
    
    if (!empty($foto_json)) {
        $photos = json_decode($foto_json, true);
        if (is_array($photos)) {
            foreach ($photos as $p) {
                @unlink('../uploads/customer/' . $p);
            }
        }
    }

    $conn->query("UPDATE sales_customer SET deleted_at = NOW() WHERE id = '$id'");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Auto-ensure columns exist in sales_customer
$chkCols = @$conn->query("SHOW COLUMNS FROM sales_customer");
if ($chkCols) {
    $existingCols = [];
    while ($r = $chkCols->fetch_assoc()) {
        $existingCols[] = strtolower($r['Field']);
    }
    if (!in_array('is_tiptok', $existingCols)) {
        @$conn->query("ALTER TABLE sales_customer ADD COLUMN `is_tiptok` TINYINT(1) NOT NULL DEFAULT 0 AFTER `kategori`");
    }
    if (!in_array('kode_customer', $existingCols)) {
        @$conn->query("ALTER TABLE sales_customer ADD COLUMN `kode_customer` VARCHAR(50) NULL AFTER `id`");
    }
    if (!in_array('rad', $existingCols)) {
        @$conn->query("ALTER TABLE sales_customer ADD COLUMN `rad` VARCHAR(50) NULL AFTER `lon`");
    }
    if (!in_array('alamat_lokasi', $existingCols)) {
        @$conn->query("ALTER TABLE sales_customer ADD COLUMN `alamat_lokasi` TEXT NULL AFTER `rad`");
    }
}

// Auto-seed from official 1.000 customers JSON (NON-DESTRUCTIVE: preserves all existing data)
$jsonCustFile = __DIR__ . '/../includes/customers_data.json';
if (file_exists($jsonCustFile)) {
    // Only check if table is totally empty or merge without deleting
    $chkCustCnt = @$conn->query("SELECT COUNT(*) as cnt FROM sales_customer WHERE deleted_at IS NULL");
    $curCustCnt = ($chkCustCnt && $rC = $chkCustCnt->fetch_assoc()) ? (int)$rC['cnt'] : 0;
    
    // Create wilayah table if needed
    $conn->query("CREATE TABLE IF NOT EXISTS `wilayah` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama` VARCHAR(100) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        INDEX (`nama`),
        INDEX (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $standardRegions = ['Jabodetabek', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Sumatera', 'Kalimantan', 'Sulawesi', 'Bali & Nusa Tenggara', 'Lainnya'];
    foreach ($standardRegions as $rName) {
        $chk = $conn->query("SELECT id FROM `wilayah` WHERE `nama` = '" . $conn->real_escape_string($rName) . "' AND `deleted_at` IS NULL");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("INSERT INTO `wilayah` (`nama`) VALUES ('" . $conn->real_escape_string($rName) . "')");
        }
    }
    
    if ($curCustCnt < 50) { // Only seed if table is virtually empty, and NEVER truncate
        $custData = json_decode(file_get_contents($jsonCustFile), true);
        if (is_array($custData) && count($custData) > 0) {
            $wilayahMap = [];
            $wRes = $conn->query("SELECT id, nama FROM `wilayah` WHERE `deleted_at` IS NULL");
            if ($wRes) {
                while ($w = $wRes->fetch_assoc()) {
                    $wilayahMap[strtolower(trim($w['nama']))] = (int)$w['id'];
                }
            }
            $defaultWilayahId = $wilayahMap['jabodetabek'] ?? 1;

            $st = $conn->prepare("INSERT INTO `sales_customer` (kode_customer, kategori, is_tiptok, nama, telp_pribadi, email, alamat, kota, id_wilayah, lat, lon, rad, alamat_lokasi, created_at, updated_at) VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if ($st) {
                foreach ($custData as $c) {
                    $kod = $c['kode_customer'] ?? '';
                    $kat = $c['kategori'] ?? 'Dealer';
                    $nam = $c['nama'] ?? '';
                    $tlp = $c['telp_pribadi'] ?? '';
                    $eml = $c['email'] ?? '';
                    $alm = $c['alamat'] ?? '';
                    $kot = $c['kota'] ?? '';
                    $wNm = strtolower(trim($c['wilayah'] ?? ''));
                    $iWil = $wilayahMap[$wNm] ?? $defaultWilayahId;
                    $la = $c['lat'] ?? '-6.1754';
                    $lo = $c['lon'] ?? '106.8272';
                    $ra = $c['rad'] ?? '100';
                    $lok = $c['alamat_lokasi'] ?? ($alm ?: $kot);
                    
                    // Check if customer with same name already exists
                    $chkExist = $conn->query("SELECT id FROM sales_customer WHERE nama = '" . $conn->real_escape_string($nam) . "' LIMIT 1");
                    if ($chkExist && $chkExist->num_rows > 0) {
                        continue; // Do not overwrite existing
                    }

                    $st->bind_param("ssssssissss", $kod, $kat, $nam, $tlp, $eml, $alm, $kot, $iWil, $la, $lo, $ra, $lok);
                    $st->execute();
                }
                $st->close();
            }
        }
    }
}

// UPDATE
$successMsg = "";
$errorMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $id = $_POST['update_id'];
    $nama = $_POST['edit_nama'];
    $kategori = $_POST['edit_kategori'];
    $is_tiptok = isset($_POST['edit_is_tiptok']) ? 1 : 0;
    $email = $_POST['edit_email'];
    $alamat = $_POST['edit_alamat'];
    $kota = $_POST['edit_kota'];
    $id_wilayah = intval($_POST['edit_id_wilayah'] ?? 0);
    $telp = preg_replace('/\D/', '', $_POST['edit_telp']);
    
    // Lokasi GPS
    $lat = !empty($_POST['edit_lat']) ? $_POST['edit_lat'] : NULL;
    $lon = !empty($_POST['edit_lon']) ? $_POST['edit_lon'] : NULL;
    $rad = !empty($_POST['edit_radius']) ? $_POST['edit_radius'] : NULL;
    $location_address = !empty($_POST['edit_location_address']) ? $_POST['edit_location_address'] : NULL;

    if (substr($telp, 0, 1) === '0') {
        $telp = '62' . substr($telp, 1);
    } elseif (substr($telp, 0, 1) === '8') {
        $telp = '62' . $telp;
    } elseif (!str_starts_with($telp, '62')) {
        $telp = '62' . $telp;
    }

    // Get current photos
    $getFoto = $conn->prepare("SELECT foto FROM sales_customer WHERE id = ?");
    $getFoto->bind_param("i", $id);
    $getFoto->execute();
    $resFoto = $getFoto->get_result()->fetch_assoc();
    $foto_json = $resFoto['foto'] ?? '';
    $getFoto->close();

    $existing_photos = [];
    if (!empty($foto_json)) {
        $existing_photos = json_decode($foto_json, true);
        if (!is_array($existing_photos)) {
            $existing_photos = [];
        }
    }

    // Process deleted existing photos
    $deleted_photos_str = $_POST['deleted_existing_photos'] ?? '';
    if (!empty($deleted_photos_str)) {
        $deleted_photos = explode(',', $deleted_photos_str);
        foreach ($deleted_photos as $dp) {
            $dp = trim($dp);
            if (in_array($dp, $existing_photos)) {
                @unlink('../uploads/customer/' . $dp);
                $existing_photos = array_diff($existing_photos, [$dp]);
            }
        }
        $existing_photos = array_values($existing_photos);
    }

    // Handle new file uploads
    $new_filenames = [];
    if (isset($_FILES['edit_foto'])) {
        $files = $_FILES['edit_foto'];
        $uploadFileDir = '../uploads/customer/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmpPath = $files['tmp_name'][$i];
                $fileName = $files['name'][$i];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $newFileName = 'cust_' . time() . '_' . rand(1000, 9999) . '_edit_' . $i . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $new_filenames[] = $newFileName;
                }
            }
        }
    }

    // Merge existing and new photos
    $merged_photos = array_merge($existing_photos, $new_filenames);
    $merged_photos = array_slice($merged_photos, 0, 10);
    $foto_json_updated = !empty($merged_photos) ? json_encode($merged_photos) : NULL;

    $stmt = $conn->prepare("UPDATE sales_customer SET nama = ?, kategori = ?, is_tiptok = ?, telp_pribadi = ?, email = ?, alamat = ?, kota = ?, id_wilayah = ?, foto = ?, lat = ?, lon = ?, rad = ?, alamat_lokasi = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        error_log("Failed to prepare UPDATE sales_customer: " . $conn->error);
        $errorMsg = "Gagal memproses pembaruan customer: " . $conn->error;
    } else {
        $stmt->bind_param("ssissssisssssi", $nama, $kategori, $is_tiptok, $telp, $email, $alamat, $kota, $id_wilayah, $foto_json_updated, $lat, $lon, $rad, $location_address, $id);
        if ($stmt->execute()) {
            $successMsg = "Data Customer berhasil diperbarui!";
        } else {
            error_log("Failed to execute UPDATE sales_customer: " . $stmt->error);
            $errorMsg = "Gagal memperbarui customer: " . $stmt->error;
        }
        $stmt->close();
    }
}

// INSERT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_id'])) {
    $nama = $_POST['nama'];
    $kategori = $_POST['kategori'];
    $is_tiptok = isset($_POST['is_tiptok']) ? 1 : 0;
    $email = $_POST['email'];
    $alamat = $_POST['alamat'];
    $kota = $_POST['kota'];
    $id_wilayah = intval($_POST['id_wilayah'] ?? 0);
    $telp = preg_replace('/\D/', '', $_POST['telp']);
    
    // Lokasi GPS
    $lat = !empty($_POST['lat']) ? $_POST['lat'] : NULL;
    $lon = !empty($_POST['lon']) ? $_POST['lon'] : NULL;
    $rad = !empty($_POST['radius']) ? $_POST['radius'] : NULL;
    $location_address = !empty($_POST['location_address']) ? $_POST['location_address'] : NULL;

    if (substr($telp, 0, 1) === '0') {
        $telp = '62' . substr($telp, 1);
    } elseif (substr($telp, 0, 1) === '8') {
        $telp = '62' . $telp;
    } elseif (!str_starts_with($telp, '62')) {
        $telp = '62' . $telp;
    }

    // Handle multiple files upload
    $filenames = [];
    if (isset($_FILES['foto'])) {
        $files = $_FILES['foto'];
        $uploadFileDir = '../uploads/customer/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmpPath = $files['tmp_name'][$i];
                $fileName = $files['name'][$i];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $newFileName = 'cust_' . time() . '_' . rand(1000, 9999) . '_' . $i . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $filenames[] = $newFileName;
                }
            }
        }
    }
    
    $foto_json = !empty($filenames) ? json_encode($filenames) : NULL;

    // Auto-generate kode customer: CUST-001, CUST-002, ...
    $lastKode = mysqli_query($conn, "SELECT kode_customer FROM sales_customer WHERE kode_customer IS NOT NULL ORDER BY id DESC LIMIT 1");
    $lastRow = mysqli_fetch_assoc($lastKode);
    if ($lastRow && preg_match('/CUST-(\d+)/', $lastRow['kode_customer'], $m)) {
        $nextNum = intval($m[1]) + 1;
    } else {
        $nextNum = 1;
    }
    $kode_customer = 'CUST-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO sales_customer (kode_customer, kategori, is_tiptok, nama, telp_pribadi, email, alamat, kota, id_wilayah, foto, lat, lon, rad, alamat_lokasi, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    if (!$stmt) {
        error_log("Failed to prepare INSERT sales_customer: " . $conn->error);
        $errorMsg = "Gagal memproses penambahan customer: " . $conn->error;
    } else {
        $stmt->bind_param("ssisssssisssss", $kode_customer, $kategori, $is_tiptok, $nama, $telp, $email, $alamat, $kota, $id_wilayah, $foto_json, $lat, $lon, $rad, $location_address);
        if ($stmt->execute()) {
            $successMsg = "Customer baru berhasil ditambahkan!";
            
            // Sync otomatis ke Database CRM utama (includes/db.php)
            $dbInc = __DIR__ . '/../includes/db.php';
            if (file_exists($dbInc)) {
                @call_user_func(function() use ($dbInc, $nama, $kategori, $alamat, $kota, $telp, $lat, $lon) {
                    mysqli_report(MYSQLI_REPORT_OFF);
                    require $dbInc;
                    if (isset($conn) && $conn && !$conn->connect_error) {
                        $chk = $conn->query("SELECT id FROM customers WHERE nama_toko = '" . $conn->real_escape_string($nama) . "' LIMIT 1");
                        if ($chk && $chk->num_rows === 0) {
                            $stC = $conn->prepare("INSERT INTO customers (sales_id, tgl_input, nama_toko, kategori, deal, kandidat, potensial, acc_boss) VALUES (1, NOW(), ?, ?, 'DEAL', 'Y', 'Y', 'Y')");
                            if ($stC) {
                                $katUpper = strtoupper($kategori);
                                $stC->bind_param("ss", $nama, $katUpper);
                                if ($stC->execute()) {
                                    $cId = $conn->insert_id;
                                    $map = "https://maps.google.com/?q={$lat},{$lon}";
                                    $stA = $conn->prepare("INSERT INTO customer_addresses (customer_id, alamat, kota, link_google_map) VALUES (?, ?, ?, ?)");
                                    if ($stA) {
                                        $stA->bind_param("isss", $cId, $alamat, $kota, $map);
                                        $stA->execute();
                                        $stA->close();
                                    }
                                    $stP = $conn->prepare("INSERT INTO customer_pics (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");
                                    if ($stP) {
                                        $stP->bind_param("iss", $cId, $nama, $telp);
                                        $stP->execute();
                                        $stP->close();
                                    }
                                }
                                $stC->close();
                            }
                        }
                    }
                });
            }
        } else {
            error_log("Failed to execute INSERT sales_customer: " . $stmt->error);
            $errorMsg = "Gagal menyimpan customer: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Ambil data wilayah untuk filter
$wilayahList = mysqli_query($conn, "SELECT id, nama FROM wilayah ORDER BY nama ASC");
$kategoriList = ['Dealer', 'Installer', 'User'];

$filterSearch = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterWilayah = isset($_GET['wilayah']) ? trim($_GET['wilayah']) : 'all';
$filterKategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : 'all';

// Check if tiptok_penitipan exists
$hasTiptokTbl = false;
$chkTiptok = @$conn->query("SHOW TABLES LIKE 'tiptok_penitipan'");
if ($chkTiptok && $chkTiptok->num_rows > 0) {
    $hasTiptokTbl = true;
}

$tiptokSelect = "";
if ($hasTiptokTbl) {
    $tiptokSelect = ", (SELECT COUNT(*) FROM tiptok_penitipan tp WHERE tp.id_customer = c.id AND tp.status = 'aktif') AS tiptok_aktif_count,
                       (SELECT SUM(ti.qty_sisa) FROM tiptok_items ti JOIN tiptok_penitipan tp ON ti.id_penitipan = tp.id WHERE tp.id_customer = c.id AND tp.status = 'aktif') AS tiptok_sisa_qty,
                       (SELECT COUNT(*) FROM tiptok_penitipan tp WHERE tp.id_customer = c.id) AS tiptok_total_count ";
} else {
    $tiptokSelect = ", 0 AS tiptok_aktif_count, 0 AS tiptok_sisa_qty, 0 AS tiptok_total_count ";
}

// Build the query
$queryStr = "SELECT c.*, w.nama AS nama_wilayah $tiptokSelect 
             FROM sales_customer c 
             LEFT JOIN wilayah w ON c.id_wilayah = w.id 
             WHERE c.deleted_at IS NULL ";

if ($filterSearch !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $filterSearch);
    $queryStr .= " AND (c.nama LIKE '%$safeSearch%' OR c.kode_customer LIKE '%$safeSearch%' OR c.telp_pribadi LIKE '%$safeSearch%' OR c.alamat LIKE '%$safeSearch%') ";
}

if ($filterWilayah !== 'all') {
    $safeWilayah = mysqli_real_escape_string($conn, $filterWilayah);
    $queryStr .= " AND c.id_wilayah = '$safeWilayah' ";
}

if ($filterKategori === 'tiptok') {
    if ($hasTiptokTbl) {
        $queryStr .= " AND (c.is_tiptok = 1 OR c.id IN (SELECT DISTINCT id_customer FROM tiptok_penitipan WHERE status = 'aktif' OR deleted_at IS NULL)) ";
    } else {
        $queryStr .= " AND c.is_tiptok = 1 ";
    }
} elseif ($filterKategori !== 'all') {
    $safeKategori = mysqli_real_escape_string($conn, $filterKategori);
    $queryStr .= " AND c.kategori = '$safeKategori' ";
}

$queryStr .= " ORDER BY c.id DESC";
$salesData = mysqli_query($conn, $queryStr);

// Calculate statistics for vibrant stat counters
$statTotalCust = 0;
$statDealer = 0;
$statInstaller = 0;
$statUser = 0;
$statTiptok = 0;
$statMapped = 0;

$qStats = mysqli_query($conn, "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN kategori = 'Dealer' THEN 1 ELSE 0 END) AS count_dealer,
    SUM(CASE WHEN kategori = 'Installer' THEN 1 ELSE 0 END) AS count_installer,
    SUM(CASE WHEN kategori = 'User' THEN 1 ELSE 0 END) AS count_user,
    SUM(CASE WHEN (is_tiptok = 1 " . ($hasTiptokTbl ? "OR id IN (SELECT DISTINCT id_customer FROM tiptok_penitipan WHERE deleted_at IS NULL)" : "") . ") THEN 1 ELSE 0 END) AS count_tiptok,
    SUM(CASE WHEN lat IS NOT NULL AND lat != '' AND lon IS NOT NULL AND lon != '' THEN 1 ELSE 0 END) AS count_mapped
FROM sales_customer 
WHERE deleted_at IS NULL");

if ($qStats && $rStats = mysqli_fetch_assoc($qStats)) {
    $statTotalCust = intval($rStats['total'] ?? 0);
    $statDealer = intval($rStats['count_dealer'] ?? 0);
    $statInstaller = intval($rStats['count_installer'] ?? 0);
    $statUser = intval($rStats['count_user'] ?? 0);
    $statTiptok = intval($rStats['count_tiptok'] ?? 0);
    $statMapped = intval($rStats['count_mapped'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include "head.php"; ?>
  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    /* ── TASTE-SKILL VIBRANT COLORFUL DESIGN SYSTEM ── */
    :root {
      --primary-blue: #2563eb;
      --primary-indigo: #4f46e5;
      --primary-purple: #7c3aed;
      --accent-amber: #f59e0b;
      --accent-emerald: #10b981;
      --accent-rose: #f43f5e;
      --surface-bg: #f8fafc;
      --card-radius: 18px;
    }

    /* ── KPI Stat Cards (Top Vibrant Strip) ── */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 18px;
      margin-bottom: 24px;
    }

    .kpi-card {
      border-radius: var(--card-radius);
      padding: 22px 24px;
      color: #fff;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 125px;
      text-decoration: none !important;
      border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .kpi-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 35px -8px rgba(0, 0, 0, 0.22);
      color: #fff !important;
    }

    .kpi-card-blue {
      background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%);
    }

    .kpi-card-amber {
      background: linear-gradient(135deg, #78350f 0%, #d97706 50%, #f59e0b 100%);
    }

    .kpi-card-purple {
      background: linear-gradient(135deg, #3b0764 0%, #6d28d9 50%, #8b5cf6 100%);
    }

    .kpi-card-emerald {
      background: linear-gradient(135deg, #064e3b 0%, #059669 50%, #10b981 100%);
    }

    .kpi-deco-circle {
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(8px);
      pointer-events: none;
    }

    .kpi-icon-badge {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.18);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: #fff;
    }

    .kpi-label {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      opacity: 0.85;
      margin: 0;
    }

    .kpi-value {
      font-size: 26px;
      font-weight: 900;
      line-height: 1.1;
      margin: 4px 0 2px 0;
      letter-spacing: -0.5px;
    }

    .kpi-sub {
      font-size: 11.5px;
      opacity: 0.75;
      font-weight: 600;
    }

    /* ── Premium Card Containers ── */
    .card-premium {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: var(--card-radius);
      overflow: hidden;
      box-shadow: 0 12px 32px rgba(15, 23, 42, 0.04);
      margin-bottom: 24px;
    }

    .card-body-premium {
      padding: 32px 36px;
    }

    /* ── Form Inputs & Focus Rings ── */
    .form-group-premium {
      margin-bottom: 20px;
    }

    .form-label-premium {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 800;
      color: #475569;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .input-premium {
      width: 100%;
      height: 48px !important;
      border: 1.5px solid #cbd5e1;
      border-radius: 12px;
      padding: 12px 16px !important;
      font-size: 13.5px;
      color: #0f172a;
      background-color: #fff;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      box-sizing: border-box;
      font-weight: 500;
    }

    .input-premium:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
      outline: none;
      background-color: #fff;
    }

    /* ── Quick Filter Chips (One-Click Category Filters) ── */
    .filter-chip-bar {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 16px;
      padding-bottom: 14px;
      border-bottom: 1px dashed #e2e8f0;
    }

    .filter-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 30px;
      font-size: 12px;
      font-weight: 700;
      color: #475569;
      background: #f1f5f9;
      border: 1.5px solid transparent;
      text-decoration: none !important;
      transition: all 0.2s ease;
      cursor: pointer;
      user-select: none;
    }

    .filter-chip:hover {
      background: #e2e8f0;
      color: #0f172a;
      transform: translateY(-1px);
    }

    .filter-chip.active {
      background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
      color: #fff;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
      border-color: rgba(255, 255, 255, 0.2);
    }

    .filter-chip-tiptok.active {
      background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%);
      box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);
    }

    .filter-chip-dealer.active {
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
    }

    .filter-chip-installer.active {
      background: linear-gradient(135deg, #6d28d9 0%, #8b5cf6 100%);
      box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
    }

    .filter-chip-user.active {
      background: linear-gradient(135deg, #065f46 0%, #10b981 100%);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
    }

    .filter-chip-count {
      background: rgba(0, 0, 0, 0.08);
      color: inherit;
      font-size: 10.5px;
      font-weight: 800;
      padding: 2px 7px;
      border-radius: 12px;
    }

    .filter-chip.active .filter-chip-count {
      background: rgba(255, 255, 255, 0.25);
      color: #fff;
    }

    /* ── Category Pill Group (Radio Switcher in Form) ── */
    .category-pill-group {
      display: flex;
      gap: 10px;
    }

    .category-pill-label {
      cursor: pointer;
      margin: 0;
      flex: 1;
    }

    .category-pill-input {
      display: none;
    }

    .category-pill-span {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 48px;
      font-size: 12.5px;
      font-weight: 800;
      border-radius: 12px;
      border: 1.5px solid #cbd5e1;
      color: #64748b;
      background: #fff;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      box-sizing: border-box;
    }

    #kategori_dealer:checked + .span-dealer {
      border-color: #2563eb;
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      color: #1d4ed8;
      box-shadow: 0 4px 14px rgba(37, 99, 235, 0.15);
    }

    #kategori_installer:checked + .span-installer {
      border-color: #7c3aed;
      background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
      color: #6d28d9;
      box-shadow: 0 4px 14px rgba(124, 58, 237, 0.15);
    }

    #kategori_user:checked + .span-user {
      border-color: #059669;
      background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
      color: #047857;
      box-shadow: 0 4px 14px rgba(5, 150, 105, 0.15);
    }

    /* ── Avatars in Table with Vibrant Rings ── */
    .avatar-initials-table {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      color: #fff;
      font-size: 14px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 14px;
      vertical-align: middle;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      flex-shrink: 0;
      border: 2px solid #fff;
    }

    .avatar-initials-table:hover {
      transform: scale(1.1);
      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.18);
    }

    .avatar-tiptok-glow {
      box-shadow: 0 0 0 2.5px #f59e0b, 0 4px 14px rgba(245, 158, 11, 0.45) !important;
    }

    .customer-identity-cell {
      display: flex;
      align-items: center;
      text-align: left;
    }

    /* ── Vibrant Badges ── */
    .category-badge {
      font-size: 9.5px;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 8px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      display: inline-block;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
    }

    .badge-dealer {
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      color: #1e40af;
      border: 1px solid #bfdbfe;
    }

    .badge-installer {
      background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
      color: #6b21a8;
      border: 1px solid #e9d5ff;
    }

    .badge-user {
      background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
      color: #065f46;
      border: 1px solid #a7f3d0;
    }

    .badge-default {
      background: #f1f5f9;
      color: #475569;
      border: 1px solid #cbd5e1;
    }

    /* ── Glowing TIP TOK Badge ── */
    .badge-tiptok {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: #ffffff !important;
      font-size: 9px;
      font-weight: 800;
      padding: 3.5px 9px;
      border-radius: 30px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      box-shadow: 0 3px 10px rgba(245, 158, 11, 0.45);
      border: 1px solid rgba(255, 255, 255, 0.3);
      text-decoration: none !important;
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      cursor: pointer;
    }

    .badge-tiptok:hover {
      background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
      transform: translateY(-1.5px) scale(1.03);
      box-shadow: 0 6px 16px rgba(245, 158, 11, 0.6);
      color: #ffffff !important;
    }

    .badge-tiptok-qty {
      background: rgba(255, 255, 255, 0.28);
      color: #ffffff;
      font-size: 8.5px;
      font-weight: 900;
      padding: 1.5px 6px;
      border-radius: 12px;
      margin-left: 2px;
    }

    /* ── Table Styling ── */
    .premium-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }

    .premium-table th {
      background: #f8fafc;
      border-bottom: 2px solid #e2e8f0;
      color: #475569;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 18px 20px;
      text-align: left;
    }

    .premium-table td {
      padding: 18px 20px;
      border-bottom: 1px solid #f1f5f9;
      color: #334155;
      font-size: 13.5px;
      vertical-align: middle;
    }

    .premium-table tbody tr {
      transition: all 0.2s ease;
    }

    .premium-table tbody tr:hover {
      background-color: #f8fafc;
      box-shadow: inset 3px 0 0 0 #2563eb;
    }

    /* ── Solid WhatsApp Button ── */
    .wa-pill {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
      border: none !important;
      color: #fff !important;
      font-size: 12px;
      font-weight: 800;
      padding: 6px 14px;
      border-radius: 30px;
      text-decoration: none !important;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      width: fit-content;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }

    .wa-pill:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 18px rgba(16, 185, 129, 0.4);
      color: #fff !important;
    }

    /* ── Map Pin Button ── */
    .btn-map-pin {
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      border: 1px solid #bfdbfe;
      color: #1d4ed8;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.22s ease;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(37, 99, 235, 0.1);
    }

    .btn-map-pin:hover {
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      color: #fff;
      transform: scale(1.12);
      box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
    }

    /* ── Action Buttons ── */
    .btn-act {
      width: 36px;
      height: 36px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      border: 1px solid transparent;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      cursor: pointer;
      text-decoration: none !important;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .btn-act:hover {
      transform: scale(1.14);
    }

    .btn-act:active {
      transform: scale(0.96);
    }

    .btn-act .material-symbols-outlined {
      font-size: 18px;
    }

    .btn-act-tiptok {
      background: #fef3c7;
      color: #b45309;
      border-color: #fde68a;
    }

    .btn-act-tiptok:hover, .btn-act-tiptok.active {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: #ffffff;
      border-color: #d97706;
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.45);
    }

    .btn-act-view {
      background: #e0f2fe;
      color: #0369a1;
      border-color: #bae6fd;
      margin-left: 6px;
    }

    .btn-act-view:hover {
      background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
      color: #fff;
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
    }

    .btn-act-edit {
      background: #faf5ff;
      color: #6b21a8;
      border-color: #f3e8ff;
      margin-left: 6px;
    }

    .btn-act-edit:hover {
      background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
      color: #fff;
      box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35);
    }

    .btn-act-delete {
      background: #fef2f2;
      color: #dc2626;
      border-color: #fee2e2;
      margin-left: 6px;
    }

    .btn-act-delete:hover {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: #fff;
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
    }

    .btn-submit-premium {
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%);
      color: #fff !important;
      border: none;
      border-radius: 12px;
      padding: 12px 28px;
      font-size: 14px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 4px 20px rgba(37, 99, 235, 0.25);
      transition: all 0.22s ease;
      cursor: pointer;
    }

    .btn-submit-premium:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(37, 99, 235, 0.4);
    }

    /* ── Drag & Drop Zone ── */
    .dropzone-area {
      border: 2px dashed #cbd5e1;
      border-radius: 14px;
      background: #f8fafc;
      padding: 24px 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease-in-out;
      user-select: none;
    }

    .dropzone-area:hover, .dropzone-area.dragover {
      border-color: #2563eb;
      background: #eff6ff;
    }

    .dropzone-icon {
      font-size: 32px;
      color: #64748b;
      margin-bottom: 6px;
    }

    .dropzone-text {
      font-size: 12.5px;
      font-weight: 600;
      color: #475569;
      margin: 0;
    }

    .preview-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
      gap: 12px;
      margin-top: 14px;
    }

    .preview-item {
      position: relative;
      width: 80px;
      height: 80px;
      border-radius: 10px;
      overflow: hidden;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    }

    .preview-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .preview-remove {
      position: absolute;
      top: 3px;
      right: 3px;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: rgba(220, 38, 38, 0.95);
      color: #fff;
      font-size: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border: none;
      font-weight: 700;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    /* ── Mobile Responsive ── */
    @media (max-width: 991.98px) {
      .kpi-grid {
        grid-template-columns: repeat(2, 1fr) !important;
      }
      .card-body-premium {
        padding: 20px 16px !important;
      }
      .category-pill-group {
        flex-wrap: wrap !important;
      }
      .category-pill-label {
        flex: 1 1 calc(33.333% - 8px) !important;
        min-width: 85px !important;
      }
      .premium-table th, .premium-table td {
        padding: 12px 10px !important;
      }
    }

    /* ── Geofence Leaflet Interactive Maps & Senior-Friendly Design System ── */
    :root {
      --kb-primary: #1d4ed8;
      --kb-primary-hover: #1e40af;
      --kb-primary-light: #eff6ff;
      --kb-primary-border: #93c5fd;
      --kb-slate-950: #020617;
      --kb-slate-900: #0f172a;
      --kb-slate-800: #1e293b;
      --kb-slate-700: #334155;
      --kb-slate-600: #475569;
      --kb-slate-500: #64748b;
      --kb-slate-400: #94a3b8;
      --kb-slate-300: #cbd5e1;
      --kb-slate-200: #e2e8f0;
      --kb-slate-100: #f1f5f9;
      --kb-slate-50: #f8fafc;
      --kb-radius-lg: 18px;
      --kb-radius-md: 12px;
      --kb-radius-sm: 8px;
    }

    .kb-section-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #1e40af;
      background: #eff6ff;
      border: 1.5px solid #93c5fd;
      padding: 6px 14px;
      border-radius: 20px;
      margin-bottom: 20px;
    }
    .kb-section-badge-green {
      color: #065f46;
      background: #ecfdf5;
      border-color: #86efac;
    }
    .kb-section-badge .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: currentColor;
    }

    .kb-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 13.5px;
      font-weight: 700;
      color: var(--kb-slate-900);
      margin-bottom: 8px;
    }
    .kb-label-icon {
      display: inline-flex;
      align-items: center;
      gap: 7px;
    }
    .kb-label-icon i {
      font-size: 16px;
      color: var(--kb-primary);
    }

    .kb-input {
      width: 100%;
      height: 48px;
      background-color: #ffffff;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-md);
      padding: 10px 16px;
      font-size: 14px;
      font-weight: 600;
      color: var(--kb-slate-950);
      transition: all 0.2s ease;
      outline: none;
    }
    .kb-input:focus {
      border-color: var(--kb-primary);
      box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.18);
      background-color: #ffffff;
    }
    .kb-input::placeholder {
      color: var(--kb-slate-400);
      font-size: 13.5px;
      font-weight: 500;
    }
    textarea.kb-input {
      height: auto;
      min-height: 80px;
      line-height: 1.5;
      resize: vertical;
    }

    .kb-select {
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='%231e293b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
      background-repeat: no-repeat;
      background-position: right 14px center;
      background-size: 16px;
      padding-right: 38px !important;
      cursor: pointer;
    }

    .kb-map-search-wrap {
      display: flex;
      gap: 8px;
    }

    .kb-radius-presets {
      display: flex;
      gap: 6px;
      margin-top: 10px;
    }
    .kb-preset-btn {
      flex: 1;
      padding: 6px 4px;
      text-align: center;
      font-size: 12px;
      font-weight: 700;
      border: 1.5px solid var(--kb-slate-300);
      border-radius: 8px;
      background: #ffffff;
      cursor: pointer;
      transition: all 0.15s;
      user-select: none;
    }
    .kb-preset-btn:hover {
      background: var(--kb-slate-100);
      border-color: var(--kb-slate-400);
    }
    .kb-preset-btn.active {
      background: var(--kb-primary);
      border-color: var(--kb-primary);
      color: #ffffff;
    }

    .kb-coord-badge {
      background: #f8fafc;
      border: 2px solid var(--kb-slate-300);
      border-radius: var(--kb-radius-sm);
      padding: 8px 12px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 13px;
      color: var(--kb-slate-950);
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .kb-coord-label {
      font-size: 10px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 800;
      color: var(--kb-slate-500);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .kb-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 24px;
      font-size: 14.5px;
      font-weight: 700;
      border-radius: var(--kb-radius-md);
      transition: all 0.2s ease;
      cursor: pointer;
      text-decoration: none;
      border: none;
    }
    .kb-btn-secondary {
      background: #ffffff;
      color: var(--kb-slate-800);
      border: 2px solid var(--kb-slate-300);
    }
    .kb-btn-secondary:hover {
      background: var(--kb-slate-100);
      border-color: var(--kb-slate-500);
      color: var(--kb-slate-950);
    }
    .kb-btn-primary {
      background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
      color: #ffffff !important;
      box-shadow: 0 4px 16px rgba(29, 78, 216, 0.35);
    }
    .kb-btn-primary:hover {
      background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
      box-shadow: 0 6px 24px rgba(29, 78, 216, 0.45);
      transform: translateY(-2px);
    }

    #edit_kategori_dealer:checked + .span-dealer {
      border-color: #2563eb;
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      color: #1d4ed8;
      box-shadow: 0 4px 14px rgba(37, 99, 235, 0.15);
    }
    #edit_kategori_installer:checked + .span-installer {
      border-color: #7c3aed;
      background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
      color: #6d28d9;
      box-shadow: 0 4px 14px rgba(124, 58, 237, 0.15);
    }
    #edit_kategori_user:checked + .span-user {
      border-color: #059669;
      background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
      color: #065f46;
      box-shadow: 0 4px 14px rgba(5, 150, 105, 0.15);
    }

    #map_create, #map_edit, .leaflet-geofence-map {
      height: 280px !important;
      min-height: 280px !important;
      width: 100% !important;
      border-radius: 14px !important;
      border: 2px solid #cbd5e1 !important;
      margin-top: 10px !important;
      margin-bottom: 8px !important;
      background: #f8fafc !important;
      position: relative !important;
      z-index: 1 !important;
      overflow: hidden !important;
      box-shadow: 0 4px 14px rgba(0,0,0,0.05);
    }
  </style>
</head>
<body class="g-sidenav-show bg-gray-200">
<?php include "cek-menu.php"; ?>
<main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
  <?php include "nav-top.php"; ?>
  <div class="container-fluid py-4">
    
    <!-- Success Alert -->
    <?php if (!empty($successMsg)): ?>
      <div class="alert alert-success text-white font-weight-bold mb-4" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); border: none; border-radius: 12px; padding: 14px 20px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);">
        <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">check_circle</span>
        <?php echo $successMsg; ?>
      </div>
    <?php endif; ?>

    <!-- Error Alert -->
    <?php if (!empty($errorMsg)): ?>
      <div class="alert alert-danger text-white font-weight-bold mb-4" style="background: linear-gradient(135deg, #e11d48 0%, #f43f5e 100%); border: none; border-radius: 12px; padding: 14px 20px; box-shadow: 0 4px 14px rgba(244, 63, 94, 0.25);">
        <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">error</span>
        <?php echo $errorMsg; ?>
      </div>
    <?php endif; ?>

    <!-- ── VIBRANT STAT METRIC CARDS ── -->
    <div class="kpi-grid">
      <!-- 1. Total Customer -->
      <a href="customer.php" class="kpi-card kpi-card-blue">
        <div class="kpi-deco-circle" style="top: -20px; right: -20px; width: 110px; height: 110px;"></div>
        <div class="kpi-deco-circle" style="bottom: -30px; left: 40px; width: 80px; height: 80px;"></div>
        <div class="d-flex justify-content-between align-items-start position-relative">
          <div>
            <p class="kpi-label">Total Customer</p>
            <h3 class="kpi-value"><?= number_format($statTotalCust); ?></h3>
          </div>
          <div class="kpi-icon-badge">
            <span class="material-symbols-outlined">storefront</span>
          </div>
        </div>
        <div class="kpi-sub position-relative">
          <span class="badge bg-white text-dark font-weight-bold" style="font-size:10px; padding:3px 8px; border-radius:20px; margin-right:4px;">Aktif</span>
          Database Mitra Terdaftar
        </div>
      </a>

      <!-- 2. Mitra TIP TOK -->
      <a href="customer.php?kategori=tiptok" class="kpi-card kpi-card-amber">
        <div class="kpi-deco-circle" style="top: -25px; right: -15px; width: 120px; height: 120px; background: rgba(255,255,255,0.12);"></div>
        <div class="d-flex justify-content-between align-items-start position-relative">
          <div>
            <p class="kpi-label">🏷️ Mitra TIP TOK</p>
            <h3 class="kpi-value"><?= number_format($statTiptok); ?> <span style="font-size:14px; font-weight:700;">Toko</span></h3>
          </div>
          <div class="kpi-icon-badge" style="background: rgba(255,255,255,0.25);">
            <i class="fa-solid fa-box-open" style="font-size:18px;"></i>
          </div>
        </div>
        <div class="kpi-sub position-relative">
          <span class="badge bg-white text-warning font-weight-bold" style="font-size:10px; padding:3px 8px; border-radius:20px; margin-right:4px;">Konsinyasi</span>
          Stok Display Dititipkan
        </div>
      </a>

      <!-- 3. Dealer & Installer -->
      <a href="customer.php?kategori=Dealer" class="kpi-card kpi-card-purple">
        <div class="kpi-deco-circle" style="top: -15px; right: -25px; width: 100px; height: 100px;"></div>
        <div class="d-flex justify-content-between align-items-start position-relative">
          <div>
            <p class="kpi-label">Dealer &amp; Mitra</p>
            <h3 class="kpi-value"><?= number_format($statDealer); ?> <span style="font-size:14px; font-weight:600; opacity:0.85;">/ <?= number_format($statInstaller); ?> Inst.</span></h3>
          </div>
          <div class="kpi-icon-badge">
            <span class="material-symbols-outlined">verified</span>
          </div>
        </div>
        <div class="kpi-sub position-relative">
          <?= number_format($statDealer); ?> Dealer • <?= number_format($statInstaller); ?> Installer • <?= number_format($statUser); ?> User
        </div>
      </a>

      <!-- 4. Geofence GPS -->
      <div class="kpi-card kpi-card-emerald">
        <div class="kpi-deco-circle" style="top: -20px; right: -20px; width: 110px; height: 110px;"></div>
        <div class="d-flex justify-content-between align-items-start position-relative">
          <div>
            <p class="kpi-label">Geofence Lokasi</p>
            <h3 class="kpi-value"><?= number_format($statMapped); ?> <span style="font-size:14px; font-weight:600; opacity:0.85;">/ <?= number_format($statTotalCust); ?></span></h3>
          </div>
          <div class="kpi-icon-badge">
            <span class="material-symbols-outlined">location_on</span>
          </div>
        </div>
        <div class="kpi-sub position-relative">
          <span class="badge bg-white text-success font-weight-bold" style="font-size:10px; padding:3px 8px; border-radius:20px; margin-right:4px;">
            <?= $statTotalCust > 0 ? round(($statMapped / $statTotalCust) * 100) : 0 ?>% Terpetakan
          </span>
          Koordinat GPS Toko
        </div>
      </div>
    </div>

    <!-- Card Tambah Sales Customer (COLLAPSIBLE / Buka Tutup Drawer) -->
    <div class="card-premium">
      <!-- Premium Gradient Header with Manual Click Trigger -->
      <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 40%,#2563eb 100%);padding:24px 36px;position:relative;overflow:hidden;cursor:pointer;"
           id="tambahCustomerHeader">
          <div style="position:absolute;top:-40px;right:-20px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
          <div style="position:absolute;bottom:-50px;right:100px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,0.03);"></div>
          <div style="position:absolute;top:10px;right:30px;width:60px;height:60px;border-radius:50%;background:rgba(59,130,246,0.2);"></div>
          <div style="display:flex;align-items:center;justify-content:space-between;position:relative;z-index:1;user-select:none;">
              <div style="display:flex;align-items:center;gap:14px;">
                  <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.12);backdrop-filter:blur(12px);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.1);">
                      <span class="material-symbols-outlined" style="color:#fff;font-size:22px;">person_add</span>
                  </div>
                  <div>
                      <h5 style="color:#fff;margin:0;font-size:18px;font-weight:700;letter-spacing:-0.3px;">Tambah Sales Customer</h5>
                      <p style="color:rgba(255,255,255,0.6);margin:0;font-size:12px;margin-top:2px;">Daftarkan toko, mitra, atau installer baru beserta wilayah kerjanya</p>
                  </div>
              </div>
              <!-- Collapsible Toggle Badge indicator -->
              <div style="display:flex; align-items:center; gap:8px;">
                  <span id="toggleText" style="color:#fff; font-size:12px; font-weight:800; background:rgba(255,255,255,0.1); padding:6px 14px; border-radius:30px; border: 1px solid rgba(255,255,255,0.15); text-transform:uppercase; letter-spacing:0.04em;">Tampilkan Form</span>
                  <span class="material-symbols-outlined" id="toggleChevron" style="color:#fff; font-size:22px; transition: transform 0.3s ease-in-out;">expand_more</span>
              </div>
          </div>
      </div>

      <!-- Collapsible Container (Collapsed by Default via inline style display: none) -->
      <div id="collapseTambahCustomer" style="display: none; border-top: 1px solid #f1f5f9;">
        <div class="card-body-premium">
          <form method="POST" enctype="multipart/form-data" id="createCustomerForm">
            <div class="row">
              
              <!-- LEFT COLUMN: Form Fields -->
              <div class="col-lg-7" style="border-right: 1px solid #f1f5f9; padding-right: 32px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <div style="width:3px;height:16px;background:#3b82f6;border-radius:2px;"></div>
                    <span style="font-size:12px;font-weight:800;color:#1e293b;text-transform: uppercase; letter-spacing: 0.05em;">Informasi Customer</span>
                </div>

                <div class="row">
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">
                      <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">store</span> Nama Toko / Mitra / Personal
                    </label>
                    <input type="text" name="nama" class="input-premium" placeholder="Masukkan nama customer..." required>
                  </div>
                  
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">
                      <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">category</span> Kategori Customer
                    </label>
                    <div class="category-pill-group">
                      <label class="category-pill-label" for="kategori_dealer">
                        <input class="category-pill-input" type="radio" name="kategori" id="kategori_dealer" value="Dealer" required checked>
                        <span class="category-pill-span span-dealer">Dealer</span>
                      </label>
                      <label class="category-pill-label" for="kategori_installer">
                        <input class="category-pill-input" type="radio" name="kategori" id="kategori_installer" value="Installer">
                        <span class="category-pill-span span-installer">Installer</span>
                      </label>
                      <label class="category-pill-label" for="kategori_user">
                        <input class="category-pill-input" type="radio" name="kategori" id="kategori_user" value="User">
                        <span class="category-pill-span span-user">User</span>
                      </label>
                    </div>
                  </div>

                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">
                      <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">map</span> Wilayah Customer
                    </label>
                    <select name="id_wilayah" class="input-premium" required>
                      <option value="">-- Pilih Wilayah --</option>
                      <?php 
                      $wQuery = mysqli_query($conn, "SELECT * FROM wilayah WHERE deleted_at IS NULL ORDER BY nama ASC");
                      while ($w = mysqli_fetch_assoc($wQuery)) {
                          echo "<option value='{$w['id']}'>" . htmlspecialchars($w['nama']) . "</option>";
                      }
                      ?>
                    </select>
                  </div>
                  
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">
                      <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">call</span> No. Telepon (WhatsApp)
                    </label>
                    <input type="text" name="telp" class="input-premium" placeholder="Contoh: 0812345678">
                  </div>
                  
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">
                      <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">mail</span> Email Customer
                    </label>
                    <input type="email" name="email" class="input-premium" placeholder="Contoh: customer@loewix.com">
                  </div>
                  
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">
                      <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">location_city</span> Kota
                    </label>
                    <input type="text" name="kota" class="input-premium" placeholder="Masukkan kota asal customer...">
                  </div>

                  <!-- Drag & Drop Multiple Photos -->
                  <div class="col-md-12 form-group-premium">
                    <label class="form-label-premium">
                      <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">image</span> Foto Dokumentasi Toko / Gudang / Pabrik (Maksimal 5 Foto)
                    </label>
                    <div class="dropzone-area" id="dropzone_create">
                      <span class="material-symbols-outlined dropzone-icon">cloud_upload</span>
                      <p class="dropzone-text">Drag &amp; drop file foto di sini, atau klik untuk memilih</p>
                      <input type="file" id="foto_input_create" name="foto[]" multiple accept="image/*" class="d-none">
                    </div>
                    <div class="preview-grid" id="preview_grid_create"></div>
                  </div>
                  
                  <div class="col-md-12 form-group-premium">
                    <label class="form-label-premium">
                      <span class="material-symbols-outlined" style="font-size:16px; color:#3b82f6;">home_pin</span> Alamat Lengkap
                    </label>
                    <input type="text" name="alamat" class="input-premium" placeholder="Masukkan alamat lengkap toko/mitra...">
                  </div>

                  <!-- TIP TOK Store Switch -->
                  <div class="col-md-12 form-group-premium mt-2">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(245, 158, 11, 0.08); border: 1.5px dashed rgba(245, 158, 11, 0.4);">
                      <div class="d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined text-warning" style="font-size:22px;">inventory_2</span>
                        <div>
                          <span style="font-weight:800; font-size:13px; color:#92400e; display:block;">Mitra TIP TOK (Konsinyasi Toko)</span>
                          <span style="font-size:11.5px; color:#b45309;">Aktifkan tanda ini jika toko dititipkan barang konsinyasi display Loewix</span>
                        </div>
                      </div>
                      <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_tiptok" id="create_is_tiptok" value="1" style="width: 2.2em; height: 1.2em; cursor: pointer;">
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- RIGHT COLUMN: Location Map -->
              <div class="col-lg-5" style="padding-left: 32px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <div style="width:3px;height:16px;background:#10b981;border-radius:2px;"></div>
                    <span style="font-size:12px;font-weight:800;color:#1e293b;text-transform: uppercase; letter-spacing: 0.05em;">Lokasi Koordinat Toko (Geofence)</span>
                </div>

                <div class="form-group-premium">
                  <label class="form-label-premium">
                    <i class="fa-solid fa-magnifying-glass text-xs me-1 text-primary"></i> Cari Alamat / Koordinat
                  </label>
                  <div class="d-flex gap-2 align-items-center" style="width: 100%;">
                    <input type="text" id="gmap_search" class="input-premium" placeholder="Contoh: Jawa Timur atau -6.175, 106.827..." style="flex: 1 1 auto; min-width: 0;">
                    <button type="button" id="gmap_search_btn" class="btn btn-primary font-weight-bold d-inline-flex align-items-center justify-content-center gap-1.5 flex-shrink-0" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); color: #ffffff !important; border: none; border-radius: 10px; height: 48px; padding: 0 20px; font-size: 13px; font-weight: 700; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); cursor: pointer; transition: all 0.2s ease; margin-bottom: 0; white-space: nowrap;">
                      <i class="bi bi-search"></i>
                      <span>CARI</span>
                    </button>
                  </div>
                </div>

                <!-- Leaflet Map create -->
                <div id="map_create" class="leaflet-geofence-map" style="height: 250px; min-height: 250px; width: 100%; border-radius: 12px; border: 1.5px solid #cbd5e1; margin-top: 10px; margin-bottom: 8px; z-index: 1;"></div>

                <!-- GPS button and Radius input -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                  <button type="button" id="btn_get_location_create" class="btn btn-outline-primary btn-sm mb-0 d-flex align-items-center gap-1 font-weight-bold" style="border-radius: 8px; font-size: 11px;">
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
                    <span id="slider_val_create" class="text-primary font-weight-bold">100m</span>
                  </label>
                  <input type="range" id="radius_slider_create" min="10" max="1000" step="10" value="100" class="form-range w-100" style="accent-color: #3b82f6;">
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
            <div class="mt-3">
              <button type="submit" class="btn-submit-premium" style="width: 100%; justify-content: center;">
                <span class="material-symbols-outlined">save</span>
                Simpan Customer &amp; Koordinat Lokasi
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Card Daftar Sales Customer -->
    <div class="card-premium">
      <!-- Premium Gradient Header -->
      <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 40%,#2563eb 100%);padding:20px 28px;position:relative;overflow:hidden;">
          <div style="position:absolute;top:-40px;right:-20px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
          <div style="display:flex;align-items:center;justify-content:space-between;position:relative;z-index:1;">
              <div style="display:flex;align-items:center;gap:12px;">
                  <div style="width:38px;height:38px;border-radius:12px;background:rgba(255,255,255,0.12);backdrop-filter:blur(12px);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.18);">
                      <span class="material-symbols-outlined" style="color:#fff;font-size:20px;">groups</span>
                  </div>
                  <div>
                      <h5 style="color:#fff;margin:0;font-size:16px;font-weight:800;letter-spacing:-0.2px;">Daftar Sales Customer</h5>
                      <span style="color:rgba(255,255,255,0.7); font-size:11.5px; font-weight:600;">Kelola data toko mitra, dealer, konsinyasi, dan lokasi geofence</span>
                  </div>
              </div>
              <span class="badge bg-white text-dark font-weight-bold" style="font-size: 11.5px; padding: 7px 16px; border-radius: 30px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"><?= mysqli_num_rows($salesData); ?> Customer Ditampilkan</span>
          </div>
      </div>
      
      <!-- Premium Filter Bar with Quick Chips -->
      <div style="background:#f8fafc; padding:20px 28px; border-bottom:1px solid #e2e8f0;">
          
          <!-- 1-Click Quick Filter Chips -->
          <div class="filter-chip-bar">
            <span style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; margin-right: 4px;">Quick Filter:</span>
            
            <a href="customer.php" class="filter-chip <?= ($filterKategori === 'all' && $filterWilayah === 'all' && empty($filterSearch)) ? 'active' : ''; ?>">
              <span>Semua</span>
              <span class="filter-chip-count"><?= $statTotalCust ?></span>
            </a>

            <a href="customer.php?kategori=tiptok" class="filter-chip filter-chip-tiptok <?= ($filterKategori === 'tiptok') ? 'active' : ''; ?>">
              <i class="fa-solid fa-box-open" style="font-size:11px;"></i>
              <span>Toko TIP TOK</span>
              <span class="filter-chip-count"><?= $statTiptok ?></span>
            </a>

            <a href="customer.php?kategori=Dealer" class="filter-chip filter-chip-dealer <?= ($filterKategori === 'Dealer') ? 'active' : ''; ?>">
              <span>🏢 Dealer</span>
              <span class="filter-chip-count"><?= $statDealer ?></span>
            </a>

            <a href="customer.php?kategori=Installer" class="filter-chip filter-chip-installer <?= ($filterKategori === 'Installer') ? 'active' : ''; ?>">
              <span>🔧 Installer</span>
              <span class="filter-chip-count"><?= $statInstaller ?></span>
            </a>

            <a href="customer.php?kategori=User" class="filter-chip filter-chip-user <?= ($filterKategori === 'User') ? 'active' : ''; ?>">
              <span>👤 User</span>
              <span class="filter-chip-count"><?= $statUser ?></span>
            </a>
          </div>

          <form method="GET" action="customer.php" class="row g-3 align-items-end">
              <div class="col-12 col-md-4">
                  <label class="form-label text-xs font-weight-bold text-uppercase text-secondary mb-1">Cari Customer</label>
                  <div class="input-group input-group-outline">
                      <input type="text" name="search" class="form-control bg-white" placeholder="Nama toko, kode, telp, atau alamat..." value="<?= htmlspecialchars($filterSearch); ?>">
                  </div>
              </div>
              <div class="col-6 col-md-3">
                  <label class="form-label text-xs font-weight-bold text-uppercase text-secondary mb-1">Wilayah / Area</label>
                  <div class="input-group input-group-outline">
                      <select name="wilayah" class="form-select form-control bg-white px-3" style="border:1.5px solid #cbd5e1; border-radius:0.5rem; -webkit-appearance: auto; -moz-appearance: auto; appearance: auto; font-size:13px; font-weight:600;">
                          <option value="all">Semua Wilayah</option>
                          <?php 
                          mysqli_data_seek($wilayahList, 0);
                          while($w = mysqli_fetch_assoc($wilayahList)): 
                          ?>
                              <option value="<?= $w['id']; ?>" <?= ($filterWilayah == $w['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($w['nama']); ?></option>
                          <?php endwhile; ?>
                      </select>
                  </div>
              </div>
              <div class="col-6 col-md-3">
                  <label class="form-label text-xs font-weight-bold text-uppercase text-secondary mb-1">Kategori</label>
                  <div class="input-group input-group-outline">
                      <select name="kategori" class="form-select form-control bg-white px-3" style="border:1.5px solid #cbd5e1; border-radius:0.5rem; -webkit-appearance: auto; -moz-appearance: auto; appearance: auto; font-size:13px; font-weight:600;">
                          <option value="all">Semua Kategori</option>
                          <option value="tiptok" <?= ($filterKategori === 'tiptok') ? 'selected' : ''; ?>>📦 Toko Mitra TIP TOK</option>
                          <?php foreach($kategoriList as $k): ?>
                              <option value="<?= $k; ?>" <?= ($filterKategori === $k) ? 'selected' : ''; ?>><?= $k; ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
              </div>
              <div class="col-12 col-md-2 d-flex gap-2">
                  <button type="submit" class="btn bg-gradient-info w-100 mb-0 font-weight-bold" style="padding:10.5px 20px; border-radius:10px;">
                      <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle; margin-right:4px;">search</span> Filter
                  </button>
                  <?php if($filterSearch !== '' || $filterWilayah !== 'all' || $filterKategori !== 'all'): ?>
                      <a href="customer.php" class="btn bg-gradient-secondary mb-0 font-weight-bold" style="padding:10.5px 15px; border-radius:10px;" title="Reset Filter">
                          <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">restart_alt</span>
                      </a>
                  <?php endif; ?>
              </div>
          </form>
      </div>
      
      <div class="table-responsive">
        <table class="premium-table">
          <thead>
            <tr>
              <th style="width: 50px; text-align: center;">No</th>
              <th style="min-width: 220px;">Customer / Toko</th>
              <th style="width: 170px;">Kontak Utama</th>
              <th>Alamat &amp; Kota</th>
              <th style="width: 130px; text-align: center;">Tgl Daftar</th>
              <th style="width: 70px; text-align: center;">Geofence</th>
              <th style="width: 150px; text-align: center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($salesData)): 
              $kat = htmlspecialchars($row['kategori'] ?? '');
              $badgeClass = match($kat) {
                'Dealer' => 'badge-dealer',
                'Installer' => 'badge-installer',
                'User' => 'badge-user',
                default => 'badge-default'
              };
              $avatarBg = match($kat) {
                'Dealer' => '#3b82f6',
                'Installer' => '#8b5cf6',
                'User' => '#10b981',
                default => '#64748b'
              };
              
              // Parse multiple photos JSON
              $photos = [];
              $foto_json = $row['foto'] ?? '';
              if (!empty($foto_json)) {
                  $photos = json_decode($foto_json, true);
                  if (!is_array($photos)) {
                      $photos = [];
                  }
              }
              $firstPhoto = !empty($photos) ? $photos[0] : '';
              
              // Wilayah Colorful Badges
              $regionName = $row['nama_wilayah'] ?? 'Tanpa Wilayah';
              if (stripos($regionName, 'Jabodetabek') !== false) {
                  $wBadge = 'background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: #fff;';
              } elseif (stripos($regionName, 'Jawa Timur') !== false) {
                  $wBadge = 'background: linear-gradient(135deg, #7c2d12, #ea580c); color: #fff;';
              } elseif (stripos($regionName, 'Jawa Tengah') !== false) {
                  $wBadge = 'background: linear-gradient(135deg, #065f46, #10b981); color: #fff;';
              } elseif (stripos($regionName, 'Jawa Barat') !== false) {
                  $wBadge = 'background: linear-gradient(135deg, #5b21b6, #8b5cf6); color: #fff;';
              } else {
                  $wBadge = 'background: linear-gradient(135deg, #374151, #4b5563); color: #fff;';
              }

              // TIP TOK Status & Consignment details
              $isTiptokManual = intval($row['is_tiptok'] ?? 0);
              $tiptokAktifCount = intval($row['tiptok_aktif_count'] ?? 0);
              $tiptokTotalCount = intval($row['tiptok_total_count'] ?? 0);
              $tiptokSisa = intval($row['tiptok_sisa_qty'] ?? 0);
              $isTiptok = ($isTiptokManual === 1 || $tiptokAktifCount > 0 || $tiptokTotalCount > 0);
            ?>
            <tr>
              <td style="text-align: center; font-weight: 700; color: #64748b; font-size:12px;"><?= $no++; ?></td>
              
              <td>
                <div class="customer-identity-cell">
                  <!-- Gallery Trigger Avatar -->
                  <?php 
                    $avatarClass = 'avatar-initials-table' . ($isTiptok ? ' avatar-tiptok-glow' : '');
                  ?>
                  <?php if (!empty($firstPhoto) && file_exists("../uploads/customer/" . $firstPhoto)): ?>
                    <div class="<?= $avatarClass ?> openGalleryBtn" 
                         style="background: <?= $avatarBg; ?>; overflow: hidden; padding: 0;"
                         data-photos='<?= htmlspecialchars(json_encode($photos)); ?>'
                         data-name="<?= htmlspecialchars($row['nama'] ?? ''); ?>">
                      <img src="../uploads/customer/<?= htmlspecialchars($firstPhoto); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                  <?php else: ?>
                    <div class="<?= $avatarClass ?>" style="background: <?= $avatarBg; ?>; cursor: default;">
                      <?php 
                        $words = explode(' ', $row['nama'] ?? '');
                        echo strtoupper(substr($words[0] ?? '', 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                      ?>
                    </div>
                  <?php endif; ?>
                  
                  <div style="display:flex; flex-direction:column; gap:4px;">
                    <span style="font-weight: 700; color: #0f172a; font-size:14px; line-height:1.2;"><?= htmlspecialchars($row['nama'] ?? ''); ?></span>
                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                      <span class="category-badge <?= $badgeClass; ?>"><?= $kat; ?></span>
                      <span class="badge text-capitalize" style="font-size: 8.5px; padding: 3px 8px; font-weight: 700; letter-spacing: 0.05em; border-radius:30px; text-transform: uppercase; <?= $wBadge; ?>">
                        <?= htmlspecialchars($regionName); ?>
                      </span>
                      <?php if ($isTiptok): ?>
                        <a href="tiptok.php?search=<?= urlencode($row['nama'] ?? ''); ?>" target="_blank" class="badge-tiptok" title="Toko Mitra TIP TOK (Konsinyasi). Klik untuk lihat konsinyasi">
                          <i class="fa-solid fa-box-open"></i> TIP TOK
                          <?php if ($tiptokSisa > 0): ?>
                            <span class="badge-tiptok-qty"><?= $tiptokSisa ?> Unit</span>
                          <?php endif; ?>
                        </a>
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($row['kode_customer'])): ?>
                      <span style="font-size:10px; font-family:monospace; color:#3b82f6; font-weight:700; letter-spacing:0.5px;"><?= htmlspecialchars($row['kode_customer']); ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              
              <td>
                <div style="display:flex; flex-direction:column; gap:6px;">
                  <?php if (!empty($row['telp_pribadi'])): ?>
                  <a href="https://wa.me/<?= htmlspecialchars($row['telp_pribadi'] ?? ''); ?>" target="_blank" class="wa-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16">
                      <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.949h.004c4.368 0 7.927-3.558 7.93-7.93a7.9 7.9 0 0 0 -2.327-5.607zM7.994 14.52a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.69-3.186c-.202-.1-.444-.201-.645-.299-.202-.1-.303-.05-.444.1l-.273.333c-.11.14-.22.15-.42.05-.2-.1-.843-.312-1.605-.985-.59-.525-.989-1.177-1.105-1.378-.11-.2-.011-.307.09-.407.09-.09.202-.233.303-.352.1-.11.14-.19.202-.32.06-.13.03-.242-.015-.342-.045-.1-.403-.974-.552-1.332-.146-.352-.295-.302-.404-.307-.105-.005-.227-.005-.35-.005-.122 0-.323.046-.492.23-.169.183-.645.63-.645 1.537 0 .907.66 1.784.75 1.907.09.124 1.3 1.982 3.148 2.776.44.19.784.303 1.05.388.442.14.843.12 1.16.073.352-.053 1.082-.442 1.233-.87.152-.427.152-.792.107-.87-.046-.078-.169-.124-.37-.224"/>
                    </svg>
                    <?= htmlspecialchars(preg_replace('/^62/', '0', $row['telp_pribadi'] ?? '')); ?>
                  </a>
                  <?php else: ?>
                  <span class="text-muted" style="font-size:12px;">-</span>
                  <?php endif; ?>
                  
                  <?php if (!empty($row['email']) && $row['email'] !== '-'): ?>
                  <span style="color: #64748b; font-size:12px; font-family: monospace; overflow:hidden; text-overflow:ellipsis; max-width:180px; display:block;" title="<?= htmlspecialchars($row['email']); ?>">
                    <?= htmlspecialchars($row['email']); ?>
                  </span>
                  <?php endif; ?>
                </div>
              </td>
              
              <td>
                <div style="display:flex; flex-direction:column; gap:4px;">
                  <span style="font-size: 12.5px; color: #475569; font-weight:500; line-height: 1.4; display:block; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($row['alamat'] ?? ''); ?>">
                    <?= htmlspecialchars($row['alamat'] ?? '-'); ?>
                  </span>
                  <?php if (!empty($row['kota']) && $row['kota'] !== '-'): ?>
                  <span style="font-weight: 700; color: #1e293b; font-size:11px; text-transform:uppercase; letter-spacing:0.02em;">
                    📍 <?= htmlspecialchars($row['kota']); ?>
                  </span>
                  <?php endif; ?>
                </div>
              </td>
              
              <td style="text-align: center;">
                <?php 
                  $tglDaftar = '-';
                  $jamDaftar = '';
                  if (!empty($row['created_at']) && $row['created_at'] !== '0000-00-00 00:00:00') {
                      $ts = strtotime($row['created_at']);
                      $tglDaftar = date('d/m/Y', $ts);
                      $jamDaftar = date('H:i', $ts);
                  }
                ?>
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center;">
                  <span style="font-size:12px; font-weight:700; color:#1e293b; white-space:nowrap; display:inline-flex; align-items:center; gap:4px;">
                    <span class="material-symbols-outlined" style="font-size:14px; color:#3b82f6;">calendar_today</span>
                    <?= $tglDaftar ?>
                  </span>
                  <?php if ($jamDaftar): ?>
                    <span style="font-size:10.5px; font-weight:600; color:#64748b; font-family:monospace;"><?= $jamDaftar ?> WIB</span>
                  <?php endif; ?>
                </div>
              </td>
              
              <td style="text-align: center;">
                <?php if (!empty($row['lat']) && !empty($row['lon'])): ?>
                  <button type="button" class="btn-map-pin openMapModalBtn" 
                          data-lat="<?= htmlspecialchars($row['lat']); ?>"
                          data-lon="<?= htmlspecialchars($row['lon']); ?>"
                          data-rad="<?= htmlspecialchars($row['rad'] ?? '100'); ?>"
                          data-name="<?= htmlspecialchars($row['nama'] ?? ''); ?>"
                          data-alamat="<?= htmlspecialchars($row['alamat_lokasi'] ?? ''); ?>"
                          title="Lihat Peta Lokasi Toko">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1, 'wght' 700;">location_on</span>
                  </button>
                <?php else: ?>
                  <span class="material-symbols-outlined text-muted" style="font-size: 20px; opacity:0.35;" title="Lokasi Belum Diset">location_off</span>
                <?php endif; ?>
              </td>
              
              <td style="text-align: center;">
                <!-- QUICK TIPTOK TOGGLE BUTTON -->
                <button type="button" class="btn-act btn-act-tiptok <?= $isTiptokManual ? 'active' : '' ?>" 
                  onclick="toggleTiptokQuick(<?= $row['id']; ?>, this)" 
                  title="<?= $isTiptokManual ? 'Mitra TIP TOK Aktif (Klik untuk lepas tanda)' : 'Tandai Toko sebagai Mitra TIP TOK' ?>">
                  <i class="fa-solid fa-box-open"></i>
                </button>

                <!-- VIEW DETAIL BUTTON -->
                <button type="button" class="btn-act btn-act-view viewDetailBtn"
                  data-id="<?= $row['id']; ?>"
                  data-nama="<?= htmlspecialchars($row['nama'] ?? ''); ?>"
                  data-kategori="<?= htmlspecialchars($row['kategori'] ?? ''); ?>"
                  data-is-tiptok="<?= $isTiptok ? '1' : '0' ?>"
                  data-is-tiptok-manual="<?= $isTiptokManual ?>"
                  data-tiptok-sisa="<?= $tiptokSisa ?>"
                  data-telp="<?= htmlspecialchars($row['telp_pribadi'] ?? ''); ?>"
                  data-email="<?= htmlspecialchars($row['email'] ?? ''); ?>"
                  data-alamat="<?= htmlspecialchars($row['alamat'] ?? ''); ?>"
                  data-kota="<?= htmlspecialchars($row['kota'] ?? ''); ?>"
                  data-foto='<?= htmlspecialchars(json_encode($photos)); ?>'
                  data-id-wilayah="<?= $row['id_wilayah']; ?>"
                  data-wilayah="<?= htmlspecialchars($regionName); ?>"
                  data-lat="<?= htmlspecialchars($row['lat'] ?? ''); ?>"
                  data-lon="<?= htmlspecialchars($row['lon'] ?? ''); ?>"
                  data-rad="<?= htmlspecialchars($row['rad'] ?? ''); ?>"
                  data-alamat-lokasi="<?= htmlspecialchars($row['alamat_lokasi'] ?? ''); ?>"
                  data-created="<?= htmlspecialchars(!empty($row['created_at']) && $row['created_at'] !== '0000-00-00 00:00:00' ? date('d F Y, H:i', strtotime($row['created_at'])).' WIB' : '-'); ?>"
                  data-bs-toggle="modal" data-bs-target="#detailModal" title="Lihat Detail Customer">
                  <span class="material-symbols-outlined">visibility</span>
                </button>

                <!-- EDIT BUTTON -->
                <button type="button" class="btn-act btn-act-edit editBtn"
                  data-id="<?= $row['id']; ?>"
                  data-nama="<?= htmlspecialchars($row['nama'] ?? ''); ?>"
                  data-kategori="<?= htmlspecialchars($row['kategori'] ?? ''); ?>"
                  data-is-tiptok="<?= $isTiptokManual ? '1' : '0' ?>"
                  data-is-tiptok-manual="<?= $isTiptokManual ?>"
                  data-telp="<?= htmlspecialchars($row['telp_pribadi'] ?? ''); ?>"
                  data-email="<?= htmlspecialchars($row['email'] ?? ''); ?>"
                  data-alamat="<?= htmlspecialchars($row['alamat'] ?? ''); ?>"
                  data-kota="<?= htmlspecialchars($row['kota'] ?? ''); ?>"
                  data-foto='<?= htmlspecialchars(json_encode($photos)); ?>'
                  data-id-wilayah="<?= $row['id_wilayah']; ?>"
                  data-lat="<?= htmlspecialchars($row['lat'] ?? ''); ?>"
                  data-lon="<?= htmlspecialchars($row['lon'] ?? ''); ?>"
                  data-rad="<?= htmlspecialchars($row['rad'] ?? ''); ?>"
                  data-alamat-lokasi="<?= htmlspecialchars($row['alamat_lokasi'] ?? ''); ?>"
                  data-bs-toggle="modal" data-bs-target="#editModal" title="Ubah Data Customer">
                  <span class="material-symbols-outlined">edit</span>
                </button>

                <!-- DELETE BUTTON -->
                <a href="?delete_id=<?= $row['id']; ?>" class="btn-act btn-act-delete" onclick="return confirm('Yakin ingin menghapus customer ini?')" title="Hapus Customer">
                  <span class="material-symbols-outlined">delete</span>
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($salesData) == 0): ?>
              <tr>
                <td colspan="6" class="text-center text-muted" style="padding: 40px;">Belum ada customer terdaftar.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal View Detail Customer -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content modal-content-premium">
          <div class="modal-header modal-header-premium" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);">
            <h5 class="modal-title modal-title-premium">
              <span class="material-symbols-outlined">storefront</span>
              Detail Sales Customer
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body modal-body-premium" style="background: #f8fafc;">
            <div class="row">
              
              <!-- Left Side: Core Info -->
              <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #fff;">
                  <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
                    <div id="detail_avatar_container" class="avatar-initials-table" style="width: 54px; height: 54px; font-size:18px; margin:0; cursor:default; box-shadow:none;"></div>
                    <div>
                      <h4 id="detail_nama" style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">-</h4>
                      <div style="display:flex; gap:6px; margin-top:4px; align-items:center; flex-wrap:wrap;">
                        <span id="detail_kategori" class="category-badge">-</span>
                        <span id="detail_wilayah" class="badge" style="font-size: 8.5px; padding: 4px 10px; border-radius:30px; background:#475569; color:#fff; text-transform:uppercase; font-weight:700;">-</span>
                        <span id="detail_tiptok_badge" class="badge-tiptok d-none"><i class="fa-solid fa-box-open"></i> TIP TOK <span id="detail_tiptok_qty" class="badge-tiptok-qty"></span></span>
                      </div>
                    </div>
                  </div>

                  <div class="detail-info-row">
                    <div class="detail-info-label">WhatsApp</div>
                    <div class="detail-info-value" id="detail_telp_container">
                      <a href="#" target="_blank" id="detail_telp_link" class="wa-pill">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M12.004 0C5.378 0 0 5.378 0 12.004c0 2.115.546 4.102 1.502 5.834L0 24l6.336-1.631c1.672.913 3.585 1.439 5.668 1.439C18.63 23.808 24 18.43 24 11.802 24 5.176 18.63 0 12.004 0zm6.086 16.943c-.272.761-1.582 1.485-2.194 1.548-.514.052-1.012.07-2.01-.115-4.07-.748-7.219-4.9-7.219-9.17 0-1.577.818-2.684 2.002-2.684.214 0 .39.009.537.014.272.009.423.023.596.377.264.54.896 2.179.977 2.348.082.169.043.342-.047.52-.09.18-.152.274-.299.449-.145.171-.313.356-.145.641.766 1.282 1.884 2.274 3.218 2.943.361.18.591.12.788-.103.227-.256.969-1.127 1.226-1.51.103-.153.284-.132.484-.055.201.077 1.296.611 1.52 1.134.223.523.223.974.12 1.21-.103.238-.238.44-.55.602z"/>
                        </svg>
                        <span id="detail_telp">-</span>
                      </a>
                    </div>
                  </div>

                  <div class="detail-info-row">
                    <div class="detail-info-label">Email</div>
                    <div class="detail-info-value" id="detail_email">-</div>
                  </div>

                  <div class="detail-info-row">
                    <div class="detail-info-label">Kota</div>
                    <div class="detail-info-value" id="detail_kota">-</div>
                  </div>

                  <div class="detail-info-row">
                    <div class="detail-info-label">Tanggal Terdaftar</div>
                    <div class="detail-info-value" id="detail_created_at" style="font-weight:700; color:#1e293b;">-</div>
                  </div>

                  <div class="detail-info-row" style="border-bottom:none;">
                    <div class="detail-info-label">Alamat</div>
                    <div class="detail-info-value" id="detail_alamat" style="line-height:1.4;">-</div>
                  </div>
                </div>

                <!-- Photos Documentation Grid inside Detail -->
                <div class="card border-0 shadow-sm rounded-4 p-4" style="background: #fff;">
                  <h6 style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:16px; letter-spacing:0.05em;">Foto Documentation Mitra</h6>
                  <div class="detail-photo-grid" id="detail_photos_container">
                    <!-- photos injected via JS -->
                  </div>
                </div>
              </div>

              <!-- Right Side: Live Leaflet Geofence Map & Sales Visits History -->
              <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4" style="background: #fff; min-height:360px; display:flex; flex-direction:column; margin-bottom: 24px;">
                  <h6 style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:12px; letter-spacing:0.05em;">Peta Geofence Lokasi Toko</h6>
                  
                  <div id="map_detail" style="flex: 1; min-height: 200px; border-radius:12px; border:1.5px solid #e2e8f0;"></div>
                  
                  <div class="mt-3 p-3 bg-light rounded-3" style="font-size:12.5px; color:#475569; line-height:1.4;">
                    <span style="font-weight:700; color:#0f172a; display:block; margin-bottom:2px;">Alamat Geocoder Peta:</span>
                    <span id="detail_alamat_peta">-</span>
                  </div>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4 p-4" style="background: #fff;">
                  <h6 style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:16px; letter-spacing:0.05em; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:18px; color:#2563eb;">history</span>
                    Riwayat Kunjungan Sales
                  </h6>
                  <div id="detail_visits_container" style="max-height:220px; overflow-y:auto; padding-right:4px;">
                    <!-- visits list injected via JS -->
                  </div>
                </div>
              </div>

            </div>
          </div>
          <div class="modal-footer modal-footer-premium">
            <button type="button" class="btn bg-gradient-secondary font-weight-bold" data-bs-dismiss="modal" style="border-radius:10px; padding:10px 20px; margin:0;">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail Kunjungan Sales -->
    <div class="modal fade" id="visitsDetailModal" tabindex="-1" aria-hidden="true" style="z-index: 1065; background: rgba(0,0,0,0.3);">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-premium" style="border: 2px solid #e2e8f0; box-shadow: 0 20px 45px rgba(0,0,0,0.25);">
          <div class="modal-header modal-header-premium" style="background: linear-gradient(135deg, #1e293b 0%, #3b82f6 100%);">
            <h5 class="modal-title modal-title-premium">
              <span class="material-symbols-outlined">history_edu</span>
              Detail Riwayat Kunjungan
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body modal-body-premium" style="background: #f8fafc; max-height: 520px; overflow-y: auto;">
            <div id="visits_detail_title" style="margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
              <h5 id="visits_detail_sales_name" style="margin: 0; font-weight: 800; color: #0f172a;">-</h5>
              <span style="font-size: 13px; color: #64748b;">Kunjungan ke Customer: <strong id="visits_detail_cust_name" style="color: #0f172a;">-</strong></span>
            </div>
            
            <div id="visits_timeline_container" style="padding: 10px;">
              <!-- Timeline items injected here -->
            </div>
          </div>
          <div class="modal-footer modal-footer-premium">
            <button type="button" class="btn bg-gradient-secondary font-weight-bold" data-bs-dismiss="modal" style="border-radius:10px; padding:10px 20px; margin:0;">Kembali</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Edit Customer (Senior-Friendly & Gambar 2 Architecture) -->
    <div class="modal fade" id="editModal" tabindex="-1">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <form method="POST" class="modal-content" enctype="multipart/form-data" id="editCustomerForm" style="border-radius: 20px; border: 2px solid #cbd5e1; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25); overflow: hidden;">
          
          <!-- Modal Header Premium -->
          <div class="modal-header d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom: 2px solid #e2e8f0; background: #ffffff;">
            <div class="d-flex align-items-center gap-3">
              <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(29, 78, 216, 0.35); flex-shrink: 0;">
                <span class="material-symbols-outlined" style="font-size: 26px;">edit_document</span>
              </div>
              <div>
                <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">Ubah Data Sales Customer</h4>
                <p style="margin: 2px 0 0; font-size: 13px; color: #64748b; font-weight: 500;">Perbarui data identitas toko, nomor kontak, kategori, dan titik radius geofence GPS.</p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 14px;"></button>
          </div>

          <!-- Modal Body -->
          <div class="modal-body p-4" style="background: #ffffff;">
            <div class="row g-4">
              
              <!-- ════ LEFT COLUMN: Detail Customer ════ -->
              <div class="col-lg-7 pe-lg-4" style="border-right: 2px solid #e2e8f0;">
                <input type="hidden" name="update_id" id="edit_id">

                <!-- Section 1 Header -->
                <div class="kb-section-badge">
                  <span class="dot"></span>
                  <span>01. INFORMASI CUSTOMER</span>
                </div>
                
                <div class="row g-3">
                  <!-- Nama Toko -->
                  <div class="col-md-6">
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-shop-window text-primary"></i>
                        <span>Nama Toko / Mitra / Personal</span>
                      </span>
                      <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 fw-bold" style="font-size: 11px;">* Wajib Diisi</span>
                    </label>
                    <input type="text" name="edit_nama" id="edit_nama" class="kb-input" placeholder="Masukkan nama toko..." required>
                  </div>
                  
                  <!-- Kategori Customer (Pills Segmented) -->
                  <div class="col-md-6">
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-tags-fill text-primary"></i>
                        <span>Kategori Customer</span>
                      </span>
                    </label>
                    <div class="category-pill-group">
                      <label class="category-pill-label" for="edit_kategori_dealer">
                        <input class="category-pill-input" type="radio" name="edit_kategori" id="edit_kategori_dealer" value="Dealer" required checked>
                        <span class="category-pill-span span-dealer">Dealer</span>
                      </label>
                      <label class="category-pill-label" for="edit_kategori_installer">
                        <input class="category-pill-input" type="radio" name="edit_kategori" id="edit_kategori_installer" value="Installer">
                        <span class="category-pill-span span-installer">Installer</span>
                      </label>
                      <label class="category-pill-label" for="edit_kategori_user">
                        <input class="category-pill-input" type="radio" name="edit_kategori" id="edit_kategori_user" value="User">
                        <span class="category-pill-span span-user">User</span>
                      </label>
                    </div>
                  </div>

                  <!-- Wilayah Customer -->
                  <div class="col-md-6">
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-geo-fill text-primary"></i>
                        <span>Wilayah Customer</span>
                      </span>
                      <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 fw-bold" style="font-size: 11px;">* Wajib Diisi</span>
                    </label>
                    <select name="edit_id_wilayah" id="edit_id_wilayah" class="kb-input kb-select" required>
                      <option value="">-- Pilih Wilayah --</option>
                      <?php 
                      $wQuery2 = mysqli_query($conn, "SELECT * FROM wilayah WHERE deleted_at IS NULL ORDER BY nama ASC");
                      while ($w2 = mysqli_fetch_assoc($wQuery2)) {
                          echo "<option value='{$w2['id']}'>" . htmlspecialchars($w2['nama']) . "</option>";
                      }
                      ?>
                    </select>
                  </div>
                  
                  <!-- No Telepon -->
                  <div class="col-md-6">
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-telephone-fill text-primary"></i>
                        <span>No. Telepon (WhatsApp)</span>
                      </span>
                    </label>
                    <input type="text" name="edit_telp" id="edit_telp" class="kb-input" placeholder="Contoh: 0812345678">
                  </div>
                  
                  <!-- Email -->
                  <div class="col-md-6">
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-envelope-fill text-primary"></i>
                        <span>Email Customer</span>
                      </span>
                    </label>
                    <input type="email" name="edit_email" id="edit_email" class="kb-input" placeholder="customer@domain.com">
                  </div>
                  
                  <!-- Kota -->
                  <div class="col-md-6">
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-building-fill text-primary"></i>
                        <span>Kota Asal</span>
                      </span>
                    </label>
                    <input type="text" name="edit_kota" id="edit_kota" class="kb-input" placeholder="Kota domisili toko...">
                  </div>

                  <!-- Alamat Lengkap -->
                  <div class="col-12">
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-card-text text-primary"></i>
                        <span>Alamat Lengkap Toko / Kantor</span>
                      </span>
                    </label>
                    <textarea name="edit_alamat" id="edit_alamat" class="kb-input" rows="2" style="min-height: 65px;" placeholder="Alamat lengkap jalan, nomor, RT/RW..."></textarea>
                  </div>

                  <!-- TIP TOK Banner Card -->
                  <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(245, 158, 11, 0.08); border: 2px dashed rgba(245, 158, 11, 0.45); border-radius: 14px;">
                      <div class="d-flex align-items-center gap-3">
                        <span class="material-symbols-outlined text-warning" style="font-size:28px;">inventory_2</span>
                        <div>
                          <span style="font-weight:800; font-size:13.5px; color:#92400e; display:block;">Mitra TIP TOK (Konsinyasi Toko)</span>
                          <span style="font-size:12px; color:#b45309;">Toko dititipkan stok barang konsinyasi display Loewix</span>
                        </div>
                      </div>
                      <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="edit_is_tiptok" id="edit_is_tiptok" value="1" style="width: 2.4em; height: 1.3em; cursor: pointer;">
                      </div>
                    </div>
                  </div>

                  <!-- Existing Photos & Dropzone -->
                  <div class="col-12 mt-2">
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-images text-primary"></i>
                        <span>Foto Dokumentasi Saat Ini (Klik ❌ untuk Menghapus)</span>
                      </span>
                    </label>
                    <div class="d-flex flex-wrap gap-2.5 mb-3" id="edit_existing_photos_container"></div>
                    <input type="hidden" name="deleted_existing_photos" id="deleted_existing_photos">
                    
                    <label class="kb-label">
                      <span class="kb-label-icon">
                        <i class="bi bi-cloud-arrow-up-fill text-primary"></i>
                        <span>Tambah Foto Dokumentasi Baru</span>
                      </span>
                    </label>
                    <div class="dropzone-area" id="dropzone_edit" style="border: 2px dashed #cbd5e1; border-radius: 14px; padding: 20px;">
                      <span class="material-symbols-outlined dropzone-icon" style="font-size: 32px;">cloud_upload</span>
                      <p class="dropzone-text" style="font-size: 13px;">Drag &amp; drop file baru di sini, atau klik untuk memilih</p>
                      <input type="file" id="foto_input_edit" name="edit_foto[]" multiple accept="image/*" class="d-none">
                    </div>
                    <div class="preview-grid" id="preview_grid_edit"></div>
                  </div>
                </div>
              </div>

              <!-- ════ RIGHT COLUMN: Peta & Geofence GPS ════ -->
              <div class="col-lg-5 ps-lg-4">
                
                <!-- Section 2 Header -->
                <div class="kb-section-badge kb-section-badge-green">
                  <span class="dot"></span>
                  <span>02. TITIK LOKASI &amp; GEOFENCE GPS</span>
                </div>

                <!-- Search Input Group -->
                <div class="mb-3">
                  <label class="kb-label">
                    <span class="kb-label-icon">
                      <i class="bi bi-geo-alt-fill text-success"></i>
                      <span>Cari Koordinat / Alamat Toko</span>
                    </span>
                  </label>
                  <div class="kb-map-search-wrap">
                    <input type="text" id="gmap_search_edit" class="kb-input" placeholder="Contoh: Surabaya atau -7.250, 112.750...">
                    <button type="button" id="gmap_search_btn_edit" class="kb-btn kb-btn-primary px-3.5 py-2 flex-shrink-0" style="font-size: 14px;">
                      <i class="bi bi-search"></i>
                      <span>Cari</span>
                    </button>
                  </div>
                </div>

                <!-- Leaflet Map edit -->
                <div id="map_edit" class="leaflet-geofence-map"></div>

                <!-- GPS Location & Quick Actions -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                  <button type="button" id="btn_get_location_edit" class="btn btn-sm d-flex align-items-center gap-2 py-2 px-3.5 rounded-pill fw-bold text-white" style="font-size: 13px; background: #059669; border: none; box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);">
                    <i class="bi bi-crosshair fs-6"></i>
                    <span>Dapatkan Lokasi Saya (GPS)</span>
                  </button>
                  <div class="d-flex align-items-center gap-2">
                    <span class="text-xs fw-bold text-dark font-monospace">Radius:</span>
                    <span class="badge bg-primary px-2.5 py-1.5 font-monospace fs-7 fw-bold" id="slider_val_edit_badge">100m</span>
                  </div>
                </div>

                <!-- Geofence Radius Card with Presets -->
                <div class="mt-3 p-3 bg-white rounded-3 border" style="border: 2px solid #cbd5e1 !important;">
                  <div class="d-flex justify-content-between align-items-center mb-1.5">
                    <span class="text-sm fw-bold text-dark">Radius Geofence Check-in</span>
                    <span class="text-xs text-muted fw-semibold">Jarak toleransi absen sales</span>
                  </div>
                  <input type="range" id="radius_slider_edit" min="10" max="1000" step="10" value="100" class="w-100" style="accent-color: #1d4ed8; height: 8px;">
                  
                  <!-- Quick Preset Pills -->
                  <div class="kb-radius-presets mt-2">
                    <div class="kb-preset-btn kb-preset-edit" data-val="50">50 m</div>
                    <div class="kb-preset-btn kb-preset-edit active" data-val="100">100 m</div>
                    <div class="kb-preset-btn kb-preset-edit" data-val="200">200 m</div>
                    <div class="kb-preset-btn kb-preset-edit" data-val="500">500 m</div>
                    <div class="kb-preset-btn kb-preset-edit" data-val="1000">1 km</div>
                  </div>
                </div>

                <!-- Coordinate Details Badges -->
                <div class="row g-2 mt-2">
                  <div class="col-6">
                    <div class="kb-coord-badge">
                      <span class="kb-coord-label">Latitude</span>
                      <span id="edit_lat_display_text" class="fw-bold text-dark">-6.130371</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="kb-coord-badge">
                      <span class="kb-coord-label">Longitude</span>
                      <span id="edit_lon_display_text" class="fw-bold text-dark">106.751442</span>
                    </div>
                  </div>
                </div>

                <!-- Reverse Geocoded Address Preview Box -->
                <div class="mt-3">
                  <div class="p-3 rounded-3 text-sm text-dark d-flex align-items-start gap-2.5" style="border: 2px solid #93c5fd; background: #eff6ff;">
                    <i class="bi bi-geo-alt-fill text-primary fs-5 mt-0.5"></i>
                    <span id="edit_location_address_text" class="fw-bold text-dark">Mengarahkan pin peta ke titik lokasi target...</span>
                  </div>
                </div>

                <!-- Hidden inputs to submit -->
                <input type="hidden" id="edit_lat" name="edit_lat">
                <input type="hidden" id="edit_lon" name="edit_lon">
                <input type="hidden" id="edit_radius" name="edit_radius" value="100">
                <input type="hidden" id="edit_radius_input" value="100">
                <input type="hidden" id="edit_location_address" name="edit_location_address">
              </div>

            </div>
          </div>

          <!-- Modal Footer Premium -->
          <div class="modal-footer d-flex align-items-center justify-content-end gap-3 px-4 py-3" style="border-top: 2px solid #e2e8f0; background: #ffffff;">
            <button type="button" class="kb-btn kb-btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-lg"></i>
              <span>Batal</span>
            </button>
            <button type="submit" class="kb-btn kb-btn-primary" id="btnSubmitEditCustomer">
              <i class="bi bi-check-circle-fill"></i>
              <span>Simpan Perubahan</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Gallery slideshow modal -->
    <div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none; background:#0f172a;">
          <div class="modal-header border-0 text-white" style="padding: 16px 24px; background: rgba(255,255,255,0.03);">
            <h6 class="modal-title text-white font-weight-bold" id="galleryTitle">Dokumentasi Toko</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1) grayscale(1) brightness(2);"></button>
          </div>
          <div class="modal-body text-center" style="padding: 30px;">
            <!-- Carousel -->
            <div id="galleryCarousel" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner" id="galleryCarouselInner" style="max-height: 480px; border-radius:12px; overflow:hidden; border: 2px solid rgba(255,255,255,0.1);">
                <!-- slides inject -->
              </div>
              <button class="carousel-control-prev" type="button" data-bs-target="#galleryCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));"></span>
                <span class="visually-hidden">Previous</span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#galleryCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));"></span>
                <span class="visually-hidden">Next</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- View map coordinates modal -->
    <div class="modal fade" id="viewMapModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none; background:#fff;">
          <div class="modal-header border-0 bg-gradient-dark text-white" style="padding: 18px 24px;">
            <h6 class="modal-title text-white font-weight-bold" id="viewMapTitle">Lokasi Geofence Toko</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="padding: 24px;">
            <div id="map_view_container" style="height: 380px; border-radius: 12px; border: 1.5px solid #e2e8f0; width: 100%;"></div>
            <div class="mt-3 p-3 bg-light rounded-3" style="font-size: 13px; color: #475569;">
              <div style="font-weight: 700; color: #1e293b; margin-bottom:4px;">Alamat Peta Geocoder:</div>
              <span id="viewMapAddress">Sedang memuat alamat...</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if (file_exists(__DIR__ . "/floating-menu.php")) { include_once __DIR__ . "/floating-menu.php"; } ?>
    <?php include "footer.php"; ?>
  </div>
</main>

<?php include "js-include.php"; ?>

<!-- Leaflet Map JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  // ── Drag & Drop Uploader Script ──
  function setupDragAndDrop(dropzoneId, inputId, previewGridId, maxFiles = 5) {
    const dropzone = document.getElementById(dropzoneId);
    const input = document.getElementById(inputId);
    const previewGrid = document.getElementById(previewGridId);
    let selectedFiles = [];

    // Trigger click on dropzone
    dropzone.addEventListener('click', () => input.click());

    // Drag events
    ['dragenter', 'dragover'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
      }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
      }, false);
    });

    // Handle dropped files
    dropzone.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      const files = dt.files;
      handleFiles(files);
    });

    // Handle file selection
    input.addEventListener('change', () => {
      handleFiles(input.files);
    });

    function handleFiles(files) {
      const filesArr = Array.from(files).filter(file => file.type.startsWith('image/'));
      
      if (selectedFiles.length + filesArr.length > maxFiles) {
        alert(`Maksimal hanya dapat mengunggah ${maxFiles} foto dokumentasi.`);
        return;
      }

      filesArr.forEach(file => {
        selectedFiles.push(file);
        
        const reader = new FileReader();
        reader.onload = (e) => {
          const previewItem = document.createElement('div');
          previewItem.className = 'preview-item';
          
          const img = document.createElement('img');
          img.src = e.target.result;
          
          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'preview-remove';
          removeBtn.innerHTML = '❌';
          removeBtn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            const idx = selectedFiles.indexOf(file);
            if (idx > -1) {
              selectedFiles.splice(idx, 1);
            }
            previewItem.remove();
            updateFileInput();
          });
          
          previewItem.appendChild(img);
          previewItem.appendChild(removeBtn);
          previewGrid.appendChild(previewItem);
        };
        reader.readAsDataURL(file);
      });

      updateFileInput();
    }

    function updateFileInput() {
      const dt = new DataTransfer();
      selectedFiles.forEach(file => dt.items.add(file));
      input.files = dt.files;
    }

    return {
      reset: () => {
        selectedFiles = [];
        previewGrid.innerHTML = '';
        input.value = '';
      }
    };
  }

  // Setup uploader zones
  const uploaderCreate = setupDragAndDrop('dropzone_create', 'foto_input_create', 'preview_grid_create', 5);
  const uploaderEdit = setupDragAndDrop('dropzone_edit', 'foto_input_edit', 'preview_grid_edit', 5);

  // Helper function to trigger gallery slideshow
  function openGallerySlideshow(photos, name) {
      document.getElementById('galleryTitle').innerText = 'Dokumentasi Foto: ' + name;
      const carouselInner = document.getElementById('galleryCarouselInner');
      carouselInner.innerHTML = '';
      
      photos.forEach((photo, idx) => {
        const item = document.createElement('div');
        item.className = 'carousel-item' + (idx === 0 ? ' active' : '');
        
        const img = document.createElement('img');
        img.src = '../uploads/customer/' + photo;
        img.className = 'd-block w-100';
        img.style.height = '420px';
        img.style.objectFit = 'contain';
        img.style.background = '#020617';
        
        item.appendChild(img);
        carouselInner.appendChild(item);
      });
      
      const galleryModal = new bootstrap.Modal(document.getElementById('galleryModal'));
      galleryModal.show();
  }

  // ── Gallery Slideshow Logic ──
  document.querySelectorAll('.openGalleryBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      const photos = JSON.parse(btn.dataset.photos || '[]');
      const name = btn.dataset.name;
      openGallerySlideshow(photos, name);
    });
  });

  // ── View Map Modal Logic ──
  let mapViewInstance = null;
  let markerView = null;
  let circleView = null;

  document.querySelectorAll('.openMapModalBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      const lat = parseFloat(btn.dataset.lat);
      const lon = parseFloat(btn.dataset.lon);
      const rad = parseInt(btn.dataset.rad) || 100;
      const name = btn.dataset.name;
      const address = btn.dataset.alamat || "Alamat lengkap tidak tertera.";

      document.getElementById('viewMapTitle').innerText = 'Lokasi Geofence: ' + name;
      document.getElementById('viewMapAddress').innerText = address;

      const latlng = L.latLng(lat, lon);

      const viewMapModal = new bootstrap.Modal(document.getElementById('viewMapModal'));
      viewMapModal.show();

      // Initialize map on modal shown
      setTimeout(() => {
        if (!mapViewInstance) {
          mapViewInstance = L.map('map_view_container').setView(latlng, 16);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
          }).addTo(mapViewInstance);

          markerView = L.marker(latlng).addTo(mapViewInstance);
          circleView = L.circle(latlng, { radius: rad, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.15 }).addTo(mapViewInstance);
        } else {
          mapViewInstance.setView(latlng, 16);
          markerView.setLatLng(latlng);
          circleView.setLatLng(latlng).setRadius(rad);
          mapViewInstance.invalidateSize();
        }
      }, 350);
    });
  });

  // ── Map Create Logic ──
  const defaultLat = -6.13037113;
  const defaultLon = 106.75144230;
  const defaultRad = 100;

  const mapCreate = L.map('map_create').setView([defaultLat, defaultLon], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(mapCreate);

  let markerCreate = L.marker([defaultLat, defaultLon], { draggable: true }).addTo(mapCreate);
  let circleCreate = L.circle([defaultLat, defaultLon], {
    radius: defaultRad,
    color: '#2563eb', // Blue border
    fillColor: '#3b82f6', // Light blue fill
    fillOpacity: 0.12,
    weight: 1.5,
    dashArray: '5, 5'
  }).addTo(mapCreate);

  const radInputCreate = document.getElementById('radius_input');
  const radSliderCreate = document.getElementById('radius_slider_create');
  const sliderValCreate = document.getElementById('slider_val_create');

  function syncRadiusCreate(value) {
    const r = parseInt(value) || defaultRad;
    radInputCreate.value = r;
    radSliderCreate.value = r;
    sliderValCreate.innerText = r + 'm';
    circleCreate.setRadius(r);
    document.getElementById('radius').value = r;
  }

  radInputCreate.addEventListener('input', function() {
    syncRadiusCreate(this.value);
  });

  radSliderCreate.addEventListener('input', function() {
    syncRadiusCreate(this.value);
  });

  function updateCreateMapData(latlng, rad) {
    const r = parseInt(rad) || defaultRad;
    markerCreate.setLatLng(latlng);
    circleCreate.setLatLng(latlng).setRadius(r);
    mapCreate.setView(latlng, 16);

    document.getElementById('lat').value = latlng.lat;
    document.getElementById('lon').value = latlng.lng;
    document.getElementById('lat_display').value = latlng.lat.toFixed(6);
    document.getElementById('lon_display').value = latlng.lng.toFixed(6);
    syncRadiusCreate(r);

    // Nominatim geocode reverse
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&accept-language=id`)
      .then(res => res.json())
      .then(data => {
        document.getElementById('location_address').value = data?.display_name || '';
      })
      .catch(() => {
        document.getElementById('location_address').value = '';
      });
  }

  mapCreate.on('click', function(e) {
    updateCreateMapData(e.latlng, radInputCreate.value);
  });

  markerCreate.on('dragend', function() {
    updateCreateMapData(markerCreate.getLatLng(), radInputCreate.value);
  });

  // GPS Create Event
  document.getElementById('btn_get_location_create').addEventListener('click', function() {
    const btn = this;
    const origContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mencari Lokasi...';

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function(position) {
          const lat = position.coords.latitude;
          const lon = position.coords.longitude;
          updateCreateMapData(L.latLng(lat, lon), radInputCreate.value);
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

  document.getElementById('gmap_search_btn').addEventListener('click', function() {
    const btn = document.getElementById('gmap_search_btn');
    const query = document.getElementById('gmap_search').value.trim();
    if (query === "") return;

    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span><span>Mencari...</span>';

    const coordsRegex = /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/;
    if (coordsRegex.test(query)) {
      const parts = query.split(',');
      updateCreateMapData(L.latLng(parseFloat(parts[0]), parseFloat(parts[1])), radInputCreate.value);
      btn.disabled = false;
      btn.innerHTML = origHtml;
    } else {
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id&accept-language=id`)
        .then(res => res.json())
        .then(data => {
          btn.disabled = false;
          btn.innerHTML = origHtml;
          if (data && data.length > 0) {
            updateCreateMapData(L.latLng(parseFloat(data[0].lat), parseFloat(data[0].lon)), radInputCreate.value);
          } else {
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'warning',
                title: 'Lokasi Tidak Ditemukan',
                text: 'Lokasi "' + query + '" tidak ditemukan. Coba ketik nama kota/daerah yang lebih umum atau masukkan koordinat (contoh: -6.175, 106.827).',
                confirmButtonColor: '#2563eb'
              });
            } else {
              alert("Alamat tidak ditemukan.");
            }
          }
        })
        .catch(err => {
          btn.disabled = false;
          btn.innerHTML = origHtml;
          console.error(err);
        });
    }
  });

  // Init create map
  updateCreateMapData(L.latLng(defaultLat, defaultLon), defaultRad);

  // ── Manual Toggle Tambah Customer Map Fix ──
  const toggleHeader = document.getElementById('tambahCustomerHeader');
  const collapseEl = document.getElementById('collapseTambahCustomer');
  const toggleText = document.getElementById('toggleText');
  const toggleChevron = document.getElementById('toggleChevron');

  toggleHeader.addEventListener('click', function () {
      if (collapseEl.style.display === 'none' || collapseEl.style.display === '') {
          collapseEl.style.display = 'block';
          toggleText.innerText = 'Sembunyikan Form';
          toggleChevron.style.transform = 'rotate(180deg)';
          if (mapCreate) {
              setTimeout(() => {
                  mapCreate.invalidateSize();
              }, 150);
          }
      } else {
          collapseEl.style.display = 'none';
          toggleText.innerText = 'Tampilkan Form';
          toggleChevron.style.transform = 'rotate(0deg)';
      }
  });


  // ── Map Edit Modal Logic ──
  let mapEditInstance = null;
  let markerEdit = null;
  let circleEdit = null;

  const radInputEdit = document.getElementById('edit_radius_input');
  const radSliderEdit = document.getElementById('radius_slider_edit');
  const sliderValEdit = document.getElementById('slider_val_edit');
  const sliderValEditBadge = document.getElementById('slider_val_edit_badge');

  function syncRadiusEdit(value) {
    const r = parseInt(value) || defaultRad;
    if (radInputEdit) radInputEdit.value = r;
    if (radSliderEdit) radSliderEdit.value = r;
    if (sliderValEdit) sliderValEdit.innerText = r + 'm';
    if (sliderValEditBadge) sliderValEditBadge.innerText = r + 'm';
    if (circleEdit) {
      circleEdit.setRadius(r);
    }
    const editRadiusHidden = document.getElementById('edit_radius');
    if (editRadiusHidden) editRadiusHidden.value = r;

    // Update active preset button
    document.querySelectorAll('.kb-preset-edit').forEach(btn => {
      if (parseInt(btn.dataset.val) === r) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });
  }

  if (radInputEdit) {
    radInputEdit.addEventListener('input', function() {
      syncRadiusEdit(this.value);
    });
  }

  if (radSliderEdit) {
    radSliderEdit.addEventListener('input', function() {
      syncRadiusEdit(this.value);
    });
  }

  // Hook up preset buttons
  document.querySelectorAll('.kb-preset-edit').forEach(btn => {
    btn.addEventListener('click', function() {
      syncRadiusEdit(this.dataset.val);
    });
  });

  const editModalEl = document.getElementById('editModal');
  if (editModalEl) {
    editModalEl.addEventListener('shown.bs.modal', function () {
        let latVal = parseFloat(document.getElementById('edit_lat').value);
        let lonVal = parseFloat(document.getElementById('edit_lon').value);
        const radVal = parseInt(document.getElementById('edit_radius').value) || defaultRad;
        
        // If values are empty/NaN, use defaults and save them so fields are never blank
        if (isNaN(latVal) || isNaN(lonVal)) {
          latVal = defaultLat;
          lonVal = defaultLon;
          document.getElementById('edit_lat').value = defaultLat;
          document.getElementById('edit_lon').value = defaultLon;
          if (document.getElementById('edit_lat_display')) document.getElementById('edit_lat_display').value = defaultLat.toFixed(6);
          if (document.getElementById('edit_lon_display')) document.getElementById('edit_lon_display').value = defaultLon.toFixed(6);
          if (document.getElementById('edit_lat_display_text')) document.getElementById('edit_lat_display_text').innerText = defaultLat.toFixed(6);
          if (document.getElementById('edit_lon_display_text')) document.getElementById('edit_lon_display_text').innerText = defaultLon.toFixed(6);
        }
        
        const latlng = L.latLng(latVal, lonVal);
        syncRadiusEdit(radVal);
        
        if (!mapEditInstance) {
            mapEditInstance = L.map('map_edit').setView(latlng, 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 19,
              attribution: '© OpenStreetMap contributors'
            }).addTo(mapEditInstance);
            
            markerEdit = L.marker(latlng, { draggable: true }).addTo(mapEditInstance);
            circleEdit = L.circle(latlng, {
              radius: radVal,
              color: '#2563eb', // Blue border
              fillColor: '#3b82f6', // Light blue fill
              fillOpacity: 0.12,
              weight: 1.5,
              dashArray: '5, 5'
            }).addTo(mapEditInstance);
            
            mapEditInstance.on('click', function(e) {
              updateEditMapData(e.latlng, radSliderEdit ? radSliderEdit.value : 100);
            });
            
            markerEdit.on('dragend', function() {
              updateEditMapData(markerEdit.getLatLng(), radSliderEdit ? radSliderEdit.value : 100);
            });
        } else {
            mapEditInstance.setView(latlng, 15);
            markerEdit.setLatLng(latlng);
            circleEdit.setLatLng(latlng).setRadius(radVal);
            mapEditInstance.invalidateSize();
        }

        setTimeout(() => {
          if (mapEditInstance) {
            mapEditInstance.invalidateSize();
          }
        }, 250);
    });
  }

  function updateEditMapData(latlng, rad) {
      const r = parseInt(rad) || defaultRad;
      if (markerEdit) markerEdit.setLatLng(latlng);
      if (circleEdit) {
        circleEdit.setLatLng(latlng);
        circleEdit.setRadius(r);
      }
      if (mapEditInstance) mapEditInstance.setView(latlng, 16);
      
      const editLat = document.getElementById('edit_lat');
      const editLon = document.getElementById('edit_lon');
      const editLatDisp = document.getElementById('edit_lat_display');
      const editLonDisp = document.getElementById('edit_lon_display');
      const editLatText = document.getElementById('edit_lat_display_text');
      const editLonText = document.getElementById('edit_lon_display_text');
      const editLocAddrText = document.getElementById('edit_location_address_text');
      const editLocAddrHidden = document.getElementById('edit_location_address');

      if (editLat) editLat.value = latlng.lat;
      if (editLon) editLon.value = latlng.lng;
      if (editLatDisp) editLatDisp.value = latlng.lat.toFixed(6);
      if (editLonDisp) editLonDisp.value = latlng.lng.toFixed(6);
      if (editLatText) editLatText.innerText = latlng.lat.toFixed(6);
      if (editLonText) editLonText.innerText = latlng.lng.toFixed(6);

      syncRadiusEdit(r);
      
      if (editLocAddrText) editLocAddrText.innerText = 'Mengambil nama lokasi alamat...';

      fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&accept-language=id`)
        .then(res => res.json())
        .then(data => {
          const addr = data?.display_name || '';
          if (editLocAddrHidden) editLocAddrHidden.value = addr;
          if (editLocAddrText) editLocAddrText.innerText = addr || 'Titik koordinat berhasil ditentukan.';
        })
        .catch(() => {
          if (editLocAddrHidden) editLocAddrHidden.value = '';
          if (editLocAddrText) editLocAddrText.innerText = 'Koordinat: ' + latlng.lat.toFixed(6) + ', ' + latlng.lng.toFixed(6);
        });
  }

  // GPS Edit Event
  const btnGetLocEdit = document.getElementById('btn_get_location_edit');
  if (btnGetLocEdit) {
    btnGetLocEdit.addEventListener('click', function() {
      const btn = this;
      const origContent = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon"></i> Mencari Lokasi...';

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            const curRad = radSliderEdit ? radSliderEdit.value : 100;
            updateEditMapData(L.latLng(lat, lon), curRad);
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
  }

  const btnSearchEdit = document.getElementById('gmap_search_btn_edit');
  if (btnSearchEdit) {
    btnSearchEdit.addEventListener('click', function() {
      const query = document.getElementById('gmap_search_edit').value.trim();
      if (query === "" || !mapEditInstance) return;

      const coordsRegex = /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/;
      const curRad = radSliderEdit ? radSliderEdit.value : 100;
      if (coordsRegex.test(query)) {
        const parts = query.split(',');
        updateEditMapData(L.latLng(parseFloat(parts[0]), parseFloat(parts[1])), curRad);
      } else {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id&accept-language=id`)
          .then(res => res.json())
          .then(data => {
            if (data && data.length > 0) {
              updateEditMapData(L.latLng(parseFloat(data[0].lat), parseFloat(data[0].lon)), curRad);
            } else {
              alert("Lokasi tidak ditemukan.");
            }
          });
      }
    });
  }

  // Enter key support for map searches
  const gmapSearchInput = document.getElementById('gmap_search');
  if (gmapSearchInput) {
    gmapSearchInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('gmap_search_btn').click();
      }
    });
  }

  const gmapSearchEditInput = document.getElementById('gmap_search_edit');
  if (gmapSearchEditInput) {
    gmapSearchEditInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const searchBtn = document.getElementById('gmap_search_btn_edit');
        if (searchBtn) searchBtn.click();
      }
    });
  }

  // ── Edit Modal Population ──
  let deletedExistingPhotos = [];
  document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      deletedExistingPhotos = [];
      document.getElementById('deleted_existing_photos').value = '';
      uploaderEdit.reset();

      document.getElementById('edit_id').value = btn.dataset.id;
      document.getElementById('edit_nama').value = btn.dataset.nama;
      document.getElementById('edit_telp').value = btn.dataset.telp;
      document.getElementById('edit_email').value = btn.dataset.email;
      document.getElementById('edit_alamat').value = btn.dataset.alamat;
      document.getElementById('edit_kota').value = btn.dataset.kota;
      document.getElementById('edit_id_wilayah').value = btn.dataset.idWilayah || "";

      // TIP TOK Switch
      const editTiptokSwitch = document.getElementById('edit_is_tiptok');
      if (editTiptokSwitch) {
        editTiptokSwitch.checked = (btn.dataset.isTiptok === '1');
      }

      // GPS Data Populate
      const latVal = parseFloat(btn.dataset.lat);
      const lonVal = parseFloat(btn.dataset.lon);
      const radVal = parseInt(btn.dataset.rad) || 100;
      const addrVal = btn.dataset.alamatLokasi || "";

      document.getElementById('edit_lat').value = isNaN(latVal) ? "" : latVal;
      document.getElementById('edit_lon').value = isNaN(lonVal) ? "" : lonVal;
      document.getElementById('edit_radius').value = radVal;
      if (document.getElementById('edit_radius_input')) document.getElementById('edit_radius_input').value = radVal;
      document.getElementById('edit_location_address').value = addrVal;
      
      if (document.getElementById('edit_lat_display')) {
        document.getElementById('edit_lat_display').value = isNaN(latVal) ? "" : latVal.toFixed(6);
      }
      if (document.getElementById('edit_lon_display')) {
        document.getElementById('edit_lon_display').value = isNaN(lonVal) ? "" : lonVal.toFixed(6);
      }
      if (document.getElementById('edit_lat_display_text')) {
        document.getElementById('edit_lat_display_text').innerText = isNaN(latVal) ? "-" : latVal.toFixed(6);
      }
      if (document.getElementById('edit_lon_display_text')) {
        document.getElementById('edit_lon_display_text').innerText = isNaN(lonVal) ? "-" : lonVal.toFixed(6);
      }
      if (document.getElementById('edit_location_address_text')) {
        document.getElementById('edit_location_address_text').innerText = addrVal || 'Titik koordinat toko terpasang.';
      }
      syncRadiusEdit(radVal);

      // Existing photos preview with delete
      const existingContainer = document.getElementById('edit_existing_photos_container');
      existingContainer.innerHTML = '';
      
      const photos = JSON.parse(btn.dataset.foto || '[]');
      if (photos.length > 0) {
        photos.forEach(photo => {
          const wrapper = document.createElement('div');
          wrapper.className = 'edit-photo-thumb';
          
          const img = document.createElement('img');
          img.src = '../uploads/customer/' + photo;
          
          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'preview-remove';
          delBtn.innerHTML = '❌';
          delBtn.addEventListener('click', () => {
            deletedExistingPhotos.push(photo);
            document.getElementById('deleted_existing_photos').value = deletedExistingPhotos.join(',');
            wrapper.remove();
          });
          
          wrapper.appendChild(img);
          wrapper.appendChild(delBtn);
          existingContainer.appendChild(wrapper);
        });
      } else {
        existingContainer.innerHTML = '<span class="text-muted" style="font-size: 12px;">Belum ada dokumentasi foto.</span>';
      }

      const kategori = (btn.dataset.kategori || '').toLowerCase().trim();
      document.querySelectorAll('input[name="edit_kategori"]').forEach(radio => {
        radio.checked = (radio.value.toLowerCase().trim() === kategori);
      });
    });
  });

  // ── Quick Toggle TIP TOK AJAX Function ──
  window.toggleTiptokQuick = function(id, btn) {
    if (!id) return;
    const isCurrentlyActive = btn.classList.contains('active');
    const newStatus = isCurrentlyActive ? 0 : 1;
    
    fetch('ajax_toggle_tiptok.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(id) + '&set_status=' + encodeURIComponent(newStatus)
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'success',
            title: res.is_tiptok === 1 ? '🏷️ Ditandai TIP TOK' : 'Tanda TIP TOK Dicabut',
            text: res.message,
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
          });
        }
        // Update button visual
        if (res.is_tiptok === 1) {
          btn.classList.add('active');
          btn.title = 'Mitra TIP TOK Aktif (Klik untuk nonaktifkan)';
        } else {
          btn.classList.remove('active');
          btn.title = 'Tandai Toko sebagai Mitra TIP TOK';
        }
        // Auto refresh table after short delay
        setTimeout(() => { window.location.reload(); }, 900);
      } else {
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Terjadi kesalahan.' });
        } else {
          alert(res.message || 'Gagal mengubah status.');
        }
      }
    })
    .catch(err => {
      console.error(err);
      alert('Terjadi kesalahan jaringan.');
    });
  };

  // ── Detail Modal View Logic ──
  let mapDetailInstance = null;
  let markerDetail = null;
  let circleDetail = null;

  document.querySelectorAll('.viewDetailBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const nama = btn.dataset.nama;
      const kategori = btn.dataset.kategori;
      const isTiptok = (btn.dataset.isTiptok === '1');
      const tiptokSisa = parseInt(btn.dataset.tiptokSisa || '0');
      const telp = btn.dataset.telp;
      const email = btn.dataset.email || "-";
      const alamat = btn.dataset.alamat || "-";
      const kota = btn.dataset.kota || "-";
      const wilayah = btn.dataset.wilayah || "Tanpa Wilayah";
      const createdAt = btn.dataset.created || "-";
      const photos = JSON.parse(btn.dataset.foto || '[]');
      
      const latVal = parseFloat(btn.dataset.lat);
      const lonVal = parseFloat(btn.dataset.lon);
      const radVal = parseInt(btn.dataset.rad) || 100;
      const alamatPeta = btn.dataset.alamatLokasi || "Koordinat lokasi belum diset.";

      // Populate details
      document.getElementById('detail_nama').innerText = nama;
      document.getElementById('detail_email').innerText = email;
      document.getElementById('detail_alamat').innerText = alamat;
      document.getElementById('detail_kota').innerText = kota;
      document.getElementById('detail_wilayah').innerText = wilayah;
      document.getElementById('detail_created_at').innerText = createdAt;
      document.getElementById('detail_alamat_peta').innerText = alamatPeta;

      // Populate category badge style
      const catBadge = document.getElementById('detail_kategori');
      catBadge.innerText = kategori;
      catBadge.className = 'category-badge ' + (
        kategori === 'Dealer' ? 'badge-dealer' :
        kategori === 'Installer' ? 'badge-installer' :
        kategori === 'User' ? 'badge-user' : 'badge-default'
      );

      // Populate TIP TOK badge in detail modal
      const tiptokBadgeEl = document.getElementById('detail_tiptok_badge');
      const tiptokQtyEl = document.getElementById('detail_tiptok_qty');
      if (tiptokBadgeEl) {
        if (isTiptok) {
          tiptokBadgeEl.classList.remove('d-none');
          if (tiptokQtyEl) {
            tiptokQtyEl.innerText = tiptokSisa > 0 ? (tiptokSisa + ' Unit') : '';
            tiptokQtyEl.style.display = tiptokSisa > 0 ? 'inline-block' : 'none';
          }
        } else {
          tiptokBadgeEl.classList.add('d-none');
        }
      }

      // Populate avatar initials
      const avatarContainer = document.getElementById('detail_avatar_container');
      const avatarBg = kategori === 'Dealer' ? '#3b82f6' : (kategori === 'Installer' ? '#8b5cf6' : (kategori === 'User' ? '#10b981' : '#64748b'));
      avatarContainer.style.background = avatarBg;
      
      const firstPhoto = photos.length > 0 ? photos[0] : '';
      if (firstPhoto) {
        avatarContainer.innerHTML = `<img src="../uploads/customer/${firstPhoto}" style="width: 100%; height: 100%; object-fit: cover; border-radius:50%;">`;
      } else {
        const words = nama.split(' ');
        const initials = (words[0] ? words[0][0] : '') + (words[1] ? words[1][0] : '');
        avatarContainer.innerHTML = initials.toUpperCase();
      }

      // Populate WA link
      if (telp) {
        document.getElementById('detail_telp_container').style.display = 'block';
        document.getElementById('detail_telp').innerText = '0' + telp.replace(/^62/, '');
        document.getElementById('detail_telp_link').href = 'https://wa.me/' + telp;
      } else {
        document.getElementById('detail_telp_container').style.display = 'none';
      }

      // Fetch and populate visits history
      const visitsContainer = document.getElementById('detail_visits_container');
      visitsContainer.innerHTML = '<div class="text-muted text-center py-3" style="font-size:12.5px;"><span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle; animation:spin 1s linear infinite; margin-right:4px;">progress_activity</span> Memuat riwayat...</div>';
      
      fetch('get_customer_visits.php?id=' + id + '&_t=' + new Date().getTime())
        .then(res => res.json())
        .then(data => {
          visitsContainer.innerHTML = '';
          if (data.length > 0) {
            data.forEach(visit => {
              const item = document.createElement('div');
              item.style.cssText = 'display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1.5px solid #f1f5f9; cursor:pointer; transition:all 0.2s;';
              item.title = 'Klik untuk melihat rincian kunjungan';
              
              item.addEventListener('mouseenter', () => {
                item.style.backgroundColor = '#f8fafc';
                item.style.paddingLeft = '6px';
                item.style.paddingRight = '6px';
              });
              item.addEventListener('mouseleave', () => {
                item.style.backgroundColor = 'transparent';
                item.style.paddingLeft = '0';
                item.style.paddingRight = '0';
              });
              
              item.addEventListener('click', () => {
                openSalesVisitsDetail(visit.sales_id, visit.nama, id, nama);
              });
              
              // Avatar
              let avatarHtml = '';
              if (visit.foto) {
                avatarHtml = `<img src="https://api-teknisi.id-giti.com/storage/profile/${visit.foto}" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:1px solid #e2e8f0;">`;
              } else {
                const words = visit.nama.split(' ');
                const initials = ((words[0] ? words[0][0] : '') + (words[1] ? words[1][0] : '')).toUpperCase();
                avatarHtml = `<div style="width:32px; height:32px; font-size:11.5px; margin:0; background:#475569; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:50%; font-weight:700;">${initials}</div>`;
              }
              
              item.innerHTML = `
                <div style="display:flex; align-items:center; gap:12px;">
                  ${avatarHtml}
                  <div>
                    <span style="font-size:13px; font-weight:700; color:#0f172a; display:block;">${visit.nama}</span>
                    <span style="font-size:10.5px; color:#64748b; display:flex; align-items:center; gap:3px; margin-top:2px;">
                      <span class="material-symbols-outlined" style="font-size:12px;">calendar_month</span>
                      Terakhir: ${visit.terakhir_kunjung}
                    </span>
                  </div>
                </div>
                <span class="badge" style="font-size:9.5px; font-weight:700; background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:4px 8px; border-radius:6px; box-shadow:none; text-transform:none;">
                  ${visit.total_kunjungan} Kunjungan
                </span>
              `;
              visitsContainer.appendChild(item);
            });
          } else {
            visitsContainer.innerHTML = '<div class="text-muted text-center py-3" style="font-size:12.5px;">Belum ada riwayat kunjungan sales.</div>';
          }
        })
        .catch(() => {
          visitsContainer.innerHTML = '<div class="text-danger text-center py-3" style="font-size:12.5px;">Gagal memuat riwayat kunjungan.</div>';
        });

      // Populate photos documentation grid
      const photosGrid = document.getElementById('detail_photos_container');
      photosGrid.innerHTML = '';
      if (photos.length > 0) {
        photos.forEach(photo => {
          const card = document.createElement('div');
          card.className = 'detail-photo-card';
          card.innerHTML = `<img src="../uploads/customer/${photo}">`;
          card.addEventListener('click', () => {
            openGallerySlideshow(photos, nama);
          });
          photosGrid.appendChild(card);
        });
      } else {
        photosGrid.innerHTML = '<span class="text-muted" style="font-size: 12.5px;">Belum ada dokumentasi foto toko.</span>';
      }

      // Handle map setup on modal show
      const detailModalEl = document.getElementById('detailModal');
      const onModalShown = () => {
        if (!isNaN(latVal) && !isNaN(lonVal)) {
          document.getElementById('map_detail').style.display = 'block';
          const latlng = L.latLng(latVal, lonVal);

          if (!mapDetailInstance) {
            mapDetailInstance = L.map('map_detail').setView(latlng, 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              maxZoom: 19,
              attribution: '© OpenStreetMap contributors'
            }).addTo(mapDetailInstance);

            markerDetail = L.marker(latlng).addTo(mapDetailInstance);
            circleDetail = L.circle(latlng, { radius: radVal, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.15 }).addTo(mapDetailInstance);
          } else {
            mapDetailInstance.setView(latlng, 16);
            markerDetail.setLatLng(latlng);
            circleDetail.setLatLng(latlng).setRadius(radVal);
            mapDetailInstance.invalidateSize();
          }
        } else {
          document.getElementById('map_detail').style.display = 'none';
        }
        detailModalEl.removeEventListener('shown.bs.modal', onModalShown);
      };
      detailModalEl.addEventListener('shown.bs.modal', onModalShown);
    });
  });

  // Function to show visits timeline for a sales person and customer
  function openSalesVisitsDetail(salesId, salesName, custId, custName) {
    console.log('openSalesVisitsDetail params:', { salesId, salesName, custId, custName });
    
    document.getElementById('visits_detail_sales_name').textContent = salesName;
    document.getElementById('visits_detail_cust_name').textContent = custName;
    
    const container = document.getElementById('visits_timeline_container');
    container.innerHTML = '<div class="text-muted text-center py-5" style="font-size:13px;"><span class="material-symbols-outlined" style="font-size:20px; vertical-align:middle; animation:spin 1s linear infinite; margin-right:6px; color:#2563eb;">progress_activity</span> Memuat rincian kunjungan...</div>';
    
    // Open modal first so the user gets instant feedback
    const subModal = new bootstrap.Modal(document.getElementById('visitsDetailModal'));
    subModal.show();
    
    fetch(`get_sales_customer_visits.php?customer_id=${custId}&sales_id=${salesId}&_t=${new Date().getTime()}`)
      .then(res => res.json())
      .then(data => {
        container.innerHTML = '';
        if (data.length > 0) {
          data.forEach(v => {
            const item = document.createElement('div');
            item.style.cssText = 'position:relative; padding-left:28px; margin-bottom:24px; border-left: 2px solid #cbd5e1;';
            
            // Dot indicator
            const dot = document.createElement('div');
            const dotColor = v.status_kegiatan === 'dibatalkan' ? '#ef4444' : '#2563eb';
            const dotShadow = v.status_kegiatan === 'dibatalkan' ? '#fecaca' : '#cbd5e1';
            dot.style.cssText = `position:absolute; left:-5px; top:4px; width:12px; height:12px; border-radius:50%; background:${dotColor}; border:2.5px solid #fff; box-shadow: 0 0 0 1.5px ${dotShadow};`;
            item.appendChild(dot);
            
            // Card content
            const card = document.createElement('div');
            card.className = 'card border-0 shadow-sm rounded-4 p-3';
            card.style.cssText = 'background:#fff; margin-top:-6px; border:1px solid #e2e8f0 !important;';
            
            // Header info
            let invoiceBadge = v.no_invoice ? `<span class="badge" style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; font-size:10px; font-weight:700; text-transform:none; box-shadow:none;">Inv: ${v.no_invoice}</span>` : '';
            
            let statusBadge = '';
            if (v.status_kegiatan === 'dibatalkan') {
              statusBadge = `<span class="badge" style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; font-size:10px; font-weight:700; text-transform:none; box-shadow:none;">Dibatalkan</span>`;
            } else {
              statusBadge = `<span class="badge" style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; font-size:10px; font-weight:700; text-transform:none; box-shadow:none;">Prospek: ${v.tipe_prospek}</span>`;
            }
            
            // Clock In / Out info
            let clockInfo = '';
            if (v.status_kegiatan === 'dibatalkan' && (v.ci_at === '-' || !v.ci_at)) {
              clockInfo = `
                <div class="col-12 text-muted" style="font-style:italic; font-size:12px;">
                  * Kunjungan dibatalkan/reschedule sebelum Sales sempat melakukan Clock In.
                </div>
              `;
            } else {
              clockInfo = `
                <div class="col-sm-6 mb-1">
                  <span style="display:block; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">CLOCK IN</span>
                  <span style="color:#0f172a; font-weight:600;"><span class="material-symbols-outlined" style="font-size:13px; vertical-align:middle; color:#10b981; margin-right:3px;">login</span> ${v.ci_at}</span>
                </div>
                <div class="col-sm-6 mb-1">
                  <span style="display:block; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">CLOCK OUT</span>
                  <span style="color:#0f172a; font-weight:600;"><span class="material-symbols-outlined" style="font-size:13px; vertical-align:middle; color:#ef4444; margin-right:3px;">logout</span> ${v.co_at}</span>
                </div>
              `;
            }
            
            // Notes or cancel reason
            let notesInfo = '';
            if (v.status_kegiatan === 'dibatalkan') {
              notesInfo = `
                <div class="p-2 rounded-3" style="font-size:12px; color:#c53030; line-height:1.4; border: 1px solid #feb2b2; background:#fff5f5;">
                  <strong style="color:#9b2c2c; font-size:10.5px; display:block; margin-bottom:2px; text-transform:uppercase;">Alasan Pembatalan / Reschedule:</strong>
                  "${v.reschedule_reason || 'Tidak ada alasan yang dicantumkan.'}"
                </div>
              `;
            } else {
              notesInfo = `
                <div class="p-2 bg-light rounded-3" style="font-size:12px; color:#334155; line-height:1.4; border: 1px solid #e2e8f0;">
                  <strong style="color:#0f172a; font-size:10.5px; display:block; margin-bottom:2px; text-transform:uppercase;">Catatan Kunjungan:</strong>
                  "${v.catatan_visit || 'Tidak ada catatan.'}"
                </div>
              `;
            }
            
            // Photos rendering
            let photosHtml = '';
            if (v.images.length > 0) {
              photosHtml = `
                <div style="margin-top:12px;">
                  <strong style="color:#0f172a; font-size:11px; display:block; margin-bottom:6px; text-transform:uppercase;">Dokumentasi Foto:</strong>
                  <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    ${v.images.map(img => `
                      <div style="width:64px; height:64px; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0; cursor:pointer;" onclick="window.open('https://api-teknisi.id-giti.com/storage/image/${img}', '_blank')">
                        <img src="https://api-teknisi.id-giti.com/storage/image/${img}" style="width:100%; height:100%; object-fit:cover;">
                      </div>
                    `).join('')}
                  </div>
                </div>
              `;
            }
            
            card.innerHTML = `
              <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; border-bottom:1.5px solid #f1f5f9; padding-bottom:8px; margin-bottom:10px;">
                <div>
                  <span style="font-size:10px; color:#64748b; text-transform:uppercase; font-weight:700; letter-spacing:0.05em; display:block;">JADWAL KUNJUNGAN</span>
                  <span style="font-size:13px; font-weight:800; color:#0f172a;">${v.jadwal}</span>
                </div>
                <div style="display:flex; gap:6px;">
                  ${statusBadge}
                  ${invoiceBadge}
                </div>
              </div>
              
              <div class="row mb-2" style="font-size:12px; color:#475569;">
                ${clockInfo}
              </div>
              
              ${notesInfo}
              
              ${photosHtml}
            `;
            
            item.appendChild(card);
            container.appendChild(item);
          });
        } else {
          container.innerHTML = '<div class="text-muted text-center py-5" style="font-size:13px;">Belum ada riwayat kunjungan.</div>';
        }
      })
      .catch(() => {
        container.innerHTML = '<div class="text-danger text-center py-5" style="font-size:13px;">Gagal memuat riwayat kunjungan.</div>';
      });
  }

  // Fix body scroll locking when nesting modals
  document.getElementById('visitsDetailModal').addEventListener('hidden.bs.modal', function () {
    if (document.getElementById('detailModal').classList.contains('show')) {
      document.body.classList.add('modal-open');
    }
  });

  // Make Modal Cards Draggable (Drag & Drop)
  function makeModalDraggable(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    const dialog = modal.querySelector('.modal-dialog');
    const header = modal.querySelector('.modal-header');
    
    if (!dialog || !header) return;
    
    header.style.cursor = 'move';
    
    let isDragging = false;
    let startX, startY;
    let modalLeft = 0, modalTop = 0;
    
    modal.addEventListener('show.bs.modal', () => {
      dialog.style.left = '0px';
      dialog.style.top = '0px';
      modalLeft = 0;
      modalTop = 0;
    });
    
    header.addEventListener('mousedown', (e) => {
      if (e.target.closest('.btn-close') || e.target.closest('button')) return;
      
      isDragging = true;
      startX = e.clientX - modalLeft;
      startY = e.clientY - modalTop;
      
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
      
      document.body.style.userSelect = 'none';
      dialog.style.transition = 'none';
    });
    
    function onMouseMove(e) {
      if (!isDragging) return;
      
      modalLeft = e.clientX - startX;
      modalTop = e.clientY - startY;
      
      dialog.style.left = modalLeft + 'px';
      dialog.style.top = modalTop + 'px';
    }
    
    function onMouseUp() {
      isDragging = false;
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
      
      document.body.style.userSelect = '';
      dialog.style.transition = '';
    }
  }

  // Initialize draggable modals
  makeModalDraggable('detailModal');
  makeModalDraggable('visitsDetailModal');
  makeModalDraggable('editModal');
</script>
</body>
</html>
