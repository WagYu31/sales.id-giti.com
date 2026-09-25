<?php
/**
 * API Sales Version Check
 * Returns the latest and minimum required app version.
 * To force update: set min_version equal to latest_version.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

echo json_encode([
    'latest_version' => '1.7.0',
    'min_version'    => '1.7.0',   // ← Samakan dengan latest_version untuk WAJIB update
    'download_url'   => 'https://api-teknisi.id-giti.com/downloads/LoewixSales-latest.apk',
    'changelog'      => 'Update tarif insentif TIP TOK resmi: 4MP IP Camera Rp 30.000/unit & 2MP AHD Rp 15.000/unit, perbaikan nama model kamera, dan sinkronisasi server.',
]);
