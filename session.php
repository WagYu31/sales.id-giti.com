<?php
/**
 * Root session.php - Compatibility forwarder
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$_SESSION['id']      = $_SESSION['user_id'];
$_SESSION['jabatan'] = $_SESSION['role'] ?? 'Sales';
$_SESSION['nama']    = $_SESSION['nama_lengkap'] ?? 'User';

$idSesi   = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'] ?? 'Sales';
$namaSesi = $_SESSION['nama_lengkap'] ?? 'User';
$nmUser   = $_SESSION['nama_lengkap'] ?? 'User';
?>
