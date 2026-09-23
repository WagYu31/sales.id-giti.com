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

<style>
/* ── Hero Banner ── */
.cust-add-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1e3a8a 100%);
    border-radius: 20px;
    padding: 28px 34px;
    margin-bottom: 24px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 35px -8px rgba(15, 23, 42, 0.4);
    border: 2px solid rgba(255, 255, 255, 0.12);
}

.cust-add-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.25) 0%, transparent 70%);
}

.cust-add-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 14px;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 11.5px;
    font-weight: 700;
    color: #93c5fd;
    margin-bottom: 10px;
}

.cust-add-title {
    font-size: 28px;
    font-weight: 900;
    margin-bottom: 6px;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
    color: #FFFFFF;
}

.cust-add-subtitle {
    font-size: 13.5px;
    color: #cbd5e1;
    margin: 0;
    max-width: 640px;
    line-height: 1.5;
}

.btn-cust-back {
    background: #FFFFFF;
    color: #0f172a;
    border: 2px solid #bfdbfe;
    font-weight: 800;
    font-size: 13px;
    padding: 9px 20px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.btn-cust-back:hover {
    background: #eff6ff;
    color: #1d4ed8;
    transform: translateY(-1px);
}

/* ── Form Section Cards ── */
.form-section-card {
    background: #FFFFFF;
    border: 2px solid #cbd5e1;
    border-radius: 18px;
    box-shadow: 0 8px 25px -4px rgba(15, 23, 42, 0.06);
    margin-bottom: 24px;
    overflow: hidden;
}

.card-utama { border-top: 5px solid #2563eb; }
.card-owner { border-top: 5px solid #8b5cf6; }
.card-alamat { border-top: 5px solid #10b981; }

.section-header {
    padding: 16px 24px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.section-title {
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    font-size: 15.5px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.form-label-custom {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #334155;
    margin-bottom: 6px;
    display: block;
}

.form-control-custom, .form-select-custom {
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    padding: 9px 14px;
    font-size: 13.5px;
    font-weight: 600;
    color: #0f172a;
    transition: all 0.2s ease;
    background-color: #ffffff;
}

.form-control-custom:focus, .form-select-custom:focus {
    border-color: #2563eb;
    background-color: #ffffff;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    outline: none;
}

/* ── Interactive Category Radio Pills ── */
.category-radio-grid {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.category-radio-label {
    flex: 1 1 calc(25% - 12px);
    min-width: 140px;
    cursor: pointer;
    margin: 0;
}

.category-radio-label input[type="radio"] {
    display: none;
}

.category-pill-box {
    border: 2px solid #cbd5e1;
    border-radius: 14px;
    padding: 12px 16px;
    text-align: center;
    font-weight: 800;
    font-size: 13px;
    color: #334155;
    background: #FFFFFF;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.category-radio-label:hover .category-pill-box {
    transform: translateY(-2px);
    border-color: #93c5fd;
    background: #eff6ff;
}

/* Checked States */
.category-radio-label input[value="INSTALLER"]:checked + .category-pill-box {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #FFFFFF;
    border-color: #3b82f6;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
}

.category-radio-label input[value="MASTER DEALER"]:checked + .category-pill-box {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #FFFFFF;
    border-color: #fbbf24;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
}

.category-radio-label input[value="DEALER"]:checked + .category-pill-box {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: #FFFFFF;
    border-color: #a78bfa;
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
}

.category-radio-label input[value="USER"]:checked + .category-pill-box {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #FFFFFF;
    border-color: #34d399;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

/* ── Dynamic Item Containers ── */
.dynamic-item-card {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 14px;
    position: relative;
    transition: all 0.2s ease;
}

.dynamic-item-card:hover {
    border-color: #cbd5e1;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.btn-add-section {
    font-size: 12.5px;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 10px;
    border: none;
    color: #FFFFFF;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}

.btn-add-pic {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
}
.btn-add-pic:hover {
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    color: #fff;
    transform: translateY(-1px);
}

.btn-add-alamat {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
}
.btn-add-alamat:hover {
    background: linear-gradient(135deg, #059669, #047857);
    color: #fff;
    transform: translateY(-1px);
}

.btn-submit-save {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border: 2px solid #3b82f6;
    color: #FFFFFF;
    font-weight: 800;
    font-size: 16px;
    padding: 14px 28px;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-submit-save:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    color: #FFFFFF;
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(37, 99, 235, 0.5);
}
</style>

<div class="cust-add-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="cust-add-breadcrumb">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Tambah Customer</span>
            </div>
            <h1 class="cust-add-title"><i class="bi bi-person-plus-fill me-2"></i>Tambah Customer Baru 🏢</h1>
            <p class="cust-add-subtitle">
                Tambahkan data master customer toko, PIC kontak, serta detail alamat lokasi ke dalam sistem database.
            </p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="customer_management.php" class="btn-cust-back">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm fw-bold mb-4" style="border-radius:14px;">
        <i class="bi bi-exclamation-octagon-fill me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<form action="customer_add.php" method="POST" enctype="multipart/form-data">
    <!-- Card 1: Informasi Utama -->
    <div class="form-section-card card-utama">
        <div class="section-header">
            <h5 class="section-title"><i class="bi bi-shop text-primary"></i> Informasi Utama Toko</h5>
            <span class="badge bg-primary text-white fw-bold px-3 py-1.5" style="border-radius:8px; font-size:11px;">Data Wajib</span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nama_toko" class="form-label-custom">Nama Toko / Perusahaan <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-building text-primary"></i></span>
                        <input type="text" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" id="nama_toko" name="nama_toko" placeholder="Contoh: Toko Berkah CCTV" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="tgl_input" class="form-label-custom">Tanggal Input <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-calendar-event text-primary"></i></span>
                        <input type="date" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" id="tgl_input" name="tgl_input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="form-label-custom mb-2">Klasifikasi Kategori Toko <span class="text-danger">*</span></label>
                <div class="category-radio-grid">
                    <label class="category-radio-label">
                        <input type="radio" name="kategori" id="kategori_installer" value="INSTALLER" checked required>
                        <div class="category-pill-box">
                            <i class="bi bi-tools"></i> INSTALLER
                        </div>
                    </label>
                    <label class="category-radio-label">
                        <input type="radio" name="kategori" id="kategori_master_dealer" value="MASTER DEALER">
                        <div class="category-pill-box">
                            <i class="bi bi-award-fill"></i> MASTER DEALER
                        </div>
                    </label>
                    <label class="category-radio-label">
                        <input type="radio" name="kategori" id="kategori_dealer" value="DEALER">
                        <div class="category-pill-box">
                            <i class="bi bi-shop-window"></i> DEALER
                        </div>
                    </label>
                    <label class="category-radio-label">
                        <input type="radio" name="kategori" id="kategori_user" value="USER">
                        <div class="category-pill-box">
                            <i class="bi bi-person-fill"></i> USER
                        </div>
                    </label>
                </div>
            </div>

            <?php if ($_SESSION['role'] == 'superadmin'): ?>
            <div class="mt-4">
                <label for="sales_id" class="form-label-custom">Assign ke Sales Representative</label>
                <select class="form-select form-select-custom" id="sales_id" name="sales_id">
                    <option value="">-- Tidak Di-assign (Umum) --</option>
                    <?php foreach ($sales_list as $sales): ?>
                        <option value="<?php echo $sales['id']; ?>"><?php echo htmlspecialchars($sales['nama_lengkap']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="mt-4">
                <label for="foto_toko" class="form-label-custom">Foto Toko (Opsional)</label>
                <input class="form-control form-control-custom" type="file" id="foto_toko" name="foto_toko" accept="image/*">
                <small class="text-muted" style="font-size:11.5px;">Format yang didukung: JPG, PNG, WEBP.</small>
            </div>
        </div>
    </div>

    <!-- Card 2: Informasi Owner / PIC -->
    <div class="form-section-card card-owner">
        <div class="section-header">
            <h5 class="section-title"><i class="bi bi-people-fill text-purple" style="color:#8b5cf6;"></i> Informasi Owner / PIC Kontak</h5>
            <button type="button" class="btn-add-section btn-add-pic" id="add-pic-btn">
                <i class="bi bi-plus-circle-fill"></i> Tambah PIC
            </button>
        </div>
        <div class="card-body p-4" id="pic-container">
            <div class="dynamic-item-card pic-item">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">Nama Owner / PIC</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-person text-secondary"></i></span>
                            <input type="text" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" name="nama_pic[]" placeholder="Contoh: Bpk. Budi Santoso">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">No. Telepon / WhatsApp PIC</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-whatsapp text-success"></i></span>
                            <input type="text" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" name="tlp_pic[]" placeholder="Contoh: 08123456789">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Informasi Alamat -->
    <div class="form-section-card card-alamat">
        <div class="section-header">
            <h5 class="section-title"><i class="bi bi-geo-alt-fill text-success" style="color:#10b981;"></i> Informasi Lokasi & Alamat Toko</h5>
            <button type="button" class="btn-add-section btn-add-alamat" id="add-address-btn">
                <i class="bi bi-plus-circle-fill"></i> Tambah Alamat
            </button>
        </div>
        <div class="card-body p-4" id="address-container">
            <div class="dynamic-item-card address-item">
                <div class="mb-3">
                    <label class="form-label-custom">Alamat Lengkap</label>
                    <textarea class="form-control form-control-custom" name="alamat[]" rows="2" placeholder="Masukkan nama jalan, nomor ruko, RT/RW, kelurahan, kecamatan..."></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">Kota / Kabupaten</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-buildings text-secondary"></i></span>
                            <input type="text" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" name="kota[]" placeholder="Contoh: Jakarta Barat, Surabaya, Medan...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Link Google Maps (URL)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-map text-primary"></i></span>
                            <input type="url" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" name="link_google_map[]" placeholder="https://maps.google.com/...">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-grid mb-5">
        <button type="submit" class="btn btn-submit-save">
            <i class="bi bi-cloud-check-fill" style="font-size:20px;"></i> Simpan Data Customer Baru
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    function addDynamicItem(containerId, templateHTML) {
        const container = document.getElementById(containerId);
        container.insertAdjacentHTML('beforeend', templateHTML);
    }

    document.body.addEventListener('click', function(e) {
        if (e.target && (e.target.classList.contains('remove-item-btn') || e.target.closest('.remove-item-btn'))) {
            const btn = e.target.classList.contains('remove-item-btn') ? e.target : e.target.closest('.remove-item-btn');
            btn.closest('.dynamic-item').remove();
        }
    });

    const picTemplate = `
        <div class="dynamic-item-card dynamic-item mt-3">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn fw-bold" style="border-radius:8px;">
                    <i class="bi bi-trash-fill me-1"></i> Hapus PIC Ini
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-custom">Nama Owner / PIC</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-person text-secondary"></i></span>
                        <input type="text" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" name="nama_pic[]" placeholder="Nama PIC">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">No. Telepon / WhatsApp PIC</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-whatsapp text-success"></i></span>
                        <input type="text" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" name="tlp_pic[]" placeholder="No. Telepon">
                    </div>
                </div>
            </div>
        </div>`;
    document.getElementById('add-pic-btn').addEventListener('click', () => addDynamicItem('pic-container', picTemplate));
    
    const addressTemplate = `
        <div class="dynamic-item-card dynamic-item mt-3">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn fw-bold" style="border-radius:8px;">
                    <i class="bi bi-trash-fill me-1"></i> Hapus Alamat Ini
                </button>
            </div>
            <div class="mb-3">
                <label class="form-label-custom">Alamat Lengkap</label>
                <textarea class="form-control form-control-custom" name="alamat[]" rows="2" placeholder="Alamat Lengkap"></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-custom">Kota / Kabupaten</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-buildings text-secondary"></i></span>
                        <input type="text" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" name="kota[]" placeholder="Kota">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Link Google Maps (URL)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-color:#cbd5e1; border-radius:12px 0 0 12px;"><i class="bi bi-map text-primary"></i></span>
                        <input type="url" class="form-control form-control-custom border-start-0" style="border-radius:0 12px 12px 0;" name="link_google_map[]" placeholder="https://maps.google.com/...">
                    </div>
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