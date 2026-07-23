<?php
// login.php
require_once 'includes/db.php';
$error = '';

// Jika pengguna sudah login, arahkan ke index.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Proses form jika metode adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Siapkan statement untuk mengambil data sales berdasarkan email
    $stmt = $conn->prepare("SELECT id, nama_lengkap, password, role FROM sales WHERE email = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Cek jika pengguna ditemukan
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verifikasi password yang diinput dengan hash di database
        if (password_verify($password, $user['password'])) {
            // Jika password cocok, buat session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
    
            // Arahkan pengguna berdasarkan peran (role)
            if ($_SESSION['role'] === 'adminsales') {
                header("Location: promosi_management.php");
            } else {
                header("Location: customer_management.php");
            }
            exit();
        }
    }
    
    // Jika email tidak ditemukan atau password tidak cocok, tampilkan error
    $error = "Email atau password salah!";
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Laporan Prospek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin: 100px auto; padding: 30px; box-shadow: 0 0 15px rgba(0,0,0,0.1); border-radius: 10px; background-color: #fff; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">Login Laporan Prospek</h2>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
             <p class="mt-3 text-center">Belum punya akun? <a href="signup.php">Daftar di sini</a></p>
        </form>
    </div>
</body>
</html>