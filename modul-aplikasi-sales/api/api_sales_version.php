<?php
/**
 * API Sales Version Check
 * Returns the latest and minimum required app version.
 * To force update: set min_version equal to latest_version.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$response = [
    'status'         => 'success',
    'latest_version' => '1.9.3',
    'min_version'    => '1.9.3',
    'version'        => '1.9.3',
    'version_code'   => 193,
    'force_update'   => true,
    'download_url'   => 'https://api-teknisi.id-giti.com/downloads/LoewixSales-latest.apk',
    'changelog'      => 'Versi terbaru (v1.9.3) tersedia! Update tarif insentif resmi TIP TOK: 4MP IP Camera Rp 30.000/unit & 2MP AHD Rp 15.000/unit, perbaikan nama model kamera, dan sinkronisasi server.',
    'data' => [
        'latest_version' => '1.9.3',
        'min_version'    => '1.9.3',
        'version'        => '1.9.3',
        'version_code'   => 193,
        'force_update'   => true,
        'download_url'   => 'https://api-teknisi.id-giti.com/downloads/LoewixSales-latest.apk',
        'changelog'      => 'Versi terbaru (v1.9.3) tersedia! Update tarif insentif resmi TIP TOK: 4MP IP Camera Rp 30.000/unit & 2MP AHD Rp 15.000/unit, perbaikan nama model kamera, dan sinkronisasi server.',
    ]
];

echo json_encode($response);
