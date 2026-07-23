<?php
// customer_edit.php
$page_title = 'Edit Customer';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: login.php');
    exit();
}

$customer_id = (int)$_GET['id'];

// Logika pemrosesan form (POST) dipindahkan ke atas
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        // Ambil data sales_id dari customer yang sedang diedit untuk fallback
        $stmt_fallback = $conn->prepare("SELECT sales_id, foto_toko FROM customers WHERE id = ?");
        $stmt_fallback->bind_param("i", $customer_id);
        $stmt_fallback->execute();
        $customer_fallback = $stmt_fallback->get_result()->fetch_assoc();
        $stmt_fallback->close();
        
        $nama_toko = $_POST['nama_toko'];
        $tgl_input = $_POST['tgl_input'];
        $kategori = $_POST['kategori'];
        $sales_id = ($_SESSION['role'] == 'superadmin') ? ($_POST['sales_id'] ?: null) : $customer_fallback['sales_id'];

        $foto_toko = $customer_fallback['foto_toko'];
        if (isset($_FILES['foto_toko']) && $_FILES['foto_toko']['error'] == 0) {
            if ($foto_toko && file_exists("assets/uploads/" . $foto_toko)) unlink("assets/uploads/" . $foto_toko);
            $target_dir = "assets/uploads/";
            $foto_toko = uniqid() . '-' . basename($_FILES["foto_toko"]["name"]);
            $target_file = $target_dir . $foto_toko;
            if (!move_uploaded_file($_FILES["foto_toko"]["tmp_name"], $target_file)) throw new Exception("Gagal mengunggah file baru.");
        }

        $stmt_update_customer = $conn->prepare("UPDATE customers SET sales_id=?, tgl_input=?, nama_toko=?, kategori=?, foto_toko=? WHERE id=?");
        $stmt_update_customer->bind_param("issssi", $sales_id, $tgl_input, $nama_toko, $kategori, $foto_toko, $customer_id);
        if (!$stmt_update_customer->execute()) throw new Exception("Gagal update data utama: " . $stmt_update_customer->error);
        $stmt_update_customer->close();
        
        // Ambil data PICs yang ada untuk perbandingan
        $pics_result = $conn->query("SELECT id FROM customer_pics WHERE customer_id = $customer_id AND deleted_at IS NULL");
        $existing_pic_ids = array_column($pics_result->fetch_all(MYSQLI_ASSOC), 'id');
        $submitted_pic_ids = isset($_POST['pic_id']) ? array_filter($_POST['pic_id']) : [];
        $pics_to_delete = array_diff($existing_pic_ids, $submitted_pic_ids);

        if (!empty($pics_to_delete)) {
            $stmt_delete_pic = $conn->prepare("UPDATE customer_pics SET deleted_at=NOW() WHERE id = ?");
            foreach ($pics_to_delete as $pic_id_to_delete) {
                $stmt_delete_pic->bind_param("i", $pic_id_to_delete);
                $stmt_delete_pic->execute();
            }
            $stmt_delete_pic->close();
        }

        if (isset($_POST['nama_pic'])) {
            foreach ($_POST['nama_pic'] as $key => $nama_pic) {
                if (!empty($nama_pic)) {
                    $pic_id = $_POST['pic_id'][$key] ?? null;
                    $tlp_pic = $_POST['tlp_pic'][$key] ?? '';
                    if ($pic_id) {
                        $stmt = $conn->prepare("UPDATE customer_pics SET nama_pic=?, tlp_pic=? WHERE id=?");
                        $stmt->bind_param("ssi", $nama_pic, $tlp_pic, $pic_id);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO customer_pics (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $customer_id, $nama_pic, $tlp_pic);
                    }
                    if (!$stmt->execute()) throw new Exception("Gagal sinkronisasi PIC: " . $stmt->error);
                    $stmt->close();
                }
            }
        }
        
        // Ambil data alamat yang ada untuk perbandingan
        $addr_result = $conn->query("SELECT id FROM customer_addresses WHERE customer_id = $customer_id AND deleted_at IS NULL");
        $existing_address_ids = array_column($addr_result->fetch_all(MYSQLI_ASSOC), 'id');
        $submitted_address_ids = isset($_POST['address_id']) ? array_filter($_POST['address_id']) : [];
        $addresses_to_delete = array_diff($existing_address_ids, $submitted_address_ids);

        if (!empty($addresses_to_delete)) {
            $stmt_delete_addr = $conn->prepare("UPDATE customer_addresses SET deleted_at=NOW() WHERE id = ?");
            foreach ($addresses_to_delete as $id_to_delete) {
                $stmt_delete_addr->bind_param("i", $id_to_delete);
                $stmt_delete_addr->execute();
            }
            $stmt_delete_addr->close();
        }

        if (isset($_POST['alamat'])) {
            foreach ($_POST['alamat'] as $key => $alamat) {
                if (!empty($alamat)) {
                    $address_id = $_POST['address_id'][$key] ?? null;
                    $kota = $_POST['kota'][$key] ?? '';
                    $link_google_map = $_POST['link_google_map'][$key] ?? '';
                    if ($address_id) {
                        $stmt = $conn->prepare("UPDATE customer_addresses SET alamat=?, kota=?, link_google_map=? WHERE id=?");
                        $stmt->bind_param("sssi", $alamat, $kota, $link_google_map, $address_id);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO customer_addresses (customer_id, alamat, kota, link_google_map) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isss", $customer_id, $alamat, $kota, $link_google_map);
                    }
                    if (!$stmt->execute()) throw new Exception("Gagal sinkronisasi alamat: " . $stmt->error);
                    $stmt->close();
                }
            }
        }
        
        $conn->commit();
        $_SESSION['flash_message'] = "Data customer berhasil diperbarui!";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Logika pengambilan data untuk tampilan (GET)
$stmt_main = $conn->prepare("SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL");
$stmt_main->bind_param("i", $customer_id);
$stmt_main->execute();
$result_main = $stmt_main->get_result();
if ($result_main->num_rows === 0) die("Customer tidak ditemukan atau telah dihapus.");
$customer = $result_main->fetch_assoc();
$stmt_main->close();

if ($_SESSION['role'] != 'superadmin' && $_SESSION['user_id'] != $customer['sales_id']) {
    die("Anda tidak memiliki izin untuk mengakses halaman ini.");
}

$customer_pics = [];
$stmt_pics = $conn->prepare("SELECT id, nama_pic, tlp_pic FROM customer_pics WHERE customer_id = ? AND deleted_at IS NULL ORDER BY id");
$stmt_pics->bind_param("i", $customer_id);
$stmt_pics->execute();
$result_pics = $stmt_pics->get_result();
while ($row = $result_pics->fetch_assoc()) {
    $customer_pics[] = $row;
}
$stmt_pics->close();

$customer_addresses = [];
$stmt_addresses = $conn->prepare("SELECT id, alamat, kota, link_google_map FROM customer_addresses WHERE customer_id = ? AND deleted_at IS NULL ORDER BY id");
$stmt_addresses->bind_param("i", $customer_id);
$stmt_addresses->execute();
$result_addresses = $stmt_addresses->get_result();
while ($row = $result_addresses->fetch_assoc()) {
    $customer_addresses[] = $row;
}
$stmt_addresses->close();

$sales_list = [];
if ($_SESSION['role'] == 'superadmin') {
    $result_sales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL");
    while ($row = $result_sales->fetch_assoc()) {
        $sales_list[] = $row;
    }
}

// Tampilkan HTML setelah semua proses selesai
require_once 'includes/header.php';
$page_title = 'Edit Customer: ' . htmlspecialchars($customer['nama_toko']);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-pencil-square"></i> Edit Customer: <?php echo htmlspecialchars($customer['nama_toko']); ?></h1>
    <a href="index.php" class="btn btn-secondary">Kembali</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<form action="customer_edit.php?id=<?php echo $customer_id; ?>" method="POST" enctype="multipart/form-data">
    <div class="card mb-4">
        <div class="card-header"><h5>Informasi Utama</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><label for="nama_toko" class="form-label">Nama Toko <span class="text-danger">*</span></label><input type="text" class="form-control" id="nama_toko" name="nama_toko" value="<?php echo htmlspecialchars($customer['nama_toko']); ?>" required></div>
                <div class="col-md-6 mb-3"><label for="tgl_input" class="form-label">Tanggal Input <span class="text-danger">*</span></label><input type="date" class="form-control" id="tgl_input" name="tgl_input" value="<?php echo htmlspecialchars($customer['tgl_input']); ?>" required></div>
            </div>
            <div class="mb-3">
                <label class="form-label">Kategori <span class="text-danger">*</span></label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="kategori" id="kategori_installer" value="INSTALLER" <?php if ($customer['kategori'] == 'INSTALLER') echo 'checked'; ?> required>
                        <label class="form-check-label" for="kategori_installer">INSTALLER</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="kategori" id="kategori_master_dealer" value="MASTER DEALER" <?php if ($customer['kategori'] == 'MASTER DEALER') echo 'checked'; ?>>
                        <label class="form-check-label" for="kategori_master_dealer">MASTER DEALER</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="kategori" id="kategori_dealer" value="DEALER" <?php if ($customer['kategori'] == 'DEALER') echo 'checked'; ?>>
                        <label class="form-check-label" for="kategori_dealer">DEALER</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="kategori" id="kategori_user" value="USER" <?php if ($customer['kategori'] == 'USER') echo 'checked'; ?>>
                        <label class="form-check-label" for="kategori_user">USER</label>
                    </div>
                    <!--<div class="form-check form-check-inline">-->
                    <!--    <input class="form-check-input" type="radio" name="kategori" id="kategori_si" value="SI" <?php if ($customer['kategori'] == 'SI') echo 'checked'; ?>>-->
                    <!--    <label class="form-check-label" for="kategori_si">SI</label>-->
                    <!--</div>-->
                </div>
            </div>
            <?php if ($_SESSION['role'] == 'superadmin'): ?>
            <div class="mb-3">
                <label for="sales_id" class="form-label">Assign ke Sales</label>
                <select class="form-select" id="sales_id" name="sales_id">
                    <option value="">-- Tidak Di-assign --</option>
                    <?php foreach ($sales_list as $sales): ?>
                        <option value="<?php echo $sales['id']; ?>" <?php if ($sales['id'] == $customer['sales_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($sales['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="mb-3">
                <label for="foto_toko" class="form-label">Ganti Foto Toko (Opsional)</label>
                <input class="form-control" type="file" id="foto_toko" name="foto_toko" accept="image/*">
                <?php if ($customer['foto_toko']): ?>
                    <div class="mt-2"><small>Foto saat ini: <a href="assets/uploads/<?php echo htmlspecialchars($customer['foto_toko']); ?>" target="_blank"><?php echo htmlspecialchars($customer['foto_toko']); ?></a></small></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Informasi Owner / PIC</h5>
            <button type="button" class="btn btn-sm btn-primary" id="add-pic-btn"><i class="bi bi-plus-circle"></i> Tambah PIC</button>
        </div>
        <div class="card-body" id="pic-container">
            <?php if (!empty($customer_pics)): foreach ($customer_pics as $pic): ?>
            <div class="row pic-item mb-3 dynamic-item">
                <input type="hidden" name="pic_id[]" value="<?php echo $pic['id']; ?>">
                <div class="col-md-6 mb-2"><label class="form-label">Nama Owner / PIC</label><input type="text" class="form-control" name="nama_pic[]" value="<?php echo htmlspecialchars($pic['nama_pic']); ?>" placeholder="Nama Owner / PIC"></div>
                <div class="col-md-5 mb-2"><label class="form-label">No. Telepon PIC (Contoh : 081234xxxxxxxx)</label><input type="text" class="form-control" name="tlp_pic[]" value="<?php echo htmlspecialchars($pic['tlp_pic']); ?>" placeholder="No. Telepon"></div>
                <div class="col-md-1 mb-2 d-flex align-items-end"><button type="button" class="btn btn-danger remove-item-btn w-100"><i class="bi bi-trash"></i></button></div>
            </div>
            <?php endforeach; else: ?>
             <div class="row pic-item mb-3">
                <input type="hidden" name="pic_id[]" value="">
                <div class="col-md-6 mb-2"><label class="form-label">Nama Owner / PIC</label><input type="text" class="form-control" name="nama_pic[]" placeholder="Nama PIC"></div>
                <div class="col-md-6 mb-2"><label class="form-label">No. Telepon PIC</label><input type="text" class="form-control" name="tlp_pic[]" placeholder="No. Telepon"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Informasi Alamat</h5>
            <button type="button" class="btn btn-sm btn-primary" id="add-address-btn"><i class="bi bi-plus-circle"></i> Tambah Alamat</button>
        </div>
        <div class="card-body" id="address-container">
            <?php if (!empty($customer_addresses)): foreach ($customer_addresses as $address): ?>
            <div class="address-item border rounded p-3 mb-3 dynamic-item">
                <input type="hidden" name="address_id[]" value="<?php echo $address['id']; ?>">
                <div class="d-flex justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn mb-2"><i class="bi bi-trash"></i> Hapus Alamat Ini</button></div>
                <div class="mb-2"><label class="form-label">Alamat Lengkap</label><textarea class="form-control" name="alamat[]" rows="2" placeholder="Alamat Lengkap"><?php echo htmlspecialchars($address['alamat']); ?></textarea></div>
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="form-label">Kota</label><input type="text" class="form-control" name="kota[]" value="<?php echo htmlspecialchars($address['kota']); ?>" placeholder="Kota"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">Link Google Map</label><input type="url" class="form-control" name="link_google_map[]" value="<?php echo htmlspecialchars($address['link_google_map']); ?>" placeholder="http://googleusercontent.com/maps/google.com/0..."></div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="address-item border rounded p-3 mb-3">
                 <input type="hidden" name="address_id[]" value="">
                 <div class="mb-2"><label class="form-label">Alamat Lengkap</label><textarea class="form-control" name="alamat[]" rows="2" placeholder="Alamat Lengkap"></textarea></div>
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="form-label">Kota</label><input type="text" class="form-control" name="kota[]" placeholder="Kota"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">Link Google Map</label><input type="url" class="form-control" name="link_google_map[]" placeholder="http://googleusercontent.com/maps/google.com/0..."></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="d-grid">
        <button type="submit" class="btn btn-primary btn-lg">Simpan Perubahan</button>
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
            e.target.closest('.dynamic-item').remove();
        }
    });

    const picTemplate = `
        <div class="row pic-item mb-3 dynamic-item">
            <input type="hidden" name="pic_id[]" value="">
            <div class="col-md-6 mb-2"><input type="text" class="form-control" name="nama_pic[]" placeholder="Nama Owner / PIC"></div>
            <div class="col-md-5 mb-2"><input type="text" class="form-control" name="tlp_pic[]" placeholder="No. Telepon"></div>
            <div class="col-md-1 mb-2 d-flex align-items-end"><button type="button" class="btn btn-danger remove-item-btn w-100"><i class="bi bi-trash"></i></button></div>
        </div>`;
    document.getElementById('add-pic-btn').addEventListener('click', () => addDynamicItem('pic-container', picTemplate));

    const addressTemplate = `
        <div class="address-item border rounded p-3 mb-3 dynamic-item">
            <input type="hidden" name="address_id[]" value="">
            <div class="d-flex justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn mb-2"><i class="bi bi-trash"></i> Hapus Alamat Ini</button></div>
            <div class="mb-2"><label class="form-label">Alamat Lengkap</label><textarea class="form-control" name="alamat[]" rows="2" placeholder="Alamat Lengkap"></textarea></div>
            <div class="row">
                <div class="col-md-6 mb-2"><label class="form-label">Kota</label><input type="text" class="form-control" name="kota[]" placeholder="Kota"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Link Google Map</label><input type="url" class="form-control" name="link_google_map[]" placeholder="http://googleusercontent.com/maps/google.com/0..."></div>
            </div>
        </div>`;
    document.getElementById('add-address-btn').addEventListener('click', () => addDynamicItem('address-container', addressTemplate));
});
</script>

<?php require_once 'includes/footer.php'; ?>