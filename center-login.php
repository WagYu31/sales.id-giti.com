<?php
include("includes/db.php");

// Proses form login jika tombol login ditekan
$nama = $_GET["nama"];
$login_err = "";

$getNama = "SELECT * FROM sales WHERE nama_lengkap = '$nama'";
$resNama = mysqli_query($conn, $getNama);
$rowNama = mysqli_fetch_array($resNama);
$email = $rowNama['email'];
$password = $rowNama['password'];

// Query untuk mencari pengguna dengan username yang cocok
$sql = "SELECT id, email, nama_lengkap, role, password FROM sales WHERE email = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $param_email);
    $param_email = $email;
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $user_id, $email, $name, $role, $stored_password);
            if (mysqli_stmt_fetch($stmt)) {
                if ($password === $stored_password) {
                    session_start();
                    $_SESSION["email"] = $email;
                    $_SESSION["user_id"] = $user_id;
                    $_SESSION["nama_lengkap"] = $name;
                    $_SESSION["role"] = $role;

                    header("Location: index.php");
                    exit();
                } else {
                    // Username or email not found
                    $_SESSION['alert'] = "User not found.";
                    header('Location: login.php');
                    exit();
                }
            }
        } else {
            // Username or email not found
            $_SESSION['alert'] = "User not found.";
            header('Location: login.php');
            exit();
        }
    } else {
        $_SESSION['alert'] = "Error: Could not prepare the statement.";
        header('Location: login.php');
        exit();
    }
    mysqli_stmt_close($stmt);
}

// Tutup koneksi database
mysqli_close($conn);

// Jika terjadi kesalahan saat login, alihkan ke halaman login.php dengan pesan kesalahan
if ($login_err) {
    // Login gagal, alihkan ke halaman login.php dengan pesan kesalahan
    header("location: index.php?login=failed");
    exit();
}
