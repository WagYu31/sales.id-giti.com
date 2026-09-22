<?php
/**
 * session.php - Modul Aplikasi Sales
 * Memvalidasi sesi login terpusat dari sales.id-giti.com
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login di sales.id-giti.com
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ── Bridge Pemetaan Session untuk Kompatibilitas Modul Sales ────────────────
$_SESSION['id']      = $_SESSION['user_id'];
$_SESSION['jabatan'] = $_SESSION['role'] ?? 'Sales';
$_SESSION['nama']    = $_SESSION['nama_lengkap'] ?? 'User';

$idSesi   = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'] ?? 'Sales';
$namaSesi = $_SESSION['nama_lengkap'] ?? 'User';
$nmUser   = $_SESSION['nama_lengkap'] ?? 'User';
?>
