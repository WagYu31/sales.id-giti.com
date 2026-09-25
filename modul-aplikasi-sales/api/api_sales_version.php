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

$apkUrl = 'https://jadwal.id-giti.com/staff/download/LoewixSales-v1.9.8.apk?v=' . time();

$response = [
    'status'         => 'success',
    'latest_version' => '1.9.8',
    'min_version'    => '1.9.8',
    'version'        => '1.9.8',
    'version_code'   => 198,
    'force_update'   => true,
    'update_url'     => $apkUrl,
    'download_url'   => $apkUrl,
    'update_message' => 'Versi terbaru (v1.9.8) tersedia! Update tarif insentif resmi TIP TOK: 4MP IP Camera Rp 30.000/unit & 2MP AHD Rp 15.000/unit.',
    'force_message'  => 'Versi aplikasi Anda perlu diperbarui ke v1.9.8 untuk sinkronisasi tarif insentif terbaru.',
    'changelog'      => 'Versi terbaru (v1.9.8) tersedia! Update tarif insentif resmi TIP TOK: 4MP IP Camera Rp 30.000/unit & 2MP AHD Rp 15.000/unit.',
    'data' => [
        'latest_version' => '1.9.8',
        'min_version'    => '1.9.8',
        'version'        => '1.9.8',
        'version_code'   => 198,
        'force_update'   => true,
        'update_url'     => $apkUrl,
        'download_url'   => $apkUrl,
        'update_message' => 'Versi terbaru (v1.9.8) tersedia! Update tarif insentif resmi TIP TOK: 4MP IP Camera Rp 30.000/unit & 2MP AHD Rp 15.000/unit.',
        'force_message'  => 'Versi aplikasi Anda perlu diperbarui ke v1.9.8 untuk sinkronisasi tarif insentif terbaru.',
        'changelog'      => 'Versi terbaru (v1.9.8) tersedia! Update tarif insentif resmi TIP TOK: 4MP IP Camera Rp 30.000/unit & 2MP AHD Rp 15.000/unit.',
    ]
];

echo json_encode($response);
