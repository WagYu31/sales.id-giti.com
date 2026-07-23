<?php
// Mulai output buffering: Mencegah error "headers already sent"
ob_start(); 

// customer_add.php
$page_title = 'Tambah Customer Baru';
require_once 'includes/db.php';

// 1. Cek Sesi (Logika)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';

// 2. Proses Simpan Data (Logika Database & Redirect)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();

    try {
        // Data utama customer
        $tgl_input = $_POST['tgl_input'];
        $nama_toko = $_POST['nama_toko'];
        $kategori = $_POST['kategori'];
        $sales_id = ($_SESSION['role'] == 'superadmin') ? ($_POST['sales_id'] ?: null) : $_SESSION['user_id'];

        $foto_toko = null;
        if (isset($_FILES['foto_toko']) && $_FILES['foto_toko']['error'] == 0) {
            $target_dir = "assets/uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $foto_toko = uniqid() . '-' . basename($_FILES["foto_toko"]["name"]);
            $target_file = $target_dir . $foto_toko;
            if (!move_uploaded_file($_FILES["foto_toko"]["tmp_name"], $target_file)) {
                throw new Exception("Maaf, terjadi kesalahan saat mengunggah file.");
            }
        }

        $stmt_customer = $conn->prepare("INSERT INTO customers (sales_id, tgl_input, nama_toko, kategori, foto_toko) VALUES (?, ?, ?, ?, ?)");
        $stmt_customer->bind_param("issss", $sales_id, $tgl_input, $nama_toko, $kategori, $foto_toko);
        if (!$stmt_customer->execute()) throw new Exception("Gagal menyimpan data customer utama: " . $stmt_customer->error);
        
        $customer_id = $conn->insert_id;
        $stmt_customer->close();

        // Insert data PICs dan teleponnya ke tabel `customer_pics`
        if (isset($_POST['nama_pic']) && is_array($_POST['nama_pic'])) {
            $stmt_pic = $conn->prepare("INSERT INTO customer_pics (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");
            foreach ($_POST['nama_pic'] as $key => $nama_pic) {
                if (!empty($nama_pic)) {
                    $tlp_pic = $_POST['tlp_pic'][$key] ?? '';
                    $stmt_pic->bind_param("iss", $customer_id, $nama_pic, $tlp_pic);
                    if (!$stmt_pic->execute()) throw new Exception("Gagal menyimpan data PIC: " . $stmt_pic->error);
                }
            }
            $stmt_pic->close();
        }

        // Blok untuk `customer_addresses`
        if (isset($_POST['alamat']) && is_array($_POST['alamat'])) {
            $stmt_address = $conn->prepare("INSERT INTO customer_addresses (customer_id, alamat, kota, link_google_map) VALUES (?, ?, ?, ?)");
            foreach ($_POST['alamat'] as $key => $alamat) {
                if (!empty($alamat)) {
                    $kota = $_POST['kota'][$key] ?? '';
                    $link_google_map = $_POST['link_google_map'][$key] ?? '';
                    $stmt_address->bind_param("isss", $customer_id, $alamat, $kota, $link_google_map);
                    if (!$stmt_address->execute()) throw new Exception("Gagal menyimpan data alamat: " . $stmt_address->error);
                }
            }
            $stmt_address->close();
        }

        $conn->commit();
        $_SESSION['flash_message'] = "Customer berhasil ditambahkan!";
        header("Location: index.php"); // Sekarang redirect ini akan berjalan lancar
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// 3. Persiapan Data untuk Tampilan (Logika)
$sales_list = [];
if ($_SESSION['role'] == 'superadmin') {
    $result = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sales_list[] = $row;
        }
    }
}

// 4. Load Tampilan Utama (HTML)
// Kita pindahkan pemanggilan header.php ke sini, SETELAH semua logika dan proses redirect selesai
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-person-plus-fill"></i> Tambah Customer Baru</h1>
    <a href="customer_management.php" class="btn btn-secondary">Kembali</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<form action="customer_add.php" method="POST" enctype="multipart/form-data">
    <div class="card mb-4">
        <div class="card-header">
            <h5>Informasi Utama</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nama_toko" class="form-label">Nama Toko <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama_toko" name="nama_toko" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="tgl_input" class="form-label">Tanggal Input <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="tgl_input" name="tgl_input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Kategori <span class="text-danger">*</span></label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="kategori" id="kategori_installer" value="INSTALLER" checked required>
                        <label class="form-check-label" for="kategori_installer">INSTALLER</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="kategori" id="kategori_master_dealer" value="MASTER DEALER">
                        <label class="form-check-label" for="kategori_master_dealer">MASTER DEALER</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="kategori" id="kategori_dealer" value="DEALER">
                        <label class="form-check-label" for="kategori_dealer">DEALER</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="kategori" id="kategori_user" value="USER">
                        <label class="form-check-label" for="kategori_user">USER</label>
                    </div>
                </div>
            </div>

            <?php if ($_SESSION['role'] == 'superadmin'): ?>
            <div class="mb-3">
                <label for="sales_id" class="form-label">Assign ke Sales</label>
                <select class="form-select" id="sales_id" name="sales_id">
                    <option value="">-- Tidak Di-assign --</option>
                    <?php foreach ($sales_list as $sales): ?>
                        <option value="<?php echo $sales['id']; ?>"><?php echo htmlspecialchars($sales['nama_lengkap']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="foto_toko" class="form-label">Foto Toko (Opsional)</label>
                <input class="form-control" type="file" id="foto_toko" name="foto_toko" accept="image/*">
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Informasi Owner / PIC</h5>
            <button type="button" class="btn btn-sm btn-primary" id="add-pic-btn"><i class="bi bi-plus-circle"></i> Tambah PIC</button>
        </div>
        <div class="card-body" id="pic-container">
            <div class="row pic-item mb-3">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Nama Owner / PIC</label>
                    <input type="text" class="form-control" name="nama_pic[]" placeholder="Nama PIC">
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">No. Telepon PIC (Contoh : 081234xxxxxxx)</label>
                    <input type="text" class="form-control" name="tlp_pic[]" placeholder="No. Telepon">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Informasi Alamat</h5>
            <button type="button" class="btn btn-sm btn-primary" id="add-address-btn"><i class="bi bi-plus-circle"></i> Tambah Alamat</button>
        </div>
        <div class="card-body" id="address-container">
            <div class="address-item border rounded p-3 mb-3">
                <div class="mb-2">
                    <label class="form-label">Alamat Lengkap</label>
                    <textarea class="form-control" name="alamat[]" rows="2" placeholder="Alamat Lengkap"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Kota</label>
                        <input type="text" class="form-control" name="kota[]" placeholder="Kota">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Link Google Map</label>
                        <input type="url" class="form-control" name="link_google_map[]" placeholder="https://maps.google.com/...">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-grid">
        <button type="submit" class="btn btn-primary btn-lg">Simpan Customer</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    function addDynamicItem(containerId, templateHTML) {
        const container = document.getElementById(containerId);
        container.insertAdjacentHTML('beforeend', templateHTML);
    }

    document.body.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-item-btn')) {
            e.target.closest('.dynamic-item').remove();
        }
    });

    const picTemplate = `
        <div class="row pic-item mb-3 dynamic-item">
            <div class="col-md-6 mb-2">
                <input type="text" class="form-control" name="nama_pic[]" placeholder="Nama PIC">
            </div>
            <div class="col-md-5 mb-2">
                <input type="text" class="form-control" name="tlp_pic[]" placeholder="No. Telepon">
            </div>
            <div class="col-md-1 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-danger remove-item-btn w-100"><i class="bi bi-trash"></i></button>
            </div>
        </div>`;
    document.getElementById('add-pic-btn').addEventListener('click', () => addDynamicItem('pic-container', picTemplate));
    
    const addressTemplate = `
        <div class="address-item border rounded p-3 mb-3 dynamic-item">
            <div class="d-flex justify-content-end">
                 <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn mb-2"><i class="bi bi-trash"></i> Hapus Alamat Ini</button>
            </div>
            <div class="mb-2">
                <label class="form-label">Alamat Lengkap</label>
                <textarea class="form-control" name="alamat[]" rows="2" placeholder="Alamat Lengkap"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Kota</label>
                    <input type="text" class="form-control" name="kota[]" placeholder="Kota">
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Link Google Map</label>
                    <input type="url" class="form-control" name="link_google_map[]" placeholder="https://maps.google.com/...">
                </div>
            </div>
        </div>`;
    document.getElementById('add-address-btn').addEventListener('click', () => addDynamicItem('address-container', addressTemplate));
});
</script>

<?php require_once 'includes/floating_menu.php'; ?>
<?php require_once 'includes/footer.php'; ?>
<?php 
// Akhiri output buffering dan kirim data ke browser
ob_end_flush(); 
?>