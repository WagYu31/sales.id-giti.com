<?php
// logout.php
session_start(); // Mulai sesi untuk mengaksesnya
session_unset(); // Hapus semua variabel sesi
session_destroy(); // Hancurkan sesi

// Arahkan kembali ke halaman login
header("Location: login.php");
exit();
?>