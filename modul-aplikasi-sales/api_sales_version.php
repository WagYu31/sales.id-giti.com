<?php
/**
 * API Sales Version Check
 * Returns the latest and minimum required app version.
 * To force update: set min_version equal to latest_version.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$apkUrl = 'https://jadwal.id-giti.com/staff/download/LoewixSales-v1.9.12.apk?v=' . time();

$msg = 'Pembaruan v1.9.12: Membuka akses Titip Barang Baru di Toko (TIP TOK) tanpa batasan jadwal kunjungan Admin.';

$response = [
    'status'         => 'success',
    'latest_version' => '1.9.12',
    'min_version'    => '1.9.12',
    'version'        => '1.9.12',
    'version_code'   => 220,
    'force_update'   => true,
    'update_url'     => $apkUrl,
    'download_url'   => $apkUrl,
    'update_message' => $msg,
    'force_message'  => $msg,
    'changelog'      => $msg,
    'data' => [
        'latest_version' => '1.9.12',
        'min_version'    => '1.9.12',
        'version'        => '1.9.12',
        'version_code'   => 220,
        'force_update'   => true,
        'update_url'     => $apkUrl,
        'download_url'   => $apkUrl,
        'update_message' => $msg,
        'force_message'  => $msg,
        'changelog'      => $msg,
    ]
];

echo json_encode($response);
