<?php
/**
 * get-user-data.php - Modul Aplikasi Sales
 * User metadata & helper functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idSesi    = $_SESSION['user_id'] ?? 0;
$userRole  = $_SESSION['role'] ?? 'sales';
$role      = $_SESSION['role'] ?? 'sales';
$namaSesi  = $_SESSION['nama_lengkap'] ?? 'User';
$nmUser    = $_SESSION['nama_lengkap'] ?? 'User';
$userEmail = $_SESSION['username'] ?? '';

if (!function_exists('formatTanggal')) {
    function formatTanggal($format = 'dd MMMM yyyy', $date = null) {
        $timestamp = $date ? strtotime($date) : time();
        if (!$timestamp) $timestamp = time();

        $hariArr = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
        ];

        $bulanArr = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $dayName   = $hariArr[date('l', $timestamp)] ?? date('l', $timestamp);
        $monthName = $bulanArr[(int)date('n', $timestamp)] ?? date('F', $timestamp);
        $d         = date('d', $timestamp);
        $m         = date('m', $timestamp);
        $y         = date('Y', $timestamp);

        if ($format === 'EEEE') {
            return $dayName;
        } elseif ($format === 'MMMM') {
            return $monthName;
        } elseif ($format === 'MMMM yyyy') {
            return "$monthName $y";
        } elseif ($format === 'dd MMMM yyyy' || $format === 'd MMMM yyyy') {
            return "$d $monthName $y";
        } elseif ($format === 'EEEE, dd MMMM yyyy') {
            return "$dayName, $d $monthName $y";
        }

        return date('d M Y', $timestamp);
    }
}
?>
