<?php
// sales_add.php
// KODE INI SUDAH MENERAPKAN VALIDASI DAN KEAMANAN YANG BAIK. TIDAK PERLU DIUBAH.

require_once 'includes/db.php';
require_once 'includes/auth.php'; // Pastikan user login dan session ada

// Proteksi Halaman: Hanya Superadmin yang bisa mengakses
if ($_SESSION['role'] !== 'superadmin') {
    // Redirect atau tampilkan pesan error, lalu hentikan eksekusi
    $_SESSION['flash_message_error'] = "Akses ditolak. Halaman ini hanya untuk Superadmin.";
    header("Location: index.php");
    exit();
}

$page_title = 'Tambah Sales Baru';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'sales';

    if (empty($nama_lengkap) || empty($email) || empty($password)) {
        $error = "Semua kolom (Nama, Email, Password) wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM sales WHERE email = ? AND deleted_at IS NULL");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $error = "Email sudah terdaftar. Silakan gunakan email lain.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO sales (nama_lengkap, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nama_lengkap, $email, $hashed_password, $role);
            
            if($stmt->execute()){
                $_SESSION['flash_message'] = "Sales baru '".htmlspecialchars($nama_lengkap)."' berhasil ditambahkan.";
                header("Location: sales_management.php");
                exit();
            } else {
                $error = "Terjadi kesalahan saat menyimpan data ke database.";
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-person-plus-fill"></i> Tambah Sales Baru</h1>
    <a href="sales_management.php" class="btn btn-secondary">Kembali</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="sales_add.php" method="POST" class="col-md-8 col-lg-6">
            <div class="mb-3">
                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <small class="form-text text-muted">Direkomendasikan minimal 8 karakter.</small>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="sales" selected>Sales</option>
                    <option value="superadmin">Superadmin</option>
                </select>
            </div>
            <hr>
            <button type="submit" class="btn btn-primary">Simpan Sales</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>