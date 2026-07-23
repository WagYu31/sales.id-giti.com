<?php
// sales_edit.php
// KODE INI SUDAH MENERAPKAN LOGIKA DAN KEAMANAN TERBAIK. TIDAK PERLU DIUBAH.

require_once 'includes/db.php';
require_once 'includes/auth.php'; // Pastikan user login dan session ada

// Proteksi Halaman: Hanya Superadmin yang bisa mengakses
if ($_SESSION['role'] !== 'superadmin' || !isset($_GET['id'])) {
    $_SESSION['flash_message_error'] = "Akses ditolak.";
    header("Location: index.php");
    exit();
}

$sales_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$sales_id) {
    die("ID Sales tidak valid.");
}

$page_title = 'Edit Data Sales';
$error = '';

// 1. Logika untuk mengambil data sales yang akan diedit
$stmt_get = $conn->prepare("SELECT nama_lengkap, email, role FROM sales WHERE id = ? AND deleted_at IS NULL");
$stmt_get->bind_param("i", $sales_id);
$stmt_get->execute();
$result_get = $stmt_get->get_result();
if ($result_get->num_rows === 0) {
    die("Sales tidak ditemukan atau telah dihapus.");
}
$sales = $result_get->fetch_assoc();
$stmt_get->close();


// 2. Logika untuk memproses update data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'sales';

    if (empty($nama_lengkap) || empty($email)) {
        $error = "Nama Lengkap dan Email wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM sales WHERE email = ? AND id != ? AND deleted_at IS NULL");
        $stmt_check->bind_param("si", $email, $sales_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $error = "Email sudah digunakan oleh sales lain.";
        }
        $stmt_check->close();
    }
    
    if (empty($error)) {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE sales SET nama_lengkap = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $nama_lengkap, $email, $role, $hashed_password, $sales_id);
        } else {
            $stmt_update = $conn->prepare("UPDATE sales SET nama_lengkap = ?, email = ?, role = ? WHERE id = ?");
            $stmt_update->bind_param("sssi", $nama_lengkap, $email, $role, $sales_id);
        }
        
        if ($stmt_update->execute()) {
            $_SESSION['flash_message'] = "Data sales '".htmlspecialchars($nama_lengkap)."' berhasil diperbarui.";
            header("Location: sales_management.php");
            exit();
        } else {
            $error = "Gagal memperbarui data.";
        }
        $stmt_update->close();
    }
    
    // Jika ada error, isi kembali variabel $sales agar form menampilkan data yang baru diinput
    $sales['nama_lengkap'] = $nama_lengkap;
    $sales['email'] = $email;
    $sales['role'] = $role;
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-pencil-square"></i> Edit Data Sales</h1>
    <a href="sales_management.php" class="btn btn-secondary">Kembali</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="sales_edit.php?id=<?php echo $sales_id; ?>" method="POST" class="col-md-8 col-lg-6">
            <div class="mb-3">
                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required value="<?php echo htmlspecialchars($sales['nama_lengkap']); ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($sales['email']); ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password Baru (Opsional)</label>
                <input type="password" class="form-control" id="password" name="password">
                <small class="form-text text-muted">Kosongkan kolom ini jika Anda tidak ingin mengubah password.</small>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="sales" <?php if($sales['role'] == 'sales') echo 'selected'; ?>>Sales</option>
                    <option value="superadmin" <?php if($sales['role'] == 'superadmin') echo 'selected'; ?>>Superadmin</option>
                </select>
            </div>
            <hr>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>