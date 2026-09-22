<?php
include "conn.php";
include "session.php";
include "get-user-data.php";
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

// UPDATE
$successMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $id = $_POST['update_id'];
    $nama = $_POST['edit_nama'];
    $kategori = $_POST['edit_kategori'];
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

    $stmt = $conn->prepare("UPDATE sales_customer SET nama = ?, kategori = ?, telp_pribadi = ?, email = ?, alamat = ?, kota = ?, id_wilayah = ?, foto = ?, lat = ?, lon = ?, rad = ?, alamat_lokasi = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssssssisssssi", $nama, $kategori, $telp, $email, $alamat, $kota, $id_wilayah, $foto_json_updated, $lat, $lon, $rad, $location_address, $id);
    $stmt->execute();
    $stmt->close();

    $successMsg = "Data Customer berhasil diperbarui!";
}

// INSERT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_id'])) {
    $nama = $_POST['nama'];
    $kategori = $_POST['kategori'];
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

    $stmt = $conn->prepare("INSERT INTO sales_customer (kode_customer, kategori, nama, telp_pribadi, email, alamat, kota, id_wilayah, foto, lat, lon, rad, alamat_lokasi, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("sssssssisssss", $kode_customer, $kategori, $nama, $telp, $email, $alamat, $kota, $id_wilayah, $foto_json, $lat, $lon, $rad, $location_address);
    $stmt->execute();
    $stmt->close();

    $successMsg = "Customer baru berhasil ditambahkan!";
}

// Ambil data wilayah untuk filter
$wilayahList = mysqli_query($conn, "SELECT id, nama FROM wilayah ORDER BY nama ASC");
$kategoriList = ['Dealer', 'Installer', 'User'];

$filterSearch = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterWilayah = isset($_GET['wilayah']) ? trim($_GET['wilayah']) : 'all';
$filterKategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : 'all';

// Build the query
$queryStr = "SELECT c.*, w.nama AS nama_wilayah 
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

if ($filterKategori !== 'all') {
    $safeKategori = mysqli_real_escape_string($conn, $filterKategori);
    $queryStr .= " AND c.kategori = '$safeKategori' ";
}

$queryStr .= " ORDER BY c.id DESC";
$salesData = mysqli_query($conn, $queryStr);
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
    /* ── Premium Styling ── */
    .card-premium {
      background: #fff;
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
      margin-bottom: 24px;
    }
    
    .section-header-premium {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 24px;
      background: #1e293b;
      color: #fff;
    }
    
    .section-header-premium h6 {
      margin: 0;
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      display: flex;
      align-items: center;
    }
    
    .card-body-premium {
      padding: 36px 40px;
    }

    /* ── Form inputs ── */
    .form-group-premium {
      margin-bottom: 20px;
    }
    
    .form-label-premium {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 700;
      color: #64748b;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    
    .input-premium {
      width: 100%;
      height: 48px !important;
      border: 1.5px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px 16px !important;
      font-size: 14px;
      color: #1e293b;
      background-color: #fff;
      transition: all 0.2s ease-in-out;
      box-sizing: border-box;
    }
    
    .input-premium:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
      outline: none;
      background-color: #fff;
    }

    /* ── Maps containers ── */
    #map_create, #map_edit, #map_detail {
      height: 290px;
      width: 100%;
      border-radius: 14px;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 4px 12px rgba(0,0,0,0.02);
      margin-top: 10px;
    }
    
    #map_detail {
      height: 320px;
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
      border-color: #3b82f6;
      background: #f0f7ff;
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
      box-shadow: 0 2px 6px rgba(0,0,0,0.03);
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
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    /* Existing Photos List in Edit */
    .edit-photo-thumb {
      position: relative;
      width: 72px;
      height: 72px;
      border-radius: 10px;
      overflow: hidden;
      border: 1.5px solid #e2e8f0;
    }
    .edit-photo-thumb img {
      width: 100%; height: 100%; object-fit: cover;
    }

    /* ── Custom Styled Select arrow ── */
    select.input-premium {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
      background-repeat: no-repeat;
      background-position: right 14px center;
      background-size: 16px;
      padding-right: 40px !important;
      cursor: pointer;
    }

    /* ── Category Pill Group (Radio Switcher) ── */
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
      font-size: 12px;
      font-weight: 700;
      border-radius: 12px;
      border: 1.5px solid #e2e8f0;
      color: #64748b;
      background: #fff;
      transition: all 0.2s ease-in-out;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      box-sizing: border-box;
    }
    
    /* Dealer checked style */
    #kategori_dealer:checked + .span-dealer {
      border-color: #3b82f6;
      background: #eff6ff;
      color: #1d4ed8;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.05);
    }
    /* Installer checked style */
    #kategori_installer:checked + .span-installer {
      border-color: #8b5cf6;
      background: #f5f3ff;
      color: #6d28d9;
      box-shadow: 0 4px 12px rgba(139, 92, 246, 0.05);
    }
    /* User checked style */
    #kategori_user:checked + .span-user {
      border-color: #10b981;
      background: #ecfdf5;
      color: #047857;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.05);
    }

    /* ── Avatars in Table ── */
    .avatar-initials-table {
      width: 44px; height: 44px;
      border-radius: 50%;
      color: #fff;
      font-size: 14px; font-weight: 700;
      display: inline-flex; align-items: center; justify-content: center;
      margin-right: 14px;
      vertical-align: middle;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
      cursor: pointer;
      transition: all 0.25s;
      flex-shrink: 0;
    }

    .avatar-initials-table:hover {
      transform: scale(1.08);
      box-shadow: 0 6px 14px rgba(0, 0, 0, 0.15);
    }
    
    .customer-identity-cell {
      display: flex;
      align-items: center;
      text-align: left;
    }

    /* ── Category Badges ── */
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
 
    /* ── Table custom styling ── */
    .premium-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .premium-table th {
      background: #f8fafc;
      border-bottom: 2px solid #e2e8f0;
      color: #64748b;
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
      transition: all 0.2s ease-in-out;
    }

    .premium-table tbody tr:hover td {
      background-color: #f8fafc;
    }
    
    /* WhatsApp Pill style - Solid Premium Green Badge */
    .wa-pill {
      background: #25D366 !important;
      border: none !important;
      color: #fff !important;
      font-size: 12px; 
      font-weight: 700;
      padding: 6px 14px;
      border-radius: 30px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease-in-out;
      width: fit-content;
      box-shadow: 0 4px 10px rgba(37, 211, 102, 0.2);
    }
    .wa-pill:hover {
      background: #20ba59 !important;
      color: #fff !important;
      transform: translateY(-1px);
      box-shadow: 0 6px 14px rgba(37, 211, 102, 0.3);
    }
    .wa-pill svg {
      flex-shrink: 0;
      fill: #fff !important;
    }

    /* Map Pin Button */
    .btn-map-pin {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      color: #2563eb;
      width: 36px; height: 36px;
      border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      transition: all 0.2s;
      cursor: pointer;
      box-shadow: 0 2px 4px rgba(37, 99, 235, 0.05);
    }
    .btn-map-pin:hover {
      background: #2563eb;
      color: #fff;
      transform: scale(1.1);
      box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
    }
    .btn-map-pin .material-symbols-outlined {
      font-size: 18px;
    }

    /* ── Action Buttons ── */
    .btn-act {
      width: 36px; height: 36px; padding: 0; display: inline-flex;
      align-items: center; justify-content: center; border-radius: 50%;
      border: 1px solid transparent; transition: all 0.2s; cursor: pointer; text-decoration: none;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .btn-act:hover { transform: scale(1.1); }
    .btn-act .material-symbols-outlined { font-size: 18px; }
    .btn-act-view { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
    .btn-act-view:hover { background: #0369a1; color: #fff; box-shadow: 0 4px 10px rgba(3, 105, 161, 0.2); }
    .btn-act-edit { background: #fffbeb; color: #d97706; border-color: #fef3c7; margin-left: 8px; }
    .btn-act-edit:hover { background: #d97706; color: #fff; box-shadow: 0 4px 10px rgba(217, 119, 6, 0.2); }
    .btn-act-delete { background: #fef2f2; color: #dc2626; border-color: #fee2e2; margin-left: 8px; }
    .btn-act-delete:hover { background: #dc2626; color: #fff; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2); }
 
    .btn-submit-premium {
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%);
      color: #fff !important;
      border: none;
      border-radius: 12px;
      padding: 12px 28px;
      font-size: 14px; font-weight: 700;
      display: inline-flex; align-items: center; gap: 6px;
      box-shadow: 0 4px 20px rgba(37, 99, 235, 0.25);
      transition: all 0.22s ease;
      cursor: pointer;
    }
    
    .btn-submit-premium:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(37, 99, 235, 0.4);
    }
    
    .btn-submit-premium:active {
      transform: translateY(0);
    }
    
    .btn-submit-premium .material-symbols-outlined {
      font-size: 18px;
    }

    /* ── Modal Premium Styling ── */
    .modal-content-premium {
      border-radius: 16px;
      border: none;
      overflow: hidden;
      box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }
    
    .modal-header-premium {
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
      color: #fff;
      padding: 20px 24px;
      border-bottom: none;
    }
    
    .modal-title-premium {
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .modal-body-premium {
      padding: 28px 24px;
      background: #fff;
    }
    
    .modal-footer-premium {
      background: #f8fafc;
      padding: 16px 24px;
      border-top: 1px solid #f1f5f9;
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }

    input[type="checkbox"] {
      -webkit-appearance: checkbox;
      -moz-appearance: checkbox;
      appearance: checkbox;
    }
    
    /* Detail View Styling */
    .detail-info-row {
      display: flex;
      border-bottom: 1.5px solid #f1f5f9;
      padding: 12px 0;
      align-items: center;
    }
    .detail-info-label {
      width: 140px;
      font-size: 11px;
      font-weight: 800;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      flex-shrink: 0;
    }
    .detail-info-value {
      font-size: 14px;
      color: #1e293b;
      font-weight: 600;
    }
    
    .detail-photo-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
      gap: 14px;
    }
    
    .detail-photo-card {
      height: 110px;
      border-radius: 12px;
      overflow: hidden;
      border: 1.5px solid #e2e8f0;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(0,0,0,0.03);
      transition: all 0.22s;
    }
    .detail-photo-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 18px rgba(0,0,0,0.1);
      border-color: #3b82f6;
    }
    .detail-photo-card img {
      width: 100%; height: 100%; object-fit: cover;
    }

    <?php include "css/floating-menu2.css"; ?>
  
    /* ── Mobile Responsive Enhancements ── */
    @media (max-width: 991.98px) {
      .card-body-premium {
        padding: 20px 16px !important;
      }
      .section-header-premium {
        padding: 14px 16px !important;
      }
      .category-pill-group {
        flex-wrap: wrap !important;
      }
      .category-pill-label {
        flex: 1 1 calc(33.333% - 8px) !important;
        min-width: 85px !important;
      }
      .btn-submit-premium {
        width: 100% !important;
        justify-content: center !important;
        min-height: 48px !important;
        touch-action: manipulation !important;
      }
      .premium-table th, .premium-table td {
        padding: 12px 10px !important;
      }
      .btn-act {
        min-width: 38px !important;
        min-height: 38px !important;
        margin: 2px !important;
        touch-action: manipulation !important;
      }
      .modal-dialog {
        margin: 10px !important;
      }
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
      <div class="alert alert-success text-white font-weight-bold mb-4" style="background: #10b981; border: none; border-radius: 10px; padding: 14px 20px;">
        <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">check_circle</span>
        <?php echo $successMsg; ?>
      </div>
    <?php endif; ?>

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
                  <div class="d-flex gap-2">
                    <input type="text" id="gmap_search" class="input-premium" placeholder="Contoh: Jawa Timur atau -6.175, 106.827...">
                    <button type="button" id="gmap_search_btn" class="btn bg-gradient-info text-white font-weight-bold" style="border-radius:10px; padding: 12px 18px; font-size:11px; display:inline-flex; align-items:center; gap:4px; margin-bottom:0;">
                      <i class="fa-solid fa-magnifying-glass text-xs"></i> CARI
                    </button>
                  </div>
                </div>

                <!-- Leaflet Map create -->
                <div id="map_create"></div>

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
                  <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.12);backdrop-filter:blur(12px);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.1);">
                      <span class="material-symbols-outlined" style="color:#fff;font-size:18px;">groups</span>
                  </div>
                  <div>
                      <h5 style="color:#fff;margin:0;font-size:15px;font-weight:700;letter-spacing:-0.2px;">Daftar Sales Customer</h5>
                  </div>
              </div>
              <span class="badge bg-light text-dark font-weight-bold" style="font-size: 11px; padding: 6px 14px; border-radius: 10px; border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.08);"><?= mysqli_num_rows($salesData); ?> Customer Terdaftar</span>
          </div>
      </div>
      
      <!-- Premium Filter Bar -->
      <div style="background:#f8fafc; padding:20px 28px; border-bottom:1px solid #e2e8f0;">
          <form method="GET" action="customer.php" class="row g-3 align-items-end">
              <div class="col-12 col-md-4">
                  <label class="form-label text-xs font-weight-bold text-uppercase text-secondary mb-1">Cari Customer</label>
                  <div class="input-group input-group-outline">
                      <input type="text" name="search" class="form-control bg-white" placeholder="Nama, kode, telp, atau alamat..." value="<?= htmlspecialchars($filterSearch); ?>">
                  </div>
              </div>
              <div class="col-6 col-md-3">
                  <label class="form-label text-xs font-weight-bold text-uppercase text-secondary mb-1">Wilayah / Area</label>
                  <div class="input-group input-group-outline">
                      <select name="wilayah" class="form-select form-control bg-white px-3" style="border:1px solid #d2d6da; border-radius:0.375rem; -webkit-appearance: auto; -moz-appearance: auto; appearance: auto;">
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
                      <select name="kategori" class="form-select form-control bg-white px-3" style="border:1px solid #d2d6da; border-radius:0.375rem; -webkit-appearance: auto; -moz-appearance: auto; appearance: auto;">
                          <option value="all">Semua Kategori</option>
                          <?php foreach($kategoriList as $k): ?>
                              <option value="<?= $k; ?>" <?= ($filterKategori === $k) ? 'selected' : ''; ?>><?= $k; ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
              </div>
              <div class="col-12 col-md-2 d-flex gap-2">
                  <button type="submit" class="btn bg-gradient-info w-100 mb-0 font-weight-bold" style="padding:10.5px 20px;">
                      <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle; margin-right:4px;">search</span> Cari
                  </button>
                  <?php if($filterSearch !== '' || $filterWilayah !== 'all' || $filterKategori !== 'all'): ?>
                      <a href="customer.php" class="btn bg-gradient-secondary mb-0 font-weight-bold" style="padding:10.5px 15px;" title="Reset Filter">
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
            ?>
            <tr>
              <td style="text-align: center; font-weight: 700; color: #64748b; font-size:12px;"><?= $no++; ?></td>
              
              <td>
                <div class="customer-identity-cell">
                  <!-- Gallery Trigger Avatar -->
                  <?php if (!empty($firstPhoto) && file_exists("../uploads/customer/" . $firstPhoto)): ?>
                    <div class="avatar-initials-table openGalleryBtn" 
                         style="background: <?= $avatarBg; ?>; overflow: hidden; padding: 0;"
                         data-photos='<?= htmlspecialchars(json_encode($photos)); ?>'
                         data-name="<?= htmlspecialchars($row['nama'] ?? ''); ?>">
                      <img src="../uploads/customer/<?= htmlspecialchars($firstPhoto); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                  <?php else: ?>
                    <div class="avatar-initials-table" style="background: <?= $avatarBg; ?>; cursor: default;">
                      <?php 
                        $words = explode(' ', $row['nama'] ?? '');
                        echo strtoupper(substr($words[0] ?? '', 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                      ?>
                    </div>
                  <?php endif; ?>
                  
                  <div style="display:flex; flex-direction:column; gap:4px;">
                    <span style="font-weight: 700; color: #0f172a; font-size:14px; line-height:1.2;"><?= htmlspecialchars($row['nama'] ?? ''); ?></span>
                    <div style="display:flex; gap:6px; align-items:center;">
                      <span class="category-badge <?= $badgeClass; ?>"><?= $kat; ?></span>
                      <span class="badge text-capitalize" style="font-size: 8.5px; padding: 3px 8px; font-weight: 700; letter-spacing: 0.05em; border-radius:30px; text-transform: uppercase; <?= $wBadge; ?>">
                        <?= htmlspecialchars($regionName); ?>
                      </span>
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
                <!-- VIEW DETAIL BUTTON -->
                <button type="button" class="btn-act btn-act-view viewDetailBtn"
                  data-id="<?= $row['id']; ?>"
                  data-nama="<?= htmlspecialchars($row['nama'] ?? ''); ?>"
                  data-kategori="<?= htmlspecialchars($row['kategori'] ?? ''); ?>"
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
                <button type="button" class="btn-act btn-act-edit editBtn"
                  data-id="<?= $row['id']; ?>"
                  data-nama="<?= htmlspecialchars($row['nama'] ?? ''); ?>"
                  data-kategori="<?= htmlspecialchars($row['kategori'] ?? ''); ?>"
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
                      <div style="display:flex; gap:6px; margin-top:4px; align-items:center;">
                        <span id="detail_kategori" class="category-badge">-</span>
                        <span id="detail_wilayah" class="badge" style="font-size: 8.5px; padding: 4px 10px; border-radius:30px; background:#475569; color:#fff; text-transform:uppercase; font-weight:700;">-</span>
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

    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <form method="POST" class="modal-content modal-content-premium" enctype="multipart/form-data" id="editCustomerForm">
          <div class="modal-header modal-header-premium">
            <h5 class="modal-title modal-title-premium">
              <span class="material-symbols-outlined">manage_accounts</span>
              Ubah Data Sales Customer
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body modal-body-premium">
            <div class="row">
              
              <!-- EDIT LEFT SIDE: Details -->
              <div class="col-lg-7" style="border-right: 1px solid #f1f5f9; padding-right: 28px;">
                <input type="hidden" name="update_id" id="edit_id">
                
                <div class="row">
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">Nama Toko / Personal</label>
                    <input type="text" name="edit_nama" id="edit_nama" class="input-premium" required>
                  </div>
                  
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">Kategori Customer</label>
                    <div class="d-flex align-items-center gap-4 mt-2">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="edit_kategori" id="edit_kategori_dealer" value="Dealer" required>
                        <label class="form-check-label font-weight-bold text-sm text-dark" for="edit_kategori_dealer">Dealer</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="edit_kategori" id="edit_kategori_installer" value="Installer">
                        <label class="form-check-label font-weight-bold text-sm text-dark" for="edit_kategori_installer">Installer</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="edit_kategori" id="edit_kategori_user" value="User">
                        <label class="form-check-label font-weight-bold text-sm text-dark" for="edit_kategori_user">User</label>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">Wilayah Customer</label>
                    <select name="edit_id_wilayah" id="edit_id_wilayah" class="input-premium" required>
                      <option value="">-- Pilih Wilayah --</option>
                      <?php 
                      $wQuery2 = mysqli_query($conn, "SELECT * FROM wilayah WHERE deleted_at IS NULL ORDER BY nama ASC");
                      while ($w2 = mysqli_fetch_assoc($wQuery2)) {
                          echo "<option value='{$w2['id']}'>" . htmlspecialchars($w2['nama']) . "</option>";
                      }
                      ?>
                    </select>
                  </div>
                  
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">No. Telepon</label>
                    <input type="text" name="edit_telp" id="edit_telp" class="input-premium">
                  </div>
                  
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">Email</label>
                    <input type="text" name="edit_email" id="edit_email" class="input-premium">
                  </div>
                  
                  <div class="col-md-6 form-group-premium">
                    <label class="form-label-premium">Kota</label>
                    <input type="text" name="edit_kota" id="edit_kota" class="input-premium">
                  </div>

                  <div class="col-md-12 form-group-premium">
                    <label class="form-label-premium">Alamat Lengkap</label>
                    <input type="text" name="edit_alamat" id="edit_alamat" class="input-premium">
                  </div>

                  <!-- Existing Photos -->
                  <div class="col-md-12 form-group-premium mt-3">
                    <label class="form-label-premium">Foto Dokumentasi Saat Ini (Klik ❌ untuk Menghapus)</label>
                    <div class="d-flex flex-wrap gap-3 mb-3" id="edit_existing_photos_container">
                      <!-- preview -->
                    </div>
                    <input type="hidden" name="deleted_existing_photos" id="deleted_existing_photos">
                    
                    <label class="form-label-premium">Tambah Foto Dokumentasi Baru</label>
                    <div class="dropzone-area" id="dropzone_edit">
                      <span class="material-symbols-outlined dropzone-icon">cloud_upload</span>
                      <p class="dropzone-text">Drag &amp; drop file baru di sini, atau klik untuk memilih</p>
                      <input type="file" id="foto_input_edit" name="edit_foto[]" multiple accept="image/*" class="d-none">
                    </div>
                    <div class="preview-grid" id="preview_grid_edit"></div>
                  </div>
                </div>
              </div>

              <!-- EDIT RIGHT SIDE: Location map -->
              <div class="col-lg-5" style="padding-left: 28px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <div style="width:3px;height:16px;background:#10b981;border-radius:2px;"></div>
                    <span style="font-size:12px;font-weight:800;color:#1e293b;text-transform: uppercase; letter-spacing: 0.05em;">Edit Koordinat Toko (Geofence)</span>
                </div>

                <div class="form-group-premium">
                  <label class="form-label-premium">
                    <i class="fa-solid fa-magnifying-glass text-xs me-1 text-primary"></i> Cari Alamat / Koordinat Baru
                  </label>
                  <div class="d-flex gap-2">
                    <input type="text" id="gmap_search_edit" class="input-premium" placeholder="Contoh: Surabaya atau -7.250, 112.750...">
                    <button type="button" id="gmap_search_btn_edit" class="btn bg-gradient-info text-white font-weight-bold" style="border-radius:10px; padding: 12px 18px; font-size:11px; display:inline-flex; align-items:center; gap:4px; margin-bottom:0;">
                      <i class="fa-solid fa-magnifying-glass text-xs"></i> CARI
                    </button>
                  </div>
                </div>

                <div id="map_edit"></div>

                <!-- GPS button and Radius input -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                  <button type="button" id="btn_get_location_edit" class="btn btn-outline-primary btn-sm mb-0 d-flex align-items-center gap-1 font-weight-bold" style="border-radius: 8px; font-size: 11px;">
                    <i class="fa-solid fa-location-crosshairs text-xs"></i> Dapatkan Lokasi Saya
                  </button>
                  <div class="d-flex align-items-center gap-2">
                    <span class="text-xs text-secondary font-weight-bold">Radius:</span>
                    <input type="number" id="edit_radius_input" class="input-premium" value="100" style="font-size:12px; padding: 6px 8px !important; text-align:center; width: 60px; border-radius: 8px; margin-bottom: 0;">
                    <span class="text-xs text-secondary font-weight-bold">Meter</span>
                  </div>
                </div>

                <!-- Radius Slider -->
                <div class="mt-3">
                  <label class="form-label-premium d-flex justify-content-between mb-1" style="font-size: 10px; color:#64748b;">
                    <span>Sesuaikan Geofence Radius (Meter)</span>
                    <span id="slider_val_edit" class="text-primary font-weight-bold">100m</span>
                  </label>
                  <input type="range" id="radius_slider_edit" min="10" max="1000" step="10" value="100" class="form-range w-100" style="accent-color: #3b82f6;">
                </div>

                <!-- Latitude and Longitude readouts -->
                <div class="row g-2 mt-2">
                  <div class="col-6">
                    <label class="form-label-premium" style="font-size: 9px; color:#64748b;">Latitude</label>
                    <input type="text" id="edit_lat_display" class="input-premium" placeholder="-6.xxxxx" style="font-family: monospace; font-size:12px; padding: 8px 12px !important; background:#f8fafc; border-radius: 8px;" readonly>
                  </div>
                  <div class="col-6">
                    <label class="form-label-premium" style="font-size: 9px; color:#64748b;">Longitude</label>
                    <input type="text" id="edit_lon_display" class="input-premium" placeholder="106.xxxxx" style="font-family: monospace; font-size:12px; padding: 8px 12px !important; background:#f8fafc; border-radius: 8px;" readonly>
                  </div>
                </div>

                <!-- Hidden parameters to submit -->
                <input type="hidden" id="edit_lat" name="edit_lat">
                <input type="hidden" id="edit_lon" name="edit_lon">
                <input type="hidden" id="edit_radius" name="edit_radius">
                <input type="hidden" id="edit_location_address" name="edit_location_address">
              </div>

            </div>
          </div>
          <div class="modal-footer modal-footer-premium">
            <button type="submit" class="btn-submit-premium">
              <span class="material-symbols-outlined">save</span>
              Simpan Perubahan
            </button>
            <button type="button" class="btn bg-gradient-secondary font-weight-bold" data-bs-dismiss="modal" style="border-radius: 10px; padding: 10px 20px; font-size: 13px;">Batal</button>
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

    <?php include "floating-menu.php"; ?>
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
    const query = document.getElementById('gmap_search').value.trim();
    if (query === "") return;

    const coordsRegex = /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/;
    if (coordsRegex.test(query)) {
      const parts = query.split(',');
      updateCreateMapData(L.latLng(parseFloat(parts[0]), parseFloat(parts[1])), radInputCreate.value);
    } else {
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id&accept-language=id`)
        .then(res => res.json())
        .then(data => {
          if (data && data.length > 0) {
            updateCreateMapData(L.latLng(parseFloat(data[0].lat), parseFloat(data[0].lon)), radInputCreate.value);
          } else {
            alert("Alamat tidak ditemukan.");
          }
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

  function syncRadiusEdit(value) {
    const r = parseInt(value) || defaultRad;
    radInputEdit.value = r;
    radSliderEdit.value = r;
    sliderValEdit.innerText = r + 'm';
    if (circleEdit) {
      circleEdit.setRadius(r);
    }
    document.getElementById('edit_radius').value = r;
  }

  radInputEdit.addEventListener('input', function() {
    syncRadiusEdit(this.value);
  });

  radSliderEdit.addEventListener('input', function() {
    syncRadiusEdit(this.value);
  });

  const editModalEl = document.getElementById('editModal');
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
        document.getElementById('edit_lat_display').value = defaultLat.toFixed(6);
        document.getElementById('edit_lon_display').value = defaultLon.toFixed(6);
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
            updateEditMapData(e.latlng, radInputEdit.value);
          });
          
          markerEdit.on('dragend', function() {
            updateEditMapData(markerEdit.getLatLng(), radInputEdit.value);
          });
      } else {
          mapEditInstance.setView(latlng, 15);
          markerEdit.setLatLng(latlng);
          circleEdit.setLatLng(latlng).setRadius(radVal);
          mapEditInstance.invalidateSize();
      }
  });

  function updateEditMapData(latlng, rad) {
      const r = parseInt(rad) || defaultRad;
      markerEdit.setLatLng(latlng);
      circleEdit.setLatLng(latlng).setRadius(r);
      mapEditInstance.setView(latlng, 16);
      
      document.getElementById('edit_lat').value = latlng.lat;
      document.getElementById('edit_lon').value = latlng.lng;
      document.getElementById('edit_lat_display').value = latlng.lat.toFixed(6);
      document.getElementById('edit_lon_display').value = latlng.lng.toFixed(6);
      syncRadiusEdit(r);
      
      fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&accept-language=id`)
        .then(res => res.json())
        .then(data => {
          document.getElementById('edit_location_address').value = data?.display_name || '';
        })
        .catch(() => {
          document.getElementById('edit_location_address').value = '';
        });
  }

  // GPS Edit Event
  document.getElementById('btn_get_location_edit').addEventListener('click', function() {
    const btn = this;
    const origContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mencari Lokasi...';

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function(position) {
          const lat = position.coords.latitude;
          const lon = position.coords.longitude;
          updateEditMapData(L.latLng(lat, lon), radInputEdit.value);
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

  document.getElementById('gmap_search_btn_edit').addEventListener('click', function() {
    const query = document.getElementById('gmap_search_edit').value.trim();
    if (query === "" || !mapEditInstance) return;

    const coordsRegex = /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/;
    if (coordsRegex.test(query)) {
      const parts = query.split(',');
      updateEditMapData(L.latLng(parseFloat(parts[0]), parseFloat(parts[1])), radInputEdit.value);
    } else {
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id&accept-language=id`)
        .then(res => res.json())
        .then(data => {
          if (data && data.length > 0) {
            updateEditMapData(L.latLng(parseFloat(data[0].lat), parseFloat(data[0].lon)), radInputEdit.value);
          } else {
            alert("Lokasi tidak ditemukan.");
          }
        });
    }
  });

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

      // GPS Data Populate
      document.getElementById('edit_lat').value = btn.dataset.lat || "";
      document.getElementById('edit_lon').value = btn.dataset.lon || "";
      document.getElementById('edit_radius').value = btn.dataset.rad || "100";
      document.getElementById('edit_radius_input').value = btn.dataset.rad || "100";
      document.getElementById('edit_location_address').value = btn.dataset.alamatLokasi || "";
      
      const latVal = parseFloat(btn.dataset.lat);
      const lonVal = parseFloat(btn.dataset.lon);
      document.getElementById('edit_lat_display').value = isNaN(latVal) ? "" : latVal.toFixed(6);
      document.getElementById('edit_lon_display').value = isNaN(lonVal) ? "" : lonVal.toFixed(6);

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

      const kategori = btn.dataset.kategori;
      document.querySelectorAll('input[name="edit_kategori"]').forEach(radio => {
        radio.checked = (radio.value === kategori);
      });
    });
  });

  // ── Detail Modal View Logic ──
  let mapDetailInstance = null;
  let markerDetail = null;
  let circleDetail = null;

  document.querySelectorAll('.viewDetailBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const nama = btn.dataset.nama;
      const kategori = btn.dataset.kategori;
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
